<?php
/*
 * autoconfigbackup_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-autoconfigbackup
##|*NAME=System: Auto COnfig Backup
##|*DESCR=Configure the auto config backup system.
##|*MATCH=autoconfigbackup-settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

$pconfig = $config['system']['acb'];


if (isset($_POST['save'])) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "encryption_password");
	$reqdfieldsn = array(gettext("Encryption password"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (strlen($_POST['encryption_password']) < 8) {
		$input_errors[] = gettext("The encryption password must contain at least 8 characters");
	}

	$update_ep = false;
	$update_gp = false;
	$update_gep = false;

	// Validate form contents
	if ($_POST['encryption_password'] != "********") {
		if ($_POST['encryption_password'] != $_POST['encryption_password_confirm']) {
			$input_errors[] = gettext("Encryption password and confirmation do not match");
		} else {
			$update_ep = true;
		}
	}

	// Validate legacy settings
	// All blank is allowed, otherwise they must be valid
	if ($pconfig['legacy'] == 'yes') {
		if (empty($_POST['gold_password']) && empty($_POST['gold_username']) && empty($_POST['gold_password_confirm']) &&
		    empty($_POST['gold_encryption_password']) && empty($_POST['gold_encryption_password_confirm'])) {
			$update_gep = true;
			$pconfig['legacy'] = 'no';
		} else {
			if ($_POST['gold_password'] != "********") {
				if ($_POST['gold_password'] != $_POST['gold_password_confirm']) {
					$input_errors[] = gettext("Legacy Gold password and confirmation do not match");
				} else {
					$update_gp = true;
				}
			}

			if ($_POST['gold_encryption_password'] != "********") {
				if ($_POST['gold_encryption_password'] != $_POST['gold_encryption_password_confirm']) {
					$input_errors[] = gettext("Legacy Gold encryption password and confirmation do not match");
				} else {
					$update_gep = true;
				}
			}

			if (empty($_POST['gold_username'])) {
				$input_errors[] = gettext("Legacy Gold username may not be blank");
			}

			if (empty($_POST['gold_password'])) {
				$input_errors[] = gettext("Legacy Gold password may not be blank");
			}

			if (empty($_POST['gold_encryption_password'])) {
				$input_errors[] = gettext("Legacy Gold encryption password may not be blank");
			}
		}
	}

	if (!$input_errors) {
		if($update_ep) {
			$config['system']['acb']['encryption_password'] = $pconfig['encryption_password'];
		}

		$config['system']['acb']['enable'] = $pconfig['enable'];

		if($update_gp) {
			$config['system']['acb']['gold_password'] = $pconfig['gold_password'];
		}

		if ($update_gep) {
			$config['system']['acb']['gold_encryption_password'] = $pconfig['gold_encryption_password'];
		}

		$config['system']['acb']['gold_username'] = $pconfig['gold_username'];
		$config['system']['acb']['hint'] = $pconfig['hint'];

		write_config("AutoCcnfigBackup settings updated");
	}
}

$pgtitle = array(gettext("Services"), gettext("Auto Configuration Backup"), gettext("Settings"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array("Settings", true, "/services_acb_settings.php");
$tab_array[] = array("Restore", false, "/services_acb.php");
$tab_array[] = array("Backup now", false, "/services_acb_backup.php");
display_top_tabs($tab_array);

$form = new Form;
$section = new Form_Section('Auto Config Backup');

$section->addInput(new Form_Input(
	'legacy',
	'',
	"hidden",
	$pconfig['legacy']
));

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable ACB',
	'Enable automatic configuration backups',
	($pconfig['enable'] == "yes")
));

$section->addPassword(new Form_Input(
	'encryption_password',
	'*Encryption Password',
	'password',
	$pconfig['encryption_password']
));

$section->addInput(new Form_Input(
	'hint',
	'Identifier',
	'text',
	$pconfig['hint']
))->setHelp("You may optionally provide an identifier which will be stored in plain text along with each encrypted backup. " .
			"This may allow the Netgate support team to locate your key should you lose it.");

$form->add($section);

$section = new Form_Section('Legacy "Gold" settings', 'legacy_panel');

$section->addInput(new Form_Input(
	'gold_username',
	'*Username',
	'text',
	$pconfig['gold_username']
));

$section->addPassword(new Form_Input(
	'gold_password',
	'*Password',
	'password',
	$pconfig['gold_password']
));

$section->addPassword(new Form_Input(
	'gold_encryption_password',
	'*Encryption password',
	'password',
	$pconfig['gold_encryption_password']
));

$form->add($section);

$form->addGlobal(new Form_Button(
	'btnlegacy',
	'Legacy "Gold" settings',
	null,
	null
))->removeClass('btn-primary')->addClass('btn-success btn-xs pull-right');

print $form;

?>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
		$('#btnlegacy').prop('type', 'button');

		// Hide/show the legacy settings on page load
		if ($('#legacy').val() != 'yes') {
			$('#legacy_panel').addClass('hidden');
		}

		// On clicking "legacy" button
		$('#btnlegacy').click(function() {
			if ($('#legacy').val() != "yes") {
				$('#legacy_panel').removeClass('hidden');
				$('#legacy').val('yes');
			} else {
				$('#legacy_panel').addClass('hidden');
				$('#legacy').val('no');
			}
		});
	});
//]]>
</script>

<?php
include("foot.inc");
?>
