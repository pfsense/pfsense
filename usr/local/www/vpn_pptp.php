<?php
/*
	vpn_pptp.php
	part of m0n0wall (http://m0n0.ch/wall)
	
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

##|+PRIV
##|*IDENT=page-vpn-vpnpptp
##|*NAME=VPN: VPN PPTP page
##|*DESCR=Allow access to the 'VPN: VPN PPTP' page.
##|*MATCH=vpn_pptp.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("vpn.inc");

if (!is_array($config['pptpd']['radius'])) {
	$config['pptpd']['radius'] = array();
}
$pptpcfg = &$config['pptpd'];

$pconfig['remoteip'] = $pptpcfg['remoteip'];
$pconfig['localip'] = $pptpcfg['localip'];
$pconfig['redir'] = $pptpcfg['redir'];
$pconfig['mode'] = $pptpcfg['mode'];
$pconfig['wins'] = $pptpcfg['wins'];
$pconfig['req128'] = isset($pptpcfg['req128']);
$pconfig['n_pptp_units'] = $pptpcfg['n_pptp_units'];
$pconfig['pptp_dns1'] = $pptpcfg['dns1'];
$pconfig['pptp_dns2'] = $pptpcfg['dns2'];
$pconfig['radiusenable'] = isset($pptpcfg['radius']['server']['enable']);
$pconfig['radiusissueips'] = isset($pptpcfg['radius']['radiusissueips']);
$pconfig['radiussecenable'] = isset($pptpcfg['radius']['server2']['enable']);
$pconfig['radacct_enable'] = isset($pptpcfg['radius']['accounting']);
$pconfig['radiusserver'] = $pptpcfg['radius']['server']['ip'];
$pconfig['radiusserverport'] = $pptpcfg['radius']['server']['port'];
$pconfig['radiusserveracctport'] = $pptpcfg['radius']['server']['acctport'];
$pconfig['radiussecret'] = $pptpcfg['radius']['server']['secret'];
$pconfig['radiusserver2'] = $pptpcfg['radius']['server2']['ip'];
$pconfig['radiusserver2port'] = $pptpcfg['radius']['server2']['port'];
$pconfig['radiusserver2acctport'] = $pptpcfg['radius']['server2']['acctport'];
$pconfig['radiussecret2'] = $pptpcfg['radius']['server2']['secret2'];
$pconfig['radius_acct_update'] = $pptpcfg['radius']['acct_update'];
$pconfig['radius_nasip'] = $pptpcfg['radius']['nasip'];

if ($_POST) {

	if (isset($input_errors))
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
		if (is_ipaddr_configured($_POST['localip'])) {
			$input_errors[] = gettext("'Server address' parameter should NOT be set to any IP address currently in use on this firewall.");
		}
		if (!is_ipaddr($_POST['remoteip'])) {
			$input_errors[] = gettext("A valid remote start address must be specified.");
		}
		if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
			$input_errors[] = gettext("A valid RADIUS server address must be specified.");
		}
		
		if (!$input_errors) {	
			$subnet_start = ip2ulong($_POST['remoteip']);
			$subnet_end = ip2ulong($_POST['remoteip']) + $_POST['n_pptp_units'] - 1;
						
			if ((ip2ulong($_POST['localip']) >= $subnet_start) && 
			    (ip2ulong($_POST['localip']) <= $subnet_end)) {
				$input_errors[] = gettext("The specified server address lies in the remote subnet.");	
			}
			// TODO: Should this check be for any local IP address?
			if ($_POST['localip'] == $config['interfaces']['lan']['ipaddr']) {
				$input_errors[] = gettext("The specified server address is equal to the LAN interface address.");	
			}
		}
	} else if ($_POST['mode'] == "redir") {
		$reqdfields = explode(" ", "redir");
		$reqdfieldsn = array(gettext("PPTP redirection target address"));
		
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
		
		if (($_POST['redir'] && !is_ipaddr($_POST['redir']))) {
			$input_errors[] = gettext("A valid target address must be specified.");
		}
	} else if (isset($config['pptpd']['mode'])) {
		unset($config['pptpd']['mode']);
	}

	if (!$input_errors) {
		$pptpcfg['remoteip'] = $_POST['remoteip'];
		$pptpcfg['redir'] = $_POST['redir'];
		$pptpcfg['localip'] = $_POST['localip'];
		$pptpcfg['mode'] = $_POST['mode'];
		$pptpcfg['wins'] = $_POST['wins'];
		$pptpcfg['n_pptp_units'] = $_POST['n_pptp_units'];	
		$pptpcfg['radius']['server']['ip'] = $_POST['radiusserver'];
		$pptpcfg['radius']['server']['port'] = $_POST['radiusserverport'];
		$pptpcfg['radius']['server']['acctport'] = $_POST['radiusserveracctport'];
		$pptpcfg['radius']['server']['secret'] = $_POST['radiussecret'];
		$pptpcfg['radius']['server2']['ip'] = $_POST['radiusserver2'];
		$pptpcfg['radius']['server2']['port'] = $_POST['radiusserver2port'];
		$pptpcfg['radius']['server2']['acctport'] = $_POST['radiusserver2acctport'];
		$pptpcfg['radius']['server2']['secret2'] = $_POST['radiussecret2'];
		$pptpcfg['radius']['nasip'] = $_POST['radius_nasip'];
		$pptpcfg['radius']['acct_update'] = $_POST['radius_acct_update'];

 		if ($_POST['pptp_dns1'] == "") {
			if (isset($pptpcfg['dns1']))
        			unset($pptpcfg['dns1']);
		} else
			$pptpcfg['dns1'] = $_POST['pptp_dns1'];

 		if ($_POST['pptp_dns2'] == "") {
			if (isset($pptpcfg['dns2']))
        			unset($pptpcfg['dns2']);
		} else
			$pptpcfg['dns2'] = $_POST['pptp_dns2'];

		if($_POST['req128'] == "yes") 
			$pptpcfg['req128'] = true;
		else if (isset($pptpcfg['req128']))
			unset($pptpcfg['req128']);

		if($_POST['radiusenable'] == "yes") 
			$pptpcfg['radius']['server']['enable'] = true;
		else if (isset($pptpcfg['radius']['server']['enable']))
			unset($pptpcfg['radius']['server']['enable']);
			
		if($_POST['radiussecenable'] == "yes") 
			$pptpcfg['radius']['server2']['enable'] = true;
		else if (isset($pptpcfg['radius']['server2']['enable']))
			unset($pptpcfg['radius']['server2']['enable']);
			
		if($_POST['radacct_enable'] == "yes") 
			$pptpcfg['radius']['accounting'] = true;
		else if (isset($pptpcfg['radius']['accounting']))
			unset($pptpcfg['radius']['accounting']);
		
		if($_POST['radiusissueips'] == "yes") {
			$pptpcfg['radius']['radiusissueips'] = true;
		} else if (isset($pptpcfg['radius']['radiusissueips']))
			unset($pptpcfg['radius']['radiusissueips']);
		
		write_config();
		
		$retval = 0;
		$retval = vpn_pptpd_configure();
		$savemsg = get_std_save_message($retval);
		
		filter_configure();
	}
}

$pgtitle = array(gettext("VPN"),gettext("VPN PPTP"));
$shortcut_section = "pptps";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/JavaScript">
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
		document.iform.radiusissueips.disabled = 0;
		document.iform.wins.disabled = 0;
		document.iform.n_pptp_units.disabled = 0;
		document.iform.pptp_dns1.disabled = 0;
		document.iform.pptp_dns2.disabled = 0;	
		
		if (document.iform.radiusenable.checked || enable_over) {
			document.iform.radiussecenable.disabled = 0;
			document.iform.radacct_enable.disabled = 0;
			document.iform.radiusserver.disabled = 0;
			document.iform.radiusserverport.disabled = 0;
			document.iform.radiusserveracctport.disabled = 0;
			document.iform.radiussecret.disabled = 0;
			document.iform.radius_nasip.disabled = 0;	
			document.iform.radius_acct_update.disabled = 0;	
			document.iform.radiusissueips.disabled = 0;		
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
			document.iform.radiusserverport.disabled = 1;
			document.iform.radiusissueips.disabled = 1;
			document.iform.radiusserveracctport.disabled = 1;
			document.iform.radiussecret.disabled = 1;
			document.iform.radius_nasip.disabled = 1;	
			document.iform.radius_acct_update.disabled = 1;	
			document.iform.radiusissueips.disabled = 1;
			document.iform.radiusserver2.disabled = 1;
			document.iform.radiussecret2.disabled = 1;
			document.iform.radiusserver2port.disabled = 1;
			document.iform.radiusserver2acctport.disabled = 1;
		}

	} else {
		document.iform.remoteip.disabled = 1;
		document.iform.localip.disabled = 1;
		document.iform.req128.disabled = 1;
		document.iform.n_pptp_units.disabled = 1;
		document.iform.pptp_dns1.disabled = 1;
		document.iform.pptp_dns2.disabled = 1;
		document.iform.radiusenable.disabled = 1;
		document.iform.radacct_enable.disabled = 1;
		document.iform.radiusserver.disabled = 1;
		document.iform.radiusserverport.disabled = 1;
		document.iform.radiusissueips.disabled = 1;
		document.iform.radiusserveracctport.disabled = 1;
		document.iform.radiussecret.disabled = 1;
		document.iform.radius_nasip.disabled = 1;	
		document.iform.radius_acct_update.disabled = 1;
		document.iform.radiussecenable.disabled = 1;
		document.iform.radiusserver2.disabled = 1;
		document.iform.radiusserver2port.disabled = 1;
		document.iform.radiusserver2acctport.disabled = 1;
		document.iform.radiussecret2.disabled = 1;	
		document.iform.wins.disabled = 1;
		document.iform.radiusissueips.disabled = 1;
	}
	if ((get_radio_value(document.iform.mode) == "redir") || enable_over) {
		document.iform.redir.disabled = 0;
	} else {
		document.iform.redir.disabled = 1;
	}
}
//-->
</script>
<form action="vpn_pptp.php" method="post" name="iform" id="iform">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php print_info_box(gettext("PPTP is no longer considered a secure VPN technology because it relies upon MS-CHAPv2 which has been compromised. If you continue to use PPTP be aware that intercepted traffic can be decrypted by a third party, so it should be considered unencrypted. We advise migrating to another VPN type such as OpenVPN or IPsec.<br/><br/><a href=\"https://isc.sans.edu/diary/End+of+Days+for+MS-CHAPv2/13807\">Read More</a>")); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Configuration"), true, "vpn_pptp.php");
	$tab_array[1] = array(gettext("Users"), false, "vpn_pptp_users.php");
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
				  	<?php if (($pconfig['mode'] != "server") && ($pconfig['mode'] != "redir")) echo "checked=\"checked\"";?>/>
                    <?=gettext("Off"); ?></td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">

			<input type="radio" name="mode" value="redir" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "redir") echo "checked=\"checked\"" ?>/>
                    <?=gettext("Redirect incoming PPTP connections to");?>:</td>
                </tr>
				<tr>
				  <td width="22%" valign="top" class="vncellreq"><?=gettext("PPTP redirection");?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="redir" type="text" class="formfld unknown" id="redir" size="20" value="<?=htmlspecialchars($pconfig['redir']);?>"/> 
                    <br />
                    <?=gettext("Enter the IP address of a host which will accept incoming " .
                    "PPTP connections"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vtable">&nbsp;</td>
                  <td width="78%" class="vtable">
			<input type="radio" name="mode" value="server" onclick="enable_change(false)" <?php if ($pconfig['mode'] == "server") echo "checked=\"checked\""; ?>/>
                    <?=gettext("Enable PPTP server"); ?></td>
                </tr>
				<tr>
					<td width="22%" valign="top" class="vncellreq"><?=gettext("No. PPTP users"); ?></td>
					<td width="78%" class="vtable">
						<select id="n_pptp_units" name="n_pptp_units">
							<?php
								$toselect = ($pconfig['n_pptp_units'] > 0) ? $pconfig['n_pptp_units'] : 16;
								for($x=1; $x<255; $x++) {
									if($x == $toselect)
										$selected = "selected=\"selected\"";
									else
										$selected = "";
									echo "<option value=\"{$x}\" {$selected}>{$x}</option>\n";
								}
							?>
						</select>
						<br /><?=gettext("Hint: 10 is ten PPTP clients"); ?>
					</td>
				</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Server address"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="localip" type="text" class="formfld unknown" id="localip" size="20" value="<?=htmlspecialchars($pconfig['localip']);?>"/> 
			<br/>
			<?=gettext("Enter the IP address the PPTP server should give to clients for use as their \"gateway\""); ?>.
			<br/>
			<?=gettext("Typically this is set to an unused IP just outside of the client range"); ?>.
			<br/>
			<br/>
			<?=gettext("NOTE: This should NOT be set to any IP address currently in use on this firewall"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncellreq"><?=gettext("Remote address " .
                    "range"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="remoteip" type="text" class="formfld unknown" id="remoteip" size="20" value="<?=htmlspecialchars($pconfig['remoteip']);?>"/>
                    <br />
                    <?=gettext("Specify the starting address for the client IP subnet"); ?>.<br />
					</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("PPTP DNS Servers"); ?></td>
                  <td width="78%" class="vtable"> 
                    <?=$mandfldhtml;?><input name="pptp_dns1" type="text" class="formfld unknown" id="pptp_dns1" size="20" value="<?=htmlspecialchars($pconfig['pptp_dns1']);?>"/>
                    <br />
					<input name="pptp_dns2" type="text" class="formfld unknown" id="pptp_dns2" size="20" value="<?=htmlspecialchars($pconfig['pptp_dns2']);?>"/>
                    <br />
                   <?=gettext("primary and secondary DNS servers assigned to PPTP clients"); ?><br />
				</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("WINS Server"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="wins" class="formfld unknown" id="wins" size="20" value="<?=htmlspecialchars($pconfig['wins']);?>"/>
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS"); ?></td>
                  <td width="78%" class="vtable"> 
                      <input name="radiusenable" type="checkbox" id="radiusenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiusenable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Use a RADIUS server for authentication"); ?></strong><br />
                      <?=gettext("When set, all users will be authenticated using " .
                      "the RADIUS server specified below. The local user database " .
                      "will not be used"); ?>.<br />
                      <br />
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radacct_enable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Enable RADIUS accounting"); ?> <br />
                      </strong><?=gettext("Sends accounting packets to the RADIUS server"); ?>.<br />
			 <br />
                      <input name="radiussecenable" type="checkbox" id="radiussecenable" onclick="enable_change(false)" value="yes" <?php if ($pconfig['radiussecenable']) echo "checked=\"checked\""; ?>/>
                      <strong><?=gettext("Secondary RADIUS server for failover authentication"); ?></strong><br />
                      <?=gettext("When set, all requests will go to the secondary server when primary fails"); ?><br />
		      <br />
                      <input name="radiusissueips" value="yes" type="checkbox" class="formfld" id="radiusissueips" <?php if($pconfig['radiusissueips']) echo "checked=\"checked\""; ?>/>
		      <strong><?=gettext("RADIUS issued IPs"); ?></strong>
                      <br /><?=gettext("Issue IP addresses via RADIUS server"); ?>.
		      </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS NAS IP"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radius_nasip" class="formfld unknown" id="radius_nasip" size="20" value="<?=htmlspecialchars($pconfig['radius_nasip']);?>"/>
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS Accounting Update"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radius_acct_update" class="formfld unknown" id="radius_acct_update" size="20" value="<?=htmlspecialchars($pconfig['radius_acct_update']);?>"/>
                  </td>
		</tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS Server"); ?> </td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver" type="text" class="formfld unknown" id="radiusserver" size="20" value="<?=htmlspecialchars($pconfig['radiusserver']);?>"/>
                      <input name="radiusserverport" type="text" class="formfld unknown" id="radiusserverport" size="4" value="<?=htmlspecialchars($pconfig['radiusserverport']);?>"/>
                      <input name="radiusserveracctport" type="text" class="formfld unknown" id="radiusserveracctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserveracctport']);?>"/>
                      <br />
                      <?=gettext("Enter the IP address, RADIUS port, and RADIUS accounting port of the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("RADIUS shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret" type="password" class="formfld pwd" id="radiussecret" size="20" value="<?=htmlspecialchars($pconfig['radiussecret']);?>"/>
                      <br />
                      <?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Secondary RADIUS server"); ?> </td>
                  <td width="78%" class="vtable">
                      <input name="radiusserver2" type="text" class="formfld unknown" id="radiusserver2" size="20" value="<?=htmlspecialchars($pconfig['radiusserver2']);?>"/>
                      <input name="radiusserver2port" type="text" class="formfld unknown" id="radiusserver2port" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2port']);?>"/>
                      <input name="radiusserver2acctport" type="text" class="formfld unknown" id="radiusserver2acctport" size="4" value="<?=htmlspecialchars($pconfig['radiusserver2acctport']);?>"/>
                      <br />
                      <?=gettext("Enter the IP address, RADIUS port, and RADIUS accounting port of the RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top" class="vncell"><?=gettext("Secondary RADIUS shared secret"); ?></td>
                  <td width="78%" valign="top" class="vtable">
                      <input name="radiussecret2" type="password" class="formfld pwd" id="radiussecret2" size="20" value="<?=htmlspecialchars($pconfig['radiussecret2']);?>"/>
                      <br />
                      <?=gettext("Enter the shared secret that will be used to authenticate " .
                      "to the secondary RADIUS server"); ?>.</td>
                </tr>
                <tr> 
                  <td height="16" colspan="2" valign="top"></td>
                </tr>
                <tr> 
                  <td width="22%" valign="middle">&nbsp;</td>
                  <td width="78%" class="vtable"> 
                    <input name="req128" type="checkbox" id="req128" value="yes" <?php if ($pconfig['req128']) echo "checked=\"checked\""; ?>/> 
                    <strong><?=gettext("Require 128-bit encryption"); ?></strong><br />
                    <?=gettext("When set, only 128-bit encryption will be accepted. Otherwise " .
                    "40-bit and 56-bit encryption will be accepted as well. Note that " .
                    "encryption will always be forced on PPTP connections (i.e. " .
                    "unencrypted connections will not be accepted)"); ?>.</td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> 
                    <input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" onclick="enable_change(true)"/> 
                  </td>
                </tr>
                <tr> 
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong><?=gettext("Note");?>:<br />
                    </strong></span><?=gettext("don't forget to ");?><a href="firewall_rules.php?if=pptp"><?=gettext("add a firewall rule"); ?></a> <?=gettext("to permit ". 
                    "traffic from PPTP clients");?>!</span></td>
                 </tr>
              </table>
</div>
			</td>
	</tr>
</table>
</form>
<script type="text/JavaScript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc"); ?>
</body>
</html>
