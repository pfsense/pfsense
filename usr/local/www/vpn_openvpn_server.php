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

if (!is_array($config['openvpn']['openvpn-server']))
	$config['openvpn']['openvpn-server'] = array();

$a_server = &$config['openvpn']['openvpn-server'];

if (!is_array($config['ca']))
	$config['ca'] = array();

$a_ca =& $config['ca'];

if (!is_array($config['cert']))
	$config['cert'] = array();

$a_cert =& $config['cert'];

if (!is_array($config['crl']))
	$config['crl'] = array();

$a_crl =& $config['crl'];

foreach ($a_crl as $cid => $acrl)
	if (!isset($acrl['refid']))
		unset ($a_crl[$cid]);

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if (isset($id) && $a_server[$id])
	$vpnid = $a_server[$id]['vpnid'];
else
	$vpnid = 0;

if ($_GET['act'] == "del") {

	if (!isset($a_server[$id])) {
		pfSenseHeader("vpn_openvpn_server.php");
		exit;
	}
	if (!empty($a_server[$id]))
		openvpn_delete('server', $a_server[$id]);
	unset($a_server[$id]);
	write_config();
	$savemsg = gettext("Server successfully deleted")."<br />";
}

if($_GET['act']=="new"){
	$pconfig['autokey_enable'] = "yes";
	$pconfig['tlsauth_enable'] = "yes";
	$pconfig['autotls_enable'] = "yes";
	$pconfig['dh_length'] = 1024;
	$pconfig['dev_mode'] = "tun";
	$pconfig['interface'] = "wan";
	$pconfig['local_port'] = openvpn_port_next('UDP');
	$pconfig['pool_enable'] = "yes";
	$pconfig['cert_depth'] = 1;
	$pconfig['verbosity_level'] = 1; // Default verbosity is 1
	// OpenVPN Defaults to SHA1
	$pconfig['digest'] = "SHA1";
}

if($_GET['act']=="edit"){

	if (isset($id) && $a_server[$id]) {
		$pconfig['disable'] = isset($a_server[$id]['disable']);
		$pconfig['mode'] = $a_server[$id]['mode'];
		$pconfig['protocol'] = $a_server[$id]['protocol'];
		$pconfig['authmode'] = $a_server[$id]['authmode'];
		$pconfig['dev_mode'] = $a_server[$id]['dev_mode'];
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
			$pconfig['crlref'] = $a_server[$id]['crlref'];
			$pconfig['certref'] = $a_server[$id]['certref'];
			$pconfig['dh_length'] = $a_server[$id]['dh_length'];
			if (isset($a_server[$id]['cert_depth']))
				$pconfig['cert_depth'] = $a_server[$id]['cert_depth'];
			else
				$pconfig['cert_depth'] = 1;
			if ($pconfig['mode'] == "server_tls_user")
				$pconfig['strictusercn'] = $a_server[$id]['strictusercn'];
		} else
			$pconfig['shared_key'] = base64_decode($a_server[$id]['shared_key']);
		$pconfig['crypto'] = $a_server[$id]['crypto'];
		// OpenVPN Defaults to SHA1 if unset
		$pconfig['digest'] = !empty($a_server[$id]['digest']) ? $a_server[$id]['digest'] : "SHA1";
		$pconfig['engine'] = $a_server[$id]['engine'];

		$pconfig['tunnel_network'] = $a_server[$id]['tunnel_network'];
		$pconfig['tunnel_networkv6'] = $a_server[$id]['tunnel_networkv6'];

		$pconfig['remote_network'] = $a_server[$id]['remote_network'];
		$pconfig['remote_networkv6'] = $a_server[$id]['remote_networkv6'];
		$pconfig['gwredir'] = $a_server[$id]['gwredir'];
		$pconfig['local_network'] = $a_server[$id]['local_network'];
		$pconfig['local_networkv6'] = $a_server[$id]['local_networkv6'];
		$pconfig['maxclients'] = $a_server[$id]['maxclients'];
		$pconfig['compression'] = $a_server[$id]['compression'];
		$pconfig['passtos'] = $a_server[$id]['passtos'];
		$pconfig['client2client'] = $a_server[$id]['client2client'];

		$pconfig['dynamic_ip'] = $a_server[$id]['dynamic_ip'];
		$pconfig['pool_enable'] = $a_server[$id]['pool_enable'];
		$pconfig['topology_subnet'] = $a_server[$id]['topology_subnet'];

		$pconfig['serverbridge_dhcp'] = $a_server[$id]['serverbridge_dhcp'];
		$pconfig['serverbridge_interface'] = $a_server[$id]['serverbridge_interface'];
		$pconfig['serverbridge_dhcp_start'] = $a_server[$id]['serverbridge_dhcp_start'];
		$pconfig['serverbridge_dhcp_end'] = $a_server[$id]['serverbridge_dhcp_end'];

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

		$pconfig['client_mgmt_port'] = $a_server[$id]['client_mgmt_port'];
		if ($pconfig['client_mgmt_port'])
			$pconfig['client_mgmt_port_enable'] = true;

		$pconfig['nbdd_server1'] = $a_server[$id]['nbdd_server1'];
		if ($pconfig['nbdd_server1'])
			$pconfig['nbdd_server_enable'] = true;

		// just in case the modes switch
		$pconfig['autokey_enable'] = "yes";
		$pconfig['autotls_enable'] = "yes";

		$pconfig['duplicate_cn'] = isset($a_server[$id]['duplicate_cn']);
		
		$pconfig['no_tun_ipv6'] = $a_server[$id]['no_tun_ipv6'];
		if (isset($a_server[$id]['verbosity_level']))
			$pconfig['verbosity_level'] = $a_server[$id]['verbosity_level'];
		else
			$pconfig['verbosity_level'] = 1; // Default verbosity is 1
		
		$pconfig['push_register_dns'] = $a_server[$id]['push_register_dns'];
	}
}
if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($id) && $a_server[$id])
		$vpnid = $a_server[$id]['vpnid'];
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

	if (empty($pconfig['authmode']) && (($pconfig['mode'] == "server_user") || ($pconfig['mode'] == "server_tls_user")))
		$input_errors[] = gettext("You must select a Backend for Authentication if the server mode requires User Auth.");

	/* input validation */
	if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6"))
		$input_errors[] = $result;

	$portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
	if (($portused != $vpnid) && ($portused != 0))
		$input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");

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

	if ($pconfig['client_mgmt_port_enable']) {
		if ($result = openvpn_validate_port($pconfig['client_mgmt_port'], 'Client management port'))
			$input_errors[] = $result;
	}

	if ($pconfig['maxclients'] && !is_numeric($pconfig['maxclients']))
		$input_errors[] = gettext("The field 'Concurrent connections' must be numeric.");

	/* If we are not in shared key mode, then we need the CA/Cert. */
	if ($pconfig['mode'] != "p2p_shared_key") {
		$reqdfields = explode(" ", "caref certref");
		$reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
	} elseif (!$pconfig['autokey_enable']) {
		/* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		$reqdfields = array('shared_key');
		$reqdfieldsn = array(gettext('Shared key'));
	}

	if ($pconfig['dev_mode'] != "tap") {
		$reqdfields[] = 'tunnel_network';
		$reqdfieldsn[] = gettext('Tunnel network');
	} else {
		if ($pconfig['serverbridge_dhcp'] && $pconfig['tunnel_network'])
			$input_errors[] = gettext("Using a tunnel network and server bridge settings together is not allowed.");
		if (($pconfig['serverbridge_dhcp_start'] && !$pconfig['serverbridge_dhcp_end']) 
		|| (!$pconfig['serverbridge_dhcp_start'] && $pconfig['serverbridge_dhcp_end']))
			$input_errors[] = gettext("Server Bridge DHCP Start and End must both be empty, or defined.");
		if (($pconfig['serverbridge_dhcp_start'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_start'])))
			$input_errors[] = gettext("Server Bridge DHCP Start must be an IPv4 address.");
		if (($pconfig['serverbridge_dhcp_end'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_end'])))
			$input_errors[] = gettext("Server Bridge DHCP End must be an IPv4 address.");
		if (ip2ulong($pconfig['serverbridge_dhcp_start']) > ip2ulong($pconfig['serverbridge_dhcp_end']))
			$input_errors[] = gettext("The Server Bridge DHCP range is invalid (start higher than end).");
	}
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	
	if (!$input_errors) {

		$server = array();

		if ($id && $pconfig['dev_mode'] <> $a_server[$id]['dev_mode'])
			openvpn_delete('server', $a_server[$id]);// delete(rename) old interface so a new TUN or TAP interface can be created.

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
		$server['dev_mode'] = $pconfig['dev_mode'];
		list($server['interface'], $server['ipaddr']) = explode ("|",$pconfig['interface']);
		$server['local_port'] = $pconfig['local_port'];
		$server['description'] = $pconfig['description'];
		$server['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

		if ($tls_mode) {
			if ($pconfig['tlsauth_enable']) {
				if ($pconfig['autotls_enable'])
					$pconfig['tls'] = openvpn_create_key();
				$server['tls'] = base64_encode($pconfig['tls']);
			}
			$server['caref'] = $pconfig['caref'];
			$server['crlref'] = $pconfig['crlref'];
			$server['certref'] = $pconfig['certref'];
			$server['dh_length'] = $pconfig['dh_length'];
			$server['cert_depth'] = $pconfig['cert_depth'];
			if ($pconfig['mode'] == "server_tls_user")
				$server['strictusercn'] = $pconfig['strictusercn'];
		} else {
			$server['shared_key'] = base64_encode($pconfig['shared_key']);
		}
		$server['crypto'] = $pconfig['crypto'];
		$server['digest'] = $pconfig['digest'];
		$server['engine'] = $pconfig['engine'];

		$server['tunnel_network'] = $pconfig['tunnel_network'];
		$server['tunnel_networkv6'] = $pconfig['tunnel_networkv6'];
		$server['remote_network'] = $pconfig['remote_network'];
		$server['remote_networkv6'] = $pconfig['remote_networkv6'];
		$server['gwredir'] = $pconfig['gwredir'];
		$server['local_network'] = $pconfig['local_network'];
		$server['local_networkv6'] = $pconfig['local_networkv6'];
		$server['maxclients'] = $pconfig['maxclients'];
		$server['compression'] = $pconfig['compression'];
		$server['passtos'] = $pconfig['passtos'];
		$server['client2client'] = $pconfig['client2client'];

		$server['dynamic_ip'] = $pconfig['dynamic_ip'];
		$server['pool_enable'] = $pconfig['pool_enable'];
		$server['topology_subnet'] = $pconfig['topology_subnet'];

		$server['serverbridge_dhcp'] = $pconfig['serverbridge_dhcp'];
		$server['serverbridge_interface'] = $pconfig['serverbridge_interface'];
		$server['serverbridge_dhcp_start'] = $pconfig['serverbridge_dhcp_start'];
		$server['serverbridge_dhcp_end'] = $pconfig['serverbridge_dhcp_end'];

		if ($pconfig['dns_domain_enable'])
			$server['dns_domain'] = $pconfig['dns_domain'];

		if ($pconfig['dns_server_enable']) {
			$server['dns_server1'] = $pconfig['dns_server1'];
			$server['dns_server2'] = $pconfig['dns_server2'];
			$server['dns_server3'] = $pconfig['dns_server3'];
			$server['dns_server4'] = $pconfig['dns_server4'];
		}

		if ($pconfig['push_register_dns'])
			$server['push_register_dns'] = $pconfig['push_register_dns'];

		if ($pconfig['ntp_server_enable']) {
			$server['ntp_server1'] = $pconfig['ntp_server1'];
			$server['ntp_server2'] = $pconfig['ntp_server2'];
		}

		$server['netbios_enable'] = $pconfig['netbios_enable'];
		$server['netbios_ntype'] = $pconfig['netbios_ntype'];
		$server['netbios_scope'] = $pconfig['netbios_scope'];
		 
		$server['no_tun_ipv6'] = $pconfig['no_tun_ipv6'];
		$server['verbosity_level'] = $pconfig['verbosity_level'];

		if ($pconfig['netbios_enable']) {

			if ($pconfig['wins_server_enable']) {
				$server['wins_server1'] = $pconfig['wins_server1'];
				$server['wins_server2'] = $pconfig['wins_server2'];
			}

			if ($pconfig['dns_server_enable'])
				$server['nbdd_server1'] = $pconfig['nbdd_server1'];
		}

		if ($pconfig['client_mgmt_port_enable'])
			$server['client_mgmt_port'] = $pconfig['client_mgmt_port'];

		if ($_POST['duplicate_cn'] == "yes")
			$server['duplicate_cn'] = true;

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
$pgtitle = array(gettext("OpenVPN"), gettext("Server"));
$shortcut_section = "openvpn";

include("head.inc");

?>

<body link="#000000" vlink="#000000" alink="#000000" onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function mode_change() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	switch(value) {
		case "p2p_tls":
		case "server_tls":
		case "server_user":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_crl").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("tls_dh").style.display="";
			document.getElementById("cert_depth").style.display="";
			document.getElementById("strictusercn").style.display="none";
			document.getElementById("psk").style.display="none";
			break;
		case "server_tls_user":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_crl").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("tls_dh").style.display="";
			document.getElementById("cert_depth").style.display="";
			document.getElementById("strictusercn").style.display="";
			document.getElementById("psk").style.display="none";
			break;
		case "p2p_shared_key":
			document.getElementById("tls").style.display="none";
			document.getElementById("tls_ca").style.display="none";
			document.getElementById("tls_crl").style.display="none";
			document.getElementById("tls_cert").style.display="none";
			document.getElementById("tls_dh").style.display="none";
			document.getElementById("cert_depth").style.display="none";
			document.getElementById("strictusercn").style.display="none";
			document.getElementById("psk").style.display="";
			break;
	}
	switch(value) {
		case "p2p_shared_key":
			document.getElementById("client_opts").style.display="none";
			document.getElementById("remote_optsv4").style.display="";
			document.getElementById("remote_optsv6").style.display="";
			document.getElementById("gwredir_opts").style.display="none";
			document.getElementById("local_optsv4").style.display="none";
			document.getElementById("local_optsv6").style.display="none";
			document.getElementById("authmodetr").style.display="none";
			document.getElementById("inter_client_communication").style.display="none";
			break;
		case "p2p_tls":
			document.getElementById("client_opts").style.display="none";
			document.getElementById("remote_optsv4").style.display="";
			document.getElementById("remote_optsv6").style.display="";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("authmodetr").style.display="none";
			document.getElementById("inter_client_communication").style.display="none";
			break;
		case "server_user":
                case "server_tls_user":
			document.getElementById("authmodetr").style.display="";
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_optsv4").style.display="none";
			document.getElementById("remote_optsv6").style.display="none";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("inter_client_communication").style.display="";
			break;
		case "server_tls":
			document.getElementById("authmodetr").style.display="none";
		default:
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_optsv4").style.display="none";
			document.getElementById("remote_optsv6").style.display="none";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("inter_client_communication").style.display="";
			break;
	}
	gwredir_change();
}

function autokey_change() {

	if ((document.iform.autokey_enable != null) && (document.iform.autokey_enable.checked))
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

	if (document.iform.gwredir.checked) {
		document.getElementById("local_optsv4").style.display="none";
		document.getElementById("local_optsv6").style.display="none";
	} else {
		document.getElementById("local_optsv4").style.display="";
		document.getElementById("local_optsv6").style.display="";
	}
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

function client_mgmt_port_change() {

	if (document.iform.client_mgmt_port_enable.checked)
		document.getElementById("client_mgmt_port_data").style.display="";
	else
		document.getElementById("client_mgmt_port_data").style.display="none";
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

function tuntap_change() {

	mindex = document.iform.mode.selectedIndex;
	mvalue = document.iform.mode.options[mindex].value;

	switch(mvalue) {
		case "p2p_tls":
		case "p2p_shared_key":
			p2p = true;
			break;
		default:
			p2p = false;
			break;
	}

	index = document.iform.dev_mode.selectedIndex;
	value = document.iform.dev_mode.options[index].value;
	switch(value) {
		case "tun":
			document.getElementById("chkboxNoTunIPv6").style.display="";
			document.getElementById("ipv4_tunnel_network").className="vncellreq";
			document.getElementById("serverbridge_dhcp").style.display="none";
			document.getElementById("serverbridge_interface").style.display="none";
			document.getElementById("serverbridge_dhcp_start").style.display="none";
			document.getElementById("serverbridge_dhcp_end").style.display="none";
			document.getElementById("topology_subnet_opt").style.display="";
			break;
		case "tap":
			document.getElementById("chkboxNoTunIPv6").style.display="none";
			document.getElementById("ipv4_tunnel_network").className="vncell";
			if (!p2p) {
				document.getElementById("serverbridge_dhcp").style.display="";
				document.getElementById("serverbridge_interface").style.display="";
				document.getElementById("serverbridge_dhcp_start").style.display="";
				document.getElementById("serverbridge_dhcp_end").style.display="";
				document.getElementById("topology_subnet_opt").style.display="none";
				document.iform.serverbridge_dhcp.disabled = false;
				if (document.iform.serverbridge_dhcp.checked) {
					document.iform.serverbridge_interface.disabled = false;
					document.iform.serverbridge_dhcp_start.disabled = false;
					document.iform.serverbridge_dhcp_end.disabled = false;
				} else {
					document.iform.serverbridge_interface.disabled = true;
					document.iform.serverbridge_dhcp_start.disabled = true;
					document.iform.serverbridge_dhcp_end.disabled = true;
				}
			} else {
				document.getElementById("topology_subnet_opt").style.display="none";
				document.iform.serverbridge_dhcp.disabled = true;
				document.iform.serverbridge_interface.disabled = true;
				document.iform.serverbridge_dhcp_start.disabled = true;
				document.iform.serverbridge_dhcp_end.disabled = true;
			}
			break;
	}
}
//]]>
</script>
<?php
if (!$savemsg)
	$savemsg = "";

if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box_np($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="vpn openvpn server">
	<tr>
		<td class="tabnavtbl">
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Server"), true, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
				$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
				add_package_tabs("OpenVPN", $tab_array);
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>    
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>

			<form action="vpn_openvpn_server.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="general information">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("General information"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Disabled"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="0" cellspacing="0" summary="enable disable server">
								<tr>
									<td>
										<?php set_checked($pconfig['disable'],$chk); ?>
										<input name="disable" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										&nbsp;
										<span class="vexpl">
											<strong><?=gettext("Disable this server"); ?></strong><br />
										</span>
									</td>
								</tr>
							</table>
							<?=gettext("Set this option to disable this server without removing it from the list"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server Mode");?></td>
							<td width="78%" class="vtable">
							<select name='mode' id='mode' class="formselect" onchange='mode_change(); tuntap_change()'>
							<?php
								foreach ($openvpn_server_modes as $name => $desc):
									$selected = "";
									if ($pconfig['mode'] == $name)
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$name;?>" <?=$selected;?>><?=$desc;?></option>
							<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="authmodetr" style="display:none">
                                                <td width="22%" valign="top" class="vncellreq"><?=gettext("Backend for authentication");?></td>
                                                        <td width="78%" class="vtable">
                                                        <select name='authmode[]' id='authmode' class="formselect" multiple="multiple" size="<?php echo count($auth_servers); ?>">
							<?php $authmodes = explode(",", $pconfig['authmode']); ?>
                                                        <?php
								$auth_servers = auth_get_authserver_list();
                                                                foreach ($auth_servers as $auth_server):
                                                                        $selected = "";
                                                                        if (in_array($auth_server['name'], $authmodes))
                                                                                $selected = "selected=\"selected\"";
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
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$prot;?>" <?=$selected;?>><?=$prot;?></option>
							<?php endforeach; ?>
							</select>
							</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Device Mode"); ?></td>
						<td width="78%" class="vtable">
							<select name="dev_mode" class="formselect" onchange='tuntap_change()'>
                                                        <?php
                                                                foreach ($openvpn_dev_mode as $device):
                                                                       $selected = "";
                                                                       if (! empty($pconfig['dev_mode'])) {
                                                                               if ($pconfig['dev_mode'] == $device)
                                                                                       $selected = "selected=\"selected\"";
                                                                       } else {
                                                                               if ($device == "tun")
                                                                                       $selected = "selected=\"selected\"";
                                                                       }
                                                        ?>
                                                                <option value="<?=$device;?>" <?=$selected;?>><?=$device;?></option>
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
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Local port");?></td>
						<td width="78%" class="vtable">
							<input name="local_port" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['local_port']);?>" />
						</td>
					</tr>
					<tr> 
						<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
						<td width="78%" class="vtable"> 
							<input name="description" type="text" class="formfld unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>" />
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
							<table border="0" cellpadding="2" cellspacing="0" summary="tls authentication">
								<tr>
									<td>
										<?php set_checked($pconfig['tlsauth_enable'],$chk); ?>
										<input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=$chk;?> onclick="tlsauth_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Enable authentication of TLS packets"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php if (!$pconfig['tls']): ?>
							<table border="0" cellpadding="2" cellspacing="0" id="tlsauth_opts" summary="tls authentication options">
								<tr>
									<td>
										<?php set_checked($pconfig['autotls_enable'],$chk); ?>
										<input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autotls_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared TLS authentication key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id="autotls_opts" summary="tls authentication key">
								<tr>
									<td>
										<textarea name="tls" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['tls']);?></textarea>
										<br />
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
								<b>No Certificate Authorities defined.</b> <br />Create one under <a href="system_camanager.php">System &gt; Cert Manager</a>.
							<?php endif; ?>
							</td>
					</tr>
					<tr id="tls_crl">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Peer Certificate Revocation List"); ?></td>
							<td width="78%" class="vtable">
							<?php if (count($a_crl)): ?>
							<select name='crlref' class="formselect">
								<option value="">None</option>
							<?php
								foreach ($a_crl as $crl):
									$selected = "";
									$caname = "";
									$ca = lookup_ca($crl['caref']);
									if ($ca) {
										$caname = " (CA: {$ca['descr']})";
										if ($pconfig['crlref'] == $crl['refid'])
											$selected = "selected=\"selected\"";
									}
							?>
								<option value="<?=$crl['refid'];?>" <?=$selected;?>><?=$crl['descr'] . $caname;?></option>
							<?php endforeach; ?>
							</select>
							<?php else: ?>
								<b>No Certificate Revocation Lists (CRLs) defined.</b> <br />Create one under <a href="system_crlmanager.php">System &gt; Cert Manager</a>.
							<?php endif; ?>
							</td>
					</tr>
					<tr id="tls_cert">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Server Certificate"); ?></td>
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
								<b>No Certificates defined.</b> <br />Create one under <a href="system_certmanager.php">System &gt; Cert Manager</a>.
							<?php endif; ?>
						</td>
					</tr>
					<tr id="tls_dh">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("DH Parameters Length"); ?></td>
						<td width="78%" class="vtable">
							<select name="dh_length" class="formselect">
								<?php
									foreach ($openvpn_dh_lengths as $length):
									$selected = "";
									if ($length == $pconfig['dh_length'])
										$selected = " selected=\"selected\"";
								?>
								<option<?=$selected?>><?=$length;?></option>
								<?php endforeach; ?>
							</select>
							<span class="vexpl">
								<?=gettext("bits"); ?>
							</span>
						</td>
					</tr>
					<tr id="psk">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Shared Key"); ?></td>
						<td width="78%" class="vtable">
							<?php if (!$pconfig['shared_key']): ?>
							<table border="0" cellpadding="2" cellspacing="0" summary="shared key">
								<tr>
									<td>
										<?php set_checked($pconfig['autokey_enable'],$chk); ?>
										<input name="autokey_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autokey_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id="autokey_opts" summary="shared key">
								<tr>
									<td>
										<textarea name="shared_key" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['shared_key']);?></textarea>
										<br />
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
									$selected = "";
									if ($name == $pconfig['crypto'])
										$selected = " selected=\"selected\"";
								?>
								<option value="<?=$name;?>"<?=$selected?>>
									<?=htmlspecialchars($desc);?>
								</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Auth Digest Algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="digest" class="formselect">
								<?php
									$digestlist = openvpn_get_digestlist();
									foreach ($digestlist as $name => $desc):
									$selected = "";
									if ($name == $pconfig['digest'])
										$selected = " selected=\"selected\"";
								?>
								<option value="<?=$name;?>"<?=$selected?>>
									<?=htmlspecialchars($desc);?>
								</option>
								<?php endforeach; ?>
							</select>
							<br /><?PHP echo gettext("NOTE: Leave this set to SHA1 unless all clients are set to match. SHA1 is the default for OpenVPN."); ?>
						</td>
					</tr>
					<tr id="engine">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Hardware Crypto"); ?></td>
						<td width="78%" class="vtable">
							<select name="engine" class="formselect">
								<?php
									$engines = openvpn_get_engines();
									foreach ($engines as $name => $desc):
									$selected = "";
									if ($name == $pconfig['engine'])
										$selected = " selected=\"selected\"";
								?>
								<option value="<?=$name;?>"<?=$selected?>>
									<?=htmlspecialchars($desc);?>
								</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="cert_depth">
						<td width="22%" valign="top" class="vncell"><?=gettext("Certificate Depth"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="certificate depth">
							<tr><td>
							<select name="cert_depth" class="formselect">
								<option value="">Do Not Check</option>
								<?php
									foreach ($openvpn_cert_depths as $depth => $depthdesc):
									$selected = "";
									if ($depth == $pconfig['cert_depth'])
										$selected = " selected=\"selected\"";
								?>
								<option value="<?= $depth ?>" <?= $selected ?>><?= $depthdesc ?></option>
								<?php endforeach; ?>
							</select>
							</td></tr>
							<tr><td>
							<span class="vexpl">
								<?=gettext("When a certificate-based client logs in, do not accept certificates below this depth. Useful for denying certificates made with intermediate CAs generated from the same CA as the server."); ?>
							</span>
							</td></tr>
							</table>
						</td>
					</tr>
					<tr id="strictusercn">
						<td width="22%" valign="top" class="vncell"><?=gettext("Strict User/CN Matching"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="strict user/cn matching">
								<tr>
									<td>
										<?php set_checked($pconfig['strictusercn'],$chk); ?>
										<input name="strictusercn" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("When authenticating users, enforce a match between the common name of the client certificate and the username given at login."); ?>
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
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Tunnel Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq" id="ipv4_tunnel_network"><?=gettext("IPv4 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_network" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_network']);?>" />
							<br />
							<?=gettext("This is the IPv4 virtual network used for private " .
							"communications between this server and client " .
							"hosts expressed using CIDR (eg. 10.0.8.0/24). " .
							"The first network address will be assigned to " .
							"the	server virtual interface. The remaining " .
							"network addresses can optionally be assigned " .
							"to connecting clients. (see Address Pool)"); ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_networkv6" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_networkv6']);?>" />
							<br />
							<?=gettext("This is the IPv6 virtual network used for private " .
							"communications between this server and client " .
							"hosts expressed using CIDR (eg. fe80::/64). " .
							"The first network address will be assigned to " .
							"the server virtual interface. The remaining " .
							"network addresses can optionally be assigned " .
							"to connecting clients. (see Address Pool)"); ?>
						</td>
					</tr>
					<tr id="serverbridge_dhcp">
						<td width="22%" valign="top" class="vncell"><?=gettext("Bridge DHCP"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="bridge dhcp">
								<tr>
									<td>
										<?php set_checked($pconfig['serverbridge_dhcp'],$chk); ?>
										<input name="serverbridge_dhcp" type="checkbox" value="yes" <?=$chk;?> onchange="tuntap_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Allow clients on the bridge to obtain DHCP."); ?><br />
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="serverbridge_interface">
						<td width="22%" valign="top" class="vncell"><?=gettext("Bridge Interface"); ?></td>
						<td width="78%" class="vtable">
							<select name="serverbridge_interface" class="formselect">
								<?php
									$serverbridge_interface['none'] = "none";
									$serverbridge_interface = array_merge($serverbridge_interface, get_configured_interface_with_descr());
									$carplist = get_configured_carp_interface_list();
									foreach ($carplist as $cif => $carpip)
										$serverbridge_interface[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
									$aliaslist = get_configured_ip_aliases_list();
									foreach ($aliaslist as $aliasip => $aliasif)
										$serverbridge_interface[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
									foreach ($serverbridge_interface as $iface => $ifacename):
										$selected = "";
										if ($iface == $pconfig['serverbridge_interface'])
											$selected = "selected=\"selected\"";
								?>
									<option value="<?=$iface;?>" <?=$selected;?>>
										<?=htmlspecialchars($ifacename);?>
									</option>
								<?php endforeach; ?>
							</select> <br />
							<?=gettext("The interface to which this tap instance will be " .
							"bridged. This is not done automatically. You must assign this " .
							"interface and create the bridge separately. " .
							"This setting controls which existing IP address and subnet " .
							"mask are used by OpenVPN for the bridge. Setting this to " .
							"'none' will cause the Server Bridge DHCP settings below to be ignored."); ?>
						</td>
					</tr>
					<tr id="serverbridge_dhcp_start">
						<td width="22%" valign="top" class="vncell"><?=gettext("Server Bridge DHCP Start"); ?></td>
						<td width="78%" class="vtable">
							<input name="serverbridge_dhcp_start" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['serverbridge_dhcp_start']);?>" />
							<br />
							<?=gettext("When using tap mode as a multi-point server, " .
							"you may optionally supply a DHCP range to use on the " .
							"interface to which this tap instance is bridged. " .
							"If these settings are left blank, DHCP will be passed " .
							"through to the LAN, and the interface setting above " .
							"will be ignored."); ?>
						</td>
					</tr>
					<tr id="serverbridge_dhcp_end">
						<td width="22%" valign="top" class="vncell"><?=gettext("Server Bridge DHCP End"); ?></td>
						<td width="78%" class="vtable">
							<input name="serverbridge_dhcp_end" type="text" class="formfld unknown" size="20" value="<?=htmlspecialchars($pconfig['serverbridge_dhcp_end']);?>" />
							<br />
						</td>
					</tr>
					<tr id="gwredir_opts">
						<td width="22%" valign="top" class="vncell"><?=gettext("Redirect Gateway"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="redirect gateway">
								<tr>
									<td>
										<?php set_checked($pconfig['gwredir'],$chk); ?>
										<input name="gwredir" type="checkbox" value="yes" <?=$chk;?> onclick="gwredir_change()" />
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
					<tr id="local_optsv4">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Local Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="local_network" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['local_network']);?>" />
							<br />
							<?=gettext("These are the IPv4 networks that will be accessible " .
							"from the remote endpoint. Expressed as a comma-separated list of one or more CIDR ranges. " .
							"You may leave this blank if you don't " .
							"want to add a route to the local network " .
							"through this tunnel on the remote machine. " .
							"This is generally set to your LAN network"); ?>.
						</td>
					</tr>
					<tr id="local_optsv6">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Local Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="local_networkv6" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['local_networkv6']);?>" />
							<br />
							<?=gettext("These are the IPv6 networks that will be accessible " .
							"from the remote endpoint. Expressed as a comma-separated list of one or more IP/PREFIX. " .
							"You may leave this blank if you don't " .
							"want to add a route to the local network " .
							"through this tunnel on the remote machine. " .
							"This is generally set to your LAN network"); ?>.
						</td>
					</tr>
					<tr id="remote_optsv4">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_network']);?>" />
							<br />
							<?=gettext("These are the IPv4 networks that will be routed through " .
							"the tunnel, so that a site-to-site VPN can be " .
							"established without manually changing the routing tables. " .
							"Expressed as a comma-separated list of one or more CIDR ranges. " .
							"If this is a site-to-site VPN, enter the " .
							"remote LAN/s here. You may leave this blank if " .
							"you don't want a site-to-site VPN"); ?>.
						</td>
					</tr>
					<tr id="remote_optsv6">
						<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_networkv6" type="text" class="formfld unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_networkv6']);?>" />
							<br />
							<?=gettext("These are the IPv6 networks that will be routed through " .
							"the tunnel, so that a site-to-site VPN can be " .
							"established without manually changing the routing tables. " .
							"Expressed as a comma-separated list of one or more IP/PREFIX. " .
							"If this is a site-to-site VPN, enter the " .
							"remote LAN/s here. You may leave this blank if " .
							"you don't want a site-to-site VPN"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Concurrent connections");?></td>
						<td width="78%" class="vtable">
							<input name="maxclients" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($pconfig['maxclients']);?>" />
							<br />
							<?=gettext("Specify the maximum number of clients allowed to concurrently connect to this server"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Compression"); ?></td>
						<td width="78%" class="vtable">
							<select name="compression" class="formselect">
								<?php
									foreach ($openvpn_compression_modes as $cmode => $cmodedesc):
									$selected = "";
									if ($cmode == $pconfig['compression'])
										$selected = " selected=\"selected\"";
								?>
								<option value="<?= $cmode ?>" <?= $selected ?>><?= $cmodedesc ?></option>
								<?php endforeach; ?>
							</select>
							<br />
							<?=gettext("Compress tunnel packets using the LZO algorithm. Adaptive compression will dynamically disable compression for a period of time if OpenVPN detects that the data in the packets is not being compressed efficiently"); ?>.
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Type-of-Service"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="type-of-service">
								<tr>
									<td>
										<?php set_checked($pconfig['passtos'],$chk); ?>
										<input name="passtos" type="checkbox" value="yes" <?=$chk;?> />
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
					<tr id="inter_client_communication">
						<td width="22%" valign="top" class="vncell"><?=gettext("Inter-client communication"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="inter-client communication">
								<tr>
									<td>
										<?php set_checked($pconfig['client2client'],$chk); ?>
										<input name="client2client" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Allow communication between clients connected to this server"); ?>
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="duplicate_cn">
						<td width="22%" valign="top" class="vncell"><?=gettext("Duplicate Connections"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="duplicate connection">
								<tr>
									<td>
										<?php set_checked($pconfig['duplicate_cn'],$chk); ?>
										<input name="duplicate_cn" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Allow multiple concurrent connections from clients using the same Common Name.<br />NOTE: This is not generally recommended, but may be needed for some scenarios."); ?>
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="chkboxNoTunIPv6">
						<td width="22%" valign="top" class="vncell"><?=gettext("Disable IPv6"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="disable-ipv6-srv">
								<tr>
									<td>
										<?php set_checked($pconfig['no_tun_ipv6'],$chk); ?>
										<input name="no_tun_ipv6" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Don't forward IPv6 traffic"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts" summary="client settings">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Client Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Dynamic IP"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="dynamic ip">
								<tr>
									<td>
										<?php set_checked($pconfig['dynamic_ip'],$chk); ?>
										<input name="dynamic_ip" type="checkbox" id="dynamic_ip" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Allow connected clients to retain their connections if their IP address changes"); ?>.<br />
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Address Pool"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="address pool">
								<tr>
									<td>
										<?php set_checked($pconfig['pool_enable'],$chk); ?>
										<input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a virtual adapter IP address to clients (see Tunnel Network)"); ?><br />
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="topology_subnet_opt">
						<td width="22%" valign="top" class="vncell"><?=gettext("Topology"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="topology">
								<tr>
									<td>
										<?php set_checked($pconfig['topology_subnet'],$chk); ?>
										<input name="topology_subnet" type="checkbox" id="topology_subnet" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Allocate only one IP per client (topology subnet), rather than an isolated subnet per client (topology net30)."); ?><br />
										</span>
									</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td>
										<?=gettext("Relevant when supplying a virtual adapter IP address to clients when using tun mode on IPv4."); ?><br />
										<?=gettext("Some clients may require this even for IPv6, such as OpenVPN Connect (iOS/Android). Others may break if it is present, such as older versions of OpenVPN or clients such as Yealink phones."); ?><br />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Default Domain"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="dns default domain">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_domain_enable'],$chk); ?>
										<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=$chk;?> onclick="dns_domain_change()" />
									</td>
									<td>
										<span class="vexpl">
	                                        <?=gettext("Provide a default domain name to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="dns_domain_data" summary="dns domain data">
								<tr>
									<td>
										<input name="dns_domain" type="text" class="formfld unknown" id="dns_domain" size="30" value="<?=htmlspecialchars($pconfig['dns_domain']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("DNS Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="dns servers">
								<tr>
									<td>
										<?php set_checked($pconfig['dns_server_enable'],$chk); ?>
										<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=$chk;?> onclick="dns_server_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a DNS server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="dns_server_data" summary="dns servers">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="dns_server1" type="text" class="formfld unknown" id="dns_server1" size="20" value="<?=htmlspecialchars($pconfig['dns_server1']);?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="dns_server2" type="text" class="formfld unknown" id="dns_server2" size="20" value="<?=htmlspecialchars($pconfig['dns_server2']);?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #3:&nbsp;
										</span>
										<input name="dns_server3" type="text" class="formfld unknown" id="dns_server3" size="20" value="<?=htmlspecialchars($pconfig['dns_server3']);?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #4:&nbsp;
										</span>
										<input name="dns_server4" type="text" class="formfld unknown" id="dns_server4" size="20" value="<?=htmlspecialchars($pconfig['dns_server4']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="chkboxPushRegisterDNS">
						<td width="22%" valign="top" class="vncell"><?=gettext("Force DNS cache update"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="push register dns">
								<tr>
									<td>
										<?php set_checked($pconfig['push_register_dns'],$chk); ?>
										<input name="push_register_dns" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Run ''net stop dnscache'', ''net start dnscache'', ''ipconfig /flushdns'' and ''ipconfig /registerdns'' on connection initiation. This is known to kick Windows into recognizing pushed DNS servers."); ?><br />
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("NTP Servers"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="ntp servers">
								<tr>
									<td>
										<?php set_checked($pconfig['ntp_server_enable'],$chk); ?>
										<input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=$chk;?> onclick="ntp_server_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a NTP server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="ntp_server_data" summary="ntp servers">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="ntp_server1" type="text" class="formfld unknown" id="ntp_server1" size="20" value="<?=htmlspecialchars($pconfig['ntp_server1']);?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="ntp_server2" type="text" class="formfld unknown" id="ntp_server2" size="20" value="<?=htmlspecialchars($pconfig['ntp_server2']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("NetBIOS Options"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="netboios options">
								<tr>
									<td>
										<?php set_checked($pconfig['netbios_enable'],$chk); ?>
										<input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=$chk;?> onclick="netbios_change()" />
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
							<table border="0" cellpadding="2" cellspacing="0" id="netbios_data" summary="netboios options">
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
													$selected = "selected=\"selected\"";
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
											<?=gettext("Scope ID"); ?>:&nbsp;
										</span>
										<input name="netbios_scope" type="text" class="formfld unknown" id="netbios_scope" size="30" value="<?=htmlspecialchars($pconfig['netbios_scope']);?>" />
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
							<table border="0" cellpadding="2" cellspacing="0" summary="wins servers">
								<tr>
									<td>
										<?php set_checked($pconfig['wins_server_enable'],$chk); ?>
										<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=$chk;?> onclick="wins_server_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Provide a WINS server list to clients"); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="wins_server_data" summary="wins servers">
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #1:&nbsp;
										</span>
										<input name="wins_server1" type="text" class="formfld unknown" id="wins_server1" size="20" value="<?=htmlspecialchars($pconfig['wins_server1']);?>" />
									</td>
								</tr>
								<tr>
									<td>
										<span class="vexpl">
											<?=gettext("Server"); ?> #2:&nbsp;
										</span>
										<input name="wins_server2" type="text" class="formfld unknown" id="wins_server2" size="20" value="<?=htmlspecialchars($pconfig['wins_server2']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Client Management Port"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="client management port">
								<tr>
									<td>
										<?php set_checked($pconfig['client_mgmt_port_enable'],$chk); ?>
										<input name="client_mgmt_port_enable" type="checkbox" id="client_mgmt_port_enable" value="yes" <?=$chk;?> onclick="client_mgmt_port_change()" />
									</td>
									<td>
										<span class="vexpl">
	                                        <?=gettext("Use a different management port on clients. The default port is 166. Specify a different port if the client machines need to select from multiple OpenVPN links."); ?><br />
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="client_mgmt_port_data" summary="client management port">
								<tr>
									<td>
										<input name="client_mgmt_port" type="text" class="formfld unknown" id="client_mgmt_port" size="30" value="<?=htmlspecialchars($pconfig['client_mgmt_port']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts" summary="advance configuration">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced configuration"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Advanced"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="advance configuration">
								<tr>
									<td>
										<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=htmlspecialchars($pconfig['custom_options']);?></textarea><br />
										<?=gettext("Enter any additional options you would like to add to the OpenVPN server configuration here, separated by a semicolon"); ?><br />
										<?=gettext("EXAMPLE: push \"route 10.0.0.0 255.255.255.0\""); ?>;
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="comboboxVerbosityLevel">
							<td width="22%" valign="top" class="vncell"><?=gettext("Verbosity level");?></td>
							<td width="78%" class="vtable">
							<select name="verbosity_level" class="formselect">
							<?php
								foreach ($openvpn_verbosity_level as $verb_value => $verb_desc):
									$selected = "";
									if ($pconfig['verbosity_level'] == $verb_value)
										$selected = "selected=\"selected\"";
							?>
								<option value="<?=$verb_value;?>" <?=$selected;?>><?=$verb_desc;?></option>
							<?php endforeach; ?>
							</select>
							<br />
							<?=gettext("Each level shows all info from the previous levels. Level 3 is recommended if you want a good summary of what's happening without being swamped by output"); ?>.<br /> <br />
							<strong>none</strong> -- <?=gettext("No output except fatal errors"); ?>. <br />
							<strong>default</strong>-<strong>4</strong> -- <?=gettext("Normal usage range"); ?>. <br />
							<strong>5</strong> -- <?=gettext("Output R and W characters to the console for each packet read and write, uppercase is used for TCP/UDP packets and lowercase is used for TUN/TAP packets"); ?>. <br />
							<strong>6</strong>-<strong>11</strong> -- <?=gettext("Debug info range"); ?>.
							</td>
					</tr>

				</table>

				<br />
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="icons">
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%"> 
							<input name="save" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" /> 
							<input name="act" type="hidden" value="<?=$act;?>" />
							<?php if (isset($id) && $a_server[$id]): ?>
							<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="list">
				<thead>
				<tr>
					<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
					<td width="10%" class="listhdrr"><?=gettext("Protocol / Port"); ?></td>
					<td width="30%" class="listhdrr"><?=gettext("Tunnel Network"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<td class="list" colspan="4"></td>
					<td class="list">
						<a href="vpn_openvpn_server.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("add server"); ?>" width="17" height="17" border="0" alt="add" />
						</a>
					</td>
				</tr>
				</tfoot>
				<tbody>
				<?php
					$i = 0;
					foreach($a_server as $server):
						$disabled = "NO";
						if (isset($server['disable']))
							$disabled = "YES";
				?>
				<tr>
					<td class="listlr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
						<?=$disabled;?>
					</td>
					<td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
						<?=htmlspecialchars($server['protocol']);?> / <?=htmlspecialchars($server['local_port']);?>
					</td>
					<td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
						<?=htmlspecialchars($server['tunnel_network']);?><br />
						<?=htmlspecialchars($server['tunnel_networkv6']);?><br />
					</td>
					<td class="listbg" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
						<?=htmlspecialchars($server['description']);?>
					</td>
					<td valign="middle" class="list nowrap">
						<a href="vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit server"); ?>" width="17" height="17" border="0" alt="edit" />
						</a>
						&nbsp;
						<a href="vpn_openvpn_server.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this server?"); ?>')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete server"); ?>" width="17" height="17" border="0" alt="delete" />
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
				?>
				<tr style="dispaly:none;"><td></td></tr>
				</tbody>
			</table>

			<?=gettext("Additional OpenVPN servers can be added here.");?>

			<?php endif; ?>

		</td>
	</tr>
</table>
<script type="text/javascript">
//<![CDATA[
mode_change();
autokey_change();
tlsauth_change();
gwredir_change();
dns_domain_change();
dns_server_change();
wins_server_change();
client_mgmt_port_change();
ntp_server_change();
netbios_change();
tuntap_change();
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
<?php

/* local utility functions */

function set_checked($var,& $chk) {
    if($var)
        $chk = "checked=\"checked\"";
    else
        $chk = "";
}

?>
