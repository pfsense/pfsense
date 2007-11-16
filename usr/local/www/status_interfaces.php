<?php
/* $Id$ */
/*
	status_interfaces.php
        part of pfSense
	Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>.
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

require_once("guiconfig.inc");

$wancfg = &$config['interfaces']['wan'];

if ($_POST) {
	$interface = $_POST['interface'];
	$ifcfg = &$config['interfaces'][$interface];
	if ($_POST['submit'] == "Disconnect" || $_POST['submit'] == "Release") {
		if ($ifcfg['ipaddr'] == "dhcp")
			interfaces_dhcp_down($interface);
		else if ($ifcfg['ipaddr'] == "pppoe")
			interfaces_wan_pppoe_down(); // FIXME: when we support multi-pppoe
		else if ($ifcfg['ipaddr'] == "pptp")
			interfaces_wan_pptp_down(); // FIXME: when we support multi-pptp
	} else if ($_POST['submit'] == "Connect" || $_POST['submit'] == "Renew") {
		if ($ifcfg['ipaddr'] == "dhcp")
			interfaces_dhcp_up($interface);
		else if ($ifcfg['ipaddr'] == "pppoe")
			interfaces_wan_pppoe_up(); // FIXME: when we support multi-pppoe
		else if ($ifcfg['ipaddr'] == "pptp")
			interfaces_wan_pptp_up(); // FIXME: when we support multi-pptp
	} else {
		header("Location: index.php");
		exit;
	}
}

$pgtitle = array("Status","Interfaces");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <?php $i = 0; $ifdescrs = array('wan' => 'WAN', 'lan' => 'LAN');
		for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
			$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
		}
		foreach ($ifdescrs as $ifdescr => $ifname):
			$ifinfo = get_interface_info($ifdescr);
		?>
		<form action="status_interfaces.php" method="post">
		<input type="hidden" name="interface" value="<?php echo $ifdescr; ?>">
              <?php if ($i): ?>
              <tr>
			<td colspan="8" class="list" height="12"></td>
			</tr>
			<?php endif; ?>
              <tr>
                <td colspan="2" class="listtopic">
                  <?=htmlspecialchars($ifname);?>
				  interface
				  (<?=convert_friendly_interface_to_real_interface_name($ifname);?>)
				</td>
              </tr>
              <tr>
                <td width="22%" class="vncellt">Status</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['status']);?>
                </td>
              </tr><?php if ($ifinfo['dhcplink']): ?>
			  <tr>
				<td width="22%" class="vncellt">DHCP</td>
				<td width="78%" class="listr">
				  <?=htmlspecialchars($ifinfo['dhcplink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['dhcplink'] == "up"): ?>
				  <input type="submit" name="submit" value="Release" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Renew" class="formbtns">
				  <?php endif; ?>
				</td>
			  </tr><?php endif; if ($ifinfo['pppoelink']): ?>
              <tr>
                <td width="22%" class="vncellt">PPPoE</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['pppoelink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['pppoelink'] == "up"): ?>
				  <input type="submit" name="submit" value="Disconnect" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Connect" class="formbtns">
				  <?php endif; ?>
                </td>
              </tr><?php  endif; if ($ifinfo['pptplink']): ?>
              <tr>
                <td width="22%" class="vncellt">PPTP</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['pptplink']);?>&nbsp;&nbsp;
				  <?php if ($ifinfo['pptplink'] == "up"): ?>
				  <input type="submit" name="submit" value="Disconnect" class="formbtns">
				  <?php else: ?>
				  <input type="submit" name="submit" value="Connect" class="formbtns">
				  <?php endif; ?>
                </td>
              </tr><?php  endif; if ($ifinfo['macaddr']): ?>
              <tr>
                <td width="22%" class="vncellt">MAC address</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['macaddr']);?>
                </td>
              </tr>
	      </form>
		<?php endif; if ($ifinfo['status'] != "down"): ?>
			  <?php if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down"): ?>
			  <?php if ($ifinfo['ipaddr']): ?>
              <tr>
                <td width="22%" class="vncellt">IP address</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['ipaddr']);?>
                  &nbsp; </td>
              </tr><?php endif; ?><?php if ($ifinfo['subnet']): ?>
              <tr>
                <td width="22%" class="vncellt">Subnet mask</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['subnet']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['gateway']): ?>
              <tr>
                <td width="22%" class="vncellt">Gateway</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['gateway']);?>
                </td>
              </tr><?php endif; if ($ifdescr == "wan" && file_exists("{$g['varetc_path']}/resolv.conf")): ?>
                <td width="22%" class="vncellt">ISP DNS servers</td>
                <td width="78%" class="listr">
		<?php
			$dns_servers = get_dns_servers();
			foreach($dns_servers as $dns) {
				echo "{$dns}<br>";
			}
		?>
		</td>
			  <?php endif; endif; if ($ifinfo['media']): ?>
              <tr>
                <td width="22%" class="vncellt">Media</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['media']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['channel']): ?>
              <tr>
                <td width="22%" class="vncellt">Channel</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['channel']);?>
                </td>
              </tr><?php endif; ?><?php if ($ifinfo['ssid']): ?>
              <tr>
                <td width="22%" class="vncellt">SSID</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['ssid']);?>
                </td>
              </tr><?php endif; ?>
              <tr>
                <td width="22%" class="vncellt">In/out packets</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['inpkts'] . "/" . $ifinfo['outpkts'] . " (" .
			format_bytes($ifinfo['inbytes']) . "/" . format_bytes($ifinfo['outbytes']) . ")");?>
                </td>
              </tr><?php if (isset($ifinfo['inerrs'])): ?>
              <tr>
                <td width="22%" class="vncellt">In/out errors</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['inerrs'] . "/" . $ifinfo['outerrs']);?>
                </td>
              </tr><?php endif; ?><?php if (isset($ifinfo['collisions'])): ?>
              <tr>
                <td width="22%" class="vncellt">Collisions</td>
                <td width="78%" class="listr">
                  <?=htmlspecialchars($ifinfo['collisions']);?>
                </td>
              </tr><?php endif; ?>
	      <?php endif; ?>

		  <?php if ($ifinfo['bridge']): ?>
		  <tr>
		    <td width="22%" class="vncellt">Bridge (<?=$ifinfo['bridgeint']?>)</td>
		    <td width="78%" class="listr">
		      <?=$ifinfo['bridge'];?>
		    </td>
		  </tr>
		  <?php endif; ?>

	<?php if(file_exists("/usr/bin/vmstat")): ?>
	<?php
			$real_interface = "";
			$interrupt_total = "";
			$interrupt_sec = "";
			$real_interface = convert_friendly_interface_to_real_interface_name($ifname);
          	$interrupt_total = `vmstat -i | grep $real_interface | awk '{ print $3 }'`;
          	$interrupt_sec = `vmstat -i | grep $real_interface | awk '{ print $4 }'`;
          	if(strstr($interrupt_total, "hci")) {
    	      	$interrupt_total = `vmstat -i | grep $real_interface | awk '{ print $4 }'`;
	          	$interrupt_sec = `vmstat -i | grep $real_interface | awk '{ print $5 }'`;          	
          	}	
	?>
	<?php if($interrupt_total): ?>
     <tr>
        <td width="22%" class="vncellt">Interrupts/Second</td>
        <td width="78%" class="listr">
          <?php

          	echo $interrupt_total . " total";
          	echo "<br/>";
          	echo $interrupt_sec . " rate";
          ?>
        </td>
      </tr>
     <?php endif; ?>
	<?php endif; ?>
	
              <?php $i++; endforeach; ?>
            </table>
<br/>
</strong>Using dial-on-demand will bring the connection up again if any packet
triggers it. To substantiate this point: disconnecting manually
will <strong>not</strong> prevent dial-on-demand from making connections
to the outside! Don't use dial-on-demand if you want to make sure that the line
is kept disconnected.
<p>
<span class="red"><strong>Note:</strong></span> In/out counters will wrap at 32bit (4 Gigabyte) ! <br/>

<meta http-equiv="refresh" content="120;url=<?php print $_SERVER['SCRIPT_NAME']; ?>">

<?php include("fend.inc"); ?>
