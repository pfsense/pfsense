<?php
/*
	services_dhcpv6_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2011 Seth Mos <seth.mos@dds.nl>
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
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

require("guiconfig.inc");

$if = $_GET['if'];
if ($_POST['if']) {
	$if = $_POST['if'];
}

if (!$if) {
	header("Location: services_dhcpv6.php");
	exit;
}

if (!is_array($config['dhcpdv6'])) {
	$config['dhcpdv6'] = array();
}
if (!is_array($config['dhcpdv6'][$if])) {
	$config['dhcpdv6'][$if] = array();
}
if (!is_array($config['dhcpdv6'][$if]['staticmap'])) {
	$config['dhcpdv6'][$if]['staticmap'] = array();
}

$netboot_enabled = isset($config['dhcpdv6'][$if]['netboot']);
$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
$ifcfgipv6 = get_interface_ipv6($if);
$ifcfgsnv6 = get_interface_subnetv6($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_maps[$id]) {
	$pconfig['duid'] = $a_maps[$id]['duid'];
	$pconfig['hostname'] = $a_maps[$id]['hostname'];
	$pconfig['ipaddrv6'] = $a_maps[$id]['ipaddrv6'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $a_maps[$id]['descr'];
} else {
	$pconfig['duid'] = $_GET['duid'];
	$pconfig['hostname'] = $_GET['hostname'];
	$pconfig['filename'] = $_GET['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $_GET['descr'];
}

if ($_POST) {

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
	if (($_POST['ipaddrv6'] && !is_ipaddrv6($_POST['ipaddrv6']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified.");
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
$shortcut_section = "dhcp6";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Static DHCPv6 Mapping');

$section->addInput(new Form_Input(
	'duid',
	'DUID',
	'text',
	$pconfig['duid'],
	['placeholder' => 'DUID-LLT - ETH -- TIME --- ---- address ---- xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx']
))->setHelp(gettext('Enter a DUID in the following format: ') . '<br />' .
			'DUID-LLT - ETH -- TIME --- ---- address ---- ' .
			'xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx');

$section->addInput(new Form_Input(
	'ipaddrv6',
	'IPv6 address',
	'text',
	$pconfig['ipaddrv6']
))->setHelp('If an IPv6 address is entered, the address must be outside of the pool.' . '<br />' .
			'If no IPv6 address is given, one will be dynamically allocated from the pool.');

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
))->setHelp('You may enter a description here for your reference (not parsed).');

if($netboot_enabled) {
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
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section->addInput(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

$form->add($section);
print($form);

include("foot.inc");
