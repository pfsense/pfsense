<?
/*
	pfSense_MODULE:	ajax
*/

if(Connection_Aborted()) {
	exit;
}

require_once("config.inc");

function get_stats() {
	$stats['cpu'] = cpu_usage();
	$stats['mem'] = mem_usage();
	$stats['uptime'] = get_uptime();
	$stats['states'] = get_pfstate();
	$stats['temp'] = get_temp();
	$stats['datetime'] = update_date_time();
	$stats['interfacestatistics'] = get_interfacestats();
	$stats['interfacestatus'] = get_interfacestatus();
	$stats['gateways'] = get_gatewaystats();
	$stats['cpufreq'] = get_cpufreq();
	$stats['load_average'] = get_load_average();
	$stats['mbuf'] = get_mbuf();
	$stats['mbufpercent'] = get_mbuf(true);
	$stats['statepercent'] = get_pfstate(true);
	$stats = join("|", $stats);
	return $stats;
}

function get_gatewaystats() {
	$a_gateways = return_gateways_array();
	$gateways_status = array();
	$gateways_status = return_gateways_status(true);
	$data = "";
	$isfirst = true;
	foreach($a_gateways as $gname => $gw) {
		if(!$isfirst)
			$data .= ",";
		$isfirst = false;
		$data .= $gw['name'] . ",";
		if ($gateways_status[$gname]) {
			$data .= lookup_gateway_ip_by_name($gname) . ",";
			$gws = $gateways_status[$gname];
			switch(strtolower($gws['status'])) {
			case "none":
				$online = "Online";
				$bgcolor = "#90EE90";  // lightgreen
				break;
			case "down":
				$online = "Offline";
				$bgcolor = "#F08080";  // lightcoral
				break;
			case "delay":
				$online = "Latency";
				$bgcolor = "#F0E68C";  // khaki
				break;
			case "loss":
				$online = "Packetloss";
				$bgcolor = "#F0E68C";  // khaki
				break;
			default:
				$online = "Pending";
				break;
			}
		} else {
			$data .= "~,";
			$gws['delay'] = "~";
			$gws['loss'] = "~";
			$online = "Unknown";
			$bgcolor = "#ADD8E6";  // lightblue
		}
		$data .= ($online == "Pending") ? "{$online},{$online}," : "{$gws['delay']},{$gws['loss']},";
		$data .= "<table border=\"0\" cellpadding=\"0\" cellspacing=\"2\" summary=\"status\"><tr><td bgcolor=\"$bgcolor\">&nbsp;$online&nbsp;</td></tr></table>";
	}
	return $data;
}

function get_uptime() {
	$boottime = "";
	$matches = "";
	exec("/sbin/sysctl -n kern.boottime", $boottime);
	preg_match("/sec = (\d+)/", $boottime[0], $matches);
	$boottime = $matches[1];
	$uptime = time() - $boottime;

	if(intval($boottime) == 0)
		return;
	if(intval($uptime) == 0)
		return;

	$updays = (int)($uptime / 86400);
	$uptime %= 86400;
	$uphours = (int)($uptime / 3600);
	$uptime %= 3600;
	$upmins = (int)($uptime / 60);
	$uptime %= 60;
	$upsecs = (int)($uptime);

	$uptimestr = "";
	if ($updays > 1)
		$uptimestr .= "$updays Days ";
	else if ($updays > 0)
		$uptimestr .= "1 Day ";

	if ($uphours > 1)
		$hours = "s";

	if ($upmins > 1)
		$minutes = "s";

	if ($upmins > 1)
		$seconds = "s";

	$uptimestr .= sprintf("%02d Hour$hours %02d Minute$minutes %02d Second$seconds", $uphours, $upmins, $upsecs);
	return $uptimestr;
}

/* Calculates non-idle CPU time and returns as a percentage */
function cpu_usage() {
	$duration = 1;
	$diff = array('user', 'nice', 'sys', 'intr', 'idle');
	$cpuTicks = array_combine($diff, explode(" ", `/sbin/sysctl -n kern.cp_time`));
	sleep($duration);
	$cpuTicks2 = array_combine($diff, explode(" ", `/sbin/sysctl -n kern.cp_time`));

	$totalStart = array_sum($cpuTicks);
	$totalEnd = array_sum($cpuTicks2);

	// Something wrapped ?!?!
	if ($totalEnd <= $totalStart)
		return 0;

	// Calculate total cycles used
	$totalUsed = ($totalEnd - $totalStart) - ($cpuTicks2['idle'] - $cpuTicks['idle']);

	// Calculate the percentage used
	$cpuUsage = floor(100 * ($totalUsed / ($totalEnd - $totalStart)));

	return $cpuUsage;
}

function get_pfstate($percent=false) {
	global $config;
	$matches = "";
	if (isset($config['system']['maximumstates']) and $config['system']['maximumstates'] > 0)
		$maxstates="{$config['system']['maximumstates']}";
	else
		$maxstates=pfsense_default_state_size();
	$curentries = `/sbin/pfctl -si |grep current`;
	if (preg_match("/([0-9]+)/", $curentries, $matches)) {
		$curentries = $matches[1];
	}
	if ($percent)
		if ($maxstates > 0)
			return round(($curentries / $maxstates) * 100, 0);
		else
			return "NA";
	else
		return $curentries . "/" . $maxstates;
}

function has_temp() {
	/* no known temp monitors available at present */

	/* should only reach here if there is no hardware monitor */
	return false;
}

function get_hwtype() {
	return;
}

function get_mbuf($percent=false) {
	$mbufs_output=trim(`/usr/bin/netstat -mb | /usr/bin/grep "mbuf clusters in use" | /usr/bin/awk '{ print $1 }'`);
	list( $mbufs_current, $mbufs_cache, $mbufs_total, $mbufs_max ) = explode( "/", $mbufs_output);
	if ($percent)
		if ($mbufs_max > 0)
			return round(($mbufs_total / $mbufs_max) * 100, 0);
		else
			return "NA";
	else
		return "{$mbufs_total}/{$mbufs_max}";
}

function get_temp() {
	$temp_out = "";
	exec("/sbin/sysctl dev.cpu.0.temperature | /usr/bin/awk '{ print $2 }' | /usr/bin/cut -d 'C' -f 1", $dfout);
	$temp_out = trim($dfout[0]);
	if ($temp_out == "") {
		exec("/sbin/sysctl hw.acpi.thermal.tz0.temperature | /usr/bin/awk '{ print $2 }' | /usr/bin/cut -d 'C' -f 1", $dfout);
		$temp_out = trim($dfout[0]);
	}

	return $temp_out;
}

function disk_usage() {
	$dfout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $5 }' | /usr/bin/cut -d '%' -f 1", $dfout);
	$diskusage = trim($dfout[0]);

	return $diskusage;
}

function swap_usage() {
	exec("/usr/sbin/swapinfo", $swap_info);
	$swap_used = "";
	foreach ($swap_info as $line)
		if (preg_match('/(\d+)%$/', $line, $matches)) {
			$swap_used = $matches[1];
			break;
		}

	return $swap_used;
}

function mem_usage() {
	$memory = "";
	exec("/sbin/sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_inactive_count " .
		"vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);

	$totalMem = $memory[0];
	$availMem = $memory[1] + $memory[2] + $memory[3];
	$usedMem = $totalMem - $availMem;
	if ($totalMem > 0)
		$memUsage = round(($usedMem * 100) / $totalMem, 0);
	else
		$memUsage = "NA";

	return $memUsage;
}

function update_date_time() {
	$datetime = date("D M j G:i:s T Y");
	return $datetime;
}

function get_cpufreq() {
	$cpufreqs = "";
	$out = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq_levels", $cpufreqs);
	$cpufreqs = explode(" ", trim($cpufreqs[0]));
	$maxfreq = explode("/", $cpufreqs[0]);
	$maxfreq = $maxfreq[0];
	$curfreq = "";
	exec("/sbin/sysctl -n dev.cpu.0.freq", $curfreq);
	$curfreq = trim($curfreq[0]);
	if (($curfreq > 0) && ($curfreq != $maxfreq))
		$out = "Current: {$curfreq} MHz, Max: {$maxfreq} MHz";
	return $out;
}

function get_cpu_count($show_detail = false) {
	$cpucount = "";
	exec("/sbin/sysctl -n kern.smp.cpus", $cpucount);
	$cpucount = $cpucount[0];

	if ($show_detail) {
		$cpudetail = "";
		exec("/usr/bin/grep 'SMP.*package.*core' /var/log/dmesg.boot | /usr/bin/cut -f2- -d' '", $cpudetail);
		$cpucount = $cpudetail[0];
	}
	return $cpucount;
}

function get_load_average() {
	$load_average = "";
	exec("/usr/bin/uptime | /usr/bin/sed 's/^.*: //'", $load_average);
	return $load_average[0];
}

function get_interfacestats() {
	global $config;
	//build interface list for widget use
	$ifdescrs = get_configured_interface_list();

	$array_in_packets = array();
	$array_out_packets = array();
	$array_in_bytes = array();
	$array_out_bytes = array();
	$array_in_errors = array();
	$array_out_errors = array();
	$array_collisions = array();
	$array_interrupt = array();
	$new_data = "";

	//build data arrays
	foreach ($ifdescrs as $ifdescr => $ifname){
		$ifinfo = get_interface_info($ifdescr);
			$new_data .= "{$ifinfo['inpkts']},";
			$new_data .= "{$ifinfo['outpkts']},";
			$new_data .= format_bytes($ifinfo['inbytes']) . ",";
			$new_data .= format_bytes($ifinfo['outbytes']) . ",";
			if (isset($ifinfo['inerrs'])){
				$new_data .= "{$ifinfo['inerrs']},";
				$new_data .= "{$ifinfo['outerrs']},";
			}
			else{
				$new_data .= "0,";
				$new_data .= "0,";
			}
			if (isset($ifinfo['collisions']))
				$new_data .= htmlspecialchars($ifinfo['collisions']) . ",";
			else
				$new_data .= "0,";
	}//end for

	return $new_data;
}

function get_interfacestatus() {
	$data = "";
	global $config;

	//build interface list for widget use
	$ifdescrs = get_configured_interface_with_descr();

	foreach ($ifdescrs as $ifdescr => $ifname){
		$ifinfo = get_interface_info($ifdescr);
		$data .= $ifname . ",";
		if($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") {
			$data .= "up";
		}else if ($ifinfo['status'] == "no carrier") {
			$data .= "down";
		}else if ($ifinfo['status'] == "down") {
			$data .= "block";
		}
		$data .= ",";
		if ($ifinfo['ipaddr'])
			$data .= htmlspecialchars($ifinfo['ipaddr']);
		$data .= ",";
		if ($ifinfo['status'] != "down")
			$data .= htmlspecialchars($ifinfo['media']);

		$data .= "~";

	}
	return $data;
}

?>
