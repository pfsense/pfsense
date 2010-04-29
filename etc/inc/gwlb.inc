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

	pfSense_BUILDER_BINARIES:	/usr/bin/killall	/sbin/route	/usr/local/sbin/apinger
	pfSense_MODULE:	routing

  */

/* add static routes for monitor IP addresse
 * creates monitoring configuration file
 */
function setup_gateways_monitor() {
	global $config;
	global $g;
	$gateways_arr = return_gateways_array();
	if (!is_array($config['gateways']['gateway_item']))
		$config['gateways']['gateway_item'] = array();

	$a_gateway_item = &$config['gateways']['gateway_item'];

	if (is_array($config['gateways']['settings'])) {
		$a_settings = &$config['gateways']['settings'];
	} else {
		$a_settings['latencylow'] = "200";
		$a_settings['latencyhigh'] = "500";
		$a_settings['losslow'] = "10";
		$a_settings['losshigh'] = "20";
	}

	/* kill apinger process */
	if(is_process_running("apinger"))
		mwexec("/usr/bin/killall apinger", true);
	$fd = fopen("{$g['varetc_path']}/apinger.conf", "w");
	$apingerconfig = <<<EOD

# pfSense apinger configuration file. Automatically Generated!

## User and group the pinger should run as
user "root"
group "wheel"

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
	command on "/usr/bin/touch /tmp/filter_dirty"
	command off "/usr/bin/touch /tmp/filter_dirty"
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
	delay_low {$a_settings['latencylow']}ms
	delay_high {$a_settings['latencyhigh']}ms
}

## "Loss" alarm definition. 
## This alarm will be fired when packet loss goes over 20%
## it will be canceled, when the loss drops below 10%
alarm loss "loss" {
	percent_low {$a_settings['losslow']}
	percent_high {$a_settings['losshigh']}
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
		$i = 2;
		foreach($gateways_arr as $name => $gateway) {
			/* for dynamic gateways without an IP address we subtitute a local one */
			if((is_numeric($gateway['attribute'])) && is_ipaddr($a_gateway_item[$gateway['attribute']]['monitor'])) {
				$gateway['monitor'] = $a_gateway_item[$gateway['attribute']]['monitor'];
			} else {
				if(($gateway['gateway'] == "dynamic") && ($gateway['monitor'])) {
					$gateway['monitor'] = "127.0.0.{$i}";
					$i++;
				}
				if(!is_ipaddr($gateway['monitor'])) {
					$gateway['monitor'] = $gateway['gateway'];
				}
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
				if(($gateway['gateway'] != "dynamic") && (is_ipaddr($gateway['gateway']))) {
					mwexec("/sbin/route delete -host " . escapeshellarg($gateway['monitor']));
					mwexec("/sbin/route add -host " . escapeshellarg($gateway['monitor']) .
						" " . escapeshellarg($gateway['gateway']));
					log_error("Removing static route for monitor {$gateway['monitor']} and adding a new route through {$gateway['gateway']}");
				}
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
	if(is_readable("{$g['tmp_path']}/apinger.status")) {
		$apingerstatus = file("{$g['tmp_path']}/apinger.status");
	}

	$status = array();
	$i = 2;
	/* assign a dummy IP address for dynamic interfaces in case we need it */
	$monitor = array();
	foreach($gateways_arr as $name => $gateway) {
		if($gateway['monitor'] == "dynamic") {
			$gateway['monitor'] = "127.0.0.{$i}";
			$i++;
		}
		$status[$gateway['monitor']]['monitor'] = $gateway['monitor'];
		$status[$gateway['monitor']]['interface'] = $gateway['interface'];
		$status[$gateway['monitor']]['gateway'] = $gateway['gateway'];
		$status[$gateway['monitor']]['name'] = $gateway['name'];
		$status[$gateway['monitor']]['status'] = "down";

	}
	foreach($apingerstatus as $line) {
		$fields = explode(":", $line);
		switch($fields[0]) {
			case "Target":
				$target = trim($fields[1]);
				break;
			case "Description":
				if($target)
		 			$status[$target]['name'] = trim($fields[1]);
				break;
			case "Last reply received":
				if($target)
					$status[$target]['lastcheck'] = trim($fields[1]) .":". trim($fields[2]) .":". trim($fields[3]);
				break;
			case "Average delay":
				if($target)
					$status[$target]['delay'] = trim($fields[1]);
				break;
			case "Average packet loss":
				if($target)
					$status[$target]['loss'] = trim($fields[1]);
				break;
			case "Active alarms":
				if($target)
					$status[$target]['status'] = trim($fields[1]);
				break;
		}
	}
	return($status);
}

/* Return all configured gateways on the system */
function return_gateways_array($disabled = false) {
	global $config;

	$gateways_arr = array();

	/* Loop through all interfaces with a gateway and add it to a array */
	if ($disabled == false) {
		$iflist = get_configured_interface_with_descr();
	} else {
		$iflist = get_configured_interface_with_descr(false, true);
	}

	$i = 0;
	/* tack on all the hard defined gateways as well */
	if(is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($gateway['gateway'] == "dynamic") {
				$gateway['gateway'] = get_interface_gateway($gateway['interface']);
				/* no IP address found, set to dynamic */
				if(! is_ipaddr($gateway['gateway'])) {
					$gateway['gateway'] = "dynamic";
				}
				$gateway['dynamic'] = true;
			}
			if($gateway['monitor'] == "") {
				$gateway['monitor'] = $gateway['gateway'];
			}
			/* include the gateway index as the attribute */
			$gateway['friendlyiface'] = $gateway['interface'];
			$gateway['interface'] = convert_friendly_interface_to_real_interface_name($gateway['interface']);
			$gateway['attribute'] = "$i";

			$gateways_arr[$gateway['name']] = $gateway;
			$i++;
		}
	} 

	foreach($iflist as $ifname => $friendly ) {
		if(! interface_has_gateway($ifname)) {
			continue;
		}
		$gateway = array();
		$gateway['dynamic'] = false;
		$gateway['gateway'] = get_interface_gateway($ifname, $gateway['dynamic']);
		$gateway['interface'] = get_real_interface($ifname);
		$gateway['friendlyiface'] = $ifname;
		$gateway['name'] = "{$friendly}_GW";
		$gateway['attribute'] = "system";
	
		/* Loopback dummy for dynamic interfaces without a IP */
		if(!is_ipaddr(trim($gateway['gateway'])) && $gateway['dynamic'] == true) {
			 $gateway['gateway'] = "dynamic";
		}

		/* automatically skip known static and dynamic gateways we have a array entry for */
		foreach($gateways_arr as $gateway_item) {
			if($gateway_item['gateway'] == $gateway['gateway'] && ($ifname == $gateway_item['friendlyiface']))
				continue 2;
			if(($gateway_item['gateway'] == "dynamic") && ($ifname == $gateway_item['friendlyiface']))
				continue 2;
		}

		/* retrieve a proper monitor IP? */
		$interface = $config['interfaces'][$ifname];
		if(is_ipaddr($interface['monitorip'])) {
			$gateway['monitor'] = $interface['monitorip'];
		} else {
			$gateway['monitor'] = $gateway['gateway'];
		}
		$gateway['descr'] = "Interface $ifname Dynamic Gateway";
		$gateways_arr[$ifname] = $gateway;
		$i++;
	}

	return($gateways_arr);
}


/* return a array with all gateway groups with name as key
 * All gateway groups will be processed before returning the array.
*/
function return_gateway_groups_array() {
	global $config, $g;

	/* fetch the current gateways status */
	$gateways_status = return_gateways_status();
	$gateways_arr = return_gateways_array();
	$gateway_groups_array = array();

	if (is_array($config['gateways']['gateway_group'])) {
		foreach($config['gateways']['gateway_group'] as $group) {
			/* create array with group gateways members seperated by tier */
			$tiers = array();
			foreach($group['item'] as $item) {
				$itemsplit = explode("|", $item);
				$tier = $itemsplit[1];
				$gwname = $itemsplit[0];
				/* check if the gateway is available before adding it to the array */
				foreach($gateways_status as $status) {
					if(($status['name'] != $gwname)) {
						continue;
					}
					if (stristr($status['status'], "down")) {
						$msg = "MONITOR: $gwname has high latency, removing from routing group";
						log_error($msg);
						notify_via_growl($msg);
					} elseif (stristr($status['status'], "loss")) {
						if (strstr($group['trigger'], "loss")) {
							/* packet loss */
							$msg = "MONITOR: $gwname has packet loss, removing from routing group";
							log_error($msg);
							notify_via_growl($msg);
						} else {
							$tiers[$tier][] = $gwname;
						}
					} elseif (stristr($status['status'], $delay)) {
						if (strstr($group['trigger'] , "latency")) {
							/* high latency */
							$msg = "MONITOR: $gwname has high latency, removing from routing group";
							log_error($msg);
							notify_via_growl($msg);
						} else {
							$tiers[$tier][] = $gwname;
						}
					} elseif (stristr($status['status'], "none")) {
						/* Online add member */
						$tiers[$tier][] = $gwname;
					}
				}
			}
			$tiers_count = count($tiers);
			if($tiers_count == 0) {
				/* Oh dear, we have no members! Engage Plan B */
				$msg = "All gateways are unavailable, proceeding with configured XML settings!";
				log_error($msg);
				notify_via_growl($msg);
				foreach($group['item'] as $item) {
					foreach($group['item'] as $item) {
						$itemsplit = explode("|", $item);
						$tier = $itemsplit[1];
						$gwname = $itemsplit[0];
						$tiers[$tier][] = $gwname;
					}
				}
			}
			/* sort the tiers array by the tier key */
			ksort($tiers);
			/* we do not really foreach the tiers as we stop after the first tier */
			foreach($tiers as $tiernr => $tier) {
				/* process all gateways in this tier */
				$member_count = count($tier);
				foreach($tier as $tiernr => $member) {
					/* determine interface gateway */
					foreach($gateways_arr as $name => $gateway) {
						if($gateway['name'] == $member) {
							$int = $gateway['interface'];
							if(is_ipaddr($gateway['gateway'])) 
								$gatewayip = $gateway['gateway'];
							else 
								$gatewayip = lookup_gateway_ip_by_name($gateway['gateway']);
							break;
						}
					}
					if (($int <> "") && is_ipaddr($gatewayip)) {
						$gateway_groups_array[$group['name']][$tiernr]['int']  = "$int";
						$gateway_groups_array[$group['name']][$tiernr]['gwip']  = "$gatewayip";
					}
				}
				/* we should have the 1st available tier now, exit stage left */
				break;
			}
		}
	}
	return($gateway_groups_array);
}

/* Update DHCP WAN Interface ip address in gateway group item */
function dhclient_update_gateway_groups_defaultroute($interface = "wan") {
	global $config, $g;
	foreach($config['gateways']['gateway_item'] as & $gw) {	
		if($gw['interface'] == $interface) {
			$current_gw = get_interface_gateway($interface);
			if($gw['gateway'] <> $current_gw) {
				$gw['gateway'] = $current_gw;
				$changed = true;
			}
		}
	}
	if($changed && $current_gw)
		write_config("Updating gateway group gateway for $interface - new gateway is $current_gw");
}

function lookup_gateway_ip_by_name($name) {
        global $config;
        if(is_array($config['gateways']['gateway_item'])) {
                foreach($config['gateways']['gateway_item'] as $gateway) {
                        if($gateway['name'] == $name) {
                                $gatewayip = $gateway['gateway'];
                                //$interfacegw = $gateway['interface'];
                                return($gatewayip);
                        }
                }
        } else {
                return(false);
        }
}

function lookup_gateway_monitor_ip_by_name($name) {
        global $config;
        $gateways_arr = return_gateways_array();

	$i = 2;
        foreach($gateways_arr as $gateway) {
		if($gateway['gateway'] == "dynamic") {
			$gateway['monitor'] = "127.0.0.{$i}";
			$i++;
		}
                if($gateway['name'] == "$name") {
                        $monitorip = $gateway['monitor'];
                        if($monitorip == "")
                                $monitorip = $gateway['gateway'];

                        return($monitorip);
                }
        }
        return(false);
}

function lookup_gateway_interface_by_name($name) {
        global $config;
        $gateways_arr = return_gateways_array();

        foreach($gateways_arr as $gateway) {
                if($gateway['name'] == "$name") {
                        $gatewayip = $gateway['gateway'];
                        $interfacegw = $gateway['interface'];
                        return($interfacegw);
                }
        }
        return(false);
}

function get_interface_gateway($interface, &$dynamic = false) {
        global $config, $g;

        $iflist = get_configured_interface_with_descr();
        /*
         * XXX: BUG: This is silly at first, but we may be called with the interface
         *      descr for no apparent reason!!!
         *      Probably one of those silly strtoupper() legacy stuff!
         */
        foreach ($iflist as $ifent => $ifdesc) {
                if ($ifent == $interface || $ifdesc == $interface) {
                        $interface = $ifent;
                        break;
                }
        }

        $gw = NULL;

        $gwcfg = $config['interfaces'][$interface];
        if (is_ipaddr($gwcfg['gateway']))
                $gw = $gwcfg['gateway'];
        else if (!empty($gwcfg['gateway']))
                $gw = lookup_gateway_ip_by_name($gwcfg['gateway']);

        // for dynamic interfaces we handle them through the $interface_router file.
        if (!is_ipaddr($gw) && !is_ipaddr($gwcfg['ipaddr'])) {
                $realif = get_real_interface($interface);
                if (file_exists("{$g['tmp_path']}/{$realif}_router")) {
                        $gw = file_get_contents("{$g['tmp_path']}/{$realif}_router");
                        $gw = rtrim($gw);
			$dynamic = true;
                }
        }

        /* return gateway */
        return $gw;
}

?>
