<?php
/*
 * interfaces_vxlan_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-vxlan-edit
##|*NAME=Interfaces: VXLAN: Edit
##|*DESCR=Allow access to the 'Interfaces: VXLAN: Edit' page.
##|*MATCH=interfaces_vxlan_edit.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('vxlans', 'vxlan'));
$a_vxlans = &$config['vxlans']['vxlan'];
$id = $_REQUEST['id'];


if (isset($id) && $a_vxlans[$id]) {
	$pconfig['if'] = $a_vxlans[$id]['if'];
	if (!empty($a_vxlans[$id]['ipaddr'])) {
		$pconfig['if'] = $pconfig['if'] . '|' . $a_vxlans[$id]['ipaddr'];
	}
	$pconfig['vxlanif'] = $a_vxlans[$id]['vxlanif'];
	$pconfig['remote-addr'] = $a_vxlans[$id]['remote-addr'];
	$pconfig['vxlanid'] = $a_vxlans[$id]['vxlanid'];
	$pconfig['vxlanlocalport'] = $a_vxlans[$id]['vxlanlocalport'];
	$pconfig['vxlanremoteport'] = $a_vxlans[$id]['vxlanremoteport'];
	$pconfig['vxlanttl'] = $a_vxlans[$id]['vxlanttl'];
	$pconfig['vxlanlearn'] = isset($a_vxlans[$id]['vxlanlearn']);
	$pconfig['descr'] = $a_vxlans[$id]['descr'];
} else {
	$pconfig['vxlanlearn'] = true;
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if remote-addr vxlanid");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("VXLAN remote address"), gettext("VXLAN ID"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddr($_POST['remote-addr']) || is_subnet($_POST['remote-addr'])) {
		$input_errors[] = gettext("The remote peer address must have valid IP address and must not contain CIDR masks or prefixes.");
	}

	$alias = strstr($_POST['if'], '|');
	if ((is_ipaddrv4($alias) && !is_ipaddrv4($_POST['remote-addr'])) ||
	    (is_ipaddrv6($alias) && !is_ipaddrv6($_POST['remote-addr']))) {
		$input_errors[] = gettext("The alias IP address family has to match the family of the remote peer address.");
	}

	if (!is_numeric($_POST['vxlanid']) || ($_POST['vxlanid'] < 1) || ($_POST['vxlanid'] > 16777215)) {
		$input_errors[] = gettext("VXLAN ID valid range is 1-16777215.");
	}

	if (!empty($_POST['vxlanlocalport']) && !is_port($_POST['vxlanlocalport'])) {
		$input_errors[] = gettext("VXLAN Local Port must be valid port value.");
	}

	if (!empty($_POST['vxlanremoteport']) && !is_port($_POST['vxlanremoteport'])) {
		$input_errors[] = gettext("VXLAN Local Port must be valid port value.");
	}

	if (!empty($_POST['vxlanttl']) && (!is_numeric($_POST['vxlanttl']) ||
	    ($_POST['vxlanttl'] < 1) || ($_POST['vxlanttl'] > 255))) {
		$input_errors[] = gettext("VXLAN TTL must be in range between 1 and 255.");
	}

	foreach ($a_vxlans as $vxlan) {
		if (isset($id) && ($a_vxlans[$id]) && ($a_vxlans[$id] === $vxlan)) {
			continue;
		}

		if (($vxlan['if'] == $_POST['if']) && ($vxlan['remote-addr'] == $_POST['remote-addr']) &&
		    ($vxlan['vxlanid'] == $vxlan['vxlanid'])) {
			$input_errors[] = sprintf(gettext("A vxlan with the remote address %s and VXLAN ID %s is already defined."), $vxlan['remote-addr'], $vxlan['vxlanid']);
			break;
		}
	}

	if (!$input_errors) {
		$vxlan = array();
		list($vxlan['if'], $vxlan['ipaddr']) = explode("|", $_POST['if']);
		$vxlan['remote-addr'] = $_POST['remote-addr'];
		$vxlan['vxlanid'] = $_POST['vxlanid'];
		$vxlan['vxlanlocalport'] = $_POST['vxlanlocalport'];
		$vxlan['vxlanremoteport'] = $_POST['vxlanremoteport'];
		$vxlan['vxlanttl'] = $_POST['vxlanttl'];
		if (isset($_POST['vxlanlearn'])) {
			$vxlan['vxlanlearn'] = '';
		}
		$vxlan['descr'] = $_POST['descr'];
		$vxlan['vxlanif'] = $_POST['vxlanif'];
		$vxlan['vxlanif'] = interface_vxlan_configure($vxlan);

		if ($vxlan['vxlanif'] == "" || !stristr($vxlan['vxlanif'], "vxlan")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_vxlans[$id]) {
				$a_vxlans[$id] = $vxlan;
			} else {
				$a_vxlans[] = $vxlan;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($vxlan['vxlanif']);

			if ($confif != "") {
				interface_configure($confif);
			}

			header("Location: interfaces_vxlan.php");
			exit;
		}
	}
}

function build_parent_list() {
	$parentlist = array();
	$portlist = get_possible_listen_ips();
	foreach ($portlist as $ifn => $ifinfo) {
		$parentlist[$ifn] = $ifinfo;
	}

	return($parentlist);
}

$pgtitle = array(gettext("Interfaces"), gettext("VXLANs"), gettext("Edit"));
$pglinks = array("", "interfaces_vxlan.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('VXLAN Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the VXLAN tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'*VXLAN Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address or multicast group where encapsulated VXLAN packets will be sent.');

$section->addInput(new Form_Input(
	'vxlanid',
	'*VXLAN ID',
	'text',
	$pconfig['vxlanid']
))->setHelp('24-bit VXLAN Network Identifier (VNI) that identifies the virtual network segment membership of the interface.');

$section->addInput(new Form_Input(
	'vxlanlocalport',
	'VXLAN Local Port',
	'text',
	$pconfig['vxlanlocalport'],
	['placeholder' => '4789']
))->setHelp('The port number the interface will listen on. The default port number is 4789.');

$section->addInput(new Form_Input(
	'vxlanremoteport',
	'VXLAN Remote Port',
	'text',
	$pconfig['vxlanremoteport'],
	['placeholder' => '4789']
))->setHelp('The destination port number used in the encapsulating IPv4/IPv6 header. ' .
	'The remote host should be listening on this port. The default port number is 4789. ' .
	'Note some other implementations, such as Linux, do not default to the IANA assigned port, ' .
	'but instead listen on port 8472.');

$section->addInput(new Form_Input(
	'vxlanttl',
	'VXLAN TTL',
	'text',
	$pconfig['vxlanttl'],
	['placeholder' => '64']
))->setHelp('The TTL used in the encapsulating IPv4/IPv6 header. The default is	64.');

$section->addInput(new Form_Checkbox(
	'vxlanlearn',
	'VXLAN Learn',
	'Populate the forwarding table with the source IP address and inner source Ethernet MAC address of received packets.',
	$pconfig['vxlanlearn']
))->setHelp('The source IP address and inner source Ethernet MAC address of ' .
	'received packets are used to dynamically populate the forwarding ' .
	'table. When in multicast mode, an entry in the forwarding table ' .
	'allows the interface to send the frame directly to the remote ' .
	'host instead of broadcasting the frame to the multicast group. ' .
	'This is the default.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->addGlobal(new Form_Input(
	'vxlanif',
	null,
	'hidden',
	$pconfig['vxlanif']
));

if (isset($id) && $a_vxlans[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
