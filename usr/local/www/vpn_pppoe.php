<?php
/*
	vpn_pppoe.php
	part of pfSense
	
	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
##|*IDENT=page-services-pppoeserver
##|*NAME=Services: PPPoE Server page
##|*DESCR=Allow access to the 'Services: PPPoE Server' page.
##|*MATCH=vpn_pppoe.php*
##|-PRIV

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['pppoe']['radius'])) {
	$config['pppoe']['radius'] = array();
}
$pppoecfg = &$config['pppoe'];

$pconfig['remoteip'] = $pppoecfg['remoteip'];
$pconfig['localip'] = $pppoecfg['localip'];
$pconfig['mode'] = $pppoecfg['mode'];
$pconfig['interface'] = $pppoecfg['interface'];
$pconfig['n_pppoe_units'] = $pppoecfg['n_pppoe_units'];
$pconfig['pppoe_subnet'] = $pppoecfg['pppoe_subnet'];
$pconfig['pppoe_dns1'] = $pppoecfg['dns1'];
$pconfig['pppoe_dns2'] = $pppoecfg['dns2'];
$pconfig['radacct_enable'] = isset($pppoecfg['radius']['accounting']);
$pconfig['radiusissueips'] = isset($pppoecfg['radius']['radiusissueips']);
$pconfig['radiusenable'] = isset($pppoecfg['radius']['server']['enable']);
$pconfig['radiusserver'] = $pppoecfg['radius']['server']['ip'];
$pconfig['radiusserverport'] = $pppoecfg['radius']['server']['port'];
$pconfig['radiusserveracctport'] = $pppoecfg['radius']['server']['acctport'];
$pconfig['radiussecret'] = $pppoecfg['radius']['server']['secret'];
$pconfig['radiussecenable'] = isset($pppoecfg['radius']['server2']['enable']);
$pconfig['radiusserver2'] = $pppoecfg['radius']['server2']['ip'];
$pconfig['radiusserver2port'] = $pppoecfg['radius']['server2']['port'];
$pconfig['radiusserver2acctport'] = $pppoecfg['radius']['server2']['acctport'];
$pconfig['radiussecret2'] = $pppoecfg['radius']['server2']['secret2'];
$pconfig['radiusissueips'] = isset($pppoecfg['radius']['radiusissueips']);
$pconfig['radius_nasip'] = $pppoecfg['radius']['nasip'];
$pconfig['radius_acct_update'] = $pppoecfg['radius']['acct_update'];


if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = array(gettext("Server address"),gettext("Remote start address"));
		
		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn, 
				array(gettext("RADIUS server address"),gettext("RADIUS shared secret")));
		}
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) {
			$input_errors[] = gettext("A valid server address must be specified.");
		}
		if (($_POST['pppoe_subnet'] && !is_ipaddr($_POST['remoteip']))) {
			$input_errors[] = gettext("A valid remote start address must be specified.");
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");
		}
		
		if (!$input_errors) {	
			$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $_POST['pppoe_subnet']);
			$subnet_start = ip2ulong($_POST['remoteip']);
			$subnet_end = ip2ulong($_POST['remoteip']) + $_POST['pppoe_subnet'] - 1;
						
			if ((ip2ulong($_POST['localip']) >= $subnet_start) && 
			    (ip2ulong($_POST['localip']) <= $subnet_end)) {
				$input_errors[] = gettext("The specified server address lies in the remote subnet.");	
			}
			if ($_POST['localip'] == get_interface_ip("lan")) {
				$input_errors[] = gettext("The specified server address is equal to the LAN interface address.");	
			}
		}
	} else {
		/* turning pppoe off, lets dump any custom rules */
		$rules = &$config['filter']['rule'];
		for($x=0; $x<count($rules); $x++) {
			if($rules[$x]['interface'] == "pppoe") { 
				unset($rules[$x]);
			}
		}
		unset($config['pppoe']);
	}
	
	if (!$input_errors) {
		$pppoecfg['remoteip'] = $_POST['remoteip'];
		$pppoecfg['localip'] = $_POST['localip'];
		$pppoecfg['mode'] = $_POST['mode'];
		$pppoecfg['interface'] = $_POST['interface'];
		$pppoecfg['n_pppoe_units'] = $_POST['n_pppoe_units'];	
		$pppoecfg['pppoe_subnet'] = $_POST['pppoe_subnet'];
		$pppoecfg['radius']['server']['ip'] = $_POST['radiusserver'];
		$pppoecfg['radius']['server']['secret'] = $_POST['radiussecret'];
		$pppoecfg['radius']['server']['port'] = $_POST['radiusserverport'];
		$pppoecfg['radius']['server']['acctport'] = $_POST['radiusserveracctport'];
		$pppoecfg['radius']['server2']['ip'] = $_POST['radiusserver2'];
		$pppoecfg['radius']['server2']['secret2'] = $_POST['radiussecret2'];
		$pppoecfg['radius']['server2']['port'] = $_POST['radiusserver2port'];
		$pppoecfg['radius']['server2']['acctport'] = $_POST['radiusserver2acctport'];
		$pppoecfg['radius']['nasip'] = $_POST['radius_nasip'];
		$pppoecfg['radius']['acct_update'] = $_POST['radius_acct_update'];

 		if ($_POST['pppoe_dns1'] == "") 
        		unset($pppoecfg['dns1']);
		else
			$pppoecfg['dns1'] = $_POST['pppoe_dns1'];

 		if ($_POST['pppoe_dns2'] == "") 
        		unset($pppoecfg['dns2']);
		else
			$pppoecfg['dns2'] = $_POST['pppoe_dns2'];

		if($_POST['radiusenable'] == "yes")
			$pppoecfg['radius']['server']['enable'] = true;
		else
			unset($pppoecfg['radius']['server']['enable']);
			
		if($_POST['radiussecenable'] == "yes")
			$pppoecfg['radius']['server2']['enable'] = true;
		else
			unset($pppoecfg['radius']['server2']['enable']);
			
		if($_POST['radacct_enable'] == "yes")
			$pppoecfg['radius']['accounting'] = true;
		else
			unset($pppoecfg['radius']['accounting']);

		if($_POST['radiusissueips'] == "yes") {
			$pppoecfg['radius']['radiusissueips'] = true;
		} else
			unset($pppoecfg['radius']['radiusissueips']);

		write_config();
		
		$retval = 0;
		$retval = vpn_pppoe_configure();
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = array(gettext("Services"),gettext("PPPoE Server"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script language="JavaScript">
<!--
function get_radio_value(obj)
{
	for (i = 0; i < obj.length; i++) {
		if (obj[i].checked)
			return obj[i].value;
	}
	return null;
}

function enable_change(enable_over) {
	if ((get_radio_value(document.iform.mode) == "server") || enable_over) {
		document.iform.remoteip.disabled = 0;
		document.iform.localip.disabled = 0;
		document.iform.radiusenable.disabled = 0;
		document.iform.interface.disabled = 0;
		document.iform.n_pppoe_units.disabled = 0;		
		document.iform.pppoe_subnet.disabled = 0;		
		document.iform.pppoe_dns1.disabled = 0;
		document.iform.pppoe_dns2.disabled = 0;		
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiussecret.disabled = 0;
			document.iform.radiusserverport.disabled = 0;
			document.iform.radiusserveracctport.disabled = 0;
			document.iform.radiusissueips.disabled = 0;
			document.iform.radius_nasip.disabled = 0;
			document.iform.radiusissueips.disabled = 0;
			document.iform.radius_nasip.disabled = 0;
			document.iform.radius_acct_update.disabled = 0;
			document.iform.radiussecenable.disabled = 0;
			if (document.iform.radiussecenable.checked || enable_over) {
				document.iform.radiusserver2.disabled = 0;
				document.iform.radiussecret2.disabled = 0;
				document.iform.radiusserver2port.disabled = 0;
				document.iform.radiusserver2acctport.disabled = 0;
			} else {

				document.iform.radiusserver2.disabled = 1;
				document.iform.radiussecret2.disabled = 1;
				document.iform.radiusserver2port.disabled = 1;
				document.iform.radiusserver2acctport.disabled = 1;
			}
		} else {
			document.iform.radacct_enable.disabled = 1;
			document.iform.radiusserver.disabled = 1;
			document.iform.radiussecret.disabled = 1;
			document.iform.radiusserverport.disabled = 1;
			document.iform.radiusserveracctport.disabled = 1;
			document.iform.radiusissueips.disabled = 1;
			document.iform.radius_nasip.disabled = 1;
			document.iform.radius_acct_update.disabled = 1;
			document.iform.radiussecenable.disabled = 1;
		}
	} else {
		document.iform.interface.disabled = 1;
		document.iform.n_pppoe_units.disabled = 1;		
		document.iform.pppoe_subnet.disabled = 1;		
		document.iform.remoteip.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.pppoe_dns1.disabled = 1;
		document.iform.pppoe_dns2.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radiussecenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiussecret.disabled = 1;
		document.iform.radiusserverport.disabled = 1;
		document.iform.radiusserveracctport.disabled = 1;
		document.iform.radiusserver2.disabled = 1;
		document.iform.radiussecret2.disabled = 1;
		document.iform.radiusserver2port.disabled = 1;
		document.iform.radiusserver2acctport.disabled = 1;
		document.iform.radiusissueips.disabled = 1;
		document.iform.radius_nasip.disabled = 1;
		document.iform.radius_acct_update.disabled = 1;
	}
}
//-->
</script>
<form action="vpn_pppoe.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Configuration"), true, "vpn_pppoe.php");
	$tab_array[1] = array(gettext("Users"), false, "vpn_pppoe_users.php");
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
                    <input name="mode" type="radio" onclick="enable_change(false)" value="off"
				  	<?php if (($pconfig['mode'] != "server") && ($pconfig['mode'] != "redir")) echo "checked";?>>
                    <?=gettext("Off"); ?></td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
		    <input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked"; ?>>
                    <?=gettext("Enable PPPoE server"); ?></td>
		</tr>

                <tr> 
                  <td width="22%" valign="top" class="vncell"><b><?=gettext("Interface"); ?></b></td>
                  <td width="78%" valign="top" class="vtable">

			<select name="interface" class="formselect" id="interface">
			  <?php
				$interfaces = get_configured_interface_with_descr();

				foreach ($interfaces as $iface => $ifacename):
			  ?>
			  <option value="<?=$iface;?>" <?php if ($iface == $pconfig['interface']) echo "selected"; ?>>
			  <?=htmlspecialchars($ifacename);?>
			  </option>
			  <?php endforeach; ?>
			</select> <br>			
                      
		  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Subnet netmask"); ?></td>
                  <td width="78%" class="vtable">
		    <select id="pppoe_subnet" name="pppoe_subnet">
		    <?php
		     for($x=0; $x<33; $x++) {
			if($x == $pconfig['pppoe_subnet'])
				$SELECTED = " SELECTED";
			else
				$SELECTED = "";
			echo "<option value=\"{$x}\"{$SELECTED}>{$x}</option>\n";			
		     }
		    ?>
		    </select>
		    <br><?=gettext("Hint"); ?>: 24 <?=gettext("is"); ?> 255.255.255.0
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("No. PPPoE users"); ?></td>
                  <td width="78%" class="vtable">
		    <select id="n_pppoe_units" name="n_pppoe_units">
		    <?php
		     for($x=0; $x<255; $x++) {
			if($x == $pconfig['n_pppoe_units'])
				$SELECTED = " SELECTED";
			else
				$SELECTED = "";
			echo "<option value=\"{$x}\"{$SELECTED}>{$x}</option>\n";			
		     }
		    ?>
		    </select>
		    <br><?=gettext("Hint: 10 is TEN pppoe clients"); ?>
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Server address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="localip" type="text" class="formfld unknown" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"> 
                    <br>
                    <?=gettext("Enter the IP address the PPPoE server should use on its side " .
                    "for all clients"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Remote address range"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remoteip" type="text" class="formfld unknown" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip']);?>">
                    <br>
                    <?=gettext("Specify the starting address for the client IP address subnet"); ?>.<br>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("DNS servers"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="pppoe_dns1" type="text" class="formfld unknown" id="pppoe_dns1" size="20" value="<?=htmlspecialchars($pconfig['pppoe_dns1']);?>">
                    <br>
			<input name="pppoe_dns2" type="text" class="formfld unknown" id="pppoe_dns2" size="20" value="<?=htmlspecialchars($pconfig['pppoe_dns2']);?>">
                    <br>
                    <?=gettext("If entered they will be given to all PPPoE clients, else LAN DNS and one WAN DNS will go to all clients"); ?><br>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS"); ?></td>
                  <td width="78%" class="vtable"> 
                      <input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked"; ?>>
                      <strong><?=gettext("Use a RADIUS server for authentication"); ?><br>
                      </strong><?=gettext("When set, all users will be authenticated using " .
                      "the RADIUS server specified below. The local user database " .
                      "will not be used"); ?>.<br>
                      <br>
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked"; ?>>
                      <strong><?=gettext("Enable RADIUS accounting"); ?> <br>
			 <br>
                      </strong><?=gettext("Sends accounting packets to the RADIUS server"); ?>.<br>
                      <input name="radiussecenable" type="checkbox" id="radiussecenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiussecenable']) echo "checked"; ?>>
                      <strong><?=gettext("Use Backup Radius Server"); ?></strong><br>
                      <?=gettext("When set, if primary server fails all requests will be sent via backup server"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("NAS IP Address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="radius_nasip" type="text" class="formfld unknown" id="radius_nasip" size="20" value="<?=htmlspecialchars($pconfig['radius_nasip']);?>">
                    <br><?=gettext("radius server NAS ip Address"); ?><br>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("RADIUS Accounting Update"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="radius_acct_update" type="text" class="formfld unknown" id="radius_acct_update" size="20" value="<?=htmlspecialchars($pconfig['radius_acct_update']);?>">
                    <br><?=gettext("Radius accounting update period in seconds"); ?>
                    </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS issued IPs"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiusissueips" value="yes" type="checkbox" class="formfld" id="radiusissueips"<?php if($pconfig['radiusissueips']) echo " CHECKED"; ?>>
                      <br><?=gettext("Issue IP Addresses via RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS server Primary"); ?></td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver" type="text" class="formfld unknown" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver']);?>">
			 <input name="radiusserverport" type="text" class="formfld unknown" id="radiusserverport" size="4" value="<?=htmlspecialchars($pconfig['radiusserverport']);?>">
			 <input name="radiusserveracctport" type="text" class="formfld unknown" id="radiusserveracctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserveracctport']);?>">
                      <br><?=gettext("Enter the IP address and port of the RADIUS server. Format ip auth_port acct_port"); ?><br>
			 <br> <?=gettext("standard port 1812 and 1813 accounting"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS primary shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret" type="password" class="formfld pwd" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret']);?>">
                      <br><?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS server Secondary"); ?></td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver2" type="text" class="formfld unknown" id="radiusserver2" size="20" value="<?=htmlspecialchars($pconfig['radiusserver2']);?>">
			 <input name="radiusserver2port" type="text" class="formfld unknown" id="radiusserver2port" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2port']);?>">
			 <input name="radiusserver2acctport" type="text" class="formfld unknown" id="radiusserver2acctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2acctport']);?>">
                      <br><?=gettext("Enter the IP address and port of the BACKUP RADIUS server. Format ip auth_port acct_port"); ?><br>
			 <br> <?=gettext("standard port 1812 and 1813 accounting"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS secondary shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret2" type="password" class="formfld pwd" id="radiussecret2" size="20" value="<?=htmlspecialchars($pconfig['radiussecret2']);?>">
                      <br>
                      <?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td height="16" colspan="2" valign="top"></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note"); ?>:<br>
                    </strong></span><?=gettext("don't forget to add a firewall rule to permit " .
                    "traffic from PPPoE clients"); ?>!</span></td>
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
