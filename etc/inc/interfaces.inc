<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2008 Ermal Luçi
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
*/

/* include all configuration functions */
require_once("functions.inc");
require_once("globals.inc");

function interfaces_bring_up($interface) {
	if(!$interface) {
		log_error("bring_interface_up was called but no variable defined.");
		log_error( "Backtrace: " . var_dump(debug_backtrace()) );
		return;
	}
	mwexec("/sbin/ifconfig " . escapeshellarg($interface) . " up");
}

function interfaces_loopback_configure() {
	mwexec("/sbin/ifconfig lo0 127.0.0.1");
	interfaces_bring_up("lo0");
	return 0;
}

function interfaces_vlan_configure() {
	global $config;
	$i = 0;
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if(empty($vlan['vlanif']))
				$vlan['vlanif'] = "vlan{$i}";
			/* XXX: Maybe we should report any errors?! */
			interface_vlan_configure($vlan['if'], $vlan['tag'], $vlan['vlanif']);
			$i++;
		}
	}
}

function interface_vlan_configure($if, $tag, $vlanif = "") {
        global $config, $g;

	if(empty($if)) {
		log_error("interface_vlan_confgure called with if undefined.");
		return;
	}

        /* make sure the parent interface is up */
	interfaces_bring_up($if);
	/* Since we are going to add vlan(4) try to enable all that hardware supports. */
	mwexec("/sbin/ifconfig {$if} vlanhwtag");
	mwexec("/sbin/ifconfig {$if} vlanmtu");

        if ($g['booting'] || !(empty($vlanif))) {
		/* before destroying, see if CARP is in use
		  If an interface containing an active CARP IP is destroyed,
		  the CARP interface will hang in INIT and must be destroyed
		  itself before it will function again (which causes a panic).
		  Trying to configure a CARP interface stuck in INIT will
		  cause a panic as well.  -cmb
		*/
		$carpcount = find_number_of_needed_carp_interfaces();
		/* will continue to destroy VLANs where CARP is not in use
		  to retain previous behavior and avoid regressions */
		if($carpcount < 1)
			mwexec("/sbin/ifconfig {$vlanif} destroy");
		mwexec("/sbin/ifconfig {$vlanif} create");
        } else 
		$vlanif = exec("/sbin/ifconfig vlan create");
	
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
                        mwexec("/sbin/ifconfig " . escapeshellarg($if) .
                                " link " . escapeshellarg($interfaces['spoofmac']));
                }
        }

        /* XXX: ermal -- for now leave it here at the moment it does not hurt. */
	interfaces_bring_up($if);

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
        global $config;

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

	mwexec("/sbin/ifconfig {$laggif} {$lagg['proto']}");

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

	foreach ($members as $member) {
		if (!array_key_exists($member, $checklist))
			continue;
		$realif =  escapeshellarg($member);
		/* make sure the parent interface is up */
		mwexec("/sbin/ifconfig {$realif} mtu {$smallermtu}");
		if($realif)
			interfaces_bring_up($realif);
		else 
			log_error("could not bring realif up -- foreach(memebers)");
		mwexec("/sbin/ifconfig {laggif} laggport {$realif}");
	}
	
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

	/* set up VLAN virtual interfaces */
	interfaces_vlan_configure();

	/* set up LAGG virtual interfaces */
	interfaces_lagg_configure();

	/* Set up PPP interfaces */
	interfaces_ppp_configure();

	$iflist = get_configured_interface_with_descr();
	$delayed_list = array();
	$bridge_list = array();
	
	foreach($iflist as $if => $ifname) {
		$realif = $config['interfaces'][$if]['if'];
		if(isset($realif['pppoe_reset'])) 
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
			interface_configure($if);
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
        if($g['debug'])
        	log_error("Configuring {$ifname}");

		interface_configure($if);

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

		interface_configure($if);

		if ($g['booting'])
			echo "done.\n";
	}

	/* bring up carp interfaces */
	interfaces_carp_configure();

	/* bring ip IP aliases */
	interfaces_ipalias_configure();

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
	sleep(1);
	interface_configure($interface);
}

function interface_bring_down($interface = "wan", $destroy = false) {
	global $config, $g;

	if (!isset($config['interfaces'][$interface]))
		return; 

	$ifcfg = $config['interfaces'][$interface];

	$realif = get_real_interface($interface);

	mwexec("/usr/sbin/arp -d -i {$realif} -a");

        /* remove interface up file if it exists */
        unlink_if_exists("{$g['tmp_path']}/{$interface}up");
        unlink_if_exists("{$g['vardb_path']}/{$interface}ip");
        //unlink_if_exists("{$g['varetc_path']}/nameservers.conf");

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
		 *	other interfaces! 
		 */
	case "dhcp":
        	$pid = find_dhclient_process($interface);
        	if($pid)
                	mwexec("kill {$pid}");
               	sleep(1);
                unlink_if_exists("{$g['varetc_path']}/dhclient_{$interface}.conf");
               	mwexec("/sbin/ifconfig {$realif} delete down");
		break;
	default:
		mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " delete down");
		break;
	}

	if ($destroy == true) {
		if (preg_match("/^tun|^ppp|^ovpn|^gif|^gre|^lagg|^bridge|^vlan/i", $realif))
                	mwexec("/sbin/ifconfig {$realif} destroy");
	}
	
	unlink_if_exists("/tmp/{$realif}_router");
	return;
}

function interfaces_ppp_configure() {
        global $config;

        $i = 0;
        if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
                foreach ($config['ppps']['ppp'] as $ppp) {
                        if(empty($ppp['pppif']))
                                $ppp['pppif'] = "ppp{$i}";
                        /* XXX: Maybe we should report any errors?! */
                        interface_ppp_configure($ppp);
                        $i++;
                }
        }
}

function interface_ppp_configure($ifcfg) {
	global $config, $g;
	
	/* Remove  the /dev/ from the device name. */
	$dev = substr($ifcfg['port'], 5);

	$realif  = $ifcfg['pppif'];
	if ($realif <> "") {
		$i = 0;
		while ($realif != "ppp{$i}")
			$i++;
		if(file_exists("/var/run/ppp{$i}.pid")) {
			$pid = trim(file_get_contents("/var/run/ppp{$i}.pid"));
			mwexec("kill {$pid}");
		}
	}
	
	if ($g['booting'] || $realif <> "") {
                mwexec("/sbin/ifconfig {$realif} destroy");
                mwexec("/sbin/ifconfig {$realif} create");
        } else
                $realif = exec("/sbin/ifconfig ppp create");


	$peerfile = "lcp-echo-failure 0\n";
	$peerfile .= "lcp-echo-interval 0\n";
	$peerfile .= "connect /etc/ppp/peers/ppp{$dev}-connect-chat\n";
	//$peerfile .= "disconnect /etc/ppp/peers/ppp{$dev}-disconnect-chat\n";
	$peerfile .= "{$ifcfg['port']} {$ifcfg['linespeed']}\n";
	$peerfile .= "crtscts\n";
	if ($ifcfg['connect-max-attempts'] <> "")
		$peerfile .= "connect-max-attempts {$ifcfg['connect-max-attempts']}";
	$peerfile .= "local\n";
	if ($ifcfg['localip'] <> "") {
		$peerfile .= ":{$ifcfg['gateway']}\n";
		$peerfile .= "{$ifcfg['localip']}:{$ifcfg['gateway']}";
	} else if ($ifcfg['gateway'] <> "") {
		$peerfile .= ":{$ifcfg['gateway']}\n";
		$peerfile .= "noipdefault\n";
	} else 
		$peerfile .= "noipdefault\n";
	$peerfile .= "ipcp-accept-local\n";
	$peerfile .= "novj\n";
	$peerfile .= "nobsdcomp\n";
	$peerfile .= "novjccomp\n";
	$peerfile .= "nopcomp\n";
	$peerfile .= "noaccomp\n";
	$peerfile .= "noauth\n";
	//$peerfile .= "nodetach\n";
	$peerfile .= "persist\n";
	$peerfile .= "debug\n";
	// KD - test
	//$peerfile .= "defaultroute\n";
	//$peerfile .= "nodetach\n";
	// KD - so I know where to look!
	$peerfile .= "# created by /etc/inc/interfaces.inc\n";

	// Added single quotes to some strings below:
	// the \rAT is *always* going to need it
	// and the phone number on a GSM connection ends in a # char
	// Kevin Dawson, 22 Jan 2008
	// Refer Andrew Curtis
			
	$chatfile = "#!/bin/sh\n";
	$chatfile .= "exec chat \\\n";
	$chatfile .= "TIMEOUT 5 \\\n";
	$chatfile .= "ECHO ON \\\n";
	$chatfile .= "ABORT '\\nBUSY\\r' \\\n";
	$chatfile .= "ABORT '\\nERROR\\r' \\\n";
	$chatfile .= "ABORT '\\nNO ANSWER\\r' \\\n";
	$chatfile .= "ABORT '\\nNO CARRIER\\r' \\\n";
	$chatfile .= "ABORT '\\nNO DIALTONE\\r' \\\n";
	$chatfile .= "ABORT '\\nRINGING\\r\\n\\r\\nRINGING\\r' \\\n";
	// KD
	$chatfile .= "'' '\\rAT' \\\n";
	$chatfile .= "TIMEOUT 12 \\\n";
	$chatfile .= "OK ATH \\\n";
	$chatfile .= "OK ATE1 \\\n";
	$chatfile .= "OK 'AT+CGDCONT=1,\"IP\",\"{$ifcfg['ap']}\"' \\\n";
	// KD
	$chatfile .= "OK 'ATD{$ifcfg['phone']}' \\\n";
	$chatfile .= "TIMEOUT 22 \\\n";
	if ($ifcfg['username'] <> "") {
		$chatfile .= "CONNECT \"\" TIMEOUT 10 \\\n";
		$chatfile .= "ogin:-\\r-ogin: {$ifcfg['username']}\\\n";
		$chatfile .= " TIMEOUT 5 sword: {$ifcfg['password']} \\\n";
	} else
		$chatfile .= "CONNECT \"\" \\\n";
	$chatfile .= "SAY \"\\nConnected.\"\n";

	config_lock();
	conf_mount_rw();
	safe_mkdir("/etc/ppp/peers", "0755");
	file_put_contents("/etc/ppp/peers/ppp_{$dev}", $peerfile);
	file_put_contents("/etc/ppp/peers/ppp{$dev}-connect-chat", $chatfile);
	chmod("/etc/ppp/peers/ppp{$dev}-connect-chat", 0755);
	conf_mount_ro();
	config_unlock();
	
	sleep(1);
	mwexec("/usr/sbin/pppd call ppp_{$dev}");

	return $realif;
}

function interfaces_carp_configure() {
	global $g, $config;
	$balanacing = "";
	$pfsyncinterface = "";
	$pfsyncenabled = "";
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_carp_configure() being called $mt\n";
	}
	// Prepare CmdCHAIN that will be used to execute commands.
	$cmdchain = new CmdCHAIN();	
	$carp_instances_counter = 0;
	$total_carp_interfaces_defined = find_number_of_created_carp_interfaces();
	/* destroy previous interfaces */
	for($x=0; $x<$total_carp_interfaces_defined; $x++) 
		$cmdchain->add("Delete CARP interface", "/sbin/ifconfig carp{$x} delete", false);
	if ($g['booting']) {
		echo "Configuring CARP interfaces...";
		mute_kernel_msgs();
	}
	/* suck in configuration items */
	if($config['installedpackages']['carpsettings']) 
		if($config['installedpackages']['carpsettings']['config']) {
		foreach($config['installedpackages']['carpsettings']['config'] as $carp) {
			$pfsyncenabled = $carp['pfsyncenabled'];
			$balanacing = $carp['balancing'];
			$pfsyncinterface = $carp['pfsyncinterface'];
			$pfsyncpeerip = $carp['pfsyncpeerip'];
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
	} else {
		$cmdchain->add("Enable CARP preemption", "/sbin/sysctl net.inet.carp.preempt=1", true);		
	}
	$cmdchain->add("Enable CARP logging", "/sbin/sysctl net.inet.carp.log=2", true);
	$carp_sync_int = convert_friendly_interface_to_real_interface_name($pfsyncinterface);
	if($g['booting']) {
		/*    install rules to alllow pfsync to sync up during boot
		 *    carp interfaces will remain down until the bootup sequence finishes
		 */
		exec("echo pass quick proto carp all keep state > /tmp/rules.boot");
		exec("echo pass quick proto pfsync all >> /tmp/rules.boot");
		exec("echo pass out quick from any to any keep state >> /tmp/rules.boot");
		exec("/sbin/pfctl -f /tmp/rules.boot");
	}
	/* setup pfsync interface */
	if($carp_sync_int and $pfsyncenabled) {
		if($pfsyncpeerip) {
			$cmdchain->add("Bring up pfsync0 syncpeer", "/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} syncpeer {$pfsyncpeerip} up", false);						
		} else {
			$cmdchain->add("Bring up pfsync0 syncdev", "/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} up", false);			
		}
	} else {
		$cmdchain->add("Bring up pfsync0", "/sbin/ifconfig pfsync0 syncdev lo0 up", false);						
	}
	//$fd = fopen("/tmp/carp.sh", "w");
	$viparr = &$config['virtualip']['vip'];
	if($config['virtualip']['vip']) {
		$cmdchain->add("Allow CARP.", "/sbin/sysctl net.inet.carp.allow=1", true);				
	} else {
		$viparr = array();
		$cmdchain->add("Disallow CARP.", "/sbin/sysctl net.inet.carp.allow=0", true);		
	}
	if(!$viparr and $config['interfaces']['wan']['ipaddr'] == "carpdev-dhcp") {
		/* no vips exist but we need to bring up carpdev... */
		$viparr_temp = array();
		$viparr_temp['advskew'] = "200";
		$viparr_temp['vhid'] = "1";
		$viparr_temp['mode'] = "carpdev-dhcp";
		$viparr_temp['password'] = $config['system']['hostname'] . "pfS";
		$viparr = $viparr_temp;
	}
	
	if($g['debug'])
		$cmdchain->setdebug(); // optional for verbose logging
	$cmdchain->execute();
	
	// Reset CmdCHAIN
	$cmdchain->clear();

	if(is_array($viparr))
	foreach ($viparr as $vip) {
		$vip_password = $vip['password'];
		$vip_password = str_replace(" ", "", $vip_password);
		if($vip['password'] != "")
                	$password = " pass \"" . $vip_password . "\"";
		$interface = filter_translate_type_to_real_interface($vip['interface']);
		$carpint = "carp" . $carp_instances_counter;

		switch ($vip['mode']) {
		case "carp":
			/* ensure CARP IP really exists prior to loading up */
			$found = false;
			$iflist = get_configured_interface_list();
			foreach($iflist as $if) {
				$ww_subnet_ip = $config['interfaces'][$if]['ipaddr'];
				$ww_subnet_bits = $config['interfaces'][$if]['subnet'];
				if (ip_in_subnet($vip['subnet'], gen_subnet($ww_subnet_ip, $ww_subnet_bits) . "/" . $ww_subnet_bits))
					$found = true;
			}
			if($found == false) {
				file_notice("CARP", "Sorry but we could not find a matching real interface subnet for the virtual IP address {$vip['subnet']}.", "Firewall: Virtual IP", "");
				continue;
			}
			/* ensure the interface containing the VIP really exists
			  prevents a panic if the interface is missing or invalid
			*/
			$realif = convert_friendly_interface_to_real_interface_name($vip['interface']);
			$intcount = exec("/sbin/ifconfig | grep $realif | wc -l | awk '{print $1}'");
			if($intcount < 1) {
				file_notice("CARP", "Interface specified for the virtual IP address {$vip['subnet']} does not exist. Skipping this VIP.", "Firewall: Virtual IP", "");
				continue;
			}
			/* create the carp interface and setup */
			$cmdchain->add("create CARP interface", "/sbin/ifconfig {$carpint} create", false);

			/* invalidate interface cache */
			get_interface_arr(true);
			$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
			$cmdchain->add("config CARP interface", "/sbin/ifconfig {$carpint} " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " vhid " . $vip['vhid'] . " advskew " . $vip['advskew'] . $password, false);
			$cmdchain->add("bring CARP interface UP", "/sbin/ifconfig {$carpint} up", false);
			$carp_instances_counter++;
			break;
		case "carpdev-dhcp":
			log_error("Found carpdev interface {$vip['interface']} on top of interface {$interface}");
			if(!empty($interface)) {
				
					$cmdchain->add("bring CARP parent interface UP", "/sbin/ifconfig {$interface} up", false);			
					$cmdchain->add("create CARP interface", "/sbin/ifconfig {$carpint} create", false);
					$cmdchain->add("bring CARP interface UP", "/sbin/ifconfig {$carpint} up", false);
					$cmdchain->add("assign CARP CarpDEV directive", "/sbin/ifconfig {$carpint} carpdev ". $interface . " vhid " . $vip['vhid'] . " advskew " . $vip['advskew'] . $password, false);
					$cmdchain->add("bring CARP interface UP", "/sbin/ifconfig {$carpint} up", false);

					/*
					 * XXX: BIG HACK but carpdev needs ip services active
					 * 	before even starting something as dhclient.
					 * 	I do not know if this is a feature or a bug
					 * 	but better than track it make it work ;) .
					 */
					//$fakeiptouse = "10.254.254." . ($carp_instances_counter+1);
					//$cmdchain->add("CarpDEV hack", "/sbin/ifconfig {$carpint} inet {$fakeiptouse}", false);

        			/* generate dhclient_wan.conf */
        			$fd = fopen("{$g['varetc_path']}/dhclient_{$carpint}.conf", "w");
        			if ($fd) {

        				$dhclientconf = "";

        				$dhclientconf .= <<<EOD
interface "{$carpint}" {
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
					$cmdchain->add("bring CARP dhclient UP", "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$carpint}.conf {$carpint} >/tmp/{$carpint}_output >/tmp/{$carpint}_error_output", false);
				} else {
					log_error("Error: cannot open dhclient_{$carpint}.conf in interfaces_carp_configure() for writing.\n");
					$cmdchain->add("bring CARP dhclient UP in background", "/sbin/dhclient -b {$carpint}", false);					
				}

        		$fout = fopen("/tmp/ifconfig_{$carpint}","w");
        		fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$carpint}.conf {$carpint}");
        		fclose($fout);

			} else {
				log_error("Could not determine CarpDEV parent interface for {$vip['descr']}.");
			}
			$carp_instances_counter++;
			break;
		}
	}

	if($g['debug'])
		$cmdchain->setdebug(); // optional for verbose logging
	// Execute built up command chain.
	$cmdchain->execute();	

	if ($g['booting']) {
		unmute_kernel_msgs();
		echo "done.\n";
	}

	/* update cache */
	if ($carp_instances_counter != find_number_of_created_carp_interfaces())
		find_number_of_created_carp_interfaces(true);

}

function interfaces_ipalias_configure() {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_ipalias_configure() being called $mt\n";
	}
	$viparr = &$config['virtualip']['vip'];
	if(is_array($viparr)) {
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "ipalias") {
				$if = get_real_interface($vip['interface']);
				mwexec("/sbin/ifconfig " . escapeshellarg($if) . " " . $vip['subnet'] . "/" . escapeshellarg($vip['subnet_bits']) . " alias"); 
			}
		}
	}
}

function interface_wireless_configure($if, $wlcfg) {
	global $config, $g;

	/*    open up a shell script that will be used to output the commands.
	 *    since wireless is changing a lot, these series of commands are fragile
     *    and will sometimes need to be verified by a operator by executing the command
     *    and returning the output of the command to the developers for inspection.  please
     *    do not change this routine from a shell script to individul exec commands.  -sullrich
	 */

	conf_mount_rw();

	unlink_if_exists("{$g['tmp_path']}/{$if}_setup.sh");

	$fd_set = fopen("/tmp/{$if}_setup.sh","w");
	fwrite($fd_set, "#!/bin/sh\n");
	fwrite($fd_set, "# {$g['product_name']} wireless configuration script.\n\n");

	fwrite($fd_set, "# enable shell debugging\n");
	fwrite($fd_set, "set -x\n");

	/* set values for /path/program */
	$hostapd = "/usr/sbin/hostapd";
	$wpa_supplicant = "/usr/sbin/wpa_supplicant";
	$ifconfig = "/sbin/ifconfig";
	$killall = "/usr/bin/killall";

	/* Set all wireless ifconfig variables (splitt up to get rid of needed checking) */

	/* Set a/b/g standard */
	$standard = "mode " . escapeshellarg($wlcfg['standard']);

	/* Set 802.11g protection mode */
	$protmode = "protmode " . escapeshellarg($wlcfg['protmode']);

	/* set wireless channel value */
	if(isset($wlcfg['channel']))
		if($wlcfg['channel'] == "0")
			$channel = "channel any";
		else
			$channel = "channel " . escapeshellarg($wlcfg['channel']);

	/* set Distance value */
	if($wlcfg['distance'])
		$distance = escapeshellarg($wlcfg['distance']);

	/* Set ssid */
	if($wlcfg['ssid'])
		$ssid = "ssid " . escapeshellarg($wlcfg['ssid']);

	/* Set wireless hostap mode */
	if ($wlcfg['mode'] == "hostap")
		$hostapmode = "mediaopt hostap";
	else
		$hostapmode = "-mediaopt hostap";

	/* Set wireless adhoc mode */
	if ($wlcfg['mode'] == "adhoc")
		$adhocmode = "mediaopt adhoc";
	else
		$adhocmode = "-mediaopt adhoc";

	/* Not neccesary to set BSS mode as this is default if adhoc and/or hostap is NOT set */

	/* handle hide ssid option */
	if(isset($wlcfg['hidessid']['enable']))
		$hidessid = "hidessid";
	else
		$hidessid = "-hidessid";

	/* handle pureg (802.11g) only option */
	if(isset($wlcfg['pureg']['enable']))
		$pureg = "mode 11g pureg";
	else
		$pureg = "-pureg";

	/* enable apbridge option */
	if(isset($wlcfg['apbridge']['enable']))
		$apbridge = "apbridge";
	else
		$apbridge = "-apbridge";

	/* handle turbo option */
	if(isset($wlcfg['turbo']['enable']))
		$turbo = "mediaopt turbo";
	else
		$turbo = "-mediaopt turbo";

	/* handle txpower setting */
	if($wlcfg['txpower'] <> "")
		$txpower = "txpower " . escapeshellarg($wlcfg['txpower']);

	/* handle wme option */
	if(isset($wlcfg['wme']['enable']))
		$wme = "wme";
	else
		$wme = "-wme";

	/* set up wep if enabled */
    if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
		if($wlcfg['wpa']['auth_algs'] == "1")
			$wepset .= "authmode open wepmode on ";
		else if($wlcfg['wpa']['auth_algs'] == "2")
			$wepset .= "authmode shared wepmode on ";
		else if($wlcfg['wpa']['auth_algs'] == "3")
			$wepset .= "authmode mixed wepmode on ";
		$i = 1;
		foreach ($wlcfg['wep']['key'] as $wepkey) {
			$wepset .= "wepkey " . escapeshellarg("{$i}:{$wepkey['value']}") . " ";
			if (isset($wepkey['txkey']))
				$wepset .= "weptxkey {$i} ";
			$i++;
		}
    } else {
    	$wepset .= "authmode open wepmode off ";
	}

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
psk="{$wlcfg['wpa']['passphrase']}"
pairwise={$wlcfg['wpa']['wpa_pairwise']}
group={$wlcfg['wpa']['wpa_pairwise']}
}
EOD;

				$fd = fopen("{$g['varetc_path']}/wpa_supplicant_{$if}.conf", "w");
				fwrite($fd, "{$wpa}");
				fclose($fd);

				fwrite($fd_set, kill_wpasupplicant($if));
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

				fwrite($fd_set, kill_hostapd($if));
			}
		break;

		case 'adhoc':
			fwrite($fd_set, kill_hostapd($if));
			fwrite($fd_set, kill_wpasupplicant($if));
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

	$settings = <<<EOD

{$ifconfig} {$if} down
{$ifconfig} {$if} {$standard_no_turbo}
{$ifconfig} {$if} {$channel}
{$ifconfig} {$if} {$turbo}
{$ifconfig} {$if} {$ssid}
{$ifconfig} {$if} {$hidessid}
{$ifconfig} {$if} {$adhocmode}
{$ifconfig} {$if} {$protmode}
{$ifconfig} {$if} {$pureg}
{$ifconfig} {$if} {$apbridge}
{$ifconfig} {$if} {$wme}
{$ifconfig} {$if} {$wepset}
{$ifconfig} {$if} {$txpower}
{$ifconfig} {$if} {$hostapmode}
{$ifconfig} {$if} up

EOD;

	/* write out above <<EOD stuff */
	fwrite($fd_set, $settings);

	if (isset($wlcfg['wpa']['enable'])) {
		if ($wlcfg['mode'] == "bss")
			fwrite($fd_set, "{$wpa_supplicant} -B -i {$if} -c {$g['varetc_path']}/wpa_supplicant_{$if}.conf\n");
		if ($wlcfg['mode'] == "hostap")
			fwrite($fd_set, "{$hostapd} -B {$g['varetc_path']}/hostapd_{$if}.conf\n");
	}

	fclose($fd_set);

	conf_mount_ro();

	/* execute commands now in shell */
	mwexec("/bin/sh /tmp/{$if}_setup.sh");
	sleep(2);
	// XXX: ermal - This seems like not needed!? 
	//mwexec("/bin/sh /tmp/{$if}_setup.sh");

	return 0;

}

function kill_hostapd($interface) {
	return "/bin/ps awwuxx | grep hostapd | grep $interface | awk '{ print \$2 }' | xargs kill\n";
}

function kill_wpasupplicant($interface) {
	return "/bin/ps awwuxx | grep wpa_supplicant | grep $interface | awk '{ print \$2 }' | xargs kill\n";
}

function find_dhclient_process($interface) {
	$realinterface = get_real_interface($interface);
	if($realinterface)
		$pid = `ps awwwux | grep dhclient | grep -v grep | grep {$realinterface} | awk '{ print \$2 }'`;
	return $pid;
}

function interface_configure($interface = "wan") {
	global $config, $g;
	global $interface_sn_arr_cache, $interface_ip_arr_cache;

	$wancfg = $config['interfaces'][$interface];

	$realif = get_real_interface($interface);

	if(!$g['booting']) {
		/* remove all addresses first */
		while (mwexec("/sbin/ifconfig " . escapeshellarg($realif) . " -alias", true) == 0);
		interface_bring_down($interface);
		mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " down");
	}
	/* wireless configuration? */
	if (is_array($wancfg['wireless']))
		interface_wireless_configure($realif, $wancfg['wireless']);

	if ($wancfg['spoofmac']) {
		mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) .
			" link " . escapeshellarg($wancfg['spoofmac']));
	}  else {
		$mac = get_interface_mac_address($wancfg['if']);
		if($mac == "ff:ff:ff:ff:ff:ff") {
			/*   this is not a valid mac address.  generate a
			 *   temporary mac address so the machine can get online.
			 */
			echo "Generating new MAC address.";
			$random_mac = generate_random_mac_address();
			mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) .
				" link " . escapeshellarg($random_mac));
			$wancfg['spoofmac'] = $random_mac;
			write_config();
			file_notice("MAC Address altered", "The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface {$realif} has been automatically replaced with {$random_mac}", "Interfaces");
		}
	}

	/* media */
	if ($wancfg['media'] || $wancfg['mediaopt']) {
		$cmd = "/sbin/ifconfig " . escapeshellarg($wancfg['if']);
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
	if($wancfg['if'])
		interfaces_bring_up($wancfg['if']);
	else 
		log_error("Could not bring wancfg['if'] up -- variable not defined in interface_configure()");
	
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

		/* XXX: Shouldn't the caller do this?! */
		/* XXX */
		if ($interface = "lan")
			/* make new hosts file */
                	system_hosts_generate();

		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* set the reload filter dity flag */
		filter_configure();

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

	$relwanif = $wancfg['if'];

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

function interface_pppoe_configure($interface = "wan") 
{
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
		if(file_exists("{$g['tmp_path']}/{$interface}up")) {
			break;
		}
		sleep(1);
	}

	unlink_if_exists("{$g['tmp_path']}/{$interface}up");

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
	set bundle authname "{$wancfg['pptp_username']}"
	set bundle password "{$wancfg['pptp_password']}"
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
 
/* XXX: stub for code that references the old functions(mostly packages) */
function get_real_wan_interface($interface = "wan") {
	return get_real_interface($interface);
}
function get_current_wan_address($interface = "wan") {
	return get_interface_ip($interface);
}

function get_real_interface($interface = "wan") {
    global $config;

	$wanif = $interface;

	switch ($interface) {
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
	/* XXX: dial in support?!
	case "ppp":
		$wanif = "ppp";
		break;
	*/
	default:
		$iflist = get_configured_interface_with_descr(false, true);

		foreach ($iflist as $if => $ifdesc) {
			if ($interface == $if || $interface == $ifdesc) {

			$cfg = $config['interfaces'][$if];

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

function get_interface_ip($interface = "wan") {
	$realif = get_real_interface($interface);
	/* Do we really come here for these interfaces ?! */
	if (in_array($realif, array("pptp", "pppoe", "openvpn", "enc0" /* , "ppp" */)))
			return "";

	$curip = find_interface_ip($realif);
	if ($curip && is_ipaddr($curip) && ($curip != "0.0.0.0"))
		return $curip;

	return null;
}

function get_interface_subnet($interface = "wan") {
	$realif = get_real_interface($interface);
	/* Do we really come here for these interfaces ?! */
	if (in_array($realif, array("pptp", "pppoe", "openvpn", "enc0" /* , "ppp" */)))
		return "";

	$cursn = find_interface_subnet($realif);
	if (!empty($cursn))
		return $cursn;

	return null;
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
         * http://www.freebsd.org/cgi/man.cgi?query=altq&manpath=FreeBSD+6.0-current&format=html
         * Only the following drivers have ALTQ support
         */
        $capable = array("an", "ath", "awi", "bfe", "bge", "dc", "de", "ed",
			"em", "fxp", "hme", "le", "nve", "re", "rl", "ndis", "sf", "sis", "sk",
			"tun", "vr", "wi", "xl", "vlan", "ste", "aue", "bce", "ep", "gem", "ipw", 
			"iwi", "msk", "mxge", "my", "nfe", "npe", "ral", "rum", "stge", "udav", 
			"ural", "pppoe", "pptp", "ng", "ppp");

        $int_family = preg_split("/[0-9]+/", $int);

        if (in_array($int_family[0], $capable))
                return true;
        else
                return false;
}

function get_wireless_modes($interface) {
	/* return wireless modes and channels */
	$wireless_modes = array();

	if(is_interface_wireless($interface)) {
		$wi = 1;
		$ifconfig = "/sbin/ifconfig";
		$awk = "/usr/bin/awk";
		$chan_list = "$ifconfig $interface list chan";
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

function get_interface_mac($interface) {
	/* build interface list with netstat */
	$linkinfo = "";
	exec("/usr/bin/netstat -I $interface -nW -f link", $linkinfo);
	array_shift($linkinfo);
	$alink = preg_split("/\s+/", $linkinfo[0]);
	$mac = chop($alink[3]);
	return $mac;
}

function setup_pppoe_reset_file($interface, $status) {
	define("CRON_PPPOE_CMD_FILE", "/conf/pppoe{$interface}restart");
	define("CRON_PPPOE_CMD", "#!/bin/sh\necho '<?php require(\"interfaces.inc\"); interface_reconfigure(\"$interface\"); services_dyndns_reset(\"$interface\"); filter_configure_sync(); ?>' | /usr/local/bin/php -q");
	if($status == true) {
		if(!file_exists(CRON_PPPOE_CMD_FILE)) {
			file_put_contents(CRON_PPPOE_CMD_FILE, CRON_PPPOE_CMD);
			chmod(CRON_PPPOE_CMD_FILE, 0700);
		}	
	} else {
		unlink_if_exists(CRON_PPPOE_CMD_FILE);
	}
}

?>
