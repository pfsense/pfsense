<?php
/* $Id$ */
/*
    part of pfSense (http://www.pfsense.org/)

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
##|+PRIV
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User manager: settings page
##|*DESCR=Allow access to the 'System: User manager: settings' page.
##|*MATCH=system_usermanager_settings.php*
##|-PRIV



if($_POST['savetest'])
	$save_and_test = true;

require("guiconfig.inc");

$pconfig['session_timeout'] = &$config['system']['webgui']['session_timeout'];
$pconfig['ldapserver'] = &$config['system']['webgui']['ldapserver'];
$pconfig['backend'] = &$config['system']['webgui']['backend'];
$pconfig['ldapbindun'] = &$config['system']['webgui']['ldapbindun'];
$pconfig['ldapbindpw'] = &$config['system']['webgui']['ldapbindpw'];
$pconfig['ldapfilter'] = &$config['system']['webgui']['ldapfilter'];
$pconfig['ldapsearchbase'] = &$config['system']['webgui']['ldapsearchbase'];
$pconfig['ldapauthcontainers'] = &$config['system']['webgui']['ldapauthcontainers'];
$pconfig['ldapgroupattribute'] = &$config['system']['webgui']['ldapgroupattribute'];
$pconfig['ldapnameattribute'] = &$config['system']['webgui']['ldapnameattribute'];

// Page title for main admin
$pgtitle = array("System","User manager settings");

if ($_POST) {
	unset($input_errors);

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if($_POST['session_timeout']) {
		$timeout = intval($_POST['session_timeout']);
		if ($timeout != "" && !is_numeric($timeout))
			$input_errors[] = gettext("Session timeout must be an integer with value 1 or greater.");

		if ($timeout < 1)
			$input_errors[] = gettext("Session timeout must be an integer with value 1 or greater.");

		if ($timeout > 999)
			$input_errors[] = gettext("Session timeout must be an integer with value 1 or greater.");
	}

	if (!$input_errors) {

		if($_POST['session_timeout'] && $_POST['session_timeout'] != "0")
			$pconfig['session_timeout'] = intval($_POST['session_timeout']);
		else
			unset($config['system']['webgui']['session_timeout']);

		if($_POST['ldapserver'])
			$pconfig['ldapserver'] = $_POST['ldapserver'];
		else
			unset($pconfig['ldapserver']);

		if($_POST['backend'])
			$pconfig['backend'] = $_POST['backend'];
		else
			unset($pconfig['backend']);

		if($_POST['ldapbindun'])
			$pconfig['ldapbindun'] = $_POST['ldapbindun'];
		else
			unset($pconfig['ldapbindun']);

		if($_POST['ldapbindpw'])
			$pconfig['ldapbindpw'] = $_POST['ldapbindpw'];
		else
			unset($pconfig['ldapbindpw']);

		if($_POST['ldapfilter'])
			$pconfig['ldapfilter'] = $_POST['ldapfilter'];
		else
			unset($pconfig['ldapfilter']);

		if($_POST['ldapsearchbase'])
			$pconfig['ldapsearchbase'] = $_POST['ldapsearchbase'];
		else
			unset($pconfig['ldapsearchbase']);

		if($_POST['ldapauthcontainers'])
			$pconfig['ldapauthcontainers'] = $_POST['ldapauthcontainers'];
		else
			unset($pconfig['ldapauthcontainers']);

		if($_POST['ldapgroupattribute'])
			$pconfig['ldapgroupattribute'] = $_POST['ldapgroupattribute'];
		else
			unset($pconfig['ldapgroupattribute']);
		if($_POST['ldapnameattribute'])
			$pconfig['ldapnameattribute'] = $_POST['ldapnameattribute'];
		else
			unset($pconfig['ldapgroupattribute']);


		write_config();

		$retval = system_password_configure();
		sync_webgui_passwords();

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
		echo "<script language='javascript'>\n";
		echo "myRef = window.open('system_usermanager_settings_test.php','mywin', ";
		echo "'left=20,top=20,width=700,height=550,toolbar=1,resizable=0');\n";
		echo "</script>\n";
	}
?>

<script language="javascript">
	function show_ldapfilter() {
		document.getElementById("filteradv").innerHTML='';
		aodiv = document.getElementById('filteradvdiv');
		aodiv.style.display = "block";		
	}
	function show_ldapnaming(){
		document.getElementById("namingattribute").innerHTML='';
		aodiv = document.getElementById('ldapnamingdiv');
		aodiv.style.display = "block";		
	}
	function show_groupmembership() {
		document.getElementById("groupmembership").innerHTML='';
		aodiv = document.getElementById('groupmembershipdiv');
		aodiv.style.display = "block";		
	}
	function ldap_typechange() {
        switch (document.iform.backend.selectedIndex) {
            case 0:
            	/* pfSense backend, disable all options */
                document.iform.ldapfilter.disabled = 1;
                document.iform.ldapnameattribute.disabled = 1;
                document.iform.ldapgroupattribute.disabled = 1;
                document.iform.ldapsearchbase.disabled = 1;
                document.iform.ldapauthcontainers.disabled = 1;
				document.iform.ldapserver.disabled = 1;
				document.iform.ldapbindun.disabled = 1;
				document.iform.ldapbindpw.disabled = 1;
				document.iform.ldapfilter.value = "";
				document.iform.ldapnameattribute.value = "";	
				document.iform.ldapgroupattribute.value = "";
				document.iform.ldapauthcontainers.value = "";
				break;
            case 1:
            	/* A/D */
                document.iform.ldapfilter.disabled = 0;
                document.iform.ldapnameattribute.disabled = 0;
                document.iform.ldapgroupattribute.disabled = 0;
                document.iform.ldapsearchbase.disabled = 0;
                document.iform.ldapauthcontainers.disabled = 0;
				document.iform.ldapserver.disabled = 0;
				document.iform.ldapbindun.disabled = 0;
				document.iform.ldapbindpw.disabled = 0;
				document.iform.ldapfilter.value = "(samaccountname=$username)";
				document.iform.ldapnameattribute.value = "samaccountname";	
				document.iform.ldapgroupattribute.value = "memberOf";
				break;							
            case 2:
            	/* eDir */
                document.iform.ldapfilter.disabled = 0;
                document.iform.ldapnameattribute.disabled = 0;
                document.iform.ldapgroupattribute.disabled = 0;
                document.iform.ldapsearchbase.disabled = 0;
                document.iform.ldapauthcontainers.disabled = 0;
				document.iform.ldapserver.disabled = 0;
				document.iform.ldapbindun.disabled = 0;
				document.iform.ldapbindpw.disabled = 0;
				document.iform.ldapfilter.value = "(cn=$username)";		
				document.iform.ldapnameattribute.value = "CN";
				document.iform.ldapgroupattribute.value = "groupMembership";
				break;				
		}
	}
</script>

  <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td class="tabnavtbl">
<?php
    $tab_array = array();
    $tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
    $tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
    $tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
    display_top_tabs($tab_array);

/* Default to pfsense backend type if none is defined */
if(!$pconfig['backend'])
	$pconfig['backend'] = "pfsense";

?>
      </td>
    <tr>
       <td>
            <div id="mainarea">
            <form id="iform" name="iform" action="system_usermanager_settings.php" method="post">
              <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
					<tr>
                        <td width="22%" valign="top" class="vncell">Session Timeout</td>
                        <td width="78%" class="vtable">
							<input name="session_timeout" id="session_timeout" type="text" size="8" value="<?=htmlspecialchars($pconfig['session_timeout']);?>" />
                          <br />
                          <?=gettext("Time in minutes to expire idle management sessions.");?><br />
						</td>
                      </tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">Authentication primary backend</td>
                        <td width="78%" class="vtable">
							<select name='backend' id='backend' onchange='ldap_typechange()'>
								<option value="pfsense"<?php if ($pconfig['backend'] == "pfsense") echo " SELECTED";?>>pfSense</option>
								<option value="ldap"<?php if ($pconfig['backend'] == "ldap") echo " SELECTED";?>>LDAP (Active Directory)</option>
								<option value="ldapother"<?php if ($pconfig['backend'] == "ldapother") echo " SELECTED";?>>LDAP OTHER (eDir, etc)</option>
							</select>
							<br/>NOTE: login failures or server not available issues will fall back to pfSense internal users/group authentication.
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Server:port</td>
                        <td width="78%" class="vtable">
							<input name="ldapserver" id="ldapserver" size="65" value="<?=htmlspecialchars($pconfig['ldapserver']);?>">
							<br/>Example: ldaps://ldap.example.org:389 or ldap://ldap.example.org:389
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Binding username</td>
                        <td width="78%" class="vtable">
							<input name="ldapbindun" id="ldapbindun" size="65" value="<?=htmlspecialchars($pconfig['ldapbindun']);?>">
							<br/>This account must have read access to the user objects and be able to retrieve groups.
							<br/>Example: For Active Directory you would want to use format DOMAIN\username or username@domain.
							<br/>Example: eDirectory you would want to use format cn=username,ou=orgunit,o=org.
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Binding password</td>
                        <td width="78%" class="vtable">
							<input name="ldapbindpw" id="ldapbindpw" type="password" size="65" value="<?=htmlspecialchars($pconfig['ldapbindpw']);?>">
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Filter</td>
                        <td width="78%" class="vtable">
							<div id="filteradv" name="filteradv">
								<input type="button" onClick="show_ldapfilter();" value="Advanced"> - Show advanced options
							</div>
							<div id="filteradvdiv" name="filteradvdiv" style="display:none">	
								<input name="ldapfilter" id="ldapfilter" size="65" value="<?=htmlspecialchars($pconfig['ldapfilter']);?>">
								<br/>Example: For Active Directory you would want to use (samaccountname=$username)
								<br/>Example: For eDirectory you would want to use (cn=$username)
							</div>
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Naming Attribute</td>
                        <td width="78%" class="vtable">
							<div id="namingattribute" name="namingattribute">
								<input type="button" onClick="show_ldapnaming();" value="Advanced"> - Show advanced options
							</div>
							<div id="ldapnamingdiv" name="ldapnamingdiv" style="display:none">	
								<input name="ldapnameattribute" id="ldapnameattribute" size="65" value="<?=htmlspecialchars($pconfig['ldapnameattribute']);?>">
								<br/>Example: For Active Directory you would want to use samaccountname.
								<br/>Example: For eDirectory you would want to use CN.
							</div>
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">Group Membership Attribute Name</td>
                        <td width="78%" class="vtable">
							<div id="groupmembership" name="groupmembership">
								<input type="button" onClick="show_groupmembership();" value="Advanced"> - Show advanced options
							</div>
							<div id="groupmembershipdiv" name="groupmembershipdiv" style="display:none">
								<input name="ldapgroupattribute" id="ldapgroupattribute" size="65" value="<?=htmlspecialchars($pconfig['ldapgroupattribute']);?>">
								<br/>Example: For Active Directory you would want to use memberOf.
								<br/>Example: For eDirectory you would want to use groupMembership.
							</div>
						</td>
					</tr>

					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Search base</td>
                        <td width="78%" class="vtable">
							<input name="ldapsearchbase" size="65" value="<?=htmlspecialchars($pconfig['ldapsearchbase']);?>">
							<br/>Example: DC=pfsense,DC=com
						</td>
					</tr>
					<tr>
                        <td width="22%" valign="top" class="vncell">LDAP Authentication container</td>
                        <td width="78%" class="vtable">
							<input name="ldapauthcontainers" id="ldapauthcontainers" size="65" value="<?=htmlspecialchars($pconfig['ldapauthcontainers']);?>">
							<input type="button" onClick="javascript:if(openwindow('system_usermanager_settings_ldapacpicker.php') == false) alert('Popup blocker detected.  Action aborted.');" value="Select"> 
							<br/>NOTE: Semi-Colon separated.
							<br/>EXAMPLE: CN=Users,DC=pfsense,DC=com;CN=OtherUsers,DC=pfsense,DC=com
						</td>
					</tr>
                	<tr>
                  		<td width="22%" valign="top">&nbsp;</td>
                  		<td width="78%">
							<input id="submit" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>" />
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
<script language="javascript">
	function openwindow(url) {
	        var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
	        if (oWin==null || typeof(oWin)=="undefined") {
	                return false;
	        } else {
	                return true;
	        }
	}
</script>

