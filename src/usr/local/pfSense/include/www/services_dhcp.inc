<?php
/*
 * services_dhcp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2024 Rubicon Communications, LLC (Netgate)
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
		'heartbeatdelay'			=> 10000,
		'listenport'				=> 8765,
		'maxackdelay'				=> 60000,
		'maxrejectedleaseupdates'	=> 10,
		'maxresponsedelay' 			=> 60000,
		'maxunackedclients' 		=> 0,
		'name' 						=> config_get_path('system/hostname', g_get('product_name'))
	});
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

    return ($input_errors);
}

function dhcp_do_settings_post(array $subnets): array {
	$pconfig = $old_config = config_get_path('kea', []);

	/* normalize these fields so that changes comparison below works properly */
	foreach ([
		['', 'hidedisabled'],
		['ha', 'enable'], 
		['ha', 'tls'],
		['ha', 'mutualtls']
	] as $parts) {
		if (array_path_enabled($old_config, $parts[0], $parts[1])) {
			array_set_path($old_config, implode('/', $parts), true);
		}
	}

	if (isset($_POST['hidedisabled'])) {
		array_set_path($pconfig, 'hidedisabled', true);
	} else {
		array_del_path($pconfig, 'hidedisabled');
	}

	if (isset($_POST['ha_enable'])) {
		array_set_path($pconfig, 'ha/enable', true);
	} else {
		array_del_path($pconfig, 'ha/enable');
	}

	array_set_path($pconfig, 'ha/role', $_POST['ha_role']);
	array_set_path($pconfig, 'ha/localip', $_POST['ha_localip']);
	array_set_path($pconfig, 'ha/remoteip', $_POST['ha_remoteip']);
	array_set_path($pconfig, 'ha/remotename', $_POST['ha_remotename']);

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
		'ha_localport' => 'ha/localport',
		'ha_remoteport' => 'ha/remoteport',
		'ha_heartbeatdelay' => 'ha/heartbeatdelay',
		'ha_maxresponsedelay' => 'ha/maxresponsedelay',
		'ha_maxackdelay' => 'ha/maxackdelay',
		'ha_maxunackedclients' => 'ha/maxunackedclients',
		'ha_maxrejectedleaseupdates' => 'ha/maxrejectedleaseupdates'
	] as $id => $path) {
		if (isset($_POST[$id])) {
			array_set_path($pconfig, $path, $_POST[$id]);
		} else {
			array_del_path($pconfig, $path);
		}
	}

	$input_errors = dhcp_validate_settings_post($pconfig);
	if (!$input_errors) {
		$changes = ($pconfig !== $old_config);
		$need_sync = false;

		/* determine interfaces to enable */
		foreach (array_diff((array) $_POST['interface'], $subnets['enabled']) as $ifent) {
			config_set_path("dhcpd/{$ifent}/enable", true);
			$changes = $need_sync = true;
		}

		/* determine interfaces to disable */
		foreach (array_diff($subnets['enabled'], (array) $_POST['interface']) as $ifent) {
			config_del_path("dhcpd/{$ifent}/enable");
			$changes = $need_sync = true;
        }

		/* write the updated config and determine if we really need to resync */
		if ($changes) {
			config_set_path('kea', $pconfig);
			write_config(gettext('DHCP Server - Settings changed'));

			if ($need_sync ||
			    ($pconfig['ha'] !== $old_config['ha'])) {
				mark_subsystem_dirty('dhcpd');
			}
		}
	}

	return ([$input_errors, $pconfig]);
}

function dhcp6_do_settings_post(array $subnets): array {
	$pconfig = $old_config = config_get_path('kea6', []);

	/* normalize these fields so that changes comparison below works properly */
	foreach ([
		['', 'hidedisabled'],
		['ha', 'enable'], 
		['ha', 'tls'],
		['ha', 'mutualtls']
	] as $parts) {
		if (array_path_enabled($old_config, $parts[0], $parts[1])) {
			array_set_path($old_config, implode('/', $parts), true);
		}
	}

	if (isset($_POST['hidedisabled'])) {
		array_set_path($pconfig, 'hidedisabled', true);
	} else {
		array_del_path($pconfig, 'hidedisabled');
	}

	if (isset($_POST['ha_enable'])) {
		array_set_path($pconfig, 'ha/enable', true);
	} else {
		array_del_path($pconfig, 'ha/enable');
	}

	array_set_path($pconfig, 'ha/role', $_POST['ha_role']);
	array_set_path($pconfig, 'ha/localip', $_POST['ha_localip']);
	array_set_path($pconfig, 'ha/remoteip', $_POST['ha_remoteip']);
	array_set_path($pconfig, 'ha/remotename', $_POST['ha_remotename']);

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
		'ha_localport' => 'ha/localport',
		'ha_remoteport' => 'ha/remoteport',
		'ha_heartbeatdelay' => 'ha/heartbeatdelay',
		'ha_maxresponsedelay' => 'ha/maxresponsedelay',
		'ha_maxackdelay' => 'ha/maxackdelay',
		'ha_maxunackedclients' => 'ha/maxunackedclients',
		'ha_maxrejectedleaseupdates' => 'ha/maxrejectedleaseupdates'
	] as $id => $path) {
		if ($_POST[$id]) {
			array_set_path($pconfig, $path, $_POST[$id]);
		} else {
			array_del_path($pconfig, $path);
		}
	}

	$input_errors = dhcp_validate_settings_post($pconfig);
	if (!$input_errors) {
		$changes = ($pconfig !== $old_config);
		$need_sync = false;

		/* determine interfaces to enable */
		foreach (array_diff((array) $_POST['interface'], $subnets['enabled']) as $ifent) {
			config_set_path("dhcpdv6/{$ifent}/enable", true);
			$changes = $need_sync = true;
		}

		/* determine interfaces to disable */
		foreach (array_diff($subnets['enabled'], (array) $_POST['interface']) as $ifent) {
			config_del_path("dhcpdv6/{$ifent}/enable");
			$changes = $need_sync = true;
        }

		/* write the updated config and determine if we really need to resync */
		if ($changes) {
			config_set_path('kea6', $pconfig);
			write_config(gettext('DHCPv6 Server - Settings changed'));

			if ($need_sync ||
			    ($pconfig['ha'] !== $old_config['ha'])) {
				mark_subsystem_dirty('dhcpd6');
			}
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
	if (config_path_enabled('dnsmasq') &&
	    config_path_enabled('dnsmasq', 'regdhcpstatic') &&
	    dhcp_is_backend('isc')) {
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd');
		}
	} elseif (config_path_enabled('unbound') &&
	    config_path_enabled('unbound', 'regdhcpstatic') &&
	    dhcp_is_backend('isc')) {
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
	if (config_path_enabled('dnsmasq') &&
	    config_path_enabled('dnsmasq', 'regdhcpstatic')) {
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd6');
		}
	} elseif (config_path_enabled('unbound') &&
	    config_path_enabled('unbound', 'regdhcpstatic')) {
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
		$isServer = $cert['item']['type'] === 'server';
		$hasCA = isset($cert['item']['caref']);
		return $isServer && $hasCA;
	}, ARRAY_FILTER_USE_KEY));
}

function kea_client_cert_list(): array {
	return (array_filter(cert_build_list('cert', 'HTTPS'), function($refid) {
		$cert = lookup_cert($refid);
		$isClient = $cert['item']['type'] === 'user';
		$hasCA = isset($cert['item']['caref']);
		return $isClient && $hasCA;
	}, ARRAY_FILTER_USE_KEY));
}

function kea_build_subnet_list(): array {
	$list = [
		'available' => [],
		'enabled' => []
	];
	$available = &$list['available'];
	$enabled = &$list['enabled'];

	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $ifent => $ifname) {
		$oc = config_get_path("interfaces/{$ifent}", []);
	
		/* Not static IPv4 or subnet >= 31 */
		if (($oc['subnet'] >= 31) ||
		    !is_ipaddrv4($oc['ipaddr']) ||
		    empty($oc['subnet'])) {
			continue;
		}

		$available[$ifent] = sprintf('%s (%s)', $ifname, $ifent);

		if (config_path_enabled("dhcpd/{$ifent}")) {
			$enabled[] = $ifent;
		}
	}

	return ($list);
}

function kea_build_prefix_list(): array {
	$list = [
		'available' => [],
		'enabled' => []
	];
	$available = &$list['available'];
	$enabled = &$list['enabled'];

	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $ifent => $ifname) {
		$oc = config_get_path("interfaces/{$ifent}", []);


		$valid = (bool) ($oc['ipaddrv6'] === 'track6' ||
		    (is_ipaddrv6($oc['ipaddrv6']) &&
		    !is_linklocal($oc['ipaddrv6'])));

		if (!$valid) {
			continue;
		}

		$available[$ifent] = sprintf('%s (%s)', $ifname, $ifent);

		if (config_path_enabled("dhcpdv6/{$ifent}")) {
			$enabled[] = $ifent;
		}
	}

	return ($list);
}