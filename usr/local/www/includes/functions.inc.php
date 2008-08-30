<?

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

	$stats = join("|", $stats);

	return $stats;
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

	$uptimestr = "";
	if ($updays > 1)
		$uptimestr .= "$updays days, ";
	else if ($updays > 0)
		$uptimestr .= "1 day, ";
	$uptimestr .= sprintf("%02d:%02d", $uphours, $upmins);
	return $uptimestr;
}

function cpu_usage() {
	exec("sysctl -n kern.cp_time && sleep 1 && sysctl -n kern.cp_time",$cpuTicksEx);

	$cpuTicks = explode(" ", $cpuTicksEx[0]);
	$cpuTicks2 = explode(" ", $cpuTicksEx[1]);
	
	$diff = array();
    $diff['user'] = ($cpuTicks2[0] - $cpuTicks[0]);
    $diff['nice'] = ($cpuTicks2[1] - $cpuTicks[1]);
    $diff['sys'] = ($cpuTicks2[2] - $cpuTicks[2]);
    $diff['intr'] = ($cpuTicks2[3] - $cpuTicks[3]);
    $diff['idle'] = ($cpuTicks2[4] - $cpuTicks[4]);
    
    //echo "<!-- user: {$diff['user']}  nice {$diff['nice']}  sys {$diff['sys']}  intr {$diff['intr']}  idle {$diff['idle']} -->";
    $totalDiff = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'] + $diff['idle'];
    $totalused = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'];
        if (isset($totalused)&&$totalused <= 0) {
            $totalused = 0.001;
        }
    $cpuUsage = floor(100 * ($totalused / $totalDiff));
	return $cpuUsage;
}

function get_pfstate() {
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

function get_temp() {
	switch(get_hwtype()) {
		default:
			return;
	}

	return $ret;
}

function disk_usage()
{
	$dfout = "";
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $5 }' | /usr/bin/cut -d '%' -f 1", $dfout);
	$diskusage = trim($dfout[0]);

	return $diskusage;
}

function swap_usage()
{
	$swapUsage = `/usr/sbin/swapinfo | /usr/bin/awk '{print $5;'}|/usr/bin/grep '%'`;
	$swapUsage = ereg_replace('%', "", $swapUsage);
	$swapUsage = rtrim($swapUsage);

	return $swapUsage;
}

function mem_usage()
{
	$memory = "";
	exec("/sbin/sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_inactive_count " .
		"vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);
	
	$totalMem = $memory[0];
	$availMem = $memory[1] + $memory[2] + $memory[3];
	$usedMem = $totalMem - $availMem;
	$memUsage = round(($usedMem * 100) / $totalMem, 0);

	return $memUsage;
}

function update_date_time() {
	
	$datetime = date("D M j G:i:s T Y");
	return $datetime;
}

function get_interfacestats(){
	
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

function get_interfacestatus(){
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
		if ($ifinfo['ipaddr']){ 
			$data .= htmlspecialchars($ifinfo['ipaddr']);
			if ($ifinfo['dhcplink']) {
				$data .= " (DHCP)";
			}
		}
		$data .= ",";
		if ($ifinfo['status'] != "down")
			$data .= htmlspecialchars($ifinfo['media']);
			
		$data .= "~";
		
	}	
	return $data;
}

?>