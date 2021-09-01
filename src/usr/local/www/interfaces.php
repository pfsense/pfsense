<?php
/*
 * interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Daniel S. Haischt
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-interfaces
##|*NAME=Interfaces: WAN
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

function remove_bad_chars($string) {
	return preg_replace('/[^a-z_0-9]/i', '', $string);
}

define("ANTENNAS", false);

if (isset($_POST['referer'])) {
	$referer = $_POST['referer'];
} else {
	$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/interfaces.php');
}

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr(true);

$if = "wan";

if ($_REQUEST['if']) {
	$if = $_REQUEST['if'];
}

if (empty($ifdescrs[$if])) {
	header("Location: interfaces.php");
	exit;
}

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($pconfig)) {
	$pconfig = array();
}

init_config_arr(array('ppps', 'ppp'));
$a_ppps = &$config['ppps']['ppp'];

init_config_arr(array('gateways', 'gateway_item'));
$a_gateways = &$config['gateways']['gateway_item'];

$interfaces = get_configured_interface_with_descr();
/* Interfaces which have addresses configured elsewhere and should not be
 * configured here. See https://redmine.pfsense.org/issues/8687 */
$no_address_interfaces = array("ovpn", "ipsec", "gif", "gre");
$show_address_controls = true;
$realifname = get_real_interface($if);
foreach ($no_address_interfaces as $ifbl) {
	if (substr($realifname, 0, strlen($ifbl)) == $ifbl) {
		$show_address_controls = false;
	}
}

init_config_arr(array('interfaces', $if));
$wancfg = &$config['interfaces'][$if];
$old_wancfg = $wancfg;
$old_wancfg['realif'] = get_real_interface($if);
$old_ppps = $a_ppps;

// Populate page descr if it does not exist.
if ($if == "wan" && !$wancfg['descr']) {
	$wancfg['descr'] = "WAN";
} else if ($if == "lan" && !$wancfg['descr']) {
	$wancfg['descr'] = "LAN";
}

/* NOTE: The code here is used to set the $pppid for the curious */
foreach ($a_ppps as $pppid => $ppp) {
	if ($wancfg['if'] == $ppp['if']) {
		break;
	}
}

$type_disabled = (substr($wancfg['if'], 0, 3) == 'gre') ? 'disabled' : '';

if ($wancfg['if'] == $a_ppps[$pppid]['if']) {
	$pconfig['pppid'] = $pppid;
	$pconfig['ptpid'] = $a_ppps[$pppid]['ptpid'];
	$pconfig['port'] = $a_ppps[$pppid]['ports'];
	if ($a_ppps[$pppid]['type'] == "ppp") {
		$pconfig['ppp_username'] = $a_ppps[$pppid]['username'];
		$pconfig['ppp_password'] = base64_decode($a_ppps[$pppid]['password']);

		$pconfig['phone'] = $a_ppps[$pppid]['phone'];
		$pconfig['apn'] = $a_ppps[$pppid]['apn'];
	} elseif ($a_ppps[$pppid]['type'] == "pppoe") {
		$pconfig['pppoe_username'] = $a_ppps[$pppid]['username'];
		$pconfig['pppoe_password'] = base64_decode($a_ppps[$pppid]['password']);
		$pconfig['provider'] = $a_ppps[$pppid]['provider'];
		$pconfig['hostuniq'] = $a_ppps[$pppid]['hostuniq'];
		$pconfig['pppoe_dialondemand'] = isset($a_ppps[$pppid]['ondemand']);
		$pconfig['pppoe_idletimeout'] = $a_ppps[$pppid]['idletimeout'];

		/* ================================================ */
		/* = force a connection reset at a specific time? = */
		/* ================================================ */

		if (isset($a_ppps[$pppid]['pppoe-reset-type'])) {
			$pconfig['pppoe-reset-type'] = $a_ppps[$pppid]['pppoe-reset-type'];
			$itemhash = getMPDCRONSettings($a_ppps[$pppid]['if']);
			if ($itemhash) {
				$cronitem = $itemhash['ITEM'];
			}
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
					if ($cronitem['mday'] != "*" && $cronitem['month'] != "*") {
						$pconfig['pppoe_resetdate'] = "{$cronitem['month']}/{$cronitem['mday']}/" . date("Y");
					}
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
		} // End force pppoe reset at specific time
		// End if type == pppoe
	} else if ($a_ppps[$pppid]['type'] == "pptp" || $a_ppps[$pppid]['type'] == "l2tp") {
		$pconfig['pptp_username'] = $a_ppps[$pppid]['username'];
		$pconfig['pptp_password'] = base64_decode($a_ppps[$pppid]['password']);
		if (($a_ppps[$pppid]['type'] == 'l2tp') && isset($a_ppps[$pppid]['secret'])) {
			$pconfig['l2tp_secret'] = base64_decode($a_ppps[$pppid]['secret']);
		}
		$pconfig['pptp_localip'] = explode(",", $a_ppps[$pppid]['localip']);
		$pconfig['pptp_subnet'] = explode(",", $a_ppps[$pppid]['subnet']);
		$pconfig['pptp_remote'] = explode(",", $a_ppps[$pppid]['gateway']);
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
$pconfig['adv_dhcp6_prefix_selected_interface'] = $wancfg['adv_dhcp6_prefix_selected_interface'];

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

switch ($wancfg['ipaddr']) {
	case "dhcp":
		$pconfig['type'] = "dhcp";
		$pconfig['dhcpvlanenable'] = isset($wancfg['dhcpvlanenable']);
		$pconfig['dhcpcvpt'] = $wancfg['dhcpcvpt'];
		break;
	case "pppoe":
	case "pptp":
	case "l2tp":
	case "ppp":
		$pconfig['type'] = $wancfg['ipaddr'];
		break;
	default:
		if (is_ipaddrv4($wancfg['ipaddr'])) {
			$pconfig['type'] = "staticv4";
			$pconfig['ipaddr'] = $wancfg['ipaddr'];
			$pconfig['subnet'] = $wancfg['subnet'];
			$pconfig['gateway'] = $wancfg['gateway'];
		} elseif (in_array(remove_ifindex($wancfg['if']), array("ppp", "pppoe", "pptp", "l2tp"))) {
			$pconfig['type'] = remove_ifindex($wancfg['if']);
		} else {
			$pconfig['type'] = "none";
		}
		break;
}

switch ($wancfg['ipaddrv6']) {
	case "slaac":
		$pconfig['type6'] = "slaac";
		$pconfig['slaacusev4iface'] = isset($wancfg['slaacusev4iface']);
		break;
	case "dhcp6":
		$pconfig['dhcp6-duid'] = $wancfg['dhcp6-duid'];
		if (!isset($wancfg['dhcp6-ia-pd-len'])) {
			$wancfg['dhcp6-ia-pd-len'] = "none";
		}
		$pconfig['dhcp6-ia-pd-len'] = $wancfg['dhcp6-ia-pd-len'];
		$pconfig['dhcp6-ia-pd-send-hint'] = isset($wancfg['dhcp6-ia-pd-send-hint']);
		$pconfig['type6'] = "dhcp6";
		$pconfig['dhcp6prefixonly'] = isset($wancfg['dhcp6prefixonly']);
		$pconfig['dhcp6usev4iface'] = isset($wancfg['dhcp6usev4iface']);
		$pconfig['dhcp6debug'] = isset($wancfg['dhcp6debug']);
		$pconfig['dhcp6withoutra'] = isset($wancfg['dhcp6withoutra']);
		$pconfig['dhcp6norelease'] = isset($wancfg['dhcp6norelease']);
		$pconfig['dhcp6vlanenable'] = isset($wancfg['dhcp6vlanenable']);
		$pconfig['dhcp6cvpt'] = $wancfg['dhcp6cvpt'];
		break;
	case "6to4":
		$pconfig['type6'] = "6to4";
		break;
	case "track6":
		$pconfig['type6'] = "track6";
		$pconfig['track6-interface'] = $wancfg['track6-interface'];
		if ($wancfg['track6-prefix-id'] == "") {
			$pconfig['track6-prefix-id'] = 0;
		} else {
			$pconfig['track6-prefix-id'] = $wancfg['track6-prefix-id'];
		}
		$pconfig['track6-prefix-id--hex'] = sprintf("%x", $pconfig['track6-prefix-id']);
		break;
	case "6rd":
		$pconfig['prefix-6rd'] = $wancfg['prefix-6rd'];
		if ($wancfg['prefix-6rd-v4plen'] == "") {
			$wancfg['prefix-6rd-v4plen'] = "0";
		}
		$pconfig['prefix-6rd-v4plen'] = $wancfg['prefix-6rd-v4plen'];
		$pconfig['type6'] = "6rd";
		$pconfig['gateway-6rd'] = $wancfg['gateway-6rd'];
		break;
	default:
		if (is_ipaddrv6($wancfg['ipaddrv6'])) {
			$pconfig['type6'] = "staticv6";
			$pconfig['ipv6usev4iface'] = isset($wancfg['ipv6usev4iface']);
			$pconfig['ipaddrv6'] = $wancfg['ipaddrv6'];
			$pconfig['subnetv6'] = $wancfg['subnetv6'];
			$pconfig['gatewayv6'] = $wancfg['gatewayv6'];
		} else {
			$pconfig['type6'] = "none";
		}
		break;
}

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
	if (!does_interface_exist($wlanif)) {
		interface_wireless_clone($wlanif, $wancfg);
	}
	$wlanbaseif = interface_get_wireless_base($wancfg['if']);
	preg_match("/^(.*?)([0-9]*)$/", $wlanbaseif, $wlanbaseif_split);
	$wl_modes = get_wireless_modes($if);
	$wl_ht_modes = get_wireless_ht_modes($if);
	$wl_ht_list = get_wireless_ht_list($if);
	$wl_chaninfo = get_wireless_channel_info($if);
	$wl_sysctl_prefix = 'dev.' . $wlanbaseif_split[1] . '.' . $wlanbaseif_split[2];
	$wl_sysctl = get_sysctl(
		array(
			"{$wl_sysctl_prefix}.diversity",
			"{$wl_sysctl_prefix}.txantenna",
			"{$wl_sysctl_prefix}.rxantenna",
			"{$wl_sysctl_prefix}.slottime",
			"{$wl_sysctl_prefix}.acktimeout",
			"{$wl_sysctl_prefix}.ctstimeout"));
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
	$pconfig['channel_width'] = $wancfg['wireless']['channel_width'];
	$pconfig['txpower'] = $wancfg['wireless']['txpower'];
	$pconfig['diversity'] = $wancfg['wireless']['diversity'];
	$pconfig['txantenna'] = $wancfg['wireless']['txantenna'];
	$pconfig['rxantenna'] = $wancfg['wireless']['rxantenna'];
	$pconfig['distance'] = $wancfg['wireless']['distance'];
	$pconfig['regdomain'] = $wancfg['wireless']['regdomain'];
	$pconfig['regcountry'] = $wancfg['wireless']['regcountry'];
	$pconfig['reglocation'] = $wancfg['wireless']['reglocation'];
	$pconfig['wme_enable'] = isset($wancfg['wireless']['wme']['enable']);
	if (isset($wancfg['wireless']['puren']['enable'])) {
		$pconfig['puremode'] = '11n';
	} else if (isset($wancfg['wireless']['pureg']['enable'])) {
		$pconfig['puremode'] = '11g';
	} else {
		$pconfig['puremode'] = 'any';
	}
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
		$pconfig['wpa_eap_client_mode'] = $wancfg['wireless']['wpa']['wpa_eap_client_mode'];
		$pconfig['wpa_eap_inner_auth'] = $wancfg['wireless']['wpa']['wpa_eap_inner_auth'];
		$pconfig['wpa_eap_inner_id'] = $wancfg['wireless']['wpa']['wpa_eap_inner_id'];
		$pconfig['wpa_eap_inner_password'] = base64_decode($wancfg['wireless']['wpa']['wpa_eap_inner_password']);
		$pconfig['wpa_eap_cert'] = $wancfg['wireless']['wpa']['wpa_eap_cert'];
		$pconfig['wpa_eap_ca'] = $wancfg['wireless']['wpa']['wpa_eap_ca'];
	}

	$pconfig['mac_acl'] = $wancfg['wireless']['mac_acl'];

}

$changes_applied = false;

if ($_POST['apply']) {
	unset($input_errors);
	if (!is_subsystem_dirty('interfaces')) {
		$input_errors[] = gettext("The settings have already been applied!");
	} else {
		$retval = 0;
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		clear_subsystem_dirty('interfaces');

		$vlan_redo = array();
		if (file_exists("{$g['tmp_path']}/.interfaces.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
			foreach ($toapplylist as $ifapply => $ifcfgo) {
				$ifmtu = get_interface_mtu(get_real_interface($ifapply));
				if (isset($config['interfaces'][$ifapply]['enable'])) {
					interface_bring_down($ifapply, false, $ifcfgo);
					interface_configure($ifapply, true);
					if ($config['interfaces'][$ifapply]['ipaddrv6'] == "track6") {
						/* call interface_track6_configure with linkup true so
						   IPv6 IPs are added back. dhcp6c needs a HUP. Can't
						   just call interface_configure with linkup true as
						   that skips bridge membership addition.
						*/
						$wancfg = $config['interfaces'][$ifapply];
						interface_track6_configure($ifapply, $wancfg, true);
					}
				} else {
					interface_bring_down($ifapply, true, $ifcfgo);
					if (isset($config['dhcpd'][$ifapply]['enable']) ||
					    isset($config['dhcpdv6'][$ifapply]['enable'])) {
						services_dhcpd_configure();
					}
				}
				if (interface_has_clones(get_real_interface($ifapply)) &&
				    (isset($config['interfaces'][$ifapply]['mtu']) &&
				    ($config['interfaces'][$ifapply]['mtu'] != $ifmtu)) ||
				    (!isset($config['interfaces'][$ifapply]['mtu']) &&
				    (get_interface_default_mtu() != $ifmtu))) { 
					$vlan_redo[] = get_real_interface($ifapply);
				}
			}
		}

		/*
                 * If the parent interface has changed MTU above, the VLANs needs to be
                 * redone.
		 */
		if (!empty($vlan_redo)) {
			foreach ($vlan_redo as $vlredo) {
				interfaces_vlan_configure_mtu($vlredo);
			}
		}

		/* restart snmp so that it binds to correct address */
		$retval |= services_snmpd_configure();

		/* sync filter configuration */
		setup_gateways_monitor();

		clear_subsystem_dirty('interfaces');

		$retval |= filter_configure();

		enable_rrd_graphing();

		$changes_applied = true;

		if (is_subsystem_dirty('staticroutes') && (system_routing_configure() == 0)) {
			clear_subsystem_dirty('staticroutes');
		}

		init_config_arr(array('syslog'));
		if (isset($config['syslog']['enable']) && ($ifapply == $config['syslog']['sourceip'])) {
			system_syslogd_start();
		}
	}
	@unlink("{$g['tmp_path']}/.interfaces.apply");
} else if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	if (ctype_xdigit($_POST['track6-prefix-id--hex'])) {
		$pconfig['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
	} else {
		$pconfig['track6-prefix-id'] = 0;
	}

	/* filter out spaces from descriptions */
	$_POST['descr'] = remove_bad_chars($_POST['descr']);

	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields related to PPPoE resets, we are going to unset $_POST
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

	/* input validation */
	$reqdfields = explode(" ", "descr");
	$reqdfieldsn = array(gettext("Description"));
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		/* description unique? */
		foreach ($ifdescrs as $ifent => $ifdescr) {
			if ($if != $ifent && (strcasecmp($ifdescr, $_POST['descr']) == 0)) {
				$input_errors[] = gettext("An interface with the specified description already exists.");
				break;
			}
		}

		/* Is the description already used as an alias name? */
		if (is_array($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if (strcasecmp($alias['name'], $_POST['descr']) == 0) {
					$input_errors[] = sprintf(gettext("Sorry, an alias with the name %s already exists."), $_POST['descr']);
				}
			}
		}

		/* Is the description already used as an interface group name? */
		if (is_array($config['ifgroups']['ifgroupentry'])) {
			foreach ($config['ifgroups']['ifgroupentry'] as $ifgroupentry) {
				if (strcasecmp($ifgroupentry['ifname'], $_POST['descr']) == 0) {
					$input_errors[] = sprintf(gettext("Sorry, an interface group with the name %s already exists."), $_POST['descr']);
				}
			}
		}

		if (is_numeric($_POST['descr'])) {
			$input_errors[] = gettext("The interface description cannot contain only numbers.");
		}

		if ((strlen(trim($_POST['descr'])) > 25) && ((substr($realifname, 0, 4) == 'ovpn') ||
		    (substr($realifname, 0, 5) == 'ipsec'))) {
			$input_errors[] = gettext("The OpenVPN and VTI interface description must be less than 26 characters long.");
		}

		if ((strlen(trim($_POST['descr'])) > 22) && ((substr($realifname, 0, 3) == 'gif') ||
		    (substr($realifname, 0, 3) == 'gre'))) {
			$input_errors[] = gettext("The GIF and GRE interface description must be less than 23 characters long.");
		}

		/*
		 * Packages (e.g. tinc) create interface groups, reserve this
		 * namespace pkg_ for them.
		 * One namespace is shared by Interfaces, Interface Groups and Aliases.
		 */
		if (substr($_POST['descr'], 0, 4) == 'pkg_') {
			$input_errors[] = gettext("The interface description cannot start with pkg_");
		}
	}

	if ($_POST['blockbogons'] == "yes" &&
	    isset($config['system']['ipv6allow']) &&
	    (!isset($config['system']['maximumtableentries']) ||
	     $config['system']['maximumtableentries'] <
	     $g['minimumtableentries_bogonsv6'])) {
		$input_errors[] = sprintf(gettext(
		    "In order to block bogon networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s."),
		    $g['minimumtableentries_bogonsv6']);
	}

	if (isset($config['dhcpd']) && isset($config['dhcpd'][$if]['enable'])) {
		if (!preg_match("/^staticv4/", $_POST['type'])) {
			$input_errors[] = gettext("The DHCP Server is active " .
			    "on this interface and it can be used only with " .
			    "a static IP configuration. Please disable the " .
			    "DHCP Server service on this interface first, " .
			    "then change the interface configuration.");
		} elseif (!empty($_POST['subnet']) && $_POST['subnet'] >= 31) {
			$input_errors[] = gettext("The DHCP Server is active " .
			    "on this interface and it can be used only with " .
			    "IPv4 subnet < 31. Please disable the " .
			    "DHCP Server service on this interface first, " .
			    "then change the interface configuration.");
		}
	}
	if (isset($config['dhcpdv6']) && ($_POST['type6'] != "staticv6" && $_POST['type6'] != "track6")) {
		if (isset($config['dhcpdv6'][$if]['enable'])) {
			$input_errors[] = gettext("The DHCP6 Server is active on this interface and it can be used only " .
			    "with a static IPv6 configuration. Please disable the DHCPv6 Server service on this " .
			    "interface first, then change the interface configuration.");
		}
		if (isset($config['dhcpdv6'][$if]['ramode']) && ($config['dhcpdv6'][$if]['ramode'] != "disabled")) {
			$input_errors[] = gettext("The Router Advertisements Server is active on this interface and it can " .
			    "be used only with a static IPv6 configuration. Please disable the Router Advertisements " .
			    "Server service on this interface first, then change the interface configuration.");
		}
	}

	switch (strtolower($_POST['type'])) {
		case "staticv4":
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = array(gettext("IPv4 address"), gettext("Subnet bit count"), gettext("Gateway"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			if (is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if (is_ipaddrv4($vip['subnet']) && $vip['interface'] == $if) {
						$input_errors[] = gettext("This interface is referenced by IPv4 VIPs. Please delete those before setting the interface to 'none' configuration.");
					}
				}
			}
			break;
		case "ppp":
			$reqdfields = explode(" ", "port phone");
			$reqdfieldsn = array(gettext("Modem Port"), gettext("Phone Number"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pppoe":
			if ($_POST['pppoe_dialondemand']) {
				$reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
				$reqdfieldsn = array(gettext("PPPoE username"), gettext("PPPoE password"), gettext("Dial on demand"), gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pppoe_username pppoe_password");
				$reqdfieldsn = array(gettext("PPPoE username"), gettext("PPPoE password"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pptp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local0 pptp_subnet0 pptp_remote0 pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("PPTP username"), gettext("PPTP password"), gettext("PPTP local IP address"), gettext("PPTP subnet"), gettext("PPTP remote IP address"), gettext("Dial on demand"), gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local0 pptp_subnet0 pptp_remote0");
				$reqdfieldsn = array(gettext("PPTP username"), gettext("PPTP password"), gettext("PPTP local IP address"), gettext("PPTP subnet"), gettext("PPTP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "l2tp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote0 pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("L2TP username"), gettext("L2TP password"), gettext("L2TP remote IP address"), gettext("Dial on demand"), gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote0");
				$reqdfieldsn = array(gettext("L2TP username"), gettext("L2TP password"), gettext("L2TP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
	}
	switch (strtolower($_POST['type6'])) {
		case "staticv6":
			$reqdfields = explode(" ", "ipaddrv6 subnetv6 gatewayv6");
			$reqdfieldsn = array(gettext("IPv6 address"), gettext("Subnet bit count"), gettext("Gateway"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			if (is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if (is_ipaddrv6($vip['subnet']) && $vip['interface'] == $if) {
						$input_errors[] = gettext("This interface is referenced by IPv6 VIPs. Please delete those before setting the interface to 'none' configuration.");
					}
				}
			}
			break;
		case "dhcp6":
			if (in_array($wancfg['ipaddrv6'], array())) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			if ($_POST['dhcp6-ia-pd-send-hint'] && strtolower($_POST['dhcp6-ia-pd-len']) == 'none') {
				$input_errors[] = gettext('DHCPv6 Prefix Delegation size must be provided when Send IPv6 prefix hint flag is checked');
			}
			break;
		case "6rd":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if ($if != $ifent && ($config['interfaces'][$ifent]['ipaddrv6'] == $_POST['type6'])) {
					if ($config['interfaces'][$ifent]['prefix-6rd'] == $_POST['prefix-6rd']) {
						$input_errors[] = gettext("Only one interface can be configured within a single 6rd prefix.");
						break;
					}
				}
			}
			if (!is_ipaddrv4($_POST['gateway-6rd'])) {
				$input_errors[] = gettext("6RD Border Relay must be an IPv4 address.");
			}
			if (in_array($wancfg['ipaddrv6'], array())) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			break;
		case "6to4":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if ($if != $ifent && ($config['interfaces'][$ifent]['ipaddrv6'] == $_POST['type6'])) {
					$input_errors[] = sprintf(gettext("Only one interface can be configured as 6to4."), $_POST['type6']);
					break;
				}
			}
			if (in_array($wancfg['ipaddrv6'], array())) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			break;
		case "track6":
			/* needs to check if $track6-prefix-id is used on another interface */
			if (in_array($wancfg['ipaddrv6'], array())) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}

			if (empty($_POST['track6-interface'])) {
				$input_errors[] = gettext("A valid interface to track must be selected.");
			}

			if ($_POST['track6-prefix-id--hex'] != "" && !ctype_xdigit($_POST['track6-prefix-id--hex'])) {
				$input_errors[] = gettext("A valid hexadecimal number must be entered for the IPv6 prefix ID.");
			} else {
				$track6_prefix_id = intval($_POST['track6-prefix-id--hex'], 16);
				if ($track6_prefix_id < 0 || $track6_prefix_id > $_POST['ipv6-num-prefix-ids-' . $_POST['track6-interface']]) {
					$input_errors[] = gettext("The specified IPv6 Prefix ID is out of range.") .
						" ({$_POST['track6-interface']}) - (0) - (" . sprintf('%x', $_POST['ipv6-num-prefix-ids-' . $_POST['track6-interface']]) . ")";
				} else {
					foreach ($ifdescrs as $ifent => $ifdescr) {
						if ($if == $ifent) {
							continue;
						}
						if ($config['interfaces'][$ifent]['ipaddrv6'] == 'track6' &&
						    $config['interfaces'][$ifent]['track6-interface'] == $_POST['track6-interface'] &&
						    $config['interfaces'][$ifent]['track6-prefix-id'] == $track6_prefix_id) {
							$input_errors[] = sprintf(gettext("This track6 prefix ID is already being used in %s."), $ifdescr);
						}
					}
				}
			}
			break;
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$staticroutes = get_staticroutes(true);
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));
	if (($_POST['type'] == 'staticv4') && $_POST['ipaddr']) {
		if (!is_ipaddrv4($_POST['ipaddr'])) {
			$input_errors[] = gettext("A valid IPv4 address must be specified.");
		} else {
			$where_ipaddr_configured = where_is_ipaddr_configured($_POST['ipaddr'], $if, true, true, $_POST['subnet']);
			if (count($where_ipaddr_configured)) {
				$subnet_conflict_text = sprintf(gettext("IPv4 address %s is being used by or overlaps with:"), $_POST['ipaddr'] . "/" . $_POST['subnet']);
				foreach ($where_ipaddr_configured as $subnet_conflict) {
					$subnet_conflict_text .= " " . convert_friendly_interface_to_friendly_descr($subnet_conflict['if']) . " (" . $subnet_conflict['ip_or_subnet'] . ")";
				}
				$input_errors[] = $subnet_conflict_text;
			}

			/* Do not accept network or broadcast address, except if subnet is 31 or 32 */
			if ($_POST['subnet'] < 31) {
				if ($_POST['ipaddr'] == gen_subnet($_POST['ipaddr'], $_POST['subnet'])) {
					$input_errors[] = gettext("This IPv4 address is the network address and cannot be used");
				} else if ($_POST['ipaddr'] == gen_subnet_max($_POST['ipaddr'], $_POST['subnet'])) {
					$input_errors[] = gettext("This IPv4 address is the broadcast address and cannot be used");
				}
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
	if (($_POST['type6'] == 'staticv6') && $_POST['ipaddrv6']) {
		$_POST['ipaddrv6'] = addrtolower($_POST['ipaddrv6']);

		if (!is_ipaddrv6($_POST['ipaddrv6'])) {
			$input_errors[] = gettext("A valid IPv6 address must be specified.");
		} else {
			if (ip_in_subnet($_POST['ipaddrv6'], "fe80::/10")) {
				$input_errors[] = gettext("IPv6 link local addresses cannot be configured as an interface IP.");
			}
			$where_ipaddr_configured = where_is_ipaddr_configured($_POST['ipaddrv6'], $if, true, true, $_POST['subnetv6']);
			if (count($where_ipaddr_configured)) {
				$subnet_conflict_text = sprintf(gettext("IPv6 address %s is being used by or overlaps with:"), $_POST['ipaddrv6'] . "/" . $_POST['subnetv6']);
				foreach ($where_ipaddr_configured as $subnet_conflict) {
					$subnet_conflict_text .= " " . convert_friendly_interface_to_friendly_descr($subnet_conflict['if']) . " (" . $subnet_conflict['ip_or_subnet'] . ")";
				}
				$input_errors[] = $subnet_conflict_text;
			}

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
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) {
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	}
	if (($_POST['subnetv6'] && !is_numeric($_POST['subnetv6']))) {
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	}
	if (($_POST['alias-address'] && !is_ipaddrv4($_POST['alias-address']))) {
		$input_errors[] = gettext("A valid alias IP address must be specified.");
	}
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet']))) {
		$input_errors[] = gettext("A valid alias subnet bit count must be specified.");
	}
	if ($_POST['dhcprejectfrom'] && !validate_ipv4_list($_POST['dhcprejectfrom'])) {
		$input_errors[] = gettext("An invalid IP address was detected in the 'Reject leases from' field.");
	}

	// Only check the IPv4 gateway already exists if it is not "none" and it is not a gateway that the user is adding
	if (($_POST['gateway'] != "none") && (!$_POST['gatewayip4'] || ($_POST['gateway'] != $_POST['gatewayname4']))) {
		$match = false;
		foreach ($a_gateways as $gateway) {
			if (in_array($_POST['gateway'], $gateway)) {
				$match = true;
			}
		}
		if (!$match) {
			$input_errors[] = gettext("A valid IPv4 gateway must be specified.");
		}
	}
	// Only check the IPv6 gateway already exists if it is not "none" and it is not a gateway that the user is adding
	if (($_POST['gatewayv6'] != "none") && (!$_POST['gatewayip6'] || ($_POST['gatewayv6'] != $_POST['gatewayname6']))) {
		$match = false;
		foreach ($a_gateways as $gateway) {
			if (in_array($_POST['gatewayv6'], $gateway)) {
				$match = true;
			}
		}
		if (!$match) {
			$input_errors[] = gettext("A valid IPv6 gateway must be specified.");
		}
	}

	if ($_POST['provider'] && strpos($_POST['provider'], "\"")) {
		$input_errors[] = gettext("The service name may not contain quote characters.");
	}
	if ($_POST['hostuniq'] && !preg_match('/^[a-zA-Z0-9]+$/i', $_POST['hostuniq'])) {
		$input_errors[] = gettext("The Host-Uniq value can only be hexadecimal or letters and numbers.");
	}
	if (($_POST['pppoe_idletimeout'] != "") && !is_numericint($_POST['pppoe_idletimeout'])) {
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	}
	if ($_POST['pppoe_resethour'] != "" && !is_numericint($_POST['pppoe_resethour']) &&
	    $_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) {
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	}
	if ($_POST['pppoe_resetminute'] != "" && !is_numericint($_POST['pppoe_resetminute']) &&
	    $_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) {
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	}
	if ($_POST['pppoe_resetdate'] != "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) {
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	}
	if (($_POST['pptp_local0'] && !is_ipaddrv4($_POST['pptp_local0']))) {
		$input_errors[] = gettext("A valid PPTP local IP address must be specified.");
	}
	if (($_POST['pptp_subnet0'] && !is_numeric($_POST['pptp_subnet0']))) {
		$input_errors[] = gettext("A valid PPTP subnet bit count must be specified.");
	}
	if (($_POST['pptp_remote0'] && !is_ipaddrv4($_POST['pptp_remote0']) && !is_hostname($_POST['pptp_remote0']))) {
		$input_errors[] = gettext("A valid PPTP remote IP address must be specified.");
	}
	if (($_POST['pptp_idletimeout'] != "") && !is_numericint($_POST['pptp_idletimeout'])) {
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	}
	if (($_POST['spoofmac'] && !is_macaddr($_POST['spoofmac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if ($_POST['mtu']) {
		if (!is_numericint($_POST['mtu'])) {
			$input_errors[] = "MTU must be an integer.";
		}
		if (substr($wancfg['if'], 0, 3) == 'gif') {
			$min_mtu = 1280;
			$max_mtu = 8192;
		} elseif (($_POST['ipaddrv6'] == '6rd') || ($_POST['ipaddrv6'] == '6to4')) {
			$min_mtu = 1300;
			$max_mtu = 9000;
		} else {
			$min_mtu = 576;
			$max_mtu = 9000;
		}

		if ($_POST['mtu'] < $min_mtu || $_POST['mtu'] > $max_mtu) {
			$input_errors[] = sprintf(gettext("The MTU must be between %d and %d bytes."), $min_mtu, $max_mtu);
		}

		unset($min_mtu, $max_mtu);

		if (interface_is_vlan($wancfg['if']) != NULL) {
			$realhwif_array = get_parent_interface($wancfg['if']);
			// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
			$parent_realhwif = $realhwif_array[0];
			$parent_if = convert_real_interface_to_friendly_interface_name($parent_realhwif);
			$mtu = 0;
			if (!empty($parent_if) && !empty($config['interfaces'][$parent_if]['mtu']))
				$mtu = intval($config['interfaces'][$parent_if]['mtu']);
			if ($mtu == 0)
				$mtu = get_interface_mtu($parent_realhwif);
			if ($_POST['mtu'] > $mtu)
				$input_errors[] = gettext("The MTU of a VLAN cannot be greater than that of its parent interface.");
		} else {
			foreach ($config['interfaces'] as $idx => $ifdata) {
				if (($idx == $if) || interface_is_vlan($ifdata['if']) == NULL) {
					continue;
				}

				$realhwif_array = get_parent_interface($ifdata['if']);
				// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
				$parent_realhwif = $realhwif_array[0];

				if ($parent_realhwif != $wancfg['if']) {
					continue;
				}

				if (isset($ifdata['mtu']) && $ifdata['mtu'] > $_POST['mtu']) {
					$input_errors[] = sprintf(gettext("Interface %s (VLAN) has MTU set to a larger value."), $ifdata['descr']);
				}
			}
		}
	}
	if ($_POST['mss'] != '') {
		if (!is_numericint($_POST['mss']) || ($_POST['mss'] < 576 || $_POST['mss'] > 65535)) {
			$input_errors[] = gettext("The MSS must be an integer between 576 and 65535 bytes.");
		}
	}
	/* Wireless interface? */
	if (isset($wancfg['wireless'])) {
		$reqdfields = array("mode");
		$reqdfieldsn = array(gettext("Mode"));
		if ($_POST['mode'] == 'hostap') {
			$reqdfields[] = "ssid";
			$reqdfieldsn[] = gettext("SSID");
			if (isset($_POST['channel']) && $_POST['channel'] == "0") {
				// auto channel with hostap is broken, prevent this for now.
				$input_errors[] = gettext("A specific channel, not auto, must be selected for Access Point mode.");
			}
		}
		if (!stristr($_POST['standard'], '11n') && ($_POST['channel_width'] != "0")) {
			$input_errors[] = gettext("Channel width selection is only supported by 802.11n standards.");
		}
		if (stristr($_POST['standard'], '11n')) {
			if (!($_POST['wme_enable'])) {
				$input_errors[] = gettext("802.11n standards require enabling WME.");
			}
			if (($_POST['channel_width'] != "0") && ($_POST['channel'] != "0") &&
			    is_array($wl_ht_list[$_POST['standard']][$_POST['channel']]) &&
			    !empty($wl_ht_list[$_POST['standard']][$_POST['channel']]) &&
			    !in_array($_POST['channel_width'], $wl_ht_list[$_POST['standard']][$_POST['channel']])) {
				$input_errors[] = sprintf(gettext("Unable to use %s channel width with channel %s."), strtoupper($_POST['channel_width']), $_POST['channel']);
			}
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		check_wireless_mode();
		if (isset($_POST['wpa_group_rekey']) && (!is_numericint($_POST['wpa_group_rekey']) || $_POST['wpa_group_rekey'] < 1 || $_POST['wpa_group_rekey'] > 9999)) {
			$input_errors[] = gettext("Key Rotation must be an integer between 1 and 9999.");
		}
		if (isset($_POST['wpa_gmk_rekey']) && (!is_numericint($_POST['wpa_gmk_rekey']) || $_POST['wpa_gmk_rekey'] < 1 || $_POST['wpa_gmk_rekey'] > 9999)) {
			$input_errors[] = gettext("Master Key Regeneration must be an integer between 1 and 9999.");
		}
		if (isset($_POST['wpa_group_rekey']) && isset($_POST['wpa_gmk_rekey'])) {
			if ($_POST['wpa_group_rekey'] > $_POST['wpa_gmk_rekey']) {
				$input_errors[] = gettext("Master Key Regeneration must be greater than Key Rotation.");
			}
		}
		if (!empty($_POST['auth_server_addr'])) {
			if (!is_domain($_POST['auth_server_addr']) && !is_ipaddr($_POST['auth_server_addr'])) {
				$input_errors[] = gettext("802.1X Authentication Server must be an IP or hostname.");
			}
		}
		if (!empty($_POST['auth_server_addr2'])) {
			if (!is_domain($_POST['auth_server_addr2']) && !is_ipaddr($_POST['auth_server_addr2'])) {
				$input_errors[] = gettext("Secondary 802.1X Authentication Server must be an IP or hostname.");
			}
		}
		if (!empty($_POST['auth_server_port'])) {
			if (!is_port($_POST['auth_server_port'])) {
				$input_errors[] = gettext("802.1X Authentication Server Port must be a valid port number (1-65535).");
			}
		}
		if (!empty($_POST['auth_server_port2'])) {
			if (!is_port($_POST['auth_server_port2'])) {
				$input_errors[] = gettext("Secondary 802.1X Authentication Server Port must be a valid port number (1-65535).");
			}
		}
		if (isset($_POST['channel']) && !is_numericint($_POST['channel'])) {
			if (!is_numericint($_POST['channel'])) {
				$input_errors[] = gettext("Invalid channel specified.");
			} else {
				if ($_POST['channel'] > 255 || $_POST['channel'] < 0) {
					$input_errors[] = gettext("Channel must be between 0-255.");
				}
			}
		}
		if (!empty($_POST['distance']) && !is_numericint($_POST['distance'])) {
			$input_errors[] = gettext("Distance must be an integer.");
		}
		if (isset($_POST['standard']) && (stristr($_POST['standard'], '11na') || stristr($_POST['standard'], '11a'))) {
			if ($_POST['channel'] != 0 && $_POST['channel'] < 15) {
				$input_errors[] = gettext("Channel selected is not valid for 802.11a or 802.11na.");
			}
		}
		if (isset($_POST['standard']) && ($_POST['standard'] == "11b" || $_POST['standard'] == "11g")) {
			if ($_POST['channel'] > 14) {
				$input_errors[] = gettext("Channel selected is not valid for 802.11b or 802.11g.");
			}
		}
		if (!empty($_POST['protmode']) && !in_array($_POST['protmode'], array("off", "cts", "rtscts"))) {
			$input_errors[] = gettext("Invalid option chosen for OFDM Protection Mode");
		}

		if ($_POST['passphrase']) {
			$passlen = strlen($_POST['passphrase']);
			if ($passlen < 8 || $passlen > 63) {
				$input_errors[] = gettext("The WPA passphrase must be between 8 and 63 characters long.");
			}
		}

		if ($_POST['wpa_enable'] == "yes") {
			if (empty($_POST['passphrase']) && stristr($_POST['wpa_key_mgmt'], "WPA-PSK")) {
				$input_errors[] = gettext("A WPA Passphrase must be specified when WPA PSK is enabled.");
			}
			if (($_POST['mode'] == 'bss') & ($_POST['wpa_key_mgmt'] == "WPA-EAP") &&
			    ($_POST['wpa_eap_client_mode'] != 'tls')) {
				if (empty($_POST['wpa_eap_inner_id'])) {
					$input_errors[] = gettext("An Inner Authentication Identity must be specified " .
					    "when PEAP/TTLS authentication method is selected.");
				}
				if (empty($_POST['wpa_eap_inner_password'])) {
					$input_errors[] = gettext("An Inner Authentication Passphrase must be specified " .
					    "when PEAP/TTLS authentication method is selected.");
				}
			}
		}
	}

	if ($_POST['ppp_password'] != $_POST['ppp_password_confirm']) {
		$input_errors[] = gettext("PPP Password and confirmed password must match!");
	}

	if ($_POST['pppoe_password'] != $_POST['pppoe_password_confirm']) {
		$input_errors[] = gettext("PPPoE Password and confirmed password must match!");
	}

	if ($_POST['pptp_password'] != $_POST['pptp_password_confirm']) {
		$input_errors[] = gettext("PTPP Password and confirmed password must match!");
	}

	if ($_POST['gatewayip4']) {
		// The user wants to add an IPv4 gateway - validate the settings
		$gateway_settings4 = array();

		$gateway_settings4['name'] = $_POST['gatewayname4'];
		$gateway_settings4['interface'] = $_POST['if'];
		$gateway_settings4['gateway'] = $_POST['gatewayip4'];
		$gateway_settings4['descr'] = $_POST['gatewaydescr4'];
		$gateway_settings4['defaultgw'] = $_POST['defaultgw4'];
		$gateway_settings4['ipprotocol'] = 'inet';
		$gw_input_errors = validate_gateway($gateway_settings4, '', $_POST['ipaddr'], $_POST['subnet']);
		foreach ($gw_input_errors as $input_error_text) {
			$input_errors[] = $input_error_text;
		}
	}

	if ($_POST['gatewayip6']) {
		// The user wants to add an IPv6 gateway - validate the settings
		$gateway_settings6 = array();

		$gateway_settings6['name'] = $_POST['gatewayname6'];
		$gateway_settings6['interface'] = $_POST['if'];
		$gateway_settings6['gateway'] = $_POST['gatewayip6'];
		$gateway_settings6['descr'] = $_POST['gatewaydescr6'];
		$gateway_settings6['defaultgw'] = $_POST['defaultgw6'];
		$gateway_settings6['ipprotocol'] = 'inet6';
		$gw_input_errors = validate_gateway($gateway_settings6, '', $_POST['ipaddrv6'], $_POST['subnetv6']);
		foreach ($gw_input_errors as $input_error_text) {
			$input_errors[] = $input_error_text;
		}
	}

	if (!$input_errors) {
		// These 3 fields can be a list of multiple data items when used for MLPPP.
		// The UI in this code only processes the first of the list, so save the data here then we can preserve any other entries.
		$poriginal['pptp_localip'] = explode(",", $a_ppps[$pppid]['localip']);
		$poriginal['pptp_subnet'] = explode(",", $a_ppps[$pppid]['subnet']);
		$poriginal['pptp_remote'] = explode(",", $a_ppps[$pppid]['gateway']);

		if ($wancfg['ipaddr'] != $_POST['type']) {
			if (in_array($wancfg['ipaddr'], array("ppp", "pppoe", "pptp", "l2tp"))) {
				$wancfg['if'] = $a_ppps[$pppid]['ports'];
				unset($a_ppps[$pppid]);
			} else if ($wancfg['ipaddr'] == "dhcp") {
				kill_dhclient_process($wancfg['if']);
			}
			if ($wancfg['ipaddrv6'] == "dhcp6") {
				kill_dhcp6client_process($wancfg['if'],true);
			}
		}
		$ppp = array();
		if ($wancfg['ipaddr'] != "ppp") {
			unset($wancfg['ipaddr']);
		}
		if ($wancfg['ipaddrv6'] != "ppp") {
			unset($wancfg['ipaddrv6']);
		}
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
		unset($wancfg['slaacusev4iface']);
		unset($wancfg['ipv6usev4iface']);
		unset($wancfg['dhcp6debug']);
		unset($wancfg['track6-interface']);
		unset($wancfg['track6-prefix-id']);
		unset($wancfg['dhcp6withoutra']);
		unset($wancfg['dhcp6norelease']);
		unset($wancfg['dhcp6vlanenable']);
		unset($wancfg['dhcp6cvpt']);
		unset($wancfg['prefix-6rd']);
		unset($wancfg['prefix-6rd-v4plen']);
		unset($wancfg['gateway-6rd']);

		unset($wancfg['dhcpvlanenable']);
		unset($wancfg['dhcpcvpt']);

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
		unset($wancfg['adv_dhcp6_prefix_selected_interface']);

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
		unset($wancfg['l2tp_secret']);
		unset($wancfg['provider']);
		unset($wancfg['hostuniq']);
		unset($wancfg['ondemand']);
		unset($wancfg['timeout']);
		if (empty($wancfg['pppoe']['pppoe-reset-type'])) {
			unset($wancfg['pppoe']['pppoe-reset-type']);
		}
		unset($wancfg['local']);

		unset($wancfg['remote']);
		if (is_array($a_ppps[$pppid]) && in_array($wancfg['ipaddr'], array("ppp", "pppoe", "pptp", "l2tp"))) {
			if ($wancfg['ipaddr'] != 'ppp') {
				unset($a_ppps[$pppid]['apn']);
				unset($a_ppps[$pppid]['phone']);
				unset($a_ppps[$pppid]['provider']);
				unset($a_ppps[$pppid]['ondemand']);
			}
			if (in_array($wancfg['ipaddr'], array("pppoe", "pptp", "l2tp"))) {
				unset($a_ppps[$pppid]['localip']);
				unset($a_ppps[$pppid]['subnet']);
				unset($a_ppps[$pppid]['gateway']);
			}
			if ($wancfg['ipaddr'] != 'pppoe') {
				unset($a_ppps[$pppid]['pppoe-reset-type']);
				unset($a_ppps[$pppid]['hostuniq']);
			}
			if ($wancfg['type'] != $_POST['type']) {
				unset($a_ppps[$pppid]['idletimeout']);
			}
		}

		$wancfg['descr'] = remove_bad_chars($_POST['descr']);
		$wancfg['enable'] = $_POST['enable'] == "yes" ? true : false;

		/* let return_gateways_array() do the magic on dynamic interfaces for us */
		switch ($_POST['type']) {
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
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				if ($_POST['dhcpvlanenable'] == "yes") {
					$wancfg['dhcpvlanenable'] = true;
				}
				if (!empty($_POST['dhcpcvpt'])) {
					$wancfg['dhcpcvpt'] = $_POST['dhcpcvpt'];
				} else {
					unset($wancfg['dhcpcvpt']);
				}
				break;
			case "ppp":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				$a_ppps[$pppid]['ports'] = $_POST['port'];
				$a_ppps[$pppid]['username'] = $_POST['ppp_username'];
				if ($_POST['ppp_password'] != DMYPWD) {
					$a_ppps[$pppid]['password'] = base64_encode($_POST['ppp_password']);
				}
				$a_ppps[$pppid]['phone'] = $_POST['phone'];
				$a_ppps[$pppid]['apn'] = $_POST['apn'];
				$wancfg['if'] = $_POST['type'] . $_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				break;

			case "pppoe":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				if (isset($_POST['ppp_port'])) {
					$a_ppps[$pppid]['ports'] = $_POST['ppp_port'];
				} else {
					$a_ppps[$pppid]['ports'] = $wancfg['if'];
				}
				$a_ppps[$pppid]['username'] = $_POST['pppoe_username'];
				if ($_POST['pppoe_password'] != DMYPWD) {
					$a_ppps[$pppid]['password'] = base64_encode($_POST['pppoe_password']);
				}
				if (!empty($_POST['provider'])) {
					$a_ppps[$pppid]['provider'] = $_POST['provider'];
				} else {
					$a_ppps[$pppid]['provider'] = true;
				}
				if (!empty($_POST['hostuniq'])) {
					$a_ppps[$pppid]['hostuniq'] = strtolower($_POST['hostuniq']);
				} else {
					$a_ppps[$pppid]['hostuniq'] = true;
				}
				$a_ppps[$pppid]['ondemand'] = $_POST['pppoe_dialondemand'] ? true : false;
				if (!empty($_POST['pppoe_idletimeout'])) {
					$a_ppps[$pppid]['idletimeout'] = $_POST['pppoe_idletimeout'];
				} else {
					unset($a_ppps[$pppid]['idletimeout']);
				}

				if (!empty($_POST['pppoe-reset-type'])) {
					$a_ppps[$pppid]['pppoe-reset-type'] = $_POST['pppoe-reset-type'];
				} else {
					unset($a_ppps[$pppid]['pppoe-reset-type']);
				}
				$wancfg['if'] = $_POST['type'].$_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}

				break;
			case "pptp":
			case "l2tp":
				$a_ppps[$pppid]['ptpid'] = $_POST['ptpid'];
				$a_ppps[$pppid]['type'] = $_POST['type'];
				$a_ppps[$pppid]['if'] = $_POST['type'].$_POST['ptpid'];
				if (isset($_POST['ppp_port'])) {
					$a_ppps[$pppid]['ports'] = $_POST['ppp_port'];
				} else {
					$a_ppps[$pppid]['ports'] = $wancfg['if'];
				}
				$a_ppps[$pppid]['username'] = $_POST['pptp_username'];
				if ($_POST['pptp_password'] != DMYPWD) {
					$a_ppps[$pppid]['password'] = base64_encode($_POST['pptp_password']);
				}
				if (($_POST['type'] == 'l2tp') && (!empty($_POST['l2tp_secret']))) {
					$a_ppps[$pppid]['secret'] = base64_encode($_POST['l2tp_secret']);
				} else {
					unset($a_ppps[$pppid]['secret']);
				}
				// Replace the first (0) entry with the posted data. Preserve any other entries that might be there.
				$poriginal['pptp_localip'][0] = $_POST['pptp_local0'];
				$a_ppps[$pppid]['localip'] = implode(',', $poriginal['pptp_localip']);
				$poriginal['pptp_subnet'][0] = $_POST['pptp_subnet0'];
				$a_ppps[$pppid]['subnet'] = implode(',', $poriginal['pptp_subnet']);
				$poriginal['pptp_remote'][0] = $_POST['pptp_remote0'];
				$a_ppps[$pppid]['gateway'] = implode(',', $poriginal['pptp_remote']);
				$a_ppps[$pppid]['ondemand'] = $_POST['pptp_dialondemand'] ? true : false;
				if (!empty($_POST['pptp_idletimeout'])) {
					$a_ppps[$pppid]['idletimeout'] = $_POST['pptp_idletimeout'];
				} else {
					unset($a_ppps[$pppid]['idletimeout']);
				}
				$wancfg['if'] = $_POST['type'].$_POST['ptpid'];
				$wancfg['ipaddr'] = $_POST['type'];
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "none":
				break;
		}
		switch ($_POST['type6']) {
			case "staticv6":
				$wancfg['ipaddrv6'] = $_POST['ipaddrv6'];
				$wancfg['subnetv6'] = $_POST['subnetv6'];
				if ($_POST['ipv6usev4iface'] == "yes") {
					$wancfg['ipv6usev4iface'] = true;
				}
				if ($_POST['gatewayv6'] != "none") {
					$wancfg['gatewayv6'] = $_POST['gatewayv6'];
				}
				break;
			case "slaac":
				$wancfg['ipaddrv6'] = "slaac";
				if ($_POST['slaacusev4iface'] == "yes") {
					$wancfg['slaacusev4iface'] = true;
				}
				break;
			case "dhcp6":
				$wancfg['ipaddrv6'] = "dhcp6";
				$wancfg['dhcp6-duid'] = $_POST['dhcp6-duid'];
				$wancfg['dhcp6-ia-pd-len'] = $_POST['dhcp6-ia-pd-len'];
				if ($_POST['dhcp6-ia-pd-send-hint'] == "yes") {
					$wancfg['dhcp6-ia-pd-send-hint'] = true;
				}
				if ($_POST['dhcp6prefixonly'] == "yes") {
					$wancfg['dhcp6prefixonly'] = true;
				}
				if ($_POST['dhcp6usev4iface'] == "yes") {
					$wancfg['dhcp6usev4iface'] = true;
				}
				if ($_POST['dhcp6debug'] == "yes") {
					$wancfg['dhcp6debug'] = true;
				}

				if ($_POST['dhcp6withoutra'] == "yes") {
					$wancfg['dhcp6withoutra'] = true;
				}
				if ($_POST['dhcp6norelease'] == "yes") {
					$wancfg['dhcp6norelease'] = true;
				}
				if ($_POST['dhcp6vlanenable'] == "yes") {
					$wancfg['dhcp6vlanenable'] = true;
				}
				if (!empty($_POST['dhcp6cvpt'])) {
					$wancfg['dhcp6cvpt'] = $_POST['dhcp6cvpt'];
				} else {
					unset($wancfg['dhcp6cvpt']);
				}

				if (!empty($_POST['adv_dhcp6_interface_statement_send_options'])) {
					$wancfg['adv_dhcp6_interface_statement_send_options'] = $_POST['adv_dhcp6_interface_statement_send_options'];
				}
				if (!empty($_POST['adv_dhcp6_interface_statement_request_options'])) {
					$wancfg['adv_dhcp6_interface_statement_request_options'] = $_POST['adv_dhcp6_interface_statement_request_options'];
				}
				if (isset($_POST['adv_dhcp6_interface_statement_information_only_enable'])) {
					$wancfg['adv_dhcp6_interface_statement_information_only_enable'] = $_POST['adv_dhcp6_interface_statement_information_only_enable'];
				}
				if (!empty($_POST['adv_dhcp6_interface_statement_script'])) {
					$wancfg['adv_dhcp6_interface_statement_script'] = $_POST['adv_dhcp6_interface_statement_script'];
				}

				if (isset($_POST['adv_dhcp6_id_assoc_statement_address_enable'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_address_enable'] = $_POST['adv_dhcp6_id_assoc_statement_address_enable'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_address'] = $_POST['adv_dhcp6_id_assoc_statement_address'];
				}
				if (is_numericint($_POST['adv_dhcp6_id_assoc_statement_address_id'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_address_id'] = $_POST['adv_dhcp6_id_assoc_statement_address_id'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address_pltime'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_address_pltime'] = $_POST['adv_dhcp6_id_assoc_statement_address_pltime'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address_vltime'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_address_vltime'] = $_POST['adv_dhcp6_id_assoc_statement_address_vltime'];
				}

				if (isset($_POST['adv_dhcp6_id_assoc_statement_prefix_enable'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_prefix_enable'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_enable'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_prefix'] = $_POST['adv_dhcp6_id_assoc_statement_prefix'];
				}
				if (is_numericint($_POST['adv_dhcp6_id_assoc_statement_prefix_id'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_prefix_id'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_id'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix_pltime'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_pltime'];
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix_vltime'])) {
					$wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'] = $_POST['adv_dhcp6_id_assoc_statement_prefix_vltime'];
				}

				if (is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_id'])) {
					$wancfg['adv_dhcp6_prefix_interface_statement_sla_id'] = $_POST['adv_dhcp6_prefix_interface_statement_sla_id'];
				}
				if (is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_len'])) {
					$wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] = $_POST['adv_dhcp6_prefix_interface_statement_sla_len'];
				}
				if (!empty($_POST['adv_dhcp6_prefix_selected_interface'])) {
					$wancfg['adv_dhcp6_prefix_selected_interface'] = $_POST['adv_dhcp6_prefix_selected_interface'];
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_authname'])) {
					$wancfg['adv_dhcp6_authentication_statement_authname'] = $_POST['adv_dhcp6_authentication_statement_authname'];
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_protocol'])) {
					$wancfg['adv_dhcp6_authentication_statement_protocol'] = $_POST['adv_dhcp6_authentication_statement_protocol'];
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_algorithm'])) {
					$wancfg['adv_dhcp6_authentication_statement_algorithm'] = $_POST['adv_dhcp6_authentication_statement_algorithm'];
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_rdm'])) {
					$wancfg['adv_dhcp6_authentication_statement_rdm'] = $_POST['adv_dhcp6_authentication_statement_rdm'];
				}

				if (!empty($_POST['adv_dhcp6_key_info_statement_keyname'])) {
					$wancfg['adv_dhcp6_key_info_statement_keyname'] = $_POST['adv_dhcp6_key_info_statement_keyname'];
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_realm'])) {
					$wancfg['adv_dhcp6_key_info_statement_realm'] = $_POST['adv_dhcp6_key_info_statement_realm'];
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_keyid'])) {
					$wancfg['adv_dhcp6_key_info_statement_keyid'] = $_POST['adv_dhcp6_key_info_statement_keyid'];
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_secret'])) {
					$wancfg['adv_dhcp6_key_info_statement_secret'] = $_POST['adv_dhcp6_key_info_statement_secret'];
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_expire'])) {
					$wancfg['adv_dhcp6_key_info_statement_expire'] = $_POST['adv_dhcp6_key_info_statement_expire'];
				}

				if (!empty($_POST['adv_dhcp6_config_advanced'])) {
					$wancfg['adv_dhcp6_config_advanced'] = $_POST['adv_dhcp6_config_advanced'];
				}
				if (!empty($_POST['adv_dhcp6_config_file_override'])) {
					$wancfg['adv_dhcp6_config_file_override'] = $_POST['adv_dhcp6_config_file_override'];
				}
				if (!empty($_POST['adv_dhcp6_config_file_override_path'])) {
					$wancfg['adv_dhcp6_config_file_override_path'] = $_POST['adv_dhcp6_config_file_override_path'];
				}

				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "6rd":
				$wancfg['ipaddrv6'] = "6rd";
				$wancfg['prefix-6rd'] = $_POST['prefix-6rd'];
				$wancfg['prefix-6rd-v4plen'] = $_POST['prefix-6rd-v4plen'];
				$wancfg['gateway-6rd'] = $_POST['gateway-6rd'];
				if ($gateway_item) {
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
					$wancfg['track6-prefix-id'] = 0;
				} else if (ctype_xdigit($_POST['track6-prefix-id--hex'])) {
					$wancfg['track6-prefix-id'] = intval($_POST['track6-prefix-id--hex'], 16);
				} else {
					$wancfg['track6-prefix-id'] = 0;
				}
				break;
			case "none":
				break;
		}
		handle_pppoe_reset($_POST);

		if ($_POST['blockpriv'] == "yes") {
			$wancfg['blockpriv'] = true;
		} else {
			unset($wancfg['blockpriv']);
		}
		if ($_POST['blockbogons'] == "yes") {
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
			if ($mediaopts[0] != '') {
				$wancfg['media'] = $mediaopts[0];
			}
			if ($mediaopts[1] != '') {
				$wancfg['mediaopt'] = $mediaopts[1];
			} else {
				unset($wancfg['mediaopt']);
			}
		}
		if (isset($wancfg['wireless'])) {
			handle_wireless_post();
		}

		write_config("Interfaces settings changed");

		if ($_POST['gatewayip4']) {
			save_gateway($gateway_settings4);
		}

		if ($_POST['gatewayip6']) {
			save_gateway($gateway_settings6);
		}

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

} // end if ($_POST['save'])

function handle_wireless_post() {
	global $_POST, $config, $g, $wancfg, $if, $wl_countries_attr, $wlanbaseif;
	if (!is_array($wancfg['wireless'])) {
		$wancfg['wireless'] = array();
	}
	$wancfg['wireless']['standard'] = $_POST['standard'];
	$wancfg['wireless']['mode'] = $_POST['mode'];
	$wancfg['wireless']['protmode'] = $_POST['protmode'];
	$wancfg['wireless']['ssid'] = $_POST['ssid'];
	$wancfg['wireless']['channel'] = $_POST['channel'];
	$wancfg['wireless']['channel_width'] = $_POST['channel_width'];
	$wancfg['wireless']['authmode'] = $_POST['authmode'];
	$wancfg['wireless']['txpower'] = $_POST['txpower'];
	$wancfg['wireless']['distance'] = $_POST['distance'];
	$wancfg['wireless']['regdomain'] = $_POST['regdomain'];
	$wancfg['wireless']['regcountry'] = $_POST['regcountry'];
	$wancfg['wireless']['reglocation'] = $_POST['reglocation'];
	if (!empty($wancfg['wireless']['regdomain']) && !empty($wancfg['wireless']['regcountry'])) {
		foreach ($wl_countries_attr as $wl_country) {
			if ($wancfg['wireless']['regcountry'] == $wl_country['ID']) {
				$wancfg['wireless']['regdomain'] = $wl_country['rd'][0]['REF'];
				break;
			}
		}
	}
	if (!is_array($wancfg['wireless']['wpa'])) {
		$wancfg['wireless']['wpa'] = array();
	}
	$wancfg['wireless']['wpa']['macaddr_acl'] = $_POST['macaddr_acl'];
	$wancfg['wireless']['wpa']['wpa_mode'] = $_POST['wpa_mode'];
	$wancfg['wireless']['wpa']['wpa_key_mgmt'] = $_POST['wpa_key_mgmt'];
	$wancfg['wireless']['wpa']['wpa_pairwise'] = $_POST['wpa_pairwise'];
	$wancfg['wireless']['wpa']['wpa_group_rekey'] = $_POST['wpa_group_rekey'];
	$wancfg['wireless']['wpa']['wpa_gmk_rekey'] = $_POST['wpa_gmk_rekey'];
	$wancfg['wireless']['wpa']['passphrase'] = $_POST['passphrase'];
	$wancfg['wireless']['wpa']['ext_wpa_sw'] = $_POST['ext_wpa_sw'];
	$wancfg['wireless']['wpa']['wpa_eap_client_mode'] = $_POST['wpa_eap_client_mode'];
	$wancfg['wireless']['wpa']['wpa_eap_inner_auth'] = $_POST['wpa_eap_inner_auth'];
	$wancfg['wireless']['wpa']['wpa_eap_inner_id'] = $_POST['wpa_eap_inner_id'];
	$wancfg['wireless']['wpa']['wpa_eap_inner_password'] = base64_encode($_POST['wpa_eap_inner_password']);
	$wancfg['wireless']['wpa']['wpa_eap_cert'] = $_POST['wpa_eap_cert'];
	$wancfg['wireless']['wpa']['wpa_eap_ca'] = $_POST['wpa_eap_ca'];
	$wancfg['wireless']['auth_server_addr'] = $_POST['auth_server_addr'];
	$wancfg['wireless']['auth_server_port'] = $_POST['auth_server_port'];
	$wancfg['wireless']['auth_server_shared_secret'] = $_POST['auth_server_shared_secret'];
	$wancfg['wireless']['auth_server_addr2'] = $_POST['auth_server_addr2'];
	$wancfg['wireless']['auth_server_port2'] = $_POST['auth_server_port2'];
	$wancfg['wireless']['auth_server_shared_secret2'] = $_POST['auth_server_shared_secret2'];

	if ($_POST['persistcommonwireless'] == "yes") {
		if (!is_array($config['wireless'])) {
			$config['wireless'] = array();
		}
		if (!is_array($config['wireless']['interfaces'])) {
			$config['wireless']['interfaces'] = array();
		}
		if (!is_array($config['wireless']['interfaces'][$wlanbaseif])) {
			$config['wireless']['interfaces'][$wlanbaseif] = array();
		}
	} else if (isset($config['wireless']['interfaces'][$wlanbaseif])) {
		unset($config['wireless']['interfaces'][$wlanbaseif]);
	}
	if (isset($_POST['diversity']) && is_numeric($_POST['diversity'])) {
		$wancfg['wireless']['diversity'] = $_POST['diversity'];
	} else if (isset($wancfg['wireless']['diversity'])) {
		unset($wancfg['wireless']['diversity']);
	}
	if (isset($_POST['txantenna']) && is_numeric($_POST['txantenna'])) {
		$wancfg['wireless']['txantenna'] = $_POST['txantenna'];
	} else if (isset($wancfg['wireless']['txantenna'])) {
		unset($wancfg['wireless']['txantenna']);
	}
	if (isset($_POST['rxantenna']) && is_numeric($_POST['rxantenna'])) {
		$wancfg['wireless']['rxantenna'] = $_POST['rxantenna'];
	} else if (isset($wancfg['wireless']['rxantenna'])) {
		unset($wancfg['wireless']['rxantenna']);
	}
	if ($_POST['hidessid_enable'] == "yes") {
		$wancfg['wireless']['hidessid']['enable'] = true;
	} else if (isset($wancfg['wireless']['hidessid']['enable'])) {
		unset($wancfg['wireless']['hidessid']['enable']);
	}
	if ($_POST['mac_acl_enable'] == "yes") {
		$wancfg['wireless']['wpa']['mac_acl_enable'] = true;
	} else if (isset($wancfg['wireless']['wpa']['mac_acl_enable'])) {
		unset($wancfg['wireless']['wpa']['mac_acl_enable']);
	}
	if ($_POST['rsn_preauth'] == "yes") {
		$wancfg['wireless']['wpa']['rsn_preauth'] = true;
	} else {
		unset($wancfg['wireless']['wpa']['rsn_preauth']);
	}
	if ($_POST['ieee8021x'] == "yes") {
		$wancfg['wireless']['wpa']['ieee8021x']['enable'] = true;
	} else if (isset($wancfg['wireless']['wpa']['ieee8021x']['enable'])) {
		unset($wancfg['wireless']['wpa']['ieee8021x']['enable']);
	}
	if ($_POST['wpa_strict_rekey'] == "yes") {
		$wancfg['wireless']['wpa']['wpa_strict_rekey'] = true;
	} else if (isset($wancfg['wireless']['wpa']['wpa_strict_rekey'])) {
		unset($wancfg['wireless']['wpa']['wpa_strict_rekey']);
	}
	if ($_POST['debug_mode'] == "yes") {
		$wancfg['wireless']['wpa']['debug_mode'] = true;
	} else if (isset($wancfg['wireless']['wpa']['debug_mode'])) {
		sunset($wancfg['wireless']['wpa']['debug_mode']);
	}
	if ($_POST['wpa_enable'] == "yes") {
		$wancfg['wireless']['wpa']['enable'] = $_POST['wpa_enable'] = true;
	} else if (isset($wancfg['wireless']['wpa']['enable'])) {
		unset($wancfg['wireless']['wpa']['enable']);
	}

	if ($_POST['wme_enable'] == "yes") {
		if (!is_array($wancfg['wireless']['wme'])) {
			$wancfg['wireless']['wme'] = array();
		}
		$wancfg['wireless']['wme']['enable'] = $_POST['wme_enable'] = true;
	} else if (isset($wancfg['wireless']['wme']['enable'])) {
		unset($wancfg['wireless']['wme']['enable']);
	}
	if ($_POST['puremode'] == "11g") {
		if (!is_array($wancfg['wireless']['pureg'])) {
			$wancfg['wireless']['pureg'] = array();
		}
		$wancfg['wireless']['pureg']['enable'] = true;
	} else if ($_POST['puremode'] == "11n") {
		if (!is_array($wancfg['wireless']['puren'])) {
			$wancfg['wireless']['puren'] = array();
		}
		$wancfg['wireless']['puren']['enable'] = true;
	} else {
		if (isset($wancfg['wireless']['pureg'])) {
			unset($wancfg['wireless']['pureg']);
		}
		if (isset($wancfg['wireless']['puren'])) {
			unset($wancfg['wireless']['puren']);
		}
	}
	if ($_POST['apbridge_enable'] == "yes") {
		if (!is_array($wancfg['wireless']['apbridge'])) {
			$wancfg['wireless']['apbridge'] = array();
		}
		$wancfg['wireless']['apbridge']['enable'] = $_POST['apbridge_enable'] = true;
	} else if (isset($wancfg['wireless']['apbridge']['enable'])) {
		unset($wancfg['wireless']['apbridge']['enable']);
	}
	if ($_POST['standard'] == "11g Turbo" || $_POST['standard'] == "11a Turbo") {
		if (!is_array($wancfg['wireless']['turbo'])) {
			$wancfg['wireless']['turbo'] = array();
		}
		$wancfg['wireless']['turbo']['enable'] = true;
	} else if (isset($wancfg['wireless']['turbo']['enable'])) {
		unset($wancfg['wireless']['turbo']['enable']);
	}

	interface_sync_wireless_clones($wancfg, true);
}

function check_wireless_mode() {
	global $_POST, $config, $g, $wlan_modes, $wancfg, $if, $wlanif, $wlanbaseif, $old_wireless_mode, $input_errors;

	if ($wancfg['wireless']['mode'] == $_POST['mode']) {
		return;
	}

	if (does_interface_exist(interface_get_wireless_clone($wlanbaseif))) {
		$clone_count = 1;
	} else {
		$clone_count = 0;
	}

	if (isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as $clone) {
			if ($clone['if'] == $wlanbaseif) {
				$clone_count++;
			}
		}
	}

	if ($clone_count > 1) {
		$old_wireless_mode = $wancfg['wireless']['mode'];
		$wancfg['wireless']['mode'] = $_POST['mode'];
		if (!interface_wireless_clone("{$wlanif}_", $wancfg)) {
			$input_errors[] = sprintf(gettext("Unable to change mode to %s. The maximum number of wireless clones supported in this mode may have been reached."), $wlan_modes[$wancfg['wireless']['mode']]);
		} else {
			pfSense_interface_destroy("{$wlanif}_");
		}
		$wancfg['wireless']['mode'] = $old_wireless_mode;
	}
}

// Find all possible media options for the interface
$mediaopts_list = array();
$intrealname = $config['interfaces'][$if]['if'];
exec("/sbin/ifconfig -m $intrealname | grep \"media \"", $mediaopts);
foreach ($mediaopts as $mediaopt) {
	preg_match("/media (.*)/", $mediaopt, $matches);
	if (preg_match("/(.*) mediaopt (.*)/", $matches[1], $matches1)) {
		// there is media + mediaopt like "media 1000baseT mediaopt full-duplex"
		array_push($mediaopts_list, $matches1[1] . " " . $matches1[2]);
	} else {
		// there is only media like "media 1000baseT"
		array_push($mediaopts_list, $matches[1]);
	}
}

$pgtitle = array(gettext("Interfaces"), "{$wancfg['descr']} ({$realifname})");
$shortcut_section = "interfaces";

$types4 = array("ppp" => gettext("PPP"), "pppoe" => gettext("PPPoE"), "pptp" => gettext("PPTP"), "l2tp" => gettext("L2TP"));

if (!in_array($pconfig['type'], array("ppp", "pppoe", "pptp", "l2tp")) ||
   !array_key_exists($a_ppps[$pppid]['ports'], get_configured_interface_list_by_realif())) { 
	$types4 = array_merge(array("none" => gettext("None"), "staticv4" => gettext("Static IPv4"), "dhcp" => gettext("DHCP")), $types4);
}

$types6 = array("none" => gettext("None"), "staticv6" => gettext("Static IPv6"), "dhcp6" => gettext("DHCP6"), "slaac" => gettext("SLAAC"), "6rd" => gettext("6rd Tunnel"), "6to4" => gettext("6to4 Tunnel"), "track6" => gettext("Track Interface"));

// Get the MAC address
$defgatewayname4 = $wancfg['descr'] . "GW";
$defgatewayname6 = $wancfg['descr'] . "GWv6";

function build_mediaopts_list() {
	global $mediaopts_list;

	$list = [""	 =>	 gettext("Default (no preference, typically autoselect)"),
			 " " =>	 gettext("------- Media Supported by this interface -------")
			];

	foreach ($mediaopts_list as $mediaopt) {
		$list[$mediaopt] = $mediaopt;
	}

	return($list);
}

function build_gateway_list() {
	global $a_gateways, $if;

	$list = array("none" => gettext("None"));
	foreach ($a_gateways as $gateway) {
		if (($gateway['interface'] == $if) && (is_ipaddrv4($gateway['gateway']))) {
			$list[$gateway['name']] = $gateway['name'] . " - " . $gateway['gateway'];
		}
	}

	return($list);
}

function build_gatewayv6_list() {
	global $a_gateways, $if;

	$list = array("none" => gettext("None"));
	foreach ($a_gateways as $gateway) {
		if (($gateway['interface'] == $if) && (is_ipaddrv6($gateway['gateway']))) {
			$list[$gateway['name']] = $gateway['name'] . " - " . $gateway['gateway'];
		}
	}

	return($list);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if (is_subsystem_dirty('interfaces')) {
	print_apply_box(sprintf(gettext("The %s configuration has been changed."), $wancfg['descr']) . "<br />" .
					gettext("The changes must be applied to take effect.") . "<br />" .
					gettext("Don't forget to adjust the DHCP Server range if needed after applying."));
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$form = new Form();

$section = new Form_Section('General Configuration');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable interface',
	$pconfig['enable'],
	'yes'
));

$section->addInput(new Form_Input(
	'descr',
	'*Description',
	'text',
	$pconfig['descr']
))->setHelp('Enter a description (name) for the interface here.');

if ($show_address_controls) {
	$section->addInput(new Form_Select(
		'type',
		'IPv4 Configuration Type',
		$pconfig['type'],
		$types4
	));
	$section->addInput(new Form_Select(
		'type6',
		'IPv6 Configuration Type',
		$pconfig['type6'],
		$types6
	));
} else {
	$section->addInput(new Form_StaticText(
		'IPv4/IPv6 Configuration',
		"This interface type does not support manual address configuration on this page. "
	));
	$form->addGlobal(new Form_Input(
		'type',
		null,
		'hidden',
		'none'
	));
	$form->addGlobal(new Form_Input(
		'type6',
		null,
		'hidden',
		'none'
	));
}

if (!is_pseudo_interface($intrealname, true)) {
	$macaddress = new Form_Input(
		'spoofmac',
		'MAC Address',
		'text',
		$pconfig['spoofmac'],
		['placeholder' => 'xx:xx:xx:xx:xx:xx']
	);

	if (interface_is_vlan($realifname)) {
		$macaddress->setDisabled();
		$macaddress->setHelp('The MAC address of a VLAN interface must be ' .
		    'set on its parent interface');
	} else {
		$macaddress->setHelp('This field can be used to modify ("spoof") the ' .
		    'MAC address of this interface.%sEnter a MAC address in the ' .
		    'following format: xx:xx:xx:xx:xx:xx or leave blank.', '<br />');
	}

	$section->addInput($macaddress);
}

$section->addInput(new Form_Input(
	'mtu',
	'MTU',
	'number',
	$pconfig['mtu']
))->setHelp('If this field is blank, the adapter\'s default MTU will be used. ' .
			'This is typically 1500 bytes but can vary in some circumstances.');

$section->addInput(new Form_Input(
	'mss',
	'MSS',
	'number',
	$pconfig['mss']
))->setHelp('If a value is entered in this field, then MSS clamping for TCP connections to the value entered above ' .
	    'minus 40 for IPv4 (TCP/IPv4 header size) and minus 60 for IPv6 (TCP/IPv6 header size) will be in effect.');

if (count($mediaopts_list) > 0) {
	$section->addInput(new Form_Select(
		'mediaopt',
		'Speed and Duplex',
		rtrim($config['interfaces'][$if]['media'] . ' ' . $config['interfaces'][$if]['mediaopt']),
		build_mediaopts_list()
	))->setHelp('Explicitly set speed and duplex mode for this interface.%s' .
				'WARNING: MUST be set to autoselect (automatically negotiate speed) unless the port this interface connects to has its speed and duplex forced.', '<br />');
}

$form->add($section);

$section = new Form_Section('Static IPv4 Configuration');
$section->addClass('staticv4');

$section->addInput(new Form_IpAddress(
	'ipaddr',
	'*IPv4 Address',
	$pconfig['ipaddr'],
	'V4'
))->addMask('subnet', $pconfig['subnet'], 32);

$group = new Form_Group('IPv4 Upstream gateway');

$group->add(new Form_Select(
	'gateway',
	'IPv4 Upstream Gateway',
	$pconfig['gateway'],
	build_gateway_list()
));

$group->add(new Form_Button(
	'addgw4',
	'Add a new gateway',
	null,
	'fa-plus'
))->setAttribute('type','button')->addClass('btn-success')->setAttribute('data-target', '#newgateway4')->setAttribute('data-toggle', 'modal');

$group->setHelp('If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the "Add" button.%1$s' .
				'On local area network interfaces the upstream gateway should be "none". ' .
				'Gateways can be managed by %2$sclicking here%3$s.', '<br />', '<a target="_blank" href="system_gateways.php">', '</a>');

$section->add($group);

$form->add($section);

$section = new Form_Section('SLAAC IPv6 Configuration');
$section->addClass('slaac');

$section->addInput(new Form_Checkbox(
	'slaacusev4iface',
	'Use IPv4 connectivity as parent interface',
	'IPv6 will use the IPv4 connectivity link (PPPoE)',
	$pconfig['slaacusev4iface']
));

$form->add($section);

$section = new Form_Section('Static IPv6 Configuration');
$section->addClass('staticv6');

$section->addInput(new Form_IpAddress(
	'ipaddrv6',
	'*IPv6 address',
	$pconfig['ipaddrv6'],
	'V6'
))->addMask('subnetv6', $pconfig['subnetv6'], 128);

$section->addInput(new Form_Checkbox(
	'ipv6usev4iface',
	'Use IPv4 connectivity as parent interface',
	'IPv6 will use the IPv4 connectivity link (PPPoE)',
	$pconfig['ipv6usev4iface']
));

$group = new Form_Group('IPv6 Upstream gateway');

$group->add(new Form_Select(
	'gatewayv6',
	'IPv6 Upstream Gateway',
	$pconfig['gatewayv6'],
	build_gatewayv6_list()
));

$group->add(new Form_Button(
	'addgw6',
	'Add a new gateway',
	null,
	'fa-plus'
))->setAttribute('type','button')->addClass('btn-success')->setAttribute('data-target', '#newgateway6')->setAttribute('data-toggle', 'modal');

$group->setHelp('If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the "Add" button.%s' .
				'On local LANs the upstream gateway should be "none". ', '<br />');

$section->add($group);
$form->add($section);

// Add new gateway modal pop-up for IPv6
$modal = new Modal('New IPv6 Gateway', 'newgateway6', 'large');

$modal->addInput(new Form_Checkbox(
	'defaultgw6',
	'Default',
	'Default gateway',
	isset($gateway_settings6['defaultgw']) ? $gateway_settings6['defaultgw'] : ($if == "wan" || $if == "WAN")
));

$modal->addInput(new Form_Input(
	'gatewayname6',
	'Gateway name',
	'text',
	($gateway_settings6['name'] == "") ? $defgatewayname6 : $gateway_settings6['name']
));

$modal->addInput(new Form_IpAddress(
	'gatewayip6',
	'Gateway IPv6',
	$gateway_settings6['gateway'],
	'V6'
));

$modal->addInput(new Form_Input(
	'gatewaydescr6',
	'Description',
	'text',
	$gateway_settings6['descr']
));

$btnaddgw6 = new Form_Button(
	'add6',
	'Add',
	null,
	'fa-plus'
);

$btnaddgw6->setAttribute('type','button')->addClass('btn-success');

$btncnxgw6 = new Form_Button(
	'cnx6',
	'Cancel',
	null,
	'fa-undo'
);

$btncnxgw6->setAttribute('type','button')->addClass('btn-warning');

$modal->addInput(new Form_StaticText(
	null,
	$btnaddgw6 . $btncnxgw6
));

$form->add($modal);

// ==== DHCP client configuration =============================

$section = new Form_Section('DHCP Client Configuration');
$section->addClass('dhcp');

$group = new Form_Group('Options');

$group->add(new Form_Checkbox(
	'adv_dhcp_config_advanced',
	null,
	'Advanced Configuration',
	$pconfig['adv_dhcp_config_advanced']
))->setHelp('Use advanced DHCP configuration options.');

$group->add(new Form_Checkbox(
	'adv_dhcp_config_file_override',
	null,
	'Configuration Override',
	$pconfig['adv_dhcp_config_file_override']
))->setHelp('Override the configuration from this file.');

$section->add($group);

$section->addInput(new Form_Input(
	'dhcphostname',
	'Hostname',
	'text',
	$pconfig['dhcphostname']
))->setHelp('The value in this field is sent as the DHCP client identifier and hostname when requesting a DHCP lease. Some ISPs may require this (for client identification).');

$section->addInput(new Form_IpAddress(
	'alias-address',
	'Alias IPv4 address',
	$pconfig['alias-address'],
	'V4'
))->addMask('alias-subnet', $pconfig['alias-subnet'], 32)->setHelp('The value in this field is used as a fixed alias IPv4 address by the DHCP client.');

$section->addInput(new Form_Input(
	'dhcprejectfrom',
	'Reject leases from',
	'text',
	$pconfig['dhcprejectfrom']
))->setHelp('To have the DHCP client reject offers from specific DHCP servers, enter their IP addresses here ' .
			'(separate multiple entries with a comma). ' .
			'This is useful for rejecting leases from cable modems that offer private IP addresses when they lose upstream sync.');

if (interface_is_vlan($wancfg['if']) != NULL) {

	$group = new Form_Group('DHCP VLAN Priority');
	$group->add(new Form_Checkbox(
		'dhcpvlanenable',
		null,
		'Enable dhcpclient VLAN Priority tagging',
		$pconfig['dhcpvlanenable']
	))->setHelp('Normally off unless specifically required by the ISP.');

	$group->add(new Form_Select(
		'dhcpcvpt',
		'VLAN Prio',
		$pconfig['dhcpcvpt'],
		$vlanprio
	))->setHelp('Choose 802.1p priority to set.');

	$section->add($group);
}

$group = new Form_Group('Protocol timing');
$group->addClass('dhcpadvanced');
$group->setHelp('The values in these fields are DHCP protocol timings used when requesting a lease.%1$s' .
				'See %2$shere%3$s for more information.', '<br />', '<a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&sektion=5#PROTOCOL_TIMING">', '</a>');

$group->add(new Form_Input(
	'adv_dhcp_pt_timeout',
	null,
	'number',
	$pconfig['adv_dhcp_pt_timeout']
))->setHelp('Timeout');

$group->add(new Form_Input(
	'adv_dhcp_pt_retry',
	null,
	'number',
	$pconfig['adv_dhcp_pt_retry']
))->setHelp('Retry');

$group->add(new Form_Input(
	'adv_dhcp_pt_select_timeout',
	null,
	'number',
	$pconfig['adv_dhcp_pt_select_timeout'],
	['min' => 0]
))->setHelp('Select timeout');

$group->add(new Form_Input(
	'adv_dhcp_pt_reboot',
	null,
	'number',
	$pconfig['adv_dhcp_pt_reboot']
))->setHelp('Reboot');

$group->add(new Form_Input(
	'adv_dhcp_pt_backoff_cutoff',
	null,
	'number',
	$pconfig['adv_dhcp_pt_backoff_cutoff']
))->setHelp('Backoff cutoff');

$group->add(new Form_Input(
	'adv_dhcp_pt_initial_interval',
	null,
	'number',
	$pconfig['adv_dhcp_pt_initial_interval']
))->setHelp('Initial interval');

$section->add($group);

$group = new Form_Group('Presets');
$group->addClass('dhcpadvanced');

$group->add(new Form_Checkbox(
	'adv_dhcp_pt_values',
	null,
	'FreeBSD default',
	null,
	'DHCP'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'adv_dhcp_pt_values',
	null,
	'Clear',
	null,
	'Clear'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'adv_dhcp_pt_values',
	null,
	'pfSense Default',
	null,
	'pfSense'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'adv_dhcp_pt_values',
	null,
	'Saved Cfg',
	null,
	'SavedCfg'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Input(
	'adv_dhcp_config_file_override_path',
	'Configuration File Override',
	'text',
	$pconfig['adv_dhcp_config_file_override_path']
))->setWidth(9)->sethelp('The value in this field is the full absolute path to a DHCP client configuration file.	 [/[dirname/[.../]]filename[.ext]] %1$s' .
			'Value Substitutions in Config File: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s'.
			'Where C is U(pper) or L(ower) Case, and D is ":-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some ISPs may require certain options be or not be sent.', '<br />');

$form->add($section);

$section = new Form_Section('Lease Requirements and Requests');
$section->addClass('dhcpadvanced');

$section->addInput(new Form_Input(
	'adv_dhcp_send_options',
	'Send options',
	'text',
	$pconfig['adv_dhcp_send_options']
))->setWidth(9)->sethelp('The values in this field are DHCP options to be sent when requesting a DHCP lease.	 [option declaration [, ...]] %1$s' .
			'Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s' .
			'Where C is U(pper) or L(ower) Case, and D is " :-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some ISPs may require certain options be or not be sent.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp_request_options',
	'Request options',
	'text',
	$pconfig['adv_dhcp_request_options']
))->setWidth(9)->sethelp('The values in this field are DHCP option 55 to be sent when requesting a DHCP lease.  [option [, ...]] %1$s' .
			'Some ISPs may require certain options be or not be requested.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp_required_options',
	'Require options',
	'text',
	$pconfig['adv_dhcp_required_options']
))->setWidth(9)->sethelp('The values in this field are DHCP options required by the client when requesting a DHCP lease.	 [option [, ...]]');

$section->addInput(new Form_Input(
	'adv_dhcp_option_modifiers',
	'Option modifiers',
	'text',
	$pconfig['adv_dhcp_option_modifiers']
))->setWidth(9)->sethelp('The values in this field are DHCP option modifiers applied to the obtained DHCP lease.	 [modifier option declaration [, ...]] %1$s' .
			'modifiers: (default, supersede, prepend, append) %1$s' .
			'See %2$shere%3$s more information', '<br />', '<a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS">', '</a>');

$form->add($section);

// DHCP6 client config

$section = new Form_Section('DHCP6 Client Configuration');
$section->addClass('dhcp6');

$group = new Form_Group('Options');

$group->add(new Form_Checkbox(
	'adv_dhcp6_config_advanced',
	null,
	'Advanced Configuration',
	$pconfig['adv_dhcp6_config_advanced']
))->setHelp('Use advanced DHCPv6 configuration options.');

$group->add(new Form_Checkbox(
	'adv_dhcp6_config_file_override',
	null,
	'Configuration Override',
	$pconfig['adv_dhcp6_config_file_override']
))->setHelp('Override the configuration from this file.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'dhcp6usev4iface',
	'Use IPv4 connectivity as parent interface',
	'Request a IPv6 prefix/information through the IPv4 connectivity link',
	$pconfig['dhcp6usev4iface']
));

$section->addInput(new Form_Checkbox(
	'dhcp6prefixonly',
	'Request only an IPv6 prefix',
	'Only request an IPv6 prefix, do not request an IPv6 address',
	$pconfig['dhcp6prefixonly']
));

$section->addInput(new Form_Select(
	'dhcp6-ia-pd-len',
	'DHCPv6 Prefix Delegation size',
	$pconfig['dhcp6-ia-pd-len'],
	array("none" => "None", 16 => "48", 15 => "49", 14 => "50", 13 => "51", 12 => "52", 11 => "53", 10 => "54", 9 => "55", 8 => "56", 7 => "57", 6 => "58", 5 => "59", 4 => "60", 3 => "61", 2 => "62", 1 => "63", 0 => "64")
))->setHelp('The value in this field is the delegated prefix length provided by the DHCPv6 server. Normally specified by the ISP.');

$section->addInput(new Form_Checkbox(
	'dhcp6-ia-pd-send-hint',
	'Send IPv6 prefix hint',
	'Send an IPv6 prefix hint to indicate the desired prefix size for delegation',
	$pconfig['dhcp6-ia-pd-send-hint']
));

$section->addInput(new Form_Checkbox(
	'dhcp6debug',
	'Debug',
	'Start DHCP6 client in debug mode',
	$pconfig['dhcp6debug']
));
$section->addInput(new Form_Checkbox(
	'dhcp6withoutra',
	'Do not wait for a RA',
	'Required by some ISPs, especially those not using PPPoE',
	$pconfig['dhcp6withoutra']
));
$section->addInput(new Form_Checkbox(
	'dhcp6norelease',
	'Do not allow PD/Address release',
	'dhcp6c will send a release to the ISP on exit, some ISPs then release the allocated address or prefix. This option prevents that signal ever being sent',
	$pconfig['dhcp6norelease']
));

if (interface_is_vlan($wancfg['if']) != NULL) {
	$group = new Form_Group('DHCP6 VLAN Priority');

	$group->add(new Form_Checkbox(
		'dhcp6vlanenable',
		null,
		'Enable dhcp6c VLAN Priority tagging',
		$pconfig['dhcp6vlanenable']
	))->setHelp('Normally off unless specifically required by the ISP.');

	$group->add(new Form_Select(
		'dhcp6cvpt',
		'VLAN Prio',
		$pconfig['dhcp6cvpt'],
		$vlanprio
	))->setHelp('Choose 802.1p priority to set.');

	$section->add($group);
}

$section->addInput(new Form_Input(
	'adv_dhcp6_config_file_override_path',
	'Configuration File Override',
	'text',
	$pconfig['adv_dhcp6_config_file_override_path']
))->setWidth(9)->setHelp('The value in this field is the full absolute path to a DHCP client configuration file.	 [/[dirname/[.../]]filename[.ext]] %1$s' .
			'Value Substitutions in Config File: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s' .
			'Where C is U(pper) or L(ower) Case, and D is " :-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some ISPs may require certain options be or not be sent.', '<br />');

$form->add($section);

// DHCP6 client config - Advanced

$section = new Form_Section('Advanced DHCP6 Client Configuration');
$section->addClass('dhcp6advanced');

$section->addInput(new Form_Checkbox(
	'adv_dhcp6_interface_statement_information_only_enable',
	'Information only',
	'Exchange Information Only',
	$pconfig['adv_dhcp6_interface_statement_information_only_enable'],
	'Selected'
))->setHelp('Only exchange informational configuration parameters with servers.');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_send_options',
	'Send options',
	'text',
	$pconfig['adv_dhcp6_interface_statement_send_options']
))->setWidth(9)->sethelp('DHCP send options to be sent when requesting a DHCP lease.	 [option declaration [, ...]] %1$s' .
			'Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s' .
			'Where C is U(pper) or L(ower) Case, and D is " :-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some DHCP services may require certain options be or not be sent.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_request_options',
	'Request Options',
	'text',
	$pconfig['adv_dhcp6_interface_statement_request_options']
))->setWidth(9)->sethelp('DHCP request options to be sent when requesting a DHCP lease.	[option [, ...]] %1$s' .
			'Some DHCP services may require certain options be or not be requested.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_script',
	'Scripts',
	'text',
	$pconfig['adv_dhcp6_interface_statement_script']
))->setWidth(9)->sethelp('Absolute path to a script invoked on certain conditions including when a reply message is received.%1$s' .
			'[/[dirname/[.../]]filename[.ext]].', '<br />');

$group = new Form_Group('Identity Association Statement');

$group->add(new Form_Checkbox(
	'adv_dhcp6_id_assoc_statement_address_enable',
	null,
	'Non-Temporary Address Allocation',
	$pconfig['adv_dhcp6_id_assoc_statement_address_enable'],
	'Selected'
));

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_id',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_address_id']
))->sethelp('id-assoc na ID');

$group->add(new Form_IpAddress(
	'adv_dhcp6_id_assoc_statement_address',
	null,
	$pconfig['adv_dhcp6_id_assoc_statement_address'],
	'V6'
))->sethelp('IPv6 address');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_pltime',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_address_pltime']
))->sethelp('pltime');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_vltime',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_address_vltime']
))->sethelp('vltime');

$section->add($group);

// Prefix delegation
$group = new Form_Group('');

$group->add(new Form_Checkbox(
	'adv_dhcp6_id_assoc_statement_prefix_enable',
	null,
	'Prefix Delegation ',
	$pconfig['adv_dhcp6_id_assoc_statement_prefix_enable'],
	'Selected'
));

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_id',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_prefix_id']
))->sethelp('id-assoc pd ID');

$group->add(new Form_IpAddress(
	'adv_dhcp6_id_assoc_statement_prefix',
	null,
	$pconfig['adv_dhcp6_id_assoc_statement_prefix'],
	'V6'
))->sethelp('IPv6 prefix');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_pltime',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_prefix_pltime']
))->sethelp('pltime');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_vltime',
	null,
	'text',
	$pconfig['adv_dhcp6_id_assoc_statement_prefix_vltime']
))->sethelp('vltime');

$section->add($group);

$group = new Form_Group('Prefix interface statement');

$group->add(new Form_Input(
	'adv_dhcp6_prefix_interface_statement_sla_id',
	null,
	'text',
	$pconfig['adv_dhcp6_prefix_interface_statement_sla_id']
))->sethelp('Prefix Interface sla-id');

$group->add(new Form_Input(
	'adv_dhcp6_prefix_interface_statement_sla_len',
	null,
	'text',
	$pconfig['adv_dhcp6_prefix_interface_statement_sla_len']
))->sethelp('sla-len');

$section->add($group);

$group = new Form_Group('Select prefix interface');
$section->addInput(new Form_Select(
	'adv_dhcp6_prefix_selected_interface',
	'Prefix Interface',
	$pconfig['adv_dhcp6_prefix_selected_interface'],
	$interfaces
))->setHelp('Select the interface on which to apply the prefix delegation.');

$group = new Form_Group('Authentication statement');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_authname',
	null,
	'text',
	$pconfig['adv_dhcp6_authentication_statement_authname']
))->sethelp('Authname');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_protocol',
	null,
	'text',
	$pconfig['adv_dhcp6_authentication_statement_protocol']
))->sethelp('Protocol');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_algorithm',
	null,
	'text',
	$pconfig['adv_dhcp6_authentication_statement_algorithm']
))->sethelp('Algorithm');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_rdm',
	null,
	'text',
	$pconfig['adv_dhcp6_authentication_statement_rdm']
))->sethelp('RDM');

$section->add($group);

$group = new Form_Group('Keyinfo statement');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_keyname',
	null,
	'text',
	$pconfig['adv_dhcp6_key_info_statement_keyname']
))->sethelp('Keyname');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_realm',
	null,
	'text',
	$pconfig['adv_dhcp6_key_info_statement_realm']
))->sethelp('Realm');

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_keyid',
	null,
	'text',
	$pconfig['adv_dhcp6_key_info_statement_keyid']
))->sethelp('KeyID');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_secret',
	null,
	'text',
	$pconfig['adv_dhcp6_key_info_statement_secret']
))->sethelp('Secret');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_expire',
	null,
	'text',
	$pconfig['adv_dhcp6_key_info_statement_expire']
))->sethelp('Expire');

$group->setHelp('See %1$shere%2$s more information', '<a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=dhcp6c.conf&sektion=5&apropos=0&manpath=FreeBSD+11.0-RELEASE+and+Ports#Interface_statement">', '</a>');

$section->add($group);

$form->add($section);

$section = new Form_Section('6RD Configuration');
$section->addClass('_6rd');

$section->addInput(new Form_Input(
	'prefix-6rd',
	'6RD Prefix',
	'text',
	$pconfig['prefix-6rd']
))->sethelp('6RD IPv6 prefix assigned by the ISP. e.g. "2001:db8::/32"');

$section->addInput(new Form_Input(
	'gateway-6rd',
	'*6RD Border relay',
	'text',
	$pconfig['gateway-6rd']
))->sethelp('6RD IPv4 gateway address assigned by the ISP');

$section->addInput(new Form_Select(
	'prefix-6rd-v4plen',
	'6RD IPv4 Prefix length',
	$pconfig['prefix-6rd-v4plen'],
	array_combine(range(0, 32), range(0, 32))
))->setHelp('6RD IPv4 prefix length. Normally specified by the ISP. A value of 0 means embed the entire IPv4 address in the 6RD prefix.');

$form->add($section);

// Track IPv6 ointerface section
$section = new Form_Section('Track IPv6 Interface');
$section->addClass('track6');

function build_ipv6interface_list() {
	global $config, $form;

	$list = array('' => '');

	$interfaces = get_configured_interface_with_descr(true);
	$dynv6ifs = array();

	foreach ($interfaces as $iface => $ifacename) {
		switch ($config['interfaces'][$iface]['ipaddrv6']) {
			case "6to4":
			case "6rd":
			case "dhcp6":
				$dynv6ifs[$iface] = array(
					'name' => $ifacename,
					'ipv6_num_prefix_ids' => pow(2, (int) calculate_ipv6_delegation_length($iface)) - 1
				);
				break;
			default:
				continue 2;
		}
	}

	foreach ($dynv6ifs as $iface => $ifacedata) {
		$list[$iface] = $ifacedata['name'];

		$form->addGlobal(new Form_Input(
			'ipv6-num-prefix-ids-' . $iface,
			null,
			'hidden',
			$ifacedata['ipv6_num_prefix_ids']
		));
	}

	return($list);
}

$section->addInput(new Form_Select(
	'track6-interface',
	'*IPv6 Interface',
	$pconfig['track6-interface'],
	build_ipv6interface_list()
))->setHelp('Selects the dynamic IPv6 WAN interface to track for configuration.');

if ($pconfig['track6-prefix-id'] == "") {
	$pconfig['track6-prefix-id'] = 0;
}

$section->addInput(new Form_Input(
	'track6-prefix-id--hex',
	'IPv6 Prefix ID',
	'text',
	sprintf("%x", $pconfig['track6-prefix-id'])
))->setHelp('(%1$shexadecimal%2$s from 0 to %3$s) The value in this field is the (Delegated) IPv6 prefix ID. This determines the configurable network ID based on the dynamic IPv6 connection. The default value is 0.', '<b>', '</b>', '<span id="track6-prefix-id-range"></span>');

$form->addGlobal(new Form_Input(
	'track6-prefix-id-max',
	null,
	'hidden',
	0
));

$form->add($section);

/// PPP section

$section = new Form_Section('PPP Configuration');
$section->addClass('ppp');

$section->addInput(new Form_Select(
	'country',
	'Country',
	$pconfig['country'],
	[]
));

$section->addInput(new Form_Select(
	'provider_list',
	'Provider',
	$pconfig['provider_list'],
	[]
));

$section->addInput(new Form_Select(
	'providerplan',
	'Plan',
	$pconfig['providerplan'],
	[]
))->setHelp('Select to fill in service provider data.');

$section->addInput(new Form_Input(
	'ppp_username',
	'Username',
	'text',
	$pconfig['ppp_username'],
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'ppp_password',
	'Password',
	'password',
	$pconfig['ppp_password']
));

$section->addInput(new Form_Input(
	'phone',
	'*Phone number',
	'text',
	$pconfig['phone']
))->setHelp('Typically *99# for GSM networks and #777 for CDMA networks.');

$section->addInput(new Form_Input(
	'apn',
	'Access Point Name',
	'text',
	$pconfig['apn']
));


function build_port_list() {
	$list = array("" => "None");

	$portlist = glob("/dev/cua*");
	$modems	  = glob("/dev/modem*");
	$portlist = array_merge($portlist, $modems);

	foreach ($portlist as $port) {
		if (preg_match("/\.(lock|init)$/", $port)) {
			continue;
		}

	$list[trim($port)] = $port;
	}

	return($list);
}

$section->addInput(new Form_Select(
	'port',
	"*Modem port",
	$pconfig['port'],
	build_port_list()
));

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced PPP',
	isset($pconfig['pppid']) ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars($pconfig['pppid']) : 'interfaces_ppps_edit.php',
	'fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('Create a new PPP configuration.');

$form->add($section);

// PPPoE configuration
$section = new Form_Section('PPPoE Configuration');
$section->addClass('pppoe');

$section->addInput(new Form_Input(
	'pppoe_username',
	'*Username',
	'text',
	$pconfig['pppoe_username'],
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'pppoe_password',
	'*Password',
	'password',
	$pconfig['pppoe_password']
));

$section->addInput(new Form_Input(
	'provider',
	'Service name',
	'text',
	$pconfig['provider']
))->setHelp('This field can usually be left empty.');

$section->addInput(new Form_Input(
	'hostuniq',
	'Host-Uniq',
	'text',
	$pconfig['hostuniq']
))->setHelp('A unique host tag value for this PPPoE client. Leave blank unless a value is required by the service provider.');

$section->addInput(new Form_Checkbox(
	'pppoe_dialondemand',
	'Dial on demand',
	'Enable Dial-On-Demand mode ',
	$pconfig['pppoe_dialondemand'],
	'enable'
));

$section->addInput(new Form_Input(
	'pppoe_idletimeout',
	'Idle timeout',
	'number',
	$pconfig['pppoe_idletimeout'],
	['min' => 0]
))->setHelp('If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. ' .
			'An idle timeout of zero disables this feature.');

$section->addInput(new Form_Select(
	'pppoe-reset-type',
	'Periodic reset',
	$pconfig['pppoe-reset-type'],
	['' => gettext('Disabled'), 'custom' => gettext('Custom'), 'preset' => gettext('Pre-set')]
))->setHelp('Select a reset timing type.');

$group = new Form_Group('Custom reset');
$group->addClass('pppoecustom');

$group->add(new Form_Input(
	'pppoe_resethour',
	null,
	'number',
	$pconfig['pppoe_resethour'],
	['min' => 0, 'max' => 23]
))->setHelp('Hour (0-23)');

$group->add(new Form_Input(
	'pppoe_resetminute',
	null,
	'number',
	$pconfig['pppoe_resetminute'],
	['min' => 0, 'max' => 59]
))->setHelp('Minutes (0-59)');

$group->add(new Form_Input(
	'pppoe_resetdate',
	null,
	'text',
	$pconfig['pppoe_resetdate']
))->setHelp('Specific date (mm/dd/yyyy)');

$group->setHelp('Leave the date field empty, for the reset to be executed each day at the time specified by the minutes and hour fields');

$section->add($group);

$group = new Form_MultiCheckboxGroup('cron based reset');
$group->addClass('pppoepreset');

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each month ("0 0 1 * *")',
	$pconfig['pppoe_monthly'],
	'monthly'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each week ("0 0 * * 0")',
	$pconfig['pppoe_weekly'],
	'weekly'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each day ("0 0 * * *")',
	$pconfig['pppoe_daily'],
	'daily'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each hour ("0 * * * *")',
	$pconfig['pppoe_hourly'],
	'hourly'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced and MLPPP',
	isset($pconfig['pppid']) ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars($pconfig['pppid']) : 'interfaces_ppps_edit.php',
	'fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('Click for additional PPPoE configuration options. Save first if changes have been made.');

$form->add($section);

// PPTP & L2TP Configuration section
$section = new Form_Section('PPTP/L2TP Configuration');
$section->addClass('pptp');

$section->addInput(new Form_Input(
	'pptp_username',
	'*Username',
	'text',
	$pconfig['pptp_username'],
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'pptp_password',
	'*Password',
	'password',
	$pconfig['pptp_password']
));

$group = new Form_Group('Shared Secret');

$group->add(new Form_Input(
	'l2tp_secret',
	'*Secret',
	'password',
	$pconfig['l2tp_secret']
))->setHelp('L2TP tunnel Shared Secret. Used to authenticate tunnel connection and encrypt ' .
	    'important control packet contents. (Optional)');

$group->addClass('l2tp_secret');
$section->add($group);

$section->addInput(new Form_IpAddress(
	'pptp_local0',
	'*Local IP address',
	$pconfig['pptp_localip'][0],
	'V4'
))->addMask('pptp_subnet0', $pconfig['pptp_subnet'][0]);

$section->addInput(new Form_IpAddress(
	'pptp_remote0',
	'*Remote IP address',
	$pconfig['pptp_remote'][0],
	'HOSTV4'
));

$section->addInput(new Form_Checkbox(
	'pptp_dialondemand',
	'Dial on demand',
	'Enable Dial-On-Demand mode ',
	$pconfig['pptp_dialondemand'],
	'enable'
))->setHelp('This option causes the interface to operate in dial-on-demand mode, allowing it to be a virtual full time connection. ' .
			'The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.');

$section->addInput(new Form_Input(
	'pptp_idletimeout',
	'Idle timeout (seconds)',
	'number',
	$pconfig['pptp_idletimeout'],
	['min' => 0]
))->setHelp('If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. ' .
			'An idle timeout of zero disables this feature.');

if (isset($pconfig['pptp_localip'][1]) || isset($pconfig['pptp_subnet'][1]) || isset($pconfig['pptp_remote'][1])) {
	$mlppp_text = gettext("There are additional Local and Remote IP addresses defined for MLPPP.") . "<br />";
} else {
	$mlppp_text = "";
}

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced and MLPPP',
	isset($pconfig['pppid']) ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars($pconfig['pppid']) : 'interfaces_ppps_edit.php',
	'fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('%sClick for additional PPTP and L2TP configuration options. Save first if changes have been made.', $mlppp_text);

$form->add($section);

// Wireless interface
if (isset($wancfg['wireless'])) {

	$section = new Form_Section('Common Wireless Configuration - Settings apply to all wireless networks on ' . $wlanbaseif . '.');

	$section->addInput(new Form_Checkbox(
		'persistcommonwireless',
		'Persist common settings',
		'Preserve common wireless configuration through interface deletions and reassignments.',
		$pconfig['persistcommonwireless'],
		'yes'
	));

	$mode_list = ['auto' => 'Auto'];

	if (is_array($wl_modes)) {
		foreach ($wl_modes as $wl_standard => $wl_channels) {
			$mode_list[$wl_standard] = '802.' . $wl_standard;
		}
	}

	if (count($mode_list) == 1) {
		$mode_list[''] = '';
	}

	$section->addInput(new Form_Select(
		'standard',
		'Standard',
		($pconfig['standard'] == "") ? "11ng":$pconfig['standard'],
		$mode_list
	));

	if (isset($wl_modes['11g'])) {
		$section->addInput(new Form_Select(
			'protmode',
			'802.11g OFDM Protection Mode',
			$pconfig['protmode'],
			['off' => gettext('Off'), 'cts' => gettext('CTS to self'), 'rtscts' => gettext('RTS and CTS')]
		))->setHelp('For IEEE 802.11g, use the specified technique for protecting OFDM frames in a mixed 11b/11g network.');
	} else {
		$form->addGlobal(new Form_Input(
			'protmode',
			null,
			'hidden',
			'off'
		));
	}

	$mode_list = ['0' => gettext('Auto')];

	if (is_array($wl_modes)) {
		foreach ($wl_modes as $wl_standard => $wl_channels) {
			if ($wl_standard == "11g") {
				$wl_standard = "11b/g";
			} else if ($wl_standard == "11ng") {
				$wl_standard = "11b/g/n";
			} else if ($wl_standard == "11na") {
				$wl_standard = "11a/n";
			}

			foreach ($wl_channels as $wl_channel) {
				if (isset($wl_chaninfo[$wl_channel])) {
					$mode_list[$wl_channel] = $wl_standard . ' - ' . $wl_channel;
				} else {
					$mode_list[$wl_channel] = $wl_standard . ' - ' . $wl_channel . ' (' . $wl_chaninfo[$wl_channel][1] . ' @ ' . $wl_chaninfo[$wl_channel][2] . ' / ' . $wl_chaninfo[$wl_channel][3] . ')';
				}
			}
		}
	}

	$section->addInput(new Form_Select(
		'channel',
		'Channel',
		$pconfig['channel'],
		$mode_list
	))->setHelp('Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain) %1$s' .
				'Not all channels may be supported by some cards.  Auto may override the wireless standard selected above.', '<br />');

	$section->addInput(new Form_Select(
		'channel_width',
		'Channel width',
		$pconfig['channel_width'],
		$wl_ht_modes
	))->setHelp('Channel width for 802.11n mode. Not all cards may support channel width changing.');

	if (ANTENNAS) {
		if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])) {
			$group = new Form_Group('Antenna Settings');

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"])) {
				$group->add(new Form_Select(
					'diversity',
					null,
					(isset($pconfig['diversity'])) ? $pconfig['diversity']:'',
					['' => gettext('Default'), '0' => gettext('Off'), '1' => gettext('On')]
				))->setHelp('Diversity');
			}

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"])) {
				$group->add(new Form_Select(
					'txantenna',
					null,
					(isset($pconfig['txantenna'])) ? $pconfig['txantenna']:'',
					['' => gettext('Default'), '0' => gettext('Auto'), '1' => gettext('#1'), '2' => gettext('#2')]
				))->setHelp('Transmit antenna');
			}

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])) {
				$group->add(new Form_Select(
					'rxantenna',
					null,
					(isset($pconfig['rxantenna'])) ? $pconfig['rxantenna']:'',
					['' => gettext('Default'), '0' => gettext('Auto'), '1' => gettext('#1'), '2' => gettext('#2')]
				))->setHelp('Receive antenna');
			}

			$group->setHelp('Note: The antenna numbers do not always match up with the labels on the card.');

			$section->add($group);
		}
	}

	if (isset($wl_sysctl["{$wl_sysctl_prefix}.slottime"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.acktimeout"]) && isset($wl_sysctl["{$wl_sysctl_prefix}.ctstimeout"])) {
			$section->addInput(new Form_Input(
				'distance',
				'Distance setting (meters)',
				'test',
				$pconfig['distance']
			))->setHelp('This field can be used to tune ACK/CTS timers to fit the distance between AP and Client');
	}

	$form->add($section);

	// Regulatory settings
	$section = new Form_Section('Regulatory Settings');

	$domain_list = array("" => 'Default');

	if (is_array($wl_regdomains)) {
		foreach ($wl_regdomains as $wl_regdomain_key => $wl_regdomain) {
			$domain_list[$wl_regdomains_attr[$wl_regdomain_key]['ID']] = $wl_regdomain['name'];
		}
	}

	$section->addInput(new Form_Select(
		'regdomain',
		'Regulatory domain',
		$pconfig['regdomain'],
		$domain_list
	))->setHelp('Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work');

	$country_list = array('' => 'Default');

	if (is_array($wl_countries)) {
		foreach ($wl_countries as $wl_country_key => $wl_country) {
			$country_list[	$wl_countries_attr[$wl_country_key]['ID']  ] = $wl_country['name'] ; //. ' -- (' . $wl_countries_attr[$wl_country_key]['ID'] . ', ' . strtoupper($wl_countries_attr[$wl_country_key]['rd'][0]['REF']);
		}
	}

	$section->addInput(new Form_Select(
		'regcountry',
		'Country',
		$pconfig['regcountry'],
		$country_list
	))->setHelp('Any country setting other than "Default" will override the regulatory domain setting');

	$section->addInput(new Form_Select(
		'reglocation',
		'Location',
		$pconfig['reglocation'],
		['' => gettext('Default'), 'indoor' => gettext('Indoor'), 'outdoor' => gettext('Outdoor'), 'anywhere' => gettext('Anywhere')]
	))->setHelp('These settings may affect which channels are available and the maximum transmit power allowed on those channels. ' .
				'Using the correct settings to comply with local regulatory requirements is recommended.%1$s' .
				'All wireless networks on this interface will be temporarily brought down when changing regulatory settings.  ' .
				'Some of the regulatory domains or country codes may not be allowed by some cards.	' .
				'These settings may not be able to add additional channels that are not already supported.', '<br />');

	$form->add($section);

	$section = new Form_Section('Network-Specific Wireless Configuration');

	$section->addInput(new Form_Select(
		'mode',
		'Mode',
		$pconfig['mode'],
		['bss' => gettext('Infrastructure (BSS)'), 'adhoc' => gettext('Ad-hoc (IBSS)'), 'hostap' => gettext('Access Point')]
	));

	$section->addInput(new Form_Input(
		'ssid',
		'SSID',
		'text',
		$pconfig['ssid']
	));

	if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])) {
		$section->addInput(new Form_Select(
			'puremode',
			'Minimum wireless standard',
			$pconfig['puremode'],
			['any' => gettext('Any'), '11g' => gettext('802.11g'), '11n' => gettext('802.11n')]
		))->setHelp('When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)');
	} elseif (isset($wl_modes['11g'])) {
		$section->addInput(new Form_Checkbox(
			'puremode',
			'802.11g only',
			null,
			$pconfig['puremode'],
			'11g'
		))->setHelp('When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)');
	}

	$section->addInput(new Form_Checkbox(
		'apbridge_enable',
		'Allow intra-BSS communication',
		'Allow packets to pass between wireless clients directly when operating as an access point',
		$pconfig['apbridge_enable'],
		'yes'
	))->setHelp('Provides extra security by isolating clients so they cannot directly communicate with one another');

	$section->addInput(new Form_Checkbox(
		'wme_enable',
		'Enable WME',
		'Force the card to use WME (wireless QoS)',
		$pconfig['wme_enable'],
		'yes'
	));

	$section->addInput(new Form_Checkbox(
		'hidessid_enable',
		'Hide SSID',
		'Disable broadcasting of the SSID for this network (This may cause problems for some clients, and the SSID may still be discovered by other means.)',
		$pconfig['hidessid_enable'],
		'yes'
	));

	$form->add($section);

	// WPA Section
	$section = new Form_Section('WPA');

	$section->addInput(new Form_Checkbox(
		'wpa_enable',
		'Enable',
		'Enable WPA',
		$pconfig['wpa_enable'],
		'yes'
	));

	$section->addInput(new Form_Select(
		'wpa_mode',
		'WPA mode',
		(isset($pconfig['wpa_mode'])) ? $pconfig['wpa_mode']: '2',
		['1' => gettext('WPA'), '2' => gettext('WPA2'), '3' => gettext('Both')]
	));

	$section->addInput(new Form_Select(
		'wpa_pairwise',
		'WPA Pairwise',
		(isset($pconfig['wpa_pairwise'])) ? $pconfig['wpa_pairwise']:'CCMP',
		['CCMP TKIP' => gettext('Both'), 'CCMP' => gettext('AES (recommended)'), 'TKIP' => gettext('TKIP')]
	));

	$section->addInput(new Form_Select(
		'wpa_key_mgmt',
		'WPA Key Management Mode',
		$pconfig['wpa_key_mgmt'],
		['WPA-PSK' => gettext('Pre-Shared Key'), 'WPA-EAP' => gettext('Extensible Authentication Protocol'), 'WPA-PSK WPA-EAP' => gettext('Both')]
	));

	$section->addInput(new Form_Input(
		'passphrase',
		'WPA Pre-Shared Key',
		'text',
		$pconfig['passphrase']
	))->setHelp('WPA Passphrase must be between 8 and 63 characters long');

	$section->addInput(new Form_Select(
		'wpa_eap_client_mode',
		'EAP Client Mode',
		$pconfig['wpa_eap_client_mode'],
		['PEAP' => 'PEAP', 'TLS' => 'TLS', 'TTLS' => 'TTLS']
	));

	$section->addInput(new Form_Select(
		'wpa_eap_ca',
		'Certificate Authority',
		$pconfig['wpa_eap_ca'],
		cert_build_list('ca', 'HTTPS')
	));

	$section->addInput(new Form_Select(
		'wpa_eap_inner_auth',
		'Inner Authentication Method',
		$pconfig['wpa_eap_inner_auth'],
		['MSCHAPV2' => gettext('MSCHAPv2'), 'MD5' => gettext('MD5'), 'PAP' => gettext('PAP')]
	));

	$section->addInput(new Form_Input(
		'wpa_eap_inner_id',
		'*Inner Authentication Identity',
		'text',
		$pconfig['wpa_eap_inner_id']
	));

	$section->addInput(new Form_Input(
		'wpa_eap_inner_password',
		'*Inner Authentication Passphrase',
		'text',
		$pconfig['wpa_eap_inner_password']
	));

	$section->addInput(new Form_Select(
		'wpa_eap_cert',
		'TLS/TTLS Client Certificate',
		$pconfig['wpa_eap_cert'],
		cert_build_list('cert', 'HTTPS')
	));

	$section->addInput(new Form_Input(
		'wpa_group_rekey',
		'Group Key Rotation',
		'number',
		$pconfig['wpa_group_rekey'] ? $pconfig['wpa_group_rekey'] : "60",
		['min' => '1', 'max' => 9999]
	))->setHelp('Time between group rekey events, specified in seconds. Allowed values are 1-9999. Must be shorter than Master Key Regeneration time');

	$section->addInput(new Form_Input(
		'wpa_gmk_rekey',
		'Group Master Key Regeneration',
		'number',
		$pconfig['wpa_gmk_rekey'] ? $pconfig['wpa_gmk_rekey'] : "3600",
		['min' => '1', 'max' => 9999]
	))->setHelp('Time between GMK rekey events, specified in seconds. Allowed values are 1-9999. Must be longer than Group Key Rotation time');

	$section->addInput(new Form_Checkbox(
		'wpa_strict_rekey',
		'Strict Key Regeneration',
		'Force the AP to rekey whenever a client disassociates',
		$pconfig['wpa_strict_rekey'],
		'yes'
	));

	$form->add($section);

	$section = new Form_Section('802.1x RADIUS Options');
	$section->addClass('ieee8021x_group');

	$section->addInput(new Form_Checkbox(
		'ieee8021x',
		'IEEE802.1X',
		'Enable 802.1X authentication',
		$pconfig['ieee8021x'],
		'yes'
	));

	$group = new Form_Group('Primary 802.1X server');

	$group->add(new Form_IpAddress(
		'auth_server_addr',
		'IP Address',
		$pconfig['auth_server_addr']
	))->setHelp('IP address of the RADIUS server');

	$group->add(new Form_Input(
		'auth_server_port',
		'Port',
		'number',
		$pconfig['auth_server_port']
	))->setHelp('Server auth port. Default is 1812');

	$group->add(new Form_Input(
		'auth_server_shared_secret',
		'Shared Secret',
		'text',
		$pconfig['auth_server_shared_secret']
	))->setHelp('RADIUS Shared secret for this firewall');

	$section->add($group);

	$group = new Form_Group('Secondary 802.1X server');

	$group->add(new Form_IpAddress(
		'auth_server_addr2',
		'IP Address',
		$pconfig['auth_server_addr2']
	))->setHelp('IP address of the RADIUS server');

	$group->add(new Form_Input(
		'auth_server_port2',
		'Port',
		'number',
		$pconfig['auth_server_port2']
	))->setHelp('Server auth port. Default is 1812');

	$group->add(new Form_Input(
		'auth_server_shared_secret2',
		'Shared Secret',
		'text',
		$pconfig['auth_server_shared_secret2']
	))->setHelp('RADIUS Shared secret for this firewall');

	$section->add($group);

	$section->addInput(new Form_Checkbox(
		'rsn_preauth',
		'Authentication Roaming Preauth',
		null,
		$pconfig['rsn_preauth'],
		'yes'
	))->setHelp('Pre-authentication to speed up roaming between access points.');

	$form->add($section);
}

$section = new Form_Section('Reserved Networks');

$section->addInput(new Form_Checkbox(
	'blockpriv',
	'Block private networks and loopback addresses',
	'',
	$pconfig['blockpriv'],
	'yes'
))->setHelp('Blocks traffic from IP addresses that are reserved for private networks per RFC 1918 (10/8, 172.16/12, 192.168/16) ' .
			'and unique local addresses per RFC 4193 (fc00::/7) as well as loopback addresses (127/8). This option should ' .
			'generally be turned on, unless this network interface resides in such a private address space, too.');

$section->addInput(new Form_Checkbox(
	'blockbogons',
	'Block bogon networks',
	'',
	$pconfig['blockbogons'],
	'yes'
))->setHelp('Blocks traffic from reserved IP addresses (but not RFC 1918) or not yet assigned by IANA. Bogons are prefixes that should ' .
			'never appear in the Internet routing table, and so should not appear as the source address in any packets received.%1$s' .
			'This option should only be used on external interfaces (WANs), it is not necessary on local interfaces and it can potentially block required local traffic.%1$s' .
			'Note: The update frequency can be changed under System > Advanced, Firewall & NAT settings.', '<br />');

$form->add($section);

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

if ($wancfg['if'] == $a_ppps[$pppid]['if']) {
	$form->addGlobal(new Form_Input(
		'ppp_port',
		null,
		'hidden',
		$pconfig['port']
	));
}

$form->addGlobal(new Form_Input(
	'ptpid',
	null,
	'hidden',
	$pconfig['ptpid']
));


// Add new gateway modal pop-up
$modal = new Modal('New IPv4 Gateway', 'newgateway4', 'large');

$modal->addInput(new Form_Checkbox(
	'defaultgw4',
	'Default',
	'Default gateway',
	isset($gateway_settings4['defaultgw']) ? $gateway_settings4['defaultgw'] : ($if == "wan" || $if == "WAN")
));

$modal->addInput(new Form_Input(
	'gatewayname4',
	'Gateway name',
	'text',
	($gateway_settings4['name'] == "") ? $defgatewayname4 : $gateway_settings4['name']
));

$modal->addInput(new Form_IpAddress(
	'gatewayip4',
	'Gateway IPv4',
	$gateway_settings4['gateway'],
	'V4'
));

$modal->addInput(new Form_Input(
	'gatewaydescr4',
	'Description',
	'text',
	$gateway_settings4['descr']
));

$btnaddgw4 = new Form_Button(
	'add4',
	'Add',
	null,
	'fa-plus'
);

$btnaddgw4->setAttribute('type','button')->addClass('btn-success');

$btncnxgw4 = new Form_Button(
	'cnx4',
	'Cancel',
	null,
	'fa-undo'
);

$btncnxgw4->setAttribute('type','button')->addClass('btn-warning');

$modal->addInput(new Form_StaticText(
	null,
	$btnaddgw4 . $btncnxgw4
));

$form->add($modal);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function updateType(t) {

		switch (t) {
			case "none": {
				$('.dhcpadvanced, .staticv4, .dhcp, .pppoe, .pptp, .ppp').hide();
				break;
			}
			case "staticv4": {
				$('.dhcpadvanced, .none, .dhcp').hide();
				$('.pppoe, .pptp, .ppp').hide();
				break;
			}
			case "dhcp": {
				$('.dhcpadvanced, .none').hide();
				$('.staticv4').hide();	// MYSTERY: This line makes the page very slow to load, but why? There is nothing special
										//			about the staticv4 class
				$('.pppoe, .pptp, .ppp').hide();
				break;
			}
			case "ppp": {
				$('.dhcpadvanced, .none, .staticv4, .dhcp, .pptp, .pppoe').hide();
				country_list();
				break;
			}
			case "pppoe": {
				$('.dhcpadvanced, .none, .staticv4, .dhcp, .pptp, .ppp').hide();
				break;
			}
			case "l2tp": {
				$('.dhcpadvanced, .none, .staticv4, .dhcp, .pppoe, .ppp').hide();
				$('.pptp, .l2tp_secret').show();
				break;
			}
			case "pptp": {
				$('.dhcpadvanced, .none, .staticv4, .dhcp, .pppoe, .ppp, .l2tp_secret').hide();
				$('.pptp').show();
				break;
			}
		}

		if (t != "l2tp" && t != "pptp") {
			$('.'+t).show();
		}
	}

	function updateTypeSix(t) {
		if (!isNaN(t[0])) {
			t = '_' + t;
		}

		switch (t) {
			case "none": {
				$('.dhcp6advanced, .staticv6, .dhcp6, ._6rd, ._6to4, .track6, .slaac').hide();
				break;
			}
			case "staticv6": {
				$('.dhcp6advanced, .none, .dhcp6, ._6rd, ._6to4, .track6, .slaac').hide();
				break;
			}
			case "slaac": {
				$('.dhcp6advanced, .none, .staticv6, ._6rd, ._6to4, .track6, .dhcp6').hide();
				break;
			}
			case "dhcp6": {
				$('.dhcp6advanced, .none, .staticv6, ._6rd, ._6to4, .track6, .slaac').hide();
				break;
			}
			case "_6rd": {
				$('.dhcp6advanced, .none, .dhcp6, .staticv6, ._6to4, .track6, .slaac').hide();
				break;
			}
			case "_6to4": {
				$('.dhcp6advanced, .none, .dhcp6, .staticv6, ._6rd, .track6, .slaac').hide();
				break;
			}
			case "track6": {
				$('.dhcp6advanced, .none, .dhcp6, .staticv6, ._6rd, ._6to4, .slaac').hide();
				update_track6_prefix();
				break;
			}
		}

		if (t != "l2tp" && t != "pptp") {
			$('.'+t).show();
		}
	}

	function show_reset_settings(reset_type) {
		if (reset_type == 'preset') {
			$('.pppoepreset').show();
			$('.pppoecustom').hide();
		} else if (reset_type == 'custom') {
			$('.pppoecustom').show();
			$('.pppoepreset').hide();
		} else {
			$('.pppoecustom').hide();
			$('.pppoepreset').hide();
		}
	}

	function update_track6_prefix() {
		var iface = $("#track6-interface").val();
		if (iface == null) {
			return;
		}

		var track6_prefix_ids = $('#ipv6-num-prefix-ids-' + iface).val();
		if (track6_prefix_ids == null) {
			return;
		}

		track6_prefix_ids = parseInt(track6_prefix_ids).toString(16);
		$('#track6-prefix-id-range').html(track6_prefix_ids);
	}

	function addOption_v4() {
		var gwtext_v4 = escape($("#gatewayname4").val()) + " - " + $("#gatewayip4").val();
		addSelectboxOption($('#gateway'), gwtext_v4, $("#gatewayname4").val());
	}

	function addOption_v6() {
		var gwtext_v6 = escape($("#gatewayname6").val()) + " - " + $("#gatewayip6").val();
		addSelectboxOption($('#gatewayv6'), gwtext_v6, $("#gatewayname6").val());
	}

	function addSelectboxOption(selectbox, text, value) {
		var optn = document.createElement("OPTION");
		optn.text = text;
		optn.value = value;
		selectbox.append(optn);
		selectbox.prop('selectedIndex', selectbox.children().length - 1);
	}

	function country_list() {
		$('#country').children().remove();
		$('#provider_list').children().remove();
		$('#providerplan').children().remove();
		$.ajax("getserviceproviders.php",{
			success: function(response) {

				var responseTextArr = response.split("\n");
				responseTextArr.sort();

				responseTextArr.forEach( function(value) {
					country = value.split(":");
					$('#country').append($('<option>', {
						value: country[1],
						text : country[0]
					}));
				});
			}
		});
	}

	function providers_list() {
		$('#provider_list').children().remove();
		$('#providerplan').children().remove();
		$.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : $('#country').val()},
			success: function(response) {
				var responseTextArr = response.split("\n");
				responseTextArr.sort();
				responseTextArr.forEach( function(value) {
					$('#provider_list').append($('<option>', {
							value: value,
							text : value
					}));
				});
			}
		});
	}

	function providerplan_list() {
		$('#providerplan').children().remove();
		$.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : $('#country').val(), provider : $('#provider_list').val()},
			success: function(response) {
				var responseTextArr = response.split("\n");
				responseTextArr.sort();

				$('#providerplan').append($('<option>', {
					value: '',
					text : ''
				}));

				responseTextArr.forEach( function(value) {
					if (value != "") {
						providerplan = value.split(":");

						$('#providerplan').append($('<option>', {
							value: providerplan[1],
							text : providerplan[0] + " - " + providerplan[1]
						}));
					}
				});
			}
		});
	}

	function prefill_provider() {
		$.ajax("getserviceproviders.php",{
			type: 'post',
			data: {country : $('#country').val(), provider : $('#provider_list').val(), plan : $('#providerplan').val()},
			success: function(data, textStatus, response) {
				var xmldoc = response.responseXML;
				var provider = xmldoc.getElementsByTagName('connection')[0];
				$('#ppp_username').val('');
				$('#ppp_password').val('');
				if (provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
					$('#phone').val('#777');
					$('#apn').val('');
				} else {
					$('#phone').val('*99#');
					$('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
				}
				ppp_username = provider.getElementsByTagName('ppp_username')[0].firstChild.data;
				ppp_password = provider.getElementsByTagName('ppp_password')[0].firstChild.data;
				$('#ppp_username').val(ppp_username);
				$('#ppp_password').val(ppp_password);
			}
		});
	}

	function show_dhcp6adv() {
		var ovr = $('#adv_dhcp6_config_file_override').prop('checked');
		var adv = $('#adv_dhcp6_config_advanced').prop('checked');

		hideCheckbox('dhcp6usev4iface', ovr);
		hideCheckbox('dhcp6prefixonly', ovr);
		hideInput('dhcp6-ia-pd-len', ovr);
		hideCheckbox('dhcp6-ia-pd-send-hint', ovr);
		hideInput('adv_dhcp6_config_file_override_path', !ovr);

		hideClass('dhcp6advanced', !adv || ovr);
	}

	function setDHCPoptions() {
		var adv = $('#adv_dhcp_config_advanced').prop('checked');
		var ovr = $('#adv_dhcp_config_file_override').prop('checked');

		if (ovr) {
			hideInput('dhcphostname', true);
			hideIpAddress('alias-address', true);
			hideInput('dhcprejectfrom', true);
			hideInput('adv_dhcp_config_file_override_path', false);
			hideClass('dhcpadvanced', true);
		} else {
			hideInput('dhcphostname', false);
			hideIpAddress('alias-address', false);
			hideInput('dhcprejectfrom', false);
			hideInput('adv_dhcp_config_file_override_path', true);
			hideClass('dhcpadvanced', !adv);
		}
	}

	// DHCP preset actions
	// Set presets from value of radio buttons
	function setPresets(val) {
		// timeout, retry, select-timeout, reboot, backoff-cutoff, initial-interval
		if (val == "DHCP")		setPresetsnow("60", "300", "0", "10", "120", "10");
		if (val == "pfSense")	setPresetsnow("60", "15", "0", "", "", "1");
		if (val == "SavedCfg")	setPresetsnow("<?=htmlspecialchars($pconfig['adv_dhcp_pt_timeout']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_retry']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_select_timeout']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_reboot']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_backoff_cutoff']);?>", "<?=htmlspecialchars($pconfig['adv_dhcp_pt_initial_interval']);?>");
		if (val == "Clear")		setPresetsnow("", "", "", "", "", "");
	}

	function setPresetsnow(timeout, retry, selecttimeout, reboot, backoffcutoff, initialinterval) {
		$('#adv_dhcp_pt_timeout').val(timeout);
		$('#adv_dhcp_pt_retry').val(retry);
		$('#adv_dhcp_pt_select_timeout').val(selecttimeout);
		$('#adv_dhcp_pt_reboot').val(reboot);
		$('#adv_dhcp_pt_backoff_cutoff').val(backoffcutoff);
		$('#adv_dhcp_pt_initial_interval').val(initialinterval);
	}

	function setPPPoEDialOnDemandItems() {
		setRequired('pppoe_idletimeout', $('#pppoe_dialondemand').prop('checked'));
	}

	function setPPTPDialOnDemandItems() {
		setRequired('pptp_idletimeout', $('#pptp_dialondemand').prop('checked'));
	}

	function show_wpaoptions() {
		var wpa = !($('#wpa_enable').prop('checked'));

		hideInput('passphrase', wpa);
		hideInput('wpa_mode', wpa);
		hideInput('wpa_key_mgmt', wpa);
		hideInput('wpa_pairwise', wpa);
		hideCheckbox('wpa_strict_rekey', wpa);
		hideClass('ieee8021x_group', true);
		if ($('#mode').val() == 'hostap') {
			hideInput('wpa_group_rekey', wpa);
			hideInput('wpa_gmk_rekey', wpa);
			hideCheckbox('wpa_strict_rekey', wpa);
		} else {
			hideInput('wpa_group_rekey', true);
			hideInput('wpa_gmk_rekey', true);
			hideCheckbox('wpa_strict_rekey', true);
		}
		updatewpakeymgmt($('#wpa_key_mgmt').val());
	}

	function updatewifistandard(s) {
		switch (s) {
			case "auto": {
				hideInput('protmode', false);
				hideInput('channel_width', false);
				break;
			}
			case "11b": {
				hideInput('protmode', true);
				hideInput('channel_width', true);
				break;
			}
			case "11g": {
				hideInput('protmode', false);
				hideInput('channel_width', true);
				break;
			}
			case "11ng": {
				hideInput('protmode', false);
				hideInput('channel_width', false);
				break;
			}
			case "11a": {
				hideInput('protmode', true);
				hideInput('channel_width', true);
				break;
			}
			case "11na": {
				hideInput('protmode', true);
				hideInput('channel_width', false);
				break;
			}
			default: {
				break;
			}
		}
	}

	function updatewifimode(m) {
		switch (m) {
			case "adhoc": {
				hideInput('puremode', true);
				hideCheckbox('apbridge_enable', true);
				hideCheckbox('hidessid_enable', false);
				break;
			}
			case "hostap": {
				hideInput('puremode', false);
				hideCheckbox('apbridge_enable', false);
				hideCheckbox('hidessid_enable', false);
				break;
			}
			default: {
				hideInput('puremode', true);
				hideCheckbox('apbridge_enable', true);
				hideCheckbox('hidessid_enable', true);
				break;
			}
		}
		show_wpaoptions();
	}

	function updateeapclientmode(m) {
		var wpa = !($('#wpa_enable').prop('checked'));
		switch (m) {
			case "PEAP": {
				hideInput('wpa_eap_cert', true);
				hideInput('wpa_eap_inner_auth', wpa);
				hideInput('wpa_eap_inner_id', wpa);
				hideInput('wpa_eap_inner_password', wpa);
				break;
			}
			case "TLS": {
				hideInput('wpa_eap_cert', wpa);
				hideInput('wpa_eap_inner_auth', true);
				hideInput('wpa_eap_inner_id', true);
				hideInput('wpa_eap_inner_password', true);
				break;
			}
			case "TTLS": {
				hideInput('wpa_eap_cert', wpa);
				hideInput('wpa_eap_inner_auth', wpa);
				hideInput('wpa_eap_inner_id', wpa);
				hideInput('wpa_eap_inner_password', wpa);
				break;
			}
			default: {
				break;
			}
		}
	}

	function updatewpakeymgmt(m) {
		var wpa = !($('#wpa_enable').prop('checked'));
		if ((m == "WPA-EAP") && ($('#mode').val() == 'bss')) {
			hideInput('passphrase', true);
			hideInput('wpa_eap_client_mode', false);
			hideInput('wpa_eap_ca', false);
			updateeapclientmode($('#wpa_eap_client_mode').val());
		} else if ((m != "WPA-PSK") && ($('#mode').val() == 'hostap')) {
			hideClass('ieee8021x_group', false);
		} else {
			hideInput('passphrase', wpa);
			hideInput('wpa_eap_client_mode', true);
			hideInput('wpa_eap_ca', true);
			hideInput('wpa_eap_cert', true);
			hideInput('wpa_eap_inner_auth', true);
			hideInput('wpa_eap_inner_id', true);
			hideInput('wpa_eap_inner_password', true);
			hideClass('ieee8021x_group', true);
		}
	}

	// ---------- On initial page load ------------------------------------------------------------

	updateType($('#type').val());
	updateTypeSix($('#type6').val());
	show_reset_settings($('#pppoe-reset-type').val());
	hideClass('dhcp6advanced', true);
	hideClass('dhcpadvanced', true);
	show_dhcp6adv();
	setDHCPoptions();
	setPPPoEDialOnDemandItems();
	setPPTPDialOnDemandItems();
	show_wpaoptions();
	updatewifistandard($('#standard').val());
	updatewifimode($('#mode').val());
	updatewpakeymgmt($('#wpa_key_mgmt').val());
	updateeapclientmode($('#wpa_eap_client_mode').val());

	// Set preset buttons on page load
	var sv = "<?=htmlspecialchars($pconfig['adv_dhcp_pt_values']);?>";
	if (sv == "") {
		$("input[name=adv_dhcp_pt_values][value='SavedCfg']").prop('checked', true);
	} else {
		$("input[name=adv_dhcp_pt_values][value="+sv+"]").prop('checked', true);
	}

	// Set preset from value
	setPresets(sv);

	// If the user wants to add a gateway, then add that to the gateway selection
	if ($("#gatewayip4").val() != '') {
		addOption_v4();
	}
	if ($("#gatewayip6").val() != '') {
		addOption_v6();
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#type').on('change', function() {
		updateType(this.value);
	});

	$('#type6').on('change', function() {
		updateTypeSix(this.value);
	});

	$('#standard').on('change', function() {
		updatewifistandard(this.value);
	});

	$('#mode').on('change', function() {
		updatewifimode(this.value);
	});

	$('#wpa_key_mgmt').on('change', function() {
		updatewpakeymgmt(this.value);
	});

	$('#wpa_eap_client_mode').on('change', function() {
		updateeapclientmode(this.value);
	});

	$('#track6-interface').on('change', function() {
		update_track6_prefix();
	});

	$('#pppoe-reset-type').on('change', function() {
		show_reset_settings(this.value);
	});

	$("#add4").click(function() {
		addOption_v4();
		$("#newgateway4").modal('hide');
	});

	$("#cnx4").click(function() {
		$("#gatewayname4").val('<?=$defgatewayname4;?>');
		$("#gatewayip4").val('');
		$("#gatewaydescr4").val('');
		$("#defaultgw4").prop("checked", false);
		$("#newgateway4").modal('hide');
	});

	$("#add6").click(function() {
		addOption_v6();
		$("#newgateway6").modal('hide');
	});

	$("#cnx6").click(function() {
		$("#gatewayname6").val('<?=$defgatewayname6;?>');
		$("#gatewayip6").val('');
		$("#gatewaydescr6").val('');
		$("#defaultgw6").prop("checked", false);
		$("#newgateway6").modal('hide');
	});

	$('#country').on('change', function() {
		providers_list();
	});

	$('#provider_list').on('change', function() {
		providerplan_list();
	});

	$('#providerplan').on('change', function() {
		prefill_provider();
	});

	$('#adv_dhcp_config_advanced, #adv_dhcp_config_file_override').click(function () {
		setDHCPoptions();
	});

	$('#adv_dhcp6_config_advanced').click(function () {
		show_dhcp6adv();
	});

	$('#adv_dhcp6_config_file_override').click(function () {
		show_dhcp6adv();
	});

	// On click . .
	$('#pppoe_dialondemand').click(function () {
		setPPPoEDialOnDemandItems();
	});

	$('#pptp_dialondemand').click(function () {
		setPPTPDialOnDemandItems();
	});

	$('[name=adv_dhcp_pt_values]').click(function () {
	   setPresets($('input[name=adv_dhcp_pt_values]:checked').val());
	});

	$('#wpa_enable').click(function () {
		show_wpaoptions();
	});

	$('#pppoe_resetdate').datepicker();

});
//]]>
</script>

<?php include("foot.inc");
