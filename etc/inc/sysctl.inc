<?php

$sysctls = array("net.inet.ip.portrange.first" => "1024", 
				 "net.inet.tcp.blackhole" => "2", 
				 "net.inet.udp.blackhole" => "1", 
				 "net.inet.ip.random_id" => "1", 
				 "net.inet.tcp.drop_synfin" => "1", 
				 "net.inet.ip.redirect" => "1", 
				 "net.inet6.ip6.redirect" => "1", 
				 "net.inet.tcp.syncookies" => "1", 
				 "net.inet.tcp.recvspace" => "65228", 
				 "net.inet.tcp.sendspace" => "65228", 
				 "net.inet.ip.fastforwarding" => "1", 
				 "net.inet.tcp.delayed_ack" => "0", 
				 "net.inet.udp.maxdgram" => "57344", 
				 "net.link.bridge.pfil_onlyip" => "0", 
				 "net.link.bridge.pfil_member" => "1", 
				 "net.link.bridge.pfil_bridge" => "0", 
				 "net.link.tap.user_open" => "1", 
				 "kern.rndtest.verbose" => "0", 
				 "kern.randompid" => "347", 
				 "net.inet.ip.intr_queue_maxlen" => "1000", 
				 "hw.syscons.kbd_reboot" => "0", 
				 "net.inet.tcp.inflight.enable" => "1", 
				 "net.inet.tcp.log_debug" => "0", 
				 "net.inet.icmp.icmplim" => "750", 
				 "net.inet.tcp.tso" => "0", 
				 "hw.bce.tso_enable" => "0"
				  );

function get_default_sysctl_value($id) {
	global $sysctls;
	foreach($sysctls as $sysctl => $value) {
		if($sysctl == $id)
			return $value;
	}
}


?>