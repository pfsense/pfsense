<?php
/*
 * diag_smart.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Eric Friesen
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgment:
 *    "This product includes software developed by the pfSense Project
 *    for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 * 4. The names "pfSense" and "pfSense Project" must not be used to
 *    endorse or promote products derived from this software without
 *    prior written permission. For written permission, please contact
 *    coreteam@pfsense.org.
 *
 * 5. Products derived from this software may not be called "pfSense"
 *    nor may "pfSense" appear in their names without prior written
 *    permission of the Electric Sheep Fencing, LLC.
 *
 * 6. Redistributions of any form whatsoever must retain the following
 *    acknowledgment:
 *
 * "This product includes software developed by the pfSense Project
 * for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 * THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 * EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 * ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
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
$action = (isset($_POST['action']) ? $_POST['action'] : $_GET['action']);

$pgtitle = array(gettext("Diagnostics"), gettext("S.M.A.R.T. Status"));
$pglinks = array("", "@self", "@self");

if ($action != 'config') {
	$pgtitle[] = htmlspecialchars(gettext('Information & Tests'));
} else {
	$pgtitle[] = gettext('Config');
}
$smartctl = "/usr/local/sbin/smartctl";
$smartd = "/usr/local/sbin/smartd";
$start_script = "/usr/local/etc/rc.d/smartd.sh";

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

// Edits smartd.conf file, adds or removes email for failed disk reporting
function update_email($email) {
	/* Bail if an e-mail address is invalid */
	if (!empty($email) && (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
		return;
	}

	if (!file_exists("/usr/local/etc/smartd.conf") && file_exists("/usr/local/etc/smartd.conf.sample")) {
		copy("/usr/local/etc/smartd.conf.sample", "/usr/local/etc/smartd.conf");
	}
	// Did they pass an email?
	if (!empty($email)) {
		// Put it in the smartd.conf file
		shell_exec("/usr/bin/sed -i .old " . escapeshellarg("s/^DEVICESCAN.*/DEVICESCAN -H -m {$email}/") . " /usr/local/etc/smartd.conf");
	} else {
		// Remove email flags in smartd.conf
		shell_exec("/usr/bin/sed -i .old 's/^DEVICESCAN.*/DEVICESCAN/' /usr/local/etc/smartd.conf");
	}
}

function smartmonctl($action) {
	global $start_script;
	shell_exec($start_script . escapeshellarg($action));
}
$targetdev = basename($_POST['device']);

if (!file_exists('/dev/' . $targetdev)) {
	echo gettext("Device does not exist, bailing.");
	return;
}

$tab_array = array();
$tab_array[0] = array(htmlspecialchars(gettext("Information & Tests")), ($action != 'config'), $_SERVER['PHP_SELF'] . "?action=default");
$tab_array[1] = array(gettext("Config"), ($action == 'config'), $_SERVER['PHP_SELF'] . "?action=config");
display_top_tabs($tab_array);

$specplatform = system_identify_specific_platform();
if ($specplatform['name'] == "Hyper-V") {
	echo gettext("S.M.A.R.T. is not supported in Hyper-V guests.");
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

	// Config changes, users email in xml config and write changes to smartd.conf
	case 'config':
	{
		if (isset($_POST['test'])) {

// FIXME				shell_exec($smartd . " -M test -m " . $config['system']['smartmonemail']);
			$savemsg = sprintf(gettext("Email sent to %s"), $config['system']['smartmonemail']);
			smartmonctl("stop");
			smartmonctl("start");
			$style = 'warning';
		} else if (isset($_POST['save'])) {
			if (!empty($_POST['smartmonemail']) && (filter_var($_POST['smartmonemail'], FILTER_VALIDATE_EMAIL) === false)) {
				$savemsg = "The supplied e-mail address is invalid.";
				$style = 'danger';
			} else {
				$config['system']['smartmonemail'] = $_POST['smartmonemail'];
				write_config();
				$retval = 0;
				if (stristr($retval, "error") != true) {
					$savemsg = get_std_save_message($retval);
					$style = 'success';
				} else {
					$savemsg = $retval;
					$style='danger';
				}
				// Write the changes to the smartd.conf file
				update_email($_POST['smartmonemail']);
				// Send sig HUP to smartd, rereads the config file
				shell_exec("/usr/bin/killall -HUP smartd");
			}
		}

	// Was the config changed? if so, print the message
	if ($savemsg) {
		print_info_box($savemsg, $style);
	}

	// Get users email from the xml file
	$pconfig['smartmonemail'] = $config['system']['smartmonemail'];

	$form = new Form();

	$section = new Form_Section('Configuration');

	$section->addInput(new Form_Input(
		'smartmonemail',
		'Email Address',
		'text',
		$pconfig['smartmonemail']
	 ));

	$form->add($section);

	if (!empty($pconfig['smartmonemail'])) {
		$form->addGlobal(new Form_Button(
			'test',
			'Send test email',
			null,
			'fa-send'
		))->addClass('btn-info');
	}

	print($form);

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
