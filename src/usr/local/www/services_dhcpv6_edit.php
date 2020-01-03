<?php
/*
 * services_dhcpv6_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-services-dhcpserverv6-editstaticmapping
##|*NAME=Services: DHCPv6 Server: Edit static mapping
##|*DESCR=Allow access to the 'Services: DHCPv6 Server : Edit static mapping' page.
##|*MATCH=services_dhcpv6_edit.php*
##|-PRIV

function staticmapcmp($a, $b) {
	return ipcmp($a['ipaddrv6'], $b['ipaddrv6']);
}

function staticmaps_sort($ifgui) {
	global $g, $config;

	usort($config['dhcpdv6'][$ifgui]['staticmap'], "staticmapcmp");
}

require_once('globals.inc');

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

require_once("guiconfig.inc");

$if = $_REQUEST['if'];

if (!$if) {
	header("Location: services_dhcpv6.php");
	exit;
}

init_config_arr(array('dhcpdv6', $if, 'staticmap'));
$netboot_enabled = isset($config['dhcpdv6'][$if]['netboot']);
$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
$ifcfgipv6 = get_interface_ipv6($if);
$ifcfgsnv6 = get_interface_subnetv6($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

$id = $_REQUEST['id'];

if (isset($id) && $a_maps[$id]) {
	$pconfig['duid'] = $a_maps[$id]['duid'];
	$pconfig['hostname'] = $a_maps[$id]['hostname'];
	$pconfig['ipaddrv6'] = $a_maps[$id]['ipaddrv6'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $a_maps[$id]['descr'];
} else {
	$pconfig['duid'] = $_REQUEST['duid'];
	$pconfig['hostname'] = $_REQUEST['hostname'];
	$pconfig['filename'] = $_REQUEST['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $_REQUEST['descr'];
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "duid");
	$reqdfieldsn = array(gettext("DUID"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['hostname']) {
		preg_match("/\-\$/", $_POST['hostname'], $matches);
		if ($matches) {
			$input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");
		}
		if (!is_hostname($_POST['hostname'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['hostname'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}

	if ($_POST['ipaddrv6']) {
		if (!is_ipaddrv6($_POST['ipaddrv6'])) {
			$input_errors[] = gettext("A valid IPv6 address must be specified.");
		} elseif ($config['interfaces'][$if]['ipaddrv6'] == 'track6') {
			$trackifname = $config['interfaces'][$if]['track6-interface'];
			$trackcfg = $config['interfaces'][$trackifname];
			$pdlen = 64 - $trackcfg['dhcp6-ia-pd-len'];
			if (!Net_IPv6::isInNetmask($_POST['ipaddrv6'], '::', $pdlen)) {
				$input_errors[] = sprintf(gettext(
				    'The prefix (upper %1$s bits) must be zero.  Use the form %2$s'),
				    $pdlen, dhcpv6_pd_str_help($ifcfgsnv6));
			}
		}
	}

	if (empty($_POST['duid'])) {
		$input_errors[] = gettext("A valid DUID must be specified.");
	}

	/* check for overlaps */
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent)) {
			continue;
		}

		if ((($mapent['hostname'] == $_POST['hostname']) && $mapent['hostname']) || ($mapent['duid'] == $_POST['duid'])) {
			$input_errors[] = gettext("This Hostname, IP or DUID already exists.");
			break;
		}
	}

	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddrv6']) {
		/* oh boy, we need to be able to somehow do this at some point. skip */
	}

	if (!$input_errors) {
		$mapent = array();
		$mapent['duid'] = $_POST['duid'];
		$mapent['ipaddrv6'] = $_POST['ipaddrv6'];
		$mapent['hostname'] = $_POST['hostname'];
		$mapent['descr'] = $_POST['descr'];
		$mapent['filename'] = $_POST['filename'];
		$mapent['rootpath'] = $_POST['rootpath'];

		if (isset($id) && $a_maps[$id]) {
			$a_maps[$id] = $mapent;
		} else {
			$a_maps[] = $mapent;
		}
		staticmaps_sort($if);

		write_config();

		if (isset($config['dhcpdv6'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic'])) {
				mark_subsystem_dirty('hosts');
			}
			if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
				mark_subsystem_dirty('unbound');
			}

		}

		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

$iflist = get_configured_interface_with_descr();
$ifname = '';

if (!empty($if) && isset($iflist[$if])) {
	$ifname = $iflist[$if];
}
$pgtitle = array(gettext("Services"), htmlspecialchars(gettext("DHCPv6 Server & RA")), $ifname, gettext("DHCPv6 Server"), gettext("Edit Static Mapping"));
$pglinks = array("", "services_dhcpv6.php", "services_dhcpv6.php?if={$if}", "services_dhcpv6.php?if={$if}", "@self");
$shortcut_section = "dhcp6";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Static DHCPv6 Mapping');

$section->addInput(new Form_Input(
	'duid',
	'*DUID',
	'text',
	$pconfig['duid'],
	['placeholder' => 'DUID-LLT - ETH -- TIME --- ---- address ---- xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx']
))->setHelp('Enter a DUID in the following format: %1$s %2$s', '<br />',
			'DUID-LLT - ETH -- TIME --- ---- address ---- ' .
			'xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx');

$section->addInput(new Form_Input(
	'ipaddrv6',
	'IPv6 address',
	'text',
	$pconfig['ipaddrv6']
))->setHelp('If an IPv6 address is entered, the address must be outside of the pool.%1$s' .
			'If no IPv6 address is given, one will be dynamically allocated from the pool.', '<br />');

$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$pconfig['hostname']
))->setHelp('Name of the host, without domain part.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if ($netboot_enabled) {
	$section->addInput(new Form_Input(
		'filename',
		'Netboot filename',
		'text',
		$pconfig['filename']
	))->setHelp('Name of the file that should be loaded when this host boots off of the network, overrides setting on main page.');

	$section->addInput(new Form_Input(
		'rootpath',
		'Root path',
		'text',
		$pconfig['rootpath']
	))->setHelp('Enter the root-path string. This overrides setting on main page.');
}

if (isset($id) && $a_maps[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

$form->add($section);
print($form);

include("foot.inc");
