<?php
/*
 * switch_vlans.php
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
##|*IDENT=page-switch-vlans
##|*NAME=Switch: VLANs
##|*DESCR=Allow access to the 'Switch: VLANs' page.
##|*MATCH=switch_vlans.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"));
$shortcut_section = "vlans";
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("System"), false, "switch_system.php");
$tab_array[] = array(gettext("Ports"), false, "switch_ports.php");
$tab_array[] = array(gettext("VLANs"), true, "switch_vlans.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
<form action="interfaces_assign.php" method="post">
	<div class="table-responsive">
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th>&nbsp;</th>
			<th><?=gettext("Switch")?></th>
			<th>&nbsp;</th>
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

$swdevice = NULL;
foreach ($swdevices as $swdev) {
	/* Just in case... */
	pfSense_etherswitch_close();

	if (pfSense_etherswitch_open($swdev) == false) {
		continue;
	}

	$swinfo = pfSense_etherswitch_getinfo();
	if ($swinfo == NULL) {
		pfSense_etherswitch_close();
		continue;
	}
	if ($swdevice == NULL)
		$swdevice = $swdev;
?>
		<tr>
			<td>&nbsp;</td>
			<td><select name="swdevice" id="swdevice" class="form-control">
				<option value="<?= $swdev ?>"><?= $swinfo['name'] ?>
			    </select>
			</td>
			<td>&nbsp;</td>
		</tr>
<?
	pfSense_etherswitch_close();
}
?>
	</tbody>
	</table>
	</div>
</form>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Switch VLANs')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("VLAN group"); ?></th>
						<th><?=gettext("VLAN ID"); ?></th>
						<th><?=gettext("Members"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php

	/* Just in case... */
	pfSense_etherswitch_close();

	if (pfSense_etherswitch_open($swdevice) == false) {
		continue;
	}

	$swinfo = pfSense_etherswitch_getinfo();
	if ($swinfo == NULL) {
		pfSense_etherswitch_close();
		continue;
	}
	for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
		$vgroup = pfSense_etherswitch_getvlangroup($i);
		if ($vgroup == NULL) {
			continue;
		}
?>
					<tr>
						<td>
							<?= htmlspecialchars($vgroup['vlangroup'])?>
						</td>
						<td>
							<?= htmlspecialchars($vgroup['vid'])?>
						</td>
						<td>
<?
		$comma = false;
		foreach ($vgroup['members'] as $member => $val) {
			if ($comma)
				echo ",";
			echo "$member";
			$comma = true;
		}
?>
						</td>
					</tr>
<?
	}

	pfSense_etherswitch_close();

?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php
include("foot.inc");
