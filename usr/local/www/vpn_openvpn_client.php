<?php 
/*
	vpn_openvpn_client.php

	Copyright (C) 2008 Shrew Soft Inc.
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
##|*IDENT=page-openvpn-client
##|*NAME=OpenVPN: Client page
##|*DESCR=Allow access to the 'OpenVPN: Client' page.
##|*MATCH=vpn_openvpn_client.php*
##|-PRIV

require("guiconfig.inc");
require_once("openvpn.inc");

$pgtitle = array(gettext("OpenVPN"), gettext("Client"));
$shortcut_section = "openvpn";

if (!is_array($config['openvpn']['openvpn-client']))
	$config['openvpn']['openvpn-client'] = array();

$a_client = &$config['openvpn']['openvpn-client'];

if (!is_array($config['ca']))
	$config['ca'] = array();

$a_ca =& $config['ca'];

if (!is_array($config['cert']))
	$config['cert'] = array();

$a_cert =& $config['cert'];

if (!is_array($config['crl']))
	$config['crl'] = array();

$a_crl =& $config['crl'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if (isset($id) && $a_client[$id])
	$vpnid = $a_client[$id]['vpnid'];
else
	$vpnid = 0;

if ($_GET['act'] == "del") {

	if (!isset($a_client[$id])) {
		pfSenseHeader("vpn_openvpn_client.php");
		exit;
	}
	if (!empty($a_client[$id]))
		openvpn_delete('client', $a_client[$id]);
	unset($a_client[$id]);
	write_config();
	$savemsg = gettext("Client successfully deleted")."<br/>";
}

if($_GET['act']=="new"){
	$pconfig['autokey_enable'] = "yes";
	$pconfig['tlsauth_enable'] = "yes";
	$pconfig['autotls_enable'] = "yes";
	$pconfig['interface'] = "wan";
	$pconfig['server_port'] = 1194;
}

if($_GET['act']=="edit"){

	if (isset($id) && $a_client[$id]) {

		$pconfig['disable'] = isset($a_client[$id]['disable']);
		$pconfig['mode'] = $a_client[$id]['mode'];
		$pconfig['protocol'] = $a_client[$id]['protocol'];
		$pconfig['interface'] = $a_client[$id]['interface'];
		if (!empty($a_client[$id]['ipaddr'])) {
			$pconfig['interface'] = $pconfig['interface'] . '|' . $a_client[$id]['ipaddr'];
		}
		$pconfig['local_port'] = $a_client[$id]['local_port'];
		$pconfig['server_addr'] = $a_client[$id]['server_addr'];
		$pconfig['server_port'] = $a_client[$id]['server_port'];
		$pconfig['resolve_retry'] = $a_client[$id]['resolve_retry'];
		$pconfig['proxy_addr'] = $a_client[$id]['proxy_addr'];
		$pconfig['proxy_port'] = $a_client[$id]['proxy_port'];
		$pconfig['proxy_user'] = $a_client[$id]['proxy_user'];
		$pconfig['proxy_passwd'] = $a_client[$id]['proxy_passwd'];
		$pconfig['proxy_authtype'] = $a_client[$id]['proxy_authtype'];
		$pconfig['description'] = $a_client[$id]['description'];
		$pconfig['custom_options'] = $a_client[$id]['custom_options'];
		$pconfig['ns_cert_type'] = $a_client[$id]['ns_cert_type'];
		$pconfig['dev_mode'] = $a_client[$id]['dev_mode'];
	
		if ($pconfig['mode'] != "p2p_shared_key") {
			$pconfig['caref'] = $a_client[$id]['caref'];
			$pconfig['certref'] = $a_client[$id]['certref'];
			if ($a_client[$id]['tls']) {
				$pconfig['tlsauth_enable'] = "yes";
				$pconfig['tls'] = base64_decode($a_client[$id]['tls']);
			}
		} else
			$pconfig['shared_key'] = base64_decode($a_client[$id]['shared_key']);
		$pconfig['crypto'] = $a_client[$id]['crypto'];
		$pconfig['engine'] = $a_client[$id]['engine'];

		$pconfig['tunnel_network'] = $a_client[$id]['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $a_client[$id]['tunnel_networkv6'];
		$pconfig['remote_network'] = $a_client[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_client[$id]['remote_networkv6'];
		$pconfig['use_shaper'] = $a_client[$id]['use_shaper'];
		$pconfig['compression'] = $a_client[$id]['compression'];
		$pconfig['passtos'] = $a_client[$id]['passtos'];

		// just in case the modes switch
		$pconfig['autokey_enable'] = "yes";
		$pconfig['autotls_enable'] = "yes";
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($id) && $a_client[$id])
		$vpnid = $a_client[$id]['vpnid'];
	else
		$vpnid = 0;

	list($iv_iface, $iv_ip) = explode ("|",$pconfig['interface']);
	if (is_ipaddrv4($iv_ip) && (stristr($pconfig['protocol'], "6") !== false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. You cannot select an IPv6 protocol and an IPv4 IP address.");
	} elseif (is_ipaddrv6($iv_ip) && (stristr($pconfig['protocol'], "6") === false)) {
		$input_errors[] = gettext("Protocol and IP address families do not match. You cannot select an IPv4 protocol and an IPv6 IP address.");
	} elseif ((stristr($pconfig['protocol'], "6") === false) && !get_interface_ip($iv_iface) && ($pconfig['interface'] != "any")) {
		$input_errors[] = gettext("An IPv4 protocol was selected, but the selected interface has no IPv4 address.");
	} elseif ((stristr($pconfig['protocol'], "6") !== false) && !get_interface_ipv6($iv_iface) && ($pconfig['interface'] != "any")) {
		$input_errors[] = gettext("An IPv6 protocol was selected, but the selected interface has no IPv6 address.");
	}

	if ($pconfig['mode'] != "p2p_shared_key")
		$tls_mode = true;
	else
		$tls_mode = false;

	/* input validation */
	if ($pconfig['local_port']) {

		if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port'))
			$input_errors[] = $result;

		$portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
		if (($portused != $vpnid) && ($portused != 0))
			$input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
	}

	if ($result = openvpn_validate_host($pconfig['server_addr'], 'Server host or address'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_port($pconfig['server_port'], 'Server port'))
		$input_errors[] = $result;

	if ($pconfig['proxy_addr']) {

		if ($result = openvpn_validate_host($pconfig['proxy_addr'], 'Proxy host or address'))
			$input_errors[] = $result;

		if ($result = openvpn_validate_port($pconfig['proxy_port'], 'Proxy port'))
			$input_errors[] = $result;

		if ($pconfig['proxy_authtype'] != "none") {
			if (empty($pconfig['proxy_user']) || empty($pconfig['proxy_passwd']))
				$input_errors[] = gettext("User name and password are required for proxy with authentication.");
		}
	}

	if($pconfig['tunnel_network'])
		if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4"))
			$input_errors[] = $result;

	if($pconfig['tunnel_networkv6'])
		if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6"))
			$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6"))
		$input_errors[] = $result;

	if (!empty($pconfig['use_shaper']) && (!is_numeric($pconfig['use_shaper']) || ($pconfig['use_shaper'] <= 0)))
		$input_errors[] = gettext("The bandwidth limit must be a positive numeric value.");

    if ($pconfig['autokey_enable'])
        $pconfig['shared_key'] = openvpn_create_key();

	if (!$tls_mode && !$pconfig['autokey_enable'])
		if (!strstr($pconfig['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['shared_key'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = gettext("The field 'Shared Key' does not appear to be valid");

	if ($tls_mode && $pconfig['tlsauth_enable'] && !$pconfig['autotls_enable'])
		if (!strstr($pconfig['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['tls'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = gettext("The field 'TLS Authentication Key' does not appear to be valid");

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($pconfig['mode'] != "p2p_shared_key") {
		$reqdfields = explode(" ", "caref certref");
		$reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (!$input_errors) {

		$client = array();

		if ($vpnid)
			$client['vpnid'] = $vpnid;
		else
			$client['vpnid'] = openvpn_vpnid_next();

		if ($_POST['disable'] == "yes")
			$client['disable'] = true;
		$client['protocol'] = $pconfig['protocol'];
		$client['dev_mode'] = $pconfig['dev_mode'];
		list($client['interface'], $client['ipaddr']) = explode ("|",$pconfig['interface']);
		$client['local_port'] = $pconfig['local_port'];
		$client['server_addr'] = $pconfig['server_addr'];
		$client['server_port'] = $pconfig['server_port'];
		$client['resolve_retry'] = $pconfig['resolve_retry'];
		$client['proxy_addr'] = $pconfig['proxy_addr'];
		$client['proxy_port'] = $pconfig['proxy_port'];
		$client['proxy_authtype'] = $pconfig['proxy_authtype'];
		$client['proxy_user'] = $pconfig['proxy_user'];
		$client['proxy_passwd'] = $pconfig['proxy_passwd'];
		$client['description'] = $pconfig['description'];
		$client['mode'] = $pconfig['mode'];
		$client['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

        if ($tls_mode) {
            $client['caref'] = $pconfig['caref'];
            $client['certref'] = $pconfig['certref'];
            if ($pconfig['tlsauth_enable']) {
                if ($pconfig['autotls_enable'])
                    $pconfig['tls'] = openvpn_create_key();
                $client['tls'] = base64_encode($pconfig['tls']);
            }
        } else {
            $client['shared_key'] = base64_encode($pconfig['shared_key']);
        }
		$client['crypto'] = $pconfig['crypto'];
		$client['engine'] = $pconfig['engine'];

		$client['tunnel_network'] = $pconfig['tunnel_network'];
		$client['tunnel_networkv6'] = $pconfig['tunnel_networkv6'];
		$client['remote_network'] = $pconfig['remote_network'];
		$client['remote_networkv6'] = $pconfig['remote_networkv6'];
		$client['use_shaper'] = $pconfig['use_shaper'];
		$client['compression'] = $pconfig['compression'];
		$client['passtos'] = $pconfig['passtos'];

		if (isset($id) && $a_client[$id])
			$a_client[$id] = $client;
		else
			$a_client[] = $client;

		openvpn_resync('client', $client);
		write_config();
		
		header("Location: vpn_openvpn_client.php");
		exit;
	}
}

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/JavaScript">
<!--

function mode_change() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	switch(value) {
		case "p2p_tls":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("psk").style.display="none";
			break;
		case "p2p_shared_key":
			document.getElementById("tls").style.display="none";
			document.getElementById("tls_ca").style.display="none";
			document.getElementById("tls_cert").style.display="none";
			document.getElementById("psk").style.display="";
			break;
	}
}

function autokey_change() {
	if (document.iform.autokey_enable.checked)
		document.getElementById("autokey_opts").style.display="none";
	else
		document.getElementById("autokey_opts").style.display="";
}

function useproxy_changed() {

	if (jQuery('#proxy_authtype').val() != 'none') {
                jQuery('#proxy_authtype_opts').show();
        } else {
                jQuery('#proxy_authtype_opts').hide();
        }
}

function tlsauth_change() {

<?php if (!$pconfig['tls']): ?>
	if (document.iform.tlsauth_enable.checked)
		document.getElementById("tlsauth_opts").style.display="";
	else
		document.getElementById("tlsauth_opts").style.display="none";
<?php endif; ?>

	autotls_change();
}

function autotls_change() {

<?php if (!$pconfig['tls']): ?>
	autocheck = document.iform.autotls_enable.checked;
<?php else: ?>
	autocheck = false;
<?php endif; ?>

	if (document.iform.tlsauth_enable.checked && !autocheck)
		document.getElementById("autotls_opts").style.display="";
	else
		document.getElementById("autotls_opts").style.display="none";
}

//-->
</script>
<?php
if (!$savemsg)
	$savemsg = "";

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
 	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav"><li>
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), true, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
				$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
				add_package_tabs("OpenVPN", $tab_array);
				display_top_tabs($tab_array);
			?>
			</li></ul>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>

			<form action="vpn_openvpn_client.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("General information"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="0" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['disable'],$chk); ?>
										<input name="disable" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										&nbsp;
										<span class="vexpl">
											<strong><?=gettext("Disable this client"); ?></strong><br />
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("Set this option to disable this client without removing it from the list"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server Mode");?></td>
						<td width="78%" class="vtable">
							<select name='mode' id='mode' class="formselect" onchange='mode_change()'>
							<?php
								foreach ($openvpn_client_modes as $name => $desc):
									$selected = "";
									if ($pconfig['mode'] == $name)
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$name;?>" <?=$selected;?>><?=$desc;?></option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Protocol");?></td>
							<td width="78%" class="vtable">
							<select name='protocol' class="formselect">
							<?php
								foreach ($openvpn_prots as $prot):
									$selected = "";
									if ($pconfig['protocol'] == $prot)
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$prot;?>" <?=$selected;?>><?=$prot;?></option>
							<?php endforeach; ?>
							</select>
							</td>
					</tr>
                                        <tr>
                                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Device mode");?></td>
                                                        <td width="78%" class="vtable">
                                                        <select name='dev_mode' class="formselect">
                                                        <?php
                                                                foreach ($openvpn_dev_mode as $mode):
                                                                        $selected = "";
                                                                        if ($pconfig['dev_mode'] == $mode)
                                                                                $selected = "selected=\"selected\"";
                                                        ?>
                                                                <option value="<?=$mode;?>" <?=$selected;?>><?=$mode;?></option>
                                                        <?php endforeach; ?>
                                                        </select>
                                                        </td>
                                        </tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Interface"); ?></td>
						<td width="78%" class="vtable">
							<select name="interface" class="formselect">
								<?php
									$interfaces = get_configured_interface_with_descr();
									$carplist = get_configured_carp_interface_list();
									foreach ($carplist as $cif => $carpip)
										$interfaces[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
									$aliaslist = get_configured_ip_aliases_list();
									foreach ($aliaslist as $aliasip => $aliasif)
										$interfaces[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
									$grouplist = return_gateway_groups_array();
									foreach ($grouplist as $name => $group) {
										if($group['ipprotocol'] != inet)
											continue;
										if($group[0]['vip'] <> "")
											$vipif = $group[0]['vip'];
										else
											$vipif = $group[0]['int'];
										$interfaces[$name] = "GW Group {$name}";
									}
									$interfaces['lo0'] = "Localhost";
									$interfaces['any'] = "any";
									foreach ($interfaces as $iface => $ifacename):
										$selected = "";
										if ($iface == $pconfig['interface'])
											$selected = "selected=\"selected\"";
								?>
									<option value="<?=$iface;?>" <?=$selected;?>>
										<?=htmlspecialchars($ifacename);?>
									</option>
								<?php endforeach; ?>
							</select> <br />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Local port");?></td>
						<td width="78%" class="vtable">
							<input name="local_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['local_port']);?>"/>
							<br/>
							<?=gettext("Set this option if you would like to bind to a specific port. Leave this blank or enter 0 for a random dynamic port."); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server host or address");?></td>
						<td width="78%" class="vtable">
							<input name="server_addr" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['server_addr']);?>"/>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server port");?></td>
						<td width="78%" class="vtable">
							<input name="server_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['server_port']);?>"/>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Proxy host or address");?></td>
						<td width="78%" class="vtable">
							<input name="proxy_addr" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['proxy_addr']);?>"/>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Proxy port");?></td>
						<td width="78%" class="vtable">
							<input name="proxy_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['proxy_port']);?>"/>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Proxy authentication extra options");?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
                                                                        <td align="right" width="25%">
                                                                                <span class="vexpl">
                                                                                         &nbsp;<?=gettext("Authentication method"); ?> :&nbsp;
                                                                                </span>
                                                                        </td>
                                                                        <td>
										<select name="proxy_authtype" id="proxy_authtype" class="formfld select" onchange="useproxy_changed()">
											<option value="none" <?php if ($pconfig['proxy_authtype'] == "none") echo "selected=\"selected\""; ?>><?=gettext("none"); ?></option>
											<option value="basic" <?php if ($pconfig['proxy_authtype'] == "basic") echo "selected=\"selected\""; ?>><?=gettext("basic"); ?></option>
											<option value="ntlm" <?php if ($pconfig['proxy_authtype'] == "ntlm") echo "selected=\"selected\""; ?>><?=gettext("ntlm"); ?></option>
										</select>
									</td>
								</tr>
							</table>
							<br />
							 <table border="0" cellpadding="2" cellspacing="0" id="proxy_authtype_opts" style="display:none">
                                                                <tr>
                                                                        <td align="right" width="25%">
                                                                                <span class="vexpl">
                                                                                         &nbsp;<?=gettext("Username"); ?> :&nbsp;
                                                                                </span>
                                                                        </td>
                                                                        <td>
                                                                                <input name="proxy_user" id="proxy_user" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['proxy_user']);?>" />
                                                                        </td>
                                                                </tr>
                                                                <tr>
                                                                        <td align="right" width="25%">
                                                                                <span class="vexpl">
                                                                                         &nbsp;<?=gettext("Password"); ?> :&nbsp;
                                                                                </span>
                                                                        </td>
                                                                        <td>
                                                                                <input name="proxy_passwd" id="proxy_passwd" type="password" class="formfld pwd" size="20" value="<?=htmlspecialchars($pconfig['proxy_passwd']);?>" />
                                                                        </td>
                                                                </tr>
                                                        </table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Server host name resolution"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['resolve_retry'],$chk); ?>
										<input name="resolve_retry" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Infinitely resolve server"); ?>
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("Continuously attempt to resolve the server host " .
							"name. Useful when communicating with a server " .
							"that is not permanently connected to the Internet"); ?>.
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
						<td width="78%" class="vtable"> 
							<input name="description" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>"/>
							<br />
							<?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Cryptographic Settings"); ?></td>
					</tr>
					<tr id="tls">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("TLS Authentication"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['tlsauth_enable'],$chk); ?>
										<input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=$chk;?> onclick="tlsauth_change()"/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Enable authentication of TLS packets"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php if (!$pconfig['tls']): ?>
							<table border="0" cellpadding="2" cellspacing="0" id='tlsauth_opts'>
								<tr>
									<td>
										<?php set_checked($pconfig['autotls_enable'],$chk); ?>
										<input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autotls_change()"/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared TLS authentication key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id='autotls_opts'>
								<tr>
									<td>
										<textarea name="tls" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['tls']);?></textarea>
										<br/>
										<?=gettext("Paste your shared key here"); ?>.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="tls_ca">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Peer Certificate Authority"); ?></td>
							<td width="78%" class="vtable">
							<?php if (count($a_ca)): ?>
							<select name='caref' class="formselect">
							<?php
								foreach ($a_ca as $ca):
									$selected = "";
									if ($pconfig['caref'] == $ca['refid'])
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$ca['refid'];?>" <?=$selected;?>><?=$ca['descr'];?></option>
							<?php endforeach; ?>
							</select>
							<?php else: ?>
								<b>No Certificate Authorities defined.</b> <br/>Create one under <a href="system_camanager.php">System &gt; Cert Manager</a>.
							<?php endif; ?>
							</td>
					</tr>
					<tr id="tls_cert">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Client Certificate"); ?></td>
							<td width="78%" class="vtable">
							<?php if (count($a_cert)): ?>
							<select name='certref' class="formselect">
							<?php
							foreach ($a_cert as $cert):
								$selected = "";
								$caname = "";
								$inuse = "";
								$revoked = "";
								$ca = lookup_ca($cert['caref']);
								if ($ca)
									$caname = " (CA: {$ca['descr']})";
								if ($pconfig['certref'] == $cert['refid'])
									$selected = "selected=\"selected\"";
								if (cert_in_use($cert['refid']))
									$inuse = " *In Use";
								if (is_cert_revoked($cert))
									$revoked = " *Revoked";
							?>
								<option value="<?=$cert['refid'];?>" <?=$selected;?>><?=$cert['descr'] . $caname . $inuse . $revoked;?></option>
							<?php endforeach; ?>
							</select>
							<?php else: ?>
								<b>No Certificates defined.</b> <br/>Create one under <a href="system_certmanager.php">System &gt; Cert Manager</a>.
							<?php endif; ?>
						</td>
					</tr>
					<tr id="psk">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Shared Key"); ?></td>
						<td width="78%" class="vtable">
							<?php if (!$pconfig['shared_key']): ?>
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['autokey_enable'],$chk); ?>
										<input name="autokey_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autokey_change()"/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id='autokey_opts'>
								<tr>
									<td>
										<textarea name="shared_key" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['shared_key']);?></textarea>
										<br/>
										<?=gettext("Paste your shared key here"); ?>.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Encryption algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="crypto" class="formselect">
								<?php
									$cipherlist = openvpn_get_cipherlist();
									foreach ($cipherlist as $name => $desc):
									$selected = '';
									if ($name == $pconfig['crypto'])
										$selected = 'selected="selected"';
								?>
								<option value="<?=$name;?>" <?=$selected?>>
									<?=htmlspecialchars($desc);?>
								</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="engine">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Hardware Crypto"); ?></td>
						<td width="78%" class="vtable">
							<select name="engine" class="formselect">
								<?php
									$engines = openvpn_get_engines();
									foreach ($engines as $name => $desc):
									$selected = '';
									if ($name == $pconfig['engine'])
										$selected = 'selected="selected"';
								?>
								<option value="<?=$name;?>" <?=$selected?>>
									<?=htmlspecialchars($desc);?>
								</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Tunnel Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_network']);?>"/>
							<br />
							<?=gettext("This is the virtual network used for private " .
							"communications between this client and the " .
							"server expressed using CIDR (eg. 10.0.8.0/24). " .
							"The first network address is assumed to be the " .
							"server address and the second network address " .
							"will be assigned to the client virtual " .
							"interface"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_networkv6" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_networkv6']);?>"/>
							<br />
							<?=gettext("This is the IPv6 virtual network used for private " .
							"communications between this client and the " .
							"server expressed using CIDR (eg. fe80::/64). " .
							"The first network address is assumed to be the " .
							"server address and the second network address " .
							"will be assigned to the client virtual " .
							"interface"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_network']);?>"/>
							<br />
							<?=gettext("These are the IPv4 networks that will be routed through " .
							"the tunnel, so that a site-to-site VPN can be " .
							"established without manually changing the routing tables. " .
							"Expressed as a comma-separated list of one or more CIDR ranges. " .
							"If this is a site-to-site VPN, enter the " .
							"remote LAN/s here. You may leave this blank to " .
							"only communicate with other clients"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_networkv6" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_networkv6']);?>"/>
							<br />
							<?=gettext("These are the IPv6 networks that will be routed through " .
							"the tunnel, so that a site-to-site VPN can be " .
							"established without manually changing the routing tables. " .
							"Expressed as a comma-separated list of one or more IP/PREFIX. " .
							"If this is a site-to-site VPN, enter the " .
							"remote LAN/s here. You may leave this blank to " .
							"only communicate with other clients"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Limit outgoing bandwidth");?></td>
						<td width="78%" class="vtable">
							<input name="use_shaper" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['use_shaper']);?>"/>
							<br/>
							<?=gettext("Maximum outgoing bandwidth for this tunnel. " .
							"Leave empty for no limit. The input value has " .
							"to be something between 100 bytes/sec and 100 " .
							"Mbytes/sec (entered as bytes per second)"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Compression"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['compression'],$chk); ?>
										<input name="compression" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Compress tunnel packets using the LZO algorithm"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Type-of-Service"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['passtos'],$chk); ?>
										<input name="passtos" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Set the TOS IP header value of tunnel packets to match the encapsulated packet value"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="avd_config">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced configuration"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Advanced"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=htmlspecialchars($pconfig['custom_options']);?></textarea><br/>
										<?=gettext("Enter any additional options you would like to add to the OpenVPN client configuration here, separated by a semicolon"); ?><br/>
										<?=gettext("EXAMPLE:"); ?> <strong>remote server.mysite.com 1194;</strong> or <strong>remote 1.2.3.4 1194;</strong>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<br/>
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>"/> 
							<input name="act" type="hidden" value="<?=$act;?>"/>
							<?php if (isset($id) && $a_client[$id]): ?>
							<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>"/>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
				<thead>
				<tr>
					<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
					<td width="10%" class="listhdrr"><?=gettext("Protocol"); ?></td>
					<td width="30%" class="listhdrr"><?=gettext("Server"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<td class="list" colspan="4"></td>
					<td class="list">
						<a href="vpn_openvpn_client.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add client"); ?>" alt="" width="17" height="17" border="0"/>
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="4">
						<p>
							<?=gettext("Additional OpenVPN clients can be added here.");?>
						</p>
					</td>
				</tr>
				</tfoot>
				<tbody>
				<?php
					$i = 0;
					foreach($a_client as $client):
						$disabled = "NO";
						if (isset($client['disable']))
							$disabled = "YES";
						$server = "{$client['server_addr']}:{$client['server_port']}";
				?>
				<tr ondblclick="document.location='vpn_openvpn_client.php?act=edit&amp;id=<?=$i;?>'">
					<td class="listlr">
						<?=$disabled;?>
					</td>
					<td class="listr">
						<?=htmlspecialchars($client['protocol']);?>
					</td>
					<td class="listr">
						<?=htmlspecialchars($server);?>
					</td>
					<td class="listbg">
						<?=htmlspecialchars($client['description']);?>
					</td>
					<td valign="middle" nowrap="nowrap" class="list">
						<a href="vpn_openvpn_client.php?act=edit&amp;id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit client"); ?>" alt="" width="17" height="17" border="0"/>
						</a>
						&nbsp;
						<a href="vpn_openvpn_client.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this client?"); ?>')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete client"); ?>" alt="" width="17" height="17" border="0"/>
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
					if ($i == 0)
						echo "<tr><td></td></tr>";
				?>
				</tbody>
			</table>

			<?php endif; ?>

		</td>
	</tr>
</table>
<script type="text/JavaScript">
<!--
mode_change();
autokey_change();
tlsauth_change();
useproxy_changed();
//-->
</script>
<?php include("fend.inc"); ?>

<?php

/* local utility functions */

function set_checked($var,& $chk) {
    if($var)
        $chk = 'checked="checked"';
    else
        $chk = '';
}

?>
</body>
</html>
