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
	global $config, $nentries, $logfile;

	$logfile = "/var/log/filter.log";

	/* make interface/port table */
	$iftable = array();
	$iftable[$config['interfaces']['lan']['if']] = "LAN";
	$iftable[get_real_wan_interface()] = "WAN";
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
		$iftable[$config['interfaces']['opt' . $i]['if']] = $config['interfaces']['opt' . $i]['descr'];

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	$logarr = "";
	exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$sor} -n {$tail}", $logarr);

	$filterlog = array();

	$counter = 0;

	foreach ($logarr as $logent) {

		if($counter > $nentries)
			break;

		$log_split = "";
		
		//old reg ex
		//preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);

		preg_match("/(.*)\s.*\spf:\s.*\srule\s(.*)\(match\)\:\s(.*)\s\w+\son\s(\w+)\:\s.*\slength\:.*\s(.*)\s>\s(.*)\:\s.*/", $logent, $log_split);
	
	
		$logent = strtoupper($logent);

		$do_not_display = false;

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
		else if(stristr(strtoupper($logent), "PFSYNC") == true)
			$flent['proto'] = "PFSYNC";
		else
			$flent['proto'] = "TCP";

		$time_regex = "";
		preg_match("/.*([0-9][0-9]:[0-9][0-9]:[0-9][0-9])/", $log_split[1], $time_regex);
		$row_time = strtotime($time_regex[1]);

		$flent['time'] 		= $row_time;
		$flent['act'] 		= $log_split[3];

		$friendly_int = convert_real_interface_to_friendly_interface_name($log_split[4]);

		$flent['interface'] 	=  strtoupper($friendly_int);

		if($config['interfaces'][$friendly_int]['descr'] <> "")
			$flent['interface'] = "{$config['interfaces'][$friendly_int]['descr']}";

		$flent['src'] 		= convert_port_period_to_colon($log_split[5]);
		$flent['dst'] 		= convert_port_period_to_colon($log_split[6]);

		$flent['dst'] = str_replace(": NBT UDP PACKET(137)", "", $flent['dst']);

		$tmp = split("/", $log_split[2]);
		$flent['rulenum'] = $tmp[0];

		$counter++;
		$filterlog[] = $flent;

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