<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004-2006 Scott Ullrich
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

	/* make sure the parent interface is up and enable hw vlan support if available*/
	mwexec("/sbin/ifconfig " . escapeshellarg($if) . " up vlanhwtag vlanmtu");

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

	return $vlanif;
}

function interfaces_lan_configure() {
	global $config, $g;

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
		setup_bridge();
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

function interfaces_optional_configure() {
	global $config, $g;

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		interfaces_optional_configure_if($i);
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

function interfaces_optional_configure_if($opti) {
	global $config, $g;
	global $debugging;

	$optcfg = $config['interfaces']['opt' . $opti];

	if ($g['booting']) {
		$optdescr = "";
		if ($optcfg['descr'])
			$optdescr = " ({$optcfg['descr']})";
		print "\tOPT{$opti}{$optdescr}... ";
	}

	if(file_exists("/tmp/{$optcfg['if']}_router"))
		unlink("/tmp/{$optcfg['if']}_router");

	if (isset($optcfg['enable'])) {
		if($optcfg['gateway'])
			system("echo " . $optcfg['gateway'] . " > /tmp/" . $optcfg['if'] . "_router");

		/* wireless configuration? */
		if (is_array($optcfg['wireless']))
			interfaces_wireless_configure($optcfg['if'], $optcfg['wireless']);

		/* MAC spoofing? */
		if ($optcfg['spoofmac']) {
			mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) .
				" link " . escapeshellarg($optcfg['spoofmac']));
		} else {
			$mac = get_interface_mac_address($optcfg['if']);
			if($mac == "ff:ff:ff:ff:ff:ff") {
				/*   this is not a valid mac address.  generate a
				 *   temporary mac address so the machine can get online.
				 */
				echo "Generating new MAC address.";
				$random_mac = generate_random_mac_address();
				mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) .
					" link " . escapeshellarg($random_mac));
				$optcfg['spoofmac'] = $random_mac;
				write_config();
				file_notice("MAC Address altered", "The INVALID MAC address (ff:ff:ff:ff:ff:ff) on interface {$optcfg['if']} has been automatically replaced with {$random_mac}", "Interfaces");
			}
		}

		/* media */
		if ($optcfg['media'] || $optcfg['mediaopt']) {
			$cmd = "/sbin/ifconfig " . escapeshellarg($optcfg['if']);
			if ($optcfg['media'])
				$cmd .= " media " . escapeshellarg($optcfg['media']);
			if ($optcfg['mediaopt'])
				$cmd .= " mediaopt " . escapeshellarg($optcfg['mediaopt']);
			mwexec($cmd);
		}

		/* bridged? */
		if ($optcfg['bridge']) {
			setup_bridge();
		} else {
			/* if user has selected DHCP type then act accordingly */
			if($optcfg['ipaddr'] == "dhcp") {
				interfaces_opt_dhcp_configure("opt{$opti}");
			} elseif ($optcfg['ipaddr'] == "none") {
				/* hidden trick for IP-less interfaces */
				mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " up");
			} else {
				mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " " .
				escapeshellarg($optcfg['ipaddr'] . "/" . $optcfg['subnet']));
			}
		}
	} else {
		mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " delete down");
	}
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
		exec("echo pass out proto { tcp, udp } from any to any port 53 keep state >> /tmp/rules.boot");
		exec("/sbin/pfctl -f /tmp/rules.boot");
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
	$fd = fopen("/tmp/carp.sh", "w");
	if($config['virtualip']['vip']) {
		$viparr = &$config['virtualip']['vip'];
		mwexec("/sbin/sysctl net.inet.carp.allow=1");
	} else {
		$viparr = array();
		mwexec("/sbin/sysctl net.inet.carp.allow=0");
	}
	foreach ($viparr as $vip) {
		if ($vip['mode'] == "carp") {
			$vip_password = $vip['password'];
			$vip_password = str_replace(" ", "", $vip_password);
			
			/* ensure CARP IP really exists prior to loading up */
			$found = false;
			$iflist = array("lan", "wan");
			for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
				$iflist['opt' . $i] = 'opt' . $i;
			foreach($iflist as $if) {
				/* ignore down or "disabled" interfaces */
				if($if <> "lan" and $if <> "wan")
					if (!isset($config['interfaces'][$if]['enable'])) 
						continue;								
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
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " create");
			fwrite($fd, "/sbin/ifconfig carp" . $carp_instances_counter . " create\n");

			/* invalidate interface cache */
			get_interface_arr(true);

			$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
			if($vip['password'] != "")
				$password = " pass \"" . $vip_password . "\"";
			if($debugging)
				echo "Configuring carp{$carp_instances_counter}.\n";
			fwrite($fd, "/sbin/ifconfig carp" . $carp_instances_counter . " " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " vhid " . $vip['vhid'] . "{$carpdev} advskew 200 " . $password . "\n");
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " vhid " . $vip['vhid'] . "{$carpdev} advskew 200 " . $password);
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " up");
			fwrite($fd, "/sbin/ifconfig carp" . $carp_instances_counter . " up\n");
			usleep(10);
			$carp_instances_counter++;
		}
	}
	fclose($fd);
	mwexec("/bin/sh /tmp/carp.sh");
	if ($g['booting']) {
		unmute_kernel_msgs();
		echo "done.\n";
	}

	/* update cache */
	if ($carp_instances_counter != find_number_of_created_carp_interfaces())
		find_number_of_created_carp_interfaces(true);
}

function interfaces_carp_bring_up_final() {
	global $config, $g, $debugging;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_carp_bring_up_final() being called $mt\n";
	}
	if(!$config['virtualip']['vip'])
		return;
	$viparr = &$config['virtualip']['vip'];
	/* could not locate an array, return */
	if(!is_array($viparr))
		return;
	$havecarp = false;
	foreach ($viparr as $vip) {
		/* bail if this isn't a carp VIP */
		if ($vip['mode'] == "carp")
			$havecarp = true;
	}		
	if($havecarp == false) 
		return;
	$carp_instances_counter = 0;
	$counter = 0;
	if($g['booting'])
		echo "Waiting for final CARP interface bringup...";
	$supress = intval(`/sbin/sysctl net.inet.carp.suppress_preempt | cut -d" " -f2`);
	if($g['booting']) {
		while($supress > 0) {
			sleep(1);
			$supress = intval(`/sbin/sysctl net.inet.carp.suppress_preempt | cut -d" " -f2`);
			if($counter > 15)
				$supress = 0;
			$counter++;
			echo ".";
		}
	}
	foreach ($viparr as $vip) {
		/* bail if this isn't a carp VIP */
		if ($vip['mode'] != "carp")
			continue;
		/* ensure CARP IP really exists prior to loading up */
		$found = false;
		$iflist = array("lan", "wan");
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
			$iflist['opt' . $i] = 'opt' . $i;
		foreach($iflist as $if) {
			/* ignore down or "disabled" interfaces */
			if($if <> "lan" and $if <> "wan")
				if (!isset($config['interfaces'][$if]['enable'])) 
					continue;								
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
		if($debugging)
			echo "Upping interface carp{$carp_instances_counter}.\n";
		$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
		if($vip['password'] != "")
			$password = " pass " . $vip['password'];
		if($debugging)
			echo "/sbin/ifconfig carp" . $carp_instances_counter . " " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " vhid " . $vip['vhid'] . "{$carpdev} advskew " . $vip['advskew'] . $password . "\n";
		mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " vhid " . $vip['vhid'] . "{$carpdev} advskew " . $vip['advskew'] . $password);
		usleep(100);
		mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " up");
		$carp_instances_counter++;
	}
	if($g['booting'])
		echo " done.\n";
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
	fwrite($fd_set, "# pfSense wireless configuration script.\n\n");

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
{$ifconfig} {$if} mtu 1500
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
	$pid = `ps awwwux | grep dhclient | grep -v grep | grep {$realinterface} | awk '{ print \$2 }'`;
	return $pid;
}

function interfaces_wan_configure() {
	
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];

	if(file_exists("/tmp/{$config['interfaces']['wan']['if']}_router")) 
		unlink("/tmp/{$config['interfaces']['wan']['if']}_router");

	if(!$g['booting']) {
		mute_kernel_msgs();

		/* find dhclient process for wan and kill it */
		killbypid(find_dhclient_process("wan"));

		/* remove wanup file if it exists */
		unlink_if_exists("{$g['tmp_path']}/wanup");

		/* kill PPPoE client (mpd) */
		killbypid("{$g['varrun_path']}/mpd.pid");

		/* wait for processes to die */
		sleep(3);
		unlink_if_exists("{$g['varetc_path']}/dhclient_wan.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd.links");
		unlink_if_exists("{$g['vardb_path']}/wanip");
		unlink_if_exists("{$g['varetc_path']}/nameservers.conf");
	}

	/* remove all addresses first */
	while (mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " -alias", true) == 0);
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

		case 'dhcp':
			interfaces_wan_dhcp_configure();
			break;

		case 'pppoe':
			interfaces_wan_pppoe_configure();
			break;

		case 'pptp':
			interfaces_wan_pptp_configure();
			break;

		case 'bigpond':
			/* just configure DHCP for now; fire up bpalogin when we've got the lease */
			interfaces_wan_dhcp_configure();
			break;

		default:
			if (isset($wancfg['ispointtopoint']) && $wancfg['pointtopoint']) {
				mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
					escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']) .
					" " . escapeshellarg($wancfg['pointtopoint']) . " up");
			} else {
				if($wancfg['ipaddr'] && $wancfg['subnet'])
					mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
						escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']));
			}
			/* install default route */
			mwexec("/sbin/route delete default", true);

			$dont_add_route = false;
			/* if OLSRD is enabled, allow WAN to house DHCP. */
			if($config['installedpackages']['olsrd']) {
				foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
						if($olsrd['enabledyngw'] == "on") {
							$dont_add_route = true;
						}
				}
			}

			if($dont_add_route == false)
				mwexec("/sbin/route add default " . escapeshellarg($config['interfaces']['wan']['gateway']));

			if($config['interfaces']['wan']['gateway'])
				system("echo " . $config['interfaces']['wan']['gateway'] . " > /tmp/" . $config['interfaces']['wan']['if'] . "_router");

			/* resync pf (done automatically for DHCP/PPPoE/PPTP) */
			filter_configure();
	}

	if ($wancfg['bridge']) {
		setup_bridge();
	}

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

function interfaces_wan_dhcp_configure() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_wan.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhclient_wan.conf in interfaces_wan_dhcp_configure() for writing.\n");
		return 1;
	}

	if ($wancfg['dhcphostname']) {
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$wancfg['dhcphostname']}\";\n";
		$dhclientconf_hostname .= "\tsend host-name \"{$wancfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
timeout 60;
retry 1;
select-timeout 0;
initial-interval 1;
interface "{$wancfg['if']}" {
	{$dhclientconf_hostname}
	script "/sbin/dhclient-script";
}

EOD;

	fwrite($fd, $dhclientconf);
	fclose($fd);

	$wanif = $wancfg['if'];

        /* bring wan interface up before starting dhclient */
        mwexec("/sbin/ifconfig {$wanif} up");

        /* fire up dhclient */
        mwexec("/sbin/dhclient -c {$g['varetc_path']}/dhclient_wan.conf {$wanif} >/tmp/{$wanif}_output >/tmp/{$wanif}_error_output");

	$fout = fopen("/tmp/ifconfig_{$wanif}","w");
	fwrite($fout, "/sbin/dhclient -c {$g['varetc_path']}/dhclient_wan.conf {$wanif}");
	fclose($fout);

	return 0;
}

function interfaces_wan_dhcp_down() {
	global $config;
	$wancfg = $config['interfaces']['wan'];
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

function interfaces_dhcp_up($interface) {
	interfaces_dhcp_configure($interface);
	sleep(1);
}

function interfaces_wan_dhcp_up() {
	interfaces_wan_dhcp_configure();
	sleep(1);
}

function interfaces_wan_pppoe_configure() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$pppoecfg = $config['pppoe'];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd.conf in interfaces_wan_pppoe_configure().\n");
		return 1;
	}

	$idle = 0;

	if (isset($pppoecfg['ondemand'])) {
		$ondemand = "enable";
		if ($pppoecfg['timeout'])
			$idle = $pppoecfg['timeout'];
	} else {
		$ondemand = "disable";
	}

	$mpdconf = <<<EOD
pppoe:
	new -i ng0 pppoe pppoe
	set iface route default
	set iface {$ondemand} on-demand
	set iface idle {$idle}
	set iface up-script /usr/local/sbin/ppp-linkup

EOD;

	/*    Check for ppp-linkdown Script in /usr/local/sbin
	 *    Create reference in mpd.conf
	 */
	if ( file_exists("/usr/local/sbin/ppp-linkdown") ){
		$mpdconf .= <<<EOD
	set iface down-script /usr/local/sbin/ppp-linkdown

EOD;
	}

	if (isset($pppoecfg['ondemand'])) {
		if (isset($pppoecfg['local-ip']) && isset($pppoecfg['remote-ip'])) {
			$mpdconf .= <<<EOD
	set iface addrs {$pppoecfg['local-ip']} {$pppoecfg['remote-ip']}

EOD;
		} else {
			$mpdconf .= <<<EOD
	set iface addrs 192.0.2.112 192.0.2.113

EOD;
		}
	}

	$mpdconf .= <<<EOD
	set bundle disable multilink
	set bundle authname "{$pppoecfg['username']}"
	set bundle password "{$pppoecfg['password']}"
	set bundle no noretry
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

	if (!isset($config['pppoe']['dnsnosec'])) {
			$mpdconf .= <<<EOD
	set ipcp enable req-sec-dns

EOD;
	}
	
	$mpdconf .= <<<EOD
	open iface

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	/* generate mpd.links */
	$fd = fopen("{$g['varetc_path']}/mpd.links", "w");
	if (!$fd) {
		printf("Error: cannot open mpd.links in interfaces_wan_pppoe_configure().\n");
		return 1;
	}

	$mpdconf = <<<EOD
pppoe:
	set link type pppoe
	set pppoe iface {$wancfg['if']}
	set pppoe service "{$pppoecfg['provider']}"
	set pppoe enable originate
	set pppoe disable incoming

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	if(file_exists("{$g['varrun_path']}/mpd.pid") and $g['booting']) {
		/* if we are booting and mpd has already been started then don't start again. */
	} else {
		/* if mpd is active, lets take it down */
		if(file_exists("{$g['varrun_path']}/mpd.pid")) {
			killbypid("{$g['varrun_path']}/mpd.pid");
			sleep(3);
		}
		/* fire up mpd */
		mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']} -p {$g['varrun_path']}/mpd.pid pppoe");
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

function interfaces_wan_pppoe_restart() {
	interfaces_wan_pppoe_down();
	sleep(1);
	interfaces_wan_pppoe_up();
}

function interfaces_wan_pppoe_down() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR2");
	sleep(1);
}

function interfaces_wan_pppoe_up() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR1");
	sleep(1);
}

function interfaces_wan_pptp_configure() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$pptpcfg = $config['pptp'];

	/* generate mpd.conf */
	$fd = fopen("{$g['varetc_path']}/mpd.conf", "w");
	if (!$fd) {
		printf("Error: cannot open mpd.conf in interfaces_wan_pptp_configure().\n");
		return 1;
	}

	$idle = 0;

	if (isset($pptpcfg['ondemand'])) {
		$ondemand = "enable";
		if ($pptpcfg['timeout'])
			$idle = $pptpcfg['timeout'];
	} else {
		$ondemand = "disable";
	}

	$mpdconf = <<<EOD
pptp:
	new -i ng0 pptp pptp
	set iface route default
	set iface {$ondemand} on-demand
	set iface idle {$idle}
	set iface up-script /usr/local/sbin/ppp-linkup

EOD;

	/*   Check for ppp-linkdown Script in /usr/local/sbin
	 *   Create reference in mpd.conf
	 */
	if ( file_exists("/usr/local/sbin/ppp-linkdown") ){
		$mpdconf .= <<<EOD
	set iface down-script /usr/local/sbin/ppp-linkdown

EOD;
	}

	if (isset($pptpcfg['ondemand'])) {
		$mpdconf .= <<<EOD
	set iface addrs 10.0.0.1 10.0.0.2

EOD;
	}

	$mpdconf .= <<<EOD
	set bundle disable multilink
	set bundle authname "{$pptpcfg['username']}"
	set bundle password "{$pptpcfg['password']}"
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
	$fd = fopen("{$g['varetc_path']}/mpd.links", "w");
	if (!$fd) {
		printf("Error: cannot open mpd.links in interfaces_wan_pptp_configure().\n");
		return 1;
	}

	$mpdconf = <<<EOD
pptp:
	set link type pptp
	set pptp enable originate outcall
	set pptp disable windowing
	set pptp self {$pptpcfg['local']}
	set pptp peer {$pptpcfg['remote']}

EOD;

	fwrite($fd, $mpdconf);
	fclose($fd);

	/* configure interface */
	mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
		escapeshellarg($pptpcfg['local'] . "/" . $pptpcfg['subnet']));

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']} -p {$g['varrun_path']}/mpd.pid pptp");

	return 0;
}

function interfaces_wan_pptp_restart() {
	interfaces_wan_pptp_down();
	sleep(1);
	interfaces_wan_pptp_up();
}

function interfaces_wan_pptp_down() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR2");
	sleep(1);
}

function interfaces_wan_pptp_up() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR1");
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

function get_real_wan_interface() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];

	$wanif = $wancfg['if'];
	if (($wancfg['ipaddr'] == "pppoe") || ($wancfg['ipaddr'] == "pptp")) {
		$wanif = $g['pppoe_interface'];
	}

	return $wanif;
}

function get_current_wan_address($interface = "wan") {
	global $config, $g;

	$wancfg = $config['interfaces'][$interface];

	$interface = filter_translate_type_to_real_interface($interface);
	$ifinfo = "";
	if(in_array($wancfg['ipaddr'], array('dhcp'))) {
		/* get interface info with netstat */
		exec("/usr/bin/netstat -nWI " . escapeshellarg($interface) . " -f inet", $ifinfo);

		if (isset($ifinfo[1])) {
			$aif = preg_split("/\s+/", $ifinfo[1]);
			$curwanip = chop($aif[3]);

			if ($curwanip && is_ipaddr($curwanip) && ($curwanip != "0.0.0.0"))
				return $curwanip;
		}

		return null;
	} else if (in_array($wancfg['ipaddr'], array('pppoe','pptp','bigpond'))) {
		/* dynamic WAN IP address, find out which one */
		$wanif = get_real_wan_interface();

		/* get interface info with netstat */
		exec("/usr/bin/netstat -nWI " . escapeshellarg($wanif) . " -f inet", $ifinfo);

		if (isset($ifinfo[1])) {
			$aif = preg_split("/\s+/", $ifinfo[1]);
			$curwanip = chop($aif[3]);

			if ($curwanip && is_ipaddr($curwanip) && ($curwanip != "0.0.0.0"))
				return $curwanip;
		}

		return null;
	} else {
		/* static WAN IP address */
		return $wancfg['ipaddr'];
	}
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
			"iwi", "msk", "mxge", "my", "nfe", "npe", "ral", "rum", "stge", "udav", "ural");


        $int_family = preg_split("/[0-9]+/", $int);

        if (in_array($int_family[0], $capable))
                return true;
        else
                return false;
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

function get_number_of_bridged_interfaces() {

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

function destroy_bridge($bridge_num) {
	mwexec("/sbin/ifconfig bridge{$bridge_num} down");
	sleep(1);
	mwexec("/sbin/ifconfig bridge{$bridge_num} delete");
	sleep(1);
	mwexec("/sbin/ifconfig bridge{$bridge_num} destroy");
	sleep(1);
	return;
}

function setup_bridge() {
	global $config, $g;
	$bridge_if_num = -1;
	$bridgearray = array();
	$iflist = array("lan", "wan");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		if(isset($config['interfaces']['opt' . $i]['enable']))
			$iflist['opt' . $i] = 'opt' . $i;
	foreach($iflist as $if) {
		// go through all interfaces and check for bridged interfaces
		log_error("foreach if is $if");
		if ($config['interfaces'][$if]['bridge']) {
			// this interface is bridged
			$bridgefound = false;
			if ($bridge_if_num == -1) {
				// this is the first bridge, create the bridge0 interface
				$bridge_if_num++;
				$bridgearray[$bridge_if_num][] = convert_friendly_interface_to_real_interface_name($if);
				$bridgearray[$bridge_if_num][] = convert_friendly_interface_to_real_interface_name($config['interfaces'][$if]['bridge']);
			} else {
				// additional bridge
				// check if interface exists in another bridge
				// since the same int can't be added to two bridges
				$realif = convert_friendly_interface_to_real_interface_name($if);
				$realifbridge = convert_friendly_interface_to_real_interface_name($config['interfaces'][$if]['bridge']);
				for($x=0; $x <= $bridge_if_num; $x++) {
					if (in_array($realif, $bridgearray[$x])) {
						// the interface is already in this bridge
						$bridgefound = true;
						if (!in_array($realifbridge, $bridgearray[$x])) {
							// bridged interface isn't already in this
							// bridge, add it.
							$bridgearray[$bridge_if_num][] = $realifbridge;
						}
					} else {
						// see if the other member interface of the bridge is in existing bridge
						if (in_array($realifbridge, $bridgearray[$x])) {
							$bridgefound = true;
							if (!in_array($realif, $bridgearray[$x])) {
								// if real interface isn't already in
								// this bridge, add it.
								$bridgearray[$bridge_if_num][] = $realif;
							}
						}
					}
				}
				if (!$bridgefound) {
					// the interfaces of this bridge are not contained
					// in any existing bridge. Add new bridge interface.
					$bridge_if_num++;
					$bridgearray[$bridge_if_num][] = convert_friendly_interface_to_real_interface_name($if);
					$bridgearray[$bridge_if_num][] = convert_friendly_interface_to_real_interface_name($config['interfaces'][$if]['bridge']);
				}
			}
		}
	}
	// at this point, $bridgearray is fully populated for the number of bridges the system requires.
	// time to set them up.
	for($x=0; $x <= $bridge_if_num; $x++) {
		// destroy and create ifbridge interface
		mwexec("/sbin/ifconfig bridge{$x} destroy");
		mwexec("/sbin/ifconfig bridge{$x} create");
		// log commands run for debugging in /tmp
		$fd = fopen("{$g['tmp_path']}/bridge{$x}_config", "w");
		fwrite($fd, "/sbin/ifconfig bridge{$x} destroy \n");
		fwrite($fd, "/sbin/ifconfig bridge{$x} create \n");
		$bridgecmd = "/sbin/ifconfig bridge{$x} ";
		$lowestmtu = "1500";
		$wirelessbridge = false;
		// iterate through and see if any of the involved interfaces are wireless or VLANs
		foreach ($bridgearray[$x] as $bridgeif) {
			$friendlyifname = convert_real_interface_to_friendly_interface_name($bridgeif);
			if(is_interface_wireless($friendlyifname))
				$wirelessbridge = true;
		}

		$vfaces = array (
				'bridge',
				'ppp',
				'sl',
				'gif',
				'faith',
				'lo',
				'ng',
				'vlan',
				'pflog',
				'pfsync',
				'enc',
				'tun',
				'tap',
				'carp'
		);
		
		foreach ($bridgearray[$x] as $bridgeif) {
			// iterate through all the interfaces in this bridge
			// append to the bridgecmd for this interface
			// only use STP on Ethernet interfaces, not wireless 
			// nor virtual interfaces
			$trimbridgeif = preg_replace("/[0-9]/","", $bridgeif);
			if($wirelessbridge)
				$bridgecmd .= " addm $bridgeif ";
			elseif(in_array($trimbridgeif, $vfaces)) 
				$bridgecmd .= " addm $bridgeif ";
			else
				$bridgecmd .= " addm $bridgeif stp $bridgeif ";
			// get MTU for this interface
			$friendlyifname = convert_real_interface_to_friendly_interface_name($bridgeif);
			$mtu = get_interface_mtu($friendlyifname);
			if (intval($mtu) == 0) {
				log_error("An error occurred while trying to obtain the MTU setting for $bridgeif.  Using 1500.");
				$mtu = "1500";
			} else {
				if (intval($mtu) < intval($lowestmtu))
					$lowestmtu = $mtu;
			}
		}
		
		// force all bridged interfaces to same MTU, the lowest common denominator
		foreach ($bridgearray[$x] as $bridgeif) {
			mwexec("/sbin/ifconfig {$bridgeif} mtu {$lowestmtu}");
			fwrite($fd, "/sbin/ifconfig {$bridgeif} mtu {$lowestmtu} \n");
		}
		
		// bring up each interface
		foreach ($bridgearray[$x] as $bridgeif) {
			mwexec("/sbin/ifconfig {$bridgeif} up");
			fwrite($fd, "/sbin/ifconfig {$bridgeif} up \n");
		}
		
		// setup the bridge
		$bridgecmd .= " up";
		mwexec("{$bridgecmd}");
		fwrite($fd, "{$bridgecmd} \n");
		
		fclose($fd);
	}
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
