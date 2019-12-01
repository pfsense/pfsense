<?php
/*
 * shaper.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/* XXX: needs some reducing on include. */
/* include all configuration functions. */
require_once("globals.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("notices.inc");
include_once("interfaces.inc");//required for convert_real_interface_to_friendly_interface_name() in get_queue_stats() to get altq interface name.

/* Limiter patch */
// I need this crazy looking model/struct to specify various algos, ecn caps, and params.
// update as needed when dummynet changes

// List of schedulers ('type' command on scheduler/pipe) that dummynet/ipfw is supposed to support
function getSchedulers() {
	return array(
		"wf2q+" => array(
			"name" => "Worst-case Weighted fair Queueing (default)",
			"parameter_format" => "type wf2q+",
			"description" => "Worst-case Weighted fair Queueing (WF2Q+) schedules flows with an associated weight. ".
					 "WF2Q+ is the default algorithm used by previous versions.",
			"parameters" => array(),
			"ecn" => false
		),
		"fifo" => array(
			"name" => "FIFO",
			"parameter_format" => "type fifo",
			"description" => "First-in-First-out is a fundamental queueing discipline which ensures packet sequence. ".
					 "Dynamic queues are not supported with the FIFO scheduler (all packets are stored in a single queue).",
			"parameters" => array(),
			"ecn" => false
		),
		"qfq" => array(
			"name" => "Quick Fair Queueing",
			"parameter_format" => "type qfq",
			"description" => "QFQ is a fast, low-complexity Approximated Fair Queueing scheduler.",
			"parameters" => array(),
			"ecn" => false
		),
		"rr" => array(
			"name" => "Round Robin",
			"parameter_format" => "type rr",
			"description" => "Round Robin (RR) schedules packets in a round-robin fashion.",
			"parameters" => array(),
			"ecn" => false
		),
		"prio" => array(
			"name" => "PRIO",
			"parameter_format" => "type prio",
			"description" => "PRIO schedules packets by their corresponding priority.",
			"parameters" => array(),
			"ecn" => false
		),
		"fq_codel" => array(
			"name" => "FQ_CODEL",
			"parameter_format" => "type fq_codel target %sms interval %sms quantum %s limit %s flows %s",
			"description" => "CoDel is a novel \"no knobs\", \"just works\", \"handles variable bandwidth and RTT\", and simple AQM algorithm."
							. " As a scheduler, FQ_CODEL implements several flows (defined below), each having their own CoDel AQM.",
			"parameters" => array(
				"target" => array("name" => "Target Delay (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqcodel.target")) / 1000),
				"interval" => array("name" => "Interval (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqcodel.interval")) / 1000),
				"quantum" => array("name" => "quantum", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqcodel.quantum")),
				"limit" => array("name" => "limit", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqcodel.limit")),
				"flows" => array("name" => "flows", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqcodel.flows"))
			),
			"ecn" => true
		),
		"fq_pie" => array(
			"name" => "FQ_PIE",
			"parameter_format" => "type fq_pie target %sms tupdate %sms alpha %s beta %s max_burst %s max_ecnth %s",
			"description" => "Fair Queue Proportional Integral controller Enhanced AQM (FQ_PIE) is similar to FQ_CODEL but uses a slightly different algorithm.",
			"parameters" => array(
				"target" => array("name" => "Target Delay (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqpie.target")) / 1000),
				"tupdate" => array("name" => "Interval (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqpie.tupdate")) / 1000),
				"alpha" => array("name" => "alpha", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.alpha")),
				"beta" => array("name" => "beta", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.beta")),
				"max_burst" => array("name" => "max_burst", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.max_burst")),
				"max_ecnth" => array("name" => "max_ecnth", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.max_ecnth"))
			),
			"ecn" => true
		)
	);
}
// list of AQMs that dummynet supports
function getAQMs() {
	return array(
		"droptail" => array(
			"name" => "Tail Drop",
			"description" => "Tail Drop is a fundamental queue management algorithm which drops inbound packets once the queue is full.",
			"parameter_format" => "droptail",
			"parameters" => array(),
			"ecn" => false
		),
		"codel" => array(
			"name" => "CoDel",
			"description" => "CoDel is a novel \"no knobs\", \"just works\", \"handles variable bandwidth and RTT\", and simple AQM algorithm.",
			"parameter_format" => "codel target %sms interval %sms",
			"parameters" => array(
				"target" => array("name" => "Target Delay (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.codel.target")) / 1000),
				"interval" => array("name" => "Interval (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.codel.interval")) / 1000),
			),
			"ecn" => true
		),
		"pie" => array(
			"name" => "PIE",
			"description" => "Proportional Integral controller Enhanced AQM (PIE) is similar to CoDel but uses a slightly different algorithm.",
			"parameter_format" => "pie target %sms tupdate %sms alpha %s beta %s max_burst %s max_ecnth %s",
			"parameters" => array(
				"target" => array("name" => "Target Delay (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqpie.target")) / 1000),
				"tupdate" => array("name" => "Interval (ms)", "type" => "number", "default" => intval(get_single_sysctl("net.inet.ip.dummynet.fqpie.tupdate")) / 1000),
				"alpha" => array("name" => "alpha", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.alpha")),
				"beta" => array("name" => "beta", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.beta")),
				"max_burst" => array("name" => "max_burst", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.max_burst")),
				"max_ecnth" => array("name" => "max_ecnth", "type" => "number", "default" => get_single_sysctl("net.inet.ip.dummynet.fqpie.max_ecnth"))
			),
			"ecn" => true
		),
		"red" => array(
			"name" => "Random Early Detection (RED)",
			"description" => "Random Early Detection (RED) drops packets based on probability, which increases as the queue increases in size.",
			"parameter_format" => "red %s/%s/%s/%s",
			"parameters" => array(
				"w_q" => array("name" => "w_q", "type" => "number", "default" => 1),
				"min_th" => array("name" => "min_th", "type" => "number", "default" => 0),
				"max_th" => array("name" => "max_th", "type" => "number", "default" => 1),
				"max_p" => array("name" => "max_p", "type" => "number", "default" => 1)
			),
			"ecn" => true
		),
		"gred" => array(
			"name" => "Gentle Random Early Detection (GRED)",
			"description" => "Gentle Random Early Detection (GRED) drops packets based on probability, which increases as the queue increases in size.",
			"parameter_format" => "gred %s/%s/%s/%s",
			"parameters" => array(
				"w_q" => array("name" => "w_q", "type" => "number", "default" => 1),
				"min_th" => array("name" => "min_th", "type" => "number", "default" => 0),
				"max_th" => array("name" => "max_th", "type" => "number", "default" => 1),
				"max_p" => array("name" => "max_p", "type" => "number", "default" => 1)
			),
			"ecn" => true
		)
	);
}
// used to map above
function array_map_assoc(callable $f, array $a) {
	return array_column(array_map($f, array_keys($a), $a), 1, 0);
}
function build_queue_params($array, $selected, $params, $id) {
	$form .= '<div id="params_' . $id . '">';

	$selectedAlgorithm = $array[$selected];

	if ($selectedAlgorithm) {
		$form .= '<span class="help-block">' . gettext($selectedAlgorithm['description']) . '</span><br/>';
	}

	$parameters = $selectedAlgorithm["parameters"];

	if (!$parameters || sizeof($parameters) <= 0) {
		if (!$selectedAlgorithm) {
			$form .= 'No parameters for selected algorithm.';
		} else {
			$form .= 'No parameters for ' . $selectedAlgorithm["name"] . '.';
		}
	} else {
		$form .= '<div class="table-responsive">';
		$form .= '<table id="maintable" class="table table-hover table-striped">';
		$form .= "<thead><tr>";
		$form .= "<th>Parameter</th>";
		$form .= "<th>Value</th>";
		$form .= "</tr></thead>";
		$form .= "<tbody>";

		foreach ($parameters as $key => $value) {
			$form .= '<tr>';

			// get current value, or default.
			$currentValue = $params[$key];
			if (!$currentValue || $currentValue == '') {
				$currentValue = $value["default"]; // default to default
			}

			$form .= "<td class=\"col-xs-4\">" . htmlspecialchars($key) . "</td>";
			$form .= "<td class=\"col-xs-8\"><input class=\"form-control\" type=" . gettext($value["type"])
					. " name=\"param_" . $selected . "_" . $key . "\" placeholder=\"" .
					htmlspecialchars($value["default"]) . "\" value=\"" . htmlspecialchars($currentValue) . "\"/></td>";

			$form .= '</tr>';
		}

		$form .= "</tbody></table></div><br />";
	}

	$form .= '</div>';
	$form .= '<script type="text/javascript">';
	$form .= 'events.push(function() {$("#' . $id . '").change(function() {$("#params_' .
		gettext($id) . '").html("Save this limiter to see algorithm parameters.");})});</script>';

	return($form);
}
function FormatParameters($format_string, $params) {
	return vsprintf($format_string, $params);
}
/* End limiter patch */

function get_queue_stats() {
	// due to sysutils\qstats not providing accurate stats with 'currently' valid numbers
	// in its current implementation this php function does the job for now..
	$result = array();
	$result['timestamp'] = gettimeofday(true);
	$r = exec("/sbin/pfctl -s queue -v", $output, $ret);
	$interfacestats = array();
	foreach($output as $line) {
		$partial = explode('{', $line);
		$items = explode(" ", $partial[0]);
		if (isset($partial[1])) {
			$contains = explode(", ", substr($partial[1], 0, strlen($partial[1]) - 1));
		} else {
			unset($contains);
		}
		if ($items[0] == "queue") {
			$level = 1;
			while (empty($items[$level])) {
				$level++;
			}
			unset($newqueue);
			$newqueue = array();
			$currentqueuename = $items[$level];
			$currentqueueif = $items[$level+2];
			$newqueue['name'] = $currentqueuename;
			$newqueue['interface'] = $items[$level+2];

			if ($items[$level+3] == "bandwidth") {
				$level += 2;
			}
			if ($items[$level+3] == "priority") {
				$level += 2;
			}
			if ($items[$level+3] == "qlimit") {
				$level += 2;
			}
			$tmp = $items[$level+3];
			if (substr($tmp,strlen($tmp)-1) == "(") {
				$newqueue['shapertype'] = substr($tmp, 0, strlen($tmp)-1);
			}

			$interfacestats[$currentqueueif][$currentqueuename] = &$newqueue;
			if (is_array($contains)) {
				$newqueue['contains'] = $contains;
			}
		} elseif ($items[0] == "altq") {
			unset($newqueue);
			$newqueue = array();
			$currentqueuename = convert_real_interface_to_friendly_interface_name($items[2]);
			$currentqueueif = $items[2];
			$newqueue['name'] = $currentqueuename;
			$newqueue['interface'] = $items[2];
			$interfacestats[$currentqueueif][$currentqueuename] = &$newqueue;
		} else {
			if ($items[3] == "pkts:") {
				$pktsquery = "pkts:";
				$pktsstart = strpos($line, $pktsquery);
				$pktsend = $pktsstart + strlen($pktsquery);

				$bytesquery = "bytes:";
				$bytesstart = strpos($line, $bytesquery);
				$bytesend = $bytesstart + strlen($bytesquery);

				$droppedpktsquery = "dropped pkts:";
				$droppedpktsstart = strpos($line, $droppedpktsquery);
				$droppedpktsend = $droppedpktsstart + strlen($droppedpktsquery);

				$droppedbytesquery = "bytes:";
				$droppedbytesstart = strpos($line, $droppedbytesquery, $droppedpktsend);
				$droppedbytesend = $droppedbytesstart + strlen($droppedbytesquery);

				$newqueue["pkts"] = trim(substr($line, $pktsend, $bytesstart-$pktsend));
				$newqueue["bytes"] = trim(substr($line, $bytesend, $droppedpktsstart-$bytesend));
				$newqueue["droppedpkts"] = trim(substr($line, $droppedpktsend, $droppedbytesstart-$droppedpktsend));
				$newqueue["droppedbytes"] = trim(substr($line, $droppedbytesend, strlen($line)-1-$droppedbytesend));
			}
			if ($items[3] == "qlength:") {
				$subitems = explode("/", substr($line, 13, 8));
				$newqueue["qlengthitems"] = trim($subitems[0]);
				$newqueue["qlengthsize"] = trim($subitems[1]);
				if (substr($line, 22, 8) == "borrows:") {
					$newqueue["borrows"] = trim(substr($line, 31, 7));
				}
				if (substr($line, 39, 9) == "suspends:") {
					$newqueue["suspends"] = trim(substr($line, 49, 7));
				}
			}
		}
	}
	$result['interfacestats'] = $interfacestats;
	return $result;
}

/*
 * I admit :) this is derived from xmlparse.inc StartElement()
 */
function &get_reference_to_me_in_config(&$mypath) {
	global $config;

	init_config_arr(array('shaper'));
	$ptr = &$config['shaper'];
	foreach ($mypath as $indeks) {
		if (!is_array($ptr)) {
			$ptr = array();
		}
		if (!is_array($ptr['queue'])) {
			$ptr['queue'] = array();
		}
		if (!is_array($ptr['queue'][$indeks])) {
			$ptr['queue'][$indeks] = array();
		}
		$ptr = &$ptr['queue'][$indeks];
	}

	return $ptr;
}

function unset_object_by_reference(&$mypath) {
	global $config;

	init_config_arr(array('shaper'));
	$ptr = &$config['shaper'];
	for ($i = 0; $i < count($mypath) - 1; $i++) {
		$ptr = &$ptr['queue'][$mypath[$i]];
	}
	unset($ptr['queue'][$mypath[$i]]);
}

function &get_dn_reference_to_me_in_config(&$mypath) {
	global $config;

	init_config_arr(array('dnshaper'));
	$ptr = &$config['dnshaper'];
	foreach ($mypath as $indeks) {
		$ptr = &$ptr['queue'][$indeks];
	}

	return $ptr;
}

function unset_dn_object_by_reference(&$mypath) {
	global $config;

	init_config_arr(array('dnshaper'));
	$ptr = &$config['dnshaper'];
	for ($i = 0; $i < count($mypath) - 1; $i++) {
		$ptr = &$ptr['queue'][$mypath[$i]];
	}
	unset($ptr['queue'][$mypath[$i]]);
}

function clean_child_queues($type, $mypath) {
	$ref = &get_reference_to_me_in_config($mypath);

	switch ($type) {
		case 'HFSC':
			if (isset($ref['borrow'])) {
				unset($ref['borrow']);
			}
			if (isset($ref['hogs'])) {
				unset($ref['hogs']);
			}
			if (isset($ref['buckets'])) {
				unset($ref['buckets']);
			}
			break;
		case 'PRIQ':
			if (isset($ref['borrow'])) {
				unset($ref['borrow']);
			}
			if (isset($ref['bandwidth'])) {
				unset($ref['bandwidth']);
			}
			if (isset($ref['bandwidthtype'])) {
				unset($ref['bandwidthtype']);
			}
			/* fall through */
		case 'FAIRQ':
			if (isset($ref['borrow'])) {
				unset($ref['borrow']);
			}
			/* fall through */
		case 'CBQ':
			if (isset($ref['realtime'])) {
				unset($ref['realtime']);
			}
			if (isset($ref['realtime1'])) {
				unset($ref['realtime1']);
			}
			if (isset($ref['realtime2'])) {
				unset($ref['realtime2']);
			}
			if (isset($ref['realtime3'])) {
				unset($ref['realtime3']);
			}
			if (isset($ref['upperlimit'])) {
				unset($ref['upperlimit']);
			}
			if (isset($ref['upperlimit1'])) {
				unset($ref['upperlimit1']);
			}
			if (isset($ref['upperlimit2'])) {
				unset($ref['upperlimit2']);
			}
			if (isset($ref['upperlimit3'])) {
				unset($ref['upperlimit3']);
			}
			if (isset($ref['linkshare'])) {
				unset($ref['linkshare']);
			}
			if (isset($ref['linkshare1'])) {
				unset($ref['linkshare1']);
			}
			if (isset($ref['linkshare2'])) {
				unset($ref['linkshare2']);
			}
			if (isset($ref['linkshare3'])) {
				unset($ref['linkshare3']);
			}
			if (isset($ref['hogs'])) {
				unset($ref['hogs']);
			}
			if (isset($ref['buckets'])) {
				unset($ref['buckets']);
			}
			break;
	}
}

function get_bandwidthtype_scale($type) {
	switch ($type) {
		case "Gb":
			$factor = 1024 * 1024 * 1024;
			break;
		case "Mb":
			$factor = 1024 * 1024;
			break;
		case "Kb":
			$factor = 1024;
			break;
		case "b":
		default:
			$factor = 1;
			break;
	}
	return intval($factor);
}

function get_bandwidth($bw, $scale, $obj) {
	$bw = (int) $bw;
	$pattern= "/(b|Kb|Mb|Gb|%)/";
	if (!preg_match($pattern, $scale, $match))
		return 0;

	switch ($match[1]) {
		case '%':
			$objbw = ($bw / 100) * get_queue_bandwidth($obj);
			break;
		default:
			$objbw = $bw * get_bandwidthtype_scale($scale);
			break;
	}

	return floatval($objbw);
}

/*
 * XXX - unused
 *
function get_hfsc_bandwidth($object, $bw) {
	$pattern= "/[0-9]+/";
	if (preg_match($pattern, $bw, $match)) {
		$bw_1 = $match[1];
	} else {
		return 0;
	}
	$pattern= "/(b|Kb|Mb|Gb|%)/";
	if (preg_match($pattern, $bw, $match)) {
		switch ($match[1]) {
			case '%':
				$bw_1 = ($bw_1 / 100) * get_interface_bandwidth($object);
				break;
			default:
				$bw_1 = $bw_1 * get_bandwidthtype_scale($match[0]);
				break;
		}
		return floatval($bw_1);
	} else {
		return 0;
	}
}
*/

function get_queue_bandwidth($obj) {
	$bw = $obj->GetBandwidth();
	$scale = $obj->GetBwscale();

	$pattern= "/(b|Kb|Mb|Gb|%)/";
	if (!preg_match($pattern, $scale, $match))
		return 0;

	switch ($match[1]) {
		case '%':
			$objbw = ($bw / 100) * get_queue_bandwidth($obj->GetParent());
			break;
		default:
			$objbw = $bw * get_bandwidthtype_scale($scale);
			break;
	}

	return floatval($objbw);
}

function get_interface_bandwidth($object) {
	global $altq_list_queues;

	$int = $object->GetInterface();
	if (isset($altq_list_queues[$int])) {
		$altq = &$altq_list_queues[$int];
		$bw_3 = $altq->GetBandwidth();
		$bw_3 = $bw_3 * get_bandwidthtype_scale($altq->GetBwscale());
		return floatval($bw_3);
	} else {
		return 0;
	}
}

/*
 * This is duplicated here since we cannot include guiconfig.inc.
 * Including it makes all stuff break.
 */
function shaper_do_input_validation($postdata, $reqdfields, $reqdfieldsn, $input_errors) {

	/* check for bad control characters */
	foreach ($postdata as $pn => $pd) {
		if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
			$input_errors[] = sprintf(gettext("The field '%s' contains invalid characters."), $pn);
		}
	}

	for ($i = 0; $i < count($reqdfields); $i++) {
		if ($postdata[$reqdfields[$i]] == "") {
			$input_errors[] = sprintf(gettext("The field '%s' is required."), $reqdfieldsn[$i]);
		}
	}
}

function cleanup_queue_from_rules($queue) {
	global $config;

	foreach ($config['filter']['rule'] as $rule) {
		if ($rule['defaultqueue'] == $queue) {
			unset($rule['defaultqueue']);
		}
		if ($rule['ackqueue'] == $queue) {
			unset($rule['ackqueue']);
		}
	}
}

function cleanup_dnqueue_from_rules($queue) {
	global $config;

	foreach ($config['filter']['rule'] as $rule) {
		if ($rule['dnpipe'] == $queue) {
			unset($rule['dnpipe']);
		}
		if ($rule['pdnpipe'] == $queue) {
			unset($rule['pdnpipe']);
		}
	}
}

class altq_root_queue {
	var $interface;
	var $tbrconfig ;
	var $bandwidth;
	var $bandwidthtype; /* b, Kb, Mb, Gb, % */
	var $scheduler;
	var $qlimit;
	var $queues = array();
	var $qenabled = false;
	var $link;

	/* Accessor functions */
	function GetDefaultQueuePresent() {
		if (!empty($this->queues)) {
			foreach ($this->queues as $q) {
				if ($q->GetDefault()) {
					return true;
				}
			}
		}

		return false;
	}
	function SetLink($link) {
		$this->link = $link;
	}
	function GetLink() {
		return $this->link;
	}
	function GetEnabled() {
		return $this->qenabled;
	}
	function SetEnabled($value) {
		$this->qenabled = $value;
	}
	function CanHaveChildren() {
		if ($this->GetScheduler() == "CODELQ") {
			return false;
		} else {
			return true;
		}
	}
	function CanBeDeleted() {
		return false;
	}
	function GetQname() {
		return $this->interface;
	}
	function SetQname($name) {
		$this->interface = trim($name);
	}
	function GetInterface() {
		return $this->interface;
	}
	function SetInterface($name) {
		$this->interface = trim($name);
	}
	function GetTbrConfig() {
		return $this->tbrconfig;
	}
	function SetTbrConfig($tbrconfig) {
		$this->tbrconfig = $tbrconfig;
	}
	function GetBandwidth() {
		return $this->bandwidth;
	}
	function SetBandwidth($bw) {
		$this->bandwidth = $bw;
	}
	function GetBwscale() {
		return $this->bandwidthtype;
	}
	function SetBwscale($bwscale) {
		$this->bandwidthtype = $bwscale;
	}
	function GetScheduler() {
		return $this->scheduler;
	}
	function SetScheduler($scheduler) {
		$this->scheduler = trim($scheduler);
	}
	function GetQlimit() {
		return $this->qlimit;
	}
	function SetQlimit($limit) {
		$this->qlimit = $limit;
	}

	function GetBwscaleText() {
		switch ($this->bandwidthtype) {
			case "b":
				$bwscaletext = "Bit/s";
				break;
			case "Kb":
				$bwscaletext = "Kbit/s";
				break;
			case "Mb":
				$bwscaletext = "Mbit/s";
				break;
			case "Gb":
				$bwscaletext = "Gbit/s";
				break;
			default:
				/* For others that do not need translating like % */
				$bwscaletext = $this->bandwidthtype;
				break;
		}
		return $bwscaletext;
	}

	function CheckBandwidth($bw, $bwtype) {
		$sum = $this->GetTotalBw();
		if ($sum > $bw * get_bandwidthtype_scale($bwtype))
			return 1;
		foreach ($this->queues as $q) {
			if ($q->CheckBandwidth(0, ''))
				return 1;
		}

		return 0;
	}

	function GetTotalBw($qignore = NULL) {
		$sum = 0;
		foreach ($this->queues as $q) {
			if ($qignore != NULL && $qignore == $q)
				continue;
			$sum += get_bandwidth($q->GetBandwidth(), $q->GetBwscale(), $this);
		}

		return $sum;
	}

	function validate_input($data, &$input_errors) {

		$reqdfields[] = "bandwidth";
		$reqdfieldsn[] = gettext("Bandwidth");
		$reqdfields[] = "bandwidthtype";
		$reqdfieldsn[] = gettext("Bandwidthtype");

		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);

		if (!isset($data['bandwidth']) || strlen($data['bandwidth']) == 0) {
			$input_errors[] = gettext("Bandwidth must be set.  This is usually the interface speed.");
		}
		if ($data['bandwidth'] && (!is_numeric($data['bandwidth']))) {
			$input_errors[] = gettext("Bandwidth must be an integer.");
		}
		if ($data['bandwidth'] < 0) {
			$input_errors[] = gettext("Bandwidth cannot be negative.");
		}
		if ($data['bandwidthtype'] == "%") {
			if ($data['bandwidth'] > 100 || $data['bandwidth'] < 0) {
				$input_errors[] = gettext("Bandwidth in percentage should be between 1 and 100.");
			}
		}
		if ($this->CheckBandwidth($data['bandwidth'], $data['bandwidthtype']))
			$input_errors[] = "The sum of child bandwidth is higher than parent.";

		if ($data['qlimit'] && (!is_numeric($data['qlimit']))) {
			$input_errors[] = gettext("Qlimit must be an integer.");
		}
		if ($data['qlimit'] < 0) {
			$input_errors[] = gettext("Qlimit must be positive.");
		}
		if ($data['tbrconfig'] && (!is_numeric($data['tbrconfig']))) {
			$input_errors[] = gettext("Tbrsize must be an integer.");
		}
		if ($data['tbrconfig'] < 0) {
			$input_errors[] = gettext("Tbrsize must be positive.");
		}
	}

	/* Implement this to shorten some code on the frontend page */
	function ReadConfig(&$conf) {
		if (isset($conf['tbrconfig'])) {
			$this->SetTbrConfig($conf['tbrconfig']);
		} else {
			$this->SetTbrConfig($conf['tbrconfig']);
		}
		$this->SetBandwidth($conf['bandwidth']);
		if ($conf['bandwidthtype'] <> "") {
			$this->SetBwscale($conf['bandwidthtype']);
		}
		if (isset($conf['scheduler'])) {
			if ($this->GetScheduler() != $conf['scheduler']) {
				foreach ($this->queues as $q) {
					clean_child_queues($conf['scheduler'], $this->GetLink());
					$q->clean_queue($conf['scheduler']);
				}
			}
			$this->SetScheduler($conf['scheduler']);
		}
		if (isset($conf['qlimit']) && $conf['qlimit'] <> "") {
			$this->SetQlimit($conf['qlimit']);
		} else {
			$this->SetQlimit("");
		}
		if (isset($conf['name'])) {
			$this->SetQname($conf['name']);
		}
		if (!empty($conf['enabled'])) {
			$this->SetEnabled($conf['enabled']);
		} else {
			$this->SetEnabled("");
		}
	}

	function copy_queue($interface, &$cflink) {
		$cflink['interface'] = $interface;
		$cflink['name'] = $interface;
		$cflink['scheduler'] = $this->GetScheduler();
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['qlimit'] = $this->GetQlimit();
		$cflink['tbrconfig'] = $this->GetTbrConfig();
		$cflink['enabled'] = $this->GetEnabled();
		if (is_array($this->queues)) {
			$cflink['queue'] = array();
			foreach ($this->queues as $q) {
				$cflink['queue'][$q->GetQname()] = array();
				$q->copy_queue($interface, $cflink['queue'][$q->GetQname()]);
			}
		}
	}

	function &get_queue_list(&$q = null) {
		$qlist = array();

		//$qlist[$this->GetQname()] = & $this;
		if (is_array($this->queues)) {
			foreach ($this->queues as $queue) {
				$queue->get_queue_list($qlist);
			}
		}
		return $qlist;
	}

	function &add_queue($interface, &$queue, &$path, &$input_errors) {

		if (!is_array($this->queues)) {
			$this->queues = array();
		}

		switch ($this->GetScheduler()) {
			case "PRIQ":
				$__tmp_q = new priq_queue(); $q =& $__tmp_q;
				break;
			case "HFSC":
				$__tmp_q = new hfsc_queue(); $q =& $__tmp_q;
				break;
			case "CBQ":
				$__tmp_q = new cbq_queue(); $q =& $__tmp_q;
				break;
			case "FAIRQ":
				$__tmp_q = new fairq_queue(); $q =& $__tmp_q;
				break;
			default:
				/* XXX: but should not happen anyway */
				return;
				break;
		}
		$q->SetLink($path);
		$q->SetInterface($this->GetInterface());
		$q->SetEnabled("on");
		$q->SetParent($this);
		$q->ReadConfig($queue);
		$q->validate_input($queue, $input_errors);

		$this->queues[$q->GetQname()] = &$q;
		ref_on_altq_queue_list($this->GetQname(), $q->GetQname());
		if (is_array($queue['queue'])) {
			foreach ($queue['queue'] as $key1 => $que) {
				array_push($path, $key1);
				$q->add_queue($q->GetInterface(), $que, $path, $input_errors);
				array_pop($path);
			}
		}

		return $q;
	}

	/* interface here might be optional */
	function &find_queue($interface, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}
		foreach ($this->queues as $q) {
			$result =& $q->find_queue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function &find_parentqueue($interface, $qname) {
		if ($qname == $interface) {
			$result = NULL;
		} else if ($this->queues[$qname]) {
			$result = $this;
		} else if ($this->GetScheduler() <> "PRIQ") {
			foreach ($this->queues as $q) {
				$result = $q->find_parentqueue("", $qname);
				if ($result) {
					return $result;
				}
			}
		}
	}

	function build_tree() {
		global $shaperIFlist;

		$tree = " <li><a href=\"firewall_shaper.php?interface=".$this->GetInterface()."&amp;queue=". $this->GetInterface()."&amp;action=show";
		$tree .= "\">" . $shaperIFlist[$this->GetInterface()] . "</a>";
		if (is_array($this->queues)) {
			$tree .= "<ul>";
			foreach ($this->queues as $q) {
				$tree .= $q->build_tree();
			}
			$tree .= "</ul>";
		}
		$tree .= "</li>";
		return $tree;
	}

	function delete_queue() {
		foreach ($this->queues as $q)
			$q->delete_queue();
		unset_object_by_reference($this->GetLink());
	}

	function delete_all() {
		if (count($this->queues)) {
			foreach ($this->queues as $q) {
				$q->delete_all();
				unset_object_by_reference($q->GetLink());
				unset($q);
			}
			unset($this->queues);
		}
	}

	/*
	 * First it spits:
	 * altq on $interface ..............
	 *	then it goes like
	 *	foreach ($queues as $qkey => $queue) {
	 *		this->queues[$qkey]->build_rule();
	 *	}
	 */
	function build_rules(&$default = false) {
		if (count($this->queues) > 0 && $this->GetEnabled() == "on") {
			$default = false;
			$rules = " altq on " . get_real_interface($this->GetInterface());
			if ($this->GetScheduler()) {
				$rules .= " ".strtolower($this->GetScheduler());
			}
			if ($this->GetQlimit() > 0) {
				$rules .= " qlimit " . $this->GetQlimit() . " ";
			}
			if ($this->GetBandwidth()) {
				$rules .= " bandwidth ".trim($this->GetBandwidth());
				if ($this->GetBwscale()) {
					$rules .= $this->GetBwscale();
				}
			}
			if ($this->GetTbrConfig()) {
				$rules .= " tbrsize ".$this->GetTbrConfig();
			}
			if (count($this->queues)) {
				$i = count($this->queues);
				$rules .= " queue { ";
				foreach ($this->queues as $qkey => $qnone) {
					if ($i > 1) {
						$i--;
						$rules .= " {$qkey}, ";
					} else {
						$rules .= " {$qkey} ";
					}
				}
				$rules .= " } \n";
				foreach ($this->queues as $q) {
					$rules .= $q->build_rules($default);
				}
			}

			if ($default == false) {
				$error = sprintf(gettext("SHAPER: no default queue specified for interface %s."), $this->GetInterface()) . " " . gettext("The interface queue will be enforced as default.");
				file_notice("Shaper", $error, "Error occurred", "");
				unset($error);
				return "\n";
			}
			$frule .= $rules;
		} else if ($this->GetEnabled() == "on" && $this->GetScheduler() == "CODELQ") {
			$rules = " altq on " . get_real_interface($this->GetInterface());
			if ($this->GetScheduler()) {
				$rules .= " ".strtolower($this->GetScheduler());
			}
			if ($this->GetQlimit() > 0) {
				$rules .= " ( qlimit " . $this->GetQlimit() . " ) ";
			}
			if ($this->GetBandwidth()) {
				$rules .= " bandwidth ".trim($this->GetBandwidth());
				if ($this->GetBwscale()) {
					$rules .= $this->GetBwscale();
				}
			}
			if ($this->GetTbrConfig()) {
				$rules .= " tbrsize ".$this->GetTbrConfig();
			}

			$rules .= " queue";
		}

		$rules .= " \n";
		return $rules;
	}

	function build_javascript() {
		$javascript = "<script type=\"text/javascript\">";
		$javascript .= "//<![CDATA[\n";
		$javascript .= "function mySuspend() {";
		$javascript .= "if (document.layers && document.layers['shaperarea'] != null) ";
		$javascript .= "document.layers['shaperarea'].visibility = 'hidden'; ";
		$javascript .= "else if (document.all)";
		$javascript .= "document.all['shaperarea'].style.visibility = 'hidden';";
		$javascript .= "}";

		$javascript .= "function myResume() {";
		$javascript .= "if (document.layers && document.layers['shaperarea'] != null) ";
		$javascript .= "document.layers['shaperarea'].visibility = 'visible';";
		$javascript .= "else if (document.all) ";
		$javascript .= "document.all['shaperarea'].style.visibility = 'visible';";
		$javascript .= "}";
		$javascript .= "//]]>";
		$javascript .= "</script>";

		return $javascript;
	}

	function build_shortform() {
		global $g;

		$altq =& $this;

		if ($altq) {
			$scheduler = ": " . $altq->GetScheduler();
		}

		$form = '<dl class="dl-horizontal">';
		$form .= '	<dt>';
		$form .= '		<a href="firewall_shaper.php?interface=' . $this->GetInterface() . '&amp;queue=' . $this->GetQname() . '&amp;action=show">' . $shaperIFlist[$this->GetInterface()] . '</a>';
		$form .= '	</dt>';
		$form .= '	<dd>';
		$form .=		$scheduler;
		$form .= '	</dd>';

		$form .= '	<dt>';
		$form .=		'Bandwidth';
		$form .= '	</dt>';
		$form .= '	<dd>';
		$form .=		$this->GetBandwidth() . '&nbsp;' . $this->GetBwscaleText();
		$form .= '	</dd>';

		$form .= '	<dt>';
		$form .= 'Disable';
		$form .= '	<dt>';
		$form .= '	<dd>';

		$form .= '<a class="btn btn-danger btn-xs" href="firewall_shaper_queues.php?interface=';
		$form .= $this->GetInterface() . '&amp;queue=';
		$form .= $this->GetQname() . '&amp;action=delete">';
		$form .= '<i class="fa fa-trash icon-embed-btn"></i>';
		$form .= gettext("Remove shaper from this interface") . '</a>';

		$form .= '	</dd>';

		$form .= '</dl>';

		return $form;

	}

	/*
	 * For requesting the parameters of the root queues
	 * to the user like the traffic wizard does.
	 */
	function build_form() {

		$sform = new Form();

		$sform->setAction("firewall_shaper.php");

		$section = new Form_Section(null);

		$section->addInput(new Form_Checkbox(
			'enabled',
			'Enable/Disable',
			'Enable/disable discipline and its children',
			($this->GetEnabled() == "on"),
			'on'
		));

		$section->addInput(new Form_StaticText(
			'*Name',
			$this->GetQname()
		));

		$section->addInput(new Form_Select(
			'scheduler',
			'Scheduler Type',
			$this->GetScheduler(),
			array('HFSC' => 'HFSC',
				  'CBQ' => 'CBQ',
				  'FAIRQ' => 'FAIRQ',
				  'CODELQ' => 'CODELQ',
				  'PRIQ' => 'PRIQ')
		))->setHelp('Changing this changes all child queues! Beware information can be lost.');

		$group = new Form_group('Bandwidth');

		$group->add(new Form_Input(
			'bandwidth',
			null,
			'number',
			$this->GetBandwidth()
		));

		$group->add(new Form_Select(
			'bandwidthtype',
			null,
			$this->GetBwscale(),
			array('Kb' => 'Kbit/s',
				  'Mb' => 'Mbit/s',
				  'Gb' => 'Gbit/s',
				  'b' => 'Bit/s',
				  '%' => '%')
		));

		$section->add($group);

		$section->addInput(new Form_Input(
			'qlimit',
			'Queue Limit',
			'number',
			$this->GetQlimit()
		));

		$section->addInput(new Form_Input(
			'tbrconfig',
			'TBR Size',
			'number',
			$this->GetTbrConfig()
		))->setHelp('Adjusts the size, in bytes, of the token bucket regulator. If not specified, heuristics based on the interface ' .
					'bandwidth are used to determine the size.');

		$section->addInput(new Form_Input(
			'interface',
			null,
			'hidden',
			$this->GetInterface()
		));

		$section->addInput(new Form_Input(
			'name',
			null,
			'hidden',
			$this->GetQname()
		));

		$sform->add($section);

		return($sform);
	}

	function update_altq_queue_data(&$data) {
		$this->ReadConfig($data);
	}

	/*
	 * Should call on each of it queues and subqueues
	 * the same function much like build_rules();
	 */
	function wconfig() {
		$cflink = &get_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['interface'] = $this->GetInterface();
		$cflink['name'] = $this->GetQname();
		$cflink['scheduler'] = $this->GetScheduler();
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['tbrconfig'] = trim($this->GetTbrConfig());
		if (empty($cflink['tbrconfig'])) {
			unset($cflink['tbrconfig']);
		}
		$cflink['enabled'] = $this->GetEnabled();
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
	}

}

class priq_queue {
	var $qname;
	var $qinterface;
	var $qlimit;
	var $qpriority;
	var $description;
	var $isparent;
	var $qbandwidth;
	var $qbandwidthtype;
	var $qdefault = "";
	var $qrio = "";
	var $qred = "";
	var $qcodel = "";
	var $qecn = "";
	var $qack;
	var $qenabled = "";
	var $qparent;
	var $link;

	/* This is here to help with form building and building rules/lists */
	var $subqueues = array();

	/* Accessor functions */
	function SetLink($link) {
		$this->link = $link;
	}
	function GetLink() {
		return $this->link;
	}
	function &GetParent() {
		return $this->qparent;
	}
	function SetParent(&$parent) {
		$this->qparent = &$parent;
	}
	function GetEnabled() {
		return $this->qenabled;
	}
	function SetEnabled($value) {
		$this->qenabled = $value;
	}
	function CanHaveChildren() {
		return false;
	}
	function CanBeDeleted() {
		return true;
	}
	function GetQname() {
		return $this->qname;
	}
	function SetQname($name) {
		$this->qname = trim($name);
	}
	function GetBandwidth() {
		return $this->qbandwidth;
	}
	function SetBandwidth($bandwidth) {
		$this->qbandwidth = $bandwidth;
	}
	function GetInterface() {
		return $this->qinterface;
	}
	function SetInterface($name) {
		$this->qinterface = trim($name);
	}
	function GetQlimit() {
		return $this->qlimit;
	}
	function SetQlimit($limit) {
		$this->qlimit = $limit;
	}
	function GetQpriority() {
		return $this->qpriority;
	}
	function SetQpriority($priority) {
		$this->qpriority = $priority;
	}
	function GetDescription() {
		return $this->description;
	}
	function SetDescription($str) {
		$this->description = trim($str);
	}
	function GetFirstime() {
		return $this->firsttime;
	}
	function SetFirsttime($number) {
		$this->firsttime = $number;
	}
	function CheckBandwidth($bw, $bwtype) {
		$parent = $this->GetParent();

		// If the child queues have bandwidth specified in %, there is no point trying
		// to compare the total to the parent's bandwidth, which is defined in Kb/S or Mb/S etc.
		// ToDo: Add check for total % > 100
		//		 If one child is in %, they must all be

		if ($bwtype != "%") {
			$sum = $parent->GetTotalBw(($bw > 0) ? $this : NULL);

			if ($bw > 0) {
				$sum += get_bandwidth($bw, $bwtype, $parent);
			}

			if ($sum > get_queue_bandwidth($parent)) {
				return 1;
			}
		}

		foreach ($this->subqueues as $q) {
			if ($q->CheckBandwidth(0, '')) {
				return 1;
			}
		}

		return 0;
	}
	function GetTotalBw($qignore = NULL) {
		$sum = 0;
		foreach ($this->subqueues as $q) {
			if ($qignore != NULL && $qignore == $q)
				continue;
			$sum += get_bandwidth($q->GetBandwidth(), $q->GetBwscale(), $q->GetParent());
		}

		return $sum;
	}
	function GetBwscale() {
		return $this->qbandwidthtype;
	}
	function SetBwscale($scale) {
		$this->qbandwidthtype = $scale;
	}
	function GetDefaultQueuePresent() {
		if ($this->GetDefault()) {
			return true;
		}
		if (!empty($this->subqueues)) {
			foreach ($this->subqueues as $q) {
				if ($q->GetDefault()) {
					return true;
				}
			}
		}

		return false;
	}
	function GetDefault() {
		return $this->qdefault;
	}
	function SetDefault($value = false) {
		$this->qdefault = $value;
	}
	function GetCodel() {
		return $this->codel;
	}
	function SetCodel($codel = false) {
		$this->codel = $codel;
	}
	function GetRed() {
		return $this->qred;
	}
	function SetRed($red = false) {
		$this->qred = $red;
	}
	function GetRio() {
		return $this->qrio;
	}
	function SetRio($rio = false) {
		$this->qrio = $rio;
	}
	function GetEcn() {
		return $this->qecn;
	}
	function SetEcn($ecn = false) {
		$this->qecn = $ecn;
	}
	function GetAck() {
		return $this->qack;
	}
	function SetAck($ack = false) {
		$this->qack = $ack;
	}

	function GetBwscaleText() {
		switch ($this->qbandwidthtype) {
			case "b":
				$bwscaletext = "Bit/s";
				break;
			case "Kb":
				$bwscaletext = "Kbit/s";
				break;
			case "Mb":
				$bwscaletext = "Mbit/s";
				break;
			case "Gb":
				$bwscaletext = "Gbit/s";
				break;
			default:
				/* For others that do not need translating like % */
				$bwscaletext = $this->qbandwidthtype;
				break;
		}
		return $bwscaletext;
	}

	function build_javascript() {
		$javascript = "<script type=\"text/javascript\">";
		$javascript .= "//<![CDATA[\n";
		$javascript .= "function mySuspend() { \n";
		$javascript .= "if (document.layers && document.layers['shaperarea'] != null)\n";
		$javascript .= "document.layers['shaperarea'].visibility = 'hidden';\n";
		$javascript .= "else if (document.all)\n";
		$javascript .= "document.all['shaperarea'].style.visibility = 'hidden';\n";
		$javascript .= "}\n";

		$javascript .= "function myResume() {\n";
		$javascript .= "if (document.layers && document.layers['shaperarea'] != null)\n";
		$javascript .= "document.layers['shaperarea'].visibility = 'visible';\n";
		$javascript .= "else if (document.all)\n";
		$javascript .= "document.all['shaperarea'].style.visibility = 'visible';\n";
		$javascript .= "}\n";
		$javascript .= "//]]>";
		$javascript .= "</script>";

		return $javascript;
	}

	function &add_queue($interface, &$qname, &$path, &$input_errors) { return; }

	/*
	 * Currently this will not be called unless we decide to clone a whole
	 * queue tree on the 'By Queues' view or support drag&drop on the tree/list
	 */
	function copy_queue($interface, &$cflink) {

		$cflink['name'] = $this->GetQname();
		$cflink['interface'] = $interface;
		$cflink['qlimit'] = $this->GetQlimit();
		$cflink['priority'] = $this->GetQpriority();
		$cflink['description'] = $this->GetDescription();
		$cflink['enabled'] = $this->GetEnabled();
		$cflink['default'] = $this->GetDefault();
		$cflink['red'] = $this->GetRed();
		$cflink['codel'] = $this->GetCodel();
		$cflink['rio'] = $this->GetRio();
		$cflink['ecn'] = $this->GetEcn();

		if (is_array($this->subqueues)) {
			$cflinkp['queue'] = array();
			foreach ($this->subqueues as $q) {
				$cflink['queue'][$q->GetQname()] = array();
				$q->copy_queue($interface, $cflink['queue'][$q->GetQname()]);
			}
		}
	}

	function clean_queue($sched) {
		clean_child_queues($sched, $this->GetLink());
		if (is_array($this->subqueues)) {
			foreach ($this->subqueues as $q) {
				$q->clean_queue($sched);
			}
		}
	}

	function &get_queue_list(&$qlist) {

		$qlist[$this->GetQname()] = & $this;
		if (is_array($this->subqueues)) {
			foreach ($this->subqueues as $queue) {
				$queue->get_queue_list($qlist);
			}
		}
	}

	function delete_queue() {
		unref_on_altq_queue_list($this->GetQname());
		cleanup_queue_from_rules($this->GetQname());
		unset_object_by_reference($this->GetLink());
	}

	function delete_all() {
		if (count($this->subqueues)) {
			foreach ($this->subqueues as $q) {
				$q->delete_all();
				unset_object_by_reference($q->GetLink());
				unset($q);
			}
			unset($this->subqueues);
		}
	}

	function &find_queue($interface, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}
	}

	function find_parentqueue($interface, $qname) { return; }

	function validate_input($data, &$input_errors) {

		$reqdfields[] = "name";
		$reqdfieldsn[] = gettext("Name");
		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);

		if ($data['bandwidth'] && (!is_numeric($data['bandwidth']))) {
			$input_errors[] = gettext("Bandwidth must be an integer.");
		}
		if ($data['bandwidth'] < 0) {
			$input_errors[] = gettext("Bandwidth cannot be negative.");
		}
		if ($data['bandwidthtype'] == "%") {
			if ($data['bandwidth'] > 100 || $data['bandwidth'] < 0) {
				$input_errors[] = gettext("Bandwidth in percentage should be between 1 and 100.");
			}
		}
		if ($this->CheckBandwidth($data['bandwidth'], $data['bandwidthtype']))
			$input_errors[] = "The sum of child bandwidth is higher than parent.";

		if (isset($data['priority']) && (!is_numeric($data['priority']) ||
		    ($data['priority'] < 0) || ($data['priority'] > 15))) {
			$input_errors[] = gettext("The priority must be an integer between 0 and 15.");
		}
		if ($data['qlimit'] && (!is_numeric($data['qlimit']))) {
				$input_errors[] = gettext("Queue limit must be an integer");
		}
		if ($data['qlimit'] < 0) {
				$input_errors[] = gettext("Queue limit must be positive");
		}
		if (!empty($data['newname']) && !preg_match("/^[a-zA-Z0-9_-]*$/", $data['newname'])) {
			$input_errors[] = gettext("Queue names must be alphanumeric and _ or - only.");
		}
		if (!empty($data['name']) && !preg_match("/^[a-zA-Z0-9_-]*$/", $data['name'])) {
			$input_errors[] = gettext("Queue names must be alphanumeric and _ or - only.");
		}
		$default = $this->GetDefault();
		if (!empty($data['default']) && altq_get_default_queue($data['interface']) && empty($default)) {
			$input_errors[] = gettext("Only one default queue per interface is allowed.");
		}
	}

	function ReadConfig(&$q) {
		if (!empty($q['name']) && !empty($q['newname']) && $q['name'] != $q['newname']) {
			$this->SetQname($q['newname']);
		} else if (!empty($q['newname'])) {
			$this->SetQname($q['newname']);
		} else if (isset($q['name'])) {
			$this->SetQname($q['name']);
		}
		if (isset($q['interface'])) {
			$this->SetInterface($q['interface']);
		}
		$this->SetBandwidth($q['bandwidth']);
		if ($q['bandwidthtype'] <> "") {
			$this->SetBwscale($q['bandwidthtype']);
		}
		if (!empty($q['qlimit'])) {
			$this->SetQlimit($q['qlimit']);
		} else {
			$this->SetQlimit(""); // Default
		}
		if (is_numeric($q['priority'])) {
			$this->SetQPriority($q['priority']);
		} else {
			$this->SetQpriority("");
		}
		if (!empty($q['description'])) {
			$this->SetDescription($q['description']);
		} else {
			$this->SetDescription("");
		}
		if (!empty($q['red'])) {
			$this->SetRed($q['red']);
		} else {
			$this->SetRed();
		}
		if (!empty($q['codel'])) {
			$this->SetCodel($q['codel']);
		} else {
			$this->SetCodel();
		}
		if (!empty($q['rio'])) {
			$this->SetRio($q['rio']);
		} else {
			$this->SetRio();
		}
		if (!empty($q['ecn'])) {
			$this->SetEcn($q['ecn']);
		} else {
			$this->SetEcn();
		}
		if (!empty($q['default'])) {
			$this->SetDefault($q['default']);
		} else {
			$this->SetDefault();
		}
		if (!empty($q['enabled'])) {
			$this->SetEnabled($q['enabled']);
		} else {
			$this->SetEnabled("");
		}
	}

	function build_tree() {
		$tree = " <li><a href=\"firewall_shaper.php?interface=". $this->GetInterface()."&amp;queue=". htmlspecialchars($this->GetQname())."&amp;action=show";
		$tree .= "\" ";
		$tmpvalue = $this->GetDefault();
		if (!empty($tmpvalue)) {
			$tree .= " class=\"navlnk\"";
		}
		$tree .= " >" . htmlspecialchars($this->GetQname()) . "</a>";
		/*
		 * Not needed here!
		 * if (is_array($queues) {
		 *	  $tree .= "<ul>";
		 *	  foreach ($q as $queues)
		 *		  $tree .= $queues['$q->GetName()']->build_tree();
		 *	  endforeach
		 *	  $tree .= "</ul>";
		 * }
		 */

		$tree .= "</li>";

		return $tree;
	}

	/* Should return something like:
	 * queue $qname on $qinterface bandwidth ....
	 */
	function build_rules(&$default = false) {
		$pfq_rule = " queue ". $this->qname;
		if ($this->GetInterface()) {
			$pfq_rule .= " on ".get_real_interface($this->GetInterface());
		}
		$tmpvalue = $this->GetQpriority();
		if (is_numeric($tmpvalue)) {
			$pfq_rule .= " priority ".$this->GetQpriority();
		}
		$tmpvalue = $this->GetQlimit();
		if (!empty($tmpvalue)) {
			$pfq_rule .= " qlimit " . $this->GetQlimit();
		}
		if ($this->GetRed() || $this->GetRio() || $this->GetEcn() || $this->GetDefault() || $this->GetCodel()) {
			$pfq_rule .= " priq ( ";
			$tmpvalue = $this->GetRed();
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " red ";
			}
			$tmpvalue = $this->GetRio();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " rio ";
			}
			$tmpvalue = $this->GetEcn();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " ecn ";
			}
			$tmpvalue = $this->GetCodel();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " codel ";
			}
			$tmpvalue = $this->GetDefault();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$pfq_rule .= " default ";
				$default = true;
			}
			$pfq_rule .= " ) ";
		}

		$pfq_rule .= " \n";

		return $pfq_rule;
	}

	/*
	 * To return the html form to show to user
	 * for getting the parameters.
	 * Should do even for first time when the
	 * object is created and later when we may
	 * need to update it. (2)
	 */

	function build_form() {

		$sform = new Form();

		$sform->setAction("firewall_shaper.php");

		$section = new Form_Section("");

		$section->addInput(new Form_Checkbox(
			'enabled',
			'Enable/Disable',
			'Enable/disable discipline and its children',
			($this->GetEnabled() == "on"),
			'on'
		));

		$section->addInput(new Form_Input(
			'newname',
			'*Name',
			'text',
			$this->GetQname()
		))->setHelp('Enter the name of the queue here. Do not use spaces and limit the size to 15 characters.');

		$section->addInput(new Form_Input(
			'name',
			null,
			'hidden',
			$this->GetQname()
		));

		if (!is_a($this, "hfsc_queue")) {
			$section->addInput(new Form_Input(
				'priority',
				'Priority',
				'number',
				$this->GetQpriority(),
				['min' => '0', 'max'=> '']
			))->setHelp('For cbq and fairq the range is 0 to 7. The default is 1. For priq the range is 0 to 15, queues with a higher priority are preferred in the case of overload.');
		}

		$section->addInput(new Form_Input(
			'qlimit',
			'Queue Limit',
			'number',
			$this->GetQlimit()
		))->setHelp('Queue limit in packets.');

		$group = new Form_Group('Scheduler options');

		if (empty($this->subqueues)) {
			$group->add(new Form_Checkbox(
				'default',
				null,
				null,
				$this->GetDefault(),
				'default'
			))->setHelp('Default Queue');
		}

		$group->add(new Form_Checkbox(
			'red',
			null,
			null,
			!empty($this->GetRed())
		))->setHelp('%1$sRandom Early Detection%2$s', '<a target="_new" href="https://web.archive.org/web/20160404153707/http://www.openbsd.org/faq/pf/queueing.html#red">', '</a>');

		$group->add(new Form_Checkbox(
			'rio',
			null,
			null,
			!empty($this->GetRio())
		))->setHelp('%1$sRandom Early Detection In and Out%2$s', '<a target="_new" href="https://web.archive.org/web/20160404153707/http://www.openbsd.org/faq/pf/queueing.html#rio">', '</a>');

		$group->add(new Form_Checkbox(
			'ecn',
			null,
			null,
			!empty($this->GetEcn())
		))->setHelp('%1$sExplicit Congestion Notification%2$s', '<a target="_new" href="https://web.archive.org/web/20160404153707/http://www.openbsd.org/faq/pf/queueing.html#ecn">', '</a>');

		$group->add(new Form_Checkbox(
			'codel',
			null,
			null,
			!empty($this->GetCodel())
		))->setHelp('%1$sCodel Active Queue%2$s', '<a target="_new" href="https://web.archive.org/web/20160404153707/http://www.openbsd.org/faq/pf/queueing.html#ecn">', '</a>');

		$group->setHelp('Select options for this queue');

		$section->add($group);

		$section->addInput(new Form_Input(
			'description',
			'Description',
			'text',
			$this->GetDescription()
		));

		$sform->add($section);

		$sform->addGlobal(new Form_Input(
			'interface',
			null,
			'hidden',
			$this->GetInterface()
		));

		$sform->addGlobal(new Form_Input(
			'name',
			null,
			'hidden',
			$this->GetQname()
		));

		return($sform);
	}

	function build_shortform() {
		/* XXX: Hacks in sight. Mostly layer violations!  */
		global $g, $altq_list_queues;
		global $shaperIFlist;

		$altq =& $altq_list_queues[$this->GetInterface()];

		if ($altq) {
			$scheduler = $altq->GetScheduler();
		}

		$form = '<dl class="dl-horizontal">';
		$form .= '	<dt>';
		$form .= '		<a href="firewall_shaper.php?interface=' . $this->GetInterface() . '&amp;queue=' . $this->GetQname() . '&amp;action=show">' . $shaperIFlist[$this->GetInterface()] . '</a>';
		$form .= '	</dt>';
		$form .= '	<dd>';
		$form .=		$scheduler;
		$form .= '	</dd>';

		$form .= '	<dt>';
		$form .=		'Bandwidth';
		$form .= '	</dt>';
		$form .= '	<dd>';
		$form .=		$this->GetBandwidth() . '&nbsp;' . $this->GetBwscaleText();
		$form .= '	</dd>';

		$tmpvalue = $this->GetQpriority();
		if (!empty($tmpvalue)) {
			$form .= '	<dt>';
			$form .=		'Priority';
			$form .= '	<dt>';
			$form .= '	<dd>';
			$form .=		'On';
			$form .= '	</dd>';
		}

		$tmpvalue = $this->GetDefault();
		if (!empty($tmpvalue)) {
			$form .= '	<dt>';
			$form .=		'Default';
			$form .= '	<dt>';
			$form .= '	<dd>';
			$form .=		'On';
			$form .= '	</dd>';
		}

			$form .= '	<dt>';
			$form .= 'Delete';
			$form .= '	<dt>';
			$form .= '	<dd>';

			$form .= '<a class="btn btn-danger btn-xs" href="firewall_shaper_queues.php?interface=';
			$form .= $this->GetInterface() . '&amp;queue=';
			$form .= $this->GetQname() . '&amp;action=delete">';
			$form .= '<i class="fa fa-trash icon-embed-btn"></i>';
			$form .= gettext("Delete Queue from this Interface") . '</a>';

			$form .= '	</dd>';

			$form .= '</dl>';

		return $form;

	}

	function update_altq_queue_data(&$q) {
		$this->ReadConfig($q);
	}

	function wconfig() {
		$cflink =& get_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['name'] = $this->GetQname();
		$cflink['interface'] = $this->GetInterface();
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = trim($this->GetQpriority());
		if (!is_numeric($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['description'] = trim($this->GetDescription());
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['enabled'] = trim($this->GetEnabled());
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = trim($this->GetDefault());
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['codel'] = trim($this->GetCodel());
		if (empty($cflink['codel'])) {
			unset($cflink['codel']);
		}
		$cflink['rio'] = trim($this->GetRio());
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
	}
}

class hfsc_queue extends priq_queue {
	/* realtime */
	var $realtime;
	var $r_m1;
	var $r_d;
	var $r_m2;
	/* linkshare */
	var $linkshare;
	var $l_m1;
	var $l_d;
	var $l_m2;
	/* upperlimit */
	var $upperlimit;
	var $u_m1;
	var $u_d;
	var $u_m2;

	/*
	 * HFSC can have nested queues.
	 */
	function CanHaveChildren() {
		return true;
	}
	function GetRealtime() {
		return $this->realtime;
	}
	function GetR_m1() {
		return $this->r_m1;
	}
	function GetR_d() {
		return $this->r_d;
	}
	function GetR_m2() {
		return $this->r_m2;
	}
	function SetRealtime() {
		$this->realtime = "on";
	}
	function DisableRealtime() {
		$this->realtime = "";
	}
	function SetR_m1($value) {
		$this->r_m1 = $value;
	}
	function SetR_d($value) {
		$this->r_d = $value;
	}
	function SetR_m2($value) {
		$this->r_m2 = $value;
	}
	function GetLinkshare() {
		return $this->linkshare;
	}
	function DisableLinkshare() {
		$this->linkshare = "";
	}
	function GetL_m1() {
		return $this->l_m1;
	}
	function GetL_d() {
		return $this->l_d;
	}
	function GetL_m2() {
		return $this->l_m2;
	}
	function SetLinkshare() {
		$this->linkshare = "on";
	}
	function SetL_m1($value) {
		$this->l_m1 = $value;
	}
	function SetL_d($value) {
		$this->l_d = $value;
	}
	function SetL_m2($value) {
		$this->l_m2 = $value;
	}
	function GetUpperlimit() {
		return $this->upperlimit;
	}
	function GetU_m1() {
		return $this->u_m1;
	}
	function GetU_d() {
		return $this->u_d;
	}
	function GetU_m2() {
		return $this->u_m2;
	}
	function SetUpperlimit() {
		$this->upperlimit = "on";
	}
	function DisableUpperlimit() {
		$this->upperlimit = "";
	}
	function SetU_m1($value) {
		$this->u_m1 = $value;
	}
	function SetU_d($value) {
		$this->u_d = $value;
	}
	function SetU_m2($value) {
		$this->u_m2 = $value;
	}

	function &add_queue($interface, &$qname, &$path, &$input_errors) {

		if (!is_array($this->subqueues)) {
			$this->subqueues = array();
		}
		$__tmp_q = new hfsc_queue(); $q =& $__tmp_q;
		$q->SetInterface($this->GetInterface());
		$q->SetParent($this);
		$q->ReadConfig($qname);
		$q->validate_input($qname, $input_errors);

		$q->SetEnabled("on");
		$q->SetLink($path);

		$this->subqueues[$q->GetQname()] =& $q; //new hfsc_queue()
		ref_on_altq_queue_list($this->GetQname(), $q->GetQname());
		if (is_array($qname['queue'])) {
			foreach ($qname['queue'] as $key1 => $que) {
				array_push($path, $key1);
				$q->add_queue($q->GetInterface(), $que, $path, $input_errors);
				array_pop($path);
			}
		}

		return $q;
	}

	function copy_queue($interface, &$cflink) {

		$cflink['name'] = $this->GetQname();
		$cflink['interface'] = $interface;
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = trim($this->GetQpriority());
		if (empty($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['description'] = trim($this->GetDescription());
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = trim($this->GetEnabled());
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = trim($this->GetDefault());
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['rio'] = trim($this->GetRio());
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
		if ($this->GetLinkshare() <> "") {
			if ($this->GetL_m1() <> "") {
				$cflink['linkshare1'] = $this->GetL_m1();
				$cflink['linkshare2'] = $this->GetL_d();
				$cflink['linkshare'] = "on";
			} else {
				unset($cflink['linkshare1']);
				unset($cflink['linkshare2']);
				unset($cflink['linkshare']);
			}
			if ($this->GetL_m2() <> "") {
				$cflink['linkshare3'] = $this->GetL_m2();
				$cflink['linkshare'] = "on";
			} else {
				unset($cflink['linkshare3']);
				unset($cflink['linkshare']);
			}
		}
		if ($this->GetRealtime() <> "") {
			if ($this->GetR_m1() <> "") {
				$cflink['realtime1'] = $this->GetR_m1();
				$cflink['realtime2'] = $this->GetR_d();
				$cflink['realtime'] = "on";
			} else {
				unset($cflink['realtime1']);
				unset($cflink['realtime2']);
				unset($cflink['realtime']);
			}
			if ($this->GetR_m2() <> "") {
				$cflink['realtime3'] = $this->GetR_m2();
				$cflink['realtime'] = "on";
			} else {
				unset($cflink['realtime3']);
				unset($cflink['realtime']);
			}
		}
		if ($this->GetUpperlimit() <> "") {
			if ($this->GetU_m1() <> "") {
				$cflink['upperlimit1'] = $this->GetU_m1();
				$cflink['upperlimit2'] = $this->GetU_d();
				$cflink['upperlimit'] = "on";
			} else {
				unset($cflink['upperlimit']);
				unset($cflink['upperlimit1']);
				unset($cflink['upperlimit2']);
			}
			if ($this->GetU_m2() <> "") {
				$cflink['upperlimit3'] = $this->GetU_m2();
				$cflink['upperlimit'] = "on";
			} else {
				unset($cflink['upperlimit3']);
				unset($cflink['upperlimit']);
			}
		}

		if (is_array($this->subqueues)) {
			$cflinkp['queue'] = array();
			foreach ($this->subqueues as $q) {
				$cflink['queue'][$q->GetQname()] = array();
				$q->copy_queue($interface, $cflink['queue'][$q->GetQname()]);
			}
		}
	}

	function delete_queue() {
		unref_on_altq_queue_list($this->GetQname());
		cleanup_queue_from_rules($this->GetQname());
		$parent =& $this->GetParent();
		foreach ($this->subqueues as $q)
			$q->delete_queue();
		unset_object_by_reference($this->GetLink());
	}

	/*
	 * Should search even its children
	 */
	function &find_queue($interface, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}

		foreach ($this->subqueues as $q) {
			$result =& $q->find_queue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function &find_parentqueue($interface, $qname) {
		if ($this->subqueues[$qname]) {
			return $this;
		}
		foreach ($this->subqueues as $q) {
			$result = $q->find_parentqueue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function validate_input($data, &$input_errors) {
		parent::validate_input($data, $input_errors);

		$reqdfields[] = "bandwidth";
		$reqdfieldsn[] = gettext("Bandwidth");
		$reqdfields[] = "bandwidthtype";
		$reqdfieldsn[] = gettext("Bandwidthtype");

		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);

		if (isset($data['linkshare3']) && $data['linkshare3'] <> "") {
			if ($data['bandwidth'] && (!is_numeric($data['bandwidth']))) {
				$input_errors[] = gettext("Bandwidth must be an integer.");
			}

			if ($data['bandwidth'] < 0) {
				$input_errors[] = gettext("Bandwidth cannot be negative.");
			}

			if ($data['bandwidthtype'] == "%") {
				if ($data['bandwidth'] > 100 || $data['bandwidth'] < 0) {
					$input_errors[] = gettext("Bandwidth in percentage should be between 1 and 100.");
				}
			}
		}

		if ($data['upperlimit1'] <> "" && $data['upperlimit2'] == "") {
			$input_errors[] = gettext("upperlimit service curve defined but missing (d) value");
		}
		if ($data['upperlimit2'] <> "" && $data['upperlimit1'] == "") {
			$input_errors[] = gettext("upperlimit service curve defined but missing initial bandwidth (m1) value");
		}
		if ($data['upperlimit1'] <> "" && !is_valid_shaperbw($data['upperlimit1'])) {
			$input_errors[] = gettext("upperlimit m1 value needs to be Kb, Mb, Gb, or %");
		}
		if ($data['upperlimit2'] <> "" && !is_numeric($data['upperlimit2'])) {
			$input_errors[] = gettext("upperlimit d value needs to be numeric");
		}
		if ($data['upperlimit3'] <> "" && !is_valid_shaperbw($data['upperlimit3'])) {
			$input_errors[] = gettext("upperlimit m2 value needs to be Kb, Mb, Gb, or %");
		}

		/*
		if (isset($data['upperlimit']) && $data['upperlimit3'] <> "" && $data['upperlimit1'] <> "") {
			$bw_1 = get_hfsc_bandwidth($this, $data['upperlimit1']);
			$bw_2 = get_hfsc_bandwidth($this, $data['upperlimit3']);
			if (floatval($bw_1) < floatval($bw_2)) {
				$input_errors[] = ("upperlimit m1 cannot be smaller than m2");
			}

			if (get_interface_bandwidth($this) < (0.8 * (floatval($bw_1) + floatval($bw_2)))) {
				$input_errors[] = ("upperlimit specification exceeds 80% of allowable allocation.");
			}
		}
		*/
		if ($data['linkshare1'] <> "" && $data['linkshare2'] == "") {
			$input_errors[] = gettext("linkshare service curve defined but missing (d) value");
		}
		if ($data['linkshare2'] <> "" && $data['linkshare1'] == "") {
			$input_errors[] = gettext("linkshare service curve defined but missing initial bandwidth (m1) value");
		}
		if ($data['linkshare1'] <> "" && !is_valid_shaperbw($data['linkshare1'])) {
			$input_errors[] = gettext("linkshare m1 value needs to be Kb, Mb, Gb, or %");
		}
		if ($data['linkshare2'] <> "" && !is_numeric($data['linkshare2'])) {
			$input_errors[] = gettext("linkshare d value needs to be numeric");
		}
		if ($data['linkshare3'] <> "" && !is_valid_shaperbw($data['linkshare3'])) {
			$input_errors[] = gettext("linkshare m2 value needs to be Kb, Mb, Gb, or %");
		}
		if ($data['realtime1'] <> "" && $data['realtime2'] == "") {
			$input_errors[] = gettext("realtime service curve defined but missing (d) value");
		}
		if ($data['realtime2'] <> "" && $data['realtime1'] == "") {
			$input_errors[] = gettext("realtime service curve defined but missing initial bandwidth (m1) value");
		}

		/*
		if (isset($data['linkshare']) && $data['linkshare3'] <> "" && $data['linkshare1'] <> "" && 0) {
			$bw_1 = get_hfsc_bandwidth($this, $data['linkshare1']);
			$bw_2 = get_hfsc_bandwidth($this, $data['linkshare3']);
			if (floatval($bw_1) < floatval($bw_2)) {
				$input_errors[] = ("linkshare m1 cannot be smaller than m2");
			}

			if (get_interface_bandwidth($this) < (0.8 * (floatval($bw_1) + floatval($bw_2)))) {
				$input_errors[] = ("linkshare specification exceeds 80% of allowable allocation.");
			}
		}
		*/

		if ($data['realtime1'] <> "" && !is_valid_shaperbw($data['realtime1'])) {
			$input_errors[] = gettext("realtime m1 value needs to be Kb, Mb, Gb, or %");
		}
		if ($data['realtime2'] <> "" && !is_numeric($data['realtime2'])) {
			$input_errors[] = gettext("realtime d value needs to be numeric");
		}
		if ($data['realtime3'] <> "" && !is_valid_shaperbw($data['realtime3'])) {
			$input_errors[] = gettext("realtime m2 value needs to be Kb, Mb, Gb, or %");
		}

		/*
		if (isset($data['realtime']) && $data['realtime3'] <> "" && $data['realtime1'] <> "" && 0) {
			$bw_1 = get_hfsc_bandwidth($this, $data['realtime1']);
			$bw_2 = get_hfsc_bandwidth($this, $data['realtime3']);
			if (floatval($bw_1) < floatval($bw_2)) {
				$input_errors[] = ("realtime m1 cannot be smaller than m2");
			}

			if (get_interface_bandwidth($this) < (0.8 * (floatval($bw_1) + floatval($bw_2)))) {
				$input_errors[] = ("realtime specification exceeds 80% of allowable allocation.");
			}
		}
		*/
	}

	function ReadConfig(&$cflink) {
		if (!empty($cflink['linkshare'])) {
			if (!empty($cflink['linkshare1'])) {
				$this->SetL_m1($cflink['linkshare1']);
				$this->SetL_d($cflink['linkshare2']);
				$this->SetLinkshare();
			} else {
				$this->SetL_m1("");
				$this->SetL_d("");
				$this->DisableLinkshare();
			}
			if (!empty($cflink['linkshare3'])) {
				$this->SetL_m2($cflink['linkshare3']);
				$this->SetLinkshare();
			}
		} else {
			$this->DisableLinkshare();
		}
		if (!empty($cflink['realtime'])) {
			if (!empty($cflink['realtime1'])) {
				$this->SetR_m1($cflink['realtime1']);
				$this->SetR_d($cflink['realtime2']);
				$this->SetRealtime();
			} else {
				$this->SetR_m1("");
				$this->SetR_d("");
				$this->DisableRealtime();
			}
			if (!empty($cflink['realtime3'])) {
				$this->SetR_m2($cflink['realtime3']);
				$this->SetRealtime();
			}
		} else {
			$this->DisableRealtime();
		}
		if (!empty($cflink['upperlimit'])) {
			if (!empty($cflink['upperlimit1'])) {
				$this->SetU_m1($cflink['upperlimit1']);
				$this->SetU_d($cflink['upperlimit2']);
				$this->SetUpperlimit();
			} else {
				$this->SetU_m1("");
				$this->SetU_d("");
				$this->DisableUpperlimit();
			}
			if (!empty($cflink['upperlimit3'])) {
				$this->SetU_m2($cflink['upperlimit3']);
				$this->SetUpperlimit();
			}
		} else {
			$this->DisableUpperlimit();
		}
		parent::ReadConfig($cflink);
	}

	function build_tree() {
		$tree = " <li><a href=\"firewall_shaper.php?interface=" . $this->GetInterface() ."&amp;queue=" . htmlspecialchars($this->GetQname())."&amp;action=show";
		$tree .= "\" ";
		$tmpvalue = $this->GetDefault();
		if (!empty($tmpvalue)) {
			$tree .= " class=\"navlnk\"";
		}
		$tree .= " >" . htmlspecialchars($this->GetQname()) . "</a>";
		if (is_array($this->subqueues)) {
			$tree .= "<ul>";
			foreach ($this->subqueues as $q) {
				$tree .= $q->build_tree();
			}
			$tree .= "</ul>";
		}
		$tree .= "</li>";
		return $tree;
	}

	/* Even this should take children into consideration */
	function build_rules(&$default = false) {

		$pfq_rule = " queue ". $this->qname;
		if ($this->GetInterface()) {
			$pfq_rule .= " on ".get_real_interface($this->GetInterface());
		}
		if ($this->GetBandwidth() && $this->GetBwscale()) {
			$pfq_rule .= " bandwidth ".trim($this->GetBandwidth()).$this->GetBwscale();
		}

		$tmpvalue = $this->GetQlimit();
		if (!empty($tmpvalue)) {
			$pfq_rule .= " qlimit " . $this->GetQlimit();
		}
		if ($this->GetDefault() || $this->GetRed() || $this->GetRio() || $this->GetEcn() || $this->GetCodel() || $this->GetRealtime() <> "" || $this->GetLinkshare() <> "" || $this->GetUpperlimit() <> "") {
			$pfq_rule .= " hfsc ( ";
			$tmpvalue = $this->GetRed();
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " red ";
			}

			$tmpvalue = $this->GetRio();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " rio ";
			}
			$tmpvalue = $this->GetEcn();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " ecn ";
			}
			$tmpvalue = $this->GetCodel();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " codel ";
			}
			$tmpvalue = $this->GetDefault();
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " default ";
				$default = true;
			}

			if ($this->GetRealtime() <> "") {
				if ($comma) {
					$pfq_rule .= " , ";
				}
				if ($this->GetR_m1() <> "" && $this->GetR_d() <> "" && $this->GetR_m2() <> "") {
					$pfq_rule .= " realtime (".$this->GetR_m1() . ", " . $this->GetR_d().", ". $this->GetR_m2() .") ";
				} else if ($this->GetR_m2() <> "") {
					$pfq_rule .= " realtime " . $this->GetR_m2();
				}
				$comma = 1;
			}
			if ($this->GetLinkshare() <> "") {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				if ($this->GetL_m1() <> "" && $this->GetL_d() <> "" && $this->GetL_m2() <> "") {
					$pfq_rule .= " linkshare (".$this->GetL_m1(). ", ". $this->GetL_d(). ", ". $this->GetL_m2(). ") ";
				} else if ($this->GetL_m2() <> "") {
					$pfq_rule .= " linkshare " . $this->GetL_m2() . " ";
				}
				$comma = 1;
			}
			if ($this->GetUpperlimit() <> "") {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				if ($this->GetU_m1() <> "" && $this->GetU_d() <> "" && $this->GetU_m2() <> "") {
							$pfq_rule .= " upperlimit (".$this->GetU_m1().", ". $this->GetU_d().", ". $this->GetU_m2(). ") ";
				} else if ($this->GetU_m2() <> "") {
					$pfq_rule .= " upperlimit " . $this->GetU_m2() . " ";
				}
			}
			$pfq_rule .= " ) ";
		}
		if (count($this->subqueues)) {
			$i = count($this->subqueues);
			$pfq_rule .= " { ";
			foreach ($this->subqueues as $qkey => $qnone) {
				if ($i > 1) {
					$i--;
					$pfq_rule .= " {$qkey}, ";
				} else {
					$pfq_rule .= " {$qkey} ";
				}
			}
			$pfq_rule .= " } \n";
			foreach ($this->subqueues as $q) {
				$pfq_rule .= $q->build_rules($default);
			}
		}

		$pfq_rule .= " \n";

		return $pfq_rule;
	}

	function build_javascript() {

		$javascript = <<<EOJS
<script type="text/javascript">
//<![CDATA[
	events.push(function(){

		// Disables the specified input element
		function disableInput(id, disable) {
			$('#' + id).prop("disabled", disable);
		}

		// Upperlimit
		function enable_upperlimit() {
			disableInput('upperlimit1', !$('#upperlimit').prop('checked'));
			disableInput('upperlimit2', !$('#upperlimit').prop('checked'));
			disableInput('upperlimit3', !$('#upperlimit').prop('checked'));
		}

		$('#upperlimit').click(function () {
			enable_upperlimit();
		});

		enable_upperlimit();

		// realtime
		function enable_realtime() {
			disableInput('realtime1', !$('#realtime').prop('checked'));
			disableInput('realtime2', !$('#realtime').prop('checked'));
			disableInput('realtime3', !$('#realtime').prop('checked'));
		}

		$('#realtime').click(function () {
			enable_realtime();
		});

		enable_realtime();

		// linkshare
		function enable_linkshare() {
			disableInput('linkshare1', !$('#linkshare').prop('checked'));
			disableInput('linkshare2', !$('#linkshare').prop('checked'));
			disableInput('linkshare3', !$('#linkshare').prop('checked'));
		}

		$('#linkshare').click(function () {
			enable_linkshare();
		});

		enable_linkshare();
	});
//]]>
</script>
EOJS;

		return $javascript;
	}

	function build_form() {

		$sform = parent::build_form();

		$section = new Form_Section('Service Curve (sc)');

		$group = new Form_Group('Bandwidth');

		$group->add(new Form_Input(
			'bandwidth',
			null,
			'number',
			$this->GetBandwidth(),
			['step' => 'any', 'min' => '0.000']
		));

		$group->add(new Form_Select(
			'bandwidthtype',
			null,
			$this->GetBwscale(),
			array('Kb' => 'Kbit/s',
				  'Mb' => 'Mbit/s',
				  'Gb' => 'Gbit/s',
				  'b' => 'Bit/s',
				  '%' => '%')
		));

		$group->setHelp('Choose the amount of bandwidth for this queue');

		$section->add($group);

		$group = new Form_Group('Max bandwidth for queue.');

		$group->add(new Form_Checkbox(
			'upperlimit',
			null,
			'Upper Limit',
			($this->GetUpperlimit()<> "")
		));

		$group->add(new Form_Input(
			'upperlimit1',
			null,
			'text',
			$this->GetU_m1()
		))->setHelp('m1');

		$group->add(new Form_Input(
			'upperlimit2',
			null,
			'text',
			$this->GetU_d()
		))->setHelp('d');

		$group->add(new Form_Input(
			'upperlimit3',
			null,
			'text',
			$this->GetU_m2()
		))->setHelp('m2');


		$section->add($group);

		$group = new Form_Group('Min bandwidth for queue.');

		$group->add(new Form_Checkbox(
			'realtime',
			null,
			'Real Time',
			($this->GetRealtime()<> "")
		));

		$group->add(new Form_Input(
			'realtime1',
			null,
			'text',
			$this->GetR_m1()
		))->setHelp('m1');

		$group->add(new Form_Input(
			'realtime2',
			null,
			'text',
			$this->GetR_d()
		))->setHelp('d');

		$group->add(new Form_Input(
			'realtime3',
			null,
			'text',
			$this->GetR_m2()
		))->setHelp('m2');

		$section->add($group);

		$group = new Form_Group('B/W share of a backlogged queue.');

		$group->add(new Form_Checkbox(
			'linkshare',
			null,
			'Link Share',
			($this->GetLinkshare()<> "")
		));

		$group->add(new Form_Input(
			'linkshare1',
			null,
			'text',
			$this->GetL_m1()
		))->setHelp('m1');

		$group->add(new Form_Input(
			'linkshare2',
			null,
			'text',
			$this->GetL_d()
		))->setHelp('d');

		$group->add(new Form_Input(
			'linkshare3',
			null,
			'text',
			$this->GetL_m2()
		))->setHelp('m2');

		$group->sethelp('Bandwidth share overrides priority.%s' .
						'The format for service curve specifications is (m1, d, m2). m2 controls the bandwidth assigned to the queue. ' .
						'm1 and d are optional and can be used to control the initial bandwidth assignment. ' .
						'For the first d milliseconds the queue gets the bandwidth given as m1, afterwards the value given in m2.',
						'<br />');

		$section->add($group);

		$sform->add($section);

		return($sform);
	}

	function update_altq_queue_data(&$data) {
		$this->ReadConfig($data);
	}

	function wconfig() {
		$cflink =& get_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['name'] = $this->GetQname();
		$cflink['interface'] = $this->GetInterface();
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = $this->GetQpriority();
		if (!is_numericint($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['description'] = $this->GetDescription();
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = $this->GetEnabled();
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = $this->GetDefault();
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['rio'] = $this->GetRio();
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
		$cflink['codel'] = trim($this->GetCodel());
		if (empty($cflink['codel'])) {
			unset($cflink['codel']);
		}
		if ($this->GetLinkshare() <> "") {
			if ($this->GetL_m1() <> "") {
				$cflink['linkshare1'] = $this->GetL_m1();
				$cflink['linkshare2'] = $this->GetL_d();
				$cflink['linkshare'] = "on";
			} else {
				unset($cflink['linkshare']);
				unset($cflink['linkshare1']);
				unset($cflink['linkshare2']);
			}
			if ($this->GetL_m2() <> "") {
				$cflink['linkshare3'] = $this->GetL_m2();
				$cflink['linkshare'] = "on";
			} else {
				unset($cflink['linkshare']);
				unset($cflink['linkshare3']);
			}
		} else {
			unset($cflink['linkshare']);
			unset($cflink['linkshare1']);
			unset($cflink['linkshare2']);
			unset($cflink['linkshare3']);
		}
		if ($this->GetRealtime() <> "") {
			if ($this->GetR_m1() <> "") {
				$cflink['realtime1'] = $this->GetR_m1();
				$cflink['realtime2'] = $this->GetR_d();
				$cflink['realtime'] = "on";
			} else {
				unset($cflink['realtime']);
				unset($cflink['realtime1']);
				unset($cflink['realtime2']);
			}
			if ($this->GetR_m2() <> "") {
				$cflink['realtime3'] = $this->GetR_m2();
				$cflink['realtime'] = "on";
			} else {
				unset($cflink['realtime']);
				unset($cflink['realtime3']);
			}
		} else {
			unset($cflink['realtime']);
			unset($cflink['realtime1']);
			unset($cflink['realtime2']);
			unset($cflink['realtime3']);
		}
		if ($this->GetUpperlimit() <> "") {
			if ($this->GetU_m1() <> "") {
				$cflink['upperlimit1'] = $this->GetU_m1();
				$cflink['upperlimit2'] = $this->GetU_d();
				$cflink['upperlimit'] = "on";
			} else {
				unset($cflink['upperlimit']);
				unset($cflink['upperlimit1']);
				unset($cflink['upperlimit2']);
			}
			if ($this->GetU_m2() <> "") {
				$cflink['upperlimit3'] = $this->GetU_m2();
				$cflink['upperlimit'] = "on";
			} else {
				unset($cflink['upperlimit']);
				unset($cflink['upperlimit3']);
			}
		} else {
			unset($cflink['upperlimit']);
			unset($cflink['upperlimit1']);
			unset($cflink['upperlimit2']);
			unset($cflink['upperlimit3']);
		}
	}
}

class cbq_queue extends priq_queue {
	var $qborrow = "";

	function GetBorrow() {
		return $this->qborrow;
	}
	function SetBorrow($borrow) {
		$this->qborrow = $borrow;
	}
	function CanHaveChildren() {
		return true;
	}

	function &add_queue($interface, &$qname, &$path, &$input_errors) {

		if (!is_array($this->subqueues)) {
			$this->subqueues = array();
		}
		$__tmp_q = new cbq_queue(); $q =& $__tmp_q;
		$q->SetInterface($this->GetInterface());
		$q->SetParent($this);
		$q->ReadConfig($qname);
		$q->validate_input($qname, $input_errors);

		$q->SetEnabled("on");
		$q->SetLink($path);
		$this->subqueues[$q->GetQName()] = &$q;
		ref_on_altq_queue_list($this->GetQname(), $q->GetQname());
		if (is_array($qname['queue'])) {
			foreach ($qname['queue'] as $key1 => $que) {
				array_push($path, $key1);
				$q->add_queue($q->GetInterface(), $que, $path, $input_errors);
				array_pop($path);
			}
		}

		return $q;
	}

	function copy_queue($interface, &$cflink) {

		$cflink['interface'] = $interface;
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($clink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = trim($this->GetQpriority());
		if (!is_numeric($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['name'] = $this->GetQname();
		$cflink['description'] = trim($this->GetDescription());
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = trim($this->GetEnabled());
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = trim($this->GetDefault());
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['rio'] = trim($this->GetRio());
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
		$cflink['borrow'] = trim($this->GetBorrow());
		if (empty($cflink['borrow'])) {
			unset($cflink['borrow']);
		}
		if (is_array($this->queues)) {
			$cflinkp['queue'] = array();
			foreach ($this->subqueues as $q) {
				$cflink['queue'][$q->GetQname()] = array();
				$q->copy_queue($interface, $cflink['queue'][$q->GetQname()]);
			}
		}
	}

	/*
	 * Should search even its children
	 */
	function &find_queue($interface, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}
		foreach ($this->subqueues as $q) {
			$result =& $q->find_queue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function &find_parentqueue($interface, $qname) {
		if ($this->subqueues[$qname]) {
			return $this;
		}
		foreach ($this->subqueues as $q) {
			$result = $q->find_parentqueue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function delete_queue() {
		unref_on_altq_queue_list($this->GetQname());
		cleanup_queue_from_rules($this->GetQname());
		foreach ($this->subqueues as $q)
			$q->delete_queue();
		unset_object_by_reference($this->GetLink());
	}

	function validate_input($data, &$input_errors) {
		parent::validate_input($data, $input_errors);

		if ($data['priority'] > 7) {
				$input_errors[] = gettext("Priority must be an integer between 0 and 7.");
		}
		$reqdfields[] = "bandwidth";
		$reqdfieldsn[] = gettext("Bandwidth");
		$reqdfields[] = "bandwidthtype";
		$reqdfieldsn[] = gettext("Bandwidthtype");

		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);
	}

	function ReadConfig(&$q) {
		parent::ReadConfig($q);
		if (!empty($q['borrow'])) {
			$this->SetBorrow("on");
		} else {
			$this->SetBorrow("");
		}
	}

	function build_javascript() {
		return parent::build_javascript();
	}

	function build_tree() {
		$tree = " <li><a href=\"firewall_shaper.php?interface=" . $this->GetInterface()."&amp;queue=" . htmlspecialchars($this->GetQname())."&amp;action=show";
		$tree .= "\" ";
		$tmpvalue = trim($this->GetDefault());
		if (!empty($tmpvalue)) {
			$tree .= " class=\"navlnk\"";
		}
		$tree .= " >" . htmlspecialchars($this->GetQname()) . "</a>";
		if (is_array($this->subqueues)) {
			$tree .= "<ul>";
			foreach ($this->subqueues as $q) {
				$tree .= $q->build_tree();
			}
			$tree .= "</ul>";
		}
		$tree .= "</li>";
		return $tree;
	}

	/* Even this should take children into consideration */
	function build_rules(&$default = false) {
		$pfq_rule = "queue ". $this->qname;
		if ($this->GetInterface()) {
			$pfq_rule .= " on ".get_real_interface($this->GetInterface());
		}
		if ($this->GetBandwidth() && $this->GetBwscale()) {
			$pfq_rule .= " bandwidth ".trim($this->GetBandwidth()).$this->GetBwscale();
		}
		$tmpvalue = $this->GetQpriority();
		if (is_numeric($tmpvalue)) {
			$pfq_rule .= " priority " . $this->GetQpriority();
		}
		$tmpvalue = trim($this->GetQlimit());
		if (!empty($tmpvalue)) {
			$pfq_rule .= " qlimit " . $this->GetQlimit();
		}
		if ($this->GetDefault() || $this->GetRed() || $this->GetRio() || $this->GetEcn() || $this->GetBorrow() || $this->GetCodel()) {
			$pfq_rule .= " cbq ( ";
			$tmpvalue = trim($this->GetRed());
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " red ";
			}
			$tmpvalue = trim($this->GetCodel());
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " codel ";
			}
			$tmpvalue = trim($this->GetRio());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " rio ";
			}
			$tmpvalue = trim($this->GetEcn());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " ecn ";
			}
			$tmpvalue = trim($this->GetDefault());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " default ";
				$default = true;
			}
			$tmpvalue = trim($this->GetBorrow());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= ", ";
				}
				$pfq_rule .= " borrow ";
			}
			$pfq_rule .= " ) ";
		}
		if (count($this->subqueues)) {
			$i = count($this->subqueues);
			$pfq_rule .= " { ";
			foreach ($this->subqueues as $qkey => $qnone) {
				if ($i > 1) {
					$i--;
					$pfq_rule .= " {$qkey}, ";
				} else {
					$pfq_rule .= " {$qkey} ";
				}
			}
			$pfq_rule .= " } \n";
			foreach ($this->subqueues as $q) {
				$pfq_rule .= $q->build_rules($default);
			}
		}

		$pfq_rule .= " \n";
		return $pfq_rule;
	}

	function build_form() {
		$sform = parent::build_form();

		$section = new Form_Section('NOTITLE');

		$group = new Form_Group('Bandwidth');

		$group->add(new Form_Input(
			'bandwidth',
			null,
			'number',
			$this->GetBandwidth()
		));

		$group->add(new Form_Select(
			'bandwidthtype',
			null,
			$this->GetBwscale(),
			array('Kb' => 'Kbit/s',
				  'Mb' => 'Mbit/s',
				  'Gb' => 'Gbit/s',
				  'b' => 'Bit/s',
				  '%' => '%')
		));

		$group->setHelp('Choose the amount of bandwidth for this queue');

		$section->add($group);

		$section->addInput(new Form_Checkbox(
			'borrow',
			'Scheduler option',
			'Borrow from other queues when available',
			($this->GetBorrow() == "on")
		));

		$sform->add($section);

		return $sform;
	}

	function update_altq_queue_data(&$data) {
		$this->ReadConfig($data);
	}

	function wconfig() {
		$cflink =& get_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['interface'] = $this->GetInterface();
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = $this->GetQpriority();
		if (!is_numeric($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['name'] = $this->GetQname();
		$cflink['description'] = $this->GetDescription();
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = trim($this->GetEnabled());
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = trim($this->GetDefault());
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['rio'] = trim($this->GetRio());
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
		$cflink['codel'] = trim($this->GetCodel());
		if (empty($cflink['codel'])) {
			unset($cflink['codel']);
		}
		$cflink['borrow'] = trim($this->GetBorrow());
		if (empty($cflink['borrow'])) {
			unset($cflink['borrow']);
		}
	}
}

class fairq_queue extends priq_queue {
	var $hogs;
	var $buckets;

	function GetBuckets() {
		return $this->buckets;
	}
	function SetBuckets($buckets) {
		$this->buckets = $buckets;
	}
	function GetHogs() {
		return $this->hogs;
	}
	function SetHogs($hogs) {
		$this->hogs = $hogs;
	}
	function CanHaveChildren() {
		return false;
	}


	function copy_queue($interface, &$cflink) {
		$cflink['interface'] = $interface;
		$cflink['qlimit'] = $this->GetQlimit();
		$cflink['priority'] = $this->GetQpriority();
		$cflink['name'] = $this->GetQname();
		$cflink['description'] = $this->GetDescription();
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = $this->GetEnabled();
		$cflink['default'] = $this->GetDefault();
		$cflink['red'] = $this->GetRed();
		$cflink['rio'] = $this->GetRio();
		$cflink['ecn'] = $this->GetEcn();
		$cflink['buckets'] = $this->GetBuckets();
		$cflink['hogs'] = $this->GetHogs();
	}

	/*
	 * Should search even its children
	 */
	function &find_queue($interface, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}
	}

	function find_parentqueue($interface, $qname) { return; }

	function delete_queue() {
		unref_on_altq_queue_list($this->GetQname());
		cleanup_queue_from_rules($this->GetQname());
		unset_object_by_reference($this->GetLink());
	}

	function validate_input($data, &$input_errors) {
		parent::validate_input($data, $input_errors);

		if ($data['priority'] > 7) {
				$input_errors[] = gettext("Priority must be an integer between 0 and 7.");
		}
		$reqdfields[] = "bandwidth";
		$reqdfieldsn[] = gettext("Bandwidth");
		$reqdfields[] = "bandwidthtype";
		$reqdfieldsn[] = gettext("Bandwidthtype");

		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);
	}

	function ReadConfig(&$q) {
		parent::ReadConfig($q);
		if (!empty($q['buckets'])) {
			$this->SetBuckets($q['buckets']);
		} else {
			$this->SetBuckets("");
		}
		if (!empty($q['hogs']) && is_valid_shaperbw($q['hogs'])) {
			$this->SetHogs($q['hogs']);
		} else {
			$this->SetHogs("");
		}
	}

	function build_javascript() {
		return parent::build_javascript();
	}

	function build_tree() {
		$tree = " <li><a href=\"firewall_shaper.php?interface=" .
		$this->GetInterface()."&amp;queue=" . htmlspecialchars($this->GetQname())."&amp;action=show";
		$tree .= "\" ";
		$tmpvalue = trim($this->GetDefault());
		if (!empty($tmpvalue)) {
			$tree .= " class=\"navlnk\"";
		}
		$tree .= " >" . htmlspecialchars($this->GetQname()) . "</a>";
		$tree .= "</li>";
		return $tree;
	}

	/* Even this should take children into consideration */
	function build_rules(&$default = false) {
		$pfq_rule = "queue ". $this->qname;
		if ($this->GetInterface()) {
			$pfq_rule .= " on ".get_real_interface($this->GetInterface());
		}
		if ($this->GetBandwidth() && $this->GetBwscale()) {
			$pfq_rule .= " bandwidth ".trim($this->GetBandwidth()).$this->GetBwscale();
		}
		$tmpvalue = trim($this->GetQpriority());
		if (is_numeric($tmpvalue)) {
			$pfq_rule .= " priority " . $this->GetQpriority();
		}
		$tmpvalue = trim($this->GetQlimit());
		if (!empty($tmpvalue)) {
			$pfq_rule .= " qlimit " . $this->GetQlimit();
		}
		if ($this->GetDefault() || $this->GetRed() || $this->GetRio() ||
		    $this->GetEcn() || $this->GetBuckets() || $this->GetHogs() || $this->GetCodel()) {
			$pfq_rule .= " fairq ( ";
			$tmpvalue = trim($this->GetRed());
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " red ";
			}
			$tmpvalue = trim($this->GetCodel());
			if (!empty($tmpvalue)) {
				$comma = 1;
				$pfq_rule .= " codel ";
			}
			$tmpvalue = trim($this->GetRio());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " rio ";
			}
			$tmpvalue = trim($this->GetEcn());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " ecn ";
			}
			$tmpvalue = trim($this->GetDefault());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= " ,";
				}
				$comma = 1;
				$pfq_rule .= " default ";
				$default = true;
			}
			$tmpvalue = trim($this->GetBuckets());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= ", ";
				}
				$pfq_rule .= " buckets " . $this->GetBuckets() . " ";
			}
			$tmpvalue = trim($this->GetHogs());
			if (!empty($tmpvalue)) {
				if ($comma) {
					$pfq_rule .= ", ";
				}
				$pfq_rule .= " hogs " . $this->GetHogs() . " ";
			}
			$pfq_rule .= " ) ";
		}

		$pfq_rule .= " \n";
		return $pfq_rule;
	}

	function build_form() {
		$form = parent::build_form();

		$section = new Form_Section('');

		$group = new Form_Group('Bandwidth');

		$group->add(new Form_Input(
			'bandwidth',
			null,
			'number',
			$this->GetBandwidth()
		));

		$group->add(new Form_Select(
			'bandwidthtype',
			null,
			$this->GetBwscale(),
			array('Kb' => 'Kbit/s',
				  'Mb' => 'Mbit/s',
				  'Gb' => 'Gbit/s',
				  'b' => 'Bit/s',
				  '%' => '%')
		));

		$group->setHelp('Choose the amount of bandwidth for this queue');

		$section->add($group);

		$section->addInput(new Form_Input(
			'buckets',
			'Scheduler specific options',
			'text',
			$this->GetBuckets()
		))->setHelp('Number of buckets available');

		$section->addInput(new Form_Input(
			'hogs',
			'',
			'text',
			$this->GetHogs()
			))->setHelp('Bandwidth limit for hosts to not saturate link');

		$form->add($section);
		return $form;
	}

	function update_altq_queue_data(&$data) {
		$this->ReadConfig($data);
	}

	function wconfig() {
		$cflink =& get_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['interface'] = $this->GetInterface();
		$cflink['qlimit'] = trim($this->GetQlimit());
		if (empty($cflink['qlimit'])) {
			unset($cflink['qlimit']);
		}
		$cflink['priority'] = trim($this->GetQpriority());
		if (!is_numeric($cflink['priority'])) {
			unset($cflink['priority']);
		}
		$cflink['name'] = $this->GetQname();
		$cflink['description'] = trim($this->GetDescription());
		if (empty($cflink['description'])) {
			unset($cflink['description']);
		}
		$cflink['bandwidth'] = $this->GetBandwidth();
		$cflink['bandwidthtype'] = $this->GetBwscale();
		$cflink['enabled'] = $this->GetEnabled();
		if (empty($cflink['enabled'])) {
			unset($cflink['enabled']);
		}
		$cflink['default'] = trim($this->GetDefault());
		if (empty($cflink['default'])) {
			unset($cflink['default']);
		}
		$cflink['red'] = trim($this->GetRed());
		if (empty($cflink['red'])) {
			unset($cflink['red']);
		}
		$cflink['rio'] = trim($this->GetRio());
		if (empty($cflink['rio'])) {
			unset($cflink['rio']);
		}
		$cflink['ecn'] = trim($this->GetEcn());
		if (empty($cflink['ecn'])) {
			unset($cflink['ecn']);
		}
		$cflink['codel'] = trim($this->GetCodel());
		if (empty($cflink['codel'])) {
			unset($cflink['codel']);
		}
		$cflink['buckets'] = trim($this->GetBuckets());
		if (empty($cflink['buckets'])) {
			unset($cflink['buckets']);
		}
		$cflink['hogs'] = trim($this->GetHogs());
		if (empty($cflink['hogs'])) {
			unset($cflink['hogs']);
		}
	}
}


/*
 * dummynet(4) wrappers.
 */


/*
 * List of respective objects!
 */
$dummynet_pipe_list = array();

class dummynet_class {
	var $qname;
	var $qnumber; /* dummynet(4) uses numbers instead of names; maybe integrate with pf the same as altq does?! */
	var $qlimit;
	var $description;
	var $qenabled;
	var $link;
	var $qparent; /* link to upper class so we do things easily on WF2Q+ rule creation */
	var $plr;

	var $buckets;
	/* mask parameters */
	var $mask;
	var $noerror;

	/* Accessor functions */
	function SetLink($link) {
		$this->link = $link;
	}
	function GetLink() {
		return $this->link;
	}
	function GetMask() {
		if (!isset($this->mask["type"])) {
			$this->mask["type"] = "none";
		}
		return $this->mask;
	}
	function SetMask($mask) {
		$this->mask = $mask;
	}
	function &GetParent() {
		return $this->qparent;
	}
	function SetParent(&$parent) {
		$this->qparent = &$parent;
	}
	function GetEnabled() {
		return $this->qenabled;
	}
	function SetEnabled($value) {
		$this->qenabled = $value;
	}
	function CanHaveChildren() {
		return false;
	}
	function CanBeDeleted() {
		return true;
	}
	function GetQname() {
		return $this->qname;
	}
	function SetQname($name) {
		$this->qname = trim($name);
	}
	function GetQlimit() {
		return $this->qlimit;
	}
	function SetQlimit($limit) {
		$this->qlimit = $limit;
	}
	function GetDescription() {
		return $this->description;
	}
	function SetDescription($str) {
		$this->description = trim($str);
	}
	function GetFirstime() {
		return $this->firsttime;
	}
	function SetFirsttime($number) {
		$this->firsttime = $number;
	}
	function GetBuckets() {
		return $this->buckets;
	}
	function SetBuckets($buckets) {
		$this->buckets = $buckets;
	}
	function SetNumber($number) {
		$this->qnumber = $number;
	}
	function GetNumber() {
		return $this->qnumber;
	}
	function GetPlr() {
		return $this->plr;
	}
	function SetPlr($plr) {
		$this->plr = $plr;
	}

	function build_javascript() {
		$javascript .= "<script type=\"text/javascript\">\n";
		$javascript .= "//<![CDATA[\n";
		$javascript .= "function enable_maskbits(enable_over) {\n";
		$javascript .= "var e = document.getElementById(\"mask\");\n";
		$javascript .= "if ((e.options[e.selectedIndex].text == \"none\") || enable_over) {\n";
		$javascript .= "document.iform.maskbits.disabled = 1;\n";
		$javascript .= "document.iform.maskbits.value = \"\";\n";
		$javascript .= "document.iform.maskbitsv6.disabled = 1;\n";
		$javascript .= "document.iform.maskbitsv6.value = \"\";\n";
		$javascript .= "} else {\n";
		$javascript .= "document.iform.maskbits.disabled = 0;\n";
		$javascript .= "document.iform.maskbitsv6.disabled = 0;\n";
		$javascript .= "}}\n";
		$javascript .= "//]]>\n";
		$javascript .= "</script>\n";
		return $javascript;
	}

	function validate_input($data, &$input_errors) {
		$reqdfields[] = "bandwidth";
		$reqdfieldsn[] = gettext("Bandwidth");
		/*$reqdfields[] = "burst";
		$reqdfieldsn[] = gettext("Burst"); */
		$reqdfields[] = "bandwidthtype";
		$reqdfieldsn[] = gettext("Bandwidthtype");
		$reqdfields[] = "newname";
		$reqdfieldsn[] = gettext("Name");

		shaper_do_input_validation($data, $reqdfields, $reqdfieldsn, $input_errors);

		if ($data['plr'] && (!is_numeric($data['plr']) ||
		    ($data['plr'] < 0) || ($data['plr'] > 1))) {
			$input_errors[] = gettext("Packet Loss Rate must be a value between 0 and 1.");
		}
		if ($data['buckets'] && (!is_numeric($data['buckets']) ||
		    ($data['buckets'] < 16) || ($data['buckets'] > 65535))) {
			$input_errors[] = gettext("Buckets must be an integer between 16 and 65535.");
		}
		if ($data['qlimit'] && (!is_numeric($data['qlimit']))) {
			$input_errors[] = gettext("Queue limit must be an integer");
		}
		if (!empty($data['newname']) && !preg_match("/^[a-zA-Z0-9_-]+$/", $data['newname'])) {
			$input_errors[] = gettext("Queue names must be alphanumeric and _ or - only.");
		}
		if (!empty($data['name']) && !preg_match("/^[a-zA-Z0-9_-]+$/", $data['name'])) {
			$input_errors[] = gettext("Queue names must be alphanumeric and _ or - only.");
		}
		if (isset($data['maskbits']) && ($data['maskbits'] <> "")) {
			if ((!is_numeric($data['maskbits'])) || ($data['maskbits'] <= 0) || ($data['maskbits'] > 32)) {
				$input_errors[] = gettext("IPv4 bit mask must be blank or numeric value between 1 and 32.");
			}
		}
		if (isset($data['maskbitsv6']) && ($data['maskbitsv6'] <> "")) {
			if ((!is_numeric($data['maskbitsv6'])) || ($data['maskbitsv6'] <= 0) || ($data['maskbitsv6'] > 128)) {
				$input_errors[] = gettext("IPv6 bit mask must be blank or numeric value between 1 and 128.");
			}
		}
	}

	function build_mask_rules(&$pfq_rule) {
		$mask = $this->GetMask();
		if (!empty($mask['type'])) {
			if ($mask['type'] <> 'none') {
				$pfq_rule .= " mask";
			}
			switch ($mask['type']) {
				case 'srcaddress':
					if (!empty($mask['bitsv6']) && ($mask['bitsv6'] <> "")) {
						$pfq_rule .= " src-ip6 /" . $mask['bitsv6'];
					} else {
						$pfq_rule .= " src-ip6 /128";
					}
					if (!empty($mask['bits']) && ($mask['bits'] <> "")) {
						$pfq_rule .= sprintf(" src-ip 0x%x", gen_subnet_mask_long($mask['bits']));
					} else {
						$pfq_rule .= " src-ip 0xffffffff";
					}
					break;
				case 'dstaddress':
					if (!empty($mask['bitsv6']) && ($mask['bitsv6'] <> "")) {
						$pfq_rule .= " dst-ip6 /" . $mask['bitsv6'];
					} else {
						$pfq_rule .= " dst-ip6 /128";
					}
					if (!empty($mask['bits']) && ($mask['bits'] <> "")) {
						$pfq_rule .= sprintf(" dst-ip 0x%x", gen_subnet_mask_long($mask['bits']));
					} else {
						$pfq_rule .= " dst-ip 0xffffffff";
					}
					break;
				default:
					break;
			}
		}
	}

}

class dnpipe_class extends dummynet_class {
	var $delay;
	var $qbandwidth = array();
	var $qbandwidthtype;

	/* Limiter queue patch */
    var $ecn; // ecn 'on' or 'off'
    var $aqm; // key to aqm_map
    var $aqm_params = array(); // AQM params
    var $scheduler;	// key to scheduler_map
    var $scheduler_params = array(); // AQM params
	function GetAQM() {
			return $this->aqm;
	}
	function SetAQM($aqm) {
			$this->aqm = $aqm;
	}
	function GetAQMParameters() {
			return $this->aqm_params;
	}
	function GetAQMParameter($parameter) {
			return $this->aqm_params[$parameter];
	}
	function SetAQMParameter($key, $value) {
			return $this->aqm_params[$key] = $value;
	}
	function SetAQMParameters($params) {
			$this->aqm_params = $params;
	}
	function GetECN() {
			return $this->ecn;
	}
	function SetECN($ecn) {
			$this->ecn = $ecn;
	}
	function GetScheduler() {
			return $this->scheduler;
	}
	function SetScheduler($scheduler) {
			$this->scheduler = $scheduler;
	}
	function GetSchedulerParameters() {
			return $this->scheduler_params;
	}
	function SetSchedulerParameters($params) {
			$this->scheduler_params = $params;
	}
	function SetSchedulerParameter($key, $value) {
			$this->scheduler_params[$key] = $value;
	}
	function GetSchedulerParameter($key) {
			return $this->scheduler_params[$key];
	}
	/* End limiter queue patch */

		/* This is here to help on form building and building rules/lists */
	var $subqueues = array();

	function CanHaveChildren() {
		return true;
	}
	function SetDelay($delay) {
		$this->delay = $delay;
	}
	function GetDelay() {
		return $this->delay;
	}
	function delete_queue() {
		cleanup_dnqueue_from_rules($this->GetQname());
		foreach ($this->subqueues as $q) {
			$q->delete_queue();
		}
		unset_dn_object_by_reference($this->GetLink());
		@pfSense_ipfw_pipe("pipe delete " . $this->GetNumber());
		@pfSense_ipfw_pipe("sched delete " . $this->GetNumber());
	}
	function GetBandwidth() {
		return $this->qbandwidth;
	}
	function SetBandwidth($bandwidth) {
		$this->qbandwidth = $bandwidth;
	}
	function GetBurst() {
		return $this->qburst;
	}
	function SetBurst($burst) {
		$this->qburst = $burst;
	}

	function &add_queue($interface, &$queue, &$path, &$input_errors) {

		if (!is_array($this->subqueues)) {
			$this->subqueues = array();
		}

		$__tmp_q = new dnqueue_class(); $q =& $__tmp_q;
		$q->SetLink($path);
		$q->SetEnabled("on");
		$q->SetPipe($this->GetQname());
		$q->SetParent($this);
		$q->ReadConfig($queue);
		$q->validate_input($queue, $input_errors);

		if (!is_array($input_errors)) {
			$input_errors = array();
		}

		if (count($input_errors)) {
			log_error(sprintf(gettext('SHAPER: Could not create queue %1$s on interface %2$s because: %3$s'), $q->GetQname(), $interface, print_r($input_errors, true)));
			return $q;
		}
		$number = dnqueue_find_nextnumber();
		$q->SetNumber($number);
		$this->subqueues[$q->GetQname()] = &$q;

		return $q;
	}

	function &get_queue_list(&$q = null) {
		$qlist = array();

		$qlist[$this->GetQname()] = $this->GetNumber();
		if (is_array($this->subqueues)) {
			foreach ($this->subqueues as $queue) {
				$queue->get_queue_list($qlist);
			}
		}
		return $qlist;
	}

	/*
	 * Should search even its children
	 */
	function &find_queue($pipe, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		}
		foreach ($this->subqueues as $q) {
			$result =& $q->find_queue("", $qname);
			if ($result) {
				return $result;
			}
		}
	}

	function &find_parentqueue($pipe, $qname) {
		return NULL;
	}

	function validate_input($data, &$input_errors) {
		parent::validate_input($data, $input_errors);

		$schedule = 0;
		$schedulenone = 0;
		$entries = 0;
		/* XXX: Really no better way? */
		for ($i = 0; $i < 2900; $i++) {
			if (!empty($data["bwsched{$i}"])) {
				if ($data["bwsched{$i}"] != "none") {
					$schedule++;
				} else {
					$schedulenone++;
				}
			}
			if (!empty($data["bandwidth{$i}"])) {
				if (!is_numeric($data["bandwidth{$i}"])) {
					$input_errors[] = sprintf(gettext("Bandwidth for schedule %s must be an integer."), $data["bwsched{$i}"]);
				} else if (($data["burst{$i}"] != "") && (!is_numeric($data["burst{$i}"]))) {
					$input_errors[] = sprintf(gettext("Burst for schedule %s must be an integer."), $data["bwsched{$i}"]);
				} else {
					$entries++;
				}
			}
		}
		if ($schedule == 0 && $entries > 1) {
			$input_errors[] = gettext("A schedule needs to be specified for every additional entry.");
		}
		if ($schedulenone > 0 && $entries > 1) {
			$input_errors[] = gettext("If more than one bandwidth configured all schedules need to be selected.");
		}
		if ($entries == 0) {
			$input_errors[] = gettext("At least one bw specification is necessary.");
		}
		if ($data['delay'] && (!is_numeric($data['delay']))) {
			$input_errors[] = gettext("Delay must be an integer.");
		}
		if ($data['delay'] && is_numeric($data['delay']) &&
		    (($data['delay'] < 0) || ($data['delay'] > 10000))) {
			$input_errors[] = gettext("Delay must be an integer between 0 and 10000.");
		}

		/* Limiter patch */
		$selectedScheduler = getSchedulers()[$data['sched']];
		if (!$selectedScheduler) {
			$input_errors[] = gettext("Selected scheduler not recognized.");
		}
		$selectedAqm = getAQMs()[$data['aqm']];
		if (!empty($data['aqm']) && !$selectedAqm) {
			$input_errors[] = gettext("Selected AQM not recognized.");
		}
		if ($data['ecn'] && $data['ecn'] == 'on' && !$selectedScheduler["ecn"] && !$selectedAqm["ecn"]) {
			$input_errors[] = gettext("Explicit Congestion Notification is selected, but neither " . $selectedAqm["name"] . " nor " . $selectedScheduler["name"] . " support it.");
		}
		/* End limiter patch */
	}

	function ReadConfig(&$q) {
		if (!empty($q['name']) && !empty($q['newname']) && $q['name'] != $q['newname']) {
			$this->SetQname($q['newname']);
		} else if (!empty($q['newname'])) {
			$this->SetQname($q['newname']);
		} else {
			$this->SetQname($q['name']);
		}
		$this->SetNumber($q['number']);

		if (!empty($_POST)) {
			$bandwidth = array();
			/* XXX: Really no better way? */
			for ($i = 0; $i < 2900; $i++) {
				if (isset($q["bandwidth{$i}"]) && $q["bandwidth{$i}"] <> "") {
					$bw = array();
					$bw['bw'] = $q["bandwidth{$i}"];
					$bw['burst'] = $q["burst{$i}"];
					if (isset($q["bwtype{$i}"]) && $q["bwtype{$i}"]) {
						$bw['bwscale'] = $q["bwtype{$i}"];
					}
					if (isset($q["bwsched{$i}"]) && $q["bwsched{$i}"]) {
						$bw['bwsched'] = $q["bwsched{$i}"];
					}
					$bandwidth[] = $bw;
				}
			}
			$this->SetBandwidth($bandwidth);
		}

		if (is_array($q['bandwidth']) && is_array($q['bandwidth']['item'])) {
			$this->SetBandwidth($q['bandwidth']['item']);
			$this->SetBurst($q['burst']['item']);
		}

		if (isset($q['qlimit']) && $q['qlimit'] <> "") {
			$this->SetQlimit($q['qlimit']);
		} else {
			$this->SetQlimit("");
		}
		if (isset($q['mask']) && $q['mask'] <> "") {
			$masktype = $q['mask'];
		} else {
			$masktype = "";
		}
		if (isset($q['maskbits']) && $q['maskbits'] <> "") {
			$maskbits = $q['maskbits'];
		} else {
			$maskbits = "";
		}
		if (isset($q['maskbitsv6']) && $q['maskbitsv6'] <> "") {
			$maskbitsv6 = $q['maskbitsv6'];
		} else {
			$maskbitsv6 = "";
		}
		$this->SetMask(array("type" => $masktype, "bits" => $maskbits, "bitsv6" => $maskbitsv6));
		if (isset($q['buckets']) && $q['buckets'] <> "") {
			$this->SetBuckets($q['buckets']);
		} else {
			$this->SetBuckets("");
		}
		if (isset($q['plr']) && $q['plr'] <> "") {
			$this->SetPlr($q['plr']);
		} else {
			$this->SetPlr("");
		}
		if (isset($q['delay']) && $q['delay'] <> "") {
			$this->SetDelay($q['delay']);
		} else {
			$this->SetDelay(0);
		}
		if (isset($q['description']) && $q['description'] <> "") {
			$this->SetDescription($q['description']);
		} else {
			$this->SetDescription("");
		}
		$this->SetEnabled($q['enabled']);

		/* Limiter patch */
		if (isset($q['aqm']) && $q['aqm'] <> "") {
				$this->SetAQM($q['aqm']);
		} else {
				$this->SetAQM('droptail');
		}
		// parse AQM arguments
		$aqm_map = getAQMs();
		$selectedParameters = $aqm_map[$this->getAQM()]["parameters"];
		if (is_array($selectedParameters)) {
			foreach ($selectedParameters as $key => $value) {
				$config_key = 'param_' . $this->GetAQM() . '_' . $key;
				if (isset($q[$config_key]) && $q[$config_key] <> "") {
					$this->SetAQMParameter($key, $q[$config_key]);
				} else {
					$this->SetAQMParameter($key, $value["default"]);
				}
			}
		}

		if (isset($q['sched']) && $q['sched'] <> "") {
				$this->SetScheduler($q['sched']);
		} else {
				$this->SetScheduler('fifo');
		}
		$scheduler_map = getSchedulers();
		$selectedParameters = $scheduler_map[$this->getScheduler()]["parameters"];
		if (is_array($selectedParameters)) {
			foreach ($selectedParameters as $key => $value) {
				$config_key = 'param_' . $this->GetScheduler() . '_' . $key;
				if (isset($q[$config_key]) && $q[$config_key] <> "") {
					$this->SetSchedulerParameter($key, $q[$config_key]);
				} else {
					$this->SetSchedulerParameter($key, $value["default"]);
				}
			}
		}

		// ecn flag
		$this->SetECN($q['ecn']);
		/* End limiter patch */
	}

	function build_tree() {
		$tree = " <li><a href=\"firewall_shaper_vinterface.php?pipe=" . htmlspecialchars($this->GetQname()) ."&amp;queue=".htmlspecialchars($this->GetQname()) ."&amp;action=show\">";
		$tree .= htmlspecialchars($this->GetQname()) . "</a>";
		if (is_array($this->subqueues)) {
			$tree .= "<ul>";
			foreach ($this->subqueues as $q) {
				$tree .= $q->build_tree();
			}
			$tree .= "</ul>";
		}
		$tree .= "</li>";

		return $tree;
	}

	function build_rules() {
		global $config, $time_based_rules;

		if ($this->GetEnabled() == "") {
			return;
		}

		$pfq_rule = "\npipe ". $this->GetNumber() . " config ";
		$found = false;
		$bandwidth = $this->GetBandwidth();
		if (is_array($bandwidth)) {
			foreach ($bandwidth as $bw) {
				if ($bw['bwsched'] != "none") {
					$time_based_rules = true;
					if (is_array($config['schedules']) && is_array($config['schedules']['schedule'])) {
						foreach ($config['schedules']['schedule'] as $schedule) {
							if ($bw['bwsched'] == $schedule['name']) {
								if (filter_get_time_based_rule_status($schedule)) {
									/* pipe throughputs must always be an integer, enforce that restriction again here. */
									$pfq_rule .= " bw ".round(trim($bw['bw']),0).$bw['bwscale'];
									if (is_numeric($bw['burst']) && ($bw['burst'] > 0)) {
										$pfq_rule .= " burst ".trim($bw['burst']);
									}
									$found = true;
									break;
								}
							}
						}
					} else {
						$pfq_rule .= " bw 0";
						$found = true;
						break;
					}
				} else {
					/* pipe throughputs must always be an integer, enforce that restriction again here. */
					$pfq_rule .= " bw ".round(trim($bw['bw']), 0).$bw['bwscale'];
					if (is_numeric($bw['burst']) && ($bw['burst'] > 0)) {
						$pfq_rule .= " burst ".trim($bw['burst']);
					}
					$found = true;
					break;
				}
			}
			if ($found == false) {
				$pfq_rule .= " bw 0";
			}
		} else {
			$pfq_rule .= " bw 0";
		}

		if ($this->GetQlimit()) {
			$pfq_rule .= " queue " . $this->GetQlimit();
		}
		if ($this->GetPlr()) {
			$pfq_rule .= " plr " . $this->GetPlr();
		}
		if ($this->GetBuckets()) {
			$pfq_rule .= " buckets " . $this->GetBuckets();
		}
		if ($this->GetDelay()) {
			$pfq_rule .= " delay " . $this->GetDelay();
		}
		$this->build_mask_rules($pfq_rule);

		/* Limiter patch */
		$selectedAQM = getAQMs()[$this->getAQM()];
		if ($selectedAQM) {
			$pfq_rule .= " " . FormatParameters($selectedAQM["parameter_format"], $this->GetAQMParameters());
			if ($selectedAQM["ecn"]) {
				if ($this->getECN() == 'on') {
					$pfq_rule .= ' ecn';
				} else {
					$pfq_rule .= ' noecn';
				}
			}
		}
		$selectedScheduler = getSchedulers()[$this->getScheduler()];
		if ($selectedScheduler) {
			$pfq_rule .= "\nsched ". $this->GetNumber() . " config pipe " . $this->GetNumber() . " " . FormatParameters($selectedScheduler["parameter_format"], $this->GetSchedulerParameters());
			if ($selectedScheduler["ecn"]) {
				if ($this->getECN() == 'on') {
					$pfq_rule .= ' ecn';
				} else {
					$pfq_rule .= ' noecn';
				}
			}
		}
		$pfq_rule .= "\n";
		/* End patch */

		if (!empty($this->subqueues) && count($this->subqueues) > 0) {
			foreach ($this->subqueues as $q) {
				$pfq_rule .= $q->build_rules();
			}
		}

		$pfq_rule .= " \n";

		return $pfq_rule;
	}

	function update_dn_data(&$data) {
		$this->ReadConfig($data);
	}

	function build_javascript() {
		global $g, $config;

		$javasr = parent::build_javascript();

		//build list of schedules
		$schedules = "<option value='none'>none</option>";
		if (is_array($config['schedules']) && is_array($config['schedules']['schedule'])) {
			foreach ($config['schedules']['schedule'] as $schedule) {
				if ($schedule['name'] <> "") {
					$schedules .= "<option value='{$schedule['name']}'>{$schedule['name']}</option>";
				}
			}
		}
		$bwopt = "";
		foreach (array("Kb" => "Kbit/s", "Mb" => "Mbit/s", "Gb" => "Gbit/s", "b" => "Bit/s") as $bwidx => $bw) {
			$bwopt .= "<option value='{$bwidx}'>{$bw}</option>";
		}

		$javasr .= <<<EOD
<script type='text/javascript'>
//<![CDATA[
var addBwRowTo = (function() {

	return (function (tableId) {

	var table = document.getElementById(tableId);
	var totalrows = table.rows.length -1;

	var row = table.insertRow(totalrows + 1);
	var cell1 = row.insertCell(0);
	var cell2 = row.insertCell(1);
	var cell3 = row.insertCell(2);
	var cell4 = row.insertCell(3);

	cell1.innerHTML = "<input type='hidden' value='" + totalrows +"' name='bandwidth_row-" + totalrows + "' /><input type='text' class='form-control' name='bandwidth" + totalrows + "' id='bandwidth" + totalrows + "' />";
	cell2.innerHTML = "<input type='hidden' value='" + totalrows +"' name='bwtype_row-" + totalrows + "' /><select class='form-control' name='bwtype" + totalrows + "'>{$bwopt}</select>";
	cell3.innerHTML = "<input type='hidden' value='" + totalrows +"' name='bwsched_row-" + totalrows + "' /><select class='form-control' name='bwsched" + totalrows + "'>{$schedules}</select>";
	cell4.innerHTML = '<a class="btn btn-warning" onclick="removeBwRow(this); return false;" href="#"><i class="fa fa-trash icon-embed-btn"></i>Delete</a>';

	});
})();

function removeBwRow(el) {
	var d = el.parentNode.parentNode.rowIndex;
	document.getElementById('maintable').deleteRow(d);
}

function ceil_func(el){
	el.value = Math.ceil(el.value);

}
//]]>
</script>

EOD;

		return $javasr;
	}

	// Compose a table of bandwidths that can then be inserted into the form using a Form_StaticText
	// The table has been "Bootstrapped" to match the web design while maintaining compatibility with
	// with the javascript in this class
	function build_bwtable() {
		global $config;

		$bandwidth = $this->GetBandwidth();
				//build list of schedules
		$schedules = array();
		$schedules[] = "none";//leave none to leave rule enabled all the time
		if (is_array($config['schedules']) && is_array($config['schedules']['schedule'])) {
			foreach ($config['schedules']['schedule'] as $schedule) {
				if ($schedule['name'] != "") {
					$schedules[] = $schedule['name'];
				}
			}
		}

		$form = '<div class="table-responsive">';
		$form .= '<table id="maintable" class="table table-hover table-striped">';
		$form .= "<thead><tr>";
		$form .= "<th>Bandwidth</th>";
		//$form .= "<td width='35%'><div id='fifthcolumn'>Burst</div></td>";
		$form .= "<th>Bw type</th>";
		$form .= "<th>Schedule</th>";
		$form .= "<th></th>";
		$form .= "</tr></thead>";
		$form .= "<tbody>";

		// If there are no bandwidths defined, make a blank one for convenience
		if (empty($bandwidth)) {
			$bandwidth = array(0 => array('bw' => '', 'bwscale' => 'Kb', 'bwsched' => 'none'));
		}

		if (is_array($bandwidth)) {
			foreach ($bandwidth as $bwidx => $bw) {
				$form .= '<tr>';
				$form .= '<td class="col-xs-4">';
				$form .= "<input class='form-control' onchange=\"ceil_func(this)\" type=\"number\" id=\"bandwidth{$bwidx}\" name=\"bandwidth{$bwidx}\" value=\"" . ceil($bw['bw']) ."\" min=\"0\" step=\"1\"/>";
				//$form .= "</td><td width='20%'>";
				//$form .= "<input class='formfld unknown' size='10' type=\"text\" id=\"burst{$bwidx}\" name=\"burst{$bwidx}\" value=\"{$bw['burst']}\" />";
				$form .= "</td>";
				$form .= '<td class="col-xs-4">';
				$form .= "<select id=\"bwtype{$bwidx}\" name=\"bwtype{$bwidx}\" class=\"form-control\">";

				foreach (array("Kb" => "Kbit/s", "Mb" => "Mbit/s", "b" => "Bit/s") as $bwsidx => $bwscale) {
					$form .= "<option value=\"{$bwsidx}\"";

					if ($bw['bwscale'] == $bwsidx) {
						$form .= " selected";
					}

					$form .= ">{$bwscale}</option>";
				}

				$form .= "</select>";
				$form .= "</td>";
				$form .= '<td class="col-xs-4">';
				$form .= "<select id=\"bwsched{$bwidx}\" name=\"bwsched{$bwidx}\" class=\"form-control\">";

				foreach ($schedules as $schd) {
					$selected = "";
					if ($bw['bwsched'] == $schd) {
						$selected = "selected";
					}

					$form .= "<option value='{$schd}' {$selected}>{$schd}</option>";
				}

				$form .= "</select>";
				$form .= "</td>";
				$form .= '<td>';
				$form .= '<a class="btn btn-warning" onclick="removeBwRow(this); return false;"><i class="fa fa-trash icon-embed-btn"></i>' . gettext('Delete') . '</a>';
				$form .= "</td></tr>";
			}
		}
		$form .= "</tbody></table></div><br />";

		$form .= '<a class="btn btn-sm btn-success" onclick="javascript:addBwRowTo(\'maintable\'); return false;" >';
		$form .= '<i class="fa fa-plus icon-embed-btn"></i>';
		$form .= gettext("Add Schedule") . "</a>";

		return($form);
	}

	function build_form() {
		global $g, $config, $pipe, $action, $qname;

		//build list of schedules
		$schedules = array();
		$schedules[] = "none";//leave none to leave rule enabled all the time
		if (is_array($config['schedules']) && is_array($config['schedules']['schedule'])) {
			foreach ($config['schedules']['schedule'] as $schedule) {
				if ($schedule['name'] <> "") {
					$schedules[] = $schedule['name'];
				}
			}
		}


		$sform = new Form();
		$sform->setAction("firewall_shaper.php");

		$section = new Form_Section('Limiters');

		$section->addInput(new Form_Checkbox(
			'enabled',
			'Enable',
			'Enable limiter and its children',
			($this->GetEnabled() == "on"),
			'on'
		));

		$section->addInput(new Form_Input(
			'newname',
			'*Name',
			'text',
			$this->GetQname()
		));

		$section->addInput(new Form_Input(
			'name',
			null,
			'hidden',
			$this->GetQname()
		));

		if ($this->GetNumber() > 0) {
			$section->addInput(new Form_Input(
				'number',
				null,
				'hidden',
				$this->GetNumber()
			));
		}

		$bandwidth = $this->GetBandwidth();

		if (is_array($bandwidth)) {
				$section->addInput(new Form_StaticText(
				'Bandwidth',
				$this->build_bwtable()
			));
		}

		$mask = $this->GetMask();

		$section->addInput(new Form_Select(
			'mask',
			'Mask',
			$mask['type'],
			array('none' => gettext('None'), 'srcaddress' => gettext('Source addresses'), 'dstaddress' => gettext('Destination addresses'))
		))->setHelp('If "source" or "destination" slots is chosen a dynamic pipe with the bandwidth, delay, packet loss ' .
					'and queue size given above will be created for each source/destination IP address encountered, respectively. ' .
					'This makes it possible to easily specify bandwidth limits per host.');

		$group = new Form_Group(null);

		$group->add(new Form_Select(
			'maskbits',
			null,
			$mask['bits'],
			array_combine(range(32, 1, -1), range(32, 1, -1))
		))->setHelp('IPv4 mask bits%1$s%2$s', '<br />', '255.255.255.255/?');

		$group->add(new Form_Select(
			'maskbitsv6',
			null,
			$mask['bitsv6'],
			array_combine(range(128, 1, -1), range(128, 1, -1))
		))->setHelp('IPv6 mask bits%1$s%2$s', '<br />', '<span style="font-family:consolas">ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/?</span>');

		$section->add($group);

		$section->addInput(new Form_Input(
			'description',
			'Description',
			'text',
			$this->GetDescription()
		))->setHelp('A description may be entered here for administrative reference (not parsed).');

		$sform->add($section);

		/* Begin limiter patch */
		$aqm_map = getAQMs();
		$scheduler_map = getSchedulers();

		$section = new Form_Section('Queue');
		$section->addInput(new Form_Select(
				'aqm',
				'Queue Management Algorithm',
				$this->GetAQM(),
				array_map_assoc(function ($k, $v) {
					return [$k, $v["name"]];
				}, $aqm_map)
		))->setHelp('Active queue management (AQM) is the intelligent drop of network packets inside the limiter, ' .
						'when it becomes full or gets close to becoming full, with the goal of reducing ' .
						'network congestion.');

		$section->addInput(new Form_StaticText(
			'',
			build_queue_params($aqm_map, $this->GetAQM(), $this->GetAQMParameters(), "aqm")
		))->setHelp('Specifies the queue management algorithm parameters.');

		$section->addInput(new Form_Select(
				'sched',
				'Scheduler',
				$this->GetScheduler(),
				array_map_assoc(function ($k, $v) {
					return [$k, $v["name"]];
				}, $scheduler_map)
		))->setHelp('The scheduler manages the sequence of network packets in the limiter\'s queue.');

		$section->addInput(new Form_StaticText(
			'',
			build_queue_params($scheduler_map, $this->GetScheduler(), $this->GetSchedulerParameters(), "sched")
		))->setHelp('Specifies the scheduler parameters.');

		$section->addInput(new Form_Input(
				'qlimit',
				'Queue length',
				'number',
				$this->GetQlimit()
		))->setHelp('Specifies the length of the limiter\'s queue, which the scheduler and AQM are responsible for. ' .
			'This field may be left empty.');

		$section->addInput(new Form_Checkbox(
			'ecn',
			'ECN',
			'Enable Explicit Congestion Notification (ECN)',
			($this->GetECN() == "on"),
			'on'
		))->setHelp('ECN sets a reserved TCP flag when the queue is nearing or exceeding capacity. Not all AQMs or schedulers support this.');

		$sform->add($section);
		/* End limiter patch */

		$section = new Form_Section('Advanced Options');

		$section->addInput(new Form_Input(
			'delay',
			'Delay (ms)',
			'text',
			$this->GetDelay() > 0 ? $this->GetDelay():null
		))->setHelp('In most cases, zero (0) should specified here (or leave the field empty).');

		$section->addInput(new Form_Input(
			'plr',
			'Packet Loss Rate',
			'number',
			$this->GetPlr(),
			['step' => '0.001', 'min' => '0.000']
		))->setHelp('In most cases, zero (0) should be specified here (or leave the field empty). ' .
					'A value of 0.001 means one packet in 1000 gets dropped.');

		$section->addInput(new Form_Input(
			'buckets',
			'Bucket size (slots)',
			'number',
			$this->GetBuckets()
		))->setHelp('In most cases, this field should be left empty. It increases the hash size set.');

		$sform->add($section);

		return($sform);
		}

	function wconfig() {
		$cflink =& get_dn_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['name'] = $this->GetQname();
		$cflink['number'] = $this->GetNumber();
		$cflink['qlimit'] = $this->GetQlimit();
		$cflink['plr'] = $this->GetPlr();
		$cflink['description'] = $this->GetDescription();

		$bandwidth = $this->GetBandwidth();
		if (is_array($bandwidth)) {
			$cflink['bandwidth'] = array();
			$cflink['bandwidth']['item'] = array();
			foreach ($bandwidth as $bwidx => $bw) {
				$cflink['bandwidth']['item'][] = $bw;
			}
		}

		$cflink['enabled'] = $this->GetEnabled();
		$cflink['buckets'] = $this->GetBuckets();
		$mask = $this->GetMask();
		$cflink['mask'] = $mask['type'];
		$cflink['maskbits'] = $mask['bits'];
		$cflink['maskbitsv6'] = $mask['bitsv6'];
		$cflink['delay'] = $this->GetDelay();

		/* Limiter queue patch */
		$cflink['sched'] = $this->GetScheduler();
		$scheduler_map = getSchedulers();
		$selectedParameters = $scheduler_map[$this->getScheduler()]["parameters"];
		foreach ($selectedParameters as $key => $value) {
			$config_key = 'param_' . $this->GetScheduler() . '_' . $key;
			$cflink[$config_key] = $this->GetSchedulerParameter($key);
		}
		$cflink['aqm'] = $this->GetAQM();
		$aqm_map = GetAQMs();
		$selectedParameters = $aqm_map[$this->getAQM()]["parameters"];
		foreach ($selectedParameters as $key => $value) {
			$config_key = 'param_' . $this->GetAQM() . '_' . $key;
			$cflink[$config_key] = $this->GetAQMParameter($key);
		}
		$cflink['ecn'] = $this->GetECN();
		/* End limiter queue patch */
	}

}

class dnqueue_class extends dummynet_class {
	var $pipeparent;
	var $weight;
	/* Limiter queue patch */
    var $ecn; // ecn 'on' or 'off'
    var $aqm; // key to aqm_map
    var $aqm_params = array(); // AQM params
	function GetAQM() {
			return $this->aqm;
	}
	function SetAQM($aqm) {
			$this->aqm = $aqm;
	}
	function GetAQMParameters() {
			return $this->aqm_params;
	}
	function GetAQMParameter($parameter) {
			return $this->aqm_params[$parameter];
	}
	function SetAQMParameter($key, $value) {
			return $this->aqm_params[$key] = $value;
	}
	function SetAQMParameters($params) {
			$this->aqm_params = $params;
	}
	function GetECN() {
			return $this->ecn;
	}
	function SetECN($ecn) {
			$this->ecn = $ecn;
	}
	/* End limiter queue patch */

	function GetWeight() {
		return $this->weight;
	}
	function SetWeight($weight) {
		$this->weight = $weight;
	}
	function GetPipe() {
		return $this->pipeparent;
	}
	function SetPipe($pipe) {
		$this->pipeparent = $pipe;
	}

	/* Just a stub in case we ever try to call this from the frontend. */
	function &add_queue($interface, &$queue, &$path, &$input_errors) {
		return;
	}

	function delete_queue() {
		cleanup_dnqueue_from_rules($this->GetQname());
		unset_dn_object_by_reference($this->GetLink());
		@pfSense_ipfw_pipe("queue delete " . $this->GetNumber());
	}

	function validate_input($data, &$input_errors) {
		parent::validate_input($data, $input_errors);


		/* Limiter patch */
		$selectedAqm = getAQMs()[$data['aqm']];
		if (!empty($data['aqm']) && !$selectedAqm) {
			$input_errors[] = gettext("Selected AQM not recognized.");
		}
		if ($data['ecn'] && $data['ecn'] == 'on' && !$selectedAqm["ecn"]) {
			$input_errors[] = gettext("Explicit Congestion Notification is selected, but " . $selectedAqm["name"] . " does not support it.");
		}
		/* End limiter patch */

		if ($data['weight'] && ((!is_numeric($data['weight'])) ||
		    ($data['weight'] < 1 && $data['weight'] > 100))) {
			$input_errors[] = gettext("Weight must be an integer between 1 and 100.");
		}
	}

	/*
	 * Should search even its children
	 */
	function &find_queue($pipe, $qname) {
		if ($qname == $this->GetQname()) {
			return $this;
		} else {
			return NULL;
		}
	}

	function &find_parentqueue($pipe, $qname) {
		return $this->qparent;
	}

	function &get_queue_list(&$qlist) {
		if ($this->GetEnabled() == "") {
			return;
		}
		$qlist[$this->GetQname()] = "?" .$this->GetNumber();
	}

	function ReadConfig(&$q) {
		if (!empty($q['name']) && !empty($q['newname']) && $q['name'] != $q['newname']) {
			$this->SetQname($q['newname']);
		} else if (!empty($q['newname'])) {
			$this->SetQname($q['newname']);
		} else {
			$this->SetQname($q['name']);
		}
		$this->SetNumber($q['number']);
		if (isset($q['qlimit']) && $q['qlimit'] <> "") {
			$this->SetQlimit($q['qlimit']);
		} else {
			$this->SetQlimit("");
		}
		if (isset($q['mask']) && $q['mask'] <> "") {
			$masktype = $q['mask'];
		} else {
			$masktype = "";
		}
		if (isset($q['maskbits']) && $q['maskbits'] <> "") {
			$maskbits = $q['maskbits'];
		} else {
			$maskbits = "";
		}
		if (isset($q['maskbitsv6']) && $q['maskbitsv6'] <> "") {
			$maskbitsv6 = $q['maskbitsv6'];
		} else {
			$maskbitsv6 = "";
		}
		$this->SetMask(array("type" => $masktype, "bits" => $maskbits, "bitsv6" => $maskbitsv6));
		if (isset($q['buckets']) && $q['buckets'] <> "") {
			$this->SetBuckets($q['buckets']);
		} else {
			$this->SetBuckets("");
		}
		if (isset($q['plr']) && $q['plr'] <> "") {
			$this->SetPlr($q['plr']);
		} else {
			$this->SetPlr("");
		}
		if (isset($q['weight']) && $q['weight'] <> "") {
			$this->SetWeight($q['weight']);
		} else {
			$this->SetWeight("");
		}
		if (isset($q['description']) && $q['description'] <> "") {
			$this->SetDescription($q['description']);
		} else {
			$this->SetDescription("");
		}
		$this->SetEnabled($q['enabled']);

		/* Limiter patch */
		if (isset($q['aqm']) && $q['aqm'] <> "") {
				$this->SetAQM($q['aqm']);
		} else {
				$this->SetAQM('droptail');
		}
		// parse AQM arguments
		$aqm_map = getAQMs();
		$selectedParameters = $aqm_map[$this->getAQM()]["parameters"];
		foreach ($selectedParameters as $key => $value) {
			$config_key = 'param_' . $this->GetAQM() . '_' . $key;
			if (isset($q[$config_key]) && $q[$config_key] <> "") {
				$this->SetAQMParameter($key, $q[$config_key]);
			} else {
				$this->SetAQMParameter($key, $value["default"]);
			}
		}

		// ecn flag
		$this->SetECN($q['ecn']);
		/* End limiter patch */
	}

	function build_tree() {
		$parent =& $this->GetParent();
		$tree = " <li><a href=\"firewall_shaper_vinterface.php?pipe=" . htmlspecialchars($parent->GetQname()) ."&amp;queue=" . htmlspecialchars($this->GetQname()) ."&amp;action=show\">";
		$tree .= htmlspecialchars($this->GetQname()) . "</a>";
		$tree .= "</li>";

		return $tree;
	}

	function build_rules() {
		if ($this->GetEnabled() == "") {
			return;
		}

		$parent =& $this->GetParent();
		$pfq_rule = "queue ". $this->GetNumber() . " config pipe " . $parent->GetNumber();
		if ($this->GetQlimit()) {
			$pfq_rule .= " queue " . $this->GetQlimit();
		}
		if ($this->GetWeight()) {
			$pfq_rule .= " weight " . $this->GetWeight();
		}
		if ($this->GetBuckets()) {
			$pfq_rule .= " buckets " . $this->GetBuckets();
		}
		$this->build_mask_rules($pfq_rule);

		/* Limiter patch */
		$selectedAQM = getAQMs()[$this->getAQM()];
		if ($selectedAQM) {
			$pfq_rule .= " " . FormatParameters($selectedAQM["parameter_format"], $this->GetAQMParameters());
			if ($selectedAQM["ecn"]) {
				if ($this->getECN() == 'on') {
					$pfq_rule .= ' ecn';
				} else {
					$pfq_rule .= ' noecn';
				}
			}
		}
		/* End patch */

		$pfq_rule .= "\n";

		return $pfq_rule;
	}

	function build_javascript() {
		return parent::build_javascript();
	}

	function build_form() {
		global $g, $config, $pipe, $action, $qname;

		//build list of schedules
		$schedules = array();
		$schedules[] = "none";//leave none to leave rule enabled all the time
		if (is_array($config['schedules']) && is_array($config['schedules']['schedule'])) {
			foreach ($config['schedules']['schedule'] as $schedule) {
				if ($schedule['name'] <> "") {
					$schedules[] = $schedule['name'];
				}
			}
		}


		$sform = new Form();
		$sform->setAction("firewall_shaper.php");
		$section = new Form_Section('Limiters');

		$section->addInput(new Form_Checkbox(
			'enabled',
			'Enable',
			'Enable this queue',
			($this->GetEnabled() == "on"),
			'on'
		));

		$section->addInput(new Form_Input(
			'newname',
			'*Name',
			'text',
			$this->GetQname()
		));

		$section->addInput(new Form_Input(
			'name',
			null,
			'hidden',
			$this->GetQname()
		));

		if ($this->GetNumber() > 0) {
			$section->addInput(new Form_Input(
				'number',
				null,
				'hidden',
				$this->GetNumber()
			));
		}

		$mask = $this->GetMask();

		$section->addInput(new Form_Select(
			'mask',
			'Mask',
			$mask['type'],
			array('none' => gettext('None'), 'srcaddress' => gettext('Source addresses'), 'dstaddress' => gettext('Destination addresses'))
		))->setHelp('If "source" or "destination" slots is chosen a dynamic pipe with the bandwidth, delay, packet loss ' .
					'and queue size given above will be created for each source/destination IP address encountered, respectively. ' .
					'This makes it possible to easily specify bandwidth limits per host.');

		$group = new Form_Group(null);

		$group->add(new Form_Select(
			'maskbits',
			null,
			$mask['bits'],
			array_combine(range(32, 1, -1), range(32, 1, -1))
		))->setHelp('IPv4 mask bits%1$s%2$s', '<br />', '255.255.255.255/?');

		$group->add(new Form_Select(
			'maskbitsv6',
			null,
			$mask['bitsv6'],
			array_combine(range(128, 1, -1), range(128, 1, -1))
		))->setHelp('IPv6 mask bits%1$s%2$s', '<br />', '<span style="font-family:consolas">ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/?</span>');

		$section->add($group);

		$section->addInput(new Form_Input(
			'description',
			'Description',
			'text',
			$this->GetDescription()
		))->setHelp('A description may be entered here for administrative reference (not parsed).');

		$sform->add($section);

		/* Begin limiter patch */
		$aqm_map = getAQMs();

		$section = new Form_Section('Queue');
		$section->addInput(new Form_Select(
				'aqm',
				'Queue Management Algorithm',
				$this->GetAQM(),
				array_map_assoc(function ($k, $v) {
					return [$k, $v["name"]];
				}, $aqm_map)
		))->setHelp('Active queue management (AQM) is the intelligent drop of network packets inside this limiter\'s queue, ' .
						'when it becomes full or gets close to becoming full, with the goal of reducing ' .
						'network congestion.');

		$section->addInput(new Form_StaticText(
			'',
			build_queue_params($aqm_map, $this->GetAQM(), $this->GetAQMParameters(), "aqm")
		))->setHelp('Specifies the queue management algorithm parameters.');

		$section->addInput(new Form_Input(
				'qlimit',
				'Queue length',
				'number',
				$this->GetQlimit()
		))->setHelp('Specifies the length of this queue, which the AQM is responsible for.  This field may be left empty.');

		$section->addInput(new Form_Checkbox(
			'ecn',
			'ECN',
			'Enable Explicit Congestion Notification (ECN)',
			($this->GetECN() == "on"),
			'on'
		))->setHelp('ECN sets a reserved TCP flag when the queue is nearing or exceeding capacity. Not all AQMs or schedulers support this.');

		$sform->add($section);
		/* End limiter patch */

		$section = new Form_Section('Advanced Options');

		$section->addInput(new Form_Input(
			'weight',
			'Weight',
			'number',
			$this->GetWeight(),
			['min' => '1', 'max' => '100']
		))->setHelp('For queues under the same parent this specifies the share that a queue gets(values range from 1 to 100),' .
					' it can be left blank otherwise.');

		$section->addInput(new Form_Input(
			'plr',
			'Packet Loss Rate',
			'number',
			$this->GetPlr(),
			['step' => '0.001', 'min' => '0.000']
		))->setHelp('In most cases, zero (0) should be specified here (or leave the field empty). ' .
					'A value of 0.001 means one packet in 1000 gets dropped');

		$section->addInput(new Form_Input(
			'buckets',
			'Bucket size (slots)',
			'number',
			$this->GetBuckets()
		))->setHelp('In most cases, this field should be left empty. It increases the hash size set');

		$section->addInput(new Form_Input(
			'pipe',
			null,
			'hidden',
			$this->GetPipe()
		));

		$sform->add($section);

		return($sform);
	}

	function update_dn_data(&$data) {
		$this->ReadConfig($data);
	}

	function wconfig() {
		$cflink =& get_dn_reference_to_me_in_config($this->GetLink());
		if (!is_array($cflink)) {
			$cflink = array();
		}
		$cflink['name'] = $this->GetQname();
		$cflink['number'] = $this->GetNumber();
		$cflink['qlimit'] = $this->GetQlimit();
		$cflink['description'] = $this->GetDescription();
		$cflink['weight'] = $this->GetWeight();
		$cflink['enabled'] = $this->GetEnabled();
		$cflink['buckets'] = $this->GetBuckets();
		$mask = $this->GetMask();
		$cflink['mask'] = $mask['type'];
		$cflink['maskbits'] = $mask['bits'];
		$cflink['maskbitsv6'] = $mask['bitsv6'];

		/* Limiter queue patch */
		$cflink['aqm'] = $this->GetAQM();
		$aqm_map = GetAQMs();
		$selectedParameters = $aqm_map[$this->getAQM()]["parameters"];
		foreach ($selectedParameters as $key => $value) {
			$config_key = 'param_' . $this->GetAQM() . '_' . $key;
			$cflink[$config_key] = $this->GetAQMParameter($key);
		}
		$cflink['ecn'] = $this->GetECN();
		/* End limiter queue patch */
	}
}

function get_dummynet_name_list() {

	$dn_name_list =& get_unique_dnqueue_list();
	$dn_name = array();
	if (is_array($dn_name_list)) {
		foreach ($dn_name_list as $key => $value) {
			$dn_name[] = $key;
		}
	}

	return $dn_name;

}

function get_altq_name_list() {
	$altq_name_list =& get_unique_queue_list();
	$altq_name = array();
	if (is_array($altq_name_list)) {
		foreach ($altq_name_list as $key => $aqobj) {
			$altq_name[] = $key;
		}
	}

	return $altq_name;
}

/*
 * XXX: TODO Make a class shaper to hide all these functions
 * from the global namespace.
 */

/*
 * This is a layer violation but for now there is no way
 * I can find to properly do this with PHP.
 */
function altq_get_default_queue($interface) {
	global $altq_list_queues;

	$altq_tmp = $altq_list_queues[$interface];
	if ($altq_tmp) {
		return $altq_tmp->GetDefaultQueuePresent();
	} else {
		return false;
	}
}

function altq_check_default_queues() {
	global $altq_list_queues;

	$count = 0;
	if (is_array($altq_list_queues)) {
		foreach ($altq_list_queues as $altq) {
			if ($altq->GetDefaultQueuePresent()) {
				$count++;
			}
		}
	}
	else {
		$count++;
	}

	return 0;
}

function &get_unique_queue_list() {
	global $altq_list_queues;

	$qlist = array();
	if (is_array($altq_list_queues)) {
		foreach ($altq_list_queues as $altq) {
			if ($altq->GetEnabled() == "") {
				continue;
			}
			$tmplist =& $altq->get_queue_list();
			foreach ($tmplist as $qname => $link) {
				if ($link->GetEnabled() <> "") {
					$qlist[$qname] = $link;
				}
			}
		}
	}
	return $qlist;
}

function &get_unique_dnqueue_list() {
	global $dummynet_pipe_list;

	$qlist = array();
	if (is_array($dummynet_pipe_list)) {
		foreach ($dummynet_pipe_list as $dn) {
			if ($dn->GetEnabled() == "") {
				continue;
			}
			$tmplist =& $dn->get_queue_list();
			foreach ($tmplist as $qname => $link) {
				$qlist[$qname] = $link;
			}
		}
	}
	return $qlist;
}

function ref_on_altq_queue_list($parent, $qname) {
	if (isset($GLOBALS['queue_list'][$qname])) {
		$GLOBALS['queue_list'][$qname]++;
	} else {
		$GLOBALS['queue_list'][$qname] = 1;
	}

	unref_on_altq_queue_list($parent);
}

function unref_on_altq_queue_list($qname) {
	$GLOBALS['queue_list'][$qname]--;
	if ($GLOBALS['queue_list'][$qname] <= 1) {
		unset($GLOBALS['queue_list'][$qname]);
	}
}

function read_altq_config() {
	global $altq_list_queues, $config;
	$path = array();

	init_config_arr(array('shaper', 'queue'));
	$a_int = &$config['shaper']['queue'];

	$altq_list_queues = array();

	if (!is_array($config['shaper']['queue'])) {
		return;
	}

	foreach ($a_int as $key => $conf) {
		$int = $conf['interface'];
		$root = new altq_root_queue();
		$root->SetInterface($int);
		$altq_list_queues[$root->GetInterface()] = $root;
		$root->ReadConfig($conf);
		array_push($path, $key);
		$root->SetLink($path);
		if (is_array($conf['queue'])) {
			foreach ($conf['queue'] as $key1 => $q) {
				array_push($path, $key1);
				/*
				 * XXX: we completely ignore errors here but anyway we must have
				 *	checked them before so no harm should be come from this.
				 */
				$root->add_queue($root->GetInterface(), $q, $path, $input_errors);
				array_pop($path);
			}
		}
		array_pop($path);
	}
}

function read_dummynet_config() {
	global $dummynet_pipe_list, $config;
	$path = array();

	init_config_arr(array('dnshaper', 'queue'));
	$a_int = &$config['dnshaper']['queue'];

	$dummynet_pipe_list = array();

	if (!count($config['dnshaper']['queue'])) {
		return;
	}

	foreach ($a_int as $key => $conf) {
		if (empty($conf['name'])) {
			continue; /* XXX: grrrrrr at php */
		}
		$root = new dnpipe_class();
		$root->ReadConfig($conf);
		$dummynet_pipe_list[$root->GetQname()] = $root;
		array_push($path, $key);
		$root->SetLink($path);
		if (is_array($conf['queue'])) {
			foreach ($conf['queue'] as $key1 => $q) {
				array_push($path, $key1);
				/*
				 * XXX: we completely ignore errors here but anyway we must have
				 *	checked them before so no harm should be come from this.
				 */
				$root->add_queue($root->GetQname(), $q, $path, $input_errors);
				array_pop($path);
			}
		}
		array_pop($path);
	}
}

function get_interface_list_to_show() {
	global $altq_list_queues, $config;
	global $shaperIFlist;

	$tree = "";
	foreach ($shaperIFlist as $shif => $shDescr) {
		if ($altq_list_queues[$shif]) {
			continue;
		} else {
			if (!is_altq_capable(get_real_interface($shif))) {
				continue;
			}
			$tree .= " <li><a href=\"firewall_shaper.php?interface=".$shif."&amp;action=add\">".$shDescr."</a></li>";
		}
	}

	return $tree;
}

function filter_generate_altq_queues() {
	global $altq_list_queues;

	read_altq_config();

	$altq_rules = "";
	foreach ($altq_list_queues as $altq) {
		$altq_rules .= $altq->build_rules();
	}

	return $altq_rules;
}

function dnqueue_find_nextnumber() {
	global $dummynet_pipe_list;

	$dnused = array();
	if (is_array($dummynet_pipe_list)) {
		foreach ($dummynet_pipe_list as $dn) {
			$tmplist =& $dn->get_queue_list();
			foreach ($tmplist as $qname => $link) {
				if ($link[0] == "?") {
					$dnused[$qname] = substr($link, 1);
				}
			}
		}
	}

	sort($dnused, SORT_NUMERIC);
	$dnnumber = 0;
	$found = false;
	foreach ($dnused as $dnnum) {
		if (($dnnum - $dnnumber) > 1) {
			$dnnumber = $dnnum - 1;
			$found = true;
			break;
		} else {
			$dnnumber = $dnnum;
		}
	}

	if ($found == false) {
		$dnnumber++;
	}

	unset($dnused, $dnnum, $found);
	return $dnnumber;
}

function dnpipe_find_nextnumber() {
	global $dummynet_pipe_list;

	$dnused = array();
	foreach ($dummynet_pipe_list as $dn) {
		$dnused[] = $dn->GetNumber();
	}

	sort($dnused, SORT_NUMERIC);
	$dnnumber = 0;
	$found = false;
	foreach ($dnused as $dnnum) {
		if (($dnnum - $dnnumber) > 1) {
			$dnnumber = $dnnum - 1;
			$found = true;
			break;
		} else {
			$dnnumber = $dnnum;
		}
	}

	if ($found == false) {
		$dnnumber++;
	}

	unset($dnused, $dnnum, $found);
	return $dnnumber;
}

function filter_generate_dummynet_rules() {
	global $g, $dummynet_pipe_list;

	read_dummynet_config();

	$dn_rules = "";
	$max_qlimit = "100"; // OS default
	foreach ($dummynet_pipe_list as $dn) {
		$dn_rules .= $dn->build_rules();
		$this_qlimit = $dn->GetQlimit();
		if ($this_qlimit > $max_qlimit) {
			$max_qlimit = $this_qlimit;
		}
	}
	if (!is_numericint($max_qlimit)) {
		$max_qlimit = "100";
	}
	if (!empty($dn_rules)) {
		if (!is_module_loaded("dummynet.ko")) {
			mwexec("/sbin/kldload dummynet");
		}
		set_sysctl(array(
				"net.inet.ip.dummynet.io_fast" => "1",
				"net.inet.ip.dummynet.hash_size" => "256",
				"net.inet.ip.dummynet.pipe_slot_limit" => $max_qlimit
		));
		file_put_contents("{$g['tmp_path']}/rules.limiter", $dn_rules);
		mwexec("/sbin/ipfw {$g['tmp_path']}/rules.limiter");
	}
}

function build_iface_without_this_queue($iface, $qname) {
	global $g, $altq_list_queues;
	global $shaperIFlist;

	$altq =& $altq_list_queues[$iface];

	if ($altq) {
		$scheduler = $altq->GetScheduler();
	}

	$form = '<dl class="dl-horizontal">';

	$form .= '	<dt>';
	$form .= '		<a href="firewall_shaper.php?interface=' . $iface . '&amp;queue=' . $iface . '&amp;action=show">' . $shaperIFlist[$iface] . '</a>';
	$form .= '	</dt>';
	$form .= '	<dd>';
	$form .=		$scheduler;
	$form .= '	</dd>';

	$form .= '	<dt>';
	$form .= 'Clone';
	$form .= '	</dt>';
	$form .= '	<dd>';
	$form .= '<a class="btn btn-info btn-xs" href="firewall_shaper_queues.php?interface=';
	$form .= $iface . '&amp;queue=';
	$form .= $qname . '&amp;action=add">';
	$form .= '<i class="fa fa-clone icon-embed-btn"></i>';
	$form .= gettext("Clone Shaper to this Interface") . '</a>';
	$form .= '	</dd>';

	$form .= '</dl>';

	return $form;

}

$default_shaper_msg = sprintf(gettext("Welcome to the %s Traffic Shaper."), $g['product_name']) . "<br />";
$dn_default_shaper_msg = $default_shaper_msg;

$shaper_msg = gettext("The tree on the left navigates through the %s.");
$default_shaper_msg .= sprintf($shaper_msg, gettext("queues")) . "<br />";
$dn_default_shaper_msg .= sprintf($shaper_msg, gettext("limiters")) . "<br />";

$shaper_msg = gettext("Buttons at the bottom represent %s actions and are activated accordingly.");
$default_shaper_msg .= sprintf($shaper_msg, gettext("queue"));
$dn_default_shaper_msg .= sprintf($shaper_msg, gettext("limiter"));

// Check to see if the specified interface has a queue configured
function interface_has_queue($if) {
	global $config;

	if ($config['shaper']) {
		foreach ($config['shaper']['queue'] as $queue) {
			if ($queue['interface'] === $if) {
				return true;
			}
		}
	}

	return false;
}
?>
