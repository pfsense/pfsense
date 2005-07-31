<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004 Scott Ullrich
        Copyright (C) 2005 Bill Marquette
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

function filter_resync() {
	global $config, $g;

	mwexec("/sbin/pfctl -y"); /* XXX */
}

function filter_pflog_start() {
	global $config, $g;

	mute_kernel_msgs();
	
	mwexec("/sbin/ifconfig pflog0 up && pflogd -sD");
	
	mwexec_bg("/usr/sbin/tcpdump -n -e -ttt -i pflog0 | logger -t pf -p local0.info");
	
	unmute_kernel_msgs();

}

/* reload filter async */
function filter_configure() {
	touch("/tmp/filter_dirty");
}

/* reload filter sync */
function filter_configure_sync() {
	
	/* file an alert that the rules are being reloaded */
	if($g['booting'] == false) 
		file_notice("Operation in progress...", "The filter rules are currently reloading.  You will receive a notice if there is an error.", "", "");
	
	if(file_exists("/tmp/filter_dirty"))
		unlink("/tmp/filter_dirty");

	global $config, $g;

	$lan_if = $config['interfaces']['lan']['if'];
	$wan_if = get_real_wan_interface();
	
	/* generate aliases */
	$aliases = filter_generate_aliases();
	/* generate nat rules */
	$natrules = filter_nat_rules_generate();
	/* generate pfctl rules */
	$pfrules = filter_rules_generate();

	if(isset($config['shaper']['enable'])) {
		/* generate altq interface setup parms */
		$altq_ints = filter_setup_altq_interfaces();
		/* generate altq queues */
		$altq_queues = filter_generate_altq_queues($altq_ints);
		/* generate altq rules */
		$pf_altq_rules = filter_generate_pf_altq_rules();
	}
	
	/* enable pf if we need to, otherwise disable */
	if( !isset( $config['system']['disablefilter'] ) ) {
		mwexec("/sbin/pfctl -e");
	} else {
		mwexec("/sbin/pfctl -d");
		return;
	}

	/* get our wan interface? */
	$wanif = get_real_wan_interface();

	/* set up MSS clamping */
       if ($config['interfaces']['wan']['mtu'] <> "" and is_numeric($config['interfaces']['wan']['mtu']))
		$mssclamp = "max-mss " . (intval($config['interfaces']['wan']['mtu'] - 40));
	else if ($config['interfaces']['wan']['ipaddr'] == "pppoe")
		$mssclamp = "max-mss 1452";
	else
		$mssclamp = "";

        $fd = fopen("{$g['tmp_path']}/rules.debug", "w");
	$rules = $aliases . " \n";

	$rules .= setup_logging_interfaces();

	$optimization = "normal";
	if($config['system']['optimization'] <> "") $optimization = $config['system']['optimization'];
        $rules.= "set optimization " . $optimization . "\n";
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* User defined maximum states in Advanced menu. */
		$maximumstates = $config['system']['maximumstates'];
		$rules.= "set limit states " . $maximumstates . "\n";
	}

	$rules.= "\n";
	$rules.= "scrub on {$wanif} all " . $mssclamp ."\n"; // reassemble all directions
	$rules.= $altq_ints . "\n";
	$rules.= $altq_queues . "\n";
	$rules.= $natrules . "\n";
        $rules.= $pf_altq_rules . "\n";
        $rules.= $pfrules . "\n";
	fwrite($fd, $rules);
        fclose($fd);

        $rules_loading = mwexec("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
	if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
		foreach ($config['shaper']['queue'] as $queue) {
        		$rules_loading .= mwexec("/sbin/pfctl -a {$queue['name']} -f {$g['tmp_path']}/{$queue['name']}.rules");
		}
	}

	/* check for a error while loading the rules file.  if an error has occured
	   then output the contents of the error to the caller */
	if($rules_loading <> 0) {
		$rules_error = exec_command("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
		$line_error = split("\:", $rules_error);
		$line_number = $line_error[1];
		$rules_file = `/bin/cat /tmp/rules.debug`;
		$line_split = split("\n", $rules_file);
		if(is_array($line_split))
			$line_error = "The line in question reads [{$line_number}]: " . $line_split[$line_number];
		file_notice("filter_load", "There was error(s) loading the rules: " . $rules_error . " " . $line_error, "Filter Reload", "");
		log_error("There was error(s) loading the rules: " . $rules_error . " - " . $line_error);
		return;
	}

	if(file_exists("/usr/local/pkg/pf/carp_sync_client.php"))
		unlink("/usr/local/pkg/pf/carp_sync_client.php");
	
	/* process packager manager custom rules */
	$files = return_dir_as_array("/usr/local/pkg/pf/");
	if($files <> "") {
		foreach ($files as $file) {
			if($file <> "") {
				$text = return_filename_as_string("/usr/local/pkg/pf/" . $file);
				if($text <> "") {
					if(stristr($file, ".sh") == true) {
						mwexec("/usr/local/pkg/pf/" . $file . " start");
					} else {
						if(stristr($file,"CVS") == false) {
							if($g['booting'] == true)
								echo "\t{$file}... ";
							eval($text);
							if($g['booting'] == true) 
								echo "done.\n";
						}
					}
				}
			}
		}
	}
	
	/* sync carp entries to other firewalls */
	carp_sync_client();
	
	/*
	    we need a way to let a user run a shell cmd after each
	    filter_configure() call.  run this xml command after
            each change.
	*/
	if($config['system']['afterfilterchangeshellcmd'] <> "") {
		mwexec($config['system']['afterfilterchangeshellcmd']);
	}
	
	return 0;
}

function filter_get_altq_queue_scheduler_type($associatedrule) {
	global $config;
	return $config['system']['schedulertype'];
}

function filter_get_rule_interface($associatedrulenumber) {
	global $config;
	foreach ($config['shaper']['rule'] as $rule) {
		if($rule['descr'] == $associatedrule) return $rule['interface'];
	}
	return $config['shaper']['rule'][$associatedrulenumber]['interface'];
}

function find_default_queue($interface) {
	global $config;
	$qconfig = $config;

	/* quick return if we've already seen the default queue for this interface */
	if (isset($queue_cache['defq'][$interface]))
	return $queue_cache['defq'][$interface];

	
	if (is_array($qconfig['shaper']['queue'])) {
		foreach ($qconfig['shaper']['queue'] as $queue) {
			if(isset($queue['defaultqueue']) and ($queue['defaultqueue'] <> "")) {
				/* If this is a child queue */
				if(isset($queue['attachtoqueue'])) {
					if(is_subqueue_used_on_interface($queue['attachtoqueue'], $interface)) {
						$queue_cache['defq'][$interface] = $queue['name'];
						return $queue['name'];
					}
				} else {
					$queue_cache['defq'][$interface] = $queue['name'];
					return $queue['name'];
				}
			}
		}
	}
}

function get_ack_queue($interface) {
	global $config;
	global $queue_cache;
	
	/* quick return if we've already seen the ack queue for this interface */
	if (isset($queue_cache['ackq'][$interface]))
	return $queue_cache['ackq'][$interface];

	$qconfig = $config;
	
	if (is_array($qconfig['shaper']['queue'])) {
		foreach ($qconfig['shaper']['queue'] as $queue) {
			if(isset($queue['ack'])) 
				if(isset($queue['attachtoqueue']))
					if(is_subqueue_used_on_interface($queue['attachtoqueue'], $interface)) {
						/* Add to cache */
						$queue_cache['ackq'][$interface] = $queue['name'];
						return $queue['name'];
					}
		}
	}
}


function filter_generate_altq_queues($altq_ints) {
	global $config;
	$altq_rules = "";
	if (is_array($config['shaper']['queue'])) {
		foreach ($config['shaper']['queue'] as $rule) {
			$options = "";
			// check to make sure we're actually using this queue.
			//if(stristr($altq_ints, $rule['name']) !== FALSE) {
				$altq_rules .= "queue " . $rule['name'] . " ";
				if (isset($rule['bandwidth']) and $rule['bandwidth'] <> "")
					$altq_rules .= "bandwidth " . $rule['bandwidth'] . $rule['bandwidthtype'] . " ";
				if (isset($rule['priority']) and $rule['priority'] <> "")
					$altq_rules .= "priority " .  $rule['priority']  . " ";
				if(isset($rule['red']) and $rule['red'] <> "")
					$options .= " red";
				if(isset($rule['borrow']) and $rule['borrow'] <> "")
					$options .= " borrow";
				if(isset($rule['ecn']) and $rule['ecn'] <> "")
					$options .= " ecn";
				if(isset($rule['rio']) and $rule['rio'] <> "")
					$options .= " rio";
				if(isset($rule['defaultqueue']) and $rule['defaultqueue'] <> "")
					$options .= " default";
				if(isset($rule['upperlimit']) and $rule['upperlimit'] <> "") {
					$options .= " upperlimit(" . $rule['upperlimit1'] . " " . $rule['upperlimit2'] . " " . $rule['upperlimit3'] . ")";
				}
				if(isset($rule['linkshare']) and $rule['linkshare'] <> "") {
					$options .= " linkshare(" . $rule['linkshare1'] . " " . $rule['linkshare2'] . " " . $rule['linkshare3'] . ")";
				}
				if(isset($rule['realtime']) and $rule['realtime'] <> "") {
					$options .= " realtime(" . $rule['realtime1'] . " " . $rule['realtime2'] . " " . $rule['realtime3'] . ")";
				}
				$scheduler_type = $config['system']['schedulertype'];
				$altq_rules .= $scheduler_type . " ";
				if($options)
					$altq_rules .= "(". $options . " )";
				$fsq="";
				foreach($config['shaper']['queue'] as $q) {
					if($q['attachtoqueue'] == $rule['name']) {
						if($fsq == "")
							$altq_rules .= "{ ";
						else if($fsq == "1")
							$altq_rules .= ", ";
						$altq_rules .= $q['name'];
						$fsq = "1";
					}
				}
				if($fsq == "1")
					$altq_rules .= " }";
				$altq_rules .= "\n";
			//}
		}
	}
	return $altq_rules;
}

function filter_get_rule_real_interface($associatedrulenumber) {
        global $config;
        $x=0;
        foreach($config['shaper']['rule'] as $rule) {
                if($x == $associatedrulenumber)
                        return $rule['interface'];
                $x++;
        }
}

/* Find a queue that's attached to this one and see if that queue is used on this interface */
function is_subqueue_used_on_interface($queuename, $interface) {
        global $config;
	$qconfig = $config;
        if (!is_array($qconfig['shaper']['queue'])) return 0;

        foreach ($qconfig['shaper']['queue'] as $queue) {
                if($queue['attachtoqueue'] == $queuename)
			$subqueue_interface = filter_is_queue_being_used_on_interface($queue['name'], $interface);
			/* Useful debugging code for when queues are messed up 
			 * echo "{$subqueue_interface}/{$interface}/{$queue['name']}/{$queuename}\n";
			 */
			if ($subqueue_interface != ""){
				return 1;
			}
        }
        return 0;
}

function filter_is_queue_being_used_on_interface($queuename, $interface) {
	global $config;
	$lconfig = $config;

	if(!is_array($lconfig['shaper']['rule'])) return;
	foreach($lconfig['shaper']['rule'] as $rule) {
		if(($rule['inqueue'] == $queuename && $rule['interface'] == $interface))
			return $interface;
	}
	return;
}
function filter_setup_altq_interfaces() {
	global $config;
	$altq_rules  = "";
	$queue_names = "";
	$is_first = "";

	if(!is_array($config['shaper']['queue'])) return;

	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs[] = "opt" . $j;
	}

	foreach ($ifdescrs as $ifdescr => $ifname) {

		$queue_names = "";
		$is_first = "";

		$workting_with_interface = $ifname;

		foreach ($config['shaper']['queue'] as $queue) {
			$rule_interface = "";
			$q = $queue;
			$rule_interface = filter_is_queue_being_used_on_interface($q['name'], $workting_with_interface);
			if ($rule_interface == $workting_with_interface) {
				if(!isset($q['attachtoqueue'])) {
					if($is_first) $queue_names .= ", ";
					$queue_names .= $q['name'];
					$is_first = "1";
				}
			} else {
                                if(isset($q['parentqueue']) && ($q['parentqueue'] <> "")) {
					if(is_subqueue_used_on_interface($q['name'], $workting_with_interface)) {
                                        	$queue_names .= " ";
                                        	$queue_names .= $q['name'];
                                        	$seen[$q['name']] = 1;
					}
				}
			}

		}

		if($queue_names <> ""){
			$altq_rules .= "altq on " . $config['interfaces'][$ifname]['if'] . " ";
			if($config['interfaces'][$ifname]['bandwidth'] <> "")
				$bandwidth = " bandwidth " . $config['interfaces'][$ifname]['bandwidth'] . $config['interfaces'][$ifname]['bandwidthtype'];
			$altq_rules .=  $config['system']['schedulertype'] . $bandwidth  . " ";
			$altq_rules .= "queue { " . $queue_names . " }";
		}
		$altq_rules .= "\n";

	}
	return $altq_rules;
}

function is_queue_attached_children($name) {
        global $config;
        $status = "";
        if (!is_array($config['shaper']['queue'])) return 0;
	foreach ($config['shaper']['queue'] as $queue) {
		if($queue['attachtoqueue'] == $name) return 1;
	}
	return 0;
}

function queue_interface_recursive($queuename) {
        global $config;
        foreach($config['shaper']['queue'] as $queue) {
                if($queue['attachtoqueue'] == $queuename) {
                        $status = queue_interface_recursive($queue['name']);
                        if($status <> "") return $status;
                }
                foreach($config['shaper']['rule'] as $rule) {
                        if($rule['inqueue'] == $queuename)
                                 return $rule['interface'];
                }
        }
}

function is_subqueue($name) {
        global $config;
	$queues = $config['shaper']['queue']; /* must assign to keep from corrupting in memory $config */
        $status = "";
        if (!is_array($queues)) return 0;
	foreach ($queues as $queue) {
		if($queue['attachtoqueue'] == $name) return 1;
	}
	return 0;
}

function filter_generate_aliases() {
	global $config;

	$aliases = "";

	$i = 0;

	$lanip = find_interface_ip($config['interfaces']['lan']['if']);
	$wanip = find_interface_ip(get_real_wan_interface());

	$lan_aliases = " " . link_ip_to_carp_interface($lanip);
	$wan_aliases = " " . link_ip_to_carp_interface($wanip);

	$aliases .= "# System Aliases \n";
	$aliases .= "lan = \"{ " . $config['interfaces']['lan']['if'] . "{$lan_aliases} }\"\n";
	$aliases .= "wan = \"{ " . get_real_wan_interface() . "{$wan_aliases} }\"\n";
	$aliases .= "pptp = \"{ ng1 ng2 ng3 ng4 ng5 ng6 ng7 ng8 ng9 ng10 ng11 ng12 ng13 ng14 }\"\n";

	$ifdescrs = array();
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	$bridgetracker = 0;
	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* do not process tun interfaces */
		if(stristr(filter_opt_interface_to_real($ifname), "tun") == true) continue;
		$aliases .= filter_get_opt_interface_descr($ifname) . " = \"{ " . filter_opt_interface_to_real($ifname);
		if($config['interfaces'][$ifname]['bridge'] <> "") {
			$aliases .= " bridge{$bridgetracker} ";
			$bridgetracker++;
		}
		$aliases .= " }\"\n";
	}
	$aliases .= "# User Aliases \n";
	/* Setup pf groups */
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			$extraalias = "";
			$ip = find_interface_ip($alias['address']);
			$extraalias = " " . link_ip_to_carp_interface($ip);
			$aliases .= $alias['name'] . " = \"{ " . $alias['address'] . "{$extralias} }\"\n";
		}
	}

	return $aliases;
}

function generate_optcfg_array(& $optcfg) {
	global $config;

        for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
                $oc = $config['interfaces']['opt' . $i];

                if (isset($oc['enable']) && $oc['if']) {
                        $oic = array();
                        $oic['if'] = $oc['if'];

                        if ($oc['bridge']) {
                                if (!strstr($oc['bridge'], "opt") ||
                                        isset($config['interfaces'][$oc['bridge']]['enable'])) {
                                        if (is_ipaddr($config['interfaces'][$oc['bridge']]['ipaddr'])) {
                                                $oic['ip'] = $config['interfaces'][$oc['bridge']]['ipaddr'];
                                                $oic['sn'] = $config['interfaces'][$oc['bridge']]['subnet'];
                                                $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
                                        }
                                }
                                $oic['bridge'] = 1;
                        } else {
                                $oic['ip'] = $oc['ipaddr'];
                                $oic['sn'] = $oc['subnet'];
                                $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
                        }

                        $optcfg['opt' . $i] = $oic;
                }
        }
}

function filter_generate_pf_altq_rules() {
	/* I don't think we're in IPFW anymore Toto */

        global $config, $g, $tcpflags;

        $wancfg = $config['interfaces']['wan'];
        $lancfg = $config['interfaces']['lan'];
        $pptpdcfg = $config['pptpd'];

        $lanif = $lancfg['if'];
        $wanif = get_real_wan_interface();

        $lanip = $lancfg['ipaddr'];
        $lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
        $lansn = $lancfg['subnet'];

        /* optional interfaces */
        $optcfg = array();
        generate_optcfg_array($optcfg);

        if ($pptpdcfg['mode'] == "server") {
                $pptpip = $pptpdcfg['localip'];
                $pptpsa = $pptpdcfg['remoteip'];
                $pptpsn = $g['pptp_subnet'];
		if($config['pptp']['pptp_subnet'] <> "")
			$pptpsn = $config['pptp']['pptp_subnet'];
        }

        /* generate rules */
        if (isset($config['shaper']['rule']))
                foreach ($config['shaper']['rule'] as $rule) {

                /* don't include disabled rules */
                if (isset($rule['disabled'])) {
                        $i++;
                        continue;
                }

                /* does the rule deal with a PPTP interface? */
                if ($rule['interface'] == "pptp") {

                        if ($pptpdcfg['mode'] != "server") {
                                $i++;
                                continue;
                        }

                        $nif = $g['n_pptp_units'];
			if($config['pptp']['n_pptp_units'] <> "")
				$nif = $config['pptp']['n_pptp_units'];

                        $ispptp = true;
                } else {

                        if (strstr($rule['interface'], "opt")) {
                                if (!array_key_exists($rule['interface'], $optcfg)) {
                                        $i++;
                                        continue;
                                }
                        }

                        $nif = 1;
                        $ispptp = false;
                }

                if ($pptpdcfg['mode'] != "server") {
                        if (($rule['source']['network'] == "pptp") ||
                                ($rule['destination']['network'] == "pptp")) {
                                        $i++;
                                        continue;
                                }
                }

                if (strstr($rule['source']['network'], "opt")) {
                        if (!array_key_exists($rule['source']['network'], $optcfg)) {
                                $i++;
                                continue;
                        }
                }
                if (strstr($rule['destination']['network'], "opt")) {
                        if (!array_key_exists($rule['destination']['network'], $optcfg)) {
                                $i++;
                                continue;
                        }
                }

                /* check for unresolvable aliases */
                if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
                        $i++;
                        continue;
                }
                if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
                        $i++;
                        continue;
                }

                for ($iif = 0; $iif < $nif; $iif++) {
		    foreach ( array('in', 'out') as $direction) {

                        $line = "pass {$direction} on ";

                        if ($ispptp) {
                                $line .= " ng" . ($iif+1);
                        } else {
                                $if = $config['interfaces'][$rule['interface']]['if'];

                                if ($rule['interface'] == "wan")
					if($direction=="in")
                                        	$if = $wanif;
					else
                                        	$if = $lanif;
                                else if($rule['interface'] == "lan")
					if($direction=="in")
                                        	$if = $lanif;
					else
                                        	$if = $wanif;

                                $line .= " {$if} ";
                        }

                       if (isset($rule['protocol'])) {
                                $line .= "proto {$rule['protocol']} ";
                        }

                        /* source address */
                        if (isset($rule['source']['any']) || $direction == "out") {
                                $src = "any";
                        } else if ($rule['source']['network']) {
                                if (strstr($rule['source']['network'], "opt")) {
                                        $src = $optcfg[$rule['source']['network']]['sa'] . "/" .
                                                $optcfg[$rule['source']['network']]['sn'];
                                } else {
                                        switch ($rule['source']['network']) {
                                                case 'lan':
                                                        $src = "$lansa/$lansn";
                                                        break;
                                                case 'pptp':
                                                        $src = "$pptpsa/$pptpsn";
                                                        break;
                                        }
                                }
                        } else if ($rule['source']['address']) {
                                $src = $rule['source']['address'];
                        }

                        if (!$src) {
                                printf("No source address found in rule $i\n");
                                break;
                        }

                        if (isset($rule['source']['not'])) {
                                $line .= "from ! $src ";
                        } else {
                                $line .= "from $src ";
                        }

                        if (!isset($rule['protocol']) || in_array($rule['protocol'], array("tcp","udp"))) {
                                if ($rule['source']['port']) {
                                        /*
                                         * Check to see if port is a alias.  If so grab it and
                                         * enclose it in { } to pass to pf.
                                         *
                                         * Otherwise combine the portrange into one if its only
                                         * one item.
                                         */
                                        $src = alias_expand($rule['source']['port']);
                                        if($src <> "") {
                                                $line .= "port {$rule['destination']['port']}";
                                        } else {
                                                $srcport = explode("-", $rule['source']['port']);
                                                if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
                                                        $line .= "port {$srcport[0]} ";
                                                } else {
                                                        $line .= "port {$srcport[0]}:{$srcport[1]} ";
                                                }
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
                                } else {
                                        switch ($rule['destination']['network']) {
                                                case 'lan':
                                                        $dst = "$lansa/$lansn";
                                                        break;
                                                case 'pptp':
                                                        $dst = "$pptpsa/$pptpsn";
                                                        break;
                                        }
                                }
                        } else if ($rule['destination']['address']) {
                                $dst = $rule['destination']['address'];
                        }

                        if (!$dst) {
                                printf("No destination address found in rule $i\n");
                                break;
                        }

                        if (isset($rule['destination']['not'])) {
                                $line .= "to ! $dst ";
                        } else {
                                $line .= "to $dst ";
                        }

                        if (!isset($rule['protocol']) || in_array($rule['protocol'], array("tcp","udp"))) {
                                if ($rule['destination']['port']) {
                                        $dst = alias_expand($rule['destination']['port']);
                                        /*
                                         * Check to see if port is a alias.  If so grab it and
                                         * enclose it in { } to pass to pf.
                                         *
                                         * Otherwise combine the portrange into one if its only
                                         * one item.
                                         */
                                        if($dst <> "") {
                                                $line .= "port {$rule['destination']['port']}";
                                        } else {
                                                $dstport = explode("-", $rule['destination']['port']);
                                                if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
                                                        $line .= "port {$dstport[0]} ";
                                                } else {
                                                        $line .= "port {$dstport[0]}:{$dstport[1]} ";
                                                }
                                        }
                                }
                        }

                        if ($rule['iptos'])
                                $line .= "tos {$rule['iptos']} ";

			$inflags = explode(",", $rule['tcpflags']);
			$flags = " flags ";
			foreach ($tcpflags as $tcpflag) {
				if (array_search($tcpflag, $inflags) !== false) {
					$flags .= strtoupper(substr($tcpflag, 0, 1));
				}
			}
			if($flags <> " flags ")
				$line .= "{$flags}/SAFRPU ";

			$qtag = "{$direction}queue";
			$line .= " keep state tag {$rule[$qtag]} ";

                        $line .= "\n";
                        $shaperrules .= $line;
		    }
                }

                $i++;
        }

	return $shaperrules;
}

function filter_altq_get_queuename($queuenum) {
        global $config;
        $x=0;
        foreach($config['shaper']['queue'] as $rule) {
                if($x == $queuenum)
                        return $rule['name'];
                $x++;
        }
}

function filter_flush_nat_table() {
	global $config, $g;

	return mwexec("/sbin/pfctl -F nat");
}

function filter_flush_state_table() {
	global $config, $g;

	return mwexec("/sbin/pfctl -F state");
}

function filter_nat_rules_generate_if($if, $src, $srcport, $dst, $natport, $target, $nonat) {
	global $config;

	if($src=="/32")
		return;
	
	if($src{0} == "/")
		return;

	$nat = "nat";
	
	if($nonat <> "")
		$nat = "no nat";
	
        if ($target)
                $tgt = $target . "/32";
        else
                $tgt = "($if)";

	if ($dst == "")
		$dst = "any";

        if ($srcport)
		$src .= " port " . $srcport;

	if ($natport)
		$tgt .= " port " . $natport;

        $natrule = <<<EOD
$nat on $if from $src to $dst -> $tgt

EOD;

	return $natrule;
}

function is_one_to_one_or_server_nat_rule($iptocheck) {
	global $config;

	if($config['nat']['onetoone'] <> "")
		foreach($config['nat']['onetoone'] as $onetoone) {
			if(ip_in_subnet($iptocheck,$onetoone['internal']."/".$onetoone['subnet']) == true)
				return true;
			if($onetoone['internal'] == $target)
				return true;
		}

	if($config['nat']['servernat'] <> "")
		foreach($config['nat']['servernat'] as $onetoone) {
			$int = explode("/", $onetoone['ipaddr']);
				if(ip_in_subnet($iptocheck,$onetoone['ipaddr']."/".$onetoone['subnet']) == true)
					return true;
			if($onetoone['ipaddr'] == $target)
				return true;
		}

	if($config['nat']['rule'] <> "")
		foreach($config['nat']['rule'] as $onetoone) {
			$int = explode("/", $onetoone['target']);
				if(ip_in_subnet($iptocheck,$onetoone['target']."/".$onetoone['subnet']) == true)
					return true;
			if($onetoone['target'] == $target)
				return true;
		}

	return FALSE;
}

function filter_nat_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];

	$pptpdcfg = $config['pptpd'];
	$wanif = get_real_wan_interface();

	$lanif = $config['interfaces']['lan']['if'];
	$lanip = $config['interfaces']['lan']['ipaddr'];

	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);

	$natrules = "";
	$natrules .= "nat-anchor \"pftpx/*\"\n";

	$natrules .= "nat-anchor \"natearly/*\"\n";
	$natrules .= "nat-anchor \"natrules/*\"\n";

	/* any 1:1 mappings? */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if (!is_numeric($natent['subnet']))
				$sn = 32;
			else
				$sn = $natent['subnet'];

			if (!$natent['interface'] || ($natent['interface'] == "wan"))
				$natif = $wanif;
			else
				$natif = $config['interfaces'][$natent['interface']]['if'];

			$natrules .= "binat on {$natif} from {$natent['internal']}/{$sn} to any -> {$natent['external']}/{$sn}\n";
		}
	}

	/* outbound rules - advanced or standard */
	if (isset($config['nat']['advancedoutbound']['enable'])) {
		/* advanced outbound rules */
		if (is_array($config['nat']['advancedoutbound']['rule'])) {
			foreach ($config['nat']['advancedoutbound']['rule'] as $obent) {

				$src = $obent['source']['network'];
				if (isset($obent['destination']['not']) && !isset($obent['destination']['any']))
					$dst = "!" . $obent['destination']['network'];
				else
					$dst = $obent['destination']['network'];


				if (!$obent['interface'] || ($obent['interface'] == "wan"))
					$natif = $wanif;
				else
					$natif = $config['interfaces'][$obent['interface']]['if'];

				$natrules .= filter_nat_rules_generate_if($natif, $src, $obent['sourceport'], $dst, $obent['natport'],
					$obent['target'], $obent['nonat']);
			}
		}
	} else {
		/* standard outbound rules (one for each interface) */
		/* create ipsec passthru rule if requested */
		if (isset($config['nat']['ipsecpassthru']['enable'])) {
			$natrules .= filter_nat_rules_generate_if($wanif,
				$lansa . "/" . $lancfg['subnet'], 500, "", 500, null, "");
		}
		$natrules .= filter_nat_rules_generate_if($wanif,
			$lansa . "/" . $lancfg['subnet'], null, "", null, null, "");

		/* optional interfaces */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			$optcfg = $config['interfaces']['opt' . $i];

			if (isset($optcfg['enable']) && !$optcfg['bridge']) {
				$optsa = gen_subnet($optcfg['ipaddr'], $optcfg['subnet']);
				$natrules .= filter_nat_rules_generate_if($wanif,
					$optsa . "/" . $optcfg['subnet'], null, "", null, null, "");
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$pptp_subnet = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptp_subnet = $config['pptp']['pptp_subnet'];
			$natrules .= filter_nat_rules_generate_if($wanif,
				$pptpdcfg['remoteip'] . "/" . $pptp_subnet, null, "", null, null, "");
		}

	}

	$natrules .= "#SSH Lockout Table\n";
	$natrules .= "table <sshlockout> persist\n\n";

	$natrules .= "\n# spam table \n";
	$natrules .= "table <spamd> persist\n\n";

	if(!isset($config['system']['disableftpproxy'])) {
		$optcfg = array();
		generate_optcfg_array($optcfg);	
		$natrules .= "# FTP proxy\n";
		$natrules .= "rdr-anchor \"pftpx/*\"\n";
		$natrules .= "rdr on {$lanif} proto tcp from any to any port 21 -> 127.0.0.1 port 8021\n";
		# go through optional interfaces, setting up pftpx for them as well.		
		foreach($optcfg as $oc) {
			$natrules .= "rdr on {$oc['if']} proto tcp from any to any port 21 -> 127.0.0.1 port 8021\n";
		}
		$natrules .= "\n";
	}


	
	/* DIAG: add ipv6 NAT, if requested */
	if (isset($config['diag']['ipv6nat']['enable']) and $config['diag']['ipv6nat']['ipaddr'] <> "") {
		$natrules .= "rdr on $wanif proto ipv6 from any to any port 0 -> " . "{$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	if (isset($config['nat']['rule'])) {
		$natrules .= "# NAT Inbound Redircts\n";
		foreach ($config['nat']['rule'] as $rule) {

			$extport = explode("-", $rule['external-port']);
			$target = alias_expand_host($rule['target']);
			$localport = $rule['local-port'];

			if (!$target)
				continue;	/* unresolvable alias */

			if ($rule['external-address'])
				if($rule['external-address'] <> "any")
					$extaddr = $rule['external-address'] . "/32";
				else
					$extaddr = $rule['external-address'];
			else
				$extaddr = get_current_wan_address();

			if (!$rule['interface'] || ($rule['interface'] == "wan"))
				$natif = $wanif;
			else if($rule['interface'] == "pptp")
				$natif = "pptp";
			else
				$natif = $config['interfaces'][$rule['interface']]['if'];

			$lanif = $lancfg['if'];

			/*
			 *   Expand aliases
			 *   XXX: may want to integrate this into pf macros
			 */
			if(alias_expand($extport[0]))
				$extport[0] = alias_expand($extport[0]);
			if(alias_expand($extport[1]))
				$extport[1] = alias_expand($extport[1]);			
			if(alias_expand($target))
				$target = alias_expand($target);
			if(alias_expand($localport))
				$localport = alias_expand($localport);
			if(alias_expand($extaddr))
				$extaddr = alias_expand($extaddr);

			if ((!$extport[1]) || ($extport[0] == $extport[1])) {
				if($rule['protocol'] == "tcp/udp")
					$natrules .=
					"rdr on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]} -> {$target} port {$localport}";
				else
					$natrules .=
					"rdr on $natif proto " . $rule['protocol'] . " from any to {$extaddr} port {$extport[0]} -> {$target} port {$localport}";

			} else {
				if($rule['protocol'] == "tcp/udp")
					$natrules .=
					 "rdr on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target} port {$extport[0]}:*";
				else
					$natrules .=
					 "rdr on $natif proto " . $rule['protocol']. " from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target} port {$extport[0]}:*";
			}

			$natrules .= "\n";
		}
	}

	if ($pptpdcfg['mode'] && $pptpdcfg['mode'] != "off") {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else if ($pptpdcfg['mode'] == "redir")
			$pptpdtarget = $pptpdcfg['redir'];

		if ($pptpdcfg['mode'] == "redir") {

		$natrules .= <<<EOD

# PPTP
rdr on $wanif proto gre from any to any -> $pptpdtarget
rdr on $wanif proto tcp from any to any port 1723 -> $pptpdtarget

EOD;
		}
	}

	if (is_package_installed("squid") == 1)
		$natrules .= "rdr on " . $lanif . " inet proto tcp from any to !{$lanip} port www -> 127.0.0.1 port 3128\n";

	$natrules .= process_carp_nat_rules();

	/* captive portal redirects */
	if(isset($config['captiveportal']['enable'])) {
		$natrules .= "# Captive Portal\n";
		$cp_interface = filter_translate_type_to_real_interface($config['captiveportal']['interface']);
		$cp_ip = $config['interfaces'][$config['captiveportal']['interface']]['ipaddr'];
		if($cp_ip == "dhcp")
			$cp_ip = find_interface_ip($cp_interface);
		$natrules .= "no rdr on {$cp_interface} proto tcp from <captiveportal> to any \n";
		$natrules .= "rdr on {$cp_interface} proto tcp from any to any port { 80 443 } -> {$cp_ip} port 8000\n\n";
	}

	return $natrules;
}

function run_command_return_string($cmd) {
	$fd = popen($cmd, "r");
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);
	return $tmp;
}

function generate_user_filter_rule_arr($rule, $ngcounter) {
	$ret = array();
	$line = generate_user_filter_rule($rule, $ngcounter);
	$ret['rule'] = $line;
	if($rule['descr'] != "" and $line != "")
	$ret['descr'] = "label \"USER_RULE: {$rule['descr']}\"";
	else
	$ret['descr'] = "label \"USER_RULE\"";
	$ret['ackq'] = get_ack_queue($rule['interface']);
		
	return $ret;	
}	

function generate_user_filter_rule($rule, $ngcounter) {
		global $config, $g;

		$wancfg = $config['interfaces']['wan'];
		$lancfg = $config['interfaces']['lan'];
		$pptpdcfg = $config['pptpd'];
		
		$lanif = $lancfg['if'];
		$wanif = get_real_wan_interface();
		
		$lanip = $lancfg['ipaddr'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
		$lansn = $lancfg['subnet'];
		
		$optcfg = array();
		generate_optcfg_array($optcfg);
		
		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			return "# rule " . $rule['descr'] . " disabled \n";
			return;
		}
		
		/* do not process this rule if the attached interface is disabled */
//		if(!isset($config['interfaces'][$rule['interface']]['enable']))
//			return;
		
		$pptpdcfg = $config['pptpd'];
		
		if ($pptpdcfg['mode'] == "server") {
			$pptpip = $pptpdcfg['localip'];
			$pptpsa = $pptpdcfg['remoteip'];
			$pptpsn = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptpsn = $config['pptp']['pptp_subnet'];
		}
		
		/* does the rule deal with a PPTP interface? */
		if ($rule['interface'] == "pptp") {
			
			if ($pptpdcfg['mode'] != "server") {
				return "# mode != server " . $rule['descr'] . "\n";
				return;
			}
			
			$nif = $g['n_pptp_units'];
			if($config['pptp']['n_pptp_units'] <> "")
				$nif = $config['pptp']['n_pptp_units'];
			$ispptp = true;
		} else {
			
			/* Check to see if the interface is opt and in our opt list */
			if (strstr($rule['interface'], "opt")) {
 				if (!array_key_exists($rule['interface'], $optcfg)) {
					$item = "";
					foreach($optcfg as $oc) $item .= $oc['if'];					
					return "# {$real_int} {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
					return;
				}
			}
			
			$nif = 1;
			$ispptp = false;
		}
			
		if ($pptpdcfg['mode'] != "server") {
			if (($rule['source']['network'] == "pptp") ||
				($rule['destination']['network'] == "pptp")) {
					return "# source network or destination network == pptp on " . $rule['descr'];
					return;
				}
		}
		
		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				return "# {$rule['source']['network']} !array_key_exists source network " . $rule['descr'];
				return;
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $optcfg)) {
				$item = "";
				foreach($optcfg as $oc) $item .= $oc;
				return "# {$item} {$rule['destination']['network']} !array_key_exists dest network " . $rule['descr'];
				return;
			}
		}
		
		/* check for unresolvable aliases */
		if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
			return "# unresolvable source aliases " . $rule['descr'];
			return;
		}
		if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
			return "# unresolvable dest aliases " . $rule['descr'];
			return;
		}
		
		for ($iif = 0; $iif < $nif; $iif++) {
			$type = $rule['type'];
			if ($type != "pass" && $type != "block" && $type != "reject") {
				/* default (for older rules) is pass */
				$type = "pass";
			}
			
			if ($type == "reject") {
				/* special reject packet */
				if ($rule['protocol'] == "tcp") {
					$line = "block return-rst";
				} else if ($rule['protocol'] == "udp") {
					$line = "block return-icmp";
				} else {
					$line = "block";
				}
			} else {
				$line = $type;
			}
			
			/* ensure the direction is in */
			$line .= " in ";
			
			if (isset($rule['log']))
				$line .= "log ";
			
			$line .= "quick ";
			
			if ($ispptp) {
				$line .= "on ng" . (intval($ngcounter)+1) . " ";
			} else {
				// translate wan, man, lan, opt to real interface.
				$interface = $rule['interface'];
				$temp = filter_get_opt_interface_descr($interface);
				if($temp <> "") $interface = $temp;
				if(isset($rule['destination']['address'])) {
					$canadd = 0;
					/* because pf will not allow a interface for proxyARP
					   type traffic lets check if its in use and if so leave
                                           off the interface */
					if(is_one_to_one_or_server_nat_rule($rule['destination']['address']))
						$canadd = 0;
				}
				if($canadd == 0)
					$line .= "on \$" . $interface . " ";
			}
			
			/* set the gateway for this item if its defined */
			if($config['interfaces'][$rule['interface']]['gateway'] <> "") 
				$rg = $config['interfaces'][$rule['interface']]['gateway'];
			$ri = filter_translate_type_to_real_interface($rule['interface']);
			
			/*
			 *    check to see if /tmp/{$rule['if']}_router exists.  This file
			 *    is created by dhclient for 2nd wan interfaces, etc.
			 */
			if(file_exists("/tmp/{$rule['if']}_router"))
				$rg = return_filename_as_string("/tmp/{$rule['if']}_router");

			if($rg <> "" and $ri <> "" and stristr($rule['interface'],"opt") == true) {
				$line .= "reply-to (" . $ri . " " . $rg . ") ";
			}
			
			/* if user has selected a custom gateway, lets work with it */
			if($rule['gateway'] <> "") {
				$int = guess_interface_from_ip($rule['gateway']);
				$line .= " route-to (" . $int . " " . $rule['gateway'] . ") ";
			}
			
			/* Setup outgoing load balancing */
			if ($config['nat']['outgoingloadbalancing']['rule'] <> "" and
			    isset($config['nat']['outgoingloadbalancing']['enable'])
				and $rule['interface'] == "lan") {
				$tmprules = " route-to { ";
				$isfirst = 0;
				foreach ($config['nat']['outgoingloadbalancing']['rule'] as $rules) {
					if($rules['gatewayip'] <> "") {
						if($isfirst == 1) $tmprules .= ", ";
						$lbinf = guess_interface_from_ip($rules['gatewayip']);
						$tmprules .= " (" . $lbinf . " " . $rules['gatewayip'] . ") ";
						$isfirst = 1;
					}
				}
				$tmprules .= "} round-robin sticky-address ";
				$line .= $tmprules;
			}

			if (isset($rule['protocol'])) {
				if($rule['protocol'] == "tcp/udp")
					$line .= "proto { tcp udp } ";
				elseif($rule['protocol'] == "icmp")
					$line .= "inet proto icmp ";
				else
					$line .= "proto {$rule['protocol']} ";
			} else {
				if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "") {
					$line .= "proto tcp ";
				}
			}

			/* source address */
			if (isset($rule['source']['any'])) {
				$src = "any";
			} else if ($rule['source']['network']) {

				if (strstr($rule['source']['network'], "opt")) {
					$src = $optcfg[$rule['source']['network']]['sa'] . "/" .
						$optcfg[$rule['source']['network']]['sn'];
					if (isset($rule['source']['not'])) $src = "!" . $src;
				} else {
					switch ($rule['source']['network']) {
						case 'lan':
							$src = "$lansa/$lansn";
							break;
						case 'pptp':
							$src = "$pptpsa/$pptpsn";
							break;
					}
					if (isset($rule['source']['not'])) $src = "!" . $src;
				}
			} else if ($rule['source']['address']) {
				$not = "";
				if (isset($rule['source']['not'])) $not = "!";
				$src = "{ " . $not .  alias_expand($rule['source']['address']). " }";
			}
			
			if (!$src || ($src == "/")) {
				return "# at the break!";
				break;
			}
			
			$line .= "from $src ";
			
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
			
				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);
					if(alias_expand($srcport[0]))
						$srcporta = alias_expand($srcport[0]);
					else
						$srcporta = $srcport[0];
					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						if(alias_expand($srcport[0]))
							$line .= "port { {$srcporta} } ";
						else
							$line .= "port = {$srcporta} ";
					} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($srcport[1] == 65535) {
						$line .= "port >= {$srcport[0]} ";
					} else if ($srcport[0] == 1) {
						$line .= "port <= {$srcport[1]} ";
					} else {
						$srcport[0]--;
						$srcport[1]++;
						$line .= "port {$srcport[0]} >< {$srcport[1]} ";
					}
				}
                                /* OS signatures */
                                if (($rule['protocol'] == "tcp") && ($rule['os'] <> ""))
                                        $line .= "os {$rule['os']} ";

			}

			/* destination address */
			if (isset($rule['destination']['any'])) {
				$dst = "any";
			} else if ($rule['destination']['network']) {

				if (strstr($rule['destination']['network'], "opt")) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
					if (isset($rule['destination']['not'])) $dst = " !" . $dst;
				} else {
					switch ($rule['destination']['network']) {
						case 'lan':
							$dst = "$lansa/$lansn";
							break;
						case 'pptp':
							$dst = "$pptpsa/$pptpsn";
							break;
					}
				}
				if (isset($rule['destination']['not'])) $dst = " !" . $dst;
			} else if ($rule['destination']['address']) {
				$not = "";
				if (isset($rule['destination']['not'])) $not = "! ";
				$dst = "{ " . $not . alias_expand($rule['destination']['address']) . " }";
			}
			
			if (!$dst || ($dst == "/")) {
				return "# returning at $dst == \"/\"";
				break;
			}
			
			$line .= "to $dst ";
			
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
			
				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);
					if(alias_expand($dstport[0]))
						$dstporta = alias_expand($dstport[0]);
					else
						$dstporta = $dstport[0];
					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						if(alias_expand($dstport[0]))
							$line .= "port { {$dstporta} }";
						else
							$line .= "port = {$dstporta} ";
					} else if (($dstport[0] == 1) && ($dstport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($dstport[1] == 65535) {
						$line .= "port >= {$dstport[0]} ";
					} else if ($dstport[0] == 1) {
						$line .= "port <= {$dstport[1]} ";
					}  else {
						$dstport[0]--;
						$dstport[1]++;
						$line .= "port {$dstport[0]} >< {$dstport[1]} ";
					}
				}
			}

			if (($rule['protocol'] == "icmp") && $rule['icmptype']) {
				$line .= "icmp-type {$rule['icmptype']} ";
			}

			if ($type == "pass") {

				if( isset($rule['source-track']) or isset($rule['max-src-nodes']) or isset($rule['max-src-states']) )
					if($rule['protocol'] == "tcp")
						$line .= "flags S/SA ";
				/*
					# keep state
						works with TCP, UDP, and ICMP.
					# modulate state
						works only with TCP. pfSense will generate strong Initial Sequence Numbers (ISNs)
						for packets matching this rule.
					# synproxy state
						proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods.
						This option includes the functionality of keep state and modulate state combined.
					# none
						do not use state mechanisms to keep track. this is only useful if your doing advanced
						queueing in certain situations. please check the faq.
				*/
				if(isset($rule['statetype']) && $rule['statetype'] <> "") {
					if($rule['statetype'] == "modulate state" or $rule['statetype'] == "synproxy state") {
						if($rule['protocol'] == "tcp")
							$line .= $rule['statetype'] . " ";
					} elseif ($rule['statetype'] == "none") {
					} else {
						if($rule['statetype'] <> "")
							$line .= $rule['statetype'] . " ";
					}
				} else {
					$line .= "keep state ";
				}
				if( isset($rule['source-track']) and $rule['source-track'] <> "" or 
				  isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "" or 
				   isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "" or 
				    isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "" or 
				     isset($rule['max-src-states']) and $rule['max-src-states'] <> "" ) {
					$line .= "( ";
					if(isset($rule['source-track']) and $rule['source-track'] <> "")
						$line .= "source-track rule ";
					if(isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "")
						$line .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
					if(isset($rule['max-src-states']) and $rule['max-src-states'] <> "")
						$line .= "max-src-states " . $rule['max-src-states'] . " ";
					if(isset($rule['statetimeout']) and $rule['statetimeout'] <> "")
						$line .= "tcp.established " . $rule['statetimeout'] . " ";
					if(isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> ""
					   and isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "") {
						$line .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";						
						$line .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
					   }
					$line .= " ) ";
				}
			}
			if ($type == "reject" && $rule['protocol'] == "tcp") {
				/* special reject packet */
				$line .= "flags S/SA ";
			}
	}
	return $line;
}

function filter_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	/* optional interfaces */
	$optcfg = array();
	generate_optcfg_array($optcfg);

	# BEGIN OF firewall rules
	$ipfrules="anchor \"firewallrules\"\n";

	if ($pptpdcfg['mode'] == "server") {
		$pptpip = $pptpdcfg['localip'];
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
		if($config['pptp']['pptp_subnet'] <> "")
			$pptpsn = $config['pptp']['pptp_subnet'];
	}

	/* default block logging? */
	if (!isset($config['syslog']['nologdefaultblock']))
		$log = "log";
	else
		$log = "";

	/* if squid is installed, lets install its rule */
	if (is_package_installed("squid") == 1) {
		$ipfrules .= "pass in quick on " . $lanif . " inet proto tcp from any to 127.0.0.1 port 3128 flags S/SA keep state label \"transparent proxy\"\n";
		$ipfrules .= "pass out quick on " . $wanif . "  inet proto tcp from ($wanif) to any port www flags S/SA keep state label \"transparent proxy\"\n";
	}

	$ipfrules .= <<<EOD

# loopback
anchor "loopback"
pass in quick on lo0 all label "pass loopback"
pass out quick on lo0 all label "pass loopback"

# package manager early specific hook
anchor "packageearly"


# carp
anchor "carp"

EOD;

	if(!isset($config['system']['disableftpproxy'])) {
	$ipfrules .= <<<EOD

# enable ftp-proxy
anchor "ftpproxy"
anchor "pftpx/*"
pass in quick on $wanif inet proto tcp from port 20 to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: PASV mode data connection"

EOD;


		if(isset($config['system']['rfc959workaround'])) {
		$ipfrules .= <<<EOD

# Fix sites that violate RFC 959 which specifies that the data connection
# be sourced from the command port - 1 (typically port 20)
# This workaround doesn't expose us to any extra risk as we'll still only allow
# connections to the firewall on a port that ftp-proxy is listening on
pass in quick on $wanif inet proto tcp from any to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: RFC959 violation workaround"

EOD;

			$optcfg = array();
			generate_optcfg_array($optcfg);	
			foreach($optcfg as $oc) {
				if($oc['gateway'] <> "")
					$ipfrules .= "pass in quick on {$oc['if']} inet proto tcp from any to ({$oc['if']}) port > 49000 user proxy flags S/SA keep state label \"FTP PROXY: RFC959 violation workaround\" \n";
			}
		}
	}

	$ipfrules .= <<<EOD

# allow access to DHCP server on LAN
anchor "dhcpserverlan"
pass in quick on $lanif proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server on LAN"
pass in quick on $lanif proto udp from any port = 68 to $lanip port = 67 label "allow access to DHCP server on LAN"
pass out quick on $lanif proto udp from $lanip port = 67 to any port = 68 label "allow access to DHCP server on LAN"

EOD;

	/* allow access to DHCP server on optional interfaces */
	foreach ($optcfg as $on => $oc) {
		if (isset($config['dhcpd'][$on]['enable']) && (!$oc['bridge'])) {
			$ipfrules .= <<<EOD

# allow access to DHCP server on {$on}
anchor "dhcpserver"
pass in quick on {$oc['if']} proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server"
pass in quick on {$oc['if']} proto udp from any port = 68 to {$oc['ip']} port = 67 label "allow access to DHCP server"
pass out quick on {$oc['if']} proto udp from {$oc['ip']} port = 67 to any port = 68 label "allow access to DHCP server"
pass out on {$oc['if']} proto icmp from any to any keep state label "allow access to DHCP server"

EOD;
		}
	}

	/* pass traffic between statically routed subnets and the subnet on the
	   interface in question to avoid problems with complicated routing
	   topologies */
	if (is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			unset($sa);

			if ($route['interface'] == "lan") {
				$sa = $lansa;
				$sn = $lansn;
				$if = $lanif;
			} else if (strstr($route['interface'], "opt")) {
				$oc = $optcfg[$route['interface']];
				if ($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
			}

			if ($sa) {
				$ipfrules .= <<<EOD
anchor "staticrouted"
pass in quick on {$if} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass in quick on {$if} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"
pass out quick on {$if} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass out quick on {$if} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"

EOD;
			}
		}
	}

	$ipfrules .= <<<EOD

# WAN spoof check
anchor "wanspoof"
block in $log quick on $wanif from $lansa/$lansn to any label "WAN spoof check"

EOD;

	foreach ($optcfg as $oc) {
		if (!$oc['bridge'])
			if($oc['sa'] <> "") 
				$ipfrules .= "block in $log quick on $wanif from {$oc['sa']}/{$oc['sn']} to any label \"interface spoof check\"\n";
	}

	/* allow PPTP traffic if PPTP client is enabled on WAN */
	if ($wancfg['ipaddr'] == "pptp") {
		$ipfrules .= <<<EOD

# allow PPTP client
anchor "pptpclient"
pass in quick on {$wancfg['if']} proto gre from any to any keep state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto gre from any to any  keep state label "allow PPTP client"
pass in quick on {$wancfg['if']} proto tcp from any port = 1723 to any  flags S/SA keep state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto tcp from any to any port = 1723 flags S/SA keep state label "allow PPTP client"

EOD;
	}

	$ipfrules .= <<<EOD

# allow our DHCP client out to the WAN
# XXX - should be more restrictive
# (not possible at the moment - need 'me' like in ipfw)
anchor "wandhcp"
pass out quick on $wanif proto udp from any port = 68 to any port = 67 label "allow dhcp client out wan"
block in $log quick on $wanif proto udp from any port = 67 to $lansa/$lansn port = 68 label "allow dhcp client out wan"
pass in quick on $wanif proto udp from any port = 67 to any port = 68 label "allow dhcp client out wan"

# LAN/OPT spoof check (needs to be after DHCP because of broadcast addresses)

EOD;

	/* LAN spoof check */
	$ipfrules .= filter_rules_spoofcheck_generate('lan', $lanif, $lansa, $lansn, $log);

	/* OPT spoof check */
	foreach ($optcfg as $on => $oc) {
		if ($oc['ip'])
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);
	}

	/* block private networks on WAN? */
	if (isset($config['interfaces']['wan']['blockpriv'])) {
		$ipfrules .= <<<EOD


# block anything from private networks on WAN interface
anchor "spoofing"
block in $log quick on $wanif from 10.0.0.0/8 to any label "block private networks from wan block 10/8"
block in $log quick on $wanif from 127.0.0.0/8 to any label "block private networks from wan block 127/8"
block in $log quick on $wanif from 172.16.0.0/12 to any label "block private networks from wan block 172.16/12"
block in $log quick on $wanif from 192.168.0.0/16 to any label "block private networks from wan block 192.168/16"

EOD;

	}
	
	/*
	 * Support for allow limiting of TCP connections by establishment rate
         * Useful for protecting against sudden outburts, etc.
	 */
	$ipfrules .= <<<EODF
# Support for allow limiting of TCP connections by establishment rate
anchor "limitingesr"
table <virusprot>
	
EODF;

	/* block bogon networks on WAN */
	/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
	/* file is automatically in cron every 3000 minutes */
	if (isset($config['interfaces']['wan']['blockbogons'])) {
		$ipfrules .= <<<EOD

# block bogon networks
# http://www.cymru.com/Documents/bogon-bn-nonagg.txt
anchor "wanbogons"
table <bogons> persist file "/etc/bogons"
block in $log quick on $wanif from <bogons> to any label "block bogon networks from wan"

EOD;
	}

	$ipfrules .= <<<EOD

# let out anything from the firewall host itself and decrypted IPsec traffic
# pass out quick on $wanif all keep state label "let out anything from firewall host itself"

EOD;

	$ipfrules .= create_firewall_outgoing_rules_to_itself();

	/* group heads for optional interfaces */
	foreach ($optcfg as $on => $oc) {
		$ipfrules .= <<<EOD


# let out anything from the firewall host itself and decrypted IPsec traffic
pass out quick on {$oc['if']} all keep state label "let out anything from firewall host itself"

EOD;

	}

	if (!isset($config['system']['webgui']['noantilockout'])) {

	$ipfrules .= <<<EOD

# make sure the user cannot lock himself out of the webGUI or SSH
anchor "anti-lockout"
pass in quick from $lansa/$lansn to $lanip keep state label "anti-lockout web rule"

EOD;
	}

	/* PPTPd enabled? */
	if ($pptpdcfg['mode'] && ($pptpdcfg['mode'] != "off")) {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else
			$pptpdtarget = $pptpdcfg['redir'];

		$ipfrules .= <<<EOD

# PPTPd rules
anchor "pptp"
#pass quick proto gre from any to $pptpdtarget keep state label "allow gre pptpd"
#pass quick proto tcp from any to $pptpdtarget port = 1723 keep state label "allow pptpd $pptpdtarget"
# XXX: fix me!
pass quick proto gre from any to any keep state label "allow gre pptpd"
pass quick proto tcp from any to any port = 1723 keep state label "allow pptpd $pptpdtarget"



EOD;
	}

	/* BigPond client enabled? */
	if ($wancfg['ipaddr'] == "bigpond") {

		$ipfrules .= <<<EOD

# BigPond heartbeat rules
anchor "bigpond"
pass in quick proto udp from any to any port = 5050 keep state label "BigPond heartbeat"

# package manager late specific hook
anchor "packagelate"



EOD;
	}

	/*
	 *    captive portal, pf version
         *    first pass captive portal interface traffic to port 8000 
         *    then block every incoming packet on the interface (non quick)
         *    then follow up with an allow items in the captiveportal anchor to flow
	 */
	 if(isset($config['captiveportal']['enable'])) {
		$cp_interface = filter_translate_type_to_real_interface($config['captiveportal']['interface']);
		$cp_ip = find_interface_ip($cp_interface);
		$lan_ip = $config['interfaces']['lan']['ipaddr'];
		$ipfrules .= "\n# Setup Captive portal policy rules\n";
		$ipfrules .= "table <captiveportal>\n";
		$ipfrules .= "block in on {$cp_interface} from any to any label \"block items initially for cp\"\n";
		$ipfrules .= "pass in on {$cp_interface} proto { tcp udp } from any to {$cp_ip} port { 8000 53 } keep state label \"pass items to cp web serv\"\n";
		$ipfrules .= "pass in on {$cp_interface} proto { tcp udp } from any to {$lanip} port { 8000 53 } keep state label \"pass items to cp web serv\"\n";
		$ipfrules .= "pass in on {$cp_interface} from <captiveportal> to any keep state label \"allow captive portal authd users\"\n";
	}

	$ipfrules .= "\n# SSH lockout\n";
	$ipfrules .= "block in log proto tcp from <sshlockout> to any port 22 label \"sshlockout\"\n\n";

	$ipfrules .= "\n# User-defined rules follow\n";

	if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue']) && isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		/* XXX: billm - twice really, this needs to be made useable for "normal rules too" */
		$rule_arr = array();
		$ipfrules .= "# Anchors for rules that might be matched by queues\n";
		foreach ($config['filter']['rule'] as $rule) {
			$line = "";
			if (!isset($rule['disabled'])) {
				if ($rule['interface'] == "pptp") {
					$n_pptp_units = $g['n_pptp_units'];
					if($config['pptp']['n_pptp_units'] <> "")
					$nif = $config['pptp']['n_pptp_units'];
					for($xxx=0; $xxx < $n_pptp_units; $xxx++) {
						/*
						*   now that PPTP server are user rules, detect
						*   that user is setting the pptp server rule
						*   and setup for all netgraph interfaces
						*/
						$rule_arr[] = generate_user_filter_rule_arr($rule, $xxx);
					}
				} else {
					$rule_arr[] = generate_user_filter_rule_arr($rule, 0);
				}
			}
		}

		/* This is ugly, but we generate one anchor per queue */
		foreach ($config['shaper']['queue'] as $queue) {
			/* Add anchor to rules */
			$ipfrules .= "anchor {$queue['name']} tagged {$queue['name']}\n";
			/* Create rules for anchors */
			$fd = fopen("{$g['tmp_path']}/{$queue['name']}.rules", "w");
			/* aliases don't recurse to anchors */
			$line = filter_generate_aliases();
			fwrite($fd, $line);
			foreach($rule_arr as $rule) {
				if($rule['ackq'] != "")
				$line = "{$rule['rule'] } queue ({$queue['name']}, {$rule['ackq']}) {$rule['descr']}\n";
				else
				$line = "{$rule['rule'] } queue {$queue['name']} {$rule['descr']}\n";
				fwrite($fd, $line);
			}
			fclose($fd);
		}
	}


	if (isset($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			$line = "";
			if (!isset($rule['disabled'])) {
				if ($rule['interface'] == "pptp") {
					$n_pptp_units = $g['n_pptp_units'];
					if($config['pptp']['n_pptp_units'] <> "")
						$nif = $config['pptp']['n_pptp_units'];					
					for($xxx=0; $xxx < $n_pptp_units; $xxx++) {					
						/*
			 			 *   now that PPTP server are user rules, detect
			 			 *   that user is setting the pptp server rule 
                                      			 *   and setup for all netgraph interfaces
                                     			 */
						$line = generate_user_filter_rule($rule, $xxx);
						if($line <> "") { 
							if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
								$defq = find_default_queue($rule['interface']);
								$ackq = get_ack_queue($rule['interface']);
								if (($defq != "") and ($ackq != ""))
									$line .= " queue ({$defq}, {$ackq}) ";
							}
							$ipfrules .= $line . "\n";
						}
					}
				} else {
					$line = generate_user_filter_rule($rule, 0);
					if($line <> "") 
						if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
							$defq = find_default_queue($rule['interface']);
							$ackq = get_ack_queue($rule['interface']);
							if (($defq != "") and ($ackq != ""))
								$line .= " queue ({$defq}, {$ackq}) ";
						}
					// label
					if($rule['descr'] <> "" and $line <> "")
						$line .= " label \"USER_RULE: " . $rule['descr'] . "\" ";
					else
						$line .= " label \"USER_RULE\" ";
				}
			}
			$line .= "\n";
			$ipfrules .= $line;
		}
	}
	
	$ipfrules .= process_carp_rules();

	$ipfrules .= "\n# VPN Rules\n";
	$lan_ip = $config['interfaces']['lan']['ipaddr'];
	$lan_subnet = $config['interfaces']['lan']['subnet'];
	$wanif = get_real_wan_interface();
	$wan_ip = find_interface_ip($wanif);
	$internal_subnet = gen_subnet($lan_ip, $lan_subnet) . "/" . $config['interfaces']['lan']['subnet'];
	/* Is IP Compression enabled? */
	if(isset($config['ipsec']['ipcomp']))
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=1");
	else
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=0");
	/* deterimine ipsec address */
	$ipsec_ip = $wan_ip;
	/* set failover ip if defined */
	if(isset($config['installedpackages']['sasyncd']['config']))
	foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
		if($sasyncd['ip'] <> "") 
			$ipsec_ip = $sasyncd['ip'];
	}	
	if(is_array($config['ipsec']['tunnel'])) {
		foreach ($config['ipsec']['tunnel'] as $tunnel) {
			$remote_gateway = $tunnel['remote-gateway'];
			$local_subnet = return_vpn_subnet($tunnel['local-subnet']);
			$ipfrules .= "pass quick on " . $wanif . " proto udp from " . $ipsec_ip . " to " . $remote_gateway . " port = 500 keep state label \"IPSEC: ". $tunnel['descr'] ." udp\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto udp from " . $remote_gateway . " to " . $ipsec_ip  . " port = 500 keep state label \"". $tunnel['descr'] ." udp\"\n";

			$ipfrules .= "pass quick on " . $wanif . " proto esp from " . $ipsec_ip . " to " . $remote_gateway . " keep state label \"IPSEC: ". $tunnel['descr'] ." esp proto\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto esp from " . $remote_gateway . " to " . $ipsec_ip  . " keep state label \"IPSEC: ". $tunnel['descr'] ." esp proto\"\n";

			$ipfrules .= "pass quick on " . $wanif . " proto ah from " . $ipsec_ip . " to " . $remote_gateway . " keep state label \"IPSEC: ". $tunnel['descr'] ." ah proto\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto ah from " . $remote_gateway . " to " . $ipsec_ip  . " keep state label \"IPSEC: ". $tunnel['descr'] ." ah proto\"\n";

			$ipfrules .= "pass quick on " . $lanif . " from " . $tunnel['remote-subnet'] . " to " . $local_subnet . " keep state label \"IPSEC:  " . $tunnel['descr'] ."\"\n";
			$ipfrules .= "pass quick on " . $lanif . " from " . $local_subnet . " to " . $tunnel['remote-subnet'] . " keep state label \"IPSEC:  " . $tunnel['descr'] ."\"\n";
		}
	}

	$ipfrules .= <<<EOD

#---------------------------------------------------------------------------
# default rules (just to be sure)
#---------------------------------------------------------------------------
block in $log quick all label "Default block all just to be sure."
block out $log quick all label "Default block all just to be sure."

EOD;

	return $ipfrules;
}

function filter_rules_spoofcheck_generate($ifname, $if, $sa, $sn, $log) {

	global $g, $config;

	$ipfrules = "antispoof for " . $if . "\n";

	return $ipfrules;

}

function setup_logging_interfaces() {
	global $config;
	$rules = "";
	$i = 0;
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* do not work with tun interfaces */
		if(stristr(filter_translate_type_to_real_interface($ifname), "tun") == true) continue;
		$int = filter_translate_type_to_real_interface($ifname);
		$rules .= "set loginterface {$int} \n";
	}
	return $rules;
}

function create_firewall_outgoing_rules_to_itself() {
	global $config, $g;
	$i = 0;
	$rule .= "# pass traffic from firewall -> out\n";
	$rule .= "anchor \"firewallout\"\n";
	$ifdescrs = array('wan', 'lan');
	
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;
	
	/* go through primary and optional interfaces */	
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$return_gateway = $config['interfaces'][$ifname]['gateway'];
		$int = filter_translate_type_to_real_interface($ifname);
                if ($config['interfaces'][$ifname]['ipaddr'] == "pppoe")
                        $int = "ng0";
		$ip = find_interface_ip($int);
		if($ip <> "") {
			if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
       				foreach ($config['shaper']['queue'] as $queue) {
					/* XXX - billm: temp fix for pppoe, needs better fix */
					if($config['interfaces'][$ifname]['ipaddr'] != "")
						$rule .="pass out quick on {$int} all keep state tagged {$queue['name']} queue {$queue['name']} label \"let out anything from firewall host itself\"\n";
					else
						$rule .="pass out quick on {$int} all keep state tagged {$queue['name']} queue {$queue['name']} label \"let out anything from firewall host itself\"\n";
				}
			}
			/* XXX - billm: temp fix for pppoe, needs better fix */
			if($config['interfaces'][$ifname]['ipaddr'] != "")
				$rule .="pass out quick on {$int} all keep state label \"let out anything from firewall host itself\"\n";
			else
				$rule .="pass out quick on {$int} all keep state label \"let out anything from firewall host itself\"\n";
		}

	}
	
	/* is bridging turned on? */
	for($x=0; $x<10; $x++) {
		if(does_interface_exist("bridge{$x}") == true) {
			$rule .="pass out quick on bridge{$x} all keep state label \"let out anything from firewall host itself\"\n";
		}
	}

	/* netgraph outgoing */
	$n_pptp_units = $g['n_pptp_units'];
	if($config['pptp']['n_pptp_units'] <> "")
		$n_pptp_units = $config['pptp']['n_pptp_units'];	
	for($x=0; $x<$n_pptp_units; $x++) {
		if(does_interface_exist("ng{$x}") == true) {
			$rule .="pass out quick on ng{$x} all keep state label \"let out anything from firewall host itself\"\n";
		}
	}

	return $rule;
}

function process_carp_nat_rules() {
	global $g, $config;
	$lines = "";
	if($config['installedpackages']['carp']['config'] != "")
	    foreach($config['installedpackages']['carp']['config'] as $carp) {
		$ip = $carp['ipaddress'];
		if($ip <> "any") {
			$ipnet = "any";
		} else {
			$int = find_ip_interface($ip);
			$carp_int = find_carp_interface($ip);			
		}
		if($int != false and $int != $wan_interface) {
		    $ipnet = convert_ip_to_network_format($ip, $carp['netmask']);
		    $lines .= "nat on {$int} inet from {$ipnet} to any -> ({$carp_int}) \n";
		}
	    }
	return $lines;
}

function process_carp_rules() {
	global $g, $config;
	$lines = "";
	/* return if there are no carp configured items */
	if($config['installedpackages']['carpsettings']['config'] <> "" or
	   $config['virtualip']['vip'] <> "") {
		$lines .= "pass quick proto carp keep state\n";
		$lines .= "pass quick proto pfsync";
	}
	return $lines;
}

function carp_sync_xml($url, $password, $section, $section_xml, $method = 'pfsense.restore_config_section') {
	$params = array(new XML_RPC_Value($password, 'string'),
			new XML_RPC_Value($section, 'array'),
			new XML_RPC_Value($section_xml, 'array'));
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url);
	$cli->setCredentials('admin', $password);
	$resp = $cli->send($msg);
}

function carp_sync_client() {
	global $config;

	if($config['installedpackages']['carpsettings']['config'] != "" and
	  is_array($config['installedpackages']['carpsettings']['config'])) {
	    $already_processed = 1;
	    foreach($config['installedpackages']['carpsettings']['config'] as $carp) {
		if($carp['synchronizetoip'] != "" ) {
		    $synchronizetoip = $carp['synchronizetoip'];
		    $sections = array();
		    $sections_xml = array();
		    if($carp['synchronizerules'] != "" and is_array($config['filter'])) {
			$sections_xml[] = new XML_RPC_Value(backup_config_section("filter"), 'string');
			$sections[] = new XML_RPC_Value('filter', 'string');
		    }
		    if($carp['synchronizenat'] != "" and is_array($config['nat'])) {
			$sections_xml[] = new XML_RPC_Value(backup_config_section("nat"), 'string');
			$sections[] = new XML_RPC_Value('nat', 'string');
		    }
		    if($carp['synchronizealiases'] != "" and is_array($config['aliases'])) {
			$sections_xml[] = new XML_RPC_Value(backup_config_section("aliases"), 'string');
			$sections[] = new XML_RPC_Value('aliases', 'string');
		    }
		    if($carp['synchronizetrafficshaper'] != "" and is_array($config['shaper'])) {
			$sections_xml[] = new XML_RPC_Value(backup_config_section("shaper"), 'string');
			$sections[] = new XML_RPC_Value('shaper', 'string');
		    }
		    if(count($sections) > 0) {
			    carp_sync_xml($synchronizetoip, $carp['password'], $sections, $sections_xml);
			    $cli = new XML_RPC_Client('/xmlrpc.php', $synchronizetoip);
			    $msg = new XML_RPC_Message('pfsense.filter_configure', array(new XML_RPC_Value($carp['password'], 'string')));
			    $cli->setCredentials('admin', $carp['password']);
			    $cli->send($msg);
		    }
		}
	    }
	}

}

function return_vpn_subnet($adr) {
	global $config;
	
        if ($adr['address']) {
                list($padr, $pmask) = explode("/", $adr['address']);
                if (is_null($pmask)) 
			return $padr . "/32";
		return $padr . "/" . $pmask;
        }
	
	/* XXX: do not return wan, lan, etc */
	if(strstr($adr['network'], "wan") or strstr($adr['network'], "lan") or strstr($adr['network'], "opt")) 
		return convert_ip_to_network_format($config['interfaces'][$adr['network']]['ipaddr'],
		       $config['interfaces'][$adr['network']]['subnet']);
	
	/* fallback - error */
	return " # error - {$adr['network']} ";
	
}

?>
