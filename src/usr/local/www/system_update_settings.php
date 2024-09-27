<?php
/*
 * system_update_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

	config_init_path('system/firmware');
	if ($_POST['disablecheck'] == "yes") {
		config_set_path('system/firmware/disablecheck', true);
	} elseif (config_path_enabled('system/firmware', 'disablecheck')) {
		config_del_path('system/firmware/disablecheck');
	}

	config_init_path('system/gitsync');
	if ($_POST['synconupgrade'] == "yes") {
		config_set_path('system/gitsync/synconupgrade', true);
	} elseif (config_path_enabled('system/gitsync', 'synconupgrade')) {
		config_del_path('system/gitsync/synconupgrade');
	}

	config_set_path('system/gitsync/repositoryurl', $_POST['repositoryurl']);
	config_set_path('system/gitsync/branch', $_POST['branch']);

	foreach ($repos as $repo) {
		if ($repo['name'] == $_POST['fwbranch']) {
			config_set_path('system/pkg_repo_conf_path', $repo['name']);
			pkg_switch_repo();
			break;
		}
	}

	if ($_POST['minimal'] == "yes") {
		config_set_path('system/gitsync/minimal', true);
	} else {
		config_del_path('system/gitsync/minimal');
	}

	if ($_POST['diff'] == "yes") {
		config_set_path('system/gitsync/diff', true);
	} else {
		config_del_path('system/gitsync/diff');
	}

	if ($_POST['show_files'] == "yes") {
		config_set_path('system/gitsync/show_files', true);
	} else {
		config_del_path('system/gitsync/show_files');
	}

	if ($_POST['show_command'] == "yes") {
		config_set_path('system/gitsync/show_command', true);
	} else {
		config_del_path('system/gitsync/show_command');
	}

	if ($_POST['dryrun'] == "yes") {
		config_set_path('system/gitsync/dryrun', true);
	} else {
		config_del_path('system/gitsync/dryrun');
	}

	if (empty(config_get_path('system/firmware'))) {
		config_del_path('system/firmware');
	}
	if (empty(config_get_path('system/gitsync'))) {
		config_del_path('system/gitsync');
	}
	write_config(gettext("Saved system update settings."));

	$savemsg = gettext("Changes have been saved successfully");
}

$curcfg = config_get_path('system/firmware');
$gitcfg = config_get_path('system/gitsync');

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
$repos = update_repos();
$helpfilename = pkg_get_repo_help();

$form = new Form();

$section = new Form_Section(gettext('Firmware Branch'));

$field = new Form_Select(
	'fwbranch',
	'*'.gettext('Branch'),
	pkg_get_repo_name(config_get_path('system/pkg_repo_conf_path')),
	pkg_build_repo_list()
);

if (file_exists($helpfilename)) {
	$field->setHelp(file_get_contents($helpfilename));
} else {
	$field->setHelp(gettext('Please select the branch from which to update the system firmware. %1$s' .
					'Use of the development version is at your own risk!'), '<br />');
}

$section->addInput($field);

$form->add($section);

$section = new Form_Section(gettext('Update Settings'));
$section->addInput(new Form_Checkbox(
	'disablecheck',
	gettext('Dashboard Check'),
	gettext('Disable the dashboard auto-update check.'),
	isset($curcfg['disablecheck'])
));
$form->add($section);

if (file_exists("/usr/local/bin/git")) {
	$section = new Form_Section('GitSync');

	$section->addInput(new Form_Checkbox(
		'synconupgrade',
		gettext('Auto sync on update'),
		gettext('Enable repository/branch sync before reboot'),
		isset($gitcfg['synconupgrade'])
		))->setHelp(gettext('After updating, sync with the following repository/branch before reboot.'));

	if (is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && /usr/local/bin/git config remote.origin.url", $output_str);
		if (is_array($output_str) && !empty($output_str[0])) {
			$lastrepositoryurl = $output_str[0];
		}
		unset($output_str);
	}

	$section->addInput(new Form_Input(
		'repositoryurl',
		gettext('Repository URL'),
		'text',
		($gitcfg['repositoryurl'] ? $gitcfg['repositoryurl'] : '')
		))->setHelp(gettext('The most recently used repository was %s. This repository will be used if the field is left blank.'), $lastrepositoryurl);

	if (is_dir("/root/pfsense/pfSenseGITREPO/pfSenseGITREPO")) {
		exec("cd /root/pfsense/pfSenseGITREPO/pfSenseGITREPO && /usr/local/bin/git branch", $output_str);
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
		gettext('Branch Name'),
		'text',
		($gitcfg['branch'] ? $gitcfg['branch'] : '')
		))->setHelp(gettext('The most recently used branch was "%1$s". (Usually the branch name is plus-master)' .
					'%2$sNote: Sync will not be performed if a branch is not specified.'), $lastbranch, '<br />');

	$group = new Form_Group(gettext('Sync Options'));

	$group->add(new Form_Checkbox(
		'minimal',
		null,
		gettext('Minimal'),
		isset($gitcfg['minimal'])
		))->setHelp(gettext('Copy of only the updated files.'));

	$group->add(new Form_Checkbox(
		'diff',
		null,
		gettext('Diff'),
		isset($gitcfg['diff'])
		))->setHelp(gettext('Copy of only the different or missing files.'));

	$group->add(new Form_Checkbox(
		'show_files',
		null,
		gettext('Show Files'),
		isset($gitcfg['show_files'])
		))->setHelp(gettext('Show different and missing files.%1$sWith \'Diff/Minimal\' option.'), '<br />');

	$group->add(new Form_Checkbox(
		'show_command',
		null,
		gettext('Show Command'),
		isset($gitcfg['show_command'])
		))->setHelp(gettext('Show constructed command.%1$sWith \'Diff/Minimal\' option.'), '<br />');

	$group->add(new Form_Checkbox(
		'dryrun',
		null,
		gettext('Dry Run'),
		isset($gitcfg['dryrun'])
		))->setHelp(gettext('Dry-run only.%1$sNo files copied.'), '<br />');

	$group->setHelp(gettext('See "playback gitsync --help" in console "PHP Shell + %s tools" for additional information.'), g_get('product_label'));
	$section->add($group);

	$form->add($section);
} // e-o-if (file_exists())

print($form);

include("foot.inc");
