<?php
/*
 * interfaces.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2004 Manuel Kasper <mk@neon1.net>.
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

/* include all configuration functions */
require_once("globals.inc");
require_once("util.inc");
require_once("gwlb.inc");
require_once("ipsec.inc");
require_once("wg.inc");

function interfaces_bring_up($interface) {
	if (!$interface) {
		log_error(gettext("interfaces_bring_up() was called but no variable defined."));
		log_error("Backtrace: " . debug_backtrace());
		return;
	}
	pfSense_interface_flags($interface, IFF_UP);
}

/*
 * Validate comma-separated list of IPv4 addresses
 */
function validate_ipv4_list($value) {
	$value = trim($value);

	if (empty($value)) {
		return false;
	}

	$list = explode(',', $value);

	foreach ($list as $ip) {
		if (!is_ipaddrv4($ip)) {
			return false;
		}
	}

	return true;
}

/*
 * Return the interface array
 */
function get_interface_arr($flush = false) {
	global $interface_arr_cache;

	/* If the cache doesn't exist, build it */
	if (!isset($interface_arr_cache) or $flush) {
		$interface_arr_cache = pfSense_interface_listget();
	}

	return $interface_arr_cache;
}

/*
 * does_interface_exist($interface): return true or false if a interface is
 * detected.
 */
function does_interface_exist($interface, $flush = false) {
	global $config;

	if (!$interface) {
		return false;
	}

	$ints = get_interface_arr($flush);
	if (in_array($interface, $ints)) {
		return true;
	} else {
		return false;
	}
}

/*
 * does_vip_exist($vip): return true or false if a vip is
 * configured.
 */
function does_vip_exist($vip) {
	global $config;

	if (!$vip) {
		return false;
	}


	switch ($vip['mode']) {
		case "carp":
		case "ipalias":
			/* XXX: Make proper checks? */
			$realif = get_real_interface($vip['interface']);
			if (!does_interface_exist($realif)) {
				return false;
			}
			break;
		case "proxyarp":
			/* XXX: Implement this */
		default:
			return false;
	}

	$ifacedata = pfSense_getall_interface_addresses($realif);

	if (is_subnetv6("{$vip['subnet']}/{$vip['subnet_bits']}")) {
		$comparevip = text_to_compressed_ip6("{$vip['subnet']}/{$vip['subnet_bits']}");
	} else {
		$comparevip = "{$vip['subnet']}/{$vip['subnet_bits']}";
	}

	foreach ($ifacedata as $vipips) {
		if ($vipips == $comparevip) {
			return true;
		}
	}

	return false;
}

function interface_netgraph_needed($interface = "wan") {
	global $config;

	$found = false;
	if (!empty($config['l2tp']) &&
	    $config['l2tp']['mode'] == "server") {
		$found = true;
	}
	if ($found == false && is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] != "server") {
				continue;
			}
			if ($pppoe['interface'] == $interface) {
				$found = true;
				break;
			}
		}
	}
	if ($found == false) {
		$found = interface_isppp_type($interface);
	}

	if ($found == false) {
		$realif = get_real_interface($interface);
		init_config_arr(array('ppps', 'ppp'));
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			$ports = explode(',', $ppp['ports']);
			foreach ($ports as $pid => $port) {
				$port = get_real_interface($port);
				if ($realif == $port) {
					$found = true;
					break;
				}
				/* Find the parent interfaces of the vlans in the MLPPP configs
				* there should be only one element in the array here
				* -- this could be better . . . */
				$parent_if = get_parent_interface($port);
				if ($realif == $parent_if[0]) {
					$found = true;
					break;
				}
			}
		}
	}

	if ($found == false) {
		$realif = get_real_interface($interface);
		pfSense_ngctl_detach("{$realif}:", $realif);
	}
	/* NOTE: We make sure for this on interface_ppps_configure()
	 *	no need to do it here again.
	 *	else
	 *		pfSense_ngctl_attach(".", $realif);
	 */
}

function interfaces_loopback_configure() {
	global $g;

	if (platform_booting()) {
		echo gettext("Configuring loopback interface...");
	}
	pfSense_interface_setaddress("lo0", "127.0.0.1");
	interfaces_bring_up("lo0");
	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
	return 0;
}

function vlan_valid_tag($tag = NULL) {

	if ($tag == NULL || empty($tag) ||
	    !is_numericint($tag) || intval($tag) < 1 || intval($tag) > 4094) {
		return (false);
	}
	return (true);
}

function qinq_inuse($qinq = NULL, $inqtag = NULL) {
        global $config;

	if ($qinq == NULL || $inqtag == NULL ||
	    !is_array($qinq) || !vlan_valid_tag($inqtag)) {
		return (false);
	}

        $iflist = get_configured_interface_list(true);
        foreach ($iflist as $if) {
                if ($config['interfaces'][$if]['if'] == qinq_interface($qinq, $inqtag)) {
                        return (true);
                }
        }

        return (false);
}

function qinq_interface($qinq = NULL, $inqtag = NULL) {

	if ($qinq == NULL || $inqtag == NULL || !is_array($qinq) ||
	    !isset($qinq['if']) || !isset($qinq['tag']) ||
	    !vlan_valid_tag($qinq['tag']) || !vlan_valid_tag($inqtag)) {
		return (NULL);
	}
	return ("{$qinq['if']}.{$qinq['tag']}.{$inqtag}");
}

function interface_is_qinq($if = NULL) {
	global $config;

	if ($if == NULL || empty($if) || !is_array($config['qinqs']['qinqentry'])) {
		return (NULL);
	}

	/* Check basic format. */
	list($qinqif, $vlantag, $inqtag) = explode(".", $if);
	if (empty($qinqif) || empty($vlantag) || empty($inqtag) ||
	    !vlan_valid_tag($vlantag) || !vlan_valid_tag($inqtag)) {
		return (NULL);
	}

	foreach ($config['qinqs']['qinqentry'] as $qinqidx => $qinq) {
		if ("{$qinqif}.{$vlantag}" != $qinq['vlanif']) {
			continue;
		}
		if (empty($qinq['members'])) {
			continue;
		}
		foreach (explode(" ", $qinq['members']) as $tag) {
			if ($if == qinq_interface($qinq, $tag)) {
				return ($qinq);
			}
		}
	}

	return (NULL);
}

function vlan_inuse($vlan) {
	global $config;

	if ($vlan == NULL || !is_array($vlan)) {
		return (false);
	}

	$iflist = get_configured_interface_list(true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $vlan['vlanif']) {
			return (true);
		}
	}

	return (false);
}

function interface_is_vlan($if = NULL) {
	global $config;

	if ($if == NULL || empty($if) || is_array($if)) {
		return (NULL);
	}

	/* Check basic format. */
	list($vlanif, $vlantag) = explode(".", $if);
	if (empty($vlanif) || empty($vlantag) || !vlan_valid_tag($vlantag)) {
		return (NULL);
	}

	/* Find the VLAN interface. */
	if (isset($config['vlans']['vlan']) && is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlanidx => $vlan) {
			if ($if == $vlan['vlanif']) {
				return ($vlan);
			}
		}
	}

	/* Check for the first level tag in QinQ interfaces. */
	if (isset($config['qinqs']['qinqentry']) && is_array($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinqidx => $qinq) {
			if ($if == $qinq['vlanif']) {
				return ($qinq);
			}
		}
	}

	return (NULL);
}

function vlan_interface($vlan = NULL) {
	if ($vlan == NULL || !is_array($vlan) || !isset($vlan['if']) ||
	    !isset($vlan['tag']) || !vlan_valid_tag($vlan['tag'])) {
		return (NULL);
	}
	return ("{$vlan['if']}.{$vlan['tag']}");
}

function interface_set_macaddr($interface, $mac_addr) {
	if (empty($mac_addr) || !is_macaddr($mac_addr)) {
		return;
	}

	$current_mac = get_interface_mac($interface);

	/*
	 * Don't try to reapply the MAC if it's already applied.
	 * When ifconfig link is used, it cycles the interface down/up, which
	 * triggers the interface config again, which attempts to apply the
	 * MAC again, which cycles the link again...
	 */
	if ($mac_addr != $current_mac) {
		mwexec("/sbin/ifconfig " . escapeshellarg($interface) .
		    " link " . escapeshellarg($mac_addr));
	}
}

function interface_is_parent($if, $parent_check) {

	$parent_array = get_parent_interface($if);
	if (count($parent_array) > 1) {
		$parent = $parent_array;
	} else {
		$parent = $parent_array[0];
	}
	if (is_array($parent)) {
		foreach ($parent as $parentif) {
			if (strcasecmp($parent_check, $parentif) == 0) {
				return (TRUE);
			}
		}
	} else {
		$realif = get_real_interface($parent);
		if (strcasecmp($parent_check, $realif) == 0) {
			return (TRUE);
		}
	}
	return (FALSE);
}

function interface_has_clones($if) {
	global $config;

	/* Check LAGGs. */
	if (isset($config['laggs']) && isset($config['laggs']['lagg']) &&
	    is_array($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as $lagg) {
			if (interface_is_parent($lagg['laggif'], $if)) {
				return (TRUE);
			}
		}
	}
	/* Check VLANs. */
	if (isset($config['vlans']) && isset($config['vlans']['vlan']) &&
	    is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (interface_is_parent($vlan['if'], $if)) {
				return (TRUE);
			}
		}
	}
	/* Check bridges. */
	if (isset($config['bridges']) && isset($config['bridges']['bridged']) &&
	    is_array($config['bridges']['bridged'])) {
		foreach ($config['bridges']['bridged'] as $bridge) {
			$members = explode(',', $bridge['members']);
			foreach ($members as $member) {
				if (interface_is_parent($member, $if)) {
					return (TRUE);
				}
			}
		}
	}

	return (FALSE);
}

function interfaces_vlan_configure($parentif = "") {
	global $config, $g;
	$dhcp6c_list = array();

	if (platform_booting()) {
		echo gettext("Configuring VLAN interfaces...");
	}
	if (is_array($config['vlans']['vlan']) &&
	    count($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (empty($vlan['vlanif'])) {
				$vlan['vlanif'] = vlan_interface($vlan);
			}
			if (!empty($parentif) && ($parentif != $vlan['if'])) {
				continue;
			}
			/* configure dhcp6 enabled VLAN interfaces later
			 * see https://redmine.pfsense.org/issues/3965 */
			$if = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
			if (!empty($config['interfaces'][$if]['ipaddrv6']) &&
			    $config['interfaces'][$if]['ipaddrv6'] == "dhcp6") {
				$dhcp6c_list[$if] = $vlan;
				continue;
			}

			/* XXX: Maybe we should report any errors?! */
			interface_vlan_configure($vlan, false);
		}
		foreach ($dhcp6c_list as $if => $vlan) {
			interface_vlan_configure($vlan, false);
		}
	}

	/* Invalidate cache */
	get_interface_arr(true);

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function interface_vlan_configure(&$vlan, $flush = true) {
	global $config, $g;

	if (!is_array($vlan)) {
		log_error(gettext("VLAN: called with wrong options. Problems with config!"));
		return(NULL);
	}
	$if = $vlan['if'];
	if (empty($if)) {
		log_error(gettext("interface_vlan_configure called with if undefined."));
		return(NULL);
	}

	$vlanif = empty($vlan['vlanif']) ? vlan_interface($vlan) : $vlan['vlanif'];
	if ($vlanif == NULL) {
		log_error(gettext("vlan_interface called with if undefined var."));
		return(NULL);
	}
	$tag = $vlan['tag'];
	$pcp  = empty($vlan['pcp']) ? 0 : $vlan['pcp'];	/* Apply "Best Effort" if not set */

	/* make sure the parent interface is up */
	interfaces_bring_up($if);
	/* Since we are going to add vlan(4) try to enable all that hardware supports. */
	pfSense_interface_capabilities($if, IFCAP_VLAN_HWTAGGING|IFCAP_VLAN_MTU|IFCAP_VLAN_HWFILTER);

	if (!empty($vlanif) && does_interface_exist($vlanif)) {
		pfSense_interface_destroy($vlanif);
	}

	$tmpvlanif = pfSense_interface_create2("vlan");
	pfSense_interface_rename($tmpvlanif, $vlanif);
	pfSense_ngctl_name("{$tmpvlanif}:", $vlanif);

	pfSense_vlan_create($vlanif, $if, $tag, $pcp);

	interfaces_bring_up($vlanif);

	/* invalidate interface cache */
	if ($flush) {
		get_interface_arr(true);
	}

	/* configure interface if assigned */
	$assignedif = convert_real_interface_to_friendly_interface_name($vlanif);
	if ($assignedif) {
		if (isset($config['interfaces'][$assignedif]['enable'])) {
			interface_configure($assignedif, true);
		}
	}

	/* XXX: ermal -- for now leave it here at the moment it does not hurt. */
	interfaces_bring_up($if);

	if (interface_vlan_mtu_configured($vlanif)) {
		set_interface_mtu($vlanif, interface_vlan_mtu_configured($vlanif));
	}

	return $vlanif;
}

/*
 * reconfigure VLAN childs interfaces after MTU changes, see
 * https://redmine.pfsense.org/issues/11035
 */
function interfaces_vlan_configure_mtu($parentif = "") {
	global $config;

	init_config_arr(array('ppps', 'ppp'));
	if (!is_array($config['vlans']['vlan']) ||
	    !count($config['vlans']['vlan'])) {
		return;
	}

	foreach ($config['vlans']['vlan'] as $vlan) {
		if ($parentif != $vlan['if']) {
			continue;
		}
		if (interface_vlan_mtu_configured($vlan['vlanif'])) {
		       set_interface_mtu($vlan['vlanif'],
			   interface_vlan_mtu_configured($vlan['vlanif']));
		}
		if (empty($config['ppps']['ppp'])) {
			continue;
		}
		// PPP interfaces must be restarted to adjust MTU changes
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			$ports = explode(',', $ppp['ports']);
			foreach ($ports as $pid => $port) {
				if ($port != $vlan['vlanif']) {
					continue;
				}
				$confif = convert_real_interface_to_friendly_interface_name($ppp['if']);
				interface_configure($confif);
			}
		}
	}
}

function interface_qinq_configure(&$qinq, $fd = NULL, $flush = true) {
	global $config, $g;

	if (!is_array($qinq)) {
		log_error(sprintf(gettext("QinQ compat VLAN: called with wrong options. Problems with config!%s"), "\n"));
		return;
	}

	$qinqif = $qinq['if'];
	$tag = $qinq['tag'];
	if (empty($qinqif)) {
		log_error(sprintf(gettext("interface_qinq_configure called with if undefined.%s"), "\n"));
		return;
	}

	if (!does_interface_exist($qinqif)) {
		log_error(sprintf(gettext("interface_qinq_configure called with invalid if.%s"), "\n"));
		return;
	}

	$vlanif = interface_vlan_configure($qinq);
	if ($vlanif == NULL || $vlanif != $qinq['vlanif']) {
		log_error(gettext("interface_qinq_configure cannot create VLAN interface"));
		return;
	}

	if ($fd == NULL) {
		$exec = true;
		$fd = fopen("{$g['tmp_path']}/netgraphcmd", "w");
	} else {
		$exec = false;
	}
	/* make sure the parent is converted to ng_vlan(4) and is up */
	interfaces_bring_up($qinqif);

	pfSense_ngctl_attach(".", $qinqif);
	$ngif = str_replace(".", "_", $vlanif);
	if (!empty($vlanif) && does_interface_exist($vlanif)) {
		exec("/usr/sbin/ngctl shutdown {$ngif}qinq: > /dev/null 2>&1");
		exec("/usr/sbin/ngctl msg {$ngif}qinq: gettable > /dev/null 2>&1", $result);
		if (empty($result)) {
			fwrite($fd, "mkpeer {$ngif}: vlan lower downstream\n");
			fwrite($fd, "name {$ngif}:lower {$ngif}qinq\n");
			fwrite($fd, "connect {$ngif}: {$ngif}qinq: upper nomatch\n");
		}
	} else {
		fwrite($fd, "mkpeer {$ngif}: vlan lower downstream\n");
		fwrite($fd, "name {$ngif}:lower {$ngif}qinq\n");
		fwrite($fd, "connect {$ngif}: {$ngif}qinq: upper nomatch\n");
	}

	/* invalidate interface cache */
	if ($flush) {
		get_interface_arr(true);
	}

	if (interface_is_vlan($qinqif) == NULL) {
		mwexec("/sbin/ifconfig {$qinqif} promisc\n");
	}

	$macaddr = get_interface_mac($qinqif);
	if (!empty($qinq['members'])) {
		$qinqcmdbuf = "";
		$members = explode(" ", $qinq['members']);
		foreach ($members as $qtag) {
			$qinq2 = array();
			$qinq2['tag'] = $qtag;
			$qinq2['if'] = $vlanif;
			interface_qinq2_configure($qinq2, $qinqcmdbuf, $macaddr,
			    false);
			unset($qinq2);
		}
		if (strlen($qinqcmdbuf) > 0) {
			fwrite($fd, $qinqcmdbuf);
		}
	}
	if ($exec == true) {
		fclose($fd);
		mwexec("/usr/sbin/ngctl -f {$g['tmp_path']}/netgraphcmd > /dev/null 2>&1");
	}

	interfaces_bring_up($qinqif);
	if (!empty($qinq['members'])) {
		$members = explode(" ", $qinq['members']);
		foreach ($members as $qtag) {
			interfaces_bring_up(qinq_interface($qinq, $qtag));
		}
	}

	return $vlanif;
}

function interfaces_qinq_configure() {
	global $config, $g;
	if (platform_booting()) {
		echo gettext("Configuring QinQ interfaces...");
	}
	if (is_array($config['qinqs']['qinqentry']) &&
	    count($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			/* XXX: Maybe we should report any errors?! */
			interface_qinq_configure($qinq, NULL, false);
		}
	}

	/* Invalidate cache */
	get_interface_arr(true);

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function interface_qinq2_configure(&$qinq, &$cmdbuf, $macaddr, $flush = true) {
	global $config, $g;

	if (!is_array($qinq)) {
		log_error(sprintf(gettext("QinQ compat VLAN: called with wrong options. Problems with config!%s"), "\n"));
		return;
	}

	$if = $qinq['if'];
	if (empty($if)) {
		log_error(sprintf(gettext("interface_qinq2_configure called with if undefined.%s"), "\n"));
		return;
	}
	$tag = $qinq['tag'];
	$vlanif = "{$if}.{$tag}";
	$ngif = str_replace(".", "_", $if);
	if (strlen($vlanif) > IF_NAMESIZE) {
		log_error(sprintf(gettext("interface_qinq2_configure interface name too big %s. (max. size: %d).%s"),
		    $vlanif, IF_NAMESIZE, "\n"));
		return;
	}

	exec("/usr/sbin/ngctl shutdown {$ngif}h{$tag}: > /dev/null 2>&1");
	$cmdbuf .= "mkpeer {$ngif}qinq: eiface {$ngif}{$tag} ether\n";
	$cmdbuf .= "name {$ngif}qinq:{$ngif}{$tag} {$ngif}h{$tag}\n";
	$cmdbuf .= "msg {$ngif}qinq: addfilter { vlan={$tag} hook=\"{$ngif}{$tag}\" }\n";
	$cmdbuf .= "msg {$ngif}h{$tag}: setifname \"{$vlanif}\"\n";
	$cmdbuf .= "msg {$ngif}h{$tag}: set {$macaddr}\n";

	/* invalidate interface cache */
	if ($flush) {
		get_interface_arr(true);
	}

	return $vlanif;
}

function interfaces_create_wireless_clones() {
	global $config, $g;

	if (platform_booting()) {
		echo gettext("Creating wireless clone interfaces...");
	}

	$iflist = get_configured_interface_list();

	foreach ($iflist as $if) {
		$realif = $config['interfaces'][$if]['if'];
		if (!is_interface_wireless($realif)) {
			continue;
		}
		interface_wireless_clone(interface_get_wireless_clone($realif),
		    $config['interfaces'][$if]);
	}

	if (isset($config['wireless']['clone']) &&
	    is_array($config['wireless']['clone']) &&
	    count($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as $clone) {
			if (empty($clone['cloneif'])) {
				continue;
			}
			if (does_interface_exist($clone['cloneif'])) {
				continue;
			}
			/* XXX: Maybe we should report any errors?! */
			interface_wireless_clone($clone['cloneif'], $clone);
		}
	}
	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}

}

function interfaces_bridge_configure($checkmember = 0, $realif = "") {
	global $config;

	if (!isset($config['bridges']['bridged']) ||
	    !is_array($config['bridges']['bridged']) ||
	    !count($config['bridges']['bridged'])) {
		return;
	}

	$i = 0;
	foreach ($config['bridges']['bridged'] as $bridge) {
		if (empty($bridge['bridgeif'])) {
			$bridge['bridgeif'] = "bridge{$i}";
		}
		if (!empty($realif) && ($realif != $bridge['bridgeif'])) {
			continue;
		}
		$ifname = false;
		foreach ($config['interfaces'] as $intname => $intpar) {
			if ($intpar['if'] == $bridge['bridgeif']) {
				$ifname = $intname;
				break;
			}
		}

		if ($ifname && ($config['interfaces'][$ifname]['ipaddrv6'] == "track6")) {
			if ($checkmember == 1) {
				continue;
			} else {
				$checkmember = 0;
			}
		} elseif (($checkmember == 2) && !$ifname) {
			continue;
		}

		/* XXX: Maybe we should report any errors?! */
		interface_bridge_configure($bridge, $checkmember, false);
		$i++;
	}

	/* Invalidate cache */
	get_interface_arr(true);
}

function interface_bridge_configure(&$bridge, $checkmember = 0, $flush = true) {
	global $config, $g;

	if (!is_array($bridge)) {
		return;
	}

	if (empty($bridge['members'])) {
		log_error(sprintf(gettext("No members found on %s"), $bridge['bridgeif']));
		return;
	}

	$members = explode(',', $bridge['members']);
	if (!count($members)) {
		return;
	}

	/* Calculate smaller mtu and enforce it */
	$smallermtu = 0;
	$foundgif = false;
	foreach ($members as $member) {
		$realif = get_real_interface($member);
		$mtu = get_interface_mtu($realif);
		if (substr($realif, 0, 3) == "gif") {
			$foundgif = true;
			if ($checkmember == 1) {
				return;
			}
			if ($mtu <= 1500) {
				continue;
			}
		}
		if ($smallermtu == 0 && !empty($mtu)) {
			$smallermtu = $mtu;
		} elseif (!empty($mtu) && $mtu < $smallermtu) {
			$smallermtu = $mtu;
		}
	}
	if ($foundgif == false && $checkmember == 2) {
		return;
	}

	/* Just in case anything is not working well */
	if ($smallermtu == 0) {
		$smallermtu = 1500;
	}

	if (!empty($bridge['bridgeif'])) {
		pfSense_interface_destroy($bridge['bridgeif']);
		pfSense_interface_create2($bridge['bridgeif']);
		$bridgeif = escapeshellarg($bridge['bridgeif']);
	} else {
		// if called directly, as interfaces_bridge_edit.php does, and bridgeif isn't set
		// normally set by interfaces_bridge_configure, but not upon creation of new bridge
		$bridgeif = pfSense_interface_create2("bridge");
		$bridge['bridgeif'] = $bridgeif;
	}

	$bridgemtu = interface_find_child_cfgmtu($bridge['bridgeif']);
	if ($bridgemtu > $smallermtu) {
		$smallermtu = $bridgemtu;
	}

	$checklist = get_configured_interface_list();

	/* Add interfaces to bridge */
	foreach ($members as $member) {
		if (empty($checklist[$member])) {
			continue;
		}
		$realif = get_real_interface($member);
		if (!$realif) {
			log_error(gettext("realif not defined in interfaces bridge - up"));
			continue;
		}
		/* make sure the parent interface is up */
		pfSense_interface_mtu($realif, $smallermtu);
		interfaces_bring_up($realif);
		enable_hardware_offloading($member);
		pfSense_bridge_add_member($bridge['bridgeif'], $realif);
	}

	if (isset($bridge['enablestp'])) {
		interface_bridge_configure_stp($bridge);
	}

	interface_bridge_configure_advanced($bridge);

	interface_bridge_configure_ip6linklocal($bridge);

	if ($flush) {
		get_interface_arr(true);
	}

	if ($bridge['bridgeif']) {
		interfaces_bring_up($bridge['bridgeif']);
	} else {
		log_error(gettext("bridgeif not defined -- could not bring interface up"));
	}
}

function interface_bridge_configure_stp($bridge) {
	if (isset($bridge['enablestp'])) {
		$bridgeif = trim($bridge['bridgeif']);
		/* configure spanning tree proto */
		mwexec("/sbin/ifconfig {$bridgeif} proto " . escapeshellarg($bridge['proto']));

		if (!empty($bridge['stp'])) {
			$stpifs = explode(',', $bridge['stp']);
			foreach ($stpifs as $stpif) {
				$realif = get_real_interface($stpif);
				mwexec("/sbin/ifconfig {$bridgeif} stp {$realif}");
			}
		}
		if (!empty($bridge['maxage'])) {
			mwexec("/sbin/ifconfig {$bridgeif} maxage " . escapeshellarg($bridge['maxage']));
		}
		if (!empty($bridge['fwdelay'])) {
			mwexec("/sbin/ifconfig {$bridgeif} fwddelay " . escapeshellarg($bridge['fwdelay']));
		}
		if (!empty($bridge['hellotime'])) {
			mwexec("/sbin/ifconfig {$bridgeif} hellotime " . escapeshellarg($bridge['hellotime']));
		}
		if (!empty($bridge['priority'])) {
			mwexec("/sbin/ifconfig {$bridgeif} priority " . escapeshellarg($bridge['priority']));
		}
		if (!empty($bridge['holdcnt'])) {
			mwexec("/sbin/ifconfig {$bridgeif} holdcnt " . escapeshellarg($bridge['holdcnt']));
		}
		if (!empty($bridge['ifpriority'])) {
			$pconfig = explode(",", $bridge['ifpriority']);
			$ifpriority = array();
			foreach ($pconfig as $cfg) {
				$embcfg = explode_assoc(":", $cfg);
				foreach ($embcfg as $key => $value) {
					$ifpriority[$key] = $value;
				}
			}
			foreach ($ifpriority as $key => $value) {
				$realif = get_real_interface($key);
				mwexec("/sbin/ifconfig ${bridgeif} ifpriority {$realif} " . escapeshellarg($value));
			}
		}
		if (!empty($bridge['ifpathcost'])) {
			$pconfig = explode(",", $bridge['ifpathcost']);
			$ifpathcost = array();
			foreach ($pconfig as $cfg) {
				$embcfg = explode_assoc(":", $cfg);
				foreach ($embcfg as $key => $value) {
					$ifpathcost[$key] = $value;
				}
			}
			foreach ($ifpathcost as $key => $value) {
				$realif = get_real_interface($key);
				mwexec("/sbin/ifconfig ${bridgeif} ifpathcost {$realif} " . escapeshellarg($value));
			}
		}
	}
}

function interface_bridge_configure_advanced($bridge) {
	$bridgeif = trim($bridge['bridgeif']);

	if ($bridge['maxaddr'] <> "") {
		mwexec("/sbin/ifconfig {$bridgeif} maxaddr " . escapeshellarg($bridge['maxaddr']));
	}
	if ($bridge['timeout'] <> "") {
		mwexec("/sbin/ifconfig {$bridgeif} timeout " . escapeshellarg($bridge['timeout']));
	}
	if (!empty($bridge['span'])) {
		$spanifs = explode(",", $bridge['span']);
		foreach ($spanifs as $spanif) {
			$realif = get_real_interface($spanif);
			mwexec("/sbin/ifconfig {$bridgeif} span {$realif}");
		}
	}
	if (!empty($bridge['edge'])) {
		$edgeifs = explode(',', $bridge['edge']);
		foreach ($edgeifs as $edgeif) {
			$realif = get_real_interface($edgeif);
			mwexec("/sbin/ifconfig {$bridgeif} edge {$realif}");
		}
	}
	if (!empty($bridge['autoedge'])) {
		$edgeifs = explode(',', $bridge['autoedge']);
		foreach ($edgeifs as $edgeif) {
			$realif = get_real_interface($edgeif);
			mwexec("/sbin/ifconfig {$bridgeif} -autoedge {$realif}");
		}
	}
	if (!empty($bridge['ptp'])) {
		$ptpifs = explode(',', $bridge['ptp']);
		foreach ($ptpifs as $ptpif) {
			$realif = get_real_interface($ptpif);
			mwexec("/sbin/ifconfig {$bridgeif} ptp {$realif}");
		}
	}
	if (!empty($bridge['autoptp'])) {
		$ptpifs = explode(',', $bridge['autoptp']);
		foreach ($ptpifs as $ptpif) {
			$realif = get_real_interface($ptpif);
			mwexec("/sbin/ifconfig {$bridgeif} -autoptp {$realif}");
		}
	}
	if (!empty($bridge['static'])) {
		$stickyifs = explode(',', $bridge['static']);
		foreach ($stickyifs as $stickyif) {
			$realif = get_real_interface($stickyif);
			mwexec("/sbin/ifconfig {$bridgeif} sticky {$realif}");
		}
	}
	if (!empty($bridge['private'])) {
		$privateifs = explode(',', $bridge['private']);
		foreach ($privateifs as $privateif) {
			$realif = get_real_interface($privateif);
			mwexec("/sbin/ifconfig {$bridgeif} private {$realif}");
		}
	}
}

function interface_bridge_configure_ip6linklocal($bridge) {
	$bridgeif = trim($bridge['bridgeif']);

	$members = explode(',', $bridge['members']);
	if (!count($members)) {
		return;
	}

	$auto_linklocal = isset($bridge['ip6linklocal']);
	$bridgeop = $auto_linklocal ? '' : '-';
	$memberop = $auto_linklocal ? '-' : '';

	mwexec("/usr/sbin/ndp -i {$bridgeif} -- {$bridgeop}auto_linklocal");
	foreach ($members as $member) {
		$realif = get_real_interface($member);
		mwexec("/usr/sbin/ndp -i {$realif} -- {$memberop}auto_linklocal");
	}
}

function interface_bridge_add_member($bridgeif, $interface, $flagsapplied = false) {
	global $config;

	if (!does_interface_exist($bridgeif) || !does_interface_exist($interface)) {
		return;
	}

	if ($flagsapplied == false) {
		$mtu = get_interface_mtu($bridgeif);
		$mtum = get_interface_mtu($interface);
		if ($mtu != $mtum && !(substr($interface, 0, 3) == "gif" && $mtu <= 1500)) {
			pfSense_interface_mtu($interface, $mtu);
		}

		hardware_offloading_applyflags($interface);
		interfaces_bring_up($interface);
	}

	pfSense_bridge_add_member($bridgeif, $interface);
	if (is_array($config['bridges']['bridged'])) {
		foreach ($config['bridges']['bridged'] as $bridge) {
			if ($bridgeif == $bridge['bridgeif']) {
				interface_bridge_configure_stp($bridge);
				interface_bridge_configure_advanced($bridge);
			}
		}
	}
}

function interfaces_lagg_configure($realif = "") {
	global $config, $g;
	if (platform_booting()) {
		echo gettext("Configuring LAGG interfaces...");
	}
	$i = 0;
	if (is_array($config['laggs']['lagg']) &&
	    count($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as $lagg) {
			if (empty($lagg['laggif'])) {
				$lagg['laggif'] = "lagg{$i}";
			}
			if (!empty($realif) && $realif != $lagg['laggif']) {
				continue;
			}
			/* XXX: Maybe we should report any errors?! */
			interface_lagg_configure($lagg, false);
			$i++;
		}

		/* Invalidate cache */
		get_interface_arr(true);
	}
	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function interface_lagg_configure($lagg, $flush = true) {
	global $config, $g;

	if (!is_array($lagg)) {
		return -1;
	}

	$members = explode(',', $lagg['members']);
	if (!count($members)) {
		return -1;
	}

	if (platform_booting() || !(empty($lagg['laggif']))) {
		pfSense_interface_destroy($lagg['laggif']);
		pfSense_interface_create2($lagg['laggif']);
		$laggif = $lagg['laggif'];
	} else {
		$laggif = pfSense_interface_create2("lagg");
	}

	/* Check if MTU was defined for this lagg interface */
	$lagg_mtu = interface_find_child_cfgmtu($laggif);
	if ($lagg_mtu == 0 &&
	    is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $tmpinterface) {
			if ($tmpinterface['if'] == $lagg['laggif'] &&
			    !empty($tmpinterface['mtu'])) {
				$lagg_mtu = $tmpinterface['mtu'];
				break;
			}
		}
	}

	/* Just in case anything is not working well */
	if ($lagg_mtu == 0) {
		$lagg_mtu = 1500;
	}

	// put failover master interface on top of list
	if (($lagg['proto'] == 'failover') && isset($lagg['failovermaster']) &&
	    ($lagg['failovermaster'] != 'auto')) {
		unset($members[array_search($lagg['failovermaster'], $members)]);
		$members = array_merge(array($lagg['failovermaster']), $members);
	}

	if (($lagg['proto'] == 'lacp') && isset($lagg['lacptimeout']) &&
	    ($lagg['lacptimeout'] != 'slow')) {
		$lacptimeout = 'lacp_fast_timeout';
	} else {
		$lacptimeout = '';
	}

	foreach ($members as $member) {
		if (!does_interface_exist($member)) {
			continue;
		}

		/* make sure the parent interface is up */
		pfSense_interface_mtu($member, $lagg_mtu);
		interfaces_bring_up($member);
		hardware_offloading_applyflags($member);

		// Ensure there are no nulls in these values. It upsets escapeshellarg()
		$laggif = str_replace("\0", "", $laggif);
		$member = str_replace("\0", "", $member);
		mwexec("/sbin/ifconfig " . escapeshellarg($laggif) . " laggport " . escapeshellarg($member));
	}

	mwexec("/sbin/ifconfig {$laggif} laggproto " . escapeshellarg($lagg['proto']) . " {$lacptimeout}");

	if ($flush) {
		get_interface_arr(true);
	}

	interfaces_bring_up($laggif);

	return $laggif;
}

/* NOTE: $grekey is not used but useful for passing this function to array_walk. */
function interface_gre_configure(&$gre, $grekey = "", $flush = true) {
	global $config, $g;

	if (!is_array($gre)) {
		return -1;
	}

	$realif = convert_friendly_interface_to_real_interface_name($gre['if']);
	if (!interface_is_vlan($realif)) {
		$realif = get_real_interface($gre['if']);
	}
	$realifip = get_interface_ip($gre['if']);
	$realifip6 = get_interface_ipv6($gre['if']);

	/* make sure the parent interface is up */
	interfaces_bring_up($realif);

	if (platform_booting() || !(empty($gre['greif']))) {
		pfSense_interface_destroy($gre['greif']);
		pfSense_interface_create2($gre['greif']);
		$greif = $gre['greif'];
	} else {
		$greif = pfSense_interface_create2("gre");
	}

	$tunnel_type = '';
	if ((!empty($gre['tunnel-local-addr'])) || (!empty($gre['tunnel-remote-addr']))) {
		$tunnel_type = 'v4';
	}
	if ((!empty($gre['tunnel-local-addr6'])) || (!empty($gre['tunnel-remote-addr6']))) {
		$tunnel_type .= 'v6';
	}

	/* Do not change the order here for more see gre(4) NOTES section. */
	if (is_ipaddrv6($gre['remote-addr'])) {
		mwexec("/sbin/ifconfig {$greif} inet6 tunnel {$realifip6} " . escapeshellarg($gre['remote-addr']));
	} else {
		mwexec("/sbin/ifconfig {$greif} tunnel {$realifip} " . escapeshellarg($gre['remote-addr']));
	}
	if (in_array($tunnel_type, array('v4', 'v4v6'))) {
		mwexec("/sbin/ifconfig {$greif} " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gre['tunnel-remote-net']));
	}
	if (in_array($tunnel_type, array('v6', 'v4v6'))) {
		/* XXX: The prefixlen argument for tunnels of ipv6 is useless since it needs to be 128 as enforced by kernel */
		mwexec("/sbin/ifconfig {$greif} inet6 " . escapeshellarg($gre['tunnel-local-addr6']) . " " . escapeshellarg($gre['tunnel-remote-addr6']) . " prefixlen 128");
	}

	$parentif = get_real_interface($gre['if']);
	if ($parentif) {
		interfaces_bring_up($parentif);
	} else {
		log_error(gettext("could not bring parentif up -- variable not defined -- interface_gre_configure()"));
	}

	if (isset($gre['link1'])) {
		if (in_array($tunnel_type, array('v4', 'v4v6'))) {
			mwexec("/sbin/route add " . escapeshellarg($gre['tunnel-remote-addr']) . "/" . escapeshellarg($gre['tunnel-remote-net']) . " " . escapeshellarg($gre['tunnel-local-addr']));
		}
		if (in_array($tunnel_type, array('v6', 'v4v6'))) {
			mwexec("/sbin/route -6 add " . escapeshellarg($gre['tunnel-remote-addr6']) . "/" . escapeshellarg($gre['tunnel-remote-net6']) . " " . escapeshellarg($gre['tunnel-local-addr6']));
		}
	}
	if (in_array($tunnel_type, array('v4', 'v4v6'))) {
		file_put_contents("{$g['tmp_path']}/{$greif}_router", $gre['tunnel-remote-addr']);
	}
	if (in_array($tunnel_type, array('v6', 'v4v6'))) {
		file_put_contents("{$g['tmp_path']}/{$greif}_routerv6", $gre['tunnel-remote-addr6']);
	}

	if ($flush) {
		get_interface_arr(true);
	}

	interfaces_bring_up($greif);

	return $greif;
}

function interface_is_type($if = NULL, $type) {
	global $config;
	switch ($type) {
		case "gre":
			$list = 'gres';
			$entry = 'gre';
			$entif = 'greif';
			break;
		case "gif":
			$list = 'gifs';
			$entry = 'gif';
			$entif = 'gifif';
			break;
		case "lagg":
			$list = 'laggs';
			$entry = 'lagg';
			$entif = 'laggif';
			break;
		case "wg":
			$list = 'wireguard';
			$entry = 'tunnel';
			$entif = 'name';
			break;
		default:
			break;
	}

	if (!isset($config[$list][$entry]) || !is_array($config[$list][$entry])) {
		return (NULL);
	}

	foreach ($config[$list][$entry] as $ent) {
		if ($ent[$entif] == $if) {
			return ($ent);
		}
	}
	return (NULL);
}

function is_greipsec($if) {
	global $config;

	if (ipsec_enabled() && is_array($config['gres']) &&
	    is_array($config['gres']['gre']) &&
	    is_array($config['ipsec']['phase2'])) {
		foreach ($config['gres']['gre'] as $gre) {
			foreach ($config['ipsec']['phase1'] as $ph1ent) {
				foreach ($config['ipsec']['phase2'] as $ph2ent) {
					if (($ph1ent['ikeid'] == $ph2ent['ikeid']) && ($ph2ent['mode'] == 'transport') &&
					    !isset($ph1ent['disabled']) && !isset($ph2ent['disabled']) &&
					    ($ph1ent['interface'] == $gre['if']) && ($gre['greif'] == $if) &&
					    ($ph1ent['remote-gateway'] == $gre['remote-addr'])) {
						    return true;
					}
				}
			}
		}
	}
	return false;
}

/* NOTE: $gifkey is not used but useful for passing this function to array_walk. */
function interface_gif_configure(&$gif, $gifkey = "", $flush = true) {
	global $config, $g;

	if (!is_array($gif)) {
		return -1;
	}

	$realif = convert_friendly_interface_to_real_interface_name($gif['if']);
	if (!interface_is_vlan($realif)) {
		$realif = get_real_interface($gif['if']);
	}
	$ipaddr = get_interface_ip($gif['if']);

	if (is_ipaddrv4($gif['remote-addr'])) {
		if (is_ipaddrv4($ipaddr)) {
			$realifip = $ipaddr;
		} else {
			$realifip = get_interface_ip($gif['if']);
		}
		$realifgw = get_interface_gateway($gif['if']);
	} elseif (is_ipaddrv6($gif['remote-addr'])) {
		if (is_ipaddrv6($ipaddr)) {
			$realifip = $ipaddr;
		} else {
			$realifip = get_interface_ipv6($gif['if']);
		}
		$realifgw = get_interface_gateway_v6($gif['if']);
	}
	/* make sure the parent interface is up */
	$parentif = get_real_interface($gif['if']);
	if ($parentif) {
		interfaces_bring_up($parentif);
	} else {
		log_error(gettext("could not bring parentif up -- variable not defined -- interface_gif_configure()"));
	}

	if (platform_booting() || !(empty($gif['gifif']))) {
		pfSense_interface_destroy($gif['gifif']);
		pfSense_interface_create2($gif['gifif']);
		$gifif = $gif['gifif'];
	} else {
		$gifif = pfSense_interface_create2("gif");
	}

	/* Do not change the order here for more see gif(4) NOTES section. */
	if (is_ipaddrv6($gif['remote-addr'])) {
		mwexec("/sbin/ifconfig {$gifif} inet6 tunnel {$realifip} " . escapeshellarg($gif['remote-addr']));
	} else {
		mwexec("/sbin/ifconfig {$gifif} tunnel {$realifip} " . escapeshellarg($gif['remote-addr']));
	}
	if ((is_ipaddrv6($gif['tunnel-local-addr'])) || (is_ipaddrv6($gif['tunnel-remote-addr']))) {
		/* XXX: The prefixlen argument for tunnels of ipv6 is useless since it needs to be 128 as enforced by kernel */
		//mwexec("/sbin/ifconfig {$gifif} inet6 " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " prefixlen /" . escapeshellarg($gif['tunnel-remote-net']));
		mwexec("/sbin/ifconfig {$gifif} inet6 " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " prefixlen 128");
	} else {
		mwexec("/sbin/ifconfig {$gifif} " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gif['tunnel-remote-net']));
	}
	if (isset($gif['link1'])) {
		pfSense_interface_flags($gifif, IFF_LINK1);
	}
	if (isset($gif['link2'])) {
		pfSense_interface_flags($gifif, IFF_LINK2);
	}
	if ($gifif) {
		interfaces_bring_up($gifif);
		$gifmtu = "";
		$currentgifmtu = get_interface_mtu($gifif);
		foreach ($config['interfaces'] as $tmpinterface) {
			if ($tmpinterface['if'] == $gifif) {
				if (isset($tmpinterface['mtu']) && is_numericint($tmpinterface['mtu'])) {
					$gifmtu = $tmpinterface['mtu'];
				}
			}
		}
		if (is_numericint($gifmtu)) {
			if ($gifmtu != $currentgifmtu) {
				mwexec("/sbin/ifconfig {$gifif} mtu {$gifmtu}");
			}
		}
	} else {
		log_error(gettext("could not bring gifif up -- variable not defined"));
	}

	if (!platform_booting()) {
		$iflist = get_configured_interface_list();
		foreach ($iflist as $ifname) {
			if ($config['interfaces'][$ifname]['if'] == $gifif) {
				if (get_interface_gateway($ifname)) {
					system_routing_configure($ifname);
					break;
				}
				if (get_interface_gateway_v6($ifname)) {
					system_routing_configure($ifname);
					break;
				}
			}
		}
	}


	if (is_ipaddrv4($gif['tunnel-remote-addr'])) {
		file_put_contents("{$g['tmp_path']}/{$gifif}_router",
		    $gif['tunnel-remote-addr']);
	} elseif (is_ipaddrv6($gif['tunnel-remote-addr'])) {
		file_put_contents("{$g['tmp_path']}/{$gifif}_routerv6",
		    $gif['tunnel-remote-addr']);
	}

	route_add_or_change($gif['remote-addr'], $realifgw);

	if ($flush) {
		get_interface_arr(true);
	}

	interfaces_bring_up($gifif);

	return $gifif;
}

/*
 * $ipsecifnum = get_ipsecifnum($ikeid, $idx);
 * locates and returns an ipsecifnum in the config.
 */
function get_ipsecifnum($ikeid, $idx) {
	global $config;

	init_config_arr(array('ipsec', 'vtimaps', 'item'));
	foreach ($config['ipsec']['vtimaps']['item'] as $vtimap) {
		if (($vtimap['reqid'] == $ikeid) &&
		    ($vtimap['index'] == $idx)) {
			return $vtimap['ifnum'];
		}
	}

	return false;
}

function ipsec_create_vtimap($ikeid, $idx) {
	global $config;

	if ((($ikeid < 33) && ($idx < 10)) || (($ikeid < 10) && ($idx < 100))) {
		$oldformat = "{$ikeid}00{$idx}";
		return array(
			"reqid" => $ikeid,
			"index" => $idx,
			"ifnum" => $oldformat
		);
	}

	init_config_arr(array('ipsec', 'vtimaps', 'item'));

	if (count($config['ipsec']['vtimaps']['item']) == 0) {
		return array(
			"reqid" => $ikeid,
			"index" => $idx,
			"ifnum" => 1
		);
	}

	$assigned = array_column($config['ipsec']['vtimaps']['item'], 'ifnum');
	asort($assigned, SORT_NUMERIC);
	$new = 1;
	foreach($assigned as $ipsecifnum) {
		if ($ipsecifnum != $new) {
			return array(
				"reqid" => $ikeid,
				"index" => $idx,
				"ifnum" => $new
			);
		}
		if ($new <= 32767) {
			$new++;
		} else {
			log_error(gettext("All 32767 ipsec interface numbers " .
			    "have been assigned!"));
			return(NULL);
		}
	}
}

function ipsec_del_vtimap($phase2) {
	global $config;

	init_config_arr(array('ipsec', 'vtimaps', 'item'));

	if (count($config['ipsec']['vtimaps']['item']) == 0) {
		return;
	}

	$a_vtimaps = &$config['ipsec']['vtimaps']['item'];
	$phase1 = ipsec_get_phase1($phase2['ikeid']);
	$last = '';
	foreach ($a_vtimaps as $id => $vtimap) {
		if ($vtimap['reqid'] == $phase1['ikeid']) {
			$last = $id;
		}
	}
	if (!is_numeric($last)) {
		return false;
	}

	$vtisubnet_spec = ipsec_vti($phase1, true);
	if (($phase1['iketype'] == 'ikev1') || isset($phase1['splitconn']) ||
	    (count($vtisubnet_spec) == 1)) {
		unset($a_vtimaps[$last]);
		return true;
	}
}

function interfaces_tunnel_configure($checkparent = 0, $realif = "", $type = "") {
	global $config;

	if (!in_array($type, array('gre', 'gif'))) {
		return;
	}

	if (!is_array($config["{$type}s"][$type]) ||
	    !count($config["{$type}s"][$type])) {
		return;
	}

	foreach ($config["{$type}s"][$type] as $i => $tunnel) {
		if (empty($tunnel["{$type}if"])) {
			$tunnel["{$type}if"] = $type . $i;
		}
		if (!empty($realif) && $realif != $tunnel["{$type}if"]) {
			continue;
		}

		if ($checkparent == 1) {
			if (substr($tunnel['if'], 0, 4) == '_vip') {
				continue;
			}
			if (substr($tunnel['if'], 0, 5) == '_lloc') {
				continue;
			}
			if (!empty($config['interfaces'][$tunnel['if']]) &&
			    $config['interfaces'][$tunnel['if']]['ipaddrv6'] ==
			    "track6") {
				continue;
			}
		} elseif ($checkparent == 2) {
			if ((substr($tunnel['if'], 0, 4) != '_vip' &&
			    substr($tunnel['if'], 0, 5) != '_lloc') &&
			    (empty($config['interfaces'][$tunnel['if']]) ||
			    $config['interfaces'][$tunnel['if']]['ipaddrv6'] !=
			    "track6")) {
				continue;
			}
		}
		if ($type == 'gif') {
			interface_gif_configure($tunnel, "", false);
		} elseif ($type == 'gre') {
			interface_gre_configure($tunnel, "", false);
		}
	}

	/* Invalidate cache */
	get_interface_arr(true);
}

/* Build a list of IPsec interfaces */
function interface_ipsec_vti_list_p1($ph1ent) {
	global $config;
	$iface_list = array();

	if (empty($ph1ent) || !is_array($ph1ent) || !is_array($config['ipsec']['phase2'])) {
		return $iface_list;
	}

	$vtisubnet_spec = ipsec_vti($ph1ent, true);
	if ((!$vtisubnet_spec || !is_array($vtisubnet_spec))) {
		return $iface_list;
	}

	/* With IKEv1 or v2+Split, each P2 gets its own conn/reqid/interface */
	if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
		foreach ($vtisubnet_spec as $idx => $vtisub) {
			$iface_list["ipsec".get_ipsecifnum($ph1ent['ikeid'], $idx)] = gettext("IPsec VTI") . ": ".htmlspecialchars($ph1ent['descr'] . " / " . $vtisub['descr']);
		}
	} else {
		/* For IKEv2, only create one interface with additional addresses as aliases */
		$iface_list["ipsec".get_ipsecifnum($ph1ent['ikeid'], 0)] = gettext("IPsec VTI") . ": ".htmlspecialchars($ph1ent['descr']);
	}
	return $iface_list;
}
function interface_ipsec_vti_list_all() {
	global $config;
	$iface_list = array();
	if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			if ($ph1ent['disabled']) {
				continue;
			}
			$iface_list = array_merge($iface_list, interface_ipsec_vti_list_p1($ph1ent));
		}
	}
	return $iface_list;
}

function is_interface_ipsec_vti_assigned($phase2) {
	$phase1 = ipsec_get_phase1($phase2['ikeid']);
	$vti_interface = null;
	$vtisubnet_spec = ipsec_vti($phase1, true);
	if (($vtisubnet_spec && is_array($vtisubnet_spec))) {
		/* With IKEv1 or v2+Split, each P2 gets its own conn/reqid/interface */
		if (!isset($phase1['mobile']) && ($phase1['iketype'] == 'ikev1' || isset($phase1['splitconn']))) {
			foreach ($vtisubnet_spec as $idx => $vtisub) {
				/* Is this for this P2? */
				if (($vtisub['left'] == ipsec_idinfo_to_cidr($phase2['localid'], true, $phase2['mode'])) &&
				    ($vtisub['right'] == ipsec_idinfo_to_cidr($phase2['remoteid'], false, $phase2['mode']))) {
					$vti_interface = "ipsec".get_ipsecifnum($phase1['ikeid'], $idx);
				}
			}
		} else {
			$vti_interface = "ipsec".get_ipsecifnum($phase1['ikeid'], 0);
		}
	}
	/* Check if this interface is assigned */
	return (does_interface_exist($vti_interface) && (convert_real_interface_to_friendly_interface_name($vti_interface) != null));
}
function interface_ipsec_vti_configure($ph1ent) {
	global $config;

	if (empty($ph1ent) || !is_array($ph1ent) || !is_array($config['ipsec']['phase2'])) {
		return false;
	}

	$vtisubnet_spec = ipsec_vti($ph1ent, true);
	if ((!$vtisubnet_spec || !is_array($vtisubnet_spec))) {
		return false;
	}

	$left_spec = ipsec_get_phase1_src($ph1ent);
	$right_spec = $ph1ent['remote-gateway'];

	$iface_addrs = array();

	/* With IKEv1 or v2+Split, each P2 gets its own conn/reqid/interface */
	if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
		/* Form a single interface for each P2 entry */
		foreach ($vtisubnet_spec as $idx => $vtisub) {
			$ipsecifnum = get_ipsecifnum($ph1ent['ikeid'], $idx);
			if (!is_array($iface_addrs[$ipsecifnum])) {
				$iface_addrs[$ipsecifnum] = array();
			}
			$vtisub['alias'] = "";
			$iface_addrs[$ipsecifnum][] = $vtisub;
		}
	} else {
		/* For IKEv2, only create one interface with additional addresses as aliases */
		$ipsecifnum = get_ipsecifnum($ph1ent['ikeid'], 0);
		if (!is_array($iface_addrs[$ipsecifnum])) {
			$iface_addrs[$ipsecifnum] = array();
		}
		$have_v4 = false;
		$have_v6 = false;
		foreach ($vtisubnet_spec as $vtisub) {
			// Alias stuff
			$vtisub['alias'] = "";
			if (is_ipaddrv6($vtisub['left'])) {
				if ($have_v6) {
					$vtisub['alias'] = " alias";
				}
				$have_v6 = true;
			} else {
				if ($have_v4) {
					$vtisub['alias'] = " alias";
				}
				$have_v4 = true;
			}
			$iface_addrs[$ipsecifnum][] = $vtisub;
		}
	}

	foreach ($iface_addrs as $ipsecifnum => $addrs) {
		$ipsecif = "ipsec{$ipsecifnum}";
		if (!is_array($addrs)) {
			continue;
		}
		// Create IPsec interface
		if (does_interface_exist($ipsecif)) {
			mwexec("/sbin/ifconfig " . escapeshellarg($ipsecif) . " destroy");
		}
		mwexec("/sbin/ifconfig " . escapeshellarg($ipsecif) . " create reqid " . escapeshellarg($ipsecifnum));

		/* Apply the outer tunnel addresses to the interface */
		$inet = is_ipaddrv6($left_spec) ? "inet6" : "inet";
		mwexec("/sbin/ifconfig " . escapeshellarg($ipsecif) . " {$inet} tunnel " . escapeshellarg($left_spec) . " " . escapeshellarg($right_spec) . " up");

		/* Loop through all of the addresses for this interface and apply them as needed */
		foreach ($addrs as $addr) {
			// apply interface addresses
			if (is_v6($addr['left'])) {
				$inet = "inet6";
				$gwtype = "v6";
				$right = '';
			} else {
				$inet = "inet";
				$gwtype = "";
				$right = escapeshellarg($addr['right']);
			}

			mwexec("/sbin/ifconfig " . escapeshellarg($ipsecif) . " {$inet} " . escapeshellarg($addr['left']) . " " . $right . $addr['alias']);
			/* If alias is empty, this is the first address on the interface and should be used as the gateway. */
			if (empty($addr['alias'])) {
				file_put_contents("/tmp/{$ipsecif}_router{$gwtype}", $addr['right']);
			}
		}
		/* Check/set the MTU if the user configured a custom value.
		 * https://redmine.pfsense.org/issues/9111 */
		$currentvtimtu = get_interface_mtu($ipsecif);
		foreach ($config['interfaces'] as $tmpinterface) {
			if ($tmpinterface['if'] == $ipsecif) {
				if (isset($tmpinterface['mtu']) && is_numericint($tmpinterface['mtu'])) {
					$vtimtu = $tmpinterface['mtu'];
				}
			}
		}
		if (is_numericint($vtimtu)) {
			if ($vtimtu != $currentvtimtu) {
				mwexec("/sbin/ifconfig {$ipsecif} mtu {$vtimtu}");
			}
		}
		system_routing_configure(convert_real_interface_to_friendly_interface_name($ipsecif));
	}
}

function interfaces_ipsec_vti_configure() {
	global $config;
	if (platform_booting()) {
		echo gettext("Configuring IPsec VTI interfaces...");
	}
	if (is_array($config['ipsec']) &&
	    is_array($config['ipsec']['phase1']) &&
	    is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			if ($ph1ent['disabled']) {
				continue;
			}
			interface_ipsec_vti_configure($ph1ent);
		}
	}
	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function interfaces_configure() {
	global $config, $g;

	/* Set up our loopback interface */
	interfaces_loopback_configure();

	/* create the unconfigured wireless clones */
	interfaces_create_wireless_clones();

	/* set up LAGG virtual interfaces */
	interfaces_lagg_configure();

	/* set up VLAN virtual interfaces */
	interfaces_vlan_configure();

	interfaces_qinq_configure();

	/* set up IPsec VTI interfaces */
	interfaces_ipsec_vti_configure();

	$iflist = get_configured_interface_with_descr(true);
	$delayed_list = array();
	$bridge_list = array();
	$track6_list = array();
	$dhcp6c_list = array();

	/* This is needed to speedup interfaces on bootup. */
	$reload = false;
	if (!platform_booting()) {
		$reload = true;
	}

	foreach ($iflist as $if => $ifname) {
		$realif = $config['interfaces'][$if]['if'];
		if (!isset($config['interfaces'][$if]['enable'])) {
			interface_bring_down($if, true);
		} elseif (strstr($realif, "bridge")) {
			$bridge_list[$if] = $ifname;
		} elseif (strstr($realif, "gre")) {
			$delayed_list[$if] = $ifname;
		} elseif (strstr($realif, "gif")) {
			$delayed_list[$if] = $ifname;
		} elseif (strstr($realif, "ovpn")) {
			continue;
		} elseif (strstr($realif, "ipsec")) {
			continue;
		} elseif (!empty($config['interfaces'][$if]['ipaddrv6']) &&
		    $config['interfaces'][$if]['ipaddrv6'] == "track6") {
			$track6_list[$if] = $ifname;
		} else {
			/* do not run dhcp6c if track interface does not exists
			 * see https://redmine.pfsense.org/issues/3965 */
			if (!empty($config['interfaces'][$if]['ipaddrv6']) &&
			    $config['interfaces'][$if]['ipaddrv6'] == "dhcp6") {
				$tr6list = link_interface_to_track6($if);
				if (is_array($tr6list) && !empty($tr6list)) {
					foreach ($tr6list as $tr6if) {
						if (!does_interface_exist($tr6if)) {
							$dhcp6c_list[$if] = $ifname;
							continue;
						}
					}
				}
			}
			if (platform_booting()) {
				printf(gettext("Configuring %s interface..."),
				    $ifname);
			}

			if ($g['debug']) {
				log_error(sprintf(gettext("Configuring %s"),
				    $ifname));
			}
			interface_configure($if, $reload);
			if (platform_booting()) {
				echo gettext("done.") . "\n";
			}
		}
	}

	/*
	 * NOTE: The following function parameter consists of
	 *	1 - Do not load gre/gif/bridge with parent/member as vip
	 *	2 - Do load gre/gif/bridge with parent/member as vip
	 */

	/* set up GRE virtual interfaces */
	interfaces_tunnel_configure(1,'','gre');

	/* set up GIF virtual interfaces */
	interfaces_tunnel_configure(1,'','gif');

	/* set up BRIDGE virtual interfaces */
	interfaces_bridge_configure(1);

	foreach ($track6_list as $if => $ifname) {
		if (platform_booting()) {
			printf(gettext("Configuring %s interface..."), $ifname);
		}
		if ($g['debug']) {
			log_error(sprintf(gettext("Configuring %s"), $ifname));
		}

		interface_configure($if, $reload);

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	/* bring up vip interfaces */
	interfaces_vips_configure();

	/* set up GRE virtual interfaces */
	interfaces_tunnel_configure(2,'','gre');

	/* set up GIF virtual interfaces */
	interfaces_tunnel_configure(2,'','gif');

	foreach ($delayed_list as $if => $ifname) {
		if (platform_booting()) {
			printf(gettext("Configuring %s interface..."), $ifname);
		}
		if ($g['debug']) {
			log_error(sprintf(gettext("Configuring %s"), $ifname));
		}

		interface_configure($if, $reload);

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	/* set up BRIDGE virtual interfaces */
	interfaces_bridge_configure(2);

	foreach ($bridge_list as $if => $ifname) {
		if (platform_booting()) {
			printf(gettext("Configuring %s interface..."), $ifname);
		}
		if ($g['debug']) {
			log_error(sprintf(gettext("Configuring %s"), $ifname));
		}

		// bridge interface needs reconfigure, then re-add VIPs, to ensure find_interface_ip is correct.
		// redmine #3997
		interface_reconfigure($if, $reload);
		interfaces_vips_configure($if);

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	foreach ($dhcp6c_list as $if => $ifname) {
		if (platform_booting()) {
			printf(gettext("Configuring %s interface..."), $ifname);
		}
		if ($g['debug']) {
			log_error(sprintf(gettext("Configuring %s"), $ifname));
		}

		interface_configure($if, $reload);

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	/* configure interface groups */
	interfaces_group_setup();

	if (!platform_booting()) {
		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* reload IPsec tunnels */
		ipsec_configure();

		/* restart dns servers (defering dhcpd reload) */
		if (isset($config['dnsmasq']['enable'])) {
			services_dnsmasq_configure(false);
		}
		if (isset($config['unbound']['enable'])) {
			services_unbound_configure(false);
		}

		/* reload dhcpd (interface enabled/disabled status may have changed) */
		services_dhcpd_configure();
	}

	return 0;
}

function interface_reconfigure($interface = "wan", $reloadall = false) {
	interface_bring_down($interface);
	interface_configure($interface, $reloadall);
}

function interface_vip_bring_down($vip) {
	global $g;

	$vipif = get_real_interface($vip['interface']);
	switch ($vip['mode']) {
		case "proxyarp":
			if (file_exists("{$g['varrun_path']}/choparp_{$vipif}.pid")) {
				killbypid("{$g['varrun_path']}/choparp_{$vipif}.pid");
			}
			break;
		case "ipalias":
			if (does_interface_exist($vipif)) {
				if (is_ipaddrv6($vip['subnet'])) {
					mwexec("/sbin/ifconfig {$vipif} inet6 " . escapeshellarg($vip['subnet']) . " -alias");
				} else {
					pfSense_interface_deladdress($vipif, $vip['subnet']);
				}
			}
			break;
		case "carp":
			/* XXX: Is enough to delete ip address? */
			if (does_interface_exist($vipif)) {
				if (is_ipaddrv6($vip['subnet'])) {
					mwexec("/sbin/ifconfig {$vipif} inet6 " . escapeshellarg($vip['subnet']) . " delete");
				} else {
					pfSense_interface_deladdress($vipif, $vip['subnet']);
				}
			}
			break;
	}
}

function interface_bring_down($interface = "wan", $destroy = false, $ifacecfg = false) {
	global $config, $g;

	if (!isset($config['interfaces'][$interface])) {
		return;
	}

	if (substr($config['interfaces'][$interface]['if'], 0, 2) == 'wg') {
		require_once('wg.inc');
		if ($destroy) {
			wg_destroy_if($config['interfaces'][$interface]['if']);
		}
		return;
	}

	if ($g['debug']) {
		log_error(sprintf(gettext('Calling interface down for interface %1$s, destroy is %2$s'), $interface, (($destroy) ? 'true' : 'false')));
	}

	/*
	 * NOTE: The $realifv6 is needed when WANv4 is type PPP and v6 is DHCP and the option v6 from v4 is used.
	 * In this case the real $realif of v4 is different from that of v6 for operation.
	 * Keep this in mind while doing changes here!
	 */
	if ($ifacecfg === false) {
		$ifcfg = $config['interfaces'][$interface];
		$ppps = $config['ppps']['ppp'];
		$realif = get_real_interface($interface);
		$realifv6 = get_real_interface($interface, "inet6", true);
	} elseif (!is_array($ifacecfg)) {
		log_error(gettext("Wrong parameters used during interface_bring_down"));
		$ifcfg = $config['interfaces'][$interface];
		$ppps = $config['ppps']['ppp'];
		$realif = get_real_interface($interface);
		$realifv6 = get_real_interface($interface, "inet6", true);
	} else {
		$ifcfg = $ifacecfg['ifcfg'];
		$ppps = $ifacecfg['ppps'];
		if (isset($ifacecfg['ifcfg']['realif'])) {
			$realif = $ifacecfg['ifcfg']['realif'];
			/* XXX: Any better way? */
			$realifv6 = $realif;
		} else {
			$realif = get_real_interface($interface);
			$realifv6 = get_real_interface($interface, "inet6", true);
		}
	}

	switch ($ifcfg['ipaddr']) {
		case "ppp":
		case "pppoe":
		case "pptp":
		case "l2tp":
			if (is_array($ppps) && count($ppps)) {
				foreach ($ppps as $pppid => $ppp) {
					if ($realif == $ppp['if']) {
						if (isset($ppp['ondemand']) && !$destroy) {
							send_event("interface reconfigure {$interface}");
							break;
						}
						if (file_exists("{$g['varrun_path']}/{$ppp['type']}_{$interface}.pid")) {
							killbypid("{$g['varrun_path']}/{$ppp['type']}_{$interface}.pid");
							sleep(2);
						}
						unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.conf");
						break;
					}
				}
			}
			break;
		case "dhcp":
			kill_dhclient_process($realif);
			unlink_if_exists("{$g['varetc_path']}/dhclient_{$interface}.conf");
			if (does_interface_exist("$realif")) {
				mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " delete", true);
				interface_vip_cleanup($interface, "inet4");
				if ($destroy == true) {
					pfSense_interface_flags($realif, -IFF_UP);
				}
				mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
			}
			break;
		default:
			if (does_interface_exist("$realif")) {
				mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " delete", true);
				interface_vip_cleanup($interface, "inet4");
				if ($destroy == true) {
					pfSense_interface_flags($realif, -IFF_UP);
				}
				mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
			}
			break;
	}

	$track6 = array();
	switch ($ifcfg['ipaddrv6']) {
		case "slaac":
		case "dhcp6":
			kill_dhcp6client_process($realif, $destroy, false);
			unlink_if_exists("{$g['varetc_path']}/dhcp6c_{$interface}.conf");
			unlink_if_exists("{$g['varetc_path']}/dhcp6c_{$interface}_script.sh");
			unlink_if_exists("{$g['varetc_path']}/dhcp6c_{$interface}_dhcp6withoutra_script.sh");
			unlink_if_exists("{$g['varetc_path']}/rtsold_{$realifv6}_script.sh");
			if (does_interface_exist($realifv6)) {
				mwexec("/sbin/ifconfig " . escapeshellarg($realifv6) . " inet6 -accept_rtadv");
				$ip6 = find_interface_ipv6($realifv6);
				if (is_ipaddrv6($ip6) && $ip6 != "::") {
					mwexec("/sbin/ifconfig " . escapeshellarg($realifv6) . " inet6 {$ip6} delete", true);
				}
				interface_vip_cleanup($interface, "inet6");
				if ($destroy == true) {
					pfSense_interface_flags($realif, -IFF_UP);
				}
			}
			$track6 = link_interface_to_track6($interface);
			break;
		case "6rd":
		case "6to4":
			$realif = "{$interface}_stf";
			if (does_interface_exist("$realif")) {
				/* destroy stf interface if tunnel is being disabled or tunnel type is being changed */
				if (($ifcfg['ipaddrv6'] == '6rd' && (!isset($config['interfaces'][$interface]['ipaddrv6']) || $config['interfaces'][$interface]['ipaddrv6'] != '6rd')) ||
				    ($ifcfg['ipaddrv6'] == '6to4' && (!isset($config['interfaces'][$interface]['ipaddrv6']) || $config['interfaces'][$interface]['ipaddrv6'] != '6to4'))) {
					$destroy = true;
				} else {
					/* get_interface_ipv6() returns empty value if interface is being disabled */
					$ip6 = get_interface_ipv6($interface);
					if (is_ipaddrv6($ip6)) {
						mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ip6} delete", true);
					}
				}
				interface_vip_cleanup($interface, "inet6");
				if ($destroy == true) {
					pfSense_interface_flags($realif, -IFF_UP);
				}
			}
			$track6 = link_interface_to_track6($interface);
			break;
		default:
			if (does_interface_exist("$realif")) {
				$ip6 = get_interface_ipv6($interface);
				if (is_ipaddrv6($ip6)) {
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ip6} delete", true);
				}
				if (!empty($ifcfg['ipaddrv6']) && is_ipaddrv6($ifcfg['ipaddrv6'])) {
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ifcfg['ipaddrv6']} delete", true);
				}
				interface_vip_cleanup($interface, "inet6");
				if ($destroy == true) {
					pfSense_interface_flags($realif, -IFF_UP);
				}
				//mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
			}
			$track6 = link_interface_to_track6($interface);
			break;
	}

	if (!empty($track6) && is_array($track6)) {
		if (!function_exists('services_dhcpd_configure')) {
			require_once('services.inc');
		}
		/* Bring down radvd and dhcp6 on these interfaces */
		services_dhcpd_configure('inet6', $track6);
	}

	$old_router = '';
	if (file_exists("{$g['tmp_path']}/{$realif}_router")) {
		$old_router = trim(file_get_contents("{$g['tmp_path']}/{$realif}_router"));
	}

	/* remove interface up file if it exists */
	unlink_if_exists("{$g['tmp_path']}/{$realif}up");
	unlink_if_exists("{$g['vardb_path']}/{$interface}ip");
	unlink_if_exists("{$g['vardb_path']}/{$interface}ipv6");
	unlink_if_exists("{$g['tmp_path']}/{$realif}_router");
	unlink_if_exists("{$g['tmp_path']}/{$realif}_routerv6");
	unlink_if_exists("{$g['varetc_path']}/nameserver_{$realif}");
	unlink_if_exists("{$g['varetc_path']}/nameserver_v6{$interface}");
	unlink_if_exists("{$g['varetc_path']}/searchdomain_{$realif}");
	unlink_if_exists("{$g['varetc_path']}/searchdomain_v6{$interface}");

	/* hostapd and wpa_supplicant do not need to be running when the interface is down.
	 * They will also use 100% CPU if running after the wireless clone gets deleted. */
	if (is_array($ifcfg['wireless'])) {
		kill_hostapd($realif);
		mwexec(kill_wpasupplicant($realif));
		unlink_if_exists("{$g['varetc_path']}/wpa_supplicant_{$realif}.*");
		unlink_if_exists("{$g['varetc_path']}/hostapd_{$realif}.conf");
	}

	if ($destroy == true) {
		if (preg_match("/^[a-z0-9]+^tun|^ovpn|^ipsec|^gif|^gre|^lagg|^bridge|vlan|_stf$/i", $realif)) {
			pfSense_interface_destroy($realif);

			/* Invalidate cache */
			get_interface_arr(true);
		}
	}

	return;
}

function interfaces_carp_set_maintenancemode($carp_maintenancemode) {
	global $config;
	if (isset($config["virtualip_carp_maintenancemode"]) && $carp_maintenancemode == false) {
		unset($config["virtualip_carp_maintenancemode"]);
		write_config("Leave CARP maintenance mode");
	} elseif (!isset($config["virtualip_carp_maintenancemode"]) && $carp_maintenancemode == true) {
		$config["virtualip_carp_maintenancemode"] = true;
		write_config(gettext("Enter CARP maintenance mode"));
	}
	init_config_arr(array('virtualip', 'vip'));
	$viparr = &$config['virtualip']['vip'];

	if (is_array($viparr)) {
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "carp") {
				interface_carp_configure($vip, true);
			}
		}
	}
}

function interface_wait_tentative($interface, $timeout = 10) {
	if (!does_interface_exist($interface)) {
		return false;
	}

	$time = 0;
	while ($time <= $timeout) {
		$if = pfSense_get_interface_addresses($interface);
		if (!isset($if['tentative'])) {
			return true;
		}
		sleep(1);
		$time++;
	}

	return false;
}

function interface_isppp_type($interface) {
	global $config;

	if (!is_array($config['interfaces'][$interface])) {
		return false;
	}

	switch ($config['interfaces'][$interface]['ipaddr']) {
		case 'pptp':
		case 'l2tp':
		case 'pppoe':
		case 'ppp':
			return true;
			break;
		default:
			return false;
			break;
	}
}

function interfaces_ptpid_used($ptpid) {
	global $config;

	if (is_array($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as & $settings) {
			if ($ptpid == $settings['ptpid']) {
				return true;
			}
		}
	}

	return false;
}

function interfaces_ptpid_next() {

	$ptpid = 0;
	while (interfaces_ptpid_used($ptpid)) {
		$ptpid++;
	}

	return $ptpid;
}

function getMPDCRONSettings($pppif) {
	global $config;

	$cron_cmd_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $i => $item) {
			if (stripos($item['command'], $cron_cmd_file) !== false) {
				return array("ID" => $i, "ITEM" => $item);
			}
		}
	}

	return NULL;
}

function handle_pppoe_reset($post_array) {
	global $config, $g;

	$pppif = "{$post_array['type']}{$post_array['ptpid']}";
	$cron_cmd_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";

	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}

	$itemhash = getMPDCRONSettings($pppif);

	// reset cron items if necessary and return
	if (empty($post_array['pppoe-reset-type'])) {
		if (isset($itemhash)) {
			unset($config['cron']['item'][$itemhash['ID']]);
		}
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
		return;
	}

	if (empty($itemhash)) {
		$itemhash = array();
	}
	$item = array();
	if (isset($post_array['pppoe-reset-type']) && $post_array['pppoe-reset-type'] == "custom") {
		$item['minute'] = $post_array['pppoe_resetminute'];
		$item['hour'] = $post_array['pppoe_resethour'];
		if (isset($post_array['pppoe_resetdate']) && $post_array['pppoe_resetdate'] <> "") {
			$date = explode("/", $post_array['pppoe_resetdate']);
			$item['mday'] = $date[1];
			$item['month'] = $date[0];
		} else {
			$item['mday'] = "*";
			$item['month'] = "*";
		}
		$item['wday'] = "*";
		$item['who'] = "root";
		$item['command'] = $cron_cmd_file;
	} elseif (isset($post_array['pppoe-reset-type']) && $post_array['pppoe-reset-type'] == "preset") {
		switch ($post_array['pppoe_pr_preset_val']) {
			case "monthly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "1";
				$item['month'] = "*";
				$item['wday'] = "*";
				break;
			case "weekly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "0";
				break;
			case "daily":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				break;
			case "hourly":
				$item['minute'] = "0";
				$item['hour'] = "*";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				break;
		} // end switch
		$item['who'] = "root";
		$item['command'] = $cron_cmd_file;
	}
	if (empty($item)) {
		return;
	}
	if (isset($itemhash['ID'])) {
		$config['cron']['item'][$itemhash['ID']] = $item;
	} else {
		$config['cron']['item'][] = $item;
	}
}

function restart_ppp_interfaces_using_interfaces($triggerinterfaces) {
	global $config;
	$ppp_list = array();
	if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			$ports = explode(",", $ppp['ports']);
			foreach($ports as $port) {
				foreach($triggerinterfaces as $vip) {
					if ($port == "_vip{$vip['uniqid']}") {
						$if = convert_real_interface_to_friendly_interface_name($ppp['if']);
						$ppp_list[$if] = 1;
					}
				}
			}
		}
	}
	foreach($ppp_list as $pppif => $dummy) {
		interface_ppps_configure($pppif);
	}
}

/*
 * This function can configure PPPoE, MLPPP (PPPoE), PPTP.
 * It writes the mpd config file to /var/etc every time the link is opened.
 */
function interface_ppps_configure($interface) {
	global $config, $g;

	/* Return for unassigned interfaces. This is a minimum requirement. */
	if (empty($config['interfaces'][$interface])) {
		return 0;
	}
	$ifcfg = $config['interfaces'][$interface];
	if (!isset($ifcfg['enable'])) {
		return 0;
	}

	// mpd5 requires a /var/spool/lock directory for PPP modem links.
	if (!is_dir("/var/spool/lock")) {
		mkdir("/var/spool/lock", 0777, true);
	}
	// mpd5 modem chat script expected in the same directory as the mpd_xxx.conf files
	if (!file_exists("{$g['varetc_path']}/mpd.script")) {
		@symlink("/usr/local/sbin/mpd.script", "{$g['varetc_path']}/mpd.script");
	}

	if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			if ($ifcfg['if'] == $ppp['if']) {
				break;
			}
		}
	}
	if (!$ppp || $ifcfg['if'] != $ppp['if']) {
		log_error(sprintf(gettext("Can't find PPP config for %s in interface_ppps_configure()."), $ifcfg['if']));
		return 0;
	}
	$pppif = $ifcfg['if'];
	if ($ppp['type'] == "ppp") {
		$type = "modem";
	} else {
		$type = $ppp['type'];
	}
	$upper_type = strtoupper($ppp['type']);

	$confports = explode(',', $ppp['ports']);
	if ($type == "modem") {
		$ports = $confports;
	} else {
		$ports = array();
		foreach ($confports as $pid => $port) {
			if (strstr($port, "_vip")) {
				if (get_carp_interface_status($port) != "MASTER") {
					continue;
				}
			}
			$ports[$pid] = get_real_interface($port);
			if (empty($ports[$pid])) {
				return 0;
			}
		}
	}
	$localips = explode(',', $ppp['localip']);
	$gateways = explode(',', $ppp['gateway']);
	$subnets = explode(',', $ppp['subnet']);

	/* We bring up the parent interface first because if DHCP is configured on the parent we need
	 * to obtain an address first so we can write it in the mpd .conf file for PPTP and L2TP configs
	 */
	foreach ($ports as $pid => $port) {
		switch ($ppp['type']) {
			case "pppoe":
				/* Bring the parent interface up */
				interfaces_bring_up($port);
				pfSense_ngctl_attach(".", $port);
				/* Enable setautosrc to automatically change mac address if parent interface's changes */
				$ngif = str_replace(".", "_", $port);
				mwexec("/usr/sbin/ngctl msg {$ngif}: setautosrc 1");
				break;
			case "pptp":
			case "l2tp":
				/* configure interface */
				if (is_ipaddr($localips[$pid])) {
					// Manually configure interface IP/subnet
					pfSense_interface_setaddress($port, "{$localips[$pid]}/{$subnets[$pid]}");
					interfaces_bring_up($port);
				} elseif (empty($localips[$pid])) {
					$localips[$pid] = get_interface_ip($port); // try to get the interface IP from the port
				}

				if (!is_ipaddr($localips[$pid])) {
					log_error(sprintf(gettext("Could not get a Local IP address for PPTP/L2TP link on %s in interfaces_ppps_configure. Using 0.0.0.0 ip!"), $port));
					$localips[$pid] = "0.0.0.0";
				}
				if (!$g['booting'] && !is_ipaddr($gateways[$pid]) && is_hostname($gateways[$pid])) {
					$gateways[$pid] = gethostbyname($gateways[$pid]);
				}
				if (!is_ipaddr($gateways[$pid])) {
					log_error(sprintf(gettext('Could not get a PPTP/L2TP Remote IP address from %1$s for %2$s in interfaces_ppps_configure.'), $dhcp_gateway, $gway));
					return 0;
				}
				pfSense_ngctl_attach(".", $port);
				break;
			case "ppp":
				if (!file_exists("{$port}")) {
					log_error(sprintf(gettext("Device %s does not exist. PPP link cannot start without the modem device."), $port));
					return 0;
				}
				break;
			default:
				log_error(sprintf(gettext("Unknown %s configured as ppp interface."), $type));
				break;
		}
	}

	if (isset($ppp['pppoe-multilink-over-singlelink']) ||
	    (is_array($ports) && count($ports) > 1)) {
		$multilink = "enable";
	} else {
		$multilink = "disable";
	}

	if ($type == "modem") {
		if (is_ipaddr($ppp['localip'])) {
			$localip = $ppp['localip'];
		} else {
			$localip = '0.0.0.0';
		}

		if (is_ipaddr($ppp['gateway'])) {
			$gateway = $ppp['gateway'];
		} else {
			$gateway = "10.64.64.{$pppid}";
		}
		$ranges = "{$localip}/0 {$gateway}/0";

		if (empty($ppp['apnum'])) {
			$ppp['apnum'] = 1;
		}
	} else {
		$ranges = "0.0.0.0/0 0.0.0.0/0";
	}

	if (isset($ppp['ondemand'])) {
		$ondemand = "enable";
	} else {
		$ondemand = "disable";
	}
	if (!isset($ppp['idletimeout'])) {
		$ppp['idletimeout'] = 0;
	}

	if (empty($ppp['username']) && $type == "modem") {
		$ppp['username'] = "user";
		$ppp['password'] = "none";
	}
	if (empty($ppp['password']) && $type == "modem") {
		$passwd = "none";
	} else {
		$passwd = base64_decode($ppp['password']);
	}

	$bandwidths = explode(',', $ppp['bandwidth']);
	$defaultmtu = "1492";
	if (!empty($ifcfg['mtu'])) {
		$defaultmtu = intval($ifcfg['mtu']);
	}
	if (isset($ppp['mtu'])) {
		$mtus = explode(',', $ppp['mtu']);
	}
	if (isset($ppp['mru'])) {
		$mrus = explode(',', $ppp['mru']);
	}
	if (isset($ppp['mrru'])) {
		$mrrus = explode(',', $ppp['mrru']);
	}
	if (!empty($ifcfg['ipaddrv6'])) {
		$ipv6cp = "set bundle enable ipv6cp";
	}

	// Construct the mpd.conf file
	$mpdconf = <<<EOD
startup:
	# configure the console
	set console close
	# configure the web server
	set web close

default:
{$ppp['type']}client:
	create bundle static {$interface}
	{$ipv6cp}
	set iface name {$pppif}

EOD;
	$setdefaultgw = false;
	$defgw4 = lookup_gateway_or_group_by_name($config['gateways']['defaultgw4']);
//	$defgw6 = lookup_gateway_or_group_by_name($config['gateways']['defaultgw6']);
	if ($defgw4['interface'] == $interface) {
		$setdefaultgw = true;
	}

/* Omit this, we maintain the default route by other means, and it causes problems with
 * default gateway switching. See redmine #1837 for original issue
 * re-enabling this for now to fix issue with missing default gateway with PPPoE in some
 * edge case. redmine #6495 open to address.
 */
	if ($setdefaultgw == true) {
		$mpdconf .= <<<EOD
	set iface route default

EOD;
	}

	$mpdconf .= <<<EOD
	set iface {$ondemand} on-demand
	set iface idle {$ppp['idletimeout']}

EOD;

	if (isset($ppp['ondemand'])) {
		$mpdconf .= <<<EOD
	set iface addrs 10.10.1.1 10.10.1.2

EOD;
	}

	if (isset($ppp['mtu-override']) &&
	    !isset($ppp['pppoe-multilink-over-singlelink'])) {
		/* Find the smaller MTU set on ports */
		$mtu = $defaultmtu;
		foreach ($ports as $pid => $port) {
			if (empty($mtus[$pid])) {
				$mtus[$pid] = $defaultmtu;
			}
			if ($type == "pppoe") {
				if ($mtus[$pid] > (get_interface_mtu($port) - 8)) {
					$mtus[$pid] = get_interface_mtu($port) - 8;
				}
			}
			if ($mtu > $mtus[$pid]) {
				$mtu = $mtus[$pid];
			}
		}
		$mpdconf .= <<<EOD
	set iface mtu {$mtu} override

EOD;
	}

	if (isset($ppp['tcpmssfix'])) {
		$tcpmss = "disable";
	} else {
		$tcpmss = "enable";
	}
	$mpdconf .= <<<EOD
	set iface {$tcpmss} tcpmssfix

EOD;

	$mpdconf .= <<<EOD
	set iface up-script /usr/local/sbin/ppp-linkup
	set iface down-script /usr/local/sbin/ppp-linkdown
	set ipcp ranges {$ranges}

EOD;
	if (isset($ppp['vjcomp'])) {
		$mpdconf .= <<<EOD
	set ipcp no vjcomp

EOD;
	}

	if (isset($config['system']['dnsallowoverride'])) {
		$mpdconf .= <<<EOD
	set ipcp enable req-pri-dns
	set ipcp enable req-sec-dns

EOD;
	}

	if (!isset($ppp['verbose_log'])) {
		$mpdconf .= <<<EOD
	#log -bund -ccp -chat -iface -ipcp -lcp -link

EOD;
	}

	foreach ($ports as $pid => $port) {
		$port = get_real_interface($port);
		$mpdconf .= <<<EOD

	create link static {$interface}_link{$pid} {$type}
	set link action bundle {$interface}
	set link {$multilink} multilink
	set link keep-alive 10 60
	set link max-redial 0

EOD;
		if (isset($ppp['shortseq'])) {
			$mpdconf .= <<<EOD
	set link no shortseq

EOD;
		}

		if (isset($ppp['acfcomp'])) {
			$mpdconf .= <<<EOD
	set link no acfcomp

EOD;
		}

		if (isset($ppp['protocomp'])) {
			$mpdconf .= <<<EOD
	set link no protocomp

EOD;
		}

		$mpdconf .= <<<EOD
	set link disable chap pap
	set link accept chap pap eap
	set link disable incoming

EOD;


		if (!empty($bandwidths[$pid])) {
			$mpdconf .= <<<EOD
	set link bandwidth {$bandwidths[$pid]}

EOD;
		}

		if (empty($mtus[$pid])) {
			$mtus[$pid] = $defaultmtu;
		}
		if ($type == "pppoe") {
			if ($mtus[$pid] > (get_interface_mtu($port) - 8)) {
				$mtus[$pid] = get_interface_mtu($port) - 8;
			}
		}
		if (!isset($ppp['pppoe-multilink-over-singlelink']) &&
		    !isset($ppp['mtu-override']) &&
		    !($type == "pppoe" && $mtus[$pid] > 1492)) {
			// N.B. MTU for PPPoE with MTU > 1492 is set using pppoe max-payload - see below
			$mpdconf .= <<<EOD
	set link mtu {$mtus[$pid]}

EOD;
		}

		if (!isset($ppp['pppoe-multilink-over-singlelink']) &&
		    !isset($ppp['mtu-override']) &&
		    !empty($mrus[$pid])) {
			$mpdconf .= <<<EOD
	set link mru {$mrus[$pid]}

EOD;
		}

		if (!empty($mrrus[$pid])) {
			$mpdconf .= <<<EOD
	set link mrru {$mrrus[$pid]}

EOD;
		}

		$mpdconf .= <<<EOD
	set auth authname "{$ppp['username']}"
	set auth password {$passwd}

EOD;
		if ($type == "modem") {
			$mpdconf .= <<<EOD
	set modem device {$ppp['ports']}
	set modem script DialPeer
	set modem idle-script Ringback
	set modem watch -cd
	set modem var \$DialPrefix "DT"
	set modem var \$Telephone "{$ppp['phone']}"

EOD;
		}
		if (isset($ppp['connect-timeout']) && $type == "modem") {
			$mpdconf .= <<<EOD
	set modem var \$ConnectTimeout "{$ppp['connect-timeout']}"

EOD;
		}
		if (isset($ppp['initstr']) && $type == "modem") {
			$initstr = base64_decode($ppp['initstr']);
			$mpdconf .= <<<EOD
	set modem var \$InitString "{$initstr}"

EOD;
		}
		if (isset($ppp['simpin']) && $type == "modem") {
			if ($ppp['pin-wait'] == "") {
				$ppp['pin-wait'] = 0;
			}
			$mpdconf .= <<<EOD
	set modem var \$SimPin "{$ppp['simpin']}"
	set modem var \$PinWait "{$ppp['pin-wait']}"

EOD;
		}
		if (isset($ppp['apn']) && $type == "modem") {
			$mpdconf .= <<<EOD
	set modem var \$APN "{$ppp['apn']}"
	set modem var \$APNum "{$ppp['apnum']}"

EOD;
		}
		if ($type == "pppoe") {
			$hostuniq = '';
			if (!empty($ppp['hostuniq'])) {
				if (preg_match('/^0x[a-fA-F0-9]+$/', $ppp['hostuniq'])) {
					$hostuniq = strtolower($ppp['hostuniq']) .'|';
				} elseif (preg_match('/^[a-zA-Z0-9]+$/i', $ppp['hostuniq'])) {
					$hostuniq = '0x' . bin2hex($ppp['hostuniq']) . '|';
				}
			}
			// Send a null service name if none is set.
			$provider = isset($ppp['provider']) ? $ppp['provider'] : "";
			$mpdconf .= <<<EOD
	set pppoe service "{$hostuniq}{$provider}"

EOD;
		}
		if (($type == "pppoe") && ($mtus[$pid] > 1492)) {
			$mpdconf .= <<<EOD
	set pppoe max-payload {$mtus[$pid]}

EOD;
		}
		if ($type == "pppoe") {
			$mpdconf .= <<<EOD
	set pppoe iface {$port}

EOD;
		}

		if (($type == "l2tp") && !empty($ppp['secret'])) {
			$secret = str_replace('"', '\"', base64_decode($ppp['secret']));
			$mpdconf .= <<<EOD
	set l2tp secret "{$secret}"

EOD;
		}

		if (($type == "pptp") || ($type == "l2tp")) {
			$mpdconf .= <<<EOD
	set {$type} self {$localips[$pid]}
	set {$type} peer {$gateways[$pid]}

EOD;
		}

		$mpdconf .= "\topen\n";
	} //end foreach ($port)


	/* Generate mpd.conf. If mpd_[interface].conf exists in the conf path, then link to it instead of generating a fresh conf file. */
	if (file_exists("{$g['conf_path']}/mpd_{$interface}.conf")) {
		@symlink("{$g['conf_path']}/mpd_{$interface}.conf", "{$g['varetc_path']}/mpd_{$interface}.conf");
	} else {
		$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
		if (!$fd) {
			log_error(sprintf(gettext('Error: cannot open mpd_%1$s.conf in interface_ppps_configure().%2$s'), $interface, "\n"));
			return 0;
		}
		// Write out mpd_ppp.conf
		fwrite($fd, $mpdconf);
		fclose($fd);
		unset($mpdconf);
	}

	// Create the uptime log if requested and if it doesn't exist already, or delete it if it is no longer requested.
	if (isset($ppp['uptime'])) {
		if (!file_exists("/conf/{$pppif}.log")) {
			file_put_contents("/conf/{$pppif}.log", '');
		}
	} else {
		if (file_exists("/conf/{$pppif}.log")) {
			@unlink("/conf/{$pppif}.log");
		}
	}

	/* clean up old lock files */
	foreach ($ports as $port) {
		if (file_exists("{$g['var_path']}/spool/lock/LCK..{$port}")) {
			unlink("{$g['var_path']}/spool/lock/LCK..{$port}");
		}
	}

	/* Set temporary bogon (RFC 5737) IPv4 addresses to work round mpd5 IPv6CP issue causing */
	/* random IPv6 interface identifier during boot. More details at */
	/* https://forum.netgate.com/post/592474 */
	if (platform_booting() && is_array($config['interfaces'])) {
		$count = 0;
		foreach ($config['interfaces'] as $tempifacename => $tempiface) {
			if ((isset($tempiface['if'])) && (isset($tempiface['ipaddr']) || isset($tempiface['ipaddrv6'])) && !interface_isppp_type($tempifacename)) {
				$tempaddr[$count]['if'] = $tempiface['if'];
				$tempaddr[$count]['ipaddr'] = '192.0.2.' . strval (10 + ($count * 2)) . '/31';
				mwexec('/sbin/ifconfig ' . escapeshellarg($tempaddr[$count]['if']) . ' inet ' . escapeshellarg($tempaddr[$count]['ipaddr']) . ' alias', true);
				$count++;
			}
			// Maximum /31 is is x.y.z.254/31
			if ($count > 122) {
				break;
			}
		}
		unset($count);
	}

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd5 -b -k -d {$g['varetc_path']} -f mpd_{$interface}.conf -p " .
		escapeshellarg("{$g['varrun_path']}/{$ppp['type']}_{$interface}.pid") . " -s ppp " .
		escapeshellarg("{$ppp['type']}client"));

	// Check for PPPoE periodic reset request
	if ($type == "pppoe") {
		if (!empty($ppp['pppoe-reset-type'])) {
			interface_setup_pppoe_reset_file($ppp['if'], $interface);
		} else {
			interface_setup_pppoe_reset_file($ppp['if']);
		}
	}
	/* wait for upto 30 seconds for the interface to appear (ppp(oe)) */
	$i = 0;
	while ($i < 10) {
		if (does_interface_exist($ppp['if'], true)) {
			break;
		}
		sleep(3);
		$i++;
	}

	/* Remove all temporary bogon IPv4 addresses */
	if (is_array($tempaddr)) {
		foreach ($tempaddr as $tempiface) {
			if (isset($tempiface['if']) && isset($tempiface['ipaddr'])) {
				mwexec('/sbin/ifconfig ' . escapeshellarg($tempiface['if']) . ' inet ' . escapeshellarg($tempiface['ipaddr']) . ' -alias', true);
			}
		}
		unset ($tempaddr);
	}

	/* we only support the 3gstats.php for huawei modems for now. Will add more later. */
	/* We should be able to launch the right version for each modem */
	/* We can also guess the mondev from the manufacturer */
	exec("/usr/sbin/usbconfig | /usr/bin/egrep -ie '(huawei)'", $usbmodemoutput);
	mwexec("/bin/ps auxww | /usr/bin/grep \"{$interface}\" | /usr/bin/grep \"[3]gstats\" | /usr/bin/awk '{print $2}' | /usr/bin/xargs kill");
	foreach ($ports as $port) {
		if (preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
			$mondev = substr(basename($port), 0, -1);
			$devlist = glob("/dev/{$mondev}?");
			$mondev = basename(end($devlist));
		}
		if (preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
			$mondev = substr(basename($port), 0, -1) . "1";
		}
		if ($mondev != '') {
			log_error(sprintf(gettext('Starting 3gstats.php on device \'%1$s\' for interface \'%2$s\''), $mondev, $interface));
			mwexec_bg("/usr/local/bin/3gstats.php {$mondev} {$interface}");
		}
	}

	return 1;
}

function interfaces_sync_setup() {
	global $g, $config;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_sync_setup() being called $mt\n";
	}

	if (platform_booting()) {
		echo gettext("Configuring CARP settings...");
		mute_kernel_msgs();
	}

	/* suck in configuration items */
	if ($config['hasync']) {
		$pfsyncenabled = $config['hasync']['pfsyncenabled'];
		$pfsyncinterface = $config['hasync']['pfsyncinterface'];
		$pfsyncpeerip = $config['hasync']['pfsyncpeerip'];
	}

	set_sysctl(array(
		"net.inet.carp.preempt" => "1",
		"net.inet.carp.log" => "1")
	);

	if (!empty($pfsyncinterface)) {
		$carp_sync_int = get_real_interface($pfsyncinterface);
	}

	/* setup pfsync interface */
	if (isset($carp_sync_int) and isset($pfsyncenabled)) {
		if (is_ipaddr($pfsyncpeerip)) {
			$syncpeer = "syncpeer {$pfsyncpeerip}";
		} else {
			$syncpeer = "-syncpeer";
		}

		mwexec("/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} " .
		    "{$syncpeer} up");
		mwexec("/sbin/ifconfig pfsync0 -defer");

		/*
		 * XXX: Handle an issue with pfsync(4) and carp(4). In a
		 * cluster carp will come up before pfsync(4) has updated and
		 * so will cause issues for existing sessions.
		 */
		log_error(gettext("waiting for pfsync..."));

		$i = 0;
		do {
			sleep(1);
			$_gb = exec('/sbin/ifconfig pfsync0 | ' .
			    '/usr/bin/grep -q "syncok: 0" 2>/dev/null', $output,
			    $rc);
			$i++;
		} while ($rc != 0 && $i <= 30);

		log_error(sprintf(gettext("pfsync done in %s seconds."), $i));
		log_error(gettext("Configuring CARP settings finalize..."));
	} else {
		mwexec("/sbin/ifconfig pfsync0 -syncdev -syncpeer down");
	}

	$carplist = get_configured_vip_list('all', VIP_CARP);
	if (is_array($carplist) && count($carplist) > 0) {
		set_single_sysctl("net.inet.carp.allow", "1");
	} else {
		set_single_sysctl("net.inet.carp.allow", "0");
	}

	if (platform_booting()) {
		unmute_kernel_msgs();
		echo gettext("done.") . "\n";
	}
}

function interface_proxyarp_configure($interface = "") {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interface_proxyarp_configure() being called $mt\n";
	}

	/* kill any running choparp */
	if (empty($interface)) {
		killbyname("choparp");
	} else {
		$vipif = get_real_interface($interface);
		if (file_exists("{$g['varrun_path']}/choparp_{$vipif}.pid")) {
			killbypid("{$g['varrun_path']}/choparp_{$vipif}.pid");
		}
	}

	$paa = array();
	if (!empty($config['virtualip']) && is_array($config['virtualip']['vip'])) {

		/* group by interface */
		foreach ($config['virtualip']['vip'] as $vipent) {
			if ($vipent['mode'] === "proxyarp") {
				if ($vipent['interface']) {
					$proxyif = $vipent['interface'];
				} else {
					$proxyif = "wan";
				}

				if (!empty($interface) && $interface != $proxyif) {
					continue;
				}

				if (!is_array($paa[$proxyif])) {
					$paa[$proxyif] = array();
				}

				$paa[$proxyif][] = $vipent;
			}
		}
	}

	if (!empty($interface)) {
		if (is_array($paa[$interface])) {
			$paaifip = get_interface_ip($interface);
			if (!is_ipaddr($paaifip)) {
				return;
			}
			$vipif = get_real_interface($interface);
			$args  = "-p {$g['varrun_path']}/choparp_{$vipif}.pid ";
			$args .= $vipif . " auto";
			foreach ($paa[$interface] as $paent) {
				if (isset($paent['subnet'])) {
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				} elseif (isset($paent['range'])) {
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" . $paent['range']['to']);
				}
			}
			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	} elseif (count($paa) > 0) {
		foreach ($paa as $paif => $paents) {
			$paaifip = get_interface_ip($paif);
			if (!is_ipaddr($paaifip)) {
				continue;
			}
			$vipif = get_real_interface($paif);
			$args  = "-p {$g['varrun_path']}/choparp_{$vipif}.pid ";
			$args .= $vipif . " auto";
			foreach ($paents as $paent) {
				if (isset($paent['subnet'])) {
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				} elseif (isset($paent['range'])) {
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" . $paent['range']['to']);
				}
			}
			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	}
}

function interface_vip_cleanup($interface, $inet = "all", $type = VIP_ALL) {
	global $g, $config;

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {

			$iface = $vip['interface'];
			if (substr($iface, 0, 4) == "_vip")
				$iface = get_configured_vip_interface($vip['interface']);
			if ($iface != $interface)
				continue;
			if ($type == VIP_CARP) {
				if ($vip['mode'] != "carp")
					continue;
			} elseif ($type == VIP_IPALIAS) {
				if ($vip['mode'] != "ipalias")
					continue;
			} else {
				if ($vip['mode'] != "carp" && $vip['mode'] != "ipalias")
					continue;
			}

			if ($inet == "inet6" && is_ipaddrv6($vip['subnet']))
				interface_vip_bring_down($vip);
			elseif ($inet == "inet4" && is_ipaddrv4($vip['subnet']))
				interface_vip_bring_down($vip);
			elseif ($inet == "all")
				interface_vip_bring_down($vip);
		}
	}
}

function interfaces_vips_configure($interface = "") {
	global $g, $config;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_vips_configure() being called $mt\n";
	}

	if (!is_array($config['virtualip']['vip'])) {
		return;
	}

	$carp_setuped = false;
	$anyproxyarp = false;
	foreach ($config['virtualip']['vip'] as $vip) {
		if ($interface <> "" &&
		    get_root_interface($vip['interface']) <> $interface) {
			continue;
		}
		switch ($vip['mode']) {
			case "proxyarp":
				/*
				 * nothing it is handled on
				 * interface_proxyarp_configure()
				 */
				$anyproxyarp = true;
				break;
			case "ipalias":
				interface_ipalias_configure($vip);
				break;
			case "carp":
				if ($carp_setuped == false) {
					$carp_setuped = true;
				}
				interface_carp_configure($vip);
				break;
		}
	}
	if ($carp_setuped == true) {
		interfaces_sync_setup();
	}
	if ($anyproxyarp == true) {
		interface_proxyarp_configure();
	}
}

function interface_ipalias_configure(&$vip) {
	global $config;

	$gateway = '';
	if ($vip['mode'] != 'ipalias') {
		return;
	}

	$realif = get_real_interface("_vip{$vip['uniqid']}");
	if ($realif != "lo0") {
		$if = convert_real_interface_to_friendly_interface_name($realif);
		if (!isset($config['interfaces'][$if]) ||
		    !isset($config['interfaces'][$if]['enable'])) {
			return;
		}
		if (is_pseudo_interface($realif)) {
			if (is_ipaddrv4($vip['subnet'])) {
				$gateway = get_interface_gateway($if);
			} else {
				$gateway = get_interface_gateway_v6($if);
			}
		}
	}

	$af = 'inet';
	if (is_ipaddrv6($vip['subnet'])) {
		$af = 'inet6';
	}
	$iface = $vip['interface'];
	$vhid = '';
	if (substr($vip['interface'], 0, 4) == "_vip") {
		$carpvip = get_configured_vip($vip['interface']);
		$iface = $carpvip['interface'];
		$vhid = "vhid {$carpvip['vhid']}";
	}
	mwexec("/sbin/ifconfig " . escapeshellarg($realif) ." {$af} ". escapeshellarg($vip['subnet']) ."/" . escapeshellarg($vip['subnet_bits']) . " alias {$gateway} {$vhid}");
	unset($iface, $af, $realif, $carpvip, $vhid, $gateway);
}

function interface_carp_configure(&$vip, $maintenancemode_only = false) {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interface_carp_configure() being called $mt\n";
	}

	if ($vip['mode'] != "carp") {
		return;
	}

	$realif = get_real_interface($vip['interface']);
	if (!does_interface_exist($realif)) {
		file_notice("CARP", sprintf(gettext(
		    "Interface specified for the virtual IP address %s does not exist. Skipping this VIP."),
		    $vip['subnet']), "Firewall: Virtual IP", "");
		return;
	}
	if ($realif != "lo0") {
		if (!isset($config['interfaces'][$vip['interface']]) ||
		    !isset($config['interfaces'][$vip['interface']]['enable'])) {
			return;
		}
	}

	$vip_password = $vip['password'];
	$vip_password = escapeshellarg(addslashes(str_replace(" ", "",
	    $vip_password)));
	if ($vip['password'] != "") {
		$password = " pass {$vip_password}";
	}

	$advbase = "";
	if (!empty($vip['advbase'])) {
		$advbase = "advbase " . escapeshellarg($vip['advbase']);
	}

	$carp_maintenancemode = isset(
	    $config["virtualip_carp_maintenancemode"]);
	if ($carp_maintenancemode) {
		$advskew = "advskew 254";
	} else {
		$advskew = "advskew " . escapeshellarg($vip['advskew']);
	}

	mwexec("/sbin/ifconfig {$realif} vhid " . escapeshellarg($vip['vhid']) .
	    " {$advskew} {$advbase} {$password}");

	if (!$maintenancemode_only) {
		if (is_ipaddrv4($vip['subnet'])) {
			mwexec("/sbin/ifconfig {$realif} " .
			    escapeshellarg($vip['subnet']) . "/" .
			    escapeshellarg($vip['subnet_bits']) .
			    " alias vhid " . escapeshellarg($vip['vhid']));
		} elseif (is_ipaddrv6($vip['subnet'])) {
			mwexec("/sbin/ifconfig {$realif} inet6 " .
			    escapeshellarg($vip['subnet']) . " prefixlen " .
			    escapeshellarg($vip['subnet_bits']) .
			    " alias vhid " . escapeshellarg($vip['vhid']));
		}
	}

	return $realif;
}

function interface_wireless_clone($realif, $wlcfg) {
	global $config, $g;
	/*   Check to see if interface has been cloned as of yet.
	 *   If it has not been cloned then go ahead and clone it.
	 */
	$needs_clone = false;
	if (is_array($wlcfg['wireless'])) {
		$wlcfg_mode = $wlcfg['wireless']['mode'];
	} else {
		$wlcfg_mode = $wlcfg['mode'];
	}
	switch ($wlcfg_mode) {
		case "hostap":
			$mode = "wlanmode hostap";
			break;
		case "adhoc":
			$mode = "wlanmode adhoc";
			break;
		default:
			$mode = "";
			break;
	}
	$baseif = interface_get_wireless_base($wlcfg['if']);
	if (does_interface_exist($realif)) {
		exec("/sbin/ifconfig " . escapeshellarg($realif), $output, $ret);
		$ifconfig_str = implode($output);
		if (($wlcfg_mode == "hostap") && (!preg_match("/hostap/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to hostap mode"), $realif));
			$needs_clone = true;
		}
		if (($wlcfg_mode == "adhoc") && (!preg_match("/adhoc/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to adhoc mode"), $realif));
			$needs_clone = true;
		}
		if (($wlcfg_mode == "bss") && (preg_match("/hostap|adhoc/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to infrastructure mode"), $realif));
			$needs_clone = true;
		}
	} else {
		$needs_clone = true;
	}

	if ($needs_clone == true) {
		/* remove previous instance if it exists */
		if (does_interface_exist($realif)) {
			pfSense_interface_destroy($realif);

			/* Invalidate cache */
			get_interface_arr(true);
		}

		log_error(sprintf(gettext("Cloning new wireless interface %s"), $realif));
		// Create the new wlan interface. FreeBSD returns the new interface name.
		// example:  wlan2
		exec("/sbin/ifconfig wlan create wlandev {$baseif} {$mode} bssid 2>&1", $out, $ret);
		if ($ret <> 0) {
			log_error(sprintf(gettext('Failed to clone interface %1$s with error code %2$s, output %3$s'), $baseif, $ret, $out[0]));
			return false;
		}
		$newif = trim($out[0]);
		// Rename the interface to {$parentnic}_wlan{$number}#: EX: ath0_wlan0
		pfSense_interface_rename($newif, $realif);
		file_put_contents("{$g['tmp_path']}/{$realif}_oldmac", get_interface_mac($realif));
	}
	return true;
}

function interface_sync_wireless_clones(&$ifcfg, $sync_changes = false) {
	global $config, $g;

	$shared_settings = array('standard', 'turbo', 'protmode', 'txpower', 'channel',
				 'diversity', 'txantenna', 'rxantenna', 'distance',
				 'regdomain', 'regcountry', 'reglocation');

	if (!is_interface_wireless($ifcfg['if'])) {
		return;
	}

	$baseif = interface_get_wireless_base($ifcfg['if']);

	// Sync shared settings for assigned clones
	$iflist = get_configured_interface_list(true);
	foreach ($iflist as $if) {
		if ($baseif == interface_get_wireless_base($config['interfaces'][$if]['if']) && $ifcfg['if'] != $config['interfaces'][$if]['if']) {
			if (isset($config['interfaces'][$if]['wireless']['standard']) || $sync_changes) {
				foreach ($shared_settings as $setting) {
					if ($sync_changes) {
						if (isset($ifcfg['wireless'][$setting])) {
							$config['interfaces'][$if]['wireless'][$setting] = $ifcfg['wireless'][$setting];
						} elseif (isset($config['interfaces'][$if]['wireless'][$setting])) {
							unset($config['interfaces'][$if]['wireless'][$setting]);
						}
					} else {
						if (isset($config['interfaces'][$if]['wireless'][$setting])) {
							$ifcfg['wireless'][$setting] = $config['interfaces'][$if]['wireless'][$setting];
						} elseif (isset($ifcfg['wireless'][$setting])) {
							unset($ifcfg['wireless'][$setting]);
						}
					}
				}
				if (!$sync_changes) {
					break;
				}
			}
		}
	}

	// Read or write settings at shared area
	if (isset($config['wireless']['interfaces'][$baseif]) && is_array($config['wireless']['interfaces'][$baseif])) {
		foreach ($shared_settings as $setting) {
			if ($sync_changes) {
				if (isset($ifcfg['wireless'][$setting])) {
					$config['wireless']['interfaces'][$baseif][$setting] = $ifcfg['wireless'][$setting];
				} elseif (isset($config['wireless']['interfaces'][$baseif][$setting])) {
					unset($config['wireless']['interfaces'][$baseif][$setting]);
				}
			} elseif (isset($config['wireless']['interfaces'][$baseif][$setting])) {
				if (isset($config['wireless']['interfaces'][$baseif][$setting])) {
					$ifcfg['wireless'][$setting] = $config['wireless']['interfaces'][$baseif][$setting];
				} elseif (isset($ifcfg['wireless'][$setting])) {
					unset($ifcfg['wireless'][$setting]);
				}
			}
		}
	}

	// Sync the mode on the clone creation page with the configured mode on the interface
	if (interface_is_wireless_clone($ifcfg['if']) && isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as &$clone) {
			if ($clone['cloneif'] == $ifcfg['if']) {
				if ($sync_changes) {
					$clone['mode'] = $ifcfg['wireless']['mode'];
				} else {
					$ifcfg['wireless']['mode'] = $clone['mode'];
				}
				break;
			}
		}
		unset($clone);
	}
}

function interface_wireless_configure($if, &$wl, &$wlcfg) {
	global $config, $g;

	/*    open up a shell script that will be used to output the commands.
	 *    since wireless is changing a lot, these series of commands are fragile
	 *    and will sometimes need to be verified by a operator by executing the command
	 *    and returning the output of the command to the developers for inspection.  please
	 *    do not change this routine from a shell script to individual exec commands.  -sullrich
	 */

	// Remove script file
	unlink_if_exists("{$g['tmp_path']}/{$if}_setup.sh");

	// Clone wireless nic if needed.
	interface_wireless_clone($if, $wl);

	// Reject inadvertent changes to shared settings in case the interface hasn't been configured.
	interface_sync_wireless_clones($wl, false);

	$fd_set = fopen("{$g['tmp_path']}/{$if}_setup.sh", "w");
	fwrite($fd_set, "#!/bin/sh\n");
	fwrite($fd_set, "# {$g['product_label']} wireless configuration script.\n\n");

	$wlan_setup_log = fopen("{$g['tmp_path']}/{$if}_setup.log", "w");

	/* set values for /path/program */
	if (file_exists("/usr/local/sbin/hostapd")) {
		$hostapd = "/usr/local/sbin/hostapd";
	} else {
		$hostapd = "/usr/sbin/hostapd";
	}
	if (file_exists("/usr/local/sbin/wpa_supplicant")) {
		$wpa_supplicant = "/usr/local/sbin/wpa_supplicant";
	} else {
		$wpa_supplicant = "/usr/sbin/wpa_supplicant";
	}
	$ifconfig = "/sbin/ifconfig";
	$sysctl = "/sbin/sysctl";
	$sysctl_args = "-q";
	$killall = "/usr/bin/killall";

	/* Set all wireless ifconfig variables (split up to get rid of needed checking) */

	$wlcmd = array();
	$wl_sysctl = array();
	/* Set a/b/g standard */
	$standard = str_replace(" Turbo", "", $wlcfg['standard']);
	/* skip mode entirely for "auto" */
	if ($wlcfg['standard'] != "auto") {
		$wlcmd[] = "mode " . escapeshellarg($standard);
	}

	/* XXX: Disable ampdu for now on mwl when running in 11n mode
	 * to prevent massive packet loss under certain conditions. */
	if (preg_match("/^mwl/i", $if) && ($standard == "11ng" || $standard == "11na")) {
		$wlcmd[] = "-ampdu";
	}

	/* Set ssid */
	if ($wlcfg['ssid']) {
		$wlcmd[] = "ssid " .escapeshellarg($wlcfg['ssid']);
	}

	/* Set 802.11g protection mode */
	$wlcmd[] = "protmode " . escapeshellarg($wlcfg['protmode']);

	/* set wireless channel value */
	if (isset($wlcfg['channel'])) {
		if ($wlcfg['channel'] == "0") {
			$wlcmd[] = "channel any";
		} else {
			if ($wlcfg['channel_width'] != "0") {
				$channel_width = ":" . $wlcfg['channel_width'];
			} else {
				$channel_width = '';
			}
			$wlcmd[] = "channel " . escapeshellarg($wlcfg['channel']) . $channel_width;
		}
	}

	/* Set antenna diversity value */
	if (isset($wlcfg['diversity'])) {
		$wl_sysctl[] = "diversity=" . escapeshellarg($wlcfg['diversity']);
	}

	/* Set txantenna value */
	if (isset($wlcfg['txantenna'])) {
		$wl_sysctl[] = "txantenna=" . escapeshellarg($wlcfg['txantenna']);
	}

	/* Set rxantenna value */
	if (isset($wlcfg['rxantenna'])) {
		$wl_sysctl[] = "rxantenna=" . escapeshellarg($wlcfg['rxantenna']);
	}

	/* set Distance value */
	if ($wlcfg['distance']) {
		$distance = escapeshellarg($wlcfg['distance']);
	}

	/* Set wireless hostap mode */
	if ($wlcfg['mode'] == "hostap") {
		$wlcmd[] = "mediaopt hostap";
	} else {
		$wlcmd[] = "-mediaopt hostap";
	}

	/* Set wireless adhoc mode */
	if ($wlcfg['mode'] == "adhoc") {
		$wlcmd[] = "mediaopt adhoc";
	} else {
		$wlcmd[] = "-mediaopt adhoc";
	}

	/* Not necessary to set BSS mode as this is default if adhoc and/or hostap is NOT set */

	/* handle hide ssid option */
	if (isset($wlcfg['hidessid']['enable'])) {
		$wlcmd[] = "hidessid";
	} else {
		$wlcmd[] = "-hidessid";
	}

	/* handle pureg (802.11g) only option */
	if (isset($wlcfg['pureg']['enable'])) {
		$wlcmd[] = "mode 11g pureg";
	} else {
		$wlcmd[] = "-pureg";
	}

	/* handle puren (802.11n) only option */
	if (isset($wlcfg['puren']['enable'])) {
		$wlcmd[] = "puren";
	} else {
		$wlcmd[] = "-puren";
	}

	/* enable apbridge option */
	if (isset($wlcfg['apbridge']['enable'])) {
		$wlcmd[] = "apbridge";
	} else {
		$wlcmd[] = "-apbridge";
	}

	/* handle turbo option */
	if (isset($wlcfg['turbo']['enable'])) {
		$wlcmd[] = "mediaopt turbo";
	} else {
		$wlcmd[] = "-mediaopt turbo";
	}

	/* handle txpower setting */
	// or don't. this has issues at the moment.
	/*
	if ($wlcfg['txpower'] <> "" && is_numeric($wlcfg['txpower'])) {
		$wlcmd[] = "txpower " . escapeshellarg($wlcfg['txpower']);
	}*/

	/* handle wme option */
	if (isset($wlcfg['wme']['enable'])) {
		$wlcmd[] = "wme";
	} else {
		$wlcmd[] = "-wme";
	}

	/* Enable wpa if it's configured. No WEP support anymore. */
	if (isset($wlcfg['wpa']['enable'])) {
		$wlcmd[] = "authmode wpa wepmode off ";
	} else {
		$wlcmd[] = "authmode open wepmode off ";
	}

	kill_hostapd($if);
	mwexec(kill_wpasupplicant("{$if}"));

	$wpa_supplicant_file = "{$g['varetc_path']}/wpa_supplicant_{$if}.";
	$hostapd_conf = "{$g['varetc_path']}/hostapd_{$if}.conf";

	unlink_if_exists("{$wpa_supplicant_file}*");
	unlink_if_exists($hostapd_conf);

	/* generate wpa_supplicant/hostap config if wpa is enabled */

	switch ($wlcfg['mode']) {
		case 'bss':
			if (isset($wlcfg['wpa']['enable'])) {
				$wpa .= <<<EOD
ctrl_interface={$g['varrun_path']}/wpa_supplicant
ctrl_interface_group=0
ap_scan=1
#fast_reauth=1
network={
ssid="{$wlcfg['ssid']}"
scan_ssid=1
priority=5
key_mgmt={$wlcfg['wpa']['wpa_key_mgmt']}
pairwise={$wlcfg['wpa']['wpa_pairwise']}
group={$wlcfg['wpa']['wpa_pairwise']}

EOD;
				if ($wlcfg['wpa']['wpa_key_mgmt'] == 'WPA-EAP') {
					if (($wlcfg['wpa']['wpa_eap_client_mode'] == 'PEAP') ||
					    ($wlcfg['wpa']['wpa_eap_client_mode'] == 'TTLS')) {
						if ($wlcfg['wpa']['wpa_eap_inner_auth'] == 'mschapv2') {
							$wpa .= "phase1=\"peaplabel=0\"\n";
							$wpa .= "phase2=\"auth=MSCHAPV2\"\n";
						} else {
							$wpa .= "phase2=\"auth=MD5\"\n";
						}
						$wpa .= "identity=\"{$wlcfg['wpa']['wpa_eap_inner_id']}\"\n";
						$eappass = base64_decode($wlcfg['wpa']['wpa_eap_inner_password']);
						$wpa .= "password=\"{$eappass}\"\n";
					}
					if (strstr($wlcfg['wpa']['wpa_eap_client_mode'], 'TLS')) { 
						$cert = lookup_cert($wlcfg['wpa']['wpa_eap_cert']);
						@file_put_contents($wpa_supplicant_file . "crt", base64_decode($cert['crt']) . "\n" .
						    ca_chain($cert)); 
						@file_put_contents($wpa_supplicant_file . "key", base64_decode($cert['prv'])); 
						@chmod($wpa_supplicant_crt, 0600);
						@chmod($wpa_supplicant_key, 0600);
						$wpa .= "client_cert=\"{$wpa_supplicant_crt}\"\n";
						$wpa .= "private_key=\"{$wpa_supplicant_key}\"\n";
					}
					$ca = lookup_ca($wlcfg['wpa']['wpa_eap_ca']);
					@file_put_contents($wpa_supplicant_file . "ca", base64_decode($ca['crt']) . "\n" .
					    ca_chain($ca)); 
					$wpa .= "ca_cert=\"{$wpa_supplicant_ca}\"\n";
					$wpa .= "eap={$wlcfg['wpa']['wpa_eap_client_mode']}\n";
				} else {
					$wpa .= "psk=\"{$wlcfg['wpa']['passphrase']}\"\n";
				}
				$wpa .= "}\n";

				@file_put_contents($wpa_supplicant_file . "conf", $wpa);
				unset($wpa);
			}
			break;
		case 'hostap':
			if (!empty($wlcfg['wpa']['passphrase'])) {
				$wpa_passphrase = "wpa_passphrase={$wlcfg['wpa']['passphrase']}\n";
			} else {
				$wpa_passphrase = "";
			}
			if (isset($wlcfg['wpa']['enable'])) {
				$wpa .= <<<EOD
interface={$if}
driver=bsd
logger_syslog=-1
logger_syslog_level=0
logger_stdout=-1
logger_stdout_level=0
dump_file={$g['tmp_path']}/hostapd_{$if}.dump
ctrl_interface={$g['varrun_path']}/hostapd
ctrl_interface_group=wheel
#accept_mac_file={$g['tmp_path']}/hostapd_{$if}.accept
#deny_mac_file={$g['tmp_path']}/hostapd_{$if}.deny
#macaddr_acl={$wlcfg['wpa']['macaddr_acl']}
ssid={$wlcfg['ssid']}
debug={$wlcfg['wpa']['debug_mode']}
wpa={$wlcfg['wpa']['wpa_mode']}
wpa_key_mgmt={$wlcfg['wpa']['wpa_key_mgmt']}
wpa_pairwise={$wlcfg['wpa']['wpa_pairwise']}
wpa_group_rekey={$wlcfg['wpa']['wpa_group_rekey']}
wpa_gmk_rekey={$wlcfg['wpa']['wpa_gmk_rekey']}
wpa_strict_rekey={$wlcfg['wpa']['wpa_strict_rekey']}
{$wpa_passphrase}

EOD;

				if (isset($wlcfg['wpa']['rsn_preauth'])) {
					$wpa .= <<<EOD
# Enable the next lines for preauth when roaming. Interface = wired or wireless interface talking to the AP to roam from/to
rsn_preauth=1
rsn_preauth_interfaces={$if}

EOD;
				}
				if (is_array($wlcfg['wpa']['ieee8021x']) && isset($wlcfg['wpa']['ieee8021x']['enable'])) {
					$wpa .= "ieee8021x=1\n";

					if (!empty($wlcfg['auth_server_addr']) && !empty($wlcfg['auth_server_shared_secret'])) {
						$auth_server_port = "1812";
						if (!empty($wlcfg['auth_server_port']) && is_numeric($wlcfg['auth_server_port'])) {
							$auth_server_port = intval($wlcfg['auth_server_port']);
						}
						$wpa .= <<<EOD

auth_server_addr={$wlcfg['auth_server_addr']}
auth_server_port={$auth_server_port}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret']}

EOD;
						if (!empty($wlcfg['auth_server_addr2']) && !empty($wlcfg['auth_server_shared_secret2'])) {
							$auth_server_port2 = "1812";
							if (!empty($wlcfg['auth_server_port2']) && is_numeric($wlcfg['auth_server_port2'])) {
								$auth_server_port2 = intval($wlcfg['auth_server_port2']);
							}

							$wpa .= <<<EOD
auth_server_addr={$wlcfg['auth_server_addr2']}
auth_server_port={$auth_server_port2}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret2']}

EOD;
						}
					}
				}

				@file_put_contents($hostapd_conf, $wpa);
				unset($wpa);
			}
			break;
	}

	/*
	 *    all variables are set, lets start up everything
	 */

	$baseif = interface_get_wireless_base($if);
	preg_match("/^(.*?)([0-9]*)$/", $baseif, $baseif_split);
	$wl_sysctl_prefix = 'dev.' . $baseif_split[1] . '.' . $baseif_split[2];

	/* set sysctls for the wireless interface */
	if (!empty($wl_sysctl)) {
		fwrite($fd_set, "# sysctls for {$baseif}\n");
		foreach ($wl_sysctl as $wl_sysctl_line) {
			fwrite($fd_set, "{$sysctl} ${sysctl_args} {$wl_sysctl_prefix}.{$wl_sysctl_line}\n");
		}
	}

	/* set ack timers according to users preference (if he/she has any) */
	if ($distance) {
		fwrite($fd_set, "# Enable ATH distance settings\n");
		fwrite($fd_set, "/usr/local/sbin/athctrl.sh -i {$baseif} -d {$distance}\n");
	}

	if (isset($wlcfg['wpa']['enable'])) {
		if ($wlcfg['mode'] == "bss") {
			fwrite($fd_set, "{$wpa_supplicant} -B -i {$if} -c {$g['varetc_path']}/wpa_supplicant_{$if}.conf\n");
		}
		if ($wlcfg['mode'] == "hostap") {
			/* add line to script to restore old mac to make hostapd happy */
			if (file_exists("{$g['tmp_path']}/{$if}_oldmac")) {
				$if_oldmac = file_get_contents("{$g['tmp_path']}/{$if}_oldmac");
				$if_curmac = get_interface_mac($if);
				if ($if_curmac != $if_oldmac && is_macaddr($if_oldmac)) {
					fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
						" link " . escapeshellarg($if_oldmac) . "\n");
				}
			}

			fwrite($fd_set, "{$hostapd} -B -P {$g['varrun_path']}/hostapd_{$if}.pid {$g['varetc_path']}/hostapd_{$if}.conf\n");

			/* add line to script to restore spoofed mac after running hostapd */
			if ($wl['spoofmac']) {
				$if_curmac = get_interface_mac($if);
				if ($wl['spoofmac'] != $if_curmac && is_macaddr($wl['spoofmac'])) {
					fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
						" link " . escapeshellarg($wl['spoofmac']) . "\n");
				}
			}
		}
	}

	fclose($fd_set);

	/* Making sure regulatory settings have actually changed
	 * before applying, because changing them requires bringing
	 * down all wireless networks on the interface. */
	exec("{$ifconfig} " . escapeshellarg($if), $output);
	$ifconfig_str = implode($output);
	unset($output);
	$reg_changing = false;

	/* special case for the debug country code */
	if ($wlcfg['regcountry'] == 'DEBUG' && !preg_match("/\sregdomain\s+DEBUG\s/si", $ifconfig_str)) {
		$reg_changing = true;
	} elseif ($wlcfg['regdomain'] && !preg_match("/\sregdomain\s+{$wlcfg['regdomain']}\s/si", $ifconfig_str)) {
		$reg_changing = true;
	} elseif ($wlcfg['regcountry'] && !preg_match("/\scountry\s+{$wlcfg['regcountry']}\s/si", $ifconfig_str)) {
		$reg_changing = true;
	} elseif ($wlcfg['reglocation'] == 'anywhere' && preg_match("/\s(indoor|outdoor)\s/si", $ifconfig_str)) {
		$reg_changing = true;
	} elseif ($wlcfg['reglocation'] && $wlcfg['reglocation'] != 'anywhere' && !preg_match("/\s{$wlcfg['reglocation']}\s/si", $ifconfig_str)) {
		$reg_changing = true;
	}

	if ($reg_changing) {
		/* set regulatory domain */
		if ($wlcfg['regdomain']) {
			$wlregcmd[] = "regdomain " . escapeshellarg($wlcfg['regdomain']);
		}

		/* set country */
		if ($wlcfg['regcountry']) {
			$wlregcmd[] = "country " . escapeshellarg($wlcfg['regcountry']);
		}

		/* set location */
		if ($wlcfg['reglocation']) {
			$wlregcmd[] = escapeshellarg($wlcfg['reglocation']);
		}

		$wlregcmd_args = implode(" ", $wlregcmd);

		/* build a complete list of the wireless clones for this interface */
		$clone_list = array();
		if (does_interface_exist(interface_get_wireless_clone($baseif))) {
			$clone_list[] = interface_get_wireless_clone($baseif);
		}
		if (isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
			foreach ($config['wireless']['clone'] as $clone) {
				if ($clone['if'] == $baseif) {
					$clone_list[] = $clone['cloneif'];
				}
			}
		}

		/* find which clones are up and bring them down */
		$clones_up = array();
		foreach ($clone_list as $clone_if) {
			$clone_status = pfSense_get_interface_addresses($clone_if);
			if ($clone_status['status'] == 'up') {
				$clones_up[] = $clone_if;
				mwexec("{$ifconfig} " . escapeshellarg($clone_if) . " down");
			}
		}

		/* apply the regulatory settings */
		mwexec("{$ifconfig} " . escapeshellarg($if) . " {$wlregcmd_args}");
		fwrite($wlan_setup_log, "$ifconfig" . escapeshellarg($if) . "$wlregcmd_args \n");

		/* bring the clones back up that were previously up */
		foreach ($clones_up as $clone_if) {
			interfaces_bring_up($clone_if);

			/*
			 * Rerun the setup script for the interface if it isn't this interface, the interface
			 * is in infrastructure mode, and WPA is enabled.
			 * This can be removed if wpa_supplicant stops dying when you bring the interface down.
			 */
			if ($clone_if != $if) {
				$friendly_if = convert_real_interface_to_friendly_interface_name($clone_if);
				if ((!empty($friendly_if)) &&
				    ($config['interfaces'][$friendly_if]['wireless']['mode'] == "bss") &&
				    (isset($config['interfaces'][$friendly_if]['wireless']['wpa']['enable']))) {
					mwexec("/bin/sh {$g['tmp_path']}/" . escapeshellarg($clone_if) . "_setup.sh");
				}
			}
		}
	}

	/* The mode must be specified in a separate command before ifconfig
	 * will allow the mode and channel at the same time in the next.
	 * Only do this for AP mode as this breaks client mode (PR 198680).
	 */
	if ($wlcfg['mode'] == "hostap") {
		mwexec("/sbin/ifconfig " . escapeshellarg($if) . " mode " . escapeshellarg($standard));
		fwrite($wlan_setup_log, "/sbin/ifconfig " . escapeshellarg($if) . " mode " . escapeshellarg($standard) . "\n");
	}

	/* configure wireless */
	$wlcmd_args = implode(" ", $wlcmd);
	mwexec("/sbin/ifconfig " . escapeshellarg($if) . " " . $wlcmd_args);
	fwrite($wlan_setup_log, "/sbin/ifconfig " . escapeshellarg($if) . " " . "$wlcmd_args \n");
	/* Bring the interface up only after setting up all the other parameters. */
	mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up");
	fwrite($wlan_setup_log, "/sbin/ifconfig " . escapeshellarg($if) . " up\n");
	fclose($wlan_setup_log);

	unset($wlcmd_args, $wlcmd);


	sleep(1);
	/* execute hostapd and wpa_supplicant if required in shell */
	mwexec("/bin/sh {$g['tmp_path']}/" . escapeshellarg($if) . "_setup.sh");

	return 0;

}

function kill_hostapd($interface) {
	global $g;

	if (isvalidpid("{$g['varrun_path']}/hostapd_{$interface}.pid")) {
		return killbypid("{$g['varrun_path']}/hostapd_{$interface}.pid");
	}
}

function kill_wpasupplicant($interface) {
	return "/bin/pkill -f \"wpa_supplicant .*{$interface}\\.conf\"\n";
}

function find_dhclient_process($interface) {
	if ($interface) {
		$pid = `/bin/pgrep -axf "dhclient: {$interface}"`;
	} else {
		$pid = 0;
	}

	return intval($pid);
}

function kill_dhclient_process($interface) {
	if (empty($interface) || !does_interface_exist($interface)) {
		return;
	}

	$i = 0;
	while ((($pid = find_dhclient_process($interface)) != 0) && ($i < 3)) {
		/* 3rd time make it die for sure */
		$sig = ($i == 2 ? SIGKILL : SIGTERM);
		posix_kill($pid, $sig);
		sleep(1);
		$i++;
	}
	unset($i);
}

function find_dhcp6c_process($interface) {
	global $g;

	if ($interface && isvalidpid("{$g['varrun_path']}/dhcp6c_{$interface}.pid")) {
		$pid = trim(file_get_contents("{$g['varrun_path']}/dhcp6c_{$interface}.pid"), " \n");
	} else {
		return(false);
	}

	return intval($pid);
}

function kill_dhcp6client_process($interface, $force, $release = false) {
	global $g;

	$i = 0;

	/*
	Beware of the following: Reason, the interface may be down, but
	dhcp6c may still be running, it just complains it cannot send
	and carries on. Commented out as will stop the call to kill.

	if (empty($interface) || !does_interface_exist($interface)) {
		return;
	}
	*/

	/*********** Notes on signals for dhcp6c and this function *************

	If we have Taken the WAN interface down, then dhcp6c sits there sending
	a release and waiting for the response that never comes.
	So we need to tell it that the interface is down and to just die quickly
	otherwise a new client may launch and we have duplicate proceses.
	In this case use SIGUSR1.

	If we want to exit normally obeying the no release flag then use SIGTERM.
	If we want to exit with a release overiding the no release flag then
	use SIGUSR2.

	If $Force is true it will use SIGUSR1, thus forcing dhcp6c to
	exit quickly without sending release signals.

	If $Force is set to false and $release is also set to false dhcp6c will
	follow the no-release flag.

	If $Force is set to false and $release is true then dhcp6c will send a
	release regardless of the no-release flag.
	***********************************************************************/

	if ($force == true) {
		$psig=SIGUSR1;
	} elseif ($release == false) {
		$psig=SIGTERM;
	} else {
		$psig=SIGUSR2;
	}

	while ((($pid = find_dhcp6c_process($interface)) != 0) && ($i < 3)) {
		/* 3rd time make it die for sure */
		$sig = ($i == 2 ? SIGKILL : $psig);
		posix_kill($pid, $sig);
		sleep(1);
		$i++;
	}
	/* Clear the RTSOLD script created lock & tidy up */
	unlink_if_exists("/tmp/dhcp6c_{$interface}_lock");
	unlink_if_exists("{$g['varrun_path']}/dhcp6c_{$interface}.pid"); // just in case!
}
function reset_dhcp6client_process($interface) {

	$pid = find_dhcp6c_process($interface);

	if($pid != 0) {
		posix_kill($pid, SIGHUP);
	}
}

function run_dhcp6client_process($interface, $interface_name, $wancfg) {
	global $g;

	$debugOption = isset($wancfg['dhcp6debug']) ? "-D" : "-d";
	$noreleaseOption = isset($wancfg['dhcp6norelease']) ? "-n" : "";

	/*
	 * Only run this if the lock does not exist. In theory the lock being
	 * there in this mode means the user has selected dhcp6withoutRA while
	 * a session is active in the other mode.
	 *
	 * It should not happen as the process should have been killed and the
	 * lock deleted.
	 */

	if (!file_exists("/tmp/dhcp6c_{$interface}_lock")) {
		kill_dhcp6client_process($interface, true);
		/* Lock it to avoid multiple runs */
		touch("/tmp/dhcp6c_{$interface}_lock");
		mwexec("/usr/local/sbin/dhcp6c {$debugOption} " .
		    "{$noreleaseOption} " .
		    "-c {$g['varetc_path']}/dhcp6c_{$interface_name}.conf " .
		    "-p {$g['varrun_path']}/dhcp6c_{$interface}.pid " .
		    $interface);
		log_error(sprintf(gettext(
		    "Starting dhcp6 client for interface wan %s in DHCP6 without RA mode"),
		    $interface));
	}
}

function interface_virtual_create($interface) {
	global $config;

	if (interface_is_vlan($interface) != NULL) {
		interface_vlan_configure(interface_is_vlan($interface));
	} elseif (substr($interface, 0, 3) == "gre") {
		interfaces_tunnel_configure(0, $interface, 'gre');
	} elseif (substr($interface, 0, 3) == "gif") {
		interfaces_tunnel_configure(0, $interface, 'gif');
	} elseif (substr($interface, 0, 5) == "ovpns") {
		if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as $server) {
				if ($interface == "ovpns{$server['vpnid']}") {
					if (!function_exists('openvpn_resync')) {
						require_once('openvpn.inc');
					}
					log_error(sprintf(gettext("OpenVPN: Resync server %s"), $server['description']));
					openvpn_resync('server', $server);
				}
			}
			unset($server);
		}
	} elseif (substr($interface, 0, 5) == "ovpnc") {
		if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as $client) {
				if ($interface == "ovpnc{$client['vpnid']}") {
					if (!function_exists('openvpn_resync')) {
						require_once('openvpn.inc');
					}
					log_error(sprintf(gettext("OpenVPN: Resync client %s"), $client['description']));
					openvpn_resync('client', $client);
				}
			}
			unset($client);
		}
	} elseif (substr($interface, 0, 5) == "ipsec") {
		if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase1'] as $ph1ent) {
				if ($ph1ent['disabled']) {
					continue;
				}
				if ($interface == "ipsec{$ph1ent['ikeid']}") {
					interface_ipsec_vti_configure($ph1ent);
				}
			}
		}
	} elseif (substr($interface, 0, 4) == "lagg") {
		interfaces_lagg_configure($interface);
	} elseif (substr($interface, 0, 6) == "bridge") {
		interfaces_bridge_configure(0, $interface);
	}
}

function interface_vlan_mtu_configured($iface) {
	global $config;

	$mtu = 0;
	if (is_array($config['vlans']) && is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {

			if ($vlan['vlanif'] != $iface)
				continue;

			$assignedport = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
			$parentinf = convert_real_interface_to_friendly_interface_name($vlan['if']);
			if (!empty($assignedport) && !empty($config['interfaces'][$assignedport]['mtu'])) {
				/* VLAN MTU */
				$mtu = $config['interfaces'][$assignedport]['mtu'];
			} elseif (!empty($config['interfaces'][$parentinf]['mtu'])) {
				/* Parent MTU */
				$mtu = $config['interfaces'][$parentinf]['mtu'];
			}
		}
	}

	return $mtu;
}

function interface_mtu_wanted_for_pppoe($realif) {
	global $config;

	if (!is_array($config['ppps']) || !is_array($config['ppps']['ppp']))
		return 0;

	$mtu = 0;
	foreach ($config['ppps']['ppp'] as $ppp) {
		if ($ppp['type'] != "pppoe") {
			continue;
		}

		$mtus = array();
		if (!empty($ppp['mtu'])) {
			$mtus = explode(',', $ppp['mtu']);
		}
		$ports = explode(',', $ppp['ports']);

		foreach ($ports as $pid => $port) {
			$parentifa = get_parent_interface($port);
			$parentif = $parentifa[0];
			if ($parentif != $realif)
				continue;

			// there is an MTU configured on the port in question
			if (!empty($mtus[$pid])) {
				$mtu = intval($mtus[$pid]) + 8;
			// or use the MTU configured on the interface ...
			} elseif (is_array($config['interfaces'])) {
				foreach ($config['interfaces'] as $interface) {
					if ($interface['if'] == $ppp['if'] &&
					    !empty($interface['mtu'])) {
						$mtu = intval($interface['mtu']) + 8;
						break;
					}
				}
			}
		}
	}

	return $mtu;
}

function interface_configure($interface = "wan", $reloadall = false, $linkupevent = false) {
	global $config, $g;
	global $interface_sn_arr_cache, $interface_ip_arr_cache;
	global $interface_snv6_arr_cache, $interface_ipv6_arr_cache;

	$wancfg = $config['interfaces'][$interface];

	if (!isset($wancfg['enable'])) {
		return;
	}

	if (substr($config['interfaces'][$interface]['if'], 0, 2) == 'wg') {
		require_once('wg.inc');
		wg_configure_if($config['interfaces'][$interface]['if']);
	}

	$realif = get_real_interface($interface);
	$realhwif_array = get_parent_interface($interface);
	// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
	$realhwif = $realhwif_array[0];

	$mac_if_cfg = $wancfg;
	if (interface_is_vlan($realif)) {
		$mac_if = convert_real_interface_to_friendly_interface_name(
		    $realhwif);
		if (is_array($config['interfaces'][$mac_if])) {
			$mac_if_cfg = $config['interfaces'][$mac_if];
		} else {
			$mac_if = $interface;
		}
	}

	if (!platform_booting() &&
	    (substr($realif, 0, 4) != "ovpn") &&
	    (substr($realif, 0, 5) != "ipsec") &&
	    (substr($realif, 0, 2) != "wg")) {
		/* remove all IPv4 and IPv6 addresses */
		$tmpifaces = pfSense_getall_interface_addresses($realif);
		if (is_array($tmpifaces)) {
			foreach ($tmpifaces as $tmpiface) {
				if (is_ipaddrv6($tmpiface) || is_subnetv6($tmpiface)) {
					if (!is_linklocal($tmpiface)) {
						mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$tmpiface} delete");
					}
				} elseif (strstr($tmpiface, "fe80::1:1")) {
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 fe80::1:1 -alias");
				} else {
					if (is_subnetv4($tmpiface)) {
						$tmpip = explode('/', $tmpiface);
						$tmpip = $tmpip[0];
					} else {
						$tmpip = $tmpiface;
					}
					pfSense_interface_deladdress($realif, $tmpip);
				}
			}
		}

		/* only bring down the interface when both v4 and v6 are set to NONE */
		if (empty($wancfg['ipaddr']) && empty($wancfg['ipaddrv6'])) {
			interface_bring_down($interface);
		}
	}

	$interface_to_check = $realif;
	if (interface_isppp_type($interface)) {
		$interface_to_check = $realhwif;
	}

	/* Need to check that the interface exists or not in the case where its coming back from disabled state see #3270 */
	if (!platform_booting() && (in_array(substr($realif, 0, 3), array("gre", "gif", "ips")) || !does_interface_exist($interface_to_check))) {
		interface_virtual_create($interface_to_check);
	}

	/* Disable Accepting router advertisements unless specifically requested */
	if ($g['debug']) {
		log_error(sprintf(gettext("Deny router advertisements for interface %s"), $interface));
	}
	if (isset($wancfg['dhcp6usev4iface']) || $wancfg['ipaddr']==='ppp')
	{
		mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 -accept_rtadv", true);
	}
	/* wireless configuration? */
	if (is_array($wancfg['wireless']) && !$linkupevent) {
		interface_wireless_configure($realif, $wancfg, $wancfg['wireless']);
	}

	$current_mac = get_interface_mac($realhwif);
	$vendor_mac = get_interface_vendor_mac($realhwif);

	if ($current_mac != "ff:ff:ff:ff:ff:ff") {
		$mac_addr = $mac_if_cfg['spoofmac'] ?: $vendor_mac;

		interface_set_macaddr($realhwif, $mac_addr);
	} else {
		/*
		 * this is not a valid mac address.  generate a
		 * temporary mac address so the machine can get online.
		 */
		echo gettext("Generating new MAC address.");
		$random_mac = generate_random_mac_address();
		interface_set_macaddr($realhwif, $random_mac);
		$config['interfaces'][$mac_if]['spoofmac'] = $random_mac;
		write_config(sprintf(gettext('The invalid MAC address ' .
		    '(ff:ff:ff:ff:ff:ff) on interface %1$s has been ' .
		    'automatically replaced with %2$s'), $mac_if, $random_mac));
		file_notice("MAC Address altered", sprintf(gettext('The ' .
		    'invalid MAC address (ff:ff:ff:ff:ff:ff) on interface ' .
		    '%1$s has been automatically replaced with %2$s'), $mac_if,
		    $random_mac), "Interfaces");
	}

	/* media */
	if ($wancfg['media'] || $wancfg['mediaopt']) {
		$cmd = "/sbin/ifconfig " . escapeshellarg($realhwif);
		if ($wancfg['media']) {
			$cmd .= " media " . escapeshellarg($wancfg['media']);
		}
		if ($wancfg['mediaopt']) {
			$cmd .= " mediaopt " . escapeshellarg($wancfg['mediaopt']);
		}
		mwexec($cmd);
	}

	/* Apply hw offloading policies as configured */
	enable_hardware_offloading($interface);

	/* invalidate interface/ip/sn cache */
	get_interface_arr(true);
	unset($interface_ip_arr_cache[$realif]);
	unset($interface_sn_arr_cache[$realif]);
	unset($interface_ipv6_arr_cache[$realif]);
	unset($interface_snv6_arr_cache[$realif]);

	$tunnelif = substr($realif, 0, 3);

	$mtuif = $realif;
	$mtuhwif = $realhwif;

	/* adjust MTU of parent interface of PPPoE interface if this does not violate explicit configuration */
	if (interface_isppp_type($interface)) {
		$mtuif = $realhwif;
		$mtuhwif_array = get_parent_interface($mtuif);
		$mtuhwif = $mtuhwif_array[0];
	}

	$wantedmtu = 0;
	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $tmpinterface) {
			if ($tmpinterface['if'] == $mtuif && !empty($tmpinterface['mtu'])) {
				$wantedmtu = $tmpinterface['mtu'];
				break;
			}
		}
	}

	/* MTU is not specified for interface, try the pppoe settings. */
	if ($wantedmtu == 0) {
		$wantedmtu = interface_mtu_wanted_for_pppoe($mtuif);
	}
	if (($wantedmtu == 0) && (interface_is_vlan($mtuif) != NULL) && interface_isppp_type($interface)) {
		$wantedmtu = interface_mtu_wanted_for_pppoe($mtuhwif);
	}
	if (($wantedmtu == 0) && interface_is_type($mtuif, 'gre')) {
		/* set MTU to 1400 for GRE over IPsec */
		if (is_greipsec($mtuif)) {
			$wantedmtu = 1400;
		} else {
			$wantedmtu = 1476;
		}
	}
	if (($wantedmtu == 0) && interface_is_type($mtuif, 'gif')) {
		$wantedmtu = 1280;
	}
	/* Default MTU for WireGuard is 1420
	 * https://redmine.pfsense.org/issues/11291 */
	if (($wantedmtu == 0) && interface_is_type($mtuif, 'wg')) {
		$wantedmtu = 1420;
	}

	/* Set the MTU to 1500 if no explicit MTU configured. */
	if ($wantedmtu == 0) {
		$wantedmtu = 1500; /* Default */
	}

	if (interface_is_vlan($mtuif) != NULL) {
		$assignedparent = convert_real_interface_to_friendly_interface_name($mtuhwif);
		if (!empty($assignedparent) && !empty($config['interfaces'][$assignedparent]['mtu'])) {
			$parentmtu = $config['interfaces'][$assignedparent]['mtu'];
			if ($wancfg['mtu'] > $parentmtu) {
				log_error(sprintf(gettext('There is a conflict on MTU between parent %1$s and VLAN(%2$s)'), $mtuhwif, $mtuif));
			}
		}

		$configuredmtu = interface_vlan_mtu_configured($mtuif);

		if ($configuredmtu != 0 && $configuredmtu > $parentmtu)
			$configuredmtu = $parentmtu;
		if ($configuredmtu != 0)
			$mtu = $configuredmtu;
		else
			$mtu = $wantedmtu;

		/* Set the parent MTU. */
		if (get_interface_mtu($mtuhwif) < $mtu)
			set_interface_mtu($mtuhwif, $mtu);
		/* Set the VLAN MTU. */
		if (get_interface_mtu($mtuif) != $mtu)
			set_interface_mtu($mtuif, $mtu);
	} elseif (substr($mtuif, 0, 4) == 'lagg') {
		/* LAGG interface must be destroyed and re-created to change MTU */
		if ($wantedmtu != get_interface_mtu($mtuif)) {
			if (isset($config['laggs']['lagg']) && is_array($config['laggs']['lagg'])) {
				foreach ($config['laggs']['lagg'] as $lagg) {
					if ($lagg['laggif'] == $mtuif) {
						interface_lagg_configure($lagg);
						break;
					}
				}
			}
		}
	} else {
		if ($wantedmtu != get_interface_mtu($mtuif)) {
			pfSense_interface_mtu($mtuif, $wantedmtu);
			set_ipv6routes_mtu($mtuif, $wantedmtu);
		}
	}
	/* XXX: What about gre/gif/.. ? */

	if (does_interface_exist($wancfg['if'])) {
		interfaces_bring_up($wancfg['if']);
	}

	switch ($wancfg['ipaddr']) {
		case 'dhcp':
			interface_dhcp_configure($interface);
			break;
		case 'pppoe':
		case 'l2tp':
		case 'pptp':
		case 'ppp':
			interface_ppps_configure($interface);
			break;
		default:
			/* XXX: Kludge for now related to #3280 */
			if (!in_array($tunnelif, array("gif", "gre", "ovp", "ips"))) {
				if (is_ipaddrv4($wancfg['ipaddr']) && $wancfg['subnet'] <> "") {
					pfSense_interface_setaddress($realif, "{$wancfg['ipaddr']}/{$wancfg['subnet']}");
				}
			}
			break;
	}

	switch ($wancfg['ipaddrv6']) {
		case 'slaac':
		case 'dhcp6':
			// N.B. PPP connections using PPP as the IPv6 parent interface are excluded because the ppp-ipv6 script
			// calls interface_dhcpv6_configure() for these connections after IPv6CP is up, whilst rc.newwanip
			// handles all non-PPP connections with 'dhcp6usev4iface' set
			/* Remove the check file. Should not be there but just in case */
			unlink_if_exists("/tmp/{$wanif}_dhcp6_complete");
			log_error(gettext("calling interface_dhcpv6_configure."));
			if ((($wancfg['ipaddrv6'] == 'dhcp6') && !isset($wancfg['dhcp6usev4iface'])) ||
			    (($wancfg['ipaddrv6'] == 'slaac') && !isset($wancfg['slaacusev4iface'])) ||
			    !interface_isppp_type($interface)) {
				interface_dhcpv6_configure($interface, $wancfg);
			}
			break;
		case '6rd':
			interface_6rd_configure($interface, $wancfg);
			break;
		case '6to4':
			interface_6to4_configure($interface, $wancfg);
			break;
		case 'track6':
			interface_track6_configure($interface, $wancfg, $linkupevent);
			break;
		default:
			/* XXX: Kludge for now related to #3280 */
			if (!in_array($tunnelif, array("gif", "gre", "ovp", "ips"))) {
				if (is_ipaddrv6($wancfg['ipaddrv6']) && $wancfg['subnetv6'] <> "") {
					//pfSense_interface_setaddress($realif, "{$wancfg['ipaddrv6']}/{$wancfg['subnetv6']}");
					// FIXME: Add IPv6 Support to the pfSense module
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$wancfg['ipaddrv6']} prefixlen " . escapeshellarg($wancfg['subnetv6']));
				}
			}
			break;
	}

	interface_netgraph_needed($interface);

	if (!platform_booting()) {
		link_interface_to_vips($interface, "update");

		if ($tunnelif != 'gre') {
			unset($gre);
			$gre = link_interface_to_tunnelif($interface, 'gre');
			if (!empty($gre)) {
				array_walk($gre, 'interface_gre_configure');
			}
		}

		if ($tunnelif != 'gif') {
			unset($gif);
			$gif = link_interface_to_tunnelif($interface, 'gif');
			if (!empty($gif)) {
				array_walk($gif, 'interface_gif_configure');
			}
		}

		if (($linkupevent == false) || (substr($realif, 0, 4) == "ovpn") || (substr($realif, 0, 5) == "ipsec")) {
			unset($bridgetmp);
			$bridgetmp = link_interface_to_bridge($interface);
			if (!empty($bridgetmp)) {
				interface_bridge_add_member($bridgetmp, $realif);
			}
		}

		$grouptmp = link_interface_to_group($interface);
		if (!empty($grouptmp)) {
			array_walk($grouptmp, 'interface_group_add_member');
		}

		if ($interface == "lan") {
			/* make new hosts file */
			system_hosts_generate();
		}

		if ($reloadall == true) {

			/* reconfigure static routes (kernel may have deleted them) */
			system_routing_configure($interface);

			/* reload ipsec tunnels */
			send_event("service reload ipsecdns");

			if (isset($config['dnsmasq']['enable'])) {
				services_dnsmasq_configure();
			}

			if (isset($config['unbound']['enable'])) {
				services_unbound_configure();
			}

			/* update dyndns */
			send_event("service reload dyndns {$interface}");

			/* reload captive portal */
			if (!function_exists('captiveportal_init_rules_byinterface')) {
				require_once('captiveportal.inc');
			}
			captiveportal_init_rules_byinterface($interface);
		}
	}

	if (!empty($wancfg['descr'])) {
		mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " description " . escapeshellarg($wancfg['descr']));
	};

	interfaces_staticarp_configure($interface);
	return 0;
}

function interface_track6_configure($interface = "lan", $wancfg, $linkupevent = false) {
	global $config, $g;

	if (!is_array($wancfg)) {
		return;
	}

	if (!isset($wancfg['enable'])) {
		return;
	}

	/* If the interface is not configured via another, exit */
	if (empty($wancfg['track6-interface'])) {
		return;
	}

	/* always configure a link-local of fe80::1:1 on the track6 interfaces */
	$realif = get_real_interface($interface);
	$linklocal = find_interface_ipv6_ll($realif, true);
	if (!empty($linklocal) && $linklocal != "fe80::1:1%{$realif}") {
		mwexec("/sbin/ifconfig {$realif} inet6 fe80::1:1%{$realif} alias");
	}

	$trackcfg = $config['interfaces'][$wancfg['track6-interface']];
	if (!isset($trackcfg['enable'])) {
		log_error(sprintf(gettext('Interface %1$s tracking non-existent interface %2$s'), $interface, $wancfg['track6-interface']));
		return;
	}

	switch ($trackcfg['ipaddrv6']) {
		case "6to4":
			if ($g['debug']) {
				log_error(sprintf(gettext('Interface %1$s configured via %2$s type %3$s'), $interface, $wancfg['track6-interface'], $type));
			}
			interface_track6_6to4_configure($interface, $wancfg);
			break;
		case "6rd":
			if ($g['debug']) {
				log_error(sprintf(gettext('Interface %1$s configured via %2$s type %3$s'), $interface, $wancfg['track6-interface'], $type));
			}
			interface_track6_6rd_configure($interface, $wancfg);
			break;
		case "dhcp6":
			if ($linkupevent == true) {
				/*
				 * NOTE: Usually come here from rc.linkup calling so just call directly instead of generating event
				 * 	Instead of disrupting all other v4 configuration just restart DHCPv6 client for now
				 *
				 * XXX: Probably DHCPv6 client should handle this automagically itself?
				 */
				$parentrealif = get_real_interface($wancfg['track6-interface']);
				$pidv6 = find_dhcp6c_process($parentrealif);
				if ($pidv6) {
					posix_kill($pidv6, SIGHUP);
				}
			}
			break;
	}

	if ($linkupevent == false && !platform_booting()) {
		if (!function_exists('services_dhcpd_configure')) {
			require_once("services.inc");
		}

		/* restart dns servers (defering dhcpd reload) */
		if (isset($config['unbound']['enable'])) {
			services_unbound_configure(false);
		}
		if (isset($config['dnsmasq']['enable'])) {
			services_dnsmasq_configure(false);
		}

		/* reconfigure dhcpdv6 (leaving dhcpdv4 alone) */
		services_dhcpd_configure("inet6");
	}

	return 0;
}

function interface_track6_6rd_configure($interface = "lan", $lancfg) {
	global $config, $g;
	global $interface_ipv6_arr_cache;
	global $interface_snv6_arr_cache;

	if (!is_array($lancfg)) {
		return;
	}

	/* If the interface is not configured via another, exit */
	if (empty($lancfg['track6-interface'])) {
		return;
	}

	$wancfg = $config['interfaces'][$lancfg['track6-interface']];
	if (empty($wancfg)) {
		log_error(sprintf(gettext('Interface %1$s tracking non-existent interface %2$s'), $interface, $lancfg['track6-interface']));
		return;
	}

	$ip4address = get_interface_ip($lancfg['track6-interface']);
	if (!is_ipaddrv4($ip4address)) { /* XXX: This should not be needed by 6rd || (is_private_ip($ip4address))) { */
		log_error(sprintf(gettext('The interface IPv4 \'%1$s\' address on interface \'%2$s\' is not valid, not configuring 6RD tunnel'), $ip4address, $lancfg['track6-interface']));
		return;
	}
	$hexwanv4 = return_hex_ipv4($ip4address);

	/* create the long prefix notation for math, save the prefix length */
	$rd6prefix = explode("/", $wancfg['prefix-6rd']);
	$rd6prefixlen = $rd6prefix[1];
	$rd6prefix = Net_IPv6::uncompress($rd6prefix[0]);

	/* binary presentation of the prefix for all 128 bits. */
	$rd6lanbin = convert_ipv6_to_128bit($rd6prefix);

	/* just save the left prefix length bits */
	$rd6lanbin = substr($rd6lanbin, 0, $rd6prefixlen);
	/* add the v4 address, offset n bits from the left */
	$rd6lanbin .= substr(sprintf("%032b", hexdec($hexwanv4)), (0 + $wancfg['prefix-6rd-v4plen']), 32);

	/* add the custom prefix id, max 32bits long? (64 bits - (prefixlen + (32 - v4plen)) */
	/* 64 - (37 + (32 - 17)) = 8 == /52 */
	$restbits = 64 - ($rd6prefixlen + (32 - $wancfg['prefix-6rd-v4plen']));
	// echo "64 - (prefixlen {$rd6prefixlen} + v4len (32 - {$wancfg['prefix-6rd-v4plen']})) = {$restbits} \n";
	$rd6lanbin .= substr(sprintf("%032b", str_pad($lancfg['track6-prefix-id'], 32, "0", STR_PAD_LEFT)), (32 - $restbits), 32);
	/* fill the rest out with zeros */
	$rd6lanbin = str_pad($rd6lanbin, 128, "0", STR_PAD_RIGHT);

	/* convert the 128 bits for the lan address back into a valid IPv6 address */
	$rd6lan = convert_128bit_to_ipv6($rd6lanbin) ."1";

	$lanif = get_real_interface($interface);
	$oip = find_interface_ipv6($lanif);
	if (is_ipaddrv6($oip)) {
		mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
	}
	unset($interface_ipv6_arr_cache[$lanif]);
	unset($interface_snv6_arr_cache[$lanif]);
	log_error(sprintf(gettext('rd6 %1$s with ipv6 address %2$s based on %3$s ipv4 %4$s'), $interface, $rd6lan, $lancfg['track6-interface'], $ip4address));
	mwexec("/sbin/ifconfig {$lanif} inet6 {$rd6lan} prefixlen 64");

	return 0;
}

function interface_track6_6to4_configure($interface = "lan", $lancfg) {
	global $config, $g;
	global $interface_ipv6_arr_cache;
	global $interface_snv6_arr_cache;

	if (!is_array($lancfg)) {
		return;
	}

	/* If the interface is not configured via another, exit */
	if (empty($lancfg['track6-interface'])) {
		return;
	}

	$wancfg = $config['interfaces'][$lancfg['track6-interface']];
	if (empty($wancfg)) {
		log_error(sprintf(gettext('Interface %1$s tracking non-existent interface %2$s'), $interface, $lancfg['track6-interface']));
		return;
	}

	$ip4address = get_interface_ip($lancfg['track6-interface']);
	if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
		log_error(sprintf(gettext('The interface IPv4 \'%1$s\' address on interface \'%2$s\' is not public, not configuring 6RD tunnel'), $ip4address, $lancfg['track6-interface']));
		return;
	}
	$hexwanv4 = return_hex_ipv4($ip4address);

	/* create the long prefix notation for math, save the prefix length */
	$sixto4prefix = "2002::";
	$sixto4prefixlen = 16;
	$sixto4prefix = Net_IPv6::uncompress($sixto4prefix);

	/* binary presentation of the prefix for all 128 bits. */
	$sixto4lanbin = convert_ipv6_to_128bit($sixto4prefix);

	/* just save the left prefix length bits */
	$sixto4lanbin = substr($sixto4lanbin, 0, $sixto4prefixlen);
	/* add the v4 address */
	$sixto4lanbin .= sprintf("%032b", hexdec($hexwanv4));
	/* add the custom prefix id */
	$sixto4lanbin .= sprintf("%016b", $lancfg['track6-prefix-id']);
	/* fill the rest out with zeros */
	$sixto4lanbin = str_pad($sixto4lanbin, 128, "0", STR_PAD_RIGHT);

	/* convert the 128 bits for the lan address back into a valid IPv6 address */
	$sixto4lan = convert_128bit_to_ipv6($sixto4lanbin) ."1";

	$lanif = get_real_interface($interface);
	$oip = find_interface_ipv6($lanif);
	if (is_ipaddrv6($oip)) {
		mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
	}
	unset($interface_ipv6_arr_cache[$lanif]);
	unset($interface_snv6_arr_cache[$lanif]);
	log_error(sprintf(gettext('sixto4 %1$s with ipv6 address %2$s based on %3$s ipv4 %4$s'), $interface, $sixto4lan, $lancfg['track6-interface'], $ip4address));
	mwexec("/sbin/ifconfig {$lanif} inet6 {$sixto4lan} prefixlen 64");

	return 0;
}

function interface_6rd_configure($interface = "wan", $wancfg) {
	global $config, $g;

	/* because this is a tunnel interface we can only function
	 *	with a public IPv4 address on the interface */

	if (!is_array($wancfg)) {
		return;
	}

	if (!is_module_loaded('if_stf.ko')) {
		mwexec('/sbin/kldload if_stf.ko');
	}

	$wanif = get_real_interface($interface);
	$ip4address = find_interface_ip($wanif);
	if (!is_ipaddrv4($ip4address)) {
		log_error(sprintf(gettext('The interface IPv4 \'%1$s\' address on interface \'%2$s\' is not public, not configuring 6RD tunnel'), $ip4address, $wanif));
		return false;
	}
	$hexwanv4 = return_hex_ipv4($ip4address);

	if (!is_numeric($wancfg['prefix-6rd-v4plen'])) {
		$wancfg['prefix-6rd-v4plen'] = 0;
	}

	/* create the long prefix notation for math, save the prefix length */
	$rd6prefix = explode("/", $wancfg['prefix-6rd']);
	$rd6prefixlen = $rd6prefix[1];
	$brgw = explode('.', $wancfg['gateway-6rd']);
	$rd6brgw = substr(ip6_to_bin($rd6prefix[0]), 0, $rd6prefixlen);
	$rd6brgw .= str_pad(decbin($brgw[0]), 8, '0', STR_PAD_LEFT) . str_pad(decbin($brgw[1]), 8, '0', STR_PAD_LEFT) . str_pad(decbin($brgw[2]), 8, '0', STR_PAD_LEFT) . str_pad(decbin($brgw[3]), 8, '0', STR_PAD_LEFT);
	if (strlen($rd6brgw) < 128) {
		$rd6brgw = str_pad($rd6brgw, 128, '0', STR_PAD_RIGHT);
	}
	$rd6brgw = bin_to_compressed_ip6($rd6brgw);
	unset($brgw);
	$rd6prefix = Net_IPv6::uncompress($rd6prefix[0]);

	/* binary presentation of the prefix for all 128 bits. */
	$rd6prefixbin = convert_ipv6_to_128bit($rd6prefix);

	/* just save the left prefix length bits */
	$rd6prefixbin = substr($rd6prefixbin, 0, $rd6prefixlen);
	/* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
	$rd6prefixbin .= substr(sprintf("%032b", hexdec($hexwanv4)), $wancfg['prefix-6rd-v4plen'], 32);
	/* fill out the rest with 0's */
	$rd6prefixbin = str_pad($rd6prefixbin, 128, "0", STR_PAD_RIGHT);

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$rd6prefix = convert_128bit_to_ipv6($rd6prefixbin);


	/* XXX: need to extend to support variable prefix size for v4 */
	$stfiface = "{$interface}_stf";
	if (does_interface_exist($stfiface)) {
		pfSense_interface_destroy($stfiface);
	}
	$tmpstfiface = pfSense_interface_create2("stf");
	pfSense_interface_rename($tmpstfiface, $stfiface);
	pfSense_interface_flags($stfiface, IFF_LINK2);
	mwexec("/sbin/ifconfig {$stfiface} inet6 {$rd6prefix}/{$rd6prefixlen}");
	mwexec("/sbin/ifconfig {$stfiface} stfv4br " . escapeshellarg($wancfg['gateway-6rd']));
	if ($wancfg['prefix-6rd-v4plen'] >= 0 && $wancfg['prefix-6rd-v4plen'] <= 32) {
		mwexec("/sbin/ifconfig {$stfiface} stfv4net {$ip4address}/" . escapeshellarg($wancfg['prefix-6rd-v4plen']));
	}
	$parentmtu = get_interface_mtu(get_real_interface($interface));
	if (isset($wancfg['mtu']) && ($wancfg['mtu'] >= 1300)) {
		set_interface_mtu($stfiface, $wancfg['mtu'] - 20);
	} elseif ($parentmtu > 1300) {
		set_interface_mtu($stfiface, $parentmtu - 20);
	}
	if ($g['debug']) {
		log_error("Created 6rd interface {$stfiface} {$rd6prefix}/{$rd6prefixlen}");
	}

	/* write out a default router file */
	file_put_contents("{$g['tmp_path']}/{$wanif}_routerv6", "{$rd6brgw}\n");
	file_put_contents("{$g['tmp_path']}/{$wanif}_defaultgwv6", "{$rd6brgw}\n");

	$ip4gateway = get_interface_gateway($interface);
	if (is_ipaddrv4($ip4gateway)) {
		route_add_or_change($wancfg['gateway-6rd'], $ip4gateway);
	}

	/* configure dependent interfaces */
	if (!platform_booting()) {
		link_interface_to_track6($interface, "update");
	}

	return 0;
}

function interface_6to4_configure($interface = "wan", $wancfg) {
	global $config, $g;

	/* because this is a tunnel interface we can only function
	 *	with a public IPv4 address on the interface */

	if (!is_array($wancfg)) {
		return;
	}

	$wanif = get_real_interface($interface);
	$ip4address = find_interface_ip($wanif);
	if ((!is_ipaddrv4($ip4address)) || (is_private_ip($ip4address))) {
		log_error(sprintf(gettext('The interface IPv4 \'%1$s\' address on interface \'%2$s\' is not public, not configuring 6RD tunnel'), $ip4address, $wanif));
		return false;
	}

	/* create the long prefix notation for math, save the prefix length */
	$stfprefixlen = 16;
	$stfprefix = Net_IPv6::uncompress("2002::");
	$stfarr = explode(":", $stfprefix);
	$v4prefixlen = "0";

	/* we need the hex form of the interface IPv4 address */
	$ip4arr = explode(".", $ip4address);
	$hexwanv4 = "";
	foreach ($ip4arr as $octet) {
		$hexwanv4 .= sprintf("%02x", $octet);
	}

	/* we need the hex form of the broker IPv4 address */
	$ip4arr = explode(".", "192.88.99.1");
	$hexbrv4 = "";
	foreach ($ip4arr as $octet) {
		$hexbrv4 .= sprintf("%02x", $octet);
	}

	/* binary presentation of the prefix for all 128 bits. */
	$stfprefixbin = "";
	foreach ($stfarr as $element) {
		$stfprefixbin .= sprintf("%016b", hexdec($element));
	}
	/* just save the left prefix length bits */
	$stfprefixstartbin = substr($stfprefixbin, 0, $stfprefixlen);

	/* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
	$stfbrokerbin = substr(sprintf("%032b", hexdec($hexbrv4)), $v4prefixlen, 32);
	$stfbrokerbin = str_pad($stfprefixstartbin . $stfbrokerbin, 128, "0", STR_PAD_RIGHT);

	/* for the local subnet too. */
	$stflanbin = substr(sprintf("%032b", hexdec($hexwanv4)), $v4prefixlen, 32);
	$stflanbin = str_pad($stfprefixstartbin . $stflanbin, 128, "0", STR_PAD_RIGHT);

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$stfbrarr = array();
	$stfbrbinarr = array();
	$stfbrbinarr = str_split($stfbrokerbin, 16);
	foreach ($stfbrbinarr as $bin) {
		$stfbrarr[] = dechex(bindec($bin));
	}
	$stfbrgw = text_to_compressed_ip6(implode(":", $stfbrarr));

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$stflanarr = array();
	$stflanbinarr = array();
	$stflanbinarr = str_split($stflanbin, 16);
	foreach ($stflanbinarr as $bin) {
		$stflanarr[] = dechex(bindec($bin));
	}
	$stflanpr = text_to_compressed_ip6(implode(":", $stflanarr));
	$stflanarr[7] = 1;
	$stflan = text_to_compressed_ip6(implode(":", $stflanarr));

	/* setup the stf interface */
	if (!is_module_loaded("if_stf")) {
		mwexec("/sbin/kldload if_stf.ko");
	}
	$stfiface = "{$interface}_stf";
	if (does_interface_exist($stfiface)) {
		pfSense_interface_destroy($stfiface);
	}
	$tmpstfiface = pfSense_interface_create2("stf");
	pfSense_interface_rename($tmpstfiface, $stfiface);
	pfSense_interface_flags($stfiface, IFF_LINK2);
	mwexec("/sbin/ifconfig {$stfiface} inet6 {$stflanpr} prefixlen 16");

	$parentmtu = get_interface_mtu(get_real_interface($interface));
	if (isset($wancfg['mtu']) && ($wancfg['mtu'] >= 1300)) {
		set_interface_mtu($stfiface, $wancfg['mtu'] - 20);
	} elseif ($parentmtu > 1300) {
		set_interface_mtu($stfiface, $parentmtu - 20);
	}
	if ($g['debug']) {
		log_error("Set IPv6 address inet6 {$stflanpr} prefixlen 16 for {$stfiface}, route {$stfbrgw}");
	}

	/* write out a default router file */
	file_put_contents("{$g['tmp_path']}/{$wanif}_routerv6", "{$stfbrgw}");
	file_put_contents("{$g['tmp_path']}/{$wanif}_defaultgwv6", "{$stfbrgw}");

	$ip4gateway = get_interface_gateway($interface);
	if (is_ipaddrv4($ip4gateway)) {
		route_add_or_change("192.88.99.1", $ip4gateway);
	}

	if (!platform_booting()) {
		link_interface_to_track6($interface, "update");
	}

	return 0;
}

function interface_dhcpv6_configure($interface = "wan", $wancfg) {
	global $config, $g;

	if (!is_array($wancfg)) {
		return;
	}

	$wanif = get_real_interface($interface, "inet6");
	$dhcp6cconf = "";

	if (!empty($config['system']['global-v6duid'])) {
		// Write the DUID file
		if(!write_dhcp6_duid($config['system']['global-v6duid'])) {
		    log_error(gettext("Failed to write user DUID file!"));
		}
	}

	/* accept router advertisements for this interface                 */
	/* Moved to early in the function as sometimes interface not ready */
	/* RTSOLD fails as interface does not accept .....                 */

	log_error("Accept router advertisements on interface {$wanif} ");
	mwexec("/sbin/ifconfig {$wanif} inet6 accept_rtadv");

	if ($wancfg['adv_dhcp6_config_file_override']) {
		// DHCP6 Config File Override
		$dhcp6cconf = DHCP6_Config_File_Override($wancfg, $wanif);
	} elseif ($wancfg['adv_dhcp6_config_advanced']) {
		// DHCP6 Config File Advanced
		$dhcp6cconf = DHCP6_Config_File_Advanced($interface, $wancfg, $wanif);
	} else {
		// DHCP6 Config File Basic
		$dhcp6cconf .= "interface {$wanif} {\n";

		/* for SLAAC interfaces we do fire off a dhcp6 client for just our name servers */
		if ($wancfg['ipaddrv6'] == "slaac") {
			$dhcp6cconf .= "\tinformation-only;\n";
			$dhcp6cconf .= "\trequest domain-name-servers;\n";
			$dhcp6cconf .= "\trequest domain-name;\n";
			$dhcp6cconf .= "\tscript \"{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";
			$dhcp6cconf .= "};\n";
		} else {
			$trackiflist = array();
			$iflist = link_interface_to_track6($interface);
			foreach ($iflist as $ifname => $ifcfg) {
				if (is_numeric($ifcfg['track6-prefix-id'])) {
					$trackiflist[$ifname] = $ifcfg;
				}
			}

			/* skip address request if this is set */
			if (!isset($wancfg['dhcp6prefixonly'])) {
				$dhcp6cconf .= "\tsend ia-na 0;\t# request stateful address\n";
			}
			if (is_numeric($wancfg['dhcp6-ia-pd-len'])) {
				$dhcp6cconf .= "\tsend ia-pd 0;\t# request prefix delegation\n";
			}

			$dhcp6cconf .= "\trequest domain-name-servers;\n";
			$dhcp6cconf .= "\trequest domain-name;\n";

			/*
			 * dhcp6c will run different scripts depending on
			 * whether dhcpwithoutra is set or unset.
			 */
			if (isset($wancfg['dhcp6withoutra'])) {
				$dhcp6cconf .= "\tscript \"{$g['varetc_path']}/dhcp6c_{$interface}_dhcp6withoutra_script.sh\"; # we'd like nameservers and RTSOLD to do all the work\n";
			} else {
				$dhcp6cconf .= "\tscript \"{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";
			}
			$dhcp6cconf .= "};\n";

			if (!isset($wancfg['dhcp6prefixonly'])) {
				$dhcp6cconf .= "id-assoc na 0 { };\n";
			}

			if (is_numeric($wancfg['dhcp6-ia-pd-len'])) {
				/* Setup the prefix delegation */
				$dhcp6cconf .= "id-assoc pd 0 {\n";
				$preflen = 64 - $wancfg['dhcp6-ia-pd-len'];
				if (isset($wancfg['dhcp6-ia-pd-send-hint'])) {
					$dhcp6cconf .= "\tprefix ::/{$preflen} infinity;\n";
				}
				foreach ($trackiflist as $friendly => $ifcfg) {
					if ($g['debug']) {
						log_error("setting up $ifdescr - {$ifcfg['track6-prefix-id']}");
					}
					$realif = get_real_interface($friendly);
					$dhcp6cconf .= "\tprefix-interface {$realif} {\n";
					$dhcp6cconf .= "\t\tsla-id {$ifcfg['track6-prefix-id']};\n";
					$dhcp6cconf .= "\t\tsla-len {$wancfg['dhcp6-ia-pd-len']};\n";
					$dhcp6cconf .= "\t};\n";
				}
				unset($preflen, $iflist, $ifcfg, $ifname);
				$dhcp6cconf .= "};\n";
			}
			unset($trackiflist);
		}
	}

	$debugOption = isset($wancfg['dhcp6debug']) ? "-D" : "-d";
	$noreleaseOption = isset($wancfg['dhcp6norelease']) ? "-n" : "";

	/* wide-dhcp6c works for now. */
	if (!@file_put_contents("{$g['varetc_path']}/dhcp6c_{$interface}.conf", $dhcp6cconf)) {
		printf("Error: cannot open dhcp6c_{$interface}.conf in interface_dhcpv6_configure() for writing.\n");
		unset($dhcp6cconf);
		return 1;
	}
	unset($dhcp6cconf);

	/*************** Script Debug Logging ***************************
	Both dhcp6 scripts now have a logging message built in.
	These logging messages ONLY appear if dhcp6c debug logging is set.
	The logging messages appear in the dhcp section of the logs,
	not in system.

	These scripts now also take advantage of the REASON= env vars
	supplied by dhcp6c.
	****************************************************************/

	/* Script create for dhcp6withoutRA mode */
	/* dhcp6c will launch rtsold. rtsold will then run the wan ipv6 configure */
	$dhcp6cscriptwithoutra = "#!/bin/sh\n";
	$dhcp6cscriptwithoutra .= "# This shell script launches rtsold.\n";
	$dhcp6cscriptwithoutra .= "dmips=\${new_domain_name_servers}\n";
	$dhcp6cscriptwithoutra .= "dmnames=\${new_domain_name}\n";
	$dhcp6cscriptwithoutra .= "dreason=\${REASON}\n";
	// Need to pass params to  the final script
	$dhcp6cscriptwithoutra .= "echo \$dmips > /tmp/{$wanif}_domain_name_servers\n";
	$dhcp6cscriptwithoutra .= "echo \$dmnames > /tmp/{$wanif}_new_domain_name\n";
	$dhcp6cscriptwithoutra .= "echo \$dreason > /tmp/{$wanif}_reason\n";
	$dhcp6cscriptwithoutra .= "case \$REASON in\n";
	$dhcp6cscriptwithoutra .= "REQUEST)\n";
	$dhcp6cscriptwithoutra .= "/bin/sleep 2\n";
	$dhcp6cscriptwithoutra .= "/usr/sbin/rtsold -1 -p {$g['varrun_path']}/rtsold_{$wanif}.pid -O {$g['varetc_path']}/rtsold_{$wanif}_script.sh {$wanif}\n";
	if ($debugOption == '-D') {
		$dhcp6cscriptwithoutra .= "/usr/bin/logger -t dhcp6c \"dhcp6c REQUEST on {$wanif} - running rtsold\"\n";
	}
	$dhcp6cscriptwithoutra .= ";;\n";
	$dhcp6cscriptwithoutra .= "REBIND)\n";
	if ($debugOption == '-D') {
		$dhcp6cscriptwithoutra .= "/usr/bin/logger -t dhcp6c \"dhcp6c rebind on {$wanif}\"\n";
	}
	$dhcp6cscriptwithoutra .= ";;\n";
	if (isset($wancfg['dhcp6norelease'])) {
		$dhcp6cscriptwithoutra .= "EXIT)\n";
	} else {
		$dhcp6cscriptwithoutra .= "RELEASE)\n";
	}
	if ($debugOption == '-D') {
		$dhcp6cscriptwithoutra .= "/usr/bin/logger -t dhcp6c \"dhcp6c EXIT or RELEASE on {$wanif} running rc.newwanipv6\"\n";
	}
	$dhcp6cscriptwithoutra .= "/usr/local/sbin/fcgicli -f /etc/rc.newwanipv6 -d \"interface={$wanif}&dmnames=\${dmnames}&dmips=\${dmips}\"\n";
	$dhcp6cscriptwithoutra .= ";;\n";
	$dhcp6cscriptwithoutra .= "RENEW|INFO)\n";
	if ($debugOption == '-D') {
		$dhcp6cscriptwithoutra .= "/usr/bin/logger -t dhcp6c \"dhcp6c renew, no change - bypassing update on {$wanif}\"\n";
	}
	$dhcp6cscriptwithoutra .= "esac\n";
	if (!@file_put_contents(
	    "{$g['varetc_path']}/dhcp6c_{$interface}_dhcp6withoutra_script.sh",
	    $dhcp6cscriptwithoutra)) {
		printf("Error: cannot open " .
		    "dhcp6c_{$interface}_dhcp6cwithoutra_script.sh in " .
		    "interface_dhcpv6_configure() for writing.\n");
		unset($dhcp6cscriptwithoutra);
		return 1;
	}

	unset($dhcp6cscriptwithoutra);
	@chmod(
	    "{$g['varetc_path']}/dhcp6c_{$interface}_dhcp6withoutra_script.sh",
	    0755);

	/*
	 * Dual mode wan_dhcp6c script with variations depending on node
	 * dhcp6 will run the wan ipv6 configure
	 */
	$dhcp6cscript  = "#!/bin/sh\n";
	$dhcp6cscript .= "# This shell script launches /etc/rc.newwanipv6 with a interface argument.\n";
	if (!isset($wancfg['dhcp6withoutra'])) {
		$dhcp6cscript .= "dmips=\${new_domain_name_servers}\n";
		$dhcp6cscript .= "dmnames=\${new_domain_name}\n";
		$dhcp6cscript .= "case \$REASON in\n";
		$dhcp6cscript .= "REBIND)\n";
		if ($debugOption == '-D') {
			$dhcp6cscript .= "/usr/bin/logger -t dhcp6c \"dhcp6c rebind on {$wanif}\"\n";
		}
		$dhcp6cscript .= ";;\n";
		$dhcp6cscript .= "REQUEST|";
		if (isset($wancfg['dhcp6norelease'])) {
			$dhcp6cscript .= "EXIT)\n";
		} else {
			$dhcp6cscript .= "RELEASE)\n";
		}
		if ($debugOption == '-D') {
			$dhcp6cscript .= "/usr/bin/logger -t dhcp6c \"dhcp6c RELEASE, REQUEST or EXIT on {$wanif} running rc.newwanipv6\"\n";
		}
		$dhcp6cscript .= "/usr/local/sbin/fcgicli -f /etc/rc.newwanipv6 -d \"interface={$wanif}&dmnames=\${dmnames}&dmips=\${dmips}\"\n";
		$dhcp6cscript .= ";;\n";
		$dhcp6cscript .= "RENEW|INFO)\n";
		if ($debugOption == '-D') {
			$dhcp6cscript .= "/usr/bin/logger -t dhcp6c \"dhcp6c renew, no change - bypassing update on {$wanif}\"\n";
		}
		$dhcp6cscript .= "esac\n";
	} else {
		// Need to get the parameters from the dhcp6cwithoutRA run
		$dhcp6cscript .= "dmips=\$(cat \"/tmp/{$wanif}_domain_name_servers\")\n";
		$dhcp6cscript .= "dmnames=\$(cat \"/tmp/{$wanif}_new_domain_name\")\n";
		$dhcp6cscript .= "/bin/sleep 1\n";
		$dhcp6cscript .= "/usr/local/sbin/fcgicli -f /etc/rc.newwanipv6 -d \"interface={$wanif}&dmnames=\${dmnames}&dmips=\${dmips}\"\n";
	}

	/* Add wide-dhcp6c shell script here. Because we can not pass a argument to it. */
	if (!@file_put_contents("{$g['varetc_path']}/dhcp6c_{$interface}_script.sh", $dhcp6cscript)) {
		printf("Error: cannot open dhcp6c_{$interface}_script.sh in interface_dhcpv6_configure() for writing.\n");
		unset($dhcp6cscript);
		return 1;
	}
	unset($dhcp6cscript);
	@chmod("{$g['varetc_path']}/dhcp6c_{$interface}_script.sh", 0755);

	$rtsoldscript = "#!/bin/sh\n";
	$rtsoldscript .= "# This shell script launches dhcp6c and configured gateways for this interface.\n";
	$rtsoldscript .= "echo $2 > {$g['tmp_path']}/{$wanif}_routerv6\n";
	$rtsoldscript .= "echo $2 > {$g['tmp_path']}/{$wanif}_defaultgwv6\n";
	$rtsoldscript .= "/usr/bin/logger -t rtsold \"Received RA specifying route \$2 for interface {$interface}({$wanif})\"\n";

	/* non ipoe Process */
	if (!isset($wancfg['dhcp6withoutra'])) {
		/*
		 * We only want this script to run once, and if it runs twice
		 * then do not launch dhcp6c again, this only happens if
		 * dhcpwithoutra is not set.
		 *
		 * Check for a lock file, trying to prevent multiple instances
		 * of dhcp6c being launched
		 */
		$rtsoldscript .= "if [ ! -f /tmp/dhcp6c_{$wanif}_lock ]; then\n";
		/*
		 * Create the lock file, trying to prevent multiple instances
		 * of dhcp6c being launched
		 */
		$rtsoldscript .= "\t/usr/bin/touch /tmp/dhcp6c_{$wanif}_lock\n";
		$rtsoldscript .= "\tif [ -f {$g['varrun_path']}/dhcp6c_{$wanif}.pid ]; then\n";
		$rtsoldscript .= "\t\t/bin/pkill -F {$g['varrun_path']}/dhcp6c_{$wanif}.pid\n";
		$rtsoldscript .= "\t\t/bin/rm -f {$g['varrun_path']}/dhcp6c_{$wanif}.pid\n";
		$rtsoldscript .= "\t\t/bin/sleep 1\n";
		$rtsoldscript .= "\tfi\n";
		$rtsoldscript .= "\t/usr/local/sbin/dhcp6c {$debugOption} " .
		    "{$noreleaseOption} -c {$g['varetc_path']}/dhcp6c_{$interface}.conf " .
		    "-p {$g['varrun_path']}/dhcp6c_{$wanif}.pid {$wanif}\n";
		$rtsoldscript .= "\t/usr/bin/logger -t rtsold \"Starting dhcp6 client for interface {$interface}({$wanif})\"\n";
		$rtsoldscript .= "else\n";
		$rtsoldscript .= "\t/usr/bin/logger -t rtsold \"RTSOLD Lock in place - sending SIGHUP to dhcp6c\"\n";
		$rtsoldscript .= "\tdhcp6c_pid=\$(cat \"{$g['varrun_path']}/dhcp6c_{$wanif}.pid\")\n";
		$rtsoldscript .= "\t/bin/kill -1 \${dhcp6c_pid}\n";
		$rtsoldscript .= "fi\n";
	} else {
		/*
		 * The script needs to run in dhcp6withoutra mode as RA may
		 * not have been received, or there can be a delay with
		 * certain ISPs
		 */
		$rtsoldscript .= "{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\n";
		$rtsoldscript .= "/bin/sleep 1\n";
	}
	/* Add wide-dhcp6c shell script here. Because we can not pass a argument to it. */
	if (!@file_put_contents("{$g['varetc_path']}/rtsold_{$wanif}_script.sh", $rtsoldscript)) {
		printf("Error: cannot open rtsold_{$wanif}_script.sh in interface_dhcpv6_configure() for writing.\n");
		unset($rtsoldscript);
		return 1;
	}
	unset($rtsoldscript);
	@chmod("{$g['varetc_path']}/rtsold_{$wanif}_script.sh", 0755);

	if (isvalidpid("{$g['varrun_path']}/rtsold_{$wanif}.pid")) {
		killbypid("{$g['varrun_path']}/rtsold_{$wanif}.pid");
		log_error("Killing running rtsold process");
		sleep(2);
	}

	if (isset($wancfg['dhcp6withoutra'])) {
		/*
		 * Start dhcp6c here if we don't want to wait for ra - calls
		 * separate function
		 *
		 * In this mode dhcp6c launches rtsold via its script. RTSOLD
		 * will then run the configure on receipt of the RA.
		 *
		 * Already started. interface_dhcpv6_configure() appears to get
		 * called multiple times.
		 *
		 * Taking the interface down or releasing will kill the client.
		 */
		if (!file_exists("/tmp/dhcp6c_{$wanif}_lock"))
		{
			/*
			 * If the interface is being brought up, wait for the
			 * interface to configure accept RA before launching.
			 * Otherwise it is not ready to accept and will fail.
			 */
			sleep(3);
			run_dhcp6client_process($wanif,$interface,$wancfg);
		}
	} else {
		/*
		 * Fire up rtsold for IPv6 RAs, this backgrounds immediately
		 * ( it does not background, it exits! ) It will launch dhcp6c
		 * if dhcpwihtoutra is not set
		 */
		log_error("Starting rtsold process");
		sleep(2);
		mwexec("/usr/sbin/rtsold -1 " .
		    "-p {$g['varrun_path']}/rtsold_{$wanif}.pid " .
		    "-M {$g['varetc_path']}/rtsold_{$wanif}_script.sh " .
		    "-O {$g['varetc_path']}/rtsold_{$wanif}_script.sh " .
		    $wanif);
	}
	/*
	 * NOTE: will be called from rtsold invoked script
	 * link_interface_to_track6($interface, "update");
	 */

	return 0;
}

function DHCP6_Config_File_Advanced($interface, $wancfg, $wanif) {
	global $g;

	$send_options = "";
	if ($wancfg['adv_dhcp6_interface_statement_send_options'] != '') {
		$options = DHCP_Config_Option_Split($wancfg['adv_dhcp6_interface_statement_send_options']);
		foreach ($options as $option) {
			$send_options .= "\tsend " . trim($option) . ";\n";
		}
	}

	$request_options = "";
	if ($wancfg['adv_dhcp6_interface_statement_request_options'] != '') {
		$options = DHCP_Config_Option_Split($wancfg['adv_dhcp6_interface_statement_request_options']);
		foreach ($options as $option) {
			$request_options .= "\trequest " . trim($option) . ";\n";
		}
	}

	$information_only = "";
	if ($wancfg['adv_dhcp6_interface_statement_information_only_enable'] != '') {
		$information_only = "\tinformation-only;\n";
	}

	$script = "\tscript \"{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\";\n";
	if ($wancfg['adv_dhcp6_interface_statement_script'] != '') {
		$script = "\tscript \"{$wancfg['adv_dhcp6_interface_statement_script']}\";\n";
	}

	$interface_statement  = "interface";
	$interface_statement .= " {$wanif}";
	$interface_statement .= " {\n";
	$interface_statement .= "$send_options";
	$interface_statement .= "$request_options";
	$interface_statement .= "$information_only";
	$interface_statement .= "$script";
	$interface_statement .= "};\n";

	$id_assoc_statement_address = "";
	if ($wancfg['adv_dhcp6_id_assoc_statement_address_enable'] != '') {
		$id_assoc_statement_address .= "id-assoc";
		$id_assoc_statement_address .= " na";
		if (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_id'])) {
			$id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address_id']}";
		}
		$id_assoc_statement_address .= " { ";

		if (($wancfg['adv_dhcp6_id_assoc_statement_address'] != '') &&
		    (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_pltime']) ||
		    ($wancfg['adv_dhcp6_id_assoc_statement_address_pltime'] == 'infinity'))) {
			$id_assoc_statement_address .= "\n\taddress";
			$id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address']}";
			$id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address_pltime']}";
			if ((is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_vltime'])) ||
			    ($wancfg['adv_dhcp6_id_assoc_statement_address_vltime'] == 'infinity')) {
				$id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address_vltime']}";
			}
			$id_assoc_statement_address .= ";\n";
		}

		$id_assoc_statement_address .= "};\n";
	}

	$id_assoc_statement_prefix = "";
	if ($wancfg['adv_dhcp6_id_assoc_statement_prefix_enable'] != '') {
		$id_assoc_statement_prefix .= "id-assoc";
		$id_assoc_statement_prefix .= " pd";
		if (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_id'])) {
			$id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix_id']}";
		}
		$id_assoc_statement_prefix .= " { ";

		if (($wancfg['adv_dhcp6_id_assoc_statement_prefix'] != '') &&
		    (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime']) ||
		    ($wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime'] == 'infinity'))) {
			$id_assoc_statement_prefix .= "\n\tprefix";
			$id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix']}";
			$id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime']}";
			if ((is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'])) ||
			    ($wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'] == 'infinity')) {
				$id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime']}";
			}
			$id_assoc_statement_prefix .= ";";
		}

		$realif = get_real_interface($wancfg['adv_dhcp6_prefix_selected_interface']);
		if (is_numeric($wancfg['adv_dhcp6_prefix_interface_statement_sla_id'])) {
			$id_assoc_statement_prefix .= "\n\tprefix-interface";
			$id_assoc_statement_prefix .= " {$realif}";
			$id_assoc_statement_prefix .= " {\n";
			$id_assoc_statement_prefix .= "\t\tsla-id {$wancfg['adv_dhcp6_prefix_interface_statement_sla_id']};\n";
			if (($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] >= 0) &&
			    ($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] <= 128)) {
				$id_assoc_statement_prefix .= "\t\tsla-len {$wancfg['adv_dhcp6_prefix_interface_statement_sla_len']};\n";
			}
			$id_assoc_statement_prefix .= "\t};";
		}

		if (($wancfg['adv_dhcp6_id_assoc_statement_prefix'] != '') ||
		    (is_numeric($wancfg['adv_dhcp6_prefix_interface_statement_sla_id']))) {
			$id_assoc_statement_prefix .= "\n";
		}

		$id_assoc_statement_prefix .= "};\n";
	}

	$authentication_statement = "";
	if (($wancfg['adv_dhcp6_authentication_statement_authname'] != '') &&
	    ($wancfg['adv_dhcp6_authentication_statement_protocol'] == 'delayed')) {
		$authentication_statement .= "authentication";
		$authentication_statement .= " {$wancfg['adv_dhcp6_authentication_statement_authname']}";
		$authentication_statement .= " {\n";
		$authentication_statement .= "\tprotocol {$wancfg['adv_dhcp6_authentication_statement_protocol']};\n";
		if (preg_match("/(hmac(-)?md5)||(HMAC(-)?MD5)/", $wancfg['adv_dhcp6_authentication_statement_algorithm'])) {
			$authentication_statement .= "\talgorithm {$wancfg['adv_dhcp6_authentication_statement_algorithm']};\n";
		}
		if ($wancfg['adv_dhcp6_authentication_statement_rdm'] == 'monocounter') {
			$authentication_statement .= "\trdm {$wancfg['adv_dhcp6_authentication_statement_rdm']};\n";
		}
		$authentication_statement .= "};\n";
	}

	$key_info_statement = "";
	if (($wancfg['adv_dhcp6_key_info_statement_keyname'] != '') &&
	    ($wancfg['adv_dhcp6_key_info_statement_realm'] != '') &&
	    (is_numeric($wancfg['adv_dhcp6_key_info_statement_keyid'])) &&
	    ($wancfg['adv_dhcp6_key_info_statement_secret'] != '')) {
		$key_info_statement .= "keyinfo";
		$key_info_statement .= " {$wancfg['adv_dhcp6_key_info_statement_keyname']}";
		$key_info_statement .= " {\n";
		$key_info_statement .= "\trealm \"{$wancfg['adv_dhcp6_key_info_statement_realm']}\";\n";
		$key_info_statement .= "\tkeyid {$wancfg['adv_dhcp6_key_info_statement_keyid']};\n";
		$key_info_statement .= "\tsecret \"{$wancfg['adv_dhcp6_key_info_statement_secret']}\";\n";
		if (preg_match("/((([0-9]{4}-)?[0-9]{2}[0-9]{2} )?[0-9]{2}:[0-9]{2})||(foreever)/", $wancfg['adv_dhcp6_key_info_statement_expire'])) {
			$key_info_statement .= "\texpire \"{$wancfg['adv_dhcp6_key_info_statement_expire']}\";\n";
		}
		$key_info_statement .= "};\n";
	}

	$dhcp6cconf  = $interface_statement;
	$dhcp6cconf .= $id_assoc_statement_address;
	$dhcp6cconf .= $id_assoc_statement_prefix;
	$dhcp6cconf .= $authentication_statement;
	$dhcp6cconf .= $key_info_statement;

	$dhcp6cconf = DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);

	return $dhcp6cconf;
}


function DHCP6_Config_File_Override($wancfg, $wanif) {

	$dhcp6cconf = @file_get_contents($wancfg['adv_dhcp6_config_file_override_path']);

	if ($dhcp6cconf === false) {
		log_error(sprintf(gettext('Error: cannot open %s in DHCP6_Config_File_Override() for reading.'), $wancfg['adv_dhcp6_config_file_override_path']));
		return '';
	} else {
		return DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);;
	}
}


function DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf) {

	$dhcp6cconf = DHCP_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);

	return $dhcp6cconf;
}


function interface_dhcp_configure($interface = "wan") {
	global $config, $g, $vlanprio_values;

	$ifcfg = $config['interfaces'][$interface];
	if (empty($ifcfg)) {
		$ifcfg = array();
	}

	$dhclientconf_vlantag = "";
	if (isset($ifcfg['dhcpvlanenable']) && isset($ifcfg['dhcpcvpt'])) {
		$dhclientconf_vlantag = "vlan-pcp {$vlanprio_values[$ifcfg['dhcpcvpt']]};\n";
	}

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$interface}.conf", "w");
	if (!$fd) {
		log_error(sprintf(gettext("Error: cannot open dhclient_%s.conf in interface_dhcp_configure() for writing."), $interface));
		return 1;
	}

	if ($ifcfg['dhcphostname']) {
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$ifcfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$ifcfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

	$realif = get_real_interface($interface);
	if (empty($realif)) {
		log_error(sprintf(gettext("Invalid interface \"%s\" in interface_dhcp_configure()"), $interface));
		return 0;
	}
	$dhclientconf = "";

	$dhclientconf .= <<<EOD
interface "{$realif}" {
	supersede interface-mtu 0;
	timeout 60;
	retry 15;
	select-timeout 0;
	initial-interval 1;
	{$dhclientconf_vlantag}
	{$dhclientconf_hostname}
	script "/usr/local/sbin/pfSense-dhclient-script";
EOD;

	if (validate_ipv4_list($ifcfg['dhcprejectfrom'])) {
		$dhclientconf .= <<<EOD

	reject {$ifcfg['dhcprejectfrom']};
EOD;
	}
	$dhclientconf .= <<<EOD

}

EOD;

	// DHCP Config File Advanced
	if ($ifcfg['adv_dhcp_config_advanced']) {
		$dhclientconf = DHCP_Config_File_Advanced($interface, $ifcfg, $realif);
	}

	if (is_ipaddr($ifcfg['alias-address'])) {
		$subnetmask = gen_subnet_mask($ifcfg['alias-subnet']);
		$dhclientconf .= <<<EOD
alias {
	interface "{$realif}";
	fixed-address {$ifcfg['alias-address']};
	option subnet-mask {$subnetmask};
}

EOD;
	}

	// DHCP Config File Override
	if ($ifcfg['adv_dhcp_config_file_override']) {
		$dhclientconf = DHCP_Config_File_Override($ifcfg, $realif);
	}

	fwrite($fd, $dhclientconf);
	fclose($fd);

	/* bring wan interface up before starting dhclient */
	if ($realif) {
		interfaces_bring_up($realif);
	}

	/* Make sure dhclient is not running */
	kill_dhclient_process($realif);

	/* fire up dhclient */
	mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$realif} > {$g['tmp_path']}/{$realif}_output 2> {$g['tmp_path']}/{$realif}_error_output");

	return 0;
}

function DHCP_Config_File_Advanced($interface, $ifcfg, $realif) {

	$hostname = "";
	if ($ifcfg['dhcphostname'] != '') {
		$hostname = "\tsend host-name \"{$ifcfg['dhcphostname']}\";\n";
	}

	/* DHCP Protocol Timings */
	$protocol_timings = array ('adv_dhcp_pt_timeout' => "timeout", 'adv_dhcp_pt_retry' => "retry", 'adv_dhcp_pt_select_timeout' => "select-timeout", 'adv_dhcp_pt_reboot' => "reboot", 'adv_dhcp_pt_backoff_cutoff' => "backoff-cutoff", 'adv_dhcp_pt_initial_interval' => "initial-interval");
	foreach ($protocol_timings as $Protocol_Timing => $PT_Name) {
		$pt_variable = "{$Protocol_Timing}";
		${$pt_variable} = "";
		if ($ifcfg[$Protocol_Timing] != "") {
			${$pt_variable} = "{$PT_Name} {$ifcfg[$Protocol_Timing]};\n";
		}
	}

	$send_options = "";
	if ($ifcfg['adv_dhcp_send_options'] != '') {
		$options = DHCP_Config_Option_Split($ifcfg['adv_dhcp_send_options']);
		foreach ($options as $option) {
			$send_options .= "\tsend " . trim($option) . ";\n";
		}
	}

	$request_options = "";
	if ($ifcfg['adv_dhcp_request_options'] != '') {
		$request_options = "\trequest {$ifcfg['adv_dhcp_request_options']};\n";
	}

	$required_options = "";
	if ($ifcfg['adv_dhcp_required_options'] != '') {
		$required_options = "\trequire {$ifcfg['adv_dhcp_required_options']};\n";
	}

	$option_modifiers = "";
	if ($ifcfg['adv_dhcp_option_modifiers'] != '') {
		$modifiers = DHCP_Config_Option_Split($ifcfg['adv_dhcp_option_modifiers']);
		foreach ($modifiers as $modifier) {
			$option_modifiers .= "\t" . trim($modifier) . ";\n";
		}
	}

	$dhclientconf  = "interface \"{$realif}\" {\n";
	$dhclientconf .= "\n";
	$dhclientconf .= "\tsupersede interface-mtu 0;\n";
	$dhclientconf .= "# DHCP Protocol Timing Values\n";
	$dhclientconf .= "{$adv_dhcp_pt_timeout}";
	$dhclientconf .= "{$adv_dhcp_pt_retry}";
	$dhclientconf .= "{$adv_dhcp_pt_select_timeout}";
	$dhclientconf .= "{$adv_dhcp_pt_reboot}";
	$dhclientconf .= "{$adv_dhcp_pt_backoff_cutoff}";
	$dhclientconf .= "{$adv_dhcp_pt_initial_interval}";
	$dhclientconf .= "\n";
	$dhclientconf .= "# DHCP Protocol Options\n";
	$dhclientconf .= "{$hostname}";
	$dhclientconf .= "{$send_options}";
	$dhclientconf .= "{$request_options}";
	$dhclientconf .= "{$required_options}";
	$dhclientconf .= "{$option_modifiers}";
	$dhclientconf .= "\n";
	if (is_ipaddrv4($ifcfg['dhcprejectfrom'])) {
		$dhclientconf .= "reject {$ifcfg['dhcprejectfrom']};\n";
	}
	$dhclientconf .= "\tscript \"/usr/local/sbin/pfSense-dhclient-script\";\n";
	$dhclientconf .= "}\n";

	$dhclientconf = DHCP_Config_File_Substitutions($ifcfg, $realif, $dhclientconf);

	return $dhclientconf;
}

function DHCP_Config_Option_Split($option_string) {
	preg_match_all('/[^",]*(?:"[^"]*"[^",]*)+(?:"[^",]*)?|[^,]+/m', $option_string, $matches, PREG_PATTERN_ORDER);
	return $matches ? $matches[0] : [];
}

function DHCP_Config_File_Override($ifcfg, $realif) {

	$dhclientconf = @file_get_contents($ifcfg['adv_dhcp_config_file_override_path']);

	if ($dhclientconf === false) {
		log_error(sprintf(gettext("Error: cannot open %s in DHCP_Config_File_Override() for reading."), $ifcfg['adv_dhcp_config_file_override_path']));
		return '';
	} else {
		return DHCP_Config_File_Substitutions($ifcfg, $realif, $dhclientconf);
	}
}


function DHCP_Config_File_Substitutions($ifcfg, $realif, $dhclientconf) {

	/* Apply Interface Substitutions */
	$dhclientconf = str_replace("{interface}", "{$realif}", $dhclientconf);

	/* Apply Hostname Substitutions */
	$dhclientconf = str_replace("{hostname}", $ifcfg['dhcphostname'], $dhclientconf);

	/* Arrays of MAC Address Types, Cases, Delimiters */
	/* ASCII or HEX, Upper or Lower Case, Various Delimiters (none, space, colon, hyphen, period) */
	$various_mac_types      = array("mac_addr_ascii", "mac_addr_hex");
	$various_mac_cases      = array("U", "L");
	$various_mac_delimiters = array("", " ", ":", "-", ".");

	/* Apply MAC Address Substitutions */
	foreach ($various_mac_types as $various_mac_type) {
		foreach ($various_mac_cases as $various_mac_case) {
			foreach ($various_mac_delimiters as $various_mac_delimiter) {

				$res = stripos($dhclientconf, $various_mac_type . $various_mac_case . $various_mac_delimiter);
				if ($res !== false) {

					/* Get MAC Address as ASCII String With Colon (:) delimiters */
					if ("$various_mac_case" == "U") {
						$dhcpclientconf_mac = strtoupper(get_interface_mac($realif));
					}
					if ("$various_mac_case" == "L") {
						$dhcpclientconf_mac = strtolower(get_interface_mac($realif));
					}

					if ("$various_mac_type" == "mac_addr_hex") {
						/* Convert MAC ascii string to HEX with colon (:) delimiters. */
						$dhcpclientconf_mac = str_replace(":", "", $dhcpclientconf_mac);
						$dhcpclientconf_mac_hex = "";
						$delimiter = "";
						for ($i = 0; $i < strlen($dhcpclientconf_mac); $i++) {
							$dhcpclientconf_mac_hex .= $delimiter. bin2hex($dhcpclientconf_mac[$i]);
							$delimiter = ":";
						}
						$dhcpclientconf_mac = $dhcpclientconf_mac_hex;
					}

					/* MAC Address Delimiter Substitutions */
					$dhcpclientconf_mac = str_replace(":", $various_mac_delimiter, $dhcpclientconf_mac);

					/* Apply MAC Address Substitutions */
					$dhclientconf = str_replace("{" . $various_mac_type . $various_mac_case . $various_mac_delimiter . "}", $dhcpclientconf_mac, $dhclientconf);
				}
			}
		}
	}

	return $dhclientconf;
}

function interfaces_group_setup() {
	global $config;

	if (!isset($config['ifgroups']['ifgroupentry']) ||
	    !is_array($config['ifgroups']['ifgroupentry'])) {
		return;
	}

	foreach ($config['ifgroups']['ifgroupentry'] as $groupar) {
		interface_group_setup($groupar);
	}

	return;
}

function interface_group_setup(&$groupname /* The parameter is an array */) {
	global $config;

	if (!is_array($groupname)) {
		return;
	}
	$members = explode(" ", $groupname['members']);
	foreach ($members as $ifs) {
		$realif = get_real_interface($ifs);
		if ($realif && does_interface_exist($realif)) {
			mwexec("/sbin/ifconfig {$realif} group {$groupname['ifname']}");
		}
	}

	return;
}

function is_interface_group($if) {
	global $config;

	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $groupentry) {
			if ($groupentry['ifname'] === $if) {
				return true;
			}
		}
	}

	return false;
}

function interface_group_add_member($interface, $groupname) {
	$interface = get_real_interface($interface);
	if (does_interface_exist($interface)) {
		mwexec("/sbin/ifconfig {$interface} group " . escapeshellarg($groupname), true);
	}
}

/* COMPAT Function */
function convert_friendly_interface_to_real_interface_name($interface) {
	return get_real_interface($interface);
}

/* COMPAT Function */
function get_real_wan_interface($interface = "wan") {
	return get_real_interface($interface);
}

/* COMPAT Function */
function get_current_wan_address($interface = "wan") {
	return get_interface_ip($interface);
}

/*
 * convert_real_interface_to_friendly_interface_name($interface): convert fxp0 -> wan, etc.
 */
function convert_real_interface_to_friendly_interface_name($interface = "wan", $checkparent = false) {
	global $config;

	/* XXX: For speed reasons reference directly the interface array */
	init_config_arr(array('interfaces'));
	$ifdescrs = &$config['interfaces'];
	//$ifdescrs = get_configured_interface_list(true);

	foreach ($ifdescrs as $if => $ifname) {
		if ($if == $interface || $ifname['if'] == $interface) {
			return $if;
		}

		if (get_real_interface($if) == $interface) {
			return $if;
		}

		if ($checkparent == false) {
			continue;
		}

		$int = get_parent_interface($if, true);
		if (is_array($int)) {
			foreach ($int as $iface) {
				if ($iface == $interface) {
					return $if;
				}
			}
		}
	}

	if ($interface == "enc0") {
		return 'IPsec';
	}
}

/* attempt to resolve interface to friendly descr */
function convert_friendly_interface_to_friendly_descr($interface) {
	global $config;

	switch ($interface) {
		case "l2tp":
			$ifdesc = "L2TP";
			break;
		case "pptp":
			$ifdesc = "PPTP";
			break;
		case "pppoe":
			$ifdesc = "PPPoE";
			break;
		case "openvpn":
			$ifdesc = "OpenVPN";
			break;
		case "lo0":
			$ifdesc = "Loopback";
			break;
		case "enc0":
		case "ipsec":
		case "IPsec":
			$ifdesc = "IPsec";
			break;
		default:
			if (isset($config['interfaces'][$interface])) {
				if (empty($config['interfaces'][$interface]['descr'])) {
					$ifdesc = strtoupper($interface);
				} else {
					$ifdesc = strtoupper($config['interfaces'][$interface]['descr']);
				}
				break;
			} elseif (substr($interface, 0, 4) == '_vip') {
				if (is_array($config['virtualip']['vip'])) {
					foreach ($config['virtualip']['vip'] as $counter => $vip) {
						if ($vip['mode'] == "carp") {
							if ($interface == "_vip{$vip['uniqid']}") {
								$descr = $vip['subnet'];
								$descr .= " (vhid {$vip['vhid']})";
								if (!empty($vip['descr'])) {
									$descr .= " - " .$vip['descr'];
								}
								return $descr;
							}
						}
					}
				}
			} elseif (substr($interface, 0, 5) == '_lloc') {
				return get_interface_linklocal($interface);
			} else {
				if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
					foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
						if ($ifgen['ifname'] === $interface) {
							return $ifgen['ifname'];
						}
					}
				}

				/* if list */
				$ifdescrs = get_configured_interface_with_descr(true);
				foreach ($ifdescrs as $if => $ifname) {
					if ($if == $interface || $ifname == $interface) {
						return $ifname;
					}
				}
			}
			break;
	}

	return $ifdesc;
}

function convert_real_interface_to_friendly_descr($interface) {

	$ifdesc = convert_real_interface_to_friendly_interface_name("{$interface}");

	if (!empty($ifdesc)) {
		return convert_friendly_interface_to_friendly_descr($ifdesc);
	}

	return $interface;
}

/*
 *  get_parent_interface($interface):
 *			--returns the (real or virtual) parent interface(s) array for a given interface friendly name (i.e. wan)
 *				or virtual interface (i.e. vlan)
 *				(We need array because MLPPP and bridge interfaces have more than one parent.)
 *			-- returns $interface passed in if $interface parent is not found
 *			-- returns empty array if an invalid interface is passed
 *	(Only handles ppps and vlans now.)
 */
function get_parent_interface($interface, $avoidrecurse = false) {
	global $config;

	$parents = array();
	//Check that we got a valid interface passed
	$realif = get_real_interface($interface);
	if ($realif == NULL) {
		return $parents;
	}

	// If we got a real interface, find it's friendly assigned name
	if ($interface == $realif && $avoidrecurse == false) {
		$interface = convert_real_interface_to_friendly_interface_name($interface);
	}

	if (!empty($interface) && isset($config['interfaces'][$interface])) {
		$ifcfg = $config['interfaces'][$interface];
		switch ($ifcfg['ipaddr']) {
			case "ppp":
			case "pppoe":
			case "pptp":
			case "l2tp":
				if (empty($parents)) {
					if (is_array($config['ppps']['ppp'])) {
						foreach ($config['ppps']['ppp'] as $pppidx => $ppp) {
							if ($ifcfg['if'] == $ppp['if']) {
								$ports = explode(',', $ppp['ports']);
								foreach ($ports as $pid => $parent_if) {
									$parents[$pid] = get_real_interface($parent_if);
								}
								break;
							}
						}
					}
				}
				break;
			case "dhcp":
			case "static":
			default:
				// Handle _vlans
				$vlan = interface_is_vlan($ifcfg['if']);
				if ($vlan != NULL) {
					$parents[0] = $vlan['if'];
				}
				break;
		}
	}

	if (empty($parents)) {
		// Handle _vlans not assigned to an interface
		$vlan = interface_is_vlan($realif);
		if ($vlan != NULL) {
			$parents[0] = $vlan['if'];
		}
	}

	if (empty($parents)) {
		/* Handle LAGGs. */
		$lagg = interface_is_type($realif, 'lagg');
		if ($lagg != NULL && isset($lagg['members'])) {
			$parents = explode(",", $lagg['members']);
		}
	}

	if (empty($parents)) {
		$parents[0] = $realif;
	}

	return $parents;
}

/*
 *  get_parent_physical_interface($interface):
 *   - returns an array of parent interface(s) for a given interface friendly name (e.g. wan)
 *  differs from get_parent_interface in that it traverses to find the physical NICs on lagg
 */
function get_parent_physical_interface($interface) {
	global $config;

	$realif = get_parent_interface($interface);

	if (substr($realif[0], 0, 4) == "lagg") {
		foreach ($config['laggs']['lagg'] as $lagg) {
			if ($realif[0] == $lagg['laggif']) {
				return explode(",", $lagg['members']);
			}
		}
	} else {
		return $realif;
	}
}

function interface_is_wireless_clone($wlif) {
	if (!stristr($wlif, "_wlan")) {
		return false;
	} else {
		return true;
	}
}

function interface_get_wireless_base($wlif) {
	if (!stristr($wlif, "_wlan")) {
		return $wlif;
	} else {
		return substr($wlif, 0, stripos($wlif, "_wlan"));
	}
}

function interface_get_wireless_clone($wlif) {
	if (!stristr($wlif, "_wlan")) {
		return $wlif . "_wlan0";
	} else {
		return $wlif;
	}
}

function interface_list_wireless() {
	$portlist = explode(" ", get_single_sysctl("net.wlan.devices"));

	$result = array();
	foreach ($portlist as $port) {
		if (preg_match("/^([^0-9]+)([0-9]+)/", $port, $matches) != 1) {
			continue;
		}

		$desc = $port . " ( " . get_single_sysctl(
		    "dev.{$matches[1]}.{$matches[2]}.%desc") . " )";

		$result[] = array(
		    "if" => $port,
		    "descr" => $desc
		);
	}

	return $result;
}

function get_real_interface($interface = "wan", $family = "all", $realv6iface = false, $flush = false) {
	global $config, $g;

	$wanif = NULL;

	switch ($interface) {
		case "l2tp":
			$wanif = "l2tp";
			break;
		case "pptp":
			$wanif = "pptp";
			break;
		case "pppoe":
			$wanif = "pppoe";
			break;
		case "openvpn":
			$wanif = "openvpn";
			break;
		case "IPsec":
		case "ipsec":
		case "enc0":
			$wanif = "enc0";
			break;
		case "ppp":
			$wanif = "ppp";
			break;
		default:
			if (substr($interface, 0, 4) == '_vip') {
				$wanif = get_configured_vip_interface($interface);
				if (!empty($wanif)) {
					$wanif = get_real_interface($wanif);
				}
				break;
			} elseif (substr($interface, 0, 5) == '_lloc') {
				$interface = substr($interface, 5);
			} elseif (interface_is_vlan($interface) != NULL ||
			    does_interface_exist($interface, $flush)) {
				/*
				 * If a real interface was already passed simply
				 * pass the real interface back.  This encourages
				 * the usage of this function in more cases so that
				 * we can combine logic for more flexibility.
				 */
				$wanif = $interface;
				break;
			}

			if (empty($config['interfaces'][$interface])) {
				break;
			}

			$cfg = &$config['interfaces'][$interface];

			if ($family == "inet6") {
				switch ($cfg['ipaddrv6']) {
					case "6rd":
					case "6to4":
						$wanif = "{$interface}_stf";
						break;
					case 'pppoe':
					case 'ppp':
					case 'l2tp':
					case 'pptp':
						if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
							$wanif = interface_get_wireless_clone($cfg['if']);
						} else {
							$wanif = $cfg['if'];
						}
						break;
					default:
						switch ($cfg['ipaddr']) {
							case 'pppoe':
							case 'ppp':
							case 'l2tp':
							case 'pptp':
								// Added catch for static v6 but using v4 link. Sets things to use pppoe link
								if ((isset($cfg['dhcp6usev4iface']) && $realv6iface === false) ||
								    isset($cfg['ipv6usev4iface']) || isset($cfg['slaacusev4iface'])) {
									$wanif = $cfg['if'];
								} else {
									$parents = get_parent_interface($interface);
									if (!empty($parents[0])) {
										$wanif = $parents[0];
									} else {
										$wanif = $cfg['if'];
									}
								}
								break;
							default:
								if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
									$wanif = interface_get_wireless_clone($cfg['if']);
								} else {
									$wanif = $cfg['if'];
								}
								break;
						}
						break;
				}
			} else {
				// Wireless cloned NIC support (FreeBSD 8+)
				// interface name format: $parentnic_wlanparentnic#
				// example: ath0_wlan0
				if (is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if'])) {
					$wanif = interface_get_wireless_clone($cfg['if']);
				} else {
					$wanif = $cfg['if'];
				}
			}
			break;
	}

	return $wanif;
}

/* Guess the physical interface by providing a IP address */
function guess_interface_from_ip($ipaddress) {

	if (!is_ipaddr($ipaddress)) {
		return false;
	}

	$route = route_get($ipaddress);
	if (empty($route)) {
		return false;
	}

	if (!empty($route[0]['interface-name'])) {
		return $route[0]['interface-name'];
	}

	return false;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 *   (or if $bits is specified, where an IP within the subnet is defined)
 */
function find_ip_interface($ip, $bits = null) {
	if (!is_ipaddr($ip)) {
		return false;
	}

	$isv6ip = is_ipaddrv6($ip);

	/* if list */
	$ifdescrs = get_configured_interface_list();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		$ifip = ($isv6ip) ? get_interface_ipv6($ifname) : get_interface_ip($ifname);
		if (is_null($ifip)) {
			continue;
		}
		if (is_null($bits)) {
			if ($ip == $ifip) {
				$int = get_real_interface($ifname);
				return $int;
			}
		} else {
			if (ip_in_subnet($ifip, $ip . "/" . $bits)) {
				$int = get_real_interface($ifname);
				return $int;
			}
		}
	}

	return false;
}

/*
 * find_virtual_ip_alias($ip): return the virtual IP alias where an IP is found
 *   (or if $bits is specified, where an IP within the subnet is found)
 */
function find_virtual_ip_alias($ip, $bits = null) {
	global $config;

	if (!is_array($config['virtualip']['vip'])) {
		return false;
	}
	if (!is_ipaddr($ip)) {
		return false;
	}

	$isv6ip = is_ipaddrv6($ip);

	foreach ($config['virtualip']['vip'] as $vip) {
		if ($vip['mode'] === "ipalias") {
			if (is_ipaddrv6($vip['subnet']) != $isv6ip) {
				continue;
			}
			if (is_null($bits)) {
				if (ip_in_subnet($ip, $vip['subnet'] . "/" . $vip['subnet_bits'])) {
					return $vip;
				}
			} else {
				if (($isv6ip && check_subnetsv6_overlap($ip, $bits, $vip['subnet'], $vip['subnet_bits'])) ||
				    (!$isv6ip && check_subnets_overlap($ip, $bits, $vip['subnet'], $vip['subnet_bits']))) {
					return $vip;
				}
			}
		}
	}
	return false;
}

function link_interface_to_track6($int, $action = "") {
	global $config;

	if (empty($int)) {
		return;
	}

	if (is_array($config['interfaces'])) {
		$list = array();
		foreach ($config['interfaces'] as $ifname => $ifcfg) {
			if (!isset($ifcfg['enable'])) {
				continue;
			}
			if (!empty($ifcfg['ipaddrv6']) && $ifcfg['track6-interface'] == $int) {
				if ($action == "update") {
					interface_track6_configure($ifname, $ifcfg);
				} elseif ($action == "") {
					$list[$ifname] = $ifcfg;
				}
			}
		}
		return $list;
	}
}

function interface_find_child_cfgmtu($realiface) {
	global $config;

	$interface = convert_real_interface_to_friendly_interface_name($realiface);
	$vlans = link_interface_to_vlans($realiface);
	$qinqs = link_interface_to_qinqs($realiface);
	$bridge = link_interface_to_bridge($realiface);
	if (!empty($interface)) {
		$gifs = link_interface_to_tunnelif($interface, 'gif');
		$gres = link_interface_to_tunnelif($interface, 'gre');
	} else {
		$gifs = array();
		$gres = array();
	}

	$mtu = 0;
	if (is_array($vlans)) {
		foreach ($vlans as $vlan) {
			$ifass = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
			if (empty($ifass)) {
				continue;
			}
			if (!empty($config['interfaces'][$ifass]['mtu'])) {
				if (intval($config['interfaces'][$ifass]['mtu']) > $mtu) {
					$mtu = intval($config['interfaces'][$ifass]['mtu']);
				}
			}
		}
	}
	if (is_array($qinqs)) {
		foreach ($qinqs as $qinq) {
			$ifass = convert_real_interface_to_friendly_interface_name($qinq['vlanif']);
			if (empty($ifass)) {
				continue;
			}
			if (!empty($config['interfaces'][$ifass]['mtu'])) {
				if (intval($config['interfaces'][$ifass]['mtu']) > $mtu) {
					$mtu = intval($config['interfaces'][$ifass]['mtu']);
				}
			}
		}
	}
	if (is_array($gifs)) {
		foreach ($gifs as $gif) {
			$ifass = convert_real_interface_to_friendly_interface_name($gif['gifif']);
			if (empty($ifass)) {
				continue;
			}
			if (!empty($config['interfaces'][$ifass]['mtu'])) {
				if (intval($config['interfaces'][$ifass]['mtu']) > $mtu) {
					$mtu = intval($config['interfaces'][$ifass]['mtu']);
				}
			}
		}
	}
	if (is_array($gres)) {
		foreach ($gres as $gre) {
			$ifass = convert_real_interface_to_friendly_interface_name($gre['greif']);
			if (empty($ifass)) {
				continue;
			}
			if (!empty($config['interfaces'][$ifass]['mtu'])) {
				if (intval($config['interfaces'][$ifass]['mtu']) > $mtu) {
					$mtu = intval($config['interfaces'][$ifass]['mtu']);
				}
			}
		}
	}
	$ifass = convert_real_interface_to_friendly_interface_name($bridge);
	if (!empty($ifass) && !empty($config['interfaces'][$ifass]['mtu'])) {
		if (intval($config['interfaces'][$ifass]['mtu']) > $mtu) {
			$mtu = intval($config['interfaces'][$ifass]['mtu']);
		}
	}
	unset($vlans, $bridge, $gifs, $gres, $ifass, $vlan);

	return $mtu;
}

function link_interface_to_vlans($int, $action = "") {
	global $config;

	if (empty($int)) {
		return;
	}

	if (is_array($config['vlans']['vlan'])) {
		$ifaces = array();
		foreach ($config['vlans']['vlan'] as $vlan) {
			if ($int == $vlan['if']) {
				if ($action == "update") {
					interfaces_bring_up($int);
				} else {
					$ifaces[$vlan['tag']] = $vlan;
				}
			}
		}
		if (!empty($ifaces)) {
			return $ifaces;
		}
	}
}

function link_interface_to_qinqs($int, $action = "") {
	global $config;

	if (empty($int)) {
		return;
	}

	if (is_array($config['qinqs']['qinqentry'])) {
		$ifaces = array();
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			if ($int == $qinq['if']) {
				if ($action == "update") {
					interfaces_bring_up($int);
				} else {
					$ifaces[$qinq['tag']] = $qinq;
				}
			}
		}
		if (!empty($ifaces)) {
			return $ifaces;
		}
	}
}

function link_interface_to_vips($int, $action = "", $vhid = '') {
	global $config;

	$updatevips = false;
	if (is_array($config['virtualip']['vip'])) {
		$result = array();
		foreach ($config['virtualip']['vip'] as $vip) {
			if (substr($vip['interface'], 0, 4) == "_vip") {
				$iface = get_configured_vip_interface($vip['interface']);
			} else {
				$iface = $vip['interface'];
			}
			if ($int != $iface) {
				continue;
			}
			if ($action == "update") {
				$updatevips = true;
			} else {
				if (empty($vhid) || ($vhid == $vip['vhid']) ||
				    substr($vip['interface'], 0, 4) == "_vip") {
					$result[] = $vip;
				}
			}
		}
		if ($updatevips === true) {
			interfaces_vips_configure($int);
		}
		return $result;
	}

	return NULL;
}

/****f* interfaces/link_interface_to_bridge
 * NAME
 *   link_interface_to_bridge - Finds out a bridge group for an interface
 * INPUTS
 *   $ip
 * RESULT
 *   bridge[0-99]
 ******/
function link_interface_to_bridge($int) {
	global $config;

	if (isset($config['bridges']['bridged']) && is_array($config['bridges']['bridged'])) {
		foreach ($config['bridges']['bridged'] as $bridge) {
			if (in_array($int, explode(',', $bridge['members']))) {
				return "{$bridge['bridgeif']}";
			}
		}
	}
}

function link_interface_to_lagg($int) {
	global $config;

	if (isset($config['laggs']['lagg']) && is_array($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as $lagg) {
			if (in_array($int, explode(',', $lagg['members']))) {
				return "{$lagg['laggif']}";
			}
		}
	}
}

function link_interface_to_group($int) {
	global $config;

	$result = array();

	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $group) {
			if (in_array($int, explode(" ", $group['members']))) {
				$result[$group['ifname']] = $int;
			}
		}
	}

	return $result;
}

function link_interface_to_tunnelif($interface, $type) {
	global $config;

	if (!in_array($type, array('gre', 'gif'))) {
		return;
	}

	$result = array();

	if (is_array($config["{$type}s"][$type])) {
		foreach ($config["{$type}s"][$type] as $tunnel) {
			if ($tunnel['if'] == $interface) {
				$result[] = $tunnel;
			}
		}
	}

	return $result;
}

/*
 * find_interface_ip($interface): return the interface ip (first found)
 */
function find_interface_ip($interface, $flush = false) {
	global $interface_ip_arr_cache;
	global $interface_sn_arr_cache;

	$interface = str_replace("\n", "", $interface);

	if (!does_interface_exist($interface)) {
		return;
	}

	/* Setup IP cache */
	if (!isset($interface_ip_arr_cache[$interface]) or $flush) {
		if (file_exists("/var/db/${interface}_ip")) {
			$ifip = chop(file_get_contents("/var/db/${interface}_ip"));
			$ifaddrs = pfSense_getall_interface_addresses($interface);
			foreach ($ifaddrs as $ifaddr) {
				list($ip, $mask) = explode("/", $ifaddr);
				if ($ip == $ifip) {
					$interface_ip_arr_cache[$interface] = $ip;
					$interface_sn_arr_cache[$interface] = $mask;
					break;
				}
			}
		}
		if (!isset($interface_ip_arr_cache[$interface])) {
			$ifinfo = pfSense_get_interface_addresses($interface);
			$interface_ip_arr_cache[$interface] = $ifinfo['ipaddr'];
			$interface_sn_arr_cache[$interface] = $ifinfo['subnetbits'];
		}
	}

	return $interface_ip_arr_cache[$interface];
}

/*
 * find_interface_ipv6($interface): return the interface ip (first found)
 */
function find_interface_ipv6($interface, $flush = false) {
	global $interface_ipv6_arr_cache;
	global $interface_snv6_arr_cache;
	global $config;

	$interface = trim($interface);
	$interface = get_real_interface($interface);

	if (!does_interface_exist($interface)) {
		return;
	}

	/* Setup IP cache */
	if (!isset($interface_ipv6_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_get_interface_addresses($interface);
		$interface_ipv6_arr_cache[$interface] = $ifinfo['ipaddr6'];
		$interface_snv6_arr_cache[$interface] = $ifinfo['subnetbits6'];
	}

	return $interface_ipv6_arr_cache[$interface];
}

/*
 * find_interface_ipv6_ll($interface): return the interface ipv6 link local (first found)
 */
function find_interface_ipv6_ll($interface, $flush = false) {
	global $interface_llv6_arr_cache;
	global $config;

	$interface = str_replace("\n", "", $interface);

	if (!does_interface_exist($interface)) {
		return;
	}

	/* Setup IP cache */
	if (!isset($interface_llv6_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_getall_interface_addresses($interface);
		foreach ($ifinfo as $line) {
			if (strstr($line, ":")) {
				$parts = explode("/", $line);
				if (is_linklocal($parts[0])) {
					$ifinfo['linklocal'] = $parts[0];
				}
			}
		}
		$interface_llv6_arr_cache[$interface] = $ifinfo['linklocal'];
	}
	return $interface_llv6_arr_cache[$interface];
}

function find_interface_subnet($interface, $flush = false) {
	global $interface_sn_arr_cache;
	global $interface_ip_arr_cache;

	$interface = str_replace("\n", "", $interface);
	if (does_interface_exist($interface) == false) {
		return;
	}

	if (!isset($interface_sn_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_get_interface_addresses($interface);
		$interface_ip_arr_cache[$interface] = $ifinfo['ipaddr'];
		$interface_sn_arr_cache[$interface] = $ifinfo['subnetbits'];
	}

	return $interface_sn_arr_cache[$interface];
}

function find_interface_subnetv6($interface, $flush = false) {
	global $interface_snv6_arr_cache;
	global $interface_ipv6_arr_cache;

	$interface = str_replace("\n", "", $interface);
	if (does_interface_exist($interface) == false) {
		return;
	}

	if (!isset($interface_snv6_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_get_interface_addresses($interface);
		$interface_ipv6_arr_cache[$interface] = $ifinfo['ipaddr6'];
		$interface_snv6_arr_cache[$interface] = $ifinfo['subnetbits6'];
	}

	return $interface_snv6_arr_cache[$interface];
}

function ip_in_interface_alias_subnet($interface, $ipalias) {
	global $config;

	if (empty($interface) || !is_ipaddr($ipalias)) {
		return false;
	}
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			switch ($vip['mode']) {
				case "ipalias":
					if ($vip['interface'] <> $interface) {
						break;
					}
					$subnet = is_ipaddrv6($ipalias) ? gen_subnetv6($vip['subnet'], $vip['subnet_bits']) : gen_subnet($vip['subnet'], $vip['subnet_bits']);
					if (ip_in_subnet($ipalias, $subnet . "/" . $vip['subnet_bits'])) {
						return true;
					}
					break;
			}
		}
	}

	return false;
}

function get_possible_listen_ips($include_ipv6_link_local=false) {

	$interfaces = get_configured_interface_with_descr();
	foreach ($interfaces as $iface => $ifacename) {
		if ($include_ipv6_link_local) {
			/* This is to avoid going though added ll below */
			if (substr($iface, 0, 5) == '_lloc') {
				continue;
			}
			$llip = find_interface_ipv6_ll(get_real_interface($iface));
			if (!empty($llip)) {
				$interfaces["_lloc{$iface}"] = "{$ifacename} IPv6 Link-Local";
			}
		}
	}
	$viplist = get_configured_vip_list();
	foreach ($viplist as $vip => $address) {
		$interfaces[$vip] = $address;
		if (get_vip_descr($address)) {
			$interfaces[$vip] .= " (". get_vip_descr($address) .")";
		}
	}

	$interfaces['lo0'] = 'Localhost';

	return $interfaces;
}

function get_possible_traffic_source_addresses($include_ipv6_link_local=false) {
	global $config;

	$sourceips = get_possible_listen_ips($include_ipv6_link_local);
	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$sourceips_key = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$sourceips[$sourceips_key] = gettext("OpenVPN") . " " . $mode . ": " . htmlspecialchars($setting['description']);
				}
			}
		}
	}
	if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			if ($ph1ent['disabled']) {
				continue;
			}
			if (ipsec_vti($ph1ent)) {
				$sourceips_key = "ipsec{$ph1ent['ikeid']}";
				$sourceips[$sourceips_key] = gettext("IPsec VTI") . ": " . htmlspecialchars($ph1ent['descr']);
			}
		}
	}
	return $sourceips;
}

function get_interface_ip($interface = "wan") {
	global $config;

	if (substr($interface, 0, 4) == '_vip') {
		return get_configured_vip_ipv4($interface);
	} elseif (substr($interface, 0, 5) == '_lloc') {
		/* No link-local address for v4. */
		return null;
	}

	$realif = get_failover_interface($interface, 'inet');
	if (!$realif) {
		return null;
	}

	if (substr($realif, 0, 4) == '_vip') {
		return get_configured_vip_ipv4($realif);
	} elseif (substr($realif, 0, 5) == '_lloc') {
		/* No link-local address for v4. */
		return null;
	}

	if (is_array($config['interfaces'][$interface]) &&
	    is_ipaddr($config['interfaces'][$interface]['ipaddr'])) {
		return ($config['interfaces'][$interface]['ipaddr']);
	}

	/*
	 * Beaware that find_interface_ip() is our last option, it will
	 * return the first IP it find on interface, not necessarily the
	 * main IP address.
	 */
	$curip = find_interface_ip($realif);
	if ($curip && is_ipaddr($curip) && ($curip != "0.0.0.0")) {
		return $curip;
	} else {
		return null;
	}
}

function get_interface_ipv6($interface = "wan", $flush = false, $linklocal_fallback = false) {
	global $config;

	if (substr($interface, 0, 4) == '_vip') {
		return get_configured_vip_ipv6($interface);
	} elseif (substr($interface, 0, 5) == '_lloc') {
		return get_interface_linklocal($interface);
	}

	$realif = get_failover_interface($interface, 'inet6');
	if (!$realif) {
		return null;
	}

	if (substr($realif, 0, 4) == '_vip') {
		return get_configured_vip_ipv6($realif);
	} elseif (substr($realif, 0, 5) == '_lloc') {
		return get_interface_linklocal($realif);
	}

	if (is_array($config['interfaces'][$interface])) {
		switch ($config['interfaces'][$interface]['ipaddr']) {
			case 'pppoe':
			case 'l2tp':
			case 'pptp':
			case 'ppp':
				if (($config['interfaces'][$interface]['ipaddrv6'] == 'dhcp6') ||
				    ($config['interfaces'][$interface]['ipaddrv6'] == 'slaac')) {
					$realif = get_real_interface($interface, 'inet6', false);
				}
				break;
		}
		if (is_ipaddrv6($config['interfaces'][$interface]['ipaddrv6'])) {
			return ($config['interfaces'][$interface]['ipaddrv6']);
		}
	}

	/* try to find track6 address, see https://redmine.pfsense.org/issues/5999 */
	if ($config['interfaces'][$interface]['ipaddrv6'] == 'track6') {
		$curip = get_interface_track6ip($interface);
		if ($curip) {
			return $curip[0];
		}
	}

	/*
	 * Beaware that find_interface_ip() is our last option, it will
	 * return the first IP it find on interface, not necessarily the
	 * main IP address.
	 */
	$curip = find_interface_ipv6($realif, $flush);
	if ($curip && is_ipaddrv6($curip) && ($curip != "::")) {
		return $curip;
	} else {
		/*
		 * NOTE: On the case when only the prefix is requested,
		 * the communication on WAN will be done over link-local.
		 */
		if ($linklocal_fallback || (is_array($config['interfaces'][$interface]) && isset($config['interfaces'][$interface]['dhcp6prefixonly']))) {
			$curip = find_interface_ipv6_ll($realif, $flush);
			if ($curip && is_ipaddrv6($curip) && ($curip != "::")) {
				return $curip;
			}
		}
	}
	return null;
}

function get_interface_linklocal($interface = "wan") {

	$realif = get_failover_interface($interface, 'inet6');
	if (!$realif) {
		return null;
	}

	if (substr($interface, 0, 4) == '_vip') {
		$realif = get_real_interface($interface);
	} elseif (substr($interface, 0, 5) == '_lloc') {
		$realif = get_real_interface(substr($interface, 5));
	}

	$curip = find_interface_ipv6_ll($realif);
	if ($curip && is_ipaddrv6($curip) && ($curip != "::")) {
		return $curip;
	} else {
		return null;
	}
}

function get_interface_track6ip($interface = "wan") {
	$realif = get_real_interface($interface);
	$vips = get_configured_vip_list('inet6');

	foreach (pfSense_getall_interface_addresses($realif) as $ifaddr) {
		list($ip, $bits) = explode("/", $ifaddr);
		$ip = text_to_compressed_ip6($ip);
		if (is_ipaddrv6($ip) && !is_linklocal($ip)) {
			if (is_array($vips) && !empty($vips)) {
				foreach ($vips as $vip) {
					if ($ip == text_to_compressed_ip6($vip)) {
						continue 2;
					}
				}
			}
			return array($ip, $bits);
		}
	}
	return false;
}

function get_interface_subnet($interface = "wan") {
	global $config;

	if (substr($interface, 0, 4) == '_vip') {
		return (get_configured_vip_subnetv4($interface));
	}

	if (is_array($config['interfaces'][$interface]) &&
	    !empty($config['interfaces'][$interface]['subnet']) &&
	    is_ipaddrv4($config['interfaces'][$interface]['ipaddr'])) {
		return ($config['interfaces'][$interface]['subnet']);
	}

	$realif = get_real_interface($interface);
	if (!$realif) {
		return (NULL);
	}

	$cursn = find_interface_subnet($realif);
	if (!empty($cursn)) {
		return ($cursn);
	}

	return (NULL);
}

function get_interface_subnetv6($interface = "wan") {
	global $config;

	if (substr($interface, 0, 4) == '_vip') {
		return (get_configured_vip_subnetv6($interface));
	} elseif (substr($interface, 0, 5) == '_lloc') {
		$interface = substr($interface, 5);
	}

	if (is_array($config['interfaces'][$interface]) &&
	    !empty($config['interfaces'][$interface]['subnetv6']) &&
	    is_ipaddrv6($config['interfaces'][$interface]['ipaddrv6'])) {
		return ($config['interfaces'][$interface]['subnetv6']);
	}

	$realif = get_real_interface($interface, 'inet6');
	if (!$realif) {
		return (NULL);
	}

	/* try to find track6 address, see https://redmine.pfsense.org/issues/5999 */
	if ($config['interfaces'][$interface]['ipaddrv6'] == 'track6') {
		$curip = get_interface_track6ip($interface);
		if ($curip) {
			return $curip[1];
		}
	}

	$cursn = find_interface_subnetv6($realif);
	if (!empty($cursn)) {
		return ($cursn);
	}

	return (NULL);
}

/* return outside interfaces with a gateway */
function get_interfaces_with_gateway() {
	global $config;

	$ints = array();

	/* loop interfaces, check config for outbound */
	foreach ($config['interfaces'] as $ifdescr => $ifname) {
		switch ($ifname['ipaddr']) {
			case "dhcp":
			case "pppoe":
			case "pptp":
			case "l2tp":
			case "ppp":
				$ints[$ifdescr] = $ifdescr;
				break;
			default:
				if (substr($ifname['if'], 0, 4) == "ovpn" ||
				    !empty($ifname['gateway'])) {
					$ints[$ifdescr] = $ifdescr;
				} elseif (substr($ifname['if'], 0, 5) == "ipsec" ||
				    !empty($ifname['gateway'])) {
					$ints[$ifdescr] = $ifdescr;
				} elseif (substr($ifname['if'], 0, 2) == "wg" ||
				    !empty($ifname['gateway'])) {
					$ints[$ifdescr] = $ifdescr;
				}

				break;
		}
	}
	return $ints;
}

/* return true if interface has a gateway */
function interface_has_gateway($friendly) {
	global $config;

	if (!empty($config['interfaces'][$friendly])) {
		$ifname = &$config['interfaces'][$friendly];
		switch ($ifname['ipaddr']) {
			case "dhcp":
				/* see https://redmine.pfsense.org/issues/5135 */
				if (get_interface_gateway($friendly)) {
					return true;
				}
				break;
			case "pppoe":
			case "pptp":
			case "l2tp":
			case "ppp":
				return true;
				break;
			default:
				if ((substr($ifname['if'], 0, 4) == "ovpn") ||
				    (substr($ifname['if'], 0, 5) == "ipsec") ||
				    (substr($ifname['if'], 0, 2) == "wg")) {
					return true;
				}
				$tunnelif = substr($ifname['if'], 0, 3);
				if ($tunnelif == "gif" || $tunnelif == "gre") {
					if (find_interface_ip($ifname['if'])) {
						return true;
					}
				}
				if (!empty($ifname['gateway'])) {
					return true;
				}
				break;
		}
	}

	return false;
}

/* return true if interface has a gateway */
function interface_has_gatewayv6($friendly) {
	global $config;

	if (!empty($config['interfaces'][$friendly])) {
		$ifname = &$config['interfaces'][$friendly];
		switch ($ifname['ipaddrv6']) {
			case "slaac":
			case "dhcp6":
			case "6to4":
			case "6rd":
				return true;
				break;
			default:
				if ((substr($ifname['if'], 0, 4) == "ovpn") ||
				    (substr($ifname['if'], 0, 5) == "ipsec") ||
				    (substr($ifname['if'], 0, 2) == "wg")) {
					return true;
				}
				$tunnelif = substr($ifname['if'], 0, 3);
				if ($tunnelif == "gif" || $tunnelif == "gre") {
					if (find_interface_ipv6($ifname['if'])) {
						return true;
					}
				}
				if (!empty($ifname['gatewayv6'])) {
					return true;
				}
				break;
		}
	}

	return false;
}

/****f* interfaces/is_altq_capable
 * NAME
 *   is_altq_capable - Test if interface is capable of using ALTQ
 * INPUTS
 *   $int            - string containing interface name
 * RESULT
 *   boolean         - true or false
 ******/

function is_altq_capable($int) {
	/* Per:
	 * http://www.freebsd.org/cgi/man.cgi?query=altq&apropos=0&sektion=0&manpath=FreeBSD+11.0-RELEASE&arch=default&format=html
	 * Only the following drivers have ALTQ support
	 * 20150328 - removed wireless drivers - ath, awi, bwn, iwi, ipw, ral, rum, run, wi - for now. redmine #4406
	 */
	$capable = array("ae", "age", "alc", "ale", "an", "aue", "axe", "bce",
			"bfe", "bge", "bnxt", "bridge", "cas", "cpsw", "cxl", "dc", "de",
			"ed", "em", "ep", "epair", "et", "fxp", "gem", "hme", "hn",
			"igb", "igc", "ix", "jme", "l2tp", "le", "lem", "msk", "mxge", "my",
			"ndis", "nfe", "ng", "nge", "npe", "nve", "ql", "ovpnc",
			"ovpns", "ppp", "pppoe", "pptp", "re", "rl", "sf", "sge",
			"sis", "sk", "ste", "stge", "ti", "tun", "txp", "udav",
			"ural", "vge", "vlan", "vmx", "vr", "vte", "vtnet", "xl");

	$int_family = remove_ifindex($int);

	if (in_array($int_family, $capable)) {
		return true;
	} elseif (stristr($int, "l2tp")) { /* VLANs are named $parent_$vlan now */
		return true;
	} elseif (interface_is_vlan($int) != NULL) { /* VLANs are named $parent.$vlan now */
		return true;
	} elseif (stristr($int, "_wlan")) { /* WLANs are named $parent_$wlan now */
		return true;
	} else {
		return false;
	}
}

/****f* interfaces/is_interface_wireless
 * NAME
 *   is_interface_wireless - Returns if an interface is wireless
 * RESULT
 *   $tmp       - Returns if an interface is wireless
 ******/
function is_interface_wireless($interface) {
	global $config, $g;

	$friendly = convert_real_interface_to_friendly_interface_name($interface);
	if (!isset($config['interfaces'][$friendly]['wireless'])) {
		if (preg_match($g['wireless_regex'], $interface)) {
			if (isset($config['interfaces'][$friendly])) {
				$config['interfaces'][$friendly]['wireless'] = array();
			}
			return true;
		}
		return false;
	} else {
		return true;
	}
}

function get_wireless_modes($interface) {
	/* return wireless modes and channels */
	$wireless_modes = array();

	$cloned_interface = get_real_interface($interface);

	if ($cloned_interface && is_interface_wireless($cloned_interface)) {
		$chan_list = "/sbin/ifconfig {$cloned_interface} list chan";
		$stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
		$format_list = "/usr/bin/awk '{print \$5 \" \" \$6 \",\" \$1}'";

		$interface_channels = "";
		exec("$chan_list | $stack_list | sort -u | $format_list 2>&1", $interface_channels);
		$interface_channel_count = count($interface_channels);

		$c = 0;
		while ($c < $interface_channel_count) {
			$channel_line = explode(",", $interface_channels["$c"]);
			$wireless_mode = trim($channel_line[0]);
			$wireless_channel = trim($channel_line[1]);
			if (trim($wireless_mode) != "") {
				/* if we only have 11g also set 11b channels */
				if ($wireless_mode == "11g") {
					if (!isset($wireless_modes["11b"])) {
						$wireless_modes["11b"] = array();
					}
				} elseif ($wireless_mode == "11g ht") {
					if (!isset($wireless_modes["11b"])) {
						$wireless_modes["11b"] = array();
					}
					if (!isset($wireless_modes["11g"])) {
						$wireless_modes["11g"] = array();
					}
					$wireless_mode = "11ng";
				} elseif ($wireless_mode == "11a ht") {
					if (!isset($wireless_modes["11a"])) {
						$wireless_modes["11a"] = array();
					}
					$wireless_mode = "11na";
				}
				$wireless_modes["$wireless_mode"]["$c"] = $wireless_channel;
			}
			$c++;
		}
	}
	return($wireless_modes);
}

function get_wireless_channels($interface) {
		$chan_list = "/sbin/ifconfig -v " . escapeshellarg($interface) . " list chan";
		$stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
		$format_list = "/usr/bin/awk '{print \$5 \",\" \$6 \",\" \$1}'";

		$interface_channels = "";
		exec("$chan_list | $stack_list | /usr/bin/sort -u | $format_list 2>&1", $interface_channels);
		return $interface_channels;
}

/* return wireless HT modes */
function get_wireless_ht_modes($interface) {
	$wireless_hts_supported = array(0 => gettext('Auto'));

	$cloned_interface = get_real_interface($interface);

	if ($cloned_interface && is_interface_wireless($cloned_interface)) {
		$interface_channels = get_wireless_channels($cloned_interface);

		foreach ($interface_channels as $channel) {
			$channel_line = explode(",", $channel);
			$wireless_ht = trim($channel_line[1]);
			if (!empty($wireless_ht)) {
				$wireless_hts_supported[$wireless_ht] = strtoupper($wireless_ht);
			}
		}
	}
	return($wireless_hts_supported);
}

/* return wireless HT by channel/standard */
function get_wireless_ht_list($interface) {
	$wireless_hts = array();

	$cloned_interface = get_real_interface($interface);

	if ($cloned_interface && is_interface_wireless($cloned_interface)) {
		$interface_channels = get_wireless_channels($cloned_interface);
		$interface_channel_count = count($interface_channels);

		$c = 0;
		while ($c < $interface_channel_count) {
			$channel_line = explode(",", $interface_channels["$c"]);
			$wireless_mode = trim($channel_line[0]);
			$wireless_ht = trim($channel_line[1]);
			$wireless_channel = trim($channel_line[2]);
			if (!empty($wireless_mode) && !empty($wireless_ht)) {
				if ($wireless_mode == "11g") {
					if (!isset($wireless_modes["11g"])) {
						$wireless_hts["11g"] = array();
					}
					$wireless_mode = "11ng";
				} elseif ($wireless_mode == "11a") {
					if (!isset($wireless_modes["11a"])) {
						$wireless_hts["11a"] = array();
					}
					$wireless_mode = "11na";
				}
				$wireless_hts["$wireless_mode"]["$wireless_channel"][] = $wireless_ht;
			}
			$c++;
		}
	}
	return($wireless_hts);
}

/* return channel numbers, frequency, max txpower, and max regulation txpower */
function get_wireless_channel_info($interface) {
	$wireless_channels = array();

	$cloned_interface = get_real_interface($interface);

	if ($cloned_interface && is_interface_wireless($cloned_interface)) {
		$chan_list = "/sbin/ifconfig " . escapeshellarg($cloned_interface) . " list txpower";
		$stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
		$format_list = "/usr/bin/awk '{print \$1 \",\" \$3 \" \" \$4 \",\" \$5 \",\" \$7}'";

		$interface_channels = "";
		exec("$chan_list | $stack_list | /usr/bin/sort -u | $format_list 2>&1", $interface_channels);

		foreach ($interface_channels as $channel_line) {
			$channel_line = explode(",", $channel_line);
			if (!isset($wireless_channels[$channel_line[0]])) {
				$wireless_channels[$channel_line[0]] = $channel_line;
			}
		}
	}
	return($wireless_channels);
}

function set_interface_mtu($interface, $mtu) {

	/* LAGG interface must be destroyed and re-created to change MTU */
	if ((substr($interface, 0, 4) == 'lagg') &&
	    (!strstr($interface, "."))) {
		if (isset($config['laggs']['lagg']) &&
		    is_array($config['laggs']['lagg'])) {
			foreach ($config['laggs']['lagg'] as $lagg) {
				if ($lagg['laggif'] == $interface) {
					interface_lagg_configure($lagg);
					break;
				}
			}
		}
	} else {
		pfSense_interface_mtu($interface, $mtu);
		set_ipv6routes_mtu($interface, $mtu);
	}
}

/****f* interfaces/get_interface_mtu
 * NAME
 *   get_interface_mtu - Return the mtu of an interface
 * RESULT
 *   $tmp       - Returns the mtu of an interface
 ******/
function get_interface_mtu($interface) {
	$mtu = pfSense_interface_getmtu($interface);
	return $mtu['mtu'];
}

function get_interface_mac($interface) {
	$macinfo = pfSense_get_interface_addresses($interface);
	return $macinfo["macaddr"];
}

function get_interface_vendor_mac($interface) {
	global $config, $g;

	$macinfo = pfSense_get_interface_addresses($interface);
	if (isset($macinfo["hwaddr"]) && $macinfo["hwaddr"] !=
	    "00:00:00:00:00:00") {
		return ($macinfo["hwaddr"]);
	}

	$hwaddr_file = "{$g['tmp_path']}/{$interface}_hwaddr";
	if (file_exists($hwaddr_file)) {
		$macaddr = trim(file_get_contents($hwaddr_file));
		if (is_macaddr($macaddr)) {
			return ($macaddr);
		}
	} elseif (is_macaddr($macinfo['macaddr'])) {
		/* Save original macaddress to be restored when necessary */
		@file_put_contents($hwaddr_file, $macinfo['macaddr']);
	}

	return (NULL);
}

/****f* pfsense-utils/generate_random_mac_address
 * NAME
 *   generate_random_mac - generates a random mac address
 * INPUTS
 *   none
 * RESULT
 *   $mac - a random mac address
 ******/
function generate_random_mac_address() {
	$mac = "02";
	for ($x = 0; $x < 5; $x++) {
		$mac .= ":" . dechex(rand(16, 255));
	}
	return $mac;
}

function interface_setup_pppoe_reset_file($pppif, $iface="") {
	global $g;

	$cron_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";

	if (!empty($iface) && !empty($pppif)) {
		$cron_cmd = <<<EOD
#!/bin/sh
/usr/local/sbin/pfSctl -c 'interface reload {$iface}'
/usr/bin/logger -t {$pppif} "PPPoE periodic reset executed on {$iface}"

EOD;

		@file_put_contents($cron_file, $cron_cmd);
		chmod($cron_file, 0755);
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
	} else {
		unlink_if_exists($cron_file);
	}
}

function get_interface_default_mtu($type = "ethernet") {
	switch ($type) {
		case "gre":
			return 1476;
			break;
		case "gif":
			return 1280;
			break;
		case "tun":
		case "vlan":
		case "tap":
		case "ethernet":
		default:
			return 1500;
			break;
	}

	/* Never reached */
	return 1500;
}

function get_vip_descr($ipaddress) {
	global $config;

	foreach ($config['virtualip']['vip'] as $vip) {
		if ($vip['subnet'] == $ipaddress) {
			return ($vip['descr']);
		}
	}
	return "";
}

function interfaces_staticarp_configure($if) {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_staticarp_configure($if) being called $mt\n";
	}

	$ifcfg = $config['interfaces'][$if];

	if (empty($if) || empty($ifcfg['if']) || !isset($ifcfg['enable'])) {
		return 0;
	}

	/* Enable staticarp, if enabled */
	if (isset($config['dhcpd'][$if]['staticarp'])) {
		mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " staticarp ");
		mwexec("/usr/sbin/arp -d -i " . escapeshellarg($ifcfg['if']) . " -a > /dev/null 2>&1 ");
	} else {
		/*
		 * Interfaces do not have staticarp enabled by default
		 * Let's not disable staticarp on freshly created interfaces
		 */
		if (!platform_booting()) {
			mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " -staticarp ");
			mwexec("/usr/sbin/arp -d -i " . escapeshellarg($ifcfg['if']) . " -a > /dev/null 2>&1 ");
		}
	}

	/* Enable static arp entries */
	if (is_array($config['dhcpd'][$if]) && is_array($config['dhcpd'][$if]['staticmap'])) {
		foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
			if (empty($arpent['ipaddr']) || empty($arpent['mac'])) {
				continue;
			}
			if (isset($config['dhcpd'][$if]['staticarp']) || isset($arpent['arp_table_static_entry'])) {
				mwexec("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));
			}
		}
	}

	return 0;
}

function get_failover_interface($interface, $family = "all") {
	global $config;

	/* shortcut to get_real_interface if we find it in the config */
	if (is_array($config['interfaces'][$interface])) {
		return get_real_interface($interface, $family);
	}

	/* compare against gateway groups */
	$a_groups = return_gateway_groups_array(true);
	if (is_array($a_groups[$interface])) {
		/* we found a gateway group, fetch the interface or vip */
		if (!empty($a_groups[$interface][0]['vip'])) {
			return $a_groups[$interface][0]['vip'];
		} else {
			return $a_groups[$interface][0]['int'];
		}
	}
	/* fall through to get_real_interface */
	/* XXX: Really needed? */
	return get_real_interface($interface, $family);
}

/****f* interfaces/interface_has_dhcp
 * NAME
 *   interface_has_dhcp - determine if the interface or gateway group uses DHCP
 * INPUTS
 *   interface or gateway group name
 *   family - 4 (check for IPv4 DHCP) or 6 (check for IPv6 DHCP)
 * RESULT
 *   true - if the interface uses DHCP/DHCP6, or the name is a gateway group which has any member that uses DHCP/DHCP6
 *   false - otherwise (DHCP/DHCP6 not in use, or the name is not an interface or gateway group)
 ******/
function interface_has_dhcp($interface, $family = 4) {
	global $config;

	if ($config['interfaces'][$interface]) {
		if (($family == 4) && ($config['interfaces'][$interface]['ipaddr'] == "dhcp")) {
			return true;
		} elseif (($family == 6) && ($config['interfaces'][$interface]['ipaddrv6'] == "dhcp6")) {
			return true;
		} else {
			return false;
		}
	}

	if (!is_array($config['gateways']['gateway_group'])) {
		return false;
	}

	if ($family == 6) {
		$dhcp_string = "_DHCP6";
	} else {
		$dhcp_string = "_DHCP";
	}

	foreach ($config['gateways']['gateway_group'] as $group) {
		if (($group['name'] != $interface) || !is_array($group['item'])) {
			continue;
		}
		foreach ($group['item'] as $item) {
			$item_data = explode("|", $item);
			if (substr($item_data[0], -strlen($dhcp_string)) == $dhcp_string) {
				return true;
			}
		}
	}

	return false;
}

function remove_ifindex($ifname) {
	return preg_replace("/[0-9]+$/", "", $ifname);
}

function get_configured_vip_list_with_descr($family = 'all', $type = VIP_ALL) {
	$interfaces = array();	// In case there are no VIPs defined

	$viplist = get_configured_vip_list($family, $type);
	foreach ($viplist as $vip => $address) {
		$interfaces[$vip] = $address;
		if ($type = VIP_CARP) {
			$vip = get_configured_vip($vipid);
			if (isset($vip) && is_array($vip) ) {
				$interfaces[$vipid] .= " - vhid {$vip['vhid']}";
			}
		}
		if (get_vip_descr($address)) {
			$interfaces[$vip] .= " (" . get_vip_descr($address) . ")";
		}
	}
	return $interfaces;
}

function return_gateway_groups_array_with_descr() {
	$interfaces = array();
	$grouplist = return_gateway_groups_array();
	foreach ($grouplist as $name => $group) {
		if ($group[0]['vip'] != "") {
			$vipif = $group[0]['vip'];
		} else {
			$vipif = $group[0]['int'];
		}

		$interfaces[$name] = "GW Group {$name}";
	}
	return $interfaces;
}

function get_serial_ports() {
	$linklist = array();
	if (!is_dir("/var/spool/lock")) {
		mwexec("/bin/mkdir -p /var/spool/lock");
	}
	$serialports = glob("/dev/cua[a-zA-Z][0-9]{,.[0-9],.[0-9][0-9],[0-9],[0-9].[0-9],[0-9].[0-9][0-9]}", GLOB_BRACE);
	foreach ($serialports as $port) {
		$linklist[$port] = trim($port);
	}
	return $linklist;
}

function get_interface_ports() {
	global $config;
	$linklist = array();
	$portlist = get_interface_list();
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			$portlist[$vlan['vlanif']] = $vlan;
		}
	}

	if (is_array($config['qinqs']['qinqentry']) && count($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			$members = explode(" ", $qinq['members']);
			foreach ($members as $mem) {
				$qentry = $qinq['vlanif'] . "." . $mem;
				$portlist[$qentry] = $qentry;
			}
		}
	}

	foreach ($portlist as $ifn => $ifinfo) {
		$string = "";
		if (is_array($ifinfo)) {
			$string .= $ifn;
			if ($ifinfo['mac']) {
				$string .= " ({$ifinfo['mac']})";
			}
			if ($ifinfo['friendly']) {
				$string .= " - " . convert_friendly_interface_to_friendly_descr($ifinfo['friendly']);
			} elseif ($ifinfo['descr']) {
				$string .= " - {$ifinfo['descr']}";
			}
		} else {
			$string .= $ifinfo;
		}

		$linklist[$ifn] = $string;
	}
	return $linklist;
}

function build_ppps_link_list() {
	global $pconfig;

	$linklist = array('list' => array(), 'selected' => array());

	if ($pconfig['type'] == 'ppp') {
		$linklist['list'] = get_serial_ports();
	} else {
		$iflist = get_interface_ports();

		$viplist = array();
		$carplist = get_configured_vip_list_with_descr('all', VIP_CARP);
		foreach ($carplist as $vid => $vaddr) {
			$vip = get_configured_vip($vid);
			$viplist[$vid] = "{$vaddr} (vhid: {$vip['vhid']})";
		}

		$linklist['list'] = array_merge($iflist, $viplist);

		// The members of a LAGG cannot be assigned, used in VLANs, QinQ, or PPP.
		$lagglist = get_lagg_interface_list();
		foreach ($lagglist as $laggif => $lagg) {
			/* LAGG members cannot be assigned */
			$laggmembers = explode(',', $lagg['members']);
			foreach ($laggmembers as $lagm) {
				if (isset($linklist['list'][$lagm])) {
					unset($linklist['list'][$lagm]);
				}
			}
		}
	}

	$selected_ports = array();
	if (is_array($pconfig['interfaces'])) {
		$selected_ports = $pconfig['interfaces'];
	} elseif (!empty($pconfig['interfaces'])) {
		$selected_ports = explode(',', $pconfig['interfaces']);
	}
	foreach ($selected_ports as $port) {
		if (isset($linklist['list'][$port])) {
			array_push($linklist['selected'], $port);
		}
	}
	return($linklist);
}

function create_interface_list() {
	global $config;

	$iflist = array();

	// add group interfaces
	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			if (have_ruleint_access($ifgen['ifname'])) {
				$iflist[$ifgen['ifname']] = $ifgen['ifname'];
			}
		}
	}

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		if (have_ruleint_access($ifent)) {
			$iflist[$ifent] = $ifdesc;
		}
	}

	if ($config['l2tp']['mode'] == "server" && have_ruleint_access("l2tp")) {
		$iflist['l2tp'] = gettext('L2TP VPN');
	}

	if (is_pppoe_server_enabled() && have_ruleint_access("pppoe")) {
		$iflist['pppoe'] = gettext("PPPoE Server");
	}

	// add ipsec interfaces
	if (ipsec_enabled() && have_ruleint_access("enc0")) {
		$iflist["enc0"] = gettext("IPsec");
	}

	// add openvpn/tun interfaces
	if ($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"]) {
		$iflist["openvpn"] = gettext("OpenVPN");
	}

	if (is_wg_enabled() && have_ruleint_access("wireguard")) {
		$iflist['wireguard'] = 'WireGuard';
	}

	return($iflist);
}

function is_pseudo_interface($inf, $tap=true) {
	global $config;
	$psifs = array('ovpn', 'ipsec', 'l2tp', 'pptp', 'gif', 'gre', 'ppp', 'pppoe', 'wg');
	foreach ($psifs as $pif) {
		if (substr($inf, 0, strlen($pif)) == $pif) {
			if (($pif == 'ovpn') && $tap) {
				preg_match('/ovpn([cs])([1-9]+)/', $inf, $m);
				$type = ($m[1] == 'c') ? 'client' : 'server';
				foreach ($config['openvpn']['openvpn-'.$type] as $ovpn) {
					if (($ovpn['vpnid'] == $m[2]) && ($ovpn['dev_mode'] == 'tap')) {
						return false;
					} elseif ($ovpn['vpnid'] == $m[2]) {
						return true;
					}
				}
			} else {
				return true;
			}
		}
	}
	return false;
}

function is_stf_interface($inf) {
	global $config;

	if (is_array($config['interfaces'][$inf]) &&
	    (($config['interfaces'][$inf]['ipaddrv6'] == '6rd') ||
	    ($config['interfaces'][$inf]['ipaddrv6'] == '6to4'))) {
	    return true;
	}

	return false;
}

?>
