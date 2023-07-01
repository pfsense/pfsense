<?php
/*
 * system_advanced_admin.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
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
require_once("system_advanced_admin.inc");

$pconfig = getAdvancedAdminConfig();

if ($_POST) {
	$rv = doAdvancedAdminPOST($_POST);

	$pconfig = $rv['pconfig'];
	$input_errors = $rv['input_errors'];
	$extra_save_msg = $rv['extra'];
	$restart_webgui = $rv['restartui'];
	$restart_sshd = $rv['restartsshd'];
	$changes_applied = $rv['changesapplied'];
	$retval = $rv['retval'];
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
	'HTTPS (SSL/TLS)',
	($pconfig['webguiproto'] == 'https'),
	'https'
))->displayAsRadio();

if (!$pconfig['certsavailable']) {
	$group->setHelp('No Certificates have been defined. A certificate is required before SSL/TLS can be enabled. %1$s Create or Import %2$s a Certificate.',
		'<a href="system_certmanager.php">', '</a>');
}

$section->add($group);

$section->addInput($input = new Form_Select(
	'ssl-certref',
	'SSL/TLS Certificate',
	$pconfig['ssl-certref'],
	cert_build_list('cert', 'HTTPS')
))->setHelp('Certificates known to be incompatible with use for HTTPS are not included in this list.');

$section->addInput(new Form_Input(
	'webguiport',
	'TCP port',
	'number',
	$pconfig['webguiport'],
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
	'GUI login messages',
	'Lower syslog level for successful GUI login events',
	$pconfig['quietlogin']
))->setHelp('When this is checked, successful logins to the GUI will '.
	'be logged as a lower non-emergency level. Note: The console bell ' .
	'behavior can be controlled independently on the Notifications tab.');

$section->addInput(new Form_Checkbox(
	'roaming',
	'Roaming',
	'Allow GUI administrator client IP address to change during a login session',
	$pconfig['roaming']
))->setHelp('When this is checked, the login session to the webConfigurator remains '.
	'valid if the client source IP address changes.');

if ($pconfig['interfaces_lan']) {
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
	'<a href="https://en.wikipedia.org/wiki/DNS_rebinding">', '</a>');

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
	'<a target="_blank" href="https://en.wikipedia.org/wiki/HTTP_referrer">', '</a>.');

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

	$group = new Form_Group($counter == 0 ? 'Pass list' : '');
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
		$group->setHelp(gettext(sprintf("%sAddresses added to the pass list will bypass login protection.%s", 
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

if (!g_get('enableserial_force')) {
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

/* Get the current console list from the kernel environment */
$current_consoles = [];
exec('/bin/kenv -q console 2>/dev/null', $current_consoles);
$current_consoles = explode(',', $current_consoles[0]);
/* The first console in the list is the current active primary console.
 * Use this as the default value if the user has not stored their own preference.
 * See https://redmine.pfsense.org/issues/12960
 */
$active_primary = ($current_consoles[0] == 'comconsole') ? 'serial' : 'video';

if (!g_get('enableserial_force') && !g_get('primaryconsole_force')) {
	$section->addInput(new Form_Select(
		'primaryconsole',
		'Primary Console',
		(!empty($pconfig['primaryconsole'])) ? $pconfig['primaryconsole'] : $active_primary,
		array(
			'serial' => gettext('Serial Console'),
			'video' => gettext('Video Console'),
		)
	))->setHelp('Select the preferred console if multiple consoles are present. '.
		'The preferred console will show %1$s boot script output. All consoles '.
		'display OS boot messages, console messages, and the console menu.', g_get('product_label'));
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
			$cert_temp = lookup_cert($pconfig['ssl-certref']);
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

if ($restart_sshd) {
	restart_SSHD();
}

if ($restart_webgui) {
	restart_GUI();
}

?>
