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
##|*IDENT=page-switch-vlans-edit
##|*NAME=Switch: VLANs Edit
##|*DESCR=Allow access to the 'Switch: VLANs Edit' page.
##|*MATCH=switch_vlans_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("switch.inc");

$pgtitle = array(gettext("Interfaces"), gettext("Switch"), gettext("VLANs"), gettext("Edit"));
$shortcut_section = "vlans";
include("head.inc");

// Create an array containing a list of hte available ports on the specified switch
function available_ports($dev) {

	//pfSense_etherswitch_close();

	if (pfSense_etherswitch_open($dev) == false) {
		return(array());
	}

	$swinfo = pfSense_etherswitch_getinfo();

	if ($swinfo == NULL) {
		pfSense_etherswitch_close();
		return(array();
	}

	$portlist = array();

	for($idx=0; $idx<$swinfo['nports']; $idx++) {
		$portlist[$idx] = "Port " . $idx;
	}

	return($portlist);
}

switch ($_GET['act']) {
		case "edit" :
			$vid = $_GET['vid'];
			$device = $_GET['swdevice'];

			print("<h3>Under construction</h3>");
			print("<br />");

			print("Editing VLAN ID: " . $vid . " on device: " . $device);

		break;

		case "new" :
			$device = $_GET['swdevice'];

			$pconfig['vlanid'] = "";
			$pconfig['desc'] = "";

		break;
}

$form = new Form();

$section = new Form_Section("Vlan properties");

$section->addInput(new Form_Input(
	'vlanid',
	'VLAN ID',
	'number',
	$pconfig['vlanid']
))->setHelp("Enter a VLAN ID number (that is not already in use.)");

$section->addInput(new Form_Input(
	'desc',
	'Description',
	'text',
	$pconfig['desc']
))->setHelp("A description may be entered here for administrative reference (not parsed).");

$group = new Form_Group('VLAN Members');

$usersGroups = array();

$group->add(new Form_Select(
	'ports',
	null,
	array(),
	available_ports($device),
	true
))->setHelp('Available ports');

$group->add(new Form_Select(
	'members',
	null,
	array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
	$usersGroups,
	true
))->setHelp('VLAN Members');

$section->add($group);

$form->add($section);

print($form);

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	$('#ports').click(function () {
		alert("Hi");
	});
});
//]]>
</script>
<?php
include("foot.inc");
