<?php
/* $Id$ */
/*
	filter_log.inc
	part of pfSesne by Scott Ullrich
	originally based on m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2009 Jim Pingle <myfirstname>@<mylastname>.org
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
/*
	pfSense_BUILDER_BINARIES:	/usr/sbin/fifolog_reader	/usr/bin/tail	/usr/local/sbin/clog
	pfSense_MODULE:	filter
*/

require 'config.inc';

/* format filter logs */
function conv_log_filter($logfile, $nentries, $tail = 50, $filtertext = "", $filterinterface = null) {
	global $config, $g;

	/* Make sure this is a number before using it in a system call */
	if (!(is_numeric($tail)))
		return;

	if ($filtertext)
		$tail = 5000;

	/* FreeBSD 8 splits pf log lines into two lines, so we need to at least
	 * tail twice as many, plus some extra to account for unparseable lines */
	$tail = $tail * 2 + 50;

	/* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
	$logarr = "";

	if(isset($config['system']['usefifolog']))
		exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . " | /usr/bin/tail -r -n {$tail}", $logarr);
	else
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail -r -n {$tail}", $logarr);

	$filterlog = array();
	$counter = 0;

	$logarr = array_reverse(collapse_filter_lines(array_reverse($logarr)));
	$filterinterface = strtoupper($filterinterface);
	foreach ($logarr as $logent) {
		if($counter >= $nentries)
			break;

		$flent = parse_filter_line($logent);
		if (!$filterinterface || ($filterinterface == $flent['interface']))
		{
			if ( ( ($flent != "") && (!is_array($filtertext)) && (match_filter_line ($flent, $filtertext))) || 
			     ( ($flent != "") && ( is_array($filtertext)) && (match_filter_field($flent, $filtertext)) ) ) {
				$counter++;
				$filterlog[] = $flent;
			}
		}
	}
	/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
	return isset($config['syslog']['reverse']) ? $filterlog : array_reverse($filterlog);
}

function match_filter_line($flent, $filtertext = "") {
	if (!$filtertext)
		return true;
	$filtertext = str_replace(' ', '\s+', $filtertext);
	return preg_match("/{$filtertext}/i", implode(" ", array_values($flent)));
}

function match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == "All")
			continue;
		if ((strpos($$field, '!') === 0)) {
			$field = substr($field, 1);
			if (strtolower($key) == 'act') {
				if (in_arrayi($flent[$key], explode(" ", $field)))
					return false;
			} else {
				if (@preg_match("/{$field}/i", $flent[$key]))
					return false;
			}
		} else {
			if (strtolower($key) == 'act') {
				if (!in_arrayi($flent[$key], explode(" ", $field)))
					return false;
			} else {
				if (!@preg_match("/{$field}/i", $flent[$key]))
					return false;
			}
		}
	}
	return true;
}

// Case Insensitive in_array function
function in_arrayi($needle, $haystack) {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function collapse_filter_lines($logarr) {
	$lastline = "";
	$collapsed = array();
	/* Stick a blank entry at the end to be sure we always fully parse the last entry */
	$logarr[] = "";
	foreach ($logarr as $logent) {
		$line_split = "";
		preg_match("/.*\spf:\s(.*)/", $logent, $line_split);
		if (substr($line_split[1], 0, 4) != "    ") {
			if (($lastline != "") && (substr($lastline, 0, 1) != " ")) {
				$collapsed[] = $lastline;
			}
			$lastline = $logent;
		} else {
			$lastline .= substr($line_split[1], 3);
		}
	}
	//print_r($collapsed);
	return $collapsed;
}

function parse_filter_line($line) {
	global $config, $g;
	$log_split = "";
	preg_match("/(.*)\s(.*)\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s(\w+)\son\s(\w+)\:\s\((.*)\)\s(.*)\s>\s(.*)\:\s(.*)/", $line, $log_split);

	list($all, $flent['time'], $host, $rule, $flent['act'], $flent['direction'], $flent['realint'], $details, $src, $dst, $leftovers) = $log_split;

	list($flent['srcip'], $flent['srcport']) = parse_ipport($src);
	list($flent['dstip'], $flent['dstport']) = parse_ipport($dst);

	$flent['src'] = $flent['srcip'];
	$flent['dst'] = $flent['dstip'];

	if ($flent['srcport'])
		$flent['src'] .= ':' . $flent['srcport'];
	if ($flent['dstport'])
		$flent['dst'] .= ':' . $flent['dstport'];

	$flent['interface']  = convert_real_interface_to_friendly_descr($flent['realint']);

	$tmp = explode("/", $rule);
	$flent['rulenum'] = $tmp[0];

	$proto = array(" ", "(?)");
	/* Attempt to determine the protocol, based on several possible patterns.
	 * The value returned by strpos() must be strictly checkeded against the
	 * boolean FALSE because it could return a valid answer of 0 upon success. */
	if (!(strpos($details, 'proto ') === FALSE)) {
		preg_match("/.*\sproto\s(.*)\s\(/", $details, $proto);
	} elseif (!(strpos($details, 'next-header ') === FALSE)) {
		preg_match("/.*\snext-header\s(.*)\s\(/", $details, $proto);
	} elseif (!(strpos($details, 'proto: ') === FALSE)) {
		preg_match("/.*\sproto\:(.*)\s\(/", $details, $proto);
	} elseif (!(strpos($leftovers, 'sum ok] ') === FALSE)) {
		preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $leftovers, $proto);
	} elseif (!(strpos($line, 'sum ok] ') === FALSE)) {
		preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $line, $proto);
	}
	$proto = explode(" ", trim($proto[1]));
	$flent['proto'] = rtrim($proto[0], ",");

	/* If we're dealing with TCP, try to determine the flags/control bits */
	$flent['tcpflags'] = "";
	if ($flent['proto'] == "TCP") {
		$flags = preg_split('/[, ]/', $leftovers);
		$flent['tcpflags'] = str_replace(".", "A", substr($flags[1], 1, -1));
	} elseif ($flent['proto'] == "Options") {
		/* Then there must be some info we missed */
		if (!(strpos($leftovers, 'sum ok] ') === FALSE)) {
			preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $leftovers, $proto);
		} elseif (!(strpos($line, 'sum ok] ') === FALSE)) {
			preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $line, $proto);
		}
		$proto = explode(" ", trim($proto[1]));
		$flent['proto'] = rtrim($proto[0], ",");
		/* If it's still 'Options', then just ignore it. */
		if ($flent['proto'] == "Options")
			$flent['proto'] = "none";
	} elseif (($flent['proto'] == "unknown") && (!(strpos($line, ':  pfsync') === FALSE))) {
		$flent['proto'] = "PFSYNC";
	}

	/* If there is a src, a dst, and a time, then the line should be usable/good */
	if (!((trim($flent['src']) == "") || (trim($flent['dst']) == "") || (trim($flent['time']) == ""))) {
		return $flent;
	} else {
		if($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing rule: %s.   Please report to mailing list or forum."), $errline));
		}
		return "";
	}
}

function parse_ipport($addr) {
	$addr = trim(rtrim($addr, ":"));
	if (substr($addr, 0, 4) == "kip ")
		$addr = substr($addr, 4);
	$port = '';
	if (substr_count($addr, '.') > 1) {
		/* IPv4 */
		$addr_split = explode(".", $addr);
		$ip = "{$addr_split[0]}.{$addr_split[1]}.{$addr_split[2]}.{$addr_split[3]}";

		if ($ip == "...")
			return array($addr, '');

		if($addr_split[4] != "") {
			$port_split = explode(":", $addr_split[4]);
			$port = $port_split[0];
		}
	} else {
		/* IPv6 */
		$addr = explode(" ", $addr);
		$addr = rtrim($addr[0], ":");
		$addr_split = explode(".", $addr);
		if (count($addr_split) > 1) {
			$ip   = $addr_split[0];
			$port = $addr_split[1];
		} else {
			$ip   = $addr;
		}
	}

	return array($ip, $port);
}

function get_port_with_service($port, $proto) {
	if (!$port)
		return '';

	$service = getservbyport($port, $proto);
	$portstr = "";
	if ($service) {
		$portstr = sprintf('<span title="' . gettext('Service %1$s/%2$s: %3$s') . '">' . htmlspecialchars($port) . '</span>', $port, $proto, $service);
	} else {
		$portstr = htmlspecialchars($port);
	}
	return ':' . $portstr;
}

function find_rule_by_number($rulenum, $type="rules") {
	/* Passing arbitrary input to grep could be a Very Bad Thing(tm) */
	if (!(is_numeric($rulenum)))
		return;
	/* At the moment, miniupnpd is the only thing I know of that
	   generates logging rdr rules */
	if ($type == "rdr")
		return `pfctl -vvsn -a "miniupnpd" | grep '^@{$rulenum} '`;
	else
		return `pfctl -vvsr | grep '^@{$rulenum} '`;
}

function buffer_rules_load() {
    global $buffer_rules_rdr, $buffer_rules_normal;
	$buffer = explode("\n",`pfctl -vvsn -a "miniupnpd" | grep '^@'`);
	foreach ($buffer as $line) {
		list($key, $value) = explode (" ", $line, 2);
		$buffer_rules_rdr[$key] = $value;
	}	
	$buffer = explode("\n",`pfctl -vvsr | grep '^@'`);
	foreach ($buffer as $line) {
		list($key, $value) = explode (" ", $line, 2);
		$buffer_rules_normal[$key] = $value;
	}	
}

function buffer_rules_clear() {
	unset($GLOBALS['buffer_rules_normal']);
	unset($GLOBALS['buffer_rules_rdr']);
}

function find_rule_by_number_buffer($rulenum, $type){
    global $g, $buffer_rules_rdr, $buffer_rules_normal;
	
	if ($type == "rdr")	{
		$ruleString = $buffer_rules_rdr["@".$rulenum];
		//TODO: get the correct 'description' part of a RDR log line. currently just first 30 characters..
		$rulename = substr($ruleString,0,30);
	} else {
		$ruleString = $buffer_rules_normal["@".$rulenum];
		list(,$rulename,) = explode("\"",$ruleString);
		$rulename = str_replace("USER_RULE: ",'<img src="/themes/'.$g['theme'].'/images/icons/icon_frmfld_user.png" width="11" height="12" title="USER_RULE" alt="USER_RULE"/> ',htmlspecialchars($rulename));
	}
	return $rulename." (@".$rulenum.")";
}

function find_action_image($action) {
	global $g;
	if ((strstr(strtolower($action), "p")) || (strtolower($action) == "rdr"))
		return "/themes/{$g['theme']}/images/icons/icon_pass.gif";
	else if(strstr(strtolower($action), "r"))
		return "/themes/{$g['theme']}/images/icons/icon_reject.gif";
	else
		return "/themes/{$g['theme']}/images/icons/icon_block.gif";
}

function is_first_row($rownum, $totalrows) {
	global $config;
	if(isset($config['syslog']['reverse'])) {
		/* Honor reverse logging setting */
		if($rownum == 0)
			return " id=\"firstrow\"";
	} else {
		/* non-reverse logging */
		if($rownum == $totalrows - 1)
			return " id=\"firstrow\"";
	}
	return "";
}

/* AJAX specific handlers */
function handle_ajax($nentries, $tail = 50) {
	global $config;
	if($_GET['lastsawtime'] or $_POST['lastsawtime']) {
		global $filter_logfile,$filterent;
		if($_GET['lastsawtime'])
			$lastsawtime = $_GET['lastsawtime'];
		if($_POST['lastsawtime'])
			$lastsawtime = $_POST['lastsawtime'];
		/*  compare lastsawrule's time stamp to filter logs.
		 *  afterwards return the newer records so that client
                 *  can update AJAX interface screen.
		 */
		$new_rules = "";
		$filterlog = conv_log_filter($filter_logfile, $nentries, $tail);
		/* We need this to always be in forward order for the AJAX update to work properly */
		$filterlog = isset($config['syslog']['reverse']) ? array_reverse($filterlog) : $filterlog;
		foreach($filterlog as $log_row) {
			$row_time = strtotime($log_row['time']);
			$img = "<img border='0' src='" . find_action_image($log_row['act']) . "' alt={$log_row['act']} title={$log_row['act']} />";
			if($row_time > $lastsawtime) {
				if ($log_row['proto'] == "TCP")
					$log_row['proto'] .= ":{$log_row['tcpflags']}";

				$img = "<a href=\"#\" onClick=\"javascript:getURL('diag_logs_filter.php?getrulenum={$log_row['rulenum']},{$log_row['rulenum']}', outputrule);\">{$img}</a>";
				$new_rules .= "{$img}||{$log_row['time']}||{$log_row['interface']}||{$log_row['srcip']}||{$log_row['dst']}||{$log_row['proto']}||" . time() . "||\n";
			}
		}
		echo $new_rules;
		exit;
	}
}

?>
