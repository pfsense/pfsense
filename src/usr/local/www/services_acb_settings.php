<?php
/*
 * autoconfigbackup_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
require_once("acb.inc");

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

	// Validate form contents
	if ($_POST['encryption_password'] != "********") {
		if ($_POST['encryption_password'] != $_POST['encryption_password_confirm']) {
			$input_errors[] = gettext("Encryption password and confirmation do not match");
		} else {
			$update_ep = true;
		}
	}

	if ($_POST['frequency'] === 'cron') {
		if (!preg_match('/^[0-9\*\/\-\,]+$/', $_POST['minute'] . $_POST['hours'] . $_POST['day'] . $_POST['month'] . $_POST['dow']))  {
			$input_errors[] = gettext("Schedule values may only contain 0-9 - , / *");
		}
	}

	if ((int)$_POST['numman'] > (int)"50" ) {
		$input_errors[] = gettext("You may not retain more than 50 manual backups.");
	}

	$pwd = "";

	if (!$input_errors) {
		if($update_ep) {
			$pwd = $pconfig['encryption_password'];
		}

		setup_ACB(
			$pconfig['enable'],
			$pconfig['hint'],
			$pconfig['frequency'],
			$pconfig['minute'],
			$pconfig['hours'],
			$pconfig['month'],
			$pconfig['day'],
			$pconfig['dow'],
			$pconfig['numman'],
			$pwd
		);
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
	'minute',
	'Minute',
	'text',
	(isset($pconfig['minute']) ? $pconfig['minute'] : strval(random_int(0,59)))
))->setHelp("Minute (0-59)");

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
$group->setHelp(sprintf('Use * ("every"), divisor or exact value.  Minutes are randomly chosen by default. See %s for more information.',
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
	'Hint/Identifier',
	'text',
	$pconfig['hint']
))->setHelp("You may optionally provide an identifier which will be stored in plain text along with each encrypted backup. " .
			"This may allow the Netgate support team to locate your key should you lose it.");

$section->addInput(new Form_Input(
	'numman',
	'Manual backups to keep',
	'number',
	$pconfig['numman']
))->setHelp("It may be useful to specify how many manual backups are retained on the server so that automatic backups do not overwrite them." .
			"A maximum of 50 retained manual backups (of the 100 total backups) is permitted.");

$form->add($section);

print $form;

?>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
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
