<?php
/* $Id$ */
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2004, 2005 Scott Ullrich
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
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-vlan-edit
##|*NAME=Interfaces: VLAN: Edit page
##|*DESCR=Allow access to the 'Interfaces: VLAN: Edit' page.
##|*MATCH=interfaces_vlan_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['vlans']['vlan']))
	$config['vlans']['vlan'] = array();

$a_vlans = &$config['vlans']['vlan'];

$portlist = get_interface_list();

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		$portlist[$lagg['laggif']] = $lagg;
	}
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_vlans[$id]) {
	$pconfig['if'] = $a_vlans[$id]['if'];
	$pconfig['vlanif'] = $a_vlans[$id]['vlanif'];
	$pconfig['tag'] = $a_vlans[$id]['tag'];
	$pconfig['descr'] = $a_vlans[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if tag");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("VLAN tag"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (isset($_POST['tag']) && (!is_numericint($_POST['tag']) || ($_POST['tag'] < '1') || ($_POST['tag'] > '4094'))) {
		$input_errors[] = gettext("The VLAN tag must be an integer between 1 and 4094.");
	}

	if (!does_interface_exist($_POST['if'])) {
		$input_errors[] = gettext("Interface supplied as parent is invalid");
	}

	if (isset($id)) {
		if ($_POST['tag'] && $_POST['tag'] != $a_vlans[$id]['tag']) {
			if (!empty($a_vlans[$id]['vlanif']) && convert_real_interface_to_friendly_interface_name($a_vlans[$id]['vlanif']) != NULL) {
				$input_errors[] = gettext("Interface is assigned and you cannot change the VLAN tag while assigned.");
			}
		}
	}
	foreach ($a_vlans as $vlan) {
		if (isset($id) && ($a_vlans[$id]) && ($a_vlans[$id] === $vlan)) {
			continue;
		}

		if (($vlan['if'] == $_POST['if']) && ($vlan['tag'] == $_POST['tag'])) {
			$input_errors[] = sprintf(gettext("A VLAN with the tag %s is already defined on this interface."), $vlan['tag']);
			break;
		}
	}
	if (is_array($config['qinqs']['qinqentry'])) {
		foreach ($config['qinqs']['qinqentry'] as $qinq) {
			if ($qinq['tag'] == $_POST['tag'] && $qinq['if'] == $_POST['if']) {
				$input_errors[] = gettext("A QinQ VLAN exists with this tag please remove it to use this tag with.");
			}
		}
	}

	if (!$input_errors) {
		if (isset($id) && $a_vlans[$id]) {
			if (($a_vlans[$id]['if'] != $_POST['if']) || ($a_vlans[$id]['tag'] != $_POST['tag'])) {
				if (!empty($a_vlans[$id]['vlanif'])) {
					$confif = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
					// Destroy previous vlan
					pfSense_interface_destroy($a_vlans[$id]['vlanif']);
				} else {
					pfSense_interface_destroy("{$a_vlans[$id]['if']}_vlan{$a_vlans[$id]['tag']}");
					$confif = convert_real_interface_to_friendly_interface_name("{$a_vlans[$id]['if']}_vlan{$a_vlans[$id]['tag']}");
				}
				if ($confif != "")
					$config['interfaces'][$confif]['if'] = "{$_POST['if']}_vlan{$_POST['tag']}";
			}
		}
		$vlan = array();
		$vlan['if'] = $_POST['if'];
		$vlan['tag'] = $_POST['tag'];
		$vlan['descr'] = $_POST['descr'];
		$vlan['vlanif'] = "{$_POST['if']}_vlan{$_POST['tag']}";
		$vlan['vlanif'] = interface_vlan_configure($vlan);
		if ($vlan['vlanif'] == "" || !stristr($vlan['vlanif'], "vlan")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_vlans[$id]) {
				$a_vlans[$id] = $vlan;
			} else {
				$a_vlans[] = $vlan;
			}

			write_config();

			if ($confif != "")
				interface_configure($confif);
			header("Location: interfaces_vlan.php");
			exit;
		}
	}
}

function build_interfaces_list() {
	global $portlist;

	$list = array();

	foreach ($portlist as $ifn => $ifinfo) {
		if (is_jumbo_capable($ifn)) {
			$list[$ifn] = $ifn . " (" . $ifinfo['mac'] . ")";
		}
	}

	return($list);
}

$pgtitle = array(gettext("Interfaces"), gettext("VLAN"), gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

require_once('classes/Form.class.php');
$form = new Form;
$section = new Form_Section('Interface VLAN	Edit');

$section->addInput(new Form_Select(
	'if',
	'Parent Interface',
	$pconfig['if'],
	build_interfaces_list()
))->setWidth(6)->setHelp('Only VLAN capable interfaces will be shown.');

$section->addInput(new Form_Input(
	'tag',
	'VLAN Tag',
	'text',
	$pconfig['tag'],
	['placeholder' => '1']
))->setWidth(6)->setHelp(gettext('802.1Q VLAN tag (between 1 and 4094).'));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setWidth(6)->setHelp('You may enter a group description here '.
	'for your reference (not parsed).');

$form->addGlobal(new Form_Input(
	'vlanif',
	'vlanif',
	'hidden',
	$pconfig['vlanif']
));

if (isset($id) && $a_vlans[$id]) {
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

