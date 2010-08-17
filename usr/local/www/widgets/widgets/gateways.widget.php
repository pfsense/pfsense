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
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

$gateways_status = array();
$gateways_status = return_gateways_status();

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
         <?php foreach ($gateways_status as $target => $gateway) { ?>
             <?php
                     $monitor = $target;
			if(empty($monitor)) {
				$monitor = $gateway['gateway'];
			}
	 ?>
                <tr>
                  <td class="listlr" id="gateway<?= $counter; ?>">
                                <?=$gateway['name'];?>
				<?php $counter++; ?>
                  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
                              <?php echo lookup_gateway_ip_by_name($gateway['name']);?>
				<?php $counter++; ?>
                  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
								<?=$gateway['delay'];?>
								<?php $counter++; ?>
				  </td>
                  <td class="listr" align="center" id="gateway<?= $counter; ?>">
								<?=$gateway['loss'];?>
								<?php $counter++; ?>
				  </td>
                  <td class="listr" id="gateway<?=$counter?>" >
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
                                } elseif ($gateway['status'] == "none") {
                                        $online = "Online";
                                        $bgcolor = "lightgreen";
                                } else
					$online = "Gathering data";
				echo "<tr><td bgcolor=\"$bgcolor\" > $online </td>";
				$counter++;
                        ?>
                        </table>
                  </td>
                </tr>
        <?php
       		}
        ?>
          </table>
