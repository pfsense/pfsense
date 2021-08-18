<?php
/*
 * unbound.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Warren Baker <warren@percol8.co.za>
 * Copyright (c) 2015-2016 Electric Sheep Fencing
 * Copyright (c) 2015-2021 Rubicon Communications, LLC (Netgate)
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

/* include all configuration functions */
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("interfaces.inc");
require_once("util.inc");

function create_unbound_chroot_path($cfgsubdir = "") {
	global $config, $g;

	// Configure chroot
	if (!is_dir($g['unbound_chroot_path'])) {
		mkdir($g['unbound_chroot_path']);
		chown($g['unbound_chroot_path'], "unbound");
		chgrp($g['unbound_chroot_path'], "unbound");
	}

	if ($cfgsubdir != "") {
		$cfgdir = $g['unbound_chroot_path'] . $cfgsubdir;
		if (!is_dir($cfgdir)) {
			mkdir($cfgdir);
			chown($cfgdir, "unbound");
			chgrp($cfgdir, "unbound");
		}
	}
}

/* Optimize Unbound for environment */
function unbound_optimization() {
	global $config;

	$optimization_settings = array();

	/*
	 * Set the number of threads equal to number of CPUs.
	 * Use 1 to disable threading, if for some reason this sysctl fails.
	 */
	$numprocs = intval(get_single_sysctl('kern.smp.cpus'));
	if ($numprocs > 1) {
		$optimization['number_threads'] = "num-threads: {$numprocs}";
		$optimize_num = pow(2, floor(log($numprocs, 2)));
	} else {
		$optimization['number_threads'] = "num-threads: 1";
		$optimize_num = 4;
	}

	// Slabs to help reduce lock contention.
	$optimization['msg_cache_slabs'] = "msg-cache-slabs: {$optimize_num}";
	$optimization['rrset_cache_slabs'] = "rrset-cache-slabs: {$optimize_num}";
	$optimization['infra_cache_slabs'] = "infra-cache-slabs: {$optimize_num}";
	$optimization['key_cache_slabs'] = "key-cache-slabs: {$optimize_num}";

	/*
	 * Larger socket buffer for busy servers
	 * Check that it is set to 4MB (by default the OS has it configured to 4MB)
	 */
	if (is_array($config['sysctl']) && is_array($config['sysctl']['item'])) {
		foreach ($config['sysctl']['item'] as $tunable) {
			if ($tunable['tunable'] == 'kern.ipc.maxsockbuf') {
				$so = floor((intval($tunable['value'])/1024/1024)-4);
				// Check to ensure that the number is not a negative
				if ($so >= 4) {
					// Limit to 32MB, users might set maxsockbuf very high for other reasons.
					// We do not want unbound to fail because of that.
					$so = min($so, 32);
					$optimization['so_rcvbuf'] = "so-rcvbuf: {$so}m";
				} else {
					unset($optimization['so_rcvbuf']);
				}
			}
		}
	}
	// Safety check in case kern.ipc.maxsockbuf is not available.
	if (!isset($optimization['so_rcvbuf'])) {
		$optimization['so_rcvbuf'] = "#so-rcvbuf: 4m";
	}

	return $optimization;

}

function test_unbound_config($unboundcfg, &$output) {
	global $g;

	$cfgsubdir = "/test";
	$cfgdir = "{$g['unbound_chroot_path']}{$cfgsubdir}";
	rmdir_recursive($cfgdir);

	// Copy the Python files to the test folder
	if (isset($unboundcfg['python']) &&
	    !empty($unboundcfg['python_script'])) {
		$python_files = glob("{$g['unbound_chroot_path']}/{$unboundcfg['python_script']}.*");
		if (is_array($python_files)) {
			create_unbound_chroot_path($cfgsubdir);
			foreach ($python_files as $file) {
				$file = pathinfo($file, PATHINFO_BASENAME);
				@copy("{$g['unbound_chroot_path']}/{$file}", "{$cfgdir}/{$file}");
			}
		}
	}

	unbound_generate_config($unboundcfg, $cfgsubdir);
	unbound_remote_control_setup($cfgsubdir);
	do_as_unbound_user("unbound-anchor", $cfgsubdir);

	$rv = 0;
	exec("/usr/local/sbin/unbound-checkconf {$cfgdir}/unbound.conf 2>&1",
	    $output, $rv);

	if ($rv == 0) {
		rmdir_recursive($cfgdir);
	}

	return $rv;
}


function unbound_generate_config($unboundcfg = NULL, $cfgsubdir = "") {
	global $g;

	$unboundcfgtxt = unbound_generate_config_text($unboundcfg, $cfgsubdir);

	// Configure static Host entries
	unbound_add_host_entries($cfgsubdir);

	// Configure Domain Overrides
	unbound_add_domain_overrides("", $cfgsubdir);

	// Configure Unbound access-lists
	unbound_acls_config($cfgsubdir);

	create_unbound_chroot_path($cfgsubdir);
	file_put_contents("{$g['unbound_chroot_path']}{$cfgsubdir}/unbound.conf", $unboundcfgtxt);
}

function unbound_get_python_scriptname($unboundcfg, $cfgsubdir = '') {
	global $g;
	if (!isset($unboundcfg['python']) ||
	    empty($unboundcfg['python_script'])) {
		/* Python is not enabled, or no script defined. */
		return "";
	}

	$python_path = $g['unbound_chroot_path'];
	if (!empty($cfgsubdir)) {
		$python_path .= "{$cfgsubdir}";
	}

	/* Full path to the selected script file */
	$python_script_file = "{$python_path}/{$unboundcfg['python_script']}.py";

	if (file_exists($python_script_file)) {
		/* If using a subdir (e.g. testing) use the full path, otherwise
		 * only use the base filename. */
		return empty($cfgsubdir) ? basename($python_script_file) : $python_script_file;
	} else {
		return '';
	}
}

function unbound_generate_config_text($unboundcfg = NULL, $cfgsubdir = "") {

	global $config, $g;
	if (is_null($unboundcfg)) {
		$unboundcfg = $config['unbound'];
	}

	if (platform_booting()) {
		unlink_if_exists("{$g['unbound_chroot_path']}{$cfgsubdir}/openvpn.*.conf");
	}

	// Setup optimization
	$optimization = unbound_optimization();

	$module_config = '';

	// Setup Python module (pre validator)
	if (!empty(unbound_get_python_scriptname($unboundcfg, $cfgsubdir)) &&
	    $unboundcfg['python_order'] == 'pre_validator') {
		$module_config .= 'python ';
	}

	// Setup DNS64 support
	if (isset($unboundcfg['dns64'])) {
		$module_config .= 'dns64 ';
		$dns64_conf = 'dns64-prefix: ';
		if (is_subnetv6($unboundcfg['dns64prefix'] . '/' . $unboundcfg['dns64netbits'])) {
			$dns64_conf .= $unboundcfg['dns64prefix'] . '/' . $unboundcfg['dns64netbits'];
		} else {
			$dns64_conf .= '64:ff9b::/96';
		}
	}

	// Setup DNSSEC support
	if (isset($unboundcfg['dnssec'])) {
		$module_config .= 'validator ';
		$anchor_file = "auto-trust-anchor-file: {$g['unbound_chroot_path']}{$cfgsubdir}/root.key";
	}

	// Setup Python module (post validator)
	if (!empty(unbound_get_python_scriptname($unboundcfg, $cfgsubdir)) &&
	    $unboundcfg['python_order'] == 'post_validator') {
		$module_config .= 'python ';
	}

	$module_config .= 'iterator';

	// Setup DNS Rebinding
	if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
		// Private-addresses for DNS Rebinding
		$private_addr = <<<EOF
# For DNS Rebinding prevention
private-address: 127.0.0.0/8
private-address: 10.0.0.0/8
private-address: ::ffff:a00:0/104
private-address: 172.16.0.0/12
private-address: ::ffff:ac10:0/108
private-address: 169.254.0.0/16
private-address: ::ffff:a9fe:0/112
private-address: 192.168.0.0/16
private-address: ::ffff:c0a8:0/112
private-address: fd00::/8
private-address: fe80::/10
EOF;
	}

	// Determine interfaces where unbound will bind
	$tlsport = is_numeric($unboundcfg['tlsport']) ? $unboundcfg['tlsport'] : "853";
	$bindintcfg = "";
	$bindints = array();
	$active_interfaces = explode(",", $unboundcfg['active_interface']);
	if (empty($unboundcfg['active_interface']) || in_array("all", $active_interfaces, true)) {
		$bindints[] = "0.0.0.0";
		$bindints[] = "::0";
		$bindintcfg .= "interface-automatic: " . (isset($unboundcfg['enablessl']) ? "no" : "yes") . "\n";
	} else {
		foreach ($active_interfaces as $ubif) {
			/* Do not bind to disabled/nocarrier interfaces,
			 * see https://redmine.pfsense.org/issues/11087 */
			$ifinfo = get_interface_info($ubif);
			if ($ifinfo && (($ifinfo['status'] != 'up') || !$ifinfo['enable'])) {
				continue;
			}
			if (is_ipaddr($ubif)) {
				$bindints[] = $ubif;
			} else {
				$intip = get_interface_ip($ubif);
				if (is_ipaddrv4($intip)) {
					$bindints[] = $intip;
				}
				$intip = get_interface_ipv6($ubif);
				if (is_ipaddrv6($intip)) {
					$bindints[] = $intip;
				}
			}
		}
	}
	foreach ($bindints as $bindint) {
		$bindintcfg .= "interface: {$bindint}\n";
		if (isset($unboundcfg['enablessl'])) {
			$bindintcfg .= "interface: {$bindint}@{$tlsport}\n";
		}
	}

	// TLS Configuration
	$tlsconfig = "tls-cert-bundle: \"/etc/ssl/cert.pem\"\n";

	if (isset($unboundcfg['enablessl'])) {
		$tlscert_path = "{$g['unbound_chroot_path']}/sslcert.crt";
		$tlskey_path = "{$g['unbound_chroot_path']}/sslcert.key";

		// Enable SSL/TLS on the chosen or default port
		$tlsconfig .= "tls-port: {$tlsport}\n";

		// Lookup CA and Server Cert
		$cert = lookup_cert($unboundcfg['sslcertref']);
		$ca = ca_chain($cert);
		$cert_chain = base64_decode($cert['crt']);
		if (!empty($ca)) {
			$cert_chain .= "\n" . $ca;
		}

		// Write CA and Server Cert
		file_put_contents($tlscert_path, $cert_chain);
		chmod($tlscert_path, 0644);
		file_put_contents($tlskey_path, base64_decode($cert['prv']));
		chmod($tlskey_path, 0600);

		// Add config for CA and Server Cert
		$tlsconfig .= "tls-service-pem: \"{$tlscert_path}\"\n";
		$tlsconfig .= "tls-service-key: \"{$tlskey_path}\"\n";
	}

	// Determine interfaces to run on
	$outgoingints = "";
	if (!empty($unboundcfg['outgoing_interface'])) {
		$outgoingints = "# Outgoing interfaces to be used\n";
		$outgoing_interfaces = explode(",", $unboundcfg['outgoing_interface']);
		foreach ($outgoing_interfaces as $outif) {
			$ifinfo = get_interface_info($outif);
			if ($ifinfo && (($ifinfo['status'] != 'up') || !$ifinfo['enable'])) {
				continue;
			}
			$outip = get_interface_ip($outif);
			if (is_ipaddr($outip)) {
				$outgoingints .= "outgoing-interface: $outip\n";
			}
			$outip = get_interface_ipv6($outif);
			if (is_ipaddrv6($outip)) {
				$outgoingints .= "outgoing-interface: $outip\n";
			}
		}
	}

	// Allow DNS Rebind for forwarded domains
	if (isset($unboundcfg['domainoverrides']) && is_array($unboundcfg['domainoverrides'])) {
		if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
			$private_domains = "# Set private domains in case authoritative name server returns a Private IP address\n";
			$private_domains .= unbound_add_domain_overrides("private");
		}
		$reverse_zones .= unbound_add_domain_overrides("reverse");
	}

	// Configure Unbound statistics
	$statistics = unbound_statistics();

	// Add custom Unbound options
	if ($unboundcfg['custom_options']) {
		$custom_options_source = explode("\n", base64_decode($unboundcfg['custom_options']));
		$custom_options = "# Unbound custom options\n";
		foreach ($custom_options_source as $ent) {
			$custom_options .= $ent."\n";
		}
	}

	// Server configuration variables
	$port = (is_port($unboundcfg['port'])) ? $unboundcfg['port'] : "53";
	$hide_identity = isset($unboundcfg['hideidentity']) ? "yes" : "no";
	$hide_version = isset($unboundcfg['hideversion']) ? "yes" : "no";
	$ipv6_allow = isset($config['system']['ipv6allow']) ? "yes" : "no";
	$harden_dnssec_stripped = isset($unboundcfg['dnssecstripped']) ? "yes" : "no";
	$prefetch = isset($unboundcfg['prefetch']) ? "yes" : "no";
	$prefetch_key = isset($unboundcfg['prefetchkey']) ? "yes" : "no";
	$dns_record_cache = isset($unboundcfg['dnsrecordcache']) ? "yes" : "no";
	$aggressivensec = isset($unboundcfg['aggressivensec']) ? "yes" : "no";
	$outgoing_num_tcp = isset($unboundcfg['outgoing_num_tcp']) ? $unboundcfg['outgoing_num_tcp'] : "10";
	$incoming_num_tcp = isset($unboundcfg['incoming_num_tcp']) ? $unboundcfg['incoming_num_tcp'] : "10";
	if (empty($unboundcfg['edns_buffer_size']) || ($unboundcfg['edns_buffer_size'] == 'auto')) {
		$edns_buffer_size = unbound_auto_ednsbufsize();
	} else {
		$edns_buffer_size = $unboundcfg['edns_buffer_size'];
	}
	$num_queries_per_thread = (!empty($unboundcfg['num_queries_per_thread'])) ? $unboundcfg['num_queries_per_thread'] : "4096";
	$jostle_timeout = (!empty($unboundcfg['jostle_timeout'])) ? $unboundcfg['jostle_timeout'] : "200";
	$cache_max_ttl = (!empty($unboundcfg['cache_max_ttl'])) ? $unboundcfg['cache_max_ttl'] : "86400";
	$cache_min_ttl = (!empty($unboundcfg['cache_min_ttl'])) ? $unboundcfg['cache_min_ttl'] : "0";
	$infra_host_ttl = (!empty($unboundcfg['infra_host_ttl'])) ? $unboundcfg['infra_host_ttl'] : "900";
	$infra_cache_numhosts = (!empty($unboundcfg['infra_cache_numhosts'])) ? $unboundcfg['infra_cache_numhosts'] : "10000";
	$unwanted_reply_threshold = (!empty($unboundcfg['unwanted_reply_threshold'])) ? $unboundcfg['unwanted_reply_threshold'] : "0";
	if ($unwanted_reply_threshold == "disabled") {
		$unwanted_reply_threshold = "0";
	}
	$msg_cache_size = (!empty($unboundcfg['msgcachesize'])) ? $unboundcfg['msgcachesize'] : "4";
	$verbosity = isset($unboundcfg['log_verbosity']) ? $unboundcfg['log_verbosity'] : 1;
	$use_caps = isset($unboundcfg['use_caps']) ? "yes" : "no";

	if (isset($unboundcfg['regovpnclients'])) {
		$openvpn_clients_conf .=<<<EOD
# OpenVPN client entries
include: {$g['unbound_chroot_path']}{$cfgsubdir}/openvpn.*.conf
EOD;
	} else {
		$openvpn_clients_conf = '';
		unlink_if_exists("{$g['unbound_chroot_path']}{$cfgsubdir}/openvpn.*.conf");
	}

	// Set up forwarding if it is configured
	if (isset($unboundcfg['forwarding'])) {
		$dnsservers = get_dns_nameservers(false, true);
		if (!empty($dnsservers)) {
			$forward_conf .=<<<EOD
# Forwarding
forward-zone:
	name: "."

EOD;
			if (isset($unboundcfg['forward_tls_upstream'])) {
				$forward_conf .= "\tforward-tls-upstream: yes\n";
			}

			/* Build DNS server hostname list. See https://redmine.pfsense.org/issues/8602 */
			$dns_hostnames = array();
			$dnshost_counter = 1;
			while (isset($config["system"]["dns{$dnshost_counter}host"])) {
				$pconfig_dnshost_counter = $dnshost_counter - 1;
				if (!empty($config["system"]["dns{$dnshost_counter}host"]) &&
				    isset($config["system"]["dnsserver"][$pconfig_dnshost_counter]))
				$dns_hostnames[$config["system"]["dnsserver"][$pconfig_dnshost_counter]] = $config["system"]["dns{$dnshost_counter}host"];
				$dnshost_counter++;
			}

			foreach ($dnsservers as $dnsserver) {
				$fwdport = "";
				$fwdhost = "";
				if (is_ipaddr($dnsserver) && !ip_in_subnet($dnsserver, "127.0.0.0/8")) {
					if (isset($unboundcfg['forward_tls_upstream'])) {
						$fwdport = "@853";
						if (array_key_exists($dnsserver, $dns_hostnames)) {
							$fwdhost = "#{$dns_hostnames[$dnsserver]}";
						}
					}
					$forward_conf .= "\tforward-addr: {$dnsserver}{$fwdport}{$fwdhost}\n";
				}
			}
		}
	} else {
		$forward_conf = "";
	}

	// Size of the RRset cache == 2 * msg-cache-size per Unbound's recommendations
	$rrset_cache_size = $msg_cache_size * 2;

	/* QNAME Minimization. https://redmine.pfsense.org/issues/8028
	 * Unbound uses the British style in the option name so the internal option
	 * name follows that, but the user-visible descriptions follow US English.
	 */
	$qname_min = "";
	if (isset($unboundcfg['qname-minimisation'])) {
		$qname_min = "qname-minimisation: yes\n";
		if (isset($unboundcfg['qname-minimisation-strict'])) {
			$qname_min .= "qname-minimisation-strict: yes\n";
		}
	}

	$python_module = '';
	$python_script_file = unbound_get_python_scriptname($unboundcfg, $cfgsubdir);
	if (!empty($python_script_file)) {
		$python_module = "\n# Python Module\npython:\npython-script: {$python_script_file}";
	}

	$unboundconf = <<<EOD
##########################
# Unbound Configuration
##########################

##
# Server configuration
##
server:
{$reverse_zones}
chroot: {$g['unbound_chroot_path']}
username: "unbound"
directory: "{$g['unbound_chroot_path']}"
pidfile: "/var/run/unbound.pid"
use-syslog: yes
port: {$port}
verbosity: {$verbosity}
hide-identity: {$hide_identity}
hide-version: {$hide_version}
harden-glue: yes
do-ip4: yes
do-ip6: {$ipv6_allow}
do-udp: yes
do-tcp: yes
do-daemonize: yes
module-config: "{$module_config}"
unwanted-reply-threshold: {$unwanted_reply_threshold}
num-queries-per-thread: {$num_queries_per_thread}
jostle-timeout: {$jostle_timeout}
infra-host-ttl: {$infra_host_ttl}
infra-cache-numhosts: {$infra_cache_numhosts}
outgoing-num-tcp: {$outgoing_num_tcp}
incoming-num-tcp: {$incoming_num_tcp}
edns-buffer-size: {$edns_buffer_size}
cache-max-ttl: {$cache_max_ttl}
cache-min-ttl: {$cache_min_ttl}
harden-dnssec-stripped: {$harden_dnssec_stripped}
msg-cache-size: {$msg_cache_size}m
rrset-cache-size: {$rrset_cache_size}m
{$qname_min}
{$optimization['number_threads']}
{$optimization['msg_cache_slabs']}
{$optimization['rrset_cache_slabs']}
{$optimization['infra_cache_slabs']}
{$optimization['key_cache_slabs']}
outgoing-range: 4096
{$optimization['so_rcvbuf']}
{$anchor_file}
prefetch: {$prefetch}
prefetch-key: {$prefetch_key}
use-caps-for-id: {$use_caps}
serve-expired: {$dns_record_cache}
aggressive-nsec: {$aggressivensec}
# Statistics
{$statistics}
# TLS Configuration
{$tlsconfig}
# Interface IP(s) to bind to
{$bindintcfg}
{$outgoingints}
# DNS Rebinding
{$private_addr}
{$private_domains}
{$dns64_conf}

# Access lists
include: {$g['unbound_chroot_path']}{$cfgsubdir}/access_lists.conf

# Static host entries
include: {$g['unbound_chroot_path']}{$cfgsubdir}/host_entries.conf

# dhcp lease entries
include: {$g['unbound_chroot_path']}{$cfgsubdir}/dhcpleases_entries.conf

{$openvpn_clients_conf}

# Domain overrides
include: {$g['unbound_chroot_path']}{$cfgsubdir}/domainoverrides.conf
{$forward_conf}

{$custom_options}

###
# Remote Control Config
###
include: {$g['unbound_chroot_path']}{$cfgsubdir}/remotecontrol.conf
{$python_module}

EOD;

	return $unboundconf;
}

function unbound_remote_control_setup($cfgsubdir = "") {
	global $g;

	if (!file_exists("{$g['unbound_chroot_path']}{$cfgsubdir}/remotecontrol.conf") ||
	    (filesize("{$g['unbound_chroot_path']}{$cfgsubdir}/remotecontrol.conf") == 0) ||
	    !file_exists("{$g['unbound_chroot_path']}{$cfgsubdir}/unbound_control.key")) {
		$remotcfg = <<<EOF
remote-control:
	control-enable: yes
	control-interface: 127.0.0.1
	control-port: 953
	server-key-file: "{$g['unbound_chroot_path']}{$cfgsubdir}/unbound_server.key"
	server-cert-file: "{$g['unbound_chroot_path']}{$cfgsubdir}/unbound_server.pem"
	control-key-file: "{$g['unbound_chroot_path']}{$cfgsubdir}/unbound_control.key"
	control-cert-file: "{$g['unbound_chroot_path']}{$cfgsubdir}/unbound_control.pem"

EOF;

		create_unbound_chroot_path($cfgsubdir);
		file_put_contents("{$g['unbound_chroot_path']}{$cfgsubdir}/remotecontrol.conf", $remotcfg);

		// Generate our keys
		do_as_unbound_user("unbound-control-setup", $cfgsubdir);

	}
}

function sync_unbound_service() {
	global $config, $g;

	create_unbound_chroot_path();

	// Configure our Unbound service
	do_as_unbound_user("unbound-anchor");
	unbound_remote_control_setup();
	unbound_generate_config();
	do_as_unbound_user("start");
	require_once("service-utils.inc");
	if (is_service_running("unbound")) {
		do_as_unbound_user("restore_cache");
	}

}

function unbound_acl_id_used($id) {
	global $config;

	if (is_array($config['unbound']['acls'])) {
		foreach ($config['unbound']['acls'] as & $acls) {
			if ($id == $acls['aclid']) {
				return true;
			}
		}
	}

	return false;
}

function unbound_get_next_id() {
	$aclid = 0;
	while (unbound_acl_id_used($aclid)) {
		$aclid++;
	}
	return $aclid;
}

// Execute commands as the user unbound
function do_as_unbound_user($cmd, $param1 = "") {
	global $g;

	switch ($cmd) {
		case "start":
			mwexec("/usr/local/sbin/unbound -c {$g['unbound_chroot_path']}/unbound.conf");
			break;
		case "stop":
			mwexec("/usr/bin/su -m unbound -c '/usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf stop'", true);
			break;
		case "reload":
			mwexec("/usr/bin/su -m unbound -c '/usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf reload'", true);
			break;
		case "unbound-anchor":
			$root_key_file = "{$g['unbound_chroot_path']}{$param1}/root.key";
			// sanity check root.key because unbound-anchor will fail without manual removal otherwise. redmine #5334
			if (file_exists($root_key_file)) {
				$rootkeycheck = mwexec("/usr/bin/grep 'autotrust trust anchor file' {$root_key_file}", true);
				if ($rootkeycheck != "0") {
					log_error("Unbound {$root_key_file} file is corrupt, removing and recreating.");
					unlink_if_exists($root_key_file);
				}
			}
			mwexec("/usr/bin/su -m unbound -c '/usr/local/sbin/unbound-anchor -a {$root_key_file}'", true);
			// Only sync the file if this is the real (default) one, not a test one.
			if ($param1 == "") {
				//pfSense_fsync($root_key_file);
			}
			break;
		case "unbound-control-setup":
			mwexec("/usr/bin/su -m unbound -c '/usr/local/sbin/unbound-control-setup -d {$g['unbound_chroot_path']}{$param1}'", true);
			break;
		default:
			break;
	}
}

function unbound_add_domain_overrides($pvt_rev="", $cfgsubdir = "") {
	global $config, $g;

	$domains = $config['unbound']['domainoverrides'];

	$sorted_domains = msort($domains, "domain");
	$result = array();
	$tls_domains = array();
	$tls_hostnames = array();
	foreach ($sorted_domains as $domain) {
		$domain_key = current($domain);
		if (!isset($result[$domain_key])) {
			$result[$domain_key] = array();
		}
		$result[$domain_key][] = $domain['ip'];
		/* If any entry for a domain has TLS set, it will be active for all entries. */
		if (isset($domain['forward_tls_upstream'])) {
			$tls_domains[] = $domain_key;
			$tls_hostnames[$domain['ip']] = $domain['tls_hostname'];
		}
	}

	// Domain overrides that have multiple entries need multiple stub-addr: added
	$domain_entries = "";
	foreach ($result as $domain=>$ips) {
		if ($pvt_rev == "private") {
			$domain_entries .= "private-domain: \"$domain\"\n";
			$domain_entries .= "domain-insecure: \"$domain\"\n";
		} else if ($pvt_rev == "reverse") {
			if (preg_match("/.+\.(in-addr|ip6)\.arpa\.?$/", $domain)) {
				$domain_entries .= "local-zone: \"$domain\" typetransparent\n";
			}
		} else {
			$use_tls = in_array($domain, $tls_domains);
			$domain_entries .= "forward-zone:\n";
			$domain_entries .= "\tname: \"$domain\"\n";
			$fwdport = "";
			/* Enable TLS forwarding for this domain if needed. */
			if ($use_tls) {
				$domain_entries .= "\tforward-tls-upstream: yes\n";
				$fwdport = "@853";
			}
			foreach ($ips as $ip) {
				$fwdhost = "";
				/* If an IP address already contains a port specification, do not add another. */
				if (strstr($ip, '@') !== false) {
					$fwdport = "";
				}
				if ($use_tls && array_key_exists($ip, $tls_hostnames)) {
					$fwdhost = "#{$tls_hostnames[$ip]}";
				}
				$domain_entries .= "\tforward-addr: {$ip}{$fwdport}{$fwdhost}\n";
			}
		}
	}

	if ($pvt_rev != "") {
		return $domain_entries;
	} else {
		create_unbound_chroot_path($cfgsubdir);
		file_put_contents("{$g['unbound_chroot_path']}{$cfgsubdir}/domainoverrides.conf", $domain_entries);
	}
}

function unbound_generate_zone_data($domain, $hosts, &$added_ptr, $zone_type = "transparent", $write_domain_zone_declaration = false, $always_add_short_names = false) {
	global $config;
	if ($write_domain_zone_declaration) {
		$zone_data = "local-zone: \"{$domain}.\" {$zone_type}\n";
	} else {
		$zone_data = "";
	}
	foreach ($hosts as $host) {
		if (is_ipaddrv4($host['ipaddr'])) {
			$type = 'A';
		} else if (is_ipaddrv6($host['ipaddr'])) {
			$type = 'AAAA';
		} else {
			continue;
		}
		if (!$added_ptr[$host['ipaddr']]) {
			$zone_data .= "local-data-ptr: \"{$host['ipaddr']} {$host['fqdn']}\"\n";
			$added_ptr[$host['ipaddr']] = true;
		}
		/* For the system localhost entry, write an entry for just the hostname. */
		if ((($host['name'] == "localhost") && ($domain == $config['system']['domain'])) || $always_add_short_names) {
			$zone_data .= "local-data: \"{$host['name']}. {$type} {$host['ipaddr']}\"\n";
		}
		/* Redirect zones must have a zone declaration that matches the
		 * local-data record exactly, it cannot have entries "under" the
		 * domain.
		 */
		if ($zone_type == "redirect") {
			$zone_data .= "local-zone: \"{$host['fqdn']}.\" {$zone_type}\n";;
		}
		$zone_data .= "local-data: \"{$host['fqdn']}. {$type} {$host['ipaddr']}\"\n";
	}
	return $zone_data;
}

function unbound_add_host_entries($cfgsubdir = "") {
	global $config, $g;

	$hosts = system_hosts_entries($config['unbound']);

	/* Pass 1: Build domain list and hosts inside domains */
	$hosts_by_domain = array();
	foreach ($hosts as $host) {
		if (!array_key_exists($host['domain'], $hosts_by_domain)) {
			$hosts_by_domain[$host['domain']] = array();
		}
		$hosts_by_domain[$host['domain']][] = $host;
	}

	$added_ptr = array();
	/* Build local zone data */
	// Check if auto add host entries is not set
	$system_domain_local_zone_type = "transparent";
	if (!isset($config['unbound']['disable_auto_added_host_entries'])) {
		// Make sure the config setting is a valid unbound local zone type.  If not use "transparent".
		if (array_key_exists($config['unbound']['system_domain_local_zone_type'], unbound_local_zone_types())) {
			$system_domain_local_zone_type = $config['unbound']['system_domain_local_zone_type'];
		}
	}
	/* Add entries for the system domain before all others */
	if (array_key_exists($config['system']['domain'], $hosts_by_domain)) {
		$unbound_entries .= unbound_generate_zone_data($config['system']['domain'],
					$hosts_by_domain[$config['system']['domain']],
					$added_ptr,
					$system_domain_local_zone_type,
					true);
		/* Unset this so it isn't processed again by the loop below. */
		unset($hosts_by_domain[$config['system']['domain']]);
	}

	/* Build zone data for other domain */
	foreach ($hosts_by_domain as $domain => $hosts) {
		$unbound_entries .= unbound_generate_zone_data($domain,
					$hosts,
					$added_ptr,
					"transparent",
					false,
					isset($config['unbound']['always_add_short_names']));
	}

	// Write out entries
	create_unbound_chroot_path($cfgsubdir);
	file_put_contents("{$g['unbound_chroot_path']}{$cfgsubdir}/host_entries.conf", $unbound_entries);

	/* dhcpleases will write to this config file, make sure it exists */
	@touch("{$g['unbound_chroot_path']}{$cfgsubdir}/dhcpleases_entries.conf");
}

function unbound_control($action) {
	global $config, $g;

	$cache_dumpfile = "/var/tmp/unbound_cache";

	switch ($action) {
	case "start":
		// Start Unbound
		if ($config['unbound']['enable'] == "on") {
			if (!is_service_running("unbound")) {
				do_as_unbound_user("start");
			}
		}
		break;
	case "stop":
		if ($config['unbound']['enable'] == "on") {
			do_as_unbound_user("stop");
		}
		break;
	case "reload":
		if ($config['unbound']['enable'] == "on") {
			do_as_unbound_user("reload");
		}
		break;
	case "dump_cache":
		// Dump Unbound's Cache
		if ($config['unbound']['dumpcache'] == "on") {
			do_as_unbound_user("dump_cache");
		}
		break;
	case "restore_cache":
		// Restore Unbound's Cache
		if ((is_service_running("unbound")) && ($config['unbound']['dumpcache'] == "on")) {
			if (file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0) {
				do_as_unbound_user("load_cache < /var/tmp/unbound_cache");
			}
		}
		break;
	default:
		break;

	}
}

// Generation of Unbound statistics
function unbound_statistics() {
	global $config;

	if ($config['stats'] == "on") {
		$stats_interval = $config['unbound']['stats_interval'];
		$cumulative_stats = $config['cumulative_stats'];
		if ($config['extended_stats'] == "on") {
			$extended_stats = "yes";
		} else {
			$extended_stats = "no";
		}
	} else {
		$stats_interval = "0";
		$cumulative_stats = "no";
		$extended_stats = "no";
	}
	/* XXX To do - add RRD graphs */
	$stats = <<<EOF
# Unbound Statistics
statistics-interval: {$stats_interval}
extended-statistics: yes
statistics-cumulative: yes

EOF;

	return $stats;
}

// Unbound Access lists
function unbound_acls_config($cfgsubdir = "") {
	global $g, $config;

	if (!isset($config['unbound']['disable_auto_added_access_control'])) {
		$aclcfg = "access-control: 127.0.0.1/32 allow_snoop\n";
		$aclcfg .= "access-control: ::1 allow_snoop\n";
		// Add our networks for active interfaces including localhost
		if (!empty($config['unbound']['active_interface'])) {
			$active_interfaces = array_flip(explode(",", $config['unbound']['active_interface']));
			if (in_array("all", $active_interfaces)) {
				$active_interfaces = get_configured_interface_with_descr();
			}
		} else {
			$active_interfaces = get_configured_interface_with_descr();
		}

		$aclnets = array();
		foreach ($active_interfaces as $ubif => $ifdesc) {
			$ifip = get_interface_ip($ubif);
			if (is_ipaddrv4($ifip)) {
				// IPv4 is handled via NAT networks below
			}
			$ifip = get_interface_ipv6($ubif);
			if (is_ipaddrv6($ifip)) {
				if (!is_linklocal($ifip)) {
					$subnet_bits = get_interface_subnetv6($ubif);
					$subnet_ip = gen_subnetv6($ifip, $subnet_bits);
					// only add LAN-type interfaces
					if (!interface_has_gateway($ubif)) {
						$aclnets[] = "{$subnet_ip}/{$subnet_bits}";
					}
				}
				// add for IPv6 static routes to local networks
				// for safety, we include only routes reachable on an interface with no
				// gateway specified - read: not an Internet connection.
				$static_routes = get_staticroutes(false, false, true); // Parameter 3 returnenabledroutesonly
				foreach ($static_routes as $route) {
					if ((lookup_gateway_interface_by_name($route['gateway']) == $ubif) && !interface_has_gateway($ubif)) {
						// route is on this interface, interface doesn't have gateway, add it
						$aclnets[] = $route['network'];
					}
				}
			}
		}

		// OpenVPN IPv6 Tunnel Networks
		foreach (array('openvpn-client', 'openvpn-server') as $ovpnentry) {
			if (is_array($config['openvpn'][$ovpnentry])) {
				foreach ($config['openvpn'][$ovpnentry] as $ovpnent) {
					if (!isset($ovpnent['disable']) && !empty($ovpnent['tunnel_networkv6'])) {
						$aclnets[] = $ovpnent['tunnel_networkv6'];
					}
				}
			}
		}
		// IPsec Mobile Virtual IPv6 Address Pool
		if ((isset($config['ipsec']['client']['enable'])) &&
		    (!empty($config['ipsec']['client']['pool_address_v6'])) &&
		    (!empty($config['ipsec']['client']['pool_netbits_v6']))) {
			$aclnets[] = "{$config['ipsec']['client']['pool_address_v6']}/{$config['ipsec']['client']['pool_netbits_v6']}";
		}

		// Generate IPv4 access-control entries using the same logic as automatic outbound NAT
		if (empty($FilterIflist)) {
			filter_generate_optcfg_array();
		}
		$aclnets = array_merge($aclnets, filter_nat_rules_automatic_tonathosts());

		/* Automatic ACL networks deduplication and sorting
		 * https://redmine.pfsense.org/issues/11309 */
		$aclnets4 = array();
		$aclnets6 = array();
		foreach (array_unique($aclnets) as $acln) {
			if (is_v4($acln)) {
				$aclnets4[] = $acln;
			} else {
				$aclnets6[] = $acln;
			}
		}
		/* ipcmp only supports IPv4 */
		usort($aclnets4, "ipcmp");
		sort($aclnets6);

		foreach (array_merge($aclnets4, $aclnets6) as $acln) {
			$aclcfg .= "access-control: {$acln} allow \n";
		}
	}

	// Configure the custom ACLs
	if (is_array($config['unbound']['acls'])) {
		foreach ($config['unbound']['acls'] as $unbound_acl) {
			$aclcfg .= "#{$unbound_acl['aclname']}\n";
			foreach ($unbound_acl['row'] as $network) {
				if ($unbound_acl['aclaction'] == "allow snoop") {
					$unbound_acl['aclaction'] = "allow_snoop";
				} elseif ($unbound_acl['aclaction'] == "deny nonlocal") {
					$unbound_acl['aclaction'] = "deny_non_local";
				} elseif ($unbound_acl['aclaction'] == "refuse nonlocal") {
					$unbound_acl['aclaction'] = "refuse_non_local";
				}
				$aclcfg .= "access-control: {$network['acl_network']}/{$network['mask']} {$unbound_acl['aclaction']}\n";
			}
		}
	}
	// Write out Access list
	create_unbound_chroot_path($cfgsubdir);
	file_put_contents("{$g['unbound_chroot_path']}{$cfgsubdir}/access_lists.conf", $aclcfg);

}

// Generate hosts and reload services
function unbound_hosts_generate() {
	// Generate our hosts file
	unbound_add_host_entries();

	// Reload our service to read the updates
	unbound_control("reload");
}

// Array of valid unbound local zone types
function unbound_local_zone_types() {
	return array(
		"deny" => gettext("Deny"),
		"refuse" => gettext("Refuse"),
		"static" => gettext("Static"),
		"transparent" => gettext("Transparent"),
		"typetransparent" => gettext("Type Transparent"),
		"redirect" => gettext("Redirect"),
		"inform" => gettext("Inform"),
		"inform_deny" => gettext("Inform Deny"),
		"nodefault" => gettext("No Default")
	);
}

// Autoconfig EDNS buffer size
function unbound_auto_ednsbufsize() {
	global $config;

	$active_ipv6_inf = false;
	if ($config['unbound']['active_interface'] != 'all') {
		$active_interfaces = explode(",", $config['unbound']['active_interface']);
	} else {
		$active_interfaces = get_configured_interface_list();
	}

	$min_mtu = get_interface_mtu(get_real_interface($active_interfaces[0]));
	foreach ($active_interfaces as $ubif) {
		$ubif_mtu = get_interface_mtu(get_real_interface($ubif));
		if (get_interface_ipv6($ubif)) {
			$active_ipv6_inf = true;
		}
		if ($ubif_mtu < $min_mtu) {
			$min_mtu = $ubif_mtu;
		}
	}

	// maximum IPv4 + UDP header = 68 bytes
	$min_mtu = $min_mtu - 68;

	if (($min_mtu < 1232) && $active_ipv6_inf) {
		$min_mtu = 1232;
	} elseif ($min_mtu < 512) {
		$min_mtu = 512;
	}	

	return $min_mtu;
}

?>
