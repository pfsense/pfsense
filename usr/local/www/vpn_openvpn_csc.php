<?php 
/*
	vpn_openvpn_csc.php

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
##|*IDENT=page-openvpn-csc
##|*NAME=OpenVPN: Client Specific Override page
##|*DESCR=Allow access to the 'OpenVPN: Client Specific Override' page.
##|*MATCH=vpn_openvpn_csc.php*
##|-PRIV

require("guiconfig.inc");
require_once("openvpn.inc");

$pgtitle = array(gettext("OpenVPN"), gettext("Client Specific Override"));
$shortcut_section = "openvpn";

if (!is_array($config['openvpn']['openvpn-csc']))
	$config['openvpn']['openvpn-csc'] = array();

$a_csc = &$config['openvpn']['openvpn-csc'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($_GET['act'] == "del") {

	if (!$a_csc[$id]) {
		pfSenseHeader("vpn_openvpn_csc.php");
		exit;
	}

	openvpn_delete_csc($a_csc[$id]);
	unset($a_csc[$id]);
	write_config();
	$savemsg = gettext("Client Specific Override successfully deleted")."<br />";
}

if($_GET['act']=="edit"){

	if (isset($id) && $a_csc[$id]) {
		$pconfig['custom_options'] = $a_csc[$id]['custom_options'];
		$pconfig['disable'] = isset($a_csc[$id]['disable']);
		$pconfig['common_name'] = $a_csc[$id]['common_name'];
		$pconfig['block'] = $a_csc[$id]['block'];
		$pconfig['description'] = $a_csc[$id]['description'];

		$pconfig['tunnel_network'] = $a_csc[$id]['tunnel_network'];
		$pconfig['local_network'] = $a_csc[$id]['local_network'];
		$pconfig['local_networkv6'] = $a_csc[$id]['local_networkv6'];
		$pconfig['remote_network'] = $a_csc[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_csc[$id]['remote_networkv6'];
		$pconfig['gwredir'] = $a_csc[$id]['gwredir'];

		$pconfig['push_reset'] = $a_csc[$id]['push_reset'];

		$pconfig['dns_domain'] = $a_csc[$id]['dns_domain'];
		if ($pconfig['dns_domain'])
			$pconfig['dns_domain_enable'] = true;

		$pconfig['dns_server1'] = $a_csc[$id]['dns_server1'];
		$pconfig['dns_server2'] = $a_csc[$id]['dns_server2'];
		$pconfig['dns_server3'] = $a_csc[$id]['dns_server3'];
		$pconfig['dns_server4'] = $a_csc[$id]['dns_server4'];
		if ($pconfig['dns_server1'] ||
			$pconfig['dns_server2'] ||
			$pconfig['dns_server3'] ||
			$pconfig['dns_server4'])
			$pconfig['dns_server_enable'] = true;

		$pconfig['ntp_server1'] = $a_csc[$id]['ntp_server1'];
		$pconfig['ntp_server2'] = $a_csc[$id]['ntp_server2'];
		if ($pconfig['ntp_server1'] ||
			$pconfig['ntp_server2'])
			$pconfig['ntp_server_enable'] = true;

		$pconfig['netbios_enable'] = $a_csc[$id]['netbios_enable'];
		$pconfig['netbios_ntype'] = $a_csc[$id]['netbios_ntype'];
		$pconfig['netbios_scope'] = $a_csc[$id]['netbios_scope'];

		$pconfig['wins_server1'] = $a_csc[$id]['wins_server1'];
		$pconfig['wins_server2'] = $a_csc[$id]['wins_server2'];
		if ($pconfig['wins_server1'] ||
			$pconfig['wins_server2'])
			$pconfig['wins_server_enable'] = true;

		$pconfig['nbdd_server1'] = $a_csc[$id]['nbdd_server1'];
		if ($pconfig['nbdd_server1'])
			$pconfig['nbdd_server_enable'] = true;
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'Tunnel network'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6"))
		$input_errors[] = $result;

	if ($pconfig['dns_server_enable']) {
		if (!empty($pconfig['dns_server1']) && !is_ipaddr(trim($pconfig['dns_server1'])))
			$input_errors[] = gettext("The field 'DNS Server #1' must contain a valid IP address");
		if (!empty($pconfig['dns_server2']) && !is_ipaddr(trim($pconfig['dns_server2'])))
			$input_errors[] = gettext("The field 'DNS Server #2' must contain a valid IP address");
		if (!empty($pconfig['dns_server3']) && !is_ipaddr(trim($pconfig['dns_server3'])))
			$input_errors[] = gettext("The field 'DNS Server #3' must contain a valid IP address");
		if (!empty($pconfig['dns_server4']) && !is_ipaddr(trim($pconfig['dns_server4'])))
			$input_errors[] = gettext("The field 'DNS Server #4' must contain a valid IP address");
	}

	if ($pconfig['ntp_server_enable']) {
		if (!empty($pconfig['ntp_server1']) && !is_ipaddr(trim($pconfig['ntp_server1'])))
			$input_errors[] = gettext("The field 'NTP Server #1' must contain a valid IP address");
		if (!empty($pconfig['ntp_server2']) && !is_ipaddr(trim($pconfig['ntp_server2'])))
			$input_errors[] = gettext("The field 'NTP Server #2' must contain a valid IP address");
		if (!empty($pconfig['ntp_server3']) && !is_ipaddr(trim($pconfig['ntp_server3'])))
			$input_errors[] = gettext("The field 'NTP Server #3' must contain a valid IP address");
		if (!empty($pconfig['ntp_server4']) && !is_ipaddr(trim($pconfig['ntp_server4'])))
			$input_errors[] = gettext("The field 'NTP Server #4' must contain a valid IP address");
	}

	if ($pconfig['netbios_enable']) {
		if ($pconfig['wins_server_enable']) {
			if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1'])))
				$input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
			if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2'])))
				$input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
		}
		if ($pconfig['nbdd_server_enable'])
			if (!empty($pconfig['nbdd_server1']) && !is_ipaddr(trim($pconfig['nbdd_server1'])))
				$input_errors[] = gettext("The field 'NetBIOS Data Distribution Server #1' must contain a valid IP address");
	}

	$reqdfields[] = 'common_name';
	$reqdfieldsn[] = 'Common name';

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {

		$csc = array();

		$csc['custom_options'] = $pconfig['custom_options'];
		if ($_POST['disable'] == "yes")
			$csc['disable'] = true;
		$csc['common_name'] = $pconfig['common_name'];
		$csc['block'] = $pconfig['block'];
		$csc['description'] = $pconfig['description'];

		$csc['tunnel_network'] = $pconfig['tunnel_network'];
		$csc['local_network'] = $pconfig['local_network'];
		$csc['local_networkv6'] = $pconfig['local_networkv6'];
		$csc['remote_network'] = $pconfig['remote_network'];
		$csc['remote_networkv6'] = $pconfig['remote_networkv6'];
		$csc['gwredir'] = $pconfig['gwredir'];

		$csc['push_reset'] = $pconfig['push_reset'];

		if ($pconfig['dns_domain_enable'])
			$csc['dns_domain'] = $pconfig['dns_domain'];

		if ($pconfig['dns_server_enable']) {
			$csc['dns_server1'] = $pconfig['dns_server1'];
			$csc['dns_server2'] = $pconfig['dns_server2'];
			$csc['dns_server3'] = $pconfig['dns_server3'];
			$csc['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['ntp_server_enable']) {
			$csc['ntp_server1'] = $pconfig['ntp_server1'];
			$csc['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$csc['netbios_enable'] = $pconfig['netbios_enable'];
		$csc['netbios_ntype'] = $pconfig['netbios_ntype'];
		$csc['netbios_scope'] = $pconfig['netbios_scope'];

		if ($pconfig['netbios_enable']) {

			if ($pconfig['wins_server_enable']) {
				$csc['wins_server1'] = $pconfig['wins_server1'];
				$csc['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['dns_server_enable'])
				$csc['nbdd_server1'] = $pconfig['nbdd_server1'];
		}
	
		if (isset($id) && $a_csc[$id]) {
			$old_csc_cn = $a_csc[$id]['common_name'];
			$a_csc[$id] = $csc;
		} else
			$a_csc[] = $csc;

		if (!empty($old_csc_cn))
			openvpn_cleanup_csc($old_csc_cn);
		openvpn_resync_csc($csc);
		write_config();
		
		header("Location: vpn_openvpn_csc.php");
		exit;
	}
}

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
<!--

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
				$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), true, "vpn_openvpn_csc.php");
				$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
				add_package_tabs("OpenVPN", $tab_array);
				display_top_tabs($tab_array);
			?>
			</ul>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>

			<form action="vpn_openvpn_csc.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
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
											<strong><?=gettext("Disable this override"); ?></strong><br />
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("Set this option to disable this client-specific override without removing it from the list"); ?>.
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Common name"); ?></td>
						<td width="78%" class="vtable"> 
							<input name="common_name" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['common_name']);?>">
							<br />
							<?=gettext("Enter the client's X.509 common name here"); ?>.
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
						<td width="78%" class="vtable"> 
							<input name="description" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>">
							<br />
							<?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Connection blocking"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['block'],$chk); ?>
										<input name="block" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Block this client connection based on its common name"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("Don't use this option to permanently disable a " .
							"client due to a compromised key or password. " .
							"Use a CRL (certificate revocation list) instead"); ?>.
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Tunnel Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_network']);?>">
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
					<tr id="local_optsv4">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Local Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="local_network" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['local_network']);?>">
							<br />
							<?=gettext("These are the IPv4 networks that will be accessible " .
							"from this particular client. Expressed as a comma-separated list of one or more CIDR ranges."); ?>
							<br /><?=gettext("NOTE: You do not need to specify networks here if they have " .
							"already been defined on the main server configuration.");?>
						</td>
					</tr>
					<tr id="local_optsv6">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Local Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="local_networkv6" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['local_networkv6']);?>">
							<br />
							<?=gettext("These are the IPv6 networks that will be accessible " .
							"from this particular client. Expressed as a comma-separated list of one or more IP/PREFIX networks."); ?>
							<br /><?=gettext("NOTE: You do not need to specify networks here if they have " .
							"already been defined on the main server configuration.");?>
						</td>
					</tr>
					<tr id="remote_optsv4">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_network']);?>">
							<br />
							<?=gettext("These are the IPv4 networks that will be routed " .
							"to this client specifically using iroute, so that a site-to-site " .
							"VPN can be established. " .
							"Expressed as a comma-separated list of one or more CIDR ranges. " .
							"You may leave this blank if there are no client-side networks to " .
							"be routed"); ?>.
							<br /><?=gettext("NOTE: Remember to add these subnets to the " .
							"IPv4 Remote Networks list on the corresponding OpenVPN server settings.");?>
						</td>
					</tr>
					<tr id="remote_optsv6">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_networkv6" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_networkv6']);?>">
							<br />
							<?=gettext("These are the IPv6 networks that will be routed " .
							"to this client specifically using iroute, so that a site-to-site " .
							"VPN can be established. " .
							"Expressed as a comma-separated list of one or more IP/PREFIX networks. " .
							"You may leave this blank if there are no client-side networks to " .
							"be routed"); ?>.
							<br /><?=gettext("NOTE: Remember to add these subnets to the " .
							"IPv6 Remote Networks list on the corresponding OpenVPN server settings.");?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Redirect Gateway"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['gwredir'],$chk); ?>
										<input name="gwredir" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Force all client generated traffic through the tunnel"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Client Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Server Definitions"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['push_reset'],$chk); ?>
										<input name="push_reset" type="checkbox" value="yes" <?=$chk;?>/>
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Prevent this client from receiving any server-defined client settings"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Default Domain"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_domain_enable'],$chk); ?>
										<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=$chk;?> onClick="dns_domain_change()">
									</td>
									<td>
										<span class="vexpl">
	                                        <?=gettext("Provide a default domain name to clients"); ?><br />
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
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_server_enable'],$chk); ?>
										<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=$chk;?> onClick="dns_server_change()">
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a DNS server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="dns_server_data">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="dns_server1" type="text" class="formfld unknown" id="dns_server1" size="20" value="<?=htmlspecialchars($pconfig['dns_server1']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="dns_server2" type="text" class="formfld unknown" id="dns_server2" size="20" value="<?=htmlspecialchars($pconfig['dns_server2']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #3:&nbsp;
										</span>
										<input name="dns_server3" type="text" class="formfld unknown" id="dns_server3" size="20" value="<?=htmlspecialchars($pconfig['dns_server3']);?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #4:&nbsp;
										</span>
										<input name="dns_server4" type="text" class="formfld unknown" id="dns_server4" size="20" value="<?=htmlspecialchars($pconfig['dns_server4']);?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("NTP Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['ntp_server_enable'],$chk); ?>
										<input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=$chk;?> onClick="ntp_server_change()">
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a NTP server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="ntp_server_data">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="ntp_server1" type="text" class="formfld unknown" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="ntp_server2" type="text" class="formfld unknown" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("NetBIOS Options"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['netbios_enable'],$chk); ?>
										<input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=$chk;?> onClick="netbios_change()">
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Enable NetBIOS over TCP/IP"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled"); ?>.
							<br />
							<table border="0" cellpadding="2" cellspacing="0" id="netbios_data">
								<tr>
									<td>
										<br />
										<span class="vexpl">
											<?=gettext("Node Type"); ?>:&nbsp;
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
										<br />
										<?=gettext("Possible options: b-node (broadcasts), p-node " .
										"(point-to-point name queries to a WINS server), " .
										"m-node (broadcast then query name server), and " .
										"h-node (query name server, then broadcast)"); ?>.
									</td>
								</tr>
								<tr>
									<td>
										<br />
										<span class="vexpl">
											Scope ID:&nbsp;
										</span>
										<input name="netbios_scope" type="text" class="formfld unknown" id="netbios_scope" size="30" value="<?=htmlspecialchars($pconfig['netbios_scope']);?>">
										<br />
										<?=gettext("A NetBIOS Scope	ID provides an extended naming " .
										"service for	NetBIOS over TCP/IP. The NetBIOS " .
										"scope ID isolates NetBIOS traffic on a single " .
										"network to only those nodes with the same " .
										"NetBIOS scope ID"); ?>.
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="wins_opts">
						<td width="22%" valign="top" class="vncell"><?=gettext("WINS Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<?php set_checked($pconfig['wins_server_enable'],$chk); ?>
										<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=$chk;?> onClick="wins_server_change()">
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a WINS server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="wins_server_data">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="wins_server1" type="text" class="formfld unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>">
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="wins_server2" type="text" class="formfld unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>">
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Advanced"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0">
								<tr>
									<td>
										<textarea rows="6" cols="70" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea><br />
										<?=gettext("Enter any additional options you would like to add for this client specific override, separated by a semicolon"); ?><br />
										<?=gettext("EXAMPLE: push \"route 10.0.0.0 255.255.255.0\""); ?>;
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>"> 
							<input name="act" type="hidden" value="<?=$act;?>">
							<?php if (isset($id) && $a_csc[$id]): ?>
							<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table width="100%" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Common Name"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
				<?php
					$i = 0;
					foreach($a_csc as $csc):
						$disabled = "NO";
						if (isset($csc['disable']))
							$disabled = "YES";
				?>
				<tr ondblclick="document.location='vpn_openvpn_csc.php?act=edit&id=<?=$i;?>'">
					<td class="listlr">
						<?=$disabled;?>
					</td>
					<td class="listr">
						<?=htmlspecialchars($csc['common_name']);?>
					</td>
					<td class="listbg">
						<?=htmlspecialchars($csc['description']);?>
					</td>
					<td valign="middle" nowrap class="list">
						<a href="vpn_openvpn_csc.php?act=edit&id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit csc"); ?>" width="17" height="17" border="0">
						</a>
						&nbsp;
						<a href="vpn_openvpn_csc.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this csc?"); ?>')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete csc"); ?>" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
				?>
				<tr>
					<td class="list" colspan="3"></td>
					<td class="list">
						<a href="vpn_openvpn_csc.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add csc"); ?>" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<p>
							<?=gettext("Additional OpenVPN client specific overrides can be added here.");?>
						</p>
					</td>
				</tr>
			</table>

			<?php endif; ?>

		</td>
	</tr>
</table>
<script type="text/javascript">
<!--
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

