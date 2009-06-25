<?php
/* format filter logs */	
function conv_clog_filter($logfile, $nentries, $tail = 50) {
	global $config, $g;
	$logarr = "";

	/* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail -r -n {$tail}", $logarr);

	$filterlog = array();
	$counter = 0;

	foreach ($logarr as $logent) {
		if($counter >= $nentries)
			break;

		$flent = parse_filter_line($logent);
		if ($flent != "") {
			$counter++;
			$filterlog[] = $flent;
		}
	}
	/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
	return isset($config['syslog']['reverse']) ? $filterlog : array_reverse($filterlog);
}

function parse_filter_line($line) {
	global $config, $g;
	/* make interface/port table */
	$iftable = array();
	$iftable[$config['interfaces']['lan']['if']] = "LAN";
	$iftable[get_real_wan_interface()] = "WAN";
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iftable[$config['interfaces']['opt' . $i]['if']] = $config['interfaces']['opt' . $i]['descr'];

	$log_split = "";
	
	preg_match("/(.*)\s(.*)\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s\((.*)\)\s(.*)\s>\s(.*)\:\s(.*)/", $line, $log_split);
	
	list($all, $ltime, $host, $rule, $action, $int, $details, $src, $dst, $leftovers) = $log_split;

	$flent['src'] 		= convert_port_period_to_colon($src);
	$flent['dst'] 		= convert_port_period_to_colon($dst);

	$proto = array(" ", "(?)");
	/* Attempt to determine the protocol, based on several possible patterns */
	if (!(strpos($details, 'proto ') === FALSE)) {
		preg_match("/.*\sproto\s(.*)\s\(/", $details, $proto);
	} elseif (!(strpos($details, 'proto: ') === FALSE)) {
		preg_match("/.*\sproto\:(.*)\s\(/", $details, $proto);
	} elseif (!(strpos($leftovers, 'sum ok] ') === FALSE)) {
		preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $leftovers, $proto);
	} elseif (!(strpos($line, 'sum ok] ') === FALSE)) {
		preg_match("/.*\ssum ok]\s(.*)\,\s.*/", $line, $proto);
	}
	$proto = split(" ", $proto[1]);
	$flent['proto'] = rtrim($proto[0], ",");
	
	/* If we're dealing with TCP, try to determine the flags/control bits */
	$flent['tcpflags'] = "";
	if ($flent['proto'] == "TCP") {
		$flags = split('[\, ]', $leftovers);
		$flent['tcpflags'] = $flags[0];
		if ($flent['tcpflags'] == ".")
			$flent['tcpflags'] = "A";
	}

	$flent['time'] 		= $ltime;
	$flent['act'] 		= $action;

	$friendly_int = convert_real_interface_to_friendly_interface_name($int);

	$flent['interface'] 	=  strtoupper($friendly_int);
	$flent['realint']       = $int;

	if($config['interfaces'][$friendly_int]['descr'] <> "")
		$flent['interface'] = "{$config['interfaces'][$friendly_int]['descr']}";

	$tmp = split("/", $rule);
	$flent['rulenum'] = $tmp[0];

	$usableline = true;

	if(trim($flent['src']) == "")
		$usableline = false;
	if(trim($flent['dst']) == "")
		$usableline = false;
	if(trim($flent['time']) == "")
		$usableline = false;

	if($usableline == true) {
		return $flent;
	} else {
		if($g['debug']) {
			log_error("There was a error parsing rule: $errline.   Please report to mailing list or forum.");
		}
		return "";
	}
}

function convert_port_period_to_colon($addr) {
	if (substr_count($addr, '.') > 1) {
		/* IPv4 - Change the port delimiter to : */
		$addr_split = split("\.", $addr);
		if($addr_split[4] == "") {
			$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3];
			$newvar = rtrim($newvar, ":");
		} else {
			$port_split = split("\:", $addr_split[4]);
			$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3] . ":" . $port_split[0];
			$newvar = rtrim($newvar, ":");
		}
		if($newvar == "...")
			return $addr;
		return $newvar;
	} else {
		/* IPv6 - Leave it alone */
		$addr = split(" ", $addr);
		return rtrim($addr[0], ":");
	}
}

function format_ipf_ip($ipfip) {
	list($ip,$port) = explode(",", $ipfip);
	if (!$port)
		return $ip;

	return $ip . ", port " . $port;
}

function find_rule_by_number($rulenum) {
	return `pfctl -vvsr | grep '^@{$rulenum} '`;
}

/* AJAX specific handlers */
function handle_ajax($tail = 50, $showflags = false) {
	if($_GET['getrulenum'] or $_POST['getrulenum']) {
		if($_GET['getrulenum'])
			$rulenum = $_GET['getrulenum'];
		if($_POST['getrulenum'])
			$rulenum = $_POST['getrulenum'];
		$rule = `pfctl -vvsr | grep '^@{$rulenum} '`;
		echo "The rule that triggered this action is:\n\n{$rule}";
		exit;
	}

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
		$filterlog = conv_clog_filter($filter_logfile, $tail);
		foreach($filterlog as $log_row) {
			$time_regex = "";
			preg_match("/.*([0-9][0-9]:[0-9][0-9]:[0-9][0-9])/", $log_row['time'], $time_regex);
			$row_time = strtotime($time_regex[1]);
			if (strstr(strtolower($log_row['act']), "p"))
				$img = "<img border='0' src='/themes/{$g['theme']}/images/icons/icon_pass.gif'>";
			else if(strstr(strtolower($filterent['act']), "r"))
				$img = "<img border='0' src='/themes/{$g['theme']}/images/icons/icon_reject.gif'>";
			else
				$img = "<img border='0' src='/themes/{$g['theme']}/images/icons/icon_block.gif'>";
			//echo "{$time_regex[1]} - $row_time > $lastsawtime<p>";
			if($row_time > $lastsawtime) {

				if ($showflags && $log_row['proto'] == "TCP")
					$log_row['proto'] .= ":" . $log_row['tcpflags'];

				$img = '<a href="#" onClick="javascript:getURL(\'diag_logs_filter.php?getrulenum=' . $log_row['rulenum'] . '\', outputrule);">' . $img . "</a>";
				$new_rules .= "{$img}||{$log_row['time']}||{$log_row['interface']}||{$log_row['src']}||{$log_row['dst']}||{$log_row['proto']}||" . time() . "||\n";
			}
		}
		echo $new_rules;
		exit;
	}
}

?>