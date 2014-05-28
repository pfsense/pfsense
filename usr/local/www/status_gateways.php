<?php
/* $Id$ */
/*
	status_gateways.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
$gateways_status = return_gateways_status(true);

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"),gettext("Gateways"));
$shortcut_section = "gateways";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array(gettext("Gateways"), true, "status_gateways.php");
        $tab_array[] = array(gettext("Gateway Groups"), false, "status_gateway_groups.php");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr"><?=gettext("Name"); ?></td>
                  <td width="10%" class="listhdrr"><?=gettext("Gateway"); ?></td>
                  <td width="10%" class="listhdrr"><?=gettext("Monitor"); ?></td>
                  <td width="8%" class="listhdrr"><?=gettext("RTT"); ?></td>
                  <td width="7%" class="listhdrr"><?=gettext("Loss"); ?></td>
                  <td width="35%" class="listhdrr"><?=gettext("Status"); ?></td>
                  <td width="20%" class="listhdr"><?=gettext("Description"); ?></td>
		</tr>
		  <?php foreach ($a_gateways as $gname => $gateway) {
			?>
                <tr>
                  <td class="listlr">
				<?=$gateway['name'];?>
                  </td>
                  <td class="listr" align="center" >
                                <?php echo lookup_gateway_ip_by_name($gname);?>
                  </td>
                  <td class="listr" align="center" >
                                <?php 	if ($gateways_status[$gname]) 
						echo $gateways_status[$gname]['monitorip'];
					else
						echo $gateway['monitorip'];
				?>
                  </td>
		<td class="listr" align="center">
		<?php	if ($gateways_status[$gname])
				echo $gateways_status[$gname]['delay'];
			else
				echo gettext("Pending");
		?>
				<?php $counter++; ?>
		</td>
		<td class="listr" align="center">
		<?php	if ($gateways_status[$gname])
				echo $gateways_status[$gname]['loss'];
			else
				echo gettext("Pending");
		?>
				<?php $counter++; ?>
		</td>
                  <td class="listr" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
				if ($gateways_status[$gname]) {
					$status = $gateways_status[$gname];
					if (stristr($status['status'], "force_down")) {
						$online = gettext("Offline (forced)");
						$bgcolor = "#F08080";  // lightcoral
					} elseif (stristr($status['status'], "down")) {
						$online = gettext("Offline");
						$bgcolor = "#F08080";  // lightcoral
					} elseif (stristr($status['status'], "loss")) {
						$online = gettext("Warning, Packetloss").': '.$status['loss'];
						$bgcolor = "#F0E68C";  // khaki
					} elseif (stristr($status['status'], "delay")) {
						$online = gettext("Warning, Latency").': '.$status['delay'];
						$bgcolor = "#F0E68C";  // khaki
					} elseif ($status['status'] == "none") {
						$online = gettext("Online");
						$bgcolor = "#90EE90";  // lightgreen
					}
				} else if (isset($gateway['monitor_disable'])) {
						$online = gettext("Online");
						$bgcolor = "#90EE90";  // lightgreen
				} else {
					$online = gettext("Pending");
					$bgcolor = "#D3D3D3";  // lightgray
				}
				echo "<tr><td><table width='100%'><tr><td bgcolor=\"$bgcolor\">&nbsp;$online&nbsp;</td></tr><tr><td>";
				$lastchange = $gateways_status[$gname]['lastcheck'];
				if(!empty($lastchange)) {
					echo gettext("Last check:") . '<br />' . $lastchange;
				}
				echo "</td></tr></table></td></tr>";
                        ?>
			</table>
                  </td>
		  <td class="listbg"> <?=$gateway['descr']; ?></td>
                </tr>
		<?php } ?>
              </table>
	   </div>
</td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
