<?php
/*
	services_wol_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
	pfSense_MODULE:	wol
*/

##|+PRIV
##|*IDENT=page-services-wakeonlan-edit
##|*NAME=Services: Wake on LAN: Edit
##|*DESCR=Allow access to the 'Services: Wake on LAN: Edit' page.
##|*MATCH=services_wol_edit.php*
##|-PRIV

function wolcmp($a, $b) {
	return strcmp($a['descr'], $b['descr']);
}

function wol_sort() {
	global $config;

	usort($config['wol']['wolentry'], "wolcmp");
}

require("guiconfig.inc");
if (!is_array($config['wol']['wolentry'])) {
	$config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_wol[$id]) {
	$pconfig['interface'] = $a_wol[$id]['interface'];
	$pconfig['mac'] = $a_wol[$id]['mac'];
	$pconfig['descr'] = $a_wol[$id]['descr'];
} else {
	$pconfig['interface'] = $_GET['if'];
	$pconfig['mac'] = $_GET['mac'];
	$pconfig['descr'] = $_GET['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface mac");
	$reqdfieldsn = array(gettext("Interface"), gettext("MAC address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));

	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}

	if (!$input_errors) {
		$wolent = array();
		$wolent['interface'] = $_POST['interface'];
		$wolent['mac'] = $_POST['mac'];
		$wolent['descr'] = $_POST['descr'];

		if (isset($id) && $a_wol[$id]) {
			$a_wol[$id] = $wolent;
		} else {
			$a_wol[] = $wolent;
		}
		wol_sort();

		write_config();

		header("Location: services_wol.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Wake on LAN"), gettext("Edit"));
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$form = new Form;

if (isset($id) && $a_wol[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Edit WOL entry');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	(link_interface_to_bridge($pconfig['interface']) ? null : $pconfig['interface']),
	get_configured_interface_with_descr()
))->setHelp('Choose which interface this host is connected to.');

$section->addInput(new Form_Input(
	'mac',
	'MAC address',
	'text',
	$pconfig['mac']
))->setHelp(gettext('Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx'));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp(gettext('You may enter a description here for your reference (not parsed).'));

$form->add($section);
print $form;

include("foot.inc");
