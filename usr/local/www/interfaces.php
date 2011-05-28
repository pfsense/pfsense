<?php
/* $Id$ */
/*
	interfaces.php
	Copyright (C) 2004-2008 Scott Ullrich
	Copyright (C) 2006 Daniel S. Haischt.
	Copyright (C) 2008-2010 Ermal Lu�i
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
	if ($a_ppps[$pppid]['type'] == "ppp"){
		$pconfig['username'] = $a_ppps[$pppid]['username'];
		$pconfig['password'] = base64_decode($a_ppps[$pppid]['password']);

		$pconfig['phone'] = $a_ppps[$pppid]['phone'];
		$pconfig['apn'] = $a_ppps[$pppid]['apn'];
	}

	if ($a_ppps[$pppid]['type'] == "pppoe"){
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
			$cronitem = $itemhash['ITEM'];
			if (isset($cronitem)) {
				$resetTime = "{$cronitem['minute']} {$cronitem['hour']} {$cronitem['mday']} {$cronitem['month']} {$cronitem['wday']}";
			} else {
				$resetTime = NULL;
			}
			log_error("ResetTime:".$resetTime);
			if ($a_ppps[$pppid]['pppoe-reset-type'] == "custom") {
				$resetTime_a = split(" ", $resetTime);
				$pconfig['pppoe_pr_custom'] = true;
				$pconfig['pppoe_resetminute'] = $resetTime_a[0];
				$pconfig['pppoe_resethour'] = $resetTime_a[1];
				/*  just initialize $pconfig['pppoe_resetdate'] if the
				 *  coresponding item contains appropriate numeric values.
				 */
				if ($resetTime_a[2] <> "*" && $resetTime_a[3] <> "*")
					$pconfig['pppoe_resetdate'] = "{$resetTime_a[3]}/{$resetTime_a[2]}/" . date("Y");
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
	case "carpdev-dhcp":
		$pconfig['type'] = "carpdev-dhcp";
		$pconfig['ipaddr'] = "";
		break;
	case "pppoe":
	case "pptp":
	case "l2tp":
	case "ppp":
		$pconfig['type'] = $wancfg['ipaddr'];
		break;
	default:
		if(is_ipaddr($wancfg['ipaddr'])) {
			$pconfig['type'] = "static";
			$pconfig['ipaddr'] = $wancfg['ipaddr'];
			$pconfig['subnet'] = $wancfg['subnet'];
			$pconfig['gateway'] = $wancfg['gateway'];
		} else
			$pconfig['type'] = "none";
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

if ($_POST['apply']) {
	unset($input_errors);
	if (!is_subsystem_dirty('interfaces'))
		$intput_errors[] = gettext("You have already applied your settings!");
	else {
		unlink_if_exists("{$g['tmp_path']}/config.cache");
		clear_subsystem_dirty('interfaces');

		if (file_exists("{$g['tmp_path']}/.interfaces.apply")) {
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
			foreach ($toapplylist as $ifapply) {
				if (isset($config['interfaces'][$ifapply]['enable']))
					interface_reconfigure($ifapply, true);
				else
					interface_bring_down($ifapply);
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
	if (file_exists("{$g['tmp_path']}/.interfaces.apply"))
		$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
	else
		$toapplylist = array();
	$toapplylist[$if] = $if; 
	file_put_contents("{$g['tmp_path']}/.interfaces.apply", serialize($toapplylist));
	header("Location: interfaces.php?if={$if}");
	exit;
} else if ($_POST) {

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
	if (isset($config['dhcpd']) && isset($config['dhcpd'][$if]['enable']) && $_POST['type'] != "static")
		$input_errors[] = gettext("The DHCP Server is active on this interface and it can be used only with a static IP configuration. Please disable the DHCP Server service on this interface first, then change the interface configuration.");

	switch(strtolower($_POST['type'])) {
		case "static":
			$reqdfields = explode(" ", "ipaddr subnet gateway");
			$reqdfieldsn = array(gettext("IP address"),gettext("Subnet bit count"),gettext("Gateway"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "none":
			if(is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vip) {
					if ($vip['interface'] == $if)
						$input_errors[] = gettext("This interface is referenced by VIPs please delete those before setting the interface to 'none' configuration.");
				}
			}
		case "dhcp":
			break;
		case "ppp":
			$reqdfields = explode(" ", "port phone");
			$reqdfieldsn = array(gettext("Modem Port"),gettext("Phone Number"));
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "pppoe":
			if ($_POST['pppoe_dialondemand']) {
				$reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
				$reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pppoe_username pppoe_password");
				$reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "pptp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_local pptp_subnet pptp_remote");
				$reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
		case "l2tp":
			if ($_POST['pptp_dialondemand']) {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote pptp_dialondemand pptp_idletimeout");
				$reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
			} else {
				$reqdfields = explode(" ", "pptp_username pptp_password pptp_remote");
				$reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"));
			}
			do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
			break;
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['spoofmac'] = strtolower(str_replace("-", ":", $_POST['spoofmac']));
	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr'])))
		$input_errors[] = gettext("A valid IP address must be specified.");
	if (($_POST['subnet'] && !is_numeric($_POST['subnet'])))
		$input_errors[] = gettext("A valid subnet bit count must be specified.");
	if (($_POST['alias-address'] && !is_ipaddr($_POST['alias-address'])))
		$input_errors[] = gettext("A valid alias IP address must be specified.");
	if (($_POST['alias-subnet'] && !is_numeric($_POST['alias-subnet'])))
		$input_errors[] = gettext("A valid alias subnet bit count must be specified.");
	if ($_POST['gateway'] != "none") {
		$match = false;
		foreach($a_gateways as $gateway) {
			if(in_array($_POST['gateway'], $gateway)) {
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
		$reqdfields = explode(" ", "mode ssid");
		$reqdfieldsn = array(gettext("Mode"),gettext("SSID"));
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
			} else if ($wancfg['ipaddr'] == "dhcp") {
				$pid = find_dhclient_process($realif);
				if($pid)
					posix_kill($pid, SIGTERM);
			}
				
		}
		$ppp = array();
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
		if (isset($wancfg['pppoe']['pppoe-reset-type']))
			unset($wancfg['pppoe']['pppoe-reset-type']);
		unset($wancfg['local']);
		unset($wancfg['subnet']);
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
				$gateway_item['gateway'] = gettext("dynamic");
				$gateway_item['descr'] = gettext("Interface") . $if . gettext("dynamic gateway");
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
				$wancfg['dhcp_plus'] = $_POST['dhcp_plus'] == "yes" ? true : false;
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
					unset($a_ppps[$pppid]['provider']);
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

		if (file_exists("{$g['tmp_path']}/.interfaces.apply"))
			$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.interfaces.apply"));
		else
			$toapplylist = array();
		$toapplylist[$if] = $if; 
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
	if ($_POST['persistcommonwireless'] == "yes") {
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
$statusurl = "status_interfaces.php";

$closehead = false;
include("head.inc");
$types = array("none" => gettext("None"), "static" => gettext("Static"), "dhcp" => gettext("DHCP"), "ppp" => gettext("PPP"), "pppoe" => gettext("PPPoE"), "pptp" => gettext("PPTP"), "l2tp" => gettext("L2TP") /* , "carpdev-dhcp" => "CarpDev"*/);

?>

<script type="text/javascript" src="/javascript/numericupdown/js/numericupdown.js"></script>
<link href="/javascript/numericupdown/css/numericupdown.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/javascript/datepicker/js/datepicker.js"></script>
<link href="/javascript/datepicker/css/datepicker.css" rel="stylesheet" type="text/css"/>

<script type="text/javascript">
	function updateType(t) {
		switch(t) {
			case "none": {
				$('static','dhcp','pppoe','pptp', 'ppp').invoke('hide');
				break;
			}
			case "static": {
				$('none','dhcp','pppoe','pptp', 'ppp').invoke('hide');
				break;
			}
			case "dhcp": {
				$('none','static','pppoe','pptp', 'ppp').invoke('hide');
				break;
			}
			case "ppp": {
				$('none','static','dhcp','pptp', 'pppoe').invoke('hide');
				country_list();
				break;
			}
			case "pppoe": {
				$('none','static','dhcp','pptp', 'ppp').invoke('hide');
				break;
			}
			case "l2tp":
			case "pptp": {
				$('none','static','dhcp','pppoe', 'ppp').invoke('hide');
				$('pptp').show();
				break;
			}
		}
		if (t != "l2tp" && t != "pptp")
			$(t).show();
	}

	function show_allcfg(obj) {
		if (obj.checked)
			$('allcfg').show();
		else
			$('allcfg').hide();
	}

	function show_reset_settings(reset_type) {
		if (reset_type == 'preset') {
			Effect.Appear('pppoepresetwrap', { duration: 0.0 });
			Effect.Fade('pppoecustomwrap', { duration: 0.0 });
		}
		else if (reset_type == 'custom') {
			Effect.Appear('pppoecustomwrap', { duration: 0.0 });
			Effect.Fade('pppoepresetwrap', { duration: 0.0 });
		} else {
			Effect.Fade('pppoecustomwrap', { duration: 0.0 });
			Effect.Fade('pppoepresetwrap', { duration: 0.0 });
		}
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
	function country_list() {
		$('country').childElements().each(function(node) { node.remove(); });
		$('provider').childElements().each(function(node) { node.remove(); });
		$('providerplan').childElements().each(function(node) { node.remove(); });
		new Ajax.Request("getserviceproviders.php",{
			onSuccess: function(response) {
				var responseTextArr = response.responseText.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					var option = new Element('option');
					country = value.split(":");
					option.text = country[0];
					option.value = country[1];
					$('country').insert({ bottom : option });
				});
			}
		});
		$('trcountry').setStyle({display : "table-row"});
	}

	function providers_list() {
		$('provider').childElements().each(function(node) { node.remove(); });
		$('providerplan').childElements().each(function(node) { node.remove(); });
		new Ajax.Request("getserviceproviders.php",{
			parameters: {country : $F('country')},
			onSuccess: function(response) {
				var responseTextArr = response.responseText.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					var option = new Element('option');
					option.text = value;
					option.value = value;
					$('provider').insert({ bottom : option });
				});
			}
		});
		$('trprovider').setStyle({display : "table-row"});
		$('trproviderplan').setStyle({display : "none"});
	}

	function providerplan_list() {
		$('providerplan').childElements().each(function(node) { node.remove(); });
		$('providerplan').insert( new Element('option') );
		new Ajax.Request("getserviceproviders.php",{
			parameters: {country : $F('country'), provider : $F('provider')},
			onSuccess: function(response) {
				var responseTextArr = response.responseText.split("\n");
				responseTextArr.sort();
				responseTextArr.each( function(value) {
					if(value != "") {
						providerplan = value.split(":");

						var option = new Element('option');
						option.text = providerplan[0] + " - " + providerplan[1];
						option.value = providerplan[1];
						$('providerplan').insert({ bottom : option });
					}
				});
			}
		});
		$('trproviderplan').setStyle({display : "table-row"});
	}

	function prefill_provider() {
		new Ajax.Request("getserviceproviders.php",{
			parameters: {country : $F('country'), provider : $F('provider'), plan : $F('providerplan')},
			onSuccess: function(response) {
				var xmldoc = response.responseXML;
				var provider = xmldoc.getElementsByTagName('connection')[0];
				$('username').setValue('');
				$('password').setValue('');
				if(provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
					$('phone').setValue('#777');
					$('apn').setValue('');
				} else {
					$('phone').setValue('*99#');
					$('apn').setValue(provider.getElementsByTagName('apn')[0].firstChild.data);
				}
				$('username').setValue(provider.getElementsByTagName('username')[0].firstChild.data);
				$('password').setValue(provider.getElementsByTagName('password')[0].firstChild.data);
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
							<td valign="middle" class="vncell"><strong><?=gettext("Type"); ?></strong></td>
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
								"address of the WAN interface"); ?><br>
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
								"be used. This is typically 1500 bytes but can vary on some hardware."); ?>
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
								foreach($mediaopts_list as $mediaopt){
									if ($mediaopt != rtrim($mediaopt_from_config)){
										print "<option value=\"$mediaopt\">" . gettext("$mediaopt") . "</option>";
									} else {
										print "<option value=\"$mediaopt\" selected>" . gettext("$mediaopt") . "</option>";
									}
								}
								echo '</select><br>';
								echo gettext("Here you can explicitely set up speed and duplex mode for the interface.");
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
						<tr style="display:none;" name="static" id="static">
							<td colspan="2" style="padding:0px;">
								<table width="100%" border="0" cellpadding="6" cellspacing="0">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Static IP configuration"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IP address"); ?></td>
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
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Gateway"); ?></td>
										<td width="78%" class="vtable">
											<select name="gateway" class="formselect" id="gateway">
												<option value="none" selected><?=gettext("None"); ?></option>
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
											</select> -or- <a OnClick="show_add_gateway();" href="#"><?=gettext("add a new one."); ?></a>
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
																	<td align="right"><font color="white"><?=gettext("Gateway IP:"); ?></td><td><input id="gatewayip" name="gatewayip"></td>
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
																				<input type="hidden" name="addrtype" id="addrtype" value="IPv4" />
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
										<td width="22%" valign="top" class="vncell"><?=gettext("Alias IP address"); ?></td>
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
											<?=gettext("The value in this field is used as a fixed alias IP address by the " .
											"DHCP client."); ?>
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
										<td>&nbsp;<?=gettext("WPA Pre Shared Key"); ?>&nbsp;</td>
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
									<option <?php if ($pconfig['wpa_key_mgmt'] == 'WPA-PSK') echo "selected";?> value="WPA-PSK"><?=gettext("Pre Shared Key"); ?></option>
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
							<td valign="top" class="vncell">802.1X <?=gettext("Authentication Roaming Preauth"); ?></td>
							<td class="vtable">
								<input name="rsn_preauth" id="rsn_preauth" type="checkbox" class="formfld unknown" size="66" value="yes" <? if ($pconfig['rsn_preauth']) echo "checked"; ?>>
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
			$('notebox').innerHTML="<p/><strong><?=gettext("NOTE:"); ?></strong> <?=gettext("You can manage Gateways"); ?> <a target='_new' href='system_gateways.php'><?=gettext("here"); ?></a>.";
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
		function show_advanced_media() {
			document.getElementById("showadvmediabox").innerHTML='';
			aodiv = document.getElementById('showmediaadv');
			aodiv.style.display = "block";
		}
		<?php
		echo "show_allcfg(document.iform.enable);";
		echo "updateType('{$pconfig['type']}');\n";
		?>
	</script>
	<?php include("fend.inc"); ?>
	</body>
</html>
