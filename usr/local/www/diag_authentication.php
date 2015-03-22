<?php
/*
	diag_authentication.php
	part of the pfSense project	(https://www.pfsense.org)
	Copyright (C) 2010 Ermal Luçi
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

	if (empty($_POST['username']) || empty($_POST['passwordfld']))
		$input_errors[] = gettext("A username and password must be specified.");

	if (!$input_errors) {
		if (authenticate_user($_POST['username'], $_POST['passwordfld'], $authcfg)) {
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
<?php if ($input_errors) print_input_errors($input_errors)?>
<?php if ($savemsg) print_info_box($savemsg)?>
	<div id="container">
		<form class="form-horizontal" action="diag_authentication.php" method="post">
			<div class="form-group">
				<label for="authmode" class="col-sm-2 control-label"><?=gettext("Authentication Server")?></label>
				<div class="col-sm-10">
					<select name="authmode" id="authmode" class="formselect" >
					<?php
							foreach (auth_get_authserver_list() as $auth_server):
								$selected = ($auth_server['name'] == $pconfig['authmode'])
					?>
						<option value="<?=$auth_server['name']?>" <?=($selected?'selected="selected"':'')?>>
							<?=$auth_server['name']?>
						</option>
					<?php   endforeach?>
					</select>
				</div>
			</div>

			<div class="form-group">
				<label for="authmode" class="col-sm-2 control-label"><?=gettext("Username")?></label>
				<div class="col-sm-10">
					<input name="username" value="<?=htmlspecialchars($pconfig['username'])?>" />
				</div>
			</div>

			<div class="form-group">
				<label for="authmode" class="col-sm-2 control-label"><?=gettext("Password")?></label>
				<div class="col-sm-10">
					<input name="password" type="password" value="<?=htmlspecialchars($pconfig['password'])?>" />
				</div>
			</div>

			<button type="submit" class="btn btn-primary"><?=gettext("Test");?></button>
		</form>
	</div>
</div>
<?php include("foot.inc")?>
