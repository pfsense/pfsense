<?php
/*
 * status_captiveportal_expire.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
 * Copyright (c) 2007 Marcel Wiget <mwiget@mac.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

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

include("head.inc");

if ($_POST) {
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
	'Vouchers',
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
