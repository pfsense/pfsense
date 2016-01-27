<?php
/*
	system_usermanager_settings_test.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2014 Silvio Giunge <desenvolvimento@bluepex.com>
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-system-usermanager-settings-testldap
##|*NAME=System: User Manager: Settings: Test LDAP
##|*DESCR=Allow access to the 'System: User Manager: Settings: Test LDAP' page.
##|*MATCH=system_usermanager_settings_test.php*
##|-PRIV

require("guiconfig.inc");
require_once("auth.inc");

if (isset($config['system']['authserver'][0]['host'])) {
	$auth_server = $config['system']['authserver'][0]['host'];
	$authserver = $_GET['authserver'];
	$authcfg = auth_get_authserver($authserver);
}

?><!DOCTYPE html>
<html lang="en">
<head>
	<link rel="stylesheet" href="/bootstrap/css/pfSense.css" />
	<title><?=gettext("Test Authentication server"); ?></title>
</head>
<body id="system_usermanager_settings_test" class="no-menu">
	<div id="jumbotron">
		<div class="container">
			<div class="col-sm-offset-3 col-sm-6 col-xs-12">
				<pre>
<?php

if (!$authcfg) {
	printf(gettext("Could not find settings for %s%s"), htmlspecialchars($authserver), "<p/>");
} else {
	echo "<b>" . sprintf(gettext("Testing %s LDAP settings... One moment please..."), $g['product_name']) . "</b>";

	echo "<table>";

	echo "<tr><td>" . gettext("Attempting connection to") . " " . "<td><center>" . htmlspecialchars($auth_server). "</b></center></td>";
	if (ldap_test_connection($authcfg)) {
		echo "<td><span class=\"text-center text-success\">OK</span></td></tr>";

		echo "<tr><td>" . gettext("Attempting bind to") . " " . "<td><center>" . htmlspecialchars($auth_server). "</b></center></td>";
		if (ldap_test_bind($authcfg)) {
			echo "<td><span class=\"text-center text-success\">OK</span></td></tr>";

			echo "<tr><td>" . gettext("Attempting to fetch Organizational Units from") . " " . "<td><center>" . htmlspecialchars($auth_server). "</b></center></td>";
			$ous = ldap_get_user_ous(true, $authcfg);
			if (count($ous)>1) {
				echo "<td><span class=\"text-center text-success\">OK</span></td></tr>";
				echo "</table>";
				if (is_array($ous)) {
					echo "<br/>";
					echo "<b>" . gettext("Organization units found") . "</b>";
					echo "<table width='100%'>";
					foreach ($ous as $ou) {
						echo "<tr><td onmouseover=\"this.style.backgroundColor='#ffffff';\" onmouseout=\"this.style.backgroundColor='#dddddd';\">" . $ou . "</td></tr>";
					}
				}
			} else {
				echo "<td><span class=\"text-alert\">" . gettext("failed") . "</span></td></tr>";
			}

			echo "</table><p/>";

		} else {
			echo "<td><span class=\"text-alert\">" . gettext("failed") . "</span></td></tr>";
			echo "</table><p/>";
		}
	} else {
		echo "<td><span class=\"text-alert\">" . gettext("failed") . "</span></td></tr>";
		echo "</table><p/>";
	}
}

?>
				</pre>

				<a href="javascript:window.close();" class="btn btn-primary">Return</a>
			</div>
		</div>
	</div>
</body>
</html>
