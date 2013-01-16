<?php
/*
        $Id$
        Copyright 2008 Seth Mos
        Part of pfSense widgets (www.pfsense.com)
        originally based on m0n0wall (http://m0n0.ch/wall)

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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status(true);

$counter = 1;

?>
         <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
                  <td width="10%" class="listhdrr">Gateway</td>
                  <td width="10%" class="listhdrr">RTT</td>
                  <td width="10%" class="listhdrr">Loss</td>
                  <td width="30%" class="listhdrr">Status</td>
                                </tr>
         <?php foreach ($a_gateways as $gname => $gateway) { ?>
                <tr>
                  <td class="listlr" id="gateway<?= $counter; ?>">
                                <?=$gateway['name'];?>
				<?php $counter++; ?>
                  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
		<?php 	if (is_ipaddr($gateway['gateway']))
				echo $gateway['gateway'];
			else {
				if($gateway['ipprotocol'] == "inet")
					echo get_interface_gateway($gateway['friendlyiface']);
				if($gateway['ipprotocol'] == "inet6")
					echo get_interface_gateway_v6($gateway['friendlyiface']);
			}
		?>
				<?php $counter++; ?>
                  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
		<?php	if ($gateways_status[$gname])
				echo $gateways_status[$gname]['delay'];
			else
				echo gettext("Pending");
		?>
				<?php $counter++; ?>
		  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
		<?php	if ($gateways_status[$gname])
				echo $gateways_status[$gname]['loss'];
			else
				echo gettext("Pending");
		?>
				<?php $counter++; ?>
		  </td>
                  <td class="listr" id="gateway<?=$counter?>" >
                        <table border="0" cellpadding="0" cellspacing="2">
		<?php	if ($gateways_status[$gname]) {
				if (stristr($gateways_status[$gname]['status'], "down")) {
                                        $online = "Offline";
                                        $bgcolor = "#F08080";  // lightcoral
                                } elseif (stristr($gateways_status[$gname]['status'], "loss")) {
                                        $online = "Packetloss";
                                        $bgcolor = "#F0E68C";  // khaki
                                } elseif (stristr($gateways_status[$gname]['status'], "delay")) {
                                        $online = "Latency";
                                        $bgcolor = "#F0E68C";  // khaki
                                } elseif ($gateways_status[$gname]['status'] == "none") {
                                        $online = "Online";
                                        $bgcolor = "#90EE90";  // lightgreen
                                } elseif ($gateways_status[$gname]['status'] == "") {
                                        $online = "Pending";
                                        $bgcolor = "#D3D3D3";  // lightgray
				}
			} else {
				$online = gettext("Unknown");
                                $bgcolor = "#ADD8E6";  // lightblue
			}
			echo "<tr><td bgcolor=\"$bgcolor\">&nbsp;$online&nbsp;</td></tr>\n";
			$counter++;
		?>
                        </table>
                  </td>
                </tr>
        <?php
       		}
        ?>
          </table>
