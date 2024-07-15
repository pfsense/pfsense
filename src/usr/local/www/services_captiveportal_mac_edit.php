<?php
/*
 * services_captiveportal_mac_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004 Dinesh Nair <dinesh@alphaque.com>
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
##|*IDENT=page-services-captiveportal-editmacaddresses
##|*NAME=Services: Captive Portal: Edit MAC Addresses
##|*DESCR=Allow access to the 'Services: Captive Portal: Edit MAC Addresses' page.
##|*MATCH=services_captiveportal_mac_edit.php*
##|-PRIV

function passthrumacscmp($a, $b) {
	return strcmp($a['mac'], $b['mac']);
}

function passthrumacs_sort() {
	global $cpzone;

	$cp_config = config_get_path("captiveportal/{$cpzone}/passthrumac");
	usort($cp_config, "passthrumacscmp");
	config_set_path("captiveportal/{$cpzone}/passthrumac", $cp_config);
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

global $cpzone;
global $cpzoneid;

$cpzone = strtolower(htmlspecialchars($_REQUEST['zone']));

if (empty($cpzone) || empty(config_get_path("captiveportal/{$cpzone}"))) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

config_init_path("captiveportal/{$cpzone}/passthrumac");
$cpzoneid = config_get_path("captiveportal/{$cpzone}/zoneid");

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), config_get_path("captiveportal/{$cpzone}/zone"), gettext("MACs"), gettext("Edit"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "services_captiveportal_mac.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$this_passthrumac_config = isset($id) ? config_get_path("captiveportal/{$cpzone}/passthrumac/{$id}") : null;
if ($this_passthrumac_config) {
	$pconfig['action'] = $this_passthrumac_config['action'];
	$pconfig['mac'] = $this_passthrumac_config['mac'];
	$pconfig['bw_up'] = $this_passthrumac_config['bw_up'];
	$pconfig['bw_down'] = $this_passthrumac_config['bw_down'];
	$pconfig['descr'] = $this_passthrumac_config['descr'];
	$pconfig['username'] = $this_passthrumac_config['username'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "action mac");
	$reqdfieldsn = array(gettext("Action"), gettext("MAC address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	list($macfull, $macmask) = explode('/', $_POST['mac']);
	$_POST['mac'] = trim(strtolower(str_replace("-", ":", $macfull)));

	if ($_POST['mac']) {
		if (is_macaddr($_POST['mac'])) {
			$iflist = get_interface_list();
			foreach ($iflist as $if) {
				if ($_POST['mac'] == strtolower($if['mac'])) {
					$input_errors[] = sprintf(gettext("The MAC address %s belongs to a local interface. It cannot be used here."), $_POST['mac']);
					break;
				}
			}
		} else {
			$input_errors[] = sprintf(gettext('A valid MAC address must be specified. [%s]'), $_POST['mac']);
		}
	}
	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}
	if ($_POST['bw_up'] && ($_POST['bw_up'] > 999999 || $_POST['bw_up'] < 1)) {
		$input_errors[] = gettext("Upload speed must be between 1 and 999999");
	}
	if ($_POST['bw_down'] && ($_POST['bw_down'] > 999999 || $_POST['bw_down'] < 1)) {
		$input_errors[] = gettext("Download speed must be between 1 and 999999");
	}
	if (isset($macmask)) {
		if (($macmask > 48) || ($macmask < 1)) {
			$input_errors[] = gettext("MAC address mask must be between 1 and 48");
		}
		if (!is_numericint($macmask)) {
			$input_errors[] = gettext("MAC address mask must be an integer.");
		}
	}

	foreach (config_get_path("captiveportal/{$cpzone}/passthrumac", []) as $macent) {
		if ($this_passthrumac_config && ($this_passthrumac_config === $macent)) {
			continue;
		}

		if ($macent['mac'] == $_POST['mac']) {
			$input_errors[] = sprintf(gettext('[%s] already exists.'), $_POST['mac']);
			break;
		}
	}

	if (!$input_errors) {
		$mac = array();
		$mac['action'] = $_POST['action'];
		$mac['mac'] = $_POST['mac'];
		if ($macmask) {
			$mac['mac'] .= '/' . $macmask;
		}
		if ($_POST['bw_up']) {
			$mac['bw_up'] = $_POST['bw_up'];
		}
		if ($_POST['bw_down']) {
			$mac['bw_down'] = $_POST['bw_down'];
		}
		if ($_POST['username']) {
			$mac['username'] = $_POST['username'];
		}

		$mac['descr'] = $_POST['descr'];

		if ($this_passthrumac_config) {
			$oldmac = $this_passthrumac_config;
			config_set_path("captiveportal/{$cpzone}/passthrumac/{$id}", $mac);
		} else {
			$oldmac = $mac;
			config_set_path("captiveportal/{$cpzone}/passthrumac/", $mac);
		}
		passthrumacs_sort();

		write_config("Captive portal passthrough MAC added");

		if (config_path_enabled("captiveportal/{$cpzone}")) {
			$cpzoneid = config_get_path("captiveportal/{$cpzone}/zoneid");
			captiveportal_passthrumac_delete_entry($oldmac);
			captiveportal_ether_configure_entry($mac, 'passthrumac');
			unset($cpzoneid);
		}

		header("Location: services_captiveportal_mac.php?zone={$cpzone}");
		exit;
	}
}

// Get the MAC address
$ip = $_SERVER['REMOTE_ADDR'];
$arp_table = system_get_arp_table();
if (($key = array_search($ip, array_column($arp_table, 'ip-address')))
    !== FALSE) {
	if (!empty($arp_table[$key]['mac-address'])) {
		$mymac = $arp_table[$key]['mac-address'];
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit MAC Address Rules');

$section->addInput(new Form_Select(
	'action',
	'*Action',
	strtolower($pconfig['action']),
	array('pass' => gettext('Pass'), 'block' => gettext('Block'))
))->setHelp('Choose what to do with packets coming from this MAC address.');

$macaddress = new Form_Input(
	'mac',
	'MAC Address',
	'text',
	$pconfig['mac'],
	['placeholder' => 'xx:xx:xx:xx:xx:xx']
);

$btnmymac = new Form_Button(
	'btnmymac',
	'Copy My MAC',
	null,
	'fa-regular fa-clone'
	);

$btnmymac->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group = new Form_Group('*MAC Address');
$group->add($macaddress);
$group->add($btnmymac);
$group->setHelp('The client\'s MAC address, optionally with a mask length (e.g. "/24").');
$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed)');

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter an upload limit to be enforced on this MAC in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this MAC in Kbit/s');

$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if ($this_passthrumac_config) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

if (isset($pconfig['username']) && $pconfig['username']) {
	$form->addGlobal(new Form_Input(
		'username',
		null,
		'hidden',
		$pconfig['username']
	));
}

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// On click, copy the hidden 'mymac' text to the 'mac' input
	$("#btnmymac").click(function() {
		$('#mac').val('<?=$mymac?>');
	});
});
//]]>
</script>

<?php include("foot.inc");
