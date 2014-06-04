#!/usr/local/bin/php -q 
<?php
ini_set('apc.enabled', '0');

require_once("util.inc");

function ip2bin($ip) {
	if (is_ipaddrv4($ip))
		return base_convert(ip2long($ip), 10, 2);
	if (!is_ipaddrv6($ip))
		return false;
	if (($ip_n = inet_pton($ip)) === false)
		return false;

	# source: http://php.net/manual/en/function.ip2long.php#104163
	$ipbin = '';
	$bits = 16; // 16 x 8 bit = 128bit (ipv6)
	while (--$bits >= 0) {
		$bin = sprintf('%08b', (ord($ip_n[$bits])));
		$ipbin = $bin.$ipbin;
	}
	return $ipbin;
}

function addr_in_range($ip, $subnet) {
	list($net, $mask) = explode('/', $subnet);

	$ip  = ip2bin($ip);
	$net = ip2bin($net);
	return substr($ip, 0, $mask) === substr($net, 0, $mask);
}


$options = getopt("n:h:o:p:");

$subnets = $options['n'];
$hosts = $options['h'];
$output = $options['o'];
$pid = $options['p'];


if (!isset($subnets) || !isset($hosts)) {
  echo "not enough arguments.\n";
  return 1;
}

if (!is_array($subnets))
	$subnets = array($subnets);

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
			foreach ($subnets as &$network) {
				if (addr_in_range($arr[0], $network))
					fwrite($foh, $line);
			}
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

return 0;

