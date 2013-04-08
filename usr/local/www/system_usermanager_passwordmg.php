<?php
/* $Id$ */
/*
    Copyright (C) 2011 Ermal Luçi
    system_usermanager.php

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
        pfSense_BUILDER_BINARIES:
        pfSense_MODULE: auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager-passwordmg
##|*NAME=System: User Password Manager page
##|*DESCR=Allow access to the 'System: User Password Manager' page.
##|*MATCH=system_usermanager_passwordmg.php*
##|-PRIV

require_once("certs.inc");
require_once("guiconfig.inc");

$pgtitle = array(gettext("System"),gettext("User Password"));

if (isset($_POST['save'])) {
	unset($input_errors);
	/* input validation */

	$reqdfields = explode(" ", "passwordfld1");
	$reqdfieldsn = array(gettext("Password"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['passwordfld1'] != $_POST['passwordfld2'])
		$input_errors[] = gettext("The passwords do not match.");

	if (!$input_errors) {
		// all values are okay --> saving changes
		$config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]['password'] = crypt(trim($_POST['passwordfld1']));
		local_user_set($config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]);

		write_config();

		$savemsg = gettext("Password successfully changed") . "<br />";
	}
}

if (!session_id())
	session_start();

/* determine if user is not local to system */
$islocal = false;
foreach($config['system']['user'] as $user)
	if($user['name'] == $_SESSION['Username'])
		$islocal = true;

session_commit();

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
include("fbegin.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

if ($islocal == false) {
	echo gettext("Sorry, you cannot change the password for a non-local user.");
	include("fend.inc");
	exit;
}

?>

<div id="mainarea">
        <div class="tabcont">
                <form action="system_usermanager_passwordmg.php" method="post" name="iform" id="iform">
                        <table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
                                <tr>
                                        <td colspan="2" valign="top" class="listtopic"><?=$HTTP_SERVER_VARS['AUTH_USER']?>'s <?=gettext("Password"); ?></td>
                                </tr>
                                <tr>
                                        <td width="22%" valign="top" class="vncell" rowspan="2"><?=gettext("Password"); ?></td>
                                        <td width="78%" class="vtable">
                                                <input name="passwordfld1" type="password" class="formfld pwd" id="passwordfld1" size="20" />
                                        </td>
                                </tr>
                                <tr>
                                        <td width="78%" class="vtable">
                                                <input name="passwordfld2" type="password" class="formfld pwd" id="passwordfld2" size="20" />
                                                &nbsp;<?=gettext("(confirmation)");?>
                                                <br/>
                                                <span class="vexpl">
                                                        <?=gettext("Select a new password");?>
                                                </span>
                                        </td>
                                </tr>
                                <tr>
                                        <td width="22%" valign="top">&nbsp;</td>
                                        <td width="78%">
                                                <input name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
                                        </td>
                                </tr>
                        </table>
                </form>
        </div>
</div>
<?php include("fend.inc");?>
</body>
</html>
