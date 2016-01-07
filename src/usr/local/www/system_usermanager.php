<?php
/*
	system_usermanager.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc.
 *	Copyright (c)  2005 Paul Taylor <paultaylor@winn-dixie.com>
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
##|*IDENT=page-system-usermanager
##|*NAME=System: User Manager
##|*DESCR=Allow access to the 'System: User Manager' page.
##|*MATCH=system_usermanager.php*
##|-PRIV

require("certs.inc");
require("guiconfig.inc");

// start admin user code
$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Users"));

if (isset($_POST['userid']) && is_numericint($_POST['userid'])) {
	$id = $_POST['userid'];
}

if (isset($_GET['userid']) && is_numericint($_GET['userid'])) {
	$id = $_GET['userid'];
}

if (!isset($config['system']['user']) || !is_array($config['system']['user'])) {
	$config['system']['user'] = array();
}

$a_user = &$config['system']['user'];
$act = $_GET['act'];

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

if ($_GET['act'] == "deluser") {

	if (!isset($_GET['username']) || !isset($a_user[$id]) || ($_GET['username'] != $a_user[$id]['name'])) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	conf_mount_rw();
	local_user_del($a_user[$id]);
	conf_mount_ro();
	$userdeleted = $a_user[$id]['name'];
	unset($a_user[$id]);
	write_config();
	$savemsg = gettext("User")." {$userdeleted} ".
				gettext("successfully deleted")."<br />";
} else if ($act == "new") {
	/*
	 * set this value cause the text field is read only
	 * and the user should not be able to mess with this
	 * setting.
	 */
	$pconfig['utype'] = "user";
	$pconfig['lifetime'] = 3650;
}

if (isset($_POST['dellall'])) {

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

if ($_POST['act'] == "delcert") {

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
}

if ($_POST['act'] == "delprivid") {
	$privdeleted = $priv_list[$a_user[$id]['priv'][$_POST['privid']]]['name'];
	unset($a_user[$id]['priv'][$_POST['privid']]);
	local_user_set($a_user[$id]);
	write_config();
	$_POST['act'] = "edit";
	$savemsg = gettext("Privilege ") . $privdeleted . gettext(" removed") . "<br />";
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

		/* Add user to groups so PHP can see the memberships properly or else the user's shell account does not get proper permissions (if applicable) See #5152. */
		local_user_set_groups($userent, $_POST['groups']);
		local_user_set($userent);
		/* Add user to groups again to ensure they are set everywhere, otherwise the user may not appear to be a member of the group. See commit:5372d26d9d25d751d16865ed9d46869d3b0ec5e1. */
		local_user_set_groups($userent, $_POST['groups']);
		write_config();

		if (is_dir("/etc/inc/privhooks")) {
			run_plugins("/etc/inc/privhooks");
		}

		conf_mount_ro();

		pfSenseHeader("system_usermanager.php");
	}
}

function build_priv_table() {
	global $a_user, $id;

	$privhtml = '<div class="table-responsive">';
	$privhtml .=	'<table class="table table-striped table-hover table-condensed">';
	$privhtml .=		'<thead>';
	$privhtml .=			'<tr>';
	$privhtml .=				'<th>' . gettext('Inherited from') . '</th>';
	$privhtml .=				'<th>' . gettext('Name') . '</th>';
	$privhtml .=				'<th>' . gettext('Description') . '</th>';
	$privhtml .=			'</tr>';
	$privhtml .=		'</thead>';
	$privhtml .=		'<tbody>';

	$i = 0;

	foreach (get_user_privdesc($a_user[$id]) as $priv) {
		$group = false;
		if ($priv['group']) {
			$group = $priv['group'];
		}

		$privhtml .=		'<tr>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['group']) . '</td>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['name']) . '</td>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['descr']) . '</td>';
		$privhtml .=			'<td>';
		if (!$group) {
			$privhtml .=			'<a class="fa fa-trash no-confirm icon-pointer" title="'.gettext('Delete Privilege').'" id="delprivid' .$i. '"></a></td>';
		}

		$privhtml .=			'</td>';
		$privhtml .=		'</tr>';

		if (!$group) {
			$i++;
		}
	}

	$privhtml .=		'</tbody>';
	$privhtml .=	'</table>';
	$privhtml .= '</div>';

	$privhtml .= '<nav class="action-buttons">';
	$privhtml .=	'<a href="system_usermanager_addprivs.php?userid=' . $id . '" class="btn btn-success">' . gettext("Add") . '</a>';
	$privhtml .= '</nav>';

	return($privhtml);
}

function build_cert_table() {
	global $a_user, $id;

	$certhtml = '<div class="table-responsive">';
	$certhtml .=	'<table class="table table-striped table-hover table-condensed">';
	$certhtml .=		'<thead>';
	$certhtml .=			'<tr>';
	$certhtml .=				'<th>' . gettext('Name') . '</th>';
	$certhtml .=				'<th>' . gettext('CA') . '</th>';
	$certhtml .=				'<th></th>';
	$certhtml .=			'</tr>';
	$certhtml .=		'</thead>';
	$certhtml .=		'<tbody>';

	$a_cert = $a_user[$id]['cert'];
	if (is_array($a_cert)) {
		$i = 0;
		foreach ($a_cert as $certref) {
			$cert = lookup_cert($certref);
			$ca = lookup_ca($cert['caref']);
			$revokedstr =	is_cert_revoked($cert) ? '<b> Revoked</b>':'';

			$certhtml .=	'<tr>';
			$certhtml .=		'<td>' . htmlspecialchars($cert['descr']) . $revokedstr . '</td>';
			$certhtml .=		'<td>' . htmlspecialchars($ca['descr']) . '</td>';
			$certhtml .=		'<td>';
			$certhtml .=			'<a id="delcert' . $i .'" class="fa fa-trash no-confirm icon-pointer" title="';
			$certhtml .=			gettext('Remove this certificate association? (Certificate will not be deleted)') . '"></a>';
			$certhtml .=		'</td>';
			$certhtml .=	'</tr>';
			$i++;
		}

	}

	$certhtml .=		'</tbody>';
	$certhtml .=	'</table>';
	$certhtml .= '</div>';

	$certhtml .= '<nav class="action-buttons">';
	$certhtml .=	'<a href="system_certmanager.php?act=new&amp;userid=' . $id . '" class="btn btn-success">' . gettext("Add") . '</a>';
	$certhtml .= '</nav>';

	return($certhtml);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if (!($act == "new" || $act == "edit" || $input_errors)) {
?>
<form method="post">
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th><?=gettext("Username")?></th>
				<th><?=gettext("Full name")?></th>
				<th><?=gettext("Disabled")?></th>
				<th><?=gettext("Groups")?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
<?php
foreach ($a_user as $i => $userent):
	?>
			<tr>
				<td>
					<input type="checkbox" id="frc<?=$i?>" name="delete_check[]" value="<?=$i?>" <?=($userent['scope'] == "system" ? 'disabled' : '')?>/>
				</td>
				<td>
<?php
	if ($userent['scope'] != "user") {
		$usrimg = 'eye-open';
	} else {
		$usrimg = 'user';
	}
?>
					<i class="fa fa-<?=$usrimg?>"></i>
					<?=htmlspecialchars($userent['name'])?>
				</td>
				<td><?=htmlspecialchars($userent['descr'])?></td>
				<td><?php if (isset($userent['disabled'])) echo "*"?></td>
				<td><?=implode(",", local_user_get_groups($userent))?></td>
				<td>
					<a class="fa fa-pencil" title="<?=gettext("Edit user"); ?>" href="?act=edit&amp;userid=<?=$i?>"></a>
<?php if ($userent['scope'] != "system"): ?>
					<a class="fa fa-trash"	title="<?=gettext("Delete user")?>" href="?act=deluser&amp;userid=<?=$i?>&amp;username=<?=$userent['name']?>"></a>
<?php endif; ?>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
</div>
<nav class="action-buttons">
	<a href="?act=new" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>

	<button type="submit" class="btn btn-sm btn-danger" name="dellall" value="dellall" title="<?=gettext('Delete selected users')?>">
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext("Delete")?>
	</button>
</nav>
</form>

<div class="infoblock">
	<?=print_info_box(gettext("Additional users can be added here. User permissions for accessing " .
	"the webConfigurator can be assigned directly or inherited from group memberships. " .
	"An icon that appears grey indicates that it is a system defined object. " .
	"Some system object properties can be modified but they cannot be deleted.") .
	'<br /><br />' .
	gettext("Accounts added here are also used for other parts of the system " .
	"such as OpenVPN, IPsec, and Captive Portal."), 'info')?>
</div>

<?php
	include("foot.inc");
	exit;
}

$form = new Form;

if ($act == "new" || $act == "edit" || $input_errors):

	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		''
	));

	$form->addGlobal(new Form_Input(
		'userid',
		null,
		'hidden',
		isset($id) ? $id:''
	));

	$form->addGlobal(new Form_Input(
		'privid',
		null,
		'hidden',
		''
	));

	$form->addGlobal(new Form_Input(
		'certid',
		null,
		'hidden',
		''
	));

	$ro = "";
	if ($pconfig['utype'] == "system") {
		$ro = "readonly";
	}

	$section = new Form_Section('User Properties');

	$section->addInput(new Form_StaticText(
		'Defined by',
		strtoupper($pconfig['utype'])
	));

	$form->addGlobal(new Form_Input(
		'utype',
		null,
		'hidden',
		$pconfig['utype']
	));

	$section->addInput(new Form_Checkbox(
		'disabled',
		'Disabled',
		'This user cannot login',
		$pconfig['disabled']
	));

	$section->addInput($input = new Form_Input(
		'usernamefld',
		'Username',
		'text',
		$pconfig['usernamefld']
	));

	if ($ro) {
		$input->setReadonly();
	}

	$form->addGlobal(new Form_Input(
		'oldusername',
		null,
		'hidden',
		$pconfig['usernamefld']
	));

	$group = new Form_Group('Password');
	$group->add(new Form_Input(
		'passwordfld1',
		'Password',
		'password'
	));
	$group->add(new Form_Input(
		'passwordfld2',
		'Confirm Password',
		'password'
	));

	$section->add($group);

	$section->addInput($input = new Form_Input(
		'descr',
		'Full name',
		'text',
		htmlspecialchars($pconfig['descr'])
	))->setHelp('User\'s full name, for your own information only');

	if ($ro) {
		$input->setDisabled();
	}

	$section->addInput(new Form_Input(
		'expires',
		'Expiration date',
		'date',
		$pconfig['expires']
	))->setHelp('Leave blank if the account shouldn\'t expire, otherwise enter '.
		'the expiration date');

	// ==== Group membership ==================================================
	$group = new Form_Group('Group membership');

	// Make a list of all the groups configured on the system, and a list of
	// those which this user is a member of
	$systemGroups = array();
	$usersGroups = array();

	$usergid = [$pconfig['usernamefld']];

	foreach ($config['system']['group'] as $Ggroup) {
		if ($Ggroup['name'] != "all") {
			if (($act == 'edit') && $Ggroup['member'] && in_array($pconfig['uid'], $Ggroup['member'])) {
				$usersGroups[ $Ggroup['name'] ] = $Ggroup['name'];	// Add it to the user's list
			} else {
				$systemGroups[ $Ggroup['name'] ] = $Ggroup['name']; // Add it to the 'not a member of' list
			}
		}
	}

	$group->add(new Form_Select(
		'sysgroups',
		null,
		array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
		$systemGroups,
		true
	))->setHelp('Not member of');

	$group->add(new Form_Select(
		'groups',
		null,
		array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
		$usersGroups,
		true
	))->setHelp('Member of');

	$section->add($group);

	$group = new Form_Group('');

	$group->add(new Form_Button(
		'movetoenabled',
		'Move to "Member of" list >'
	))->removeClass('btn-primary')->addClass('btn-default btn-sm');

	$group->add(new Form_Button(
		'movetodisabled',
		'< Move to "Not member of" list'
	))->removeClass('btn-primary')->addClass('btn-default btn-sm');

	$group->setHelp('Hold down CTRL (pc)/COMMAND (mac) key to select multiple items');
	$section->add($group);

	// ==== Button for adding user certificate ================================
	if ($act == 'new') {
		$section->addInput(new Form_Checkbox(
			'showcert',
			'Certificate',
			'Click to create a user certificate',
			false
		));
	}

	$form->add($section);

	// ==== Effective privileges section ======================================
	if (isset($pconfig['uid'])) {
		// We are going to build an HTML table and add it to an Input_StaticText. It may be ugly, but it
		// is the best way to make the display we need.

		$section = new Form_Section('Effective Privileges');

		$section->addInput(new Form_StaticText(
			null,
			build_priv_table()
		));

		$form->add($section);

		// ==== Certificate table section =====================================
		$section = new Form_Section('User certificates');

		$section->addInput(new Form_StaticText(
			null,
			build_cert_table()
		));

		$form->add($section);
	}

	// ==== Add user certificate for a new user
	if (is_array($config['ca']) && count($config['ca']) > 0) {
		$section = new Form_Section('Create certificate for user');
		$section->addClass('cert-options');

		$nonPrvCas = array();
		foreach($config['ca'] as $ca) {
			if (!$ca['prv']) {
				continue;
			}

			$nonPrvCas[ $ca['refid'] ] = $ca['descr'];
		}

		if (!empty($nonPrvCas)) {
			$section->addInput(new Form_Input(
				'name',
				'Descriptive name',
				'text',
				$pconfig['name']
			));

			$section->addInput(new Form_Select(
				'caref',
				'Certificate authority',
				null,
				$nonPrvCas
			));

			$section->addInput(new Form_Select(
				'keylen',
				'Key length',
				2048,
				array(
					512 => '512 bits',
					1024 => '1024 bits',
					2048 => '2049 bits',
					4096 => '4096 bits',
				)
			));

			$section->addInput(new Form_Input(
				'lifetime',
				'Lifetime',
				'number',
				$pconfig['lifetime']
			));
		}

		$form->add($section);
	}

endif;
// ==== Paste a key for the new user
$section = new Form_Section('Keys');

$section->addInput(new Form_Checkbox(
	'showkey',
	'Authorized keys',
	'Click to paste an authorized key',
	false
));

$section->addInput(new Form_Textarea(
	'authorizedkeys',
	'Authorized SSH Keys',
	$pconfig['authorizedkeys']
))->setHelp('Enter authorized SSH keys for this user');

$section->addInput(new Form_Input(
	'ipsecpsk',
	'IPsec Pre-Shared Key',
	'text',
	$pconfig['ipsecpsk']
));

$form->add($section);

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Select every option in the specified multiselect
	function AllServers(id, selectAll) {
	   for (i = 0; i < id.length; i++)	   {
		   id.eq(i).prop('selected', selectAll);
	   }
	}

	// Move all selected options from one multiselect to another
	function moveOptions(From, To)	{
		var len = From.length;
		var option;

		if (len > 0) {
			for (i=0; i<len; i++) {
				if (From.eq(i).is(':selected')) {
					option = From.eq(i).val();
					value  = From.eq(i).text();
					To.append(new Option(value, option));
					From.eq(i).remove();
				}
			}
		}
	}

	// Make buttons plain buttons, not submit
	$("#movetodisabled").prop('type','button');
	$("#movetoenabled").prop('type','button');

	// On click . .
	$("#movetodisabled").click(function() {
		moveOptions($('[name="groups[]"] option'), $('[name="sysgroups[]"]'));
	});

	$("#movetoenabled").click(function() {
		moveOptions($('[name="sysgroups[]"] option'), $('[name="groups[]"]'));
	});

	$("#showcert").click(function() {
		hideClass('cert-options', !this.checked);
	});

	$("#showkey").click(function() {
		hideInput('authorizedkeys', false);
		hideCheckbox('showkey', true);
	});

	$('[id^=delcert]').click(function(event) {
		if (confirm(event.target.title)) {
			$('#certid').val(event.target.id.match(/\d+$/)[0]);
			$('#userid').val('<?=$id;?>');
			$('#act').val('delcert');
			$('form').submit();
		}
	});

	$('[id^=delprivid]').click(function(event) {
		if (confirm(event.target.title)) {
			$('#privid').val(event.target.id.match(/\d+$/)[0]);
			$('#userid').val('<?=$id;?>');
			$('#act').val('delprivid');
			$('form').submit();
		}
	});


	// ---------- On initial page load ------------------------------------------------------------

	hideClass('cert-options', true);
	//hideInput('authorizedkeys', true);
	hideCheckbox('showkey', true);

	// On submit mark all the user's groups as "selected"
	$('form').submit(function() {
		AllServers($('[name="groups[]"] option'), true);
	});
});
//]]>
</script>
<?php
include('foot.inc');
?>