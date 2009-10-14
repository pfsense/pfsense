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
					if($config['load_balancer']['lbpool']) {
						if(is_array($config['load_balancer']['lbpool'][$i]['servers'])) {
							foreach ($config['load_balancer']['lbpool'][$i]['servers'] as $lbsvr) {
								$svrtxt .= "\t:{$svrcnt}={$lbsvr}:\\\n";
								$svrcnt++;
							}
						}
					}
					if($config['load_balancer']['lbpool'][$i]['behaviour'])
						$lb_pool_behaviour = $config['load_balancer']['lbpool'][$i]['behaviour'];
					else 
						$lb_pool_behaviour = "round-robin";
					$slbdconf .= "\t:method={$lb_pool_behaviour}:\\\n";
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
	$a_settings['latencylow'] = "200";
	$a_settings['latencyhigh'] = "500";
	$a_settings['losslow'] = "10";
	$a_settings['losshigh'] = "20";

	/* kill apinger process */
	if(is_process_running("apinger"))
		mwexec("/usr/bin/killall apinger", true);

	$fda = fopen("{$g['varetc_path']}/apinger.conf", "w");
	$apingerconfig = <<<EOD
# pfSense apinger configuration file. Automatically Generated!
user "nobody"
group "nobody"
pid_file "{$g['varrun_path']}/apinger.pid"
status {
	file "/tmp/apinger.status"
	interval 10s
}
alarm default { 
	command on "/usr/bin/touch /tmp/filter_dirty"
	command off "/usr/bin/touch /tmp/filter_dirty"
	combine 10s
}
alarm down "down" {
	time 10s
}
alarm delay "delay" {
	delay_low {$a_settings['latencylow']}ms
	delay_high {$a_settings['latencyhigh']}ms
}
alarm loss "loss" {
	percent_low {$a_settings['losslow']}
	percent_high {$a_settings['losshigh']}
}
target default {
	interval 1s
	avg_delay_samples 10
	avg_loss_samples 50
	avg_loss_delay_samples 20
	alarms "down","delay","loss"
}

EOD;

	/* loop all pools to create configuration */
	if(is_array($a_pool)) {
		/* create a list of unique IP addresses */
		$a_addresses = array();
		foreach ($a_pool as $vspool) {
			if ($vspool['type'] != "gateway")
				continue;

			if(is_array($vspool['servers'])) {
				foreach ($vspool['servers'] as $lbsvr) {
					$lbsvr_split=split("\|", $lbsvr);
					$a_addresses[] = "$lbsvr_split[1]";
				}
			}
		}
		$a_addresses = array_unique($a_addresses);

		/* add static routes for each gateway with their monitor IP */
		if(!empty($a_addresses)) {
			foreach($a_addresses as $address) {
				$apingerconfig .= "target \"{$address}\" {\n";
				$apingerconfig .= "	description \"{$address}\"\n";
				$apingerconfig .= "}\n";
				$apingerconfig .= "\n";
			}
			fwrite($fda, $apingerconfig);
			fclose($fda);
		}

		foreach ($a_pool as $vspool) {
			if ($vspool['type'] != "gateway") {
				continue;
			}

			if(is_array($vspool['servers'])) {
				foreach ($vspool['servers'] as $lbsvr) {
					$lbsvr_split=split("\|", $lbsvr);

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
			}
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

	if(!is_process_running("apinger") && (!empty($a_addresses))) {
		if (is_dir("{$g['tmp_path']}"))
			chmod("{$g['tmp_path']}", 01777);
		if (is_dir("{$g['vardb_path']}/rrd"))
			chown("{$g['vardb_path']}/rrd", "nobody");
		/* start a new apinger process */
		mwexec_bg("/usr/local/sbin/apinger -c {$g['varetc_path']}/apinger.conf");
	}
	
}

/* return the status of the apinger targets as a array */
function return_apinger_status() {
	global $config;
	global $g;

	$apingerstatus = array();
	if(is_readable("{$g['tmp_path']}/apinger.status"))
		$apingerstatus = file("{$g['tmp_path']}/apinger.status");

	$status = array();
	foreach($apingerstatus as $line) {
		$fields = explode(":", $line);
		switch($fields[0]) {
			case "Target":
				$target = trim($fields[1]);
				$status[$target] = array();
				$status[$target]['monitor'] = $target;
				break;
			case "Description":
	 			$status[$target]['name'] = trim($fields[1]);
				break;
			case "Last reply received":
				$status[$target]['lastcheck'] = trim($fields[1]) .":". trim($fields[2]) .":". 
trim($fields[3]);
				break;
			case "Average delay":
				$status[$target]['delay'] = trim($fields[1]);
				break;
			case "Average packet loss":
				$status[$target]['loss'] = trim($fields[1]);
				break;
			case "Active alarms":
				$status[$target]['status'] = trim($fields[1]);
				break;
		}
	}
	return($status);
}

?>
