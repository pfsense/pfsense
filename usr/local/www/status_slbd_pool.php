<?php
/* $Id$ */
/*
	status_slbd_pool.php
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

require("guiconfig.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

$slbd_logfile = "{$g['varlog_path']}/slbd.log";

$apinger_status = return_apinger_status();

$nentries = $config['syslog']['nentries'];
if (!$nentries)
        $nentries = 50;

$now = time();
$year = date("Y");

$pgtitle = "Status: Load Balancer: Pool";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array("Pools", true, "status_slbd_pool.php");
        $tab_array[] = array("Virtual Servers", false, "status_slbd_vs.php");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
		  <td width="10%" class="listhdrr">Type</td>
                  <td width="10%" class="listhdrr">Gateways</td>
                  <td width="30%" class="listhdrr">Status</td>
                  <td width="30%" class="listhdr">Description</td>
				</tr>
			  <?php $i = 0; foreach ($a_pool as $vipent):
				if ($vipent['type'] == "gateway") {
			  ?>
                <tr>
                  <td class="listlr">
				<?=$vipent['name'];?>
                  </td>
                  <td class="listr" align="center" >
                                <?=$vipent['type'];?>
                                <br />
                                (<?=$vipent['behaviour'];?>)
                  </td>
                  <td class="listr" align="center" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
                                foreach ((array) $vipent['servers'] as $server) {
                                        $svr = split("\|", $server);
					PRINT "<tr><td> {$svr[0]} </td></tr>";
                                }
                        ?>
			</table>
                  </td>
                  <td class="listr" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
				if ($vipent['type'] == "gateway") {
                                        foreach ((array) $vipent['servers'] as $server) {
                                                $svr = split("\|", $server);
						$monitorip = $svr[1];

						if(preg_match("/down/i", $apinger_status[$monitorip]['status'])) {
							$online = "Offline";
							$bgcolor = "lightcoral";
						} elseif(preg_match("/delay/i", $apinger_status[$monitorip]['status'])) {
							$online = "Warning";
							$bgcolor = "khaki";
						} elseif(preg_match("/loss/i", $apinger_status[$monitorip]['status'])) {
							$online = "Warning";
							$bgcolor = "khaki";
						} elseif(preg_match("/none/i", $apinger_status[$monitorip]['status'])) {
							$online = "Online";
							$bgcolor = "lightgreen";
						}
					PRINT "<tr><td bgcolor=\"$bgcolor\" > $online </td><td>";
					PRINT "Delay: {$apinger_status[$monitorip]['delay']}, ";
					PRINT "Loss: {$apinger_status[$monitorip]['loss']}";
					PRINT "</td></tr>";
					}
                                } else {
					PRINT "<tr><td> {$vipent['monitor']} </td></tr>";
                                }
                        ?>
			</table>
                  </td>
                  <td class="listbg" >
				<font color="#FFFFFF"><?=$vipent['desc'];?></font>
                  </td>
                </tr>
		<?php
			}
			$i++;
		 endforeach;
		 ?>
              </table>
	   </div>
	</table>

<?php include("fend.inc"); ?>
</body>
</html>
