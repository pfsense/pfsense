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

// Create an array containing a list of the available ports on the specified switch
// ToDo: Need to add "tagged" indicator
function available_ports($swdevice) {

	$portlist = array();

	$swinfo = pfSense_etherswitch_getinfo($swdevice);
	if ($swinfo == NULL) {
		return ($portlist);
	}

	for ($idx = 0; $idx < $swinfo['nports']; $idx++) {
		$swport = pfSense_etherswitch_getport($swdevice, $idx);
		if ($swport == NULL)
			break;
		$portlist[$idx] = "Port ". $swport['port'];
	}

	return($portlist);
}

if ($_POST) {
	// $_POST data will look like this:  [vlanid] => "4321" [desc] => "My test VLAN" [members] => Array ( [0] => 1 [1] => 2 )
	//print_r($_POST);
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
	'availports',
	null,
	array(),
	available_ports($device),
	true
))->setHelp('Available ports<br />Click to add or remove a port from the VLAN');

$group->add(new Form_Select(
	'members',
	null,
	array(),
	$usersGroups,
	true
))->setReadonly()
  ->setHelp('VLAN Members');

$section->add($group);

$form->add($section);

print($form);

?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function updateMembers(mem) {
		var found = false;

		// If the member exists, remove it
		$('[id="members[]"] option').each(function() {
		    if($(this).val() == mem) {
		    	$(this).remove();
				found = true;
		    }
		});

		// If not, add it
	    if (!found) {
			$('[id="members[]"]').append(new Option('Port ' + mem , mem));
	    }

	    // Sort alphabetically
		var options = $('[id="members[]"] option');

		options.detach().sort(function(a,b) {
		    var at = $(a).text();
		    var bt = $(b).text();
		    return (at > bt)?1:((at < bt)?-1:0);
		});

		options.appendTo('[id="members[]"]');

		// Unselect all options
		$('[id="availports[]"] option:selected').removeAttr("selected");


	}

	// On click, update the members list
	$('[id="availports[]"]').click(function () {
		updateMembers($(this).val());
	});

	// When the form is posted, all options in members must be selected if htey are to submit
	$("#save").prop('type' ,'button');

	$('#save').click(function() {
		$('[id="members[]"] option').prop('selected', true);
		$('form').submit();
	});


});
//]]>
</script>
<?php
include("foot.inc");
