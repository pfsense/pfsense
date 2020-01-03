<?php
/*
 * system_update_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
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
##|*IDENT=page-system-update-settings
##|*NAME=System: Update: Settings
##|*DESCR=Allow access to the 'System: Update: Settings' page.
##|*MATCH=system_update_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

$repos = pkg_list_repos();

if ($_POST) {

	init_config_arr(array('system', 'firmware'));
	if ($_POST['disablecheck'] == "yes") {
		$config['system']['firmware']['disablecheck'] = true;
	} elseif (isset($config['system']['firmware']['disablecheck'])) {
		unset($config['system']['firmware']['disablecheck']);
	}

	init_config_arr(array('system', 'gitsync'));
	if ($_POST['synconupgrade'] == "yes") {
		$config['system']['gitsync']['synconupgrade'] = true;
	} elseif (isset($config['system']['gitsync']['synconupgrade'])) {
		unset($config['system']['gitsync']['synconupgrade']);
	}

	$config['system']['gitsync']['repositoryurl'] = $_POST['repositoryurl'];
	$config['system']['gitsync']['branch'] = $_POST['branch'];

	foreach ($repos as $repo) {
		if ($repo['name'] == $_POST['fwbranch']) {
			$config['system']['pkg_repo_conf_path'] = $repo['path'];
			pkg_switch_repo($repo['path']);
			break;
		}
	}

	if ($_POST['minimal'] == "yes") {
		$config['system']['gitsync']['minimal'] = true;
	} else {
		unset($config['system']['gitsync']['minimal']);
	}

	if ($_POST['diff'] == "yes") {
		$config['system']['gitsync']['diff'] = true;
	} else {
		unset($config['system']['gitsync']['diff']);
	}

	if ($_POST['show_files'] == "yes") {
		$config['system']['gitsync']['show_files'] = true;
	} else {
		unset($config['system']['gitsync']['show_files']);
	}

	if ($_POST['show_command'] == "yes") {
		$config['system']['gitsync']['show_command'] = true;
	} else {
		unset($config['system']['gitsync']['show_command']);
	}

	if ($_POST['dryrun'] == "yes") {
		$config['system']['gitsync']['dryrun'] = true;
	} else {
		unset($config['system']['gitsync']['dryrun']);
	}

	if (empty($config['system']['firmware'])) {
		unset($config['system']['firmware']);
	}
	if (empty($config['system']['gitsync'])) {
		unset($config['system']['gitsync']);
	}
	write_config(gettext("Saved system update settings."));

	$savemsg = gettext("Changes have been saved successfully");
}

$curcfg = $config['system']['firmware'];
$gitcfg = $config['system']['gitsync'];

$pgtitle = array(gettext("System"), gettext("Update"), gettext("Update Settings"));
$pglinks = array("", "pkg_mgr_install.php?id=firmware", "@self");

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("System Update"), false, "pkg_mgr_install.php?id=firmware");
$tab_array[] = array(gettext("Update Settings"), true, "system_update_settings.php");
display_top_tabs($tab_array);

// Check to see if any new repositories have become available. This data is cached and
// refreshed every 24 hours
update_repos();
$repopath = "/usr/local/share/{$g['product_name']}/pkg/repos";
$helpfilename = "{$repopath}/{$g['product_name']}-repo-custom.help";

$form = new Form();

$section = new Form_Section('Firmware Branch');

$field = new Form_Select(
	'fwbranch',
	'*Branch',
	pkg_get_repo_name($config['system']['pkg_repo_conf_path']),
	pkg_build_repo_list()
);

if (file_exists($helpfilename)) {
	$field->setHelp(file_get_contents($helpfilename));
} else {
	$field->setHelp('Please select the branch from which to update the system firmware. %1$s' .
					'Use of the development version is at your own risk!', '<br />');
}

$section->addInput($field);

$form->add($section);

$section = new Form_Section('Updates');

$section->addInput(new Form_Checkbox(
	'disablecheck',
	'Dashboard check',
	'Disable the Dashboard auto-update check',
	isset($curcfg['disablecheck'])
	));

$form->add($section);

if (file_exists("/usr/local/bin/git")) {
	$section = new Form_Section('GitSync');

	$section->addInput(new Form_Checkbox(
		'synconupgrade',
		'Auto sync on update',
		'Enable repository/branch sync before reboot',
		isset($gitcfg['synconupgrade'])
		))->setHelp('After updating, sync with the following repository/branch before reboot.');

	if (is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git config remote.origin.url", $output_str);
		if (is_array($output_str) && !empty($output_str[0])) {
			$lastrepositoryurl = $output_str[0];
		}
		unset($output_str);
	}

	$section->addInput(new Form_Input(
		'repositoryurl',
		'Repository URL',
		'text',
		($gitcfg['repositoryurl'] ? $gitcfg['repositoryurl'] : '')
		))->setHelp('The most recently used repository was %s. This repository will be used if the field is left blank.', $lastrepositoryurl);

	if (is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && git branch", $output_str);
		if (is_array($output_str)) {
			foreach ($output_str as $output_line) {
				if (strstr($output_line, '* ')) {
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
		))->setHelp('The most recently used branch was "%1$s". (Usually the branch name is master)' .
					'%2$sNote: Sync will not be performed if a branch is not specified.', $lastbranch, '<br />');

	$group = new Form_Group('Sync options');

	$group->add(new Form_Checkbox(
		'minimal',
		null,
		'Minimal',
		isset($gitcfg['minimal'])
		))->setHelp('Copy of only the updated files.');

	$group->add(new Form_Checkbox(
		'diff',
		null,
		'Diff',
		isset($gitcfg['diff'])
		))->setHelp('Copy of only the different or missing files.');

	$group->add(new Form_Checkbox(
		'show_files',
		null,
		'Show Files',
		isset($gitcfg['show_files'])
		))->setHelp('Show different and missing files.%1$sWith \'Diff/Minimal\' option.', '<br />');

	$group->add(new Form_Checkbox(
		'show_command',
		null,
		'Show Command',
		isset($gitcfg['show_command'])
		))->setHelp('Show constructed command.%1$sWith \'Diff/Minimal\' option.', '<br />');

	$group->add(new Form_Checkbox(
		'dryrun',
		null,
		'Dry Run',
		isset($gitcfg['dryrun'])
		))->setHelp('Dry-run only.%1$sNo files copied.', '<br />');

	$group->setHelp('See "playback gitsync --help" in console "PHP Shell + pfSense tools" for additional information.');
	$section->add($group);

	$form->add($section);
} // e-o-if (file_exists())

print($form);

include("foot.inc");
