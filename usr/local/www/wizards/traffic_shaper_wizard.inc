<?php

/* $Id$ */
/*
        traffic_shaper_wizard.inc
        part of pfSense (http://www.pfsense.org/)

        Copyright (C) 2006 Bill Marquette - bill.marquette@gmail.com.
        Copyright (C) 2006 Scott Ullrich - sullrich@pfsense.com.
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

function step1_stepsubmitphpaction() {
		global $g, $config;
		/* wipe previous */
		unset($config['shaper']['queue']);
		unset($config['shaper']['rule']);
		$config['shaper']['enable'] = FALSE;
		$config['shaper']['itemsshaped'] = "0";
}

function step2_stepsubmitphpaction() {
		global $g, $config;
		if(!is_numeric($_POST['upload']) or !is_numeric($_POST['download'])) {
			$message="Upload and download speeds must be a number only!  Values should be Kbits/second.";
			header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&amp;stepid=1&amp;message={$message}");
			exit;
		}
		if ($config['ezshaper']['step2']['inside_int'] == $config['ezshaper']['step2']['outside_int']) {
			$message="Inside and Outside interfaces cannot be the same";
			header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&amp;stepid=1&amp;message={$message}");
			exit;
		}
		$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
		$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);

		/* Magic shaper scheduler */
		$config['shaper']['schedulertype'] = "hfsc";

		/* Create queues */
		/* WAN root queue */
		$queue = array();
		$queue['name'] = "{$upq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 0;
		$queue['parentqueue'] = "on";
		/* $queue['bandwidth'] = (int)$_POST['upload'] * .8; */
		$queue['bandwidth'] = (int)$_POST['upload'];
		$queue['bandwidthtype'] = "Kb";
		$config['shaper']['queue'][] = $queue;

		/* LAN root queue */
		$queue = array();
		$queue['name'] = "{$downq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 0;
		$queue['parentqueue'] = "on";
		/* $queue['bandwidth'] = (int)$_POST['download'] * .8; */
		$queue['bandwidth'] = (int)$_POST['download'];
		$queue['bandwidthtype'] = "Kb";
		$config['shaper']['queue'][] = $queue;

		/* WAN default queue */
        $queue = array();
        $queue['name'] = "{$upq}def";
        $queue['attachtoqueue'] = "{$upq}Root";
        $queue['associatedrule'] = 0;
		$queue['defaultqueue'] = 'true';
		$queue['priority'] = 1;
		if ($config['shaper']['schedulertype'] == "hfsc") {
        	$queue['realtime'] = "on";
        	$queue['realtime3'] = "1%";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
			$queue['bandwidth'] = 6;
			$queue['bandwidthtype'] = 'Kb';
		}
                $config['shaper']['queue'][] = $queue;


		/* LAN default queue */
        $queue = array();
        $queue['name'] = "{$downq}def";
        $queue['priority'] = 1;
        $queue['attachtoqueue'] = "{$downq}Root";
        $queue['associatedrule'] = 0;
		$queue['defaultqueue'] = 'true';
		if ($config['shaper']['schedulertype'] == "hfsc") {
        	$queue['realtime'] = "on";
        	$queue['realtime3'] = "1%";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
			$queue['bandwidth'] = 6;
			$queue['bandwidthtype'] = 'Kb';
		}
        $config['shaper']['queue'][] = $queue;

		/* WAN ack queue */
            $queue = array();
            $queue['name'] = "{$upq}acks";
			$queue['ack'] = TRUE;
            $queue['attachtoqueue'] = "{$upq}Root";
            $queue['associatedrule'] = 0;
            $queue['priority'] = 7;
		if ($config['shaper']['schedulertype'] == "hfsc") {
        	$queue['realtime'] = "on";
        	$queue['realtime3'] = "10%";
			$queue['bandwidth'] = 25;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
			$queue['bandwidth'] = 6;
			$queue['bandwidthtype'] = 'Kb';
		}
	    $config['shaper']['queue'][] = $queue;
	    $queue = array();

		/* LAN ack queue */
        $queue['name'] = "{$downq}acks";
		$queue['ack'] = TRUE;
        $queue['attachtoqueue'] = "{$downq}Root";
        $queue['associatedrule'] = 0;
        $queue['priority'] = 7;
		if ($config['shaper']['schedulertype'] == "hfsc") {
        	$queue['realtime'] = "on";
        	$queue['realtime3'] = "10%";
			$queue['bandwidth'] = 25;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
			$queue['bandwidth'] = 6;
			$queue['bandwidthtype'] = 'Kb';
		}
                $config['shaper']['queue'][] = $queue;


}
function step3_stepsubmitphpaction() {
		global $g, $config;
		if($_POST['address']) {
			if(!is_ipaddr($_POST['address'])) {
				if(!is_alias($_POST['address'])) {
					/* item is not an ip or alias.  error out */
					$message="Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.";
					header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&amp;stepid=3&amp;message={$message}");
					exit;
				}
			}
		}

		if ( $_POST['enable'] ) {
			$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
			$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);
			$downint = strtolower($config['ezshaper']['step2']['inside_int']);
			$upint = strtolower($config['ezshaper']['step2']['outside_int']);

	                /* create qVOIPUp */
	                $queue = array();
	                $queue['name'] = "qVOIPUp";
 	               	$queue['attachtoqueue'] = "{$upq}Root";
	                $queue['associatedrule'] = 0;
	                $queue['priority'] = 7;
			if ($config['shaper']['schedulertype'] == "hfsc") {
				$queue['realtime'] = "on";
	                	$queue['realtime3'] = $_POST['bandwidth'] . "Kb";
				$queue['bandwidth'] = 25;
				$queue['bandwidthtype'] = '%';
			} elseif ($config['shaper']['schedulertype'] == "cbq") {
                        	$queue['bandwidth'] = $_POST['bandwidth'];
                        	$queue['bandwidthtype'] = 'Kb';
			}
 	                $config['shaper']['queue'][] = $queue;

 	                /* create qVOIPDown */
 	                $queue = array();
 	                $queue['name'] = "qVOIPDown";
 	                $queue['attachtoqueue'] = "{$downq}Root";
 	                $queue['associatedrule'] = 0;
 	                $queue['priority'] = 7;
			if ($config['shaper']['schedulertype'] == "hfsc") {
 	                	$queue['realtime'] = "on";
 	                	$queue['realtime3'] = $_POST['bandwidth'] . "Kb";
				$queue['bandwidth'] = 25;
				$queue['bandwidthtype'] = '%';
			} elseif ($config['shaper']['schedulertype'] == "cbq") {
                        	$queue['bandwidth'] = $_POST['bandwidth'];
                        	$queue['bandwidthtype'] = 'Kb';
			}
 	                $config['shaper']['queue'][] = $queue;

			$config['shaper']['itemsshaped']++;

			/* If user specifies an IP, we don't bother with providers */
			if( is_ipaddr($_POST['address']) or is_alias($_POST['address'])) {
			    /* create VOIP rules */
			    $rule = array();
			    $rule['descr'] = "VOIP Adapter";
			    $rule['inqueue'] = "qVOIPDown";
			    $rule['outqueue'] = "qVOIPUp";
			    $rule['in-interface'] = $downint;
			    $rule['out-interface'] = $upint;
			    /* $rule['source']['network'] = $downint; */
			    $rule['source']['address'] = $_POST['address'];
			    $rule['destination']['any'] = TRUE;
			    $config['shaper']['rule'][] = $rule;

			    $rule = array();
			    $rule['descr'] = "VOIP Adapter";
			    $rule['inqueue'] = "qVOIPUp";
			    $rule['outqueue'] = "qVOIPDown";
			    $rule['in-interface'] = $upint;
			    $rule['out-interface'] = $downint;
			    $rule['source']['any'] = TRUE;
			    $rule['destination']['address'] = $_POST['address'];
			    $config['shaper']['rule'][] = $rule;
			} elseif( $_POST['provider'] == "Generic" ) {
			    /* create VOIP rules */
			    $rule = array();
			    $rule['descr'] = "DiffServ/Lowdelay/Upload";
			    $rule['inqueue'] = "qVOIPDown";
			    $rule['outqueue'] = "qVOIPUp";
			    $rule['in-interface'] = $downint;
			    $rule['out-interface'] = $upint;
			    $rule['source']['network'] = $downint;
			    $rule['destination']['any'] = TRUE;
			    $rule['iptos'] = "lowdelay";
			    $config['shaper']['rule'][] = $rule;

			    $rule = array();
			    $rule['descr'] = "DiffServ/Lowdelay/Download";
			    $rule['inqueue'] = "qVOIPUp";
			    $rule['outqueue'] = "qVOIPDown";
			    $rule['in-interface'] = $upint;
			    $rule['out-interface'] = $downint;
			    $rule['source']['any'] = TRUE;
			    $rule['destination']['network'] = $downint;
			    $rule['iptos'] = "lowdelay";
			    $config['shaper']['rule'][] = $rule;
			} else {

			    $voiplist = array();

			    /* asterisk server / same as vonage */
			    if(($_POST['provider'] == "Asterisk") || ($_POST['provider'] == "Vonage")) {
				$voiplist[] = array('Asterisk', 'udp', '5060', '5069', 'both');
				$voiplist[] = array('Asterisk', 'udp', '10000', '20000', 'both');
			    }

			    /* VoicePulse server */
			    if( $_POST['provider'] == "VoicePulse") {
				$voiplist[] = array('VoicePulse', 'udp', '16384', '16482', 'both');
				$voiplist[] = array('VoicePulse', 'udp', '4569', '4569', 'both');
			    }

			    /* Panasonic Hybrid PBX */
			    if( $_POST['provider'] == "Panasonic") {
				$voiplist[] = array('Panasonic1', 'udp', '8000', '8063', 'both');
				$voiplist[] = array('Panasonic2', 'udp', '9300', '9301', 'both');
				$voiplist[] = array('Panasonic3', 'udp', '2747', '2747', 'both');
			    }

			    /* Set up/down VOIP as higher weight */
			    /* loop through voiplist[] */
			    foreach ($voiplist as $voip) {
				foreach (array('source', 'destination') as $srcdest) {
					$rule = array();
					if ($srcdest == 'source') {
						$destsrc = 'destination';
						$rule['inqueue'] = 'qVOIPDown';
						$rule['outqueue'] = 'qVOIPUp';
						$rule['in-interface'] = $downint;
						$rule['out-interface'] = $upint;
						$rule['source']['network'] = $downint;
						$rule['destination']['any'] = TRUE;
						$rule['descr'] = "m_voip {$voip[0]} outbound";
					} else {
						$destsrc = 'source';
						$rule['inqueue'] = 'qVOIPUp';
						$rule['outqueue'] = 'qVOIPDown';
						$rule['in-interface'] = $upint;
						$rule['out-interface'] = $downint;
						$rule['source']['any'] = TRUE;
						$rule['destination']['network'] = $downint;
						$rule['descr'] = "m_voip {$voip[0]} inbound";
					}

					$rule['destination']['port'] = $voip[2]."-".$voip[3];
					if($voip[1] != '')
						$rule['protocol'] = $voip[1];

					$config['shaper']['rule'][] = $rule;
				}
			    }
			}
		}
}
function step4_stepsubmitphpaction() {
	global $g, $config;
	/* XXX - billm - needs to actually honor what the user selects still */
	if ( $_POST['enable'] ) {
		$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
		$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);
		$downint = strtolower($config['ezshaper']['step2']['inside_int']);
		$upint = strtolower($config['ezshaper']['step2']['outside_int']);

		$p2plist = array();

		/* To add p2p clients, push Descr,Protocol,Start,End,src/dest/both onto p2plist */
		if($_POST['aimster'] != "")
		    $p2plist[] = array('Aimster', 'tcp', '7668', '7668', 'both');
		if($_POST['bittorrent'] != "")
		    $p2plist[] = array('BitTorrent', 'tcp', '6881', '6999', 'both');
		if($_POST['buddyshare'] != "")
		    $p2plist[] = array('BuddyShare', 'tcp', '7788', '7788', 'both');
		if($_POST['cutemx'] != "")
		    $p2plist[] = array('CuteMX', 'tcp', '2340', '2340', 'both');
		if($_POST['dc++'] != "")
		    $p2plist[] = array('DC++', 'tcp', '1412', '1412', 'both');
		if($_POST['dcc'] != "")
		    $p2plist[] = array('dcc', 'tcp', '6666', '6668', 'both');
		if($_POST['directconnect'] != "")
		    $p2plist[] = array('DirectConnect', 'tcp', '412', '412', 'both');
		if($_POST['directfileexpress'] != "")
		    $p2plist[] = array('DirectFileExpress', 'tcp', '1044', '1045', 'both');
		if($_POST['edonkey2000'] != "")
		    $p2plist[] = array('EDonkey2000', 'tcp', '4661', '4665', 'both');
		if($_POST['fastTrack'] != "")
		    $p2plist[] = array('FastTrack', 'tcp', '1214', '1214', 'both');
		if($_POST['gnutella'] != "") {
		    $p2plist[] = array('Gnutella-TCP', 'tcp', '6346', '6346', 'both');
		    $p2plist[] = array('Gnutella-UDP', 'udp', '6346', '6346', 'both');
		}
		if($_POST['grouper'] != "")
		    $p2plist[] = array('grouper', 'tcp', '8038', '8039', 'both');
		if($_POST['hotcomm'] != "")
		    $p2plist[] = array('hotComm', 'tcp', '28864', '28865', 'both');
		if($_POST['hotlineconnect'] != "")
		    $p2plist[] = array('HotlineConnect', 'tcp', '5500', '5503', 'both');
		if($_POST['imesh'] != "")
		    $p2plist[] = array('iMesh', 'tcp', '4329', '4329', 'both');
		if($_POST['napster'] != "")
		    $p2plist[] = array('Napster', 'tcp', '6699', '6701', 'both');
		if($_POST['opennap'] != "")
		    $p2plist[] = array('OpenNap', 'tcp', '8888', '8889', 'both');
		if($_POST['scour'] != "")
		    $p2plist[] = array('Scour', 'tcp', '8311', '8311', 'both');
		if($_POST['shareaza'] != "")
		    $p2plist[] = array('Shareaza', 'tcp', '6346', '6346', 'both');
		if($_POST['songspy'] != "")
		    $p2plist[] = array('SongSpy', 'tcp', '5190', '5190', 'both');
		if($_POST['winmx'] != "")
		    $p2plist[] = array('WinMX', 'tcp', '6699', '6699', 'both');

		/* Set up/down p2p as lowest weight */
		/* loop through p2plist[] */
		foreach ($p2plist as $p2pclient) {
			foreach (array('source', 'destination') as $srcdest) {
				$rule = array();
				$config['shaper']['itemsshaped']++;
				if ($srcdest == 'source') {
					$destsrc = 'destination';
					$rule['inqueue'] = 'qP2PDown';
					$rule['outqueue'] = 'qP2PUp';
					$rule['in-interface'] = $downint;
					$rule['out-interface'] = $upint;
					$rule['interface'] = $downint;
					$rule['source']['network'] = $downint;
					$rule['destination']['any'] = TRUE;
					$rule['descr'] = "m_P2P {$p2pclient[0]} outbound";
				} else {
					$destsrc = 'source';
					$rule['inqueue'] = 'qP2PUp';
					$rule['outqueue'] = 'qP2PDown';
					$rule['in-interface'] = $upint;
					$rule['out-interface'] = $downint;
					$rule['source']['any'] = TRUE;
					$rule['destination']['network'] = $downint;
					$rule['descr'] = "m_P2P {$p2pclient[0]} inbound";
				}


				$rule['destination']['port'] = $p2pclient[2]."-".$p2pclient[3];
				if($p2pclient[1] != '')
					$rule['protocol'] = $p2pclient[1];

				$config['shaper']['rule'][] = $rule;
			}
		}

		/* create qP2PUp */
		$queue = array();
		$queue['name'] = "qP2PUp";
                $queue['attachtoqueue'] = "{$upq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 1;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		/* create qP2PDown */
		$queue = array();
		$queue['name'] = "qP2PDown";
                $queue['attachtoqueue'] = "{$downq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 1;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;
	}
}
function step5_stepsubmitphpaction() {
	global $g, $config;
	/* XXX - billm - needs to actually honor what the user selects still */
	if ( $_POST['enable'] ) {
		$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
		$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);
		$downint = strtolower($config['ezshaper']['step2']['inside_int']);
		$upint = strtolower($config['ezshaper']['step2']['outside_int']);

		/* create qGamesUp queue */
		$queue = array();
		$queue['name'] = "qGamesUp";
                $queue['attachtoqueue'] = "{$upq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 5;
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 15;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		/* create qGamesDown queue */
		$queue = array();
		$queue['name'] = "qGamesDown";
                $queue['attachtoqueue'] = "{$downq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 5;
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 15;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		$gamesplist = array();

		if($_POST['battlefield2'] != "") {
		    /* Battlefield 2 */
		    $gamesplist[] = array('BF2-1500-4999', 'udp', '1500', '4999', 'both');
		    $gamesplist[] = array('BF2-4711', 'tcp', '4711', '4711', 'both');
		    $gamesplist[] = array('BF2-16567', 'udp', '16567', '16567', 'both');
		    $gamesplist[] = array('BF2-27900', 'udp', '27900', '27900', 'both');
		    $gamesplist[] = array('BF2-28910', 'tcp', '28910', '28910', 'both');
		    $gamesplist[] = array('BF2-29900-29901-UDP', 'udp', '29900', '29901', 'both');
		    $gamesplist[] = array('BF2-29900-29901-TCP', 'tcp', '29900', '29901', 'both');
		    $gamesplist[] = array('BF2-27900', 'udp', '27900', '27900', 'both');
		    $gamesplist[] = array('BF2-55123-55125', 'udp', '55123', '55125', 'both');
		}

		if($_POST['counterstrike'] != "") {
		    /* counter strike */
		    $gamesplist[] = array('Titan', 'udp', '6003', '6003', 'both');
		    $gamesplist[] = array('Authentication', 'udp', '7002', '7002', 'both');
		    $gamesplist[] = array('Client', 'udp', '6003', '6003', 'both');
		    $gamesplist[] = array('Masterserver', 'udp', '27010', '27010', 'both');
		    $gamesplist[] = array('Mod-Server', 'udp', '27011', '27011', 'both');
		    $gamesplist[] = array('Chat', 'udp', '27012', '27012', 'both');
		    $gamesplist[] = array('HL-Serverport1', 'udp', '27013', '27013', 'both');
		    $gamesplist[] = array('HL-Serverport2', 'udp', '27014', '27014', 'both');
		    $gamesplist[] = array('HL-Serverport', 'udp', '27015', '27015', 'both');
		}

		if($_POST['deltaforce'] != "") {
		    /* delta force */
		    $gamesplist[] = array('Delta1', 'udp', '17478', '17488', 'both');
		}

		if($_POST['quakeiii'] != "") {
		    /* quake3 */
		    $gamesplist[] = array('quakeiii', 'udp', '27910', '27919', 'both');
		}

		if($_POST['tigerwoods2004ps2'] != "") {
		    /* tiger woods 2004 ps2 */
		    $gamesplist[] = array('Outbound2Player', 'udp', '3658', '3658', 'both');
		    $gamesplist[] = array('Outbound2Player2', 'udp', '6000', '6000', 'both');
		    $gamesplist[] = array('Outbound2EA', 'tcp', '10300', '10301', 'both');
		}

		if($_POST['callofduty'] != "") {
		    $gamesplist[] = array('CallOfDuty1', 'tcp', '28960', '28960', 'both');
		    $gamesplist[] = array('CallOfDuty2', 'udp', '28960', '28960', 'both');
		}

		if($_POST['planetside'] != "") {
		    /* PlanetSide */
		    $gamesplist[] = array('PlanetSide', 'tcp', '7000', '7000', 'both');
		    $gamesplist[] = array('PlanetSide', 'tcp', '7080', '7080', 'both');
		    $gamesplist[] = array('PlanetSide2', 'udp', '3016', '3021', 'both');
		    $gamesplist[] = array('PlanetSide2', 'udp', '45000', '45010', 'both');
		    $gamesplist[] = array('PlanetSide2', 'udp', '30000', '30500', 'both');
		}

		if($_POST['halo2'] != "") {
		    /* Halo2 + XBOX Live */
		    $gamesplist[] = array('Halo2-1', 'udp', '88', '88', 'both');
		    $gamesplist[] = array('Halo2-2', 'udp', '3074', '3074', 'both');
		    $gamesplist[] = array('Halo2-3', 'tcp', '3074', '3074', 'both');
		}

		if($_POST['unrealtournament'] != "") {
		    /* Unreal Tournament */
		    $gamesplist[] = array('ur1', 'udp', '7777', '7787', 'both');
		    $gamesplist[] = array('ur2', 'tcp', '7777', '7787', 'both');
		}

		if($_POST['doom3'] != "") {
		    /* doom3 */
		    $gamesplist[] = array('DOOM3-1', 'udp', '27650', '27650', 'both');
		    $gamesplist[] = array('DOOM3-2', 'udp', '27666', '27666', 'both');
		}

		if($_POST['empireearth'] != "") {
		    /* empire earth */
		    $gamesplist[] = array('EmpireEarth-1', 'tcp', '33335', '33336', 'both');
		    $gamesplist[] = array('EmpireEarth-2', 'udp', '33334', '33334', 'both');
		}

		if($_POST['everquest'] != "") {
		    /* everquest */
		    $gamesplist[] = array('Everquest-1', 'tcp', '1024', '6000', 'both');
		    $gamesplist[] = array('Everquest-2', 'tcp', '7000', '7000', 'both');
		    $gamesplist[] = array('Everquest-3', 'udp', '1024', '6000', 'both');
		    $gamesplist[] = array('Everquest-4', 'udp', '7000', '7000', 'both');
		}

		if($_POST['everquest2'] != "") {
		    /* everquest2 */
		    $gamesplist[] = array('Everquest2-1', 'tcp', '7000', '7000', 'both');
		    $gamesplist[] = array('Everquest2-2', 'udp', '3016', '3021', 'both');
		    $gamesplist[] = array('Everquest2-3', 'udp', '9100', '9100', 'both');
		    $gamesplist[] = array('Everquest2-4', 'udp', '9700', '9703', 'both');
		    $gamesplist[] = array('Everquest2-5', 'udp', '32800', '33000', 'both');
 		}

		if($_POST['farcry'] != "") {
		    /* far cry */
		    $gamesplist[] = array('FarCry-1', 'tcp', '49001', '49002', 'both');
		    $gamesplist[] = array('FarCry-2', 'udp', '49001', '49002', 'both');
		}

		if($_POST['halflife2'] != "") {
		    /* halflife 2 */
		    $gamesplist[] = array('HL2-1', 'tcp', '27020', '27050', 'both');
		    $gamesplist[] = array('HL2-2', 'udp', '1200', '1200', 'both');
		    $gamesplist[] = array('HL2-3', 'udp', '27000', '27015', 'both');
		}

		if($_POST['halflife'] != "") {
		    /* halflife */
		    $gamesplist[] = array('HL-1', 'tcp', '27015', '27015', 'both');
		    $gamesplist[] = array('HL-2', 'udp', '27650', '27650', 'both');
		    $gamesplist[] = array('HL-3', 'udp', '27666', '27666', 'both');
		}

		if($_POST['wolfet'] != "") {
		    /* wolfenstein enemy territory */
		    $gamesplist[] = array('WolfET-1', 'tcp', '27960', '27960', 'both');
		}

		if($_POST['lineage2'] != "") {
		    /* Lineage II */
		    $gamesplist[] = array('Lineage2-2009', 'tcp', '2009', '2009', 'both');
		    $gamesplist[] = array('Lineage2-2106', 'tcp', '2106', '2106', 'both');
		    $gamesplist[] = array('Lineage2-7777', 'tcp', '7777', '7777', 'both');
		}

		if($_POST['battlenet'] != "") {
		    /* Blizzard Publishing games */
		    $gamesplist[] = array('Battle.NET', 'tcp', '6112', '6119', 'both');
		}
		if($_POST['worldofwarcraft'] != "") {
		    /* World of WarCract */
		    if ($_POST['battlenet'] == "") {
			/* Add battle.net only if WoW is selected and battle.net isn't */
		        $gamesplist[] = array('Battle.NET', 'tcp', '6112', '6119', 'both');
		    }
		    $gamesplist[] = array('WoW', 'tcp', '3724', '3724', 'both');
		}
		if($_POST['gunzonline'] != "") {
		    /* GunZ Online */
		    $gamesplist[] = array('GunZOnline', 'udp', '7700', '7700', 'both');
		}

		/* XXX: add some more games before this line!! */

		/* Set up/down games as higher weight */
		/* loop through p2plist[] */
		foreach ($gamesplist as $Gameclient) {
		    foreach (array('source', 'destination') as $srcdest) {
			    $rule = array();
			    $config['shaper']['itemsshaped']++;
			    if ($srcdest == 'source') {
				    $destsrc = 'destination';
				    $rule['inqueue'] = 'qGamesDown';
				    $rule['outqueue'] = 'qGamesUp';
				    $rule['in-interface'] = $downint;
				    $rule['out-interface'] = $upint;
				    $rule['source']['network'] = $downint;
				    $rule['destination']['any'] = TRUE;
				    $rule['descr'] = "m_Game {$Gameclient[0]} outbound";
			    } else {
				    $destsrc = 'source';
				    $rule['inqueue'] = 'qGamesUp';
				    $rule['outqueue'] = 'qGamesDown';
				    $rule['in-interface'] = $upint;
				    $rule['out-interface'] = $downint;
				    $rule['source']['any'] = TRUE;
				    $rule['destination']['network'] = $downint;
				    $rule['descr'] = "m_Game {$Gameclient[0]} inbound";
			    }

			    $rule['destination']['port'] = $Gameclient[2]."-".$Gameclient[3];

			    if($Gameclient[1] != '')
				    $rule['protocol'] = $Gameclient[1];

			    $config['shaper']['rule'][] = $rule;
		    }
		}
	}
}
function step6_stepsubmitphpaction() {
	global $g, $config;
	if ( $_POST['enable'] ) {
		$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
		$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);
		$downint = strtolower($config['ezshaper']['step2']['inside_int']);
		$upint = strtolower($config['ezshaper']['step2']['outside_int']);
		/* create qOthersUp queue */
		$queue = array();
		$queue['name'] = "qOthersUpH";
                $queue['attachtoqueue'] = "{$upq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 4;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 25;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		/* create qOthersDown queue */
		$queue = array();
		$queue['name'] = "qOthersDownH";
                $queue['attachtoqueue'] = "{$downq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 4;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 25;
			$queue['bandwidthtype'] = '%';
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;


		/* create qOthersUp queue */
		$queue = array();
		$queue['name'] = "qOthersUpL";
                $queue['attachtoqueue'] = "{$upq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 2;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		/* create qOthersDown queue */
		$queue = array();
		$queue['name'] = "qOthersDownL";
                $queue['attachtoqueue'] = "{$downq}Root";
		$queue['associatedrule'] = 0;
		$queue['priority'] = 2;
		$queue['red'] = "on";
		$queue['ecn'] = "on";
		if ($config['shaper']['schedulertype'] == "hfsc") {
			$queue['realtime'] = "on";
			$queue['realtime3'] = "1Kb";
			$queue['bandwidth'] = 1;
			$queue['bandwidthtype'] = '%';
			$queue['qlimit'] = 500;
		} elseif ($config['shaper']['schedulertype'] == "cbq") {
			$queue['borrow'] = "on";
                        $queue['bandwidth'] = 6;
                        $queue['bandwidthtype'] = 'Kb';
                }
		$config['shaper']['queue'][] = $queue;

		$othersplist = array();

		/* Unlike other areas we are posting the queue H or L or BLANK */

		if($_POST['msrdp'] != "") {
		    /* MSRDP */
		    $othersplist[] = array('MSRDP', 'tcp', '3389', '3389', 'both', $_POST['msrdp']);
		}

		if($_POST['pptp'] != "") {
		    /* PPTP */
		    $othersplist[] = array('PPTP', 'tcp', '1723', '1723', 'both', $_POST['pptp']);
		    $othersplist[] = array('PPTPGRE', 'gre', '', '', 'both', $_POST['pptp']);
                }

		if($_POST['ipsec'] != "") {
		    /* IPSEC */
		    $othersplist[] = array('IPSEC', 'udp', '500', '500', 'both', $_POST['ipsec']);
		    $othersplist[] = array('IPSEC', 'ah', '', '', 'both', $_POST['ipsec']);
		    $othersplist[] = array('IPSEC', 'esp', '', '', 'both', $_POST['ipsec']);
		}

		if($_POST['streamingmp3'] != "") {
		    /* streaming mp3 media aka shoutcast */
		    $othersplist[] = array('STREAMINGMP3', 'tcp', '8000', '8100', 'both', $_POST['streamingmp3']);
		}

		if($_POST['irc'] != "") {
		    /* internet relay chat */
		    $othersplist[] = array('IRC', 'tcp', '6667', '6670', 'both', $_POST['irc']);
		}

		if($_POST['jabber'] != "") {
		    /* jabber */
		    $othersplist[] = array('IRC', 'tcp', '5222', '5222', 'both', $_POST['jabber']);
		    $othersplist[] = array('IRC', 'tcp', '5223', '5223', 'both', $_POST['jabber']);
		    $othersplist[] = array('IRC', 'tcp', '5269', '5269', 'both', $_POST['jabber']);
		}

		if($_POST['dns'] != "") {
		    /* domain name system */
		    $othersplist[] = array('DNS1', 'tcp', '53', '53', 'both', $_POST['dns']);
		    $othersplist[] = array('DNS2', 'udp', '53', '53', 'both', $_POST['dns']);
		}

		if($_POST['http'] != "") {
		    /* HTTP aka Web Traffic */
		    $othersplist[] = array('HTTP', 'tcp', '80', '80', 'both', $_POST['http']);
		    $othersplist[] = array('HTTPS', 'tcp', '443', '443', 'both', $_POST['http']);
		}

		if($_POST['smtp'] != "") {
		    /* Secure shell traffic */
		    $othersplist[] = array('SMTP', 'tcp', '25', '25', 'both', $_POST['smtp']);
		}

		if($_POST['pop3'] != "") {
		    /* Post Office Protocol - POP3 */
		    $othersplist[] = array('POP3', 'tcp', '110', '110', 'both', $_POST['pop3']);
		}

		if($_POST['icmp'] != "") {
		    /* ICMP */
		    $othersplist[] = array('ICMP', 'icmp', '', '', 'both', $_POST['icmp']);
		}

		if($_POST['imap'] != "") {
		    /* IMAP */
		    $othersplist[] = array('IMAP', 'tcp', '143', '143', 'both', $_POST['imap']);
		}

		if($_POST['smb'] != "") {
		    /* Microsoft SMB and friends */
		    $othersplist[] = array('SMB1', 'tcp', '445', '445', 'both', $_POST['smb']);
		    $othersplist[] = array('SMB2', 'tcp', '137-139', '137-139', 'both', $_POST['smb']);

		if($_POST['rtsp'] != "") {
		    /* realtime streaming protocol */
		    $othersplist[] = array('RTSP1', 'tcp', '554', '554', 'both', $_POST['rtsp']);
		}		}

		if($_POST['snmp'] != "") {
		    /* Simple network management protocol */
		    $othersplist[] = array('SNMP', 'tcp', '161', '161', 'both', $_POST['snmp']);
		    $othersplist[] = array('SNMP2', 'udp', '161', '161', 'both', $_POST['snmp']);
		}

		if($_POST['vnc'] != "") {
		    /* virtual network control */
		    $othersplist[] = array('VNC', 'tcp', '5900', '5930', 'both', $_POST['vnc']);
		}

		if($_POST['appleremotedesktop'] != "") {
		    /* apple remote desktop */
		    $othersplist[] = array('AppleRemoteDesktop1', 'tcp', '3283', '3283', 'both', $_POST['appleremotedesktop']);
		    $othersplist[] = array('AppleRemoteDesktop2', 'tcp', '5900', '5900', 'both', $_POST['appleremotedesktop']);
		    $othersplist[] = array('AppleRemoteDesktop3', 'udp', '3283', '3283', 'both', $_POST['appleremotedesktop']);
		    $othersplist[] = array('AppleRemoteDesktop4', 'udp', '5900', '5900', 'both', $_POST['appleremotedesktop']);
		}

		if($_POST['icq'] != "") {
		    /* icq */
		    $othersplist[] = array('ICQ1', 'tcp', '5190', '5190', 'both', $_POST['icq']);
		    $othersplist[] = array('ICQ2', 'udp', '5190', '5190', 'both', $_POST['icq']);
		}

		if($_POST['lotusnotes'] != "") {
		    /* lotus notes */
		    $othersplist[] = array('LotusNotes1', 'tcp', '1352', '1352', 'both', $_POST['lotusnotes']);
		    $othersplist[] = array('LotusNotes2', 'udp', '1352', '1352', 'both', $_POST['lotusnotes']);
		}
		if($_POST['aolinstantmessenger'] != "") {
		    /* AIM */
		    $othersplist[] = array('AIM', 'tcp', '5190', '5190', 'both', $_POST['aolinstantmessenger']);
		}

		if($_POST['msnmessenger'] != "") {
		    /* msn messenger */
		    $othersplist[] = array('MSN1', 'tcp', '1863', '1863', 'both', $_POST['msnmessenger']);
		    $othersplist[] = array('MSN2', 'tcp', '6891', '6900', 'both', $_POST['msnmessenger']);
		    $othersplist[] = array('MSN3', 'tcp', '6901', '6901', 'both', $_POST['msnmessenger']);
		    $othersplist[] = array('MSN4', 'udp', '6901', '6901', 'both', $_POST['msnmessenger']);
		}

		if($_POST['mysqlserver'] != "") {
		    /* mysql server */
		    $othersplist[] = array('MySQL1', 'tcp', '3306', '3306', 'both', $_POST['mysqlserver']);
		}

		if($_POST['nntp'] != "") {
		    /* nntp */
		    $othersplist[] = array('NNTP1', 'tcp', '119', '119', 'both', $_POST['mysqlserver']);
			$othersplist[] = array('NNTP2', 'udp', '119', '119', 'both', $_POST['mysqlserver']);
		}

		if($_POST['pcanywhere'] != "") {
		    /* symantec pc anywhere */
		    $othersplist[] = array('pcany1', 'tcp', '5631', '5631', 'both', $_POST['pcanywhere']);
		    $othersplist[] = array('pcany2', 'udp', '5632', '5632', 'both', $_POST['pcanywhere']);
		    }

		if($_POST['teamspeak'] != "") {
		    /* teamspeak  */
		    $othersplist[] = array('teamspeak1', 'tcp', '14534', '14534', 'both', $_POST['teamspeak']);
		    $othersplist[] = array('teamspeak2', 'tcp', '51234', '51234', 'both', $_POST['teamspeak']);
		    $othersplist[] = array('teamspeak3', 'udp', '8767', '8768', 'both', $_POST['teamspeak']);
		}

		if($_POST['cvsup'] != "") {
		    /* cvsup */
		    $othersplist[] = array('cvs', 'tcp', '5999', '5999', 'both', $_POST['cvsup']);
		}

		/* XXX: add some more protocols here! */

		/* Set up/down protocols as higher weight */
		/* loop through othersplist[] */
		foreach ($othersplist as $otherclient) {
		    foreach (array('source', 'destination') as $srcdest) {
			    $rule = array();
			    $config['shaper']['itemsshaped']++;
			    if ($srcdest == 'source') {
				    $destsrc = 'destination';
				    switch ($otherclient[5]) {
					case "H":
					case "L":
				    		$rule['inqueue'] = 'qOthersDown' . $otherclient[5]; /* posted value H or L */
				    		$rule['outqueue'] = 'qOthersUp' . $otherclient[5]; /* posted value H or L */
						break;
					case "D":
				    		$rule['inqueue'] = "q${downint}def"; /* posted value H or L */
				    		$rule['outqueue'] = "q${upint}def"; /* posted value H or L */
						break;
				    }

				    $rule['in-interface'] = $downint;
				    $rule['out-interface'] = $upint;
				    $rule['source']['network'] = $downint;
				    $rule['destination']['any'] = TRUE;
				    $rule['descr'] = "m_Other {$otherclient[0]} outbound";
			    } else {
				    $destsrc = 'source';
				    switch ($otherclient[5]) {
					case "H":
					case "L":
				    		$rule['inqueue'] = 'qOthersUp' . $otherclient[5]; /* posted value H or L */
				    		$rule['outqueue'] = 'qOthersDown' . $otherclient[5]; /* posted value H or L */
						break;
					case "D":
				    		$rule['inqueue'] = "q${upint}def"; /* posted value H or L */
				    		$rule['outqueue'] = "q${downint}def"; /* posted value H or L */
						break;
				    }
				    $rule['in-interface'] = $upint;
				    $rule['out-interface'] = $downint;
				    $rule['source']['any'] = TRUE;
				    $rule['destination']['network'] = $downint;
				    $rule['descr'] = "m_Other {$otherclient[0]} inbound";
			    }

				if($otherclient[2] or $otherclient[3]) {
				    $rule['destination']['port'] = $otherclient[2]."-".$otherclient[3];
				}
				if($otherclient[1] != '')
				    $rule['protocol'] = $otherclient[1];

			    $config['shaper']['rule'][] = $rule;
		    }
		}
    }
}
function step7_stepbeforeformdisplay() {
		global $g, $config;
		if($config['shaper']['itemsshaped'] == "0") {
		    /*    no shaper items have been selected
		     *    wipe previous
		     */
		    unset($config['shaper']['queue']);
		    unset($config['shaper']['rule']);
		    unset($config['shaper']);
		    $config['shaper']['enable'] = FALSE;
		    $message = "No items have been selected to shape.  Exiting traffic shaper wizard.";
		    write_config("No shaper items picked, unsetting shaper configuration");
		    header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&amp;stepid=7&amp;message={$message}");
		    exit;
		}
}

function step7_stepsubmitphpaction() {
		global $g, $config, $d_shaperconfdirty_path;
		/* Sort rules by queue priority */
		 sort_rule_by_queue_priority();

		$downq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['inside_int']);
		$upq = "q" . convert_friendly_interface_to_friendly_descr($config['ezshaper']['step2']['outside_int']);
		$downint = strtolower($config['ezshaper']['step2']['inside_int']);
		$upint = strtolower($config['ezshaper']['step2']['outside_int']);

		/* install default p2p catch all rule if user has enabled option (MUST BE LAST!) */
		if($config['ezshaper']['step4']['p2pcatchall'] == "on") {
			$othersplist = array();
		    $othersplist[] = array('p2pCatchAll', 'tcp', '', '', 'both', '');
			$othersplist[] = array('p2pCatchAll2', 'udp', '', '', 'both', '');
		} else {
			$othersplist = array();
		}

		/* Set up/down protocols as p2p weight */
		/* loop through othersplist[] */
		foreach ($othersplist as $otherclient) {
		    foreach (array('source', 'destination') as $srcdest) {
			    $rule = array();
			    $config['shaper']['itemsshaped']++;
			    if ($srcdest == 'source') {
				    $destsrc = 'destination';
				    $rule['inqueue'] = 'qP2PDown' . $otherclient[5]; /* posted value H or L */
				    $rule['outqueue'] = 'qP2PUp' . $otherclient[5]; /* posted value H or L */
				    $rule['in-interface'] = $downint;
				    $rule['out-interface'] = $upint;
				    $rule['source']['network'] = $downint;
				    $rule['destination']['any'] = TRUE;
				    $rule['descr'] = "p2pCatchAll outbound";
			    } else {
				    $destsrc = 'source';
				    $rule['inqueue'] = 'qP2PUp' . $otherclient[5]; /* posted value H or L */
				    $rule['outqueue'] = 'qP2PDown' . $otherclient[5]; /* posted value H or L */
				    $rule['in-interface'] = $upint;
				    $rule['out-interface'] = $downint;
				    $rule['source']['any'] = TRUE;
				    $rule['destination']['network'] = $downint;
				    $rule['descr'] = "p2pCatchAll inbound";
			    }

				if($otherclient[2] or $otherclient[3]) {
				    $rule['destination']['port'] = $otherclient[2]."-".$otherclient[3];
					if($otherclient[1] != '')
				    $rule['protocol'] = $otherclient[1];
				}
				if($rule['inqueue'] != "" and $rule['in-interface'] != "" and $rule['out-interface'] != "")
					$config['shaper']['rule'][] = $rule;

		    }
		}

		/* Enable shaper */
		$config['shaper']['enable'] = TRUE;

		/* Prepare for next ezshaper wizard run */
		unset($config['shaper']['itemsshaped']);

		/* Create new rules */
		filter_configure();

		/* reset rrd queues */
		system("rm -f /var/db/rrd/wan-queues.rrd");
		enable_rrd_graphing();

		/* And we're no longer dirty! */
		unlink_if_exists($d_shaperconfdirty_path);

		update_filter_reload_status("Initializing");

		/* Head over and check out the groovy queue stats */
		header("Location: status_filter_reload.php");
}
function step8_stepsubmitphpaction() {
	global $g, $config;
	header("Location: status_filter_reload.php");
}


?>