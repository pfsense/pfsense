<?php
/* $Id$ */
/*
	interfaces.inc
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
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

	$lancfg = $config['interfaces']['lan'];

	/* wireless configuration? */
	if (is_array($lancfg['wireless']))
		interfaces_wireless_configure($lancfg['if'], $lancfg['wireless']);

	/* MAC spoofing? */
	if ($lancfg['spoofmac'])
		mwexec("/sbin/ifconfig " . escapeshellarg($lancfg['if']) .
			" link " . escapeshellarg($lancfg['spoofmac']));

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

		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");

		/* reload IPsec tunnels */
		vpn_ipsec_configure();

		/* reload dhcpd (interface enabled/disabled/bridged status may have changed) */
		services_dhcpd_configure();

		/* restart dnsmasq */
		services_dnsmasq_configure();
	}

	return 0;
}

function interfaces_optional_configure_if($opti) {
	global $config, $g;
	global $bridgeconfig;
	global $bridges_total;
	
	if(!is_numeric($bridges_total)) $bridges_total=0;

	$optcfg = $config['interfaces']['opt' . $opti];

	if ($g['booting']) {
		$optdescr = "";
		if ($optcfg['descr'])
			$optdescr = " ({$optcfg['descr']})";
		print "\tOPT{$opti}{$optdescr}... ";
		mute_kernel_msgs();
	}

	if (isset($optcfg['enable'])) {
		/* wireless configuration? */
		if (is_array($optcfg['wireless']))
			interfaces_wireless_configure($optcfg['if'], $optcfg['wireless']);

		/* MAC spoofing? */
		if ($optcfg['spoofmac'])
			mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) .
				" link " . escapeshellarg($optcfg['spoofmac']));

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
                        mwexec("/sbin/brconfig bridge{$bridges_total} add {$optcfg['if']} add {$config['interfaces'][$optcfg['bridge']]['if']} up");
			mwexec("/sbin/brconfig bridge{$bridges_total} stp {$optcfg['if']} add {$config['interfaces'][$optcfg['bridge']]['if']}");
			/* lets keep track of the amount of bridges initialized */
			$bridges_total++;
		} else {
			/* if user has selected DHCP type then act accordingly */
			if($optcfg['type'] == 'DHCP') {
				interfaces_opt_dhcp_configure($opti);
			} else {			
				mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " " .
					escapeshellarg($optcfg['ipaddr'] . "/" . $optcfg['subnet']));
			}
		}
	} else {
		mwexec("/sbin/ifconfig " . escapeshellarg($optcfg['if']) . " delete down");
	}

	if ($g['booting']) {
		unmute_kernel_msgs();
		print "done.\n";
	}

	return 0;
}

function interfaces_carp_configure() {
	global $g, $config;
	if ($g['booting']) {
		echo "Configuring CARP interfaces...";
		mute_kernel_msgs();
	}
	unlink_if_exists("/usr/local/etc/rc.d/carp.sh");
	unlink_if_exists("/usr/local/pkg/pf/carp.sh");
	unlink_if_exists("/usr/local/pkg/pf/carp_rules.sh");
	$carp_instances_counter = 0;
	$pfsync_instances_counter = 0;
	if($config['installedpackages']['carpsettings']['config'] != "") {
		foreach($config['installedpackages']['carpsettings']['config'] as $carp)
		if($carp['pfsyncenabled'] != "") {
			if($carp['premption'] != "")
			mwexec("/sbin/sysctl net.inet.carp.preempt=1");
			if($carp['balancing'] != "")
			mwexec("/sbin/sysctl net.inet.arpbalance=1");
			$carp_sync_int = convert_friendly_interface_to_real_interface_name($carp['pfsyncinterface']);
			mwexec("/sbin/ifconfig pfsync0 create");
			mwexec("/sbin/ifconfig pfsync0 syncdev " . $carp_sync_int);
			mwexec("/sbin/ifconfig pfsync0 syncif " . $carp_sync_int);
			mwexec("/sbin/ifconfig {$carp_sync_int} up");
			mwexec("/sbin/ifconfig pfsync0 up");
			if($g['booting']) {
				/* install rules to alllow pfsync to sync up during boot
				* carp interfaces will remain down until the bootup sequence finishes
				*/
				exec("echo pass quick proto carp all keep state > /tmp/rules.boot");
				exec("echo pass quick proto pfsync all >> /tmp/rules.boot");
				exec("echo pass out proto { tcp, udp } from any to any port 53 keep state >> /tmp/rules.boot");
				exec("/sbin/pfctl -f /tmp/rules.boot");
			}
			$pfsync_instances_counter++;
		}
	}
	if($config['installedpackages']['carp']['config'] != "") {
		foreach($config['installedpackages']['carp']['config'] as $carp) {
			/*
			*  create the carp interface
			*/
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " create");
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " down");
			$broadcast_address = gen_subnet_max($carp['ipaddress'], $carp['netmask']);
			if($carp['password'] != "") {
				$password = " pass " . $carp['password'];
			}
			$carpdev = "";
			if($carp['interface'] <> "AUTO" and $carp['interface'] <> "") {
				$ci = filter_opt_interface_to_real($carp['interface']);
				$carpdev = " carpdev {$ci} ";
			}
			mwexec("/sbin/ifconfig carp" . $carp_instances_counter . " " . $carp['ipaddress'] . "/" . $carp['netmask'] . " broadcast " . $broadcast_address . " vhid " . $carp['vhid'] . "{$carpdev} advskew " . $carp['advskew'] . $password);
			$carp_instances_counter++;
		}
	}
	unmute_kernel_msgs();
	if ($g['booting']) {
		unmute_kernel_msgs();
		echo "done.\n";
	}
}

function interfaces_carp_bringup() {
	global $g;
	/* lets bring the carp interfaces up now */
	if ($g['booting'])
		sleep(5);
	$carp_ints = find_number_of_created_carp_interfaces();
	for($x=0; $x<$carp_ints; $x++)
		mwexec("/sbin/ifconfig carp{$x} up");	
}

function interfaces_wireless_configure($if, $wlcfg) {
        global $config, $g;

        /* wireless configuration */
        $ifcargs = escapeshellarg($if) .
                " ssid " . escapeshellarg($wlcfg['ssid']) . " channel " .
                escapeshellarg($wlcfg['channel']) . " ";

        if ($wlcfg['stationname'])
                $ifcargs .= "stationname " . escapeshellarg($wlcfg['stationname']) . " ";

        if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
                $ifcargs .= "wepmode on ";

                $i = 1;
                foreach ($wlcfg['wep']['key'] as $wepkey) {
                        $ifcargs .= "wepkey " . escapeshellarg("{$i}:{$wepkey['value']}") . " ";
                        if (isset($wepkey['txkey'])) {
                                $ifcargs .= "weptxkey {$i} ";
                        }
                        $i++;
                }
        } else {
                $ifcargs .= "wepmode off ";
        }

        if (strstr($if, "ath")) {
                if ($wlcfg['standard'])
                        $ifcargs .= "mode {$wlcfg['standard']} ";
        }

        switch ($wlcfg['mode']) {
                case 'hostap':
                        if (strstr($if, "ath"))
                                $ifcargs .= "-mediaopt adhoc mediaopt hostap ";
                        else if (strstr($if, "wi"))
                                $ifcargs .= "-mediaopt ibss mediaopt hostap ";
                        break;
                case 'ibss':
                case 'IBSS':
                        if (strstr($if, "ath"))
                                $ifcargs .= "-mediaopt hostap mediaopt adhoc ";
                        else if (strstr($if, "wi"))
                                $ifcargs .= "-mediaopt hostap mediaopt ibss ";
                        else if (strstr($if, "an"))
                                $ifcargs .= "mediaopt adhoc ";
                        break;
                case 'bss':
                case 'BSS':
                        if (strstr($if, "ath"))
                                $ifcargs .= "-mediaopt hostap -mediaopt adhoc ";
                        else if (strstr($if, "wi"))
                                $ifcargs .= "-mediaopt hostap -mediaopt ibss ";
                        else if (strstr($if, "an"))
                                $ifcargs .= "-mediaopt adhoc ";
                        break;
        }

        $ifcargs .= "up";

        mwexec("/sbin/ifconfig " . $ifcargs);

	$fd = fopen("{$g['tmp_path']}/ifconfig_wireless", "w");
	fwrite($fd, "/sbin/ifconfig {$ifcargs}");
	fclose($fd);

        return 0;

}

function find_dhclient_process($interface) {
	$pid = `ps ax | grep dhclient | grep {$interface} | cut -f" " -d2`;
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

	if ($wancfg['spoofmac'])
		mwexec("/sbin/ifconfig " . escapeshellarg($wancfg['if']) .
			" link " . escapeshellarg($wancfg['spoofmac']));

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
			mwexec("/sbin/route add default " . escapeshellarg($config['system']['gateway']));

			/* resync ipfilter (done automatically for DHCP/PPPoE/PPTP) */
			filter_resync();
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

	/* generate dhclient_wan.conf */
	$fd = fopen("{$g['varetc_path']}/dhclient_{$interface}.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhclient_{$interface}.conf in interfaces_opt_dhcp_configure({$interface}) for writing.\n");
		return 1;
	}

 	$dhclientconf = "";

 	if ($optcfg['dhcphostname']) {
		$dhclientconf .= <<<EOD
interface "{$optcfg['if']}" {
	send dhcp-client-identifier "{$optcfg['dhcphostname']}";
	send host-name "{$optcfg['dhcphostname']}";
	script "/etc/dhclient-script";
}

EOD;
	}

	fwrite($fd, $dhclientconf);
	fclose($fd);
	
	$optif = $optcfg['if'];
	
        /* bring wan interface up before starting dhclient */
        mwexec("/sbin/ifconfig {$optif} up");

        /* fire up dhclient */
        mwexec_bg("/sbin/dhclient -c {$g['varetc_path']}/dhclient_{$interface}.conf {$optif}");

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

 	$dhclientconf = "";

 	if ($wancfg['dhcphostname']) {
		$dhclientconf .= <<<EOD
interface "{$wancfg['if']}" {
	send dhcp-client-identifier "{$wancfg['dhcphostname']}";
	send host-name "{$wancfg['dhcphostname']}";
	script "/etc/dhclient-script";
}

EOD;
	}

	fwrite($fd, $dhclientconf);
	fclose($fd);
	
	$wanif = $wancfg['if'];
	
        /* bring wan interface up before starting dhclient */
        mwexec("/sbin/ifconfig {$wanif} up");

        /* fire up dhclient */
        mwexec_bg("/sbin/dhclient -c {$g['varetc_path']}/dhclient_wan.conf {$wanif}");

	return 0;
}

function interfaces_wan_dhcp_down() {
	mwexec("/sbin/dhclient -r");
	sleep(3);
}

function interfaces_wan_dhcp_up() {
	interfaces_wan_dhcp_configure();
	sleep(3);
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
		$mpdconf .= <<<EOD
	set iface addrs 10.0.0.1 10.0.0.2

EOD;
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

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']} -p {$g['varrun_path']}/mpd.pid pppoe");

	return 0;
}

function interfaces_wan_pppoe_down() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR2");
	sleep(3);
}

function interfaces_wan_pppoe_up() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR1");
	sleep(3);
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
	set ipcp enable req-sec-dns

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
	sleep(3);
}

function interfaces_wan_pptp_up() {
	global $g;
	sigkillbypid("{$g['varrun_path']}/mpd.pid", "SIGUSR1");
	sleep(3);
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

function get_current_wan_address() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];

	if (in_array($wancfg['ipaddr'], array('pppoe','dhcp','pptp','bigpond'))) {
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

?>