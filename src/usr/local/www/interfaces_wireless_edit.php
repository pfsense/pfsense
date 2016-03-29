<?php
/*
	interfaces_wireless_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2010 Erik Fonnesbeck
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
##|*IDENT=page-interfaces-wireless-edit
##|*NAME=Interfaces: Wireless: Edit
##|*DESCR=Allow access to the 'Interfaces: Wireless: Edit' page.
##|*MATCH=interfaces_wireless_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['wireless'])) {
	$config['wireless'] = array();
}

if (!is_array($config['wireless']['clone'])) {
	$config['wireless']['clone'] = array();
}

$a_clones = &$config['wireless']['clone'];

function clone_inuse($num) {
	global $config, $a_clones;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_clones[$num]['cloneif']) {
			return true;
		}
	}

	return false;
}

function clone_compare($a, $b) {
	return strnatcmp($a['cloneif'], $b['cloneif']);
}

$portlist = get_interface_list();

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_clones[$id]) {
	$pconfig['if'] = $a_clones[$id]['if'];
	$pconfig['cloneif'] = $a_clones[$id]['cloneif'];
	$pconfig['mode'] = $a_clones[$id]['mode'];
	$pconfig['descr'] = $a_clones[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if mode");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("Mode"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		$clone = array();
		$clone['if'] = $_POST['if'];
		$clone['mode'] = $_POST['mode'];
		$clone['descr'] = $_POST['descr'];

		if (isset($id) && $a_clones[$id]) {
			if ($clone['if'] == $a_clones[$id]['if']) {
				$clone['cloneif'] = $a_clones[$id]['cloneif'];
			}
		}

		if (!$clone['cloneif']) {
			$clone_id = 1;
			do {
				$clone_exists = false;
				$clone['cloneif'] = "{$_POST['if']}_wlan{$clone_id}";
				foreach ($a_clones as $existing) {
					if ($clone['cloneif'] == $existing['cloneif']) {
						$clone_exists = true;
						$clone_id++;
						break;
					}
				}
			} while ($clone_exists);
		}

		if (isset($id) && $a_clones[$id]) {
			if (clone_inuse($id)) {
				if ($clone['if'] != $a_clones[$id]['if']) {
					$input_errors[] = gettext("This wireless clone cannot be modified because it is still assigned as an interface.");
				} else if ($clone['mode'] != $a_clones[$id]['mode']) {
					$input_errors[] = gettext("Use the configuration page for the assigned interface to change the mode.");
				}
			}
		}

		if (!$input_errors) {
			if (!interface_wireless_clone($clone['cloneif'], $clone)) {
				$input_errors[] = sprintf(gettext('Error creating interface with mode %1$s.	 The %2$s interface may not support creating more clones with the selected mode.'), $wlan_modes[$clone['mode']], $clone['if']);
			} else {
				if (isset($id) && $a_clones[$id]) {
					if ($clone['if'] != $a_clones[$id]['if']) {
						mwexec("/sbin/ifconfig " . $a_clones[$id]['cloneif'] . " destroy");
					}
					$input_errors[] = sprintf(gettext("Created with id %s"), $id);
					$a_clones[$id] = $clone;
				} else {
					$input_errors[] = gettext("Created without id");
					$a_clones[] = $clone;
				}

				usort($a_clones, "clone_compare");
				write_config();

				header("Location: interfaces_wireless.php");
				exit;
			}
		}
	}
}

function build_parent_list() {
	global $g;

	$parentlist = array();
	$portlist = get_interface_list();
	$count = 0;
	foreach ($portlist as $ifn => $ifinfo) {
		if (preg_match($g['wireless_regex'], $ifn)) {
			$parentlist[$ifn] = htmlspecialchars($ifn . ' (' . $ifinfo['mac'] . ')');
			$count++;
		}
	}

	if ($count > 0) {
		return($parentlist);
	} else {
		return(array('0' => gettext('None available')));
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Wireless"), gettext("Edit"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Wireless Interface Configuration');

$section->addInput(new Form_Select(
	'if',
	'Parent Interface',
	$pconfig['if'],
	build_parent_list()
));

$section->addInput(new Form_Select(
	'mode',
	'Mode',
	$pconfig['mode'],
	array(
		'bss' => gettext('Infrastructure (BSS)'),
		'adhoc' => gettext('Ad-hoc (IBSS)'),
		'hostap' => gettext('Access Point')
	)
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Input(
	'cloneif',
	null,
	'hidden',
	$pconfig['cloneif']
));

if (isset($id) && $a_clones[$id]) {
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
