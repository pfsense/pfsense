<?php
/*
	lb.php
	part of pfSense (www.pfSense.com)

	Copyright (C) 2005 Chris Dionissopoulos <chdio@bug.gr>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/*
global config data
~~~~~~~~~~~~~~~~~~
['lb'] --> unique,required
['lb']['enable'] --> unique , required, int(0|1)
['lb']['gateway'] --> ARRAY , optional
['lb']['gateway']['enable'] --> unique , required, int(0|1)
['lb']['gateway']['state']  --> unique , required, int(0|1)
['lb']['gateway']['gateway']  --> unique , required, string
['lb']['gateway']['monitor']  --> unique , required, string
['lb']['gateway']['weight']  --> unique , required, int(1..10)
['lb']['gateway']['default'] --> unique , optional, int(0|1)

*/

require_once("functions.inc");

	function addgateway($enable,$state,$gateway,$monitor,$weight,$default){
		global $config,$g;
		$exists = false;
		$lb = &$config['lb'];
		
		foreach($lb['gateway'] as $tgate)
			if ($gateway == $tgate['gateway']){
				$exists = true;
				break;
			}
		}
		if (!$exists) {
			$tgate = array();
			$tgate['enable'] = $enable;
			if ($enable) 
				$tgate['state'] = $this->getstate($monitor);
			else 
				$tgate['state'] = 0;
			$tgate['gateway'] = $gateway;
			$tgate['monitor'] = $monitor;
			$tgate['weight'] = $weight;
			if ($default==1) {
				$i=0;
				foreach($lb['gateway'] as $ttgate) {
					if (isset($this->lb['gateway'][$i]['default']))
						unset($this->lb['gateway'][$i]['default']);
					$i++;
				}
				$tgate['default'] = 1;
			} else 
				$tgate['default'] = 0;
			$lb['gateway'][] = $tgate;
			return true;
		} else {
			return "error: gateway exists!";
	}
		
	function edit_gateway($num,$enable,$state,$gateway,$monitor,$weight,$default){
		global $config,$g;
		$i=0;
		$exists = false;
		$lb = &$config['lb'];
		
		foreach($lb['gateway'] as $tgate)
			if ($gateway == $tgate['gateway'] && $i!=$num){
				$exists = true;
				break;
			}
			$i++;
		}
		if (!$exists) {
			$tgate = array();
			$tgate['enable'] = $enable;
			if ($enable) 
				$tgate['state'] = get_state($monitor);
			else 
				$tgate['state'] = 0;
			$tgate['gateway'] = $gateway;
			$tgate['monitor'] = $monitor;
			$tgate['weight'] = $weight;
			if ($default==1) {
				$i=0;
				foreach($lb['gateway'] as $ttgate){
					if (isset($lb['gateway'][$i]['default']))
						unset($lb['gateway'][$i]['default']);
					$i++;
				}
				$tgate['default'] = 1;
			} else 
				$tgate['default'] = 0;
			$lb['gateway'][$num] = $tgate;
			return true;
		} else {
			return "error: gateway exists!";
	}
	
	function get_state($ip){
		$pingcmd = exec("/usr/local/bin/sudo /sbin/ping -c1 -t1 -n ".$ip,$result);
		if (preg_match_all('/.*100\%\spacket\sloss.*/',$result[count($result)-1], $match))
			return 0;
		if (preg_match_all('/.*0\%\spacket\sloss.*/',$result[count($result)-2], $match))
			return 1;
	}
	
	function rules(){
		global $config,$g;
		$lb = &$config['lb'];
		$lancfg = $config['interfaces']['lan'];
		$wancfg = $config['interfaces']['wan'];
		$out_flow = ' ip from '.$lancfg['ipaddr'].'/'.$lancfg['subnet']
					.' to not '. $lancfg['ipaddr'].'/'.$lancfg['subnet'];
		$in_flow = 'ip from not '.$lancfg['ipaddr'].'/'.$lancfg['subnet']
					.' to '.$lancfg['ipaddr'].'/'.$lancfg['subnet'];
					
		$rules ="";
		$num = 1;
		$i=0;
		$gates = count($lb['gateway']);
		foreach($lb['gateway'] as $tgate){
			$num++;
			$in_num = 10000+$num;
			$out_num = 11000+$num;
			$skipto = 20000 + $num*10;
			$sw[$i] = 0;
			for($j=$i;$j<$gates;$j++)
				$sw[$i] +=$lb['gateway'][$j]['weight'];
			$prob = round($tgate['weight']/$sw[$i], 2);
					
			$rules .='/sbin/ipfw add $in_num set 5 skipto '.$skipto
					.' '.$in_flow.' mac any '.arp_get_mac_by_ip($tgate['gateway'])
					.' in recv '.$wancfg['if'].' keep-state\n';
					
			$rules .='/sbin/ipfw add $out_num set 6 prob '.$prob.' skipto '.$skipto
				.' '.$out_flow.' in recv '. $lancfg['if'].' keep-state\n';
					
			$rules .='/sbin/ipfw add $skipto set 6 fwd '.$tgate['gateway']
					.' '.$out_flow.' in recv '.$lancfg['if'].'\n';
					
			$skipto++;
			$rule .= '/sbin/ipfw add $skipto set 6 skipto 65535 ip from any to any\n';
			$i++;
		}
		return $rules;
	}
	
	


?>
