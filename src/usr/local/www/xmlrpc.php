<?php
/*
 * xmlrpc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-xmlrpclibrary
##|*NAME=XMLRPC Library
##|*DESCR=Allow access to the 'XMLRPC Library' page.
##|*MATCH=xmlrpc.php*
##|-PRIV

require_once("config.inc");
require_once("functions.inc");
require_once("auth.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("captiveportal.inc");
require_once("shaper.inc");
require_once("XML/RPC2/Server.php");

class pfsense_xmlrpc_server {

	private $loop_detected = false;
	private $remote_addr;

	private function auth() {
		global $config;
		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];

		$login_ok = false;
		if (!empty($username) && !empty($password)) {
			$attributes = array();
			$authcfg = auth_get_authserver(
			    $config['system']['webgui']['authmode']);

			if (authenticate_user($username, $password,
			    $authcfg, $attributes) ||
			    authenticate_user($username, $password)) {
				$login_ok = true;
			}
		}

		if (!$login_ok) {
			log_auth("webConfigurator authentication error for '" .
			    $username . "' from " . $this->remote_addr);

			require_once("XML/RPC2/Exception.php");
			throw new XML_RPC2_FaultException(gettext(
			    'Authentication failed: Invalid username or password'),
			    -1);
		}

		$user_entry = getUserEntry($username);
		/*
		 * admin (uid = 0) is allowed
		 * or regular user with necessary privilege
		 */
		if (isset($user_entry['uid']) && $user_entry['uid'] != '0' &&
		    !userHasPrivilege($user_entry, 'system-xmlrpc-ha-sync')) {
			log_auth("webConfigurator authentication error for '" .
			    $username . "' from " . $this->remote_addr .
			    " not enough privileges");

			require_once("XML/RPC2/Exception.php");
			throw new XML_RPC2_FaultException(gettext(
			    'Authentication failed: not enough privileges'),
			    -2);
		}

		return;
	}

	private function array_overlay($a1, $a2) {
		foreach ($a1 as $k => $v) {
			if (!array_key_exists($k, $a2)) {
				continue;
			}
			if (is_array($v) && is_array($a2[$k])) {
				$a1[$k] = $this->array_overlay($v, $a2[$k]);
			} else {
				$a1[$k] = $a2[$k];
			}
		}

		return $a1;
	}

	public function __construct() {
		global $config;

		$this->remote_addr = $_SERVER['REMOTE_ADDR'];

		/* grab sync to ip if enabled */
		if (isset($config['hasync']['synchronizetoip']) &&
		    $config['hasync']['synchronizetoip'] == $this->remote_addr) {
			$this->loop_detected = true;
		}
	}

	/**
	 * Get host version information
	 *
	 * @return array
	 */
	public function host_firmware_version($dummy = 1) {
		$this->auth();
		return host_firmware_version();
	}

	/**
	 * Executes a PHP block of code
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public function exec_php($code) {
		$this->auth();

		eval($code);
		if ($toreturn) {
			return $toreturn;
		}

		return true;
	}

	/**
	 * Executes shell commands
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public function exec_shell($code) {
		$this->auth();

		mwexec($code);
		return true;
	}

	/**
	 * Backup chosen config sections
	 *
	 * @param array $section
	 *
	 * @return array
	 */
	public function backup_config_section($section) {
		$this->auth();

		global $config;

		return array_intersect_key($config, array_flip($section));
	}

	/**
	 * Restore defined config section into local config
	 *
	 * @param array $sections
	 *
	 * @return bool
	 */
	public function restore_config_section($sections) {
		$this->auth();

		global $config, $cpzone, $cpzoneid;

		$old_config = $config;
		$old_ipsec_enabled = ipsec_enabled();

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		/*
		 * Some sections should just be copied and not merged or we end
		 * up unable to sync the deletion of the last item in a section
		 */
		$sync_full_sections = array(
			'aliases',
			'ca',
			'cert',
			'crl',
			'dhcpd',
			'dhcpv6',
			'dnsmasq',
			'filter',
			'ipsec',
			'nat',
			'openvpn',
			'schedules',
			'unbound',
			'wol',
		);

		$syncd_full_sections = array();

		foreach ($sync_full_sections as $section) {
			if (!isset($sections[$section])) {
				continue;
			}

			$config[$section] = $sections[$section];
			unset($sections[$section]);
			$syncd_full_sections[] = $section;
		}

		/* Create a list of CP zones to be deleted locally */
		$cp_to_del = array();
		if (is_array($config['captiveportal'])) {
			if (is_array($sections['captiveportal'])) {
				$remote_cp = $sections['captiveportal'];
			} else {
				$remote_cp = array();
			}
			foreach ($config['captiveportal'] as $zone => $item) {
				if (!isset($remote_cp[$zone])) {
					$cp_to_del[] = $zone;
				}
			}
			unset($remote_cp);
		}

		/* Only touch users if users are set to synchronize from the primary node
		 * See https://redmine.pfsense.org/issues/8450
		 */
		if ($sections['system']['user'] && $sections['system']['group']) {
			$g2add = array();
			$g2del = array();
			$g2del_idx = array();
			$g2keep = array();
			if (is_array($sections['system']['group'])) {
				$local_groups = isset($config['system']['group'])
				    ? $config['system']['group']
				    : array();

				foreach ($sections['system']['group'] as $group) {
					$idx = array_search($group['name'],
					    array_column($local_groups, 'name'));

					if ($idx === false) {
						$g2add[] = $group;
					} else if ($group['gid'] < 1999) {
						$g2keep[] = $idx;
					} else if ($group != $local_groups[$idx]) {
						$g2add[] = $group;
						$g2del[] = $group;
						$g2del_idx[] = $idx;
					} else {
						$g2keep[] = $idx;
					}
				}
			}
			if (is_array($config['system']['group'])) {
				foreach ($config['system']['group'] as $idx => $group) {
					if (array_search($idx, $g2keep) === false &&
					    array_search($idx, $g2del_idx) === false) {
						$g2del[] = $group;
						$g2del_idx[] = $idx;
					}
				}
			}
			unset($sections['system']['group'], $g2keep, $g2del_idx);

			$u2add = array();
			$u2del = array();
			$u2del_idx = array();
			$u2keep = array();
			if (is_array($sections['system']['user'])) {
				$local_users = isset($config['system']['user'])
				    ? $config['system']['user']
				    : array();

				foreach ($sections['system']['user'] as $user) {
					$idx = array_search($user['name'],
					    array_column($local_users, 'name'));

					if ($idx === false) {
						$u2add[] = $user;
					} else if (($user['uid'] < 2000) && ($sections['hasync']['adminsync'] != 'on')) {
						$u2keep[] = $idx;
					} else if ($user != $local_users[$idx]) {
						$u2add[] = $user;
						$u2del[] = $user;
						$u2del_idx[] = $idx;
					} else {
						$u2keep[] = $idx;
					}
				}
			}
			if (is_array($config['system']['user'])) {
				foreach ($config['system']['user'] as $idx => $user) {
					if (array_search($idx, $u2keep) === false &&
					    array_search($idx, $u2del_idx) === false) {
						$u2del[] = $user;
						$u2del_idx[] = $idx;
					}
				}
			}
			unset($sections['system']['user'], $u2keep, $u2del_idx);
		}

		$voucher = array();
		if (is_array($sections['voucher'])) {
			/* Save voucher rolls to process after merge */
			$voucher = $sections['voucher'];

			foreach($sections['voucher'] as $zone => $item) {
				unset($sections['voucher'][$zone]['roll']);
				if (isset($config['voucher'][$zone]['vouchersyncdbip'])) {
					$sections['voucher'][$zone]['vouchersyncdbip'] =
					    $config['voucher'][$zone]['vouchersyncdbip'];
				} else {
					unset($sections['voucher'][$zone]['vouchersyncdbip']);
				}
				if (isset($config['voucher'][$zone]['vouchersyncport'])) {
					$sections['voucher'][$zone]['vouchersyncport'] =
					    $config['voucher'][$zone]['vouchersyncport'];
				} else {
					unset($sections['voucher'][$zone]['vouchersyncport']);
				}
				if (isset($config['voucher'][$zone]['vouchersyncusername'])) {
					$sections['voucher'][$zone]['vouchersyncusername'] =
					    $config['voucher'][$zone]['vouchersyncusername'];
				} else {
					unset($sections['voucher'][$zone]['vouchersyncusername']);
				}
				if (isset($config['voucher'][$zone]['vouchersyncpass'])) {
					$sections['voucher'][$zone]['vouchersyncpass'] =
					    $config['voucher'][$zone]['vouchersyncpass'];
				} else {
					unset($sections['voucher'][$zone]['vouchersyncpass']);
				}
			}
		}

		$vipbackup = array();
		$oldvips = array();
		if (isset($sections['virtualip']) &&
		    is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vip) {
				if ($vip['mode'] == "carp") {
					$key = $vip['interface'] .
					    "_vip" . $vip['vhid'];

					$oldvips[$key]['content'] =
					    $vip['password'] .
					    $vip['advskew'] .
					    $vip['subnet'] .
					    $vip['subnet_bits'] .
					    $vip['advbase'];
					$oldvips[$key]['interface'] =
					    $vip['interface'];
					$oldvips[$key]['subnet'] =
					    $vip['subnet'];
				} else if ($vip['mode'] == "ipalias" &&
				    (substr($vip['interface'], 0, 4) == '_vip'
				    || strstr($vip['interface'], "lo0"))) {
					$oldvips[$vip['subnet']]['content'] =
					    $vip['interface'] .
					    $vip['subnet'] .
					    $vip['subnet_bits'];
					$oldvips[$vip['subnet']]['interface'] =
					    $vip['interface'];
					$oldvips[$vip['subnet']]['subnet'] =
					    $vip['subnet'];
				} else if (($vip['mode'] == "ipalias" ||
				    $vip['mode'] == 'proxyarp') &&
				    !(substr($vip['interface'], 0, 4) == '_vip')
				    || strstr($vip['interface'], "lo0")) {
					$vipbackup[] = $vip;
				}
			}
		}

		/* For vip section, first keep items sent from the master */
		$config = array_merge_recursive_unique($config, $sections);

		/* Remove local CP zones removed remote */
		foreach ($cp_to_del as $zone) {
			$cpzone = $zone;
			$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];
			unset($config['captiveportal'][$cpzone]['enable']);
			captiveportal_configure_zone(
			    $config['captiveportal'][$cpzone]);
			unset($config['captiveportal'][$cpzone]);
			if (isset($config['voucher'][$cpzone])) {
				unset($config['voucher'][$cpzone]);
			}
		}

		/* Remove locally items removed remote */
		foreach ($voucher as $zone => $item) {
			/* No rolls on master, delete local ones */
			if (!is_array($item['roll'])) {
				unset($config['voucher'][$zone]['roll']);
			}
		}

		$l_rolls = array();
		if (is_array($config['voucher'])) {
			foreach ($config['voucher'] as $zone => $item) {
				if (!is_array($item['roll'])) {
					continue;
				}
				foreach ($item['roll'] as $idx => $roll) {
					/* Make it easy to find roll by # */
					$l_rolls[$zone][$roll['number']] = $idx;
				}
			}
		}

		/*
		 * Process vouchers sent by primary node and:
		 * - Add new items
		 * - Update existing items based on 'lastsync' field
		 */
		foreach ($voucher as $zone => $item) {
			if (!is_array($item['roll'])) {
				continue;
			}
			foreach ($item['roll'] as $idx => $roll) {
				if (!isset($l_rolls[$zone][$roll['number']])) {
					$config['voucher'][$zone]['roll'][] =
					    $roll;
					continue;
				}
				$l_roll_idx = $l_rolls[$zone][$roll['number']];
				init_config_arr(array('voucher', $zone));
				$l_vouchers = &$config['voucher'][$zone];
				$l_roll = $l_vouchers['roll'][$l_roll_idx];
				if (!isset($l_roll['lastsync'])) {
					$l_roll['lastsync'] = 0;
				}

				if (isset($roll['lastsync']) &&
				    $roll['lastsync'] != $l_roll['lastsync']) {
					$l_vouchers['roll'][$l_roll_idx] =
					    $roll;
					unset($l_rolls[$zone][$roll['number']]);
				}
			}
		}

		/*
		 * At this point $l_rolls contains only items that are not
		 * present on primary node. They must be removed
		 */
		foreach ($l_rolls as $zone => $item) {
			foreach ($item as $number => $idx) {
				unset($config['voucher'][$zone][$idx]);
			}
		}

		/*
		 * Then add ipalias and proxyarp types already defined
		 * on the backup
		 */
		if (is_array($vipbackup) && !empty($vipbackup)) {
			if (!is_array($config['virtualip'])) {
				$config['virtualip'] = array();
			}
			if (!is_array($config['virtualip']['vip'])) {
				$config['virtualip']['vip'] = array();
			}
			foreach ($vipbackup as $vip) {
				array_unshift($config['virtualip']['vip'], $vip);
			}
		}

		/* Log what happened */
		$mergedkeys = implode(", ", array_merge(array_keys($sections),
		    $syncd_full_sections));
		write_config(sprintf(gettext(
		    "Merged in config (%s sections) from XMLRPC client."),
		    $mergedkeys));

		/*
		 * The real work on handling the vips specially
		 * This is a copy of intefaces_vips_configure with addition of
		 * not reloading existing/not changed carps
		 */
		if (isset($sections['virtualip']) &&
		    is_array($config['virtualip']) &&
		    is_array($config['virtualip']['vip'])) {
			$carp_setuped = false;
			$anyproxyarp = false;

			foreach ($config['virtualip']['vip'] as $vip) {
				$key = "{$vip['interface']}_vip{$vip['vhid']}";

				if ($vip['mode'] == "carp" &&
				    isset($oldvips[$key])) {
					if ($oldvips[$key]['content'] ==
					    $vip['password'] .
					    $vip['advskew'] .
					    $vip['subnet'] .
					    $vip['subnet_bits'] .
					    $vip['advbase'] &&
					    does_vip_exist($vip)) {
						unset($oldvips[$key]);
						/*
						 * Skip reconfiguring this vips
						 * since nothing has changed.
						 */
						continue;
					}

				} elseif ($vip['mode'] == "ipalias" &&
				    (substr($vip['interface'], 0, 4) == '_vip'
				    || strstr($vip['interface'], "lo0")) &&
				    isset($oldvips[$vip['subnet']])) {
					$key = $vip['subnet'];
					if ($oldvips[$key]['content'] ==
					    $vip['interface'] .
					    $vip['subnet'] .
					    $vip['subnet_bits'] &&
					    does_vip_exist($vip)) {
						unset($oldvips[$key]);
						/*
						 * Skip reconfiguring this vips
						 * since nothing has changed.
						 */
						continue;
					}
					unset($oldvips[$key]);
				}

				switch ($vip['mode']) {
				case "proxyarp":
					$anyproxyarp = true;
					break;
				case "ipalias":
					interface_ipalias_configure($vip);
					break;
				case "carp":
					$carp_setuped = true;
					interface_carp_configure($vip);
					break;
				}
			}

			/* Cleanup remaining old carps */
			foreach ($oldvips as $oldvipar) {
				$oldvipif = get_real_interface(
				    $oldvipar['interface']);

				if (empty($oldvipif)) {
					continue;
				}

				if (is_ipaddrv6($oldvipar['subnet'])) {
					 mwexec("/sbin/ifconfig " .
					     escapeshellarg($oldvipif) .
					     " inet6 " .
					     escapeshellarg($oldvipar['subnet']) .
					     " delete");
				} else {
					pfSense_interface_deladdress($oldvipif,
					    $oldvipar['subnet']);
				}
			}
			if ($carp_setuped == true) {
				interfaces_sync_setup();
			}
			if ($anyproxyarp == true) {
				interface_proxyarp_configure();
			}
		}

		if ($old_ipsec_enabled !== ipsec_enabled()) {
			vpn_ipsec_configure();
		}

		unset($old_config);

		local_sync_accounts($u2add, $u2del, $g2add, $g2del);
		$this->filter_configure(false);

		return true;
	}

	/**
	 * Merge items into installedpackages config section
	 *
	 * @param array $section
	 *
	 * @return bool
	 */
	public function merge_installedpackages_section($section) {
		$this->auth();

		global $config;

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		$config['installedpackages'] = array_merge(
		    $config['installedpackages'], $section);
		$mergedkeys = implode(", ", array_keys($section));
		write_config(sprintf(gettext(
		    "Merged in config (%s sections) from XMLRPC client."),
		    $mergedkeys));

		return true;
	}

	/**
	 * Merge items into config
	 *
	 * @param array $section
	 *
	 * @return bool
	 */
	public function merge_config_section($section) {
		$this->auth();

		global $config;

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		$config_new = $this->array_overlay($config, $section);
		$config = $config_new;
		$mergedkeys = implode(", ", array_keys($section));
		write_config(sprintf(gettext(
		    "Merged in config (%s sections) from XMLRPC client."),
		    $mergedkeys));

		return true;
	}

	/**
	 * Wrapper for filter_configure()
	 *
	 * @return bool
	 */
	private function filter_configure($reset_accounts = true) {
		global $g, $config;

		filter_configure();
		system_routing_configure();
		setup_gateways_monitor();
		require_once("openvpn.inc");
		openvpn_resync_all();

		/*
		 * The DNS Resolver and the DNS Forwarder may both be active so
		 * long as * they are running on different ports.
		 * See ticket #5882
		 */
		if (isset($config['dnsmasq']['enable'])) {
			/* Configure dnsmasq but tell it NOT to restart DHCP */
			services_dnsmasq_configure(false);
		} else {
			/* kill any running dnsmasq instance */
			if (isvalidpid("{$g['varrun_path']}/dnsmasq.pid")) {
				sigkillbypid("{$g['varrun_path']}/dnsmasq.pid",
				    "TERM");
			}
		}
		if (isset($config['unbound']['enable'])) {
			/* Configure unbound but tell it NOT to restart DHCP */
			services_unbound_configure(false);
		} else {
			/* kill any running Unbound instance */
			if (isvalidpid("{$g['varrun_path']}/unbound.pid")) {
				sigkillbypid("{$g['varrun_path']}/unbound.pid",
				    "TERM");
			}
		}

		/*
		 * Call this separately since the above are manually set to
		 * skip the DHCP restart they normally perform.
		 * This avoids restarting dhcpd twice as described on
		 * ticket #3797
		 */
		services_dhcpd_configure();

		if ($reset_accounts) {
			local_reset_accounts();
		}

		captiveportal_configure();

		return true;
	}

	/**
	 * Wrapper for configuring CARP interfaces
	 *
	 * @return bool
	 */
	public function interfaces_carp_configure() {
		$this->auth();

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		interfaces_vips_configure();

		return true;
	}

	/**
	 * Wrapper for rc.reboot
	 *
	 * @return bool
	 */
	public function reboot() {
		$this->auth();

		mwexec_bg("/etc/rc.reboot");

		return true;
	}
}

// run script untill its done and can 'unlock' the xmlrpc.lock, this prevents hanging php-fpm / webgui
ignore_user_abort(true);
set_time_limit(0);

$xmlrpclockkey = lock('xmlrpc', LOCK_EX);

XML_RPC2_Backend::setBackend('php');
$HTTP_RAW_POST_DATA = file_get_contents('php://input');

$options = array(
	'prefix' => 'pfsense.',
	'encoding' => 'utf-8',
	'autoDocument' => false,
);

$server = XML_RPC2_Server::create(new pfsense_xmlrpc_server(), $options);
$server->handleCall();

unlock($xmlrpclockkey);

?>
