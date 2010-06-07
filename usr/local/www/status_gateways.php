<?php
/* $Id$ */
/*
	status_gateways.php
	part of pfSense (http://www.pfsense.com/)

	Copyright (C) 2006 Seth Mos <seth.mos@xs4all.nl>.
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
	pfSense_MODULE:	routing
*/

##|+PRIV
##|*IDENT=page-status-gateways
##|*NAME=Status: Gateways page
##|*DESCR=Allow access to the 'Status: Gateways' page.
##|*MATCH=status_gateways.php*
##|-PRIV

require("guiconfig.inc");

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status();

$now = time();
$year = date("Y");

$pgtitle = array("Status","Gateways");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array("Gateways", true, "status_gateways.php");
        $tab_array[] = array("Gateway Groups", false, "status_gateway_groups.php");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
                  <td width="10%" class="listhdrr">Gateway</td>
                  <td width="10%" class="listhdrr">Monitor</td>
                  <td width="30%" class="listhdrr">Status</td>
                  <td width="30%" class="listhdr">Description</td>
				</tr>
			  <?php foreach ($gateways_status as $gateway) {
			?>
                <tr>
                  <td class="listlr">
				<?=strtoupper($gateway['name']);?>
                  </td>
                  <td class="listr" align="center" >
                                <?=$gateway['gateway'];?>
                  </td>
                  <td class="listr" align="center" >
                                <?=$gateway['monitor'];?>
                  </td>
                  <td class="listr" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
				if (stristr($gateway['status'], "down")) {
					$online = "Offline";
					$bgcolor = "lightcoral";
				} elseif (stristr($gateway['status'], "loss")) {
					$online = "Warning, Packetloss";
					$bgcolor = "khaki";
				} elseif (stristr($gateway['status'], "delay")) {
					$online = "Warning, Latency";
					$bgcolor = "khaki";
				} elseif (stristr($gateway['status'], "none")) {
					$online = "Online";
					$bgcolor = "lightgreen";
				}
				echo "<tr><td bgcolor=\"$bgcolor\" > $online </td><td>";
				$lastchange = $gateway['lastcheck'];
				if(!empty($lastchange)) {
					$lastchange = explode(" ", $lastchange);
					array_shift($lastchange);
					array_shift($lastchange);
					$lastchange = implode(" ", $lastchange);
					PRINT "Last check $lastchange";
				} else {
					print "Gathering data";
				}
				echo "</td></tr>";
                        ?>
			</table>
                  </td>
		  <td class="listbg"> <?=$a_gateway[$gateway['name']]['descr']; ?></td>
                </tr>
		<?php } ?>
              </table>
	   </div>
	</table>

<?php include("fend.inc"); ?>
</body>
</html>
