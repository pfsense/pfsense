<?php
/* $Id$ */
/*
	interfaces.php
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2006 Daniel S. Haischt.
	Copyright (C) 2008-2010 Ermal Lu?i
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/arp
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces
##|*NAME=Interfaces: WAN page
##|*DESCR=Allow access to the 'Interfaces' page.
##|*MATCH=interfaces.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("functions.inc");
require_once("captiveportal.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");
require_once("vpn.inc");
require_once("xmlparse_attr.inc");

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr(false, true);

$if = "wan";
if ($_REQUEST['if'])
	$if = $_REQUEST['if'];

if (empty($ifdescrs[$if])) {
	Header("Location: interfaces.php");
	exit;
}

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();

$a_ppps = &$config['ppps']['ppp'];

function remove_bad_chars($string) {
	return preg_replace('/[^a-z_0-9]/i','',$string);
}

if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();
$a_gateways = &$config['gateways']['gateway_item'];

$wancfg = &$config['interfaces'][$if];
$old_wancfg = $wancfg;
// Populate page descr if it does not exist.
if ($if == "wan" && !$wancfg['descr'])
	$wancfg['descr'] = "WAN";
else if ($if == "lan" && !$wancfg['descr'])
	$wancfg['descr'] = "LAN";


foreach ($a_ppps as $pppid => $ppp) {
	if ($wancfg['if'] == $ppp['if'])
		break;
}

if ($wancfg['if'] == $a_ppps[$pppid]['if']) {
	$pconfig['pppid'] = $pppid;
	$pconfig['ptpid'] = $a_ppps[$pppid]['ptpid'];
	$pconfig['port'] = $a_ppps[$pppid]['ports'];
	if ($a_ppps[$pppid]['type'] == "ppp") {
		$pconfig['username'] = $a_ppps[$pppid]['username'];
		$pconfig['password'] = base64_decode($a_ppps[$pppid]['password']);

		$pconfig['phone'] = $a_ppps[$pppid]['phone'];
		$pconfig['apn'] = $a_ppps[$pppid]['apn'];
	}
	else if ($a_ppps[$pppid]['type'] == "pppoe") {
		$pconfig['pppoe_username'] = $a_ppps[$pppid]['username'];
		$pconfig['pppoe_password'] = base64_decode($a_ppps[$pppid]['password']);
		$pconfig['provider'] = $a_ppps[$pppid]['provider'];
		$pconfig['pppoe_dialondemand'] = isset($a_ppps[$pppid]['ondemand']);
		$pconfig['pppoe_idletimeout'] = $a_ppps[$pppid]['idletimeout'];

		/* ================================================ */
		/* = force a connection reset at a specific time? = */
		/* ================================================ */

		if (isset($a_ppps[$pppid]['pppoe-reset-type'])) {
			$pconfig['pppoe-reset-type'] = $a_ppps[$pppid]['pppoe-reset-type'];
			$itemhash = getMPDCRONSettings($a_ppps[$pppid]['if']);
			if ($itemhash)
				$cronitem = $itemhash['ITEM'];
			if (isset($cronitem)) {
				$resetTime = "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
			} else {
				$resetTime = NULL;
			}
			//log_error("ResetTime:".$resetTime);
			if ($a_ppps[$pppid]['pppoe-reset-type'] == "custom") {
				if ($cronitem) {
					$pconfig['pppoe_pr_custom'] = true;
					$pconfig['pppoe_resetminute'] = $cronitem['minute'];
					$pconfig['pppoe_resethour'] = $cronitem['hour'];
					if ($cronitem['mday'] <> "*" && $cronitem['month'] <> "*")
						$pconfig['pppoe_resetdate'] = "{$cronitem['month']}/{$cronitem['mday']}/" . date("Y");
				}
			} else if ($a_ppps[$pppid]['pppoe-reset-type'] == "preset") {
				$pconfig['pppoe_pr_preset'] = true;
				switch ($resetTime) {
					case CRON_MONTHLY_PATTERN:
						$pconfig['pppoe_monthly'] = true;
						break;
					case CRON_WEEKLY_PATTERN:
						$pconfig['pppoe_weekly'] = true;
						break;
					case CRON_DAILY_PATTERN:
						$pconfig['pppoe_daily'] = true;
						break;
					case CRON_HOURLY_PATTERN:
						$pconfig['pppoe_hourly'] = true;
						break;
				}
			}
		}// End force pppoe reset at specific time
	}// End if type == pppoe
	else if ($a_ppps[$pppid]['type'] == "pptp" || $a_ppps[$pppid]['type'] == "l2tp"){
		$pconfig['pptp_username'] = $a_ppps[$pppid]['username'];
		$pconfig['pptp_password'] = base64_decode($a_ppps[$pppid]['password']);
		$pconfig['pptp_local'] = explode(",",$a_ppps[$pppid]['localip']);
		$pconfig['pptp_subnet'] = explode(",",$a_ppps[$pppid]['subnet']);
		$pconfig['pptp_remote'] = explode(",",$a_ppps[$pppid]['gateway']);
		$pconfig['pptp_dialondemand'] = isset($a_ppps[$pppid]['ondemand']);
		$pconfig['pptp_idletimeout'] = $a_ppps[$pppid]['timeout'];
	}
} else {
	$pconfig['ptpid'] = interfaces_ptpid_next();
	$pppid = count($a_ppps);
}
$pconfig['dhcphostname'] = $wancfg['dhcphostname'];
$pconfig['alias-address'] = $wancfg['alias-address'];
$pconfig['alias-subnet'] = $wancfg['alias-subnet'];
$pconfig['dhcp_plus'] = isset($wancfg['dhcp_plus']);
$pconfig['descr'] = remove_bad_chars($wancfg['descr']);
$pconfig['enable'] = isset($wancfg['enable']);

if (is_array($config['aliases']['alias'])) {
	foreach($config['aliases']['alias'] as $alias) {
		if($alias['name'] == $wancfg['descr']) {
			$input_errors[] = sprintf(gettext("Sorry, an alias with the name %s already exists."),$wancfg['descr']);
		}
	}
}

switch($wancfg['ipaddr']) {
	case "dhcp":
		$pconfig['type'] = "dhcp";
		break;
	case "pppoe":
	case "pptp":
	case "l2tp":
	case "ppp":
		$pconfig['type'] = $wancfg['ipaddr'];
		break;
	default:
		if(is_ipaddr($wancfg['ipaddr'])) {
			$pconfig['type'] = "staticv4";
			$pconfig['ipaddr'] = $wancfg['ipaddr'];
			$pconfig['subnet'] = $wancfg['subnet'];
			$pconfig['gateway'] = $wancfg['gateway'];
		} else
			$pconfig['type'] = "none";
		break;
}

switch($wancfg['ipaddrv6']) {
	case "slaac":
		$pconfig['type6'] = "slaac";
		break;
	case "dhcp6":
		$pconfig['dhcp6-duid'] = $wancfg['dhcp6-duid'];
		if($wancfg['dhcp6-ia-pd-len'] == "")
			$wancfg['dhcp6-ia-pd-len'] = "none";
		$pconfig['dhcp6-ia-pd-len'] = $wancfg['dhcp6-ia-pd-len'];
		$pconfig['type6'] = "dhcp6";
		break;
	case "6to4":
		$pconfig['type6'] = "6to4";
		break;
	case "track6":
		$pconfig['type6'] = "track6";
		$pconfig['track6-interface'] = $wancfg['track6-interface'];
		$pconfig['track6-prefix-id'] = $wancfg['track6-prefix-id'];
		if ($wancfg['track6-prefix-id'] == "" || $wancfg['track6-prefix-id'] == "none") {
			$pconfig['track6-prefix-id--hex'] = "";
		} else {
			$pconfig['track6-prefix-id--hex'] = sprintf("%x", $wancfg['track6-prefix-id']);
		}
		break;
	case "6rd":
		$pconfig['prefix-6rd'] = $wancfg['prefix-6rd'];
		if($wancfg['prefix-6rd-v4plen'] == "")
			$wancfg['prefix-6rd-v4plen'] = "0";
		$pconfig['prefix-6rd-v4plen'] = $wancfg['prefix-6rd-v4plen'];
		$pconfig['type6'] = "6rd";
		$pconfig['gateway-6rd'] = $wancfg['gateway-6rd'];
		break;
	default:
		if(is_ipaddr($wancfg['ipaddrv6'])) {
			$pconfig['type6'] = "staticv6";
			$pconfig['ipaddrv6'] = $wancfg['ipaddrv6'];
			$pconfig['subnetv6'] = $wancfg['subnetv6'];
			$pconfig['gatewayv6'] = $wancfg['gatewayv6'];
		} else
			$pconfig['type6'] = "none";
		break;
}

// print_r($pconfig);

$pconfig['blockpriv'] = isset($wancfg['blockpriv']);
$pconfig['blockbogons'] = isset($wancfg['blockbogons']);
$pconfig['spoofmac'] = $wancfg['spoofmac'];
$pconfig['mtu'] = $wancfg['mtu'];
$pconfig['mss'] = $wancfg['mss'];

/* Wireless interface? */
if (isset($wancfg['wireless'])) {
	/* Sync first to be sure it displays the actual settings that will be used */
	interface_sync_wireless_clones($wancfg, false);
	/* Get wireless modes */
	$wlanif = get_real_interface($if);
	if (!does_interface_exist($wlanif))
		interface_wireless_clone($wlanif, $wancfg);
	$wlanbaseif = interface_get_wireless_base($wancfg['if']);
	preg_match("/^(.*?)([0-9]*)$/", $wlanbaseif, $wlanbaseif_split);
	$wl_modes = get_wireless_modes($if);
	$wl_chaninfo = get_wireless_channel_info($if);
	$wl_sysctl_prefix = 'dev.' . $wlanbaseif_split[1] . '.' . $wlanbaseif_split[2];
	$wl_sysctl = get_sysctl(array("{$wl_sysctl_prefix}.diversity", "{$wl_sysctl_prefix}.txantenna", "{$wl_sysctl_prefix}.rxantenna",
	                              "{$wl_sysctl_prefix}.slottime", "{$wl_sysctl_prefix}.acktimeout", "{$wl_sysctl_prefix}.ctstimeout"));
	$wl_regdomain_xml_attr = array();
	$wl_regdomain_xml = parse_xml_regdomain($wl_regdomain_xml_attr);
	$wl_regdomains = &$wl_regdomain_xml['regulatory-domains']['rd'];
	$wl_regdomains_attr = &$wl_regdomain_xml_attr['regulatory-domains']['rd'];
	$wl_countries = &$wl_regdomain_xml['country-codes']['country'];
	$wl_countries_attr = &$wl_regdomain_xml_attr['country-codes']['country'];
	$pconfig['persistcommonwireless'] = isset($config['wireless']['interfaces'][$wlanbaseif]);
	$pconfig['standard'] = $wancfg['wireless']['standard'];
	$pconfig['mode'] = $wancfg['wireless']['mode'];
	$pconfig['protmode'] = $wancfg['wireless']['protmode'];
	$pconfig['ssid'] = $wancfg['wireless']['ssid'];
	$pconfig['channel'] = $wancfg['wireless']['channel'];
	$pconfig['txpower'] = $wancfg['wireless']['txpower'];
	$pconfig['diversity'] = $wancfg['wireless']['diversity'];
	$pconfig['txantenna'] = $wancfg['wireless']['txantenna'];
	$pconfig['rxantenna'] = $wancfg['wireless']['rxantenna'];
	$pconfig['distance'] = $wancfg['wireless']['distance'];
	$pconfig['regdomain'] = $wancfg['wireless']['regdomain'];
	$pconfig['regcountry'] = $wancfg['wireless']['regcountry'];
	$pconfig['reglocation'] = $wancfg['wireless']['reglocation'];
	$pconfig['wme_enable'] = isset($wancfg['wireless']['wme']['enable']);
	if (isset($wancfg['wireless']['puren']['enable']))
		$pconfig['puremode'] = '11n';
	else if (isset($wancfg['wireless']['pureg']['enable']))
		$pconfig['puremode'] = '11g';
	else
		$pconfig['puremode'] = 'any';
	$pconfig['apbridge_enable'] = isset($wancfg['wireless']['apbridge']['enable']);
	$pconfig['authmode'] = $wancfg['wireless']['authmode'];
	$pconfig['hidessid_enable'] = isset($wancfg['wireless']['hidessid']['enable']);
	$pconfig['auth_server_addr'] = $wancfg['wireless']['auth_server_addr'];
	$pconfig['auth_server_port'] = $wancfg['wireless']['auth_server_port'];
	$pconfig['auth_server_shared_secret'] = $wancfg['wireless']['auth_server_shared_secret'];
	$pconfig['auth_server_addr2'] = $wancfg['wireless']['auth_server_addr2'];
	$pconfig['auth_server_port2'] = $wancfg['wireless']['auth_server_port2'];
	$pconfig['auth_server_shared_secret2'] = $wancfg['wireless']['auth_server_shared_secret2'];
	if (is_array($wancfg['wireless']['wpa'])) {
		$pconfig['debug_mode'] = $wancfg['wireless']['wpa']['debug_mode'];
		$pconfig['macaddr_acl'] = $wancfg['wireless']['wpa']['macaddr_acl'];
		$pconfig['mac_acl_enable'] = isset($wancfg['wireless']['wpa']['mac_acl_enable']);
		$pconfig['auth_algs'] = $wancfg['wireless']['wpa']['auth_algs'];
		$pconfig['wpa_mode'] = $wancfg['wireless']['wpa']['wpa_mode'];
		$pconfig['wpa_key_mgmt'] = $wancfg['wireless']['wpa']['wpa_key_mgmt'];
		$pconfig['wpa_pairwise'] = $wancfg['wireless']['wpa']['wpa_pairwise'];
		$pconfig['wpa_group_rekey'] = $wancfg['wireless']['wpa']['wpa_group_rekey'];
		$pconfig['wpa_gmk_rekey'] = $wancfg['wireless']['wpa']['wpa_gmk_rekey'];
		$pconfig['wpa_strict_rekey'] = isset($wancfg['wireless']['wpa']['wpa_strict_rekey']);
		$pconfig['passphrase'] = $wancfg['wireless']['wpa']['passphrase'];
		$pconfig['ieee8021x'] = isset($wancfg['wireless']['wpa']['ieee8021x']['enable']);
		$pconfig['rsn_preauth'] = isset($wancfg['wireless']['wpa']['rsn_preauth']);
		$pconfig['ext_wpa_sw'] = $wancfg['wireless']['wpa']['ext_wpa_sw'];
		$pconfig['wpa_enable'] = isset($wancfg['wireless']['wpa']['enable']);
	}
	$pconfig['wep_enable'] = isset($wancfg['wireless']['wep']['enable']);
	$pconfig['mac_acl'] = $wancfg['wireless']['mac_acl'];
	if (is_array($wancfg['wireless']['wep']) && is_array($wancfg['wireless']['wep']['key'])) {
		$i = 1;
		foreach ($wancfg['wireless']['wep']['key'] as $wepkey) {
			$pconfig['key' . $i] = $wepkey['value'];
			if (isset($wepkey['txkey']))
				$pconfig['txkey'] = $i;
			$i++;
		}
		if (!isset($wepkey['txkey']))
			$pconfig['txkey'] = 1;
	}
}

$ipv6_delegation_length = calculate_ipv6_delegation_length($pconfig['track6-interface']);
$ipv6_num_prefix_ids = pow(2, $ipv6_delegation_length);

if ($_POST['apply']) {
	unset($input_errors);
	if (!is_subsystem_dirty('interfaces'))
		$intput_errors[] = gettext("You have already applied your settings!");
	else {
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		clear_subsystem_dirty('interfaces');

		if (file_exists("{$g['tmp_path']}/.interfaces.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
			foreach ($toapplylist as $ifapply => $values) {
				if (isset($config['interfaces'][$ifapply]['enable'])) {
					/* check if any old addresses need purging */
					if(is_ipaddrv6($values['ipaddrv6'])) {
						$realif = get_real_interface("$ifapply");
						log_error("removing old v6 address {$values['ipaddrv6']} on {$realif}");
						mwexec("/sbin/ifconfig {$realif} inet6 {$values['ipaddrv6']} -alias");
					}
					interface_reconfigure($ifapply, true);
				} else {

					interface_bring_down($ifapply);
				}
			}
		}
		/* restart snmp so that it binds to correct address */
		services_snmpd_configure();

		/* sync filter configuration */
		setup_gateways_monitor();

		clear_subsystem_dirty('staticroutes');

		filter_configure();

		enable_rrd_graphing();
	}
	@unlink("{$g['tmp_path']}/.interfaces.apply");
	header("Location: interfaces.php?if={$if}");
	exit;
} else if ($_POST && $_POST['enable'] != "yes") {
	unset($wancfg['enable']);
	if (isset($wancfg['wireless']))
		interface_sync_wireless_clones($wancfg, false);
	write_config("Interface {$_POST['descr']}({$if}) is now disabled.");
	mark_subsystem_dirty('interfaces');
	if (file_exists("{$g['tmp_path']}/.interfaces.apply")) {
		$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
	} else {
		$toapplylist = array();
	}
	$toapplylist[$if] = array();
	/* we need to be able remove IP aliases for IPv6 */
	if(($old_wancfg['ipaddrv6'] != $wancfg['ipaddrv6']) && (is_ipaddrv6($old_wancfg['ipaddrv6']))) {
		$toapplylist[$if]['ipaddrv6'] = "{$old_wancfg['ipaddrv6']}";
	}
	file_put_contents("{$g['tmp_path']}/.interfaces.apply", serialize($toapplylist));
	header("Location: interfaces.php?if={$if}");
	exit;
} else if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;
	if ($pconfig['track6-prefix-id--hex'] === "") {
		$pconfig['track6-prefix-id'] = "none";
	} else if (is_numeric("0x" . $_POST['track6-prefix-id--hex'])) {
		$pconfig['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
	} else {
		$pconfig['track6-prefix-id'] = "none";
	}
	conf_mount_rw();

	/* filter out spaces from descriptions  */
	$_POST['descr'] = remove_bad_chars($_POST['descr']);

	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields releated to PPPoE resets, we are going to unset $_POST
	 * vars, if the reset feature should not be used. Otherwise the
	 * data validation procedure below, may trigger a false error
	 * message.
	 */
	if (empty($_POST['pppoe-reset-type'])) {
		unset($_POST['pppoe_pr_type']);
		unset($_POST['pppoe_resethour']);
		unset($_POST['pppoe_resetminute']);
		unset($_POST['pppoe_resetdate']);
		unset($_POST['pppoe_pr_preset_val']);
	}
	/* description unique? */
	foreach ($ifdescrs as $ifent => $ifdescr) {
		if ($if != $ifent && $ifdescr == $_POST['descr']) {
			$input_errors[] = gettext("An interface with the specified description already exists.");
			break;
		}
	}
	/* input validation */
	if (isset($config['dhcpd']) && isset($config['dhcpd'][$if]['enable']) && (! preg_match("/^staticv4/", $_POST['type'])))
		$input_errors[] = gettext("The DHCP Server is active on this interface and it can be used only with a static IP configuration. Please disable the DHCP Server service on this interface first, then change the interface configuration.");
	if (isset($config['dhcpd6']) && isset($config['dhcpd6'][$if]['enable']) && (! preg_match("/^staticv6/", $_POST['type6'])))
		$input_errors[] = gettext("The DHCP6 Server is active on this interface and it can be used only with a static IPv6 configuration. Please disable the DHCPv6 Server service on this interface first, then change the interface configuration.");

	switch(strtolower($_POST['type'])) {
		case "staticv4":
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = array(gettext("IPv4 address"),gettext("Subnet bit count"),gettext("Gateway"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			if(is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if (is_ipaddrv4($vip['subnet']) && $vip['interface'] == $if)
						$input_errors[] = gettext("This interface is referenced by IPv4 VIPs. Please delete those before setting the interface to 'none' configuration.");
				}
			}
		case "dhcp":
			if (in_array($wancfg['ipaddr'], array("ppp", "pppoe", "pptp", "l2tp")))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type']);
			break;
		case "ppp":
			$reqdfields = explode(" ", "port phone");
			$reqdfieldsn = array(gettext("Modem Port"),gettext("Phone Number"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pppoe":
			if ($_POST['pppoe_dialondemand']) {
				$reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
				$reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pppoe_username pppoe_password");
				$reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pptp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote");
				$reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "l2tp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote");
				$reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
	}
	switch(strtolower($_POST['type6'])) {
		case "staticv6":
			$reqdfields = explode(" ", "ipaddrv6 subnetv6 gatewayv6");
			$reqdfieldsn = array(gettext("IPv6 address"),gettext("Subnet bit count"),gettext("Gateway"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			if(is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if (is_ipaddrv6($vip['subnet']) && $vip['interface'] == $if)
						$input_errors[] = gettext("This interface is referenced by IPv6 VIPs. Please delete those before setting the interface to 'none' configuration.");
				}
			}
		case "dhcp6":
			if (in_array($wancfg['ipaddrv6'], array()))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type6']);
			break;
		case "6rd":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if ($if != $ifent && (($config[interfaces][$ifent]['ipaddrv6'] == $_POST['type6']) || ($config[interfaces][$ifent]['ipaddrv6'] == "6to4")) ) {
					$input_errors[] = sprintf(gettext("You can only have one interface configured as %s or 6to4."),$_POST['type6']);
					break;
				}
			}
			if (in_array($wancfg['ipaddrv6'], array()))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type6']);
			break;
		case "6to4":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if ($if != $ifent && (($config[interfaces][$ifent]['ipaddrv6'] == $_POST['type6']) || ($config[interfaces][$ifent]['ipaddrv6'] == "6rd")) ) {
					$input_errors[] = sprintf(gettext("You can only have one interface configured as %s or 6rd."),$_POST['type6']);
					break;
				}
			}
			if (in_array($wancfg['ipaddrv6'], array()))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type6']);
			break;
		case "track6":
			/* needs to check if $track6-prefix-id is used on another interface */
			if (in_array($wancfg['ipaddrv6'], array()))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type6']);

			if ($_POST['track6-prefix-id--hex'] != "" && !is_numeric("0x" . $_POST['track6-prefix-id--hex'])) {
				$input_errors[] = gettext("You must enter a valid hexadecimal number for the IPv6 prefix ID.");
			} else {
				$track6_prefix_id = intval($_POST['track6-prefix-id--hex'], 16);
				if ($track6_prefix_id < 0 || $track6_prefix_id >= $ipv6_num_prefix_ids) {
					$input_errors[] = gettext("You specified an IPv6 prefix ID that is out of range.");
				}
			}
			break;
	}

	
	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr'])))
		$input_errors[] = gettext("A valid IPv4 address must be specified.");
	if (($_POST['ipaddrv6'] && !is_ipaddr($_POST['ipaddrv6'])))
		$input_errors[] = gettext("A valid IPv6 address must be specified.");
	if (($_POST['subnet'] && !is_numeric($_POST['subnet'])))
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	if (($_POST['subnetv6'] && !is_numeric($_POST['subnetv6'])))
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	if (($_POST['alias-address'] && !is_ipaddr($_POST['alias-address'])))
		$input_errors[] = gettext("A valid alias IP address must be specified.");
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet'])))
		$input_errors[] = gettext("A valid alias subnet bit count must be specified.");
	if (($_POST['gateway'] != "none") || ($_POST['gatewayv6'] != "none")) {
		$match = false;
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gateway'], $gateway)) {
				$match = true;
			}
		}
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gatewayv6'], $gateway)) {
				$match = true;
			}
		}
		if(!$match) {
			$input_errors[] = gettext("A valid gateway must be specified.");
		}
	}
	if (($_POST['provider'] && !is_domain($_POST['provider'])))
		$input_errors[] = gettext("The service name contains invalid characters.");
	if (($_POST['pppoe_idletimeout'] != "") && !is_numericint($_POST['pppoe_idletimeout']))
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	if ($_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) &&
		$_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23)
			$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	if ($_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) &&
		$_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59)
			$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	if ($_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate'])))
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	if (($_POST['pptp_local'] && !is_ipaddr($_POST['pptp_local'])))
		$input_errors[] = gettext("A valid PPTP local IP address must be specified.");
	if (($_POST['pptp_subnet'] && !is_numeric($_POST['pptp_subnet'])))
		$input_errors[] = gettext("A valid PPTP subnet bit count must be specified.");
	if (($_POST['pptp_remote'] && !is_ipaddr($_POST['pptp_remote']) && !is_hostname($_POST['gateway'][$iface])))
		$input_errors[] = gettext("A valid PPTP remote IP address must be specified.");
	if (($_POST['pptp_idletimeout'] != "") && !is_numericint($_POST['pptp_idletimeout']))
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	if (($_POST['spoofmac'] && !is_macaddr($_POST['spoofmac'])))
		$input_errors[] = gettext("A valid MAC address must be specified.");
	if ($_POST['mtu'] && ($_POST['mtu'] < 576))
		$input_errors[] = gettext("The MTU must be greater than 576 bytes.");
	if ($_POST['mss'] && ($_POST['mss'] < 576))
		$input_errors[] = gettext("The MSS must be greater than 576 bytes.");
	/* Wireless interface? */
	if (isset($wancfg['wireless'])) {
		$reqdfields = array("mode");
		$reqdfieldsn = array(gettext("Mode"));
		if ($_POST['mode'] == 'hostap') {
			$reqdfields[] = "ssid";
			$reqdfieldsn[] = gettext("SSID");
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		check_wireless_mode();
		/* loop through keys and enforce size */
		for ($i = 1; $i <= 4; $i++) {
			if ($_POST['key' . $i]) {
				/* 64 bit */
				if (strlen($_POST['key' . $i]) == 5)
					continue;
				if (strlen($_POST['key' . $i]) == 10) {
					/* hex key */
					if (stristr($_POST['key' . $i], "0x") == false) {
						$_POST['key' . $i] = "0x" . $_POST['key' . $i];
					}
					continue;
				}
				if (strlen($_POST['key' . $i]) == 12) {
					/* hex key */
					if(stristr($_POST['key' . $i], "0x") == false) {
					$_POST['key' . $i] = "0x" . $_POST['key' . $i];
					}
					continue;
				}
				/* 128 bit */
				if (strlen($_POST['key' . $i]) == 13)
					continue;
				if (strlen($_POST['key' . $i]) == 26) {
					/* hex key */
					if (stristr($_POST['key' . $i], "0x") == false)
						$_POST['key' . $i] = "0x" . $_POST['key' . $i];
					continue;
				}
				if(strlen($_POST['key' . $i]) == 28)
					continue;
				$input_errors[] =  gettext("Invalid WEP key size.   Sizes should be 40 (64) bit keys or 104 (128) bit.");
				break;
			}
		}

		if ($_POST['passphrase']) {
                	$passlen = strlen($_POST['passphrase']);
                	if ($passlen < 8 || $passlen > 64)
                        	$input_errors[] = gettext("The length of the passphrase should be between 8 and 63 characters.");
		}
	}
	if (!$input_errors) {
		if ($wancfg['ipaddr'] != $_POST['type']) {
			if (in_array($wancfg['ipaddr'], array("ppp", "pppoe", "pptp", "l2tp"))) {
				$wancfg['if'] = $a_ppps[$pppid]['ports'];
				unset($a_ppps[$pppid]);
			} else if ($wancfg['ipaddrv6'] == "dhcp6") {
				$pid = find_dhcp6c_process($wancfg['if']);
				if($pid)
					posix_kill($pid, SIGTERM);
			} else if ($wancfg['ipaddr'] == "dhcp") {
				$pid = find_dhclient_process($wancfg['if']);
				if($pid)
					posix_kill($pid, SIGTERM);
			}
				
		}
		$ppp = array();
		if ($wancfg['ipaddr'] != "ppp")
			unset($wancfg['ipaddr']);
		if ($wancfg['ipaddrv6'] != "ppp")
			unset($wancfg['ipaddrv6']);
		unset($wancfg['subnet']);
		unset($wancfg['gateway']);
		unset($wancfg['subnetv6']);
		unset($wancfg['gatewayv6']);
		unset($wancfg['dhcphostname']);
		unset($wancfg['dhcp6-duid']);
		unset($wancfg['dhcp6-ia-pd-len']);
		unset($wancfg['track6-interface']);
		unset($wancfg['track6-prefix-id']);		
		unset($wancfg['prefix-6rd']);
		unset($wancfg['prefix-6rd-v4plen']);
		unset($wancfg['gateway-6rd']);
		unset($wancfg['pppoe_password']);
		unset($wancfg['pptp_username']);
		unset($wancfg['pptp_password']);
		unset($wancfg['provider']);
		unset($wancfg['ondemand']);
		unset($wancfg['timeout']);
		if (empty($wancfg['pppoe']['pppoe-reset-type']))
			unset($wancfg['pppoe']['pppoe-reset-type']);
		unset($wancfg['local']);
		
		unset($wancfg['remote']);
		unset($a_ppps[$pppid]['apn']);
		unset($a_ppps[$pppid]['phone']);
		unset($a_ppps[$pppid]['localip']);
		unset($a_ppps[$pppid]['subnet']);
		unset($a_ppps[$pppid]['gateway']);
		unset($a_ppps[$pppid]['pppoe-reset-type']);
		unset($a_ppps[$pppid]['provider']);

		$wancfg['descr'] = remove_bad_chars($_POST['descr']);
		$wancfg['enable'] =  $_POST['enable']  == "yes" ? true : false;

		/* let return_gateways_array() do the magic on dynamic interfaces for us */
		switch($_POST['type']) {
			case "staticv4":
				$wancfg['ipaddr'] = $_POST['ipaddr'];
				$wancfg['subnet'] = $_POST['subnet'];
				if ($_POST['gateway'] != "none") {
					$wancfg['gateway'] = $_POST['gateway'];
				}
				break;
			case "dhcp":
				$wancfg['ipaddr'] = "dhcp";
				$wancfg['dhcphostname'] = $_POST['dhcphostname'];
				$wancfg['alias-address'] = $_POST['alias-address'];
				$wancfg['alias-subnet'] = $_POST['alias-subnet'];
				$wancfg['dhcp_plus'] = $_POST['dhcp_plus'] == "yes" ? true : false;
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "ppp":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				$a_ppps[$pppid]['ports'] = $_POST['port'];
				$a_ppps[$pppid]['username'] = $_POST['username'];
				$a_ppps[$pppid]['password'] = base64_encode($_POST['password']);
				$a_ppps[$pppid]['phone'] = $_POST['phone'];
				$a_ppps[$pppid]['apn'] = $_POST['apn'];
				$wancfg['if'] = $_POST['type'] . $_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				unset($a_ppps[$pppid]['ondemand']);
				unset($a_ppps[$pppid]['idletimeout']);
				break;

			case "pppoe":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				if (isset($_POST['ppp_port']))
					$a_ppps[$pppid]['ports'] = $_POST['ppp_port'];
				else
					$a_ppps[$pppid]['ports'] = $wancfg['if'];
				$a_ppps[$pppid]['username'] = $_POST['pppoe_username'];
				$a_ppps[$pppid]['password'] = base64_encode($_POST['pppoe_password']);
				if (!empty($_POST['provider']))
					$a_ppps[$pppid]['provider'] = $_POST['provider'];
				else
					$a_ppps[$pppid]['provider'] = true;
				$a_ppps[$pppid]['ondemand'] = $_POST['pppoe_dialondemand'] ? true : false;
				if (!empty($_POST['idletimeout']))
					$a_ppps[$pppid]['idletimeout'] = $_POST['pppoe_idletimeout'];
				else
					unset($a_ppps[$pppid]['idletimeout']);

				if (!empty($_POST['pppoe-reset-type']))
					$a_ppps[$pppid]['pppoe-reset-type'] = $_POST['pppoe-reset-type'];
				else
					unset($a_ppps[$pppid]['pppoe-reset-type']);
				$wancfg['if'] = $_POST['type'].$_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}

				break;
			case "pptp":
			case "l2tp":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				if (isset($_POST['ppp_port']))
					$a_ppps[$pppid]['ports'] = $_POST['ppp_port'];
				else
					$a_ppps[$pppid]['ports'] = $wancfg['if'];
				$a_ppps[$pppid]['username'] = $_POST['pptp_username'];
				$a_ppps[$pppid]['password'] = base64_encode($_POST['pptp_password']);
				$a_ppps[$pppid]['localip'] = $_POST['pptp_local'];
				$a_ppps[$pppid]['subnet'] = $_POST['pptp_subnet'];
				$a_ppps[$pppid]['gateway'] = $_POST['pptp_remote'];
				$a_ppps[$pppid]['ondemand'] = $_POST['pptp_dialondemand'] ? true : false;
				if (!empty($_POST['idletimeout']))
					$a_ppps[$pppid]['idletimeout'] = $_POST['pptp_idletimeout'];
				else
					unset($a_ppps[$pppid]['idletimeout']);
				$wancfg['if'] = $_POST['type'].$_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "none":
				break;
		}
		switch($_POST['type6']) {
			case "staticv6":
				$wancfg['ipaddrv6'] = $_POST['ipaddrv6'];
				$wancfg['subnetv6'] = $_POST['subnetv6'];
				if ($_POST['gatewayv6'] != "none") {
					$wancfg['gatewayv6'] = $_POST['gatewayv6'];
				}
				break;
			case "slaac":
				$wancfg['ipaddrv6'] = "slaac";
				break;
			case "dhcp6":
				$wancfg['ipaddrv6'] = "dhcp6";
				$wancfg['dhcp6-duid'] = $_POST['dhcp6-duid'];
				$wancfg['dhcp6-ia-pd-len'] = $_POST['dhcp6-ia-pd-len'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "6rd":
				$wancfg['ipaddrv6'] = "6rd";
				$wancfg['prefix-6rd'] = $_POST['prefix-6rd'];
				$wancfg['prefix-6rd-v4plen'] = $_POST['prefix-6rd-v4plen'];
				$wancfg['gateway-6rd'] = $_POST['gateway-6rd'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "6to4":
				$wancfg['ipaddrv6'] = "6to4";
				break;
			case "track6":
				$wancfg['ipaddrv6'] = "track6";
				$wancfg['track6-interface'] = $_POST['track6-interface'];
				if ($_POST['track6-prefix-id--hex'] === "") {
					$wancfg['track6-prefix-id'] = "none";
				} else if (is_numeric("0x" . $_POST['track6-prefix-id--hex'])) {
					$wancfg['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
				} else {
					$wancfg['track6-prefix-id'] = "none";
				}
				break;
			case "none":
				break;
		}
		handle_pppoe_reset($_POST);

		if($_POST['blockpriv'] == "yes") {
			$wancfg['blockpriv'] = true;
		} else {
			unset($wancfg['blockpriv']);
		}
		if($_POST['blockbogons'] == "yes") {
			$wancfg['blockbogons'] = true;
		} else {
			unset($wancfg['blockbogons']);
		}
		$wancfg['spoofmac'] = $_POST['spoofmac'];
		if (empty($_POST['mtu'])) {
			unset($wancfg['mtu']);
		} else {
			$wancfg['mtu'] = $_POST['mtu'];
		}
		if (empty($_POST['mss'])) {
			unset($wancfg['mss']);
		} else {
			$wancfg['mss'] = $_POST['mss'];
		}
		if (empty($_POST['mediaopt'])) {
			unset($wancfg['media']);
			unset($wancfg['mediaopt']);
		} else {
			$mediaopts = explode(' ', $_POST['mediaopt']);	
			if ($mediaopts[0] != ''){ $wancfg['media'] = $mediaopts[0]; }
			if ($mediaopts[1] != ''){ $wancfg['mediaopt'] = $mediaopts[1]; }
			else { unset($wancfg['mediaopt']); }
		}
		if (isset($wancfg['wireless'])) {
			handle_wireless_post();
		}

		conf_mount_ro();
		write_config();

		if (file_exists("{$g['tmp_path']}/.interfaces.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
		} else {
			$toapplylist = array();
		}
		$toapplylist[$if] = array();
		/* we need to be able remove IP aliases for IPv6 */
		if(($old_wancfg['ipaddrv6'] != $wancfg['ipaddrv6']) && (is_ipaddrv6($old_wancfg['ipaddrv6']))) {
			$toapplylist[$if]['ipaddrv6'] = $old_wancfg['ipaddrv6'];
		}
		
		file_put_contents("{$g['tmp_path']}/.interfaces.apply", serialize($toapplylist));

		mark_subsystem_dirty('interfaces');

		/* regenerate cron settings/crontab file */
		configure_cron();

		header("Location: interfaces.php?if={$if}");
		exit;
	}

} // end if($_POST)

function handle_wireless_post() {
	global $_POST, $config, $g, $wancfg, $if, $wl_countries_attr, $wlanbaseif;
	if (!is_array($wancfg['wireless']))
		$wancfg['wireless'] = array();
	$wancfg['wireless']['standard'] = $_POST['standard'];
	$wancfg['wireless']['mode'] = $_POST['mode'];
	$wancfg['wireless']['protmode'] = $_POST['protmode'];
	$wancfg['wireless']['ssid'] = $_POST['ssid'];
	$wancfg['wireless']['channel'] = $_POST['channel'];
	$wancfg['wireless']['authmode'] = $_POST['authmode'];
	$wancfg['wireless']['txpower'] = $_POST['txpower'];
	$wancfg['wireless']['distance'] = $_POST['distance'];
	$wancfg['wireless']['regdomain'] = $_POST['regdomain'];
	$wancfg['wireless']['regcountry'] = $_POST['regcountry'];
	$wancfg['wireless']['reglocation'] = $_POST['reglocation'];
	if (!empty($wancfg['wireless']['regdomain']) && !empty($wancfg['wireless']['regcountry'])) {
		foreach($wl_countries_attr as $wl_country) {
			if ($wancfg['wireless']['regcountry'] == $wl_country['ID']) {
				$wancfg['wireless']['regdomain'] = $wl_country['rd'][0]['REF'];
				break;
			}
		}
	}
	if (!is_array($wancfg['wireless']['wpa']))
		$wancfg['wireless']['wpa'] = array();
	$wancfg['wireless']['wpa']['macaddr_acl'] = $_POST['macaddr_acl'];
	$wancfg['wireless']['wpa']['auth_algs'] = $_POST['auth_algs'];
	$wancfg['wireless']['wpa']['wpa_mode'] = $_POST['wpa_mode'];
	$wancfg['wireless']['wpa']['wpa_key_mgmt'] = $_POST['wpa_key_mgmt'];
	$wancfg['wireless']['wpa']['wpa_pairwise'] = $_POST['wpa_pairwise'];
	$wancfg['wireless']['wpa']['wpa_group_rekey'] = $_POST['wpa_group_rekey'];
	$wancfg['wireless']['wpa']['wpa_gmk_rekey'] = $_POST['wpa_gmk_rekey'];
	$wancfg['wireless']['wpa']['passphrase'] = $_POST['passphrase'];
	$wancfg['wireless']['wpa']['ext_wpa_sw'] = $_POST['ext_wpa_sw'];
	$wancfg['wireless']['auth_server_addr'] = $_POST['auth_server_addr'];
	$wancfg['wireless']['auth_server_port'] = $_POST['auth_server_port'];
	$wancfg['wireless']['auth_server_shared_secret'] = $_POST['auth_server_shared_secret'];
	$wancfg['wireless']['auth_server_addr2'] = $_POST['auth_server_addr2'];
	$wancfg['wireless']['auth_server_port2'] = $_POST['auth_server_port2'];
	$wancfg['wireless']['auth_server_shared_secret2'] = $_POST['auth_server_shared_secret2'];
	
	if ($_POST['persistcommonwireless'] == "yes") {
		if (!is_array($config['wireless']))
			$config['wireless'] = array();
		if (!is_array($config['wireless']['interfaces']))
			$config['wireless']['interfaces'] = array();
		if (!is_array($config['wireless']['interfaces'][$wlanbaseif]))
			$config['wireless']['interfaces'][$wlanbaseif] = array();
	} else if (isset($config['wireless']['interfaces'][$wlanbaseif]))
		unset($config['wireless']['interfaces'][$wlanbaseif]);
	if (isset($_POST['diversity']) && is_numeric($_POST['diversity']))
		$wancfg['wireless']['diversity'] = $_POST['diversity'];
	else if (isset($wancfg['wireless']['diversity']))
		unset($wancfg['wireless']['diversity']);
	if (isset($_POST['txantenna']) && is_numeric($_POST['txantenna']))
		$wancfg['wireless']['txantenna'] = $_POST['txantenna'];
	else if (isset($wancfg['wireless']['txantenna']))
		unset($wancfg['wireless']['txantenna']);
	if (isset($_POST['rxantenna']) && is_numeric($_POST['rxantenna']))
		$wancfg['wireless']['rxantenna'] = $_POST['rxantenna'];
	else if (isset($wancfg['wireless']['rxantenna']))
		unset($wancfg['wireless']['rxantenna']);
	if ($_POST['hidessid_enable'] == "yes")
		$wancfg['wireless']['hidessid']['enable'] = true;
	else if (isset($wancfg['wireless']['hidessid']['enable']))
		unset($wancfg['wireless']['hidessid']['enable']);
	if ($_POST['mac_acl_enable'] == "yes")
		$wancfg['wireless']['wpa']['mac_acl_enable'] = true;
	else if (isset($wancfg['wireless']['wpa']['mac_acl_enable']))
		unset($wancfg['wireless']['wpa']['mac_acl_enable']);
	if ($_POST['rsn_preauth'] == "yes")
		$wancfg['wireless']['wpa']['rsn_preauth'] = true;
	else
		unset($wancfg['wireless']['wpa']['rsn_preauth']);
	if ($_POST['ieee8021x'] == "yes")
		$wancfg['wireless']['wpa']['ieee8021x']['enable'] = true;
	else if (isset($wancfg['wireless']['wpa']['ieee8021x']['enable']))
		unset($wancfg['wireless']['wpa']['ieee8021x']['enable']);
	if ($_POST['wpa_strict_rekey'] == "yes")
		$wancfg['wireless']['wpa']['wpa_strict_rekey'] = true;
	else if (isset($wancfg['wireless']['wpa']['wpa_strict_rekey']))
		unset($wancfg['wireless']['wpa']['wpa_strict_rekey']);
	if ($_POST['debug_mode'] == "yes")
		$wancfg['wireless']['wpa']['debug_mode'] = true;
	else if (isset($wancfg['wireless']['wpa']['debug_mode']))
		sunset($wancfg['wireless']['wpa']['debug_mode']);
	if ($_POST['wpa_enable'] == "yes")
		$wancfg['wireless']['wpa']['enable'] = $_POST['wpa_enable'] = true;
	else if (isset($wancfg['wireless']['wpa']['enable']))
		unset($wancfg['wireless']['wpa']['enable']);
	if ($_POST['wep_enable'] == "yes") {
		if (!is_array($wancfg['wireless']['wep']))
			$wancfg['wireless']['wep'] = array();
		$wancfg['wireless']['wep']['enable'] = $_POST['wep_enable'] = true;
	} else if (isset($wancfg['wireless']['wep']))
		unset($wancfg['wireless']['wep']);
	if ($_POST['wme_enable'] == "yes") {
		if (!is_array($wancfg['wireless']['wme']))
			$wancfg['wireless']['wme'] = array();
		$wancfg['wireless']['wme']['enable'] = $_POST['wme_enable'] = true;
	} else if (isset($wancfg['wireless']['wme']['enable']))
		unset($wancfg['wireless']['wme']['enable']);
	if ($_POST['puremode'] == "11g") {
		if (!is_array($wancfg['wireless']['pureg']))
			$wancfg['wireless']['pureg'] = array();
		$wancfg['wireless']['pureg']['enable'] = true;
	} else if ($_POST['puremode'] == "11n") {
		if (!is_array($wancfg['wireless']['puren']))
			$wancfg['wireless']['puren'] = array();
		$wancfg['wireless']['puren']['enable'] = true;
	} else {
		if (isset($wancfg['wireless']['pureg']))
			unset($wancfg['wireless']['pureg']);
		if (isset($wancfg['wireless']['puren']))
			unset($wancfg['wireless']['puren']);
	}
	if ($_POST['apbridge_enable'] == "yes") {
		if (!is_array($wancfg['wireless']['apbridge']))
			$wancfg['wireless']['apbridge'] = array();
		$wancfg['wireless']['apbridge']['enable'] = $_POST['apbridge_enable'] = true;
	} else if (isset($wancfg['wireless']['apbridge']['enable']))
		unset($wancfg['wireless']['apbridge']['enable']);
	if ($_POST['standard'] == "11g Turbo" || $_POST['standard'] == "11a Turbo") {
		if (!is_array($wancfg['wireless']['turbo']))
			$wancfg['wireless']['turbo'] = array();
		$wancfg['wireless']['turbo']['enable'] = true;
	} else if (isset($wancfg['wireless']['turbo']['enable']))
		unset($wancfg['wireless']['turbo']['enable']);
	$wancfg['wireless']['wep']['key'] = array();
	for ($i = 1; $i <= 4; $i++) {
		if ($_POST['key' . $i]) {
			$newkey = array();
			$newkey['value'] = $_POST['key' . $i];
			if ($_POST['txkey'] == $i)
				$newkey['txkey'] = true;
			$wancfg['wireless']['wep']['key'][] = $newkey;
		}
	}
	interface_sync_wireless_clones($wancfg, true);
}

function check_wireless_mode() {
	global $_POST, $config, $g, $wlan_modes, $wancfg, $if, $wlanif, $wlanbaseif, $old_wireless_mode, $input_errors;

	if ($wancfg['wireless']['mode'] == $_POST['mode'])
		return;

	if (does_interface_exist(interface_get_wireless_clone($wlanbaseif)))
		$clone_count = 1;
	else
		$clone_count = 0;
	if (isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as $clone) {
			if ($clone['if'] == $wlanbaseif)
				$clone_count++;
		}
	}
	if ($clone_count > 1) {
		$old_wireless_mode = $wancfg['wireless']['mode'];
		$wancfg['wireless']['mode'] = $_POST['mode'];
		if (!interface_wireless_clone("{$wlanif}_", $wancfg)) {
			$input_errors[] = sprintf(gettext("Unable to change mode to %s.  You may already have the maximum number of wireless clones supported in this mode."), $wlan_modes[$wancfg['wireless']['mode']]);
		} else {
			mwexec("/sbin/ifconfig {$wlanif}_ destroy");
		}
		$wancfg['wireless']['mode'] = $old_wireless_mode;
	}
}

// Find all possible media options for the interface
$mediaopts_list = array();
$intrealname = $config['interfaces'][$if]['if'];
exec("/sbin/ifconfig -m $intrealname | grep \"media \"", $mediaopts);
foreach ($mediaopts as $mediaopt){
	preg_match("/media (.*)/", $mediaopt, $matches);
 	if (preg_match("/(.*) mediaopt (.*)/", $matches[1], $matches1)){
		// there is media + mediaopt like "media 1000baseT mediaopt full-duplex"
 		array_push($mediaopts_list, $matches1[1] . " " . $matches1[2]);
	}else{
		// there is only media like "media 1000baseT"
		array_push($mediaopts_list, $matches[1]);
	}
}

$pgtitle = array(gettext("Interfaces"), $pconfig['descr']);
$shortcut_section = "interfaces";

$closehead = false;
include("head.inc");
$types4 = array("none" => gettext("None"), "staticv4" => gettext("Static IPv4"), "dhcp" => gettext("DHCP"), "ppp" => gettext("PPP"), "pppoe" => gettext("PPPoE"), "pptp" => gettext("PPTP"), "l2tp" => gettext("L2TP"));
$types6 = array("none" => gettext("None"), "staticv6" => gettext("Static IPv6"), "dhcp6" => gettext("DHCP6"), "slaac" => gettext("SLAAC"), "6rd" => gettext("6rd Tunnel"), "6to4" => gettext("6to4 Tunnel"), "track6" => gettext("Track Interface"));

?>

<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css"/>

<script type="text/javascript">
	function updateType(t) {
		switch(t) {
			case "none": {
				jQuery('#staticv4, #dhcp, #pppoe, #pptp, #ppp').hide();
				break;
			}
			case "staticv4": {
				jQuery('#none, #dhcp, #pppoe, #pptp, #ppp').hide();
				break;
			}
			case "dhcp": {
				jQuery('#none, #staticv4, #pppoe, #pptp, #ppp').hide();
				break;
			}
			case "ppp": {
				jQuery('#none, #staticv4, #dhcp, #pptp, #pppoe').hide();
				country_list();
				break;
			}
			case "pppoe": {
				jQuery('#none, #staticv4, #dhcp, #pptp, #ppp').hide();
				break;
			}
			case "l2tp":
			case "pptp": {
				jQuery('#none, #staticv4, #dhcp, #pppoe, #ppp').hide();
				jQuery('#pptp').show();
				break;
			}
		}
		if (t != "l2tp" && t != "pptp")
			jQuery('#'+t).show();
	}
	function updateTypeSix(t) {
		switch(t) {
			case "none": {
				jQuery('#staticv6, #dhcp6, #6rd, #6to4, #track6, #slaac').hide();
				break;
			}
			case "staticv6": {
				jQuery('#none, #dhcp6, #6rd, #6to4, #track6, #slaac').hide();
				break;
			}
			case "slaac": {
				jQuery('#none, #staticv6, #6rd, #6to4, #track6, #dhcp6').hide();
				break;
			}
			case "dhcp6": {
				jQuery('#none, #staticv6, #6rd, #6to4, #track6, #slaac').hide();
				break;
			}
			case "6rd": {
				jQuery('#none, #dhcp6, #staticv6, #6to4, #track6, #slaac').hide();
				break;
			}
			case "6to4": {
				jQuery('#none, #dhcp6, #staticv6, #6rd, #track6, #slaac').hide();
				break;
			}
			case "track6": {
				jQuery('#none, #dhcp6, #staticv6, #6rd, #6to4, #slaac').hide();
				break;
			}
		}
		if (t != "l2tp" && t != "pptp")
			jQuery('#'+t).show();
	}

	function show_allcfg(obj) {
		if (obj.checked)
			jQuery('#allcfg').show();
		else
			jQuery('#allcfg').hide();
	}

	function show_reset_settings(reset_type) {
		if (reset_type == 'preset') {
			jQuery('#pppoepresetwrap').show();
			jQuery('#pppoecustomwrap').hide();
		}
		else if (reset_type == 'custom') {
			jQuery('#pppoecustomwrap').show();
			jQuery('#pppoepresetwrap').hide();
		} else {
			jQuery('#pppoecustomwrap').hide();
			jQuery('#pppoepresetwrap').hide();
		}
	}
	function show_mon_config() {
		jQuery("#showmonbox").html('');
		jQuery('#showmon').css('display','block');
	}

	function openwindow(url) {
		var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
		if (oWin==null || typeof(oWin)=="undefined")
			return false;
		else
			return true;
	}
	function country_list() {
		jQuery('#country').children().remove();
		jQuery('#provider').children().remove();
		jQuery('#providerplan').children().remove();
		jQuery.ajax("getserviceproviders.php",{
			success: function(response) {
				var responseTextArr = response.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					var option = new Element('option');
					country = value.split(":");
					option.text = country[0];
					option.value = country[1];
					jQuery('#country').append(option);
				});
			}
		});
		jQuery('#trcountry').css('display',"table-row");
	}

	function providers_list() {
		jQuery('#provider').children().remove();
		jQuery('#providerplan').children().remove();
		jQuery.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : jQuery('#country').val()},
			success: function(response) {
				var responseTextArr = response.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					var option = new Element('option');
					option.text = value;
					option.value = value;
					jQuery('#provider').append(option);
				});
			}
		});
		jQuery('#trprovider').css("display","table-row");
		jQuery('#trproviderplan').css("display","none");
	}

	function providerplan_list() {
		jQuery('#providerplan').children().remove();
		jQuery('#providerplan').append( new Element('option') );
		jQuery.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : jQuery('#country').val(), provider : jQuery('#provider').val()},
			success: function(response) {
				var responseTextArr = response.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					if(value != "") {
						providerplan = value.split(":");

						var option = new Element('option');
						option.text = providerplan[0] + " - " + providerplan[1];
						option.value = providerplan[1];
						jQuery('#providerplan').append(option);
					}
				});
			}
		});
		jQuery('#trproviderplan').css("display","table-row");
	}

	function prefill_provider() {
		jQuery.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : jQuery('#country').val(), provider : jQuery('#provider').val(), plan : jQuery('#providerplan').val()},
			success: function(data,textStatus,response) {
				var xmldoc = response.responseXML;
				var provider = xmldoc.getElementsByTagName('connection')[0];
				jQuery('#username').val('');
				jQuery('#password').val('');
				if(provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
					jQuery('#phone').val('#777');
					jQuery('#apn').val('');
				} else {
					jQuery('#phone').val('*99#');
					jQuery('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
				}
				username = provider.getElementsByTagName('username')[0].firstChild.data;
				password = provider.getElementsByTagName('password')[0].firstChild.data;
				jQuery('#username').val(username);
				jQuery('#password').val(password);
			}
		});
	}

</script>
</head>
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<form action="interfaces.php" method="post" name="iform" id="iform">
		<?php if ($input_errors) print_input_errors($input_errors); ?>
		<?php if (is_subsystem_dirty('interfaces')): ?><p>
		<?php print_info_box_np(sprintf(gettext("The %s configuration has been changed."),$wancfg['descr'])."<p>".gettext("You must apply the changes in order for them to take effect.")."<p>".gettext("Don't forget to adjust the DHCP Server range if needed after applying."));?><br />
		<?php endif; ?>
		<?php if ($savemsg) print_info_box($savemsg); ?>
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td id="mainarea">
					<div class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("General configuration"); ?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Enable"); ?></td>
							<td width="78%" class="vtable">
								<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable'] == true) echo "checked"; ?> onClick="show_allcfg(this);">
							<strong><?=gettext("Enable Interface"); ?></strong>
							</td>
						</tr>
					</table>
					<div style="display:none;" name="allcfg" id="allcfg">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
							<td width="78%" class="vtable">
								<input name="descr" type="text" class="formfld unknown" id="descr" size="30" value="<?=htmlspecialchars($pconfig['descr']);?>">
								<br><span class="vexpl"><?= gettext("Enter a description (name) for the interface here."); ?></span>
							</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell"><strong><?=gettext("IPv4 Configuration Type"); ?></strong></td>
							<td class="vtable">
								<select name="type" onChange="updateType(this.value);" class="formselect" id="type">
								<?php
									foreach ($types4 as $key => $opt) {
										echo "<option onClick=\"updateType('{$key}');\"";
										if ($key == $pconfig['type'])
											echo " selected";
										echo " value=\"{$key}\" >" . htmlspecialchars($opt);
										echo "</option>";
									}
								?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell"><strong><?=gettext("IPv6 Configuration Type"); ?></strong></td>
							<td class="vtable">
								<select name="type6" onChange="updateTypeSix(this.value);" class="formselect" id="type6">
								<?php
									foreach ($types6 as $key => $opt) {
										echo "<option onClick=\"updateTypeSix('{$key}');\"";
										if ($key == $pconfig['type6'])
											echo " selected";
										echo " value=\"{$key}\" >" . htmlspecialchars($opt);
										echo "</option>";
									}
								?>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("MAC address"); ?></td>
							<td class="vtable">
								<input name="spoofmac" type="text" class="formfld unknown" id="spoofmac" size="30" value="<?=htmlspecialchars($pconfig['spoofmac']);?>">
								<?php
									$ip = getenv('REMOTE_ADDR');
									$mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
									$mac = str_replace("\n","",$mac);
									if($mac):
								?>
									<a OnClick="document.forms[0].spoofmac.value='<?=$mac?>';" href="#"><?=gettext("Insert my local MAC address"); ?></a>
								<?php endif; ?>
								<br>
								<?=gettext("This field can be used to modify (\"spoof\") the MAC " .
								"address of this interface"); ?><br>
								<?=gettext("(may be required with some cable connections)"); ?><br>
								<?=gettext("Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx " .
								"or leave blank"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("MTU"); ?></td>
							<td class="vtable">
								<input name="mtu" type="text" class="formfld unknown" id="mtu" size="8" value="<?=htmlspecialchars($pconfig['mtu']);?>">
								<br>
								<?=gettext("If you leave this field blank, the adapter's default MTU will " .
								"be used. This is typically 1500 bytes but can vary in some circumstances."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("MSS"); ?></td>
							<td class="vtable">
								<input name="mss" type="text" class="formfld unknown" id="mss" size="8" value="<?=htmlspecialchars($pconfig['mss']);?>">
								<br>
								<?=gettext("If you enter a value in this field, then MSS clamping for " .
								"TCP connections to the value entered above minus 40 (TCP/IP " .
								"header size) will be in effect."); ?>
							</td>
						</tr>
						<?php 
						if (count($mediaopts_list) > 0){
						$mediaopt_from_config = $config['interfaces'][$if]['media'] . ' ' . $config['interfaces'][$if]['mediaopt'];
						echo "<tr>";
							echo '<td valign="top" class="vncell">' . gettext("Speed and duplex") . '</td>';
							echo '<td class="vtable">';
                                			echo '<div id="showadvmediabox"';
								if ($mediaopt_from_config != 'autoselect ' && $mediaopt_from_config != ' ') echo " style='display:none'>";
								else echo '>';
								echo '<input type="button" onClick="show_advanced_media()" value="' . gettext("Advanced") . '"></input> - ' . gettext("Show advanced option");
							echo "</div>";
							echo '<div id="showmediaadv" ';
							if ($mediaopt_from_config == 'autoselect ' || $mediaopt_from_config == ' ') echo "style='display:none'>";
							else echo '>';
								echo '<select name="mediaopt" class="formselect" id="mediaopt">';
								print "<option value=\"\">Default (no preference, typically autoselect)</option>";
								print "<option value=\"\">------- Media Supported by this interface -------</option>";
								foreach($mediaopts_list as $mediaopt){
									if ($mediaopt != rtrim($mediaopt_from_config)){
										print "<option value=\"$mediaopt\">" . gettext("$mediaopt") . "</option>";
									} else {
										print "<option value=\"$mediaopt\" selected>" . gettext("$mediaopt") . "</option>";
									}
								}
								echo '</select><br>';
								echo gettext("Here you can explicitly set speed and duplex mode for this interface. WARNING: You MUST leave this set to autoselect (automatically negotiate speed) unless the port this interface connects to has its speed and duplex forced.");
						echo '</div>';
							echo '</td>';
						echo '</tr>';
						}
						?>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr style="display:none;" name="none" id="none">
						</tr>
						<tr style="display:none;" name="staticv4" id="staticv4">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Static IPv4 configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IPv4 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="ipaddr" type="text" class="formfld unknown" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>">
											/
											<select name="subnet" class="formselect" id="subnet">
												<?php
												for ($i = 32; $i > 0; $i--) {
													if($i <> 31) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['subnet']) echo "selected";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Gateway"); ?></td>
										<td width="78%" class="vtable">
											<select name="gateway" class="formselect" id="gateway">
												<option value="none" selected><?=gettext("None"); ?></option>
													<?php
													if(count($a_gateways) > 0) {
														foreach ($a_gateways as $gateway) {
															if(($gateway['interface'] == $if)  && (is_ipaddrv4($gateway['gateway']))) {
													?>
															<option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gateway']) echo "selected"; ?>>
																<?=htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']);?>
															</option>
													<?php
															}
														}
													}
													?>
											</select>
											- or  <strong><a OnClick="show_add_gateway();" href="#"><?=gettext("add a new one."); ?></a></strong>
											<br/>
											<div id='addgwbox'>
												<?=gettext("If this interface is an Internet connection, select an existing Gateway from the list or add one using the link above"); ?>
											</div>
											<div id='notebox'>
											</div>
											<div id="status">
											</div>
											<div style="display:none" id="addgateway" name="addgateway">
												<p>
												<table border="1" style="background:#990000; border-style: none none none none; width:225px;">
													<tr>
														<td>
															<table bgcolor="#990000" cellpadding="1" cellspacing="1">
																<tr><td>&nbsp;</td>
																<tr>
																	<td colspan="2"><center><b><font color="white"><?=gettext("Add new gateway:"); ?></font></b></center></td>
																</tr>
																<tr><td>&nbsp;</td>
																<?php
																if($if == "wan" || $if == "WAN")
																	$checked = " CHECKED";
																?>
																<tr>
																	<td width="45%" align="right"><font color="white"><?=gettext("Default  gateway:"); ?></td><td><input type="checkbox" id="defaultgw" name="defaultgw"<?=$checked?>></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway Name:"); ?></td><td><input id="name" name="name" value="<?=$wancfg['descr'] . "GW"?>"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway IPv4:"); ?></td><td><input id="gatewayip" name="gatewayip"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Description:"); ?></td><td><input id="gatewaydescr" name="gatewaydescr"></td>
																</tr>
																<tr><td>&nbsp;</td>
																<tr>
																	<td>&nbsp;</td>
																	<td>
																		<center>
																			<div id='savebuttondiv'>


																				<input id="gwsave" type="Button" value="<?=gettext("Save Gateway"); ?>" onClick='hide_add_gatewaysave();'>
																				<input id="gwcancel" type="Button" value="<?=gettext("Cancel"); ?>" onClick='hide_add_gateway();'>
																			</div>
																		</center>
																	</td>
																</tr>
																<tr><td>&nbsp;</td></tr>
															</table>
														</td>
													</tr>
												</table>
												<p/>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="staticv6" id="staticv6">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top">&nbsp;</td>
									</tr>
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Static IPv6 configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IPv6 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="ipaddrv6" type="text" class="formfld unknown ipv4v6" id="ipaddrv6" size="28" value="<?=htmlspecialchars($pconfig['ipaddrv6']);?>">
											/
											<select name="subnetv6" class="formselect ipv4v6" id="subnetv6">
												<?php
												for ($i = 128; $i > 0; $i--) {
													if($i <> 127) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['subnetv6']) echo "selected";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Gateway IPv6"); ?></td>
										<td width="78%" class="vtable">
											<select name="gatewayv6" class="formselect" id="gatewayv6">
												<option value="none" selected><?=gettext("None"); ?></option>
													<?php
													if(count($a_gateways) > 0) {
														foreach ($a_gateways as $gateway) {
															if(($gateway['interface'] == $if) && (is_ipaddrv6($gateway['gateway']))) {
													?>
															<option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gatewayv6']) echo "selected"; ?>>
																<?=htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']);?>
															</option>
													<?php
															}
														}
													}
													?>
											</select>
											- or <strong><a OnClick="show_add_gateway_v6();" href="#"><?=gettext("add a new one."); ?></a></strong>
											<br/>
											<div id='addgwboxv6'>
											<?=gettext("If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the link above"); ?>
											</div>
											<div id='noteboxv6'>
											</div>
											<div id="statusv6">
											</div>
											<div style="display:none" id="addgatewayv6" name="addgatewayv6">
												<p>
												<table border="1" style="background:#990000; border-style: none none none none; width:225px;">
													<tr>
														<td>
															<table bgcolor="#990000" cellpadding="1" cellspacing="1">
																<tr><td>&nbsp;</td>
																<tr>
																	<td colspan="2"><center><b><font color="white"><?=gettext("Add new v6 gateway:"); ?></font></b></center></td>
																</tr>
																<tr><td>&nbsp;</td>
																<?php
																if($if == "wan" || $if == "WAN")
																	$checked = " CHECKED";
																?>
																<tr>
																	<td width="45%" align="right"><font color="white"><?=gettext("Default v6 gateway:"); ?></td><td><input type="checkbox" id="defaultgwv6" name="defaultgwv6"<?=$checked?>></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway Name IPv6:"); ?></td><td><input id="namev6" name="namev6" value="<?=$wancfg['descr'] . "GWv6"?>"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway IPv6:"); ?></td><td><input id="gatewayipv6" name="gatewayipv6"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Description:"); ?></td><td><input id="gatewaydescrv6" name="gatewaydescrv6"></td>
																</tr>
																<tr><td>&nbsp;</td>
																<tr>
																	<td>&nbsp;</td>
																	<td>
																		<center>
																			<div id='savebuttondivv6'>
																				<input id="gwsavev6" type="Button" value="<?=gettext("Save Gateway"); ?>" onClick='hide_add_gatewaysave_v6();'>
																				<input id="gwcancelv6" type="Button" value="<?=gettext("Cancel"); ?>" onClick='hide_add_gateway_v6();'>
																			</div>
																		</center>
																	</td>
																</tr>
																<tr><td>&nbsp;</td></tr>
															</table>
														</td>
													</tr>
												</table>
												<p/>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="dhcp" id="dhcp">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("DHCP client configuration"); ?></td>
									</tr>
									<!-- Uncomment to expose DHCP+ in GUI
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Enable DHCP+"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp_plus" type="checkbox" value="yes" <?php if ($pconfig['dhcp_plus'] == true) echo "checked"; ?> >
										<strong><?=gettext("Enable DHCP+L2TP or DHCP+PPTP."); ?></strong>
										<br/>
										<?=gettext("Status changes on this interface will trigger reconfiguration (if necessary) of the associated PPTP/L2TP link."); ?>
										</td>
									</tr>
									-->
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Hostname"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcphostname" type="text" class="formfld unknown" id="dhcphostname" size="40" value="<?=htmlspecialchars($pconfig['dhcphostname']);?>">
											<br>
											<?=gettext("The value in this field is sent as the DHCP client identifier " .
											"and hostname when requesting a DHCP lease. Some ISPs may require " .
											"this (for client identification)."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Alias IPv4 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="alias-address" type="text" class="formfld unknown" id="alias-address" size="20" value="<?=htmlspecialchars($pconfig['alias-address']);?>">
											<select name="alias-subnet" class="formselect" id="alias-subnet">
												<?php
												for ($i = 32; $i > 0; $i--) {
													if($i <> 31) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['alias-subnet']) echo "selected";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
											<?=gettext("The value in this field is used as a fixed alias IPv4 address by the " .
											"DHCP client."); ?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="dhcp6" id="dhcp6">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("DHCP6 client configuration"); ?></td>
									</tr>
									<!--- Leave commented out for now
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("DHCPv6 Unique Identifier (DUID)"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp6-duid" type="text" class="formfld unknown" id="dhcp6-duid" size="40" value="<?=htmlspecialchars($pconfig['dhcp6-duid']);?>">
											<br>
											<?=gettext("The value in this field is sent as the DHCPv6 client identifier " .
											"when requesting a DHCPv6 lease."); ?><br />
											<?php	if(is_readable("/var/db/dhcp6c_duid")) {
													// $current_duid = file_get_contents("/var/db/dhcp6c_duid");
												}
												printf(gettext("The current DUID is: '%s'"),$current_duid);
												// hexdump -e '"%07.7_ax " 1/2 "%04x" " " 14/1 "%02x:" "\n"'
											?>
										</td>
									</tr>
									-->
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("DHCPv6 Prefix Delegation size"); ?></td>
										<td width="78%" class="vtable">
											<select name="dhcp6-ia-pd-len" class="formselect" id="dhcp6-ia-pd-len">
												<?php
												$sizes = array("none" => "None", 16 => "48", 12 => "52", 8 => "56", 4 => "60", 2 => "62", 1 => "63", 0 => "64");
												foreach($sizes as $bits => $length) {
													echo "<option value=\"{$bits}\" ";
													if (is_numeric($pconfig['dhcp6-ia-pd-len']) && ($bits == $pconfig['dhcp6-ia-pd-len'])) echo "selected";
													echo ">" . $length . "</option>";
												}
												?>
											</select>
											<br>
											<?=gettext("The value in this field is the delegated prefix length provided by the DHCPv6 server. Normally specified by the ISP."); ?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="6rd" id="6rd">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("6RD Rapid Deployment"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("6RD prefix"); ?></td>
										<td width="78%" class="vtable">
											<input name="prefix-6rd" type="text" class="formfld unknown" id="prefix-6rd" size="40" value="<?=htmlspecialchars($pconfig['prefix-6rd']);?>">
											<br>
											<?=gettext("The value in this field is the 6RD IPv6 prefix assigned by your ISP. e.g. '2001:db8::/32'") ?><br />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("6RD Border Relay"); ?></td>
										<td width="78%" class="vtable">
											<input name="gateway-6rd" type="text" class="formfld unknown" id="gateway-6rd" size="40" value="<?=htmlspecialchars($pconfig['gateway-6rd']);?>">
											<br>
											<?=gettext("The value in this field is 6RD IPv4 gateway address assigned by your ISP") ?><br />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("6RD IPv4 Prefix length"); ?></td>
										<td width="78%" class="vtable">
											<select name="prefix-6rd-v4plen" class="formselect" id="prefix-6rd-v4plen">
												<?php
												for ($i = 0; $i < 32; $i++) {
													echo "<option value=\"{$i}\" ";
													if (is_numeric($pconfig['prefix-6rd-v4plen']) && ($i == $pconfig['prefix-6rd-v4plen'])) echo "selected";
													echo ">" . $i . " bits</option>";
												}
												?>
											</select>
											<br>
											<?=gettext("The value in this field is the 6RD IPv4 prefix length. Normally specified by the ISP. A value of 0 means we embed the entire IPv4 address in the 6RD prefix."); ?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="track6" id="track6">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Track IPv6 Interface"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Interface"); ?></td>
										<td width="78%" class="vtable">
										<select name='track6-interface' class='formselect' >
										<?php 
											$interfaces = get_configured_interface_with_descr(false, true);
											$dynv6ifs = array();
											foreach ($interfaces as $iface => $ifacename) {
												switch($config['interfaces'][$iface]['ipaddrv6']) {
													case "6to4":
													case "6rd":
													case "dhcp6":
														$dynv6ifs[$iface] = $ifacename;
														break;
													default:
														continue;
												}
											}
											foreach($dynv6ifs as $iface => $ifacename) {
												echo "<option value=\"{$iface}\"";
												if ($iface == $pconfig['track6-interface'])
													echo " selected";
												echo ">" . htmlspecialchars($ifacename) . "</option>";
											}
										  ?>
										</select> <br>
											<br>
											<?=gettext("This selects the dynamic IPv6 WAN interface to track for configuration") ?><br />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Prefix ID"); ?></td>
										<td width="78%" class="vtable">
											<?php
												if ($pconfig['track6-prefix-id'] == "none" || $pconfig['track6-prefix-id'] == "") {
													$track6_prefix_id_hex = "";
												} else {
													$track6_prefix_id_hex = sprintf("%x", $pconfig['track6-prefix-id']);
												}
											?>
											<input name="track6-prefix-id--hex" type="text" class="formfld unknown" id="track6-prefix-id--hex" size="8" value="<?= $track6_prefix_id_hex ?>" />
											<br />
											<?= gettext("The value in this field is the (Delegated) IPv6 prefix id. This determines the configurable network ID based on the dynamic IPv6 connection"); ?>
											<br />
											<?= sprintf(gettext("Enter a <b>hexadecimal</b> value between %x and %x here, or leave blank."), 0, $ipv6_num_prefix_ids - 1); ?>
										</td>
									</tr>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="ppp" id="ppp">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPP configuration"); ?></td>
									</tr>
									<tr name="ppp_provider" id="ppp_provider">
										<td width="22%" valign="top" class="vncell"><?=gettext("Service Provider"); ?></td>
										<td width="78%" class="vtable">
											<table border="0" cellpadding="0" cellspacing="0">
												<tr id="trcountry">
													<td><?=gettext("Country:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="country" id="country" onChange="providers_list()">
															<option></option>
														</select>
													</td>
												</tr>
												<tr id="trprovider" style="display:none">
													<td><?=gettext("Provider:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="provider" id="provider" onChange="providerplan_list()">
															<option></option>
														</select>
													</td>
												</tr>
												<tr id="trproviderplan" style="display:none">
													<td><?=gettext("Plan:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="providerplan" id="providerplan" onChange="prefill_provider()">
															<option></option>
														</select>
													</td>
												</tr>
											</table>
											<br/><span class="vexpl"><?=gettext("Select to fill in data for your service provider."); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
										<input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
										<input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>">
										</td>
									</tr>
									<tr name="phone_num" id="phone_num">
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Phone Number"); ?></td>
										<td width="78%" class="vtable">
											<input name="phone" type="text" class="formfld unknown" id="phone" size="12" value="<?=htmlspecialchars($pconfig['phone']);?>">
										</td>
									</tr>
									<tr name="apn_" id="apn_">
										<td width="22%" valign="top" class="vncell"><?=gettext("Access Point Name (APN)"); ?></td>
										<td width="78%" class="vtable">
											<input name="apn" type="text" class="formfld unknown" id="apn" size="40" value="<?=htmlspecialchars($pconfig['apn']);?>">
										</td>
									</tr>
									<tr name="interface" id="interface" >
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Modem Port"); ?></td>
										<td width="78%" class="vtable">
											<select name="port" id="port" class="formselect">
											<?php
												$portlist = glob("/dev/cua*");
												$modems = glob("/dev/modem*");
												$portlist = array_merge($portlist, $modems);
												foreach ($portlist as $port) {
													if(preg_match("/\.(lock|init)$/", $port))
														continue;
													echo "<option value=\"".trim($port)."\"";
													if ($pconfig['port'] == $port)
														echo "selected";
													echo ">{$port}</option>";
												}?>
											</select>
										</td>
									</tr>
									<td width="22%" valign="top" class="vncell"><?=gettext("Advanced PPP"); ?></td>
										<?php if (isset($pconfig['pppid'])): ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php?id=<?=htmlspecialchars($pconfig['pppid']);?>" class="navlnk"><?=gettext("Click here"); ?> </a>
											<?=gettext("to edit PPP configuration."); ?>
											</td>
										<?php else: ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php" class="navlnk"><?=gettext("Click here"); ?> </a>
											<?=gettext("to create a PPP configuration."); ?>
											</td>
										<?php endif; ?>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="pppoe" id="pppoe">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPPoE configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
												<input name="pppoe_username" type="text" class="formfld user" id="pppoe_username" size="20" value="<?=htmlspecialchars($pconfig['pppoe_username']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_password" type="password" class="formfld pwd" id="pppoe_password" size="20" value="<?=htmlspecialchars($pconfig['pppoe_password']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Service name"); ?></td>
										<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
											<br> <span class="vexpl"><?=gettext("Hint: this field can usually be left empty"); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Dial on demand"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_dialondemand" type="checkbox" id="pppoe_dialondemand" value="enable" <?php if ($pconfig['pppoe_dialondemand']) echo "checked"; ?>>
											<strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br>
											<?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a "); ?><i><?=gettext("virtual full time"); ?></i> <?=gettext("connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_idletimeout" type="text" class="formfld unknown" id="pppoe_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pppoe_idletimeout']);?>"> <?=gettext("seconds"); ?><br><?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
										<td width="78%" class="vtable">
											<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%">
												<tr>
													<td align="left" valign="top">
														<p style="margin: 4px; padding: 4px 0 4px 0; width: 94%;">
														<select valign="top" id="reset_type" name="pppoe-reset-type" class="formselect" onChange="show_reset_settings(this.value);">
															<option value = ""><?=gettext("Disabled"); ?></option>
															<option value="custom" <?php if ($pconfig['pppoe-reset-type'] == "custom") echo "selected"; ?>><?=gettext("Custom"); ?></option>
															<option value="preset" <?php if ($pconfig['pppoe-reset-type'] == "preset") echo "selected"; ?>><?=gettext("Pre-Set"); ?></option>
														</select> <?=gettext("Select a reset timing type"); ?>
														</p>
														<?php if ($pconfig['pppoe_pr_custom']): ?>
															<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoecustomwrap">
														<?php else: ?>
															<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoecustomwrap">
														<?php endif; ?>
														<input type="text" name="pppoe_resethour" class="fd_incremental_inp_range_0_23 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resethour" value="<?= $pconfig['pppoe_resethour']; ?>" size="3" />
														<?=gettext("hour (0-23)"); ?><br />
														<input type="text" name="pppoe_resetminute" class="fd_incremental_inp_range_0_59 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resetminute" value="<?= $pconfig['pppoe_resetminute']; ?>" size="3" />
														<?=gettext("minute (0-59)"); ?><br />
														<input name="pppoe_resetdate" type="text" class="w8em format-m-d-y highlight-days-67" id="pppoe_resetdate" maxlength="10" size="10" value="<?=htmlspecialchars($pconfig['pppoe_resetdate']);?>" />
														<?=gettext("reset at a specific date (mm/dd/yyyy)"); ?>
														<br />&nbsp;<br />
														<span class="red"><strong><?=gettext("Note:"); ?> </strong></span>
														<?=gettext("If you leave the date field empty, the reset will be executed each day at the time you did specify using the minutes and hour field."); ?>
														</p>
														<?php if ($pconfig['pppoe_pr_preset']): ?>
															<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoepresetwrap">
														<?php else: ?>
															<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoepresetwrap">
														<?php endif; ?>
														<input name="pppoe_pr_preset_val" type="radio" id="pppoe_monthly" value="monthly" <?php if ($pconfig['pppoe_monthly']) echo "checked=\"checked\""; ?> />
														<?=gettext("reset at each month ('0 0 1 * *')"); ?>
														<br />
														<input name="pppoe_pr_preset_val" type="radio" id="pppoe_weekly" value="weekly" <?php if ($pconfig['pppoe_weekly']) echo "checked=\"checked\""; ?> />
														<?=gettext("reset at each week ('0 0 * * 0')"); ?>
														<br />
														<input name="pppoe_pr_preset_val" type="radio" id="pppoe_daily" value="daily" <?php if ($pconfig['pppoe_daily']) echo "checked=\"checked\""; ?> />
														<?=gettext("reset at each day ('0 0 * * *')"); ?>
														<br />
														<input name="pppoe_pr_preset_val" type="radio" id="pppoe_hourly" value="hourly" <?php if ($pconfig['pppoe_hourly']) echo "checked=\"checked\""; ?> />
														<?=gettext("reset at each hour ('0 * * * *')"); ?>
														</p>
													</td>
												</tr>
											</table>
										</td>
									</tr>

									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Advanced and MLPPP"); ?></td>
										<?php if (isset($pconfig['pppid'])): ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php?id=<?=htmlspecialchars($pconfig['pppid']);?>" class="navlnk"><?=gettext("Click here"); ?> </a>
											<?=gettext("for additional PPPoE configuration options. Save first if you made changes."); ?>
											</td>
										<?php else: ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php" class="navlnk"><?=gettext("Click here"); ?> </a>
											<?=gettext("for advanced PPPoE configuration options and MLPPP configuration."); ?>
											</td>
										<?php endif; ?>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="pptp" id="pptp">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPTP/L2TP configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_username" type="text" class="formfld user" id="pptp_username" size="20" value="<?=htmlspecialchars($pconfig['pptp_username']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_password" type="password" class="formfld pwd" id="pptp_password" size="20" value="<?=htmlspecialchars($pconfig['pptp_password']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" width="100" valign="top" class="vncellreq"><?=gettext("Local IP address"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_local" type="text" class="formfld unknown" id="pptp_local" size="20"  value="<?=htmlspecialchars($pconfig['pptp_local'][0]);?>">
											/
											<select name="pptp_subnet" class="formselect" id="pptp_subnet">
												<?php for ($i = 31; $i > 0; $i--): ?>
													<option value="<?=$i;?>" <?php if ($i == $pconfig['pptp_subnet'][0]) echo "selected"; ?>>
														<?=$i;?></option>
												<?php endfor; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" width="100" valign="top" class="vncellreq"><?=gettext("Remote IP address"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_remote" type="text" class="formfld unknown" id="pptp_remote" size="20" value="<?=htmlspecialchars($pconfig['pptp_remote'][0]);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Dial on demand"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_dialondemand" type="checkbox" id="pptp_dialondemand" value="enable" <?php if ($pconfig['pptp_dialondemand']) echo "checked"; ?>>
											<strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br>
											<?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a"); ?> <i><?=gettext("virtual full time"); ?></i> <?=gettext("connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_idletimeout" type="text" class="formfld unknown" id="pptp_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pptp_idletimeout']);?>"> <?=gettext("seconds"); ?><br><?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Advanced"); ?></td>
										<?php if (isset($pconfig['pppid'])): ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php?id=<?=htmlspecialchars($pconfig['pppid']);?>" class="navlnk"><?=gettext("Click here");?></a>
											<?=gettext("for additional PPTP and L2TP configuration options. Save first if you made changes.");?>
											</td>
										<?php else: ?>
											<td width="78%" class="vtable">
											<a href="/interfaces_ppps_edit.php" class="navlnk"><?=gettext("Click here");?></a>
											<?=gettext("for advanced PPTP and L2TP configuration options");?>.
											</td>
										<?php endif; ?>
									</tr>
								</table>
							</td>
						</tr>
						<?php
							/* Wireless interface? */
							if (isset($wancfg['wireless'])):
						?>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Common wireless configuration - Settings apply to all wireless networks on"); ?> <?=$wlanbaseif;?>.</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Persist common settings");?></td>
							<td class="vtable">
								<input name="persistcommonwireless" type="checkbox" value="yes"  class="formfld" id="persistcommonwireless" <?php if ($pconfig['persistcommonwireless']) echo "checked";?>>
								<br/><?=gettext("Enabling this preserves the common wireless configuration through interface deletions and reassignments.");?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Standard"); ?></td>
							<td class="vtable">
							<select name="standard" class="formselect" id="standard">
								<?php
								foreach($wl_modes as $wl_standard => $wl_channels) {
									echo "<option ";
									if ($pconfig['standard'] == "$wl_standard")
										echo "selected ";
									echo "value=\"$wl_standard\">802.$wl_standard</option>\n";
								}
								?>
							</select>
							</td>
						</tr>
						<?php if (isset($wl_modes['11g'])): ?>
						<tr>
							<td valign="top" class="vncellreq">802.11g OFDM <?=gettext("Protection Mode"); ?></td>
							<td class="vtable">
								<select name="protmode" class="formselect" id="protmode">
									<option <?php if ($pconfig['protmode'] == 'off') echo "selected";?> value="off"><?=gettext("Protection mode off"); ?></option>
									<option <?php if ($pconfig['protmode'] == 'cts') echo "selected";?> value="cts"><?=gettext("Protection mode CTS to self"); ?></option>
									<option <?php if ($pconfig['protmode'] == 'rtscts') echo "selected";?> value="rtscts"><?=gettext("Protection mode RTS and CTS"); ?></option>
								</select>
								<br/>
								<?=gettext("For IEEE 802.11g, use the specified technique for protecting OFDM frames in a mixed 11b/11g network."); ?>
								<br/>
							</td>
						</tr>
						<?php else: ?>
						<input name="protmode" type="hidden" id="protmode" value="off">
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Transmit power"); ?></td>
							<td class="vtable">
								<select name="txpower" class="formselect" id="txpower">
									<?
									for($x = 99; $x > 0; $x--) {
										if($pconfig["txpower"] == $x)
											$SELECTED = " SELECTED";
										else
											$SELECTED = "";
										echo "<option {$SELECTED}>{$x}</option>\n";
									}
									?>
								</select><br/>
								<?=gettext("Note: Typically only a few discreet power settings are available and the driver will use the setting closest to the specified value.  Not all adapters support changing the transmit power setting."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Channel"); ?></td>
							<td class="vtable">
								<select name="channel" class="formselect" id="channel">
									<option <?php if ($pconfig['channel'] == 0) echo "selected"; ?> value="0"><?=gettext("Auto"); ?></option>
									<?php
									foreach($wl_modes as $wl_standard => $wl_channels) {
										if($wl_standard == "11g") { $wl_standard = "11b/g"; }
										else if($wl_standard == "11ng") { $wl_standard = "11b/g/n"; }
										else if($wl_standard == "11na") { $wl_standard = "11a/n"; }
										foreach($wl_channels as $wl_channel) {
											echo "<option ";
											if ($pconfig['channel'] == "$wl_channel") {
												echo "selected ";
											}
											echo "value=\"$wl_channel\">$wl_standard - $wl_channel";
											if(isset($wl_chaninfo[$wl_channel]))
												echo " ({$wl_chaninfo[$wl_channel][1]} @ {$wl_chaninfo[$wl_channel][2]} / {$wl_chaninfo[$wl_channel][3]})";
											echo "</option>\n";
										}
									}
									?>
								</select>
								<br/>
								<?=gettext("Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain)"); ?>
								<br/>
								<?=gettext("Note: Not all channels may be supported by your card.  Auto may override the wireless standard selected above."); ?>
							</td>
						</tr>
						<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Antenna settings"); ?></td>
							<td class="vtable">
								<table border="0" cellpadding="0" cellspacing="0">
									<tr>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"])): ?>
										<td>
											<?=gettext("Diversity"); ?><br/>
											<select name="diversity" class="formselect" id="diversity">
												<option <?php if (!isset($pconfig['diversity'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['diversity'] === '0') echo "selected"; ?> value="0"><?=gettext("Off"); ?></option>
												<option <?php if ($pconfig['diversity'] === '1') echo "selected"; ?> value="1"><?=gettext("On"); ?></option>
											</select>
										</td>
										<td>&nbsp;&nbsp</td>
										<?php endif; ?>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"])): ?>
										<td>
											<?=gettext("Transmit antenna"); ?><br/>
											<select name="txantenna" class="formselect" id="txantenna">
												<option <?php if (!isset($pconfig['txantenna'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '0') echo "selected"; ?> value="0"><?=gettext("Auto"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '1') echo "selected"; ?> value="1"><?=gettext("#1"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '2') echo "selected"; ?> value="2"><?=gettext("#2"); ?></option>
											</select>
										</td>
										<td>&nbsp;&nbsp</td>
										<?php endif; ?>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
										<td>
											<?=gettext("Receive antenna"); ?><br/>
											<select name="rxantenna" class="formselect" id="rxantenna">
												<option <?php if (!isset($pconfig['rxantenna'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '0') echo "selected"; ?> value="0"><?=gettext("Auto"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '1') echo "selected"; ?> value="1"><?=gettext("#1"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '2') echo "selected"; ?> value="2"><?=gettext("#2"); ?></option>
											</select>
										</td>
										<?php endif; ?>
									</tr>
								</table>
								<br/>
								<?=gettext("Note: The antenna numbers do not always match up with the labels on the card."); ?>
							</td>
						</tr>
						<?php endif; ?>
						<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.slottime"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.acktimeout"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.ctstimeout"])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Distance setting"); ?></td>
							<td class="vtable">
								<input name="distance" type="text" class="formfld unknown" id="distance" size="5" value="<?=htmlspecialchars($pconfig['distance']);?>">
								<br/>
								<?=gettext("Note: This field can be used to tune ACK/CTS timers to fit the distance between AP and Client"); ?><br/>
								<?=gettext("(measured in Meters and works only for Atheros based cards !)"); ?>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Regulatory settings"); ?></td>
							<td class="vtable">
								<?=gettext("Regulatory domain"); ?><br/>
								<select name="regdomain" class="formselect" id="regdomain">
									<option <?php if (empty($pconfig['regdomain'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
									<?php
									foreach($wl_regdomains as $wl_regdomain_key => $wl_regdomain) {
										echo "<option ";
										if ($pconfig['regdomain'] == $wl_regdomains_attr[$wl_regdomain_key]['ID']) {
											echo "selected ";
										}
										echo "value=\"{$wl_regdomains_attr[$wl_regdomain_key]['ID']}\">{$wl_regdomain['name']}</option>\n";
									}
									?>
								</select>
								<br/>
								<?=gettext("Note: Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work."); ?>
								<br/><br/>
								<?=gettext("Country (listed with country code and regulatory domain)"); ?><br/>
								<select name="regcountry" class="formselect" id="regcountry">
									<option <?php if (empty($pconfig['regcountry'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
									<?php
									foreach($wl_countries as $wl_country_key => $wl_country) {
										echo "<option ";
										if ($pconfig['regcountry'] == $wl_countries_attr[$wl_country_key]['ID']) {
											echo "selected ";
										}
										echo "value=\"{$wl_countries_attr[$wl_country_key]['ID']}\">{$wl_country['name']} -- ({$wl_countries_attr[$wl_country_key]['ID']}, " . strtoupper($wl_countries_attr[$wl_country_key]['rd'][0]['REF']) . ")</option>\n";
									}
									?>
								</select>
								<br/>
								<?=gettext("Note: Any country setting other than \"Default\" will override the regulatory domain setting"); ?>.
								<br/><br/>
								<?=gettext("Location"); ?><br/>
								<select name="reglocation" class="formselect" id="reglocation">
									<option <?php if (empty($pconfig['reglocation'])) echo "selected"; ?> value=""><?=gettext("Default"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'indoor') echo "selected"; ?> value="indoor"><?=gettext("Indoor"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'outdoor') echo "selected"; ?> value="outdoor"><?=gettext("Outdoor"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'anywhere') echo "selected"; ?> value="anywhere"><?=gettext("Anywhere"); ?></option>
								</select>
								<br/><br/>
								<?=gettext("These settings may affect which channels are available and the maximum transmit power allowed on those channels.  Using the correct settings to comply with local regulatory requirements is recommended."); ?>
								<br/>
								<?=gettext("Note: All wireless networks on this interface will be temporarily brought down when changing regulatory settings.  Some of the regulatory domains or country codes may not be allowed by some cards.  These settings may not be able to add additional channels that are not already supported."); ?>
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Network-specific wireless configuration");?></td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Mode"); ?></td>
							<td class="vtable">
								<select name="mode" class="formselect" id="mode">
									<option <?php if ($pconfig['mode'] == 'bss') echo "selected";?> value="bss"><?=gettext("Infrastructure (BSS)"); ?></option>
									<option <?php if ($pconfig['mode'] == 'adhoc') echo "selected";?> value="adhoc"><?=gettext("Ad-hoc (IBSS)"); ?></option>
									<option <?php if ($pconfig['mode'] == 'hostap') echo "selected";?> value="hostap"><?=gettext("Access Point"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("SSID"); ?></td>
							<td class="vtable">
								<input name="ssid" type="text" class="formfld unknown" id="ssid" size="20" value="<?=htmlspecialchars($pconfig['ssid']); ?>">
								<br/>
								<?=gettext("Note: Only required in Access Point mode. If left blank in Ad-hoc or Infrastructure mode, this interface will connect to any available SSID"); ?>
							</td>
						</tr>
						<?php if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Minimum wireless standard"); ?></td>
							<td class="vtable">
								<select name="puremode" class="formselect" id="puremode">
									<option <?php if ($pconfig['puremode'] == 'any') echo "selected";?> value="any"><?=gettext("Any"); ?></option>
									<?php if (isset($wl_modes['11g'])): ?>
									<option <?php if ($pconfig['puremode'] == '11g') echo "selected";?> value="11g"><?=gettext("802.11g"); ?></option>
									<?php endif; ?>
									<option <?php if ($pconfig['puremode'] == '11n') echo "selected";?> value="11n"><?=gettext("802.11n"); ?></option>
								</select>
								<br/>
								<?=gettext("When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)."); ?>
							</td>
						</tr>
						<?php elseif (isset($wl_modes['11g'])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.11g only"); ?></td>
							<td class="vtable">
								<input name="puremode" type="checkbox" value="11g"  class="formfld" id="puremode" <?php if ($pconfig['puremode'] == '11g') echo "checked";?>>
								<br/><?=gettext("When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)."); ?>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Allow intra-BSS communication"); ?></td>
							<td class="vtable">
								<input name="apbridge_enable" type="checkbox" value="yes"  class="formfld" id="apbridge_enable" <?php if ($pconfig['apbridge_enable']) echo "checked";?>>
								<br/>
								<?=gettext("When operating as an access point, enable this if you want to pass packets between wireless clients directly."); ?>
								<br/>
								<?=gettext("Disabling the internal bridging is useful when traffic is to be processed with packet filtering."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable WME"); ?></td>
							<td class="vtable">
								<input name="wme_enable" type="checkbox" class="formfld" id="wme_enable" value="yes" <?php if ($pconfig['wme_enable']) echo "checked";?>>
								<br/><?=gettext("Setting this option will force the card to use WME (wireless QoS)."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable Hide SSID"); ?></td>
							<td class="vtable">
								<input name="hidessid_enable" type="checkbox" class="formfld" id="hidessid_enable" value="yes" <?php if ($pconfig['hidessid_enable']) echo "checked";?>>
								<br/>
								<?=gettext("Setting this option will force the card to NOT broadcast its SSID"); ?>
								<br/>
								<?=gettext("(this might create problems for some clients)."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WEP"); ?></td>
							<td class="vtable">
								<input name="wep_enable" type="checkbox" id="wep_enable" value="yes" <?php if ($pconfig['wep_enable']) echo "checked"; ?>>
								<strong><?=gettext("Enable WEP"); ?></strong>
								<table border="0" cellspacing="0" cellpadding="0">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;</td>
										<td>&nbsp;<?=gettext("TX key"); ?>&nbsp;</td>
									</tr>
									<tr>
										<td><?=gettext("Key 1:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key1" type="text" class="formfld unknown" id="key1" size="30" value="<?=htmlspecialchars($pconfig['key1']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="1" <?php if ($pconfig['txkey'] == 1) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 2:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key2" type="text" class="formfld unknown" id="key2" size="30" value="<?=htmlspecialchars($pconfig['key2']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="2" <?php if ($pconfig['txkey'] == 2) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 3:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key3" type="text" class="formfld unknown" id="key3" size="30" value="<?=htmlspecialchars($pconfig['key3']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="3" <?php if ($pconfig['txkey'] == 3) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 4:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key4" type="text" class="formfld unknown" id="key4" size="30" value="<?=htmlspecialchars($pconfig['key4']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="4" <?php if ($pconfig['txkey'] == 4) echo "checked";?>>
										</td>
									</tr>
								</table>
								<br/>
								<?=gettext("40 (64) bit keys may be entered as 5 ASCII characters or 10 hex digits preceded by '0x'."); ?><br/>
								<?=gettext("104 (128) bit keys may be entered as 13 ASCII characters or 26 hex digits preceded by '0x'."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA"); ?></td>
							<td class="vtable">
								<input name="wpa_enable" type="checkbox" class="formfld" id="wpa_enable" value="yes" <?php if ($pconfig['wpa_enable']) echo "checked"; ?>>
								<strong><?=gettext("Enable WPA"); ?></strong>
								<br/><br/>
								<table border="0" cellspacing="0" cellpadding="0">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;<?=gettext("WPA Pre-Shared Key"); ?>&nbsp;</td>
									</tr>
									<tr>
										<td><?=gettext("PSK:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="passphrase" type="text" class="formfld unknown" id="passphrase" size="66" value="<?=htmlspecialchars($pconfig['passphrase']);?>">
										</td>
									</tr>
								</table>
								<br/><?=gettext("Passphrase must be from 8 to 63 characters."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Mode"); ?></td>
							<td class="vtable">
								<select name="wpa_mode" class="formselect" id="wpa_mode">
									<option <?php if ($pconfig['wpa_mode'] == '1') echo "selected";?> value="1"><?=gettext("WPA"); ?></option>
									<option <?php if ($pconfig['wpa_mode'] == '2') echo "selected";?> value="2"><?=gettext("WPA2"); ?></option>
									<option <?php if ($pconfig['wpa_mode'] == '3') echo "selected";?> value="3"><?=gettext("Both"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Key Management Mode"); ?></td>
							<td class="vtable">
								<select name="wpa_key_mgmt" class="formselect" id="wpa_key_mgmt">
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK') echo "selected";?> value="WPA-PSK"><?=gettext("Pre-Shared Key"); ?></option>
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-EAP') echo "selected";?> value="WPA-EAP"><?=gettext("Extensible Authentication Protocol"); ?></option>
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK WPA-EAP') echo "selected";?> value="WPA-PSK WPA-EAP"><?=gettext("Both"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Authentication"); ?></td>
							<td class="vtable">
								<select name="auth_algs" class="formselect" id="auth_algs">
									<option <?php if ($pconfig['auth_algs'] == '1') echo "selected";?> value="1"><?=gettext("Open System Authentication"); ?></option>
									<option <?php if ($pconfig['auth_algs'] == '2') echo "selected";?> value="2"><?=gettext("Shared Key Authentication"); ?></option>
									<option <?php if ($pconfig['auth_algs'] == '3') echo "selected";?> value="3"><?=gettext("Both"); ?></option>
								</select>
								<br/><?=gettext("Note: Shared Key Authentication requires WEP."); ?></br>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Pairwise"); ?></td>
							<td class="vtable">
								<select name="wpa_pairwise" class="formselect" id="wpa_pairwise">
									<option <?php if ($pconfig['wpa_pairwise'] == 'CCMP TKIP') echo "selected";?> value="CCMP TKIP"><?=gettext("Both"); ?></option>
									<option <?php if ($pconfig['wpa_pairwise'] == 'CCMP') echo "selected";?> value="CCMP"><?=gettext("AES (recommended)"); ?></option>
									<option <?php if ($pconfig['wpa_pairwise'] == 'TKIP') echo "selected";?> value="TKIP"><?=gettext("TKIP"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Key Rotation"); ?></td>
							<td class="vtable">
								<input name="wpa_group_rekey" type="text" class="formfld unknown" id="wpa_group_rekey" size="30" value="<?php echo $pconfig['wpa_group_rekey'] ? $pconfig['wpa_group_rekey'] : "60";?>">
								<br/><?=gettext("Allowed values are 1-9999 but should not be longer than Master Key Regeneration time."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Master Key Regeneration"); ?></td>
							<td class="vtable">
								<input name="wpa_gmk_rekey" type="text" class="formfld" id="wpa_gmk_rekey" size="30" value="<?php echo $pconfig['wpa_gmk_rekey'] ? $pconfig['wpa_gmk_rekey'] : "3600";?>">
								<br/><?=gettext("Allowed values are 1-9999 but should not be shorter than Key Rotation time."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Strict Key Regeneration"); ?></td>
							<td class="vtable">
								<input name="wpa_strict_rekey" type="checkbox" value="yes"  class="formfld" id="wpa_strict_rekey" <?php if ($pconfig['wpa_strict_rekey']) echo "checked"; ?>>
								<br/><?=gettext("Setting this option will force the AP to rekey whenever a client disassociates."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable IEEE802.1X Authentication"); ?></td>
							<td class="vtable">
								<input name="ieee8021x" type="checkbox" value="yes"  class="formfld" id="ieee8021x" <?php if ($pconfig['ieee8021x']) echo "checked";?>>
								<br/><?=gettext("Setting this option will enable 802.1x authentication."); ?>
								<br/><span class="red"><strong><?=gettext("NOTE"); ?>:</strong></span> <?=gettext("this option requires checking the \"Enable WPA box\"."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server IP Address"); ?></td>
							<td class="vtable">
								<input name="auth_server_addr" id="auth_server_addr" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_addr']);?>">
								<br/><?=gettext("Enter the IP address of the 802.1X Authentication Server.  This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server Port"); ?></td>
							<td class="vtable">
								<input name="auth_server_port" id="auth_server_port" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_port']);?>">
								<br/><?=gettext("Leave blank for the default 1812 port."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server Shared Secret"); ?></td>
							<td class="vtable">
								<input name="auth_server_shared_secret" id="auth_server_shared_secret" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_shared_secret']);?>">
								<br/>
							</td>
						</tr>
						<tr>
					<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server IP Address"); ?></td>
							<td class="vtable">
								<input name="auth_server_addr2" id="auth_server_addr2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_addr2']);?>">
								<br/><?=gettext("Enter the IP address of the 802.1X Authentication Server.  This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server Port"); ?></td>
							<td class="vtable">
								<input name="auth_server_port2" id="auth_server_port2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_port2']);?>">
								<br/><?=gettext("Leave blank for the default 1812 port."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server Shared Secret"); ?></td>
							<td class="vtable">
								<input name="auth_server_shared_secret2" id="auth_server_shared_secret2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_shared_secret2']);?>">
								<br/>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">802.1X <?=gettext("Authentication Roaming Preauth"); ?></td>
							<td class="vtable">
								<input name="rsn_preauth" id="rsn_preauth" type="checkbox" class="formfld unknown" size="66" value="yes" <?php if ($pconfig['rsn_preauth']) echo "checked"; ?>>
								<br/>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Private networks"); ?></td>
						</tr>
						<tr>
							<td valign="middle" class="vncell">&nbsp;</td>
							<td class="vtable">
								<a name="rfc1918"></a>
								<input name="blockpriv" type="checkbox" id="blockpriv" value="yes" <?php if ($pconfig['blockpriv']) echo "checked"; ?>>
								<strong><?=gettext("Block private networks"); ?></strong><br>
								<?=gettext("When set, this option blocks traffic from IP addresses that are reserved " .
								"for private  networks as per RFC 1918 (10/8, 172.16/12, 192.168/16) as"); ?>
								<?=gettext("well as loopback addresses (127/8)."); ?>&nbsp;&nbsp; <?=gettext("You should generally " .
								"leave this option turned on, unless your WAN network lies in such " .
								"a private address space, too."); ?>
							</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell">&nbsp;</td>
							<td class="vtable">
								<input name="blockbogons" type="checkbox" id="blockbogons" value="yes" <?php if ($pconfig['blockbogons']) echo "checked"; ?>>
								<strong><?=gettext("Block bogon networks"); ?></strong><br>
								<?=gettext("When set, this option blocks traffic from IP addresses that are reserved " .
								"(but not RFC 1918) or not yet assigned by IANA."); ?>&nbsp;&nbsp;
								<?=gettext("Bogons are prefixes that should never appear in the Internet routing table, " .
								"and obviously should not appear as the source address in any packets you receive."); ?>
								<br/><br/>
								<?=gettext("Note: The update frequency can be changed under System->Advanced Firewall/NAT settings.")?>
							</td>
						</tr>
					</table> <!-- End "allcfg" table -->
					</div> <!-- End "allcfg" div -->

					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top">
								&nbsp;
							</td>
							<td width="78%">
								<br/>
								<input id="save" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">
								<input id="cancel" type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
								<input name="if" type="hidden" id="if" value="<?=$if;?>">
								<?php if ($wancfg['if'] == $a_ppps[$pppid]['if']) : ?>
								<input name="ppp_port" type="hidden" value="<?=htmlspecialchars($pconfig['port']);?>">
								<?php endif; ?>
								<input name="ptpid" type="hidden" value="<?=htmlspecialchars($pconfig['ptpid']);?>">
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<!--
		</div>
		</td></tr>
		</table>
		-->
	</form>
	<script type="text/javascript">
		var gatewayip;
		var name;
		var gatewayipv6;
		var namev6;
		function show_add_gateway() {
			document.getElementById("addgateway").style.display = '';
			document.getElementById("addgwbox").style.display = 'none';
			document.getElementById("gateway").style.display = 'none';
			document.getElementById("save").style.display = 'none';
			document.getElementById("cancel").style.display = 'none';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			jQuery('#notebox').html("");
		}
		function show_add_gateway_v6() {
			document.getElementById("addgatewayv6").style.display = '';
			document.getElementById("addgwboxv6").style.display = 'none';
			document.getElementById("gatewayv6").style.display = 'none';
			document.getElementById("save").style.display = 'none';
			document.getElementById("cancel").style.display = 'none';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			jQuery('#noteboxv6').html("");
		}
		function hide_add_gateway() {
			document.getElementById("addgateway").style.display = 'none';
			document.getElementById("addgwbox").style.display = '';
			document.getElementById("gateway").style.display = '';
			document.getElementById("save").style.display = '';
			document.getElementById("cancel").style.display = '';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
		}
		function hide_add_gateway_v6() {
			document.getElementById("addgatewayv6").style.display = 'none';
			document.getElementById("addgwboxv6").style.display = '';
			document.getElementById("gatewayv6").style.display = '';
			document.getElementById("save").style.display = '';
			document.getElementById("cancel").style.display = '';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
		}
		function hide_add_gatewaysave() {
			document.getElementById("addgateway").style.display = 'none';
			jQuery('#status').html('<img src="/themes/metallic/images/misc/loader.gif"> One moment please...');
			var iface = jQuery('#if').val();
			name = jQuery('#name').val();
			var descr = jQuery('#gatewaydescr').val();
			gatewayip = jQuery('#gatewayip').val();

			var defaultgw = jQuery('#defaultgw').val();
			var url = "system_gateways_edit.php";
			var pars = 'isAjax=true&defaultgw=' + escape(defaultgw) + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip);
			jQuery.ajax(
				url,
				{
					type: 'post',
					data: pars,
					error: report_failure,
					complete: save_callback
				});
		}
		function hide_add_gatewaysave_v6() {
			document.getElementById("addgatewayv6").style.display = 'none';
			jQuery('#statusv6').html('<img src="/themes/metallic/images/misc/loader.gif"> One moment please...');
			var iface = jQuery('#if').val();
			name = jQuery('#namev6').val();
			var descr = jQuery('#gatewaydescrv6').val();
			gatewayip = jQuery('#gatewayipv6').val();
			var defaultgw = jQuery('#defaultgwv6').val();
			var url_v6 = "system_gateways_edit.php";
			var pars_v6 = 'isAjax=true&defaultgw=' + escape(defaultgw) + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip);
			jQuery.ajax(
				url_v6,
				{
					type: 'post',
					data: pars_v6,
					error: report_failure_v6,
					complete: save_callback_v6
				});
		}
		function addOption(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.append(optn);
			selectbox.prop('selectedIndex',selectbox.children().length-1);
			jQuery('#notebox').html("<p/><strong><?=gettext("NOTE:"); ?></strong> <?=gettext("You can manage Gateways"); ?> <a target='_new' href='system_gateways.php'><?=gettext("here"); ?></a>.");
		}
		function addOption_v6(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.append(optn);
			selectbox.prop('selectedIndex',selectbox.children().length-1);
			jQuery('#noteboxv6').html("<p/><strong><?=gettext("NOTE:"); ?></strong> <?=gettext("You can manage Gateways"); ?> <a target='_new' href='system_gateways.php'><?=gettext("here"); ?></a>.");
		}
		function report_failure(request, textStatus, errorThrown) {
			if (textStatus === "error" && request.getResponseHeader("Content-Type") === "text/plain") {
				alert(request.responseText);
			} else {
				alert("Sorry, we could not create your IPv4 gateway at this time.");
			}
			hide_add_gateway();
		}
		function report_failure_v6(request, textStatus, errorThrown) {
			if (textStatus === "error" && request.getResponseHeader("Content-Type") === "text/plain") {
				alert(request.responseText);
			} else {
				alert("Sorry, we could not create your IPv6 gateway at this time.");
			}
			hide_add_gateway_v6();
		}
		function save_callback(transport) {
			var response = transport.responseText;
			if(response) {
				document.getElementById("addgateway").style.display = 'none';
				hide_add_gateway();
				jQuery('#status').html('');
				var gwtext = escape(name) + " - " + gatewayip;
				addOption(jQuery('#gateway'), gwtext, name);
				// Auto submit form?
				//document.iform.submit();
				//jQuery('#status').html('<img src="/themes/metallic/images/misc/loader.gif">');
			} else {
				report_failure();
			}
		}
		function show_advanced_media() {
			document.getElementById("showadvmediabox").innerHTML='';
			aodiv = document.getElementById('showmediaadv');
			aodiv.style.display = "block";
		}
		function save_callback_v6(transport) {
			var response_v6 = transport.responseText;
			if(response_v6) {
				document.getElementById("addgatewayv6").style.display = 'none';
				hide_add_gateway_v6();
				jQuery('#statusv6').html('');
				var gwtext_v6 = escape(name) + " - " + gatewayip;
				addOption_v6(jQuery('#gatewayv6'), gwtext_v6, name);
				// Auto submit form?
				//document.iform.submit();
				//jQuery('#statusv6').html('<img src="/themes/metallic/images/misc/loader.gif">');
			} else {
				report_failure_v6();
			}
		}
		<?php
		echo "show_allcfg(document.iform.enable);";
		echo "updateType('{$pconfig['type']}');\n";
		echo "updateTypeSix('{$pconfig['type6']}');\n";
		?>
	</script>
	<?php include("fend.inc"); ?>
	</body>
</html>
