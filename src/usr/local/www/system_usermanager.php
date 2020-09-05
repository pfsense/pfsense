<?php
/*
 * system_usermanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc.
 * Copyright (c) 2005 Paul Taylor <paultaylor@winn-dixie.com>
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-system-usermanager
##|*NAME=System: User Manager
##|*DESCR=Allow access to the 'System: User Manager' page.
##|*WARN=standard-warning-root
##|*MATCH=system_usermanager.php*
##|-PRIV

require_once("certs.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

$logging_level = LOG_WARNING;
$logging_prefix = gettext("Local User Database");
$cert_keylens = array("1024", "2048", "3072", "4096", "6144", "7680", "8192", "15360", "16384");
$cert_keytypes = array("RSA", "ECDSA");
$openssl_ecnames = cert_build_curve_list();

global $openssl_digest_algs;

// start admin user code
if (isset($_REQUEST['userid']) && is_numericint($_REQUEST['userid'])) {
	$id = $_REQUEST['userid'];
}

init_config_arr(array('system', 'user'));
$a_user = &$config['system']['user'];
$act = $_REQUEST['act'];

if (isset($_SERVER['HTTP_REFERER'])) {
	$referer = $_SERVER['HTTP_REFERER'];
} else {
	$referer = '/system_usermanager.php';
}

if (isset($id) && $a_user[$id]) {
	$pconfig['usernamefld'] = $a_user[$id]['name'];
	$pconfig['descr'] = $a_user[$id]['descr'];
	$pconfig['expires'] = $a_user[$id]['expires'];
	$pconfig['customsettings'] = isset($a_user[$id]['customsettings']);
	$pconfig['webguicss'] = $a_user[$id]['webguicss'];
	$pconfig['webguifixedmenu'] = $a_user[$id]['webguifixedmenu'];
	$pconfig['webguihostnamemenu'] = $a_user[$id]['webguihostnamemenu'];
	$pconfig['dashboardcolumns'] = $a_user[$id]['dashboardcolumns'];
	$pconfig['interfacessort'] = isset($a_user[$id]['interfacessort']);
	$pconfig['dashboardavailablewidgetspanel'] = isset($a_user[$id]['dashboardavailablewidgetspanel']);
	$pconfig['systemlogsfilterpanel'] = isset($a_user[$id]['systemlogsfilterpanel']);
	$pconfig['systemlogsmanagelogpanel'] = isset($a_user[$id]['systemlogsmanagelogpanel']);
	$pconfig['statusmonitoringsettingspanel'] = isset($a_user[$id]['statusmonitoringsettingspanel']);
	$pconfig['webguileftcolumnhyper'] = isset($a_user[$id]['webguileftcolumnhyper']);
	$pconfig['disablealiaspopupdetail'] = isset($a_user[$id]['disablealiaspopupdetail']);
	$pconfig['pagenamefirst'] = isset($a_user[$id]['pagenamefirst']);
	$pconfig['groups'] = local_user_get_groups($a_user[$id]);
	$pconfig['utype'] = $a_user[$id]['scope'];
	$pconfig['uid'] = $a_user[$id]['uid'];
	$pconfig['authorizedkeys'] = base64_decode($a_user[$id]['authorizedkeys']);
	$pconfig['priv'] = $a_user[$id]['priv'];
	$pconfig['ipsecpsk'] = $a_user[$id]['ipsecpsk'];
	$pconfig['disabled'] = isset($a_user[$id]['disabled']);
}

/*
 * Check user privileges to test if the user is allowed to make changes.
 * Otherwise users can end up in an inconsistent state where some changes are
 * performed and others denied. See https://redmine.pfsense.org/issues/9259
 */
phpsession_begin();
$guiuser = getUserEntry($_SESSION['Username']);
$read_only = (is_array($guiuser) && userHasPrivilege($guiuser, "user-config-readonly"));
phpsession_end();

if (!empty($_POST) && $read_only) {
	$input_errors = array(gettext("Insufficient privileges to make the requested change (read only)."));
}

if (($_POST['act'] == "deluser") && !$read_only) {

	if (!isset($_POST['username']) || !isset($a_user[$id]) || ($_POST['username'] != $a_user[$id]['name'])) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	if ($_POST['username'] == $_SESSION['Username']) {
		$delete_errors[] = sprintf(gettext("Cannot delete user %s because you are currently logged in as that user."), $_POST['username']);
	} else {
		local_user_del($a_user[$id]);
		$userdeleted = $a_user[$id]['name'];
		unset($a_user[$id]);
		/* Reindex the array to avoid operating on an incorrect index https://redmine.pfsense.org/issues/7733 */
		$a_user = array_values($a_user);
		$savemsg = sprintf(gettext("Successfully deleted user: %s"), $userdeleted);
		write_config($savemsg);
		syslog($logging_level, "{$logging_prefix}: {$savemsg}");
	}

} else if ($act == "new") {
	/*
	 * set this value cause the text field is read only
	 * and the user should not be able to mess with this
	 * setting.
	 */
	$pconfig['utype'] = "user";
	$pconfig['lifetime'] = 3650;

	$nonPrvCas = array();
	if (is_array($config['ca']) && count($config['ca']) > 0) {
		foreach ($config['ca'] as $ca) {
			if (!$ca['prv']) {
				continue;
			}

			$nonPrvCas[ $ca['refid'] ] = $ca['descr'];
		}
	}

}

if (isset($_POST['dellall']) && !$read_only) {

	$del_users = $_POST['delete_check'];
	$deleted_users = array();

	if (!empty($del_users)) {
		foreach ($del_users as $userid) {
			if (isset($a_user[$userid]) && $a_user[$userid]['scope'] != "system") {
				if ($a_user[$userid]['name'] == $_SESSION['Username']) {
					$delete_errors[] = sprintf(gettext("Cannot delete user %s because you are currently logged in as that user."), $a_user[$userid]['name']);
				} else {
					$deleted_users[] = $a_user[$userid]['name'];
					local_user_del($a_user[$userid]);
					unset($a_user[$userid]);
				}
			} else {
				$delete_errors[] = sprintf(gettext("Cannot delete user %s because it is a system user."), $a_user[$userid]['name']);
			}
		}

		if (count($deleted_users) > 0) {
			$savemsg = sprintf(gettext("Successfully deleted %s: %s"), (count($deleted_users) == 1) ? gettext("user") : gettext("users"), implode(', ', $deleted_users));
			/* Reindex the array to avoid operating on an incorrect index https://redmine.pfsense.org/issues/7733 */
			$a_user = array_values($a_user);
			write_config($savemsg);
			syslog($logging_level, "{$logging_prefix}: {$savemsg}");
		}
	}
}

if (($_POST['act'] == "delcert") && !$read_only) {

	if (!$a_user[$id]) {
		pfSenseHeader("system_usermanager.php");
		exit;
	}

	$certdeleted = lookup_cert($a_user[$id]['cert'][$_POST['certid']]);
	$certdeleted = $certdeleted['descr'];
	unset($a_user[$id]['cert'][$_POST['certid']]);
	$savemsg = sprintf(gettext("Removed certificate association \"%s\" from user %s"), $certdeleted, $a_user[$id]['name']);
	write_config($savemsg);
	syslog($logging_level, "{$logging_prefix}: {$savemsg}");
	$_POST['act'] = "edit";
}

if (($_POST['act'] == "delprivid") && !$read_only) {
	$privdeleted = $priv_list[$a_user[$id]['priv'][$_POST['privid']]]['name'];
	unset($a_user[$id]['priv'][$_POST['privid']]);
	local_user_set($a_user[$id]);
	$savemsg = sprintf(gettext("Removed Privilege \"%s\" from user %s"), $privdeleted, $a_user[$id]['name']);
	write_config($savemsg);
	syslog($logging_level, "{$logging_prefix}: {$savemsg}");
	$_POST['act'] = "edit";
}

if ($_POST['save'] && !$read_only) {
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

	if (strlen($_POST['usernamefld']) > 32) {
		$input_errors[] = gettext("The username is longer than 32 characters.");
	}

	if (($_POST['passwordfld1']) && ($_POST['passwordfld1'] != $_POST['passwordfld2'])) {
		$input_errors[] = gettext("The passwords do not match.");
	}

	if (isset($_POST['ipsecpsk']) && !preg_match('/^[[:ascii:]]*$/', $_POST['ipsecpsk'])) {
		$input_errors[] = gettext("IPsec Pre-Shared Key contains invalid characters.");
	}

	/* Check the POSTed groups to ensure they are valid and exist */
	if (is_array($_POST['groups'])) {
		foreach ($_POST['groups'] as $newgroup) {
			if (empty(getGroupEntry($newgroup))) {
				$input_errors[] = gettext("One or more invalid groups was submitted.");
			}
		}
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
	validate_webguicss_field($input_errors, $_POST['webguicss']);
	validate_webguifixedmenu_field($input_errors, $_POST['webguifixedmenu']);
	validate_webguihostnamemenu_field($input_errors, $_POST['webguihostnamemenu']);
	validate_dashboardcolumns_field($input_errors, $_POST['dashboardcolumns']);

	if (!$input_errors) {

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

		/* only change description if sent */
		if (isset($_POST['descr'])) {
			$userent['descr'] = $_POST['descr'];
		}

		$userent['name'] = $_POST['usernamefld'];
		$userent['expires'] = $_POST['expires'];
		$userent['dashboardcolumns'] = $_POST['dashboardcolumns'];
		$userent['authorizedkeys'] = base64_encode($_POST['authorizedkeys']);
		$userent['ipsecpsk'] = $_POST['ipsecpsk'];

		if ($_POST['disabled']) {
			$userent['disabled'] = true;
		} else {
			unset($userent['disabled']);
		}

		if ($_POST['customsettings']) {
			$userent['customsettings'] = true;
		} else {
			unset($userent['customsettings']);
		}

		if ($_POST['webguicss']) {
			$userent['webguicss'] = $_POST['webguicss'];
		} else {
			unset($userent['webguicss']);
		}

		if ($_POST['webguifixedmenu']) {
			$userent['webguifixedmenu'] = $_POST['webguifixedmenu'];
		} else {
			unset($userent['webguifixedmenu']);
		}

		if ($_POST['webguihostnamemenu']) {
			$userent['webguihostnamemenu'] = $_POST['webguihostnamemenu'];
		} else {
			unset($userent['webguihostnamemenu']);
		}

		if ($_POST['interfacessort']) {
			$userent['interfacessort'] = true;
		} else {
			unset($userent['interfacessort']);
		}

		if ($_POST['dashboardavailablewidgetspanel']) {
			$userent['dashboardavailablewidgetspanel'] = true;
		} else {
			unset($userent['dashboardavailablewidgetspanel']);
		}

		if ($_POST['systemlogsfilterpanel']) {
			$userent['systemlogsfilterpanel'] = true;
		} else {
			unset($userent['systemlogsfilterpanel']);
		}

		if ($_POST['systemlogsmanagelogpanel']) {
			$userent['systemlogsmanagelogpanel'] = true;
		} else {
			unset($userent['systemlogsmanagelogpanel']);
		}

		if ($_POST['statusmonitoringsettingspanel']) {
			$userent['statusmonitoringsettingspanel'] = true;
		} else {
			unset($userent['statusmonitoringsettingspanel']);
		}

		if ($_POST['webguileftcolumnhyper']) {
			$userent['webguileftcolumnhyper'] = true;
		} else {
			unset($userent['webguileftcolumnhyper']);
		}

		if ($_POST['disablealiaspopupdetail']) {
			$userent['disablealiaspopupdetail'] = true;
		} else {
			unset($userent['disablealiaspopupdetail']);
		}

		if ($_POST['pagenamefirst']) {
			$userent['pagenamefirst'] = true;
		} else {
			unset($userent['pagenamefirst']);
		}

		if (isset($id) && $a_user[$id]) {
			$a_user[$id] = $userent;
		} else {
			if (!empty($_POST['name'])) {
				$cert = array();
				$cert['refid'] = uniqid();
				$userent['cert'] = array();

				$cert['descr'] = $_POST['name'];

				$subject = cert_get_subject_hash($ca['crt']);

				$dn = array();
				if (!empty($subject['C'])) {
					$dn['countryName'] = $subject['C'];
				}
				if (!empty($subject['ST'])) {
					$dn['stateOrProvinceName'] = $subject['ST'];
				}
				if (!empty($subject['L'])) {
					$dn['localityName'] = $subject['L'];
				}
				if (!empty($subject['O'])) {
					$dn['organizationName'] = $subject['O'];
				}
				if (!empty($subject['OU'])) {
					$dn['organizationalUnitName'] = $subject['OU'];
				}
				$dn['commonName'] = $userent['name'];
				$cn_altname = cert_add_altname_type($userent['name']);
				if (!empty($cn_altname)) {
					$dn['subjectAltName'] = $cn_altname;
				}

				cert_create($cert, $_POST['caref'], $_POST['keylen'],
					(int)$_POST['lifetime'], $dn, $_POST['type'],
					$_POST['digest_alg'], $_POST['keytype'],
				       	$_POST['ecname']);

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

		/* Sort it alphabetically */
		usort($config['system']['user'], function($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		local_user_set_groups($userent, $_POST['groups']);
		local_user_set($userent);

		/* Update user index to account for new changes */
		global $userindex;
		$userindex = index_users();

		$savemsg = sprintf(gettext("Successfully %s user %s"), (isset($id)) ? gettext("edited") : gettext("created"), $userent['name']);
		write_config($savemsg);
		syslog($logging_level, "{$logging_prefix}: {$savemsg}");
		if (is_dir("/etc/inc/privhooks")) {
			run_plugins("/etc/inc/privhooks");
		}

		pfSenseHeader("system_usermanager.php");
	}
}

function build_priv_table() {
	global $a_user, $id, $read_only;

	$privhtml = '<div class="table-responsive">';
	$privhtml .=	'<table class="table table-striped table-hover table-condensed">';
	$privhtml .=		'<thead>';
	$privhtml .=			'<tr>';
	$privhtml .=				'<th>' . gettext('Inherited from') . '</th>';
	$privhtml .=				'<th>' . gettext('Name') . '</th>';
	$privhtml .=				'<th>' . gettext('Description') . '</th>';
	$privhtml .=				'<th>' . gettext('Action') . '</th>';
	$privhtml .=			'</tr>';
	$privhtml .=		'</thead>';
	$privhtml .=		'<tbody>';

	$i = 0;
	$user_has_root_priv = false;

	foreach (get_user_privdesc($a_user[$id]) as $priv) {
		$group = false;
		if ($priv['group']) {
			$group = $priv['group'];
		}

		$privhtml .=		'<tr>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['group']) . '</td>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['name']) . '</td>';
		$privhtml .=			'<td>' . htmlspecialchars($priv['descr']);
		if (isset($priv['warn']) && ($priv['warn'] == 'standard-warning-root')) {
			$privhtml .=			' ' . gettext('(admin privilege)');
			$user_has_root_priv = true;
		}
		$privhtml .=			'</td>';
		$privhtml .=			'<td>';
		if (!$group && !$read_only) {
			$privhtml .=			'<a class="fa fa-trash no-confirm icon-pointer" title="' . gettext('Delete Privilege') . '" id="delprivid' . $i . '"></a>';
		}

		$privhtml .=			'</td>';
		$privhtml .=		'</tr>';

		if (!$group) {
			$i++;
		}
	}

	if ($user_has_root_priv) {
		$privhtml .=		'<tr>';
		$privhtml .=			'<td colspan="3">';
		$privhtml .=				'<b>' . gettext('Security notice: This user effectively has administrator-level access') . '</b>';
		$privhtml .=			'</td>';
		$privhtml .=			'<td>';
		$privhtml .=			'</td>';
		$privhtml .=		'</tr>';

	}

	$privhtml .=		'</tbody>';
	$privhtml .=	'</table>';
	$privhtml .= '</div>';

	$privhtml .= '<nav class="action-buttons">';
	if (!$read_only) {
		$privhtml .=	'<a href="system_usermanager_addprivs.php?userid=' . $id . '" class="btn btn-success"><i class="fa fa-plus icon-embed-btn"></i>' . gettext("Add") . '</a>';
	}
	$privhtml .= '</nav>';

	return($privhtml);
}

function build_cert_table() {
	global $a_user, $id, $read_only;

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
			if (!$read_only) {
				$certhtml .=			'<a id="delcert' . $i .'" class="fa fa-trash no-confirm icon-pointer" title="';
				$certhtml .=			gettext('Remove this certificate association? (Certificate will not be deleted)') . '"></a>';
			}
			$certhtml .=		'</td>';
			$certhtml .=	'</tr>';
			$i++;
		}

	}

	$certhtml .=		'</tbody>';
	$certhtml .=	'</table>';
	$certhtml .= '</div>';

	$certhtml .= '<nav class="action-buttons">';
	if (!$read_only) {
		$certhtml .=	'<a href="system_certmanager.php?act=new&amp;userid=' . $id . '" class="btn btn-success"><i class="fa fa-plus icon-embed-btn"></i>' . gettext("Add") . '</a>';
	}
	$certhtml .= '</nav>';

	return($certhtml);
}

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Users"));
$pglinks = array("", "system_usermanager.php", "system_usermanager.php");

if ($act == "new" || $act == "edit" || $input_errors) {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}

include("head.inc");

if ($delete_errors) {
	print_input_errors($delete_errors);
}

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
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

if (!($act == "new" || $act == "edit" || $input_errors)) {
?>
<form method="post">
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Users')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th><?=gettext("Username")?></th>
						<th><?=gettext("Full name")?></th>
						<th><?=gettext("Status")?></th>
						<th><?=gettext("Groups")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php
foreach ($a_user as $i => $userent):
	?>
					<tr>
						<td>
							<input type="checkbox" id="frc<?=$i?>" name="delete_check[]" value="<?=$i?>" <?=((($userent['scope'] == "system") || ($userent['name'] == $_SESSION['Username'])) ? 'disabled' : '')?>/>
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
						<td><i class="fa fa-<?= (isset($userent['disabled'])) ? 'ban" title="' . gettext("Disabled") . '"' : 'check" title="' . gettext("Enabled") . '"' ; ?>><span style='display: none'><?= (isset($userent['disabled'])) ? gettext("Disabled") : gettext("Enabled") ; ?></span></i></td>
						<td><?=implode(",", local_user_get_groups($userent))?></td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext("Edit user"); ?>" href="?act=edit&amp;userid=<?=$i?>"></a>
<?php if (($userent['scope'] != "system") && ($userent['name'] != $_SESSION['Username']) && !$read_only): ?>
							<a class="fa fa-trash"	title="<?=gettext("Delete user")?>" href="?act=deluser&amp;userid=<?=$i?>&amp;username=<?=$userent['name']?>" usepost></a>
<?php endif; ?>
						</td>
					</tr>
<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<nav class="action-buttons">
	<?php if (!$read_only): ?>

	<a href="?act=new" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>

	<button type="submit" class="btn btn-sm btn-danger" name="dellall" value="dellall" title="<?=gettext('Delete selected users')?>">
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext("Delete")?>
	</button>
	<?php endif; ?>

</nav>
</form>
<div class="infoblock">
<?php
	print_callout('<p>' . gettext("Additional users can be added here. User permissions for accessing " .
		"the webConfigurator can be assigned directly or inherited from group memberships. " .
		"Some system object properties can be modified but they cannot be deleted.") . '</p>' .
		'<p>' . gettext("Accounts added here are also used for other parts of the system " .
		"such as OpenVPN, IPsec, and Captive Portal.") . '</p>'
	);

?></div>

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
		'*Username',
		'text',
		$pconfig['usernamefld'],
		['autocomplete' => 'new-password']
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

	if ($act == "edit") {
		$pwd_required = "";
	} else {
		$pwd_required = "*";
	}

	$group = new Form_Group($pwd_required . 'Password');
	$group->add(new Form_Input(
		'passwordfld1',
		'Password',
		'password',
		null,
		['autocomplete' => 'new-password']
	));
	$group->add(new Form_Input(
		'passwordfld2',
		'Confirm Password',
		'password',
		null,
		['autocomplete' => 'new-password']
	));

	$section->add($group);

	$section->addInput($input = new Form_Input(
		'descr',
		'Full name',
		'text',
		htmlspecialchars($pconfig['descr'])
	))->setHelp('User\'s full name, for administrative information only');

	if ($ro) {
		$input->setDisabled();
	}

	$section->addInput(new Form_Input(
		'expires',
		'Expiration date',
		'text',
		$pconfig['expires']
	))->setHelp('Leave blank if the account shouldn\'t expire, otherwise enter '.
		'the expiration date as MM/DD/YYYY');

	$section->addInput(new Form_Checkbox(
		'customsettings',
		'Custom Settings',
		'Use individual customized GUI options and dashboard layout for this user.',
		$pconfig['customsettings']
	));

	gen_user_settings_fields($section, $pconfig);

	// ==== Group membership ==================================================
	$group = new Form_Group('Group membership');

	// Make a list of all the groups configured on the system, and a list of
	// those which this user is a member of
	$systemGroups = array();
	$usersGroups = array();

	$usergid = [$pconfig['usernamefld']];

	foreach ($config['system']['group'] as $Ggroup) {
		if ($Ggroup['name'] != "all") {
			if (($act == 'edit' || $input_errors) && $Ggroup['member'] && in_array($a_user[$id]['uid'], $Ggroup['member'])) {
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
		'Move to "Member of" list',
		null,
		'fa-angle-double-right'
	))->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-info btn-sm');

	$group->add(new Form_Button(
		'movetodisabled',
		'Move to "Not member of" list',
		null,
		'fa-angle-double-left'
	))->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-info btn-sm');

	$group->setHelp('Hold down CTRL (PC)/COMMAND (Mac) key to select multiple items.');
	$section->add($group);

	// ==== Button for adding user certificate ================================
	if ($act == 'new') {
		if (count($nonPrvCas) > 0) {
			$section->addInput(new Form_Checkbox(
				'showcert',
				'Certificate',
				'Click to create a user certificate',
				false
			));
		} else {
			$section->addInput(new Form_StaticText(
				'Certificate',
				gettext('No private CAs found. A private CA is required to create a new user certificate. ' .
					'Save the user first to import an external certificate.')
			));
		}
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
		$section = new Form_Section('User Certificates');

		$section->addInput(new Form_StaticText(
			null,
			build_cert_table()
		));

		$form->add($section);
	}

	// ==== Add user certificate for a new user
	if (is_array($config['ca']) && count($config['ca']) > 0) {
		$section = new Form_Section('Create Certificate for User');
		$section->addClass('cert-options');

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
				'keytype',
				'*Key type',
				$pconfig['keytype'],
				array_combine($cert_keytypes, $cert_keytypes)
			));

			$group = new Form_Group($i == 0 ? '*Key length':'');
			$group->addClass('rsakeys');
			$group->add(new Form_Select(
				'keylen',
				null,
				$pconfig['keylen'],
				array_combine($cert_keylens, $cert_keylens)
			))->setHelp('The length to use when generating a new RSA key, in bits. %1$s' .
				'The Key Length should not be lower than 2048 or some platforms ' .
				'may consider the certificate invalid.', '<br/>');
			$section->add($group);

			$group = new Form_Group($i == 0 ? '*Elliptic Curve Name':'');
			$group->addClass('ecnames');
			$group->add(new Form_Select(
				'ecname',
				null,
				$pconfig['ecname'],
				$openssl_ecnames
			))->setHelp('Curves may not be compatible with all uses. Known compatible curve uses are denoted in brackets.');
			$section->add($group);

			$section->addInput(new Form_Select(
				'csrsign_digest_alg',
				'*Digest Algorithm',
				$pconfig['csrsign_digest_alg'],
				array_combine($openssl_digest_algs, $openssl_digest_algs)
			))->setHelp('The digest method used when the certificate is signed. %1$s' .
				'The best practice is to use an algorithm stronger than SHA1. '.
				'Some platforms may consider weaker digest algorithms invalid', '<br/>');

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

$csswarning = sprintf(gettext("%sUser-created themes are unsupported, use at your own risk."), "<br />");
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function setcustomoptions() {
		var adv = $('#customsettings').prop('checked');

		hideInput('webguicss', !adv);
		hideInput('webguifixedmenu', !adv);
		hideInput('webguihostnamemenu', !adv);
		hideInput('dashboardcolumns', !adv);
		hideCheckbox('interfacessort', !adv);
		hideCheckbox('dashboardavailablewidgetspanel', !adv);
		hideCheckbox('systemlogsfilterpanel', !adv);
		hideCheckbox('systemlogsmanagelogpanel', !adv);
		hideCheckbox('statusmonitoringsettingspanel', !adv);
		hideCheckbox('webguileftcolumnhyper', !adv);
		hideCheckbox('disablealiaspopupdetail', !adv);
		hideCheckbox('pagenamefirst', !adv);
	}

	// Handle displaying a warning message if a user-created theme is selected.
	function setThemeWarning() {
		if ($('#webguicss').val().startsWith("pfSense")) {
			$('#csstxt').html("").addClass("text-default");
		} else {
			$('#csstxt').html("<?=$csswarning?>").addClass("text-danger");
		}
	}

	function change_keytype() {
		hideClass('rsakeys', ($('#keytype').val() != 'RSA'));
		hideClass('ecnames', ($('#keytype').val() != 'ECDSA'));
	}

	$('#webguicss').change(function() {
		setThemeWarning();
	});

	setThemeWarning();

	// On click . .
	$('#customsettings').click(function () {
		setcustomoptions();
	});

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

	$('#expires').datepicker();

	$('#keytype').change(function () {
		change_keytype();
	});

	// ---------- On initial page load ------------------------------------------------------------

	hideClass('cert-options', true);
	//hideInput('authorizedkeys', true);
	hideCheckbox('showkey', true);
	setcustomoptions();
	change_keytype();

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
