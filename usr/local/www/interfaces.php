<?php
/* $Id$ */
/*
	interfaces.php
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2006 Daniel S. Haischt.
	Copyright (C) 2008-2010 Ermal Luçi
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

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/interfaces.php');

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr(false, true);

$if = "wan";
if ($_REQUEST['if'])
	$if = $_REQUEST['if'];

if (empty($ifdescrs[$if])) {
	header("Location: interfaces.php");
	exit;
}

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($pconfig))
	$pconfig = array();

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
$old_wancfg['realif'] = get_real_interface($if);
$old_ppps = $a_ppps;
// Populate page descr if it does not exist.
if ($if == "wan" && !$wancfg['descr'])
	$wancfg['descr'] = "WAN";
else if ($if == "lan" && !$wancfg['descr'])
	$wancfg['descr'] = "LAN";

foreach ($a_ppps as $pppid => $ppp) {
	if ($wancfg['if'] == $ppp['if'])
		break;
}

$type_disabled = (substr($wancfg['if'], 0, 3) == 'gre') ? 'disabled="disabled"' : '';

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
$pconfig['dhcprejectfrom'] = $wancfg['dhcprejectfrom'];

$pconfig['adv_dhcp_pt_timeout'] = $wancfg['adv_dhcp_pt_timeout'];
$pconfig['adv_dhcp_pt_retry'] = $wancfg['adv_dhcp_pt_retry'];
$pconfig['adv_dhcp_pt_select_timeout'] = $wancfg['adv_dhcp_pt_select_timeout'];
$pconfig['adv_dhcp_pt_reboot'] = $wancfg['adv_dhcp_pt_reboot'];
$pconfig['adv_dhcp_pt_backoff_cutoff'] = $wancfg['adv_dhcp_pt_backoff_cutoff'];
$pconfig['adv_dhcp_pt_initial_interval'] = $wancfg['adv_dhcp_pt_initial_interval'];

$pconfig['adv_dhcp_pt_values'] = $wancfg['adv_dhcp_pt_values'];

$pconfig['adv_dhcp_send_options'] = $wancfg['adv_dhcp_send_options'];
$pconfig['adv_dhcp_request_options'] = $wancfg['adv_dhcp_request_options'];
$pconfig['adv_dhcp_required_options'] = $wancfg['adv_dhcp_required_options'];
$pconfig['adv_dhcp_option_modifiers'] = $wancfg['adv_dhcp_option_modifiers'];

$pconfig['adv_dhcp_config_advanced'] = $wancfg['adv_dhcp_config_advanced'];
$pconfig['adv_dhcp_config_file_override'] = $wancfg['adv_dhcp_config_file_override'];
$pconfig['adv_dhcp_config_file_override_path'] = $wancfg['adv_dhcp_config_file_override_path'];

$pconfig['adv_dhcp6_interface_statement_send_options'] = $wancfg['adv_dhcp6_interface_statement_send_options'];
$pconfig['adv_dhcp6_interface_statement_request_options'] = $wancfg['adv_dhcp6_interface_statement_request_options'];
$pconfig['adv_dhcp6_interface_statement_information_only_enable'] = $wancfg['adv_dhcp6_interface_statement_information_only_enable'];
$pconfig['adv_dhcp6_interface_statement_script'] = $wancfg['adv_dhcp6_interface_statement_script'];

$pconfig['adv_dhcp6_id_assoc_statement_address_enable'] = $wancfg['adv_dhcp6_id_assoc_statement_address_enable'];
$pconfig['adv_dhcp6_id_assoc_statement_address'] = $wancfg['adv_dhcp6_id_assoc_statement_address'];
$pconfig['adv_dhcp6_id_assoc_statement_address_id'] = $wancfg['adv_dhcp6_id_assoc_statement_address_id'];
$pconfig['adv_dhcp6_id_assoc_statement_address_pltime'] = $wancfg['adv_dhcp6_id_assoc_statement_address_pltime'];
$pconfig['adv_dhcp6_id_assoc_statement_address_vltime'] = $wancfg['adv_dhcp6_id_assoc_statement_address_vltime'];

$pconfig['adv_dhcp6_id_assoc_statement_prefix_enable'] = $wancfg['adv_dhcp6_id_assoc_statement_prefix_enable'];
$pconfig['adv_dhcp6_id_assoc_statement_prefix'] = $wancfg['adv_dhcp6_id_assoc_statement_prefix'];
$pconfig['adv_dhcp6_id_assoc_statement_prefix_id'] = $wancfg['adv_dhcp6_id_assoc_statement_prefix_id'];
$pconfig['adv_dhcp6_id_assoc_statement_prefix_pltime'] = $wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime'];
$pconfig['adv_dhcp6_id_assoc_statement_prefix_vltime'] = $wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'];

$pconfig['adv_dhcp6_prefix_interface_statement_sla_id'] = $wancfg['adv_dhcp6_prefix_interface_statement_sla_id'];
$pconfig['adv_dhcp6_prefix_interface_statement_sla_len'] = $wancfg['adv_dhcp6_prefix_interface_statement_sla_len'];

$pconfig['adv_dhcp6_authentication_statement_authname'] = $wancfg['adv_dhcp6_authentication_statement_authname'];
$pconfig['adv_dhcp6_authentication_statement_protocol'] = $wancfg['adv_dhcp6_authentication_statement_protocol'];
$pconfig['adv_dhcp6_authentication_statement_algorithm'] = $wancfg['adv_dhcp6_authentication_statement_algorithm'];
$pconfig['adv_dhcp6_authentication_statement_rdm'] = $wancfg['adv_dhcp6_authentication_statement_rdm'];

$pconfig['adv_dhcp6_key_info_statement_keyname'] = $wancfg['adv_dhcp6_key_info_statement_keyname'];
$pconfig['adv_dhcp6_key_info_statement_realm'] = $wancfg['adv_dhcp6_key_info_statement_realm'];
$pconfig['adv_dhcp6_key_info_statement_keyid'] = $wancfg['adv_dhcp6_key_info_statement_keyid'];
$pconfig['adv_dhcp6_key_info_statement_secret'] = $wancfg['adv_dhcp6_key_info_statement_secret'];
$pconfig['adv_dhcp6_key_info_statement_expire'] = $wancfg['adv_dhcp6_key_info_statement_expire'];

$pconfig['adv_dhcp6_config_advanced'] = $wancfg['adv_dhcp6_config_advanced'];
$pconfig['adv_dhcp6_config_file_override'] = $wancfg['adv_dhcp6_config_file_override'];
$pconfig['adv_dhcp6_config_file_override_path'] = $wancfg['adv_dhcp6_config_file_override_path'];

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
		if(is_ipaddrv4($wancfg['ipaddr'])) {
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
		if(!isset($wancfg['dhcp6-ia-pd-len']))
			$wancfg['dhcp6-ia-pd-len'] = "none";
		$pconfig['dhcp6-ia-pd-len'] = $wancfg['dhcp6-ia-pd-len'];
		$pconfig['dhcp6-ia-pd-send-hint'] = isset($wancfg['dhcp6-ia-pd-send-hint']);
		$pconfig['type6'] = "dhcp6";
		$pconfig['dhcp6prefixonly'] = isset($wancfg['dhcp6prefixonly']);
		$pconfig['dhcp6usev4iface'] = isset($wancfg['dhcp6usev4iface']);
		break;
	case "6to4":
		$pconfig['type6'] = "6to4";
		break;
	case "track6":
		$pconfig['type6'] = "track6";
		$pconfig['track6-interface'] = $wancfg['track6-interface'];
		if ($wancfg['track6-prefix-id'] == "")
			$pconfig['track6-prefix-id'] = 0;
		else
			$pconfig['track6-prefix-id'] = $wancfg['track6-prefix-id'];
		$pconfig['track6-prefix-id--hex'] = sprintf("%x", $pconfig['track6-prefix-id']);
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
		if(is_ipaddrv6($wancfg['ipaddrv6'])) {
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
			foreach ($toapplylist as $ifapply => $ifcfgo) {
				if (isset($config['interfaces'][$ifapply]['enable'])) {
					interface_bring_down($ifapply, false, $ifcfgo);
					interface_configure($ifapply, true);
				} else
					interface_bring_down($ifapply, true, $ifcfgo);
			}
		}
		/* restart snmp so that it binds to correct address */
		services_snmpd_configure();

		/* sync filter configuration */
		setup_gateways_monitor();

		clear_subsystem_dirty('interfaces');

		filter_configure();

		enable_rrd_graphing();

		if (is_subsystem_dirty('staticroutes') && (system_routing_configure() == 0))
			clear_subsystem_dirty('staticroutes');
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
	$toapplylist[$if]['ifcfg'] = $wancfg;
	$toapplylist[$if]['ppps'] = $a_ppps;
	/* we need to be able remove IP aliases for IPv6 */
	file_put_contents("{$g['tmp_path']}/.interfaces.apply", serialize($toapplylist));
	header("Location: interfaces.php?if={$if}");
	exit;
} else if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (isset($_POST['track6-interface'])) {
		$ipv6_delegation_length = calculate_ipv6_delegation_length($_POST['track6-interface']);
		$ipv6_num_prefix_ids = pow(2, $ipv6_delegation_length);
	}

	if (is_numeric("0x" . $_POST['track6-prefix-id--hex']))
		$pconfig['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
	else
		$pconfig['track6-prefix-id'] = 0;
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
	if (isset($config['dhcpdv6']) && isset($config['dhcpdv6'][$if]['enable']) && (! preg_match("/^staticv6/", $_POST['type6'])))
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
				if ($if != $ifent && ($config[interfaces][$ifent]['ipaddrv6'] == $_POST['type6'])) {
					if ($config[interfaces][$ifent]['prefix-6rd'] == $_POST['prefix-6rd']) {
						$input_errors[] = gettext("You can only have one interface configured in 6rd with same prefix.");
						break;
					}
				}
			}
			if (in_array($wancfg['ipaddrv6'], array()))
				$input_errors[] = sprintf(gettext("You have to reassign the interface to be able to configure as %s."),$_POST['type6']);
			break;
		case "6to4":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if ($if != $ifent && ($config[interfaces][$ifent]['ipaddrv6'] == $_POST['type6'])) {
					$input_errors[] = sprintf(gettext("You can only have one interface configured as 6to4."),$_POST['type6']);
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
	$staticroutes = get_staticroutes(true);
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));
	if ($_POST['ipaddr']) {
		if (!is_ipaddrv4($_POST['ipaddr']))
			$input_errors[] = gettext("A valid IPv4 address must be specified.");
		else {
			if (is_ipaddr_configured($_POST['ipaddr'], $if, true))
				$input_errors[] = gettext("This IPv4 address is being used by another interface or VIP.");

			/* Do not accept network or broadcast address, except if subnet is 31 or 32 */
			if ($_POST['subnet'] < 31) {
				if ($_POST['ipaddr'] == gen_subnet($_POST['ipaddr'], $_POST['subnet']))
					$input_errors[] = gettext("This IPv4 address is the network address and cannot be used");
				else if ($_POST['ipaddr'] == gen_subnet_max($_POST['ipaddr'], $_POST['subnet']))
					$input_errors[] = gettext("This IPv4 address is the broadcast address and cannot be used");
			}

			foreach ($staticroutes as $route_subnet) {
				list($network, $subnet) = explode("/", $route_subnet);
				if ($_POST['subnet'] == $subnet && $network == gen_subnet($_POST['ipaddr'], $_POST['subnet'])) {
					$input_errors[] = gettext("This IPv4 address conflicts with a Static Route.");
					break;
				}
				unset($network, $subnet);
			}
		}
	}
	if ($_POST['ipaddrv6']) {
		if (!is_ipaddrv6($_POST['ipaddrv6']))
			$input_errors[] = gettext("A valid IPv6 address must be specified.");
		else {
			if (is_ipaddr_configured($_POST['ipaddrv6'], $if, true))
				$input_errors[] = gettext("This IPv6 address is being used by another interface or VIP.");

			foreach ($staticroutes as $route_subnet) {
				list($network, $subnet) = explode("/", $route_subnet);
				if ($_POST['subnetv6'] == $subnet && $network == gen_subnetv6($_POST['ipaddrv6'], $_POST['subnetv6'])) {
					$input_errors[] = gettext("This IPv6 address conflicts with a Static Route.");
					break;
				}
				unset($network, $subnet);
			}
		}
	}
	if (($_POST['subnet'] && !is_numeric($_POST['subnet'])))
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	if (($_POST['subnetv6'] && !is_numeric($_POST['subnetv6'])))
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	if (($_POST['alias-address'] && !is_ipaddrv4($_POST['alias-address'])))
		$input_errors[] = gettext("A valid alias IP address must be specified.");
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet'])))
		$input_errors[] = gettext("A valid alias subnet bit count must be specified.");
	if ($_POST['dhcprejectfrom'] && !is_ipaddrv4($_POST['dhcprejectfrom']))
		$input_errors[] = gettext("A valid alias IP address must be specified to reject DHCP Leases from.");
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
	if (($_POST['pptp_local'] && !is_ipaddrv4($_POST['pptp_local'])))
		$input_errors[] = gettext("A valid PPTP local IP address must be specified.");
	if (($_POST['pptp_subnet'] && !is_numeric($_POST['pptp_subnet'])))
		$input_errors[] = gettext("A valid PPTP subnet bit count must be specified.");
	if (($_POST['pptp_remote'] && !is_ipaddrv4($_POST['pptp_remote']) && !is_hostname($_POST['gateway'][$iface])))
		$input_errors[] = gettext("A valid PPTP remote IP address must be specified.");
	if (($_POST['pptp_idletimeout'] != "") && !is_numericint($_POST['pptp_idletimeout']))
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	if (($_POST['spoofmac'] && !is_macaddr($_POST['spoofmac'])))
		$input_errors[] = gettext("A valid MAC address must be specified.");
	if ($_POST['mtu']) {
		if ($_POST['mtu'] < 576 || $_POST['mtu'] > 9000)
			$input_errors[] = gettext("The MTU must be greater than 576 bytes and less than 9000.");

		if (stristr($wancfg['if'], "_vlan")) {
			$realhwif_array = get_parent_interface($wancfg['if']);
			// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
			$parent_realhwif = $realhwif_array[0];
			$parent_if = convert_real_interface_to_friendly_interface_name($parent_realhwif);
			if (!empty($parent_if) && !empty($config['interfaces'][$parent_if]['mtu'])) {
				if ($_POST['mtu'] > intval($config['interfaces'][$parent_if]['mtu']))
					$input_errors[] = gettext("MTU of a vlan should not be bigger than parent interface.");
			}
		} else {
			foreach ($config['interfaces'] as $idx => $ifdata) {
				if (($idx == $if) || !preg_match('/_vlan[0-9]/', $ifdata['if']))
					continue;

				$realhwif_array = get_parent_interface($ifdata['if']);
				// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
				$parent_realhwif = $realhwif_array[0];

				if ($parent_realhwif != $wancfg['if'])
					continue;

				if (isset($ifdata['mtu']) && $ifdata['mtu'] > $_POST['mtu'])
					$input_errors[] = sprintf(gettext("Interface %s (VLAN) has MTU set to a bigger value"), $ifdata['descr']);
			}
		}
	}
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
			if ($passlen < 8 || $passlen > 63)
				$input_errors[] = gettext("The length of the passphrase should be between 8 and 63 characters.");
		}
	}
	if (!$input_errors) {
		if ($wancfg['ipaddr'] != $_POST['type']) {
			if (in_array($wancfg['ipaddr'], array("ppp", "pppoe", "pptp", "l2tp"))) {
				$wancfg['if'] = $a_ppps[$pppid]['ports'];
				unset($a_ppps[$pppid]);
			} else if ($wancfg['ipaddr'] == "dhcp") {
				kill_dhclient_process($wancfg['if']);
			}
			if ($wancfg['ipaddrv6'] == "dhcp6") {
				$pid = find_dhcp6c_process($wancfg['if']);
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
		unset($wancfg['dhcprejectfrom']);
		unset($wancfg['dhcp6-duid']);
		unset($wancfg['dhcp6-ia-pd-len']);
		unset($wancfg['dhcp6-ia-pd-send-hint']);
		unset($wancfg['dhcp6prefixonly']);
		unset($wancfg['dhcp6usev4iface']);
		unset($wancfg['track6-interface']);
		unset($wancfg['track6-prefix-id']);
		unset($wancfg['prefix-6rd']);
		unset($wancfg['prefix-6rd-v4plen']);
		unset($wancfg['gateway-6rd']);

		unset($wancfg['adv_dhcp_pt_timeout']);
		unset($wancfg['adv_dhcp_pt_retry']);
		unset($wancfg['adv_dhcp_pt_select_timeout']);
		unset($wancfg['adv_dhcp_pt_reboot']);
		unset($wancfg['adv_dhcp_pt_backoff_cutoff']);
		unset($wancfg['adv_dhcp_pt_initial_interval']);

		unset($wancfg['adv_dhcp_pt_values']);

		unset($wancfg['adv_dhcp_send_options']);
		unset($wancfg['adv_dhcp_request_options']);
		unset($wancfg['adv_dhcp_required_options']);
		unset($wancfg['adv_dhcp_option_modifiers']);

		unset($wancfg['adv_dhcp_config_advanced']);
		unset($wancfg['adv_dhcp_config_file_override']);
		unset($wancfg['adv_dhcp_config_file_override_path']);

		unset($wancfg['adv_dhcp6_interface_statement_send_options']);
		unset($wancfg['adv_dhcp6_interface_statement_request_options']);
		unset($wancfg['adv_dhcp6_interface_statement_information_only_enable']);
		unset($wancfg['adv_dhcp6_interface_statement_script']);

		unset($wancfg['adv_dhcp6_id_assoc_statement_address_enable']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_address']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_address_id']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_address_pltime']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_address_vltime']);

		unset($wancfg['adv_dhcp6_id_assoc_statement_prefix_enable']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_prefix']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_prefix_id']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime']);
		unset($wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime']);

		unset($wancfg['adv_dhcp6_prefix_interface_statement_sla_id']);
		unset($wancfg['adv_dhcp6_prefix_interface_statement_sla_len']);

		unset($wancfg['adv_dhcp6_authentication_statement_authname']);
		unset($wancfg['adv_dhcp6_authentication_statement_protocol']);
		unset($wancfg['adv_dhcp6_authentication_statement_algorithm']);
		unset($wancfg['adv_dhcp6_authentication_statement_rdm']);

		unset($wancfg['adv_dhcp6_key_info_statement_keyname']);
		unset($wancfg['adv_dhcp6_key_info_statement_realm']);
		unset($wancfg['adv_dhcp6_key_info_statement_keyid']);
		unset($wancfg['adv_dhcp6_key_info_statement_secret']);
		unset($wancfg['adv_dhcp6_key_info_statement_expire']);

		unset($wancfg['adv_dhcp6_config_advanced']);
		unset($wancfg['adv_dhcp6_config_file_override']);
		unset($wancfg['adv_dhcp6_config_file_override_path']);

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
				$wancfg['dhcprejectfrom'] = $_POST['dhcprejectfrom'];

				$wancfg['adv_dhcp_pt_timeout'] = $_POST['adv_dhcp_pt_timeout'];
				$wancfg['adv_dhcp_pt_retry'] = $_POST['adv_dhcp_pt_retry'];
				$wancfg['adv_dhcp_pt_select_timeout'] = $_POST['adv_dhcp_pt_select_timeout'];
				$wancfg['adv_dhcp_pt_reboot'] = $_POST['adv_dhcp_pt_reboot'];
				$wancfg['adv_dhcp_pt_backoff_cutoff'] = $_POST['adv_dhcp_pt_backoff_cutoff'];
				$wancfg['adv_dhcp_pt_initial_interval'] = $_POST['adv_dhcp_pt_initial_interval'];

				$wancfg['adv_dhcp_pt_values'] = $_POST['adv_dhcp_pt_values'];

				$wancfg['adv_dhcp_send_options'] = $_POST['adv_dhcp_send_options'];
				$wancfg['adv_dhcp_request_options'] = $_POST['adv_dhcp_request_options'];
				$wancfg['adv_dhcp_required_options'] = $_POST['adv_dhcp_required_options'];
				$wancfg['adv_dhcp_option_modifiers'] = $_POST['adv_dhcp_option_modifiers'];

				$wancfg['adv_dhcp_config_advanced'] = $_POST['adv_dhcp_config_advanced'];
				$wancfg['adv_dhcp_config_file_override'] = $_POST['adv_dhcp_config_file_override'];
				$wancfg['adv_dhcp_config_file_override_path'] = $_POST['adv_dhcp_config_file_override_path'];

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
				if (!empty($_POST['pppoe_idletimeout']))
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
				if (!empty($_POST['pptp_idletimeout']))
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
				if($_POST['dhcp6-ia-pd-send-hint'] == "yes")
					$wancfg['dhcp6-ia-pd-send-hint'] = true;
				if($_POST['dhcp6prefixonly'] == "yes")
					$wancfg['dhcp6prefixonly'] = true;
				if($_POST['dhcp6usev4iface'] == "yes")
					$wancfg['dhcp6usev4iface'] = true;

				$wancfg['adv_dhcp6_interface_statement_send_options'] = $_POST['adv_dhcp6_interface_statement_send_options'];
				$wancfg['adv_dhcp6_interface_statement_request_options'] = $_POST['adv_dhcp6_interface_statement_request_options'];
				$wancfg['adv_dhcp6_interface_statement_information_only_enable'] = $_POST['adv_dhcp6_interface_statement_information_only_enable'];
				$wancfg['adv_dhcp6_interface_statement_script'] = $_POST['adv_dhcp6_interface_statement_script'];

				$wancfg['adv_dhcp6_id_assoc_statement_address_enable'] = $_POST['adv_dhcp6_id_assoc_statement_address_enable'];
				$wancfg['adv_dhcp6_id_assoc_statement_address'] = $_POST['adv_dhcp6_id_assoc_statement_address'];
				$wancfg['adv_dhcp6_id_assoc_statement_address_id'] = $_POST['adv_dhcp6_id_assoc_statement_address_id'];
				$wancfg['adv_dhcp6_id_assoc_statement_address_pltime'] = $_POST['adv_dhcp6_id_assoc_statement_address_pltime'];
				$wancfg['adv_dhcp6_id_assoc_statement_address_vltime'] = $_POST['adv_dhcp6_id_assoc_statement_address_vltime'];

				$wancfg['adv_dhcp6_id_assoc_statement_prefix_enable'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_enable'];
				$wancfg['adv_dhcp6_id_assoc_statement_prefix'] = $_POST['adv_dhcp6_id_assoc_statement_prefix'];
				$wancfg['adv_dhcp6_id_assoc_statement_prefix_id'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_id'];
				$wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_pltime'];
				$wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_vltime'];

				$wancfg['adv_dhcp6_prefix_interface_statement_sla_id'] = $_POST['adv_dhcp6_prefix_interface_statement_sla_id'];
				$wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] = $_POST['adv_dhcp6_prefix_interface_statement_sla_len'];

				$wancfg['adv_dhcp6_authentication_statement_authname'] = $_POST['adv_dhcp6_authentication_statement_authname'];
				$wancfg['adv_dhcp6_authentication_statement_protocol'] = $_POST['adv_dhcp6_authentication_statement_protocol'];
				$wancfg['adv_dhcp6_authentication_statement_algorithm'] = $_POST['adv_dhcp6_authentication_statement_algorithm'];
				$wancfg['adv_dhcp6_authentication_statement_rdm'] = $_POST['adv_dhcp6_authentication_statement_rdm'];

				$wancfg['adv_dhcp6_key_info_statement_keyname'] = $_POST['adv_dhcp6_key_info_statement_keyname'];
				$wancfg['adv_dhcp6_key_info_statement_realm'] = $_POST['adv_dhcp6_key_info_statement_realm'];
				$wancfg['adv_dhcp6_key_info_statement_keyid'] = $_POST['adv_dhcp6_key_info_statement_keyid'];
				$wancfg['adv_dhcp6_key_info_statement_secret'] = $_POST['adv_dhcp6_key_info_statement_secret'];
				$wancfg['adv_dhcp6_key_info_statement_expire'] = $_POST['adv_dhcp6_key_info_statement_expire'];

				$wancfg['adv_dhcp6_config_advanced'] = $_POST['adv_dhcp6_config_advanced'];
				$wancfg['adv_dhcp6_config_file_override'] = $_POST['adv_dhcp6_config_file_override'];
				$wancfg['adv_dhcp6_config_file_override_path'] = $_POST['adv_dhcp6_config_file_override_path'];

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
				if ($_POST['track6-prefix-id--hex'] === "")
					$wancfg['track6-prefix-id'] = 0;
				else if (is_numeric("0x" . $_POST['track6-prefix-id--hex']))
					$wancfg['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
				else
					$wancfg['track6-prefix-id'] = 0;
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
		$toapplylist[$if]['ifcfg'] = $old_wancfg;
		$toapplylist[$if]['ppps'] = $old_ppps;
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
			mwexec("/sbin/ifconfig " . escapeshellarg($wlanif) . "_ destroy");
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

<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css"/>

<script type="text/javascript">
//<![CDATA[
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
		if (!isNaN(t[0])) t = '_' + t;
		switch(t) {
			case "none": {
				jQuery('#staticv6, #dhcp6, #_6rd, #_6to4, #track6, #slaac').hide();
				break;
			}
			case "staticv6": {
				jQuery('#none, #dhcp6, #_6rd, #_6to4, #track6, #slaac').hide();
				break;
			}
			case "slaac": {
				jQuery('#none, #staticv6, #_6rd, #_6to4, #track6, #dhcp6').hide();
				break;
			}
			case "dhcp6": {
				jQuery('#none, #staticv6, #_6rd, #_6to4, #track6, #slaac').hide();
				break;
			}
			case "_6rd": {
				jQuery('#none, #dhcp6, #staticv6, #_6to4, #track6, #slaac').hide();
				break;
			}
			case "_6to4": {
				jQuery('#none, #dhcp6, #staticv6, #_6rd, #track6, #slaac').hide();
				break;
			}
			case "track6": {
				jQuery('#none, #dhcp6, #staticv6, #_6rd, #_6to4, #slaac').hide();
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
		jQuery('#provider_list').children().remove();
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
		jQuery('#provider_list').children().remove();
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
					jQuery('#provider_list').append(option);
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
			data: {country : jQuery('#country').val(), provider : jQuery('#provider_list').val()},
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
			data: {country : jQuery('#country').val(), provider : jQuery('#provider_list').val(), plan : jQuery('#providerplan').val()},
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

//]]>
</script>
</head>
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<form action="interfaces.php" method="post" name="iform" id="iform">
		<?php if ($input_errors) print_input_errors($input_errors); ?>
		<?php if (is_subsystem_dirty('interfaces')): ?><p>
		<?php print_info_box_np(sprintf(gettext("The %s configuration has been changed."),$wancfg['descr'])."</p><p>".gettext("You must apply the changes in order for them to take effect.")."</p><p>".gettext("Don't forget to adjust the DHCP Server range if needed after applying."));?></p><br />
		<?php endif; ?>
		<?php if ($savemsg) print_info_box($savemsg); ?>
		<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="interfaces">
			<tr>
				<td id="mainarea">
					<div class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="tabs">
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("General configuration"); ?></td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Enable"); ?></td>
							<td width="78%" class="vtable">
								<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable'] == true) echo "checked=\"checked\""; ?> onclick="show_allcfg(this);" />
							<strong><?=gettext("Enable Interface"); ?></strong>
							</td>
						</tr>
					</table>
					<div style="display:none;" id="allcfg">
					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="allcfg">
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext("Description"); ?></td>
							<td width="78%" class="vtable">
								<input name="descr" type="text" class="formfld unknown" id="descr" size="30" value="<?=htmlspecialchars($pconfig['descr']);?>" />
								<br /><span class="vexpl"><?= gettext("Enter a description (name) for the interface here."); ?></span>
							</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell"><strong><?=gettext("IPv4 Configuration Type"); ?></strong></td>
							<td class="vtable">
							<select name="type" onchange="updateType(this.value);" <?php echo $type_disabled; ?> class="formselect" id="type">
								<?php
									foreach ($types4 as $key => $opt) {
										echo "<option onclick=\"updateType('{$key}');\"";
										if ($key == $pconfig['type'])
											echo " selected=\"selected\"";
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
							<select name="type6" onchange="updateTypeSix(this.value);" <?php echo $type_disabled; ?> class="formselect" id="type6">
								<?php
									foreach ($types6 as $key => $opt) {
										echo "<option onclick=\"updateTypeSix('{$key}');\"";
										if ($key == $pconfig['type6'])
											echo " selected=\"selected\"";
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
								<input name="spoofmac" type="text" class="formfld unknown" id="spoofmac" size="30" value="<?=htmlspecialchars($pconfig['spoofmac']);?>" />
								<?php
									$ip = getenv('REMOTE_ADDR');
									$mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
									$mac = str_replace("\n","",$mac);
									if($mac):
								?>
									<a onclick="document.forms[0].spoofmac.value='<?=$mac?>';" href="#"><?=gettext("Insert my local MAC address"); ?></a>
								<?php endif; ?>
								<br />
								<?=gettext("This field can be used to modify (\"spoof\") the MAC " .
								"address of this interface"); ?><br />
								<?=gettext("(may be required with some cable connections)"); ?><br />
								<?=gettext("Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx " .
								"or leave blank"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("MTU"); ?></td>
							<td class="vtable">
								<input name="mtu" type="text" class="formfld unknown" id="mtu" size="8" value="<?=htmlspecialchars($pconfig['mtu']);?>" />
								<br />
								<?php
									print gettext("If you leave this field blank, the adapter's default MTU will " .
									"be used. This is typically 1500 bytes but can vary in some circumstances.");
								?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("MSS"); ?></td>
							<td class="vtable">
								<input name="mss" type="text" class="formfld unknown" id="mss" size="8" value="<?=htmlspecialchars($pconfig['mss']);?>" />
								<br />
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
								echo '<input type="button" onclick="show_advanced_media()" value="' . gettext("Advanced") . '" /> - ' . gettext("Show advanced option");
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
										print "<option value=\"$mediaopt\" selected=\"selected\">" . gettext("$mediaopt") . "</option>";
									}
								}
								echo '</select><br />';
								echo gettext("Here you can explicitly set speed and duplex mode for this interface. WARNING: You MUST leave this set to autoselect (automatically negotiate speed) unless the port this interface connects to has its speed and duplex forced.");
						echo '</div>';
							echo '</td>';
						echo '</tr>';
						}
						?>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr style="display:none;" id="none"><td style="display:none;"></td></tr>
						<tr style="display:none;" id="staticv4">
							<td colspan="2" style="padding:0px;">
								<a name="gatewaysection"></a>
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="staticv4">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Static IPv4 configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IPv4 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="ipaddr" type="text" class="formfld unknown" id="ipaddr" size="20" value="<?=htmlspecialchars($pconfig['ipaddr']);?>" />
											/
											<select name="subnet" class="formselect" id="subnet">
												<?php
												for ($i = 32; $i > 0; $i--) {
													if($i <> 31) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['subnet']) echo "selected=\"selected\"";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("IPv4 Upstream Gateway"); ?></td>
										<td width="78%" class="vtable">
											<select name="gateway" class="formselect" id="gateway">
												<option value="none" selected="selected"><?=gettext("None"); ?></option>
													<?php
													if(count($a_gateways) > 0) {
														foreach ($a_gateways as $gateway) {
															if(($gateway['interface'] == $if)  && (is_ipaddrv4($gateway['gateway']))) {
													?>
															<option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gateway']) echo "selected=\"selected\""; ?>>
																<?=htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']);?>
															</option>
													<?php
															}
														}
													}
													?>
											</select>
											- or  <strong><a onclick="show_add_gateway();" href="#gatewaysection"><?=gettext("add a new one."); ?></a></strong>
											<br />
											<div id='addgwbox'>
												<?=gettext("If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the link above."); ?><br />
												<?=gettext("On local LANs the upstream gateway should be \"none\"."); ?>
											</div>
											<div id='notebox'>
											</div>
											<div id="status">
											</div>
											<div style="display:none" id="addgateway">
												<p>&nbsp;</p>
												<table border="1" class="addgatewaybox" summary="addgateway">
													<tr>
														<td>
															<table class="addgatewaybox" cellpadding="1" cellspacing="1" summary="addgateway">
																<tr><td>&nbsp;</td></tr>
																<tr>
																	<td colspan="2"><center><b><font color="white"><?=gettext("Add new gateway:"); ?></font></b></center></td>
																</tr>
																<tr><td>&nbsp;</td></tr>
																<?php
																if($if == "wan" || $if == "WAN")
																	$checked = " checked=\"checked\"";
																?>
																<tr>
																	<td width="45%" align="right"><font color="white"><?=gettext("Default  gateway:"); ?></font></td><td><input type="checkbox" id="defaultgw" name="defaultgw"<?=$checked?> /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway Name:"); ?></font></td><td><input id="name" name="name" value="<?=$wancfg['descr'] . "GW"?>" /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway IPv4:"); ?></font></td><td><input id="gatewayip" name="gatewayip" /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Description:"); ?></font></td><td><input id="gatewaydescr" name="gatewaydescr" /></td>
																</tr>
																<tr><td>&nbsp;</td></tr>
																<tr>
																	<td colspan="2">
																		<center>
																			<div id='savebuttondiv'>
																				<input id="gwsave" type="button" value="<?=gettext("Save Gateway"); ?>" onclick='hide_add_gatewaysave();' />
																				<input id="gwcancel" type="button" value="<?=gettext("Cancel"); ?>" onclick='hide_add_gateway();' />
																			</div>
																		</center>
																	</td>
																</tr>
																<tr><td>&nbsp;</td></tr>
															</table>
														</td>
													</tr>
												</table>
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="staticv6">
							<td colspan="2" style="padding:0px;">
								<a name="gatewayv6section"></a>
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="staticv6">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Static IPv6 configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IPv6 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="ipaddrv6" type="text" class="formfld unknown" id="ipaddrv6" size="28" value="<?=htmlspecialchars($pconfig['ipaddrv6']);?>" />
											/
											<select name="subnetv6" class="formselect" id="subnetv6">
												<?php
												for ($i = 128; $i > 0; $i--) {
													if($i <> 127) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['subnetv6']) echo "selected=\"selected\"";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Upstream Gateway"); ?></td>
										<td width="78%" class="vtable">
											<select name="gatewayv6" class="formselect" id="gatewayv6">
												<option value="none" selected="selected"><?=gettext("None"); ?></option>
													<?php
													if(count($a_gateways) > 0) {
														foreach ($a_gateways as $gateway) {
															if(($gateway['interface'] == $if) && (is_ipaddrv6($gateway['gateway']))) {
													?>
															<option value="<?=$gateway['name'];?>" <?php if ($gateway['name'] == $pconfig['gatewayv6']) echo "selected=\"selected\""; ?>>
																<?=htmlspecialchars($gateway['name']) . " - " . htmlspecialchars($gateway['gateway']);?>
															</option>
													<?php
															}
														}
													}
													?>
											</select>
											- or <strong><a onclick="show_add_gateway_v6();" href="#gatewayv6section"><?=gettext("add a new one."); ?></a></strong>
											<br />
											<div id='addgwboxv6'>
												<?=gettext("If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the link above."); ?><br />
												<?=gettext("On local LANs the upstream gateway should be \"none\"."); ?>
											</div>
											<div id='noteboxv6'>
											</div>
											<div id="statusv6">
											</div>
											<div style="display:none" id="addgatewayv6">
												<p>&nbsp;</p>
												<table border="1" class="addgatewaybox" summary="addgatewayv6">
													<tr>
														<td>
															<table class="addgatewaybox" cellpadding="1" cellspacing="1" summary="addgatewayv6">
																<tr><td>&nbsp;</td></tr>
																<tr>
																	<td colspan="2"><center><b><font color="white"><?=gettext("Add new v6 gateway:"); ?></font></b></center></td>
																</tr>
																<tr><td>&nbsp;</td></tr>
																<?php
																if($if == "wan" || $if == "WAN")
																	$checked = " checked=\"checked\"";
																?>
																<tr>
																	<td width="45%" align="right"><font color="white"><?=gettext("Default v6 gateway:"); ?></font></td><td><input type="checkbox" id="defaultgwv6" name="defaultgwv6"<?=$checked?> /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway Name IPv6:"); ?></font></td><td><input id="namev6" name="namev6" value="<?=$wancfg['descr'] . "GWv6"?>" /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Gateway IPv6:"); ?></font></td><td><input id="gatewayipv6" name="gatewayipv6" /></td>
																</tr>
																<tr>
																	<td align="right"><font color="white"><?=gettext("Description:"); ?></font></td><td><input id="gatewaydescrv6" name="gatewaydescrv6" /></td>
																</tr>
																<tr><td>&nbsp;</td></tr>
																<tr>
																	<td colspan="2">
																		<center>
																			<div id='savebuttondivv6'>
																				<input id="gwsavev6" type="button" value="<?=gettext("Save Gateway"); ?>" onclick='hide_add_gatewaysave_v6();' />
																				<input id="gwcancelv6" type="button" value="<?=gettext("Cancel"); ?>" onclick='hide_add_gateway_v6();' />
																			</div>
																		</center>
																	</td>
																</tr>
																<tr><td>&nbsp;</td></tr>
															</table>
														</td>
													</tr>
												</table>
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="dhcp">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="dhcp">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("DHCP client configuration &nbsp; &nbsp; " .
										' <input name="adv_dhcp_config_advanced" type="checkbox" id="adv_dhcp_config_advanced" value="" onclick="show_adv_dhcp_config(this)" /> ' .
										" Advanced &nbsp; &nbsp; " .
										' <input name="adv_dhcp_config_file_override" type="checkbox" id="adv_dhcp_config_file_override" value="" onclick="show_adv_dhcp_config(this)" /> ' .
										" Config File Override &nbsp; &nbsp; "); ?>
										</td>
									</tr>
									<!-- Uncomment to expose DHCP+ in GUI
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Enable DHCP+"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp_plus" type="checkbox" value="yes" <?php if ($pconfig['dhcp_plus'] == true) echo "checked=\"checked\""; ?> />
										<strong><?=gettext("Enable DHCP+L2TP or DHCP+PPTP."); ?></strong>
										<br />
										<?=gettext("Status changes on this interface will trigger reconfiguration (if necessary) of the associated PPTP/L2TP link."); ?>
										</td>
									</tr>
									-->
									<tr style='display:none' id="show_basic_dhcphostname">
										<td width="22%" valign="top" class="vncell"><?=gettext("Hostname"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcphostname" type="text" class="formfld unknown" id="dhcphostname" size="40" value="<?=htmlspecialchars($pconfig['dhcphostname']);?>" />
											<br />
											<?=gettext("The value in this field is sent as the DHCP client identifier " .
											"and hostname when requesting a DHCP lease. Some ISPs may require " .
											"this (for client identification)."); ?>
										</td>
									</tr>
									<tr style='display:none' id="show_basic_dhcpalias-address">
										<td width="22%" valign="top" class="vncell"><?=gettext("Alias IPv4 address"); ?></td>
										<td width="78%" class="vtable">
											<input name="alias-address" type="text" class="formfld unknown" id="alias-address" size="20" value="<?=htmlspecialchars($pconfig['alias-address']);?>" />
											<select name="alias-subnet" class="formselect" id="alias-subnet">
												<?php
												for ($i = 32; $i > 0; $i--) {
													if($i <> 31) {
														echo "<option value=\"{$i}\" ";
														if ($i == $pconfig['alias-subnet']) echo "selected=\"selected\"";
														echo ">" . $i . "</option>";
													}
												}
												?>
											</select>
											<?=gettext("The value in this field is used as a fixed alias IPv4 address by the " .
											"DHCP client."); ?>
										</td>
									</tr>
									<tr style='display:none' id="show_basic_dhcprejectlease">
										<td width="22%" valign="top" class="vncell"><?=gettext("Reject Leases From"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcprejectfrom" type="text" class="formfld unknown" id="dhcprejectfrom" size="20" value="<?=htmlspecialchars($pconfig['dhcprejectfrom']);?>" />
											<br />
											<?=gettext("If there is a certain upstream DHCP server that should be ignored, place the IP address or subnet of the DHCP server to be ignored here."); ?>
											<?=gettext("This is useful for rejecting leases from cable modems that offer private IPs when they lose upstream sync."); ?>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp_protocol_timing">
										<td width="22%" valign="top" class="vncell"><a target="_blank" href="http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#PROTOCOL_TIMING"><?=gettext("Protocol Timing"); ?></a></td>
										<td width="48%" class="vtable">
											Timeout: <input name="adv_dhcp_pt_timeout" type="text" class="formfld unknown" id="adv_dhcp_pt_timeout" size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_timeout']);?>" onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />
											Retry:   <input name="adv_dhcp_pt_retry"   type="text" class="formfld unknown" id="adv_dhcp_pt_retry"   size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_retry']);?>"   onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />
											Select Timeout: <input name="adv_dhcp_pt_select_timeout" type="text" class="formfld unknown" id="adv_dhcp_pt_select_timeout" size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_select_timeout']);?>" onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />

											&nbsp; &nbsp; &nbsp; &nbsp; 
											Presets: &nbsp;
											<input name="adv_dhcp_pt_values" type="radio" value="DHCP"	id="customdhcpptdhcpdefaults"	onclick="customdhcpptsetvalues(this, iform);" />FreeBSD Default &nbsp; 
											<input name="adv_dhcp_pt_values" type="radio" value="Clear"	id="customdhcpptclear"		onclick="customdhcpptsetvalues(this, iform);" />Clear

											<br />
											Reboot: <input name="adv_dhcp_pt_reboot" type="text" class="formfld unknown" id="adv_dhcp_pt_reboot" size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_reboot']);?>" onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />
											Backoff Cutoff:   <input name="adv_dhcp_pt_backoff_cutoff"   type="text" class="formfld unknown" id="adv_dhcp_pt_backoff_cutoff"   size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_backoff_cutoff']);?>"   onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />
											Initial Interval: <input name="adv_dhcp_pt_initial_interval" type="text" class="formfld unknown" id="adv_dhcp_pt_initial_interval" size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp_pt_initial_interval']);?>" onchange="customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, '');" />

											&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 
											<input name="adv_dhcp_pt_values" type="radio" value="pfSense"	id="customdhcpptpfsensedefaults"	onclick="customdhcpptsetvalues(this, iform);" />pfSense Default &nbsp; 
											<input name="adv_dhcp_pt_values" type="radio" value="SavedCfg" checked="checked"	id="customdhcpptsavedcfg"		onclick="customdhcpptsetvalues(this, iform);" />Saved Cfg 

											<br />
											<?=gettext("The values in these fields are DHCP protocol timings used when requesting a lease. <br /> " ); ?>

											<script type="text/javascript">
											//<![CDATA[
												function customdhcpptcheckradiobuton(T, BUTTON) {
													for (var i = 0; i < T.length; i++) {
														T[i].checked = false;
														if (T[i].value == BUTTON) T[i].checked = true;
													}
													T.value = BUTTON;
												}

												function customdhcpptsetvalues(T, FORM) {
													// timeout, retry, select-timeout, reboot, backoff-cutoff, initial-interval
													if (T.value == "DHCP")		customdhcpptsetvaluesnow(T, FORM, "60", "300", "0", "10", "120", "10");
													if (T.value == "pfSense")	customdhcpptsetvaluesnow(T, FORM, "60", "15", "0", "", "", "1");
													if (T.value == "SavedCfg")	customdhcpptsetvaluesnow(T, FORM, "<?=htmlspecialchars($pconfig['adv_dhcp_pt_timeout']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_retry']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_select_timeout']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_reboot']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_backoff_cutoff']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_initial_interval']);?>");
													if (T.value == "Clear")		customdhcpptsetvaluesnow(T, FORM, "", "", "", "", "", "");
												}

												function customdhcpptsetvaluesnow(T, FORM, timeout, retry, selecttimeout, reboot, backoffcutoff, initialinterval) {
													FORM.adv_dhcp_pt_timeout.value = timeout;
													FORM.adv_dhcp_pt_retry.value = retry;
													FORM.adv_dhcp_pt_select_timeout.value = selecttimeout;
													FORM.adv_dhcp_pt_reboot.value = reboot;
													FORM.adv_dhcp_pt_backoff_cutoff.value = backoffcutoff;
													FORM.adv_dhcp_pt_initial_interval.value = initialinterval;

													FORM.adv_dhcp_pt_values.value = T.value;
												}

												<!-- Set the adv_dhcp_pt_values radio button from saved config -->
												var RADIOBUTTON_VALUE = "<?=htmlspecialchars($pconfig['adv_dhcp_pt_values']);?>";
												if (RADIOBUTTON_VALUE == "") RADIOBUTTON_VALUE = "SavedCfg";
												customdhcpptcheckradiobuton(document.iform.adv_dhcp_pt_values, RADIOBUTTON_VALUE);
											//]]>
											</script>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp_lease_requirements_and_requests">
										<td width="22%" valign="top" class="vncell"><?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS\">Lease Requirements and Requests</a>"); ?></td>
										<td width="78%" class="vtable">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS\">Send</a> <a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp-options&amp;sektion=5\">Options</a>"); ?><br />
											<input name="adv_dhcp_send_options" type="text" class="formfld unknown" id="adv_dhcp_send_options" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp_send_options']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP options to be sent when requesting a DHCP lease.  [option declaration [, ...]] <br />" .
											"Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
											"Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none). <br />" .
											"Some ISPs may require certain options be or not be sent. "); ?>
											<hr/>
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS\">Request</a> <a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp-options&amp;sektion=5\">Options</a>"); ?><br />
											<input name="adv_dhcp_request_options" type="text" class="formfld unknown" id="adv_dhcp_request_options" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp_request_options']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP option 55 to be sent when requesting a DHCP lease.  [option [, ...]] <br />" .
											"Some ISPs may require certain options be or not be requested. "); ?>
											<hr/>
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS\">Require</a> <a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp-options&amp;sektion=5\">Options</a>"); ?><br />
											<input name="adv_dhcp_required_options" type="text" class="formfld unknown" id="adv_dhcp_required_options" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp_required_options']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP options required by the client when requesting a DHCP lease.  [option [, ...]] "); ?>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp_option_modifiers">
										<td width="22%" valign="top" class="vncell"><?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp-options&amp;sektion=5\">Option</a> <a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#OPTION_MODIFIERS\">Modifiers</a>"); ?></td>
										<td width="78%" class="vtable">
											<input name="adv_dhcp_option_modifiers" type="text" class="formfld unknown" id="adv_dhcp_option_modifiers" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp_option_modifiers']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP option modifiers applied to obtained DHCP lease.  [modifier option declaration [, ...]] <br /> " .
											"modifiers: (default, supersede, prepend, append)"); ?>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp_config_file_override">
										<td width="22%" valign="top" class="vncell"><?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5\">Configuration File</a> Override"); ?></td>
										<td width="78%" class="vtable">
 											<input name="adv_dhcp_config_file_override_path"   type="text" class="formfld unknown" id="adv_dhcp_config_file_override_path"  size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp_config_file_override_path']);?>" />
											<br />
											<?=gettext("The value in this field is the full absolute path to a DHCP client configuration file.  [/[dirname/[.../]]filename[.ext]] <br /> " .
											"Value Substitutions in Config File: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
											"Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none). <br />" .
											"Some ISPs may require certain options be or not be sent. "); ?>
										</td>
									</tr>

 									<tr>
 										<td colspan="2" valign="top" height="16"></td>
 									</tr>

								</table>

									<script type="text/javascript">
									//<![CDATA[
										function show_adv_dhcp_config(T) {

											if (T.checked) T.value = "Selected";
											else T.value = "";

											     if (document.iform.adv_dhcp_config_file_override.checked)	show_hide_adv_dhcp('none', 'none', '');
											else if (document.iform.adv_dhcp_config_advanced.checked)	show_hide_adv_dhcp('', '', 'none');
											else 									show_hide_adv_dhcp('', 'none', 'none');
										}

										function show_hide_adv_dhcp(basic, advanced, override) {

											document.getElementById("show_basic_dhcphostname").style.display = basic;
											document.getElementById("show_basic_dhcpalias-address").style.display = basic;
											document.getElementById("show_basic_dhcprejectlease").style.display = basic;

											document.getElementById("show_adv_dhcp_protocol_timing").style.display = advanced;
											document.getElementById("show_adv_dhcp_lease_requirements_and_requests").style.display = advanced;
											document.getElementById("show_adv_dhcp_option_modifiers").style.display = advanced;

											document.getElementById("show_adv_dhcp_config_file_override").style.display = override;
										}

										<!-- Set the adv_dhcp_config_advanced checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp_config_advanced']);?>" == "Selected") document.iform.adv_dhcp_config_advanced.checked = true;
										show_adv_dhcp_config(document.iform.adv_dhcp_config_advanced);

										<!-- Set the adv_dhcp_config_file_override checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp_config_file_override']);?>" == "Selected") document.iform.adv_dhcp_config_file_override.checked = true;
										show_adv_dhcp_config(document.iform.adv_dhcp_config_file_override);
									//]]>
									</script>

							</td>
						</tr>
						<tr style="display:none;" id="dhcp6">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="dhcp6">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("DHCP6 client configuration &nbsp; &nbsp; " .
										' <input name="adv_dhcp6_config_advanced" type="checkbox" id="adv_dhcp6_config_advanced" value="" onclick="show_adv_dhcp6_config(this)" /> ' .
										" Advanced &nbsp; &nbsp; " .
										' <input name="adv_dhcp6_config_file_override" type="checkbox" id="adv_dhcp6_config_file_override" value="" onclick="show_adv_dhcp6_config(this)" /> ' .
										" Config File Override &nbsp; &nbsp; "); ?>
										</td>
									</tr>
									<!--- Leave commented out for now
									<tr style='display:none' id="basicdhcp6_show_dhcp6_duid">
										<td width="22%" valign="top" class="vncell"><?=gettext("DHCPv6 Unique Identifier (DUID)"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp6-duid" type="text" class="formfld unknown" id="dhcp6-duid" size="40" value="<?=htmlspecialchars($pconfig['dhcp6-duid']);?>" />
											<br />
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
									<tr style='display:none' id="basicdhcp6_use_pppoeinterface">
										<td width="22%" valign="top" class="vncell"><?=gettext("Use IPv4 connectivity as parent interface"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp6usev4iface" type="checkbox" id="dhcp6usev4iface" value="yes" <?php if ($pconfig['dhcp6usev4iface'] == true) echo "checked=\"checked\""; ?> />
											<?=gettext("Request a IPv6 prefix/information through the IPv4 connectivity link"); ?>
										</td>
									</tr>
									<tr style='display:none' id="basicdhcp6_show_dhcp6_prefix_only">
										<td width="22%" valign="top" class="vncell"><?=gettext("Request only a IPv6 prefix"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp6prefixonly" type="checkbox" id="dhcp6prefixonly" value="yes" <?php if ($pconfig['dhcp6prefixonly'] == true) echo "checked=\"checked\""; ?> />
											<?=gettext("Only request a IPv6 prefix, do not request a IPv6 address"); ?>
										</td>
									</tr>
									<tr style='display:none' id="basicdhcp6_show_dhcp6_prefix_delegation_size">
										<td width="22%" valign="top" class="vncell"><?=gettext("DHCPv6 Prefix Delegation size"); ?></td>
										<td width="78%" class="vtable">
											<select name="dhcp6-ia-pd-len" class="formselect" id="dhcp6-ia-pd-len">
												<?php
												$sizes = array("none" => "None", 16 => "48", 12 => "52", 8 => "56", 4 => "60", 2 => "62", 1 => "63", 0 => "64");
												foreach($sizes as $bits => $length) {
													echo "<option value=\"{$bits}\" ";
													if (is_numeric($pconfig['dhcp6-ia-pd-len']) && ($bits == $pconfig['dhcp6-ia-pd-len'])) echo "selected=\"selected\"";
													echo ">" . $length . "</option>";
												}
												?>
											</select>
											<br />
											<?=gettext("The value in this field is the delegated prefix length provided by the DHCPv6 server. Normally specified by the ISP."); ?>
										</td>
									</tr>
									<tr style='display:none' id="basicdhcp6_show_dhcp6_prefix_send_hint">
										<td width="22%" valign="top" class="vncell"><?=gettext("Send IPv6 prefix hint"); ?></td>
										<td width="78%" class="vtable">
											<input name="dhcp6-ia-pd-send-hint" type="checkbox" id="dhcp6-ia-pd-send-hint" value="yes" <?php if ($pconfig['dhcp6-ia-pd-send-hint'] == true) echo "checked=\"checked\""; ?> />
											<?=gettext("Send an IPv6 prefix hint to indicate the desired prefix size for delegation"); ?>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_interface_statement">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports#Interface_statement\">Interface Statement</a>"); ?>
											<br /><br />
											<input name="adv_dhcp6_interface_statement_information_only_enable" type="checkbox" id="adv_dhcp6_interface_statement_information_only_enable" value="" onclick="show_adv_dhcp6_config(this)" />
											<?=gettext("Information Only"); ?>
										</td>
										<td width="78%" class="vtable">
											<?=gettext("Send Options"); ?><br />
											<input name="adv_dhcp6_interface_statement_send_options" type="text" class="formfld unknown" id="adv_dhcp6_interface_statement_send_options" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_send_options']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP send options to be sent when requesting a DHCP lease.  [option declaration [, ...]] <br />" .
											"Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
											"Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none). <br />" .
											"Some DHCP services may require certain options be or not be sent. "); ?>
											<br />
											<br />
											<?=gettext("Request Options"); ?><br />
											<input name="adv_dhcp6_interface_statement_request_options" type="text" class="formfld unknown" id="adv_dhcp6_interface_statement_request_options" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_request_options']);?>" />
											<br />
											<?=gettext("The values in this field are DHCP request options to be sent when requesting a DHCP lease.  [option [, ...]] <br />" .
											"Some DHCP services may require certain options be or not be requested. "); ?>
											<br />
											<br />
											<?=gettext("Script"); ?><br />
											<input name="adv_dhcp6_interface_statement_script" type="text" class="formfld unknown" id="adv_dhcp6_interface_statement_script" size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_script']);?>" />
											<br />
											<?=gettext("The value in this field is the absolute path to a script invoked on certain conditions including when a reply message is received. <br />" .
											"[/[dirname/[.../]]filename[.ext]] "); ?>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_id_assoc_statement">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports#Identity_association_statement\">Identity Association Statement</a>"); ?>
										</td>
										<td width="78%" class="vtable">

											<input name="adv_dhcp6_id_assoc_statement_address_enable" type="checkbox" id="adv_dhcp6_id_assoc_statement_address_enable" value="" onclick="show_adv_dhcp6_config(this);" />
											<?=gettext("Non-Temporary Address Allocation"); ?>
											<div style='display:none'  id="show_adv_dhcp6_id_assoc_statement_address">
											<?=gettext("id-assoc na"); ?>
											<?=gettext("<i>ID</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_address_id" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_address_id" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_address_id']);?>" />
											<br />
											<?=gettext("Address"); ?>
											<?=gettext("<i>ipv6-address</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_address" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_address" size="34" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_address']);?>" />
											<?=gettext("<i>pltime</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_address_pltime" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_address_pltime" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_address_pltime']);?>" />
											<?=gettext("<i>vltime</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_address_vltime" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_address_vltime" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_address_vltime']);?>" />
											</div>
											<hr/>

											<input name="adv_dhcp6_id_assoc_statement_prefix_enable" type="checkbox" id="adv_dhcp6_id_assoc_statement_prefix_enable" value="" onclick="show_adv_dhcp6_config(this)" />
											<?=gettext("Prefix Delegation"); ?>
											<div style='display:none'  id="show_adv_dhcp6_id_assoc_statement_prefix">
											<?=gettext("id-assoc pd"); ?>
											<?=gettext("<i>ID</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_prefix_id" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_prefix_id" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_prefix_id']);?>" />
											<br />
											<?=gettext("Prefix"); ?>
											<?=gettext("<i>ipv6-prefix</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_prefix" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_prefix" size="37" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_prefix']);?>" />
											<?=gettext("<i>pltime</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_prefix_pltime" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_prefix_pltime" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_prefix_pltime']);?>" />
											<?=gettext("<i>vltime</i>"); ?>
											<input name="adv_dhcp6_id_assoc_statement_prefix_vltime" type="text" class="formfld unknown" id="adv_dhcp6_id_assoc_statement_prefix_vltime" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_prefix_vltime']);?>" />
											</div>
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_prefix_interface_statement">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports#Prefix_interface_statement\">Prefix Interface Statement</a>"); ?>
										</td>
										<td width="78%" class="vtable">
											<?=gettext("Prefix Interface "); ?>
											<?=gettext("<i>sla-id</i>"); ?>
											<input name="adv_dhcp6_prefix_interface_statement_sla_id" type="text" class="formfld unknown" id="adv_dhcp6_prefix_interface_statement_sla_id" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_prefix_interface_statement_sla_id']);?>" />
											<?=gettext("<i>sla-len</i>"); ?>
											<input name="adv_dhcp6_prefix_interface_statement_sla_len" type="text" class="formfld unknown" id="adv_dhcp6_prefix_interface_statement_sla_len" size="3" value="<?=htmlspecialchars($pconfig['adv_dhcp6_prefix_interface_statement_sla_len']);?>" />
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_authentication_statement">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports#Authentication_statement\">Authentication Statement</a>"); ?>
										</td>
										<td width="78%" class="vtable">
											<?=gettext("<i>authname</i>"); ?>
											<input name="adv_dhcp6_authentication_statement_authname" type="text" class="formfld unknown" id="adv_dhcp6_authentication_statement_authname" size="10" value="<?=htmlspecialchars($pconfig['adv_dhcp6_authentication_statement_authname']);?>" />
											<?=gettext("<i>protocol</i>"); ?>
											<input name="adv_dhcp6_authentication_statement_protocol" type="text" class="formfld unknown" id="adv_dhcp6_authentication_statement_protocol" size="6" value="<?=htmlspecialchars($pconfig['adv_dhcp6_authentication_statement_protocol']);?>" />
											<?=gettext("<i>algorithm</i>"); ?>
											<input name="adv_dhcp6_authentication_statement_algorithm" type="text" class="formfld unknown" id="adv_dhcp6_authentication_statement_algorithm" size="8" value="<?=htmlspecialchars($pconfig['adv_dhcp6_authentication_statement_algorithm']);?>" />
											<?=gettext("<i>rdm</i>"); ?>
											<input name="adv_dhcp6_authentication_statement_rdm" type="text" class="formfld unknown" id="adv_dhcp6_authentication_statement_rdm" size="9" value="<?=htmlspecialchars($pconfig['adv_dhcp6_authentication_statement_rdm']);?>" />
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_key_info_statement">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports#Keyinfo_statement\">Keyinfo Statement</a>"); ?>
										</td>
										<td width="78%" class="vtable">
											<?=gettext("<i>keyname</i>"); ?>
											<input name="adv_dhcp6_key_info_statement_keyname" type="text" class="formfld unknown" id="adv_dhcp6_key_info_statement_keyname" size="27" value="<?=htmlspecialchars($pconfig['adv_dhcp6_key_info_statement_keyname']);?>" />
											<?=gettext("<i>realm</i>"); ?>
											<input name="adv_dhcp6_key_info_statement_realm" type="text" class="formfld unknown" id="adv_dhcp6_key_info_statement_realm" size="37" value="<?=htmlspecialchars($pconfig['adv_dhcp6_key_info_statement_realm']);?>" />
											<br />
											<?=gettext("<i>keyid</i>"); ?>
											<input name="adv_dhcp6_key_info_statement_keyid" type="text" class="formfld unknown" id="adv_dhcp6_key_info_statement_keyid" size="2" value="<?=htmlspecialchars($pconfig['adv_dhcp6_key_info_statement_keyid']);?>" />
											<?=gettext("<i>secret</i>"); ?>
											<input name="adv_dhcp6_key_info_statement_secret" type="text" class="formfld unknown" id="adv_dhcp6_key_info_statement_secret" size="36" value="<?=htmlspecialchars($pconfig['adv_dhcp6_key_info_statement_secret']);?>" />
											<?=gettext("<i>expire</i>"); ?>
											<input name="adv_dhcp6_key_info_statement_expire" type="text" class="formfld unknown" id="adv_dhcp6_key_info_statement_expire" size="15" value="<?=htmlspecialchars($pconfig['adv_dhcp6_key_info_statement_expire']);?>" />
										</td>
									</tr>

									<tr style='display:none' id="show_adv_dhcp6_config_file_override">
										<td width="22%" valign="top" class="vncell">
											<?=gettext("<a target=\"FreeBSD_DHCP\" href=\"http://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&amp;sektion=5&amp;apropos=0&amp;manpath=FreeBSD+Ports\">Configuration File</a> Override"); ?>
										</td>
										<td width="78%" class="vtable">
 											<input name="adv_dhcp6_config_file_override_path"   type="text" class="formfld unknown" id="adv_dhcp6_config_file_override_path"  size="86" value="<?=htmlspecialchars($pconfig['adv_dhcp6_config_file_override_path']);?>" />
											<br />
											<?=gettext("The value in this field is the full absolute path to a DHCP client configuration file.  [/[dirname/[.../]]filename[.ext]] <br /> " .
											"Value Substitutions in Config File: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
											"Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none). <br />" .
											"Some ISPs may require certain options be or not be sent. "); ?>
										</td>
									</tr>

 									<tr>
 										<td colspan="2" valign="top" height="16"></td>
 									</tr>

								</table>

									<script type="text/javascript">
									//<![CDATA[
										function show_adv_dhcp6_config(T) {

											if (T.checked) T.value = "Selected";
											else T.value = "";

											     if (document.iform.adv_dhcp6_config_file_override.checked)	show_hide_adv_dhcp6('none', 'none', ''    );
											else if (document.iform.adv_dhcp6_config_advanced.checked)		show_hide_adv_dhcp6('none', '',     'none');
											else 															show_hide_adv_dhcp6('',     'none', 'none');
										}

										function show_hide_adv_dhcp6(basic, advanced, override) {

											document.getElementById("basicdhcp6_use_pppoeinterface").style.display = basic;
											document.getElementById("basicdhcp6_show_dhcp6_prefix_delegation_size").style.display = basic;
											document.getElementById("basicdhcp6_show_dhcp6_prefix_send_hint").style.display = basic;
											document.getElementById("basicdhcp6_show_dhcp6_prefix_only").style.display = basic;

											document.getElementById("show_adv_dhcp6_interface_statement").style.display = advanced;
											document.getElementById("show_adv_dhcp6_id_assoc_statement").style.display = advanced;

											document.getElementById("show_adv_dhcp6_id_assoc_statement_address").style.display = 'none';
											if (document.iform.adv_dhcp6_id_assoc_statement_address_enable.checked)  {
												document.getElementById("show_adv_dhcp6_id_assoc_statement_address").style.display = advanced;
											}

											document.getElementById("show_adv_dhcp6_id_assoc_statement_prefix").style.display = 'none';
											document.getElementById("show_adv_dhcp6_prefix_interface_statement").style.display = 'none';
											if (document.iform.adv_dhcp6_id_assoc_statement_prefix_enable.checked)  {
												document.getElementById("show_adv_dhcp6_id_assoc_statement_prefix").style.display = advanced;
												document.getElementById("show_adv_dhcp6_prefix_interface_statement").style.display = advanced;
											}

											document.getElementById("show_adv_dhcp6_authentication_statement").style.display = advanced;
											document.getElementById("show_adv_dhcp6_key_info_statement").style.display = advanced;

											document.getElementById("show_adv_dhcp6_config_file_override").style.display = override;
										}

										<!-- Set the adv_dhcp6_config_advanced checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp6_config_advanced']);?>" == "Selected") document.iform.adv_dhcp6_config_advanced.checked = true;
										show_adv_dhcp6_config(document.iform.adv_dhcp6_config_advanced);

										<!-- Set the adv_dhcp6_config_file_override checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp6_config_file_override']);?>" == "Selected") document.iform.adv_dhcp6_config_file_override.checked = true;
										show_adv_dhcp6_config(document.iform.adv_dhcp6_config_file_override);

										<!-- Set the adv_dhcp6_interface_statement_information_only_enable checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_information_only_enable']);?>" == "Selected") document.iform.adv_dhcp6_interface_statement_information_only_enable.checked = true;
										show_adv_dhcp6_config(document.iform.adv_dhcp6_interface_statement_information_only_enable);

										<!-- Set the adv_dhcp6_id_assoc_statement_address_enable checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_address_enable']);?>" == "Selected") document.iform.adv_dhcp6_id_assoc_statement_address_enable.checked = true;
										show_adv_dhcp6_config(document.iform.adv_dhcp6_id_assoc_statement_address_enable);

										<!-- Set the adv_dhcp6_id_assoc_statement_prefix_enable checkbox from saved config -->
										if ("<?=htmlspecialchars($pconfig['adv_dhcp6_id_assoc_statement_prefix_enable']);?>" == "Selected") document.iform.adv_dhcp6_id_assoc_statement_prefix_enable.checked = true;
										show_adv_dhcp6_config(document.iform.adv_dhcp6_id_assoc_statement_prefix_enable);
									//]]>
									</script>

							</td>
						</tr>
						<tr style="display:none;" id="_6rd">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="6rd">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("6RD Rapid Deployment"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("6RD prefix"); ?></td>
										<td width="78%" class="vtable">
											<input name="prefix-6rd" type="text" class="formfld unknown" id="prefix-6rd" size="40" value="<?=htmlspecialchars($pconfig['prefix-6rd']);?>" />
											<br />
											<?=gettext("The value in this field is the 6RD IPv6 prefix assigned by your ISP. e.g. '2001:db8::/32'") ?><br />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("6RD Border Relay"); ?></td>
										<td width="78%" class="vtable">
											<input name="gateway-6rd" type="text" class="formfld unknown" id="gateway-6rd" size="40" value="<?=htmlspecialchars($pconfig['gateway-6rd']);?>" />
											<br />
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
													if (is_numeric($pconfig['prefix-6rd-v4plen']) && ($i == $pconfig['prefix-6rd-v4plen'])) echo "selected=\"selected\"";
													echo ">" . $i . " bits</option>";
												}
												?>
											</select>
											<br />
											<?=gettext("The value in this field is the 6RD IPv4 prefix length. Normally specified by the ISP. A value of 0 means we embed the entire IPv4 address in the 6RD prefix."); ?>
										</td>
									</tr>
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="track6">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="track6">
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
											$rowIndex = 0;
											foreach($dynv6ifs as $iface => $ifacename) {
												$rowIndex++;
												echo "<option value=\"{$iface}\"";
												if ($iface == $pconfig['track6-interface'])
													echo " selected=\"selected\"";
												echo ">" . htmlspecialchars($ifacename) . "</option>";
											}
											if ($rowIndex == 0)
												echo "<option></option>";
										?>
										</select> <br />
											<br />
											<?=gettext("This selects the dynamic IPv6 WAN interface to track for configuration") ?><br />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("IPv6 Prefix ID"); ?></td>
										<td width="78%" class="vtable">
											<?php
												if ($pconfig['track6-prefix-id'] == "")
													$pconfig['track6-prefix-id'] = 0;
												$track6_prefix_id_hex = sprintf("%x", $pconfig['track6-prefix-id']);
											?>
											<input name="track6-prefix-id--hex" type="text" class="formfld unknown" id="track6-prefix-id--hex" size="8" value="<?= $track6_prefix_id_hex ?>" />
											<br />
											<?= gettext("The value in this field is the (Delegated) IPv6 prefix id. This determines the configurable network ID based on the dynamic IPv6 connection"); ?>
											<br />
											<?= sprintf(gettext("Enter a <b>hexadecimal</b> value between %x and %x here, default value is 0."), 0, $ipv6_num_prefix_ids - 1); ?>
										</td>
									</tr>
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="ppp">
							<td colspan="2" style="padding: 0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="ppp">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPP configuration"); ?></td>
									</tr>
									<tr id="ppp_provider">
										<td width="22%" valign="top" class="vncell"><?=gettext("Service Provider"); ?></td>
										<td width="78%" class="vtable">
											<table border="0" cellpadding="0" cellspacing="0" summary="service provider">
												<tr id="trcountry">
													<td><?=gettext("Country:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="country" id="country" onchange="providers_list()">
															<option></option>
														</select>
													</td>
												</tr>
												<tr id="trprovider" style="display:none">
													<td><?=gettext("Provider:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="provider_list" id="provider_list" onchange="providerplan_list()">
															<option></option>
														</select>
													</td>
												</tr>
												<tr id="trproviderplan" style="display:none">
													<td><?=gettext("Plan:"); ?> &nbsp;&nbsp;</td>
													<td>
														<select class="formselect" name="providerplan" id="providerplan" onchange="prefill_provider()">
															<option></option>
														</select>
													</td>
												</tr>
											</table>
											<br /><span class="vexpl"><?=gettext("Select to fill in data for your service provider."); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
										<input name="username" type="text" class="formfld user" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
										<input name="password" type="password" class="formfld pwd" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>" />
										</td>
									</tr>
									<tr id="phone_num">
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Phone Number"); ?></td>
										<td width="78%" class="vtable">
											<input name="phone" type="text" class="formfld unknown" id="phone" size="12" value="<?=htmlspecialchars($pconfig['phone']);?>" />
										</td>
									</tr>
									<tr id="apn_">
										<td width="22%" valign="top" class="vncell"><?=gettext("Access Point Name (APN)"); ?></td>
										<td width="78%" class="vtable">
											<input name="apn" type="text" class="formfld unknown" id="apn" size="40" value="<?=htmlspecialchars($pconfig['apn']);?>" />
										</td>
									</tr>
									<tr id="interface" >
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Modem Port"); ?></td>
										<td width="78%" class="vtable">
											<select name="port" id="port" class="formselect">
											<?php
												$portlist = glob("/dev/cua*");
												$modems = glob("/dev/modem*");
												$portlist = array_merge($portlist, $modems);
												$rowIndex = 0;
												foreach ($portlist as $port) {
													if(preg_match("/\.(lock|init)$/", $port))
														continue;
													$rowIndex++;
													echo "<option value=\"".trim($port)."\"";
													if ($pconfig['port'] == $port)
														echo " selected=\"selected\"";
													echo ">{$port}</option>";
												}
												if ($rowIndex == 0)
													echo "<option></option>";
												?>
											</select>
										</td>
									</tr>
									<tr>
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
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="pppoe">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="pppoe">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPPoE configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
												<input name="pppoe_username" type="text" class="formfld user" id="pppoe_username" size="20" value="<?=htmlspecialchars($pconfig['pppoe_username']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_password" type="password" class="formfld pwd" id="pppoe_password" size="20" value="<?=htmlspecialchars($pconfig['pppoe_password']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Service name"); ?></td>
										<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>" />
											<br /> <span class="vexpl"><?=gettext("Hint: this field can usually be left empty"); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Dial on demand"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_dialondemand" type="checkbox" id="pppoe_dialondemand" value="enable" <?php if ($pconfig['pppoe_dialondemand']) echo "checked=\"checked\""; ?> />
											<strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br />
											<?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a "); ?><i><?=gettext("virtual full time"); ?></i> <?=gettext("connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_idletimeout" type="text" class="formfld unknown" id="pppoe_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pppoe_idletimeout']);?>" /> <?=gettext("seconds"); ?><br /><?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
										<td width="78%" class="vtable">
											<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%" summary="periodic reset">
												<tr>
													<td align="left" valign="top">
														<p style="margin: 4px; padding: 4px 0 4px 0; width: 94%;">
														<select style="vertical-align:top" id="reset_type" name="pppoe-reset-type" class="formselect" onchange="show_reset_settings(this.value);">
															<option value=""><?=gettext("Disabled"); ?></option>
															<option value="custom" <?php if ($pconfig['pppoe-reset-type'] == "custom") echo "selected=\"selected\""; ?>><?=gettext("Custom"); ?></option>
															<option value="preset" <?php if ($pconfig['pppoe-reset-type'] == "preset") echo "selected=\"selected\""; ?>><?=gettext("Pre-Set"); ?></option>
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
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" id="pptp">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="pptp">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("PPTP/L2TP configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Username"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_username" type="text" class="formfld user" id="pptp_username" size="20" value="<?=htmlspecialchars($pconfig['pptp_username']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Password"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_password" type="password" class="formfld pwd" id="pptp_password" size="20" value="<?=htmlspecialchars($pconfig['pptp_password']);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Local IP address"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_local" type="text" class="formfld unknown" id="pptp_local" size="20"  value="<?=htmlspecialchars($pconfig['pptp_local'][0]);?>" />
											/
											<select name="pptp_subnet" class="formselect" id="pptp_subnet">
												<?php for ($i = 31; $i > 0; $i--): ?>
													<option value="<?=$i;?>" <?php if ($i == $pconfig['pptp_subnet'][0]) echo "selected=\"selected\""; ?>>
														<?=$i;?></option>
												<?php endfor; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Remote IP address"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_remote" type="text" class="formfld unknown" id="pptp_remote" size="20" value="<?=htmlspecialchars($pconfig['pptp_remote'][0]);?>" />
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Dial on demand"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_dialondemand" type="checkbox" id="pptp_dialondemand" value="enable" <?php if ($pconfig['pptp_dialondemand']) echo "checked=\"checked\""; ?> />
											<strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br />
											<?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a"); ?> <i><?=gettext("virtual full time"); ?></i> <?=gettext("connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Idle timeout"); ?></td>
										<td width="78%" class="vtable">
											<input name="pptp_idletimeout" type="text" class="formfld unknown" id="pptp_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pptp_idletimeout']);?>" /> <?=gettext("seconds"); ?><br /><?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
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
									<tr>
										<td colspan="2" valign="top" height="16"></td>
									</tr>
								</table>
							</td>
						</tr>
						<?php
							/* Wireless interface? */
							if (isset($wancfg['wireless'])):
						?>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Common wireless configuration - Settings apply to all wireless networks on"); ?> <?=$wlanbaseif;?>.</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Persist common settings");?></td>
							<td class="vtable">
								<input name="persistcommonwireless" type="checkbox" value="yes"  class="formfld" id="persistcommonwireless" <?php if ($pconfig['persistcommonwireless']) echo "checked=\"checked\"";?> />
								<br /><?=gettext("Enabling this preserves the common wireless configuration through interface deletions and reassignments.");?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Standard"); ?></td>
							<td class="vtable">
							<select name="standard" class="formselect" id="standard">
								<?php
								$rowIndex = 0;
								foreach($wl_modes as $wl_standard => $wl_channels) {
									$rowIndex++;
									echo "<option ";
									if ($pconfig['standard'] == "$wl_standard")
										echo "selected=\"selected\" ";
									echo "value=\"$wl_standard\">802.$wl_standard</option>\n";
								}
								if ($rowIndex == 0)
									echo "<option></option>";
								?>
							</select>
							</td>
						</tr>
						<?php if (isset($wl_modes['11g'])): ?>
						<tr>
							<td valign="top" class="vncellreq">802.11g OFDM <?=gettext("Protection Mode"); ?></td>
							<td class="vtable">
								<select name="protmode" class="formselect" id="protmode">
									<option <?php if ($pconfig['protmode'] == 'off') echo "selected=\"selected\"";?> value="off"><?=gettext("Protection mode off"); ?></option>
									<option <?php if ($pconfig['protmode'] == 'cts') echo "selected=\"selected\"";?> value="cts"><?=gettext("Protection mode CTS to self"); ?></option>
									<option <?php if ($pconfig['protmode'] == 'rtscts') echo "selected=\"selected\"";?> value="rtscts"><?=gettext("Protection mode RTS and CTS"); ?></option>
								</select>
								<br />
								<?=gettext("For IEEE 802.11g, use the specified technique for protecting OFDM frames in a mixed 11b/11g network."); ?>
								<br />
							</td>
						</tr>
						<?php else: ?>
						<input name="protmode" type="hidden" id="protmode" value="off" />
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Transmit power"); ?></td>
							<td class="vtable">
								<select name="txpower" class="formselect" id="txpower">
									<?
									for($x = 99; $x > 0; $x--) {
										if($pconfig["txpower"] == $x)
											$SELECTED = " selected=\"selected\"";
										else
											$SELECTED = "";
										echo "<option {$SELECTED}>{$x}</option>\n";
									}
									?>
								</select><br />
								<?=gettext("Note: Typically only a few discreet power settings are available and the driver will use the setting closest to the specified value.  Not all adapters support changing the transmit power setting."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("Channel"); ?></td>
							<td class="vtable">
								<select name="channel" class="formselect" id="channel">
									<option <?php if ($pconfig['channel'] == 0) echo "selected=\"selected\""; ?> value="0"><?=gettext("Auto"); ?></option>
									<?php
									foreach($wl_modes as $wl_standard => $wl_channels) {
										if($wl_standard == "11g") { $wl_standard = "11b/g"; }
										else if($wl_standard == "11ng") { $wl_standard = "11b/g/n"; }
										else if($wl_standard == "11na") { $wl_standard = "11a/n"; }
										foreach($wl_channels as $wl_channel) {
											echo "<option ";
											if ($pconfig['channel'] == "$wl_channel") {
												echo "selected=\"selected\" ";
											}
											echo "value=\"$wl_channel\">$wl_standard - $wl_channel";
											if(isset($wl_chaninfo[$wl_channel]))
												echo " ({$wl_chaninfo[$wl_channel][1]} @ {$wl_chaninfo[$wl_channel][2]} / {$wl_chaninfo[$wl_channel][3]})";
											echo "</option>\n";
										}
									}
									?>
								</select>
								<br />
								<?=gettext("Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain)"); ?>
								<br />
								<?=gettext("Note: Not all channels may be supported by your card.  Auto may override the wireless standard selected above."); ?>
							</td>
						</tr>
						<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Antenna settings"); ?></td>
							<td class="vtable">
								<table border="0" cellpadding="0" cellspacing="0" summary="antenna settings">
									<tr>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"])): ?>
										<td>
											<?=gettext("Diversity"); ?><br />
											<select name="diversity" class="formselect" id="diversity">
												<option <?php if (!isset($pconfig['diversity'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['diversity'] === '0') echo "selected=\"selected\""; ?> value="0"><?=gettext("Off"); ?></option>
												<option <?php if ($pconfig['diversity'] === '1') echo "selected=\"selected\""; ?> value="1"><?=gettext("On"); ?></option>
											</select>
										</td>
										<td>&nbsp;&nbsp;</td>
										<?php endif; ?>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"])): ?>
										<td>
											<?=gettext("Transmit antenna"); ?><br />
											<select name="txantenna" class="formselect" id="txantenna">
												<option <?php if (!isset($pconfig['txantenna'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '0') echo "selected=\"selected\""; ?> value="0"><?=gettext("Auto"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '1') echo "selected=\"selected\""; ?> value="1"><?=gettext("#1"); ?></option>
												<option <?php if ($pconfig['txantenna'] === '2') echo "selected=\"selected\""; ?> value="2"><?=gettext("#2"); ?></option>
											</select>
										</td>
										<td>&nbsp;&nbsp;</td>
										<?php endif; ?>
										<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
										<td>
											<?=gettext("Receive antenna"); ?><br />
											<select name="rxantenna" class="formselect" id="rxantenna">
												<option <?php if (!isset($pconfig['rxantenna'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '0') echo "selected=\"selected\""; ?> value="0"><?=gettext("Auto"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '1') echo "selected=\"selected\""; ?> value="1"><?=gettext("#1"); ?></option>
												<option <?php if ($pconfig['rxantenna'] === '2') echo "selected=\"selected\""; ?> value="2"><?=gettext("#2"); ?></option>
											</select>
										</td>
										<?php endif; ?>
									</tr>
								</table>
								<br />
								<?=gettext("Note: The antenna numbers do not always match up with the labels on the card."); ?>
							</td>
						</tr>
						<?php endif; ?>
						<?php if (isset($wl_sysctl["{$wl_sysctl_prefix}.slottime"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.acktimeout"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.ctstimeout"])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Distance setting"); ?></td>
							<td class="vtable">
								<input name="distance" type="text" class="formfld unknown" id="distance" size="5" value="<?=htmlspecialchars($pconfig['distance']);?>" />
								<br />
								<?=gettext("Note: This field can be used to tune ACK/CTS timers to fit the distance between AP and Client"); ?><br />
								<?=gettext("(measured in Meters and works only for Atheros based cards !)"); ?>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Regulatory settings"); ?></td>
							<td class="vtable">
								<?=gettext("Regulatory domain"); ?><br />
								<select name="regdomain" class="formselect" id="regdomain">
									<option <?php if (empty($pconfig['regdomain'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
									<?php
									foreach($wl_regdomains as $wl_regdomain_key => $wl_regdomain) {
										echo "<option ";
										if ($pconfig['regdomain'] == $wl_regdomains_attr[$wl_regdomain_key]['ID']) {
											echo "selected=\"selected\" ";
										}
										echo "value=\"{$wl_regdomains_attr[$wl_regdomain_key]['ID']}\">{$wl_regdomain['name']}</option>\n";
									}
									?>
								</select>
								<br />
								<?=gettext("Note: Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work."); ?>
								<br /><br />
								<?=gettext("Country (listed with country code and regulatory domain)"); ?><br />
								<select name="regcountry" class="formselect" id="regcountry">
									<option <?php if (empty($pconfig['regcountry'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
									<?php
									foreach($wl_countries as $wl_country_key => $wl_country) {
										echo "<option ";
										if ($pconfig['regcountry'] == $wl_countries_attr[$wl_country_key]['ID']) {
											echo "selected=\"selected\" ";
										}
										echo "value=\"{$wl_countries_attr[$wl_country_key]['ID']}\">{$wl_country['name']} -- ({$wl_countries_attr[$wl_country_key]['ID']}, " . strtoupper($wl_countries_attr[$wl_country_key]['rd'][0]['REF']) . ")</option>\n";
									}
									?>
								</select>
								<br />
								<?=gettext("Note: Any country setting other than \"Default\" will override the regulatory domain setting"); ?>.
								<br /><br />
								<?=gettext("Location"); ?><br />
								<select name="reglocation" class="formselect" id="reglocation">
									<option <?php if (empty($pconfig['reglocation'])) echo "selected=\"selected\""; ?> value=""><?=gettext("Default"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'indoor') echo "selected=\"selected\""; ?> value="indoor"><?=gettext("Indoor"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'outdoor') echo "selected=\"selected\""; ?> value="outdoor"><?=gettext("Outdoor"); ?></option>
									<option <?php if ($pconfig['reglocation'] == 'anywhere') echo "selected=\"selected\""; ?> value="anywhere"><?=gettext("Anywhere"); ?></option>
								</select>
								<br /><br />
								<?=gettext("These settings may affect which channels are available and the maximum transmit power allowed on those channels.  Using the correct settings to comply with local regulatory requirements is recommended."); ?>
								<br />
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
									<option <?php if ($pconfig['mode'] == 'bss') echo "selected=\"selected\"";?> value="bss"><?=gettext("Infrastructure (BSS)"); ?></option>
									<option <?php if ($pconfig['mode'] == 'adhoc') echo "selected=\"selected\"";?> value="adhoc"><?=gettext("Ad-hoc (IBSS)"); ?></option>
									<option <?php if ($pconfig['mode'] == 'hostap') echo "selected=\"selected\"";?> value="hostap"><?=gettext("Access Point"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq"><?=gettext("SSID"); ?></td>
							<td class="vtable">
								<input name="ssid" type="text" class="formfld unknown" id="ssid" size="20" value="<?=htmlspecialchars($pconfig['ssid']); ?>" />
								<br />
								<?=gettext("Note: Only required in Access Point mode. If left blank in Ad-hoc or Infrastructure mode, this interface will connect to any available SSID"); ?>
							</td>
						</tr>
						<?php if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Minimum wireless standard"); ?></td>
							<td class="vtable">
								<select name="puremode" class="formselect" id="puremode">
									<option <?php if ($pconfig['puremode'] == 'any') echo "selected=\"selected\"";?> value="any"><?=gettext("Any"); ?></option>
									<?php if (isset($wl_modes['11g'])): ?>
									<option <?php if ($pconfig['puremode'] == '11g') echo "selected=\"selected\"";?> value="11g"><?=gettext("802.11g"); ?></option>
									<?php endif; ?>
									<option <?php if ($pconfig['puremode'] == '11n') echo "selected=\"selected\"";?> value="11n"><?=gettext("802.11n"); ?></option>
								</select>
								<br />
								<?=gettext("When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)."); ?>
							</td>
						</tr>
						<?php elseif (isset($wl_modes['11g'])): ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.11g only"); ?></td>
							<td class="vtable">
								<input name="puremode" type="checkbox" value="11g"  class="formfld" id="puremode" <?php if ($pconfig['puremode'] == '11g') echo "checked=\"checked\"";?> />
								<br /><?=gettext("When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)."); ?>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Allow intra-BSS communication"); ?></td>
							<td class="vtable">
								<input name="apbridge_enable" type="checkbox" value="yes"  class="formfld" id="apbridge_enable" <?php if ($pconfig['apbridge_enable']) echo "checked=\"checked\"";?> />
								<br />
								<?=gettext("When operating as an access point, enable this if you want to pass packets between wireless clients directly."); ?>
								<br />
								<?=gettext("Disabling the internal bridging is useful when traffic is to be processed with packet filtering."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable WME"); ?></td>
							<td class="vtable">
								<input name="wme_enable" type="checkbox" class="formfld" id="wme_enable" value="yes" <?php if ($pconfig['wme_enable']) echo "checked=\"checked\"";?> />
								<br /><?=gettext("Setting this option will force the card to use WME (wireless QoS)."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable Hide SSID"); ?></td>
							<td class="vtable">
								<input name="hidessid_enable" type="checkbox" class="formfld" id="hidessid_enable" value="yes" <?php if ($pconfig['hidessid_enable']) echo "checked=\"checked\"";?> />
								<br />
								<?=gettext("Setting this option will force the card to NOT broadcast its SSID"); ?>
								<br />
								<?=gettext("(this might create problems for some clients)."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WEP"); ?></td>
							<td class="vtable">
								<input name="wep_enable" type="checkbox" id="wep_enable" value="yes" <?php if ($pconfig['wep_enable']) echo "checked=\"checked\""; ?> />
								<strong><?=gettext("Enable WEP"); ?></strong>
								<table border="0" cellspacing="0" cellpadding="0" summary="wep">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;</td>
										<td>&nbsp;<?=gettext("TX key"); ?>&nbsp;</td>
									</tr>
									<tr>
										<td><?=gettext("Key 1:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key1" type="text" class="formfld unknown" id="key1" size="30" value="<?=htmlspecialchars($pconfig['key1']);?>" />
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="1" <?php if ($pconfig['txkey'] == 1) echo "checked=\"checked\"";?> />
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 2:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key2" type="text" class="formfld unknown" id="key2" size="30" value="<?=htmlspecialchars($pconfig['key2']);?>" />
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="2" <?php if ($pconfig['txkey'] == 2) echo "checked=\"checked\"";?> />
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 3:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key3" type="text" class="formfld unknown" id="key3" size="30" value="<?=htmlspecialchars($pconfig['key3']);?>" />
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="3" <?php if ($pconfig['txkey'] == 3) echo "checked=\"checked\"";?> />
										</td>
									</tr>
									<tr>
										<td><?=gettext("Key 4:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="key4" type="text" class="formfld unknown" id="key4" size="30" value="<?=htmlspecialchars($pconfig['key4']);?>" />
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="4" <?php if ($pconfig['txkey'] == 4) echo "checked=\"checked\"";?> />
										</td>
									</tr>
								</table>
								<br />
								<?=gettext("40 (64) bit keys may be entered as 5 ASCII characters or 10 hex digits preceded by '0x'."); ?><br />
								<?=gettext("104 (128) bit keys may be entered as 13 ASCII characters or 26 hex digits preceded by '0x'."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA"); ?></td>
							<td class="vtable">
								<input name="wpa_enable" type="checkbox" class="formfld" id="wpa_enable" value="yes" <?php if ($pconfig['wpa_enable']) echo "checked=\"checked\""; ?> />
								<strong><?=gettext("Enable WPA"); ?></strong>
								<br /><br />
								<table border="0" cellspacing="0" cellpadding="0" summary="wpa">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;<?=gettext("WPA Pre-Shared Key"); ?>&nbsp;</td>
									</tr>
									<tr>
										<td><?=gettext("PSK:"); ?>&nbsp;&nbsp;</td>
										<td>
											<input name="passphrase" type="text" class="formfld unknown" id="passphrase" size="66" value="<?=htmlspecialchars($pconfig['passphrase']);?>" />
										</td>
									</tr>
								</table>
								<br /><?=gettext("Passphrase must be from 8 to 63 characters."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Mode"); ?></td>
							<td class="vtable">
								<select name="wpa_mode" class="formselect" id="wpa_mode">
									<option <?php if ($pconfig['wpa_mode'] == '1') echo "selected=\"selected\"";?> value="1"><?=gettext("WPA"); ?></option>
									<option <?php if ($pconfig['wpa_mode'] == '2') echo "selected=\"selected\"";?> value="2"><?=gettext("WPA2"); ?></option>
									<option <?php if ($pconfig['wpa_mode'] == '3') echo "selected=\"selected\"";?> value="3"><?=gettext("Both"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Key Management Mode"); ?></td>
							<td class="vtable">
								<select name="wpa_key_mgmt" class="formselect" id="wpa_key_mgmt">
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK') echo "selected=\"selected\"";?> value="WPA-PSK"><?=gettext("Pre-Shared Key"); ?></option>
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-EAP') echo "selected=\"selected\"";?> value="WPA-EAP"><?=gettext("Extensible Authentication Protocol"); ?></option>
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK WPA-EAP') echo "selected=\"selected\"";?> value="WPA-PSK WPA-EAP"><?=gettext("Both"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Authentication"); ?></td>
							<td class="vtable">
								<select name="auth_algs" class="formselect" id="auth_algs">
									<option <?php if ($pconfig['auth_algs'] == '1') echo "selected=\"selected\"";?> value="1"><?=gettext("Open System Authentication"); ?></option>
									<option <?php if ($pconfig['auth_algs'] == '2') echo "selected=\"selected\"";?> value="2"><?=gettext("Shared Key Authentication"); ?></option>
									<option <?php if ($pconfig['auth_algs'] == '3') echo "selected=\"selected\"";?> value="3"><?=gettext("Both"); ?></option>
								</select>
								<br /><?=gettext("Note: Shared Key Authentication requires WEP."); ?><br />
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("WPA Pairwise"); ?></td>
							<td class="vtable">
								<select name="wpa_pairwise" class="formselect" id="wpa_pairwise">
									<option <?php if ($pconfig['wpa_pairwise'] == 'CCMP TKIP') echo "selected=\"selected\"";?> value="CCMP TKIP"><?=gettext("Both"); ?></option>
									<option <?php if ($pconfig['wpa_pairwise'] == 'CCMP') echo "selected=\"selected\"";?> value="CCMP"><?=gettext("AES (recommended)"); ?></option>
									<option <?php if ($pconfig['wpa_pairwise'] == 'TKIP') echo "selected=\"selected\"";?> value="TKIP"><?=gettext("TKIP"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Key Rotation"); ?></td>
							<td class="vtable">
								<input name="wpa_group_rekey" type="text" class="formfld unknown" id="wpa_group_rekey" size="30" value="<?php echo $pconfig['wpa_group_rekey'] ? $pconfig['wpa_group_rekey'] : "60";?>" />
								<br /><?=gettext("Allowed values are 1-9999 but should not be longer than Master Key Regeneration time."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Master Key Regeneration"); ?></td>
							<td class="vtable">
								<input name="wpa_gmk_rekey" type="text" class="formfld" id="wpa_gmk_rekey" size="30" value="<?php echo $pconfig['wpa_gmk_rekey'] ? $pconfig['wpa_gmk_rekey'] : "3600";?>" />
								<br /><?=gettext("Allowed values are 1-9999 but should not be shorter than Key Rotation time."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Strict Key Regeneration"); ?></td>
							<td class="vtable">
								<input name="wpa_strict_rekey" type="checkbox" value="yes"  class="formfld" id="wpa_strict_rekey" <?php if ($pconfig['wpa_strict_rekey']) echo "checked=\"checked\""; ?> />
								<br /><?=gettext("Setting this option will force the AP to rekey whenever a client disassociates."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Enable IEEE802.1X Authentication"); ?></td>
							<td class="vtable">
								<input name="ieee8021x" type="checkbox" value="yes"  class="formfld" id="ieee8021x" <?php if ($pconfig['ieee8021x']) echo "checked=\"checked\"";?> />
								<br /><?=gettext("Setting this option will enable 802.1x authentication."); ?>
								<br /><span class="red"><strong><?=gettext("NOTE"); ?>:</strong></span> <?=gettext("this option requires checking the \"Enable WPA box\"."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server IP Address"); ?></td>
							<td class="vtable">
								<input name="auth_server_addr" id="auth_server_addr" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_addr']);?>" />
								<br /><?=gettext("Enter the IP address of the 802.1X Authentication Server.  This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server Port"); ?></td>
							<td class="vtable">
								<input name="auth_server_port" id="auth_server_port" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_port']);?>" />
								<br /><?=gettext("Leave blank for the default 1812 port."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("802.1X Authentication Server Shared Secret"); ?></td>
							<td class="vtable">
								<input name="auth_server_shared_secret" id="auth_server_shared_secret" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_shared_secret']);?>" />
								<br />
							</td>
						</tr>
						<tr>
					<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server IP Address"); ?></td>
							<td class="vtable">
								<input name="auth_server_addr2" id="auth_server_addr2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_addr2']);?>" />
								<br /><?=gettext("Enter the IP address of the 802.1X Authentication Server.  This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server Port"); ?></td>
							<td class="vtable">
								<input name="auth_server_port2" id="auth_server_port2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_port2']);?>" />
								<br /><?=gettext("Leave blank for the default 1812 port."); ?>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell"><?=gettext("Secondary 802.1X Authentication Server Shared Secret"); ?></td>
							<td class="vtable">
								<input name="auth_server_shared_secret2" id="auth_server_shared_secret2" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_shared_secret2']);?>" />
								<br />
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">802.1X <?=gettext("Authentication Roaming Preauth"); ?></td>
							<td class="vtable">
								<input name="rsn_preauth" id="rsn_preauth" type="checkbox" class="formfld unknown" size="66" value="yes" <?php if ($pconfig['rsn_preauth']) echo "checked=\"checked\""; ?> />
								<br />
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<?php endif; ?>
						<tr>
							<td colspan="2" valign="top" class="listtopic"><?=gettext("Private networks"); ?></td>
						</tr>
						<tr>
							<td valign="middle" class="vncell">&nbsp;</td>
							<td class="vtable">
								<a name="rfc1918"></a>
								<input name="blockpriv" type="checkbox" id="blockpriv" value="yes" <?php if ($pconfig['blockpriv']) echo "checked=\"checked\""; ?> />
								<strong><?=gettext("Block private networks"); ?></strong><br />
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
								<input name="blockbogons" type="checkbox" id="blockbogons" value="yes" <?php if ($pconfig['blockbogons']) echo "checked=\"checked\""; ?> />
								<strong><?=gettext("Block bogon networks"); ?></strong><br />
								<?=gettext("When set, this option blocks traffic from IP addresses that are reserved " .
								"(but not RFC 1918) or not yet assigned by IANA."); ?>&nbsp;&nbsp;
								<?=gettext("Bogons are prefixes that should never appear in the Internet routing table, " .
								"and obviously should not appear as the source address in any packets you receive."); ?>
								<br /><br />
								<?=gettext("Note: The update frequency can be changed under System->Advanced Firewall/NAT settings.")?>
							</td>
						</tr>
					</table> <!-- End "allcfg" table -->
					</div> <!-- End "allcfg" div -->

					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="buttons">
						<tr>
							<td width="22%" valign="top">
								&nbsp;
							</td>
							<td width="78%">
								<br />
								<input id="save" name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>" />
								<input id="cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
								<input name="if" type="hidden" id="if" value="<?=htmlspecialchars($if);?>" />
								<?php if ($wancfg['if'] == $a_ppps[$pppid]['if']) : ?>
								<input name="ppp_port" type="hidden" value="<?=htmlspecialchars($pconfig['port']);?>" />
								<?php endif; ?>
								<input name="ptpid" type="hidden" value="<?=htmlspecialchars($pconfig['ptpid']);?>" />
							</td>
						</tr>
					</table>
					</div>
				</td>
			</tr>
		</table>
	</form>
	<script type="text/javascript">
	//<![CDATA[
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
			jQuery('#status').html('');
		}
		function hide_add_gateway_v6() {
			document.getElementById("addgatewayv6").style.display = 'none';
			document.getElementById("addgwboxv6").style.display = '';
			document.getElementById("gatewayv6").style.display = '';
			document.getElementById("save").style.display = '';
			document.getElementById("cancel").style.display = '';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			jQuery('#statusv6').html('');
		}
		function hide_add_gatewaysave() {
			document.getElementById("addgateway").style.display = 'none';
			jQuery('#status').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" /> One moment please...');
			var iface = jQuery('#if').val();
			name = jQuery('#name').val();
			var descr = jQuery('#gatewaydescr').val();
			gatewayip = jQuery('#gatewayip').val();

			var defaultgw = '';
			if (jQuery('#defaultgw').is(':checked'))
				defaultgw = '&defaultgw=on';
			var url = "system_gateways_edit.php";
			var pars = 'isAjax=true&ipprotocol=inet' + defaultgw + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip);
			jQuery.ajax(
				url,
				{
					type: 'post',
					data: pars,
					error: report_failure,
					success: save_callback
				});
		}
		function hide_add_gatewaysave_v6() {
			document.getElementById("addgatewayv6").style.display = 'none';
			jQuery('#statusv6').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" /> One moment please...');
			var iface = jQuery('#if').val();
			name = jQuery('#namev6').val();
			var descr = jQuery('#gatewaydescrv6').val();
			gatewayip = jQuery('#gatewayipv6').val();
			var defaultgw = '';
			if (jQuery('#defaultgwv6').is(':checked'))
				defaultgw = '&defaultgw=on';
			var url_v6 = "system_gateways_edit.php";
			var pars_v6 = 'isAjax=true&ipprotocol=inet6' + defaultgw + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip);
			jQuery.ajax(
				url_v6,
				{
					type: 'post',
					data: pars_v6,
					error: report_failure_v6,
					success: save_callback_v6
				});
		}
		function addOption(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.append(optn);
			selectbox.prop('selectedIndex',selectbox.children().length-1);
			jQuery('#notebox').html("<p><strong><?=gettext("NOTE:"); ?><\/strong> <?=gettext("You can manage Gateways"); ?> <a target='_blank' href='system_gateways.php'><?=gettext("here"); ?><\/a>.<\/p>");
		}
		function addOption_v6(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.append(optn);
			selectbox.prop('selectedIndex',selectbox.children().length-1);
			jQuery('#noteboxv6').html("<p><strong><?=gettext("NOTE:"); ?><\/strong> <?=gettext("You can manage Gateways"); ?> <a target='_blank' href='system_gateways.php'><?=gettext("here"); ?><\/a>.<\/p>");
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
		function save_callback(response) {
			if(response) {
				document.getElementById("addgateway").style.display = 'none';
				hide_add_gateway();
				var gwtext = escape(name) + " - " + gatewayip;
				addOption(jQuery('#gateway'), gwtext, name);
				// Auto submit form?
				//document.iform.submit();
				//jQuery('#status').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader /">');
			} else {
				report_failure();
			}
		}
		function show_advanced_media() {
			document.getElementById("showadvmediabox").innerHTML='';
			aodiv = document.getElementById('showmediaadv');
			aodiv.style.display = "block";
		}
		function save_callback_v6(response_v6) {
			if(response_v6) {
				document.getElementById("addgatewayv6").style.display = 'none';
				hide_add_gateway_v6();
				var gwtext_v6 = escape(name) + " - " + gatewayip;
				addOption_v6(jQuery('#gatewayv6'), gwtext_v6, name);
				// Auto submit form?
				//document.iform.submit();
				//jQuery('#statusv6').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" />');
			} else {
				report_failure_v6();
			}
		}
		<?php
		echo "show_allcfg(document.iform.enable);";
		echo "updateType('{$pconfig['type']}');\n";
		echo "updateTypeSix('{$pconfig['type6']}');\n";
		?>
	//]]>
	</script>
	<?php include("fend.inc"); ?>
	</body>
</html>
