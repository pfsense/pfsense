#!/usr/local/bin/php
<?php 
/*
	services_captiveportal_users_edit.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	All rights reserved.
	Copyright (C) 2005 Pascal Suter <d-monodev@psuter.ch>.
	All rights reserved. 
	(files was created by Pascal based on the source code of services_captiveportal.php from Manuel)
	
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
$pgtitle = array("Services", "Captive portal", "Edit user");
require("guiconfig.inc");

if (!is_array($config['captiveportal']['user'])) {
	$config['captiveportal']['user'] = array();
}
captiveportal_users_sort();
$a_user = &$config['captiveportal']['user'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_user[$id]) {
	$pconfig['username'] = $a_user[$id]['name'];
	$pconfig['fullname'] = $a_user[$id]['fullname'];
	$pconfig['expirationdate'] = $a_user[$id]['expirationdate'];
}

if ($_POST) {
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if (isset($id) && ($a_user[$id])) {
		$reqdfields = explode(" ", "username");
		$reqdfieldsn = explode(",", "Username");
	} else {
		$reqdfields = explode(" ", "username password");
		$reqdfieldsn = explode(",", "Username,Password");
	}
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (preg_match("/[^a-zA-Z0-9\.\-_]/", $_POST['username']))
		$input_errors[] = "The username contains invalid characters.";
		
	if (($_POST['password']) && ($_POST['password'] != $_POST['password2']))
		$input_errors[] = "The passwords do not match.";

	//check for a valid expirationdate if one is set at all (valid means, strtotime() puts out a time stamp
	//so any strtotime compatible time format may be used. to keep it simple for the enduser, we only claim 
	//to accept MM/DD/YYYY as inputs. advanced users may use inputs like "+1 day", which will be converted to 
	//MM/DD/YYYY based on "now" since otherwhise such an entry would lead to a never expiring expirationdate
	if ($_POST['expirationdate']){
		if(strtotime($_POST['expirationdate']) > 0){
			if (strtotime("-1 day") > strtotime(date("m/d/Y",strtotime($_POST['expirationdate'])))){
				$input_errors[] = "The expiration date lies in the past.";			
			} else {
				//convert from any strtotime compatible date to MM/DD/YYYY
				$expdate = strtotime($_POST['expirationdate']);
				$_POST['expirationdate'] = date("m/d/Y",$expdate);
			}
		} else {
			$input_errors[] = "Invalid expiration date format; use MM/DD/YYYY instead.";
		}
	}
	
	if (!$input_errors && !(isset($id) && $a_user[$id])) {
		/* make sure there are no dupes */
		foreach ($a_user as $userent) {
			if ($userent['name'] == $_POST['username']) {
				$input_errors[] = "Another entry with the same username already exists.";
				break;
			}
		}
	}
	
	if (!$input_errors) {
	
		if (isset($id) && $a_user[$id])
			$userent = $a_user[$id];
		
		$userent['name'] = $_POST['username'];
		$userent['fullname'] = $_POST['fullname'];
		$userent['expirationdate'] = $_POST['expirationdate'];
		
		if ($_POST['password'])
			$userent['password'] = md5($_POST['password']);
		
		if (isset($id) && $a_user[$id])
			$a_user[$id] = $userent;
		else
			$a_user[] = $userent;
		
		write_config();
		
		header("Location: services_captiveportal_users.php");
		exit;
	}
}

?>
<?php include("fbegin.inc"); ?>
<script language="javascript" type="text/javascript" src="datetimepicker.js">
<!--
//Date Time Picker script- by TengYong Ng of http://www.rainforestnet.com
//Script featured on JavaScript Kit (http://www.javascriptkit.com)
//For this script, visit http://www.javascriptkit.com
// -->
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<form action="services_captiveportal_users_edit.php" method="post" name="iform" id="iform">
  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr> 
	  <td width="22%" valign="top" class="vncellreq">Username</td>
	  <td width="78%" class="vtable"> 
		<?=$mandfldhtml;?><input name="username" type="text" class="formfld" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>"> 
		</td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncellreq">Password</td>
	  <td width="78%" class="vtable"> 
		<?=$mandfldhtml;?><input name="password" type="password" class="formfld" id="password" size="20"><br>
		<?=$mandfldhtml;?><input name="password2" type="password" class="formfld" id="password2" size="20">
		&nbsp;(confirmation)<?php if (isset($id) && $a_user[$id]): ?><br>
        <span class="vexpl">If you want to change the users' password, 
        enter it here twice.</span><?php endif; ?>
		</td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncell">Full name</td>
	  <td width="78%" class="vtable"> 
		<input name="fullname" type="text" class="formfld" id="fullname" size="20" value="<?=htmlspecialchars($pconfig['fullname']);?>">
		<br>
		<span class="vexpl">User's full name, for your own information only</span></td>
	</tr>
	<tr> 
	  <td width="22%" valign="top" class="vncell">Expiration date</td>
	  <td width="78%" class="vtable"> 
		<input name="expirationdate" type="text" class="formfld" id="expirationdate" size="10" value="<?=$pconfig['expirationdate'];?>">
		<a href="javascript:NewCal('expirationdate','mmddyyyy')"><img src="cal.gif" width="16" height="16" border="0" alt="Pick a date"></a> 
		<br> 
		<span class="vexpl">Leave blank if the account shouldn't expire, otherwise enter the expiration date in the following format: mm/dd/yyyy</span></td>
	</tr>
	<tr> 
	  <td width="22%" valign="top">&nbsp;</td>
	  <td width="78%"> 
		<input name="Submit" type="submit" class="formbtn" value="Save"> 
		<?php if (isset($id) && $a_user[$id]): ?>
		<input name="id" type="hidden" value="<?=$id;?>">
		<?php endif; ?>
	  </td>
	</tr>
  </table>
 </form>
<?php include("fend.inc"); ?>
