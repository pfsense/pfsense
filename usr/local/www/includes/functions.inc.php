<?
function cpu_usage() {
	sleep(5);
	return get_cpuusage(get_cputicks(), get_cputicks());
}


function get_uptime() {
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

function get_cputicks() {
	sleep(5);
	$cputicks = explode(" ", `/sbin/sysctl -n kern.cp_time`);
	return $cputicks;
}

function get_cpuusage($cpuTicks, $cpuTicks2) {

	$diff = array();
	$diff['user'] = ($cpuTicks2[0] - $cpuTicks[0])+1;
	$diff['nice'] = ($cpuTicks2[1] - $cpuTicks[1])+1;
	$diff['sys'] = ($cpuTicks2[2] - $cpuTicks[2])+1;
	$diff['intr'] = ($cpuTicks2[3] - $cpuTicks[3])+1;
	$diff['idle'] = ($cpuTicks2[4] - $cpuTicks[4])+1;
	
	//echo "<!-- user: {$diff['user']}  nice {$diff['nice']}  sys {$diff['sys']}  intr {$diff['intr']}  idle {$diff['idle']} -->";
	$totalDiff = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'] + $diff['idle'];
	$totalused = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'] - 1;
	$cpuUsage = round(100 * ($totalused / $totalDiff), 0);
	
	#$totalDiff = $diff['user'] + $diff['nice'] + $diff['sys'] + $diff['intr'] + $diff['idle'];
	#$cpuUsage = round(100 * (1 - $diff['idle'] / $totalDiff), 0);
	
	return $cpuUsage;
}

function get_pfstate() {
	global $config;
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


function disk_usage()
{
	exec("df -h | grep -w '/' | awk '{ print $5 }' | cut -d '%' -f 1", $dfout);
	$diskusage = trim($dfout[0]);

	return $diskusage;
}

function swap_usage()
{
	$swapUsage = `/usr/sbin/swapinfo | cut -c45-55 | grep "%"`;
	$swapUsage = ereg_replace('%', "", $swapUsage);
	$swapUsage = ereg_replace(' ', "", $swapUsage);
	$swapUsage = rtrim($swapUsage);

	return $swapUsage;
}

function mem_usage()
{
	exec("/sbin/sysctl -n vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count " .
		"vm.stats.vm.v_wire_count vm.stats.vm.v_cache_count vm.stats.vm.v_free_count", $memory);
	
	$totalMem = $memory[0] + $memory[1] + $memory[2] + $memory[3] + $memory[4];
	$freeMem = $memory[4];
	$usedMem = $totalMem - $freeMem;
	$memUsage = round(($usedMem * 100) / $totalMem, 0);

	return $memUsage;
}