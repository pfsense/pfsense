<?php
/*
 * services_acb_changekey.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-acb-changekey
##|*NAME=Services: Auto Config Backup: Change Device Key
##|*DESCR=Change the auto config backup device key.
##|*MATCH=services_acb_changekey.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");
require_once("acb.inc");

$pconfig = config_get_path('system/acb', []);

if ($_POST['generatekey']) {
	print json_encode(['newdevicekey' => acb_generate_device_key()]);
	exit;
}

$userkey = get_acb_device_key();

if (isset($_POST['updatekey'])) {
	unset($input_errors);

	/* Add validation */
	if (!is_valid_acb_device_key($_POST['devkey'])) {
		$input_errors[] = gettext("Invalid Device Key value.");
	}

	if (!$input_errors) {
		/* Update key */
		config_set_path('system/acb/device_key', $_POST['devkey']);
		write_config(sprintf(gettext('Changed the AutoConfigBackup device key from %s to %s'),
			$userkey,
			$_POST['devkey']));
		Header("Location: /services_acb_settings.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Auto Configuration Backup"), gettext("Change Device Key"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array("Settings", false, "/services_acb_settings.php");
$tab_array[] = array("Restore", false, "/services_acb.php");
$tab_array[] = array("Backup Now", false, "/services_acb_backup.php");
$tab_array[] = array("Change Device Key", true, "/services_acb_changekey.php");
display_top_tabs($tab_array);

$savebutton = new Form_Button(
	'updatekey',
	'Update Key',
	null,
	'fa-solid fa-save icon-embed-btn'
);
$savebutton->addClass('btn-danger')->setAttribute('disabled', true);

$form = new Form($savebutton);
$section = new Form_Section('Change Device Key');

$legacy_key = get_acb_legacy_device_key();

if (!is_valid_acb_device_key($legacy_key) ||
    ($legacy_key != $userkey)) {
	$device_key_backups = acb_backup_list($userkey);
	$section->addInput(new Form_Input(
		'currentkey',
		'Current Device Key',
		'text',
		$userkey
	))->setWidth(7)->setReadonly()->setHelp('Unique key which identifies backups associated with this device.%1$s%1$s' .
		'%2$sKeep a secure copy of this value!%3$s %4$s%1$s' .
		'If this key is lost, all backups for this device key will be lost!%1$s%1$s' .
		'Hosted backups for this device key: %5$d',
		'<br/>', '<strong>', '</strong>', acb_key_download_link('device', $userkey), count($device_key_backups));
}

if (is_valid_acb_device_key($legacy_key)) {
	$legacy_key_backups = acb_backup_list($legacy_key);
	$section->addInput(new Form_Input(
		'legacykey',
		'Legacy Device Key',
		'text',
		$legacy_key
	))->setWidth(7)->setReadonly()->setHelp('Unique key which identifies backups associated with this device.%1$s%1$s' .
		'This is a legacy style key derived from the SSH public key.%1$s%1$s' .
		'%2$sKeep a secure copy of this value!%3$s %4$s%1$s' .
		'If this key is lost, all backups for this legacy device key will be lost!%1$s%1$s' .
		'Hosted backups for this legacy key: %5$d',
		'<br/>', '<strong>', '</strong>', acb_key_download_link('legacy', $userkey), count($legacy_key_backups));
}

$group = new Form_Group("New Device Key");

$group->add(new Form_Input(
	'devkey',
	'Device Key',
	'text',
	""
))->setWidth(7)->setHelp('New device key, replaces the Current Device Key.%1$s%1$s' .
	'Use the %2$sGenerate New Key%3$s button to create a new randomized key in the proper format, or ' .
	'paste a properly formatted key into the field. Keys must be 64 character hexadecimal strings (0-9, a-f).%1$s%1$s' .
	'%2$sTreat this key as a secret!%3$s%1$sAnyone who has this key can manipulate the backups for this key.%1$s%1$s' .
	'%2$sChanging the Device Key removes the existing device key!%3$s%1$sUse the download icon above to save a copy ' .
	'of the old key before continuing!',
	'<br/>', '<strong>', '</strong>');

$group->add(new Form_Button(
	'generatekey',
	'Generate New Key',
	null,
	'fa-solid fa-arrows-rotate'
))->addClass('btn-info btn-xs');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'confirmation',
	'Warning',
	'Check this box to acknowledge that the Device Key will change, disconnecting from previous backups',
	false
))->setHelp('Checking this box enables the Update Key button. ' .
	'Save a copy of the old Device Key before continuing.%1$s%1$s' .
	'Old backups are not automatically removed from the server and must be removed manually.',
	'<br/>');

$form->add($section);

print $form;

?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('#generatekey').click(function(event) {
		event.preventDefault();
		$.ajax({
			type: 'post',
			url: 'services_acb_changekey.php',
			data: {
				generatekey: true,
			},
			dataType: 'json',
			success: function(data) {
				$('#devkey').val(data.newdevicekey.replace(/\\n/g, '\n'));
			}
		});
	});
	$('#confirmation').click(function() {
		$('#updatekey').prop("disabled", !$('#confirmation').prop('checked'));
	});
});
//]]>
</script>

<?php
include("foot.inc");
?>
