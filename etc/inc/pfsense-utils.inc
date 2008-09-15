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
 * Copyright (C) 2004-2007 Scott Ullrich (sullrich@gmail.com)
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

/****f* pfsense-utils/have_natonetooneruleint_access
 * NAME
 *   have_natonetooneruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall nat one to one interface
 ******/
function have_natonetooneruleint_access($if) {
	$security_url = "firewall_nat_1to1_edit.php?if=". strtolower($if);
	if(isAllowedPage($security_url, $allowed)) 
		return true;
	return false;
}

/****f* pfsense-utils/have_natpfruleint_access
 * NAME
 *   have_natpfruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall nat port forward interface
 ******/
function have_natpfruleint_access($if) {
	$security_url = "firewall_nat_edit.php?if=". strtolower($if);
	if(isAllowedPage($security_url, $allowed)) 
		return true;
	return false;
}

/****f* pfsense-utils/have_ruleint_access
 * NAME
 *   have_ruleint_access
 * INPUTS
 *	 none
 * RESULT
 *   returns true if user has access to edit a specific firewall interface
 ******/
function have_ruleint_access($if) {
	$security_url = "firewall_rules.php?if=". strtolower($if);
	if(isAllowedPage($security_url)) 
		return true;
	return false;
}

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
				write_config("Installed 15 minute filter reload for Time Based Rules");
				configure_cron();
			}
		break;
		case false:
			if($is_installed == true) {
				if($x > 0) {
					unset($config['cron']['item'][$x]);
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

		/* if list */
        	$ifdescrs = get_configured_interface_list();

		for ($iif = 0; $iif < $nif; $iif++) {

			if ($ispptp) {
				$aline['interface'] = "\$pptp ";
			} else if ($ispppoe) {
				$aline['interface'] = "\$pppoe ";
			} else {
				// translate wan, man, lan, opt to real interface.
				$interface = $rule['interface'];
				$temp = $config['interfaces'][$interface]['descr'];
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
	if(isset($config['system']['do_not_use_nic_microcode']))
		return;

	if($g['booting']) {
	/* translate wan, lan, opt -> real interface if needed */
	$int = filter_translate_type_to_real_interface($interface);
	if($int <> "") $interface = $int;
	$int_family = preg_split("/[0-9]+/", $int);
	$options = strtolower(`/sbin/ifconfig {$interface} | grep options`);
	$supported_ints = array('fxp');
	if (in_array($int_family, $supported_ints))
		mwexec("/sbin/ifconfig {$interface} link0");

	if($config['system']['disablechecksumoffloading'])
		return;

	if(stristr($options, "txcsum") == true)
	    mwexec("/sbin/ifconfig {$interface} txcsum 2>/dev/null");
	if(stristr($options, "rxcsum") == true)
	    mwexec("/sbin/ifconfig {$interface} rxcsum 2>/dev/null");
	if(stristr($options, "polling") == true)
	    mwexec("/sbin/ifconfig {$interface} polling 2>/dev/null");
	} else {
		mwexec("sysctl kern.polling.enable=0");
	}
	return;
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

	if(isset($config['system']['polling']))
		$supported_ints = array('dc', 'em', 'fwe', 'fwip', 'fxp', 'ixgb', 'ste', 'nge', 're', 'rl', 'sf', 'sis', 'ste', 'vge', 'vr', 'xl');
	else
		$supported_ints = array();

	/* if list */
        $iflist = get_configured_interface_list();

	foreach ($iflist as $ifent => $ifname) {
		$real_interface = convert_friendly_interface_to_real_interface_name($ifname);
		$supported = false;
		foreach($supported_ints as $supported) {
			if(stristr($real_interface, $supported)) {
				$supported = true;
			}
		}
		if ($supported == true) {
			mwexec("/sbin/ifconfig {$real_interface} polling");
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

	/* if list */
        $ifdescrs = get_configured_interface_list();

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
	$friendly = convert_real_interface_to_friendly_interface_name($interface);
	if (preg_match($g['wireless_regex'], $interface)) {
		$config['interfaces'][$friendly]['wireless'] = array();
	} else {
		unset($config['interfaces'][$friendly]['wireless']);
	}
	if(isset($config['interfaces'][$friendly]['wireless'])) {
		return true;
	} else {
		return false;
	}
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
	global $config;
	
	if (is_array($config['bridges']['bridged']))
		foreach ($config['bridges']['bridged'] as $bridge) 
			if(stristr($bridge['members'], "{$int}")) 
				return "{$bridge['bridgeif']}";
}

function link_carp_interface_to_parent($interface) {
	global $config;
	if($interface == "") return;

	/* if list */
        $ifdescrs = get_configured_interface_list();

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

	/* if list */
        $ifdescrs = get_configured_interface_list();

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
	global $config;

	if(!$interface)
		return false;
		
	$ints = get_interface_arr();

	if(strtolower($interface) == "lan")
		if(!$config['interfaces']['lan']) 
			return false;

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
	$ret = exec_command("/sbin/route -n get {$ipaddress} | /usr/bin/grep interface | /usr/bin/awk '{ print \$2; };'");
	return $ret;
}

/*
 * find_ip_interface($ip): return the interface where an ip is defined
 */
function find_ip_interface($ip) {

	/* if list */
        $ifdescrs = get_configured_interface_list();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		$int = convert_friendly_interface_to_real_interface_name($ifname);
		$ifconfig = exec_command("/sbin/ifconfig {$int}");
		if(stristr($ifconfig,$ip) <> false)
			return $int;
	}
	return false;
}

/*
 *  filter_translate_type_to_real_interface($interface): 
 *		returns the real hardware interface name for a friendly interface.  ie: wan
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
		if(stristr($fci, $ip) == true)
			return "carp{$carps}";
		$carps++;
	}
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
function add_text_to_file($file, $text, $replace = false) {
	if(file_exists($file) and is_writable($file)) {
		$filecontents = file($file);
		$fout = fopen($file, "w");

		$filecontents = array_map('rtrim', $filecontents);
		array_push($filecontents, $text);
		if ($replace)
			$filecontents = array_unique($filecontents);

		$file_text = implode("\n", $filecontents);

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
 *   exec_command_and_return_output: execute command and update output window dynamically
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

	$wanif = $interface;
	switch ($interface) {
		case "pptp":
			$wanif = "pptp";
			break;
		case "pppoe":
			$wanif = "pppoe";
			break;
		case "openvpn":
			$wanif = "openvpn";
			break;
		case "enc0":
			$wanif = "enc0";
			break;
		/* XXX: dial in support?!
		case "ppp":
			$wanif = "ppp";
			break;
		*/
		default:
			$iflist = get_configured_interface_with_descr(false,true);

		foreach ($iflist as $if => $ifdesc) {
			if ($interface == $if || $interface == $ifdesc) {

			$cfg = $config['interfaces'][$if];

			switch ($cfg['ipaddr']) {
			case "carpdev-dhcp":
				$viparr = &$config['virtualip']['vip'];
				$counter = 0;
				if(is_array($viparr))
				foreach ($viparr as $vip) {
					if ($vip['mode'] == "carpdev-dhcp") {
						if($vip['interface'] == $if) {
							$wanif =  "carp{$counter}";
							break;
						}
						$counter++;
					} else if ($vip['mode'] = "carp") 
						$counter++;
				}
				break;
			case "pppoe": 
				if ($if == "wan")
					$wanif = "pppoe0";
				else
					$wanif = "pppoe" . substr($if,3);
				break;
			case "pptp": 
				if ($if == "wan")
					$wanif = "pptp0";
				else
					$wanif = "pptp" . substr($if, 3);
				break;
			default:
				if (isset($cfg['ispointtopoint']) && $cfg['pointtopoint'])
					$wanif = "ppp0"; // XXX: PPP needs to convert to mpd
				else	
					$wanif = $cfg['if'];
				break;
			}
			break;

			break;
			}
		}
		break;
	}

    return $wanif;
}

/*
 * convert_real_interface_to_friendly_interface_name($interface): convert fxp0 -> wan, etc.
 */
function convert_real_interface_to_friendly_interface_name($interface = "wan") {
	global $config;
	
	if (stristr($interface, "pppoe")) {
		$index = substr($interface, 5);
		if (intval($index) > 0)
			return "opt{$index}";
		else
			return "wan";
	} else if (stristr($interface, "pptp")) {
		$index = substr($interface, 4);
                if (intval($index) > 0)
                        return "opt{$index}";
                else
                        return "wan";	
	} else if (stristr($interface, "carp")) {
		$index = substr($interface, 4);
		$counter = 0;
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] == "carpdev-dhcp" || $vip['mode'] == "carp")  {
				if (intval($index) == $counter)
					return $vip['interface'];
				$counter++;
			}
		}
	}
		
	/* if list */
        $ifdescrs = get_configured_interface_list(false, true);

	foreach ($ifdescrs as $if => $ifname) {
		if($config['interfaces'][$if]['if'] == $interface)
			return $ifname;

		/* XXX: ermal - The 3 lines below are totally bogus code. */
		$int = filter_translate_type_to_real_interface($if);
		if($ifname == $interface) 
			return $ifname;

		if($int == $interface) 
			return $ifname;
	}
	return NULL;
}

/* attempt to resolve interface to friendly descr */
function convert_friendly_interface_to_friendly_descr($interface) {
        global $config;

        switch ($interface) {
        	case "pptp":
				$ifdesc = "PPTP";
				break;
       		case "pppoe":
				$ifdesc = "PPPoE";
				break;
        	case "openvpn":
				$ifdesc = "OpenVPN";
				break;
        	case "enc0":
			case "ipsec":
				$ifdesc = "IPsec";
				break;
        default:
        	/* if list */
        	$ifdescrs = get_configured_interface_with_descr(false, true);
        	foreach ($ifdescrs as $if => $ifname) {
				if ($if == $interface || $ifname == $interface)
					return $ifname;
        	}
		break;
	}

        return $ifdesc;
}

function convert_real_interface_to_friendly_descr($interface) {
        global $config;

	$ifdesc = convert_real_interface_to_friendly_interface_name("{$interface}");

	if ($ifdesc) {
		$iflist = get_configured_interface_with_descr();
		return $iflist[$ifdesc];
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
	$stats = `/sbin/pfctl -vvsq & /bin/sleep 5`;
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

	if (!is_file($path) && !is_dir($path)) {
		return @mkdir($path, $mode);
	} else {
		return false;
	}
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
	global $g, $config;

	$ip = gethostbyname($g['product_website']);
	if($ip == $g['product_website'])
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
	$ip = gethostbyname($g['product_website']);
	if($ip == $g['product_website'])
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

/****f* pfsense-utils/display_top_tabs
 * NAME
 *   display_top_tabs - display tabs with rounded edges
 * INPUTS
 *   $text      - array of tabs
 * RESULT
 *   null
 ******/
function display_top_tabs(& $tab_array) {
	global $HTTP_SERVER_VARS;
	global $config;
	global $g;

	/*  does the user have access to this tab?
	 *  master user has access to everything.
	 *  if the user does not have access, simply
	 *  unset the tab item.
	 */

	$tab_temp = array ();
	foreach ($tab_array as $ta)
		if(isAllowedPage($ta[2]))
			$tab_temp[] = $ta;
	/*
		// FIXME :	if the checks are not good enough
		//			in isAllowedPage, it needs to be
		//			fixed instead of kludging here

		// TODO: humm what shall we do with pkg_edit.php and pkg.php?
		if ((strpos($link, "pkg.php")) !== false || (strpos($link, "pkg_edit.php")) !== false) {
			$pos_equal = strpos($link, "=");
			$pos_xmlsuffix = strpos($link, ".xml");
			// do we match an absolute url including ?xml= foo
			if(!isAllowedPage($link, $allowed))
				$link = substr($link, $pos_equal +1, ($pos_xmlsuffix - $pos_equal +3));
		}
		// next check - what if the basename contains a query string?
		if ((strpos($link, "?")) !== false) {
			$pos_qmark = strpos($link, "?");
			$link = substr($link, 0, $pos_qmark);
		}
		$authorized_text = print_r($allowed, true);
		if(is_array($authorized))
			if (in_array(basename($link), $authorized))
	*/

	unset ($tab_array);
	$tab_array = & $tab_temp;

	echo "<table cellpadding='0' cellspacing='0'>\n";
	echo " <tr>\n";
	$tabscounter = 0;
	foreach ($tab_array as $ta) {
		if ($ta[1] == true) {
			echo "  <td bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><div id='tabactive'></div></td>\n";
		} else {
			echo "  <td bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><div id='tabdeactive{$tabscounter}'></div></td>\n";
		}
		$tabscounter++;
	}
	echo "</tr>\n<tr>\n";
	foreach ($tab_array as $ta) {
		if ($ta[1] == true) {
			echo "  <td height=\"15\" valign=\"middle\" bgcolor='#EEEEEE' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><B>&nbsp;&nbsp;&nbsp;{$ta[0]}";
			echo "&nbsp;&nbsp;&nbsp;";
			echo "<font size='-12'>&nbsp;</font></B></td>\n";
		} else {
			echo "  <td height=\"15\" valign=\"middle\" bgcolor='#777777' onClick=\"document.location='{$ta[2]}'\" style=\"cursor: pointer;\"><B>&nbsp;&nbsp;&nbsp;<a href='{$ta[2]}'>";
			echo "<font color='white'>{$ta[0]}</font></a>&nbsp;&nbsp;&nbsp;";
			echo "<font size='-12'>&nbsp;</font></B></td>\n";
		}
	}
	echo "</tr>\n<tr>\n";
	foreach ($tab_array as $ta) {
		if ($ta[1] == true) {
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
	for ($x = 0; $x < $tabscounter; $x++)
		echo "Rounded(\"div#tabdeactive{$x}\",\"top\",\"#FFF\",\"#777777\",\"smooth\");\n";
	echo "</script>";
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

	/* if list */
        $iflist = get_configured_interface_list(true);

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

	/* set up interfaces */
	interfaces_configure();

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

	/* if list */
	$iflist = get_configured_interface_list();

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

	/* set up interfaces */
	interfaces_configure();

	/* set up static routes */
	system_routing_configure();

	/* enable routing */
	system_routing_enable();

	/* ensure passwords are sync'd */
//	system_password_configure();

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

function enable_rrd_graphing() {
	global $config, $g, $altq_list_queues;

	if($g['booting']) 
		echo "Generating RRD graphs...";

	$rrddbpath = "/var/db/rrd/";
	$rrdgraphpath = "/usr/local/www/rrd";

	$traffic = "-traffic.rrd";
	$packets = "-packets.rrd";
	$states = "-states.rrd";
	$quality = "-quality.rrd";
	$wireless = "-wireless.rrd";
	$queues = "-queues.rrd";
	$queuesdrop = "-queuedrops.rrd";
	$spamd = "-spamd.rrd";
	$proc = "-processor.rrd";
	$mem = "-memory.rrd";

	$rrdtool = "/usr/local/bin/rrdtool";
	$netstat = "/usr/bin/netstat";
	$awk = "/usr/bin/awk";
	$tar = "/usr/bin/tar";
	$pfctl = "/sbin/pfctl";
	$sysctl = "/sbin/sysctl";
	$php = "/usr/local/bin/php";
	$top = "/usr/bin/top";
	$spamd_gather = "/usr/local/bin/spamd_gather_stats.php";
	$ifconfig = "/sbin/ifconfig";

	$rrdtrafficinterval = 60;
	$rrdqualityinterval = 60;
	$rrdwirelessinterval = 60;
	$rrdqueuesinterval = 60;
	$rrdqueuesdropinterval = 60;
	$rrdpacketsinterval = 60;
	$rrdstatesinterval = 60;
	$rrdspamdinterval = 60;
	$rrdlbpoolinterval = 60;
	$rrdprocinterval = 60;
	$rrdmeminterval = 60;

	$trafficvalid = $rrdtrafficinterval * 2;
	$qualityvalid = $rrdqualityinterval * 2;
	$wirelessvalid = $rrdwirelessinterval * 2;
	$queuesvalid = $rrdqueuesinterval * 2;
	$queuesdropvalid = $rrdqueuesdropinterval * 2;
	$packetsvalid = $rrdpacketsinterval * 2;
	$statesvalid = $rrdstatesinterval*2;
	$spamdvalid = $rrdspamdinterval * 2;
	$lbpoolvalid = $rrdlbpoolinterval * 2;
	$procvalid = $rrdlbpoolinterval * 2;
	$memvalid = $rrdmeminterval * 2;

	/* Asume GigE for now */
	$downstream = 125000000;
	$upstream = 125000000;

	/* read the shaper config */
	read_altq_config();

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
		$ifdescrs['enc0']['descr'] = "IPsec";
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
				$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

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
				$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

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

			/* QUALITY, create link quality database */
			/* if the interface has a gateway defined, use it */
			if(interface_has_gateway($ifname)) {
				if (!file_exists("$rrddbpath$ifname$quality")) {
					/* create rrd file if it does not exist */
					log_error("Create RRD database $rrddbpath$ifname$quality");
					$rrdcreate = "$rrdtool create $rrddbpath$ifname$quality --step $rrdqualityinterval ";
					$rrdcreate .= "DS:loss:GAUGE:$qualityvalid:0:100 ";
					$rrdcreate .= "DS:roundtrip:GAUGE:$qualityvalid:0:10000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

					$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
					if ($rrdcreatereturn != 0) {
						log_error("RRD create failed exited with $rrdcreatereturn, the error is: $rrdcreateoutput[0]\n");
					}
				}

				/* enter UNKNOWN values in the RRD so it knows we rebooted. */
				if($g['booting']) {
					exec("$rrdtool update $rrddbpath$ifname$quality N:U:U");
				}
				
				$numpings = 5;
				$monitorip = lookup_gateway_monitor_ip_by_name($config['interfaces'][$ifname]['gateway']);
				/* the ping test function. We call this on the last line */
				$rrdupdatesh .= "# Quality collector for {$ifname}\n";
				$rrdupdatesh .= "get_quality_stats_{$ifname} () {\n";
				$rrdupdatesh .= "	packetloss_{$ifname}=100\n";
				$rrdupdatesh .= "	roundtrip_{$ifname}=0\n";
				$rrdupdatesh .= "	local out_{$ifname}\n";
				$rrdupdatesh .= "	out_{$ifname}=`ping -c $numpings -q $monitorip`\n";
				$rrdupdatesh .= "	if [ $? -eq 0 ]; then\n";
				$rrdupdatesh .= "		packetloss_{$ifname}=`echo \$out_{$ifname} | cut -f18 -d' ' | cut -c -1`\n";
				$rrdupdatesh .= "		roundtrip_{$ifname}=`echo \$out_{$ifname} | cut -f24 -d' ' | cut -f2 -d'/'`\n";
				$rrdupdatesh .= "	fi\n";
				$rrdupdatesh .= "	$rrdtool update $rrddbpath$ifname$quality N:\$packetloss_{$ifname}:\$roundtrip_{$ifname}\n";
				$rrdupdatesh .= "}\n\n";

				$rrdupdatesh .= "get_quality_stats_{$ifname} &\n\n";
			}

			/* WIRELESS, set up the rrd file */
			if($config['interfaces'][$ifname]['wireless']['mode'] == "bss") {
				if (!file_exists("$rrddbpath$ifname$wireless")) {
					/* create rrd file if it does not exist */
					log_error("Create RRD database $rrddbpath$ifname$wireless");
					$rrdcreate = "$rrdtool create $rrddbpath$ifname$wireless --step $rrdwirelessinterval ";
					$rrdcreate .= "DS:snr:GAUGE:$wirelessvalid:0:1000 ";
					$rrdcreate .= "DS:rate:GAUGE:$wirelessvalid:0:1000 ";
					$rrdcreate .= "DS:channel:GAUGE:$wirelessvalid:0:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
					$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";
	
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
					exec("$rrdtool update $rrddbpath$ifname$wireless N:U:U:U");
				}

				$rrdupdatesh .= "\n";
				$rrdupdatesh .= "# polling wireless for interface $ifname $realif \n";
				$rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$wireless N:\\\n";
				$rrdupdatesh .= "`$ifconfig {$realif} list ap| $awk 'gsub(\"M\", \"\") {getline 2;print substr(\$5, 0, length(\$5)-2) \":\" $4 \":\" $3}'`\n";
			}

				/* QUEUES, set up the queues databases */
				if ($altq_list_queues[$ifname]) {
					$altq =& $altq_list_queues[$ifname];
					/* NOTE: Is it worth as its own function?! */
					switch ($altq->GetBwscale()) {
                                        case "Gb":
                                        	$factor = 1000 * 1000 * 1000;
                                                break;
                                        case "Mb":
                                                $factor = 1000 * 1000;
                                                break;
                                        case "Kb":
                                                $factor = 1000;
                                                break;
                                        case "b":
                                        default:
                                                $factor = 1;
                                                break;
                                        }
					$qbandwidth = $altq->GetBandwidth() * $factor;
					if ($qbandwidth <=0)
						$qbandwidth = 100 * 1000 * 1000; /* 100Mbit */
					$qlist =& $altq->get_queue_list($notused);
					if (!file_exists("$rrddbpath$ifname$queues")) {
						/* create rrd file if it does not exist */
						log_error("Create RRD database $rrddbpath$ifname$queues");
						$rrdcreate = "$rrdtool create $rrddbpath$ifname$queues --step $rrdqueuesinterval ";
						/* loop list of shaper queues */
						$q = 0;
						foreach ($qlist as $qname => $q) {
							$rrdcreate .= "DS:$qname:COUNTER:$queuesvalid:0:$qbandwidth ";
						}

						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

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
						foreach ($qlist as $qname => $q) {
							$rrdcreate .= "DS:$qname:COUNTER:$queuesdropvalid:0:$qbandwidth ";
						}

						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

						$rrdcreatel = exec("$rrdcreate 2>&1", $rrdcreateoutput, $rrdcreatereturn);
						if ($rrdcreatereturn != 0) {
							log_error("RRD create failed exited with $rrdcreatereturn, the error is: $rrdcreateoutput[0]\n");
						}
					}

					if($g['booting']) {
						$rrdqcommand = "-t ";
						$rrducommand = "N";
						$q = 0;
						foreach ($qlist as $qname => $q) {
							if($q == 0) {
								$rrdqcommand .= "{$qname}";
							} else {
								$rrdqcommand .= ":{$qname}";
							}
							$q++;
							$rrducommand .= ":U";
						}
						exec("$rrdtool update $rrddbpath$ifname$queues $rrdqcommand $rrducommand");
						exec("$rrdtool update $rrddbpath$ifname$queuesdrop $rrdqcommand $rrducommand");
					}

					/* awk function to gather shaper data */
					/* yes, it's special */
					$rrdupdatesh .= "` pfctl -vsq -i {$realif} | awk 'BEGIN {printf \"$rrdtool update $rrddbpath$ifname$queues \" } ";
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

					$rrdupdatesh .= "` pfctl -vsq -i {$realif} | awk 'BEGIN {printf \"$rrdtool update $rrddbpath$ifname$queuesdrop \" } ";
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
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

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
				$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
				$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";

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
 			$rrdupdatesh .= "`$top -d 2 -s 1 0 | $awk '{gsub(/%/, \"\")} BEGIN { ";
			$rrdupdatesh .= "printf \"$rrdtool update $rrddbpath$ifname$proc \" } ";
			$rrdupdatesh .= "{ if ( \$2 == \"processes:\" ) { ";
			$rrdupdatesh .= "processes = \$1; ";
			$rrdupdatesh .= "} ";
			$rrdupdatesh .= "else if ( \$1 == \"CPU\" ) { ";
			$rrdupdatesh .= "user = \$3; ";
			$rrdupdatesh .= "nice = \$5; ";
			$rrdupdatesh .= "sys = \$7; ";
			$rrdupdatesh .= "interrupt = \$9; ";
			$rrdupdatesh .= "} ";
			$rrdupdatesh .= "} END { ";
			$rrdupdatesh .= "printf \"N:\"user\":\"nice\":\"sys\":\"interrupt\":\"processes ";
			$rrdupdatesh .= "}'`\n\n";

			/* End CPU statistics */

			/* Memory, create Memory statistics database */
			if(! file_exists("$rrddbpath$ifname$mem")) {
				/* create rrd file if it does not exist */
				log_error("Create RRD database $rrddbpath$ifname$mem");
				$rrdcreate = "$rrdtool create $rrddbpath$ifname$mem --step $rrdmeminterval ";
				$rrdcreate .= "DS:active:GAUGE:$memvalid:0:10000000 ";
				$rrdcreate .= "DS:inactive:GAUGE:$memvalid:0:10000000 ";
				$rrdcreate .= "DS:free:GAUGE:$memvalid:0:10000000 ";
				$rrdcreate .= "DS:cache:GAUGE:$memvalid:0:10000000 ";
				$rrdcreate .= "DS:wire:GAUGE:$memvalid:0:10000000 ";
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
				exec("$rrdtool update $rrddbpath$ifname$mem N:U:U:U:U:U");
			}

 			/* the Memory stats gathering function. */
 			$rrdupdatesh .= "`$sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count vm.stats.vm.v_free_count vm.stats.vm.v_cache_count vm.stats.vm.v_wire_count | ";
			$rrdupdatesh .= " $awk '{getline active;getline inactive;getline free;getline cache;getline wire;printf \"$rrdtool update $rrddbpath$ifname$mem N:\"";
			$rrdupdatesh .= "((active/$0) * 100)\":\"((inactive/$0) * 100)\":\"((free/$0) * 100)\":\"((cache/$0) * 100)\":\"(wire/$0 * 100)}'`\n\n";
			
			/* End Memory statistics */

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
						$rrdcreate .= "RRA:MIN:0.5:1440:1500 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";
						$rrdcreate .= "RRA:AVERAGE:0.5:1440:1500 ";
						$rrdcreate .= "RRA:MAX:0.5:1:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:5:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:60:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:720:1000 ";
						$rrdcreate .= "RRA:MAX:0.5:1440:1500 ";

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
		mwexec("ps awwwux | grep '/[u]pdaterrd.sh' | awk '{print $2}' | xargs kill");
}

function update_filter_reload_status($text) {
	global $g;
	config_lock();
	$fd = fopen("{$g['varrun_path']}/filter_reload_status", "w");
	fwrite($fd, $text);
	fclose($fd);
	config_unlock();
}

function get_interface_gateway($interface) {
        global $config, $g;

        /* if wan is requested, return it */
        if($interface == "wan")
                return str_replace("\n", "", `route -n get default | grep gateway | awk '{ print $2 }'`);

	$iflist = get_configured_interface_with_descr();
	/* 
	 * XXX: This is silly at first, but we may be called with the interface
	 *	descr for no apparent reason!!!
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
        else if ($gwcfg['gateway'] <> "") {
        	if (is_array($config['gateways']['gateway_item']))
                	foreach($config['gateways']['gateway_item'] as $gateway) {
                                if($gateway['name'] == $gwcfg['gateway']) {
                                        $gw = $gateway['gateway'];
                        		break;
                		}
        		}
        } 
	
	// for dynamic interfaces we handle them through the $interface_router file.
	if (!is_ipaddr($gw)) {
        	$realif = get_real_wan_interface($interface);
        	if (file_exists("{$g['tmp_path']}/{$realif}_router")) {
                	$gw = file_get_contents("{$g['tmp_path']}/{$realif}_router");
                	$gw = rtrim($gw);
        	}
	}

        /* return gateway */
        return $gw;
}

/* DHCP enabled on any interfaces? */
function is_dhcp_server_enabled() 
{
	global $config, $g;

	$dhcpdenable = false;
	
	if (!is_array($config['dhcpd']))
		return false;

	foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
		if (isset ($dhcpifconf['enable']) && (($dhcpif == "lan") || (isset ($config['interfaces'][$dhcpif]['enable']) && $config['interfaces'][$dhcpif]['if'] && (!link_int_to_bridge_interface($dhcpif)))))
			$dhcpdenable = true;
		if (isset ($dhcpifconf['enable']) && (($dhcpif == "wan") || (isset ($config['interfaces'][$dhcpif]['enable']) && $config['interfaces'][$dhcpif]['if'] && (!link_int_to_bridge_interface($dhcpif)))))
			$dhcpdenable = true;
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
		switch ($config['interfaces'][$friendly]['ipaddr']) {
		case "dhcp":
		case "carpdev-dhcp":
		case "pppoe":
		case "pptp":
			$ints[] = $friendly;
			break;	
		default:
			if ($config['interfaces'][$friendly]['pointtopoint']) 
				$ints[] = $friendly;
			else if ($config['interfaces'][$friendly]['gateway'] <> "")
				$ints[] = $friendly;
			break;
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

/****f* pfsense-utils/isAjax
 * NAME
 *   isAjax - reports if the request is driven from prototype
 * INPUTS
 *   none
 * RESULT
 *   true/false
 ******/
function isAjax() {
	return isset ($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

//returns interface information
function get_interface_info($ifdescr) {
	global $config, $linkinfo, $netstatrninfo;

	$ifinfo = array();
	/* if list */
	$iflist = get_configured_interface_with_descr(false,true);
	
	$found = false;
    	foreach ($iflist as $if => $ifname) {
    	if ($ifdescr == $if || $ifdescr == $ifname) {
			$ifinfo['hwif'] = $config['interfaces'][$if]['if'];
			$ifinfo['if'] = get_real_wan_interface($if);
			$found = true;
			break;
		}
	}
	if ($found == false)
		return;

	/* run netstat to determine link info */

	unset($linkinfo);
	exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if (preg_match("/\*$/", $linkinfo[0])) {
		$ifinfo['status'] = "down";
	} else {
		$ifinfo['status'] = "up";
	}

	if (preg_match("/^enc|^tun|^ppp|^pptp|^ovpn/i", $ifinfo['if'])) {
		$ifinfo['inpkts'] = $linkinfo[3];
                $ifinfo['inbytes'] = $linkinfo[5];
                $ifinfo['outpkts'] = $linkinfo[6];
                $ifinfo['outbytes'] = $linkinfo[8];
	} else {
		$ifinfo['macaddr'] = $linkinfo[3];
		$ifinfo['inpkts'] = $linkinfo[4];
		$ifinfo['inerrs'] = $linkinfo[5];
		$ifinfo['inbytes'] = $linkinfo[6];
		$ifinfo['outpkts'] = $linkinfo[7];
		$ifinfo['outerrs'] = $linkinfo[8];
		$ifinfo['outbytes'] = $linkinfo[9];
		$ifinfo['collisions'] = $linkinfo[10];
	}

	$ifconfiginfo = "";
	unset($ifconfiginfo);
	exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
	foreach ($ifconfiginfo as $ici) {
		if (preg_match("/inet (\S+)/", $ici, $matches)) 
			$ifinfo['ipaddr'] = $matches[1];
		if (preg_match("/netmask (\S+)/", $ici, $matches)) 
			if (preg_match("/^0x/", $matches[1]))
				$ifinfo['subnet'] = long2ip(hexdec($matches[1]));
	}

	switch ($config['interfaces'][$if]['ipaddr']) {
	/* DHCP? -> see if dhclient is up */
	case "dhcp":
	case "carpdev-dhcp":
        /* see if dhclient is up */
        if (is_dhcp_running($ifinfo['if']) == true)
        	$ifinfo['dhcplink'] = "up";
        else
            $ifinfo['dhcplink'] = "down";

		break;
	/* PPPoE interface? -> get status from virtual interface */
	case "pppoe":
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pppoelink'] = "down";
		} else {
			/* get PPPoE link status for dial on demand */
			$ifinfo['pppoelink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pppoelink'] = "down";
			}
		}
		break;
	/* PPTP interface? -> get status from virtual interface */
	case "pptp":
		unset($linkinfo);
		exec("/usr/bin/netstat -I " . $ifinfo['if'] . " -nWb -f link", $linkinfo);
		$linkinfo = preg_split("/\s+/", $linkinfo[1]);
		if (preg_match("/\*$/", $linkinfo[0])) {
			$ifinfo['pptplink'] = "down";
		} else {
			/* get PPTP link status for dial on demand */
			$ifinfo['pptplink'] = "up";

			foreach ($ifconfiginfo as $ici) {
				if (strpos($ici, 'LINK0') !== false)
					$ifinfo['pptplink'] = "down";
			}
		}
		break;
	default:
		break;
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
		/* lookup the gateway */
		if (interface_has_gateway($if)) 
			$ifinfo['gateway'] = get_interface_gateway($if);
	}

	$bridge = "";
	$bridge = link_int_to_bridge_interface($ifdescr);
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

function find_dns_aliases() {
	global $config, $g;
	foreach((array) $config['aliases']['alias'] as $alias) {
		$alias_value = $alias['address'];
		$alias_name = $alias['name'];
		if(stristr($alias_value, " ")) {
			$alias_split = split(" ", $alias_value);
			foreach($alias_split as $as) {
				if(is_fqdn($as)) 
					add_hostname_to_watch($as);			
			}
		} else {
			if(is_fqdn($alias_value)) 
				add_hostname_to_watch($alias_value);
		}
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

function pfsense_default_state_size() {
  /* get system memory amount */
  $memory = get_memory();
  $avail = $memory[0];
  /* Be cautious and only allocate 10% of system memory to the state table */
  $max_states = (int) ($avail/10)*1000;
  return $max_states;
}

function lookup_gateway_ip_by_name($name) {
	global $config;
	if(is_array($config['gateways'])) {
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($gateway['name'] == $name) {
				$gatewayip = $gateway['gateway'];
				$interfacegw = $gateway['interface'];
				return($gatewayip);
			}
		}
	} else {
		return(false);
	}
}

function lookup_gateway_monitor_ip_by_name($name) {
	global $config;
	if(is_array($config['gateways'])) {
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($gateway['name'] == "$name") {
				$monitorip = $gateway['monitor'];
				if($monitorip == "")
					$monitorip = $gateway['gateway'];

				return($monitorip);
			}
		}
	} else {
		return(false);
	}
}

function lookup_gateway_interface_by_name($name) {
	global $config;
	if(is_array($config['gateways'])) {
		foreach($config['gateways']['gateway_item'] as $gateway) {
			if($gateway['name'] == "$name") {
				$gatewayip = $gateway['gateway'];
				$interfacegw = $gateway['interface'];
				return($interfacegw);
			}
		}
	} else {
		return(false);
	}
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

function rule_popup($src,$srcport,$dst,$dstport){
global $config;
$aliases_array = array();
if($config['aliases']['alias'] <> "" and is_array($config['aliases']['alias']))
{
$span_begin = "";
		$alias_src_span_begin = "";
		$alias_src_span_end = "";
		$alias_src_port_span_begin = "";
		$alias_src_port_span_end = "";
		$alias_dst_span_begin = "";
		$alias_dst_span_end = "";
		$alias_dst_port_span_begin = "";
		$alias_dst_port_span_end = "";
		$alias_content_text = "";
	foreach($config['aliases']['alias'] as $alias_name) 
	{	
	 	$alias_addresses = explode (" ", $alias_name['address']);
	 	$alias_details = explode ("||", $alias_name['detail']);
	 	$alias_objects_with_details = "";
	 	$counter = 0;
	 	foreach($alias_addresses as $alias_ports_address)
	 	{
			$alias_objects_with_details .= $alias_addresses[$counter];
			$alias_detail_default = strpos ($alias_details[$counter],"Entry added");
			if ($alias_details[$counter] != "" && $alias_detail_default === False){
				$alias_objects_with_details .=" - " . $alias_details[$counter];
			}  
			$alias_objects_with_details .= "<br>";
			$counter++;
		}			
		//max character length for caption field
		$maxlength = 60;
		
		$alias_descr_substr = $alias_name['descr'];
		$alias_content_text = htmlspecialchars($alias_objects_with_details);
		$alias_caption = htmlspecialchars($alias_descr_substr . ":");
		$strlength = strlen ($alias_caption);
		if ($strlength >= $maxlength) 
			$alias_caption = substr($alias_caption, 0, $maxlength) . "...";		
						
		$span_begin = "<span style=\"cursor: help;\" onmouseover=\"domTT_activate(this, event, 'content', '<h1>$alias_caption</h1><p>$alias_content_text</p>', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><U>";
		
		
		if ($alias_name['name'] == $src)
	 	{										
			$alias_src_span_begin = $span_begin;
		}
	 	if ($alias_name['name'] == $srcport)
	 	{									
			$alias_src_port_span_begin = $span_begin;					
		}
		if ($alias_name['name'] == $dst)
	 	{										
			$alias_dst_span_begin = $span_begin;									
		}
		if ($alias_name['name'] == $dstport)
	 	{											
			$alias_dst_port_span_begin = $span_begin;											
		}										
		
	}
	$descriptions = array ();
	$descriptions['src'] = $alias_src_span_begin;
	$descriptions['srcport'] = $alias_src_port_span_begin;
	$descriptions['dst'] = $alias_dst_span_begin;
	$descriptions['dstport'] = $alias_dst_port_span_begin;
	return $descriptions; 
  }
}
function download_file_with_progress_bar($url_file, $destination_file, $readbody = 'read_body') {
	global $ch, $fout, $file_size, $downloaded;
	$file_size  = 1;
	$downloaded = 1;
	/* open destination file */
	$fout = fopen($destination_file, "wb");

	/*
	 *	Originally by Author: Keyvan Minoukadeh
	 *	Modified by Scott Ullrich to return Content-Length size
         */

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_file);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, $readbody);
	curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, '5');

	curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if($fout)
		fclose($fout);
	curl_close($ch);
	return ($http_code == 200) ? true : $http_code;
}

function read_header($ch, $string) {
	global $file_size, $fout;
	$length = strlen($string);
	$regs = "";
	ereg("(Content-Length:) (.*)", $string, $regs);
	if($regs[2] <> "") {
		$file_size = intval($regs[2]);
	}
	ob_flush();
	return $length;
}

function read_body($ch, $string) {
	global $fout, $file_size, $downloaded, $sendto, $static_status, $static_output, $lastseen;
	$length = strlen($string);
	$downloaded += intval($length);
	$downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
	$downloadProgress = 100 - $downloadProgress;
	if($lastseen <> $downloadProgress and $downloadProgress < 101) {
		if($sendto == "status") {
			$tostatus = $static_status . $downloadProgress . "%";
			update_status($tostatus);
		} else {
			$tooutput = $static_output . $downloadProgress . "%";
			update_output_window($tooutput);
		}
		update_progress_bar($downloadProgress);
		$lastseen = $downloadProgress;
	}
	if($fout)
		fwrite($fout, $string);
	ob_flush();
	return $length;
}

?>
