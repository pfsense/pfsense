<?php 
/*
	vpn_openvpn_server.php

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
##|*IDENT=page-openvpn-server
##|*NAME=OpenVPN: Server page
##|*DESCR=Allow access to the 'OpenVPN: Server' page.
##|*MATCH=vpn_openvpn_server.php*
##|-PRIV

require("guiconfig.inc");
require_once("openvpn.inc");

$pgtitle = array("OpenVPN", "Server");

if (!is_array($config['openvpn']['openvpn-server']))
	$config['openvpn']['openvpn-server'] = array();

$a_server = &$config['openvpn']['openvpn-server'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($_GET['act'] == "del") {

	if (!$a_server[$id]) {
		pfSenseHeader("vpn_openvpn_server.php");
		exit;
	}

	openvpn_delete('server', $a_server[$id]);
	unset($a_server[$id]);
	write_config();
	$savemsg = gettext("Server successfully deleted")."<br/>";
}

if($_GET['act']=="new"){
	$pconfig['autokey_enable'] = "yes";
	$pconfig['tlsauth_enable'] = "yes";
	$pconfig['autotls_enable'] = "yes";
	$pconfig['dh_length'] = 1024;
	$pconfig['interface'] = "wan";
	$pconfig['local_port'] = openvpn_port_next('UDP');
	$pconfig['pool_enable'] = "yes";
}

if($_GET['act']=="edit"){

	if (isset($id) && $a_server[$id]) {

		$pconfig['disable'] = isset($a_server[$id]['disable']);
		$pconfig['mode'] = $a_server[$id]['mode'];
		$pconfig['protocol'] = $a_server[$id]['protocol'];
		$pconfig['authmode'] = $a_server[$id]['authmode'];
		$pconfig['interface'] = $a_server[$id]['interface'];
		if (!empty($a_server[$id]['ipaddr'])) {
			$pconfig['interface'] = $pconfig['interface'] . '|' . $a_server[$id]['ipaddr'];
		}
		$pconfig['local_port'] = $a_server[$id]['local_port'];
		$pconfig['description'] = $a_server[$id]['description'];
		$pconfig['custom_options'] = $a_server[$id]['custom_options'];

		if ($pconfig['mode'] != "p2p_shared_key") {
			if ($a_server[$id]['tls']) {
				$pconfig['tlsauth_enable'] = "yes";
				$pconfig['tls'] = base64_decode($a_server[$id]['tls']);
			}
			$pconfig['caref'] = $a_server[$id]['caref'];
			$pconfig['certref'] = $a_server[$id]['certref'];
			$pconfig['dh_length'] = $a_server[$id]['dh_length'];
		} else
			$pconfig['shared_key'] = base64_decode($a_server[$id]['shared_key']);
		$pconfig['crypto'] = $a_server[$id]['crypto'];

		$pconfig['tunnel_network'] = $a_server[$id]['tunnel_network'];
		$pconfig['remote_network'] = $a_server[$id]['remote_network'];
		$pconfig['gwredir'] = $a_server[$id]['gwredir'];
		$pconfig['local_network'] = $a_server[$id]['local_network'];
		$pconfig['maxclients'] = $a_server[$id]['maxclients'];
		$pconfig['compression'] = $a_server[$id]['compression'];
		$pconfig['passtos'] = $a_server[$id]['passtos'];
		$pconfig['client2client'] = $a_server[$id]['client2client'];

		$pconfig['dynamic_ip'] = $a_server[$id]['dynamic_ip'];
		$pconfig['pool_enable'] = $a_server[$id]['pool_enable'];

		$pconfig['dns_domain'] = $a_server[$id]['dns_domain'];
		if ($pconfig['dns_domain'])
			$pconfig['dns_domain_enable'] = true;

		$pconfig['dns_server1'] = $a_server[$id]['dns_server1'];
		$pconfig['dns_server2'] = $a_server[$id]['dns_server2'];
		$pconfig['dns_server3'] = $a_server[$id]['dns_server3'];
		$pconfig['dns_server4'] = $a_server[$id]['dns_server4'];
		if ($pconfig['dns_server1'] ||
			$pconfig['dns_server2'] ||
			$pconfig['dns_server3'] ||
			$pconfig['dns_server4'])
			$pconfig['dns_server_enable'] = true;

		$pconfig['ntp_server1'] = $a_server[$id]['ntp_server1'];
		$pconfig['ntp_server2'] = $a_server[$id]['ntp_server2'];
		if ($pconfig['ntp_server1'] ||
			$pconfig['ntp_server2'])
			$pconfig['ntp_server_enable'] = true;

		$pconfig['netbios_enable'] = $a_server[$id]['netbios_enable'];
		$pconfig['netbios_ntype'] = $a_server[$id]['netbios_ntype'];
		$pconfig['netbios_scope'] = $a_server[$id]['netbios_scope'];

		$pconfig['wins_server1'] = $a_server[$id]['wins_server1'];
		$pconfig['wins_server2'] = $a_server[$id]['wins_server2'];
		if ($pconfig['wins_server1'] ||
			$pconfig['wins_server2'])
			$pconfig['wins_server_enable'] = true;

		$pconfig['nbdd_server1'] = $a_server[$id]['nbdd_server1'];
		if ($pconfig['nbdd_server1'])
			$pconfig['nbdd_server_enable'] = true;

		// just in case the modes switch
		$pconfig['autokey_enable'] = "yes";
		$pconfig['autotls_enable'] = "yes";
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($id) && $a_server[$id])
		$vpnid = $a_server[$id]['vpnid'];
	else
		$vpnid = 0;

	if ($pconfig['mode'] != "p2p_shared_key")
		$tls_mode = true;
	else
		$tls_mode = false;

	if (!empty($pconfig['authmode'])) {
		foreach ($pconfig['authmode'] as $pauthmode) {
			if ($pauthmode != "Local Database" && $pconfig['mode'] == "server_tls_user") 
				$input_errors[] = "Only 'Local authentication database'  is allowed with " . $openvpn_server_modes[$pconfig['mode']];
		}
	}

	/* input validation */
	if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'Tunnel network'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'Remote network'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'Local network'))
		$input_errors[] = $result;

	$portused = openvpn_port_used($pconfig['protocol'], $pconfig['local_port']);
	if (($portused != $vpnid) && ($portused != 0))
		$input_errors[] = "The specified 'Local port' is in use. Please select another value";

	if ($pconfig['autokey_enable'])
		$pconfig['shared_key'] = openvpn_create_key();

	if (!$tls_mode && !$pconfig['autokey_enable'])
		if (!strstr($pconfig['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['shared_key'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = "The field 'Shared Key' does not appear to be valid";

	if ($tls_mode && $pconfig['tlsauth_enable'] && !$pconfig['autotls_enable'])
		if (!strstr($pconfig['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($pconfig['tls'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = "The field 'TLS Authentication Key' does not appear to be valid";

	if ($pconfig['dns_server_enable']) {
		if (!empty($pconfig['dns_server1']) && !is_ipaddr(trim($pconfig['dns_server1'])))
			$input_errors[] = "The field 'DNS Server #1' must contain a valid IP address";
		if (!empty($pconfig['dns_server2']) && !is_ipaddr(trim($pconfig['dns_server2'])))
			$input_errors[] = "The field 'DNS Server #2' must contain a valid IP address";
		if (!empty($pconfig['dns_server3']) && !is_ipaddr(trim($pconfig['dns_server3'])))
			$input_errors[] = "The field 'DNS Server #3' must contain a valid IP address";
		if (!empty($pconfig['dns_server4']) && !is_ipaddr(trim($pconfig['dns_server4'])))
			$input_errors[] = "The field 'DNS Server #4' must contain a valid IP address";
	}

	if ($pconfig['ntp_server_enable']) {
		if (!empty($pconfig['ntp_server1']) && !is_ipaddr(trim($pconfig['ntp_server1'])))
			$input_errors[] = "The field 'NTP Server #1' must contain a valid IP address";
		if (!empty($pconfig['ntp_server2']) && !is_ipaddr(trim($pconfig['ntp_server2'])))
			$input_errors[] = "The field 'NTP Server #2' must contain a valid IP address";
		if (!empty($pconfig['ntp_server3']) && !is_ipaddr(trim($pconfig['ntp_server3'])))
			$input_errors[] = "The field 'NTP Server #3' must contain a valid IP address";
		if (!empty($pconfig['ntp_server4']) && !is_ipaddr(trim($pconfig['ntp_server4'])))
			$input_errors[] = "The field 'NTP Server #4' must contain a valid IP address";
	}

	if ($pconfig['netbios_enable']) {
		if ($pconfig['wins_server_enable']) {
			if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1'])))
				$input_errors[] = "The field 'WINS Server #1' must contain a valid IP address";
			if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2'])))
				$input_errors[] = "The field 'WINS Server #2' must contain a valid IP address";
		}
		if ($pconfig['nbdd_server_enable'])
			if (!empty($pconfig['nbdd_server1']) && !is_ipaddr(trim($pconfig['nbdd_server1'])))
				$input_errors[] = "The field 'NetBIOS Data Distribution Server #1' must contain a valid IP address";
	}

	if ($pconfig['maxclients'] && !is_numeric($pconfig['maxclients']))
		$input_errors[] = "The field 'Concurrent connections' must be numeric.";

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($pconfig['mode'] != "p2p_shared_key") {
		$reqdfields = explode(" ", "caref certref");
		$reqdfieldsn = explode(",", "Certificate Authority,Certificate");;
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array('Shared key');
	}

	$reqdfields[] = 'tunnel_network';
	$reqdfieldsn[] = 'Tunnel network';

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	
	if (!$input_errors) {

		$server = array();

		if ($vpnid)
			$server['vpnid'] = $vpnid;
		else
			$server['vpnid'] = openvpn_vpnid_next();

		if ($_POST['disable'] == "yes")
			$server['disable'] = true;
		$server['mode'] = $pconfig['mode'];
		if (!empty($pconfig['authmode']))
			$server['authmode'] = implode(",", $pconfig['authmode']);
		$server['protocol'] = $pconfig['protocol'];
		list($server['interface'], $server['ipaddr']) = explode ("|",$pconfig['interface']);
		$server['local_port'] = $pconfig['local_port'];
		$server['description'] = $pconfig['description'];
		$server['custom_options'] = $pconfig['custom_options'];

		if ($tls_mode) {
			if ($pconfig['tlsauth_enable']) {
				if ($pconfig['autotls_enable'])
					$pconfig['tls'] = openvpn_create_key();
				$server['tls'] = base64_encode($pconfig['tls']);
			}
			$server['caref'] = $pconfig['caref'];
			$server['certref'] = $pconfig['certref'];
			$server['dh_length'] = $pconfig['dh_length'];
		} else {
			$server['shared_key'] = base64_encode($pconfig['shared_key']);
		}
		$server['crypto'] = $pconfig['crypto'];

		$server['tunnel_network'] = $pconfig['tunnel_network'];
		$server['remote_network'] = $pconfig['remote_network'];
		$server['gwredir'] = $pconfig['gwredir'];
		$server['local_network'] = $pconfig['local_network'];
		$server['maxclients'] = $pconfig['maxclients'];
		$server['compression'] = $pconfig['compression'];
		$server['passtos'] = $pconfig['passtos'];
		$server['client2client'] = $pconfig['client2client'];

		$server['dynamic_ip'] = $pconfig['dynamic_ip'];
		$server['pool_enable'] = $pconfig['pool_enable'];

		if ($pconfig['dns_domain_enable'])
			$server['dns_domain'] = $pconfig['dns_domain'];

		if ($pconfig['dns_server_enable']) {
			$server['dns_server1'] = $pconfig['dns_server1'];
			$server['dns_server2'] = $pconfig['dns_server2'];
			$server['dns_server3'] = $pconfig['dns_server3'];
			$server['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['ntp_server_enable']) {
			$server['ntp_server1'] = $pconfig['ntp_server1'];
			$server['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$server['netbios_enable'] = $pconfig['netbios_enable'];
		$server['netbios_ntype'] = $pconfig['netbios_ntype'];
		$server['netbios_scope'] = $pconfig['netbios_scope'];

		if ($pconfig['netbios_enable']) {

			if ($pconfig['wins_server_enable']) {
				$server['wins_server1'] = $pconfig['wins_server1'];
				$server['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['dns_server_enable'])
				$server['nbdd_server1'] = $pconfig['nbdd_server1'];
		}
	
		if (isset($id) && $a_server[$id])
			$a_server[$id] = $server;
		else
			$a_server[] = $server;

		openvpn_resync('server', $server);
		write_config();
		
		header("Location: vpn_openvpn_server.php");
		exit;
	}
	if (!empty($pconfig['authmode']))
		$pconfig['authmode'] = implode(",", $pconfig['authmode']);
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
		case "server_tls":
		case "server_user":
		case "server_tls_user":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("tls_dh").style.display="";
			document.getElementById("psk").style.display="none";
			break;
		case "p2p_shared_key":
			document.getElementById("tls").style.display="none";
			document.getElementById("tls_ca").style.display="none";
			document.getElementById("tls_cert").style.display="none";
			document.getElementById("tls_dh").style.display="none";
			document.getElementById("psk").style.display="";
			break;
	}
	switch(value) {
		case "p2p_tls":
		case "p2p_shared_key":
			document.getElementById("client_opts").style.display="none";
			document.getElementById("remote_opts").style.display="";
			document.getElementById("authmodetr").style.display="none";
			break;
		case "server_user":
                case "server_tls_user":
			document.getElementById("authmodetr").style.display="";
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_opts").style.display="none";
			break;
		case "server_tls":
			document.getElementById("authmodetr").style.display="none";
		default:
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_opts").style.display="none";
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

function gwredir_change() {

	if (document.iform.gwredir.checked)
		document.getElementById("local_opts").style.display="none";
	else
		document.getElementById("local_opts").style.display="";
}

function dns_domain_change() {

	if (document.iform.dns_domain_enable.checked)
		document.getElementById("dns_domain_data").style.display="";
	else
		document.getElementById("dns_domain_data").style.display="none";
}

function dns_server_change() {

	if (document.iform.dns_server_enable.checked)
		document.getElementById("dns_server_data").style.display="";
	else
		document.getElementById("dns_server_data").style.display="none";
}

function wins_server_change() {

	if (document.iform.wins_server_enable.checked)
		document.getElementById("wins_server_data").style.display="";
	else
		document.getElementById("wins_server_data").style.display="none";
}

function ntp_server_change() {

	if (document.iform.ntp_server_enable.checked)
		document.getElementById("ntp_server_data").style.display="";
	else
		document.getElementById("ntp_server_data").style.display="none";
}

function netbios_change() {

	if (document.iform.netbios_enable.checked) {
		document.getElementById("netbios_data").style.display="";
		document.getElementById("wins_opts").style.display="";
	} else {
		document.getElementById("netbios_data").style.display="none";
		document.getElementById("wins_opts").style.display="none";
	}
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
				$tab_array[] = array(gettext("Server"), true, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
				$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
				$tab_array[] = array(gettext("Logs"), false, "diag_logs_openvpn.php");
				add_package_tabs("OpenVPN", $tab_array);
				display_top_tabs($tab_array);
			?>
			</ul>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>

			<form action="vpn_openvpn_server.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
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
											<strong>Disable this server</strong><br>
										</span>
									</td>
								</tr>
							</table>
							Set this option to disable this server without removing it from the list.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server Mode");?></td>
							<td width="78%" class="vtable">
							<select name='mode' id='mode' class="formselect" onchange='mode_change()'>
							<?php
								foreach ($openvpn_server_modes as $name => $desc):
									$selected = "";
									if ($pconfig['mode'] == $name)
										$selected = "selected";
							?>
								<option value="<?=$name;?>" <?=$selected;?>><?=$desc;?></option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="authmodetr" style="display:none">
                                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Backend for authentication");?></td>
                                                        <td width="78%" class="vtable">
                                                        <select name='authmode[]' id='authmode' class="formselect" multiple="true" size="<?php echo count($auth_servers); ?>">
							<?php $authmodes = explode(",", $pconfig['authmode']); ?>
                                                        <?php
								$auth_servers = auth_get_authserver_list();
                                                                foreach ($auth_servers as $auth_server):
                                                                        $selected = "";
                                                                        if (in_array($auth_server['name'], $authmodes))
                                                                                $selected = "selected";
                                                        ?>
                                                                <option value="<?=$auth_server['name'];?>" <?=$selected;?>><?=$auth_server['name'];?></option>
                                                        <?php 	endforeach; ?>
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
									$carplist = get_configured_carp_interface_list();
									foreach ($carplist as $cif => $carpip)
										$interfaces[$cif.'|'.$carpip] = strtoupper($cif) . " ({$carpip})";
									$aliaslist = get_configured_ip_aliases_list();
									foreach ($aliaslist as $aliasip => $aliasif)
										$interfaces[$aliasif.'|'.$aliasip] = strtoupper($aliasif) . " ({$aliasip})";
									$interfaces['any'] = "any";
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
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Local port");?></td>
						<td width="78%" class="vtable">
							<input name="local_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['local_port']);?>"/>
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
						<td width="22%" valign="top" class="vncellreq">Server Certificate</td>
							<td width="78%" class="vtable">
							<select name='certref' class="formselect">
							<?php
								foreach ($config['system']['cert'] as $cert):
									$selected = "";
									if (strstr($cert['name'], "webConfigurator"))
										continue;
									if ($pconfig['certref'] == $cert['refid'])
										$selected = "selected";
							?>
								<option value="<?=$cert['refid'];?>" <?=$selected;?>><?=$cert['name'];?></option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="tls_dh">
						<td width="22%" valign="top" class="vncellreq">DH Parameters Length</td>
						<td width="78%" class="vtable">
							<select name="dh_length" class="formselect">
								<?php
									foreach ($openvpn_dh_lengths as $length):
									$selected = '';
									if ($length == $pconfig['dh_length'])
										$selected = ' selected';
								?>
								<option<?=$selected?>><?=$length;?></option>
								<?php endforeach; ?>
							</select>
							<span class="vexpl">
								bits
							</span>
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
							communications between this server and client
							hosts expressed using CIDR (eg. 10.0.8.0/24).
							The first network address will be assigned to
							the	server virtual interface. The remaining
							network addresses can optionally be assigned
							to connecting clients. (see Address Pool)
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Redirect Gateway</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['gwredir'],$chk); ?>
										<input name="gwredir" type="checkbox" value="yes" <?=$chk;?> onClick="gwredir_change()"/>
									</td>
									<td>
										<span class="vexpl">
											Force all client generated traffic through the tunnel.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="local_opts">
						<td width="22%" valign="top" class="vncell">Local Network</td>
						<td width="78%" class="vtable">
							<input name="local_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['local_network']);?>">
							<br>
							This is the network that will be accessible
							from the remote endpoint. Expressed as a CIDR
							range. You may leave this blank if you don't
							want to add a route to the local network
							through this tunnel on the remote machine.
							This is generally set to your LAN network.
						</td>
					</tr>
					<tr id="remote_opts">
						<td width="22%" valign="top" class="vncell">Remote Network</td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['remote_network']);?>">
							<br>
							This is a network that will be routed through
							the tunnel, so that a site-to-site VPN can be
							established without manually changing the
							routing tables. Expressed as a CIDR range. If
							this is a site-to-site VPN, enter here the
							remote LAN here. You may leave this blank if
							you don't want a site-to-site VPN.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Concurrent connections");?></td>
						<td width="78%" class="vtable">
							<input name="maxclients" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['maxclients']);?>"/>
							<br/>
							Specify the maximum number of clients allowed to concurrently connect to this server.
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
						<td width="22%" valign="top" class="vncell">Inter-client communication</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['client2client'],$chk); ?>
										<input name="client2client" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											Allow communication between clients connected to this server
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">Client Settings</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Dynamic IP</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dynamic_ip'],$chk); ?>
										<input name="dynamic_ip" type="checkbox" id="dynamic_ip" value="yes" <?=$chk;?>">
									</td>
									<td>
										<span class="vexpl">
											Allow connected clients to retain their connections if their IP address changes.<br>
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Address Pool</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['pool_enable'],$chk); ?>
										<input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?=$chk;?>">
									</td>
									<td>
										<span class="vexpl">
											Provide a virtual adapter IP address to clients (see Tunnel Network)<br>
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">DNS Default Domain</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_domain_enable'],$chk); ?>
										<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=$chk;?> onClick="dns_domain_change()">
									</td>
									<td>
										<span class="vexpl">
	                                        Provide a default domain name to clients<br>
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="dns_domain_data">
								<tr>
									<td>
										<input name="dns_domain" type="text" class="formfld unknown" id="dns_domain" size="30" value="<?=htmlspecialchars($pconfig['dns_domain']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">DNS Servers</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_server_enable'],$chk); ?>
										<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=$chk;?> onClick="dns_server_change()">
									</td>
									<td>
										<span class="vexpl">
											Provide a DNS server list to clients<br>
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="dns_server_data">
								<tr>
									<td>
										<span class="vexpl">
											Server #1:&nbsp;
										</span>
										<input name="dns_server1" type="text" class="formfld unknown" id="dns_server1" size="20" value="<?=$pconfig['dns_server1'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											Server #2:&nbsp;
										</span>
										<input name="dns_server2" type="text" class="formfld unknown" id="dns_server2" size="20" value="<?=$pconfig['dns_server2'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											Server #3:&nbsp;
										</span>
										<input name="dns_server3" type="text" class="formfld unknown" id="dns_server3" size="20" value="<?=$pconfig['dns_server3'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											Server #4:&nbsp;
										</span>
										<input name="dns_server4" type="text" class="formfld unknown" id="dns_server4" size="20" value="<?=$pconfig['dns_server4'];?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">NTP Servers</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['ntp_server_enable'],$chk); ?>
										<input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=$chk;?> onClick="ntp_server_change()">
									</td>
									<td>
										<span class="vexpl">
											Provide a NTP server list to clients<br>
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="ntp_server_data">
								<tr>
									<td>
										<span class="vexpl">
											Server #1:&nbsp;
										</span>
										<input name="ntp_server1" type="text" class="formfld unknown" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											Server #2:&nbsp;
										</span>
										<input name="ntp_server2" type="text" class="formfld unknown" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">NetBIOS Options</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['netbios_enable'],$chk); ?>
										<input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=$chk;?> onClick="netbios_change()">
									</td>
									<td>
										<span class="vexpl">
											Enable NetBIOS over TCP/IP<br>
										</span>
									</td>
								</tr>
							</table>
							If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled.
							<br/>
							<table border="0" cellpadding="2" cellspacing="0" id="netbios_data">
								<tr>
									<td>
										<br/>
										<span class="vexpl">
											Node Type:&nbsp;
										</span>
										<select name='netbios_ntype' class="formselect">
										<?php
											foreach ($netbios_nodetypes as $type => $name):
												$selected = "";
												if ($pconfig['netbios_ntype'] == $type)
													$selected = "selected";
										?>
											<option value="<?=$type;?>" <?=$selected;?>><?=$name;?></option>
										<?php endforeach; ?>
										</select>
										<br/>
										Possible options: b-node (broadcasts), p-node
										(point-to-point name queries to a WINS server),
										m-node (broadcast then query name server), and
										h-node (query name server, then broadcast).
									</td>
								</tr>
								<tr>
									<td>
										<br/>
										<span class="vexpl">
											Scope ID:&nbsp;
										</span>
										<input name="netbios_scope" type="text" class="formfld unknown" id="netbios_scope" size="30" value="<?=htmlspecialchars($pconfig['netbios_scope']);?>">
										<br/>
										A NetBIOS Scope	ID provides an extended naming
										service for	NetBIOS over TCP/IP. The NetBIOS
										scope ID isolates NetBIOS traffic on a single
										network to only those nodes with the same
										NetBIOS scope ID.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="wins_opts">
						<td width="22%" valign="top" class="vncell">WINS Servers</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['wins_server_enable'],$chk); ?>
										<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=$chk;?> onClick="wins_server_change()">
									</td>
									<td>
										<span class="vexpl">
											Provide a WINS server list to clients<br>
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="wins_server_data">
								<tr>
									<td>
										<span class="vexpl">
											Server #1:&nbsp;
										</span>
										<input name="wins_server1" type="text" class="formfld unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											Server #2:&nbsp;
										</span>
										<input name="wins_server2" type="text" class="formfld unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">Advanced configuration</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Advanced</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea><br/>
										Enter any additional options you would like to add to the OpenVPN server configuration here, separated by a semicolon<br/>
										EXAMPLE: push "route 10.0.0.0 255.255.255.0";
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<br/>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts">
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="Save"> 
							<input name="act" type="hidden" value="<?=$act;?>">
							<?php if (isset($id) && $a_server[$id]): ?>
							<input name="id" type="hidden" value="<?=$id;?>">
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td width="10%" class="listhdrr">Disabled</td>
					<td width="10%" class="listhdrr">Protocol / Port</td>
					<td width="30%" class="listhdrr">Tunnel Network</td>
					<td width="40%" class="listhdrr">Description</td>
					<td width="10%" class="list"></td>
				</tr>
				<?php
					$i = 0;
					foreach($a_server as $server):
						$disabled = "NO";
						if (isset($server['disable']))
							$disabled = "YES";
				?>
				<tr>
					<td class="listlr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&id=<?=$i;?>'">
						<?=$disabled;?>
					</td>
					<td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&id=<?=$i;?>'">
						<?=htmlspecialchars($server['protocol']);?> / <?=htmlspecialchars($server['local_port']);?>
					</td>
					<td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&id=<?=$i;?>'">
						<?=htmlspecialchars($server['tunnel_network']);?>
					</td>
					<td class="listbg" ondblclick="document.location='vpn_openvpn_server.php?act=edit&id=<?=$i;?>'">
						<?=htmlspecialchars($server['description']);?>
					</td>
					<td valign="middle" nowrap class="list">
						<a href="vpn_openvpn_server.php?act=edit&id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="edit server" width="17" height="17" border="0">
						</a>
						&nbsp;
						<a href="vpn_openvpn_server.php?act=del&id=<?=$i;?>" onclick="return confirm('Do you really want to delete this server?')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="delete server" width="17" height="17" border="0">
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
						<a href="vpn_openvpn_server.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="add server" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
			</table>

			<?=gettext("Additional OpenVPN servers can be added here.");?>

			<? endif; ?>

		</td>
	</tr>
</table>
<script language="JavaScript">
<!--
mode_change();
autokey_change();
tlsauth_change();
gwredir_change();
dns_domain_change();
dns_server_change();
wins_server_change();
ntp_server_change();
netbios_change();
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