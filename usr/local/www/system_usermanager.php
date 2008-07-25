<?php
/* $Id$ */
/*
    system_usermanager.php
    part of m0n0wall (http://m0n0.ch/wall)

    Copyright (C) 2005 Paul Taylor <paultaylor@winn-dixie.com>.
    All rights reserved.

    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

require("guiconfig.inc");

if (isAllowedPage("system_usermanager")) {

	// start admin user code
	$pgtitle = array("System","User Manager");

	$id = $_GET['id'];
	if (isset($_POST['id']))
		$id = $_POST['id'];

	if (!is_array($config['system']['user'])) 
		$config['system']['user'] = array();

	admin_users_sort();
	if (is_array($config['system']['user'])) 
		$a_user = &$config['system']['user'];
	$t_privs = $a_user[$id]['priv'];

	if ($_GET['act'] == "del") {

		if (($_GET['what'] == "user") && $a_user[$_GET['id']]) {
			del_local_user($a_user[$_GET['id']]);
			$userdeleted = $a_user[$_GET['id']]['name'];
			unset($a_user[$_GET['id']]);
			write_config();
			$retval = system_password_configure();
			$savemsg = gettext("User")." {$userdeleted} ".
						gettext("successfully deleted")."<br/>";
		}

		if (($_GET['what'] == "priv") && $t_privs[$_GET['privid']]) {
 			$privdeleted = $t_privs[$_GET['privid']]['id'];
 			unset($a_user[$id]['priv'][$_GET['privid']]);
			write_config();
			unset($t_privs[$_GET['privid']]);
			$_GET['act'] = "edit";
			$savemsg = gettext("Privilege")." {$privdeleted} ".
						gettext("of user")." {$a_user[$_GET['id']]['name']} ".
						gettext("successfully deleted")."<br/>";
		}
	}

	if ($_GET['act'] == "edit") {
		if (isset($id) && $a_user[$id]) {
			$pconfig['usernamefld'] = $a_user[$id]['name'];
			$pconfig['fullname'] = $a_user[$id]['fullname'];
			$pconfig['groups'] = get_local_user_groups($a_user[$id]);
			$pconfig['utype'] = $a_user[$id]['scope'];
			$pconfig['uid'] = $a_user[$id]['uid'];
			$pconfig['authorizedkeys'] = base64_decode($a_user[$id]['authorizedkeys']);
		}
	}

	if ($_GET['act'] == "new") {
		/*
		 * set this value cause the text field is read only
		 * and the user should not be able to mess with this
		 * setting.
		 */
		$pconfig['utype'] = "user";
	}

	if ($_POST) {
		unset($input_errors);
		$pconfig = $_POST;

		/* input validation */
		if (isset($id) && ($a_user[$id])) {
			$reqdfields = explode(" ", "usernamefld");
			$reqdfieldsn = explode(",", "Username");
		} else {
			$reqdfields = explode(" ", "usernamefld passwordfld1");
			$reqdfieldsn = explode(",", "Username,Password");
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['usernamefld']))
			$input_errors[] = gettext("The username contains invalid characters.");

		if (($_POST['passwordfld1']) && ($_POST['passwordfld1'] != $_POST['passwordfld2']))
			$input_errors[] = gettext("The passwords do not match.");

		/* make sure this user name is unique */
		if (!$input_errors && !(isset($id) && $a_user[$id])) {
			foreach ($a_user as $userent) {
				if ($userent['name'] == $_POST['usernamefld']) {
					$input_errors[] = gettext("Another entry with the same username already exists.");
					break;
				}
			}
		}

		if(is_array($_POST['groups']))
			foreach($_POST['groups'] as $groupname)
				if ($pconfig['utype'] <> "system" && !isset($groupindex[$groupname]))
					$input_errors[] = gettext("group {$groupname} does not exist, please define the group before assigning users.");

        if (isset($config['system']['ssh']['sshdkeyonly']) && empty($_POST['authorizedkeys']))
			$input_errors[] = gettext("You must provide an authorized key otherwise you won't be able to login into this system.");

		/* if this is an AJAX caller then handle via JSON */
		if (isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}

		if (!$input_errors) {
			$userent = array();
			if (isset($id) && $a_user[$id])
				$userent = $a_user[$id];

			/* the user did change his username */
			if ($_POST['usernamefld'] <> $_POST['oldusername'])
				$_SERVER['REMOTE_USER'] = $_POST['usernamefld'];

			$userent['name'] = $_POST['usernamefld'];
			$userent['fullname'] = $_POST['fullname'];

			isset($_POST['utype']) ? $userent['scope'] = $_POST['utype'] : $userent['scope'] = "system";

			if ($_POST['passwordfld1'])
				 set_local_user_password($userent, $_POST['passwordfld1']);

			if(isset($config['system']['ssh']['sshdkeyonly']))
				$userent['authorizedkeys'] = base64_encode($_POST['authorizedkeys']);

			if (isset($id) && $a_user[$id])
				$a_user[$id] = $userent;
			else {
				$userent['uid'] = $config['system']['nextuid']++;
				$a_user[] = $userent;
			}

			set_local_user($userent);
			set_local_user_groups($userent,$_POST['groups']);
			write_config();
			$retval = system_password_configure();

			pfSenseHeader("system_usermanager.php");
		}
	}

	include("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl">
		<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
			$tab_array[] = array(gettext("Group"), false, "system_groupmanager.php");
			$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
			display_top_tabs($tab_array);
		?>
		</td>
	</tr>
	<tr>
		<td class="tabcont">

			<?php if ($_GET['act'] == "new" || $_GET['act'] == "edit" || $input_errors): ?>

			<form action="system_usermanager.php" method="post" name="iform" id="iform">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<?php
						$ro = "";
						if ($pconfig['utype'] == "system")
							$ro = "readonly = \"readonly\"";
					?>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Username");?></td>
						<td width="78%" class="vtable">
							<input name="usernamefld" type="text" class="formfld user" id="usernamefld" size="20" value="<?=htmlspecialchars($pconfig['usernamefld']);?>" <?=$ro;?>/>
							<input name="oldusername" type="hidden" id="oldusername" value="<?=htmlspecialchars($pconfig['usernamefld']);?>" />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq" rowspan="2"><?=gettext("Password");?></td>
						<td width="78%" class="vtable">
							<input name="passwordfld1" type="password" class="formfld pwd" id="passwordfld1" size="20" value="" />
						</td>
					</tr>
					<tr>
						<td width="78%" class="vtable">
							<input name="passwordfld2" type="password" class="formfld pwd" id="passwordfld2" size="20" value="" />&nbsp;<?= gettext("(confirmation)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Full name");?></td>
						<td width="78%" class="vtable">
							<input name="fullname" type="text" class="formfld unknown" id="fullname" size="20" value="<?=htmlspecialchars($pconfig['fullname']);?>" <?=$ro;?>/>
							<br/>
							<?=gettext("User's full name, for your own information only");?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("User type");?></td>
						<td width="78%" class="vtable">
							<input name="utype" type="text" class="formfld unknown" id="utype" size="20" value="<?=htmlspecialchars($pconfig['utype']);?>" readonly="readonly" />
							<br/>
							<?=gettext("Indicates whether this is a system (aka non-deletable) user or a user created by a particular user.");?>
						</td>
					</tr>

					<?php if ($pconfig['uid']): ?>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("User Privileges");?></td>
						<td width="78%" class="vtable">
							<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
								<tr>
									<td width="5%" class="listhdrr"><?=gettext("ID");?></td>
									<td width="30%" class="listhdrr"><?=gettext("Name");?></td>
									<td width="40%" class="listhdrr"><?=gettext("Description");?></td>
									<td width="5%" class="list"></td>
								</tr>
								<?php
									if(is_array($t_privs)):
										$i = 0;
										foreach ($t_privs as $priv):
											if($priv['id'] <> ""):
								?>
								<tr>
									<td class="listlr" <?php if($a_user[$id]['scope'] == "user") echo "ondblclick=\"document.location='system_usermanager_edit.php?id={$i}&userid={$id}&useract={$_GET['act']}';\""; ?>>
										<?=htmlspecialchars($priv['id']);?>
									</td>
									<td class="listr" <?php if($a_user[$id]['scope'] == "user") echo "ondblclick=\"document.location='system_usermanager_edit.php?id={$i}&userid={$id}&useract={$_GET['act']}';\""; ?>>
										<?=htmlspecialchars($priv['name']);?>
									</td>
									<td class="listbg" <?php if($a_user[$id]['scope'] == "user") echo "ondblclick=\"document.location='system_usermanager_edit?id={$i}&userid={$id}&useract={$_GET['act']}';\""; ?>>
										<font color="#FFFFFF"><?=htmlspecialchars($priv['descr']);?>&nbsp;</font>
									</td>
									<td valign="middle" nowrap class="list">
										<?php if($a_user[$id]['scope'] == "user"): ?>
										<table border="0" cellspacing="0" cellpadding="1">
											<tr>
												<td valign="middle">
													<a href="system_usermanager_edit.php?id=<?=$i;?>&userid=<?= $id ?>&useract=<?= $_GET['act'] ?>">
														<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" alt="" />
													</a>
												</td>
												<td valign="middle">
													<a href="system_usermanager.php?act=del&privid=<?=$i;?>&what=priv&id=<?= $id ?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?");?>')">
														<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="" />
													</a>
												</td>
											</tr>
										</table>
										<?php endif; ?>
									</td>
								</tr>
								<?php
											endif;
										$i++;
										endforeach;
									endif;
										if ($a_user[$id]['scope'] == "user"):
								?>
								<tr>
									<td class="list" colspan="3"></td>
									<td class="list">
										<table border="0" cellspacing="0" cellpadding="1">
											<tr>
												<td valign="middle">
													<a href="system_usermanager_edit.php?userid=<?= $id ?>&useract=<?= $_GET['act'] ?>">
														<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="" />
													</a>
												</td>
											</tr>
										</table>
									</td>
								</tr>
								<?php
									endif;
								?>
							</table>
						</td>
					</tr>

					<?php endif; ?>
					<?php if (isset($config['system']['ssh']['sshdkeyonly'])): ?>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Authorized keys");?></td>
						<td width="78%" class="vtable">
							<textarea name="authorizedkeys" cols="65" rows="7" id="authorizedkeys" class="formfld_cert" wrap="off"><?=htmlspecialchars($pconfig['authorizedkeys']);?></textarea>
							<br/>
							<?=gettext("Paste an authorized keys file here.");?>
						</td>
					</tr>

					<?php endif; ?>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Group Memberships");?></td>
						<td width="78%" class="vtable">
							<select size="10" name="groups[]" class="formselect" id="groups" multiple>
								<?php
									foreach ($config['system']['group'] as $group):
										if ($group['gid'] != 1998): /* all users group */
											$selected = "";
											if (in_array($group['name'],$pconfig['groups']))
												$selected = "selected";
								?>
								<option value="<?=$group['name'];?>" <?=$selected;?>>
									<?=htmlspecialchars($group['name']);?>
								</option>
								<?php
										endif;
									endforeach;
								?>
							</select>
							<br/>
							<?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<input id="submit" name="save" type="submit" class="formbtn" value="Save" />
							<?php if (isset($id) && $a_user[$id]): ?>
							<input name="id" type="hidden" value="<?=$id;?>" />
							<?php endif;?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td width="25%" class="listhdrr">Username</td>
					<td width="25%" class="listhdrr">Full name</td>
					<td width="30%" class="listhdrr">Groups</td>
					<td width="10%" class="list"></td>
				</tr>
				<?php
					$i = 0;
					foreach($a_user as $userent):
				?>
				<tr ondblclick="document.location='system_usermanager.php?act=edit&id=<?=$i;?>'">
					<td class="listlr">
						<table border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td align="left" valign="center">
									<?php
										if($userent['scope'] == "user")
											$usrimg = "/themes/{$g['theme']}/images/icons/icon_system-user.png";
										else
											$usrimg = "/themes/{$g['theme']}/images/icons/icon_system-user-grey.png";
									?>
									<img src="<?=$usrimg;?>" alt="User" title="User" border="0" height="16" width="16" />
								</td>
								<td align="left" valign="middle">
									<?=htmlspecialchars($userent['name']);?>
								</td>
							</tr>
						</table>
					</td>
					<td class="listr"><?=htmlspecialchars($userent['fullname']);?>&nbsp;</td>
					<td class="listbg">
						<font color="white">
							<?=implode(",",get_local_user_groups($userent));?>
						</font>
						&nbsp;
					</td>
					<td valign="middle" nowrap class="list">
						<a href="system_usermanager.php?act=edit&id=<?=$i;?>">
							<img src="/themes/<?= $g['theme'];?>/images/icons/icon_e.gif" title="edit user" alt="edit user" width="17" height="17" border="0" />
						</a>
						<?php if($userent['scope'] == "user"): ?>
						&nbsp;
						<a href="system_usermanager.php?act=del&what=user&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this User?");?>')">
							<img src="/themes/<?= $g['theme'];?>/images/icons/icon_x.gif" title="delete user" alt="delete user" width="17" height="17" border="0" />
						</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php
						$i++;
					endforeach;
				?>
				<tr>
					<td class="list" colspan="3"></td>
					<td class="list">
						<a href="system_usermanager.php?act=new">
							<img src="/themes/<?= $g['theme'];?>/images/icons/icon_plus.gif" title="add user" alt="add user" width="17" height="17" border="0" />
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<p>
							<?=gettext("Additional webConfigurator users can be added here.  User permissions are determined by the admin group they are a member of.");?>
						</p>
						<p>
							<?=gettext("An user icon that appears grey indicates that it is a system user and thus it's only possible to modified a subset of the regular user data. Additionally such an user can't be deleted.");?>
						</p>
					</td>
				</tr>
			</table>

			<?php endif; ?>

		</td>
	</tr>
</table>
<?php include("fend.inc");?>
</body>

<?php

	// end admin user code

} else {

	// start normal user code
	$pgtitle = array("System","User Password");

	if (isset($_POST['save'])) {
		unset($input_errors);

		/* input validation */
		$reqdfields = explode(" ", "passwordfld1");
		$reqdfieldsn = explode(",", "Password");

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		if ($_POST['passwordfld1'] != $_POST['passwordfld2'])
			$input_errors[] = "The passwords do not match.";

		if (!$input_errors) {
			// all values are okay --> saving changes
			$config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]['password'] = crypt(trim($_POST['passwordfld1']));

			write_config();

			sync_webgui_passwords();

			$retval = system_password_configure();
			$savemsg = "Password successfully changed<br />";
		}
	}

	/* deterimine if user is not local to system */
	$islocal = false;
	foreach($config['system']['user'] as $user) 
		if($user['name'] == $_SESSION['Username'])
			$islocal = true;
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
    include("head.inc");
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);

	if($islocal == false) {
		echo "Sorry, you cannot change the password for a LDAP user.";
		include("fend.inc");
		exit;
	}
?>
<form action="system_usermanager.php" method="post" name="iform" id="iform">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic"><?=$HTTP_SERVER_VARS['AUTH_USER']?>'s Password</td>
		</tr>
		<tr>
			<td width="22%" valign="top" class="vncell" rowspan="2">Password</td>
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
<?php include("fend.inc");?>
</body>

<?php
} // end of normal user code

/*
 * NOTE : sections of the code below are based on the BSD
 *		  licensed CHAP.php courtesy of Michael Retterklieber.
 */
function set_password_hashes(& $userent, $password) {

	$userent['password'] = crypt($password);
	$userent['md5-hash'] = md5($password);
/*
 *	Waiting for mhash
 *
 *	// Converts ascii to unicode.
 *	$astr = (string) $password;
 *	$ustr = '';
 *	for ($i = 0; $i < strlen($astr); $i++) {
 *		$a = ord($astr{$i}) << 8;
 *		$ustr.= sprintf("%X", $a);
 *	}
 *
 *	// Generate the NT-HASH from the unicode string
 *	$userent['nt-hash'] = bin2hex(mhash(MHASH_MD4, $ustr));
 */
}

?>
