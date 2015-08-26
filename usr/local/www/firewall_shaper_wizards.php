<?php
/* $Id$ */
/*
	firewall_shaper_wizards.php
	Copyright (C) 2004, 2005 Scott Ullrich
	Copyright (C) 2008 Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_BUILDER_BINARIES:	/usr/bin/killall
	pfSense_MODULE: shaper
*/

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-wizard
##|*NAME=Firewall: Traffic Shaper: Wizard page
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Wizard' page.
##|*MATCH=firewall_shaper_wizards.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");

if($_GET['reset'] != "") {
	sigkillbyname('pfctl', SIGKILL);
	exit;
}

if ($_POST['apply']) {
	write_config();

	$retval = 0;
	/* Setup pf rules since the user may have changed the optimization value */
	$retval = filter_configure();
	$savemsg = get_std_save_message($retval);
	if (stristr($retval, "error") <> true) {
		$savemsg = get_std_save_message($retval);
	} else {
		$savemsg = $retval;
	}

	/* reset rrd queues */
	unlink_if_exists("/var/db/rrd/*queuedrops.rrd");
	unlink_if_exists("/var/db/rrd/*queues.rrd");
	enable_rrd_graphing();

	clear_subsystem_dirty('shaper');
}

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Wizards"));
$shortcut_section = "trafficshaper";

$wizards = array(
	gettext("Multiple Lan/Wan") => "traffic_shaper_wizard_multi_all.xml",
	gettext("Dedicated Links") => "traffic_shaper_wizard_dedicated.xml",
);

$closehead = false;
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Layer7"), false, "firewall_shaper_layer7.php");
$tab_array[] = array(gettext("Wizards"), true, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

if ($savemsg)
	print_info_box($savemsg, 'success');

if (is_subsystem_dirty('shaper'))
	print_info_box_np(gettext("The traffic shaper configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Traffic Shaper Wizards')?></h2></div>
	<div class="panel-body">
		<dl	 class="dl-horizontal responsive">
<?php
foreach ($wizards as $key => $wizard):
?>
			<dt>
				<?=$key?>
			</dt>
			<dd>
				<?='<a href="wizard.php?xml=' . $wizard . '">' . $wizard . '</a>'?>
			</dd>
<?php
endforeach;
?>
		</dl>
	</div>
</div>
<?php
include("foot.inc");