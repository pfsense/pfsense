<?php
/* $Id$ */
/*
        traffic_shaper_wizard.inc
        part of pfSense (http://www.pfsense.org/)

        Copyright (C) 2006 Bill Marquette - bill.marquette@gmail.com.
        Copyright (C) 2006 Scott Ullrich - sullrich@pfsense.com.
	Copyright (C) 2008 Ermal Lu\xe7i
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



function  step1_stepbeforeformdisplay() {
		global $g, $config;
		
		$pkg = parse_xml_config_pkg("{$g['www_path']}/wizards/traffic_shaper_wizard.xml", "pfsensewizard");
		unset($pkg['step'][1]['fields']);
		$fields =& $pkg['step'][1]['fields']['field'];
		
		$fields = array();
		$field = array();
		$field['name'] = "Setup network speeds";
		$field['type'] = "listtopic";
		$fields[] = $field;
		
		foreach ($config['interfaces'] as $if => $ifdesc) {
			if (!is_altq_capable($ifdesc['if']))
				continue;
			if (!isset($ifdesc['enable']) && $if != "lan" && $if != "wan")
				continue;
			$field = array();
			$field['name'] = "{$if}scheduler";
			$field['type'] = "select";
			$field['typehint'] = "Queueing discipline to apply on this interface";
			$field['options']['option'] = array();
			$opts = array();
			$opts['name'] = "HFSC";
			$opts['value'] = "HFSC";
			$field['options']['option'][] = $opts;
			$opts = array();
			$opts['name'] = "CBQ";
			$opts['value'] = "CBQ";
			$field['options']['option'][] = $opts;
			$opts = array();
			$opts['name'] = "PRIQ";
			$opts['value'] = "PRIQ";
			$field['options']['option'][] = $opts;
			$field['bindstofield'] = "ezshaper->step2->{$if}->scheduler";
			$fields[] = $field;

			$field = array();
			$field['name'] = $if;
			$field['type'] = "input";
			$field['typehint'] = "Bandwidth in Kbit/sec on this interface";
			$field['validate'] = "^[0-9]*$";
			$field['bindstofield'] = "ezshaper->step2->{$if}->bandwidth";
			$fields[] = $field;
		}
		$field = array();
		$field['name'] = "Next";
		$field['type'] = "submit";
		$fields[] = $field;

	conf_mount_rw();
	
	$xmlcf = dump_xml_config_pkg($pkg, "pfsensewizard");
        /* write new configuration */
        $fd = fopen("{$g['www_path']}/wizards/traffic_shaper_wizard.xml", "w");
        if (!$fd)
                die("Unable to open {$g['www_path']}/wizards/traffic_shaper_wizard.xml for writing in write_config()\n");
        fwrite($fd, $xmlcf);
        fclose($fd);
	
	conf_mount_ro();

}
function step1_stepsubmitphpaction() {
		global $g, $config;
		/* wipe previous */
		if(isset($config['shaper']['queue']))
			unset($config['shaper']['queue']);
		if(isset($config['shaper']['rule']))
			unset($config['shaper']['rule']);
		foreach ($config['filter']['rule'] as $key => $rule)
			if ($rule['wizard'] == "yes")
				unset($config['filter']['rule'][$key]);
		write_config();
}

function step2_stepsubmitphpaction() {
	global $config;

	foreach ($config['interfaces'] as $if => $ifdesc) {
		if (!is_altq_capable($ifdesc['if']))
			continue;
		if (!isset($ifdesc['enable']) && $if != "lan" && $if != "wan")
			continue;
		if (isset($_POST['bandwidth']) && $_POST['bandwidth'] < 128) {
				$message=gettext("We do not support Bandwidths smaller than 128Kbit/s. Please correct this value to continue.");
				header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=2&message={$message}");
		}
	}	

}
function step3_stepsubmitphpaction() {

		if($_POST['address']) {
			if(!is_ipaddr($_POST['address'])) {
				if(!is_alias($_POST['address'])) {
					/* item is not an ip or alias.  error out */
					$message=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
					header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=3&message={$message}");

				}
			}
		}
}
function step4_stepsubmitphpaction() {

		if ( $_POST['enable'] ) {
			if(!is_numeric($_POST['bandwidth'])) {
				$message="Speed must be a number only!  Values should be in percentage.";
				header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=4&message={$message}");
				exit;
			}
			if(!$_POST['bandwidth']) {
				$message="You need to specify a value for bandwidth!  Values should be in percentage.";
				header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=4&message={$message}");
				exit;
			}
			if(!is_ipaddr($_POST['address'])) {
				if(!is_alias($_POST['address'])) {
					/* item is not an ip or alias.  error out */
					$message=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
					header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=4&message={$message}");
					exit;
				}
			}
		}

}
function step5_stepsubmitphpaction() {

	if ( $_POST['enable'] ) {
		if (( $_POST['bandwidth'] )) {
			if(!is_numeric($_POST['bandwidth'])) {
				$message="Speed must be a number only!  Values should be in percentage.";
				header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=4&message={$message}");
				exit;
			}
		}
	}


}

function step8_stepsubmitphpaction() {
		global $g, $config, $d_shaperconfdirty_path;

		/* Prepare for next ezshaper wizard run */
		unset($config['shaper']['itemsshaped']);
		
		/* create configuration */
		apply_all_choosen_items();

		/* reset rrd queues */
		system("rm -f /var/db/rrd/wan-queuesdrop.rrd");
		system("rm -f /var/db/rrd/wan-queues.rrd");
		enable_rrd_graphing();

		/* Create new rules */
		filter_configure();

		/* And we're no longer dirty! */
		unlink_if_exists($d_shaperconfdirty_path);

		update_filter_reload_status("Initializing");

		/* Head over and check out the groovy queue stats */
		header("Location: status_filter_reload.php");
}
function step9_stepsubmitphpaction() {
	global $g, $config;
	header("Location: status_filter_reload.php");
}

function apply_all_choosen_items() {
	global $pkg, $config, $g, $altq_list_queues;

$gamesplist = array();

$gamesplist['battlefield2'] = array();
		    /* Battlefield 2 */
		    $gamesplist['battlefield2'][] = array('BF2-1500-4999', 'udp', '1500', '4999', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-4711', 'tcp', '4711', '4711', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-16567', 'udp', '16567', '16567', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-27900', 'udp', '27900', '27900', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-28910', 'tcp', '28910', '28910', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-29900-29901-UDP', 'udp', '29900', '29901', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-29900-29901-TCP', 'tcp', '29900', '29901', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-27900', 'udp', '27900', '27900', 'both');
		    $gamesplist['battlefield2'][] = array('BF2-55123-55125', 'udp', '55123', '55125', 'both');

$gamesplist['counterstrike'] = array();
		    /* counter strike */
		    $gamesplist['counterstrike'][] = array('Titan', 'udp', '6003', '6003', 'both');
		    $gamesplist['counterstrike'][] = array('Authentication', 'udp', '7002', '7002', 'both');
		    $gamesplist['counterstrike'][] = array('Client', 'udp', '6003', '6003', 'both');
		    $gamesplist['counterstrike'][] = array('Masterserver', 'udp', '27010', '27010', 'both');
		    $gamesplist['counterstrike'][] = array('Mod-Server', 'udp', '27011', '27011', 'both');
		    $gamesplist['counterstrike'][] = array('Chat', 'udp', '27012', '27012', 'both');
		    $gamesplist['counterstrike'][] = array('HL-Serverport1', 'udp', '27013', '27013', 'both');
		    $gamesplist['counterstrike'][] = array('HL-Serverport2', 'udp', '27014', '27014', 'both');
		    $gamesplist['counterstrike'][] = array('HL-Serverport', 'udp', '27015', '27015', 'both');

$gamesplist['deltaforce'] = array();
		    /* delta force */
		    $gamesplist['deltaforce'][] = array('Delta1', 'udp', '17478', '17488', 'both');


$gamesplist['quakeiii'] = array();
		    /* quake3 */
		    $gamesplist['quakeiii'][] = array('quakeiii', 'udp', '27910', '27919', 'both');


$gamesplist['tigerwoods2004ps2'] = array();
		    /* tiger woods 2004 ps2 */
		    $gamesplist['tigerwoods2004ps2'][] = array('Outbound2Player', 'udp', '3658', '3658', 'both');
		    $gamesplist['tigerwoods2004ps2'][] = array('Outbound2Player2', 'udp', '6000', '6000', 'both');
		    $gamesplist['tigerwoods2004ps2'][] = array('Outbound2EA', 'tcp', '10300', '10301', 'both');


$gamesplist['callofduty'] = array();
		    $gamesplist['callofduty'][] = array('CallOfDuty1', 'tcp', '28960', '28960', 'both');
		    $gamesplist['callofduty'][] = array('CallOfDuty2', 'udp', '28960', '28960', 'both');


$gamesplist['planetside'] = array();
		    /* PlanetSide */
		    $gamesplist['planetside'][] = array('PlanetSide', 'tcp', '7000', '7000', 'both');
		    $gamesplist['planetside'][] = array('PlanetSide', 'tcp', '7080', '7080', 'both');
		    $gamesplist['planetside'][] = array('PlanetSide2', 'udp', '3016', '3021', 'both');
		    $gamesplist['planetside'][] = array('PlanetSide2', 'udp', '45000', '45010', 'both');
		    $gamesplist['planetside'][] = array('PlanetSide2', 'udp', '30000', '30500', 'both');


$gamesplist['halo2'] = array();
		    /* Halo2 + XBOX Live */
		    $gamesplist['halo2'][] = array('Halo2-1', 'udp', '88', '88', 'both');
		    $gamesplist['halo2'][] = array('Halo2-2', 'udp', '3074', '3074', 'both');
		    $gamesplist['halo2'][] = array('Halo2-3', 'tcp', '3074', '3074', 'both');


$gamesplist['unrealtournament'] = array();
		    /* Unreal Tournament */
		    $gamesplist['unrealtournament'][] = array('ur1', 'udp', '7777', '7787', 'both');
		    $gamesplist['unrealtournament'][] = array('ur2', 'tcp', '7777', '7787', 'both');


$gamesplist['doom3'] = array();
		    /* doom3 */
		    $gamesplist['doom3'][] = array('DOOM3-1', 'udp', '27650', '27650', 'both');
		    $gamesplist['doom3'][] = array('DOOM3-2', 'udp', '27666', '27666', 'both');


$gamesplist['empireearth'] = array();
		    /* empire earth */
		    $gamesplist['empireearth'][] = array('EmpireEarth-1', 'tcp', '33335', '33336', 'both');
		    $gamesplist['empireearth'][] = array('EmpireEarth-2', 'udp', '33334', '33334', 'both');


$gamesplist['everquest'] = array();
		    /* everquest */
		    $gamesplist['everquest'][] = array('Everquest-1', 'tcp', '1024', '6000', 'both');
		    $gamesplist['everquest'][] = array('Everquest-2', 'tcp', '7000', '7000', 'both');
		    $gamesplist['everquest'][] = array('Everquest-3', 'udp', '1024', '6000', 'both');
		    $gamesplist['everquest'][] = array('Everquest-4', 'udp', '7000', '7000', 'both');


$gamesplist['everquest2'] = array();
		    /* everquest2 */
		    $gamesplist['everquest2'][] = array('Everquest2-1', 'tcp', '7000', '7000', 'both');
		    $gamesplist['everquest2'][] = array('Everquest2-2', 'udp', '3016', '3021', 'both');
		    $gamesplist['everquest2'][] = array('Everquest2-3', 'udp', '9100', '9100', 'both');
		    $gamesplist['everquest2'][] = array('Everquest2-4', 'udp', '9700', '9703', 'both');
		    $gamesplist['everquest2'][] = array('Everquest2-5', 'udp', '32800', '33000', 'both');


$gamesplist['farcry'] = array();
		    /* far cry */
		    $gamesplist['farcry'][] = array('FarCry-1', 'tcp', '49001', '49002', 'both');
		    $gamesplist['farcry'][] = array('FarCry-2', 'udp', '49001', '49002', 'both');


$gamesplist['halflife2'] = array();
		    /* halflife 2 */
		    $gamesplist['halflife2'][] = array('HL2-1', 'tcp', '27020', '27050', 'both');
		    $gamesplist['halflife2'][] = array('HL2-2', 'udp', '1200', '1200', 'both');
		    $gamesplist['halflife2'][] = array('HL2-3', 'udp', '27000', '27015', 'both');


$gamesplist['halflife'] = array();
		    /* halflife */
		    $gamesplist['halflife'][] = array('HL-1', 'tcp', '27015', '27015', 'both');
		    $gamesplist['halflife'][] = array('HL-2', 'udp', '27650', '27650', 'both');
		    $gamesplist['halflife'][] = array('HL-3', 'udp', '27666', '27666', 'both');


$gamesplist['wolfet'] = array();
		    /* wolfenstein enemy territory */
		    $gamesplist['wolfet'][] = array('WolfET-1', 'tcp', '27960', '27960', 'both');


$gamesplist['lineage2'] = array();
		    /* Lineage II */
		    $gamesplist['lineage2'][] = array('Lineage2-2009', 'tcp', '2009', '2009', 'both');
		    $gamesplist['lineage2'][] = array('Lineage2-2106', 'tcp', '2106', '2106', 'both');
		    $gamesplist['lineage2'][] = array('Lineage2-7777', 'tcp', '7777', '7777', 'both');
$gamesplist['battlenet'] = array();
		    /* Blizzard Publishing games */
		    $gamesplist['battlenet'][] = array('Battle.NET', 'tcp', '6112', '6119', 'both');
/* World of WarCract */
$gamesplist['worldofwarcraft'] = array();
  $gamesplist[] = array('WoW', 'tcp', '3724', '3724', 'both');

/* Add battle.net only if WoW is selected and battle.net isn't */
$gamesplist['battlenet'] = array();
  $gamesplist[] = array('Battle.NET', 'tcp', '6112', '6119', 'both');

/* ArchLord */
$gamesplist['archlord'] = array();
  $gamesplist[] = array('AL-1', 'tcp', '11000', '11000', 'both');
  $gamesplist[] = array('AL-2', 'tcp', '11002', '11002', 'both');
  $gamesplist[] = array('AL-3', 'tcp', '11008', '11008', 'both');

/* GunZ Online */
$gamesplist['gunzonline'] = array();
  $gamesplist['gunzonline'][] = array('GunZOnline', 'udp', '7700', '7700', 'both');

/* XBox360 */
$gamesplist['xbox360'] = array();
  $gamesplist['xbox360'][] = array('xbox360-1', 'udp', '88', '88', 'both');
  $gamesplist['xbox360'][] = array('xbox360-2', 'udp', '3074', '3074', 'both');
  $gamesplist['xbox360'][] = array('xbox360-3', 'tcp', '3074', '3074', 'both');


$voiplist = array();

   /* asterisk server / same as vonage */
$voiplist['Asterisk'] = array();
		$voiplist['Asterisk'][] = array($_POST['provider'], 'udp', '5060', '5069', 'both');
		$voiplist['Asterisk'][] = array($_POST['provider'], 'udp', '10000', '20000', 'both');

    /* VoicePulse server */	
$voiplist['VoicePulse'] = array();
	$voiplist['VoicePulse'][] = array('VoicePulse', 'udp', '16384', '16482', 'both');
	$voiplist['VoicePulse'][] = array('VoicePulse', 'udp', '4569', '4569', 'both');

    /* Panasonic Hybrid PBX */
$voiplist['Panasonic'] = array();
	$voiplist['Panasonic'][] = array('Panasonic1', 'udp', '8000', '8063', 'both');
	$voiplist['Panasonic'][] = array('Panasonic2', 'udp', '9300', '9301', 'both');
	$voiplist['Panasonic'][] = array('Panasonic3', 'udp', '2747', '2747', 'both');


$p2plist = array();
		/* To add p2p clients, push Descr,Protocol,Start,End,src/dest/both onto p2plist */
		$p2plist['aimster'] = array();
		    $p2plist['aimster'][] = array('Aimster', 'tcp', '7668', '7668', 'both');
		$p2plist['bittorrent'] = array(); 
		    $p2plist['bittorrent'][] = array('BitTorrent', 'tcp', '6881', '6999', 'both');
		    $p2plist['bittorrent'][] = array('BitTorrent', 'udp', '6881', '6999', 'both');
		$p2plist['buddyshare'] = array();
		    $p2plist['buddyshare'][] = array('BuddyShare', 'tcp', '7788', '7788', 'both');
		$p2plist['cutemx'] = array();
		    $p2plist['cutemx'][] = array('CuteMX', 'tcp', '2340', '2340', 'both');
		$p2plist['dc++'] = array();
		    $p2plist['dc++'][] = array('DC++', 'tcp', '1412', '1412', 'both');
		$p2plist['dcc'] = array();
		    $p2plist['dcc'][] = array('dcc', 'tcp', '6666', '6668', 'both');
		$p2plist['directconnect'] = array();
		    $p2plist['directconnect'][] = array('DirectConnect', 'tcp', '412', '412', 'both');
		$p2plist['directfileexpress'] = array();
		    $p2plist['directfileexpress'][] = array('DirectFileExpress', 'tcp', '1044', '1045', 'both');
		$p2plist['edonkey2000'] = array();
		    $p2plist['edonkey2000'][] = array('EDonkey2000', 'tcp', '4661', '4665', 'both');
		$p2plist['fastTrack'] = array();
		    $p2plist['fastTrack'][] = array('FastTrack', 'tcp', '1214', '1214', 'both');
		$p2plist['gnutella'] = array(); 
		    $p2plist['gnutella'][] = array('Gnutella-TCP', 'tcp', '6346', '6346', 'both');
		    $p2plist['gnutella'][] = array('Gnutella-UDP', 'udp', '6346', '6346', 'both');
		$p2plist['grouper'] = array();
		    $p2plist['grouper'][] = array('grouper', 'tcp', '8038', '8039', 'both');
		$p2plist['hotcomm'] = array();
		    $p2plist['hotcomm'][] = array('hotComm', 'tcp', '28864', '28865', 'both');
		$p2plist['hotlineconnect'] = array();
		    $p2plist['hotlineconnect'][] = array('HotlineConnect', 'tcp', '5500', '5503', 'both');
		$p2plist['imesh'] = array();
		    $p2plist['imesh'][] = array('iMesh', 'tcp', '4329', '4329', 'both');
		$p2plist['napster'] = array();
		    $p2plist['napster'][] = array('Napster', 'tcp', '6699', '6701', 'both');
		$p2plist['opennap'] = array();
		    $p2plist['opennap'][] = array('OpenNap', 'tcp', '8888', '8889', 'both');
		$p2plist['scour'] = array();
		    $p2plist['scour'][] = array('Scour', 'tcp', '8311', '8311', 'both');
		$p2plist['shareaza'] = array();
		    $p2plist['shareaza'][] = array('Shareaza', 'tcp', '6346', '6346', 'both');
		$p2plist['songspy'] = array();
		    $p2plist['songspy'][] = array('SongSpy', 'tcp', '5190', '5190', 'both');
		$p2plist['winmx'] = array();
		    $p2plist['winmx'][] = array('WinMX', 'tcp', '6699', '6699', 'both');



$othersplist = array();
		/* Unlike other areas we are posting the queue H or L or BLANK */

		$othersplist['msrdp'] = array();
		    /* MSRDP */
		    $othersplist['msrdp'][] = array('MSRDP', 'tcp', '3389', '3389', 'both');
		$othersplist['pptp'] = array();
		    /* PPTP */
		    $othersplist['pptp'][] = array('PPTP', 'tcp', '1723', '1723', 'both');
		    $othersplist['pptp'][] = array('PPTPGRE', 'gre', '', '', 'both');
		$othersplist['ipsec'] = array();
		    /* IPSEC */
		    $othersplist['ipsec'][] = array('IPSEC', 'udp', '500', '500', 'both');
		    $othersplist['ipsec'][] = array('IPSEC', 'ah', '', '', 'both');
		    $othersplist['ipsec'][] = array('IPSEC', 'esp', '', '', 'both');
		$othersplist['streamingmp3'] = array();
		    /* streaming mp3 media aka shoutcast */
		    $othersplist['streamingmp3'][] = array('STREAMINGMP3', 'tcp', '8000', '8100', 'both');
		$othersplist['irc'] = array();
		    /* internet relay chat */
		    $othersplist['irc'][] = array('IRC', 'tcp', '6667', '6670', 'both');
		$othersplist['jabber'] = array();
		    /* jabber */
		    $othersplist['jabber'][] = array('IRC', 'tcp', '5222', '5222', 'both');
		    $othersplist['jabber'][] = array('IRC', 'tcp', '5223', '5223', 'both');
		    $othersplist['jabber'][] = array('IRC', 'tcp', '5269', '5269', 'both');
		$othersplist['dns'] = array();
		    /* domain name system */
		    $othersplist['dns'][] = array('DNS1', 'tcp', '53', '53', 'both');
		    $othersplist['dns'][] = array('DNS2', 'udp', '53', '53', 'both');
		$othersplist['http'] = array();
		    /* HTTP aka Web Traffic */
		    $othersplist['http'][] = array('HTTP', 'tcp', '80', '80', 'both');
		    $othersplist['http'][] = array('HTTPS', 'tcp', '443', '443', 'both');
		$othersplist['smtp'] = array();
		    /* Secure shell traffic */
		    $othersplist['smtp'][] = array('SMTP', 'tcp', '25', '25', 'both');
		$othersplist['pop3'] = array();
		    /* Post Office Protocol - POP3 */
		    $othersplist['pop3'][] = array('POP3', 'tcp', '110', '110', 'both');
		$othersplist['icmp'] = array();
		    /* ICMP */
		    $othersplist['icmp'][] = array('ICMP', 'icmp', '', '', 'both');
		$othersplist['imap'] = array();
		    /* IMAP */
		    $othersplist['imap'][] = array('IMAP', 'tcp', '143', '143', 'both');
		$othersplist['smb'] = array();
		    /* Microsoft SMB and friends */
		    $othersplist['smb'][] = array('SMB1', 'tcp', '445', '445', 'both');
		    $othersplist['smb'][] = array('SMB2', 'tcp', '137-139', '137-139', 'both');
		$othersplist['rtsp'] = array();
		    /* realtime streaming protocol */
		    $othersplist['rtsp'][] = array('RTSP1', 'tcp', '554', '554', 'both');
		$othersplist['snmp'] = array();
		    /* Simple network management protocol */
		    $othersplist['snmp'][] = array('SNMP', 'tcp', '161', '161', 'both');
		    $othersplist['snmp'][] = array('SNMP2', 'udp', '161', '161', 'both');
		$othersplist['vnc'] = array();
		    /* virtual network control */
		    $othersplist['vnc'][] = array('VNC', 'tcp', '5900', '5930', 'both');
		$othersplist['appleremotedesktop'] = array();
		    /* apple remote desktop */
		    $othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop1', 'tcp', '3283', '3283', 'both');
		    $othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop2', 'tcp', '5900', '5900', 'both');
		    $othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop3', 'udp', '3283', '3283', 'both');
		    $othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop4', 'udp', '5900', '5900', 'both');
		$othersplist['icq'] = array();
		    /* icq */
		    $othersplist['icq'][] = array('ICQ1', 'tcp', '5190', '5190', 'both');
		    $othersplist['icq'][] = array('ICQ2', 'udp', '5190', '5190', 'both');
		$othersplist['lotusnotes'] = array();
		    /* lotus notes */
		    $othersplist['lotusnotes'][] = array('LotusNotes1', 'tcp', '1352', '1352', 'both');
		    $othersplist['lotusnotes'][] = array('LotusNotes2', 'udp', '1352', '1352', 'both');
		$othersplist['aolinstantmessenger'] = array();
		    /* AIM */
		    $othersplist['aolinstantmessenger'][] = array('AIM', 'tcp', '5190', '5190', 'both');
		$othersplist['msnmessenger'] = array();
		    /* msn messenger */
		    $othersplist['msnmessenger'][] = array('MSN1', 'tcp', '1863', '1863', 'both');
		    $othersplist['msnmessenger'][] = array('MSN2', 'tcp', '6891', '6900', 'both');
		    $othersplist['msnmessenger'][] = array('MSN3', 'tcp', '6901', '6901', 'both');
		    $othersplist['msnmessenger'][] = array('MSN4', 'udp', '6901', '6901', 'both');
		$othersplist['mysqlserver'] = array();
		    /* mysql server */
		    $othersplist['mysqlserver'][] = array('MySQL1', 'tcp', '3306', '3306', 'both');
		$othersplist['nntp'] = array();
		    /* nntp */
		    $othersplist['nntp'][] = array('NNTP1', 'tcp', '119', '119', 'both');
			$othersplist['nntp'][] = array('NNTP2', 'udp', '119', '119', 'both');
		$othersplist['pcanywhere'] = array();
		    /* symantec pc anywhere */
		    $othersplist['pcanywhere'][] = array('pcany1', 'tcp', '5631', '5631', 'both');
		    $othersplist['pcanywhere'][] = array('pcany2', 'udp', '5632', '5632', 'both');
		$othersplist['teamspeak'] = array();
		    /* teamspeak  */
		    $othersplist['teamspeak'][] = array('teamspeak1', 'tcp', '14534', '14534', 'both');
		    $othersplist['teamspeak'][] = array('teamspeak2', 'tcp', '51234', '51234', 'both');
		    $othersplist['teamspeak'][] = array('teamspeak3', 'udp', '8767', '8768', 'both');
		$othersplist['cvsup'] = array();
		    /* cvs  */
		    $othersplist['cvsup'][] = array('cvsup', 'tcp', '5999', '5999', 'both');
		$othersplist['hbci'] = array();
		    /* HBCI  */
		    $othersplist['hbci'][] = array('HBCI', 'tcp', '3000', '3000', 'both');
		$othersplist['p2pCatchAll'] = array();
			$othersplist['p2pCatchAll'][] = array('p2pCatchAll', 'udp', '', '', 'both', '');
			$othersplist['p2pCatchAll'][] = array('p2pCatchAll2', 'tcp', '', '', 'both', '');


	unset($altq_list_queues);

	$altq_list_queues = array();
	$tmppath = array();	

	foreach ($config['ezshaper']['step2'] as $ifkey => $if) {

		$altq =& new altq_root_queue();

		$altq->SetInterface($ifkey);
		$altq->SetScheduler($if['scheduler']);
		$altq->SetBandwidth($if['bandwidth']);
		$altq->SetBwscale("Kb");
		$altq->SetEnabled("on");
		$altq_list_queues[$altq->GetQname()] =& $altq;
		array_push($tmppath, $ifkey);
		$altq->SetLink($tmppath);
		$altq->wconfig();


		$sched = $if['scheduler'];
		/* Values are in percent */
		if ($sched == "CBQ" || $sched == "HFSC") {
			if (isset($config['ezshaper']['step3']['bandwidth'])) {
					$voip = TRUE;
					$voipbw = $config['ezshaper']['step3']['bandwidth'];
			} else {
					$voip = FALSE;
					$voipbw = 20;
			}
			if (isset($config['ezshaper']['step4']['enable'])) {
					$penalty = TRUE;
					$penaltybw = $config['ezshaper']['step4']['bandwidth'];
			} else {
					$penalty = FALSE;
					$penaltybw = 0;
			}
			if (isset($config['ezshaper']['step5']['p2pcatchall'])) {
					$p2pcatchall = TRUE;
					$p2pcatchbw = $config['ezshaper']['step5']['bandwidth'];
			} else {
					$p2pcatchall = FALSE;
					$p2pcatchbw = 0;
			}
			
				
			if ($voipbw + $penaltybw + $p2pcatchbw > 40) {
				$message=gettext("Custom Bandwidths are greater than 70%. Please make them more reasonable  to continue.");
				header("Location: /wizard.php?xml=traffic_shaper_wizard.xml&stepid=2&message={$message}");
			}
			$remainbw = 100 - $voipbw - $penaltybw - $p2pcatchbw;			
		}

		if ($sched == "PRIQ" || $sched == "CBQ" || $sched == "HFSC") {
			if ($sched != "HFSC") {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();	
				$tmpcf = array();
				$tmpcf['name'] = "qACK";
				$tmpcf['priority'] = 6;
				$tmpcf['red'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 30/100; /* 30% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				}
				array_push($tmppath, "qACK");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
				array_pop($tmppath);
				$qtmp->wconfig();
			} else {
				/* If you find out how to make qACK for HFSC work be my guest */
				$remainbw = $remainbw + 30;
			}


			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qDefault";
			$tmpcf['priority'] = 3;
			if (!$p2pcatchall)
				$tmpcf['default'] = "on";
			$tmpcf['red'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 7/100; /* 7% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = $remainbw * 7/100; /* 7% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qDefault");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();

	

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qP2P";
			$tmpcf['priority'] = 1;
			$tmpcf['red'] = "on";
			if ($p2pcatchall) {
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $p2pcatchbw;
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					$tmpcf['bandwidth'] = $p2pcatchbw;
					$tmpcf['bandwidthtype'] = "%";
				}
				$tmpcf['default'] = "on";				
			} else {
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 5/100; /* 5% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					$tmpcf['bandwidth'] = $remainbw * 5/100; /* 5% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				}
			}
			array_push($tmppath, "qP2P");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qVoIP";
			$tmpcf['priority'] = 7;
			$tmpcf['red'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				if ($voip)
					$tmpcf['bandwidth'] = $voipbw;
				else
					$tmpcf['bandwidth'] = $remainbw * 20/100; /* 20% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				if ($voip) {
					$tmpcf['realtime'] = "on";
					$tmpcf['realtime1'] = "10%";
					$tmpcf['realtime2'] = "50ms";
					$tmpcf['realtime3'] = "{$voipbw}%";
				} else {
					$tmpcf['realtime'] = "on";
					$tmpcf['realtime1'] = "10%";
					$tmpcf['realtime2'] = "50ms";
					$voipbw = ($remainbw * 20/100); /* 20% bandwidth */
					$tmpcf['realtime3'] = "{$voipbw}%";
				}
			}
			array_push($tmppath, "qVoIP");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qGames";
			$tmpcf['priority'] = 5;
			$tmpcf['red'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 20/100; /* 20% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = $remainbw * 20/100; /* 20% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qGames");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qOthersHigh";
			$tmpcf['priority'] = 4;
			$tmpcf['red'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 8/100; /* 8% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = $remainbw * 8/100; /* 8% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qOthersHigh");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();
		
			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qOthersLow";
			$tmpcf['priority'] = 2;
			$tmpcf['red'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				if ($penalty)
					$tmpcf['bandwidth'] = $penaltybw;
				else
					$tmpcf['bandwidth'] = $remainbw * 5/100; /* 5% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				if ($penalty)
					$tmpcf['bandwidth'] = $penaltybw;
				else
					$tmpcf['bandwidth'] = $remainbw * 5/100; /* 5% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qOthersLow");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath);
			array_pop($tmppath);
			$qtmp->wconfig();

			if (!is_array($config['filter']['rule']))
				$config['filter']['rule'] = array();

		}
		array_pop($tmppath);
	
			/* Rules */
			if ($penalty) {
				if( is_ipaddr($config['ezshaper']['step4']['address']) or is_alias($config['ezshaper']['step4']['address'])) {
				    $rule = array();
				    $rule['descr'] = gettext("Penalty Box");
				    $rule['interface'] = $altq->GetInterface();
			    	    $rule['source']['network'] = $altq->GetInterface();
//				    $rule['source']['any'] = TRUE;
				    $rule['defaultqueue'] = "qOthersLow";
				    $rule['source']['address'] = $config['ezshaper']['step4']['address'];
				    $rule['destination']['any'] = TRUE;
				    $rule['wizard'] = "yes";
				    $config['filter']['rule'][] = $rule;
	
				}
			}

			/* If user specifies an IP, we don't bother with providers */
			if( is_ipaddr($config['ezshaper']['step3']['address']) or is_alias($config['ezshaper']['step3']['address'])) {
			    /* create VOIP rules */
			    $rule = array();
			    $rule['descr'] = gettext("VOIP Adapter");
			    $rule['interface'] = $altq->GetInterface();
			    	    $rule['source']['network'] = $altq->GetInterface();
//				    $rule['source']['any'] = TRUE;
			    $rule['defaultqueue'] = "qVoIP";
			    $rule['source']['address'] = $config['ezshaper']['step3']['address'];
			    $rule['destination']['any'] = TRUE;
			    $rule['wizard'] = "yes";
			    $config['filter']['rule'][] = $rule;

			} elseif( $config['ezshaper']['step3']['provider'] == "Generic" ) {
			    /* create VOIP rules */
			    $rule = array();
			    $rule['descr'] = "DiffServ/Lowdelay/Upload";
			    $rule['interface'] = $altq->GetInterface();
			    	    $rule['source']['network'] = $altq->GetInterface();
//				    $rule['source']['any'] = TRUE;
			    $rule['defaultqueue'] = "qVoIP";
			    $rule['source']['network'] = $altq->GetInterface();
			    $rule['destination']['any'] = TRUE;
			    $rule['iptos'] = "lowdelay";
			    $rule['wizard'] = "yes";
			    $config['filter']['rule'][] = $rule;

			} else {
			    /* loop through voiplist[] */
			    foreach ($voiplist[$config['ezshaper']['step3']['provider']] as $voip) {
				$rule = array();
    			        $rule['interface'] = $altq->GetInterface();
				$rule['defaultqueue'] = 'qVoIP';
			    	    $rule['source']['network'] = $altq->GetInterface();
//				    $rule['source']['any'] = TRUE;
				$rule['destination']['any'] = TRUE;
				$rule['descr'] = "m_voip {$voip[0]} outbound";
			        $rule['wizard'] = "yes";
				$rule['destination']['port'] = $voip[2]."-".$voip[3];
				if($voip[1] != '')
					$rule['protocol'] = $voip[1];
				$config['filter']['rule'][] = $rule;
			    }
			}
			

			/* loop through p2plist[] */
			foreach($config['ezshaper']['step5'] as $key => $val) {
			if (!is_array($p2plist[$key]))
				continue;
			foreach ($p2plist[$key] as $p2pclient) {
				$rule = array();
				$rule['defaultqueue'] = 'qP2P';
				$rule['interface'] = $altq->GetInterface();
			    	    $rule['source']['network'] = $altq->GetInterface();
//			    $rule['source']['any'] = TRUE;
			        $rule['source']['network'] = $altq->GetInterface();				
				$rule['destination']['any'] = TRUE;
				$rule['descr'] = "m_P2P {$p2pclient[0]} outbound";
			        $rule['wizard'] = "yes";
				$rule['destination']['port'] = $p2pclient[2]."-".$p2pclient[3];
				if($p2pclient[1] != '')
					$rule['protocol'] = $p2pclient[1];
				$config['filter']['rule'][] = $rule;
			}
			}

			/* loop through gamesplist[] */
			foreach($config['ezshaper']['step6'] as $key => $val) {
			if (!is_array($gamesplist[$key]))
                                continue;
			foreach ($gamesplist[$key] as $Gameclient) {
			    $rule = array();
			    $rule['defaultqueue'] = 'qGames';
			    if ($sched != "HFSC")
				$rule['ackqueue'] = 'qACK';
			    $rule['interface'] = $altq->GetInterface();
		    	    $rule['source']['network'] = $altq->GetInterface();
//			    $rule['source']['any'] = TRUE;
			    $rule['destination']['any'] = TRUE;
			    $rule['wizard'] = "yes";
			    $rule['descr'] = "m_Game {$Gameclient[0]} outbound";
			    $rule['destination']['port'] = $Gameclient[2]."-".$Gameclient[3];
			    if($Gameclient[1] != '')
				    $rule['protocol'] = $Gameclient[1];
			    $config['filter']['rule'][] = $rule;
		   	}
			}

			/* loop through othersplist[] */
			foreach($config['ezshaper']['step7'] as $key => $val) {
			if (!is_array($othersplist[$key]))
                                continue;
			foreach ($othersplist[$key] as $otherclient) {
			    $rule = array();
			    switch ($val) {
				case "H":
			    		$rule['defaultqueue'] = 'qOthersHigh'; /* posted value H or L */
		    		        if ($sched != "HFSC")
						$rule['ackqueue'] = 'qACK';
					$loop = 0;
				break;
				case "L":
			    		$rule['defaultqueue'] = 'qOthersLow'; /* posted value H or L */
					$loop = 0;
				break;
				case "D":
					if ($p2pcatchall) {
						$loop = 0;
						$rule['defaultqueue'] = 'qDefault';
			 		        if ($sched != "HFSC")
							$rule['ackqueue'] = 'qACK';
					} else
						$loop = 1; /* It automitaclly goes to default queue */
				break;
				default:
					$loop = 1;
				}
				if (!$loop) {
	    			    $rule['interface'] = $altq->GetInterface();
			    	    $rule['source']['network'] = $altq->GetInterface();
//				    $rule['source']['any'] = TRUE;
				    $rule['destination']['any'] = TRUE;
			    	    $rule['wizard'] = "yes";
				    $rule['descr'] = "m_Other {$otherclient[0]} outbound";

					if($otherclient[2] or $otherclient[3]) {
					    $rule['destination']['port'] = $otherclient[2]."-".$otherclient[3];
					}
					if($otherclient[1] != '')
					    $rule['protocol'] = $otherclient[1];

			    		$config['filter']['rule'][] = $rule;
				}
		    	}
			}
	}
	write_config();
}
?>
