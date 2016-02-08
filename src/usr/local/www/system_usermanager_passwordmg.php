<?php
/*
	system_usermanager_passwordmg.php
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
##|*IDENT=page-system-usermanager-passwordmg
##|*NAME=System: User Password Manager
##|*DESCR=Allow access to the 'System: User Password Manager' page.
##|*MATCH=system_usermanager_passwordmg.php*
##|-PRIV

require_once("auth.inc");
require_once("certs.inc");
require_once("guiconfig.inc");

$pgtitle = array(gettext("System"), gettext("User Password"));

if (isset($_POST['save'])) {
	unset($input_errors);
	/* input validation */

	$reqdfields = explode(" ", "passwordfld1");
	$reqdfieldsn = array(gettext("Password"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['passwordfld1'] != $_POST['passwordfld2']) {
		$input_errors[] = gettext("The passwords do not match.");
	}

	if (!$input_errors) {
		if (!session_id()) {
			session_start();
		}
		// all values are okay --> saving changes

		$userent =& $config['system']['user'][$userindex[$_SESSION['Username']]];
		local_user_set_password($userent, $_POST['passwordfld1']);
		local_user_set($userent);
		unset($userent);
		session_commit();

		write_config();

		$savemsg = gettext("Password successfully changed");
	}
}

if (!session_id()) {
	session_start();
}

/* determine if user is not local to system */
$islocal = false;
foreach ($config['system']['user'] as $user) {
	if ($user['name'] == $_SESSION['Username']) {
		$islocal = true;
	}
}

session_commit();

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if ($islocal == false) {
	echo gettext("Sorry, you cannot change the password for a non-local user.");
	include("foot.inc");
	exit;
}

$form = new Form();

$section = new Form_Section('Update Password');

$section->addInput(new Form_Input(
	'passwordfld1',
	'Password',
	'password'
));

$section->addInput(new Form_Input(
	'passwordfld2',
	'Confirmation',
	'password'
))->setHelp('Select a new password');

$form->add($section);
print($form);

include("foot.inc");
