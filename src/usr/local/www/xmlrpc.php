<?php
/*
 * xmlrpc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
require_once("openvpn.inc");
require_once("captiveportal.inc");
require_once("shaper.inc");
require_once("XML/RPC2/Server.php");

class pfsense_xmlrpc_server {

	private $loop_detected = false;
	private $remote_addr;

	private function auth() {
		global $config, $userindex;
		$userindex = index_users();

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
			log_auth(sprintf(gettext("webConfigurator authentication error for user '%1\$s' from: %2\$s"),
			    $username,
			    $this->remote_addr));

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
	public function host_firmware_version($dummy = 1, $timeout) {
		ini_set('default_socket_timeout', $timeout);
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
	public function restore_config_section($sections, $timeout) {
		ini_set('default_socket_timeout', $timeout);
		$this->auth();

		global $config, $cpzone, $cpzoneid, $old_config;

		$old_config = $config;

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
			'dhcrelay',
			'dhcrelay6',
			'dnshaper',
			'dnsmasq',
			'filter',
			'ipsec',
			'nat',
			'openvpn',
			'schedules',
			'shaper',
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

		/* If captive portal sync is enabled on primary node, remove local CP on the secondary */
		if (is_array($config['captiveportal']) && is_array($sections['captiveportal'])) {
			foreach ($config['captiveportal'] as $zone => $item) {
				if (!isset($sections['captiveportal'][$zone])) {
					$cpzone = $zone;
					unset($config['captiveportal'][$cpzone]['enable']);
					captiveportal_configure_zone($config['captiveportal'][$cpzone]);
					unset($config['captiveportal'][$cpzone]);
					if (isset($config['voucher'][$cpzone])) {
						unset($config['voucher'][$cpzone]);
					}
					unlink_if_exists("/var/db/captiveportal{$cpzone}.db");
					unlink_if_exists("/var/db/captiveportal_usedmacs_{$cpzone}.db");
					unlink_if_exists("/var/db/voucher_{$cpzone}_*.db");
				}
			}
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
				// Note : This code can be safely deleted once #97 fix has been applied and deployed to pfSense stable release.
				// Please do not delete this code before
				if (isset($config['voucher'][$zone]['vouchersyncdbip'])) {
					$sections['voucher'][$zone]['vouchersyncdbip'] =
					    $config['voucher'][$zone]['vouchersyncdbip'];
				} else {
					unset($sections['voucher'][$zone]['vouchersyncdbip']);
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
				// End note.
			}
		}

		if (is_array($sections['captiveportal'])) {
			// Captiveportal : Backward HA settings should remain local.
			foreach ($sections['captiveportal'] as $zone => $cp) {
				if (isset($config['captiveportal'][$zone]['enablebackwardsync'])) {
					$sections['captiveportal'][$zone]['enablebackwardsync'] = $config['captiveportal'][$zone]['enablebackwardsync'];
				} else {
					unset($sections['captiveportal'][$zone]['enablebackwardsync']);
				}
				if (isset($config['captiveportal'][$zone]['backwardsyncip'])) {
					$sections['captiveportal'][$zone]['backwardsyncip'] = $config['captiveportal'][$zone]['backwardsyncip'];
				} else {
					unset($sections['captiveportal'][$zone]['backwardsyncip']);
				}
				if (isset($config['captiveportal'][$zone]['backwardsyncuser'])) {
					$sections['captiveportal'][$zone]['backwardsyncuser'] = $config['captiveportal'][$zone]['backwardsyncuser'];
				} else {
					unset($sections['captiveportal'][$zone]['backwardsyncuser']);
				}
				if (isset($config['captiveportal'][$zone]['backwardsyncpassword'])) {
					$sections['captiveportal'][$zone]['backwardsyncpassword'] = $config['captiveportal'][$zone]['backwardsyncpassword'];
				} else {
					unset($sections['captiveportal'][$zone]['vouchersyncpass']);
				}
			}
			$config['captiveportal'] = $sections['captiveportal'];
			unset($sections['captiveportal']);
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
		 * This is a copy of interfaces_vips_configure with addition of
		 * not reloading existing/not changed carps
		 */
		$force_filterconfigure = false;
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
				$force_filterconfigure = true;
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

		local_sync_accounts($u2add, $u2del, $g2add, $g2del);
		$this->filter_configure(false, $force_filterconfigure);
		unset($old_config);

		return true;
	}

	/**
	 * Merge items into installedpackages config section
	 *
	 * @param array $section
	 *
	 * @return bool
	 */
	public function merge_installedpackages_section($section, $timeout) {
		ini_set('default_socket_timeout', $timeout);
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
	public function merge_config_section($section, $timeout) {
		ini_set('default_socket_timeout', $timeout);
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
	private function filter_configure($reset_accounts = true, $force = false) {
		global $g, $config, $old_config;

		filter_configure();
		system_routing_configure();
		setup_gateways_monitor();

		/* do not restart unchanged services on XMLRPC sync,
		 * see https://redmine.pfsense.org/issues/11082 
		 */
		if (is_array($config['openvpn']) || is_array($old_config['openvpn'])) {
			foreach (array("server", "client") as $type) {
				$remove_id = array();
				if (is_array($old_config['openvpn']["openvpn-{$type}"])) {
					foreach ($old_config['openvpn']["openvpn-{$type}"] as & $old_settings) {
						$remove_id[] = $old_settings['vpnid'];
					}
				}
				if (!is_array($config['openvpn']["openvpn-{$type}"])) {
					continue;
				}
				foreach ($config['openvpn']["openvpn-{$type}"] as & $settings) {
					$new_instance = true;
					if (in_array($settings['vpnid'], $remove_id)) {
						$remove_id = array_diff($remove_id, array($settings['vpnid']));
					}
					if (is_array($old_config['openvpn']["openvpn-{$type}"])) {
						foreach ($old_config['openvpn']["openvpn-{$type}"] as & $old_settings) {
							if ($settings['vpnid'] == $old_settings['vpnid']) {
								$new_instance = false;
								if (($settings != $old_settings) || $force) {
									/* restart changed openvpn instance */
									openvpn_resync($type, $settings);
									break;
								}
							}
						}
					}
					if ($new_instance) {
						/* start new openvpn instance */
						openvpn_resync($type, $settings);
					}
				}
				if (!empty($remove_id)) {
					foreach ($remove_id as $id) {
						/* stop/delete removed openvpn instances */
						openvpn_delete($type, array('vpnid' => $id));
					}
				}
			}
			/* no service restart required */
			openvpn_resync_csc_all();
		}

		/* run ipsec_configure() on any IPsec change, see https://redmine.pfsense.org/issues/12075 */
		if (((is_array($config['ipsec']) || is_array($old_config['ipsec'])) &&
		    ($config['ipsec'] != $old_config['ipsec'])) ||
		    $force) {
			ipsec_configure();
		}

		/*
		 * The DNS Resolver and the DNS Forwarder may both be active so
		 * long as * they are running on different ports.
		 * See ticket #5882
		 */
		if (((is_array($config['dnsmasq']) || is_array($old_config['dnsmasq'])) &&
		    ($config['dnsmasq'] != $old_config['dnsmasq'])) ||
		    $force) {
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
		}
		if (((is_array($config['unbound']) || is_array($old_config['unbound'])) &&
		    ($config['unbound'] != $old_config['unbound'])) ||
		    $force) {
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
		}

		/*
		 * Call this separately since the above are manually set to
		 * skip the DHCP restart they normally perform.
		 * This avoids restarting dhcpd twice as described on
		 * ticket #3797
		 */
		if (((is_array($config['dhcpd']) || is_array($old_config['dhcpd'])) &&
		    ($config['dhcpd'] != $old_config['dhcpd'])) ||
		    $force) {
			services_dhcpd_configure();
		}

		if (((is_array($config['dhcrelay']) || is_array($old_config['dhcrelay'])) &&
		    ($config['dhcrelay'] != $old_config['dhcrelay'])) ||
		    $force) {
			services_dhcrelay_configure();
		}

		if (((is_array($config['dhcrelay6']) || is_array($old_config['dhcrelay6'])) &&
		    ($config['dhcrelay6'] != $old_config['dhcrelay6'])) ||
		    $force) {
			services_dhcrelay6_configure();
		}

		if ($reset_accounts) {
			local_reset_accounts();
		}

		if ((is_array($config['captiveportal']) || is_array($old_config['captiveportal']) &&
		    ($config['captiveportal'] != $old_config['captiveportal'])) ||
		    $force) {
			captiveportal_configure();
		}
		if ((is_array($config['voucher']) || is_array($old_config['voucher']) &&
		    ($config['voucher'] != $old_config['voucher'])) ||
		    $force) {
			voucher_configure();
		}

		return true;
	}

	/**
	 * Wrapper for captiveportal connected users and
	 * active/expired vouchers synchronization
	 *
	 * @param array $arguments
	 *
	 * @return array
	 */
	public function captive_portal_sync($arguments, $timeout) {
		ini_set('default_socket_timeout', $timeout);
		$this->auth();
		// Note : no protection against CARP loop is done here, and this is in purpose.
		// This function is used for bi-directionnal sync, which is precisely what CARP loop protection is supposed to prevent.
		// CARP loop has to be managed within functions using captive_portal_sync()
		global $g, $config, $cpzone;

		if (empty($arguments['op']) || empty($arguments['zone']) || empty($config['captiveportal'][$arguments['zone']])) {
			return false;
		}
		$cpzone = $arguments['zone'];

		if ($arguments['op'] === 'get_databases') {
			$active_vouchers = array();
			$expired_vouchers = array();
			$usedmacs = '';

			if (is_array($config['voucher'][$cpzone]['roll'])) {
				foreach($config['voucher'][$cpzone]['roll'] as $id => $roll) {
					$expired_vouchers[$roll['number']] = base64_encode(voucher_read_used_db($roll['number']));
					$active_vouchers[$roll['number']] = voucher_read_active_db($roll['number']);
				}
			}
			if (!empty($config['captiveportal'][$cpzone]['freelogins_count']) &&
			    !empty($config['captiveportal'][$cpzone]['freelogins_resettimeout'])) {
				$usedmacs = captiveportal_read_usedmacs_db();
			}
			// base64 is here for safety reasons, as we don't fully control
			// the content of these arrays.
			$returndata = array('connected_users' => base64_encode(serialize(captiveportal_read_db())),
			'active_vouchers' => base64_encode(serialize($active_vouchers)),
			'expired_vouchers' => base64_encode(serialize($expired_vouchers)),
			'usedmacs' => base64_encode(serialize($usedmacs)));

			return $returndata;
		} elseif ($arguments['op'] === 'connect_user') {
			$user = unserialize(base64_decode($arguments['user']));
			$user['attributes']['allow_time'] = $user['allow_time'];

			// pipeno might be different between primary and secondary
			$pipeno = captiveportal_get_next_dn_ruleno('auth');
			return portal_allow($user['clientip'], $user['clientmac'], $user['username'], $user['password'], null,
			    $user['attributes'], $pipeno, $user['authmethod'], $user['context'], $user['sessionid']);
		} elseif ($arguments['op'] === 'disconnect_user') {
			$session = unserialize(base64_decode($arguments['session']));
			/* read database again, as pipeno might be different between primary & secondary */
			$sessionid = SQLite3::escapeString($session['sessionid']);
			$local_dbentry = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

			if (!empty($local_dbentry) && count($local_dbentry) == 1) {
				return captiveportal_disconnect($local_dbentry[0], $session['term_cause'], $session['stop_time'], true);
			} else {
				return false;
			}
		} elseif ($arguments['op'] === 'remove_entries') {
			$entries = unserialize(base64_decode($arguments['entries']));

			return captiveportal_remove_entries($entries, true);
		} elseif ($arguments['op'] === 'disconnect_all') {
			$arguments = unserialize(base64_decode($arguments['arguments']));

			return captiveportal_disconnect_all($arguments['term_cause'], $arguments['logout_reason'], true);
		} elseif ($arguments['op'] === 'write_vouchers') {
			$arguments = unserialize(base64_decode($arguments['arguments']));

			if (is_array($arguments['active_and_used_vouchers_bitmasks'])) {
				foreach ($arguments['active_and_used_vouchers_bitmasks'] as $roll => $used) {
					if (is_array($used)) {
						foreach ($used as $u) {
							voucher_write_used_db($roll, base64_encode($u));
						}
					} else {
						voucher_write_used_db($roll, base64_encode($used));
					}
				}
			}
			foreach ($arguments['active_vouchers'] as $roll => $active_vouchers) {
				voucher_write_active_db($roll, $active_vouchers);
			}
			return true;
		} elseif ($arguments['op'] === 'write_usedmacs') {
			$arguments = unserialize(base64_decode($arguments['arguments']));

			captiveportal_write_usedmacs_db($arguments['usedmacs']); 
			return true;
		}
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

// run script until its done and can 'unlock' the xmlrpc.lock, this prevents hanging php-fpm / webgui
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
