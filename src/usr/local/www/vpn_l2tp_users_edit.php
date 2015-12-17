<?php
/*
	vpn_l2tp_users_edit.php
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
##|*IDENT=page-vpn-vpnl2tp-users-edit
##|*NAME=VPN: L2TP: Users: Edit
##|*DESCR=Allow access to the 'VPN: L2TP: Users: Edit' page.
##|*MATCH=vpn_l2tp_users_edit.php*
##|-PRIV

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("User"), gettext("Edit"));
$shortcut_section = "l2tps";

function l2tpusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function l2tp_users_sort() {
	global $config;

	if (!is_array($config['l2tp']['user'])) {
		return;
	}

	usort($config['l2tp']['user'], "l2tpusercmp");
}

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['l2tp']['user'])) {
	$config['l2tp']['user'] = array();
}
$a_secret = &$config['l2tp']['user'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_secret[$id]) {
	$pconfig['usernamefld'] = $a_secret[$id]['name'];
	$pconfig['ip'] = $a_secret[$id]['ip'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($id) && ($a_secret[$id])) {
		$reqdfields = explode(" ", "usernamefld");
		$reqdfieldsn = array(gettext("Username"));
	} else {
		$reqdfields = explode(" ", "usernamefld passwordfld");
		$reqdfieldsn = array(gettext("Username"), gettext("Password"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['usernamefld'])) {
		$input_errors[] = gettext("The username contains invalid characters.");
	}

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['passwordfld'])) {
		$input_errors[] = gettext("The password contains invalid characters.");
	}

	if (($_POST['passwordfld']) && ($_POST['passwordfld'] != $_POST['passwordfld2'])) {
		$input_errors[] = gettext("The passwords do not match.");
	}
	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = gettext("The IP address entered is not valid.");
	}

	if (!$input_errors && !(isset($id) && $a_secret[$id])) {
		/* make sure there are no dupes */
		foreach ($a_secret as $secretent) {
			if ($secretent['name'] == $_POST['usernamefld']) {
				$input_errors[] = gettext("Another entry with the same username already exists.");
				break;
			}
		}
	}

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {

		if (isset($id) && $a_secret[$id]) {
			$secretent = $a_secret[$id];
		}

		$secretent['name'] = $_POST['usernamefld'];
		$secretent['ip'] = $_POST['ip'];

		if ($_POST['passwordfld']) {
			$secretent['password'] = $_POST['passwordfld'];
		}

		if (isset($id) && $a_secret[$id]) {
			$a_secret[$id] = $secretent;
		} else {
			$a_secret[] = $secretent;
		}
		l2tp_users_sort();

		write_config();

		$retval = vpn_l2tp_configure();

		pfSenseHeader("vpn_l2tp_users.php");

		exit;
	}
}

include("head.inc");
?>

<?php
if ($input_errors) {
	print_input_errors($input_errors);
}
?>

<form class="form-horizontal" action="vpn_l2tp_users_edit.php" method="post" name="iform" id="iform">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('User'); ?></h2>
		</div>

		<div class="panel-body">
			<div class="form-group">
				<label for="usernamefld" class="col-sm-2 control-label"><?=gettext("Username")?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="usernamefld" type="text" class="formfld user form-control" id="usernamefld" size="20" value="<?=htmlspecialchars($pconfig['usernamefld'])?>" />
				</div>
			</div>
			<div class="form-group">
				<label for="passwordfld" class="col-sm-2 control-label"><?=gettext("Password")?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="passwordfld" type="password" class="formfld pwd form-control" id="passwordfld" size="20" />
				</div>
			</div>
			<div class="form-group">
				<label for="passwordfld2" class="col-sm-2 control-label"><?=gettext('Confirm')?></label>
				<div class="col-sm-10">
					<?=$mandfldhtml?><input name="passwordfld2" type="password" class="formfld pwd form-control" id="passwordfld2" size="20" />
<?php if (isset($id) && $a_secret[$id]):?>
					<span class="help-block"><?=gettext("If you want to change the users password, enter it here twice.")?></span>
<?php endif?>
				</div>
			</div>
			<div class="form-group">
				<label for="ip" class="col-sm-2 control-label"><?=gettext("IP address")?></label>
				<div class="col-sm-10">
					<input name="ip" type="text" class="formfld unknown form-control" id="ip" size="20" value="<?=htmlspecialchars($pconfig['ip'])?>" />
					<span class="help-block"><?=gettext("If you want the user to be assigned a specific IP address, enter it here.")?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="col-sm-10 col-sm-offset-2">
		<input id="submit" name="Submit" type="submit" class="formbtn btn btn-primary" value="<?=gettext('Save')?>" />
	</div>

<?php if (isset($id) && $a_secret[$id]):?>
	<input name="id" type="hidden" value="<?=htmlspecialchars($id)?>" />
<?php endif?>
</form>

<?php
include("foot.inc");
