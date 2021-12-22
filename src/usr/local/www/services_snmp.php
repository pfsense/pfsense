<?php
/*
 * services_snmp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-snmp
##|*NAME=Services: SNMP
##|*DESCR=Allow access to the 'Services: SNMP' page.
##|*MATCH=services_snmp.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['snmpd'])) {
	$config['snmpd'] = array();
	$config['snmpd']['rocommunity'] = "public";
	$config['snmpd']['pollport'] = "161";
}

if (!is_array($config['snmpd']['modules'])) {
	$config['snmpd']['modules'] = array();
	$config['snmpd']['modules']['mibii'] = true;
	$config['snmpd']['modules']['netgraph'] = true;
	$config['snmpd']['modules']['pf'] = true;
	$config['snmpd']['modules']['hostres'] = true;
	$config['snmpd']['modules']['bridge'] = true;
	$config['snmpd']['modules']['ucd'] = true;
	$config['snmpd']['modules']['regex'] = true;
}

$pconfig['enable'] = isset($config['snmpd']['enable']);
$pconfig['pollport'] = $config['snmpd']['pollport'];
$pconfig['syslocation'] = $config['snmpd']['syslocation'];
$pconfig['syscontact'] = $config['snmpd']['syscontact'];
$pconfig['rocommunity'] = $config['snmpd']['rocommunity'];
/* disabled until some docs show up on what this does.
$pconfig['rwenable'] = isset($config['snmpd']['rwenable']);
$pconfig['rwcommunity'] = $config['snmpd']['rwcommunity'];
*/
$pconfig['trapenable'] = isset($config['snmpd']['trapenable']);
$pconfig['trapserver'] = $config['snmpd']['trapserver'];
$pconfig['trapserverport'] = $config['snmpd']['trapserverport'];
$pconfig['trapstring'] = $config['snmpd']['trapstring'];

$pconfig['mibii'] = isset($config['snmpd']['modules']['mibii']);
$pconfig['netgraph'] = isset($config['snmpd']['modules']['netgraph']);
$pconfig['pf'] = isset($config['snmpd']['modules']['pf']);
$pconfig['hostres'] = isset($config['snmpd']['modules']['hostres']);
$pconfig['bridge'] = isset($config['snmpd']['modules']['bridge']);
$pconfig['ucd'] = isset($config['snmpd']['modules']['ucd']);
$pconfig['regex'] = isset($config['snmpd']['modules']['regex']);

$pconfig['ipprotocol'] = $config['snmpd']['ipprotocol'];
if (empty($config['snmpd']['bindip'])) {
	$pconfig['bindip'] = array();
} else {
	$pconfig['bindip'] = explode(",", $config['snmpd']['bindip']);
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		if (strstr($_POST['syslocation'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in system location");
		}
		if (strstr($_POST['syscontact'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in system contact");
		}
		if (strstr($_POST['rocommunity'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in read community string");
		}

		$reqdfields = explode(" ", "rocommunity");
		$reqdfieldsn = array(gettext("Community"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "pollport");
		$reqdfieldsn = array(gettext("Polling Port"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);


	}

	if ($_POST['trapenable']) {
		if (strstr($_POST['trapstring'], "#")) {
			$input_errors[] = gettext("Invalid character '#' in SNMP trap string");
		}

		$reqdfields = explode(" ", "trapserver");
		$reqdfieldsn = array(gettext("Trap server"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "trapserverport");
		$reqdfieldsn = array(gettext("Trap server port"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		$reqdfields = explode(" ", "trapstring");
		$reqdfieldsn = array(gettext("Trap string"));
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

/* disabled until some docs show up on what this does.
	if ($_POST['rwenable']) {
		$reqdfields = explode(" ", "rwcommunity");
		$reqdfieldsn = explode(",", "Write community string");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}
*/



	if (!$input_errors) {
		$config['snmpd']['enable'] = $_POST['enable'] ? true : false;
		$config['snmpd']['pollport'] = $_POST['pollport'];
		$config['snmpd']['syslocation'] = $_POST['syslocation'];
		$config['snmpd']['syscontact'] = $_POST['syscontact'];
		$config['snmpd']['rocommunity'] = $_POST['rocommunity'];
		/* disabled until some docs show up on what this does.
		$config['snmpd']['rwenable'] = $_POST['rwenable'] ? true : false;
		$config['snmpd']['rwcommunity'] = $_POST['rwcommunity'];
		*/
		$config['snmpd']['trapenable'] = $_POST['trapenable'] ? true : false;
		$config['snmpd']['trapserver'] = $_POST['trapserver'];
		$config['snmpd']['trapserverport'] = $_POST['trapserverport'];
		$config['snmpd']['trapstring'] = $_POST['trapstring'];

		$config['snmpd']['modules']['mibii'] = $_POST['mibii'] ? true : false;
		$config['snmpd']['modules']['netgraph'] = $_POST['netgraph'] ? true : false;
		$config['snmpd']['modules']['pf'] = $_POST['pf'] ? true : false;
		$config['snmpd']['modules']['hostres'] = $_POST['hostres'] ? true : false;
		$config['snmpd']['modules']['bridge'] = $_POST['bridge'] ? true : false;
		$config['snmpd']['modules']['ucd'] = $_POST['ucd'] ? true : false;
		$config['snmpd']['modules']['regex'] = $_POST['regex'] ? true : false;

		$config['snmpd']['ipprotocol'] = $_POST['ipprotocol'];
		if (is_array($_POST['bindip']) && !empty($_POST['bindip'])) {
			$config['snmpd']['bindip'] = implode(",", $_POST['bindip']);
		}

		write_config("SNMP settings saved");

		$changes_applied = true;
		$retval = 0;
		$retval |= services_snmpd_configure();
	}
}

function build_if_list($selectedifs) {
	$interface_addresses = get_possible_listen_ips(true);
	$iflist = array('options' => array(), 'selected' => array());

	$iflist['options']['all']	= gettext("All");
	if (empty($selectedifs) || empty($selectedifs[0]) || in_array("all", $selectedifs)) {
		array_push($iflist['selected'], "all");
	}

	foreach ($interface_addresses as $laddr => $ldescr) {
		if (is_ipaddr(get_interface_ip($laddr))) {
			$iflist['options'][$laddr] = htmlspecialchars($ldescr);
		}

		if ($selectedifs && in_array($laddr, $selectedifs)) {
			array_push($iflist['selected'], $laddr);
		}
	}

	unset($interface_addresses);

	return($iflist);
}

$pgtitle = array(gettext("Services"), gettext("SNMP"));
$shortcut_section = "snmp";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$form = new Form();

$section = new Form_Section('SNMP Daemon');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable the SNMP Daemon and its controls',
	$pconfig['enable']
));

$form->add($section);

$section = new Form_Section('SNMP Daemon Settings');

$section->addInput(new Form_Input(
	'pollport',
	'Polling Port',
	'text',
	($pconfig['pollport'] ? $pconfig['pollport']:'161')
))->setHelp('Enter the port to accept polling events on (default 161).');

$section->addInput(new Form_Input(
	'syslocation',
	'System Location',
	'text',
	$pconfig['syslocation']
));

$section->addInput(new Form_Input(
	'syscontact',
	'System Contact',
	'text',
	$pconfig['syscontact']
));

$section->addInput(new Form_Input(
	'rocommunity',
	'Read Community String',
	'text',
	$pconfig['rocommunity']
))->setHelp('The community string is like a password, restricting access to querying SNMP to hosts knowing the community string. Use a strong value here to protect from unauthorized information disclosure.');

$form->add($section);

$section = new Form_Section('SNMP Traps Enable');

$section->addInput(new Form_Checkbox(
	'trapenable',
	'Enable',
	'Enable the SNMP Trap and its controls',
	$pconfig['trapenable']
))->toggles('.toggle-traps');

$form->add($section);

$section = new Form_Section('SNMP Trap Settings');

if ($pconfig['trapenable']) {
	$section->addClass('toggle-traps', 'in');
} else {
	$section->addClass('toggle-traps', 'collapse');
}

$section->addInput(new Form_Input(
	'trapserver',
	'Trap server',
	'text',
	$pconfig['trapserver']
))->setHelp('Enter the trap server name');

$section->addInput(new Form_Input(
	'trapserverport',
	'Trap Server Port',
	'text',
	($pconfig['trapserverport'] ? $pconfig['trapserverport']:'162')
))->setHelp('Enter the port to send the traps to (default 162)');

$section->addInput(new Form_Input(
	'trapstring',
	'SNMP Trap String',
	'text',
	$pconfig['trapstring']
));

$form->add($section);

$section = new Form_Section('SNMP Modules');

$group = new Form_MultiCheckboxGroup('SNMP modules');

$group->add(new Form_MultiCheckbox(
	'mibii',
	null,
	'MibII',
	$pconfig['mibii']
));

$group->add(new Form_MultiCheckbox(
	'netgraph',
	null,
	'Netgraph',
	$pconfig['netgraph']
));

$group->add(new Form_MultiCheckbox(
	'pf',
	null,
	'PF',
	$pconfig['pf']
));

$group->add(new Form_MultiCheckbox(
	'hostres',
	null,
	'Host Resources',
	$pconfig['hostres']
));

$group->add(new Form_MultiCheckbox(
	'ucd',
	null,
	'UCD',
	$pconfig['ucd']
));

$group->add(new Form_MultiCheckbox(
	'regex',
	null,
	'Regex',
	$pconfig['regex']
));

$section->add($group);
$form->add($section);

$section = new Form_Section('Interface Binding');

$section->addInput(new Form_Select(
	'ipprotocol',
	'Internet Protocol',
	$pconfig['ipprotocol'],
	array("inet4" => "IPv4", "inet6" => "IPv6", "inet46" => "IPv4+IPv6")
));

$iflist = build_if_list($pconfig['bindip']);

$section->addInput(new Form_Select(
	'bindip',
	'Bind Interfaces',
	$iflist['selected'],
	$iflist['options'],
	true
));

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[

// hostres requires mibii so we force that here
events.push(function() {

	noMibii = false;

	$('#junk').hide();
	enableChange();
	trapenableChange();
	hostresChange();

	function enableChange() {
		setRequired('pollport', $('#enable').prop('checked'));
		setRequired('rocommunity', $('#enable').prop('checked'));
	}

	function trapenableChange() {
		setRequired('trapserver', $('#trapenable').prop('checked'));
		setRequired('trapserverport', $('#trapenable').prop('checked'));
		setRequired('trapstring', $('#trapenable').prop('checked'));
	}

	function hostresChange() {
		if ($('#hostres').prop('checked')) {
			$('#mibii').prop('checked', true);
			noMibii = true;
		} else {
			noMibii = false;
		}
	}

	$('#enable').change(function() {
		enableChange();
	});

	$('#trapenable').change(function() {
		trapenableChange();
	});

	$('#hostres').change(function() {
		hostresChange();
	});

	$('#mibii').change(function() {
		if (noMibii) {
			$('#mibii').prop('checked', 'true');
		}
	});

	$('[name=btntoggleall]').hide();
});
//]]>
</script>

<?php include("foot.inc");
