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

require("guiconfig.inc");

$ldapserver = $config['system']['webgui']['ldapserver'];
$ldapbindun = $config['system']['webgui']['ldapbindun'];
$ldapbindpw = $config['system']['webgui']['ldapbindpw'];

$ldapfilter = $config['system']['webgui']['ldapfilter'];

echo "Testing pfSense LDAP settings... One moment please...<p/>";

echo "<table>";

echo "<tr><td>Attempting connection to {$ldapserver}</td><td>";
if(ldap_test_connection()) 
	echo "<td><font color=green>OK</td></tr>";
else 
	echo "<td><font color=red>failed</td></tr>";

echo "<tr><td>Attempting bind to {$ldapserver}</td><td>";
if(ldap_test_bind()) 
	echo "<td><font color=green>OK</td></tr>";
else 
	echo "<td><font color=red>failed</td></tr>";

echo "<tr><td>Attempting to fetch Organizational Units from {$ldapserver}</td><td>";
$ous = ldap_get_user_ous(true);
if(count($ous)>1) 
	echo "<td><font color=green>OK</td></tr>";
else 
	echo "<td><font color=red>failed</td></tr>";

echo "</table><p/>";

if(is_array($ous)) {
	echo "Organization units found:<p/>";
	echo "<table border='1'>";
	foreach($ous as $ou) {
		echo "<tr><td>" . $ou . "</td></tr>";
	}
	echo "</table>";
}

?>