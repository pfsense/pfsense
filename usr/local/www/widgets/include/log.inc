<?php

//set variable for custom title
$log_title = "Firewall Logs";
$log_title_link = "diag_logs_filter.php";

//set variables for log
$filter_logfile = "{$g['varlog_path']}/filter.log";
$nentries = 5;
$filterlog = conv_clog_filter($filter_logfile, $nentries);

/* AJAX related routines */
	handle_ajax();
	

/* format filter logs */	
function conv_clog_filter($logfile, $tail = 8) {
	global $config, $nentries, $g;
	$logarr = "";
	/* make interface/port table */
	$iftable = array();
	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $ifl => $ifdesc)
		$iftable[get_real_interface($ifl)] = $ifdesc;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$sor} -n 500", $logarr);

	$filterlog = array();

	$counter = 1;

	foreach ($logarr as $logent) {

		if($counter > $nentries)
			break;

		$log_split = "";


		preg_match("/(\b(?:\d{1,3}\.){3}\d{1,3}(\.\w+)?)\s.*\s(\b(?:\d{1,3}\.){3}\d{1,3}(\.\w+)?)/", $logent, $log_split);

		$flent['src'] 		= convert_port_period_to_colon($log_split[1]);
		$flent['dst'] 		= convert_port_period_to_colon($log_split[3]);

		preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);

		$beforeupper = $logent;
		$logent = strtoupper($logent);

		if(stristr(strtoupper($logent), "UDP") == true)
			$flent['proto'] = "UDP";
		else if(stristr(strtoupper($logent), "TCP") == true)
			$flent['proto'] = "TCP";
		else if(stristr(strtoupper($logent), "ICMP") == true)
			$flent['proto'] = "ICMP";
		else if(stristr(strtoupper($logent), "HSRP") == true)
			$flent['proto'] = "HSRP";
		else if(stristr(strtoupper($logent), "ESP") == true)
			$flent['proto'] = "ESP";
		else if(stristr(strtoupper($logent), "AH") == true)
			$flent['proto'] = "AH";
		else if(stristr(strtoupper($logent), "GRE") == true)
			$flent['proto'] = "GRE";
		else if(stristr(strtoupper($logent), "IGMP") == true)
			$flent['proto'] = "IGMP";
		else if(stristr(strtoupper($logent), "CARP") == true)
			$flent['proto'] = "CARP";
		else if(stristr(strtoupper($logent), "VRRP") == true)
			$flent['proto'] = "VRRP";
		else if(stristr(strtoupper($logent), "PFSYNC") == true)
			$flent['proto'] = "PFSYNC";
		else if(stristr($logent, "sack") == true)
			$flent['proto'] = "TCP";
		else
			$flent['proto'] = "TCP";

		$flent['time'] 		= $log_split[1];
		$flent['act'] 		= $log_split[3];
		$flent['interface'] 	=  empty($iftable[$log_split[4]]) ? $log_split[4] : $iftable[$log_split[4]];

		$tmp = split("/", $log_split[2]);
		$flent['rulenum'] = $tmp[0];

		$shouldadd = true;

		if(trim($flent['src']) == "")
			$shouldadd = false;
		if(trim($flent['dst']) == "")
			$shouldadd = false;
		if(trim($flent['time']) == "")
			$shouldadd = false;

		if($shouldadd == true) {
			$counter++;
			$filterlog[] = $flent;
		} else {
			if($g['debug']) {
				log_error("There was a error parsing rule: $beforeupper .   Please report to mailing list or forum.");
			}
		}

	}

	return $filterlog;
}

function convert_port_period_to_colon($addr) {
	$addr_split = split("\.", $addr);
	if($addr_split[4] == "")
		$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3];
	else
		$newvar = $addr_split[0] . "." . $addr_split[1] . "." . $addr_split[2] . "." . $addr_split[3] . ":" . $addr_split[4];
	if($newvar == "...")
		return $addr;
	return $newvar;
}

function format_ipf_ip($ipfip) {
	list($ip,$port) = explode(",", $ipfip);
	if (!$port)
		return $ip;

	return $ip . ", port " . $port;
}

/* AJAX specific handlers */
function handle_ajax() {
	if($_GET['getrulenum'] or $_POST['getrulenum']) {
		if($_GET['getrulenum'])
			$rulenum = $_GET['getrulenum'];
		if($_POST['getrulenum'])
			$rulenum = $_POST['getrulenum'];
		$rule = `pfctl -vvsr | grep @{$rulenum}`;
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
		$filterlog = conv_clog_filter($filter_logfile, 8);
		foreach($filterlog as $log_row) {
			$time_regex = "";
			preg_match("/.*([0-9][0-9]:[0-9][0-9]:[0-9][0-9])/", $log_row['time'], $time_regex);
			$row_time = strtotime($time_regex[1]);
			if (strstr(strtolower($log_row['act']), "p"))
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_pass.gif'>";
			else if(strstr(strtolower($filterent['act']), "r"))
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_reject.gif'>";
			else
				$img = "<img border='0' src='/themes/metallic/images/icons/icon_block.gif'>";
			//echo "{$time_regex[1]} - $row_time > $lastsawtime<p>";
			if($row_time > $lastsawtime)
				$new_rules .= "{$img}||{$log_row['time']}||{$log_row['interface']}||{$log_row['src']}||{$log_row['dst']}||{$log_row['proto']}||" . time() . "||\n";
		}
		echo $new_rules;
		exit;
	}
}
?>
