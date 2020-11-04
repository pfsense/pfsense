<?php
/*
 * system_hasync.php
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
##|*IDENT=page-system-hasync
##|*NAME=System: High Availability Sync
##|*DESCR=Allow access to the 'System: High Availability Sync' page.
##|*MATCH=system_hasync.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('hasync'));
$a_hasync = &$config['hasync'];

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

	if (!empty($pconfig['pfsyncpeerip']) && !is_ipaddrv4($pconfig['pfsyncpeerip'])) {
		$input_errors[] = gettext("pfsync Synchronize Peer IP must be an IPv4 IP.");
	}

	if (!empty($pconfig['synchronizetoip']) && !is_ipaddr($pconfig['synchronizetoip'])) {
		$input_errors[] = gettext("pfsync Synchronize Peer IP must be a valid IP address.");
	}

	if (!$input_errors) {
		write_config("Updated High Availability Sync configuration");
		interfaces_sync_setup();
		header("Location: system_hasync.php");
		exit();
	}
}

foreach ($checkbox_names as $name) {
	$pconfig[$name] = $a_hasync[$name];
}
$pconfig['pfsyncpeerip']	= $a_hasync['pfsyncpeerip'];
$pconfig['pfsyncinterface'] = $a_hasync['pfsyncinterface'];
$pconfig['synchronizetoip'] = $a_hasync['synchronizetoip'];
$pconfig['username']		= $a_hasync['username'];
$pconfig['passwordfld']	 = $a_hasync['password'];

$ifaces = get_configured_interface_with_descr();
$ifaces["lo0"] = "loopback";

$pgtitle = array(gettext("System"), gettext("High Availability Sync"));
$shortcut_section = "carp";

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

$section = new Form_Section('State Synchronization Settings (pfsync)');

$section->addInput(new Form_Checkbox(
	'pfsyncenabled',
	'Synchronize states',
	'pfsync transfers state insertion, update, and deletion messages between firewalls.',
	($pconfig['pfsyncenabled'] === 'on'),
	'on'
))->setHelp('Each firewall sends these messages out via multicast on a specified interface, using the PFSYNC protocol (IP Protocol 240).' .
			' It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.%1$s' .
			'This setting should be enabled on all members of a failover group.%1$s' .
			'Clicking "Save" will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)', '<br />');

$section->addInput(new Form_Select(
	'pfsyncinterface',
	'Synchronize Interface',
	$pconfig['pfsyncinterface'],
	$iflist
))->setHelp('If Synchronize States is enabled this interface will be used for communication.%1$s' .
			'It is recommended to set this to an interface other than LAN! A dedicated interface works the best.%1$s' .
			'An IP must be defined on each machine participating in this failover group.%1$s' .
			'An IP must be assigned to the interface on any participating sync nodes.', '<br />');

$section->addInput(new Form_Input(
	'pfsyncpeerip',
	'pfsync Synchronize Peer IP',
	'text',
	$pconfig['pfsyncpeerip'],
	['placeholder' => 'IP Address']
))->setHelp('Setting this option will force pfsync to synchronize its state table to this IP address. The default is directed multicast.');

$form->add($section);

$section = new Form_Section('Configuration Synchronization Settings (XMLRPC Sync)');

$section->addInput(new Form_Input(
	'synchronizetoip',
	'Synchronize Config to IP',
	'text',
	$pconfig['synchronizetoip'],
	['placeholder' => 'IP Address']
))->setHelp('Enter the IP address of the firewall to which the selected configuration sections should be synchronized.%1$s%1$s' .
			'XMLRPC sync is currently only supported over connections using the same protocol and port as this system - make sure the remote system\'s port and protocol are set accordingly!%1$s' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!', '<br />');

$section->addInput(new Form_Input(
	'username',
	'Remote System Username',
	'text',
	$pconfig['username'],
	['autocomplete' => 'new-password']
))->setHelp('Enter the webConfigurator username of the system entered above for synchronizing the configuration.%1$s' .
			'Do not use the Synchronize Config to IP and username option on backup cluster members!', '<br />');

$section->addPassword(new Form_Input(
	'passwordfld',
	'Remote System Password',
	'password',
	$pconfig['passwordfld']
))->setHelp('Enter the webConfigurator password of the system entered above for synchronizing the configuration.%1$s' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!', '<br />');

$section->addInput(new Form_Checkbox(
	'adminsync',
	'Synchronize admin',
	'synchronize admin accounts and autoupdate sync password.',
	($pconfig['adminsync'] === 'on'),
	'on'
))->setHelp('By default, the admin account does not synchronize, and each node may have a different admin password.%1$s' .
			'This option automatically updates XMLRPC Remote System Password when the password is changed on 
			the Remote System Username account.', '<br />');

$group = new Form_MultiCheckboxGroup('Select options to sync');

$group->add(new Form_MultiCheckbox(
	'synchronizeusers',
	'Synchronize Users and Groups',
	'User manager users and groups',
	($pconfig['synchronizeusers'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeauthservers',
	'Synchronize Auth Servers',
	'Authentication servers (e.g. LDAP, RADIUS)',
	($pconfig['synchronizeauthservers'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizecerts',
	'Synchronize Certificates',
	'Certificate Authorities, Certificates, and Certificate Revocation Lists',
	($pconfig['synchronizecerts'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizerules',
	'Synchronize Rules',
	'Firewall rules ',
	($pconfig['synchronizerules'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeschedules',
	'Synchronize Firewall schedules',
	'Firewall schedules ',
	($pconfig['synchronizeschedules'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizealiases',
	'Synchronize Firewall aliases',
	'Firewall aliases ',
	($pconfig['synchronizealiases'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizenat',
	'Synchronize NAT',
	'NAT configuration ',
	($pconfig['synchronizenat'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeipsec',
	'Synchronize IPsec',
	'IPsec configuration ',
	($pconfig['synchronizeipsec'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizeopenvpn',
	'Synchronize OpenVPN',
	'OpenVPN configuration (Implies CA/Cert/CRL Sync) ',
	($pconfig['synchronizeopenvpn'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizedhcpd',
	'Synchronize DHCPD',
	'DHCP Server settings ',
	($pconfig['synchronizedhcpd'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizewol',
	'Synchronize Wake-on-LAN',
	'WoL Server settings ',
	($pconfig['synchronizewol'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizestaticroutes',
	'Synchronize Static Routes',
	'Static Route configuration ',
	($pconfig['synchronizestaticroutes'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizevirtualip',
	'Synchronize Virtual IPs',
	'Virtual IPs ',
	($pconfig['synchronizevirtualip'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizetrafficshaper',
	'Synchronize traffic shaper (queues)',
	'Traffic Shaper configuration ',
	($pconfig['synchronizetrafficshaper'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizetrafficshaperlimiter',
	'Synchronize traffic shaper (limiter)',
	'Traffic Shaper Limiters configuration ',
	($pconfig['synchronizetrafficshaperlimiter'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizednsforwarder',
	'Synchronize DNS (Forwarder/Resolver)',
	'DNS Forwarder and DNS Resolver configurations ',
	($pconfig['synchronizednsforwarder'] === 'on'),
	'on'
));

$group->add(new Form_MultiCheckbox(
	'synchronizecaptiveportal',
	'Synchronize Captive Portal)',
	'Captive Portal ',
	($pconfig['synchronizecaptiveportal'] === 'on'),
	'on'
));

$section->add($group);

$form->add($section);

print($form);

include("foot.inc");
