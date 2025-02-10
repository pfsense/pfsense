<?php
/*
 * config.console.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("config.inc");
require_once("globals.inc");
require_once("interfaces.inc");
require_once("util.inc");

/*
 * returns:
 * -2: error
 * -1: no interface found
 *  0: interface(s) assigned
 *  1: user quit
 */
function set_networking_interfaces_ports() {
	global $g;
	global $fp;

	$fp = fopen('php://stdin', 'r');

	$memory = get_memory();
	$physmem = $memory[0];
	$realmem = $memory[1];

	if ($physmem < g_get('minimum_ram_warning')) {
		echo "\n\n\n";
		echo gettext("DANGER!  WARNING!  ACHTUNG!") . "\n\n";
		printf(gettext('%1$s requires *AT LEAST* %2$s RAM to function correctly.%3$s'), g_get('product_label'), g_get('minimum_ram_warning_text'), "\n");
		printf(gettext('Only (%1$s) MB RAM has been detected, with (%2$s) available to %3$s.%4$s'), $realmem, $physmem, g_get('product_label'), "\n");
		echo "\n" . gettext("Press ENTER to continue.") . " ";
		fgets($fp);
		echo "\n";
	}

	$iflist = get_interface_list('active', 'physical', true);

	/* Function flow is based on $key or the lack thereof */
	$key = null;

	echo <<<EOD

Valid interfaces are:


EOD;

	if (!is_array($iflist)) {
		echo gettext("No interfaces found!") . "\n";
		return (-1);
	} else {
		// ifsmallist is kept with spaces at the beginning and end to assist with str_replace() operations
		$ifsmallist = " ";
		foreach ($iflist as $iface => $ifa) {
			$friendly = convert_real_interface_to_friendly_interface_name($iface);
			$ifstatus = (!empty($friendly)) ? get_interface_addresses(config_get_path("interfaces/{$friendly}/if", "")) : null;
			if (isset($ifstatus) && $ifstatus['linkstateup'])
				$status = "  (up)";
			else
				$status = "(down)";
			$ifsmallist = $ifsmallist . $iface. " ";
			echo sprintf("%-7s %s %s %s\n", $iface, $ifa['mac'],
				$status, substr($ifa['dmesg'], 0, 46));
		}
	}

	echo "\n" . gettext("Do VLANs need to be set up first?");
	echo "\n" .
		gettext(
			"If VLANs will not be used, or only for optional interfaces, it is typical to\n" .
			"say no here and use the webConfigurator to configure VLANs later, if required.") .
		"\n";
	echo "\n" . gettext("Should VLANs be set up now [y|n]?") . " ";

	$key = chop(fgets($fp));

	//Manually assign interfaces
	if (in_array($key, array('y', 'Y'))) {
		vlan_setup();
	}

	$vlans = config_get_path('vlans/vlan', []);
	if (is_array($vlans) && count($vlans)) {
		echo "\n\n" . gettext("VLAN interfaces:") . "\n\n";
		foreach ($vlans as $vlan) {
			echo sprintf("% -16s%s\n", vlan_interface($vlan),
				"VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}");
			$iflist[vlan_interface($vlan)] = array();
			$ifsmallist = $ifsmallist . vlan_interface($vlan) . " ";
		}
	}

	echo <<<EOD

If the names of the interfaces are not known, auto-detection can
be used instead. To use auto-detection, please disconnect all
interfaces before pressing 'a' to begin the process.

EOD;

	do {
		echo "\n" . gettext("Enter the WAN interface name or 'a' for auto-detection") . " ";
		printf(gettext('%1$s(%2$s or a): '), "\n", trim($ifsmallist));
		$wanif = chop(fgets($fp));
		if ($wanif === "") {
			return (1);
		}
		if ($wanif === "a") {
			$wanif = autodetect_interface("WAN", $fp);
		} else if (!array_key_exists($wanif, $iflist)) {
			printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $wanif, "\n");
			unset($wanif);
			continue;
		}
		$ifsmallist = str_replace(" " . $wanif . " ", " ", $ifsmallist);
	} while (!$wanif);

	do {
		printf(gettext('%1$sEnter the LAN interface name or \'a\' for auto-detection %2$s' .
			'NOTE: this enables full Firewalling/NAT mode.%3$s' .
			'(%4$s a or nothing if finished):%5$s'), "\n", "\n", "\n", trim($ifsmallist), " ");

		$lanif = chop(fgets($fp));

		if ($lanif == "exit") {
			exit;
		}

		if ($lanif == "") {
			/* It is OK to have just a WAN, without a LAN so break if the user does not want LAN. */
			break;
		}

		if ($lanif === "a") {
			$lanif = autodetect_interface("LAN", $fp);
		} else if (!array_key_exists($lanif, $iflist)) {
			printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $lanif, "\n");
			unset($lanif);
			continue;
		}
		$ifsmallist = str_replace(" " . $lanif . " ", " ", $ifsmallist);
	} while (!$lanif);

	/* optional interfaces */
	$i = 0;
	$optif = array();

	if ($lanif <> "") {
		while (strlen(trim($ifsmallist)) > 0) {
			if (!empty($optif[$i])) {
				$i++;
			}
			$io = $i + 1;

			$if_descr_config = config_get_path("interfaces/opt{$io}/descr");
			if ($if_descr_config) {
				printf(gettext('%1$sOptional interface %2$s description found: %3$s'), "\n", $io, $if_descr_config);
			}

			printf(gettext('%1$sEnter the Optional %2$s interface name or \'a\' for auto-detection%3$s' .
				'(%4$s a or nothing if finished):%5$s'), "\n", $io, "\n", trim($ifsmallist), " ");

			$optif[$i] = chop(fgets($fp));

			if ($optif[$i]) {
				if ($optif[$i] === "a") {
					$ad = autodetect_interface(gettext("Optional") . " " . $io, $fp);
					if ($ad) {
						$optif[$i] = $ad;
					} else {
						unset($optif[$i]);
					}
				} else if (!array_key_exists($optif[$i], $iflist)) {
					printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $optif[$i], "\n");
					unset($optif[$i]);
					continue;
				}
				$ifsmallist = str_replace(" " . $optif[$i] . " ", " ", $ifsmallist);
			} else {
				unset($optif[$i]);
				break;
			}
		}
	}

	/* check for double assignments */
	$ifarr = array_merge(array($lanif, $wanif), $optif);

	for ($i = 0; $i < (count($ifarr)-1); $i++) {
		for ($j = ($i+1); $j < count($ifarr); $j++) {
			if ($ifarr[$i] == $ifarr[$j]) {
				echo <<<EOD

Error: The same interface name cannot be assigned twice!

EOD;
				fclose($fp);
				return (-2);
			}
		}
	}

	echo "\n" . gettext("The interfaces will be assigned as follows:") . "\n\n";

	echo "WAN  -> " . $wanif . "\n";
	if ($lanif != "") {
		echo "LAN  -> " . $lanif . "\n";
	}
	for ($i = 0; $i < count($optif); $i++) {
		echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
	}

	echo "\n" . gettext("Do you want to proceed [y|n]?") . " ";
	$key = chop(fgets($fp));

	if (in_array($key, array('y', 'Y'))) {
		if ($lanif) {
			$if_lan_config = config_get_path('interfaces/lan');
			if (is_array($if_lan_config)) {
				$upints = pfSense_interface_listget(IFF_UP);
				if (in_array($if_lan_config['if'], $upints))
					interface_bring_down('lan', true);
			}
			if (!is_array($if_lan_config)) {
				$if_lan_config['lan'] = array();
			}
			$if_lan_config['if'] = $lanif;
			$if_lan_config['enable'] = true;
			config_set_path('interfaces/lan', $if_lan_config);
		} elseif (!is_platform_booting()) {

			echo "\n" . gettext("You have chosen to remove the LAN interface.") . "\n";
			echo "\n" . gettext("Would you like to remove the LAN IP address and \nunload the interface now [y|n]?") . " ";

			$if_lan_config = config_get_path('interfaces/lan');
			if (strcasecmp(chop(fgets($fp)), "y") == 0) {
				if (isset($if_lan_config) && $if_lan_config['if']) {
					mwexec("/sbin/ifconfig {$if_lan_config['if']} delete");
				}
			}
			if (isset($if_lan_config)) {
				config_del_path('interfaces/lan');
			}

			config_del_path('dhcpd/lan');
			config_del_path('dhcpdv6/lan');
			config_del_path('interfaces/wan/blockpriv');
			config_del_path('shaper');
			config_del_path('ezshaper');
			config_del_path('nat');
		} else {
			$if_lan_config = config_get_path('interfaces/lan');
			if (isset($if_lan_config['if'])) {
				mwexec("/sbin/ifconfig {$if_lan_config['if']} delete");
			}
			if (isset($if_lan_config)) {
				config_del_path('interfaces/lan');
			}
			config_del_path('dhcpd/lan');
			config_del_path('dhcpdv6/lan');
			config_del_path('interfaces/wan/blockpriv');
			config_del_path('shaper');
			config_del_path('ezshaper');
			config_del_path('nat');
		}
		$if_lan_config = config_get_path('interfaces/lan');
		if (preg_match(g_get('wireless_regex'), $lanif)) {
			if (is_array($if_lan_config) &&
			    !is_array($if_lan_config['wireless'])) {
				config_set_path('interfaces/lan/wireless', []);
			}
		} else {
			if (isset($if_lan_config)) {
				config_del_path('interfaces/lan/wireless');
			}
		}

		
		if (is_array(config_get_path('interfaces/wan'))) {
			$upints = pfSense_interface_listget(IFF_UP);
			if (in_array(config_get_path('interfaces/wan/if'), $upints))
				interface_bring_down('wan', true);
		}

		$if_wan_config = config_get_path('interfaces/wan', []);
		$if_wan_config['if'] = $wanif;
		$if_wan_config['enable'] = true;
		if (preg_match(g_get('wireless_regex'), $wanif)) {
			if (is_array($if_wan_config) &&
			    !is_array($if_wan_config['wireless'])) {
				$if_wan_config['wireless'] = array();
			}
		} else {
			if (isset($if_wan_config)) {
				unset($if_wan_config['wireless']);
			}
		}
		config_set_path('interfaces/wan', $if_wan_config);

		$if_opt_counter = 0;
		for (; $if_opt_counter < count($optif); $if_opt_counter++) {
			$if_opt_name = 'opt' . ($if_opt_counter+1);
			if (is_array(config_get_path("interfaces/{$if_opt_name}"))) {
				$upints = pfSense_interface_listget(IFF_UP);
				if (in_array(config_get_path("interfaces/{$if_opt_name}/if"), $upints))
					interface_bring_down($if_opt_name, true);
			}
			$if_opt_config = config_get_path("interfaces/{$if_opt_name}", []);
			$if_opt_config['if'] = $optif[$if_opt_counter];

			/* wireless interface? */
			if (preg_match(g_get('wireless_regex'), $optif[$i])) {
				if (!is_array($if_opt_config['wireless'])) {
					$if_opt_config['wireless'] = array();
				}
			} else {
				unset($if_opt_config['wireless']);
			}

			if (empty($if_opt_config['descr'])) {
				$if_opt_config['descr'] = strtoupper($if_opt_name);
			}
			config_set_path("interfaces/{$if_opt_name}", $if_opt_config);
		}

		/* remove all other (old) optional interfaces */
		$if_config = config_get_path('interfaces', []);
		for (; isset($if_config['opt' . ($if_opt_counter+1)]); $if_opt_counter++) {
			unset($if_config['opt' . ($if_opt_counter+1)]);
		}
		config_set_path('interfaces', $if_config);

		printf(gettext("%sWriting configuration..."), "\n");
		write_config(gettext("Console assignment of interfaces"));
		printf(gettext("done.%s"), "\n");

		fclose($fp);

		echo gettext("One moment while the settings are reloading...");
		touch("{$g['tmp_path']}/assign_complete");

		if (file_exists("{$g['conf_path']}/trigger_initial_wizard")) {
			// Let the system know that the interface assign part of initial setup has been done.
			touch("{$g['conf_path']}/assign_complete");
		}

		echo gettext(" done!") . "\n";
		return (0);
	}
	return (1);
}

function autodetect_interface($ifname, $fp) {
	$iflist_prev = get_interface_list("media");
	echo <<<EOD

Connect the {$ifname} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
	fgets($fp);
	$iflist = get_interface_list("media");

	foreach ($iflist_prev as $ifn => $ifa) {
		if (!$ifa['up'] && $iflist[$ifn]['up']) {
			printf(gettext('Detected link-up on interface %1$s.%2$s'), $ifn, "\n");
			return $ifn;
		}
	}

	printf(gettext("No link-up detected.%s"), "\n");

	return null;
}

function interfaces_setup() {
	global $iflist;

	$iflist = get_interface_list();
}

function vlan_setup() {
	global $iflist, $fp;

	$iflist = get_interface_list();

	$vlancfg = config_get_path('vlans/vlan');
	if (is_array($vlancfg) && count($vlancfg)) {
		echo "\n" . gettext("WARNING: all existing VLANs will be cleared if you proceed!") . "\n";
		echo "\n" . gettext("Do you want to proceed [y|n]?") . " ";

		if (strcasecmp(chop(fgets($fp)), "y") != 0) {
			return;
		}
	}

	echo "\n";

	$vlanif = 0;

	while (1) {
		$vlan = array();

		echo "\n\n" . gettext("VLAN Capable interfaces:") . "\n\n";
		if (!is_array($iflist)) {
			echo gettext("No interfaces found!") . "\n";
		} else {
			$vlan_capable = 0;
			foreach ($iflist as $iface => $ifa) {
				echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
				    $ifa['up'] ? "   (up)" : "");
				$vlan_capable++;
			}
		}

		if ($vlan_capable == 0) {
			echo gettext("No VLAN capable interfaces detected.") . "\n";
			return;
		}

		echo "\n" . gettext("Enter the parent interface name for the new VLAN (or nothing if finished):") . " ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist)) {
				printf(gettext(
				    '%1$sInvalid interface name \'%2$s\'%3$s'),
				    "\n", $vlan['if'], "\n");
				continue;
			}
		} else {
			break;
		}

		echo gettext("Enter the VLAN tag (1-4094):") . " ";
		$vlan['tag'] = chop(fgets($fp));
		$vlan['vlanif'] = vlan_interface($vlan);
		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			printf(gettext('%1$sInvalid VLAN tag \'%2$s\'%3$s'), "\n", $vlan['tag'], "\n");
			continue;
		}

		foreach (config_get_path('vlans/vlan', []) as $existingvlan) {
			if ($vlan['if'] == $existingvlan['if'] && $vlan['tag'] == $existingvlan['tag']) {
				printf("\n\n" . gettext("This parent interface and VLAN already created."));
				continue 2;
			}
		}
		config_set_path('vlans/vlan/', $vlan);
		$vlanif++;
	}
}

function installer_add_gateway($if, $gateway) {

	if (!isset($if) || strlen($if) == 0 ||
	    !isset($gateway) || strlen($gateway) == 0) {
		return (-1);
	}

	/* Delete the gateway first, if already present. */
	$gws = config_get_path('gateways/gateway_item', []);
	$gws = array_filter($gws, function($gw) use ($gateway, $if) {
		if ($gw['gateway'] == $gateway && $gw['interface'] == $if) {
			return (false);
		}
		return (true);
	});
	config_set_path('gateways/gateway_item', $gws);

	/* Add the interface gateway. */
	$gwname = strtoupper($if) . "GW";
	$gw = [
	    'interface'			=> $if,
	    'gateway'			=> $gateway,
	    'ipprotocol'		=> 'inet',
	    'name'			=> $gwname,
	    'descr'			=> "",
	    'weight'			=> "",
	    'gw_down_kill_states'	=> "",
	];
	config_set_path('gateways/gateway_item/', $gw);
	config_set_path('gateways/defaultgw4', $gwname);
	config_set_path("interfaces/{$if}/gateway", $gwname);

	return (0);
}

function installer_add_pppoe($dev, $if, $user, $pass, $svc, $svc_null) {

	$newid = 0;

	/* Remove any existing PPPoE settings on the interface. */
	$ppps = config_get_path('ppps/ppp', []);
	$ppps = array_filter($ppps, function($ppp) use (&$newid, $dev) {
		if ($ppp['type'] == "pppoe" && $ppp['ports'] == $dev) {
			return (false);
		}
		$newid = max($newid, $ppp['ptpid'] + 1);
		return (true);
	});
	config_set_path('ppps/ppp', $ppps);

	/* Add the PPPoE connection. */
	$ppp = [
	    'ptpid'	=> $newid,
	    'type'	=> 'pppoe',
	    'if'	=> "pppoe{$newid}",
	    'ports'	=> $dev,
	    'username'	=> $user,
	    'password'	=> base64_encode($pass),
	    'bandwidth'	=> "",
	    'mtu'	=> "",
	    'mru'	=> "",
	    'mrru'	=> "",
	];
	if ($svc_null !== true) {
		$ppp['provider'] = $svc;
	}
	config_set_path('ppps/ppp/', $ppp);
	config_set_path("interfaces/{$if}/enable", '');
	config_set_path("interfaces/{$if}/ipaddr", 'pppoe');
	config_set_path("interfaces/{$if}/if", "pppoe{$newid}");
	config_del_path("interfaces/{$if}/subnet");
	config_del_path("interfaces/{$if}/media");
	config_del_path("interfaces/{$if}/mediaopt");
	config_del_path("interfaces/{$if}/gateway");

	return (0);
}

function installer_add_vlan($dev, $if, $descr, $tag, $pcp) {

	if (intval($tag) < 1 || intval($tag) > 4094 ||
	    intval($pcp) < 0 || intval($pcp) > 7) {
		return (-1);
	}

	/* Delete the VLAN is already present. */
	$vlans = config_get_path('vlans/vlan', []);
	$vlans = array_filter($vlans, function($vlan) use ($dev, $tag) {
		if ($vlan['tag'] == $tag && $vlan['if'] == $dev) {
			return (false);
		}
		return (true);
	});
	config_set_path('vlans/vlan', $vlans);

	/* Remove other interfaces using this VLAN tag. */
	$ifs = config_get_path('interfaces', []);
	$ifs = array_filter($ifs, function($intf) use ($dev, $if, $tag) {
		if ($intf != $if && $intf['if'] == "{$dev}.{$tag}") {
			return (false);
		}
		return (true);
	});
	config_set_path('interfaces', $ifs);

	/* Add the VLAN. */
	$vlan = [
	    'if'	=> $dev,
	    'tag'	=> $tag,
	    'pcp'	=> $pcp,
	    'descr'	=> $descr,
	    'vlanif'	=> "{$dev}.{$tag}",
	];
	config_set_path('vlans/vlan/', $vlan);

	return (0);
}

function installer_disable_lan() {
	config_del_path('interfaces/lan');

	return (0);
}

function installer_setup_lan($lan) {

	if (!isset($lan->if) ||
	    !does_interface_exist($lan->if)) {
		return (-1);
	}

	$if = $lan->if;

	/* VLAN setup. */
	if (isset($lan->vlan) && intval($lan->vlan) > 0) {
		if (installer_add_vlan($lan->if, "lan", "LAN",
		    $lan->vlan, $lan->vlan_pcp) != 0) {
			return (-1);
		}
		$if = "{$lan->if}.{$lan->vlan}";
	}

	config_set_path('interfaces/lan/if', $if);
	switch ($lan->mode) {
	case "DHCP":
		config_set_path('interfaces/lan/enable', '');
		config_set_path('interfaces/lan/ipaddr', 'dhcp');
		config_set_path('interfaces/lan/ipaddrv6', 'dhcp6');
		config_set_path('interfaces/lan/subnet', '');
		config_set_path('interfaces/lan/gateway', '');
		config_set_path('interfaces/lan/media', '');
		config_set_path('interfaces/lan/mediaopt', '');
		break;
	case "STATIC":
		list ($ip, $subnet) = explode("/", $lan->ip);
		if (empty($subnet)) {
			$subnet = "24";
		}
		config_set_path('interfaces/lan/enable', '');
		config_set_path('interfaces/lan/ipaddr', $ip);
		config_del_path('interfaces/lan/ipaddrv6');
		config_set_path('interfaces/lan/subnet', $subnet);
		config_set_path('interfaces/lan/media', '');
		config_set_path('interfaces/lan/mediaopt', '');
		break;
	}

	/* dhcpd. */
	if (isset($lan->dhcpd_enabled) &&
	    isset($lan->dhcpd_range_start) &&
	    isset($lan->dhcpd_range_end) &&
	    $lan->dhcpd_enabled === true) {
		config_set_path('dhcpd/lan/enable', '');
		config_set_path('dhcpd/lan/range/from', $lan->dhcpd_range_start);
		config_set_path('dhcpd/lan/range/to', $lan->dhcpd_range_end);
	} else {
		config_del_path('dhcpd/lan/enable');
	}

	return (0);
}

function installer_setup_wan($wan) {

	if (!isset($wan->if) ||
	    !does_interface_exist($wan->if)) {
		return (-1);
	}

	$if = $wan->if;

	/* VLAN setup. */
	if (isset($wan->vlan) && intval($wan->vlan) > 0) {
		if (installer_add_vlan($wan->if, "wan", "WAN",
		    $wan->vlan, $wan->vlan_pcp) != 0) {
			return (-1);
		}
		$if = "{$wan->if}.{$wan->vlan}";
	}

	config_set_path('interfaces/wan/if', $if);
	switch ($wan->mode) {
	case "DHCP":
		config_set_path('interfaces/wan/enable', '');
		config_set_path('interfaces/wan/ipaddr', 'dhcp');
		config_set_path('interfaces/wan/ipaddrv6', 'dhcp6');
		config_set_path('interfaces/wan/subnet', '');
		config_set_path('interfaces/wan/gateway', '');
		config_set_path('interfaces/wan/media', '');
		config_set_path('interfaces/wan/mediaopt', '');
		break;
	case "PPPoE":
		if (installer_add_pppoe($if, "wan", $wan->PPPoE_User,
		    $wan->PPPoE_Password, $wan->PPPoE_Service_Name,
		    $wan->PPPoE_NULL_Service) != 0) {
			return (-1);
		}
		break;
	case "STATIC":
		list ($ip, $subnet) = explode("/", $wan->ip);
		if (empty($subnet)) {
			$subnet = "24";
		}
		config_set_path('interfaces/wan/enable', '');
		config_set_path('interfaces/wan/ipaddr', $ip);
		config_del_path('interfaces/wan/ipaddrv6');
		config_set_path('interfaces/wan/subnet', $subnet);
		config_set_path('interfaces/wan/media', '');
		config_set_path('interfaces/wan/mediaopt', '');
		if (isset($wan->default_gateway) &&
		    strlen($wan->default_gateway) > 0) {
			if (installer_add_gateway("wan", $wan->default_gateway) != 0) {
				return (-1);
			}
		}
		break;
	}

	/* DNS. */
	if (isset($wan->DNS_server) && strlen($wan->DNS_server) > 0) {
		config_set_path('system/dnsserver', $wan->DNS_server);
	} else {
		config_del_path('system/dnsserver');
	}

	return (0);
}

function installer_settings_import() {
	global $g;

	/* Should not happen. */
	if (!file_exists("{$g['conf_path']}/installer-settings.json")) {
		return (-1);
	}
	/* Do not accept files bigger than 64KiB. */
	$size = filesize("{$g['conf_path']}/installer-settings.json");
	if ($size === false || $size >= 64 * 1024) {
		return (-1);
	}

	/* Read the settings from the Netgate Installer. */
	$settings = file_get_contents("{$g['conf_path']}/installer-settings.json");
	if ($settings === false) {
		return (0);
	}
	$json = json_decode($settings);
	if ($json === NULL ||
	    !isset($json->lan) ||
	    !isset($json->wan)) {
		return (0);
	}
	$lan = $json->lan;
	$wan = $json->wan;

	/* WAN setup. */
	if (isset($wan->if) &&
	    does_interface_exist($wan->if) &&
	    installer_setup_wan($wan) != 0) {
		return (-1);
	}

	/* LAN setup. */
	if (isset($lan->if) &&
	    does_interface_exist($lan->if)) {
		if (installer_setup_lan($lan) != 0) {
			return (-1);
		}
	} else {
		installer_disable_lan();
	}

	/* Remove the settings file. */
	unlink("{$g['conf_path']}/installer-settings.json");

	/* Let the system know that the interface assignment is done. */
	touch("{$g['conf_path']}/assign_complete");

	write_config(gettext("Network settings imported from the Netgate Installer"));

	return (0);
}

function check_for_alternate_interfaces() {
	// If the WAN and/or LAN devices in the factory default config do not exist,
	// then look for alternate devices.
	// This lets many systems boot a factory default config without being
	// forced to do interface assignment on the console.

	$specplatform = system_identify_specific_platform();
	$default_device = array();

	// If we recognise the platform, then specify the devices directly.
	$if_config = config_get_path('interfaces', []);
	switch ($specplatform['name']) {
		case '5100':
			$if_config['wan']['if'] = 'igb0';
			$if_config['lan']['if'] = 'igb1';
			$if_config['opt1'] = array(
				'enable' => true,
				'if' => 'ix0',
				'descr' => 'OPT1'
			);
			$if_config['opt2'] = array(
				'enable' => true,
				'if' => 'ix1',
				'descr' => 'OPT2'
			);
			$if_config['opt3'] = array(
				'enable' => true,
				'if' => 'ix2',
				'descr' => 'OPT3'
			);
			$if_config['opt4'] = array(
				'enable' => true,
				'if' => 'ix3',
				'descr' => 'OPT4'
			);
			break;
		case 'alix':
			$default_device['wan'] = "vr1";
			$default_device['lan'] = "vr0";
			break;
		case 'APU':
			$default_device['wan'] = "re1";
			$default_device['lan'] = "re2";
			break;
		case 'Turbot Dual-E':
			$if_config['wan']['if'] = 'igb0';
			$if_config['lan']['if'] = 'igb1';
			break;
		case 'C2758':
			$if_config['wan']['if'] = 'igb0';
			$if_config['lan']['if'] = 'igb1';
			$if_config['opt1'] = array(
				'if' => 'igb2',
				'descr' => 'OPT1'
			);
			$if_config['opt2'] = array(
				'if' => 'igb3',
				'descr' => 'OPT2'
			);
			break;
		case 'RCC-VE':
		case 'SG-2220':
			/* SG-4860 or SG-8860 */
			if (does_interface_exist('igb4')) {
				$if_config['wan']['if'] = 'igb1';
				$if_config['lan']['if'] = 'igb0';
			} else {
				$if_config['wan']['if'] = 'igb0';
				$if_config['lan']['if'] = 'igb1';
			}
			/* It has 4 ports */
			if (does_interface_exist('igb3')) {
				$if_config['opt1'] = array(
					'if' => 'igb2',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'if' => 'igb3',
					'descr' => 'OPT2'
				);
			}
			/* It has 6 ports */
			if (does_interface_exist('igb5')) {
				$if_config['opt3'] = array(
					'if' => 'igb4',
					'descr' => 'OPT3'
				);
				$if_config['opt4'] = array(
					'if' => 'igb5',
					'descr' => 'OPT4'
				);
			}
			break;
		case '1537':
			if (does_interface_exist('cxl0')) {
				/* It has 10G SFP+ addon */
				$if_config['wan']['if'] = 'cxl0';
				$if_config['lan']['if'] = 'cxl1';
				$if_config['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT2'
				);
				$if_config['opt3'] = array(
					'if' => 'igb1',
					'descr' => 'OPT3'
				);
				$if_config['opt4'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT4'
				);
			} elseif (does_interface_exist('igb4')) {
				/* It has 4 port ethernet addon */
				$if_config['wan']['if'] = 'igb4';
				$if_config['lan']['if'] = 'igb5';
				$if_config['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
				$if_config['opt3'] = array(
					'if' => 'igb3',
					'descr' => 'OPT3'
				);
				$if_config['opt4'] = array(
					'if' => 'igb2',
					'descr' => 'OPT4'
				);
				$if_config['opt5'] = array(
					'if' => 'igb1',
					'descr' => 'OPT5'
				);
				$if_config['opt6'] = array(
					'if' => 'igb0',
					'descr' => 'OPT6'
				);
			} else {
				$if_config['wan']['if'] = 'igb0';
				$if_config['lan']['if'] = 'igb1';
				$if_config['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
			}
			break;
		case '1540':
		case '1541':
			if (does_interface_exist('igb2')) {
				/* It has 4 port Intel 1Gb expansion card */
				$if_config['wan']['if'] = 'igb4';
				$if_config['lan']['if'] = 'igb5';
				$if_config['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
				$if_config['opt3'] = array(
					'if' => 'igb3',
					'descr' => 'OPT3'
				);
				$if_config['opt4'] = array(
					'if' => 'igb2',
					'descr' => 'OPT4'
				);
				$if_config['opt5'] = array(
					'if' => 'igb1',
					'descr' => 'OPT5'
				);
				$if_config['opt6'] = array(
					'if' => 'igb0',
					'descr' => 'OPT6'
				);
			} elseif (does_interface_exist('cxl0')) {
				/* It has 2 port Chelsio 10Gb expansion card */
				$if_config['wan']['if'] = 'cxl0';
				$if_config['lan']['if'] = 'cxl1';
				$if_config['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT2'
				);
				$if_config['opt3'] = array(
					'if' => 'igb1',
					'descr' => 'OPT3'
				);
				$if_config['opt4'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT4'
				);
			} else {
				$if_config['wan']['if'] = 'ix0';
				$if_config['lan']['if'] = 'ix1';
				$if_config['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'if' => 'igb1',
					'descr' => 'OPT2'
				);
			}
			break;
		case 'RCC':
			if (does_interface_exist('igb7')) {
				// has quad port expansion card
				$if_config['opt5'] = array(
					'if' => 'igb0',
					'descr' => 'OPT5'
				);
				$if_config['opt6'] = array(
					'if' => 'igb1',
					'descr' => 'OPT6'
				);
				$if_config['opt7'] = array(
					'if' => 'igb2',
					'descr' => 'OPT7'
				);
				$if_config['opt8'] = array(
					'if' => 'igb3',
					'descr' => 'OPT8'
				);
				$if_config['wan']['if'] = 'igb4';
				$if_config['lan']['if'] = 'igb6';
				$if_config['opt1'] = array(
					'if' => 'igb5',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'if' => 'igb7',
					'descr' => 'OPT2'
				);
			} else {
				$if_config['wan']['if'] = 'igb0';
				$if_config['lan']['if'] = 'igb2';
				$if_config['opt1'] = array(
					'if' => 'igb1',
					'descr' => 'OPT1'
				);
				$if_config['opt2'] = array(
					'if' => 'igb3',
					'descr' => 'OPT2'
				);
			}
			$if_config['opt3'] = array(
				'enable' => true,
				'if' => 'ix0',
				'descr' => 'OPT3'
			);
			$if_config['opt4'] = array(
				'enable' => true,
				'if' => 'ix1',
				'descr' => 'OPT4'
			);
			break;
		default:
			$default_device['wan'] = "";
			$default_device['lan'] = "";
			break;
	}
	config_set_path('interfaces', $if_config);

	// Other common device names can be put here and will be looked for
	// if the system was not one of the known platforms.
	$other_devices_arr['wan'] = array("vr1", "re1", "igb0", "em0");
	$other_devices_arr['lan'] = array("vr0", "re2", "igb1", "em1");
	$interface_assignment_changed = false;

	foreach ($other_devices_arr as $ifname => $other_devices) {
		$if_config = config_get_path("interfaces/{$ifname}/if");
		if (!does_interface_exist($if_config)) {
			if (does_interface_exist($default_device[$ifname])) {
				$if_config = $default_device[$ifname];
				$interface_assignment_changed = true;
			} else {
				foreach ($other_devices as $other_device) {
					if (does_interface_exist($other_device)) {
						$if_config = $other_device;
						$interface_assignment_changed = true;
						break;
					}
				}
			}
			config_set_path("interfaces/{$ifname}/if", $if_config);
		}
	}

	if ($interface_assignment_changed) {
		write_config("Factory default boot detected WAN " . config_get_path('interfaces/wan/if') . " and LAN " . config_get_path('interfaces/lan/if'));
	}
}

?>
