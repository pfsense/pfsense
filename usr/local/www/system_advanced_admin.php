<?php
/* $Id$ */
/*
	system_advanced_admin.php
	part of pfSense
	Copyright (C) 2005-2007 Scott Ullrich

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
$pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
$pconfig['disablehttpredirect'] = isset($config['system']['disablehttpredirect']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['enableserial'] = $config['system']['enableserial'];
$pconfig['enablesshd'] = $config['system']['enablesshd'];
$pconfig['sshport'] = $config['system']['ssh']['port'];
$pconfig['sshdkeyonly'] = isset($config['system']['ssh']['sshdkeyonly']);

$a_cert =& $config['system']['cert'];

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

		if ($_POST['disablehttpredirect'] == "yes") {
			$config['system']['disablehttpredirect'] = true;
			$restart_webgui = true;
		} else {
			unset($config['system']['disablehttpredirect']);
			$restart_webgui = true;
		}

		if($_POST['disableconsolemenu'] == "yes") {
			$config['system']['disableconsolemenu'] = true;
			auto_login();
		} else {
			unset($config['system']['disableconsolemenu']);
			auto_login();
		}

		if ($_POST['noantilockout'] == "yes")
			$config['system']['webgui']['noantilockout'] = true;
		else
			unset($config['system']['webgui']['noantilockout']);

		if ($_POST['enableserial'] == "yes")
			$config['system']['enableserial'] = true;
		else
			unset($config['system']['enableserial']);

		if ($_POST['nodnsrebindcheck'] == "yes")
			$config['system']['webgui']['nodnsrebindcheck'] = true;
		else
			unset($config['system']['webgui']['nodnsrebindcheck']);

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
			list($host) = explode(":", $_SERVER['HTTP_HOST']);
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
		conf_mount_ro();
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Admin Access"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--

function prot_change() {

	if (document.iform.https_proto.checked)
		document.getElementById("ssl_opts").style.display="";
	else
		document.getElementById("ssl_opts").style.display="none";
}

//-->
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<form action="system_advanced_admin.php" method="post" name="iform" id="iform">
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
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
								<strong><?=gettext("NOTE"); ?>:&nbsp</strong>
							</span>
							<?=gettext("The options on this page are intended for use by advanced users only."); ?>
							<br/>
						</span>
						<br/>
						<table width="100%" border="0" cellpadding="6" cellspacing="0">
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("webConfigurator"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Protocol"); ?></td>
								<td width="78%" class="vtable">
									<?php
										if ($pconfig['webguiproto'] == "http")
											$http_chk = "checked";
										if ($pconfig['webguiproto'] == "https")
											$https_chk = "checked";
										if (!$certs_available)
											$https_disabled = "disabled";
									?>
									<input name="webguiproto" id="http_proto" type="radio" value="http" <?=$http_chk;?> onClick="prot_change()">
									HTTP
									&nbsp;&nbsp;&nbsp;
									<input name="webguiproto" id="https_proto" type="radio" value="https" <?=$https_chk;?> <?=$https_disabled;?> onClick="prot_change()">
									HTTPS
									<?php if (!$certs_available): ?>
									<br/>
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
													$selected = "selected";
										?>
										<option value="<?=$cert['refid'];?>"<?=$selected;?>><?=$cert['name'];?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td valign="top" class="vncell"><?=gettext("TCP port"); ?></td>
								<td class="vtable">
									<input name="webguiport" type="text" class="formfld unknown" id="webguiport" "size="5" value="<?=htmlspecialchars($config['system']['webgui']['port']);?>">
									<br>
									<span class="vexpl">
										<?=gettext("Enter a custom port number for the webConfigurator " .
										"above if you want to override the default (80 for HTTP, 443 " .
										"for HTTPS). Changes will take effect immediately after save."); ?>
									</span>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("WebGUI redirect"); ?></td>
								<td width="78%" class="vtable">
									<input name="disablehttpredirect" type="checkbox" id="disablehttpredirect" value="yes" <?php if ($pconfig['disablehttpredirect']) echo "checked"; ?> />
									<strong><?=gettext("Disable webConfigurator redirect rule"); ?></strong>
									<br/>
									<?php gettext("When this is unchecked, access to the webConfigurator " .
									"is always permitted even on port 80, regardless of the listening port configured." .
									"Check this box to disable this automatically added redirect rule. ");
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
									<input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked"; ?> />
									<strong><?=gettext("Disable webConfigurator anti-lockout rule"); ?></strong>
									<br/>
									<?php sprintf(gettext("When this is unchecked, access to the webConfigurator " .
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
									<input name="nodnsrebindcheck" type="checkbox" id="nodnsrebindcheck" value="yes" <?php if ($pconfig['nodnsrebindcheck']) echo "checked"; ?> />
									<strong><?=gettext("Disable webConfigurator DNS Rebinding Checks"); ?></strong>
									<br/>
									<?php echo gettext("When this is unchecked, access to the webConfigurator " .
									"is protected against <a href=\"http://en.wikipedia.org/wiki/DNS_rebinding\">DNS Rebinding attacks</a>. " .
									"Check this box to disable this protection if you find that it interferes with " .
									"webConfigurator access in certain corner cases. "); ?>
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
									<input name="enablesshd" type="checkbox" id="enablesshd" value="yes" <?php if (isset($pconfig['enablesshd'])) echo "checked"; ?> />
									<strong><?=gettext("Enable Secure Shell"); ?></strong>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Authentication Method"); ?></td>
								<td width="78%" class="vtable">
									<input name="sshdkeyonly" type="checkbox" id="sshdkeyonly" value="yes" <?php if ($pconfig['sshdkeyonly']) echo "checked"; ?> />
									<strong><?=gettext("Disable password login for Secure Shell (RSA key only)"); ?></strong>
									<br/>
									<?=gettext("When enabled, authorized keys need to be configured for each"); ?>
									<a href="system_usermanager.php"><?=gettext("user"); ?></a>
									<?=gettext("that has been granted secure shell access."); ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("SSH port"); ?></td>
								<td width="78%" class="vtable">
									<input name="sshport" type="text" id="sshport" value="<?php echo $pconfig['sshport']; ?>" />
									<br/>
									<span class="vexpl"><?=gettext("Note: Leave this blank for the default of 22."); ?></span>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php if($g['platform'] == "pfSense" || $g['platform'] == "cdrom"): ?>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Serial Communcations"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Serial Terminal"); ?></td>
								<td width="78%" class="vtable">
									<input name="enableserial" type="checkbox" id="enableserial" value="yes" <?php if (isset($pconfig['enableserial'])) echo "checked"; ?> />
									<strong><?=gettext("This will enable the first serial port with 9600/8/N/1"); ?></strong>
									<br>
									<span class="vexpl"><?=gettext("Note:  This will disable the internal video card/keyboard"); ?></span>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12">&nbsp;</td>
							</tr>
							<?php endif; ?>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Console Options"); ?></td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Console menu"); ?></td>
								<td width="78%" class="vtable">
									<input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>  />
									<strong><?=gettext("Password protect the console menu"); ?></strong>
									<br/>
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
	<script language="JavaScript" type="text/javascript">
	<!--
		prot_change();
	//-->
	</script>

<?php include("fend.inc"); ?>
<?php
	if ($restart_webgui)
		echo "<meta http-equiv=\"refresh\" content=\"20;url={$url}\">";
?>
</body>
</html>

<?php
if ($restart_sshd) {

	killbyname("sshd");
	log_error(gettext("secure shell configuration has changed. Stopping sshd."));

	if ($config['system']['enablesshd']) {
		log_error(gettext("secure shell configuration has changed. Restarting sshd."));
		touch("{$g['tmp_path']}/start_sshd");
	}
}
if ($restart_webgui) {
	ob_flush();
	flush();
	log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
	touch("{$g['tmp_path']}/restart_webgui");
}

?>
