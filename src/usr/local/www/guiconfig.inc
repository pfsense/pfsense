<?php
/*
	guiconfig.inc
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */
/*
	pfSense_MODULE: base
*/

/* Include authentication routines */
/* THIS MUST BE ABOVE ALL OTHER CODE */
if (!$nocsrf) {
	function csrf_startup() {
		csrf_conf('rewrite-js', '/csrf/csrf-magic.js');
		$timeout_minutes = isset($config['system']['webgui']['session_timeout']) ? $config['system']['webgui']['session_timeout'] : 240;
		csrf_conf('expires', $timeout_minutes * 60);
	}
	require_once("csrf/csrf-magic.php");
}

/* make sure nothing is cached */
if (!$omit_nocacheheaders) {
	header("Expires: 0");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate");
	header("Pragma: no-cache");
}

header("X-Frame-Options: SAMEORIGIN");
require_once("authgui.inc");

/* parse the configuration and include all configuration functions */
require_once("functions.inc");

/* Include the autoloader for all the GUI display classes */
require_once("classes/autoload.inc.php");

/* Set the default interface language */
if ($config['system']['language'] <> "") {
	$g['language'] = $config['system']['language'];
} elseif ($g['language'] == "") {
	$g['language'] = 'en_US';
}

set_language($g['language']);

/* used by progress bar */
$lastseen = "-1";

$navlevelsep = ": ";	/* navigation level separator string */
$mandfldhtml = "";		/* display this before mandatory input fields */
$mandfldhtmlspc = "";	/* same as above, but with spacing */

/* Some ajax scripts still need access to GUI */
if (!$ignorefirmwarelock) {
	if (is_subsystem_dirty('firmwarelock')) {
		if (!$d_isfwfile) {
			header("Location: system_update.php");
			exit;
		} else {
			return;
		}
	}
}

/* Reserved table names to avoid collision */
$reserved_table_names = array(
	"bogons",
	"bogonsv6",
	"negate_networks",
	"snort2c",
	"sshlockout",
	"tonatsubnets",
	"virusprot",
	"vpn_networks",
	"webConfiguratorlockout"
);

$firewall_rules_dscp_types = array(
	"af11",
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
	"VA",
	"EF",
	"cs1",
	"cs2",
	"cs3",
	"cs4",
	"cs5",
	"cs6",
	"cs7",
	"0x01",
	"0x02",
	"0x04");

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
	'8' => "h-node");

/* some well known ports */
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
	587 => "SUBMISSION",
	3544 => "Teredo",
	23 => "Telnet",
	69 => "TFTP",
	5900 => "VNC");

/* TCP flags */
$tcpflags = array("fin", "syn", "rst", "psh", "ack", "urg", "ece", "cwr");

$specialnets = array("(self)" => "This Firewall", "pppoe" => "PPPoE clients", "l2tp" => "L2TP clients");

$spiflist = get_configured_interface_with_descr(false, true);
foreach ($spiflist as $ifgui => $ifdesc) {
	$specialnets[$ifgui] = $ifdesc . " net";
	$specialnets[$ifgui . 'ip'] = $ifdesc . " address";
}

$medias = array(
	"auto" => "autoselect",
	"100full" => "100BASE-TX full-duplex",
	"100half" => "100BASE-TX half-duplex",
	"10full" => "10BASE-T full-duplex",
	"10half" => "10BASE-T half-duplex");

$wlan_modes = array(
	"bss" => "Infrastructure (BSS)",
	"adhoc" => "Ad-hoc (IBSS)",
	"hostap" => "Access Point");

function do_input_validation($postdata, $reqdfields, $reqdfieldsn, &$input_errors) {

	/* check for bad control characters */
	foreach ($postdata as $pn => $pd) {
		if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
			$input_errors[] = sprintf(gettext("The field %s contains invalid characters."), $pn);
		}
	}

	for ($i = 0; $i < count($reqdfields); $i++) {
		if ($_POST[$reqdfields[$i]] == "" && $_REQUEST[$reqdfields[$i]] == "") {
			$input_errors[] = sprintf(gettext("The field %s is required."), $reqdfieldsn[$i]);
		}
	}
}

function print_input_errors($input_errors) {
	echo '<div class="alert alert-danger input-errors">';
	echo '<p>' . gettext('The following input errors were detected:') . '</p>';
	echo '<ul>';

	foreach ($input_errors as $ierr) {
		echo '<li>' . htmlspecialchars($ierr) . '</li>';
	}

	echo '</ul>';
	echo '</div>';
}

function verify_gzip_file($fname) {
	$returnvar = mwexec("/usr/bin/gzip -t " . escapeshellarg($fname));
	if ($returnvar != 0) {
		return 0;
	} else {
		return 1;
	}
}

function print_info_box_np($msg, $name="apply", $value="", $showapply=false, $class="alert-warning") {
	global $g;

	if (strpos($class, "alert-") !== 0)
		$class = 'alert-' . $class;

	if (empty($value)) {
		$value = gettext("Apply changes");
	}

	$msg = '<div class="pull-left">' . $msg . '</div>';

	if (stristr($msg, gettext("apply")) != false || stristr($msg, gettext("save")) != false || stristr($msg, gettext("create")) != false || $showapply) {
		$msg .= '<form method="post" class="pull-right"><button type="submit" class="btn btn-default" name="'. $name .'" value="'.$value.'">'.$name.'</button>';

		if ($_POST['if'])
			$msg .= "<input type=\"hidden\" name=\"if\" value=\"" . htmlspecialchars($_POST['if']) . "\" />";

		$msg .= '</form>';
	} else
		$msg = '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'. $msg;

	echo '<div class="alert ' . $class . ' clearfix" role="alert">'.$msg.'</div>';
}

function print_info_box_np_undo($msg, $name = "apply", $value = "Apply changes", $undo) {
	global $g;

	if (stristr($msg, "apply") != false || stristr($msg, "save") != false || stristr($msg, "create") != false) {
		$savebutton = "<td class=\"infoboxsave nowrap\">";
		$savebutton .= "<input type=\"button\" value=\"". gettext("Undo") . "\" onclick=\"document.location='{$undo}'\" />";
		$savebutton .= "<input name=\"{$name}\" type=\"submit\" class=\"formbtn\" id=\"${name}\" value=\"{$value}\" />";
		$savebutton .= "</td>";
		if ($_POST['if']) {
			$savebutton .= "<input type=\"hidden\" name=\"if\" value=\"" . htmlspecialchars($_POST['if']) . "\" />";
		}
	}

	$nifty_redbox = "#990000";
	$nifty_blackbox = "#000000";

	if (!$savebutton) {
		$savebutton = "<td class=\"infoboxsave\"><input value=\"" . gettext("Close") . "\" type=\"button\" onclick=\"jQuery(this).parents('table[id=redboxtable]').hide();\" /></td>";
	}

	echo <<<EOFnp
	<table class="infobox" id="redboxtable" summary="red box table">
		<tr>
			<td>
				<div class="infoboxnp" id="redbox">
					<table class="infoboxnptable2" summary="message">
						<tr>
							<td class="infoboxnptd">
								&nbsp;&nbsp;&nbsp;<i class="fa fa-exclamation-circle"></i>
							</td>
							<td class="infoboxnptd2">
								<b>{$msg}</b>
							</td>
							{$savebutton}
							{$undobutton}
						</tr>
					</table>
				</div>
				<div>
					<p>&nbsp;</p>
				</div>
			</td>
		</tr>
	</table>
EOFnp;

}

function print_info_box($msg, $class="alert-warning") {
	print_info_box_np($msg, null, null, false, $class);

}

function get_std_save_message($ok) {
	global $d_sysrebootreqd_path;
	$filter_related = false;
	$filter_pages = array("nat", "filter");
	$to_return = gettext("The changes have been applied successfully.");
	foreach ($filter_pages as $fp) {
		if (stristr($_SERVER['SCRIPT_FILENAME'], $fp)) {
			$filter_related = true;
		}
	}
	if ($filter_related) {
		$to_return .= "<br />" . gettext("You can also <a href=\"status_filter_reload.php\">monitor</a> the filter reload progress.");
	}
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

	if (isset($adr['not'])) {
		$padr = "! " . $padr;
	}

	return $padr;
}

function pprint_port($port) {
	global $wkports;

	$pport = "";

	if (!$port) {
		return "*";
	} else {
		$srcport = explode("-", $port);
		if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
			$pport = $srcport[0];
			if ($wkports[$srcport[0]]) {
				$pport .= " (" . $wkports[$srcport[0]] . ")";
			}
		} else {
			$pport .= $srcport[0] . " - " . $srcport[1];
		}
	}

	return $pport;
}

function firewall_check_for_advanced_options(&$item) {
	$item_set = "";
	if ($item['os']) {
			$item_set .= "os {$item['os']} ";
	}
	if ($item['dscp']) {
		$item_set .= "dscp {$item['dscp']} ";
	}
	if ($item['max']) {
		$item_set .= "max {$item['max']} ";
	}
	if ($item['max-src-nodes']) {
		$item_set .= "max-src-nodes {$item['max-src-nodes']} ";
	}
	if ($item['max-src-conn']) {
		$item_set .= "max-src-conn {$item['max-src-conn']} ";
	}
	if ($item['max-src-states']) {
		$item_set .= "max-src-states {$item['max-src-states']} ";
	}
	if (isset($item['nopfsync'])) {
		$item_set .= "nopfsync ";
	}
	if ($item['statetype'] != "keep state" && $item['statetype'] != "") {
		$item_set .= "statetype {$item['statetype']} ";
	}
	if ($item['statetimeout']) {
		$item_set .= "statetimeout {$item['statetimeout']} ";
	}
	if (isset($item['nosync'])) {
		$item_set .= "no XMLRPC Sync ";
	}
	if ($item['max-src-conn-rate']) {
		$item_set .= "max-src-conn-rate {$item['max-src-conn-rate']} ";
	}
	if ($item['max-src-conn-rates']) {
		$item_set .= "max-src-conn-rates {$item['max-src-conn-rates']} ";
	}
	if ($item['vlanprio']) {
		$item_set .= "vlanprio {$item['vlanprio']} ";
	}
	if ($item['vlanprioset']) {
		$item_set .= "vlanprioset {$item['vlanprioset']} ";
	}
	if ($item['gateway']) {
		$item_set .= "gateway {$item['gateway']} ";
	}
	if ($item['dnpipe']) {
		$item_set .= "limiter {$item['dnpipe']} ";
	}
	if ($item['pdnpipe']) {
		$item_set .= "limiter {$item['pdnpipe']} ";
	}
	if ($item['ackqueue']) {
		$item_set .= "ackqueue {$item['ackqueue']} ";
	}
	if ($item['defaultqueue']) {
		$item_set .= "defaultqueue {$item['defaultqueue']} ";
	}
	if ($item['tag']) {
		$item_set .= "tag {$item['tag']} ";
	}
	if ($item['tagged']) {
		$item_set .= "tagged {$item['tagged']} ";
	}
	if (isset($item['allowopts'])) {
		$item_set .= "allowopts ";
	}
	if (isset($item['disablereplyto'])) {
		$item_set .= "disable reply-to ";
	}
	if ($item['tcpflags_any'] || $item['tcpflags1'] || $item['tcpflags2']) {
		$item_set .= "tcpflags set";
	}

	return $item_set;
}

function gentitle($title) {
	global $navlevelsep;
	if (!is_array($title)) {
		return $title;
	} else {
		return join($navlevelsep, $title);
	}
}

function genhtmltitle($title) {
	if (!is_array($title))
		return '<h1 class="page-header"><a href="">' . $title . '</a></h1>';

	$heading = '<h1 class="page-header"><a href="">' . end($title) . '</a></h1>';

	// If the array contains only one element, there are no breadcrumbs, so don't
	// add anything else
	if(count($title) > 1) {
		$bc = '<ol class="breadcrumb">';

		foreach ($title as $el)
			$bc .= '<li>'.$el.'</li>';

		$bc .= '</ol>';
	} else {
		$bc = "";
	}

	return $heading . $bc;
}

/* update the changedesc and changecount(er) variables */
function update_changedesc($update) {
	global $changedesc;
	global $changecount;

	$changedesc .= " {$update}";
	$changecount++;
}

function clear_log_file($logfile = "/var/log/system.log", $restart_syslogd = true) {
	global $config, $g;
	if ($restart_syslogd) {
		exec("/usr/bin/killall syslogd");
	}
	if (isset($config['system']['disablesyslogclog'])) {
		unlink($logfile);
		touch($logfile);
	} else {
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "511488";
		$log_size = isset($config['syslog'][basename($logfile, '.log') . '_settings']['logfilesize']) ? $config['syslog'][basename($logfile, '.log') . '_settings']['logfilesize'] : $log_size;
		if (isset($config['system']['usefifolog'])) {
			exec("/usr/sbin/fifolog_create -s {$log_size} " . escapeshellarg($logfile));
		} else {
			exec("/usr/local/sbin/clog -i -s {$log_size} " . escapeshellarg($logfile));
		}
	}
	if ($restart_syslogd) {
		system_syslogd_start();
	}
}

function clear_all_log_files() {
	global $g;
	exec("/usr/bin/killall syslogd");

	$log_files = array("system", "filter", "dhcpd", "vpn", "pptps", "poes", "l2tps", "openvpn", "portalauth", "ipsec", "ppp", "relayd", "wireless", "lighttpd", "ntpd", "gateways", "resolver", "routing");
	foreach ($log_files as $lfile) {
		clear_log_file("{$g['varlog_path']}/{$lfile}.log", false);
	}

	system_syslogd_start();
	killbyname("dhcpd");
	services_dhcpd_configure();
	return;
}

// This version of dump_clog() does not output <td></td> or any other table elements.
function dump_clog_no_table($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	global $g, $config;
	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";
	$logarr = "";
	$grepline = "  ";
	if (is_array($grepfor))
		$grepline .= " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	if (is_array($grepinvert))
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	if (is_dir($logfile)) {
		$logarr = array("File $logfile is a directory.");
	} elseif (file_exists($logfile) && filesize($logfile) == 0) {
		$logarr = array("Log file started.");
	} else {
		if ($config['system']['disablesyslogclog']) {
			exec("cat " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
		} else {
			if (isset($config['system']['usefifolog']))
				exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
			else
				exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . "{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
		}
	}
	echo "\n";

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$logent = preg_split("/\s+/", $logent, 6);

		if ($withorig) {
				if (isset($config['system']['usefifolog'])) {
					$entry_date_time = htmlspecialchars(date("F j, Y, g:i a","" . $logent[1] . ""));
					$entry_text = htmlspecialchars($logent[5]);
				} else {
					$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
					$entry_text = ($logent[3] ==  $config['system']['hostname']) ? "" : $logent[3] . " ";
					$entry_text .= htmlspecialchars($logent[4] . " " . $logent[5]);
				}
				echo "{$entry_date_time}";
				echo " " . "{$entry_text}"	. "\n";
		} else {
				echo htmlspecialchars($logent[5]) . "\n";
		}

	}
	return($rows);
}

function dump_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "") {
	global $g, $config;
	$sor = isset($config['syslog']['reverse']) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";
	$logarr = "";
	$grepline = "  ";
	if (is_array($grepfor)) {
		$grepline .= " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	}
	if (is_array($grepinvert)) {
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	}
	if (is_dir($logfile)) {
		$logarr = array("File $logfile is a directory.");
	} elseif (file_exists($logfile) && filesize($logfile) == 0) {
		$logarr = array("Log file started.");
	} else {
		if ($config['system']['disablesyslogclog']) {
			exec("cat " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
		} else {
			if (isset($config['system']['usefifolog'])) {
				exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
			} else {
				exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . "{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
			}
		}
	}

	$rows = 0;
	foreach ($logarr as $logent) {
		$rows++;
		$logent = preg_split("/\s+/", $logent, 6);
		echo "<tr>\n";
		if ($withorig) {
			if (isset($config['system']['usefifolog'])) {
				$entry_date_time = htmlspecialchars(date("F j, Y, g:i a", "" . $logent[1] . ""));
				$entry_text = htmlspecialchars($logent[5]);
			} else {
				$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
				$entry_text = ($logent[3] == $config['system']['hostname']) ? "" : $logent[3] . " ";
				$entry_text .= htmlspecialchars($logent[4] . " " . $logent[5]);
			}
			echo "<td style=\"white-space:nowrap;\">{$entry_date_time}</td>\n";
			echo "<td style=\"word-wrap:break-word; word-break:break-all; white-space:normal\">{$entry_text}</td>\n";
		} else {
				echo "<td>" . htmlspecialchars($logent[5]) . "</td>\n";
		}
		echo "</tr>\n";
	}
	return($rows);
}

function return_clog($logfile, $tail, $withorig = true, $grepfor = "", $grepinvert = "", $grepreverse = false) {
	global $g, $config;
	$sor = (isset($config['syslog']['reverse']) || $grepreverse) ? "-r" : "";
	$specific_log = basename($logfile, '.log') . '_settings';
	if ($config['syslog'][$specific_log]['cronorder'] == 'forward') $sor = "";
	if ($config['syslog'][$specific_log]['cronorder'] == 'reverse') $sor = "-r";
	$logarr = "";
	$grepline = "  ";
	if (is_array($grepfor)) {
		$grepline .= " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	}
	if (is_array($grepinvert)) {
		$grepline .= " | /usr/bin/egrep -v " . escapeshellarg(implode("|", $grepinvert));
	}
	if ($config['system']['disablesyslogclog']) {
		exec("cat " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	} else {
		if (isset($config['system']['usefifolog'])) {
			exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . "{$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
		} else {
			exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . "{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
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
	if (isset($adr['any'])) {
		$padr = "any";
	} else if ($adr['network']) {
		$padr = $adr['network'];
	} else if ($adr['address']) {
		list($padr, $pmask) = explode("/", $adr['address']);
		if (!$pmask) {
			if (is_ipaddrv6($padr)) {
				$pmask = 128;
			} else {
				$pmask = 32;
			}
		}
	}

	if (isset($adr['not'])) {
		$pnot = 1;
	} else {
		$pnot = 0;
	}

	if ($adr['port']) {
		list($pbeginport, $pendport) = explode("-", $adr['port']);
		if (!$pendport) {
			$pendport = $pbeginport;
		}
	} else if (!is_alias($pbeginport) && !is_alias($pendport)) {
		$pbeginport = "any";
		$pendport = "any";
	}
}

function pconfig_to_address(&$adr, $padr, $pmask, $pnot = false, $pbeginport = 0, $pendport = 0) {
	$adr = array();

	if ($padr == "any") {
		$adr['any'] = true;
	} else if (is_specialnet($padr)) {
		$adr['network'] = $padr;
	} else {
		$adr['address'] = $padr;
		if (is_ipaddrv6($padr)) {
			if ($pmask != 128) {
				$adr['address'] .= "/" . $pmask;
			}
		} else {
			if ($pmask != 32) {
				$adr['address'] .= "/" . $pmask;
			}
		}
	}

	if ($pnot) {
		$adr['not'] = true;
	} else {
		unset($adr['not']);
	}

	if (($pbeginport != 0) && ($pbeginport != "any")) {
		if ($pbeginport != $pendport) {
			$adr['port'] = $pbeginport . "-" . $pendport;
		} else {
			$adr['port'] = $pbeginport;
		}
	}

	if (is_alias($pbeginport)) {
		$adr['port'] = $pbeginport;
	}
}

function is_specialnet($net) {
	global $specialsrcdst;

	if (!$net) {
		return false;
	}
	if (in_array($net, $specialsrcdst)) {
		return true;
	} else {
		return false;
	}
}

//function to create widget tabs when called
function display_widget_tabs(& $tab_array) {
	echo "<div id=\"tabs\">";
	$tabscounter = 0;
	foreach ($tab_array as $ta) {
		$dashpos = strpos($ta[2], '-');
		$tabname = $ta[2] . "-tab";
		$tabclass = substr($ta[2], 0, $dashpos);
		$tabclass = $tabclass . "-class";
		if ($ta[1] == true) {
			$tabActive = "table-cell";
			$tabNonActive = "none";
		} else {
			$tabActive = "none";
			$tabNonActive = "table-cell";
		}
		echo "<div id=\"{$ta[2]}-active\" class=\"{$tabclass}-tabactive\" style=\"display:{$tabActive}; background-color:#EEEEEE; color:black;\">";
		echo "<b>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</b>";
		echo "</div>";

		echo "<div id=\"{$ta[2]}-deactive\" class=\"{$tabclass}-tabdeactive\" style=\"display:{$tabNonActive}; background-color:#777777; color:white; cursor: pointer;\" onclick=\"return changeTabDIV('{$ta[2]}')\">";
		echo "<b>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</b>";
		echo "</div>";
	}
	echo "</div>";
}


// Return inline javascript file or CSS to minimize
// request count going back to server.
function outputJavaScriptFileInline($javascript) {
	if (file_exists($javascript)) {
		echo "\n<script type=\"text/javascript\">\n";
		include($javascript);
		echo "\n</script>\n";
	} else {
		echo "\n\n<!-- Could not locate file:  {$javascript} -->\n\n";
	}
}



function outputCSSPrintFileInline($css) {
	if (file_exists($css)) {
		echo "\n<style media=\"print\" type=\"text/css\">\n";
		include($css);
		echo "\n</style>\n";
	} else {
		echo "\n\n<!-- Could not locate file:  {$css} -->\n\n";
	}
}


function outputCSSFileInline($css) {
	if (file_exists($css)) {
		echo "\n<style type=\"text/css\">\n";
		include($css);
		echo "\n</style>\n";
	} else {
		echo "\n\n<!-- Could not locate file:  {$css} -->\n\n";
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
	if (isset($rfc2616[$code])) {
		return true;
	} else {
		return false;
	}
}

function print_rfc2616_select($tag, $current) {
	global $rfc2616;

	/* Default to 200 OK if not set */
	if ($current == "") {
		$current = 200;
	}

	echo "<select id=\"{$tag}\" name=\"{$tag}\">\n";
	foreach ($rfc2616 as $code => $message) {
		if ($code == $current) {
			$sel = " selected";
		} else {
			$sel = "";
		}
		echo "<option value=\"{$code}\"{$sel}>{$message}</option>\n";
	}
	echo "</select>\n";
}

// Useful debugging function, much cleaner than print_r
function echo_array($array, $return_me = false) {
	if (is_array($array) == false) {
		$return = "The provided variable is not an array.";
	} else {
		foreach ($array as $name=>$value) {
			if (is_array($value)) {
				$return .= "";
				$return .= "['<b>$name</b>'] {<div style=\"margin-left:10px;\">\n";
				$return .= echo_array($value, true);
				$return .= "</div>}";
				$return .= "\n\n";
			} else {
				if (is_string($value)) {
					$value = "\"$value\"";
				}
				$return .= "['<b>$name</b>'] = $value\n\n";
			}
		}
	}
	if ($return_me == true) {
		return $return;
	} else {
		echo "<pre>".$return."</pre>";
	}
}

/****f* pfsense-utils/display_top_tabs
 * NAME
 *	 display_top_tabs - display tabs with rounded edges
 * INPUTS
 *	 $text	  - array of tabs
 * RESULT
 *	 null
 ******/
function display_top_tabs(& $tab_array, $no_drop_down = false, $type = 'pills') {
	global $config;
	global $g;
	global $tab_array_indent;
	global $tab_array_space;
	global $tab_array_char_limit;

	/*	does the user have access to this tab?
	 *	master user has access to everything.
	 *	if the user does not have access, simply
	 *	unset the tab item.
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

	foreach ($tab_array as $tab_id => $ta) {
		if (!isAllowedPage($ta[2])) {
			unset ($tab_array[$tab_id]);
		}
	}

	$tab_active_bg	 = "#EEEEEE";
	$tab_inactive_bg = "#777777";
	$nifty_tabs_corners = "#FFF";
	$font_color = "white";

	$tabcharcount = 0;
	foreach ($tab_array as $ta)
		$tabcharcount = $tabcharcount + strlen($ta[0]);

	if ($no_drop_down == true) {
		$tabcharcount = 0;
		unset($tab_array_char_limit);
	}

	// If the character count of the tab names is > 670
	// then show a select item dropdown menubox.
	if ($tabcharcount > $tab_array_char_limit) {
		echo gettext("Currently viewing: ");
		echo "<select name=\"TabSelect\" onchange=\"tabs_will_go(this)\">\n";

		foreach ($tab_array as $ta) {
			if ($ta[1] == "true")
				$selected = " selected";
			else
				$selected = "";
			// Onclick in option will not work in some browser
			// echo "<option onclick=\"document.location='{$ta[2]}';\"{$selected}>{$ta['0']}</option>\n";
			echo "<option value=\"{$ta[2]}\"{$selected}>{$ta['0']}</option>\n";
		}

		echo "</select>\n<p>&nbsp;</p>";
		echo "<script type=\"text/javascript\">";
		echo "\n//<![CDATA[\n";
		echo " function tabs_will_go(obj){ document.location = obj.value; }\n";
		echo "//]]>\n";
		echo "</script>";
	} else {
		echo '<ul class="nav nav-' . $type . '">';

		foreach ($tab_array as $ta) {
			echo '<li role="presentation"';
			if ($ta[1]) {
				echo ' class="active"';
			}

			echo '><a href="' . $ta[2] . '">' . $ta[0] . '</a></li>';
		}

		echo '</ul>';
	}
}

function add_package_tabs($tabgroup, &$tab_array) {
	global $config, $g;

	if (!isset($config['installedpackages']['package'])) {
		return;
	}

	foreach ($config['installedpackages']['package'] as $pkg){
		$pkg_config = read_package_configurationfile($pkg['name']);

		if (!isset($pkg_config['tabs']['tab'])) {
			continue;
		}

		foreach ($pkg_config['tabs']['tab'] as $tab) {
			$tab_entry = array();
			if ($tab['name']) {
				$tab_entry[] = $tab['name'];
				$tab_entry[] = false;
				$tab_entry[] = $tab['url'];
				$tab_array[] = $tab_entry;
			}
		}
	}
}

function alias_info_popup($alias_id) {
	global $config;

	if (!is_array($config['aliases']['alias'][$alias_id]))
		return;

	$maxlength = 60;
	$alias = $config['aliases']['alias'][$alias_id];
	$content = "";

	if ($alias['url'])
	{
		// TODO: Change it when pf supports tables with ports
		if ($alias['type'] == "urltable") {
			exec("/sbin/pfctl -t {$alias['name']} -T show | wc -l", $total_entries);
			$counter=preg_replace("/\D/","",$total_entries[0]);
			exec("/sbin/pfctl -t {$alias['name']} -T show | head -10002", $alias_addresses);
		} else {
			$urlfn = alias_expand_urltable($alias['name']);
			$alias_addresses = explode("\n", file_get_contents($urlfn));
			$counter = count($alias_addresses);
		}
		
		$content .= '<h5>'. $alias['url'] .'</h5><ul><li>'. implode('</li><li>', $alias_addresses) .'</li></ul>';
		if ($counter > 10002)
			$content .= '<i>'. gettext("listing only first 10k items") .'</i>';
	}
	else
	{
		$alias_addresses = explode (" ", $alias['address']);
		$alias_details = explode ("||", $alias['detail']);
		$idx = 0;

		$content .= "<table>\n";
		$content .= "<thead>\n";
		$content .= "<tr>\n";
		$content .= "<th>" . gettext("Value") . "</th><th  style='padding-left: 10px;'>" . gettext("Description") . "</th></tr>\n";
		$content .= "</thead>\n";
		$content .= "<tbody>\n";

		foreach ($alias_addresses as $ap) {
			$content .= "	<tr>\n";
			$content .= "		<td>\n";
			$content .= 			$ap;
			$content .=	"		</td>\n";
			$content .= "		<td style='padding-left: 10px;'>\n";
			$content .= 			$alias_details[$idx];
			$content .=	"		</td>\n";
			$content .= "	</tr>\n";
			$idx++;
		}

		$content .= "</tbody>\n";
		$content .= "<table>\n";
	}

	if (strlen($alias['descr']) >= $maxlength)
		$alias['descr'] = substr($alias['descr'], 0, $maxlength) . '&hellip;';

	return $content;
}

function rule_columns_with_alias($src, $srcport, $dst, $dstport) {
	global $config;

	if ($config['aliases']['alias'] == "" || !is_array($config['aliases']['alias']))
		return;

	$columns = array();
	foreach ($config['aliases']['alias'] as $alias_id => $alias_name) {
		if ($alias_name['name'] == $src)
			$columns['src'] = $alias_id;
		if ($alias_name['name'] == $srcport)
			$columns['srcport'] = $alias_id;
		if ($alias_name['name'] == $dst )
			$columns['dst'] = $alias_id;
		if ($alias_name['name'] == $dstport)
			$columns['dstport'] = $alias_id;
	}

	return $columns;
}

function form_output_row($name, $label, $content) {
var_dump($content);die;
?>
<div class="form-group">
	<label for="<?=$name?>" class="col-sm-2 control-label"><?=gettext($label); ?></label>
	<div class="col-sm-10">
		<?=$content?>
	</div>
</div>
<?php
}

function set_flash_message($class, $msg) {
	@session_start();
	$_SESSION['flash_messages'][$class][] = $msg;
}

function get_flash_message() {
	@session_start();
	if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
		foreach ($_SESSION['flash_messages'] as $class => $flash_message) {
			print_info_box_np(implode("<br />", $flash_message), null, null, false, $class);
		}
		unset($_SESSION['flash_messages']);
	}
}

/* set timezone */
if (isset($config['system']['timezone']) &&
    !empty($config['system']['timezone'])) {
	$timezone = $config['system']['timezone'];
} elseif (isset($g['default_timezone']) && !empty($g['default_timezone'])) {
	$timezone = $g['default_timezone'];
} else {
	$timezone = "Etc/UTC";
}

date_default_timezone_set($timezone);

?>
