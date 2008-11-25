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

##|+PRIV
##|*IDENT=page-system-gateways-editgateway
##|*NAME=System: Gateways: Edit Gateway page
##|*DESCR=Allow access to the 'System: Gateways: Edit Gateway' page.
##|*MATCH=system_gateways_edit.php*
##|-PRIV


require("guiconfig.inc");

$a_gateways = return_gateways_array();

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
}

if (isset($id) && $a_gateways[$id]) {
	$pconfig['name'] = $a_gateways[$id]['name'];
	$pconfig['interface'] = $a_gateways[$id]['interface'];
	$pconfig['gateway'] = $a_gateways[$id]['gateway'];
	$pconfig['defaultgw'] = $a_gateways[$id]['defaultgw'];
	$pconfig['monitor'] = $a_gateways[$id]['monitor'];
	$pconfig['descr'] = $a_gateways[$id]['descr'];
	$pconfig['attribute'] = $a_gateways[$id]['attribute'];
}

if (isset($_GET['dup'])) {
	unset($id);
	unset($pconfig['attribute']);
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "interface name gateway");
	$reqdfieldsn = explode(",", "Interface,Name,Gateway");		
	
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	/* Does this gateway name already exist? */
	foreach($config['gateways']['gateway_item'] as $gw) 
		if($gw['name'] == $_POST['name']) 
			$input_errors[] = "This gateway name already exists.";
	
	if (! isset($_POST['name'])) {
		$input_errors[] = "A valid gateway name must be specified.";
	}
	/* skip system gateways which have been automatically added */
	if ($_POST['gateway'] && (!is_ipaddr($_POST['gateway'])) && ($pconfig['attribute'] != "system")) {
		$input_errors[] = "A valid gateway IP address must be specified.";
	}
	if ((($_POST['monitor'] <> "") && !is_ipaddr($_POST['monitor']))) {
		$input_errors[] = "A valid monitor IP address must be specified.";
	}

	/* check for overlaps */
	foreach ($a_gateways as $gateway) {
		if (isset($id) && ($a_gateways[$id]) && ($a_gateways[$id] === $gateway))
			continue;

		if (($gateway['name'] <> "") && (in_array($gateway, $_POST['name']))) {
			$input_errors[] = "The name \"{$_POST['name']}\" already exists.";
			break;
		}
		if (($gateway['gateway'] <> "") && (in_array($gateway, $_POST['gateway']))) {
			$input_errors[] = "The IP address \"{$_POST['gateway']}\" already exists.";
			break;
		}
		if (($gateway['monitor'] <> "") && (in_array($gateway, $gateway['monitor']))) {
			$input_errors[] = "The IP address \"{$_POST['monitor']}\" already exists.";
			break;
		}
	}

	if (!$input_errors) {
		/* if we are processing a system gateway only save the monitorip */
		if($pconfig['attribute'] == "system") {
			$config['interfaces'][$_POST['interface']]['monitorip'] = $_POST['monitor'];
		}

		/* Manual gateways are handled differently */
		/* rebuild the array with the manual entries only */
		if (!is_array($config['gateways']['gateway_item']))
			$config['gateways']['gateway_item'] = array();

		$a_gateways = &$config['gateways']['gateway_item'];

		if ($pconfig['attribute'] != "system") {
			$gateway = array();
			$gateway['interface'] = $_POST['interface'];
			$gateway['name'] = $_POST['name'];
			$gateway['gateway'] = $_POST['gateway'];
			$gateway['descr'] = $_POST['descr'];

			if ($_POST['defaultgw'] == "yes") {
				$i = 0;
				foreach($a_gateways as $gw) {
					unset($config['gateways'][$i]['defaultgw']);
					$i++;
				}
				$gateway['defaultgw'] = true;
			} else {
				unset($gateway['defaultgw']);
			}

			/* when saving the manual gateway we use the attribute which has the corresponding id */
			$id = $pconfig['attribute'];
			if (isset($id) && $a_gateways[$id]) {
				$a_gateways[$id] = $gateway;
			} else {
				$a_gateways[] = $gateway;
			}
		}
		
		touch($d_staticroutesdirty_path);
		
		write_config();
		
		if($_REQUEST['isAjax']) {
			echo $_POST['name'];
			exit;
		}
		
		header("Location: system_gateways.php");
		exit;
	}
}

$pgtitle = array("System","Gateways","Edit gateway");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="system_gateways_edit.php" method="post" name="iform" id="iform">
	<?php
	/* If this is a automatically added system gateway we need this var */
	if(($pconfig['attribute'] == "system") || is_numeric($pconfig['attribute'])) {
		echo "<input type='hidden' name='attribute' id='attribute' value='{$pconfig['attribute']}' >\n";
	}
	?>
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">Edit gateway</td>
				</tr>	
                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Interface</td>
                  <td width="78%" class="vtable">
			<select name="interface" class="formselect">
                      <?php $interfaces = get_configured_interface_with_descr(false, true);
					  foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>> 
                      <?=htmlspecialchars($ifacename);?>
                      </option>
                      <?php 
						endforeach;
						if (is_package_installed("openbgpd") == 1) {
							echo "<option value=\"bgpd\"";
							if($pconfig['interface'] == "bgpd") 
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
                    <input name="gateway" type="text" class="formfld host" id="gateway" size="40" value="<?=htmlspecialchars($pconfig['gateway']);?>">
                    <br> <span class="vexpl">Gateway IP address</span></td>
                </tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Default Gateway</td>
		  <td width="78%" class="vtable">
			<input name="defaultgw" type="checkbox" id="defaultgw" value="yes" <?php if (isset($pconfig['defaultgw'])) echo "checked"; ?> onclick="enable_change(false)" />
			<strong>Default Gateway</strong><br />
			This will select the above gateway as the default gateway
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncell">Monitor IP</td>
		  <td width="78%" class="vtable">
			<input name="monitor" type="text" id="monitor" value="<?php echo ($pconfig['monitor']) ; ?>" />
			<strong>Alternative monitor IP</strong> <br />
			Enter a alternative address here to be used to monitor the link. This is used for the
			quality RRD graphs as well as the load balancer entries. Use this if the gateway does not respond
			to icmp requests.</strong>
			<br />
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
	enable_change();
</script>
</body>
</html>
