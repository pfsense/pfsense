<?php
/*
 * autoconfigbackup_backup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2015 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-acb-backup
##|*NAME=Services: Auto Config Backup: Backup Now
##|*DESCR=Create a new auto config backup entry.
##|*MATCH=services_acb_backup.php*
##|-PRIV

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("acb.inc");

if ($_POST) {

	if ($_REQUEST['nooverwrite']) {
		touch("/tmp/acb_nooverwrite");
	}

	touch("/tmp/forceacb");

	if ($_REQUEST['reason']) {
		if (write_config($_REQUEST['reason'])) {
			$savemsg = "Backup completed successfully.";
		}
	} elseif (write_config("Backup invoked via Auto Config Backup.")) {
			$savemsg = "Backup completed successfully.";
	} else {
		$savemsg = "Backup not completed - write_config() failed.";
	}

	$config = parse_config(true);
	unlink_if_exists("/cf/conf/lastpfSbackup.txt");

	/* The config write above will trigger a fresh upload with the given reason.
	 * This manual upload appears to be a relic of an older time (1.2.x)
	 * Leaving it just in case it needs to be resurrected
	 */
	//upload_config($_REQUEST['reason']);

	$donotshowheader = true;
}

$pgtitle = array("Services", "Auto Configuration Backup", "Backup Now");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
} else if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array("Settings", false, "/services_acb_settings.php");
$tab_array[] = array("Restore", false, "/services_acb.php");
$tab_array[] = array("Backup now", true, "/services_acb_backup.php");
display_top_tabs($tab_array);

$form = new Form("Backup");

$section = new Form_Section('Backup Details');

$section->addInput(new Form_Input(
	'reason',
	'Revision Reason',
	'text',
	$_REQUEST['reason']
))->setWidth(7)->setHelp("Enter the reason for the backup");

$form->add($section);

$section2 = new Form_Section('Device key');

$section2->addInput(new Form_Input(
	'devkey',
	'Device key',
	'text',
	$userkey
))->setWidth(7)->setReadonly()->setHelp("ID used to identify this firewall (derived from the SSH public key.) " .
	"Keep a record of this key in case you should ever need to recover this backup on another firewall.");

$form->add($section2);

print($form);

?>
<?php include("foot.inc"); ?>
