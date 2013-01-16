<?php 
/*
	$Id: system_groupmanager.php 
	part of m0n0wall (http://m0n0.ch/wall)

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
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-system-groupmanager
##|*NAME=System: Group manager page
##|*DESCR=Allow access to the 'System: Group manager' page.
##|*MATCH=system_groupmanager.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = array(gettext("System"), gettext("Group manager"));

if (!is_array($config['system']['group']))
	$config['system']['group'] = array();

$a_group = &$config['system']['group'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if ($_GET['act'] == "delgroup") {

	if (!$a_group[$_GET['id']]) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	local_group_del($a_group[$_GET['id']]);
	$groupdeleted = $a_group[$_GET['id']]['name'];
	unset($a_group[$_GET['id']]);
	write_config();
	$savemsg = gettext("Group")." {$groupdeleted} ".
				gettext("successfully deleted")."<br/>";
}

if ($_GET['act'] == "delpriv") {

	if (!$a_group[$_GET['id']]) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	$privdeleted = $priv_list[$a_group[$id]['priv'][$_GET['privid']]]['name'];
	unset($a_group[$id]['priv'][$_GET['privid']]);

	if (is_array($a_group[$id]['member'])) {
		foreach ($a_group[$id]['member'] as $uid) {
			$user = getUserEntryByUID($uid);
			if ($user)
				local_user_set($user);
		}
	}

	write_config();
	$_GET['act'] = "edit";
	$savemsg = gettext("Privilege")." {$privdeleted} ".
				gettext("successfully deleted")."<br/>";
}

if($_GET['act']=="edit"){
	if (isset($id) && $a_group[$id]) {
		$pconfig['name'] = $a_group[$id]['name'];
		$pconfig['gid'] = $a_group[$id]['gid'];
		$pconfig['gtype'] = $a_group[$id]['scope'];
		$pconfig['description'] = $a_group[$id]['description'];
		$pconfig['members'] = $a_group[$id]['member'];
		$pconfig['priv'] = $a_group[$id]['priv'];
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "groupname");
	$reqdfieldsn = array(gettext("Group Name"));
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	
	if (preg_match("/[^a-zA-Z0-9\.\-_ ]/", $_POST['groupname']))
		$input_errors[] = gettext("The group name contains invalid characters.");
		
	if (!$input_errors && !(isset($id) && $a_group[$id])) {
		/* make sure there are no dupes */
		foreach ($a_group as $group) {
			if ($group['name'] == $_POST['groupname']) {
				$input_errors[] = gettext("Another entry with the same group name already exists.");
				break;
			}
		}
	}
	
	if (!$input_errors) {
		$group = array();
		if (isset($id) && $a_group[$id])
			$group = $a_group[$id];
		
		$group['name'] = $_POST['groupname'];
		$group['description'] = $_POST['description'];

		if (empty($_POST['members']))
			unset($group['member']);
		else if ($group['gid'] != 1998) // all group
			$group['member'] = $_POST['members'];

		if (isset($id) && $a_group[$id])
			$a_group[$id] = $group;
		else {
			$group['gid'] = $config['system']['nextgid']++;
			$a_group[] = $group;
		}

		local_group_set($group);

		/* Refresh users in this group since their privileges may have changed. */
		if (is_array($group['member'])) {
			$a_user = &$config['system']['user'];
			foreach ($a_user as & $user) {
				if (in_array($user['uid'], $group['member']))
					local_user_set($user);
			}
		}

		write_config();
		
		header("Location: system_groupmanager.php");
		exit;
	}
}

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--

function setall_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++)
		selbox.options[index].selected = true;
}

function clear_selected(id) {
	selbox = document.getElementById(id);
	count = selbox.options.length;
	for (index = 0; index<count; index++)
		selbox.options[index].selected = false;
}

function remove_selected(id) {
	selbox = document.getElementById(id);
	index = selbox.options.length - 1;
	for (; index >= 0; index--)
		if (selbox.options[index].selected)
			selbox.remove(index);
}

function copy_selected(srcid, dstid) {
	src_selbox = document.getElementById(srcid);
	dst_selbox = document.getElementById(dstid);
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
	clear_selected('notmembers');
	setall_selected('members');
}

//-->
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
				$tab_array[] = array(gettext("Groups"), true, "system_groupmanager.php");
				$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
				$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>    
	<tr>
		<td id="mainarea">
			<div class="tabcont">

				<?php if($_GET['act']=="new" || $_GET['act']=="edit"): ?>

				<form action="system_groupmanager.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
	                    <?php
	                        $ro = "";
	                        if ($pconfig['gtype'] == "system")
	                            $ro = "readonly = \"readonly\"";
	                    ?>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Defined by");?></td>
							<td width="78%" class="vtable">
								<strong><?=strtoupper($pconfig['gtype']);?></strong>
								<input name="gtype" type="hidden" value="<?=htmlspecialchars($pconfig['gtype'])?>"/>
							</td>
						</tr>
						<tr> 
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Group name");?></td>
							<td width="78%" class="vtable"> 
								<input name="groupname" type="text" class="formfld group" id="groupname" size="20" value="<?=htmlspecialchars($pconfig['name']);?>" <?=$ro;?>> 
							</td>
						</tr>
						<tr> 
							<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
							<td width="78%" class="vtable"> 
								<input name="description" type="text" class="formfld unknown" id="description" size="20" value="<?=htmlspecialchars($pconfig['description']);?>">
								<br>
								<?=gettext("Group description, for your own information only");?>
							</td>
						</tr>

						<?php if ($pconfig['gid'] != 1998): // all users group ?>

						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Group Memberships");?></td>
							<td width="78%" class="vtable" align="center">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td align="center" width="50%">
											<strong><?=gettext("Not Members");?></strong><br/>
											<br/>
												<select size="10" style="width: 75%" name="notmembers[]" class="formselect" id="notmembers" onChange="clear_selected('members')" multiple>
												<?php
													foreach ($config['system']['user'] as $user):
														if (is_array($pconfig['members']) && in_array($user['uid'],$pconfig['members']))
															continue;
												?>
												<option value="<?=$user['uid'];?>" <?=$selected;?>>
													<?=htmlspecialchars($user['name']);?>
												</option>
												<?php endforeach; ?>
											</select>
											<br/>
										</td>
										<td>
											<br/>
											<a href="javascript:move_selected('notmembers','members')">
												<img src="/themes/<?= $g['theme'];?>/images/icons/icon_right.gif" title="<?=gettext("Add Members");?>" alt="<?=gettext("Add Members");?>" width="17" height="17" border="0" />
											</a>
											<br/><br/>
											<a href="javascript:move_selected('members','notmembers')">
												<img src="/themes/<?= $g['theme'];?>/images/icons/icon_left.gif" title="<?=gettext("Remove Members");?>" alt="<?=gettext("Remove Members");?>" width="17" height="17" border="0" />
											</a>
										</td>
										<td align="center" width="50%">
											<strong><?=gettext("Members");?></strong><br/>
											<br/>
											<select size="10" style="width: 75%" name="members[]" class="formselect" id="members" onChange="clear_selected('notmembers')" multiple>
												<?php
													foreach ($config['system']['user'] as $user):
														if (!(is_array($pconfig['members']) && in_array($user['uid'],$pconfig['members'])))
															continue;
												?>
												<option value="<?=$user['uid'];?>">
													<?=htmlspecialchars($user['name']);?>
												</option>
												<?php endforeach; ?>
											</select>
											<br/>
										</td>
									</tr>
								</table>
								<?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
							</td>
						</tr>

						<?php endif; ?>
						<?php if($_GET['act'] != "new"): ?>
						
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Assigned Privileges");?></td>
							<td width="78%" class="vtable">
								<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td width="40%" class="listhdrr"><?=gettext("Name");?></td>
										<td width="60%" class="listhdrr"><?=gettext("Description");?></td>
										<td class="list"></td>
									</tr>
									<?php
										if(is_array($pconfig['priv'])):
											$i = 0;
											foreach ($pconfig['priv'] as $priv):
									?>
									<tr>
										<td class="listr">
											<?=htmlspecialchars($priv_list[$priv]['name']);?>
										</td>
										<td class="listbg">
											<?=htmlspecialchars($priv_list[$priv]['descr']);?>
										</td>
										<td valign="middle" nowrap class="list">
											<a href="system_groupmanager.php?act=delpriv&id=<?=htmlspecialchars($id)?>&privid=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this privilege?");?>')">
												<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="" />
											</a>
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
											<a href="system_groupmanager_addprivs.php?groupid=<?=htmlspecialchars($id)?>">
												<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="" />
											</a>

										</td>
									</tr>

								</table>
							</td>
						</tr>
						<?php endif; ?>
						<tr> 
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%"> 
								<input name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>"> 
								<?php if (isset($id) && $a_group[$id]): ?>
								<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
								<input name="gid" type="hidden" value="<?=htmlspecialchars($pconfig['gid']);?>">
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</form>

				<?php else: ?>

				<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<th width="25%" class="listhdrr"><?=gettext("Group name");?></th>
							<th width="25%" class="listhdrr"><?=gettext("Description");?></th>
							<th width="30%" class="listhdrr"><?=gettext("Member Count");?></th>
							<th width="10%" class="list"></th>
						</tr>
					</thead>
					<tbody>
						<?php
							$i = 0;
							foreach($a_group as $group):

								if($group['scope'] == "system")
									$grpimg = "/themes/{$g['theme']}/images/icons/icon_system-group-grey.png";
								else
									$grpimg = "/themes/{$g['theme']}/images/icons/icon_system-group.png";
								$groupcount = count($group['member']);
								if ($group["name"] == "all")
									$groupcount = count($config['system']['user']);
						?>
						<tr ondblclick="document.location='system_groupmanager.php?act=edit&id=<?=$i;?>'">
							<td class="listlr">
								<table border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td align="left" valign="center">
											<img src="<?=$grpimg;?>" alt="<?=gettext("User");?>" title="<?=gettext("User");?>" border="0" height="16" width="16" />
										</td>
										</td>
										<td align="left" valign="middle">
											<?=htmlspecialchars($group['name']); ?>&nbsp;
										</td>
									</tr>
								</table>
							</td>
							<td class="listr">
								<?=htmlspecialchars($group['description']);?>&nbsp;
							</td>
							<td class="listbg">
								<?=$groupcount;?>
							</td>
							<td valign="middle" nowrap class="list">
								<a href="system_groupmanager.php?act=edit&id=<?=$i;?>">
									<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit group");?>" width="17" height="17" border="0">
								</a>
								&nbsp;
								<?php if($group['scope'] != "system"): ?>
								<a href="system_groupmanager.php?act=delgroup&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this group?"); ?>')">
									<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete group"); ?>" width="17" height="17" border="0">
								</a>
								<?php endif; ?>
							</td>
						</tr>
						<?php
							$i++;
							endforeach;
						?>
					</tbody>
					<tfoot>
						<tr> 
							<td class="list" colspan="3"></td>
							<td class="list">
								<a href="system_groupmanager.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add group");?>" width="17" height="17" border="0">
								</a>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<p>
									<?=gettext("Additional webConfigurator groups can be added here. 
									Group permissions can be assigned which are inherited by users who are members of the group.
									An icon that appears grey indicates that it is a system defined object.
									Some system object properties can be modified but they cannot be deleted.");?>
								</p>
							</td>
						</tr>
					</tfoot>
				</table>
			
				<?php endif; ?>

			</div>     
		</td>
	</tr>
</table>
</body>
<?php include("fend.inc"); ?>
