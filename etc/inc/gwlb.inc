<?php
/* $Id$ */
/*
  Copyright (C) 2008 Bill Marquette, Seth Mos
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
require_once("globals.inc");

/* add static routes for monitor IP addresse
 * creates monitoring configuration file
 */
function setup_gateways_monitor() {
	global $config;
	global $g;
	$gateways_arr = return_gateways_array();

	/* kill apinger process */
	if(is_process_running("apinger"))
		mwexec("/usr/bin/killall apinger", true);
	$fd = fopen("{$g['varetc_path']}/apinger.conf", "w");
	$apingerconfig = <<<EOD

# pfSense apinger configuration file. Automatically Generated!

## User and group the pinger should run as
user "nobody"
group "nobody"

## Mailer to use (default: "/usr/lib/sendmail -t")
#mailer "/var/qmail/bin/qmail-inject" 

## Location of the pid-file (default: "/var/run/apinger.pid")
pid_file "{$g['varrun_path']}/apinger.pid"

## Format of timestamp (%s macro) (default: "%b %d %H:%M:%S")
#timestamp_format "%Y%m%d%H%M%S"

status {
	## File where the status information whould be written to
	file "/tmp/apinger.status"
	## Interval between file updates
	## when 0 or not set, file is written only when SIGUSR1 is received
	interval 10s
}

########################################
# RRDTool status gathering configuration
# Interval between RRD updates
rrd interval 60s;

## These parameters can be overriden in a specific alarm configuration
alarm default { 
	command on "touch /tmp/filter_dirty"
	command off "touch /tmp/filter_dirty"
	combine 10s
}

## "Down" alarm definition. 
## This alarm will be fired when target doesn't respond for 30 seconds.
alarm down "down" {
	time 10s
}

## "Delay" alarm definition. 
## This alarm will be fired when responses are delayed more than 200ms
## it will be canceled, when the delay drops below 100ms
alarm delay "delay" {
	delay_low 200ms
	delay_high 500ms
}

## "Loss" alarm definition. 
## This alarm will be fired when packet loss goes over 20%
## it will be canceled, when the loss drops below 10%
alarm loss "loss" {
	percent_low 10
	percent_high 20
}

target default {
	## How often the probe should be sent	
	interval 1s
	
	## How many replies should be used to compute average delay 
	## for controlling "delay" alarms
	avg_delay_samples 10
	
	## How many probes should be used to compute average loss
	avg_loss_samples 50

	## The delay (in samples) after which loss is computed
	## without this delays larger than interval would be treated as loss
	avg_loss_delay_samples 20

	## Names of the alarms that may be generated for the target
	alarms "down","delay","loss"

	## Location of the RRD
	#rrd file "{$g['vardb_path']}/rrd/apinger-%t.rrd"
}

## Targets to probe
## Each one defined with:
## target <address> { <parameter>... }
## The parameters are those described above in the "target default" section
## plus the "description" parameter.
## the <address> should be IPv4 or IPv6 address (not hostname!)

EOD;

	/* add static routes for each gateway with their monitor IP */
	if(is_array($gateways_arr)) {
		foreach($gateways_arr as $gateway) {
			if($gateway['monitor'] == "") {
				$gateway['monitor'] = $gateway['gateway'];
			}
			$apingerconfig .= "target \"{$gateway['monitor']}\" {\n";
			$apingerconfig .= "	description \"{$gateway['name']}\"\n";
			$apingerconfig .= "	rrd file \"{$g['vardb_path']}/rrd/{$gateway['name']}-quality.rrd\"\n";
			$apingerconfig .= "}\n";
			$apingerconfig .= "\n";
			if($gateway['monitor'] == $gateway['gateway']) {
				/* if the gateway is the same as the monitor we do not add a
				 * route as this will break the routing table */
				continue;
			} else {
				mwexec("/sbin/route delete -host " . escapeshellarg($gateway['monitor']));
				mwexec("/sbin/route add -host " . escapeshellarg($gateway['monitor']) .
					" " . escapeshellarg($gateway['gateway']));
			}
		}
	}
	fwrite($fd, $apingerconfig);
	fclose($fd);

	if(!is_process_running("apinger")) {
		if (is_dir("{$g['tmp_path']}"))
			chmod("{$g['tmp_path']}", 01777);
		if (is_dir("{$g['vardb_path']}/rrd"))
			chown("{$g['vardb_path']}/rrd", "nobody");
		/* start a new apinger process */
		mwexec_bg("/usr/local/sbin/apinger -c {$g['varetc_path']}/apinger.conf");
	}
	return 0;
}

/* return the status of the apinger targets as a array */
function return_gateways_status() {
	global $config;
	global $g;
	$gateways_arr = return_gateways_array();

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
				foreach($gateways_arr as $gateway) {
					if($gateway['monitor'] == "$target") {
						$status[$target]['gateway'] = $gateway['gateway'];
						$status[$target]['interface'] = $gateway['interface'];
					}
				}
				break;
			case "Description":
	 			$status[$target]['name'] = trim($fields[1]);
				break;
			case "Last reply received":
				$status[$target]['lastcheck'] = trim($fields[1]) .":". trim($fields[2]) .":". trim($fields[3]);
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

function return_gateways_array() {
	global $config;
	global $g;
	$gateways_arr = array();

	/* Loop through all interfaces with a gateway and add it to a array */
	$iflist = get_interface_list();
	foreach($iflist as $ifname => $if ) {
		if(interface_has_gateway($ifname)) {
			$gateway = array();
			$friendly = convert_real_interface_to_friendly_interface_name($ifname);
			if(isset($config['interfaces'][$friendly]['gateway'])) {
				$gateway['gateway'] = lookup_gateway_ip_by_name($config['interfaces'][$friendly]['gateway']);
			} else {
				$gateway['gateway'] = get_interface_gateway($ifname);
				echo log_error("Found dynamic gateway '{$gateway['gateway']}'");
			}
			/* Loopback for dynamic interfaces without a IP */
			if(!is_ipaddr(trim($gateway['gateway']))) {
				 $gateway['gateway'] = "127.0.0.2";
			}
			/* do not add dynamic gateways if it is also found in the gateways array */
			if(is_array($config['gateways']['gateway_item'])) {
				foreach($config['gateways']['gateway_item'] as $gateway_item) {
					if($gateway_item['gateway'] == $gateway['gateway'])
						continue 2;
				}
			}

			$gateway['interface'] = $ifname;
			$descr = convert_friendly_interface_to_friendly_descr($friendly);
			/* retrieve a proper monitor IP? */
			if(is_ipaddr($config['interfaces'][$friendly]['monitorip'])) {
				$gateway['monitor'] = $config['interfaces'][$friendly]['monitorip'];
			} else {
				$gateway['monitor'] = $gateway['gateway'];
			}
			$gateway['name'] = "{$friendly}";
			$gateway['descr'] = "Interface {$descr} Gateway";
			$gateway['attribute'] = "system";
			$gateway['interface'] = "$friendly";
			$gateways_arr[] = $gateway;
		}
	}

	/* tack on all the hard defined gateways as well */
	if(is_array($config['gateways']['gateway_item'])) {
		$i = 0;
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($gateway['monitor'] == "") {
				$gateway['monitor'] = $gateway['gateway'];
			}
			/* include the gateway index as the attribute */
			$gateway['attribute'] = "$i";
			$gateways_arr[] = $gateway;
			$i++;
		}
	}
	return($gateways_arr);
}

?>
