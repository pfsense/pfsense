<?php
/*
 * interfaces_gre_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-gre-edit
##|*NAME=Interfaces: GRE: Edit
##|*DESCR=Allow access to the 'Interfaces: GRE: Edit' page.
##|*MATCH=interfaces_gre_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_gre_config = isset($id) ? config_get_path("gres/gre/{$id}") : null;
if ($this_gre_config) {
	$pconfig['if'] = $this_gre_config['if'];
	$pconfig['greif'] = $this_gre_config['greif'];
	$pconfig['remote-addr'] = $this_gre_config['remote-addr'];
	$pconfig['tunnel-remote-net'] = $this_gre_config['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $this_gre_config['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $this_gre_config['tunnel-remote-addr'];
	$pconfig['tunnel-remote-net6'] = $this_gre_config['tunnel-remote-net6'];
	$pconfig['tunnel-local-addr6'] = $this_gre_config['tunnel-local-addr6'];
	$pconfig['tunnel-remote-addr6'] = $this_gre_config['tunnel-remote-addr6'];
	$pconfig['link1'] = isset($this_gre_config['link1']);
	$pconfig['link2'] = isset($this_gre_config['link2']);
	$pconfig['link0'] = isset($this_gre_config['link0']);
	$pconfig['descr'] = $this_gre_config['descr'];
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	$pconfig['tunnel-local-addr'] = addrtolower($_POST['tunnel-local-addr']);
	$pconfig['tunnel-remote-addr'] = addrtolower($_POST['tunnel-remote-addr']);
	$pconfig['tunnel-local-addr6'] = addrtolower($_POST['tunnel-local-addr6']);
	$pconfig['tunnel-remote-addr6'] = addrtolower($_POST['tunnel-remote-addr6']);
	$pconfig['remote-addr'] = addrtolower($_POST['remote-addr']);

	$tunnel_type = '';
	if ((!empty($pconfig['tunnel-local-addr'])) || (!empty($pconfig['tunnel-remote-addr']))) {
		$tunnel_type = 'v4';
	}
	if ((!empty($pconfig['tunnel-local-addr6'])) || (!empty($pconfig['tunnel-remote-addr6']))) {
		$tunnel_type .= 'v6';
	}

	/* input validation */
	if ($tunnel_type === 'v4v6') {
		$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net tunnel-local-addr6 tunnel-remote-addr6 tunnel-remote-net6");
		$reqdfieldsn = array(
			gettext("Parent interface"),
			gettext("Remote Address"),
			gettext("Local IPv4 tunnel address"),
			gettext("Remote IPv4 tunnel address"),
			gettext("IPv4 tunnel subnet"),
			gettext("Local IPv6 tunnel address"),
			gettext("Remote IPv6 tunnel address"),
			gettext("IPv6 tunnel subnet"));
	} else if ($tunnel_type === 'v6') {
		$reqdfields = explode(" ", "if remote-addr tunnel-local-addr6 tunnel-remote-addr6 tunnel-remote-net6");
		$reqdfieldsn = array(
			gettext("Parent interface"),
			gettext("Remote Address"),
			gettext("Local IPv6 tunnel address"),
			gettext("Remote IPv6 tunnel address"),
			gettext("IPv6 tunnel subnet"));
	} else {
		$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net");
		$reqdfieldsn = array(
			gettext("Parent interface"),
			gettext("Remote Address"),
			gettext("Local IPv4 tunnel address"),
			gettext("Remote IPv4 tunnel address"),
			gettext("IPv4 tunnel subnet"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($tunnel_type === '') {
		$input_errors[] = gettext("The tunnel needs either a valid IPv4 or IPv6 tunnel configuration.");
	}

	if (!is_ipaddr($_POST['remote-addr'])) {
		$input_errors[] = gettext("The remote address must be a valid IP address.");
	}

	if ((!is_ipaddrv4($_POST['tunnel-local-addr'])) &&
	    in_array($tunnel_type, array('v4', 'v4v6'))) {
		$input_errors[] = gettext("The IPv4 local tunnel address must be a valid IPv4 address.");
	}

	if ((!is_ipaddrv4($_POST['tunnel-remote-addr'])) &&
	    in_array($tunnel_type, array('v4', 'v4v6'))) {
		$input_errors[] = gettext("The IPv4 remote tunnel address must be a valid IPv4 address.");
	}

	if ((!is_ipaddrv6($_POST['tunnel-local-addr6'])) &&
	     in_array($tunnel_type, array('v6', 'v4v6'))) {
		$input_errors[] = gettext("The IPv6 local tunnel address must be a valid IPv6 address.");
	}

	if ((!is_ipaddrv6($_POST['tunnel-remote-addr6'])) &&
	     in_array($tunnel_type, array('v6', 'v4v6'))) {
		$input_errors[] = gettext("The IPv6 remote tunnel address must be a valid IPv6 address.");
	}

	if ((!is_numericint($_POST['tunnel-remote-net'])) || ($_POST['tunnel-remote-net'] > 32) || ($_POST['tunnel-remote-net'] < 1)) {
		$input_errors[] = gettext("The IPv4 tunnel subnet must be an integer between 1 and 32.");
	}

	if ((!is_numericint($_POST['tunnel-remote-net6'])) || ($_POST['tunnel-remote-net6'] > 128) || ($_POST['tunnel-remote-net6'] < 1)) {
		$input_errors[] = gettext("The IPv6 tunnel subnet must be an integer between 1 and 128.");
	}

	foreach (config_get_path('gres/gre', []) as $gre) {
		if ($this_gre_config && ($this_gre_config === $gre)) {
			continue;
		}

		if (($gre['if'] == $_POST['if']) && !empty($gre['tunnel-remote-addr']) &&
		    ($gre['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A GRE tunnel with the same IPv4 tunnel network is already defined."));
			break;
		}
		if (($gre['if'] == $_POST['if']) && !empty($gre['tunnel-remote-addr6']) &&
		    ($gre['tunnel-remote-addr6'] == $_POST['tunnel-remote-addr6'])) {
			$input_errors[] = sprintf(gettext("A GRE tunnel with the same IPv6 tunnel network is already defined."));
			break;
		}
	}

	if (!$input_errors) {
		$gre = array();
		$gre['if'] = $_POST['if'];
		$gre['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gre['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gre['tunnel-local-addr6'] = $_POST['tunnel-local-addr6'];
		$gre['tunnel-remote-addr6'] = $_POST['tunnel-remote-addr6'];
		$gre['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gre['tunnel-remote-net6'] = $_POST['tunnel-remote-net6'];
		$gre['remote-addr'] = $_POST['remote-addr'];
		$gre['descr'] = $_POST['descr'];
		if (isset($_POST['link1']) && $_POST['link1']) {
			$gre['link1'] = '';
		}

		if (empty($_POST['greif']) ||
		    preg_match("/^gre[0-9]+$/", $_POST['greif'])) {
			/* Attempt initial configuration of the GRE if the
			 * submitted interface is empty or looks like a GRE
			 * interface. */
			$gre['greif'] = $_POST['greif'];
			$gre['greif'] = interface_gre_configure($gre);
		} else {
			$input_errors[] = gettext("Invalid GRE interface.");
		}

		if (empty($gre['greif']) ||
		    !preg_match("/^gre[0-9]+$/", $gre['greif'])) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if ($this_gre_config) {
				config_set_path("gres/gre/{$id}", $gre);
			} else {
				config_set_path('gres/gre/', $gre);
			}

			write_config("GRE interface added");

			$confif = convert_real_interface_to_friendly_interface_name($gre['greif']);

			if ($confif != "") {
				interface_configure($confif);
			}

			header("Location: interfaces_gre.php");
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

$pgtitle = array(gettext("Interfaces"), gettext("GREs"), gettext("Edit"));
$pglinks = array("", "interfaces_gre.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('GRE Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the GRE tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'*Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address where encapsulated GRE packets will be sent.');

$group = new Form_Group(gettext('IPv4'));

$group->add(new Form_IpAddress(
	'tunnel-local-addr',
	'*Local Tunnel Address',
	$pconfig['tunnel-local-addr'],
	'V4'
))->setHelp('Local IPv4 tunnel address.');

$group->add(new Form_IpAddress(
	'tunnel-remote-addr',
	'*Remote Tunnel Address',
	$pconfig['tunnel-remote-addr'],
	'V4'
))->setHelp('Remote IPv4 tunnel address.');

$group->add(new Form_Select(
	'tunnel-remote-net',
	'*Tunnel subnet',
	$pconfig['tunnel-remote-net'],
	array_combine(range(32, 1, -1), range(32, 1, -1))
))->setHelp('The subnet is used for determining the IPv4 network that is tunnelled.');

$section->add($group);

$group = new Form_Group(gettext('IPv6'));

$group->add(new Form_IpAddress(
	'tunnel-local-addr6',
	'*Local Tunnel Address',
	$pconfig['tunnel-local-addr6'],
	'V6'
))->setHelp('Local IPv6 tunnel address.');

$group->add(new Form_IpAddress(
	'tunnel-remote-addr6',
	'*Remote Tunnel address',
	$pconfig['tunnel-remote-addr6'],
	'V6'
))->setHelp('Remote IPv6 tunnel address.');

$group->add(new Form_Select(
	'tunnel-remote-net6',
	'*Tunnel subnet',
	$pconfig['tunnel-remote-net6'],
	array_combine(range(128, 1, -1), range(128, 1, -1))
))->setHelp('The subnet is used for determining the IPv6 network that is tunnelled.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'link1',
	'Add Static Route',
	'Add an explicit static route for the remote inner tunnel address/subnet via the local tunnel address',
	$pconfig['link1']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->addGlobal(new Form_Input(
	'greif',
	null,
	'hidden',
	$pconfig['greif']
));

if ($this_gre_config) {
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
