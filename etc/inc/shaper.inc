<?php
/* $Id$ */
/*
shaper.inc
Copyright (C) 2004 Scott Ullrich
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


function find_default_queue($interface) {
	global $config, $queue_cache;
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

	/* unreachable */
	return null;
}


function get_ack_queue($interface) {
	global $config, $queue_cache;

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
	/* unreachable */
	return null;
}


function filter_generate_altq_queues($altq_ints) {
	global $config;
	$altq_rules = "";
	if (is_array($config['shaper']['queue'])) {
		foreach ($config['shaper']['queue'] as $rule) {
			$options = "";
			// check to make sure we're actually using this queue.
			//if(stristr($altq_ints, $rule['name']) !== FALSE) {
			$altq_rules .= "queue {$rule['name']} ";
			if (isset($rule['bandwidth']) and $rule['bandwidth'] <> "")
			$altq_rules .= "bandwidth {$rule['bandwidth']}{$rule['bandwidthtype']} ";
			if (isset($rule['priority']) and $rule['priority'] <> "")
			$altq_rules .= "priority {$rule['priority']} ";
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
				$options .= " upperlimit({$rule['upperlimit1']} {$rule['upperlimit2']} {$rule['upperlimit3']})";
			}
			if(isset($rule['linkshare']) and $rule['linkshare'] <> "") {
				$options .= " linkshare({$rule['linkshare1']} {$rule['linkshare2']} {$rule['linkshare3']})";
			}
			if(isset($rule['realtime']) and $rule['realtime'] <> "") {
				$options .= " realtime({$rule['realtime1']} {$rule['realtime2']} {$rule['realtime3']})";
			}
			$scheduler_type = $config['shaper']['schedulertype'];
			$altq_rules .= "{$scheduler_type} "
			if($options)
				$altq_rules .= "( {$options} )";
			$fsq="";
			foreach($config['shaper']['queue'] as $q) {
				if($q['attachtoqueue'] == $rule['name']) {
					if($fsq == "") {
						$altq_rules .= "{ ";
					}
					else if($fsq == "1") {
						$altq_rules .= ", ";
					}
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

	if(!is_array($lconfig['shaper']['rule'])) return null;
	foreach($lconfig['shaper']['rule'] as $rule) {
		if(($rule['inqueue'] == $queuename && $rule['interface'] == $interface))
		return $interface;
	}
	return null;
}


function filter_setup_altq_interfaces() {
	global $config;
	$altq_rules  = "";
	$queue_names = "";
	$is_first = "";

	if(!is_array($config['shaper']['queue'])) return null;

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
					}
				}
			}

		}
		if($queue_names <> ""){
			$altq_rules .= "altq on {$config['interfaces'][$ifname]['if']} ";
			if($config['interfaces'][$ifname]['bandwidth'] <> "")
				$bandwidth = "bandwidth {$config['interfaces'][$ifname]['bandwidth']}{$config['interfaces'][$ifname]['bandwidthtype']}";	
			$altq_rules .= "{$config['shaper']['schedulertype']} {$bandwidth} ";
			$altq_rules .= "queue { {$queue_names} }";
		}
		$altq_rules .= "\n";

	}
	return $altq_rules;
}


function is_queue_attached_children($name) {
	global $config;
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

	/* unreachable */
	return null;
}


function is_subqueue($name) {
	global $config;
	$queues = $config['shaper']['queue']; /* must assign to keep from corrupting in memory $config */
	if (!is_array($queues)) return 0;
	foreach ($queues as $queue) {
		if($queue['attachtoqueue'] == $name) return 1;
	}
	return 0;
}


function filter_altq_get_queuename($queuenum) {
	global $config;
	$x=0;
	foreach($config['shaper']['queue'] as $rule) {
		if($x == $queuenum)
		return $rule['name'];
		$x++;
	}
	/* unreachable */
	return null;
}


function filter_generate_pf_altq_rules() {
	/* I don't think we're in IPFW anymore Toto */

	global $config, $g, $tcpflags;

	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	/* optional interfaces */
	$optcfg = array();
	generate_optcfg_array($optcfg);

	if ($pptpdcfg['mode'] == "server") {
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
		if($config['pptp']['pptp_subnet'] <> "")
		$pptpsn = $config['pptp']['pptp_subnet'];
	}

	if ($pppoecfg['mode'] == "server") {
		$pppoesa = $pppoecfg['remoteip'];
		$pppoesn = $g['pppoe_subnet'];
		if($config['pppoe']['pppoe_subnet'] <> "")
		$pppoesn = $config['pppoe']['pppoe_subnet'];
	}

	/* generate rules */
	if (isset($config['shaper']['rule']))
	foreach ($config['shaper']['rule'] as $rule) {

		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			$i++;
			continue;
		}

		switch($rule['interface']) {
			case "pptp": /* does the rule deal with a PPTP interface? */
				if ($pptpdcfg['mode'] != "server") {
					if (($rule['source']['network'] == "pptp") ||
					($rule['destination']['network'] == "pptp")) {
						$i++;
						continue;
					}
				}

				$nif = $g['n_pptp_units'];
				if($config['pptp']['n_pptp_units'] <> "")
					$nif = $config['pptp']['n_pptp_units'];

				$ispptp = true;
				break;

			case "pppoe": /* does the rule deal with a PPPOE interface? */
				if ($pppoecfg['mode'] != "server") {
					if (($rule['source']['network'] == "pppoe") ||
					($rule['destination']['network'] == "pppoe")) {
						$i++;
						continue;
					}
				}

				$nif = $g['n_pppoe_units'];
				if($config['pppoe']['n_pppoe_units'] <> "")
					$nif = $config['pppoe']['n_pppoe_units'];

				$ispppoe = true;
				break;
			default:
				if (strstr($rule['interface'], "opt")) {
					if (!array_key_exists($rule['interface'], $optcfg)) {
						$i++;
						continue;
					}
				}
				$nif = 1;
				$ispptp = false;
				$ispppoe = false;
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
					if($ispppoe) {
						$line .= " ng" . ($iif+1);
					} else {
						$if = $config['interfaces'][$rule['interface']]['if'];
					}

					if ($rule['interface'] == "wan") {
						if($direction=="in") {
							$if = $wanif;
						} else {
							$if = $lanif;
						}
					} else {
						if($rule['interface'] == "lan") {
							if($direction=="in") {
								$if = $lanif;
							} else {
								$if = $wanif;
							}
						}
					}

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
							case 'pppoe':
							$src = "$pppoesa/$pppoesn";
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
							case 'pppoe':
							$dst = "$pppoesa/$pppoesn";
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

?>
