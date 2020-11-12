<?php
/*
 * services_captiveportal_ip_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004 Dinesh Nair <dinesh@alphaque.com>
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
##|*IDENT=page-services-captiveportal-editallowedips
##|*NAME=Services: Captive Portal: Edit Allowed IPs
##|*DESCR=Allow access to the 'Services: Captive Portal: Edit Allowed IPs' page.
##|*MATCH=services_captiveportal_ip_edit.php*
##|-PRIV

function allowedipscmp($a, $b) {
	return strcmp($a['ip'], $b['ip']);
}

function allowedips_sort() {
	global $g, $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['allowedip'], "allowedipscmp");
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

$cpzone = strtolower(htmlspecialchars($_REQUEST['zone']));

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

init_config_arr(array('captiveportal', $cpzone, 'allowedip'));
$a_cp = &$config['captiveportal'];
$a_allowedips = &$config['captiveportal'][$cpzone]['allowedip'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("Allowed IP Addresses"), gettext("Edit"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "services_captiveportal_ip.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";
$id = $_REQUEST['id'];

if (isset($id) && $a_allowedips[$id]) {
	$pconfig['ip'] = $a_allowedips[$id]['ip'];
	$pconfig['sn'] = $a_allowedips[$id]['sn'];
	$pconfig['dir'] = $a_allowedips[$id]['dir'];
	$pconfig['bw_up'] = $a_allowedips[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedips[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedips[$id]['descr'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ip sn");
	$reqdfieldsn = array(gettext("Allowed IP address"), gettext("Subnet mask"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['ip'] && !is_ipaddr($_POST['ip'])) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['ip']);
	}

	if ($_POST['sn'] && (!is_numeric($_POST['sn']) || ($_POST['sn'] < 1) || ($_POST['sn'] > 32))) {
		$input_errors[] = gettext("A valid subnet mask must be specified");
	}

	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}

	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}

	if ($_POST['bw_up'] && ($_POST['bw_up'] > 999999 || $_POST['bw_up'] < 1)) {
		$input_errors[] = gettext("Upload speed must be between 1 and 999999");
	}

	if ($_POST['bw_down'] && ($_POST['bw_down'] > 999999 || $_POST['bw_down'] < 1)) {
		$input_errors[] = gettext("Download speed must be between 1 and 999999");
	}

	foreach ($a_allowedips as $ipent) {
		if (isset($id) && ($a_allowedips[$id]) && ($a_allowedips[$id] === $ipent)) {
			continue;
		}

		if ($ipent['ip'] == $_POST['ip']) {
			$input_errors[] = sprintf(gettext('[%s] already allowed.'), $_POST['ip']);
			break ;
		}
	}

	if (!$input_errors) {
		$ip = array();
		$ip['ip'] = $_POST['ip'];
		$ip['sn'] = $_POST['sn'];
		$ip['descr'] = $_POST['descr'];
		$ip['dir'] = $_POST['dir'];

		if ($_POST['bw_up']) {
			$ip['bw_up'] = $_POST['bw_up'];
		}

		if ($_POST['bw_down']) {
			$ip['bw_down'] = $_POST['bw_down'];
		}

		if (isset($id) && $a_allowedips[$id]) {
			$oldip = $a_allowedips[$id]['ip'];
			if (!empty($a_allowedips[$id]['sn'])) {
				$oldmask = $a_allowedips[$id]['sn'];
			} else {
				$oldmask = 32;
			}

			$a_allowedips[$id] = $ip;
		} else {
			$a_allowedips[] = $ip;
		}

		allowedips_sort();

		write_config("Captive portal allowed IPs added");

		if (isset($a_cp[$cpzone]['enable']) && is_module_loaded("ipfw.ko")) {
			$rules = "";
			$cpzoneid = $a_cp[$cpzone]['zoneid'];

			unset($rule);
			if (isset($oldip) && isset($oldmask)) {
				$rule = pfSense_ipfw_table_lookup("{$cpzone}_allowed_up", "{$oldip}/{$oldmask}");

				$rules .= "table {$cpzone}_allowed_up delete {$oldip}/{$oldmask}\n";
				$rules .= "table {$cpzone}_allowed_down delete {$oldip}/{$oldmask}\n";

				if (is_array($rule) && !empty($rule['pipe'])) {
					$rules .= "pipe delete {$rule['pipe']}\n";
					$rules .= "pipe delete " . ($rule['pipe']+1 . "\n");
				}
			}

			$rules .= captiveportal_allowedip_configure_entry($ip);
			if (is_array($rule) && !empty($rule['pipe'])) {
				captiveportal_free_dn_ruleno($rule['pipe']);
			}

			$uniqid = uniqid("{$cpzone}_allowed");
			@file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/{$uniqid}_tmp");
			@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
		}

		header("Location: services_captiveportal_ip.php?zone={$cpzone}");
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

$section = new Form_Section('Edit Captive Portal IP Rule');

$section->addInput(new Form_IpAddress(
	'ip',
	'*IP Address',
	$pconfig['ip']
))->addMask('sn', $pconfig['sn'], 32);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp("Enter a description here for reference only. (Not parsed)");

$section->addInput(new Form_Select(
	'dir',
	'*Direction',
	strtolower($pconfig['dir']),
	build_dir_list()
))->setHelp('Use "From" to always allow access to an address through the captive portal (without authentication). ' .
			'Use "To" to allow access from all clients (even non-authenticated ones) behind the portal to this IP.');

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter an upload limit to be enforced on this address in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this address in Kbit/s');

$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if (isset($id) && $a_allowedips[$id]) {
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
