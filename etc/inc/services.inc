<?php
/*
	services.inc
	part of m0n0wall (http://m0n0.ch/wall)
	
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

function services_dhcpd_configure() {
	global $config, $g;
	
	/* kill any running dhcpd */
	killbypid("{$g['varrun_path']}/dhcpd.pid");
	
	$syscfg = $config['system'];
	$dhcpdcfg = $config['dhcpd'];
	
	/* DHCP enabled on any interfaces? */
	$dhcpdenable = false;
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		if (isset($dhcpifconf['enable']) &&
			(($dhcpif == "lan") ||
			(isset($config['interfaces'][$dhcpif]['enable']) &&
			$config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
			$dhcpdenable = true;
	}
	
	if (!$dhcpdenable)
		return 0;
	
	if ($g['booting'])
		echo "Starting DHCP service... ";
	else
		sleep(1);
	
	/* write dhcpd.conf */
	$fd = fopen("{$g['varetc_path']}/dhcpd.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhcpd.conf in services_dhcpd_configure().\n");
		return 1;
	}
	
	$dhcpdconf = <<<EOD
option domain-name "{$syscfg['domain']}";
default-lease-time 7200;
max-lease-time 86400;
authoritative;
log-facility local7;
ddns-update-style none;

EOD;
	
	$dhcpdifs = array();
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		
		$ifcfg = $config['interfaces'][$dhcpif];
		
		if (!isset($dhcpifconf['enable']) ||
			(($dhcpif != "lan") &&
			(!isset($ifcfg['enable']) || !$ifcfg['if'] || $ifcfg['bridge'])))
			continue;
			
		$subnet = gen_subnet($ifcfg['ipaddr'], $ifcfg['subnet']);
		$subnetmask = gen_subnet_mask($ifcfg['subnet']);
		
		$dnscfg = "";
		
		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
		}
		
		if (is_array($dhcpifconf['dnsserver']) && ($dhcpifconf['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $dhcpifconf['dnsserver']) . ";";
		} else if (isset($config['dnsmasq']['enable'])) {
			$dnscfg .= "	option domain-name-servers " . $ifcfg['ipaddr'] . ";";
		} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $syscfg['dnsserver']) . ";";
		}

		$dhcpdconf .= "subnet $subnet netmask $subnetmask {\n";
		$dhcpdconf .= "	pool {\n";
		if (isset($dhcpifconf['denyunknown'])) 
		   $dhcpdconf .= "		deny unknown clients;\n";
		
		if ($dhcpifconf['gateway'])
			$routers = $dhcpifconf['gateway'];
		else
			$routers = $ifcfg['ipaddr'];
		
		$dhcpdconf .= <<<EOD
		range {$dhcpifconf['range']['from']} {$dhcpifconf['range']['to']};
	}
	option routers {$routers};
$dnscfg

EOD;

		if ($dhcpifconf['defaultleasetime'])
			$dhcpdconf .= "	default-lease-time {$dhcpifconf['defaultleasetime']};\n";
		if ($dhcpifconf['maxleasetime'])
			$dhcpdconf .= "	max-lease-time {$dhcpifconf['maxleasetime']};\n";
			
		if (is_array($dhcpifconf['winsserver']) && $dhcpifconf['winsserver'][0]) {
			$dhcpdconf .= "	option netbios-name-servers " . join(",", $dhcpifconf['winsserver']) . ";\n";
			$dhcpdconf .= "	option netbios-node-type 8;\n";
		}
		
		if ($dhcpifconf['next-server'])
			$dhcpdconf .= "	next-server {$dhcpifconf['next-server']};\n";
		if ($dhcpifconf['filename'])
			$dhcpdconf .= "	filename \"{$dhcpifconf['filename']}\";\n";
			
		$dhcpdconf .= <<<EOD
}

EOD;

		/* add static mappings */
		if (is_array($dhcpifconf['staticmap'])) {
		
			$i = 0;
			foreach ($dhcpifconf['staticmap'] as $sm) {
				$dhcpdconf .= <<<EOD
host s_{$dhcpif}_{$i} {
	hardware ethernet {$sm['mac']};

EOD;
				if ($sm['ipaddr'])
					$dhcpdconf .= "	fixed-address {$sm['ipaddr']};\n";
				
				$dhcpdconf .= "}\n";
				$i++;
			}
		}
		
		$dhcpdifs[] = $ifcfg['if'];
	}

	fwrite($fd, $dhcpdconf);
	fclose($fd);

	/* create an empty leases database */
	touch("{$g['vardb_path']}/dhcpd.leases");
	
	/* fire up dhcpd */
	mwexec("/usr/local/sbin/dhcpd -cf {$g['varetc_path']}/dhcpd.conf " . 
		join(" ", $dhcpdifs));
		
	if (!$g['booting']) {
		filter_configure();
	} else
		echo "done\n";
	
	return 0;
}

function services_dhcrelay_configure() {
	global $config, $g;
	
	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay.pid");
	
	$dhcrelaycfg = $config['dhcrelay'];
	
	/* DHCPRelay enabled on any interfaces? */
	$dhcrelayenable = false;
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) &&
			(($dhcrelayif == "lan") ||
			(isset($config['interfaces'][$dhcrelayif]['enable']) &&
			$config['interfaces'][$dhcrelayif]['if'] && (!$config['interfaces'][$dhcrelayif]['bridge']))))
			$dhcrelayenable = true;
	}
	
	if (!$dhcrelayenable)
		return 0;
	
	if ($g['booting'])
		echo "Starting DHCP relay service... ";
	else
		sleep(1);
	
	$dhcrelayifs = array();
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		
		$ifcfg = $config['interfaces'][$dhcrelayif];
		
		if (!isset($dhcrelayifconf['enable']) ||
			(($dhcrelayif != "lan") &&
			(!isset($ifcfg['enable']) || !$ifcfg['if'] || $ifcfg['bridge'])))
			continue;
			
		$dhcrelayifs[] = $ifcfg['if'];
	}

	/* In order for the relay to work, it needs to be active on the 
	   interface in which the destination server sits */
	foreach ($config['interfaces'] as $ifname) {
		$subnet = $ifname['ipaddr'] . "/" . $ifname['subnet'];
		if (ip_in_subnet($dhcrelaycfg['server'],$subnet)) 
			$destif = $ifname['if'];	
	}
	
	if (!isset($destif)) 
		$destif = $config['interfaces']['wan']['if'];
	
	$dhcrelayifs[] = $destif;
	$dhcrelayifs = array_unique($dhcrelayifs);

	/* fire up dhcrelay */
	$cmd = "/usr/local/sbin/dhcrelay -i " .  join(" -i ", $dhcrelayifs);

	if (isset($dhcrelaycfg['agentoption']))	
		$cmd .=  " -a -m replace";

	$cmd .= " {$dhcrelaycfg['server']}";
	mwexec($cmd);
		
	if (!$g['booting']) {
		filter_configure();
	} else
		echo "done\n";
	
	return 0;
}

function services_dyndns_reset() {
	global $config, $g;

	if (file_exists("{$g['vardb_path']}/ez-ipupdate.cache")) {
		unlink("{$g['vardb_path']}/ez-ipupdate.cache");
	}	
	
	if (file_exists("{$g['conf_path']}/ez-ipupdate.cache")) {
		conf_mount_rw();
		unlink("{$g['conf_path']}/ez-ipupdate.cache");
		conf_mount_ro();
	}
	
	return 0;
}

function services_dyndns_configure() {
	global $config, $g;
	
	/* kill any running ez-ipupdate */
	/* ez-ipupdate needs SIGQUIT instead of SIGTERM */
	sigkillbypid("{$g['varrun_path']}/ez-ipupdate.pid", "QUIT");
	
	$dyndnscfg = $config['dyndns'];
	$wancfg = $config['interfaces']['wan'];
	
	if (isset($dyndnscfg['enable'])) {
	
		if ($g['booting'])
			echo "Starting DynDNS client... ";
		else
			sleep(1);
		
		/* determine WAN interface name */
		$wanif = get_real_wan_interface();
		
		/* write ez-ipupdate.conf */
		$fd = fopen("{$g['varetc_path']}/ez-ipupdate.conf", "w");
		if (!$fd) {
			printf("Error: cannot open ez-ipupdate.conf in services_dyndns_configure().\n");
			return 1;
		}
			
		$ezipupdateconf = <<<EOD
service-type={$dyndnscfg['type']}
user={$dyndnscfg['username']}:{$dyndnscfg['password']}
host={$dyndnscfg['host']}
interface=$wanif
max-interval=2073600
pid-file={$g['varrun_path']}/ez-ipupdate.pid
cache-file={$g['vardb_path']}/ez-ipupdate.cache
execute=/etc/rc.dyndns.storecache
daemon

EOD;

		/* enable MX? */
		if ($dyndnscfg['mx']) {
			$ezipupdateconf .= "mx={$dyndnscfg['mx']}\n";
		}
		
		/* enable wildcards? */
		if (isset($dyndnscfg['wildcard'])) {
			$ezipupdateconf .= "wildcard\n";
		}

		fwrite($fd, $ezipupdateconf);
		fclose($fd);
		
		/* if we're booting, copy the cache file from /conf */
		if ($g['booting']) {
			if (file_exists("{$g['conf_path']}/ez-ipupdate.cache")) {
				copy("{$g['conf_path']}/ez-ipupdate.cache", "{$g['vardb_path']}/ez-ipupdate.cache");
			}
		}
		
		/* run ez-ipupdate */
		mwexec("/usr/local/bin/ez-ipupdate -c {$g['varetc_path']}/ez-ipupdate.conf");
		
		if ($g['booting'])
			echo "done\n";
	}
	
	return 0;
}

function services_dnsmasq_configure() {
	global $config, $g;

	/* kill any running dnsmasq */
	sigkillbypid("{$g['varrun_path']}/dnsmasq.pid", "TERM");

	if (isset($config['dnsmasq']['enable'])) {
	
		if ($g['booting'])
			echo "Starting DNS forwarder... ";
		else
			sleep(1);

		/* generate hosts file */
		system_hosts_generate();
		
		$args = "";
		
		if (isset($config['dnsmasq']['regdhcp'])) {
			
			$args .= " -l {$g['vardb_path']}/dhcpd.leases" . 
				" -s {$config['system']['domain']}";
		}

		/* run dnsmasq */
		mwexec("/usr/local/sbin/dnsmasq {$args}");

		if ($g['booting'])
			echo "done\n";
	}
	
	if (!$g['booting']) {
		services_dhcpd_configure();
	}

	return 0;
}

function services_snmpd_configure() {
	global $config, $g;

	/* kill any running snmpd */
	sigkillbypid("{$g['varrun_path']}/snmpd.pid", "TERM");

	if (isset($config['snmpd']['enable'])) {
	
		if ($g['booting'])
			echo "Starting SNMP agent... ";

		/* generate snmpd.conf */
		$fd = fopen("{$g['varetc_path']}/snmpd.conf", "w");
		if (!$fd) {
			printf("Error: cannot open snmpd.conf in services_snmpd_configure().\n");
			return 1;
		}
			
		$snmpdconf = <<<EOD
syslocation "{$config['snmpd']['syslocation']}"
syscontact "{$config['snmpd']['syscontact']}"
rocommunity "{$config['snmpd']['rocommunity']}"

EOD;

		fwrite($fd, $snmpdconf);
		fclose($fd);

		/* run snmpd */
		mwexec("/usr/local/sbin/snmpd -c {$g['varetc_path']}/snmpd.conf" .
			" -P {$g['varrun_path']}/snmpd.pid");

		if ($g['booting'])
			echo "done\n";
	}

	return 0;
}

function services_proxyarp_configure() {
	global $config, $g;

	/* kill any running choparp */
	killbyname("choparp");
	
	if (is_array($config['proxyarp']) && count($config['proxyarp']) &&
			(is_ipaddr($config['interfaces']['wan']['ipaddr']) ||
			($config['interfaces']['wan']['ipaddr'] == "dhcp") ||
			($config['interfaces']['wan']['ipaddr'] == "bigpond"))) {
	
		$args = $config['interfaces']['wan']['if'] . " auto";
	
		foreach ($config['proxyarp']['proxyarpnet'] as $paent) {
			if (isset($paent['network']))
				$args .= " " . escapeshellarg($paent['network']);
			else if (isset($paent['range']))
				$args .= " " . escapeshellarg($paent['range']['from'] . "-" . 
					$paent['range']['to']);
		}
		
		mwexec_bg("/usr/local/sbin/choparp " . $args);
	}
}

?>
