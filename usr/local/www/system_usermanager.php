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
	$savemsg = gettext("User")." {$userdeleted} ".
				gettext("successfully deleted")."<br />";
}
else if ($_GET['act'] == "new") {
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
		// This used to be a separate act=delpriv
		if ($a_user[$id] && !empty($_POST['privid'])) {
			foreach ($_POST['privid'] as $i)
				unset($a_user[$id]['priv'][$i]);
			local_user_set($a_user[$id]);
			write_config();
		}

		// This used to be a separate act=delcert
		if ($a_user[$id] && !empty($_POST['certid'])) {
			foreach ($_POST['certid'] as $i)
				unset($a_user[$id]['cert'][$i]);

			write_config();
		}

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

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Users"), true, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if (!($_GET['act'] == "new" || $_GET['act'] == "edit" || $input_errors))
{
?>

<div class="table-responsive">
<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th>&nbsp;</th>
			<th><?=gettext("Username")?></th>
			<th><?=gettext("Full name")?></th>
			<th><?=gettext("Disabled")?></th>
			<th><?=gettext("Groups")?></th>
		</tr>
	</thead>
	<tbody>
	</tbody>
	<tbody>
<?php
foreach($a_user as $i => $userent):
	?>
	<tr>
		<td>
			<input type="checkbox" id="frc<?=$i?>" name="delete_check[]" value="<?=$i?>" <?=($userent['scope'] == "system" ? 'disabled="disabled"' : '')?>/>
		</td>
		<td>
<?php
	if($userent['scope'] != "user")
		$usrimg = 'eye-open';
	else
		$usrimg = 'user';
?>
			<i class="icon icon-<?=$usrimg?>"></i>
			<?=htmlspecialchars($userent['name'])?>
		</td>
		<td><?=htmlspecialchars($userent['descr'])?></td>
		<td><?php if(isset($userent['disabled'])) echo "*"?></td>
		<td><?=implode(",",local_user_get_groups($userent))?></td>
		<td>
			<a href="?act=edit&amp;userid=<?=$i?>" class="btn btn-xs btn-primary">edit</a>
<?php if($userent['scope'] != "system"): ?>
			<a href="?act=del&amp;userid=<?=$i?>" class="btn btn-xs btn-danger">delete</a>
<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
</div>
<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success">add new</a>
</nav>
<p>
	<?=gettext("Additional users can be added here. User permissions for accessing " .
	"the webConfigurator can be assigned directly or inherited from group memberships. " .
	"An icon that appears grey indicates that it is a system defined object. " .
	"Some system object properties can be modified but they cannot be deleted.")?>
	<br /><br />
	<?=gettext("Accounts created here are also used for other parts of the system " .
	"such as OpenVPN, IPsec, and Captive Portal.")?>
</p>
<?php
	include("foot.inc");
	exit;
}

require('classes/Form.class.php');
$form = new Form;
$form->setAction('system_usermanager.php?act=edit');
$form->addGlobal(new Form_Input(
	'userid',
	null,
	'hidden',
	$id
));
$form->addGlobal(new Form_Input(
	'utype',
	null,
	'hidden',
	$pconfig['utype']
));
$form->addGlobal(new Form_Input(
	'oldusername',
	null,
	'hidden',
	$pconfig['usernamefld']
));

$section = new Form_Section('User Properties');

$section->addInput(new Form_StaticText(
	'Defined by',
	strtoupper($pconfig['utype'])
));

?>
<?php
$ro = false;
if ($pconfig['utype'] == "system")
	$ro = true;

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

if ($ro)
	$input->setDisabled();

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

if ($ro)
	$input->setDisabled();

$section->addInput(new Form_Input(
	'expires',
	'Expiration date',
	'date',
	$pconfig['expires']
))->setHelp('Leave blank if the account shouldn\'t expire, otherwise enter '.
	'the expiration date');

$systemGroups = array();
foreach ($config['system']['group'] as $group)
	$systemGroups[ $group['name'] ] = $group['name'];

$section->addInput(new Form_Select(
	'groups',
	'Group Memberships',
	array_combine((array)$pconfig['groups'], (array)$pconfig['groups']),
	$systemGroups,
	true
))->setHelp('Hold down CTRL (pc)/COMMAND (mac) key to select multiple items');

$form->add($section);

if (isset($pconfig['uid']))
{
	$section = new Form_Section('Effective Privileges');

	foreach (get_user_privdesc($a_user[$id]) as $i => $priv)
	{
		// We reverse name and action for readability of longer names
		$input = new Form_Checkbox(
			'privid[]',
			null,
			$priv['name'],
			false,
			$i
		);

		if ($priv['group'])
		{
			$group = new Form_Group('Inherited from '. $priv['group']);
			$input->setDisabled();
		}
		else
			$group = new Form_Group('Revoke privilege');

		$group->add($input);
		$section->add($group);
	}

	$section->addInput(new Form_StaticText(
		null,
		new Form_Button(null, 'grant more privileges', 'system_usermanager_addprivs.php?userid='. $id)
	));

	$form->add($section);

	$section = new Form_Section('User Certificates');

	foreach ((array)$a_user[$id]['cert'] as $i => $certref)
	{
		$cert = lookup_cert($certref);
		$ca = lookup_ca($cert['caref']);

		// We reverse name and action for readability of longer names
		$section->addInput(new Form_Checkbox(
			'certid[]',
			'Delete certificate',
			$cert['descr']. (is_cert_revoked($cert) ? ' <b>revoked</b>' : ''),
			false,
			$i
		));
	}

	#FIXME; old ui supplied direct export links to each certificate

	$section->addInput(new Form_StaticText(
		null,
		new Form_Button(null, 'add certificate', 'system_certmanager.php?act=new&userid='. $id).
		new Form_Button(null, 'export certificates', 'system_certmanager.php')
	));
}
else
{
	if (is_array($config['ca']) && count($config['ca']) > 0)
	{
		$section = new Form_Section('Create certificate for user');

		$nonPrvCas = array();
		foreach( $config['ca'] as $ca)
		{
			if (!$ca['prv'])
				continue;

			$nonPrvCas[ $ca['refid'] ] = $ca['descr'];
		}

		if (!empty($nonPrvCas))
		{
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
}

$section = new Form_Section('Keys');

$section->addInput(new Form_Textarea(
	'authorizedkeys',
	'Authorized keys',
	'text',
	$pconfig['authorizedkeys']
))->setHelp('Paste an authorized keys file here.');

$section->addInput(new Form_Input(
	'ipsecpsk',
	'IPsec Pre-Shared Key',
	'text',
	$pconfig['ipsecpsk']
));

$form->add($section);
print $form;

include('foot.inc');