<?php
/*
	shaper.inc
	part of m0n0wall (http://m0n0.ch/wall)

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

function shaper_configure() {
	global $config, $g;

	if (isset($config['pfqueueing']['enable'])) {

		if ($g['booting'])
			echo "Starting traffic shaper... ";

		/* generate shaper rules */
		$shaperrules = shaper_rules_generate();

		/* make sure ipfw and dummynet are loaded */
		mwexec("/sbin/kldload ipfw");
		mwexec("/sbin/kldload dummynet");

		/* change one_pass to 1 so ipfw stops checking after
		   a rule has matched */
		mwexec("/sbin/sysctl net.inet.ip.fw.one_pass=1");

		/* load shaper rules */
		mwexec("/sbin/ipfw -f delete set 4");
		mwexec("/sbin/ipfw -f pipe flush");

		/* XXX - seems like ipfw cannot accept rules directly on stdin,
		   so we have to write them to a temporary file first */
		$fd = fopen("{$g['tmp_path']}/ipfw.rules", "w");
		if (!$fd) {
			printf("Cannot open ipfw.rules in shaper_configure()\n");
			return 1;
		}

		fwrite($fd, $shaperrules);
		fclose($fd);

		mwexec("/sbin/ipfw {$g['tmp_path']}/ipfw.rules");

		unlink("{$g['tmp_path']}/ipfw.rules");

		/* make sure bridged packets are shaped as well */
		mwexec("/sbin/sysctl net.link.ether.bridge_ipfw=1");

		if ($g['booting'])
			echo "done\n";

	} else {
		mwexec("/sbin/sysctl net.link.ether.bridge_ipfw=0");
		if (!isset($config['captiveportal']['enable'])) {
			/* unload ipfw and dummynet */
			#mwexec("/sbin/kldunload dummynet");
			#mwexec("/sbin/kldunload ipfw");
		} else {
			/* captive portal is on - just remove our rules */
			mwexec("/sbin/ipfw -f delete set 4");
			mwexec("/sbin/ipfw -f pipe flush");
		}
	}

	return 0;
}

function shaper_rules_generate() {
	global $config, $g;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	/* optional interfaces */
	$optcfg = array();

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		$oc = $config['interfaces']['opt' . $i];

		if (isset($oc['enable']) && $oc['if']) {
			$oic = array();
			$oic['ip'] = $oc['ipaddr'];
			$oic['if'] = $oc['if'];
			$oic['sa'] = gen_subnet($oc['ipaddr'], $oc['subnet']);
			$oic['sn'] = $oc['subnet'];

			$optcfg['opt' . $i] = $oic;
		}
	}

	if ($pptpdcfg['mode'] == "server") {
		$pptpip = $pptpdcfg['localip'];
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
	}

	$rulei = 50000;

	/* add a rule to pass all traffic from/to the firewall,
	   so the user cannot lock himself out of the webGUI */
	$shaperrules = "add $rulei set 4 pass all from $lanip to any\n"; $rulei++;
	$shaperrules .= "add $rulei set 4 pass all from any to $lanip\n"; $rulei++;

	/* generate rules */
	if (isset($config['pfqueueing']['rule']))
		foreach ($config['pfqueueing']['rule'] as $rule) {

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

		if (strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				$i++;
				continue;
			}
		}
		if (strstr($rule['destination']['network'], "opt")) {
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

			/* pipe or queue? */
			if (isset($rule['targetpipe']) && isset($config['pfqueueing']['pipe'][$rule['targetpipe']])) {
				$pipen = $rule['targetpipe'] + 1;
				$line = "add $rulei set 4 pipe $pipen "; $rulei++;
			} else if (isset($rule['targetqueue']) && isset($config['pfqueueing']['queue'][$rule['targetqueue']])) {
				$queuen = $rule['targetqueue'] + 1;
				$line = "add $rulei set 4 queue $queuen "; $rulei++;
			} else {
				printf("Neither existing pipe nor queue found in rule $i\n");
				break;
			}

			if (isset($rule['protocol'])) {
				$line .= "{$rule['protocol']} ";
			} else {
				$line .= "all ";
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

			if (!$src) {
				printf("No source address found in rule $i\n");
				break;
			}

			if (isset($rule['source']['not'])) {
				$line .= "from not $src ";
			} else {
				$line .= "from $src ";
			}

			if (!isset($rule['protocol']) || in_array($rule['protocol'], array("tcp","udp"))) {

				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);

					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						$line .= "{$srcport[0]} ";
					} else {
						$line .= "{$srcport[0]}-{$srcport[1]} ";
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

			if (!$dst) {
				printf("No destination address found in rule $i\n");
				break;
			}

			if (isset($rule['destination']['not'])) {
				$line .= "to not $dst ";
			} else {
				$line .= "to $dst ";
			}

			if (!isset($rule['protocol']) || in_array($rule['protocol'], array("tcp","udp"))) {

				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);

					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						$line .= "{$dstport[0]} ";
					} else {
						$line .= "{$dstport[0]}-{$dstport[1]} ";
					}
				}
			}

			if ($rule['iplen'])
				$line .= "iplen {$rule['iplen']} ";

			if ($rule['iptos'])
				$line .= "iptos {$rule['iptos']} ";

			if ($rule['tcpflags'])
				$line .= "tcpflags {$rule['tcpflags']} ";

			if ($rule['direction'] == "in")
				$line .= "in ";
			else if ($rule['direction'] == "out")
				$line .= "out ";

			if ($ispptp) {
				$line .= "via ng" . ($iif+1);
			} else {
				if ($rule['interface'] == "wan")
					$if = $wanif;
				else
					$if = $config['interfaces'][$rule['interface']]['if'];

				$line .= "via {$if}";
			}

			$line .= "\n";
			$shaperrules .= $line;
		}

		$i++;
	}

	/* generate pipes */
	if (isset($config['pfqueueing']['pipe'])) {
		$pipei = 1;
		foreach ($config['pfqueueing']['pipe'] as $pipe) {
			$line = "pipe $pipei config bw {$pipe['bandwidth']}Kbit/s ";

			if ($pipe['delay']) {
				$line .= "delay {$pipe['delay']} ";
			}

			switch ($pipe['mask']) {
				case 'source':
					$line .= "mask src-ip 0xffffffff ";
					break;
				case 'destination':
					$line .= "mask dst-ip 0xffffffff ";
					break;
			}

			$line .= "\n";
			$shaperrules .= $line;
			$pipei++;
		}
	}

	/* generate queues */
	if (isset($config['pfqueueing']['queue'])) {
		$queuei = 1;
		foreach ($config['pfqueueing']['queue'] as $queue) {

			$pipen = $queue['targetpipe'] + 1;
			if (!isset($queue['targetpipe']) || !isset($config['pfqueueing']['pipe'][$queue['targetpipe']])) {
				printf("Pipe $pipen for queue $queuei not found!\n");
				continue;
			}

			$line = "queue $queuei config pipe {$pipen}";
			$line .= " weight {$queue['weight']}";

			switch ($queue['mask']) {
				case 'source':
					$line .= " mask src-ip 0xffffffff ";
					break;
				case 'destination':
					$line .= " mask dst-ip 0xffffffff ";
					break;
			}

			$line .= "\n";
			$shaperrules .= $line;
			$queuei++;
		}
	}

	return $shaperrules;
}

?>
