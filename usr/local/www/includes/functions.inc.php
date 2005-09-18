<?

if(Connection_Aborted()) {
	exit;
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

function cpu_usage() {
	$cpuTicks = explode(" ", `/sbin/sysctl -n kern.cp_time`);
	usleep(5);
	$cpuTicks2 = explode(" ", `/sbin/sysctl -n kern.cp_time`);
	
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
	exec("/bin/df -h | /usr/bin/grep -w '/' | /usr/bin/awk '{ print $5 }' | /usr/bin/cut -d '%' -f 1", $dfout);
	$diskusage = trim($dfout[0]);

	return $diskusage;
}

function swap_usage()
{
	$swapUsage = `/usr/sbin/swapinfo | /usr/bin/cut -c45-55 | /usr/bin/grep "%"`;
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
