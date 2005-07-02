#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	services_captiveportal_users.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
$pgtitle = array("Services", "Captive portal");
require("guiconfig.inc");
if(isset($_POST['save'])){
	//value-checking
	if(trim($_POST['password1'])!="********" &&
	   trim($_POST['password1'])!="" &&
	   trim($_POST['password1'])!=trim($_POST['password2'])){
	   	//passwords are to be changed but don't match
	   	$input_errors[]="passwords don't match";
	}
	if((trim($_POST['password1'])=="" || trim($_POST['password1'])=="********") &&
	   (trim($_POST['password2'])=="" || trim($_POST['password2'])=="********")){
	   	//assume password should be left as is if a password is set already.
		if(!empty($config['users'][$_POST['old_username']]['password'])){
			$_POST['password1']="********";
			$_POST['password2']="********";
		} else {
			$input_errors[]="password must not be empty";
		}
	} else {
		if(trim($_POST['password1'])!=trim($_POST['password2'])){
		   	//passwords are to be changed or set but don't match
		   	$input_errors[]="passwords don't match";
		} else {
			//check password for invalid characters
			if(!preg_match('/^[a-zA-Z0-9_\-\.@\~\(\)\&\*\+§?!\$£°\%;:]*$/',$_POST['username'])){
				$input_errors[] = "password contains illegal characters, only  letters from A-Z and a-z, _, -, .,@,~,(,),&,*,+,§,?,!,$,£,°,%,;,: and numbers are allowed";
				//test pw: AZaz_-.@~()&*+§?!$£°%;:
			}
		}
	}
	if($_POST['username']==""){
		$input_errors[] = "username must not be empty!";
	}
	//check for a valid expirationdate if one is set at all (valid means, strtotime() puts out a time stamp
	//so any strtotime compatible time format may be used. to keep it simple for the enduser, we only claim
	//to accept MM/DD/YYYY as inputs. advanced users may use inputs like "+1 day", which will be converted to
	//MM/DD/YYYY based on "now" since otherwhise such an entry would lead to a never expiring expirationdate
	if(trim($_POST['expirationdate'])!=""){
		if(strtotime($_POST['expirationdate'])>0){
			if(strtotime("-1 day")>strtotime(date("m/d/Y",strtotime($_POST['expirationdate'])))){
				$input_errors[] = "selected expiration date lies in the past";
			} else {
				//convert from any strtotime compatible date to MM/DD/YYYY
				$expdate = strtotime($_POST['expirationdate']);
				$_POST['expirationdate'] = date("m/d/Y",$expdate);
			}
		} else {
			$input_errors[] = "invalid expiration date format, use MM/DD/YYYY instead";
		}
	}
	//check username: only allow letters from A-Z and a-z, _, -, . and numbers from 0-9 (note: username can
	//not contain characters which are not allowed in an xml-token. i.e. if you'd use @ in a username, config.xml
	//could not be parsed anymore!
	if(!preg_match('/^[a-zA-Z0-9_\-\.]*$/',$_POST['username'])){
		$input_errors[] = "username contains illegal characters, only  letters from A-Z and a-z, _, -, . and numbers are allowed";
	}

	if(!empty($input_errors)){
		//there are illegal inputs --> print out error message and show formular again (and fill in all recently entered values
		//except passwords
		$_GET['act']="new";
		$_POST['old_username']=($_POST['old_username'] ? $_POST['old_username'] : $_POST['username']);
		$_GET['username']=$_POST['old_username'];
		foreach(Array("username","fullname","expirationdate") as $field){
			$config['users'][$_POST['old_username']][$field]=$_POST[$field];
		}
	} else {
		//all values are okay --> saving changes
		$_POST['username']=trim($_POST['username']);
		if($_POST['old_username']!="" && $_POST['old_username']!=$_POST['username']){
			//change the username (which is used as array-index)
			$config['users'][$_POST['username']]=$config['users'][$_POST['old_username']];
			unset($config['users'][$_POST['old_username']]);
		}
		foreach(Array('fullname','expirationdate') as $field){
			$config['users'][$_POST['username']][$field]=trim($_POST[$field]);
		}
		if(trim($_POST['password1'])!="********" && trim($_POST['password1'])!=""){
			$config['users'][$_POST['username']]['password']=md5(trim($_POST['password1']));
		}
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
		if(trim($user['expirationdate'])!="" && strtotime("-1 day")>strtotime($user['expirationdate']) && empty($input_errors)){
			unset($config['users'][$username]);
			$changed=true;
			$savemsg.="$username has expired --> $username was deleted<br>";
		}
	}
	if($changed){
		write_config();
	}
}


$pgtitle = "Services: Captive Portal: Users";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="javascript" type="text/javascript" src="datetimepicker.js">
//Date Time Picker script- by TengYong Ng of http://www.rainforestnet.com
//Script featured on JavaScript Kit (http://www.javascriptkit.com)
//For this script, visit http://www.javascriptkit.com
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<div id="mainarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Captive portal", false, "services_captiveportal.php");
	$tab_array[1] = array("Pass-through MAC", false, "services_captiveportal_mac.php");
	$tab_array[2] = array("Allowed IP addresses", false, "services_captiveportal_ip.php");
	$tab_array[3] = array("Users", true, "services_captiveportal_users.php");
	display_top_tabs($tab_array);
?>  
  </td></tr>
  <tr>
  <td class="tabcont">
<?php
if($_GET['act']=="new" || $_GET['act']=="edit"){
	if($_GET['act']=="edit" && isset($_GET['username'])){
		$user=$config['users'][$_GET['username']];
	}
?>
	<form action="services_captiveportal_users.php" method="post" name="iform" id="iform">

              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Username</td>
                  <td width="78%" class="vtable">
                    <input name="username" type="text" class="formfld" id="username" size="20" value="<? echo $_GET['username']; ?>">
                    <br>
                    <span class="vexpl">Username to be used</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Password</td>
                  <td width="78%" class="vtable">
                    <input name="password1" type="password" class="formfld" id="password1" size="20" value="<?php echo ($_GET['act']=='edit' ? "********" : "" ); ?>">
                    <br>
                    <span class="vexpl">Password for the user</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">confirm Password</td>
                  <td width="78%" class="vtable">
                    <input name="password2" type="password" class="formfld" id="password2" size="20" value="<?php echo ($_GET['act']=='edit' ? "********" : "" ); ?>">
                    <br>
                    <span class="vexpl">Confirm the above Password</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Full Name</td>
                  <td width="78%" class="vtable">
                    <input name="fullname" type="text" class="formfld" id="fullname" size="20" value="<? echo $user['fullname']; ?>">
                    <br>
                    Full Name of current user, for your own information only</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Expiration Date</td>
                  <td width="78%" class="vtable">
                    <input name="expirationdate" type="text" class="formfld" id="expirationdate" size="10" value="<? echo $user['expirationdate']; ?>">
                    <a href="javascript:NewCal('expirationdate','mmddyyyy')"><img src="cal.gif" width="16" height="16" border="0" alt="Pick a date"></a>
                    <br> <span class="vexpl">enter nothing if account doesnt expire, otherwhise enter the expiration date in us-format: mm/dd/yyyy</span></td>
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
                  <td class="listlr" ondblclick="document.location='services_captiveportal_users_edit.php?act=edit&username=<?=$username;?>';">
                    <?php echo $username; ?>&nbsp;
                  </td>
                  <td class="listr" ondblclick="document.location='services_captiveportal_users_edit.php?act=edit&username=<?=$username;?>';">
                    <?php echo $user['fullname']; ?>&nbsp;
                  </td>
                  <td class="listbg" ondblclick="document.location='services_captiveportal_users_edit.php?act=edit&username=<?=$username;?>';">
                    <font color="white"><?php echo $user['expirationdate']; ?>&nbsp;</font>
                  </td>
                  <td valign="middle" nowrap class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_captiveportal_users.php?act=edit&username=<?php echo $username; ?>"><img src="e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="services_captiveportal_users.php?act=delete&username=<?php echo $username; ?>" onclick="return confirm('Do you really want to delete this User?')"><img src="x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
		</tr>
<?php
		}
	}
	echo <<<END
		<tr>
                  <td class="list" colspan="3"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_captiveportal_users.php?act=new"><img src="plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
	        </tr>
     </table>
END;
}
?>

  </td>
  </tr>
  </table>
</div>
<?php include("fend.inc"); ?>

<script type="text/javascript">
NiftyCheck();
Rounded("div#mainarea","bl br","#FFF","#eeeeee","smooth");
</script>

</body>
</html>
