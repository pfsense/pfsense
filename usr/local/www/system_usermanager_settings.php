<?php
/* $Id$ */
/*
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
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
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User Manager: settings page
##|*DESCR=Allow access to the 'System: User Manager: settings' page.
##|*MATCH=system_usermanager_settings.php*
##|-PRIV

require("guiconfig.inc");

$pconfig['session_timeout'] = &$config['system']['webgui']['session_timeout'];
$pconfig['authmode'] = &$config['system']['webgui']['authmode'];
$pconfig['backend'] = &$config['system']['webgui']['backend'];

// Page title for main admin
$pgtitle = array(gettext("System"),gettext("User manager settings"));

$save_and_test = false;
if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if(isset($_POST['session_timeout'])) {
		$timeout = intval($_POST['session_timeout']);
		if ($timeout != "" && (!is_numeric($timeout) || $timeout <= 0))
			$input_errors[] = gettext("Session timeout must be an integer value.");
	}

	if (!$input_errors) {
		if ($_POST['authmode'] != "local") {
			$authsrv = auth_get_authserver($_POST['authmode']);
			if ($_POST['savetest'])
				if ($authsrv['type'] == "ldap")
					$save_and_test = true;
				else
					$savemsg = gettext("The test was not performed because it is supported only for ldap based backends.");
		}


		if(isset($_POST['session_timeout']) && $_POST['session_timeout'] != "")
			$config['system']['webgui']['session_timeout'] = intval($_POST['session_timeout']);
		else
			unset($config['system']['webgui']['session_timeout']);

		if($_POST['authmode'])
			$config['system']['webgui']['authmode'] = $_POST['authmode'];
		else
			unset($config['system']['webgui']['authmode']);

		write_config();

	}
}

include("head.inc");
?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc");?>
<?php if ($input_errors) print_input_errors($input_errors);?>
<?php if ($savemsg) print_info_box($savemsg);?>

<?php
	if($save_and_test) {
		echo "<script type=\"text/javascript\">\n";
		echo "//<![CDATA[\n";
		echo "myRef = window.open('system_usermanager_settings_test.php?authserver={$pconfig['authmode']}','mywin', ";
		echo "'left=20,top=20,width=700,height=550,toolbar=1,resizable=0');\n";
		echo "if (myRef==null || typeof(myRef)=='undefined') alert('" . gettext("Popup blocker detected.  Action aborted.") ."');\n";
		echo "//]]>\n";
		echo "</script>\n";
	}
?>

	<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="user manager settings">
		<tr>
			<td class="tabnavtbl">
<?php
				$tab_array = array();
				$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
				$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
				$tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
				$tab_array[] = array(gettext("Servers"), false, "system_authservers.php");
				display_top_tabs($tab_array);

				/* Default to pfsense backend type if none is defined */
				if(!$pconfig['backend'])
					$pconfig['backend'] = "pfsense";
?>
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea">
					<form id="iform" name="iform" action="system_usermanager_settings.php" method="post">
						<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6" summary="main area">
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Session Timeout"); ?></td>
								<td width="78%" class="vtable">
									<input name="session_timeout" id="session_timeout" type="text" size="8" value="<?=htmlspecialchars($pconfig['session_timeout']);?>" />
									<br />
									<?=gettext("Time in minutes to expire idle management sessions. The default is 4 hours (240 minutes).");?><br />
									<?=gettext("Enter 0 to never expire sessions. NOTE: This is a security risk!");?><br />
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncell"><?=gettext("Authentication Server"); ?></td>
								<td width="78%" class="vtable">
									<select name='authmode' id='authmode' class="formselect" >
<?php
									$auth_servers = auth_get_authserver_list();
									foreach ($auth_servers as $auth_server):
										$selected = "";
										if ($auth_server['name'] == $pconfig['authmode'])
											$selected = "selected=\"selected\"";
										if (!isset($pconfig['authmode']) && $auth_server['name'] == "Local Database")
											$selected = "selected=\"selected\"";
?>
										<option value="<?=$auth_server['name'];?>" <?=$selected;?>><?=$auth_server['name'];?></option>
<?php
									endforeach;
?>
									</select>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
									<input id="savetest" name="savetest" type="submit" class="formbtn" value="<?=gettext("Save and Test");?>" />
								</td>
							</tr>
						</table>
					</form>
				</div>
			</td>
		</tr>
	</table>
<?php include("fend.inc");?>
</body>
</html>
