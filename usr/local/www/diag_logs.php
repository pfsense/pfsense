<?php
/* $Id$ */
/*
	diag_logs.php
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-logs-system
##|*NAME=Diagnostics: Logs: System page
##|*DESCR=Allow access to the 'Diagnostics: Logs: System' page.
##|*MATCH=diag_logs.php*
##|-PRIV

require("guiconfig.inc");

$system_logfile = "{$g['varlog_path']}/system.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) 
	clear_log_file($system_logfile);

if ($_GET['filtertext'])
	$filtertext = htmlspecialchars($_GET['filtertext']);

if ($_POST['filtertext'])
	$filtertext = htmlspecialchars($_POST['filtertext']);

if ($filtertext)
	$filtertextmeta="?filtertext=$filtertext";

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("General"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), true, "diag_logs.php");
$tab_array[] = array(gettext("Firewall"), false, "diag_logs_filter.php");
$tab_array[] = array(gettext("DHCP"), false, "diag_logs_dhcp.php");
$tab_array[] = array(gettext("Portal Auth"), false, "diag_logs_auth.php");
$tab_array[] = array(gettext("IPsec"), false, "diag_logs_ipsec.php");
$tab_array[] = array(gettext("PPP"), false, "diag_logs_ppp.php");
$tab_array[] = array(gettext("VPN"), false, "diag_logs_vpn.php");
$tab_array[] = array(gettext("Load Balancer"), false, "diag_logs_relayd.php");
$tab_array[] = array(gettext("OpenVPN"), false, "diag_logs_openvpn.php");
$tab_array[] = array(gettext("NTP"), false, "diag_logs_ntpd.php");
$tab_array[] = array(gettext("Settings"), false, "diag_logs_settings.php");
display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("General"), true, "/diag_logs.php");
$tab_array[] = array(gettext("Gateways"), false, "/diag_logs_gateways.php");
$tab_array[] = array(gettext("Routing"), false, "/diag_logs_routing.php");
$tab_array[] = array(gettext("Resolver"), false, "/diag_logs_resolver.php");
$tab_array[] = array(gettext("Wireless"), false, "/diag_logs_wireless.php");
display_top_tabs($tab_array, false, 'nav nav-tabs');

require('classes/Form.class.php');

$form = new Form(false);

$section = new Form_Section('Log file filter');

$section->addInput(new Form_Input(
	'filtertext',
	'Filter',
	'text',
	$filtertext,
	['placeholder' => 'Filter text']
));

$form->addGlobal(new Form_Button(
	'filtersubmit',
	'Filter'
))->removeClass('btn-primary')->addClass('btn-default');

$form->addGlobal(new Form_Button(
	'clear',
	'Clear log'
))->removeClass('btn-primary')->addClass('btn-danger');

$form->add($section);
print $form;

?>
    <div class="panel panel-default">
        <div class="panel-heading"><?=gettext("Last ")?><?=$nentries?><?=gettext(" log entries")?></div>
	    <pre>
<?php
    	if($filtertext)
    		dump_clog_no_table($system_logfile, $nentries, true, array("$filtertext"), array("ppp"));
    	else
    		dump_clog_no_table($system_logfile, $nentries, true, array(), array("ppp"));
?>
    	</pre>
	</div>

<?php include("foot.inc"); ?>
