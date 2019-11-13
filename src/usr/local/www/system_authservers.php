<?php
/*
 * system_authservers.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-system-authservers
##|*NAME=System: Authentication Servers
##|*DESCR=Allow access to the 'System: Authentication Servers' page.
##|*WARN=standard-warning-root
##|*MATCH=system_authservers.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("auth.inc");
require_once("pfsense-utils.inc");

// Have we been called to populate the "Select a container" modal?
if ($_REQUEST['ajax']) {

	$ous = array();
	$authcfg = array();

	$authcfg['ldap_port'] = $_REQUEST['port'];
	$authcfg['ldap_basedn'] = $_REQUEST['basedn'];
	$authcfg['host'] = $_REQUEST['host'];
	$authcfg['ldap_scope'] = $_REQUEST['scope'];
	$authcfg['ldap_binddn'] = $_REQUEST['binddn'];
	$authcfg['ldap_bindpw'] = $_REQUEST['bindpw'];
	$authcfg['ldap_urltype'] = $_REQUEST['urltype'];
	$authcfg['ldap_protver'] = $_REQUEST['proto'];
	$authcfg['ldap_authcn'] = explode(";", $_REQUEST['authcn']);
	$authcfg['ldap_caref'] = $_REQUEST['cert'];

	$ous = ldap_get_user_ous(true, $authcfg);

	if (empty($ous)) {
		print('<span class="text-danger">Could not connect to the LDAP server. Please check the LDAP configuration.</span>');
	} else {
		$modal = new Modal("Select LDAP containers for authentication", "containers", true);
		$group = new Form_MultiCheckboxGroup('Containers');

		if (is_array($ous)) {
			$idx = 0;

			foreach ($ous as $ou) {
				$group->add(new Form_MultiCheckbox(
					'ou' . $idx,
					'',
					$ou,
					in_array($ou, $authcfg['ldap_authcn']),
					$ou
				));

				$idx++;
			}
		}

		$modal->add($group);

		// Create a "Save button"

		$btnsv = new Form_Button(
			'svcontbtn',
			'Save',
			null,
			'fa-save'
		);

		$btnsv->removeClass("btn-default)")->addClass("btn-primary");

		$modal->addInput(new Form_StaticText(
			'',
			$btnsv
		));

		print($modal);
	}

	exit;
}

$id = $_REQUEST['id'];

if (!is_array($config['system']['authserver'])) {
	$config['system']['authserver'] = array();
}

$a_server = array_values(auth_get_authserver_list());

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

$act = $_REQUEST['act'];

if ($_POST['act'] == "del") {

	if (!$a_server[$_POST['id']]) {
		pfSenseHeader("system_authservers.php");
		exit;
	}

	/* Remove server from main list. */
	$serverdeleted = $a_server[$_POST['id']]['name'];
	foreach ($config['system']['authserver'] as $k => $as) {
		if ($config['system']['authserver'][$k]['name'] == $serverdeleted) {
			unset($config['system']['authserver'][$k]);
		}
	}

	/* Remove server from temp list used later on this page. */
	unset($a_server[$_POST['id']]);
	$a_server = array_values($a_server);

	$savemsg = sprintf(gettext("Authentication Server %s deleted."), htmlspecialchars($serverdeleted));
	write_config($savemsg);
}

if ($act == "edit") {
	if (isset($id) && $a_server[$id]) {

		$pconfig['type'] = $a_server[$id]['type'];
		$pconfig['name'] = $a_server[$id]['name'];

		if ($pconfig['type'] == "ldap") {
			$pconfig['ldap_caref'] = $a_server[$id]['ldap_caref'];
			$pconfig['ldap_host'] = $a_server[$id]['host'];
			$pconfig['ldap_port'] = $a_server[$id]['ldap_port'];
			$pconfig['ldap_timeout'] = $a_server[$id]['ldap_timeout'];
			$pconfig['ldap_urltype'] = $a_server[$id]['ldap_urltype'];
			$pconfig['ldap_protver'] = $a_server[$id]['ldap_protver'];
			$pconfig['ldap_scope'] = $a_server[$id]['ldap_scope'];
			$pconfig['ldap_basedn'] = $a_server[$id]['ldap_basedn'];
			$pconfig['ldap_authcn'] = $a_server[$id]['ldap_authcn'];
			$pconfig['ldap_extended_enabled'] = $a_server[$id]['ldap_extended_enabled'];
			$pconfig['ldap_extended_query'] = $a_server[$id]['ldap_extended_query'];
			$pconfig['ldap_binddn'] = $a_server[$id]['ldap_binddn'];
			$pconfig['ldap_bindpw'] = $a_server[$id]['ldap_bindpw'];
			$pconfig['ldap_attr_user'] = $a_server[$id]['ldap_attr_user'];
			$pconfig['ldap_attr_group'] = $a_server[$id]['ldap_attr_group'];
			$pconfig['ldap_attr_member'] = $a_server[$id]['ldap_attr_member'];
			$pconfig['ldap_attr_groupobj'] = $a_server[$id]['ldap_attr_groupobj'];
			$pconfig['ldap_utf8'] = isset($a_server[$id]['ldap_utf8']);
			$pconfig['ldap_nostrip_at'] = isset($a_server[$id]['ldap_nostrip_at']);
			$pconfig['ldap_allow_unauthenticated'] = isset($a_server[$id]['ldap_allow_unauthenticated']);
			$pconfig['ldap_rfc2307'] = isset($a_server[$id]['ldap_rfc2307']);

			if (!$pconfig['ldap_binddn'] || !$pconfig['ldap_bindpw']) {
				$pconfig['ldap_anon'] = true;
			}
		}

		if ($pconfig['type'] == "radius") {
			$pconfig['radius_protocol'] = $a_server[$id]['radius_protocol'];
			$pconfig['radius_host'] = $a_server[$id]['host'];
			$pconfig['radius_nasip_attribute'] = $a_server[$id]['radius_nasip_attribute'];
			$pconfig['radius_auth_port'] = $a_server[$id]['radius_auth_port'];
			$pconfig['radius_acct_port'] = $a_server[$id]['radius_acct_port'];
			$pconfig['radius_secret'] = $a_server[$id]['radius_secret'];
			$pconfig['radius_timeout'] = $a_server[$id]['radius_timeout'];

			if ($pconfig['radius_auth_port'] &&
				$pconfig['radius_acct_port']) {
				$pconfig['radius_srvcs'] = "both";
			}

			if ($pconfig['radius_auth_port'] &&
				!$pconfig['radius_acct_port']) {
				$pconfig['radius_srvcs'] = "auth";
				$pconfig['radius_acct_port'] = 1813;
			}

			if (!$pconfig['radius_auth_port'] &&
				$pconfig['radius_acct_port']) {
				$pconfig['radius_srvcs'] = "acct";
				$pconfig['radius_auth_port'] = 1812;
			}

		}
	}
}

if ($act == "new") {
	$pconfig['ldap_protver'] = 3;
	$pconfig['ldap_anon'] = true;
	$pconfig['radius_protocol'] = "MSCHAPv2";
	$pconfig['radius_srvcs'] = "both";
	$pconfig['radius_auth_port'] = "1812";
	$pconfig['radius_acct_port'] = "1813";
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */

	if ($pconfig['type'] == "ldap") {
		$reqdfields = explode(" ",
			"name type ldap_host ldap_port " .
			"ldap_urltype ldap_protver ldap_scope " .
			"ldap_attr_user ldap_attr_group ldap_attr_member ldapauthcontainers");

		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Type"),
			gettext("Hostname or IP"),
			gettext("Port value"),
			gettext("Transport"),
			gettext("Protocol version"),
			gettext("Search level"),
			gettext("User naming Attribute"),
			gettext("Group naming Attribute"),
			gettext("Group member attribute"),
			gettext("Authentication container"));

		if (!$pconfig['ldap_anon']) {
			$reqdfields[] = "ldap_binddn";
			$reqdfields[] = "ldap_bindpw";
			$reqdfieldsn[] = gettext("Bind user DN");
			$reqdfieldsn[] = gettext("Bind Password");
		}
	}

	if ($pconfig['type'] == "radius") {
		$reqdfields = explode(" ", "name type radius_protocol radius_host radius_srvcs");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Type"),
			gettext("Radius Protocol"),
			gettext("Hostname or IP"),
			gettext("Services"));

		if ($pconfig['radius_srvcs'] == "both" ||
			$pconfig['radius_srvcs'] == "auth") {
			$reqdfields[] = "radius_auth_port";
			$reqdfieldsn[] = gettext("Authentication port");
		}

		if ($pconfig['radius_srvcs'] == "both" ||
			$pconfig['radius_srvcs'] == "acct") {
			$reqdfields[] = "radius_acct_port";
			$reqdfieldsn[] = gettext("Accounting port");
		}

		if (!isset($id)) {
			$reqdfields[] = "radius_secret";
			$reqdfieldsn[] = gettext("Shared Secret");
		}
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['host'])) {
		$input_errors[] = gettext("The host name contains invalid characters.");
	}

	if (auth_get_authserver($pconfig['name']) && !isset($id)) {
		$input_errors[] = gettext("An authentication server with the same name already exists.");
	}

	if (isset($id) && $config['system']['authserver'][$id] &&
	   ($config['system']['authserver'][$id]['name'] != $pconfig['name'])) {
		$input_errors[] = gettext("The name of an authentication server cannot be changed.");
	}

	if (($pconfig['type'] == "ldap") || ($pconfig['type'] == "radius")) {
		$to_field = "{$pconfig['type']}_timeout";
		if (isset($_POST[$to_field]) && !empty($_POST[$to_field]) && (!is_numeric($_POST[$to_field]) || (is_numeric($_POST[$to_field]) && ($_POST[$to_field] <= 0)))) {
			$input_errors[] = sprintf(gettext("%s Timeout value must be numeric and positive."), strtoupper($pconfig['type']));
		}
	}

	// https://redmine.pfsense.org/issues/4154
	if ($pconfig['type'] == "radius") {
		if (is_ipaddrv6($_POST['radius_host'])) {
			$input_errors[] = gettext("IPv6 does not work for RADIUS authentication, see Bug #4154.");
		}
	}

	if (!$input_errors) {
		$server = array();
		$server['refid'] = uniqid();
		if (isset($id) && $a_server[$id]) {
			$server = $a_server[$id];
		}

		$server['type'] = $pconfig['type'];
		$server['name'] = $pconfig['name'];

		if ($server['type'] == "ldap") {

			if (!empty($pconfig['ldap_caref'])) {
				$server['ldap_caref'] = $pconfig['ldap_caref'];
			}
			$server['host'] = $pconfig['ldap_host'];
			$server['ldap_port'] = $pconfig['ldap_port'];
			$server['ldap_urltype'] = $pconfig['ldap_urltype'];
			$server['ldap_protver'] = $pconfig['ldap_protver'];
			$server['ldap_scope'] = $pconfig['ldap_scope'];
			$server['ldap_basedn'] = $pconfig['ldap_basedn'];
			$server['ldap_authcn'] = $pconfig['ldapauthcontainers'];
			$server['ldap_extended_enabled'] = $pconfig['ldap_extended_enabled'];
			$server['ldap_extended_query'] = $pconfig['ldap_extended_query'];
			$server['ldap_attr_user'] = $pconfig['ldap_attr_user'];
			$server['ldap_attr_group'] = $pconfig['ldap_attr_group'];
			$server['ldap_attr_member'] = $pconfig['ldap_attr_member'];

			$server['ldap_attr_groupobj'] = empty($pconfig['ldap_attr_groupobj']) ? "posixGroup" : $pconfig['ldap_attr_groupobj'];

			if ($pconfig['ldap_utf8'] == "yes") {
				$server['ldap_utf8'] = true;
			} else {
				unset($server['ldap_utf8']);
			}
			if ($pconfig['ldap_nostrip_at'] == "yes") {
				$server['ldap_nostrip_at'] = true;
			} else {
				unset($server['ldap_nostrip_at']);
			}
			if ($pconfig['ldap_allow_unauthenticated'] == "yes") {
				$server['ldap_allow_unauthenticated'] = true;
			} else {
				unset($server['ldap_allow_unauthenticated']);
			}
			if ($pconfig['ldap_rfc2307'] == "yes") {
				$server['ldap_rfc2307'] = true;
			} else {
				unset($server['ldap_rfc2307']);
			}


			if (!$pconfig['ldap_anon']) {
				$server['ldap_binddn'] = $pconfig['ldap_binddn'];
				$server['ldap_bindpw'] = $pconfig['ldap_bindpw'];
			} else {
				unset($server['ldap_binddn']);
				unset($server['ldap_bindpw']);
			}

			if ($pconfig['ldap_timeout']) {
				$server['ldap_timeout'] = $pconfig['ldap_timeout'];
			} else {
				$server['ldap_timeout'] = 25;
			}
		}

		if ($server['type'] == "radius") {

			$server['radius_protocol'] = $pconfig['radius_protocol'];
			$server['host'] = $pconfig['radius_host'];
			$server['radius_nasip_attribute'] = $pconfig['radius_nasip_attribute'];

			if ($pconfig['radius_secret']) {
				$server['radius_secret'] = $pconfig['radius_secret'];
			}

			if ($pconfig['radius_timeout']) {
				$server['radius_timeout'] = $pconfig['radius_timeout'];
			} else {
				$server['radius_timeout'] = 5;
			}

			if ($pconfig['radius_srvcs'] == "both") {
				$server['radius_auth_port'] = $pconfig['radius_auth_port'];
				$server['radius_acct_port'] = $pconfig['radius_acct_port'];
			}

			if ($pconfig['radius_srvcs'] == "auth") {
				$server['radius_auth_port'] = $pconfig['radius_auth_port'];
				unset($server['radius_acct_port']);
			}

			if ($pconfig['radius_srvcs'] == "acct") {
				$server['radius_acct_port'] = $pconfig['radius_acct_port'];
				unset($server['radius_auth_port']);
			}
		}

		if (isset($id) && $config['system']['authserver'][$id]) {
			$config['system']['authserver'][$id] = $server;
		} else {
			$config['system']['authserver'][] = $server;
		}

		write_config();

		pfSenseHeader("system_authservers.php");
	}
}

function build_radiusnas_list() {
	global $config;
	$list = array();

	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $ifdesc => $ifdescr) {
		$ipaddr = get_interface_ip($ifdesc);
		if (is_ipaddr($ipaddr)) {
			$list[$ifdesc] = $ifdescr . ' - ' . $ipaddr;
		}
	}

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $sn) {
			if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
				$start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
				$end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
				$len = $end - $start;

				for ($i = 0; $i <= $len; $i++) {
					$snip = long2ip32($start+$i);
					$list[$snip] = $sn['descr'] . ' - ' . $snip;
				}
			} else {
				$list[$sn['subnet']] = $sn['descr'] . ' - ' . $sn['subnet'];
			}
		}
	}

	return($list);
}

// On error, restore the form contents so the user doesn't have to re-enter too much
if ($_POST && $input_errors) {
	$pconfig = $_POST;
	$pconfig['ldap_authcn'] = $_POST['ldapauthcontainers'];
	$pconfig['ldap_template'] = $_POST['ldap_tmpltype'];
}

$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Authentication Servers"));
$pglinks = array("", "system_usermanager.php", "system_authservers.php");

if ($act == "new" || $act == "edit" || $input_errors) {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
$shortcut_section = "authentication";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
if (!isAllowedPage("system_usermanager.php")) {
       $tab_array[] = array(gettext("User Password"), false, "system_usermanager_passwordmg.php");
} else {
       $tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
}
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), false, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), true, "system_authservers.php");
display_top_tabs($tab_array);

if (!($act == "new" || $act == "edit" || $input_errors)) {
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Authentication Servers')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Server Name")?></th>
						<th><?=gettext("Type")?></th>
						<th><?=gettext("Host Name")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
			<?php foreach ($a_server as $i => $server): ?>
					<tr>
						<td><?=htmlspecialchars($server['name'])?></td>
						<td><?=htmlspecialchars($auth_server_types[$server['type']])?></td>
						<td><?=htmlspecialchars($server['host'])?></td>
						<td>
						<?php if ($i < (count($a_server) - 1)): ?>
							<a class="fa fa-pencil" title="<?=gettext("Edit server"); ?>" href="system_authservers.php?act=edit&amp;id=<?=$i?>"></a>
							<a class="fa fa-trash"  title="<?=gettext("Delete server")?>" href="system_authservers.php?act=del&amp;id=<?=$i?>" usepost></a>
						<?php endif?>
						</td>
					</tr>
			<?php endforeach; ?>
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
	include("foot.inc");
	exit;
}

$form = new Form;
$form->setAction('system_authservers.php?act=edit');

$form->addGlobal(new Form_Input(
	'userid',
	null,
	'hidden',
	$id
));

$section = new Form_Section('Server Settings');

$section->addInput($input = new Form_Input(
	'name',
	'*Descriptive name',
	'text',
	$pconfig['name']
));

$section->addInput($input = new Form_Select(
	'type',
	'*Type',
	$pconfig['type'],
	$auth_server_types
))->toggles();

$form->add($section);

// ==== LDAP settings =========================================================
$section = new Form_Section('LDAP Server Settings');
$section->addClass('toggle-ldap collapse');

if (!isset($pconfig['type']) || $pconfig['type'] == 'ldap')
	$section->addClass('in');

$section->addInput(new Form_Input(
	'ldap_host',
	'*Hostname or IP address',
	'text',
	$pconfig['ldap_host']
))->setHelp('NOTE: When using SSL or STARTTLS, this hostname MUST match the Common Name '.
	'(CN) of the LDAP server\'s SSL Certificate.');

$section->addInput(new Form_Input(
	'ldap_port',
	'*Port value',
	'number',
	$pconfig['ldap_port']
));

$section->addInput(new Form_Select(
	'ldap_urltype',
	'*Transport',
	$pconfig['ldap_urltype'],
	array_combine(array_keys($ldap_urltypes), array_keys($ldap_urltypes))
));

if (empty($a_ca))
{
	$section->addInput(new Form_StaticText(
		'Peer Certificate Authority',
		'No Certificate Authorities defined.<br/>Create one under <a href="system_camanager.php">System &gt; Cert. Manager</a>.'
	));
}
else
{
	$ldapCaRef = array( 'global' => 'Global Root CA List' );
	foreach ($a_ca as $ca)
		$ldapCaRef[ $ca['refid'] ] = $ca['descr'];

	$section->addInput(new Form_Select(
		'ldap_caref',
		'Peer Certificate Authority',
		$pconfig['ldap_caref'],
		$ldapCaRef
	))->setHelp('This option is used if \'SSL Encrypted\' '.
		'or \'TCP - STARTTLS\' options are chosen. '.
		'It must match with the CA in the AD otherwise problems will arise.');
}

$section->addInput(new Form_Select(
	'ldap_protver',
	'*Protocol version',
	$pconfig['ldap_protver'],
	array_combine($ldap_protvers, $ldap_protvers)
));

$section->addInput(new Form_Input(
	'ldap_timeout',
	'Server Timeout',
	'number',
	$pconfig['ldap_timeout'],
	['placeholder' => 25]
))->setHelp('Timeout for LDAP operations (seconds)');

$group = new Form_Group('Search scope');

$SSF = new Form_Select(
	'ldap_scope',
	'*Level',
	$pconfig['ldap_scope'],
	$ldap_scopes
);

$SSB = new Form_Input(
	'ldap_basedn',
	'Base DN',
	'text',
	$pconfig['ldap_basedn']
);


$section->addInput(new Form_StaticText(
	'Search scope',
	'Level ' . $SSF . '<br />' . 'Base DN' . $SSB
));

$group = new Form_Group('*Authentication containers');
$group->add(new Form_Input(
	'ldapauthcontainers',
	'Containers',
	'text',
	$pconfig['ldap_authcn']
))->setHelp('Note: Semi-Colon separated. This will be prepended to the search '.
	'base dn above or the full container path can be specified containing a dc= '.
	'component.%1$sExample: CN=Users;DC=example,DC=com or OU=Staff;OU=Freelancers', '<br/>');

$group->add(new Form_Button(
	'Select',
	'Select a container',
	null,
	'fa-search'
))->setAttribute('type','button')->addClass('btn-info');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'ldap_extended_enabled',
	'Extended query',
	'Enable extended query',
	$pconfig['ldap_extended_enabled']
));

$group = new Form_Group('Query');
$group->addClass('extended');

$group->add(new Form_Input(
	'ldap_extended_query',
	'Query',
	'text',
	$pconfig['ldap_extended_query']
))->setHelp('Example: memberOf=CN=Groupname,OU=MyGroups,DC=example,DC=com');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'ldap_anon',
	'Bind anonymous',
	'Use anonymous binds to resolve distinguished names',
	$pconfig['ldap_anon']
));

$group = new Form_Group('*Bind credentials');
$group->addClass('ldapanon');

$group->add(new Form_Input(
	'ldap_binddn',
	'User DN:',
	'text',
	$pconfig['ldap_binddn']
));

$group->add(new Form_Input(
	'ldap_bindpw',
	'Password',
	'password',
	$pconfig['ldap_bindpw']
));
$section->add($group);

if (!isset($id)) {
	$template_list = array();

	foreach ($ldap_templates as $option => $template) {
		$template_list[$option] = $template['desc'];
	}

	$section->addInput(new Form_Select(
		'ldap_tmpltype',
		'Initial Template',
		$pconfig['ldap_template'],
		$template_list
	));
}

$section->addInput(new Form_Input(
	'ldap_attr_user',
	'*User naming attribute',
	'text',
	$pconfig['ldap_attr_user']
));

$section->addInput(new Form_Input(
	'ldap_attr_group',
	'*Group naming attribute',
	'text',
	$pconfig['ldap_attr_group']
));

$section->addInput(new Form_Input(
	'ldap_attr_member',
	'*Group member attribute',
	'text',
	$pconfig['ldap_attr_member']
));

$section->addInput(new Form_Checkbox(
	'ldap_rfc2307',
	'RFC 2307 Groups',
	'LDAP Server uses RFC 2307 style group membership',
	$pconfig['ldap_rfc2307']
))->setHelp('RFC 2307 style group membership has members listed on the group '.
	'object rather than using groups listed on user object. Leave unchecked '.
	'for Active Directory style group membership (RFC 2307bis).');

$section->addInput(new Form_Input(
	'ldap_attr_groupobj',
	'Group Object Class',
	'text',
	$pconfig['ldap_attr_groupobj'],
	['placeholder' => 'posixGroup']
))->setHelp('Object class used for groups in RFC2307 mode. '.
	'Typically "posixGroup" or "group".');

$section->addInput(new Form_Checkbox(
	'ldap_utf8',
	'UTF8 Encode',
	'UTF8 encode LDAP parameters before sending them to the server.',
	$pconfig['ldap_utf8']
))->setHelp('Required to support international characters, but may not be '.
	'supported by every LDAP server.');

$section->addInput(new Form_Checkbox(
	'ldap_nostrip_at',
	'Username Alterations',
	'Do not strip away parts of the username after the @ symbol',
	$pconfig['ldap_nostrip_at']
))->setHelp('e.g. user@host becomes user when unchecked.');

$section->addInput(new Form_Checkbox(
	'ldap_allow_unauthenticated',
	'Allow unauthenticated bind',
	'Allow unauthenticated bind',
	$pconfig['ldap_allow_unauthenticated'],
))->setHelp('Unauthenticated binds are bind with an existing login but with an empty password. '.
         'Some LDAP servers (Microsoft AD) allow this type of bind without any possiblity to disable it.');

$form->add($section);

// ==== RADIUS section ========================================================
$section = new Form_Section('RADIUS Server Settings');
$section->addClass('toggle-radius collapse');

$section->addInput(new Form_Select(
	'radius_protocol',
	'*Protocol',
	$pconfig['radius_protocol'],
	$radius_protocol
));

$section->addInput(new Form_Input(
	'radius_host',
	'*Hostname or IP address',
	'text',
	$pconfig['radius_host']
));

$section->addInput(new Form_Input(
	'radius_secret',
	'*Shared Secret',
	'password',
	$pconfig['radius_secret']
));

$section->addInput(new Form_Select(
	'radius_srvcs',
	'*Services offered',
	$pconfig['radius_srvcs'],
	$radius_srvcs
));

$section->addInput(new Form_Input(
	'radius_auth_port',
	'Authentication port',
	'number',
	$pconfig['radius_auth_port']
));

$section->addInput(new Form_Input(
	'radius_acct_port',
	'Accounting port',
	'number',
	$pconfig['radius_acct_port']
));

$section->addInput(new Form_Input(
	'radius_timeout',
	'Authentication Timeout',
	'number',
	$pconfig['radius_timeout']
))->setHelp('This value controls how long, in seconds, that the RADIUS '.
	'server may take to respond to an authentication request. If left blank, the '.
	'default value is 5 seconds. NOTE: If using an interactive two-factor '.
	'authentication system, increase this timeout to account for how long it will '.
	'take the user to receive and enter a token.');

$section->addInput(new Form_Select(
	'radius_nasip_attribute',
	'RADIUS NAS IP Attribute',
	$pconfig['radius_nasip_attribute'],
	build_radiusnas_list()
))->setHelp('Enter the IP to use for the "NAS-IP-Address" attribute during RADIUS Acccess-Requests.<br />'.
			'Please note that this choice won\'t change the interface used for contacting the RADIUS server.');

if (isset($id) && $a_server[$id])
{
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);

// Create a largely empty modal to show the available containers. We will populate it via AJAX later
$modal = new Modal("LDAP containers", "containers", true);

$form->add($modal);

print $form;
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Create an AJAX request (to this page) to get the container list and controls
	function select_clicked() {
		if (document.getElementById("ldap_port").value == '' ||
			document.getElementById("ldap_host").value == '' ||
			document.getElementById("ldap_scope").value == '' ||
			document.getElementById("ldap_basedn").value == '' ||
			document.getElementById("ldapauthcontainers").value == '') {
			alert("<?=gettext("Please fill the required values.");?>");
			return;
		}

		if (!document.getElementById("ldap_anon").checked) {
			if (document.getElementById("ldap_binddn").value == '' ||
				document.getElementById("ldap_bindpw").value == '') {
				alert("<?=gettext("Please fill the bind username/password.");?>");
				return;
			}
		}

		var ajaxRequest;
		var authserver = $('#authmode').val();
		var cert;

<?php if (count($a_ca) > 0): ?>
			cert = $('#ldap_caref').val();
<?php else: ?>
			cert = '';
<?php endif; ?>
/*
		$('#containers').modal('show');
		$('#serverlist').parent('div').prev('label').remove();
		$('#serverlist').parent('div').removeClass("col-sm-10");
		$('#serverlist').parent('div').addClass("col-sm-12");
*/
		ajaxRequest = $.ajax(
			{
				url: "/system_authservers.php",
				type: "post",
				data: {
					ajax: 	"ajax",
					port: 	$('#ldap_port').val(),
					host: 	$('#ldap_host').val(),
					scope: 	$('#ldap_scope').val(),
					basedn: $('#ldap_basedn').val(),
					binddn: $('#ldap_binddn').val(),
					bindpw: $('#ldap_bindpw').val(),
					urltype:$('#ldap_urltype').val(),
					proto:  $('#ldap_protver').val(),
					authcn: $('#ldapauthcontainers').val(),
					cert:   cert
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#containers').replaceWith(response);

			$('#containers').modal('show');

			// The button handler needs to be here because until the modal has been populated
			// the controls we need to attach handlers to do not exist
			$('#svcontbtn').prop("type", "button");
			$('#svcontbtn').removeAttr("href");

			$('#svcontbtn').click(function () {
				var ous = $('[id^=ou]').length;
				var i;

				$('#ldapauthcontainers').val("");

				for (i = 0; i < ous; i++) {
					if ($('#ou' + i).prop("checked")) {
						if ($('#ldapauthcontainers').val() != "") {
							$('#ldapauthcontainers').val($('#ldapauthcontainers').val() +";");
						}

						$('#ldapauthcontainers').val($('#ldapauthcontainers').val() + $('#ou' + i).val());
					}
				}

				$('#containers').modal('hide');
			});
		});

	}

	function set_ldap_port() {
		if ($('#ldap_urltype').find(":selected").index() == 2)
			$('#ldap_port').val('636');
		else
			$('#ldap_port').val('389');
	}

	function set_required_port_fields() {
		if (document.getElementById("radius_srvcs").value == 'auth') {
			setRequired('radius_auth_port', true);
			setRequired('radius_acct_port', false);
		} else if (document.getElementById("radius_srvcs").value == 'acct') {
			setRequired('radius_auth_port', false);
			setRequired('radius_acct_port', true);
		} else { // both
			setRequired('radius_auth_port', true);
			setRequired('radius_acct_port', true);
		}
	}

	// Hides all elements of the specified class. This will usually be a section
	function hideClass(s_class, hide) {
		if (hide)
			$('.' + s_class).hide();
		else
			$('.' + s_class).show();
	}

	function ldap_tmplchange() {
		switch ($('#ldap_tmpltype').find(":selected").index()) {
<?php
		$index = 0;
		foreach ($ldap_templates as $tmpldata):
?>
			case <?=$index;?>:
				$('#ldap_attr_user').val("<?=$tmpldata['attr_user'];?>");
				$('#ldap_attr_group').val("<?=$tmpldata['attr_group'];?>");
				$('#ldap_attr_member').val("<?=$tmpldata['attr_member'];?>");
				$("#ldap_allow_unauthenticated").attr("checked", <?=$tmpldata['allow_unauthenticated'];?>);
				break;
<?php
			$index++;
		endforeach;
?>
		}
	}

	// ---------- On initial page load ------------------------------------------------------------

<?php if ($act != 'edit') : ?>
	ldap_tmplchange();
<?php endif; ?>

	hideClass('ldapanon', $('#ldap_anon').prop('checked'));
	hideClass('extended', !$('#ldap_extended_enabled').prop('checked'));
	set_required_port_fields();

	if ($('#ldap_port').val() == "")
		set_ldap_port();

<?php
	if ($act == 'edit') {
?>
		$('#type option:not(:selected)').each(function(){
			$(this).attr('disabled', 'disabled');
		});

<?php
		if (!$input_errors) {
?>
		$('#name').prop("readonly", true);
<?php
		}
	}
?>
	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#ldap_tmpltype').on('change', function() {
		ldap_tmplchange();
	});

	$('#ldap_anon').click(function () {
		hideClass('ldapanon', this.checked);
	});

	$('#ldap_urltype').on('change', function() {
		set_ldap_port();
	});

	$('#Select').click(function () {
		select_clicked();
	});

	$('#ldap_extended_enabled').click(function () {
		hideClass('extended', !this.checked);
	});

	$('#radius_srvcs').on('change', function() {
		set_required_port_fields();
	});

});
//]]>
</script>
<?php
include("foot.inc");
