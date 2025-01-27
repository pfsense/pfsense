<?php
/*
 * services_acb.php
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
##|*IDENT=page-services-acb
##|*NAME=Services: Auto Config Backup: Restore
##|*DESCR=Restore from auto config backup.
##|*MATCH=services_acb.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("acb.inc");

$decrypt_password = config_get_path('system/acb/encryption_password');

/* Check if the ACB configuration contains an encryption password.
 * If it does not, then redirect user to the settings page.
 */
if (!$decrypt_password) {
	Header("Location: /services_acb_settings.php");
	exit;
}

$input_errors = [];

$origkey = get_acb_device_key();
$userkey = $origkey;

if (isset($_POST['userkey'])) {
	if (is_valid_acb_device_key($_POST['userkey'])) {
		$userkey = htmlentities($_POST['userkey']);
	} else {
		$input_errors[] = gettext("Invalid Device Key value");
		unset($_POST['userkey']);
	}
}

if ($_POST['savemsg']) {
	$savemsg = htmlentities($_POST['savemsg']);
}

if ($_POST['view'] &&
    is_valid_acb_revision($_POST['view'])) {
	$pgtitle = array("Services", "Auto Configuration Backup", "Revision Information");
} else {
	$pgtitle = array("Services", "Auto Configuration Backup", "Restore");
	unset($_POST['view']);
}

include("head.inc");

if (($_POST['rmver'] != "") &&
    is_valid_acb_revision($_POST['rmver'])) {
	$savemsg = acb_backup_delete($userkey, $_POST['rmver']);
} else {
	unset($_POST['rmver']);
}

if (($_POST['newver'] != "") &&
    is_valid_acb_revision($_POST['newver'])) {
	[$encrypted_backup, $httpcode, $errno] = acb_backup_get($userkey, $_POST['newver']);
	if ($errno) {
		$input_errors[] = sprintf(gettext('Unable to get backup revision from AutoConfigBackup service: %s'), htmlspecialchars($_POST['newver']));
	} else {
		[$decrypted_config, $encrypted_config, $sha256, $decrypt_errors] = acb_backup_decrypt($encrypted_backup, $decrypt_password);
	}

	if (!empty($decrypt_errors)) {
		$input_errors = array_merge($input_errors, $decrypt_errors);
	} else {
		$config_restore_path = '/tmp/config_restore.xml';
		file_put_contents($config_restore_path, $decrypted_config);

		$ondisksha256 = hash_file('sha256', $config_restore_path);
		/* ACB may not have a sha256 hash on file for older backup entries */
		if (($sha256 != "0") &&
		    ($sha256 != "")) {
			if ($ondisksha256 != $sha256) {
				$input_errors[] = "SHA256 values do not match, cannot restore. ({$ondisksha256} != {$sha256})";
			}
		}

		if (!$input_errors && $decrypted_config) {
			if (config_restore($config_restore_path,
			    sprintf(gettext('AutoConfigBackup revision %s'), $_POST['newver']))) {
				$savemsg = sprintf(gettext('Successfully reverted the %s configuration to revision %s.'),
					g_get('product_label'),
					$_POST['newver']);
				$savemsg .= <<<EOF
			<br />
		<form action="diag_reboot.php" method="post">
			Reboot the firewall to full activate changes?
			<input name="rebootmode" type="hidden" value="Reboot" />
			<input name="Submit" type="submit" class="formbtn" value=" Yes " />
		</form>
EOF;
			} else {
				$errormsg = gettext('Unable to revert to the selected configuration backup revision.');
			}
		} else {
			log_error(gettext('There was an error while restoring the AutoConfigBackup entry'));
		}
		unlink_if_exists($config_restore_path);
	}
} else {
	unset($_POST['newver']);
}

if ($_POST['view'] &&
    is_valid_acb_revision($_POST['view'])) {
	[$encrypted_backup, $httpcode, $errno] = acb_backup_get($userkey, $_POST['view']);
	if ($errno) {
		$input_errors[] = sprintf(gettext('Unable to get backup revision from AutoConfigBackup service: %s'), htmlspecialchars($_POST['view']));
	} else {
		[$decrypted_config, $encrypted_config, $sha256sum, $decrypt_errors] = acb_backup_decrypt($encrypted_backup, $decrypt_password);
	}

	if (!empty($decrypt_errors)) {
		$input_errors = array_merge($input_errors, $decrypt_errors);
	} else {
		$revision = $_POST['view'];
		if ($sha256sum == "0") {
			$sha256sum = "None on file.";
		}
		$configtype = "Encrypted";
	}
	if ($_POST['download'] == 'true') {
		$hostname = config_get_path('system/hostname') . "." . config_get_path('system/domain');
		$revision = acb_time_shift($_POST['view'], "YmdHis");
		send_user_download('data', $decrypted_config, "config-backup-acb-{$hostname}-{$revision}.xml", "text/xml");
	}
} else {
	unset($_POST['view']);
}

/* $confvers must be an array. */
$confvers = [];

if ((!($_POST['view']) || $input_errors) &&
    acb_check_dns()) {
	$confvers = acb_backup_list($userkey);
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}
if ($errormsg) {
	print_info_box($errormsg, 'danger');
}

$tab_array = array();
$tab_array[0] = array("Settings", false, "/services_acb_settings.php");
$tab_array[1] = array("Restore", !($_POST['view']), "/services_acb.php");
if ($_POST['view']) {
	$tab_array[] = array("Revision", true, "/services_acb.php?view=" . htmlspecialchars($_POST['view']));
}
$tab_array[] = array("Backup Now", false, "/services_acb_backup.php");

display_top_tabs($tab_array);
?>

<div id="loading">
	<i class="fa-solid fa-spinner fa-spin"></i> Loading, please wait...
</div>


<?php if ($_POST['view'] && (!$input_errors)):

$form = new Form(false);

$section = new Form_Section('Backup Details');

$section->addInput(new Form_Input(
	'view',
	'Service Date/Time',
	'text',
	$_POST['view']
))->setWidth(7)->setReadOnly()->setHelp('Date and time of this revision on the AutoConfigBackup service (UTC).');

$local_revision_time = acb_time_shift($_POST['view'], $format = DATE_RFC2822);

$section->addInput(new Form_Input(
	'view',
	'Local Date/Time',
	'text',
	$local_revision_time
))->setWidth(7)->setReadOnly()->setHelp('Local date and time of this revision.');

$metadata = acb_backup_get_metadata($userkey, $_POST['view']);
$reason = (array_key_exists('reason', $metadata)) ? $metadata['reason'] : gettext("No Revision Description");

$section->addInput(new Form_Input(
	'reason',
	'Revision Reason',
	'text',
	$reason
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'shasum',
	'SHA256 Summary',
	'text',
	$sha256sum
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Textarea(
	'config_xml',
	'Encrypted config.xml',
	$encrypted_config
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$section->addInput(new Form_Textarea(
	'dec_config_xml',
	'Decrypted config.xml',
	$decrypted_config
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$form->add($section);

print($form);

?>
<a class="btn btn-warning do-confirm"
	title="<?=sprintf(gettext('Restore backup revision %s'), $_POST['view'])?>"
	href="services_acb.php?userkey=<?=$userkey?>&newver=<?=$_POST['view']?>"
	usepost><i class="fa-solid fa-undo"></i> Restore this revision</a>

<a class="btn btn-primary"
	title="<?=gettext('Download this revision')?>"
	href="services_acb.php?userkey=<?=$userkey?>&view=<?=$_POST['view']?>&download=true"
	usepost><i class="fa-solid fa-cloud-arrow-down"></i> Download this revision</a>

<?php else:

$section2 = new Form_Section('Device Key');
$group = new Form_Group("Device Key");

$legacy_key = get_acb_legacy_device_key();
$legacy_string = "";
if (is_valid_acb_device_key($legacy_key) &&
    ($legacy_key != $userkey)) {
	$legacy_string = sprintf(gettext('%1$s%1$sBackups may also exist under the legacy key for this device: %2$s'), "<br/>", $legacy_key);
	$check_button_key = $legacy_key;
} elseif ($userkey != $origkey) {
	$legacy_string = sprintf(gettext('%1$s%1$sBackups may also exist under the configured key for this device: %2$s'), "<br/>", $origkey);
	$check_button_key = $origkey;
}

if ($userkey == $legacy_key) {
	$key_type = "legacy";
} elseif($userkey != $origkey) {
	$key_type = "alternate";
} else {
	$key_type = "device";
}

if (!empty($legacy_string)) {
	/* Add a check button */
	$legacy_string .= ' <a class="btn-sm btn-primary" ' .
				'title="' . gettext('Check this key') . '" ' .
				'href="services_acb.php?userkey=' . $check_button_key . '" ' .
				'usepost><i class="fa-solid fa-search"></i> ' .
				gettext('Check') . '</a><br/><br/>';
}
$group->add(new Form_Input(
	'devkey',
	'Device Key',
	'text',
	$userkey
))->setWidth(7)->setHelp('Unique key which identifies backups associated with this device. ' .
	'See help below for more details.%1$s%1$s' .
	'%2$sKeep a secure copy of this value!%3$s %4$s%1$s' .
	'If this key is lost, all backups for this device will be lost!%5$s',
	'<br/>', '<strong>', '</strong>', acb_key_download_link($key_type, $userkey), $legacy_string);

$group->add(new Form_Button(
	'upduserkey',
	'Search',
	null,
	'fa-solid fa-search'
))->addClass('btn-success btn-xs');

$group->add(new Form_Button(
	'restore',
	'Reset',
	null,
	'fa-solid fa-undo'
))->addClass('btn-info btn-xs');

$section2->add($group);
print($section2);

print('<div class="infoblock">');
print_info_box(gettext('AutoConfigBackup uses the Device Key to associate ' .
	'uploaded configuration backups with a specific installation. ' .
	'To view or restore configuration backups from a different installation, ' .
	'paste its device key into the Device Key field above and click "Search". '), 'info', false);
print('</div>');

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Automatic Configuration Backups')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
		</div>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" id="backups" data-sortable>
				<thead>
					<tr>
						<th data-sortable-type="date" width="25%"><?=gettext('Local Date/Time')?></th>
						<th width="65%"><?=gettext('Configuration Change')?></th>
						<th width="10%"><?=gettext('Actions')?></th>
					</tr>
				</thead>
				<tbody>

			<?php
				$counter = 0;
				if (config_get_path('system/acb/reverse') == "yes"){
					$confvers = array_reverse($confvers);
				}
				$staged_backup_count = count(glob(g_get('acbbackuppath') . '*.form'));

				foreach ($confvers as $cv):
			?>
					<tr>
						<td title="<?= sprintf(gettext("Service Date/Time: %s"), $cv['time']) ?>">
							<?= $cv['localtime']; ?>
						</td>
						<td>
							<?= htmlspecialchars($cv['reason'], double_encode: false); ?>
						</td>
						<td>
							<a class="fa-solid fa-undo text-warning do-confirm"
								title="<?=sprintf(gettext('Restore backup revision %s'), $cv['time'])?>"
								href="services_acb.php?userkey=<?=$userkey?>&newver=<?=$cv['time']?>"
								usepost></a>
							<a class="fa-solid fa-file-lines"
								title="<?=gettext('View this revision')?>"
								href="services_acb.php?userkey=<?=$userkey?>&view=<?=$cv['time']?>"
								usepost></a>
							<a class="fa-solid fa-cloud-arrow-down"
								title="<?=gettext('Download this revision')?>"
								href="services_acb.php?userkey=<?=$userkey?>&view=<?=$cv['time']?>&download=true"
								usepost></a>
							<a class="fa-solid fa-trash-can text-danger"
								title="<?=gettext('Delete this revision')?>"
								href="services_acb.php?userkey=<?=$userkey?>&rmver=<?=$cv['time']?>"
								usepost></a>
						</td>
					</tr>
				<?php	$counter++;
				endforeach;
				?>
				</tbody>
			</table>
			<table class="table table-striped table-hover table-condensed" id="backups" data-sortable>
			<tr>
				<td colspan="3" align="center">
			<?php if ($counter == 0): ?>
					<span class="text-danger">
						<strong>
						<?=gettext('The AutoConfigBackup service could not find any backups for this device key.')?>
						</strong>
					</span>
			<?php else: ?>
					<?=sprintf(gettext('Hosted backup count: %d'), $counter) ?>
			<?php endif; ?>
			<?php if ($staged_backup_count > 0): ?>
					<br />
					<?=sprintf(gettext('Staged backups waiting to upload: %d'), $staged_backup_count) ?>
			<?php endif; ?>
				</td>
			</tr>
			</table>
		</div>
	</div>
</div>
<?php

endif; ?>

</form>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#loading').hide();

	// Submit a form to change the device key
	function changedevkey() {
		var $form = $('<form>');
		var newuserkey = $('#devkey').val();
		$form
			.attr("method", "POST")
			.attr("action", '/services_acb.php')
			// CSRF magic value is required to submit form content securely.
			.append(
				$("<input>")
					.attr("type", "hidden")
					.attr("name", "__csrf_magic")
					.val(csrfMagicToken)
			)
			.append(
			$("<input>")
				.attr("type", "hidden")
				.attr("name", "userkey")
				.val(newuserkey)
			)
			.appendTo('body')
			.submit();
	}

	$('#upduserkey').click(function() {
		changedevkey();
	});

	$('#restore').click(function() {
		$('#devkey').val("<?=$origkey?>");
		changedevkey();
	});
});
//]]>
</script>

<?php include("foot.inc"); ?>
