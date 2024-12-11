<?php
/*
 * xmlrpc.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
		global $userindex;
		$userindex = index_users();

		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];

		$login_ok = false;
		if (!empty($username) && !empty($password)) {
			$attributes = array();
			$authcfg = auth_get_authserver(config_get_path('system/webgui/authmode'));

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
		$user_entry = $user_entry['item'];
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
		$this->remote_addr = $_SERVER['REMOTE_ADDR'];

		/* grab sync to ip if enabled */
		if ((config_get_path('hasync/synchronizetoip') !== null) &&
		    config_get_path('hasync/synchronizetoip') == $this->remote_addr) {
			$this->loop_detected = true;
		}
	}

	/**
	 * Get host version information
	 *
	 * @return array
	 */
	public function host_firmware_version($dummy, $timeout) {
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
		return array_intersect_key(config_get_path(''), array_flip($section));
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

		global $cpzone, $cpzoneid, $old_config;

		$old_config = config_get_path('');

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
			/* Do not test for empty here or removing final entry
			 * from a section will not work */
			if (!array_key_exists($section, $sections)) {
				continue;
			}

			config_set_path($section, array_get_path($sections, $section));
			array_del_path($sections, $section);
			$syncd_full_sections[] = $section;
		}

		/* If captive portal sync is enabled on primary node, remove local CP on the secondary */
		if (is_array($sections['captiveportal'])) {
			foreach (config_get_path('captiveportal', []) as $zone => $item) {
				if (!isset($sections['captiveportal'][$zone])) {
					$cpzone = $zone;
					config_del_path("captiveportal/{$cpzone}/enable");
					captiveportal_configure_zone(config_get_path("captiveportal/{$cpzone}", []));
					config_del_path("captiveportal/{$cpzone}");
					config_del_path("voucher/{$cpzone}");
					unlink_if_exists("/var/db/captiveportal{$cpzone}.db");
					unlink_if_exists("/var/db/captiveportal_usedmacs_{$cpzone}.db");
					unlink_if_exists("/var/db/voucher_{$cpzone}_*.db");
				}
			}
		}

		$group_config = config_get_path('system/group', []);
		/* Only touch users if users are set to synchronize from the primary node
		 * See https://redmine.pfsense.org/issues/8450
		 */
		if ($sections['system']['user'] && $sections['system']['group']) {
			$g2add = array();
			$g2del = array();
			$g2del_idx = array();
			$g2keep = array();
			if (is_array($sections['system']['group'])) {
				$local_groups = $group_config;

				foreach ($sections['system']['group'] as $group) {
					$idx = array_search($group['name'],
					    array_column($local_groups, 'name'));

					if ($idx === false) {
						// section config group not found in local config
						$g2add[] = $group;
					} else if ($group['gid'] < 2000) {
						// section config group found in local config and is a special group
						$g2keep[] = $idx;
					} else if ($group != $local_groups[$idx]) {
						// section config group found in local config with different settings
						$g2add[] = $group;
						$g2del[] = $group;
						$g2del_idx[] = $idx;
					} else {
						// section config group found in local config and its settings are synced
						$g2keep[] = $idx;
					}
				}
			}
			if (is_array($group_config)) {
				foreach ($group_config as $idx => $group) {
					if (array_search($idx, $g2keep) === false &&
					    array_search($idx, $g2del_idx) === false) {
						// local config group not in section config group
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
			$user_config = config_get_path('system/user', []);
			if (is_array($sections['system']['user'])) {
				$local_users = $user_config;

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
			if (is_array($user_config)) {
				foreach ($user_config as $idx => $user) {
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
				if (config_get_path("voucher/{$zone}/vouchersyncdbip") !== null) {
					$sections['voucher'][$zone]['vouchersyncdbip'] =
					    config_get_path("voucher/{$zone}/vouchersyncdbip");
				} else {
					unset($sections['voucher'][$zone]['vouchersyncdbip']);
				}
				if (config_get_path("voucher/{$zone}/vouchersyncusername") !== null) {
					$sections['voucher'][$zone]['vouchersyncusername'] =
					    config_get_path("voucher/{$zone}/vouchersyncusername");
				} else {
					unset($sections['voucher'][$zone]['vouchersyncusername']);
				}
				if (config_get_path("voucher/{$zone}/vouchersyncpass") !== null) {
					$sections['voucher'][$zone]['vouchersyncpass'] =
					    config_get_path("voucher/{$zone}/vouchersyncpass");
				} else {
					unset($sections['voucher'][$zone]['vouchersyncpass']);
				}
				// End note.
			}
		}

		if (is_array($sections['captiveportal'])) {
			// Captiveportal : Backward HA settings should remain local.
			foreach ($sections['captiveportal'] as $zone => $cp) {
				if (config_path_enabled("captiveportal/{$zone}", "enablebackwardsync")) {
					$sections['captiveportal'][$zone]['enablebackwardsync'] = config_get_path("captiveportal/{$zone}/enablebackwardsync");
				} else {
					unset($sections['captiveportal'][$zone]['enablebackwardsync']);
				}
				if (config_get_path("captiveportal/{$zone}/backwardsyncip") !== null) {
					$sections['captiveportal'][$zone]['backwardsyncip'] = config_get_path("captiveportal/{$zone}/backwardsyncip");
				} else {
					unset($sections['captiveportal'][$zone]['backwardsyncip']);
				}
				if (config_get_path("captiveportal/{$zone}/backwardsyncuser") !== null) {
					$sections['captiveportal'][$zone]['backwardsyncuser'] = config_get_path("captiveportal/{$zone}/backwardsyncuser");
				} else {
					unset($sections['captiveportal'][$zone]['backwardsyncuser']);
				}
				if (config_get_path("captiveportal/{$zone}/backwardsyncpassword") !== null) {
					$sections['captiveportal'][$zone]['backwardsyncpassword'] = config_get_path("captiveportal/{$zone}/backwardsyncpassword");
				} else {
					unset($sections['captiveportal'][$zone]['vouchersyncpass']);
				}
			}
			config_set_path('captiveportal', $sections['captiveportal']);
			unset($sections['captiveportal']);
		}

		$vipbackup = array();
		$oldvips = array();
		if (array_key_exists('virtualip', $sections)) {
			foreach (config_get_path('virtualip/vip', []) as $vip) {
				if (empty($vip)) {
					continue;
				}
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

		/* Extract and save any package sections before merging other sections */
		$pkg_sections = ['installedpackages' => array_get_path($sections, 'installedpackages', [])];
		array_del_path($sections, 'installedpackages');

		/* Check for changed or removed static routes; do this before updating the active/running config. */
		$static_routes_to_remove = [];
		if (empty(array_get_path($old_config, 'staticroutes/route',))) {
			$static_routes_to_remove = array_keys(array_get_path($sections, 'staticroutes/route', []));
		} else {
			foreach ($old_config['staticroutes']['route'] as $idx => $old_route) {
				if (!isset($sections['staticroutes']['route'][$idx]) ||
				    ($old_route['network'] != $sections['staticroutes']['route'][$idx]['network']) ||
				    ($old_route['gateway'] != $sections['staticroutes']['route'][$idx]['gateway'])) {
					$static_routes_to_remove[] = $idx;
				}
			}
		}
		if (!empty($static_routes_to_remove)) {
			foreach ($static_routes_to_remove as $route_index) {
				delete_static_route($route_index, true);
			}
			// Apply the removed route changes, if any.
			$routes_apply_file = g_get('tmp_path') . '/.system_routes.apply';
			if (file_exists($routes_apply_file)) {
				$toapplylist = unserialize_data(file_get_contents($routes_apply_file), []);
				foreach ($toapplylist as $toapply) {
					mwexec($toapply, true);
				}
				@unlink($routes_apply_file);
			}
		}

		/* For vip section, first keep items sent from the master */
		config_set_path('', array_merge_recursive_unique(config_get_path(''), $sections));

		/* Special handling for Kea HA, skip receiving certain settings from master */
		foreach (['kea', 'kea6'] as $kea) {
			if (array_path_enabled($old_config, $kea.'/ha', 'tls')) {
				config_set_path($kea.'/ha/tls', true);
				if ($value = array_get_path($old_config, $kea.'/ha/scertref')) {
					config_set_path($kea.'/ha/scertref', $value);
				} else {
					config_del_path($kea.'/ha/scertref');
				}
				if (array_path_enabled($old_config, $kea.'/ha', 'mutualtls')) {
					config_set_path($kea.'/ha/mutualtls', true);
					if ($value = array_get_path($old_config, $kea.'/ha/ccertref')) {
						config_set_path($kea.'/ha/ccertref', $value);
					} else {
						config_del_path($kea.'/ha/ccertref');
					}
				}
			} else {
				config_del_path($kea.'/ha/tls');
				config_del_path($kea.'/ha/scertref');
				config_del_path($kea.'/ha/mutualtls');
				config_del_path($kea.'/ha/ccertref');
			}

			if ($value = array_get_path($old_config, $kea.'/ha/localname')) {
				config_set_path($kea.'/ha/localname', $value);
			} else {
				config_del_path($kea.'/ha/localname');
			}
		}

		/* Remove locally items removed remote */
		foreach ($voucher as $zone => $item) {
			/* No rolls on master, delete local ones */
			if (!is_array($item['roll'])) {
				config_del_path("voucher/{$zone}/roll");
			}
		}

		$l_rolls = array();
		if (is_array(config_get_path('voucher'))) {
			foreach (config_get_path('voucher', []) as $zone => $item) {
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
			$l_vouchers = config_get_path("voucher/{$zone}", []);
			foreach ($item['roll'] as $roll) {
				if (!isset($l_rolls[$zone][$roll['number']])) {
					$l_vouchers['roll'][] = $roll;
					continue;
				}
				$l_roll_idx = $l_rolls[$zone][$roll['number']];
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
			config_set_path("voucher/{$zone}", $l_vouchers);
		}

		/*
		 * At this point $l_rolls contains only items that are not
		 * present on primary node. They must be removed
		 */
		foreach ($l_rolls as $zone => $item) {
			foreach ($item as $idx) {
				config_del_path("voucher/{$zone}/{$idx}");
			}
		}

		/*
		 * Then add ipalias and proxyarp types already defined
		 * on the backup
		 */
		if (is_array($vipbackup) && !empty($vipbackup)) {
			$vips = config_get_path('virtualip/vip', []);
			foreach ($vipbackup as $vip) {
				array_unshift($vips, $vip);
			}
			config_set_path('virtualip/vip', $vips);
		}

		/* xmlrpc_recv plugin expects path => value pairs of changed nodes, not an associative tree */
		$pkg_merged_paths = pkg_call_plugins("plugin_xmlrpc_recv", $pkg_sections);
		foreach ($pkg_merged_paths as $pkg => $sections) {
			if (!is_array($sections)) {
				log_error('Package {$pkg} xmlrpc_recv plugin returned invalid value.');
				continue;
			}
			foreach ($sections as $path => $section) {
				if (is_null(config_set_path($path, $section))) {
					log_error('Could not write section {$path} supplied by package {$pkg} xmlrpc_recv plugin');
				}
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
		    is_array(config_get_path('virtualip/vip'))) {
			$carp_setuped = false;
			$anyproxyarp = false;

			foreach (config_get_path('virtualip/vip', []) as $vip) {
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
					if (does_vip_exist($vip) && isset($oldvips[$key]['vhid']) &&
					    ($oldvips[$key]['vhid'] ^ $vip['vhid'])) {
						/* properly remove the old VHID
						 * see https://redmine.pfsense.org/issues/12202 */
						$realif = get_real_interface($vip['interface']);
						mwexec("/sbin/ifconfig {$realif} " .
							escapeshellarg($vip['subnet']) . " -alias");
						$ipalias_reload = true;
					} else {
						$ipalias_reload = false;
					}
					interface_carp_configure($vip, false, $ipalias_reload);
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

				/* do not remove VIP if the IP address remains the same */
				foreach (config_get_path('virtualip/vip', []) as $vip) {
					if ($vip['subnet'] == $oldvipar['subnet']) {
						continue 2;
					}
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

		pkg_call_plugins('plugin_xmlrpc_recv_done', []);
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

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		config_set_path('installedpackages', array_merge(
		    config_get_path('installedpackages'), $section));
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

		if ($this->loop_detected) {
			log_error("Disallowing CARP sync loop");
			return true;
		}

		config_set_path('', $this->array_overlay(config_get_path(''), $section));
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
		global $g, $old_config;

		filter_configure();
		system_routing_configure();
		setup_gateways_monitor();

		/* do not restart unchanged services on XMLRPC sync,
		 * see https://redmine.pfsense.org/issues/11082 
		 */
		if (is_array(config_get_path('openvpn')) || is_array($old_config['openvpn'])) {
			foreach (array("server", "client") as $type) {
				$remove_id = array();
				if (is_array(array_get_path($old_config, "openvpn/openvpn-{$type}"))) {
					foreach ($old_config['openvpn']["openvpn-{$type}"] as $old_settings) {
						$remove_id[] = $old_settings['vpnid'];
					}
				}
				if (!is_array(config_get_path("openvpn/openvpn-{$type}"))) {
					continue;
				}
				foreach (config_get_path("openvpn/openvpn-{$type}", []) as $settings) {
					$new_instance = true;
					if (in_array($settings['vpnid'], $remove_id)) {
						$remove_id = array_diff($remove_id, array($settings['vpnid']));
					}
					if (is_array(array_get_path($old_config, "openvpn/openvpn-{$type}"))) {
						foreach ($old_config['openvpn']["openvpn-{$type}"] as $old_settings) {
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
		if (((is_array(config_get_path('ipsec')) || is_array($old_config['ipsec'])) &&
		    (config_get_path('ipsec') != $old_config['ipsec'])) ||
		    $force) {
			ipsec_configure();
		}

		/*
		 * The DNS Resolver and the DNS Forwarder may both be active so
		 * long as * they are running on different ports.
		 * See ticket #5882
		 */
		if (((is_array(config_get_path('dnsmasq')) || is_array($old_config['dnsmasq'])) &&
		    (config_get_path('dnsmasq') != $old_config['dnsmasq'])) ||
		    $force) {
			if (config_path_enabled('dnsmasq')) {
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
		if (((is_array(config_get_path('unbound')) || is_array($old_config['unbound'])) &&
		    (config_get_path('unbound') != $old_config['unbound'])) ||
		    $force) {
			if (config_path_enabled('unbound')) {
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
		$called = [];
		foreach ([
			'dhcpd'			=> 'services_dhcpd_configure',
			'dhcpdv6'		=> 'services_dhcpd_configure',
			'kea'			=> 'services_dhcpd_configure',
			'kea6'			=> 'services_dhcpd_configure',
			'dhcrelay'		=> 'services_dhcrelay_configure',
			'dhcrelay6'		=> 'services_dhcrelay6_configure',
			'captiveportal'	=> 'captiveportal_configure',
			'voucher'		=> 'voucher_configure'
		] as $path => $fn) {
			if (!array_key_exists($fn, $called)) {
				if (((is_array(config_get_path($path)) || is_array($old_config[$path])) &&
			        (config_get_path($path) !== array_get_path($old_config, $path))) || $force) {
					if (is_callable($fn)) {
						$fn();
					}
					$called[$fn] = true;
				}
			}
		}

		if ($reset_accounts) {
			local_reset_accounts();
		}

		return true;
	}

	/**
	 * Wrapper for captiveportal connected users and
	 * active/expired vouchers synchronization
	 *
	 * @param array $arguments
	 *
	 * @return array|bool|null
	 */
	public function captive_portal_sync($arguments, $timeout) {
		ini_set('default_socket_timeout', $timeout);
		$this->auth();
		// Note : no protection against CARP loop is done here, and this is in purpose.
		// This function is used for bi-directionnal sync, which is precisely what CARP loop protection is supposed to prevent.
		// CARP loop has to be managed within functions using captive_portal_sync()
		global $g, $cpzone;

		if (empty($arguments['op']) || empty($arguments['zone']) || empty(config_get_path("captiveportal/{$arguments['zone']}"))) {
			return false;
		}
		$cpzone = $arguments['zone'];

		if ($arguments['op'] === 'get_databases') {
			$active_vouchers = [];
			$expired_vouchers = [];
			$usedmacs = [];

			foreach(config_get_path("voucher/{$cpzone}/roll", []) as $roll) {
				$expired_vouchers[$roll['number']] = base64_encode(voucher_read_used_db($roll['number']));
				$active_vouchers[$roll['number']] = voucher_read_active_db($roll['number']);
			}
			if (!empty(config_get_path("captiveportal/{$cpzone}/freelogins_count")) &&
			    !empty(config_get_path("captiveportal/{$cpzone}/freelogins_resettimeout"))) {
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
			$user = unserialize_data(base64_decode($arguments['user']), []);
			$user['attributes']['allow_time'] = $user['allow_time'];

			// pipeno might be different between primary and secondary
			$pipeno = captiveportal_get_next_dn_ruleno('auth');
			return portal_allow($user['clientip'], $user['clientmac'], $user['username'], $user['password'], null,
			    $user['attributes'], $pipeno, $user['authmethod'], $user['context'], $user['sessionid']);
		} elseif ($arguments['op'] === 'disconnect_user') {
			$session = unserialize_data(base64_decode($arguments['session']), []);
			/* read database again, as pipeno might be different between primary & secondary */
			$sessionid = SQLite3::escapeString($session['sessionid']);
			$local_dbentry = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

			if (!empty($local_dbentry) && count($local_dbentry) == 1) {
				return captiveportal_disconnect($local_dbentry[0], $session['term_cause'], $session['stop_time'], true);
			} else {
				return false;
			}
		} elseif ($arguments['op'] === 'remove_entries') {
			$entries = unserialize_data(base64_decode($arguments['entries']), []);

			return captiveportal_remove_entries($entries, true);
		} elseif ($arguments['op'] === 'disconnect_all') {
			$arguments = unserialize_data(base64_decode($arguments['arguments']), []);

			return captiveportal_disconnect_all($arguments['term_cause'], $arguments['logout_reason'], true);
		} elseif ($arguments['op'] === 'write_vouchers') {
			$arguments = unserialize_data(base64_decode($arguments['arguments']), []);

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
			$arguments = unserialize_data(base64_decode($arguments['arguments']), []);

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
