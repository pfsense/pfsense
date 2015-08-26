<?php
/*
	$Id: system_groupmanager.php
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

if (!is_array($config['system']['group'])) {
	$config['system']['group'] = array();
}

$a_group = &$config['system']['group'];

unset($id);
if (isset($_POST['groupid']) && is_numericint($_POST['groupid'])) {
	$id = $_POST['groupid'];
}

$act = (isset($_POST['act']) ? $_POST['act'] : '');

if ($act == "delgroup") {

	if (!isset($id) || !isset($_POST['groupname']) || !isset($a_group[$id]) || ($_POST['groupname'] != $a_group[$id]['name'])) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	conf_mount_rw();
	local_group_del($a_group[$id]);
	conf_mount_ro();
	$groupdeleted = $a_group[$id]['name'];
	unset($a_group[$id]);
	write_config();
	$savemsg = gettext("Group") . " {$groupdeleted} " .
		gettext("successfully deleted") . "<br />";
}

if ($act == "delpriv") {

	if (!isset($id) || !isset($a_group[$id])) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	$privdeleted = $priv_list[$a_group[$id]['priv'][$_POST['privid']]]['name'];
	unset($a_group[$id]['priv'][$_POST['privid']]);

	if (is_array($a_group[$id]['member'])) {
		foreach ($a_group[$id]['member'] as $uid) {
			$user = getUserEntryByUID($uid);
			if ($user) {
				local_user_set($user);
			}
		}
	}

	write_config();
	$act = "edit";
	$savemsg = gettext("Privilege") . " {$privdeleted} " .
		gettext("successfully deleted") . "<br />";
}

if ($act == "edit") {
	if (isset($id) && isset($a_group[$id])) {
		$pconfig['name'] = $a_group[$id]['name'];
		$pconfig['gid'] = $a_group[$id]['gid'];
		$pconfig['gtype'] = $a_group[$id]['scope'];
		$pconfig['description'] = $a_group[$id]['description'];
		$pconfig['members'] = $a_group[$id]['member'];
		$pconfig['priv'] = $a_group[$id]['priv'];
	}
}

if (isset($_POST['dellall_x'])) {

	$del_groups = $_POST['delete_check'];

	if (!empty($del_groups)) {
		foreach ($del_groups as $groupid) {
			if (isset($a_group[$groupid]) && $a_group[$groupid]['scope'] != "system") {
				conf_mount_rw();
				local_group_del($a_group[$groupid]);
				conf_mount_ro();
				unset($a_group[$groupid]);
			}
		}
		$savemsg = gettext("Selected groups removed successfully!");
		write_config($savemsg);
	}
}

if (isset($_POST['save'])) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "groupname");
	$reqdfieldsn = array(gettext("Group Name"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_ ]/", $_POST['groupname'])) {
		$input_errors[] = gettext("The group name contains invalid characters.");
	}

	if (strlen($_POST['groupname']) > 16) {
		$input_errors[] = gettext("The group name is longer than 16 characters.");
	}

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
		if (isset($id) && $a_group[$id]) {
			$group = $a_group[$id];
		}

		$group['name'] = $_POST['groupname'];
		$group['description'] = $_POST['description'];

		if (empty($_POST['members'])) {
			unset($group['member']);
		} else if ($group['gid'] != 1998) { // all group
			$group['member'] = $_POST['members'];
		}

		if (isset($id) && $a_group[$id]) {
			$a_group[$id] = $group;
		} else {
			$group['gid'] = $config['system']['nextgid']++;
			$a_group[] = $group;
		}

		conf_mount_rw();
		local_group_set($group);
		conf_mount_ro();

		/* Refresh users in this group since their privileges may have changed. */
		if (is_array($group['member'])) {
			$a_user = &$config['system']['user'];
			foreach ($a_user as & $user) {
				if (in_array($user['uid'], $group['member'])) {
					local_user_set($user);
				}
			}
		}

		write_config();

		header("Location: system_groupmanager.php");
		exit;
	}
}

include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), true, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if (!($_GET['act'] == "new" || $_GET['act'] == "edit"))
{
?>
	<div class="table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<th><?=gettext("Group name")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Member Count")?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
<?php
	foreach($a_group as $i => $group):
		if ($group["name"] == "all")
			$groupcount = count($config['system']['user']);
		else
			$groupcount = count($group['member']);
?>
				<tr>
					<td>
						<?=htmlspecialchars($group['name'])?>
					</td>
					<td>
						<?=htmlspecialchars($group['description'])?>
					</td>
					<td>
						<?=$groupcount?>
					</td>
					<td>
						<a href="?act=edit&amp;groupid=<?=$i?>" class="btn btn-xs btn-primary">edit</a>
						<?php if($group['scope'] != "system"): ?>
							<a href="?act=delgroup&amp;groupid=<?=$i?>&amp;groupname=<?=$group['name']?>" class="btn btn-xs btn-danger">delete</a>
						<?php endif;?>
					</td>
				</tr>
<?php
	endforeach;
?>
			</tbody>
		</table>
	</div>

	<nav class="action-buttons">
		<a href="?act=new" class="btn btn-success">add new</a>
	</nav>
<?php
	include('foot.inc');
	exit;
}

require('classes/Form.class.php');
$form = new Form;
$form->setAction('system_groupmanager.php?act=edit');
$form->addGlobal(new Form_Input(
	'groupid',
	null,
	'hidden',
	$id
));

if (isset($id) && $a_group[$id]){
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Input(
		'gid',
		null,
		'hidden',
		$pconfig['gid']
	));
}

$section = new Form_Section('Group properties');

if ($_GET['act'] != "new")
{
	$section->addInput(new Form_StaticText(
		'Defined by',
		strtoupper($pconfig['gtype'])
	));
}

$section->addInput($input = new Form_Input(
	'groupname',
	'Group name',
	'text',
	$pconfig['name']
));

if ($pconfig['gtype'] == "system")
	$input->setReadonly();

$section->addInput(new Form_Input(
	'description',
	'Description',
	'text',
	$pconfig['description']
))->setHelp('Group description, for your own information only');

$form->add($section);
if ($pconfig['gid'] != 1998) // all users group
{
	$section = new Form_Section('Group Memberships');

	$allUsers = array_map(function($u){ return $u['name']; }, $config['system']['user']);
	$section->addInput(new Form_Select(
		'members',
		'Members',
		$pconfig['members'],
		$allUsers,
		true
	))->setHelp('Hold down CTRL (pc)/COMMAND (mac) key to select');

	$form->add($section);
}

if ($_GET['act'] != "new")
{
	$section = new Form_Section('Assigned Privileges');

	foreach ((array)$pconfig['priv'] as $i => $priv)
	{
		// We reverse name and action for readability of longer names
		$group = new Form_Group('Revoke privilege');

		$group->add(new Form_Checkbox(
			'delpriv[]',
			null,
			$priv_list[ $priv ]['name'],
			false,
			$i
		));

		$section->add($group);
	}

	$section->addInput(new Form_StaticText(
		null,
		new Form_Button(null, 'grant more privileges', 'system_groupmanager_addprivs.php?groupid='. $id)
	));

	$form->add($section);
}

print $form;

include('foot.inc');