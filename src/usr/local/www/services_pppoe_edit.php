<?php
/*
 * services_pppoe_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-pppoeserver-edit
##|*NAME=Services: PPPoE Server: Edit
##|*DESCR=Allow access to the 'Services: PPPoE Server: Edit' page.
##|*MATCH=services_pppoe_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("vpn.inc");

function vpn_pppoe_get_id() {
	global $config;

	$vpnid = 1;
	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($vpnid == $pppoe['pppoeid']) {
				$vpnid++;
			} else {
				return $vpnid;
			}
		}
	}

	return $vpnid;
}

init_config_arr(array('pppoes', 'pppoe'));
$a_pppoes = &$config['pppoes']['pppoe'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_pppoes[$id]) {
	$pppoecfg =& $a_pppoes[$id];
	$pppoecfg_old = $a_pppoes[$id];

	$pconfig['remoteip'] = $pppoecfg['remoteip'];
	$pconfig['localip'] = $pppoecfg['localip'];
	$pconfig['mode'] = $pppoecfg['mode'];
	$pconfig['interface'] = $pppoecfg['interface'];
	$pconfig['n_pppoe_units'] = $pppoecfg['n_pppoe_units'];
	$pconfig['n_pppoe_maxlogin'] = $pppoecfg['n_pppoe_maxlogin'];
	$pconfig['pppoe_subnet'] = $pppoecfg['pppoe_subnet'];
	$pconfig['pppoe_dns1'] = $pppoecfg['dns1'];
	$pconfig['pppoe_dns2'] = $pppoecfg['dns2'];
	$pconfig['descr'] = $pppoecfg['descr'];
	$pconfig['username'] = $pppoecfg['username'];
	$pconfig['pppoeid'] = $pppoecfg['pppoeid'];
	if (is_array($pppoecfg['radius'])) {
		$pconfig['radacct_enable'] = isset($pppoecfg['radius']['accounting']);
		$pconfig['radiusissueips'] = isset($pppoecfg['radius']['radiusissueips']);
		if (is_array($pppoecfg['radius']['server'])) {
			$pconfig['radiusenable'] = isset($pppoecfg['radius']['server']['enable']);
			$pconfig['radiusserver'] = $pppoecfg['radius']['server']['ip'];
			$pconfig['radiusserverport'] = $pppoecfg['radius']['server']['port'];
			$pconfig['radiusserveracctport'] = $pppoecfg['radius']['server']['acctport'];
			$pconfig['radiussecret'] = $pppoecfg['radius']['server']['secret'];
		}

		if (is_array($pppoecfg['radius']['server2'])) {
			$pconfig['radiussecenable'] = isset($pppoecfg['radius']['server2']['enable']);
			$pconfig['radiusserver2'] = $pppoecfg['radius']['server2']['ip'];
			$pconfig['radiusserver2port'] = $pppoecfg['radius']['server2']['port'];
			$pconfig['radiusserver2acctport'] = $pppoecfg['radius']['server2']['acctport'];
			$pconfig['radiussecret2'] = $pppoecfg['radius']['server2']['secret2'];
		}

		$pconfig['radius_nasip'] = $pppoecfg['radius']['nasip'];
		$pconfig['radius_acct_update'] = $pppoecfg['radius']['acct_update'];
	}
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = array(gettext("Server address"), gettext("Remote start address"));

		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn,
				array(gettext("RADIUS server address"), gettext("RADIUS shared secret")));
		}
		if ($_POST['radiussecenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver2 radiussecret2"));
			$reqdfieldsn = array_merge($reqdfieldsn,
				array(gettext("Secondary RADIUS server address"), gettext("Secondary RADIUS server shared secret")));
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) {
			$input_errors[] = gettext("A valid server address must be specified.");
		}
		if (($_POST['remoteip'] && !is_ipaddr($_POST['remoteip']))) {
			$input_errors[] = gettext("A valid remote start address must be specified.");
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");
		}
		if (!is_numericint($_POST['n_pppoe_units']) || $_POST['n_pppoe_units'] > 255) {
			$input_errors[] = gettext("Number of PPPoE users must be between 1 and 255");
		}
		if (!is_numericint($_POST['n_pppoe_maxlogin']) || $_POST['n_pppoe_maxlogin'] > 255) {
			$input_errors[] = gettext("User Max Logins must be between 1 and 255");
		}
		if (!is_numericint($_POST['pppoe_subnet']) || $_POST['pppoe_subnet'] > 32) {
			$input_errors[] = gettext("Subnet mask must be an integer between 0 and 32");
		}

		$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $_POST['pppoe_subnet']);
		if (is_inrange_v4($_POST['localip'], $_POST['remoteip'], ip_after($_POST['remoteip'], $_POST['pppoe_subnet'] - 1))) {
			$input_errors[] = gettext("The specified server address lies in the remote subnet.");
		}
		if ($_POST['localip'] == get_interface_ip($_POST['interface'])) {
			$input_errors[] = gettext("The specified server address is equal to an interface ip address.");
		}

		for ($x = 0; $x < 4999; $x++) {
			if ($_POST["username{$x}"]) {
				if (empty($_POST["password{$x}"])) {
					$input_errors[] = sprintf(gettext("No password specified for username %s"), $_POST["username{$x}"]);
				} elseif (preg_match("/^!/", trim($_POST["password{$x}"]))) {
					$input_errors[] = gettext("User passwords cannot start with '!'.");
				}
				if ($_POST["ip{$x}"] != "" && !is_ipaddr($_POST["ip{$x}"])) {
					$input_errors[] = sprintf(gettext("Incorrect ip address specified for username %s"), $_POST["username{$x}"]);
				}
			}
		}
	}

	if ($_POST['pppoeid'] && !is_numeric($_POST['pppoeid'])) {
		$input_errors[] = gettext("Wrong data submitted");
	}

	if (!$input_errors) {
		$pppoecfg = array();

		$pppoecfg['remoteip'] = $_POST['remoteip'];
		$pppoecfg['localip'] = $_POST['localip'];
		$pppoecfg['mode'] = $_POST['mode'];
		$pppoecfg['interface'] = $_POST['interface'];
		$pppoecfg['n_pppoe_units'] = $_POST['n_pppoe_units'];
		$pppoecfg['n_pppoe_maxlogin'] = $_POST['n_pppoe_maxlogin'];
		$pppoecfg['pppoe_subnet'] = $_POST['pppoe_subnet'];
		$pppoecfg['descr'] = $_POST['descr'];
		if ($_POST['radiusserver'] || $_POST['radiusserver2']) {
			$pppoecfg['radius'] = array();

			$pppoecfg['radius']['nasip'] = $_POST['radius_nasip'];
			$pppoecfg['radius']['acct_update'] = $_POST['radius_acct_update'];
		}

		if ($_POST['radiusserver']) {
			$pppoecfg['radius']['server'] = array();

			$pppoecfg['radius']['server']['ip'] = $_POST['radiusserver'];
			if ($_POST['radiussecret'] != DMYPWD) {
				$pppoecfg['radius']['server']['secret'] = $_POST['radiussecret'];
			}
			$pppoecfg['radius']['server']['port'] = $_POST['radiusserverport'];
			$pppoecfg['radius']['server']['acctport'] = $_POST['radiusserveracctport'];
		}

		if ($_POST['radiusserver2']) {
			$pppoecfg['radius']['server2'] = array();

			$pppoecfg['radius']['server2']['ip'] = $_POST['radiusserver2'];
			if ($_POST['radiussecret2'] != DMYPWD) {
				$pppoecfg['radius']['server2']['secret2'] = $_POST['radiussecret2'];
			}
			$pppoecfg['radius']['server2']['port'] = $_POST['radiusserver2port'];
			$pppoecfg['radius']['server2']['acctport'] = $_POST['radiusserver2acctport'];
		}

		if ($_POST['pppoe_dns1'] <> "") {
			$pppoecfg['dns1'] = $_POST['pppoe_dns1'];
		}

		if ($_POST['pppoe_dns2'] <> "") {
			$pppoecfg['dns2'] = $_POST['pppoe_dns2'];
		}

		if ($_POST['radiusenable'] == "yes") {
			$pppoecfg['radius']['server']['enable'] = true;
		}

		if ($_POST['radiussecenable'] == "yes") {
			$pppoecfg['radius']['server2']['enable'] = true;
		}

		if ($_POST['radacct_enable'] == "yes") {
			$pppoecfg['radius']['accounting'] = true;
		}

		if ($_POST['radiusissueips'] == "yes") {
			$pppoecfg['radius']['radiusissueips'] = true;
		}

		if ($_POST['pppoeid']) {
			$pppoecfg['pppoeid'] = $_POST['pppoeid'];
		} else {
			$pppoecfg['pppoeid'] = vpn_pppoe_get_id();
		}

		$users = array();
		for ($x = 0; $x < 4999; $x++) {
			if ($_POST["username{$x}"]) {
				$usernam = $_POST["username{$x}"] . ":" . base64_encode($_POST["password{$x}"]);
				if ($_POST["ip{$x}"]) {
					$usernam .= ":" . $_POST["ip{$x}"];
				}

				$users[] = $usernam;
			}
		}

		if (count($users) > 0) {
			$pppoecfg['username'] = implode(" ", $users);
		}

		if (isset($id) && 
		    (($pppoecfg_old['remoteip'] != $pppoecfg['remoteip']) ||
		    ($pppoecfg_old['localip'] != $pppoecfg['localip']) ||
		    ($pppoecfg_old['mode'] != $pppoecfg['mode']) ||
		    ($pppoecfg_old['interface'] != $pppoecfg['interface']) ||
		    ($pppoecfg_old['n_pppoe_units'] != $pppoecfg['n_pppoe_units']) ||
		    ($pppoecfg_old['n_pppoe_maxlogin'] != $pppoecfg['n_pppoe_maxlogin']) ||
		    ($pppoecfg_old['pppoe_subnet'] != $pppoecfg['pppoe_subnet']) ||
		    ($pppoecfg_old['dns1'] != $pppoecfg['dns1']) ||
		    ($pppoecfg_old['dns2'] != $pppoecfg['dns2']) ||
		    ($pppoecfg_old['radius'] != $pppoecfg['radius']))) {
		    	$reload = true;
		} else {
			$reload = false;
		}

		if (!isset($id)) {
			$id = count($a_pppoes);
		}

		if (file_exists("{$g['tmp_path']}/.vpn_pppoe.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.vpn_pppoe.apply"));
		} else {
			$toapplylist = array();
		}

		write_config();

		if ($reload) {
			$toapplylist[] = $pppoecfg['pppoeid'];
			mark_subsystem_dirty('vpnpppoe');
			file_put_contents("{$g['tmp_path']}/.vpn_pppoe.apply", serialize($toapplylist));
		}

		$a_pppoes[$id] = $pppoecfg;
		vpn_pppoe_updatesecret($pppoecfg);

		header("Location: services_pppoe.php");
		exit;
	}
}

function build_interface_list() {
	$list = array();

	$interfaces = get_configured_interface_with_descr();

	foreach ($interfaces as $iface => $ifacename) {
		$list[$iface] = $ifacename;
	}

	return($list);
}

$pgtitle = array(gettext("Services"), gettext("PPPoE Server"), gettext("Edit"));
$pglinks = array("", "services_pppoe.php", "@self");
$shortcut_section = "pppoes";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('PPPoE Server Configuration');

$section->addInput(new Form_Checkbox(
	'mode',
	'Enable',
	'Enable PPPoE Server',
	($pconfig['mode'] == "server"),
	'server'
)) ->toggles('.form-group:not(:first-child)');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	build_interface_list()

));

$section->addInput(new Form_Select(
	'n_pppoe_units',
	'*Total User Count',
	$pconfig['n_pppoe_units'],
	array_combine(range(1, 255, 1), range(1, 255, 1))
))->setHelp('The number of PPPoE users allowed to connect to this server simultaneously.');

$section->addInput(new Form_Select(
	'n_pppoe_maxlogin',
	'*User Max Logins',
	$pconfig['n_pppoe_maxlogin'],
	array_combine(range(1, 255, 1), range(1, 255, 1))
))->setHelp('The number of times a single user may be logged in at the same time.');

$section->addInput(new Form_IpAddress(
	'localip',
	'*Server Address',
	$pconfig['localip']
))->setHelp('Enter the IP address the PPPoE server should give to clients for use as their "gateway".%1$s' .
			'Typically this is set to an unused IP just outside of the client range.%1$s' .
			'NOTE: This should NOT be set to any IP address currently in use on this firewall.', '<br />');

$section->addInput(new Form_IpAddress(
	'remoteip',
	'*Remote Address Range',
	$pconfig['remoteip']
))->setHelp('Specify the starting address for the client IP address subnet.');

$section->addInput(new Form_Select(
	'pppoe_subnet',
	'*Subnet mask',
	$pconfig['pppoe_subnet'],
	array_combine(range(0, 32, 1), range(0, 32, 1))
))->setHelp('Hint: 24 is 255.255.255.0');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));

$section->addInput(new Form_Input(
	'pppoe_dns1',
	'DNS Servers',
	'text',
	$pconfig['pppoe_dns1']
));

$section->addInput(new Form_IpAddress(
	'pppoe_dns2',
	null,
	$pconfig['pppoe_dns2']
))->setHelp('If entered these servers will be given to all PPPoE clients, otherwise LAN DNS and one WAN DNS will go to all clients.');

$section->addInput(new Form_Checkbox(
	'radiusenable',
	'RADIUS',
	'Use RADIUS Authentication',
	$pconfig['radiusenable']
))->setHelp('Users will be authenticated using the RADIUS server specified below. The local user database ' .
			'will not be used.');

$section->addInput(new Form_Checkbox(
	'radacct_enable',
	null,
	'Use RADIUS Accounting',
	$pconfig['radacct_enable']
))->setHelp('Sends accounting packets to the RADIUS server.');

$section->addInput(new Form_Checkbox(
	'radiussecenable',
	null,
	'Use a Backup RADIUS Authentication Server',
	$pconfig['radiussecenable']
))->setHelp('If primary server fails all requests will be sent via backup server.');

$section->addInput(new Form_IpAddress(
	'radius_nasip',
	'NAS IP Address',
	$pconfig['radius_nasip']
))->setHelp('NAS IP Address sent to the RADIUS Server');

$section->addInput(new Form_Input(
	'radius_acct_update',
	'RADIUS Accounting Update',
	'text',
	$pconfig['radius_acct_update']
))->setHelp('RADIUS accounting update period in seconds');

$section->addInput(new Form_Checkbox(
	'radiusissueips',
	'RADIUS Issued IP Addresses',
	'Assign IP Addresses to users via RADIUS server reply attributes',
	$pconfig['radiusissueips']
));

$group = new Form_Group('Primary RADIUS Server');

$group->add(new Form_IpAddress(
	'radiusserver',
	null,
	$pconfig['radiusserver']
))->setHelp('IP Address');

$group->add(new Form_Input(
	'radiusserverport',
	null,
	'text',
	$pconfig['radiusserverport']
))->setHelp('Authentication port');

$group->add(new Form_Input(
	'radiusserveracctport',
	null,
	'text',
	$pconfig['radiusserveracctport']
))->setHelp('Accounting port (optional)');

$group->setHelp('Standard ports are 1812 (authentication) and 1813 (accounting).');

$section->add($group);

$section->addPassword(new Form_Input(
	'radiussecret',
	'Primary RADIUS Server Shared Secret',
	'password',
	$pconfig['radiussecret']
))->setHelp('Enter the shared secret that will be used to authenticate to the RADIUS server.');

$group = new Form_Group('Secondary RADIUS Server');

$group->add(new Form_IpAddress(
	'radiusserver2',
	null,
	$pconfig['radiusserver2']
))->setHelp('IP Address');

$group->add(new Form_Input(
	'radiusserver2port',
	null,
	'text',
	$pconfig['radiusserver2port']
))->setHelp('Authentication port');

$group->add(new Form_Input(
	'radiusserver2acctport',
	null,
	'text',
	$pconfig['radiusserver2acctport']
))->setHelp('Accounting port (optional)');

$group->setHelp('Standard ports are 1812 (authentication) and 1813 (accounting).');

$section->add($group);

$section->addPassword(new Form_Input(
	'radiussecret2',
	'Secondary RADIUS Server Shared Secret',
	'password',
	$pconfig['radiussecret2']
))->setHelp('Enter the shared secret that will be used to authenticate to the backup RADIUS server.');

$counter = 0;
$numrows = count($item) -1;

if (!empty($pconfig['username'])) {
	$usernames = $pconfig['username'];
} else {
	$usernames = $pppoecfg['username'];
}

if ($usernames == "") {
	$usernames = '::';
}

if ($usernames != "") {
	$item = explode(" ", $usernames);

	$numrows = count($item) -1;

	foreach ($item as $ww) {
		$wws = explode(":", $ww);
		$user = $wws[0];
		$passwd = base64_decode($wws[1]);
		$ip = $wws[2];

		$group = new Form_Group($counter == 0 ? 'User table':null);
		$group->addClass('repeatable');

		$group->add(new Form_Input(
			'username' . $counter,
			null,
			'text',
			$user,
			['autocomplete' => 'new-password']
		))->setHelp($numrows == $counter ? 'Username':null);

		$group->add(new Form_Input(
			'password' . $counter,
			null,
			'password',
			$passwd,
			['autocomplete' => 'new-password']
		))->setHelp($numrows == $counter ? 'Password':null);

		$group->add(new Form_IpAddress(
			'ip' . $counter,
			null,
			$ip
		))->setHelp($numrows == $counter ? 'IP Address':null);

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete',
			null,
			'fa-trash'
		))->addClass('btn-warning');

		$section->add($group);

		$counter++;
	}
}

$btnaddrow = new Form_Button(
	'addrow',
	'Add user',
	null,
	'fa-plus'
);

$btnaddrow->addClass('btn-success');

$section->addInput(new Form_StaticText(
	null,
	'&nbsp;' . $btnaddrow
));

// Hidden fields
if (isset($id)) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		htmlspecialchars($id, ENT_QUOTES | ENT_HTML401)
	));
}

if (isset($pconfig['pppoeid'])) {
	$form->addGlobal(new Form_Input(
		'pppoeid',
		null,
		'hidden',
		$pconfig['pppoeid']
	));
}

$form->add($section);

print($form);

print_info_box(gettext('Don\'t forget to add a firewall rule to permit traffic from PPPoE clients.'));
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// show/hide radius server controls
	function hide_radius(hide) {
		disableInput('radacct_enable', hide);
		disableInput('radiusserver', hide);
		disableInput('radiussecret', hide);
		disableInput('radiussecret_confirm', hide);
		disableInput('radiusserverport', hide);
		disableInput('radiusserveracctport', hide);
		disableInput('radiusissueips', hide);
		disableInput('radius_nasip', hide);
		disableInput('radiusissueips', hide);
		disableInput('radius_nasip', hide);
		disableInput('radius_acct_update', hide);
		disableInput('radiussecenable', hide);
		setRequired('radiusserver', !hide);
		setRequired('radiussecret', !hide);
		hide_radius2(hide);
	}
	// show/hide radius server 2 controls
	function hide_radius2(hide) {
		disableInput('radiusserver2', hide);
		disableInput('radiussecret2', hide);
		disableInput('radiussecret2_confirm', hide);
		disableInput('radiusserver2port', hide);
		disableInput('radiusserver2acctport', hide);
		setRequired('radiusserver2', !hide);
		setRequired('radiussecret2', !hide);
	}

	// When the RADIUS checkbox is clicked . .
	$('#radiusenable').click(function () {
		hide_radius(!$('#radiusenable').prop('checked'));
		if (!$('#radiusenable').prop('checked')) {
			hide_radius2(true);
		} else {
			hide_radius2(!$('#radiussecenable').prop('checked'));
		}
	});

	// When the 'Use backup RADIUS' checkbox is clicked . .
	$('#radiussecenable').click(function () {
		hide_radius2(!$('#radiussecenable').prop('checked'));
	});

	// ---------- On initial page load ------------------------------------------------------------
	hide_radius2(!$('#radiussecenable').prop('checked'));
	hide_radius(!$('#radiusenable').prop('checked'));

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

});
//]]>
</script>
<?php
include("foot.inc");
