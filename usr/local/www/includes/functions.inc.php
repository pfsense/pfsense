<?

if(Connection_Aborted()) {
	exit;
}

require_once("config.inc");
require_once("guiconfig.inc");

function array_combine($arr1, $arr2) {
    $out = array();

    $arr1 = array_values($arr1);
    $arr2 = array_values($arr2);

    foreach($arr1 as $key1 => $value1) {
        $out[(string)$value1] = $arr2[$key1];
    }

    return $out;
}

function get_stats() {
	$stats['cpu'] = cpu_usage();
	$stats['mem'] = mem_usage();
	$stats['uptime'] = get_uptime();
	$stats['states'] = get_pfstate();
	$stats['temp'] = get_temp();

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

	if ($uptime > 60)
		$uptime += 30;
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

function get_pfstate() {
	global $config;
	$matches = "";
	if (isset($config['system']['maximumstates']) and $config['system']['maximumstates'] > 0)
	        $maxstates="/{$config['system']['maximumstates']}";
	else
	        $maxstates="/10000";
	$curentries = `/sbin/pfctl -si |grep current`;
	if (preg_match("/([0-9]+)/", $curentries, $matches)) {
	     $curentries = $matches[1];
	}
	return $curentries . $maxstates;
}

function has_temp() {
	if(`/sbin/dmesg -a | /usr/bin/grep net4801` <> "") {
		/* Initialize hw monitor */
		exec("/usr/local/sbin/env4801 -i");
		return true;
	}

	/* should only reach here if there is no hardware monitor */
	return false;
}

function get_hwtype() {
        if(`/sbin/dmesg -a | /usr/bin/grep net4801` <> "") {
                return "4801";
        }

	return;
}

function get_temp() {
	switch(get_hwtype()) {
		case '4801':
			$ret = rtrim(`/usr/local/sbin/env4801 | /usr/bin/grep Temp |/usr/bin/cut -c24-25`);
			break;
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

?>