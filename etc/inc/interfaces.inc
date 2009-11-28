<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2008-2009 Ermal Luçi
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

	pfSense_BUILDER_BINARIES:	/usr/sbin/pppd	/sbin/dhclient	/bin/sh	/usr/bin/grep	/usr/bin/xargs	/usr/bin/awk	/usr/local/sbin/choparp
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/sbin/route	/usr/sbin/ngctl	/usr/sbin/arp	/bin/kill	/usr/local/sbin/mpd4
	pfSense_MODULE:	interfaces

*/

/* include all configuration functions */
require_once("globals.inc");
require_once("cmd_chain.inc");

function interfaces_bring_up($interface) {
	if(!$interface) {
		log_error("interfaces_bring_up() was called but no variable defined.");
		log_error( "Backtrace: " . debug_backtrace() );
		return;
	}
	mwexec("/sbin/ifconfig " . escapeshellarg($interface) . " up");
}

/*
 * Return the interface array
 */
function get_interface_arr($flush = false) {
        global $interface_arr_cache;

        /* If the cache doesn't exist, build it */
        if (!isset($interface_arr_cache) or $flush)
                $interface_arr_cache = `/sbin/ifconfig -l`;

        return $interface_arr_cache;
}

/*
 * does_interface_exist($interface): return true or false if a interface is
 * detected.
 */
function does_interface_exist($interface) {
        global $config;

        if(!$interface)
                return false;

        $ints = get_interface_arr();
        if(stristr($ints, $interface) !== false)
                return true;
        else
                return false;
}

function interfaces_loopback_configure() {
	if($g['booting'])
		echo "Configuring loopback interface...";
	mwexec("/sbin/ifconfig lo0 127.0.0.1");
	interfaces_bring_up("lo0");
	exec("/sbin/route add 127.0.0.2 127.0.0.1");
	if($g['booting'])
		echo "done.\n";
	return 0;
}

function interfaces_vlan_configure() {
	global $config, $g;
	if($g['booting'])
		echo "Configuring VLAN interfaces...";
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if(empty($vlan['vlanif']))
				$vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
			/* XXX: Maybe we should report any errors?! */
			interface_vlan_configure($vlan);
		}
	}
	if($g['booting'])
		echo "done.\n";
}

function interface_vlan_configure(&$vlan) {
        global $config, $g;

	if (!is_array($vlan)) {
		log_error("VLAN: called with wrong options. Problems with config!");
		return;
	}
	$if = $vlan['if'];
	$vlanif  = empty($vlan['vlanif']) ? "{$if}_vlan{$vlan['tag']}" : $vlan['vlanif'];
	$tag = $vlan['tag'];

	if(empty($if)) {
		log_error("interface_vlan_confgure called with if undefined.");
		return;
	}

	/* make sure the parent interface is up */
	interfaces_bring_up($if);
	/* Since we are going to add vlan(4) try to enable all that hardware supports. */
	mwexec("/sbin/ifconfig {$if} vlanhwtag");
	mwexec("/sbin/ifconfig {$if} vlanmtu");

	if (!empty($vlanif) && does_interface_exist($vlanif)) {
		interface_bring_down($vlanif);
	} else {
		$tmpvlanif = exec("/sbin/ifconfig vlan create");
		mwexec("/sbin/ifconfig {$tmpvlanif} name {$vlanif}");
		mwexec("/usr/sbin/ngctl name {$tmpvlanif}: {$vlanif}");
	}
	
	mwexec("/sbin/ifconfig {$vlanif} vlan " .
		escapeshellarg($tag) . " vlandev " .
		escapeshellarg($if));

	interfaces_bring_up($vlanif);

    /* invalidate interface cache */
    get_interface_arr(true);

    /*   all vlans need to spoof their parent mac address, too.  see
     *   ticket #1514: http://cvstrac.pfsense.com/tktview?tn=1514,33
     */
    foreach($config['interfaces'] as $interfaces) {
		if($interfaces['if'] == $if && $interfaces['spoofmac']) {
			mwexec("/sbin/ifconfig " . escapeshellarg($vlanif) .
				" link " . escapeshellarg($interfaces['spoofmac']));
		}
	}

	/* XXX: ermal -- for now leave it here at the moment it does not hurt. */
	interfaces_bring_up($if);

	return $vlanif;
}

function interface_qinq_configure(&$vlan, $fd = NULL) {
        global $config, $g;

        if (!is_array($vlan)) {
                log_error("QinQ compat VLAN: called with wrong options. Problems with config!\n");
                return;
        }

        $qinqif = $vlan['if'];
        $tag = $vlan['tag'];
        if(empty($qinqif)) {
                log_error("interface_qinq_confgure called with if undefined.\n");
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

        if (!stristr($qinqif, "vlan"))
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
		echo "Configuring QinQ interfaces...";
	if (is_array($config['qinqs']['qinqentry']) && count($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			/* XXX: Maybe we should report any errors?! */
			interface_qinq_configure($qinq);
		}
	}
	if($g['booting'])
		echo "done.\n";
}

function interface_qinq2_configure(&$qinq, $fd, $macaddr) {
        global $config, $g;

        if (!is_array($qinq)) {
                log_error("QinQ compat VLAN: called with wrong options. Problems with config!\n");
                return;
        }

        $if = $qinq['if'];
        $tag = $qinq['tag'];
        $vlanif = "{$if}_{$tag}";
        if(empty($if)) {
                log_error("interface_qinq_confgure called with if undefined.\n");
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

function interfaces_bridge_configure() {
        global $config;

        $i = 0;
        if (is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
                foreach ($config['bridges']['bridged'] as $bridge) {
                        if(empty($bridge['bridgeif']))
                                $bridge['bridgeif'] = "bridge{$i}";
                        /* XXX: Maybe we should report any errors?! */
                        interface_bridge_configure($bridge);
                        $i++;
                }
        }
}

function interface_bridge_configure(&$bridge) {
	global $config, $g;

	if (!is_array($bridge))
	        return -1;

	if (empty($bridge['members'])) {
		log_error("No members found on {$bridge['bridgeif']}");
		return -1;
	}

	$members = explode(',', $bridge['members']);
	if (!count($members))
		return -1;
	
	$checklist = get_configured_interface_list();

	if ($g['booting'] || !empty($bridge['bridgeif'])) {
		mwexec("/sbin/ifconfig {$bridge['bridgeif']} destroy");
		mwexec("/sbin/ifconfig {$bridge['bridgeif']} create");
		$bridgeif = $bridge['bridgeif'];
	} else {
		$bridgeif = exec("/sbin/ifconfig bridge create");
	}

	/* Calculate smaller mtu and enforce it */
	$smallermtu = 0;
	foreach ($members as $member) {
		$realif = get_real_interface($member);
		$mtu = get_interface_mtu($realif);
		if ($smallermtu == 0 && !empty($mtu))
			$smallermtu = $mtu;
		else if (!empty($mtu) && $mtu < $smallermtu)
			$smallermtu = $mtu;
	}
	 
	/* Just in case anything is not working well */
	if ($smallermtu == 0)
		$smallermtu = 1500; 

	/* Add interfaces to bridge */
	foreach ($members as $member) {
		if (!array_key_exists($member, $checklist))
			continue;
		$realif = get_real_interface($member);
		$realif =  escapeshellarg($realif);
		/* make sure the parent interface is up */
		mwexec("/sbin/ifconfig {$realif} mtu {$smallermtu}");
		if(!$realif) 
			log_error("realif not defined in interfaces bridge - up");
		interfaces_bring_up($realif);
		mwexec("/sbin/ifconfig {$bridgeif} addm {$realif}");	
	}

	if (isset($bridge['enablestp'])) {
		/* Choose spanning tree proto */
		mwexec("/sbin/ifconfig {$bridgeif} proto {$bridge['proto']}");	
		
		if (!empty($bridge['stp'])) {
			$stpifs = explode(',', $bridge['stp']);
			foreach ($stpifs as $stpif) {
				$realif = get_real_interface($stpif);
				mwexec("/sbin/ifconfig {$bridgeif} stp {$realif}");
			}
		}
		if (!empty($bridge['maxage']))
			mwexec("/sbin/ifconfig {$bridgeif} maxage {$bridge['maxage']}");
		if (!empty($brige['fwdelay']))
			mwexec("/sbin/ifconfig {$bridgeif} fwddelay {$bridge['fwdelay']}");
		if (!empty($brige['hellotime']))
                        mwexec("/sbin/ifconfig {$bridgeif} hellotime {$bridge['hellotime']}");
		if (!empty($brige['priority']))
                        mwexec("/sbin/ifconfig {$bridgeif} priority {$bridge['priority']}");
		if (!empty($brige['holdcount']))
                        mwexec("/sbin/ifconfig {$bridgeif} holdcnt {$bridge['holdcnt']}");
		if (!empty($bridge['ifpriority'])) {
			$pconfig = explode(",", $bridge['ifpriority']);
			$ifpriority = array();
			foreach ($pconfig as $cfg) {
				$embcfg = explode(":", $cfg);
				foreach ($embcfg as $key => $value)
					$ifpriority[$key] = $value;
			}
			foreach ($ifpriority as $key => $value) {
				$realif = get_real_interface($key);
				mwexec("/sbin/ifconfig ${bridgeif} ifpriority {$realif} {$value}"); 
			}
		}
		if (!empty($bridge['ifpathcost'])) {
			$pconfig = explode(",", $bridges['ifpathcost']);
			$ifpathcost = array();
			foreach ($pconfig as $cfg) {
				$embcfg = explode(":", $cfg);
				foreach ($embcfg as $key => $value)
					$ifpathcost[$key] = $value;
			}
			foreach ($ifpathcost as $key => $value) {
                        	$realif = get_real_interface($key);
                        	mwexec("/sbin/ifconfig ${bridgeif} ifpathcost {$realif} {$value}");
                	}
		}
	}

	if ($bridge['maxaddr'] <> "")
		mwexec("/sbin/ifconfig {$bridgeif} maxaddr {$bridge['maxaddr']}");
        if ($bridge['timeout'] <> "")
                mwexec("/sbin/ifconfig {$bridgeif} timeout {$bridge['timeout']}");
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

	if($bridgeif)
		interfaces_bring_up($bridgeif);	
	else 
		log_error("bridgeif not defined -- could not bring interface up");

	return $bridgeif;
}

function interfaces_lagg_configure() 
{
        global $config, $g;
		if($g['booting']) 
			echo "Configuring LAGG interfaces...";
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
			echo "done.\n";
}

function interface_lagg_configure(&$lagg) {
        global $config, $g;

        if (!is_array($lagg))
		return -1;

	$members = explode(',', $lagg['members']);
	if (!count($members))
		return -1;
	
	$checklist = get_interface_list();

	if ($g['booting'] || !(empty($lagg['laggif']))) {
                mwexec("/sbin/ifconfig {$lagg['laggif']} destroy");
                mwexec("/sbin/ifconfig {$lagg['laggif']} create");
                $laggif = $lagg['laggif'];
        } else
                $laggif = exec("/sbin/ifconfig lagg create");

	/* Calculate smaller mtu and enforce it */
        $smallermtu = 0;
        foreach ($members as $member) {
                $mtu = get_interface_mtu($member);
		if ($smallermtu == 0 && !empty($mtu))
			$smallermtu = $mtu;
                else if (!empty($mtu) && $mtu < $smallermtu)
                        $smallermtu = $mtu;
        }

	/* Just in case anything is not working well */
        if ($smallermtu == 0)
                $smallermtu = 1500;

	foreach ($members as $member) {
		if (!array_key_exists($member, $checklist))
			continue;
		/* make sure the parent interface is up */
		mwexec("/sbin/ifconfig {$member} mtu {$smallermtu}");
		interfaces_bring_up($member);
		mwexec("/sbin/ifconfig {$laggif} laggport {$member}");
	}
	
	mwexec("/sbin/ifconfig {$laggif} laggproto {$lagg['proto']}");

	interfaces_bring_up($laggif);

	return $laggif;
}

function interfaces_gre_configure() {
        global $config;

        $i = 0;
        if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
                foreach ($config['gres']['gre'] as $gre) {
                        if(empty($gre['greif']))
                                $gre['greif'] = "gre{$i}";
                        /* XXX: Maybe we should report any errors?! */
                        interface_gre_configure($gre);
                        $i++;
                }
        }
}

function interface_gre_configure(&$gre) {
        global $config, $g;

	if (!is_array($gre))
		return -1;

	$realif = get_real_interface($gre['if']);
	$realifip = get_interface_ip($gre['if']);

	/* make sure the parent interface is up */
	interfaces_bring_up($realif);

	if ($g['booting'] || !(empty($gre['greif']))) {
		mwexec("/sbin/ifconfig {$gre['greif']} destroy");
		mwexec("/sbin/ifconfig {$gre['greif']} create");
		$greif = $gre['greif'];
	} else {
		$greif = exec("/sbin/ifconfig gre create");
	}

	/* Do not change the order here for more see gre(4) NOTES section. */
	mwexec("/sbin/ifconfig {$greif} tunnel {$realifip} {$gre['remote-addr']}");
	mwexec("/sbin/ifconfig {$greif} {$gre['tunnel-local-addr']} {$gre['tunnel-remote-addr']} netmask " . gen_subnet_mask($gre['tunnel-remote-net']));
	if (isset($gre['link0']) && $gre['link0'])
		mwexec("/sbin/ifconfig {$greif} link0");
	if (isset($gre['link1']) && $gre['link1'])
		mwexec("/sbin/ifconfig {$greif} link1");
	if (isset($gre['link2']) && $gre['link2'])
		mwexec("/sbin/ifconfig {$greif} link2");

	if($greif)
		interfaces_bring_up($greif);
	else 
		log_error("Could not bring greif up -- variable not defined.");

	mwexec("/sbin/route add {$gre['remote-addr']}/{$gre['tunnel-remote-net']} {$realifip}");
	file_put_contents("/tmp/{$greif}_router", $gre['tunnel-remote-addr']);

	return $greif;
}

function interfaces_gif_configure() {
	global $config;
	$i = 0;
	if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
		foreach ($config['gifs']['gif'] as $gif) {
			if(empty($gif['gifif']))
				$gre['gifif'] = "gif{$i}";
			/* XXX: Maybe we should report any errors?! */
			interface_gif_configure($gif);
			$i++;
		}
	}
}

function interface_gif_configure(&$gif) {
	global $config, $g;

	if (!is_array($gif))
		return -1;

	$realif = get_real_interface($gif['if']);
	$realifip = get_interface_ip($gif['if']);

	/* make sure the parent interface is up */
	if($realif)
		interfaces_bring_up($realif);
	else 
		log_error("could not bring realif up -- variable not defined -- interface_gif_configure()");

	if ($g['booting'] || !(empty($gif['gifif']))) {
		mwexec("/sbin/ifconfig {$gif['gifif']} destroy");
		mwexec("/sbin/ifconfig {$gif['gifif']} create");
		$gifif = $gif['gifif'];
	} else
		$gifif = exec("/sbin/ifconfig gif create");

	/* Do not change the order here for more see gif(4) NOTES section. */
	mwexec("/sbin/ifconfig {$gifif} tunnel {$realifip} {$gif['remote-addr']}");
	mwexec("/sbin/ifconfig {$gifif} {$gif['tunnel-local-addr']} {$gif['tunnel-remote-addr']} netmask " . gen_subnet_mask($gif['tunnel-remote-net']));
	if (isset($gif['link0']) && $gif['link0'])
		mwexec("/sbin/ifconfig {$gifif} link0");
	if (isset($gif['link1']) && $gif['link1'])
		mwexec("/sbin/ifconfig {$gifif} link1");
	if($gifif)
		interfaces_bring_up($gifif);
	else
		log_error("could not bring gifif up -- variable not defined");

	mwexec("/sbin/route add {$gif['remote-addr']}/{$gif['tunnel-remote-net']} {$realifip}");
	file_put_contents("/tmp/{$gifif}_router", $gif['tunnel-remote-addr']);

	return $gifif;
}

function interfaces_configure() {
	global $config, $g;

	/* Set up our loopback interface */
	interfaces_loopback_configure();

	/* set up LAGG virtual interfaces */
	interfaces_lagg_configure();

	/* set up VLAN virtual interfaces */
	interfaces_vlan_configure();

	interfaces_qinq_configure();

	/* Set up PPP interfaces */
	interfaces_ppp_configure();

	$iflist = get_configured_interface_with_descr();
	$delayed_list = array();
	$bridge_list = array();
	
	foreach($iflist as $if => $ifname) {
		$realif = $config['interfaces'][$if]['if'];
		if(is_array($realif['pppoe']) && isset($realif['pppoe']['pppoe-reset-type']))
			setup_pppoe_reset_file($if, true);
		else 
			setup_pppoe_reset_file($if, false);
		if (strstr($realif, "bridge")) 
			$bridge_list[$if] = $ifname;
		else if (strstr($realif, "gre"))
			$delayed_list[$if] = $ifname;
		else if (strstr($realif, "gif"))
			$delayed_list[$if] = $ifname;
		else {
			if ($g['booting'])
				echo "Configuring {$ifname} interface...";
			if($g['debug'])
				log_error("Configuring {$ifname}");
			interface_configure($if, true);
			if ($g['booting']) 
				echo "done.\n";
		}
	}

	/* set up GRE virtual interfaces */
	interfaces_gre_configure();

	/* set up GIF virtual interfaces */
	interfaces_gif_configure();
	
	foreach ($delayed_list as $if => $ifname) {
		if ($g['booting'])
			echo "Configuring {$ifname} interface...";
        	if ($g['debug'])
        		log_error("Configuring {$ifname}");

		interface_configure($if, true);

		if ($g['booting'])
			echo "done.\n";
	}

	/* set up BRIDGe virtual interfaces */
	interfaces_bridge_configure();

	foreach ($bridge_list as $if => $ifname) {
		if ($g['booting'])
			echo "Configuring {$ifname} interface...";
		if($g['debug'])
			log_error("Configuring {$ifname}");

		interface_configure($if, true);

		if ($g['booting'])
			echo "done.\n";
	}

	/* bring up vip interfaces */
	interfaces_vips_configure();

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
		captiveportal_configure();

		/* set the reload filter dity flag */
		filter_configure();
	}

	return 0;
}

function interface_reconfigure($interface = "wan") {
	interface_bring_down($interface);
	interface_configure($interface);
}

function interface_vip_bring_down(&$vip) {
	switch ($vip['mode']) {
	case "proxyarp":
		interface_proxyarp_configure();
		break;
	case "ipalias":
		if(does_interface_exist($vip['interface']))
			mwexec("/sbin/ifconfig " . get_real_interface($vip['interface']) . " delete {$vip['subnet']}");
		break;
	case "carp":
		$vipif = "vip" . $vip['vhid'];
		if(does_interface_exist("$vipif")) 
			interface_bring_down($vipif);
		break;
	case "carpdev-dhcp":
		$vipif = "vip" . $vip['vhid'];
		if(does_interface_exist("$vipif")) 
			interface_bring_down($vipif);
		break;
	}
}

function interface_bring_down($interface = "wan", $destroy = false) {
	global $config, $g;

	if (!isset($config['interfaces'][$interface]))
		return; 

	$ifcfg = $config['interfaces'][$interface];

	$realif = get_real_interface($interface);

	mwexec("/usr/sbin/arp -d -i {$realif} -a");

	/* remove interface up file if it exists */
	unlink_if_exists("{$g['tmp_path']}/{$realif}up");
	unlink_if_exists("{$g['vardb_path']}/{$interface}ip");
	unlink_if_exists("/tmp/{$realif}_router");

	switch ($ifcfg['ipaddr']) {
	case "pppoe":
		killbypid("{$g['varrun_path']}/pppoe_{$interface}.pid");
		sleep(2);
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.links");
		break;
	case "pptp":
		killbypid("{$g['varrun_path']}/pptp_{$interface}.pid");
		sleep(2);
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.links");
		break;
	case "carpdev-dhcp":
		/* 
		 * NB: When carpdev gets enabled it would be better to be handled as all
		 *	   other interfaces! 
		 */
	case "dhcp":
		$pid = find_dhclient_process($interface);
		if($pid)
			mwexec("kill {$pid}");
		sleep(1);
		unlink_if_exists("{$g['varetc_path']}/dhclient_{$interface}.conf");
		if(does_interface_exist("$realif")) {
			mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " down");
		}
		break;
	default:
		if(does_interface_exist("$realif")) {
			mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " down");
		}
		break;
	}

	if ($destroy == true) {
		if (preg_match("/^tun|^ppp|^ovpn|^gif|^gre|^lagg|^bridge|vlan/i", $realif))
			mwexec("/sbin/ifconfig {$realif} destroy");
	}	

	return;
}

function interfaces_ppp_configure() {
	global $config, $g;
	if(!$g['booting']) 
		conf_mount_rw();
	if($g['booting'])
		echo "Configuring PPP interfaces...";
	if($config['ppps']['ppp']) {
		foreach($config['ppps']['ppp'] as $ppp) {
			$dev = substr($ppp['port'], 5);
			interface_ppp_configure($dev);
		}
	}
	if(!$g['booting']) 
		conf_mount_ro();
	if($g['booting'])
		echo "done.\n";
}

function interface_ppp_configure($ifcfg) {
	global $config, $g;
	
	/* Remove  the /dev/ from the device name. */
	$orig_dev = $ifcfg;

	// ppp (userland) requires a /var/spool/lock directory
	if(!is_dir("/var/spool/lock")) {
		exec("mkdir -p /var/spool/lock");
		exec("chmod a+rw /var/spool/lock/.");
	}

	// Construct the ppp.conf file
	$peerfile .= "default:\n";
	$peerfile .= " set log Phase Chat LCP IPCP CCP tun command\n";
	$peerfile .= " ident user-ppp VERSION (built COMPILATIONDATE)\n";
	$peerfile .= " set dial \"ABORT BUSY ABORT NO\\\sCARRIER TIMEOUT 5 \\\n";
	$peerfile .= " 		\\\"\\\" AT OK-AT-OK ATE1Q0 OK \\\dATDT\\\T TIMEOUT 40 CONNECT\"\n";
	$peerfile .= " enable dns\n";
	$peerfile .= " nat enable yes\n";
	$peerfile .= " set reconnect {$ifcfg['connect-max-attempts']} 5\n";
	$peerfile .= " allow users root\n\n";

	// Loop variables
    $i = 0;
	$startingip = 1;

	// Start ppp.linkup file
	$rclinkup = "default:\n";

    if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $ppp) {
			$dev = substr($ppp['port'], 5);
			$realif  = $ppp['port'];
			$peerfile .= "{$dev}:\n";
			$peerfile .= " set device {$realif}\n";
			if($ppp['dialcmd'])
				$peerfile .= " set dial {$ppp['dialcmd']}\n";
			$peerfile .= " set speed \"{$ppp['linespeed']}\"\n";
			$peerfile .= " add default HISADDR\n";
			$peerfile .= " set timeout 0\n";
			$endingip = $startingip+1;
			if($ppp['localip'])
				$startingip = $ppp['localip'];
			if($ppp['gateway'])
				$endingip = $ppp['gateway'];
			$peerfile .= " enable dns\n";
			$peerfile .= " set ifaddr 10.0.0.{$startingip}/0 10.0.0.{$endingip}/0 255.255.255.0 0.0.0.0\n";
			$peerfile .= " set phone \"{$ppp['phone']}\"\n";
			$peerfile .= " set authname \"{$ppp['username']}\"\n";
			$peerfile .= " set authkey \"{$ppp['password']}\"\n";
			$peerfile .= "\n";
			$i++;
			$startingip++;
			$rclinkup.= "{$dev}:\n";
			$rclinkup.= "	! sh -c \"/sbin/ppp-script HISADDR INTERFACE DNS0 DNS1 \"\n";
			$rclinkup.= "	! sh -c \"/etc/rc.linkup INTERFACE start\"\n";
		}
    }

	// Write out configuration for ppp.conf
	file_put_contents("/etc/ppp/ppp.conf", $peerfile);

	// Write out linkup file
	file_put_contents("/etc/ppp/ppp.linkup", $rclinkup);

	// Make executable
	exec("chmod a+rx /etc/ppp/ppp.linkup");

	// Launch specified ppp instance
	$running = `ps awux | grep ppp | grep -v grep | grep $orig_dev`;
	if(!$running)
		mwexec("/usr/sbin/ppp -background {$orig_dev}");
}

function interfaces_carp_setup() {
	global $g, $config;

	$balanacing = "";
	$pfsyncinterface = "";
	$pfsyncenabled = "";
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_carp_setup() being called $mt\n";
	}

	// Prepare CmdCHAIN that will be used to execute commands.
	$cmdchain = new CmdCHAIN();	

	if ($g['booting']) {
		echo "Configuring CARP settings...";
		mute_kernel_msgs();
	}

	/* suck in configuration items */
	if($config['installedpackages']['carpsettings']) {
		if($config['installedpackages']['carpsettings']['config']) {
			foreach($config['installedpackages']['carpsettings']['config'] as $carp) {
				$pfsyncenabled = $carp['pfsyncenabled'];
				$balanacing = $carp['balancing'];
				$pfsyncinterface = $carp['pfsyncinterface'];
				$pfsyncpeerip = $carp['pfsyncpeerip'];
			}
		}
	} else {
		unset($pfsyncinterface);
		unset($balanacing);
		unset($pfsyncenabled);
	}

	$cmdchain->add("Allow CARP", "/sbin/sysctl net.inet.carp.allow=1", true);			
	if($balanacing) {
		$cmdchain->add("Enable CARP ARP-balancing", "/sbin/sysctl net.inet.carp.arpbalance=1", true);
		$cmdchain->add("Disallow CARP preemption", "/sbin/sysctl net.inet.carp.preempt=0", true);
	} else
		$cmdchain->add("Enable CARP preemption", "/sbin/sysctl net.inet.carp.preempt=1", true);		

	$cmdchain->add("Enable CARP logging", "/sbin/sysctl net.inet.carp.log=2", true);
	if (!empty($pfsyncinterface))
		$carp_sync_int = get_real_interface($pfsyncinterface);

	if($g['booting']) {
		/*    install rules to alllow pfsync to sync up during boot
		 *    carp interfaces will remain down until the bootup sequence finishes
		 */
		$fd = fopen("{$g['tmp_path']}/rules.boot", "w");
		if ($fd) {
			fwrite($fd, "pass quick proto carp all keep state\n");
			fwrite($fd, "pass quick proto pfsync all\n");
			fwrite($fd, "pass out quick from any to any keep state\n");
			fclose($fd);
			mwexec("/sbin/pfctl -f {$g['tmp_path']}/rules.boot");
		} else
			log_error("Could not create rules.boot file!");
	}

	/* setup pfsync interface */
	if($carp_sync_int and $pfsyncenabled) {
		if (is_ipaddr($pfsyncpeerip))
			$cmdchain->add("Bring up pfsync0 syncpeer", "/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} syncpeer {$pfsyncpeerip} up", false);						
		else
			$cmdchain->add("Bring up pfsync0 syncdev", "/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} up", false);			
	} else
		$cmdchain->add("Bring up pfsync0", "/sbin/ifconfig pfsync0 syncdev lo0 up", false);						

	if($config['virtualip']['vip'])
		$cmdchain->add("Allow CARP.", "/sbin/sysctl net.inet.carp.allow=1", true);				
	else
		$cmdchain->add("Disallow CARP.", "/sbin/sysctl net.inet.carp.allow=0", true);		
	
	if($g['debug'])
		$cmdchain->setdebug(); // optional for verbose logging

	$cmdchain->execute();
	$cmdchain->clear();

	if ($g['booting']) {
		unmute_kernel_msgs();
		echo "done.\n";
	}
}

function interface_proxyarp_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interface_proxyarp_configure() being called $mt\n";
	}

	/* kill any running choparp */
	killbyname("choparp");

	if (isset($config['virtualip']) && is_array($config['virtualip']['vip'])) {
		$paa = array();

		/* group by interface */
		foreach ($config['virtualip']['vip'] as $vipent) {
			if ($vipent['mode'] === "proxyarp") {
				if ($vipent['interface'])
					$proxyif = $vipent['interface'];
				else
					$proxyif = "wan";

				if (!is_array($paa[$if]))
					$paa[$proxyif] = array();

				$paa[$proxyif][] = $vipent;
			}
	}

	if (count($paa))
		foreach ($paa as $paif => $paents) {
			$paaifip = get_interface_ip($paif);
			if (!(is_ipaddr($paaifip)))
				continue;
			$args = get_real_interface($paif) . " auto";
			foreach ($paents as $paent) {

				if (isset($paent['subnet']))
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				else if (isset($paent['range']))
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" .
					$paent['range']['to']);
			}
			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	}

}

function interfaces_vips_configure() {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_vips_configure() being called $mt\n";
	}
	$paa = array();
	if(is_array($config['virtualip']['vip'])) {
		$carp_setuped = false;
		foreach ($config['virtualip']['vip'] as $vip) {
			switch ($vip['mode']) {
			case "proxyarp":
				/* nothing it is handled on interface_proxyarp_configure() */
				break;
			case "ipalias":
				interface_ipalias_configure(&$vip);
				break;
			case "carp":
				if ($carp_setuped == false) {
					interfaces_carp_setup();
					$carp_setuped = true;
				}
				interface_carp_configure($vip);
				break;
			case "carpdev-dhcp":
				interface_carpdev_configure($vip);
				break;
			}
		}
		
		interface_proxyarp_configure();
	}
}

function interface_ipalias_configure(&$vip) {

	if ($vip['mode'] == "ipalias") {
		$if = get_real_interface($vip['interface']);
		mwexec("/sbin/ifconfig " . escapeshellarg($if) . " " . $vip['subnet'] . "/" . escapeshellarg($vip['subnet_bits']) . " alias");
	}
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
					case "carpdev-dhcp":
					interface_vip_bring_down($vip);
					sleep(1);
					interface_carpdev_configure($vip);
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

	$vip_password = $vip['password'];
	$vip_password = str_replace(" ", "", $vip_password);
	if ($vip['password'] != "")
		$password = " pass \"" . $vip_password . "\"";

	// set the vip interface to the vhid
	$vipif = "vip{$vip['vhid']}";

	$interface = interface_translate_type_to_real($vip['interface']);
	/*
	 * ensure the interface containing the VIP really exists
 	 * prevents a panic if the interface is missing or invalid
	 */
	$realif = get_real_interface($vip['interface']);
	if (!does_interface_exist($realif)) {
		file_notice("CARP", "Interface specified for the virtual IP address {$vip['subnet']} does not exist. Skipping this VIP.", "Firewall: Virtual IP", "");
		return;
	}

	/* ensure CARP IP really exists prior to loading up */
	/* XXX: this can be bound to only the interface choosen in the carp creation. Not yet since upgrade is needed! */
	$found = false;
	$iflist = get_configured_interface_list();
	foreach($iflist as $if) {
		$ww_subnet_ip = get_interface_ip($if);
		$ww_subnet_bits = get_interface_subnet($if);
		if (ip_in_subnet($vip['subnet'], gen_subnet($ww_subnet_ip, $ww_subnet_bits) . "/" . $ww_subnet_bits)) {
			$found = true;
			break;
		}
	}
	if($found == false) {
		file_notice("CARP", "Sorry but we could not find a matching real interface subnet for the virtual IP address {$vip['subnet']}.", "Firewall: Virtual IP", "");
		return;
	}

	/* invalidate interface cache */
	get_interface_arr(true);

	/* create the carp interface and setup */
	if (does_interface_exist($vipif)) {
		interface_bring_down($vipif);
	} else {
		$carpif = exec("/sbin/ifconfig carp create");
		mwexec("/sbin/ifconfig {$carpif} name {$vipif}");
		mwexec("/usr/sbin/ngctl name {$carpif}: {$vipif}");
	}

	/* invalidate interface cache */
	get_interface_arr(true);

	$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
	mwexec("/sbin/ifconfig {$vipif} {$vip['subnet']}/{$vip['subnet_bits']} vhid {$vip['vhid']} advskew {$vip['advskew']} {$password}");

	interfaces_bring_up($vipif);
	
	return $vipif;
}

function interface_carpdev_configure(&$vip) {
	global $g;

	if ($vip['mode'] != "carpdev-dhcp")
		return;

	$vip_password = $vip['password'];
	$vip_password = str_replace(" ", "", $vip_password);
	if($vip['password'] != "")
		$password = " pass \"" . $vip_password . "\"";

	log_error("Found carpdev interface {$vip['interface']} on top of interface {$interface}");
	if (empty($vip['interface']))
		return;

	$vipif = "vip" . $vip['vhid'];
	$realif = interface_translate_type_to_real($vip['interface']);
	interfaces_bring_up($realif);
	/*
	 * ensure the interface containing the VIP really exists
	 * prevents a panic if the interface is missing or invalid
	 */
	if (!does_interface_exist($realif)) {
		file_notice("CARP", "Interface specified for the virtual IP address {$vip['subnet']} does not exist. Skipping this VIP.", "Firewall: Virtual IP", "");
		return;
	}

	if (does_interface_exist($vipif)) {
		interface_bring_down($vipif);
	} else {
		$carpdevif = exec("/sbin/ifconfig carp create");
		mwexec("/sbin/ifconfig {$carpdevif} name {$vipif}");
		mwexec("/usr/sbin/ngctl name {$carpdevif}: {$vipif}");
	}

	mwexec("/sbin/ifconfig {$vipif} carpdev {$realif} vhid {$vip['vhid']} advskew {$vip['advskew']} {$password}");
	interfaces_bring_up($vipif);

	/*
	 * XXX: BIG HACK but carpdev needs ip services active
	 *      before even starting something as dhclient.
	 *      I do not know if this is a feature or a bug
	 *      but better than track it make it work ;) .
	 */
	//$fakeiptouse = "10.254.254." . ($carp_instances_counter+1);
	//$cmdchain->add("CarpDEV hack", "/sbin/ifconfig {$carpint} inet {$fakeiptouse}", false);

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$vipif}.conf", "w");
	if ($fd) {
		$dhclientconf = "";

		$dhclientconf .= <<<EOD
interface "{$vipif}" {
timeout 60;
retry 1;
select-timeout 0;
initial-interval 1;
script "/sbin/dhclient-script";
}

EOD;

		fwrite($fd, $dhclientconf);
		fclose($fd);

		/* fire up dhclient */
		mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$vipif}.conf {$vipif} >/tmp/{$vipif}_output >/tmp/{$vipif}_error_output", false);
	} else {
		log_error("Error: cannot open dhclient_{$vipif}.conf in interfaces_carpdev_configure() for writing.\n");
		mwexec("/sbin/dhclient -b {$vipif}");
	}

	$fout = fopen("/tmp/ifconfig_{$vipif}","w");
	fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$vipif}.conf {$vipif}");
	fclose($fout);

	return $vipif;
}

function interface_wireless_clone($if, $wlcfg) {
	global $config, $g;
	/*   Check to see if interface has been cloned as of yet.  
	 *   If it has not been cloned then go ahead and clone it.
	 */
	$needs_clone = false;
	/* FIXME: Interface num needs to be something else when we
	 * integrate the wireless VAP support */
	/* Setting it to 0 for the time being. */
	$interface_num = 0;
	switch($wlcfg['wireless']['mode']) {
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
	if(does_interface_exist("{$wlcfg['if']}_wlan{$interface_num}")) {
		exec("/sbin/ifconfig {$wlcfg['if']}_wlan{$interface_num}", $output, $ret);
		$ifconfig_str = implode($output);
		if(($wlcfg['wireless']['mode'] == "hostap") && (! preg_match("/hostap/si", $ifconfig_str))) {
			log_error("Interface {$wlcfg['if']}_wlan{$interface_num} changed to hostap mode");
			$needs_clone = true;
		}
		if(($wlcfg['wireless']['mode'] == "adhoc") && (! preg_match("/adhoc/si", $ifconfig_str))) {
			log_error("Interface {$wlcfg['if']}_wlan{$interface_num} changed to adhoc mode");
			$needs_clone = true;
		}
		if(($wlcfg['wireless']['mode'] == "bss") && (preg_match("/hostap|adhoc/si", $ifconfig_str))) {
			log_error("Interface {$wlcfg['if']}_wlan{$interface_num} changed to infrastructure mode");
			$needs_clone = true;
		}
	} else {
		$needs_clone = true;
	}

	if($needs_clone == true) {
		/* remove previous instance if it exists */
		if(does_interface_exist("{$wlcfg['if']}_wlan{$interface_num}")) {
			mwexec("/sbin/ifconfig {$wlcfg['if']}_wlan{$interface_num} destroy");			
		}
		log_error("Cloning new wireless interface {$wlcfg['if']}_wlan{$interface_num}");
		// Create the new wlan interface. FreeBSD returns the new interface name.
		// example:  wlan2
		exec("/sbin/ifconfig wlan create wlandev {$wlcfg['if']} {$mode} 2>&1", $out, $ret);
		if($ret <> 0) {
			log_error("Failed to clone interface {$wlcfg['if']} with error code {$ret}, output {$out[0]}");
		}
		$newif = trim($out[0]);
		// Rename the interface to {$parentnic}_wlan{$number}#: EX: ath0_wlan0
		mwexec("/sbin/ifconfig {$newif} name {$wlcfg['if']}_wlan{$interface_num} 2>&1", false);
		// FIXME: not sure what ngctl is for. Doesn't work.
		// mwexec("/usr/sbin/ngctl name {$newif}: {$wlcfg['if']}_wlan{$interface_num}", false);
	}
}

function interface_wireless_configure($if, &$wl, &$wlcfg) {
	global $config, $g;

	/*    open up a shell script that will be used to output the commands.
	 *    since wireless is changing a lot, these series of commands are fragile
     *    and will sometimes need to be verified by a operator by executing the command
     *    and returning the output of the command to the developers for inspection.  please
     *    do not change this routine from a shell script to individul exec commands.  -sullrich
	 */

	// Remove script file
	unlink_if_exists("{$g['tmp_path']}/{$if}_setup.sh");

	// Clone wireless nic if needed.
	interface_wireless_clone($if, $wl);

	$fd_set = fopen("/tmp/{$if}_setup.sh","w");
	fwrite($fd_set, "#!/bin/sh\n");
	fwrite($fd_set, "# {$g['product_name']} wireless configuration script.\n\n");

	/* set values for /path/program */
	$hostapd = "/usr/sbin/hostapd";
	$wpa_supplicant = "/usr/sbin/wpa_supplicant";
	$ifconfig = "/sbin/ifconfig";
	$killall = "/usr/bin/killall";

	/* Set all wireless ifconfig variables (splitt up to get rid of needed checking) */

	$wlcmd = array();
	/* Make sure it's up */
	$wlcmd[] = "up";
	/* Set a/b/g standard */
	$wlcmd[] = "mode " . escapeshellarg($wlcfg['standard']);

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

				$fd = fopen("{$g['varetc_path']}/wpa_supplicant_{$if}.conf", "w");
				fwrite($fd, "{$wpa}");
				fclose($fd);

				mwexec(kill_wpasupplicant("{$if}"));
			}
			break;
		case 'hostap':
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
wpa_passphrase={$wlcfg['wpa']['passphrase']}
ieee8021x={$wlcfg['wpa']['ieee8021x']}
#Enable the next lines for preauth when roaming. Interface = wired or wireless interface talking to the AP you want to roam from/to
#rsn_preauth=1
#rsn_preauth_interfaces=eth0
EOD;

				$fd = fopen("{$g['varetc_path']}/hostapd_{$if}.conf", "w");
				fwrite($fd, "{$wpa}");
				fclose($fd);

			}
			mwexec(kill_hostapd("{$if}"));
			break;
		case 'adhoc':
			mwexec(kill_hostapd("{$if}"));
			mwexec(kill_wpasupplicant("{$if}"));
			break;
	}

	/*
	 *    all variables are set, lets start up everything
	 */

	/* set ack timers according to users preference (if he/she has any) */
	if($distance) {
		fwrite($fd_set, "# Enable ATH distance settings\n");
		fwrite($fd_set, "/sbin/athctrl.sh -i {$if} -d {$distance}\n");
	}

	$standard_no_turbo = str_replace(" Turbo", "", $standard);

	if (isset($wlcfg['wpa']['enable'])) {
		if ($wlcfg['mode'] == "bss") {
			fwrite($fd_set, "{$wpa_supplicant} -B -i {$if} -c {$g['varetc_path']}/wpa_supplicant_{$if}.conf\n");
		}
		if ($wlcfg['mode'] == "hostap") {
			fwrite($fd_set, "{$hostapd} -B {$g['varetc_path']}/hostapd_{$if}.conf\n");
		}
	}

	fclose($fd_set);
	conf_mount_ro();

	/* configure wireless */
	$wlcmd_args = implode(" ", $wlcmd);
	mwexec("/sbin/ifconfig {$if} $wlcmd_args", false);

	
	sleep(1);
	/* execute hostapd and wpa_supplicant if required in shell */
	mwexec("/bin/sh /tmp/{$if}_setup.sh");

	return 0;

}

function kill_hostapd($interface) {
	return "/bin/ps awwuxx | grep hostapd | grep $interface | awk '{ print \$2 }' | xargs kill\n";
}

function kill_wpasupplicant($interface) {
	return "/bin/ps awwuxx | grep wpa_supplicant | grep $interface | awk '{ print \$2 }' | xargs kill\n";
}

function find_dhclient_process($interface) {
	if($interface)
		$pid = `ps awwwux | grep dhclient | grep -v grep | grep {$interface} | awk '{ print \$2 }'`;
	return $pid;
}

function interface_configure($interface = "wan", $reloadall = false) {
	global $config, $g;
	global $interface_sn_arr_cache, $interface_ip_arr_cache;

	$wancfg = $config['interfaces'][$interface];

	$realif = get_real_interface($interface);

	if (!$g['booting']) {
		/* remove all IPv4 addresses */
		while (mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " -alias", true) == 0);
			interface_bring_down($interface);
	}

	/* wireless configuration? */
	if (is_array($wancfg['wireless']))
		interface_wireless_configure($realif, $wancfg, $wancfg['wireless']);

	if ($wancfg['spoofmac']) {
		mwexec("/sbin/ifconfig " . escapeshellarg($realif) .
			" link " . escapeshellarg($wancfg['spoofmac']));
	}  else {
		$mac = get_interface_mac(get_real_interface(get_real_interface($wancfg['if'])));
		if($mac == "ff:ff:ff:ff:ff:ff") {
			/*   this is not a valid mac address.  generate a
			 *   temporary mac address so the machine can get online.
			 */
			echo "Generating new MAC address.";
			$random_mac = generate_random_mac_address();
			mwexec("/sbin/ifconfig " . escapeshellarg(get_real_interface($wancfg['if'])) .
				" link " . escapeshellarg($random_mac));
			$wancfg['spoofmac'] = $random_mac;
			write_config();
			file_notice("MAC Address altered", "The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface {$realif} has been automatically replaced with {$random_mac}", "Interfaces");
		}
	}

	/* media */
	if ($wancfg['media'] || $wancfg['mediaopt']) {
		$cmd = "/sbin/ifconfig " . escapeshellarg(get_real_interface($wancfg['if']));
		if ($wancfg['media'])
			$cmd .= " media " . escapeshellarg($wancfg['media']);
		if ($wancfg['mediaopt'])
			$cmd .= " mediaopt " . escapeshellarg($wancfg['mediaopt']);
		mwexec($cmd);
	}

	/* invalidate interface/ip/sn cache */
	get_interface_arr(true);
	unset($interface_ip_arr_cache[$realif]);
	unset($interface_sn_arr_cache[$realif]);

	switch ($wancfg['ipaddr']) {

		case 'carpdev-dhcp':
			interface_carpdev_dhcp_configure($interface);
			break;
		case 'dhcp':
			interface_dhcp_configure($interface);
			break;

		case 'pppoe':
			interface_pppoe_configure($interface);
			break;

		case 'pptp':
			interface_pptp_configure($interface);
			break;

		default:
			if ($wancfg['ipaddr'] <> "" && $wancfg['subnet'] <> "") {
				if (isset($wancfg['ispointtopoint']) && $wancfg['pointtopoint']) {
					mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " " .
						escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']) .
						" " . escapeshellarg($wancfg['pointtopoint']) . " up");
				} else {
					if($wancfg['ipaddr'] && $wancfg['subnet'])
						mwexec("/sbin/ifconfig " . escapeshellarg($realif) .
							" " . escapeshellarg($wancfg['ipaddr'] . "/" . 
							$wancfg['subnet']));
				}
			}

			if (is_ipaddr($wancfg['gateway']))
				file_put_contents("/tmp/{$realif}_router", $wancfg['gateway']);
	}

	if(get_real_interface($wancfg['if']) && does_interface_exist($wancfg['if']))
		interfaces_bring_up($wancfg['if']);
	
	interface_reload_carps($realif);

	if (!$g['booting']) {
		if (link_interface_to_gre($interface)) {
			foreach ($config['gres']['gre'] as $gre)
				if ($gre['if'] == $interface)
					interface_gre_configure($gre);
		}
		if (link_interface_to_gif($interface)) {
                	foreach ($config['gifs']['gif'] as $gif)
				if ($gif['if'] == $interface)
                        		interface_gre_configure($gif);
        	}
		if (link_interface_to_bridge($interface)) {
			foreach ($config['bridges']['bridged'] as $bridge)
				if (stristr($bridge['members'], "{$interface}"))
					interface_bridge_configure($bridge);
		}

		if ($interface == "lan")
			/* make new hosts file */
			system_hosts_generate();

		if ($reloadall == true) {

			/* reconfigure static routes (kernel may have deleted them) */
			system_routing_configure();

			/* reload ipsec tunnels */
			vpn_ipsec_configure();

			/* update dyndns */
			services_dyndns_configure();

			/* force DNS update */
			services_dnsupdate_process();

			/* restart dnsmasq */
			services_dnsmasq_configure();

			/* reload captive portal */
			captiveportal_configure();

			/* set the reload filter dity flag */
			filter_configure();
		}
	}

	unmute_kernel_msgs();

	return 0;
}

function interface_carpdev_dhcp_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];
	$wanif = $wancfg['if'];
	/* bring wan interface up before starting dhclient */
	if($wanif)
		interfaces_bring_up($wanif);
	else 
		log_error("Could not bring wanif up in terface_carpdev_dhcp_configure()");

	return 0;
}

function interface_dhcp_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhclient_{$interface}.conf in interfaces_wan_dhcp_configure() for writing.\n");
		return 1;
	}

	if ($wancfg['dhcphostname']) {
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$wancfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$wancfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

	$wanif = get_real_interface($interface);

 	$dhclientconf = "";
	
	$dhclientconf .= <<<EOD
interface "{$wanif}" {
timeout 60;
retry 1;
select-timeout 0;
initial-interval 1;
	{$dhclientconf_hostname}
	script "/sbin/dhclient-script";
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

	$realwanif = $wancfg['if'];

	/* bring wan interface up before starting dhclient */
	if($realwanif)
		interfaces_bring_up($realwanif);
	else 
		log_error("Could not bring realwanif up in interface_dhcp_configure()");

	/* fire up dhclient */
	mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$wanif} >/tmp/{$wanif}_output >/tmp/{$wanif}_error_output");

	$fout = fopen("/tmp/ifconfig_{$wanif}","w");
	fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$wanif}");
	fclose($fout);

	return 0;
}

function interface_pppoe_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.conf in interface_pppoe_configure().\n");
		return 1;
	}

	$idle = 0;

	if (isset($wancfg['ondemand'])) {
		$ondemand = "enable";
		if ($wancfg['timeout'])
			$idle = $wancfg['timeout'];
	} else {
		$ondemand = "disable";
	}

	$mpdconf = <<<EOD
startup:
pppoeclient:

EOD;

	if ($interface == "wan")
		$realif = "pppoe0";
	else {
		// Here code assumes only that strings of form "opt#" will be passed.
		$realif = "pppoe" . substr($interface, 3); 
	}
	
	$mpdconf .= <<<EOD
	new -i {$realif} pppoeclient pppoeclient

EOD;
	if ($interface == "wan")
		$mpdconf .= <<<EOD
	set iface route default

EOD;
	
	$mpdconf .= <<<EOD
	set iface {$ondemand} on-demand
	set iface idle {$idle}
	set iface enable tcpmssfix
	set iface up-script /usr/local/sbin/ppp-linkup
	set iface down-script /usr/local/sbin/ppp-linkdown

EOD;

	if (isset($wancfg['ondemand'])) {
		if (isset($wancfg['local-ip']) && isset($wancfg['remote-ip'])) {
			$mpdconf .= <<<EOD
	set iface addrs {$wancfg['local-ip']} {$wancfg['remote-ip']}

EOD;
		} else {
			$mpdconf .= <<<EOD
	set iface addrs 192.0.2.112 192.0.2.113

EOD;
		}
	}

	$mpdconf .= <<<EOD
	set bundle disable multilink
	set auth authname "{$wancfg['pppoe_username']}"
	set auth password "{$wancfg['pppoe_password']}"
	set link keep-alive 10 60
	set link max-redial 0
	set link no acfcomp protocomp
	set link disable pap chap
	set link accept chap
	
EOD;
	if (empty($wancfg['mtu']))
		$mpdmtu = "1492";
	else 
		$mpdmtu = "{$wancfg['mtu']}";

	$mpdconf .= <<<EOD
	set link mtu {$mpdmtu}
	set ipcp yes vjcomp
	set ipcp ranges 0.0.0.0/0 0.0.0.0/0

EOD;

	if (isset($config['system']['dnsallowoverride'])) {
		$mpdconf .= <<<EOD
	set ipcp enable req-pri-dns

EOD;
	}

	if (!isset($wancfg['dnsnosec']) && isset($config['system']['dnsallowoverride'])) {
			$mpdconf .= <<<EOD
	set ipcp enable req-sec-dns

EOD;
	}
	
	$mpdconf .= <<<EOD
	open

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	/* generate mpd.links */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.links", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.links in interface_pppoe_configure().\n");
		return 1;
	}

	$mpdconf = <<<EOD
pppoeclient:
	set link type pppoe
	set pppoe iface {$wancfg['if']}
	set pppoe service "{$wancfg['provider']}"
	set pppoe enable originate
	set pppoe disable incoming

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	if(file_exists("{$g['varrun_path']}/pppoe_{$interface}.pid") and $g['booting']) {
		/* if we are booting and mpd has already been started then don't start again. */
	} else {
		/* if mpd is active, lets take it down */
		if(file_exists("{$g['varrun_path']}/pppoe_{$interface}.pid")) {
			killbypid("{$g['varrun_path']}/pppoe_{$interface}.pid");
			sleep(3);
		}

		/* Bring the parent interface up */
		if($wancfg['if'])
			interfaces_bring_up($wancfg['if']);
		else 
			log_error("Could not bring wancfg['if'] up in interface_pppoe_configure()");

		/* fire up mpd */
		mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']} -f mpd_{$interface}.conf -l mpd_{$interface}.links -p {$g['varrun_path']}/pppoe_{$interface}.pid pppoeclient");
	}

	/* sleep until wan is up - or 30 seconds, whichever comes first */
	for ($count = 0; $count < 30; $count++) {
		if(file_exists("{$g['tmp_path']}/{$realif}up")) {
			break;
		}
		sleep(1);
	}

	unlink_if_exists("{$g['tmp_path']}/{$realif}up");

	return 0;
}

function interface_pptp_configure($interface) {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.conf in interface_pptp_configure().\n");
		return 1;
	}

	$idle = 0;

	if (isset($wancfg['ondemand'])) {
		$ondemand = "enable";
		if ($wancfg['timeout'])
			$idle = $wancfg['timeout'];
	} else {
		$ondemand = "disable";
	}

	$mpdconf = <<<EOD
startup:
pptp:

EOD;

        if ($interface == "wan")
                $realif = "pptp0";
        else {
                // Here code assumes only that strings of form "opt#" will be passed.
                $realif = "pptp" . substr($interface, 3);
	}

        $mpdconf .= <<<EOD
        new -i {$realif} pptp pptp 

EOD;
        if ($interface == "wan")
                $mpdconf .= <<<EOD
        set iface route default

EOD;

        $mpdconf .= <<<EOD
	set iface {$ondemand} on-demand
	set iface idle {$idle}
	set iface up-script /usr/local/sbin/ppp-linkup
	set iface down-script /usr/local/sbin/ppp-linkdown

EOD;

	if (isset($wanfg['ondemand'])) {
		$mpdconf .= <<<EOD
	set iface addrs 10.0.0.1 10.0.0.2

EOD;
	}

	$mpdconf .= <<<EOD
	set bundle disable multilink
	set auth authname "{$wancfg['pptp_username']}"
	set auth password "{$wancfg['pptp_password']}"
	set bundle no noretry
	set link keep-alive 10 60
	set link max-redial 0
	set link no acfcomp protocomp
	set link disable pap chap
	set link accept chap
	set ipcp no vjcomp
	set ipcp ranges 0.0.0.0/0 0.0.0.0/0

EOD;
	if (isset($config['system']['dnsallowoverride'])) {
		$mpdconf .= <<<EOD
	set ipcp enable req-pri-dns

EOD;
	}

	$mpdconf .= <<<EOD
	open

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	/* generate mpd.links */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.links", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.links in interface_pptp_configure().\n");
		return 1;
	}

	$mpdconf = <<<EOD
pptp:
	set link type pptp
	set pptp enable originate outcall
	set pptp disable windowing
	set pptp self {$wancfg['local']}
	set pptp peer {$wancfg['remote']}

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	/* configure interface */
	if($wancfg['if'])
		mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
			escapeshellarg($wancfg['local'] . "/" . $wancfg['subnet']) . " up");
	else 
		log_error("Could not bring interface wancfg['if'] up in interface_pptp_configure()");
	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']} -f mpd_{$interface}.conf -l mpd_{$interface}.links -p {$g['varrun_path']}/pptp_{$interface}.pid pptp");

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

        if (stristr($interface, "pppoe")) {
                $index = substr($interface, 5);
                if (intval($index) > 0)
                        return "opt{$index}";
                else
                        return "wan";
        } else if (stristr($interface, "pptp")) {
                $index = substr($interface, 4);
                if (intval($index) > 0)
                        return "opt{$index}";
                else
                        return "wan";
	} else if (stristr($interface, "vip")) {
                $index = substr($interface, 3);
                $counter = 0;
                foreach ($config['virtualip']['vip'] as $vip) {
                        if ($vip['mode'] == "carpdev-dhcp" || $vip['mode'] == "carp")  {
                                if (intval($index) == $counter)
                                        return $vip['interface'];
                                $counter++;
                        }
                }
        } else if (stristr($interface, "carp")) {
                $index = substr($interface, 4);
                $counter = 0;
                foreach ($config['virtualip']['vip'] as $vip) {
                        if ($vip['mode'] == "carpdev-dhcp" || $vip['mode'] == "carp")  {
                                if (intval($index) == $counter)
                                        return $vip['interface'];
                                $counter++;
                        }
                }
        }

        /* if list */
        $ifdescrs = get_configured_interface_list(false, true);

        foreach ($ifdescrs as $if => $ifname) {
                if($config['interfaces'][$if]['if'] == $interface)
                        return $ifname;

                /* XXX: ermal - The 3 lines below are totally bogus code. */
                $int = interface_translate_type_to_real($if);
                if($ifname == $interface)
                        return $ifname;

                if($int == $interface)
                        return $ifname;
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
                                $ifdesc = "pptp";
                                break;
                case "pppoe":
                                $ifdesc = "pppoe";
                                break;
                case "openvpn":
                                $ifdesc = "OpenVPN";
                                break;
                case "enc0":
                        case "ipsec":
                                $ifdesc = "IPsec";
                                break;
        default:
                /* if list */
                $ifdescrs = get_configured_interface_with_descr(false, true);
                foreach ($ifdescrs as $if => $ifname) {
                                if ($if == $interface || $ifname == $interface)
                                        return $ifname;
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
 *  interface_translate_type_to_real($interface):
 *              returns the real hardware interface name for a friendly interface.  ie: wan
 */
function interface_translate_type_to_real($interface) {
        global $config;

        if ($config['interfaces'][$interface]['if'] <> "")
                return $config['interfaces'][$interface]['if'];
        else
		return $interface;
}

function get_real_interface($interface = "wan") {
    global $config;

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
	case "enc0":
		$wanif = "enc0";
		break;
	case "ppp":
		$wanif = "ppp";
		break;
	default:
		$iflist = get_configured_interface_with_descr(false, true);

		foreach ($iflist as $if => $ifdesc) {
			// If a real interface was alread passed simply
			// pass the real interface back.  This encourages
			// the usage of this function in more cases so that
			// we can combine logic for more flexibility.
			if($config['interfaces'][$if]['if'] == $interface) {
				if(does_interface_exist($interface)) {
					$wanif = $interface;
					break;
				}
			}

			if ($interface == $if || $interface == $ifdesc) {

			// PPP Support
			if($config['interfaces'][$if]['serialport']) {
				$dev = $config['interfaces'][$if]['serialport'];
				$ps = trim(`ps awux | grep -v grep | grep "$dev" | awk '{ print \$2 }'`);
				if($ps) {
					$int = trim(`/sbin/ifconfig | grep -v grep | grep -B2 "$ps" | head -n1 | cut -d: -f1`);
					if($int) {
						$wanif = $int;
						break;
					}
				}
			}

			$cfg = $config['interfaces'][$if];

			// Wireless cloned NIC support (FreeBSD 8+)
			// interface name format: $parentnic_wlanparentnic#
			// example: ath0_wlan0
			if(is_interface_wireless($interface)) {
				$interface_num = substr($cfg['if'], 3);
				$wanif = $cfg['if'] . "_wlan" . $interface_num;
				break;
			}

			if (empty($cfg['ipaddr'])) {
				$wanif = $cfg['if'];
				break;
			}

			switch ($cfg['ipaddr']) {
				case "carpdev-dhcp":
					$viparr = &$config['virtualip']['vip'];
					$counter = 0;
					if(is_array($viparr))
					foreach ($viparr as $vip) {
						if ($vip['mode'] == "carpdev-dhcp") {
							if($vip['interface'] == $if) {
								$wanif =  "carp{$counter}";
								break;
							}
							$counter++;
						} else if ($vip['mode'] = "carp") 
							$counter++;
					}
					break;
				case "pppoe": 
					if ($if == "wan")
						$wanif = "pppoe0";
					else
						$wanif = "pppoe" . substr($if,3);
					break;
				case "pptp": 
					if ($if == "wan")
						$wanif = "pptp0";
					else
						$wanif = "pptp" . substr($if, 3);
					break;
				default:
					$wanif = $cfg['if'];
					break;
				}
			
				break;
			}
		}
		break;
	}

    return $wanif;
}

function guess_interface_from_ip($ipaddress) {
        $ret = `/usr/bin/netstat -rn | /usr/bin/awk '/^{$ipaddress}/ {printf "%s", \$6}'`;
        if (empty($ret))
                return false;

        return $ret;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 */
function find_ip_interface($ip)
{
        /* if list */
        $ifdescrs = get_configured_interface_list();

        foreach ($ifdescrs as $ifdescr => $ifname) {
		if ($ip == get_interface_ip($ifname)) {
                	$int = get_real_interface($ifname);
			return $int;
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
	return $ints;
}

/*
 * find_carp_interface($ip): return the carp interface where an ip is defined
 */
function find_carp_interface($ip) {
	global $config;
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "carp" || $vip['mode'] == "carpdev") {
				$carp_ip = get_interface_ip($vip['interface']);
				$if = `ifconfig | grep '$ip' -B1 | head -n1 | cut -d: -f1`;
				if ($if)
					return $if;
			}
		}
	}
}

function link_carp_interface_to_parent($interface) {
        global $config;

        if ($interface == "")
                return;

        $carp_ip = get_interface_ip($interface);
        if (!is_ipaddr($carp_ip))
                return;

        /* if list */
        $ifdescrs = get_configured_interface_list();
        foreach ($ifdescrs as $ifdescr => $ifname) {
                $interfaceip = get_interface_ip($ifname);
                $subnet_bits = get_interface_subnet($ifname);
                $subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
                if(ip_in_subnet($carp_ip, "{$subnet_ip}/{$subnet_bits}"))
                        return $ifname;
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
                foreach ($config['virtualip']['vip'] as $vip) {
                        if ($vip['mode'] == "carp" || $vip['mode'] == "carpdev") {
                                $carp_ip = $vip['subnet'];
                                $carp_sn = $vip['subnet_bits'];
                                $carp_nw = gen_subnet($carp_ip, $carp_sn);
                                if (ip_in_subnet($ip, "{$carp_nw}/{$carp_sn}")) {
                                        if (!stristr($carp_ints, $carp_int))
                                                $carp_ints .= " {$carp_int}";
                                }
                        }
                }
        }

        return $carp_ints;
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

        if (is_array($config['bridges']['bridged']))
                foreach ($config['bridges']['bridged'] as $bridge)
                        if(stristr($bridge['members'], "{$int}"))
                                return "{$bridge['bridgeif']}";
}

function link_interface_to_gre($interface) {
        global $config;

        if (is_array($config['gres']['gre']))
                foreach ($config['gres']['gre'] as $gre)
                        if($gre['if'] == $interface)
                                return "{$gre['greif']}";
}

function link_interface_to_gif($interface) {
        global $config;

        if (is_array($config['gifs']['gif']))
                foreach ($config['gifs']['gif'] as $gif)
                        if($gif['if'] == $interface)
                                return "{$gif['gifif']}";
}

/*
 * find_interface_ip($interface): return the interface ip (first found)
 */
function find_interface_ip($interface, $flush = false)
{
	global $interface_ip_arr_cache;

	$interface = str_replace("\n", "", $interface);
	
	if (does_interface_exist($interface) == false)
		return;

	/* Setup IP cache */
	if (!isset($interface_ip_arr_cache[$interface]) or $flush) {
		$interface_ip_arr_cache[$interface] = `/sbin/ifconfig {$interface} | /usr/bin/grep -w "inet" | /usr/bin/cut -d" " -f 2| /usr/bin/head -1`;
		$interface_ip_arr_cache[$interface] = str_replace("\n", "", $interface_ip_arr_cache[$interface]);
	}

	return $interface_ip_arr_cache[$interface];
}

function find_interface_subnet($interface, $flush = false)
{
	global $interface_sn_arr_cache;

	$interface = str_replace("\n", "", $interface);
	if (does_interface_exist($interface) == false)
		return;

	if (!isset($interface_sn_arr_cache[$interface]) or $flush) {
		$interface_sn_arr_cache[$interface] = `/sbin/ifconfig {$interface} | /usr/bin/grep -w "inet" | /usr/bin/cut -d" " -f 4 | /usr/bin/head -1`;
		$interface_sn_arr_cache[$interface] = strlen(str_replace("0", "", base_convert(str_replace("\n", "", $interface_sn_arr_cache[$interface]),16, 2)));
        }

	return $interface_sn_arr_cache[$interface];
}

function get_interface_ip($interface = "wan")
{
	$realif = get_real_interface($interface);
	if (!$realif) {
		if (preg_match("/^carp/i", $interface))
			$realif = $interface;
		else if (preg_match("/^vip/i", $interface))
			$realif = $interface;
		else
			return null;
	}

	/* Do we really come here for these interfaces ?! */
	if (in_array($realif, array("pptp", "pppoe", "l2tp", "openvpn", "enc0" /* , "ppp" */)))
			return "";

	$curip = find_interface_ip($realif);
	if ($curip && is_ipaddr($curip) && ($curip != "0.0.0.0"))
		return $curip;

	return null;
}

function get_interface_subnet($interface = "wan")
{
	$realif = get_real_interface($interface);
	if (!$realif) {
                if (preg_match("/^carp/i", $interface))
                        $realif = $interface;
                else if (preg_match("/^vip/i", $interface))
                        $realif = $interface;
                else
                        return null;
        }

	/* Do we really come here for these interfaces ?! */
	if (in_array($realif, array("pptp", "pppoe", "l2tp", "openvpn", "enc0" /* , "ppp" */)))
		return "";

	$cursn = find_interface_subnet($realif);
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
		if($ifname['serialport']) {
			$ints[] = $ifdescr;
			continue;
		}
		switch ($ifname['ipaddr']) {
			case "dhcp":
			case "carpdev-dhcp":
			case "pppoe":
			case "pptp":
			$ints[] = $ifdescr;
			break;
			default:
			if ($ifname['pointtopoint'])
				$ints[] = $ifdescr;
			else if (!empty($ifname['gateway']))
				$ints[] = $ifdescr;
			break;
		}
	}
	return $ints;
}

/* return true if interface has a gateway */
function interface_has_gateway($friendly) {

        $friendly = strtolower($friendly);
        if (in_array($friendly, get_interfaces_with_gateway()))
                return true;

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
         * http://www.freebsd.org/cgi/man.cgi?query=altq&manpath=FreeBSD+7.2-current&format=html
         * Only the following drivers have ALTQ support
         */
	$capable = array("age", "ale", "an", "ath", "aue", "awi", "bce",
			"bfe", "bge", "dc", "de", "ed", "em", "ep", "fxp", "gem",
			"hme", "ipw", "iwi", "jme", "le", "msk", "mxge", "my", "nfe",
			"npe", "nve", "ral", "re", "rl", "rum", "sf", "sis", "sk",
			"ste", "stge", "txp", "udav", "ural", "vge", "vr", "wi", "xl",
			"ndis", "tun", "vlan", "pppoe", "pptp", "ng", "ppp");

        $int_family = preg_split("/[0-9]+/", $int);

        if (in_array($int_family[0], $capable))
                return true;
	else if (stristr($int_family, "vlan")) /* VLANs are name $parent.$vlan now */
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
                        $config['interfaces'][$friendly]['wireless'] = array();
                        return true;
                }
                unset($config['interfaces'][$friendly]['wireless']);
                return false;
        } else
                return true;
}

function get_wireless_modes($interface) {
	/* return wireless modes and channels */
	$wireless_modes = array();

	if(is_interface_wireless($interface)) {
		$cloned_interface = get_real_interface($interface);
		$wi = 1;
		$ifconfig = "/sbin/ifconfig";
		$awk = "/usr/bin/awk";
		$chan_list = "$ifconfig $cloned_interface list chan";
		$stack_list = "$awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
		$format_list = "$awk '{print \$5 \" \" \$6 \",\" \$1}'";

		$interface_channels = "";
		exec("$chan_list | $stack_list | sort -u | $format_list 2>&1", $interface_channels);
		$interface_channel_count = count($interface_channels);

		$c = 0;
		while ($c < $interface_channel_count)
		{
			$channel_line = explode(",", $interface_channels["$c"]);
			$wireless_mode = trim($channel_line[0]);
			$wireless_channel = trim($channel_line[1]);
			if(trim($wireless_mode) != "") {
				/* if we only have 11g also set 11b channels */
				if($wireless_mode == "11g") {
					$wireless_modes["11b"] = array();
				}
				$wireless_modes["$wireless_mode"]["$c"] = $wireless_channel;
			}
			$c++;
		}
	}
	return($wireless_modes);
}

/****f* interfaces/get_interface_mtu
 * NAME
 *   get_interface_mtu - Return the mtu of an interface
 * RESULT
 *   $tmp       - Returns the mtu of an interface
 ******/
function get_interface_mtu($interface) {
        $mtu = `/sbin/ifconfig {$interface} | /usr/bin/grep mtu | /usr/bin/cut -d" " -f6`;
        return $mtu;
}

function get_interface_mac($interface) {
	$mac = array();
        exec("/sbin/ifconfig {$interface} | /usr/bin/awk '/ether/ {print $2}'", $mac);
        if(is_macaddr($mac[0])) {
                return trim($mac[0]);
        } else {
                return "";
        }
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
function is_jumbo_capable($int) {
        global $g;

        $int_family = preg_split("/[0-9]+/", $int);

        if (in_array($int_family[0], $g['vlan_long_frame']))
                return true;
        else
                return false;
}

function setup_pppoe_reset_file($interface, $status) {
	define("CRON_PPPOE_CMD_FILE", "/conf/pppoe{$interface}restart");
	define("CRON_PPPOE_CMD", "#!/bin/sh\necho '<?php require(\"config.inc\"); require(\"interfaces.inc\"); interface_reconfigure({$interface}); ?>' | /usr/local/bin/php -q");
	if ($status == true) {
		if (!file_exists(CRON_PPPOE_CMD_FILE)) {
			file_put_contents(CRON_PPPOE_CMD_FILE, CRON_PPPOE_CMD);
			chmod(CRON_PPPOE_CMD_FILE, 0700);
		}	
	} else
		unlink_if_exists(CRON_PPPOE_CMD_FILE);
}

?>
