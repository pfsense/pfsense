<?php
/*
 * switch_system.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-switch-system
##|*NAME=Switch: System
##|*DESCR=Allow access to the 'Switch: System' page.
##|*MATCH=switch_system.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("System"));
$shortcut_section = "system";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), true, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), false, "switch_vlans.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Switch System')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Type"); ?></th>
						<th><?=gettext("Ports"); ?></th>
						<th><?=gettext("VLAN groups"); ?></th>
						<th><?=gettext("VLAN Mode"); ?></th>
						<th><?=gettext("Capabilities"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

$swdevices = array();

$platform = system_identify_specific_platform();
if ($platform['name'] == "uFW") {
	/* Only one switch on uFW. */
	$swdevices[] = "/dev/etherswitch0";
}

foreach ($swdevices as $swdev) {

	/* Just in case... */
	pfSense_etherswitch_close();

	if (pfSense_etherswitch_open($swdev) == false)
		continue;

	$swinfo = pfSense_etherswitch_getinfo();
	if ($swinfo == NULL) {
		pfSense_etherswitch_close();
		continue;
	}
?>
					<tr>
						<td>
							<?= htmlspecialchars($swinfo['name'])?>
						</td>
						<td>
							<?= htmlspecialchars($swinfo['nports'])?>
						</td>
						<td>
							<?= htmlspecialchars($swinfo['nvlangroups'])?>
						</td>
						<td>
							<?= htmlspecialchars($swinfo['vlan_mode'])?>
						</td>
						<td>
<?
	$comma = false;
	foreach ($swinfo['caps'] as $cap => $val) {
		if ($comma)
			echo ",";
		echo "$cap";
		$comma = true;
	}
?>
						</td>
					</tr>
<?

	pfSense_etherswitch_close();
}

?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php
include("foot.inc");
