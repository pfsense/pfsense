<?php
/* $Id$ */
/*
        traffic_shaper_wizard.inc
        part of pfSense (http://www.pfsense.org/)

        Copyright (C) 2006 Bill Marquette - bill.marquette@gmail.com.
        Copyright (C) 2006 Scott Ullrich - sullrich@pfsense.com.
      	Copyright (C) 2008 Ermal Luçi
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

function step1_submitphpaction() {
	global $stepid, $savemsg;

	if (!isset($_POST['numberofconnections'])) {
		$savemsg=gettext("You need to specify the number of connections.");
		$stepid--;
		return;
	}			
	if (intval($_POST['numberofconnections']) > 100 || intval($_POST['numberofconnections']) < 1) {
		$savemsg=gettext("The number of connections supported is between 1 and 100.");
		$stepid--;
		return;
	}	
}

function step2_stepbeforeformdisplay() {
	global $config, $pkg;
	global $stepid, $savemsg;

	$numberofinterfaces = 0;
	$iflist = array();
	$iflisttmp = get_configured_interface_with_descr();
	foreach ($iflisttmp as $if => $ifdesc) {
		if (!is_altq_capable(get_real_interface($if)))
			continue;
		if ($if == "lan")
			continue;
		$numberofinterfaces++;
		$iflist[$if] = $ifdesc;
	}
	$numberofconnections = intval($config['ezshaper']['step1']['numberofconnections']);
	if ($numberofconnections > $numberofinterfaces) {
		$savemsg=gettext("You have less interfaces than number of connections!");
		$stepid--;
		return;
	}

	$cfgname = "traffic_shaper_wizard.xml";

	$fields =& $pkg['step'][1]['fields']['field'];
		
	/*
	unset($config['ezshaper']['step2']);
	$config['ezshaper']['step2'] = array();
	write_config();
	*/
	$fields = array();

	$field = array();
	$field['name'] = "Setup LAN scheduler";
	$field['type'] = "listtopic";
	$fields[] = $field;
	$field = array();
	$field['displayname'] = "Download Scheduler";
	$field['name'] = "downloadscheduler";
       	$field['type'] = "select";
        $field['typehint'] = "Queueing discipline to apply on the download of this connection.";
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
	$field['bindstofield'] = "ezshaper->step2->downloadscheduler";
	$fields[] = $field;

	for ($i = 0; $i < $numberofconnections; $i++) {
		$field = array();
		$interface_friendly = $i+1;
		$field['name'] = "Setup connection speed and scheduler information for WAN #{$interface_friendly}";
		$field['type'] = "listtopic";
		$fields[] = $field;

		$field = array();
		$field['displayname'] = "Interface";
		$field['name'] = "conn{$i}interface";
		$field['type'] = "select";
		$field['typehint'] = "Interface of connection.";
		$field['options']['option'] = array();
		foreach ($iflist as $ifname => $ifdescr) {
			$opts = array();
			$opts['displayname'] = $ifdescr;
			$opts['name'] = $ifname;
			$opts['value'] = $ifname;
			$field['options']['option'][] = $opts;
		}
		$field['bindstofield'] = "ezshaper->step2->conn{$i}interface";
		$fields[] = $field;

		$field['displayname'] = "Upload Scheduler";
		$field['name'] = "conn{$i}uploadscheduler";
		$field['type'] = "select";
		$field['typehint'] = "Queueing discipline to apply on the upload of this connection.";
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
		$field['bindstofield'] = "ezshaper->step2->conn{$i}uploadscheduler";
		$fields[] = $field;

		$field = array();
		$field['displayname'] = "Connection Upload";
		$field['name'] = "conn{$i}upload";
		$field['type'] = "input";
		$field['bindstofield'] = "ezshaper->step2->conn{$i}upload";
		$field['combinefieldsbegin'] = "true";
		$fields[] = $field;

		$field = array();
		$field['combinefieldsend'] = "true";	
		$field['dontdisplayname'] = "true";
		$field['dontcombinecells'] = "true";
		$field['name'] = "conn{$i}uploadspeed";
		$field['typehint'] = "Upload bandwidth on this connection.";
		$field['type'] = "select";
		$field['options']['option'] = array();
		$opts = array();
		$opts['value'] = "Kb";
		$opts['name'] = "Kbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Mb";
		$opts['name'] = "Mbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Gb";
		$opts['name'] = "Gbit/s";
		$field['options']['option'][] = $opts;
		$field['bindstofield'] = "ezshaper->step2->conn{$i}uploadspeed";
		$fields[] = $field;

		$field = array();
		$field['displayname'] = "Connection Download";
		$field['name'] = "conn{$i}download";
		$field['type'] = "input";
		$field['bindstofield'] = "ezshaper->step2->conn{$i}download";
		$field['combinefieldsbegin'] = "true";
		$fields[] = $field;

		$field = array();
		$field['combinefieldsend'] = "true";
		$field['dontdisplayname'] = "true";
		$field['dontcombinecells'] = "true";
		$field['name'] = "conn{$i}downloadspeed";
		$field['typehint'] = "Download bandwidth on this connection.";
		$field['type'] = "select";
		$field['options']['option'] = array();
		$opts = array();
		$opts['value'] = "Kb";
		$opts['name'] = "Kbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Mb";
		$opts['name'] = "Mbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Gb";
		$opts['name'] = "Gbit/s";
		$field['options']['option'][] = $opts;
		$field['bindstofield'] = "ezshaper->step2->conn{$i}downloadspeed";
		$fields[] = $field;
	}
	$field = array();
	$field['name'] = "Next";
	$field['type'] = "submit";
	$fields[] = $field;
}

function step2_stepsubmitphpaction() {
	global $config;
	global $stepid, $savemsg;
	$sumdownloads = 0;
	
	/* Input Validation */
	$steps = intval($config['ezshaper']['step1']['numberofconnections']);
	for ($i = 0; $i < $steps; $i++) {
		for ($j = $j; $j < $steps; $j++) {
			$wannum = $i+1;
			if ($_POST["conn{$i}interface"] == $_POST["conn{$j}interface"]) {
				$savemsg=gettext("You cannot select the same interface for WAN# {$wannum} and WAN #{$j}.");
				$stepid--;
				return;
			}
			if (!is_numeric($_POST["conn{$i}upload"])) {
				$savemsg = gettext("WAN #{$wannum}: Upload bandwidth is not valid.");
				$stepid--;
				return;
			}
			if (!is_numeric($_POST["conn{$i}download"])) {
				$savemsg = gettext("WAN #{$wannum}: Download bandwidth is not valid.");
				$stepid--;
				return;
			}
			$upbw = $_POST["conn{$i}upload"];
			$downbw = $_POST["conn{$i}download"];
			if ($upbw < 1 || $downbw < 1) {
				$savemsg = gettext("WAN #{$wannum}: You cannot specify bandwidth less then 1!");
				$stepid--;
				return;
			}
			if ($upbw < 128 && $_POST["conn{$i}uploadspeed"] == "Kb" && trim($_POST["conn{$i}scheduler"]) == "CBQ") {
				$savemsg=gettext("WAN #{$wannum}: We do not support Bandwidths smaller than 128Kbit/s for CBQ scheduler.");
				$stepid--;
				return;
			}
			if ($downbw < 128 && $_POST["conn{$i}downloadspeed"] == "Kb" && trim($_POST["downloadscheduler"]) == "CBQ") {
				$savemsg=gettext("WAN #{$wannum}: We do not support Bandwidths smaller than 128Kbit/s for CBQ scheduler.");
				$stepid--;
				return;
			}

		}
	}		
	/* This is necessary since the wizard expects predefined fields. */
	unset($config['ezshaper']['step2']);
	$config['ezshaper']['step2'] = array();

	$config['ezshaper']['step2']['downloadscheduler'] = $_POST['downloadscheduler'];
	for ($i = 0; $i < $steps; $i++) {
		$config['ezshaper']['step2']["conn{$i}uploadscheduler"] = $_POST["conn{$i}uploadscheduler"];
		$config['ezshaper']['step2']["conn{$i}upload"] = $_POST["conn{$i}upload"];
		$config['ezshaper']['step2']["conn{$i}uploadspeed"] = $_POST["conn{$i}uploadspeed"];
		$config['ezshaper']['step2']["conn{$i}download"] = $_POST["conn{$i}download"];
		$config['ezshaper']['step2']["conn{$i}downloadspeed"] = $_POST["conn{$i}downloadspeed"];
		$config['ezshaper']['step2']["conn${i}interface"] = $_POST["conn{$i}interface"];
    }
}

function step3_stepbeforeformdisplay() {
	global $config, $pkg;
	global $stepid, $savemsg;

	$cfgname = "traffic_shaper_wizard.xml";

	$numberofconnections = intval($config['ezshaper']['step1']['numberofconnections']);

	$fields =& $pkg['step'][1]['fields']['field'];

	$voipfields =& $pkg['step'][2]['fields']['field'];

	$voipfields = array();
	$enablefields = array();

	$field = array();
	$field['name'] = "enable";
	$field['type'] = "checkbox";
	$field['typehint'] = "Prioritize Voice over IP traffic.";
	$field['bindstofield'] = "ezshaper->step3->enable";
	$field['descritpion'] = "This will raise the priority of VOIP traffic above all other traffic.";
	$voipfields[] = $field;

	$field = array();
	$field['name'] = "Next";
	$field['type'] = "submit";
	$voipfields[] = $field;

	$field = array();
	$field['name'] = "VOIP specific settings";
	$field['type'] = "listtopic";
	$voipfields[] = $field;

	$field['name'] = "Provider";
	$enablefields[] = "Provider";
	$field['type'] = "select";
	$field['description'] = "Choose Generic if your provider isn't listed.";
	$field['options']['option'] = array();
	$opts = array();
	$opts['name'] = "Generic (lowdelay)";
	$opts['value'] = "Generic";
	$field['options']['option'][] = $opts;
	$opts = array();
	$opts['name'] = "VoicePulse";
	$opts['value'] = "VoicePulse";
	$field['options']['option'][] = $opts;
	$opts = array();
	$opts['name'] = "Asterisk/Vonage";
	$opts['value'] = "Asterisk";
	$field['options']['option'][] = $opts;
	$opts = array();
	$opts['name'] = "PanasonicTDA";
	$opts['value'] = "Panasonic";
	$field['options']['option'][] = $opts;
	$field['bindstofield'] = "ezshaper->step3->provider";
	$voipfields[] = $field;

	$field = array();
	$field['name'] = "Address";
	$enablefields[] = "Address";
	$field['type'] = "inputalias";
	$field['description'] = "(Optional) If this is chosen, the provider field will be overridden. This allows you to just provide the IP address of the VOIP adaptor to prioritize. NOTE: You can also use a Firewall Alias in this location.";
	$field['message'] = "IP Address field is non-blank and doesn't look like an IP address.";
	$field['bindstofield'] = "ezshaper->step3->address";
	$voipfields[] = $field;

	$field = array();
	$interface_friendly = $i+1;
	$field['name'] = "Upload bandwidth for each WAN(interface)";
	$field['type'] = "listtopic";
	$voipfields[] = $field;			

	for ($i = 0; $i < $numberofconnections; $i++) {
		$field = array();
		$wannum = $i+1;
		$field['displayname'] = "WAN #{$wannum} upload";
		$field['name'] = "conn{$i}upload";
		$enablefields[] = "conn{$i}upload";
		$field['type'] = "input";
		$field['bindstofield'] = "ezshaper->step3->conn{$i}upload";
		$field['combinefieldsbegin'] = "true";
		$voipfields[] = $field;

		$field = array();
		$field['combinefieldsend'] = "true";
		$field['dontdisplayname'] = "true";
		$field['dontcombinecells'] = "true";
		$field['name'] = "conn{$i}uploadspeed";
		$enablefields[] = "conn{$i}uploadspeed";
		$friendly_interface = $i+1;
		$field['typehint'] = "Upload bandwidth guarantee for VOIP phone(s) on connection #{$friendly_interface}.";
		$field['type'] = "select";
		$field['options']['option'] = array();
		$opts = array();
		$opts['value'] = "Kb";
		$opts['name'] = "Kbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Mb";
		$opts['name'] = "Mbit/s";
		$field['options']['option'][] = $opts;
		$opts = array();
		$opts['value'] = "Gb";
		$opts['name'] = "Gbit/s";
		$field['options']['option'][] = $opts;
		$field['bindstofield'] = "ezshaper->step3->conn{$i}uploadspeed";
		$voipfields[] = $field;
	}

	$field = array();
	$interface_friendly = $i+1;
	$field['name'] = "Download bandwidth(speed) for Voice over IP phones";
	$field['type'] = "listtopic";
	$voipfields[] = $field;			

	$field = array();
	$field['name'] = "download";
	$field['displayname'] = "LAN download";
	$enablefields[] = "download";
	$field['type'] = "input";
	$field['bindstofield'] = "ezshaper->step3->download";
	$field['combinefieldsbegin'] = "true";
	$voipfields[] = $field;

	$field = array();
	$field['combinefieldsend'] = "true";
	$field['dontdisplayname'] = "true";
	$field['dontcombinecells'] = "true";
	$field['name'] = "downloadspeed";
	$enablefields[] = "downloadspeed";
	$field['typehint'] = "Download bandwidth guarantee for VOIP phone(s) on connections.";
	$field['type'] = "select";
	$field['options']['option'] = array();
	$opts = array();
	$opts['value'] = "Kb";
	$opts['name'] = "Kbit/s";
	$field['options']['option'][] = $opts;
	$opts = array();
	$opts['value'] = "Mb";
	$opts['name'] = "Mbit/s";
	$field['options']['option'][] = $opts;
	$opts = array();
	$opts['value'] = "Gb";
	$opts['name'] = "Gbit/s";
	$field['options']['option'][] = $opts;
	$field['bindstofield'] = "ezshaper->step3->downloadspeed";
	$voipfields[] = $field;

	$field = array();
        $field['name'] = "Next";
    	$field['type'] = "submit";
        $voipfields[] = $field;

	$voipfields[0]['enablefields'] = implode(",", $enablefields);
}

function step3_stepsubmitphpaction() {
	global $config;
	global $stepid, $savemsg;

	if($_POST['address']) {
		if(!is_ipaddroralias($_POST['address'])) {
			/* item is not an ip or alias.  error out */
			$savemsg=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
			$stepid--;
			return;
		}
	}

	$steps = intval($config['ezshaper']['step1']['numberofconnections']);
	for ($i = 0; $i < $steps; $i++) {
		if ($_POST["conn{$i}upload"]) {
			if (!is_numeric($_POST["conn{$i}upload"])) {
				$wannum = $i + 1;
				$savemsg = gettext("Upload bandwidth of WAN #{$wannum} is not valid.");
				$stepid--;
				return;
			}
			$factor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["conn{$i}uploadspeed"]);
			$ifbw = $factor * floatval($config['ezshaper']['step2']["conn{$i}upload"]);	
			$factor = wizard_get_bandwidthtype_scale($_POST["conn{$i}uploadspeed"]);
			$input_bw = $factor * floatval($_POST["conn{$i}upload"]);
			if ((0.8 * $ifbw) < $input_bw) {
				$friendly_interface = $i+1;
				$savemsg=gettext("You cannot set the VoIP upload bandwidth on WAN #{$friendly_interface} higher than 80% of the connection.");
				$stepid--;
				return;
			}	
		}
	}

	$config['ezshaper']['step3']['download'] = $_POST['download'];
	$config['ezshaper']['step3']['downloadspeed'] = $_POST['downloadspeed'];
	for ($i = 0; $i < $steps; $i++) {
		$config['ezshaper']['step3']["conn{$i}upload"] = $_POST["conn{$i}upload"];
		$config['ezshaper']['step3']["conn{$i}uploadspeed"] = $_POST["conn{$i}uploadspeed"];
	}
}

function step4_stepsubmitphpaction() {
	global $config;
	global $stepid, $savemsg;

	if ( $_POST['enable'] ) {
		if(!$_POST['bandwidth']) {
			$savemsg="You need to specify a value for bandwidth!";
			$stepid--;
			return;
		}
		if(!is_numeric($_POST['bandwidth'])) {
			$savemsg="The posted value is not a valid bandwidth.";
			$stepid--;
			return;
		}

		if ($_POST['bandwidthspeed'] <> "%") {
				$savemsg = gettext("Only percentage bandwidth specification is allowed.");
				$stepid--;
				return;
		}
		$bw = $_POST['bandwidth'];
		if($bw > 15 && $bw < 2) {
			$savemsg="Values should be between 2% and 15%!";
			$stepid--;
			return;
		}

		if($_POST['address'] <> "" && !is_ipaddroralias($_POST['address'])) {
			/* item is not an ip or alias.  error out */
			$savemsg=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
			$stepid--;
		}
	}
}

function step5_stepsubmitphpaction() {
	global $stepid, $savemsg;

	if ( $_POST['enable'] ) {
		if (isset($_POST['bandwidth']) && $_POST['bandwidth'] <> "") {
			if(!is_numeric($_POST['bandwidth'])) {
				$savemsg="Posted value is not a valid bandwidth.";
				$stepid--;
				return;
			}

			if ($_POST['bandwidthspeed'] <> "%") {
				$savemsg = gettext("Only percentage bandwidth specification is allowed.");
				$stepid--;
				return;
			}
			$bw = $_POST['bandwidth'];
			if($bw > 15 && $bw < 2) {
				$savemsg="Values should be between 2% and 15%!";
				$stepid--;
				return;
			}
		} 
	}
}

function step8_stepsubmitphpaction() {
	global $g, $config;

	/* save the new configuration */
	apply_all_choosen_items();

	/* reset rrd queues */
	system("rm -f /var/db/rrd/*queuedrops.rrd");
	system("rm -f /var/db/rrd/*queues.rrd");
	enable_rrd_graphing();

	/* apply the new configuration to the system */
	filter_configure();

	/* And we're no longer dirty! */
	clear_subsystem_dirty('shaper');

	update_filter_reload_status("Initializing");

	/* Head over and check out the groovy queue stats */
	header("Location: status_filter_reload.php");
}
function step9_stepsubmitphpaction() {
	global $g, $config;
	header("status_filter_reload.php");
}

function apply_all_choosen_items() {
	global $config, $g, $altq_list_queues;

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
	$gamesplist['battlenet'][] = array('Battle.NET-2', 'tcp', '1119', '1119', 'both');

$gamesplist['worldofwarcraft'] = array();
	$gamesplist[] = array('WoW', 'tcp', '3724', '3724', 'both');

$gamesplist['battlenet'] = array();
	/* Add battle.net only if WoW is selected and battle.net isn't */
	$gamesplist[] = array('Battle.NET', 'tcp', '6112', '6119', 'both');

$gamesplist['gunzonline'] = array();
	/* GunZ Online */
	$gamesplist['gunzonline'][] = array('GunZOnline', 'udp', '7700', '7700', 'both');

$gamesplist['xbox360'] = array();
	/* XBox360 */
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
	$othersplist['slingbox'] = array();
		$othersplist['slingbox'][] = array('Slingbox1', 'tcp', '5001', '5001', 'both');
		$othersplist['slingbox'][] = array('Slingbox2', 'udp', '5001', '5001', 'both');

	/* 
	 * Wipe previous config.
	 * Doing it here makes sense since we can wipe the previous config only after 
	 * the user decides to do so, finishing the wizard.
	 */
	if(isset($config['shaper']['queue']))
		unset($config['shaper']['queue']);
	/* XXX: This is redundant, because this should be handled by converter at startup. */
	if(isset($config['shaper']['rule']))
		unset($config['shaper']['rule']);
	foreach ($config['filter']['rule'] as $key => $rule)
		if ($rule['wizard'] == "yes")
			unset($config['filter']['rule'][$key]);

	/* restart the cached config */
	unset($altq_list_queues);
	$altq_list_queues = array();

	$steps = floatval($config['ezshaper']['step1']['numberofconnections']);
	$lanbw = 0;
	for ($i = 0; $i < $steps; $i++) {
		$down = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["conn{$i}downloadspeed"]);
		$input_bw = $config['ezshaper']['step2']["conn{$i}download"] * $down;
		$lanbw += $input_bw;
	}

	for ($i = 0; $i < $steps; $i++) {

		$altq =& new altq_root_queue();

		$tmppath = array();	
		$altq->SetInterface($config['ezshaper']['step2']["conn{$i}interface"]);
		$altq->SetScheduler($config['ezshaper']['step2']["conn{$i}uploadscheduler"]);
		$altq->SetBandwidth(floatval($config['ezshaper']['step2']["conn{$i}upload"]));
		$altq->SetBwscale($config['ezshaper']['step2']["conn{$i}uploadspeed"]);
		$altq->SetEnabled("on");
		$altq_list_queues[$altq->GetQname()] =& $altq;
		array_push($tmppath, $config['ezshaper']['step2']["conn{$i}interface"]);
		$altq->SetLink($tmppath);
		$altq->wconfig();

		$sched = $config['ezshaper']['step2']["conn{$i}uploadscheduler"];
		$voipbw =0;
		$voipbwunit = "Kb";
		$voip = false;
		$penalty = false;
		$penaltybw = 0;
		$penaltybwunit = "Kb";
		$p2p = false;
		$p2pcatchall = false;
		$p2pcatchbw = 0;
		$p2pcatchbwunit = "%";
		$games = false;
		$otherpriority = false;
		$remainbw = 0;
		$upfactor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["conn{$i}uploadspeed"]);
                $upbw = floatval($config['ezshaper']['step2']["conn{$i}upload"]) * $upfactor;

			if ($config['ezshaper']['step3']['enable']) {
					$voip = true;
					$voipbw = $config['ezshaper']['step3']["conn{$i}upload"];
					$voipbwunit = $config['ezshaper']['step3']["conn{$i}uploadspeed"];
					if ($sched != "HFSC") {
						if ($voipbwunit == "%") 
							$factor =  $upbw/100;
						else
							$factor = wizard_get_bandwidthtype_scale($voipbwunit);
						$remainbw += $voipbw * $factor;
					} else
						$remainbw += 32000; /* 32Kbit/s forHFSC linksharing */
			}
			if ($config['ezshaper']['step4']['enable']) {
					$penalty = true;
					$penaltybw = $config['ezshaper']['step4']['bandwidth'];
					$penaltybwunit = $config['ezshaper']['step4']['bandwidthunit'];
					if ($penaltybwunit == "%")
						$factor = $upbw/100;
					else
						$factor = wizard_get_bandwidthtype_scale($penaltybwunit);
					$remainbw += $penaltybw * $factor;
			} else {
					$penalty = false;
					$penaltybw = 0;
			}
			if ($config['ezshaper']['step5']['enable']) {
				$p2p = true;
				if ($config['ezshaper']['step5']['p2pcatchall']) {
						$p2pcatchall = true;
						$p2pcatchbw = $config['ezshaper']['step5']['bandwidth'];
						$p2pcatchbwunit = $config['ezshaper']['step5']['bandwidthunit'];
						if ($p2pcatchbwunit == "%")
							$factor = $upbw/100;
						else
							$factor = wizard_get_bandwidthtype_scale($p2pcatchbwunit);
						$remainbw += $p2pcatchbw * $factor;
				} else {
						$p2pcatchall = false;
						$p2pcatchbw = 0;
				}
			} else {
				$p2p = false;
				$p2pcatchall = false;
				$p2pcatchbw = 0;
			}
			if ($config['ezshaper']['step6']['enable']) {
				$games = true;
			} else {
				$games = false;
			}
			
			if ($config['ezshaper']['step7']['enable']) {
				$otherpriority = true;
			} else  {
				$otherpriority = false;
			}	
			$remainbw = round($remainbw / $upbw * 100, 2);

			if ($remainbw > 0 && $remainbw > 30) {
				$savemsg=gettext("Custom Bandwidths are greater than 30%. Please lower them for the wizard to continue.");
				header("Location: wizard.php?xml=traffic_shaper_wizard.xml&stepid=2&message={$message}");
				return;
			} else {
				$remainbw = 100 - $remainbw;
			}

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();	
			else if ($sched == "HFSC")
                                $q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qACK";
			$tmpcf['priority'] = 6;
			$tmpcf['ecn'] = "on";
			$tmpcf['enabled'] = "on";
			If ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 0.2;
				$tmpcf['bandwidthtype'] = "%";
			} 
			else if ($sched == "HFSC") {
				$lkbw = 0.20 * $remainbw;
				$tmpcf['linkshare3'] = "{$lkbw}%";
			 	$tmpcf['linkshare2'] = 100;
				$tmpcf['linkshare1'] = "0b";	
				$tmpcf['linkshare'] = "on";
				$tmpcf['bandwidth'] = $lkbw; 
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qACK");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qACK <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			if ($p2pcatchall)
				$tmpcf['name'] = "qOthersDefault";
			else
				$tmpcf['name'] = "qDefault";
			$tmpcf['priority'] = 3;
			$tmpcf['enabled'] = "on";
			if (!$p2pcatchall)
				$tmpcf['default'] = "on";
			$tmpcf['ecn'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, $tmpcf['name']);
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qDefault <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();

			if ($p2p) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qP2P";
				$tmpcf['priority'] = 1;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($p2pcatchall) {
					if ($sched == "CBQ") {
						$tmpcf['borrow'] = "on";
						$tmpcf['bandwidth'] = $p2pcatchbw;
						$tmpcf['bandwidthtype'] = $p2pcatchbwunit;
					} else if ($sched == "HFSC") {
						$tmpcf['linkshare'] = "on";
						$tmpcf['linkshare1'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['linkshare2'] = 300;
						$tmpcf['linkshare3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['upperlimit'] = "on";
						$tmpcf['upperlimit3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['bandwidth'] = $p2pcatchbw;
                                                $tmpcf['bandwidthtype'] = $p2pcatchbwunit;
					}
					$tmpcf['default'] = "on";				
					
				} else {
					if ($sched == "CBQ") {
						$tmpcf['borrow'] = "on";
						$tmpcf['bandwidth'] = $remainbw * 0.05; /* 5% bandwidth */
						$tmpcf['bandwidthtype'] = "%";
					} else if ($sched == "HFSC") {
						$tmpbw = $remainbw * 0.05; /* 5% bandwidth */
						$tmpcf['linkshare'] = "on";
                                                $tmpcf['linkshare1'] = "{$tmpbw}%";
                                                $tmpcf['linkshare2'] = 300;
                                                $tmpcf['linkshare3'] = "{$tmpbw}%";
                                                $tmpcf['upperlimit'] = "on";
                                                $tmpcf['upperlimit3'] = "{$tmpbw}%";
                                                $tmpcf['bandwidth'] = $tmpbw;
						$tmpcf['bandwidthtype'] = "%";
					}
				}
				array_push($tmppath, "qP2P");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qP2P <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
						
			if ($voip) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qVoIP";
				$tmpcf['priority'] = 7;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					if ($voipbw > 0) {
						$tmpcf['bandwidth'] = $voipbw;
						$tmpcf['bandwidthtype'] = $voipbwunit;
					} else {
						$tmpcf['bandwidth'] = $remainbw * 0.2; /* 20% bandwidth */
						$tmpcf['bandwidthtype'] = "%";
					}
				} else if ($sched == "HFSC") {
					if ($voipbw > 0) {
						$tmpcf['realtime3'] = "{$voipbw}{$voipbwunit}";
					} else {
						$voipbw = $remainbw * 0.20; /* 20% bandwidth */
						$tmpcf['realtime3'] = "{$voipbw}%";
					}
					$tmpcf['realtime'] = "on";
					$tmpcf['realtime2'] = "10";
					$tmpcf['bandwidth'] = 32;
					$tmpcf['realtime1'] = "0b";
					$tmpcf['bandwidthtype'] = "Kb";
				}
				array_push($tmppath, "qVoIP");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qVoIP <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			
			if ($games) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qGames";
				$tmpcf['priority'] = 5;
				$tmpcf['enabled'] = "on";
				$tmpcf['ecn'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 0.2; /* 20% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
                                        $gamesbw = $remainbw * 0.2; /* 20% bandwidth */
					$tmpcf['linkshare'] = "on";
                                       	$tmpcf['linkshare1'] = "0b";
                                        $tmpcf['linkshare2'] = "50";
                                        $tmpcf['linkshare3'] = "{$gamesbw}%";
					$tmpcf['bandwidth'] = "{$gamesbw}";
					$tmpcf['bandwidthtype'] = "%";
				}
				array_push($tmppath, "qGames");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qGames <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			
			if ($otherpriority) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qOthersHigh";
				$tmpcf['priority'] = 4;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					$tmpcf['linkshare'] = "on";
					$otherbw = $remainbw * 0.1; /* 10% bandwidth */
					$tmpcf['linkshare1'] = "0b";
					$tmpcf['linkshare2'] = 200;
					$tmpcf['linkshare3'] = "{$otherbw}%";
					$tmpcf['bandwidth'] = $otherbw;
					$tmpcf['bandwidthtype'] = "%";
				}
				array_push($tmppath, "qOthersHigh");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qHigh <br />";
				//var_dump($input_errors);
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
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					if ($penalty)
						$tmpcf['bandwidth'] = $penaltybw;
					else
						$tmpcf['bandwidth'] = $remainbw * 0.05; /* 5% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					if ($penalty) {
						$tmpcf['linkshare3'] = "{$penaltybw}{$penaltybwunit}";
						$tmpcf['linkshare1'] = "{$penaltybw}{$penaltybwunit}";
						$tmpcf['bandwidth'] = $penaltybw;
						$tmpcf['bandwidthtype'] = $penaltybwunit;
					} else {
						$lsbw = $remainbw * 0.05;
						$tmpcf['linkshare1'] = "{$lsbw}%"; /* 5% bandwidth */
						$tmpcf['linkshare3'] = "{$lsbw}%"; /* 5% bandwidth */
						$tmpcf['bandwidth'] = $lsbw;
                                                $tmpcf['bandwidthtype'] = "%";
					}
					$tmpcf['linkshare'] = "on";
					$tmpcf['linkshare2'] = 500;
				}
				array_push($tmppath, "qOthersLow");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qLow <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			array_pop($tmppath);
		}

/* LAN bandwidth ----------------------------------------------------------------------------------------- */

		$tmppath = array();
		$altq =& new altq_root_queue();

		$altq->SetInterface('lan');
		$altq->SetScheduler($config['ezshaper']['step2']["downloadscheduler"]);
		$altq->SetBandwidth($lanbw/1000);
		$altq->SetBwscale("Kb");
		$altq->SetEnabled("on");
		$altq_list_queues[$altq->GetQname()] =& $altq;
		array_push($tmppath, 'lan');
		$altq->SetLink($tmppath);
		//var_dump($input_errors);
		$altq->wconfig();

		$sched = $config['ezshaper']['step2']["downloadscheduler"];
		$voipbw =0;
		$voipbwunit = "%";
		$voip = false;
		$penalty = false;
		$penaltybw = 0;
		$penaltybwunit = "%";
		$p2p = false;
		$p2pcatchall = false;
		$p2pcatchbw = 0;
		$games = false;
		$otherpriority = false;
		$remainbw = 0;
		
		
			if ($config['ezshaper']['step3']['enable']) {
					$voip = true;
					$voipbw = $config['ezshaper']['step3']["download"];
					$voipbwunit = $config['ezshaper']['step3']["downloadspeed"];
					if ($sched != "HFSC") {
						if ($voipbwunit == "%") 
							$factor =  $lanbw/100;
						else
							$factor = wizard_get_bandwidthtype_scale($voipbwunit);
						$remainbw += $voipbw * $factor;
					} else
						$remainbw += 32000; /* 32Kbit/s forHFSC linksharing */
			}
			if ($config['ezshaper']['step4']['enable']) {
					$penalty = true;
					$penaltybw = $config['ezshaper']['step4']['bandwidth'];
					$penaltybwunit = $config['ezshaper']['step4']['bandwidthunit'];
					if ($penaltybwunit == "%")
						$factor = $lanbw/100;
					else
						$factor = wizard_get_bandwidthtype_scale($penaltybwunit);
					$remainbw += $penaltybw * $factor;
			} else {
					$penalty = false;
					$penaltybw = 0;
			}
			if ($config['ezshaper']['step5']['enable']) {
				$p2p = true;
				if ($config['ezshaper']['step5']['p2pcatchall']) {
						$p2pcatchall = true;
						$p2pcatchbw = $config['ezshaper']['step5']['bandwidth'];
						$p2pcatchbwunit = $config['ezshaper']['step5']['bandwidthunit'];
						if ($p2pcatchbwunit == "%")
							$factor = $lanbw/100;
						else
							$factor = wizard_get_bandwidthtype_scale($p2pcatchbwunit);
						$remainbw += $p2pcatchbw * $factor;
				} else {
						$p2pcatchall = false;
						$p2pcatchbw = 0;
				}
			} else {
				$p2p = false;
				$p2pcatchall = false;
				$p2pcatchbw = 0;
			}
			if ($config['ezshaper']['step6']['enable']) {
				$games = true;
			} else {
				$games = false;
			}
			
			if ($config['ezshaper']['step7']['enable']) {
				$otherpriority = true;
			} else  {
				$otherpriority = false;
			}	
			$remainbw = round($remainbw / $lanbw * 100, 2);

			if ($remainbw > 0 && $remainbw > 30) {
				$savemsg=gettext("Custom Bandwidths are greater than 30%. Please lower them for the wizard to continue.");
				header("Location: wizard.php?xml=traffic_shaper_wizard.xml&stepid=2&message={$message}");
				return;
			} else {
				$remainbw = 100 - $remainbw;
			}

                        if ($sched != "PRIQ") {
                        	if ($sched == "CBQ")
                                	$q =& new cbq_queue();
                        	else if ($sched == "HFSC")
                                	$q =& new hfsc_queue();
                        	$tmpcf = array();
                        	$tmpcf['name'] = "qInternet";
                        	//$tmpcf['priority'] = 6;
                        	$tmpcf['ecn'] = "on";
                        	$tmpcf['enabled'] = "on";
                        	If ($sched == "CBQ") {
                                	$tmpcf['bandwidth'] = $lanbw/1000;
                                	$tmpcf['bandwidthtype'] = "Kb";
                        	}
                        	else if ($sched == "HFSC") {
                                	$tmpcf['linkshare1'] = $tmpcf['linkshare3'] = $lanbw/1000 ."Kb";
                                	$tmpcf['upperlimit'] = "on";
                                	$tmpcf['upperlimit3'] = $lanbw/1000 ."Kb";


                                	$tmpcf['linkshare2'] = 100;
                                	//$tmpcf['linkshare1'] = "0b";
                                	$tmpcf['linkshare'] = "on";
                                	$tmpcf['bandwidth'] =  $lanbw/1000;
                                	$tmpcf['bandwidthtype'] = "Kb";
                        	}
                        	array_push($tmppath, "qInternet");
                        	$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
                        	//array_pop($tmppath);
                        	//echo "qInternet <br />";
                        	//var_dump($input_errors);
                        	$qtmp->wconfig();
                        	$altq =& $qtmp;
			}


			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();	
			else if ($sched == "HFSC")
                                $q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qACK";
			$tmpcf['priority'] = 6;
			$tmpcf['ecn'] = "on";
			$tmpcf['enabled'] = "on";
			If ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 0.2;
				$tmpcf['bandwidthtype'] = "%";
			} 
			else if ($sched == "HFSC") {
				$lkbw = 0.20 * $remainbw;
				$tmpcf['linkshare3'] = "{$lkbw}%";
			 	$tmpcf['linkshare2'] = 100;
				$tmpcf['linkshare1'] = "0b";	
				$tmpcf['linkshare'] = "on";
				$tmpcf['bandwidth'] = $lkbw; 
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, "qACK");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qACK <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();

			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			if ($p2pcatchall)
				$tmpcf['name'] = "qOthersDefault";
			else
				$tmpcf['name'] = "qDefault";
			$tmpcf['priority'] = 3;
			$tmpcf['enabled'] = "on";
			if (!$p2pcatchall)
				$tmpcf['default'] = "on";
			$tmpcf['ecn'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, $tmpcf['name']);
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qDefault <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();

			if ($p2p) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qP2P";
				$tmpcf['priority'] = 1;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($p2pcatchall) {
					if ($sched == "CBQ") {
						$tmpcf['borrow'] = "on";
						$tmpcf['bandwidth'] = $p2pcatchbw;
						$tmpcf['bandwidthtype'] = $p2pcatchbwunit;
					} else if ($sched == "HFSC") {
						$tmpcf['linkshare'] = "on";
						$tmpcf['linkshare1'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['linkshare2'] = 300;
						$tmpcf['linkshare3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['upperlimit'] = "on";
						$tmpcf['upperlimit3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
						$tmpcf['bandwidth'] = $p2pcatchbw;
                                                $tmpcf['bandwidthtype'] = $p2pcatchbwunit;
					}
					$tmpcf['default'] = "on";				
					
				} else {
					if ($sched == "CBQ") {
						$tmpcf['borrow'] = "on";
						$tmpcf['bandwidth'] = $remainbw * 0.05; /* 5% bandwidth */
						$tmpcf['bandwidthtype'] = "%";
					} else if ($sched == "HFSC") {
						$tmpbw = $remainbw * 0.05; /* 5% bandwidth */
						$tmpcf['linkshare'] = "on";
                                                $tmpcf['linkshare1'] = "{$tmpbw}%";
                                                $tmpcf['linkshare2'] = 300;
                                                $tmpcf['linkshare3'] = "{$tmpbw}%";
                                                $tmpcf['upperlimit'] = "on";
                                                $tmpcf['upperlimit3'] = "{$tmpbw}%";
                                                $tmpcf['bandwidth'] = $tmpbw;
						$tmpcf['bandwidthtype'] = "%";
					}
				}
				array_push($tmppath, "qP2P");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qP2P <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
						
			if ($voip) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qVoIP";
				$tmpcf['priority'] = 7;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					if ($voipbw > 0) {
						$tmpcf['bandwidth'] = $voipbw;
						$tmpcf['bandwidthtype'] = $voipbwunit;
					} else {
						$tmpcf['bandwidth'] = $remainbw * 0.2; /* 20% bandwidth */
						$tmpcf['bandwidthtype'] = "%";
					}
				} else if ($sched == "HFSC") {
					if ($voipbw > 0) {
						$tmpcf['realtime3'] = "{$voipbw}{$voipbwunit}";
					} else {
						$voipbw = $remainbw * 0.20; /* 20% bandwidth */
						$tmpcf['realtime3'] = "{$voipbw}%";
					}
					$tmpcf['realtime'] = "on";
					$tmpcf['realtime2'] = "10";
					$tmpcf['bandwidth'] = 32;
					$tmpcf['realtime1'] = "0b";
					$tmpcf['bandwidthtype'] = "Kb";
				}
				array_push($tmppath, "qVoIP");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qVoIP <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			
			if ($games) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qGames";
				$tmpcf['priority'] = 5;
				$tmpcf['enabled'] = "on";
				$tmpcf['ecn'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 0.2; /* 20% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
                                        $gamesbw = $remainbw * 0.2; /* 20% bandwidth */
					$tmpcf['linkshare'] = "on";
                                       	$tmpcf['linkshare1'] = "0b";
                                        $tmpcf['linkshare2'] = "50";
                                        $tmpcf['linkshare3'] = "{$gamesbw}%";
					$tmpcf['bandwidth'] = "{$gamesbw}";
					$tmpcf['bandwidthtype'] = "%";
				}
				array_push($tmppath, "qGames");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qGames <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			
			if ($otherpriority) {
				if ($sched == "PRIQ")
					$q =& new priq_queue();
				else if ($sched == "CBQ")
					$q =& new cbq_queue();
				else if ($sched == "HFSC")
					$q =& new hfsc_queue();
				$tmpcf = array();
				$tmpcf['name'] = "qOthersHigh";
				$tmpcf['priority'] = 4;
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 0.1; /* 10% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					$tmpcf['linkshare'] = "on";
					$otherbw = $remainbw * 0.1; /* 10% bandwidth */
					$tmpcf['linkshare1'] = "0b";
					$tmpcf['linkshare2'] = 200;
					$tmpcf['linkshare3'] = "{$otherbw}%";
					$tmpcf['bandwidth'] = $otherbw;
					$tmpcf['bandwidthtype'] = "%";
				}
				array_push($tmppath, "qOthersHigh");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qHigh <br />";
				//var_dump($input_errors);
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
				$tmpcf['ecn'] = "on";
				$tmpcf['enabled'] = "on";
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					if ($penalty)
						$tmpcf['bandwidth'] = $penaltybw;
					else
						$tmpcf['bandwidth'] = $remainbw * 0.05; /* 5% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					if ($penalty) {
						$tmpcf['linkshare3'] = "{$penaltybw}{$penaltybwunit}";
						$tmpcf['linkshare1'] = "{$penaltybw}{$penaltybwunit}";
						$tmpcf['bandwidth'] = $penaltybw;
						$tmpcf['bandwidthtype'] = $penaltybwunit;
					} else {
						$lsbw = $remainbw * 0.05;
						$tmpcf['linkshare1'] = "{$lsbw}%"; /* 5% bandwidth */
						$tmpcf['linkshare3'] = "{$lsbw}%"; /* 5% bandwidth */
						$tmpcf['bandwidth'] = $lsbw;
                                                $tmpcf['bandwidthtype'] = "%";
					}
					$tmpcf['linkshare'] = "on";
					$tmpcf['linkshare2'] = 500;
				}
				array_push($tmppath, "qOthersLow");
				$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
				array_pop($tmppath);
				//echo "qLow <br />";
				//var_dump($input_errors);
				$qtmp->wconfig();
			}
			array_pop($tmppath);

/* End LAN bandwidth ------------------------------------------------------------------------------------- */



	if (!is_array($config['filter']['rule']))
	        $config['filter']['rule'] = array();

			/* Rules */
			if ($penalty) {
				if( is_ipaddr($config['ezshaper']['step4']['address']) || is_alias($config['ezshaper']['step4']['address'])) {
				    $rule = array();
				    $rule['descr'] = gettext("Penalty Box");
				    $rule['direction'] = "out";
				    $rule['source']['any'] = TRUE;
				    $rule['defaultqueue'] = "qOthersLow";
				    $rule['source']['address'] = $config['ezshaper']['step4']['address'];
				    $rule['destination']['any'] = TRUE;
				    $rule['floating'] = "yes";
				    $rule['wizard'] = "yes";
				    $rule['enabled'] = "on";
				    $config['filter']['rule'][] = $rule;
	
				}
			}

			/* If user specifies an IP, we don't bother with providers */
			if ($voip) {
				if( is_ipaddr($config['ezshaper']['step3']['address']) || is_alias($config['ezshaper']['step3']['address'])) {
					/* create VOIP rules */
					$rule = array();
					$rule['descr'] = gettext("VOIP Adapter");
					$rule['direction'] = "out";
					$rule['protocol'] = "udp";
					$rule['defaultqueue'] = "qVoIP";
					$rule['source']['address'] = $config['ezshaper']['step3']['address'];
					$rule['destination']['any'] = TRUE;
					$rule['floating'] = "yes";
					$rule['wizard'] = "yes";
					$rule['enabled'] = "on";
					$config['filter']['rule'][] = $rule;
	
				} elseif( $config['ezshaper']['step3']['provider'] == "Generic" ) {
					/* create VOIP rules */
					$rule = array();
					$rule['descr'] = "DiffServ/Lowdelay/Upload";
					$rule['direction'] = "out";
					$rule['protocol'] = "udp";
					$rule['source']['any'] = TRUE;
					$rule['defaultqueue'] = "qVoIP";
					$rule['destination']['any'] = TRUE;
					$rule['iptos'] = "lowdelay";
					$rule['floating'] = "yes";
					$rule['wizard'] = "yes";
					$rule['enabled'] = "on";
					$config['filter']['rule'][] = $rule;
	
				} else {
					/* loop through voiplist[] */
					foreach ($voiplist[$config['ezshaper']['step3']['provider']] as $voip) {
						$rule = array();
						$rule['defaultqueue'] = 'qVoIP';
						$rule['direction'] = "out";
						$rule['source']['any'] = TRUE;
						$rule['destination']['any'] = TRUE;
						$rule['descr'] = "m_voip {$voip[0]} outbound";
						$rule['floating'] = "yes";
						$rule['wizard'] = "yes";
						$rule['enabled'] = "on";
						$rule['destination']['port'] = $voip[2]."-".$voip[3];
						if($voip[1] != '')
							$rule['protocol'] = $voip[1];
						$config['filter']['rule'][] = $rule;
					}
				}
			}			

			/* loop through p2plist[] */
			if ($p2p) {
				foreach($config['ezshaper']['step5'] as $key => $val) {
					if (!is_array($p2plist[$key]))
						continue;
					foreach ($p2plist[$key] as $p2pclient) {
						$rule = array();
						$rule['defaultqueue'] = 'qP2P';
						$rule['direction'] = "out";
						$rule['source']['any'] = TRUE;		
						$rule['destination']['any'] = TRUE;
						$rule['descr'] = "m_P2P {$p2pclient[0]} outbound";
						$rule['floating'] = "yes";
						$rule['wizard'] = "yes";
						$rule['destination']['port'] = $p2pclient[2]."-".$p2pclient[3];
						if($p2pclient[1] != '')
							$rule['protocol'] = $p2pclient[1];
						$config['filter']['rule'][] = $rule;
					}
				}
			}
			
			/* loop through gamesplist[] */
			if ($games) {
				foreach($config['ezshaper']['step6'] as $key => $val) {
					if (!is_array($gamesplist[$key]))
										continue;
					foreach ($gamesplist[$key] as $Gameclient) {
						$rule = array();
						$rule['defaultqueue'] = 'qGames';
						if ($Gameclient[1] == "tcp")
							$rule['ackqueue'] = 'qACK';
						$rule['direction'] = "out";
						$rule['source']['any'] = TRUE;
						$rule['destination']['any'] = TRUE;
						$rule['floating'] = "yes";
						$rule['wizard'] = "yes";
						$rule['enabled'] = "on";
						$rule['descr'] = "m_Game {$Gameclient[0]} outbound";
						$rule['destination']['port'] = $Gameclient[2]."-".$Gameclient[3];
						if($Gameclient[1] != '')
							$rule['protocol'] = $Gameclient[1];
						$config['filter']['rule'][] = $rule;
					}
				}
			}
			
			/* loop through othersplist[] */
			if ($otherpriority) {
				foreach($config['ezshaper']['step7'] as $key => $val) {
					if (!is_array($othersplist[$key]))
						continue;
					foreach ($othersplist[$key] as $otherclient) {
						$rule = array();
						switch ($val) {
						case "H":
							$rule['defaultqueue'] = 'qOthersHigh'; /* posted value H or L */
							if ($otherclient[1] == "tcp")
								$rule['ackqueue'] = 'qACK';
							$loop = 0;
						break;
						case "L":
							$rule['defaultqueue'] = 'qOthersLow'; /* posted value H or L */
							if ($otherclient[1] == "tcp")
                                                                $rule['ackqueue'] = 'qACK';
							$loop = 0;
						break;
						case "D":
							if ($p2pcatchall) {
								$loop = 0;
								$rule['defaultqueue'] = 'qOthersDefault';
								if ($otherclient[1] == "tcp")
									$rule['ackqueue'] = 'qACK';
							} else
								$loop = 1; /* It automitaclly goes to default queue */
						break;
						default:
							$loop = 1;
						}
						if (!$loop) {
							$rule['direction'] = "out";
							$rule['source']['any'] = TRUE;
							$rule['destination']['any'] = TRUE;
							$rule['floating'] = "yes";
							$rule['wizard'] = "yes";
							$rule['enabled'] = "on";
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

function wizard_get_bandwidthtype_scale($type) {
        switch ($type) {
        case "Gb":
                $factor = 1000 * 1000 * 1000;
        break;
        case "Mb":
                $factor = 1000 * 1000;
        break;
        case "Kb":
                $factor = 1000;
        break;
        case "b":
        default:
                $factor = 1;
        break;
        }
        return floatval($factor);
}

?>
