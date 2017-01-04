<?php
/*
	services_checkip_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Rubicon Communications, LLC (Netgate). All rights reserved.
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
##|*IDENT=page-services-checkipedit
##|*NAME=Services: Check IP Service: Edit
##|*DESCR=Allow access to the 'Services: Check IP Service: Edit' page.
##|*MATCH=services_checkip_edit.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['checkipservices']['checkipservice'])) {
	$config['checkipservices']['checkipservice'] = array();
}

$a_checkip = &$config['checkipservices']['checkipservice'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && isset($a_checkip[$id])) {
	$pconfig['enable'] = isset($a_checkip[$id]['enable']);
	$pconfig['name'] = $a_checkip[$id]['name'];
	$pconfig['url'] = $a_checkip[$id]['url'];
	$pconfig['username'] = $a_checkip[$id]['username'];
	$pconfig['password'] = $a_checkip[$id]['password'];
	$pconfig['verifysslpeer'] = isset($a_checkip[$id]['verifysslpeer']);
	$pconfig['descr'] = $a_checkip[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	$reqdfields = array_merge($reqdfields, explode(" ", "name url"));
	$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Name"), gettext("URL")));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['name'] && !is_validaliasname($_POST['name']))) {
		$input_errors[] = gettext("The Check IP Service name contains invalid characters.");
	}
	if (($_POST['url'] && !is_URL($_POST['url']))) {
		$input_errors[] = gettext("The Check IP Service URL is not valid.");
	}
	if ($_POST['passwordfld'] != $_POST['passwordfld_confirm']) {
		$input_errors[] = gettext("Password and confirmed password must match.");
	}

	if (!$input_errors) {
		$checkip = array();
		$checkip['enable'] = $_POST['enable'] ? true : false;
		$checkip['name'] = $_POST['name'];
		$checkip['url'] = $_POST['url'];
		$checkip['username'] = $_POST['username'];

		if ($_POST['passwordfld'] != DMYPWD) {
			$checkip['password'] = $_POST['passwordfld'];
		} else {
			$checkip['password'] = $a_checkip[$id]['password'];;
		}

		$checkip['verifysslpeer'] = $_POST['verifysslpeer'] ? true : false;
		$checkip['descr'] = $_POST['descr'];

		if (isset($id) && $a_checkip[$id]) {
			$a_checkip[$id] = $checkip;
		} else {
			$a_checkip[] = $checkip;
		}

		write_config(gettext("New/Edited Check IP Services entry was posted."));

		header("Location: services_checkip.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("Check IP Services"), gettext("Edit"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section('Check IP Service');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	null,
	$pconfig['enable']
));

$section->addInput(new Form_Input(
	'name',
	'Name',
	'text',
	$pconfig['name']
))->setHelp('The name of the service may only consist of the characters "a-z, A-Z, 0-9 and _".');

$section->addInput(new Form_Input(
	'url',
	'URL',
	'text',
	$pconfig['url']
));

$section->addInput(new Form_Input(
	'username',
	'User name',
	'text',
	$pconfig['username']
));

$section->addPassword(new Form_Input(
	'passwordfld',
	'Password',
	'password',
	$pconfig['password']
));

$section->addInput(new Form_Checkbox(
	'verifysslpeer',
	'Verify SSL Peer',
	'Verify SSL Peer',
	$pconfig['verifysslpeer']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_checkip[$id]) {
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
