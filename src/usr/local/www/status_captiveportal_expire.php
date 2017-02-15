<?php
/*
 * status_captiveportal_expire.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Marcel Wiget <mwiget@mac.com>
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
##|*IDENT=page-status-captiveportal-expire
##|*NAME=Status: Captive Portal: Expire Vouchers
##|*DESCR=Allow access to the 'Status: Captive Portal: Expire Vouchers' page.
##|*MATCH=status_captiveportal_expire.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");
require_once("voucher.inc");

$cpzone = strtolower($_REQUEST['zone']);

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}

$a_cp =& $config['captiveportal'];

/* If the zone does not exist, do not display the invalid zone */
if (!array_key_exists($cpzone, $a_cp)) {
	$cpzone = "";
}

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

$pgtitle = array(gettext("Status"), gettext("Captive Portal"), htmlspecialchars($a_cp[$cpzone]['zone']), gettext("Expire Vouchers"));
$pglinks = array("", "status_captiveportal.php", "status_captiveportal.php?zone=" . $cpzone, "@self");

include("head.inc");

if ($_POST['save']) {
	if ($_POST['vouchers']) {
		if (voucher_expire($_POST['vouchers'])) {
			print_info_box(gettext('Voucher(s) successfully marked.'), 'success', false);
		} else {
			print_info_box(gettext('Voucher(s) could not be processed.'), 'danger', false);
		}
	}
}

$tab_array = array();
$tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone=" . htmlspecialchars($cpzone));
$tab_array[] = array(gettext("Expire Vouchers"), true, "status_captiveportal_expire.php?zone=" . htmlspecialchars($cpzone));
display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section('Expire Vouchers');

$section->addInput(new Form_Textarea(
	'vouchers',
	'*Vouchers',
	$_POST['vouchers']
))->setHelp('Enter multiple vouchers separated by space or newline. All valid vouchers will be marked as expired.');

$section->addInput(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Expire',
	null,
	'fa-trash'
))->addClass('btn-warning');

print($form);

include("foot.inc");
