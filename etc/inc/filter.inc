<?php
/*
	filter.inc
	Copyright (C) 2004 Scott Ullrich
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

function filter_resync() {
	global $config, $g;

	mwexec("/sbin/pfctl -y"); /* XXX */
}

function filter_pflog_start() {
	global $config, $g;

	mwexec("ifconfig pflog0 up && pflogd -sD");
}

function filter_configure() {
	global $config, $g;

	$lan_if = $config['interfaces']['lan']['if'];
	$wan_if = get_real_wan_interface();

	if(does_interface_exist($lan_if) == false) return;
	if(does_interface_exist($wan_if) == false) return;

	if ($g['booting'])
		echo "Configuring firewall... ";

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
		$altq_rules = filter_generate_altq_rules();
	}
	if( !isset( $config['system']['disablefilter'] ) ) {
		mwexec("/sbin/pfctl -e");
		mwexec("/sbin/pfctl -F nat");
		mwexec("/sbin/pfctl -F rules");
	} else {
		mwexec("/sbin/pfctl -d");
		return;
	}

	/* get our wan interface? */
	$wanif = get_real_wan_interface();

	/* set up MSS clamping */
	if ($config['interfaces']['wan']['mtu'])
		$mssclamp = "max-mss " . $config['interfaces']['wan']['mtu'] - 40;
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
	$rules.= "\nscrub all " . $mssclamp ."\n"; // reassemble all directions
	$rules.= $altq_ints . "\n";
	$rules.= $altq_queues . "\n";
	$rules.= $natrules . "\n";
        $rules.= $pfrules . "\n";
	fwrite($fd, $rules);
        fclose($fd);

        $rules_loading = mwexec("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");

	/* load ipfw+altq module */
	if (isset($config['shaper']['enable'])) {
		mwexec("/sbin/kldload ipfw");
                /* change one_pass to 1 so ipfw stops checking after
                   a rule has matched */
                mwexec("/sbin/sysctl net.inet.ip.fw.one_pass=1");
                /* load shaper rules */
                mwexec("/sbin/ipfw -f delete set 4");
                /* XXX - seems like ipfw cannot accept rules directly on stdin,
                   so we have to write them to a temporary file first */
                $fd = fopen("{$g['tmp_path']}/ipfw.rules", "w");
                if (!$fd) {
                        printf("Cannot open ipfw.rules in filter_configure()\n");
                        return 1;
                }
                fwrite($fd, $altq_rules);
                fclose($fd);
                mwexec("/sbin/ipfw {$g['tmp_path']}/ipfw.rules");
		mwexec("/sbin/ipfw enable altq");
                mwexec("/bin/mv {$g['tmp_path']}/ipfw.rules {$g['tmp_path']}/ipfw.rules_old");
	} else {
		mwexec("/sbin/ipfw -f flush");
		if(!isset($config['captiveportal']['enable']))
			mwexec("/sbin/kldunload ipfw.ko");
	}

	if($rules_loading <> 0) {
		$rules_error = exec_command("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
		return "There was an error loading the rules.<p><pre>" . $rules_error . "</pre>";
	} else {
		/* process packager manager custom rules */
		$files = return_dir_as_array("/usr/local/pkg/pf/");
		if($files <> "") {
			foreach ($files as $file) {
				if($file <> "") {
					$text = return_filename_as_string("/usr/local/pkg/pf/" . $file);
					if($text != "")
						eval($text);
				}
			}
		}

		return 0;
	}
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

function filter_generate_altq_queues($altq_ints) {
	global $config;
	$altq_rules = "";
	if (is_array($config['shaper']['queue'])) {
		foreach ($config['shaper']['queue'] as $rule) {
			$options = "";
			// check to make sure we're actually using this queue.
			if(stristr($altq_ints, $rule['name']) !== FALSE) {
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
				if (isset($rule['subqueue'])) {
					$altq_rules .= "{ ";
					$fsq = "";
					foreach ($rule['subqueue'] as $sq) {
						if($fsq) $altq_rules .= ",";
						$altq_rules .= $sq['name'];
						$fsq = "1";
					}
					$altq_rules .= " }";
				}
				$altq_rules .= "\n";
			}
		}
	}
	return $altq_rules;
}

function filter_translate_type_to_real_interface($interface) {
        global $config;
	return $config['interfaces'][$interface]['if'];
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

function filter_is_queue_being_used_on_interface($queuename, $interface) {
	global $config;
	if(!is_array($config['shaper']['rule'])) return;
	foreach($config['shaper']['rule'] as $rule) {
		if($rule['targetqueue'] == $queuename && $rule['interface'] == $interface)
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

	$i = 0;
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}

	foreach ($ifdescrs as $ifdescr => $ifname) {

		$queue_names = "";
		$is_first = "";

		$workting_with_interface = $ifname;

		foreach ($config['shaper']['queue'] as $queue) {
			$rule_interface = filter_is_queue_being_used_on_interface($queue['name'], $workting_with_interface);
			if ($rule_interface == $workting_with_interface) {
				if(is_subqueue($queue['name']) == 0) {
					if($is_first) $queue_names .= ", ";
					$queue_names .= $queue['name'];
					$is_first = "1";
				}
			}
		}

		$subnet = $ifname['ipaddr'] . "/" . $config['interfaces'][$ifname]['subnet'];
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

function is_subqueue($name) {
        global $config;
        $status = "";
        if (is_array($config['shaper']['queue'])) {
                foreach ($config['shaper']['queue'] as $queue) {
                        if(is_array($queue['subqueue'])) {
                                foreach ($queue['subqueue'] as $sq) {
                                        if($sq['name'] == $name) return 1;
                                }
                        }
                }
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
	$aliases .= "pptp = \"{  }\"\n";

	$ifdescrs = array();
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$aliases .= filter_get_opt_interface_descr($ifname) . " = \"{ " . filter_opt_interface_to_real($ifname) . " }\"\n";
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

function filter_generate_altq_rules() {
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

        for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
                $oc = $config['interfaces']['opt' . $i];

                if (isset($oc['enable']) && $oc['if']) {
                        $oic = array();
                        $oic['ip'] = $oc['ipaddr'];
                        $oic['if'] = $oc['if'];
                        $oic['sa'] = gen_subnet($oc['ipaddr'], $oc['subnet']);
                        $oic['sn'] = $oc['subnet'];

                        $optcfg['opt' . $i] = $oic;
                }
        }

        if ($pptpdcfg['mode'] == "server") {
                $pptpip = $pptpdcfg['localip'];
                $pptpsa = $pptpdcfg['remoteip'];
                $pptpsn = $g['pptp_subnet'];
        }

        $rulei = 50000;

        /* add a rule to pass all traffic from/to the firewall,
           so the user cannot lock himself out of the webGUI */
        $shaperrules = "add $rulei set 4 pass all from $lanip to any\n"; $rulei++;
        $shaperrules .= "add $rulei set 4 pass all from any to $lanip\n"; $rulei++;

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

			$queuename = filter_altq_get_queuename($rule['targetqueue']);
			$line = "add $rulei set 4 pass altq " . $rule['targetqueue'] . " ";
			$rulei++;

                       if (isset($rule['protocol'])) {
                                $line .= "{$rule['protocol']} ";
                        } else {
                                $line .= "all ";
                        }

                        /* source address */
                        if (isset($rule['source']['any'])) {
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
                                $line .= "from not $src ";
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
						$line .= $rule['destination']['port'];
					} else {
						$srcport = explode("-", $rule['source']['port']);
						if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
							$line .= "{$srcport[0]} ";
						} else {
							$line .= "{$srcport[0]}-{$srcport[1]} ";
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
                                $line .= "to not $dst ";
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
						$line .= $rule['destination']['port'];
					} else {
						$dstport = explode("-", $rule['destination']['port']);
						if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
							$line .= "{$dstport[0]} ";
						} else {
							$line .= "{$dstport[0]}-{$dstport[1]} ";
						}
					}
                                }
                        }

                        if ($rule['iplen'])
                                $line .= "iplen {$rule['iplen']} ";

                        if ($rule['iptos'])
                                $line .= "iptos {$rule['iptos']} ";

                        if ($rule['tcpflags'])
                                $line .= "tcpflags {$rule['tcpflags']} ";

                        if ($rule['direction'] == "in")
                                $line .= "in ";
                        else if ($rule['direction'] == "out")
                                $line .= "out ";

                        if ($ispptp) {
                                $line .= "via ng" . ($iif+1);
                        } else {
                                if ($rule['interface'] == "wan")
                                        $if = $wanif;
                                else
                                        $if = $config['interfaces'][$rule['interface']]['if'];

                                $line .= "via {$if}";
                        }

                        $line .= "\n";
                        $shaperrules .= $line;
                }

                $i++;
        }

	$rulei++;
	//$shaperrules .= $line;

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

function filter_nat_rules_generate_if($if, $src, $srcport, $dst, $natport, $target) {
	global $config;

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
nat on $if from $src to $dst -> $tgt

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

	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);

	$natrules = "";

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
					$obent['target']);
			}
		}
	} else {
		/* standard outbound rules (one for each interface) */

		/* create ipsec passthru rule if requested */
		if (isset($config['nat']['ipsecpassthru']['enable'])) {
			$natrules .= filter_nat_rules_generate_if($wanif,
				$lansa . "/" . $lancfg['subnet'], 500, "", 500, null);
		}
		$natrules .= filter_nat_rules_generate_if($wanif,
			$lansa . "/" . $lancfg['subnet'], null, "", null, null);

		/* optional interfaces */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			$optcfg = $config['interfaces']['opt' . $i];

			if (isset($optcfg['enable']) && !$optcfg['bridge']) {
				$optsa = gen_subnet($optcfg['ipaddr'], $optcfg['subnet']);
				$natrules .= filter_nat_rules_generate_if($wanif,
					$optsa . "/" . $optcfg['subnet'], null, "", null, null);
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$natrules .= filter_nat_rules_generate_if($wanif,
				$pptpdcfg['remoteip'] . "/" . $g['pptp_subnet'], null, "", null, null);
		}

		/* static routes */
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['interface'] != "wan")
					$natrules .= filter_nat_rules_generate_if($wanif,
						$route['network'], null, "", null, null);
			}
		}
	}

	if(!isset($config['system']['disableftpproxy']))
		$natrules .= "rdr on " . $lanif . " proto tcp from any to any port 21 -> 127.0.0.1 port 8021\n";


	/* DIAG: add ipv6 NAT, if requested */
	if (isset($config['diag']['ipv6nat']['enable'])) {
		$natrules .= "rdr on $wanif proto ipv6 from any to any port 0 -> " .
		"{$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	if (isset($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $rule) {

			$extport = explode("-", $rule['external-port']);
			$target = alias_expand_host($rule['target']);

			if (!$target)
				continue;	/* unresolvable alias */

			if ($rule['external-address'])
				$extaddr = $rule['external-address'] . "/32";
			else
				$extaddr = get_current_wan_address();

			if (!$rule['interface'] || ($rule['interface'] == "wan"))
				$natif = $wanif;
			else
				$natif = $config['interfaces'][$rule['interface']]['if'];

			$lanif = $lancfg['if'];

			if ((!$extport[1]) || ($extport[0] == $extport[1])) {
				if($rule['protocol'] == "tcp/udp")
					$natrules .=
					"rdr on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]} -> {$target} port {$rule['local-port']}";
				else
					$natrules .=
					"rdr on $natif proto " . $rule['protocol'] . " from any to {$extaddr} port {$extport[0]} -> {$target} port {$rule['local-port']}";

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

		if ($pptpdtarget) {

		$natrules .= <<<EOD

# PPTP
rdr on $wanif proto gre from any to any -> $pptpdtarget
rdr on $wanif proto tcp from any to any port 1723 -> $pptpdtarget

EOD;
		}
	}

	$squid_installed = run_command_return_string("/bin/ls /var/db/pkg/ | grep squid");
	if ($squid_installed <> "") {
		$natrules .= "rdr on " . $lanif . " inet proto tcp from any to any port www -> 127.0.0.1 port 3128\n";
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

function filter_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	/* rule groups (optional interfaces: see below) */
	$ifgroups = array("lan" => 100, "wan" => 200);

	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	/* optional interfaces */
	$optcfg = array();

	# BEGIN OF firewall rules
	$ipfrules="anchor \"firewallrules\"\n";

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
			$ifgroups['opt' . $i] = ($i * 100) + 200;
		}
	}

	if ($pptpdcfg['mode'] == "server") {
		$pptpip = $pptpdcfg['localip'];
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
	}

	/* default block logging? */
	if (!isset($config['syslog']['nologdefaultblock']))
		$log = "log";
	else
		$log = "";

	/* if squid is installed, lets install its rule */
	$squid_installed = run_command_return_string("/bin/ls /var/db/pkg/ | grep squid");
	if ($squid_installed <> "") {
		$ipfrules .= "pass in quick on " . $lanif . " inet proto tcp from any to 127.0.0.1 port 3128 keep state label \"transparent proxy\"\n";
		$ipfrules .= "pass out quick on " . $wanif . "  inet proto tcp from any to any port www keep state label \"transparent proxy\"\n";
	}

	$ipfrules .= create_firewall_outgoing_rules_to_itself();

	$ipfrules .= <<<EOD

# loopback
anchor "loopback"
pass in quick on lo0 all label "pass loopback"
pass out quick on lo0 all label "pass loopback"

# package manager early specific hook
anchor "packageearly"

# carp
anchor "carp"

# enable ftp-proxy
anchor "ftpproxy"
pass in quick on $wanif inet proto tcp from port 20 to ($wanif) user proxy flags S/SA keep state
pass in quick on $wanif inet proto tcp from any to $wanif port > 49000 user proxy keep state

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

	/* Setup outgoing NAT load balancing */
	if (isset($config['nat']['outgoingloadbalancing']['rule'])) {
		$tmprules .= "pass in on " . $lanif . " route-to { ";
		$isfirst = 0;
		foreach ($config['nat']['outgoingloadbalancing']['rule'] as $rule) {
			if($rule['gatewayip'] <> "") {
				if(isfirst == 1) $tmprules .= ", ";
				$tmprules .= " (" . $wanif . " " . $rule['gatewayip'] . ") ";
				$isfirst = 1;
			}
		}
		$tmprules .= "} round-robin proto tcp from " . $lansa ." to any flags S/SA modulate state label \"outgoing nat load balancing\"\n";
		if($isfirst == 1) $ipfrules .= $tmprules;
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
			$ipfrules .= "block in $log quick on $wanif from {$oc['sa']}/{$oc['sn']} to any label \"interface spoof check\"\n";
	}

	/* allow PPTP traffic if PPTP client is enabled on WAN */
	if ($wancfg['ipaddr'] == "pptp") {
		$ipfrules .= <<<EOD

# allow PPTP client
anchor "pptpclient"
pass in quick on {$wancfg['if']} proto gre from any to any keep state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto gre from any to any  keep state label "allow PPTP client"
pass in quick on {$wancfg['if']} proto tcp from any port = 1723 to any  keep state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto tcp from any to any port = 1723 keep state label "allow PPTP client"

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

	$ipfrules .= <<<EOD

# let out anything from the firewall host itself and decrypted IPsec traffic
pass out quick on $wanif all keep state label "let out anything from firewall host itself"

EOD;

	/* group heads for optional interfaces */
	foreach ($optcfg as $on => $oc) {

		$ingroup = $ifgroups[$on];

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

# PPTP rules
anchor "pptp"
pass quick proto gre from any to $pptpdtarget keep state label "allow gre pptp"
pass quick proto tcp from any to $pptpdtarget port = 1723 keep state label "allow pptp $pptpdtarget"

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

	$i = 0;

	$ipfrules .= "\n# User-defined rules follow\n";

	if (isset($config['filter']['rule']))
		foreach ($config['filter']['rule'] as $rule) {

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

		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				$i++;
				continue;
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
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

			if (!$ispptp) {

				$groupnum = $ifgroups[$rule['interface']];

				if (!$groupnum) {
					printf("Invalid interface name in rule $i\n");
					break;
				}
			}

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
				$line .= "on ng" . ($iif+1) . " ";
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
						$canadd = 1;
				}
				if($canadd == 0)
					$line .= "on \$" . $interface . " ";
			}

			if (isset($rule['protocol'])) {
				if($rule['protocol'] == "tcp/udp")
					$line .= "proto { tcp udp } ";
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
				//printf("No source address found in rule $i\n");
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
				//printf("No destination address found in rule $i\n");
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
				   isset($rule['max-src-states']) and $rule['max-src-states'] <> "" ) {
					$line .= "( ";
					if(isset($rule['source-track']) & $rule['source-track'] <> "")
						$line .= "source-track rule ";
					if(isset($rule['max-src-nodes']) & $rule['max-src-nodes'] <> "")
						$line .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
					if(isset($rule['max-src-states']) & $rule['max-src-states'] <> "")
						$line .= "max-src-states " . $rule['max-src-states'] . " ";
					if(isset($rule['statetimeout']) & $rule['statetimeout'] <> "")
						$line .= "tcp.established " . $rule['statetimeout'] . " ";
					$line .= " ) ";
				}

			}

			if ($type == "reject" && $rule['protocol'] == "tcp") {
				/* special reject packet */
				$line .= "flags S/SA ";
			}

			if (!$ispptp) {
				#$line .= "group $groupnum ";
			}

			if(isset($config['shaper']['queue'])) {
				foreach ($config['shaper']['queue'] as $que) {
					if($que['associatedrule'] == $rule['descr']) {
						$line .= "queue " . $que['name'];
						break;
					}
				}
			}

			// tos
                        if($rule['iptos'] <> "")
				$line .= "tos " . $rule['iptos'] . " ";

			// label
                        if($rule['descr'] <> "") $line .= "label \"USER_RULE: " . $rule['descr'] . "\" ";

			$line .= "\n";

			$ipfrules .= $line;
		}

		$i++;
	}

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
	if(is_array($config['ipsec']['tunnel'])) {
		foreach ($config['ipsec']['tunnel'] as $tunnel) {
			$remote_gateway = $tunnel['remote-gateway'];
			$ipfrules .= "pass quick on " . $wanif . " proto udp from " . $wan_ip . " to " . $remote_gateway . " port = 500 keep state label \"IPSEC: ". $tunnel['descr'] ." udp\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto udp from " . $remote_gateway . " to " . $wan_ip  . " port = 500 keep state label \"". $tunnel['descr'] ." udp\"\n";

			$ipfrules .= "pass quick on " . $wanif . " proto esp from " . $wan_ip . " to " . $remote_gateway . " keep state label \"IPSEC: ". $tunnel['descr'] ." esp proto\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto esp from " . $remote_gateway . " to " . $wan_ip  . " keep state label \"IPSEC: ". $tunnel['descr'] ." esp proto\"\n";

			$ipfrules .= "pass quick on " . $wanif . " proto ah from " . $wan_ip . " to " . $remote_gateway . " keep state label \"IPSEC: ". $tunnel['descr'] ." ah proto\"\n";
			$ipfrules .= "pass quick on " . $wanif . " proto ah from " . $remote_gateway . " to " . $wan_ip  . " keep state label \"IPSEC: ". $tunnel['descr'] ." ah proto\"\n";

			$ipfrules .= "pass quick on " . $lanif . " from " . $tunnel['remote-subnet'] . " to " . $internal_subnet  . " keep state label \"IPSEC:  " . $tunnel['descr'] ."\"\n";
			$ipfrules .= "pass quick on " . $lanif . " from " . $internal_subnet . " to " . $tunnel['remote-subnet'] . " keep state label \"IPSEC:  " . $tunnel['descr'] ."\"\n";
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
	    $int = filter_translate_type_to_real_interface($ifname);
	    $rules.= "set loginterface {$int} \n";
	}
	return $rules;
}

function create_firewall_outgoing_rules_to_itself() {
	global $config;
	$i = 0;
	$rule .= "# pass traffic from firewall -> out\n";
	$rule .= "anchor \"firewallout\"\n";
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$int = filter_translate_type_to_real_interface($ifname);
		$rule .="pass out quick on {$int} inet from any to any keep state\n";
                /* XXX - billm: temp fix for pppoe, needs better fix */
                if ($config['interfaces'][$ifname]['ipaddr'] == "pppoe")
                        $int = "ng0";
		if($config['interfaces'][$ifname]['ipaddr'] != "")
			$rule .="pass out quick on {$int} inet from {$int} to any keep state\n";
	}
	return $rule;
}

?>