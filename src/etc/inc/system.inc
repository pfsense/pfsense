<?php
/*
 * system.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('syslog.inc');

function activate_powerd() {
	global $config, $g;

	if (is_process_running("powerd")) {
		exec("/usr/bin/killall powerd");
	}
	if (isset($config['system']['powerd_enable'])) {
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

		mwexec("/usr/sbin/powerd" .
			" -b " . escapeshellarg($battery_mode) .
			" -a " . escapeshellarg($ac_mode) .
			" -n " . escapeshellarg($normal_mode));
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
	$_gb = exec("/sbin/sysctl -qnd {$sysctl}", $output);

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

	foreach(get_dns_nameservers() as $dns_ns) {
		$resolvconf .= "nameserver $dns_ns\n";
	}

	$ns = array();
	if (isset($syscfg['dnsallowoverride'])) {
		/* get dynamically assigned DNS servers (if any) */
		$ns = array_unique(get_searchdomains());
		foreach ($ns as $searchserver) {
			if ($searchserver) {
				$resolvconf .= "search {$searchserver}\n";
			}
		}
	}
	if (empty($ns)) {
		// Do not create blank search/domain lines, it can break tools like dig.
		if ($syscfg['domain']) {
			$resolvconf .= "search {$syscfg['domain']}\n";
		}
	}

	// Add EDNS support
	if (isset($config['unbound']['enable']) && isset($config['unbound']['edns'])) {
		$resolvconf .= "options edns0\n";
	}

	$dnslock = lock('resolvconf', LOCK_EX);

	$fd = fopen("{$g['etc_path']}/resolv.conf", "w");
	if (!$fd) {
		printf("Error: cannot open resolv.conf in system_resolvconf_generate().\n");
		unlock($dnslock);
		return 1;
	}

	fwrite($fd, $resolvconf);
	fclose($fd);

	// Prevent resolvconf(8) from rewriting our resolv.conf
	$fd = fopen("{$g['etc_path']}/resolvconf.conf", "w");
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

	// set up or tear down static routes for DNS servers
	$dnscounter = 1;
	$dnsgw = "dns{$dnscounter}gw";
	while (isset($config['system'][$dnsgw])) {
		/* setup static routes for dns servers */
		$gwname = $config['system'][$dnsgw];
		unset($gatewayip);
		unset($inet6);
		if ((!empty($gwname)) && ($gwname != "none")) {
			$gatewayip = lookup_gateway_ip_by_name($gwname);
			$inet6 = is_ipaddrv6($gatewayip) ? '-inet6 ' : '';
		}
		/* dns server array starts at 0 */
		$dnsserver = $syscfg['dnsserver'][$dnscounter - 1];
		if (!empty($dnsserver)) {
			if (is_ipaddr($gatewayip)) {
				route_add_or_change("-host {$inet6}{$dnsserver} {$gatewayip}");
			} else {
				/* Remove old route when disable gw */
				$gatewayip = exec("/sbin/route -n get {$dnsserver} | /usr/bin/awk '/gateway:/ {print $2;}'");
				mwexec("/sbin/route -q delete -host {$inet6}{$dnsserver} " . escapeshellarg($gatewayip));
				if (isset($config['system']['route-debug'])) {
					$mt = microtime();
					log_error("ROUTING debug: $mt - route delete -host {$inet6}{$dnsserver} {$gatewayip}");
				}
			}
		}
		$dnscounter++;
		$dnsgw = "dns{$dnscounter}gw";
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

/* Create localhost + local interfaces entries for /etc/hosts */
function system_hosts_local_entries() {
	global $config;

	$syscfg = $config['system'];

	$hosts = array();
	$hosts[] = array(
	    'ipaddr' => '127.0.0.1',
	    'fqdn' => 'localhost.' . $syscfg['domain'],
	    'name' => 'localhost',
	    'domain' => $syscfg['domain']
	);
	$hosts[] = array(
	    'ipaddr' => '::1',
	    'fqdn' => 'localhost.' . $syscfg['domain'],
	    'name' => 'localhost',
	    'domain' => $syscfg['domain']
	);

	if ($config['interfaces']['lan']) {
		$sysiflist = array('lan' => "lan");
	} else {
		$sysiflist = get_configured_interface_list();
	}

	$hosts_if_found = false;
	$local_fqdn = "{$syscfg['hostname']}.{$syscfg['domain']}";
	foreach ($sysiflist as $sysif) {
		if ($sysif != 'lan' && interface_has_gateway($sysif)) {
			continue;
		}
		$cfgip = get_interface_ip($sysif);
		if (is_ipaddrv4($cfgip)) {
			$hosts[] = array(
			    'ipaddr' => $cfgip,
			    'fqdn' => $local_fqdn,
			    'name' => $syscfg['hostname'],
			    'domain' => $syscfg['domain']
			);
			$hosts_if_found = true;
		}
		if (!isset($syscfg['ipv6dontcreatelocaldns'])) {
			$cfgipv6 = get_interface_ipv6($sysif);
			if (is_ipaddrv6($cfgipv6)) {
				$hosts[] = array(
					'ipaddr' => $cfgipv6,
					'fqdn' => $local_fqdn,
					'name' => $syscfg['hostname'],
					'domain' => $syscfg['domain']
				);
				$hosts_if_found = true;
			}
		}
		if ($hosts_if_found == true) {
			break;
		}
	}

	return $hosts;
}

/* Read host override entries from dnsmasq or unbound */
function system_hosts_override_entries($dnscfg) {
	$hosts = array();

	if (!is_array($dnscfg) ||
	    !is_array($dnscfg['hosts']) ||
	    !isset($dnscfg['enable'])) {
		return $hosts;
	}

	foreach ($dnscfg['hosts'] as $host) {
		$fqdn = '';
		if ($host['host'] || $host['host'] == "0") {
			$fqdn .= "{$host['host']}.";
		}
		$fqdn .= $host['domain'];

		$hosts[] = array(
		    'ipaddr' => $host['ip'],
		    'fqdn' => $fqdn,
		    'name' => $host['host'],
		    'domain' => $host['domain']
		);

		if (!is_array($host['aliases']) ||
		    !is_array($host['aliases']['item'])) {
			continue;
		}

		foreach ($host['aliases']['item'] as $alias) {
			$fqdn = '';
			if ($alias['host'] || $alias['host'] == "0") {
				$fqdn .= "{$alias['host']}.";
			}
			$fqdn .= $alias['domain'];

			$hosts[] = array(
			    'ipaddr' => $host['ip'],
			    'fqdn' => $fqdn,
			    'name' => $alias['host'],
			    'domain' => $alias['domain']
			);
		}
	}

	return $hosts;
}

/* Read all dhcpd/dhcpdv6 staticmap entries */
function system_hosts_dhcpd_entries() {
	global $config;

	$hosts = array();
	$syscfg = $config['system'];

	if (is_array($config['dhcpd'])) {
		$conf_dhcpd = $config['dhcpd'];
	} else {
		$conf_dhcpd = array();
	}

	foreach ($conf_dhcpd as $dhcpif => $dhcpifconf) {
		if (!is_array($dhcpifconf['staticmap']) ||
		    !isset($dhcpifconf['enable'])) {
			continue;
		}
		foreach ($dhcpifconf['staticmap'] as $host) {
			if (!$host['ipaddr'] ||
			    !$host['hostname']) {
				continue;
			}

			$fqdn = $host['hostname'] . ".";
			$domain = "";
			if ($host['domain']) {
				$domain = $host['domain'];
			} elseif ($dhcpifconf['domain']) {
				$domain = $dhcpifconf['domain'];
			} else {
				$domain = $syscfg['domain'];
			}

			$hosts[] = array(
			    'ipaddr' => $host['ipaddr'],
			    'fqdn' => $fqdn . $domain,
			    'name' => $host['hostname'],
			    'domain' => $domain
			);
		}
	}
	unset($conf_dhcpd);

	if (is_array($config['dhcpdv6'])) {
		$conf_dhcpdv6 = $config['dhcpdv6'];
	} else {
		$conf_dhcpdv6 = array();
	}

	foreach ($conf_dhcpdv6 as $dhcpif => $dhcpifconf) {
		if (!is_array($dhcpifconf['staticmap']) ||
		    !isset($dhcpifconf['enable'])) {
			continue;
		}

		if (isset($config['interfaces'][$dhcpif]['ipaddrv6']) &&
		    $config['interfaces'][$dhcpif]['ipaddrv6'] ==
		    'track6') {
			$isdelegated = true;
		} else {
			$isdelegated = false;
		}

		foreach ($dhcpifconf['staticmap'] as $host) {
			$ipaddrv6 = $host['ipaddrv6'];

			if (!$ipaddrv6 || !$host['hostname']) {
				continue;
			}

			if ($isdelegated) {
				/*
				 * We are always in an "end-user" subnet
				 * here, which all are /64 for IPv6.
				 */
				$ipaddrv6 = merge_ipv6_delegated_prefix(
				    get_interface_ipv6($dhcpif),
				    $ipaddrv6, 64);
			}

			$fqdn = $host['hostname'] . ".";
			$domain = "";
			if ($host['domain']) {
				$domain = $host['domain'];
			} elseif ($dhcpifconf['domain']) {
				$domain = $dhcpifconf['domain'];
			} else {
				$domain = $syscfg['domain'];
			}

			$hosts[] = array(
			    'ipaddr' => $ipaddrv6,
			    'fqdn' => $fqdn . $domain,
			    'name' => $host['hostname'],
			    'domain' => $domain
			);
		}
	}
	unset($conf_dhcpdv6);

	return $hosts;
}

/* Concatenate local, dnsmasq/unbound and dhcpd/dhcpdv6 hosts entries */
function system_hosts_entries($dnscfg) {
	$local = array();
	if (!isset($dnscfg['disable_auto_added_host_entries'])) {
		$local = system_hosts_local_entries();
	}

	$dns = array();
	$dhcpd = array();
	if (isset($dnscfg['enable'])) {
		$dns = system_hosts_override_entries($dnscfg);
		if (isset($dnscfg['regdhcpstatic'])) {
			$dhcpd = system_hosts_dhcpd_entries();
		}
	}

	if (isset($dnscfg['dhcpfirst'])) {
		return array_merge($local, $dns, $dhcpd);
	} else {
		return array_merge($local, $dhcpd, $dns);
	}
}

function system_hosts_generate() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "system_hosts_generate() being called $mt\n";
	}

	// prefer dnsmasq for hosts generation where it's enabled. It relies
	// on hosts for name resolution of its overrides, unbound does not.
	if (isset($config['dnsmasq']) && isset($config['dnsmasq']['enable'])) {
		$dnsmasqcfg = $config['dnsmasq'];
	} else {
		$dnsmasqcfg = $config['unbound'];
	}

	$syscfg = $config['system'];
	$hosts = "";
	$lhosts = "";
	$dhosts = "";

	$hosts_array = system_hosts_entries($dnsmasqcfg);
	foreach ($hosts_array as $host) {
		$hosts .= "{$host['ipaddr']}\t";
		if ($host['name'] == "localhost") {
			$hosts .= "{$host['name']} {$host['fqdn']}";
		} else {
			$hosts .= "{$host['fqdn']} {$host['name']}";
		}
		$hosts .= "\n";
	}
	unset($hosts_array);

	$fd = fopen("{$g['etc_path']}/hosts", "w");
	if (!$fd) {
		log_error(gettext(
		    "Error: cannot open hosts file in system_hosts_generate()."
		    ));
		return 1;
	}

	/*
	 * Do not remove this because dhcpleases monitors with kqueue it needs
	 * to be killed before writing to hosts files.
	 */
	if (file_exists("{$g['varrun_path']}/dhcpleases.pid")) {
		sigkillbypid("{$g['varrun_path']}/dhcpleases.pid", "TERM");
		@unlink("{$g['varrun_path']}/dhcpleases.pid");
	}

	fwrite($fd, $hosts);
	fclose($fd);

	if (isset($config['unbound']['enable'])) {
		require_once("unbound.inc");
		unbound_hosts_generate();
	}

	/* restart dhcpleases */
	if (!platform_booting()) {
		system_dhcpleases_configure();
	}

	return 0;
}

function system_dhcpleases_configure() {
	global $config, $g;
	if (!function_exists('is_dhcp_server_enabled')) {
		require_once('pfsense-utils.inc');
	}
	$pidfile = "{$g['varrun_path']}/dhcpleases.pid";

	/* Start the monitoring process for dynamic dhcpclients. */
	if (((isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcp'])) ||
	    (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcp']))) &&
	    (is_dhcp_server_enabled())) {
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
		mwexec("/usr/local/sbin/dhcpleases -l {$g['dhcpd_chroot_path']}/var/db/dhcpd.leases -d {$config['system']['domain']} -p {$g['varrun_path']}/{$dns_pid} {$unbound_conf} -h {$g['etc_path']}/hosts");
	} else {
		if (isvalidpid($pidfile)) {
			sigkillbypid($pidfile, "TERM");
			@unlink($pidfile);
		}
		if (file_exists("{$g['unbound_chroot_path']}/dhcpleases_entries.conf")) {
			$dhcpleases = fopen("{$g['unbound_chroot_path']}/dhcpleases_entries.conf", "w");
			ftruncate($dhcpleases, 0);
			fclose($dhcpleases);
		}
	}
}

function system_get_dhcpleases() {
	global $config, $g;

	$leases = array();
	$leases['lease'] = array();
	$leases['failover'] = array();

	$leases_file = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

	if (!file_exists($leases_file)) {
		return $leases;
	}

	$leases_content = file($leases_file, FILE_IGNORE_NEW_LINES |
	    FILE_IGNORE_NEW_LINES);

	if ($leases_content === FALSE) {
		return $leases;
	}

	$arp_table = system_get_arp_table();

	$arpdata_ip = array();
	$arpdata_mac = array();
	foreach ($arp_table as $arp_entry) {
		if (isset($arpentry['incomplete'])) {
			continue;
		}
		$arpdata_ip[] = $arp_entry['ip-address'];
		$arpdata_mac[] = $arp_entry['mac-address'];
	}
	unset($arp_table);

	/*
	 * Translate these once so we don't do it over and over in the loops
	 * below.
	 */
	$online_string = gettext("online");
	$offline_string = gettext("offline");
	$active_string = gettext("active");
	$expired_string = gettext("expired");
	$reserved_string = gettext("reserved");
	$dynamic_string = gettext("dynamic");
	$static_string = gettext("static");

	$lease_regex = '/^lease\s+([^\s]+)\s+{$/';
	$starts_regex = '/^\s*(starts|ends)\s+\d+\s+([\d\/]+|never)\s*(|[\d:]*);$/';
	$binding_regex = '/^\s*binding\s+state\s+(.+);$/';
	$mac_regex = '/^\s*hardware\s+ethernet\s+(.+);$/';
	$hostname_regex = '/^\s*client-hostname\s+"(.+)";$/';

	$failover_regex = '/^failover\s+peer\s+"(.+)"\s+state\s+{$/';
	$state_regex = '/\s*(my|partner)\s+state\s+(.+)\s+at\s+\d+\s+([\d\/]+)\s+([\d:]+);$/';

	$lease = false;
	$failover = false;
	$dedup_lease = false;
	$dedup_failover = false;
	foreach ($leases_content as $line) {
		/* Skip comments */
		if (preg_match('/^\s*(|#.*)$/', $line)) {
			continue;
		}

		if (preg_match('/}$/', $line)) {
			if ($lease) {
				if (empty($item['hostname'])) {
					$hostname = gethostbyaddr($item['ip']);
					if (!empty($hostname)) {
						$item['hostname'] = $hostname;
					}
				}
				$leases['lease'][] = $item;
				$lease = false;
				$dedup_lease = true;
			} else if ($failover) {
				$leases['failover'][] = $item;
				$failover = false;
				$dedup_failover = true;
			}
			continue;
		}

		if (preg_match($lease_regex, $line, $m)) {
			$lease = true;
			$item = array();
			$item['ip'] = $m[1];
			$item['type'] = $dynamic_string;
			continue;
		}

		if ($lease) {
			if (preg_match($starts_regex, $line, $m)) {
				/*
				 * Quote from dhcpd.leases(5) man page:
				 * If a lease will never expire, date is never
				 * instead of an actual date
				 */
				if ($m[2] == "never") {
					$item[$m[1]] = gettext("Never");
				} else {
					$item[$m[1]] = dhcpd_date_adjust_gmt(
					    $m[2] . ' ' . $m[3]);
				}
				continue;
			}

			if (preg_match($binding_regex, $line, $m)) {
				switch ($m[1]) {
					case "active":
						$item['act'] = $active_string;
						break;
					case "free":
						$item['act'] = $expired_string;
						$item['online'] =
						    $offline_string;
						break;
					case "backup":
						$item['act'] = $reserved_string;
						$item['online'] =
						    $offline_string;
						break;
				}
				continue;
			}

			if (preg_match($mac_regex, $line, $m) &&
			    is_macaddr($m[1])) {
				$item['mac'] = $m[1];

				if (in_array($item['ip'], $arpdata_ip)) {
					$item['online'] = $online_string;
				} else {
					$item['online'] = $offline_string;
				}
				continue;
			}

			if (preg_match($hostname_regex, $line, $m)) {
				$item['hostname'] = $m[1];
			}
		}

		if (preg_match($failover_regex, $line, $m)) {
			$failover = true;
			$item = array();
			$item['name'] = $m[1] . ' (' .
			    convert_friendly_interface_to_friendly_descr(
			    substr($m[1],5)) . ')';
			continue;
		}

		if ($failover && preg_match($state_regex, $line, $m)) {
			$item[$m[1] . 'state'] = $m[2];
			$item[$m[1] . 'date'] = dhcpd_date_adjust_gmt($m[3] .
			    ' ' . $m[4]);
			continue;
		}
	}

	foreach ($config['interfaces'] as $ifname => $ifarr) {
		if (!is_array($config['dhcpd'][$ifname]) ||
		    !is_array($config['dhcpd'][$ifname]['staticmap'])) {
			continue;
		}

		foreach ($config['dhcpd'][$ifname]['staticmap'] as $idx =>
		    $static) {
			if (empty($static['mac']) && empty($static['cid'])) {
				continue;
			}

			$slease = array();
			$slease['ip'] = $static['ipaddr'];
			$slease['type'] = $static_string;
			if (!empty($static['cid'])) {
				$slease['cid'] = $static['cid'];
			}
			$slease['mac'] = $static['mac'];
			$slease['if'] = $ifname;
			$slease['starts'] = "";
			$slease['ends'] = "";
			$slease['hostname'] = $static['hostname'];
			$slease['descr'] = $static['descr'];
			$slease['act'] = $static_string;
			$slease['online'] = in_array(strtolower($slease['mac']),
			    $arpdata_mac) ? $online_string : $offline_string;
			$slease['staticmap_array_index'] = $idx;
			$leases['lease'][] = $slease;
			$dedup_lease = true;
		}
	}

	if ($dedup_lease) {
		$leases['lease'] = array_remove_duplicate($leases['lease'],
		    'ip');
	}
	if ($dedup_failover) {
		$leases['failover'] = array_remove_duplicate(
		    $leases['failover'], 'name');
		asort($leases['failover']);
	}

	return $leases;
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

	$gateways_arr = return_gateways_array(false, true);
	foreach ($gateways_arr as $gateway) {
		// setup static interface routes for nonlocal gateways
		if (isset($gateway["nonlocalgateway"])) {
			$srgatewayip = $gateway['gateway'];
			$srinterfacegw = $gateway['interface'];
			if (is_ipaddr($srgatewayip) && !empty($srinterfacegw)) {
				$inet = (!is_ipaddrv4($srgatewayip) ? "-inet6" : "-inet");
				route_add_or_change("{$inet} {$srgatewayip} " .
				    "-iface {$srinterfacegw}");
			}
		}
	}

	$gateways_status = return_gateways_status(true);
	fixup_default_gateway("inet", $gateways_status, $gateways_arr);
	fixup_default_gateway("inet6", $gateways_status, $gateways_arr);

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
			if (!strcasecmp("Null", substr($rtent['gateway'], 0, 4))) {
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
				mwexec("/sbin/route delete " . escapeshellarg($ip) . " " . escapeshellarg($gatewayip), true);
				if (isset($config['system']['route-debug'])) {
					$mt = microtime();
					log_error("ROUTING debug: $mt - route delete $ip $gatewayip ");
				}
			}

			if (isset($rtent['disabled'])) {
				/* XXX: This can break things by deleting routes that shouldn't be deleted - OpenVPN, dynamic routing scenarios, etc. redmine #3709 */
				foreach ($ips as $ip) {
					mwexec("/sbin/route delete " . escapeshellarg($ip) . " " . escapeshellarg($gatewayip), true);
					if (isset($config['system']['route-debug'])) {
						$mt = microtime();
						log_error("ROUTING debug: $mt - route delete $ip $gatewayip ");
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

				$cmd = "{$inet} {$blackhole} {$ip} ";

				if (is_subnet($ip)) {
					if (is_ipaddr($gatewayip)) {
						if (is_linklocal($gatewayip) == "6" && !strpos($gatewayip, '%')) {
							// add interface scope for link local v6 routes
							$gatewayip .= "%$interfacegw";
						}
						route_add_or_change($cmd . $gatewayip);
					} else if (!empty($interfacegw)) {
						route_add_or_change($cmd . "-iface {$interfacegw}");
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

function system_webgui_create_certificate() {
	global $config, $g, $cert_strict_values;

	init_config_arr(array('ca'));
	$a_ca = &$config['ca'];
	init_config_arr(array('cert'));
	$a_cert = &$config['cert'];
	log_error(gettext("Creating SSL/TLS Certificate for this host"));

	$cert = array();
	$cert['refid'] = uniqid();
	$cert['descr'] = sprintf(gettext("webConfigurator default (%s)"), $cert['refid']);
	$cert_hostname = "{$config['system']['hostname']}-{$cert['refid']}";

	$dn = array(
		'organizationName' => "{$g['product_name']} webConfigurator Self-Signed Certificate",
		'commonName' => $cert_hostname,
		'subjectAltName' => "DNS:{$cert_hostname}");
	$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
	if (!cert_create($cert, null, 2048, $cert_strict_values['max_server_cert_lifetime'], $dn, "self-signed", "sha256")) {
		while ($ssl_err = openssl_error_string()) {
			log_error(sprintf(gettext("Error creating WebGUI Certificate: openssl library returns: %s"), $ssl_err));
		}
		error_reporting($old_err_level);
		return null;
	}
	error_reporting($old_err_level);

	$a_cert[] = $cert;
	$config['system']['webgui']['ssl-certref'] = $cert['refid'];
	write_config(sprintf(gettext("Generated new self-signed SSL/TLS certificate for HTTPS (%s)"), $cert['refid']));
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
		$hsts = isset($config['system']['webgui']['disablehsts']) ? false : true;
	}

	/* generate nginx configuration */
	system_generate_nginx_config("{$g['varetc_path']}/nginx-webConfigurator.conf",
		$crt, $key, $ca, "nginx-webConfigurator.pid", $portarg, "/usr/local/www/",
		"cert.crt", "cert.key", false, $hsts);

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

function get_dns_nameservers($add_v6_brackets = false) {
	global $config;

	$dns_nameservers = array();

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "get_dns_nameservers() being called $mt\n";
	}

	$syscfg = $config['system'];
	if ((((isset($config['dnsmasq']['enable'])) &&
   		(empty($config['dnsmasq']['port']) || $config['dnsmasq']['port'] == "53") &&
	      	(empty($config['dnsmasq']['interface']) ||
	       	in_array("lo0", explode(",", $config['dnsmasq']['interface'])))) ||
	     	((isset($config['unbound']['enable'])) &&
	      	(empty($config['unbound']['port']) || $config['unbound']['port'] == "53") &&
	      	(empty($config['unbound']['active_interface']) ||
	       	in_array("lo0", explode(",", $config['unbound']['active_interface'])) ||
	       	in_array("all", explode(",", $config['unbound']['active_interface']), true)))) &&
	     	(!isset($config['system']['dnslocalhost']))) {
			$dns_nameservers[] = "127.0.0.1";
	}
	/* get dynamically assigned DNS servers (if any) */
	$ns = array_unique(get_nameservers());
	if (isset($syscfg['dnsallowoverride'])) {
		if(!is_array($ns)) {
			$ns = array();
		}
		foreach ($ns as $nameserver) {
			if ($nameserver) {
				if ($add_v6_brackets && is_ipaddrv6($nameserver)) {
					$nameserver = "[{$nameserver}]";
				}
				$dns_nameservers[] = $nameserver;
			}
		}
	} else {
		/* If override is disallowed, ignore the dynamic NS entries
		 * https://redmine.pfsense.org/issues/9963 */
		$ns = array();
	}
	if (is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $sys_dnsserver) {
			if ($sys_dnsserver && (!in_array($sys_dnsserver, $ns))) {
				if ($add_v6_brackets && is_ipaddrv6($sys_dnsserver)) {
					$sys_dnsserver = "[{$sys_dnsserver}]";
				}
				$dns_nameservers[] = $sys_dnsserver;
			}
		}
	}
	return array_unique($dns_nameservers);
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
	$captive_portal = false,
	$hsts = true) {

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
				$cp_hostcheck .= "\t\tif (\$http_host ~* $cpint_ip) {\n";
				$cp_hostcheck .= "\t\t\tset \$cp_redirect no;\n";
				$cp_hostcheck .= "\t\t}\n";
			}
		}
		if (isset($config['captiveportal'][$captive_portal]['httpsname']) &&
		    is_domain($config['captiveportal'][$captive_portal]['httpsname'])) {
			$cp_hostcheck .= "\t\tif (\$http_host ~* {$config['captiveportal'][$captive_portal]['httpsname']}) {\n";
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

	/* Disable file logging */
	$nginx_config .= "error_log /dev/null;\n";
	if (!isset($config['syslog']['nolognginx'])) {
		/* Send nginx error log to syslog */
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

	access_log      syslog:server=unix:/var/run/log,facility=local5 combined;

EOD;

	if ($captive_portal !== false) {
		$nginx_config .= "\tlimit_conn_zone \$binary_remote_addr zone=addr:10m;\n";
		$nginx_config .= "\tkeepalive_timeout 0;\n";
	} else {
		$nginx_config .= "\tkeepalive_timeout 75;\n";
	}

	if ($cert <> "" and $key <> "") {
		$nginx_config .= "\n";
		$nginx_config .= "\tserver {\n";
		$nginx_config .= "\t\tlisten {$nginx_port} ssl http2;\n";
		$nginx_config .= "\t\tlisten [::]:{$nginx_port} ssl http2;\n";
		$nginx_config .= "\n";
		$nginx_config .= "\t\tssl_certificate         {$g['varetc_path']}/{$cert_location};\n";
		$nginx_config .= "\t\tssl_certificate_key     {$g['varetc_path']}/{$key_location};\n";
		$nginx_config .= "\t\tssl_session_timeout     10m;\n";
		$nginx_config .= "\t\tkeepalive_timeout       70;\n";
		$nginx_config .= "\t\tssl_session_cache       shared:SSL:10m;\n";
		if ($captive_portal !== false) {
			// leave TLSv1.1 for CP for now for compatibility
			$nginx_config .= "\t\tssl_protocols   TLSv1.1 TLSv1.2 TLSv1.3;\n";
		} else {
			$nginx_config .= "\t\tssl_protocols   TLSv1.2 TLSv1.3;\n";
		}
		$nginx_config .= "\t\tssl_ciphers \"EECDH+AESGCM:EDH+AESGCM:AES256+EECDH:AES256+EDH:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305\";\n";
		$nginx_config .= "\t\tssl_prefer_server_ciphers       on;\n";
		if ($captive_portal === false && $hsts !== false) {
			$nginx_config .= "\t\tadd_header Strict-Transport-Security \"max-age=31536000\";\n";
		}
		$nginx_config .= "\t\tadd_header X-Content-Type-Options nosniff;\n";
		$nginx_config .= "\t\tssl_session_tickets off;\n";
		$nginx_config .= "\t\tssl_dhparam /etc/dh-parameters.4096;\n";
		$cert_temp = lookup_cert($config['system']['webgui']['ssl-certref']);
		if (($config['system']['webgui']['ocsp-staple'] == true) or
		    (cert_get_ocspstaple($cert_temp['crt']) == true)) {
			$nginx_config .= "\t\tssl_stapling on;\n";
			$nginx_config .= "\t\tssl_stapling_verify on;\n";
			$nginx_config .= "\t\tresolver " . implode(" ", get_dns_nameservers(true)) . " valid=300s;\n";
			$nginx_config .= "\t\tresolver_timeout 5s;\n";
		}
	} else {
		$nginx_config .= "\n";
		$nginx_config .= "\tserver {\n";
		$nginx_config .= "\t\tlisten {$nginx_port};\n";
		$nginx_config .= "\t\tlisten [::]:{$nginx_port};\n";
	}

	$nginx_config .= <<<EOD

		client_max_body_size 200m;

		gzip on;
		gzip_types text/plain text/css text/javascript application/x-javascript text/xml application/xml application/xml+rss application/json;


EOD;

	if ($captive_portal !== false) {
		$nginx_config .= <<<EOD
$captive_portal_maxprocperip
$cp_hostcheck
$cp_rewrite
		log_not_found off;

EOD;

	}

	$nginx_config .= <<<EOD
		root "{$document_root}";
		location / {
			index  index.php index.html index.htm;
		}
		location ~ \.inc$ {
			deny all;
			return 403;
		}
		location ~ \.php$ {
			try_files \$uri =404; #  This line closes a potential security hole
			# ensuring users can't execute uploaded files
			# see: http://forum.nginx.org/read.php?2,88845,page=3
			fastcgi_pass   unix:{$g['varrun_path']}/php-fpm.socket;
			fastcgi_index  index.php;
			fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
			# Fix httpoxy - https://httpoxy.org/#fix-now
			fastcgi_param  HTTP_PROXY  "";
			fastcgi_read_timeout 180;
			include        /usr/local/etc/nginx/fastcgi_params;
		}
		location ~ (^/status$) {
			allow 127.0.0.1;
			deny all;
			fastcgi_pass   unix:{$g['varrun_path']}/php-fpm.socket;
			fastcgi_index  index.php;
			fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
			# Fix httpoxy - https://httpoxy.org/#fix-now
			fastcgi_param  HTTP_PROXY  "";
			fastcgi_read_timeout 360;
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
		chmod("{$g['varetc_path']}/{$cert_location}", 0644);
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
		return 301 https://\$http_host$redirectport\$request_uri;
	}

EOD;
	}

	$nginx_config .= "}\n";

	$fd = fopen("{$filename}", "w");
	if (!$fd) {
		printf(gettext('Error: cannot open %1$s in system_generate_nginx_config().%2$s'), $filename, "\n");
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
	/* DO NOT remove \n otherwise tzsetup will fail */
	@file_put_contents("/var/db/zoneinfo", $timezone . "\n");
	mwexec("/usr/sbin/tzsetup -r");

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}
}

function check_gps_speed($device) {
	usleep(1000);
	// Set timeout to 5s
	$timeout=microtime(true)+5;
	if ($fp = fopen($device, 'r')) {
		stream_set_blocking($fp, 0);
		stream_set_timeout($fp, 5);
		$contents = "";
		$cnt = 0;
		$buffersize = 256;
		do {
			$c = fread($fp, $buffersize - $cnt);

			// Wait for data to arive
			if (($c === false) || (strlen($c) == 0)) {
				usleep(500);
				continue;
			}

			$contents.=$c;
			$cnt = $cnt + strlen($c);
		} while (($cnt < $buffersize) && (microtime(true) < $timeout));
		fclose($fp);

		$nmeasentences = ['RMC', 'GGA', 'GLL', 'ZDA', 'ZDG', 'PGRMF'];
		foreach ($nmeasentences as $sentence) {
			if (strpos($contents, $sentence) > 0) {
				return true;
			}
		}
		if (strpos($contents, '0') > 0) {
			$filters = ['`', '?', '/', '~'];
			foreach ($filters as $filter) {
				if (strpos($contents, $filter) !== false) {
					return false;
				}
			}
			return true;
		}
	}
	return false;
}

/* Generate list of possible NTP poll values
 * https://redmine.pfsense.org/issues/9439 */
global $ntp_poll_min_value, $ntp_poll_max_value;
global $ntp_poll_min_default_gps, $ntp_poll_max_default_gps;
global $ntp_poll_min_default_pps, $ntp_poll_max_default_pps;
global $ntp_poll_min_default, $ntp_poll_max_default;
$ntp_poll_min_value = 4;
$ntp_poll_max_value = 17;
$ntp_poll_min_default_gps = 4;
$ntp_poll_max_default_gps = 4;
$ntp_poll_min_default_pps = 4;
$ntp_poll_max_default_pps = 4;
$ntp_poll_min_default = 'omit';
$ntp_poll_max_default = 9;

function system_ntp_poll_values() {
	global $ntp_poll_min_value, $ntp_poll_max_value;
	$poll_values = array("" => gettext('Default'));

	for ($i = $ntp_poll_min_value; $i <= $ntp_poll_max_value; $i++) {
		$sec = 2 ** $i;
		$poll_values[$i] = $i . ': ' . number_format($sec) . ' ' . gettext('seconds') .
					' (' . convert_seconds_to_dhms($sec) . ')';
	}

	$poll_values['omit'] = gettext('Omit (Do not set)');
	return $poll_values;
}

function system_ntp_fixup_poll_value($type, $configvalue, $default) {
	$pollstring = "";

	if (empty($configvalue)) {
		$configvalue = $default;
	}

	if ($configvalue != 'omit') {
		$pollstring = " {$type} {$configvalue}";
	}

	return $pollstring;
}

function system_ntp_setup_gps($serialport) {
	global $config, $g;

	if (is_array($config['ntpd']) && ($config['ntpd']['enable'] == 'disabled')) {
		return false;
	}

	init_config_arr(array('ntpd', 'gps', 'speed'));
	$gps_device = '/dev/gps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport)) {
		return false;
	}

	// Create symlink that ntpd requires
	unlink_if_exists($gps_device);
	@symlink($serialport, $gps_device);

	$gpsbaud = '4800';
	$speeds = array(
		0 => '4800', 
		16 => '9600', 
		32 => '19200', 
		48 => '38400', 
		64 => '57600', 
		80 => '115200'
	);
	if (!empty($config['ntpd']['gps']['speed']) && array_key_exists($config['ntpd']['gps']['speed'], $speeds)) {
		$gpsbaud = $speeds[$config['ntpd']['gps']['speed']];
	}

	system_ntp_setup_rawspeed($serialport, $gpsbaud);

	$autospeed = ($config['ntpd']['gps']['speed'] == 'autoalways' || $config['ntpd']['gps']['speed'] == 'autoset');
	if ($autospeed || ($config['ntpd']['gps']['autobaudinit'] && !check_gps_speed($gps_device))) {
		$found = false;
		foreach ($speeds as $baud) {
			system_ntp_setup_rawspeed($serialport, $baud);
			if ($found = check_gps_speed($gps_device)) {
				if ($autospeed) {
					$saveconfig = ($config['ntpd']['gps']['speed'] == 'autoset');
					$config['ntpd']['gps']['speed'] = array_search($baud, $speeds);
					$gpsbaud = $baud;
					if ($saveconfig) {
						write_config(sprintf(gettext('Autoset GPS baud rate to %s'), $baud));
					}
				}
				break;
			}
		}
		if ($found === false) {
			log_error(gettext("Could not find correct GPS baud rate."));
			return false;
		}
	}

	/* Send the following to the GPS port to initialize the GPS */
	if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['type'])) {
		$gps_init = base64_decode($config['ntpd']['gps']['initcmd']);
	} else {
		$gps_init = base64_decode('JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ==');
	}

	/* XXX: Why not file_put_contents to the device */
	@file_put_contents('/tmp/gps.init', $gps_init);
	mwexec("cat /tmp/gps.init > {$serialport}");

	if ($found && $config['ntpd']['gps']['autobaudinit']) {
		system_ntp_setup_rawspeed($serialport, $gpsbaud);
	}

	/* Remove old /etc/remote entry if it exists */
	if (mwexec("/usr/bin/grep -c '^gps0' /etc/remote")) {
		mwexec("/usr/bin/sed -i '' -n '/gps0/!p' /etc/remote");
	}

	/* Add /etc/remote entry in case we need to read from the GPS with tip */
	if (intval(`/usr/bin/grep -c '^gps0' /etc/remote`) == 0) {
		@file_put_contents("/etc/remote", "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:\n", FILE_APPEND);
	}

	return true;
}

// Configure the serial port for raw IO and set the speed
function system_ntp_setup_rawspeed($serialport, $baud) {
	mwexec("/bin/stty -f " .  escapeshellarg($serialport) . " raw speed " . escapeshellarg($baud));
	mwexec("/bin/stty -f " .  escapeshellarg($serialport) . ".init raw speed " . escapeshellarg($baud));
}

function system_ntp_setup_pps($serialport) {
	global $config, $g;

	$pps_device = '/dev/pps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport)) {
		return false;
	}
	// If ntpd is disabled, just return
	if (is_array($config['ntpd']) && ($config['ntpd']['enable'] == 'disabled')) {
		return false;
	}

	// Create symlink that ntpd requires
	unlink_if_exists($pps_device);
	@symlink($serialport, $pps_device);


	return true;
}

function system_ntp_configure() {
	global $config, $g;
	global $ntp_poll_min_default_gps, $ntp_poll_max_default_gps;
	global $ntp_poll_min_default_pps, $ntp_poll_max_default_pps;
	global $ntp_poll_min_default, $ntp_poll_max_default;

	$driftfile = "/var/db/ntpd.drift";
	$statsdir = "/var/log/ntp";
	$gps_device = '/dev/gps0';

	safe_mkdir($statsdir);

	if (!is_array($config['ntpd'])) {
		$config['ntpd'] = array();
	}
	// ntpd is disabled, just stop it and return
	if ($config['ntpd']['enable'] == 'disabled') {
		while (isvalidpid("{$g['varrun_path']}/ntpd.pid")) {
			killbypid("{$g['varrun_path']}/ntpd.pid");
		}
		@unlink("{$g['varrun_path']}/ntpd.pid");
		@unlink("{$g['varetc_path']}/ntpd.conf");
		log_error("NTPD is disabled.");
		return;
	}

	if (platform_booting()) {
		echo gettext("Starting NTP Server...");
	}

	/* if ntpd is running, kill it */
	while (isvalidpid("{$g['varrun_path']}/ntpd.pid")) {
		killbypid("{$g['varrun_path']}/ntpd.pid");
	}
	@unlink("{$g['varrun_path']}/ntpd.pid");

	$ntpcfg = "# \n";
	$ntpcfg .= "# pfSense ntp configuration file \n";
	$ntpcfg .= "# \n\n";
	$ntpcfg .= "tinker panic 0 \n";

	/* Add Orphan mode */
	$ntpcfg .= "# Orphan mode stratum and Maximum candidate NTP peers\n";
	$ntpcfg .= 'tos orphan ';
	if (!empty($config['ntpd']['orphan'])) {
		$ntpcfg .= $config['ntpd']['orphan'];
	} else {
		$ntpcfg .= '12';
	}
	/* Add Maximum candidate NTP peers */
	$ntpcfg .= ' maxclock ';
	if (!empty($config['ntpd']['ntpmaxpeers'])) {
		$ntpcfg .= $config['ntpd']['ntpmaxpeers'];
	} else {
		$ntpcfg .= '5';
	}
	$ntpcfg .= "\n";

	/* Add PPS configuration */
	if (is_array($config['ntpd']['pps']) && !empty($config['ntpd']['pps']['port']) &&
	    file_exists('/dev/'.$config['ntpd']['pps']['port']) &&
	    system_ntp_setup_pps($config['ntpd']['pps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# PPS Setup\n";
		$ntpcfg .= 'server 127.127.22.0';
		$ntpcfg .= system_ntp_fixup_poll_value('minpoll', $config['ntpd']['pps']['ppsminpoll'], $ntp_poll_min_default_pps);
		$ntpcfg .= system_ntp_fixup_poll_value('maxpoll', $config['ntpd']['pps']['ppsmaxpoll'], $ntp_poll_max_default_pps);
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
	    system_ntp_setup_gps($config['ntpd']['gps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# GPS Setup\n";
		$ntpcfg .= 'server 127.127.20.0 mode ';
		if (!empty($config['ntpd']['gps']['nmea']) || !empty($config['ntpd']['gps']['speed']) || !empty($config['ntpd']['gps']['subsec']) || !empty($config['ntpd']['gps']['processpgrmf'])) {
			if (!empty($config['ntpd']['gps']['nmea'])) {
				$ntpmode = (int) $config['ntpd']['gps']['nmea'];
			}
			if (!empty($config['ntpd']['gps']['speed'])) {
				$ntpmode += (int) $config['ntpd']['gps']['speed'];
			}
			if (!empty($config['ntpd']['gps']['subsec'])) {
				$ntpmode += 128;
			}
			if (!empty($config['ntpd']['gps']['processpgrmf'])) {
				$ntpmode += 256;
			}
			$ntpcfg .= (string) $ntpmode;
		} else {
			$ntpcfg .= '0';
		}
		$ntpcfg .= system_ntp_fixup_poll_value('minpoll', $config['ntpd']['gps']['gpsminpoll'], $ntp_poll_min_default_gps);
		$ntpcfg .= system_ntp_fixup_poll_value('maxpoll', $config['ntpd']['gps']['gpsmaxpoll'], $ntp_poll_max_default_gps);

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
	$auto_pool_suffix = "pool.ntp.org";
	$have_pools = false;
	$ntpcfg .= "\n\n# Upstream Servers\n";
	/* foreach through ntp servers and write out to ntpd.conf */
	foreach (explode(' ', $config['system']['timeservers']) as $ts) {
		if ((substr_compare($ts, $auto_pool_suffix, strlen($ts) - strlen($auto_pool_suffix), strlen($auto_pool_suffix)) === 0)
		    || substr_count($config['ntpd']['ispool'], $ts)) {
			$ntpcfg .= 'pool ';
			$have_pools = true;
		} else {
			$ntpcfg .= 'server ';
		}

		$ntpcfg .= "{$ts} iburst";

		$ntpcfg .= system_ntp_fixup_poll_value('minpoll', $config['ntpd']['ntpminpoll'], $ntp_poll_min_default);
		$ntpcfg .= system_ntp_fixup_poll_value('maxpoll', $config['ntpd']['ntpmaxpoll'], $ntp_poll_max_default);

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

	/* Default Access restrictions */
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

	/* Pools require "restrict source" and cannot contain "nopeer" and "noserve". */
	if ($have_pools) {
		$ntpcfg .= "\nrestrict source";
		if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
			$ntpcfg .= ' kod limited';
		}
		if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
			$ntpcfg .= ' nomodify';
		}
		if (!empty($config['ntpd']['noquery'])) {
			$ntpcfg .= ' noquery';
		}
		if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
			$ntpcfg .= ' notrap';
		}
	}

	/* Custom Access Restrictions */
	if (is_array($config['ntpd']['restrictions']) && is_array($config['ntpd']['restrictions']['row'])) {
		$networkacl = $config['ntpd']['restrictions']['row'];
		foreach ($networkacl as $acl) {
			$restrict = "";
			if (is_ipaddrv6($acl['acl_network'])) {
				$restrict .= "{$acl['acl_network']} mask " . gen_subnet_mask_v6($acl['mask']) . " ";
			} elseif (is_ipaddrv4($acl['acl_network'])) {
				$restrict .= "{$acl['acl_network']} mask " . gen_subnet_mask($acl['mask']) . " ";
			} else {
				continue;
			}
			if (!empty($acl['kod'])) {
				$restrict .= ' kod limited';
			}
			if (!empty($acl['nomodify'])) {
				$restrict .= ' nomodify';
			}
			if (!empty($acl['noquery'])) {
				$restrict .= ' noquery';
			}
			if (!empty($acl['nopeer'])) {
				$restrict .= ' nopeer';
			}
			if (!empty($acl['noserve'])) {
				$restrict .= ' noserve';
			}
			if (!empty($acl['notrap'])) {
				$restrict .= ' notrap';
			}
			if (!empty($restrict)) {
				$ntpcfg .= "\nrestrict {$restrict} ";
			}
		}
	}
	/* End Custom Access Restrictions */

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
		$finterfaces = array();
		$ntpcfg .= "interface ignore all\n";
		$ntpcfg .= "interface ignore wildcard\n";
		foreach ($interfaces as $interface) {
			$interface = get_real_interface($interface);
			if (!empty($interface)) {
				$finterfaces[] = $interface;
			}
		}
		foreach ($finterfaces as $interface) {
			$ntpcfg .= "interface listen {$interface}\n";
		}
	}

	/* open configuration for writing or bail */
	if (!@file_put_contents("{$g['varetc_path']}/ntpd.conf", $ntpcfg)) {
		log_error(sprintf(gettext("Could not open %s/ntpd.conf for writing"), $g['varetc_path']));
		return;
	}

	/* if /var/empty does not exist, create it */
	if (!is_dir("/var/empty")) {
		mkdir("/var/empty", 0555, true);
	}

	/* start ntpd, set time now and use /var/etc/ntpd.conf */
	mwexec("/usr/local/sbin/ntpd -g -c {$g['varetc_path']}/ntpd.conf -p {$g['varrun_path']}/ntpd.pid", false, true);

	// Note that we are starting up
	log_error("NTPD is starting up.");

	if (platform_booting()) {
		echo gettext("done.") . "\n";
	}

	return;
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

function system_reboot_sync($reroot=false) {
	global $g;

	if ($reroot) {
		$args = " -r ";
	}

	system_reboot_cleanup();

	mwexec("/etc/rc.reboot {$args} > /dev/null 2>&1");
}

function system_reboot_cleanup() {
	global $config, $g, $cpzone;

	mwexec("/usr/local/bin/beep.sh stop");
	require_once("captiveportal.inc");
	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone=>$cp) {
			if (!isset($cp['preservedb'])) {
				/* send Accounting-Stop packet for all clients, termination cause 'Admin-Reboot' */
				captiveportal_radius_stop_all(7); // Admin-Reboot
				unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
				captiveportal_free_dnrules();
			}
			/* Send Accounting-Off packet to the RADIUS server */
			captiveportal_send_server_accounting('off');
		}
		/* Remove the pipe database */
		unlink_if_exists("{$g['vardb_path']}/captiveportaldn.rules");
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

	// vm-bhyve expects dmesg.boot at the standard location
	@symlink("{$g['varlog_path']}/dmesg.boot", "{$g['varrun_path']}/dmesg.boot");

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
		case 'SG-2220':
			$binprefix = "RCC-DFF";
			break;
		case 'alix':
		case 'wrap':
		case 'FW7541':
		case 'APU':
		case 'RCC-VE':
		case 'RCC':
			$binprefix = $specplatform['name'];
			break;
		default:
			return 0;
	}

	$retval = mwexec("/usr/local/sbin/" . $binprefix . "resetbtn");

	if ($retval == 99) {
		/* user has pressed reset button for 2 seconds -
		   reset to factory defaults */
		echo <<<EOD

***********************************************************************
* Reset button pressed - resetting configuration to factory defaults. *
* All additional packages installed will be removed                   *
* The system will reboot after this completes.                        *
***********************************************************************


EOD;

		reset_factory_defaults();
		system_reboot_sync();
		exit(0);
	}

	return 0;
}

function system_get_serial() {
	$platform = system_identify_specific_platform();

	unset($output);
	if ($platform['name'] == 'Turbot Dual-E') {
		$if_info = pfSense_get_interface_addresses('igb0');
		if (!empty($if_info['hwaddr'])) {
			$serial = str_replace(":", "", $if_info['hwaddr']);
		}
	} else {
		foreach (array('system', 'planar', 'chassis') as $key) {
			unset($output);
			$_gb = exec("/bin/kenv -q smbios.{$key}.serial",
			    $output);
			if (!empty($output[0]) && $output[0] != "0123456789" &&
			    preg_match('/^[\w\d]{10,16}$/', $output[0]) === 1) {
				$serial = $output[0];
				break;
			}
		}
	}

	$vm_guest = get_single_sysctl('kern.vm_guest');

	if (strlen($serial) >= 10 && strlen($serial) <= 16 &&
	    $vm_guest == 'none') {
		return $serial;
	}

	return "";
}

function system_get_uniqueid() {
	global $g;

	$uniqueid_file="{$g['vardb_path']}/uniqueid";

	if (empty($g['uniqueid'])) {
		if (!file_exists($uniqueid_file)) {
			mwexec("/usr/sbin/gnid > {$g['vardb_path']}/uniqueid " .
			    "2>/dev/null");
		}
		if (file_exists($uniqueid_file)) {
			$g['uniqueid'] = @file_get_contents($uniqueid_file);
		}
	}

	return ($g['uniqueid'] ?: '');
}

/*
 * attempt to identify the specific platform (for embedded systems)
 * Returns an array with two elements:
 * name => platform string (e.g. 'wrap', 'alix' etc.)
 * descr => human-readable description (e.g. "PC Engines WRAP")
 */
function system_identify_specific_platform() {
	global $g;

	$hw_model = get_single_sysctl('hw.model');
	$hw_ncpu = get_single_sysctl('hw.ncpu');

	/* Try to guess from smbios strings */
	unset($product);
	unset($maker);
	$_gb = exec('/bin/kenv -q smbios.system.product 2>/dev/null', $product);
	$_gb = exec('/bin/kenv -q smbios.system.maker 2>/dev/null', $maker);
	switch ($product[0]) {
		case 'FW7541':
			return (array('name' => 'FW7541', 'descr' => 'Netgate FW7541'));
			break;
		case 'APU':
			return (array('name' => 'APU', 'descr' => 'Netgate APU'));
			break;
		case 'RCC-VE':
			$result = array();
			$result['name'] = 'RCC-VE';

			/* Detect specific models */
			if (!function_exists('does_interface_exist')) {
				require_once("interfaces.inc");
			}
			if (!does_interface_exist('igb4')) {
				$result['model'] = 'SG-2440';
			} elseif (strpos($hw_model, "C2558") !== false) {
				$result['model'] = 'SG-4860';
			} elseif (strpos($hw_model, "C2758") !== false) {
				$result['model'] = 'SG-8860';
			} else {
				$result['model'] = 'RCC-VE';
			}
			$result['descr'] = 'Netgate ' . $result['model'];
			return $result;
			break;
		case 'DFFv2':
			return (array('name' => 'SG-2220', 'descr' => 'Netgate SG-2220'));
			break;
		case 'RCC':
			return (array('name' => 'RCC', 'descr' => 'Netgate XG-2758'));
			break;
		case 'SG-5100':
			return (array('name' => 'SG-5100', 'descr' => 'Netgate SG-5100'));
			break;
		case 'Minnowboard Turbot D0 PLATFORM':
			$result = array();
			$result['name'] = 'Turbot Dual-E';
			/* Detect specific model */
			switch ($hw_ncpu) {
			case '4':
				$result['model'] = 'MBT-4220';
				break;
			case '2':
				$result['model'] = 'MBT-2220';
				break;
			default:
				$result['model'] = $result['name'];
				break;
			}
			$result['descr'] = 'Netgate ' . $result['model'];
			return $result;
			break;
		case 'SYS-5018A-FTN4':
		case 'A1SAi':
			if (strpos($hw_model, "C2558") !== false) {
				return (array(
				    'name' => 'C2558',
				    'descr' => 'Super Micro C2558'));
			} elseif (strpos($hw_model, "C2758") !== false) {
				return (array(
				    'name' => 'C2758',
				    'descr' => 'Super Micro C2758'));
			}
			break;
		case 'SYS-5018D-FN4T':
			if (strpos($hw_model, "D-1541") !== false) {
				return (array('name' => 'XG-1541', 'descr' => 'Super Micro XG-1541'));
			} else {
				return (array('name' => 'XG-1540', 'descr' => 'Super Micro XG-1540'));
			}
			break;
		case 'apu2':
		case 'APU2':
			return (array('name' => 'apu2', 'descr' => 'PC Engines APU2'));
			break;
		case 'VirtualBox':
			return (array('name' => 'VirtualBox', 'descr' => 'VirtualBox Virtual Machine'));
			break;
		case 'Virtual Machine':
			if ($maker[0] == "Microsoft Corporation") {
				return (array('name' => 'Hyper-V', 'descr' => 'Hyper-V Virtual Machine'));
			}
			break;
		case 'VMware Virtual Platform':
			if ($maker[0] == "VMware, Inc.") {
				return (array('name' => 'VMware', 'descr' => 'VMware Virtual Machine'));
			}
			break;
	}

	$_gb = exec('/bin/kenv -q smbios.planar.product 2>/dev/null',
	    $planar_product);
	if (isset($planar_product[0]) &&
	    $planar_product[0] == 'X10SDV-8C-TLN4F+') {
		return array('name' => 'XG-1537', 'descr' => 'Super Micro XG-1537');
	}

	if (strpos($hw_model, "PC Engines WRAP") !== false) {
		return array('name' => 'wrap', 'descr' => gettext('PC Engines WRAP'));
	}

	if (strpos($hw_model, "PC Engines ALIX") !== false) {
		return array('name' => 'alix', 'descr' => gettext('PC Engines ALIX'));
	}

	if (preg_match("/Soekris net45../", $hw_model, $matches)) {
		return array('name' => 'net45xx', 'descr' => $matches[0]);
	}

	if (preg_match("/Soekris net48../", $hw_model, $matches)) {
		return array('name' => 'net48xx', 'descr' => $matches[0]);
	}

	if (preg_match("/Soekris net55../", $hw_model, $matches)) {
		return array('name' => 'net55xx', 'descr' => $matches[0]);
	}

	unset($hw_model);

	$dmesg_boot = system_get_dmesg_boot();
	if (strpos($dmesg_boot, "PC Engines ALIX") !== false) {
		return array('name' => 'alix', 'descr' => gettext('PC Engines ALIX'));
	}
	unset($dmesg_boot);

	return array('name' => $g['platform'], 'descr' => $g['platform']);
}

function system_get_dmesg_boot() {
	global $g;

	return file_get_contents("{$g['varlog_path']}/dmesg.boot");
}

function system_get_arp_table($resolve_hostnames = false) {
	$params="-a";
	if (!$resolve_hostnames) {
		$params .= "n";
	}

	$arp_table = array();
	$_gb = exec("/usr/sbin/arp --libxo json {$params}", $rawdata, $rc);
	if ($rc == 0) {
		$arp_table = json_decode(implode(" ", $rawdata),
		    JSON_OBJECT_AS_ARRAY);
		if ($rc == 0) {
			$arp_table = $arp_table['arp']['arp-cache'];
		}
	}

	return $arp_table;
}

?>
