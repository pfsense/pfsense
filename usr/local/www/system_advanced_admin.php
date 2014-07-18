<?php
/* $Id$ */
/*
	system_advanced_admin.php
	part of pfSense
	Copyright (C) 2005-2010 Scott Ullrich

	Copyright (C) 2008 Shrew Soft Inc

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_BUILDER_BINARIES:	/usr/bin/killall
	pfSense_MODULE:	system
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
$pconfig['beast_protection'] = isset($config['system']['webgui']['beast_protection']);
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
if (is_array($a_cert) && count($a_cert))
	$certs_available = true;

if (!$pconfig['webguiproto'] || !$certs_available)
	$pconfig['webguiproto'] = "http";

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['webguiport'])
		if(!is_port($_POST['webguiport']))
			$input_errors[] = gettext("You must specify a valid webConfigurator port number");

	if ($_POST['max_procs'])
		if(!is_numeric($_POST['max_procs']) || ($_POST['max_procs'] < 1) || ($_POST['max_procs'] > 500))
			$input_errors[] = gettext("Max Processes must be a number 1 or greater");

	if ($_POST['althostnames']) {
		$althosts = explode(" ", $_POST['althostnames']);
		foreach ($althosts as $ah)
			if (!is_hostname($ah))
				$input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."),htmlspecialchars($ah));
	}

	if ($_POST['sshport'])
		if(!is_port($_POST['sshport']))
			$input_errors[] = gettext("You must specify a valid port number");

	if($_POST['sshdkeyonly'] == "yes")
		$config['system']['ssh']['sshdkeyonly'] = "enabled";
	else if (isset($config['system']['ssh']['sshdkeyonly']))
		unset($config['system']['ssh']['sshdkeyonly']);

	ob_flush();
	flush();

	if (!$input_errors) {

		if (update_if_changed("webgui protocol", $config['system']['webgui']['protocol'], $_POST['webguiproto']))
			$restart_webgui = true;
		if (update_if_changed("webgui port", $config['system']['webgui']['port'], $_POST['webguiport']))
			$restart_webgui = true;
		if (update_if_changed("webgui certificate", $config['system']['webgui']['ssl-certref'], $_POST['ssl-certref']))
			$restart_webgui = true;
		if (update_if_changed("webgui max processes", $config['system']['webgui']['max_procs'], $_POST['max_procs']))
			$restart_webgui = true;

		if ($_POST['disablehttpredirect'] == "yes") {
			$config['system']['webgui']['disablehttpredirect'] = true;
			$restart_webgui = true;
		} else {
			unset($config['system']['webgui']['disablehttpredirect']);
			$restart_webgui = true;
		}
		if ($_POST['quietlogin'] == "yes") {
			$config['system']['webgui']['quietlogin'] = true;
		} else {
			unset($config['system']['webgui']['quietlogin']);
		}

		if($_POST['disableconsolemenu'] == "yes")
			$config['system']['disableconsolemenu'] = true;
		else
			unset($config['system']['disableconsolemenu']);

		if ($_POST['noantilockout'] == "yes")
			$config['system']['webgui']['noantilockout'] = true;
		else
			unset($config['system']['webgui']['noantilockout']);

		if ($_POST['enableserial'] == "yes" || $g['enableserial_force'])
			$config['system']['enableserial'] = true;
		else
			unset($config['system']['enableserial']);

		if (is_numeric($_POST['serialspeed']))
			$config['system']['serialspeed'] = $_POST['serialspeed'];
		else
			unset($config['system']['serialspeed']);

		if ($_POST['primaryconsole'])
			$config['system']['primaryconsole'] = $_POST['primaryconsole'];
		else
			unset($config['system']['primaryconsole']);

		if ($_POST['nodnsrebindcheck'] == "yes")
			$config['system']['webgui']['nodnsrebindcheck'] = true;
		else
			unset($config['system']['webgui']['nodnsrebindcheck']);

		if ($_POST['nohttpreferercheck'] == "yes")
			$config['system']['webgui']['nohttpreferercheck'] = true;
		else
			unset($config['system']['webgui']['nohttpreferercheck']);

		if ($_POST['beast_protection'] == "yes")
			$config['system']['webgui']['beast_protection'] = true;
		else
			unset($config['system']['webgui']['beast_protection']);

		if ($_POST['loginautocomplete'] == "yes")
			$config['system']['webgui']['loginautocomplete'] = true;
		else
			unset($config['system']['webgui']['loginautocomplete']);

		if ($_POST['althostnames'])
			$config['system']['webgui']['althostnames'] = $_POST['althostnames'];
		else
			unset($config['system']['webgui']['althostnames']);

		$sshd_enabled = $config['system']['enablesshd'];
		if($_POST['enablesshd'])
			$config['system']['enablesshd'] = "enabled";
		else
			unset($config['system']['enablesshd']);

		$sshd_keyonly = isset($config['system']['sshdkeyonly']);
		if ($_POST['sshdkeyonly'])
			$config['system']['sshdkeyonly'] = true;
		else
			unset($config['system']['sshdkeyonly']);

		$sshd_port = $config['system']['ssh']['port'];
		if ($_POST['sshport'])
			$config['system']['ssh']['port'] = $_POST['sshport'];
		else if (isset($config['system']['ssh']['port']))
			unset($config['system']['ssh']['port']);

		if (($sshd_enabled != $config['system']['enablesshd']) ||
			($sshd_keyonly != $config['system']['sshdkeyonly']) ||
			($sshd_port != $config['system']['ssh']['port']))
			$restart_sshd = true;

		if ($restart_webgui) {
			global $_SERVER;
			$http_host_port = explode("]", $_SERVER['HTTP_HOST']);
			/* IPv6 address check */
			if(strstr($_SERVER['HTTP_HOST'], "]")) {
				if(count($http_host_port) > 1) {
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
			if ($port)
				$url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
			else
				$url = "{$prot}://{$host}/system_advanced_admin.php";
		}

		write_config();

		$retval = filter_configure();
	    $savemsg = get_std_save_message($retval);

		if ($restart_webgui)
			$savemsg .= sprintf("<br />" . gettext("One moment...redirecting to %s in 20 seconds."),$url);

		conf_mount_rw();
		setup_serial_port();
		// Restart DNS in case dns rebinding toggled
		if (isset($config['dnsmasq']['enable']))
			services_dnsmasq_configure();
		elseif (isset($config['unbound']['enable']))
			services_unbound_configure();
		conf_mount_ro();
	}
}

unset($hwcrypto);
$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
if ($fd) {
	while (!feof($fd)) {
		$dmesgl = fgets($fd);
		if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches)) {
				unset($pconfig['beast_protection']);
				$disable_beast_option = "disabled";
				$hwcrypto = $matches[1];
			break;
		}
	}
	fclose($fd);
}

$pgtitle = array(gettext("System"),gettext("Advanced: Admin Access"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function prot_change() {

	if (document.iform.https_proto.checked)
		document.getElementById("ssl_opts").style.display="";
	else
		document.getElementById("ssl_opts").style.display="none";
}

//]]>
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system_advanced_admin.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system advanced admin">
			<tr>
				<td>
					<?php
						$tab_array = array();
						$tab_array[] = array(gettext("Admin Access"), true, "system_advanced_admin.php");
						$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
						$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
						$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
						$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
						$tab_array[] = array(gettext("Notifications"), false, "system_advanced_notifications.php");
						display_top_tabs($tab_array);
					?>
				</td>
			</tr>
			<tr>
				<td id="mainarea">
					<div class="tabcont">
						<span class="vexpl">
							<span class="red">
								<strong><?=gettext("NOTE:"); ?>&nbsp;</strong>
							</span>
							<?=gettext("The options on this page are intended for use by advanced users only."); ?>
							<br />
						</span>
						<br />
						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("webConfigurator"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Protocol"); ?></td>
								<td width="78%" class="vtable">
									<?php
										if ($pconfig['webguiproto'] == "http")
											$http_chk = "checked=\"checked\"";
										if ($pconfig['webguiproto'] == "https")
											$https_chk = "checked=\"checked\"";
										if (!$certs_available)
											$https_disabled = "disabled=\"disabled\"";
									?>
									<input name="webguiproto" id="http_proto" type="radio" value="http" <?=$http_chk;?> onclick="prot_change()" />
									<?=gettext("HTTP"); ?>
									&nbsp;&nbsp;&nbsp;
									<input name="webguiproto" id="https_proto" type="radio" value="https" <?=$https_chk;?> <?=$https_disabled;?> onclick="prot_change()" />
									<?=gettext("HTTPS"); ?>
									<?php if (!$certs_available): ?>
									<br />
									<?=gettext("No Certificates have been defined. You must"); ?>
									<a href="system_certmanager.php"><?=gettext("Create or Import"); ?></a>
									<?=gettext("a Certificate before SSL can be enabled."); ?>
									<?php endif; ?>
								</td>
							</tr>
							<tr id="ssl_opts">
								<td width="22%" valign="top" class="vncell"><?=gettext("SSL Certificate"); ?></td>
								<td width="78%" class="vtable">
									<select name="ssl-certref" id="ssl-certref" class="formselect">
										<?php
											foreach($a_cert as $cert):
												$selected = "";
												if ($pconfig['ssl-certref'] == $cert['refid'])
													$selected = "selected=\"selected\"";
										?>
										<option value="<?=$cert['refid'];?>" <?=$selected;?>><?=$cert['descr'];?></option>
										<?php
										endforeach;
										if (!count($a_cert))
											echo "<option></option>";
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td valign="top" class="vncell"><?=gettext("TCP port"); ?></td>
								<td class="vtable">
									<input name="webguiport" type="text" class="formfld unknown" id="webguiport" size="5" value="<?=htmlspecialchars($config['system']['webgui']['port']);?>" />
									<br />
									<span class="vexpl">
										<?=gettext("Enter a custom port number for the webConfigurator " .
										"above if you want to override the default (80 for HTTP, 443 " .
										"for HTTPS). Changes will take effect immediately after save."); ?>
									</span>
								</td>
							</tr>
							<tr>
								<td valign="top" class="vncell"><?=gettext("Max Processes"); ?></td>
								<td class="vtable">
									<input name="max_procs" type="text" class="formfld unknown" id="max_procs" size="5" value="<?=htmlspecialchars($pconfig['max_procs']);?>" />
									<br />
									<span class="vexpl">
										<?=gettext("Enter the number of webConfigurator processes you " .
										"want to run. This defaults to 2. Increasing this will allow more " .
										"users/browsers to access the GUI concurrently."); ?>
									</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("WebGUI redirect"); ?></td>
								<td width="78%" class="vtable">
									<input name="disablehttpredirect" type="checkbox" id="disablehttpredirect" value="yes" <?php if ($pconfig['disablehttpredirect']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable webConfigurator redirect rule"); ?></strong>
									<br />
									<?php echo gettext("When this is unchecked, access to the webConfigurator " .
									"is always permitted even on port 80, regardless of the listening port configured. " .
									"Check this box to disable this automatically added redirect rule. ");
									?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("WebGUI Login Autocomplete"); ?></td>
								<td width="78%" class="vtable">
									<input name="loginautocomplete" type="checkbox" id="loginautocomplete" value="yes" <?php if ($pconfig['loginautocomplete']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Enable webConfigurator login autocomplete"); ?></strong>
									<br />
									<?php echo gettext("When this is checked, login credentials for the webConfigurator " .
									"may be saved by the browser. While convenient, some security standards require this to be disabled. " .
									"Check this box to enable autocomplete on the login form so that browsers will prompt to save credentials (NOTE: Some browsers do not respect this option). ");
									?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("WebGUI login messages"); ?></td>
								<td width="78%" class="vtable">
									<input name="quietlogin" type="checkbox" id="quietlogin" value="yes" <?php if ($pconfig['quietlogin']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable logging of webConfigurator successful logins"); ?></strong>
									<br />
									<?php echo gettext("When this is checked, successful logins to the webConfigurator " .
									"will not be logged.");
									?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Anti-lockout"); ?></td>
								<td width="78%" class="vtable">
									<?php
										if($config['interfaces']['lan']) 
											$lockout_interface = "LAN";
										else 
											$lockout_interface = "WAN";
									?>
									<input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable webConfigurator anti-lockout rule"); ?></strong>
									<br />
									<?php printf(gettext("When this is unchecked, access to the webConfigurator " .
									"on the %s interface is always permitted, regardless of the user-defined firewall " .
									"rule set. Check this box to disable this automatically added rule, so access " .
									"to the webConfigurator is controlled by the user-defined firewall rules " .
									"(ensure you have a firewall rule in place that allows you in, or you will " .
									"lock yourself out!)"), $lockout_interface); ?>
									<em> <?=gettext("Hint: the &quot;Set interface(s) IP address&quot; option in the console menu resets this setting as well."); ?> </em>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("DNS Rebind Check"); ?></td>
								<td width="78%" class="vtable">
									<input name="nodnsrebindcheck" type="checkbox" id="nodnsrebindcheck" value="yes" <?php if ($pconfig['nodnsrebindcheck']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable DNS Rebinding Checks"); ?></strong>
									<br />
									<?php echo gettext("When this is unchecked, your system " .
									"is protected against <a href=\"http://en.wikipedia.org/wiki/DNS_rebinding\">DNS Rebinding attacks</a>. " .
									"This blocks private IP responses from your configured DNS servers. Check this box to disable this protection if it interferes with " .
									"webConfigurator access or name resolution in your environment. "); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Alternate Hostnames"); ?></td>
								<td width="78%" class="vtable">
									<input name="althostnames" type="text" class="formfld unknown" id="althostnames" size="75" value="<?=htmlspecialchars($pconfig['althostnames']);?>"/>
									<br />
									<strong><?=gettext("Alternate Hostnames for DNS Rebinding and HTTP_REFERER Checks"); ?></strong>
									<br />
									<?php echo gettext("Here you can specify alternate hostnames by which the router may be queried, to " . 
									"bypass the DNS Rebinding Attack checks. Separate hostnames with spaces."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Browser HTTP_REFERER enforcement"); ?></td>
								<td width="78%" class="vtable">
									<input name="nohttpreferercheck" type="checkbox" id="nohttpreferercheck" value="yes" <?php if ($pconfig['nohttpreferercheck']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable HTTP_REFERER enforcement check"); ?></strong>
									<br />
									<?php echo gettext("When this is unchecked, access to the webConfigurator " .
									"is protected against HTTP_REFERER redirection attempts. " .
									"Check this box to disable this protection if you find that it interferes with " .
									"webConfigurator access in certain corner cases such as using external scripts to interact with this system. More information on HTTP_REFERER is available from <a target='_blank' href='http://en.wikipedia.org/wiki/HTTP_referrer'>Wikipedia</a>."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("BEAST Attack Protection"); ?></td>
								<td width="78%" class="vtable">
									<input name="beast_protection" type="checkbox" id="beast_protection" value="yes" <?php if ($pconfig['beast_protection']) echo "checked=\"checked\""; ?> <?= $disable_beast_option ?>/>
									<strong><?=gettext("Mitigate the BEAST SSL Attack"); ?></strong>
									<br />
									<?php echo gettext("When this is checked, the webConfigurator can mitigate BEAST SSL attacks. ") ?>
									<br />
									<?php 	if ($disable_beast_option) {
											echo "<br />" . sprintf(gettext("This option has been automatically disabled because a conflicting cryptographic accelerator card has been detected (%s)."), $hwcrypto) . "<br /><br />";
										} ?>
									<?php echo gettext("This option is off by default because Hifn accelerators do NOT work with this option, and the GUI will not function. " .
									"It is possible that other accelerators have a similar problem that is not yet known/documented. " .
									"More information on BEAST is available from <a target='_blank' href='https://en.wikipedia.org/wiki/Transport_Layer_Security#BEAST_attack'>Wikipedia</a>."); ?>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Secure Shell"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Secure Shell Server"); ?></td>
								<td width="78%" class="vtable">
									<input name="enablesshd" type="checkbox" id="enablesshd" value="yes" <?php if (isset($pconfig['enablesshd'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Enable Secure Shell"); ?></strong>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Authentication Method"); ?></td>
								<td width="78%" class="vtable">
									<input name="sshdkeyonly" type="checkbox" id="sshdkeyonly" value="yes" <?php if ($pconfig['sshdkeyonly']) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Disable password login for Secure Shell (RSA/DSA key only)"); ?></strong>
									<br />
									<?=gettext("When enabled, authorized keys need to be configured for each"); ?>
									<a href="system_usermanager.php"><?=gettext("user"); ?></a>
									<?=gettext("that has been granted secure shell access."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("SSH port"); ?></td>
								<td width="78%" class="vtable">
									<input name="sshport" type="text" id="sshport" value="<?php echo $pconfig['sshport']; ?>" />
									<br />
									<span class="vexpl"><?=gettext("Note: Leave this blank for the default of 22."); ?></span>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Serial Communications"); ?></td>
							</tr>
							<?php if (!$g['enableserial_force'] && ($g['platform'] == "pfSense" || $g['platform'] == "cdrom" || file_exists("/etc/nano_use_vga.txt"))): ?>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Serial Terminal"); ?></td>
								<td width="78%" class="vtable">
									<input name="enableserial" type="checkbox" id="enableserial" value="yes" <?php if (isset($pconfig['enableserial'])) echo "checked=\"checked\""; ?> />
									<strong><?=gettext("Enables the first serial port with 115200/8/N/1 by default, or another speed selectable below."); ?></strong>
									<span class="vexpl"><?=gettext("Note:  This will redirect the console output and messages to the serial port. You can still access the console menu from the internal video card/keyboard. A <b>null modem</b> serial cable or adapter is required to use the serial console."); ?></span>
								</td>
							</tr>
							<?php endif; ?>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Serial Speed")?></td>
								<td width="78%" class="vtable">
									<select name="serialspeed" id="serialspeed" class="formselect">
										<option value="115200" <?php if ($pconfig['serialspeed'] == "115200") echo "selected=\"selected\"";?>>115200</option>
										<option value="57600"  <?php if ($pconfig['serialspeed'] == "57600")  echo "selected=\"selected\"";?>>57600</option>
										<option value="38400"  <?php if ($pconfig['serialspeed'] == "38400")  echo "selected=\"selected\"";?>>38400</option>
										<option value="19200"  <?php if ($pconfig['serialspeed'] == "19200")  echo "selected=\"selected\"";?>>19200</option>
										<option value="14400"  <?php if ($pconfig['serialspeed'] == "14400")  echo "selected=\"selected\"";?>>14400</option>
										<option value="9600"   <?php if ($pconfig['serialspeed'] == "9600")   echo "selected=\"selected\"";?>>9600</option>
									</select> bps
									<br /><?=gettext("Allows selection of different speeds for the serial console port."); ?>
								</td>
							</tr>
							<?php if (!$g['primaryconsole_force'] && ($g['platform'] == "pfSense" || $g['platform'] == "cdrom" || file_exists("/etc/nano_use_vga.txt"))): ?>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Primary Console")?></td>
								<td width="78%" class="vtable">
									<select name="primaryconsole" id="primaryconsole" class="formselect">
										<option value="serial"   <?php if ($pconfig['primaryconsole'] == "serial")   echo "selected=\"selected\"";?>>Serial Console</option>
										<option value="video"  <?php if ($pconfig['primaryconsole'] == "video")  echo "selected=\"selected\"";?>>VGA Console</option>
									</select>
									<br /><?=gettext("Select the preferred console if multiple consoles are present. The preferred console will show pfSense boot script output. All consoles display OS boot messages, console messages, and the console menu."); ?>
								</td>
							</tr>
							<?php endif; ?>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Console Options"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Console menu"); ?></td>
								<td width="78%" class="vtable">
									<input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked=\"checked\""; ?>  />
									<strong><?=gettext("Password protect the console menu"); ?></strong>
									<br />
									<span class="vexpl"><?=gettext("Changes to this option will take effect after a reboot."); ?></span>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>							
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%"><input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" /></td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</form>
	<script type="text/javascript">
	//<![CDATA[
		prot_change();
	//]]>
	</script>

<?php include("fend.inc"); ?>
<?php
	if ($restart_webgui)
		echo "<meta http-equiv=\"refresh\" content=\"20;url={$url}\" />";
?>
</body>
</html>

<?php
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
