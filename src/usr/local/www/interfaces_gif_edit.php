<?php
/*
 * interfaces_gif_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-gif-edit
##|*NAME=Interfaces: GIF: Edit
##|*DESCR=Allow access to the 'Interfaces: GIF: Edit' page.
##|*MATCH=interfaces_gif_edit.php*
##|-PRIV

require_once("guiconfig.inc");

config_init_path('gifs/gif');
$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_gif_config = isset($id) ? config_get_path("gifs/gif/{$id}") : null;
if ($this_gif_config) {
	$pconfig['if'] = $this_gif_config['if'];
	if (!empty($this_gif_config['ipaddr'])) {
		$pconfig['if'] = $pconfig['if'] . '|' . $this_gif_config['ipaddr'];
	}
	$pconfig['gifif'] = $this_gif_config['gifif'];
	$pconfig['remote-addr'] = $this_gif_config['remote-addr'];
	$pconfig['tunnel-remote-net'] = $this_gif_config['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $this_gif_config['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $this_gif_config['tunnel-remote-addr'];
	$pconfig['link1'] = isset($this_gif_config['link1']);
	$pconfig['link2'] = isset($this_gif_config['link2']);
	$pconfig['descr'] = $this_gif_config['descr'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("gif remote address"), gettext("gif tunnel local address"), gettext("gif tunnel remote address"), gettext("gif tunnel remote netmask"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr']) || is_subnet($_POST['tunnel-local-addr'])) ||
	    (!is_ipaddr($_POST['tunnel-remote-addr']) || is_subnet($_POST['tunnel-remote-addr'])) ||
	    (!is_ipaddr($_POST['remote-addr']) || is_subnet($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses and must not contain CIDR masks or prefixes.");
	}

	if (!is_numericint($_POST['tunnel-remote-net'])) {
		$input_errors[] = gettext("The gif tunnel subnet must be an integer.");
	}

	if (is_ipaddrv4($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv4($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The gif tunnel remote address must be IPv4 where tunnel local address is IPv4.");
		}
		if ($_POST['tunnel-remote-net'] > 32 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The gif tunnel subnet must be an integer between 1 and 32.");
		}
	}

	if (is_ipaddrv6($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv6($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The gif tunnel remote address must be IPv6 where tunnel local address is IPv6.");
		}
		if ($_POST['tunnel-remote-net'] > 128 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The gif tunnel subnet must be an integer between 1 and 128.");
		}
	}

	$alias = strstr($_POST['if'], '|');
	if ((is_ipaddrv4($alias) && !is_ipaddrv4($_POST['remote-addr'])) ||
	    (is_ipaddrv6($alias) && !is_ipaddrv6($_POST['remote-addr']))) {
		$input_errors[] = gettext("The alias IP address family has to match the family of the remote peer address.");
	}

	foreach (config_get_path('gifs/gif', []) as $gif) {
		if ($this_gif_config && ($this_gif_config === $gif)) {
			continue;
		}

		/* FIXME: needs to perform proper subnet checks in the future */
		if (($gif['if'] == $interface) && ($gif['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A gif with the network %s is already defined."), $gif['tunnel-remote-addr']);
			break;
		}
	}

	if (!$input_errors) {
		$gif = array();
		list($gif['if'], $gif['ipaddr']) = explode("|", $_POST['if']);
		$gif['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gif['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gif['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gif['remote-addr'] = $_POST['remote-addr'];
		$gif['descr'] = $_POST['descr'];
		if (isset($_POST['link1'])) {
			$gif['link1'] = '';
		}
		if (isset($_POST['link2'])) {
			$gif['link2'] = '';
		}

		if (empty($_POST['gifif']) ||
		    preg_match("/^gif[0-9]+$/", $_POST['gifif'])) {
			/* Attempt initial configuration of the GIF if the
			 * submitted interface is empty or looks like a GIF
			 * interface. */
			$gif['gifif'] = $_POST['gifif'];
			$gif['gifif'] = interface_gif_configure($gif);
		} else {
			$input_errors[] = gettext("Invalid GIF interface.");
		}

		if (empty($gif['gifif']) ||
		    !preg_match("/^gif[0-9]+$/", $gif['gifif'])) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if ($this_gif_config) {
				config_set_path("gifs/gif/{$id}", $gif);
			} else {
				config_set_path('gifs/gif/', $gif);
			}

			write_config("GIF interface added");

			$confif = convert_real_interface_to_friendly_interface_name($gif['gifif']);

			if ($confif != "") {
				interface_configure($confif);
			}

			header("Location: interfaces_gif.php");
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

$pgtitle = array(gettext("Interfaces"), gettext("GIFs"), gettext("Edit"));
$pglinks = array("", "interfaces_gif.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('GIF Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the GIF tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'*GIF Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address where encapsulated gif packets will be sent.');

$section->addInput(new Form_IpAddress(
	'tunnel-local-addr',
	'*GIF tunnel local address',
	$pconfig['tunnel-local-addr']
))->setHelp('Local gif tunnel endpoint.');

$section->addInput(new Form_IpAddress(
	'tunnel-remote-addr',
	'*GIF tunnel remote address',
	$pconfig['tunnel-remote-addr']
))->setHelp('Remote GIF address endpoint.');

$section->addInput(new Form_Select(
	'tunnel-remote-net',
	'*GIF tunnel subnet',
	$pconfig['tunnel-remote-net'],
	array_combine(range(128, 1, -1), range(128, 1, -1))
))->setHelp('The subnet is used for determining the network that is tunnelled.');

$section->addInput(new Form_Checkbox(
	'link1',
	'ECN friendly behavior',
	'ECN friendly behavior violates RFC2893. This should be used in mutual agreement with the peer. ',
	$pconfig['link1']
));

$section->addInput(new Form_Checkbox(
	'link2',
	'Outer Source Filtering',
	'Disable automatic filtering of the outer GIF source which ensures a match with the configured remote peer. When disabled, martian and inbound filtering is not performed which allows asymmetric routing of the outer traffic.',
	$pconfig['link2']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->addGlobal(new Form_Input(
	'gifif',
	null,
	'hidden',
	$pconfig['gifif']
));

if ($this_gif_config) {
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
