<?php
//set variable for custom title
$ipsec_title = "IPsec";

function get_ipsec_tunnel_sad() {
	/* query SAD */
	$fd = @popen("/usr/local/sbin/setkey -D", "r");
	$sad = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line)
				continue;
			if ($line == "No SAD entries.")
				break;
			if ($line[0] != "\t") {
				if (is_array($cursa))
					$sad[] = $cursa;
				$cursa = array();
				list($cursa['src'],$cursa['dst']) = explode(" ", $line);
				$i = 0;
			} else {
				$linea = explode(" ", trim($line));
				if ($i == 1) {
					$cursa['proto'] = $linea[0];
					$cursa['spi'] = substr($linea[2], strpos($linea[2], "x")+1, -1);
				} else if ($i == 2) {
					$cursa['ealgo'] = $linea[1];
				} else if ($i == 3) {
					$cursa['aalgo'] = $linea[1];
				}
			}
			$i++;
		}
		if (is_array($cursa) && count($cursa))
			$sad[] = $cursa;
		pclose($fd);
	}
	return($sad);
}

function get_ipsec_tunnel_src($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	if ($tunnel['interface']) {
		$if = $tunnel['interface'];
		$realinterface = convert_friendly_interface_to_real_interface_name($if);
		$interfaceip = find_interface_ip($realinterface);
	}
	return $interfaceip;
}

function output_ipsec_tunnel_status($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	$interfaceip = get_ipsec_tunnel_src($tunnel);
	$foundsrc = false;
	$founddst = false;

	if(!is_array($sad)) {
		/* we have no sad array, bail */
		return(false);
	}
	foreach($sad as $sa) {
		if($sa['src'] == $interfaceip) 
			$foundsrc = true;
		if($sa['dst'] == $tunnel['remote-gateway']) 
			$founddst = true;
	}
	if($foundsrc && $founddst) { 
		/* tunnel is up */
		$iconfn = "pass";
		return(true);
	} else {
		/* tunnel is down */
		$iconfn = "reject";
		return(false);
	}
}

?>
