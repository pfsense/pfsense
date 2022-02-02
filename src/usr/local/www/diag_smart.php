<?php
/*
 * diag_smart.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

$test_types = array(
	'offline' => gettext('Offline Test'),
	'short' => gettext('Short Test'),
	'long' => gettext('Long Test'),
	'conveyance' => gettext('Conveyance Test')
);
$info_types = array(
	'x' => gettext('All SMART and Non-SMART Information'),
	'a' => gettext('All SMART Information'),
	'i' => gettext('Device Information'),
	'H' => gettext('Device Health'),
	'c' => gettext('SMART Capabilities'),
	'A' => gettext('SMART Attributes'),
);
$log_types = array(
	'error' => gettext('Summary Error Log'),
	'xerror' => gettext('Extended Error Log'),
	'selftest' => gettext('SMART Self-Test Log'),
	'xselftest' => gettext('Extended Self-Test Log'),
	'selective' => gettext('Selective Self-Test Log'),
	'directory' => gettext('Log Directory'),
	'scttemp' => gettext('Device Temperature Log (ATA Only)'),
	'devstat' => gettext('Device Statistics (ATA Only)'),
	'sataphy' => gettext('SATA PHY Events (SATA Only)'),
	'sasphy' => gettext('SAS PHY Events (SAS Only)'),
	'nvmelog' => gettext('NVMe Log (NVMe Only)'),
	'ssd' => gettext('SSD Device Statistics (ATA/SCSI)'),
);

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
		$test = $_POST['type'];
		if (!in_array($test, array_keys($test_types))) {
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
				<button type="submit" name="submit" class="btn btn-danger" value="<?=gettext("Abort Tests")?>">
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

		if (!in_array($type, array_keys($info_types))) {
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
		if (!in_array($type, array_keys($log_types))) {
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
		$group = new Form_Group('Select a drive and type:');
		$form->addGlobal(new Form_Input(
			'action',
			null,
			'hidden',
			'info'
		))->setAttribute('id');

		$group->add(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setHelp(gettext("Device: /dev/"));

		$group->add(new Form_Select(
			'type',
			'Type',
			false,
			$info_types
		))->setHelp(gettext("Information Type"));

		$group->add(new Form_StaticText(
			'',
			$btnview
		));
		$section->add($group);
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
		$group = new Form_Group('Select a device and log');
		$form->addGlobal(new Form_Input(
			'action',
			null,
			'hidden',
			'logs'
		))->setAttribute('id');

		$group->add(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setHelp(gettext("Device: /dev/"));

		$group->add(new Form_Select(
			'type',
			'Log',
			false,
			$log_types
		))->setHelp(gettext("Log"));

		$group->add(new Form_StaticText(
			'',
			$btnview
		));

		$section->add($group);
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
		$group = new Form_Group('Select a drive and test');
		$form->addGlobal(new Form_Input(
			'action',
			null,
			'hidden',
			'test'
		))->setAttribute('id');

		$group->add(new Form_Select(
			'device',
			'Device: /dev/',
			false,
			array_combine($devs, $devs)
		))->setHelp(gettext("Device: /dev/"));

		$group->add(new Form_Select(
			'type',
			'Test',
			false,
			$test_types
		))->setHelp(gettext("Self-Test Type"));

		$group->add(new Form_StaticText(
			'',
			$btntest
		));

		$group->setHelp('Select "Conveyance" for ATA disks only.');
		$section->add($group);
		$form->add($section);
		print($form);

// Abort
		$btnabort = new Form_Button(
			'submit',
			'Abort Tests',
			null,
			'fa-times'
		);

		$btnabort->addClass('btn-danger')->setAttribute('id');

		$form = new Form(false);

		$section = new Form_Section('Abort Tests');

		$form->addGlobal(new Form_Input(
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
		))->setHelp(gettext("Aborts all self-tests running on the selected device."));

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
