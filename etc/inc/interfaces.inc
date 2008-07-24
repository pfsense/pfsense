<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004-2006 Scott Ullrich
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

function interfaces_loopback_configure() {
	mwexec("/sbin/ifconfig lo0 127.0.0.1");
	mwexec("/sbin/ifconfig lo0 up");
	return 0;
}

function interfaces_vlan_configure() {
        global $config;

	$i = 0;
        if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
                foreach ($config['vlans']['vlan'] as $vlan) {
			if(empty($vlan['vlanif'])) {
				$vlan['vlanif'] = "vlan{$i}";
			}
                        /* XXX: Maybe we should report any errors?! */
                        interface_vlan_configure($vlan['if'], $vlan['tag'], $vlan['vlanif']);
			$i++;
		}
        }
}

function interface_vlan_configure($if, $tag, $vlanif = "") {
        global $config, $g;

        /* devices with native VLAN support */
        $vlan_native_supp = $g['vlan_native_supp'];

        /* devices with long frame support */
        $vlan_long_frame = $g['vlan_long_frame'];

        /* make sure the parent interface is up */
        mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up");

        if ($g['booting'] || !(empty($vlanif))) {
		mwexec("/sbin/ifconfig {$vlanif} destroy");
		mwexec("/sbin/ifconfig {$vlanif} create");
        } else 
		$vlanif = exec("/sbin/ifconfig vlan create");
	
        mwexec("/sbin/ifconfig {$vlanif} vlan " .
                escapeshellarg($tag) . " vlandev " .
                escapeshellarg($if));

        /* get driver name */
        for ($j = 0; $j < strlen($if); $j++) {
                if ($if[$j] >= '0' && $if[$j] <= '9')
                        break;
	}
        $drvname = substr($if, 0, $j);

        if (in_array($drvname, $vlan_native_supp))
		mwexec("/sbin/ifconfig {$if} vlanhwtag");
        else if (in_array($drvname, $vlan_long_frame))
                mwexec("/sbin/ifconfig {$if} vlanmtu 1500");

	mwexec("/sbin/ifconfig {$vlanif} up");

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
        mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up");

        return $vlanif;
}

function interfaces_gre_configure() {
        global $config;

        $i = 0;
        if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
                foreach ($config['gres']['gre'] as $gre) {
                        if(empty($gre['greif'])) {
                                $gre['greif'] = "gre{$i}";
                        }
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

	$realif = get_real_wan_interface($gre['if']);
	$realifip = get_current_wan_address($gre['if']);

        /* make sure the parent interface is up */
        mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up");

        if ($g['booting'] || !(empty($gre['greif']))) {
                mwexec("/sbin/ifconfig {$gre['greif']} destroy");
                mwexec("/sbin/ifconfig {$gre['greif']} create");
		$greif = $gre['greif'];
        } else
                $greif = exec("/sbin/ifconfig gre create");

	/* Do not change the order here for more see gre(4) NOTES section. */
	mwexec("/sbin/ifconfig {$greif} tunnel {$realifip} {$gre['remote-addr']}");
	mwexec("/sbin/ifconfig {$greif} {$gre['tunnel-local-addr']} {$gre['tunnel-remote-addr']} netmask 255.255.255.255 ");
	if (isset($gre['link0']) && $gre['link0'])
		mwexec("/sbin/ifconfig {$greif} link0");
        if (isset($gre['link1']) && $gre['link1'])
                mwexec("/sbin/ifconfig {$greif} link1");
        if (isset($gre['link2']) && $gre['link2'])
                mwexec("/sbin/ifconfig {$greif} link2");

	mwexec("/sbin/ifconfig {$greif} up");
	mwexec("/sbin/route add {$gre['remote-addr']}/{$gre['tunnel-remote-net']} {$realifip}");

	return $greif;
}

function interfaces_gif_configure() {
        global $config;

        $i = 0;
        if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
                foreach ($config['gifs']['gif'] as $gif) {
                        if(empty($gif['gifif'])) {
                                $gre['gifif'] = "gif{$i}";
                        }
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

        $realif = get_real_wan_interface($gif['if']);
        $realifip = get_current_wan_address($gif['if']);

        /* make sure the parent interface is up */
        mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up");

        if ($g['booting'] || !(empty($gif['gifif']))) {
                mwexec("/sbin/ifconfig {$gif['gifif']} destroy");
                mwexec("/sbin/ifconfig {$gif['gifif']} create");
                $gifif = $gif['gifif'];
        } else
                $gifif = exec("/sbin/ifconfig gif create");

        /* Do not change the order here for more see gif(4) NOTES section. */
        mwexec("/sbin/ifconfig {$gifif} tunnel {$realifip} {$gif['remote-addr']}");
        mwexec("/sbin/ifconfig {$gifif} {$gif['tunnel-local-addr']} {$gif['tunnel-remote-addr']} netmask 255.255.255.255 ");
        if (isset($gif['link0']) && $gif['link0'])
                mwexec("/sbin/ifconfig {$gifif} link0");
        if (isset($gif['link1']) && $gif['link1'])
                mwexec("/sbin/ifconfig {$gifif} link1");

        mwexec("/sbin/ifconfig {$gifif} up");
        mwexec("/sbin/route add {$gif['remote-addr']}/{$gif['tunnel-remote-net']} {$realifip}");

        return $gifif;
}

function interfaces_lan_configure() {
	global $config, $g;

	$bridges_total = get_next_available_bridge_interface();

	$lancfg = $config['interfaces']['lan'];

	/* if user has removed ip address, clear it*/
	if($lancfg['ipaddr'] == "")
		mwexec("/sbin/ifconfig {$lancfg['if']} delete");

	/* wireless configuration? */
	if (is_array($lancfg['wireless']))
		interfaces_wireless_configure($lancfg['if'], $lancfg['wireless']);

	/* MAC spoofing? */
	if ($lancfg['spoofmac']) {
		mwexec("/sbin/ifconfig " . escapeshellarg($lancfg['if']) .
			" link " . escapeshellarg($lancfg['spoofmac']));
	} else {
		$mac = get_interface_mac_address($lancfg['if']);
		if($mac == "ff:ff:ff:ff:ff:ff") {
			/*   this is not a valid mac address.  generate a
			 *   temporary mac address so the machine can get online.
			 */
			echo "Generating new MAC address.";
			$random_mac = generate_random_mac_address();
			mwexec("/sbin/ifconfig " . escapeshellarg($lancfg['if']) .
				" link " . escapeshellarg($random_mac));
			$lancfg['spoofmac'] = $random_mac;
			write_config();
			file_notice("MAC Address altered", "The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface {$lancfg['if']} has been automatically replaced with {$random_mac}", "Interfaces");
		}
	}

	/* bridged? */

	if ($lancfg['bridge']) {
		/* use open/netBSD style bridge */
		mwexec("/sbin/ifconfig bridge{$bridges_total} create");

		/* force all bridged interfaces to use same mtu */
		$bridgedif = get_real_wan_interface($lancfg['bridge']);
		$mtu = get_interface_mtu($bridgedif);
		mwexec("/sbin/ifconfig {$lancfg['if']} mtu {$mtu}");
		mwexec("/sbin/ifconfig {$bridgeidf} mtu {$mtu}");

		/* assign items to a bridge */
		mwexec("/sbin/ifconfig bridge{$bridges_total} addm {$lancfg['if']} addm {$bridgedif}");

		if(!is_interface_wireless($lancfg['if']) and
		   !is_interface_wireless($bridgedif))
			mwexec("/sbin/ifconfig bridge{$bridges_total} stp {$bridgedif} stp {$lancfg['if']}");

		/* log commands run for debugging in /tmp/ */
		$fd = fopen("{$g['tmp_path']}/bridge_config_{$lancfg['if']}", "w");
		fwrite($fd, "/sbin/ifconfig {$lancfg['if']} mtu {$mtu}\n");
		fwrite($fd, "/sbin/ifconfig {$bridgedif} mtu {$mtu}\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} create\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} addm {$lancfg['if']} addm {$bridgedif}\n");
		if(!is_interface_wireless($lancfg['if']) and
		   !is_interface_wireless($bridgedif))		
			fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} stp {$lancfg['if']} stp {$bridgedif}\n");
		fclose($fd);

		/* bring up interfaces */
		mwexec("/sbin/ifconfig bridge{$bridges_total} down");
		usleep(100);
		mwexec("/sbin/ifconfig {$bridgedif} up");
		usleep(5);
		mwexec("/sbin/ifconfig {$lancfg['if']} up");
		usleep(5);
		mwexec("/sbin/ifconfig bridge{$bridges_total} up");

		$bridges_total++;
		/* update cache */
		if ($bridges_total != find_number_of_created_bridges())
			find_number_of_created_bridges(true);
	}

	/* media */
	if ($lancfg['media'] || $lancfg['mediaopt']) {
		$cmd = "/sbin/ifconfig " . escapeshellarg($lancfg['if']);
		if ($lancfg['media'])
			$cmd .= " media " . escapeshellarg($lancfg['media']);
		if ($lancfg['mediaopt'])
			$cmd .= " mediaopt " . escapeshellarg($lancfg['mediaopt']);
		mwexec($cmd);
	}

	mwexec("/sbin/ifconfig " . escapeshellarg($lancfg['if']) . " " .
		escapeshellarg($lancfg['ipaddr'] . "/" . $lancfg['subnet']));

	if (!$g['booting']) {
		/* make new hosts file */
		system_hosts_generate();

		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");

		/* reload IPsec tunnels */
		vpn_ipsec_configure();

		/* reload dhcpd (gateway may have changed) */
		services_dhcpd_configure();

		/* reload dnsmasq */
		services_dnsmasq_configure();

		/* reload captive portal */
		captiveportal_configure();

	}

	return 0;
}

function interfaces_configure() {
	global $g;

	/* XXX: unify with wan when pppoe/pptp fixups are done. */
	/* optional interface if list */
	$iflist = get_configured_interface_with_descr();
	
	foreach($iflist as $if => $ifname) {
		if ($g['booting']) 
			echo "Configuring {$ifname} interface...";
		if($debug)
 	               log_error("Configuring {$ifname}");

		if ($if == "lan")
			interfaces_lan_configure();
		else
			interfaces_wan_configure($if);
		if ($g['booting']) 
                	echo "done.\n";
	}

	if (!$g['booting']) {
		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* reload IPsec tunnels */
		vpn_ipsec_configure();

		/* reload dhcpd (interface enabled/disabled/bridged status may have changed) */
		services_dhcpd_configure();

		/* restart dnsmasq */
		services_dnsmasq_configure();

		/* reload captive portal */
		captiveportal_configure();

		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");
	}

	return 0;
}

function interface_bring_down($interface) {
	global $config;

	$cfg = $config['interfaces'][$interface];
	mwexec("/sbin/ifconfig " . escapeshellarg($cfg['if']) . " delete down");
}

function interfaces_ppp_configure_if($ifcfg) {
	global $config;
	
	if(file_exists("/var/run/ppp0.pid")) {
		$pid = file_get_contents("/var/run/ppp0.pid");
		mwexec('kill $pid');
	}
	
	mwexec("/sbin/ifconfig ppp0 down destroy");

	$peerfile = "lcp-echo-failure 0\n";
	$peerfile .= "lcp-echo-interval 0\n";
	$peerfile .= "connect /etc/ppp/peers/ppp0-connect-chat\n";
	//$peerfile .= "disconnect /etc/ppp/peers/ppp0-disconnect-chat\n";
	$peerfile .= "/dev/{$ifcfg['serialport']}\n";
	$peerfile .= "crtscts\n";
	$peerfile .= "local\n";
	$peerfile .= ":{$ifcfg['gateway']}\n";
	$peerfile .= "noipdefault\n";
	$peerfile .= "ipcp-accept-local\n";
	$peerfile .= "novj\n";
	$peerfile .= "nobsdcomp\n";
	$peerfile .= "novjccomp\n";
	$peerfile .= "nopcomp\n";
	$peerfile .= "noaccomp\n";
	$peerfile .= "noauth\n";
	$peerfile .= "persist\n";
	$peerfile .= "debug\n";
	// KD - test
	//$peerfile .= "defaultroute\n";
	//$peerfile .= "nodetach\n";
	// KD - so I know where to look!
	$peerfile .= "# created by /etc/inc/interfaces.inc\n";
	file_put_contents("/etc/ppp/peers/ppp0", $peerfile);

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
	$chatfile .= "CONNECT \"\" \\\n";
	$chatfile .= "SAY \"\\nConnected.\"\n";
	file_put_contents("/etc/ppp/peers/ppp0-connect-chat", $chatfile);
	chmod("/etc/ppp/peers/ppp0-connect-chat", 0755);
	mwexec("/sbin/ifconfig ppp0 create");
	return 0;
}

function interfaces_carp_configure() {
	global $g, $config, $debugging;
	$balanacing = "";
	$pfsyncinterface = "";
	$pfsyncenabled = "";
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_carp_configure() being called $mt\n";
	}
	$carp_instances_counter = 0;
	$total_carp_interfaces_defined = find_number_of_created_carp_interfaces();
	/* destroy previous interfaces */
	for($x=0; $x<$total_carp_interfaces_defined; $x++)
		mwexec("/sbin/ifconfig carp{$x} delete");
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
	mwexec("/sbin/sysctl net.inet.carp.allow=1");	
	if($balanacing) {
		mwexec("/sbin/sysctl net.inet.carp.arpbalance=1");
		mwexec("/sbin/sysctl net.inet.carp.preempt=0");
	} else {
		mwexec("/sbin/sysctl net.inet.carp.preempt=1");
	}
	mwexec("/sbin/sysctl net.inet.carp.log=2"); // tell syslog about state changes
	$carp_sync_int = convert_friendly_interface_to_real_interface_name($pfsyncinterface);
	if($g['booting']) {
		/*    install rules to alllow pfsync to sync up during boot
		 *    carp interfaces will remain down until the bootup sequence finishes
		 */
		exec("echo pass quick proto carp all keep state > /tmp/rules.boot");
		exec("echo pass quick proto pfsync all >> /tmp/rules.boot");
		exec("echo pass out quick from any to any keep state >> /tmp/rules.boot");
//		exec("/sbin/pfctl -f /tmp/rules.boot");
	}
	/* setup pfsync interface */
	if($carp_sync_int and $pfsyncenabled) {
		if($pfsyncpeerip) {
			mwexec("/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} syncpeer {$pfsyncpeerip} up");
		} else {
			mwexec("/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} up");
		}
	} else {
		mwexec("/sbin/ifconfig pfsync0 syncdev lo0 up");
	}
	//$fd = fopen("/tmp/carp.sh", "w");
	$viparr = &$config['virtualip']['vip'];
	if($config['virtualip']['vip']) {
		mwexec("/sbin/sysctl net.inet.carp.allow=1");
	} else {
		$viparr = array();
		mwexec("/sbin/sysctl net.inet.carp.allow=0");
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
			/* create the carp interface and setup */
			mwexec("/sbin/ifconfig {$carpint} create");

			/* invalidate interface cache */
			get_interface_arr(true);
			$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
			mwexec("/sbin/ifconfig {$carpint} " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " broadcast " . $broadcast_address . " vhid " . $vip['vhid'] . " advskew " . $vip['advskew'] . $password);
			mwexec("/sbin/ifconfig {$carpint} up");
			$carp_instances_counter++;
			usleep(10);
			break;
		case "carpdev-dhcp":
			log_error("Found carpdev interface {$vip['interface']} on top of interface {$interface}");
			if(!empty($interface)) {
				mwexec("/sbin/ifconfig {$interface} up");
				mwexec("/sbin/ifconfig {$carpint} create");
				sleep(3);
				mwexec("/sbin/ifconfig {$carpint} carpdev ". $interface . " vhid " . $vip['vhid'] . " advskew " . $vip['advskew'] . $password);
				mwexec("/sbin/ifconfig {$carpint} up");
				/*
				 * XXX: BIG HACK but carpdev needs ip services active
				 * 	before even starting something as dhclient.
				 * 	I do not know if this is a feature or a bug
				 * 	but better than track it make it work ;) .
				 */
				$fakeiptouse = "10.254.254." . ($carp_instances_counter+1);
				mwexec("/sbin/ifconfig {$carpint} inet {$fakeiptouse}");

				sleep(3);
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
        			mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$carpint}.conf {$carpint} >/tmp/{$carpint}_output >/tmp/{$carpint}_error_output");
				} else {
                                        log_error("Error: cannot open dhclient_{$carpint}.conf in interfaces_carp_configure() for writing.\n");
					mwexec("/sbin/dhclient -b {$carpint}");
				}


        			$fout = fopen("/tmp/ifconfig_{$carpint}","w");
        			fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$carpint}.conf {$carpint}");

        			fclose($fout);

			} else {
				log_error("Could not determine CarpDEV parent interface for {$vip['descr']}.");
			}
			$carp_instances_counter++;
			usleep(10);
			break;
		}
	}

	if ($g['booting']) {
		unmute_kernel_msgs();
		echo "done.\n";
	}

	/* update cache */
	if ($carp_instances_counter != find_number_of_created_carp_interfaces())
		find_number_of_created_carp_interfaces(true);
}

function interfaces_ipalias_configure() {
	global $g, $config, $debugging;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_ipalias_configure() being called $mt\n";
	}
	$viparr = &$config['virtualip']['vip'];
	if(is_array($viparr)) {
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "ipalias") {
				$if = $vip['interface'];
				mwexec("/sbin/ifconfig " . escapeshellarg($config['interfaces'][$if]['if']) . " " . $vip['subnet'] . "/" . escapeshellarg($vip['subnet_bits']) . " alias"); 
			}
		}
	}
}

function interfaces_wireless_configure($if, $wlcfg) {
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
{$ifconfig} {$if} {$hostapmode}
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
	if(filter_translate_type_to_real_interface($interface) <> "")
        	$realinterface = filter_translate_type_to_real_interface($interface);
	if($realinterface)
		$pid = `ps awwwux | grep dhclient | grep -v grep | grep {$realinterface} | awk '{ print \$2 }'`;
	return $pid;
}

function interfaces_wan_configure($interface = "wan") {
	global $config, $g, $bridges_total;

	$wancfg = $config['interfaces'][$interface];

	$realif = get_real_wan_interface($interface);

	if(file_exists("/tmp/{$wancfg['if']}_router")) 
		unlink("/tmp/{$wancfg['if']}_router");

	if(!$g['booting']) {
		mute_kernel_msgs();

		/* find dhclient process for wan and kill it */
		killbypid(find_dhclient_process($interface));

		/* remove wanup file if it exists */
		unlink_if_exists("{$g['tmp_path']}/{$interface}up");

		/* kill PPPoE client (mpd) */
		killbypid("{$g['varrun_path']}/pppoe_{$interface}.pid");
		killbypid("{$g['varrun_path']}/pptp_{$interface}.pid");

		/* wait for processes to die */
		sleep(3);

		unlink_if_exists("{$g['varetc_path']}/dhclient_{$interface}.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd_{$interface}.links");
		unlink_if_exists("{$g['vardb_path']}/{$interface}ip");
		unlink_if_exists("{$g['varetc_path']}/nameservers.conf");
	}

	/* remove all addresses first */
	while (mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " -alias") == 0);
	mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " down");

	/* wireless configuration? */
	if (is_array($wancfg['wireless']))
		interfaces_wireless_configure($wancfg['if'], $wancfg['wireless']);

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
			file_notice("MAC Address altered", "The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface {$wancfg['if']} has been automatically replaced with {$random_mac}", "Interfaces");
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

	switch ($wancfg['ipaddr']) {

		case 'carpdev-dhcp':
			interfaces_wan_carpdev_dhcp_configure($interface);
			break;
		case 'dhcp':
			interfaces_wan_dhcp_configure($interface);
			break;

		case 'pppoe':
			interfaces_wan_pppoe_configure($interface);
			break;

		case 'pptp':
			interfaces_wan_pptp_configure($interface);
			break;

		case 'bigpond':
			/* just configure DHCP for now; fire up bpalogin when we've got the lease */
			interfaces_wan_dhcp_configure($interface);
			break;

		default:
			if (isset($wancfg['ispointtopoint']) && $wancfg['pointtopoint']) {
				mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
					escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']) .
					" " . escapeshellarg($wancfg['pointtopoint']) . " up");
			} else {
				mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
					escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']));
			}

			if (is_ipaddr($wancfg['gateway']))
				system("echo " . $wancfg['gateway'] . " > /tmp/" . $wancfg['if'] . "_router");

			/* resync pf (done automatically for DHCP/PPPoE/PPTP) */
			/* XXX: shouldn't the caller do this?! */
			filter_configure();
	}

	if ($wancfg['bridge']) {
		/* use open/netBSD style bridge */
		mwexec("/sbin/ifconfig bridge{$bridges_total} create");

		/* invalidate interface cache */
		get_interface_arr(true);

		/* force all bridged interfaces to use same mtu */
		$bridgedif = get_real_wan_interface($wancfg['bridge']);
		$mtu = get_interface_mtu($bridgedif);
		mwexec("/sbin/ifconfig {$wancfg['if']} mtu {$mtu}");
		mwexec("/sbin/ifconfig {$bridgedif} mtu {$mtu}");

		/* assign items to a bridge */
		mwexec("/sbin/ifconfig bridge{$bridges_total} addm {$wancfg['if']} addm {$bridgedif}");

		if(!is_interface_wireless($wancfg['if']) and
		   !is_interface_wireless($bridgedif))
			mwexec("/sbin/ifconfig bridge{$bridges_total} stp {$bridgedif} stp {$wancfg['if']}");

		/* log commands run for debugging in /tmp/ */
		$fd = fopen("{$g['tmp_path']}/bridge_config_{$wancfg['if']}", "w");
		fwrite($fd, "/sbin/ifconfig {$wancfg['if']} mtu {$mtu}\n");
		fwrite($fd, "/sbin/ifconfig {$bridgedif} mtu {$mtu}\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} create\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} addm {$wancfg['if']} addm {$bridgedif}\n");
		if(!is_interface_wireless($wancfg['if']) and
		   !is_interface_wireless($bridgedif))
			fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} stp {$wancfg['if']} stp {$bridgedif}\n");
		fclose($fd);

		/* bring up interfaces */
		mwexec("/sbin/ifconfig bridge{$bridges_total} down");
		usleep(100);
		mwexec("/sbin/ifconfig {$bridgedif} up");
		usleep(5);
		mwexec("/sbin/ifconfig {$wancfg['if']} up");
		usleep(5);
		mwexec("/sbin/ifconfig bridge{$bridges_total} up");

		$bridges_total++;
		/* update cache */
		if ($bridges_total != find_number_of_created_bridges())
			find_number_of_created_bridges(true);
	}

	/* XXX: Shouldn't the caller do this?! */
	if (!$g['booting']) {
		/* reconfigure static routes (kernel may have deleted them) */
		system_routing_configure();

		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");

		/* reload ipsec tunnels */
		vpn_ipsec_configure();

		/* restart ez-ipupdate */
		services_dyndns_configure();

		/* force DNS update */
		services_dnsupdate_process();

		/* restart dnsmasq */
		services_dnsmasq_configure();

		/* reload captive portal */
		captiveportal_configure();
	}

	mwexec("/sbin/ifconfig {$wancfg['if']} up");

	unmute_kernel_msgs();

	return 0;
}

function interfaces_opt_dhcp_configure($interface) {
	global $config, $g;

	$optcfg = $config['interfaces'][$interface];
	$optif = $optcfg['if'];

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$optif}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhclient_{$optif}.conf in interfaces_opt_dhcp_configure({$optif}) for writing.\n");
		return 1;
	}

	if ($optcfg['dhcphostname']) {
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$optcfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$optcfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
timeout 60;
retry 1;
select-timeout 0;
initial-interval 1;
interface "{$optif}" {
	script "/sbin/dhclient-script";
	{$dhclientconf_hostname}
}

EOD;

if(is_ipaddr($optcfg['alias-address'])) {
	$subnetmask = gen_subnet_mask($optcfg['alias-subnet']);
	$dhclientconf .= <<<EOD
alias {
	interface  "{$optif}";
	fixed-address {$optcfg['alias-address']};
	option subnet-mask {$subnetmask};
}

EOD;
}
	fwrite($fd, $dhclientconf);
	fclose($fd);

        /* bring interface up before starting dhclient */
        mwexec("/sbin/ifconfig {$optif} up");

        /* fire up dhclient */
        mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$optif}.conf {$optif}");

	return 0;
}

function interfaces_dhcp_configure($interface) {
	global $config, $g;

	if(filter_translate_type_to_real_interface($interface) <> "")
        	$realinterface = filter_translate_type_to_real_interface($interface);

	$optcfg = $config['interfaces'][$interface];

	/* generate dhclient_$interface.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhclient_{$interface}.conf in interfaces_dhcp_configure({$$interface}) for writing.\n");
		return 1;
	}

	if ($optcfg['dhcphostname']) {
		$dhclientconf_hostname =  "send dhcp-client-identifier \"{$optcfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$optcfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
timeout 60;
retry 1;
select-timeout 0;
initial-interval 1;
interface "{$realinterface}" {
	{$dhclientconf_hostname}
	script "/sbin/dhclient-script";
}

EOD;

if(is_ipaddr($optcfg['alias-address'])) {
	$subnetmask = gen_subnet_mask($optcfg['alias-subnet']);
	$dhclientconf .= <<<EOD
alias {
	interface  "{$optif}";
	fixed-address {$optcfg['alias-address']};
	option subnet-mask {$subnetmask};
}

EOD;
}

	fwrite($fd, $dhclientconf);
	fclose($fd);

	$optif = $optcfg['if'];

        /* bring wan interface up before starting dhclient */
        mwexec("/sbin/ifconfig {$optif} up");

        /* fire up dhclient */
        mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$optif}.conf {$optif} >/tmp/{$optif}_output >/tmp/{$optif}_error_output");

	$fout = fopen("/tmp/ifconfig_{$optif}","w");
	fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$optif}.conf {$optif}");
	fclose($fout);

	return 0;
}

function interfaces_wan_carpdev_dhcp_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];
	$wanif = $wancfg['if'];
	/* bring wan interface up before starting dhclient */
	mwexec("/sbin/ifconfig {$wanif} up");

	return 0;
}

function interfaces_wan_dhcp_configure($interface = "wan") {
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

	$wanif = get_real_wan_interface($interface);

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
        mwexec("/sbin/ifconfig {$realwanif} up");

        /* fire up dhclient */
        mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$wanif} >/tmp/{$wanif}_output >/tmp/{$wanif}_error_output");

	$fout = fopen("/tmp/ifconfig_{$wanif}","w");
	fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$wanif}");
	fclose($fout);

	return 0;
}

function interfaces_wan_dhcp_down($interface = "wan") {
	global $config;
	$wancfg = $config['interfaces'][$interface];
	$wanif = $wancfg['if'];
	mwexec("/sbin/ifconfig {$wanif} delete");
	sleep(1);
}

function interfaces_dhcp_down($interface) {
	global $config;
	if(filter_translate_type_to_real_interface($interface) <> "")
		$realinterface = filter_translate_type_to_real_interface($interface);
	mwexec("/sbin/ifconfig {$realinterface} down");
	sleep(1);
	$pid = find_dhclient_process($interface);
	if($pid)
		mwexec("kill {$pid}");
}

function interfaces_dhcp_up($interface = "wan") {
	interfaces_dhcp_configure($interface);
	sleep(1);
}

function interfaces_wan_dhcp_up($interface = "wan") {
	interfaces_wan_dhcp_configure($interface);
	sleep(1);
}

function interfaces_wan_pppoe_configure($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.conf in interfaces_wan_pppoe_configure().\n");
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
	set auth authname "{$wancfg['username']}"
	set auth password "{$wancfg['password']}"
	set link keep-alive 10 60
	set link max-redial 0
	set link no acfcomp protocomp
	set link disable pap chap
	set link accept chap
	set link mtu 1492
	set ipcp yes vjcomp
	set ipcp ranges 0.0.0.0/0 0.0.0.0/0

EOD;

	if (isset($config['system']['dnsallowoverride'])) {
		$mpdconf .= <<<EOD
	set ipcp enable req-pri-dns

EOD;
	}

	if (!isset($wancfg['dnsnosec'])) {
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
		printf("Error: cannot open mpd_{$interface}.links in interfaces_wan_pppoe_configure().\n");
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
		/* fire up mpd */
		mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']} -f mpd_{$interface}.conf -l mpd_{$interface}.links -p {$g['varrun_path']}/pppoe_{$interface}.pid pppoeclient");
	}

        /* sleep until wan is up - or 30 seconds, whichever comes first */
	for ($count = 0; $count < 30; $count++) {
		if(file_exists("{$g['tmp_path']}/wanup")) {
			break;
		}
		sleep(1);
	}

	unlink_if_exists("{$g['tmp_path']}/wanup");

	return 0;
}

function interfaces_wan_pppoe_restart($interface = "wan") {
	interfaces_wan_pppoe_down($interface);
	sleep(1);
	interfaces_wan_pppoe_up($interface);
}

function interfaces_wan_pppoe_down($interface = "wan") {
	global $g;
	sigkillbypid("{$g['varrun_path']}/pppoe_{$interface}.pid", "SIGUSR2");
	sleep(1);
}

function interfaces_wan_pppoe_up($interface = "wan") {
	global $g;
	sigkillbypid("{$g['varrun_path']}/pppoe_{$interface}.pid", "SIGUSR1");
	sleep(1);
}

function interfaces_wan_pptp_configure($interface) {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd_{$interface}.conf in interfaces_wan_pptp_configure().\n");
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
	set bundle authname "{$wancfg['username']}"
	set bundle password "{$wancfg['password']}"
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
		printf("Error: cannot open mpd_{$interface}.links in interfaces_wan_pptp_configure().\n");
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
	mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
		escapeshellarg($wancfg['local'] . "/" . $wancfg['subnet']) . " up");

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']} -f mpd_{$interface}.conf -l mpd_{$interface}.links -p {$g['varrun_path']}/pptp_{$interface}.pid pptp");

	return 0;
}

function interfaces_wan_pptp_restart($interface = "wan") {
	interfaces_wan_pptp_down($interface);
	sleep(1);
	interfaces_wan_pptp_up($interface);
}

function interfaces_wan_pptp_down($interface = "wan") {
	global $g;
	sigkillbypid("{$g['varrun_path']}/pptp_{$interface}.pid", "SIGUSR2");
	sleep(1);
}

function interfaces_wan_pptp_up($interface = "wan") {
	global $g;
	sigkillbypid("{$g['varrun_path']}/pptp_{$interface}.pid", "SIGUSR1");
	sleep(1);
}

function interfaces_wan_bigpond_configure($curwanip) {
	global $config, $g;

	$bpcfg = $config['bigpond'];

	if (!$curwanip) {
		/* IP address not configured yet, exit */
		return 0;
	}

	/* kill bpalogin */
	killbyname("bpalogin");

	/* wait a moment */
	sleep(1);

	/* get the default domain */
	$nfd = @fopen("{$g['varetc_path']}/defaultdomain.conf", "r");
	if ($nfd) {
		$defaultdomain = trim(fgets($nfd));
		fclose($nfd);
	}

	/* generate bpalogin.conf */
	$fd = fopen("{$g['varetc_path']}/bpalogin.conf", "w");
	if (!$fd) {
		printf("Error: cannot open bpalogin.conf in interfaces_wan_bigpond_configure().\n");
		return 1;
	}

	if (!$bpcfg['authserver'])
		$bpcfg['authserver'] = "dce-server";
	if (!$bpcfg['authdomain'])
		$bpcfg['authdomain'] = $defaultdomain;

	$bpconf = <<<EOD
username {$bpcfg['username']}
password {$bpcfg['password']}
authserver {$bpcfg['authserver']}
authdomain {$bpcfg['authdomain']}
localport 5050

EOD;

	if ($bpcfg['minheartbeatinterval'])
		$bpconf .= "minheartbeatinterval {$bpcfg['minheartbeatinterval']}\n";

	fwrite($fd, $bpconf);
	fclose($fd);

	/* fire up bpalogin */
	mwexec("/usr/local/sbin/bpalogin -c {$g['varetc_path']}/bpalogin.conf");

	return 0;
}

function get_real_wan_interface($interface = "wan") {
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
		$iflist = get_configured_interface_with_descr();

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
				if (isset($cfg['ispointtopoint']) && $cfg['pointtopoint'])
					$wanif = "ppp0"; // XXX: PPP needs to convert to mpd
				else	
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

function get_current_wan_address($interface = "wan") {
	global $config, $g;

	$realif = get_real_wan_interface($interface);
	/* Do we really come here for these interfaces ?! */
	if (in_array($realif, array("pptp", "pppoe", "openvpn", "enc0" /* , "ppp" */)))
			return "";

	$wancfg = $config['interfaces'][$interface];

	$ifinfo = "";
	switch ($wancfg['ipaddr']) {
	case "dhcp":
		/* get interface info with netstat */
		exec("/usr/bin/netstat -nWI " . escapeshellarg($realif) . " -f inet", $ifinfo);

		if (isset($ifinfo[1])) {
			$aif = preg_split("/\s+/", $ifinfo[1]);
			$curwanip = chop($aif[3]);

			if ($curwanip && is_ipaddr($curwanip) && ($curwanip != "0.0.0.0"))
				return $curwanip;
		}

		return null;
		break;
	case "pppoe":
	case "pptp":
	case "bigpond":
		/* get interface info with netstat */
		exec("/usr/bin/netstat -nWI " . escapeshellarg($realif) . " -f inet", $ifinfo);
		if (isset($ifinfo[1])) {
			$aif = preg_split("/\s+/", $ifinfo[1]);
			$curwanip = chop($aif[3]);

			if ($curwanip && is_ipaddr($curwanip) && ($curwanip != "0.0.0.0"))
				return $curwanip;
		}

		return null;
		break;
	 /* carpdev support */
	 case "carpdev-dhcp":
	        $viparr = &$config['virtualip']['vip'];
	        $counter = 0;
		if (is_array($viparr))
	        	foreach ($viparr as $vip) {
	                        if ($vip['mode'] == "carpdev-dhcp" && 
	                        	$vip['interface'] == $interface) {
					return str_replace("\n", "", `ifconfig carp{$counter} | grep inet | awk '{ print $2 }'`);
	                        	$counter++;
	                        } else if ($vip['mode'] == "carp")
					$counter++;
	                }
		return null;
		break;
	 default:	
		if (isset($cfg['ispointtopoint']) && $cfg['pointtopoint']) {
			/* get interface info with netstat */
                	exec("/usr/bin/netstat -nWI " . escapeshellarg($realif) . " -f inet", $ifinfo
);
                	if (isset($ifinfo[1])) {
 	                       	$aif = preg_split("/\s+/", $ifinfo[1]);
                       		$curwanip = chop($aif[3]);

                        	if ($curwanip && is_ipaddr($curwanip) && ($curwanip != "0.0.0.0"))
                                	return $curwanip;
                	}

                	return null;
		}
		break;
	}

	/* static WAN IP address */
	return $wancfg['ipaddr'];
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
		"em", "fxp", "hme", "lnc", "le", "nve", "re", "rl", "ndis", "sf", "sis", "sk",
		"tun", "vr", "wi", "xl", "vlan", "ste");

        $int_family = preg_split("/[0-9]+/", $int);

        if (in_array($int_family[0], $capable))
                return true;
        else
                return false;
}

function get_number_of_bridged_interfaces() {
	$bridges_total = 0;
	$bridges = split("\n", `/sbin/ifconfig -a | /usr/bin/grep bridge | grep flags`);
	foreach($bridges as $bridge) {
		$match_array = "";
		preg_match_all("/bridge(.*):/",$bridge,$match_array);
		if($match_array[1][0] <> "") {
			if($match_array[1][0] > $bridges_total)
				$bridges_total = $match_array[1][0];
		}
	}
	return "{$bridges_total}";
}

function get_number_of_vlan_interfaces() {
        $vlans_total = 0;
        $vlans = split("\n", `/sbin/ifconfig -a | /usr/bin/grep vlan | grep flags`);
        foreach($vlans as $bridge) {
                $match_array = "";
                preg_match_all("/vlan(.*):/",$bridge,$match_array);
                if($match_array[1][0] <> "") {
                        if($match_array[1][0] > $vlans_total)
                                $vlans_total = $match_array[1][0];
                }
        }
        return "{$vlans_total}";
}

function get_number_of_ppp_interfaces() {
        $ppps_total = 0;
        $ppps = split("\n", `/sbin/ifconfig -a | /usr/bin/grep ppp | grep flags`);
        foreach($ppps as $bridge) {
                $match_array = "";
                preg_match_all("/ppp(.*):/",$bridge,$match_array);
                if($match_array[1][0] <> "") {
                        if($match_array[1][0] > $ppps_total)
                                $ppps_total = $match_array[1][0];
                }
        }
        return "{$ppps_total}";
}

function get_next_available_bridge_interface() {
	$bridges_total = get_number_of_bridged_interfaces();
	$interfaces = `/sbin/ifconfig -l`;
	$x=0;
	for($x=0; $x<$bridges_total; $x++) {
		if(!stristr($interfaces, "bridge{$x}")) {
			return "{$x}";
		}
	}
	return "{$x}";
}

function destroy_bridge($bridge_num) {
	mwexec("/sbin/ifconfig bridge{$bridge_num} down");
	sleep(1);
	mwexec("/sbin/ifconfig bridge{$bridge_num} delete");
	sleep(1);
	mwexec("/sbin/ifconfig bridge{$bridge_num} destroy");
	sleep(1);
	return;
}

function discover_bridge($interface1, $interface2) {
	if(!$interface1) return;
	if(!$interface2) return;
	$total_bridges = get_number_of_bridged_interfaces();
	$total_bridges++;
	$interfaces = `/sbin/ifconfig -l`;
	$x=0;
	for($x=0; $x<$total_bridges; $x++) {
		$bridge_text = "NA";
		if(!stristr($interfaces, "bridge{$x}"))
			continue;
		$bridge_text = `/sbin/ifconfig bridge{$x} | grep member`;
		if(stristr($bridge_text, $interface1))
			if(stristr($bridge_text, $interface2))
				return $x;
	}
	return "-1";
}

function get_wireless_modes($interface)
{
	/* return wireless modes and channels */
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

?>
