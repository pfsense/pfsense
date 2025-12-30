<?php
/*
 * services_dhcpv6_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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

$dnsregpolicy_values = [
	'default' => gettext('Track subnet'),
	'enable' => gettext('Enable'),
	'disable' => gettext('Disable')
];

function staticmapcmp($a, $b) {
	return ipcmp($a['ipaddrv6'], $b['ipaddrv6']);
}

function staticmaps_sort($ifgui) {
	global $g;

	$dhcpd6_config = config_get_path("dhcpdv6/{$ifgui}/staticmap");
	usort($dhcpd6_config, "staticmapcmp");
	config_set_path("dhcpdv6/{$ifgui}/staticmap", $dhcpd6_config);
}

require_once('globals.inc');

if (!g_get('services_dhcp_server_enable')) {
	header("Location: /");
	exit;
}

require_once("guiconfig.inc");

$if = $_REQUEST['if'];

if (!$if) {
	header("Location: services_dhcpv6.php");
	exit;
}

$netboot_enabled = config_path_enabled("dhcpdv6/{$if}", 'netboot');
$ifcfgipv6 = get_interface_ipv6($if);
$ifcfgsnv6 = get_interface_subnetv6($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_map_config = isset($id) ? config_get_path("dhcpdv6/{$if}/staticmap/{$id}") : null;
if ($this_map_config) {
	$pconfig['duid'] = $this_map_config['duid'];
	$pconfig['hostname'] = $this_map_config['hostname'];
	$pconfig['ipaddrv6'] = $this_map_config['ipaddrv6'];
	$pconfig['filename'] = $this_map_config['filename'];
	$pconfig['rootpath'] = $this_map_config['rootpath'];
	$pconfig['descr'] = $this_map_config['descr'];
	if (dhcp_is_backend('kea')) {
		$pconfig['earlydnsregpolicy'] = $this_map_config['earlydnsregpolicy'];
		$pconfig['pdprefix'] = $this_map_config['pdprefix'];
		$pconfig['custom_kea_config'] = base64_decode($this_map_config['custom_kea_config']);
	}
} else {
	$pconfig['duid'] = $_REQUEST['duid'];
	$pconfig['hostname'] = $_REQUEST['hostname'];
	$pconfig['filename'] = $_REQUEST['filename'];
	$pconfig['rootpath'] = $_REQUEST['rootpath'];
	$pconfig['descr'] = $_REQUEST['descr'];
	if (dhcp_is_backend('kea')) {
		$pconfig['earlydnsregpolicy'] = $_REQUEST['earlydnsregpolicy'];
		$pconfig['pdprefix'] = $_REQUEST['pdprefix'];
		$pconfig['custom_kea_config'] = $_REQUEST['custom_kea_config'];
	}
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

	if ($_POST['earlydnsregpolicy'] && !array_key_exists($_POST['earlydnsregpolicy'], $dnsregpolicy_values)) {
		$input_errors[] = gettext("Invalid Early DNS Registration Policy.");
	}

	if ($_POST['ipaddrv6']) {
		if (!is_ipaddrv6($_POST['ipaddrv6'])) {
			$input_errors[] = gettext("A valid IPv6 address must be specified.");
		} elseif (config_get_path("dhcpdv6/{$if}/ipaddrv6") == 'track6') {
			$trackifname = config_get_path("interfaces/{$if}/track6-interface");
			$trackcfg = config_get_path("interfaces/{$trackifname}");
			$pdlen = 64 - $trackcfg['dhcp6-ia-pd-len'];
			if (!Net_IPv6::isInNetmask($_POST['ipaddrv6'], '::', $pdlen)) {
				$input_errors[] = sprintf(gettext(
				    'The prefix (upper %1$s bits) must be zero.  Use the form %2$s'),
				    $pdlen, dhcpv6_pd_str_help($ifcfgsnv6));
			}
		}
	}

	if (dhcp_is_backend('kea')) {
		if ($_POST['pdprefix']) {
			$parts = explode('/', trim($_POST['pdprefix']));
			if (!is_ipaddrv6($parts[0]) || !is_numeric($parts[1])) {
				$input_errors[] = gettext('A valid delegated prefix must be specified.');
			}
		}
	}

	if (empty($_POST['duid'])) {
		$input_errors[] = gettext("A valid DUID must be specified.");
	}

	/* check for overlaps */
	foreach (config_get_path("dhcpdv6/{$if}/staticmap", []) as $mapent) {
		if ($this_map_config && ($this_map_config === $mapent)) {
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

	/* validate custom config */
	if (dhcp_is_backend('kea')) {
		if (!empty($_POST['custom_kea_config'])) {
			$json = json_decode($_POST['custom_kea_config'], true);
			if (!is_array($json) || (json_last_error() !== JSON_ERROR_NONE)) {
				$input_errors[] = gettext('Custom configuration is not a well formed JSON object.');
			}
		}
	}

	if (!$input_errors) {
		$mapent = array();
		$mapent['duid'] = str_replace("-", ":", $_POST['duid']);
		$mapent['ipaddrv6'] = $_POST['ipaddrv6'];
		$mapent['hostname'] = $_POST['hostname'];
		$mapent['descr'] = $_POST['descr'];
		$mapent['earlydnsregpolicy'] = $_POST['earlydnsregpolicy'];
		$mapent['filename'] = $_POST['filename'];
		$mapent['rootpath'] = $_POST['rootpath'];

		if (dhcp_is_backend('kea')) {
			if (!empty($_POST['pdprefix'])) {
				$mapent['pdprefix'] = text_to_compressed_ip6($_POST['pdprefix']);
			}
			$mapent['custom_kea_config'] = base64_encode($_POST['custom_kea_config']);
		}

		if ($this_map_config) {
			config_set_path("dhcpdv6/{$if}/staticmap/{$id}", $mapent);
		} else {
			config_set_path("dhcpdv6/{$if}/staticmap/", $mapent);
		}
		staticmaps_sort($if);

		write_config("DHCPv6 server static maps saved");

		if (config_path_enabled("dhcpdv6/{$if}")) {
			mark_subsystem_dirty('dhcpd6');
			if (config_path_enabled('dnsmasq') && config_path_enabled('dnsmasq', 'regdhcpstatic')) {
				mark_subsystem_dirty('hosts');
			}
			if (config_path_enabled('unbound') && config_path_enabled('unbound', 'regdhcpstatic')) {
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
$pgtitle = [gettext('Services'), gettext('DHCPv6 Server'), $ifname, gettext('Static Mapping'), gettext('Edit')];
$pglinks = [null, 'services_dhcpv6.php', "services_dhcpv6.php?if={$if}", "services_dhcpv6.php?if={$if}", '@self'];
$shortcut_section = 'dhcp6';
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp6';
}

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

$valid_ra = in_array(config_get_path('dhcpdv6/'.$if.'/ramode', 'disabled'), ['managed', 'assist', 'stateless_dhcp']);
if (config_path_enabled('dhcpdv6/'.$if) && !$valid_ra) {
	print_info_box(sprintf(gettext('DHCPv6 is enabled but not being advertised to clients on %1$s. Router Advertisement must be enabled and Router Mode set to "Managed", "Assisted" or "Stateless DHCP."'), $iflist[$if]), 'danger', false);
}

display_isc_warning();

$form = new Form();

$section = new Form_Section(sprintf(gettext('Static DHCPv6 Mapping on %s'), $ifname));

$section->addInput(new Form_StaticText(
	gettext('DHCP Backend'),
	match (dhcp_get_backend()) {
		'isc' => gettext('ISC DHCP'),
		'kea' => gettext('Kea DHCP'),
		default => gettext('Unknown')
	}
));

$section->addInput(new Form_Input(
	'duid',
	'*'.gettext('DHCP Unique Identifier'),
	'text',
	$pconfig['duid'],
	['placeholder' => 'xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx']
))->setHelp('DHCP Unique Identifier (DUID) of a client to match.%1$s%1$s' .
		'Enter a DUID in the following format: %1$s' .
		'DUID-LLT - ETH -- TIME --- ---- address ----%1$s' .
		'xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx%1$s' .
		'xx-xx-xx-xx-xx-xx-xx-xx-xx-xx-xx-xx-xx-xx', '<br />');

$section->addInput(new Form_Input(
	'ipaddrv6',
	gettext('IPv6 Address'),
	'text',
	$pconfig['ipaddrv6']
))->setHelp('IPv6 address to assign this client.%1$s%1$s' .
		'Address must be outside of the pool. ' .
		'If no IPv6 address is given, one will be dynamically allocated from the address pool.', '<br />');

if (dhcp_is_backend('kea')):
$section->addInput(new Form_IpAddress(
	'pdprefix',
	gettext('Delegated Prefix'),
	$pconfig['pdprefix'],
	'V6'
))->addClass('trim')
  ->setHelp('Delegated prefix to assign this client.%1$s%1$s' .
		'If no prefix is given, one will be dynamically allocated from the prefix delegation pool.', '<br />');
endif;

$section->addInput(new Form_Input(
	'hostname',
	gettext('Hostname'),
	'text',
	$pconfig['hostname']
))->setHelp(gettext('Name of the client host without the domain part.'));

$section->addInput(new Form_Input(
	'descr',
	gettext('Description'),
	'text',
	$pconfig['descr']
))->setHelp(gettext('A description for administrative reference (not parsed).'));

$section->addInput(new Form_Select(
	'earlydnsregpolicy',
	gettext('Early DNS Registration'),
	array_get_path($pconfig, 'earlydnsregpolicy', 'default'),
	$dnsregpolicy_values
))->setHelp(gettext('Optionally overides the subnet early DNS registration policy to force a specific policy.'));

if (dhcp_is_backend('isc')):
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
endif;

$form->add($section);

if (dhcp_is_backend('kea')):
$section = new Form_Section(gettext('Custom Configuration'));
$section->addInput(new Form_Textarea(
	'custom_kea_config',
	gettext('JSON Configuration'),
	array_get_path($pconfig, 'custom_kea_config')
))->setWidth(8)->setHelp(gettext('JSON to be merged into the "%1$s" section of the generated Kea DHCPv6 configuration.%2$sThe input must be a well formed JSON object and should not include the "%1$s" key itself.'), 'reservation', '<br/>');
$form->add($section);
endif;

if ($this_map_config) {
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

print($form);

include("foot.inc");
