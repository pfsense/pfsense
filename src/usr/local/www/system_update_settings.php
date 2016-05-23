<?php
/*
	system_update_settings.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Colin Smith
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-system-update-settings
##|*NAME=System: Update: Settings
##|*DESCR=Allow access to the 'System: Update: Settings' page.
##|*MATCH=system_update_settings.php*
##|-PRIV

require("guiconfig.inc");
require("pkg-utils.inc");

$repos = pkg_list_repos();

if ($_POST) {

	// Set the firmware branch, but only if we are not using it already
	if ($_POST['fwbranch']) {
		if (($_POST['fwbranch'] == "development") && !is_pkg_installed($g['product_name'] . "-repo-devel")) {
			pkg_switch_repo(true);
		} else if (($_POST['fwbranch'] == "stable") && !is_pkg_installed($g['product_name'] . "-repo")) {
			pkg_switch_repo(false);
		}
	}

	if ($_POST['disablecheck'] == "yes") {
		$config['system']['firmware']['disablecheck'] = true;
	} elseif (isset($config['system']['firmware']['disablecheck'])) {
		unset($config['system']['firmware']['disablecheck']);
	}

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

	write_config();

	$savemsg = gettext("Changes have been saved successfully");
}

$curcfg = $config['system']['firmware'];
$gitcfg = $config['system']['gitsync'];

$pgtitle = array(gettext("System"), gettext("Update"), gettext("Update Settings"));

exec("/usr/bin/fetch -q -o {$g['tmp_path']}/manifest \"{$g['update_manifest']}\"");
if (file_exists("{$g['tmp_path']}/manifest")) {
	$preset_urls_split = explode("\n", file_get_contents("{$g['tmp_path']}/manifest"));
}

// Create an array of repo names and descriptions to populate the "Branch" selector
function build_repo_list() {
	global $repos;

	$list = array();

	foreach ($repos as $repo) {
		$list[$repo['name']] = $repo['descr'];
	}

	return($list);
}

function get_repo_name($path) {
	global $repos;

	foreach ($repos as $repo) {
		if ($repo['path'] == $path) {
			return $repo['name'];
		}
	}

	/* Default */
	return $repos[0]['name'];
}

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

$form = new Form();

$section = new Form_Section('Firmware Branch');

$section->addInput(new Form_Select(
	fwbranch,
	'Branch',
	get_repo_name($config['system']['pkg_repo_conf_path']),
	build_repo_list()
))->setHelp('Please select the stable, or the development branch from which to update the system firmware. ' . ' <br />' .
			'Use of the development version is at your own risk!');

$form->add($section);

$section = new Form_Section('Updates');

$section->addInput(new Form_Checkbox(
	'disablecheck',
	'Dashboard check',
	'Disable the automatic dashboard auto-update check',
	isset($curcfg['disablecheck'])
	));

$form->add($section);

if (file_exists("/usr/local/bin/git") && $g['platform'] == $g['product_name']) {
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
		))->setHelp('The most recently used repository was %s. This repository will be used if the field is left blank.', [$lastrepositoryurl]);

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
		))->setHelp('The most recently used branch was "%s". (Usually the branch name is master)' .
					'<br />Note: Sync will not be performed if a branch is not specified.', [$lastbranch]);

	$form->add($section);
} // e-o-if(file_exista()

print($form);

include("foot.inc");
