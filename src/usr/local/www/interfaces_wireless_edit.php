<?php
/*
 * interfaces_wireless_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Erik Fonnesbeck
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
##|*IDENT=page-interfaces-wireless-edit
##|*NAME=Interfaces: Wireless: Edit
##|*DESCR=Allow access to the 'Interfaces: Wireless: Edit' page.
##|*MATCH=interfaces_wireless_edit.php*
##|-PRIV

require_once("guiconfig.inc");

config_init_path('wireless/clone');

function clone_inuse($num) {
	$a_clones = config_get_path('wireless/clone');
	$iflist = get_configured_interface_list(true);
	$if_config = config_get_path('interfaces');
	foreach ($iflist as $if) {
		if ($if_config[$if]['if'] == $a_clones[$num]['cloneif']) {
			return true;
		}
	}

	return false;
}

function clone_compare($a, $b) {
	return strnatcmp($a['cloneif'], $b['cloneif']);
}

$portlist = get_interface_list();

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$this_clone_config = isset($id) ? config_get_path("wireless/clone/{$id}") : null;
if ($this_clone_config) {
	$pconfig['if'] = $this_clone_config['if'];
	$pconfig['cloneif'] = $this_clone_config['cloneif'];
	$pconfig['mode'] = $this_clone_config['mode'];
	$pconfig['descr'] = $this_clone_config['descr'];
}

if ($_POST['save']) {
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

		if ($this_clone_config) {
			if ($clone['if'] == $this_clone_config['if']) {
				$clone['cloneif'] = $this_clone_config['cloneif'];
			}
		}

		if (!$clone['cloneif']) {
			$clone_id = 0;
			do {
				$clone_exists = false;
				$clone['cloneif'] = "{$_POST['if']}_wlan{$clone_id}";
				foreach (config_get_path('wireless/clone', []) as $existing) {
					if ($clone['cloneif'] == $existing['cloneif']) {
						$clone_exists = true;
						$clone_id++;
						break;
					}
				}
			} while ($clone_exists);
		}

		if ($this_clone_config) {
			if (clone_inuse($id)) {
				if ($clone['if'] != $this_clone_config['if']) {
					$input_errors[] = gettext("This wireless clone cannot be modified because it is still assigned as an interface.");
				} else if ($clone['mode'] != $this_clone_config['mode']) {
					$input_errors[] = gettext("Use the configuration page for the assigned interface to change the mode.");
				}
			}
		}

		if (!$input_errors) {
			if (!interface_wireless_clone($clone['cloneif'], $clone)) {
				$input_errors[] = sprintf(gettext('Error creating interface with mode %1$s.	 The %2$s interface may not support creating more clones with the selected mode.'), $wlan_modes[$clone['mode']], $clone['if']);
			} else {
				if ($this_clone_config) {
					if ($clone['if'] != $this_clone_config['if']) {
						pfSense_interface_destroy($this_clone_config['cloneif']);
					}
					$input_errors[] = sprintf(gettext("Created with id %s"), $id);
					config_set_path("wireless/clone/{$id}", $clone);
				} else {
					$input_errors[] = gettext("Created without id");
					config_set_path('wireless/clone/', $clone);
				}

				$a_clones = config_get_path('wireless/clone');
				usort($a_clones, "clone_compare");
				config_set_path('wireless/clone', $a_clones);
				write_config("Wireless interface added");

				header("Location: interfaces_wireless.php");
				exit;
			}
		}
	}
}

function build_parent_list() {
	global $g;

	$parentlist = array();
	$portlist = interface_list_wireless();
	$count = 0;
	foreach ($portlist as $ifn) {
		$parentlist[$ifn['if']] = htmlspecialchars($ifn['descr']);
		$count++;
	}

	if ($count > 0) {
		return($parentlist);
	} else {
		return(array('0' => gettext('None available')));
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Wireless"), gettext("Edit"));
$pglinks = array("", "interfaces_wireless.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Wireless Interface Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
));

$section->addInput(new Form_Select(
	'mode',
	'*Mode',
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

$form->addGlobal(new Form_Input(
	'cloneif',
	null,
	'hidden',
	$pconfig['cloneif']
));

if ($this_clone_config) {
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
