<?php 
/* $Id$ */
/*
	system_gateways_edit.php
	part of pfSense (http://pfsense.com)
	
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
##|*IDENT=page-system-gateways-editgateway
##|*NAME=System: Gateways: Edit Gateway page
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway' page.
##|*MATCH=system_gateways_edit.php*
##|-PRIV

require("guiconfig.inc");
require("pkg-utils.inc");

$a_gateways = return_gateways_array(true);
$a_gateways_arr = array();
foreach($a_gateways as $gw) {
	$a_gateways_arr[] = $gw;
}
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item']))
        $config['gateways']['gateway_item'] = array();
        
$a_gateway_item = &$config['gateways']['gateway_item'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

if (isset($id) && $a_gateways[$id]) {
	$pconfig = array();
	$pconfig['name'] = $a_gateways[$id]['name'];
	$pconfig['weight'] = $a_gateways[$id]['weight'];
	$pconfig['interval'] = $a_gateways[$id]['interval'];
	$pconfig['interface'] = $a_gateways[$id]['interface'];
	$pconfig['friendlyiface'] = $a_gateways[$id]['friendlyiface'];
	if (isset($a_gateways[$id]['dynamic']))
		$pconfig['dynamic'] = true;
	$pconfig['gateway'] = $a_gateways[$id]['gateway'];
	$pconfig['defaultgw'] = isset($a_gateways[$id]['defaultgw']);
	$pconfig['latencylow'] = $a_gateway_item[$id]['latencylow'];
        $pconfig['latencyhigh'] = $a_gateway_item[$id]['latencyhigh'];
        $pconfig['losslow'] = $a_gateway_item[$id]['losslow'];
        $pconfig['losshigh'] = $a_gateway_item[$id]['losshigh'];
        $pconfig['down'] = $a_gateway_item[$id]['down'];
	$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	$pconfig['monitor_disable'] = isset($a_gateways[$id]['monitor_disable']);
	$pconfig['descr'] = $a_gateways[$id]['descr'];
	$pconfig['attribute'] = $a_gateways[$id]['attribute'];
}

if (isset($_GET['dup'])) {
	unset($id);
	unset($pconfig['attribute']);
}

if ($_POST) {

	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "name interface");
	$reqdfieldsn = array(gettext("Name"), gettext("Interface"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (! isset($_POST['name'])) {
		$input_errors[] = "A valid gateway name must be specified.";
	}
	if (! is_validaliasname($_POST['name'])) {
		$input_errors[] = gettext("The gateway name must not contain invalid characters.");
	}
	/* skip system gateways which have been automatically added */
	if (($_POST['gateway'] && (!is_ipaddr($_POST['gateway'])) && ($_POST['attribute'] != "system")) && ($_POST['gateway'] != "dynamic")) {
		$input_errors[] = gettext("A valid gateway IP address must be specified.");
	}

	if ($_POST['gateway'] && (is_ipaddr($_POST['gateway'])) && !$_REQUEST['isAjax']) {
		if(is_ipaddrv4($_POST['gateway'])) {
			$parent_ip = get_interface_ip($_POST['interface']);
			$parent_sn = get_interface_subnet($_POST['interface']);
			if(empty($parent_ip) || empty($parent_sn)) {
				$input_errors[] = gettext("You can not use a IPv6 Gateway Address on a IPv4 only interface.");
			} else {
				$subnet = gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn;
				if(!ip_in_subnet($_POST['gateway'], $subnet))
					$input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within the chosen interface's subnet '%2\$s'."), $_POST['gateway'],$subnet);
			}
		}
		if(is_ipaddrv6($_POST['gateway'])) {
			$parent_ip = get_interface_ipv6($_POST['interface']);
			$parent_sn = get_interface_subnetv6($_POST['interface']);
			if(empty($parent_ip) || empty($parent_sn)) {
				$input_errors[] = gettext("You can not use a IPv4 Gateway Address on a IPv6 only interface.");
			} else {
				/* do not do a subnet match on a link local address, it's valid */
				if(! preg_match("/fe80::/", $_POST['gateway'])) {
					$subnet = gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn;
					if(!ip_in_subnet($_POST['gateway'], $subnet))
						$input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within the chosen interface's subnet '%2\$s'."), $_POST['gateway'],$subnet);
				}
			}
		}

		if (!empty($config['interfaces'][$_POST['interface']]['ipaddr'])) {
			if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddr']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic"))
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
		}
		if (!empty($config['interfaces'][$_POST['interface']]['ipaddrv6'])) {
			if (is_ipaddr($config['interfaces'][$_POST['interface']]['ipaddrv6']) && (empty($_POST['gateway']) || $_POST['gateway'] == "dynamic"))
				$input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv6 configuration.");
		}
	}
	if (($_POST['monitor'] <> "") && !is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		$input_errors[] = gettext("A valid monitor IP address must be specified.");
	}
	if (($_POST['monitor'] <> "") && is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		if(!validate_address_family($_POST['monitor'], $_POST['gateway'])) {
			$input_errors[] = gettext("The monitor address '{$_POST['monitor']}' is a different Address Family then gateway '{$_POST['gateway']}'.");
		}
	}

	if (isset($_POST['name'])) {
		/* check for overlaps */
		foreach ($a_gateways as $gateway) {
			if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway)) {
				if ($gateway['name'] != $_POST['name'])
					$input_errors[] = gettext("Changing name on a gateway is not allowed.");
				continue;
			}
			if($_POST['name'] <> "") {
				if (($gateway['name'] <> "") && ($_POST['name'] == $gateway['name']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = sprintf(gettext('The gateway name "%s" already exists.'), $_POST['name']);
					break;
				}
			}
			if(is_ipaddr($_POST['gateway'])) {
				if (($gateway['gateway'] <> "") && ($_POST['gateway'] == $gateway['gateway']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = sprintf(gettext('The gateway IP address "%s" already exists.'), $_POST['gateway']);
					break;
				}
			}
			if(is_ipaddr($_POST['monitor'])) {
				if (($gateway['monitor'] <> "") && ($_POST['monitor'] == $gateway['monitor']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. You must choose a different monitor IP.'), $_POST['monitor']);
					break;
				}
			}
		}
	}

	/* input validation */
        if($_POST['latencylow']) {
                if (! is_numeric($_POST['latencylow'])) {
                        $input_errors[] = gettext("The low latency watermark needs to be a numeric value.");
                }
        }

        if($_POST['latencyhigh']) {
                if (! is_numeric($_POST['latencyhigh'])) {
                        $input_errors[] = gettext("The high latency watermark needs to be a numeric value.");
                }
        }
        if($_POST['losslow']) {
                if (! is_numeric($_POST['losslow'])) {
                        $input_errors[] = gettext("The low loss watermark needs to be a numeric value.");
                }
        }
        if($_POST['losshigh']) {
                if (! is_numeric($_POST['losshigh'])) {
                        $input_errors[] = gettext("The high loss watermark needs to be a numeric value.");
                }
        }

        if(($_POST['latencylow']) && ($_POST['latencyhigh'])){
                if(($_POST['latencylow'] > $_POST['latencyhigh'])) {
                        $input_errors[] = gettext("The High latency watermark needs to be higher then the low latency watermark");
                }
        }

        if(($_POST['losslow']) && ($_POST['losshigh'])){
                if($_POST['losslow'] > $_POST['losshigh']) {
                        $input_errors[] = gettext("The High packet loss watermark needs to be higher then the low packet loss watermark");
                }
        }
	if($_POST['down']) {
                if (! is_numeric($_POST['down']) || $_POST['down'] < 1) {
                        $input_errors[] = gettext("The low latency watermark needs to be a numeric value.");
                }
        }

	if (!$input_errors) {
		$reloadif = "";
		$gateway = array();

		if (empty($_POST['interface']))
			$gateway['interface'] = $pconfig['friendlyiface'];
		else
			$gateway['interface'] = $_POST['interface'];
		if (is_ipaddr($_POST['gateway']))
			$gateway['gateway'] = $_POST['gateway'];
		else
			$gateway['gateway'] = "dynamic";
		$gateway['name'] = $_POST['name'];
		$gateway['weight'] = $_POST['weight'];
		$gateway['interval'] = $_POST['interval'];
		$gateway['descr'] = $_POST['descr'];
		if ($_POST['monitor_disable'] == "yes")
			$gateway['monitor_disable'] = true;
		else if (is_ipaddr($_POST['monitor']))
			$gateway['monitor'] = $_POST['monitor'];

		if ($_POST['defaultgw'] == "yes" || $_POST['defaultgw'] == "on") {
			$i = 0;
			/* remove the default gateway bits for all gateways with the same address family */
			foreach($a_gateway_item as $gw) {
				if(is_ipaddrv4($gateway['gateway']) && is_ipaddrv4($gw['gateway'])) {
					unset($config['gateways']['gateway_item'][$i]['defaultgw']);
					if ($gw['interface'] != $_POST['interface'] && $gw['defaultgw'])
						$reloadif = $gw['interface'];
				}
				if(is_ipaddrv6($gateway['gateway']) && is_ipaddrv6($gw['gateway'])) {
					unset($config['gateways']['gateway_item'][$i]['defaultgw']);
					if ($gw['interface'] != $_POST['interface'] && $gw['defaultgw'])
						$reloadif = $gw['interface'];
				}
				$i++;
			}
			$gateway['defaultgw'] = true;
		}

		if ($_POST['latencylow'])
			$gateway['latencylow'] = $_POST['latencylow'];
		if ($_POST['latencyhigh'])
               		$gateway['latencyhigh'] = $_POST['latencyhigh'];
		if ($_POST['losslow'])
              			$gateway['losslow'] = $_POST['losslow'];
		if ($_POST['losshigh'])
               		$gateway['losshigh'] = $_POST['losshigh'];
		if ($_POST['down'])
               		$gateway['down'] = $_POST['down'];

		/* when saving the manual gateway we use the attribute which has the corresponding id */
		if (isset($id) && $a_gateway_item[$id])
			$a_gateway_item[$id] = $gateway;
		else
			$a_gateway_item[] = $gateway;

		mark_subsystem_dirty('staticroutes');
	
		write_config();

		if($_REQUEST['isAjax']) {
			echo $_POST['name'];
			exit;
		} else if (!empty($reloadif))
			send_event("interface reconfigure {$reloadif}");
		
		header("Location: system_gateways.php");
		exit;
	} else {
		$pconfig = $_POST;
		if (empty($_POST['friendlyiface']))
			$pconfig['friendlyiface'] = $_POST['interface'];
	}
}


$pgtitle = array(gettext("System"),gettext("Gateways"),gettext("Edit gateway"));
$statusurl = "status_gateways.php";

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
function show_advanced_gateway() {
        document.getElementById("showadvgatewaybox").innerHTML='';
        aodiv = document.getElementById('showgatewayadv');
        aodiv.style.display = "block";
}
function monitor_change() {
        document.iform.monitor.disabled = document.iform.monitor_disable.checked;
}
</script>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="system_gateways_edit.php" method="post" name="iform" id="iform">
	<?php

	/* If this is a system gateway we need this var */
	if(($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
		echo "<input type='hidden' name='attribute' id='attribute' value='{$pconfig['attribute']}' >\n";
	}
	echo "<input type='hidden' name='friendlyiface' id='friendlyiface' value='{$pconfig['friendlyiface']}' >\n";
	?>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit gateway"); ?></td>
				</tr>	
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
                  <td width="78%" class="vtable">
		 	<select name='interface' class='formselect' >

		<?php 
                      	$interfaces = get_configured_interface_with_descr(false, true);
			foreach ($interfaces as $iface => $ifacename) {
				echo "<option value=\"{$iface}\"";
				if ($iface == $pconfig['friendlyiface'])
					echo " selected";
				echo ">" . htmlspecialchars($ifacename) . "</option>";
			}
			if (is_package_installed("openbgpd") == 1) {
				echo "<option value=\"bgpd\"";
				if ($pconfig['interface'] == "bgpd") 
					echo " selected";
				echo ">" . gettext("Use BGPD") . "</option>";
			}
 		  ?>
                    </select> <br>
                    <span class="vexpl"><?=gettext("Choose which interface this gateway applies to."); ?></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Name"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"> 
                    <br> <span class="vexpl"><?=gettext("Gateway name"); ?></span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="gateway" type="text" class="formfld host" id="gateway" size="28" value="<?php if ($pconfig['dynamic']) echo "dynamic"; else echo $pconfig['gateway']; ?>">
                    <br> <span class="vexpl"><?=gettext("Gateway IP address"); ?></span></td>
                </tr>
		<tr>
		  <td width="22%" valign="top" class="vncell"><?=gettext("Default Gateway"); ?></td>
		  <td width="78%" class="vtable">
			<input name="defaultgw" type="checkbox" id="defaultgw" value="yes" <?php if ($pconfig['defaultgw'] == true) echo "checked"; ?> />
			<strong><?=gettext("Default Gateway"); ?></strong><br />
			<?=gettext("This will select the above gateway as the default gateway"); ?>
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell"><?=gettext("Disable Gateway Monitoring"); ?></td>
		  <td width="78%" class="vtable">
			<input name="monitor_disable" type="checkbox" id="monitor_disable" value="yes" <?php if ($pconfig['monitor_disable'] == true) echo "checked"; ?> onClick="monitor_change()" />
			<strong><?=gettext("Disable Gateway Monitoring"); ?></strong><br />
			<?=gettext("This will consider this gateway as always being up"); ?>
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell"><?=gettext("Monitor IP"); ?></td>
		  <td width="78%" class="vtable">
			<?php
				if ($pconfig['gateway'] == $pconfig['monitor'])
					$monitor = "";
				else
					$monitor = htmlspecialchars($pconfig['monitor']);
			?>
			<input name="monitor" type="text" id="monitor" value="<?php echo $monitor; ?>" size="28" />
			<strong><?=gettext("Alternative monitor IP"); ?></strong> <br />
			<?=gettext("Enter an alternative address here to be used to monitor the link. This is used for the " .
			"quality RRD graphs as well as the load balancer entries. Use this if the gateway does not respond " .
			"to ICMP echo requests (pings)"); ?>.</strong>
			<br />
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell"><?=gettext("Advanced");?></td>
		  <td width="78%" class="vtable">
			<?php $showbutton = (!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) || !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) || (isset($pconfig['weight']) && $pconfig['weight'] > 1) || (isset($pconfig['interval']) && $pconfig['interval'])); ?>
			<div id="showadvgatewaybox" <? if ($showbutton) echo "style='display:none'"; ?>>
				<input type="button" onClick="show_advanced_gateway()" value="Advanced"></input> - Show advanced option</a>
			</div>
			<div id="showgatewayadv" <? if (!$showbutton) echo "style='display:none'"; ?>>
                        <table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tr>
                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Weight");?></td>
                                <td width="78%" class="vtable">
					<select name='weight' class='formfldselect' id='weight'>
				<?php
					for ($i = 1; $i < 6; $i++) {
                                        	$selected = "";
                                        	if ($pconfig['weight'] == $i)
                                                	$selected = "selected";
                                        	echo "<option value='{$i}' {$selected} >{$i}</option>";
                                	}
				?>
					</select>
					<br /><?=gettext("Weight for this gateway when used in a Gateway Group.");?> <br />
		   		</td>
			</tr>
                        <tr>
                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Latency thresholds");?></td>
                                <td width="78%" class="vtable">
                                <?=gettext("From");?>
                                    <input name="latencylow" type="text" class="formfld unknown" id="latencylow" size="2"
                                        value="<?=htmlspecialchars($pconfig['latencylow']);?>">
                                <?=gettext("To");?>
                                    <input name="latencyhigh" type="text" class="formfld unknown" id="latencyhigh" size="2"
                                        value="<?=htmlspecialchars($pconfig['latencyhigh']);?>">
                                    <br> <span class="vexpl"><?=gettext("These define the low and high water marks for latency in milliseconds. Default is 100/200.");?></span></td>
                                </td>
                        </tr>
                        <tr>
                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Packet Loss thresholds");?></td>
                                <td width="78%" class="vtable">
                                <?=gettext("From");?>
                                    <input name="losslow" type="text" class="formfld unknown" id="losslow" size="2"
                                        value="<?=htmlspecialchars($pconfig['losslow']);?>">
                                <?=gettext("To");?>
                                    <input name="losshigh" type="text" class="formfld unknown" id="losshigh" size="2"
                                        value="<?=htmlspecialchars($pconfig['losshigh']);?>">
                                    <br> <span class="vexpl"><?=gettext("These define the low and high water marks for packet loss in %. Default is 10/20.");?></span></td>
                                </td>
                        </tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq"><?=gettext("Frequency Probe");?></td>
				<td width="78%" class="vtable">
					<input name="interval" type="text" class="formfld unknown" id="interval" size="2"
						value="<?=htmlspecialchars($pconfig['interval']);?>">
					<br><span class="vexpl">
						<?=gettext("This defines how often that an icmp probe will be sent in seconds. Default is 1.");?><br/><br/>
						<?=gettext("NOTE: The quality graph is averaged over seconds, not intervals, so as the frequency probe is increased the accuracy of the quality graph is decreased.");?>
					</span></td>
				</td>
			</tr>
			<tr>
				<td width="22%" valign="top" class="vncellreq"><?=gettext("Down");?></td>
				<td width="78%" class="vtable">
					<input name="down" type="text" class="formfld unknown" id="down" size="2"
						value="<?=htmlspecialchars($pconfig['down']);?>">
					<br> <span class="vexpl"><?=gettext("This defines the number of bad probes before the alarm will fire. Default is 10.");?></span></td>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?= gettext("NOTE: The total time before a gateway is down is the product of the Frequency Probe and the Down fields. By default this is 1*10=10 seconds."); ?><br/>
					<?php if (is_numeric($pconfig['interval']) || is_numeric($pconfig['down'])) {
						echo "<br/>";
						$interval = is_numeric($pconfig['interval']) ? $pconfig['interval'] : 1;
						$down = is_numeric($pconfig['down']) ? $pconfig['down'] : 10;
						echo gettext(sprintf("With the current configuration, the total time before this gateway would be considered down would be: %d*%d=%d seconds.", $interval, $down, $interval*$down));
					} ?>
				</td>
			</tr>
                        </table>
			</div>
		   </td>
		</tr>
		<tr>
                  <td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed)"); ?>.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>"> <input type="button" value="<?=gettext("Cancel");?>" class="formbtn"  onclick="history.back()">
                    <?php if (isset($id) && $a_gateways[$id]): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
<script language="JavaScript">
monitor_change();
</script>
</body>
</html>
