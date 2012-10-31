<?php
/*
	guiconfig.inc
	by Scott Ullrich, Copyright 2004, All rights reserved.
	originally based on of m0n0wall (http://m0n0.ch/wall)

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
/*
	pfSense_MODULE:	base
*/

/* Include authentication routines */
/* THIS MUST BE ABOVE ALL OTHER CODE */
if(!$nocsrf) {
	function csrf_startup() {
		csrf_conf('rewrite-js', '/csrf/csrf-magic.js');
		$timeout_minutes = isset($config['system']['webgui']['session_timeout']) ?  $config['system']['webgui']['session_timeout'] : 240;
		csrf_conf('expires', $timeout_minutes * 60);
		echo $GLOBALS['csrf']['expires'];
	}
	require_once("csrf/csrf-magic.php");
}
require_once("authgui.inc");

/* make sure nothing is cached */
if (!$omit_nocacheheaders) {
	header("Expires: 0");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
}

/* parse the configuration and include all configuration functions */
require_once("functions.inc");

/* Pull in all the gui related display classes) */
foreach (scandir("/usr/local/www/classes/") as $file) {
	if (stristr($file, ".inc") !== false) {
		require_once("classes/{$file}");
	}
}
/*
 *   if user has selected a custom template, use it.
 *   otherwise default to pfsense template
 */
if($config['theme'] <> "")
        $g['theme'] = $config['theme'];
else
        $g['theme'] = "pfsense";

/*
 *  If this device is an apple ipod/iphone
 *  switch the theme to one that works with it.
 */
$apple_ua = array("iPhone","iPod", "iPad");
foreach($apple_ua as $useragent)
	if(strstr($_SERVER['HTTP_USER_AGENT'], $useragent))
		$g['theme'] = "pfsense";

/* used by progress bar */
$lastseen = "-1";

$navlevelsep = ": ";	/* navigation level separator string */
$mandfldhtml = "";		/* display this before mandatory input fields */
$mandfldhtmlspc = "";	/* same as above, but with spacing */

/* Some ajax scripts still need access to GUI */
if(!$ignorefirmwarelock) {
	if (is_subsystem_dirty('firmwarelock')) {
		if (!$d_isfwfile) {
			header("Location: system_firmware.php");
			exit;
		} else {
			return;
		}
	}
}

$firewall_rules_dscp_types = array("af11", 
				"af12", 
				"af13", 
				"af21", 
				"af22", 
				"af23", 
				"af31", 
				"af32", 
				"af33", 
				"af41", 
				"af42", 
				"af43", 
				"EF", 
				"1-64", 
				"0x10",
				"0x04-0xfc");

$auth_server_types = array(
	'ldap' => "LDAP",
	'radius' => "Radius");

$ldap_urltypes = array(
	'TCP - Standard' => 389,
	'SSL - Encrypted' => 636);

$ldap_scopes = array(
	'one' => "One Level",
	'subtree' => "Entire Subtree");

$ldap_protvers = array(
	2,
	3);

$ldap_templates = array(

	'open' => array(
				'desc' => "OpenLDAP",
				'attr_user' => "cn",
				'attr_group' => "cn",
				'attr_member' => "member"),

	'msad' => array(
				'desc' => "Microsoft AD",
				'attr_user' => "samAccountName",
				'attr_group' => "cn",
				'attr_member' => "memberOf"),

	'edir' => array(
				'desc' => "Novell eDirectory",
				'attr_user' => "cn",
				'attr_group' => "cn",
				'attr_member' => "uniqueMember"));

$radius_srvcs = array(
	'both' => "Authentication and Accounting",
	'auth' => "Authentication",
	'acct' => "Accounting");

$netbios_nodetypes = array(
	'0' => "none",
	'1' => "b-node",
	'2' => "p-node",
	'4' => "m-node",
	'5' => "h-node");

/* some well knows ports */
$wkports = array(
	5999 => "CVSup",	
	53 => "DNS",
	21 => "FTP",
	3000 => "HBCI",
	80 => "HTTP",
	443 => "HTTPS",
	5190 => "ICQ",
	113 => "IDENT/AUTH",
	143 => "IMAP",
	993 => "IMAP/S",
	4500 => "IPsec NAT-T",
	500 => "ISAKMP",
	1701 => "L2TP",
	389 => "LDAP",
	1755 => "MMS/TCP",
	7000 => "MMS/UDP",
	445 => "MS DS",
	3389 => "MS RDP",
	1512 => "MS WINS",
	1863 => "MSN",
	119 => "NNTP",
	123 => "NTP",
	138 => "NetBIOS-DGM",
	137 => "NetBIOS-NS",
	139 => "NetBIOS-SSN",
	1194 => "OpenVPN",
	110 => "POP3",
	995 => "POP3/S",
	1723 => "PPTP",	
	1812 => "RADIUS",
	1813 => "RADIUS accounting",
	5004 => "RTP",
	5060 => "SIP",
	25 => "SMTP",
	465 => "SMTP/S",
	161 => "SNMP",
	162 => "SNMP-Trap",
	22 => "SSH",
	3478 => "STUN",
	3544 => "Teredo",
	23 => "Telnet",
	69 => "TFTP",
	5900 => "VNC");

/* TCP flags */
$tcpflags = array("fin", "syn", "rst", "psh", "ack", "urg");

$specialnets = array("pptp" => "PPTP clients", "pppoe" => "PPPoE clients", "l2tp" => "L2TP clients");

$spiflist = get_configured_interface_with_descr(false, true);
foreach ($spiflist as $ifgui => $ifdesc) {
	$specialnets[$ifgui] = $ifdesc . " net";
	$specialnets[$ifgui . 'ip'] = $ifdesc . " address";
}

$medias = array("auto" => "autoselect", "100full" => "100BASE-TX full-duplex",
	"100half" => "100BASE-TX half-duplex", "10full" => "10BASE-T full-duplex",
	"10half" => "10BASE-T half-duplex");

$wlan_modes = array("bss" => "Infrastructure (BSS)", "adhoc" => "Ad-hoc (IBSS)",
	"hostap" => "Access Point");

/* platforms that support firmware updating */
$fwupplatforms = array('pfSense', 'net45xx', 'net48xx', 'generic-pc', 'embedded', 'wrap', 'nanobsd');

function do_input_validation($postdata, $reqdfields, $reqdfieldsn, $input_errors) {

	/* check for bad control characters */
	foreach ($postdata as $pn => $pd) {
		if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
			$input_errors[] = "The field '" . $pn . "' contains invalid characters.";
		}
	}

	for ($i = 0; $i < count($reqdfields); $i++) {
		if ($_POST[$reqdfields[$i]] == "" && $_REQUEST[$reqdfields[$i]] == "") {
			$input_errors[] = "The field '" . $reqdfieldsn[$i] . "' is required.";
		}
	}
}

function print_input_errors($input_errors) {
	global $g;

	print <<<EOF
	<div id='inputerrorsdiv' name='inputerrorsdiv'>
	<p>
	<table border="0" cellspacing="0" cellpadding="4" width="100%">
	<tr>
		<td class="inputerrorsleft">
			<img src="/themes/{$g['theme']}/images/icons/icon_error.gif">
		</td>
		<td class="inputerrorsright">
			<span class="errmsg"><p>
				The following input errors were detected:
				<ul>
EOF;
		foreach ($input_errors as $ierr) {
			echo "<li>" . htmlspecialchars($ierr) . "</li>";
		}

	print <<<EOF2
				</ul>
			</span>
		</td></tr>
	</table>
	</div>
	</p>&nbsp;<br>
EOF2;
	
}

function verify_gzip_file($fname) {

    $returnvar = mwexec("/usr/bin/gzip -t " . escapeshellarg($fname));
	if ($returnvar != 0)
		return 0;
	else
		return 1;
}

function print_info_box_np($msg, $name="apply",$value="Apply changes") {
	global $g, $nifty_redbox, $nifty_blackbox, $nifty_background;

	// Set the Nifty background color if one is not set already (defaults to white)
	if($nifty_background == "")
		$nifty_background = "#FFF";

	if(stristr($msg, "apply") != false || stristr($msg, "save") != false || stristr($msg, "create") != false) {
		$savebutton = "<td class='infoboxsave'>";
		$savebutton .= "<input name=\"{$name}\" type=\"submit\" class=\"formbtn\" id=\"${name}\" value=\"{$value}\">";
		if($_POST['if']) 
			$savebutton .= "<input type='hidden' name='if' value='{$_POST['if']}'>";
		$savebutton.="</td>";
	}
	$nifty_redbox = "#990000";
	$nifty_blackbox = "#000000";
	
	$themename = $g['theme'];
	
	if(file_exists("/usr/local/www/themes/{$themename}/tabcontrols.php")) {
		$toeval = file_get_contents("/usr/local/www/themes/{$themename}/tabcontrols.php");
		eval($toeval);
	}
	
	if(file_exists("/usr/local/www/themes/{$themename}/infobox.php")) {
		$toeval = file_get_contents("/usr/local/www/themes/{$themename}/infobox.php");
		eval($toeval);
	}	
		
	if(!$savebutton) {
		$savebutton = '<td class="infoboxsave"><input value="Close" type="button" onClick="$(\'redboxtable\').hide();"></td>';
	}

	echo <<<EOFnp
	<table class='infobox' id='redboxtable'>
		<tr>
			<td>
				<div class='infoboxnp' id='redbox'>
					<table class='infoboxnptable2'>
						<tr>
							<td class='infoboxnptd'>
								&nbsp;&nbsp;&nbsp;<img class='infoboxnpimg' src="/themes/{$g['theme']}/images/icons/icon_exclam.gif" >
							</td>
							<td class='infoboxnptd2'>
								<b>{$msg}</b>
							</td>
							{$savebutton}
						</tr>
					</table>
				</div>
				<div>
					<p/>
				</div>
			</td>
		</tr>
	</table>
	<script type="text/javascript">
		NiftyCheck();
		Rounded("div#redbox","all","{$nifty_background}","{$nifty_redbox}","smooth");
		Rounded("td#blackbox","all","{$nifty_background}","{$nifty_blackbox}","smooth");
	</script>
EOFnp;

}

function print_info_box_np_undo($msg, $name="apply",$value="Apply changes", $undo) {
	global $g;
	
	if(stristr($msg, "apply") != false || stristr($msg, "save") != false || stristr($msg, "create") != false) {
		$savebutton = "<td class='infoboxsave'><nobr>";
		$savebutton .= " <input type=\"button\" value=\"Undo\" onClick=\"document.location='{$undo}'\">";
		$savebutton .= " <input name=\"{$name}\" type=\"submit\" class=\"formbtn\" id=\"${name}\" value=\"{$value}\">";
		$savebutton.="</nobr></td>";
		if($_POST['if']) 
			$savebutton .= "<input type='hidden' name='if' value='{$_POST['if']}'>";
	}
	$nifty_redbox = "#990000";
	$nifty_blackbox = "#000000";
	
	$themename = $g['theme'];
	
	if(file_exists("/usr/local/www/themes/{$themename}/tabcontrols.php")) {
		$toeval = file_get_contents("/usr/local/www/themes/{$themename}/tabcontrols.php");
		eval($toeval);
	}
	
	if(file_exists("/usr/local/www/themes/{$themename}/infobox.php")) {
		$toeval = file_get_contents("/usr/local/www/themes/{$themename}/infobox.php");
		eval($toeval);
	}	
	
		
	if(!$savebutton) {
		$savebutton = '<td class="infoboxsave"><input value="Close" type="button" onClick="$(\'redboxtable\').hide();"></td>';
	}

	echo <<<EOFnp
	<table class='infobox' id='redboxtable'>
		<tr>
			<td>
				<div class='infoboxnp' id='redbox'>
					<table class='infoboxnptable2'>
						<tr>
							<td class='infoboxnptd'>
								&nbsp;&nbsp;&nbsp;<img class='infoboxnpimg' src="/themes/{$g['theme']}/images/icons/icon_exclam.gif" >
							</td>
							<td class='infoboxnptd2'>
								<b>{$msg}</b>
							</td>
							{$savebutton} 
							{$undobutton}
						</tr>
					</table>
				</div>
				<div>
					<p/>
				</div>
			</td>
		</tr>
	</table>
	<script type="text/javascript">
		NiftyCheck();
		Rounded("div#redbox","all","#FFF","{$nifty_redbox}","smooth");
		Rounded("td#blackbox","all","#FFF","{$nifty_blackbox}","smooth");
	</script>
EOFnp;

}

function print_info_box($msg) {
	print_info_box_np($msg);
}

function get_std_save_message($ok) {
	global $d_sysrebootreqd_path;
	$filter_related = false;
	$filter_pages = array("nat", "filter");
	$to_return = "The changes have been applied successfully.";
	foreach($filter_pages as $fp) 
		if(stristr($_SERVER['SCRIPT_FILENAME'], $fp))
			$filter_related = true;	
	if($filter_related)
		$to_return .= "<br/>You can also <a href='status_filter_reload.php'>monitor</a> the filter reload progress.";
	return $to_return;
}

function pprint_address($adr) {
	global $specialnets;

	if (isset($adr['any'])) {
		$padr = "*";
	} else if ($adr['network']) {
		$padr = $specialnets[$adr['network']];
	} else {
		$padr = $adr['address'];
	}

	if (isset($adr['not']))
		$padr = "! " . $padr;

	return $padr;
}

function pprint_port($port) {
	global $wkports;

	$pport = "";

	if (!$port)
		return "*";
	else {
		$srcport = explode("-", $port);
		if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
			$pport = $srcport[0];
			if ($wkports[$srcport[0]]) {
				$pport .= " (" . $wkports[$srcport[0]] . ")";
			}
		} else
			$pport .= $srcport[0] . " - " . $srcport[1];
	}

	return $pport;
}

function firewall_check_for_advanced_options(&$item) {
        $item_set = "";
        if($item['max'])
                $item_set .= "max {$item['max']} ";
        if($item['max-src-nodes'])
                $item_set .= "max-src-nodes {$item['max-src-nodes']} ";
        if($item['max-src-conn'])
                $item_set .= "max-src-conn {$item['max-src-conn']} ";
        if($item['max-src-states'])
                $item_set .= "max-src-states {$item['max-src-states']} ";
        if($item['statetype'] != "keep state" && $item['statetype'] != "")
                $item_set .= "statetype {$item['statetype']} ";
        if($item['statetimeout'])
                $item_set .= "statetimeout {$item['statetimeout']} ";
        if($item['nosync'])
                $item_set .= "nosync ";
        if($item['max-src-conn-rate'])
                $item_set .= "max-src-conn-rate {$item['max-src-conn-rate']} ";
        if($item['max-src-conn-rates'])
                $item_set .= "max-src-conn-rates {$item['max-src-conn-rates']} ";
        if($item['gateway'])
                $item_set .= "gateway {$item['gateway']} ";
        if($item['dnpipe'])
                $item_set .= "limiter {$item['dnpipe']} ";
        if($item['pdnpipe'])
                $item_set .= "limiter {$item['pdnpipe']} ";
        if($item['l7container'])
                $item_set .= "layer7 {$item['l7container']} ";
        if($item['tag'])
                $item_set .= "tag {$item['tag']} ";
        if($item['tagged'])
                $item_set .= "tagged {$item['tagged']} ";
        if(isset($item['allowopts']))
                $item_set .= "allowopts ";
        if(isset($item['disablereplyto']))
                $item_set .= "disable reply-to ";
        if($item['tcpflags_any'] || $item['tcpflags1'] || $item['tcpflags2'])
                $item_set .= "tcpflags set";

        return $item_set;
}

function gentitle($title) {
	global $navlevelsep;
	if(!is_array($title))
		return $title;
	else
		return join($navlevelsep, $title);
}

function genhtmltitle($title) {
        global $config;
        return gentitle($title);
}

/* update the changedesc and changecount(er) variables */
function update_changedesc($update) {
	global $changedesc;
	global $changecount;

	$changedesc .= " {$update}";
	$changecount++;
}

function clear_log_file($logfile = "/var/log/system.log") {
	global $config, $g;
	exec("/usr/bin/killall syslogd");
	if(isset($config['system']['disablesyslogclog'])) {
		unlink($logfile);
		touch($logfile);
	} else {
		if(isset($config['system']['usefifolog'])) 
			exec("/usr/sbin/fifolog_create -s 511488 {$logfile}");
		else
			exec("/usr/sbin/clog -i -s 511488 {$logfile}");
	}
	system_syslogd_start();	
}

function dump_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	global $g, $config;
	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$logarr = "";
	$grepline = "  ";
	if(is_array($grepfor))
		foreach($grepfor as $agrep)
			$grepline .= " | grep \"$agrep\"";
	if(is_array($grepinvert))
		foreach($grepinvert as $agrep)
			$grepline .= " | grep -v \"$agrep\"";
	if(file_exists($logfile) && filesize($logfile) == 0) {
		$logarr = array("Log file started.");
	} else {
		if($config['system']['disablesyslogclog']) {
			exec("cat {$logfile}{$grepline} | /usr/bin/tail {$sor} -n {$tail}", $logarr);
		} else {
			if(isset($config['system']['usefifolog']))
				exec("/usr/sbin/fifolog_reader {$logfile}{$grepline} | /usr/bin/tail {$sor} -n {$tail}", $logarr);
			else
				exec("/usr/sbin/clog {$logfile}{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n {$tail}", $logarr);
		}
	}
	foreach ($logarr as $logent) {
			$logent = preg_split("/\s+/", $logent, 6);
			echo "<tr valign=\"top\">\n";
			if ($withorig) {
					if(isset($config['system']['usefifolog'])) {
						$entry_date_time = htmlspecialchars(date("F j, Y, g:i a","" . $logent[1] . ""));
						$entry_text = htmlspecialchars($logent[5]);
					} else {
						$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
						$entry_text = htmlspecialchars($logent[4] . " " . $logent[5]);
					}
					echo "<td class=\"listlr\" nowrap>{$entry_date_time}</td>\n";
					echo "<td class=\"listr\">{$entry_text}</td>\n";

			} else {
					echo "<td class=\"listlr\" colspan=\"2\">" . htmlspecialchars($logent[5]) . "</td>\n";
			}
			echo "</tr>\n";
	}
}

function return_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	global $g, $config;
	$sor = (isset($config['syslog']['reverse']) || $grepreverse) ? "-r" : "";
	$logarr = "";
	$grepline = "  ";
	if(is_array($grepfor))
		foreach($grepfor as $agrep)
			$grepline .= " | grep \"$agrep\"";
	if(is_array($grepinvert))
		foreach($grepinvert as $agrep)
			$grepline .= " | grep -v \"$agrep\"";
	if($config['system']['disablesyslogclog']) {
		exec("cat {$logfile}{$grepline} | /usr/bin/tail {$sor} -n {$tail}", $logarr);
	} else {
		if(isset($config['system']['usefifolog'])) {
			exec("/usr/sbin/fifolog_reader {$logfile}{$grepline} | /usr/bin/tail {$sor} -n {$tail}", $logarr);			
		} else {
			exec("/usr/sbin/clog {$logfile}{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n {$tail}", $logarr);
		}
	}
	return($logarr);
}

/* Check if variable has changed, update and log if it has
 * returns true if var changed
 * varname = variable name in plain text
 * orig = original value
 * new = new value
 */
function update_if_changed($varname, & $orig, $new) {
	if (is_array($orig) && is_array($new)) {
		$a_diff = array_diff($orig, $new);
		foreach ($a_diff as $diff) {
			update_changedesc("removed {$varname}: \"{$diff}\"");
		}
		$a_diff = array_diff($new, $orig);
		foreach ($a_diff as $diff) {
			update_changedesc("added {$varname}: \"{$diff}\"");
		}
		$orig = $new;
		return true;

	} else {
		if ($orig != $new) {
			update_changedesc("{$varname}: \"{$orig}\" -> \"{$new}\"");
			$orig = $new;
			return true;
		}
	}
	return false;
}

function address_to_pconfig($adr, &$padr, &$pmask, &$pnot, &$pbeginport, &$pendport) {

        if (isset($adr['any']))
                $padr = "any";
        else if ($adr['network'])
                $padr = $adr['network'];
        else if ($adr['address']) {
                list($padr, $pmask) = explode("/", $adr['address']);
                if (!$pmask)
                        $pmask = 32;
        }

        if (isset($adr['not']))
                $pnot = 1;
        else
                $pnot = 0;

        if ($adr['port']) {
                list($pbeginport, $pendport) = explode("-", $adr['port']);
                if (!$pendport)
                        $pendport = $pbeginport;
	} else if (!is_alias($pbeginport) && !is_alias($pendport)) {
		$pbeginport = "any";
		$pendport = "any";
        }
}

function pconfig_to_address(&$adr, $padr, $pmask, $pnot=false, $pbeginport=0, $pendport=0) {

        $adr = array();

        if ($padr == "any")
                $adr['any'] = true;
        else if (is_specialnet($padr))
                $adr['network'] = $padr;
        else {
                $adr['address'] = $padr;
                if ($pmask != 32)
                        $adr['address'] .= "/" . $pmask;
        }

        if ($pnot)
                $adr['not'] = true;
        else
                unset($adr['not']);

        if (($pbeginport != 0) && ($pbeginport != "any")) {
                if ($pbeginport != $pendport)
                        $adr['port'] = $pbeginport . "-" . $pendport;
                else
                        $adr['port'] = $pbeginport;
        }

        if(is_alias($pbeginport)) {
                $adr['port'] = $pbeginport;
        }
}

function is_specialnet($net) {
        global $specialsrcdst;

	if(!$net) 
		return false;
        if (in_array($net, $specialsrcdst))
                return true;
        else
                return false;
}

//function to create widget tabs when called
function display_widget_tabs(& $tab_array) {	
	echo "<div id='tabs'>";
	$tabscounter = 0;
	foreach ($tab_array as $ta) {
	$dashpos = strpos($ta[2],'-');
	$tabname = $ta[2] . "-tab";
	$tabclass = substr($ta[2],0,$dashpos);
	$tabclass = $tabclass . "-class";
		if ($ta[1] == true) {
			$tabActive = "table-cell";
			$tabNonActive = "none";
		} 
		else {
			$tabActive = "none";
			$tabNonActive = "table-cell";
		}
		echo "<div id='{$ta[2]}-active' class='{$tabclass}-tabactive' style='display:{$tabActive}; background-color:#EEEEEE; color:black;'>";
		echo "<B>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</B>";
		echo "</div>";
		
		echo "<div id='{$ta[2]}-deactive' class='{$tabclass}-tabdeactive' style='display:{$tabNonActive}; background-color:#777777; color:white; cursor: pointer;' onClick=\"return changeTabDIV('{$ta[2]}')\">";
		echo "<B>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</B>";
		echo "</div>";
	}
	
	echo "<script type=\"text/javascript\">";
	echo "NiftyCheck();\n";
	echo "Rounded(\"div.{$tabclass}-tabactive\",\"top\",\"#CCCCCC\",\"#EEEEEE\",\"smooth\");\n";
	echo "Rounded(\"div.{$tabclass}-tabdeactive\",\"top\",\"#CCCCCC\",\"#777777\",\"smooth\");\n";
	echo "</script>";
	echo "</div>";
}


// Return inline javascript file or CSS to minimizie 
// request count going back to server.
function outputJavaScriptFileInline($javascript) {
	if(file_exists($javascript)) {
		echo "\n<script type=\"text/javascript\">\n";
		include($javascript);
		echo "\n</script>\n";
	} else {
		echo "\n\n<!-- Could not location file:  {$javascript} -->\n\n";
	}
}



function outputCSSPrintFileInline($css) {
	if(file_exists($css)) {
		echo "\n<style media=\"print\" type=\"text/css\">\n";
		include($css);
		echo "\n</style>\n";
	} else {
		echo "\n\n<!-- Could not location file:  {$css} -->\n\n";
	}
}


function outputCSSFileInline($css) {
	if(file_exists($css)) {
		echo "\n<style type=\"text/css\">\n";
		include($css);
		echo "\n</style>\n";
	} else {
		echo "\n\n<!-- Could not location file:  {$css} -->\n\n";
	}
}

$rfc2616 = array(
	100 => "100 Continue",
	101 => "101 Switching Protocols",
	200 => "200 OK",
	201 => "201 Created",
	202 => "202 Accepted",
	203 => "203 Non-Authoritative Information",
	204 => "204 No Content",
	205 => "205 Reset Content",
	206 => "206 Partial Content",
	300 => "300 Multiple Choices",
	301 => "301 Moved Permanently",
	302 => "302 Found",
	303 => "303 See Other",
	304 => "304 Not Modified",
	305 => "305 Use Proxy",
	306 => "306 (Unused)",
	307 => "307 Temporary Redirect",
	400 => "400 Bad Request",
	401 => "401 Unauthorized",
	402 => "402 Payment Required",
	403 => "403 Forbidden",
	404 => "404 Not Found",
	405 => "405 Method Not Allowed",
	406 => "406 Not Acceptable",
	407 => "407 Proxy Authentication Required",
	408 => "408 Request Timeout",
	409 => "409 Conflict",
	410 => "410 Gone",
	411 => "411 Length Required",
	412 => "412 Precondition Failed",
	413 => "413 Request Entity Too Large",
	414 => "414 Request-URI Too Long",
	415 => "415 Unsupported Media Type",
	416 => "416 Requested Range Not Satisfiable",
	417 => "417 Expectation Failed",
	500 => "500 Internal Server Error",
	501 => "501 Not Implemented",
	502 => "502 Bad Gateway",
	503 => "503 Service Unavailable",
	504 => "504 Gateway Timeout",
	505 => "505 HTTP Version Not Supported"
);

function is_rfc2616_code($code) {
	global $rfc2616;
	if (isset($rfc2616[$code]))
		return true;
	else
		return false;
}

function print_rfc2616_select($tag, $current){
	global $rfc2616;

	/* Default to 200 OK if not set */
	if ($current == "")
		$current = 200;

	echo "<select id=\"{$tag}\" name=\"{$tag}\">\n";	
	foreach($rfc2616 as $code => $message) {
		if ($code == $current) {
			$sel = " selected";
		} else {
			$sel = "";
		}
		echo "<option value=\"{$code}\"{$sel}>{$message}</option>\n";
	}
}

// Useful debugging function, much cleaner than print_r
function echo_array($array,$return_me=false){
    if(is_array($array) == false){
        $return = "The provided variable is not an array.";
    }else{
        foreach($array as $name=>$value){
            if(is_array($value)){
                $return .= "";
                $return .= "['<b>$name</b>'] {<div style='margin-left:10px;'>\n";
                $return .= echo_array($value,true);
                $return .= "</div>}";
                $return .= "\n\n";
            }else{
                if(is_string($value)){
                    $value = "\"$value\"";
                }
                $return .= "['<b>$name</b>'] = $value\n\n";
            }
        }
    }
    if($return_me == true){
        return $return;
    }else{
        echo "<pre>".$return."</pre>";
    }
}

/****f* pfsense-utils/display_top_tabs
 * NAME
 *   display_top_tabs - display tabs with rounded edges
 * INPUTS
 *   $text      - array of tabs
 * RESULT
 *   null
 ******/
function display_top_tabs(& $tab_array, $no_drop_down = false) {
        global $HTTP_SERVER_VARS;
        global $config;
        global $g;
		global $tab_array_indent;
		global $tab_array_space;
		global $tab_array_char_limit;
		
        /*  does the user have access to this tab?
         *  master user has access to everything.
         *  if the user does not have access, simply
         *  unset the tab item.
         */
		 
		/* empty string code */ 
		if ($tab_array_indent == '') {
			$tab_array_indent = 0;
		}

		if ($tab_array_space == '') {
			$tab_array_space = 1;
		}

		if ($tab_array_char_limit == '') {
			$tab_array_char_limit = 92;
		}

        $tab_temp = array ();
        foreach ($tab_array as $ta)
                if(isAllowedPage($ta[2]))
                        $tab_temp[] = $ta;
        /*
                // FIXME :      if the checks are not good enough
                //                      in isAllowedPage, it needs to be
                //                      fixed instead of kludging here

                // TODO: humm what shall we do with pkg_edit.php and pkg.php?
                if ((strpos($link, "pkg.php")) !== false || (strpos($link, "pkg_edit.php")) !== false) {
                        $pos_equal = strpos($link, "=");
                        $pos_xmlsuffix = strpos($link, ".xml");
                        // do we match an absolute url including ?xml= foo
                        if(!isAllowedPage($link, $allowed))
                                $link = substr($link, $pos_equal +1, ($pos_xmlsuffix - $pos_equal +3));
                }
                // next check - what if the basename contains a query string?
                if ((strpos($link, "?")) !== false) {
                        $pos_qmark = strpos($link, "?");
                        $link = substr($link, 0, $pos_qmark);
                }
                $authorized_text = print_r($allowed, true);
                if(is_array($authorized))
                        if (in_array(basename($link), $authorized))
        */

        unset ($tab_array);
        $tab_array = & $tab_temp;

        $tab_active_bg   = "#EEEEEE";
        $tab_inactive_bg = "#777777";
        $nifty_tabs_corners = "#FFF";
        $font_color = "white";

        /* if tabcontrols.php exist for a theme, allow it to be overriden */
        $themename = $config['theme'];
        $filename = "/usr/local/www/themes/{$themename}/tabcontrols.php";
        if(file_exists($filename)) {
                $eval_code = file_get_contents($filename);
                eval($eval_code);
        }

        $tabcharcount = 0;
        foreach ($tab_array as $ta)
                $tabcharcount = $tabcharcount + strlen($ta[0]);

		if($no_drop_down == true) {
			$tabcharcount = 0;
			unset($tab_array_char_limit);
		}

        // If the character count of the tab names is > 670
        // then show a select item dropdown menubox.
         if($tabcharcount > $tab_array_char_limit) {
                echo "Currently viewing: ";
                echo "<select name='TabSelect' onchange='tabs_will_go(this)'>\n";
                foreach ($tab_array as $ta) {
                        if($ta[1]=="true")
                                $selected = " SELECTED";
                        else
                                $selected = "";
                        // Onclick in option will not work in some browser
                        // echo "<option onClick=\"document.location='{$ta[2]}';\"{$selected}>{$ta['0']}</option>\n";
                        echo "<option value=\"{$ta[2]}\"{$selected}>{$ta['0']}</option>\n";
                }
                echo "</select>\n<p/>";
                echo "<script type=\"text/javascript\">";
                echo " function tabs_will_go(obj){ document.location = obj.value; }";
                echo "</script>";
        }  else {
                echo "<div class=\"newtabmenu\" style=\"margin:{$tab_array_space}px {$tab_array_indent}px; width:775px;\">\n";
                echo "<!-- Tabbed bar code-->\n";
				echo "<ul class=\"newtabmenu\">\n";
                $tabscounter = 0;
                foreach ($tab_array as $ta) {
                        if ($ta[1] == true) {
								echo "  <li class=\"newtabmenu_active\"><a href=\"{$ta[2]}\"><span>{$ta[0]}</span></a></li>\n";
                        } else {
								echo "  <li><a href=\"{$ta[2]}\"><span>{$ta[0]}</span></a></li>\n";
                        }
                        $tabscounter++;
                }
                echo "</ul>\n</div>\n";
        }
}

function add_package_tabs($tabgroup, & $tab_array) {
        global $config, $g;

        if(!is_array($config['installedpackages']))
                return;
        if(!is_array($config['installedpackages']['tab']))
                return;

        foreach($config['installedpackages']['tab'] as $tab) {
                if ($tab['group'] !== $group)
                        continue;
                $tab_entry = array();
                if($tab['name']) {
                        $tab_entry[] = $tab['name'];
                        $tab_entry[] = false;
                        $tab_entry[] = $tab['url'];
                        $tab_array[] = $tab_entry;
                }
        }
}

function rule_popup($src,$srcport,$dst,$dstport){
	global $config;
	$aliases_array = array();
	if($config['aliases']['alias'] <> "" and is_array($config['aliases']['alias']))
	{
		$span_begin = "";
                $alias_src_span_begin = "";
                $alias_src_span_end = "";
                $alias_src_port_span_begin = "";
                $alias_src_port_span_end = "";
                $alias_dst_span_begin = "";
                $alias_dst_span_end = "";
                $alias_dst_port_span_begin = "";
                $alias_dst_port_span_end = "";
                $alias_content_text = "";
        	foreach($config['aliases']['alias'] as $alias_name)
        	{
                	$alias_addresses = explode (" ", $alias_name['address']);
                	$alias_details = explode ("||", $alias_name['detail']);
                	$alias_objects_with_details = "";
                	$counter = 0;
			if ($alias_name['url']) {
				$alias_objects_with_details .= $alias_name['url'] . "<br/>";
			}
                	foreach($alias_addresses as $alias_ports_address)
                	{
                        	$alias_objects_with_details .= $alias_addresses[$counter];
                        	$alias_detail_default = strpos ($alias_details[$counter],"Entry added");
                        	if ($alias_details[$counter] != "" && $alias_detail_default === False){
                                	$alias_objects_with_details .=" - " . $alias_details[$counter];
                        	}
                        	$alias_objects_with_details .= "<br>";
                        	$counter++;
                	}
                	//max character length for caption field
                	$maxlength = 60;

                	$alias_descr_substr = $alias_name['descr'];
                	$alias_content_text = htmlspecialchars($alias_objects_with_details);
                	$alias_caption = htmlspecialchars($alias_descr_substr . ":");
                	$strlength = strlen ($alias_caption);
                	if ($strlength >= $maxlength)
                        	$alias_caption = substr($alias_caption, 0, $maxlength) . "...";

			$alias_caption_escaped = str_replace("'", "\'", $alias_caption);
			$alias_content_escaped = str_replace("'", "\'", $alias_content_text);
			$span_begin = "<span style=\"cursor: help;\" onmouseover=\"domTT_activate(this, event, 'content', '<h1>$alias_caption_escaped</h1><p>$alias_content_escaped</p>', 'trail', true, 'delay', 0, 'fade', 'both', 'fadeMax', 93, 'styleClass', 'niceTitle');\" onmouseout=\"this.style.color = ''; domTT_mouseout(this, event);\"><U>";

                	if ($alias_name['name'] == $src)
                        	$alias_src_span_begin = $span_begin;
                	if ($alias_name['name'] == $srcport)
                        	$alias_src_port_span_begin = $span_begin;
                	if ($alias_name['name'] == $dst)
                        	$alias_dst_span_begin = $span_begin;
                	if ($alias_name['name'] == $dstport)
                        	$alias_dst_port_span_begin = $span_begin;
        	}
        	$descriptions = array ();
        	$descriptions['src'] = $alias_src_span_begin;
        	$descriptions['srcport'] = $alias_src_port_span_begin;
        	$descriptions['dst'] = $alias_dst_span_begin;
        	$descriptions['dstport'] = $alias_dst_port_span_begin;

        	return $descriptions;
  	}
}

?>