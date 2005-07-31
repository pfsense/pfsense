#!/usr/local/bin/php
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

require("guiconfig.inc");

if (!is_array($config['pppoe']['radius'])) {
	$config['pppoe']['radius'] = array();
}
$pppoecfg = &$config['pppoe'];

$pconfig['remoteip'] = $pppoecfg['remoteip'];
$pconfig['localip'] = $pppoecfg['localip'];
$pconfig['mode'] = $pppoecfg['mode'];
$pconfig['interface'] = $pppoecfg['interface'];
$pconfig['wins'] = $pppoecfg['wins'];
$pconfig['req128'] = isset($pppoecfg['req128']);
$pconfig['radiusenable'] = isset($pppoecfg['radius']['enable']);
$pconfig['radacct_enable'] = isset($pppoecfg['radius']['accounting']);
$pconfig['radiusserver'] = $pppoecfg['radius']['server'];
$pconfig['radiussecret'] = $pppoecfg['radius']['secret'];

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($_POST['mode'] == "server") {
		$reqdfields = explode(" ", "localip remoteip");
		$reqdfieldsn = explode(",", "Server address,Remote start address");
		
		if ($_POST['radiusenable']) {
			$reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
			$reqdfieldsn = array_merge($reqdfieldsn, 
				explode(",", "RADIUS server address,RADIUS shared secret"));
		}
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['localip'] && !is_ipaddr($_POST['localip']))) {
			$input_errors[] = "A valid server address must be specified.";
		}
		if (($_POST['subnet'] && !is_ipaddr($_POST['remoteip']))) {
			$input_errors[] = "A valid remote start address must be specified.";
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = "A valid RADIUS server address must be specified.";
		}
		
		if (!$input_errors) {	
			$_POST['remoteip'] = $pconfig['remoteip'] = gen_subnet($_POST['remoteip'], $g['pppoe_subnet']);
			$subnet_start = ip2long($_POST['remoteip']);
			$subnet_end = ip2long($_POST['remoteip']) + $g['n_pppoe_units'] - 1;
						
			if ((ip2long($_POST['localip']) >= $subnet_start) && 
			    (ip2long($_POST['localip']) <= $subnet_end)) {
				$input_errors[] = "The specified server address lies in the remote subnet.";	
			}
			if ($_POST['localip'] == $config['interfaces']['lan']['ipaddr']) {
				$input_errors[] = "The specified server address is equal to the LAN interface address.";	
			}
		}
	}
	
	if (!$input_errors) {
		$pppoecfg['remoteip'] = $_POST['remoteip'];
		$pppoecfg['localip'] = $_POST['localip'];
		$pppoecfg['mode'] = $_POST['mode'];
		$pppoecfg['wins'] = $_POST['wins'];
		$pppoecfg['interface'] = $_POST['interface'];
		$pppoecfg['req128'] = $_POST['req128'] ? true : false;
		$pppoecfg['radius']['enable'] = $_POST['radiusenable'] ? true : false;
		$pppoecfg['radius']['accounting'] = $_POST['radacct_enable'] ? true : false;
		$pppoecfg['radius']['server'] = $_POST['radiusserver'];
		$pppoecfg['radius']['secret'] = $_POST['radiussecret'];

		write_config();
		
		$retval = 0;
		
		config_lock();
		$retval = vpn_pppoe_configure();
		config_unlock();
		
		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = "VPN: PPPoE";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
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
		document.iform.req128.disabled = 0;
		document.iform.radiusenable.disabled = 0;
		document.iform.wins.disabled = 0;
		
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiussecret.disabled = 0;
		} else {
			document.iform.radacct_enable.disabled = 1;
			document.iform.radiusserver.disabled = 1;
			document.iform.radiussecret.disabled = 1;
		}
	} else {
		document.iform.remoteip.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.req128.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiussecret.disabled = 1;
		document.iform.wins.disabled = 1;
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
	$tab_array[0] = array("Configuration", true, "vpn_pppoe.php");
	$tab_array[1] = array("Users", false, "vpn_pppoe_users.php");
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
                    Off</td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
		    <input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked"; ?>>
                    Enable PPPoE server</td>
		</tr>

                <tr> 
                  <td width="22%" valign="top" class="vncell"><b>Interface</b></td>
                  <td width="78%" valign="top" class="vtable">

			<select name="interface" class="formfld" id="interface">
			  <?php
				$interfaces = array('lan' => 'LAN', 'wan' => 'WAN');
				for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
				      if (isset($config['interfaces']['opt' . $i]['enable']))
					      $interfaces['opt' . $i] = $config['interfaces']['opt' . $i]['descr'];
				}
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
                  <td width="22%" valign="top" class="vncellreq">Max. concurrent connections</td>
                  <td width="78%" class="vtable"> 
                    <?=$g['n_pppoe_units'];?>
                  </td>
		</tr>

                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Server address</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="localip" type="text" class="formfld" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"> 
                    <br>
                    Enter the IP address the PPPoE server should use on its side 
                    for all clients.</td>
                </tr>

                <tr> 
                  <td width="22%" valign="top" class="vncellreq">Remote address range</td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remoteip" type="text" class="formfld" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip']);?>">
                    / 
                    <?=$g['pppoe_subnet'];?>
                    <br>
                    Specify the starting address for the client IP address subnet.<br>
                    The PPPoE server will assign 
                    <?=$g['n_pppoe_units'];?>
                    addresses, starting at the address entered above, to clients.</td>
                </tr>

                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS</td>
                  <td width="78%" class="vtable"> 
                      <input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked"; ?>>
                      <strong>Use a RADIUS server for authentication<br>
                      </strong>When set, all users will be authenticated using 
                      the RADIUS server specified below. The local user database 
                      will not be used.<br>
                      <br>
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked"; ?>>
                      <strong>Enable RADIUS accounting <br>
                      </strong>Sends accounting packets to the RADIUS server.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS server </td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver" type="text" class="formfld" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver']);?>">
                      <br>
                      Enter the IP address of the RADIUS server.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell">RADIUS shared secret</td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret" type="password" class="formfld" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret']);?>">
                      <br>
                      Enter the shared secret that will be used to authenticate 
                      to the RADIUS server.</td>
                </tr>
		


                <tr> 
                  <td height="16" colspan="2" valign="top"></td>
                </tr>

                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="Save" onclick="enable_change(true)"> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Note:<br>
                    </strong></span>don't forget to add a firewall rule to permit 
                    traffic from PPPoE clients!</span></td>
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
