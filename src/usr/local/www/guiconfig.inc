<?php
/*
 * guiconfig.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/* Include authentication routines */
/* THIS MUST BE ABOVE ALL OTHER CODE */
header("X-Frame-Options: SAMEORIGIN");
include_once('phpsessionmanager.inc');
if (!$nocsrf) {
	function csrf_startup() {
		global $config;
		csrf_conf('rewrite-js', '/csrf/csrf-magic.js');
		$timeout_minutes = isset($config['system']['webgui']['session_timeout']) ? $config['system']['webgui']['session_timeout'] : 240;
		csrf_conf('expires', $timeout_minutes * 60);
	}
	require_once("csrf/csrf-magic.php");
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		phpsession_end(true);
	}
}

/* make sure nothing is cached */
if (!$omit_nocacheheaders) {
	header("Expires: 0");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate");
	header("Pragma: no-cache");
}

require_once("authgui.inc");

/* parse the configuration and include all configuration functions */
require_once("functions.inc");

/* Include the autoloader for all the GUI display classes */
require_once("classes/autoload.inc.php");

/* used by progress bar */
$lastseen = "-1";

$navlevelsep = ": ";	/* navigation level separator string */
$mandfldhtml = "";		/* display this before mandatory input fields */
$mandfldhtmlspc = "";	/* same as above, but with spacing */

if (!function_exists('set_language')) {
	require_once("pfsense-utils.inc");
}

set_language();

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
	"sshguard",
	"tonatsubnets",
	"virusprot",
	"vpn_networks",
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
	'radius' => "RADIUS");

$ldap_urltypes = array(
	'TCP - Standard' => 389,
	'TCP - STARTTLS' => 389,
	'SSL - Encrypted' => 636);

$ldap_scopes = array(
	'one' => gettext("One Level"),
	'subtree' => gettext("Entire Subtree"));

$ldap_protvers = array(
	2,
	3);

$ldap_templates = array(

	'open' => array(
		'desc' => "OpenLDAP",
		'attr_user' => "cn",
		'attr_group' => "cn",
		'attr_member' => "member",
		'allow_unauthenticated' => "true"),

	'msad' => array(
		'desc' => "Microsoft AD",
		'attr_user' => "samAccountName",
		'attr_group' => "cn",
		'attr_member' => "memberOf",
		'allow_unauthenticated' => "false"),

	'edir' => array(
		'desc' => "Novell eDirectory",
		'attr_user' => "cn",
		'attr_group' => "cn",
		'attr_member' => "uniqueMember",
		'allow_unauthenticated' => "false"));

$radius_srvcs = array(
	'both' => gettext("Authentication and Accounting"),
	'auth' => gettext("Authentication"),
	'acct' => gettext("Accounting"));

$radius_protocol = array(
	'PAP' => "PAP",
	'CHAP_MD5' => "MD5-CHAP",
	'MSCHAPv1' => "MS-CHAPv1",
	'MSCHAPv2' => "MS-CHAPv2");

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

$specialnets = array(
	"(self)" => gettext("This Firewall"),
	"pppoe" => gettext("PPPoE clients"),
	"l2tp" => gettext("L2TP clients"));

$spiflist = get_configured_interface_with_descr(true);
foreach ($spiflist as $ifgui => $ifdesc) {
	$specialnets[$ifgui] = $ifdesc . " net";
	$specialnets[$ifgui . 'ip'] = $ifdesc . " address";
}

$medias = array(
	"auto" => gettext("autoselect"),
	"100full" => gettext("100BASE-TX full-duplex"),
	"100half" => gettext("100BASE-TX half-duplex"),
	"10full" => gettext("10BASE-T full-duplex"),
	"10half" => gettext("10BASE-T half-duplex"));

$wlan_modes = array(
	"bss" => gettext("Infrastructure (BSS)"),
	"adhoc" => gettext("Ad-hoc (IBSS)"),
	"hostap" => gettext("Access Point"));

function do_input_validation($postdata, $reqdfields, $reqdfieldsn, &$input_errors) {

	/* check for bad control characters */
	foreach ($postdata as $pn => $pd) {
		if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
			$input_errors[] = sprintf(gettext("The field %s contains invalid characters."), $pn);
		}
	}

	if (is_array($reqdfields)) {
		for ($i = 0; $i < count($reqdfields); $i++) {
			if ($postdata[$reqdfields[$i]] == "") {
				$input_errors[] = sprintf(gettext("The field %s is required."), $reqdfieldsn[$i]);
			}
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

// sprint_info_box() returns a string with a formatted informational box, it does not print the box.
// To format and print in one step, call print_info_box() as usual.
// Any required button is explicitly created, rather than relying on the detection of certain
// strings in the message (such as "apply"). print_info_box_np() has been exterminated.
// $class = the bootstrap style class (default, info, warning, success, danger)
// $btnname and btntext describe the optional button and its display text, the default is an 'x' Close button.
// Note that there is also a shortcut function print_apply_box here that creates a standard "apply" box for you.
// In many cases just substitute that for print_info_box_np() to easily get a warning style "Apply changes" box.
function sprint_info_box($msg, $class="alert-warning", $btnname = "close", $btntext = "", $btnicon = "", $btnclass = "default") {

	if (strpos($class, "alert-") !== 0) {
		$class = 'alert-' . $class;
	}

	$msg = '<div class="pull-left">' . $msg . '</div>';

	if ($btnname === "close") {
		$msg = '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . $msg;
	} else if ($btnname != "") {
		if (empty($btntext)) {
			$btntext = $btnname;
		}
		if (!empty($btnicon)) {
			$btnicon = '<i class="fa ' . $btnicon . ' icon-embed-btn"></i>';
		}

		$msg .= '<form method="post" class="pull-right"><button type="submit" class="btn btn-' . $btnclass . '" name="'. $btnname . '" value="' . $btntext . '">' . $btnicon . $btntext . '</button>';

		if ( isset($_POST['if']) && !empty($_POST['if'])) {
			$msg .= "<input type=\"hidden\" name=\"if\" value=\"" . htmlspecialchars($_POST['if']) . "\" />";
		}

		$msg .= '</form>';
	}

	return '<div class="alert ' . $class . ' clearfix" role="alert">' . $msg . '</div>';
}

// Format and print an info box. See sprint_info_box() for details.
function print_info_box($msg, $class="alert-warning", $btnname = "close", $btntext = "", $btnicon = "", $btnclass = "default") {
	echo sprint_info_box($msg, $class, $btnname, $btntext, $btnicon, $btnclass);
}

function print_apply_box($msg) {
	print_info_box($msg, "warning", "apply", gettext("Apply Changes"), 'fa-check', 'success');
}

// Format and print a box reporting that changes have been applied
// $retval = status value from the functions called to apply the changes
// 0 is good
// non-zero is a problem
// $extra_text = optional extra text to display after the standard message
function print_apply_result_box($retval, $extra_text="") {
	$result_msg = get_std_save_message($retval);
	if ($retval === 0) {
		// 0 is success
		$severity = "success";
	} else {
		// non-zero means there was some problem
		$severity = "warning";
	}

	if (strlen($extra_text) > 0) {
		$result_msg .= " " . $extra_text;
	}
	print_info_box($result_msg, $severity);
}

/*
 * Print Bootstrap callout
 *
 * @param string $msg     message to display
 * @param string $class   contextual class, defaults to info (default | danger | warning | info)
 * @param string $heading optional callout heading
 */
function print_callout($msg, $class = 'info', $heading = '') {

	if ('' == $msg) {
		return;
	}
	$class = strtolower($class);
	$callout = '';

	if ($class != 'default' && $class != 'danger' && $class != 'warning' && $class != 'info') {
		$class = 'info';
	}
	$callout .= '<div class="bs-callout bs-callout-' . $class . '">';

	if ('' != $heading) {
		$callout .= '<h4>' . $heading . '</h4>';
	}
	$callout .= $msg . '</div>';
	echo $callout;
}

function get_std_save_message($retval) {
	$filter_related = false;
	$filter_pages = array("firewall_aliases", "firewall_nat", "firewall_rules", "status_logs_filter");
	if ($retval === 0) {
		// 0 is success
		$to_return = gettext("The changes have been applied successfully.");
	} else {
		// non-zero means there was some problem
		$to_return = sprintf(gettext('There was a problem applying the changes. See the %1$sSystem Logs%2$s.'), '<a href="status_logs.php">', '</a>');
	}
	foreach ($filter_pages as $fp) {
		if (stristr($_SERVER['SCRIPT_FILENAME'], $fp)) {
			$filter_related = true;
		}
	}
	if ($filter_related) {
		$to_return .= " " . gettext("The firewall rules are now reloading in the background.") . "<br />" .
		    sprintf(gettext('%1$sMonitor%2$s the filter reload progress.'), "<a href='status_filter_reload.php'>", "</a>");
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

function insert_word_breaks_in_domain_name($domain_name) {
	return str_replace('.', '<wbr>.', $domain_name);
}

function firewall_check_for_advanced_options(&$item) {
	$item_set = "";
	if ($item['os']) {
			$item_set .= "os " . htmlspecialchars($item['os']) . " ";
	}
	if ($item['dscp']) {
		$item_set .= "dscp " . htmlspecialchars($item['dscp']) . " ";
	}
	if ($item['max']) {
		$item_set .= "max " . htmlspecialchars($item['max']) . " ";
	}
	if ($item['max-src-nodes']) {
		$item_set .= "max-src-nodes " . htmlspecialchars($item['max-src-nodes']) . " ";
	}
	if ($item['max-src-conn']) {
		$item_set .= "max-src-conn " . htmlspecialchars($item['max-src-conn']) . " ";
	}
	if ($item['max-src-states']) {
		$item_set .= "max-src-states " . htmlspecialchars($item['max-src-states']) . " ";
	}
	if (isset($item['nopfsync'])) {
		$item_set .= "nopfsync ";
	}
	if ($item['statetype'] != "keep state" && $item['statetype'] != "") {
		$item_set .= "statetype " . htmlspecialchars($item['statetype']) . " ";
	}
	if ($item['statetimeout']) {
		$item_set .= "statetimeout " . htmlspecialchars($item['statetimeout']) . " ";
	}
	if (isset($item['nosync'])) {
		$item_set .= "no XMLRPC Sync ";
	}
	if ($item['max-src-conn-rate']) {
		$item_set .= "max-src-conn-rate " . htmlspecialchars($item['max-src-conn-rate']) . " ";
	}
	if ($item['max-src-conn-rates']) {
		$item_set .= "max-src-conn-rates " . htmlspecialchars($item['max-src-conn-rates']) . " ";
	}
	if ($item['vlanprio']) {
		$item_set .= "vlanprio " . htmlspecialchars($item['vlanprio']) . " ";
	}
	if ($item['vlanprioset']) {
		$item_set .= "vlanprioset " . htmlspecialchars($item['vlanprioset']) . " ";
	}
	if ($item['gateway']) {
		$item_set .= "gateway " . htmlspecialchars($item['gateway']) . " ";
	}
	if ($item['dnpipe']) {
		$item_set .= "limiter " . htmlspecialchars($item['dnpipe']) . " ";
	}
	if ($item['pdnpipe']) {
		$item_set .= "limiter " . htmlspecialchars($item['pdnpipe']) . " ";
	}
	if ($item['ackqueue']) {
		$item_set .= "ackqueue " . htmlspecialchars($item['ackqueue']) . " ";
	}
	if ($item['defaultqueue']) {
		$item_set .= "defaultqueue " . htmlspecialchars($item['defaultqueue']) . " ";
	}
	if ($item['tag']) {
		$item_set .= "tag " . htmlspecialchars($item['tag']) . " ";
	}
	if ($item['tagged']) {
		$item_set .= "tagged " . htmlspecialchars($item['tagged']) . " ";
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

function genhtmltitle($title, $links=true) {
	if (is_array($title)) {
		$num_crumbs = count($title);
	} else if ($title != NULL) {
		$num_crumbs = 1;
	} else {
		$num_crumbs = 0;
	}

	// If the array contains only one element, there are no breadcrumbs, so don't
	// add anything else
	if ($num_crumbs > 1) {
		$bc = '<ol class="breadcrumb">';

		if (!is_array($links)) {
			$gen_default = ($links === true);
			$links = array_fill(0, $num_crumbs, '');
			// If no links passed, then default to a link to self on the last entry.
			if ($gen_default) {
				$links[$num_crumbs-1] = '@self';
			}
		}

		foreach ($title as $idx => $el) {
			$href = $links[$idx];
			if (strlen($href) > 0) {
				// For convenience, if the caller specifies '@self' then make a link
				// to the current page, including any query string.
				if ($href == '@self') {
					$href = $_SERVER['REQUEST_URI'];
				}
				if (substr($href, 0, 1) != '/') {
					$href = '/' . $href;
				}
				$bc .= '<li><a href="' . htmlentities($href) . '">' . $el . '</a></li>';
			} else {
				$bc .= '<li>' . $el . '</li>';
			}
		}

		$bc .= '</ol>';
	} else {
		$bc = "";
	}

	return $bc;
}

function gen_customwidgettitle_div($widgettitle) {
	$divstr = '<div class="form-group">';
	$divstr .= '  <label for="descr" class="col-sm-4 control-label">' . gettext('Widget title'). '</label>';
	$divstr .= '  <div class="col-sm-4">';
	$divstr .= '    <input type="text" name="descr" id="descr" value="'. $widgettitle . '" class="form-control" />';
	$divstr .= '  </div>';
	$divstr .= '</div>';

	return $divstr;
}

function set_customwidgettitle(& $user_settings) {
	if ($_POST['descr']) {
		$user_settings['widgets'][$_POST['widgetkey']]['descr'] = trim($_POST['descr']);
	} else {
		unset($user_settings['widgets'][$_POST['widgetkey']]['descr']);
	}
}

/* update the changedesc and changecount(er) variables */
function update_changedesc($update) {
	global $changedesc;
	global $changecount;

	$changedesc .= " {$update}";
	$changecount++;
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

	/*
	 * If the port is still unset, then it must not be numeric, but could
	 * be an alias or a well-known/registered service.
	 * See https://redmine.pfsense.org/issues/8410
	 */
	if (!isset($adr['port']) && is_port_or_alias($pbeginport)) {
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
		include_once($javascript);
		echo "\n</script>\n";
	} else {
		echo "\n\n<!-- Could not locate file:  {$javascript} -->\n\n";
	}
}



function outputCSSPrintFileInline($css) {
	if (file_exists($css)) {
		echo "\n<style media=\"print\" type=\"text/css\">\n";
		include_once($css);
		echo "\n</style>\n";
	} else {
		echo "\n\n<!-- Could not locate file:  {$css} -->\n\n";
	}
}


function outputCSSFileInline($css) {
	if (file_exists($css)) {
		echo "\n<style type=\"text/css\">\n";
		include_once($css);
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
function display_top_tabs(& $tab_array, $no_drop_down = false, $type = 'pills', $usepost = "") {
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
		$tab_array_char_limit = 256;
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
	foreach ($tab_array as $ta) {
		$tabcharcount = $tabcharcount + strlen($ta[0]);
	}

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
			if ($ta[1] == "true") {
				$selected = " selected";
			} else {
				$selected = "";
			}
			// Onclick in option will not work in some browser
			// echo "<option onclick=\"document.location='{$ta[2]}';\"{$selected}>{$ta['0']}</option>\n";
			echo "<option value=\"{$ta[2]}\"{$selected}>{$ta['0']}</option>\n";
		}

		echo "</select>\n<p>&nbsp;</p>";
		echo "<script type=\"text/javascript\">";
		echo "\n//<![CDATA[\n";
		if ($usepost == 'usepost') {
			echo " function tabs_will_go(obj){ var target = obj.value.split(\"?\"); postSubmit(get2post(target[1]),target[0]); }\n";
		} else {
			echo " function tabs_will_go(obj){ document.location = obj.value; }\n";
		}
		echo "//]]>\n";
		echo "</script>";
	} else {
		echo '<ul class="nav nav-' . $type . '">';

		foreach ($tab_array as $ta) {
			echo '<li role="presentation"';
			if ($ta[1]) {
				echo ' class="active"';
			}

			echo '><a href="' . $ta[2] . '" ' . $usepost . '>' . $ta[0] . '</a></li>';
		}

		echo '</ul>';
	}
}

function add_package_tabs($tabgroup, &$tab_array) {
	global $config, $g;

	if (!isset($config['installedpackages']['package'])) {
		return;
	}

	foreach ($config['installedpackages']['package'] as $pkg) {
		if (!is_array($pkg['tabs']['tab'])) {
			continue;
		}

		foreach ($pkg['tabs']['tab'] as $tab) {
			if ($tab['tabgroup'] != $tabgroup) {
				continue;
			}
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
	global $config, $user_settings;

	if (!is_array($config['aliases']['alias'][$alias_id])) {
		return;
	}

	$maxlength = 60;
	$alias = $config['aliases']['alias'][$alias_id];
	$content = "";

	if ($user_settings['webgui']['disablealiaspopupdetail']) {
		if (strlen($alias['descr']) >= $maxlength) {
			$alias['descr'] = substr($alias['descr'], 0, $maxlength) . '&hellip;';
		}

		$content .= $alias['descr'];
	} else if ($alias['url']) {
		// TODO: Change it when pf supports tables with ports
		if ($alias['type'] == "urltable") {
			exec("/sbin/pfctl -t {$alias['name']} -T show | wc -l", $total_entries);
			$counter=preg_replace("/\D/", "", $total_entries[0]);
			exec("/sbin/pfctl -t {$alias['name']} -T show | head -10002", $alias_addresses);
		} else {
			$urlfn = alias_expand_urltable($alias['name']);
			$alias_addresses = explode("\n", file_get_contents($urlfn));
			$counter = count($alias_addresses);
		}

		$content .= '<h5>'. $alias['url'] .'</h5><ul><li>'. implode('</li><li>', $alias_addresses) .'</li></ul>';
		if ($counter > 10002) {
			$content .= '<i>'. gettext("listing only first 10k items") .'</i>';
		}
	} else {
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
			$content .= 			htmlspecialchars($alias_details[$idx]);
			$content .=	"		</td>\n";
			$content .= "	</tr>\n";
			$idx++;
		}

		$content .= "</tbody>\n";
		$content .= "<table>\n";
	}

	return $content;
}

function rule_columns_with_alias($src, $srcport, $dst, $dstport, $target="", $targetport="") {
	global $config;

	if ($config['aliases']['alias'] == "" || !is_array($config['aliases']['alias'])) {
		return;
	}

	$columns = array();
	foreach ($config['aliases']['alias'] as $alias_id => $alias_name) {
		if ($alias_name['name'] == $src) {
			$columns['src'] = $alias_id;
		}
		if ($alias_name['name'] == $srcport) {
			$columns['srcport'] = $alias_id;
		}
		if ($alias_name['name'] == $dst) {
			$columns['dst'] = $alias_id;
		}
		if ($alias_name['name'] == $dstport) {
			$columns['dstport'] = $alias_id;
		}
		if ($alias_name['name'] == $target) {
			$columns['target'] = $alias_id;
		}
		if ($alias_name['name'] == $targetport) {
			$columns['targetport'] = $alias_id;
		}
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
	@phpsession_begin();
	$_SESSION['flash_messages'][$class][] = $msg;
	@phpsession_end(true);
}

function get_flash_message() {
	@phpsession_begin();
	if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
		foreach ($_SESSION['flash_messages'] as $class => $flash_message) {
			print_info_box(implode("<br />", $flash_message), $class);
		}
		unset($_SESSION['flash_messages']);
	}
	@phpsession_end(true);
}

/* Retrieve GET or POST Value/State
 * Eample Usage:
 * $value = getGETPOSTsettingvalue('get/post parameter name', "");
 * $value = getGETPOSTsettingvalue('get/post parameter name', null);
 * $state = getGETPOSTsettingvalue('get/post parameter name', null);
 * $state = getGETPOSTsettingvalue('get/post parameter name', false);
 */
function getGETPOSTsettingvalue($settingname, $default) {
	$settingvalue = $default;
	if ($_GET[$settingname]) {
		$settingvalue = $_GET[$settingname];
	}
	if ($_POST[$settingname]) {
		$settingvalue = $_POST[$settingname];
	}
	return $settingvalue;
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

/* Remove files we do not want to see in a crash report */
function cleanup_crash_file_list() {
	$files = glob("/var/crash/*");
	if (!is_array($files) || empty($files)) {
		return array();
	}

	$exclude_patterns = array(
		'.*.last',
		'bounds',
		'minfree'
	);

	foreach ($files as $idx => $fb) {
		if (preg_match('/' . implode('|', $exclude_patterns) . '/', basename($fb)) == 1) {
			unset($files[$idx]);
		}
	}

	return $files;
}

function system_has_crash_data() {
	/* Test if there are any crash data files present */
	return count(cleanup_crash_file_list()) > 0;
}

function system_has_php_errors() {
	/* Check if the PHP error log is empty. Cast to int in case the file
	 * does not exist and filesize() returns false. */
	return (int) @filesize("/tmp/PHP_errors.log") > 0;
}

date_default_timezone_set($timezone);

?>
