<?php
/*
 * interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
require_once("util.inc");

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
$if = $_REQUEST['if'] ?? 'wan';
$bridged = link_interface_to_bridge($if);

if (empty($ifdescrs[$if])) {
	header("Location: interfaces.php");
	exit;
}

define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

if (!is_array($pconfig)) {
	$pconfig = [];
}

$a_ppps = config_get_path('ppps/ppp', []);
$old_ppps = $a_ppps;

$a_gateways = config_get_path('gateways/gateway_item', []);

$wancfg = config_get_path("interfaces/{$if}", []);
$realifname = get_real_interface($if);
$old_wancfg = $wancfg;
array_set_path($old_wancfg, 'realif', $realifname);

$interfaces = get_configured_interface_with_descr();

$gateway_settings4 = [];
$gateway_settings6 = [];

/* Interfaces which have addresses configured elsewhere and should not be
 * configured here. See https://redmine.pfsense.org/issues/8687 */
$no_address_interfaces = ["ovpn", "ipsec", "gif", "gre", "l2tps"];
$show_address_controls = true;
foreach ($no_address_interfaces as $ifbl) {
	if (substr($realifname, 0, strlen($ifbl)) == $ifbl) {
		$show_address_controls = false;
	}
}

// Populate page descr if it does not exist.
if ((strtolower($if) == "wan") &&
    empty(array_get_path($wancfg, 'descr'))) {
	array_set_path($wancfg, 'descr', "WAN");
} elseif ((strtolower($if) == "lan") &&
    empty(array_get_path($wancfg, 'descr'))) {
	array_set_path($wancfg, 'descr', "LAN");
}

/* NOTE: The code here is used to set the $pppid for the curious */
$pppid = null;
foreach ($a_ppps as $pid => $ppp) {
	if (array_get_path($wancfg, 'if') == array_get_path($ppp, 'if')) {
		$pppid = $pid;
		break;
	}
}

$type_disabled = (substr(array_get_path($wancfg, 'if', ''), 0, 3) == 'gre') ? 'disabled' : '';

if (array_get_path($wancfg, 'if') == array_get_path($a_ppps, "{$pppid}/if")) {
	array_set_path($pconfig, 'pppid', $pppid);
	array_set_path($pconfig, 'ptpid', array_get_path($a_ppps, "{$pppid}/ptpid"));
	array_set_path($pconfig, 'port', array_get_path($a_ppps, "{$pppid}/ports"));
	if (array_get_path($a_ppps, "{$pppid}/type") == "ppp") {
		array_set_path($pconfig, 'ppp_username', array_get_path($a_ppps, "{$pppid}/username", ''));
		array_set_path($pconfig, 'ppp_password', base64_decode(array_get_path($a_ppps, "{$pppid}/password", '')));
		array_set_path($pconfig, 'phone', array_get_path($a_ppps, "{$pppid}/phone", ''));
		array_set_path($pconfig, 'apn', array_get_path($a_ppps, "{$pppid}/apn", ''));
	} elseif (array_get_path($a_ppps, "{$pppid}/type") == "pppoe") {
		array_set_path($pconfig, 'pppoe_username', array_get_path($a_ppps, "{$pppid}/username", ''));
		array_set_path($pconfig, 'pppoe_password', base64_decode(array_get_path($a_ppps, "{$pppid}/password", '')));

		array_set_path($pconfig, 'provider', array_get_path($a_ppps, "{$pppid}/provider"));
		array_set_path($pconfig, 'hostuniq', array_get_path($a_ppps, "{$pppid}/hostuniq"));
		array_set_path($pconfig, 'pppoe_dialondemand', array_path_enabled($a_ppps, $pppid, 'ondemand'));
		array_set_path($pconfig, 'pppoe_idletimeout', array_get_path($a_ppps, "{$pppid}/idletimeout"));

		/* ================================================ */
		/* = force a connection reset at a specific time? = */
		/* ================================================ */

		if (array_path_enabled($a_ppps, $pppid, 'pppoe-reset-type')) {
			array_set_path($pconfig, 'pppoe-reset-type', array_get_path($a_ppps, "{$pppid}/pppoe-reset-type"));
			$itemhash = getMPDCRONSettings(array_get_path($a_ppps, "{$pppid}/if"));
			if (is_array($itemhash) &&
			    !empty($itemhash)) {
				$cronitem = array_get_path($itemhash, 'ITEM');
			}
			if (isset($cronitem)) {
				$resetTime  = array_get_path($cronitem, 'minute') . ' ';
				$resetTime .= array_get_path($cronitem, 'hour') . ' ';
				$resetTime .= array_get_path($cronitem, 'mday') . ' ';
				$resetTime .= array_get_path($cronitem, 'month') . ' ';
				$resetTime .= array_get_path($cronitem, 'wday');
			} else {
				$resetTime = NULL;
			}

			if (array_get_path($a_ppps, "{$pppid}/pppoe-reset-type") == 'custom') {
				if ($cronitem) {
					array_set_path($pconfig, 'pppoe_pr_custom', true);
					array_set_path($pconfig, 'pppoe_resetminute', array_get_path($cronitem, 'minute'));
					array_set_path($pconfig, 'pppoe_resethour', array_get_path($cronitem, 'hour'));
					if ((array_get_path($cronitem, 'mday') != "*") &&
					    (array_get_path($cronitem, 'month') != "*")) {
						$resetDate  = array_get_path($cronitem, 'month') . '/';
						$resetDate .= array_get_path($cronitem, 'mday') . '/';
						$resetDate .= date("Y");
						array_set_path($pconfig, 'pppoe_resetdate', $resetDate);
					}
				}
			} elseif (array_get_path($a_ppps, "{$pppid}/pppoe-reset-type") == 'preset') {
				array_set_path($pconfig, 'pppoe_pr_preset', true);
				switch ($resetTime) {
					case CRON_MONTHLY_PATTERN:
						array_set_path($pconfig, 'pppoe_monthly', true);
						break;
					case CRON_WEEKLY_PATTERN:
						array_set_path($pconfig, 'pppoe_weekly', true);
						break;
					case CRON_DAILY_PATTERN:
						array_set_path($pconfig, 'pppoe_daily', true);
						break;
					case CRON_HOURLY_PATTERN:
						array_set_path($pconfig, 'pppoe_hourly', true);
						break;
				}
			}
		} // End force pppoe reset at specific time
		// End if type == pppoe
	} elseif ((array_get_path($a_ppps, "{$pppid}/type") == "pptp") ||
		  array_get_path($a_ppps, "{$pppid}/type") == "l2tp") {
		array_set_path($pconfig, 'pptp_username', array_get_path($a_ppps, "{$pppid}/username", ''));
		array_set_path($pconfig, 'pptp_password', base64_decode(array_get_path($a_ppps, "{$pppid}/password", '')));
		if ((array_get_path($a_ppps, "{$pppid}/type") == 'l2tp') &&
		    !empty(array_get_path($a_ppps, "{$pppid}/secret"))) {
			array_set_path($pconfig, 'l2tp_secret', base64_decode(array_get_path($a_ppps, "{$pppid}/secret")));
		}
		array_set_path($pconfig, 'pptp_localip', explode(",", array_get_path($a_ppps, "{$pppid}/localip")));
		array_set_path($pconfig, 'pptp_subnet', explode(",", array_get_path($a_ppps, "{$pppid}/subnet")));
		array_set_path($pconfig, 'pptp_remote', explode(",", array_get_path($a_ppps, "{$pppid}/gateway")));
		array_set_path($pconfig, 'pptp_dialondemand', array_path_enabled($a_ppps, $pppid, 'ondemand'));
		array_set_path($pconfig, 'pptp_idletimeout', array_get_path($a_ppps, "{$pppid}/timeout"));
	}
} else {
	array_set_path($pconfig, 'ptpid', interfaces_ptpid_next());
	$pppid = count($a_ppps);
}

/* Settings to copy from $wancfg to $pconfig */
$copy_list = [
	'dhcphostname',
	'alias-address',
	'alias-subnet',
	'dhcprejectfrom',
	'adv_dhcp_pt_timeout',
	'adv_dhcp_pt_retry',
	'adv_dhcp_pt_select_timeout',
	'adv_dhcp_pt_reboot',
	'adv_dhcp_pt_backoff_cutoff',
	'adv_dhcp_pt_initial_interval',
	'adv_dhcp_pt_values',
	'adv_dhcp_send_options',
	'adv_dhcp_request_options',
	'adv_dhcp_required_options',
	'adv_dhcp_option_modifiers',
	'adv_dhcp_config_advanced',
	'adv_dhcp_config_file_override',
	'adv_dhcp_config_file_override_path',
	'adv_dhcp6_interface_statement_send_options',
	'adv_dhcp6_interface_statement_request_options',
	'adv_dhcp6_interface_statement_information_only_enable',
	'adv_dhcp6_interface_statement_script',
	'adv_dhcp6_id_assoc_statement_address_enable',
	'adv_dhcp6_id_assoc_statement_address',
	'adv_dhcp6_id_assoc_statement_address_id',
	'adv_dhcp6_id_assoc_statement_address_pltime',
	'adv_dhcp6_id_assoc_statement_address_vltime',
	'adv_dhcp6_id_assoc_statement_prefix_enable',
	'adv_dhcp6_id_assoc_statement_prefix',
	'adv_dhcp6_id_assoc_statement_prefix_id',
	'adv_dhcp6_id_assoc_statement_prefix_pltime',
	'adv_dhcp6_id_assoc_statement_prefix_vltime',
	'adv_dhcp6_prefix_interface_statement_sla_id',
	'adv_dhcp6_prefix_interface_statement_sla_len',
	'adv_dhcp6_prefix_selected_interface',
	'adv_dhcp6_authentication_statement_authname',
	'adv_dhcp6_authentication_statement_protocol',
	'adv_dhcp6_authentication_statement_algorithm',
	'adv_dhcp6_authentication_statement_rdm',
	'adv_dhcp6_key_info_statement_keyname',
	'adv_dhcp6_key_info_statement_realm',
	'adv_dhcp6_key_info_statement_keyid',
	'adv_dhcp6_key_info_statement_secret',
	'adv_dhcp6_key_info_statement_expire',
	'adv_dhcp6_config_advanced',
	'adv_dhcp6_config_file_override',
	'adv_dhcp6_config_file_override_path',
];
foreach ($copy_list as $sn) {
	array_set_path($pconfig, $sn, array_get_path($wancfg, $sn));
}

array_set_path($pconfig, 'dhcp_plus', array_path_enabled($wancfg, '', 'dhcp_plus'));
array_set_path($pconfig, 'descr', remove_bad_chars(array_get_path($wancfg, 'descr')));
array_set_path($pconfig, 'enable', array_path_enabled($wancfg, ''));

switch (array_get_path($wancfg, 'ipaddr')) {
	case "dhcp":
		array_set_path($pconfig, 'type', 'dhcp');
		array_set_path($pconfig, 'dhcpvlanenable', array_path_enabled($wancfg, '', 'dhcpvlanenable'));
		array_set_path($pconfig, 'dhcpcvpt', array_get_path($wancfg, 'dhcpcvpt'));
		break;
	case "pppoe":
	case "pptp":
	case "l2tp":
	case "ppp":
		array_set_path($pconfig, 'type', array_get_path($wancfg, 'ipaddr'));
		break;
	default:
		if (is_ipaddrv4(array_get_path($wancfg, 'ipaddr'))) {
			array_set_path($pconfig, 'type', 'staticv4');
			array_set_path($pconfig, 'ipaddr', array_get_path($wancfg, 'ipaddr'));
			array_set_path($pconfig, 'subnet', array_get_path($wancfg, 'subnet'));
			array_set_path($pconfig, 'gateway', array_get_path($wancfg, 'gateway'));
		} elseif (in_array(remove_ifindex(array_get_path($wancfg, 'if')), ["ppp", "pppoe", "pptp", "l2tp"])) {
			array_set_path($pconfig, 'type', remove_ifindex(array_get_path($wancfg, 'if')));
		} else {
			array_set_path($pconfig, 'type', 'none');
		}
		break;
}

switch (array_get_path($wancfg, 'ipaddrv6')) {
	case "slaac":
		array_set_path($pconfig, 'type6', 'slaac');
		array_set_path($pconfig, 'slaacusev4iface', array_path_enabled($wancfg, '', 'slaacusev4iface'));
		break;
	case "dhcp6":
		array_set_path($pconfig, 'type6', 'dhcp6');
		array_set_path($pconfig, 'dhcp6-duid', array_get_path($wancfg, 'dhcp6-duid'));
		if (is_null(array_get_path($wancfg, 'dhcp6-ia-pd-len'))) {
			array_set_path($wancfg, 'dhcp6-ia-pd-len', 'none');
		}
		array_set_path($pconfig, 'dhcp6-ia-pd-len', array_get_path($wancfg, 'dhcp6-ia-pd-len'));
		array_set_path($pconfig, 'dhcp6-ia-pd-send-hint', array_path_enabled($wancfg, '', 'dhcp6-ia-pd-send-hint'));
		array_set_path($pconfig, 'dhcp6prefixonly', array_path_enabled($wancfg, '', 'dhcp6prefixonly'));
		array_set_path($pconfig, 'dhcp6usev4iface', array_path_enabled($wancfg, '', 'dhcp6usev4iface'));
		array_set_path($pconfig, 'dhcp6withoutra', array_path_enabled($wancfg, '', 'dhcp6withoutra'));
		array_set_path($pconfig, 'dhcp6vlanenable', array_path_enabled($wancfg, '', 'dhcp6vlanenable'));
		array_set_path($pconfig, 'dhcp6cvpt', array_get_path($wancfg, 'dhcp6cvpt'));
		break;
	case "6to4":
		array_set_path($pconfig, 'type6', '6to4');
		break;
	case "track6":
		array_set_path($pconfig, 'type6', 'track6');
		array_set_path($pconfig, 'track6-interface', array_get_path($wancfg, 'track6-interface'));
		array_set_path($pconfig, 'track6-prefix-id', array_get_path($wancfg, 'track6-prefix-id', '0'));
		array_set_path($pconfig, 'track6-prefix-id--hex', sprintf("%x", array_get_path($pconfig, 'track6-prefix-id')));
		break;
	case "6rd":
		array_set_path($pconfig, 'type6', '6rd');
		array_set_path($pconfig, 'prefix-6rd', array_get_path($wancfg, 'prefix-6rd'));
		array_set_path($pconfig, 'prefix-6rd-v4plen', array_get_path($wancfg, 'prefix-6rd-v4plen', '0'));
		array_set_path($pconfig, 'gateway-6rd', array_get_path($wancfg, 'gateway-6rd'));
		break;
	default:
		if (is_ipaddrv6(array_get_path($wancfg, 'ipaddrv6'))) {
			array_set_path($pconfig, 'type6', 'staticv6');
			array_set_path($pconfig, 'ipv6usev4iface', array_path_enabled($wancfg, '', 'ipv6usev4iface'));
			array_set_path($pconfig, 'ipaddrv6', array_get_path($wancfg, 'ipaddrv6'));
			array_set_path($pconfig, 'subnetv6', array_get_path($wancfg, 'subnetv6'));
			array_set_path($pconfig, 'gatewayv6', array_get_path($wancfg, 'gatewayv6'));
		} else {
			array_set_path($pconfig, 'type6', 'none');
		}
		break;
}

array_set_path($pconfig, 'blockpriv', array_path_enabled($wancfg, '', 'blockpriv'));
array_set_path($pconfig, 'blockbogons', array_path_enabled($wancfg, '', 'blockbogons'));
array_set_path($pconfig, 'spoofmac', array_get_path($wancfg, 'spoofmac'));
array_set_path($pconfig, 'mtu', array_get_path($wancfg, 'mtu'));
array_set_path($pconfig, 'mss', array_get_path($wancfg, 'mss'));

/* Wireless interface? */
if (is_array(array_get_path($wancfg, 'wireless'))) {
	/* Sync first to be sure it displays the actual settings that will be used */
	interface_sync_wireless_clones($wancfg, false);
	/* Get wireless modes */
	$wlanif = get_real_interface($if);
	if (!does_interface_exist($wlanif)) {
		interface_wireless_clone($wlanif, $wancfg);
	}
	$wlanbaseif = interface_get_wireless_base(array_get_path($wancfg, 'if'));
	preg_match("/^(.*?)([0-9]*)$/", $wlanbaseif, $wlanbaseif_split);
	$wl_modes = get_wireless_modes($if);
	$wl_ht_modes = get_wireless_ht_modes($if);
	$wl_ht_list = get_wireless_ht_list($if);
	$wl_chaninfo = get_wireless_channel_info($if);
	$wl_sysctl_prefix = 'dev.' . $wlanbaseif_split[1] . '.' . $wlanbaseif_split[2];
	$wl_sysctl = get_sysctl(
		[
			"{$wl_sysctl_prefix}.diversity",
			"{$wl_sysctl_prefix}.txantenna",
			"{$wl_sysctl_prefix}.rxantenna",
			"{$wl_sysctl_prefix}.slottime",
			"{$wl_sysctl_prefix}.acktimeout",
			"{$wl_sysctl_prefix}.ctstimeout"
		]);
	$wl_regdomain_xml_attr = [];
	$wl_regdomain_xml = parse_xml_regdomain($wl_regdomain_xml_attr);
	$wl_regdomains = array_get_path($wl_regdomain_xml, 'regulatory-domains/rd');
	$wl_regdomains_attr = array_get_path($wl_regdomain_xml_attr, 'regulatory-domains/rd');
	$wl_countries = array_get_path($wl_regdomain_xml, 'country-codes/country');
	$wl_countries_attr = array_get_path($wl_regdomain_xml_attr, 'country-codes/country');

	array_set_path($pconfig, 'persistcommonwireless', config_path_enabled('wireless/interfaces', $wlanbaseif));
	array_set_path($pconfig, 'standard', array_get_path($wancfg, 'wireless/standard'));
	array_set_path($pconfig, 'mode', array_get_path($wancfg, 'wireless/mode'));
	array_set_path($pconfig, 'protmode', array_get_path($wancfg, 'wireless/protmode'));
	array_set_path($pconfig, 'ssid', array_get_path($wancfg, 'wireless/ssid'));
	array_set_path($pconfig, 'channel', array_get_path($wancfg, 'wireless/channel'));
	array_set_path($pconfig, 'channel_width', array_get_path($wancfg, 'wireless/channel_width'));
	array_set_path($pconfig, 'txpower', array_get_path($wancfg, 'wireless/txpower'));
	array_set_path($pconfig, 'diversity', array_get_path($wancfg, 'wireless/diversity'));
	array_set_path($pconfig, 'txantenna', array_get_path($wancfg, 'wireless/txantenna'));
	array_set_path($pconfig, 'rxantenna', array_get_path($wancfg, 'wireless/rxantenna'));
	array_set_path($pconfig, 'distance', array_get_path($wancfg, 'wireless/distance'));
	array_set_path($pconfig, 'regdomain', array_get_path($wancfg, 'wireless/regdomain'));
	array_set_path($pconfig, 'regcountry', array_get_path($wancfg, 'wireless/regcountry'));
	array_set_path($pconfig, 'reglocation', array_get_path($wancfg, 'wireless/reglocation'));
	array_set_path($pconfig, 'wme_enable', array_path_enabled($wancfg, 'wireless/wme'));

	if (array_path_enabled($wancfg, 'wireless/puren')) {
		array_set_path($pconfig, 'puremode', '11n');
	} elseif (array_path_enabled($wancfg, 'wireless/pureg')) {
		array_set_path($pconfig, 'puremode', '11g');
	} else {
		array_set_path($pconfig, 'puremode', 'any');
	}
	array_set_path($pconfig, 'apbridge_enable', array_path_enabled($wancfg, 'wireless/apbridge'));
	array_set_path($pconfig, 'authmode', array_get_path($wancfg, 'wireless/authmode'));
	array_set_path($pconfig, 'hidessid_enable', array_path_enabled($wancfg, 'wireless/hidessid'));
	array_set_path($pconfig, 'auth_server_addr', array_get_path($wancfg, 'wireless/auth_server_addr'));
	array_set_path($pconfig, 'auth_server_port', array_get_path($wancfg, 'wireless/auth_server_port'));
	array_set_path($pconfig, 'auth_server_shared_secret', array_get_path($wancfg, 'wireless/auth_server_shared_secret'));
	array_set_path($pconfig, 'auth_server_addr2', array_get_path($wancfg, 'wireless/auth_server_addr2'));
	array_set_path($pconfig, 'auth_server_port2', array_get_path($wancfg, 'wireless/auth_server_port2'));
	array_set_path($pconfig, 'auth_server_shared_secret2', array_get_path($wancfg, 'wireless/auth_server_shared_secret2'));
	if (is_array(array_get_path($wancfg, 'wireless/wpa'))) {
		array_set_path($pconfig, 'wpa_enable', array_path_enabled($wancfg, 'wireless/wpa'));
		array_set_path($pconfig, 'debug_mode', array_get_path($wancfg, 'wireless/wpa/debug_mode'));
		array_set_path($pconfig, 'macaddr_acl', array_get_path($wancfg, 'wireless/wpa/macaddr_acl'));
		array_set_path($pconfig, 'mac_acl_enable', array_path_enabled($wancfg, 'wireless/wpa', 'mac_acl_enable'));
		array_set_path($pconfig, 'wpa_mode', array_get_path($wancfg, 'wireless/wpa/wpa_mode'));
		array_set_path($pconfig, 'wpa_key_mgmt', array_get_path($wancfg, 'wireless/wpa/wpa_key_mgmt'));
		array_set_path($pconfig, 'wpa_pairwise', array_get_path($wancfg, 'wireless/wpa/wpa_pairwise'));
		array_set_path($pconfig, 'wpa_group_rekey', array_get_path($wancfg, 'wireless/wpa/wpa_group_rekey'));
		array_set_path($pconfig, 'wpa_gmk_rekey', array_get_path($wancfg, 'wireless/wpa/wpa_gmk_rekey'));
		array_set_path($pconfig, 'wpa_strict_rekey', array_path_enabled($wancfg, 'wireless/wpa', 'wpa_strict_rekey'));
		array_set_path($pconfig, 'passphrase', array_get_path($wancfg, 'wireless/wpa/passphrase'));
		array_set_path($pconfig, 'ieee8021x', array_path_enabled($wancfg, 'wireless/wpa/ieee8021x'));
		array_set_path($pconfig, 'rsn_preauth', array_path_enabled($wancfg, 'wireless/wpa', 'rsn_preauth'));
		array_set_path($pconfig, 'ext_wpa_sw', array_get_path($wancfg, 'wireless/wpa/ext_wpa_sw'));
		array_set_path($pconfig, 'wpa_eap_client_mode', array_get_path($wancfg, 'wireless/wpa/wpa_eap_client_mode'));
		array_set_path($pconfig, 'wpa_eap_inner_auth', array_get_path($wancfg, 'wireless/wpa/wpa_eap_inner_auth'));
		array_set_path($pconfig, 'wpa_eap_inner_id', array_get_path($wancfg, 'wireless/wpa/wpa_eap_inner_id'));
		array_set_path($pconfig, 'wpa_eap_inner_password', base64_decode(array_get_path($wancfg, 'wireless/wpa/wpa_eap_inner_password')));
		array_set_path($pconfig, 'wpa_eap_cert', array_get_path($wancfg, 'wireless/wpa/wpa_eap_cert'));
		array_set_path($pconfig, 'wpa_eap_ca', array_get_path($wancfg, 'wireless/wpa/wpa_eap_ca'));
	}
	array_set_path($pconfig, 'mac_acl', array_get_path($wancfg, 'wireless/mac_acl'));
}

$changes_applied = false;

if ($_POST['apply']) {
	unset($input_errors);
	if (!is_subsystem_dirty('interfaces')) {
		$input_errors[] = gettext("The settings have already been applied!");
	} else {
		$retval = 0;
		unlink_if_exists(g_get('tmp_path') . '/config.cache');
		clear_subsystem_dirty('interfaces');

		$vlan_redo = [];
		if (file_exists(g_get('tmp_path') . '/.interfaces.apply')) {
			$toapplylist = unserialize_data(file_get_contents(g_get('tmp_path') . '/.interfaces.apply'), []);
			foreach ($toapplylist as $ifapply => $ifcfgo) {
				$realif = get_real_interface($ifapply);
				$ifmtu = get_interface_mtu($realif);
				if (config_path_enabled("interfaces/{$ifapply}")) {
					interface_bring_down($ifapply, false, $ifcfgo);
					interface_configure($ifapply, true);
					if (config_get_path("interfaces/{$ifapply}/ipaddrv6") == "track6") {
						/* call interface_track6_configure with linkup true so
						   IPv6 IPs are added back. dhcp6c needs a HUP. Can't
						   just call interface_configure with linkup true as
						   that skips bridge membership addition.
						*/
						$wancfg = config_get_path("interfaces/{$ifapply}");
						interface_track6_configure($ifapply, $wancfg, true);
					}
				} else {
					interface_bring_down($ifapply, true, $ifcfgo);
				}
				restart_interface_services($ifapply, array_get_path($ifcfgo, 'ifcfg/ipaddrv6'));
				$mtu = config_get_path("interfaces/{$ifapply}/mtu");
				if (interface_has_clones($realif) &&
				    ($mtu && ($mtu != $ifmtu)) ||
				    (!$mtu && (get_interface_default_mtu() != $ifmtu))) {
					$vlan_redo[] = $realif;
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

		/* sync filter configuration */
		setup_gateways_monitor();

		clear_subsystem_dirty('interfaces');

		$retval |= filter_configure();

		enable_rrd_graphing();

		$changes_applied = true;

		if (is_subsystem_dirty('staticroutes') &&
		    (system_routing_configure() == 0)) {
			clear_subsystem_dirty('staticroutes');
		}

		send_event("service reload packages");
	}
	@unlink(g_get('tmp_path') . '/.interfaces.apply');
} elseif ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	if (ctype_xdigit($_POST['track6-prefix-id--hex'])) {
		array_set_path($pconfig, 'track6-prefix-id', intval($_POST['track6-prefix-id--hex'], 16));
	} else {
		array_set_path($pconfig, 'track6-prefix-id', 0);
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
	$reqdfieldsn = [gettext("Description")];
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		/* Reserved name? */
		if (get_pf_reserved($_POST['descr'], false)) {
			$input_errors[] = sprintf(gettext("Cannot use a reserved keyword as an interface name: %s"), $_POST['descr']);
		}

		/* description unique? */
		foreach ($ifdescrs as $ifent => $ifdescr) {
			if (($if != $ifent) &&
			    (strcasecmp($ifdescr, $_POST['descr']) == 0)) {
				$input_errors[] = gettext("An interface with the specified description already exists.");
				break;
			}
		}

		/* Is the description already used as an alias name? */
		foreach (config_get_path('aliases/alias', []) as $alias) {
			if (strcasecmp(array_get_path($alias, 'name'), $_POST['descr']) == 0) {
				$input_errors[] = sprintf(gettext("Sorry, an alias with the name %s already exists."), $_POST['descr']);
			}
		}

		/* Is the description already used as an interface group name? */
		foreach (config_get_path('ifgroups/ifgroupentry', []) as $ifgroupentry) {
			if (strcasecmp(array_get_path($ifgroupentry, 'ifname'), $_POST['descr']) == 0) {
				$input_errors[] = sprintf(gettext("Sorry, an interface group with the name %s already exists."), $_POST['descr']);
			}
		}

		if (is_numeric($_POST['descr'])) {
			$input_errors[] = gettext("The interface description cannot contain only numbers.");
		}

		if ((strlen(trim($_POST['descr'])) > 25) &&
		    ((substr($realifname, 0, 4) == 'ovpn') ||
		    (substr($realifname, 0, 5) == 'ipsec'))) {
			$input_errors[] = gettext("OpenVPN and VTI interface descriptions must be less than 26 characters long.");
		}

		if ((strlen(trim($_POST['descr'])) > 22) &&
		    ((substr($realifname, 0, 3) == 'gif') ||
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

	if (($_POST['blockbogons'] == "yes") &&
	    config_path_enabled('system','ipv6allow') &&
	    (config_get_path('system/maximumtableentries', 0) <
	    g_get('minimumtableentries_bogonsv6'))) {
		$input_errors[] = sprintf(gettext(
		    "In order to block bogon networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s."),
		    g_get('minimumtableentries_bogonsv6'));
	}

	if (config_path_enabled("dhcpd/{$if}")) {
		if (!preg_match("/^staticv4/", $_POST['type'])) {
			$input_errors[] = gettext("The DHCP Server is active " .
			    "on this interface and it can be used only with " .
			    "a static IP configuration. Please disable the " .
			    "DHCP Server service on this interface first, " .
			    "then change the interface configuration.");
		} elseif (!empty($_POST['subnet']) &&
			  ($_POST['subnet'] >= 31)) {
			$input_errors[] = gettext("The DHCP Server is active " .
			    "on this interface and it can be used only with " .
			    "IPv4 subnet < 31. Please disable the " .
			    "DHCP Server service on this interface first, " .
			    "then change the interface configuration.");
		}
	}
	if (!empty(config_get_path('dhcpdv6', [])) &&
	    (($_POST['type6'] != "staticv6") &&
	    ($_POST['type6'] != "track6"))) {
		if (config_path_enabled("dhcpdv6/{$if}")) {
			$input_errors[] = gettext("The DHCP6 Server is active on this interface and it can be used only " .
			    "with a static IPv6 configuration. Please disable the DHCPv6 Server service on this " .
			    "interface first, then change the interface configuration.");
		}
		if (config_get_path("dhcpdv6/{$if}/ramode", "disabled") != "disabled") {
			$input_errors[] = gettext("The Router Advertisements Server is active on this interface and it can " .
			    "be used only with a static IPv6 configuration. Please disable the Router Advertisements " .
			    "Server service on this interface first, then change the interface configuration.");
		}
	}

	switch (strtolower($_POST['type'])) {
		case "staticv4":
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = [gettext("IPv4 address"), gettext("Subnet bit count"), gettext("Gateway")];
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			foreach (config_get_path('virtualip/vip', []) as $vip) {
				if (is_ipaddrv4(array_get_path($vip, 'subnet')) &&
				    (array_get_path($vip, 'interface') == $if)) {
					$input_errors[] = gettext("This interface is referenced by IPv4 VIPs. Please delete these VIPs before setting the interface configuration type to 'none'.");
				}
			}
			break;
		case "ppp":
			$reqdfields = explode(" ", "port phone");
			$reqdfieldsn = [gettext("Modem Port"), gettext("Phone Number")];
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pppoe":
			if ($_POST['pppoe_dialondemand']) {
				$reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
				$reqdfieldsn = [gettext("PPPoE username"), gettext("PPPoE password"), gettext("Dial on demand"), gettext("Idle timeout value")];
			} else {
				$reqdfields = explode(" ", "pppoe_username pppoe_password");
				$reqdfieldsn = [gettext("PPPoE username"), gettext("PPPoE password")];
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "pptp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local0 pptp_subnet0 pptp_remote0 pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = [gettext("PPTP username"), gettext("PPTP password"), gettext("PPTP local IP address"), gettext("PPTP subnet"), gettext("PPTP remote IP address"), gettext("Dial on demand"), gettext("Idle timeout value")];
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local0 pptp_subnet0 pptp_remote0");
				$reqdfieldsn = [gettext("PPTP username"), gettext("PPTP password"), gettext("PPTP local IP address"), gettext("PPTP subnet"), gettext("PPTP remote IP address")];
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "l2tp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote0 pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = [gettext("L2TP username"), gettext("L2TP password"), gettext("L2TP remote IP address"), gettext("Dial on demand"), gettext("Idle timeout value")];
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote0");
				$reqdfieldsn = [gettext("L2TP username"), gettext("L2TP password"), gettext("L2TP remote IP address")];
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
	}
	switch (strtolower($_POST['type6'])) {
		case "staticv6":
			$reqdfields = explode(" ", "ipaddrv6 subnetv6 gatewayv6");
			$reqdfieldsn = [gettext("IPv6 address"), gettext("Subnet bit count"), gettext("Gateway")];
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
			break;
		case "none":
			foreach (config_get_path('virtualip/vip', []) as $vip) {
				if (is_ipaddrv6(array_get_path($vip, 'subnet')) &&
				    (array_get_path($vip, 'interface') == $if)) {
					$input_errors[] = gettext("This interface is referenced by IPv6 VIPs. Please delete these VIPs before setting the interface configuration type to 'none'.");
				}
			}
			break;
		case "dhcp6":
			if (in_array(array_get_path($wancfg, 'ipaddrv6'), [])) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			if ($_POST['dhcp6-ia-pd-send-hint'] &&
			    (strtolower($_POST['dhcp6-ia-pd-len']) == 'none')) {
				$input_errors[] = gettext('DHCPv6 Prefix Delegation size must be provided when Send IPv6 prefix hint flag is checked');
			}
			if (!empty($_POST['adv_dhcp6_id_assoc_statement_address_id']) &&
			    !is_numericint($_POST['adv_dhcp6_id_assoc_statement_address_id'])) {
				$input_errors[] = gettext('DHCPv6 advanced id-assoc na ID value must be empty or an integer.');
			}
			if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix_id']) &&
			    !is_numericint($_POST['adv_dhcp6_id_assoc_statement_prefix_id'])) {
				$input_errors[] = gettext('DHCPv6 advanced id-assoc pd ID value must be empty or an integer.');
			}
			if (!empty($_POST['adv_dhcp6_prefix_interface_statement_sla_id']) &&
			    !is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_id'])) {
				$input_errors[] = gettext('DHCPv6 advanced Prefix Interface sla-id value must be empty or an integer.');
			}
			if (!empty($_POST['adv_dhcp6_prefix_interface_statement_sla_len']) &&
			    !is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_len'])) {
				$input_errors[] = gettext('DHCPv6 advanced Prefix Interface sla-len value must be empty or an integer.');
			}
			break;
		case "6rd":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if (($if != $ifent) &&
				    (config_get_path("interfaces/{$ifent}/ipaddrv6") == $_POST['type6'])) {
					if (config_get_path("interfaces/{$ifent}/prefix-6rd") == $_POST['prefix-6rd']) {
						$input_errors[] = gettext("Only one interface can be configured within a single 6rd prefix.");
						break;
					}
				}
			}
			if (!is_subnetv6($_POST['prefix-6rd'])) {
				$input_errors[] = gettext("6RD Prefix must be a valid IPv6 prefix.");
			}
			if (!is_ipaddrv4($_POST['gateway-6rd'])) {
				$input_errors[] = gettext("6RD Border Relay must be an IPv4 address.");
			}
			if (in_array(array_get_path($wancfg, 'ipaddrv6'), [])) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			break;
		case "6to4":
			foreach ($ifdescrs as $ifent => $ifdescr) {
				if (($if != $ifent) &&
				    (config_get_path("interfaces/{$ifent}/ipaddrv6") == $_POST['type6'])) {
					$input_errors[] = sprintf(gettext("Only one interface can be configured as 6to4."), $_POST['type6']);
					break;
				}
			}
			if (in_array(array_get_path($wancfg, 'ipaddrv6'), [])) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}
			break;
		case "track6":
			/* needs to check if $track6-prefix-id is used on another interface */
			if (in_array(array_get_path($wancfg, 'ipaddrv6'), [])) {
				$input_errors[] = sprintf(gettext("The interface must be reassigned to configure as %s."), $_POST['type6']);
			}

			if (empty($_POST['track6-interface'])) {
				$input_errors[] = gettext("A valid interface to track must be selected.");
			}

			if (($_POST['track6-prefix-id--hex'] != "") &&
			    !ctype_xdigit($_POST['track6-prefix-id--hex'])) {
				$input_errors[] = gettext("A valid hexadecimal number must be entered for the IPv6 prefix ID.");
			} else {
				$track6_prefix_id = intval($_POST['track6-prefix-id--hex'], 16);
				if (($track6_prefix_id < 0) ||
				    ($track6_prefix_id > $_POST['ipv6-num-prefix-ids-' . $_POST['track6-interface']])) {
					$input_errors[] = gettext("The specified IPv6 Prefix ID is out of range.") .
						" ({$_POST['track6-interface']}) - (0) - (" . sprintf('%x', $_POST['ipv6-num-prefix-ids-' . $_POST['track6-interface']]) . ")";
				} else {
					foreach ($ifdescrs as $ifent => $ifdescr) {
						if ($if == $ifent) {
							continue;
						}
						if (config_get_path("interfaces/{$ifent}/ipaddrv6") == 'track6' &&
						    config_get_path("interfaces/{$ifent}/track6-interface") == $_POST['track6-interface'] &&
						    config_get_path("interfaces/{$ifent}/track6-prefix-id") == $track6_prefix_id) {
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
	if (($_POST['type'] == 'staticv4') &&
	    $_POST['ipaddr']) {
		$_POST['ipaddr'] = trim($_POST['ipaddr']);
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
				} elseif ($_POST['ipaddr'] == gen_subnet_max($_POST['ipaddr'], $_POST['subnet'])) {
					$input_errors[] = gettext("This IPv4 address is the broadcast address and cannot be used");
				}
			}

			foreach ($staticroutes as $route_subnet) {
				list($network, $subnet) = explode("/", $route_subnet);
				if (($_POST['subnet'] == $subnet) &&
				    ($network == gen_subnet($_POST['ipaddr'], $_POST['subnet']))) {
					$input_errors[] = gettext("This IPv4 address conflicts with a Static Route.");
					break;
				}
				unset($network, $subnet);
			}
		}
	}
	if (($_POST['type6'] == 'staticv6') &&
	    $_POST['ipaddrv6']) {
		$_POST['ipaddrv6'] = trim(addrtolower($_POST['ipaddrv6']));

		if (!is_ipaddrv6($_POST['ipaddrv6'])) {
			$input_errors[] = gettext("A valid IPv6 address must be specified.");
		} else {
			if (ip_in_subnet($_POST['ipaddrv6'], "fe80::/10")) {
				$input_errors[] = gettext("IPv6 link local addresses cannot be configured as an interface IP address.");
			}
			$where_ipaddr_configured = where_is_ipaddr_configured($_POST['ipaddrv6'], $if, true, true, $_POST['subnetv6']);
			if (count($where_ipaddr_configured)) {
				$subnet_conflict_text = sprintf(gettext("IPv6 address %s is being used by or overlaps with:"), $_POST['ipaddrv6'] . "/" . $_POST['subnetv6']);
				foreach ($where_ipaddr_configured as $subnet_conflict) {
					$subnet_conflict_text .= " " . convert_friendly_interface_to_friendly_descr($subnet_conflict['if']) . " (" . array_get_path($subnet_conflict, 'ip_or_subnet') . ")";
				}
				$input_errors[] = $subnet_conflict_text;
			}

			foreach ($staticroutes as $route_subnet) {
				list($network, $subnet) = explode("/", $route_subnet);
				if (($_POST['subnetv6'] == $subnet) &&
				    ($network == gen_subnetv6($_POST['ipaddrv6'], $_POST['subnetv6']))) {
					$input_errors[] = gettext("This IPv6 address conflicts with a Static Route.");
					break;
				}
				unset($network, $subnet);
			}
		}
	}
	if (($_POST['subnet'] &&
	    !is_numeric($_POST['subnet']))) {
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	}
	if (($_POST['subnetv6'] &&
	    !is_numeric($_POST['subnetv6']))) {
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	}
	if (($_POST['alias-address'] &&
	    !is_ipaddrv4($_POST['alias-address']))) {
		$input_errors[] = gettext("A valid alias IP address must be specified.");
	}
	if (($_POST['alias-subnet'] &&
	    !is_numeric($_POST['alias-subnet']))) {
		$input_errors[] = gettext("A valid alias subnet bit count must be specified.");
	}
	if ($_POST['dhcprejectfrom'] &&
	    !validate_ipv4_list($_POST['dhcprejectfrom'])) {
		$input_errors[] = gettext("An invalid IP address was detected in the 'Reject leases from' field.");
	}

	// Only check the IPv4 gateway already exists if it is not "none" and it is not a gateway that the user is adding
	if (($_POST['gateway'] != "none") &&
	    (!$_POST['gatewayip4'] ||
	    ($_POST['gateway'] != $_POST['gatewayname4']))) {
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
	if (($_POST['gatewayv6'] != "none") &&
	    (!$_POST['gatewayip6'] ||
	    ($_POST['gatewayv6'] != $_POST['gatewayname6']))) {
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

	if ($_POST['provider'] &&
	    strpos($_POST['provider'], "\"")) {
		$input_errors[] = gettext("The service name may not contain quote characters.");
	}
	if ($_POST['hostuniq'] &&
	    !preg_match('/^[a-zA-Z0-9]+$/i', $_POST['hostuniq'])) {
		$input_errors[] = gettext("The Host-Uniq value can only be hexadecimal or letters and numbers.");
	}
	if (($_POST['pppoe_idletimeout'] != "") &&
	   !is_numericint($_POST['pppoe_idletimeout'])) {
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	}
	if (($_POST['pppoe_resethour'] != "") &&
	    !is_numericint($_POST['pppoe_resethour']) &&
	    ($_POST['pppoe_resethour'] >= 0) &&
	    ($_POST['pppoe_resethour'] <= 23)) {
		$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	}
	if (($_POST['pppoe_resetminute'] != "") &&
	    !is_numericint($_POST['pppoe_resetminute']) &&
	    ($_POST['pppoe_resetminute'] >= 0) &&
	    ($_POST['pppoe_resetminute'] <= 59)) {
		$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	}
	if (($_POST['pppoe_resetdate'] != "") &&
	    !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) {
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	}
	if (($_POST['pptp_local0'] &&
	    !is_ipaddrv4($_POST['pptp_local0']))) {
		$input_errors[] = gettext("A valid PPTP local IP address must be specified.");
	}
	if (($_POST['pptp_subnet0'] &&
	    !is_numeric($_POST['pptp_subnet0']))) {
		$input_errors[] = gettext("A valid PPTP subnet bit count must be specified.");
	}
	if (($_POST['pptp_remote0'] &&
	    !is_ipaddrv4($_POST['pptp_remote0']) &&
	    !is_hostname($_POST['pptp_remote0']))) {
		$input_errors[] = gettext("A valid PPTP remote IP address must be specified.");
	}
	if (($_POST['pptp_idletimeout'] != "") &&
	    !is_numericint($_POST['pptp_idletimeout'])) {
		$input_errors[] = gettext("The idle timeout value must be an integer.");
	}
	if (($_POST['spoofmac'] &&
	    !is_macaddr($_POST['spoofmac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if ($_POST['mtu']) {
		if (!is_numericint($_POST['mtu'])) {
			$input_errors[] = "MTU must be an integer.";
		}
		if (substr(array_get_path($wancfg, 'if'), 0, 3) == 'gif') {
			$min_mtu = 1280;
			$max_mtu = 8192;
		} elseif (($_POST['ipaddrv6'] == '6rd') ||
			  ($_POST['ipaddrv6'] == '6to4')) {
			$min_mtu = 1300;
			$max_mtu = 9000;
		} else {
			$min_mtu = 576;
			$max_mtu = 9000;
		}

		if (($_POST['mtu'] < $min_mtu) ||
		    ($_POST['mtu'] > $max_mtu)) {
			$input_errors[] = sprintf(gettext("The MTU must be between %d and %d bytes."), $min_mtu, $max_mtu);
		}

		unset($min_mtu, $max_mtu);

		if (interface_is_vlan(array_get_path($wancfg, 'if')) != NULL) {
			$realhwif_array = get_parent_interface(array_get_path($wancfg, 'if'));
			// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
			$parent_realhwif = $realhwif_array[0];
			$parent_if = convert_real_interface_to_friendly_interface_name($parent_realhwif);
			$mtu = 0;
			if (!empty($parent_if) &&
			    !(config_get_path("interfaces/{$parent_if}/mtu"))) {
				$mtu = intval(config_get_path("interfaces/{$parent_if}/mtu"));
			}
			if ($mtu == 0) {
				$mtu = get_interface_mtu($parent_realhwif);
			}
			if ($_POST['mtu'] > $mtu) {
				$input_errors[] = gettext("The MTU of a VLAN cannot be greater than that of its parent interface.");
			}
		} else {
			foreach (config_get_path('interfaces', []) as $idx => $ifdata) {
				if (($idx == $if) ||
				    (interface_is_vlan(array_get_path($ifdata, 'if')) == NULL)) {
					continue;
				}

				$realhwif_array = get_parent_interface(array_get_path($ifdata, 'if'));
				// Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
				$parent_realhwif = $realhwif_array[0];

				if ($parent_realhwif != array_get_path($wancfg, 'if')) {
					continue;
				}

				if (array_path_enabled($ifdata, '', 'mtu') &&
				    (array_get_path($ifdata, 'mtu') > $_POST['mtu'])) {
					$input_errors[] = sprintf(gettext("Interface %s (VLAN) has MTU set to a larger value."), array_get_path($ifdata, 'descr'));
				}
			}
		}
	}
	if ($_POST['mss'] != '') {
		if (!is_numericint($_POST['mss']) ||
		    (($_POST['mss'] < 576) ||
		    ($_POST['mss'] > 65535))) {
			$input_errors[] = gettext("The MSS must be an integer between 576 and 65535 bytes.");
		}
	}
	/* Wireless interface? */
	if (is_array(array_get_path($wancfg, 'wireless'))) {
		$reqdfields = ["mode"];
		$reqdfieldsn = [gettext("Mode")];
		if ($_POST['mode'] == 'hostap') {
			$reqdfields[] = "ssid";
			$reqdfieldsn[] = gettext("SSID");
			if (isset($_POST['channel']) &&
			    ($_POST['channel'] == "0")) {
				// auto channel with hostap is broken, prevent this for now.
				$input_errors[] = gettext("A specific channel, not auto, must be selected for Access Point mode.");
			}
		}
		if (!stristr($_POST['standard'], '11n') &&
		    ($_POST['channel_width'] != "0")) {
			$input_errors[] = gettext("Channel width selection is only supported by 802.11n standards.");
		}
		if (stristr($_POST['standard'], '11n')) {
			if (!($_POST['wme_enable'])) {
				$input_errors[] = gettext("802.11n standards require enabling WME.");
			}
			$whl = array_get_path($wl_ht_list, array_get_path($_POST, 'standard') . '/' . array_get_path($_POST, 'channel'), []);
			if (($_POST['channel_width'] != "0") &&
			    ($_POST['channel'] != "0") &&
			    !empty($whl) &&
			    !in_array($_POST['channel_width'], $whl)) {
				$input_errors[] = sprintf(gettext("Unable to use %s channel width with channel %s."), strtoupper($_POST['channel_width']), $_POST['channel']);
			}
		}
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		check_wireless_mode();
		if (isset($_POST['wpa_group_rekey']) &&
		    (!is_numericint($_POST['wpa_group_rekey']) ||
		    ($_POST['wpa_group_rekey'] < 1) ||
		    ($_POST['wpa_group_rekey'] > 9999))) {
			$input_errors[] = gettext("Key Rotation must be an integer between 1 and 9999.");
		}
		if (isset($_POST['wpa_gmk_rekey']) &&
		    (!is_numericint($_POST['wpa_gmk_rekey']) ||
		    ($_POST['wpa_gmk_rekey'] < 1) ||
		    ($_POST['wpa_gmk_rekey'] > 9999))) {
			$input_errors[] = gettext("Master Key Regeneration must be an integer between 1 and 9999.");
		}
		if (isset($_POST['wpa_group_rekey']) &&
		    isset($_POST['wpa_gmk_rekey'])) {
			if ($_POST['wpa_group_rekey'] > $_POST['wpa_gmk_rekey']) {
				$input_errors[] = gettext("Master Key Regeneration must be greater than Key Rotation.");
			}
		}
		if (!empty($_POST['auth_server_addr'])) {
			if (!is_domain($_POST['auth_server_addr']) &&
			    !is_ipaddr($_POST['auth_server_addr'])) {
				$input_errors[] = gettext("802.1X Authentication Server must be an IP or hostname.");
			}
		}
		if (!empty($_POST['auth_server_addr2'])) {
			if (!is_domain($_POST['auth_server_addr2']) &&
			    !is_ipaddr($_POST['auth_server_addr2'])) {
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
		if (isset($_POST['channel']) &&
		    !is_numericint($_POST['channel'])) {
			if (!is_numericint($_POST['channel'])) {
				$input_errors[] = gettext("Invalid channel specified.");
			} else {
				if (($_POST['channel'] > 255) ||
				    ($_POST['channel'] < 0)) {
					$input_errors[] = gettext("Channel must be between 0-255.");
				}
			}
		}
		if (!empty($_POST['distance']) &&
		    !is_numericint($_POST['distance'])) {
			$input_errors[] = gettext("Distance must be an integer.");
		}
		if (isset($_POST['standard']) &&
		    (stristr($_POST['standard'], '11na') ||
		    stristr($_POST['standard'], '11a'))) {
			if (($_POST['channel'] != 0) &&
			    ($_POST['channel'] < 15)) {
				$input_errors[] = gettext("Channel selected is not valid for 802.11a or 802.11na.");
			}
		}
		if (isset($_POST['standard']) &&
		    (($_POST['standard'] == "11b") ||
		    ($_POST['standard'] == "11g"))) {
			if ($_POST['channel'] > 14) {
				$input_errors[] = gettext("Channel selected is not valid for 802.11b or 802.11g.");
			}
		}
		if (!empty($_POST['protmode']) &&
		    !in_array($_POST['protmode'], ["off", "cts", "rtscts"])) {
			$input_errors[] = gettext("Invalid option chosen for OFDM Protection Mode");
		}

		if ($_POST['passphrase']) {
			$passlen = strlen($_POST['passphrase']);
			if (($passlen < 8) ||
			    ($passlen > 63)) {
				$input_errors[] = gettext("The WPA passphrase must be between 8 and 63 characters long.");
			}
		}

		if ($_POST['wpa_enable'] == "yes") {
			if (empty($_POST['passphrase']) &&
			    stristr($_POST['wpa_key_mgmt'], "WPA-PSK")) {
				$input_errors[] = gettext("A WPA Passphrase must be specified when WPA PSK is enabled.");
			}
			if (($_POST['mode'] == 'bss') &&
			    ($_POST['wpa_key_mgmt'] == "WPA-EAP") &&
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
		$gateway_settings4 = [
			'name' => $_POST['gatewayname4'],
			'interface' => $_POST['if'],
			'gateway' => $_POST['gatewayip4'],
			'descr' => $_POST['gatewaydescr4'],
			'defaultgw' => $_POST['defaultgw4'],
			'ipprotocol' => 'inet',
		];
		$gw_input_errors = validate_gateway($gateway_settings4, '', $_POST['ipaddr'], $_POST['subnet']);
		foreach ($gw_input_errors as $input_error_text) {
			$input_errors[] = $input_error_text;
		}
	}

	if ($_POST['gatewayip6']) {
		// The user wants to add an IPv6 gateway - validate the settings
		$gateway_settings6 = [
			'name' => $_POST['gatewayname6'],
			'interface' => $_POST['if'],
			'gateway' => $_POST['gatewayip6'],
			'descr' => $_POST['gatewaydescr6'],
			'defaultgw' => $_POST['defaultgw6'],
			'ipprotocol' => 'inet6',
		];
		$gw_input_errors = validate_gateway($gateway_settings6, '', $_POST['ipaddrv6'], $_POST['subnetv6']);
		foreach ($gw_input_errors as $input_error_text) {
			$input_errors[] = $input_error_text;
		}
	}

	if (!$input_errors) {
		$poriginal = [];
		// These 3 fields can be a list of multiple data items when used for MLPPP.
		// The UI in this code only processes the first of the list, so save the data here then we can preserve any other entries.
		array_set_path($poriginal, 'pptp_localip', explode(",", array_get_path($a_ppps, "{$pppid}/localip")));
		array_set_path($poriginal, 'pptp_subnet',  explode(",", array_get_path($a_ppps, "{$pppid}/subnet")));
		array_set_path($poriginal, 'pptp_remote',  explode(",", array_get_path($a_ppps, "{$pppid}/gateway")));

		if (array_get_path($wancfg, 'ipaddr') != $_POST['type']) {
			if (in_array(array_get_path($wancfg, 'ipaddr'), ["ppp", "pppoe", "pptp", "l2tp"])) {
				array_set_path($wancfg, 'if', array_get_path($a_ppps, "{$pppid}/ports"));
				array_del_path($a_ppps, $pppid);
			} elseif (array_get_path($wancfg, 'ipaddr') == "dhcp") {
				kill_dhclient_process(array_get_path($wancfg, 'if'));
			}
			if ((array_get_path($wancfg, 'ipaddrv6') == "dhcp6") &&
			    ($_POST['type6'] != "dhcp6")) {
				interface_dhcpv6_configure($if, $wancfg, true);
			}
		}
		$ppp = [];
		if (array_get_path($wancfg, 'ipaddr') != "ppp") {
			array_del_path($wancfg, 'ipaddr');
		}
		if (array_get_path($wancfg, 'ipaddrv6') != "ppp") {
			array_del_path($wancfg, 'ipaddrv6');
		}

		$wancfg_to_delelete = [
			'subnet',
			'gateway',
			'subnetv6',
			'gatewayv6',
			'dhcphostname',
			'dhcprejectfrom',
			'dhcp6-duid',
			'dhcp6-ia-pd-len',
			'dhcp6-ia-pd-send-hint',
			'dhcp6prefixonly',
			'dhcp6usev4iface',
			'slaacusev4iface',
			'ipv6usev4iface',
			'track6-interface',
			'track6-prefix-id',
			'dhcp6withoutra',
			'dhcp6vlanenable',
			'dhcp6cvpt',
			'prefix-6rd',
			'prefix-6rd-v4plen',
			'gateway-6rd',
			'dhcpvlanenable',
			'dhcpcvpt',
			'adv_dhcp_pt_timeout',
			'adv_dhcp_pt_retry',
			'adv_dhcp_pt_select_timeout',
			'adv_dhcp_pt_reboot',
			'adv_dhcp_pt_backoff_cutoff',
			'adv_dhcp_pt_initial_interval',
			'adv_dhcp_pt_values',
			'adv_dhcp_send_options',
			'adv_dhcp_request_options',
			'adv_dhcp_required_options',
			'adv_dhcp_option_modifiers',
			'adv_dhcp_config_advanced',
			'adv_dhcp_config_file_override',
			'adv_dhcp_config_file_override_path',
			'adv_dhcp6_interface_statement_send_options',
			'adv_dhcp6_interface_statement_request_options',
			'adv_dhcp6_interface_statement_information_only_enable',
			'adv_dhcp6_interface_statement_script',
			'adv_dhcp6_id_assoc_statement_address_enable',
			'adv_dhcp6_id_assoc_statement_address',
			'adv_dhcp6_id_assoc_statement_address_id',
			'adv_dhcp6_id_assoc_statement_address_pltime',
			'adv_dhcp6_id_assoc_statement_address_vltime',
			'adv_dhcp6_id_assoc_statement_prefix_enable',
			'adv_dhcp6_id_assoc_statement_prefix',
			'adv_dhcp6_id_assoc_statement_prefix_id',
			'adv_dhcp6_id_assoc_statement_prefix_pltime',
			'adv_dhcp6_id_assoc_statement_prefix_vltime',
			'adv_dhcp6_prefix_interface_statement_sla_id',
			'adv_dhcp6_prefix_interface_statement_sla_len',
			'adv_dhcp6_prefix_selected_interface',
			'adv_dhcp6_authentication_statement_authname',
			'adv_dhcp6_authentication_statement_protocol',
			'adv_dhcp6_authentication_statement_algorithm',
			'adv_dhcp6_authentication_statement_rdm',
			'adv_dhcp6_key_info_statement_keyname',
			'adv_dhcp6_key_info_statement_realm',
			'adv_dhcp6_key_info_statement_keyid',
			'adv_dhcp6_key_info_statement_secret',
			'adv_dhcp6_key_info_statement_expire',
			'adv_dhcp6_config_advanced',
			'adv_dhcp6_config_file_override',
			'adv_dhcp6_config_file_override_path',
			'pppoe_password',
			'pptp_username',
			'pptp_password',
			'l2tp_secret',
			'provider',
			'hostuniq',
			'ondemand',
			'timeout',
			'local',
			'remote',
		];
		foreach ($wancfg_to_delelete as $todel) {
			array_del_path($wancfg, $todel);
		}

		if (empty(array_get_path($wancfg, 'pppoe/pppoe-reset-type'))) {
			array_del_path($wancfg, 'pppoe/pppoe-reset-type');
		}
		if (is_array(array_get_path($a_ppps, $pppid)) &&
		    in_array(array_get_path($wancfg, 'ipaddr'), ["ppp", "pppoe", "pptp", "l2tp"])) {
			if (array_get_path($wancfg, 'ipaddr') != 'ppp') {
				array_del_path($a_ppps, "{$pppid}/apn");
				array_del_path($a_ppps, "{$pppid}/phone");
				array_del_path($a_ppps, "{$pppid}/provider");
				array_del_path($a_ppps, "{$pppid}/ondemand");
			}
			if (in_array(array_get_path($wancfg, 'ipaddr'), ["pppoe", "pptp", "l2tp"])) {
				array_del_path($a_ppps, "{$pppid}/localip");
				array_del_path($a_ppps, "{$pppid}/subnet");
				array_del_path($a_ppps, "{$pppid}/gateway");
			}
			if (array_get_path($wancfg, 'ipaddr') != 'pppoe') {
				array_del_path($a_ppps, "{$pppid}/pppoe-reset-type");
				array_del_path($a_ppps, "{$pppid}/hostuniq");
			}
			if (array_get_path($wancfg, 'type') != $_POST['type']) {
				array_del_path($a_ppps, "{$pppid}/idletimeout");
			}
		}

		array_set_path($wancfg, 'descr', remove_bad_chars($_POST['descr']));
		array_set_path($wancfg, 'enable', (($_POST['enable'] == "yes") ? true : false));

		/* let return_gateways_array() do the magic on dynamic interfaces for us */
		switch ($_POST['type']) {
			case "staticv4":
				array_set_path($wancfg, 'ipaddr', $_POST['ipaddr']);
				array_set_path($wancfg, 'subnet', $_POST['subnet']);
				if ($_POST['gateway'] != "none") {
					array_set_path($wancfg, 'gateway', $_POST['gateway']);
				}
				break;
			case "dhcp":
				array_set_path($wancfg, 'ipaddr', 'dhcp');

				array_set_path($wancfg, 'dhcphostname', $_POST['dhcphostname']);
				array_set_path($wancfg, 'alias-address', $_POST['alias-address']);
				array_set_path($wancfg, 'alias-subnet', $_POST['alias-subnet']);
				array_set_path($wancfg, 'dhcprejectfrom', $_POST['dhcprejectfrom']);

				array_set_path($wancfg, 'adv_dhcp_pt_timeout', $_POST['adv_dhcp_pt_timeout']);
				array_set_path($wancfg, 'adv_dhcp_pt_retry', $_POST['adv_dhcp_pt_retry']);
				array_set_path($wancfg, 'adv_dhcp_pt_select_timeout', $_POST['adv_dhcp_pt_select_timeout']);
				array_set_path($wancfg, 'adv_dhcp_pt_reboot', $_POST['adv_dhcp_pt_reboot']);
				array_set_path($wancfg, 'adv_dhcp_pt_backoff_cutoff', $_POST['adv_dhcp_pt_backoff_cutoff']);
				array_set_path($wancfg, 'adv_dhcp_pt_initial_interval', $_POST['adv_dhcp_pt_initial_interval']);
				array_set_path($wancfg, 'adv_dhcp_pt_values', $_POST['adv_dhcp_pt_values']);

				array_set_path($wancfg, 'adv_dhcp_send_options', $_POST['adv_dhcp_send_options']);
				array_set_path($wancfg, 'adv_dhcp_request_options', $_POST['adv_dhcp_request_options']);
				array_set_path($wancfg, 'adv_dhcp_required_options', $_POST['adv_dhcp_required_options']);
				array_set_path($wancfg, 'adv_dhcp_option_modifiers', $_POST['adv_dhcp_option_modifiers']);

				array_set_path($wancfg, 'adv_dhcp_config_advanced', $_POST['adv_dhcp_config_advanced']);
				array_set_path($wancfg, 'adv_dhcp_config_file_override', $_POST['adv_dhcp_config_file_override']);
				array_set_path($wancfg, 'adv_dhcp_config_file_override_path', $_POST['adv_dhcp_config_file_override_path']);

				array_set_path($wancfg, 'dhcp_plus', (($_POST['dhcp_plus'] == "yes") ? true : false));

				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				if ($_POST['dhcpvlanenable'] == "yes") {
					array_set_path($wancfg, 'dhcpvlanenable', true);
				}
				if (!empty($_POST['dhcpcvpt'])) {
					array_set_path($wancfg, 'dhcpcvpt', $_POST['dhcpcvpt']);
				} else {
					array_del_path($wancfg, 'dhcpcvpt');
				}
				break;
			case "ppp":
				array_set_path($a_ppps, "{$pppid}/ptpid", $_POST['ptpid']);
				array_set_path($a_ppps, "{$pppid}/type", $_POST['type']);
				array_set_path($a_ppps, "{$pppid}/if", $_POST['type'] . $_POST['ptpid']);
				array_set_path($a_ppps, "{$pppid}/ports", $_POST['port']);
				array_set_path($a_ppps, "{$pppid}/username", $_POST['ppp_username']);
				if ($_POST['ppp_password'] != DMYPWD) {
					array_set_path($a_ppps, "{$pppid}/password", base64_encode($_POST['ppp_password']));
				}
				array_set_path($a_ppps, "{$pppid}/phone", $_POST['phone']);
				array_set_path($a_ppps, "{$pppid}/apn", $_POST['apn']);

				array_set_path($wancfg, 'if', $_POST['type'] . $_POST['ptpid']);
				array_set_path($wancfg, 'ipaddr', $_POST['type']);
				break;
			case "pppoe":
				array_set_path($a_ppps, "{$pppid}/ptpid", $_POST['ptpid']);
				array_set_path($a_ppps, "{$pppid}/type", $_POST['type']);
				array_set_path($a_ppps, "{$pppid}/if", $_POST['type'] . $_POST['ptpid']);
				if (isset($_POST['ppp_port'])) {
					array_set_path($a_ppps, "{$pppid}/ports", $_POST['ppp_port']);
				} else {
					array_set_path($a_ppps, "{$pppid}/ports", array_get_path($wancfg, 'if'));
				}
				array_set_path($a_ppps, "{$pppid}/username", $_POST['pppoe_username']);
				if ($_POST['pppoe_password'] != DMYPWD) {
					array_set_path($a_ppps, "{$pppid}/password", base64_encode($_POST['pppoe_password']));
				}
				if (!empty($_POST['provider'])) {
					array_set_path($a_ppps, "{$pppid}/provider", $_POST['provider']);
				} else {
					array_set_path($a_ppps, "{$pppid}/provider", true);
				}
				if (!empty($_POST['hostuniq'])) {
					array_set_path($a_ppps, "{$pppid}/hostuniq", strtolower($_POST['hostuniq']));
				} else {
					array_set_path($a_ppps, "{$pppid}/hostuniq", true);
				}
				array_set_path($a_ppps, "{$pppid}/ondemand", ($_POST['pppoe_dialondemand'] ? true : false));

				if (!empty($_POST['pppoe_idletimeout'])) {
					array_set_path($a_ppps, "{$pppid}/idletimeout", $_POST['pppoe_idletimeout']);
				} else {
					array_del_path($a_ppps, "{$pppid}/idletimeout");
				}

				if (!empty($_POST['pppoe-reset-type'])) {
					array_set_path($a_ppps, "{$pppid}/pppoe-reset-type", $_POST['pppoe-reset-type']);
				} else {
					array_del_path($a_ppps, "{$pppid}/pppoe-reset-type");
				}

				array_set_path($wancfg, 'if', $_POST['type'] . $_POST['ptpid']);
				array_set_path($wancfg, 'ipaddr', $_POST['type']);
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}

				break;
			case "pptp":
			case "l2tp":
				array_set_path($a_ppps, "{$pppid}/ptpid", $_POST['ptpid']);
				array_set_path($a_ppps, "{$pppid}/type", $_POST['type']);
				array_set_path($a_ppps, "{$pppid}/if", $_POST['type'] . $_POST['ptpid']);
				if (isset($_POST['ppp_port'])) {
					array_set_path($a_ppps, "{$pppid}/ports", $_POST['ppp_port']);
				} else {
					array_set_path($a_ppps, "{$pppid}/ports", array_get_path($wancfg, 'if'));
				}
				array_set_path($a_ppps, "{$pppid}/username", $_POST['pptp_username']);
				if ($_POST['pptp_password'] != DMYPWD) {
					array_set_path($a_ppps, "{$pppid}/password", base64_encode($_POST['pptp_password']));
				}
				if (($_POST['type'] == 'l2tp') &&
				    !empty($_POST['l2tp_secret'])) {
					array_set_path($a_ppps, "{$pppid}/secret", base64_encode($_POST['l2tp_secret']));
				} else {
					array_del_path($a_ppps, "{$pppid}/secret");
				}
				// Replace the first (0) entry with the posted data. Preserve any other entries that might be there.
				array_set_path($poriginal, 'pptp_localip/0', $_POST['pptp_local0']);
				array_set_path($a_ppps, "{$pppid}/localip", implode(',', array_get_path($poriginal, 'pptp_localip')));
				array_set_path($poriginal, 'pptp_subnet/0', $_POST['pptp_subnet0']);
				array_set_path($a_ppps, "{$pppid}/subnet", implode(',', array_get_path($poriginal, 'pptp_subnet')));
				array_set_path($poriginal, 'pptp_remote/0', $_POST['pptp_remote0']);
				array_set_path($a_ppps, "{$pppid}/gateway", implode(',', array_get_path($poriginal, 'pptp_remote')));
				array_set_path($a_ppps, "{$pppid}/ondemand", ($_POST['pptp_dialondemand'] ? true : false));
				if (!empty($_POST['pptp_idletimeout'])) {
					array_set_path($a_ppps, "{$pppid}/idletimeout", $_POST['pptp_idletimeout']);
				} else {
					array_del_path($a_ppps, "{$pppid}/idletimeout");
				}
				array_set_path($wancfg, 'if', $_POST['type'] . $_POST['ptpid']);
				array_set_path($wancfg, 'ipaddr', $_POST['type']);
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "none":
				break;
		}
		switch ($_POST['type6']) {
			case "staticv6":
				array_set_path($wancfg, 'ipaddrv6', $_POST['ipaddrv6']);
				array_set_path($wancfg, 'subnetv6', $_POST['subnetv6']);
				if ($_POST['ipv6usev4iface'] == "yes") {
					array_set_path($wancfg, 'ipv6usev4iface', true);
				}
				if ($_POST['gatewayv6'] != "none") {
					array_set_path($wancfg, 'gatewayv6', $_POST['gatewayv6']);
				}
				break;
			case "slaac":
				array_set_path($wancfg, 'ipaddrv6', 'slaac');
				if ($_POST['slaacusev4iface'] == "yes") {
					array_set_path($wancfg, 'slaacusev4iface', true);
				}
				break;
			case "dhcp6":
				array_set_path($wancfg, 'ipaddrv6', 'dhcp6');
				array_set_path($wancfg, 'dhcp6-duid', $_POST['dhcp6-duid']);
				array_set_path($wancfg, 'dhcp6-ia-pd-len', $_POST['dhcp6-ia-pd-len']);
				if ($_POST['dhcp6-ia-pd-send-hint'] == "yes") {
					array_set_path($wancfg, 'dhcp6-ia-pd-send-hint', true);
				}
				if ($_POST['dhcp6prefixonly'] == "yes") {
					array_set_path($wancfg, 'dhcp6prefixonly', true);
				}
				if ($_POST['dhcp6usev4iface'] == "yes") {
					array_set_path($wancfg, 'dhcp6usev4iface', true);
				}
				if ($_POST['dhcp6withoutra'] == "yes") {
					array_set_path($wancfg, 'dhcp6withoutra', true);
				}
				if ($_POST['dhcp6vlanenable'] == "yes") {
					array_set_path($wancfg, 'dhcp6vlanenable', true);
				}
				if (!empty($_POST['dhcp6cvpt'])) {
					array_set_path($wancfg, 'dhcp6cvpt', $_POST['dhcp6cvpt']);
				} else {
					array_del_path($wancfg, 'dhcp6cvpt');
				}

				if (!empty($_POST['adv_dhcp6_interface_statement_send_options'])) {
					array_set_path($wancfg, 'adv_dhcp6_interface_statement_send_options', $_POST['adv_dhcp6_interface_statement_send_options']);
				}
				if (!empty($_POST['adv_dhcp6_interface_statement_request_options'])) {
					array_set_path($wancfg, 'adv_dhcp6_interface_statement_request_options', $_POST['adv_dhcp6_interface_statement_request_options']);
				}
				if (isset($_POST['adv_dhcp6_interface_statement_information_only_enable'])) {
					array_set_path($wancfg, 'adv_dhcp6_interface_statement_information_only_enable', $_POST['adv_dhcp6_interface_statement_information_only_enable']);
				}
				if (!empty($_POST['adv_dhcp6_interface_statement_script'])) {
					array_set_path($wancfg, 'adv_dhcp6_interface_statement_script', $_POST['adv_dhcp6_interface_statement_script']);
				}

				if (isset($_POST['adv_dhcp6_id_assoc_statement_address_enable'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_address_enable', $_POST['adv_dhcp6_id_assoc_statement_address_enable']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_address', $_POST['adv_dhcp6_id_assoc_statement_address']);
				}
				if (is_numericint($_POST['adv_dhcp6_id_assoc_statement_address_id'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_address_id', $_POST['adv_dhcp6_id_assoc_statement_address_id']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address_pltime'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_address_pltime', $_POST['adv_dhcp6_id_assoc_statement_address_pltime']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_address_vltime'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_address_vltime', $_POST['adv_dhcp6_id_assoc_statement_address_vltime']);
				}

				if (isset($_POST['adv_dhcp6_id_assoc_statement_prefix_enable'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_prefix_enable', $_POST['adv_dhcp6_id_assoc_statement_prefix_enable']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_prefix', $_POST['adv_dhcp6_id_assoc_statement_prefix']);
				}
				if (is_numericint($_POST['adv_dhcp6_id_assoc_statement_prefix_id'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_prefix_id', $_POST['adv_dhcp6_id_assoc_statement_prefix_id']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix_pltime'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_prefix_pltime', $_POST['adv_dhcp6_id_assoc_statement_prefix_pltime']);
				}
				if (!empty($_POST['adv_dhcp6_id_assoc_statement_prefix_vltime'])) {
					array_set_path($wancfg, 'adv_dhcp6_id_assoc_statement_prefix_vltime', $_POST['adv_dhcp6_id_assoc_statement_prefix_vltime']);
				}

				if (is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_id'])) {
					array_set_path($wancfg, 'adv_dhcp6_prefix_interface_statement_sla_id', $_POST['adv_dhcp6_prefix_interface_statement_sla_id']);
				}
				if (is_numericint($_POST['adv_dhcp6_prefix_interface_statement_sla_len'])) {
					array_set_path($wancfg, 'adv_dhcp6_prefix_interface_statement_sla_len', $_POST['adv_dhcp6_prefix_interface_statement_sla_len']);
				}
				if (!empty($_POST['adv_dhcp6_prefix_selected_interface'])) {
					array_set_path($wancfg, 'adv_dhcp6_prefix_selected_interface', $_POST['adv_dhcp6_prefix_selected_interface']);
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_authname'])) {
					array_set_path($wancfg, 'adv_dhcp6_authentication_statement_authname', $_POST['adv_dhcp6_authentication_statement_authname']);
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_protocol'])) {
					array_set_path($wancfg, 'adv_dhcp6_authentication_statement_protocol', $_POST['adv_dhcp6_authentication_statement_protocol']);
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_algorithm'])) {
					array_set_path($wancfg, 'adv_dhcp6_authentication_statement_algorithm', $_POST['adv_dhcp6_authentication_statement_algorithm']);
				}
				if (!empty($_POST['adv_dhcp6_authentication_statement_rdm'])) {
					array_set_path($wancfg, 'adv_dhcp6_authentication_statement_rdm', $_POST['adv_dhcp6_authentication_statement_rdm']);
				}

				if (!empty($_POST['adv_dhcp6_key_info_statement_keyname'])) {
					array_set_path($wancfg, 'adv_dhcp6_key_info_statement_keyname', $_POST['adv_dhcp6_key_info_statement_keyname']);
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_realm'])) {
					array_set_path($wancfg, 'adv_dhcp6_key_info_statement_realm', $_POST['adv_dhcp6_key_info_statement_realm']);
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_keyid'])) {
					array_set_path($wancfg, 'adv_dhcp6_key_info_statement_keyid', $_POST['adv_dhcp6_key_info_statement_keyid']);
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_secret'])) {
					array_set_path($wancfg, 'adv_dhcp6_key_info_statement_secret', $_POST['adv_dhcp6_key_info_statement_secret']);
				}
				if (!empty($_POST['adv_dhcp6_key_info_statement_expire'])) {
					array_set_path($wancfg, 'adv_dhcp6_key_info_statement_expire', $_POST['adv_dhcp6_key_info_statement_expire']);
				}

				if (!empty($_POST['adv_dhcp6_config_advanced'])) {
					array_set_path($wancfg, 'adv_dhcp6_config_advanced', $_POST['adv_dhcp6_config_advanced']);
				}
				if (!empty($_POST['adv_dhcp6_config_file_override'])) {
					array_set_path($wancfg, 'adv_dhcp6_config_file_override', $_POST['adv_dhcp6_config_file_override']);
				}
				if (!empty($_POST['adv_dhcp6_config_file_override_path'])) {
					array_set_path($wancfg, 'adv_dhcp6_config_file_override_path', $_POST['adv_dhcp6_config_file_override_path']);
				}

				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "6rd":
				array_set_path($wancfg, 'ipaddrv6', '6rd');
				array_set_path($wancfg, 'prefix-6rd', $_POST['prefix-6rd']);
				array_set_path($wancfg, 'prefix-6rd-v4plen', $_POST['prefix-6rd-v4plen']);
				array_set_path($wancfg, 'gateway-6rd', $_POST['gateway-6rd']);
				if ($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "6to4":
				array_set_path($wancfg, 'ipaddrv6', '6to4');
				break;
			case "track6":
				array_set_path($wancfg, 'ipaddrv6', 'track6');
				array_set_path($wancfg, 'track6-interface', $_POST['track6-interface']);
				if ($_POST['track6-prefix-id--hex'] === "") {
					array_set_path($wancfg, 'track6-prefix-id', 0);
				} elseif (ctype_xdigit($_POST['track6-prefix-id--hex'])) {
					array_set_path($wancfg, 'track6-prefix-id', intval($_POST['track6-prefix-id--hex'], 16));
				} else {
					array_set_path($wancfg, 'track6-prefix-id', 0);
				}
				break;
			case "none":
				break;
		}
		handle_pppoe_reset($_POST);

		if ($_POST['blockpriv'] == "yes") {
			array_set_path($wancfg, 'blockpriv', true);
		} else {
			array_del_path($wancfg, 'blockpriv');
		}
		if ($_POST['blockbogons'] == "yes") {
			array_set_path($wancfg, 'blockbogons', true);
		} else {
			array_del_path($wancfg, 'blockbogons');
		}
		array_set_path($wancfg, 'spoofmac', $_POST['spoofmac']);
		/* Only update MTU in the config if the if is not a member of a
		 * bridge. The display will show the bridge MTU in a disabled input
		 * field, and we will maintain the user configured MTU for initial
		 * configuration and in the event that the interface is removed from the
		 * bridge it will return to its original MTU. */
		if (!$bridged) {
			if (empty($_POST['mtu'])) {
				array_del_path($wancfg, 'mtu');
			} else {
				array_set_path($wancfg, 'mtu', $_POST['mtu']);
			}
		}
		if (empty($_POST['mss'])) {
			array_del_path($wancfg, 'mss');
		} else {
			array_set_path($wancfg, 'mss', $_POST['mss']);
		}
		if (empty($_POST['mediaopt'])) {
			array_del_path($wancfg, 'media');
			array_del_path($wancfg, 'mediaopt');
		} else {
			$mediaopts = explode(' ', $_POST['mediaopt']);
			if ($mediaopts[0] != '') {
				array_set_path($wancfg, 'media', $mediaopts[0]);
			}
			if ($mediaopts[1] != '') {
				array_set_path($wancfg, 'mediaopt', $mediaopts[1]);
			} else {
				array_del_path($wancfg, 'mediaopt');
			}
		}
		if (!empty(array_get_path($wancfg, 'wireless'))) {
			handle_wireless_post();
		}

		config_set_path('ppps/ppp', $a_ppps);
		config_set_path('gateways/gateway_item', $a_gateways);
		config_set_path("interfaces/{$if}", $wancfg);
		write_config("Interfaces settings changed");

		if ($_POST['gatewayip4']) {
			save_gateway($gateway_settings4);
		}

		if ($_POST['gatewayip6']) {
			save_gateway($gateway_settings6);
		}

		if (file_exists(g_get('tmp_path') . '/.interfaces.apply')) {
			$toapplylist = unserialize_data(file_get_contents(g_get('tmp_path') . '/.interfaces.apply'), []);
		} else {
			$toapplylist = [];
		}
		array_set_path($toapplylist, "{$if}/ifcfg", $old_wancfg);
		array_set_path($toapplylist, "{$if}/ppps", $old_ppps);
		file_put_contents(g_get('tmp_path') . '/.interfaces.apply', serialize($toapplylist));

		mark_subsystem_dirty('interfaces');

		/* regenerate cron settings/crontab file */
		configure_cron();

		header("Location: interfaces.php?if={$if}");
		exit;
	}

	/* keep port value, see https://redmine.pfsense.org/issues/12498 */
	if (!empty($_POST['ppp_port'])) {
		array_set_path($pconfig, 'port', $_POST['ppp_port']);
	}

} // end if ($_POST['save'])

function handle_wireless_post() {
	global $_POST, $wancfg, $if, $wl_countries_attr, $wlanbaseif;

	array_set_path($wancfg, 'wireless/standard', $_POST['standard']);
	array_set_path($wancfg, 'wireless/mode', $_POST['mode']);
	array_set_path($wancfg, 'wireless/protmode', $_POST['protmode']);
	array_set_path($wancfg, 'wireless/ssid', $_POST['ssid']);
	array_set_path($wancfg, 'wireless/channel', $_POST['channel']);
	array_set_path($wancfg, 'wireless/channel_width', $_POST['channel_width']);
	array_set_path($wancfg, 'wireless/authmode', $_POST['authmode']);
	array_set_path($wancfg, 'wireless/txpower', $_POST['txpower']);
	array_set_path($wancfg, 'wireless/distance', $_POST['distance']);
	array_set_path($wancfg, 'wireless/regdomain', $_POST['regdomain']);
	array_set_path($wancfg, 'wireless/regcountry', $_POST['regcountry']);
	array_set_path($wancfg, 'wireless/reglocation', $_POST['reglocation']);

	if (!empty(array_get_path($wancfg, 'wireless/regdomain')) &&
	    !empty(array_get_path($wancfg, 'wireless/regcountry'))) {
		foreach ($wl_countries_attr as $wl_country) {
			if (array_get_path($wancfg, 'wireless/regcountry') == array_get_path($wl_country, 'ID')) {
				array_set_path($wancfg, 'wireless/regdomain', array_get_path($wl_country, 'rd/0/REF'));
				break;
			}
		}
	}

	array_set_path($wancfg, 'wireless/wpa/macaddr_acl', $_POST['macaddr_acl']);
	array_set_path($wancfg, 'wireless/wpa/wpa_mode', $_POST['wpa_mode']);
	array_set_path($wancfg, 'wireless/wpa/wpa_key_mgmt', $_POST['wpa_key_mgmt']);
	array_set_path($wancfg, 'wireless/wpa/wpa_pairwise', $_POST['wpa_pairwise']);
	array_set_path($wancfg, 'wireless/wpa/wpa_group_rekey', $_POST['wpa_group_rekey']);
	array_set_path($wancfg, 'wireless/wpa/wpa_gmk_rekey', $_POST['wpa_gmk_rekey']);
	array_set_path($wancfg, 'wireless/wpa/passphrase', $_POST['passphrase']);
	array_set_path($wancfg, 'wireless/wpa/ext_wpa_sw', $_POST['ext_wpa_sw']);
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_client_mode', $_POST['wpa_eap_client_mode']);
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_inner_auth', $_POST['wpa_eap_inner_auth']);
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_inner_id', $_POST['wpa_eap_inner_id']);
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_inner_password', base64_encode($_POST['wpa_eap_inner_password']));
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_cert', $_POST['wpa_eap_cert']);
	array_set_path($wancfg, 'wireless/wpa/wpa_eap_ca', $_POST['wpa_eap_ca']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_addr', $_POST['auth_server_addr']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_port', $_POST['auth_server_port']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_shared_secret', $_POST['auth_server_shared_secret']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_addr2', $_POST['auth_server_addr2']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_port2', $_POST['auth_server_port2']);
	array_set_path($wancfg, 'wireless/wpa/auth_server_shared_secret2', $_POST['auth_server_shared_secret2']);

	if ($_POST['persistcommonwireless'] == "yes") {
		config_set_path("wireless/interfaces/{$wlanbaseif}", []);
	} elseif (config_path_enabled('wireless/interfaces', $wlanbaseif)) {
		config_del_path("wireless/interfaces/{$wlanbaseif}");
	}
	if (isset($_POST['diversity']) &&
	    is_numeric($_POST['diversity'])) {
		array_set_path($wancfg, 'wireless/diversity', $_POST['diversity']);
	} elseif (array_path_enabled($wancfg, 'wireless', 'diversity')) {
		array_del_path($wancfg, 'wireless/diversity');
	}
	if (isset($_POST['txantenna']) &&
	    is_numeric($_POST['txantenna'])) {
		array_set_path($wancfg, 'wireless/txantenna', $_POST['txantenna']);
	} elseif (array_path_enabled($wancfg, 'wireless', 'txantenna')) {
		array_del_path($wancfg, 'wireless/txantenna');
	}
	if (isset($_POST['rxantenna']) &&
	    is_numeric($_POST['rxantenna'])) {
		array_set_path($wancfg, 'wireless/rxantenna', $_POST['rxantenna']);
	} elseif (array_path_enabled($wancfg, 'wireless', 'rxantenna')) {
		array_del_path($wancfg, 'wireless/rxantenna');
	}
	if ($_POST['hidessid_enable'] == "yes") {
		array_set_path($wancfg, 'wireless/hidessid/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/hidessid')) {
		array_del_path($wancfg, 'wireless/hidessid/enable');
	}
	if ($_POST['mac_acl_enable'] == "yes") {
		array_set_path($wancfg, 'wireless/wpa/mac_acl_enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wpa', 'mac_acl_enable')) {
		array_del_path($wancfg, 'wireless/wpa/mac_acl_enable');
	}
	if ($_POST['rsn_preauth'] == "yes") {
		array_set_path($wancfg, 'wireless/wpa/rsn_preauth', true);
	} else {
		array_del_path($wancfg, 'wireless/wpa/rsn_preauth');
	}
	if ($_POST['ieee8021x'] == "yes") {
		array_set_path($wancfg, 'wireless/wpa/ieee8021x/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wpa/ieee8021x')) {
		array_del_path($wancfg, 'wireless/wpa/ieee8021x/enable');
	}
	if ($_POST['wpa_strict_rekey'] == "yes") {
		array_set_path($wancfg, 'wireless/wpa/wpa_strict_rekey', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wpa', 'wpa_strict_rekey')) {
		array_del_path($wancfg, 'wireless/wpa/wpa_strict_rekey');
	}
	if ($_POST['debug_mode'] == "yes") {
		array_set_path($wancfg, 'wireless/wpa/debug_mode', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wpa', 'debug_mode')) {
		array_del_path($wancfg, 'wireless/wpa/debug_mode');
	}
	if ($_POST['wpa_enable'] == "yes") {
		$_POST['wpa_enable'] = true;
		array_set_path($wancfg, 'wireless/wpa/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wpa')) {
		array_del_path($wancfg, 'wireless/wpa/enable');
	}

	if ($_POST['wme_enable'] == "yes") {
		$_POST['wme_enable'] = true;
		array_set_path($wancfg, 'wireless/wme/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/wme')) {
		array_del_path($wancfg, 'wireless/wme/enable');
	}
	if ($_POST['puremode'] == "11g") {
		array_set_path($wancfg, 'wireless/pureg/enable', true);
	} elseif ($_POST['puremode'] == "11n") {
		array_set_path($wancfg, 'wireless/puren/enable', true);
	} else {
		array_del_path($wancfg, 'wireless/pureg');
		array_del_path($wancfg, 'wireless/puren');
	}
	if ($_POST['apbridge_enable'] == "yes") {
		$_POST['apbridge_enable'] = true;
		array_set_path($wancfg, 'wireless/apbridge/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/apbridge')) {
		array_del_path($wancfg, 'wireless/apbridge/enable');
	}
	if (($_POST['standard'] == "11g Turbo") ||
	    ($_POST['standard'] == "11a Turbo")) {
		array_set_path($wancfg, 'wireless/turbo/enable', true);
	} elseif (array_path_enabled($wancfg, 'wireless/turbo')) {
		array_del_path($wancfg, 'wireless/turbo/enable');
	}

	interface_sync_wireless_clones($wancfg, true);
}

function check_wireless_mode() {
	global $_POST, $wlan_modes, $wancfg, $if, $wlanif, $wlanbaseif, $old_wireless_mode, $input_errors;

	if (array_get_path($wancfg, 'wireless/mode') == $_POST['mode']) {
		return;
	}

	if (does_interface_exist(interface_get_wireless_clone($wlanbaseif))) {
		$clone_count = 1;
	} else {
		$clone_count = 0;
	}

	foreach (config_get_path('wireless/clone', []) as $clone) {
		if (array_get_path($clone, 'if') == $wlanbaseif) {
				$clone_count++;
		}
	}

	if ($clone_count > 1) {
		$old_wireless_mode = array_get_path($wancfg, 'wireless/mode');
		array_set_path($wancfg, 'wireless/mode', $_POST['mode']);
		if (!interface_wireless_clone("{$wlanif}_", $wancfg)) {
			$input_errors[] = sprintf(gettext("Unable to change mode to %s. The maximum number of wireless clones supported in this mode may have been reached."),
					array_get_path($wlan_modes, array_get_path($wancfg, 'wireless/mode')));
		} else {
			pfSense_interface_destroy("{$wlanif}_");
		}
		array_set_path($wancfg, 'wireless/mode', $old_wireless_mode);
	}
}

// Find all possible media options for the interface
$mediaopts_list = [];
$intrealname = config_get_path("interfaces/{$if}/if");
exec("/sbin/ifconfig -m " . escapeshellarg($intrealname) . " | /usr/bin/grep \"media \"", $mediaopts);
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

$pgtitle = [gettext("Interfaces"), array_get_path($wancfg, 'descr') . " ({$realifname})"];
$shortcut_section = "interfaces";

$types4 = ["ppp" => gettext("PPP"), "pppoe" => gettext("PPPoE"), "pptp" => gettext("PPTP"), "l2tp" => gettext("L2TP")];

if (!in_array(array_get_path($pconfig, 'type'), ["ppp", "pppoe", "pptp", "l2tp"]) ||
    !array_intersect_key(explode(",", array_get_path($a_ppps, "{$pppid}/ports", "")), get_configured_interface_list_by_realif())) {
	$types4 = array_merge(["none" => gettext("None"), "staticv4" => gettext("Static IPv4"), "dhcp" => gettext("DHCP")], $types4);
}

$types6 = ["none" => gettext("None"), "staticv6" => gettext("Static IPv6"), "dhcp6" => gettext("DHCP6"), "slaac" => gettext("SLAAC"), "6rd" => gettext("6rd Tunnel"), "6to4" => gettext("6to4 Tunnel"), "track6" => gettext("Track Interface")];

// Get the MAC address
$defgatewayname4 = array_get_path($wancfg, 'descr') . "GW";
$defgatewayname6 = array_get_path($wancfg, 'descr') . "GWv6";

function build_mediaopts_list() {
	global $mediaopts_list;

	$list = [
		"" => gettext("Default (no preference, typically autoselect)"),
		" " => gettext("------- Media Supported by this interface -------")
		];

	foreach ($mediaopts_list as $mediaopt) {
		/* Output may include a '/' so we can't use array_set_path() */
		$list[$mediaopt] = $mediaopt;
	}

	return($list);
}

function build_gateway_list() {
	global $a_gateways, $if;

	$list = ["none" => gettext("None")];
	foreach ($a_gateways as $gateway) {
		if ((array_get_path($gateway, 'interface') == $if) &&
		    is_ipaddrv4(array_get_path($gateway, 'gateway'))) {
			array_set_path($list,
				array_get_path($gateway, 'name'),
				array_get_path($gateway, 'name') . " - " . array_get_path($gateway, 'gateway'));
		}
	}

	return($list);
}

function build_gatewayv6_list() {
	global $a_gateways, $if;

	$list = ["none" => gettext("None")];
	foreach ($a_gateways as $gateway) {
		if ((array_get_path($gateway, 'interface') == $if) &&
		    is_ipaddrv6(array_get_path($gateway, 'gateway'))) {
			array_set_path($list,
				array_get_path($gateway, 'name'),
				array_get_path($gateway, 'name') . " - " . array_get_path($gateway, 'gateway'));
		}
	}

	return($list);
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if (is_subsystem_dirty('interfaces')) {
	print_apply_box(sprintf(gettext("The %s configuration has been changed."), array_get_path($wancfg, 'descr')) . "<br />" .
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
	array_get_path($pconfig, 'enable'),
	'yes'
));

$section->addInput(new Form_Input(
	'descr',
	'*Description',
	'text',
	array_get_path($pconfig, 'descr'),
))->setHelp('Enter a description (name) for the interface here.');

if ($show_address_controls) {
	$section->addInput(new Form_Select(
		'type',
		'IPv4 Configuration Type',
		array_get_path($pconfig, 'type'),
		$types4
	));
	$section->addInput(new Form_Select(
		'type6',
		'IPv6 Configuration Type',
		array_get_path($pconfig, 'type6'),
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
		array_get_path($pconfig, 'spoofmac'),
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

$mtuInput = $section->addInput(new Form_Input(
	'mtu',
	'MTU',
	'number',
	array_get_path($pconfig, 'mtu'),
))->setHelp('If this field is blank, the adapter\'s default MTU will be used. ' .
			'This is typically 1500 bytes but can vary in some circumstances.');
/* Do not allow MTU changes for interfaces in a bridge */
if ($bridged) {
	$mtuInput->setDisabled();
	$mtuInput->setHelp('This interface is a bridge member, its MTU is ' .
					   'controlled by its parent bridge interface');
	$mtuInput->setPlaceholder(get_interface_mtu($bridged));
	$mtuInput->setValue(null);
}

$section->addInput(new Form_Input(
	'mss',
	'MSS',
	'number',
	array_get_path($pconfig, 'mss'),
))->setHelp('If a value is entered in this field, then MSS clamping for TCP connections to the value entered above ' .
	    'minus 40 for IPv4 (TCP/IPv4 header size) and minus 60 for IPv6 (TCP/IPv6 header size) will be in effect.');

if (count($mediaopts_list) > 0) {
	$section->addInput(new Form_Select(
		'mediaopt',
		'Speed and Duplex',
		rtrim(config_get_path("interfaces/{$if}/media", "") . ' ' . config_get_path("interfaces/{$if}/mediaopt")),
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
	array_get_path($pconfig, 'ipaddr'),
	'V4'
))->addMask('subnet', array_get_path($pconfig, 'subnet'), 32);

$group = new Form_Group('IPv4 Upstream gateway');

$group->add(new Form_Select(
	'gateway',
	'IPv4 Upstream Gateway',
	array_get_path($pconfig, 'gateway'),
	build_gateway_list()
));

$group->add(new Form_Button(
	'addgw4',
	'Add a new gateway',
	null,
	'fa-solid fa-plus'
))->setAttribute('type','button')->addClass('btn-success')->setAttribute('data-target', '#newgateway4')->setAttribute('data-toggle', 'modal');

$group->setHelp('If this interface is an Internet connection, select an existing Gateway from the list or add a new one using the "Add" button.%1$s' .
				'On local area network interfaces the upstream gateway should be "none".%1$s' .
				'Selecting an upstream gateway causes the firewall to treat this interface as a %2$sWAN type interface%4$s.%1$s' .
				'Gateways can be managed by %3$sclicking here%4$s.', '<br />', '<a target="_blank" href="https://docs.netgate.com/pfsense/en/latest/interfaces/wanvslan.html">', '<a target="_blank" href="system_gateways.php">', '</a>');

$section->add($group);

$form->add($section);

$section = new Form_Section('SLAAC IPv6 Configuration');
$section->addClass('slaac');

$section->addInput(new Form_Checkbox(
	'slaacusev4iface',
	'Use IPv4 connectivity as parent interface',
	'IPv6 will use the IPv4 connectivity link (PPPoE)',
	array_get_path($pconfig, 'slaacusev4iface'),
));

$form->add($section);

$section = new Form_Section('Static IPv6 Configuration');
$section->addClass('staticv6');

$section->addInput(new Form_IpAddress(
	'ipaddrv6',
	'*IPv6 address',
	array_get_path($pconfig, 'ipaddrv6'),
	'V6'
))->addMask('subnetv6', array_get_path($pconfig, 'subnetv6'), 128);

$section->addInput(new Form_Checkbox(
	'ipv6usev4iface',
	'Use IPv4 connectivity as parent interface',
	'IPv6 will use the IPv4 connectivity link (PPPoE)',
	array_get_path($pconfig, 'ipv6usev4iface'),
));

$group = new Form_Group('IPv6 Upstream gateway');

$group->add(new Form_Select(
	'gatewayv6',
	'IPv6 Upstream Gateway',
	array_get_path($pconfig, 'gatewayv6'),
	build_gatewayv6_list()
));

$group->add(new Form_Button(
	'addgw6',
	'Add a new gateway',
	null,
	'fa-solid fa-plus'
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
	array_set_path($gateway_settings6, 'defaultgw', (strtolower($if) == "wan")),
));

$modal->addInput(new Form_Input(
	'gatewayname6',
	'Gateway name',
	'text',
	array_set_path($gateway_settings6, 'name', $defgatewayname6),
));

$modal->addInput(new Form_IpAddress(
	'gatewayip6',
	'Gateway IPv6',
	array_get_path($gateway_settings6, 'gateway'),
	'V6'
));

$modal->addInput(new Form_Input(
	'gatewaydescr6',
	'Description',
	'text',
	array_get_path($gateway_settings6, 'descr')
));

$btnaddgw6 = new Form_Button(
	'add6',
	'Add',
	null,
	'fa-solid fa-plus'
);

$btnaddgw6->setAttribute('type','button')->addClass('btn-success');

$btncnxgw6 = new Form_Button(
	'cnx6',
	'Cancel',
	null,
	'fa-solid fa-undo'
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
	array_get_path($pconfig, 'adv_dhcp_config_advanced'),
))->setHelp('Use advanced DHCP configuration options.');

$group->add(new Form_Checkbox(
	'adv_dhcp_config_file_override',
	null,
	'Configuration Override',
	array_get_path($pconfig, 'adv_dhcp_config_file_override'),
))->setHelp('Override the configuration from this file.');

$section->add($group);

$section->addInput(new Form_Input(
	'dhcphostname',
	'Hostname',
	'text',
	array_get_path($pconfig, 'dhcphostname'),
))->setHelp('The value in this field is sent as the DHCP client identifier and hostname when requesting a DHCP lease. Some ISPs may require this (for client identification).');

$section->addInput(new Form_IpAddress(
	'alias-address',
	'Alias IPv4 address',
	array_get_path($pconfig, 'alias-address'),
	'V4'
))->addMask('alias-subnet', array_get_path($pconfig, 'alias-subnet'), 32)->setHelp('The value in this field is used as a fixed alias IPv4 address by the DHCP client.');

$section->addInput(new Form_Input(
	'dhcprejectfrom',
	'Reject leases from',
	'text',
	array_get_path($pconfig, 'dhcprejectfrom'),
))->setHelp('To have the DHCP client reject offers from specific DHCP servers, enter their IP addresses here ' .
			'(separate multiple entries with a comma). ' .
			'This is useful for rejecting leases from cable modems that offer private IP addresses when they lose upstream sync.');

if (interface_is_vlan(array_get_path($wancfg, 'if')) != NULL) {
	$group = new Form_Group('DHCP VLAN Priority');
	$group->add(new Form_Checkbox(
		'dhcpvlanenable',
		null,
		'Enable dhcpclient VLAN Priority tagging',
		array_get_path($pconfig, 'dhcpvlanenable'),
	))->setHelp('Normally off unless specifically required by the ISP.');

	$group->add(new Form_Select(
		'dhcpcvpt',
		'VLAN Prio',
		array_get_path($pconfig, 'dhcpcvpt'),
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
	array_get_path($pconfig, 'adv_dhcp_pt_timeout'),
))->setHelp('Timeout');

$group->add(new Form_Input(
	'adv_dhcp_pt_retry',
	null,
	'number',
	array_get_path($pconfig, 'adv_dhcp_pt_retry'),
))->setHelp('Retry');

$group->add(new Form_Input(
	'adv_dhcp_pt_select_timeout',
	null,
	'number',
	array_get_path($pconfig, 'adv_dhcp_pt_select_timeout'),
	['min' => 0]
))->setHelp('Select timeout');

$group->add(new Form_Input(
	'adv_dhcp_pt_reboot',
	null,
	'number',
	array_get_path($pconfig, 'adv_dhcp_pt_reboot'),
))->setHelp('Reboot');

$group->add(new Form_Input(
	'adv_dhcp_pt_backoff_cutoff',
	null,
	'number',
	array_get_path($pconfig, 'adv_dhcp_pt_backoff_cutoff'),
))->setHelp('Backoff cutoff');

$group->add(new Form_Input(
	'adv_dhcp_pt_initial_interval',
	null,
	'number',
	array_get_path($pconfig, 'adv_dhcp_pt_initial_interval'),
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
	array_get_path($pconfig, 'adv_dhcp_config_file_override_path'),
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
	array_get_path($pconfig, 'adv_dhcp_send_options'),
))->setWidth(9)->sethelp('The values in this field are DHCP options to be sent when requesting a DHCP lease.	 [option declaration [, ...]] %1$s' .
			'Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s' .
			'Where C is U(pper) or L(ower) Case, and D is " :-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some ISPs may require certain options be or not be sent.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp_request_options',
	'Request options',
	'text',
	array_get_path($pconfig, 'adv_dhcp_request_options'),
))->setWidth(9)->sethelp('The values in this field are DHCP option 55 to be sent when requesting a DHCP lease.  [option [, ...]] %1$s' .
			'Some ISPs may require certain options be or not be requested.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp_required_options',
	'Require options',
	'text',
	array_get_path($pconfig, 'adv_dhcp_required_options'),
))->setWidth(9)->sethelp('The values in this field are DHCP options required by the client when requesting a DHCP lease.	 [option [, ...]]');

$section->addInput(new Form_Input(
	'adv_dhcp_option_modifiers',
	'Option modifiers',
	'text',
	array_get_path($pconfig, 'adv_dhcp_option_modifiers'),
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
	array_get_path($pconfig, 'adv_dhcp6_config_advanced'),
))->setHelp('Use advanced DHCPv6 configuration options.');

$group->add(new Form_Checkbox(
	'adv_dhcp6_config_file_override',
	null,
	'Configuration Override',
	array_get_path($pconfig, 'adv_dhcp6_config_file_override'),
))->setHelp('Override the configuration from this file.');

$section->add($group);

$section->addInput(new Form_Checkbox(
	'dhcp6usev4iface',
	'Use IPv4 connectivity as parent interface',
	'Request a IPv6 prefix/information through the IPv4 connectivity link',
	array_get_path($pconfig, 'dhcp6usev4iface'),
));

$section->addInput(new Form_Checkbox(
	'dhcp6prefixonly',
	'Request only an IPv6 prefix',
	'Only request an IPv6 prefix, do not request an IPv6 address',
	array_get_path($pconfig, 'dhcp6prefixonly'),
));

$section->addInput(new Form_Select(
	'dhcp6-ia-pd-len',
	'DHCPv6 Prefix Delegation size',
	array_get_path($pconfig, 'dhcp6-ia-pd-len'),
	["none" => "None", 16 => "48", 15 => "49", 14 => "50", 13 => "51", 12 => "52", 11 => "53", 10 => "54", 9 => "55", 8 => "56", 7 => "57", 6 => "58", 5 => "59", 4 => "60", 3 => "61", 2 => "62", 1 => "63", 0 => "64"]
))->setHelp('The value in this field is the delegated prefix length provided by the DHCPv6 server. Normally specified by the ISP.');

$section->addInput(new Form_Checkbox(
	'dhcp6-ia-pd-send-hint',
	'Send IPv6 prefix hint',
	'Send an IPv6 prefix hint to indicate the desired prefix size for delegation',
	array_get_path($pconfig, 'dhcp6-ia-pd-send-hint'),
));

$section->addInput(new Form_Checkbox(
	'dhcp6withoutra',
	'Do not wait for a RA',
	'Required by some ISPs, especially those not using PPPoE',
	array_get_path($pconfig, 'dhcp6withoutra'),
));

if (interface_is_vlan(array_get_path($wancfg, 'if')) != NULL) {
	$group = new Form_Group('DHCP6 VLAN Priority');

	$group->add(new Form_Checkbox(
		'dhcp6vlanenable',
		null,
		'Enable dhcp6c VLAN Priority tagging',
		array_get_path($pconfig, 'dhcp6vlanenable'),
	))->setHelp('Normally off unless specifically required by the ISP.');

	$group->add(new Form_Select(
		'dhcp6cvpt',
		'VLAN Prio',
		array_get_path($pconfig, 'dhcp6cvpt'),
		$vlanprio
	))->setHelp('Choose 802.1p priority to set.');

	$section->add($group);
}

$section->addInput(new Form_Input(
	'adv_dhcp6_config_file_override_path',
	'Configuration File Override',
	'text',
	array_get_path($pconfig, 'adv_dhcp6_config_file_override_path'),
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
	array_get_path($pconfig, 'adv_dhcp6_interface_statement_information_only_enable'),
	'Selected'
))->setHelp('Only exchange informational configuration parameters with servers.');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_send_options',
	'Send options',
	'text',
	array_get_path($pconfig, 'adv_dhcp6_interface_statement_send_options'),
))->setWidth(9)->sethelp('DHCP send options to be sent when requesting a DHCP lease.	 [option declaration [, ...]] %1$s' .
			'Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} %1$s' .
			'Where C is U(pper) or L(ower) Case, and D is " :-." Delimiter (space, colon, hyphen, or period) (omitted for none).%1$s' .
			'Some DHCP services may require certain options be or not be sent.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_request_options',
	'Request Options',
	'text',
	array_get_path($pconfig, 'adv_dhcp6_interface_statement_request_options'),
))->setWidth(9)->sethelp('DHCP request options to be sent when requesting a DHCP lease.	[option [, ...]] %1$s' .
			'Some DHCP services may require certain options be or not be requested.', '<br />');

$section->addInput(new Form_Input(
	'adv_dhcp6_interface_statement_script',
	'Scripts',
	'text',
	array_get_path($pconfig, 'adv_dhcp6_interface_statement_script'),
))->setWidth(9)->sethelp('Absolute path to a script invoked on certain conditions including when a reply message is received.%1$s' .
			'[/[dirname/[.../]]filename[.ext]].', '<br />');

$group = new Form_Group('Identity Association Statement');

$group->add(new Form_Checkbox(
	'adv_dhcp6_id_assoc_statement_address_enable',
	null,
	'Non-Temporary Address Allocation',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_address_enable'),
	'Selected'
));

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_id',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_address_id'),
))->sethelp('id-assoc na ID');

$group->add(new Form_IpAddress(
	'adv_dhcp6_id_assoc_statement_address',
	null,
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_address'),
	'V6'
))->sethelp('IPv6 address');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_pltime',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_address_pltime'),
))->sethelp('pltime');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_address_vltime',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_address_vltime'),
))->sethelp('vltime');

$section->add($group);

// Prefix delegation
$group = new Form_Group('');

$group->add(new Form_Checkbox(
	'adv_dhcp6_id_assoc_statement_prefix_enable',
	null,
	'Prefix Delegation ',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_prefix_enable'),
	'Selected'
));

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_id',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_prefix_id'),
))->sethelp('id-assoc pd ID');

$group->add(new Form_IpAddress(
	'adv_dhcp6_id_assoc_statement_prefix',
	null,
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_prefix'),
	'V6'
))->sethelp('IPv6 prefix');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_pltime',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_prefix_pltime'),
))->sethelp('pltime');

$group->add(new Form_Input(
	'adv_dhcp6_id_assoc_statement_prefix_vltime',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_id_assoc_statement_prefix_vltime'),
))->sethelp('vltime');

$section->add($group);

$group = new Form_Group('Prefix interface statement');

$group->add(new Form_Input(
	'adv_dhcp6_prefix_interface_statement_sla_id',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_prefix_interface_statement_sla_id'),
))->sethelp('Prefix Interface sla-id');

$group->add(new Form_Input(
	'adv_dhcp6_prefix_interface_statement_sla_len',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_prefix_interface_statement_sla_len'),
))->sethelp('sla-len');

$section->add($group);

$group = new Form_Group('Select prefix interface');
$section->addInput(new Form_Select(
	'adv_dhcp6_prefix_selected_interface',
	'Prefix Interface',
	array_get_path($pconfig, 'adv_dhcp6_prefix_selected_interface'),
	$interfaces
))->setHelp('Select the interface on which to apply the prefix delegation.');

$group = new Form_Group('Authentication statement');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_authname',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_authentication_statement_authname'),
))->sethelp('Authname');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_protocol',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_authentication_statement_protocol'),
))->sethelp('Protocol');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_algorithm',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_authentication_statement_algorithm'),
))->sethelp('Algorithm');

$group->add(new Form_Input(
	'adv_dhcp6_authentication_statement_rdm',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_authentication_statement_rdm'),
))->sethelp('RDM');

$section->add($group);

$group = new Form_Group('Keyinfo statement');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_keyname',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_key_info_statement_keyname'),
))->sethelp('Keyname');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_realm',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_key_info_statement_realm'),
))->sethelp('Realm');

$section->add($group);

$group = new Form_Group('');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_keyid',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_key_info_statement_keyid'),
))->sethelp('KeyID');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_secret',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_key_info_statement_secret'),
))->sethelp('Secret');

$group->add(new Form_Input(
	'adv_dhcp6_key_info_statement_expire',
	null,
	'text',
	array_get_path($pconfig, 'adv_dhcp6_key_info_statement_expire'),
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
	array_get_path($pconfig, 'prefix-6rd'),
))->sethelp('6RD IPv6 prefix assigned by the ISP. e.g. "2001:db8::/32"');

$section->addInput(new Form_Input(
	'gateway-6rd',
	'*6RD Border relay',
	'text',
	array_get_path($pconfig, 'gateway-6rd'),
))->sethelp('6RD IPv4 gateway address assigned by the ISP');

$section->addInput(new Form_Select(
	'prefix-6rd-v4plen',
	'6RD IPv4 Prefix length',
	array_get_path($pconfig, 'prefix-6rd-v4plen'),
	array_combine(range(0, 32), range(0, 32))
))->setHelp('6RD IPv4 prefix length. Normally specified by the ISP. A value of 0 means embed the entire IPv4 address in the 6RD prefix.');

$form->add($section);

// Track IPv6 ointerface section
$section = new Form_Section('Track IPv6 Interface');
$section->addClass('track6');

function build_ipv6interface_list() {
	global $form;

	$list = ['' => ''];

	$interfaces = get_configured_interface_with_descr(true);
	$dynv6ifs = [];

	foreach ($interfaces as $iface => $ifacename) {
		switch (config_get_path("interfaces/{$iface}/ipaddrv6")) {
			case "6to4":
			case "6rd":
			case "dhcp6":
				array_set_path($dynv6ifs,
					$iface,
					[
						'name' => $ifacename,
						'ipv6_num_prefix_ids' => pow(2, (int) calculate_ipv6_delegation_length($iface)) - 1
					]);
				break;
			default:
				continue 2;
		}
	}

	foreach ($dynv6ifs as $iface => $ifacedata) {
		array_set_path($list, $iface, array_get_path($ifacedata, 'name'));

		$form->addGlobal(new Form_Input(
			'ipv6-num-prefix-ids-' . $iface,
			null,
			'hidden',
			array_get_path($ifacedata, 'ipv6_num_prefix_ids')
		));
	}

	return($list);
}

$section->addInput(new Form_Select(
	'track6-interface',
	'*IPv6 Interface',
	array_get_path($pconfig, 'track6-interface'),
	build_ipv6interface_list()
))->setHelp('Selects the dynamic IPv6 WAN interface to track for configuration.');

if (array_get_path($pconfig, 'track6-prefix-id') == "") {
	array_set_path($pconfig, 'track6-prefix-id', 0);
}

$section->addInput(new Form_Input(
	'track6-prefix-id--hex',
	'IPv6 Prefix ID',
	'text',
	sprintf("%x", array_get_path($pconfig, 'track6-prefix-id'))
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
	array_get_path($pconfig, 'country'),
	[]
));

$section->addInput(new Form_Select(
	'provider_list',
	'Provider',
	array_get_path($pconfig, 'provider_list'),
	[]
));

$section->addInput(new Form_Select(
	'providerplan',
	'Plan',
	array_get_path($pconfig, 'providerplan'),
	[]
))->setHelp('Select to fill in service provider data.');

$section->addInput(new Form_Input(
	'ppp_username',
	'Username',
	'text',
	array_get_path($pconfig, 'ppp_username'),
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'ppp_password',
	'Password',
	'password',
	array_get_path($pconfig, 'ppp_password'),
));

$section->addInput(new Form_Input(
	'phone',
	'*Phone number',
	'text',
	array_get_path($pconfig, 'phone'),
))->setHelp('Typically *99# for GSM networks and #777 for CDMA networks.');

$section->addInput(new Form_Input(
	'apn',
	'Access Point Name',
	'text',
	array_get_path($pconfig, 'apn'),
));


function build_port_list() {
	$list = ["" => "None"];

	$portlist = glob("/dev/cua*");
	$modems	  = glob("/dev/modem*");
	$portlist = array_merge($portlist, $modems);

	foreach ($portlist as $port) {
		if (preg_match("/\.(lock|init)$/", $port)) {
			continue;
		}

		$port = trim($port);
		$list[$port] = $port;
	}

	return($list);
}

$section->addInput(new Form_Select(
	'port',
	"*Modem port",
	array_get_path($pconfig, 'port'),
	build_port_list()
));

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced PPP',
	array_path_enabled($pconfig, '', 'pppid') ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars(array_get_path($pconfig, 'pppid')) : 'interfaces_ppps_edit.php',
	'fa-solid fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('Create a new PPP configuration.');

$form->add($section);

// PPPoE configuration
$section = new Form_Section('PPPoE Configuration');
$section->addClass('pppoe');

$section->addInput(new Form_Input(
	'pppoe_username',
	'*Username',
	'text',
	array_get_path($pconfig, 'pppoe_username'),
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'pppoe_password',
	'*Password',
	'password',
	array_get_path($pconfig, 'pppoe_password'),
));

$section->addInput(new Form_Input(
	'provider',
	'Service name',
	'text',
	array_get_path($pconfig, 'provider'),
))->setHelp('This field can usually be left empty.');

$section->addInput(new Form_Input(
	'hostuniq',
	'Host-Uniq',
	'text',
	array_get_path($pconfig, 'hostuniq'),
))->setHelp('A unique host tag value for this PPPoE client. Leave blank unless a value is required by the service provider.');

$section->addInput(new Form_Checkbox(
	'pppoe_dialondemand',
	'Dial on demand',
	'Enable Dial-On-Demand mode ',
	array_get_path($pconfig, 'pppoe_dialondemand'),
	'enable'
));

$section->addInput(new Form_Input(
	'pppoe_idletimeout',
	'Idle timeout',
	'number',
	array_get_path($pconfig, 'pppoe_idletimeout'),
	['min' => 0]
))->setHelp('If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. ' .
			'An idle timeout of zero disables this feature.');

$section->addInput(new Form_Select(
	'pppoe-reset-type',
	'Periodic reset',
	array_get_path($pconfig, 'pppoe-reset-type'),
	['' => gettext('Disabled'), 'custom' => gettext('Custom'), 'preset' => gettext('Pre-set')]
))->setHelp('Select a reset timing type.');

$group = new Form_Group('Custom reset');
$group->addClass('pppoecustom');

$group->add(new Form_Input(
	'pppoe_resethour',
	null,
	'number',
	(strlen(array_get_path($pconfig, 'pppoe_resethour')) > 0) ? array_get_path($pconfig, 'pppoe_resethour'): "0",
	['min' => 0, 'max' => 23]
))->setHelp('Hour (0-23), blank for * (every)');

$group->add(new Form_Input(
	'pppoe_resetminute',
	null,
	'number',
	(strlen(array_get_path($pconfig, 'pppoe_resetminute')) > 0) ? array_get_path($pconfig, 'pppoe_resetminute') : "0",
	['min' => 0, 'max' => 59]
))->setHelp('Minute (0-59), blank for * (every)');

$group->add(new Form_Input(
	'pppoe_resetdate',
	null,
	'text',
	array_get_path($pconfig, 'pppoe_resetdate'),
))->setHelp('Specific date (mm/dd/yyyy)');

$group->setHelp('Leave the date field empty, for the reset to be executed each day at the time specified by the minutes and hour fields');

$section->add($group);

$group = new Form_MultiCheckboxGroup('cron based reset');
$group->addClass('pppoepreset');

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each month ("0 0 1 * *")',
	array_get_path($pconfig, 'pppoe_monthly'),
	'monthly'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each week ("0 0 * * 0")',
	array_get_path($pconfig, 'pppoe_weekly'),
	'weekly'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each day ("0 0 * * *")',
	array_get_path($pconfig, 'pppoe_daily'),
	'daily'
))->displayAsRadio();

$group->add(new Form_MultiCheckbox(
	'pppoe_pr_preset_val',
	null,
	'Reset at each hour ("0 * * * *")',
	array_get_path($pconfig, 'pppoe_hourly'),
	'hourly'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced and MLPPP',
	array_path_enabled($pconfig, '', 'pppid') ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars(array_get_path($pconfig, 'pppid')) : 'interfaces_ppps_edit.php',
	'fa-solid fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('Click for additional PPPoE configuration options. Save first if changes have been made.');

$form->add($section);

// PPTP & L2TP Configuration section
$section = new Form_Section('PPTP/L2TP Configuration');
$section->addClass('pptp');

$section->addInput(new Form_Input(
	'pptp_username',
	'*Username',
	'text',
	array_get_path($pconfig, 'pptp_username'),
	['autocomplete' => 'new-password']
));

$section->addPassword(new Form_Input(
	'pptp_password',
	'*Password',
	'password',
	array_get_path($pconfig, 'pptp_password'),
));

$group = new Form_Group('Shared Secret');

$group->add(new Form_Input(
	'l2tp_secret',
	'*Secret',
	'password',
	array_get_path($pconfig, 'l2tp_secret'),
))->setHelp('L2TP tunnel Shared Secret. Used to authenticate tunnel connection and encrypt ' .
	    'important control packet contents. (Optional)');

$group->addClass('l2tp_secret');
$section->add($group);

$section->addInput(new Form_IpAddress(
	'pptp_local0',
	'*Local IP address',
	$_POST['pptp_local0'] ? $_POST['pptp_local0'] : array_get_path($pconfig, 'pptp_localip', [])[0],
	'V4'
))->addMask('pptp_subnet0', $_POST['pptp_subnet0'] ? $_POST['pptp_subnet0'] : array_get_path($pconfig, 'pptp_subnet', [])[0]);

$section->addInput(new Form_IpAddress(
	'pptp_remote0',
	'*Remote IP address',
	$_POST['pptp_remote0'] ? $_POST['pptp_remote0'] : array_get_path($pconfig, 'pptp_remote', [])[0],
	'HOSTV4'
));

$section->addInput(new Form_Checkbox(
	'pptp_dialondemand',
	'Dial on demand',
	'Enable Dial-On-Demand mode ',
	array_get_path($pconfig, 'pptp_dialondemand'),
	'enable'
))->setHelp('This option causes the interface to operate in dial-on-demand mode, allowing it to be a virtual full time connection. ' .
			'The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.');

$section->addInput(new Form_Input(
	'pptp_idletimeout',
	'Idle timeout (seconds)',
	'number',
	array_get_path($pconfig, 'pptp_idletimeout'),
	['min' => 0]
))->setHelp('If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. ' .
			'An idle timeout of zero disables this feature.');

if (array_path_enabled($pconfig, 'pptp_localip', '1') ||
    array_path_enabled($pconfig, 'pptp_subnet', '1') |
    array_path_enabled($pconfig, 'pptp_remote', '1')) {
	$mlppp_text = gettext("There are additional Local and Remote IP addresses defined for MLPPP.") . "<br />";
} else {
	$mlppp_text = "";
}

$section->addInput(new Form_Button(
	'btnadvppp',
	'Advanced and MLPPP',
	array_path_enabled($pconfig, '', 'pppid') ? 'interfaces_ppps_edit.php?id=' . htmlspecialchars(array_get_path($pconfig, 'pppid')) : 'interfaces_ppps_edit.php',
	'fa-solid fa-cog'
))->setAttribute('type','button')->addClass('btn-info')->setAttribute('id')->setHelp('%sClick for additional PPTP and L2TP configuration options. Save first if changes have been made.', $mlppp_text);

$form->add($section);

// Wireless interface
if (is_array(array_get_path($wancfg, 'wireless'))) {
	$section = new Form_Section('Common Wireless Configuration - Settings apply to all wireless networks on ' . $wlanbaseif . '.');

	$section->addInput(new Form_Checkbox(
		'persistcommonwireless',
		'Persist common settings',
		'Preserve common wireless configuration through interface deletions and reassignments.',
		array_get_path($pconfig, 'persistcommonwireless'),
		'yes'
	));

	$mode_list = ['auto' => 'Auto'];

	if (is_array($wl_modes)) {
		foreach ($wl_modes as $wl_standard => $wl_channels) {
			array_set_path($mode_list, $wl_standard, '802.' . $wl_standard);
		}
	}

	if (count($mode_list) == 1) {
		$mode_list[''] = '';
	}

	$section->addInput(new Form_Select(
		'standard',
		'Standard',
		(array_get_path($pconfig, 'standard') == "") ? "11ng" : array_get_path($pconfig, 'standard'),
		$mode_list
	));

	if (isset($wl_modes['11g'])) {
		$section->addInput(new Form_Select(
			'protmode',
			'802.11g OFDM Protection Mode',
			array_get_path($pconfig, 'protmode'),
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
			} elseif ($wl_standard == "11ng") {
				$wl_standard = "11b/g/n";
			} elseif ($wl_standard == "11na") {
				$wl_standard = "11a/n";
			}

			foreach ($wl_channels as $wl_channel) {
				if (isset($wl_chaninfo[$wl_channel])) {
					array_set_path($mode_list, $wl_channel, $wl_standard . ' - ' . $wl_channel);
				} else {
					$tcinfo = array_get_path($wl_chaninfo, $wl_channel, []);
					array_set_path($mode_list, $wl_channel, $wl_standard . ' - ' . $wl_channel . ' (' . $tcinfo[1] . ' @ ' . $tcinfo[2] . ' / ' . $tcinfo[3] . ')');
				}
			}
		}
	}

	$section->addInput(new Form_Select(
		'channel',
		'Channel',
		array_get_path($pconfig, 'channel'),
		$mode_list
	))->setHelp('Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain) %1$s' .
				'Not all channels may be supported by some cards.  Auto may override the wireless standard selected above.', '<br />');

	$section->addInput(new Form_Select(
		'channel_width',
		'Channel width',
		array_get_path($pconfig, 'channel_width'),
		$wl_ht_modes
	))->setHelp('Channel width for 802.11n mode. Not all cards may support channel width changing.');

	if (ANTENNAS) {
		if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"]) ||
		    isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"]) ||
		    isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])) {
			$group = new Form_Group('Antenna Settings');

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"])) {
				$group->add(new Form_Select(
					'diversity',
					null,
					array_get_path($pconfig, 'diversity', ''),
					['' => gettext('Default'), '0' => gettext('Off'), '1' => gettext('On')]
				))->setHelp('Diversity');
			}

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"])) {
				$group->add(new Form_Select(
					'txantenna',
					null,
					array_get_path($pconfig, 'txantenna', ''),
					['' => gettext('Default'), '0' => gettext('Auto'), '1' => gettext('#1'), '2' => gettext('#2')]
				))->setHelp('Transmit antenna');
			}

			if (isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])) {
				$group->add(new Form_Select(
					'rxantenna',
					null,
					array_get_path($pconfig, 'rxantenna', ''),
					['' => gettext('Default'), '0' => gettext('Auto'), '1' => gettext('#1'), '2' => gettext('#2')]
				))->setHelp('Receive antenna');
			}

			$group->setHelp('Note: The antenna numbers do not always match up with the labels on the card.');

			$section->add($group);
		}
	}

	if (isset($wl_sysctl["{$wl_sysctl_prefix}.slottime"]) &&
	    isset($wl_sysctl["{$wl_sysctl_prefix}.acktimeout"]) &&
	    isset($wl_sysctl["{$wl_sysctl_prefix}.ctstimeout"])) {
			$section->addInput(new Form_Input(
				'distance',
				'Distance setting (meters)',
				'test',
				array_get_path($pconfig, 'distance'),
			))->setHelp('This field can be used to tune ACK/CTS timers to fit the distance between AP and Client');
	}

	$form->add($section);

	// Regulatory settings
	$section = new Form_Section('Regulatory Settings');

	$domain_list = ["" => 'Default'];

	if (is_array($wl_regdomains)) {
		foreach ($wl_regdomains as $wl_regdomain_key => $wl_regdomain) {
			array_set_path($domain_list, array_get_path($wl_regdomains_attr, "{$wl_regdomain_key}/ID"), array_get_path($wl_regdomain, 'name'));
		}
	}

	$section->addInput(new Form_Select(
		'regdomain',
		'Regulatory domain',
		array_get_path($pconfig, 'regdomain'),
		$domain_list
	))->setHelp('Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work');

	$country_list = ['' => 'Default'];

	if (is_array($wl_countries)) {
		foreach ($wl_countries as $wl_country_key => $wl_country) {
			array_set_path($country_list, array_get_path($wl_countries_attr, "{$wl_country_key}/ID"), array_get_path($wl_country, 'name'));
		}
	}

	$section->addInput(new Form_Select(
		'regcountry',
		'Country',
		array_get_path($pconfig, 'regcountry'),
		$country_list
	))->setHelp('Any country setting other than "Default" will override the regulatory domain setting');

	$section->addInput(new Form_Select(
		'reglocation',
		'Location',
		array_get_path($pconfig, 'reglocation'),
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
		array_get_path($pconfig, 'mode'),
		['bss' => gettext('Infrastructure (BSS)'), 'adhoc' => gettext('Ad-hoc (IBSS)'), 'hostap' => gettext('Access Point')]
	));

	$section->addInput(new Form_Input(
		'ssid',
		'SSID',
		'text',
		array_get_path($pconfig, 'ssid'),
	));

	if (isset($wl_modes['11ng']) ||
	    isset($wl_modes['11na'])) {
		$section->addInput(new Form_Select(
			'puremode',
			'Minimum wireless standard',
			array_get_path($pconfig, 'puremode'),
			['any' => gettext('Any'), '11g' => gettext('802.11g'), '11n' => gettext('802.11n')]
		))->setHelp('When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)');
	} elseif (isset($wl_modes['11g'])) {
		$section->addInput(new Form_Checkbox(
			'puremode',
			'802.11g only',
			null,
			array_get_path($pconfig, 'puremode'),
			'11g'
		))->setHelp('When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)');
	}

	$section->addInput(new Form_Checkbox(
		'apbridge_enable',
		'Allow intra-BSS communication',
		'Allow packets to pass between wireless clients directly when operating as an access point',
		array_get_path($pconfig, 'apbridge_enable'),
		'yes'
	))->setHelp('Provides extra security by isolating clients so they cannot directly communicate with one another');

	$section->addInput(new Form_Checkbox(
		'wme_enable',
		'Enable WME',
		'Force the card to use WME (wireless QoS)',
		array_get_path($pconfig, 'wme_enable'),
		'yes'
	));

	$section->addInput(new Form_Checkbox(
		'hidessid_enable',
		'Hide SSID',
		'Disable broadcasting of the SSID for this network (This may cause problems for some clients, and the SSID may still be discovered by other means.)',
		array_get_path($pconfig, 'hidessid_enable'),
		'yes'
	));

	$form->add($section);

	// WPA Section
	$section = new Form_Section('WPA');

	$section->addInput(new Form_Checkbox(
		'wpa_enable',
		'Enable',
		'Enable WPA',
		array_get_path($pconfig, 'wpa_enable'),
		'yes'
	));

	$section->addInput(new Form_Select(
		'wpa_mode',
		'WPA mode',
		array_get_path($pconfig, 'wpa_mode', 2),
		['1' => gettext('WPA'), '2' => gettext('WPA2'), '3' => gettext('Both')]
	));

	$section->addInput(new Form_Select(
		'wpa_pairwise',
		'WPA Pairwise',
		array_get_path($pconfig, 'wpa_pairwise', 'CCMP'),
		['CCMP TKIP' => gettext('Both'), 'CCMP' => gettext('AES (recommended)'), 'TKIP' => gettext('TKIP')]
	));

	$section->addInput(new Form_Select(
		'wpa_key_mgmt',
		'WPA Key Management Mode',
		array_get_path($pconfig, 'wpa_key_mgmt'),
		['WPA-PSK' => gettext('Pre-Shared Key'), 'WPA-EAP' => gettext('Extensible Authentication Protocol'), 'WPA-PSK WPA-EAP' => gettext('Both')]
	));

	$section->addInput(new Form_Input(
		'passphrase',
		'WPA Pre-Shared Key',
		'text',
		array_get_path($pconfig, 'passphrase'),
	))->setHelp('WPA Passphrase must be between 8 and 63 characters long');

	$section->addInput(new Form_Select(
		'wpa_eap_client_mode',
		'EAP Client Mode',
		array_get_path($pconfig, 'wpa_eap_client_mode'),
		['PEAP' => 'PEAP', 'TLS' => 'TLS', 'TTLS' => 'TTLS']
	));

	$section->addInput(new Form_Select(
		'wpa_eap_ca',
		'Certificate Authority',
		array_get_path($pconfig, 'wpa_eap_ca'),
		cert_build_list('ca', 'HTTPS')
	));

	$section->addInput(new Form_Select(
		'wpa_eap_inner_auth',
		'Inner Authentication Method',
		array_get_path($pconfig, 'wpa_eap_inner_auth'),
		['MSCHAPV2' => gettext('MSCHAPv2'), 'MD5' => gettext('MD5'), 'PAP' => gettext('PAP')]
	));

	$section->addInput(new Form_Input(
		'wpa_eap_inner_id',
		'*Inner Authentication Identity',
		'text',
		array_get_path($pconfig, 'wpa_eap_inner_id'),
	));

	$section->addInput(new Form_Input(
		'wpa_eap_inner_password',
		'*Inner Authentication Passphrase',
		'text',
		array_get_path($pconfig, 'wpa_eap_inner_password'),
	));

	$section->addInput(new Form_Select(
		'wpa_eap_cert',
		'TLS/TTLS Client Certificate',
		array_get_path($pconfig, 'wpa_eap_cert'),
		cert_build_list('cert', 'HTTPS')
	));

	$section->addInput(new Form_Input(
		'wpa_group_rekey',
		'Group Key Rotation',
		'number',
		array_get_path($pconfig, 'wpa_group_rekey', 60),
		['min' => '1', 'max' => 9999]
	))->setHelp('Time between group rekey events, specified in seconds. Allowed values are 1-9999. Must be shorter than Master Key Regeneration time');

	$section->addInput(new Form_Input(
		'wpa_gmk_rekey',
		'Group Master Key Regeneration',
		'number',
		array_get_path($pconfig, 'wpa_gmk_rekey', 3600),
		['min' => '1', 'max' => 9999]
	))->setHelp('Time between GMK rekey events, specified in seconds. Allowed values are 1-9999. Must be longer than Group Key Rotation time');

	$section->addInput(new Form_Checkbox(
		'wpa_strict_rekey',
		'Strict Key Regeneration',
		'Force the AP to rekey whenever a client disassociates',
		array_get_path($pconfig, 'wpa_strict_rekey'),
		'yes'
	));

	$form->add($section);

	$section = new Form_Section('802.1x RADIUS Options');
	$section->addClass('ieee8021x_group');

	$section->addInput(new Form_Checkbox(
		'ieee8021x',
		'IEEE802.1X',
		'Enable 802.1X authentication',
		array_get_path($pconfig, 'ieee8021x'),
		'yes'
	));

	$group = new Form_Group('Primary 802.1X server');

	$group->add(new Form_IpAddress(
		'auth_server_addr',
		'IP Address',
		array_get_path($pconfig, 'auth_server_addr'),
	))->setHelp('IP address of the RADIUS server');

	$group->add(new Form_Input(
		'auth_server_port',
		'Port',
		'number',
		array_get_path($pconfig, 'auth_server_port'),
	))->setHelp('Server auth port. Default is 1812');

	$group->add(new Form_Input(
		'auth_server_shared_secret',
		'Shared Secret',
		'text',
		array_get_path($pconfig, 'auth_server_shared_secret'),
	))->setHelp('RADIUS Shared secret for this firewall');

	$section->add($group);

	$group = new Form_Group('Secondary 802.1X server');

	$group->add(new Form_IpAddress(
		'auth_server_addr2',
		'IP Address',
		array_get_path($pconfig, 'auth_server_addr2'),
	))->setHelp('IP address of the RADIUS server');

	$group->add(new Form_Input(
		'auth_server_port2',
		'Port',
		'number',
		array_get_path($pconfig, 'auth_server_port2'),
	))->setHelp('Server auth port. Default is 1812');

	$group->add(new Form_Input(
		'auth_server_shared_secret2',
		'Shared Secret',
		'text',
		array_get_path($pconfig, 'auth_server_shared_secret2'),
	))->setHelp('RADIUS Shared secret for this firewall');

	$section->add($group);

	$section->addInput(new Form_Checkbox(
		'rsn_preauth',
		'Authentication Roaming Preauth',
		null,
		array_get_path($pconfig, 'rsn_preauth'),
		'yes'
	))->setHelp('Pre-authentication to speed up roaming between access points.');

	$form->add($section);
}

$section = new Form_Section('Reserved Networks');

$section->addInput(new Form_Checkbox(
	'blockpriv',
	'Block private networks and loopback addresses',
	'',
	array_get_path($pconfig, 'blockpriv'),
	'yes'
))->setHelp('Blocks traffic from IP addresses that are reserved for private networks per RFC 1918 (10/8, 172.16/12, 192.168/16) ' .
			'and unique local addresses per RFC 4193 (fc00::/7) as well as loopback addresses (127/8). This option should ' .
			'generally be turned on, unless this network interface resides in such a private address space, too.');

$section->addInput(new Form_Checkbox(
	'blockbogons',
	'Block bogon networks',
	'',
	array_get_path($pconfig, 'blockbogons'),
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

if (array_get_path($wancfg, 'if') == array_get_path($a_ppps, "{$pppid}/if")) {
	$form->addGlobal(new Form_Input(
		'ppp_port',
		null,
		'hidden',
		array_get_path($pconfig, 'port'),
	));
}

$form->addGlobal(new Form_Input(
	'ptpid',
	null,
	'hidden',
	array_get_path($pconfig, 'ptpid'),
));


// Add new gateway modal pop-up
$modal = new Modal('New IPv4 Gateway', 'newgateway4', 'large');

$modal->addInput(new Form_Checkbox(
	'defaultgw4',
	'Default',
	'Default gateway',
	array_get_path($gateway_settings4, 'defaultgw', (strtolower($if) == "wan")),
));

$modal->addInput(new Form_Input(
	'gatewayname4',
	'Gateway name',
	'text',
	array_get_path($gateway_settings4, 'name', $defgatewayname4),
));

$modal->addInput(new Form_IpAddress(
	'gatewayip4',
	'Gateway IPv4',
	array_get_path($gateway_settings4, 'gateway'),
	'V4'
));

$modal->addInput(new Form_Input(
	'gatewaydescr4',
	'Description',
	'text',
	array_get_path($gateway_settings4, 'descr'),
));

$btnaddgw4 = new Form_Button(
	'add4',
	'Add',
	null,
	'fa-solid fa-plus'
);

$btnaddgw4->setAttribute('type','button')->addClass('btn-success');

$btncnxgw4 = new Form_Button(
	'cnx4',
	'Cancel',
	null,
	'fa-solid fa-undo'
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

		if ((t != "l2tp") && (t != "pptp")) {
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

		if ((t != "l2tp") && (t != "pptp")) {
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
		$.ajax({
			type: 'post',
			url: 'getserviceproviders.php',
			data: { get_country_list: true },
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
			type: 'POST',
			data: {country : $('#country').val(), provider : $('#provider_list').val(), plan : $('#providerplan').val()},
			success: function(data, textStatus, response) {
				var xmldoc = response.responseXML;
				var provider = xmldoc.getElementsByTagName('connection')[0];
				$('#ppp_username').val('');
				$('#ppp_password').val('');
				$('#ppp_password_confirm').val('');
				if (provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
					$('#phone').val('#777');
					$('#apn').val('');
				} else {
					$('#phone').val('*99#');
					$('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
				}
				ppp_username = provider.getElementsByTagName('username')[0].firstChild.data;
				ppp_password = provider.getElementsByTagName('password')[0].firstChild.data;
				$('#ppp_username').val(ppp_username);
				$('#ppp_password').val(ppp_password);
				$('#ppp_password_confirm').val(ppp_password);
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
		if (val == "SavedCfg")	setPresetsnow("<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_timeout'));?>", "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_retry'));?>", "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_select_timeout'));?>", "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_reboot'));?>", "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_backoff_cutoff'));?>", "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_initial_interval'));?>");
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
		updateeapclientmode($('#wpa_eap_client_mode').val());
		updatewpakeymgmt($('#wpa_key_mgmt').val());
	}

	function updateeapclientmode(m) {
		if ($('#mode').val() == 'bss') {
			var wpa = !($('#wpa_enable').prop('checked'));
		} else {
			var wpa = true;
		}
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
		hideInput('passphrase', false);
		hideInput('wpa_eap_client_mode', true);
		hideInput('wpa_eap_ca', true);
		hideInput('wpa_eap_cert', true);
		hideInput('wpa_eap_inner_auth', true);
		hideInput('wpa_eap_inner_id', true);
		hideInput('wpa_eap_inner_password', true);
		hideClass('ieee8021x_group', true);
		if (m == "WPA-EAP") {
			hideInput('passphrase', true);
			if ($('#mode').val() == 'bss') {
				hideInput('wpa_eap_client_mode', false);
				hideInput('wpa_eap_ca', false);
				updateeapclientmode($('#wpa_eap_client_mode').val());
			} else if ($('#mode').val() == 'hostap') {
				hideClass('ieee8021x_group', false);
			}
		} else if (m != "WPA-PSK") {
			hideInput('passphrase', false);
			if ($('#mode').val() == 'bss') {
				hideInput('wpa_eap_client_mode', false);
				hideInput('wpa_eap_ca', false);
				hideInput('wpa_eap_cert', false);
				hideInput('wpa_eap_inner_auth', false);
				hideInput('wpa_eap_inner_id', false);
				hideInput('wpa_eap_inner_password', false);
			} else if ($('#mode').val() == 'hostap') {
				hideClass('ieee8021x_group', false);
			}
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

	// Set preset buttons on page load
	var sv = "<?=htmlspecialchars(array_get_path($pconfig, 'adv_dhcp_pt_values'));?>";
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
