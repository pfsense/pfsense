<?php
/*
 * autoconfigbackup_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-acb-settings
##|*NAME=Services: Auto Config Backup: Settings
##|*DESCR=Configure the auto config backup system.
##|*MATCH=services_acb_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");

function index_of_command() {
	global $croncmd, $a_cron;

	$i = 0;
	$rv = -1;

	if (count($a_cron) > 0) {
		foreach ($a_cron as $ent) {
			if ($ent['command'] === $croncmd) {
				return $i;
			}

		$i++;
		}
	}

	return $rv;

}

$croncmd = "/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/execacb.php";

init_config_arr(array('cron', 'item'));
$a_cron = &$config['cron']['item'];
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

	if ($_POST['frequency'] === 'cron') {
		if (!preg_match('/^[0-9\*\/\-\,]+$/', $_POST['hours'] . $_POST['day'] . $_POST['month'] . $_POST['dow']))  {
			$input_errors[] = gettext("Schedule values may only contain 0-9 - , / *");
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
		$config['system']['acb']['frequency'] = $pconfig['frequency'];
		$config['system']['acb']['hours'] = $pconfig['hours'];
		$config['system']['acb']['month'] = $pconfig['month'];
		$config['system']['acb']['day'] = $pconfig['day'];
		$config['system']['acb']['dow'] = $pconfig['dow'];

		// Remove any existing cron jobs
		$cronid = index_of_command();

		if ($cronid >= 0) {
			unset($a_cron[$cronid]);
		}

		if ($pconfig['frequency'] === "cron") {
			$ent = array();
			$ent['minute'] = '0';
			$ent['hour'] = $pconfig['hours'];
			$ent['mday'] = $pconfig['day'];
			$ent['month'] = $pconfig['month'];
			$ent['wday'] = $pconfig['dow'];
			$ent['who'] = 'root';
			$ent['command'] = $croncmd;

			$a_cron[] = $ent;

		}

		write_config("AutoConfigBackup settings updated");
		configure_cron();
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

$group = new Form_MultiCheckboxGroup('Backup Frequency');

$group->add(new Form_MultiCheckbox(
	'frequency',
	'',
	'Automatically backup on every configuration change',
	(!isset($pconfig['frequency']) || $pconfig['frequency'] === 'every'),
	'every'
))->displayasRadio();
/*
$group->add(new Form_MultiCheckbox(
	'frequency',
	'',
	'Backup manually only',
	($pconfig['frequency'] === 'manual'),
	'manual'
))->displayasRadio();
*/
$group->add(new Form_MultiCheckbox(
	'frequency',
	'',
	'Automatically backup on a regular schedule',
	($pconfig['frequency'] === 'cron'),
	'cron'
))->displayasRadio();

$group->addClass("notoggleall");
$section->add($group);


$group = new Form_Group("Schedule");

$group->add(new Form_Input(
	'hours',
	'Hour',
	'text',
	(isset($pconfig['hours']) ? $pconfig['hours']:'0')
))->setHelp("Hours (0-23)");

$group->add(new Form_Input(
	'day',
	'Day of month',
	'text',
	(isset($pconfig['day']) ? $pconfig['day']:'*')
))->setHelp("Day (1-31)");

$group->add(new Form_Input(
	'month',
	'Month',
	'text',
	(isset($pconfig['month']) ? $pconfig['month']:'*')
))->setHelp("Month (1-12)");

$group->add(new Form_Input(
	'dow',
	'Day of week',
	'text',
	(isset($pconfig['dow']) ? $pconfig['dow']:'*')
))->setHelp("Day of week (0-6)");

$group->addClass("cronsched");
$group->setHelp(sprintf('Use * ("every"), divisor or exact value.  Minutes are fixed at 0. See %s for more information.',
	'<a href="https://www.freebsd.org/cgi/man.cgi?crontab(5)" target="_blank">Cron format</a>'));
$section->add($group);

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

		$('input:radio[name=frequency]').click(function() {
			hideClass("cronsched", ($(this).val() != 'cron'));
		});

		hideClass("cronsched", ("<?=$pconfig['frequency']?>" != 'cron'));
	});
//]]>
</script>

<?php
include("foot.inc");
?>
