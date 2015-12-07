<?php
/*
	filter_log.inc
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_BUILDER_BINARIES:	/usr/sbin/fifolog_reader	/usr/bin/tail	/usr/local/sbin/clog
	pfSense_MODULE: filter
*/

require 'config.inc';

global $buffer_rules_rdr, $buffer_rules_normal;
$buffer_rules_rdr = array();
$buffer_rules_normal = array();

/* format filter logs */
function conv_log_filter($logfile, $nentries, $tail = 50, $filtertext = "", $filterinterface = null) {
	global $config, $g;

	/* Make sure this is a number before using it in a system call */
	if (!(is_numeric($tail))) {
		return;
	}

	if ($filtertext) {
		$tail = 5000;
	}

	/* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
	$logarr = "";

	     if ($logfile == "{$g['varlog_path']}/system.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/gateways.log")	{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/routing.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/resolver.log")	{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/wireless.log")	{ $logfile_type = "system"; }

	else if ($logfile == "{$g['varlog_path']}/filter.log")		{ $logfile_type = "firewall"; }
	else if ($logfile == "{$g['varlog_path']}/dhcpd.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/portalauth.log")	{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/ipsec.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/ppp.log")			{ $logfile_type = "system"; }

	else if ($logfile == "{$g['varlog_path']}/vpn.log")			{ $logfile_type = "tbd"; }
	else if ($logfile == "{$g['varlog_path']}/pptps.log")		{ $logfile_type = "tbd"; }
	else if ($logfile == "{$g['varlog_path']}/poes.log")		{ $logfile_type = "tbd"; }
	else if ($logfile == "{$g['varlog_path']}/l2tps.log")		{ $logfile_type = "tbd"; }

	else if ($logfile == "{$g['varlog_path']}/relayd.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/openvpn.log")		{ $logfile_type = "system"; }
	else if ($logfile == "{$g['varlog_path']}/ntpd.log")		{ $logfile_type = "system"; }

	else 														{ $logfile_type = "unknown"; }

	     if ($logfile_type == 'firewall')	{
		$pattern = "filterlog:";
	}
	else if ($logfile_type == 'system')		{

		$month_pattern = "[a-zA-Z]{3}";
		$day_pattern = "[0-9]{1,2}";
		$time_pattern = "[0-9]{2}:[0-9]{2}:[0-9]{2}";

		$date_pattern = "\(" . $month_pattern . "\ +" . $day_pattern . "\ +" . $time_pattern . "\)";

		$process_pattern = "\(.*?\)";
		$pid_pattern = "\(.*?\)\(?::\ +\)?\(?:\[[0-9:]*\]\)?:?";
		$log_message_pattern = "\(.*\)";

		$pattern = "^" . $date_pattern . "\ +" . $process_pattern . "\ +" . $pid_pattern . "\ +" . $log_message_pattern . "$";
	}
	else if ($logfile_type == 'tbd')		{
		$pattern = "^\(.*\)$";
	}
	else if ($logfile_type == 'unknown')	{
		$pattern = "^\(.*\)$";
	}
	else									{
		$pattern = "^\(.*\)$";
	}

	if (isset($config['system']['usefifolog'])) {
		exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . " | /usr/bin/grep -E $pattern | /usr/bin/tail -r -n {$tail}", $logarr);
	} else {
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | /usr/bin/grep -v \"CLOG\" | /usr/bin/grep -v \"\033\" | /usr/bin/grep -E $pattern | /usr/bin/tail -r -n {$tail}", $logarr);
	}

	$filterlog = array();
	$counter = 0;

	$filterinterface = strtoupper($filterinterface);
	foreach ($logarr as $logent) {
		if ($counter >= $nentries) {
			break;
		}

		     if ($logfile_type == 'firewall')	{ $flent = parse_firewall_log_line($logent); }
		else if ($logfile_type == 'system')		{ $flent = parse_system_log_line($logent); }
		else if ($logfile_type == 'tbd')		{ $flent = array(); }
		else if ($logfile_type == 'unknown')	{ $flent = parse_unknown_log_line($logent); }
		else 									{ $flent = array(); }

		if (!$filterinterface || ($filterinterface == $flent['interface'])) {
			if ((($flent != "") && (!is_array($filtertext)) && (match_filter_line($flent, $filtertext))) ||
			    (($flent != "") && (is_array($filtertext)) && (match_filter_field($flent, $filtertext)))) {
				$counter++;
				$filterlog[] = $flent;
			}
		}
	}
	/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
	# First get the "General Logging Options" (global) chronological order setting.  Then apply specific log override if set.
	$reverse = isset($config['syslog']['reverse']);
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $reverse = false;
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $reverse = true;

	return ($reverse) ? $filterlog : array_reverse($filterlog);
}

function escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".    */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function match_filter_line($flent, $filtertext = "") {
	if (!$filtertext) {
		return true;
	}
	$filtertext = escape_filter_regex(str_replace(' ', '\s+', $filtertext));
	return @preg_match("/{$filtertext}/i", implode(" ", array_values($flent)));
}

function match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == "All") {
			continue;
		}
		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			if (strtolower($key) == 'act') {
				if (in_arrayi($flent[$key], explode(" ", $field))) {
					return false;
				}
			} else {
				$field_regex = escape_filter_regex($field);
				if (@preg_match("/{$field_regex}/i", $flent[$key])) {
					return false;
				}
			}
		} else {
			if (strtolower($key) == 'act') {
				if (!in_arrayi($flent[$key], explode(" ", $field))) {
					return false;
				}
			} else {
				$field_regex = escape_filter_regex($field);
				if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
					return false;
				}
			}
		}
	}
	return true;
}

// Case Insensitive in_array function
function in_arrayi($needle, $haystack) {
	return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function parse_unknown_log_line($line) {
	global $config, $g;

	$flent = array();
	$log_split = "";

	$month_pattern = "[a-zA-Z]{3}";
	$day_pattern = "[0-9]{1,2}";
	$time_pattern = "[0-9]{2}:[0-9]{2}:[0-9]{2}";

	$date_pattern = "(" . $month_pattern . "\ +" . $day_pattern . "\ +" . $time_pattern . ")";

	$log_message_pattern = "(.*)";

	$pattern = "/^" . $date_pattern . "\ +" . $log_message_pattern . "$/";

	if (!preg_match($pattern, $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $flent['message']) = $log_split;

	/* If there is time, and message, fields, then the line should be usable/good */
	if (!((trim($flent['time']) == "") && (trim($flent['message']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s.   Please report to mailing list or forum."), $errline));
		}
		return "";
	}
}

function parse_system_log_line($line) {
	global $config, $g;

	$flent = array();
	$log_split = "";

	$month_pattern = "[a-zA-Z]{3}";
	$day_pattern = "[0-9]{1,2}";
	$time_pattern = "[0-9]{2}:[0-9]{2}:[0-9]{2}";

	$date_pattern = "(" . $month_pattern . "\ +" . $day_pattern . "\ +" . $time_pattern . ")";

	$process_pattern = "(.*?)";
	$pid_pattern = "(.*?)(?::\ +)?(?:\[([0-9:]*)\])?:?";
	$log_message_pattern = "(.*)";

	$pattern = "/^" . $date_pattern . "\ +" . $process_pattern . "\ +" . $pid_pattern . "\ +" . $log_message_pattern . "$/";

	if (!preg_match($pattern, $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $flent['host'], $flent['process'], $flent['pid'], $flent['message']) = $log_split;

	/* If there is time, process, and message, fields, then the line should be usable/good */
	if (!((trim($flent['time']) == "") && (trim($flent['process']) == "") && (trim($flent['message']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing log entry: %s.   Please report to mailing list or forum."), $errline));
		}
		return "";
	}
}

function parse_firewall_log_line($line) {
	global $config, $g;

	$flent = array();
	$log_split = "";

	if (!preg_match("/(.*)\s(.*)\sfilterlog:\s(.*)$/", $line, $log_split)) {
		return "";
	}

	list($all, $flent['time'], $host, $rule) = $log_split;

	$rule_data = explode(",", $rule);
	$field = 0;

	$flent['rulenum'] = $rule_data[$field++];
	$flent['subrulenum'] = $rule_data[$field++];
	$flent['anchor'] = $rule_data[$field++];
	$flent['tracker'] = $rule_data[$field++];
	$flent['realint'] = $rule_data[$field++];
	$flent['interface'] = convert_real_interface_to_friendly_descr($flent['realint']);
	$flent['reason'] = $rule_data[$field++];
	$flent['act'] = $rule_data[$field++];
	$flent['direction'] = $rule_data[$field++];
	$flent['version'] = $rule_data[$field++];

	if ($flent['version'] == '4' || $flent['version'] == '6') {
		if ($flent['version'] == '4') {
			$flent['tos'] = $rule_data[$field++];
			$flent['ecn'] = $rule_data[$field++];
			$flent['ttl'] = $rule_data[$field++];
			$flent['id'] = $rule_data[$field++];
			$flent['offset'] = $rule_data[$field++];
			$flent['flags'] = $rule_data[$field++];
			$flent['protoid'] = $rule_data[$field++];
			$flent['proto'] = strtoupper($rule_data[$field++]);
		} else {
			$flent['class'] = $rule_data[$field++];
			$flent['flowlabel'] = $rule_data[$field++];
			$flent['hlim'] = $rule_data[$field++];
			$flent['proto'] = $rule_data[$field++];
			$flent['protoid'] = $rule_data[$field++];
		}

		$flent['length'] = $rule_data[$field++];
		$flent['srcip'] = $rule_data[$field++];
		$flent['dstip'] = $rule_data[$field++];

		if ($flent['protoid'] == '6' || $flent['protoid'] == '17') { // TCP or UDP
			$flent['srcport'] = $rule_data[$field++];
			$flent['dstport'] = $rule_data[$field++];

			$flent['src'] = $flent['srcip'] . ':' . $flent['srcport'];
			$flent['dst'] = $flent['dstip'] . ':' . $flent['dstport'];

			$flent['datalen'] = $rule_data[$field++];
			if ($flent['protoid'] == '6') { // TCP
				$flent['tcpflags'] = $rule_data[$field++];
				$flent['seq'] = $rule_data[$field++];
				$flent['ack'] = $rule_data[$field++];
				$flent['window'] = $rule_data[$field++];
				$flent['urg'] = $rule_data[$field++];
				$flent['options'] = explode(";", $rule_data[$field++]);
			}
		} else if ($flent['protoid'] == '1') { // ICMP
			$flent['src'] = $flent['srcip'];
			$flent['dst'] = $flent['dstip'];

			$flent['icmp_type'] = $rule_data[$field++];

			switch ($flent['icmp_type']) {
				case "request":
				case "reply":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					break;
				case "unreachproto":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_protoid'] = $rule_data[$field++];
					break;
				case "unreachport":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_protoid'] = $rule_data[$field++];
					$flent['icmp_port'] = $rule_data[$field++];
					break;
				case "unreach":
				case "timexceed":
				case "paramprob":
				case "redirect":
				case "maskreply":
					$flent['icmp_descr'] = $rule_data[$field++];
					break;
				case "needfrag":
					$flent['icmp_dstip'] = $rule_data[$field++];
					$flent['icmp_mtu'] = $rule_data[$field++];
					break;
				case "tstamp":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					break;
				case "tstampreply":
					$flent['icmp_id'] = $rule_data[$field++];
					$flent['icmp_seq'] = $rule_data[$field++];
					$flent['icmp_otime'] = $rule_data[$field++];
					$flent['icmp_rtime'] = $rule_data[$field++];
					$flent['icmp_ttime'] = $rule_data[$field++];
					break;
				default :
					$flent['icmp_descr'] = $rule_data[$field++];
					break;
			}

		} else if ($flent['protoid'] == '2') { // IGMP
			$flent['src'] = $flent['srcip'];
			$flent['dst'] = $flent['dstip'];
		} else if ($flent['protoid'] == '112') { // CARP
			$flent['type'] = $rule_data[$field++];
			$flent['ttl'] = $rule_data[$field++];
			$flent['vhid'] = $rule_data[$field++];
			$flent['version'] = $rule_data[$field++];
			$flent['advskew'] = $rule_data[$field++];
			$flent['advbase'] = $rule_data[$field++];
		}
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing rule number: %s. Please report to mailing list or forum."), $flent['rulenum']));
		}
		return "";
	}

	/* If there is a src, a dst, and a time, then the line should be usable/good */
	if (!((trim($flent['src']) == "") || (trim($flent['dst']) == "") || (trim($flent['time']) == ""))) {
		return $flent;
	} else {
		if ($g['debug']) {
			log_error(sprintf(gettext("There was a error parsing rule: %s. Please report to mailing list or forum."), $errline));
		}
		return "";
	}
}

function get_port_with_service($port, $proto) {
	if (!$port) {
		return '';
	}

	$service = getservbyport($port, $proto);
	$portstr = "";
	if ($service) {
		$portstr = sprintf('<span title="' . gettext('Service %1$s/%2$s: %3$s') . '">' . htmlspecialchars($port) . '</span>', $port, $proto, $service);
	} else {
		$portstr = htmlspecialchars($port);
	}
	return ':' . $portstr;
}

function find_rule_by_number($rulenum, $trackernum, $type="block") {
	global $g;

	/* Passing arbitrary input to grep could be a Very Bad Thing(tm) */
	if (!is_numeric($rulenum) || !is_numeric($trackernum) || !in_array($type, array('pass', 'block', 'match', 'rdr'))) {
		return;
	}

	if ($trackernum == "0") {
		$lookup_pattern = "^@{$rulenum}\([0-9]+\)[[:space:]]{$type}[[:space:]].*[[:space:]]log[[:space:]]";
	} else {
		$lookup_pattern = "^@[0-9]+\({$trackernum}\)[[:space:]]{$type}[[:space:]].*[[:space:]]log[[:space:]]";
	}

	/* At the moment, miniupnpd is the only thing I know of that
	   generates logging rdr rules */
	unset($buffer);
	if ($type == "rdr") {
		$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | /usr/bin/egrep " . escapeshellarg("^@{$rulenum}"), $buffer);
	} else {
		$_gb = exec("/sbin/pfctl -vvPsr | /usr/bin/egrep " . escapeshellarg($lookup_pattern), $buffer);
	}
	if (is_array($buffer)) {
		return $buffer[0];
	}

	return "";
}

function buffer_rules_load() {
	global $g, $buffer_rules_rdr, $buffer_rules_normal;
	unset($buffer, $buffer_rules_rdr, $buffer_rules_normal);
	/* Redeclare globals after unset to work around PHP */
	global $buffer_rules_rdr, $buffer_rules_normal;
	$buffer_rules_rdr = array();
	$buffer_rules_normal = array();

	$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | /usr/bin/grep '^@'", $buffer);
	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			$buffer_rules_rdr[$key] = $value;
		}
	}
	unset($buffer, $_gb);
	$_gb = exec("/sbin/pfctl -vvPsr | /usr/bin/egrep '^@[0-9]+\([0-9]+\)[[:space:]].*[[:space:]]log[[:space:]]'", $buffer);

	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			# pfctl rule number output with tracker number: @dd(dddddddddd)
			$matches = array();
			if (preg_match('/\@(?P<rulenum>\d+)\((?<trackernum>\d+)\)/', $key, $matches) == 1) {
				if ($matches['trackernum'] > 0) {
					$key = $matches['trackernum'];
				} else {
					$key = "@{$matches['rulenum']}";
				}
			}
			$buffer_rules_normal[$key] = $value;
		}
	}
	unset($_gb, $buffer);
}

function buffer_rules_clear() {
	unset($GLOBALS['buffer_rules_normal']);
	unset($GLOBALS['buffer_rules_rdr']);
}

function find_rule_by_number_buffer($rulenum, $trackernum, $type) {
	global $g, $buffer_rules_rdr, $buffer_rules_normal;

	if ($trackernum == "0") {
		$lookup_key = "@{$rulenum}";
	} else {
		$lookup_key = $trackernum;
	}

	if ($type == "rdr") {
		$ruleString = $buffer_rules_rdr[$lookup_key];
		//TODO: get the correct 'description' part of a RDR log line. currently just first 30 characters..
		$rulename = substr($ruleString, 0, 30);
	} else {
		$ruleString = $buffer_rules_normal[$lookup_key];
		list(,$rulename,) = explode("\"", $ruleString);
		$rulename = str_replace("USER_RULE: ", '<i class="fa fa-user"></i> ', $rulename);
	}
	return "{$rulename} ({$lookup_key})";
}

function find_action_image($action) {
	global $g;
	if ((strstr(strtolower($action), "p")) || (strtolower($action) == "rdr")) {
		return "fa-check-circle-o";
	} else if (strstr(strtolower($action), "r")) {
		return "fa-times-circle-o";
	} else {
		return "fa-ban";
	}
}

/* AJAX specific handlers */
function handle_ajax($nentries, $tail = 50) {
	global $config;
	if ($_GET['lastsawtime'] or $_POST['lastsawtime']) {
		global $filter_logfile, $filterent;
		if ($_GET['lastsawtime']) {
			$lastsawtime = $_GET['lastsawtime'];
		}
		if ($_POST['lastsawtime']) {
			$lastsawtime = $_POST['lastsawtime'];
		}
		/* compare lastsawrule's time stamp to filter logs.
		 * afterwards return the newer records so that client
		 * can update AJAX interface screen.
		 */
		$new_rules = "";
		$filterlog = conv_log_filter($filter_logfile, $nentries, $tail);
		/* We need this to always be in forward order for the AJAX update to work properly */
		$filterlog = isset($config['syslog']['reverse']) ? array_reverse($filterlog) : $filterlog;
		foreach ($filterlog as $log_row) {
			$row_time = strtotime($log_row['time']);
			if ($row_time > $lastsawtime) {
				if ($log_row['proto'] == "TCP") {
					$log_row['proto'] .= ":{$log_row['tcpflags']}";
				}

				if ($log_row['act'] == "block") {
					$icon_act = "fa-times icon-danger";
				} else {
					$icon_act = "fa-check icon-success";
				}

				$btn = '<i class="fa ' . $icon_act . ' icon-pointer" title="' . $log_row['act'] . '/' . $log_row['tracker'] . '" onclick="javascript:getURL(\'diag_logs_filter.php?getrulenum=' . $log_row['rulenum'] . ',' . $log_row['tracker'] . ',' . $log_row['act'] . '\', outputrule);"></i>';
				$new_rules .= "{$btn}||{$log_row['time']}||{$log_row['interface']}||{$log_row['srcip']}||{$log_row['srcport']}||{$log_row['dstip']}||{$log_row['dstport']}||{$log_row['proto']}||{$log_row['version']}||" . time() . "||\n";
			}
		}
		echo $new_rules;
		exit;
	}
}

?>
