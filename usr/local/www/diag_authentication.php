<?php
/*
	diag_authentication.php
	part of the pfSense project	(https://www.pfsense.org)
	Copyright (C) 2010 Ermal Luçi

	All rights reserved.

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
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-diagnostics-authentication
##|*NAME=Diagnostics: Authentication page
##|*DESCR=Allow access to the 'Diagnostics: Authentication' page.
##|*MATCH=diag_authentication.php*
##|-PRIV

require("guiconfig.inc");
require_once("PEAR.inc");
require_once("radius.inc");

if ($_POST) {
	$pconfig = $_POST;
	unset($input_errors);

	$authcfg = auth_get_authserver($_POST['authmode']);
	if (!$authcfg)
		$input_errors[] = $_POST['authmode'] . " " . gettext("is not a valid authentication server");

	if (empty($_POST['username']) || empty($_POST['password']))
		$input_errors[] = gettext("A username and password must be specified.");

	if (!$input_errors) {
		if (authenticate_user($_POST['username'], $_POST['password'], $authcfg)) {
			$savemsg = gettext("User") . ": " . $_POST['username'] . " " . gettext("authenticated successfully.");
			$groups = getUserGroups($_POST['username'], $authcfg);
			$savemsg .= "<br />" . gettext("This user is a member of these groups") . ": <br />";
			foreach ($groups as $group)
				$savemsg .= "{$group} ";
		} else {
			$input_errors[] = gettext("Authentication failed.");
		}
	}
}
$pgtitle = array(gettext("Diagnostics"),gettext("Authentication"));
$shortcut_section = "authentication";
include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000">

<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors);?>
<?php if ($savemsg) print_info_box($savemsg);?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl"></td>
	</tr>
	<tr>
	<td>
	<div id="mainarea">
	<form id="iform" name="iform" action="diag_authentication.php" method="post">
	<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Authentication Server"); ?></td>
		<td width="78%" class="vtable">
			<select name='authmode' id='authmode' class="formselect" >
			<?php
				$auth_servers = auth_get_authserver_list();
				foreach ($auth_servers as $auth_server):
					$selected = "";
					if ($auth_server['name'] == $pconfig['authmode'])
						$selected = "selected";
			?>
			<option value="<?=$auth_server['name'];?>" <?=$selected;?>><?=$auth_server['name'];?></option>
			<?php   endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Username"); ?></td>
		<td width="78%" class="vtable">
			<input class="formfld unknown" size='20' id='username' name='username' value="<?=htmlspecialchars($pconfig['username']);?>" />
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?=gettext("Password"); ?></td>
		<td width="78%" class="vtable">
			<input class="formfld pwd" type='password' size='20' id='password' name='password' value="<?=htmlspecialchars($pconfig['password']);?>" />
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Test");?>" />
		</td>
	</tr>
	</table>
	</td></tr>
</table>

<?php include("fend.inc"); ?>
