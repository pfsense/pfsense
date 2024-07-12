<?php
/*
 * system_hasync.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-hasync
##|*NAME=System: High Availability Sync
##|*DESCR=Allow access to the 'System: High Availability Sync' page.
##|*MATCH=system_hasync.php*
##|-PRIV

require_once("guiconfig.inc");

config_init_path('hasync');
$a_hasync = config_get_path('hasync');

$checkbox_names = array(
	'pfsyncenabled',
	'adminsync',
	'synchronizeusers',
	'synchronizeauthservers',
	'synchronizecerts',
	'synchronizerules',
	'synchronizeschedules',
	'synchronizealiases',
	'synchronizenat',
	'synchronizeipsec',
	'synchronizeopenvpn',
	'synchronizedhcpd',
	'synchronizedhcrelay',
	'synchronizekea6',
	'synchronizedhcrelay6',
	'synchronizewol',
	'synchronizestaticroutes',
	'synchronizevirtualip',
	'synchronizetrafficshaper',
	'synchronizetrafficshaperlimiter',
	'synchronizednsforwarder',
	'synchronizecaptiveportal');

if ($_POST) {
	$pconfig = $_POST;
	foreach ($checkbox_names as $name) {
		$a_hasync[$name] = $pconfig[$name] ? $pconfig[$name] : false;
	}
	$old_pfhostid = isset($a_hasync['pfhostid']) ? $a_hasync['pfhostid'] : '';
	$a_hasync['pfhostid'] = strtolower(trim($pconfig['pfhostid']));
	$a_hasync['pfsyncpeerip'] = $pconfig['pfsyncpeerip'];
	$a_hasync['pfsyncinterface'] = $pconfig['pfsyncinterface'];
	$a_hasync['synchronizetoip'] = $pconfig['synchronizetoip'];
	$a_hasync['username'] = $pconfig['username'];

	if ($pconfig['passwordfld'] == $pconfig['passwordfld_confirm']) {
		if ($pconfig['passwordfld'] != DMYPWD) {
				$a_hasync['password'] = $pconfig['passwordfld'];
		}
	} else {
		$input_errors[] = gettext("Password and confirmation must match.");
	}

	if ((!empty($pconfig['pfhostid']) &&
	    !(ctype_xdigit($pconfig['pfhostid']) &&
	    (strlen($pconfig['pfhostid']) <= 8))) ||
	    ($pconfig['pfhostid'] === "0")) {
		$input_errors[] = gettext("Invalid Host ID. Must be a non-zero hexadecimal string 8 characters or less.");
	}

	if (!empty($pconfig['pfsyncpeerip']) && !is_ipaddrv4($pconfig['pfsyncpeerip'])) {
		$input_errors[] = gettext("pfsync Synchronize Peer IP must be an IPv4 IP.");
	}

	if (!empty($pconfig['synchronizetoip']) && !is_ipaddr($pconfig['synchronizetoip'])) {
		$input_errors[] = gettext("Synchronize Config to IP must be a valid IP address.");
	}

	if (!$input_errors) {
		config_set_path('hasync', $a_hasync);
		write_config("Updated High Availability Sync configuration");
		interfaces_sync_setup();
		if ($old_pfhostid != $a_hasync['pfhostid']) {
			filter_configure();
		}
		header("Location: system_hasync.php");
		exit();
	}
}

foreach ($checkbox_names as $name) {
	$pconfig[$name] = $a_hasync[$name];
}
$pconfig['pfhostid']	= $a_hasync['pfhostid'];
$pconfig['pfsyncpeerip']	= $a_hasync['pfsyncpeerip'];
$pconfig['pfsyncinterface'] = $a_hasync['pfsyncinterface'];
$pconfig['synchronizetoip'] = $a_hasync['synchronizetoip'];
$pconfig['username']		= $a_hasync['username'];
$pconfig['passwordfld']	 = $a_hasync['password'];

$ifaces = get_configured_interface_with_descr();
$ifaces["lo0"] = "loopback";

$pgtitle = array(gettext('System'), gettext('High Availability'));
$shortcut_section = 'carp';

// Build a list of available interfaces
$iflist = array();
foreach ($ifaces as $ifname => $iface) {
	$iflist[$ifname] = $iface;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section(gettext('State Synchronization Settings (pfsync)'));

$section->addInput(new Form_Checkbox(
	'pfsyncenabled',
	gettext('Synchronize states'),
	gettext('pfsync transfers state insertion, update, and deletion messages between firewalls.'),
	($pconfig['pfsyncenabled'] === 'on'),
	'on'
))->setHelp(gettext('Each firewall sends these messages out via multicast on a specified interface, using the PFSYNC protocol (IP Protocol 240).' .
			' It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.%1$s' .
			'This setting should be enabled on all members of a failover group.%1$s' .
			'Clicking "Save" will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)'), '<br />');

$section->addInput(new Form_Select(
	'pfsyncinterface',
	gettext('Synchronize Interface'),
	$pconfig['pfsyncinterface'],
	$iflist
))->setHelp(gettext('If Synchronize States is enabled this interface will be used for communication.%1$s' .
			'It is recommended to set this to an interface other than LAN! A dedicated interface works the best.%1$s' .
			'An IP must be defined on each machine participating in this failover group.%1$s' .
			'An IP must be assigned to the interface on any participating sync nodes.'), '<br />');

$section->addInput(new Form_Input(
	'pfhostid',
	gettext('Filter Host ID'),
	'text',
	$pconfig['pfhostid'],
	['placeholder' => substr(system_get_uniqueid(), -8)]
))->setHelp(gettext('Custom pf host identifier carried in state data to uniquely identify which host created a firewall state.%1$s' .
		'Must be a non-zero hexadecimal string 8 characters or less (e.g. 1, 2, ff01, abcdef01).%1$s' .
		'Each node participating in state synchronization must have a different ID.'), '<br />');

$section->addInput(new Form_Input(
	'pfsyncpeerip',
	gettext('pfsync Synchronize Peer IP'),
	'text',
	$pconfig['pfsyncpeerip'],
	['placeholder' => 'IP Address']
))->setHelp(gettext('Setting this option will force pfsync to synchronize its state table to this IP address. The default is directed multicast.'));

$form->add($section);

$section = new Form_Section(gettext('Configuration Synchronization Settings (XMLRPC Sync)'));

$section->addInput(new Form_Input(
	'synchronizetoip',
	gettext('Synchronize Config to IP'),
	'text',
	$pconfig['synchronizetoip'],
	['placeholder' => 'IP Address']
))->setHelp(gettext('Enter the IP address of the firewall to which the selected configuration sections should be synchronized.%1$s%1$s' .
			'XMLRPC sync is currently only supported over connections using the same protocol and port as this system - make sure the remote system\'s port and protocol are set accordingly!%1$s' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!'), '<br />');

$section->addInput(new Form_Input(
	'username',
	gettext('Remote System Username'),
	'text',
	$pconfig['username'],
	['autocomplete' => 'new-password']
))->setHelp(gettext('Enter the webConfigurator username of the system entered above for synchronizing the configuration.%1$s' .
			'Do not use the Synchronize Config to IP and username option on backup cluster members!'), '<br />');

$section->addPassword(new Form_Input(
	'passwordfld',
	gettext('Remote System Password'),
	'password',
	$pconfig['passwordfld']
))->setHelp(gettext('Enter the webConfigurator password of the system entered above for synchronizing the configuration.%1$s' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!'), '<br />');

$section->addInput(new Form_Checkbox(
	'adminsync',
	gettext('Synchronize admin'),
	gettext('synchronize admin accounts and autoupdate sync password.'),
	($pconfig['adminsync'] === 'on'),
	'on'
))->setHelp(gettext('By default, the admin account does not synchronize, and each node may have a different admin password.%1$s' .
			'This option automatically updates XMLRPC Remote System Password when the password is changed on
			the Remote System Username account.'), '<br />');

$group = new Form_MultiCheckboxGroup(gettext('Select options to sync'));

$group->add(new Form_MultiCheckbox(
	'synchronizeusers',
	gettext('Synchronize Users and Groups'),
	gettext('User manager users and groups'),
	($pconfig['synchronizeusers'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeauthservers',
	gettext('Synchronize Auth Servers'),
	gettext('Authentication servers (e.g. LDAP, RADIUS)'),
	($pconfig['synchronizeauthservers'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizecerts',
	gettext('Synchronize Certificates'),
	gettext('Certificate Authorities, Certificates, and Certificate Revocation Lists'),
	($pconfig['synchronizecerts'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizerules',
	gettext('Synchronize Rules'),
	gettext('Firewall rules'),
	($pconfig['synchronizerules'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeschedules',
	gettext('Synchronize Firewall schedules'),
	gettext('Firewall schedules'),
	($pconfig['synchronizeschedules'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizealiases',
	gettext('Synchronize Firewall aliases'),
	gettext('Firewall aliases'),
	($pconfig['synchronizealiases'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizenat',
	gettext('Synchronize NAT'),
	gettext('NAT configuration'),
	($pconfig['synchronizenat'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeipsec',
	gettext('Synchronize IPsec'),
	gettext('IPsec configuration'),
	($pconfig['synchronizeipsec'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeopenvpn',
	gettext('Synchronize OpenVPN'),
	gettext('OpenVPN configuration (Implies CA/Cert/CRL Sync)'),
	($pconfig['synchronizeopenvpn'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizedhcpd',
	gettext('Synchronize DHCPD'),
	gettext('DHCP Server settings'),
	($pconfig['synchronizedhcpd'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizedhcrelay',
	gettext('Synchronize DHCP Relay'),
	gettext('DHCP Relay settings'),
	($pconfig['synchronizedhcrelay'] === 'on'),
	'on'
));

if (dhcp_is_backend('kea')) {
	$group->add(new Form_MultiCheckbox(
		'synchronizekea6',
		gettext('Synchronize Kea DHCPv6'),
		gettext('DHCPv6 Server settings'),
		($pconfig['synchronizekea6'] === 'on'),
		'on'
	));	
}

$group->add(new Form_MultiCheckbox(
	'synchronizedhcrelay6',
	gettext('Synchronize DHCPv6 Relay'),
	gettext('DHCPv6 Relay settings'),
	($pconfig['synchronizedhcrelay6'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizewol',
	gettext('Synchronize Wake-on-LAN'),
	gettext('WoL Server settings'),
	($pconfig['synchronizewol'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizestaticroutes',
	gettext('Synchronize Static Routes'),
	gettext('Static Route configuration'),
	($pconfig['synchronizestaticroutes'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizevirtualip',
	gettext('Synchronize Virtual IPs'),
	gettext('Virtual IPs'),
	($pconfig['synchronizevirtualip'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizetrafficshaper',
	gettext('Synchronize traffic shaper (queues)'),
	gettext('Traffic Shaper configuration'),
	($pconfig['synchronizetrafficshaper'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizetrafficshaperlimiter',
	gettext('Synchronize traffic shaper (limiter)'),
	gettext('Traffic Shaper Limiters configuration'),
	($pconfig['synchronizetrafficshaperlimiter'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizednsforwarder',
	gettext('Synchronize DNS (Forwarder/Resolver)'),
	gettext('DNS Forwarder and DNS Resolver configurations'),
	($pconfig['synchronizednsforwarder'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizecaptiveportal',
	gettext('Synchronize Captive Portal'),
	gettext('Captive Portal'),
	($pconfig['synchronizecaptiveportal'] === 'on'),
	'on'
));

$section->add($group);

$form->add($section);

print($form);

include("foot.inc");
