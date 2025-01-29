<?php
/*
 * services_acb_backup.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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

$input_errors = [];

if ($_POST) {
	/* Check backup reason string length. Service limit is 1024 but some of
	 *  that is consumed by the backup creator string and auth database. */
	if (!empty($_POST['reason'])) {
		if (strlen($_POST['reason']) > 900) {
			$input_errors[] = gettext("Reason text must be less than 900 characters in length.");
		}
		if (is_acb_ignored_reason($_POST['reason'])) {
			$input_errors[] = gettext("Reason text contains keywords ignored by AutoConfigBackup and will not be uploaded.");
		}
	}


	if (empty($input_errors)) {
		global $acb_force_file, $acb_last_backup_file;
		touch($acb_force_file);
		if ($_POST['reason']) {
			if (write_config($_POST['reason'] . "-MaNuAlBaCkUp")) {
				$savemsg = gettext('Backup queued successfully.');
			}
		} elseif (write_config(gettext('Backup invoked via Auto Config Backup.') . '-MaNuAlBaCkUp')) {
			$savemsg = gettext('Backup queued successfully.');
		} else {
			$savemsg = gettext('Backup not completed -- write_config() failed.');
		}

		config_read_file(true);
		unlink_if_exists($acb_last_backup_file);
	}
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
$tab_array[] = array("Backup Now", true, "/services_acb_backup.php");
display_top_tabs($tab_array);

$form = new Form("Backup", acb_enabled());

if (acb_enabled()) {
	$section = new Form_Section('Backup Details');

	$section->addInput(new Form_Input(
		'reason',
		'Revision Reason',
		'text',
		$_POST['reason']
	))->setWidth(7)->setHelp('Enter the reason for the backup. ' .
		'Must be 900 characters in length or less.');

	$form->add($section);

	$section = new Form_Section('Device Key');

	$userkey = get_acb_device_key();

	$section->addInput(new Form_Input(
		'devkey',
		'Device Key',
		'text',
		$userkey
	))->setWidth(7)->setReadonly()->setHelp('Unique key which identifies backups associated with this device.%1$s%1$s' .
		'%2$sKeep a secure copy of this value!%3$s %4$s%1$s' .
		'If this key is lost, all backups for this device will be lost!',
		'<br/>', '<strong>', '</strong>', acb_key_download_link('device', $userkey));

	$form->add($section);
} else {
	$section = new Form_Section('AutoConfigBackup Disabled');
	$section->addInput(new Form_StaticText(
		null,
		'The AutoConfigBackup service is currently disabled, manual backups are not possible.'
	))->setHelp('Enable AutoConfigBackup on the %sSettings tab%s.', '<a href="services_acb_settings.php">', '</a>');
	$form->add($section);
}

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$(form).submit(function(e) {
		e.preventDefault();
		encpwdl = '<?=strlen(config_get_path("system/acb/encryption_password", ''))?>';
		if ( encpwdl === 0) {
			alert('<?=gettext("No encryption password has been set")?>');
		} else if ($('#devkey').val().length === 0 ) {
			alert('<?=gettext("No device key has been specified")?>');
		} else if ($('#reason').val().length === 0 ) {
			alert('<?=gettext("Please provide a reason for this backup")?>');
		} else {
			form.submit(); // submit bypassing the jQuery bound event
		}
	});
});
//]]>
</script>

<?php include("foot.inc"); ?>
