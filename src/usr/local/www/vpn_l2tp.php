<?php
/*
 * vpn_l2tp.php
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
##|*IDENT=page-vpn-vpnl2tp
##|*NAME=VPN: L2TP
##|*DESCR=Allow access to the 'VPN: L2TP' page.
##|*MATCH=vpn_l2tp.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("vpn.inc");

init_config_arr(array('l2tp', 'radius'));
$l2tpcfg = &$config['l2tp'];

$pconfig['remoteip'] = $l2tpcfg['remoteip'];
$pconfig['localip'] = $l2tpcfg['localip'];
$pconfig['l2tp_subnet'] = $l2tpcfg['l2tp_subnet'];
$pconfig['mode'] = $l2tpcfg['mode'];
$pconfig['interface'] = $l2tpcfg['interface'];
$pconfig['l2tp_dns1'] = $l2tpcfg['dns1'];
$pconfig['l2tp_dns2'] = $l2tpcfg['dns2'];
$pconfig['radiusenable'] = isset($l2tpcfg['radius']['enable']);
$pconfig['radacct_enable'] = isset($l2tpcfg['radius']['accounting']);
$pconfig['radiusserver'] = $l2tpcfg['radius']['server'];
$pconfig['radiussecret'] = $l2tpcfg['radius']['secret'];
$pconfig['radiusissueips'] = isset($l2tpcfg['radius']['radiusissueips']);
$pconfig['n_l2tp_units'] = $l2tpcfg['n_l2tp_units'];
$pconfig['paporchap'] = $l2tpcfg['paporchap'];
$pconfig['secret'] = $l2tpcfg['secret'];

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip");
		$reqdfieldsn = array(gettext("Server address"));
		if (!$_POST['radiusenable'] || !$_POST['radiusissueips']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "remoteip"));
			$reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Remote start address")));
		} elseif ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn,
				array(gettext("RADIUS server address"), gettext("RADIUS shared secret")));
		}

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (($_POST['localip'] && !is_ipaddrv4($_POST['localip']))) {
			$input_errors[] = gettext("A valid server address must be specified.");
		}
		if (is_ipaddr_configured($_POST['localip'])) {
			$input_errors[] = gettext("'Server address' parameter should NOT be set to any IP address currently in use on this firewall.");
		}
		if ($_POST['l2tp_subnet'] && !is_ipaddrv4($_POST['remoteip']) &&
		    (!$_POST['radiusenable'] || !$_POST['radiusissueips'])) {
			$input_errors[] = gettext("A valid remote start address must be specified.");
		}
		if (($_POST['radiusserver'] && !is_ipaddrv4($_POST['radiusserver']))) {
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");
		}

		if ($_POST['secret'] != $_POST['secret_confirm']) {
			$input_errors[] = gettext("Secret and confirmation must match");
		}

		if ($_POST['radiussecret'] != $_POST['radiussecret_confirm']) {
			$input_errors[] = gettext("RADIUS secret and confirmation must match");
		}

		if (!is_numericint($_POST['n_l2tp_units']) || $_POST['n_l2tp_units'] > 255) {
			$input_errors[] = gettext("Number of L2TP users must be between 1 and 255");
		}

		if (!$input_errors) {
			$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $_POST['l2tp_subnet']);
			if (is_inrange_v4($_POST['localip'], $_POST['remoteip'], ip_after($_POST['remoteip'], $_POST['n_l2tp_units'] - 1))) {
				$input_errors[] = gettext("The specified server address lies in the remote subnet.");
			}
			if ($_POST['localip'] == get_interface_ip("lan")) {
				$input_errors[] = gettext("The specified server address is equal to the LAN interface address.");
			}
		}

		if (!empty($_POST['l2tp_dns1']) && !is_ipaddrv4(trim($_POST['l2tp_dns1']))) {
			$input_errors[] = gettext("The field 'Primary L2TP DNS Server' must contain a valid IPv4 address.");
		}
		if (!empty($_POST['l2tp_dns2']) && !is_ipaddrv4(trim($_POST['l2tp_dns2']))) {
			$input_errors[] = gettext("The field 'Secondary L2TP DNS Server' must contain a valid IPv4 address.");
		}
		if (!empty($_POST['l2tp_dns2']) && empty($_POST['l2tp_dns1'])) {
			$input_errors[] = gettext("The Secondary L2TP DNS Server cannot be set when the Primary L2TP DNS Server is empty.");
		}

	}

	if (!$input_errors) {
		$l2tpcfg['remoteip'] = $_POST['remoteip'];
		$l2tpcfg['localip'] = $_POST['localip'];
		$l2tpcfg['l2tp_subnet'] = $_POST['l2tp_subnet'];
		$l2tpcfg['mode'] = $_POST['mode'];
		$l2tpcfg['interface'] = $_POST['interface'];
		$l2tpcfg['n_l2tp_units'] = $_POST['n_l2tp_units'];
		$l2tpcfg['radius']['server'] = $_POST['radiusserver'];
		if ($_POST['radiussecret'] != DMYPWD) {
			$l2tpcfg['radius']['secret'] = $_POST['radiussecret'];
		}

		if ($_POST['secret'] != DMYPWD) {
			$l2tpcfg['secret'] = $_POST['secret'];
		}

		$l2tpcfg['paporchap'] = $_POST['paporchap'];


		if ($_POST['l2tp_dns1'] == "") {
			if (isset($l2tpcfg['dns1'])) {
				unset($l2tpcfg['dns1']);
			}
		} else {
			$l2tpcfg['dns1'] = $_POST['l2tp_dns1'];
		}

		if ($_POST['l2tp_dns2'] == "") {
			if (isset($l2tpcfg['dns2'])) {
				unset($l2tpcfg['dns2']);
			}
		} else {
			$l2tpcfg['dns2'] = $_POST['l2tp_dns2'];
		}

		if ($_POST['radiusenable'] == "yes") {
			$l2tpcfg['radius']['enable'] = true;
		} else {
			unset($l2tpcfg['radius']['enable']);
		}

		if ($_POST['radacct_enable'] == "yes") {
			$l2tpcfg['radius']['accounting'] = true;
		} else {
			unset($l2tpcfg['radius']['accounting']);
		}

		if ($_POST['radiusissueips'] == "yes") {
			$l2tpcfg['radius']['radiusissueips'] = true;
		} else {
			unset($l2tpcfg['radius']['radiusissueips']);
		}

		write_config(gettext("L2TP VPN configuration changed."));

		$changes_applied = true;
		$retval = 0;
		$retval |= vpn_l2tp_configure();
	}
}

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("Configuration"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "l2tps";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), true, "vpn_l2tp.php");
$tab_array[] = array(gettext("Users"), false, "vpn_l2tp_users.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section("Enable L2TP");

$section->addInput(new Form_Checkbox(
	'mode',
	'Enable',
	'Enable L2TP server',
	($pconfig['mode'] == "server"),
	'server'
));

$form->add($section);

$iflist = array();
$interfaces = get_configured_interface_with_descr();
foreach ($interfaces as $iface => $ifacename) {
	$iflist[$iface] = $ifacename;
}

$section = new Form_Section("Configuration");
$section->addClass('toggle-l2tp-enable');

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	$iflist
));

$section->addInput(new Form_Input(
	'localip',
	'*Server address',
	'text',
	$pconfig['localip']
))->setHelp('Enter the IP address the L2TP server should give to clients for use as their "gateway". %1$s' .
			'Typically this is set to an unused IP just outside of the client range.%1$s%1$s' .
			'NOTE: This should NOT be set to any IP address currently in use on this firewall.', '<br />');

$section->addInput(new Form_IpAddress(
        'remoteip',
        '*Remote address range',
        $pconfig['remoteip']
))->addMask('l2tp_subnet', $pconfig['l2tp_subnet'], 32)->setWidth(5)
  ->setHelp('Specify the starting address for the client IP address subnet.');

$section->addInput(new Form_Select(
	'n_l2tp_units',
	'*Number of L2TP users',
	$pconfig['n_l2tp_units'],
	array_combine(range(1, 255, 1), range(1, 255, 1))
));

$section->addPassword(new Form_Input(
	'secret',
	'Secret',
	'password',
	$pconfig['secret']
))->setHelp('Specify optional secret shared between peers. Required on some devices/setups.');

$section->addInput(new Form_Select(
	'paporchap',
	'*Authentication type',
	$pconfig['paporchap'],
	array(
		'chap' => 'CHAP',
		'chap-msv2' => 'MS-CHAPv2',
		'pap' => 'PAP'
		)
))->setHelp('Specifies the protocol to use for authentication.');

$section->addInput(new Form_Input(
	'l2tp_dns1',
	'Primary L2TP DNS server',
	'text',
	$pconfig['l2tp_dns1']
));

$section->addInput(new Form_Input(
	'l2tp_dns2',
	'Secondary L2TP DNS server',
	'text',
	$pconfig['l2tp_dns2']
));

$form->add($section);

$section = new Form_Section("RADIUS");
$section->addClass('toggle-l2tp-enable');

$section->addInput(new Form_Checkbox(
	'radiusenable',
	'Enable',
	'Use a RADIUS server for authentication',
	$pconfig['radiusenable']
))->setHelp('When set, all users will be authenticated using the RADIUS server specified below. The local user database will not be used.');

$section->addInput(new Form_Checkbox(
	'radacct_enable',
	'Accounting',
	'Enable RADIUS accounting',
	$pconfig['radacct_enable']
))->setHelp('Sends accounting packets to the RADIUS server.');

$section->addInput(new Form_IpAddress(
	'radiusserver',
	'*Server',
	$pconfig['radiusserver']
))->setHelp('Enter the IP address of the RADIUS server.');

$section->addPassword(new Form_Input(
	'radiussecret',
	'*Secret',
	'password',
	$pconfig['radiussecret']
))->setHelp('Enter the shared secret that will be used to authenticate to the RADIUS server.');

$section->addInput(new Form_Checkbox(
	'radiusissueips',
	'RADIUS issued IPs',
	'Issue IP Addresses via RADIUS server.',
	$pconfig['radiusissueips']
));

$form->add($section);

print($form);
?>
<div class="infoblock blockopen">
<?php
	print_info_box(gettext("Don't forget to add a firewall rule to permit traffic from L2TP clients."), 'info', false);
?>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function setL2TP () {
		hide = ! $('#mode').prop('checked');

		hideClass('toggle-l2tp-enable', hide);
	}

	function setRADIUS () {
		hide = ! $('#radiusenable').prop('checked');

		hideCheckbox('radacct_enable', hide);
		hideInput('radiusserver', hide);
		hideInput('radiussecret', hide);
		hideCheckbox('radiusissueips', hide);
	}

	// on-click
	$('#mode').click(function () {
		setL2TP();
	});

	$('#radiusenable').click(function () {
		setRADIUS();
	});

	// on-page-load
	setRADIUS();
	setL2TP();

});
//]]>
</script>

<?php include("foot.inc")?>
