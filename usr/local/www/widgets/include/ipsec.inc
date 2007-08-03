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
?>