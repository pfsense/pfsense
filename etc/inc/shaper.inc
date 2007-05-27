<?php
/* $Id$ */
/*
	shaper.inc
	Copyright (C) 2004-2006 Scott Ullrich
	Copyright (C) 2005-2006 Bill Marquette
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
			update_filter_reload_status("Generating ALTQ queue {$rule['descr']}...");
			$options = "";
			// check to make sure we're actually using this queue.
			//if(stristr($altq_ints, $rule['name']) !== FALSE) {
			$altq_rules .= "queue {$rule['name']} ";
			if (isset($rule['bandwidth']) and $rule['bandwidth'] <> "")
			$altq_rules .= "bandwidth {$rule['bandwidth']}{$rule['bandwidthtype']} ";
			if (isset($rule['priority']) and $rule['priority'] <> "")
			$altq_rules .= "priority {$rule['priority']} ";
			if (isset($rule['qlimit']) and $rule['qlimit'] <> "")
			$altq_rules .= "qlimit {$rule['qlimit']} ";
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
				if ($rule['upperlimit1'] <> "")
					$options .= " upperlimit({$rule['upperlimit1']} {$rule['upperlimit2']} {$rule['upperlimit3']})";
				else
					$options .= " upperlimit {$rule['upperlimit3']}";
			}
			if(isset($rule['linkshare']) and $rule['linkshare'] <> "") {
				if ($rule['linkshare1'] <> "")
					$options .= " linkshare({$rule['linkshare1']} {$rule['linkshare2']} {$rule['linkshare3']})";
				else
					$options .= " linkshare {$rule['linkshare3']}";
			}
			if(isset($rule['realtime']) and $rule['realtime'] <> "") {
				if ($rule['realtime1'] <> "")
					$options .= " realtime({$rule['realtime1']} {$rule['realtime2']} {$rule['realtime3']})";
				else
					 $options .= " realtime {$rule['realtime3']}";
			}
			$scheduler_type = $config['shaper']['schedulertype'];
			$altq_rules .= "{$scheduler_type} ";
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
		if($queue['attachtoqueue'] == $queuename) {
			/* recurse if we're a parent queue */
			if ($queue['parentqueue'] == "on") {
				return is_subqueue_used_on_interface($queue['name'], $interface);
			}

			/* If we're not a parent check to see if the queue is used on this interface */
			$subqueue_interface = filter_is_queue_being_used_on_interface($queue['name'], $interface);
			if ($subqueue_interface != ""){
				return 1;
			}
		}
	}
	return 0;
}

function filter_is_queue_being_used_on_interface($queuename, $interface, $direction = 'in') {
	global $config;
	$lconfig = $config;

	if(!is_array($lconfig['shaper']['rule'])) return null;
	foreach($lconfig['shaper']['rule'] as $rule) {
		$q  = $direction . 'queue';
		$if = $direction . '-interface';
		if(($rule[$q] == $queuename && $rule[$if] == $interface))
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

		$queue_names = find_root_queue($ifname);

		if($queue_names <> ""){
			$altq_rules .= "altq on {$config['interfaces'][$ifname]['if']} ";
			$bandwidth_arr = get_queue_bandwidth($queue_names);
			$bandwidth = "bandwidth {$bandwidth_arr['bandwidth']}{$bandwidth_arr['bandwidthtype']}";
			$altq_rules .= "{$config['shaper']['schedulertype']} {$bandwidth} ";
			$altq_rules .= "queue { {$queue_names} }";
		}
		$altq_rules .= "\n";

	}
	return $altq_rules;
}



/* Find the root queue for an interface */
function find_root_queue($ifname) {
	global $config;
	$queue_names = "";
	foreach ($config['shaper']['queue'] as $queue) {
		$rule_interface = "";
		$q = $queue;
		/* if we're a parentqueue and aren't attached to another queue we're probably a root */
		if ((isset($q['parentqueue']) && $q['parentqueue'] <> "") && (!isset($q['attachtoqueue']) || $q['attachtoqueue'] == "")) {
			/* Confirm that this is a valid queue for this interface */
			$rule_interface = is_subqueue_used_on_interface($q['name'], $ifname);
			if ($rule_interface == 1) {
				if (strlen($queue_names) > 0)
					$queue_names .= " ";
				$queue_names .= $q['name'];
			}
		}
	}
	return $queue_names;
}

function get_queue_bandwidth($name) {
	global $config;
	foreach ($config['shaper']['queue'] as $queue) {
		if ($queue['name'] == $name) {
			return array(
				'bandwidth' => $queue['bandwidth'],
				'bandwidthtype' => $queue['bandwidthtype']
			);
		}
	}
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
			return $rule['in-interface'];
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
	$i = 0;

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

		update_filter_reload_status("Generating ALTQ rule {$rule['descr']}...");

		switch($rule['in-interface']) {
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
				if (strstr($rule['in-interface'], "opt")) {
					if (!array_key_exists($rule['in-interface'], $optcfg)) {
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

		$lanip = find_interface_ip($config['interfaces']['lan']['if']);
		$wanip = find_interface_ip(get_real_wan_interface());

		for ($iif = 0; $iif < $nif; $iif++) {
			$direction = 'in';
			$line = "pass {$direction} on ";

			if ($ispptp) {
				$line .= " ng" . ($iif+1);
			}
			else if($ispppoe) {
				$line .= " ng" . ($iif+1);
			}
			else {
				$friendly_desc = convert_friendly_interface_to_friendly_descr($rule['in-interface']);
				$line .= " \${$friendly_desc} ";
			}

			/* get protocol */
			$proto = $rule['protocol'];
			if (isset($proto)) {
				$line .= "proto {$proto} ";
			}

			/* get source address */
			if (isset($rule['source']['any'])) {
				$src = "any";
			} else if ($rule['source']['network']) {
				if (stristr($rule['source']['network'], "opt") == true) {
					$src = $optcfg[$rule['source']['network']]['sa'] . "/" .
						$optcfg[$rule['source']['network']]['sn'];
				} else {
					switch ($rule['source']['network']) {
						case 'wanip':
							$src = $wanip;
							break;
						case 'lanip':
							$src = $lanip;
							break;
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
				$src = alias_expand($rule['source']['address']);
				if(!$src)
					$src = $rule['source']['address'];
			}

			if (!$src) {
				printf("No source address found in rule $i\n");
				break;
			}

			if (isset($rule['source']['not'])) {

				/*pf is not really happy with this sexy ($src = "!{$src}";) approach of
				 * negating a list or macro. So we have to write out a ! on each entry.
				 */

				/* not very happy with this! but it beats copying this section to
				 * several places.
				 */
				$alias = alias_expand(substr($src, 1));

				if(isset($alias) && stristr($alias, "$")) {
					$alias = alias_expand_value(substr($src, 1));
					$src = "{";
					foreach(preg_split("/[\s]+/", $alias) as $item) {
						if($item != "") {
							$src .= " !{$item}";
						}
					}
					$src .= " }";
				}
				else {
					$src = "!{$src}";
				}
			}
			$line .= "from {$src} ";

			/* get source port */
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
						$line .= "port {$src}";
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
				if (stristr($rule['destination']['network'], "opt") == true) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
				} else {
					switch ($rule['destination']['network']) {
						case 'wanip':
							$dst = $wanip;
							break;
						case 'lanip':
							$dst = $lanip;
							break;
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
				$dst = alias_expand($rule['destination']['address']);
				if(!$dst)
					$dst = $rule['destination']['address'];
			}

			if (!$dst) {
				printf("No destination address found in rule {$i}. \n");
				print_r($rule['destination']['network']);
				break;
			}

			if (isset($rule['destination']['not'])) {

				/*pf is not really happy with this sexy ($dst = "!{$dst}";) approach of
				 * negating a list or macro. So we have to write out a ! on each entry.
				 */

				/* not very happy with this! but it beats copying this section to
				 * several places.
				 */
				$alias = alias_expand(substr($dst, 1));

				if(isset($alias) && stristr($alias, "$")) {
					$alias = alias_expand_value(substr($dst, 1));
					$dst = "{";
					foreach(preg_split("/[\s]+/", $alias) as $item) {
						if($item != "") {
							$dst .= " !{$item}";
						}
					}
					$dst .= " }";
				}
				else {
					$dst = "!{$dst}";
				}
			}
			$line .= " to {$dst} ";

			if (!isset($rule['protocol']) || in_array($rule['protocol'], array("tcp","udp"))) {
				if ($rule['destination']['port']) {
					$dstport = alias_expand($rule['destination']['port']);
					/*
					* Check to see if port is a alias.  If so grab it and
					* enclose it in { } to pass to pf.
					*
					* Otherwise combine the portrange into one if its only
					* one item.
					*/
					if($dstport <> "") {
						$line .= "port {$dstport}";
					} else {
						$dstport = explode("-", $rule['destination']['port']);
						if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
							$dstport = $dstport[0];
							$line .= "port {$dstport} ";
						} else {
							$dstport = "{$dstport[0]}:{$dstport[1]}";
							$line .= "port {$dstport} ";
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
			$line .= " keep state tagged unshaped tag {$rule[$qtag]} ";

			$line .= "\n";
			$shaperrules .= $line;

			/* setup the outbound queue on the other interface */
			$direction = 'out';
			$qouttag = "{$direction}queue";

			$friendly_desc = convert_friendly_interface_to_friendly_descr($rule['out-interface']);
			$shaperrules .= "pass out on \${$friendly_desc}";

			if(isset($proto) && $proto != "") {
				$shaperrules .= " proto {$proto}";
			}
			$shaperrules .= " from any to {$dst}";
			if(isset($dstport) && $dstport != "") {
				$shaperrules .= " port {$dstport}";
			}
			if ($rule['iptos']) {
				$shaperrules .= " tos {$rule['iptos']}";
			}
			if($flags <> " flags ") {
				$shaperrules .= "{$flags}/SAFRPU";
			}

			$shaperrules .= " keep state tagged {$rule[$qtag]} tag {$rule[$qouttag]}\n";

			unset($src);
			unset($dst);
			unset($srcport);
			unset($dstport);
		}

		$i++;
	}
	return $shaperrules;
}

?>