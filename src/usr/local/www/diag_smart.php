<?php
/*
 * diag_smart.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Eric Friesen
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
##|*IDENT=page-diagnostics-smart
##|*NAME=Diagnostics: S.M.A.R.T. Status
##|*DESCR=Allow access to the 'Diagnostics: S.M.A.R.T. Status' page.
##|*MATCH=diag_smart.php*
##|-PRIV

require_once("guiconfig.inc");

// What page, aka. action is being wanted
// If they "get" a page but don't pass all arguments, smartctl will throw an error
$action = $_POST['action'];

$pgtitle = array(gettext("Diagnostics"), gettext("S.M.A.R.T. Status"));
$pglinks = array("", "@self", "@self");

if ($action != 'config') {
	$pgtitle[] = htmlspecialchars(gettext('Information & Tests'));
} else {
	$pgtitle[] = gettext('Config');
}

$smartctl = "/usr/local/sbin/smartctl";

$valid_test_types = array("offline", "short", "long", "conveyance");
$valid_info_types = array("i", "H", "c", "A", "a");
$valid_log_types = array("error", "selftest");

include("head.inc");

// Highlights the words "PASSED", "FAILED", and "WARNING".
function add_colors($string) {
	// To add words keep arrays matched by numbers
	$patterns[0] = '/PASSED/';
	$patterns[1] = '/FAILED/';
	$patterns[2] = '/Warning/';
	$replacements[0] = '<span class="text-success">' . gettext("PASSED") . '</span>';
	$replacements[1] = '<span class="text-alert">' . gettext("FAILED") . '</span>';
	$replacements[2] = '<span class="text-warning">' . gettext("Warning") . '</span>';
	ksort($patterns);
	ksort($replacements);
	return preg_replace($patterns, $replacements, $string);
}

$targetdev = basename($_POST['device']);

if (!file_exists('/dev/' . $targetdev)) {
	echo gettext("Device does not exist, bailing.");
	return;
}

$specplatform = system_identify_specific_platform();
if (($specplatform['name'] == "Hyper-V") || ($specplatform['name'] == "uFW")) {
	echo sprintf(gettext("S.M.A.R.T. is not supported on this system (%s)."), $specplatform['descr']);
	include("foot.inc");
	exit;
}

switch ($action) {
	// Testing devices
	case 'test':
	{
		$test = $_POST['testType'];
		if (!in_array($test, $valid_test_types)) {
			echo gettext("Invalid test type, bailing.");
			return;
		}

		$output = add_colors(shell_exec($smartctl . " -t " . escapeshellarg($test) . " /dev/" . escapeshellarg($targetdev)));
?>
		<div class="panel  panel-default">
			<div class="panel-heading"><h2 class="panel-title"><?=gettext('Test Results')?></h2></div>
			<div class="panel-body">
				<pre><?=$output?></pre>
			</div>
		</div>

		<form action="diag_smart.php" method="post" name="abort">
			<input type="hidden" name="device" value="<?=$targetdev?>" />
			<input type="hidden" name="action" value="abort" />
			<nav class="action-buttons">
				<button type="submit" name="submit" class="btn btn-danger" value="<?=gettext("Abort")?>">
					<i class="fa fa-times icon-embed-btn"></i>
					<?=gettext("Abort Test")?>
				</button>
				<a href="<?=$_SERVER['PHP_SELF']?>" class="btn btn-info">
					<i class="fa fa-undo icon-embed-btn"></i>
					<?=gettext("Back")?>
				</a>
			</nav>
		</form>

<?php
		break;
	}

	// Info on devices
	case 'info':
	{
		$type = $_POST['type'];

		if (!in_array($type, $valid_info_types)) {
			print_info_box(gettext("Invalid info type, bailing."), 'danger');
			return;
		}

		$output = add_colors(shell_exec($smartctl . " -" . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
?>
		<div class="panel  panel-default">
			<div class="panel-heading"><h2 class="panel-title"><?=gettext('Information')?></h2></div>
			<div class="panel-body">
				<pre><?=$output?></pre>
			</div>
		</div>

		<nav class="action-buttons">
			<a href="<?=$_SERVER['PHP_SELF']?>" class="btn btn-info">
				<i class="fa fa-undo icon-embed-btn"></i>
				<?=gettext("Back")?>
			</a>
		</nav>
<?php
		break;
	}

	// View logs
	case 'logs':
	{
		$type = $_POST['type'];
		if (!in_array($type, $valid_log_types)) {
			print_info_box(gettext("Invalid log type, bailing."), 'danger');
			return;
		}

		$output = add_colors(shell_exec($smartctl . " -l " . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
?>
		<div class="panel  panel-default">
			<div class="panel-heading"><h2 class="panel-title"><?=gettext('Logs')?></h2></div>
			<div class="panel-body">
				<pre><?=$output?></pre>
			</div>
		</div>

		<nav class="action-buttons">
			<a href="<?=$_SERVER['PHP_SELF']?>" class="btn btn-info">
				<i class="fa fa-undo icon-embed-btn"></i>
				<?=gettext("Back")?>
			</a>
		</nav>
<?php
		break;
	}

	// Abort tests
	case 'abort':
	{
		$output = shell_exec($smartctl . " -X /dev/" . escapeshellarg($targetdev));
?>
		<div class="panel  panel-default">
			<div class="panel-heading"><h2 class="panel-title"><?=gettext('Abort')?></h2></div>
			<div class="panel-body">
				<pre><?=$output?></pre>
			</div>
		</div>
<?php
		break;
	}

	// Default page, prints the forms to view info, test, etc...
	default: {
// Information
		$devs = get_smart_drive_list();

		$form = new Form(false);

		$btnview = new Form_Button(
			'submit',
			'View',
			null,
			'fa-file-text-o'
		);
		$btnview->addClass('btn-primary');
		$btnview->setAttribute('id');

		$section = new Form_Section('Information');

		$section->addInput(new Form_Input(
			'action',
			null,
			'hidden',
			'info'
		))->setAttribute('id');

		$group = new Form_Group('Info type');

		$group->add(new Form_Checkbox(
			'type',
			null,
			'Info',
			false,
			'i'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'type',
			null,
			'Health',
			true,
			'H'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'type',
			null,
			'S.M.A.R.T. Capabilities',
			false,
			'c'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'type',
			null,
			'Attributes',
			false,
			'A'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'type',
			null,
			'All',
			false,
			'a'
		))->displayAsRadio();

		$section->add($group);

		$section->addInput(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setAttribute('id');

		$section->addInput(new Form_StaticText(
			'',
			$btnview
		));

		$form->add($section);
		print($form);

// Tests
		$form = new Form(false);

		$btntest = new Form_Button(
			'submit',
			'Test',
			null,
			'fa-wrench'
		);
		$btntest->addClass('btn-primary');
		$btntest->setAttribute('id');

		$section = new Form_Section('Perform self-tests');

		$section->addInput(new Form_Input(
			'action',
			null,
			'hidden',
			'test'
		))->setAttribute('id');

		$group = new Form_Group('Test type');

		$group->add(new Form_Checkbox(
			'testType',
			null,
			'Offline',
			false,
			'offline'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'testType',
			null,
			'Short',
			true,
			'short'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'testType',
			null,
			'Long',
			false,
			'long'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'testType',
			null,
			'Conveyance',
			false,
			'conveyance'
		))->displayAsRadio();

		$group->setHelp('Select "Conveyance" for ATA disks only.');
		$section->add($group);

		$section->addInput(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setAttribute('id');

		$section->addInput(new Form_StaticText(
			'',
			$btntest
		));

		$form->add($section);
		print($form);

// Logs
		$form = new Form(false);

		$btnview =  new Form_Button(
			'submit',
			'View',
			null,
			'fa-file-text-o'
		);
		$btnview->addClass('btn-primary');
		$btnview->setAttribute('id');

		$section = new Form_Section('View Logs');

		$section->addInput(new Form_Input(
			'action',
			null,
			'hidden',
			'logs'
		))->setAttribute('id');

		$group = new Form_Group('Log type');

		$group->add(new Form_Checkbox(
			'type',
			null,
			'Error',
			true,
			'error'
		))->displayAsRadio();

		$group->add(new Form_Checkbox(
			'type',
			null,
			'Self-test',
			false,
			'selftest'
		))->displayAsRadio();

		$section->add($group);

		$section->addInput(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setAttribute('id');

		$section->addInput(new Form_StaticText(
			'',
			$btnview
		));

		$form->add($section);
		print($form);

// Abort
		$btnabort = new Form_Button(
			'submit',
			'Abort',
			null,
			'fa-times'
		);

		$btnabort->addClass('btn-danger')->setAttribute('id');

		$form = new Form(false);

		$section = new Form_Section('Abort');

		$section->addInput(new Form_Input(
			'action',
			null,
			'hidden',
			'abort'
		))->setAttribute('id');

		$section->addInput(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setAttribute('id');

		$section->addInput(new Form_StaticText(
			'',
			$btnabort
		));

		$form->add($section);
		print($form);

		break;
	}
}

include("foot.inc");
