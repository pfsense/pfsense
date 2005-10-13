<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004-2005 Scott Ullrich
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

function interfaces_loopback_configure() {
	mwexec("/sbin/ifconfig lo0 127.0.0.1");

	return 0;
}

function interfaces_vlan_configure() {
	global $config;

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

		/* devices with native VLAN support */
		$vlan_native_supp = explode(" ", "bge em gx nge ti txp");

		/* devices with long frame support */
		$vlan_long_supp = explode(" ", "dc fxp sis ste tl tx xl");

		$i = 0;

		foreach ($config['vlans']['vlan'] as $vlan) {

			$cmd = "/sbin/ifconfig vlan{$i} create vlan " .
				escapeshellarg($vlan['tag']) . " vlandev " .
				escapeshellarg($vlan['if']);

			/* get driver name */
			for ($j = 0; $j < strlen($vlan['if']); $j++) {
				if ($vlan['if'][$j] >= '0' && $vlan['if'][$j] <= '9')
					break;
			}
			$drvname = substr($vlan['if'], 0, $j);

			if (in_array($drvname, $vlan_native_supp))
				$cmd .= " link0";
			else if (in_array($drvname, $vlan_long_supp))
				$cmd .= " mtu 1500";

			mwexec($cmd);

			/* make sure the parent interface is up */
			mwexec("/sbin/ifconfig " . escapeshellarg($vlan['if']) . " up");

			$i++;
		}
	}

	return 0;
}

function interfaces_lan_configure() {
	global $config, $g;

	$bridges_total = get_next_available_bridge_interface();

	$lancfg = $config['interfaces']['lan'];

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
		mwexec("/sbin/ifconfig bridge{$bridges_total} addm {$lancfg['if']} addm {$config['interfaces'][$lancfg['bridge']]['if']} up");
		mwexec("/sbin/ifconfig bridge{$bridges_total} stp {$lancfg['if']} add {$config['interfaces'][$lancfg['bridge']]['if']}");
		
		$fd = fopen("{$g['tmp_path']}/bridge_config_{$lancfg['if']}", "w");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} create\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} addm {$lancfg['if']} addm {$config['interfaces'][$lancfg['bridge']]['if']} up\n");
		fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} stp {$lancfg['if']} add {$config['interfaces'][$lancfg['bridge']]['if']}\n");
		fclose($fd);

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

		/* reload webgui */
		system_webgui_start();

		/* reload captive portal */
		captiveportal_configure();
	}

	return 0;
}

function interfaces_optional_configure() {
	global $config, $g;
	global $bridgeconfig;

	/* Reset bridge configuration.	Interfaces will add to it. */
	$bridgeconfig = "";

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

		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");				
	}

	return 0;
}

function interfaces_optional_configure_if($opti) {
	global $config, $g;
	global $bridgeconfig;

	$bridges_total = get_next_available_bridge_interface();

	$optcfg = $config['interfaces']['opt' . $opti];

	if ($g['booting']) {
		$optdescr = "";
		if ($optcfg['descr'])
			$optdescr = " ({$optcfg['descr']})";
		print "\tOPT{$opti}{$optdescr}... ";
	}

	if (isset($optcfg['enable'])) {
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

		/* OpenVPN configuration? */
 		if (isset($optcfg['ovpn'])) {
 			if (strstr($optcfg['if'], "tap"))
 				ovpn_link_tap();
 		}

		/* bridged? */
		if ($optcfg['bridge']) {
			mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " delete up");
                        /* use open/netBSD style bridge */
			mwexec("/sbin/ifconfig bridge{$bridges_total} create");
                        mwexec("/sbin/ifconfig bridge{$bridges_total} addm {$optcfg['if']} addm {$config['interfaces'][$optcfg['bridge']]['if']} up");
			mwexec("/sbin/ifconfig bridge{$bridges_total} stp {$optcfg['if']} add {$config['interfaces'][$optcfg['bridge']]['if']}");
			
			$fd = fopen("{$g['tmp_path']}/bridge_config_{$optcfg['if']}", "w");
			fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} create\n");
			fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} addm {$optcfg['if']} addm {$config['interfaces'][$optcfg['bridge']]['if']} up\n");
			fwrite($fd, "/sbin/ifconfig bridge{$bridges_total} stp {$optcfg['if']} add {$config['interfaces'][$optcfg['bridge']]['if']}\n");
			fclose($fd);

		} else {
			/* if user has selected DHCP type then act accordingly */
			if($optcfg['ipaddr'] == "dhcp") {
				interfaces_opt_dhcp_configure("opt{$opti}");
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
	global $g, $config;
	if ($g['booting'] and !$debugging) {
		echo "Configuring CARP interfaces...";
		mute_kernel_msgs();
	}
	unlink_if_exists("/usr/local/etc/rc.d/carp.sh");
	unlink_if_exists("/usr/local/pkg/pf/carp.sh");
	unlink_if_exists("/usr/local/pkg/pf/carp_rules.sh");
	$carp_instances_counter = 0;
	$pfsync_instances_counter = 0;
	$total_carp_interfaces_defined = find_number_of_created_carp_interfaces();
	if (is_array($config['virtualip']['vip'])) {
		if(is_array($config['installedpackages']['carpsettings']['config'])) {
			foreach($config['installedpackages']['carpsettings']['config'] as $carp)
				if($carp['pfsyncenabled'] != "") {
					if($debugging) 
						echo "Enabling preempt\n";
					if($carp['premption'] != "")
						mwexec("/sbin/sysctl net.inet.carp.preempt=1");
					if($carp['balancing'] != "")
						mwexec("/sbin/sysctl net.inet.arpbalance=1");
					if($debugging) 
						echo "Get friendly interface name.\n";
					$carp_sync_int = convert_friendly_interface_to_real_interface_name($carp['pfsyncinterface']);
					/* do not setup pfsync twice */
					if($total_carp_interfaces_defined == 0) {
						if($debugging)
							echo "Bringing up pfsync0.\n";
						mwexec("/sbin/ifconfig pfsync0 create");
						if($debugging)
							echo "Assigning syncdev to {$carp_sync_int}.\n";						
						mwexec("/sbin/ifconfig pfsync0 syncdev " . $carp_sync_int);
						if($debugging)
							echo "Bringing up syncif pfsync0.\n";

						mwexec("/sbin/ifconfig pfsync0 up");
					}
					if($g['booting']) {
						/* install rules to alllow pfsync to sync up during boot
						* carp interfaces will remain down until the bootup sequence finishes
						*/
						if($debugging) 
							echo "Adding firewall rules..\n";
						exec("echo pass quick proto carp all keep state > /tmp/rules.boot");
						exec("echo pass quick proto pfsync all >> /tmp/rules.boot");
						exec("echo pass out proto { tcp, udp } from any to any port 53 keep state >> /tmp/rules.boot");
						exec("/sbin/pfctl -f /tmp/rules.boot");
					}
					$pfsync_instances_counter++;
				}
		}
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "carp") {
				/*
				*  create the carp interface
				*/
				if($debugging)
					echo "Creating carp{$carp_instances_counter}.\n";
				mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " create");
				$broadcast_address = gen_subnet_max($vip['subnet'], $vip['subnet_bits']);
				if($vip['password'] != "") {
					$password = " pass " . $vip['password'];
				}
				/* XXX: billm - carpdev not in our build?
				$carpdev = "";
				if(isset($vip['interface']) && ($vip['interface'] != "AUTO" && $vip['interface'] != "")) {
					$ci = filter_opt_interface_to_real($vip['interface']);
					$carpdev = " carpdev {$ci} ";
				}
				*/
				if($debugging)
					echo "Configuring carp{$carp_instances_counter}.\n";
				mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " " . $vip['subnet'] . "/" . $vip['subnet_bits'] . " broadcast " . $broadcast_address . " vhid " . $vip['vhid'] . "{$carpdev} advskew " . $vip['advskew'] . $password);
				if($g['booting']) 
					mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " down");
				$carp_instances_counter++;
			}
		}
	} else {
		/* Hush little pfsync, don't say a word.
		   GeekGod's gonna buy you a mocking bird. */
		mwexec("/sbin/ifconfig pfsync0 syncdev lo0 up");
	}
	if ($g['booting'] and !$debugging) {
		unmute_kernel_msgs();
		echo "done.\n";
	}
}

function interfaces_carp_bring_up_final() {
	global $config, $g;
	$carp_instances_counter = 0;
	$viparr = &$config['virtualip']['vip'];
	if(!is_array($viparr))
		return;
	foreach ($viparr as $vip) {
		echo "Upping interface carp{$carp_instances_counter}.\n";
		mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " up");
		$carp_instances_counter++;
	}
}

function interfaces_wireless_configure($if, $wlcfg) {
	global $config, $g;
	
	/* set values for /path/program */
	$hostapd = "/usr/sbin/hostapd";
	$wpa_supplicant = "/usr/sbin/wpa_supplicant";
	$ifconfig = "/sbin/ifconfig ";
	$killall = "/usr/bin/killall ";

	/* Sett all wireless ifconfig variables (splitt up to get rid of needed checking) */

	/* Set a/b/g standard */
	$standard = ("mode " . escapeshellarg($wlcfg['standard']));

	/* set wireless channel value */
	$channel = escapeshellarg($wlcfg['channel']);
	
	if($channel == "") {
		$channel = "";
	} else { 
		$channel = ("channel " . escapeshellarg($wlcfg['channel']));
	}

	/* Set ssid */
	$ssid = ("ssid " . escapeshellarg($wlcfg['ssid']));

	/* Set stationname */
	if (!$wlcfg['stationname'])
		$stationname = "pfsense";
	else
		$stationname = ("stationname " . escapeshellarg($wlcfg['stationname']));

	/* Set wireless hostap mode */
	if ($wlcfg['mode'] == hostap)
		$hostapmode = "mediaopt hostap";
	else
		$hostapmode = "-mediaopt hostap";

	/* Set wireless adhoc mode */
	if ($wlcfg['mode'] == adhoc)
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
		$txpower = ("txpower " . escapeshellarg($wlcfg['txpower']));
	
	/* handle wme option */
	if(isset($wlcfg['wme']['enable']))
		$wme = "wme";
	else
		$wme = "-wme";
	
	/* set up wep if enabled */
        if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
                $wepset .= "authmode shared wepmode on ";

                $i = 1;
                foreach ($wlcfg['wep']['key'] as $wepkey) {
                        $wepset .= "wepkey " . escapeshellarg("{$i}:{$wepkey['value']}") . " ";
                        if (isset($wepkey['txkey'])) {
                                $wepset .= "weptxkey {$i} ";
                        }
                        $i++;
                }
        } else {
                $wepset = "authmode open wepmode off";
	}

	/* generate wpa_supplicant/hostap config if wpa is enabled */

	switch ($wlcfg['mode']) {
		case 'BSS':
			if (isset($wlcfg['wpa']['enable'])) {

				$wpa .= <<<EOD
ctrl_interface={$g['varrun_path']}/hostapd
ctrl_interface_group=0
ap_scan=1
#fast_reauth=1
network={
ssid={$wlcfg['ssid']}
scan_ssid=2
priority=5
key_mgmt={$wlcfg['wpa']['wpa_key_mgmt']}
psk={$wlcfg['wpa']['passphrase']}
pairwise={$wlcfg['wpa']['wpa_pairwise']}
group={$wlcfg['wpa']['wpa_pairwise']}
}
EOD;

				$fd = fopen("{$g['tmp_path']}/wpa_supplicant_{$if}.conf", "w");
				fwrite($fd, "{$wpa}");
				fclose($fd);

				if(is_process_running("wpa_supplicant"))
					mwexec("$killall" . " wpa_supplicant");
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
ssid={$wlcfg['ssid']}
debug={$wlcfg['wpa']['debug_mode']}
#macaddr_acl={$wlcfg['wpa']['macaddr_acl']}
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

				$fd = fopen("{$g['tmp_path']}/hostapd_{$if}.conf", "w");
				fwrite($fd, "{$wpa}");
				fclose($fd);

				if(is_process_running("hostapd"))
					mwexec("$killall" . " hostapd");
			}
		break;

		case 'adhoc':
			if(is_process_running("hostapd"))
				mwexec("$killall hostapd");

			if(is_process_running("wpa_supplicant"))
				mwexec("$killall wpa_supplicant");
		break;
	}	

	/* start up everything */
	        
	mwexec("$ifconfig {$if}" . " down"); 
	mwexec("$ifconfig $if" . " " . $standard);
	mwexec("$ifconfig $if" . " " . $channel);
	mwexec("$ifconfig $if" . " " . $ssid);
	mwexec("$ifconfig $if" . " " . $stationname);
	mwexec("$ifconfig $if" . " " . $hostapmode);
	mwexec("$ifconfig $if" . " " . $adhocmode);
	mwexec("$ifconfig $if" . " " . $hidessid);
	mwexec("$ifconfig $if" . " " . $pureg);
	mwexec("$ifconfig $if" . " " . $apbridge);
	mwexec("$ifconfig $if" . " " . $turbo);
	mwexec("$ifconfig $if" . " " . $wme);
	mwexec("$ifconfig $if" . " " . $wepset);
	mwexec("$ifconfig $if" . " up"); 

	if (isset($wlcfg['wpa']['enable'])) {
		if ($wlcfg['mode'] == BSS) 
			mwexec("$wpa_supplicant -i {$if} -c {$g['etc_path']}/wpa_supplicant_{$if}.conf");
		if ($wlcfg['mode'] == hostap) 
			mwexec("$hostapd -B {$g['tmp_path']}/hostapd_{$if}.conf");
	}

	/* Write ifconfig settings to tmp file so we can see if user set something weird */ 
//	$ifcargs = ("$standard $channel $ssid $stationname $hostapmode $adhocmode $hidessid $pureg $apbridge $turbo $wme $wepset");
//	$fd = fopen("{$g['tmp_path']}/ifconfig_wireless", "w");
//	fwrite($fd, "/sbin/ifconfig {$ifcargs}");
//	fclose($fd);

	/* Write wep crap out */
//	$fd = fopen("{$g['tmp_path']}/ifconfig_wep", "w");
//	fwrite($fd, "sbin/ifconfig {$wepset}");
//	fclose($fd);
	
	if(isset($wlcfg['useolsr']))
		setup_wireless_olsr(escapeshellarg($if));

	return 0;

}

function find_dhclient_process($interface) {
	if(filter_translate_type_to_real_interface($interface) <> "")
        	$realinterface = filter_translate_type_to_real_interface($interface);
	$pid = `ps ax | grep "[d]hclient" | grep {$realinterface} | awk -F" " '{print $1}'`;
	return $pid;
}

function interfaces_wan_configure() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];

	if(!$g['booting']) {
		mute_kernel_msgs();

		/* find dhclient process for wan and kill it */
		killbypid(find_dhclient_process("wan"));

		/* kill PPPoE client (mpd) */
		killbypid("{$g['varrun_path']}/mpd.pid");

		/* wait for processes to die */
		sleep(1);

		unlink_if_exists("{$g['varetc_path']}/dhclient_wan.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd.links");
		unlink_if_exists("{$g['vardb_path']}/wanip");
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
				mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) . " " .
					escapeshellarg($wancfg['ipaddr'] . "/" . $wancfg['subnet']));
			}
			/* install default route */
			mwexec("/sbin/route delete default");
			mwexec("/sbin/route add default " . escapeshellarg($config['interfaces']['wan']['gateway']));

			/* resync pf (done automatically for DHCP/PPPoE/PPTP) */
			filter_configure();
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
	}

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
		$dhclientconf_hostname = "	send dhcp-client-identifier \"{$optcfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
interface "{$optif}" {
	send host-name "{$optcfg['dhcphostname']}";
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
		$dhclientconf_hostname = "send dhcp-client-identifier \"{$optcfg['dhcphostname']}\";\n";
		$dhclientconf_hostname = "	send dhcp-client-identifier \"{$optcfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
interface "{$realinterface}" {
	script "/sbin/dhclient-script";
	{$dhclientconf_hostname}
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
		$dhclientconf_hostname = "	send dhcp-client-identifier \"{$wancfg['dhcphostname']}\";\n";
	} else {
		$dhclientconf_hostname = "";
	}

 	$dhclientconf = "";

	$dhclientconf .= <<<EOD
interface "{$wancfg['if']}" {
	script "/sbin/dhclient-script";
	{$dhclientconf_hostname}
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

	/* if mpd is active, lets take it down */
	if(file_exists("{$g['varrun_path']}/mpd.pid")) {
		killbypid(file_get_contents("{$g['varrun_path']}/mpd.pid"));
		sleep(1);
	}

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']} -p {$g['varrun_path']}/mpd.pid pppoe");

	return 0;
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
		"em", "fxp", "hme", "lnc", "ndis", "rl", "sf", "sis", "sk",
		"tun", "vr", "wi", "xl");

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
		preg_match_all("/bridge(.*):/",$bridge,$match_array);
		if($match_array[1][0] <> "") {
			if($match_array[1][0] > $bridges_total)
				$bridges_total = $match_array[1][0];
		}
	}
	return "{$bridges_total}";
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
	$interfaces = `/sbin/ifconfig -l`;
	$x=0;
	for($x=0; $x<$total_bridges; $x++) {
		$bridge_text = "NA";
		if(!stristr($interfaces, "bridge{$x}")) 
			continue;
		$bridge_text = `/sbin/ifconfig bridge{$x} | grep member`;
		if(stristr($bridge_text, $interface1) == true and
		   stristr($bridge_text, $interface2) == true) {
			return "{$x}";			
		}
	}
	return "-1";
}

?>