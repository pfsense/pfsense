#!/usr/local/bin/php -q
<?php
ini_set('apc.enabled', '0');
define('MAXDNAME', 1025);

date_default_timezone_set('UTC');

require_once("util.inc");

function ip_in_subnets($ip, $subnets) {
	if (!is_array($subnets))
		$subnets = array($subnets);
	
	foreach ($subnets as &$subnet) {
		if (ip_in_subnet($ip, $subnet))
			return true;
	}
	return false;
}

function canonicalise($s) {
	$l = count($s);
	if ($l <= MAXDNAME && preg_match('/^([a-zA-Z0-9\-\/_ ])+\.?$/', $s, $matches)) {
		return $matches[0];
	}
	
	return null;
}

$opts = getopt("n:h:o:p:l:d:s:");

$domain = 'local';
foreach (array_keys($opts) as $opt) switch ($opt) {
	case 'n': $subnets = $opts['n'];	break;
	case 'h': $hosts = $opts['h'];		break;
	case 'l': $leases = $opts['l'];		break;
	case 'o': $output = $opts['o'];		break;
	case 'p': $pid = $opts['p'];			break;
	case 'd': $domain = $opts['d'];		break;
	case 's': $filesize = $opts['s'];	break;
}

if (!isset($subnets) || (!isset($hosts) && !isset($leases))) {
  echo "not enough arguments.\n";
  return 1;
}

if (isset($leases)) {
	if ($fih = fopen($leases, 'r')) {
		if ($foh = fopen($output ?: 'php://output', 'r+')) {
			if (isset($output)) {
				ftruncate($foh, $filesize ?: 0);
				if (fseek($foh, 0, SEEK_END) !== 0)
					return 2;
			}
		
			fwrite($foh, "\n# dhcpleases automatically entered\n");
			
			$leases = array();
			while ($line = fgets($fih)) {
				if (preg_match('/^\s*lease\s+(?P<address>[a-zA-Z0-9\.]+)(?:\s*{)?$/', $line, $matches)) {
					$lease = array();
					$lease['addr'] = $matches['address'];
					
					// address must be in at least one of the given networks
					if (ip_in_subnets($lease['addr'], $subnets)) {
						$hostname = NULL;					
						$tts = NULL;
						$ttd = NULL;
						
						while ($line = fgets($fih)) {							
							// extract hostname
							if (preg_match('/.*(?:client-)?hostname\s+(?P<q>"?)(?P<hostname>[a-zA-Z0-9\-\/_\.]+)(?P=q)\s*;(?P<end>\s*})?/', $line, $matches)) {
								$hostname = canonicalise($matches['hostname']);
								if (!empty($matches['end']))
									break;
							}
							elseif (preg_match('/^\s*(?P<type>starts|ends)\s+\d\s+(?P<date>[\d\/]+)\s+(?P<time>[\d:]+)\s*;(?P<end>\s*})?/', $line, $matches)) {
								$type = $matches['type'];
								$date = $matches['date'];
								$time = $matches['time'];
								
								list($year, $month, $day) = sscanf($date, "%d/%d/%d");
								list($hour, $minute, $second) = sscanf($time, "%d:%d:%d:");
								
								$epoch = mktime($hour, $minute, $second, $month, $day, $year);
								if ($type == 'ends')
									$ttd = $epoch;
								elseif ($type == 'starts')
									$tts = $epoch;
								
								if (!empty($matches['end']))
									break;
							}
							elseif (preg_match('/}/', $line))
								break;
						}
						
						if (!$hostname)
							continue;
						
						$lease['expires'] = (!$ttd || ($tts && $ttd == $tts - 1)) ? NULL : $ttd;
						if ($lease['expires'] && $lease['expires'] - time() <= 0)
							continue;
						
						$suffix = strstr($hostname, '.');
						if (!$suffix || !$domain || preg_match('/^'.$domain.'$/i', $suffix)) {
							$suffix = $domain;
						}
						
						$lease['name'] = $hostname;
						$lease['fqdn'] = sprintf("%s.%s", $hostname, $suffix);
						
						$leases[$hostname] = $lease;
					}
				}
			}
			
			foreach ($leases as &$lease) {
				fprintf($foh, "%s\t%s %s\t\t# dynamic entry from dhcpd.leasesx\n", $lease['addr'],
								$lease['fqdn'] ? $lease['fqdn'] : "empty", $lease['name'] ? $lease['name'] : "empty");
			}
		} else {
			echo "cannot write to file ". $output ."\n";
			fclose($fih);
			return 1;
		}
	} else {
		echo "cannot open file ". $leases ."\n";
	}
} else {
	if ($fih = fopen($hosts, 'r')) {
		if ($foh = fopen(!isset($output) ? 'php://output' : $output, 'w')) {
			while ($line = fgets($fih)) {
				// copy comments and blank lines
				if (preg_match('/^(#.*)?$/', $line)) {
					fwrite($foh, $line);
					continue;
				}

				// copy iff ip in any of the given subnets
				$arr = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
				if (ip_in_subnets($arr[0], $subnets))
					fwrite($foh, $line);
			}
			fclose($foh);
			fclose($fih);
			if (isset($pid) && file_exists($pid))
				sigkillbypid($pid, "HUP");
		} else {
			echo "cannot write to file ". $output ."\n";
			fclose($fih);
			return 1;
		}
	} else {
		echo "cannot open file ". $hosts ."\n";
	}
}

return 0;

