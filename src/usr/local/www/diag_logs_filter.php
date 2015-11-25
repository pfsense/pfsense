<?php
/*
	diag_logs_filter.php
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
	pfSense_MODULE: filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-firewall
##|*NAME=Status: Logs: Firewall
##|*DESCR=Allow access to the 'Status: Logs: Firewall' page.
##|*MATCH=diag_logs_filter.php*
##|-PRIV

require("guiconfig.inc");
require_once("ipsec.inc");
require_once("filter_log.inc");

# --- AJAX RESOLVE ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');

	if ($res && $res != $ip) {
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	} else {
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));
	}

	echo json_encode(str_replace("\\", "\\\\", $response)); // single escape chars can break JSON decode
	exit;
}

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

$rulenum = getGETPOSTsettingvalue('getrulenum', null);
if ($rulenum) {
	list($rulenum, $tracker, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum, $tracker, $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);

if ($filtersubmit) {
	$interfacefilter = getGETPOSTsettingvalue('interface', null);
	$filtertext = getGETPOSTsettingvalue('filtertext', "");
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filterlogentries_submit = getGETPOSTsettingvalue('filterlogentries_submit', null);

if ($filterlogentries_submit) {
	$filterfieldsarray = array();

	$actpass = getGETPOSTsettingvalue('actpass', null);
	$actblock = getGETPOSTsettingvalue('actblock', null);
	$filterfieldsarray['act'] = str_replace("  ", " ", trim($actpass . " " . $actblock));
	$filterfieldsarray['act'] = $filterfieldsarray['act'] != "" ? $filterfieldsarray['act'] : 'All';
	$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
	$filterfieldsarray['interface'] = getGETPOSTsettingvalue('filterlogentries_interfaces', null);
	$filterfieldsarray['srcip'] = getGETPOSTsettingvalue('filterlogentries_sourceipaddress', null);
	$filterfieldsarray['srcport'] = getGETPOSTsettingvalue('filterlogentries_sourceport', null);
	$filterfieldsarray['dstip'] = getGETPOSTsettingvalue('filterlogentries_destinationipaddress', null);
	$filterfieldsarray['dstport'] = getGETPOSTsettingvalue('filterlogentries_destinationport', null);
	$filterfieldsarray['proto'] = getGETPOSTsettingvalue('filterlogentries_protocol', null);
	$filterfieldsarray['tcpflags'] = getGETPOSTsettingvalue('filterlogentries_protocolflags', null);
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
}

$filter_logfile = "{$g['varlog_path']}/filter.log";

$nentries = $config['syslog']['nentries'];

# Override Display Quantity
if ($filterlogentries_qty) {
	$nentries = $filterlogentries_qty;
}

if (!$nentries || !is_numeric($nentries)) {
	$nentries = 50;
}

if ($_POST['clear']) {
	clear_log_file($filter_logfile);
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("Firewall"));
$shortcut_section = "firewall";
include("head.inc");

function build_if_list() {
	$iflist = get_configured_interface_with_descr(false, true);
	//$iflist = get_interface_list();
	// Allow extending of the firewall edit interfaces
	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/pre_interfaces_edit");
	foreach ($iflist as $if => $ifdesc)
		$interfaces[$if] = $ifdesc;

	if ($config['l2tp']['mode'] == "server")
		$interfaces['l2tp'] = "L2TP VPN";

	if (is_pppoe_server_enabled() && have_ruleint_access("pppoe"))
		$interfaces['pppoe'] = "PPPoE Server";

	/* add ipsec interfaces */
	if (ipsec_enabled())
		$interfaces["enc0"] = "IPsec";

	/* add openvpn/tun interfaces */
	if	($config['openvpn']["openvpn-server"] || $config['openvpn']["openvpn-client"])
		$interfaces["openvpn"] = "OpenVPN";

	return($interfaces);
}

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), true, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), false, "diag_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), false, "diag_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), false, "diag_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), false, "diag_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("Normal View"), true, "/diag_logs_filter.php");
$tab_array[] = array(gettext("Dynamic View"), false, "/diag_logs_filter_dynamic.php");
$tab_array[] = array(gettext("Summary View"), false, "/diag_logs_filter_summary.php");
display_top_tabs($tab_array, false, 'nav nav-tabs');

$Include_Act = explode(",", str_replace(" ", ",", $filterfieldsarray['act']));
if ($filterfieldsarray['interface'] == "All")
	$interface = "";

if (!isset($config['syslog']['rawfilter'])) { // Advanced log filter form
	$form = new Form(new Form_Button(
		'filterlogentries_submit',
		'Filter'
	));

	$section = new Form_Section('Advanced Log Filter');

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'filterlogentries_sourceipaddress',
		null,
		'text',
		$filterfieldsarray['srcip']
	))->setHelp('Source IP Address');

	$group->add(new Form_Input(
		'filterlogentries_destinationipaddress',
		null,
		'text',
		$filterfieldsarray['dstip']
	))->setHelp('Destination IP Address');

	$section->add($group);
	$group = new Form_Group('');

	$group->add(new Form_Checkbox(
		'actpass',
		'Pass',
		'Pass',
		in_arrayi('Pass', $Include_Act),
		'Pass'
	));

	$group->add(new Form_Input(
		'filterlogentries_time',
		null,
		'text',
		$filterfieldsarray['time']
	))->setHelp('Time');

	$group->add(new Form_Input(
		'filterlogentries_sourceport',
		null,
		'text',
		$filterfieldsarray['srcport']
	))->setHelp('Source Port');

	$group->add(new Form_Input(
		'filterlogentries_protocol',
		null,
		'text',
		$filterfieldsarray['proto']
	))->setHelp('Protocol');

	$group->add(new Form_Input(
		'filterlogentries_qty',
		null,
		'text',
		$filterlogentries_qty
	))->setHelp('Quantity');

	$section->add($group);

	$group = new Form_Group('');

	$group->add(new Form_Checkbox(
		'actblock',
		'Block',
		'Block',
		in_arrayi('Block', $Include_Act),
		'Block'
	));

	$group->add(new Form_Input(
		'filterlogentries_interfaces',
		null,
		'text',
		$filterfieldsarray['interface']
	))->setHelp('Interface');

	$group->add(new Form_Input(
		'filterlogentries_destinationport',
		null,
		'text',
		$filterfieldsarray['dstport']
	))->setHelp('Destination Port');

	$group->add(new Form_Input(
		'filterlogentries_protocolflags',
		null,
		'text',
		$filterfieldsarray['tcpflags']
	))->setHelp('Protocol Flags');
}
else { // Simple log filter form
	$form = new Form(new Form_Button(
		'filtersubmit',
		'Filter'
	));
	$section = new Form_Section('Log Filter');

	$section->addInput(new Form_Select(
		'interface',
		'Interface',
		$interfacefilter,
		build_if_list()
	));

	$group = new Form_Group('');

	$group->add(new Form_Input(
		'filtertext',
		null,
		'text',
		$filtertext
	))->setHelp('Filter Expression');

	$group->add(new Form_Input(
		'filterlogentries_qty',
		null,
		'text',
		$filterlogentries_qty
	))->setHelp('Quantity');
}

$group->setHelp('<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">' . 'Regular expression reference</a> Precede with exclamation (!) to exclude match.');
$section->add($group);
$form->add($section);
print($form);

// Now the forms are complete we can draw the log table and its controls
if (!isset($config['syslog']['rawfilter'])) {
	$iflist = get_configured_interface_with_descr(false, true);

	if ($iflist[$interfacefilter])
		$interfacefilter = $iflist[$interfacefilter];

	if ($filterlogentries_submit)
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filterfieldsarray);
	else
		$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext, $interfacefilter);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	if ((!$filtertext) && (!$filterfieldsarray))
		printf(gettext("Last %s firewall log entries."), count($filterlog));
	else
		print(count($filterlog). ' ' . gettext('matched log entries.') . ' ');

	printf(gettext(" (Maximum %s)"), $nentries);
?>
		</h2>
	</div>
	<div class="panel-body">
	   <div class="table-responsive">
		<table class="table table-striped table-hover table-compact">
			<tr>
				<th><?=gettext("Act")?></th>
				<th><?=gettext("Time")?></th>
				<th><?=gettext("IF")?></th>
<?php
	if ($config['syslog']['filterdescriptions'] === "1") {
?>
					<th>
						<?=gettext("Rule")?>
					</th>
<?php
	}
?>
				<th><?=gettext("Source")?></th>
				<th><?=gettext("Destination")?></th>
				<th><?=gettext("Proto")?></th>
			</tr>
<?php
	if ($config['syslog']['filterdescriptions'])
		buffer_rules_load();

	foreach ($filterlog as $filterent) {
?>
			<tr>
				<td>
<?php
		if ($filterent['act'] == "block") {
			$icon_act = "fa-times icon-danger";
		} else {
			$icon_act = "fa-check icon-success";
		}
?>
					<i class="fa <?php echo $icon_act;?> icon-pointer" title="<?php echo $filterent['act'] .'/'. $filterent['tracker'];?>" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?="{$filterent['rulenum']},{$filterent['tracker']},{$filterent['act']}"; ?>', outputrule);"></i>
<?php
		if ($filterent['count'])
			echo $filterent['count'];
?>
				</td>
				<td>
					<?=htmlspecialchars($filterent['time'])?>
				</td>
				<td>
<?php
					if ($filterent['direction'] == "out")
						print('&#x25ba;' . ' ');
?>
					<?=htmlspecialchars($filterent['interface'])?>
				</td>
<?php
		if ($config['syslog']['filterdescriptions'] === "1") {
?>
				<td>
					<?=find_rule_by_number_buffer($filterent['rulenum'], $filterent['tracker'], $filterent['act'])?>
				</td>
<?php
		}

		$int = strtolower($filterent['interface']);
		$proto = strtolower($filterent['proto']);

		if ($filterent['version'] == '6') {
			$ipproto = "inet6";
			$filterent['srcip'] = "[{$filterent['srcip']}]";
			$filterent['dstip'] = "[{$filterent['dstip']}]";
		} else {
			$ipproto = "inet";
		}

		$srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
		$src_htmlclass = str_replace(array('.', ':'), '-', $filterent['srcip']);
		$dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
		$dst_htmlclass = str_replace(array('.', ':'), '-', $filterent['dstip']);
?>
				<td>
					<i class="fa fa-info icon-pointer icon-primary" onclick="javascript:resolve_with_ajax('<?="{$filterent['srcip']}"; ?>');" title="<?=gettext("Click to resolve")?>" alt="Reverse Resolve with DNS"/>
					</i>

					<i class="fa fa-minus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=block&amp;int={$int}&amp;src={$filterent['srcip']}&amp;ipproto={$ipproto}"; ?>" alt="Easy Rule: Add to Block List" title="<?=gettext("Easy Rule: Add to Block List")?>" onclick="return confirm('<?=gettext("Do you really want to add this BLOCK rule?")?>')">
					</i>

					<?=$srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>'?>
				</td>
				<td>
					<i class="fa fa-info icon-pointer icon-primary" onclick="javascript:resolve_with_ajax('<?="{$filterent['dstip']}"; ?>');" title="<?=gettext("Click to resolve")?>" class="ICON-<?= $dst_htmlclass; ?>" alt="Reverse Resolve with DNS"/>
					</i>

					<i class="fa fa-plus-square-o icon-pointer icon-primary" href="easyrule.php?<?="action=pass&amp;int={$int}&amp;proto={$proto}&amp;src={$filterent['srcip']}&amp;dst={$filterent['dstip']}&amp;dstport={$filterent['dstport']}&amp;ipproto={$ipproto}"; ?>" title="<?=gettext("Easy Rule: Pass this traffic")?>" onclick="return confirm('<?=gettext("Do you really want to add this PASS rule?")?>')">
					</i>
					<?=$dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>'?>
				</td>
<?php
		if ($filterent['proto'] == "TCP")
			$filterent['proto'] .= ":{$filterent['tcpflags']}";
?>
				<td>
					<?=htmlspecialchars($filterent['proto'])?>
				</td>
			</tr>
<?php
		if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2") {
?>
				<tr>
					<td colspan="2" />
					<td colspan="4"><?=find_rule_by_number_buffer($filterent['rulenum'],$filterent['tracker'],$filterent['act'])?></td>
				</tr>
<?php
		}
	} // e-o-foreach
	buffer_rules_clear();
}
else
{
?>
			<tr>
				<td colspan="2">
					<?php printf(gettext("Last %s firewall log entries"),$nentries)?>
				</td>
			</tr>
<?php
	if ($filtertext)
		dump_clog($filter_logfile, $nentries, true, array("$filtertext"));
	else
		dump_clog($filter_logfile, $nentries);
}
?>
		</table>
		</div>
	</div>
</div>

<p>
	<form id="clearform" name="clearform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
		<input id="submit" name="clear" type="submit" class="btn btn-danger" value="<?=gettext("Clear log")?>" />
	</form>
</p>

<?php

print_info_box('<a href="https://doc.pfsense.org/index.php/What_are_TCP_Flags%3F">' .
	gettext("TCP Flags") . '</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, C - CWR' . '<br />' .
	'<i class="fa fa-minus-square-o icon-primary"></i> = Add to block list., <i class="fa fa-plus-square-o icon-primary"></i> = Pass traffic, <i class="fa fa-info icon-primary"></i> = Resolve');

?>

<!-- AJAXY STUFF -->
<script type="text/javascript">
//<![CDATA[
	function outputrule(req) {
		alert(req.content);
	}
//]]>
</script>

<?php include("foot.inc");
?>
<script type="text/javascript">
//<![CDATA[

function resolve_with_ajax(ip_to_resolve) {
	var url = "/diag_logs_filter.php";

	jQuery.ajax(
		url,
		{
			method: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

}

function resolve_ip_callback(transport) {
	var response = jQuery.parseJSON(transport.responseText);
	var resolve_class = htmlspecialchars(response.resolve_ip.replace(/[.:]/g, '-'));
	var resolve_text = '<small><br />' + htmlspecialchars(response.resolve_text) + '<\/small>';

	jQuery('span.RESOLVE-' + resolve_class).html(resolve_text);
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
	return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}

if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url)
			throw 'No URL for getURL';
		try {
			if (typeof callback.operationComplete == 'function')
				callback = callback.operationComplete;
		} catch (e) {}
			if (typeof callback != 'function')
				throw 'No callback function for getURL';
		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
			http_request = new XMLHttpRequest();
		}
		else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request)
			throw 'Both getURL and XMLHttpRequest are undefined';
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
				  content : http_request.responseText,
				  contentType : http_request.getResponseHeader("Content-Type") } );
			}
		};
		http_request.open('GET', url, true);
		http_request.send(null);
	};
}

events.push(function(){
    $('.fa').tooltip();
});
//]]>
</script>
