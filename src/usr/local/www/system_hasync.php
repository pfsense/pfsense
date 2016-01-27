<?php
/*
	system_hasync.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2012 Darren Embry <dse@webonastick.com>.
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
##|*IDENT=page-system-hasync
##|*NAME=System: High Availability Sync
##|*DESCR=Allow access to the 'System: High Availability Sync' page.
##|*MATCH=system_hasync.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['hasync'])) {
	$config['hasync'] = array();
}

$a_hasync = &$config['hasync'];

$checkbox_names = array(
	'pfsyncenabled',
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
	'synchronizelb',
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
			' It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.<br />' .
			'This setting should be enabled on all members of a failover group.<br />' .
			'Clicking "Save" will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)');

$section->addInput(new Form_Select(
	'pfsyncinterface',
	'Synchronize Interface',
	$pconfig['pfsyncinterface'],
	$iflist
))->setHelp('If Synchronize States is enabled this interface will be used for communication.<br />' .
			'We recommend setting this to an interface other than LAN! A dedicated interface works the best.<br />' .
			'You must define an IP on each machine participating in this failover group.<br />' .
			'You must have an IP assigned to the interface on any participating sync nodes.');

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
))->setHelp('Enter the IP address of the firewall to which the selected configuration sections should be synchronized.<br /><br />' .
			'XMLRPC sync is currently only supported over connections using the same protocol and port as this system - make sure the remote system\'s port and protocol are set accordingly!<br />' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!');

$section->addInput(new Form_Input(
	'username',
	'Remote System Username',
	'text',
	$pconfig['username']
))->setHelp('Enter the webConfigurator username of the system entered above for synchronizing your configuration.<br />' .
			'Do not use the Synchronize Config to IP and username option on backup cluster members!');

$section->addPassword(new Form_Input(
	'passwordfld',
	'Remote System Password',
	'password',
	$pconfig['passwordfld']
))->setHelp('Enter the webConfigurator password of the system entered above for synchronizing your configuration.<br />' .
			'Do not use the Synchronize Config to IP and password option on backup cluster members!');

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
	'OpenVPN configuration ',
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
	'Synchronize Wake on LAN',
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
	'synchronizelb',
	'Synchronize Load Balancer',
	'Load Balancer configuration ',
	($pconfig['synchronizelb'] === 'on'),
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
