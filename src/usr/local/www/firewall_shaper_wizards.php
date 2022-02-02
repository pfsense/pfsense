<?php
/*
 * firewall_shaper_wizards.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-firewall-trafficshaper-wizard
##|*NAME=Firewall: Traffic Shaper: Wizard
##|*DESCR=Allow access to the 'Firewall: Traffic Shaper: Wizard' page.
##|*MATCH=firewall_shaper_wizards.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");

if ($_GET['reset'] != "") {
	sigkillbyname('pfctl', SIGKILL);
	exit;
}

if ($_POST['apply']) {
	write_config("Traffic Shaper Wizard settings applied");

	$retval = 0;
	/* Setup pf rules since the user may have changed the optimization value */
	$retval |= filter_configure();

	/* reset rrd queues */
	unlink_if_exists("/var/db/rrd/*queuedrops.rrd");
	unlink_if_exists("/var/db/rrd/*queues.rrd");
	enable_rrd_graphing();

	clear_subsystem_dirty('shaper');
}

$shaperIFlist = get_configured_interface_with_descr();

$pgtitle = array(gettext("Firewall"), gettext("Traffic Shaper"), gettext("Wizards"));
$pglinks = array("", "firewall_shaper.php", "@self");
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
$tab_array[] = array(gettext("Limiters"), false, "firewall_shaper_vinterface.php");
$tab_array[] = array(gettext("Wizards"), true, "firewall_shaper_wizards.php");
display_top_tabs($tab_array);

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('shaper')) {
	print_apply_box(gettext("The traffic shaper configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
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
<?php if (empty(get_interface_list_to_show())): ?>
<div>
	<div class="infoblock blockopen">
		<?php print_info_box(gettext("This firewall does not have any interfaces assigned that are capable of using ALTQ traffic shaping."), 'danger', false); ?>
	</div>
</div>
<?php endif; ?>

<?php
include("foot.inc");
