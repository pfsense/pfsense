<?php
/*
	filter.inc
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

/* include all configuration functions */
require_once("functions.inc");

function filter_resync() {
	global $config, $g;

	mwexec("/sbin/pfctl -y"); /* XXX */
}

function filter_ipmon_start() {
	global $config, $g;

	mwexec("ifconfig pflog0 up && pflogd -sD");
}

function filter_configure() {
	global $config, $g;

	if ($g['booting'])
		echo "Configuring firewall... ";

	/* set TCP timeouts */
	$tcpidletimeout = 9000;
	if ($config['filter']['tcpidletimeout'])
		$tcpidletimeout = $config['filter']['tcpidletimeout'];
	mwexec("/sbin/sysctl net.inet.ipf.fr_tcpidletimeout={$tcpidletimeout}");
	mwexec("/sbin/sysctl net.inet.ipf.fr_tcphalfclosed=480");

	/* generate pfctl rules */
	$natrules = filter_nat_rules_generate();
	/* generate pfctl rules */
	$pfrules = filter_rules_generate();
	/* generate altq interface setup parms */
	$altq_ints = filter_setup_altq_interfaces();
	/* generate altq queues */
	$altq_queues = filter_generate_altq_queues();

	if( !isset( $config['system']['disablefilter'] ) ) {
		mwexec("/sbin/pfctl -e");
		mwexec("/sbin/pfctl -F nat");
		mwexec("/sbin/pfctl -F rules");
	}

	/* get our wan interface? */
	$wanif = get_real_wan_interface();

        $fd = fopen("/tmp/rules.debug", "w");
	fwrite($fd, "set loginterface $wanif \n");
        fwrite($fd, "set optimization aggressive\n");
	fwrite($fd, "\nscrub in all\n");
	fwrite($fd, $altq_ints);
	fwrite($fd, $altq_queues);
	fwrite($fd, $natrules);
        fwrite($fd, $pfrules);
        fclose($fd);

	mwexec("chmod a+x /tmp/rules.debug");
	mwexec("/sbin/pfctl -f /tmp/rules.debug");

	/* set up MSS clamping */
	if ($config['interfaces']['wan']['mtu'])
		$mssclamp = $config['interfaces']['wan']['mtu'] - 40;
	else if ($config['interfaces']['wan']['ipaddr'] == "pppoe")
		$mssclamp = 1452;
	else
		$mssclamp = 0;

	mwexec("/sbin/sysctl net.inet.ipf.fr_mssif={$wanif}");
	mwexec("/sbin/sysctl net.inet.ipf.fr_mssclamp={$mssclamp}");

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function filter_get_altq_queue_scheduler_type($associatedrule) {
	global $config;
	$schedulertype = "";
	/* XXX: assign all the OPT interfaces */
	foreach ($config['filter']['rule'] as $rule) {
		if($rule['descr'] == $associatedrule) {
			if($rule['interface'] == "wan") $schedulertpye = $config['interfaces']['wan']['schedulertype'];
			if($rule['interface'] == "lan") $schedulertpye = $config['interfaces']['lan']['schedulertype'];
		}
	}
	return $schedulertpye;
}

function filter_get_rule_real_interface($associatedrule) {
	global $config;
	foreach ($config['filter']['rule'] as $rule) {
		if($rule['descr'] == $associatedrule) {
			$interface = $rule['interface'];
			return $config['interfaces'][$interface]['if'];
		}
	}
	return "";
}


function filter_get_rule_interface($associatedrule) {
	global $config;
	foreach ($config['filter']['rule'] as $rule) {
		if($rule['descr'] == $associatedrule) return $rule['interface'];
	}
	return "";
}

function filter_generate_altq_queues() {
	global $config;
	$altq_rules = "";
	if (is_array($config['pfqueueing']['queue'])) {
		foreach ($config['pfqueueing']['queue'] as $rule) {
			$options = "";
			$altq_rules .= "queue " . $rule['name'] . " ";
			if (isset($rule['bandwidth']) and $rule['bandwidth'] <> "")
				$altq_rules .= "bandwidth " . $rule['bandwidth'] . $rule['bandwidthtype'] . " ";
			if (isset($rule['priority']) and $rule['priority'] <> "")
				$altq_rules .= "priority " .  $rule['priority']  . " ";
			if(isset($rule['options']['red']) and $rule['options']['red'] <> "")
				$options .= " red";
			if(isset($rule['options']['borrow']) and $rule['options']['borrow'] <> "")
				$options .= " borrow";
			if(isset($rule['options']['ecn']) and $rule['options']['ecn'] <> "")
				$options .= " ecn";
			if(isset($rule['options']['rio']) and $rule['options']['rio'] <> "")
				$options .= " rio";
			if(isset($rule['options']['defaultqueue']) and $rule['options']['defaultqueue'] <> "")
				$options .= " default";
			if(isset($rule['options']['upperlimit']) and $rule['options']['upperlimit'] <> "") {
				$options .= " upperlimit(" . $rule['options']['upperlimit1'] . " " . $rule['options']['upperlimit2'] . " " . $rule['options']['upperlimit3'] . ")";
			}
			if(isset($rule['options']['linkshare']) and $rule['options']['linkshare'] <> "") {
				$options .= " linkshare(" . $rule['options']['linkshare1'] . " " . $rule['options']['linkshare2'] . " " . $rule['options']['linkshare3'] . ")";
			}
			if(isset($rule['options']['realtime']) and $rule['options']['realtime'] <> "") {
				$options .= " realtime(" . $rule['options']['realtime1'] . " " . $rule['options']['realtime2'] . " " . $rule['options']['realtime3'] . ")";
			}
			if($options) {
				$scheduler_type = filter_get_altq_queue_scheduler_type($rule['options']['associatedrule']);
				$altq_rules .= $scheduler_type . "(". $options . " )";
			}
			if (isset($rule['subqueue'])) {
				$altq_rules .= "{ ";
				$fsq = "";
				foreach ($rule['subqueue'] as $sq) {
					if($fsq) $altq_rules .= ",";
					$altq_rules .= $sq['name'];
					$fsq = "1";
				}
				$altq_rules .= " }";
			}
			$altq_rules .= "\n";
		}
	}
	return $altq_rules;
}

function filter_setup_altq_interfaces() {
	global $config;
	$altq_rules  = "";
	$queue_names = "";
	$is_first = "";
	$queue_names = "";
	$is_first = "";
	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $ifname) {
			if (is_array($config['pfqueueing']['queue'])) {
				foreach ($config['pfqueueing']['queue'] as $queue) {
					$schedulertype = $ifname['schedulertype'];
					if(filter_get_altq_queue_scheduler_type($queue['options']['associatedrule']) == $schedulertype) {
						if (filter_get_rule_real_interface($queue['options']['associatedrule']) == $ifname['if']) {
							if(is_subqueue($queue['name']) == 0) {
								if($is_first) $queue_names .= ", ";
								$queue_names .= $queue['name'];
								$is_first = "1";
							}
						}
					}
				}
			}
			if(isset($ifname['bandwidth'])) {
				$subnet = $ifname['ipaddr'] . "/" . $ifname['subnet'];
				if($queue_names <> ""){
					$altq_rules .= "altq on " . $ifname['if'] . " ";
					$altq_rules .=  $ifname['schedulertype'] . " bandwidth " . $ifname['bandwidth'] . $ifname['bandwidthtype'] . " ";
					$altq_rules .= "queue { " . $queue_names . " }";
				}
				$altq_rules .= "\n";
				$is_first = "";
				$queue_names = "";
			}
		}
	}
	return $altq_rules;
}

function is_subqueue($name) {
        global $config;
        $status = "";
        if (is_array($config['pfqueueing']['queue'])) {
                foreach ($config['pfqueueing']['queue'] as $queue) {
                        if(is_array($queue['subqueue'])) {
                                foreach ($queue['subqueue'] as $sq) {
                                        if($sq['name'] == $name) return 1;
                                }
                        }
                }
        }
        return 0;
}


function filter_flush_nat_table() {
	global $config, $g;

	return mwexec("/sbin/pfctl -F nat");
}

function filter_flush_state_table() {
	global $config, $g;

	return mwexec("/sbin/pfctl -F state");
}

function filter_nat_rules_generate_if($if, $src, $dst, $target) {
	global $config;

	if ($target)
		$tgt = $target . "/32";
	else
		$tgt = "0/32";

	$natrule = <<<EOD
nat on $if from $src to any -> ($if)

EOD;
	return $natrule;
}

function filter_nat_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];

	$pptpdcfg = $config['pptpd'];
	$wanif = get_real_wan_interface();

	$lanif = $config['interfaces']['lan']['if'];

	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);

	$natrules = "";

	/* any 1:1 mappings? */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if (!is_numeric($natent['subnet']))
				$sn = 32;
			else
				$sn = $natent['subnet'];

			if (!$natent['interface'] || ($natent['interface'] == "wan"))
				$natif = $wanif;
			else
				$natif = $config['interfaces'][$natent['interface']]['if'];

			$natrules .= "binat on {$natif} from {$natent['internal']}/{$sn} to any -> {$natent['external']}\n";
		}
	}

	/* Setup pf groups */
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			$natrules .= $alias['name'] . " = \"{ " . $alias['address'] . " }\"\n";
		}
	}

	/* Setup outgoing NAT load balancing */
	if (isset($config['nat']['outgoingloadbalancing']['rule'])) {
		$tmprules .= "pass in on " . $lanif . " route-to { ";
		$isfirst = 0;
		foreach ($config['nat']['outgoingloadbalancing']['rule'] as $rule) {
			if($rule['gatewayip'] <> "") {
				if(isfirst == 1) $tmprules .= ", ";
				$tmprules .= " (" . $wanif . " " . $rule['gatewayip'] . ") ";
				$isfirst = 1;
			}
		}
		$tmprules .= "} round-robin proto tcp from " . $lansa ." to any flags S/SA modulate state\n";
		if($isfirst == 1) $natrules .= $tmprules;
	}

	/* outbound rules - advanced or standard */
	if (isset($config['nat']['advancedoutbound']['enable'])) {
		/* advanced outbound rules */
		if (is_array($config['nat']['advancedoutbound']['rule'])) {
			foreach ($config['nat']['advancedoutbound']['rule'] as $obent) {
				$dst = "";
				$src = "";
				if (!isset($obent['destination']['any'])) {
					$src = "from ";
					if (isset($obent['destination']['not']))
						$dst = "! to ";
					else
						$dst = "to ";
					$dst .= $obent['destination']['network'];
				}
				$src .= $obent['source']['network'];

				if (!$obent['interface'] || ($obent['interface'] == "wan"))
					$natif = $wanif;
				else
					$natif = $config['interfaces'][$obent['interface']]['if'];

				$natrules .= filter_nat_rules_generate_if($natif, $src, $dst,
					$obent['target']);
			}
		}
	} else {
		/* standard outbound rules (one for each interface) */
		$natrules .= filter_nat_rules_generate_if($wanif,
			$lansa . "/" . $lancfg['subnet'], "", null);

		/* optional interfaces */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			$optcfg = $config['interfaces']['opt' . $i];

			if (isset($optcfg['enable']) && !$optcfg['bridge']) {
				$optsa = gen_subnet($optcfg['ipaddr'], $optcfg['subnet']);
				$natrules .= filter_nat_rules_generate_if($wanif,
					$optsa . "/" . $optcfg['subnet'], "", null);
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$natrules .= filter_nat_rules_generate_if($wanif,
				$pptpdcfg['remoteip'] . "/" . $g['pptp_subnet'], "", null);
		}

		/* static routes */
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['interface'] != "wan")
					$natrules .= filter_nat_rules_generate_if($wanif,
						$route['network'], "", null);
			}
		}
	}

	/* DIAG: add ipv6 NAT, if requested */
	if (isset($config['diag']['ipv6nat']['enable'])) {
		$natrules .= "rdr on $wanif proto ipv6 from any to any port 0 -> " .
		"{$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	if (isset($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $rule) {

			$extport = explode("-", $rule['external-port']);
			$target = alias_expand_host($rule['target']);

			if (!$target)
				continue;	/* unresolvable alias */

			if ($rule['external-address'])
				$extaddr = $rule['external-address'] . "/32";
			else
				$extaddr = "0/0";

			if (!$rule['interface'] || ($rule['interface'] == "wan"))
				$natif = $wanif;
			else
				$natif = $config['interfaces'][$rule['interface']]['if'];

			$lanif = $lancfg['if'];

			if ((!$extport[1]) || ($extport[0] == $extport[1])) {
				$natrules .=
				 "rdr on $natif proto " . $rule['protocol'] . " from any to any port {$extport[0]} -> {$target} \n";
			} else {
				$natrules .=
				 "rdr on $natif proto " . $rule['protocol']. " from any to any port {$extport[0]}:{$extport[1]} " .
				 "-> {$target} \n";
			}

			$natrules .= "\n";
		}
	}

	if ($pptpdcfg['mode'] && $pptpdcfg['mode'] != "off") {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else if ($pptpdcfg['mode'] == "redir")
			$pptpdtarget = $pptpdcfg['redir'];

		if ($pptpdtarget) {

		$natrules .= <<<EOD

# PPTP
rdr on $wanif proto gre from any to any port 0 -> $pptpdtarget
rdr on $wanif proto tcp from any to any port 1723 -> $pptpdtarget

# auto-proxy
rdr on $lanif proto tcp from any to any port 21 -> 127.0.0.1 port 8021

EOD;
		}
	}

	return $natrules;
}

function filter_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	/* rule groups (optional interfaces: see below) */
	$ifgroups = array("lan" => 100, "wan" => 200);

	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	/* optional interfaces */
	$optcfg = array();

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		$oc = $config['interfaces']['opt' . $i];

		if (isset($oc['enable']) && $oc['if']) {
			$oic = array();
			$oic['if'] = $oc['if'];

			if ($oc['bridge']) {
				if (!strstr($oc['bridge'], "opt") ||
					 isset($config['interfaces'][$oc['bridge']]['enable'])) {
					if (is_ipaddr($config['interfaces'][$oc['bridge']]['ipaddr'])) {
						$oic['ip'] = $config['interfaces'][$oc['bridge']]['ipaddr'];
						$oic['sn'] = $config['interfaces'][$oc['bridge']]['subnet'];
						$oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
					}
				}
				$oic['bridge'] = 1;
			} else {
				$oic['ip'] = $oc['ipaddr'];
				$oic['sn'] = $oc['subnet'];
				$oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
			}

			$optcfg['opt' . $i] = $oic;
			$ifgroups['opt' . $i] = ($i * 100) + 200;
		}
	}

	if ($pptpdcfg['mode'] == "server") {
		$pptpip = $pptpdcfg['localip'];
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
	}

	/* default block logging? */
	if (!isset($config['syslog']['nologdefaultblock']))
		$log = "log";
	else
		$log = "";

	# BEGIN OF firewall rules
	$ipfrules="";
	$ipfrules .= <<<EOD

# loopback
pass in quick on lo0 all
pass out quick on lo0 all

# enable ftp-proxy
pass in on $wanif inet proto tcp from port 20 to ($wanif) user proxy flags S/SA keep state

# allow access to DHCP server on LAN
pass in quick on $lanif proto udp from any port = 68 to 255.255.255.255 port = 67
pass in quick on $lanif proto udp from any port = 68 to $lanip port = 67
pass out quick on $lanif proto udp from $lanip port = 67 to any port = 68

EOD;

	/* allow access to DHCP server on optional interfaces */
	foreach ($optcfg as $on => $oc) {
		if (isset($config['dhcpd'][$on]['enable']) && (!$oc['bridge'])) {
			$ipfrules .= <<<EOD

# allow access to DHCP server on {$on}
pass in quick on {$oc['if']} proto udp from any port = 68 to 255.255.255.255 port = 67
pass in quick on {$oc['if']} proto udp from any port = 68 to {$oc['ip']} port = 67
pass out quick on {$oc['if']} proto udp from {$oc['ip']} port = 67 to any port = 68
pass out on {$oc['if']} proto icmp from any to any keep state

EOD;
		}
	}

	/* pass traffic between statically routed subnets and the subnet on the
	   interface in question to avoid problems with complicated routing
	   topologies */
	if (is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			unset($sa);

			if ($route['interface'] == "lan") {
				$sa = $lansa;
				$sn = $lansn;
				$if = $lanif;
			} else if (strstr($route['interface'], "opt")) {
				$oc = $optcfg[$route['interface']];
				if ($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
			}

			if ($sa) {
				$ipfrules .= <<<EOD
pass in quick on {$if} from {$sa}/{$sn} to {$route['network']}
pass in quick on {$if} from {$route['network']} to {$sa}/{$sn}
pass out quick on {$if} from {$sa}/{$sn} to {$route['network']}
pass out quick on {$if} from {$route['network']} to {$sa}/{$sn}

EOD;
			}
		}
	}

	$ipfrules .= <<<EOD

# WAN spoof check
block in $log quick on $wanif from $lansa/$lansn to any

EOD;

	foreach ($optcfg as $oc) {
		if (!$oc['bridge'])
			$ipfrules .= "block in $log quick on $wanif from {$oc['sa']}/{$oc['sn']} to any\n";
	}

	/* allow PPTP traffic if PPTP client is enabled on WAN */
	if ($wancfg['ipaddr'] == "pptp") {
		$ipfrules .= <<<EOD

# allow PPTP client
pass in quick on {$wancfg['if']} proto gre from any to any
pass out quick on {$wancfg['if']} proto gre from any to any
pass in quick on {$wancfg['if']} proto tcp from any port = 1723 to any
pass out quick on {$wancfg['if']} proto tcp from any to any port = 1723

EOD;
	}

	$ipfrules .= <<<EOD

# allow our DHCP client out to the WAN
# XXX - should be more restrictive
# (not possible at the moment - need 'me' like in ipfw)
pass out quick on $wanif proto udp from any port = 68 to any port = 67
block in $log quick on $wanif proto udp from any port = 67 to $lansa/$lansn port = 68
pass in quick on $wanif proto udp from any port = 67 to any port = 68

# LAN/OPT spoof check (needs to be after DHCP because of broadcast addresses)

EOD;

	/* LAN spoof check */
	$ipfrules .= filter_rules_spoofcheck_generate('lan', $lanif, $lansa, $lansn, $log);

	/* OPT spoof check */
	foreach ($optcfg as $on => $oc) {
		if ($oc['ip'])
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);
	}

	/* block private networks on WAN? */
	if (isset($config['interfaces']['wan']['blockpriv'])) {
		$ipfrules .= <<<EOD

# block anything from private networks on WAN interface
block in $log quick on $wanif from 10.0.0.0/8 to any
block in $log quick on $wanif from 127.0.0.0/8 to any
block in $log quick on $wanif from 172.16.0.0/12 to any
block in $log quick on $wanif from 192.168.0.0/16 to any

EOD;

	}

	/* IPsec enabled? */
	if (isset($config['ipsec']['enable']) &&
		((is_array($config['ipsec']['tunnel']) &&
		count($config['ipsec']['tunnel'])) ||
			isset($config['ipsec']['mobileclients']['enable']))) {

		$curwanip = get_current_wan_address();

		if ($curwanip)
			$ipfrules .= filter_rules_ipsec_generate($wanif, $curwanip);

		$ipfrules .= filter_rules_ipsec_generate($lanif, $lanip);

		foreach ($optcfg as $on => $oc) {
			if ($oc['ip'])
				$ipfrules .= filter_rules_ipsec_generate($oc['if'], $oc['ip']);
		}
	}

	$ipfrules .= <<<EOD


# let out anything from the firewall host itself and decrypted IPsec traffic
pass out quick on $wanif all keep state

EOD;

	/* group heads for optional interfaces */
	foreach ($optcfg as $on => $oc) {

		$ingroup = $ifgroups[$on];

		$ipfrules .= <<<EOD


# let out anything from the firewall host itself and decrypted IPsec traffic
pass out quick on {$oc['if']} all keep state

EOD;

	}

	if (!isset($config['system']['webgui']['noantilockout'])) {

	$ipfrules .= <<<EOD

# make sure the user cannot lock himself out of the webGUI
pass in quick from $lansa/$lansn to $lanip keep state
# group 100

EOD;
	}

	/* PPTPd enabled? */
	if ($pptpdcfg['mode'] && ($pptpdcfg['mode'] != "off")) {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else
			$pptpdtarget = $pptpdcfg['redir'];

		$ipfrules .= <<<EOD

# PPTP rules
pass in quick proto gre from any to $pptpdtarget keep state
# group 200
pass in quick proto tcp from any to $pptpdtarget port = 1723 keep state
# group 200

EOD;
	}

	/* BigPond client enabled? */
	if ($wancfg['ipaddr'] == "bigpond") {

		$ipfrules .= <<<EOD

# BigPond heartbeat rules
pass in quick proto udp from any to any port = 5050 keep state
# group 200

EOD;
	}

	$i = 0;

	$ipfrules .= "\n# User-defined rules follow\n";

	if (isset($config['filter']['rule']))
		foreach ($config['filter']['rule'] as $rule) {

		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			$i++;
			continue;
		}

		/* does the rule deal with a PPTP interface? */
		if ($rule['interface'] == "pptp") {

			if ($pptpdcfg['mode'] != "server") {
				$i++;
				continue;
			}

			$nif = $g['n_pptp_units'];
			$ispptp = true;
		} else {

			if (strstr($rule['interface'], "opt")) {
				if (!array_key_exists($rule['interface'], $optcfg)) {
					$i++;
					continue;
				}
			}

			$nif = 1;
			$ispptp = false;
		}

		if ($pptpdcfg['mode'] != "server") {
			if (($rule['source']['network'] == "pptp") ||
				($rule['destination']['network'] == "pptp")) {
					$i++;
					continue;
				}
		}

		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				$i++;
				continue;
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $optcfg)) {
				$i++;
				continue;
			}
		}

		/* check for unresolvable aliases */
		if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
			$i++;
			continue;
		}
		if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
			$i++;
			continue;
		}

		for ($iif = 0; $iif < $nif; $iif++) {

			if (!$ispptp) {

				$groupnum = $ifgroups[$rule['interface']];

				if (!$groupnum) {
					printf("Invalid interface name in rule $i\n");
					break;
				}
			}

			$type = $rule['type'];
			if ($type != "pass" && $type != "block" && $type != "reject") {
				/* default (for older rules) is pass */
				$type = "pass";
			}

			if ($type == "reject") {
				/* special reject packet */
				if ($rule['protocol'] == "tcp") {
					$line = "block return-rst";
				} else if ($rule['protocol'] == "udp") {
					$line = "block return-icmp";
				} else {
					$line = "block";
				}
			} else {
				$line = $type;
			}

			if(!isset($rule['direction'])) {
				$line .= " in ";
			} else {
				$line .= " " . $rule['direction'] . " ";
			}

			if (isset($rule['log']))
				$line .= "log ";

			$line .= "quick ";

			if ($ispptp) {
				$line .= "on ng" . ($iif+1) . " ";
			}

			if (isset($rule['protocol'])) {
				$line .= "proto {$rule['protocol']} ";
			}

			/* source address */
			if (isset($rule['source']['any'])) {
				$src = "any";
			} else if ($rule['source']['network']) {

				if (strstr($rule['source']['network'], "opt")) {
					$src = $optcfg[$rule['source']['network']]['sa'] . "/" .
						$optcfg[$rule['source']['network']]['sn'];
				} else {
					switch ($rule['source']['network']) {
						case 'lan':
							$src = "$lansa/$lansn";
							break;
						case 'pptp':
							$src = "$pptpsa/$pptpsn";
							break;
					}
				}
			} else if ($rule['source']['address']) {
				$src = alias_expand($rule['source']['address']);
			}

			if (!$src || ($src == "/")) {
				//printf("No source address found in rule $i\n");
				break;
			}

			if (isset($rule['source']['not'])) {
				$line .= "from !$src ";
			} else {
				$line .= "from $src ";
			}

			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {

				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);

					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						$line .= "port = {$srcport[0]} ";
					} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($srcport[1] == 65535) {
						$line .= "port >= {$srcport[0]} ";
					} else if ($srcport[0] == 1) {
						$line .= "port <= {$srcport[1]} ";
					} else {
						$srcport[0]--;
						$srcport[1]++;
						$line .= "port {$srcport[0]} >< {$srcport[1]} ";
					}
				}
			}

			/* destination address */
			if (isset($rule['destination']['any'])) {
				$dst = "any";
			} else if ($rule['destination']['network']) {

				if (strstr($rule['destination']['network'], "opt")) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
				} else {
					switch ($rule['destination']['network']) {
						case 'lan':
							$dst = "$lansa/$lansn";
							break;
						case 'pptp':
							$dst = "$pptpsa/$pptpsn";
							break;
					}
				}
			} else if ($rule['destination']['address']) {
				$dst = alias_expand($rule['destination']['address']);
			}

			if (!$dst || ($dst == "/")) {
				//printf("No destination address found in rule $i\n");
				break;
			}

			if (isset($rule['destination']['not'])) {
				$line .= "to !$dst ";
			} else {
				$line .= "to $dst ";
			}

			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {

				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);

					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						$line .= "port = {$dstport[0]} ";
					} else if (($dstport[0] == 1) && ($dstport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($dstport[1] == 65535) {
						$line .= "port >= {$dstport[0]} ";
					} else if ($dstport[0] == 1) {
						$line .= "port <= {$dstport[1]} ";
					}  else {
						$dstport[0]--;
						$dstport[1]++;
						$line .= "port {$dstport[0]} >< {$dstport[1]} ";
					}
				}
			}

			if (($rule['protocol'] == "icmp") && $rule['icmptype']) {
				$line .= "icmp-type {$rule['icmptype']} ";
			}

			if ($type == "pass") {

				if( isset($rule['source-track']) or isset($rule['max-src-nodes']) or isset($rule['max-src-states']) )
					$line .= "flags S/SA ";

				/*
					# keep state
						works with TCP, UDP, and ICMP.
					# modulate state
						works only with TCP. pfSense will generate strong Initial Sequence Numbers (ISNs)
						for packets matching this rule.
					# synproxy state
						proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods.
						This option includes the functionality of keep state and modulate state combined.
					# none
						do not use state mechanisms to keep track. this is only useful if your doing advanced
						queueing in certain situations. please check the faq.
				*/
				if( !isset($rule['statetype'] ) ) {
					$line .= "keep state ";
				} else {
					if($rule['statetype'] == "modulate state" or $rule['statetype'] == "synproxy state") {
						if($rule['protocol'] == "tcp")
							$line .= $rule['statetype'] ;
					} else {
						if(!$rule['statetype'] == "none")
							$line .= $rule['statetype'] ;
					}
				}

				if( isset($rule['source-track']) or isset($rule['max-src-nodes']) or isset($rule['max-src-states'] ) ) {
					$line .= "( ";
					if(isset($rule['source-track']))
						$line .= "source-track rule ";
					if(isset($rule['max-src-nodes']))
						$line .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
					if(isset($rule['max-src-states']))
						$line .= "max-src-states" . $rule['max-src-states'] . " ";
					$line .= " ) ";
				}

				if (isset($rule['frags']))
					$line .= "keep frags ";
			}

			if ($type == "reject" && $rule['protocol'] == "tcp") {
				/* special reject packet */
				$line .= "flags S/SA ";
			}

			if (isset($rule['flags'])) {
				$line .= "flags " . $rule['flags'] . " ";
			}

			if (!$ispptp) {
				#$line .= "group $groupnum ";
			}

			if(isset($config['pfqueueing']['queue'])) {
				foreach ($config['pfqueueing']['queue'] as $que) {
					if($que['options']['associatedrule'] == $rule['descr']) {
						$line .= "queue " . $que['name'];
						break;
					}
				}
			}

			$line .= "\n";

			$ipfrules .= $line;
		}

		$i++;
	}

	$ipfrules .= "\n# VPN Rules\n";
	$lan_ip = $config['interfaces']['lan']['ipaddr'];
	$lan_subnet = $config['interfaces']['lan']['subnet'];
	$wan_ip = $config['interfaces']['wan']['ipaddr'];
	$internal_subnet = gen_subnet($lan_ip, $lan_subnet) . "/" . $config['interfaces']['lan']['subnet'];
	if(is_array($config['ipsec']['tunnel'])) {
		foreach ($config['ipsec']['tunnel'] as $tunnel) {
			$remote_gateway = $tunnel['remote-gateway'];
			$ipfrules .= "pass quick on " . $wanif . " from " . $wan_ip . " to " . $remote_gateway . " keep state\n";
			$ipfrules .= "pass quick on " . $wanif . " from " . $remote_gateway . " to " . $wan_ip  . " keep state\n";
			$ipfrules .= "pass quick on " . $lanif . " from " . $tunnel['remote-subnet'] . " to " . $internal_subnet  . " keep state\n";
			$ipfrules .= "pass quick on " . $lanif . " from " . $internal_subnet . " to " . $tunnel['remote-subnet'] . " keep state\n";
		}
	}

	$ipfrules .= <<<EOD

#---------------------------------------------------------------------------
# default rules (just to be sure)
#---------------------------------------------------------------------------
block in $log quick all
block out $log quick all

EOD;

	return $ipfrules;
}

function filter_rules_spoofcheck_generate($ifname, $if, $sa, $sn, $log) {

	global $g, $config;

	$ipfrules = "";

	if (is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		/* count rules */
		$n = 1;
		foreach ($config['staticroutes']['route'] as $route) {
			if ($route['interface'] == $ifname)
				$n++;
		}

		/* output skip rules */
		foreach ($config['staticroutes']['route'] as $route) {
			if ($route['interface'] == $ifname) {
				//$ipfrules .= "skip $n in on $if from {$route['network']} to any\n";
				$n--;
			}
		}
		$ipfrules .= "#skip 1 in on $if from $sa/$sn to any\n";
		$ipfrules .= "#block in $log quick on $if all\n";
	} else {
		$ipfrules .= "#block in $log quick on $if from ! $sa/$sn to any\n";
	}

	return $ipfrules;
}

function filter_rules_ipsec_generate($ifname, $ip) {

	$ipfrules = <<<EOD

# Pass IKE packets
pass in quick on {$ifname} proto udp from any to {$ip} port = 500
pass out quick on {$ifname} proto udp from {$ip} port = 500 to any

# Pass ESP packets
pass in quick on {$ifname} proto esp from any to {$ip}
pass out quick on {$ifname} proto esp from {$ip} to any

# Pass AH packets
pass in quick on {$ifname} proto ah from any to {$ip}
pass out quick on {$ifname} proto ah from {$ip} to any

EOD;

	return $ipfrules;
}

?>
