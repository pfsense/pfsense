<?php
/* $Id$ */
/*
    part of pfSense (http://www.pfsense.org/)

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
require("priv.defs.inc");
require("priv.inc");

if($_POST) {
		print_r($_POST);
		$ous = ldap_get_user_ous(true);
		$values = "";
		$isfirst = true;
		foreach($ous as $ou) {		
			if(in_array($ou, $_POST['ou'])) {
				if($isfirst == false) 
					$values .= ";";
				$isfirst = false;
				$values .= $ou;
			}	
		}
		echo "<script language=\"JavaScript\">\n";
		echo "<!--\n";
		echo "	opener.document.forms[0].ldapauthcontainers.value='$values'\n";
		echo "	this.close();\n";
		echo "-->\n";
		echo "</script>\n";
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
 <body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
 <form method="post" action="system_usermanager_settings_ldapacpicker.php">	
	<b>Please select which containers to Authenticate against:</b>
	<p/>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
  	 <tr>
    	<td class="tabnavtbl">
			<table width="100%">
<?php
	$ous = ldap_get_user_ous(true);
	$pconfig['ldapauthcontainers'] = split(";",$config['system']['webgui']['ldapauthcontainers']);
	if(!is_array($ous)) {
		echo "Sorry, we could not connect to the LDAP server.  Please try later.";
		exit;
	}
	if(is_array($ous)) {	
		foreach($ous as $ou) {
			if(in_array($ou, $pconfig['ldapauthcontainers']))
				$CHECKED=" CHECKED";
			else 
				$CHECKED="";
			echo "			<tr><td><input type='checkbox' value='{$ou}' name='ou[]'{$CHECKED}> {$ou}<br/></td></tr>\n";
		}
	}
?>	
			</table>
      	</td>
     </tr>
	</table>	

	<p/>

	<input type='submit' value='Save'>

 </body>
</html>

