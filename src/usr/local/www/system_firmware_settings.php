<?php
/* $Id$ */
/*
	system_firmware_settings.php
	part of pfSense
	Copyright (C) 2005 Colin Smith
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_BUILDER_BINARIES:	/usr/bin/fetch
	pfSense_MODULE: firmware
*/

##|+PRIV
##|*IDENT=page-system-firmware-settings
##|*NAME=System: Firmware: Settings page
##|*DESCR=Allow access to the 'System: Firmware: Settings' page.
##|*MATCH=system_firmware_settings.php*
##|-PRIV

require("guiconfig.inc");

if ($_POST) {
	unset($input_errors);

	/* input validation */
	if (($_POST['alturlenable'] == "yes") && (empty($_POST['firmwareurl']))) {
		$input_errors[] = gettext("A Firmware Auto Update Base URL must be specified when \"Use an unofficial server for firmware upgrades\" is enabled.");
	}

	if (!$input_errors) {
		if ($_POST['alturlenable'] == "yes") {
			$config['system']['firmware']['alturl']['enable'] = true;
			$config['system']['firmware']['alturl']['firmwareurl'] = $_POST['firmwareurl'];
		} else {
			unset($config['system']['firmware']['alturl']['enable']);
			unset($config['system']['firmware']['alturl']['firmwareurl']);
			unset($config['system']['firmware']['alturl']);
			unset($config['system']['firmware']);
		}
		if ($_POST['allowinvalidsig'] == "yes") {
			$config['system']['firmware']['allowinvalidsig'] = true;
		} else {
			unset($config['system']['firmware']['allowinvalidsig']);
		}

		if ($_POST['disablecheck'] == "yes") {
			$config['system']['firmware']['disablecheck'] = true;
		} else {
			unset($config['system']['firmware']['disablecheck']);
		}

		if ($_POST['synconupgrade'] == "yes") {
			$config['system']['gitsync']['synconupgrade'] = true;
		} else {
			unset($config['system']['gitsync']['synconupgrade']);
		}
		$config['system']['gitsync']['repositoryurl'] = $_POST['repositoryurl'];
		$config['system']['gitsync']['branch'] = $_POST['branch'];

		write_config();
	}
}

$curcfg = $config['system']['firmware'];
$gitcfg = $config['system']['gitsync'];

$pgtitle = array(gettext("System"), gettext("Firmware"), gettext("Settings"));
$closehead = false;

exec("/usr/bin/fetch -q -o {$g['tmp_path']}/manifest \"{$g['update_manifest']}\"");
if (file_exists("{$g['tmp_path']}/manifest")) {
	$preset_urls_split = explode("\n", file_get_contents("{$g['tmp_path']}/manifest"));
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

$tab_array = array();
$tab_array[] = array(gettext("Manual Update"), false, "system_firmware.php");
$tab_array[] = array(gettext("Auto Update"), false, "system_firmware_check.php");
$tab_array[] = array(gettext("Updater Settings"), true, "system_firmware_settings.php");

if($g['hidedownloadbackup'] == false)
	$tab_array[] = array(gettext("Restore Full Backup"), false, "system_firmware_restorefullbackup.php");

display_top_tabs($tab_array);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Firmware Branch');

if(is_array($preset_urls_split)) {
	$urllist = array();

	foreach($preset_urls_split as $pus) {
		$pus_text = explode("\t", $pus);
		if (empty($pus_text[0]))
			continue;
		if (stristr($pus_text[0], php_uname("m")) !== false) {
			$yourarch = " (Current architecture)";
			$choice = $pus_text[1];
		} else {
			$yourarch = "";
		}

		$urllist[$pus_text[1]] = $pus_text[0] . $yourarch;
	}

	$section->addInput(new Form_Select(
	   'preseturls',
	   'Default Auto Update URLs',
	   $choice,
	   $urllist
	   ))->setHelp('Entries denoted by "Current architecture" match the architecture of your current installation, ' .
	   'such as %s. Changing architectures during an upgrade is not recommended, and may require a manual reboot after the update completes.', [php_uname("m")]);

	$form->add($section);
}

$section = new Form_Section('Firmware Auto Update URL');

$section->addInput(new Form_Checkbox(
	'alturlenable',
	'Unofficial',
	'Allow the use of an "unofficial" server for firmware upgrades',
	isset($curcfg['alturl']['enable'])
	));

$section->addInput(new Form_Input(
	'firmwareurl',
	'Base URL',
	'text'
	))->setHelp('This is where %s will check for newer firmware versions when the <a href="system_firmware_check.php">' .
				'System: Firmware: Auto Update</a> page is viewed', [$g['product_name']]);

$form->add($section);

$section = new Form_Section('Updates');

$section->addInput(new Form_Checkbox(
	'allowinvalidsig',
	'Unsigned images',
	'Allow auto-update firmware images with a missing or invalid digital signature to be used',
	isset($curcfg['allowinvalidsig'])
	));

$section->addInput(new Form_Checkbox(
	'disablecheck',
	'Dashboard check',
	'Disable the automatic dashboard auto-update check',
	isset($curcfg['disablecheck'])
	));

$form->add($section);

if(file_exists("/usr/local/bin/git") && $g['platform'] == "pfSense") {
	$section = new Form_Section('GitSync');

	$section->addInput(new Form_Checkbox(
		'synconupgrade',
		'Auto sync on update',
		'After updating, sync with the following repository/branch before reboot',
		isset($gitcfg['synconupgrade'])
		))->setHelp('After updating, sync with the following repository/branch before reboot');

	if(is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git config remote.origin.url", $output_str);
		if(is_array($output_str) && !empty($output_str[0]))
			$lastrepositoryurl = $output_str[0];
		unset($output_str);
	}

	$section->addInput(new Form_Input(
		'repositoryurl',
		'Repository URL',
		'text',
		($gitcfg['repositoryurl'] ? $gitcfg['repositoryurl'] : '')
		))->setHelp('The most recently used repository was %s. This repository will be used if the field is left blank.', [$lastrepositoryurl]);

	if(is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git branch", $output_str);
		if(is_array($output_str)) {
			foreach($output_str as $output_line) {
				if(strstr($output_line, '* ')) {
					$lastbranch = substr($output_line, 2);
					break;
				}
			}
			unset($output_str);
		}
		unset($output_str);
	}

	$section->addInput(new Form_Input(
		'branch',
		'Branch name',
		'text',
		($gitcfg['branch'] ? $gitcfg['branch'] : '')
		))->setHelp('The most recently used branch was "%s". (Usually the branch name is master)' .
					'<br />Note: Sync will not be performed if a branch is not specified', [$lastbranch]);

	$form->add($section);
} // e-o-if(file_exista()

print($form);
?>

<script>
// Update firmwareurl from preseturls
function update_firmwareurl() {
	var pre = document.getElementById("preseturls");
	var preVal = pre.options[pre.selectedIndex].value;
	var firm = document.getElementById("firmwareurl");
	firm.value = preVal;
}

// Call it when preseturls changes
events.push(function(){
	$('#preseturls').on('change', function(){
	update_firmwareurl();
	})
});

// And call it on page load
update_firmwareurl();

</script>
<?php

include("foot.inc");