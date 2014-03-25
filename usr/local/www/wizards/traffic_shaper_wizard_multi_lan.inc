<?php
/*
        traffic_shaper_wizard_multi_lan.inc
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2006 Bill Marquette - bill.marquette@gmail.com.
        Copyright (C) 2006 Scott Ullrich - sullrich@pfsense.com.
      	Copyright (C) 2008-2010 Ermal Luçi
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
		$savemsg=gettext("You need to specify the number of local interfaces connected.");
		$stepid--;
	}			
	if (intval($_POST['numberofconnections']) < 1) {
		$savemsg=gettext("The number of local connection needs to be greater than 1.");
		$stepid--;
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
		if ($if == "wan")
			continue;
                $numberofinterfaces++;
                $iflist[$if] = $ifdesc;
	}
	$numberofconnections = intval($config['ezshaper']['step1']['numberofconnections']);
	if ($numberofconnections > $numberofinterfaces) {
		$savemsg=gettext("You do not have {$numberofconnections} of local interfaces!");
		$stepid--;
		return;
	}

	$fields =& $pkg['step'][1]['fields']['field'];
		
	/*
	unset($config['ezshaper']['step2']);
	$config['ezshaper']['step2'] = array();
	write_config();
	*/
	$fields = array();

	$field = array();
	$field['name'] = "Setup WAN(upload) scheduler";
	$field['type'] = "listtopic";
	$fields[] = $field;
	$field = array();
	$field['name'] = "Upload Scheduler";
	$field['type'] = "select";
	$field['typehint'] = "Queueing discipline to apply on the upload of this link.";
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
	$field['bindstofield'] = "ezshaper->step2->uploadscheduler";
	$fields[] = $field;

	$field = array();
	$field['name'] = "Setup link speed details";
	$field['type'] = "listtopic";
	$fields[] = $field;
	$field = array();
	$field['name'] = "connupload";
	$field['displayname'] = "Link Upload";
	$field['type'] = "input";
	$field['bindstofield'] = "ezshaper->step2->connupload";
	$field['combinefieldsbegin'] = "true";
	$fields[] = $field;

	$field = array();
	$field['combinefieldsend'] = "true";	
	$field['dontdisplayname'] = "true";
	$field['dontcombinecells'] = "true";
	$field['name'] = "connuploadspeed";
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
	$field['bindstofield'] = "ezshaper->step2->connuploadspeed";
	$fields[] = $field;

	$field = array();
	$field['name'] = "conndownload";
	$field['displayname'] = "Link Download";
	$field['type'] = "input";
	$field['bindstofield'] = "ezshaper->step2->conndownload";
	$field['combinefieldsbegin'] = "true";
	$fields[] = $field;

	$field = array();
	$field['combinefieldsend'] = "true";
	$field['dontdisplayname'] = "true";
	$field['dontcombinecells'] = "true";
	$field['name'] = "conndownloadspeed";
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
	$field['bindstofield'] = "ezshaper->step2->conndownloadspeed";
	$fields[] = $field;

	for ($i = 0; $i < $numberofconnections; $i++) {

		$field = array();
		$interface_friendly = $i+1;
		$field['name'] = "Setup connection speed and scheduler information for LAN interface #{$interface_friendly}";
		$field['type'] = "listtopic";
		$fields[] = $field;

		$field = array();
		$field['name'] = "conn{$i}interface";
		$field['displayname'] = "LAN interface";
		$field['type'] = "select";
		$field['typehint'] = "Interface of this connection.";
		$field['options']['option'] = array();
		$ifselect = 0;
		foreach ($iflist as $ifname => $ifdescr) {
			if ($ifselect > 0 && $ifselect == ($i + 1))
				$field['value'] = $ifname;
                        $opts = array();
                        $opts['displayname'] = $ifdescr;
                        $opts['name'] = $ifname;
                        $opts['value'] = $ifname;
                        $field['options']['option'][] = $opts;
			$ifselect++;
		}
		$field['bindstofield'] = "ezshaper->step2->conn{$i}interface";
		$fields[] = $field;

		$field = array();
		$field['name'] = "conn{$i}downloadscheduler";
		$field['displayname'] = "LAN Scheduler";
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
		$field['bindstofield'] = "ezshaper->step2->conn{$i}downloadscheduler";
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
		for ($j = $i + 1; $j <= $steps; $j++) {
			if ($_POST["conn{$i}interface"] == $_POST["conn{$j}interface"]) {
				$savemsg=gettext("You cannot select the same interface for different LAN #{$i} and LAN #{$j}.");
				$stepid--;
				return;
			}
		}
	}
	if (!is_numeric($_POST["connupload"])) {
		$savemsg= gettext("Upload bandwidth of connection is not valid.");
		$stepid--;
		return;
	}
	if (!is_numeric($_POST["conndownload"])) {
		$savemsg= gettext("Download bandwidth of connection is not valid.");
		$stepid--;
		return;
	}
	$upbw = floatval($_POST["connupload"]);
	$downbw = floatval($_POST["conndownload"]);
	if ($upbw < 1 || $downbw < 1) {
		$savemsg = gettext("You cannot specify bandwidth less than 1!");
		$stepid--;
		return;
	}
	if (intval($upbw) < 128 && $_POST["connuploadspeed"] == "Kb" && trim($_POST["connscheduler"]) == "CBQ") {
		$savemsg=gettext("We do not support Bandwidths smaller than 128Kbit/s for CBQ scheduler.");
		$stepid--;
		return;
	}
	if ($downbw < 128 && $_POST["conndownloadspeed"] == "Kb" && trim($_POST["downloadscheduler"]) == "CBQ") {
		$savemsg=gettext("We do not support Bandwidths smaller than 128Kbit/s for CBQ scheduler.");
		$stepid--;
		return;
	}

	/* This is necessary since the wizard expects pecnefined fields. */
	unset($config['ezshaper']['step2']);
	$config['ezshaper']['step2'] = array();

	$config['ezshaper']['step2']['uploadscheduler'] = $_POST['uploadscheduler'];
	$config['ezshaper']['step2']["connupload"] = $_POST["connupload"];
	$config['ezshaper']['step2']["connuploadspeed"] = $_POST["connuploadspeed"];
	$config['ezshaper']['step2']["conndownload"] = $_POST["conndownload"];
	$config['ezshaper']['step2']["conndownloadspeed"] = $_POST["conndownloadspeed"];

	for ($i = 0; $i < $steps; $i++) {
		$config['ezshaper']['step2']["conn{$i}downloadscheduler"] = $_POST["conn{$i}downloadscheduler"];
		$config['ezshaper']['step2']["conn${i}interface"] = $_POST["conn{$i}interface"];
    }
}

function step3_stepsubmitphpaction() {
	global $config;
	global $stepid, $savemsg;

	if (!$_POST['enable'])
                return;

	if($_POST['address']) {
		if(!is_ipaddroralias($_POST['address'])) {
			/* item is not an ip or alias.  error out */
			$savemsg=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
			$stepid--;
			return;
		}
	}

	if (!is_numeric($_POST["connupload"]) || !is_numeric($_POST['conndownload'])) {
		$savemsg = gettext("Upload or download bandwidth is not valid.");
		$stepid--;
		return;
	}

	if ($_POST["connuploadspeed"] == "%") {
		if (intval($_POST['connupload']) > 80) {
			$savemsg=gettext("You cannot set the VoIP upload bandwidth on connection {$i} higher than 80% of the connection.");
			$stepid--;
			return;
		}
	} else {
		$factor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["connuploadspeed"]);
		$ifbw = $factor * floatval($config['ezshaper']['step2']["connupload"]);	
		$factor = wizard_get_bandwidthtype_scale($_POST["connuploadspeed"]);
		$input_bw = $factor * floatval($_POST["connupload"]);
		if ((0.8 * $ifbw) < $input_bw) {
			$savemsg=gettext("You cannot set the VoIP upload bandwidth on WAN connection higher than 80% of the connection.");
			$stepid--;
			return;
		}
	}	
	if ($_POST["conndownloadspeed"] == "%") {
		if (intval($_POST['conndownload']) > 80) {
			$savemsg=gettext("You cannot set the VoIP download bandwidth on connection higher than 80% of the connection.");
			$stepid--;
			return;
		}
	} else {
		$factor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["conndownloadspeed"]);
		$ifbw = $factor * floatval($config['ezshaper']['step2']["conndownload"]);	
		$factor = wizard_get_bandwidthtype_scale($_POST["conndownloadspeed"]);
		$input_bw = $factor * floatval($_POST["conndownload"]);
		if ((0.8 * $ifbw) < $input_bw) {
			$savemsg=gettext("You cannot set the VoIP download bandwidth on connection higher than 80% of the connection.");
			$stepid--;
			return;
		}
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
		if($bw > 15 || $bw < 2) {
			$savemsg="Values should be between 2% and 15%!";
			$stepid--;
			return;
		}

		if($_POST['address']) {
			if(!is_ipaddroralias($_POST['address'])) {
				/* item is not an ip or alias.  error out */
				$savemsg=gettext("Address must be a valid IP address or Firewall Alias.  Please correct this value to continue.");
				$stepid--;
				return;
			}	
		}
	}
}

function step5_stepsubmitphpaction() {
	global $stepid, $savemsg;

	if ( $_POST['enable'] ) {
		if ($_POST['p2pcatchall']) {
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
			if($bw > 15 || $bw < 2) {
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
	header("Location: status_filter_reload.php");
	exit;
}

function apply_all_choosen_items() {
	global $config, $g, $altq_list_queues, $gamesplist, $voiplist, $othersplist, $p2plist;

	require_once("wizardapp.inc");

	/* 
	 * Wipe previous config.
	 * Doing it here makes sense since we can wipe the previous config only after 
	 * the user decides to do so, finishing the wizard.
	 */
	if (isset($config['shaper']['queue']))
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

	for ($i = 0; $i < $steps; $i++) {

		$tmppath = array();	
		$altq =& new altq_root_queue();

		$altq->SetInterface($config['ezshaper']['step2']["conn{$i}interface"]);
		$altq->SetScheduler($config['ezshaper']['step2']["conn{$i}downloadscheduler"]);
		//$altq->SetBandwidth(floatval($config['ezshaper']['step2']["conndownload"]));
		//$altq->SetBwscale($config['ezshaper']['step2']["conndownloadspeed"]);
		$altq->SetEnabled("on");
		$altq_list_queues[$altq->GetQname()] =& $altq;
		array_push($tmppath, $config['ezshaper']['step2']["conn{$i}interface"]);
		$altq->SetLink($tmppath);
		$altq->wconfig();

		$sched = $config['ezshaper']['step2']["conn{$i}downloadscheduler"];
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
		$upfactor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["conndownloadspeed"]);
		$upbw = floatval($config['ezshaper']['step2']["conndownload"]) * $upfactor;

		if ($config['ezshaper']['step3']['enable']) {
			$voip = true;
			$voipbw = $config['ezshaper']['step3']["conndownload"];
			$voipbwunit = $config['ezshaper']['step3']["conndownloadspeed"];
			if ($voipbwunit == "%") 
				$factor =  $upbw/100;
			else
				$factor = wizard_get_bandwidthtype_scale($voipbwunit);
			$remainbw += $voipbw * $factor;
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
			header("Location: wizard.php?xml=traffic_shaper_wizard_multi_lan.xml&stepid=2&message={$savemsg}");
			exit;
		} else {
			$remainbw = 100 - $remainbw;
		}

		if (!$p2pcatchall) {
			if ($sched == "PRIQ")
				$q =& new priq_queue();
			else if ($sched == "CBQ")
				$q =& new cbq_queue();
			else if ($sched == "HFSC")
				$q =& new hfsc_queue();
			$tmpcf = array();
			$tmpcf['name'] = "qLink";
			$tmpcf['priority'] = 2;
			$tmpcf['enabled'] = "on";
			$tmpcf['default'] = "on";
			$tmpcf['qlimit'] = 500;
			$tmpcf['ecn'] = "on";
			if ($sched == "CBQ") {
				$tmpcf['borrow'] = "on";
				$tmpcf['bandwidth'] = 20; /* 20% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			} else if ($sched == "HFSC") {
				$tmpcf['bandwidth'] = 20; /* 20% bandwidth */
				$tmpcf['bandwidthtype'] = "%";
			}
			array_push($tmppath, $tmpcf['name']);
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qDefault <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();
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
				$tmpcf['bandwidth'] = floatval($config['ezshaper']['step2']["conndownload"]);
				$tmpcf['bandwidthtype'] = $config['ezshaper']['step2']["conndownloadspeed"];
			} else if ($sched == "HFSC") {
				$tmpcf['linkshare3'] = 
					floatval($config['ezshaper']['step2']["conndownload"]) . $config['ezshaper']['step2']["conndownloadspeed"];
				$tmpcf['upperlimit'] = "on";
				$tmpcf['upperlimit3'] = 
					floatval($config['ezshaper']['step2']["conndownload"]) . $config['ezshaper']['step2']["conndownloadspeed"];

				$tmpcf['linkshare'] = "on";
				$tmpcf['bandwidth'] =  floatval($config['ezshaper']['step2']["conndownload"]);
				$tmpcf['bandwidthtype'] = $config['ezshaper']['step2']["conndownloadspeed"];
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
					$tmpcf['linkshare3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
					$tmpcf['upperlimit'] = "on";
					$tmpcf['upperlimit3'] = "{$p2pcatchbw}{$p2pcatchbwunit}";
					$tmpcf['bandwidth'] = $p2pcatchbw;
                                               $tmpcf['bandwidthtype'] = $p2pcatchbwunit;
				}
				$tmpcf['default'] = "on";				
				$tmpcf['qlimit'] = 500;
			} else {
				if ($sched == "CBQ") {
					$tmpcf['borrow'] = "on";
					$tmpcf['bandwidth'] = $remainbw * 0.05; /* 5% bandwidth */
					$tmpcf['bandwidthtype'] = "%";
				} else if ($sched == "HFSC") {
					$tmpbw = $remainbw * 0.05; /* 5% bandwidth */
					$tmpcf['linkshare'] = "on";
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
				$tmpcf['bandwidth'] = 32;
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
			$tmpcf['priority'] = 3;
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
					$tmpcf['bandwidth'] = $penaltybw;
					$tmpcf['bandwidthtype'] = $penaltybwunit;
				} else {
					$lsbw = $remainbw * 0.05;
					$tmpcf['linkshare3'] = "{$lsbw}%"; /* 5% bandwidth */
					$tmpcf['bandwidth'] = $lsbw;
                                               $tmpcf['bandwidthtype'] = "%";
				}
				$tmpcf['linkshare'] = "on";
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

	/* WAN bandwidth ----------------------------------------------------------------------------------------- */

		$tmppath = array();
		$altq =& new altq_root_queue();

		$altq->SetInterface('wan');
		$altq->SetScheduler($config['ezshaper']['step2']["uploadscheduler"]);
		$altq->SetBandwidth($config['ezshaper']['step2']['connupload']);
		$altq->SetBwscale($config['ezshaper']['step2']['connuploadspeed']);
		$altq->SetEnabled("on");
		$altq_list_queues[$altq->GetQname()] =& $altq;
		array_push($tmppath, 'wan');
		$altq->SetLink($tmppath);
		//var_dump($input_errors);
		$altq->wconfig();

		$sched = $config['ezshaper']['step2']["uploadscheduler"];
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
		$upfactor = wizard_get_bandwidthtype_scale($config['ezshaper']['step2']["connuploadspeed"]);
		$lanbw = floatval($config['ezshaper']['step2']["connupload"]) * $upfactor;

		if ($config['ezshaper']['step3']['enable']) {
			$voip = true;
			$voipbw = $config['ezshaper']['step3']["connupload"];
			$voipbwunit = $config['ezshaper']['step3']["connuploadspeed"];
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
			header("Location: wizard.php?xml=traffic_shaper_wizard_multi_lan.xml&stepid=2&message={$savemsg}");
			exit;
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
			$tmpcf['linkshare'] = "on";
			$tmpcf['bandwidth'] = $lkbw; 
			$tmpcf['bandwidthtype'] = "%";
		}
		array_push($tmppath, "qACK");
		$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
		array_pop($tmppath);
		//echo "$lkbw $remainbw qACK <br />";
		//var_dump($input_errors);
		//var_dump($qtmp);
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
				$tmpcf['bandwidth'] = 32;
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
					$tmpcf['bandwidth'] = $penaltybw;
					$tmpcf['bandwidthtype'] = $penaltybwunit;
				} else {
					$lsbw = $remainbw * 0.05;
					$tmpcf['linkshare3'] = "{$lsbw}%"; /* 5% bandwidth */
					$tmpcf['bandwidth'] = $lsbw;
                                               $tmpcf['bandwidthtype'] = "%";
				}
				$tmpcf['linkshare'] = "on";
			}
			array_push($tmppath, "qOthersLow");
			$qtmp =& $altq->add_queue($q, &$tmpcf, &$tmppath, $input_errors);
			array_pop($tmppath);
			//echo "qLow <br />";
			//var_dump($input_errors);
			$qtmp->wconfig();
		}
		array_pop($tmppath);

		/* End WAN bandwidth ------------------------------------------------------------------------------------- */



	if (!is_array($config['filter']['rule']))
	        $config['filter']['rule'] = array();

		/* Rules */
		if ($penalty) {
			if( is_ipaddr($config['ezshaper']['step4']['address']) || is_alias($config['ezshaper']['step4']['address'])) {
			    $rule = array();
			    $rule['type'] = "match";
			    $rule['interface'] = "wan";
			    $rule['descr'] = gettext("Penalty Box");
			    $rule['defaultqueue'] = "qOthersLow";
			    $rule['source']['address'] = $config['ezshaper']['step4']['address'];
			    $rule['destination']['any'] = TRUE;
			    $rule['floating'] = "yes";
			    $rule['wizard'] = "yes";
			    $rule['enabled'] = "on";
			    $rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
			    $config['filter']['rule'][] = $rule;

			}
		}

		/* If user specifies an IP, we don't bother with providers */
		if ($voip) {
			if( is_ipaddr($config['ezshaper']['step3']['address']) || is_alias($config['ezshaper']['step3']['address'])) {
				/* create VOIP rules */
				$rule = array();
				$rule['type'] = "match";
				//$rule['interface'] = "wan";
				$rule['descr'] = gettext("Connections From Upstream SIP Server");
				$rule['protocol'] = "udp";
				$rule['defaultqueue'] = "qVoIP";
				$rule['source']['address'] = $config['ezshaper']['step3']['address'];
				$rule['destination']['any'] = TRUE;
				$rule['floating'] = "yes";
				$rule['wizard'] = "yes";
				$rule['enabled'] = "on";
				$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
				$config['filter']['rule'][] = $rule;

				$rule = array();
				$rule['type'] = "match";
				//$rule['interface'] = "wan";
				$rule['descr'] = gettext("Connections To Upstream SIP Server");
				$rule['protocol'] = "udp";
				$rule['defaultqueue'] = "qVoIP";
				$rule['source']['any'] = TRUE;
				$rule['destination']['address'] = $config['ezshaper']['step3']['address'];
				$rule['floating'] = "yes";
				$rule['wizard'] = "yes";
				$rule['enabled'] = "on";
				$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
				$config['filter']['rule'][] = $rule;

			} elseif( $config['ezshaper']['step3']['provider'] == "Generic" ) {
				/* create VOIP rules */
				$rule = array();
				$rule['type'] = "match";
			        $rule['interface'] = "wan";
				$rule['descr'] = "DiffServ/Lowdelay/Upload";
				$rule['protocol'] = "udp";
				$rule['source']['any'] = TRUE;
				$rule['defaultqueue'] = "qVoIP";
				$rule['destination']['any'] = TRUE;
				$rule['iptos'] = "lowdelay";
				$rule['floating'] = "yes";
				$rule['wizard'] = "yes";
				$rule['enabled'] = "on";
				$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
				$config['filter']['rule'][] = $rule;

			} else {
				/* loop through voiplist[] */
				foreach ($voiplist[$config['ezshaper']['step3']['provider']] as $voip) {
					$rule = array();
					$rule['type'] = "match";
					$rule['interface'] = "wan";
					$rule['defaultqueue'] = 'qVoIP';
					$rule['source']['any'] = TRUE;
					$rule['destination']['any'] = TRUE;
					$rule['descr'] = "m_voip {$voip[0]} outbound";
					$rule['floating'] = "yes";
					$rule['wizard'] = "yes";
					$rule['enabled'] = "on";
					$rule['destination']['port'] = $voip[2]."-".$voip[3];
					if($voip[1] != '')
						$rule['protocol'] = $voip[1];
					$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
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
					$rule['type'] = "match";
					$rule['interface'] = "wan";
					$rule['defaultqueue'] = 'qP2P';
					$rule['source']['any'] = TRUE;		
					$rule['destination']['any'] = TRUE;
					$rule['descr'] = "m_P2P {$p2pclient[0]} outbound";
					$rule['floating'] = "yes";
					$rule['wizard'] = "yes";
					$rule['destination']['port'] = $p2pclient[2]."-".$p2pclient[3];
					if($p2pclient[1] != '')
						$rule['protocol'] = $p2pclient[1];
					$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
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
					$rule['type'] = "match";
					$rule['interface'] = "wan";
					$rule['defaultqueue'] = 'qGames';
					if ($Gameclient[1] == "tcp")
						$rule['ackqueue'] = 'qACK';
					$rule['source']['any'] = TRUE;
					$rule['destination']['any'] = TRUE;
					$rule['floating'] = "yes";
					$rule['wizard'] = "yes";
					$rule['enabled'] = "on";
					$rule['descr'] = "m_Game {$Gameclient[0]} outbound";
					$rule['destination']['port'] = $Gameclient[2]."-".$Gameclient[3];
					if($Gameclient[1] != '')
						$rule['protocol'] = $Gameclient[1];
					$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
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
					$rule['type'] = "match";
					$rule['interface'] = "wan";
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
						$rule['created'] = make_config_revision_entry(null, gettext("Traffic Shaper Wizard"));
						$config['filter']['rule'][] = $rule;
					}
				}
			}
		}
				
		write_config();
}

function wizard_get_bandwidthtype_scale($type = "b") {
	switch ($type) {
	case "Gb":
	        $factor = 1024 * 1024 * 1024;
		break;
	case "Mb":
	        $factor = 1024 * 1024;
		break;
	case "Kb":
	        $factor = 1024;
		break;
	case "b":
	default:
	        $factor = 1;
		break;
	}
	return intval($factor);
}

?>
