<?php
/*
 * services_captiveportal_zones_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-captiveportal-editzones
##|*NAME=Services: Captive Portal: Edit Zones
##|*DESCR=Allow access to the 'Services: Captive Portal: Edit Zones' page.
##|*MATCH=services_captiveportal_zones_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");
require_once("xmlparse.inc");

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), gettext("Add Zone"));
$pglinks = array("", "services_captiveportal_zones.php", "@self");
$shortcut_section = "captiveportal";

init_config_arr(array('captiveportal'));
$a_cp = &$config['captiveportal'];

if ($_POST['Submit']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "zone");
	$reqdfieldsn = array(gettext("Zone name"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match('/[^A-Za-z0-9_]/', $_POST['zone'])) {
		$input_errors[] = gettext("The zone name can only contain letters, digits, and underscores ( _ ).");
	}

	if (in_array(strtolower($_POST['zone']), array_keys(array_merge(listtags(), listtags_pkg()), true))) {
		$input_errors[] = sprintf(gettext("The zone name [%s] is reserved."), $_POST['zone']);
	}

	foreach ($a_cp as $cpkey => $cpent) {
		if ($cpent['zone'] == $_POST['zone']) {
			$input_errors[] = sprintf(gettext("Zone [%s] already exists."), $_POST['zone']);
			break;
		}
	}

	if (!$input_errors) {
		$cpzone = strtolower(htmlspecialchars($_POST['zone']));
		$a_cp[$cpzone] = array();
		$a_cp[$cpzone]['zone'] = str_replace(" ", "", $_POST['zone']);
		$a_cp[$cpzone]['descr'] = $_POST['descr'];
		$a_cp[$cpzone]['localauth_priv'] = true;
		write_config("Captive portal zone saved");

		header("Location: services_captiveportal.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);

$section = new Form_Section('Add Captive Portal Zone');

$section->addInput(new Form_Input(
	'zone',
	'*Zone name'
))->setPattern('^[A-Za-z_][0-9A-Za-z_]+')->setHelp('Zone name. Can only contain letters, digits, and underscores (_) and may not start with a digit.');

$section->addInput(new Form_Input(
	'descr',
	'Zone description'
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Save & Continue',
	null,
	'fa-save'
))->addClass('btn-primary');

print($form);

include("foot.inc");
