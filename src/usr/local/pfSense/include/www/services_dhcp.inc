<?php
/*
 * services_dhcp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2024-2025 Rubicon Communications, LLC (Netgate)
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

require_once('config.gui.inc');
require_once('util.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');

function kea_defaults(string $key): int|string {
	/* throws an exception if $key isn't matched */
	return (match($key) {
		'fallback'					=> config_get_path('system/domain', 'unknown.home.arpa'),
		'heartbeatdelay'			=> 10000,
		'listenport'				=> 8765,
		'maxackdelay'				=> 60000,
		'maxrejectedleaseupdates'	=> 10,
		'maxresponsedelay' 			=> 60000,
		'maxunackedclients' 		=> 0,
		'name' 						=> config_get_path('system/hostname', g_get('product_name'))
	});
}

function kea_earlydnsreg_enabled(array $dhcpdconf, array $mapent): array|false {
	$ret = false;
	if (!($mapent['hostname'] && $mapent['ipaddr'])) {
		goto done;
	}

	$policies = [];
	$policies[] = config_path_enabled('kea', 'earlydnsreg') ? 'enable' : 'disable';
	$policies[] = array_get_path($dhcpdconf, 'earlydnsregpolicy', 'default');
	$policies[] = array_get_path($mapent, 'earlydnsregpolicy', 'default');

	$domain = kea_defaults('fallback');
	if ($dhcpdconf['domain']) {
		$domain = $dhcpdconf['domain'];
	}
	if ($mapent['domain']) {
		$domain = $mapent['domain'];
	}

	$name = trim($mapent['hostname']);
	$domain = trim($domain);
	$fqdn = "{$name}.{$domain}";
	$ipaddr = trim($mapent['ipaddr']);

	foreach (array_reverse($policies) as $policy) {
		switch ($policy) {
			case 'enable':
				$ret = [
					'name'		=> $name,
					'domain'	=> $domain,
					'fqdn'		=> $fqdn,
					'ipaddr'	=> $ipaddr,
				];
			case 'disable':
				break 2;
			default:
				continue;
		}
	}

done:
	return ($ret);
}

function kea6_earlydnsreg_enabled(array $dhcpdconf, array $mapent): array|false {
	$ret = false;
	if (!($mapent['hostname'] && $mapent['ipaddrv6'])) {
		goto done;
	}

	$policies = [];
	$policies[] = config_path_enabled('kea6', 'earlydnsreg') ? 'enable' : 'disable';
	$policies[] = array_get_path($dhcpdconf, 'earlydnsregpolicy', 'default');
	$policies[] = array_get_path($mapent, 'earlydnsregpolicy', 'default');

	$domain = kea_defaults('fallback');
	if ($dhcpdconf['domain']) {
		$domain = $dhcpdconf['domain'];
	}
	if ($mapent['domain']) {
		$domain = $mapent['domain'];
	}

	$name = trim($mapent['hostname']);
	$domain = trim($domain);
	$fqdn = "{$name}.{$domain}";
	$ipaddr = trim($mapent['ipaddrv6']);

	foreach (array_reverse($policies) as $policy) {
		switch ($policy) {
			case 'enable':
				$ret = [
					'name'		=> $name,
					'domain'	=> $domain,
					'fqdn'		=> $fqdn,
					'ipaddr'	=> $ipaddr,
				];
			case 'disable':
				break 2;
			default:
				continue;
		}
	}

done:
	return ($ret);
}

function dhcp_static_mapping_icons(array $dhcpdconf, array $mapent): string {
	$icons = [];

	if (dhcp_is_backend('kea') &&
	    kea_earlydnsreg_enabled($dhcpdconf, $mapent)) {
		$icons[] = [ 'fa-solid fa-globe', gettext('Early DNS Registration Enabled') ];
	}

	if (isset($mapent['arp_table_static_entry'])) {
		$icons[] = [ 'fa-solid fa-lock', gettext('Static ARP Enabled') ];
	}

	return (implode(' ', (array_map(function($v) {
		return (sprintf('<i class="%s" title="%s" />', $v[0], $v[1]));
	}, $icons))));
}

function dhcp6_static_mapping_icons(array $dhcpdconf, array $mapent): string {
	$icons = [];

	if (dhcp_is_backend('kea') &&
	    kea6_earlydnsreg_enabled($dhcpdconf, $mapent)) {
		$icons[] = [ 'fa-solid fa-globe', gettext('Early DNS Registration Enabled') ];
	}

	return (implode(' ', (array_map(function($v) {
		return (sprintf('<i class="%s" title="%s" />', $v[0], $v[1]));
	}, $icons))));
}

function kea_earlydnsreg_mappings(): array {
	$mappings = [];
	foreach (config_get_path('dhcpd', []) as $if => $conf) {
		foreach (array_get_path($conf, 'staticmap', []) as $static) {
			if ($mapping = kea_earlydnsreg_enabled($conf, $static)) {
				$mappings[] = $mapping;
			}
		}
	}
	return ($mappings);
}

function kea6_earlydnsreg_mappings(): array {
	$mappings = [];
	foreach (config_get_path('dhcpdv6', []) as $if => $conf) {
		foreach (array_get_path($conf, 'staticmap', []) as $static) {
			if ($mapping = kea6_earlydnsreg_enabled($conf, $static)) {
				$mappings[] = $mapping;
			}
		}
	}
	return ($mappings);
}

function kea_dnsreg_enabled_ids(): array {
	$ids = [];
	$enabled['enable'] = true;
	if (config_path_enabled('kea', 'dnsreg')) {
		$enabled['default'] = true;
	}
	foreach (array_keys(config_get_path('dhcpd', [])) as $idx => $if) {
		$dnsregpolicy = config_get_path("dhcpd/${if}/dnsregpolicy", 'default');
		if (array_key_exists($dnsregpolicy, $enabled)) {
			$ids[$idx + 1] = true;
		}
	}
	return ($ids);
}

function kea_dnsreg_enabled(): bool {
	return ((bool)kea_dnsreg_enabled_ids());
}

function kea_dnsreg_subnet_enabled(int $subnet_id): bool {
	return (array_key_exists($subnet_id, kea_dnsreg_enabled_ids()));
}

function kea6_dnsreg_enabled_ids(): array {
	$ids = [];
	$enabled['enable'] = true;
	if (config_path_enabled('kea6', 'dnsreg')) {
		$enabled['default'] = true;
	}
	foreach (array_keys(config_get_path('dhcpdv6', [])) as $idx => $if) {
		$dnsregpolicy = config_get_path("dhcpdv6/${if}/dnsregpolicy", 'default');
		if (array_key_exists($dnsregpolicy, $enabled)) {
			$ids[$idx + 1] = true;
		}
	}
	return ($ids);
}

function kea6_dnsreg_enabled(): bool {
	return ((bool)kea6_dnsreg_enabled_ids());
}

function kea6_dnsreg_subnet_enabled(int $subnet_id): bool {
	return (array_key_exists($subnet_id, kea6_dnsreg_enabled_ids()));
}

function kea_format_age(string|null $age): string {
	if (!isset($age)) {
		return gettext('N/A');
	}

	$parts = [];
	$age = intval($age);

	$values = [
		'day' => floor($age / 86400),
		'hour' => floor(($age % 86400) / 3600),
		'minute' => floor((($age % 86400) % 3600) / 60),
		'second' => ceil((($age % 86400) % 3600) % 60)
	];

	$pluralize = fn($n, $v) => ($v == 1) ? "{$n}" : "{$n}s";

	foreach ($values as $name => $value) {
		if ($value > 0) {
			$parts[] = "{$value} {$pluralize($name, $value)}";
		}
	}

	if (empty($parts)) {
		$parts[] = gettext('0 seconds');
	}

	return (implode(', ', $parts). ' ' . gettext('ago'));
}

function dhcp_ha_status_icon($where, $server) {
	/* local is always assumed online */
	$class = 'text-success';
	$icon = 'fa-solid fa-heart';
	$title = gettext('Online');

	if ($where === 'remote') {
		$heartbeatdelay = (int) config_get_path('kea/ha/heartbeatdelay', kea_defaults('heartbeatdelay'));
		$heartbeatdelay /= 1000;
		$heartbeatdelay += 2; /* some grace */
		if ($server['age'] >= $heartbeatdelay) {
			$class = 'text-warning';
			$icon = 'fa-solid fa-heart-pulse';
			$title = gettext('Interrupted');
		}
		if (!$server['in-touch'] || $server['communication-interrupted']) {
			$class = 'text-danger';
			$icon = 'fa-solid fa-heart-crack';
			$title = gettext('Offline');
		}
	}

	printf('<i class="%s %s" title="%s"></i>', $icon, $class, $title);
}

function dhcp_validate_settings_post(array $pconfig): array {
	$input_errors = [];

	/* validate local name */
	$localname = trim(array_get_path($pconfig, 'ha/localname'));
	if (!(empty($localname) || is_hostname($localname))) {
		$input_errors[] = gettext('Local name must be hostname-like.');
	}

	/* validate remote name */
	$remotename = trim(array_get_path($pconfig, 'ha/remotename'));
	if ((array_path_enabled($pconfig, 'ha') && empty($remotename)) ||
	    !(empty($remotename) || is_hostname($remotename))) {
		$input_errors[] = gettext('Remote name must be non-empty and hostname-like.');
	}

	/* validate addresses */
	foreach ([
		[
			'msg' => gettext('Local'),
			'val' => trim(array_get_path($pconfig, 'ha/localip'))
		], [
			'msg' => gettext('Remote'),
			'val' => trim(array_get_path($pconfig, 'ha/remoteip'))
		]
	] as $ip) {
		if (((array_path_enabled($pconfig, 'ha')) && (empty($ip['val']))) ||
		    !(empty($ip['val']) || is_ipaddr($ip['val'])))  {
			$input_errors[] = sprintf(gettext('%s address must be a valid IPv4 or IPv6 address.'), $ip['msg']);
		}
	}

	/* validate ports */
	foreach ([
		[
			'msg' => gettext('local'),
			'val' => trim(array_get_path($pconfig, 'ha/localport'))
		], [
			'msg' => gettext('remote'),
			'val' => trim(array_get_path($pconfig, 'ha/remoteport'))
		]
	] as $port) {
		if (!(empty($port['val']) || ctype_digit($port['val']) || is_port($port['val'])))  {
			$input_errors[] = sprintf(gettext('Invalid %s port.'), $port['msg']);
		}
	}

	/* validate custom config */
	if (!empty($pconfig['custom_kea_config'])) {
		$json = json_decode(base64_decode(array_get_path($pconfig, 'custom_kea_config')), true);
		if (!is_array($json) || (json_last_error() !== JSON_ERROR_NONE)) {
			$input_errors[] = gettext('Custom configuration is not a well formed JSON object.');
		}
	}

    return ($input_errors);
}

function dhcp_do_settings_post(): array {
	$pconfig = $old_config = config_get_path('kea', []);

	/* normalize these fields so that changes comparison below works properly */
	foreach ([
		['', 'dnsreg'],
		['', 'earlydnsreg'],
		['ha', 'enable'], 
		['ha', 'tls'],
		['ha', 'mutualtls']
	] as $parts) {
		if (array_path_enabled($old_config, $parts[0], $parts[1])) {
			array_set_path($old_config, ltrim(implode('/', $parts), '/'), true);
		}
	}

	if (isset($_POST['dnsreg'])) {
		array_set_path($pconfig, 'dnsreg', true);
	} else {
		array_del_path($pconfig, 'dnsreg');
	}

	if (isset($_POST['earlydnsreg'])) {
		array_set_path($pconfig, 'earlydnsreg', true);
	} else {
		array_del_path($pconfig, 'earlydnsreg');
	}

	if (isset($_POST['ha_enable'])) {
		array_set_path($pconfig, 'ha/enable', true);
		array_set_path($pconfig, 'ha/role', $_POST['ha_role']);
	} else {
		array_del_path($pconfig, 'ha/enable');
		array_del_path($pconfig, 'ha/role');
	}

	if (isset($_POST['ha_tls'])) {
		array_set_path($pconfig, 'ha/tls', true);
		array_set_path($pconfig, 'ha/scertref', $_POST['ha_scertref']);
		if (isset($_POST['ha_mutualtls'])) {
			array_set_path($pconfig, 'ha/mutualtls', true);
			array_set_path($pconfig, 'ha/ccertref', $_POST['ha_ccertref']);
		} else {
			array_del_path($pconfig, 'ha/mutualtls');
			array_del_path($pconfig, 'ha/ccertref');
		}
	} else {
		array_del_path($pconfig, 'ha/tls');
		array_del_path($pconfig, 'ha/scertref');
		array_del_path($pconfig, 'ha/mutualtls');
		array_del_path($pconfig, 'ha/ccertref');
	}

	/* these are optional settings */
	foreach ([
		'ha_localname' => 'ha/localname',
		'ha_localip' => 'ha/localip',
		'ha_localport' => 'ha/localport',
		'ha_remotename' => 'ha/remotename',
		'ha_remoteip' => 'ha/remoteip',
		'ha_remoteport' => 'ha/remoteport',
		'ha_heartbeatdelay' => 'ha/heartbeatdelay',
		'ha_maxresponsedelay' => 'ha/maxresponsedelay',
		'ha_maxackdelay' => 'ha/maxackdelay',
		'ha_maxunackedclients' => 'ha/maxunackedclients',
		'ha_maxrejectedleaseupdates' => 'ha/maxrejectedleaseupdates'
	] as $id => $path) {
		if (!empty($_POST[$id])) {
			array_set_path($pconfig, $path, $_POST[$id]);
		} else {
			array_del_path($pconfig, $path);
		}
	}

	if (!empty($_POST['custom_kea_config'])) {
		array_set_path($pconfig, 'custom_kea_config', base64_encode($_POST['custom_kea_config']));
	} else {
		array_del_path($pconfig, 'custom_kea_config');
	}

	/* don't leave empty ha config section */
	if (empty(array_get_path($pconfig, 'ha', []))) {
		array_del_path($pconfig, 'ha');
	}

	$input_errors = dhcp_validate_settings_post($pconfig);
	if (!$input_errors && ($pconfig !== $old_config)) {
		config_set_path('kea', $pconfig);
		write_config(gettext('DHCP Server - Settings changed'));
		if (is_dhcp_server_enabled()) {
			mark_subsystem_dirty('dhcpd');
		}
	}

	return ([$input_errors, $pconfig]);
}

function dhcp6_do_settings_post(): array {
	$pconfig = $old_config = config_get_path('kea6', []);

	/* normalize these fields so that changes comparison below works properly */
	foreach ([
		['', 'dnsreg'],
		['', 'earlydnsreg'],
		['ha', 'enable'], 
		['ha', 'tls'],
		['ha', 'mutualtls']
	] as $parts) {
		if (array_path_enabled($old_config, $parts[0], $parts[1])) {
			array_set_path($old_config, implode('/', $parts), true);
		}
	}

	if (isset($_POST['dnsreg'])) {
		array_set_path($pconfig, 'dnsreg', true);
	} else {
		array_del_path($pconfig, 'dnsreg');
	}

	if (isset($_POST['earlydnsreg'])) {
		array_set_path($pconfig, 'earlydnsreg', true);
	} else {
		array_del_path($pconfig, 'earlydnsreg');
	}

	if (isset($_POST['ha_enable'])) {
		array_set_path($pconfig, 'ha/enable', true);
		array_set_path($pconfig, 'ha/role', $_POST['ha_role']);
	} else {
		array_del_path($pconfig, 'ha/enable');
		array_del_path($pconfig, 'ha/role');
	}

	if (isset($_POST['ha_tls'])) {
		array_set_path($pconfig, 'ha/tls', true);
		array_set_path($pconfig, 'ha/scertref', $_POST['ha_scertref']);
		if (isset($_POST['ha_mutualtls'])) {
			array_set_path($pconfig, 'ha/mutualtls', true);
			array_set_path($pconfig, 'ha/ccertref', $_POST['ha_ccertref']);
		} else {
			array_del_path($pconfig, 'ha/mutualtls');
			array_del_path($pconfig, 'ha/ccertref');
		}
	} else {
		array_del_path($pconfig, 'ha/tls');
		array_del_path($pconfig, 'ha/scertref');
		array_del_path($pconfig, 'ha/mutualtls');
		array_del_path($pconfig, 'ha/ccertref');
	}

	/* these are optional settings */
	foreach ([
		'ha_localname' => 'ha/localname',
		'ha_localip' => 'ha/localip',
		'ha_localport' => 'ha/localport',
		'ha_remotename' => 'ha/remotename',
		'ha_remoteip' => 'ha/remoteip',
		'ha_remoteport' => 'ha/remoteport',
		'ha_heartbeatdelay' => 'ha/heartbeatdelay',
		'ha_maxresponsedelay' => 'ha/maxresponsedelay',
		'ha_maxackdelay' => 'ha/maxackdelay',
		'ha_maxunackedclients' => 'ha/maxunackedclients',
		'ha_maxrejectedleaseupdates' => 'ha/maxrejectedleaseupdates'
	] as $id => $path) {
		if (!empty($_POST[$id])) {
			array_set_path($pconfig, $path, $_POST[$id]);
		} else {
			array_del_path($pconfig, $path);
		}
	}

	if (!empty($_POST['custom_kea_config'])) {
		array_set_path($pconfig, 'custom_kea_config', base64_encode($_POST['custom_kea_config']));
	} else {
		array_del_path($pconfig, 'custom_kea_config');
	}

	/* don't leave empty ha config section */
	if (empty(array_get_path($pconfig, 'ha', []))) {
		array_del_path($pconfig, 'ha');
	}

	$input_errors = dhcp_validate_settings_post($pconfig);
	if (!$input_errors && ($pconfig !== $old_config)) {
		config_set_path('kea6', $pconfig);
		write_config(gettext('DHCPv6 Server - Settings changed'));
		if (is_dhcpv6_server_enabled()) {
			mark_subsystem_dirty('dhcpd6');
		}
	}

	return ([$input_errors, $pconfig]);
}

function dhcp_apply_changes(): int {
	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
	/* dnsmasq_configure calls dhcpd_configure */
	/* no need to restart dhcpd twice */
	if (config_path_enabled('dnsmasq')) {
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd');
		}
	} elseif (config_path_enabled('unbound')) {
		$retvaldns |= services_unbound_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('unbound');
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd');
		}
	} else {
		$retvaldhcp |= services_dhcpd_configure();
		if ($retvaldhcp == 0) {
			clear_subsystem_dirty('dhcpd');
		}
	}
	/* BIND package - Bug #3710 */
	if (!function_exists('is_package_installed')) {
		require_once('pkg-utils.inc');
	}
	if (is_package_installed('pfSense-pkg-bind') &&
	    config_path_enabled('installedpackages/bind/config/0', 'enable_bind') &&
	    dhcp_is_backend('isc')) {
		$reloadbind = false;
		$bindzone = config_get_path('installedpackages/bindzone/config', []);

		for ($x = 0; $x < sizeof($bindzone); $x++) {
			$zone = $bindzone[$x];
			if ($zone['regdhcpstatic'] == 'on') {
				$reloadbind = true;
				break;
			}
		}
		if ($reloadbind === true) {
			if (file_exists("/usr/local/pkg/bind.inc")) {
				require_once("/usr/local/pkg/bind.inc");
				bind_sync();
			}
		}
	}
	$retvalfc |= filter_configure();
	if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
		$retval = 1;
	}
	return $retval;
}

function dhcp6_apply_changes(): int {
	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
	/* dnsmasq_configure calls dhcpd_configure */
	/* no need to restart dhcpd twice */
	if (config_path_enabled('dnsmasq')) {
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd6');
		}
	} elseif (config_path_enabled('unbound')) {
		$retvaldns |= services_unbound_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('unbound');
			clear_subsystem_dirty('dhcpd6');
		}
	} else {
		$retvaldhcp |= services_dhcpd_configure();
		if ($retvaldhcp == 0) {
			clear_subsystem_dirty('dhcpd6');
		}
	}
	/* BIND package - Bug #3710 */
	if (!function_exists('is_package_installed')) {
		require_once('pkg-utils.inc');
	}
	if (is_package_installed('pfSense-pkg-bind') &&
	    config_path_enabled('installedpackages/bind/config/0', 'enable_bind')) {
		$reloadbind = false;
		$bindzone = config_get_path('installedpackages/bindzone/config', []);

		for ($x = 0; $x < sizeof($bindzone); $x++) {
			$zone = $bindzone[$x];
			if ($zone['regdhcpstatic'] == 'on') {
				$reloadbind = true;
				break;
			}
		}
		if ($reloadbind === true) {
			if (file_exists("/usr/local/pkg/bind.inc")) {
				require_once("/usr/local/pkg/bind.inc");
				bind_sync();
			}
		}
	}
	$retvalfc |= filter_configure();
	if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
		$retval = 1;
	}
	return $retval;
}

function kea_server_cert_list(): array {
	return (array_filter(cert_build_list('cert', 'HTTPS'), function($refid) {
		$cert = lookup_cert($refid);
		$is_valid = false;
		if (isset($cert['item']) && isset($cert['item']['caref'])) {
			if (isset($cert['item']['type']) && ($cert['item']['type'] == 'server')) {
				$is_valid = true;
			} elseif (isset($cert['item']['crt'])) {
				$purpose = cert_get_purpose($cert['item']['crt']);
				$is_valid = ($purpose['server'] == 'Yes');
			}
		}
		return $is_valid;
	}, ARRAY_FILTER_USE_KEY));
}

function kea_client_cert_list(): array {
	return (array_filter(cert_build_list('cert', 'HTTPS'), function($refid) {
		$cert = lookup_cert($refid);
		$is_valid = false;
		if (isset($cert['item']) && isset($cert['item']['caref'])) {
			if (isset($cert['item']['type']) && ($cert['item']['type'] == 'user')) {
				$is_valid = true;
			} elseif (isset($cert['item']['crt'])) {
				$purpose = cert_get_purpose($cert['item']['crt']);
				$is_valid = ($purpose['server'] == 'No');
			}
		}
		return $is_valid;
	}, ARRAY_FILTER_USE_KEY));
}