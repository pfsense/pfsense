<?php
/*
	system_groupmanager.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2005 Paul Taylor <paultaylor@winn-dixie.com>
 *	Copyright (c)  2008 Shrew Soft Inc
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-system-groupmanager
##|*NAME=System: Group manager
##|*DESCR=Allow access to the 'System: Group manager' page.
##|*MATCH=system_groupmanager.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['system']['group'])) {
	$config['system']['group'] = array();
}

$a_group = &$config['system']['group'];

unset($id);

if (isset($_POST['groupid']) && is_numericint($_POST['groupid'])) {
	$id = $_POST['groupid'];
}

if (isset($_GET['groupid']) && is_numericint($_GET['groupid'])) {
	$id = $_GET['groupid'];
}

$act = (isset($_GET['act']) ? $_GET['act'] : '');

function cpusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}
function admin_groups_sort() {
	global $a_group;

	if (!is_array($a_group)) {
		return;
	}

	usort($a_group, "cpusercmp");
}

if ($act == "delgroup") {

	if (!isset($id) || !isset($_GET['groupname']) || !isset($a_group[$id]) || ($_GET['groupname'] != $a_group[$id]['name'])) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	conf_mount_rw();
	local_group_del($a_group[$id]);
	conf_mount_ro();
	$groupdeleted = $a_group[$id]['name'];
	unset($a_group[$id]);
	write_config();
	$savemsg = sprintf(gettext("Group %s successfully deleted."), $groupdeleted);
}

if ($act == "delpriv") {

	if (!isset($id) || !isset($a_group[$id])) {
		pfSenseHeader("system_groupmanager.php");
		exit;
	}

	$privdeleted = $priv_list[$a_group[$id]['priv'][$_POST['privid']]]['name'];
	unset($a_group[$id]['priv'][$_GET['privid']]);

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
	$savemsg = sprintf(gettext("Privilege %s successfully deleted."), $privdeleted);
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

if (isset($_GET['dellall_x'])) {

	$del_groups = $_GET['delete_check'];

	if (!empty($del_groups)) {
		foreach ($del_groups as $groupid) {
			if (isset($a_group[$groupid]) && $a_group[$groupid]['scope'] != "system") {
				conf_mount_rw();
				local_group_del($a_group[$groupid]);
				conf_mount_ro();
				unset($a_group[$groupid]);
			}
		}
		$savemsg = gettext("Selected groups removed successfully.");
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

		admin_groups_sort();

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

function build_priv_table() {
	global $a_group, $id;

	$privhtml = '<div class="table-responsive">';
	$privhtml .=	'<table class="table table-striped table-hover table-condensed">';
	$privhtml .=		'<thead>';
	$privhtml .=			'<tr>';
	$privhtml .=				'<th>' . gettext('Name') . '</th>';
	$privhtml .=				'<th>' . gettext('Description') . '</th>';
	$privhtml .=				'<th>' . gettext('Action') . '</th>';
	$privhtml .=			'</tr>';
	$privhtml .=		'</thead>';
	$privhtml .=		'<tbody>';

	foreach (get_user_privdesc($a_group[$id]) as $i => $priv) {
		$privhtml .=		'<tr>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['name']) . '</td>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['descr']) . '</td>';
		$privhtml .=			'<td><a class="fa fa-trash" title="' . gettext('Delete Privilege') . '"	href="system_groupmanager.php?act=delpriv&amp;groupid=' . $id . '&amp;privid=' . $i . '"></a></td>';
		$privhtml .=		'</tr>';

	}

	$privhtml .=		'</tbody>';
	$privhtml .=	'</table>';
	$privhtml .= '</div>';

	$privhtml .= '<nav class="action-buttons">';
	$privhtml .=	'<a href="system_groupmanager_addprivs.php?groupid=' . $id . '" class="btn btn-success">' . gettext("Add") . '</a>';
	$privhtml .= '</nav>';

	return($privhtml);
}

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Groups"));

if ($act == "new" || $act == "edit") {
	$pgtitle[] = gettext('Edit');
}
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), true, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if (!($_GET['act'] == "new" || $_GET['act'] == "edit")) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Groups')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Group name")?></th>
						<th><?=gettext("Description")?></th>
						<th><?=gettext("Member Count")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
	foreach ($a_group as $i => $group):
		if ($group["name"] == "all") {
			$groupcount = count($config['system']['user']);
		} else {
			$groupcount = count($group['member']);
		}
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
							<a class="fa fa-pencil" title="<?=gettext("Edit group"); ?>" href="?act=edit&amp;groupid=<?=$i?>"></a>
							<?php if ($group['scope'] != "system"): ?>
								<a class="fa fa-trash"	title="<?=gettext("Delete group")?>" href="?act=delgroup&amp;groupid=<?=$i?>&amp;groupname=<?=$group['name']?>"></a>
							<?php endif;?>
						</td>
					</tr>
<?php
	endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
	include('foot.inc');
	exit;
}

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

$section = new Form_Section('Group Properties');

if ($_GET['act'] != "new") {
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

if ($pconfig['gtype'] == "system") {
	$input->setReadonly();
}

$section->addInput(new Form_Input(
	'description',
	'Description',
	'text',
	$pconfig['description']
))->setHelp('Group description, for your own information only');

$form->add($section);
if ($pconfig['gid'] != 1998) { // all users group

	// ==== Group membership ==================================================
	$group = new Form_Group('Group membership');

	// Make a list of all the groups configured on the system, and a list of
	// those which this user is a member of
	$systemGroups = array();
	$usersGroups = array();

	foreach ($config['system']['user'] as $user) {
		if (is_array($pconfig['members']) && in_array($user['uid'], $pconfig['members'])) {
			$usersGroups[ $user['uid'] ] = $user['name'];	// Add it to the user's list
		} else {
			$systemGroups[ $user['uid'] ] = $user['name']; // Add it to the 'not a member of' list
		}
	}

	$group->add(new Form_Select(
		'notmembers',
		null,
		array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
		$systemGroups,
		true
	))->setHelp('Not members');

	$group->add(new Form_Select(
		'members',
		null,
		array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
		$usersGroups,
		true
	))->setHelp('Members');

	$section->add($group);

	$group = new Form_Group('');

	$group->add(new Form_Button(
		'movetoenabled',
		'Move to "Members" >'
	))->removeClass('btn-primary')->addClass('btn-default btn-sm');

	$group->add(new Form_Button(
		'movetodisabled',
		'< Move to "Not members'
	))->removeClass('btn-primary')->addClass('btn-default btn-sm');

	$group->setHelp('Hold down CTRL (pc)/COMMAND (mac) key to select multiple items');
	$section->add($group);

}

if ($_GET['act'] != "new") {
	$section = new Form_Section('Assigned Privileges');

	$section->addInput(new Form_StaticText(
		null,
		build_priv_table()
	));


	$form->add($section);
}

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Make buttons plain buttons, not submit
	$("#movetodisabled").prop('type','button');
	$("#movetoenabled").prop('type','button');


	// On click . .
	$("#movetodisabled").click(function() {
		moveOptions($('[name="members[]"] option'), $('[name="notmembers[]"]'));
	});

	$("#movetoenabled").click(function() {
		moveOptions($('[name="notmembers[]"] option'), $('[name="members[]"]'));
	});

	// On submit mark all the user's groups as "selected"
	$('form').submit(function() {
		AllServers($('[name="members[]"] option'), true);
	});
});
//]]>
</script>
<?php
include('foot.inc');
