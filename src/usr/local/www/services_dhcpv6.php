<?php
/*
 * services_dhcpv6.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-services-dhcpv6server
##|*NAME=Services: DHCPv6 Server
##|*DESCR=Allow access to the 'Services: DHCPv6 Server' page.
##|*MATCH=services_dhcpv6.php*
##|-PRIV

require_once('guiconfig.inc');
require_once('filter.inc');
require_once('services_dhcp.inc');

if (!g_get('services_dhcp_server_enable')) {
	header("Location: /");
	exit;
}

$if = $_REQUEST['if'];
$iflist = get_configured_interface_with_descr();
$iflist = array_merge($iflist, get_configured_pppoe_server_interfaces());

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$ifaddr = config_get_path("interfaces/{$ifent}/ipaddrv6");

		if (!config_path_enabled("dhcpdv6/{$ifent}") &&
		    !(($ifaddr == 'track6') ||
		    (is_ipaddrv6($ifaddr) &&
		    !is_linklocal($ifaddr)))) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

$act = $_REQUEST['act'];

if (!empty(config_get_path("dhcpdv6/{$if}"))) {
	$pool = $_REQUEST['pool'];
	if (is_numeric($_POST['pool'])) {
		$pool = $_POST['pool'];
	}

	if (is_numeric($pool) && empty($if)) {
		header('Location: services_dhcpv6.php');
		exit;
	}

	config_init_path("dhcpdv6/{$if}/pool");

	if (is_numeric($pool) && config_get_path("dhcpdv6/{$if}/pool/{$pool}")) {
		$dhcpdconf = config_get_path("dhcpdv6/{$if}/pool/{$pool}");
	} elseif ($act === 'newpool') {
		$dhcpdconf = [];
	} else {
		$dhcpdconf = config_get_path("dhcpdv6/{$if}");
	}

	config_init_path("dhcpdv6/{$if}/staticmap");
}

if (is_array($dhcpdconf)) {
	if (!is_numeric($pool) && !($act === 'newpool')) {
		$pconfig['enable'] = isset($dhcpdconf['enable']);
	} else {
		$pconfig['descr'] = $dhcpdconf['descr'];
	}

	/* DHCPv6 */
	if (is_array($dhcpdconf['range'])) {
		$pconfig['range_from'] = $dhcpdconf['range']['from'];
		$pconfig['range_to'] = $dhcpdconf['range']['to'];
	}

	if (is_array($dhcpdconf['prefixrange'])) {
		$pconfig['prefixrange_from'] = $dhcpdconf['prefixrange']['from'];
		$pconfig['prefixrange_to'] = $dhcpdconf['prefixrange']['to'];
		$pconfig['prefixrange_length'] = $dhcpdconf['prefixrange']['prefixlength'];
	}

	$pconfig['deftime'] = $dhcpdconf['defaultleasetime'];
	$pconfig['maxtime'] = $dhcpdconf['maxleasetime'];
	$pconfig['domain'] = $dhcpdconf['domain'];
	$pconfig['domainsearchlist'] = $dhcpdconf['domainsearchlist'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $dhcpdconf['dnsserver'];
	$pconfig['dhcp6c-dns'] = ($dhcpdconf['dhcp6c-dns'] !== 'disabled' ? 'enabled' : 'disabled');
	if (isset($dhcpdconf['denyunknown'])) {
		$pconfig['denyunknown'] = empty($dhcpdconf['denyunknown']) ? "enabled" : $dhcpdconf['denyunknown'];
	} else {
		$pconfig['denyunknown'] = "disabled";
	}
	$pconfig['ddnsdomain'] = $dhcpdconf['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $dhcpdconf['ddnsdomainprimary'];
	$pconfig['ddnsdomainprimaryport'] = $dhcpdconf['ddnsdomainprimaryport'];
	$pconfig['ddnsdomainsecondary'] = $dhcpdconf['ddnsdomainsecondary'];
	$pconfig['ddnsdomainsecondaryport'] = $dhcpdconf['ddnsdomainsecondaryport'];
	$pconfig['ddnsdomainkeyname'] = $dhcpdconf['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $dhcpdconf['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $dhcpdconf['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($dhcpdconf['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($dhcpdconf['ddnsforcehostname']);
	$pconfig['ddnsclientupdates'] = $dhcpdconf['ddnsclientupdates'];
	$pconfig['ddnsreverse'] = isset($dhcpdconf['ddnsreverse']);
	list($pconfig['ntp1'], $pconfig['ntp2'], $pconfig['ntp3'], $pconfig['ntp4']) = $dhcpdconf['ntpserver'];
	$pconfig['tftp'] = $dhcpdconf['tftp'];
	$pconfig['ldap'] = $dhcpdconf['ldap'];
	$pconfig['netboot'] = isset($dhcpdconf['netboot']);
	$pconfig['bootfile_url'] = $dhcpdconf['bootfile_url'];
	$pconfig['netmask'] = $dhcpdconf['netmask'];
	$pconfig['numberoptions'] = $dhcpdconf['numberoptions'];
	$pconfig['dhcpv6leaseinlocaltime'] = $dhcpdconf['dhcpv6leaseinlocaltime'];
}

if (config_get_path("interfaces/{$if}/ipaddrv6") == 'track6') {
	$trackifname = config_get_path("interfaces/{$if}/track6-interface");
	$trackcfg = config_get_path("interfaces/{$trackifname}");
	$ifcfgsn = "64";
	$ifcfgip = '::';

	$str_help_mask = dhcpv6_pd_str_help($ifcfgsn);
} else {
	$ifcfgip = get_interface_ipv6($if);
	$ifcfgsn = get_interface_subnetv6($if);
}

/*	 set the enabled flag which will tell us if DHCP relay is enabled
 *	 on any interface. We will use this to disable DHCP server since
 *	 the two are not compatible with each other.
 */

$dhcrelay_enabled = false;
$dhcrelaycfg = config_get_path('dhcrelay6');

if (is_array($dhcrelaycfg) && isset($dhcrelaycfg['enable']) && isset($dhcrelaycfg['interface']) && !empty($dhcrelaycfg['interface'])) {
	$dhcrelayifs = explode(",", $dhcrelaycfg['interface']);

	foreach ($dhcrelayifs as $dhcrelayif) {

		if (isset($iflist[$dhcrelayif]) && (!link_interface_to_bridge($dhcrelayif))) {
			$dhcrelay_enabled = true;
			break;
		}
	}
}

if (isset($_POST['apply'])) {
	$changes_applied = true;
	$retval = dhcp6_apply_changes();
} elseif (isset($_POST['save'])) {

	unset($input_errors);

	$old_dhcpdv6_enable = ($pconfig['enable'] == true);
	$new_dhcpdv6_enable = ($_POST['enable'] ? true : false);
	$dhcpdv6_enable_changed = ($old_dhcpdv6_enable != $new_dhcpdv6_enable);

	$pconfig = $_POST;

	$numberoptions = array();
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["number{$x}"]) && ctype_digit(strval($_POST["number{$x}"]))) {
			$numbervalue = array();
			$numbervalue['number'] = htmlspecialchars($_POST["number{$x}"]);
			$numbervalue['value'] = base64_encode($_POST["value{$x}"]);
			$numberoptions['item'][] = $numbervalue;
		}
	}
	// Reload the new pconfig variable that the form uses.
	$pconfig['numberoptions'] = $numberoptions;

	/* input validation */

	// Note: if DHCPv6 Server is not enabled, then it is OK to adjust other parameters without specifying range from-to.
	if ($_POST['enable'] || is_numeric($pool) || ($act === 'newpool')) {
		if ((empty($_POST['range_from']) || empty($_POST['range_to'])) &&
		    (config_get_path("dhcpdv6/{$if}/ramode") != 'stateless_dhcp')) {
			$input_errors[] = gettext('A valid range must be specified for any Router Advertisement mode except "Stateless DHCP."');
		}
	}

	if (($_POST['prefixrange_from'] && !is_ipaddrv6($_POST['prefixrange_from']))) {
		$input_errors[] = gettext("A valid prefix range must be specified.");
	}
	if (($_POST['prefixrange_to'] && !is_ipaddrv6($_POST['prefixrange_to']))) {
		$input_errors[] = gettext("A valid prefix range must be specified.");
	}

	if ($_POST['prefixrange_from'] && $_POST['prefixrange_to'] &&
		$_POST['prefixrange_length']) {
		$netmask = Net_IPv6::getNetmask($_POST['prefixrange_from'],
			$_POST['prefixrange_length']);
		$netmask = text_to_compressed_ip6($netmask);

		if ($netmask != text_to_compressed_ip6(strtolower(
			$_POST['prefixrange_from']))) {
			$input_errors[] = sprintf(gettext(
				"Prefix Delegation From address is not a valid IPv6 Netmask for %s"),
				$netmask . '/' . $_POST['prefixrange_length']);
		}

		$netmask = Net_IPv6::getNetmask($_POST['prefixrange_to'],
			$_POST['prefixrange_length']);
		$netmask = text_to_compressed_ip6($netmask);

		if ($netmask != text_to_compressed_ip6(strtolower(
			$_POST['prefixrange_to']))) {
			$input_errors[] = sprintf(gettext(
				"Prefix Delegation To address is not a valid IPv6 Netmask for %s"),
				$netmask . '/' . $_POST['prefixrange_length']);
		}
	}

	$range_from_to_ok = true;

	if ($_POST['range_from']) {
		if (!is_ipaddrv6($_POST['range_from'])) {
			$input_errors[] = gettext("A valid range must be specified.");
			$range_from_to_ok = false;
		} elseif (config_get_path("interfaces/{$if}/ipaddrv6") == 'track6' &&
			!Net_IPv6::isInNetmask($_POST['range_from'], '::', $ifcfgsn)) {
			$input_errors[] = sprintf(gettext(
				'The prefix (upper %1$s bits) must be zero.  Use the form %2$s'),
				$ifcfgsn, $str_help_mask);
			$range_from_to_ok = false;
		}
	}
	if ($_POST['range_to']) {
		if (!is_ipaddrv6($_POST['range_to'])) {
			$input_errors[] = gettext("A valid range must be specified.");
			$range_from_to_ok = false;
		} elseif (config_get_path("interfaces/{$if}/ipaddrv6") == 'track6' &&
			!Net_IPv6::isInNetmask($_POST['range_to'], '::', $ifcfgsn)) {
			$input_errors[] = sprintf(gettext(
				'The prefix (upper %1$s bits) must be zero.  Use the form %2$s'),
				$ifcfgsn, $str_help_mask);
			$range_from_to_ok = false;
		}
	}
	if (($_POST['range_from'] && !$_POST['range_to']) || ($_POST['range_to'] && !$_POST['range_from'])) {
		$input_errors[] = gettext("Range From and Range To must both be entered.");
	}
	if (($_POST['gateway'] && !is_ipaddrv6($_POST['gateway']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for the gateway.");
	}
	if (($_POST['dns1'] && !is_ipaddrv6($_POST['dns1'])) ||
		($_POST['dns2'] && !is_ipaddrv6($_POST['dns2'])) ||
		($_POST['dns3'] && !is_ipaddrv6($_POST['dns3'])) ||
		($_POST['dns4'] && !is_ipaddrv6($_POST['dns4']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for each of the DNS servers.");
	}

	if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
		$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
	}
	if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] < $_POST['deftime']))) {
		$input_errors[] = gettext("The maximum lease time must be at least 60 seconds, and the same value or greater than the default lease time.");
	}
	if ($_POST['ddnsupdate']) {
		if (!is_domain($_POST['ddnsdomain'])) {
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		}
		if (!is_ipaddr($_POST['ddnsdomainprimary'])) {
			$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
		}
		if (!empty($_POST['ddnsdomainsecondary']) && !is_ipaddr($_POST['ddnsdomainsecondary'])) {
			$input_errors[] = gettext("A valid secondary domain name server IP address must be specified for the dynamic domain name.");
		}
		if (!$_POST['ddnsdomainkeyname'] || !$_POST['ddnsdomainkeyalgorithm'] || !$_POST['ddnsdomainkey']) {
			$input_errors[] = gettext("A valid domain key name, algorithm and secret must be specified.");
		}
		if (preg_match('/[^A-Za-z0-9\.\-\_]/', $_POST['ddnsdomainkeyname'])) {
			$input_errors[] = gettext("The domain key name may only contain the characters a-z, A-Z, 0-9, '-', '_' and '.'");
		}
		if ($_POST['ddnsdomainkey'] && !base64_decode($_POST['ddnsdomainkey'], true)) {
			$input_errors[] = gettext("The domain key secret must be a Base64 encoded value.");
		}
	}
	if ($_POST['domainsearchlist']) {
		$domain_array = preg_split("/[ ;]+/", $_POST['domainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
	}

	if (($_POST['ntp1'] && !is_ipaddrv6($_POST['ntp1'])) ||
	    ($_POST['ntp2'] && !is_ipaddrv6($_POST['ntp2'])) ||
	    ($_POST['ntp3'] && !is_ipaddrv6($_POST['ntp3'])) ||
	    ($_POST['ntp4'] && !is_ipaddrv6($_POST['ntp4']))) {
		$input_errors[] = gettext('A valid IPv6 address must be specified for the NTP servers.');
	}
	if (($_POST['domain'] && !is_domain($_POST['domain']))) {
		$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
	}
	if ($_POST['tftp'] && !is_ipaddr($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp'])) {
		$input_errors[] = gettext("A valid IPv6 address or hostname must be specified for the TFTP server.");
	}
	if (($_POST['bootfile_url'] && !is_URL($_POST['bootfile_url']))) {
		$input_errors[] = gettext("A valid URL must be specified for the network bootfile.");
	}

	// Disallow a range that includes the virtualip
	if ($range_from_to_ok) {
		foreach (config_get_path('virtualip/vip', []) as $vip) {
			if ($vip['interface'] == $if) {
				if ($vip['subnetv6'] && is_inrange_v6($vip['subnetv6'], $_POST['range_from'], $_POST['range_to'])) {
					$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IPv6 address %s."), $vip['subnetv6']);
				}
			}
		}
	}

	$noip = false;
	foreach (config_get_path("dhcpdv6/{$if}/staticmap", []) as $map) {
		if (empty($map['ipaddrv6'])) {
			$noip = true;
		}
	}

	/* make sure that the DHCP Relay isn't enabled on this interface */
	if ($_POST['enable'] && $dhcrelay_enabled) {
		$input_errors[] = sprintf(gettext("The DHCP relay on the %s interface must be disabled before enabling the DHCP server."), $iflist[$if]);
	}

	// If nothing is wrong so far, and we have range from and to, then check conditions related to the values of range from and to.
	if (!$input_errors && $_POST['range_from'] && $_POST['range_to']) {
		/* make sure the range lies within the current subnet */
		$subnet_start = gen_subnetv6($ifcfgip, $ifcfgsn);
		$subnet_end = gen_subnetv6_max($ifcfgip, $ifcfgsn);

		if (is_ipaddrv6($ifcfgip)) {
			if ((!is_inrange_v6($_POST['range_from'], $subnet_start, $subnet_end)) ||
				(!is_inrange_v6($_POST['range_to'], $subnet_start, $subnet_end))) {
				$input_errors[] = gettext("The specified range lies outside of the current subnet.");
			}
		}

		if (is_numeric($pool) || ($act === 'newpool')) {
			if (is_inrange_v6($_POST['range_from'],
				config_get_path("dhcpdv6/{$if}/range/from"),
				config_get_path("dhcpdv6/{$if}/range/to")) ||
				is_inrange_v6($_POST['range_to'],
				config_get_path("dhcpdv6/{$if}/range/from"),
				config_get_path("dhcpdv6/{$if}/range/to"))) {
				$input_errors[] = gettext('The specified range must not be within the primary DHCPv6 address pool for this interface.');
			}
		}

		foreach (config_get_path("dhcpdv6/{$if}/pool", []) as $id => $p) {
			if (is_numeric($pool) && ($id == $pool)) {
				continue;
			}

			if (is_inrange_v6($_POST['range_from'], $p['range']['from'], $p['range']['to']) ||
			    is_inrange_v6($_POST['range_to'], $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext('The specified range must not be within the range configured on another DHCPv6 pool for this interface.');
				break;
			}
		}


		/* "from" cannot be higher than "to" */
		if (inet_pton($_POST['range_from']) > inet_pton($_POST['range_to'])) {
			$input_errors[] = gettext("The range is invalid (first element higher than second element).");
		}

		/* Verify static mappings do not overlap:
		   - available DHCP range
		   - prefix delegation range (FIXME: still need to be completed) */
		$dynsubnet_start = inet_pton($_POST['range_from']);
		$dynsubnet_end = inet_pton($_POST['range_to']);

		foreach (config_get_path("dhcpdv6/{$if}/staticmap", []) as $map) {
			if (empty($map['ipaddrv6'])) {
				continue;
			}
			if ((inet_pton($map['ipaddrv6']) > $dynsubnet_start) &&
				(inet_pton($map['ipaddrv6']) < $dynsubnet_end)) {
				$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
				break;
			}
		}
	}

	if (!$input_errors) {
		if (!is_numeric($pool)) {
			if ($act === 'newpool') {
				$dhcpdconf = [];
			} else {
				config_init_path("dhcpdv6/{$if}");
				$dhcpdconf = config_get_path("dhcpdv6/{$if}");
			}
		} else {
			if (is_array(config_get_path("dhcpdv6/{$if}/pool/{$pool}"))) {
				$dhcpdconf = config_get_path("dhcpdv6/{$if}/pool/{$pool}");
			} else {
				header("Location: services_dhcpv6.php");
				exit;
			}
		}

		if (!is_array($dhcpdconf)) {
			$dhcpdconf = [];
		}

		if (!is_array($dhcpdconf['range'])) {
			$dhcpdconf['range'] = [];
		}
		if (!is_array($dhcpdconf['range'])) {
			$dhcpdconf['range'] = [];
		}

		// Global options
		if (!is_numeric($pool) && !($act === 'newpool')) {
			$dhcpdconf['enable'] = ($_POST['enable']) ? true : false;
		} else {
			// Options that exist only in pools
			$dhcpdconf['descr'] = $_POST['descr'];
		}


		if (in_array($_POST['denyunknown'], array("enabled", "class"))) {
			$dhcpdconf['denyunknown'] = $_POST['denyunknown'];
		} else {
			unset($dhcpdconf['denyunknown']);
		}

		$dhcpdconf['range']['from'] = $_POST['range_from'];
		$dhcpdconf['range']['to'] = $_POST['range_to'];
		$dhcpdconf['prefixrange']['from'] = $_POST['prefixrange_from'];
		$dhcpdconf['prefixrange']['to'] = $_POST['prefixrange_to'];
		$dhcpdconf['prefixrange']['prefixlength'] = $_POST['prefixrange_length'];
		$dhcpdconf['defaultleasetime'] = $_POST['deftime'];
		$dhcpdconf['maxleasetime'] = $_POST['maxtime'];
		$dhcpdconf['netmask'] = $_POST['netmask'];

		unset($dhcpdconf['dnsserver']);
		if ($_POST['dns1']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$dhcpdconf['dnsserver'][] = $_POST['dns4'];
		}
		$dhcpdconf['dhcp6c-dns'] = ($_POST['dhcp6c-dns']) ? 'enabled' : 'disabled';
		$dhcpdconf['domain'] = $_POST['domain'];
		$dhcpdconf['domainsearchlist'] = $_POST['domainsearchlist'];

		$dhcpdconf['ddnsdomain'] = $_POST['ddnsdomain'];
		$dhcpdconf['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$dhcpdconf['ddnsdomainsecondary'] = (!empty($_POST['ddnsdomainsecondary'])) ? $_POST['ddnsdomainsecondary'] : '';
		$dhcpdconf['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$dhcpdconf['ddnsdomainkeyalgorithm'] = $_POST['ddnsdomainkeyalgorithm'];
		$dhcpdconf['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$dhcpdconf['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$dhcpdconf['ddnsforcehostname'] = ($_POST['ddnsforcehostname']) ? true : false;
		$dhcpdconf['ddnsreverse'] = ($_POST['ddnsreverse']) ? true : false;
		$dhcpdconf['ddnsclientupdates'] = $_POST['ddnsclientupdates'];

		unset($dhcpdconf['ntpserver']);
		if ($_POST['ntp1']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp2'];
		}
		if ($_POST['ntp3']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp3'];
		}
		if ($_POST['ntp4']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp4'];
		}

		$dhcpdconf['tftp'] = $_POST['tftp'];
		$dhcpdconf['ldap'] = $_POST['ldap'];
		$dhcpdconf['netboot'] = ($_POST['netboot']) ? true : false;
		$dhcpdconf['bootfile_url'] = $_POST['bootfile_url'];
		$dhcpdconf['dhcpv6leaseinlocaltime'] = $_POST['dhcpv6leaseinlocaltime'];

		// Handle the custom options rowhelper
		if (isset($dhcpdconf['numberoptions']['item'])) {
			unset($dhcpdconf['numberoptions']['item']);
		}

		$dhcpdconf['numberoptions'] = $numberoptions;

		if (is_numeric($pool) && is_array(config_get_path("dhcpdv6/{$if}/pool/{$pool}"))) {
			config_set_path("dhcpdv6/{$if}/pool/{$pool}", $dhcpdconf);
		} elseif ($act === 'newpool') {
			config_set_path("dhcpdv6/{$if}/pool/", $dhcpdconf);
		} else {
			config_set_path("dhcpdv6/{$if}", $dhcpdconf);
		}

		mark_subsystem_dirty('dhcpd6');

		write_config("DHCPv6 Server settings saved");

		if (is_numeric($pool) || ($act === 'newpool')) {
			header('Location: /services_dhcpv6.php?if='.$if);
		}
	}
}

if ($act == "delpool") {
	if (config_get_path("dhcpdv6/{$if}/pool/{$_POST['id']}")) {
		config_del_path("dhcpdv6/{$if}/pool/{$_POST['id']}");
		write_config('DHCPv6 Server pool deleted');
		mark_subsystem_dirty('dhcpd6');
		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

if ($_POST['act'] == "del") {
	if (config_get_path("dhcpdv6/{$if}/staticmap/{$_POST['id']}")) {
		config_del_path("dhcpdv6/{$if}/staticmap/{$_POST['id']}");
		write_config("DHCPv6 server static map deleted");
		if (config_path_enabled("dhcpdv6/{$if}")) {
			mark_subsystem_dirty('dhcpd6');
			if (config_path_enabled('dnsmasq') && config_path_enabled('dnsmasq/regdhcpstaticv6', 'regdhcpstaticv6')) {
				mark_subsystem_dirty('hosts');
			}
		}
		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

// Build an HTML table that can be inserted into a Form_StaticText element
function build_pooltable() {
	global $if;

	$pooltbl =	'<div class="table-responsive">';
	$pooltbl .=		'<table class="table table-striped table-hover table-condensed">';
	$pooltbl .=			'<thead>';
	$pooltbl .=				'<tr>';
	$pooltbl .=					'<th>' . gettext("Pool Start") . '</th>';
	$pooltbl .=					'<th>' . gettext("Pool End") . '</th>';
	$pooltbl .=					'<th>' . gettext("Description") . '</th>';
	$pooltbl .=					'<th>' . gettext("Actions") . '</th>';
	$pooltbl .=				'</tr>';
	$pooltbl .=			'</thead>';
	$pooltbl .=			'<tbody>';

	$i = 0;
	foreach (config_get_path("dhcpdv6/{$if}/pool", []) as $poolent) {
		if (!empty($poolent['range']['from']) && !empty($poolent['range']['to'])) {
			$pooltbl .= '<tr>';
			$pooltbl .= '<td ondblclick="document.location=\'services_dhcpv6.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['range']['from']) . '</td>';

			$pooltbl .= '<td ondblclick="document.location=\'services_dhcpv6.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['range']['to']) . '</td>';

			$pooltbl .= '<td ondblclick="document.location=\'services_dhcpv6.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['descr']) . '</td>';

			$pooltbl .= '<td><a class="fa-solid fa-pencil" title="'. gettext("Edit pool") . '" href="services_dhcpv6.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '"></a>';

			$pooltbl .= ' <a class="fa-solid fa-trash-can" title="'. gettext("Delete pool") . '" href="services_dhcpv6.php?if=' . htmlspecialchars($if) . '&act=delpool&id=' . $i . '" usepost></a></td>';
			$pooltbl .= '</tr>';
		}
		$i++;
	}

	$pooltbl .=			'</tbody>';
	$pooltbl .=		'</table>';
	$pooltbl .= '</div>';

	return($pooltbl);
}

$pgtitle = [gettext('Services'), gettext('DHCPv6 Server')];
$pglinks = [null, 'services_dhcpv6.php'];

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = '/services_dhcpv6.php?if='.$if;

	if (is_numeric($pool) || ($act === 'newpool')) {
		$pgtitle[] = gettext('Address Pool');
		$pglinks[] = '@self';
		$pgtitle[] = gettext('Edit');
		$pglinks[] = '@self';
	}
}

$shortcut_section = "dhcp6";
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp6';
}

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('dhcpd6')) {
	print_apply_box(gettext('The DHCP Server configuration has been changed.') . "<br />" . gettext('The changes must be applied for them to take effect.'));
}

$is_stateless_dhcp = in_array(config_get_path('dhcpdv6/'.$if.'/ramode', 'disabled'), ['stateless_dhcp']);

$valid_ra = in_array(config_get_path('dhcpdv6/'.$if.'/ramode', 'disabled'), ['managed', 'assist', 'stateless_dhcp']);
if (config_path_enabled('dhcpdv6/'.$if) && !$valid_ra) {
	print_info_box(sprintf(gettext('DHCPv6 is enabled but not being advertised to clients on %1$s. Router Advertisement must be enabled and Router Mode set to "Managed", "Assisted" or "Stateless DHCP."'), $iflist[$if]), 'danger', false);
}

display_isc_warning();

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;

if (dhcp_is_backend('kea')) {
	$tab_array[] = [gettext('Settings'), false, 'services_dhcpv6_settings.php'];
}

foreach ($iflist as $ifent => $ifname) {
	config_init_path("dhcpdv6/{$ifent}");

	$oc = config_get_path("interfaces/{$ifent}");
	$valid_if_ipaddrv6 = (bool) ($oc['ipaddrv6'] == 'track6' ||
	    (is_ipaddrv6($oc['ipaddrv6']) &&
	    !is_linklocal($oc['ipaddrv6'])));

	if (!config_path_enabled("dhcpdv6/{$ifent}") && !$valid_if_ipaddrv6) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_dhcpv6.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	print_info_box(gettext("The DHCPv6 Server can only be enabled on interfaces configured with a static IPv6 address. This system has none."), 'danger');
	include("foot.inc");
	exit;
}

if ($dhcrelay_enabled) {
	print_info_box(gettext('DHCPv6 Relay is currently enabled. DHCPv6 Server canot be enabled while the DHCPv6 Relay is enabled on any interface.'), 'danger', false);
}

display_top_tabs($tab_array);

if (is_null($pconfig) || !is_array($pconfig)) {
	$pconfig = [];
}

$form = new Form();

$section = new Form_Section(gettext('General Settings'));

$section->addInput(new Form_StaticText(
	gettext('DHCP Backend'),
	match (dhcp_get_backend()) {
		'isc' => gettext('ISC DHCP'),
		'kea' => gettext('Kea DHCP'),
		default => gettext('Unknown')
	}
));

if (!is_numeric($pool) && !($act === 'newpool')) {
	if ($dhcrelay_enabled) {
		$section->addInput(new Form_Checkbox(
			'enable',
			gettext('Enable'),
			gettext("DHCPv6 Relay is currently enabled. DHCPv6 Server canot be enabled while the DHCPv6 Relay is enabled on any interface."),
			$pconfig['enable']
		))->setAttribute('disabled', true);
	} else {
		$section->addInput(new Form_Checkbox(
			'enable',
			gettext('Enable'),
			sprintf(gettext('Enable DHCPv6 server on %s interface'), $iflist[$if]),
			$pconfig['enable']
		));
	}
} else {
	print_info_box(gettext('Editing pool-specific options. To return to the Interface, click its tab above.'), 'info', false);
}

$section->addInput(new Form_Select(
	'denyunknown',
	gettext('Deny Unknown Clients'),
	$pconfig['denyunknown'],
	[
		'disabled' => gettext('Allow all clients'),
		'enabled' => gettext('Allow known clients from any interface'),
		'class' => gettext('Allow known clients from only this interface'),
	]
))->setHelp(gettext('When set to %3$sAllow all clients%4$s, any DHCP client will get an IP address within this scope/range on this interface. '.
	'If set to %3$sAllow known clients from any interface%4$s, any DHCP client with a DUID listed in a static mapping on %1$s%3$sany%4$s%2$s scope(s)/interface(s) will get an IP address. ' .
	'If set to %3$sAllow known clients from only this interface%4$s, only DUIDs listed in static mappings on this interface will get an IP address within this scope/range.'),
	'<i>', '</i>', '<b>', '</b>');

if (dhcp_is_backend('kea')):
if (is_numeric($pool) || ($act == "newpool")) {
	$section->addInput(new Form_Input(
		'descr',
		gettext('Description'),
		'text',
		$pconfig['descr']
	))->setHelp(gettext('Description for administrative reference (not parsed).'));
}
endif; /* dhcp_is_backend('kea') */

$form->add($section);

$pool_title = gettext('Primary Address Pool');
if (dhcp_is_backend('kea')):
if (is_numeric($pool) || ($act === 'newpool')) {
	$pool_title = gettext('Additional Address Pool');
}
endif;

$section = new Form_Section($pool_title);

if (is_ipaddrv6($ifcfgip)) {
	if ($ifcfgip == "::") {
		$sntext = gettext("Delegated Prefix") . ':';
		$sntext .= ' ' . convert_friendly_interface_to_friendly_descr(config_get_path("interfaces/{$if}/track6-interface"));
		$sntext .= '/' . config_get_path("interfaces/{$if}/track6-prefix-id");
		if (get_interface_track6ip($if)) {
			$track6ip = get_interface_track6ip($if);
			$pdsubnet = gen_subnetv6($track6ip[0], $track6ip[1]);
			$sntext .= " ({$pdsubnet}/{$track6ip[1]})";
		}
	} else {
		$sntext = gen_subnetv6($ifcfgip, $ifcfgsn);
	}
	$section->addInput(new Form_StaticText(
		gettext('Prefix'),
		$sntext . '/' . $ifcfgsn
		));

	$section->addInput(new Form_StaticText(
		gettext('Prefix Range'),
		$range_from = gen_subnetv6($ifcfgip, $ifcfgsn) . ' to ' . gen_subnetv6_max($ifcfgip, $ifcfgsn)
	))->setHelp($trackifname ? gettext('Prefix Delegation subnet will be appended to the beginning of the defined range'):'');

	if (is_numeric($pool) || ($act === 'newpool')) {
		$ranges = [];
		$subnet_range = config_get_path('dhcpdv6/'.$if.'/range', []);
		if (!empty($subnet_range)) {
			$subnet_range['descr'] = gettext('Primary Pool');
			$ranges[] = $subnet_range;
		}

		foreach (config_get_path("dhcpdv6/{$if}/pool", []) as $p) {
			$pa = array_get_path($p, 'range', []);
			if (!empty($pa)) {
				$pa['descr'] = trim($p['descr']);
				$ranges[] = $pa;
			}
		}

		$first = true;
		foreach ($ranges as $range) {
			$section->addInput(new Form_StaticText(
				($first ? ((count($ranges) > 1) ? gettext('In-use Ranges') : gettext('In-use Range')) : null),
				sprintf('%s - %s%s',
					array_get_path($range, 'from'),
					array_get_path($range, 'to'),
					!empty($range['descr']) ? ' ('.$range['descr'].')' : null
				)
			));
			$first = false;
		}
	}
}

$f1 = new Form_Input(
	'range_from',
	null,
	'text',
	$pconfig['range_from']
);

$f1->addClass('autotrim')
   ->setHelp(gettext('From'));

$f2 = new Form_Input(
	'range_to',
	null,
	'text',
	$pconfig['range_to']
);

$f2->addClass('autotrim')
   ->setHelp(gettext('To'));

/* address pool is optional when stateless */
$group = new Form_Group((!$is_stateless_dhcp ? '*' : '').gettext('Address Pool Range'));

$group->add($f1);
$group->add($f2);

$group->setHelp(gettext('The specified range for this pool must not be within the range configured on any other address pool for this interface.'));

$section->add($group);

if (dhcp_is_backend('kea')):
if (!is_numeric($pool) && !($act === 'newpool')) {
	$has_pools = false;
	if (isset($if) && (count(config_get_path("dhcpdv6/{$if}/pool", [])) > 0)) {
		$section->addInput(new Form_StaticText(
			gettext('Additional Pools'),
			build_pooltable()
		));
		$has_pools = true;
	}

	$btnaddpool = new Form_Button(
		'btnaddpool',
		gettext('Add Address Pool'),
		'services_dhcpv6.php?if=' . $if . '&act=newpool',
		'fa-solid fa-plus'
	);
	$btnaddpool->addClass('btn-success');

	$section->addInput(new Form_StaticText(
		(!$has_pools ? gettext('Additional Pools') : null),
		$btnaddpool
	))->setHelp(gettext('If additional pools of addresses are needed inside of this prefix outside the above range, they may be specified here.'));
}
endif; /* dhcp_is_backend('kea') */

$form->add($section);

if (dhcp_is_backend('isc')):
if (!is_numeric($pool) && !($act === 'newpool')):
$section = new Form_Section(gettext('Prefix Delegation Pool'));

$f1 = new Form_Input(
	'prefixrange_from',
	null,
	'text',
	$pconfig['prefixrange_from']
);

$f1->addClass('trim')
   ->setHelp('From');

$f2 = new Form_Input(
	'prefixrange_to',
	null,
	'text',
	$pconfig['prefixrange_to']
);

$f2->addClass('trim')
   ->setHelp('To');

$group = new Form_Group(gettext('Prefix Delegation Range'));

$group->add($f1);
$group->add($f2);

$section->add($group);

$section->addInput(new Form_Select(
	'prefixrange_length',
	'Prefix Delegation Size',
	$pconfig['prefixrange_length'],
	array(
		'48' => '48',
		'52' => '52',
		'56' => '56',
		'59' => '59',
		'60' => '60',
		'61' => '61',
		'62' => '62',
		'63' => '63',
		'64' => '64'
		)
))->setHelp(gettext('A prefix range can be defined here for DHCP Prefix Delegation. This allows for assigning networks to subrouters. The start and end of the range must end on boundaries of the prefix delegation size.'));

$form->add($section);
endif;
endif; /* dhcp_is_backend('isc') */

$section = new Form_Section(gettext('Server Options'));

if (!is_numeric($pool) && !($act === 'newpool')):
$section->addInput(new Form_Checkbox(
	'dhcp6c-dns',
	gettext('Enable DNS'),
	gettext('Provide DNS servers to DHCPv6 clients'),
	(($pconfig['dhcp6c-dns'] == 'enabled') || ($pconfig['dhcp6c-dns'] == 'yes'))
))->setHelp(gettext('Unchecking this box disables the dhcp6.name-servers option. ' .
	'Use with caution, as the resulting behavior may violate RFCs and lead to unintended client behavior.'));
endif;

$ifipv6 = get_interface_ipv6($if);

$dns_arrv6 = [];
foreach (config_get_path('system/dnsserver', []) as $dnsserver) {
	if (is_ipaddrv6($dnsserver)) {
		$dns_arrv6[] = $dnsserver;
	}
}

if (config_path_enabled('dnsmasq') ||
    config_path_enabled('unbound')) {
	$dns_arrv6 = [$ifipv6];
}

if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_dnsservers = config_get_path('dhcpdv6/'.$if.'/dnsserver', []);
	if (!empty($subnet_dnsservers)) {
		$dns_arrv6 = $subnet_dnsservers;
	}
}

for ($idx = 1; $idx <= 4; $idx++) {
	$last = $section->addInput(new Form_IpAddress(
		'dns' . $idx,
		(($idx === 1) ? gettext('DNS Servers') : null),
		$pconfig['dns' . $idx],
		'V6'
	))->addClass('autotrim')
	  ->setAttribute('placeholder', $dns_arrv6[$idx - 1] ?? sprintf('DNS Server %s', $idx));
}
$last->setHelp(gettext('Leave blank to use the IP address of this firewall interface if DNS Resolver or Forwarder is enabled, the servers configured in General settings or those obtained dynamically.'));

$form->add($section);

$section = new Form_Section(gettext('Other DHCPv6 Options'));

/* the system domain name has lowest priority */
$domain_holder = config_get_path('system/domain');

$section->addInput(new Form_Input(
	'domain',
	gettext('Domain Name'),
	'text',
	$pconfig['domain']
))->addClass('autotrim')
  ->setAttribute('placeholder', $domain_holder)
  ->setHelp(gettext('The default is to use the domain name of this firewall as the default domain name provided by DHCP. An alternate domain name may be specified here.'));

$section->addInput(new Form_Input(
	'domainsearchlist',
	gettext('Domain Search List'),
	'text',
	$pconfig['domainsearchlist']
))->addClass('autotrim')
  ->setAttribute('placeholder', 'example.com;sub.example.com')
  ->setHelp(gettext('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.'));

if (dhcp_is_backend('isc') ||
    (dhcp_is_backend('kea') && (!is_numeric($pool) && !($act === 'newpool')))):
$section->addInput(new Form_Input(
	'deftime',
	gettext('Default Lease Time'),
	'text',
	$pconfig['deftime']
))->setAttribute('placeholder', '7200')
  ->setHelp(gettext('This is used for clients that do not ask for a specific expiration time. The default is 7200 seconds.'));

$section->addInput(new Form_Input(
	'maxtime',
	gettext('Maximum Lease Time'),
	'text',
	$pconfig['maxtime']
))->setAttribute('placeholder', '86400')
  ->setHelp(gettext('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds.'));
endif;

if (dhcp_is_backend('isc')):
$section->addInput(new Form_Checkbox(
	'dhcpv6leaseinlocaltime',
	'Time Format Change',
	'Change DHCPv6 display lease time from UTC to local time',
	($pconfig['dhcpv6leaseinlocaltime'] == 'yes')
))->setHelp('By default DHCPv6 leases are displayed in UTC time. ' .
			'By checking this box DHCPv6 lease time will be displayed in local time and set to time zone selected. ' .
			'This will be used for all DHCPv6 interfaces lease time.');

$btnadv = new Form_Button(
	'btnadvdns',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('Dynamic DNS'),
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'ddnsupdate',
	'DHCP Registration',
	'Enable registration of DHCP client names in DNS.',
	$pconfig['ddnsupdate']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	'DDNS Domain',
	'text',
	$pconfig['ddnsdomain']
))->setHelp('Enter the dynamic DNS domain which will be used to register client names in the DNS server.');

$section->addInput(new Form_Checkbox(
	'ddnsforcehostname',
	'DDNS Hostnames',
	'Force dynamic DNS hostname to be the same as configured hostname for Static Mappings',
	$pconfig['ddnsforcehostname']
))->setHelp('Default registers host name option supplied by DHCP client.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	'Primary DDNS address',
	$pconfig['ddnsdomainprimary'],
	'BOTH'
))->setHelp('Enter the primary domain name server IP address for the dynamic domain name.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainsecondary',
	'Secondary DDNS address',
	$pconfig['ddnsdomainsecondary'],
	'BOTH'
))->setHelp('Enter the secondary domain name server IP address for the dynamic domain name.');

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	'DDNS Domain Key name',
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp('Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.');

$section->addInput(new Form_Select(
	'ddnsdomainkeyalgorithm',
	'Key algorithm',
	$pconfig['ddnsdomainkeyalgorithm'],
	array(
		'hmac-md5' => 'HMAC-MD5 (legacy default)',
		'hmac-sha1' => 'HMAC-SHA1',
		'hmac-sha224' => 'HMAC-SHA224',
		'hmac-sha256' => 'HMAC-SHA256 (current bind9 default)',
		'hmac-sha384' => 'HMAC-SHA384',
		'hmac-sha512' => 'HMAC-SHA512 (most secure)',
	)
));

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	'DDNS Domain Key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setAttribute('placeholder', 'Base64 encoded string')
->setHelp('Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.');

$section->addInput(new Form_Select(
	'ddnsclientupdates',
	'DDNS Client Updates',
	$pconfig['ddnsclientupdates'],
	array(
	    'allow' => gettext('Allow'),
	    'deny' => gettext('Deny'),
	    'ignore' => gettext('Ignore'))
))->setHelp('How Forward entries are handled when client indicates they wish to update DNS.  ' .
	    'Allow prevents DHCP from updating Forward entries, Deny indicates that DHCP will ' .
	    'do the updates and the client should not, Ignore specifies that DHCP will do the ' .
	    'update and the client can also attempt the update usually using a different domain name.');

$section->addInput(new Form_Checkbox(
	'ddnsreverse',
	'DDNS Reverse',
	'Add reverse dynamic DNS entries.',
	$pconfig['ddnsreverse']
));
endif; /* dhcp_is_backend('isc') */

$btnadv = new Form_Button(
	'btnadvntp',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('NTP'),
	$btnadv
));

$ntp_holder = [];
if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_ntp = config_get_path('dhcpd/'.$if.'/ntpserver', []);
	if (!empty($subnet_ntp)) {
		$ntp_holder = $subnet_ntp;
	}
}

$section->addInput(new Form_IpAddress(
	'ntp1',
	gettext('NTP Server 1'),
	$pconfig['ntp1'],
	'HOSTV6'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[0] ?? gettext('NTP Server 1'));

$section->addInput(new Form_IpAddress(
	'ntp2',
	gettext('NTP Server 2'),
	$pconfig['ntp2'],
	'HOSTV6'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[1] ?? gettext('NTP Server 2'));

$section->addInput(new Form_IpAddress(
	'ntp3',
	gettext('NTP Server 3'),
	$pconfig['ntp3'],
	'HOSTV6'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[2] ?? gettext('NTP Server 3'));

$section->addInput(new Form_IpAddress(
	'ntp4',
	gettext('NTP Server 4'),
	$pconfig['ntp4'],
	'HOSTV6'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[3] ?? gettext('NTP Server 4'));

if (dhcp_is_backend('isc')):
$btnadv = new Form_Button(
	'btnadvldap',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('LDAP'),
	$btnadv
));


$ldap_example = 'ldap://ldap.example.com/dc=example,dc=com';
$section->addInput(new Form_Input(
	'ldap',
	gettext('LDAP Server URI'),
	'text',
	$pconfig['ldap']
))->setAttribute('placeholder', sprintf(gettext('LDAP Server URI (e.g. %s)'), $ldap_example))
  ->setHelp(gettext('Leave blank to disable. Enter a full URI for the LDAP server in the form %s'), $ldap_example);
endif; /* dhcp_is_backend('isc') */

$btnadv = new Form_Button(
	'btnadvnetboot',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('Network Booting'),
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'netboot',
	gettext('Enable'),
	gettext('Enable Network Booting'),
	$pconfig['netboot']
));

$section->addInput(new Form_Input(
	'bootfile_url',
	gettext('Bootfile URL'),
	'text',
	$pconfig['bootfile_url']
));

if (dhcp_is_backend('isc')):
$btnadv = new Form_Button(
	'btnadvopts',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Additional BOOTP/DHCP Options',
	$btnadv
));

$form->add($section);

$title = 'Show Additional BOOTP/DHCP Options';

if (!$pconfig['numberoptions']) {
	$noopts = true;
	$pconfig['numberoptions'] = array();
	$pconfig['numberoptions']['item'] = array(0 => array('number' => "", 'value' => ""));
} else {
	$noopts = false;
}

$counter = 0;
if (!is_array($pconfig['numberoptions'])) {
	$pconfig['numberoptions'] = array();
}
if (!is_array($pconfig['numberoptions']['item'])) {
	$pconfig['numberoptions']['item'] = array();
}
$last = count($pconfig['numberoptions']['item']) - 1;

foreach ($pconfig['numberoptions']['item'] as $item) {
	$group = new Form_Group(null);
	$group->addClass('repeatable');
	$group->addClass('adnloptions');

	$group->add(new Form_Input(
		'number' . $counter,
		null,
		'text',
		$item['number']
	))->setHelp($counter == $last ? 'Number':null);

	$group->add(new Form_Input(
		'value' . $counter,
		null,
		'text',
		base64_decode($item['value'])
	))->setHelp($counter == $last ? 'Value':null);

	$btn = new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-solid fa-trash-can'
	);

	$btn->addClass('btn-warning');
	$group->add($btn);
	$section->add($group);
	$counter++;
}


$btnaddopt = new Form_Button(
	'addrow',
	'Add Option',
	null,
	'fa-solid fa-plus'
);

$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');

$section->addInput($btnaddopt);
endif; /* dhcp_is_backend('isc') */

if (dhcp_is_backend('kea')):
$form->add($section);
endif; /* dhcp_is_backend('kea') */

if ($act === 'newpool') {
	$form->addGlobal(new Form_Input(
		'act',
		null,
		'hidden',
		'newpool'
	));
}

if (is_numeric($pool)) {
	$form->addGlobal(new Form_Input(
		'pool',
		null,
		'hidden',
		$pool
	));
}

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

print($form);

// DHCP Static Mappings table
if (!is_numeric($pool) && !($act === 'newpool')):
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('DHCPv6 Static Mappings');?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<thead>
				<tr>
					<th><?=gettext("DUID")?></th>
					<th><?=gettext("IPv6 address")?></th>
					<th><?=gettext("Hostname")?></th>
					<th><?=gettext("Description")?></th>
					<th><!-- Buttons --></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach (config_get_path("dhcpdv6/{$if}/staticmap", []) as $mapent):
	if ($mapent['duid'] != "" or $mapent['ipaddrv6'] != ""):
?>
			<tr>
				<td>
					<?=htmlspecialchars($mapent['duid'])?>
				</td>
				<td>
					<?=htmlspecialchars($mapent['ipaddrv6'])?>
				</td>
				<td>
					<?=htmlspecialchars($mapent['hostname'])?>
				</td>
				<td>
					<?=htmlspecialchars($mapent['descr'])?>
				</td>
				<td>
					<a class="fa-solid fa-pencil" title="<?=gettext('Edit static mapping')?>" href="services_dhcpv6_edit.php?if=<?=$if?>&amp;id=<?=$i?>"></a>
					<a class="fa-solid fa-trash-can text-danger" title="<?=gettext('Delete static mapping')?>" href="services_dhcpv6.php?if=<?=$if?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
				</td>
			</tr>
<?php
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcpv6_edit.php?if=<?=$if?>" class="btn btn-success"/>
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add Static Mapping')?>
	</a>
</nav>
<?php endif; ?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Show advanced DNS options ======================================================================================
	var showadvdns = false;

	function show_advdns(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (!$pconfig['ddnsupdate'] &&
			    !$pconfig['ddnsforcehostname'] &&
			    empty($pconfig['ddnsdomain']) &&
			    empty($pconfig['ddnsdomainprimary']) &&
			    empty($pconfig['ddnsdomainsecondary']) &&
			    empty($pconfig['ddnsdomainkeyname']) &&
			    (empty($pconfig['ddnsdomainkeyalgorithm'])  || ($pconfig['ddnsdomainkeyalgorithm'] == "hmac-md5")) &&
			    empty($pconfig['ddnsdomainkey']) &&
			    (empty($pconfig['ddnsclientupdates']) || ($pconfig['ddnsclientupdates'] == "allow")) &&
			    !$pconfig['ddnsreverse']) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvdns = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvdns = !showadvdns;
		}

		hideCheckbox('ddnsupdate', !showadvdns);
		hideInput('ddnsdomain', !showadvdns);
		hideCheckbox('ddnsforcehostname', !showadvdns);
		hideInput('ddnsdomainprimary', !showadvdns);
		hideInput('ddnsdomainsecondary', !showadvdns);
		hideInput('ddnsdomainkeyname', !showadvdns);
		hideInput('ddnsdomainkeyalgorithm', !showadvdns);
		hideInput('ddnsdomainkey', !showadvdns);
		hideInput('ddnsclientupdates', !showadvdns);
		hideCheckbox('ddnsreverse', !showadvdns);

		if (showadvdns) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvdns').children();
		$('#btnadvdns').text(text).prepend(children);
	}

	$('#btnadvdns').click(function(event) {
		show_advdns();
	});

	// Show advanced NTP options ======================================================================================
	var showadvntp = false;

	function show_advntp(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['ntp1']) && empty($pconfig['ntp2']) && empty($pconfig['ntp3'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvntp = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvntp = !showadvntp;
		}

		hideInput('ntp1', !showadvntp);
		hideInput('ntp2', !showadvntp);
		hideInput('ntp3', !showadvntp);
		hideInput('ntp4', !showadvntp);

		if (showadvntp) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvntp').children();
		$('#btnadvntp').text(text).prepend(children);
	}

	$('#btnadvntp').click(function(event) {
		show_advntp();
	});

	// Show advanced LDAP options ======================================================================================
	var showadvldap = false;

	function show_advldap(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['ldap'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvldap = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvldap = !showadvldap;
		}

		hideInput('ldap', !showadvldap);

		if (showadvldap) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvldap').children();
		$('#btnadvldap').text(text).prepend(children);
	}

	$('#btnadvldap').click(function(event) {
		show_advldap();
	});

	// Show advanced Netboot options ======================================================================================
	var showadvnetboot = false;

	function show_advnetboot(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (!$pconfig['netboot'] && empty($pconfig['bootfile_url'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvnetboot = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvnetboot = !showadvnetboot;
		}

		hideCheckbox('netboot', !showadvnetboot);
		hideInput('bootfile_url', !showadvnetboot);

		if (showadvnetboot) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvnetboot').children();
		$('#btnadvnetboot').text(text).prepend(children);
	}

	$('#btnadvnetboot').click(function(event) {
		show_advnetboot();
	});

	// Show advanced additional opts options ===========================================================================
	var showadvopts = false;

	function show_advopts(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['numberoptions']) ||
			    (empty($pconfig['numberoptions']['item'][0]['number']) && (empty($pconfig['numberoptions']['item'][0]['value'])))) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvopts = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvopts = !showadvopts;
		}

		hideClass('adnloptions', !showadvopts);
		hideInput('addrow', !showadvopts);

		if (showadvopts) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvopts').children();
		$('#btnadvopts').text(text).prepend(children);
	}

	$('#btnadvopts').click(function(event) {
		show_advopts();
		checkLastRow();
	});

	// On initial load
	show_advdns(true);
	show_advntp(true);
	show_advldap(true);
	show_advnetboot(true);
	show_advopts(true);
	if ($('#enable').prop('checked')) {
		hideClass('adnloptions', <?php echo json_encode($noopts); ?>);
		hideInput('addrow', <?php echo json_encode($noopts); ?>);
	} else {
		hideClass('adnloptions', true);
		hideInput('addrow', true);
	}
});
//]]>
</script>

<?php
include('foot.inc');
