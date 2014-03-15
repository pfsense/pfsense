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

##|+PRIV
##|*IDENT=page-system-usermanager-settings-testldap
##|*NAME=System: User Manager: Settings: Test LDAP page
##|*DESCR=Allow access to the 'System: User Manager: Settings: Test LDAP' page.
##|*MATCH=system_usermanager_settings_test.php*
##|-PRIV

require("guiconfig.inc");
require_once("auth.inc");

$authserver = $_GET['authserver'];
$authcfg = auth_get_authserver($authserver);

?>

<html>
  <HEAD>
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
			border-width: 1px 1px 1px 1px;
			border-spacing: 0px;
			border-style: solid solid solid solid;
			border-color: gray gray gray gray;
			border-collapse: collapse;
			background-color: white;
		}
    </STYLE>
  </HEAD>	
	<body>
		<form method="post" name="iform" id="iform">
			
<?php

if (!$authcfg) {
	printf(gettext("Could not find settings for %s%s"), htmlspecialchars($authserver), "<p/>");
} else {
	echo sprintf(gettext("Testing %s LDAP settings... One moment please..."), $g['product_name']) . "<p/>";

	echo "<table width='100%'>";

	echo "<tr><td>" . gettext("Attempting connection to") . " " . $ldapserver . "</td><td>";
	if(ldap_test_connection($authcfg)) {
		echo "<td><font color=green>OK</td></tr>";

		echo "<tr><td>" . gettext("Attempting bind to") . " " .  $ldapserver . "</td><td>";
		if(ldap_test_bind($authcfg)) {
			echo "<td><font color=green>OK</td></tr>";

			echo "<tr><td>" . gettext("Attempting to fetch Organizational Units from") . " " . $ldapserver . "</td><td>";
			$ous = ldap_get_user_ous(true, $authcfg);
			if(count($ous)>1) {
				echo "<td><font color=green>OK</td></tr>";
				echo "</table>";
				if(is_array($ous)) {
					echo gettext("Organization units found") . ":<p/>";
					echo "<table width='100%'>";
					foreach($ous as $ou) {
						echo "<tr><td>" . $ou . "</td></tr>";
					}
				}
			} else
				echo "<td><font color=red>" . gettext("failed") . "</td></tr>";

			echo "</table><p/>";

		} else {
			echo "<td><font color=red>" . gettext("failed") . "</td></tr>";
			echo "</table><p/>";
		}
	} else {
		echo "<td><font color=red>" . gettext("failed") . "</td></tr>";
		echo "</table><p/>";
	}
}

?>
	<p/>
	<input type="Button" value="<?=gettext("Close"); ?>" onClick='Javascript:window.close();'>

	</form>
</body>
</html>
