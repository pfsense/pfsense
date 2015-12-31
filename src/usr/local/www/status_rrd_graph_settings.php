<?php
/*
	status_rrd_graph_settings.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2007 Seth Mos <seth.mos@dds.nl>
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

##|+PRIV
##|*IDENT=page-status-rrdgraph-settings
##|*NAME=Status: RRD Graphs: Settings
##|*DESCR=Allow access to the 'Status: RRD Graphs: Settings' page.
##|*MATCH=status_rrd_graph_settings.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");

$pconfig['enable'] = isset($config['rrd']['enable']);
$pconfig['category'] = $config['rrd']['category'];
$pconfig['style'] = $config['rrd']['style'];
$pconfig['period'] = $config['rrd']['period'];

$curcat = "settings";
$categories = array('system' => gettext("System"),
	'traffic' => gettext("Traffic"),
	'packets' => gettext("Packets"),
	'quality' => gettext("Quality"),
	'queues' => gettext("Queues"),
	'captiveportal' => gettext("Captive Portal"));

if (isset($config['ntpd']['statsgraph'])) {
	$categories['ntpd'] = gettext("NTP");
}

$styles = array('inverse' => gettext("Inverse"),
	'absolute' => gettext("Absolute"));
$periods = array("absolute" => gettext("Absolute Timespans"),
	"current" => gettext("Current Period"),
	"previous" => gettext("Previous Period"));

if ($_POST['ResetRRD']) {
	mwexec('/bin/rm /var/db/rrd/*');
	enable_rrd_graphing();
	$savemsg = "RRD data has been cleared. New RRD files have been generated.";
} elseif ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		$config['rrd']['enable'] = $_POST['enable'] ? true : false;
		$config['rrd']['category'] = $_POST['category'];
		$config['rrd']['style'] = $_POST['style'];
		$config['rrd']['period'] = $_POST['period'];
		write_config();

		$retval = 0;
		$retval = enable_rrd_graphing();
		$savemsg = get_std_save_message($retval);
	}
}

$here = getcwd();
$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob('*.rrd');
chdir($here);		// Need to go back home otherwise the 'include/requires fail!

foreach ($databases as $database) {
	if (stristr($database, "wireless")) {
		$wireless = true;
	}
	if (stristr($database, "queues")) {
		$queues = true;
	}
	if (stristr($database, "-cellular") && !empty($config['ppps'])) {
		$cellular = true;
	}
	if (stristr($database, "-vpnusers")) {
		$vpnusers = true;
	}
	if (stristr($database, "captiveportal-") && is_array($config['captiveportal'])) {
		$captiveportal = true;
	}
	if (stristr($database, "ntpd") && isset($config['ntpd']['statsgraph'])) {
		$ntpd = true;
	}
	if (stristr($database, "-dhcpd") && is_array($config['dhcpd'])) {
		$dhcpd = true;
	}
}

$pgtitle = array(gettext("Status"), gettext("RRD Graphs"), gettext("Settings"));
include("head.inc");

$tab_array[] = array(gettext("System"), ($curcat == "system"), "status_rrd_graph.php?cat=system");
$tab_array[] = array(gettext("Traffic"), ($curcat == "traffic"), "status_rrd_graph.php?cat=traffic");
$tab_array[] = array(gettext("Packets"), ($curcat == "packets"), "status_rrd_graph.php?cat=packets");
$tab_array[] = array(gettext("Quality"), ($curcat == "quality"), "status_rrd_graph.php?cat=quality");

if ($queues) {
	$tab_array[] = array(gettext("Queues"), ($curcat == "queues"), "status_rrd_graph.php?cat=queues");
	$tab_array[] = array(gettext("QueueDrops"), ($curcat == "queuedrops"), "status_rrd_graph.php?cat=queuedrops");
}

if ($wireless) {
	$tab_array[] = array(gettext("Wireless"), ($curcat == "wireless"), "status_rrd_graph.php?cat=wireless");
}

if ($cellular) {
	$tab_array[] = array(gettext("Cellular"), ($curcat == "cellular"), "status_rrd_graph.php?cat=cellular");
}

if ($vpnusers) {
	$tab_array[] = array(gettext("VPN"), ($curcat == "vpnusers"), "status_rrd_graph.php?cat=vpnusers");
}

if ($captiveportal) {
	$tab_array[] = array(gettext("Captive Portal"), ($curcat == "captiveportal"), "status_rrd_graph.php?cat=captiveportal");
}

if ($ntpd) {
	$tab_array[] = array(gettext("NTPD"), ($curcat == "ntpd"), "status_rrd_graph.php?cat=ntpd");
}

if ($dhcpd) {
	$tab_array[] = array(gettext("DHCP Server"), ($curcat == "dhcpd"), "status_rrd_graph.php?cat=dhcpd");
}

$tab_array[] = array(gettext("Custom"), ($curcat == "custom"), "status_rrd_graph.php?cat=custom");
$tab_array[] = array(gettext("Settings"), ($curcat == "settings"), "status_rrd_graph_settings.php");

display_top_tabs($tab_array);

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form;

$section = new Form_Section('Graph settings');

$section->addInput(new Form_Checkbox(
	'enable',
	'RRD Graphs',
	'Enable the RRD Graphing backend',
	$pconfig['enable']
));

$section->addInput(new Form_Select(
	'category',
	'Default category',
	$pconfig['category'],
	$categories
));

$section->addInput(new Form_Select(
	'style',
	'Default style',
	$pconfig['style'],
	$styles
));

$section->addInput(new Form_Select(
	'period',
	'Default period',
	$pconfig['period'],
	$periods
))->setHelp('Graphs will not be allowed to be recreated within a 1 minute interval, please ' .
			'take this into account after changing the style.');

$form->addGlobal(new Form_Button(
	'ResetRRD',
	'Reset RRD Data'
))->removeClass('btn-primary')->addClass('btn-danger');

$form->add($section);
print $form;

include("foot.inc");
