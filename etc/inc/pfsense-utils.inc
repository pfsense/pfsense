<?php
/****h* pfSense/pfsense-utils
 * NAME
 *   pfsense-utils.inc - Utilities specific to pfSense
 * DESCRIPTION
 *   This include contains various pfSense specific functions.
 * HISTORY
 *   $Id$
 ******
 *
 * Copyright (C) 2004-2006 Scott Ullrich (sullrich@gmail.com)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */
 
 /****f* pfsense-utils/does_url_exist
 * NAME
 *   does_url_exist
 * INPUTS
 *	 none
 * RESULT
 *   returns true if a url is available
 ******/
function does_url_exist($url) {
	$fd = fopen("$url","r");
	if($fd) {
		fclose($fd);
   		return true;    
	} else {
        return false;
	}
}

/****f* pfsense-utils/is_private_ip
 * NAME
 *   is_private_ip
 * INPUTS
 *	 none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck) {
        $isprivate = false;
        $ip_private_list=array(
               "10.0.0.0/8",
               "172.16.0.0/12",
               "192.168.0.0/16",
               "99.0.0.0/8"
        );
        foreach($ip_private_list as $private) {
                if(ip_in_subnet($iptocheck,$private)==true)
                        $isprivate = true;
        }
        return $isprivate;
}

/****f* pfsense-utils/get_tmp_file
 * NAME
 *   get_tmp_file
 * INPUTS
 *	 none
 * RESULT
 *   returns a temporary filename
 ******/
function get_tmp_file() {
	return "/tmp/tmp-" . time();
}

/****f* pfsense-utils/tdr_install_cron
 * NAME
 *   tdr_install_cron
 * INPUTS
 *   $should_install true if the cron entry should be installed, false
 *   if the entry should be removed if it is present
 * RESULT
 *   none
 ******/
function tdr_install_cron($should_install) {
	global $config, $g;
	if($g['booting']==true) 
		return;
	$is_installed = false;
	if(!$config['cron']['item'])
		return;
	$x=0;
	foreach($config['cron']['item'] as $item) {
		if(strstr($item['command'], "filter_configure_sync")) {
			$is_installed = true;
			break;
		}
		$x++;
	}
	switch($should_install) {
		case true:
			if(!$is_installed) {
				$cron_item = array();
				$cron_item['minute'] = "0,15,30,45";
				$cron_item['hour'] = "*";
				$cron_item['mday'] = "*";
				$cron_item['month'] = "*";
				$cron_item['wday'] = "*";
				$cron_item['who'] = "root";
				$cron_item['command'] = "/etc/rc.filter_configure_sync";		
				$config['cron']['item'][] = $cron_item;
				parse_config(true);
				write_config("Installed 15 minute filter reload for Time Based Rules");
				configure_cron();
			}
		break;
		case false:
			if($is_installed == true) {
				if($x > 0) {
					unset($config['cron']['item'][$x]);
					parse_config(true);
					write_config();
				}
				configure_cron();
			}
		break;
	}
}

/****f* pfsense-utils/tdr_create_ipfw_rule
 * NAME
 *   tdr_create_ipfw_rule
 * INPUTS
 *   $rule xml firewall rule array, $type allow or deny
 * RESULT
 *   text string with ipfw rule already formatted
 ******/
function tdr_create_ipfw_rule($rule, $type) {
		global $config, $g, $tdr_get_next_ipfw_rule;

		$wancfg = $config['interfaces']['wan'];
		$lancfg = $config['interfaces']['lan'];
		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];

		$lanif = $lancfg['if'];
		$wanif = get_real_wan_interface();

		$lanip = $lancfg['ipaddr'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
		$lansn = $lancfg['subnet'];

		$int = "";

		$optcfg = array();
		generate_optcfg_array($optcfg);

		$curwanip = get_current_wan_address();

		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			return "";
		}

		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];

		if ($pptpdcfg['mode'] == "server") {
			$pptpip = $pptpdcfg['localip'];
			$pptpsa = $pptpdcfg['remoteip'];
			$pptpsn = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptpsn = $config['pptp']['pptp_subnet'];
		}

		if ($pppoecfg['mode'] == "server") {
			$pppoeip = $pppoecfg['localip'];
			$pppoesa = $pppoecfg['remoteip'];
			$pppoesn = $g['pppoe_subnet'];
			if($config['pppoe']['pppoe_subnet'] <> "")
				$pppoesn = $config['pppoe']['pppoe_subnet'];
		}

		/* does the rule deal with a PPTP interface? */
		if ($rule['interface'] == "pptp") {
			if ($pptpdcfg['mode'] != "server")
				return "";
			$nif = $g['n_pptp_units'];
			if($config['pptp']['n_pptp_units'] <> "")
				$nif = $config['pptp']['n_pptp_units'];
			$ispptp = true;
		} else if($rule['interface'] == "pppoe") {
			if ($pppoecfg['mode'] != "server") {
				return " # Error creating pppoe rule";
			}
			$nif = $g['n_pppoe_units'];
			if($config['pppoe']['n_pppoe_units'] <> "")
				$nif = $config['pppoe']['n_pppoe_units'];
			$ispppoe = true;
		} else {

			/* Check to see if the interface is opt and in our opt list */
			if (strstr($rule['interface'], "opt")) {
 				if (!array_key_exists($rule['interface'], $optcfg)) {
					$item = "";
					foreach($optcfg as $oc) $item .= $oc['if'];
					return "# {$real_int} {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
				}
			}

			$nif = 1;
			$ispptp = false;
			$ispppoe = false;
		}
		if ($pptpdcfg['mode'] != "server") {
			if (($rule['source']['network'] == "pptp") ||
				($rule['destination']['network'] == "pptp")) {
					return "# source network or destination network == pptp on " . $rule['descr'];
				}
		}
		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				$optmatch = "";
				if(preg_match("/opt([0-999])/", $rule['source']['network'], $optmatch)) {
					$real_opt_int = convert_friendly_interface_to_real_interface_name("opt" . $optmatch[1]);
					$opt_ip = find_interface_ip($real_opt_int);
					if(!$opt_ip)
						return "# unresolvable optarray $real_opt_int - $optmatch[0] - $opt_ip";
				} else {
					return "# {$rule['source']['network']} !array_key_exists source network " . $rule['descr'];
				}
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $optcfg)) {
				if(preg_match("/opt([0-999])/", $rule['destination']['network'], $optmatch)) {
					$real_opt_int = convert_friendly_interface_to_real_interface_name("opt" . $optmatch[1]);
					$opt_ip = find_interface_ip($real_opt_int);
					if(!$opt_ip)
						return "# unresolvable oparray $real_opt_int - $optmatch[0] - $opt_ip";
				} else {
					return "# {$item} {$rule['destination']['network']} !array_key_exists dest network " . $rule['descr'];
				}
			}
		}
		/* check for unresolvable aliases */
		if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
			file_notice("Filter_Reload", "# unresolvable source aliases {$rule['descr']}");
			return "# tdr unresolvable source aliases {$rule['descr']}";
		}
		if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
			file_notice("Filter_Reload", "# unresolvable dest aliases {$rule['descr']}");
			return "# tdr unresolvable dest aliases {$rule['descr']}";
		}

		$ifdescrs = array();
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
			$ifdescrs[] = "opt" . $i;

		for ($iif = 0; $iif < $nif; $iif++) {

			if ($ispptp) {
				$aline['interface'] = "\$pptp ";
			} else if ($ispppoe) {
				$aline['interface'] = "\$pppoe ";
			} else {
				// translate wan, man, lan, opt to real interface.
				$interface = $rule['interface'];
				$temp = filter_get_opt_interface_descr($interface);
				if($temp <> "") $interface = $temp;
				$aline['interface'] = convert_friendly_interface_to_real_interface_name($rule['interface']) . " ";
			}

			if (isset($rule['protocol'])) {
				if($rule['protocol'] == "tcp/udp")
					$aline['prot'] = "ip ";
				elseif($rule['protocol'] == "icmp")
					$aline['prot'] = "icmp ";
				else
					$aline['prot'] = "{$rule['protocol']} ";
			} else {
				if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "") {
					$aline['prot'] = "tcp ";
				}
			}

			/* source address */
			if (isset($rule['source']['any'])) {
				$src = "any";
			} else if ($rule['source']['network']) {

				if (strstr($rule['source']['network'], "opt")) {
					$src = $optcfg[$rule['source']['network']]['sa'] . "/" .
						$optcfg[$rule['source']['network']]['sn'];
					if (isset($rule['source']['not'])) $src = " not {$src}";
					/* check for opt$NUMip here */
					$matches = "";
					if (preg_match("/opt([0-9999])ip/", $rule['source']['network'], $matches)) {
						$optnum = $matches[1];
						$real_int = convert_friendly_interface_to_real_interface_name("opt{$optnum}");
						$src = find_interface_ip($real_int);
					}
				} else {
					switch ($rule['source']['network']) {
						case 'wanip':
							$src = $curwanip;
							break;
						case 'lanip':
							$src = $lanip;
							break;
						case 'lan':
							$src = "{$lansa}/{$lansn}";
							break;
						case 'pptp':
							$src = "{$pptpsa}/{$pptpsn}";
							break;
						case 'pppoe':
							$src = "{$pppoesa}/{$pppoesn}";
							break;
					}
					if (isset($rule['source']['not'])) $src = " not {$src}";
				}
			} else if ($rule['source']['address']) {
				$expsrc = alias_expand_value($rule['source']['address']);
				if(!$expsrc) 
					$expsrc = $rule['source']['address'];
					
				if (isset($rule['source']['not']))
					$not = " not";
				else
					$not = "";

				if(alias_expand_value($rule['source']['address'])) {
					$src = "{";
					$first_item = true;
					foreach(preg_split("/[\s]+/", alias_expand_value($rule['source']['address'])) as $item) {
						if($item != "") {
							if(!$first_item) 
								$src .= " or";
							$src .= " {$not}{$item}";
							$first_item = false;
						}
					}
					$src .= " }";
				} else {
					$src = "{$not}" . $expsrc;
				}

			}

			if (!$src || ($src == "/")) {
				return "# tdr at the break!";
			}

			$aline['src'] = "from $src ";
			$srcporta = "";
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);
					if(alias_expand($srcport[0])) {
						$first_time = true;
						foreach(preg_split("/[\s]+/", alias_expand_value($srcport[0])) as $item) {
							if(!$first_time) 
								$srcporta .= ",";				
							$srcporta .= $item;
							$first_time = false;
						}
					} else {
						$srcporta = $srcport[0];
					}
					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						if(alias_expand($srcport[0]))
							$aline['srcport'] = "{$srcporta} ";
						else
							$aline['srcport'] = "{$srcporta} ";
					} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($srcport[1] == 65535) {
						$aline['srcport'] = ">={$srcport[0]} ";
					} else if ($srcport[0] == 1) {
						$aline['srcport']= "<={$srcport[1]} ";
					} else {
						$aline['srcport'] = "{$srcport[0]}-{$srcport[1]} ";
					}
				}
			}

			/* destination address */
			if (isset($rule['destination']['any'])) {
				$dst = "any";
			} else if ($rule['destination']['network']) {

				if (strstr($rule['destination']['network'], "opt")) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
					/* check for opt$NUMip here */
					$matches = "";
					if (preg_match("/opt([0-9999])ip/", $rule['destination']['network'], $matches)) {
						$optnum = $matches[1];
						$real_int = convert_friendly_interface_to_real_interface_name("opt{$optnum}");
						$dst = find_interface_ip($real_int);
					}
					if (isset($rule['destination']['not'])) $dst = " not {$dst}";
				} else {
					switch ($rule['destination']['network']) {
						case 'wanip':
							$dst = $curwanip;
							break;
						case 'lanip':
							$dst = $lanip;
							break;
						case 'lan':
							$dst = "{$lansa}/{$lansn}";
							break;
						case 'pptp':
							$dst = "{$pptpsa}/{$pptpsn}";
							break;
						case 'pppoe':
							$dst = "{$ppoesa}/{$pppoesn}";
							break;
					}
					if (isset($rule['destination']['not'])) $dst = " not {$dst}";
				}
			} else if ($rule['destination']['address']) {
				$expdst = alias_expand_value($rule['destination']['address']);
				if(!$expdst) 
					$expdst = $rule['destination']['address'];

				if (isset($rule['destination']['not']))
					$not = " not ";
				else
					$not = "";

				if(alias_expand_value($rule['destination']['address'])) {
					$dst = "{";
					$first_item = true;
					foreach(preg_split("/[\s]+/", alias_expand_value($rule['destination']['address'])) as $item) {
						if($item != "") {
							if(!$first_item) 
								$dst .= " or";
							$dst .= " {$not}{$item}";
							$first_item = false;
						}
					}
					$dst .= " }";
				} else {
					$dst = "{$not}" . $expdst;
				}
			}

			if (!$dst || ($dst == "/")) {
				return "# returning at dst $dst == \"/\"";
			}

			$aline['dst'] = "to $dst ";
			$dstporta = "";
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);
					if(alias_expand($dstport[0])) {
						$first_time = true;
						foreach(preg_split("/[\s]+/", alias_expand_value($dstport[0])) as $item) {
							if(!$first_time)
							 	$dstporta .= ",";
							$dstporta .= $item;			
							$first_time = false;
						}
					} else {
						$dstporta = $dstport[0];
					}
					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						if(alias_expand($dstport[0]))
							$aline['dstport'] = "{$dstporta} ";
						else
							$aline['dstport'] = "{$dstporta} ";
					} else if (($dstport[0] == 1) && ($dstport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($dstport[1] == 65535) {
						$aline['dstport'] = ">= {$dstport[0]} ";
					} else if ($dstport[0] == 1) {
						$aline['dstport'] = "<= {$dstport[1]} ";
					}  else {
						$aline['dstport'] = "{$dstport[0]}-{$dstport[1]} ";
					}
				}
			}

	}
	
	if($aline['prot'] == "")
		$aline['prot'] = "ip ";

	tdr_get_next_ipfw_rule();

 	/* piece together the actual user rule */
	if($type == "skipto") {
		$next_rule = tdr_get_next_ipfw_rule();
		$next_rule = $next_rule+1;
		$type = "skipto $next_rule";
	}

	/* piece together the actual user rule */
	$line .= $type . " " . $aline['prot'] . $aline['src'] . 
	$aline['srcport'] . $aline['dst'] . $aline['dstport'] . " in recv " . $aline['interface'];

	return $line;

}

/****f* pfsense-utils/tdr_install_rule
 * NAME
 *   tdr_install_rule
 * INPUTS
 *   $rule - ascii string containing the ifpw rule to add
 * RESULT
 *   none
 ******/
function tdr_install_rule($rule) {
	global $tdr_next_ipfw_rule;
	mwexec("/sbin/ipfw -f add $tdr_next_ipfw_rule set 9 $rule");
	$tdr_next_ipfw_rule++;
}

/****f* pfsense-utils/tdr_get_next_ipfw_rule
 * NAME
 *   tdr_get_next_ipfw_rule
 * INPUTS
 *  none
 * RESULT
 *   returns the next available ipfw rule number
 ******/
function tdr_get_next_ipfw_rule() {
	global $tdr_next_ipfw_rule;
	if(intval($tdr_next_ipfw_rule) < 2) 
		$tdr_next_ipfw_rule = 2;
	return $tdr_next_ipfw_rule;
 }

/****f* pfsense-utils/tdr_install_set
 * NAME
 *   tdr_install_set
 * INPUTS
 *  none
 * RESULT
 *   swaps in the temporary ipfw time based rule set
 ******/
function tdr_install_set() {
	global $config;
	
	mwexec("/sbin/ipfw delete 1");
	mwexec("/sbin/ipfw add 1 check-state");
	mwexec("/sbin/ipfw delete 65534");
	mwexec("/sbin/ipfw add 1 allow all from me to any keep-state");
	if (!isset ($config['system']['webgui']['noantilockout'])) {
		/* lan ip lockout */
		$lancfg = $config['interfaces']['lan'];
		$lanip = $lancfg['ipaddr'];
		$lansn = $lancfg['subnet'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
		mwexec("/sbin/ipfw add 1 allow all from {$lansa}/{$lansn} to $lanip keep-state");
	}
	mwexec("/sbin/ipfw add 65534 check-state");
	/* set 8 contains time based rules */
	mwexec("/sbin/ipfw -f delete set 8");
	mwexec("/sbin/ipfw -f set swap 9 8");
}

/****f* pfsense-utils/get_time_based_rule_status
 * NAME
 *   get_time_based_rule_status
 * INPUTS
 *   xml schedule block
 * RESULT
 *   true/false - true if the rule should be installed
 ******/
/*
 <schedules>
   <schedule>
     <name>ScheduleMultipleTime</name>
     <descr>main descr</descr>
     <time>
       <position>0,1,2</position>
       <hour>0:0-24:0</hour>
       <desc>time range 2</desc>
     </time>
     <time>
       <position>4,5,6</position>
       <hour>0:0-24:0</hour>
       <desc>time range 1</desc>
     </time>
   </schedule>
 </schedules>
*/
function get_time_based_rule_status($schedule) {
	$should_add_rule = false;
	global $debug;
	/* no schedule? rule should be installed */
	if($schedule == "") 
		return true;
	/*
	 * iterate through time blocks and deterimine
	 * if the rule should be installed or not.
	 */
	foreach($schedule['timerange'] as $timeday) {
		if($timeday['month']) 
			$month = $timeday['month'];
		else 
			$week = "";	
		if($timeday['day']) 
			$day = $timeday['day'];
		else 
			$day = "";
		if($timeday['hour']) 
			$hour = $timeday['hour'];
		else 
			$hour = "";
		if($timeday['position']) 
			$position = $timeday['position'];
		else 
			$position = "";
		if($timeday['desc']) 
			$desc = $timeday['desc'];
		else 
			$desc = "";
		if($month) {
			$monthstatus = tdr_month($month);
		} else {
			$monthstatus = true;
		}
		if($day) {
			$daystatus = tdr_day($day);
		} else {
			$daystatus = true;
		}
		if($hour) {
			$hourstatus = tdr_hour($hour);
		} else {
			$hourstatus = true;
		}
		if($position) {
			$positionstatus = tdr_position($position);
		} else {
			$positionstatus = true;
		}

		if($monthstatus == true) 
			if($daystatus == true) 
				if($positionstatus == true) 
					if($hourstatus == true) {
						$should_add_rule = true;
					}
	}
	
	return $should_add_rule;
}

function tdr_day($schedule) {
	/*
	 * Calculate day of month. 
	 * IE: 29th of may
	 */
	global $debug;
	$weekday	= date("w");
	if ($weekday == 0)
		$weekday = 7;
	$date	 	= date("d");
	$defined_days = split(",", $schedule);
	if($g['debug'])
		log_error("[TDR DEBUG] tdr_day($schedule)");
	foreach($defined_days as $dd) {
		if($date == $dd) {
			return true;
		}
	}
	return false;
}

function tdr_hour($schedule) {
	/* $schedule should be a string such as 16:00-19:00 */
	global $debug;
	$tmp = split("-", $schedule);
	$starting_time = strtotime($tmp[0]);
	$ending_time = strtotime($tmp[1]);
	$now = strtotime("now");
	if($g['debug'])
		log_error("[TDR DEBUG] S: $starting_time E: $ending_time N: $now");
	if($now >= $starting_time and $now <= $ending_time) {
		return true;
	}
	return false;
}

function tdr_position($schedule) {
	/*
	 * Calculate possition, ie: day of week.
	 * Sunday = 7, Monday = 1, Tuesday = 2
	 * Weds = 3, Thursday = 4, Friday = 5,
	 * Saturday = 6
	 * ...
	 */
	global $debug;
	$weekday	= date("w");
	if($g['debug'])
		log_error("[TDR DEBUG] tdr_position($schedule) $weekday");
	if ($weekday == 0)
		$weekday = 7;
	$schedule_days = split(",", $schedule);
	foreach($schedule_days as $day) {
		if($day == $weekday) {
			return true;
		}
	}
	return false;
}

function tdr_month($schedule) {
	/*
	 * Calculate month
	 */
	global $debug;
	$todays_month = date("n");
	$months = split(",", $schedule);
	if($g['debug'])
		log_error("[TDR DEBUG] tdr_month($schedule)");
	foreach($months as $month) {
		if($month == $todays_month) {
			return true;
		}
	}
	return false;
}

/****f* pfsense-utils/find_number_of_needed_carp_interfaces
 * NAME
 *   find_number_of_needed_carp_interfaces
 * INPUTS
 *   null
 * RESULT
 *   the number of needed carp interfacs
 ******/
function find_number_of_needed_carp_interfaces() {
	global $config, $g;
	$carp_counter=0;
	if(!$config['virtualip'])
		return 0;
	if(!$config['virtualip']['vip'])
		return 0;
	foreach($config['virtualip']['vip'] as $vip) {
		if($vip['mode'] == "carp")
			$carp_counter++;
	}
	return $carp_counter;
}

/****f* pfsense-utils/reset_carp
 * NAME
 *   reset_carp - resets carp after primary interface changes
 * INPUTS
 *   null
 * RESULT
 *   null
 ******/
function reset_carp() {
	$carp_counter=find_number_of_created_carp_interfaces();
	$needed_carp_interfaces = find_number_of_needed_carp_interfaces();
	mwexec("/sbin/sysctl net.inet.carp.allow=0");
	for($x=0; $x<$carp_counter; $x++) {
		mwexec("/sbin/ifconfig carp{$x} down");
		usleep(1000);
		mwexec("/sbin/ifconfig carp{$x} delete");
		if($needed_carp_interfaces < $carp_counter) {
			$needed_carp_interfaces--;
			//log_error("Destroying carp interface.");
			//mwexec("/sbin/ifconfig carp{$x} destroy");
		}
	}
	find_number_of_created_carp_interfaces(true);
	sleep(1);
	mwexec("/sbin/sysctl net.inet.carp.allow=1");
	interfaces_carp_configure();
	usleep(1000);
	interfaces_carp_bring_up_final();
}

/****f* pfsense-utils/get_dns_servers
 * NAME
 *   get_dns_servres - get system dns servers
 * INPUTS
 *   $dns_servers - an array of the dns servers
 * RESULT
 *   null
 ******/
function get_dns_servers() {
	$dns_servers = array();
	$dns = `cat /etc/resolv.conf`;
	$dns_s = split("\n", $dns);
	foreach($dns_s as $dns) {
		$matches = "";
		if (preg_match("/nameserver (.*)/", $dns, $matches))
			$dns_servers[] = $matches[1];
	}
	$dns_server_master = array();
	$lastseen = "";
	foreach($dns_servers as $t) {
		if($t <> $lastseen)
			if($t <> "")
				$dns_server_master[] = $t;
		$lastseen = $t;
	}
	return $dns_server_master;
}

/****f* pfsense-utils/log_error
* NAME
*   log_error  - Sends a string to syslog.
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_error($error) {
	$page = $_SERVER['SCRIPT_NAME'];
	syslog(LOG_WARNING, "$page: $error");
	return;
}

/****f* pfsense-utils/get_interface_mac_address
 * NAME
 *   get_interface_mac_address - Return a interfaces mac address
 * INPUTS
 *   $interface	- interface to obtain mac address from
 * RESULT
 *   $mac - the mac address of the interface
 ******/
function get_interface_mac_address($interface) {
	$mac = exec("ifconfig {$interface} | awk '/ether/ {print $2}'");
	if(is_macaddr($mac)) {
		return trim($mac);
	} else {
		return "";
	}
}

/****f* pfsense-utils/setup_glxsb
 * NAME
 *   setup_glxsb - loads or unloads glxsb module as needed
 * INPUTS
 *   null
 * RESULT
 *   null
 ******/
function setup_glxsb() {
        global $config, $g;
        $is_loaded = `/sbin/kldstat | /usr/bin/grep -c glxsb`;
        if (!isset($config['system']['disableglxsb']) && ($is_loaded == 0)) {
                mwexec("/sbin/kldload glxsb");
        } elseif (isset($config['system']['disableglxsb']) && ($is_loaded > 0)) {
                mwexec("/sbin/kldunload glxsb");
        }
        
}

/****f* pfsense-utils/return_dir_as_array
 * NAME
 *   return_dir_as_array - Return a directory's contents as an array.
 * INPUTS
 *   $dir	- string containing the path to the desired directory.
 * RESULT
 *   $dir_array - array containing the directory's contents. This array will be empty if the path specified is invalid.
 ******/
function return_dir_as_array($dir) {
	$dir_array = array();
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				$canadd = 0;
				if($file == ".") $canadd = 1;
				if($file == "..") $canadd = 1;
				if($canadd == 0)
					array_push($dir_array, $file);
			}
			closedir($dh);
		}
	}
	return $dir_array;
}

/****f* pfsense-utils/enable_hardware_offloading
 * NAME
 *   enable_hardware_offloading - Enable a NIC's supported hardware features.
 * INPUTS
 *   $interface	- string containing the physical interface to work on.
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function enable_hardware_offloading($interface) {
	global $g, $config;

	if(stristr($interface,"lnc"))
		return;

	/* translate wan, lan, opt -> real interface if needed */
	$int = filter_translate_type_to_real_interface($interface);
	if($int <> "") $interface = $int;
	$int_family = preg_split("/[0-9]+/", $int);
	$options = strtolower(`/sbin/ifconfig -m {$interface} | grep capabilities`);
	$supported_ints = array('fxp');
	if (in_array($int_family, $supported_ints)) {
        	if(isset($config['system']['do_not_use_nic_microcode']))
                        continue;
		mwexec("/sbin/ifconfig {$interface} link0");
        }

	/* skip vlans for checksumming and polling */
	if(stristr($interface, "vlan")) 
		return;

	if($config['system']['disablechecksumoffloading']) {
                if(stristr($options, "txcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} -txcsum 2>/dev/null");
                if(stristr($options, "rxcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} -rxcsum 2>/dev/null");
	} else {
               	if(stristr($options, "txcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} txcsum 2>/dev/null");
        	if(stristr($options, "rxcsum") == true)
                        mwexec("/sbin/ifconfig {$interface} rxcsum 2>/dev/null");
        }

	/* if the NIC supports polling *AND* it is enabled in the GUI */
	if(interface_supports_polling($interface)) {
		$polling = isset($config['system']['polling']);	
		if($polling) {
			mwexec("sysctl kern.polling.enable=1");
                        mwexec("/sbin/ifconfig {$interface} polling 2>/dev/null");
		} else {
			mwexec("sysctl kern.polling.enable=0");
		}
	}
	return;
}

/****f* pfsense-utils/interface_supports_polling
 * NAME
 *   checks to see if an interface supports polling according to man polling
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function interface_supports_polling($iface) {
	$pattern = '/([a-z].*)[0-9]/';
	preg_match($pattern, $iface, $iface2);
	$interface=$iface2[1];
	$supported_ints = array("bge",
		"dc",
		"em",
		"fwe",
		"fwip",
		"fxp",
		"ixgb",
		"nfe",
		"vge",
		"re",
		"rl",
		"sf",
		"sis",
		"ste",
		"stge",    
		"vge",
		"vr",
		"xl");
	if(in_array($interface, $supported_ints))
		return true;
	return false;
}

/****f* pfsense-utils/is_alias_inuse
 * NAME
 *   checks to see if an alias is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_alias_inuse($alias) {
	global $g, $config;

	if($alias == "") return false;
	/* loop through firewall rules looking for alias in use */
	if(is_array($config['filter']['rule']))
		foreach($config['filter']['rule'] as $rule) {
			if($rule['source']['address'])
				if($rule['source']['address'] == $alias)
					return true;
			if($rule['destination']['address'])
				if($rule['destination']['address'] == $alias)
					return true;
		}
	/* loop through nat rules looking for alias in use */
	if(is_array($config['nat']['rule']))
		foreach($config['nat']['rule'] as $rule) {
			if($rule['target'] == $alias)
				return true;
			if($rule['external-address'] == $alias)
				return true;
		}
	return false;
}

/****f* pfsense-utils/is_schedule_inuse
 * NAME
 *   checks to see if a schedule is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_schedule_inuse($schedule) {
	global $g, $config;

	if($schedule == "") return false;
	/* loop through firewall rules looking for schedule in use */
	if(is_array($config['filter']['rule']))
		foreach($config['filter']['rule'] as $rule) {
			if($rule['sched'] == $schedule)
				return true;
		}
	return false;
}

/****f* pfsense-utils/setup_polling_defaults
 * NAME
 *   sets up sysctls for pollingS
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *
 ******/
function setup_polling_defaults() {
	global $g, $config;
	if($config['system']['polling_each_burst'])
		mwexec("sysctl kern.polling.each_burst={$config['system']['polling_each_burst']}");
	if($config['system']['polling_burst_max'])
		mwexec("sysctl kern.polling.burst_max={$config['system']['polling_burst_max']}");
	if($config['system']['polling_user_frac'])
		mwexec("sysctl kern.polling.user_frac={$config['system']['polling_user_frac']}");
}

/****f* pfsense-utils/setup_polling
 * NAME
 *   sets up polling
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *
 ******/
function setup_polling() {
	global $g, $config;

	setup_polling_defaults();

	$supported_ints = array('bge', 'dc', 'em', 'fwe', 'fwip', 'fxp', 'ixgb', 'nfe', 'nge', 're', 'rl', 'sf', 'sis', 'ste', 'stge', 'vge', 'vr', 'xl');

	/* build an array of interfaces to work with */
	$iflist = array("lan" => "LAN", "wan" => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];

	foreach ($iflist as $ifent => $ifname) {
		$real_interface = convert_friendly_interface_to_real_interface_name($ifname);
		$ifdevice = substr($real_interface, 0, -1); 
		if(!in_array($ifdevice, $supported_ints)) {
			continue;
 		}
		if(isset($config['system']['polling'])) {
			mwexec("/sbin/ifconfig {$real_interface} polling");
			mwexec("/sbin/sysctl kern.polling.idle_poll=1");
		} else {
			mwexec("/sbin/ifconfig {$real_interface} -polling");
		}
	}
}

/****f* pfsense-utils/setup_microcode
 * NAME
 *   enumerates all interfaces and calls enable_hardware_offloading which
 *   enables a NIC's supported hardware features.
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function setup_microcode() {
	global $config;

	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach($ifdescrs as $if)
		enable_hardware_offloading($if);
}

/****f* pfsense-utils/return_filename_as_array
 * NAME
 *   return_filename_as_array - Return a file's contents as an array.
 * INPUTS
 *   $filename	- string containing the path to the desired file.
 *   $strip	- array of characters to strip - default is '#'.
 * RESULT
 *   $file	- array containing the file's contents.
 * NOTES
 *   This function strips lines starting with '#' and leading/trailing whitespace by default.
 ******/
function return_filename_as_array($filename, $strip = array('#')) {
	if(file_exists($filename)) $file = file($filename);
	if(is_array($file)) {
		foreach($file as $line) $line = trim($line);
		foreach($strip as $tostrip) $file = preg_grep("/^{$tostrip}/", $file, PREG_GREP_INVERT);
	}
	return $file;
}

/****f* pfsense-utils/file_put_contents
 * NAME
 *   file_put_contents - Wrapper for file_put_contents if it doesn't exist
 * RESULT
 *   none
 ******/
if(!function_exists("file_put_contents")) {
	function file_put_contents($filename, $data) {
		$fd = fopen($filename,"w");
		fwrite($fd, $data);
		fclose($fd);
	}
}

/****f* pfsense-utils/get_carp_status
 * NAME
 *   get_carp_status - Return whether CARP is enabled or disabled.
 * RESULT
 *   boolean	- true if CARP is enabled, false if otherwise.
 ******/
function get_carp_status() {
    /* grab the current status of carp */
    $status = `/sbin/sysctl net.inet.carp.allow | cut -d" " -f2`;
    if(intval($status) == "0") return false;
    return true;
}

/****f* pfsense-utils/is_carp_defined
 * NAME
 *   is_carp_defined - Return whether CARP is detected in the kernel.
 * RESULT
 *   boolean	- true if CARP is detected, false otherwise.
 ******/
function is_carp_defined() {
	/* is carp compiled into the kernel and userland? */
	$command = "/sbin/sysctl -a | grep carp";
	$fd = popen($command . " 2>&1 ", "r");
	if(!$fd) {
		log_error("Warning, could not execute command {$command}");
		return 0;
	}
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);

	if($tmp == "")
		return false;
	else
		return true;
}

/****f* pfsense-utils/get_interface_mtu
 * NAME
 *   get_interface_mtu - Return the mtu of an interface
 * RESULT
 *   $tmp	- Returns the mtu of an interface
 ******/
function get_interface_mtu($interface) {
	$mtu = `/sbin/ifconfig {$interface} | /usr/bin/grep mtu | /usr/bin/cut -d" " -f6`;
	return $mtu;
}

/****f* pfsense-utils/is_interface_wireless
 * NAME
 *   is_interface_wireless - Returns if an interface is wireless
 * RESULT
 *   $tmp	- Returns if an interface is wireless
 ******/
function is_interface_wireless($interface) {
	global $config, $g;
	$interface = convert_real_interface_to_friendly_interface_name($interface);
	if(isset($config['interfaces'][$interface]['wireless']))
		return true;
	else
		return false;
}

/****f* pfsense-utils/find_number_of_created_carp_interfaces
 * NAME
 *   find_number_of_created_carp_interfaces - Return the number of CARP interfaces.
 * RESULT
 *   $tmp	- Number of currently created CARP interfaces.
 ******/
function find_number_of_created_carp_interfaces($flush = false) {
	global $carp_interface_count_cache;

	if (!isset($carp_interface_count_cache) or $flush) {
		$command = "/sbin/ifconfig | /usr/bin/grep \"carp*:\" | /usr/bin/wc -l";
		$fd = popen($command . " 2>&1 ", "r");
		if(!$fd) {
			log_error("Warning, could not execute command {$command}");
			return 0;
		}
		while(!feof($fd)) {
			$tmp .= fread($fd,49);
		}
		fclose($fd);
		$carp_interface_count_cache = intval($tmp);
	}
	return $carp_interface_count_cache;
}

/****f* pfsense-utils/link_int_to_bridge_interface
 * NAME
 *   link_int_to_bridge_interface - Finds out a bridge group for an interface
 * INPUTS
 *   $ip
 * RESULT
 *   bridge[0-99]
 ******/
function link_int_to_bridge_interface($int) {
	global $config, $g;
	$real_int = convert_friendly_interface_to_real_interface_name($int);
	$num_bridges = find_number_of_created_bridges();
	for($x=0; $x<$num_bridges; $x++) {
		$matches = "";
		$bridge_info = `/sbin/ifconfig bridge{$x}`;
		if(stristr($bridge_info, "member: {$real_int}")) {
			return "bridge{$x}";
		}
	}
}

function link_carp_interface_to_parent($interface) {
	global $config;
	if($interface == "") return;

	$ifdescrs = array("wan" => "wan", "lan" => "lan");
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;

	$carp_int = $interface;
	$carp_ip = find_interface_ip($interface);
	$carp_subnet = find_virtual_ip_netmask($carp_ip);
	$starting_ip = gen_subnet("{$carp_ip}", "{$carp_subnet}");
	$carp_ints = "";
	$num_carp_ints = find_number_of_created_carp_interfaces();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		if(interface_has_gateway($ifname)) {
			$interfaceip = $config['interfaces'][$ifname]['ipaddr'];
			$subnet_bits = $config['interfaces'][$ifname]['subnet'];
			$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
			if(ip_in_subnet($carp_ip, "{$subnet_ip}/{$subnet_bits}")) {
				return $ifname;
			}
		}
	}
	return $carp_ints;
}

/****f* pfsense-utils/link_ip_to_carp_interface
 * NAME
 *   link_ip_to_carp_interface - Find where a CARP interface links to.
 * INPUTS
 *   $ip
 * RESULT
 *   $carp_ints
 ******/
function link_ip_to_carp_interface($ip) {
	global $config;
	if($ip == "") return;

	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;

	$ft = split("\.", $ip);
	$ft_ip = $ft[0] . "." . $ft[1] . "." . $ft[2] . ".";

	$carp_ints = "";
	$num_carp_ints = find_number_of_created_carp_interfaces();
	foreach ($ifdescrs as $ifdescr => $ifname) {
		for($x=0; $x<$num_carp_ints; $x++) {
			$carp_int = "carp{$x}";
			$carp_ip = find_interface_ip($carp_int);
			$carp_subnet = find_virtual_ip_netmask($carp_ip);
			$starting_ip = gen_subnet("{$carp_ip}", "{$carp_subnet}");
			if(ip_in_subnet($ip, "{$starting_ip}/{$carp_subnet}"))
				if(!stristr($carp_ints, $carp_int))
					$carp_ints .= " " . $carp_int;
		}
	}
	return $carp_ints;
}

/****f* pfsense-utils/find_virtual_ip_netmask
 * NAME
 *   find_virtual_ip_netmask - Finds a virtual ip's subnet mask'
 * INPUTS
 *   $ip - ip address to locate subnet mask of
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function find_virtual_ip_netmask($ip) {
        global $config;
        foreach($config['virtualip']['vip'] as $vip) {
                if($ip == $vip['subnet'])
                        return $vip['subnet_bits'];
        }
}

/****f* pfsense-utils/exec_command
 * NAME
 *   exec_command - Execute a command and return a string of the result.
 * INPUTS
 *   $command	- String of the command to be executed.
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function exec_command($command) {
	$output = array();
	exec($command . ' 2>&1 ', $output);
	return(implode("\n", $output));
}

/****f* interfaces/is_jumbo_capable
 * NAME
 *   is_jumbo_capable - Test if interface is jumbo frame capable.  Useful for determining VLAN capability.
 * INPUTS
 *   $int             - string containing interface name
 * RESULT
 *   boolean          - true or false
 ******/
function is_jumbo_capable($int) {
	/* Per:
	 * http://www.freebsd.org/cgi/man.cgi?query=vlan&manpath=FreeBSD+6.0-RELEASE&format=html
	 * Only the following drivers support large frames
         *
	 * 'de' chipset purposely left out of this list
	 * requires defining BIG_PACKET in the
	 * /usr/src/sys/pci/if_de.c source file and rebuilding the
	 * kernel or module.  The hack works only for the 21041,
	 * 21140, and 21140A chips.
	 */
	global $g;

	$capable = $g['vlan_long_frame'];

	$int_family = preg_split("/[0-9]+/", $int);

	if (in_array($int_family[0], $capable))
		return true;
	else
		return false;
}

/*
 * Return the interface array
 */
function get_interface_arr($flush = false) {
	global $interface_arr_cache;

	/* If the cache doesn't exist, build it */
	if (!isset($interface_arr_cache) or $flush)
		$interface_arr_cache = exec_command("/sbin/ifconfig -l");

	return $interface_arr_cache;
}

/*
 * does_interface_exist($interface): return true or false if a interface is
 * detected.
 */
function does_interface_exist($interface) {
	if(!$interface) 
		return false;
	$ints = get_interface_arr();

	if(stristr($ints, $interface) !== false)
		return true;
	else
		return false;
}

/*
 * convert_ip_to_network_format($ip, $subnet): converts an ip address to network form
 */
function convert_ip_to_network_format($ip, $subnet) {
	$ipsplit = split('[.]', $ip);
	$string = $ipsplit[0] . "." . $ipsplit[1] . "." . $ipsplit[2] . ".0/" . $subnet;
	return $string;
}

/*
 * find_interface_ip($interface): return the interface ip (first found)
 */
function find_interface_ip($interface, $flush = false) {
	global $interface_ip_arr_cache;
	$interface = str_replace("\n", "", $interface);
	if(does_interface_exist($interface) == false) return;
	/* Setup IP cache */
	if (!isset($interface_ip_arr_cache[$interface]) or $flush) {
		$interface_ip_arr_cache[$interface] = exec_command("/sbin/ifconfig {$interface} | /usr/bin/grep -w \"inet\" | /usr/bin/cut -d\" \" -f 2| /usr/bin/head -1");
		$interface_ip_arr_cache[$interface] = str_replace("\n", "", $interface_ip_arr_cache[$interface]);
	}

	return $interface_ip_arr_cache[$interface];
}

function guess_interface_from_ip($ipaddress) {
        exec("netstat -rn", $output, $ret);
        foreach($output as $line) {
                if(preg_match("/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\/[0-9]+[ ]+link[#]/", $line)) {
                        $fields = preg_split("/[ ]+/", $line);
                        if(ip_in_subnet($ipaddress, $fields[0])) {
                                return $fields[5];
                        }
                }
        }
        $ret = exec_command("/sbin/route -n get {$ipaddress} | /usr/bin/awk '/interface/ { print \$2; };'");
        if(empty($ret)) {
                return false;
        }
        return $ret;
}

function filter_opt_interface_to_real($opt) {
	global $config;
	return $config['interfaces'][$opt]['if'];
}

function filter_get_opt_interface_descr($opt) {
	global $config;
	return $config['interfaces'][$opt]['descr'];
}

function get_friendly_interface_list_as_array() {
	global $config;
	$ints = array();
	$ifdescrs = get_interface_list();
	foreach ($ifdescrs as $ifdescr => $ifname) {
		array_push($ints,$ifdescr);
	}
	return $ints;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 */
function find_ip_interface($ip) {
	global $config;
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$int = filter_translate_type_to_real_interface($ifname);
		$ifconfig = exec_command("/sbin/ifconfig {$int}");
	if(stristr($ifconfig,$ip) <> false)
		return $int;
	}
	return false;
}

/*
 *  filter_translate_type_to_real_interface($interface): returns the real interface name
 *                                                       for a friendly interface.  ie: wan
 */
function filter_translate_type_to_real_interface($interface) {
	global $config;
	if($config['interfaces'][$interface]['if'] <> "") {
		return $config['interfaces'][$interface]['if'];
	} else {
		return $interface;
	}
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
	/* basically cache the contents of ifconfig statement
	to speed up this routine */
	global $carp_query;
	if($carp_query == "")
	$carp_query = split("\n", `/sbin/ifconfig | /usr/bin/grep carp`);
	$found_interface = 0;
	foreach($carp_query as $int) {
		if($found_interface == 1) {
			if(stristr($int, "MASTER")) return "MASTER";
			if(stristr($int, "BACKUP")) return "BACKUP";
			if(stristr($int, "INIT")) return "INIT";
			return false;
		}
		if(stristr($int, $carpinterface) == true)
		$found_interface=1;
	}
	return;
}

/*
 * get_pfsync_interface_status($pfsyncinterface): returns the status of a pfsync
 */
function get_pfsync_interface_status($pfsyncinterface) {
    $result = does_interface_exist($pfsyncinterface);
    if($result <> true) return;
    $status = exec_command("/sbin/ifconfig {$pfsyncinterface} | /usr/bin/grep \"pfsync:\" | /usr/bin/cut -d\" \" -f5");
    return $status;
}

/*
 * find_carp_interface($ip): return the carp interface where an ip is defined
 */
function find_carp_interface($ip) {
	global $find_carp_ifconfig;
	if($find_carp_ifconfig == "") {
		$find_carp_ifconfig = array();
		$num_carp_ints = find_number_of_created_carp_interfaces();
		for($x=0; $x<$num_carp_ints; $x++) {
			$find_carp_ifconfig[$x] = exec_command("/sbin/ifconfig carp{$x}");
		}
	}
	$carps = 0;
	foreach($find_carp_ifconfig as $fci) {
		if(stristr($fci, $ip . " ") == true)		
			return "carp{$carps}";
		$carps++;
	}
}

/*
 * setup_filter_bridge(): toggle filtering bridge
 *
 * disabled 20080805 - this is of no use with if_bridge, in the sense it was
 * with m0n0 (where this came from)
 * --cmb
function setup_filter_bridge() {
	global $config, $g;
	if(isset($config['bridge']['filteringbridge'])) {
		mwexec("/sbin/sysctl net.link.bridge.pfil_member=1");
		mwexec("/sbin/sysctl net.link.bridge.pfil_bridge=1");
	} else {
		mwexec("/sbin/sysctl net.link.bridge.pfil_member=0");
		mwexec("/sbin/sysctl net.link.bridge.pfil_bridge=0");
	}
}
*/

/*
 * find_number_of_created_bridges(): returns the number of currently created bridges
 */
function find_number_of_created_bridges($flush = false) {
	global $bridge_interface_count_cache;
	if(!isset($bridge_interface_count_cache) or $flush)
		$bridge_interface_count_cache = exec_command('/sbin/ifconfig | /usr/bin/grep "bridge[0-999]" | /usr/bin/wc -l');

	return $bridge_interface_count_cache;
}

/*
 * add_rule_to_anchor($anchor, $rule): adds the specified rule to an anchor
 */
function add_rule_to_anchor($anchor, $rule, $label) {
	mwexec("echo " . $rule . " | /sbin/pfctl -a " . $anchor . ":" . $label . " -f -");
}

/*
 * remove_text_from_file
 * remove $text from file $file
 */
function remove_text_from_file($file, $text) {
	global $fd_log;
	if($fd_log)
		fwrite($fd_log, "Adding needed text items:\n");
	$filecontents = file_get_contents($file);
	$textTMP = str_replace($text, "", $filecontents);
	$text = $textTMP;
	if($fd_log)
		fwrite($fd_log, $text);
	$fd = fopen($file, "w");
	fwrite($fd, $text);
	fclose($fd);
}

/*
 * add_text_to_file($file, $text): adds $text to $file.
 * replaces the text if it already exists.
 */
function add_text_to_file($file, $text) {
	if(file_exists($file) and is_writable($file)) {
		$filecontents = split("\n", file_get_contents($file));
		$fout = fopen($file, "w");
		$new_file_text = "";
		foreach($filecontents as $line) {
			if($line)
				$new_file_text .= rtrim($line) . "\n";
		}
		$new_file_text .= $text . "\n";
		$file_text = str_replace("\n\n", "\n", $new_file_text);
		fwrite($fout, $file_text);
		fclose($fout);
		return true;
	} else {
		return false;
	}
}

/*
 *   after_sync_bump_adv_skew(): create skew values by 1S
 */
function after_sync_bump_adv_skew() {
	global $config, $g;
	$processed_skew = 1;
	$a_vip = &$config['virtualip']['vip'];
	foreach ($a_vip as $vipent) {
		if($vipent['advskew'] <> "") {
			$processed_skew = 1;
			$vipent['advskew'] = $vipent['advskew']+1;
		}
	}
	if($processed_skew == 1)
		write_config("After synch increase advertising skew");
}

/*
 * get_filename_from_url($url): converts a url to its filename.
 */
function get_filename_from_url($url) {
	return basename($url);
}

/*
 *   update_output_window: update bottom textarea dynamically.
 */
function update_output_window($text) {
	global $pkg_interface;
	$log = ereg_replace("\n", "\\n", $text);
	if($pkg_interface == "console") {
		/* too chatty */
	} else {
		echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"" . $log . "\";</script>";
	}
	/* ensure that contents are written out */
	ob_flush();
}

/*
 *   get_dir: return an array of $dir
 */
function get_dir($dir) {
	$dir_array = array();
	$d = dir($dir);
	while (false !== ($entry = $d->read())) {
		array_push($dir_array, $entry);
	}
	$d->close();
	return $dir_array;
}

/*
 *   update_output_window: update top textarea dynamically.
 */
function update_status($status) {
	global $pkg_interface;
	if($pkg_interface == "console") {
		echo $status . "\n";
	} else {
		echo "\n<script type=\"text/javascript\">document.forms[0].status.value=\"" . $status . "\";</script>";
	}
	/* ensure that contents are written out */
	ob_flush();
}

/*
 *   exec_command_and_return_text_array: execute command and return output
 */
function exec_command_and_return_text_array($command) {
	$fd = popen($command . " 2>&1 ", "r");
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);
	$temp_array = split("\n", $tmp);
	return $temp_array;
}

/*
 *   exec_command_and_return_text: execute command and return output
 */
function exec_command_and_return_text($command) {
	return exec_command($command);
}

/*
 *   exec_command_and_return_text: execute command and update output window dynamically
 */
function execute_command_return_output($command) {
	global $fd_log, $pkg_interface;
	$fd = popen($command . " 2>&1 ", "r");
	if($pkg_interface <> "console") {
		echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
	}
	$counter = 0;
	$counter2 = 0;
	while(!feof($fd)) {
		$tmp = fread($fd, 50);
		$tmp1 = ereg_replace("\n","\\n", $tmp);
		$text = ereg_replace("\"","'", $tmp1);
		$lasttext = "";
		if($lasttext == "..") {
			$text = "";
			$lasttext = "";
			$counter=$counter-2;
		} else {
			$lasttext .= $text;
		}
		if($counter > 51) {
			$counter = 0;
			$extrabreak = "\\n";
		} else {
	    $extrabreak = "";
	    $counter++;
		}
		if($counter2 > 600) {
			if($pkg_interface <> "console") {
				echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = \"\";</script>";
			}
			$counter2 = 0;
		} else
			$counter2++;
		if($pkg_interface <> "console") {
			echo "\n<script language=\"JavaScript\">this.document.forms[0].output.value = this.document.forms[0].output.value + \"" . $text . $extrabreak .  "\"; f('output'); </script>";
		}
	}
	fclose($fd);
}

/*
 * convert_friendly_interface_to_real_interface_name($interface): convert WAN to FXP0
 */
function convert_friendly_interface_to_real_interface_name($interface) {
	global $config;
	if($config['interfaces'][$interface]['ipaddr'] == "pppoe" or $config['interfaces'][$interface]['ipaddr'] == "pptp")
		return "ng0";
	$lc_interface = strtolower($interface);
	if($lc_interface == "lan") return $config['interfaces']['lan']['if'];
	if($lc_interface == "wan") return $config['interfaces']['wan']['if'];
	$ifdescrs = array();
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;
	foreach ($ifdescrs as $ifdescr => $ifname) {
		if(strtolower($ifname) == $lc_interface)
	    return $config['interfaces'][$ifname]['if'];
		if(strtolower($config['interfaces'][$ifname]['descr']) == $lc_interface)
			return $config['interfaces'][$ifname]['if'];
   }
   return $interface;
}

/*
 * convert_real_interface_to_friendly_interface_name($interface): convert fxp0 -> wan, etc.
 */
function convert_real_interface_to_friendly_interface_name($interface) {
	global $config;
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;
	foreach ($ifdescrs as $ifdescr => $ifname) {
		if($config['interfaces']['$ifname']['if'] == $interface)
			return $ifname;
		$int = filter_translate_type_to_real_interface($ifname);
		if($ifname == $interface) return $ifname;
		if($int == $interface) return $ifname;
	}
	return $interface;
}

/*
 * update_progress_bar($percent): updates the javascript driven progress bar.
 */
function update_progress_bar($percent) {
	global $pkg_interface;
	if($percent > 100) $percent = 1;
	if($pkg_interface <> "console") {
		echo "\n<script type=\"text/javascript\" language=\"javascript\">";
		echo "\ndocument.progressbar.style.width='" . $percent . "%';";
		echo "\n</script>";
	} else {
		echo " {$percent}%";
	}
}

/****f* pfsense-utils/WakeOnLan
 * NAME
 *   WakeOnLan - Wake a machine up using the wake on lan format/protocol
 * RESULT
 *   true/false - true if the operation was successful
 ******/
function WakeOnLan($addr, $mac)
{
	$addr_byte = explode(':', $mac);
	$hw_addr = '';

	for ($a=0; $a < 6; $a++)
		$hw_addr .= chr(hexdec($addr_byte[$a]));

	$msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);

	for ($a = 1; $a <= 16; $a++)
		$msg .= $hw_addr;

	// send it to the broadcast address using UDP
	$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($s == false) {
		log_error("Error creating socket!");
		log_error("Error code is '".socket_last_error($s)."' - " . socket_strerror(socket_last_error($s)));
	} else {
		// setting a broadcast option to socket:
		$opt_ret =  socket_set_option($s, 1, 6, TRUE);
		if($opt_ret < 0)
			log_error("setsockopt() failed, error: " . strerror($opt_ret));
		$e = socket_sendto($s, $msg, strlen($msg), 0, $addr, 2050);
		socket_close($s);
		log_error("Magic Packet sent ({$e}) to {$addr} MAC={$mac}");
		return true;
	}

	return false;
}

/*
 * gather_altq_queue_stats():  gather altq queue stats and return an array that
 *                             is queuename|qlength|measured_packets
 *                             NOTE: this command takes 5 seconds to run
 */
function gather_altq_queue_stats($dont_return_root_queues) {
	mwexec("/usr/bin/killall -9 pfctl", true);
	$stats = `/sbin/pfctl -vvsq & /bin/sleep 5;/usr/bin/killall pfctl 2>/dev/null`;
	$stats_array = split("\n", $stats);
	$queue_stats = array();
	foreach ($stats_array as $stats_line) {
		$match_array = "";
		if (preg_match_all("/queue\s+(\w+)\s+/",$stats_line,$match_array))
			$queue_name = $match_array[1][0];
		if (preg_match_all("/measured:\s+.*packets\/s\,\s(.*)\s+\]/",$stats_line,$match_array))
			$speed = $match_array[1][0];
		if (preg_match_all("/borrows:\s+(.*)/",$stats_line,$match_array))
			$borrows = $match_array[1][0];
		if (preg_match_all("/suspends:\s+(.*)/",$stats_line,$match_array))
			$suspends = $match_array[1][0];
		if (preg_match_all("/dropped pkts:\s+(.*)/",$stats_line,$match_array))
			$drops = $match_array[1][0];
		if (preg_match_all("/measured:\s+(.*)packets/",$stats_line,$match_array)) {
			$measured = $match_array[1][0];
			if($dont_return_root_queues == true)
				if(stristr($queue_name,"root_") == false)
					array_push($queue_stats, "{$queue_name}|{$speed}|{$measured}|{$borrows}|{$suspends}|{$drops}");
		}
	}
	return $queue_stats;
}

/*
 * reverse_strrchr($haystack, $needle):  Return everything in $haystack up to the *last* instance of $needle.
 *					 Useful for finding paths and stripping file extensions.
 */
function reverse_strrchr($haystack, $needle) {
	return strrpos($haystack, $needle) ? substr($haystack, 0, strrpos($haystack, $needle) +1 ) : false;
}

/*
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section) {
	global $config;
	$new_section = &$config[$section];
	/* generate configuration XML */
	$xmlconfig = dump_xml_config($new_section, $section);
	$xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
	return $xmlconfig;
}

/*
 *  backup_vip_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_vip_config_section() {
	global $config;
	$new_section = &$config['virtualip'];
	foreach($new_section['vip'] as $section) {
		if($section['mode'] == "proxyarp") {
			unset($section);
		}
		if($section['advskew'] <> "") {
			$section_val = intval($section['advskew']);
			$section_val=$section_val+100;
			if($section_val > 255)
				$section_val = 255;
			$section['advskew'] = $section_val;
		}
		$temp['vip'][] = $section;
   }
   return $temp;
}

/*
 *  restore_config_section($section, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section, $new_contents) {
	global $config, $g;
	conf_mount_rw();
	$fout = fopen("{$g['tmp_path']}/tmpxml","w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($g['tmp_path'] . "/tmpxml", $section);
	$config[$section] = &$section_xml;
	unlink($g['tmp_path'] . "/tmpxml");
	write_config("Restored {$section} of config file (maybe from CARP partner)");
	conf_mount_ro();
	return;
}

/*
 *  merge_config_section($section, new_contents):   restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.  But preserve the prior
 * 													structure if needed
 */
function merge_config_section($section, $new_contents) {
	global $config;
	conf_mount_rw();
	$fname = get_tmp_filename();
	$fout = fopen($fname, "w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($fname, $section);
	$config[$section] = $section_xml;
	unlink($fname);
	write_config("Restored {$section} of config file (maybe from CARP partner)");
	conf_mount_ro();
	return;
}

/*
 * http_post($server, $port, $url, $vars): does an http post to a web server
 *                                         posting the vars array.
 * written by nf@bigpond.net.au
 */
function http_post($server, $port, $url, $vars) {
	$user_agent = "Mozilla/4.0 (compatible; MSIE 5.5; Windows 98)";
	$urlencoded = "";
	while (list($key,$value) = each($vars))
		$urlencoded.= urlencode($key) . "=" . urlencode($value) . "&";
	$urlencoded = substr($urlencoded,0,-1);
	$content_length = strlen($urlencoded);
	$headers = "POST $url HTTP/1.1
Accept: */*
Accept-Language: en-au
Content-Type: application/x-www-form-urlencoded
User-Agent: $user_agent
Host: $server
Connection: Keep-Alive
Cache-Control: no-cache
Content-Length: $content_length

";

	$errno = "";
	$errstr = "";
	$fp = fsockopen($server, $port, $errno, $errstr);
	if (!$fp) {
		return false;
	}

	fputs($fp, $headers);
	fputs($fp, $urlencoded);

	$ret = "";
	while (!feof($fp))
		$ret.= fgets($fp, 1024);
	fclose($fp);

	return $ret;
}

/*
 *  php_check_syntax($code_tocheck, $errormessage): checks $code_to_check for errors
 */
if (!function_exists('php_check_syntax')){
	function php_check_syntax($code_to_check, &$errormessage){
		return false;
		$fout = fopen("/tmp/codetocheck.php","w");
		$code = $_POST['content'];
		$code = str_replace("<?php", "", $code);
		$code = str_replace("?>", "", $code);
		fwrite($fout, "<?php\n\n");
		fwrite($fout, $code_to_check);
		fwrite($fout, "\n\n?>\n");
		fclose($fout);
		$command = "/usr/local/bin/php -l /tmp/codetocheck.php";
		$output = exec_command($command);
		if (stristr($output, "Errors parsing") == false) {
			echo "false\n";
			$errormessage = '';
			return(false);
		} else {
			$errormessage = $output;
			return(true);
		}
	}
}

/*
 *  php_check_filename_syntax($filename, $errormessage): checks the file $filename for errors
 */
if (!function_exists('php_check_syntax')){
	function php_check_syntax($code_to_check, &$errormessage){
		return false;
		$command = "/usr/local/bin/php -l " . $code_to_check;
		$output = exec_command($command);
		if (stristr($output, "Errors parsing") == false) {
			echo "false\n";
			$errormessage = '';
			return(false);
		} else {
			$errormessage = $output;
			return(true);
		}
	}
}

/*
 * rmdir_recursive($path,$follow_links=false)
 * Recursively remove a directory tree (rm -rf path)
 * This is for directories _only_
 */
function rmdir_recursive($path,$follow_links=false) {
	$to_do = glob($path);
	if(!is_array($to_do)) $to_do = array($to_do);
	foreach($to_do as $workingdir) { // Handle wildcards by foreaching.
		if(file_exists($workingdir)) {
			if(is_dir($workingdir)) {
				$dir = opendir($workingdir);
				while ($entry = readdir($dir)) {
					if (is_file("$workingdir/$entry") || ((!$follow_links) && is_link("$workingdir/$entry")))
						unlink("$workingdir/$entry");
					elseif (is_dir("$workingdir/$entry") && $entry!='.' && $entry!='..')
						rmdir_recursive("$workingdir/$entry");
				}
				closedir($dir);
				rmdir($workingdir);
			} elseif (is_file($workingdir)) {
				unlink($workingdir);
			}
               	}
	}
	return;
}

/*
 *     get_memory()
 *     returns an array listing the amount of
 *     memory installed in the hardware
 *     [0]real and [1]available
 */
function get_memory() {
	if(file_exists("/var/log/dmesg.boot")) {
		$mem = `cat /var/log/dmesg.boot | grep memory`;
		$matches = "";
		if (preg_match_all("/real memory  = .* \((.*) MB/", $mem, $matches))
			$real = $matches[1];
		if (preg_match_all("/avail memory = .* \((.*) MB/", $mem, $matches))
			$avail = $matches[1];
		return array($real[0],$avail[0]);
	} else {
		$mem = `dmesg -a`;
		$matches = "";
		if (preg_match_all("/real memory  = .* \((.*) MB/", $mem, $matches))
			$real = $matches[1];
		if (preg_match_all("/avail memory = .* \((.*) MB/", $mem, $matches))
			$avail = $matches[1];
		return array($real[0],$avail[0]);
	}
}

/*
 *    safe_mkdir($path, $mode = 0755)
 *    create directory if it doesn't already exist and isn't a file!
 */
function safe_mkdir($path, $mode=0755) {
	global $g;

	/* cdrom is ro. */
	if($g['platform'] == "cdrom")
		return false;

	if (!is_file($path) && !is_dir($path))
		return mkdir($path, $mode);
	else
		return false;
}

/*
 * make_dirs($path, $mode = 0755)
 * create directory tree recursively (mkdir -p)
 */
function make_dirs($path, $mode = 0755) {
	$base = '';
	foreach (explode('/', $path) as $dir) {
		$base .= "/$dir";
		if (!is_dir($base)) {
			if (!@mkdir($base, $mode))
				return false;
		}
	}
	return true;
}

/*
 * call_pfsense_method(): Call a method exposed by the pfsense.com XMLRPC server.
 */
function call_pfsense_method($method, $params, $timeout = 0) {
	$ip = gethostbyname('www.pfsense.com');
	if($ip == "www.pfsense.com")
		return false;
	global $g, $config;
	$xmlrpc_base_url = $g['xmlrpcbaseurl'];
	$xmlrpc_path = $g['xmlrpcpath'];
	$msg = new XML_RPC_Message($method, array(XML_RPC_Encode($params)));
	$cli = new XML_RPC_Client($xmlrpc_path, $xmlrpc_base_url);
	$resp = $cli->send($msg, $timeout);
	if(!$resp) {
		log_error("XMLRPC communication error: " . $cli->errstr);
		return false;
	} elseif($resp->faultCode()) {
		log_error("XMLRPC request failed with error " . $resp->faultCode() . ": " . $resp->faultString());
		return false;
	} else {
		return XML_RPC_Decode($resp->value());
	}
}

/*
 * check_firmware_version(): Check whether the current firmware installed is the most recently released.
 */
function check_firmware_version($tocheck = "all", $return_php = true) {
	global $g, $config;
	$ip = gethostbyname('www.pfsense.com');
	if($ip == "www.pfsense.com")
		return false;
	$rawparams = array("firmware" => array("version" => trim(file_get_contents('/etc/version'))),
		"kernel"   => array("version" => trim(file_get_contents('/etc/version_kernel'))),
		"base"     => array("version" => trim(file_get_contents('/etc/version_base'))),
		"platform" => trim(file_get_contents('/etc/platform'))
		);
	if($tocheck == "all") {
		$params = $rawparams;
	} else {
		foreach($tocheck as $check) {
			$params['check'] = $rawparams['check'];
			$params['platform'] = $rawparams['platform'];
		}
	}
	if($config['system']['firmware']['branch']) {
		$params['branch'] = $config['system']['firmware']['branch'];
	}
	if(!$versions = call_pfsense_method('pfsense.get_firmware_version', $params)) {
		return false;
	} else {
		$versions["current"] = $params;
	}
	return $versions;
}

function get_disk_info() {
	$diskout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $2, $3, $4, $5 }'", $diskout);
	return explode(' ', $diskout[0]);
	// $size, $used, $avail, $cap
}

function run_plugins($directory) {
        global $config, $g;

        /* process packager manager custom rules */
        $files = return_dir_as_array($directory);
        if (is_array($files)) {
                foreach ($files as $file) {
			if (stristr($file, ".sh") == true)
				mwexec($directory . $file . " start");
			else if (!is_dir($directory . "/" . $file) && stristr($file,".inc")) {
				if ($g['booting'] == true)
					echo "{$file}...";
				require_once($directory . "/" . $file);
                        }
                }
        }
}

/****f* pfsense-utils/display_top_tabs
 * NAME
 *   display_top_tabs - display tabs with rounded edges
 * INPUTS
 *   $text	- array of tabs
 * RESULT
 *   null
 ******/
function display_top_tabs($tab_array) {
	$tabcharcount = 0;
	foreach ($tab_array as $ta) 
		$tabcharcount = $tabcharcount + strlen($ta[0]);
	// If the character count of the tab names is > 82
	// then show a select item dropdown menubox.
	if($tabcharcount > 82) {
		echo "Currently viewing: ";
		echo "<select name='TabSelect'>\n";
		foreach ($tab_array as $ta) {
			if($ta[1]=="true")	
				$selected = " SELECTED";
			else 
				$selected = "";
			echo "<option onClick=\"document.location='{$ta[2]}';\"{$selected}>{$ta['0']}</option>\n";
		}
		echo "</select>\n<p/>";
	}  else {
		echo "<table cellpadding='0' cellspacing='0'>\n";
		echo " <tr height='1'>\n";
		$tabscounter = 0;
		foreach ($tab_array as $ta) {
			if($ta[1] == true) {
				echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><div id='tabactive'></div></td>\n";
			} else {
				echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><div id='tabdeactive{$tabscounter}'></div></td>\n";
			}
			$tabscounter++;
		}
		echo "</tr>\n<tr>\n";
		foreach ($tab_array as $ta) {
			if($ta[1] == true) {
				echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><B>&nbsp;&nbsp;&nbsp;{$ta[0]}";
				echo "&nbsp;&nbsp;&nbsp;";
				echo "<font size='-12'>&nbsp;</td>\n";
			} else {
				echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><B>&nbsp;&nbsp;&nbsp;<a href='{$ta[2]}'>";
				echo "<font color='white'>{$ta[0]}</a>&nbsp;&nbsp;&nbsp;";
				echo "<font size='-12'>&nbsp;</td>\n";
			}
		}
		echo "</tr>\n<tr height='5px'>\n";
		foreach ($tab_array as $ta) {
			if($ta[1] == true) {
				echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"></td>\n";
			} else {
				echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"></td>\n";
			}
			$tabscounter++;
		}
		echo " </tr>\n";
		echo "</table>\n";

		echo "<script type=\"text/javascript\">";
		echo "NiftyCheck();\n";
		echo "Rounded(\"div#tabactive\",\"top\",\"#FFF\",\"#EEEEEE\",\"smooth\");\n";
		for($x=0; $x<$tabscounter; $x++)
			echo "Rounded(\"div#tabdeactive{$x}\",\"top\",\"#FFF\",\"#777777\",\"smooth\");\n";
		echo "</script>";
	}
}

/****f* pfsense-utils/display_topbar
 * NAME
 *   display_topbar - top a table off with rounded edges
 * INPUTS
 *   $text	- (optional) Text to include in bar
 * RESULT
 *   null
 ******/
function display_topbar($text = "", $bg_color="#990000", $replace_color="#FFFFFF", $rounding_style="smooth") {
	echo "     <table width='100%' cellpadding='0' cellspacing='0'>\n";
	echo "       <tr height='1'>\n";
	echo "         <td width='100%' valign='top' color='{$bg_color}' bgcolor='{$bg_color}'>";
	echo "		<div id='topbar'></div></td>\n";
	echo "       </tr>\n";
	echo "       <tr height='1'>\n";
	if ($text != "")
		echo "         <td height='1' class='listtopic'>{$text}</td>\n";
	else
		echo "         <td height='1' class='listtopic'></td>\n";
	echo "       </tr>\n";
	echo "     </table>";
	echo "<script type=\"text/javascript\">";
	echo "NiftyCheck();\n";
	echo "Rounded(\"div#topbar\",\"top\",\"{$replace_color}\",\"{$bg_color}\",\"{$rounding_style}\");\n";
	echo "</script>";
}

/****f* pfsense-utils/generate_random_mac_address
 * NAME
 *   generate_random_mac - generates a random mac address
 * INPUTS
 *   none
 * RESULT
 *   $mac - a random mac address
 ******/
function generate_random_mac_address() {
	$mac = "02";
	for($x=0; $x<5; $x++)
		$mac .= ":" . dechex(rand(16, 255));
	return $mac;
}

/****f* pfsense-utils/strncpy
 * NAME
 *   strncpy - copy strings
 * INPUTS
 *   &$dst, $src, $length
 * RESULT
 *   none
 ******/
function strncpy(&$dst, $src, $length) {
	if (strlen($src) > $length) {
		$dst = substr($src, 0, $length);
	} else {
		$dst = $src;
	}
}

/****f* pfsense-utils/reload_interfaces_sync
 * NAME
 *   reload_interfaces - reload all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces_sync() {
	global $config, $g, $debug;

	$shutdown_webgui_needed = false;

	touch("{$g['tmp_path']}/reloading_all");

	if($debug)
		log_error("reload_interfaces_sync() is starting.");

	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");

	/* parse config.xml again */
	$config = parse_config(true);

	$wan_if = $config['interfaces']['wan']['if'];
	$lan_if = $config['interfaces']['lan']['if'];

	if($debug)
		log_error("Cleaning up Interfaces");

	/* build an array of interfaces to work with */
	$iflist = array("lan" => "LAN", "wan" => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iflist['opt' . $i] = "opt{$i}";

	foreach ($iflist as $ifent => $ifname) {
		$ifname_real = convert_friendly_interface_to_real_interface_name($ifname);
		if(stristr($ifname, "lo0") == true)
			continue;
		/* do not process wan interface, its mandatory */
		if(stristr($ifname, "$wan_if") == true)
			continue;
		/* do not process lan interface, its mandatory */
		if(stristr($ifname, "$lan_if") == true)
			continue;
		if($debug)
			log_error("Downing and deleting $ifname_real - $ifname");
		mwexec("/sbin/ifconfig {$ifname_real} down");
		mwexec("/sbin/ifconfig {$ifname_real} delete");
	}

	/* set up VLAN virtual interfaces */
	if($debug)
		log_error("Configuring VLANS");
	interfaces_vlan_configure();

	/* set up LAN interface */
	if($debug)
		log_error("Configuring LAN");
	interfaces_lan_configure();

	/* set up WAN interface */
	if($debug)
		log_error("Configuring WAN");
	interfaces_wan_configure();

	/* set up Optional interfaces */
	if($debug)
		log_error("Configuring optional interfaces");
	interfaces_optional_configure();

	/* set up static routes */
	if($debug)
		log_error("Configuring system Routing");
	system_routing_configure();

	/* enable routing */
	if($debug)
		log_error("Enabling system routing");
	system_routing_enable();

	/* setup captive portal if needed */
	if($debug)
		log_error("Configuring Captive portal");
	captiveportal_configure();

	/* bring up carp interfaces */
	if($debug)
		log_error("Configuring CARP");
	interfaces_carp_configure();

	/* bring up carp interfaces*/
	if($debug)
		log_error("Bringing up CARP interfaces");
	interfaces_carp_bring_up_final();

	/* restart webConfigurator if needed */
	if($shutdown_webgui_needed == true)
		touch("/tmp/restart_webgui");

	/* start devd back up */
	mwexec("/bin/rm /tmp/reload*");

	/* remove reloading_all trigger */
	if($debug)
		log_error("Removing {$g['tmp_path']}/reloading_all");
	unlink_if_exists("{$g['tmp_path']}/reloading_all");
}

/****f* pfsense-utils/reload_all
 * NAME
 *   reload_all - triggers a reload of all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all() {
	touch("/tmp/reload_all");
}

/****f* pfsense-utils/reload_interfaces
 * NAME
 *   reload_interfaces - triggers a reload of all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces() {
	touch("/tmp/reload_interfaces");
}

/****f* pfsense-utils/sync_webgui_passwords
 * NAME
 *   sync_webgui_passwords - syncs webgui and ssh passwords
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function sync_webgui_passwords() {
	global $config, $g;
	conf_mount_rw();
	$fd = fopen("{$g['varrun_path']}/htpasswd", "w");
	if (!$fd) {
		printf("Error: cannot open htpasswd in system_password_configure().\n");
		return 1;
	}
	/* set admin account */
	$username = $config['system']['username'];

	/* set defined user account */
	if($username <> "admin") {
		$username = $config['system']['username'];
		fwrite($fd, $username . ":" . $config['system']['password'] . "\n");
	} else {
		fwrite($fd, $username . ":" . $config['system']['password'] . "\n");
	}
	fclose($fd);
	chmod("{$g['varrun_path']}/htpasswd", 0600);
	$crypted_pw = $config['system']['password'];
	if(file_exists("/etc/pwd.db.tmp")) 
		unlink("/etc/pwd.db.tmp");
	mwexec("/usr/sbin/pwd_mkdb -d /etc -p /etc/master.passwd");
	mwexec("/usr/sbin/pwd_mkdb -p /etc/master.passwd");
	/* sync root */
	$fd = popen("/usr/sbin/pw usermod -n root -H 0", "w");
	fwrite($fd, $crypted_pw);
	pclose($fd);
	mwexec("/usr/sbin/pw usermod -n root -s /bin/sh");
	/* sync admin */
	$fd = popen("/usr/sbin/pw usermod -n admin -H 0", "w");
	fwrite($fd, $crypted_pw);
	pclose($fd);
	mwexec("/usr/sbin/pw usermod -n admin -s /etc/rc.initial");
	mwexec("/usr/sbin/pwd_mkdb -d /etc -p /etc/master.passwd");
	mwexec("/usr/sbin/pwd_mkdb -p /etc/master.passwd");
	conf_mount_ro();
}

/****f* pfsense-utils/cleanup_opt_interfaces_after_removal
 * NAME
 *   cleanup_opt_interfaces_after_removal - renumber interfaces after removing
 *   * INPUTS
 *   optional interface number
 * RESULT
 *   none
 ******/
function cleanup_opt_interfaces_after_removal($opt_interface_num) {
	/*	move all the interfaces up.  for example:
	*		opt1 --> opt1
	*		opt2 --> delete
	*		opt3 --> opt2
	*		opt4 --> opt3
	*/
	global $g, $config;
	config_lock();
	conf_mount_rw();
	unlink_if_exists("{$g['tmp_path']}/config.cache");
	$config_file = file_get_contents("/cf/conf/config.xml");
	/* loop through and reassign deleted items */
	$orig = array('opt'.$opt_interface_num,'OPT'.$opt_interface_num);
	$datetime = date("YmdHis");
	$repl = array('optXXXX',"OPT$datetime");
	for ($i = $opt_interface_num+1; isset ($config['interfaces']['opt' . $i]); $i++) {
		array_push($orig,'opt'.$i);
		array_push($repl,'opt'.($i -1));
		array_push($orig,'OPT'.$i);
		array_push($repl,'OPT'.($i -1));
	}
	$config_file = str_replace($orig, $repl, $config_file);
	$fd = fopen("/cf/conf/config.xml", "w");
	fwrite($fd, $config_file);
	fclose($fd);
	$config = parse_config(true);
	/* loop through and delete old rules */
	$num_rules = count($config['filter']['rule']);
	for($x = $num_rules; $x > 0; $x--) {
		if($config['filter']['rule'][$x])
			if($config['filter']['rule'][$x]['interface'] == "optXXXX")
		 		unset($config['filter']['rule'][$x]['interface']);
	}
	$num_rules = count($config['nat']['advancedoutbound']['rule']);
	for($x = $num_rules; $x > 0; $x--) {
		if($config['nat']['advancedoutbound']['rule'][$x])
			if($config['nat']['advancedoutbound']['rule'][$x]['interface'] == "optXXXX")
		 		unset($config['nat']['advancedoutbound']['rule'][$x]['interface']);
	}
	$num_rules = count($config['nat']['rule']);
	for($x = $num_rules; $x > 0; $x--) {
		if($config['nat']['rule'][$x])
			if($config['nat']['rule'][$x]['interface'] == "optXXXX")
		 		unset($config['nat']['rule'][$x]['interface']);
	}
	write_config();
	conf_mount_ro();
	config_unlock();
	return true;
}

/****f* pfsense-utils/get_number_of_wan_netgraph_interfaces_needed
 * NAME
 *   get_number_of_wan_netgraph_interfaces_needed - returns the
 *   		amount of netgraph interfaces needed for system wans
 *   * INPUTS
 *   none
 * RESULT
 *   number of needed netgraph (ng) interfaces
 ******/
function get_number_of_wan_netgraph_interfaces_needed() {
	global $config, $g;
	/* build an array of interfaces to work with */
	$iflist = array("wan" => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
	$ng_interfaces_needed = 0;
	foreach ($iflist as $ifent => $ifname) {
		if($config['interfaces'][$ifname]['ipaddr'] == "pppoe") {
			$ng_interfaces_needed++;
		}
	}
	return $ng_interfaces_needed;
}

function get_netgaph_interface_assignment($friendly_interface) {
	global $config, $g;
	/* build an array of interfaces to work with */
	$iflist = array("wan" => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iflist['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
		$ng_interfaces_needed = 0;
		$ng_interfaces_number = 0;
		foreach ($iflist as $ifent => $ifname) {
		if($config['interfaces'][$ifname]['ipaddr'] == "pppoe") {
			$ng_interfaces_number++;
		}
		if($friendly_interface == $ifname)
			break;
	}
	return $ng_interfaces_number;
}

/****f* pfsense-utils/reload_all_sync
 * NAME
 *   reload_all - reload all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all_sync() {
	global $config, $g;

	$g['booting'] = false;

	touch("{$g['tmp_path']}/reloading_all");

	$shutdown_webgui_needed = false;

	if(file_exists("{$g['tmp_path']}/config.cache"))
		unlink("{$g['tmp_path']}/config.cache");

	/* parse config.xml again */
	$config = parse_config(true);

	/* set up our timezone */
	system_timezone_configure();

	/* set up our hostname */
	system_hostname_configure();

	/* make hosts file */
	system_hosts_generate();

	/* generate resolv.conf */
	system_resolvconf_generate();

	/* Set up our loopback interface */
	interfaces_loopback_configure();

	/* delete all old interface information */
	$iflist = split(" ", str_replace("\n", "", `/sbin/ifconfig -l`));

	$wan_if = $config['interfaces']['wan']['if'];
	$lan_if = $config['interfaces']['lan']['if'];

	/* build an array of interfaces to work with */
	$iflist = array("lan" => "LAN", "wan" => "WAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iflist['opt' . $i] = "opt{$i}";

	foreach ($iflist as $ifent => $ifname) {
		$ifname_real = convert_friendly_interface_to_real_interface_name($ifname);
		if(stristr($ifname, "lo0") == true)
			continue;
		/* do not process wan interface, its mandatory */
		if($wan_if == $ifname_real)
			continue;
		/* do not process lan interface, its mandatory */
		if($lan_if == $ifname_real)
			continue;
		mwexec("/sbin/ifconfig {$ifname_real} down");
		mwexec("/sbin/ifconfig {$ifname_real} delete");
	}

	/* set up VLAN virtual interfaces */
	interfaces_vlan_configure();

	/* set up LAN interface */
	interfaces_lan_configure();

	/* set up WAN interface */
	interfaces_wan_configure();

	/* set up Optional interfaces */
	interfaces_optional_configure();

	/* bring up carp interfaces */
	interfaces_carp_configure();

	/* set up static routes */
	system_routing_configure();

	/* enable routing */
	system_routing_enable();

	/* ensure passwords are sync'd */
	system_password_configure();

	/* start dnsmasq service */
	services_dnsmasq_configure();

	/* start dyndns service */
	services_dyndns_configure();

	/* start DHCP service */
	services_dhcpd_configure();

	/* configure cron service */
	configure_cron();

	/* start the NTP client */
	system_ntp_configure();

	/* start ftp proxy helpers if they are enabled */
	system_start_ftp_helpers();

	/* start the captive portal */
	captiveportal_configure();

        /* reload the filter */
	filter_configure_sync();

	/* bring up carp interfaces*/
	interfaces_carp_bring_up_final();

	/* sync pw database */
	conf_mount_rw();
	mwexec("/usr/sbin/pwd_mkdb -d /etc/ /etc/master.passwd");
	conf_mount_ro();

	/* restart sshd */
	touch("/tmp/start_sshd");

	/* restart webConfigurator if needed */
	if($shutdown_webgui_needed == true)
		touch("/tmp/restart_webgui");

	mwexec("/bin/rm /tmp/reload*");

	unlink_if_exists("{$g['tmp_path']}/reloading_all");

}

function auto_login($status) {
	$gettytab = file_get_contents("/etc/gettytab");
	$getty_split = split("\n", $gettytab);
	conf_mount_rw();
	$fd = fopen("/etc/gettytab", "w");
	foreach($getty_split as $gs) {
		if(stristr($gs, ":ht:np:sp#115200") ) {
			if($status == true) {
				fwrite($fd, "	:ht:np:sp#115200:al=root:\n");
			} else {
				fwrite($fd, "	:ht:np:sp#115200:\n");
			}
		} else {
			fwrite($fd, "{$gs}\n");
		}
	}
	fclose($fd);
	conf_mount_ro();
}

function setup_serial_port() {
	global $g, $config;
	conf_mount_rw();
	/* serial console - write out /boot.config */
	if(file_exists("/boot.config"))
		$boot_config = file_get_contents("/boot.config");
	else
		$boot_config = "";

	if($g['platform'] <> "cdrom") {
		$boot_config_split = split("\n", $boot_config);
		$fd = fopen("/boot.config","w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "-D")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "-D");
			}
			fclose($fd);
		}
		/* serial console - write out /boot/loader.conf */
		$boot_config = file_get_contents("/boot/loader.conf");
		$boot_config_split = split("\n", $boot_config);
		$fd = fopen("/boot/loader.conf","w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "console")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "console=\"comconsole\"\n");
			}
			fclose($fd);
		}
	}
	$ttys = file_get_contents("/etc/ttys");
	$ttys_split = split("\n", $ttys);
	$fd = fopen("/etc/ttys", "w");
	foreach($ttys_split as $tty) {
		if(stristr($tty, "ttyd0")) {
			if(isset($config['system']['enableserial'])) {
				fwrite($fd, "ttyd0	\"/usr/libexec/getty bootupcli\"	dialup	on	secure\n");
			} else {
				fwrite($fd, "ttyd0	\"/usr/libexec/getty bootupcli\"	dialup	off	secure\n");
			}
		} else {
			fwrite($fd, $tty . "\n");
		}
	}
	fclose($fd);
	if(isset($config['system']['disableconsolemenu'])) {
		auto_login(false);
	} else {
		auto_login(true);
	}
	conf_mount_ro();
	return;
}

function print_value_list($list, $count = 10, $separator = ",") {
	$list = implode($separator, array_slice($list, 0, $count));
	if(count($list) < $count) {
		$list .= ".";
	} else {
		$list .= "...";
	}
	return $list;
}

function convert_friendly_interface_to_friendly_descr($interface) {
	global $config;
	/* attempt to resolve interface to friendly descr */
	if($config['interfaces'][$interface]['descr'])
		return $config['interfaces'][$interface]['descr'];
	$tmp = convert_real_interface_to_friendly_descr($interface);
	/* could not resolve, return back what was passed */
	return $interface;
}

function convert_real_interface_to_friendly_descr($interface) {
	global $config;
	if($interface == $config['interfaces']['wan']['if'])
		return "wan";
	if($interface == $config['interfaces']['lan']['if'])
		return "lan";
	/* attempt to resolve interface to friendly descr */
	$friendly_int = convert_real_interface_to_friendly_interface_name($interface);
	if($config['interfaces'][$friendly_int]['descr'])
		return $config['interfaces'][$friendly_int]['descr'];
	/* could not resolve, return back what was passed */
	return $interface;
}

function enable_rrd_graphing() {
	global $config, $g;

	if($g['booting']) 
		echo "Generating RRD graphs...";

	$rrddbpath = "/var/db/rrd/";
	$rrdgraphpath = "/usr/local/www/rrd";

	$traffic = "-traffic.rrd";
	$packets = "-packets.rrd";
	$states = "-states.rrd";
	$quality = "-quality.rrd";
	$queues = "-queues.rrd";
	$queuesdrop = "-queuesdrop.rrd";
	$spamd = "-spamd.rrd";
	$proc = "-processor.rrd";

	$rrdtool = "/usr/local/bin/rrdtool";
	$netstat = "/usr/bin/netstat";
	$awk = "/usr/bin/awk";
	$tar = "/usr/bin/tar";
	$pfctl = "/sbin/pfctl";
	$php = "/usr/local/bin/php";
	$top = "/usr/bin/top";
	$spamd_gather = "/usr/local/bin/spamd_gather_stats.php";

	$rrdtrafficinterval = 60;
	$rrdqualityinterval = 60;
	$rrdqueuesinterval = 60;
	$rrdqueuesdropinterval = 60;
	$rrdpacketsinterval = 60;
	$rrdstatesinterval = 60;
	$rrdspamdinterval = 60;
	$rrdlbpoolinterval = 60;
	$rrdprocinterval = 60;

	$trafficvalid = $rrdtrafficinterval * 2;
	$qualityvalid = $rrdqualityinterval * 2;
	$queuesvalid = $rrdqueuesinterval * 2;
	$queuesdropvalid = $rrdqueuesdropinterval * 2;
	$packetsvalid = $rrdpacketsinterval * 2;
	$statesvalid = $rrdstatesinterval*2;
	$spamdvalid = $rrdspamdinterval * 2;
	$lbpoolvalid = $rrdlbpoolinterval * 2;
	$procvalid = $rrdlbpoolinterval * 2;

	/* Asume GigE for now */
	$downstream = 125000000;
	$upstream = 125000000;

	$rrdrestore = "";
	$rrdreturn = "";

	if (isset ($config['rrd']['enable'])) {

		/* create directory if needed */
		if (!is_dir("$rrddbpath")) {
			mkdir("$rrddbpath", 0755);
		}

		if ($g['booting']) {
			if ($g['platform'] != "pfSense") {
				/* restore the databases, if we have one */
				if (file_exists("{$g['cf_conf_path']}/rrd.tgz")) {
					exec("cd /;LANG=C /usr/bin/tar -xzf {$g['cf_conf_path']}/rrd.tgz", $rrdrestore, $rrdreturn);
					if((int)$rrdrestore <> 0) {
						log_error("RRD restore failed exited with $rrdreturn, the error is: $rrdrestore[0]\n");
					}
				}
			}
		}

		/* db update script */
		$rrdupdatesh = "#!/bin/sh\n";
		$rrdupdatesh .= "\n";
		$rrdupdatesh .= "counter=1\n";
		$rrdupdatesh .= "while [ \"\$counter\" -ne 0 ]\n";
		$rrdupdatesh .= "do\n";
		$rrdupdatesh .= "";

		$i = 0;
		$vfaces = array (
			"vlan.?*",
			"enc.?*"
		);
		$ifdescrs = get_interface_list(true, true, $vfaces);
		$ifdescrs['enc0']['friendly'] = "ipsec";
		$ifdescrs['enc0']['descr'] = "IPSEC";
		$ifdescrs['enc0']['up'] = true;

		foreach ($ifdescrs as $realif => $ifdescr) {
			$ifname = $ifdescr['friendly'];
			$state = $ifdescr['up'];

			/* skip interfaces that do not have a friendly name */
			if ("$ifname" == "") {
				continue;
			}

			/* or are down */
			if (!$state) {
				continue;
			}

			/* TRAFFIC, set up the rrd file */
			if (!file_exists("$rrddbpath$ifname$traffic")) {
				/* create rrd file if it does not exist */
				log_error("Create RRD database $rrddbpath$ifname$traffic");
				$rrdcreate = "$rrdtool create $rrddbpath$ifname$traffic --step $rrdtrafficinterval ";
				$rrdcreate .= "DS:in:COUNTER:$trafficvalid:0:$downstream ";
				$rrdcreate .= "DS:out:COUNTER:$trafficvalid:0:$upstream ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:720:1000";

				$rrdcreateoutput = array();
				$rrdcreatereturn = "";

				$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
				if ($rrdcreatereturn != 0) {
					log_error("RRD create failed exited with $rrdcreatereturn, the
							error is: $rrdcreateoutput[0]\n");
				}
			}

			/* enter UNKNOWN values in the RRD so it knows we rebooted. */
			if($g['booting']) {
				exec("$rrdtool update $rrddbpath$ifname$traffic N:U:U");
			}

			$rrdupdatesh .= "\n";
			$rrdupdatesh .= "# polling traffic for interface $ifname $realif \n";
			$rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$traffic N:\\\n";
			if(! is_macaddr(get_interface_mac($realif))) {
				$rrdupdatesh .= "`$netstat -nbf link -I {$realif} | $awk '{getline 2;print \$6 \":\" \$9}'`\n";
			} else {
				$rrdupdatesh .= "`$netstat -nbf link -I {$realif} | $awk '{getline 2;print \$7 \":\" \$10}'`\n";
			}

			/* PACKETS, set up the rrd file */
			if (!file_exists("$rrddbpath$ifname$packets")) {
				/* create rrd file if it does not exist */
				log_error("Create RRD database $rrddbpath$ifname$packets");
				$rrdcreate = "$rrdtool create $rrddbpath$ifname$packets --step $rrdpacketsinterval ";
				$rrdcreate .= "DS:in:COUNTER:$packetsvalid:0:$downstream ";
				$rrdcreate .= "DS:out:COUNTER:$packetsvalid:0:$upstream ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:720:1000";

				$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
				if ($rrdcreatereturn != 0) {
					log_error("RRD create failed exited with $rrdcreatereturn, the
							error is: $rrdcreateoutput[0]\n");
				}
			}

			/* enter UNKNOWN values in the RRD so it knows we rebooted. */
			if($g['booting']) {
				exec("$rrdtool update $rrddbpath$ifname$packets N:U:U");
			}

			$rrdupdatesh .= "\n";
			$rrdupdatesh .= "# polling packets for interface $ifname $realif \n";
			$rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$packets N:\\\n";
			if(! is_macaddr(get_interface_mac($realif))) {
				$rrdupdatesh .= "`$netstat -nbf link -I {$realif} | $awk '{getline 2;print \$4 \":\" \$7}'`\n";
			} else {
				$rrdupdatesh .= "`$netstat -nbf link -I {$realif} | $awk '{getline 2;print \$5 \":\" \$8}'`\n";
			}
			/* if an alternative gateway is defined, use it */
			if ($config['interfaces'][$ifname]['use_rrd_gateway'] <> "") {
				$gatewayip = get_interface_gateway($ifname);
				$monitorip = $config['interfaces'][$ifname]['use_rrd_gateway'];
				mwexec("/sbin/route add -host {$monitorip} {$gatewayip} 2>&1");
			} else {
				$monitorip = get_interface_gateway($ifname);
			}
			$numpings = 5;
			$btick = '`';

			if($monitorip <> "") {
				/* QUALITY, create link quality database */
				if (!file_exists("$rrddbpath$ifname$quality")) {
					/* create rrd file if it does not exist */
					log_error("Create RRD database $rrddbpath$ifname$quality");
					$rrdcreate = "$rrdtool create $rrddbpath$ifname$quality --step $rrdqualityinterval ";
					$rrdcreate .= "DS:loss:GAUGE:$qualityvalid:0:100 ";
					$rrdcreate .= "DS:roundtrip:GAUGE:$qualityvalid:0:10000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:720:1000";

					$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
					if ($rrdcreatereturn != 0) {
						log_error("RRD create failed exited with $rrdcreatereturn, the error is: $rrdcreateoutput[0]\n");
					}
				}

				/* enter UNKNOWN values in the RRD so it knows we rebooted. */
				if($g['booting']) {
					exec("$rrdtool update $rrddbpath$ifname$quality N:U:U");
				}

				/* the ping test function. We call this on the last line */
				$rrdupdatesh .= "get_quality_stats_{$ifname} () {\n";
				$rrdupdatesh .= "	packetloss_{$ifname}=100\n";
				$rrdupdatesh .= "	roundtrip_{$ifname}=0\n";
				$rrdupdatesh .= "	local out_{$ifname}\n";
				$rrdupdatesh .= "	out_{$ifname}=$btick ping -c $numpings -q $monitorip $btick\n";
				$rrdupdatesh .= "	if [ $? -eq 0 ]; then\n";
				$rrdupdatesh .= "		packetloss_{$ifname}=$btick echo \$out_{$ifname} | cut -f18 -d' ' | cut -c -1 $btick\n";
				$rrdupdatesh .= "		roundtrip_{$ifname}=$btick echo \$out_{$ifname} | cut -f24 -d' ' | cut -f2 -d'/' $btick\n";
				$rrdupdatesh .= "	fi\n";
				$rrdupdatesh .= "	$rrdtool update $rrddbpath$ifname$quality N:\$packetloss_{$ifname}:\$roundtrip_{$ifname}\n";
				$rrdupdatesh .= "}\n\n";

				$rrdupdatesh .= "get_quality_stats_{$ifname} &\n\n";
			}

			/* WAN interface only statistics */
			if ("$ifname" == "wan") {

				/* QUEUES, set up the queues databases */
				if (!is_array($config['shaper']['queue'])) {
					$config['shaper']['queue'] = array ();
				}
				$a_queues = & $config['shaper']['queue'];

				if (isset ($config['shaper']['enable'])) {
					if (!file_exists("$rrddbpath$ifname$queues")) {
						/* create rrd file if it does not exist */
						log_error("Create RRD database $rrddbpath$ifname$queues");
						$rrdcreate = "$rrdtool create $rrddbpath$ifname$queues --step $rrdqueuesinterval ";
						/* loop list of shaper queues */
						$q = 0;
						foreach ($a_queues as $queue) {
							$name = $queue['name'];
							$rrdcreate .= "DS:$name:COUNTER:$queuesvalid:0:$downstream ";
						}

						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000";

						$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
						if ($rrdcreatereturn != 0) {
							log_error("RRD create failed exited with $rrdcreatereturn, the
									error is: $rrdcreateoutput[0]\n");
						}
					}

					if (!file_exists("$rrddbpath$ifname$queuesdrop")) {
						/* create rrd file if it does not exist */
						log_error("Create RRD database $rrddbpath$ifname$queuesdrop");
						$rrdcreate = "$rrdtool create $rrddbpath$ifname$queuesdrop --step $rrdqueuesdropinterval ";
						/* loop list of shaper queues */
						$q = 0;
						foreach ($a_queues as $queue) {
							$name = $queue['name'];
							$rrdcreate .= "DS:$name:COUNTER:$queuesdropvalid:0:$downstream ";
						}

						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000";

						$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
						if ($rrdcreatereturn != 0) {
							log_error("RRD create failed exited with $rrdcreatereturn, the error is: $rrdcreateoutput[0]\n");
						}
					}

					if($g['booting']) {
						$rrdqcommand = "-t ";
						$rrducommand = "N";
						$q = 0;
						foreach ($a_queues as $queue) {
							if($q == 0) {
								$rrdqcommand .= "{$queue['name']}";
							} else {
								$rrdqcommand .= ":{$queue['name']}";
							}
							$q++;
							$rrducommand .= ":U";
						}
						exec("$rrdtool update $rrddbpath$ifname$queues $rrdqcommand $rrducommand");
						exec("$rrdtool update $rrddbpath$ifname$queuesdrop $rrdqcommand $rrducommand");
					}

					/* awk function to gather shaper data */
					/* yes, it's special */
					$rrdupdatesh .= "` pfctl -vsq | awk 'BEGIN {printf \"$rrdtool update $rrddbpath$ifname$queues \" } ";
					$rrdupdatesh .= "{ ";
					$rrdupdatesh .= "if ((\$1 == \"queue\") && ( \$2 ~ /^q/ )) { ";
					$rrdupdatesh .= "dsname = dsname \":\" \$2 ; ";
					$rrdupdatesh .= "q=1; ";
					$rrdupdatesh .= "} ";
					$rrdupdatesh .= "else if ((\$4 == \"bytes:\") && ( q == 1 ) ) { ";
					$rrdupdatesh .= "dsdata = dsdata \":\" \$5 ; ";
					$rrdupdatesh .= "q=0; ";
					$rrdupdatesh .= "} ";
					$rrdupdatesh .= "} END { ";
					$rrdupdatesh .= "dsname = substr(dsname,2); ";
					$rrdupdatesh .= "dsdata = substr(dsdata,2); ";
					$rrdupdatesh .= "printf \"-t \" dsname \" N:\" dsdata }' ";
					$rrdupdatesh .= "dsname=\"\" dsdata=\"\"`\n\n";

					$rrdupdatesh .= "` pfctl -vsq | awk 'BEGIN {printf \"$rrdtool update $rrddbpath$ifname$queuesdrop \" } ";
					$rrdupdatesh .= "{ ";
					$rrdupdatesh .= "if ((\$1 == \"queue\") && ( \$2 ~ /^q/ )) { ";
					$rrdupdatesh .= "dsname = dsname \":\" \$2 ; ";
					$rrdupdatesh .= "q=1; ";
					$rrdupdatesh .= "} ";
					$rrdupdatesh .= "else if ((\$4 == \"bytes:\") && ( q == 1 ) ) { ";
					$rrdupdatesh .= "dsdata = dsdata \":\" \$8 ; ";
					$rrdupdatesh .= "q=0; ";
					$rrdupdatesh .= "} ";
					$rrdupdatesh .= "} END { ";
					$rrdupdatesh .= "dsname = substr(dsname,2); ";
					$rrdupdatesh .= "dsdata = substr(dsdata,2); ";
					$rrdupdatesh .= "printf \"-t \" dsname \" N:\" dsdata }' ";
					$rrdupdatesh .= "dsname=\"\" dsdata=\"\"`\n\n";
				}
			}
		}
		$i++;

		/* System only statistics */
		$ifname = "system";

			/* STATES, create pf states database */
			if(! file_exists("$rrddbpath$ifname$states")) {
				/* create rrd file if it does not exist */
				log_error("Create RRD database $rrddbpath$ifname$states");
				$rrdcreate = "$rrdtool create $rrddbpath$ifname$states --step $rrdstatesinterval ";
				$rrdcreate .= "DS:pfrate:GAUGE:$statesvalid:0:10000000 ";
				$rrdcreate .= "DS:pfstates:GAUGE:$statesvalid:0:10000000 ";
				$rrdcreate .= "DS:pfnat:GAUGE:$statesvalid:0:10000000 ";
				$rrdcreate .= "DS:srcip:GAUGE:$statesvalid:0:10000000 ";
				$rrdcreate .= "DS:dstip:GAUGE:$statesvalid:0:10000000 ";
				$rrdcreate .= "RRA:MIN:0.5:1:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:5:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:60:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:720:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:720:1000";

				$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
				if($rrdcreatereturn != 0) {
			                log_error("RRD create failed exited with $rrdcreatereturn, the
						error is: $rrdcreateoutput[0]\n");
				}
			}

			/* enter UNKNOWN values in the RRD so it knows we rebooted. */
			if($g['booting']) {
				exec("$rrdtool update $rrddbpath$ifname$states N:U:U:U:U:U");
			}

 			/* the pf states gathering function. */
 			$rrdupdatesh .= "\n";
			$rrdupdatesh .= "pfctl_si_out=\"` $pfctl -si > /tmp/pfctl_si_out `\"\n";
			$rrdupdatesh .= "pfctl_ss_out=\"` $pfctl -ss > /tmp/pfctl_ss_out`\"\n";
			$rrdupdatesh .= "pfrate=\"` cat /tmp/pfctl_si_out | egrep \"inserts|removals\" | awk '{ pfrate = \$3 + pfrate } {print pfrate}'|tail -1 `\"\n";
			$rrdupdatesh .= "pfstates=\"` cat /tmp/pfctl_ss_out | egrep -v \"<\\-.*?<\\-|\\->.*?\\->\" | wc -l|sed 's/ //g'`\"\n";
			$rrdupdatesh .= "pfnat=\"` cat /tmp/pfctl_ss_out | egrep '<\\-.*?<\\-|\\->.*?\\->' | wc -l|sed 's/ //g' `\"\n";
			$rrdupdatesh .= "srcip=\"` cat /tmp/pfctl_ss_out | egrep -v '<\\-.*?<\\-|\\->.*?\\->' | grep '\\->' | awk '{print \$3}' | awk -F: '{print \$1}' | sort -u|wc -l|sed 's/ //g' `\"\n";
			$rrdupdatesh .= "dstip=\"` cat /tmp/pfctl_ss_out | egrep -v '<\\-.*?<\\-|\\->.*?\\->' | grep '<\\-' | awk '{print \$3}' | awk -F: '{print \$1}' | sort -u|wc -l|sed 's/ //g' `\"\n";
			$rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$states N:\$pfrate:\$pfstates:\$pfnat:\$srcip:\$dstip\n\n";

			/* End pf states statistics */

			/* CPU, create CPU statistics database */
			if(! file_exists("$rrddbpath$ifname$proc")) {
				/* create rrd file if it does not exist */
				log_error("Create RRD database $rrddbpath$ifname$proc");
				$rrdcreate = "$rrdtool create $rrddbpath$ifname$proc --step $rrdprocinterval ";
				$rrdcreate .= "DS:user:GAUGE:$procvalid:0:10000000 ";
				$rrdcreate .= "DS:nice:GAUGE:$procvalid:0:10000000 ";
				$rrdcreate .= "DS:system:GAUGE:$procvalid:0:10000000 ";
				$rrdcreate .= "DS:interrupt:GAUGE:$procvalid:0:10000000 ";
				$rrdcreate .= "DS:processes:GAUGE:$procvalid:0:10000000 ";
				$rrdcreate .= "RRA:MIN:0.5:1:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:5:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:60:1000 ";
				$rrdcreate .= "RRA:MIN:0.5:720:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
				$rrdcreate .= "RRA:MAX:0.5:720:1000";

				$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
				if($rrdcreatereturn != 0) {
			                log_error("RRD create failed exited with $rrdcreatereturn, the
						error is: $rrdcreateoutput[0]\n");
				}
			}

			/* enter UNKNOWN values in the RRD so it knows we rebooted. */
			if($g['booting']) {
				exec("$rrdtool update $rrddbpath$ifname$proc N:U:U:U:U:U");
			}

 			/* the CPU stats gathering function. */
                        $rrdupdatesh .= "`$top -d 2 -s 1 0 | $awk '{gsub(/%/, \"\")} BEGIN { \\\n";
                        $rrdupdatesh .= "printf \"$rrdtool update $rrddbpath$ifname$proc \" } \\\n";
                        $rrdupdatesh .= "{ if ( \$2 == \"processes:\" ) { processes = \$1; } \\\n";
                        $rrdupdatesh .= "else if ( \$1 == \"CPU:\" ) { user = \$2; nice = \$4; sys = \$6; interrupt = \$8; } \\\n";
                        $rrdupdatesh .= "} END { printf \"N:\"user\":\"nice\":\"sys\":\"interrupt\":\"processes }'`\n\n";

			/* End CPU statistics */

			/* SPAMD, set up the spamd rrd file */
			if (isset($config['installedpackages']['spamdsettings']) &&
				 isset ($config['installedpackages']['spamdsettings']['config'][0]['enablerrd'])) {
				/* set up the spamd rrd file */
				if (!file_exists("$rrddbpath$ifname$spamd")) {
						/* create rrd file if it does not exist */
						log_error("Create RRD database $rrddbpath$ifname$spamd");
						$rrdcreate = "$rrdtool create $rrddbpath$ifname$spamd --step $rrdspamdinterval ";
						$rrdcreate .= "DS:conn:GAUGE:$spamdvalid:0:10000 ";
						$rrdcreate .= "DS:time:GAUGE:$spamdvalid:0:86400 ";
						$rrdcreate .= "RRA:MIN:0.5:1:1000 ";
						$rrdcreate .= "RRA:MIN:0.5:5:1000 ";
						$rrdcreate .= "RRA:MIN:0.5:60:1000 ";
						$rrdcreate .= "RRA:MIN:0.5:720:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:720:1000";

					$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
					if ($rrdcreatereturn != 0) {
						log_error("RRD create failed exited with $rrdcreatereturn, the
							error is: $rrdcreateoutput[0]\n");
					}
				}

				$rrdupdatesh .= "\n";
				$rrdupdatesh .= "# polling spamd for connections and tarpitness \n";
				$rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$spamd \\\n";
				$rrdupdatesh .= "`$php -q $spamd_gather`\n";

			}
		/* End System statistics */

		$rrdupdatesh .= "sleep 60\n";
		$rrdupdatesh .= "done\n";
		log_error("Creating rrd update script");
		/* write the rrd update script */
		$updaterrdscript = "{$g['vardb_path']}/rrd/updaterrd.sh";
		$fd = fopen("$updaterrdscript", "w");
		fwrite($fd, "$rrdupdatesh");
		fclose($fd);

		/* kill off traffic collectors */
		kill_traffic_collector();

		/* start traffic collector */
		mwexec_bg("/usr/bin/nice -n20 /bin/sh $updaterrdscript");

	} else {
		/* kill off traffic collectors */
		kill_traffic_collector();
	}

	if($g['booting']) 
		echo "done.\n";
		
}

function kill_traffic_collector() {
		mwexec("/usr/bin/killall -9 rrdtool");
		mwexec("ps awwwux | grep '/[u]pdaterrd.sh' | awk '{print $2}' | xargs kill -9");
}

function update_filter_reload_status($text) {
	global $g;
	$fd = fopen("{$g['varrun_path']}/filter_reload_status", "w");
	fwrite($fd, $text);
	fclose($fd);
}

function get_interface_gateway($interface) {
	global $config, $g;
	$interface = strtolower($interface);
	/*  if we are dhclient, obtain the gateway from the tmp file, otherwise
	 *  grab the address from the configuration file.
	 */
	$tmpif = convert_real_interface_to_friendly_interface_name($interface);
	if($tmpif <> $interface)
		$interface = $tmpif;
	$realif = $config['interfaces'][$interface]['if'];
	if(file_exists("{$g['tmp_path']}/{$realif}_router")) {
		$gw = file_get_contents("{$g['tmp_path']}/{$realif}_router");
		$gw = rtrim($gw);
	} else {
		$gw = $config['interfaces'][$interface]['gateway'];
	}
	/* if wan is requested, return it */
	if($interface == "wan")
		return str_replace("\n", "", `/usr/bin/netstat -rn | /usr/bin/awk '/default/ {print $2}'`);
	/* return gateway */
	return $gw;
}

function is_dhcp_server_enabled() {
	/* DHCP enabled on any interfaces? */
	global $config, $g;
	$dhcpdcfg = $config['dhcpd'];
	$dhcpdenable = false;
	if($config['dhcpd']) {
		foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
			if (isset ($dhcpifconf['enable']) && (($dhcpif == "lan") || (isset ($config['interfaces'][$dhcpif]['enable']) && $config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
				$dhcpdenable = true;
			if (isset ($dhcpifconf['enable']) && (($dhcpif == "wan") || (isset ($config['interfaces'][$dhcpif]['enable']) && $config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
				$dhcpdenable = true;
		}
	}
	return $dhcpdenable;
}

/* return outside interfaces with a gateway */
function get_interfaces_with_gateway() {
	global $config;
	$ints = array();
	$vfaces = array(
			'bridge.?*',
			'ppp.?*',
			'sl.?*',
			'gif.?*',
			'faith.?*',
			'lo.?*',
			'ng.?*',
			'vlan.?*',
			'pflog.?*',
			'pfsync.?*',
			'enc.?*',
			'tun.?*',
			'carp.?*'
		);	
	$ifdescrs = get_interface_list("active","physical",$vfaces);


	/* loop interfaces, check config for outbound */
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$friendly = $ifname['friendly'];
		if ($config['interfaces'][$friendly]['ipaddr'] == "dhcp") {
			$ints[] = $friendly;
			continue;
		}
		if ($config['interfaces'][$friendly]['ipaddr'] == "pppoe") {
			$ints[] = $friendly;
			continue;
		}
		if ($config['interfaces'][$friendly]['ipaddr'] == "pptp") {
			$ints[] = $friendly;
			continue;
		}
		if ($config['interfaces'][$friendly]['gateway'] <> "") {
			$ints[] = $friendly;
			continue;
		}
	}
	return $ints;
}

/* return true if interface has a gateway */
function interface_has_gateway($friendly) {
	$friendly = strtolower($friendly);
	if(in_array($friendly, get_interfaces_with_gateway())) {
		return true;
	} else {
		/* extra check for real interface names if it falls through */
		$friendly = convert_real_interface_to_friendly_interface_name($friendly);
		return(in_array($friendly, get_interfaces_with_gateway()));
	}
}

//returns interface information
function get_interface_info($ifdescr) {
	global $config, $linkinfo, $netstatrninfo;

	$ifinfo = array();

	/* find out interface name */
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	if ($ifdescr == "wan")
		$ifinfo['if'] = get_real_wan_interface();
	else
		$ifinfo['if'] = $ifinfo['hwif'];

	/* run netstat to determine link info */

	unset($linkinfo);
	exec("/usr/bin/netstat -I " . $ifinfo['hwif'] . " -nWb -f link", $linkinfo);
	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if (preg_match("/\*$/", $linkinfo[0])) {
		$ifinfo['status'] = "down";
	} else {
		$ifinfo['status'] = "up";
	}

	if (!strstr($ifinfo['if'],'tun')) {
		$ifinfo['macaddr'] = $linkinfo[3];
		$ifinfo['inpkts'] = $linkinfo[4];
		$ifinfo['inerrs'] = $linkinfo[5];
		$ifinfo['inbytes'] = $linkinfo[6];
		$ifinfo['outpkts'] = $linkinfo[7];
		$ifinfo['outerrs'] = $linkinfo[8];
		$ifinfo['outbytes'] = $linkinfo[9];
		$ifinfo['collisions'] = $linkinfo[10];
	} else {
		$ifinfo['inpkts'] = $linkinfo[3];
		$ifinfo['inbytes'] = $linkinfo[5];
		$ifinfo['outpkts'] = $linkinfo[6];
		$ifinfo['outbytes'] = $linkinfo[8];
	}

	/* DHCP? -> see if dhclient is up */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "dhcp")) {
		/* see if dhclient is up */
		if (is_dhcp_running("wan") == true)
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";
	}
	/* loop through optional interfaces looking to see if they are dhcp */
        for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
                $ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
                if (($ifdescr == "opt{$j}") && ($config['interfaces']['opt' . $j]['ipaddr'] == "dhcp")) {
                        /* see if dhclient is up */
                        if (is_dhcp_running("opt{$j}") == true)
                                $ifinfo['dhcplink'] = "up";
                        else
                                $ifinfo['dhcplink'] = "down";
                }
        }

	/* PPPoE interface? -> get status from virtual interface */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pppoe")) {
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pppoelink'] = "down";
		} else {
			/* get PPPoE link status for dial on demand */
			$ifconfiginfo = "";
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);

			$ifinfo['pppoelink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pppoelink'] = "down";
			}
		}
	}

	/* PPTP interface? -> get status from virtual interface */
	if (($ifdescr == "wan") && ($config['interfaces']['wan']['ipaddr'] == "pptp")) {
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pptplink'] = "down";
		} else {
			/* get PPTP link status for dial on demand */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);

			$ifinfo['pptplink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pptplink'] = "down";
			}
		}
	}

	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['hwif'], $ifconfiginfo);
		$matches = "";
		foreach ($ifconfiginfo as $ici) {

			/* don't list media/speed for wireless cards, as it always
			   displays 2 Mbps even though clients can connect at 11 Mbps */
			if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: IEEE 802.11 Wireless Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			}

			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active")
					$ifinfo['status'] = $matches[1];
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"')
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				else
					$ifinfo['ssid'] = $matches[1];
			}
		}

		if ($ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down") {
			/* try to determine IP address and netmask with ifconfig */
			unset($ifconfiginfo);
			exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);

			foreach ($ifconfiginfo as $ici) {
				if (preg_match("/inet (\S+)/", $ici, $matches)) {
					$ifinfo['ipaddr'] = $matches[1];
				}
				if (preg_match("/netmask (\S+)/", $ici, $matches)) {
					if (preg_match("/^0x/", $matches[1]))
						$ifinfo['subnet'] = long2ip(hexdec($matches[1]));
				}
			}

			if ($ifdescr == "wan") {
				/* run netstat to determine the default gateway */
				unset($netstatrninfo);
				exec("/usr/bin/netstat -rnf inet", $netstatrninfo);

				foreach ($netstatrninfo as $nsr) {
					if (preg_match("/^default\s*(\S+)/", $nsr, $matches)) {
						$ifinfo['gateway'] = $matches[1];
					}
				}
			} else {
				/* deterimine interface gateway */
				$int = convert_friendly_interface_to_real_interface_name($ifdescr);
				$gw = get_interface_gateway($int);
				if($gw)
					$ifinfo['gateway'] = $gw;
			}
		}
	}

	$bridge = "";
	$int = "";
	$int = convert_friendly_interface_to_real_interface_name($ifdescr);
	$bridge = link_int_to_bridge_interface($int);
	if($bridge) {
		$bridge_text = `/sbin/ifconfig {$bridge}`;
		if(stristr($bridge_text, "blocking") <> false) {
			$ifinfo['bridge'] = "<b><font color='red'>blocking</font></b> - check for ethernet loops";
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "learning") <> false) {
			$ifinfo['bridge'] = "learning";
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "forwarding") <> false) {
			$ifinfo['bridge'] = "forwarding";
			$ifinfo['bridgeint'] = $bridge;
		}
	}

	return $ifinfo;
}


//returns cpu speed of processor. Good for determining capabilities of machine
function get_cpu_speed() {
	 return exec("sysctl hw.clockrate | awk '{ print $2 }'");
}

/* check if the wan interface is up
 * Wait for a maximum of 10 seconds
 * If the interface is up before then continue
 */
function is_wan_interface_up($interface) {
	global $g;
	global $config;
	$i = 0;
	while($i < 10) {
		if(get_interface_gateway($interface)) {
			return true;
		} else {
			sleep(1);
		}
		$i++;
	}
	return false;
}

/****f* pfsense-utils/safe_write_file
 * NAME
 *   safe_write_file - Write a file out atomically
 * DESCRIPTION
 *   safe_write_file() Writes a file out atomically by first writing to a
 *   temporary file of the same name but ending with the pid of the current
 *   process, them renaming the temporary file over the original.
 * INPUTS
 *   $filename  - string containing the filename of the file to write
 *   $content   - string containing the file content to write to file
 *   $force_binary      - boolean denoting whether we should force binary
 *   mode writing.
 * RESULT
 *   boolean - true if successful, false if not
 ******/
function safe_write_file($file, $content, $force_binary) {
        $tmp_file = $file . "." . getmypid();
        $write_mode = $force_binary ? "wb" : "w";

        $fd = fopen($tmp_file, $write_mode);
        if (!$fd) {
                // Unable to open temporary file for writing
                return false;
        }
        if (!fwrite($fd, $content)) {
                // Unable to write to temporary file
                fclose($fd);
                return false;
        }
        fclose($fd);

        if (!rename($tmp_file, $file)) {
                // Unable to move temporary file to original
                unlink($tmp_file);
                return false;
        }
        return true;
}

/* Write out all the found IP addresses to a file
 * so we can compare it on change */
function add_hostname_to_watch($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A $hostname", $domrecords, $rethost);
		if($rethost == 0) {
			foreach($domrecords as $domr) {
				$doml = explode(" ", $domr);
				$domip = $doml[3];
				/* fill array with domain ip addresses */
				if(is_ipaddr($domip)) {
					$domips[] = $domip;
				}
			}
		}
		sort($domips);
		$contents = "";
		if(! empty($domips)) {
			foreach($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
		file_put_contents("/var/db/dnscache/$hostname", $contents);
	}
}

/* Compare the current hostname DNS to the DNS cache we made
 * if it has changed we return the old records
 * if no change we return true */
function compare_hostname_to_dnscache($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$hostname = trim($hostname);
	if(is_readable("/var/db/dnscache/{$hostname}")) {
		$oldcontents = file_get_contents("/var/db/dnscache/{$hostname}");
	} else {
		$oldcontents = "";
	}
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A $hostname", $domrecords, $rethost);
		if($rethost == 0) {
			foreach($domrecords as $domr) {
				$doml = explode(" ", $domr);
				$domip = $doml[3];
				/* fill array with domain ip addresses */
				if(is_ipaddr($domip)) {
					$domips[] = $domip;
				}
			}
		}
		sort($domips);
		$contents = "";
		if(! empty($domips)) {
			foreach($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
	}

	if(trim($oldcontents) != trim($contents)) {
		if($g['debug']) {
			log_error("DNSCACHE: Found old IP {$oldcontents} and new IP {$contents}");
		}
		return ($oldcontents);
	} else {
		return false;
	}
}

function is_fqdn($fqdn) {
	$hostname = false;
	if(preg_match("/[-A-Z0-9\.]+\.[-A-Z0-9\.]+/i", $fqdn)) {
		$hostname = true;
	}
	if(preg_match("/\.\./", $fqdn)) {
		$hostname = false;
	}
	if(preg_match("/^\./i", $fqdn)) { 
		$hostname = false;
	}
	if(preg_match("/\//i", $fqdn)) {
		$hostname = false;
	}
	return($hostname);
}

function update_alias_names_upon_change($section, $subsection, $fielda, $fieldb, $new_alias_name, $origname) {
	global $g, $config, $pconfig, $debug;
	if(!$origname) 
		return;

	if($debug) $fd = fopen("{$g['tmp_path']}/print_r", "a");
	if($debug) fwrite($fd, print_r($pconfig, true));

	if($fieldb) {
		if($debug) fwrite($fd, "fieldb exists\n");
		for ($i = 0; isset($config["$section"]["$subsection"][$i]["$fielda"]); $i++) {
			if($debug) fwrite($fd, "$i\n");
			if($config["$section"]["$subsection"][$i]["$fielda"]["$fieldb"] == $origname) {
				if($debug) fwrite($fd, "Setting old alias value $origname to $new_alias_name\n");
				$config["$section"]["$subsection"][$i]["$fielda"]["$fieldb"] = $new_alias_name;
			}
		}	
	} else {
		if($debug) fwrite($fd, "fieldb does not exist\n");
		for ($i = 0; isset($config["$section"]["$subsection"][$i]["$fielda"]); $i++) {
			if($config["$section"]["$subsection"][$i]["$fielda"] == $origname) {
				$config["$section"]["$subsection"][$i]["$fielda"] = $new_alias_name;
				if($debug) fwrite($fd, "Setting old alias value $origname to $new_alias_name\n");
			}
		}
	}

	if($debug) fclose($fd);

}

?>