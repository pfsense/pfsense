<?php
/*
	interfaces_gre_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
/*
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-gre-edit
##|*NAME=Interfaces: GRE: Edit page
##|*DESCR=Allow access to the 'Interfaces: GRE: Edit' page.
##|*MATCH=interfaces_gre_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['gres']['gre']))
	$config['gres']['gre'] = array();

$a_gres = &$config['gres']['gre'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_gres[$id]) {
	$pconfig['if'] = $a_gres[$id]['if'];
	$pconfig['greif'] = $a_gres[$id]['greif'];
	$pconfig['remote-addr'] = $a_gres[$id]['remote-addr'];
	$pconfig['tunnel-remote-net'] = $a_gres[$id]['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $a_gres[$id]['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $a_gres[$id]['tunnel-remote-addr'];
	$pconfig['link1'] = isset($a_gres[$id]['link1']);
	$pconfig['link2'] = isset($a_gres[$id]['link2']);
	$pconfig['link0'] = isset($a_gres[$id]['link0']);
	$pconfig['descr'] = $a_gres[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("Remote tunnel endpoint IP address"), gettext("Local tunnel IP address"), gettext("Remote tunnel IP address"), gettext("Remote tunnel network"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) ||
	    (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
	    (!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
	}

	if (!is_numericint($_POST['tunnel-remote-net'])) {
		$input_errors[] = gettext("The GRE tunnel subnet must be an integer.");
	}

	if (is_ipaddrv4($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv4($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The GRE Tunnel remote address must be IPv4 where tunnel local address is IPv4.");
		}
		if ($_POST['tunnel-remote-net'] > 32 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The GRE tunnel subnet must be an integer between 1 and 32.");
		}
	}

	if (is_ipaddrv6($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv6($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The GRE Tunnel remote address must be IPv6 where tunnel local address is IPv6.");
		}
		if ($_POST['tunnel-remote-net'] > 128 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The GRE tunnel subnet must be an integer between 1 and 128.");
		}
	}

	foreach ($a_gres as $gre) {
		if (isset($id) && ($a_gres[$id]) && ($a_gres[$id] === $gre)) {
			continue;
		}

		if (($gre['if'] == $_POST['if']) && ($gre['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A GRE tunnel with the network %s is already defined."), $gre['remote-network']);
			break;
		}
	}

	if (!$input_errors) {
		$gre = array();
		$gre['if'] = $_POST['if'];
		$gre['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gre['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gre['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gre['remote-addr'] = $_POST['remote-addr'];
		$gre['descr'] = $_POST['descr'];
		$gre['link1'] = isset($_POST['link1']);
		$gre['link2'] = isset($_POST['link2']);
		$gre['link0'] = isset($_POST['link0']);
		$gre['greif'] = $_POST['greif'];

		$gre['greif'] = interface_gre_configure($gre);
		if ($gre['greif'] == "" || !stristr($gre['greif'], "gre")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_gres[$id]) {
				$a_gres[$id] = $gre;
			} else {
				$a_gres[] = $gre;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($gre['greif']);

			if ($confif != "")
				interface_configure($confif);

			header("Location: interfaces_gre.php");
			exit;
		}
	}
}

function build_parent_list() {
	$parentlist = array();
	$portlist = get_possible_listen_ips();
	foreach ($portlist as $ifn => $ifinfo)
		$parentlist[$ifn] = $ifinfo;

	return($parentlist);
}

$pgtitle = array(gettext("Interfaces"),gettext("GRE"),gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$form = new Form();

$section = new Form_Section('GRE Configuration');

$section->addInput(new Form_Select(
	'if',
	'Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the GRE tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'GRE Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address where encapsulated GRE packets will be sent.');

$section->addInput(new Form_IpAddress(
	'tunnel-local-addr',
	'GRE tunnel local address',
	$pconfig['tunnel-local-addr']
))->setHelp('Local GRE tunnel endpoint.');

$section->addInput(new Form_IpAddress(
	'tunnel-remote-addr',
	'GRE tunnel remote address',
	$pconfig['tunnel-remote-addr']
))->setHelp('Remote GRE address endpoint.');

$section->addInput(new Form_Select(
	'tunnel-remote-net',
	'GRE tunnel subnet',
	$pconfig['tunnel-remote-net'],
	array_combine(range(128, 1, -1), range(128, 1, -1))
))->setHelp('The subnet is used for determining the network that is tunnelled');

$section->addInput(new Form_Checkbox(
	'link0',
	'Route Caching',
	'Specify if route caching can be enabled. (Be careful with these settings on dynamic networks.)',
	$pconfig['link0']
));

$section->addInput(new Form_Checkbox(
	'link1',
	'ECN friendly behavior',
	'ECN friendly behavior violates RFC2893. This should be used in mutual agreement with the peer. ',
	$pconfig['link1']
));

$section->addInput(new Form_Checkbox(
	'link2',
	'WCCP Version',
	'Check this box for WCCP encapsulation version 2, or leave unchecked for version 1.',
	$pconfig['link2']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_Input(
	'greif',
	null,
	'hidden',
	$pconfig['greif']
));

if (isset($id) && $a_gres[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
