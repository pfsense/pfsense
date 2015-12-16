<?php
/*
	services_captiveportal_hostname_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-services-captiveportal-editallowedhostnames
##|*NAME=Services: Captive portal: Edit Allowed Hostnames
##|*DESCR=Allow access to the 'Services: Captive portal: Edit Allowed Hostnames' page.
##|*MATCH=services_captiveportal_hostname_edit.php*
##|-PRIV

function allowedhostnamescmp($a, $b) {
	return strcmp($a['hostname'], $b['hostname']);
}

function allowedhostnames_sort() {
	global $g, $config, $cpzone;
	usort($config['captiveportal'][$cpzone]['allowedhostname'], "allowedhostnamescmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone, $cpzoneid;

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), gettext("Edit allowed Hostname"));
$shortcut_section = "captiveportal";

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzoneid = $config['captiveportal'][$cpzone]['zoneid'];

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (!is_array($a_cp[$cpzone]['allowedhostname'])) {
	$a_cp[$cpzone]['allowedhostname'] = array();
}
$a_allowedhostnames = &$a_cp[$cpzone]['allowedhostname'];

if (isset($id) && $a_allowedhostnames[$id]) {
	$pconfig['zone'] = $a_allowedhostnames[$id]['zone'];
	$pconfig['hostname'] = $a_allowedhostnames[$id]['hostname'];
	$pconfig['sn'] = $a_allowedhostnames[$id]['sn'];
	$pconfig['dir'] = $a_allowedhostnames[$id]['dir'];
	$pconfig['bw_up'] = $a_allowedhostnames[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedhostnames[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedhostnames[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "hostname");
	$reqdfieldsn = array(gettext("Allowed Hostname"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['hostname'] && !is_hostname($_POST['hostname']))) {
		$input_errors[] = sprintf(gettext("A valid Hostname must be specified. [%s]"), $_POST['hostname']);
	}

	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}
	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}

	foreach ($a_allowedhostnames as $ipent) {
		if (isset($id) && ($a_allowedhostnames[$id]) && ($a_allowedhostnames[$id] === $ipent)) {
			continue;
		}

		if ($ipent['hostname'] == $_POST['hostname']) {
			$input_errors[] = sprintf("[%s] %s.", $_POST['hostname'], gettext("already allowed")) ;
			break ;
		}
	}

	if (!$input_errors) {
		$ip = array();
		$ip['hostname'] = $_POST['hostname'];
		$ip['sn'] = $_POST['sn'];
		$ip['dir'] = $_POST['dir'];
		$ip['descr'] = $_POST['descr'];
		if ($_POST['bw_up']) {
			$ip['bw_up'] = $_POST['bw_up'];
		}
		if ($_POST['bw_down']) {
			$ip['bw_down'] = $_POST['bw_down'];
		}
		if (isset($id) && $a_allowedhostnames[$id]) {
			$a_allowedhostnames[$id] = $ip;
		} else {
			$a_allowedhostnames[] = $ip;
		}

		allowedhostnames_sort();

		write_config();

		$rules = captiveportal_allowedhostname_configure();
		@file_put_contents("{$g['tmp_path']}/hostname_rules", $rules);
		mwexec("/sbin/ipfw -x {$cpzoneid} {$g['tmp_path']}/hostname_rules", true);
		unset($rules);

		header("Location: services_captiveportal_hostname.php?zone={$cpzone}");
		exit;
	}
}

function build_dir_list() {
	$dirs = array(gettext("Both"), gettext("From"), gettext("To"));
	$dirlist = array();

	foreach ($dirs as $dir) {
		$dirlist[strtolower($dir)] = $dir;
	}

	return($dirlist);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(new Form_Button(
	'Submit',
	gettext("Save")
));

$section = new Form_Section('Captive Portal Hostname settings');

$section->addInput(new Form_Select(
	'dir',
	'Direction',
	strtolower($pconfig['dir']),
	build_dir_list()
))->setHelp('Use "From" to always allow a Hostname through the captive portal (without authentication). ' .
			'Use "To" to allow access from all clients (even non-authenticated ones) behind the portal to this Hostname.');

$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$pconfig['hostname']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter a upload limit to be enforced on this Hostname in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this Hostname in Kbit/s');

$section->addInput(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if (isset($id) && $a_allowedhostnames[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
