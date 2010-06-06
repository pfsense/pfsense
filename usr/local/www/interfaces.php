<?php
/* $Id$ */
/*
	interfaces.php
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2006 Daniel S. Haischt.
	Copyright (C) 2008 Ermal Luçi
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

if ($_REQUEST['if']) {
	$if = $_REQUEST['if'];
} else {
	$if = "wan";
}

define("CRON_PPPOE_CMD_FILE", "/conf/pppoe{$if}restart");
define("CRON_MONTHLY_PATTERN", "0 0 1 * *");
define("CRON_WEEKLY_PATTERN", "0 0 * * 0");
define("CRON_DAILY_PATTERN", "0 0 * * *");
define("CRON_HOURLY_PATTERN", "0 * * * *");

function getMPDCRONSettings() {
	global $config;
	if (is_array($config['cron']['item'])) {
		for ($i = 0; $i < count($config['cron']['item']); $i++) {
			$item = $config['cron']['item'][$i];
			if (strpos($item['command'], CRON_PPPOE_CMD_FILE) !== false) {
				return array("ID" => $i, "ITEM" => $item);
			}
		}
	}
	return NULL;
}

function getMPDResetTimeFromConfig() {
	$itemhash = getMPDCRONSettings();
	$cronitem = $itemhash['ITEM'];
	if (isset($cronitem)) {
		return "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
	} else {
		return NULL;
	}
}

function remove_bad_chars($string) {
	return preg_replace('/[^a-z|_|0-9]/i','',$string);
}

if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();

$a_gateways = &$config['gateways']['gateway_item'];

$wancfg = &$config['interfaces'][$if];

$pconfig['pppoe_username'] = $wancfg['pppoe_username'];
$pconfig['pppoe_password'] = $wancfg['pppoe_password'];
$pconfig['provider'] = $wancfg['provider'];
$pconfig['pppoe_dialondemand'] = isset($wancfg['ondemand']);
$pconfig['pppoe_idletimeout'] = $wancfg['timeout'];

/* ================================================ */
/* = force a connection reset at a specific time? = */
/* ================================================ */

if (isset($wancfg['pppoe']['pppoe-reset-type'])) {
	$resetTime = getMPDResetTimeFromConfig();  
	$pconfig['pppoe_preset'] = true;
	if ($wancfg['pppoe']['pppoe-reset-type'] == "custom") {
		$resetTime_a = split(" ", $resetTime);
		$pconfig['pppoe_pr_custom'] = true;
		$pconfig['pppoe_resetminute'] = $resetTime_a[0];
		$pconfig['pppoe_resethour'] = $resetTime_a[1];
		/*  just initialize $pconfig['pppoe_resetdate'] if the
		 *  coresponding item contains appropriate numeric values.
		 */
		if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*") 
			$pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
	} else if ($wancfg['pppoe']['pppoe-reset-type'] == "preset") {
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
}

$pconfig['pptp_username'] = $wancfg['pptp_username'];
$pconfig['pptp_password'] = $wancfg['pptp_password'];
$pconfig['pptp_local'] = $wancfg['local'];
$pconfig['pptp_subnet'] = $wancfg['subnet'];
$pconfig['pptp_remote'] = $wancfg['remote'];
$pconfig['pptp_dialondemand'] = isset($wancfg['ondemand']);
$pconfig['pptp_idletimeout'] = $wancfg['timeout'];

$pconfig['dhcphostname'] = $wancfg['dhcphostname'];
$pconfig['alias-address'] = $wancfg['alias-address'];
$pconfig['alias-subnet'] = $wancfg['alias-subnet'];

// Populate page descr if it does not exist.
if($if == "wan" && !$wancfg['descr']) {
	$wancfg['descr'] = "WAN";
} else if ($if == "lan" && !$wancfg['descr']) {
	$wancfg['descr'] = "LAN";
}
$pconfig['descr'] = remove_bad_chars($wancfg['descr']);

$pconfig['enable'] = isset($wancfg['enable']);

if (is_array($config['aliases']['alias'])) {
	foreach($config['aliases']['alias'] as $alias) {
		if($alias['name'] == $wancfg['descr']) {
			$input_errors[] = gettext("Sorry, an alias with the name {$wancfg['descr']} already exists.");
		}
	}
}

switch($wancfg['ipaddr']) {
	case "dhcp":
		$pconfig['type'] = "dhcp";
		break;
	case "carpdev-dhcp":
		$pconfig['type'] = "carpdev-dhcp";
		$pconfig['ipaddr'] = "";
		break;
	case "pppoe":
		$pconfig['type'] = "pppoe";
		break;
	case "pptp":
		$pconfig['type'] = "pptp";
		break;
	case "ppp":
		$pconfig['type'] = "ppp";
		break;
	default:
		if(is_ipaddr($wancfg['ipaddr'])) {
			$pconfig['type'] = "static";
			$pconfig['ipaddr'] = $wancfg['ipaddr'];
			$pconfig['subnet'] = $wancfg['subnet'];
			$pconfig['gateway'] = $wancfg['gateway'];
		} else {
			$pconfig['type'] = "none";
		}
		break;
}

$pconfig['blockpriv'] = isset($wancfg['blockpriv']);
$pconfig['blockbogons'] = isset($wancfg['blockbogons']);
$pconfig['spoofmac'] = $wancfg['spoofmac'];
$pconfig['mtu'] = $wancfg['mtu'];

/* Wireless interface? */
if (isset($wancfg['wireless'])) {
	/* Get wireless modes */
	$wlanif = get_real_interface($if);
	if (!does_interface_exist($wlanif))
		interface_wireless_clone($wlanif, $wancfg);
	$wlanbaseif = interface_get_wireless_base($wancfg['if']);
	$wl_modes = get_wireless_modes($if);
	$wl_chaninfo = get_wireless_channel_info($if);
	$wl_regdomain_xml_attr = array();
	$wl_regdomain_xml = parse_xml_regdomain($wl_regdomain_xml_attr);
	$wl_regdomains = &$wl_regdomain_xml['regulatory-domains']['rd'];
	$wl_regdomains_attr = &$wl_regdomain_xml_attr['regulatory-domains']['rd'];
	$wl_countries = &$wl_regdomain_xml['country-codes']['country'];
	$wl_countries_attr = &$wl_regdomain_xml_attr['country-codes']['country'];
	$pconfig['standard'] = $wancfg['wireless']['standard'];
	$pconfig['mode'] = $wancfg['wireless']['mode'];
	$pconfig['protmode'] = $wancfg['wireless']['protmode'];
	$pconfig['ssid'] = $wancfg['wireless']['ssid'];
	$pconfig['channel'] = $wancfg['wireless']['channel'];
	$pconfig['txpower'] = $wancfg['wireless']['txpower'];
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

if ($_POST['apply']) {
	unset($input_errors);
	if (!is_subsystem_dirty('interfaces'))
		$intput_errors[] = "You have already applied your settings!";
	else {	
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		clear_subsystem_dirty('interfaces');
		if ($pconfig['enable'])
			interface_configure($if, true);
		else
			interface_bring_down($if);
		
		/* restart snmp so that it binds to correct address */		
		services_snmpd_configure();		
		if ($if == "lan") 		
			$savemsg = "The changes have been applied.  You may need to correct your web browser's IP address.";

		/* sync filter configuration */
		setup_gateways_monitor();

		clear_subsystem_dirty('staticroutes');
		
		filter_configure();
		
		enable_rrd_graphing();
	}
	header("Location: interfaces.php?if={$if}");
	exit;
} else

if ($_POST && $_POST['enable'] != "yes") {
	unset($wancfg['enable']);
	if (isset($wancfg['wireless'])) {
		interface_sync_wireless_clones($wancfg, false);
	}
	write_config("Interface {$_POST['descr']}({$if}) is now disabled.");
	mark_subsystem_dirty('interfaces');
	header("Location: interfaces.php?if={$if}");
	exit;
} else

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	conf_mount_rw();
	/* filter out spaces from descriptions  */
	$_POST['descr'] = remove_bad_chars($_POST['descr']);
	/* okay first of all, cause we are just hiding the PPPoE HTML
	 * fields releated to PPPoE resets, we are going to unset $_POST
	 * vars, if the reset feature should not be used. Otherwise the
	 * data validation procedure below, may trigger a false error
	 * message.
	 */
	if (empty($_POST['pppoe_preset'])) {
		unset($_POST['pppoe_pr_type']);                
		unset($_POST['pppoe_resethour']);
		unset($_POST['pppoe_resetminute']);
		unset($_POST['pppoe_resetdate']);
		unset($_POST['pppoe_pr_preset_val']);
	}
	/* optional interface if list */
	$iflist = get_configured_interface_with_descr();
	/* description unique? */
	foreach ($iflist as $ifent => $ifdescr) {
		if ($if != $ifent && $ifdescr == $_POST['descr'])
			$input_errors[] = "An interface with the specified description already exists.";
	}
	/* input validation */
	if (isset($config['dhcpd']) && isset($config['dhcpd'][$if]['enable']) && $_POST['type'] != "static")
		$input_errors[] = "The DHCP Server is active on this interface and it can be used only with a static IP configuration. Please disable the DHCP Server service on this interface first, then change the interface configuration.";

	switch($_POST['type']) {
		case "static":
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = explode(",", "IP address,Subnet bit count,Gateway");
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "PPPoE":
			if ($_POST['pppoe_dialondemand']) {
				$reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
				$reqdfieldsn = explode(",", "PPPoE username,PPPoE password,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "pppoe_username pppoe_password");
				$reqdfieldsn = explode(",", "PPPoE username,PPPoE password");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "PPTP":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = explode(",", "PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address,Dial on demand,Idle timeout value");
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote");
				$reqdfieldsn = explode(",", "PPTP username,PPTP password,PPTP local IP address,PPTP subnet,PPTP remote IP address");
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) 
		$input_errors[] = "A valid IP address must be specified.";
	if (($_POST['subnet'] && !is_numeric($_POST['subnet']))) 
		$input_errors[] = "A valid subnet bit count must be specified.";
	if (($_POST['alias-address'] && !is_ipaddr($_POST['alias-address']))) 
		$input_errors[] = "A valid alias IP address must be specified.";
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet']))) 
		$input_errors[] = "A valid alias subnet bit count must be specified.";
	if ($_POST['gateway'] != "none") {
		$match = false;
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gateway'], $gateway)) {
				$match = true;
			}
		}
		if(!$match) {
			$input_errors[] = "A valid gateway must be specified.";
		}
	}
	if (($_POST['provider'] && !is_domain($_POST['provider']))) 
		$input_errors[] = "The service name contains invalid characters.";
	if (($_POST['pppoe_idletimeout'] != "") && !is_numericint($_POST['pppoe_idletimeout'])) 
		$input_errors[] = "The idle timeout value must be an integer.";
	if ($_POST['pppoe_resethour'] <> "" && !is_numericint($_POST['pppoe_resethour']) && 
		$_POST['pppoe_resethour'] >= 0 && $_POST['pppoe_resethour'] <=23) 
			$input_errors[] = gettext("A valid PPPoE reset hour must be specified (0-23).");
	if ($_POST['pppoe_resetminute'] <> "" && !is_numericint($_POST['pppoe_resetminute']) && 
		$_POST['pppoe_resetminute'] >= 0 && $_POST['pppoe_resetminute'] <=59) 
			$input_errors[] = gettext("A valid PPPoE reset minute must be specified (0-59).");
	if ($_POST['pppoe_resetdate'] <> "" && !is_numeric(str_replace("/", "", $_POST['pppoe_resetdate']))) 
		$input_errors[] = gettext("A valid PPPoE reset date must be specified (mm/dd/yyyy).");
	if (($_POST['pptp_local'] && !is_ipaddr($_POST['pptp_local']))) 
		$input_errors[] = "A valid PPTP local IP address must be specified.";
	if (($_POST['pptp_subnet'] && !is_numeric($_POST['pptp_subnet']))) 
		$input_errors[] = "A valid PPTP subnet bit count must be specified.";
	if (($_POST['pptp_remote'] && !is_ipaddr($_POST['pptp_remote']))) 
		$input_errors[] = "A valid PPTP remote IP address must be specified.";
	if (($_POST['pptp_idletimeout'] != "") && !is_numericint($_POST['pptp_idletimeout'])) 
		$input_errors[] = "The idle timeout value must be an integer.";
	if (($_POST['spoofmac'] && !is_macaddr($_POST['spoofmac']))) 
		$input_errors[] = "A valid MAC address must be specified.";
	if ($_POST['mtu'] && ($_POST['mtu'] < 576)) 
		$input_errors[] = "The MTU must be greater than 576 bytes.";
	/* Wireless interface? */
	if (isset($wancfg['wireless'])) {
		$reqdfields = explode(" ", "mode ssid");
		$reqdfieldsn = explode(",", "Mode,SSID");
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
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
				$input_errors[] =  "Invalid WEP key size.   Sizes should be 40 (64) bit keys or 104 (128) bit.";
				break;
			}
		}

		if ($_POST['passphrase']) {
                	$passlen = strlen($_POST['passphrase']);
                	if ($passlen < 8 || $passlen > 64)
                        	$input_errors[] = "The length of the passphrase should be between 8 and 63 characters.";
		}
	}
	if (!$input_errors) {
		if ($wancfg['ipaddr'] != "ppp")
			unset($wancfg['ipaddr']);
		unset($wancfg['subnet']);
		unset($wancfg['gateway']);
		unset($wancfg['dhcphostname']);
		unset($wancfg['pppoe_username']);
		unset($wancfg['pppoe_password']);
		unset($wancfg['pptp_username']);
		unset($wancfg['pptp_password']);
		unset($wancfg['provider']);
		unset($wancfg['ondemand']);
		unset($wancfg['timeout']);
		if ($wancfg['pppoe']['pppoe-reset-type'])
			unset($wancfg['pppoe']['pppoe-reset-type']);
		unset($wancfg['local']);
		unset($wancfg['subnet']);
		unset($wancfg['remote']);

		$wancfg['descr'] = remove_bad_chars($_POST['descr']);
		$wancfg['enable'] =  $_POST['enable']  == "yes" ? true : false;

		/* for dynamic interfaces we tack a gateway item onto the array to prevent system
		 * log messages from appearing. They can also manually add these items */
		/* 1st added gateway gets a default bit */
		if(!empty($a_gateways)) {
			$gateway_item = array();
			/* check for duplicates */
			$skip = false;
			foreach($a_gateways as $item) {
				if(($item['interface'] == "$if") && ($item['gateway'] == "dynamic")) {
					$skip = true;
				}
			}
			if($skip == false) {
				$gateway_item['gateway'] = "dynamic";
				$gateway_item['descr'] = "Interface {$if} dynamic gateway";
				$gateway_item['name'] = "GW_" . strtoupper($if);
				$gateway_item['interface'] = "{$if}";
			} else {
				unset($gateway_item);
			}
		}

		switch($_POST['type']) {
			case "static":
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
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "carpdev-dhcp":
				$wancfg['ipaddr'] = "carpdev-dhcp";
				$wancfg['dhcphostname'] = $_POST['dhcphostname'];
				$wancfg['alias-address'] = $_POST['alias-address'];
				$wancfg['alias-subnet'] = $_POST['alias-subnet'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "pppoe":
				$wancfg['ipaddr'] = "pppoe";
				$wancfg['pppoe_username'] = $_POST['pppoe_username'];
				$wancfg['pppoe_password'] = $_POST['pppoe_password'];
				$wancfg['provider'] = $_POST['provider'];
				$wancfg['ondemand'] = $_POST['pppoe_dialondemand'] ? true : false;
				$wancfg['timeout'] = $_POST['pppoe_idletimeout'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
			case "pptp":
				$wancfg['ipaddr'] = "pptp";
				$wancfg['pptp_username'] = $_POST['pptp_username'];
				$wancfg['pptp_password'] = $_POST['pptp_password'];
				$wancfg['local'] = $_POST['pptp_local'];
				$wancfg['subnet'] = $_POST['pptp_subnet'];
				$wancfg['remote'] = $_POST['pptp_remote'];
				$wancfg['ondemand'] = $_POST['pptp_dialondemand'] ? true : false;
				$wancfg['timeout'] = $_POST['pptp_idletimeout'];
				if($gateway_item) {
					$a_gateways[] = $gateway_item;
				}
				break;
		}
		handle_pppoe_reset();
		/* reset cron items if necessary */
		if (empty($_POST['pppoe_preset'])) {
			/* test whether a cron item exists and unset() it if necessary */
			$itemhash = getMPDCRONSettings();
			$item = $itemhash['ITEM'];
			if (isset($item))
				unset($config['cron']['item'][$itemhash['ID']]); 
		}
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
		if (isset($wancfg['wireless'])) {
			handle_wireless_post();
		}
		write_config();
		mark_subsystem_dirty('interfaces');
		/* regenerate cron settings/crontab file */
		configure_cron();
		conf_mount_ro();
		header("Location: interfaces.php?if={$if}");
		exit;
	}
} // end if($_POST) 

function handle_pppoe_reset() {
	global $_POST, $config, $g, $wancfg, $if;
	/* perform a periodic reset? */
	if(!isset($_POST['pppoe_preset'])) {
		setup_pppoe_reset_file($if, false);		
		return;
	}
	if (!is_array($config['cron']['item'])) 
		$config['cron']['item'] = array(); 
	$itemhash = getMPDCRONSettings();
	$item = $itemhash['ITEM'];
	if (empty($item)) 
		$item = array();
	if (isset($_POST['pppoe_pr_type']) && $_POST['pppoe_pr_type'] == "custom") {
		$wancfg['pppoe']['pppoe-reset-type'] = "custom";
		$pconfig['pppoe_pr_custom'] = true;
		$item['minute'] = $_POST['pppoe_resetminute'];
		$item['hour'] = $_POST['pppoe_resethour'];
		if (isset($_POST['pppoe_resetdate']) && $_POST['pppoe_resetdate'] <> "" && strlen($_POST['pppoe_resetdate']) == 10) {
			$date = explode("/", $_POST['pppoe_resetdate']);
			$item['mday'] = $date[1];
			$item['month'] = $date[0];
		} else {
			$item['mday'] = "*";
			$item['month'] = "*";
		}
		$item['wday'] = "*";
		$item['who'] = "root";
		$item['command'] = CRON_PPPOE_CMD_FILE;
	} else if (isset($_POST['pppoe_pr_type']) && $_POST['pppoe_pr_type'] = "preset") {
		$wancfg['pppoe']['pppoe-reset-type'] = "preset";
		$pconfig['pppoe_pr_preset'] = true;
		switch ($_POST['pppoe_pr_preset_val']) {
			case "monthly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "1";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
	        	case "weekly":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "0";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
			case "daily":
				$item['minute'] = "0";
				$item['hour'] = "0";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
			case "hourly":
				$item['minute'] = "0";
				$item['hour'] = "*";
				$item['mday'] = "*";
				$item['month'] = "*";
				$item['wday'] = "*";
				$item['who'] = "root";
				$item['command'] = CRON_PPPOE_CMD_FILE;
				break;
		} // end switch
	} // end if
	if (isset($itemhash['ID'])) 
		$config['cron']['item'][$itemhash['ID']] = $item;
	else 
		$config['cron']['item'][] = $item;
	/* finally install the pppoerestart file */
	if (isset($_POST['pppoe_preset'])) {
		setup_pppoe_reset_file($if, true);
		$wancfg['pppoe_reset'] = true;
		$wancfg['pppoe_preset'] = true;
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
	} else {
		unset($wancfg['pppoe_reset']);
		unset($wancfg['pppoe_preset']);		
		setup_pppoe_reset_file($if, false);	
	}
}

function handle_wireless_post() {
	global $_POST, $config, $g, $wancfg, $if, $wl_countries_attr;
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
	if ($_POST['hidessid_enable'] == "yes")
		$wancfg['wireless']['hidessid']['enable'] = true;
	else if (isset($wancfg['wireless']['hidessid']['enable']))
		unset($wancfg['wireless']['hidessid']['enable']);
	if ($_POST['mac_acl_enable'] == "yes")
		$wancfg['wireless']['wpa']['mac_acl_enable'] = true;
	else if (isset($wancfg['wireless']['wpa']['mac_acl_enable']))
		unset($wancfg['wireless']['wpa']['mac_acl_enable']);
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
	if (is_array($config['wireless']['clone'])) {
		foreach ($config['wireless']['clone'] as $clone) {
			if ($clone['if'] == $wlanbaseif)
				$clone_count++;
		}
	}
	if ($clone_count > 1) {
		$old_wireless_mode = $wancfg['wireless']['mode'];
		$wancfg['wireless']['mode'] = $_POST['mode'];
		if (!interface_wireless_clone("{$wlanif}_", $wancfg)) {
			$input_errors[] = "Unable to change mode to {$wlan_modes[$wancfg['wireless']['mode']]}.  You may already have the maximum number of wireless clones supported in this mode.";
		} else {
			mwexec("/sbin/ifconfig {$wlanif}_ destroy");
		}
		$wancfg['wireless']['mode'] = $old_wireless_mode;
	}
}

$pgtitle = array("Interfaces", $pconfig['descr']);
$statusurl = "status_interfaces.php";

$closehead = false;
include("head.inc");
$types = array("none" => "None", "static" => "Static", "dhcp" => "DHCP", "pppoe" => "PPPoE", "pptp" => "PPTP" /* , "carpdev-dhcp" => "CarpDev"*/); 

?>

<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css"/>

<script type="text/javascript">
	function updateType(t){
		switch(t) {
	<?php
		/* OK, so this is sick using php to generate javascript, but it needed to be done */
		foreach ($types as $key => $val) {
			echo "case \"{$key}\": {\n";
			$t = $types;
			foreach ($t as $k => $v) {
				if ($k != $key) {
					echo "$('{$k}').hide();\n";
				}
			}
			echo "}\n";
		}
	?>
		}
		$(t).show();
	}

	function show_allcfg(obj) {
		if (obj.checked)
			$('allcfg').show();
		else
			$('allcfg').hide();
	}
	
	function show_periodic_reset(obj) {
		if (obj.checked)
			$('presetwrap').show();
		else
			$('presetwrap').hide();
	}

	function show_mon_config() {
		document.getElementById("showmonbox").innerHTML='';
		aodiv = document.getElementById('showmon');
		aodiv.style.display = "block";
	}

	function openwindow(url) {
		var oWin = window.open(url,"pfSensePop","width=620,height=400,top=150,left=150");
		if (oWin==null || typeof(oWin)=="undefined") 
			return false;
		else 
			return true;
	}
</script>
</head>
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php include("fbegin.inc"); ?>
	<form action="interfaces.php" method="post" name="iform" id="iform">
		<?php if ($input_errors) print_input_errors($input_errors); ?>
		<?php if (is_subsystem_dirty('interfaces')): ?><p>
		<?php print_info_box_np(gettext("The {$wancfg['descr']} configuration has been changed.<p>You must apply the changes in order for them to take effect.<p>Don't forget to adjust the DHCP Server range if needed after applying."));?><br />
		<?php endif; ?>
		<?php if ($savemsg) print_info_box($savemsg); ?>
		<table width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td id="mainarea">
					<div class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td colspan="2" valign="top" class="listtopic">General configuration</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell">Enable</td>
							<td width="78%" class="vtable">
								<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable'] == true) echo "checked"; ?> onClick="show_allcfg(this);">
							<strong>Enable Interface</strong>
							</td>
						</tr>
					</table>
					<div style="display:none;" name="allcfg" id="allcfg">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="22%" valign="top" class="vncell">Description</td>
							<td width="78%" class="vtable">
								<input name="descr" type="text" class="formfld unknown" id="descr" size="30" value="<?=htmlspecialchars($pconfig['descr']);?>">
								<br><span class="vexpl">Enter a description (name) for the interface here.</span>
							</td>
						</tr>
						<? if(!$wancfg['serialport']): ?>
						<tr>
							<td valign="middle" class="vncell"><strong>Type</strong></td>
							<td class="vtable"> 
								<select name="type" onChange="updateType(this.value);" class="formselect" id="type">
								<?php 
									foreach ($types as $key => $opt) { 
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
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell">MAC address</td>
							<td class="vtable">
								<input name="spoofmac" type="text" class="formfld unknown" id="spoofmac" size="30" value="<?=htmlspecialchars($pconfig['spoofmac']);?>">
								<?php
									$ip = getenv('REMOTE_ADDR');
									$mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
									$mac = str_replace("\n","",$mac);
									if($mac):
								?>
									<a OnClick="document.forms[0].spoofmac.value='<?=$mac?>';" href="#">Insert my local MAC address</a>
								<?php endif; ?>
								<br>
								This field can be used to modify (&quot;spoof&quot;) the MAC
								address of the WAN interface<br>
								(may be required with some cable connections)<br>
								Enter a MAC address in the following format: xx:xx:xx:xx:xx:xx
								or leave blank
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">MTU</td>
							<td class="vtable"> 
								<input name="mtu" type="text" class="formfld unknown" id="mtu" size="8" value="<?=htmlspecialchars($pconfig['mtu']);?>">
								<br>
								If you enter a value in this field, then MSS clamping for
								TCP connections to the value entered above minus 40 (TCP/IP
								header size) will be in effect. If you leave this field blank,
								an MTU of 1500 bytes will be assumed.
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>			
						<tr style="display:none;" name="none" id="none">
						</tr>
						<tr style="display:none;" name="static" id="static">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic">Static IP configuration</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq">IP address</td>
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
										<td width="22%" valign="top" class="vncellreq">Gateway</td>
										<td width="78%" class="vtable">
											<select name="gateway" class="formselect" id="gateway">
												<option value="none" selected>None</option>
													<?php
													if(count($a_gateways) > 0) {
														foreach ($a_gateways as $gateway) {
															if($gateway['interface'] == $if) {
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
											<br/>
											<div id='addgwbox'>
												If this interface is an Internet connection, select an existing Gateway from the list or <a OnClick="show_add_gateway();" href="#">add a new one</a>.
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
																	<td colspan="2"><center><b><font color="white">Add new gateway:</font></b></center></td>
																</tr>
																<tr><td>&nbsp;</td>
																<?php
																if($if == "wan" || $if == "WAN")
																	$checked = " CHECKED";
																?>
																<tr>
																	<td width="45%" align="right"><font color="white">Default  gateway:</td><td><input type="checkbox" id="defaultgw" name="defaultgw"<?=$checked?>></td>
																</tr>												
																<tr>
																	<td align="right"><font color="white">Gateway Name:</td><td><input id="name" name="name" value="<?=$wancfg['descr'] . "GW"?>"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white">Gateway IP:</td><td><input id="gatewayip" name="gatewayip"></td>
																</tr>
																<tr>
																	<td align="right"><font color="white">Description:</td><td><input id="gatewaydescr" name="gatewaydescr"></td>
																</tr>
																<tr><td>&nbsp;</td>
																<tr>
																	<td colspan="2">
																		<center>
																			<div id='savebuttondiv'>
																				<input type="hidden" name="addrtype" id="addrtype" value="IPv4" />
																				<input id="gwsave" type="Button" value="Save Gateway" onClick='hide_add_gatewaysave();'> 
																				<input id="gwcancel" type="Button" value="Cancel" onClick='hide_add_gateway();'>
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
										<td colspan="2" valign="top" class="listtopic">DHCP client configuration</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Hostname</td>
										<td width="78%" class="vtable">
											<input name="dhcphostname" type="text" class="formfld unknown" id="dhcphostname" size="40" value="<?=htmlspecialchars($pconfig['dhcphostname']);?>">
											<br>
											The value in this field is sent as the DHCP client identifier
											and hostname when requesting a DHCP lease. Some ISPs may require
											this (for client identification).
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Alias IP address</td>
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
											The value in this field is used as a fixed alias IP address by the
											DHCP client.
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr style="display:none;" name="pppoe" id="pppoe">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic">PPPoE configuration</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq">Username</td>
										<td width="78%" class="vtable">
												<input name="pppoe_username" type="text" class="formfld user" id="pppoe_username" size="20" value="<?=htmlspecialchars($pconfig['pppoe_username']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq">Password</td>
										<td width="78%" class="vtable">
											<input name="pppoe_password" type="password" class="formfld pwd" id="pppoe_password" size="20" value="<?=htmlspecialchars($pconfig['pppoe_password']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Service name</td>
										<td width="78%" class="vtable"><input name="provider" type="text" class="formfld unknown" id="provider" size="20" value="<?=htmlspecialchars($pconfig['provider']);?>">
											<br> <span class="vexpl">Hint: this field can usually be left empty</span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Dial on demand</td>
										<td width="78%" class="vtable">
											<input name="pppoe_dialondemand" type="checkbox" id="pppoe_dialondemand" value="enable" <?php if ($pconfig['pppoe_dialondemand']) echo "checked"; ?>>
											<strong>Enable Dial-On-Demand mode</strong><br>
											This option causes the interface to operate in dial-on-demand mode, allowing you to have a <i>virtual full time</i> connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Idle timeout</td>
										<td width="78%" class="vtable">
											<input name="pppoe_idletimeout" type="text" class="formfld unknown" id="pppoe_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pppoe_idletimeout']);?>"> seconds<br>If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature.
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Periodic reset");?></td>
										<td width="78%" class="vtable">
											<input name="pppoe_preset" type="checkbox" id="pppoe_preset" value="yes" <?php if ($pconfig['pppoe_preset']) echo "checked=\"checked\""; ?> onclick="show_periodic_reset(this);" />
												<?= gettext("enable periodic PPPoE resets"); ?>
												<br />
												<?php if ($pconfig['pppoe_preset']): ?>
													<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%">
													<?php else: ?>
														<table id="presetwrap" cellspacing="0" cellpadding="0" width="100%" style="display: none;">
														<?php endif; ?>
														<tr>
															<td align="left" valign="top">
																<p style="margin: 4px; padding: 4px 0 4px 0; width: 94%;">
																	<input name="pppoe_pr_type" type="radio" id="pppoe_pr_custom" value="custom" <?php if ($pconfig['pppoe_pr_custom']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoecustomwrap', { duration: 0.0 }); Effect.Fade('pppoepresetwrap', { duration: 0.0 }); }" /> 
																		<?= gettext("provide a custom reset time"); ?>
																		<br />
																		<input name="pppoe_pr_type" type="radio" id="pppoe_pr_preset" value="preset" <?php if ($pconfig['pppoe_pr_preset']) echo "checked=\"checked\""; ?> onclick="if (this.checked) { Effect.Appear('pppoepresetwrap', { duration: 0.0 }); Effect.Fade('pppoecustomwrap', { duration: 0.0 }); }" /> 
																			<?= gettext("select reset time from a preset"); ?>
																		</p>
																		<?php if ($pconfig['pppoe_pr_custom']): ?>
																			<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoecustomwrap">
																			<?php else: ?>
																				<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoecustomwrap">
																				<?php endif; ?>
																				<input type="text" name="pppoe_resethour" class="fd_incremental_inp_range_0_23 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resethour" value="<?= $pconfig['pppoe_resethour']; ?>" size="3" /> 
																				<?= gettext("hour (0-23)"); ?><br />
																				<input type="text" name="pppoe_resetminute" class="fd_incremental_inp_range_0_59 fd_increment_1 fd_classname_dec_buttonDec fd_classname_inc_buttonInc" maxlength="2" id="pppoe_resetminute" value="<?= $pconfig['pppoe_resetminute']; ?>" size="3" /> 
																				<?= gettext("minute (0-59)"); ?><br />
																				<input name="pppoe_resetdate" type="text" class="w8em format-m-d-y highlight-days-67" id="pppoe_resetdate" maxlength="10" size="10" value="<?=htmlspecialchars($pconfig['pppoe_resetdate']);?>" /> 
																				<?= gettext("reset at a specific date (mm/dd/yyyy)"); ?>
																				<br />&nbsp;<br />
																				<span class="red"><strong>Note: </strong></span>
																				If you leave the date field empty, the reset will be executed each day at the time you did specify using the minutes and hour field.
																			</p>
																			<?php if ($pconfig['pppoe_pr_preset']): ?>
																				<p style="margin: 2px; padding: 4px; width: 94%;" id="pppoepresetwrap">
																				<?php else: ?>
																					<p style="margin: 2px; padding: 4px; width: 94%; display: none;" id="pppoepresetwrap">
																					<?php endif; ?>
																					<input name="pppoe_pr_preset_val" type="radio" id="pppoe_monthly" value="monthly" <?php if ($pconfig['pppoe_monthly']) echo "checked=\"checked\""; ?> /> 
																					<?= gettext("reset at each month ('0 0 1 * *')"); ?>
																					<br />
																					<input name="pppoe_pr_preset_val" type="radio" id="pppoe_weekly" value="weekly" <?php if ($pconfig['pppoe_weekly']) echo "checked=\"checked\""; ?> /> 
																					<?= gettext("reset at each week ('0 0 * * 0')"); ?>
																					<br />
																					<input name="pppoe_pr_preset_val" type="radio" id="pppoe_daily" value="daily" <?php if ($pconfig['pppoe_daily']) echo "checked=\"checked\""; ?> /> 
																					<?= gettext("reset at each day ('0 0 * * *')"); ?>
																					<br />
																					<input name="pppoe_pr_preset_val" type="radio" id="pppoe_hourly" value="hourly" <?php if ($pconfig['pppoe_hourly']) echo "checked=\"checked\""; ?> /> 
																					<?= gettext("reset at each hour ('0 * * * *')"); ?>
																				</p>
																			</td>
																		</tr>
																	</table>
																</td>
															</tr>
														</table>
													</td>
												</tr>
						<tr style="display:none;" name="pptp" id="pptp">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic">PPTP configuration</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq">Username</td>
										<td width="78%" class="vtable">
											<input name="pptp_username" type="text" class="formfld user" id="pptp_username" size="20" value="<?=htmlspecialchars($pconfig['pptp_username']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq">Password</td>
										<td width="78%" class="vtable">
											<input name="pptp_password" type="text" class="formfld pwd" id="pptp_password" size="20" value="<?=htmlspecialchars($pconfig['pptp_password']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" width="100" valign="top" class="vncellreq">Local IP address</td>
										<td width="78%" class="vtable"> 
											<input name="pptp_local" type="text" class="formfld unknown" id="pptp_local" size="20"  value="<?=htmlspecialchars($pconfig['pptp_local']);?>">
											/
											<select name="pptp_subnet" class="formselect" id="pptp_subnet">
												<?php for ($i = 31; $i > 0; $i--): ?>
													<option value="<?=$i;?>" <?php if ($i == $pconfig['pptp_subnet']) echo "selected"; ?>>
														<?=$i;?>
													</option>
												<?php endfor; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td width="22%" width="100" valign="top" class="vncellreq">Remote IP address</td>
										<td width="78%" class="vtable">
											<input name="pptp_remote" type="text" class="formfld unknown" id="pptp_remote" size="20" value="<?=htmlspecialchars($pconfig['pptp_remote']);?>">
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Dial on demand</td>
										<td width="78%" class="vtable">
											<input name="pptp_dialondemand" type="checkbox" id="pptp_dialondemand" value="enable" <?php if ($pconfig['pptp_dialondemand']) echo "checked"; ?>>
											<strong>Enable Dial-On-Demand mode</strong><br>
											This option causes the interface to operate in dial-on-demand mode, allowing you to have a <i>virtual full time</i> connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected.
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell">Idle timeout</td>
										<td width="78%" class="vtable">
											<input name="pptp_idletimeout" type="text" class="formfld unknown" id="pptp_idletimeout" size="8" value="<?=htmlspecialchars($pconfig['pptp_idletimeout']);?>"> seconds<br>If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature.
										</td>
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
							<td colspan="2" valign="top" class="listtopic">Common wireless configuration - Settings apply to all wireless networks on <?=$wlanbaseif;?>.</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq">Standard</td>
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
							<td valign="top" class="vncellreq">802.11g OFDM Protection Mode</td>
							<td class="vtable">
								<select name="protmode" class="formselect" id="protmode">
									<option <? if ($pconfig['protmode'] == 'off') echo "selected";?> value="off">Protection mode off</option>
									<option <? if ($pconfig['protmode'] == 'cts') echo "selected";?> value="cts">Protection mode CTS to self</option>
									<option <? if ($pconfig['protmode'] == 'rtscts') echo "selected";?> value="rtscts">Protection mode RTS and CTS</option>
								</select>
								<br/>
								For IEEE 802.11g, use the specified technique for protecting OFDM frames in a mixed 11b/11g network.
								<br/>
							</td>
						</tr>
						<?php else: ?>
						<input name="protmode" type="hidden" id="protmode" value="off">
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncellreq">Transmit power</td>
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
								Note: Typically only a few discreet power settings are available and the driver will use the setting closest to the specified value.  Not all adaptors support changing the transmit power setting.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq">Channel</td>
							<td class="vtable">
								<select name="channel" class="formselect" id="channel">
									<option <? if ($pconfig['channel'] == 0) echo "selected"; ?> value="0">Auto</option>
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
								Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain)
								<br/>
								Note: Not all channels may be supported by your card.  Auto may override the wireless standard selected above.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Distance setting</td>
							<td class="vtable">
								<input name="distance" type="text" class="formfld unknown" id="distance" size="5" value="<?=htmlspecialchars($pconfig['distance']);?>">
								<br/>
								Note: This field can be used to tune ACK/CTS timers to fit the distance between AP and Client<br/>
								(measured in Meters and works only for Atheros based cards !)
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Regulatory settings</td>
							<td class="vtable">
								Regulatory domain<br/>
								<select name="regdomain" class="formselect" id="regdomain">
									<option <? if (empty($pconfig['regdomain'])) echo "selected"; ?> value="">Default</option>
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
								Note: Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work.
								<br/><br/>
								Country (listed with country code and regulatory domain)<br/>
								<select name="regcountry" class="formselect" id="regcountry">
									<option <? if (empty($pconfig['regcountry'])) echo "selected"; ?> value="">Default</option>
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
								Note: Any country setting other than "Default" will override the regulatory domain setting.
								<br/><br/>
								Location<br/>
								<select name="reglocation" class="formselect" id="reglocation">
									<option <? if (empty($pconfig['reglocation'])) echo "selected"; ?> value="">Default</option>
									<option <? if ($pconfig['reglocation'] == 'indoor') echo "selected"; ?> value="indoor">Indoor</option>
									<option <? if ($pconfig['reglocation'] == 'outdoor') echo "selected"; ?> value="outdoor">Outdoor</option>
									<option <? if ($pconfig['reglocation'] == 'anywhere') echo "selected"; ?> value="anywhere">Anywhere</option>
								</select>
								<br/><br/>
								These settings may affect which channels are available and the maximum transmit power allowed on those channels.  Using the correct settings to comply with local regulatory requirements is recommended.
								<br/>
								Note: All wireless networks on this interface will be temporarily brought down when changing regulatory settings.  Some of the regulatory domains or country codes may not be allowed by some cards.  These settings may not be able to add additional channels that are not already supported.
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>										
						<tr>
							<td colspan="2" valign="top" class="listtopic">Wireless configuration</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq">Mode</td>
							<td class="vtable">
								<select name="mode" class="formselect" id="mode">
									<option <? if ($pconfig['mode'] == 'bss') echo "selected";?> value="bss">Infrastructure (BSS)</option>
									<option <? if ($pconfig['mode'] == 'adhoc') echo "selected";?> value="adhoc">Ad-hoc (IBSS)</option>
									<option <? if ($pconfig['mode'] == 'hostap') echo "selected";?> value="hostap">Access Point</option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncellreq">SSID</td>
							<td class="vtable">
								<input name="ssid" type="text" class="formfld unknown" id="ssid" size="20" value="<?=htmlspecialchars($pconfig['ssid']); ?>">
							</td>
						</tr>
						<?php if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])): ?>
						<tr>
							<td valign="top" class="vncell">Minimum wireless standard</td>
							<td class="vtable">
								<select name="puremode" class="formselect" id="puremode">
									<option <? if ($pconfig['puremode'] == 'any') echo "selected";?> value="any">Any</option>
									<?php if (isset($wl_modes['11g'])): ?>
									<option <? if ($pconfig['puremode'] == '11g') echo "selected";?> value="11g">802.11g</option>
									<?php endif; ?>
									<option <? if ($pconfig['puremode'] == '11n') echo "selected";?> value="11n">802.11n</option>
								</select>
								<br/>
								When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate).
							</td>
						</tr>
						<?php elseif (isset($wl_modes['11g'])): ?>
						<tr>
							<td valign="top" class="vncell">802.11g only</td>
							<td class="vtable">
								<input name="puremode" type="checkbox" value="11g"  class="formfld" id="puremode" <? if ($pconfig['puremode'] == '11g') echo "checked";?>>
								<br/>When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate).
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td valign="top" class="vncell">Allow intra-BSS communication</td>
							<td class="vtable">
								<input name="apbridge_enable" type="checkbox" value="yes"  class="formfld" id="apbridge_enable" <? if ($pconfig['apbridge_enable']) echo "checked";?>>
								<br/>
								When operating as an access point, enable this if you want to pass packets between wireless clients directly.
								<br/>
								Disabling the internal bridging is useful when traffic is to be processed with packet filtering.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Enable WME</td>
							<td class="vtable">
								<input name="wme_enable" type="checkbox" class="formfld" id="wme_enable" value="yes" <? if ($pconfig['wme_enable']) echo "checked";?>>
								<br/>Setting this option will force the card to use WME (wireless QoS).
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Enable Hide SSID</td>
							<td class="vtable">
								<input name="hidessid_enable" type="checkbox" class="formfld" id="hidessid_enable" value="yes" <? if ($pconfig['hidessid_enable']) echo "checked";?>>
								<br/>
								Setting this option will force the card to NOT broadcast its SSID
								<br/>
								(this might create problems for some clients).
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">WEP</td>
							<td class="vtable"> 
								<input name="wep_enable" type="checkbox" id="wep_enable" value="yes" <? if ($pconfig['wep_enable']) echo "checked"; ?>>
								<strong>Enable WEP</strong>
								<table border="0" cellspacing="0" cellpadding="0">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;</td>
										<td>&nbsp;TX key&nbsp;</td>
									</tr>
									<tr>
										<td>Key 1:&nbsp;&nbsp;</td>
										<td>
											<input name="key1" type="text" class="formfld unknown" id="key1" size="30" value="<?=htmlspecialchars($pconfig['key1']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="1" <? if ($pconfig['txkey'] == 1) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td>Key 2:&nbsp;&nbsp;</td>
										<td>
											<input name="key2" type="text" class="formfld unknown" id="key2" size="30" value="<?=htmlspecialchars($pconfig['key2']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="2" <? if ($pconfig['txkey'] == 2) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td>Key 3:&nbsp;&nbsp;</td>
										<td>
											<input name="key3" type="text" class="formfld unknown" id="key3" size="30" value="<?=htmlspecialchars($pconfig['key3']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="3" <? if ($pconfig['txkey'] == 3) echo "checked";?>>
										</td>
									</tr>
									<tr>
										<td>Key 4:&nbsp;&nbsp;</td>
										<td>
											<input name="key4" type="text" class="formfld unknown" id="key4" size="30" value="<?=htmlspecialchars($pconfig['key4']);?>">
										</td>
										<td align="center">
											<input name="txkey" type="radio" value="4" <? if ($pconfig['txkey'] == 4) echo "checked";?>>
										</td>
									</tr>
								</table>
								<br/>
								40 (64) bit keys may be entered as 5 ASCII characters or 10 hex digits preceded by '0x'.<br/>
								104 (128) bit keys may be entered as 13 ASCII characters or 26 hex digits preceded by '0x'.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">WPA</td>
							<td class="vtable">
								<input name="wpa_enable" type="checkbox" class="formfld" id="wpa_enable" value="yes" <? if ($pconfig['wpa_enable']) echo "checked"; ?>>
								<strong>Enable WPA</strong>
								<br/><br/>
								<table border="0" cellspacing="0" cellpadding="0">
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;WPA Pre Shared Key&nbsp;</td>
									</tr>
									<tr>
										<td>PSK:&nbsp;&nbsp;</td>
										<td>
											<input name="passphrase" type="text" class="formfld unknown" id="passphrase" size="66" value="<?=htmlspecialchars($pconfig['passphrase']);?>">
										</td>
									</tr>
								</table>
								<br/>Passphrase must be from 8 to 63 characters.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">WPA Mode</td>
							<td class="vtable">
								<select name="wpa_mode" class="formselect" id="wpa_mode">
									<option <? if ($pconfig['wpa_mode'] == '1') echo "selected";?> value="1">WPA</option>
									<option <? if ($pconfig['wpa_mode'] == '2') echo "selected";?> value="2">WPA2</option>
									<option <? if ($pconfig['wpa_mode'] == '3') echo "selected";?> value="3">Both</option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">WPA Key Management Mode</td>
							<td class="vtable"> 
								<select name="wpa_key_mgmt" class="formselect" id="wpa_key_mgmt">
									<option <? if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK') echo "selected";?> value="WPA-PSK">Pre Shared Key</option>
									<option <? if ($pconfig['wpa_key_mgmt'] == 'WPA-EAP') echo "selected";?> value="WPA-EAP">Extensible Authentication Protocol</option>
									<option <? if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK WPA-EAP') echo "selected";?> value="WPA-PSK WPA-EAP">Both</option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Authentication</td>
							<td class="vtable">
								<select name="auth_algs" class="formselect" id="auth_algs">
									<option <? if ($pconfig['auth_algs'] == '1') echo "selected";?> value="1">Open System Authentication</option>
									<option <? if ($pconfig['auth_algs'] == '2') echo "selected";?> value="2">Shared Key Authentication</option>
									<option <? if ($pconfig['auth_algs'] == '3') echo "selected";?> value="3">Both</option>
								</select>
								<br/>Note: Shared Key Authentication requires WEP.</br>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">WPA Pairwise</td>
							<td class="vtable">
								<select name="wpa_pairwise" class="formselect" id="wpa_pairwise">
									<option <? if ($pconfig['wpa_pairwise'] == 'CCMP TKIP') echo "selected";?> value="CCMP TKIP">Both</option>
									<option <? if ($pconfig['wpa_pairwise'] == 'CCMP') echo "selected";?> value="CCMP">AES (recommended)</option>
									<option <? if ($pconfig['wpa_pairwise'] == 'TKIP') echo "selected";?> value="TKIP">TKIP</option>
								</select>
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Key Rotation</td>
							<td class="vtable">
								<input name="wpa_group_rekey" type="text" class="formfld unknown" id="wpa_group_rekey" size="30" value="<? echo $pconfig['wpa_group_rekey'] ? $pconfig['wpa_group_rekey'] : "60";?>">
								<br/>Allowed values are 1-9999 but should not be longer than Master Key Regeneration time.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Master Key Regeneration</td>
							<td class="vtable">
								<input name="wpa_gmk_rekey" type="text" class="formfld" id="wpa_gmk_rekey" size="30" value="<? echo $pconfig['wpa_gmk_rekey'] ? $pconfig['wpa_gmk_rekey'] : "3600";?>">
								<br/>Allowed values are 1-9999 but should not be shorter than Key Rotation time.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Strict Key Regeneration</td>
							<td class="vtable">
								<input name="wpa_strict_rekey" type="checkbox" value="yes"  class="formfld" id="wpa_strict_rekey" <? if ($pconfig['wpa_strict_rekey']) echo "checked"; ?>>
								<br/>Setting this option will force the AP to rekey whenever a client disassociates.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">Enable IEEE802.1X Authentication</td>
							<td class="vtable">
								<input name="ieee8021x" type="checkbox" value="yes"  class="formfld" id="ieee8021x" <? if ($pconfig['ieee8021x']) echo "checked";?>>
								<br/>Setting this option will enable 802.1x authentication.
								<br/><span class="red"><strong>NOTE:</strong</span> this option requires checking the "Enable WPA box".
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">802.1X Authentication Server IP Address</td>
							<td class="vtable">
								<input name="auth_server_addr" id="auth_server_addr" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_addr']);?>">
								<br/>Enter the IP address of the 802.1X Authentication Server.  This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">802.1X Authentication Server Port</td>
							<td class="vtable">
								<input name="auth_server_port" id="auth_server_port" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_port']);?>">
								<br/>Leave blank for the default 1812 port.
							</td>
						</tr>
						<tr>
							<td valign="top" class="vncell">802.1X Authentication Server Shared Secret</td>
							<td class="vtable">
								<input name="auth_server_shared_secret" id="auth_server_shared_secret" type="text" class="formfld unknown" size="66" value="<?=htmlspecialchars($pconfig['auth_server_shared_secret']);?>">
								<br/>
							</td>
						</tr>
						<? endif; ?>
						<tr>
							<td colspan="2" valign="top" height="16"></td>
						</tr>
						<tr>
							<td colspan="2" valign="top" class="listtopic">Private networks</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell">&nbsp;</td>
							<td class="vtable">
								<a name="rfc1918"></a> 
								<input name="blockpriv" type="checkbox" id="blockpriv" value="yes" <?php if ($pconfig['blockpriv']) echo "checked"; ?>>
								<strong>Block private networks</strong><br>
								When set, this option blocks traffic from IP addresses that are reserved 
								for private  networks as per RFC 1918 (10/8, 172.16/12, 192.168/16) as 
								well as loopback addresses (127/8).&nbsp;&nbsp; You should generally
								leave this option turned on, unless your WAN network lies in such 
								a private address space, too. 
							</td>
						</tr>
						<tr>
							<td valign="middle" class="vncell">&nbsp;</td>
							<td class="vtable"> 
								<input name="blockbogons" type="checkbox" id="blockbogons" value="yes" <?php if ($pconfig['blockbogons']) echo "checked"; ?>>
								<strong>Block bogon networks</strong><br>
								When set, this option blocks traffic from IP addresses that are reserved 
								(but not RFC 1918) or not yet assigned by IANA.&nbsp;&nbsp;
								Bogons are prefixes that should never appear in the Internet routing table, 
								and obviously should not appear as the source address in any packets you receive.
							</td>
						</tr>
					</tr>
					</table>
					</div>
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td width="100" valign="top">
								&nbsp;
							</td>
							<td>
								<br/>
								<input id="save" name="Submit" type="submit" class="formbtn" value="Save"> 
								<input id="cancel" type="button" class="formbtn" value="Cancel" onclick="history.back()">
								<input name="if" type="hidden" id="if" value="<?=$if;?>">
							</td>
						</tr>
					</table>
				</td>
			</table>
		</div>
		</td></tr>
		</table>
	</form>
	<script type="text/javascript">
		var gatewayip;
		var name;
		function show_add_gateway() {
			document.getElementById("addgateway").style.display = '';
			document.getElementById("addgwbox").style.display = 'none';
			document.getElementById("gateway").style.display = 'none';
			document.getElementById("save").style.display = 'none';
			document.getElementById("cancel").style.display = 'none';
			document.getElementById("gwsave").style.display = '';
			document.getElementById("gwcancel").style.display = '';
			$('notebox').innerHTML="";
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
		function hide_add_gatewaysave() {
			document.getElementById("addgateway").style.display = 'none';
			$('status').innerHTML = '<img src="/themes/metallic/images/misc/loader.gif"> One moment please...';
			var iface = $F('if');
			name = $('name').getValue();
			var descr = $('gatewaydescr').getValue();
			gatewayip = $('gatewayip').getValue();
			addrtype = $('addrtype').getValue();
			var defaultgw = $('defaultgw').getValue();
			var url = "system_gateways_edit.php";
			var pars = 'isAjax=true&defaultgw=' + escape(defaultgw) + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip) + '&type=' + escape(addrtype);
			var myAjax = new Ajax.Request(
				url,
				{
					method: 'post',
					parameters: pars,
					onFailure: report_failure,
					onComplete: save_callback
				});	
		}
		function addOption(selectbox,text,value)
		{
			var optn = document.createElement("OPTION");
			optn.text = text;
			optn.value = value;
			selectbox.options.add(optn);
			selectbox.selectedIndex = (selectbox.options.length-1);
			$('notebox').innerHTML="<p/><strong>NOTE:</strong> You can manage Gateways <a target='_new' href='system_gateways.php'>here</a>.";
		}				
		function report_failure() {
			alert("Sorry, we could not create your gateway at this time.");
			hide_add_gateway();
		}
		function save_callback(transport) {
			var response = transport.responseText;
			if(response) {
				document.getElementById("addgateway").style.display = 'none';
				hide_add_gateway();
				$('status').innerHTML = '';
				addOption($('gateway'), name, name);
				// Auto submit form?
				//document.iform.submit();
				//$('status').innerHTML = '<img src="/themes/metallic/images/misc/loader.gif">';
			} else {
				report_failure();
			}
		}
		<?php
		if ($if == "wan" || $if == "lan")
			echo "\$('allcfg').show();";
		else
			echo "show_allcfg(document.iform.enable);";
		echo "updateType('{$pconfig['type']}')";
		?>
	</script>
	<?php include("fend.inc"); ?>
	</body>
</html>
