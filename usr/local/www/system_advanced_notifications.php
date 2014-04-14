<?php
/* $Id$ */
/*
	system_advanced_notifications.php
	part of pfSense
	Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>

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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-advanced-notifications
##|*NAME=System: Advanced: Notifications page
##|*DESCR=Allow access to the 'System: Advanced: Notifications' page.
##|*MATCH=system_advanced_notifications.php*
##|-PRIV

require("guiconfig.inc");
require_once("notices.inc");

// Growl
$pconfig['disable_growl'] = isset($config['notifications']['growl']['disable']);
if($config['notifications']['growl']['password']) 
	$pconfig['password'] = $config['notifications']['growl']['password'];
if($config['notifications']['growl']['ipaddress']) 
	$pconfig['ipaddress'] = $config['notifications']['growl']['ipaddress'];

if($config['notifications']['growl']['notification_name']) 
	$pconfig['notification_name'] = $config['notifications']['growl']['notification_name'];
else
  $pconfig['notification_name'] = "{$g['product_name']} growl alert";
  
if($config['notifications']['growl']['name']) 
	$pconfig['name'] = $config['notifications']['growl']['name'];
else
  $pconfig['name'] = 'PHP-Growl';


// SMTP
$pconfig['disable_smtp'] = isset($config['notifications']['smtp']['disable']);
if ($config['notifications']['smtp']['ipaddress'])
	$pconfig['smtpipaddress'] = $config['notifications']['smtp']['ipaddress'];
if ($config['notifications']['smtp']['port'])
	$pconfig['smtpport'] = $config['notifications']['smtp']['port'];
if (isset($config['notifications']['smtp']['ssl']))
	$pconfig['smtpssl'] = true;
if (isset($config['notifications']['smtp']['tls']))
	$pconfig['smtptls'] = true;
if ($config['notifications']['smtp']['notifyemailaddress'])
	$pconfig['smtpnotifyemailaddress'] = $config['notifications']['smtp']['notifyemailaddress'];
if ($config['notifications']['smtp']['username'])
	$pconfig['smtpusername'] = $config['notifications']['smtp']['username'];
if ($config['notifications']['smtp']['password'])
	$pconfig['smtppassword'] = $config['notifications']['smtp']['password'];
if ($config['notifications']['smtp']['fromaddress'])
	$pconfig['smtpfromaddress'] = $config['notifications']['smtp']['fromaddress'];

// System Sounds
$pconfig['disablebeep'] = isset($config['system']['disablebeep']);

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if ($_POST['apply']) {
		$retval = 0;
		system_setup_sysctl();		
		$savemsg = get_std_save_message($retval);
	}

	if ($_POST['Submit'] == gettext("Save")) {
		$tunableent = array();

		// Growl
		$config['notifications']['growl']['ipaddress'] = $_POST['ipaddress'];
		$config['notifications']['growl']['password'] = $_POST['password'];
		$config['notifications']['growl']['name'] = $_POST['name'];
		$config['notifications']['growl']['notification_name'] = $_POST['notification_name'];

		if($_POST['disable_growl'] == "yes")
			$config['notifications']['growl']['disable'] = true;
		else
			unset($config['notifications']['growl']['disable']);

		// SMTP
		$config['notifications']['smtp']['ipaddress'] = $_POST['smtpipaddress'];
		$config['notifications']['smtp']['port'] = $_POST['smtpport'];
		if (isset($_POST['smtpssl']))
			$config['notifications']['smtp']['ssl'] = true;
		else
			unset($config['notifications']['smtp']['ssl']);
		if (isset($_POST['smtptls']))
			$config['notifications']['smtp']['tls'] = true;
		else
			unset($config['notifications']['smtp']['tls']);
		$config['notifications']['smtp']['notifyemailaddress'] = $_POST['smtpnotifyemailaddress'];
		$config['notifications']['smtp']['username'] = $_POST['smtpusername'];
		$config['notifications']['smtp']['password'] = $_POST['smtppassword'];
		$config['notifications']['smtp']['fromaddress'] = $_POST['smtpfromaddress'];

		if($_POST['disable_smtp'] == "yes")
			$config['notifications']['smtp']['disable'] = true;
		else
			unset($config['notifications']['smtp']['disable']);

		// System Sounds
		if($_POST['disablebeep'] == "yes")
			$config['system']['disablebeep'] = true;
		else
			unset($config['system']['disablebeep']);

		write_config();
		pfSenseHeader("system_advanced_notifications.php");
		return;

	}
	if ($_POST['test_growl'] == gettext("Test Growl")) {
		// Send test message via growl
		if($config['notifications']['growl']['ipaddress'] && 
			$config['notifications']['growl']['password'] = $_POST['password']) {
			unlink_if_exists($g['vardb_path'] . "/growlnotices_lastmsg.txt");
			register_via_growl();
			notify_via_growl(sprintf(gettext("This is a test message from %s.  It is safe to ignore this message."), $g['product_name']), true);
		}
	}
	if ($_POST['test_smtp'] == gettext("Test SMTP")) {
		// Send test message via smtp
		if(file_exists("/var/db/notices_lastmsg.txt"))
			unlink("/var/db/notices_lastmsg.txt");
		$savemsg = notify_via_smtp(sprintf(gettext("This is a test message from %s.  It is safe to ignore this message."), $g['product_name']), true);
	}
}

$pgtitle = array(gettext("System"),gettext("Advanced: Notifications"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
	<form action="system_advanced_notifications.php" method="post">
		<?php
			if ($input_errors)
				print_input_errors($input_errors);
			if ($savemsg)
				print_info_box($savemsg);
		?>
	</form>
	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system advanced notifications">
		<tr>
			<td>
				<?php
					$tab_array = array();
					$tab_array[] = array(gettext("Admin Access"), false, "system_advanced_admin.php");
					$tab_array[] = array(gettext("Firewall / NAT"), false, "system_advanced_firewall.php");
					$tab_array[] = array(gettext("Networking"), false, "system_advanced_network.php");
					$tab_array[] = array(gettext("Miscellaneous"), false, "system_advanced_misc.php");
					$tab_array[] = array(gettext("System Tunables"), false, "system_advanced_sysctl.php");
					$tab_array[] = array(gettext("Notifications"), true, "system_advanced_notifications.php");
					display_top_tabs($tab_array);
				?>
			</td>
		</tr>
		<tr>
			<td id="mainarea">
				<div class="tabcont">
					<form action="system_advanced_notifications.php" method="post" name="iform">
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
						<!-- GROWL -->
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Growl"); ?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Disable Growl Notifications"); ?></td>
							<td width="78%" class="vtable">
								<input type='checkbox' name='disable_growl' value="yes" <?php if ($pconfig['disable_growl']) {?>checked="checked"<?php } ?> /><br />
								<?=gettext("Check this option to disable growl notifications but preserve the settings below."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Registration Name"); ?></td>
							<td width="78%" class="vtable">
								<input name='name' value='<?php echo $pconfig['name']; ?>' /><br />
								<?=gettext("Enter the name to register with the Growl server (default: PHP-Growl)."); ?>
							</td>
						</tr>
  					<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Notification Name"); ?></td>
							<td width="78%" class="vtable">
								<input name='notification_name' value='<?php echo $pconfig['notification_name']; ?>' /><br />
								<?=sprintf(gettext("Enter a name for the Growl notifications (default: %s growl alert)."), $g['product_name']); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("IP Address"); ?></td>
							<td width="78%" class="vtable">
								<input name='ipaddress' value='<?php echo $pconfig['ipaddress']; ?>' /><br />
								<?=gettext("This is the IP address that you would like to send growl notifications to."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Password"); ?></td>
							<td width="78%" class="vtable">
								<input name='password' type='password' value='<?php echo $pconfig['password']; ?>' /><br />
								<?=gettext("Enter the password of the remote growl notification device."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="">
								&nbsp;
							</td>
							<td>
								<input type='submit' id='test_growl' name='test_growl' value='<?=gettext("Test Growl"); ?>' />
								<br /><?= gettext("NOTE: A test notification will be sent even if the service is marked as disabled.") ?>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>	
						<!-- SMTP -->
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("SMTP E-Mail"); ?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Disable SMTP Notifications"); ?></td>
							<td width="78%" class="vtable">
								<input type='checkbox' name='disable_smtp' value="yes" <?php if ($pconfig['disable_smtp']) {?>checked="checked"<?php } ?> /><br />
								<?=gettext("Check this option to disable SMTP notifications but preserve the settings below. Some other mechanisms, such as packages, may need these settings in place to function."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("E-Mail server"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtpipaddress' value='<?php echo $pconfig['smtpipaddress']; ?>' /><br />
								<?=gettext("This is the FQDN or IP address of the SMTP E-Mail server to which notifications will be sent."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("SMTP Port of E-Mail server"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtpport' value='<?php echo $pconfig['smtpport']; ?>' /><br />
								<?=gettext("This is the port of the SMTP E-Mail server, typically 25, 587 (submission) or 465 (smtps)"); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Secure SMTP Connection"); ?></td>
							<td width="78%" class="vtable">
								<input type='checkbox' id='smtpssl' name='smtpssl' <?php if (isset($pconfig['smtpssl'])) echo "checked=\"checked\""; ?> />Enable SMTP over SSL/TLS<br />
								<input type='checkbox' id='smtptls' name='smtptls' <?php if (isset($pconfig['smtptls'])) echo "checked=\"checked\""; ?> />Enable STARTTLS<br />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("From e-mail address"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtpfromaddress' type='text' value='<?php echo $pconfig['smtpfromaddress']; ?>' /><br />
								<?=gettext("This is the e-mail address that will appear in the from field."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Notification E-Mail address"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtpnotifyemailaddress' type='text' value='<?php echo $pconfig['smtpnotifyemailaddress']; ?>' /><br />
								<?=gettext("Enter the e-mail address that you would like email notifications sent to."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Notification E-Mail auth username (optional)"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtpusername' type='text' value='<?php echo $pconfig['smtpusername']; ?>' /><br />
								<?=gettext("Enter the e-mail address username for SMTP authentication."); ?>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Notification E-Mail auth password"); ?></td>
							<td width="78%" class="vtable">
								<input name='smtppassword' type='password' value='<?php echo $pconfig['smtppassword']; ?>' /><br />
								<?=gettext("Enter the e-mail address password for SMTP authentication."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="">
								&nbsp;
							</td>
							<td>
								<input type='submit' id='test_smtp' name='test_smtp' value='<?=gettext("Test SMTP"); ?>' />
								<br /><?= gettext("NOTE: A test message will be sent even if the service is marked as disabled.") ?>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>	
						<!-- System Sounds -->
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("System Sounds"); ?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Startup/Shutdown Sound"); ?></td>
							<td width="78%" class="vtable">
								<input name="disablebeep" type="checkbox" id="disablebeep" value="yes" <?php if ($pconfig['disablebeep']) echo "checked=\"checked\""; ?>  />
								<strong><?=gettext("Disable the startup/shutdown beep"); ?></strong>
								<br />
								<span class="vexpl"><?=gettext("When this is checked, startup and shutdown sounds will no longer play."); ?></span>
							</td>
						</tr>
						<tr>
							<td colspan="2" class="list" height="12">&nbsp;</td>
						</tr>	
						<tr>
							<td valign="top" class="">
								&nbsp;
							</td>
							<td>
								<input type='submit' id='Submit' name='Submit' value='<?=gettext("Save"); ?>' />
							</td>
						</tr>
					</table>
					</form>
				</div>
			</td>
		</tr>
	</table>
<script type="text/javascript">
	jQuery(document).ready(function() {
		if (jQuery('#smtpssl').is(':checked')) {
			jQuery('#smtptls').prop('disabled', true);
		} else if  (jQuery('#smtptls').is(':checked')) {
			jQuery('#smtpssl').prop('disabled', true);
		}
	});
	jQuery('#smtpssl').change( function() {
		jQuery('#smtptls').prop('disabled', this.checked);
	});
	jQuery('#smtptls').change( function() {
		jQuery('#smtpssl').prop('disabled', this.checked);
	});
</script>
<?php include("fend.inc"); ?>
</body>
</html>
