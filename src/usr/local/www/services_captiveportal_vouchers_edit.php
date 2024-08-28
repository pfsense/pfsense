<?php
/*
 * services_captiveportal_vouchers_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-captiveportal-voucher-edit
##|*NAME=Services: Captive Portal Voucher Rolls
##|*DESCR=Allow access to the 'Services: Captive Portal Edit Voucher Rolls' page.
##|*MATCH=services_captiveportal_vouchers_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");
require_once("voucher.inc");

$cpzone = strtolower(htmlspecialchars($_REQUEST['zone']));

if (empty($cpzone) || empty(config_get_path("captiveportal/{$cpzone}"))) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

config_init_path('captiveportal');
config_init_path("voucher/{$cpzone}/roll");

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), config_get_path("captiveportal/{$cpzone}/zone"), gettext("Vouchers"), gettext("Edit"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "services_captiveportal_vouchers.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal-vouchers";

$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_roll_config = isset($id) ? config_get_path("voucher/{$cpzone}/roll/{$id}") : null;
if ($this_roll_config) {
	$pconfig['zone'] = $this_roll_config['zone'];
	$pconfig['number'] = $this_roll_config['number'];
	$pconfig['count'] = $this_roll_config['count'];
	$pconfig['minutes'] = $this_roll_config['minutes'];
	$pconfig['descr'] = $this_roll_config['descr'];
}

$maxnumber = (1<<config_get_path("voucher/{$cpzone}/rollbits")) -1;	// Highest Roll#
$maxcount = (1<<config_get_path("voucher/{$cpzone}/ticketbits")) -1;	 // Highest Ticket#

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "number count minutes");
	$reqdfieldsn = array(gettext("Roll #"), gettext("Count"), gettext("Minutes per ticket"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	// Look for duplicate roll #
	foreach (config_get_path("voucher/{$cpzone}/roll", []) as $re) {
		if ($this_roll_config && $this_roll_config === $re) {
			continue;
		}
		if ($re['number'] == $_POST['number']) {
			$input_errors[] = sprintf(gettext("Roll number %s already exists."), $_POST['number']);
			break;
		}
	}

	if (!is_numeric($_POST['number']) || $_POST['number'] >= $maxnumber) {
		$input_errors[] = sprintf(gettext("Roll number must be numeric and less than %s"), $maxnumber);
	}

	if (!is_numeric($_POST['count']) || $_POST['count'] < 1 || $_POST['count'] > $maxcount) {
		$input_errors[] = sprintf(gettext("A roll has at least one voucher and less than %s."), $maxcount);
	}

	if (!is_numeric($_POST['minutes']) || $_POST['minutes'] < 1) {
		$input_errors[] = gettext("Each voucher must be good for at least 1 minute.");
	}

	if (!$input_errors) {

		if ($this_roll_config) {
			$rollent = $this_roll_config;
		}

		$rollent['zone'] = $_POST['zone'];
		$rollent['number'] = intval($_POST['number']);
		$rollent['minutes'] = intval($_POST['minutes']);
		$rollent['descr'] = $_POST['descr'];

		/* New Roll or modified voucher count: create bitmask */
		$voucherlck = lock("voucher{$cpzone}");

		if ($_POST['count'] != $rollent['count']) {
			$rollent['count'] = intval($_POST['count']);
			$len = ($rollent['count']>>3) + 1;	 // count / 8 +1
			$rollent['used'] = base64_encode(str_repeat("\000", $len)); // 4 bitmask
			$rollent['active'] = array();
			voucher_write_used_db($rollent['number'], $rollent['used']);
			voucher_write_active_db($rollent['number'], array());	// create empty DB
			voucher_log(LOG_INFO, sprintf(gettext('All %1$s vouchers from Roll %2$s marked unused'), $rollent['count'], $rollent['number']));

			if (captiveportal_xmlrpc_sync_get_details($syncip, $syncport,
				$syncuser, $syncpass, $carp_loop)) {
				$rpc_client = new pfsense_xmlrpc_client();
				$rpc_client->setConnectionData($syncip, $syncport, $syncuser, $syncpass);
				$rpc_client->set_noticefile("CaptivePortalVouchersSync");
				$arguments = array('active_and_used_vouchers_bitmasks' => array($rollent['number'] => base64_decode($rollent['used'])),
				'active_vouchers' => array($rollent['number'] => array()));

				$rpc_client->xmlrpc_method('captive_portal_sync', array('op' => 'write_vouchers', 'zone' => $cpzone, 'arguments' => base64_encode(serialize($arguments))));
			}
		} else {
			// existing roll has been modified but without changing the count
			// read active and used DB from ramdisk and store it in XML config
			$rollent['used'] = base64_encode(voucher_read_used_db($rollent['number']));
			$activent = array();
			$db = array();
			$active_vouchers = voucher_read_active_db($rollent['number']);
			foreach ($active_vouchers as $voucher => $line) {
				list($timestamp, $minutes) = explode(",", $line);
				$activent['voucher'] = $voucher;
				$activent['timestamp'] = $timestamp;
				$activent['minutes'] = $minutes;
				$db[] = $activent;
			}
			$rollent['active'] = $db;
		}
		/* Flag this entry to be sync'd */
		$rollent['lastsync'] = time();

		unlock($voucherlck);

		if ($this_roll_config) {
			config_set_path("voucher/{$cpzone}/roll/{$id}", $rollent);
		} else {
			config_set_path("voucher/{$cpzone}/roll/", $rollent);
		}

		write_config("Captive portal vouchers edited");

		header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Voucher Rolls');

$section->addInput(new Form_Input(
	'number',
	'*Roll #',
	'text',
	$pconfig['number']
))->setHelp('Enter the Roll# (0..%d) found on top of the generated/printed vouchers', $maxnumber);

$section->addInput(new Form_Input(
	'minutes',
	'*Minutes per ticket',
	'text',
	$pconfig['minutes']
))->setHelp('Defines the time in minutes that a user is allowed access. The clock starts ticking the first time a voucher is used for authentication.');

$section->addInput(new Form_Input(
	'count',
	'*Count',
	'text',
	$pconfig['count']
))->setHelp('Enter the number of vouchers (1..%d) found on top of the generated/printed vouchers. WARNING: Changing this number for an existing Roll will mark all vouchers as unused again', $maxcount);

$section->addInput(new Form_Input(
	'descr',
	'Comment',
	'text',
	$pconfig['descr']
))->setHelp('Can be used to further identify this roll. Ignored by the system.');

$form->addGlobal(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if ($this_roll_config) {
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
