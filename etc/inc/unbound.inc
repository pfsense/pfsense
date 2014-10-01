<?php
/*
    unbound.inc
    part of the pfSense project (https://www.pfsense.org)
    Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
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
    
    pfSense_BUILDER_BINARIES:   /usr/sbin/unbound  /usr/sbin/unbound-anchor    /usr/sbin/unbound-checkconf
    pfSense_BUILDER_BINARIES:   /usr/sbin/unbound-control    /usr/sbin/unbound-control-setup
    pfSense_MODULE: unbound
*/

/* include all configuration functions */
require_once("config.inc");
require_once("functions.inc");

/* Optimize Unbound for environment */
function unbound_optimization() {
    global $config;

    $optimization_settings = array();
    
    /* 
     * Set the number of threads equal to number of CPUs.
     * Use 1 to disable threading, if for some reason this sysctl fails.
     */
    $numprocs = intval(get_single_sysctl('kern.smp.cpus'));
    if ($numprocs > 0)
        $optimization['number_threads'] = "num-threads: {$numprocs}";
    else
        $optimization['number_threads'] = "num-threads: 1";
    
    // Slabs to help reduce lock contention.
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
    
    // Memory usage default of 4MB
    $optimization['msg_cache_size'] = "msg-cache-size: 4m";
    $optimization['rrset_cache_size'] = "rrset-cache-size: 8m";

    // More outgoing connections per thread otherwise assign a default of 4096 for a single thread
    if ($numprocs > 0) {
        $or = (1024/$numprocs) - 50;
        $optimization['outgoing_range'] = "outgoing-range: {$or}";
    } else
        $optimization['outgoing_range'] = "outgoing-range: {4096}";

    /*
     * Larger socket buffer for busy servers
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
    // Safety check in case kern.ipc.maxsockbuf is not available.
    if (!isset($optimization['so_rcvbuf']))
        $optimization['so_rcvbuf'] = "#so-rcvbuf: 4m";

    return $optimization;

}

function unbound_generate_config() {
    global $config, $g;

    // Setup optimization
    $optimization = unbound_optimization();

    // Setup DNSSEC support
    if (isset($config['unbound']['dnssec'])) {
        $module_config = "validator iterator";
        $anchor_file = "auto-trust-anchor-file: {$g['unbound_chroot_path']}/root.key";
    } else
        $module_config = "iterator";

    // Setup DNS Rebinding
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
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

    // Determine interfaces to run on
    $bindints = "";
    if (!empty($config['unbound']['active_interface'])) {
        $active_interfaces = explode(",", $config['unbound']['active_interface']);
        foreach($active_interfaces as $ubif) {
            $intip = get_interface_ip($ubif);
            if (!is_null($intip))
                $bindints .= "interface: $intip\n";
            $intip = get_interface_ipv6($ubif);
            if (!is_null($intip))
                $bindints .= "interface: $intip\n";
        }
    } else {
        $bindints .= "interface: 0.0.0.0\n";
        $bindints .= "interface: ::0\n";
    }

    // Determine interfaces to run on
    $outgoingints = "";
    if (!empty($config['unbound']['outgoing_interface'])) {
        $outgoingints = "# Outgoing interfaces to be used\n";
        $outgoing_interfaces = explode(",", $config['unbound']['outgoing_interface']);
        foreach($outgoing_interfaces as $outif) {
            $outip = get_interface_ip($outif);
            if (!is_null($outip))
                $outgoingints .= "outgoing-interface: $outip\n";
            $outip = get_interface_ipv6($outif);
            if (!is_null($outip))
                $outgoingints .= "outgoing-interface: $outip\n";
        }
    }

    // Allow DNS Rebind for forwarded domains
    if ((isset($config['unbound']['domainoverrides']) && is_array($config['unbound']['domainoverrides'])) && !isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $private_domains = "# Set private domains in case authoritative name server returns a Private IP address\n";
        $private_domains .= unbound_add_domain_overrides(true);
    }

    // Configure static Host entries
    unbound_add_host_entries();

    // Configure Domain Overrides
    unbound_add_domain_overrides();

    // Configure Unbound statistics
    $statistics = unbound_statistics();

    // Configure Unbound access-lists
    unbound_acls_config();

    // Add custom Unbound options
    if ($config['unbound']['custom_options']) {
        $custom_option = "# Unbound custom option";
        foreach (preg_split('/\s+/', $config['unbound']['custom_options']) as $ent)
            $custom_option .= $ent."\n";
    }

    // Server configuration variables
    $port = (is_port($config['unbound']['port'])) ? $config['unbound']['port'] : "53";
    $hide_id = ($config['unbound']['hide_id'] == "on") ? "yes" : "no";
    $hide_version = ($config['unbound']['hide_version'] == "on") ? "yes" : "no";
    $harden_glue = ($config['unbound']['harden_glue'] == "on") ? "yes" : "no";
    $harden_dnssec_stripped = ($config['unbound']['harden_dnssec_stripped'] == "on") ? "yes" : "no";
    $prefetch = ($config['unbound']['prefetch'] == "on") ? "yes" : "no";
    $prefetch_key = ($config['unbound']['prefetch_key'] == "on") ? "yes" : "no";
    $outgoing_num_tcp = (!empty($config['unbound']['outgoing_num_tcp'])) ? $config['unbound']['outgoing_num_tcp'] : "10";
    $incoming_num_tcp = (!empty($config['unbound']['incoming_num_tcp'])) ? $config['unbound']['incoming_num_tcp'] : "10";
    $edns_buffer_size = (!empty($config['unbound']['edns_buffer_size'])) ? $config['unbound']['edns_buffer_size'] : "4096";
    $num_queries_per_thread = (!empty($config['unbound']['num_queries_per_thread'])) ? $config['unbound']['num_queries_per_thread'] : "4096";
    $jostle_timeout = (!empty($config['unbound']['jostle_timeout'])) ? $config['unbound']['jostle_timeout'] : "200";
    $cache_max_ttl = (!empty($config['unbound']['cache_max_ttl'])) ? $config['unbound']['cache_max_ttl'] : "86400";
    $cache_min_ttl = (!empty($config['unbound']['cache_min_ttl'])) ? $config['unbound']['cache_min_ttl'] : "0";
    $infra_host_ttl = (!empty($config['unbound']['infra_host_ttl'])) ? $config['unbound']['infra_host_ttl'] : "900";
    $infra_lame_ttl = (!empty($config['unbound']['infra_lame_ttl'])) ? $config['unbound']['infra_lame_ttl'] : "900";
    $infra_cache_numhosts = (!empty($config['unbound']['infra_cache_numhosts'])) ? $config['unbound']['infra_cache_numhosts'] : "10000";
    $unwanted_reply_threshold = (!empty($config['unbound']['unwanted_reply_threshold'])) ? $config['unbound']['unwanted_reply_threshold'] : "0";
    $verbosity = isset($config['unbound']['loglevel']) ? $config['unbound']['loglevel'] : 1;

    // Set up forwarding if it configured
    if (isset($config['unbound']['forwarding'])) {
        $dnsservers = array();
        if (isset($config['system']['dnsallowoverride'])) {
            $ns = array_unique(get_nameservers());
            foreach($ns as $nameserver) {
                if ($nameserver)
                    $dnsservers[] = $nameserver;
            }
        } else {
            $ns = array_unique(get_dns_servers());
            foreach($ns as $nameserver) {
                if ($nameserver)
                    $dnsservers[] = $nameserver;
            }
        }

        if (!empty($dnsservers)) {
            $forward_conf .=<<<EOD
# Forwarding
forward-zone:
    name: "."

EOD;
            foreach($dnsservers as $dnsserver)
                $forward_conf .= "\tforward-addr: $dnsserver\n";
        }
    } else
        $forward_conf = "";

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
directory: "{$g['unbound_chroot_path']}"
pidfile: "/var/run/unbound.pid"
use-syslog: yes
port: {$port}
verbosity: {$verbosity}
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
cache-max-ttl: {$cache_max_ttl}
cache-min-ttl: {$cache_min_ttl}
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
prefetch: {$prefetch}
prefetch-key: {$prefetch_key}
# Statistics
{$statistics}
# Interface IP(s) to bind to
{$bindints}
{$outgoingints}

# DNS Rebinding
{$private_addr}
{$private_domains}

# Access lists
include: {$g['unbound_chroot_path']}/access_lists.conf

# Static host entries
include: {$g['unbound_chroot_path']}/host_entries.conf

# Domain overrides
include: {$g['unbound_chroot_path']}/domainoverrides.conf
{$forward_conf}

{$custom_options}

###
# Remote Control Config
###
include: {$g['unbound_chroot_path']}/remotecontrol.conf

EOD;

    file_put_contents("{$g['unbound_chroot_path']}/unbound.conf", $unboundconf);

    return 0;
}

function unbound_remote_control_setup() {
    global $g;

    if (!file_exists("{$g['unbound_chroot_path']}/remotecontrol.conf") || !file_exists("{$g['unbound_chroot_path']}/unbound_control.key")) {
        $remotcfg = <<<EOF
remote-control:
    control-enable: yes
    control-interface: 127.0.0.1
    control-port: 953
    server-key-file: "{$g['unbound_chroot_path']}/unbound_server.key"
    server-cert-file: "{$g['unbound_chroot_path']}/unbound_server.pem"
    control-key-file: "{$g['unbound_chroot_path']}/unbound_control.key"
    control-cert-file: "{$g['unbound_chroot_path']}/unbound_control.pem"

EOF;

        file_put_contents("{$g['unbound_chroot_path']}/remotecontrol.conf", $remotcfg);

        // Generate our keys
        do_as_unbound_user("unbound-control-setup");

    }
}


// Read /etc/hosts
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

function sync_unbound_service() {
    global $config, $g;

    // Configure chroot
    if (!is_dir($g['unbound_chroot_path'])) {
        mkdir($g['unbound_chroot_path']);
        chown($g['unbound_chroot_path'], "unbound");
        chgrp($g['unbound_chroot_path'], "unbound");
    }

    // Configure our Unbound service
    do_as_unbound_user("unbound-anchor");
    unbound_remote_control_setup();
    unbound_generate_config();
    do_as_unbound_user("start");
    require_once("service-utils.inc");
    if (is_service_running("unbound"))
        do_as_unbound_user("restore_cache");

}

function unbound_acl_id_used($id) {
    global $config;

    if (is_array($config['unbound']['acls']))
        foreach($config['unbound']['acls'] as & $acls)
            if ($id == $acls['aclid'])
                return true;

    return false;
}

function unbound_get_next_id() {
    $aclid = 0;
    while(unbound_acl_id_used($aclid))
        $aclid++;
    return $aclid;
}

// Execute commands as the user unbound
function do_as_unbound_user($cmd) {
    global $g;

    switch ($cmd) {
        case "start":
            mwexec("/usr/sbin/unbound -c {$g['unbound_chroot_path']}/unbound.conf");
            break;
        case "stop":
            mwexec("echo '/usr/sbin/unbound-control stop' | /usr/bin/su -m unbound", true);
            break;
        case "unbound-anchor":
            mwexec("echo '/usr/sbin/unbound-anchor -a {$g['unbound_chroot_path']}/root.key' | /usr/bin/su -m unbound", true);
            break;
        case "unbound-control-setup":
            mwexec("echo '/usr/sbin/unbound-control-setup -d {$g['unbound_chroot_path']}' | /usr/bin/su -m unbound", true);
            break;
        default:
            break;
    }
}

function unbound_add_domain_overrides($pvt=false) {
    global $config, $g;

    $domains = $config['unbound']['domainoverrides'];

    $sorted_domains = msort($domains, "domain");
    $result = array();      
    foreach($sorted_domains as $domain) {
        $domain_key = current($domain);
        if (!isset($result[$domain_key]))
            $result[$domain_key] = array();
        $result[$domain_key][] = $domain['ip'];
    }

    // Domain overrides that have multiple entries need multiple stub-addr: added
    $domain_entries = "";
    foreach($result as $domain=>$ips) {
        if ($pvt == true) {
            $domain_entries .= "private-domain: \"$domain\"\n";
            $domain_entries .= "domain-insecure: \"$domain\"\n";
        } else {
            $domain_entries .= "stub-zone:\n";
            $domain_entries .= "\tname: \"$domain\"\n";
            foreach($ips as $ip)
                $domain_entries .= "\tstub-addr: $ip\n";
            $domain_entries .= "\tstub-prime: no\n";
        }
    }
    
    if ($pvt == true)
        return $domain_entries;
    else
        file_put_contents("{$g['unbound_chroot_path']}/domainoverrides.conf", $domain_entries);
}

function unbound_add_host_entries() {
    global $config, $g;

    $unbound_entries = "local-zone: \"{$config['system']['domain']}\" transparent\n";
    // IPv4 entries
    $unbound_entries .= "local-data-ptr: \"127.0.0.1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost A 127.0.0.1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} A 127.0.0.1\"\n";
    // IPv6 entries
    $unbound_entries .= "local-data-ptr: \"::1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost AAAA ::1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} AAAA ::1\"\n";

    $listen_addresses = "";
    if (isset($config['unbound']['interface'])) {
        $interfaces = explode(",", $config['unbound']['interface']);
        foreach ($interfaces as $interface) {
            if (is_ipaddrv4($interface)) {
                $unbound_entries .= "local-data-ptr: \"{$interface} {$config['system']['hostname']}.{$config['system']['domain']}\"\n";
                $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$config['system']['domain']} A {$interface}\"\n";
                $unbound_entries .= "local-data: \"{$config['system']['hostname']} A {$interface}\"\n";
            } else if (is_ipaddrv6($interface)) {
                $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$config['system']['domain']} AAAA {$interface}\"\n";
                $unbound_entries .= "local-data: \"{$config['system']['hostname']} AAAA {$interface}\"\n";
            } else {
                $if = get_real_interface($interface);
                if (does_interface_exist($if)) {
                    $laddr = find_interface_ip($if);
                    if (is_ipaddrv4($laddr)) {
                        $unbound_entries .= "local-data-ptr: \"{$laddr} {$config['system']['hostname']}.{$config['system']['domain']}\"\n";
                        $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$config['system']['domain']} A {$laddr}\"\n";
                        $unbound_entries .= "local-data: \"{$config['system']['hostname']} A {$laddr}\"\n";
                    }
                    $laddr6 = find_interface_ipv6($if);
                    if (is_ipaddrv6($laddr6) && !isset($config['dnsmasq']['strictbind'])) {
                        $unbound_entries .= "local-data-ptr: \"{$laddr6} {$config['system']['hostname']}.{$config['system']['domain']}\"\n";
                        $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$config['system']['domain']} AAAA {$laddr}\"\n";
                        $unbound_entries .= "local-data: \"{$config['system']['hostname']} AAAA {$laddr}\"\n";
                    }
                }
            }
        }
    }

    // Static Host entries
    if (isset($config['unbound']['hosts'])) {
        $host_entries = "";
        $added_item = array();
        foreach($config['unbound']['hosts'] as $host) {
            $current_host = $host['host'];
            if ($host['host'] != "")
                $host['host'] = $host['host'].".";
            if (!$added_item[$current_host]) {
                $host_entries .= "local-data-ptr: \"{$host['ip']} {$host['host']}{$host['domain']}\"\n";
                if (is_ipaddrv6($host['ip']))
                    $host_entries .= "local-data: \"{$host['host']}{$host['domain']} IN AAAA {$host['ip']}\"\n";
                else
                    $host_entries .= "local-data: \"{$host['host']}{$host['domain']} IN A {$host['ip']}\"\n";
                if (!empty($host['descr']) && isset($config['unbound']['txtsupport']))
                    $host_entries .= "local-data: '{$host['host']}{$host['domain']} TXT \"".addslashes($host['descr'])."\"'\n";

                // Do not add duplicate entries
                $added_item[$current_host] = true;
            }
        }
        $unbound_entries .= $host_entries;
    }

    // Static DHCP entries
    $host_entries = "";
    if (isset($config['unbound']['regdhcpstatic']) && is_array($config['dhcpd'])) {
        foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf)
            if (is_array($dhcpifconf['staticmap']) && isset($dhcpifconf['enable']))
                foreach ($dhcpifconf['staticmap'] as $host)
                    if ($host['ipaddr'] && $host['hostname']) {
                        $host_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['hostname']}.{$config['system']['domain']}\"\n";
                        $host_entries .= "local-data: \"{$host['hostname']}.{$config['system']['domain']} IN A {$host['ipaddr']}\"\n";
                        if (!empty($host['descr']) && $unboundcfg['txtsupport'] == 'on')
                            $host_entries .= "local-data: '{$host['hostname']}.{$config['system']['domain']} TXT \"".addslashes($host['descr'])."\"'\n";
                    }
        $unbound_entries .= $host_entries;
    }

    // Handle DHCPLeases added host entries
    $dhcplcfg = read_hosts();
    $host_entries = "";
    if (is_array($dhcplcfg)) {
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

    // Write out entries
    file_put_contents("{$g['unbound_chroot_path']}/host_entries.conf", $unbound_entries);
}

function unbound_control($action) {
    global $config, $g;

    $cache_dumpfile = "/var/tmp/unbound_cache";

    switch ($action) {
        case "start":
            // Start Unbound
            if ($config['unbound']['enable'] == "on") {
                if (!is_service_running("unbound"))
                    do_as_unbound_user("start");
            }
            break;
        case "stop":
            if ($config['unbound']['enable'] == "on")
                do_as_unbound_user("stop");
            break;
        case "reload":
            if ($config['unbound']['enable'] == "on")
                do_as_unbound_user("reload");
            break;
        case "dump_cache":
            // Dump Unbound's Cache
            if ($config['unbound']['dumpcache'] == "on")
                do_as_unbound_user("dump_cache");
            break;
        case "restore_cache":
            // Restore Unbound's Cache
            if ((is_service_running("unbound")) && ($config['unbound']['dumpcache'] == "on")) {
                if (file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0)
                    do_as_unbound_user("load_cache < /var/tmp/unbound_cache");
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
        if ($config['extended_stats'] == "on")
            $extended_stats = "yes";
        else
            $extended_stats = "no";
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
function unbound_acls_config() {
    global $g, $config;

    $aclcfg = "access-control: 127.0.0.1/32 allow\n";
    $aclcfg .= "access-control: ::1 allow\n";
    // Add our networks for active interfaces including localhost
    if (!empty($config['unbound']['active_interface']))
        $active_interfaces = array_flip(explode(",", $config['unbound']['active_interface']));
    else
        $active_interfaces = get_configured_interface_with_descr();

    $bindints = "";
    foreach($active_interfaces as $ubif => $ifdesc) {
        $ifip = get_interface_ip($ubif);
        if (!is_null($ifip)) {
            $subnet_bits = get_interface_subnet($ubif);
            $subnet_ip = gen_subnet($ifip, $subnet_bits);
            $aclcfg .= "access-control: {$subnet_ip}/{$subnet_bits} allow\n";
        }
        $ifip = get_interface_ipv6($ubif);
        if (!is_null($ifip)) {
            $subnet_bits = get_interface_subnetv6($ubif);
            $subnet_ip = gen_subnetv6($ifip, $subnet_bits);
            $aclcfg .= "access-control: {$subnet_ip}/{$subnet_bits} allow\n";
        }
    }

    // Configure the custom ACLs
    if (is_array($config['unbound']['acls'])) {
        foreach($config['unbound']['acls'] as $unbound_acl) {
            $aclcfg .= "#{$unbound_acl['aclname']}\n";
            foreach($unbound_acl['row'] as $network) {
                if ($unbound_acl['aclaction'] == "allow snoop")
                    $unbound_acl['aclaction'] = "allow_snoop";
                $aclcfg .= "access-control: {$network['acl_network']}/{$network['mask']} {$unbound_acl['aclaction']}\n";
            }
        }
    }
    // Write out Access list
    file_put_contents("{$g['unbound_chroot_path']}/access_lists.conf", $aclcfg);

}

// Generate hosts and reload services
function unbound_hosts_generate() {
    // Generate our hosts file
    unbound_add_host_entries();

    // Reload our service to read the updates
    unbound_control("reload");
}

?>
