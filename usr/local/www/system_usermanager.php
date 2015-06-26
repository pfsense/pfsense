<?php
/* $Id$ */
/*
	system_usermanager.php
	part of m0n0wall (http://m0n0.ch/wall)

	part of pfSense
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	Copyright (C) 2008 Shrew Soft Inc.
	All rights reserved.

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
/*
	pfSense_BUILDER_BINARIES:
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager
##|*NAME=System: User Manager page
##|*DESCR=Allow access to the 'System: User Manager' page.
##|*MATCH=system_usermanager.php*
##|-PRIV

require("certs.inc");
require("guiconfig.inc");

// start admin user code
$pgtitle = array(gettext("System"), gettext("User Manager"));

if (isset($_POST['userid']) && is_numericint($_POST['userid'])) {
	$id = $_POST['userid'];
}

if (!isset($config['system']['user']) || !is_array($config['system']['user'])) {
	$config['system']['user'] = array();
}

$a_user = &$config['system']['user'];

if (isset($_SERVER['HTTP_REFERER'])) {
	$referer = $_SERVER['HTTP_REFERER'];
} else {
	$referer = '/system_usermanager.php';
}

if (isset($id) && $a_user[$id]) {
	$pconfig['usernamefld'] = $a_user[$id]['name'];
	$pconfig['descr'] = $a_user[$id]['descr'];
	$pconfig['expires'] = $a_user[$id]['expires'];
	$pconfig['groups'] = local_user_get_groups($a_user[$id]);
	$pconfig['utype'] = $a_user[$id]['scope'];
	$pconfig['uid'] = $a_user[$id]['uid'];
	$pconfig['authorizedkeys'] = base64_decode($a_user[$id]['authorizedkeys']);
	$pconfig['priv'] = $a_user[$id]['priv'];
	$pconfig['ipsecpsk'] = $a_user[$id]['ipsecpsk'];
	$pconfig['disabled'] = isset($a_user[$id]['disabled']);
}

if ($_POST['act'] == "deluser") {

	if (!isset($_POST['username']) || !isset($a_user[$id]) || ($_POST['username'] != $a_user[$id]['name'])) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	conf_mount_rw();
	local_user_del($a_user[$id]);
	conf_mount_ro();
	$userdeleted = $a_user[$id]['name'];
	unset($a_user[$id]);
	write_config();
	$savemsg = gettext("User") . " {$userdeleted} " . gettext("successfully deleted") . "<br />";
} else if ($_POST['act'] == "delpriv") {

	if (!$a_user[$id]) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	$privdeleted = $priv_list[$a_user[$id]['priv'][$_POST['privid']]]['name'];
	unset($a_user[$id]['priv'][$_POST['privid']]);
	local_user_set($a_user[$id]);
	write_config();
	$_POST['act'] = "edit";
	$savemsg = gettext("Privilege") . " {$privdeleted} " . gettext("successfully deleted"). "<br />";
} else if ($_POST['act'] == "expcert") {

	if (!$a_user[$id]) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	$cert =& lookup_cert($a_user[$id]['cert'][$_POST['certid']]);

	$exp_name = urlencode("{$a_user[$id]['name']}-{$cert['descr']}.crt");
	$exp_data = base64_decode($cert['crt']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
} else if ($_POST['act'] == "expckey") {

	if (!$a_user[$id]) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	$cert =& lookup_cert($a_user[$id]['cert'][$_POST['certid']]);

	$exp_name = urlencode("{$a_user[$id]['name']}-{$cert['descr']}.key");
	$exp_data = base64_decode($cert['prv']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
} else if ($_POST['act'] == "delcert") {

	if (!$a_user[$id]) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	$certdeleted = lookup_cert($a_user[$id]['cert'][$_POST['certid']]);
	$certdeleted = $certdeleted['descr'];
	unset($a_user[$id]['cert'][$_POST['certid']]);
	write_config();
	$_POST['act'] = "edit";
	$savemsg = gettext("Certificate") . " {$certdeleted} " . gettext("association removed.") . "<br />";
} else if ($_POST['act'] == "new") {
	/*
	 * set this value cause the text field is read only
	 * and the user should not be able to mess with this
	 * setting.
	 */
	$pconfig['utype'] = "user";
	$pconfig['lifetime'] = 3650;
}

if (isset($_POST['dellall_x'])) {

	$del_users = $_POST['delete_check'];

	if (!empty($del_users)) {
		foreach ($del_users as $userid) {
			if (isset($a_user[$userid]) && $a_user[$userid]['scope'] != "system") {
				conf_mount_rw();
				local_user_del($a_user[$userid]);
				conf_mount_ro();
				unset($a_user[$userid]);
			}
		}
		$savemsg = gettext("Selected users removed successfully!");
		write_config($savemsg);
	}
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($id) && ($a_user[$id])) {
		$reqdfields = explode(" ", "usernamefld");
		$reqdfieldsn = array(gettext("Username"));
	} else {
		if (empty($_POST['name'])) {
			$reqdfields = explode(" ", "usernamefld passwordfld1");
			$reqdfieldsn = array(
				gettext("Username"),
				gettext("Password"));
		} else {
			$reqdfields = explode(" ", "usernamefld passwordfld1 name caref keylen lifetime");
			$reqdfieldsn = array(
				gettext("Username"),
				gettext("Password"),
				gettext("Descriptive name"),
				gettext("Certificate authority"),
				gettext("Key length"),
				gettext("Lifetime"));
		}
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['usernamefld'])) {
		$input_errors[] = gettext("The username contains invalid characters.");
	}

	if (strlen($_POST['usernamefld']) > 16) {
		$input_errors[] = gettext("The username is longer than 16 characters.");
	}

	if (($_POST['passwordfld1']) && ($_POST['passwordfld1'] != $_POST['passwordfld2'])) {
		$input_errors[] = gettext("The passwords do not match.");
	}

	if (isset($_POST['ipsecpsk']) && !preg_match('/^[[:ascii:]]*$/', $_POST['ipsecpsk'])) {
		$input_errors[] = gettext("IPsec Pre-Shared Key contains invalid characters.");
	}

	if (isset($id) && $a_user[$id]) {
		$oldusername = $a_user[$id]['name'];
	} else {
		$oldusername = "";
	}
	/* make sure this user name is unique */
	if (!$input_errors) {
		foreach ($a_user as $userent) {
			if ($userent['name'] == $_POST['usernamefld'] && $oldusername != $_POST['usernamefld']) {
				$input_errors[] = gettext("Another entry with the same username already exists.");
				break;
			}
		}
	}
	/* also make sure it is not reserved */
	if (!$input_errors) {
		$system_users = explode("\n", file_get_contents("/etc/passwd"));
		foreach ($system_users as $s_user) {
			$ent = explode(":", $s_user);
			if ($ent[0] == $_POST['usernamefld'] && $oldusername != $_POST['usernamefld']) {
				$input_errors[] = gettext("That username is reserved by the system.");
				break;
			}
		}
	}

	/*
	 * Check for a valid expiration date if one is set at all (valid means,
	 * DateTime puts out a time stamp so any DateTime compatible time
	 * format may be used. to keep it simple for the enduser, we only
	 * claim to accept MM/DD/YYYY as inputs. Advanced users may use inputs
	 * like "+1 day", which will be converted to MM/DD/YYYY based on "now".
	 * Otherwise such an entry would lead to an invalid expiration data.
	 */
	if ($_POST['expires']) {
		try {
			$expdate = new DateTime($_POST['expires']);
			//convert from any DateTime compatible date to MM/DD/YYYY
			$_POST['expires'] = $expdate->format("m/d/Y");
		} catch (Exception $ex) {
			$input_errors[] = gettext("Invalid expiration date format; use MM/DD/YYYY instead.");
		}
	}

	if (!empty($_POST['name'])) {
		$ca = lookup_ca($_POST['caref']);
		if (!$ca) {
			$input_errors[] = gettext("Invalid internal Certificate Authority") . "\n";
		}
	}

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if (!$input_errors) {
		conf_mount_rw();
		$userent = array();
		if (isset($id) && $a_user[$id]) {
			$userent = $a_user[$id];
		}

		isset($_POST['utype']) ? $userent['scope'] = $_POST['utype'] : $userent['scope'] = "system";

		/* the user name was modified */
		if (!empty($_POST['oldusername']) && ($_POST['usernamefld'] <> $_POST['oldusername'])) {
			$_SERVER['REMOTE_USER'] = $_POST['usernamefld'];
			local_user_del($userent);
		}

		/* the user password was modified */
		if ($_POST['passwordfld1']) {
			local_user_set_password($userent, $_POST['passwordfld1']);
		}

		$userent['name'] = $_POST['usernamefld'];
		$userent['descr'] = $_POST['descr'];
		$userent['expires'] = $_POST['expires'];
		$userent['authorizedkeys'] = base64_encode($_POST['authorizedkeys']);
		$userent['ipsecpsk'] = $_POST['ipsecpsk'];

		if ($_POST['disabled']) {
			$userent['disabled'] = true;
		} else {
			unset($userent['disabled']);
		}

		if (isset($id) && $a_user[$id]) {
			$a_user[$id] = $userent;
		} else {
			if (!empty($_POST['name'])) {
				$cert = array();
				$cert['refid'] = uniqid();
				$userent['cert'] = array();

				$cert['descr'] = $_POST['name'];

				$subject = cert_get_subject_array($ca['crt']);

				$dn = array(
					'countryName' => $subject[0]['v'],
					'stateOrProvinceName' => $subject[1]['v'],
					'localityName' => $subject[2]['v'],
					'organizationName' => $subject[3]['v'],
					'emailAddress' => $subject[4]['v'],
					'commonName' => $userent['name']);

				cert_create($cert, $_POST['caref'], $_POST['keylen'],
					(int)$_POST['lifetime'], $dn);

				if (!is_array($config['cert'])) {
					$config['cert'] = array();
				}
				$config['cert'][] = $cert;
				$userent['cert'][] = $cert['refid'];
			}
			$userent['uid'] = $config['system']['nextuid']++;
			/* Add the user to All Users group. */
			foreach ($config['system']['group'] as $gidx => $group) {
				if ($group['name'] == "all") {
					if (!is_array($config['system']['group'][$gidx]['member'])) {
						$config['system']['group'][$gidx]['member'] = array();
					}
					$config['system']['group'][$gidx]['member'][] = $userent['uid'];
					break;
				}
			}

			$a_user[] = $userent;
		}

		local_user_set($userent);
		local_user_set_groups($userent, $_POST['groups']);
		write_config();

		if (is_dir("/etc/inc/privhooks")) {
			run_plugins("/etc/inc/privhooks");
		}

		conf_mount_ro();

		pfSenseHeader("system_usermanager.php");
	}
}

$closehead = false;
include("head.inc");
?>

<link rel="stylesheet" type="text/css" href="/javascript/jquery-ui-timepicker-addon/css/jquery-ui-timepicker-addon.css" />
<link rel="stylesheet" type="text/css" href="/javascript/jquery/jquery-ui-1.11.1.css" />

<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<script type="text/javascript">
//<![CDATA[
	jQuery(function() {
		jQuery( "#expires" ).datepicker( { dateFormat: 'mm/dd/yy', changeYear: true, yearRange: "+0:+100" } );
	});
//]]>
</script>
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

function setall_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++) {
		selbox.options[index].selected = true;
	}
}

function delete_empty(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++) {
		if (selbox.options[index].value == '') {
			selbox.remove(index);
		}
	}
}

function clear_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++) {
		selbox.options[index].selected = false;
	}
}

function remove_selected(id) {
	selbox = document.getElementById(id);
	index = selbox.options.length - 1;
	for (; index >= 0; index--) {
		if (selbox.options[index].selected) {
			selbox.remove(index);
		}
	}
}

function copy_selected(srcid, dstid) {
	src_selbox = document.getElementById(srcid);
	dst_selbox = document.getElementById(dstid);
	count = dst_selbox.options.length;
	for (index = count - 1; index >= 0; index--) {
		if (dst_selbox.options[index].value == '') {
			dst_selbox.remove(index);
		}
	}
	count = src_selbox.options.length;
	for (index = 0; index < count; index++) {
		if (src_selbox.options[index].selected) {
			option = document.createElement('option');
			option.text = src_selbox.options[index].text;
			option.value = src_selbox.options[index].value;
			dst_selbox.add(option, null);
		}
	}
}

function move_selected(srcid, dstid) {
	copy_selected(srcid, dstid);
	remove_selected(srcid);
}

function presubmit() {
	delete_empty('groups');
	delete_empty('notgroups');
	clear_selected('notgroups');
	setall_selected('groups');
}

function usercertClicked(obj) {
	if (obj.checked) {
		document.getElementById("usercertchck").style.display="none";
		document.getElementById("usercert").style.display="";
	} else {
		document.getElementById("usercert").style.display="none";
		document.getElementById("usercertchck").style.display="";
	}
}

function sshkeyClicked(obj) {
	if (obj.checked) {
		document.getElementById("sshkeychck").style.display="none";
		document.getElementById("sshkey").style.display="";
	} else {
		document.getElementById("sshkey").style.display="none";
		document.getElementById("sshkeychck").style.display="";
	}
}
//]]>
</script>
<?php
	if ($input_errors) {
		print_input_errors($input_errors);
	}
	if ($savemsg) {
		print_info_box($savemsg);
	}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="user manager">
	<tr>
		<td>
<?php
			$tab_array = array();
			$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
			$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
			$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
			$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
			display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
<?php
			if ($_POST['act'] == "new" || $_POST['act'] == "edit" || $input_errors):
?>
				<form action="system_usermanager.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
					<input type="hidden" id="act" name="act" value="" />
					<input type="hidden" id="userid" name="userid" value="<?=(isset($id) ? $id : '');?>" />
					<input type="hidden" id="privid" name="privid" value="" />
					<input type="hidden" id="certid" name="certid" value="" />
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
<?php
						$ro = "";
						if ($pconfig['utype'] == "system") {
							$ro = "readonly=\"readonly\"";
						}
?>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Defined by");?></td>
							<td width="78%" class="vtable">
								<strong><?=strtoupper(htmlspecialchars($pconfig['utype']));?></strong>
								<input name="utype" type="hidden" value="<?=htmlspecialchars($pconfig['utype'])?>" />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Disabled");?></td>
							<td width="78%" class="vtable">
								<input name="disabled" type="checkbox" id="disabled" <?php if ($pconfig['disabled']) echo "checked=\"checked\""; ?> />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Username");?></td>
							<td width="78%" class="vtable">
								<input name="usernamefld" type="text" class="formfld user" id="usernamefld" size="20" maxlength="16" value="<?=htmlspecialchars($pconfig['usernamefld']);?>" <?=$ro;?> />
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
								<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?=htmlspecialchars($pconfig['descr']);?>" <?=$ro;?> />
								<br />
								<?=gettext("User's full name, for your own information only");?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Expiration date"); ?></td>
							<td width="78%" class="vtable">
								<input name="expires" type="text" class="formfld unknown" id="expires" size="10" value="<?=htmlspecialchars($pconfig['expires']);?>" />
								<br />
								<span class="vexpl"><?=gettext("Leave blank if the account shouldn't expire, otherwise enter the expiration date in the following format: mm/dd/yyyy"); ?></span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Group Memberships");?></td>
							<td width="78%" class="vtable" align="center">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="group membership">
									<tr>
										<td align="center" width="50%">
											<strong><?=gettext("Not Member Of"); ?></strong><br />
											<br />
											<select size="10" style="width: 75%" name="notgroups[]" class="formselect" id="notgroups" onchange="clear_selected('groups')" multiple="multiple">
<?php
												$rowIndex = 0;
												foreach ($config['system']['group'] as $group):
													if ($group['gid'] == 1998) { /* all users group */
														continue;
													}
													if (is_array($pconfig['groups']) && in_array($group['name'], $pconfig['groups'])) {
														continue;
													}
													$rowIndex++;
?>
												<option value="<?=$group['name'];?>" <?=$selected;?>>
													<?=htmlspecialchars($group['name']);?>
												</option>
<?php
												endforeach;
												if ($rowIndex == 0) {
													echo "<option></option>";
												}
?>
											</select>
											<br />
										</td>
										<td>
											<br />
											<a href="javascript:move_selected('notgroups', 'groups')">
												<img src="/themes/<?= $g['theme'];?>/images/icons/icon_right.gif" title="<?=gettext("Add Groups"); ?>" alt="<?=gettext("Add Groups"); ?>" width="17" height="17" border="0" />
											</a>
											<br /><br />
											<a href="javascript:move_selected('groups', 'notgroups')">
												<img src="/themes/<?= $g['theme'];?>/images/icons/icon_left.gif" title="<?=gettext("Remove Groups"); ?>" alt="<?=gettext("Remove Groups"); ?>" width="17" height="17" border="0" />
											</a>
										</td>
										<td align="center" width="50%">
											<strong><?=gettext("Member Of"); ?></strong><br />
											<br />
											<select size="10" style="width: 75%" name="groups[]" class="formselect" id="groups" onchange="clear_selected('notgroups')" multiple="multiple">
<?php
												$rowIndex = 0;
												if (is_array($pconfig['groups'])):
													foreach ($config['system']['group'] as $group):
														if ($group['gid'] == 1998) { /* all users group */
															continue;
														}
														if (!in_array($group['name'], $pconfig['groups'])) {
															continue;
														}
														$rowIndex++;
?>
												<option value="<?=$group['name'];?>">
													<?=htmlspecialchars($group['name']);?>
												</option>
<?php
													endforeach;
												endif;
												if ($rowIndex == 0) {
													echo "<option></option>";
												}
?>
											</select>
											<br />
										</td>
									</tr>
								</table>
								<?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
							</td>
						</tr>
<?php
					if (isset($pconfig['uid'])):
?>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Effective Privileges");?></td>
							<td width="78%" class="vtable">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="privileges">
									<tr>
										<td width="20%" class="listhdrr"><?=gettext("Inherited From");?></td>
										<td width="30%" class="listhdrr"><?=gettext("Name");?></td>
										<td width="40%" class="listhdrr"><?=gettext("Description");?></td>
										<td class="list"></td>
									</tr>
<?php
							$privdesc = get_user_privdesc($a_user[$id]);
							if (is_array($privdesc)):
								$i = 0;
								foreach ($privdesc as $priv):
									$group = false;
									if ($priv['group']) {
										$group = $priv['group'];
									}
?>
									<tr>
										<td class="listlr"><?=$group;?></td>
										<td class="listr">
											<?=htmlspecialchars($priv['name']);?>
										</td>
										<td class="listbg">
											<?=htmlspecialchars($priv['descr']);?>
										</td>
										<td valign="middle" class="list nowrap">
<?php
										if (!$group):
?>
											<input type="image" name="delpriv[]" width="17" height="17" border="0"
												src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif"
												onclick="document.getElementById('privid').value='<?=$i;?>';
													document.getElementById('userid').value='<?=$id;?>';
													document.getElementById('act').value='<?php echo "delpriv";?>';
													return confirm('<?=gettext("Do you really want to delete this privilege?");?>');"
												title="<?=gettext("delete privilege");?>" />
<?php
										endif;
?>
										</td>
									</tr>
<?php
										/* can only delete user priv indexes */
										if (!$group) {
											$i++;
										}
								endforeach;
							endif;
?>
									<tr>
										<td class="list" colspan="3"></td>
										<td class="list">
											<a href="system_usermanager_addprivs.php?userid=<?=$id?>">
												<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" />
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("User Certificates");?></td>
							<td width="78%" class="vtable">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="certificates">
									<tr>
										<td width="45%" class="listhdrr"><?=gettext("Name");?></td>
										<td width="45%" class="listhdrr"><?=gettext("CA");?></td>
										<td class="list"></td>
									</tr>
<?php
							$a_cert = $a_user[$id]['cert'];
							if (is_array($a_cert)):
								$i = 0;
								foreach ($a_cert as $certref):
									$cert = lookup_cert($certref);
									$ca = lookup_ca($cert['caref']);
?>
									<tr>
										<td class="listlr">
											<?=htmlspecialchars($cert['descr']);?>
<?php
										if (is_cert_revoked($cert)):
?>
											(<b>Revoked</b>)
<?php
										endif;
?>
										</td>
										<td class="listr">
											<?=htmlspecialchars($ca['descr']);?>
										</td>
										<td valign="middle" class="list nowrap">
											<input type="image" name="expckey[]" width="17" height="17" border="0"
												src="/themes/<?=$g['theme'];?>/images/icons/icon_down.gif"
												onclick="document.getElementById('certid').value='<?=$i;?>';
													document.getElementById('userid').value='<?=$id;?>';
													document.getElementById('act').value='<?php echo "expckey";?>';"
												title="<?=gettext("export private key");?>" />
											<input type="image" name="expcert[]" width="17" height="17" border="0"
												src="/themes/<?=$g['theme'];?>/images/icons/icon_down.gif"
												onclick="document.getElementById('certid').value='<?=$i;?>';
													document.getElementById('userid').value='<?=$id;?>';
													document.getElementById('act').value='<?php echo "expcert";?>';"
												title="<?=gettext("export cert");?>" />
											<input type="image" name="delcert[]" width="17" height="17" border="0"
												src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif"
												onclick="document.getElementById('certid').value='<?=$i;?>';
													document.getElementById('userid').value='<?=$id;?>';
													document.getElementById('act').value='<?php echo "delcert";?>';
													return confirm('<?=gettext("Do you really want to remove this certificate association?") .'\n'. gettext("(Certificate will not be deleted)");?>')"
												title="<?=gettext("delete cert");?>" />
										</td>
									</tr>
<?php
									$i++;
								endforeach;
							endif;
?>
									<tr>
										<td class="list" colspan="2"></td>
										<td class="list">
											<a href="system_certmanager.php?act=new&amp;userid=<?=$id?>">
												<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="add" />
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>

<?php
					else:
						if (is_array($config['ca']) && count($config['ca']) > 0):
							$i = 0;
							foreach ($config['ca'] as $ca) {
								if (!$ca['prv']) {
									continue;
								}
								$i++;
							}
?>

						<tr id="usercertchck">
							<td width="22%" valign="top" class="vncell"><?=gettext("Certificate");?></td>
							<td width="78%" class="vtable">
								<input type="checkbox" onclick="javascript:usercertClicked(this)" /> <?=gettext("Click to create a user certificate."); ?>
							</td>
						</tr>

<?php
							if ($i > 0):
?>
						<tr id="usercert" style="display:none">
							<td width="22%" valign="top" class="vncell"><?=gettext("Certificate");?></td>
							<td width="78%" class="vtable">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="certificate">
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
										<td width="78%" class="vtable">
											<input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate authority");?></td>
										<td width="78%" class="vtable">
											<select name='caref' id='caref' class="formselect" onchange='internalca_change()'>
<?php
											$rowIndex = 0;
											foreach ($config['ca'] as $ca):
												if (!$ca['prv']) {
													continue;
												}
												$rowIndex++;
?>
												<option value="<?=$ca['refid'];?>"><?=$ca['descr'];?></option>
<?php
											endforeach;
											if ($rowIndex == 0) {
												echo "<option></option>";
											}
?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
										<td width="78%" class="vtable">
											<select name='keylen' class="formselect">
<?php
											$cert_keylens = array("2048", "512", "1024", "4096");
											foreach ($cert_keylens as $len):
?>
												<option value="<?=$len;?>"><?=$len;?></option>
<?php
											endforeach;
											if (!count($cert_keylens)) {
												echo "<option></option>";
											}
?>
											</select>
											bits
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
										<td width="78%" class="vtable">
											<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>" />days
										</td>
									</tr>
								</table>
							</td>
						</tr>
<?php
							endif;
						endif;
					endif;
?>
						<tr id="sshkeychck" <?php if (!empty($pconfig['authorizedkeys'])) echo 'style="display:none"'; ?>>
							<td width="22%" valign="top" class="vncell"><?=gettext("Authorized keys");?></td>
							<td width="78%" class="vtable">
								<input type="checkbox" onclick="javascript:sshkeyClicked(this)" /> <?=gettext("Click to paste an authorized key."); ?>
							</td>
						</tr>
						<tr id="sshkey" <?php if (empty($pconfig['authorizedkeys'])) echo 'style="display:none"'; ?>>
							<td width="22%" valign="top" class="vncell"><?=gettext("Authorized keys");?></td>
							<td width="78%" class="vtable">
								<script type="text/javascript">
								//<![CDATA[
								window.onload=function() {
									document.getElementById("authorizedkeys").wrap='off';
								}
								//]]>
								</script>
								<textarea name="authorizedkeys" cols="65" rows="7" id="authorizedkeys" class="formfld_cert"><?=htmlspecialchars($pconfig['authorizedkeys']);?></textarea>
								<br />
								<?=gettext("Paste an authorized keys file here.");?>
							</td>
						</tr>
						<tr id="ipsecpskrow">
							<td width="22%" valign="top" class="vncell"><?=gettext("IPsec Pre-Shared Key");?></td>
							<td width="78%" class="vtable">
								<input name="ipsecpsk" type="text" class="formfld unknown" id="ipsecpsk" size="65" value="<?=htmlspecialchars($pconfig['ipsecpsk']);?>" />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submit" name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
								<input type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
								<?php if (isset($id) && $a_user[$id]): ?>
								<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
								<?php endif;?>
							</td>
						</tr>
					</table>
				</form>
<?php
			else:
?>
				<form action="system_usermanager.php" method="post" name="iform2" id="iform2">
					<input type="hidden" id="act" name="act" value="" />
					<input type="hidden" id="userid" name="userid" value="<?=(isset($id) ? $id : '');?>" />
					<input type="hidden" id="username" name="username" value="" />
					<input type="hidden" id="privid" name="privid" value="" />
					<input type="hidden" id="certid" name="certid" value="" />
					<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="">
						<thead>
							<tr>
								<th width="5%" class="list">&nbsp;</th>
								<th width="25%" class="listhdrr"><?=gettext("Username"); ?></th>
								<th width="25%" class="listhdrr"><?=gettext("Full name"); ?></th>
								<th width="5%" class="listhdrr"><?=gettext("Disabled"); ?></th>
								<th width="25%" class="listhdrr"><?=gettext("Groups"); ?></th>
								<th width="10%" class="list"></th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<td class="list" colspan="5"></td>
								<td class="list">
									<input type="image" name="addcert" width="17" height="17" border="0"
										src="/themes/<?=$g['theme'];?>/images/icons/icon_plus.gif"
										onclick="document.getElementById('act').value='<?php echo "new";?>';"
										title="<?=gettext("add user");?>" />
									<input type="image" src="/themes/<?= $g['theme'];?>/images/icons/icon_x.gif" name="dellall" title="<?=gettext('Delete selected users')?>" onClick="return confirm('<?=gettext("Do you really want to delete selected Users?");?>')" />
								</td>
							</tr>
							<tr>
								<td colspan="5">
									<p>
										<?=gettext("Additional users can be added here. User permissions for accessing " .
										"the webConfigurator can be assigned directly or inherited from group memberships. " .
										"An icon that appears grey indicates that it is a system defined object. " .
										"Some system object properties can be modified but they cannot be deleted."); ?>
										<br /><br />
										<?=gettext("Accounts created here are also used for other parts of the system " .
										"such as OpenVPN, IPsec, and Captive Portal.");?>
									</p>
								</td>
							</tr>
						</tfoot>
						<tbody>
<?php
						$i = 0;
						foreach ($a_user as $userent):
?>
								<tr ondblclick="document.getElementById('act').value='<?php echo "edit";?>';
									document.getElementById('userid').value='<?=$i;?>';
									document.iform2.submit();" id="fr<?=$i?>">
								<td class="list" id="frd<?=$i?>">
								<?php if ($userent['scope'] != "system") : ?>
									<input type="checkbox" id="frc<?=$i?>" onclick="fr_bgcolor(<?=$i?>)" name="delete_check[]" value="<?=$i?>" />
								<?php endif; ?>
								</td>
								<td class="listlr" id="frd<?=$i?>" onclick="fr_toggle('<?=$i;?>')">
									<table border="0" cellpadding="0" cellspacing="0" summary="icons">
										<tr>
											<td align="left" valign="middle">
<?php
												if ($userent['scope'] != "user") {
													$usrimg = "/themes/{$g['theme']}/images/icons/icon_system-user-grey.png";
												} else {
													$usrimg = "/themes/{$g['theme']}/images/icons/icon_system-user.png";
												}
?>
												<img src="<?=$usrimg;?>" alt="<?=gettext("User"); ?>" title="<?=gettext("User"); ?>" border="0" height="16" width="16" />
											</td>
											<td align="left" valign="middle">
												<?=htmlspecialchars($userent['name']);?>
											</td>
										</tr>
									</table>
								</td>
								<td class="listr" id="frd<?=$i?>" onclick="fr_toggle('<?=$i;?>')"><?=htmlspecialchars($userent['descr']);?>&nbsp;</td>
								<td class="listr" id="frd<?=$i?>" onclick="fr_toggle('<?=$i;?>')"><?php if (isset($userent['disabled'])) echo "*"; ?></td>
								<td class="listbg" onclick="fr_toggle('<?=$i;?>')">
									<?=implode(",", local_user_get_groups($userent));?>
									&nbsp;
								</td>
								<td valign="middle" class="list nowrap">
									<input type="image" name="edituser[]" width="17" height="17" border="0"
										src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"
										onclick="document.getElementById('userid').value='<?=$i;?>';
											document.getElementById('act').value='<?php echo "edit";?>';"
										title="<?=gettext("edit user");?>" />
<?php
								if ($userent['scope'] != "system"):
?>
									&nbsp;
									<input type="image" name="deluser[]" width="17" height="17" border="0"
										src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif"
										onclick="document.getElementById('userid').value='<?=$i;?>';
											document.getElementById('username').value='<?=$userent['name'];?>';
											document.getElementById('act').value='<?php echo "deluser";?>';
											return confirm('<?=gettext("Do you really want to delete this user?");?>');"
										title="<?=gettext("delete user");?>" />
<?php
								endif;
?>
								</td>
							</tr>
<?php
							$i++;
						endforeach;
?>
						</tbody>
					</table>
				</form>
<?php
			endif;
?>
			</div>
		</td>
	</tr>
</table>
<?php include("fend.inc");?>
</body>
</html>
