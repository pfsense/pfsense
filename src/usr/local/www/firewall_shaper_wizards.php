<?php
/*
	firewall_shaper_wizards.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-wizard
##|*NAME=Firewall: Traffic Shaper: Wizard
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Wizard' page.
##|*MATCH=firewall_shaper_wizards.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");

if ($_GET['reset'] != "") {
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

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("By Interface"), false, "firewall_shaper.php");
$tab_array[] = array(gettext("By Queue"), false, "firewall_shaper_queues.php");
$tab_array[] = array(gettext("Limiter"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Wizards"), true, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('shaper')) {
	print_info_box_np(gettext("The traffic shaper configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
}

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
