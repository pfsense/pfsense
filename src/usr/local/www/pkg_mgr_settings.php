<?php
/* $Id$ */
/*
	pkg_mgr_settings.php
	part of pfSense
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
	Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2005 Colin Smith

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
	pfSense_MODULE: pkgs
*/

##|+PRIV
##|*IDENT=page-pkg-mgr-settings
##|*NAME=Packages: Settings page
##|*DESCR=Allow access to the 'Packages: Settings' page.
##|*MATCH=pkg_mgr_settings.php*
##|-PRIV

ini_set('max_execution_time', '0');

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pkg-utils.inc");

if ($_POST) {
	if (!$input_errors) {
		if ($_POST['alturlenable'] == "yes") {
			$config['system']['altpkgrepo']['enable'] = true;
			$config['system']['altpkgrepo']['xmlrpcbaseurl'] = $_POST['pkgrepourl'];
		} else {
			unset($config['system']['altpkgrepo']['enable']);
		}
		write_config();
	}

	write_config();
}

$curcfg = $config['system']['altpkgrepo'];
$closehead = false;
$pgtitle = array(gettext("System"), gettext("Package Settings"));
include("head.inc");

// Print package server mismatch warning. See https://redmine.pfsense.org/issues/484
if (!verify_all_package_servers())
	print_info_box(package_server_mismatch_message());

// Print package server SSL warning. See https://redmine.pfsense.org/issues/484
if (check_package_server_ssl() === false)
	print_info_box(package_server_ssl_failure_message());

if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(sprintf(gettext("%s packages"), $g['product_version']), false, "pkg_mgr.php");
$tab_array[] = array(gettext("Installed Packages"), false, "pkg_mgr_installed.php");
$tab_array[] = array(gettext("Package Settings"), true, "pkg_mgr_settings.php");
display_top_tabs($tab_array);

print_info_box(gettext('This page allows an alternate package repository to be configured, primarily for temporary use as a testing mechanism.' .
					   'The contents of unofficial packages servers cannot be verified and may contain malicious files.' .
					   'The package server settings should remain at their default values to ensure that verifiable and trusted packages are recevied.' .
					   'A warning is printed on the Dashboard and in the package manager when an unofficial package server is in use.'), 'default');

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Alternate package repository');

$section->addInput(new Form_Checkbox(
	'alturlenable',
	'Enable Alternate',
	'Use a non-official server for packages',
	$curcfg['enable']
))->toggles('.form-group:not(:first-child)');

$section->addInput(new Form_Input(
	'pkgrepourl',
	'Package Repository URL',
	'text',
	$curcfg['xmlrpcbaseurl'] ? $curcfg['xmlrpcbaseurl'] : $g['']
))->setHelp(sprintf("This is where %s will check for packages when the",$g['product_name']) .
			'<a href="pkg_mgr.php">' . ' ' . 'System: Packages' . ' </a>' . 'page is viewed.');

$form->add($section);
print($form);

include("foot.inc");
