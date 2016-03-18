<?php
/*
	interfaces_gif_edit.php
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

##|+PRIV
##|*IDENT=page-interfaces-gif-edit
##|*NAME=Interfaces: GIF: Edit
##|*DESCR=Allow access to the 'Interfaces: GIF: Edit' page.
##|*MATCH=interfaces_gif_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['gifs']['gif'])) {
	$config['gifs']['gif'] = array();
}

$a_gifs = &$config['gifs']['gif'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_gifs[$id]) {
	$pconfig['if'] = $a_gifs[$id]['if'];
	if (!empty($a_gifs[$id]['ipaddr'])) {
		$pconfig['if'] = $pconfig['if'] . '|' . $a_gifs[$id]['ipaddr'];
	}
	$pconfig['gifif'] = $a_gifs[$id]['gifif'];
	$pconfig['remote-addr'] = $a_gifs[$id]['remote-addr'];
	$pconfig['tunnel-remote-net'] = $a_gifs[$id]['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $a_gifs[$id]['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $a_gifs[$id]['tunnel-remote-addr'];
	$pconfig['link1'] = isset($a_gifs[$id]['link1']);
	$pconfig['link0'] = isset($a_gifs[$id]['link0']);
	$pconfig['descr'] = $a_gifs[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("gif remote address"), gettext("gif tunnel local address"), gettext("gif tunnel remote address"), gettext("gif tunnel remote netmask"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) ||
	    (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
	    (!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
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

	foreach ($a_gifs as $gif) {
		if (isset($id) && ($a_gifs[$id]) && ($a_gifs[$id] === $gif)) {
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
		if (isset($_POST['link0'])) {
			$gif['link0'] = '';
		}
		if (isset($_POST['link1'])) {
			$gif['link1'] = '';
		}
		$gif['gifif'] = $_POST['gifif'];
		$gif['gifif'] = interface_gif_configure($gif);

		if ($gif['gifif'] == "" || !stristr($gif['gifif'], "gif")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_gifs[$id]) {
				$a_gifs[$id] = $gif;
			} else {
				$a_gifs[] = $gif;
			}

			write_config();

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
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('GIF Configuration');

$section->addInput(new Form_Select(
	'if',
	'Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the GIF tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'GIF Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address where encapsulated gif packets will be sent.');

$section->addInput(new Form_IpAddress(
	'tunnel-local-addr',
	'GIF tunnel local address',
	$pconfig['tunnel-local-addr']
))->setHelp('Local gif tunnel endpoint.');

$section->addInput(new Form_IpAddress(
	'tunnel-remote-addr',
	'GIF tunnel remote address',
	$pconfig['tunnel-remote-addr']
))->setHelp('Remote GIF address endpoint.');

$section->addInput(new Form_Select(
	'tunnel-remote-net',
	'GIF tunnel subnet',
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

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_Input(
	'gifif',
	null,
	'hidden',
	$pconfig['gifif']
));

if (isset($id) && $a_gifs[$id]) {
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
