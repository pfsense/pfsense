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
	return $domain_entries;
}


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
	
	// Memory usage default of 4Mb
	$optimization['msg_cache_size'] = "msg-cache-size: 4m";
	$optimization['rrset_cache_size'] = "rrset-cache-size: 8m";

	// More outgoing connections per thread otherwise assign a default of 4096 for a single thread
	if($numprocs > 0) {
		$or = (1024/$numprocs) - 50;
		$optimization['outgoing_range'] = "outgoing-range: {$or}";
	} else {
		$optimization['outgoing_range'] = "outgoing-range: {4096}";
	}

	// Larger socket buffer for busy servers
	// Check that it is set to 4MB (by default the OS has it configured to 4MB)
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
	// Safety check in case kern.ipc.maxsockbuf is deleted.
	if(!isset($optimization['so_rcvbuf']))
		$optimization['so_rcvbuf'] = "#so-rcvbuf: 4m";

	return $optimization;
}

function unbound_fetch_root_hints() {
	global $g;

	$hints = "{$g['unbound_chroot_path']}/etc/root.hints";
	if (@filesize($hints) == 0 ) {
		conf_mount_rw();
		$returnvar = mwexec("/usr/bin/dig +tcp +nocmd +answer @`/usr/bin/dig +nocmd +noall +answer +short . NS | /usr/bin/head -1` . NS > {$hints}");
		conf_mount_ro();
		if ($returnvar != 0) {
			mwexec("/bin/rm -f {$hints}");
			return false;
		} else
			return true;
	} else
		return true;
}

?>