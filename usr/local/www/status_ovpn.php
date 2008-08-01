#!/usr/local/bin/php
<?php 
/*
	HEAD
	status_ovpn.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2005-2006 Peter Allgeyer <allgeyer@web.de>.
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
##|*IDENT=page-status-openvpn
##|*NAME=Status: OpenVPN page
##|*DESCR=Allow access to the 'Status: OpenVPN' page.
##|*MATCH=status_ovpn.php*
##|-PRIV


$pgtitle = array("Status", "OpenVPN");
require("guiconfig.inc");

$client_list = array();
$virtip_list = array();

function dump_log($type) {
	global $g, $config;

	unset($client_list);
	$client_list = array();

	unset($virtip_list);
	$virtip_list = array();
			
	$max = ($type == 'tun') ? 17 : 4;
	for ($i = 0; $i < $max; $i++) {
		if (file_exists("/var/log/openvpn.log")) {

			unset($string);
			unset($logarr);
			
			exec("/bin/cat /var/log/openvpn.log", $logarr);
	
			foreach ($logarr as $logent) {
				$logent = preg_split("/,/", $logent, 5);
				$string = preg_split("/:/", $logent[1]);

				/* search for ip address in second column */
				if (isset($string[0]) && is_ipaddr($string[0]))
					array_push($client_list, $logent);
				
				/* search for ip address in first column */
				else if (is_ipaddr($logent[0]))
					array_push($virtip_list, $logent);
			}
		}
	}

	if (count($client_list > 1)) {
		foreach ($client_list as $cent) {
			echo "<tr>\n";
			echo "<td class=\"listlr\" nowrap>" . htmlspecialchars($cent[0]) . "</td>\n";
			echo "<td class=\"listr\">" . htmlspecialchars($cent[1]) . "</td>\n";

			unset($found);
			if (isset($virtip_list)) {
				foreach ($virtip_list as $vent) {
					if ($cent[1] == $vent[2]) {
						$found = 1;
						echo "<td class=\"listr\">" . htmlspecialchars($vent[0]) . "&nbsp;</td>\n";
					}
				}
			}
			if (!isset($found))
				echo "<td class=\"listr\" align=\"center\">--</td>\n";
				
			$date = preg_split("/\s+/", $cent[4]);
			echo "<td nowrap class=\"listr\">" . htmlspecialchars($date[1]) . " " . htmlspecialchars($date[2]) . " " . htmlspecialchars($date[3]) . "</td>\n";
			echo "<td class=\"listr\">" . htmlspecialchars($cent[2]) . "</td>\n";
			echo "<td class=\"listr\">" . htmlspecialchars($cent[3]) . "</td>\n";
			echo "</tr>\n";
		}
	}
}

?>
<?php include("head.inc"); ?>
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
  <td colspan="6" class="listtopic"> 
	    OpenVPN server status entries</td>
	</tr>
	 <tr>
	  <td class="listhdrr">Common Name</td>
	  <td class="listhdrr">Real Address</td>
	  <td class="listhdrr">Virtual Address</td>
	  <td class="listhdrr">Connected Since</td>
	  <td class="listhdrr">Bytes Received</td>
	  <td class="listhdr">Bytes Sent</td>
	</tr>
	<?php dump_log(tun); ?>
	<?php dump_log(tap); ?>
</table>
<br>
<strong class="red">Note:<br>
</strong>Please note that status entries are updated once every minute only.
<?php include("fend.inc"); ?>

