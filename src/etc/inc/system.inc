<?php
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

function activate_powerd() {
	global $config, $g;

	if (is_process_running("powerd")) {
		exec("/usr/bin/killall powerd");
	}
	if (isset($config['system']['powerd_enable'])) {
		if ($g["platform"] == "nanobsd") {
			exec("/sbin/kldload cpufreq");
		}

		$ac_mode = "hadp";
		if (!empty($config['system']['powerd_ac_mode'])) {
			$ac_mode = $config['system']['powerd_ac_mode'];
		}

		$battery_mode = "hadp";
		if (!empty($config['system']['powerd_battery_mode'])) {
			$battery_mode = $config['system']['powerd_battery_mode'];
		}

		$normal_mode = "hadp";
		if (!empty($config['system']['powerd_normal_mode'])) {
			$normal_mode = $config['system']['powerd_normal_mode'];
		}

		mwexec("/usr/sbin/powerd -b $battery_mode -a $ac_mode -n $normal_mode");
	}
}

function get_default_sysctl_value($id) {
	global $sysctls;

	if (isset($sysctls[$id])) {
		return $sysctls[$id];
	}
}

function get_sysctl_descr($sysctl) {
	unset($output);
	$_gb = exec("/sbin/sysctl -nd {$sysctl}", $output);

	return $output[0];
}

function system_get_sysctls() {
	global $config, $sysctls;

	$disp_sysctl = array();
	$disp_cache = array();
	if (is_array($config['sysctl']) && is_array($config['sysctl']['item'])) {
		foreach ($config['sysctl']['item'] as $id => $tunable) {
			if ($tunable['value'] == "default") {
				$value = get_default_sysctl_value($tunable['tunable']);
			} else {
				$value = $tunable['value'];
			}

			$disp_sysctl[$id] = $tunable;
			$disp_sysctl[$id]['modified'] = true;
			$disp_cache[$tunable['tunable']] = 'set';
		}
	}

	foreach ($sysctls as $sysctl => $value) {
		if (isset($disp_cache[$sysctl])) {
			continue;
		}

		$disp_sysctl[$sysctl] = array('tunable' => $sysctl, 'value' => $value, 'descr' => get_sysctl_descr($sysctl));
	}
	unset($disp_cache);
	return $disp_sysctl;
}

function activate_sysctls() {
	global $config, $g, $sysctls;

	if (is_array($config['sysctl']) && is_array($config['sysctl']['item'])) {
		foreach ($config['sysctl']['item'] as $tunable) {
			if ($tunable['value'] == "default") {
				$value = get_default_sysctl_value($tunable['tunable']);
			} else {
				$value = $tunable['value'];
			}

			$sysctls[$tunable['tunable']] = $value;
		}
	}

	set_sysctl($sysctls);
}

function system_resolvconf_generate($dynupdate = false) {
	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_resolvconf_generate() being called $mt\n";
	}

	$syscfg = $config['system'];

	if ((((isset($config['dnsmasq']['enable'])) &&
	      (!isset($config['dnsmasq']['port']) || $config['dnsmasq']['port'] == "53") &&
	      (empty($config['dnsmasq']['interface']) ||
	       in_array("lo0", explode(",", $config['dnsmasq']['interface'])))) ||
	     ((isset($config['unbound']['enable'])) &&
	      (!isset($config['unbound']['port']) || $config['unbound']['port'] == "53") &&
	      (empty($config['unbound']['active_interface']) ||
	       in_array("lo0", explode(",", $config['unbound']['active_interface'])) ||
	       in_array("all", explode(",", $config['unbound']['active_interface']), true)))) &&
	     (!isset($config['system']['dnslocalhost']))) {
		$resolvconf .= "nameserver 127.0.0.1\n";
	}

	if (isset($syscfg['dnsallowoverride'])) {
		/* get dynamically assigned DNS servers (if any) */
		$ns = array_unique(get_searchdomains());
		foreach ($ns as $searchserver) {
			if ($searchserver) {
				$resolvconf .= "search {$searchserver}\n";
			}
		}
		$ns = array_unique(get_nameservers());
		foreach ($ns as $nameserver) {
			if ($nameserver) {
				$resolvconf .= "nameserver $nameserver\n";
			}
		}
	} else {
		$ns = array();
		// Do not create blank search/domain lines, it can break tools like dig.
		if ($syscfg['domain']) {
			$resolvconf .= "search {$syscfg['domain']}\n";
		}
	}
	if (is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $sys_dnsserver) {
			if ($sys_dnsserver && (!in_array($sys_dnsserver, $ns))) {
				$resolvconf .= "nameserver $sys_dnsserver\n";
			}
		}
	}

	// Add EDNS support
	if (isset($config['unbound']['enable']) && isset($config['unbound']['edns'])) {
		$resolvconf .= "options edns0\n";
	}

	$dnslock = lock('resolvconf', LOCK_EX);

	$fd = fopen("{$g['varetc_path']}/resolv.conf", "w");
	if (!$fd) {
		printf("Error: cannot open resolv.conf in system_resolvconf_generate().\n");
		unlock($dnslock);
		return 1;
	}

	fwrite($fd, $resolvconf);
	fclose($fd);

	// Prevent resolvconf(8) from rewriting our resolv.conf
	$fd = fopen("{$g['varetc_path']}/resolvconf.conf", "w");
	if (!$fd) {
		printf("Error: cannot open resolvconf.conf in system_resolvconf_generate().\n");
		return 1;
	}
	fwrite($fd, "resolv_conf=\"/dev/null\"\n");
	fclose($fd);

	if (!platform_booting()) {
		/* restart dhcpd (nameservers may have changed) */
		if (!$dynupdate) {
			services_dhcpd_configure();
		}
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
					if (isset($config['system']['route-debug'])) {
						$mt = microtime();
						log_error("ROUTING debug: $mt - route change -host {$syscfg['dnsserver'][$dnscountermo]} $gatewayip ");
					}
				}
				if (is_ipaddrv6($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("/sbin/route change -host -inet6 " . $syscfg['dnsserver'][$dnscountermo] . " {$gatewayip}");
					if (isset($config['system']['route-debug'])) {
						$mt = microtime();
						log_error("ROUTING debug: $mt - route change -host -inet6 {$syscfg['dnsserver'][$dnscountermo]} $gatewayip ");
					}
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
		foreach ($search_list as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents)) {
				continue;
			}
			foreach ($contents as $dns) {
				if (is_hostname($dns)) {
					$master_list[] = $dns;
				}
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
		foreach ($dns_lists as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents)) {
				continue;
			}
			foreach ($contents as $dns) {
				if (is_ipaddr($dns)) {
					$master_list[] = $dns;
				}
			}
		}
	}

	// Read in any extra nameservers
	if (file_exists("/var/etc/nameservers.conf")) {
		$dns_s = file("/var/etc/nameservers.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (is_array($dns_s)) {
			foreach ($dns_s as $dns) {
				if (is_ipaddr($dns)) {
					$master_list[] = $dns;
				}
			}
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
	if (isset($config['unbound']) && isset($config['unbound']['enable'])) {
		$dnsmasqcfg = $config['unbound'];
	} else {
		$dnsmasqcfg = $config['dnsmasq'];
	}

	$hosts = "127.0.0.1	localhost localhost.{$syscfg['domain']}\n";
	$hosts .= "::1		localhost localhost.{$syscfg['domain']}\n";
	$lhosts = "";
	$dhosts = "";

	if ($config['interfaces']['lan']) {
		$cfgip = get_interface_ip("lan");
		if (is_ipaddr($cfgip)) {
			$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
		}
		$cfgipv6 = get_interface_ipv6("lan");
		if (is_ipaddrv6($cfgipv6)) {
			$hosts .= "{$cfgipv6}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
		}
	} else {
		$sysiflist = get_configured_interface_list();
		$hosts_if_found = false;
		foreach ($sysiflist as $sysif) {
			if (!interface_has_gateway($sysif)) {
				$cfgip = get_interface_ip($sysif);
				if (is_ipaddr($cfgip)) {
					$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
					$hosts_if_found = true;
				}
				$cfgipv6 = get_interface_ipv6($sysif);
				if (is_ipaddrv6($cfgipv6)) {
					$hosts .= "{$cfgipv6}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
					$hosts_if_found = true;
				}
				if ($hosts_if_found == true) {
					break;
				}
			}
		}
	}

	if (isset($dnsmasqcfg['enable'])) {
		if (!is_array($dnsmasqcfg['hosts'])) {
			$dnsmasqcfg['hosts'] = array();
		}

		foreach ($dnsmasqcfg['hosts'] as $host) {
			if ($host['host'] || $host['host'] == "0") {
				$lhosts .= "{$host['ip']}	{$host['host']}.{$host['domain']} {$host['host']}\n";
			} else {
				$lhosts .= "{$host['ip']}	{$host['domain']}\n";
			}
			if (!is_array($host['aliases']) || !is_array($host['aliases']['item'])) {
				continue;
			}
			foreach ($host['aliases']['item'] as $alias) {
				if ($alias['host'] || $alias['host'] == "0") {
					$lhosts .= "{$host['ip']}	{$alias['host']}.{$alias['domain']} {$alias['host']}\n";
				} else {
					$lhosts .= "{$host['ip']}	{$alias['domain']}\n";
				}
			}
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpd'])) {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
				if (is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
					foreach ($dhcpifconf['staticmap'] as $host) {
						if ($host['ipaddr'] && $host['hostname'] && $host['domain']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
						} else if ($host['ipaddr'] && $host['hostname'] && $dhcpifconf['domain']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
						} else if ($host['ipaddr'] && $host['hostname']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
						}
					}
				}
			}
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpdv6'])) {
			foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
				if (is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
					foreach ($dhcpifconf['staticmap'] as $host) {
						if ($host['ipaddrv6'] && $host['hostname'] && $host['domain']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
						} else if ($host['ipaddrv6'] && $host['hostname'] && $dhcpifconf['domain']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
						} else if ($host['ipaddrv6'] && $host['hostname']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
						}
					}
				}
			}
		}

		if (isset($dnsmasqcfg['dhcpfirst'])) {
			$hosts .= $dhosts . $lhosts;
		} else {
			$hosts .= $lhosts . $dhosts;
		}
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

	return 0;
}

function system_dhcpleases_configure() {
	global $config, $g;

	/* Start the monitoring process for dynamic dhcpclients. */
	if ((isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcp'])) ||
	    (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcp']))) {
		/* Make sure we do not error out */
		mwexec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/db");
		if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases")) {
			@touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");
		}

		if (isset($config['unbound']['enable'])) {
			$dns_pid = "unbound.pid";
			$unbound_conf = "-u {$g['unbound_chroot_path']}/dhcpleases_entries.conf";
		} else {
			$dns_pid = "dnsmasq.pid";
			$unbound_conf = "";
		}

		$pidfile = "{$g['varrun_path']}/dhcpleases.pid";
		if (isvalidpid($pidfile)) {
			/* Make sure dhcpleases is using correct unbound or dnsmasq */
			$_gb = exec("/bin/pgrep -F {$pidfile} -f {$dns_pid}", $output, $retval);
			if (intval($retval) == 0) {
				sigkillbypid($pidfile, "HUP");
				return;
			} else {
				sigkillbypid($pidfile, "TERM");
			}
		}

		/* To ensure we do not start multiple instances of dhcpleases, perform some clean-up first. */
		if (is_process_running("dhcpleases")) {
			sigkillbyname('dhcpleases', "TERM");
		}
		@unlink($pidfile);
		mwexec("/usr/local/sbin/dhcpleases -l {$g['dhcpd_chroot_path']}/var/db/dhcpd.leases -d {$config['system']['domain']} -p {$g['varrun_path']}/{$dns_pid} {$unbound_conf} -h {$g['varetc_path']}/hosts");
	} else {
		sigkillbypid($pidfile, "TERM");
		@unlink($pidfile);
	}
}

function system_hostname_configure() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
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

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_routing_configure() being called $mt\n";
	}

	$gatewayip = "";
	$interfacegw = "";
	$gatewayipv6 = "";
	$interfacegwv6 = "";
	$foundgw = false;
	$foundgwv6 = false;
	/* tack on all the hard defined gateways as well */
	if (is_array($config['gateways']['gateway_item'])) {
		array_map('unlink', glob("{$g['tmp_path']}/*_defaultgw{,v6}", GLOB_BRACE));
		foreach	($config['gateways']['gateway_item'] as $gateway) {
			if (isset($gateway['defaultgw'])) {
				if ($foundgw == false && ($gateway['ipprotocol'] != "inet6" && (is_ipaddrv4($gateway['gateway']) || $gateway['gateway'] == "dynamic"))) {
					if (strpos($gateway['gateway'], ":")) {
						continue;
					}
					if ($gateway['gateway'] == "dynamic") {
						$gateway['gateway'] = get_interface_gateway($gateway['interface']);
					}
					$gatewayip = $gateway['gateway'];
					$interfacegw = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultif = get_real_interface($gateway['interface']);
						if ($defaultif) {
							@file_put_contents("{$g['tmp_path']}/{$defaultif}_defaultgw", $gateway['gateway']);
						}
					}
					$foundgw = true;
				} else if ($foundgwv6 == false && ($gateway['ipprotocol'] == "inet6" && (is_ipaddrv6($gateway['gateway']) || $gateway['gateway'] == "dynamic"))) {
					if ($gateway['gateway'] == "dynamic") {
						$gateway['gateway'] = get_interface_gateway_v6($gateway['interface']);
					}
					$gatewayipv6 = $gateway['gateway'];
					$interfacegwv6 = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultifv6 = get_real_interface($gateway['interface']);
						if ($defaultifv6) {
							@file_put_contents("{$g['tmp_path']}/{$defaultifv6}_defaultgwv6", $gateway['gateway']);
						}
					}
					$foundgwv6 = true;
				}
			}
			if ($foundgw === true && $foundgwv6 === true) {
				break;
			}
		}
	}
	if ($foundgw == false) {
		$defaultif = get_real_interface("wan");
		$interfacegw = "wan";
		$gatewayip = get_interface_gateway("wan");
		@file_put_contents("{$g['tmp_path']}/{$defaultif}_defaultgw", $gatewayip);
	}
	if ($foundgwv6 == false) {
		$defaultifv6 = get_real_interface("wan");
		$interfacegwv6 = "wan";
		$gatewayipv6 = get_interface_gateway_v6("wan");
		@file_put_contents("{$g['tmp_path']}/{$defaultifv6}_defaultgwv6", $gatewayipv6);
	}
	$dont_add_route = false;
	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if (is_array($config['installedpackages']['olsrd'])) {
		foreach ($config['installedpackages']['olsrd']['config'] as $olsrd) {
			if (($olsrd['enabledyngw'] == "on") && ($olsrd['enable'] == "on")) {
				$dont_add_route = true;
				log_error(sprintf(gettext("Not adding default route because OLSR dynamic gateway is enabled.")));
				break;
			}
		}
	}

	$gateways_arr = return_gateways_array(false, true);
	foreach ($gateways_arr as $gateway) {
		// setup static interface routes for nonlocal gateways
		if (isset($gateway["nonlocalgateway"])) {
			$srgatewayip = $gateway['gateway'];
			$srinterfacegw = $gateway['interface'];
			if (is_ipaddr($srgatewayip) && !empty($srinterfacegw)) {
				$inet = (!is_ipaddrv4($srgatewayip) ? "-inet6" : "-inet");
				$cmd = "/sbin/route change {$inet} " . escapeshellarg($srgatewayip) . " ";
				mwexec($cmd . "-iface " . escapeshellarg($srinterfacegw));
				if (isset($config['system']['route-debug'])) {
					$mt = microtime();
					log_error("ROUTING debug: $mt - $cmd -iface $srinterfacegw ");
				}
			}
		}
	}

	if ($dont_add_route == false) {
		if (!empty($interface) && $interface != $interfacegw) {
			;
		} else if (is_ipaddrv4($gatewayip)) {
			log_error("ROUTING: setting default route to $gatewayip");
			mwexec("/sbin/route change -inet default " . escapeshellarg($gatewayip));
		}

		if (!empty($interface) && $interface != $interfacegwv6) {
			;
		} else if (is_ipaddrv6($gatewayipv6)) {
			$ifscope = "";
			if (is_linklocal($gatewayipv6) && !strpos($gatewayipv6, '%')) {
				$ifscope = "%{$defaultifv6}";
			}
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
			if (!empty($interface) && $interface != $gateway['friendlyiface']) {
				continue;
			}

			$gatewayip = $gateway['gateway'];
			$interfacegw = $gateway['interface'];

			$blackhole = "";
			if (!strcasecmp("Null", substr($rtent['gateway'], 0, 3))) {
				$blackhole = "-blackhole";
			}

			if (!is_fqdn($rtent['network']) && !is_subnet($rtent['network'])) {
				continue;
			}

			$dnscache = array();
			if ($update_dns === true) {
				if (is_subnet($rtent['network'])) {
					continue;
				}
				$dnscache = explode("\n", trim(compare_hostname_to_dnscache($rtent['network'])));
				if (empty($dnscache)) {
					continue;
				}
			}

			if (is_subnet($rtent['network'])) {
				$ips = array($rtent['network']);
			} else {
				if (!isset($rtent['disabled'])) {
					$filterdns_list[] = $rtent['network'];
				}
				$ips = add_hostname_to_watch($rtent['network']);
			}

			foreach ($dnscache as $ip) {
				if (in_array($ip, $ips)) {
					continue;
				}
				mwexec("/sbin/route delete " . escapeshellarg($ip), true);
				if (isset($config['system']['route-debug'])) {
					$mt = microtime();
					log_error("ROUTING debug: $mt - route delete $ip ");
				}
			}

			if (isset($rtent['disabled'])) {
				/* XXX: This can break things by deleting routes that shouldn't be deleted - OpenVPN, dynamic routing scenarios, etc. redmine #3709 */
				foreach ($ips as $ip) {
					mwexec("/sbin/route delete " . escapeshellarg($ip), true);
					if (isset($config['system']['route-debug'])) {
						$mt = microtime();
						log_error("ROUTING debug: $mt - route delete $ip ");
					}
				}
				continue;
			}

			foreach ($ips as $ip) {
				if (is_ipaddrv4($ip)) {
					$ip .= "/32";
				}
				// do NOT do the same check here on v6, is_ipaddrv6 returns true when including the CIDR mask. doing so breaks v6 routes

				$inet = (is_subnetv6($ip) ? "-inet6" : "-inet");

				$cmd = "/sbin/route change {$inet} {$blackhole} " . escapeshellarg($ip) . " ";

				if (is_subnet($ip)) {
					if (is_ipaddr($gatewayip)) {
						mwexec($cmd . escapeshellarg($gatewayip));
						if (isset($config['system']['route-debug'])) {
							$mt = microtime();
							log_error("ROUTING debug: $mt - $cmd $gatewayip");
						}
					} else if (!empty($interfacegw)) {
						mwexec($cmd . "-iface " . escapeshellarg($interfacegw));
						if (isset($config['system']['route-debug'])) {
							$mt = microtime();
							log_error("ROUTING debug: $mt - $cmd -iface $interfacegw ");
						}
					}
				}
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
			foreach ($filterdns_list as $hostname) {
				$hostnames .= "cmd {$hostname} '/usr/local/sbin/pfSctl -c \"service reload routedns\"'\n";
			}
			file_put_contents("{$g['varetc_path']}/filterdns-route.hosts", $hostnames);
			unset($hostnames);

			if (isvalidpid("{$g['varrun_path']}/filterdns-route.pid")) {
				sigkillbypid("{$g['varrun_path']}/filterdns-route.pid", "HUP");
			} else {
				mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-route.pid -i {$interval} -c {$g['varetc_path']}/filterdns-route.hosts -d 1");
			}
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
	if (isset($config['system']['developerspew'])) {
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
	if (is_ipaddrv6($server)) {
		return "[$server]";
	} else {
		return $server;
	}
}

function system_syslogd_get_remote_servers($syslogcfg, $facility = "*.*") {
	// Rather than repeatedly use the same code, use this function to build a list of remote servers.
	$facility .= " ".
	$remote_servers = "";
	$pad_to  = max(strlen($facility), 56);
	$padding = ceil(($pad_to - strlen($facility))/8)+1;
	if (isset($syslogcfg['enable'])) {
		if ($syslogcfg['remoteserver']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver']) . "\n";
		}
		if ($syslogcfg['remoteserver2']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver2']) . "\n";
		}
		if ($syslogcfg['remoteserver3']) {
			$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver3']) . "\n";
		}
	}
	return $remote_servers;
}

function clear_log_file($logfile = "/var/log/system.log", $restart_syslogd = true) {
	global $config, $g;
	if ($restart_syslogd) {
		exec("/usr/bin/killall syslogd");
	}
	if (isset($config['system']['disablesyslogclog'])) {
		unlink($logfile);
		touch($logfile);
	} else {
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "511488";
		$log_size = isset($config['syslog'][basename($logfile, '.log') . '_settings']['logfilesize']) ? $config['syslog'][basename($logfile, '.log') . '_settings']['logfilesize'] : $log_size;
		exec("/usr/local/sbin/clog -i -s {$log_size} " . escapeshellarg($logfile));
	}
	if ($restart_syslogd) {
		system_syslogd_start();
	}
}

function clear_all_log_files($restart = false) {
	global $g;
	exec("/usr/bin/killall syslogd");

	$log_files = array("system", "filter", "dhcpd", "vpn", "pptps", "poes", "l2tps", "openvpn", "portalauth", "ipsec", "ppp", "relayd", "wireless", "nginx", "ntpd", "gateways", "resolver", "routing");
	foreach ($log_files as $lfile) {
		clear_log_file("{$g['varlog_path']}/{$lfile}.log", false);
	}

	if ($restart) {
		system_syslogd_start();
		killbyname("dhcpd");
		services_dhcpd_configure();
	}
	return;
}

function system_syslogd_start() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_syslogd_start() being called $mt\n";
	}

	mwexec("/etc/rc.d/hostid start");

	$syslogcfg = $config['syslog'];

	if (platform_booting()) {
		echo gettext("Starting syslog...");
	}

	// Which logging type are we using this week??
	if (isset($config['system']['disablesyslogclog'])) {
		$log_directive = "";
		$log_create_directive = "/usr/bin/touch ";
		$log_size = "";
	} else { // Defaults to CLOG
		$log_directive = "%";
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "10240";
		$log_create_directive = "/usr/local/sbin/clog -i -s ";
	}

	$syslogd_extra = "";
	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp', 'ntpd', 'ntpdate', 'charon', 'ipsec_starter', 'openvpn', 'pptps', 'poes', 'l2tps', 'relayd', 'hostapd', 'dnsmasq', 'filterdns', 'unbound', 'dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c', 'dpinger', 'radvd', 'routed', 'olsrd', 'zebra', 'ospfd', 'bgpd', 'miniupnpd', 'filterlog');
		$syslogconf = "";
		if ($config['installedpackages']['package']) {
			foreach ($config['installedpackages']['package'] as $package) {
				if ($package['logging']) {
					array_push($separatelogfacilities, $package['logging']['facilityname']);
					if (!is_file($g['varlog_path'].'/'.$package['logging']['logfilename'])) {
						mwexec("{$log_create_directive} {$log_size} {$g['varlog_path']}/{$package['logging']['logfilename']}");
					}
					$syslogconf .= "!{$package['logging']['facilityname']}\n*.*\t\t\t\t\t\t {$log_directive}{$g['varlog_path']}/{$package['logging']['logfilename']}\n";
				}
			}
		}
		$facilitylist = implode(',', array_unique($separatelogfacilities));
		$syslogconf .= "!radvd,routed,olsrd,zebra,ospfd,bgpd,miniupnpd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/routing.log\n";
		}

		$syslogconf .= "!ntp,ntpd,ntpdate\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ntpd.log\n";
		}

		$syslogconf .= "!ppp\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ppp.log\n";
		}

		$syslogconf .= "!pptps\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/pptps.log\n";
		}

		$syslogconf .= "!poes\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/poes.log\n";
		}

		$syslogconf .= "!l2tps\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/l2tps.log\n";
		}

		$syslogconf .= "!charon,ipsec_starter\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/ipsec.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!openvpn\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/openvpn.log\n";
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!dpinger\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/gateways.log\n";
		}
		if (isset($syslogcfg['dpinger'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!dnsmasq,filterdns,unbound\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/resolver.log\n";
		}

		$syslogconf .= "!dhcpd,dhcrelay,dhclient,dhcp6c\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.*								{$log_directive}{$g['varlog_path']}/dhcpd.log\n";
		}
		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!relayd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/relayd.log\n";
		}
		if (isset($syslogcfg['relayd'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!hostapd\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/wireless.log\n";
		}
		if (isset($syslogcfg['hostapd'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!filterlog\n";
		$syslogconf .= "*.* 								{$log_directive}{$g['varlog_path']}/filter.log\n";
		if (isset($syslogcfg['filter'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging'])) {
			$syslogconf .= <<<EOD
local3.*							{$log_directive}{$g['varlog_path']}/vpn.log
local4.*							{$log_directive}{$g['varlog_path']}/portalauth.log
local5.*							{$log_directive}{$g['varlog_path']}/nginx.log
local7.*							{$log_directive}{$g['varlog_path']}/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;news.err;local0.none;local3.none;local4.none;local7.none;security.*;auth.info;authpriv.info;daemon.info	{$log_directive}{$g['varlog_path']}/system.log
auth.info;authpriv.info 					|exec /usr/local/sbin/sshlockout_pf 15
*.emerg								*

EOD;
		}
		if (isset($syslogcfg['vpn'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
		}
		if (isset($syslogcfg['portalauth'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local4.*");
		}
		if (isset($syslogcfg['dhcp'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local7.*");
		}
		if (isset($syslogcfg['system'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.emerg;*.notice;kern.debug;lpr.info;mail.crit;news.err;local0.none;local3.none;local7.none;security.*;auth.info;authpriv.info;daemon.info");
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
		if (!is_dir("{$g['dhcpd_chroot_path']}/var/run")) {
			exec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/run");
		}

		$sourceip = "";
		if (!empty($syslogcfg['sourceip'])) {
			if ($syslogcfg['ipproto'] == "ipv6") {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ipv6($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr)) {
					$ifaddr = get_interface_ip($syslogcfg['sourceip']);
				}
			} else {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ip($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr)) {
					$ifaddr = get_interface_ipv6($syslogcfg['sourceip']);
				}
			}
			if (is_ipaddr($ifaddr)) {
				$sourceip = "-b {$ifaddr}";
			}
		}

		$syslogd_extra = "-f {$g['varetc_path']}/syslog.conf {$sourceip}";
	}

	if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
		sigkillbypid("{$g['varrun_path']}/syslog.pid", "TERM");
		usleep(100000); // syslogd often doesn't respond to a TERM quickly enough for the starting of syslogd below to be successful
	}

	if (isvalidpid("{$g['varrun_path']}/syslog.pid")) {
		// if it still hasn't responded to the TERM, KILL it.
		sigkillbypid("{$g['varrun_path']}/syslog.pid", "KILL");
		usleep(100000);
	}


	$retval = mwexec_bg("/usr/sbin/syslogd -s -c -c -l {$g['dhcpd_chroot_path']}/var/run/log -P {$g['varrun_path']}/syslog.pid {$syslogd_extra}");

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}

	return $retval;
}

function system_webgui_create_certificate() {
	global $config, $g;

	if (!is_array($config['ca'])) {
		$config['ca'] = array();
	}
	$a_ca =& $config['ca'];
	if (!is_array($config['cert'])) {
		$config['cert'] = array();
	}
	$a_cert =& $config['cert'];
	log_error("Creating SSL Certificate for this host");

	$cert = array();
	$cert['refid'] = uniqid();
	$cert['descr'] = gettext("webConfigurator default ({$cert['refid']})");

	$dn = array(
		'countryName' => "US",
		'stateOrProvinceName' => "State",
		'localityName' => "Locality",
		'organizationName' => "{$g['product_name']} webConfigurator Self-Signed Certificate",
		'emailAddress' => "admin@{$config['system']['hostname']}.{$config['system']['domain']}",
		'commonName' => "{$config['system']['hostname']}-{$cert['refid']}");
	$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
	if (!cert_create($cert, null, 2048, 2000, $dn, "self-signed", "sha256")) {
		while ($ssl_err = openssl_error_string()) {
			log_error("Error creating WebGUI Certificate: openssl library returns: " . $ssl_err);
		}
		error_reporting($old_err_level);
		return null;
	}
	error_reporting($old_err_level);

	$a_cert[] = $cert;
	$config['system']['webgui']['ssl-certref'] = $cert['refid'];
	write_config(gettext("Generated new self-signed HTTPS certificate ({$cert['refid']})"));
	return $cert;
}

function system_webgui_start() {
	global $config, $g;

	if (platform_booting()) {
		echo gettext("Starting webConfigurator...");
	}

	chdir($g['www_path']);

	/* defaults */
	$portarg = "80";
	$crt = "";
	$key = "";
	$ca = "";

	/* non-standard port? */
	if (isset($config['system']['webgui']['port']) && $config['system']['webgui']['port'] <> "") {
		$portarg = "{$config['system']['webgui']['port']}";
	}

	if ($config['system']['webgui']['protocol'] == "https") {
		// Ensure that we have a webConfigurator CERT
		$cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
		if (!is_array($cert) || !$cert['crt'] || !$cert['prv']) {
			$cert = system_webgui_create_certificate();
		}
		$crt = base64_decode($cert['crt']);
		$key = base64_decode($cert['prv']);

		if (!$config['system']['webgui']['port']) {
			$portarg = "443";
		}
		$ca = ca_chain($cert);
	}

	/* generate nginx configuration */
	system_generate_nginx_config("{$g['varetc_path']}/nginx-webConfigurator.conf",
		$crt, $key, $ca, "nginx-webConfigurator.pid", $portarg, "/usr/local/www/",
		"cert.crt", "cert.key");

	/* kill any running nginx */
	killbypid("{$g['varrun_path']}/nginx-webConfigurator.pid");

	sleep(1);

	@unlink("{$g['varrun_path']}/nginx-webConfigurator.pid");

	/* start nginx */
	$res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-webConfigurator.conf");

	if (platform_booting()) {
		if ($res == 0) {
			echo gettext("done.") . "\n";
		} else {
			echo gettext("failed!") . "\n";
		}
	}

	return $res;
}

function system_generate_nginx_config($filename,
	$cert,
	$key,
	$ca,
	$pid_file,
	$port = 80,
	$document_root = "/usr/local/www/",
	$cert_location = "cert.crt",
	$key_location = "cert.key",
	$captive_portal = false) {

	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_generate_nginx_config() being called $mt\n";
	}

	if ($captive_portal !== false) {
		$cp_interfaces = explode(",", $config['captiveportal'][$captive_portal]['interface']);
		$cp_hostcheck = "";
		foreach ($cp_interfaces as $cpint) {
			$cpint_ip = get_interface_ip($cpint);
			if (is_ipaddr($cpint_ip)) {
				$cp_hostcheck .= "\t\tif (\$http_host = $cpint_ip) {\n";
				$cp_hostcheck .= "\t\t\tset \$cp_redirect no;\n";
				$cp_hostcheck .= "\t\t}\n";
			}
		}
		if (isset($config['captiveportal'][$captive_portal]['httpsname']) &&
		    is_domain($config['captiveportal'][$captive_portal]['httpsname'])) {
			$cp_hostcheck .= "\t\tif (\$http_host = {$config['captiveportal'][$captive_portal]['httpsname']}) {\n";
			$cp_hostcheck .= "\t\t\tset \$cp_redirect no;\n";
			$cp_hostcheck .= "\t\t}\n";
		}
		$cp_rewrite = "\t\tif (\$cp_redirect = '') {\n";
		$cp_rewrite .= "\t\t\trewrite	^ /index.php?zone=$captive_portal&redirurl=\$request_uri break;\n";
		$cp_rewrite .= "\t\t}\n";

		$maxprocperip = $config['captiveportal'][$captive_portal]['maxprocperip'];
		if (empty($maxprocperip)) {
			$maxprocperip = 10;
		}
		$captive_portal_maxprocperip = "\t\tlimit_conn addr $maxprocperip;\n";

	}

	if (empty($port)) {
		$nginx_port = "80";
	} else {
		$nginx_port = $port;
	}

	$memory = get_memory();
	$realmem = $memory[1];

	// Determine web GUI process settings and take into account low memory systems
	if ($realmem < 255) {
		$max_procs = 1;
	} else {
		$max_procs = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;
	}

	// Ramp up captive portal max procs, assuming each PHP process can consume up to 64MB RAM
	if ($captive_portal !== false) {
		if ($realmem > 135 and $realmem < 256) {
			$max_procs += 1; // 2 worker processes
		} else if ($realmem > 255 and $realmem < 513) {
			$max_procs += 2; // 3 worker processes
		} else if ($realmem > 512) {
			$max_procs += 4; // 6 worker processes
		}
	}

	$nginx_config = <<<EOD
#
# nginx configuration file

pid {$g['varrun_path']}/{$pid_file};

user  root wheel;
worker_processes  {$max_procs};

EOD;

if (!isset($config['syslog']['nolognginx'])) {
	$nginx_config .= "error_log  syslog:server=unix:/var/run/log,facility=local5;\n";
}

$nginx_config .= <<<EOD

events {
    worker_connections  1024;
}

http {
	include       /usr/local/etc/nginx/mime.types;
	default_type  application/octet-stream;
	add_header X-Frame-Options SAMEORIGIN;
	server_tokens off;

	sendfile        on;
	keepalive_timeout  65;

	access_log      syslog:server=unix:/var/run/log,facility=local5 combined;

EOD;

if ($captive_portal !== false) {
	$nginx_config .= "\tlimit_conn_zone \$binary_remote_addr zone=addr:10m;\n";
}

$nginx_config .= <<<EOD

	server {
		listen {$nginx_port};
		listen [::]:{$nginx_port};

EOD;

	if ($cert <> "" and $key <> "") {
		$nginx_config .= "\t\tssl             on;\n";
		$nginx_config .= "\t\tssl_certificate         {$g['varetc_path']}/{$cert_location};\n";
		$nginx_config .= "\t\tssl_certificate_key     {$g['varetc_path']}/{$key_location};\n";
		$nginx_config .= "\t\tssl_session_timeout     10m;\n";
		$nginx_config .= "\t\tkeepalive_timeout       70;\n";
		$nginx_config .= "\t\tssl_session_cache       shared:SSL:10m;\n";
		$nginx_config .= "\t\tssl_protocols   TLSv1 TLSv1.1 TLSv1.2;\n";
		$nginx_config .= "\t\tssl_ciphers \"EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH\";\n";
		$nginx_config .= "\t\tssl_prefer_server_ciphers       on;\n";
		$nginx_config .= "\t\tadd_header Strict-Transport-Security \"max-age=31536000\";\n";
		$nginx_config .= "\t\tadd_header X-Content-Type-Options nosniff;\n";
		$nginx_config .= "\t\tssl_session_tickets off;\n";
		$nginx_config .= "\t\tssl_stapling on;\n";
		$nginx_config .= "\t\tssl_stapling_verify on;\n";
		$nginx_config .= "\t\tssl_dhparam /etc/dh-parameters.4096;\n";
		$nginx_config .= "\n";
	}

	if ($captive_portal !== false) {
		$nginx_config .= <<<EOD
$captive_portal_maxprocperip
$cp_hostcheck
$cp_rewrite

EOD;

	}

	$nginx_config .= <<<EOD
		root "{$document_root}";
		location / {
			index  index.html index.htm index.php;
		}

		location ~ \.php$ {
			try_files \$uri =404; #  This line closes a potential security hole
			# ensuring users can't execute uploaded files
			# see: http://forum.nginx.org/read.php?2,88845,page=3
			fastcgi_pass   unix:{$g['varrun_path']}/php-fpm.socket;
			fastcgi_index  index.php;
			fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
			include        /usr/local/etc/nginx/fastcgi_params;
		}
	}

EOD;

	$cert = str_replace("\r", "", $cert);
	$key = str_replace("\r", "", $key);

	$cert = str_replace("\n\n", "\n", $cert);
	$key = str_replace("\n\n", "\n", $key);

	if ($cert <> "" and $key <> "") {
		$fd = fopen("{$g['varetc_path']}/{$cert_location}", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open certificate file in system_webgui_start().%s"), "\n");
			return 1;
		}
		chmod("{$g['varetc_path']}/{$cert_location}", 0600);
		if ($ca <> "") {
			$cert_chain = $cert . "\n" . $ca;
		} else {
			$cert_chain = $cert;
		}
		fwrite($fd, $cert_chain);
		fclose($fd);
		$fd = fopen("{$g['varetc_path']}/{$key_location}", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open certificate key file in system_webgui_start().%s"), "\n");
			return 1;
		}
		chmod("{$g['varetc_path']}/{$key_location}", 0600);
		fwrite($fd, $key);
		fclose($fd);
	}

	// Add HTTP to HTTPS redirect
	if ($captive_portal === false && $config['system']['webgui']['protocol'] == "https" && !isset($config['system']['webgui']['disablehttpredirect'])) {
		if ($nginx_port != "443") {
			$redirectport = ":{$nginx_port}";
		}
		$nginx_config .= <<<EOD
	server {
		listen 80;
		listen [::]:80;
		rewrite         ^ https://\$http_host$redirectport\$request_uri? permanent;
	}

EOD;
	}

	$nginx_config .= "}\n";

	$fd = fopen("{$filename}", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open %s in system_generate_nginx_config().%s"), $filename, "\n");
		return 1;
	}
	fwrite($fd, $nginx_config);
	fclose($fd);

	/* nginx will fail to start if this directory does not exist. */
	safe_mkdir("/var/tmp/nginx/");

	return 0;

}

function system_get_timezone_list() {
	global $g;

	$file_list = array_merge(
		glob("/usr/share/zoneinfo/[A-Z]*"),
		glob("/usr/share/zoneinfo/*/*"),
		glob("/usr/share/zoneinfo/*/*/*")
	);

	if (empty($file_list)) {
		$file_list[] = $g['default_timezone'];
	} else {
		/* Remove directories from list */
		$file_list = array_filter($file_list, function($v) {
			return !is_dir($v);
		});
	}

	/* Remove directory prefix */
	$file_list = str_replace('/usr/share/zoneinfo/', '', $file_list);

	sort($file_list);

	return $file_list;
}

function system_timezone_configure() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_timezone_configure() being called $mt\n";
	}

	$syscfg = $config['system'];

	if (platform_booting()) {
		echo gettext("Setting timezone...");
	}

	/* extract appropriate timezone file */
	$timezone = (isset($syscfg['timezone']) ? $syscfg['timezone'] : $g['default_timezone']);
	conf_mount_rw();
	/* DO NOT remove \n otherwise tzsetup will fail */
	@file_put_contents("/var/db/zoneinfo", $timezone . "\n");
	mwexec("/usr/sbin/tzsetup -r");
	conf_mount_ro();

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function system_ntp_setup_gps($serialport) {
	global $config, $g;
	$gps_device = '/dev/gps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport)) {
		return false;
	}

	conf_mount_rw();
	// Create symlink that ntpd requires
	unlink_if_exists($gps_device);
	@symlink($serialport, $gps_device);

	$gpsbaud = '4800';
	if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['speed'])) {
		switch ($config['ntpd']['gps']['speed']) {
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

	/* Configure the serial port for raw IO and set the speed */
	mwexec("stty -f {$serialport}.init raw speed {$gpsbaud}");

	/* Send the following to the GPS port to initialize the GPS */
	if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['type'])) {
		$gps_init = base64_decode($config['ntpd']['gps']['initcmd']);
	} else {
		$gps_init = base64_decode('JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ==');
	}

	/* XXX: Why not file_put_contents to the device */
	@file_put_contents('/tmp/gps.init', $gps_init);
	mwexec("cat /tmp/gps.init > {$serialport}");

	/* Add /etc/remote entry in case we need to read from the GPS with tip */
	if (intval(`grep -c '^gps0' /etc/remote`) == 0) {
		@file_put_contents("/etc/remote", "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:", FILE_APPEND);
	}

	conf_mount_ro();

	return true;
}

function system_ntp_setup_pps($serialport) {
	global $config, $g;

	$pps_device = '/dev/pps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport)) {
		return false;
	}

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

	safe_mkdir($statsdir);

	if (!is_array($config['ntpd'])) {
		$config['ntpd'] = array();
	}

	$ntpcfg = "# \n";
	$ntpcfg .= "# pfSense ntp configuration file \n";
	$ntpcfg .= "# \n\n";
	$ntpcfg .= "tinker panic 0 \n";

	/* Add Orphan mode */
	$ntpcfg .= "# Orphan mode stratum\n";
	$ntpcfg .= 'tos orphan ';
	if (!empty($config['ntpd']['orphan'])) {
		$ntpcfg .= $config['ntpd']['orphan'];
	} else {
		$ntpcfg .= '12';
	}
	$ntpcfg .= "\n";

	/* Add PPS configuration */
	if (is_array($config['ntpd']['pps']) && !empty($config['ntpd']['pps']['port']) &&
	    file_exists('/dev/'.$config['ntpd']['pps']['port']) &&
	    system_ntp_setup_pps($config['ntpd']['pps']['port'])) {
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
		} else {
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
	if (is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['port']) &&
	    file_exists('/dev/'.$config['ntpd']['gps']['port']) &&
	    system_ntp_setup_gps($config['ntpd']['gps']['port'])) {
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
		} else {
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
		} else {
			$ntpcfg .= ' flag1 0';
		}
		if (!empty($config['ntpd']['gps']['flag2'])) {
			$ntpcfg .= ' flag2 1';
		}
		if (!empty($config['ntpd']['gps']['flag3'])) {
			$ntpcfg .= ' flag3 1';
		} else {
			$ntpcfg .= ' flag3 0';
		}
		if (!empty($config['ntpd']['gps']['flag4'])) {
			$ntpcfg .= ' flag4 1';
		}
		if (!empty($config['ntpd']['gps']['refid'])) {
			$ntpcfg .= ' refid ';
			$ntpcfg .= $config['ntpd']['gps']['refid'];
		}
		if (!empty($config['ntpd']['gps']['stratum'])) {
			$ntpcfg .= ' stratum ';
			$ntpcfg .= $config['ntpd']['gps']['stratum'];
		}
		$ntpcfg .= "\n";
	} elseif (is_array($config['ntpd']) && !empty($config['ntpd']['gpsport']) &&
	    file_exists('/dev/'.$config['ntpd']['gpsport']) &&
	    system_ntp_setup_gps($config['ntpd']['gpsport'])) {
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
		if (substr_count($config['ntpd']['prefer'], $ts)) {
			$ntpcfg .= ' prefer';
		}
		if (substr_count($config['ntpd']['noselect'], $ts)) {
			$ntpcfg .= ' noselect';
		}
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


	if (empty($config['ntpd']['interface'])) {
		if (is_array($config['installedpackages']['openntpd']) && !empty($config['installedpackages']['openntpd']['config'][0]['interface'])) {
			$interfaces = explode(",", $config['installedpackages']['openntpd']['config'][0]['interface']);
		} else {
			$interfaces = array();
		}
	} else {
		$interfaces = explode(",", $config['ntpd']['interface']);
	}

	if (is_array($interfaces) && count($interfaces)) {
		$ntpcfg .= "interface ignore all\n";
		foreach ($interfaces as $interface) {
			if (strstr($interface, "_vip")) {
				$interface = get_configured_carp_interface_list($interface);
			}
			if (!is_ipaddr($interface)) {
				$interface = get_real_interface($interface);
			}
			if (!empty($interface)) {
				$ntpcfg .= "interface listen {$interface}\n";
			}
		}
	}

	/* open configuration for writing or bail */
	if (!@file_put_contents("{$g['varetc_path']}/ntpd.conf", $ntpcfg)) {
		log_error("Could not open {$g['varetc_path']}/ntpd.conf for writing");
		return;
	}

	/* At bootup we just want to write out the config. */
	if (!$start_ntpd) {
		return;
	}

	/* if ntpd is running, kill it */
	while (isvalidpid("{$g['varrun_path']}/ntpd.pid")) {
		killbypid("{$g['varrun_path']}/ntpd.pid");
	}
	@unlink("{$g['varrun_path']}/ntpd.pid");

	/* if /var/empty does not exist, create it */
	if (!is_dir("/var/empty")) {
		mkdir("/var/empty", 0775, true);
	}

	/* start opentpd, set time now and use /var/etc/ntpd.conf */
	mwexec("/usr/local/sbin/ntpd -g -c {$g['varetc_path']}/ntpd.conf -p {$g['varrun_path']}/ntpd.pid", false, true);

	// Note that we are starting up
	log_error("NTPD is starting up.");
	return;
}

function sync_system_time() {
	global $config, $g;

	if (platform_booting()) {
		echo gettext("Syncing system time before startup...");
	}

	/* foreach through servers and write out to ntpd.conf */
	foreach (explode(' ', $config['system']['timeservers']) as $ts) {
		mwexec("/usr/local/sbin/ntpdate -s $ts");
	}

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}

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
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_do_shell_commands() being called $mt\n";
	}

	if ($early) {
		$cmdn = "earlyshellcmd";
	} else {
		$cmdn = "shellcmd";
	}

	if (is_array($config['system'][$cmdn])) {

		/* *cmd is an array, loop through */
		foreach ($config['system'][$cmdn] as $cmd) {
			exec($cmd);
		}

	} elseif ($config['system'][$cmdn] <> "") {

		/* execute single item */
		exec($config['system'][$cmdn]);

	}
}

function system_console_configure() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
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
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_dmesg_save() being called $mt\n";
	}

	$dmesg = "";
	$_gb = exec("/sbin/dmesg", $dmesg);

	/* find last copyright line (output from previous boots may be present) */
	$lastcpline = 0;

	for ($i = 0; $i < count($dmesg); $i++) {
		if (strstr($dmesg[$i], "Copyright (c) 1992-")) {
			$lastcpline = $i;
		}
	}

	$fd = fopen("{$g['varlog_path']}/dmesg.boot", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open dmesg.boot in system_dmesg_save().%s"), "\n");
		return 1;
	}

	for ($i = $lastcpline; $i < count($dmesg); $i++) {
		fwrite($fd, $dmesg[$i] . "\n");
	}

	fclose($fd);
	unset($dmesg);

	return 0;
}

function system_set_harddisk_standby() {
	global $g, $config;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_set_harddisk_standby() being called $mt\n";
	}

	if (isset($config['system']['harddiskstandby'])) {
		if (platform_booting()) {
			echo gettext('Setting hard disk standby... ');
		}

		$standby = $config['system']['harddiskstandby'];
		// Check for a numeric value
		if (is_numeric($standby)) {
			// Get only suitable candidates for standby; using get_smart_drive_list()
			// from utils.inc to get the list of drives.
			$harddisks = get_smart_drive_list();

			// Since get_smart_drive_list() only matches ad|da|ada; lets put the check below
			// just in case of some weird pfSense platform installs.
			if (count($harddisks) > 0) {
				// Iterate disks and run the camcontrol command for each
				foreach ($harddisks as $harddisk) {
					mwexec("/sbin/camcontrol standby {$harddisk} -t {$standby}");
				}
				if (platform_booting()) {
					echo gettext("done.") . "\n";
				}
			} else if (platform_booting()) {
				echo gettext("failed!") . "\n";
			}
		} else if (platform_booting()) {
			echo gettext("failed!") . "\n";
		}
	}
}

function system_setup_sysctl() {
	global $config;
	if (isset($config['system']['developerspew'])) {
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
	if (isset($config['system']['developerspew'])) {
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
	if (isset($config['system']['developerspew'])) {
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
	foreach ($supported_watchdogs as $sd) {
		if (stristr($file, "Geode")) {
			$install_watchdog = true;
		}
	}
	if ($install_watchdog == true) {
		if (is_process_running("watchdogd")) {
			mwexec("/usr/bin/killall watchdogd", true);
		}
		exec("/usr/sbin/watchdogd");
	}
}

function system_check_reset_button() {
	global $g;

	$specplatform = system_identify_specific_platform();

	switch ($specplatform['name']) {
		case 'alix':
		case 'wrap':
		case 'FW7541':
		case 'APU':
		case 'RCC-VE':
		case 'RCC-DFF':
			break;
		default:
			return 0;
	}

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

/*
 * attempt to identify the specific platform (for embedded systems)
 * Returns an array with two elements:
 * name => platform string (e.g. 'wrap', 'alix' etc.)
 * descr => human-readable description (e.g. "PC Engines WRAP")
 */
function system_identify_specific_platform() {
	global $g;

	if ($g['platform'] == 'generic-pc') {
		return array('name' => 'generic-pc', 'descr' => gettext("Generic PC"));
	}

	if ($g['platform'] == 'generic-pc-cdrom') {
		return array('name' => 'generic-pc-cdrom', 'descr' => gettext("Generic PC (CD-ROM)"));
	}

	/* Try to guess from smbios strings */
	unset($output);
	$_gb = exec('/bin/kenv smbios.system.product 2>/dev/null', $output);
	switch ($output[0]) {
		case 'FW7541':
			return (array('name' => 'FW7541', 'descr' => 'Netgate FW7541'));
			break;
		case 'APU':
			return (array('name' => 'APU', 'descr' => 'Netgate APU'));
			break;
		case 'RCC-VE':
			return (array('name' => 'RCC-VE', 'descr' => 'Netgate RCC-VE'));
			break;
		case 'DFFv2':
			return (array('name' => 'RCC-DFF', 'descr' => 'Netgate RCC-DFF'));
			break;
		case 'SYS-5018A-FTN4':
		case 'A1SAi':
			return (array('name' => 'C2758', 'descr' => 'Super Micro C2758'));
			break;
		case 'SYS-5018D-FN4T':
			return (array('name' => 'D1540-XG', 'descr' => 'Super Micro D1540-XG'));
			break;
	}

	/* the rest of the code only deals with 'embedded' platforms */
	if ($g['platform'] != 'nanobsd') {
		return array('name' => $g['platform'], 'descr' => $g['platform']);
	}

	$dmesg = get_single_sysctl('hw.model');

	if (strpos($dmesg, "PC Engines WRAP") !== false) {
		return array('name' => 'wrap', 'descr' => gettext('PC Engines WRAP'));
	}

	if (strpos($dmesg, "PC Engines ALIX") !== false) {
		return array('name' => 'alix', 'descr' => gettext('PC Engines ALIX'));
	}

	if (preg_match("/Soekris net45../", $dmesg, $matches)) {
		return array('name' => 'net45xx', 'descr' => $matches[0]);
	}

	if (preg_match("/Soekris net48../", $dmesg, $matches)) {
		return array('name' => 'net48xx', 'descr' => $matches[0]);
	}

	if (preg_match("/Soekris net55../", $dmesg, $matches)) {
		return array('name' => 'net55xx', 'descr' => $matches[0]);
	}

	unset($dmesg);

	$dmesg_boot = system_get_dmesg_boot();
	if (strpos($dmesg_boot, "PC Engines ALIX") !== false) {
		return array('name' => 'alix', 'descr' => gettext('PC Engines ALIX'));
	}
	unset($dmesg_boot);

	/* unknown embedded platform */
	return array('name' => 'embedded', 'descr' => gettext('embedded (unknown)'));
}

function system_get_dmesg_boot() {
	global $g;

	return file_get_contents("{$g['varlog_path']}/dmesg.boot");
}

?>
