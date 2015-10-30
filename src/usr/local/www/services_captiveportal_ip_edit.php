<?php
/*
	services_captiveportal_ip_edit.php
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
/*
	pfSense_BUILDER_BINARIES:	/sbin/ipfw
	pfSense_MODULE: captiveportal
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-editallowedips
##|*NAME=Services: Captive portal: Edit Allowed IPs page
##|*DESCR=Allow access to the 'Services: Captive portal: Edit Allowed IPs' page.
##|*MATCH=services_captiveportal_ip_edit.php*
##|-PRIV

function allowedipscmp($a, $b) {
	return strcmp($a['ip'], $b['ip']);
}

function allowedips_sort() {
	global $g, $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['allowedip'], "allowedipscmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$pgtitle = array(gettext("Services"), gettext("Captive portal"), gettext("Edit allowed IP address"));
$shortcut_section = "captiveportal";

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}

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

if (!is_array($config['captiveportal'][$cpzone]['allowedip'])) {
	$config['captiveportal'][$cpzone]['allowedip'] = array();
}
$a_allowedips =& $config['captiveportal'][$cpzone]['allowedip'];

if (isset($id) && $a_allowedips[$id]) {
	$pconfig['ip'] = $a_allowedips[$id]['ip'];
	$pconfig['sn'] = $a_allowedips[$id]['sn'];
	$pconfig['bw_up'] = $a_allowedips[$id]['bw_up'];
	$pconfig['bw_down'] = $a_allowedips[$id]['bw_down'];
	$pconfig['descr'] = $a_allowedips[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "ip sn");
	$reqdfieldsn = array(gettext("Allowed IP address"), gettext("Subnet mask"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['ip'] && !is_ipaddr($_POST['ip'])) {
		$input_errors[] = sprintf(gettext("A valid IP address must be specified. [%s]"), $_POST['ip']);
	}

	if ($_POST['sn'] && (!is_numeric($_POST['sn']) || ($_POST['sn'] < 1) || ($_POST['sn'] > 32))) {
		$input_errors[] = gettext("A valid subnet mask must be specified");
	}

	if ($_POST['bw_up'] && !is_numeric($_POST['bw_up'])) {
		$input_errors[] = gettext("Upload speed needs to be an integer");
	}

	if ($_POST['bw_down'] && !is_numeric($_POST['bw_down'])) {
		$input_errors[] = gettext("Download speed needs to be an integer");
	}

	foreach ($a_allowedips as $ipent) {
		if (isset($id) && ($a_allowedips[$id]) && ($a_allowedips[$id] === $ipent)) {
			continue;
		}

		if ($ipent['ip'] == $_POST['ip']) {
			$input_errors[] = sprintf("[%s] %s.", $_POST['ip'], gettext("already allowed")) ;
			break ;
		}
	}

	if (!$input_errors) {
		$ip = array();
		$ip['ip'] = $_POST['ip'];
		$ip['sn'] = $_POST['sn'];
		$ip['descr'] = $_POST['descr'];
		$ip['dir'] = $_POST['dir'];

		if ($_POST['bw_up']) {
			$ip['bw_up'] = $_POST['bw_up'];
		}

		if ($_POST['bw_down']) {
			$ip['bw_down'] = $_POST['bw_down'];
		}

		if (isset($id) && $a_allowedips[$id]) {
			$oldip = $a_allowedips[$id]['ip'];
			if (!empty($a_allowedips[$id]['sn'])) {
				$oldmask = $a_allowedips[$id]['sn'];
			} else {
				$oldmask = 32;
			}

			$a_allowedips[$id] = $ip;
		} else {
			$a_allowedips[] = $ip;
		}

		allowedips_sort();

		write_config();

		if (isset($a_cp[$cpzone]['enable']) && is_module_loaded("ipfw.ko")) {
			$rules = "";
			$cpzoneid = $a_cp[$cpzone]['zoneid'];
			unset($ipfw);
			if (isset($oldip) && isset($oldmask)) {
				$ipfw = pfSense_ipfw_getTablestats($cpzoneid, IP_FW_TABLE_XLISTENTRY, 3, $oldip);
				$rules .= "table 3 delete {$oldip}/{$oldmask}\n";
				$rules .= "table 4 delete {$oldip}/{$oldmask}\n";
				if (is_array($ipfw)) {
					$rules .= "pipe delete {$ipfw['dnpipe']}\n";
					$rules .= "pipe delete " . ($ipfw['dnpipe']+1 . "\n");
				}
			}

			$rules .= captiveportal_allowedip_configure_entry($ip);
			if (is_array($ipfw)) {
				captiveportal_free_dn_ruleno($ipfw['dnpipe']);
			}

			$uniqid = uniqid("{$cpzone}_allowed");
			@file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
			mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
			@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
		}

		header("Location: services_captiveportal_ip.php?zone={$cpzone}");
		exit;
	}
}

function build_dir_list() {
	$dirs = array(gettext("Both"),gettext("From"),gettext("To"));
	$dirlist = array();

	foreach ($dirs as $dir) {
		$dirlist[strtolower($dir)] = $dir;
	}

	return($dirlist);
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('Edit Captive Portal IP rule');

$section->addInput(new Form_IpAddress(
	'ip',
	'IP Address',
	$pconfig['ip']
))->addMask(sn, $pconfig['sn'], 32);


$section->addInput(new Form_Select(
	'dir',
	'Direction',
	strtolower($pconfig['dir']),
	build_dir_list()
))->setHelp('Use "From" to always allow access to an address through the captive portal (without authentication). ' .
			'Use "To" to allow access from all clients (even non-authenticated ones) behind the portal to this IP.');

$section->addInput(new Form_Input(
	'bw_up',
	'Bandwidth up',
	'text',
	$pconfig['bw_up']
))->setHelp('Enter an upload limit to be enforced on this address in Kbit/s');

$section->addInput(new Form_Input(
	'bw_down',
	'Bandwidth down',
	'text',
	$pconfig['bw_down']
))->setHelp('Enter a download limit to be enforced on this address in Kbit/s');

$section->addInput(new Form_Input(
	'zone',
	null,
	'hidden',
	$cpzone
));

if (isset($id) && $a_allowedips[$id]) {
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
