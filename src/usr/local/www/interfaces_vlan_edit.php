<?php
/*
 * interfaces_vlan_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-vlan-edit
##|*NAME=Interfaces: VLAN: Edit
##|*DESCR=Allow access to the 'Interfaces: VLAN: Edit' page.
##|*MATCH=interfaces_vlan_edit.php*
##|-PRIV

require_once("config.lib.inc");
require_once("guiconfig.inc");

config_init_path('vlans/vlan');

$portlist = get_interface_list();
$lagglist = get_lagg_interface_list();
$portlist = array_merge($portlist, $lagglist);
foreach ($lagglist as $lagg) {
	/* LAGG members cannot be assigned */
	$laggmembers = explode(',', $lagg['members']);
	foreach ($laggmembers as $lagm) {
		if (isset($portlist[$lagm])) {
			unset($portlist[$lagm]);
		}
	}
}

/* Do not allow OpenVPN TUN interfaces to be used for QinQ
 * https://redmine.pfsense.org/issues/11675 */
config_init_path('openvpn/openvpn-server');
config_init_path('openvpn/openvpn-client');
foreach ($portlist as $portname => $port) {
	if (strstr($portname, "ovpn")) {
		preg_match('/ovpn([cs])([1-9]+)/', $portname, $m);
		$type = ($m[1] == 'c') ? 'client' : 'server';
		foreach (config_get_path("openvpn/openvpn-{$type}", []) as $ovpn) {
			if (($ovpn['vpnid'] == $m[2]) && ($ovpn['dev_mode'] == 'tun')) {
				unset($portlist[$portname]);
			}
		}
	}
}

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$this_vlan_config = isset($id) ? config_get_path("vlans/vlan/{$id}") : null;
if ($this_vlan_config) {
	$pconfig['if'] = $this_vlan_config['if'];
	$pconfig['vlanif'] = $this_vlan_config['vlanif'];
	$pconfig['tag'] = $this_vlan_config['tag'];
	$pconfig['pcp'] = $this_vlan_config['pcp'];
	$pconfig['descr'] = $this_vlan_config['descr'];
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/*
	 * Check user privileges to test if the user is allowed to make changes.
	 * Otherwise users can end up in an inconsistent state where some changes are
	 * performed and others denied. See https://redmine.pfsense.org/issues/15282
	 */
	phpsession_begin();
	$guiuser = getUserEntry($_SESSION['Username']);
	$read_only = (is_array($guiuser) && userHasPrivilege($guiuser, "user-config-readonly"));
	phpsession_end();

	if ($read_only) {
		$input_errors = array(gettext("Insufficient privileges to make the requested change (read only)."));
	}

	/* input validation */
	$reqdfields = explode(" ", "if tag");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("VLAN tag"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (isset($_POST['tag']) && !vlan_valid_tag($_POST['tag'])) {
		$input_errors[] = gettext("The VLAN tag must be an integer between 1 and 4094.");
	}
	if (isset($_POST['pcp']) && !empty($_POST['pcp']) && (!is_numericint($_POST['pcp']) || ($_POST['pcp'] < '0') || ($_POST['pcp'] > '7'))) {
		$input_errors[] = gettext("The VLAN Priority must be an integer between 0 and 7.");
 	}

	if (!does_interface_exist($_POST['if'])) {
		$input_errors[] = gettext("Interface supplied as parent is invalid");
	}

	if (isset($id)) {
		if ($_POST['tag'] && $_POST['tag'] != $this_vlan_config['tag']) {
			if (!empty($this_vlan_config['vlanif']) && convert_real_interface_to_friendly_interface_name($this_vlan_config['vlanif']) != NULL) {
				$input_errors[] = gettext("The VLAN tag cannot be changed while the interface is assigned.");
			}
		}
	}
	foreach (config_get_path('vlans/vlan', []) as $vlan) {
		if ($this_vlan_config && ($this_vlan_config === $vlan)) {
			continue;
		}

		if (($vlan['if'] == $_POST['if']) && ($vlan['tag'] == $_POST['tag'])) {
			$input_errors[] = sprintf(gettext("A VLAN with the tag %s is already defined on this interface."), $vlan['tag']);
			break;
		}
	}

	foreach (config_get_path('qinqs/qinqentry', []) as $qinq) {
		if ($qinq['tag'] == $_POST['tag'] && $qinq['if'] == $_POST['if']) {
			$input_errors[] = sprintf(gettext('A QinQ VLAN exists on %s with this tag. Please remove it to use this tag for a normal VLAN.'), $_POST['if']);
		}
	}

	if (!$input_errors) {
		if ($this_vlan_config) {
			if (($this_vlan_config['if'] != $_POST['if']) || ($this_vlan_config['tag'] != $_POST['tag'])) {
				if (!empty($this_vlan_config['vlanif'])) {
					$confif = convert_real_interface_to_friendly_interface_name($this_vlan_config['vlanif']);
					// Destroy previous vlan
					pfSense_interface_destroy($this_vlan_config['vlanif']);
				} else {
					pfSense_interface_destroy(vlan_interface($this_vlan_config));
					$confif = convert_real_interface_to_friendly_interface_name(vlan_interface($this_vlan_config));
				}
				if ($confif != "") {
					config_set_path("interfaces/{$confif}/if", vlan_interface($_POST));
				}
			}
		}
		$vlan = array();
		$vlan['if'] = $_POST['if'];
		$vlan['tag'] = $_POST['tag'];
		$vlan['pcp'] = $_POST['pcp'];
		$vlan['descr'] = $_POST['descr'];
		$vlan['vlanif'] = vlan_interface($_POST);
		$vlanif = interface_vlan_configure($vlan);
		if ($vlanif == NULL || $vlanif != $vlan['vlanif']) {
			pfSense_interface_destroy($vlan['vlanif']);
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if ($this_vlan_config) {
				config_set_path("vlans/vlan/{$id}", $vlan);
			} else {
				config_set_path('vlans/vlan/', $vlan);
			}

			write_config("VLAN interface added");

			if ($confif != "") {
				interface_configure($confif);
			}
			header("Location: interfaces_vlan.php");
			exit;
		}
	}
}

function build_interfaces_list() {
	global $portlist;

	$list = array();

	foreach ($portlist as $ifn => $ifinfo) {
		$list[$ifn] = $ifn . " (" . $ifinfo['mac'] . ")";
		$iface = convert_real_interface_to_friendly_interface_name($ifn);
		if (isset($iface) && strlen($iface) > 0) {
			$list[$ifn] .= " - $iface";
		}
	}

	return($list);
}

$pgtitle = array(gettext("Interfaces"), gettext("VLANs"), gettext("Edit"));
$pglinks = array("", "interfaces_vlan.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$section = new Form_Section('VLAN Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_interfaces_list()
))->setWidth(6)->setHelp('Only VLAN capable interfaces will be shown.');

$section->addInput(new Form_Input(
	'tag',
	'*VLAN Tag',
	'text',
	$pconfig['tag'],
	['placeholder' => '1']
))->setWidth(6)->setHelp('802.1Q VLAN tag (between 1 and 4094).');

$section->addInput(new Form_Input(
	'pcp',
	'VLAN Priority',
	'text',
	$pconfig['pcp'],
	['placeholder' => '0']
))->setWidth(6)->setHelp('802.1Q VLAN Priority (between 0 and 7).');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setWidth(6)->setHelp('A group description may be entered here for administrative reference (not parsed).');

$form->addGlobal(new Form_Input(
	'vlanif',
	'vlanif',
	'hidden',
	$pconfig['vlanif']
));

if ($this_vlan_config) {
	$form->addGlobal(new Form_Input(
		'id',
		'id',
		'hidden',
		$id
	));
}

$form->add($section);
print $form;

include("foot.inc");
