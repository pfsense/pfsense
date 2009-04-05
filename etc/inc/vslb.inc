<?php
/* $Id$ */
/*
	vslb.inc
    Copyright (C) 2005 Bill Marquette
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
require_once("pkg-utils.inc");
require_once("notices.inc");

function slbd_configure() {
	global $config, $g;
	
	$a_vs = &$config['load_balancer']['virtual_server'];
	$a_pool = &$config['load_balancer']['lbpool'];

	$should_start=0;

	$fd = fopen("{$g['varetc_path']}/slbd.conf", "w");


	/* Virtual server pools */
	if(is_array($a_vs)) {
		foreach ($a_vs as $vsent) {
			if ($vsent['desc'] == "")
				$slbdconf .= "{$vsent['name']}:\\\n";
			else
				$slbdconf .= "{$vsent['name']}|{$vsent['desc']}:\\\n";
	
			/* pool name */
			$slbdconf .= "\t:poolname={$vsent['name']}:\\\n";
			/* remove pool status files so we don't end up with a mismatch */
			if(file_exists("{$g['tmp_path']}/{$vsent['name']}.pool"))
				unlink("{$g['tmp_path']}/{$vsent['name']}.pool");
			/* virtual IP */
			$slbdconf .= "\t:vip={$vsent['ipaddr']}:\\\n";
			/* virtual port */
			$slbdconf .= "\t:vip-port={$vsent['port']}:\\\n";
			if($vsent['port'] <> "" and $vsent['sitedown'] <> "") {
				/* fallback IP */
				$slbdconf .= "\t:sitedown={$vsent['sitedown']}:\\\n";
				/* fallback port */
				$slbdconf .= "\t:sitedown-port={$vsent['port']}:\\\n";
			}
			for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
				if ($config['load_balancer']['lbpool'][$i]['name'] == $vsent['pool']) {
					$svrcnt = 0;
					$svrtxt = "";
					$svrtxt = "\t:service-port={$config['load_balancer']['lbpool'][$i]['port']}:\\\n";
					if($config['load_balancer']['lbpool'])
						if(is_array($config['load_balancer']['lbpool'][$i]['servers']))
							foreach ($config['load_balancer']['lbpool'][$i]['servers'] as $lbsvr) {
								$svrtxt .= "\t:{$svrcnt}={$lbsvr}:\\\n";
								$svrcnt++;
							}
					$slbdconf .= "\t:method=round-robin:\\\n";
					$slbdconf .= "\t:services={$svrcnt}:\\\n";
					$slbdconf .= $svrtxt;
					if($config['load_balancer']['lbpool'][$i]['monitor'] == "ICMP") {
						$slbdconf .= "\t:ping:\n";
					} else {
						$slbdconf .= "\t:tcppoll:send=:expect=:\n";
					}
				}
			}
			
			$should_start=1;
		}
	}

	/* Gateway Pools */
	if(is_array($a_pool)) {
		foreach ($a_pool as $vspool) {
			if ($vspool['type'] != "gateway")
				continue;

			if ($vspool['desc'] == "")
				$slbdconf .= "{$vspool['name']}:\\\n";
			else
				$slbdconf .= "{$vspool['name']}|{$vspool['desc']}:\\\n";
	
			/* pool name */
			$slbdconf .= "\t:poolname={$vspool['name']}:\\\n";
			/* remove pool status files so we don't end up with a mismatch */
			if(file_exists("{$g['tmp_path']}/{$vspool['name']}.pool"))
				unlink("{$g['tmp_path']}/{$vspool['name']}.pool");
			/* virtual IP */
			$slbdconf .= "\t:vip=127.0.0.1:\\\n";
			$slbdconf .= "\t:vip-port=666:\\\n";
			/* fallback IP */
			$slbdconf .= "\t:sitedown=127.0.0.1:\\\n";
			/* fallback port */
			$slbdconf .= "\t:sitedown-port=666:\\\n";

			$svrcnt = 0;
			$svrtxt = "";
			if($vspool['servers'])
			foreach ($vspool['servers'] as $lbsvr) {
				$lbsvr_split=split("\|", $lbsvr);
				$svrtxt .= "\t:{$svrcnt}={$lbsvr_split[1]}:\\\n";
				$svrcnt++;

				/* Add static routes to the monitor IPs */
				$int = convert_friendly_interface_to_real_interface_name($lbsvr_split[0]);
				$gateway = get_interface_gateway($int);
				$int_ip = find_interface_ip($int);
				if($int_ip == "0.0.0.0") {
					/*   DHCP Corner case.  If DHCP is down, we delete the route then
					 *   there is a chance the monitor ip gateway will go out the link
					 *   that is up.
					 */
					mwexec("/sbin/route delete -host {$lbsvr_split[1]} 2>&1", true);
					mwexec("/sbin/route add -host {$lbsvr_split[1]} 127.0.0.1 2>&1", true);
				} else {
					mwexec("/sbin/route delete -host {$lbsvr_split[1]} 2>&1", true);
					mwexec("/sbin/route add -host {$lbsvr_split[1]} {$gateway} 2>&1", true);
				}
			}
			$slbdconf .= "\t:service-port=666:\\\n";
			$slbdconf .= "\t:method=round-robin:\\\n";
			$slbdconf .= "\t:services={$svrcnt}:\\\n";
			$slbdconf .= $svrtxt;
			
			$slbdconf .= "\t:ping:\n";
			
			$should_start=1;
		}
	}
	
	if($should_start == 1) {
		fwrite($fd, $slbdconf);
		fclose($fd);
		mwexec("/usr/bin/killall -9 slbd", true);
		sleep(2);
		/* startup slbd pointing it's config at /var/etc/slbd.conf with a polling interval of 5 seconds */
		mwexec("/usr/local/sbin/slbd -c{$g['varetc_path']}/slbd.conf -r5000");
	} else {
		mwexec("/usr/bin/killall -9 slbd", true);
		fclose($fd);
	}
}

?>