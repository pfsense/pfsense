<?php
/*
	interfaces.inc
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2008-2009 Ermal Lu?i
	All rights reserved.

	function interfaces_wireless_configure is
	Copyright (C) 2005 Espen Johansen
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notices,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notices, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

	pfSense_BUILDER_BINARIES:	/sbin/dhclient	/bin/sh	/usr/bin/grep	/usr/bin/xargs	/usr/bin/awk	/usr/local/sbin/choparp
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/sbin/route	/usr/sbin/ngctl	/usr/sbin/arp	/bin/kill	/usr/local/sbin/mpd5
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/dhcp6c
	pfSense_MODULE:	interfaces

*/

/* include all configuration functions */
require_once("globals.inc");
require_once("util.inc");
require_once("gwlb.inc");

function interfaces_bring_up($interface) {
	if(!$interface) {
		log_error(gettext("interfaces_bring_up() was called but no variable defined."));
		log_error( "Backtrace: " . debug_backtrace() );
		return;
	}
	pfSense_interface_flags($interface, IFF_UP);
}

/*
 * Return the interface array
 */
function get_interface_arr($flush = false) {
	global $interface_arr_cache;

	/* If the cache doesn't exist, build it */
	if (!isset($interface_arr_cache) or $flush)
		$interface_arr_cache = pfSense_interface_listget();

	return $interface_arr_cache;
}

/*
 * does_interface_exist($interface): return true or false if a interface is
 * detected.
 */
function does_interface_exist($interface, $flush = true) {
	global $config;

	if(!$interface)
		return false;

	$ints = get_interface_arr($flush);
	if (in_array($interface, $ints))
		return true;
	else
		return false;
}

/*
 * does_vip_exist($vip): return true or false if a vip is
 * configured.
 */
function does_vip_exist($vip) {
	global $config;

	if(!$vip)
		return false;


	switch ($vip['mode']) {
	case "carp":
		$realif = "{$vip['interface']}_vip{$vip['vhid']}";
		if (!does_interface_exist($realif)) {
			return false;
		}
		break;
	case "ipalias":
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
	foreach ($ifacedata as $vipips) {
		if ($vipips == "{$vip['subnet']}/{$vip['subnet_bits']}")
			return true;
	}

	return false;
}

function interface_netgraph_needed($interface = "wan") {
	global $config;

	$found = false;
	if (!empty($config['pptpd']) &&
		$config['pptpd']['mode'] == "server")
		$found = true;
	if ($found == false && !empty($config['l2tp']) &&
		$config['l2tp']['mode'] == "server")
		$found = true;
	if ($found == false && is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] != "server")
				continue;
			if ($pppoe['interface'] == $interface)
				$found = true;
				break;
		}
	}
	if ($found == false) {
		if (!empty($config['interfaces'][$interface])) {
			switch ($config['interfaces'][$interface]['ipaddr']) {
			case "ppp":
			case "pppoe":
			case "l2tp":
			case "pptp":
				$found = true;
				break;
			default:
				$found = false;
				break;
			}
		}
	}
	if ($found == false) {
		$realif = get_real_interface($interface);
		if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
			foreach ($config['ppps']['ppp'] as $pppid => $ppp) {

/* This if block doesn't do anything. It can be deleted.
PPP interfaces are found above in the previous if ($found == false) block.
This block of code is only entered for OPTx interfaces that are configured for PPPoE modem access, so $realif != $ppp['if']

				if ($realif == $ppp['if']) {
					$found = true;
					break;
				}
*/
				$ports = explode(',',$ppp['ports']);
				foreach($ports as $pid => $port){
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
	}

	if ($found == false) {
		$realif = get_real_interface($interface);
		pfSense_ngctl_detach("{$realif}:", $realif);
	}
	/* NOTE: We make sure for this on interface_ppps_configure()
	 *	no need to do it here agan.
	 *	else
	 *		pfSense_ngctl_attach(".", $realif);
	 */
}

function interfaces_loopback_configure() {
	global $g;

	if ($g['platform'] == 'jail')
		return;
	if($g['booting'])
		echo gettext("Configuring loopback interface...");
	pfSense_interface_setaddress("lo0", "127.0.0.1");
	interfaces_bring_up("lo0");
	if($g['booting'])
		echo gettext("done.") . "\n";
	return 0;
}

function interfaces_vlan_configure() {
	global $config, $g;
	if($g['booting'])
		echo gettext("Configuring VLAN interfaces...");
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if(empty($vlan['vlanif']))
				$vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
			/* XXX: Maybe we should report any errors?! */
			interface_vlan_configure($vlan);
		}
	}
	if($g['booting'])
		echo gettext("done.") . "\n";
}

function interface_vlan_configure(&$vlan) {
	global $config, $g;

	if (!is_array($vlan)) {
		log_error(gettext("VLAN: called with wrong options. Problems with config!"));
		return;
	}
	$if = $vlan['if'];
	$vlanif  = empty($vlan['vlanif']) ? "{$if}_vlan{$vlan['tag']}" : $vlan['vlanif'];
	$tag = $vlan['tag'];

	if (empty($if)) {
		log_error(gettext("interface_vlan_configure called with if undefined."));
		return;
	}

	/* make sure the parent interface is up */
	interfaces_bring_up($if);
	/* Since we are going to add vlan(4) try to enable all that hardware supports. */
	pfSense_interface_capabilities($if, IFCAP_VLAN_HWTAGGING|IFCAP_VLAN_MTU|IFCAP_VLAN_HWFILTER);

	if (!empty($vlanif) && does_interface_exist($vlanif)) {
		interface_bring_down($vlanif, true);
	} else {
		$tmpvlanif = pfSense_interface_create("vlan");
		pfSense_interface_rename($tmpvlanif, $vlanif);
		pfSense_ngctl_name("{$tmpvlanif}:", $vlanif);
	}

	pfSense_vlan_create($vlanif, $if, $tag);

	interfaces_bring_up($vlanif);

	/* invalidate interface cache */
	get_interface_arr(true);

	/* XXX: ermal -- for now leave it here at the moment it does not hurt. */
	interfaces_bring_up($if);

	return $vlanif;
}

function interface_qinq_configure(&$vlan, $fd = NULL) {
	global $config, $g;

	if (!is_array($vlan)) {
		log_error(sprintf(gettext("QinQ compat VLAN: called with wrong options. Problems with config!%s"), "\n"));
		return;
	}

	$qinqif = $vlan['if'];
	$tag = $vlan['tag'];
	if(empty($qinqif)) {
		log_error(sprintf(gettext("interface_qinq_configure called with if undefined.%s"), "\n"));
		return;
	}

	if(!does_interface_exist($qinqif)) {
		log_error(sprintf(gettext("interface_qinq_configure called with invalid if.%s"), "\n"));
		return;
	}

	$vlanif = interface_vlan_configure($vlan);

	if ($fd == NULL) {
		$exec = true;
		$fd = fopen("{$g['tmp_path']}/netgraphcmd", "w");
	} else
		$exec = false;
	/* make sure the parent is converted to ng_vlan(4) and is up */
	interfaces_bring_up($qinqif);

	pfSense_ngctl_attach(".", $qinqif);
	if (!empty($vlanif) && does_interface_exist($vlanif)) {
		fwrite($fd, "shutdown {$qinqif}qinq:\n");
		exec("/usr/sbin/ngctl msg {$qinqif}qinq: gettable", $result);
		if (empty($result)) {
			fwrite($fd, "mkpeer {$qinqif}: vlan lower downstream\n");
			fwrite($fd, "name {$qinqif}:lower {$vlanif}qinq\n");
			fwrite($fd, "connect {$qinqif}: {$vlanif}qinq: upper nomatch\n");
		}
	} else {
		fwrite($fd, "mkpeer {$qinqif}: vlan lower downstream\n");
		fwrite($fd, "name {$qinqif}:lower {$vlanif}qinq\n");
		fwrite($fd, "connect {$qinqif}: {$vlanif}qinq: upper nomatch\n");
	}

	/* invalidate interface cache */
	get_interface_arr(true);

	if (!stristr($qinqif, "_vlan"))
		mwexec("/sbin/ifconfig {$qinqif} promisc\n");

	$macaddr = get_interface_mac($qinqif);
	if (!empty($vlan['members'])) {
		$members = explode(" ", $vlan['members']);
		foreach ($members as $qtag) {
			$qinq = array();
			$qinq['tag'] = $qtag;
			$qinq['if'] = $vlanif;
			interface_qinq2_configure($qinq, $fd, $macaddr);
		}
	}
	if ($exec == true) {
		fclose($fd);
		mwexec("/usr/sbin/ngctl -f {$g['tmp_path']}/netgraphcmd");
	}

	interfaces_bring_up($qinqif);
	if (!empty($vlan['members'])) {
		$members = explode(" ", $vlan['members']);
		foreach ($members as $qif)
			interfaces_bring_up("{$vlanif}_{$qif}");
	}

	return $vlanif;
}

function interfaces_qinq_configure() {
	global $config, $g;
	if($g['booting'])
		echo gettext("Configuring QinQ interfaces...");
	if (is_array($config['qinqs']['qinqentry']) && count($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			/* XXX: Maybe we should report any errors?! */
			interface_qinq_configure($qinq);
		}
	}
	if($g['booting'])
		echo gettext( "done.") . "\n";
}

function interface_qinq2_configure(&$qinq, $fd, $macaddr) {
	global $config, $g;

	if (!is_array($qinq)) {
		log_error(sprintf(gettext("QinQ compat VLAN: called with wrong options. Problems with config!%s"), "\n"));
		return;
	}

	$if = $qinq['if'];
	$tag = $qinq['tag'];
	$vlanif = "{$if}_{$tag}";
	if(empty($if)) {
		log_error(sprintf(gettext("interface_qinq2_configure called with if undefined.%s"), "\n"));
		return;
	}

	fwrite($fd, "shutdown {$if}h{$tag}:\n");
	fwrite($fd, "mkpeer {$if}qinq: eiface {$if}{$tag} ether\n");
	fwrite($fd, "name {$if}qinq:{$if}{$tag} {$if}h{$tag}\n");
	fwrite($fd, "msg {$if}qinq: addfilter { vlan={$tag} hook=\"{$if}{$tag}\" }\n");
	fwrite($fd, "msg {$if}h{$tag}: setifname \"{$vlanif}\"\n");
	fwrite($fd, "msg {$if}h{$tag}: set {$macaddr}\n");

	/* invalidate interface cache */
	get_interface_arr(true);

	return $vlanif;
}

function interfaces_create_wireless_clones() {
	global $config, $g;

	if($g['booting'])
		echo gettext("Creating wireless clone interfaces...");

	$iflist = get_configured_interface_list();

	foreach ($iflist as $if) {
		$realif = $config['interfaces'][$if]['if'];
		if (is_interface_wireless($realif))
			interface_wireless_clone(interface_get_wireless_clone($realif), $config['interfaces'][$if]);
	}

	if (isset($config['wireless']['clone']) && is_array($config['wireless']['clone']) && count($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as $clone) {
			if(empty($clone['cloneif']))
				continue;
			if(does_interface_exist($clone['cloneif']))
				continue;
			/* XXX: Maybe we should report any errors?! */
			interface_wireless_clone($clone['cloneif'], $clone);
		}
	}
	if($g['booting'])
		echo gettext("done.") . "\n";

}

function interfaces_bridge_configure($checkmember = 0) {
	global $config;

	$i = 0;
	if (is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
		foreach ($config['bridges']['bridged'] as $bridge) {
			if(empty($bridge['bridgeif']))
				$bridge['bridgeif'] = "bridge{$i}";
			if ($checkmember == 1) {
				$members = explode(',', $bridge['members']);
				foreach ($members as $member) {
					if (strstr($bridge['if'], "_vip"))
						continue 2;
					if (!empty($config['interfaces'][$bridge['if']]) && $config['interfaces'][$bridge['if']]['ipaddrv6'] == "track6")
						continue 2;
				}
			}
			else if ($checkmember == 2) {
				$members = explode(',', $bridge['members']);
				foreach ($members as $member) {
					if (!strstr($bridge['if'], "_vip"))
						continue 2;
					if (empty($config['interfaces'][$bridge['if']]) || $config['interfaces'][$bridge['if']]['ipaddrv6'] != "track6")
						continue 2;
				}
			}
			/* XXX: Maybe we should report any errors?! */
			interface_bridge_configure($bridge, $checkmember);
			$i++;
		}
	}
}

function interface_bridge_configure(&$bridge, $checkmember = 0) {
	global $config, $g;

	if (!is_array($bridge))
		return;

	if (empty($bridge['members'])) {
		log_error(sprintf(gettext("No members found on %s"), $bridge['bridgeif']));
		return;
	}

	$members = explode(',', $bridge['members']);
	if (!count($members))
		return;

	/* Calculate smaller mtu and enforce it */
	$smallermtu = 0;
	$commonrx = true;
	$commontx = true;
	$foundgif = false;
	foreach ($members as $member) {
		$realif = get_real_interface($member);
		$opts = pfSense_get_interface_addresses($realif);
		$mtu = $opts['mtu'];
		if (substr($realif, 0, 3) == "gif") {
			$foundgif = true;
			if ($checkmember == 1)
				return;
			if ($mtu <= 1500)
				continue;
		}
		if (!isset($opts['caps']['txcsum']))
			$commontx = false;
		if (!isset($opts['caps']['rxcsum']))
			$commonrx = false;
		if (!isset($opts['caps']['tso4']))
			$commontso4 = false;
		if (!isset($opts['caps']['tso6']))
			$commontso6 = false;
		if (!isset($opts['caps']['lro']))
			$commonlro = false;
		if ($smallermtu == 0 && !empty($mtu))
			$smallermtu = $mtu;
		else if (!empty($mtu) && $mtu < $smallermtu)
			$smallermtu = $mtu;
	}
	if ($foundgif == false && $checkmember == 2)
		return;

	/* Just in case anything is not working well */
	if ($smallermtu == 0)
		$smallermtu = 1500;

	$flags_on = 0;
	$flags_off = 0;
	if (isset($config['system']['disablechecksumoffloading']) || ($commonrx === false))
		$flags_off |= IFCAP_RXCSUM;
	else
		$flags_on |= IFCAP_RXCSUM;
	if (isset($config['system']['disablechecksumoffloading']) || ($commontx === false))
		$flags_off |= IFCAP_TXCSUM;
	else
		$flags_on |= IFCAP_TXCSUM;
	if (isset($config['system']['disablesegmentationoffloading']) || ($commontso4 === false))
		$flags_off |= IFCAP_TSO4;
	else
		$flags_on |= IFCAP_TSO4;
	if (isset($config['system']['disablesegmentationoffloading']) || ($commontso6 === false))
		$flags_off |= IFCAP_TSO6;
	else
		$flags_on |= IFCAP_TSO6;
	if (isset($config['system']['disablelargereceiveoffloading']) || ($commonlro === false))
		$flags_off |= IFCAP_LRO;
	else
		$flags_on |= IFCAP_LRO;

	if ($g['booting'] || !empty($bridge['bridgeif'])) {
		pfSense_interface_destroy($bridge['bridgeif']);
		pfSense_interface_create($bridge['bridgeif']);
		$bridgeif = escapeshellarg($bridge['bridgeif']);
	} else {
		$bridgeif = pfSense_interface_create("bridge");
		$bridge['bridgeif'] = $bridgeif;
	}

	$checklist = get_configured_interface_list();

	/* Add interfaces to bridge */
	foreach ($members as $member) {
		if (empty($checklist[$member]))
			continue;
		$realif = get_real_interface($member);
		if (!$realif) {
			log_error(gettext("realif not defined in interfaces bridge - up"));
			continue;
		}
		/* make sure the parent interface is up */
		pfSense_interface_mtu($realif, $smallermtu);
		pfSense_interface_capabilities($realif, -$flags_off);
		pfSense_interface_capabilities($realif, $flags_on);
		interfaces_bring_up($realif);
		pfSense_bridge_add_member($bridge['bridgeif'], $realif);
	}

	if (isset($bridge['enablestp'])) {
		/* Choose spanning tree proto */
		mwexec("/sbin/ifconfig {$bridgeif} proto " . escapeshellarg($bridge['proto']));

		if (!empty($bridge['stp'])) {
			$stpifs = explode(',', $bridge['stp']);
			foreach ($stpifs as $stpif) {
				$realif = get_real_interface($stpif);
				mwexec("/sbin/ifconfig {$bridgeif} stp {$realif}");
			}
		}
		if (!empty($bridge['maxage']))
			mwexec("/sbin/ifconfig {$bridgeif} maxage " . escapeshellarg($bridge['maxage']));
		if (!empty($bridge['fwdelay']))
			mwexec("/sbin/ifconfig {$bridgeif} fwddelay " . escapeshellarg($bridge['fwdelay']));
		if (!empty($bridge['hellotime']))
			mwexec("/sbin/ifconfig {$bridgeif} hellotime " . escapeshellarg($bridge['hellotime']));
		if (!empty($bridge['priority']))
			mwexec("/sbin/ifconfig {$bridgeif} priority " . escapeshellarg($bridge['priority']));
		if (!empty($bridge['holdcnt']))
			mwexec("/sbin/ifconfig {$bridgeif} holdcnt " . escapeshellarg($bridge['holdcnt']));
		if (!empty($bridge['ifpriority'])) {
			$pconfig = explode(",", $bridge['ifpriority']);
			$ifpriority = array();
			foreach ($pconfig as $cfg) {
				$embcfg = explode_assoc(":", $cfg);
				foreach ($embcfg as $key => $value)
					$ifpriority[$key] = $value;
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
				foreach ($embcfg as $key => $value)
					$ifpathcost[$key] = $value;
			}
			foreach ($ifpathcost as $key => $value) {
				$realif = get_real_interface($key);
				mwexec("/sbin/ifconfig ${bridgeif} ifpathcost {$realif} " . escapeshellarg($value));
			}
		}
	}

	if ($bridge['maxaddr'] <> "")
		mwexec("/sbin/ifconfig {$bridgeif} maxaddr " . escapeshellarg($bridge['maxaddr']));
	if ($bridge['timeout'] <> "")
		mwexec("/sbin/ifconfig {$bridgeif} timeout " . escapeshellarg($bridge['timeout']));
	if ($bridge['span'] <> "") {
		$realif = get_real_interface($bridge['span']);
		mwexec("/sbin/ifconfig {$bridgeif} span {$realif}");
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

	if ($bridge['bridgeif'])
		interfaces_bring_up($bridge['bridgeif']);
	else
		log_error(gettext("bridgeif not defined -- could not bring interface up"));
}

function interface_bridge_add_member($bridgeif, $interface) {

	if (!does_interface_exist($bridgeif) || !does_interface_exist($interface))
		return;

	$mtu = get_interface_mtu($bridgeif);
	$mtum = get_interface_mtu($interface);

	if ($mtu != $mtum && !(substr($interface, 0, 3) == "gif" && $mtu <= 1500))
		pfSense_interface_mtu($interface, $mtu);

	$options = pfSense_get_interface_addresses($bridgeif);
	$flags_on = 0;
	$flags_off = 0;
	if (isset($options['encaps']['txcsum']))
		$flags_on |= IFCAP_TXCSUM;
	else
		$flags_off |= IFCAP_TXCSUM;
	if (isset($options['encaps']['rxcsum']))
		$flags_on |= IFCAP_RXCSUM;
	else
		$flags_off |= IFCAP_RXCSUM;
	if (isset($options['encaps']['tso4']))
		$flags_on |= IFCAP_TSO4;
	else
		$flags_off |= IFCAP_TSO4;
	if (isset($options['encaps']['tso6']))
		$flags_on |= IFCAP_TSO6;
	else
		$flags_off |= IFCAP_TSO6;
	if (isset($options['encaps']['lro']))
		$flags_on |= IFCAP_LRO;
	else
		$flags_off |= IFCAP_LRO;

	pfSense_interface_capabilities($interface, -$flags_off);
	pfSense_interface_capabilities($interface, $flags_on);

	interfaces_bring_up($interface);
	pfSense_bridge_add_member($bridgeif, $interface);
}

function interfaces_lagg_configure() {
	global $config, $g;
	if($g['booting'])
		echo gettext("Configuring LAGG interfaces...");
	$i = 0;
	if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as $lagg) {
			if(empty($lagg['laggif']))
				$lagg['laggif'] = "lagg{$i}";
			/* XXX: Maybe we should report any errors?! */
			interface_lagg_configure($lagg);
			$i++;
		}
	}
	if($g['booting'])
		echo gettext("done.") . "\n";
}

function interface_lagg_configure(&$lagg) {
	global $config, $g;

	if (!is_array($lagg))
		return -1;

	$members = explode(',', $lagg['members']);
	if (!count($members))
		return -1;

	if ($g['booting'] || !(empty($lagg['laggif']))) {
		pfSense_interface_destroy($lagg['laggif']);
		pfSense_interface_create($lagg['laggif']);
		$laggif = $lagg['laggif'];
	} else
		$laggif = pfSense_interface_create("lagg");

	/* Calculate smaller mtu and enforce it */
	$smallermtu = 0;
	foreach ($members as $member) {
		$opts = pfSense_get_interface_addresses($member);
		$mtu = $opts['mtu'];
		if (!isset($opts['caps']['txcsum']))
			$commontx = false;
		if (!isset($opts['caps']['rxcsum']))
			$commonrx = false;
		if (!isset($opts['caps']['tso4']))
			$commontso4 = false;
		if (!isset($opts['caps']['tso6']))
			$commontso6 = false;
		if (!isset($opts['caps']['lro']))
			$commonlro = false;
		if ($smallermtu == 0 && !empty($mtu))
			$smallermtu = $mtu;
		else if (!empty($mtu) && $mtu < $smallermtu)
			$smallermtu = $mtu;
	}

	/* Just in case anything is not working well */
	if ($smallermtu == 0)
		$smallermtu = 1500;

	$flags_on = 0;
	$flags_off = 0;
	if (isset($config['system']['disablechecksumoffloading']) || ($commonrx === false))
		$flags_off |= IFCAP_RXCSUM;
	else
		$flags_on |= IFCAP_RXCSUM;
	if (isset($config['system']['disablechecksumoffloading']) || ($commontx === false))
		$flags_off |= IFCAP_TXCSUM;
	else
		$flags_on |= IFCAP_TXCSUM;
	if (isset($config['system']['disablesegmentationoffloading']) || ($commontso4 === false))
		$flags_off |= IFCAP_TSO4;
	else
		$flags_on |= IFCAP_TSO4;
	if (isset($config['system']['disablesegmentationoffloading']) || ($commontso6 === false))
		$flags_off |= IFCAP_TSO6;
	else
		$flags_on |= IFCAP_TSO6;
	if (isset($config['system']['disablelargereceiveoffloading']) || ($commonlro === false))
		$flags_off |= IFCAP_LRO;
	else
		$flags_on |= IFCAP_LRO;

	$checklist = get_interface_list();

	foreach ($members as $member) {
		if (!array_key_exists($member, $checklist))
			continue;
		/* make sure the parent interface is up */
		pfSense_interface_mtu($member, $smallermtu);
		pfSense_interface_capabilities($member, -$flags_off);
		pfSense_interface_capabilities($member, $flags_on);
		interfaces_bring_up($member);
		mwexec("/sbin/ifconfig {$laggif} laggport {$member}");
	}

	mwexec("/sbin/ifconfig {$laggif} laggproto " . escapeshellarg($lagg['proto']));

	interfaces_bring_up($laggif);

	return $laggif;
}

function interfaces_gre_configure($checkparent = 0) {
	global $config;

	if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
		foreach ($config['gres']['gre'] as $i => $gre) {
			if(empty($gre['greif']))
				$gre['greif'] = "gre{$i}";
			if ($checkparent == 1) {
				if (strstr($gre['if'], "_vip"))
					continue;
				if (!empty($config['interfaces'][$gre['if']]) && $config['interfaces'][$gre['if']]['ipaddrv6'] == "track6")
					continue;
			}
			else if ($checkparent == 2) {
				if (!strstr($gre['if'], "_vip"))
					continue;
				if (empty($config['interfaces'][$gre['if']]) || $config['interfaces'][$gre['if']]['ipaddrv6'] != "track6")
					continue;
			}
			/* XXX: Maybe we should report any errors?! */
			interface_gre_configure($gre);
		}
	}
}

/* NOTE: $grekey is not used but useful for passing this function to array_walk. */
function interface_gre_configure(&$gre, $grekey = "") {
	global $config, $g;

	if (!is_array($gre))
		return -1;

	$realif = get_real_interface($gre['if']);
	$realifip = get_interface_ip($gre['if']);

	/* make sure the parent interface is up */
	interfaces_bring_up($realif);

	if ($g['booting'] || !(empty($gre['greif']))) {
		pfSense_interface_destroy($gre['greif']);
		pfSense_interface_create($gre['greif']);
		$greif = $gre['greif'];
	} else
		$greif = pfSense_interface_create("gre");

	/* Do not change the order here for more see gre(4) NOTES section. */
	mwexec("/sbin/ifconfig {$greif} tunnel {$realifip} " . escapeshellarg($gre['remote-addr']));
	if((is_ipaddrv6($gre['tunnel-local-addr'])) || (is_ipaddrv6($gre['tunnel-remote-addr']))) {
		/* XXX: The prefixlen argument for tunnels of ipv6 is useless since it needs to be 128 as enforced by kernel */
		//mwexec("/sbin/ifconfig {$greif} inet6 " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " prefixlen /" . escapeshellarg($gre['tunnel-remote-net']));
		mwexec("/sbin/ifconfig {$greif} inet6 " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " prefixlen 128");
	} else {
		mwexec("/sbin/ifconfig {$greif} " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gre['tunnel-remote-net']));
	}
	if (isset($gre['link0']))
		pfSense_interface_flags($greif, IFF_LINK0);
	if (isset($gre['link1']))
		pfSense_interface_flags($greif, IFF_LINK1);
	if (isset($gre['link2']))
		pfSense_interface_flags($greif, IFF_LINK2);

	if($greif)
		interfaces_bring_up($greif);
	else
		log_error(gettext("Could not bring greif up -- variable not defined."));

	if (isset($gre['link1']) && $gre['link1'])
		mwexec("/sbin/route add " . escapeshellarg($gre['tunnel-remote-addr']) . "/" . escapeshellarg($gre['tunnel-remote-net']) . " " . escapeshellarg($gre['tunnel-local-addr']));
	if(is_ipaddrv4($gre['tunnel-remote-addr']))
		file_put_contents("{$g['tmp_path']}/{$greif}_router", $gre['tunnel-remote-addr']);
	if(is_ipaddrv6($gre['tunnel-remote-addr']))
		file_put_contents("{$g['tmp_path']}/{$greif}_routerv6", $gre['tunnel-remote-addr']);

	interfaces_bring_up($greif);

	return $greif;
}

function interfaces_gif_configure($checkparent = 0) {
	global $config;

	if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
		foreach ($config['gifs']['gif'] as $i => $gif) {
			if(empty($gif['gifif']))
				$gre['gifif'] = "gif{$i}";
			if ($checkparent == 1) {
				if (strstr($gif['if'], "_vip"))
					continue;
				if (!empty($config['interfaces'][$gif['if']]) && $config['interfaces'][$gif['if']]['ipaddrv6'] == "track6")
					continue;
			}
			else if ($checkparent == 2) {
				if (!strstr($gre['if'], "_vip"))
					continue;
				if (empty($config['interfaces'][$gif['if']]) || $config['interfaces'][$gif['if']]['ipaddrv6'] != "track6")
					continue;
			}
			/* XXX: Maybe we should report any errors?! */
			interface_gif_configure($gif);
		}
	}
}

/* NOTE: $gifkey is not used but useful for passing this function to array_walk. */
function interface_gif_configure(&$gif, $gifkey = "") {
	global $config, $g;

	if (!is_array($gif))
		return -1;

	$realif = get_real_interface($gif['if']);
	$ipaddr = $gif['ipaddr'];

	if (is_ipaddrv4($gif['remote-addr'])) {
		if (is_ipaddrv4($ipaddr))
			$realifip = $ipaddr;
		else
			$realifip = get_interface_ip($gif['if']);
		$realifgw = get_interface_gateway($gif['if']);
	} else if (is_ipaddrv6($gif['remote-addr'])) {
		if (is_ipaddrv6($ipaddr))
			$realifip = $ipaddr;
		else
			$realifip = get_interface_ipv6($gif['if']);
		$realifgw = get_interface_gateway_v6($gif['if']);
	}
	/* make sure the parent interface is up */
	if($realif)
		interfaces_bring_up($realif);
	else
		log_error(gettext("could not bring realif up -- variable not defined -- interface_gif_configure()"));

	if ($g['booting'] || !(empty($gif['gifif']))) {
		pfSense_interface_destroy($gif['gifif']);
		pfSense_interface_create($gif['gifif']);
		$gifif = $gif['gifif'];
	} else
		$gifif = pfSense_interface_create("gif");

	/* Do not change the order here for more see gif(4) NOTES section. */
	mwexec("/sbin/ifconfig {$gifif} tunnel {$realifip} " . escapeshellarg($gif['remote-addr']));
	if((is_ipaddrv6($gif['tunnel-local-addr'])) || (is_ipaddrv6($gif['tunnel-remote-addr']))) {
		/* XXX: The prefixlen argument for tunnels of ipv6 is useless since it needs to be 128 as enforced by kernel */
		//mwexec("/sbin/ifconfig {$gifif} inet6 " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " prefixlen /" . escapeshellarg($gif['tunnel-remote-net']));
		mwexec("/sbin/ifconfig {$gifif} inet6 " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " prefixlen 128");
	} else {
		mwexec("/sbin/ifconfig {$gifif} " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gif['tunnel-remote-net']));
	}
	if (isset($gif['link0']))
		pfSense_interface_flags($gifif, IFF_LINK0);
	if (isset($gif['link1']))
		pfSense_interface_flags($gifif, IFF_LINK1);
	if($gifif)
		interfaces_bring_up($gifif);
	else
		log_error(gettext("could not bring gifif up -- variable not defined"));

	$iflist = get_configured_interface_list();
	foreach($iflist as $ifname) {
		if($config['interfaces'][$ifname]['if'] == $gifif) {
			if(get_interface_gateway($ifname)) {
				system_routing_configure($ifname);
				break;
			}
			if(get_interface_gateway_v6($ifname)) {
				system_routing_configure($ifname);
				break;
			}
		}
	}


	if(is_ipaddrv4($gif['tunnel-remote-addr']))
		file_put_contents("{$g['tmp_path']}/{$gifif}_router", $gif['tunnel-remote-addr']);
	if(is_ipaddrv6($gif['tunnel-remote-addr']))
		file_put_contents("{$g['tmp_path']}/{$gifif}_routerv6", $gif['tunnel-remote-addr']);

	if (is_ipaddrv4($realifgw)) {
		mwexec("/sbin/route change -host " . escapeshellarg($gif['remote-addr']) . " {$realifgw}");
	}
	if (is_ipaddrv6($realifgw)) {
		mwexec("/sbin/route change -host -inet6 " . escapeshellarg($gif['remote-addr']) . " {$realifgw}");
	}

	interfaces_bring_up($gifif);

	return $gifif;
}

function interfaces_configure() {
	global $config, $g;

	if ($g['platform'] == 'jail')
		return;

	/* Set up our loopback interface */
	interfaces_loopback_configure();

	/* create the unconfigured wireless clones */
	interfaces_create_wireless_clones();

	/* set up LAGG virtual interfaces */
	interfaces_lagg_configure();

	/* set up VLAN virtual interfaces */
	interfaces_vlan_configure();

	interfaces_qinq_configure();

	$iflist = get_configured_interface_with_descr();
	$delayed_list = array();
	$bridge_list = array();
	$track6_list = array();

	/* This is needed to speedup interfaces on bootup. */
	$reload = false;
	if (!$g['booting'])
		$reload = true;

	foreach($iflist as $if => $ifname) {
		$realif = $config['interfaces'][$if]['if'];
		if (strstr($realif, "bridge"))
			$bridge_list[$if] = $ifname;
		else if (strstr($realif, "gre"))
			$delayed_list[$if] = $ifname;
		else if (strstr($realif, "gif"))
			$delayed_list[$if] = $ifname;
		else if (strstr($realif, "ovpn")) {
			//echo "Delaying OpenVPN interface configuration...done.\n";
			continue;
		} else if (!empty($config['interfaces'][$if]['ipaddrv6']) && $config['interfaces'][$if]['ipaddrv6'] == "track6") {
			$track6_list[$if] = $ifname;
		} else {
			if ($g['booting'])
				printf(gettext("Configuring %s interface..."), $ifname);

			if($g['debug'])
				log_error(sprintf(gettext("Configuring %s"), $ifname));
			interface_configure($if, $reload);
			if ($g['booting'])
				echo gettext( "done.") . "\n";
		}
	}

	/*
	 * NOTE: The following function parameter consists of
	 *	1 - Do not load gre/gif/bridge with parent/member as vip
	 *	2 - Do load gre/gif/bridge with parent/member as vip
	 */

	/* set up GRE virtual interfaces */
	interfaces_gre_configure(1);

	/* set up GIF virtual interfaces */
	interfaces_gif_configure(1);

	/* set up BRIDGe virtual interfaces */
	interfaces_bridge_configure(1);

	foreach ($track6_list as $if => $ifname) {
		if ($g['booting'])
			printf(gettext("Configuring %s interface..."), $ifname);
		if ($g['debug'])
			log_error(sprintf(gettext("Configuring %s"), $ifname));

		interface_configure($if, $reload);

		if ($g['booting'])
			echo gettext("done.") . "\n";
	}

	/* bring up vip interfaces */
	interfaces_vips_configure();

	/* set up GRE virtual interfaces */
	interfaces_gre_configure(2);

	/* set up GIF virtual interfaces */
	interfaces_gif_configure(2);

	foreach ($delayed_list as $if => $ifname) {
		if ($g['booting'])
			printf(gettext("Configuring %s interface..."), $ifname);
		if ($g['debug'])
			log_error(sprintf(gettext("Configuring %s"), $ifname));

		interface_configure($if, $reload);

		if ($g['booting'])
			echo gettext("done.") . "\n";
	}

	/* set up BRIDGe virtual interfaces */
	interfaces_bridge_configure(2);

	foreach ($bridge_list as $if => $ifname) {
		if ($g['booting'])
			printf(gettext("Configuring %s interface..."), $ifname);
		if($g['debug'])
			log_error(sprintf(gettext("Configuring %s"), $ifname));

		interface_configure($if, $reload);

		if ($g['booting'])
			echo gettext("done.") . "\n";
	}

	/* configure interface groups */
	interfaces_group_setup();

	if (!$g['booting']) {
		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* reload IPsec tunnels */
		vpn_ipsec_configure();

		/* reload dhcpd (interface enabled/disabled status may have changed) */
		services_dhcpd_configure();

		/* restart dnsmasq */
		services_dnsmasq_configure();

		/* reload captive portal */
		if (function_exists('captiveportal_init_rules'))
			captiveportal_init_rules();
	}

	return 0;
}

function interface_reconfigure($interface = "wan", $reloadall = false) {
	interface_bring_down($interface);
	interface_configure($interface, $reloadall);
}

function interface_vip_bring_down($vip) {
	global $g;

	switch ($vip['mode']) {
	case "proxyarp":
		$vipif = get_real_interface($vip['interface']);
		if (file_exists("{$g['varrun_path']}/choparp_{$vipif}.pid"))
			killbypid("{$g['varrun_path']}/choparp_{$vipif}.pid");
		break;
	case "ipalias":
		$vipif = get_real_interface($vip['interface']);
		if (does_interface_exist($vipif)) {
			if (is_ipaddrv6($vip['subnet']))
				mwexec("/sbin/ifconfig {$vipif} inet6 " . escapeshellarg($vip['subnet']) . " -alias");
			else
				pfSense_interface_deladdress($vipif, $vip['subnet']);
		}
		break;
	case "carp":
		$vipif = "{$vip['interface']}_vip{$vip['vhid']}";
		if (does_interface_exist($vipif))
			pfSense_interface_destroy($vipif);
		break;
	}
}

function interface_bring_down($interface = "wan", $destroy = false, $ifacecfg = false) {
	global $config, $g;

	if (!isset($config['interfaces'][$interface]))
		return;

	if ($g['debug'])
		log_error("Calling interface down for interface {$interface}, destroy is " . (($destroy) ? 'true' : 'false'));

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
					if (isset($ppp['ondemand']) && !$destroy){
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
		$pid = find_dhclient_process($realif);
		if($pid)
			posix_kill($pid, SIGTERM);
		sleep(1);
		unlink_if_exists("{$g['varetc_path']}/dhclient_{$interface}.conf");
		if(does_interface_exist("$realif")) {
			mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " delete", true);
			if ($destroy == true) {
				interface_ipalias_cleanup($interface);
				pfSense_interface_flags($realif, -IFF_UP);
			}
			mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
		}
		break;
	default:
		if(does_interface_exist("$realif")) {
			mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " delete", true);
			if ($destroy == true) {
				interface_ipalias_cleanup($interface);
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
		$pidv6 = find_dhcp6c_process($realif);
		if($pidv6)
			posix_kill($pidv6, SIGTERM);
		sleep(3);
		unlink_if_exists("{$g['varetc_path']}/dhcp6c_{$interface}.conf");
		if (does_interface_exist($realifv6)) {
			$ip6 = find_interface_ipv6($realifv6);
			if (is_ipaddrv6($ip6) && $ip6 != "::")
				mwexec("/sbin/ifconfig " . escapeshellarg($realifv6) . " inet6 {$ip6} delete", true);
			if ($destroy == true) {
				interface_ipalias_cleanup($interface, "inet6");
				pfSense_interface_flags($realif, -IFF_UP);
			}
			mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
		}
		$track6 = link_interface_to_track6($interface);
		break;
	case "6rd":
	case "6to4":
		$realif = "{$interface}_stf";
		if(does_interface_exist("$realif")) {
			$ip6 = get_interface_ipv6($interface);
			if (is_ipaddrv6($ip6))
				mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ip6} delete", true);
			if ($destroy == true) {
				interface_ipalias_cleanup($interface, "inet6");
				pfSense_interface_flags($realif, -IFF_UP);
			}
		}
		$track6 = link_interface_to_track6($interface);
		break;
	default:
		if(does_interface_exist("$realif")) {
			$ip6 = get_interface_ipv6($interface);
			if (is_ipaddrv6($ip6))
				mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ip6} delete", true);
			if (!empty($ifcfg['ipaddrv6']) && is_ipaddrv6($ifcfg['ipaddrv6']))
				mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$ifcfg['ipaddrv6']} delete", true);
			if ($destroy == true) {
				interface_ipalias_cleanup($interface, "inet6");
				pfSense_interface_flags($realif, -IFF_UP);
			}
			mwexec("/usr/sbin/arp -d -i " . escapeshellarg($realif) . " -a");
		}
		$track6 = link_interface_to_track6($interface);
		break;
	}

	if (!empty($track6) && is_array($track6)) {
		if (!function_exists('services_dhcp_configure'))
			require_once('services.inc');
		/* Bring down radvd and dhcp6 on these interfaces */
		services_dhcpd_configure('inet6', $track6);
	}

	$old_router = '';
	if (file_exists("{$g['tmp_path']}/{$realif}_router"))
		$old_router = trim(file_get_contents("{$g['tmp_path']}/{$realif}_router"));
//	log_error("Checking for old router states: {$g['tmp_path']}/{$realif}_router = {$old_router}");
	if (!empty($old_router)) {
		log_error("Clearing states to old gateway {$old_router}.");
		mwexec("/sbin/pfctl -i " . escapeshellarg($realif) . " -Fs");
	}

	/* remove interface up file if it exists */
	unlink_if_exists("{$g['tmp_path']}/{$realif}up");
	unlink_if_exists("{$g['vardb_path']}/{$interface}ip");
	unlink_if_exists("{$g['vardb_path']}/{$interface}ipv6");
	unlink_if_exists("{$g['tmp_path']}/{$realif}_router");
	unlink_if_exists("{$g['tmp_path']}/{$realif}_routerv6");
	unlink_if_exists("{$g['varetc_path']}/nameserver_{$realif}");
	unlink_if_exists("{$g['varetc_path']}/searchdomain_{$realif}");

	/* hostapd and wpa_supplicant do not need to be running when the interface is down.
	 * They will also use 100% CPU if running after the wireless clone gets deleted. */
	if (is_array($ifcfg['wireless'])) {
		kill_hostapd($realif);
		mwexec(kill_wpasupplicant($realif));
	}

	if ($destroy == true) {
		if (preg_match("/^[a-z0-9]+_vip|^tun|^ovpn|^gif|^gre|^lagg|^bridge|vlan|_stf$/i", $realif))
			pfSense_interface_destroy($realif);
	}

	return;
}

function interfaces_ptpid_used($ptpid) {
	global $config;

	if (is_array($config['ppps']['ppp']))
		foreach ($config['ppps']['ppp'] as & $settings)
			if ($ptpid == $settings['ptpid'])
				return true;

	return false;
}

function interfaces_ptpid_next() {

	$ptpid = 0;
	while(interfaces_ptpid_used($ptpid))
		$ptpid++;

	return $ptpid;
}

function getMPDCRONSettings($pppif) {
	global $config;

	$cron_cmd_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $i => $item) {
			if (stripos($item['command'], $cron_cmd_file) !== false)
				return array("ID" => $i, "ITEM" => $item);
		}
	}

	return NULL;
}

function handle_pppoe_reset($post_array) {
	global $config, $g;

	$pppif = "{$post_array['type']}{$post_array['ptpid']}";
	$cron_cmd_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";

	if (!is_array($config['cron']['item']))
		$config['cron']['item'] = array();

	$itemhash = getMPDCRONSettings($pppif);

	// reset cron items if necessary and return
	if (empty($post_array['pppoe-reset-type'])) {
		if (isset($itemhash))
			unset($config['cron']['item'][$itemhash['ID']]);
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
		return;
	}

	if (empty($itemhash))
		$itemhash = array();
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
	} else if (isset($post_array['pppoe-reset-type']) && $post_array['pppoe-reset-type'] == "preset") {
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
	if (empty($item))
		return;
	if (isset($itemhash['ID']))
		$config['cron']['item'][$itemhash['ID']] = $item;
	else
		$config['cron']['item'][] = $item;
}

/*
 * This function can configure PPPoE, MLPPP (PPPoE), PPTP.
 * It writes the mpd config file to /var/etc every time the link is opened.
 */
function interface_ppps_configure($interface) {
	global $config, $g;

	/* Return for unassigned interfaces. This is a minimum requirement. */
	if (empty($config['interfaces'][$interface]))
		return 0;
	$ifcfg = $config['interfaces'][$interface];
	if (!isset($ifcfg['enable']))
		return 0;

	// mpd5 requires a /var/spool/lock directory for PPP modem links.
	if(!is_dir("/var/spool/lock")) {
		mkdir("/var/spool/lock", 0777, true);
	}
	// mpd5 modem chat script expected in the same directory as the mpd_xxx.conf files
	if (!file_exists("{$g['varetc_path']}/mpd.script"))
		@symlink("/usr/local/sbin/mpd.script", "{$g['varetc_path']}/mpd.script");

	if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			if ($ifcfg['if'] == $ppp['if'])
				break;
		}
	}
	if (!$ppp || $ifcfg['if'] != $ppp['if']){
		log_error(sprintf(gettext("Can't find PPP config for %s in interface_ppps_configure()."), $ifcfg['if']));
		return 0;
	}
	$pppif = $ifcfg['if'];
	if ($ppp['type'] == "ppp")
		$type = "modem";
	else
		$type = $ppp['type'];
	$upper_type = strtoupper($ppp['type']);

	if($g['booting']) {
		$descr = isset($ifcfg['descr']) ? $ifcfg['descr'] : strtoupper($interface);
		echo "starting {$pppif} link...";
		// Do not re-configure the interface if we are booting and it's already been started
		if(file_exists("{$g['varrun_path']}/{$ppp['type']}_{$interface}.pid"))
			return 0;
	}

	$ports = explode(',',$ppp['ports']);
	if ($type != "modem") {
		foreach ($ports as $pid => $port) {
			$ports[$pid] = get_real_interface($port);
			if (empty($ports[$pid]))
				return 0;
		}
	}
	$localips = explode(',',$ppp['localip']);
	$gateways = explode(',',$ppp['gateway']);
	$subnets = explode(',',$ppp['subnet']);

	/* We bring up the parent interface first because if DHCP is configured on the parent we need
	 * to obtain an address first so we can write it in the mpd .conf file for PPTP and L2TP configs
	 */
	foreach($ports as $pid => $port){
		switch ($ppp['type']) {
			case "pppoe":
				/* Bring the parent interface up */
				interfaces_bring_up($port);
				pfSense_ngctl_attach(".", $port);
				/* Enable setautosrc to automatically change mac address if parent interface's changes */
				mwexec("ngctl msg {$port}: setautosrc 1");
				break;
			case "pptp":
			case "l2tp":
				/* configure interface */
				if(is_ipaddr($localips[$pid])){
					// Manually configure interface IP/subnet
					pfSense_interface_setaddress($port, "{$localips[$pid]}/{$subnets[$pid]}");
					interfaces_bring_up($port);
				} else if (empty($localips[$pid]))
					$localips[$pid] = get_interface_ip($port); // try to get the interface IP from the port

				if(!is_ipaddr($localips[$pid])){
					log_error("Could not get a Local IP address for PPTP/L2TP link on {$port} in interfaces_ppps_configure. Using 0.0.0.0 ip!");
					$localips[$pid] = "0.0.0.0";
				}
				if(!is_ipaddr($gateways[$pid])){
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
				log_error(sprintf(gettext("Unkown %s configured as ppp interface."), $type));
				break;
		}
	}

	if (is_array($ports) && count($ports) > 1)
		$multilink = "enable";
	else
		$multilink = "disable";

	if ($type == "modem"){
		if (is_ipaddr($ppp['localip']))
			$localip = $ppp['localip'];
		else
			$localip = '0.0.0.0';

		if (is_ipaddr($ppp['gateway']))
			$gateway = $ppp['gateway'];
		else
			$gateway = "10.64.64.{$pppid}";
		$ranges = "{$localip}/0 {$gateway}/0";

		if (empty($ppp['apnum']))
			$ppp['apnum'] = 1;
	} else
		$ranges = "0.0.0.0/0 0.0.0.0/0";

	if (isset($ppp['ondemand']))
		$ondemand = "enable";
	else
		$ondemand = "disable";
	if (!isset($ppp['idletimeout']))
		$ppp['idletimeout'] = 0;

	if (empty($ppp['username']) && $type == "modem"){
		$ppp['username'] = "user";
		$ppp['password'] = "none";
	}
	if (empty($ppp['password']) && $type == "modem")
		$passwd = "none";
	else
		$passwd = base64_decode($ppp['password']);

	$bandwidths = explode(',',$ppp['bandwidth']);
	$defaultmtu = "1492";
	if (!empty($ifcfg['mtu']))
		$defaultmtu = intval($ifcfg['mtu']);
	$mtus = explode(',',$ppp['mtu']);
	$mrus = explode(',',$ppp['mru']);

	if (isset($ppp['mrru']))
		$mrrus = explode(',',$ppp['mrru']);

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
	set bundle enable ipv6cp
	set iface name {$pppif}

EOD;
	$setdefaultgw = false;
	$founddefaultgw = false;
	if (is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($interface == $gateway['interface'] && isset($gateway['defaultgw'])) {
				$setdefaultgw = true;
				break;
			} else if (isset($gateway['defaultgw']) && !empty($gateway['interface'])) {
				$founddefaultgw = true;
				break;
			}
		}
	}

	if (($interface == "wan" && $founddefaultgw == false) || $setdefaultgw == true){
		$setdefaultgw = true;
		$mpdconf .= <<<EOD
	set iface route default

EOD;
	}
	$mpdconf .= <<<EOD
	set iface {$ondemand} on-demand
	set iface idle {$ppp['idletimeout']}

EOD;

	if (isset($ppp['ondemand']))
		$mpdconf .= <<<EOD
	set iface addrs 10.10.1.1 10.10.1.2

EOD;

	if (isset($ppp['tcpmssfix']))
		$tcpmss = "disable";
	else
		$tcpmss = "enable";
		$mpdconf .= <<<EOD
	set iface {$tcpmss} tcpmssfix

EOD;

	$mpdconf .= <<<EOD
	set iface up-script /usr/local/sbin/ppp-linkup
	set iface down-script /usr/local/sbin/ppp-linkdown
	set ipcp ranges {$ranges}

EOD;
	if (isset($ppp['vjcomp']))
		$mpdconf .= <<<EOD
	set ipcp no vjcomp

EOD;

	if (isset($config['system']['dnsallowoverride']))
		$mpdconf .= <<<EOD
	set ipcp enable req-pri-dns
	set ipcp enable req-sec-dns

EOD;
	if (!isset($ppp['verbose_log']))
		$mpdconf .= <<<EOD
	#log -bund -ccp -chat -iface -ipcp -lcp -link

EOD;
	foreach($ports as $pid => $port){
		$port = get_real_interface($port);
		$mpdconf .= <<<EOD

	create link static {$interface}_link{$pid} {$type}
	set link action bundle {$interface}
	set link {$multilink} multilink
	set link keep-alive 10 60
	set link max-redial 0

EOD;
		if (isset($ppp['shortseq']))
			$mpdconf .= <<<EOD
	set link no shortseq

EOD;

		if (isset($ppp['acfcomp']))
			$mpdconf .= <<<EOD
	set link no acfcomp

EOD;

		if (isset($ppp['protocomp']))
			$mpdconf .= <<<EOD
	set link no protocomp

EOD;

		$mpdconf .= <<<EOD
	set link disable chap pap
	set link accept chap pap eap
	set link disable incoming

EOD;


		if (!empty($bandwidths[$pid]))
			$mpdconf .= <<<EOD
	set link bandwidth {$bandwidths[$pid]}

EOD;

		if (empty($mtus[$pid]))
			$mtus[$pid] = $defaultmtu;
			$mpdconf .= <<<EOD
	set link mtu {$mtus[$pid]}

EOD;

		if (!empty($mrus[$pid]))
			$mpdconf .= <<<EOD
	set link mru {$mrus[$pid]}

EOD;

		if (!empty($mrrus[$pid]))
			$mpdconf .= <<<EOD
	set link mrru {$mrrus[$pid]}

EOD;

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
			if($ppp['pin-wait'] == "")
				$ppp['pin-wait'] = 0;
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
			// Send a null service name if none is set.
			$provider = isset($ppp['provider']) ? $ppp['provider'] : "";
			$mpdconf .= <<<EOD
	set pppoe service "{$provider}"

EOD;
		}
		if ($type == "pppoe")
			$mpdconf .= <<<EOD
	set pppoe iface {$port}

EOD;

		if ($type == "pptp" || $type == "l2tp") {
			$mpdconf .= <<<EOD
	set {$type} self {$localips[$pid]}
	set {$type} peer {$gateways[$pid]}

EOD;
		}

		$mpdconf .= "\topen\n";
	} //end foreach($port)


	/* Generate mpd.conf. If mpd_[interface].conf exists in the conf path, then link to it instead of generating a fresh conf file. */
	if (file_exists("{$g['conf_path']}/mpd_{$interface}.conf"))
		@symlink("{$g['conf_path']}/mpd_{$interface}.conf", "{$g['varetc_path']}/mpd_{$interface}.conf");
	else {
		$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
		if (!$fd) {
			log_error(sprintf(gettext("Error: cannot open mpd_%s.conf in interface_ppps_configure().%s"), $interface, "\n"));
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
			conf_mount_rw();
			file_put_contents("/conf/{$pppif}.log", '');
			conf_mount_ro();
		}
	} else {
		if (file_exists("/conf/{$pppif}.log")) {
			conf_mount_rw();
			@unlink("/conf/{$pppif}.log");
			conf_mount_ro();
		}
	}

	/* clean up old lock files */
	foreach($ports as $port) {
		if(file_exists("{$g['var_path']}/spool/lock/LCK..{$port}"))
			unlink("{$g['var_path']}/spool/lock/LCK..{$port}");
	}

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd5 -b -k -d {$g['varetc_path']} -f mpd_{$interface}.conf -p {$g['varrun_path']}/" .
		escapeshellarg($ppp['type']) . "_{$interface}.pid -s ppp " . escapeshellarg($ppp['type']) . "client");

	// Check for PPPoE periodic reset request
	if ($type == "pppoe") {
		if (!empty($ppp['pppoe-reset-type']))
			interface_setup_pppoe_reset_file($ppp['if'], $interface);
		else
			interface_setup_pppoe_reset_file($ppp['if']);
	}
	/* wait for upto 10 seconds for the interface to appear (ppp(oe)) */
	$i = 0;
	while($i < 10) {
		exec("/sbin/ifconfig " . escapeshellarg($ppp['if']) . " 2>&1", $out, $ret);
		if($ret == 0)
			break;
		sleep(1);
		$i++;
	}

	/* we only support the 3gstats.php for huawei modems for now. Will add more later. */
	/* We should be able to launch the right version for each modem */
	/* We can also guess the mondev from the manufacturer */
	exec("usbconfig | egrep -ie '(huawei)'", $usbmodemoutput);
	mwexec("/bin/ps auxww|grep \"{$interface}\" |grep \"[3]gstats\" | awk '{print $2}' |xargs kill");
	foreach($ports as $port) {
		if(preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
			$mondev  = substr(basename($port), 0, -1);
			$devlist = glob("/dev/{$mondev}?");
			$mondev = basename(end($devlist));
		}
		if(preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
			$mondev  = substr(basename($port), 0, -1) . "1";
		}
		log_error("Starting 3gstats.php on device '{$mondev}' for interface '{$interface}'");
		mwexec_bg("/usr/local/bin/3gstats.php {$mondev} {$interface}");
	}

	return 1;
}

function interfaces_carp_setup() {
	global $g, $config;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_carp_setup() being called $mt\n";
	}

	if ($g['booting']) {
		echo gettext("Configuring CARP settings...");
		mute_kernel_msgs();
	}

	/* suck in configuration items */
	if ($config['hasync']) {
		$pfsyncenabled = $config['hasync']['pfsyncenabled'];
		$balancing = $config['hasync']['balancing'];
		$pfsyncinterface = $config['hasync']['pfsyncinterface'];
		$pfsyncpeerip = $config['hasync']['pfsyncpeerip'];
	} else {
		unset($pfsyncinterface);
		unset($balancing);
		unset($pfsyncenabled);
	}

	if ($balancing) {
		mwexec("/sbin/sysctl net.inet.carp.arpbalance=1", true);
		mwexec("/sbin/sysctl net.inet.carp.preempt=0", true);
	} else
		mwexec("/sbin/sysctl net.inet.carp.preempt=1", true);

	mwexec("sbin/sysctl net.inet.carp.log=1", true);
	if (!empty($pfsyncinterface))
		$carp_sync_int = get_real_interface($pfsyncinterface);
	else
		unset($carp_sync_int);

	/* setup pfsync interface */
	if ($carp_sync_int and $pfsyncenabled) {
		if (is_ipaddr($pfsyncpeerip))
			$syncpeer = "syncpeer {$pfsyncpeerip}";
		else
			$syncpeer = "-syncpeer";

		mwexec("/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} {$syncpeer} up", false);

		sleep(1);

		/* XXX: Handle an issue with pfsync(4) and carp(4). In a cluster carp will come up before pfsync(4) has updated and so will cause issues
		 * for existing sessions.
		 */
		log_error("waiting for pfsync...");
		$i = 0;
		while (intval(trim(`/sbin/ifconfig pfsync0 | /usr/bin/grep 'syncok: 0' | /usr/bin/grep -v grep | /usr/bin/wc -l`)) == 0 && $i < 30) {
			$i++;
			sleep(1);
		}
		log_error("pfsync done in $i seconds.");
		log_error("Configuring CARP settings finalize...");
	} else {
		mwexec("/sbin/ifconfig pfsync0 -syncdev -syncpeer down", false);
	}

	if($config['virtualip']['vip'])
		mwexec("/sbin/sysctl net.inet.carp.allow=1", true);
	else
		mwexec("/sbin/sysctl net.inet.carp.allow=0", true);

	if ($g['booting']) {
		unmute_kernel_msgs();
		echo gettext("done.") . "\n";
	}
}

function interface_proxyarp_configure($interface = "") {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interface_proxyarp_configure() being called $mt\n";
	}

	/* kill any running choparp */
	if (empty($interface))
		killbyname("choparp");
	else {
		$vipif = get_real_interface($interface);
		if (file_exists("{$g['varrun_path']}/choparp_{$vipif}.pid"))
			killbypid("{$g['varrun_path']}/choparp_{$vipif}.pid");
	}

	$paa = array();
	if (!empty($config['virtualip']) && is_array($config['virtualip']['vip'])) {

		/* group by interface */
		foreach ($config['virtualip']['vip'] as $vipent) {
			if ($vipent['mode'] === "proxyarp") {
				if ($vipent['interface'])
					$proxyif = $vipent['interface'];
				else
					$proxyif = "wan";

				if (!empty($interface) && $interface != $proxyif)
					continue;

				if (!is_array($paa[$proxyif]))
					$paa[$proxyif] = array();

				$paa[$proxyif][] = $vipent;
			}
		}
	}

	if (!empty($interface)) {
		if (is_array($paa[$interface])) {
			$paaifip = get_interface_ip($interface);
			if (!is_ipaddr($paaifip))
				return;
			$args = get_real_interface($interface) . " auto";
			foreach ($paa[$interface] as $paent) {
				if (isset($paent['subnet']))
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				else if (isset($paent['range']))
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" . $paent['range']['to']);
			}
			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	} else if (count($paa) > 0) {
		foreach ($paa as $paif => $paents)  {
			$paaifip = get_interface_ip($paif);
			if (!is_ipaddr($paaifip))
				continue;
			$args = get_real_interface($paif) . " auto";
			foreach ($paents as $paent) {
				if (isset($paent['subnet']))
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				else if (isset($paent['range']))
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" . $paent['range']['to']);
			}
			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	}
}

function interface_ipalias_cleanup($interface, $inet = "inet4") {
	global $g, $config;

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "ipalias" && $vip['interface'] == $interface) {
				if ($inet == "inet6" && is_ipaddrv6($vip['subnet']))
					interface_vip_bring_down($vip);
				else if ($inet == "inet4" && is_ipaddrv4($vip['subnet']))
					interface_vip_bring_down($vip);
			}
		}
	}
}

function interfaces_vips_configure($interface = "") {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_vips_configure() being called $mt\n";
	}
	$paa = array();
	if(is_array($config['virtualip']['vip'])) {
		$carp_setuped = false;
		$anyproxyarp = false;
		foreach ($config['virtualip']['vip'] as $vip) {
			switch ($vip['mode']) {
			case "proxyarp":
				/* nothing it is handled on interface_proxyarp_configure() */
				if ($interface <> "" && $vip['interface'] <> $interface)
					continue;
				$anyproxyarp = true;
				break;
			case "ipalias":
				if ($interface <> "" && $vip['interface'] <> $interface)
					continue;
				interface_ipalias_configure($vip);
				break;
			case "carp":
				if ($interface <> "" && $vip['interface'] <> $interface)
					continue;
				if ($carp_setuped == false)
					$carp_setuped = true;
				interface_carp_configure($vip);
				break;
			}
		}
		if ($carp_setuped == true)
			interfaces_carp_setup();
		if ($anyproxyarp == true)
			interface_proxyarp_configure();
	}
}

function interface_ipalias_configure(&$vip) {
	global $config;

	if ($vip['mode'] != "ipalias")
		return;

	$if = get_real_interface($vip['interface']);

	if ($vip['interface'] != 'lo0' && stristr($if, "_vip") === false) {
		if (!isset($config['interfaces'][$vip['interface']]))
			return;

		if (!isset($config['interfaces'][$vip['interface']]['enable']))
			return;
	}

	$af = "inet";
	if(is_ipaddrv6($vip['subnet']))
		$af = "inet6";
	mwexec("/sbin/ifconfig " . escapeshellarg($if) ." {$af} ". escapeshellarg($vip['subnet']) ."/" . escapeshellarg($vip['subnet_bits']) . " alias");
}

function interface_reload_carps($cif) {
	global $config;

	$carpifs = link_ip_to_carp_interface(find_interface_ip($cif));
	if (empty($carpifs))
		return;

	$carps = explode(" ", $carpifs);
	if(is_array($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			if (in_array($vip['carpif'], $carps)) {
				switch ($vip['mode']) {
				case "carp":
					interface_vip_bring_down($vip);
					sleep(1);
					interface_carp_configure($vip);
					break;
				case "ipalias":
					interface_vip_bring_down($vip);
					sleep(1);
					interface_ipalias_configure($vip);
					break;
				}
			}
		}
	}
}

function interface_carp_configure(&$vip) {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interface_carp_configure() being called $mt\n";
	}

	if ($vip['mode'] != "carp")
		return;

	/*
	 * ensure the interface containing the VIP really exists
	 * prevents a panic if the interface is missing or invalid
	 */
	$realif = get_real_interface($vip['interface']);
	if (!does_interface_exist($realif)) {
		file_notice("CARP", sprintf(gettext("Interface specified for the virtual IP address %s does not exist. Skipping this VIP."), $vip['subnet']), "Firewall: Virtual IP", "");
		return;
	}

	if(is_ipaddrv4($vip['subnet'])) {
		/* Ensure CARP IP really exists prior to loading up. */
		$ww_subnet_ip = find_interface_ip($realif);
		$ww_subnet_bits = find_interface_subnet($realif);
		if (!ip_in_subnet($vip['subnet'], gen_subnet($ww_subnet_ip, $ww_subnet_bits) . "/" . $ww_subnet_bits) && !ip_in_interface_alias_subnet($vip['interface'], $vip['subnet'])) {
			file_notice("CARP", sprintf(gettext("Sorry but we could not find a matching real interface subnet for the virtual IP address %s."), $vip['subnet']), "Firewall: Virtual IP", "");
			return;
		}
	}
	if(is_ipaddrv6($vip['subnet'])) {
		/* Ensure CARP IP really exists prior to loading up. */
		$ww_subnet_ip = find_interface_ipv6($realif);
		$ww_subnet_bits = find_interface_subnetv6($realif);
		if (!ip_in_subnet($vip['subnet'], gen_subnetv6($ww_subnet_ip, $ww_subnet_bits) . "/" . $ww_subnet_bits) && !ip_in_interface_alias_subnet($vip['interface'], $vip['subnet'])) {
			file_notice("CARP", sprintf(gettext("Sorry but we could not find a matching real interface subnet for the virtual IPv6 address %s."), $vip['subnet']), "Firewall: Virtual IP", "");
			return;
		}
	}

	// set the vip interface to the vhid
	$vipif = "{$vip['interface']}_vip{$vip['vhid']}";

	/* create the carp interface and setup */
	if (does_interface_exist($vipif)) {
		pfSense_interface_flags($vipif, -IFF_UP);
	} else {
		$carpif = pfSense_interface_create("carp");
		pfSense_interface_rename($carpif, $vipif);
		pfSense_ngctl_name("{$carpif}:", $vipif);
	}

	/* invalidate interface cache */
	get_interface_arr(true);

	$vip_password = $vip['password'];
	$vip_password = escapeshellarg(addslashes(str_replace(" ", "", $vip_password)));
	if ($vip['password'] != "")
		$password = " pass {$vip_password}";

	$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
	$advbase = "";
	if (!empty($vip['advbase']))
		$advbase = "advbase " . escapeshellarg($vip['advbase']);

	if(is_ipaddrv4($vip['subnet'])) {
		$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
		mwexec("/sbin/ifconfig {$vipif} {$vip['subnet']}/{$vip['subnet_bits']} vhid {$vip['vhid']} advskew {$vip['advskew']} {$advbase} {$password}");
	}
	if(is_ipaddrv6($vip['subnet'])) {
		$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
		mwexec("/sbin/ifconfig {$vipif} inet6 {$vip['subnet']} prefixlen {$vip['subnet_bits']} vhid {$vip['vhid']} advskew {$vip['advskew']} {$advbase} {$password}");
	}

	interfaces_bring_up($vipif);

	if (isset($config['virtualip']['vip']) && is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as &$vip_alias) {
			if ($vip_alias['interface'] == $vipif)
				interface_ipalias_configure($vip_alias);
		}
	}

	return $vipif;
}

function interface_wireless_clone($realif, $wlcfg) {
	global $config, $g;
	/*   Check to see if interface has been cloned as of yet.
	 *   If it has not been cloned then go ahead and clone it.
	 */
	$needs_clone = false;
	if(is_array($wlcfg['wireless']))
		$wlcfg_mode = $wlcfg['wireless']['mode'];
	else
		$wlcfg_mode = $wlcfg['mode'];
	switch($wlcfg_mode) {
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
	if(does_interface_exist($realif)) {
		exec("/sbin/ifconfig " . escapeshellarg($realif), $output, $ret);
		$ifconfig_str = implode($output);
		if(($wlcfg_mode == "hostap") && (! preg_match("/hostap/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to hostap mode"), $realif));
			$needs_clone = true;
		}
		if(($wlcfg_mode == "adhoc") && (! preg_match("/adhoc/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to adhoc mode"), $realif));
			$needs_clone = true;
		}
		if(($wlcfg_mode == "bss") && (preg_match("/hostap|adhoc/si", $ifconfig_str))) {
			log_error(sprintf(gettext("Interface %s changed to infrastructure mode"), $realif));
			$needs_clone = true;
		}
	} else {
		$needs_clone = true;
	}

	if($needs_clone == true) {
		/* remove previous instance if it exists */
		if(does_interface_exist($realif))
			pfSense_interface_destroy($realif);

		log_error(sprintf(gettext("Cloning new wireless interface %s"), $realif));
		// Create the new wlan interface. FreeBSD returns the new interface name.
		// example:  wlan2
		exec("/sbin/ifconfig wlan create wlandev {$baseif} {$mode} bssid 2>&1", $out, $ret);
		if($ret <> 0) {
			log_error(sprintf(gettext('Failed to clone interface %1$s with error code %2$s, output %3$s'), $baseif, $ret, $out[0]));
			return false;
		}
		$newif = trim($out[0]);
		// Rename the interface to {$parentnic}_wlan{$number}#: EX: ath0_wlan0
		pfSense_interface_rename($newif, $realif);
		// FIXME: not sure what ngctl is for. Doesn't work.
		// mwexec("/usr/sbin/ngctl name {$newif}: {$realif}", false);
		file_put_contents("{$g['tmp_path']}/{$realif}_oldmac", get_interface_mac($realif));
	}
	return true;
}

function interface_sync_wireless_clones(&$ifcfg, $sync_changes = false) {
	global $config, $g;

	$shared_settings = array('standard', 'turbo', 'protmode', 'txpower', 'channel',
				 'diversity', 'txantenna', 'rxantenna', 'distance',
				 'regdomain', 'regcountry', 'reglocation');

	if(!is_interface_wireless($ifcfg['if']))
		return;

	$baseif = interface_get_wireless_base($ifcfg['if']);

	// Sync shared settings for assigned clones
	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($baseif == interface_get_wireless_base($config['interfaces'][$if]['if']) && $ifcfg['if'] != $config['interfaces'][$if]['if']) {
			if (isset($config['interfaces'][$if]['wireless']['standard']) || $sync_changes) {
				foreach ($shared_settings as $setting) {
					if ($sync_changes) {
						if (isset($ifcfg['wireless'][$setting]))
							$config['interfaces'][$if]['wireless'][$setting] = $ifcfg['wireless'][$setting];
						else if (isset($config['interfaces'][$if]['wireless'][$setting]))
							unset($config['interfaces'][$if]['wireless'][$setting]);
					} else {
						if (isset($config['interfaces'][$if]['wireless'][$setting]))
							$ifcfg['wireless'][$setting] = $config['interfaces'][$if]['wireless'][$setting];
						else if (isset($ifcfg['wireless'][$setting]))
							unset($ifcfg['wireless'][$setting]);
					}
				}
				if (!$sync_changes)
					break;
			}
		}
	}

	// Read or write settings at shared area
	if (isset($config['wireless']['interfaces'][$baseif]) && is_array($config['wireless']['interfaces'][$baseif])) {
		foreach ($shared_settings as $setting) {
			if ($sync_changes) {
				if (isset($ifcfg['wireless'][$setting]))
					$config['wireless']['interfaces'][$baseif][$setting] = $ifcfg['wireless'][$setting];
				else if (isset($config['wireless']['interfaces'][$baseif][$setting]))
					unset($config['wireless']['interfaces'][$baseif][$setting]);
			} else if (isset($config['wireless']['interfaces'][$baseif][$setting])) {
				if (isset($config['wireless']['interfaces'][$baseif][$setting]))
					$ifcfg['wireless'][$setting] = $config['wireless']['interfaces'][$baseif][$setting];
				else if (isset($ifcfg['wireless'][$setting]))
					unset($ifcfg['wireless'][$setting]);
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

	$fd_set = fopen("{$g['tmp_path']}/{$if}_setup.sh","w");
	fwrite($fd_set, "#!/bin/sh\n");
	fwrite($fd_set, "# {$g['product_name']} wireless configuration script.\n\n");

	/* set values for /path/program */
	$hostapd = "/usr/sbin/hostapd";
	$wpa_supplicant = "/usr/sbin/wpa_supplicant";
	$ifconfig = "/sbin/ifconfig";
	$sysctl = "/sbin/sysctl";
	$killall = "/usr/bin/killall";

	/* Set all wireless ifconfig variables (split up to get rid of needed checking) */

	$wlcmd = array();
	$wl_sysctl = array();
	/* Make sure it's up */
	$wlcmd[] = "up";
	/* Set a/b/g standard */
	$standard = str_replace(" Turbo", "", $wlcfg['standard']);
	$wlcmd[] = "mode " . escapeshellarg($standard);

	/* XXX: Disable ampdu for now on mwl when running in 11n mode
	 * to prevent massive packet loss under certain conditions. */
	if(preg_match("/^mwl/i", $if) && ($standard == "11ng" || $standard == "11na"))
		$wlcmd[] = "-ampdu";

	/* Set ssid */
	if($wlcfg['ssid'])
		$wlcmd[] = "ssid " .escapeshellarg($wlcfg['ssid']);

	/* Set 802.11g protection mode */
	$wlcmd[] = "protmode " . escapeshellarg($wlcfg['protmode']);

	/* set wireless channel value */
	if(isset($wlcfg['channel'])) {
		if($wlcfg['channel'] == "0") {
			$wlcmd[] = "channel any";
		} else {
			$wlcmd[] = "channel " . escapeshellarg($wlcfg['channel']);
		}
	}

	/* Set antenna diversity value */
	if(isset($wlcfg['diversity']))
		$wl_sysctl[] = "diversity=" . escapeshellarg($wlcfg['diversity']);

	/* Set txantenna value */
	if(isset($wlcfg['txantenna']))
		$wl_sysctl[] = "txantenna=" . escapeshellarg($wlcfg['txantenna']);

	/* Set rxantenna value */
	if(isset($wlcfg['rxantenna']))
		$wl_sysctl[] = "rxantenna=" . escapeshellarg($wlcfg['rxantenna']);

	/* set Distance value */
	if($wlcfg['distance'])
		$distance = escapeshellarg($wlcfg['distance']);

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

	/* Not neccesary to set BSS mode as this is default if adhoc and/or hostap is NOT set */

	/* handle hide ssid option */
	if(isset($wlcfg['hidessid']['enable'])) {
		$wlcmd[] = "hidessid";
	} else {
		$wlcmd[] = "-hidessid";
	}

	/* handle pureg (802.11g) only option */
	if(isset($wlcfg['pureg']['enable'])) {
		$wlcmd[] = "mode 11g pureg";
	} else {
		$wlcmd[] = "-pureg";
	}

	/* handle puren (802.11n) only option */
	if(isset($wlcfg['puren']['enable'])) {
		$wlcmd[] = "puren";
	} else {
		$wlcmd[] = "-puren";
	}

	/* enable apbridge option */
	if(isset($wlcfg['apbridge']['enable'])) {
		$wlcmd[] = "apbridge";
	} else {
		$wlcmd[] = "-apbridge";
	}

	/* handle turbo option */
	if(isset($wlcfg['turbo']['enable'])) {
		$wlcmd[] = "mediaopt turbo";
	} else {
		$wlcmd[] = "-mediaopt turbo";
	}

	/* handle txpower setting */
	/* if($wlcfg['txpower'] <> "")
		$wlcmd[] = "txpower " . escapeshellarg($wlcfg['txpower']);
	*/
	/* handle wme option */
	if(isset($wlcfg['wme']['enable'])) {
		$wlcmd[] = "wme";
	} else {
		$wlcmd[] = "-wme";
	}

	/* set up wep if enabled */
	$wepset = "";
	if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
		switch($wlcfg['wpa']['auth_algs']) {
			case "1":
				$wepset .= "authmode open wepmode on ";
				break;
			case "2":
				$wepset .= "authmode shared wepmode on ";
				break;
			case "3":
				$wepset .= "authmode mixed wepmode on ";
		}
		$i = 1;
		foreach ($wlcfg['wep']['key'] as $wepkey) {
			$wepset .= "wepkey " . escapeshellarg("{$i}:{$wepkey['value']}") . " ";
			if (isset($wepkey['txkey'])) {
				$wlcmd[] = "weptxkey {$i} ";
			}
			$i++;
		}
		$wlcmd[] = $wepset;
	} else {
		$wlcmd[] = "authmode open wepmode off ";
	}

	kill_hostapd($if);
	mwexec(kill_wpasupplicant("{$if}"));

	/* generate wpa_supplicant/hostap config if wpa is enabled */
	conf_mount_rw();

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
psk="{$wlcfg['wpa']['passphrase']}"
pairwise={$wlcfg['wpa']['wpa_pairwise']}
group={$wlcfg['wpa']['wpa_pairwise']}
}
EOD;

			@file_put_contents("{$g['varetc_path']}/wpa_supplicant_{$if}.conf", $wpa);
			unset($wpa);
		}
		break;
	case 'hostap':
		if (!empty($wlcfg['wpa']['passphrase']))
			$wpa_passphrase = "wpa_passphrase={$wlcfg['wpa']['passphrase']}\n";
		else
			$wpa_passphrase = "";
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
auth_algs={$wlcfg['wpa']['auth_algs']}
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
# Enable the next lines for preauth when roaming. Interface = wired or wireless interface talking to the AP you want to roam from/to
rsn_preauth=1
rsn_preauth_interfaces={$if}

EOD;
			}
			if (is_array($wlcfg['wpa']['ieee8021x']) && isset($wlcfg['wpa']['ieee8021x']['enable'])) {
				$wpa .= "ieee8021x=1\n";

			if (!empty($wlcfg['auth_server_addr']) && !empty($wlcfg['auth_server_shared_secret'])) {
				$auth_server_port = "1812";
				if (!empty($wlcfg['auth_server_port']) && is_numeric($wlcfg['auth_server_port']))
					$auth_server_port = intval($wlcfg['auth_server_port']);
				$wpa .= <<<EOD

auth_server_addr={$wlcfg['auth_server_addr']}
auth_server_port={$auth_server_port}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret']}

EOD;
				if (!empty($wlcfg['auth_server_addr2']) && !empty($wlcfg['auth_server_shared_secret2'])) {
					$auth_server_port2 = "1812";
					if (!empty($wlcfg['auth_server_port2']) && is_numeric($wlcfg['auth_server_port2']))
						$auth_server_port2 = intval($wlcfg['auth_server_port2']);

					$wpa .= <<<EOD
auth_server_addr={$wlcfg['auth_server_addr2']}
auth_server_port={$auth_server_port2}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret2']}

EOD;
					}
				}
			}

			@file_put_contents("{$g['varetc_path']}/hostapd_{$if}.conf", $wpa);
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
			fwrite($fd_set, "{$sysctl} {$wl_sysctl_prefix}.{$wl_sysctl_line}\n");
		}
	}

	/* set ack timers according to users preference (if he/she has any) */
	if($distance) {
		fwrite($fd_set, "# Enable ATH distance settings\n");
		fwrite($fd_set, "/sbin/athctrl.sh -i {$baseif} -d {$distance}\n");
	}

	if (isset($wlcfg['wpa']['enable'])) {
		if ($wlcfg['mode'] == "bss") {
			fwrite($fd_set, "{$wpa_supplicant} -B -i {$if} -c {$g['varetc_path']}/wpa_supplicant_{$if}.conf\n");
		}
		if ($wlcfg['mode'] == "hostap") {
			/* add line to script to restore old mac to make hostapd happy */
			if (file_exists("{$g['tmp_path']}/{$if}_oldmac")) {
				$if_oldmac = file_get_contents("{$g['tmp_path']}/{$if}_oldmac");
				if (is_macaddr($if_oldmac))
					fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
						" link " . escapeshellarg($if_oldmac) . "\n");
			}

			fwrite($fd_set, "{$hostapd} -B -P {$g['varrun_path']}/hostapd_{$if}.pid {$g['varetc_path']}/hostapd_{$if}.conf\n");

			/* add line to script to restore spoofed mac after running hostapd */
			if (file_exists("{$g['tmp_path']}/{$if}_oldmac")) {
				if ($wl['spoofmac'])
					$if_curmac = $wl['spoofmac'];
				else
					$if_curmac = get_interface_mac($if);
				if (is_macaddr($if_curmac))
					fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
						" link " . escapeshellarg($if_curmac) . "\n");
			}
		}
	}

	fclose($fd_set);
	conf_mount_ro();

	/* Making sure regulatory settings have actually changed
	 * before applying, because changing them requires bringing
	 * down all wireless networks on the interface. */
	exec("{$ifconfig} " . escapeshellarg($if), $output);
	$ifconfig_str = implode($output);
	unset($output);
	$reg_changing = false;

	/* special case for the debug country code */
	if ($wlcfg['regcountry'] == 'DEBUG' && !preg_match("/\sregdomain\s+DEBUG\s/si", $ifconfig_str))
		$reg_changing = true;
	else if ($wlcfg['regdomain'] && !preg_match("/\sregdomain\s+{$wlcfg['regdomain']}\s/si", $ifconfig_str))
		$reg_changing = true;
	else if ($wlcfg['regcountry'] && !preg_match("/\scountry\s+{$wlcfg['regcountry']}\s/si", $ifconfig_str))
		$reg_changing = true;
	else if ($wlcfg['reglocation'] == 'anywhere' && preg_match("/\s(indoor|outdoor)\s/si", $ifconfig_str))
		$reg_changing = true;
	else if ($wlcfg['reglocation'] && $wlcfg['reglocation'] != 'anywhere' && !preg_match("/\s{$wlcfg['reglocation']}\s/si", $ifconfig_str))
		$reg_changing = true;

	if ($reg_changing) {
		/* set regulatory domain */
		if($wlcfg['regdomain'])
			$wlregcmd[] = "regdomain " . escapeshellarg($wlcfg['regdomain']);

		/* set country */
		if($wlcfg['regcountry'])
			$wlregcmd[] = "country " . escapeshellarg($wlcfg['regcountry']);

		/* set location */
		if($wlcfg['reglocation'])
			$wlregcmd[] = escapeshellarg($wlcfg['reglocation']);

		$wlregcmd_args = implode(" ", $wlregcmd);

		/* build a complete list of the wireless clones for this interface */
		$clone_list = array();
		if (does_interface_exist(interface_get_wireless_clone($baseif)))
			$clone_list[] = interface_get_wireless_clone($baseif);
		if (isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
			foreach ($config['wireless']['clone'] as $clone) {
				if ($clone['if'] == $baseif)
					$clone_list[] = $clone['cloneif'];
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

		/* bring the clones back up that were previously up */
		foreach ($clones_up as $clone_if) {
			mwexec("{$ifconfig} " . escapeshellarg($clone_if) . " up");

			/*
			 * Rerun the setup script for the interface if it isn't this interface, the interface
			 * is in infrastructure mode, and WPA is enabled.
			 * This can be removed if wpa_supplicant stops dying when you bring the interface down.
			 */
			if ($clone_if != $if) {
				$friendly_if = convert_real_interface_to_friendly_interface_name($clone_if);
				if ( !empty($friendly_if)
				    && $config['interfaces'][$friendly_if]['wireless']['mode'] == "bss"
				    && isset($config['interfaces'][$friendly_if]['wireless']['wpa']['enable']) ) {
					mwexec("/bin/sh {$g['tmp_path']}/" . escapeshellarg($clone_if) . "_setup.sh");
				}
			}
		}
	}

	/* The mode must be specified in a separate command before ifconfig
	 * will allow the mode and channel at the same time in the next. */
	mwexec("/sbin/ifconfig " . escapeshellarg($if) . " mode " . escapeshellarg($standard));

	/* configure wireless */
	$wlcmd_args = implode(" ", $wlcmd);
	mwexec("/sbin/ifconfig " . escapeshellarg($if) . " " . $wlcmd_args, false);
	unset($wlcmd_args, $wlcmd);


	sleep(1);
	/* execute hostapd and wpa_supplicant if required in shell */
	mwexec("/bin/sh {$g['tmp_path']}/" . escapeshellarg($if) . "_setup.sh");

	return 0;

}

function kill_hostapd($interface) {
	global $g;

	if (isvalidpid("{$g['varrun_path']}/hostapd_{$interface}.pid"))
		return killbypid("{$g['varrun_path']}/hostapd_{$interface}.pid");
}

function kill_wpasupplicant($interface) {
	return "/bin/pkill -f \"wpa_supplicant .*{$interface}\\.conf\"\n";
}

function find_dhclient_process($interface) {
	if ($interface)
		$pid = `/bin/pgrep -axf "dhclient: {$interface}"`;
	else
		$pid = 0;

	return intval($pid);
}

function find_dhcp6c_process($interface) {
	global $g;

	if ($interface && isvalidpid("{$g['varrun_path']}/dhcp6c_{$interface}.pid"))
		$pid = trim(file_get_contents("{$g['varrun_path']}/dhcp6c_{$interface}.pid"), " \n");
	else
		return(false);

	return intval($pid);
}

function interface_vlan_mtu_configured($realhwif, $mtu) {
	global $config;

	if (is_array($config['vlans']) && is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if ($vlan['if'] != $realhwif)
				continue;
			$assignedport = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
			if (!empty($assignedport) && !empty($config['interfaces'][$assignedport]['mtu'])) {
				if (intval($config['interfaces'][$assignedport]['mtu'])> $mtu)
					$mtu = $portmtu;
			}
		}
	}

	return $mtu;
}

function interface_vlan_adapt_mtu($vlanifs, $mtu) {
	global $config;

	if (!is_array($vlanifs))
		return;

	/* All vlans need to use the same mtu value as their parent. */
	foreach ($vlanifs as $vlan) {
		$assignedport = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
		if (!empty($assignedport)) {
			if (!empty($config['interfaces'][$assignedport]['mtu'])) {
				/*
				* XXX: This is really never going to happen just keep the code for safety and readbility.
				* It never happens since interface_vlan_mtu_configured finds the biggest mtu on vlans.
				* Also if it has a lower mtu configured just respect user choice.
				*/
				if (intval($config['interfaces'][$assignedport]['mtu']) > $mtu)
					pfSense_interface_mtu($vlan['vlanif'], $mtu);
			} else {
				if (get_interface_mtu($vlan['vlanif']) != $mtu)
					pfSense_interface_mtu($vlan['vlanif'], $mtu);
			}
		} else if (get_interface_mtu($vlan['vlanif']) != $mtu)
			pfSense_interface_mtu($vlan['vlanif'], $mtu);
	}
}

function interface_configure($interface = "wan", $reloadall = false, $linkupevent = false) {
	global $config, $g;
	global $interface_sn_arr_cache, $interface_ip_arr_cache;
	global $interface_snv6_arr_cache, $interface_ipv6_arr_cache;

	$wancfg = $config['interfaces'][$interface];

	if (!isset($wancfg['enable']))
		return;

	$realif = get_real_interface($interface);
	$realhwif_array = get_parent_interface($interface);
	// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
	$realhwif = $realhwif_array[0];

	if (!does_interface_exist($realif)) {
		$matches = array();
		if (preg_match('/^(.*)_vlan([0-9]+)$/', $realif, $matches))
			if (is_array($config['vlans']['vlan']))
				foreach ($config['vlans']['vlan'] as $vlan)
					if ($vlan['if'] == $matches[1] && $vlan['tag'] == $matches[2]) {
						interface_vlan_configure($vlan);
						break;
					}
		unset($matches);
	}

	/* Disable Accepting router advertisements unless specifically requested */
	if ($g['debug'])
		log_error("Deny router advertisements for interface {$interface}");
	mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 -accept_rtadv");

	if (!$g['booting'] && !(substr($realif, 0, 4) == "ovpn")) {
		/* remove all IPv4 and IPv6 addresses */
		$tmpifaces = pfSense_getall_interface_addresses($realif);
		if (is_array($tmpifaces)) {
			foreach ($tmpifaces as $tmpiface) {
				if (is_ipaddrv6($tmpiface) || is_subnetv6($tmpiface)) {
					if (!is_linklocal($tmpiface))
						mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$tmpiface} delete");
				} else {
					if (is_subnetv4($tmpiface)) {
						$tmpip = explode('/', $tmpiface);
						$tmpip = $tmpip[0];
					} else
						$tmpip = $tmpiface;
					pfSense_interface_deladdress($realif, $tmpip);
				}
			}
		}

		/* only bring down the interface when both v4 and v6 are set to NONE */
		if(empty($wancfg['ipaddr']) && empty($wancfg['ipaddrv6'])) {
			interface_bring_down($interface);
		}
	}

	/* wireless configuration? */
	if (is_array($wancfg['wireless']))
		interface_wireless_configure($realif, $wancfg, $wancfg['wireless']);

	$mac = get_interface_mac($realhwif);
	/*
	 * Don't try to reapply the spoofed MAC if it's already applied.
	 * When ifconfig link is used, it cycles the interface down/up, which triggers
	 * the interface config again, which attempts to spoof the MAC again,
	 * which cycles the link again...
	 */
	if (!empty($wancfg['spoofmac']) && ($wancfg['spoofmac'] != $mac)) {
		mwexec("/sbin/ifconfig " . escapeshellarg($realhwif) .
			" link " . escapeshellarg($wancfg['spoofmac']));

		/*
		 * All vlans need to spoof their parent mac address, too.  see
		 * ticket #1514: http://cvstrac.pfsense.com/tktview?tn=1514,33
		 */
		if (is_array($config['vlans']['vlan'])) {
			foreach ($config['vlans']['vlan'] as $vlan) {
				if ($vlan['if'] == $realhwif)
					mwexec("/sbin/ifconfig " . escapeshellarg($vlan['vlanif']) .
					" link " . escapeshellarg($wancfg['spoofmac']));
			}
		}
	}  else {

		if ($mac == "ff:ff:ff:ff:ff:ff") {
			/*   this is not a valid mac address.  generate a
			 *   temporary mac address so the machine can get online.
			 */
			echo gettext("Generating new MAC address.");
			$random_mac = generate_random_mac_address();
			mwexec("/sbin/ifconfig " . escapeshellarg($realhwif) .
				" link " . escapeshellarg($random_mac));
			$wancfg['spoofmac'] = $random_mac;
			write_config();
			file_notice("MAC Address altered", sprintf(gettext('The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface %1$s has been automatically replaced with %2$s'), $realif, $random_mac), "Interfaces");
		}
	}

	/* media */
	if (!empty($wancfg['media']) || !empty($wancfg['mediaopt'])) {
		$cmd = "/sbin/ifconfig " . escapeshellarg($realhwif);
		if ($wancfg['media'])
			$cmd .= " media " . escapeshellarg($wancfg['media']);
		if ($wancfg['mediaopt'])
			$cmd .= " mediaopt " . escapeshellarg($wancfg['mediaopt']);
		mwexec($cmd);
	}
	$options = pfSense_get_interface_addresses($realhwif);

	/* skip vlans for checksumming and polling */
	if (!stristr($realif, "_vlan") && is_array($options)) {
		$flags_on = 0;
		$flags_off = 0;
		if(isset($config['system']['disablechecksumoffloading'])) {
			if (isset($options['encaps']['txcsum']))
				$flags_off |= IFCAP_TXCSUM;
			if (isset($options['encaps']['rxcsum']))
				$flags_off |= IFCAP_RXCSUM;
		} else {
			if (isset($options['caps']['txcsum']))
				$flags_on |= IFCAP_TXCSUM;
			if (isset($options['caps']['rxcsum']))
				$flags_on |= IFCAP_RXCSUM;
		}

		if(isset($config['system']['disablesegmentationoffloading']))
			$flags_off |= IFCAP_TSO;
		else if (isset($options['caps']['tso']) || isset($options['caps']['tso4']) || isset($options['caps']['tso6']))
			$flags_on |= IFCAP_TSO;

		if(isset($config['system']['disablelargereceiveoffloading']))
			$flags_off |= IFCAP_LRO;
		else if (isset($options['caps']['lro']))
			$flags_on |= IFCAP_LRO;

		/* if the NIC supports polling *AND* it is enabled in the GUI */
		if (!isset($config['system']['polling']))
			$flags_off |= IFCAP_POLLING;
		else if (isset($options['caps']['polling']))
			$flags_on |= IFCAP_POLLING;

		pfSense_interface_capabilities($realhwif, -$flags_off);
		pfSense_interface_capabilities($realhwif, $flags_on);
	}

	/* invalidate interface/ip/sn cache */
	get_interface_arr(true);
	unset($interface_ip_arr_cache[$realif]);
	unset($interface_sn_arr_cache[$realif]);
	unset($interface_ipv6_arr_cache[$realif]);
	unset($interface_snv6_arr_cache[$realif]);

	$tunnelif = substr($realif, 0, 3);
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
			if ($tunnelif == "gre") {
				if (is_array($config['gres']['gre'])) {
					foreach ($config['gres']['gre'] as $gre)
						if ($gre['greif'] == $realif)
							interface_gre_configure($gre);
				}
			} else if ($tunnelif == "gif") {
				if (is_array($config['gifs']['gif'])) {
					foreach ($config['gifs']['gif'] as $gif)
						if($gif['gifif'] == $realif)
							interface_gif_configure($gif);
				}
			} else if (substr($realif, 0, 4) == "ovpn") {
				/* XXX: Should be done anything?! */
			} else if (is_ipaddrv4($wancfg['ipaddr']) && $wancfg['subnet'] <> "") {
				pfSense_interface_setaddress($realif, "{$wancfg['ipaddr']}/{$wancfg['subnet']}");
			}
			break;
	}

	switch ($wancfg['ipaddrv6']) {
		case 'slaac':
		case 'dhcp6':
			interface_dhcpv6_configure($interface, $wancfg);
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
			if (!in_array($tunnelif, array("gif", "gre", "ovp"))) {
				if (is_ipaddrv6($wancfg['ipaddrv6']) && $wancfg['subnetv6'] <> "") {
					//pfSense_interface_setaddress($realif, "{$wancfg['ipaddrv6']}/{$wancfg['subnetv6']}");
					// FIXME: Add IPv6 Support to the pfSense module
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " inet6 {$wancfg['ipaddrv6']} prefixlen " . escapeshellarg($wancfg['subnetv6']));
				}
			}
			break;
	}

	if (!empty($wancfg['mtu'])) {
		if (stristr($realif, "_vlan")) {
			$assignedparent = convert_real_interface_to_friendly_interface_name($realhwif);
			if (!empty($assignedparent) && !empty($config['interfaces'][$assignedparent]['mtu']))
				$parentmtu = $config['interfaces'][$assignedparent]['mtu'];
			else 
				$parentmtu = interface_vlan_mtu_configured($realhwif, $wancfg['mtu']);

			if ($wancfg['mtu'] > $parentmtu) {
				if (get_interface_mtu($realhwif) != $wancfg['mtu'])
					pfSense_interface_mtu($realhwif, $wancfg['mtu']);

				/* All vlans need to use the same mtu value as their parent. */
				interface_vlan_adapt_mtu(link_interface_to_vlans($realhwif), $wancfg['mtu']);
			} else
				pfSense_interface_mtu($realif, $wancfg['mtu']);
		} else {
			if ($wancfg['mtu'] != get_interface_mtu($realif))
				pfSense_interface_mtu($realif, $wancfg['mtu']);

			/* This case is needed when the parent of vlans is being configured */
			interface_vlan_adapt_mtu(link_interface_to_vlans($realif), $wancfg['mtu']);
		}
		/* XXX: What about gre/gif/lagg/.. ? */
	}

	if (does_interface_exist($wancfg['if']))
		interfaces_bring_up($wancfg['if']);

	interface_netgraph_needed($interface);

	if (!$g['booting']) {
		link_interface_to_vips($interface, "update");

		unset($gre);
		$gre = link_interface_to_gre($interface);
		if (!empty($gre))
			array_walk($gre, 'interface_gre_configure');

		unset($gif);
		$gif = link_interface_to_gif($interface);
		if (!empty($gif))
			array_walk($gif, 'interface_gif_configure');

		if ($linkupevent == false || substr($realif, 0, 4) == "ovpn") {
			unset($bridgetmp);
			$bridgetmp = link_interface_to_bridge($interface);
			if (!empty($bridgetmp))
				interface_bridge_add_member($bridgetmp, $realif);
		}

		$grouptmp = link_interface_to_group($interface);
		if (!empty($grouptmp))
			array_walk($grouptmp, 'interface_group_add_member');

		if ($interface == "lan")
			/* make new hosts file */
			system_hosts_generate();

		if ($reloadall == true) {

			/* reconfigure static routes (kernel may have deleted them) */
			system_routing_configure($interface);

			/* reload ipsec tunnels */
			vpn_ipsec_configure();

			/* restart dnsmasq */
			services_dnsmasq_configure();

			/* update dyndns */
			send_event("service reload dyndns {$interface}");

			/* reload captive portal */
			captiveportal_init_rules();
		}
	}

	interfaces_staticarp_configure($interface);
	return 0;
}

function interface_track6_configure($interface = "lan", $wancfg, $linkupevent = false) {
	global $config, $g;

	if (!is_array($wancfg))
		return;

	if (!isset($wancfg['enable']))
		return;

	/* If the interface is not configured via another, exit */
	if (empty($wancfg['track6-interface']))
		return;

	/* always configure a link-local of fe80::1:1 on the track6 interfaces */
	$realif = get_real_interface($interface);
	$linklocal = find_interface_ipv6_ll($realif);
	if (!empty($linklocal))
		mwexec("/sbin/ifconfig {$realif} inet6 {$linklocal} delete");
	/* XXX: This might break for good on a carp installation using link-local as network ips */
	/* XXX: Probably should remove? */
	mwexec("/sbin/ifconfig {$realif} inet6 fe80::1:1%{$realif}");

	$trackcfg = $config['interfaces'][$wancfg['track6-interface']];
	if (!isset($trackcfg['enable'])) {
		log_error("Interface {$interface} tracking non-existant interface {$wancfg['track6-interface']}");
		return;
	}

	switch($trackcfg['ipaddrv6']) {
	case "6to4":
		if ($g['debug'])
			log_error("Interface {$interface} configured via {$wancfg['track6-interface']}  type {$type}");
		interface_track6_6to4_configure($interface, $wancfg);
		break;
	case "6rd":
		if ($g['debug'])
			log_error("Interface {$interface} configured via {$wancfg['track6-interface']}  type {$type}");
		interface_track6_6rd_configure($interface, $wancfg);
		break;
	case "dhcp6":
		if ($linkupevent == true) {
			/* 
			 * NOTE: Usually come here from rc.linkup calling so just call directly intead of generating event
			 *      Instead of disrupting all other v4 configuration just restart DHCPv6 client for now
			 *
			 * XXX: Probably DHCPv6 client should handle this autmagically itself?
			 */
			$parentrealif = get_real_interface($wancfg['track6-interface']);
			$pidv6 = find_dhcp6c_process($parentrealif);
			if($pidv6)
				posix_kill($pidv6, SIGHUP);
		}
		break;
	}

	if (!$g['booting'] && $linkupevent == false) {
		if (!function_exists('services_dhcpd_configure'))
			require_once("services.inc");

		services_dhcpd_configure("inet6");
	}

	return 0;
}

function interface_track6_6rd_configure($interface = "lan", $lancfg) {
	global $config, $g;
	global $interface_ipv6_arr_cache;
	global $interface_snv6_arr_cache;

	if (!is_array($lancfg))
		return;

	/* If the interface is not configured via another, exit */
	if (empty($lancfg['track6-interface']))
		return;

	$wancfg = $config['interfaces'][$lancfg['track6-interface']];
	if (empty($wancfg)) {
		log_error("Interface {$interface} tracking non-existant interface {$lancfg['track6-interface']}");
		return;
	}

	$ip4address = get_interface_ip($lancfg['track6-interface']);
	if (!is_ipaddrv4($ip4address)) { /* XXX: This should not be needed by 6rd || (is_private_ip($ip4address))) { */
		log_error("The interface IPv4 '{$ip4address}' address on interface '{$lancfg['track6-interface']}' is not public, not configuring 6RD tunnel");
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
	$rd6lanbin = str_pad($rd6lanbin, 128, "0", STR_PAD_RIGHT);;

	/* convert the 128 bits for the lan address back into a valid IPv6 address */
	$rd6lan = convert_128bit_to_ipv6($rd6lanbin) ."1";

	$lanif = get_real_interface($interface);
	$oip = find_interface_ipv6($lanif);
	if (is_ipaddrv6($oip))
		mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
	unset($interface_ipv6_arr_cache[$lanif]);
	unset($interface_snv6_arr_cache[$lanif]);
	log_error("rd6 {$interface} with ipv6 address {$rd6lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
	mwexec("/sbin/ifconfig {$lanif} inet6 {$rd6lan} prefixlen 64");

	return 0;
}

function interface_track6_6to4_configure($interface = "lan", $lancfg) {
	global $config, $g;
	global $interface_ipv6_arr_cache;
	global $interface_snv6_arr_cache;

	if (!is_array($lancfg))
		return;

	/* If the interface is not configured via another, exit */
	if (empty($lancfg['track6-interface']))
		return;

	$wancfg = $config['interfaces'][$lancfg['track6-interface']];
	if (empty($wancfg)) {
		log_error("Interface {$interface} tracking non-existant interface {$lancfg['track6-interface']}");
		return;
	}

	$ip4address = get_interface_ip($lancfg['track6-interface']);
	if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
		log_error("The interface IPv4 '{$ip4address}' address on interface '{$lancfg['track6-interface']}' is not public, not configuring 6RD tunnel");
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
	$sixto4lanbin = str_pad($sixto4lanbin, 128, "0", STR_PAD_RIGHT);;

	/* convert the 128 bits for the lan address back into a valid IPv6 address */
	$sixto4lan = convert_128bit_to_ipv6($sixto4lanbin) ."1";

	$lanif = get_real_interface($interface);
	$oip = find_interface_ipv6($lanif);
	if (is_ipaddrv6($oip))
		mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
	unset($interface_ipv6_arr_cache[$lanif]);
	unset($interface_snv6_arr_cache[$lanif]);
	log_error("sixto4 {$interface} with ipv6 address {$sixto4lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
	mwexec("/sbin/ifconfig {$lanif} inet6 {$sixto4lan} prefixlen 64");

	return 0;
}

function interface_6rd_configure($interface = "wan", $wancfg) {
	global $config, $g;

	/* because this is a tunnel interface we can only function
	 *	with a public IPv4 address on the interface */

	if (!is_array($wancfg))
		return;

	$wanif = get_real_interface($interface);
	$ip4address = find_interface_ip($wanif);
	if ((!is_ipaddrv4($ip4address)) || (is_private_ip($ip4address))) {
		log_error("The interface IPv4 '{$ip4address}' address on interface '{$wanif}' is not public, not configuring 6RD tunnel");
		return false;
	}
	$hexwanv4 = return_hex_ipv4($ip4address);

	if (!is_numeric($wancfg['prefix-6rd-v4plen']))
		$wancfg['prefix-6rd-v4plen'] = 0;

	/* create the long prefix notation for math, save the prefix length */
	$rd6prefix = explode("/", $wancfg['prefix-6rd']);
	$rd6prefixlen = $rd6prefix[1];
	$rd6prefix = Net_IPv6::uncompress($rd6prefix[0]);

	/* binary presentation of the prefix for all 128 bits. */
	$rd6prefixbin = convert_ipv6_to_128bit($rd6prefix);

	/* just save the left prefix length bits */
	$rd6prefixbin = substr($rd6prefixbin, 0, $rd6prefixlen);
	/* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
	$rd6prefixbin .= substr(sprintf("%032b", hexdec($hexwanv4)), $wancfg['prefix-6rd-v4plen'], 32);
	/* fill out the rest with 0's */
	$rd6prefixbin = str_pad($rd6prefixbin, 128, "0", STR_PAD_RIGHT);;

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$rd6prefix = convert_128bit_to_ipv6($rd6prefixbin);

	$rd6brgw = "{$rd6prefix}{$wancfg['gateway-6rd']}";

	/* XXX: need to extend to support variable prefix size for v4 */
	if (!is_module_loaded("if_stf"))
		mwexec("/sbin/kldload if_stf.ko");
	$stfiface = "{$interface}_stf";
	if (does_interface_exist($stfiface))
		pfSense_interface_destroy($stfiface);
	$tmpstfiface = pfSense_interface_create("stf");
	pfSense_interface_rename($tmpstfiface, $stfiface);
	pfSense_interface_flags($stfiface, IFF_LINK2);
	mwexec("/sbin/ifconfig {$stfiface} inet6 {$rd6prefix}/{$rd6prefixlen}");
	mwexec("/sbin/ifconfig {$stfiface} stfv4br " . escapeshellarg($wancfg['gateway-6rd']));
	if ($wancfg['prefix-6rd-v4plen'] > 0 && $wancfg['prefix-6rd-v4plen'] < 32)
		mwexec("/sbin/ifconfig {$stfiface} stfv4net {$ip4address}/{$wancfg['prefix-6rd-v4plen']}");
	if ($g['debug'])
		log_error("Created 6rd interface {$stfiface} {$rd6prefix}/{$rd6prefixlen}");

	/* write out a default router file */
	file_put_contents("{$g['tmp_path']}/{$wanif}_routerv6", "{$rd6brgw}\n");
	file_put_contents("{$g['tmp_path']}/{$wanif}_defaultgwv6", "{$rd6brgw}\n");

	$ip4gateway = get_interface_gateway($interface);
	if (is_ipaddrv4($ip4gateway))
		mwexec("/sbin/route change -host " . escapeshellarg($wancfg['gateway-6rd']) . " {$ip4gateway}");

	/* configure dependent interfaces */
	if (!$g['booting'])
		link_interface_to_track6($interface, "update");

	return 0;
}

function interface_6to4_configure($interface = "wan", $wancfg){
	global $config, $g;

	/* because this is a tunnel interface we can only function
	 *	with a public IPv4 address on the interface */

	if (!is_array($wancfg))
		return;

	$wanif = get_real_interface($interface);
	$ip4address = find_interface_ip($wanif);
	if((!is_ipaddrv4($ip4address)) || (is_private_ip($ip4address))) {
		log_error("The interface IPv4 '{$ip4address}' address on interface '{$wanif}' is not public, not configuring 6RD tunnel");
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
	foreach($ip4arr as $octet)
		$hexwanv4 .= sprintf("%02x", $octet);

	/* we need the hex form of the broker IPv4 address */
	$ip4arr = explode(".", "192.88.99.1");
	$hexbrv4 = "";
	foreach($ip4arr as $octet)
		$hexbrv4 .= sprintf("%02x", $octet);

	/* binary presentation of the prefix for all 128 bits. */
	$stfprefixbin = "";
	foreach($stfarr as $element) {
		$stfprefixbin .= sprintf("%016b", hexdec($element));
	}
	/* just save the left prefix length bits */
	$stfprefixstartbin = substr($stfprefixbin, 0, $stfprefixlen);

	/* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
	$stfbrokerbin = substr(sprintf("%032b", hexdec($hexbrv4)), $v4prefixlen, 32);
	$stfbrokerbin = str_pad($stfprefixstartbin . $stfbrokerbin, 128, "0", STR_PAD_RIGHT);;

	/* for the local subnet too. */
	$stflanbin = substr(sprintf("%032b", hexdec($hexwanv4)), $v4prefixlen, 32);
	$stflanbin = str_pad($stfprefixstartbin . $stflanbin, 128, "0", STR_PAD_RIGHT);;

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$stfbrarr = array();
	$stfbrbinarr = array();
	$stfbrbinarr = str_split($stfbrokerbin, 16);
	foreach($stfbrbinarr as $bin)
		$stfbrarr[] = dechex(bindec($bin));
	$stfbrgw = Net_IPv6::compress(implode(":", $stfbrarr));

	/* convert the 128 bits for the broker address back into a valid IPv6 address */
	$stflanarr = array();
	$stflanbinarr = array();
	$stflanbinarr = str_split($stflanbin, 16);
	foreach($stflanbinarr as $bin)
		$stflanarr[] = dechex(bindec($bin));
	$stflanpr = Net_IPv6::compress(implode(":", $stflanarr));
	$stflanarr[7] = 1;
	$stflan = Net_IPv6::compress(implode(":", $stflanarr));

	/* setup the stf interface */
	if (!is_module_loaded("if_stf"))
		mwexec("/sbin/kldload if_stf.ko");
	$stfiface = "{$interface}_stf";
	if (does_interface_exist($stfiface))
		pfSense_interface_destroy($stfiface);
	$tmpstfiface = pfSense_interface_create("stf");
	pfSense_interface_rename($tmpstfiface, $stfiface);
	pfSense_interface_flags($stfiface, IFF_LINK2);
	mwexec("/sbin/ifconfig {$stfiface} inet6 {$stflanpr} prefixlen 16");

	if ($g['debug'])
		log_error("Set IPv6 address inet6 {$stflanpr} prefixlen 16 for {$stfiface}, route {$stfbrgw}");

	/* write out a default router file */
	file_put_contents("{$g['tmp_path']}/{$wanif}_routerv6", "{$stfbrgw}");
	file_put_contents("{$g['tmp_path']}/{$wanif}_defaultgwv6", "{$stfbrgw}");

	$ip4gateway = get_interface_gateway($interface);
	if (is_ipaddrv4($ip4gateway))
		mwexec("/sbin/route change -host 192.88.99.1 {$ip4gateway}");

	if (!$g['booting'])
		link_interface_to_track6($interface, "update");

	return 0;
}

function interface_dhcpv6_configure($interface = "wan", $wancfg) {
	global $config, $g;

	if (!is_array($wancfg))
		return;

	$wanif = get_real_interface($interface, "inet6");
	$dhcp6cconf = "";
	$dhcp6cconf .= "interface {$wanif} {\n";

	/* for SLAAC interfaces we do fire off a dhcp6 client for just our name servers */
	if($wancfg['ipaddrv6'] == "slaac") {
		$dhcp6cconf .= "	information-only;\n";
		$dhcp6cconf .= "	request domain-name-servers;\n";
		$dhcp6cconf .= "	request domain-name;\n";
		$dhcp6cconf .= "	script \"{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";
		$dhcp6cconf .= "};\n";
	} else {
		/* skip address request if this is set */
		if(!isset($wancfg['dhcp6prefixonly']))
			$dhcp6cconf .= "        send ia-na 0;   # request stateful address\n";
		if(is_numeric($wancfg['dhcp6-ia-pd-len']))
			$dhcp6cconf .= "	send ia-pd 0;	# request prefix delegation\n";

		$dhcp6cconf .= "\trequest domain-name-servers;\n";
		$dhcp6cconf .= "\trequest domain-name;\n";
		$dhcp6cconf .= "\tscript \"{$g['varetc_path']}/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";

		$dhcp6cconf .= "};\n";

		if(!isset($wancfg['dhcp6prefixonly']))
			$dhcp6cconf .= "id-assoc na 0 { };\n";

		if(is_numeric($wancfg['dhcp6-ia-pd-len'])) {
			/* Setup the prefix delegation */
			$dhcp6cconf .= "id-assoc pd 0 {\n";
			$preflen = 64 - $wancfg['dhcp6-ia-pd-len'];
			if (isset($wancfg['dhcp6-ia-pd-send-hint']))
				$dhcp6cconf .= "	prefix ::/{$preflen} infinity;\n";
			$iflist = link_interface_to_track6($interface);
			foreach ($iflist as $friendly => $ifcfg) {
				if (is_numeric($ifcfg['track6-prefix-id'])) {
					if ($g['debug'])
						log_error("setting up $ifdescr - {$ifcfg['track6-prefix-id']}");
					$realif = get_real_interface($friendly);
					$dhcp6cconf .= "	prefix-interface {$realif} {\n";
					$dhcp6cconf .= "		sla-id {$ifcfg['track6-prefix-id']};\n";
					$dhcp6cconf .= "		sla-len {$wancfg['dhcp6-ia-pd-len']};\n";
					$dhcp6cconf .= "	};\n";
				}
			}
			unset($preflen, $iflist, $ifcfg);
			$dhcp6cconf .= "};\n";
		}
	}
	/* wide-dhcp6c works for now. */
	if (!@file_put_contents("{$g['varetc_path']}/dhcp6c_{$interface}.conf", $dhcp6cconf)) {
		printf("Error: cannot open dhcp6c_{$interface}.conf in interface_dhcpv6_configure() for writing.\n");
		unset($dhcp6cconf);
		return 1;
	}
	unset($dhcp6cconf);

	$dhcp6cscript = "#!/bin/sh\n";
	$dhcp6cscript .= "# This shell script launches /etc/rc.newwanipv6 with a interface argument.\n";
	$dhcp6cscript .= "/etc/rc.newwanipv6 {$wanif} \n";
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
	$rtsoldscript .= "if [ -f {$g['varrun_path']}/dhcp6c_{$wanif}.pid ]; then\n";
	$rtsoldscript .= "\t/bin/pkill -F {$g['varrun_path']}/dhcp6c_{$wanif}.pid\n";
	$rtsoldscript .= "\t/bin/sleep 1\n";
	$rtsoldscript .= "fi\n";
	$rtsoldscript .= "/usr/local/sbin/dhcp6c -d -c {$g['varetc_path']}/dhcp6c_{$interface}.conf -p {$g['varrun_path']}/dhcp6c_{$wanif}.pid {$wanif}\n";
	$rtsoldscript .= "/usr/bin/logger -t rtsold \"Starting dhcp6 client for interface {$interface}({$wanif})\"\n";
	/* Add wide-dhcp6c shell script here. Because we can not pass a argument to it. */
	if (!@file_put_contents("{$g['varetc_path']}/rtsold_{$wanif}_script.sh", $rtsoldscript)) {
		printf("Error: cannot open rtsold_{$interface}_script.sh in interface_dhcpv6_configure() for writing.\n");
		unset($rtsoldscript);
		return 1;
	}
	unset($rtsoldscript);
	@chmod("{$g['varetc_path']}/rtsold_{$wanif}_script.sh", 0755);

	/* accept router advertisements for this interface */
	mwexec("/sbin/sysctl -w net.inet6.ip6.accept_rtadv=1");
	log_error("Accept router advertisements on interface {$wanif} ");
	mwexec("/sbin/ifconfig {$wanif} inet6 accept_rtadv");

	/* fire up rtsold for IPv6 RAs first, this backgrounds immediately. It will call dhcp6c */
	if (isvalidpid("{$g['varrun_path']}/rtsold_{$wanif}.pid")) {
		killbypid("{$g['varrun_path']}/rtsold_{$wanif}.pid");
		sleep(2);
	}
	mwexec("/usr/sbin/rtsold -1 -p {$g['varrun_path']}/rtsold_{$wanif}.pid -O {$g['varetc_path']}/rtsold_{$wanif}_script.sh {$wanif}");

	/* NOTE: will be called from rtsold invoked script
	 * link_interface_to_track6($interface, "update");
	 */

	return 0;
}

function interface_dhcp_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];
	$wanif = $wancfg['if'];
	if (empty($wancfg))
		$wancfg = array();

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$interface}.conf", "w");
	if (!$fd) {
		printf(printf(gettext("Error: cannot open dhclient_%s.conf in interface_dhcp_configure() for writing.%s"), $interface, "\n"));
		return 1;
	}

	if ($wancfg['dhcphostname']) {
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$wancfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$wancfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

	$wanif = get_real_interface($interface);
	if (empty($wanif)) {
		log_error(sprintf(gettext("Invalid interface \"%s\" in interface_dhcp_configure()"), $interface));
		return 0;
	}
	$dhclientconf = "";

	$dhclientconf .= <<<EOD
interface "{$wanif}" {
timeout 60;
retry 15;
select-timeout 0;
initial-interval 1;
	{$dhclientconf_hostname}
	script "/sbin/dhclient-script";
EOD;

if (is_ipaddrv4($wancfg['dhcprejectfrom'])) {
	$dhclientconf .= <<<EOD

	reject {$wancfg['dhcprejectfrom']};
EOD;
}
	$dhclientconf .= <<<EOD

}

EOD;

if(is_ipaddr($wancfg['alias-address'])) {
	$subnetmask = gen_subnet_mask($wancfg['alias-subnet']);
	$dhclientconf .= <<<EOD
alias {
	interface  "{$wanif}";
	fixed-address {$wancfg['alias-address']};
	option subnet-mask {$subnetmask};
}

EOD;
}
	fwrite($fd, $dhclientconf);
	fclose($fd);

	/* bring wan interface up before starting dhclient */
	if($wanif)
		interfaces_bring_up($wanif);
	else
		log_error(printf(gettext("Could not bring up %s interface in interface_dhcp_configure()"), $wanif));

	/* fire up dhclient */
	mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$wanif} > {$g['tmp_path']}/{$wanif}_output 2> {$g['tmp_path']}/{$wanif}_error_output");

	return 0;
}

function interfaces_group_setup() {
	global $config;

	if (!is_array($config['ifgroups']['ifgroupentry']))
		return;

	foreach ($config['ifgroups']['ifgroupentry'] as $groupar)
		interface_group_setup($groupar);

	return;
}

function interface_group_setup(&$groupname /* The parameter is an array */) {
	global $config;

	if (!is_array($groupname))
		return;
	$members = explode(" ", $groupname['members']);
	foreach($members as $ifs) {
		$realif = get_real_interface($ifs);
		if ($realif)
			mwexec("/sbin/ifconfig {$realif} group {$groupname['ifname']}");
	}

	return;
}

function is_interface_group($if) {
	global $config;

	if (is_array($config['ifgroups']['ifgroupentry']))
		foreach ($config['ifgroups']['ifgroupentry'] as $groupentry) {
			if ($groupentry['ifname'] === $if)
				return true;
		}

	return false;
}

function interface_group_add_member($interface, $groupname) {
	$interface = get_real_interface($interface);
	mwexec("/sbin/ifconfig {$interface} group " . escapeshellarg($groupname), true);
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
function convert_real_interface_to_friendly_interface_name($interface = "wan") {
	global $config;

	if (stristr($interface, "_vip")) {
		foreach ($config['virtualip']['vip'] as $counter => $vip) {
			if ($vip['mode'] == "carp")  {
				if ($interface == "{$vip['interface']}_vip{$vip['vhid']}")
					return $vip['interface'];
			}
		}
	}

	/* XXX: For speed reasons reference directly the interface array */
	$ifdescrs = &$config['interfaces'];
	//$ifdescrs = get_configured_interface_list(false, true);

	foreach ($ifdescrs as $if => $ifname) {
		if ($if == $interface || $ifname['if'] == $interface)
			return $if;

		if (get_real_interface($if) == $interface)
			return $if;

		$int = get_parent_interface($if, true);
		if (is_array($int)) {
			foreach ($int as $iface) {
				if ($iface == $interface)
					return $if;
			}
		}
	}

	return NULL;
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
	case "enc0":
	case "ipsec":
		$ifdesc = "IPsec";
		break;
	default:
		if (isset($config['interfaces'][$interface])) {
			if (empty($config['interfaces'][$interface]['descr']))
				$ifdesc = strtoupper($interface);
			else
				$ifdesc = strtoupper($config['interfaces'][$interface]['descr']);
			break;
		} else if (stristr($interface, "_vip")) {
			if (is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $counter => $vip) {
					if ($vip['mode'] == "carp")  {
						if ($interface == "{$vip['interface']}_vip{$vip['vhid']}")
							return "{$vip['subnet']} - {$vip['descr']}";
					}
				}
			}
		} else {
			/* if list */
			$ifdescrs = get_configured_interface_with_descr(false, true);
			foreach ($ifdescrs as $if => $ifname) {
				if ($if == $interface || $ifname == $interface)
					return $ifname;
			}
		}
		break;
	}

	return $ifdesc;
}

function convert_real_interface_to_friendly_descr($interface) {
	global $config;

	$ifdesc = convert_real_interface_to_friendly_interface_name("{$interface}");

	if ($ifdesc) {
		$iflist = get_configured_interface_with_descr(false, true);
		return $iflist[$ifdesc];
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
	if ($realif == NULL)
		return $parents;

	// If we got a real interface, find it's friendly assigned name
	if ($interface == $realif && $avoidrecurse == false)
		$interface = convert_real_interface_to_friendly_interface_name($interface);

	if (!empty($interface) && isset($config['interfaces'][$interface])) {
		$ifcfg = $config['interfaces'][$interface];
		switch ($ifcfg['ipaddr']) {
			case "ppp":
			case "pppoe":
			case "pptp":
			case "l2tp":
				if (empty($parents))
					if (is_array($config['ppps']['ppp']))
						foreach ($config['ppps']['ppp'] as $pppidx => $ppp) {
							if ($ifcfg['if'] == $ppp['if']) {
								$ports = explode(',', $ppp['ports']);
								foreach ($ports as $pid => $parent_if)
									$parents[$pid] = get_real_interface($parent_if);
								break;
							}
						}
				break;
			case "dhcp":
			case "static":
			default:
				// Handle _vlans
				if (stristr($realif,"_vlan"))
					if (is_array($config['vlans']['vlan']))
						foreach ($config['vlans']['vlan'] as $vlanidx => $vlan)
							if ($ifcfg['if'] == $vlan['vlanif']){
								$parents[0] = $vlan['if'];
								break;
							}
				break;
		}
	}

	if (empty($parents))
		$parents[0] = $realif;

	return $parents;
}

function interface_is_wireless_clone($wlif) {
	if(!stristr($wlif, "_wlan")) {
		return false;
	} else {
		return true;
	}
}

function interface_get_wireless_base($wlif) {
	if(!stristr($wlif, "_wlan")) {
		return $wlif;
	} else {
		return substr($wlif, 0, stripos($wlif, "_wlan"));
	}
}

function interface_get_wireless_clone($wlif) {
	if(!stristr($wlif, "_wlan")) {
		return $wlif . "_wlan0";
	} else {
		return $wlif;
	}
}

function get_real_interface($interface = "wan", $family = "all", $realv6iface = false, $flush = true) {
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
	case "ipsec":
	case "enc0":
		$wanif = "enc0";
		break;
	case "ppp":
		$wanif = "ppp";
		break;
	default:
		// If a real interface was alread passed simply
		// pass the real interface back.  This encourages
		// the usage of this function in more cases so that
		// we can combine logic for more flexibility.
		if(does_interface_exist($interface, $flush)) {
			$wanif = $interface;
			break;
		}

		if (empty($config['interfaces'][$interface]))
			break;

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
				if( is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if']))
					$wanif = interface_get_wireless_clone($cfg['if']);
				else
					$wanif = $cfg['if'];
				break;
			default:
				switch ($cfg['ipaddr']) {
				case 'pppoe':
				case 'ppp':
				case 'l2tp':
				case 'pptp':
					if (isset($cfg['dhcp6usev4iface']) && $realv6iface === false)
						$wanif = $cfg['if'];
					else {
						$parents = get_parent_interface($interface);
						if (!empty($parents[0]))
							$wanif = $parents[0];
						else
							$wanif = $cfg['if'];
					}
					break;
				default:
					if( is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if']))
						$wanif = interface_get_wireless_clone($cfg['if']);
					else
						$wanif = $cfg['if'];
					break;
				}
				break;
			}
		} else {
			// Wireless cloned NIC support (FreeBSD 8+)
			// interface name format: $parentnic_wlanparentnic#
			// example: ath0_wlan0
			if( is_array($cfg['wireless']) || preg_match($g['wireless_regex'], $cfg['if']))
				$wanif = interface_get_wireless_clone($cfg['if']);
			else
				$wanif = $cfg['if'];
		}
		break;
	}

	return $wanif;
}

/* Guess the physical interface by providing a IP address */
function guess_interface_from_ip($ipaddress) {
	if(! is_ipaddr($ipaddress)) {
		return false;
	}
	if(is_ipaddrv4($ipaddress)) {
		/* create a route table we can search */
		exec("netstat -rnWf inet", $output, $ret);
		foreach($output as $line) {
			if(preg_match("/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\/[0-9]+[ ]+link[#]/", $line)) {
				$fields = preg_split("/[ ]+/", $line);
				if(ip_in_subnet($ipaddress, $fields[0])) {
					return $fields[6];
				}
			}
		}
	}
	/* FIXME: This works from cursory testing, regexp might need fine tuning */
	if(is_ipaddrv6($ipaddress)) {
		/* create a route table we can search */
		exec("netstat -rnWf inet6", $output, $ret);
		foreach($output as $line) {
			if(preg_match("/[0-9a-f]+[:]+[0-9a-f]+[:]+[\/][0-9]+/", $line)) {
				$fields = preg_split("/[ ]+/", $line);
				if(ip_in_subnet($ipaddress, $fields[0])) {
					return $fields[6];
				}
			}
		}
	}
	$ret = exec_command("/sbin/route -n get {$ipaddress} | /usr/bin/awk '/interface/ { print \$2; };'");
	if(empty($ret)) {
		return false;
	}
	return $ret;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 *   (or if $bits is specified, where an IP within the subnet is defined)
 */
function find_ip_interface($ip, $bits = null) {
	if (!is_ipaddr($ip))
		return false;

	$isv6ip = is_ipaddrv6($ip);

	/* if list */
	$ifdescrs = get_configured_interface_list();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		$ifip = ($isv6ip) ? get_interface_ipv6($ifname) : get_interface_ip($ifname);
		if (is_null($ifip))
			continue;
		if (is_null($bits)) {
			if ($ip == $ifip) {
				$int = get_real_interface($ifname);
				return $int;
			}
		}
		else {
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
	if (!is_ipaddr($ip))
		return false;

	$isv6ip = is_ipaddrv6($ip);

	foreach ($config['virtualip']['vip'] as $vip) {
		if ($vip['mode'] === "ipalias") {
			if (is_ipaddrv6($vip['subnet']) != $isv6ip)
				continue;
			if (is_null($bits)) {
				if (ip_in_subnet($ip, $vip['subnet'] . "/" . $vip['subnet_bits'])) {
					return $vip;
				}
			}
			else {
				if (($isv6ip && check_subnetsv6_overlap($ip, $bits, $vip['subnet'], $vip['subnet_bits']))
					|| (!$isv6ip && check_subnets_overlap($ip, $bits, $vip['subnet'], $vip['subnet_bits']))) {
					return $vip;
				}
			}
		}
	}
	return false;
}

/*
 *   find_number_of_created_carp_interfaces: return the number of carp interfaces
 */
function find_number_of_created_carp_interfaces() {
	return `/sbin/ifconfig | grep "carp:" | wc -l`;
}

function get_all_carp_interfaces() {
	$ints = str_replace("\n", " ", `ifconfig | grep "carp:" -B2 | grep ": flag" | cut -d: -f1`);
	$ints = explode(" ", $ints);
	return $ints;
}

/*
 * find_carp_interface($ip): return the carp interface where an ip is defined
 */
function find_carp_interface($ip) {
	global $config;
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "carp") {
				if(is_ipaddrv4($ip)) {
					$carp_ip = get_interface_ip($vip['interface']);
				}
				if(is_ipaddrv6($ip)) {
					$carp_ip = get_interface_ipv6($vip['interface']);
				}
				exec("/sbin/ifconfig", $output, $return);
				foreach($output as $line) {
					$elements = preg_split("/[ ]+/i", $line);
					if(strstr($elements[0], "vip"))
						$curif = str_replace(":", "", $elements[0]);
					if(stristr($line, $ip)) {
						$if = $curif;
						continue;
					}
				}

				if ($if)
					return $if;
			}
		}
	}
}

function link_carp_interface_to_parent($interface) {
	global $config;

	if (empty($interface))
		return;

	$carp_ip = get_interface_ip($interface);
	$carp_ipv6 = get_interface_ipv6($interface);

	if((!is_ipaddrv4($carp_ip)) && (!is_ipaddrv6($carp_ipv6)))
		return;

	/* if list */
	$ifdescrs = get_configured_interface_list();
	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* check IPv4 */
		if(is_ipaddrv4($carp_ip)) {
			$interfaceip = get_interface_ip($ifname);
			$subnet_bits = get_interface_subnet($ifname);
			$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
			if(ip_in_subnet($carp_ip, "{$subnet_ip}/{$subnet_bits}"))
				return $ifname;
		}
		/* Check IPv6 */
		if(is_ipaddrv6($carp_ipv6)) {
			$interfaceipv6 = get_interface_ipv6($ifname);
			$prefixlen = get_interface_subnetv6($ifname);
			if(ip_in_subnet($carp_ipv6, "{$interfaceipv6}/{$prefixlen}"))
				return $ifname;
		}
	}
	return "";
}


/****f* interfaces/link_ip_to_carp_interface
 * NAME
 *   link_ip_to_carp_interface - Find where a CARP interface links to.
 * INPUTS
 *   $ip
 * RESULT
 *   $carp_ints
 ******/
function link_ip_to_carp_interface($ip) {
	global $config;

	if (!is_ipaddr($ip))
		return;

	$carp_ints = "";
	if (is_array($config['virtualip']['vip'])) {
		$first = 0;
		$carp_int = array();
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "carp") {
				$carp_ip = $vip['subnet'];
				$carp_sn = $vip['subnet_bits'];
				$carp_nw = gen_subnet($carp_ip, $carp_sn);
				if (ip_in_subnet($ip, "{$carp_nw}/{$carp_sn}")) {
					$carp_int[] = "{$vip['interface']}_vip{$vip['vhid']}";
				}
			}
		}
		if (!empty($carp_int))
			$carp_ints = implode(" ", array_unique($carp_int));
	}

	return $carp_ints;
}

function link_interface_to_track6($int, $action = "") {
	global $config;

	if (empty($int))
		return;

	if (is_array($config['interfaces'])) {
		$list = array();
		foreach ($config['interfaces'] as $ifname => $ifcfg) {
			if (!isset($ifcfg['enable']))
				continue;
			if (!empty($ifcfg['ipaddrv6']) && $ifcfg['track6-interface'] == $int) {
				if ($action == "update")
					interface_track6_configure($ifname, $ifcfg);
				else if ($action == "")
					$list[$ifname] = $ifcfg;
			}
		}
		return $list;
	}
}

function link_interface_to_vlans($int, $action = "") {
	global $config;

	if (empty($int))
		return;

	if (is_array($config['vlans']['vlan'])) {
		$ifaces = array();
		foreach ($config['vlans']['vlan'] as $vlan) {
			if ($int == $vlan['if']) {
				if ($action == "update") {
					interfaces_bring_up($int);
				} else if ($action == "")
					$ifaces[$vlan['tag']] = $vlan;
			}
		}
		if (!empty($ifaces))
			return $ifaces;
	}
}

function link_interface_to_vips($int, $action = "") {
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		$result = array();
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($int == $vip['interface']) {
				if ($action == "update")
					interfaces_vips_configure($int);
				else
					$result[] = $vip;
			}
		}
		return $result;
	}
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

	if (is_array($config['bridges']['bridged'])) {
		foreach ($config['bridges']['bridged'] as $bridge) {
			if (in_array($int, explode(',', $bridge['members'])))
				return "{$bridge['bridgeif']}";
		}
	}
}

function link_interface_to_group($int) {
	global $config;

	$result = array();

	if (is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $group) {
			if (in_array($int, explode(" ", $group['members'])))
				$result[$group['ifname']] = $int;
		}
	}

	return $result;
}

function link_interface_to_gre($interface) {
	global $config;

	$result = array();

	if (is_array($config['gres']['gre'])) {
		foreach ($config['gres']['gre'] as $gre)
			if($gre['if'] == $interface)
				$result[] = $gre;
	}

	return $result;
}

function link_interface_to_gif($interface) {
	global $config;

	$result = array();

	if (is_array($config['gifs']['gif'])) {
		foreach ($config['gifs']['gif'] as $gif)
			if($gif['if'] == $interface)
				$result[] = $gif;
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

	if (!does_interface_exist($interface))
		return;

	/* Setup IP cache */
	if (!isset($interface_ip_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_get_interface_addresses($interface);
		$interface_ip_arr_cache[$interface] = $ifinfo['ipaddr'];
		$interface_sn_arr_cache[$interface] = $ifinfo['subnetbits'];
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

	if (!does_interface_exist($interface))
		return;

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

	if (!does_interface_exist($interface))
		return;

	/* Setup IP cache */
	if (!isset($interface_llv6_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_getall_interface_addresses($interface);
		foreach($ifinfo as $line) {
			if (strstr($line, ":")) {
				$parts = explode("/", $line);
				if(is_linklocal($parts[0])) {
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
	if (does_interface_exist($interface) == false)
		return;

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
	if (does_interface_exist($interface) == false)
		return;

	if (!isset($interface_snv6_arr_cache[$interface]) or $flush) {
		$ifinfo = pfSense_get_interface_addresses($interface);
		$interface_ipv6_arr_cache[$interface] = $ifinfo['ipaddr6'];
		$interface_snv6_arr_cache[$interface] = $ifinfo['subnetbits6'];
	}

	return $interface_snv6_arr_cache[$interface];
}

function ip_in_interface_alias_subnet($interface, $ipalias) {
	global $config;

	if (empty($interface) || !is_ipaddr($ipalias))
		return false;
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			switch ($vip['mode']) {
			case "ipalias":
				if ($vip['interface'] <> $interface)
					break;
				$subnet = is_ipaddrv6($ipalias) ? gen_subnetv6($vip['subnet'], $vip['subnet_bits']) : gen_subnet($vip['subnet'], $vip['subnet_bits']);
				if (ip_in_subnet($ipalias, $subnet . "/" . $vip['subnet_bits']))
					return true;
				break;
			}
		}
	}

	return false;
}

function get_interface_ip($interface = "wan") {
	$realif = get_failover_interface($interface);
	if (!$realif) {
		if (preg_match("/^carp/i", $interface))
			$realif = $interface;
		else if (preg_match("/^[a-z0-9]+_vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	$curip = find_interface_ip($realif);
	if ($curip && is_ipaddr($curip) && ($curip != "0.0.0.0"))
		return $curip;
	else
		return null;
}

function get_interface_ipv6($interface = "wan", $flush = false) {
	global $config;

	$realif = get_failover_interface($interface, "inet6");
	if (!$realif) {
		if (preg_match("/^[a-z0-9]+_vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	/*
	 * NOTE: On the case when only the prefix is requested,
	 * the communication on WAN will be done over link-local.
	 */
	if (is_array($config['interfaces'][$interface])) {
		switch ($config['interfaces'][$interface]['ipaddr']) {
		case 'pppoe':
		case 'l2tp':
		case 'pptp':
		case 'ppp':
			if ($config['interfaces'][$interface]['ipaddrv6'] == 'dhcp6')
				$realif = get_real_interface($interface, "inet6", true);
			break;
		}
		if (isset($config['interfaces'][$interface]['dhcp6prefixonly'])) {
			$curip = find_interface_ipv6_ll($realif, $flush);
			if ($curip && is_ipaddrv6($curip) && ($curip != "::"))
				return $curip;
		}
	}

	$curip = find_interface_ipv6($realif, $flush);
	if ($curip && is_ipaddrv6($curip) && ($curip != "::"))
		return $curip;
	else
		return null;
}

function get_interface_linklocal($interface = "wan") {

	$realif = get_failover_interface($interface, "inet6");
	if (!$realif) {
		if (preg_match("/^carp/i", $interface))
			$realif = $interface;
		else if (preg_match("/^[a-z0-9]+_vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	$curip = find_interface_ipv6_ll($realif);
	if ($curip && is_ipaddrv6($curip) && ($curip != "::"))
		return $curip;
	else
		return null;
}

function get_interface_subnet($interface = "wan") {
	$realif = get_real_interface($interface);
	if (!$realif) {
		if (preg_match("/^carp/i", $interface))
			$realif = $interface;
		else if (preg_match("/^[a-z0-9]+_vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	$cursn = find_interface_subnet($realif);
	if (!empty($cursn))
		return $cursn;

	return null;
}

function get_interface_subnetv6($interface = "wan") {
	global $config;

	$realif = get_real_interface($interface, "inet6");
	if (!$realif) {
		if (preg_match("/^[a-z0-9]+_vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	$cursn = find_interface_subnetv6($realif);
	if (!empty($cursn))
		return $cursn;

	return null;
}

/* return outside interfaces with a gateway */
function get_interfaces_with_gateway() {
	global $config;

	$ints = array();

	/* loop interfaces, check config for outbound */
	foreach($config['interfaces'] as $ifdescr => $ifname) {
		switch ($ifname['ipaddr']) {
			case "dhcp":
			case "ppp";
			case "pppoe":
			case "pptp":
			case "l2tp":
			case "ppp";
				$ints[$ifdescr] = $ifdescr;
			break;
			default:
				if (substr($ifname['if'], 0, 4) ==  "ovpn" ||
				    !empty($ifname['gateway']))
					$ints[$ifdescr] = $ifdescr;
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
			case "pppoe":
			case "pptp":
			case "l2tp":
			case "ppp";
				return true;
			break;
			default:
				if (substr($ifname['if'], 0, 4) ==  "ovpn")
					return true;
				if (!empty($ifname['gateway']))
					return true;
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
				if (substr($ifname['if'], 0, 4) ==  "ovpn")
					return true;
				$tunnelif = substr($ifname['if'], 0, 3);
				if ($tunnelif == "gif" || $tunnelif == "gre")
					return true;
				if (!empty($ifname['gatewayv6']))
					return true;
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
	 * http://www.freebsd.org/cgi/man.cgi?query=altq&apropos=0&sektion=0&manpath=FreeBSD+8.3-RELEASE&arch=default&format=html
	 * Only the following drivers have ALTQ support
	 */
	$capable = array("ae", "age", "alc", "ale", "an", "ath", "aue", "axe", "awi", "bce",
			"bfe", "bge", "bridge", "cas", "dc", "de", "ed", "em", "ep", "epair", "et", "fxp", "gem",
			"hme", "igb", "ipw", "iwi", "ixgbe", "jme", "le", "lem", "msk", "mxge", "my", "nfe",
			"nge", "npe", "nve", "ral", "re", "rl", "rum", "run", "bwn", "sf", "sge", "sis", "sk",
			"ste", "stge", "ti", "txp", "udav", "ural", "vge", "vr", "vte", "wi", "xl",
			"ndis", "tun", "ovpns", "ovpnc", "vlan", "pppoe", "pptp", "ng",
			"l2tp", "ppp", "vtnet");

	$int_family = remove_ifindex($int);

	if (in_array($int_family, $capable))
		return true;
	else if (stristr($int, "l2tp")) /* VLANs are name $parent_$vlan now */
		return true;
	else if (stristr($int, "_vlan")) /* VLANs are name $parent_$vlan now */
		return true;
	else if (stristr($int, "_wlan")) /* WLANs are name $parent_$wlan now */
		return true;
	else
		return false;
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
	if(!isset($config['interfaces'][$friendly]['wireless'])) {
		if (preg_match($g['wireless_regex'], $interface)) {
			if (isset($config['interfaces'][$friendly]))
				$config['interfaces'][$friendly]['wireless'] = array();
			return true;
		}
		return false;
	} else
		return true;
}

function get_wireless_modes($interface) {
	/* return wireless modes and channels */
	$wireless_modes = array();

	$cloned_interface = get_real_interface($interface);

	if($cloned_interface && is_interface_wireless($cloned_interface)) {
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
			if(trim($wireless_mode) != "") {
				/* if we only have 11g also set 11b channels */
				if($wireless_mode == "11g") {
					if(!isset($wireless_modes["11b"]))
						$wireless_modes["11b"] = array();
				} else if($wireless_mode == "11g ht") {
					if(!isset($wireless_modes["11b"]))
						$wireless_modes["11b"] = array();
					if(!isset($wireless_modes["11g"]))
						$wireless_modes["11g"] = array();
					$wireless_mode = "11ng";
				} else if($wireless_mode == "11a ht") {
					if(!isset($wireless_modes["11a"]))
						$wireless_modes["11a"] = array();
					$wireless_mode = "11na";
				}
				$wireless_modes["$wireless_mode"]["$c"] = $wireless_channel;
			}
			$c++;
		}
	}
	return($wireless_modes);
}

/* return channel numbers, frequency, max txpower, and max regulation txpower */
function get_wireless_channel_info($interface) {
	$wireless_channels = array();

	$cloned_interface = get_real_interface($interface);

	if($cloned_interface && is_interface_wireless($cloned_interface)) {
		$chan_list = "/sbin/ifconfig {$cloned_interface} list txpower";
		$stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
		$format_list = "/usr/bin/awk '{print \$1 \",\" \$3 \" \" \$4 \",\" \$5 \",\" \$7}'";

		$interface_channels = "";
		exec("$chan_list | $stack_list | sort -u | $format_list 2>&1", $interface_channels);

		foreach ($interface_channels as $channel_line) {
			$channel_line = explode(",", $channel_line);
			if(!isset($wireless_channels[$channel_line[0]]))
				$wireless_channels[$channel_line[0]] = $channel_line;
		}
	}
	return($wireless_channels);
}

/****f* interfaces/get_interface_mtu
 * NAME
 *   get_interface_mtu - Return the mtu of an interface
 * RESULT
 *   $tmp       - Returns the mtu of an interface
 ******/
function get_interface_mtu($interface) {
	$mtu = pfSense_get_interface_addresses($interface);
	return $mtu['mtu'];
}

function get_interface_mac($interface) {

	$macinfo = pfSense_get_interface_addresses($interface);
	return $macinfo["macaddr"];
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
	for($x=0; $x<5; $x++)
		$mac .= ":" . dechex(rand(16, 255));
	return $mac;
}

/****f* interfaces/is_jumbo_capable
 * NAME
 *   is_jumbo_capable - Test if interface is jumbo frame capable.  Useful for determining VLAN capability.
 * INPUTS
 *   $int             - string containing interface name
 * RESULT
 *   boolean          - true or false
 ******/
function is_jumbo_capable($iface) {
	$iface = trim($iface);
	$capable = pfSense_get_interface_addresses($iface);

	if (isset($capable['caps']['vlanmtu']))
		return true;

	return false;
}

function interface_setup_pppoe_reset_file($pppif, $iface="") {
	global $g;

	$cron_file = "{$g['varetc_path']}/pppoe_restart_{$pppif}";

	if(!empty($iface) && !empty($pppif)){
		$cron_cmd = <<<EOD
#!/bin/sh
/usr/local/sbin/pfSctl -c 'interface reload {$iface}'
/usr/bin/logger -t {$pppif} "PPPoE periodic reset executed on {$iface}"

EOD;

		@file_put_contents($cron_file, $cron_cmd);
		chmod($cron_file, 0755);
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
	} else
		unlink_if_exists($cron_file);
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
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_staticarp_configure($if) being called $mt\n";
	}

	$ifcfg = $config['interfaces'][$if];

	if (empty($if) || empty($ifcfg['if']) || !isset($ifcfg['enable']))
		return 0;

	/* Enable staticarp, if enabled */
	if(isset($config['dhcpd'][$if]['staticarp'])) {
		mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " staticarp " );
		mwexec("/usr/sbin/arp -d -i " . escapeshellarg($ifcfg['if']) . " -a > /dev/null 2>&1 ");
		if (is_array($config['dhcpd'][$if]['staticmap'])) {

			foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
				mwexec("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));

			}

		}
	} else {
		mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " -staticarp " );
		mwexec("/usr/sbin/arp -d -i " . escapeshellarg($ifcfg['if']) . " -a > /dev/null 2>&1 ");
		if (is_array($config['dhcpd'][$if]) && is_array($config['dhcpd'][$if]['staticmap'])) {
			foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
				if (isset($arpent['arp_table_static_entry'])) {
					mwexec("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));
				}
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
	$a_groups = return_gateway_groups_array();
	if (is_array($a_groups[$interface])) {
		/* we found a gateway group, fetch the interface or vip */
		if ($a_groups[$interface][0]['vip'] <> "")
			return $a_groups[$interface][0]['vip'];
		else
			return $a_groups[$interface][0]['int'];
	}
	/* fall through to get_real_interface */
	/* XXX: Really needed? */
	return get_real_interface($interface, $family);
}

function remove_ifindex($ifname) {
	return preg_replace("/[0-9]+$/", "", $ifname);
}

?>
