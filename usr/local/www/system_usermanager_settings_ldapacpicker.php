<?php
/* $Id$ */
/*
    part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
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

require("guiconfig.inc");
require_once("auth.inc");

$ous = array();

if($_GET) {
	$authcfg = array();
	$authcfg['ldap_port'] = $_GET['port'];
	$authcfg['ldap_basedn'] = $_GET['basedn'];
	$authcfg['host'] = $_GET['host'];
	$authcfg['ldap_scope'] = $_GET['scope'];
	$authcfg['ldap_binddn'] = $_GET['binddn'];
	$authcfg['ldap_bindpw'] = $_GET['bindpw'];
	$authcfg['ldap_urltype'] = $_GET['urltype'];
	$authcfg['ldap_protver'] = $_GET['proto'];
	$authcfg['ldap_authcn'] = explode(";", $_GET['authcn']);
	$authcfg['ldap_caref'] = $_GET['cert'];
	$ous = ldap_get_user_ous(true, $authcfg);
}

?>
<html>
	<head>
            <STYLE type="text/css">
                        TABLE {
                                border-width: 1px 1px 1px 1px;
                                border-spacing: 0px;
                                border-style: solid solid solid solid;
                                border-color: gray gray gray gray;
                                border-collapse: separate;
                                background-color: collapse;
                        }
                        TD {
                                border-width: 0px 0px 0px 0px;
                                border-spacing: 0px;
                                border-style: solid solid solid solid;
                                border-color: gray gray gray gray;
                                border-collapse: collapse;
                                background-color: white;
                        }
            </STYLE>
        </head>
<script type="text/javascript">
function post_choices() {

	var ous = <?php echo count($ous); ?>;
	var i;
		opener.document.forms[0].ldapauthcontainers.value="";
	for (i = 0; i < ous; i++) {
		if (document.forms[0].ou[i].checked) {
			if (opener.document.forms[0].ldapauthcontainers.value != "")
				opener.document.forms[0].ldapauthcontainers.value+=";";
			opener.document.forms[0].ldapauthcontainers.value+=document.forms[0].ou[i].value;
		}
	}
	window.close();
-->
}
</script>

 <body link="#000000" vlink="#000000" alink="#000000" >
 <form method="post" action="system_usermanager_settings_ldapacpicker.php">	
<?php if (empty($ous)): ?>
	<p><?=gettext("Could not connect to the LDAP server. Please check your LDAP configuration.");?></p>
	<input type='button' value='<?=gettext("Close"); ?>' onClick="window.close();">
<?php else: ?>
	<b><?=gettext("Please select which containers to Authenticate against:");?></b>
	<p/>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
  	 <tr>
    	<td class="tabnavtbl">
			<table width="100%">
<?php
	if(is_array($ous)) {	
		foreach($ous as $ou) {
			if(in_array($ou, $authcfg['ldap_authcn']))
				$CHECKED=" CHECKED";
			else 
				$CHECKED="";
			echo "			<tr><td><input type='checkbox' value='{$ou}' id='ou' name='ou[]'{$CHECKED}> {$ou}<br /></td></tr>\n";
		}
	}
?>
			</table>
      	</td>
     </tr>
	</table>	

	<p/>

	<input type='button' value='<?=gettext("Save");?>' onClick="post_choices();">
<?php endif; ?>
 </form>
 </body>
</html>
