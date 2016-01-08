<?php
/*
	system_advanced_admin.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
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
##|*IDENT=page-system-advanced-admin
##|*NAME=System: Advanced: Admin Access Page
##|*DESCR=Allow access to the 'System: Advanced: Admin Access' page.
##|*MATCH=system_advanced_admin.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
$pconfig['webguiport'] = $config['system']['webgui']['port'];
$pconfig['max_procs'] = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;
$pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
$pconfig['disablehttpredirect'] = isset($config['system']['webgui']['disablehttpredirect']);
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
$pconfig['enablesshd'] = $config['system']['enablesshd'];
$pconfig['sshport'] = $config['system']['ssh']['port'];
$pconfig['sshdkeyonly'] = isset($config['system']['ssh']['sshdkeyonly']);
$pconfig['quietlogin'] = isset($config['system']['webgui']['quietlogin']);

$a_cert =& $config['cert'];
$certs_available = false;

if (is_array($a_cert) && count($a_cert)) {
	$certs_available = true;
}

if (!$pconfig['webguiproto'] || !$certs_available) {
	$pconfig['webguiproto'] = "http";
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['webguiport']) {
		if (!is_port($_POST['webguiport'])) {
			$input_errors[] = gettext("You must specify a valid webConfigurator port number");
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
			if (!is_hostname($ah)) {
				$input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."), htmlspecialchars($ah));
			}
		}
	}

	if ($_POST['sshport']) {
		if (!is_port($_POST['sshport'])) {
			$input_errors[] = gettext("You must specify a valid port number");
		}
	}

	if ($_POST['sshdkeyonly'] == "yes") {
		$config['system']['ssh']['sshdkeyonly'] = "enabled";
	} else if (isset($config['system']['ssh']['sshdkeyonly'])) {
		unset($config['system']['ssh']['sshdkeyonly']);
	}

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
			if ($config['system']['webgui']['disablehttpredirect'] == true) {
				$restart_webgui = true;
			}

			unset($config['system']['webgui']['disablehttpredirect']);
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

		$sshd_enabled = $config['system']['enablesshd'];
		if ($_POST['enablesshd']) {
			$config['system']['enablesshd'] = "enabled";
		} else {
			unset($config['system']['enablesshd']);
		}

		$sshd_keyonly = isset($config['system']['sshdkeyonly']);
		if ($_POST['sshdkeyonly']) {
			$config['system']['sshdkeyonly'] = true;
		} else {
			unset($config['system']['sshdkeyonly']);
		}

		$sshd_port = $config['system']['ssh']['port'];
		if ($_POST['sshport']) {
			$config['system']['ssh']['port'] = $_POST['sshport'];
		} else if (isset($config['system']['ssh']['port'])) {
			unset($config['system']['ssh']['port']);
		}

		if (($sshd_enabled != $config['system']['enablesshd']) ||
		    ($sshd_keyonly != $config['system']['sshdkeyonly']) ||
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
			$prot = $config['system']['webgui']['protocol'];
			$port = $config['system']['webgui']['port'];
			if ($port) {
				$url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
			} else {
				$url = "{$prot}://{$host}/system_advanced_admin.php";
			}
		}

		write_config();

		$retval = filter_configure();
		$savemsg = get_std_save_message($retval);

		if ($restart_webgui) {
			$savemsg .= sprintf("<br />" . gettext("One moment...redirecting to %s in 20 seconds."), $url);
		}

		conf_mount_rw();
		setup_serial_port();
		// Restart DNS in case dns rebinding toggled
		if (isset($config['dnsmasq']['enable'])) {
			services_dnsmasq_configure();
		} elseif (isset($config['unbound']['enable'])) {
			services_unbound_configure();
		}
		conf_mount_ro();
	}
}

$pgtitle = array(gettext("System"), gettext("Advanced"), gettext("Admin Access"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Admin Access"), true, "system_advanced_admin.php");
$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
display_top_tabs($tab_array);

?><div id="container"><?php

$form = new Form;
$section = new Form_Section('WebConfigurator');
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

$group->setHelp($certs_available ? '':'No Certificates have been defined. You must '.
	'<a href="system_certmanager.php">'. gettext("Create or Import").'</a> '.
	'a Certificate before SSL can be enabled.');

$section->add($group);

$values = array();
foreach ($a_cert as $cert) {
	$values[ $cert['refid'] ] = $cert['descr'];
}

$section->addInput($input = new Form_Select(
	'ssl-certref',
	'SSL Certificate',
	$pconfig['ssl-certref'],
	$values
));

$section->addInput(new Form_Input(
	'webguiport',
	'TCP port',
	'number',
	$config['system']['webgui']['port'],
	['min' => 1, 'max' => 65535]
))->setHelp('Enter a custom port number for the webConfigurator '.
	'above if you want to override the default (80 for HTTP, 443 '.
	'for HTTPS). Changes will take effect immediately after save.');

$section->addInput(new Form_Input(
	'max_procs',
	'Max Processes',
	'number',
	$pconfig['max_procs']
))->setHelp('Enter the number of webConfigurator processes you '.
	'want to run. This defaults to 2. Increasing this will allow more '.
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
	'unchecked, access to the webConfigurator on the %s interface is always '.
	'permitted, regardless of the user-defined firewall rule set. Check this box to '.
	'disable this automatically added rule, so access to the webConfigurator is '.
	'controlled by the user-defined firewall rules (ensure you have a firewall rule '.
	'in place that allows you in, or you will lock yourself out!)<em>Hint: the &quot;Set interface(s) IP address&quot; '.
	'option in the console menu resets this setting as well.</em>', [$lockout_interface]);

$section->addInput(new Form_Checkbox(
	'nodnsrebindcheck',
	'DNS Rebind Check',
	'Disable DNS Rebinding Checks',
	$pconfig['nodnsrebindcheck']
))->setHelp('When this is unchecked, your system is protected against <a '.
	'href="http://en.wikipedia.org/wiki/DNS_rebinding">DNS Rebinding attacks</a>. '.
	'This blocks private IP responses from your configured DNS servers. Check this '.
	'box to disable this protection if it interferes with webConfigurator access or '.
	'name resolution in your environment.');

$section->addInput(new Form_Input(
	'althostnames',
	'Alternate Hostnames',
	'text',
	htmlspecialchars($pconfig['althostnames'])
))->setHelp('Alternate Hostnames for DNS Rebinding and HTTP_REFERER Checks. Here '.
	'you can specify alternate hostnames by which the router may be queried, to '.
	'bypass the DNS Rebinding Attack checks. Separate hostnames with spaces.');

$section->addInput(new Form_Checkbox(
	'nohttpreferercheck',
	'Browser HTTP_REFERER enforcement',
	'Disable HTTP_REFERER enforcement check',
	$pconfig['nohttpreferercheck']
))->setHelp('When this is unchecked, access to the webConfigurator is protected '.
	'against HTTP_REFERER redirection attempts. Check this box to disable this '.
	'protection if you find that it interferes with webConfigurator access in certain '.
	'corner cases such as using external scripts to interact with this system. More '.
	'information on HTTP_REFERER is available from <a target="_blank" '.
	'href="http://en.wikipedia.org/wiki/HTTP_referrer">Wikipedia</a>.');

$section->addInput(new Form_Checkbox(
	'pagenamefirst',
	'Browser tab text',
	'Display page name first in browser tab',
	$pconfig['pagenamefirst']
))->setHelp('When this is unchecked, the browser tab shows the host name followed '.
	'by the current page. Check this box to display the current page followed by the '.
	'host name.');

$form->add($section);
$section = new Form_Section('Secure Shell');

$section->addInput(new Form_Checkbox(
	'enablesshd',
	'Secure Shell Server',
	'Enable Secure Shell',
	isset($pconfig['enablesshd'])
));

$section->addInput(new Form_Checkbox(
	'sshdkeyonly',
	'Authentication Method',
	'Disable password login for Secure Shell (RSA/DSA key only)',
	$pconfig['sshdkeyonly']
))->setHelp('When enabled, authorized keys need to be configured for each <a '.
	'href="system_usermanager.php">user</a> that has been granted secure shell '.
	'access.');

$section->addInput(new Form_Input(
	'sshport',
	'SSH port',
	'number',
	$pconfig['sshport'],
	['min' => 1, 'max' => 65535, 'placeholder' => 22]
))->setHelp('Note: Leave this blank for the default of 22.');


if (!$g['enableserial_force'] && ($g['platform'] == $g['product_name'] || $g['platform'] == "cdrom")) {
	$form->add($section);
	$section = new Form_Section('Serial Communications');

	$section->addInput(new Form_Checkbox(
		'enableserial',
		'Serial Terminal',
		'Enables the first serial port with 115200/8/N/1 by default, or another speed selectable below.',
		isset($pconfig['enableserial'])
	))->setHelp('Note:	This will redirect the console output and messages to '.
		'the serial port. You can still access the console menu from the internal video '.
		'card/keyboard. A <b>null modem</b> serial cable or adapter is required to use the '.
		'serial console.');

	$section->addInput(new Form_Select(
		'serialspeed',
		'Serial Speed',
		$pconfig['serialspeed'],
		array_combine(array(115200, 57600, 38400, 19200, 14400, 9600), array(115200, 57600, 38400, 19200, 14400, 9600))
	))->setHelp('Allows selection of different speeds for the serial console port.');

	$section->addInput(new Form_Select(
		'primaryconsole',
		'Primary Console',
		$pconfig['primaryconsole'],
		array(
			'serial' => 'Serial Console',
			'video' => 'VGA Console',
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

	// ---------- On initial page load ------------------------------------------------------------

	hideInput('ssl-certref', $('input[name=webguiproto]:checked').val() == 'http');

	// ---------- Click checkbox handlers ---------------------------------------------------------

	 $('[name=webguiproto]').click(function () {
		hideInput('ssl-certref', $('input[name=webguiproto]:checked').val() == 'http');
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

	if ($config['system']['enablesshd']) {
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
