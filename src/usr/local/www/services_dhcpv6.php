<?php
/*
 * services_dhcpv6.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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

require_once("guiconfig.inc");
require_once("filter.inc");

function dhcpv6_apply_changes($dhcpdv6_enable_changed) {
	global $config, $g;

	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
	/* Stop DHCPv6 so we can cleanup leases */
	killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid");
	// dhcp_clean_leases();
	/* dnsmasq_configure calls dhcpd_configure */
	/* no need to restart dhcpd twice */
	if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic']))	{
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('staticmaps');
		}
	} else if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
		$retvaldns |= services_unbound_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('unbound');
			clear_subsystem_dirty('staticmaps');
		}
	} else {
		$retvaldhcp |= services_dhcpd_configure();
		if ($retvaldhcp == 0) {
			clear_subsystem_dirty('staticmaps');
		}
	}
	/* BIND package - Bug #3710 */
	if (!function_exists('is_package_installed')) {
		require_once('pkg-utils.inc');
	}
	if (is_package_installed('pfSense-pkg-bind') && isset($config['installedpackages']['bind']['config'][0]['enable_bind'])) {
		$reloadbind = false;
		if (is_array($config['installedpackages']['bindzone'])) {
			$bindzone = $config['installedpackages']['bindzone']['config'];
		} else {
			$bindzone = array();
		}
		for ($x = 0; $x < sizeof($bindzone); $x++) {
			$zone = $bindzone[$x];
			if ($zone['regdhcpstatic'] == 'on') {
				$reloadbind = true;
				break;
			}
		}
		if ($reloadbind === true) {
			if (file_exists("/usr/local/pkg/bind.inc")) {
				require_once("/usr/local/pkg/bind.inc");
				bind_sync();
			}
		}
	}
	if ($dhcpdv6_enable_changed) {
		$retvalfc |= filter_configure();
	}
	if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
		$retval = 1;
	}
	return $retval;
}

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

$if = $_REQUEST['if'];
$iflist = get_configured_interface_with_descr();
$iflist = array_merge($iflist, get_configured_pppoe_server_interfaces());

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		$valid_if_ipaddrv6 = (bool) ($oc['ipaddrv6'] == 'track6' ||
		    (is_ipaddrv6($oc['ipaddrv6']) &&
		    !is_linklocal($oc['ipaddrv6'])));

		if ((!is_array($config['dhcpdv6'][$ifent]) ||
		    !isset($config['dhcpdv6'][$ifent]['enable'])) &&
		    !$valid_if_ipaddrv6) {
			continue;
		}
		$if = $ifent;
		break;
	}
}

if (is_array($config['dhcpdv6'][$if])) {
	/* DHCPv6 */
	if (is_array($config['dhcpdv6'][$if]['range'])) {
		$pconfig['range_from'] = $config['dhcpdv6'][$if]['range']['from'];
		$pconfig['range_to'] = $config['dhcpdv6'][$if]['range']['to'];
	}
	if (is_array($config['dhcpdv6'][$if]['prefixrange'])) {
		$pconfig['prefixrange_from'] = $config['dhcpdv6'][$if]['prefixrange']['from'];
		$pconfig['prefixrange_to'] = $config['dhcpdv6'][$if]['prefixrange']['to'];
		$pconfig['prefixrange_length'] = $config['dhcpdv6'][$if]['prefixrange']['prefixlength'];
	}
	$pconfig['deftime'] = $config['dhcpdv6'][$if]['defaultleasetime'];
	$pconfig['maxtime'] = $config['dhcpdv6'][$if]['maxleasetime'];
	$pconfig['domain'] = $config['dhcpdv6'][$if]['domain'];
	$pconfig['domainsearchlist'] = $config['dhcpdv6'][$if]['domainsearchlist'];
	list($pconfig['wins1'], $pconfig['wins2']) = $config['dhcpdv6'][$if]['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $config['dhcpdv6'][$if]['dnsserver'];
	$pconfig['dhcp6c-dns'] = ($config['dhcpdv6'][$if]['dhcp6c-dns'] != 'disabled') ? "enabled" : "disabled";
	$pconfig['enable'] = isset($config['dhcpdv6'][$if]['enable']);
	$pconfig['ddnsdomain'] = $config['dhcpdv6'][$if]['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $config['dhcpdv6'][$if]['ddnsdomainprimary'];
	$pconfig['ddnsdomainsecondary'] = $config['dhcpdv6'][$if]['ddnsdomainsecondary'];
	$pconfig['ddnsdomainkeyname'] = $config['dhcpdv6'][$if]['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $config['dhcpdv6'][$if]['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $config['dhcpdv6'][$if]['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($config['dhcpdv6'][$if]['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($config['dhcpdv6'][$if]['ddnsforcehostname']);
	$pconfig['ddnsreverse'] = isset($config['dhcpdv6'][$if]['ddnsreverse']);
	$pconfig['ddnsclientupdates'] = $config['dhcpdv6'][$if]['ddnsclientupdates'];
	list($pconfig['ntp1'], $pconfig['ntp2'], $pconfig['ntp3'] ) = $config['dhcpdv6'][$if]['ntpserver'];
	$pconfig['tftp'] = $config['dhcpdv6'][$if]['tftp'];
	$pconfig['ldap'] = $config['dhcpdv6'][$if]['ldap'];
	$pconfig['netboot'] = isset($config['dhcpdv6'][$if]['netboot']);
	$pconfig['bootfile_url'] = $config['dhcpdv6'][$if]['bootfile_url'];
	$pconfig['netmask'] = $config['dhcpdv6'][$if]['netmask'];
	$pconfig['numberoptions'] = $config['dhcpdv6'][$if]['numberoptions'];
	$pconfig['dhcpv6leaseinlocaltime'] = $config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'];
	if (!is_array($config['dhcpdv6'][$if]['staticmap'])) {
		$config['dhcpdv6'][$if]['staticmap'] = array();
	}
	init_config_arr(array('dhcpdv6', $if, 'staticmap'));
	$a_maps = &$config['dhcpdv6'][$if]['staticmap'];
}

if ($config['interfaces'][$if]['ipaddrv6'] == 'track6') {
	$trackifname = $config['interfaces'][$if]['track6-interface'];
	$trackcfg = $config['interfaces'][$trackifname];
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
$dhcrelaycfg = $config['dhcrelay6'];

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
	$retval = dhcpv6_apply_changes(false);
} elseif (isset($_POST['save'])) {
	unset($input_errors);

	$old_dhcpdv6_enable = ($pconfig['enable'] == true);
	$new_dhcpdv6_enable = ($_POST['enable'] ? true : false);
	$dhcpdv6_enable_changed = ($old_dhcpdv6_enable != $new_dhcpdv6_enable);

	$pconfig = $_POST;

	$numberoptions = array();
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
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
	if ($_POST['enable']) {
		if ((empty($_POST['range_from']) || empty($_POST['range_to'])) &&
		    ($config['dhcpdv6'][$if]['ramode'] != 'stateless_dhcp')) {
			$input_errors[] = gettext("A valid range must be specified for any mode except Stateless DHCP.");
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
		} elseif ($config['interfaces'][$if]['ipaddrv6'] == 'track6' &&
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
		} elseif ($config['interfaces'][$if]['ipaddrv6'] == 'track6' &&
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
	if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
		$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
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
	    ($_POST['ntp3'] && !is_ipaddrv6($_POST['ntp3']))) {
		$input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary NTP servers.");
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
	if ($range_from_to_ok && is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['interface'] == $if) {
				if ($vip['subnetv6'] && is_inrange_v6($vip['subnetv6'], $_POST['range_from'], $_POST['range_to'])) {
					$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IPv6 address %s."), $vip['subnetv6']);
				}
			}
		}
	}

	$noip = false;
	if (is_array($a_maps)) {
		foreach ($a_maps as $map) {
			if (empty($map['ipaddrv6'])) {
				$noip = true;
			}
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
		/* "from" cannot be higher than "to" */
		if (inet_pton($_POST['range_from']) > inet_pton($_POST['range_to'])) {
			$input_errors[] = gettext("The range is invalid (first element higher than second element).");
		}

		/* Verify static mappings do not overlap:
		   - available DHCP range
		   - prefix delegation range (FIXME: still need to be completed) */
		$dynsubnet_start = inet_pton($_POST['range_from']);
		$dynsubnet_end = inet_pton($_POST['range_to']);

		if (is_array($a_maps)) {
			foreach ($a_maps as $map) {
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
	}

	if (!$input_errors) {
		if (!is_array($config['dhcpdv6'])) {
			$config['dhcpdv6'] = array();
		}
		if (!is_array($config['dhcpdv6'][$if])) {
			$config['dhcpdv6'][$if] = array();
		}
		if (!is_array($config['dhcpdv6'][$if]['range'])) {
			$config['dhcpdv6'][$if]['range'] = array();
		}
		if (!is_array($config['dhcpdv6'][$if]['prefixrange'])) {
			$config['dhcpdv6'][$if]['prefixrange'] = array();
		}

		$config['dhcpdv6'][$if]['range']['from'] = $_POST['range_from'];
		$config['dhcpdv6'][$if]['range']['to'] = $_POST['range_to'];
		$config['dhcpdv6'][$if]['prefixrange']['from'] = $_POST['prefixrange_from'];
		$config['dhcpdv6'][$if]['prefixrange']['to'] = $_POST['prefixrange_to'];
		$config['dhcpdv6'][$if]['prefixrange']['prefixlength'] = $_POST['prefixrange_length'];
		$config['dhcpdv6'][$if]['defaultleasetime'] = $_POST['deftime'];
		$config['dhcpdv6'][$if]['maxleasetime'] = $_POST['maxtime'];
		$config['dhcpdv6'][$if]['netmask'] = $_POST['netmask'];

		unset($config['dhcpdv6'][$if]['winsserver']);

		unset($config['dhcpdv6'][$if]['dnsserver']);
		if ($_POST['dns1']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$config['dhcpdv6'][$if]['dnsserver'][] = $_POST['dns4'];
		}
		$config['dhcpdv6'][$if]['dhcp6c-dns'] = ($_POST['dhcp6c-dns']) ? "enabled" : "disabled";
		$config['dhcpdv6'][$if]['domain'] = $_POST['domain'];
		$config['dhcpdv6'][$if]['domainsearchlist'] = $_POST['domainsearchlist'];
		$config['dhcpdv6'][$if]['enable'] = ($_POST['enable']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsdomain'] = $_POST['ddnsdomain'];
		$config['dhcpdv6'][$if]['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$config['dhcpdv6'][$if]['ddnsdomainsecondary'] = (!empty($_POST['ddnsdomainsecondary'])) ? $_POST['ddnsdomainsecondary'] : ''; 
		$config['dhcpdv6'][$if]['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$config['dhcpdv6'][$if]['ddnsdomainkeyalgorithm'] = $_POST['ddnsdomainkeyalgorithm'];
		$config['dhcpdv6'][$if]['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$config['dhcpdv6'][$if]['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsforcehostname'] = ($_POST['ddnsforcehostname']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsreverse'] = ($_POST['ddnsreverse']) ? true : false;
		$config['dhcpdv6'][$if]['ddnsclientupdates'] = $_POST['ddnsclientupdates'];

		unset($config['dhcpdv6'][$if]['ntpserver']);
		if ($_POST['ntp1']) {
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp2'];
		}
		if ($_POST['ntp3']) {
			$config['dhcpdv6'][$if]['ntpserver'][] = $_POST['ntp3'];
		}

		$config['dhcpdv6'][$if]['tftp'] = $_POST['tftp'];
		$config['dhcpdv6'][$if]['ldap'] = $_POST['ldap'];
		$config['dhcpdv6'][$if]['netboot'] = ($_POST['netboot']) ? true : false;
		$config['dhcpdv6'][$if]['bootfile_url'] = $_POST['bootfile_url'];
		$config['dhcpdv6'][$if]['dhcpv6leaseinlocaltime'] = $_POST['dhcpv6leaseinlocaltime'];

		// Handle the custom options rowhelper
		if (isset($config['dhcpdv6'][$if]['numberoptions']['item'])) {
			unset($config['dhcpdv6'][$if]['numberoptions']['item']);
		}

		$config['dhcpdv6'][$if]['numberoptions'] = $numberoptions;

		write_config("DHCPv6 server settings saved");

		$changes_applied = true;
		$retval = dhcpv6_apply_changes($dhcpdv6_enable_changed);
	}
}

if ($_POST['act'] == "del") {
	if ($a_maps[$_POST['id']]) {
		unset($a_maps[$_POST['id']]);
		write_config("DHCPv6 server static map deleted");
		if (isset($config['dhcpdv6'][$if]['enable'])) {
			mark_subsystem_dirty('staticmapsv6');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstaticv6'])) {
				mark_subsystem_dirty('hosts');
			}
		}
		header("Location: services_dhcpv6.php?if={$if}");
		exit;
	}
}

$pgtitle = array(gettext("Services"), htmlspecialchars(gettext("DHCPv6 Server & RA")));
$pglinks = array("", "services_dhcpv6.php");

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = "@self";
	$pgtitle[] = gettext("DHCPv6 Server");
	$pglinks[] = "@self";
}
$shortcut_section = "dhcp6";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('staticmaps')) {
	print_apply_box(gettext('The static mapping configuration has been changed.') . '<br />' . gettext('The changes must be applied for them to take effect.'));
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;

foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];
	$valid_if_ipaddrv6 = (bool) ($oc['ipaddrv6'] == 'track6' ||
	    (is_ipaddrv6($oc['ipaddrv6']) &&
	    !is_linklocal($oc['ipaddrv6'])));

	if ((!is_array($config['dhcpdv6'][$ifent]) ||
	    !isset($config['dhcpdv6'][$ifent]['enable'])) &&
	    !$valid_if_ipaddrv6) {
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

display_top_tabs($tab_array);

$tab_array = array();
$tab_array[] = array(gettext("DHCPv6 Server"),		 true,	"services_dhcpv6.php?if={$if}");
$tab_array[] = array(gettext("Router Advertisements"), false, "services_router_advertisements.php?if={$if}");
display_top_tabs($tab_array, false, 'nav nav-tabs');

$form = new Form();

$section = new Form_Section('DHCPv6 Options');

if ($dhcrelay_enabled) {
	$section->addInput(new Form_Checkbox(
		'enable',
		'DHCPv6 Server',
		gettext("DHCPv6 Relay is currently enabled. DHCPv6 Server canot be enabled while the DHCPv6 Relay is enabled on any interface."),
		$pconfig['enable']
	))->setAttribute('disabled', true);
} else {
	$section->addInput(new Form_Checkbox(
		'enable',
		'DHCPv6 Server',
		'Enable DHCPv6 server on interface ' . $iflist[$if],
		$pconfig['enable']
	));
}

if (is_ipaddrv6($ifcfgip)) {

	if ($ifcfgip == "::") {
		$sntext = "Prefix Delegation";
		if (get_interface_track6ip($if)) {
			$track6ip = get_interface_track6ip($if);
			$pdsubnet = gen_subnetv6($track6ip[0], $track6ip[1]);
			$sntext .= " ({$pdsubnet}/{$track6ip[1]})";
		}
	} else {
		$sntext = gen_subnetv6($ifcfgip, $ifcfgsn);
	}
	$section->addInput(new Form_StaticText(
		'Subnet',
		$sntext
		));

	$section->addInput(new Form_StaticText(
		'Subnet Mask',
		$ifcfgsn . ' bits'
		));

	$section->addInput(new Form_StaticText(
		'Available Range',
		$range_from = gen_subnetv6($ifcfgip, $ifcfgsn) . ' to ' . gen_subnetv6_max($ifcfgip, $ifcfgsn)
		))->setHelp($trackifname ? 'Prefix Delegation subnet will be appended to the beginning of the defined range':'');
}

$f1 = new Form_Input(
	'range_from',
	null,
	'text',
	$pconfig['range_from']
);

$f1->setHelp('From');

$f2 = new Form_Input(
	'range_to',
	null,
	'text',
	$pconfig['range_to']
);

$f2->setHelp('To');

$group = new Form_Group('*Range');

$group->add($f1);
$group->add($f2);

$section->add($group);

$f1 = new Form_Input(
	'prefixrange_from',
	null,
	'text',
	$pconfig['prefixrange_from']
);

$f1->setHelp('From');

$f2 = new Form_Input(
	'prefixrange_to',
	null,
	'text',
	$pconfig['prefixrange_to']
);

$f2->setHelp('To');

$group = new Form_Group('Prefix Delegation Range');

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
))->setHelp('A Prefix range can be defined here for DHCP Prefix Delegation. This allows for assigning networks to subrouters. The start and end of the range must end on boundaries of the prefix delegation size.');

$group = new Form_Group('DNS Servers');

for ($i=1;$i<=4; $i++) {
	$group->add(new Form_input(
		'dns' . $i,
		null,
		'text',
		$pconfig['dns' . $i],
		['placeholder' => 'DNS ' . $i]
	));
}

$group->setHelp('Leave blank to use the system default DNS servers, this interface\'s IP if DNS forwarder is enabled, or the servers configured on the "General" page.');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'dhcp6c-dns',
	null,
	'Provide DNS servers to DHCPv6 clients',
	($pconfig['dhcp6c-dns'] == "enabled")
))->setHelp('Unchecking this box disables the dhcp6.name-servers option. ' .
			'Use with caution, as the resulting behavior may violate RFCs and lead to unintended client behavior.');

$section->addInput(new Form_Input(
	'domain',
	'Domain name',
	'text',
	$pconfig['domain']
))->setHelp('The default is to use the domain name of this system as the default domain name provided by DHCP. An alternate domain name may be specified here. ');

$section->addInput(new Form_Input(
	'domainsearchlist',
	'Domain search list',
	'text',
	$pconfig['domainsearchlist']
))->setHelp('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.');

$section->addInput(new Form_Input(
	'deftime',
	'Default lease time',
	'text',
	$pconfig['deftime']
))->setHelp('Lease time in seconds. Used for clients that do not ask for a specific expiration time. %1$s' .
			'The default is 7200 seconds.', '<br />');

$section->addInput(new Form_Input(
	'maxtime',
	'Max lease time',
	'text',
	$pconfig['maxtime']
))->setHelp('Maximum lease time for clients that ask for a specific expiration time. %1$s' .
			'The default is 86400 seconds.', '<br />');

$section->addInput(new Form_Checkbox(
	'dhcpv6leaseinlocaltime',
	'Time Format Change',
	'Change DHCPv6 display lease time from UTC to local time',
	$pconfig['dhcpv6leaseinlocaltime']
))->setHelp('By default DHCPv6 leases are displayed in UTC time. ' .
			'By checking this box DHCPv6 lease time will be displayed in local time and set to time zone selected. ' .
			'This will be used for all DHCPv6 interfaces lease time.');

$btnadv = new Form_Button(
	'btnadvdns',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Dynamic DNS',
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

$btnadv = new Form_Button(
	'btnadvntp',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'NTP servers',
	$btnadv
));

$group = new Form_Group('NTP Servers');

$group->add(new Form_Input(
	'ntp1',
	'NTP Server 1',
	'text',
	$pconfig['ntp1'],
	['placeholder' => 'NTP 1']
));

$group->add(new Form_Input(
	'ntp2',
	'NTP Server 2',
	'text',
	$pconfig['ntp2'],
	['placeholder' => 'NTP 2']
));

$group->add(new Form_Input(
	'ntp3',
	'NTP Server 3',
	'text',
	$pconfig['ntp3'],
	['placeholder' => 'NTP 3']
));

$group->addClass('ntpclass');

$section->add($group);

$btnadv = new Form_Button(
	'btnadvldap',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'LDAP',
	$btnadv
));

$section->addInput(new Form_Input(
	'ldap',
	'LDAP URI',
	'text',
	$pconfig['ldap']
));

$btnadv = new Form_Button(
	'btnadvnetboot',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Network booting',
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'netboot',
	'Network booting',
	'Enable Network Booting',
	$pconfig['netboot']
));

$section->addInput(new Form_Input(
	'bootfile_url',
	'Bootfile URL',
	'text',
	$pconfig['bootfile_url']
));

$btnadv = new Form_Button(
	'btnadvopts',
	'Display Advanced',
	null,
	'fa-cog'
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
		'fa-trash'
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
	'fa-plus'
);

$btnaddopt->removeClass('btn-primary')->addClass('btn-success btn-sm');

$section->addInput($btnaddopt);

$form->addGlobal(new Form_Input(
	'if',
	null,
	'hidden',
	$if
));

print($form);

?>
<div class="infoblock blockopen">
<?php
print_info_box(
	sprintf(
		gettext('The DNS servers entered in %1$sSystem: General Setup%3$s (or the %2$sDNS forwarder%3$s if enabled) will be assigned to clients by the DHCP server.'),
		'<a href="system.php">',
		'<a href="services_dnsmasq.php"/>',
		'</a>') .
	'<br />' .
	sprintf(
		gettext('The DHCP lease table can be viewed on the %1$sStatus: DHCPv6 leases%2$s page.'),
		'<a href="status_dhcpv6_leases.php">',
		'</a>'),
	'info',
	false);
?>
</div>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("DHCPv6 Static Mappings for this Interface");?></h2></div>
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
if (is_array($a_maps)):
	$i = 0;
	foreach ($a_maps as $mapent):
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
						<a class="fa fa-pencil"	title="<?=gettext('Edit static mapping')?>" href="services_dhcpv6_edit.php?if=<?=$if?>&amp;id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext('Delete static mapping')?>" href="services_dhcpv6.php?if=<?=$if?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
		endif;
	$i++;
	endforeach;
endif;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcpv6_edit.php?if=<?=$if?>" class="btn btn-sm btn-success"/>
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

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
		$('#btnadvdns').html('<i class="fa fa-cog"></i> ' + text);
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

		if (showadvntp) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvntp').html('<i class="fa fa-cog"></i> ' + text);
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
		$('#btnadvldap').html('<i class="fa fa-cog"></i> ' + text);
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
		$('#btnadvnetboot').html('<i class="fa fa-cog"></i> ' + text);
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
		$('#btnadvopts').html('<i class="fa fa-cog"></i> ' + text);
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

<?php include('foot.inc');
