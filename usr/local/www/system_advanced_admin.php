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

##|+PRIV
##|*IDENT=page-system-advanced-admin
##|*NAME=System: Advanced: Admin Access Page
##|*DESCR=Allow access to the 'System: Advanced: Admin Access' page.
##|*MATCH=system_advanced_admin.php*
##|-PRIV


require("guiconfig.inc");

$pconfig['cert'] = base64_decode($config['system']['webgui']['certificate']);
$pconfig['key'] = base64_decode($config['system']['webgui']['private-key']);
$pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
$pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
$pconfig['enableserial'] = $config['system']['enableserial'];
$pconfig['enablesshd'] = $config['system']['enablesshd'];
$pconfig['sshport'] = $config['system']['ssh']['port'];
$pconfig['sshdkeyonly'] = $config['system']['ssh']['sshdkeyonly'];
$pconfig['authorizedkeys'] = base64_decode($config['system']['ssh']['authorizedkeys']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (($_POST['cert'] && !$_POST['key']) || ($_POST['key'] && !$_POST['cert']))
		$input_errors[] = "Certificate and key must always be specified together.";

	if ($_POST['cert'] && $_POST['key']) {
		if (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))
			$input_errors[] = "This certificate does not appear to be valid.";
		if (!strstr($_POST['key'], "BEGIN RSA PRIVATE KEY") || !strstr($_POST['key'], "END RSA PRIVATE KEY"))
			$input_errors[] = "This key does not appear to be valid.";
	}

	if ($_POST['sshport'])
		if(!is_port($_POST['sshport']))
			$input_errors[] = "You must specify a valid port number";

	if($_POST['sshdkeyonly'] == "yes")
		$config['system']['ssh']['sshdkeyonly'] = "enabled";
	else
		unset($config['system']['ssh']['sshdkeyonly']);

	ob_flush();
	flush();

	if (!$input_errors) {

		$oldcert = $config['system']['webgui']['certificate'];
		$oldkey = $config['system']['webgui']['private-key'];
		$config['system']['webgui']['certificate'] = base64_encode($_POST['cert']);
		$config['system']['webgui']['private-key'] = base64_encode($_POST['key']);

		if($_POST['disableconsolemenu'] == "yes") {
			$config['system']['disableconsolemenu'] = true;
			auto_login(true);
		} else {
			unset($config['system']['disableconsolemenu']);
			auto_login(false);
		}

		if ($_POST['noantilockout'] == "yes")
			$config['system']['webgui']['noantilockout'] = true;
		else
			unset($config['system']['webgui']['noantilockout']);

		if ($_POST['enableserial'] == "yes")
			$config['system']['enableserial'] = true;
		else
			unset($config['system']['enableserial']);

		if($_POST['enablesshd'] == "yes") {
			$config['system']['enablesshd'] = "enabled";
			touch("{$g['tmp_path']}/start_sshd");
		} else {
			unset($config['system']['enablesshd']);
			mwexec("/usr/bin/killall sshd");
		}

		$oldsshport = $config['system']['ssh']['port'];

		if ($_POST['sshdkeyonly'] == "yes") {
			$config['system']['sshdkeyonly'] = true;
			touch("{$g['tmp_path']}/start_sshd");
		} else {
			unset($config['system']['sshdkeyonly']);
			mwexec("/usr/bin/killall sshd");
		}

		$config['system']['ssh']['port'] = $_POST['sshport'];
		$config['system']['ssh']['authorizedkeys'] = base64_encode($_POST['authorizedkeys']);

		write_config();

		config_lock();
		$retval = filter_configure();
		if(stristr($retval, "error") <> true)
		    $savemsg = get_std_save_message($retval);
		else
		    $savemsg = $retval;
		config_unlock();

		conf_mount_rw();
		setup_serial_port();
		conf_mount_ro();
	}
}

$pgtitle = array("System","Advanced: Admin Access");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
	include("fbegin.inc");
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				<span class="vexpl">
					<span class="red">
						<strong>Note:</strong>
					</span>
					the options on this page are intended for use by advanced users only.
					<br/>
				</span>
				<br/>
			</td>
		</tr>
		<tr>
			<td class="tabnavtbl">
				<ul id="tabnav">
				<?php
					$tab_array = array();
					$tab_array[] = array("Admin Access", true, "system_advanced_admin.php");
					$tab_array[] = array("Firewall / NAT", false, "system_advanced_firewall.php");
					$tab_array[] = array("Networking", false, "system_advanced_network.php");
					$tab_array[] = array("Miscellaneous", false, "system_advanced_misc.php");
					$tab_array[] = array("System Tunables", false, "system_advanced_sysctl.php");
					display_top_tabs($tab_array);
				?>
				</ul>
			</td>
		</tr>
		<tr>
			<td class="tabcont">
				<form action="system_advanced_admin.php" method="post" name="iform" id="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td colspan="2" valign="top" class="listtopic">webConfigurator</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Certificate</td>
							<td width="78%" class="vtable">
								<textarea name="cert" cols="65" rows="7" id="cert" class="formpre"><?=htmlspecialchars($pconfig['cert']);?></textarea>
								<br/>
								Paste a signed certificate in X.509 PEM format here. <a href="javascript:if(openwindow('system_advanced_create_certs.php') == false) alert('Popup blocker detected.  Action aborted.');" >Create</a> certificates automatically.
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Key</td>
							<td width="78%" class="vtable">
								<textarea name="key" cols="65" rows="7" id="key" class="formpre"><?=htmlspecialchars($pconfig['key']);?></textarea>
								<br/>
								Paste an RSA private key in PEM format here.
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Anti-lockout</td>
							<td width="78%" class="vtable">
								<?php
									if($config['interfaces']['lan']) 
										$lockout_interface = "LAN";
									else 
										$lockout_interface = "WAN";
								?>
								<input name="noantilockout" type="checkbox" id="noantilockout" value="yes" <?php if ($pconfig['noantilockout']) echo "checked"; ?> />
								<strong>Disable webConfigurator anti-lockout rule</strong>
								<br/>
								By default, access to the webConfigurator on the <?=$lockout_interface;?>
								interface is always permitted, regardless of the user-defined filter
								rule set. Enable this feature to control webConfigurator access (make
								sure to have a filter rule in place that allows you in, or you will
								lock yourself out!). <em> Hint: the &quot;set configure IP address&quot;
								option in the console menu resets this setting as well. </em>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic">Secure Shell</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Secure Shell Server</td>
							<td width="78%" class="vtable">
								<input name="enablesshd" type="checkbox" id="enablesshd" value="yes" <?php if (isset($pconfig['enablesshd'])) echo "checked"; ?> />
								<strong>Enable Secure Shell</strong>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Authentication Method</td>
							<td width="78%" class="vtable">
								<input name="sshdkeyonly" type="checkbox" id="sshdkeyonly" value="yes" <?php if (isset($pconfig['sshdkeyonly'])) echo "checked"; ?> />
								<strong>Disable Password login for Secure Shell (rsa key only)</strong>
								<br/>
								When this option is enabled, you will need to configure
								allowed keys for each user that has secure shell
								access.
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">SSH port</td>
							<td width="78%" class="vtable">
								<input name="sshport" type="text" id="sshport" value="<?php echo $pconfig['sshport']; ?>" />
								<br/>
								<span class="vexpl">Note:  Leave this blank for the default of 22</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Authorizedkeys");?></td>
							<td width="78%" class="vtable">
								<textarea name="authorizedkeys" cols="65" rows="7" id="authorizedkeys" class="formfld_cert"><?=htmlspecialchars($pconfig['authorizedkeys']);?></textarea>
								<br/>
								Paste an authorized keys file here.
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>
						<?php if($g['platform'] == "pfSense" || $g['platform'] == "cdrom"): ?>
						<tr>
							<td colspan="2" valign="top" class="listtopic">Serial Communcations</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Serial Terminal</td>
							<td width="78%" class="vtable">
								<input name="enableserial" type="checkbox" id="enableserial" value="yes" <?php if (isset($pconfig['enableserial'])) echo "checked"; ?> />
								<strong>This will enable the first serial port with 9600/8/N/1</strong>
								<br>
								<span class="vexpl">Note:  This will disable the internal video card/keyboard</span>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td colspan="2" valign="top" class="listtopic">Shell Options</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Console menu</td>
							<td width="78%" class="vtable">
								<input name="disableconsolemenu" type="checkbox" id="disableconsolemenu" value="yes" <?php if ($pconfig['disableconsolemenu']) echo "checked"; ?>  />
								<strong>Password protect the console menu</strong>
								<br/>
								<span class="vexpl">Changes to this option will take effect after a reboot.</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%"><input name="Submit" type="submit" class="formbtn" value="Save" /></td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>
					</table>
				</form>
			</td>
		</tr>
	</table>

<?php include("fend.inc"); ?>
</body>
</html>

<?php

if($_POST['cert'] || $_POST['key']) {
	if (($config['system']['webgui']['certificate'] != $oldcert)
			|| ($config['system']['webgui']['private-key'] != $oldkey)) {
		ob_flush();
		flush();
		log_error("webConfigurator certificates have changed.  Restarting webConfigurator.");
		sleep(1);
		touch("/tmp/restart_webgui");
	}
}

?>
