<?php
/* $Id$ */
/*
	system.inc
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

/*
	pfSense_BUILDER_BINARIES:	/usr/sbin/powerd	/usr/bin/killall	/sbin/route
	pfSense_BUILDER_BINARIES:	/bin/hostname	/bin/ls	/usr/sbin/syslogd	
	pfSense_BUILDER_BINARIES:	/usr/sbin/pccardd	/usr/local/sbin/lighttpd	/bin/chmod 	/bin/mkdir
	pfSense_BUILDER_BINARIES:	/usr/bin/tar		/usr/local/sbin/ntpd	/usr/local/sbin/ntpdate
	pfSense_BUILDER_BINARIES:	/usr/bin/nohup	/sbin/dmesg	/usr/local/sbin/atareinit	/sbin/kldload
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/filterdns
	pfSense_MODULE:	utils
*/

function activate_powerd() {
	global $config, $g;
	if ($g['platform'] == 'jail')
		return;
	if(is_process_running("powerd"))
		exec("/usr/bin/killall powerd");
	if(isset($config['system']['powerd_enable'])) {
		if ($g["platform"] == "nanobsd")
			exec("/sbin/kldload cpufreq");

		$ac_mode = "hadp";
		if (!empty($config['system']['powerd_ac_mode']))
			$ac_mode = $config['system']['powerd_ac_mode'];

		$battery_mode = "hadp";
		if (!empty($config['system']['powerd_battery_mode']))
			$battery_mode = $config['system']['powerd_battery_mode'];

		$normal_mode = "hadp";
		if (!empty($config['system']['powerd_normal_mode']))
			$normal_mode = $config['system']['powerd_normal_mode'];

		mwexec("/usr/sbin/powerd -b $battery_mode -a $ac_mode -n $normal_mode");
	}
}

function get_default_sysctl_value($id) {
	global $sysctls;

	if (isset($sysctls[$id]))
		return $sysctls[$id];
}

function activate_sysctls() {
	global $config, $g;

	if ($g['platform'] == 'jail')
		return;

	$sysctls = array(
		"net.enc.out.ipsec_bpf_mask" => "0x0001",
		"net.enc.out.ipsec_filter_mask" => "0x0001",
		"net.enc.in.ipsec_bpf_mask" => "0x0002",
		"net.enc.in.ipsec_filter_mask" => "0x0002"
	);

	if(is_array($config['sysctl'])) {
		foreach($config['sysctl']['item'] as $tunable) {
			if($tunable['value'] == "default")
				$value = get_default_sysctl_value($tunable['tunable']);
			else
				$value = $tunable['value'];

			$sysctls[$tunable['tunable']] = $value;
		}
	}

	set_sysctl($sysctls);
}

function system_resolvconf_generate($dynupdate = false) {
	global $config, $g;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_resolvconf_generate() being called $mt\n";
	}

	$syscfg = $config['system'];

	// Do not create blank domain lines, it breaks tools like dig.
	if($syscfg['domain'])
		$resolvconf = "domain {$syscfg['domain']}\n";

	if (((isset($config['dnsmasq']['enable']) && (empty($config['dnsmasq']['interface']) || in_array("lo0", explode(",", $config['dnsmasq']['interface']))))
		|| (isset($config['unbound']['enable'])) && (empty($config['unbound']['active_interface']) || in_array("lo0", explode(",", $config['unbound']['active_interface']))))
		&& !isset($config['system']['dnslocalhost']))
		$resolvconf .= "nameserver 127.0.0.1\n";

	if (isset($syscfg['dnsallowoverride'])) {
		/* get dynamically assigned DNS servers (if any) */
		$ns = array_unique(get_searchdomains());
		foreach($ns as $searchserver) {
			if($searchserver)
				$resolvconf .= "search {$searchserver}\n";
		}
		$ns = array_unique(get_nameservers());
		foreach($ns as $nameserver) {
			if($nameserver)
				$resolvconf .= "nameserver $nameserver\n";
		}
	}
	if (is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $ns) {
			if ($ns)
				$resolvconf .= "nameserver $ns\n";
		}
	}

	// Add EDNS support
	if (isset($config['unbound']['enable']) && isset($config['unbound']['edns']))
		$resolvconf .= "options edns0\n";

	$dnslock = lock('resolvconf', LOCK_EX);

	$fd = fopen("{$g['varetc_path']}/resolv.conf", "w");
	if (!$fd) {
		printf("Error: cannot open resolv.conf in system_resolvconf_generate().\n");
		unlock($dnslock);
		return 1;
	}

	fwrite($fd, $resolvconf);
	fclose($fd);

	if (!$g['booting']) {
		/* restart dhcpd (nameservers may have changed) */
		if (!$dynupdate)
			services_dhcpd_configure();
	}

	/* setup static routes for DNS servers. */
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		/* setup static routes for dns servers */
		$dnsgw = "dns{$dnscounter}gw";
		if (isset($config['system'][$dnsgw])) {
			$gwname = $config['system'][$dnsgw];
			if (($gwname <> "") && ($gwname <> "none")) {
				$gatewayip = lookup_gateway_ip_by_name($gwname);
				if (is_ipaddrv4($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("/sbin/route change -host " . $syscfg['dnsserver'][$dnscountermo] . " {$gatewayip}");
				}
				if (is_ipaddrv6($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("/sbin/route change -host -inet6 " . $syscfg['dnsserver'][$dnscountermo] . " {$gatewayip}");
				}
			}
		}
	}

	unlock($dnslock);

	return 0;
}

function get_searchdomains() {
	global $config, $g;

	$master_list = array();
	
	// Read in dhclient nameservers
	$search_list = glob("/var/etc/searchdomain_*");
	if (is_array($search_list)) {
		foreach($search_list as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents))
				continue;
			foreach ($contents as $dns) {
				if(is_hostname($dns)) 
					$master_list[] = $dns;
			}
		}
	}

	return $master_list;
}

function get_nameservers() {
	global $config, $g;
	$master_list = array();
	
	// Read in dhclient nameservers
	$dns_lists = glob("/var/etc/nameserver_*");
	if (is_array($dns_lists)) {
		foreach($dns_lists as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents))
				continue;
			foreach ($contents as $dns) {
				if(is_ipaddr($dns)) 
					$master_list[] = $dns;
			}
		}
	}

	// Read in any extra nameservers
	if(file_exists("/var/etc/nameservers.conf")) {
		$dns_s = file("/var/etc/nameservers.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if(is_array($dns_s)) {
			foreach($dns_s as $dns)
				if (is_ipaddr($dns))
					$master_list[] = $dns;
		}
	}

	return $master_list;
}

function system_hosts_generate() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_hosts_generate() being called $mt\n";
	}

	$syscfg = $config['system'];
	$dnsmasqcfg = $config['dnsmasq'];

	$hosts = "127.0.0.1	localhost localhost.{$syscfg['domain']}\n";
	$lhosts = "";
	$dhosts = "";

	if ($config['interfaces']['lan']) {
		$cfgip = get_interface_ip("lan");
		if (is_ipaddr($cfgip))
			$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
	} else {
		$sysiflist = get_configured_interface_list();
		foreach ($sysiflist as $sysif) {
			if (!interface_has_gateway($sysif)) {
				$cfgip = get_interface_ip($sysif);
				if (is_ipaddr($cfgip)) {
					$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
					break;
				}
			}
		}
	}

	if (isset($dnsmasqcfg['enable'])) {
		if (!is_array($dnsmasqcfg['hosts']))
			$dnsmasqcfg['hosts'] = array();

		foreach ($dnsmasqcfg['hosts'] as $host) {
			if ($host['host'])
				$lhosts .= "{$host['ip']}	{$host['host']}.{$host['domain']} {$host['host']}\n";
			else
				$lhosts .= "{$host['ip']}	{$host['domain']}\n";
			if (!is_array($host['aliases']) || !is_array($host['aliases']['item']))
				continue;
			foreach ($host['aliases']['item'] as $alias) {
				if ($alias['host'])
					$lhosts .= "{$host['ip']}	{$alias['host']}.{$alias['domain']} {$alias['host']}\n";
				else
					$lhosts .= "{$host['ip']}	{$alias['domain']}\n";
			}
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpd'])) {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf)
				if(is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable']))
						foreach ($dhcpifconf['staticmap'] as $host)
							if ($host['ipaddr'] && $host['hostname'] && $host['domain'])
								$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
							else if ($host['ipaddr'] && $host['hostname'] && $dhcpifconf['domain'])
								$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
							else if ($host['ipaddr'] && $host['hostname'])
								$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpdv6'])) {
			foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf)
				if(is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable']))
						foreach ($dhcpifconf['staticmap'] as $host)
							if ($host['ipaddrv6'] && $host['hostname'] && $host['domain'])
								$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
							else if ($host['ipaddrv6'] && $host['hostname'] && $dhcpifconf['domain'])
								$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
							else if ($host['ipaddrv6'] && $host['hostname'])
								$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
		}

		if (isset($dnsmasqcfg['dhcpfirst']))
			$hosts .= $dhosts . $lhosts;
		else
			$hosts .= $lhosts . $dhosts;
	}

	/*
	 * Do not remove this because dhcpleases monitors with kqueue it needs to be 
	 * killed before writing to hosts files.
	 */
	if (file_exists("{$g['varrun_path']}/dhcpleases.pid")) {
		sigkillbypid("{$g['varrun_path']}/dhcpleases.pid", "TERM");
		@unlink("{$g['varrun_path']}/dhcpleases.pid");
	}
	$fd = fopen("{$g['varetc_path']}/hosts", "w");
	if (!$fd) {
		log_error("Error: cannot open hosts file in system_hosts_generate().\n");
		return 1;
	}
	fwrite($fd, $hosts);
	fclose($fd);

	if (isset($config['unbound']['enable'])) {
		require_once("unbound.inc");
		unbound_hosts_generate();
	}

	system_dhcpleases_configure();

	return 0;
}

function system_dhcpleases_configure() {
	global $config, $g;
	
	if ($g['platform'] == 'jail')
		return;
	/* Start the monitoring process for dynamic dhcpclients. */
	if ((isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcp'])) 
		|| (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcp']))) {
		/* Make sure we do not error out */
		mwexec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/db");
		if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases"))
			@touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");
		if (isvalidpid("{$g['varrun_path']}/dhcpleases.pid"))
			sigkillbypid("{$g['varrun_path']}/dhcpleases.pid", "HUP");
		else {
			/* To ensure we do not start multiple instances of dhcpleases, perform some clean-up first. */
			if (is_process_running("dhcpleases"))
				mwexec('/bin/pkill dhcpleases');
			@unlink("{$g['varrun_path']}/dhcpleases.pid");
			if (isset($config['unbound']['enable']))
				$dns_pid = "unbound.pid";
			else
				$dns_pid = "dnsmasq.pid";
			mwexec("/usr/local/sbin/dhcpleases -l {$g['dhcpd_chroot_path']}/var/db/dhcpd.leases -d {$config['system']['domain']} -p {$g['varrun_path']}/{$dns_pid} -h {$g['varetc_path']}/hosts");
		}
	} else {
		sigkillbypid("{$g['varrun_path']}/dhcpleases.pid", "TERM");
		@unlink("{$g['varrun_path']}/dhcpleases.pid");
	}
}

function system_hostname_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_hostname_configure() being called $mt\n";
	}

	$syscfg = $config['system'];

	/* set hostname */
	$status = mwexec("/bin/hostname " .
		escapeshellarg("{$syscfg['hostname']}.{$syscfg['domain']}"));

    /* Setup host GUID ID.  This is used by ZFS. */
	mwexec("/etc/rc.d/hostid start");

	return $status;
}

function system_routing_configure($interface = "") {
	global $config, $g;
	if ($g['platform'] == 'jail')
		return;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_configure() being called $mt\n";
	}

	$gatewayip = "";
	$interfacegw = "";
	$foundgw = false;
	$gatewayipv6 = "";
	$interfacegwv6 = "";
	$foundgwv6 = false;
	/* tack on all the hard defined gateways as well */
	if (is_array($config['gateways']['gateway_item'])) {
		array_map('unlink', glob("{$g['tmp_path']}/*_defaultgw{,v6}", GLOB_BRACE));
		foreach	($config['gateways']['gateway_item'] as $gateway) {
			if (isset($gateway['defaultgw'])) {
				if ($gateway['ipprotocol'] != "inet6" && (is_ipaddrv4($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
					if(strstr($gateway['gateway'], ":"))
						continue;
					if ($gateway['gateway'] == "dynamic")
						$gateway['gateway'] = get_interface_gateway($gateway['interface']);
					$gatewayip = $gateway['gateway'];
					$interfacegw = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultif = get_real_interface($gateway['interface']);
						if ($defaultif)
							@file_put_contents("{$g['tmp_path']}/{$defaultif}_defaultgw", $gateway['gateway']);
					}
					$foundgw = true;
				} else if ($gateway['ipprotocol'] == "inet6" && (is_ipaddrv6($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
					if ($gateway['gateway'] == "dynamic")
						$gateway['gateway'] = get_interface_gateway_v6($gateway['interface']);
					$gatewayipv6 = $gateway['gateway'];
					$interfacegwv6 = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultifv6 = get_real_interface($gateway['interface']);
						if ($defaultifv6)
							@file_put_contents("{$g['tmp_path']}/{$defaultifv6}_defaultgwv6", $gateway['gateway']);
					}
					$foundgwv6 = true;
				}
			}
			if ($foundgw === true && $foundgwv6 === true)
				break;
		}
	}
	if ($foundgw == false) {
		$defaultif = get_real_interface("wan");
		$interfacegw = "wan";
		$gatewayip = get_interface_gateway("wan");
		@touch("{$g['tmp_path']}/{$defaultif}_defaultgw");
	}	
	if ($foundgwv6 == false) {
		$defaultifv6 = get_real_interface("wan");
		$interfacegwv6 = "wan";
		$gatewayipv6 = get_interface_gateway_v6("wan");
		@touch("{$g['tmp_path']}/{$defaultif}_defaultgwv6");
	}
	$dont_add_route = false;
	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if (is_array($config['installedpackages']['olsrd'])) {
		foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if(($olsrd['enabledyngw'] == "on") && ($olsrd['enable'] == "on")) {
				$dont_add_route = true;
				log_error(sprintf(gettext("Not adding default route because OLSR dynamic gateway is enabled.")));
				break;
			}
		}
	}

	if ($dont_add_route == false ) {
		if (!empty($interface) && $interface != $interfacegw)
			;
		else if (is_ipaddrv4($gatewayip)) {
			log_error("ROUTING: setting default route to $gatewayip");
			mwexec("/sbin/route change -inet default " . escapeshellarg($gatewayip));
		}

		if (!empty($interface) && $interface != $interfacegwv6)
			;
		else if (is_ipaddrv6($gatewayipv6)) {
			$ifscope = "";
			if (is_linklocal($gatewayipv6))
				$ifscope = "%{$defaultifv6}";
			log_error("ROUTING: setting IPv6 default route to {$gatewayipv6}{$ifscope}");
			mwexec("/sbin/route change -inet6 default " . escapeshellarg("{$gatewayipv6}{$ifscope}"));
		}
	}

	system_staticroutes_configure($interface, false);

	return 0;
}

function system_staticroutes_configure($interface = "", $update_dns = false) {
	global $config, $g, $aliastable;

	$filterdns_list = array();

	$static_routes = get_staticroutes(false, true);
	if (count($static_routes)) {
		$gateways_arr = return_gateways_array(false, true);

		foreach ($static_routes as $rtent) {
			if (empty($gateways_arr[$rtent['gateway']])) {
				log_error(sprintf(gettext("Static Routes: Gateway IP could not be found for %s"), $rtent['network']));
				continue;
			}
			$gateway = $gateways_arr[$rtent['gateway']];
			if (!empty($interface) && $interface != $gateway['friendlyiface'])
				continue;

			$gatewayip = $gateway['gateway'];
			$interfacegw = $gateway['interface'];

			$blackhole = "";
			if (!strcasecmp("Null", substr($rtent['gateway'], 0, 3)))
				$blackhole = "-blackhole";

			if (!is_fqdn($rtent['network']) && !is_subnet($rtent['network']))
				continue;

			$dnscache = array();
			if ($update_dns === true) {
				if (is_subnet($rtent['network']))
					continue;
				$dnscache = explode("\n", trim(compare_hostname_to_dnscache($rtent['network'])));
				if (empty($dnscache))
					continue;
			}

			if (is_subnet($rtent['network']))
				$ips = array($rtent['network']);
			else {
				if (!isset($rtent['disabled']))
					$filterdns_list[] = $rtent['network'];
				$ips = add_hostname_to_watch($rtent['network']);
			}

			foreach ($dnscache as $ip) {
				if (in_array($ip, $ips))
					continue;
				mwexec("/sbin/route delete " . escapeshellarg($ip), true);
			}

			if (isset($rtent['disabled'])) {
				/* XXX: This is a bit dangerous in case of routing daemons!? */
				foreach ($ips as $ip)
					mwexec("/sbin/route delete " . escapeshellarg($ip), true);
				continue;
			}

			foreach ($ips as $ip) {
				if (is_ipaddrv4($ip))
					$ip .= "/32";
				else if (is_ipaddrv6($ip))
					$ip .= "/128";

				$inet = (is_subnetv6($ip) ? "-inet6" : "-inet");

				$cmd = "/sbin/route change {$inet} {$blackhole} " . escapeshellarg($ip) . " ";

				if (is_subnet($ip))
					if (is_ipaddr($gatewayip))
						mwexec($cmd . escapeshellarg($gatewayip));
					else if (!empty($interfacegw))
						mwexec($cmd . "-iface " . escapeshellarg($interfacegw));
			}
		}
		unset($gateways_arr);
	}
	unset($static_routes);

	if ($update_dns === false) {
		if (count($filterdns_list)) {
			$interval = 60;
			$hostnames = "";
			array_unique($filterdns_list);
			foreach ($filterdns_list as $hostname)
				$hostnames .= "cmd {$hostname} '/usr/local/sbin/pfSctl -c \"service reload routedns\"'\n";
			file_put_contents("{$g['varetc_path']}/filterdns-route.hosts", $hostnames);
			unset($hostnames);

			if (isvalidpid("{$g['varrun_path']}/filterdns-route.pid"))
				sigkillbypid("{$g['varrun_path']}/filterdns-route.pid", "HUP");
			else
				mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-route.pid -i {$interval} -c {$g['varetc_path']}/filterdns-route.hosts -d 1");
		} else {
			killbypid("{$g['varrun_path']}/filterdns-route.pid");
			@unlink("{$g['varrun_path']}/filterdns-route.pid");
		}
	}
	unset($filterdns_list);

	return 0;
}

function system_routing_enable() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_enable() being called $mt\n";
	}

	set_sysctl(array(
		"net.inet.ip.forwarding" => "1",
		"net.inet6.ip6.forwarding" => "1"
	));

	return;
}

function system_syslogd_fixup_server($server) {
	/* If it's an IPv6 IP alone, encase it in brackets */
	if (is_ipaddrv6($server))
		return "[$server]";
	else
		return $server;
}

function system_syslogd_get_remote_servers($syslogcfg, $facility = "*.*") {
	// Rather than repeatedly use the same code, use this function to build a list of remote servers.
	$facility .= " ".
	$remote_servers = "";
	$pad_to  = 56;
	$padding = ceil(($pad_to - strlen($facility))/8)+1;
	if($syslogcfg['remoteserver'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver']) . "\n";
	if($syslogcfg['remoteserver2'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver2']) . "\n";
	if($syslogcfg['remoteserver3'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver3']) . "\n";
	return $remote_servers;
}

function system_syslogd_start() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_syslogd_start() being called $mt\n";
	}

	mwexec("/etc/rc.d/hostid start");

	$syslogcfg = $config['syslog'];

	if ($g['booting'])
		echo gettext("Starting syslog...");

	if (is_process_running("fifolog_writer"))
		mwexec('/bin/pkill fifolog_writer');

	// Which logging type are we using this week??
	if (isset($config['system']['disablesyslogclog'])) {
		$log_directive = "";
		$log_create_directive = "/usr/bin/touch ";
		$log_size = "";
	} else if (isset($config['system']['usefifolog'])) {
		$log_directive = "|/usr/sbin/fifolog_writer ";
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "10240";
		$log_create_directive = "/usr/sbin/fifolog_create -s ";
	} else { // Defaults to CLOG
		$log_directive = "%";
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "10240";
		$log_create_directive = "/usr/local/sbin/clog -i -s ";
	}

	$syslogd_extra = "";
	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp','ntpd','ntpdate','charon','openvpn','pptps','poes','l2tps','relayd','hostapd','dnsmasq','filterdns','unbound','dhcpd','dhcrelay','dhclient','dhcp6c','apinger','radvd','routed','olsrd','zebra','ospfd','bgpd','miniupnpd','filterlog');
		$syslogconf = "";
		if($config['installedpackages']['package']) {
			foreach($config['installedpackages']['package'] as $package) {
				if($package['logging']) {
					array_push($separatelogfacilities, $package['logging']['facilityname']);
					mwexec("{$log_create_directive} {$log_size} {$g['varlog_path']}/{$package['logging']['logfilename']}");
					$syslogconf .= "!{$package['logging']['facilityname']}\n*.*\t\t\t\t\t\t {$log_directive}{$g['varlog_path']}/{$package['logging']['logfilename']}\n";
				}
			}
		}
		$facilitylist = implode(',', array_unique($separatelogfacilities));
		$syslogconf .= "!radvd,routed,olsrd,zebra,ospfd,bgpd,miniupnpd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/routing.log\n";

		$syslogconf .= "!ntp,ntpd,ntpdate\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ntpd.log\n";

		$syslogconf .= "!ppp\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ppp.log\n";

		$syslogconf .= "!pptps\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/pptps.log\n";

		$syslogconf .= "!poes\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/poes.log\n";

		$syslogconf .= "!l2tps\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/l2tps.log\n";

		$syslogconf .= "!charon\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ipsec.log\n";
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!openvpn\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/openvpn.log\n";
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!apinger\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/gateways.log\n";
		if (isset($syslogcfg['apinger']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!dnsmasq,filterdns,unbound\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/resolver.log\n";

		$syslogconf .= "!dhcpd,dhcrelay,dhclient,dhcp6c\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/dhcpd.log\n";
		if (isset($syslogcfg['dhcp']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!relayd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/relayd.log\n";
		if (isset($syslogcfg['relayd']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!hostapd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/wireless.log\n";
		if (isset($syslogcfg['hostapd']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!filterlog\n";
		$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/filter.log\n";
		if (isset($syslogcfg['filter']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging'])) 
			$syslogconf .= <<<EOD
local3.*							{$log_directive}{$g['varlog_path']}/vpn.log
local4.*							{$log_directive}{$g['varlog_path']}/portalauth.log
local7.*							{$log_directive}{$g['varlog_path']}/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;		{$log_directive}{$g['varlog_path']}/system.log
news.err;local0.none;local3.none;local4.none;			{$log_directive}{$g['varlog_path']}/system.log
local7.none							{$log_directive}{$g['varlog_path']}/system.log
security.*							{$log_directive}{$g['varlog_path']}/system.log
auth.info;authpriv.info;daemon.info				{$log_directive}{$g['varlog_path']}/system.log
auth.info;authpriv.info 					|exec /usr/local/sbin/sshlockout_pf 15
*.emerg								*

EOD;
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
		if (isset($syslogcfg['portalauth']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local4.*");
		if (isset($syslogcfg['dhcp']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local7.*");
		if (isset($syslogcfg['system'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.notice;kern.debug;lpr.info;mail.crit;");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "news.err;local0.none;local3.none;local7.none");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "security.*");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "auth.info;authpriv.info;daemon.info");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.emerg");
		}
		if (isset($syslogcfg['logall'])) {
			// Make everything mean everything, including facilities excluded above.
			$syslogconf .= "!*\n";
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		if (isset($syslogcfg['zmqserver'])) {
				$syslogconf .= <<<EOD
*.*								^{$syslogcfg['zmqserver']}

EOD;
		}
		/* write syslog.conf */		
		if (!@file_put_contents("{$g['varetc_path']}/syslog.conf", $syslogconf)) {
			printf(gettext("Error: cannot open syslog.conf in system_syslogd_start().%s"), "\n");
			unset($syslogconf);
			return 1;
		}
		unset($syslogconf);

		// Ensure that the log directory exists
		if (!is_dir("{$g['dhcpd_chroot_path']}/var/run"))
			exec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/run");

		$sourceip = "";
		if (!empty($syslogcfg['sourceip'])) {
			if ($syslogcfg['ipproto'] == "ipv6") {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ipv6($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr))
					$ifaddr = get_interface_ip($syslogcfg['sourceip']);
			} else {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ip($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr))
					$ifaddr = get_interface_ipv6($syslogcfg['sourceip']);
			}
			if (is_ipaddr($ifaddr)) {
				$sourceip = "-b {$ifaddr}";
			}
		}

		$syslogd_extra = "-f {$g['varetc_path']}/syslog.conf {$sourceip}";
	}

	if (isvalidpid("{$g['varrun_path']}/syslog.pid"))
		sigkillbypid("{$g['varrun_path']}/syslog.pid", "HUP");
	else
		$retval = mwexec_bg("/usr/sbin/syslogd -s -c -c -l {$g['dhcpd_chroot_path']}/var/run/log -P {$g['varrun_path']}/syslog.pid {$syslogd_extra}");

	if ($g['booting'])
		echo gettext("done.") . "\n";

	return $retval;
}

function system_webgui_start() {
	global $config, $g;

	if ($g['booting'])
		echo gettext("Starting webConfigurator...");

	chdir($g['www_path']);

	/* defaults */
	$portarg = "80";
	$crt = "";
	$key = "";
	$ca = "";

	/* non-standard port? */
	if (isset($config['system']['webgui']['port']) && $config['system']['webgui']['port'] <> "")
		$portarg = "{$config['system']['webgui']['port']}";

	if ($config['system']['webgui']['protocol'] == "https") {
		// Ensure that we have a webConfigurator CERT
		$cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
		if(!is_array($cert) && !$cert['crt'] && !$cert['prv']) {
			if (!is_array($config['ca']))
				$config['ca'] = array();
			$a_ca =& $config['ca'];
			if (!is_array($config['cert']))
				$config['cert'] = array();
			$a_cert =& $config['cert'];
			log_error("Creating SSL Certificate for this host");
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = gettext("webConfigurator default");
			mwexec("/usr/bin/openssl genrsa 1024 > {$g['tmp_path']}/ssl.key");
			mwexec("/usr/bin/openssl req -new -x509 -nodes -sha256 -days 2000 -key {$g['tmp_path']}/ssl.key > {$g['tmp_path']}/ssl.crt");
			$crt = file_get_contents("{$g['tmp_path']}/ssl.crt");
			$key = file_get_contents("{$g['tmp_path']}/ssl.key");
			unlink("{$g['tmp_path']}/ssl.key");
			unlink("{$g['tmp_path']}/ssl.crt");
			cert_import($cert, $crt, $key);
			$a_cert[] = $cert;
			$config['system']['webgui']['ssl-certref'] = $cert['refid'];
			write_config(gettext("Importing HTTPS certificate"));
			if(!$config['system']['webgui']['port'])
				$portarg = "443";
			$ca = ca_chain($cert);
		} else {
			$crt = base64_decode($cert['crt']);
			$key = base64_decode($cert['prv']);
			if(!$config['system']['webgui']['port'])
				$portarg = "443";
			$ca = ca_chain($cert);
		}
	}

	/* generate lighttpd configuration */
	system_generate_lighty_config("{$g['varetc_path']}/lighty-webConfigurator.conf",
		$crt, $key, $ca, "lighty-webConfigurator.pid", $portarg, "/usr/local/www/",
		"cert.pem", "ca.pem");

	/* kill any running lighttpd */
	killbypid("{$g['varrun_path']}/lighty-webConfigurator.pid");

	sleep(1);

	@unlink("{$g['varrun_path']}/lighty-webConfigurator.pid");

	/* attempt to start lighthttpd */
	$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-webConfigurator.conf");

	if ($g['booting']) {
		if ($res == 0)
			echo gettext("done.") . "\n";
		else
			echo gettext("failed!") . "\n";
	}

	return $res;
}

function system_generate_lighty_config($filename,
	$cert,
	$key,
	$ca,
	$pid_file,
	$port = 80,
	$document_root = "/usr/local/www/",
	$cert_location = "cert.pem",
	$ca_location = "ca.pem",
	$captive_portal = false) {

	global $config, $g;

	if(!is_dir("{$g['tmp_path']}/lighttpdcompress"))
		mkdir("{$g['tmp_path']}/lighttpdcompress");

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_generate_lighty_config() being called $mt\n";
	}

	if ($captive_portal !== false)  {
		$captiveportal = ",\"mod_rewrite\",\"mod_evasive\"";
		$captive_portal_rewrite = "url.rewrite-once = ( \"(.*captiveportal.*)\" => \"$1\", \"(.*)\" => \"/index.php?zone={$captive_portal}&redirurl=$1\" )\n";

		$maxprocperip = $config['captiveportal'][$captive_portal]['maxprocperip'];
		if (empty($maxprocperip))
			$maxprocperip = 10;
		$captive_portal_mod_evasive = "evasive.max-conns-per-ip = {$maxprocperip}";

		$server_upload_dirs = "server.upload-dirs = ( \"{$g['tmp_path']}/captiveportal/\" )\n";
		if(!is_dir("{$g['tmp_path']}/captiveportal"))
			@mkdir("{$g['tmp_path']}/captiveportal", 0555);
		$server_max_request_size = "server.max-request-size    = 384";
		$cgi_config = "";
	} else {
		$captiveportal = ",\"mod_cgi\"";
		$captive_portal_rewrite = "";
		$captive_portal_mod_evasive = "";
		$server_upload_dirs = "server.upload-dirs = ( \"{$g['upload_path']}/\", \"{$g['tmp_path']}/\", \"/var/\" )\n";
		$server_max_request_size = "server.max-request-size    = 2097152";
		$cgi_config = "cgi.assign                 = ( \".cgi\" => \"\" )";
	}
	
	if (empty($port))
		$lighty_port = "80";
	else
		$lighty_port = $port;

	$memory = get_memory();
	$realmem = $memory[1];

	// Determine web GUI process settings and take into account low memory systems
	if ($realmem < 255)
		$max_procs = 1;
	else
		$max_procs = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;

	// Ramp up captive portal max procs, assuming each PHP process can consume up to 64MB RAM 
	if ($captive_portal !== false)  {
		if ($realmem > 135 and $realmem < 256) {
			$max_procs += 1; // 2 worker processes
		} else if ($realmem > 255 and $realmem < 513) {
			$max_procs += 2; // 3 worker processes
		} else if ($realmem > 512) {
			$max_procs += 4; // 6 worker processes
		}
		if ($max_procs > 1)
			$max_php_children = intval($max_procs/2);
		else
			$max_php_children = 1;

	} else {
		if ($realmem < 78)
			$max_php_children = 0;
		else
			$max_php_children = 1;
	}

	if(!isset($config['syslog']['nologlighttpd'])) {
		$lighty_use_syslog = <<<EOD
## where to send error-messages to
server.errorlog-use-syslog="enable"
EOD;
	}


	if ($captive_portal !== false) {
		$fast_cgi_path = "{$g['tmp_path']}/php-fastcgi-{$captive_portal}.socket";
		$fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server = ( ".php" =>
	( "localhost" =>
		(
			"socket" => "{$fast_cgi_path}",
			"max-procs" => {$max_procs},
			"bin-environment" => (
				"PHP_FCGI_CHILDREN" => "{$max_php_children}",
				"PHP_FCGI_MAX_REQUESTS" => "500"
			),
			"bin-path" => "/usr/local/bin/php"
		)
	)
)

EOD;
	} else {
		$fast_cgi_path = "{$g['varrun_path']}/php-fpm.socket";
		$fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server = ( ".php" =>
	( "localhost" =>
		(
			"socket" => "{$fast_cgi_path}",
			"broken-scriptfilename" => "enable"
		)
	)
)

EOD;
	}


	$lighty_config = <<<EOD
#
# lighttpd configuration file
#
# use a it as base for lighttpd 1.0.0 and above
#
############ Options you really have to take care of ####################

## FreeBSD!
server.event-handler	= "freebsd-kqueue"
server.network-backend 	= "writev"
#server.use-ipv6 = "enable"

## modules to load
server.modules              =   ( "mod_access", "mod_expire", "mod_compress", "mod_redirect",
	{$captiveportal}, "mod_fastcgi"
)

server.max-keep-alive-requests = 15
server.max-keep-alive-idle = 30

## a static document-root, for virtual-hosting take look at the
## server.virtual-* options
server.document-root        = "{$document_root}"
{$captive_portal_rewrite}

# Maximum idle time with nothing being written (php downloading)
server.max-write-idle = 999

{$lighty_use_syslog}

# files to check for if .../ is requested
server.indexfiles           = ( "index.php", "index.html",
                                "index.htm", "default.htm" )

# mimetype mapping
mimetype.assign             = (
  ".pdf"          =>      "application/pdf",
  ".sig"          =>      "application/pgp-signature",
  ".spl"          =>      "application/futuresplash",
  ".class"        =>      "application/octet-stream",
  ".ps"           =>      "application/postscript",
  ".torrent"      =>      "application/x-bittorrent",
  ".dvi"          =>      "application/x-dvi",
  ".gz"           =>      "application/x-gzip",
  ".pac"          =>      "application/x-ns-proxy-autoconfig",
  ".swf"          =>      "application/x-shockwave-flash",
  ".tar.gz"       =>      "application/x-tgz",
  ".tgz"          =>      "application/x-tgz",
  ".tar"          =>      "application/x-tar",
  ".zip"          =>      "application/zip",
  ".mp3"          =>      "audio/mpeg",
  ".m3u"          =>      "audio/x-mpegurl",
  ".wma"          =>      "audio/x-ms-wma",
  ".wax"          =>      "audio/x-ms-wax",
  ".ogg"          =>      "audio/x-wav",
  ".wav"          =>      "audio/x-wav",
  ".gif"          =>      "image/gif",
  ".jpg"          =>      "image/jpeg",
  ".jpeg"         =>      "image/jpeg",
  ".png"          =>      "image/png",
  ".xbm"          =>      "image/x-xbitmap",
  ".xpm"          =>      "image/x-xpixmap",
  ".xwd"          =>      "image/x-xwindowdump",
  ".css"          =>      "text/css",
  ".html"         =>      "text/html",
  ".htm"          =>      "text/html",
  ".js"           =>      "text/javascript",
  ".asc"          =>      "text/plain",
  ".c"            =>      "text/plain",
  ".conf"         =>      "text/plain",
  ".text"         =>      "text/plain",
  ".txt"          =>      "text/plain",
  ".dtd"          =>      "text/xml",
  ".xml"          =>      "text/xml",
  ".mpeg"         =>      "video/mpeg",
  ".mpg"          =>      "video/mpeg",
  ".mov"          =>      "video/quicktime",
  ".qt"           =>      "video/quicktime",
  ".avi"          =>      "video/x-msvideo",
  ".asf"          =>      "video/x-ms-asf",
  ".asx"          =>      "video/x-ms-asf",
  ".wmv"          =>      "video/x-ms-wmv",
  ".bz2"          =>      "application/x-bzip",
  ".tbz"          =>      "application/x-bzip-compressed-tar",
  ".tar.bz2"      =>      "application/x-bzip-compressed-tar"
 )

# Use the "Content-Type" extended attribute to obtain mime type if possible
#mimetypes.use-xattr        = "enable"

## deny access the file-extensions
#
# ~    is for backupfiles from vi, emacs, joe, ...
# .inc is often used for code includes which should in general not be part
#      of the document-root
url.access-deny             = ( "~", ".inc" )


######### Options that are good to be but not neccesary to be changed #######

## bind to port (default: 80)

EOD;

	$lighty_config .= "server.bind  = \"0.0.0.0\"\n";
	$lighty_config .= "server.port  = {$lighty_port}\n";
	$lighty_config .= "\$SERVER[\"socket\"]  == \"0.0.0.0:{$lighty_port}\" { }\n";
	$lighty_config .= "\$SERVER[\"socket\"]  == \"[::]:{$lighty_port}\" { \n";
	if($cert <> "" and $key <> "") {
		$lighty_config .= "\n";
		$lighty_config .= "## ssl configuration\n";
		$lighty_config .= "ssl.engine = \"enable\"\n";
		$lighty_config .= "ssl.pemfile = \"{$g['varetc_path']}/{$cert_location}\"\n\n";
		if($ca <> "")
			$lighty_config .= "ssl.ca-file = \"{$g['varetc_path']}/{$ca_location}\"\n\n";
	}
	$lighty_config .= " }\n";


	$lighty_config .= <<<EOD

## error-handler for status 404
#server.error-handler-404   = "/error-handler.html"
#server.error-handler-404   = "/error-handler.php"

## to help the rc.scripts
server.pid-file            = "{$g['varrun_path']}/{$pid_file}"

## virtual directory listings
server.dir-listing         = "disable"

## enable debugging
debug.log-request-header   = "disable"
debug.log-response-header  = "disable"
debug.log-request-handling = "disable"
debug.log-file-not-found   = "disable"

# gzip compression
compress.cache-dir = "{$g['tmp_path']}/lighttpdcompress/"
compress.filetype  = ("text/plain","text/css", "text/xml", "text/javascript" )

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

{$captive_portal_mod_evasive}

expire.url = (
				"" => "access 50 hours",	
        )

EOD;

	$cert = str_replace("\r", "", $cert);
	$key = str_replace("\r", "", $key);
	$ca = str_replace("\r", "", $ca);

	$cert = str_replace("\n\n", "\n", $cert);
	$key = str_replace("\n\n", "\n", $key);
	$ca = str_replace("\n\n", "\n", $ca);

	if($cert <> "" and $key <> "") {
		$fd = fopen("{$g['varetc_path']}/{$cert_location}", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open cert.pem in system_webgui_start().%s"), "\n");
			return 1;
		}
		chmod("{$g['varetc_path']}/{$cert_location}", 0600);
		fwrite($fd, $cert);
		fwrite($fd, "\n");
		fwrite($fd, $key);
		fclose($fd);
		if(!(empty($ca) || (strlen(trim($ca)) == 0))) {
			$fd = fopen("{$g['varetc_path']}/{$ca_location}", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open ca.pem in system_webgui_start().%s"), "\n");
				return 1;
			}
			chmod("{$g['varetc_path']}/{$ca_location}", 0600);
			fwrite($fd, $ca);
			fclose($fd);
		}
		$lighty_config .= "\n";
		$lighty_config .= "## " . gettext("ssl configuration") . "\n";
		$lighty_config .= "ssl.engine = \"enable\"\n";
		$lighty_config .= "ssl.pemfile = \"{$g['varetc_path']}/{$cert_location}\"\n\n";

		// Harden SSL a bit for PCI conformance testing
		$lighty_config .= "ssl.use-sslv2 = \"disable\"\n";

		/* Hifn accelerators do NOT work with the BEAST mitigation code. Do not allow it to be enabled if a Hifn card has been detected. */
		$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
		if ($fd) {
			while (!feof($fd)) {
				$dmesgl = fgets($fd);
				if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches) && isset($config['system']['webgui']['beast_protection'])) {
						unset($config['system']['webgui']['beast_protection']);
						log_error("BEAST Protection disabled because a conflicting cryptographic accelerator card has been detected (" . $matches[1] . ")");
					break;
				}
			}
			fclose($fd);
		}

		if (isset($config['system']['webgui']['beast_protection'])) {
			$lighty_config .= "ssl.honor-cipher-order = \"enable\"\n";
			$lighty_config .= "ssl.cipher-list = \"ECDHE-RSA-AES256-SHA384:AES256-SHA256:RC4-SHA:RC4:HIGH:!MD5:!aNULL:!EDH:!AESGCM\"\n";
		} else {
			$lighty_config .= "ssl.cipher-list = \"DHE-RSA-CAMELLIA256-SHA:DHE-DSS-CAMELLIA256-SHA:CAMELLIA256-SHA:DHE-DSS-AES256-SHA:AES256-SHA:DHE-RSA-CAMELLIA128-SHA:DHE-DSS-CAMELLIA128-SHA:CAMELLIA128-SHA:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA:AES128-SHA:RC4-SHA:RC4-MD5:!aNULL:!eNULL:!3DES:@STRENGTH\"\n";
		}

		if(!(empty($ca) || (strlen(trim($ca)) == 0)))
			$lighty_config .= "ssl.ca-file = \"{$g['varetc_path']}/{$ca_location}\"\n\n";
	}

	// Add HTTP to HTTPS redirect	
	if ($captive_portal === false && $config['system']['webgui']['protocol'] == "https" && !isset($config['system']['webgui']['disablehttpredirect'])) {
		if($lighty_port != "443") 
			$redirectport = ":{$lighty_port}";
		$lighty_config .= <<<EOD
\$SERVER["socket"] == ":80" {
	\$HTTP["host"] =~ "(.*)" {
		url.redirect = ( "^/(.*)" => "https://%1{$redirectport}/$1" )
	}
}
\$SERVER["socket"] == "[::]:80" {
	\$HTTP["host"] =~ "(.*)" {
		url.redirect = ( "^/(.*)" => "https://%1{$redirectport}/$1" )
	}
}
EOD;
	}

	$fd = fopen("{$filename}", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open %s in system_generate_lighty_config().%s"), $filename, "\n");
		return 1;
	}
	fwrite($fd, $lighty_config);
	fclose($fd);

	return 0;

}

function system_timezone_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_timezone_configure() being called $mt\n";
	}

	$syscfg = $config['system'];

	if ($g['booting'])
		echo gettext("Setting timezone...");

	/* extract appropriate timezone file */
	$timezone = $syscfg['timezone'];
	if ($timezone) {
		exec('/usr/bin/tar -tvzf /usr/share/zoneinfo.tgz', $tzs);
		foreach ($tzs as $tz) {
			if (preg_match(",{$timezone}$,", $tz))
				break;
			if (preg_match(",{$timezone} link to *(.*)$,", $tz, $matches)) {
				$timezone = $matches[1];
				break;
			}
		}
	} else
		$timezone = "Etc/UTC";

	conf_mount_rw();

	exec("LANG=C /usr/bin/tar xzfO /usr/share/zoneinfo.tgz " .
		escapeshellarg($timezone) . " > /etc/localtime");

	mwexec("sync");
	conf_mount_ro();

	if ($g['booting'])
		echo gettext("done.") . "\n";
}

function system_ntp_setup_gps($serialport) {
	global $config, $g;
	$gps_device = '/dev/gps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport))
		return false;

	conf_mount_rw();
	// Create symlink that ntpd requires
	unlink_if_exists($gps_device);
	symlink($serialport, $gps_device);

	/* Send the following to the GPS port to initialize the GPS */
	if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['type'])) {
		$gps_init = base64_decode($config['ntpd']['gps']['initcmd']);
	}else{
		$gps_init = base64_decode('JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ==');
	}

	/* XXX: Why not file_put_contents to the device */
	@file_put_contents('/tmp/gps.init', $gps_init);
	`cat /tmp/gps.init > $serialport`;

	/* Add /etc/remote entry in case we need to read from the GPS with tip */
	if (intval(`grep -c '^gps0' /etc/remote`) == 0) {
		$gpsbaud = '4800';
		if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['speed'])) {
			switch($config['ntpd']['gps']['speed']) {
				case '16':
					$gpsbaud = '9600';
					break;
				case '32':
					$gpsbaud = '19200';
					break;
				case '48':
					$gpsbaud = '38400';
					break;
				case '64':
					$gpsbaud = '57600';
					break;
				case '80':
					$gpsbaud = '115200';
					break;
			}
		}
		@file_put_contents("/etc/remote", "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:", FILE_APPEND);
	}

	conf_mount_ro();

	return true;
}

function system_ntp_setup_pps($serialport) {
	global $config, $g;

	$pps_device = '/dev/pps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport))
		return false;

	conf_mount_rw();
	// Create symlink that ntpd requires
	unlink_if_exists($pps_device);
	@symlink($serialport, $pps_device);

	conf_mount_ro();

	return true;
}


function system_ntp_configure($start_ntpd=true) {
	global $config, $g;

	$driftfile = "/var/db/ntpd.drift";
	$statsdir = "/var/log/ntp";
	$gps_device = '/dev/gps0';

	if ($g['platform'] == 'jail')
		return;

	safe_mkdir($statsdir);

	if (!is_array($config['ntpd']))
		$config['ntpd'] = array();

	$ntpcfg = "# \n";
	$ntpcfg .= "# pfSense ntp configuration file \n";
	$ntpcfg .= "# \n\n";
	$ntpcfg .= "tinker panic 0 \n";

	/* Add Orphan mode */
	$ntpcfg .= "# Orphan mode stratum\n";
	$ntpcfg .= 'tos orphan ';
	if (!empty($config['ntpd']['orphan'])) {
		$ntpcfg .= $config['ntpd']['orphan'];
	}else{
		$ntpcfg .= '12';
	}
	$ntpcfg .= "\n";

	/* Add PPS configuration */
	if (!empty($config['ntpd']['pps'])
		&& file_exists('/dev/'.$config['ntpd']['pps']['port'])
		&& system_ntp_setup_pps($config['ntpd']['pps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# PPS Setup\n";
		$ntpcfg .= 'server 127.127.22.0';
		$ntpcfg .= ' minpoll 4 maxpoll 4';
		if (empty($config['ntpd']['pps']['prefer'])) { /*note: this one works backwards */
			$ntpcfg .= ' prefer'; 
		}
		if (!empty($config['ntpd']['pps']['noselect'])) {
			$ntpcfg .= ' noselect ';
		}
		$ntpcfg .= "\n";
		$ntpcfg .= 'fudge 127.127.22.0';
		if (!empty($config['ntpd']['pps']['fudge1'])) {
			$ntpcfg .= ' time1 ';
			$ntpcfg .= $config['ntpd']['pps']['fudge1'];
		}
		if (!empty($config['ntpd']['pps']['flag2'])) {
			$ntpcfg .= ' flag2 1';
		}
		if (!empty($config['ntpd']['pps']['flag3'])) {
			$ntpcfg .= ' flag3 1';
		}else{
			$ntpcfg .= ' flag3 0';
		}
		if (!empty($config['ntpd']['pps']['flag4'])) {
			$ntpcfg .= ' flag4 1';
		}
		if (!empty($config['ntpd']['pps']['refid'])) {
			$ntpcfg .= ' refid ';
			$ntpcfg .= $config['ntpd']['pps']['refid'];
		}
		$ntpcfg .= "\n";
	}
	/* End PPS configuration */

	/* Add GPS configuration */
	if (!empty($config['ntpd']['gps'])
		&& file_exists('/dev/'.$config['ntpd']['gps']['port'])
		&& system_ntp_setup_gps($config['ntpd']['gps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# GPS Setup\n";
		$ntpcfg .= 'server 127.127.20.0 mode ';
		if (!empty($config['ntpd']['gps']['nmea']) || !empty($config['ntpd']['gps']['speed']) || !empty($config['ntpd']['gps']['subsec'])) {
			if (!empty($config['ntpd']['gps']['nmea'])) {
				$ntpmode = (int) $config['ntpd']['gps']['nmea'];
			}
			if (!empty($config['ntpd']['gps']['speed'])) {
				$ntpmode += (int) $config['ntpd']['gps']['speed'];
			}
			if (!empty($config['ntpd']['gps']['subsec'])) {
				$ntpmode += 128;
			}
			$ntpcfg .= (string) $ntpmode;
		}else{
			$ntpcfg .= '0';
		}
		$ntpcfg .= ' minpoll 4 maxpoll 4';
		if (empty($config['ntpd']['gps']['prefer'])) { /*note: this one works backwards */
			$ntpcfg .= ' prefer'; 
		}
		if (!empty($config['ntpd']['gps']['noselect'])) {
			$ntpcfg .= ' noselect ';
		}
		$ntpcfg .= "\n";
		$ntpcfg .= 'fudge 127.127.20.0';
		if (!empty($config['ntpd']['gps']['fudge1'])) {
			$ntpcfg .= ' time1 ';
			$ntpcfg .= $config['ntpd']['gps']['fudge1'];
		}
		if (!empty($config['ntpd']['gps']['fudge2'])) {
			$ntpcfg .= ' time2 ';
			$ntpcfg .= $config['ntpd']['gps']['fudge2'];
		}
		if (!empty($config['ntpd']['gps']['flag1'])) {
			$ntpcfg .= ' flag1 1';
		}else{
			$ntpcfg .= ' flag1 0';
		}
		if (!empty($config['ntpd']['gps']['flag2'])) {
			$ntpcfg .= ' flag2 1';
		}
		if (!empty($config['ntpd']['gps']['flag3'])) {
			$ntpcfg .= ' flag3 1';
		}else{
			$ntpcfg .= ' flag3 0';
		}
		if (!empty($config['ntpd']['gps']['flag4'])) {
			$ntpcfg .= ' flag4 1';
		}
		if (!empty($config['ntpd']['gps']['refid'])) {
			$ntpcfg .= ' refid ';
			$ntpcfg .= $config['ntpd']['gps']['refid'];
		}
		$ntpcfg .= "\n";
	}elseif (!empty($config['ntpd']['gpsport'])
		&& file_exists('/dev/'.$config['ntpd']['gpsport'])
		&& system_ntp_setup_gps($config['ntpd']['gpsport'])) {
		/* This handles a 2.1 and earlier config */
		$ntpcfg .= "# GPS Setup\n";
		$ntpcfg .= "server 127.127.20.0 mode 0 minpoll 4 maxpoll 4 prefer\n";
		$ntpcfg .= "fudge 127.127.20.0 time1 0.155 time2 0.000 flag1 1 flag2 0 flag3 1\n";
		// Fall back to local clock if GPS is out of sync?
		$ntpcfg .= "server 127.127.1.0\n";
		$ntpcfg .= "fudge 127.127.1.0 stratum 12\n";
	}
	/* End GPS configuration */
	
	$ntpcfg .= "\n\n# Upstream Servers\n";
	/* foreach through ntp servers and write out to ntpd.conf */
	foreach (explode(' ', $config['system']['timeservers']) as $ts) {
		$ntpcfg .= "server {$ts} iburst maxpoll 9";
		if (substr_count($config['ntpd']['prefer'], $ts)) $ntpcfg .= ' prefer';
		if (substr_count($config['ntpd']['noselect'], $ts)) $ntpcfg .= ' noselect';
		$ntpcfg .= "\n";
	}
	unset($ts);

	$ntpcfg .= "\n\n";
	$ntpcfg .= "disable monitor\n"; //prevent NTP reflection attack, see https://forum.pfsense.org/index.php/topic,67189.msg389132.html#msg389132
	if (!empty($config['ntpd']['clockstats']) || !empty($config['ntpd']['loopstats']) || !empty($config['ntpd']['peerstats'])) {
		$ntpcfg .= "enable stats\n";
		$ntpcfg .= 'statistics';
		if (!empty($config['ntpd']['clockstats'])) {
			$ntpcfg .= ' clockstats';
		}
		if (!empty($config['ntpd']['loopstats'])) {
			$ntpcfg .= ' loopstats';
		}
		if (!empty($config['ntpd']['peerstats'])) {
			$ntpcfg .= ' peerstats';
		}
		$ntpcfg .= "\n";
	}
	$ntpcfg .= "statsdir {$statsdir}\n";
	$ntpcfg .= 'logconfig =syncall +clockall';
	if (!empty($config['ntpd']['logpeer'])) {
		$ntpcfg .= ' +peerall';
	}
	if (!empty($config['ntpd']['logsys'])) {
		$ntpcfg .= ' +sysall';
	}
	$ntpcfg .= "\n";
	$ntpcfg .= "driftfile {$driftfile}\n";
	/* Access restrictions */
	$ntpcfg .= 'restrict default';
	if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
		$ntpcfg .= ' kod limited'; 
	}
	if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
		$ntpcfg .= ' nomodify'; 
	}
	if (!empty($config['ntpd']['noquery'])) {
		$ntpcfg .= ' noquery';
	}
	if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
		$ntpcfg .= ' nopeer'; 
	}
	if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
		$ntpcfg .= ' notrap'; 
	}
	if (!empty($config['ntpd']['noserve'])) {
		$ntpcfg .= ' noserve';
	}
	$ntpcfg .= "\nrestrict -6 default";
	if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
		$ntpcfg .= ' kod limited'; 
	}
	if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
		$ntpcfg .= ' nomodify'; 
	}
	if (!empty($config['ntpd']['noquery'])) {
		$ntpcfg .= ' noquery';
	}
	if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
		$ntpcfg .= ' nopeer'; 
	}
	if (!empty($config['ntpd']['noserve'])) {
		$ntpcfg .= ' noserve';
	}
	if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
		$ntpcfg .= ' notrap'; 
	}
	$ntpcfg .= "\n";

	/* A leapseconds file is really only useful if this clock is stratum 1 */
	$ntpcfg .= "\n";
	if (!empty($config['ntpd']['leapsec'])) {
		$leapsec .= base64_decode($config['ntpd']['leapsec']);
		file_put_contents('/var/db/leap-seconds', $leapsec);
		$ntpcfg .= "leapfile /var/db/leap-seconds\n";
	}
	

	if (empty($config['ntpd']['interface']))
		if (is_array($config['installedpackages']['openntpd']) && !empty($config['installedpackages']['openntpd']['config'][0]['interface']))
			$interfaces = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		else
			$interfaces = array();
	else
		$interfaces = explode(",", $config['ntpd']['interface']);

	if (is_array($interfaces) && count($interfaces)) {
		$ntpcfg .= "interface ignore all\n";
		foreach ($interfaces as $interface) {
			if (!is_ipaddr($interface)) {
				$interface = get_real_interface($interface);
			}
			if (!empty($interface))
				$ntpcfg .= "interface listen {$interface}\n";
		}
	}

	/* open configuration for wrting or bail */
	if (!@file_put_contents("{$g['varetc_path']}/ntpd.conf", $ntpcfg)) {
		log_error("Could not open {$g['varetc_path']}/ntpd.conf for writing");
		return;
	}

	/* At bootup we just want to write out the config. */
	if (!$start_ntpd)
		return;

	/* if ntpd is running, kill it */
	while (isvalidpid("{$g['varrun_path']}/ntpd.pid")) {
		killbypid("{$g['varrun_path']}/ntpd.pid");
	}
	@unlink("{$g['varrun_path']}/ntpd.pid");

	/* if /var/empty does not exist, create it */
	if(!is_dir("/var/empty"))
		mkdir("/var/empty", 0775, true);

	/* start opentpd, set time now and use /var/etc/ntpd.conf */
	mwexec("/usr/local/sbin/ntpd -g -c {$g['varetc_path']}/ntpd.conf -p {$g['varrun_path']}/ntpd.pid", false, true);
	
	// Note that we are starting up
	log_error("NTPD is starting up.");
	return;
}

function sync_system_time() {
	global $config, $g;

	if ($g['booting'])
		echo gettext("Syncing system time before startup...");

	/* foreach through servers and write out to ntpd.conf */
	foreach (explode(' ', $config['system']['timeservers']) as $ts) {
		mwexec("/usr/local/sbin/ntpdate -s $ts");
	}
	
	if ($g['booting'])
		echo gettext("done.") . "\n";
	
}

function system_halt() {
	global $g;

	system_reboot_cleanup();

	mwexec("/usr/bin/nohup /etc/rc.halt > /dev/null 2>&1 &");
}

function system_reboot() {
	global $g;

	system_reboot_cleanup();

	mwexec("nohup /etc/rc.reboot > /dev/null 2>&1 &");
}

function system_reboot_sync() {
	global $g;

	system_reboot_cleanup();

	mwexec("/etc/rc.reboot > /dev/null 2>&1");
}

function system_reboot_cleanup() {
	global $config, $cpzone;

	mwexec("/usr/local/bin/beep.sh stop");
	require_once("captiveportal.inc");
	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone=>$cp) {
			captiveportal_radius_stop_all();
			captiveportal_send_server_accounting(true);
		}
	}
	require_once("voucher.inc");
	voucher_save_db_to_config();
	require_once("pkg-utils.inc");
	stop_packages();
}

function system_do_shell_commands($early = 0) {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_do_shell_commands() being called $mt\n";
	}

	if ($early)
		$cmdn = "earlyshellcmd";
	else
		$cmdn = "shellcmd";

	if (is_array($config['system'][$cmdn])) {

		/* *cmd is an array, loop through */
		foreach ($config['system'][$cmdn] as $cmd) {
			exec($cmd);
		}

	} elseif($config['system'][$cmdn] <> "") {

		/* execute single item */
		exec($config['system'][$cmdn]);

	}
}

function system_console_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_console_configure() being called $mt\n";
	}

	if (isset($config['system']['disableconsolemenu'])) {
		touch("{$g['varetc_path']}/disableconsole");
	} else {
		unlink_if_exists("{$g['varetc_path']}/disableconsole");
	}
}

function system_dmesg_save() {
	global $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_dmesg_save() being called $mt\n";
	}

	$dmesg = "";
	$_gb = exec("/sbin/dmesg", $dmesg);

	/* find last copyright line (output from previous boots may be present) */
	$lastcpline = 0;

	for ($i = 0; $i < count($dmesg); $i++) {
		if (strstr($dmesg[$i], "Copyright (c) 1992-"))
			$lastcpline = $i;
	}

	$fd = fopen("{$g['varlog_path']}/dmesg.boot", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open dmesg.boot in system_dmesg_save().%s"), "\n");
		return 1;
	}

	for ($i = $lastcpline; $i < count($dmesg); $i++)
		fwrite($fd, $dmesg[$i] . "\n");

	fclose($fd);
	unset($dmesg);

	return 0;
}

function system_set_harddisk_standby() {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_set_harddisk_standby() being called $mt\n";
	}

	if (isset($config['system']['harddiskstandby'])) {
		if ($g['booting']) {
			echo gettext('Setting hard disk standby... ');
		}

		$standby = $config['system']['harddiskstandby'];
		// Check for a numeric value
		if (is_numeric($standby)) {
			// Sync the disk(s)
			pfSense_sync();
			if (set_single_sysctl('hw.ata.standby', (int)$standby)) {
				// Reinitialize ATA-drives
				mwexec('/usr/local/sbin/atareinit');
				if ($g['booting']) {
					echo gettext("done.") . "\n";
				}
			} else if ($g['booting']) {
				echo gettext("failed!") . "\n";
			}
		} else if ($g['booting']) {
			echo gettext("failed!") . "\n";
		}
	}
}

function system_setup_sysctl() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_setup_sysctl() being called $mt\n";
	}

	activate_sysctls();	

	if (isset($config['system']['sharednet'])) {
		system_disable_arp_wrong_if();
	}
}

function system_disable_arp_wrong_if() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_disable_arp_wrong_if() being called $mt\n";
	}
	set_sysctl(array(
		"net.link.ether.inet.log_arp_wrong_iface" => "0",
		"net.link.ether.inet.log_arp_movements" => "0"
	));
}

function system_enable_arp_wrong_if() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_enable_arp_wrong_if() being called $mt\n";
	}
	set_sysctl(array(
		"net.link.ether.inet.log_arp_wrong_iface" => "1",
		"net.link.ether.inet.log_arp_movements" => "1"
	));
}

function enable_watchdog() {
	global $config;
	return;
	$install_watchdog = false;
	$supported_watchdogs = array("Geode");
	$file = file_get_contents("/var/log/dmesg.boot");
	foreach($supported_watchdogs as $sd) {
		if(stristr($file, "Geode")) {
			$install_watchdog = true;
		}
	}
	if($install_watchdog == true) {
		if(is_process_running("watchdogd"))
			mwexec("/usr/bin/killall watchdogd", true);
		exec("/usr/sbin/watchdogd");
	}
}

function system_check_reset_button() {
	global $g;
	if($g['platform'] != "nanobsd")
		return 0;

	$specplatform = system_identify_specific_platform();

	if ($specplatform['name'] != "wrap" && $specplatform['name'] != "alix")
		return 0;

	$retval = mwexec("/usr/local/sbin/" . $specplatform['name'] . "resetbtn");

	if ($retval == 99) {
		/* user has pressed reset button for 2 seconds - 
		   reset to factory defaults */
		echo <<<EOD

***********************************************************************
* Reset button pressed - resetting configuration to factory defaults. *
* The system will reboot after this completes.                        *
***********************************************************************


EOD;
		
		reset_factory_defaults();
		system_reboot_sync();
		exit(0);
	}

	return 0;
}

/* attempt to identify the specific platform (for embedded systems)
   Returns an array with two elements:
	name => platform string (e.g. 'wrap', 'alix' etc.)
	descr => human-readable description (e.g. "PC Engines WRAP")
*/
function system_identify_specific_platform() {
	global $g;
	
	if ($g['platform'] == 'generic-pc')
		return array('name' => 'generic-pc', 'descr' => gettext("Generic PC"));
	
	if ($g['platform'] == 'generic-pc-cdrom')
		return array('name' => 'generic-pc-cdrom', 'descr' => gettext("Generic PC (CD-ROM)"));
	
	/* the rest of the code only deals with 'embedded' platforms */
	if ($g['platform'] != 'nanobsd')
		return array('name' => $g['platform'], 'descr' => $g['platform']);

	$dmesg = get_single_sysctl('hw.model');

	if (strpos($dmesg, "PC Engines WRAP") !== false)
		return array('name' => 'wrap', 'descr' => gettext('PC Engines WRAP'));
	
	if (strpos($dmesg, "PC Engines ALIX") !== false)
		return array('name' => 'alix', 'descr' => gettext('PC Engines ALIX'));

	if (preg_match("/Soekris net45../", $dmesg, $matches))
		return array('name' => 'net45xx', 'descr' => $matches[0]);
	
	if (preg_match("/Soekris net48../", $dmesg, $matches))
		return array('name' => 'net48xx', 'descr' => $matches[0]);
		
	if (preg_match("/Soekris net55../", $dmesg, $matches))
		return array('name' => 'net55xx', 'descr' => $matches[0]);
	
	/* unknown embedded platform */
	return array('name' => 'embedded', 'descr' => gettext('embedded (unknown)'));
}

function system_get_dmesg_boot() {
	global $g;
		
	return file_get_contents("{$g['varlog_path']}/dmesg.boot");
}

function get_possible_listen_ips($include_ipv6_link_local=false) {
	$interfaces = get_configured_interface_with_descr();
	$carplist = get_configured_carp_interface_list();
	$listenips = array();
	foreach ($carplist as $cif => $carpip)
		$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
	$aliaslist = get_configured_ip_aliases_list();
	foreach ($aliaslist as $aliasip => $aliasif)
		$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$listenips[] = $tmp;
		if ($include_ipv6_link_local) {
			$llip = find_interface_ipv6_ll(get_real_interface($iface));
			if (!empty($llip)) {
				$tmp["name"]  = "{$ifacename} IPv6 Link-Local";
				$tmp["value"] = $llip;
				$listenips[] = $tmp;
			}
		}
	}
	$tmp["name"]  = "Localhost";
	$tmp["value"] = "lo0";
	$listenips[] = $tmp;
	return $listenips;
}

function get_possible_traffic_source_addresses($include_ipv6_link_local=false) {
	global $config;
	$sourceips = get_possible_listen_ips($include_ipv6_link_local);
	foreach (array('server', 'client') as $mode) {
		if (is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$vpn = array();
					$vpn['value'] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$vpn['name'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
					$sourceips[] = $vpn;
				}
			}
		}
	}
	return $sourceips;
}
?>
