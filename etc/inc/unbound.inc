<?php
/* $Id$ */
/*
	unbound.inc
	part of the pfSense project (http://www.pfsense.com)
	Copyright (C) 2011	Warren Baker
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/unbound /usr/local/sbin/unbound-anchor
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/unbound-checkconf /usr/local/sbin/unbound-control
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/unbound-control-setup /usr/local/sbin/unbound-host 
*/


/* Handle Domain overrides and DNS Rebinding domains */
function unbound_add_domain_overrides($pvt=false) {
	global $config;

	$domains = $config['unbound']['domainoverrides'];

	$sorted_domains = msort($domains, "domain");
	$result = array();		
	foreach($sorted_domains as $domain) {
		$domain_key = current($domain);
		if(!isset($result[$domain_key])) {
			$result[$domain_key] = array();
		}
		$result[$domain_key][] = $domain['ip'];
	}

	// Domain overrides that have multiple entries need multiple stub-addr: added
	$domain_entries = "";
	foreach($result as $domain=>$ips) {
		if($pvt == true) {
			$domain_entries .= "private-domain: \"$domain\"\n";
			$domain_entries .= "domain-insecure: \"$domain\"\n";
		} else {
			$domain_entries .= "stub-zone:\n";
			$domain_entries .= "\tname: \"$domain\"\n";
			foreach($ips as $ip) {
				$domain_entries .= "\tstub-addr: $ip\n";
			}
			$domain_entries .= "\tstub-prime: no\n";
		}
	}
	if($pvt == true)
		return $domain_entries;
	else
		file_put_contents("{$g['unbound_chroot_path']}/etc/domainoverrides.conf", $domain_entries);
}


/* Optimize Unbound for environment */
function unbound_optimization() {
	global $config;

	$optimization_settings = array();
	
	/* Set the number of threads equal to number of CPUs.
	 * Use 1 to disable threading, if for some reason this sysctl fails.
	 */
	$numprocs = intval(trim(`/sbin/sysctl kern.smp.cpus | /usr/bin/cut -d" " -f2`));
	if($numprocs > 0)
		$optimization['number_threads'] = "num-threads: {$numprocs}";
	else
		$optimization['number_threads'] = "num-threads: 1";
	
	/* Slabs to help reduce lock contention. */
	if ($numprocs > 4) {
		$optimization['msg_cache_slabs'] = "msg-cache-slabs: {$numprocs}";
		$optimization['rrset_cache_slabs'] = "rrset-cache-slabs: {$numprocs}";
		$optimization['infra_cache_slabs'] = "infra-cache-slabs: {$numprocs}";
		$optimization['key_cache_slabs'] = "key-cache-slabs: {$numprocs}";
	} else {
		$optimization['msg_cache_slabs'] = "msg-cache-slabs: 4";
		$optimization['rrset_cache_slabs'] = "rrset-cache-slabs: 4";
		$optimization['infra_cache_slabs'] = "infra-cache-slabs: 4";
		$optimization['key_cache_slabs'] = "key-cache-slabs: 4";
	}
	
	/* Memory usage default of 4MB */
	$optimization['msg_cache_size'] = "msg-cache-size: 4m";
	$optimization['rrset_cache_size'] = "rrset-cache-size: 8m";

	/* More outgoing connections per thread otherwise assign a default of 4096 for a single thread */
	if($numprocs > 0) {
		$or = (1024/$numprocs) - 50;
		$optimization['outgoing_range'] = "outgoing-range: {$or}";
	} else {
		$optimization['outgoing_range'] = "outgoing-range: {4096}";
	}

	/* Larger socket buffer for busy servers
	 * Check that it is set to 4MB (by default the OS has it configured to 4MB)
	 */
	foreach ($config['sysctl']['item'] as $tunable) {
		if ($tunable['tunable'] == 'kern.ipc.maxsockbuf') {
			$so = floor(($tunable['value']/1024/1024)-1);
			// Check to ensure that the number is not a negative
			if ($so > 0)
				$optimization['so_rcvbuf'] = "so-rcvbuf: {$so}m";
			else
				unset($optimization['so_rcvbuf']);
		}
	}
	/* Safety check in case kern.ipc.maxsockbuf is not available. */
	if(!isset($optimization['so_rcvbuf']))
		$optimization['so_rcvbuf'] = "#so-rcvbuf: 4m";

	return $optimization;
}

/* Fetch root name servers hints file */
function unbound_fetch_root_hints() {
	global $g;

	$hints = "{$g['unbound_chroot_path']}/etc/root.hints";
	if (@filesize($hints) == 0 ) {
		$returnvar = mwexec("/usr/bin/dig +tcp +nocmd +answer @`/usr/bin/dig +nocmd +noall +answer +short . NS | /usr/bin/head -1` . NS > {$hints}");

		if ($returnvar != 0) {
			mwexec("/bin/rm -f {$hints}");
			return false;
		} else
			return true;
	} else
		return true;
}

/* Configure initial anchor to support DNSSEC */
function unbound_anchor_setup() {
	global $g;

	$conf = <<<EOD
. IN DS 19036 8 2 49AAC11D7B6F6446702E54A1607371607A1A41855200FD2CE1CDDE32F24E8FB5
EOD;

	file_put_contents("{$g['unbound_chroot_path']}/etc/root-trust-anchor", $conf);
	@chown("{$g['unbound_chroot_path']}/etc/root-trust-anchor", "unbound");
	@chgrp("{$g['unbound_chroot_path']}/etc/root-trust-anchor", "wheel");
	@chmod("{$g['unbound_chroot_path']}/etc/root-trust-anchor", 0600);
	mwexec("/usr/local/sbin/unbound-anchor -a {$g['unbound_chroot_path']}/etc/root-trust-anchor", true);
}

/* Setup Unbound Remote Control SSL keys */
function unbound_keys_setup() {
	global $g;

	if (!file_exists("{$g['unbound_chroot_path']}/unbound_control.key")) {
		mwexec("/usr/local/sbin/unbound-control-setup -d {$g['unbound_chroot_path']}/etc");
		@chown("{$g['unbound_chroot_path']}/etc/unbound_*", "unbound");
		@chgrp("{$g['unbound_chroot_path']}/etc/unbound_*", "wheel");
	}
}

/* Generation of Unbound statistics */
function unbound_statistics() {
	global $config;

	/* XXX To do - add RRD graphs */
	$stats = <<<EOF
# Unbound Statistics
statistics-interval: {$config['unbound']['stats_interval']}
extended-statistics: yes
statistics-cumulative: yes

EOF;

	return $stats;
}

function unbound_resync_config() {
	global $config,$g;

	$unboundcfg = $config['config']['unbound'];

	/* Setup optimization */
	$optimization = unbound_optimization();

	/* Setup DNSSEC support */
	if($unbound_config['dnssec_status'] == "on") {
		$module_config = "validator iterator";
		$anchor_file = "auto-trust-anchor-file: /etc/root-trust-anchor";
	} else
		$module_config = "iterator";

	/* Setup DNS Rebinding */
	if(!isset($config['system']['webgui']['nodnsrebindcheck'])) {
		// Private-addresses for DNS Rebinding
		$private_addr = <<<EOF
# For DNS Rebinding prevention
private-address: 10.0.0.0/8
private-address: 172.16.0.0/12
private-address: 192.168.0.0/16
private-address: 192.254.0.0/16
private-address: fd00::/8
private-address: fe80::/10
EOF;
	}

	/* Allow DNS Rebind for forwarded domains */
	if (isset($config['unbound']['domainoverrides']) && is_array($config['unbound']['domainoverrides'])) {
		if(!isset($config['system']['webgui']['nodnsrebindcheck'])) {
			$private_domains = "# Set private domains in case authoritative name server returns a Private IP address";
			$private_domains .= unbound_add_domain_overrides(true);
		}
	}

	/* Configure static Host entries */
	$host_entries = unbound_add_host_entries();

	/* Configure Domain Overrides */
	$domain_overrides = unbound_add_domain_overrides();

	/* Configure Unbound statistics */
	$statistics = unbound_statistics();

	/* Add custom Unbound options */
	if ($config['unbound']['custom_options']) {
		$custom_option = "# Unbound custom option";
		foreach (preg_split('/\s+/', $config['dns']['custom_options']) as $ent)
			$custom_option .= $ent."\n";
	}

	$unboundconf = <<<EOD
##########################
# Unbound Configuration
##########################

##
# Server configuration
##
server:
chroot: {$g['unbound_chroot_path']}
username: "unbound"
directory: "{$g['unbound_chroot_path']}/etc"
root-hints: "root.hints"
pidfile: "/var/run/unbound.pid"
use-syslog: yes
port: 53
verbosity: {$unboundcfg['loglevel']}
harden-referral-path: no
do-ip4: yes
do-ip6: yes
do-udp: yes
do-tcp: yes
do-daemonize: yes
module-config: "{$module_config}"
unwanted-reply-threshold: 0
num-queries-per-thread: 1024
jostle-timeout: 200
infra-host-ttl: 900
infra-lame-ttl: 900
infra-cache-numhosts: 10000
outgoing-num-tcp: 10
incoming-num-tcp: 10
edns-buffer-size: 4096
cache-max-ttl: {$unboundcfg['cache_max_ttl']}
cache-min-ttl: {$unboundcfg['cache_min_ttl']}
harden-dnssec-stripped: yes
{$optimization['number_threads']}
{$optimization['msg_cache_slabs']}
{$optimization['rrset_cache_slabs']}
{$optimization['infra_cache_slabs']}
{$optimization['key_cache_slabs']}
{$optimization['msg_cache_size']}
{$optimization['rrset_cache_size']}
{$optimization['outgoing_range']}
{$optimization['so_rcvbuf']}
{$anchor_file}
prefetch: {$unboundcfg['prefetch']}
prefetch-key: {$unboundcfg['prefetch_key']}
# Statistics
{$statistics}
# Interface IP(s) to bind to
interface: 0.0.0.0
interface: ::0

# DNS Rebinding
{$private_addr}
{$private_domains}

# Static host entries
include: {$g['unbound_chroot_path']}/etc/host_entries.conf

# Domain overrides
include: {$g['unbound_chroot_path']}/etc/domainoverrides.conf

{$custom_options}

###
# Remote Control Config
###
include: {$g['unbound_chroot_path']}/etc/remotecontrol.conf

EOD;

	file_put_contents("{$g['unbound_chroot_path']}/etc/unbound.conf", $unboundconf);

}

function unbound_remote_control_setup() {
	global $g;

	if(!file_exists("{$g['unbound_chroot_path']}/etc/remotecontrol.conf")) {
		$remotcfg = <<<EOF
remote-control:
control-enable: yes
control-interface: 127.0.0.1
control-port: 953
server-key-file: "/etc/unbound_server.key"
server-cert-file: "/etc/unbound_server.pem"
control-key-file: "/etc/unbound_control.key"
control-cert-file: "/etc/unbound_control.pem"
EOF;

		file_put_contents("{$g['unbound_chroot_path']}/etc/remotecontrol.conf", $remotcfg);
	}
}

function unbound_add_host_entries() {
	global $config, $g;

	/* XXX: break this out into a separate config file and make use of include */
	$syscfg = $config['system'];
	$dnscfg = $config['unbound'];

	$dns_entries = "local-zone: \"{$syscfg['domain']}\" transparent\n";
	// IPv4 entries
	$dns_entries .= "local-data-ptr: \"127.0.0.1 localhost\"\n";
	$dns_entries .= "local-data: \"localhost A 127.0.0.1\"\n";
	$dns_entries .= "local-data: \"localhost.{$syscfg['domain']} A 127.0.0.1\"\n";
	// IPv6 entries
	$dns_entries .= "local-data-ptr: \"::1 localhost\"\n";
	$dns_entries .= "local-data: \"localhost AAAA ::1\"\n";
	$dns_entries .= "local-data: \"localhost.{$syscfg['domain']} AAAA ::1\"\n";

	/*if ($config['interfaces']['lan']) {
		$cfgip = get_interface_ip("lan");
		if (is_ipaddr($cfgip)) {
			$unbound_entries .= "local-data-ptr: \"{$cfgip} {$syscfg['hostname']}.{$syscfg['domain']}\"\n";
			$unbound_entries .= "local-data: \"{$syscfg['hostname']}.{$syscfg['domain']} A {$cfgip}\"\n";
			$unbound_entries .= "local-data: \"{$syscfg['hostname']} A {$cfgip}\"\n";
		}
	} else {
		$sysiflist = get_configured_interface_list();
		foreach ($sysiflist as $sysif) {
			if (!interface_has_gateway($sysif)) {
				$cfgip = get_interface_ip($sysif);
				if (is_ipaddr($cfgip)) {
					$unbound_entries .= "local-data-ptr: \"{$cfgip} {$syscfg['hostname']}.{$syscfg['domain']}\"\n";
					$unbound_entries .= "local-data: \"{$syscfg['hostname']}.{$syscfg['domain']} A {$cfgip}\"\n";
					$unbound_entries .= "local-data: \"{$syscfg['hostname']} A {$cfgip}\"\n";
					break;
				}
			}
		}
	}*/

	/* Static Host entries */
	if (isset($dnscfg['hosts'])) {
		$hosts = $dnscfg['hosts'];
		$host_entries = "";
		$added_item = array();
		foreach ($hosts as $host) {
			$current_host = $host['host'];
			if ($host['host'] != "")
				$host['host'] = $host['host'].".";
			if(!$added_item[$current_host]) {
				$host_entries .= "local-data-ptr: \"{$host['ip']} {$host['host']}{$host['domain']}\"\n";
				if (is_ipaddrv6($host['ip']))
					$host_entries .= "local-data: \"{$host['host']}{$host['domain']} IN AAAA {$host['ip']}\"\n";
				else
					$host_entries .= "local-data: \"{$host['host']}{$host['domain']} IN A {$host['ip']}\"\n";
				if (!empty($host['descr']) && $dnscfg['txtsupport'] == 'on')
					$host_entries .= "local-data: '{$host['host']}{$host['domain']} TXT \"".addslashes($host['descr'])."\"'\n";

				// Do not add duplicate entries
				$added_item[$current_host] = true;
			}
		}
		$unbound_entries .= $host_entries;
	}
	// Static DHCP entries
	$host_entries = "";
	if (isset($dnscfg['regdhcpstatic']) && is_array($config['dhcpd'])) {
		foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf)
			if(is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable']))
				foreach ($dhcpifconf['staticmap'] as $host)
					if ($host['ipaddr'] && $host['hostname']) {
						$host_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['hostname']}.{$syscfg['domain']}\"\n";
						$host_entries .= "local-data: \"{$host['hostname']}.{$syscfg['domain']} IN A {$host['ipaddr']}\"\n";
						if (!empty($host['descr']) && $unboundcfg['txtsupport'] == 'on')
							$host_entries .= "local-data: '{$host['hostname']}.{$syscfg['domain']} TXT \"".addslashes($host['descr'])."\"'\n";
					}
		$unbound_entries .= $host_entries;
    }

	// Handle DHCPLeases added host entries
	$dhcplcfg = read_hosts();
	$host_entries = "";
	if(is_array($dhcplcfg)) {
		foreach($dhcplcfg as $key=>$host) {
			$host_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['fqdn']}\"\n";
			$host_entries .= "local-data: \"{$host['fqdn']} IN A {$host['ipaddr']}\"\n";
			if (!empty($host['name'])) {
				$host_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['name']}\"\n";
				$host_entries .= "local-data: \"{$host['name']} IN A {$host['ipaddr']}\"\n";
			}
		}
		$unbound_entries .= $host_entries;
	}

	/* Write out entries */
	file_put_contents("{$g['unbound_chroot_path']}/etc/host_entries.conf", $unbound_entries);
}

/* Read /etc/hosts */
function read_hosts() {

	/* Open /etc/hosts and extract the only dhcpleases info
	 * XXX - to convert to an unbound C library which reads /etc/hosts automatically
	 */
	$etc_hosts = array();
	foreach (file('/etc/hosts') as $line) {
		$d = preg_split('/\s/', $line, -1, PREG_SPLIT_NO_EMPTY);
		if (empty($d) || substr(reset($d), 0, 1) == "#")
			continue;
		if ($d[3] == "#") {
			$ip = array_shift($d);
			$fqdn = array_shift($d);
			$name = array_shift($d);
			if ($fqdn != "empty") {
				if ($name != "empty")
					array_push($etc_hosts, array(ipaddr => "$ip", fqdn => "$fqdn", name => "$name"));
				else
					array_push($etc_hosts, array(ipaddr => "$ip", fqdn => "$fqdn"));
			}
		}
	}
	return $etc_hosts;
}

function unbound_setup() {
	global $config, $g;

	unbound_anchor_setup();
	unbound_remote_control_setup();
	unbound_keys_setup();
	unbound_resync_config();

}

?>