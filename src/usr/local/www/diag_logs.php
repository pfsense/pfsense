<?php
/*
	diag_logs.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Some or all of this file is based on the m0n0wall project which is
 *  Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-system
##|*NAME=Diagnostics: Logs: System page
##|*DESCR=Allow access to the 'Diagnostics: Logs: System' page.
##|*MATCH=diag_logs.php*
##|-PRIV

require("guiconfig.inc");

// The logs to display are specified in a GET argument. Default to 'system' logs
if (!$_GET['logfile'])
	$logfile = 'system';
else
	$logfile = $_GET['logfile'];

$system_logfile = "{$g['varlog_path']}/{$logfile}.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries) {
	$nentries = 50;
}

if ($_POST['clear']) {
	clear_log_file($system_logfile);
}

if ($_GET['filtertext']) {
	$filtertext = htmlspecialchars($_GET['filtertext']);
}

if ($_POST['filtertext']) {
	$filtertext = htmlspecialchars($_POST['filtertext']);
}

if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
}

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("General"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), ($logfile == 'system'), "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), ($logfile == 'dhcpd'), "diag_logs.php?logfile=dhcpd");
$tab_array[] = array(gettext("Portal Auth"), ($logfile == 'portalauth'), "diag_logs.php?logfile=portalauth");
$tab_array[] = array(gettext("IPsec"), ($logfile == 'ipsec'), "diag_logs.php?logfile=ipsec");
$tab_array[] = array(gettext("PPP"), ($logfile == 'ppp'), "diag_logs.php?logfile=ppp");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), ($logfile == 'relayd'), "diag_logs.php?logfile=relayd");
$tab_array[] = array(gettext("OpenVPN"), ($logfile == 'openvpn'), "diag_logs.php?logfile=openvpn");
$tab_array[] = array(gettext("NTP"), ($logfile == 'ntpd'), "diag_logs.php?logfile=ntpd");
$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
if (in_array($logfile, array('system', 'gateways', 'routing', 'resolver', 'wireless')))	 {
	$tab_array[] = array(gettext("General"), ($logfile == 'system'), "/diag_logs.php");
	$tab_array[] = array(gettext("Gateways"), ($logfile == 'gateways'), "/diag_logs.php?logfile=gateways");
	$tab_array[] = array(gettext("Routing"), ($logfile == 'routing'), "/diag_logs.php?logfile=routing");
	$tab_array[] = array(gettext("Resolver"), ($logfile == 'resolver'), "/diag_logs.php?logfile=resolver");
	$tab_array[] = array(gettext("Wireless"), ($logfile == 'wireless'), "/diag_logs.php?logfile=wireless");
	display_top_tabs($tab_array, false, 'nav nav-tabs');
}

require_once('classes/Form.class.php');

$form = new Form(false);

$section = new Form_Section('Log file filter');

$section->addInput(new Form_Input(
	'filtertext',
	'Filter',
	'text',
	$filtertext,
	['placeholder' => 'Filter text']
));

$btnsubmit = new Form_Button(
	'filtersubmit',
	'Filter',
	null,
	'fa-filter'
);

$btnsubmit->removeClass('btn-primary')->addClass('btn-success')->addClass('btn-sm');

$btnclear = new Form_Button(
	'clear',
	'Clear log',
	null,
	'fa-trash'
);

$btnclear->removeClass('btn-primary')->addClass('btn-danger')->addClass('btn-sm');

$section->addInput(new Form_StaticText(
	'',
	$btnsubmit . $btnclear
));

$form->add($section);
print $form;

if ($logfile == 'dhcpd')
	print_info_box('Warning: Clearing the log file will restart the DHCP daemon.');

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Last ")?><?=$nentries?> <?=$logfile?><?=gettext(" log entries")?></h2></div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<th class="col-sm-2"></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
	if (($logfile == 'resolver') || ($logfile == 'system'))
		$inverse = array("ppp");
	else
		$inverse = null;

	if ($filtertext)
		dump_clog($system_logfile, $nentries, true, array("$filtertext"), $inverse);
	else
		dump_clog($system_logfile, $nentries, true, array(), $inverse);
?>
			</tbody>
		</table>
	</div>
</div>

<?php include("foot.inc"); ?>
