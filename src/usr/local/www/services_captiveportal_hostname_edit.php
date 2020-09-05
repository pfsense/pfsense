<?php
/*
 * services_captiveportal_hostname_edit.php
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
##|*IDENT=page-services-captiveportal-editallowedhostnames
##|*NAME=Services: Captive Portal: Edit Allowed Hostnames
##|*DESCR=Allow access to the 'Services: Captive Portal: Edit Allowed Hostnames' page.
##|*MATCH=services_captiveportal_hostname_edit.php*
##|-PRIV

function allowedhostnamescmp($a, $b) {
	return strcmp(idn_to_utf8($a['hostname']), idn_to_utf8($b['hostname']));
}

function allowedhostnames_sort() {
	global $g, $config, $cpzone;
	usort($config['captiveportal'][$cpzone]['allowedhostname'], "allowedhostnamescmp");
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

global $cpzone, $cpzoneid;

$cpzone = $_REQUEST['zone'];

$cpzone = strtolower(htmlspecialchars($cpzone));

$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal', $cpzone, 'allowedhostname'));
$a_cp = &$config['captiveportal'];
$a_allowedhostnames = &$a_cp[$cpzone]['allowedhostname'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Allowed Hostnames"), gettext("Edit"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "services_captiveportal_hostname.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";

$id = $_REQUEST['id'];

if (isset($id) && $a_allowedhostnames[$id]) {
	$pconfig['zone'] = $a_allowedhostnames[$id]['zone'];
	$pconfig['hostname'] = idn_to_utf8($a_allowedhostnames[$id]['hostname']);
	$pconfig['sn'] = $a_allowedhostnames[$id]['sn'];
	$pconfig['dir'] = $a_allowedhostnames[$id]['dir'];
	$pconfig['bw_up'] = $a_allowedhostnames[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedhostnames[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedhostnames[$id]['descr'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;
	$_POST['hostname'] = idn_to_ascii($_POST['hostname']);

	/* input validation */
	$reqdfields = explode(" ", "hostname");
	$reqdfieldsn = array(gettext("Allowed Hostname"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['hostname'] && !is_hostname($_POST['hostname']))) {
		$input_errors[] = sprintf(gettext("A valid Hostname must be specified. [%s]"), idn_to_utf8($_POST['hostname']));
	}

	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}

	foreach ($a_allowedhostnames as $ipent) {
		if (isset($id) && ($a_allowedhostnames[$id]) && ($a_allowedhostnames[$id] === $ipent)) {
			continue;
		}

		if ($ipent['hostname'] == $_POST['hostname']) {
			$input_errors[] = sprintf(gettext("Hostname [%s] already allowed."), idn_to_utf8($_POST['hostname'])) ;
			break ;
		}
	}

	if (!$input_errors) {
		$ip = array();
		$ip['hostname'] = $_POST['hostname'];
		$ip['sn'] = $_POST['sn'];
		$ip['dir'] = $_POST['dir'];
		$ip['descr'] = $_POST['descr'];
		if ($_POST['bw_up']) {
			$ip['bw_up'] = $_POST['bw_up'];
		}
		if ($_POST['bw_down']) {
			$ip['bw_down'] = $_POST['bw_down'];
		}
		if (isset($id) && $a_allowedhostnames[$id]) {
			$a_allowedhostnames[$id] = $ip;
		} else {
			$a_allowedhostnames[] = $ip;
		}

		allowedhostnames_sort();

		write_config();

		$rules = captiveportal_allowedhostname_configure();
		@file_put_contents("{$g['tmp_path']}/hostname_rules", $rules);
		mwexec("/sbin/ipfw {$g['tmp_path']}/hostname_rules", true);
		unset($rules);

		header("Location: services_captiveportal_hostname.php?zone={$cpzone}");
		exit;
	}
}

function build_dir_list() {
	$dirs = array(gettext("Both"), gettext("From"), gettext("To"));
	$dirlist = array();

	foreach ($dirs as $dir) {
		$dirlist[strtolower($dir)] = $dir;
	}

	return($dirlist);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Captive Portal Hostname Settings');

$section->addInput(new Form_Select(
	'dir',
	'*Direction',
	strtolower($pconfig['dir']),
	build_dir_list()
))->setHelp('Use "From" to always allow a Hostname through the captive portal (without authentication). ' .
			'Use "To" to allow access from all clients (even non-authenticated ones) behind the portal to this Hostname.');

$section->addInput(new Form_Input(
	'hostname',
	'*Hostname',
	'text',
	$pconfig['hostname']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter a upload limit to be enforced on this Hostname in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this Hostname in Kbit/s');

$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if (isset($id) && $a_allowedhostnames[$id]) {
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
