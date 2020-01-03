<?php
/*
 * system_advanced_admin.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-system-advanced-admin
##|*NAME=System: Advanced: Admin Access Page
##|*DESCR=Allow access to the 'System: Advanced: Admin Access' page.
##|*MATCH=system_advanced_admin.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

init_config_arr(array('system', 'webgui'));
init_config_arr(array('system', 'ssh'));

$valid_webguiproto = array('http', 'https');

$pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
$pconfig['webguiport'] = $config['system']['webgui']['port'];
$pconfig['max_procs'] = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;
$pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
$pconfig['disablehttpredirect'] = isset($config['system']['webgui']['disablehttpredirect']);
$pconfig['disablehsts'] = isset($config['system']['webgui']['disablehsts']);
$pconfig['ocsp-staple'] = $config['system']['webgui']['ocsp-staple'];
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);
$pconfig['nohttpreferercheck'] = isset($config['system']['webgui']['nohttpreferercheck']);
$pconfig['pagenamefirst'] = isset($config['system']['webgui']['pagenamefirst']);
$pconfig['loginautocomplete'] = isset($config['system']['webgui']['loginautocomplete']);
$pconfig['althostnames'] = $config['system']['webgui']['althostnames'];
$pconfig['enableserial'] = $config['system']['enableserial'];
$pconfig['serialspeed'] = $config['system']['serialspeed'];
$pconfig['primaryconsole'] = $config['system']['primaryconsole'];
$pconfig['enablesshd'] = $config['system']['ssh']['enable'];
$pconfig['sshport'] = $config['system']['ssh']['port'];
$pconfig['sshdkeyonly'] = $config['system']['ssh']['sshdkeyonly'];
$pconfig['sshdagentforwarding'] = isset($config['system']['ssh']['sshdagentforwarding']);
$pconfig['quietlogin'] = isset($config['system']['webgui']['quietlogin']);
$pconfig['sshguard_threshold'] = $config['system']['sshguard_threshold'] ?? '';
$pconfig['sshguard_blocktime'] = $config['system']['sshguard_blocktime'] ?? '';
$pconfig['sshguard_detection_time'] = $config['system']['sshguard_detection_time'] ?? '';
$pconfig['sshguard_whitelist'] = $config['system']['sshguard_whitelist'] ?? '';

init_config_arr(array('cert'));
$a_cert = &$config['cert'];
$certs_available = false;

if (is_array($a_cert) && count($a_cert)) {
	$certs_available = true;
} else {
	$a_cert = array();
}

if (!$pconfig['webguiproto'] || !$certs_available) {
	$pconfig['webguiproto'] = "http";
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */

	if (!in_array($pconfig['webguiproto'], $valid_webguiproto)) {
		$input_errors[] = gettext("A valid webConfigurator protocol must be specified");
	}

	if ($_POST['webguiport']) {
		if (!is_port($_POST['webguiport'])) {
			$input_errors[] = gettext("A valid webConfigurator port number must be specified");
		}
	}

	if ($_POST['max_procs']) {
		if (!is_numericint($_POST['max_procs']) || ($_POST['max_procs'] < 1) || ($_POST['max_procs'] > 500)) {
			$input_errors[] = gettext("Max Processes must be a number 1 or greater");
		}
	}

	if ($_POST['althostnames']) {
		$althosts = explode(" ", $_POST['althostnames']);
		foreach ($althosts as $ah) {
			if (!is_ipaddr($ah) && !is_hostname($ah)) {
				$input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."), htmlspecialchars($ah));
			}
		}
	}

	if ($_POST['sshport']) {
		if (!is_port($_POST['sshport'])) {
			$input_errors[] = gettext("A valid port number must be specified");
		}
	}

	$whitelist_addresses = array();
	for ($i = 0; isset($_POST['address' . $i]); $i++) {
		/* Ignore blank fields */
		if (empty($_POST['address' . $i])) {
			continue;
		}

		$whitelist_address = $_POST['address' . $i] . '/' .
		    $_POST['address_subnet'. $i];

		if (!is_subnet($whitelist_address)) {
			$input_errors[] = sprintf(gettext(
			    "Invalid subnet '%s' added to Login Protection Whitelist"),
			    $whitelist_address);
			break;
		}
		$whitelist_addresses[] = $whitelist_address;
	}
	$pconfig['sshguard_whitelist'] = implode(' ', $whitelist_addresses);

	ob_flush();
	flush();

	if (!$input_errors) {
		if (update_if_changed("webgui protocol", $config['system']['webgui']['protocol'], $_POST['webguiproto'])) {
			$restart_webgui = true;
		}

		if (update_if_changed("webgui port", $config['system']['webgui']['port'], $_POST['webguiport'])) {
			$restart_webgui = true;
		}

		if (update_if_changed("webgui certificate", $config['system']['webgui']['ssl-certref'], $_POST['ssl-certref'])) {
			$restart_webgui = true;
		}

		if (update_if_changed("webgui max processes", $config['system']['webgui']['max_procs'], $_POST['max_procs'])) {
			$restart_webgui = true;
		}

		// Restart the webgui only if this actually changed
		if ($_POST['webgui-redirect'] == "yes") {
			if ($config['system']['webgui']['disablehttpredirect'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['disablehttpredirect'] = true;
		} else {
			if (isset($config['system']['webgui']['disablehttpredirect'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['disablehttpredirect']);
		}

		if ($_POST['webgui-hsts'] == "yes") {
			if ($config['system']['webgui']['disablehsts'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['disablehsts'] = true;
		} else {
			if (isset($config['system']['webgui']['disablehsts'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['disablehsts']);
		}

		if ($_POST['ocsp-staple'] == "yes") {
			if ($config['system']['webgui']['ocsp-staple'] != true) {
				$restart_webgui = true;
			}

			$config['system']['webgui']['ocsp-staple'] = true;
		} else {
			if (isset($config['system']['webgui']['ocsp-staple'])) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['ocsp-staple']);
		}
		
		if ($_POST['webgui-login-messages'] == "yes") {
			$config['system']['webgui']['quietlogin'] = true;
		} else {
			unset($config['system']['webgui']['quietlogin']);
		}

		if ($_POST['disableconsolemenu'] == "yes") {
			$config['system']['disableconsolemenu'] = true;
		} else {
			unset($config['system']['disableconsolemenu']);
		}

		if ($_POST['noantilockout'] == "yes") {
			$config['system']['webgui']['noantilockout'] = true;
		} else {
			unset($config['system']['webgui']['noantilockout']);
		}

		if ($_POST['enableserial'] == "yes" || $g['enableserial_force']) {
			$config['system']['enableserial'] = true;
		} else {
			unset($config['system']['enableserial']);
		}

		if (is_numericint($_POST['serialspeed'])) {
			$config['system']['serialspeed'] = $_POST['serialspeed'];
		} else {
			unset($config['system']['serialspeed']);
		}

		if ($_POST['primaryconsole']) {
			$config['system']['primaryconsole'] = $_POST['primaryconsole'];
		} else {
			unset($config['system']['primaryconsole']);
		}

		if ($_POST['nodnsrebindcheck'] == "yes") {
			$config['system']['webgui']['nodnsrebindcheck'] = true;
		} else {
			unset($config['system']['webgui']['nodnsrebindcheck']);
		}

		if ($_POST['nohttpreferercheck'] == "yes") {
			$config['system']['webgui']['nohttpreferercheck'] = true;
		} else {
			unset($config['system']['webgui']['nohttpreferercheck']);
		}

		if ($_POST['pagenamefirst'] == "yes") {
			$config['system']['webgui']['pagenamefirst'] = true;
		} else {
			unset($config['system']['webgui']['pagenamefirst']);
		}

		if ($_POST['loginautocomplete'] == "yes") {
			$config['system']['webgui']['loginautocomplete'] = true;
		} else {
			unset($config['system']['webgui']['loginautocomplete']);
		}

		if ($_POST['althostnames']) {
			$config['system']['webgui']['althostnames'] = $_POST['althostnames'];
		} else {
			unset($config['system']['webgui']['althostnames']);
		}

		$sshd_enabled = $config['system']['ssh']['enable'];
		if ($_POST['enablesshd']) {
			$config['system']['ssh']['enable'] = "enabled";
		} else {
			unset($config['system']['ssh']['enable']);
		}

		$sshd_keyonly = $config['system']['ssh']['sshdkeyonly'];
		if ($_POST['sshdkeyonly'] == "enabled") {
			$config['system']['ssh']['sshdkeyonly'] = "enabled";
		} else if ($_POST['sshdkeyonly'] == "both") {
			$config['system']['ssh']['sshdkeyonly'] = "both";
		} else if (isset($config['system']['ssh']['sshdkeyonly'])) {
			unset($config['system']['ssh']['sshdkeyonly']);
		}

		$sshd_agentforwarding = isset($config['system']['ssh']['sshdagentforwarding']);
		if ($_POST['sshdagentforwarding']) {
			$config['system']['ssh']['sshdagentforwarding'] = 'enabled';
		} else if (isset($config['system']['ssh']['sshdagentforwarding'])) {
			unset($config['system']['ssh']['sshdagentforwarding']);
		}

		$sshd_port = $config['system']['ssh']['port'];
		if ($_POST['sshport']) {
			$config['system']['ssh']['port'] = $_POST['sshport'];
		} else if (isset($config['system']['ssh']['port'])) {
			unset($config['system']['ssh']['port']);
		}

		if (($sshd_enabled != $config['system']['ssh']['enable']) ||
		    ($sshd_keyonly != $config['system']['ssh']['sshdkeyonly']) ||
		    ($sshd_agentforwarding != $config['system']['ssh']['sshdagentforwarding']) ||
		    ($sshd_port != $config['system']['ssh']['port'])) {
			$restart_sshd = true;
		}

		if ($restart_webgui) {
			global $_SERVER;
			$http_host_port = explode("]", $_SERVER['HTTP_HOST']);
			/* IPv6 address check */
			if (strstr($_SERVER['HTTP_HOST'], "]")) {
				if (count($http_host_port) > 1) {
					array_pop($http_host_port);
					$host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
					$host = "[{$host}]";
				} else {
					$host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
					$host = "[{$host}]";
				}
			} else {
				list($host) = explode(":", $_SERVER['HTTP_HOST']);
			}
			$prot = in_array($config['system']['webgui']['protocol'], $valid_webguiproto) ? $config['system']['webgui']['protocol'] : 'http' ;
			$port = $config['system']['webgui']['port'];
			if ($port) {
				$url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
			} else {
				$url = "{$prot}://{$host}/system_advanced_admin.php";
			}
		}

		$restart_sshguard = false;
		if (update_if_changed("login protection threshold",
		    $config['system']['sshguard_threshold'],
		    $pconfig['sshguard_threshold'])) {
			$restart_sshguard = true;
		}
		if (update_if_changed("login protection blocktime",
		    $config['system']['sshguard_blocktime'],
		    $pconfig['sshguard_blocktime'])) {
			$restart_sshguard = true;
		}
		if (update_if_changed("login protection detection_time",
		    $config['system']['sshguard_detection_time'],
		    $pconfig['sshguard_detection_time'])) {
			$restart_sshguard = true;
		}
		if (update_if_changed("login protection whitelist",
		    $config['system']['sshguard_whitelist'],
		    $pconfig['sshguard_whitelist'])) {
			$restart_sshguard = true;
		}

		write_config();

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
		if ($restart_sshguard) {
			$retval |= system_syslogd_start(true);
		}

		if ($restart_webgui) {
			$extra_save_msg = sprintf("<br />" . gettext("One moment...redirecting to %s in 20 seconds."), $url);
		}

		console_configure();
		// Restart DNS in case dns rebinding toggled
		if (isset($config['dnsmasq']['enable'])) {
			services_dnsmasq_configure();
		} elseif (isset($config['unbound']['enable'])) {
			services_unbound_configure();
		}
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Admin Access"));
$pglinks = array("", "@self", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval, $extra_save_msg);
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), true, "system_advanced_admin.php");
$tab_array[] = array(htmlspecialchars(gettext("Firewall & NAT")), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

$form = new Form;
$section = new Form_Section('webConfigurator');
$group = new Form_Group('Protocol');

$group->add(new Form_Checkbox(
	'webguiproto',
	'Protocol',
	'HTTP',
	($pconfig['webguiproto'] == 'http'),
	'http'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'webguiproto',
	'Protocol',
	'HTTPS',
	($pconfig['webguiproto'] == 'https'),
	'https'
))->displayAsRadio();

if (!$certs_available) {
	$group->setHelp('No Certificates have been defined. A certificate is required before SSL can be enabled. %1$s Create or Import %2$s a Certificate.',
		'<a href="system_certmanager.php">', '</a>');
}

$section->add($group);

$section->addInput($input = new Form_Select(
	'ssl-certref',
	'SSL Certificate',
	$pconfig['ssl-certref'],
	cert_build_list('cert', 'HTTPS')
))->setHelp('Certificates known to be incompatible with use for HTTPS are not included in this list.');

$section->addInput(new Form_Input(
	'webguiport',
	'TCP port',
	'number',
	$config['system']['webgui']['port'],
	['min' => 1, 'max' => 65535]
))->setHelp('Enter a custom port number for the webConfigurator '.
	'above to override the default (80 for HTTP, 443 for HTTPS). '.
	'Changes will take effect immediately after save.');

$section->addInput(new Form_Input(
	'max_procs',
	'Max Processes',
	'number',
	$pconfig['max_procs']
))->setHelp('Enter the number of webConfigurator processes to run. '.
	'This defaults to 2. Increasing this will allow more '.
	'users/browsers to access the GUI concurrently.');

$section->addInput(new Form_Checkbox(
	'webgui-redirect',
	'WebGUI redirect',
	'Disable webConfigurator redirect rule',
	$pconfig['disablehttpredirect']
))->setHelp('When this is unchecked, access to the webConfigurator '.
	'is always permitted even on port 80, regardless of the listening port configured. '.
	'Check this box to disable this automatically added redirect rule.');

$section->addInput(new Form_Checkbox(
	'webgui-hsts',
	'HSTS',
	'Disable HTTP Strict Transport Security',
	$pconfig['disablehsts']
))->setHelp('When this is unchecked, Strict-Transport-Security HTTPS response header '.
	'is sent by the webConfigurator to the browser. This will force the browser to use '.
	'only HTTPS for future requests to the firewall FQDN. Check this box to disable HSTS. '.
	'(NOTE: Browser-specific steps are required for disabling to take effect when the browser '.
	'already visited the FQDN while HSTS was enabled.)');
	
$section->addInput(new Form_Checkbox(
	'ocsp-staple',
	'OCSP Must-Staple',
	'Force OCSP Stapling in nginx',
	$pconfig['ocsp-staple']
))->setHelp('When this is checked, OCSP Stapling is forced on in nginx. Remember to '.
	'upload your certificate as a full chain, not just the certificate, or this option '.
	'will be ignored by nginx.');

$section->addInput(new Form_Checkbox(
	'loginautocomplete',
	'WebGUI Login Autocomplete',
	'Enable webConfigurator login autocomplete',
	$pconfig['loginautocomplete']
))->setHelp('When this is checked, login credentials for the webConfigurator may '.
	'be saved by the browser. While convenient, some security standards require this '.
	'to be disabled. Check this box to enable autocomplete on the login form so that '.
	'browsers will prompt to save credentials (NOTE: Some browsers do not respect '.
	'this option).');

$section->addInput(new Form_Checkbox(
	'webgui-login-messages',
	'WebGUI login messages',
	'Disable logging of webConfigurator successful logins',
	$pconfig['quietlogin']
))->setHelp('When this is checked, successful logins to the webConfigurator will '.
	'not be logged.');

if ($config['interfaces']['lan']) {
	$lockout_interface = "LAN";
} else {
	$lockout_interface = "WAN";
}

$section->addInput(new Form_Checkbox(
	'noantilockout',
	'Anti-lockout',
	'Disable webConfigurator anti-lockout rule',
	$pconfig['noantilockout']
))->setHelp('When this is '.
	'unchecked, access to the webConfigurator on the %1$s interface is always '.
	'permitted, regardless of the user-defined firewall rule set. Check this box to '.
	'disable this automatically added rule, so access to the webConfigurator is '.
	'controlled by the user-defined firewall rules (ensure a firewall rule is '.
	'in place that allows access, to avoid being locked out!) %2$sHint: the &quot;Set interface(s) IP address&quot; '.
	'option in the console menu resets this setting as well.%3$s', $lockout_interface, '<em>', '</em>');

$section->addInput(new Form_Checkbox(
	'nodnsrebindcheck',
	'DNS Rebind Check',
	'Disable DNS Rebinding Checks',
	$pconfig['nodnsrebindcheck']
))->setHelp('When this is unchecked, the system is protected against %1$sDNS Rebinding attacks%2$s. '.
	'This blocks private IP responses from the configured DNS servers. Check this '.
	'box to disable this protection if it interferes with webConfigurator access or '.
	'name resolution in the environment.',
	'<a href="http://en.wikipedia.org/wiki/DNS_rebinding">', '</a>');

$section->addInput(new Form_Input(
	'althostnames',
	'Alternate Hostnames',
	'text',
	htmlspecialchars($pconfig['althostnames'])
))->setHelp('Alternate Hostnames for DNS Rebinding and HTTP_REFERER Checks. '.
	'Specify alternate hostnames by which the router may be queried, to '.
	'bypass the DNS Rebinding Attack checks. Separate hostnames with spaces.');

$section->addInput(new Form_Checkbox(
	'nohttpreferercheck',
	'Browser HTTP_REFERER enforcement',
	'Disable HTTP_REFERER enforcement check',
	$pconfig['nohttpreferercheck']
))->setHelp('When this is unchecked, access to the webConfigurator is protected '.
	'against HTTP_REFERER redirection attempts. Check this box to disable this '.
	'protection if it interferes with webConfigurator access in certain '.
	'corner cases such as using external scripts to interact with this system. More '.
	'information on HTTP_REFERER is available from %1$sWikipedia%2$s',
	'<a target="_blank" href="http://en.wikipedia.org/wiki/HTTP_referrer">', '</a>.');

gen_pagenamefirst_field($section, $pconfig['pagenamefirst']);

$form->add($section);
$section = new Form_Section('Secure Shell');

$section->addInput(new Form_Checkbox(
	'enablesshd',
	'Secure Shell Server',
	'Enable Secure Shell',
	isset($pconfig['enablesshd'])
));

$section->addInput(new Form_Select(
	'sshdkeyonly',
	'SSHd Key Only',
	$pconfig['sshdkeyonly'],
	array(
		"disabled" => "Password or Public Key",
		"enabled" => "Public Key Only",
		"both" => "Require Both Password and Public Key",
	)
))->setHelp('When set to %3$sPublic Key Only%4$s, SSH access requires authorized keys and these '.
	'keys must be configured for each %1$suser%2$s that has been granted secure shell access. '.
	'If set to %3$sRequire Both Password and Public Key%4$s, the SSH daemon requires both authorized keys ' .
	'%5$sand%6$s valid passwords to gain access. The default %3$sPassword or Public Key%4$s setting allows '.
	'either a valid password or a valid authorized key to login.',
	'<a href="system_usermanager.php">', '</a>', '<i>', '</i>', '<b>', '</b>');

$section->addInput(new Form_Checkbox(
	'sshdagentforwarding',
	'Allow Agent Forwarding',
	'Enables ssh-agent forwarding support.',
	$pconfig['sshdagentforwarding']
));

$section->addInput(new Form_Input(
	'sshport',
	'SSH port',
	'number',
	$pconfig['sshport'],
	['min' => 1, 'max' => 65535, 'placeholder' => 22]
))->setHelp('Note: Leave this blank for the default of 22.');

$form->add($section);
$section = new Form_Section('Login Protection');

$section->addinput(new form_input(
	'sshguard_threshold',
	'Threshold',
	'number',
	$pconfig['sshguard_threshold'],
	['min' => 10, 'step' => 10, 'placeholder' => 30]
))->setHelp('Block attackers when their cumulative attack score exceeds '.
	'threshold.  Most attacks have a score of 10.');

$section->addinput(new form_input(
	'sshguard_blocktime',
	'Blocktime',
	'number',
	$pconfig['sshguard_blocktime'],
	['min' => 10, 'step' => 10, 'placeholder' => 120]
))->setHelp('Block attackers for initially blocktime seconds after exceeding '.
	'threshold. Subsequent blocks increase by a factor of 1.5.%s'.
	'Attacks are unblocked at random intervals, so actual block '.
	'times will be longer.', '<br />');

$section->addinput(new form_input(
	'sshguard_detection_time',
	'Detection time',
	'number',
	$pconfig['sshguard_detection_time'],
	['min' => 10, 'step' => 10, 'placeholder' => 1800]
))->setHelp('Remember potential attackers for up to detection_time seconds '.
	'before resetting their score.');

$counter = 0;
$addresses = explode(' ', $pconfig['sshguard_whitelist']);

$numaddrs = count($addresses);

while ($counter < $numaddrs) {
	list($address, $address_subnet) = explode("/", $addresses[$counter]);

	$group = new Form_Group($counter == 0 ? 'Whitelist' : '');
	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		'address' . $counter,
		'Address',
		$address,
		'BOTH'
	))->addMask('address_subnet' . $counter, $address_subnet)->setWidth(4);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning btn-xs');

	if ($counter == ($numaddrs - 1)) {
		$group->setHelp(gettext(sprintf("%sAddresses added to the whitelist will bypass login protection.%s", 
			'<span class="text-danger">', '</span>')));
	}

	$section->add($group);
	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add address',
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

$form->add($section);
$section = new Form_Section('Serial Communications');

if (!$g['enableserial_force']) {
	$section->addInput(new Form_Checkbox(
		'enableserial',
		'Serial Terminal',
		'Enables the first serial port with 115200/8/N/1 by default, or another speed selectable below.',
		isset($pconfig['enableserial'])
	))->setHelp('Note:	This will redirect the console output and messages to '.
		'the serial port. The console menu can still be accessed from the internal video '.
		'card/keyboard. A %1$snull modem%2$s serial cable or adapter is required to use the '.
		'serial console.', '<b>', '</b>');
}

$section->addInput(new Form_Select(
	'serialspeed',
	'Serial Speed',
	$pconfig['serialspeed'],
	array_combine(array(115200, 57600, 38400, 19200, 14400, 9600), array(115200, 57600, 38400, 19200, 14400, 9600))
))->setHelp('Allows selection of different speeds for the serial console port.');

if (!$g['enableserial_force'] && !$g['primaryconsole_force']) {
	$section->addInput(new Form_Select(
		'primaryconsole',
		'Primary Console',
		$pconfig['primaryconsole'],
		array(
			'serial' => gettext('Serial Console'),
			'video' => gettext('VGA Console'),
		)
	))->setHelp('Select the preferred console if multiple consoles are present. '.
		'The preferred console will show pfSense boot script output. All consoles '.
		'display OS boot messages, console messages, and the console menu.');
}

$form->add($section);
$section = new Form_Section('Console Options');

$section->addInput(new Form_Checkbox(
	'disableconsolemenu',
	'Console menu',
	'Password protect the console menu',
	$pconfig['disableconsolemenu']
));

$form->add($section);
print $form;

?>
</div>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	checkLastRow();

	// ---------- On initial page load ------------------------------------------------------------
	hideInput('ssl-certref', $('input[name=webguiproto]:checked').val() == 'http');
	hideCheckbox('webgui-hsts', $('input[name=webguiproto]:checked').val() == 'http');
	hideCheckbox('ocsp-staple', "<?php 
			$cert_temp = lookup_cert($config['system']['webgui']['ssl-certref']);
			echo (cert_get_ocspstaple($cert_temp['crt']) ? "true" : "false");
			?>" === "true");

	// ---------- Click checkbox handlers ---------------------------------------------------------
	 $('[name=webguiproto]').click(function () {
		hideInput('ssl-certref', $('input[name=webguiproto]:checked').val() == 'http');
		hideCheckbox('webgui-hsts', $('input[name=webguiproto]:checked').val() == 'http');
	});
});
//]]>
</script>

<?php
include("foot.inc");

if ($restart_webgui) {
	echo "<meta http-equiv=\"refresh\" content=\"20;url={$url}\" />";
}

if ($restart_sshd) {
	killbyname("sshd");
	log_error(gettext("secure shell configuration has changed. Stopping sshd."));

	if ($config['system']['ssh']['enable']) {
		log_error(gettext("secure shell configuration has changed. Restarting sshd."));
		send_event("service restart sshd");
	}
}

if ($restart_webgui) {
	ob_flush();
	flush();
	log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
	send_event("service restart webgui");
}
?>
