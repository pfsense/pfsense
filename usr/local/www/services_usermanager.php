#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	services_usermanager.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.
	Copyright (C) 2005 Pascal Suter <d-pfsense-dev@psuter.ch>.
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
require("guiconfig.inc");
if(isset($_POST['save'])){
	$_POST['username']=trim($_POST['username']);
	if($_POST['old_username']!="" && $_POST['old_username']!=$_POST['username']){
		$config['users'][$_POST['username']]=$config['users'][$_POST['old_username']];
		unset($config['users'][$_POST['old_username']]);
	}
	foreach(Array('fullname','expirationdate') as $field){
		$config['users'][$_POST['username']][$field]=trim($_POST[$field]);
	}
	if(trim($_POST['password1'])!="********" && trim($_POST['password1'])!=""){
		if(trim($_POST['password1'])==trim($_POST['password2'])){
			$config['users'][$_POST['username']]['password']=md5(trim($_POST['password1']));
		} else {
			$input_errors[]="passwords did not match --> password was not changed!";
		}
	}
	if($_POST['username']=="" || trim($_POST['password1'])==""){
		$input_errors[] = "Username and password must not be empty!";
		$_GET['act']="new";
	} else {
		write_config();
		$savemsg=$_POST['username']." successfully saved<br>";
	}
} else if ($_GET['act']=="delete" && isset($_GET['username'])){
	unset($config['users'][$_GET['username']]);
	write_config();
	$savemsg=$_GET['username']." successfully deleted<br>";
}
//erase expired accounts
$changed=false;
if(is_array($config['users'])){
	foreach($config['users'] as $username => $user){
		if(trim($user['expirationdate'])!="" && strtotime("-1 day")>strtotime($user['expirationdate'])){
			unset($config['users'][$username]);
			$changed=true;
			$savemsg.="$username has expired --> $username was deleted<br>";
		}
	}
	if($changed){
		write_config();
	}
}

$pgtitle = "Services: User Manager";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<script language="javascript" type="text/javascript" src="datetimepicker.js">
//Date Time Picker script- by TengYong Ng of http://www.rainforestnet.com
//Script featured on JavaScript Kit (http://www.javascriptkit.com)
//For this script, visit http://www.javascriptkit.com
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <ul id="tabnav">
	<li class="tabinact1"><a href="services_captiveportal.php">Captive portal</a></li>
	<li class="tabinact"><a href="services_captiveportal_mac.php">Pass-through MAC</a></li>
	<li class="tabinact"><a href="services_captiveportal_ip.php">Allowed IP addresses</a></li>
	<li class="tabact">User Manager</li>
  </ul>
  </td></tr>
  <tr>
  <td class="tabcont">
<?php
if($_GET['act']=="new" || $_GET['act']=="edit"){
	if($_GET['act']=="edit" && isset($_GET['username'])){
		$user=$config['users'][$_GET['username']];
	}
?>
	<form action="services_usermanager.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Username</td>
                  <td width="78%" class="vtable">
                    <input name="username" type="text" class="formfld" id="username" size="20" value="<? echo $_GET['username']; ?>">
                    <br>
                    <span class="vexpl">Enter the desired username.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Password</td>
                  <td width="78%" class="vtable">
                    <input name="password1" type="password" class="formfld" id="password1" size="20" value="<?php echo ($_GET['act']=='edit' ? "********" : "" ); ?>">
                    <br>
                    <span class="vexpl">Enter the desired password.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Password confirmation</td>
                  <td width="78%" class="vtable">
                    <input name="password2" type="password" class="formfld" id="password2" size="20" value="<?php echo ($_GET['act']=='edit' ? "********" : "" ); ?>">
                    <br>
                    <span class="vexpl">Confirm the above password.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Full name</td>
                  <td width="78%" class="vtable">
                    <input name="fullname" type="text" class="formfld" id="fullname" size="20" value="<? echo $user['fullname']; ?>">
                    <br>
                    Enter the user's full name.</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Expiration Date</td>
                  <td width="78%" class="vtable">
                    <input name="expirationdate" type="text" class="formfld" id="expirationdate" size="10" value="<? echo $user['expirationdate']; ?>">
                    <a href="javascript:NewCal('expirationdate','mmddyyyy')"><img src="cal.gif" width="16" height="16" border="0" alt="Pick a date"></a>
                    <br> <span class="vexpl">Enter this acocunt's expiration date in us-format (mm/dd/yyyy) or leave this field empty for no expiration.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input name="save" type="submit" class="formbtn" value="Save">
                    <input name="old_username" type="hidden" value="<? echo $_GET['username'];?>">
                  </td>
                </tr>
              </table>
     </form>
<?php
} else {
	echo <<<END
     <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="35%" class="listhdrr">Username</td>
                  <td width="20%" class="listhdrr">Full Name</td>
                  <td width="35%" class="listhdr">Expires</td>
                  <td width="10%" class="list"></td>
		</tr>
END;
	if(is_array($config['users'])){
		foreach($config['users'] as $username => $user){
?>
		<tr>
                  <td class="listlr">
                    <?php echo $username; ?>&nbsp;
                  </td>
                  <td class="listr">
                    <?php echo $user['fullname']; ?>&nbsp;
                  </td>
                  <td class="listbg">
                    <?php echo $user['expirationdate']; ?>&nbsp;
                  </td>
                  <td valign="middle" nowrap class="list"> <a href="services_usermanager.php?act=edit&username=<?php echo $username; ?>"><img src="e.gif" width="17" height="17" border="0"></a>
                     &nbsp;<a href="services_usermanager.php?act=delete&username=<?php echo $username; ?>" onclick="return confirm('Do you really want to delete this user?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
		</tr>
<?php
		}
	}
	echo <<<END
		<tr>
                  <td class="list" colspan="3"></td>
                  <td class="list"> <a href="services_usermanager.php?act=new"><img src="plus.gif" width="17" height="17" border="0"></a></td>
	        </tr>
     </table>
END;
}
?>

  </td>
  </tr>
  </table>
<?php include("fend.inc"); ?>
