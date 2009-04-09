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

$pgtitle = array("OpenVPN", "Client");

if (!is_array($config['openvpn']['openvpn-client']))
	$config['openvpn']['openvpn-client'] = array();

$a_client = &$config['openvpn']['openvpn-client'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($_GET['act'] == "del") {

	if (!$a_client[$id]) {
		pfSenseHeader("vpn_openvpn_client.php");
		exit;
	}

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

		$pconfig['disable'] = $a_client[$id]['disable'];
		$pconfig['mode'] = $a_client[$id]['mode'];
		$pconfig['protocol'] = $a_client[$id]['protocol'];
		$pconfig['interface'] = $a_client[$id]['interface'];
		$pconfig['local_port'] = $a_client[$id]['local_port'];
		$pconfig['server_addr'] = $a_client[$id]['server_addr'];
		$pconfig['server_port'] = $a_client[$id]['server_port'];
		$pconfig['resolve_retry'] = $a_client[$id]['resolve_retry'];
		$pconfig['proxy_addr'] = $a_client[$id]['proxy_addr'];
		$pconfig['proxy_port'] = $a_client[$id]['proxy_port'];
		$pconfig['description'] = $a_client[$id]['description'];

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

		$pconfig['tunnel_network'] = $a_client[$id]['tunnel_network'];
		$pconfig['remote_network'] = $a_client[$id]['remote_network'];
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

	if ($client['mode'] != "p2p_shared_key")
		$tls_mode = true;
	else
		$tls_mode = false;

	/* input validation */
	if ($pconfig['local_port']) {

		if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port'))
			$input_errors[] = $result;

		$portused = openvpn_port_used($pconfig['protocol'], $pconfig['local_port']);
		if (($portused != $vpnid) && ($portused != 0))
			$input_errors[] = "The specified 'Local port' is in use. Please select another value";
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
	}

	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'Tunnel network'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'Remote network'))
		$input_errors[] = $result;

	if (!$tls_mode && !$pconfig['autokey_enable'])
		if (!strstr($pconfig['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['shared_key'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = "The field 'Shared Key' does not appear to be valid";

	if ($tls_mode && $pconfig['tlsauth_enable'] && !$pconfig['autotls_enable'])
		if (!strstr($pconfig['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['tls'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = "The field 'TLS Authentication Key' does not appear to be valid";

	if (!$tls_mode) {
		$reqdfields = array('shared_key');
		$reqdfieldsn = array('Shared key');
    } else {
		$reqdfields = explode(" ", "caref certref");
		$reqdfieldsn = explode(",", "Certificate Authority,Certificate");;
	}

    $reqdfields[] = 'tunnel_network';
    $reqdfieldsn[] = 'Tunnel network';

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (!$input_errors) {

		$client = array();

		if ($vpnid)
			$client['vpnid'] = $vpnid;
		else
			$client['vpnid'] = openvpn_vpnid_next();

		$client['disable'] = $pconfig['disable'];
		$client['protocol'] = $pconfig['protocol'];
		$client['interface'] = $pconfig['interface'];
		$client['local_port'] = $pconfig['local_port'];
		$client['server_addr'] = $pconfig['server_addr'];
		$client['server_port'] = $pconfig['server_port'];
		$client['resolve_retry'] = $pconfig['resolve_retry'];
		$client['proxy_addr'] = $pconfig['proxy_addr'];
		$client['proxy_port'] = $pconfig['proxy_port'];
		$client['description'] = $pconfig['description'];

        if ($tls_mode) {
            $client['caref'] = $pconfig['caref'];
            $client['certref'] = $pconfig['certref'];
            if ($pconfig['tlsauth_enable']) {
                if ($pconfig['autotls_enable'])
                    $pconfig['tls'] = openvpn_create_key();
                $client['tls'] = base64_encode($pconfig['tls']);
            }
        } else {
            if ($pconfig['autokey_enable'])
                $pconfig['shared_key'] = openvpn_create_key();
            $client['shared_key'] = base64_encode($pconfig['shared_key']);
        }
		$client['crypto'] = $pconfig['crypto'];

		$client['tunnel_network'] = $pconfig['tunnel_network'];
		$client['remote_network'] = $pconfig['remote_network'];
		$client['compression'] = $pconfig['compression'];

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
<script language="JavaScript">
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
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
 	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav">
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), true, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
				add_package_tabs("OpenVPN", $tab_array);
				display_top_tabs($tab_array);
			?>
			</ul>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>

			<form action="vpn_openvpn_client.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td colspan="2" valign="top" class="listtopic">General information</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq">Disabled</td>
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
											<strong>Disable this client</strong><br>
										</span>
									</td>
								</tr>
							</table>
							Set this option to disable this client without removing it from the list.
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
										$selected = "selected";
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
										$selected = "selected";
							?>
								<option value="<?=$prot;?>" <?=$selected;?>><?=$prot;?></option>
							<?php endforeach; ?>
							</select>
							</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq">Interface</td>
						<td width="78%" class="vtable">
							<select name="interface" class="formselect">
								<?php
									$interfaces = get_configured_interface_with_descr();
										foreach ($interfaces as $iface => $ifacename):
										$selected = "";
										if ($iface == $pconfig['interface'])
											$selected = "selected";
								?>
									<option value="<?=$iface;?>" <?=$selected;?>>
										<?=htmlspecialchars($ifacename);?>
									</option>
								<?php endforeach; ?>
							</select> <br>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Local port");?></td>
						<td width="78%" class="vtable">
							<input name="local_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['local_port']);?>"/>
							<br/>
							Set this option if you would like to bind to a specific port.
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
						<td width="22%" valign="top" class="vncell">Server host name resolution</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['resolve_retry'],$chk); ?>
										<input name="compression" type="checkbox" value="yes" <?=$chk;?>>
									</td>
									<td>
										<span class="vexpl">
											Infinitely resolve server
										</span>
									</td>
								</tr>
							</table>
							Continuously attempt to resolve the server host
							name. Useful when communicating with a server
							that is not permanently connected to the internet.
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell">Description</td>
						<td width="78%" class="vtable"> 
							<input name="description" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>">
							<br>
							You may enter a description here for your reference (not parsed).
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">Cryptographic Settings</td>
					</tr>
					<tr id="tls">
						<td width="22%" valign="top" class="vncellreq">TLS Authentication</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['tlsauth_enable'],$chk); ?>
										<input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=$chk;?> onClick="tlsauth_change()">
									</td>
									<td>
										<span class="vexpl">
											Enable authentication of TLS packets.
										</span>
									</td>
								</tr>
							</table>
							<?php if (!$pconfig['tls']): ?>
							<table border="0" cellpadding="2" cellspacing="0" id='tlsauth_opts'>
								<tr>
									<td>
										<?php set_checked($pconfig['autotls_enable'],$chk); ?>
										<input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=$chk;?> onClick="autotls_change()">
									</td>
									<td>
										<span class="vexpl">
											Automatically generate a shared TLS authentication key.
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
										Paste your shared key here.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="tls_ca">
						<td width="22%" valign="top" class="vncellreq">Peer Certificate Authority</td>
							<td width="78%" class="vtable">
							<select name='caref' class="formselect">
							<?php
								foreach ($config['system']['ca'] as $ca):
									$selected = "";
									if ($pconfig['caref'] == $ca['refid'])
										$selected = "selected";
							?>
								<option value="<?=$ca['refid'];?>" <?=$selected;?>><?=$ca['name'];?></option>
							<?php endforeach; ?>
							</select>
							</td>
					</tr>
					<tr id="tls_cert">
						<td width="22%" valign="top" class="vncellreq">Client Certificate</td>
							<td width="78%" class="vtable">
							<select name='certref' class="formselect">
							<?php
								foreach ($config['system']['cert'] as $cert):
									$selected = "";
									if ($pconfig['certref'] == $cert['refid'])
										$selected = "selected";
							?>
								<option value="<?=$cert['refid'];?>" <?=$selected;?>><?=$cert['name'];?></option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="psk">
						<td width="22%" valign="top" class="vncellreq">Shared Key</td>
						<td width="78%" class="vtable">
							<?php if (!$pconfig['shared_key']): ?>
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['autokey_enable'],$chk); ?>
										<input name="autokey_enable" type="checkbox" value="yes" <?=$chk;?> onClick="autokey_change()">
									</td>
									<td>
										<span class="vexpl">
											Automatically generate a shared key.
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
										Paste your shared key here.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq">Encryption algorithm</td>
						<td width="78%" class="vtable">
							<select name="crypto" class="formselect">
								<?php
									$cipherlist = openvpn_get_cipherlist();
									foreach ($cipherlist as $name => $desc):
									$selected = '';
									if ($name == $pconfig['crypto'])
										$selected = ' selected';
								?>
								<option value="<?=$name;?>"<?=$selected?>>
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
						<td colspan="2" valign="top" class="listtopic">Tunnel Settings</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq">Tunnel Network</td>
						<td width="78%" class="vtable">
							<input name="tunnel_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_network']);?>">
							<br>
							This is the virtual network used for private
							communications between this client and the
							server expressed using CIDR (eg. 10.0.8.0/24).
							The first network address is assumed to be the
							server address and the second network address
							will be assigned to the client virtual
							interface.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Remote Network</td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['remote_network']);?>">
							<br>
							This is a network that will be routed through
							the tunnel, so that a site-to-site VPN can be
							established without manually changing the
							routing tables. Expressed as a CIDR range. If
							this is a site-to-site VPN, enter here the
							remote LAN here. You may leave this blank to
							only communicate with other clients.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Limit outgoing bandwidth");?></td>
						<td width="78%" class="vtable">
							<input name="use_shaper" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['use_shaper']);?>"/>
							<br/>
							Maximum outgoing bandwidth for this tunnel.
							Leave empty for no limit. The input value has
							to be something between 100 bytes/sec and 100
							Mbytes/sec (entered as bytes per second).
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Compression</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['compression'],$chk); ?>
										<input name="compression" type="checkbox" value="yes" <?=$chk;?>>
									</td>
									<td>
										<span class="vexpl">
											Compress tunnel packets using the LZO algorithm.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Type-of-Service</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['passtos'],$chk); ?>
										<input name="passtos" type="checkbox" value="yes" <?=$chk;?>>
									</td>
									<td>
										<span class="vexpl">
											Set the TOS IP header value of tunnel packets to match the encapsulated packet value.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="Save"> 
							<input name="act" type="hidden" value="<?=$act;?>">
							<?php if (isset($id) && $a_client[$id]): ?>
							<input name="id" type="hidden" value="<?=$id;?>">
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td width="10%" class="listhdrr">Disabled</td>
					<td width="10%" class="listhdrr">Protocol</td>
					<td width="30%" class="listhdrr">Server</td>
					<td width="40%" class="listhdrr">Description</td>
					<td width="10%" class="list"></td>
				</tr>
				<?php
					$i = 0;
					foreach($a_client as $client):
						$disabled = "NO";
						if ($client['disable'])
							$disabled = "YES";
						$server = "{$client['server_addr']}:{$client['server_port']}";
				?>
				<tr>
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
					<td valign="middle" nowrap class="list">
						<a href="vpn_openvpn_client.php?act=edit&id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="edit client" width="17" height="17" border="0">
						</a>
						&nbsp;
						<a href="vpn_openvpn_client.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this client?')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="delete client" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
				?>
				<tr>
					<td class="list" colspan="4"></td>
					<td class="list">
						<a href="vpn_openvpn_client.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="add client" width="17" height="17" border="0">
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
			</table>

			<? endif; ?>

		</td>
	</tr>
</table>
<script language="JavaScript">
<!--
mode_change();
autokey_change();
tlsauth_change();
//-->
</script>
</body>
<?php include("fend.inc"); ?>

<?php

/* local utility functions */

function set_checked($var,& $chk) {
    if($var)
        $chk = 'checked';
    else
        $chk = '';
}

?>

