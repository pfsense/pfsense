<?php 
/* $Id$ */
/*
	system_gateways_edit.php
	part of pfSense (http://pfsense.com)
	
	Copyright (C) 2007 Seth Mos <seth.mos@xs4all.nl>.
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

$a_gateways = return_gateways_array();
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
	$pconfig['name'] = $a_gateways[$id]['name'];
	$pconfig['weight'] = $a_gateways[$id]['weight'];
	$pconfig['interface'] = $a_gateways[$id]['interface'];
	$pconfig['friendlyiface'] = $a_gateways[$id]['friendlyiface'];
	$pconfig['gateway'] = $a_gateways[$id]['gateway'];
	$pconfig['defaultgw'] = isset($a_gateways[$id]['defaultgw']);
	if (isset($a_gateways[$id]['dynamic']))
		$pconfig['dynamic'] = true;
	if($a_gateways[$id]['monitor'] <> "") {
		$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	} else {
		$pconfig['monitor'] == "";
	}
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
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (! isset($_POST['name'])) {
		$input_errors[] = "A valid gateway name must be specified.";
	}
	if (! is_validaliasname($_POST['name'])) {
		$input_errors[] = "The gateway name must not contain invalid characters.";
	}
	/* skip system gateways which have been automatically added */
	if (($_POST['gateway'] && (!is_ipaddr($_POST['gateway'])) && ($_POST['attribute'] != "system")) && ($_POST['gateway'] != "dynamic")) {
		$input_errors[] = "A valid gateway IP address must be specified.";
	}

	if ($_POST['gateway'] && (is_ipaddr($_POST['gateway'])) && ($pconfig['attribute'] != "system") && !$_REQUEST['isAjax']) {
		$parent_ip = get_interface_ip($_POST['interface']);
		if (is_ipaddr($parent_ip)) {
			$parent_sn = get_interface_subnet($_POST['interface']);
			if(!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn)) {
				$input_errors[] = "The gateway address {$_POST['gateway']} does not lie within the chosen interface's subnet.";
			}
		}
	}
	if (($_POST['monitor'] <> "") && !is_ipaddr($_POST['monitor']) && $_POST['monitor'] != "dynamic") {
		$input_errors[] = "A valid monitor IP address must be specified.";
	}

	if (isset($_POST['name'])) {
		/* check for overlaps */
		foreach ($a_gateways as $gateway) {
			if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway)) {
				continue;
			}
			if($_POST['name'] <> "") {
				if (($gateway['name'] <> "") && ($_POST['name'] == $gateway['name']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = "The gateway name \"{$_POST['name']}\" already exists.";
					break;
				}
			}
			if(is_ipaddr($_POST['gateway'])) {
				if (($gateway['gateway'] <> "") && ($_POST['gateway'] == $gateway['gateway']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = "The gateway IP address \"{$_POST['gateway']}\" already exists.";
					break;
				}
			}
			if(is_ipaddr($_POST['monitor'])) {
				if (($gateway['monitor'] <> "") && ($_POST['monitor'] == $gateway['monitor']) && ($gateway['attribute'] != "system")) {
					$input_errors[] = "The monitor IP address \"{$_POST['monitor']}\" is already in use. You must choose a different monitor IP.";
					break;
				}
			}
		}
	}

	if (!$input_errors) {
		$reloadif = false;
		/* if we are processing a system gateway only save the monitorip */
		if ($_POST['weight'] == 1 && (($_POST['attribute'] == "system" && empty($_POST['defaultgw'])) || (empty($_POST['interface']) && empty($_POST['gateway']) && empty($_POST['defaultgw'])))) {
			if (is_ipaddr($_POST['monitor'])) {
				if (empty($_POST['interface']))
					$interface = $pconfig['friendlyiface'];
				else
					$interface = $_POST['interface'];
				$config['interfaces'][$interface]['monitorip'] = $_POST['monitor'];
			}
			/* when dynamic gateway is not anymore a default the entry is no more needed. */
                        if (isset($id) && $a_gateway_item[$id]) {
                                unset($a_gateway_item[$id]);
                        }
		} else {

			/* Manual gateways are handled differently */
			/* rebuild the array with the manual entries only */

			$gateway = array();
			if ($_POST['attribute'] == "system") {
				$gateway['interface'] = $pconfig['friendlyiface'];
				$gateway['gateway'] = "dynamic";
			} else {
				$gateway['interface'] = $_POST['interface'];
				$gateway['gateway'] = $_POST['gateway'];
			}
			$gateway['name'] = $_POST['name'];
			$gateway['weight'] = $_POST['weight'];
			$gateway['descr'] = $_POST['descr'];
			if(is_ipaddr($_POST['monitor'])) {
				$gateway['monitor'] = $_POST['monitor'];
			} else {
				unset($gateway['monitor']);
			}			
			if ($_POST['defaultgw'] == "yes" or $_POST['defaultgw'] == "on") {
				$i = 0;
				foreach($a_gateway_item as $gw) {
					unset($config['gateways']['gateway_item'][$i]['defaultgw']);
					$i++;
				}
				$gateway['defaultgw'] = true;
				$reloadif = true;
			} else {
				unset($gateway['defaultgw']);
			}

			/* when saving the manual gateway we use the attribute which has the corresponding id */
			if (isset($id) && $a_gateway_item[$id]) {
				$a_gateway_item[$id] = $gateway;
			} else {
				$a_gateway_item[] = $gateway;
			}
		}
		system_resolvconf_generate();
		mark_subsystem_dirty('staticroutes');
		
		write_config();
		
		if($_REQUEST['isAjax']) {
			echo $_POST['name'];
			exit;
		} else if ($reloadif == true)
			interface_configure($_POST['interface']);
		
		header("Location: system_gateways.php");
		exit;
	}  else {
		$pconfig = $_POST;
		if (empty($_POST['friendlyiface']))
			$pconfig['friendlyiface'] = $_POST['interface'];
	}
}


$pgtitle = array("System","Gateways","Edit gateway");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
function enable_change(obj) {
	if (document.iform.gateway.disabled) {
		if (obj.checked)
			document.iform.interface.disabled=false;
		else
			document.iform.interface.disabled=true;
	}	
	
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
					<td colspan="2" valign="top" class="listtopic">Edit gateway</td>
				</tr>	
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
				  <select name="interface" class="formselect" <?php if ($pconfig['dynamic'] == true && $pconfig['attribute'] == "system") echo "disabled"; ?>>
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
				echo ">Use BGPD</option>";
			}
 		  ?>
                    </select> <br>
                    <span class="vexpl">Choose which interface this gateway applies to.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Name</td>
                  <td width="78%" class="vtable"> 
                    <input name="name" type="text" class="formfld unknown" id="name" size="20" value="<?=htmlspecialchars($pconfig['name']);?>"> 
                    <br> <span class="vexpl">Gateway name</span></td>
                </tr>
		<tr>
                  <td width="22%" valign="top" class="vncellreq">Gateway</td>
                  <td width="78%" class="vtable"> 
                    <input name="gateway" type="text" class="formfld host" id="gateway" size="40" value="<?php echo $pconfig['gateway']; ?>" <?php if ($pconfig['dynamic'] == true && $pconfig['attribute'] == "system") echo "disabled"; ?>>
                    <br> <span class="vexpl">Gateway IP address</span></td>
                </tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Default Gateway</td>
		  <td width="78%" class="vtable">
			<input name="defaultgw" type="checkbox" id="defaultgw" value="yes" <?php if ($pconfig['defaultgw'] == true) echo "checked"; ?> onclick="enable_change(this)" />
			<strong>Default Gateway</strong><br />
			This will select the above gateway as the default gateway
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Monitor IP</td>
		  <td width="78%" class="vtable">
			<?php
				if(is_numeric($pconfig['attribute']) && ($pconfig['gateway'] == dynamic) && ($pconfig['monitor'] == "")) {
					$monitor = "";
				} else {
					$monitor = htmlspecialchars($pconfig['monitor']);
				}
			?>
			<input name="monitor" type="text" id="monitor" value="<?php echo $monitor; ?>" />
			<strong>Alternative monitor IP</strong> <br />
			Enter an alternative address here to be used to monitor the link. This is used for the
			quality RRD graphs as well as the load balancer entries. Use this if the gateway does not respond
			to ICMP echo requests (pings).</strong>
			<br />
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Weight</td>
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
			<strong>Weight for this gateway when used in a Gateway Group.</strong> <br />
		   </td>
		</tr>
		<tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable"> 
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" class="formbtn"  onclick="history.back()">
                    <?php if (isset($id) && $a_gateways[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
<script language="JavaScript">
enable_change(document.iform.defaultgw);
</script>
</body>
</html>
