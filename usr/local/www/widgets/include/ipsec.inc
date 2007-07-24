<?php
//set variable for custom title
$ipsec_title = "IPSec";

function get_ipsec_tunnel_src($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	if ($tunnel['interface']) {
		$iflabels = array('lan' => 'LAN', 'wan' => 'WAN');
 		$carpips = find_number_of_needed_carp_interfaces();
	    for($j=0; $j<$carpips; $j++) {
   			$interfaceip = find_interface_ip("carp" . $j);
  			$iflabels['carp' . $j] = $interfaceip; 
		}
		for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
			$realinterface = convert_friendly_interface_to_real_interface_name('opt' . $j);
			$iflabels['opt' . $j] = find_interface_ip($realinterface);
		}
		$interfaceip = $iflabels[$tunnel['interface']];
	} else {
		$realinterface = convert_friendly_interface_to_real_interface_name($if);
		$interfaceip = find_interface_ip($realinterface);
	}
	return $interfaceip;
}

function output_ipsec_tunnel_status($tunnel) {
	global $g, $config, $sad;
	$if = "WAN";
	$interfaceip = get_ipsec_tunnel_src($tunnel);
	$interfaceip = strtolower($interfaceip); 
	
	$foundsrc = false;
	$founddst = false;
	foreach($sad as $sa) {
		if (!$foundsrc){
			$sourceIF = find_ip_interface($sa['src']);
			$sourceIF = convert_real_interface_to_friendly_interface_name($sourceIF);
			
			if($sourceIF == $tunnel['interface'])
				$foundsrc = true;
		}
		if($sa['dst'] == $tunnel['remote-gateway']) 
			$founddst = true;
	}
	if($foundsrc && $founddst) { 
		/* tunnel is up */
		$iconfn = true;
	} else {
		/* tunnel is down */
		$iconfn = false;
	}
	return $iconfn;
}
?>