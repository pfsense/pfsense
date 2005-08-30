#!/usr/local/bin/php
<?php 
/* $Id$ */
/*
	services_dhcp.php
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$if = $_GET['if'];
if ($_POST['if'])
	$if = $_POST['if'];
	
$iflist = array("lan" => "LAN");

for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
	$oc = $config['interfaces']['opt' . $i];
	
	if (isset($oc['enable']) && $oc['if'] && (!$oc['bridge'])) {
		$iflist['opt' . $i] = $oc['descr'];
	}
}

if (!$if || !isset($iflist[$if]))
	$if = "lan";

$pconfig['range_from'] = $config['dhcpd'][$if]['range']['from'];
$pconfig['range_to'] = $config['dhcpd'][$if]['range']['to'];
$pconfig['deftime'] = $config['dhcpd'][$if]['defaultleasetime'];
$pconfig['maxtime'] = $config['dhcpd'][$if]['maxleasetime'];
$pconfig['gateway'] = $config['dhcpd'][$if]['gateway'];
list($pconfig['wins1'],$pconfig['wins2']) = $config['dhcpd'][$if]['winsserver'];
list($pconfig['dns1'],$pconfig['dns2']) = $config['dhcpd'][$if]['dnsserver'];
$pconfig['enable'] = isset($config['dhcpd'][$if]['enable']);
$pconfig['denyunknown'] = isset($config['dhcpd'][$if]['denyunknown']);
$pconfig['staticarp'] = isset($config['dhcpd'][$if]['staticarp']);
$pconfig['failover_peerip'] = $config['dhcpd'][$if]['failover_peerip'];

$ifcfg = $config['interfaces'][$if];

if (!is_array($config['dhcpd'][$if]['staticmap'])) {
	$config['dhcpd'][$if]['staticmap'] = array();
}
staticmaps_sort($if);
$a_maps = &$config['dhcpd'][$if]['staticmap'];

function is_inrange($test, $start, $end) {
	if ( (ip2long($test) < ip2long($end)) && (ip2long($test) > ip2long($start)) )
		return true;
	else
		return false;
}

if ($_POST) {

	unset($input_errors);

	$pconfig = $_POST;

	/* input validation */
	if ($_POST['enable']) {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = explode(",", "Range begin,Range end");
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

		foreach($a_maps as $mapent) {
			if(is_inrange($mapent['ipaddr'], $_POST['range_from'], $_POST['range_to'])) {
				$input_errors[] = "{$mapent['ipaddr']} is inside the range you specified.";
			}

		}
		
		if (($_POST['range_from'] && !is_ipaddr($_POST['range_from']))) {
			$input_errors[] = "A valid range must be specified.";
		}
		if (($_POST['range_to'] && !is_ipaddr($_POST['range_to']))) {
			$input_errors[] = "A valid range must be specified.";
		}
		if (($_POST['gateway'] && !is_ipaddr($_POST['gateway'])))
			$input_errors[] = "A valid IP address must be specified for the gateway.";
		if (($_POST['wins1'] && !is_ipaddr($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddr($_POST['wins2']))) {
			$input_errors[] = "A valid IP address must be specified for the primary/secondary WINS servers.";
		}
		if (($_POST['dns1'] && !is_ipaddr($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddr($_POST['dns2']))) {
			$input_errors[] = "A valid IP address must be specified for the primary/secondary DNS servers.";
		}
		if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
			$input_errors[] = "The default lease time must be at least 60 seconds.";
		}
		if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
			$input_errors[] = "The maximum lease time must be at least 60 seconds and higher than the default lease time.";
		}
		
		if (!$input_errors) {
			/* make sure the range lies within the current subnet */
			$subnet_start = (ip2long($ifcfg['ipaddr']) & gen_subnet_mask_long($ifcfg['subnet']));
			$subnet_end = (ip2long($ifcfg['ipaddr']) | (~gen_subnet_mask_long($ifcfg['subnet'])));
			
			if ((ip2long($_POST['range_from']) < $subnet_start) || (ip2long($_POST['range_from']) > $subnet_end) ||
			    (ip2long($_POST['range_to']) < $subnet_start) || (ip2long($_POST['range_to']) > $subnet_end)) {
				$input_errors[] = "The specified range lies outside of the current subnet.";	
			}
			
			if (ip2long($_POST['range_from']) > ip2long($_POST['range_to']))
				$input_errors[] = "The range is invalid (first element higher than second element).";
			
			/* make sure that the DHCP Relay isn't enabled on this interface */
			if (isset($config['dhcrelay'][$if]['enable']))
				$input_errors[] = "You must disable the DHCP relay on the {$iflist[$if]} interface before enabling the DHCP server.";
		}
	}

	if (!$input_errors) {
		$config['dhcpd'][$if]['range']['from'] = $_POST['range_from'];
		$config['dhcpd'][$if]['range']['to'] = $_POST['range_to'];
		$config['dhcpd'][$if]['defaultleasetime'] = $_POST['deftime'];
		$config['dhcpd'][$if]['maxleasetime'] = $_POST['maxtime'];
		$config['dhcpd'][$if]['enable'] = $_POST['enable'] ? true : false;
		$config['dhcpd'][$if]['denyunknown'] = $_POST['denyunknown'] ? true : false;
		$config['dhcpd'][$if]['failover_peerip'] = $_POST['failover_peerip'];
				
		unset($config['dhcpd'][$if]['winsserver']);
		if ($_POST['wins1'])
			$config['dhcpd'][$if]['winsserver'][] = $_POST['wins1'];
		if ($_POST['wins2'])
			$config['dhcpd'][$if]['winsserver'][] = $_POST['wins2'];

		if ($_POST['dns1']) {
			unset($config['dhcpd'][$if]['dnsserver']);
			$config['dhcpd'][$if]['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$config['dhcpd'][$if]['dnsserver'][] = $_POST['dns2'];
		}

			
		$config['dhcpd'][$if]['gateway'] = $_POST['gateway'];

		$config['dhcpd'][$if]['staticarp'] = $_POST['staticarp'] ? true : false;
	

		write_config();

		/* static arp configuration */
                if (isset($config['dhcpd'][$if]['staticarp']))
			interfaces_staticarp_configure($if);
		
		$retval = 0;
		config_lock();
		$retval = services_dhcpd_configure();
		config_unlock();
		$savemsg = get_std_save_message($retval);
	}
}

if ($_GET['act'] == "del") {
	if ($a_maps[$_GET['id']]) {
		unset($a_maps[$_GET['id']]);
		write_config();
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

$pgtitle = "Services: DHCP server";
include("head.inc");

?>

<script language="JavaScript">
<!--
function enable_change(enable_over) {
	var endis;
	endis = !(document.iform.enable.checked || enable_over);
	document.iform.range_from.disabled = endis;
	document.iform.range_to.disabled = endis;
	document.iform.wins1.disabled = endis;
	document.iform.wins2.disabled = endis;
	document.iform.dns1.disabled = endis;
	document.iform.dns2.disabled = endis;
	document.iform.deftime.disabled = endis;
	document.iform.maxtime.disabled = endis;
	document.iform.gateway.disabled = endis;
	document.iform.failover_peerip.disabled = endis;
	document.iform.staticarp.disabled = endis;
	document.iform.denyunknown.disabled = endis;
}
//-->
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="services_dhcp.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_staticmapsdirty_path)): ?><p>
<?php print_info_box_np("The static mapping configuration has been changed.<br>You must apply the changes in order for them to take effect.");?><br>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <?php
	/* active tabs */
	$tab_array = array();
	$tabscounter = 0;
	$i = 0;
	foreach ($iflist as $ifent => $ifname) {
		if ($ifent == $if)
			$active = true;
		else
			$active = false;
		$tab_array[] = array($ifname, $active, "services_dhcp.php?if={$ifent}");
	}
	display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr> 
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
                      <tr> 
                        <td width="22%" valign="top" class="vtable">&nbsp;</td>
                        <td width="78%" class="vtable">
<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked"; ?> onClick="enable_change(false)">
                          <strong>Enable DHCP server on 
                          <?=htmlspecialchars($iflist[$if]);?>
                          interface</strong></td>
                      </tr>
				  <tr>
	              <td width="22%" valign="top" class="vtable">&nbsp;</td>
                      <td width="78%" class="vtable">
<input name="denyunknown" id="denyunknown" type="checkbox" value="yes" <?php if ($pconfig['denyunknown']) echo "checked"; ?>>
                      <strong>Deny unknown clients</strong><br>
                      If this is checked, only the clients defined below will get DHCP leases from this server. </td>
		      		  </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncellreq">Subnet</td>
                        <td width="78%" class="vtable"> 
                          <?=gen_subnet($ifcfg['ipaddr'], $ifcfg['subnet']);?>
                        </td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncellreq">Subnet 
                          mask</td>
                        <td width="78%" class="vtable"> 
                          <?=gen_subnet_mask($ifcfg['subnet']);?>
                        </td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncellreq">Available 
                          range</td>
                        <td width="78%" class="vtable"> 
                          <?=long2ip(ip2long($ifcfg['ipaddr']) & gen_subnet_mask_long($ifcfg['subnet']));?>
                          - 
                          <?=long2ip(ip2long($ifcfg['ipaddr']) | (~gen_subnet_mask_long($ifcfg['subnet']))); ?>
                        </td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncellreq">Range</td>
                        <td width="78%" class="vtable"> 
                          <input name="range_from" type="text" class="formfld" id="range_from" size="20" value="<?=htmlspecialchars($pconfig['range_from']);?>"> 
                          &nbsp;to&nbsp; <input name="range_to" type="text" class="formfld" id="range_to" size="20" value="<?=htmlspecialchars($pconfig['range_to']);?>">
			</td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncell">WINS servers</td>
                        <td width="78%" class="vtable"> 
                          <input name="wins1" type="text" class="formfld" id="wins1" size="20" value="<?=htmlspecialchars($pconfig['wins1']);?>"><br>
                          <input name="wins2" type="text" class="formfld" id="wins2" size="20" value="<?=htmlspecialchars($pconfig['wins2']);?>">
			</td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncell">DNS servers</td>
                        <td width="78%" class="vtable"> 
                          <input name="dns1" type="text" class="formfld" id="dns1" size="20" value="<?=htmlspecialchars($pconfig['dns1']);?>"><br>
                          <input name="dns2" type="text" class="formfld" id="dns2" size="20" value="<?=htmlspecialchars($pconfig['dns2']);?>"><br>
			  NOTE: leave blank to use the system default DNS servers.  This option is handy when your doing CARP+DHCP Failover, etc.
			</td>
                      </tr>
                     <tr> 
                       <td width="22%" valign="top" class="vncell">Gateway</td>
                       <td width="78%" class="vtable"> 
                         <input name="gateway" type="text" class="formfld" id="gateway" size="20" value="<?=htmlspecialchars($pconfig['gateway']);?>"><br>
			 The default is to use the IP of the firewall as the gateway.  Specify an alternate gateway here if this is not the correct gateway for your network.
			</td> 
                     </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncell">Default lease 
                          time</td>
                        <td width="78%" class="vtable"> 
                          <input name="deftime" type="text" class="formfld" id="deftime" size="10" value="<?=htmlspecialchars($pconfig['deftime']);?>">
                          seconds<br>
                          This is used for clients that do not ask for a specific 
                          expiration time.<br>
                          The default is 7200 seconds.
			</td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top" class="vncell">Maximum lease 
                          time</td>
                        <td width="78%" class="vtable"> 
                          <input name="maxtime" type="text" class="formfld" id="maxtime" size="10" value="<?=htmlspecialchars($pconfig['maxtime']);?>">
                          seconds<br>
                          This is the maximum lease time for clients that ask 
                          for a specific expiration time.<br>
                          The default is 86400 seconds.
			</td>
                      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Failover peer IP:</td>
                        <td width="78%" class="vtable">
				<input name="failover_peerip" type="text" class="formfld" id="failover_peerip" size="10" value="<?=htmlspecialchars($pconfig['failover_peerip']);?>"><br>
				Leave blank to disable.  Enter the REAL address of the other machine.  Machines must be using CARP.
			</td>
		      </tr>
                      <tr>
                        <td width="22%" valign="top" class="vncell">Static ARP</td>
                        <td width="78%" class="vtable">
				<table>
					<tr>
						<td>
							<input valign="middle" type="checkbox" value="yes" name="staticarp" id="staticarp" <?php if($pconfig['staticarp']) echo " checked"; ?>>&nbsp;
						</td>
						<td>
							<b>Enable Static ARP entries</b>
						</td>
					</tr>
					<tr>
						<td>
							&nbsp;
						</td>
						<td>
							<span class="red"><strong>Note:</strong></span> This feature is under development.  Only the machines listed below will be able to communicate with the firewall on this NIC.  Disabling this has been tested to be broken, a reboot will be required to disable.  Be warned!
						</td>
					</tr>
				</table>
			</td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%"> 
                          <input name="if" type="hidden" value="<?=$if;?>"> 
                          <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                        </td>
                      </tr>
                      <tr> 
                        <td width="22%" valign="top">&nbsp;</td>
                        <td width="78%"> <p><span class="vexpl"><span class="red"><strong>Note:<br>
                            </strong></span>The DNS servers entered in <a href="system.php">System: 
                            General setup</a> (or the <a href="services_dnsmasq.php">DNS 
                            forwarder</a>, if enabled) </span><span class="vexpl">will 
                            be assigned to clients by the DHCP server.<br>
                            <br>
                            The DHCP lease table can be viewed on the <a href="diag_dhcp_leases.php">Diagnostics: 
                            DHCP leases</a> page.<br>
                            </span></p>
			</td>
                      </tr>
                    </table>
              <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="35%" class="listhdrr">MAC address</td>
                  <td width="20%" class="listhdrr">IP address</td>
                  <td width="35%" class="listhdr">Description</td>
                  <td width="10%" class="list">
		  </td>
		</tr>
			  <?php $i = 0; foreach ($a_maps as $mapent): ?>
                <tr>
                  <td class="listlr" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <?=htmlspecialchars($mapent['mac']);?>
                  </td>
                  <td class="listr" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <?=htmlspecialchars($mapent['ipaddr']);?>&nbsp;
                  </td>
                  <td class="listbg" ondblclick="document.location='services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>';">
                    <font color="#FFFFFF"><?=htmlspecialchars($mapent['descr']);?>&nbsp;</font>
                  </td>
                  <td valign="middle" nowrap class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_dhcp_edit.php?if=<?=$if;?>&id=<?=$i;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0"></a></td>
                        <td valign="middle"><a href="services_dhcp.php?if=<?=$if;?>&act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this mapping?')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
		<?php $i++; endforeach; ?>
                <tr> 
                  <td class="list" colspan="3"></td>
                  <td class="list">
                    <table border="0" cellspacing="0" cellpadding="1">
                      <tr>
                        <td valign="middle"><a href="services_dhcp_edit.php?if=<?=$if;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a></td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
	</div>
    </td>
  </tr>
</table>
</form>
<script language="JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
