<?php
/*
 * services_dhcp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-dhcpserver
##|*NAME=Services: DHCP Server
##|*DESCR=Allow access to the 'Services: DHCP Server' page.
##|*MATCH=services_dhcp.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once('rrd.inc');
require_once("shaper.inc");
require_once("util.inc");

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

$if = $_REQUEST['if'];
$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	$found_starting_if = false;
	// First look for an interface with DHCP already enabled.
	foreach ($iflist as $ifent => $ifname) {
		$oc = $config['interfaces'][$ifent];
		if (is_array($config['dhcpd'][$ifent]) &&
		    isset($config['dhcpd'][$ifent]['enable']) &&
		    is_ipaddrv4($oc['ipaddr']) && $oc['subnet'] < 31) {
			$if = $ifent;
			$found_starting_if = true;
			break;
		}
	}

	/*
	 * If there is no DHCP-enabled interface and LAN is a candidate,
	 * then choose LAN.
	 */
	if (!$found_starting_if && isset($iflist['lan']) &&
	    is_ipaddrv4($config['interfaces']['lan']['ipaddr']) &&
	    $config['interfaces']['lan']['subnet'] < 31) {
		$if = 'lan';
		$found_starting_if = true;
	}

	// At the last select whatever can be found.
	if (!$found_starting_if) {
		foreach ($iflist as $ifent => $ifname) {
			$oc = $config['interfaces'][$ifent];

			/* Not static IPv4 or subnet >= 31 */
			if (!is_ipaddrv4($oc['ipaddr']) ||
			    empty($oc['subnet']) || $oc['subnet'] < 31) {
				continue;
			}

			if (!is_array($config['dhcpd'][$ifent]) ||
			    !isset($config['dhcpd'][$ifent]['enable'])) {
				continue;
			}

			$if = $ifent;
			break;
		}
	}
}

$act = $_REQUEST['act'];

$a_pools = array();

if (is_array($config['dhcpd'][$if])) {
	$pool = $_REQUEST['pool'];
	if (is_numeric($_POST['pool'])) {
		$pool = $_POST['pool'];
	}

	// If we have a pool but no interface name, that's not valid. Redirect away.
	if (is_numeric($pool) && empty($if)) {
		header("Location: services_dhcp.php");
		exit;
	}

	init_config_arr(array('dhcpd', $if, 'pool'));
	$a_pools = &$config['dhcpd'][$if]['pool'];

	if (is_numeric($pool) && $a_pools[$pool]) {
		$dhcpdconf = &$a_pools[$pool];
	} elseif ($act == "newpool") {
		$dhcpdconf = array();
	} else {
		$dhcpdconf = &$config['dhcpd'][$if];
	}

	init_config_arr(array('dhcpd', $if, 'staticmap'));
	$a_maps = &$config['dhcpd'][$if]['staticmap'];
}

if (is_array($dhcpdconf)) {
	// Global Options
	if (!is_numeric($pool) && !($act == "newpool")) {
		$pconfig['enable'] = isset($dhcpdconf['enable']);
		$pconfig['staticarp'] = isset($dhcpdconf['staticarp']);
		// No reason to specify this per-pool, per the dhcpd.conf man page it needs to be in every
		//	 pool and should be specified in every pool both nodes share, so we'll treat it as global
		$pconfig['failover_peerip'] = $dhcpdconf['failover_peerip'];

		// dhcpleaseinlocaltime is global to all interfaces. So if it is selected on any interface,
		// then show it true/checked.
		foreach ($config['dhcpd'] as $dhcpdifitem) {
			$dhcpleaseinlocaltime = $dhcpdifitem['dhcpleaseinlocaltime'];
			if ($dhcpleaseinlocaltime) {
				break;
			}
		}

		$pconfig['dhcpleaseinlocaltime'] = $dhcpleaseinlocaltime;
	} else {
		// Options that exist only in pools
		$pconfig['descr'] = $dhcpdconf['descr'];
	}

	// Options that can be global or per-pool.
	if (is_array($dhcpdconf['range'])) {
		$pconfig['range_from'] = $dhcpdconf['range']['from'];
		$pconfig['range_to'] = $dhcpdconf['range']['to'];
	}

	$pconfig['deftime'] = $dhcpdconf['defaultleasetime'];
	$pconfig['maxtime'] = $dhcpdconf['maxleasetime'];
	$pconfig['gateway'] = $dhcpdconf['gateway'];
	$pconfig['domain'] = $dhcpdconf['domain'];
	$pconfig['domainsearchlist'] = $dhcpdconf['domainsearchlist'];
	list($pconfig['wins1'], $pconfig['wins2']) = $dhcpdconf['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $dhcpdconf['dnsserver'];
	$pconfig['ignorebootp'] = isset($dhcpdconf['ignorebootp']);
	$pconfig['denyunknown'] = isset($dhcpdconf['denyunknown']);
	$pconfig['ignoreclientuids'] = isset($dhcpdconf['ignoreclientuids']);
	$pconfig['nonak'] = isset($dhcpdconf['nonak']);
	$pconfig['ddnsdomain'] = $dhcpdconf['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $dhcpdconf['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $dhcpdconf['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $dhcpdconf['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $dhcpdconf['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($dhcpdconf['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($dhcpdconf['ddnsforcehostname']);
	$pconfig['mac_allow'] = $dhcpdconf['mac_allow'];
	$pconfig['mac_deny'] = $dhcpdconf['mac_deny'];
	list($pconfig['ntp1'], $pconfig['ntp2']) = $dhcpdconf['ntpserver'];
	$pconfig['tftp'] = $dhcpdconf['tftp'];
	$pconfig['ldap'] = $dhcpdconf['ldap'];
	$pconfig['netboot'] = isset($dhcpdconf['netboot']);
	$pconfig['nextserver'] = $dhcpdconf['nextserver'];
	$pconfig['filename'] = $dhcpdconf['filename'];
	$pconfig['filename32'] = $dhcpdconf['filename32'];
	$pconfig['filename64'] = $dhcpdconf['filename64'];
	$pconfig['rootpath'] = $dhcpdconf['rootpath'];
	$pconfig['netmask'] = $dhcpdconf['netmask'];
	$pconfig['numberoptions'] = $dhcpdconf['numberoptions'];
	$pconfig['statsgraph'] = $dhcpdconf['statsgraph'];
	$pconfig['disablepingcheck'] = $dhcpdconf['disablepingcheck'];
	$pconfig['ddnsclientupdates'] = $dhcpdconf['ddnsclientupdates'];

	// OMAPI Settings
	if(isset($dhcpdconf['omapi_port'])) {
		$pconfig['omapi_port'] = $dhcpdconf['omapi_port'];
		$pconfig['omapi_key'] = $dhcpdconf['omapi_key'];
		$pconfig['omapi_key_algorithm'] = $dhcpdconf['omapi_key_algorithm'];
	}
}

$ifcfgip = $config['interfaces'][$if]['ipaddr'];
$ifcfgsn = $config['interfaces'][$if]['subnet'];

$subnet_start = gen_subnetv4($ifcfgip, $ifcfgsn);
$subnet_end = gen_subnetv4_max($ifcfgip, $ifcfgsn);

function validate_partial_mac_list($maclist) {
	$macs = explode(',', $maclist);

	// Loop through and look for invalid MACs.
	foreach ($macs as $mac) {
		if (!is_macaddr($mac, true)) {
			return false;
		}
	}

	return true;
}

if (isset($_POST['save'])) {

	unset($input_errors);

	$pconfig = $_POST;

	$numberoptions = array();
	for ($x = 0; $x < 99; $x++) {
		if (isset($_POST["number{$x}"]) && ctype_digit($_POST["number{$x}"])) {
			if ($_POST["number{$x}"] < 1 || $_POST["number{$x}"] > 254) {
				$input_errors[] = gettext("The DHCP option must be a number between 1 and 254.");
				continue;
			}
			$numbervalue = array();
			$numbervalue['number'] = htmlspecialchars($_POST["number{$x}"]);
			$numbervalue['type'] = htmlspecialchars($_POST["itemtype{$x}"]);
			$numbervalue['value'] = base64_encode($_POST["value{$x}"]);
			$numberoptions['item'][] = $numbervalue;
		}
	}

	// Reload the new pconfig variable that the form uses.
	$pconfig['numberoptions'] = $numberoptions;

	/* input validation */

	/*
	 * Check the OMAPI settings
	 * - Make sure that if the port is defined, that it is valid and isn't in use
	 * - Make sure the key is defined and the length is appropriate for the selected algorithm
	 * - Generate a new key if selected
	 */
	if (!empty($_POST['omapi_port'])) {
		// Check the port entry
		switch(true){
			case !is_port($_POST['omapi_port']) || $_POST['omapi_port'] <= 1024:
				$input_errors[] = gettext("The specified OMAPI port number is invalid. Port number must be between 1024 and 65635.");
				break;
			case is_port_in_use($_POST['omapi_port']) && $_POST['omapi_port'] != $dhcpdconf['omapi_port']:
				$input_errors[] = gettext("Specified port number for OMAPI is in use. Please choose another port or consider using the default.");
				break;
		}

		// Define the minimum base64 character length for each algorithm
		$key_char_len_by_alg = array(
			'hmac-md5' => 24,
			'hmac-sha1' => 28,
			'hmac-sha224' => 40,
			'hmac-sha256' => 44,
			'hmac-sha384' => 64,
			'hmac-sha512' => 88
		);

		// Generate a key if checked
		if ($_POST['omapi_gen_key'] == "yes") {
			// Figure out the key bits from the selected algorithm
			switch ($_POST['omapi_key_algorithm']) {
				case "hmac-md5":
					$key_bit_len = 128;
					break;
				case "hmac-sha1":
					$key_bit_len = 160;
					break;
				default:
					$key_bit_len = str_replace("hmac-sha","",$_POST['omapi_key_algorithm']);
					break;
			}

			// Convert the bits to bytes
			$key_bytes_len = $key_bit_len / 8; // 8 bits = 1 Byte

			// Generate random bytes based on key length
			$ran_bytes = openssl_random_pseudo_bytes($key_bytes_len);

			// Encode the bytes to get the key string
			$key_str = base64_encode($ran_bytes);

			// Set the key
			$_POST['omapi_key'] = $key_str;
			$pconfig['omapi_key'] = $key_str;

			// Uncheck the generate box
			unset($_POST['omapi_gen_key']);
			unset($pconfig['omapi_gen_key']);
		} elseif (!empty($_POST['omapi_key'])) { // Check the key if it's not being generated
			if (strlen($_POST['omapi_key']) < $key_char_len_by_alg[$_POST['omapi_key_algorithm']]) {
				$input_errors[] = gettext("Please specify a valid OMAPI key. Key does not meet the minimum length requirement of {$key_char_len_by_alg[$_POST['omapi_key_algorithm']]} for the selected algorithm {$_POST['omapi_key_algorithm']}.");
			}
		} else {
			$input_errors[] = gettext("A key is required when OMAPI is enabled (port specified).");
		}
	}

	// Note: if DHCP Server is not enabled, then it is OK to adjust other parameters without specifying range from-to.
	if ($_POST['enable'] || is_numeric($pool) || $act == "newpool") {
		$reqdfields = explode(" ", "range_from range_to");
		$reqdfieldsn = array(gettext("Range begin"), gettext("Range end"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if (($_POST['nonak']) && !empty($_POST['failover_peerip'])) {
		$input_errors[] = gettext("Ignore Denied Clients may not be used when a Failover Peer IP is defined.");
	}

	if ($_POST['range_from'] && !is_ipaddrv4($_POST['range_from'])) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for range from.");
	}
	if ($_POST['range_to'] && !is_ipaddrv4($_POST['range_to'])) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for range to.");
	}
	if (($_POST['range_from'] && !$_POST['range_to']) || ($_POST['range_to'] && !$_POST['range_from'])) {
		$input_errors[] = gettext("Range From and Range To must both be entered.");
	}
	if (($_POST['gateway'] && $_POST['gateway'] != "none" && !is_ipaddrv4($_POST['gateway']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the gateway.");
	}
	if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
	}
	$parent_ip = get_interface_ip($_POST['if']);
	if (is_ipaddrv4($parent_ip) && $_POST['gateway'] && $_POST['gateway'] != "none") {
		$parent_sn = get_interface_subnet($_POST['if']);
		if (!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['if'], $_POST['gateway'])) {
			$input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
		}
	}

	if (($_POST['dns1'] && !is_ipaddrv4($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddrv4($_POST['dns2'])) || ($_POST['dns3'] && !is_ipaddrv4($_POST['dns3'])) || ($_POST['dns4'] && !is_ipaddrv4($_POST['dns4']))) {
		$input_errors[] = gettext("A valid IP address must be specified for each of the DNS servers.");
	}

	if ($_POST['deftime'] && (!is_numeric($_POST['deftime']) || ($_POST['deftime'] < 60))) {
		$input_errors[] = gettext("The default lease time must be at least 60 seconds.");
	}

	if (isset($config['captiveportal']) && is_array($config['captiveportal'])) {
		$deftime = 7200; // Default value if it's empty
		if (is_numeric($_POST['deftime'])) {
			$deftime = $_POST['deftime'];
		}

		foreach ($config['captiveportal'] as $cpZone => $cpdata) {
			if (!isset($cpdata['enable'])) {
				continue;
			}
			if (!isset($cpdata['timeout']) || !is_numeric($cpdata['timeout'])) {
				continue;
			}
			$cp_ifs = explode(',', $cpdata['interface']);
			if (!in_array($if, $cp_ifs)) {
				continue;
			}
			if ($cpdata['timeout'] > $deftime) {
				$input_errors[] = sprintf(gettext(
					'The Captive Portal zone (%1$s) has Hard Timeout parameter set to a value bigger than Default lease time (%2$s).'), $cpZone, $deftime);
			}
		}
	}

	if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
		$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
	}
	if ($_POST['ddnsupdate'] && !is_domain($_POST['ddnsdomain'])) {
		$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
	}
	if ($_POST['ddnsupdate'] && !is_ipaddrv4($_POST['ddnsdomainprimary'])) {
		$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
	}
	if ($_POST['ddnsupdate'] && (!$_POST['ddnsdomainkeyname'] || !$_POST['ddnsdomainkeyalgorithm'] || !$_POST['ddnsdomainkey'])) {
		$input_errors[] = gettext("A valid domain key name, algorithm and secret must be specified.");
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

	// Validate MACs
	if (!empty($_POST['mac_allow']) && !validate_partial_mac_list($_POST['mac_allow'])) {
		$input_errors[] = gettext("If a mac allow list is specified, it must contain only valid partial MAC addresses.");
	}
	if (!empty($_POST['mac_deny']) && !validate_partial_mac_list($_POST['mac_deny'])) {
		$input_errors[] = gettext("If a mac deny list is specified, it must contain only valid partial MAC addresses.");
	}

	if (($_POST['ntp1'] && (!is_ipaddrv4($_POST['ntp1']) && !is_hostname($_POST['ntp1']))) || ($_POST['ntp2'] && (!is_ipaddrv4($_POST['ntp2']) && !is_hostname($_POST['ntp2'])))) {
		$input_errors[] = gettext("A valid IP address or hostname must be specified for the primary/secondary NTP servers.");
	}
	if (($_POST['domain'] && !is_domain($_POST['domain']))) {
		$input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
	}
	if ($_POST['tftp'] && !is_ipaddrv4($_POST['tftp']) && !is_domain($_POST['tftp']) && !filter_var($_POST['tftp'], FILTER_VALIDATE_URL)) {
		$input_errors[] = gettext("A valid IP address, hostname or URL must be specified for the TFTP server.");
	}
	if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the network boot server.");
	}

	if (gen_subnet($ifcfgip, $ifcfgsn) == $_POST['range_from']) {
		$input_errors[] = gettext("The network address cannot be used in the starting subnet range.");
	}
	if (gen_subnet_max($ifcfgip, $ifcfgsn) == $_POST['range_to']) {
		$input_errors[] = gettext("The broadcast address cannot be used in the ending subnet range.");
	}

	// Disallow a range that includes the virtualip
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['interface'] == $if) {
				if ($vip['subnet'] && is_inrange_v4($vip['subnet'], $_POST['range_from'], $_POST['range_to'])) {
					$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IP address %s."), $vip['subnet']);
				}
			}
		}
	}

	$noip = false;
	if (is_array($a_maps)) {
		foreach ($a_maps as $map) {
			if (empty($map['ipaddr'])) {
				$noip = true;
			}
		}
	}

	if ($_POST['staticarp'] && $noip) {
		$input_errors[] = gettext("Cannot enable static ARP when there are static map entries without IP addresses. Ensure all static maps have IP addresses and try again.");
	}

	if (is_array($pconfig['numberoptions']['item'])) {
		foreach ($pconfig['numberoptions']['item'] as $numberoption) {
			$numberoption_value = base64_decode($numberoption['value']);
			if ($numberoption['type'] == 'text' && strstr($numberoption_value, '"')) {
				$input_errors[] = gettext("Text type cannot include quotation marks.");
			} else if ($numberoption['type'] == 'string' && !preg_match('/^"[^"]*"$/', $numberoption_value) && !preg_match('/^[0-9a-f]{2}(?:\:[0-9a-f]{2})*$/i', $numberoption_value)) {
				$input_errors[] = gettext("String type must be enclosed in quotes like \"this\" or must be a series of octets specified in hexadecimal, separated by colons, like 01:23:45:67:89:ab:cd:ef");
			} else if ($numberoption['type'] == 'boolean' && $numberoption_value != 'true' && $numberoption_value != 'false' && $numberoption_value != 'on' && $numberoption_value != 'off') {
				$input_errors[] = gettext("Boolean type must be true, false, on, or off.");
			} else if ($numberoption['type'] == 'unsigned integer 8' && (!is_numeric($numberoption_value) || $numberoption_value < 0 || $numberoption_value > 255)) {
				$input_errors[] = gettext("Unsigned 8-bit integer type must be a number in the range 0 to 255.");
			} else if ($numberoption['type'] == 'unsigned integer 16' && (!is_numeric($numberoption_value) || $numberoption_value < 0 || $numberoption_value > 65535)) {
				$input_errors[] = gettext("Unsigned 16-bit integer type must be a number in the range 0 to 65535.");
			} else if ($numberoption['type'] == 'unsigned integer 32' && (!is_numeric($numberoption_value) || $numberoption_value < 0 || $numberoption_value > 4294967295)) {
				$input_errors[] = gettext("Unsigned 32-bit integer type must be a number in the range 0 to 4294967295.");
			} else if ($numberoption['type'] == 'signed integer 8' && (!is_numeric($numberoption_value) || $numberoption_value < -128 || $numberoption_value > 127)) {
				$input_errors[] = gettext("Signed 8-bit integer type must be a number in the range -128 to 127.");
			} else if ($numberoption['type'] == 'signed integer 16' && (!is_numeric($numberoption_value) || $numberoption_value < -32768 || $numberoption_value > 32767)) {
				$input_errors[] = gettext("Signed 16-bit integer type must be a number in the range -32768 to 32767.");
			} else if ($numberoption['type'] == 'signed integer 32' && (!is_numeric($numberoption_value) || $numberoption_value < -2147483648 || $numberoption_value > 2147483647)) {
				$input_errors[] = gettext("Signed 32-bit integer type must be a number in the range -2147483648 to 2147483647.");
			} else if ($numberoption['type'] == 'ip-address' && !is_ipaddrv4($numberoption_value) && !is_hostname($numberoption_value)) {
				$input_errors[] = gettext("IP address or host type must be an IP address or host name.");
			}
		}
	}

	if ((!isset($pool) || !is_numeric($pool)) && $act != "newpool") {
		/* If enabling DHCP Server, make sure that the DHCP Relay isn't enabled on this interface */
		if ($_POST['enable'] && isset($config['dhcrelay']['enable']) &&
		    (stristr($config['dhcrelay']['interface'], $if) !== false)) {
			$input_errors[] = sprintf(gettext(
			    "The DHCP relay on the %s interface must be disabled before enabling the DHCP server."),
			    $iflist[$if]);
		}

		/* If disabling DHCP Server, make sure that DHCP registration isn't enabled for DNS forwarder/resolver */
		if (!$_POST['enable']) {
			/* Find out how many other interfaces have DHCP enabled. */
			$dhcp_enabled_count = 0;
			foreach ($config['dhcpd'] as $dhif => $dhcps) {
				if ($dhif == $if) {
					/* Skip this interface, we only want to know how many others are enabled. */
					continue;
				}
				if (isset($dhcps['enable'])) {
					$dhcp_enabled_count++;
				}
			}

			if (isset($config['dnsmasq']['enable']) &&
			    ($dhcp_enabled_count == 0) &&
			    (isset($config['dnsmasq']['regdhcp']) ||
			    isset($config['dnsmasq']['regdhcpstatic']) ||
			    isset($config['dnsmasq']['dhcpfirst']))) {
				$input_errors[] = gettext(
				    "DHCP Registration features in the DNS Forwarder are active and require at least one enabled DHCP Server.");
			}
			if (isset($config['unbound']['enable']) &&
			    ($dhcp_enabled_count == 0) &&
			    (isset($config['unbound']['regdhcp']) ||
			    isset($config['unbound']['regdhcpstatic']))) {
				$input_errors[] = gettext(
				    "DHCP Registration features in the DNS Resolver are active and require at least one enabled DHCP Server.");
			}
		}
	}

	// If nothing is wrong so far, and we have range from and to, then check conditions related to the values of range from and to.
	if (!$input_errors && $_POST['range_from'] && $_POST['range_to']) {
		/* make sure the range lies within the current subnet */
		if (ip_greater_than($_POST['range_from'], $_POST['range_to'])) {
			$input_errors[] = gettext("The range is invalid (first element higher than second element).");
		}

		if (!is_inrange_v4($_POST['range_from'], $subnet_start, $subnet_end) ||
			!is_inrange_v4($_POST['range_to'], $subnet_start, $subnet_end)) {
			$input_errors[] = gettext("The specified range lies outside of the current subnet.");
		}

		if (is_numeric($pool) || ($act == "newpool")) {
			if (is_inrange_v4($_POST['range_from'],
				$config['dhcpd'][$if]['range']['from'],
				$config['dhcpd'][$if]['range']['to']) ||
				is_inrange_v4($_POST['range_to'],
				$config['dhcpd'][$if]['range']['from'],
				$config['dhcpd'][$if]['range']['to'])) {
				$input_errors[] = gettext("The specified range must not be within the DHCP range for this interface.");
			}
		}

		foreach ($a_pools as $id => $p) {
			if (is_numeric($pool) && ($id == $pool)) {
				continue;
			}

			if (is_inrange_v4($_POST['range_from'],
				$p['range']['from'], $p['range']['to']) ||
				is_inrange_v4($_POST['range_to'],
				$p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext("The specified range must not be within the range configured on a DHCP pool for this interface.");
				break;
			}
		}

		if (is_array($a_maps)) {
			foreach ($a_maps as $map) {
				if (empty($map['ipaddr'])) {
					continue;
				}
				if (is_inrange_v4($map['ipaddr'], $_POST['range_from'], $_POST['range_to'])) {
					$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
					break;
				}
			}
		}
	}

	if (!$input_errors) {
		if (!is_numeric($pool)) {
			if ($act == "newpool") {
				$dhcpdconf = array();
			} else {
				if (!is_array($config['dhcpd'])) {
					$config['dhcpd']= array();
				}
				if (!is_array($config['dhcpd'][$if])) {
					$config['dhcpd'][$if] = array();
				}
				$dhcpdconf = $config['dhcpd'][$if];
			}
		} else {
			if (is_array($a_pools[$pool])) {
				$dhcpdconf = $a_pools[$pool];
			} else {
				// Someone specified a pool but it doesn't exist. Punt.
				header("Location: services_dhcp.php");
				exit;
			}
		}
		if (!is_array($dhcpdconf)) {
			$dhcpdconf = array();
		}
		if (!is_array($dhcpdconf['range'])) {
			$dhcpdconf['range'] = array();
		}

		$dhcpd_enable_changed = false;

		// Global Options
		if (!is_numeric($pool) && !($act == "newpool")) {
			$old_dhcpd_enable = isset($dhcpdconf['enable']);
			$new_dhcpd_enable = ($_POST['enable']) ? true : false;
			if ($old_dhcpd_enable != $new_dhcpd_enable) {
				/* DHCP has been enabled or disabled. The pf ruleset will need to be rebuilt to allow or disallow DHCP. */
				$dhcpd_enable_changed = true;
			}

			$dhcpdconf['enable'] = $new_dhcpd_enable;
			$dhcpdconf['staticarp'] = ($_POST['staticarp']) ? true : false;
			$previous = $dhcpdconf['failover_peerip'];
			if ($previous != $_POST['failover_peerip']) {
				mwexec("/bin/rm -rf /var/dhcpd/var/db/*");
			}

			$dhcpdconf['failover_peerip'] = $_POST['failover_peerip'];
			// dhcpleaseinlocaltime is global to all interfaces. So update the setting on all interfaces.
			foreach ($config['dhcpd'] as &$dhcpdifitem) {
				$dhcpdifitem['dhcpleaseinlocaltime'] = $_POST['dhcpleaseinlocaltime'];
			}
		} else {
			// Options that exist only in pools
			$dhcpdconf['descr'] = $_POST['descr'];
		}

		// Options that can be global or per-pool.
		$dhcpdconf['range']['from'] = $_POST['range_from'];
		$dhcpdconf['range']['to'] = $_POST['range_to'];
		$dhcpdconf['defaultleasetime'] = $_POST['deftime'];
		$dhcpdconf['maxleasetime'] = $_POST['maxtime'];
		$dhcpdconf['netmask'] = $_POST['netmask'];

		unset($dhcpdconf['winsserver']);
		if ($_POST['wins1']) {
			$dhcpdconf['winsserver'][] = $_POST['wins1'];
		}
		if ($_POST['wins2']) {
			$dhcpdconf['winsserver'][] = $_POST['wins2'];
		}

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

		$dhcpdconf['gateway'] = $_POST['gateway'];
		$dhcpdconf['domain'] = $_POST['domain'];
		$dhcpdconf['domainsearchlist'] = $_POST['domainsearchlist'];
		$dhcpdconf['ignorebootp'] = ($_POST['ignorebootp']) ? true : false;
		$dhcpdconf['denyunknown'] = ($_POST['denyunknown']) ? true : false;
		$dhcpdconf['ignoreclientuids'] = ($_POST['ignoreclientuids']) ? true : false;
		$dhcpdconf['nonak'] = ($_POST['nonak']) ? true : false;
		$dhcpdconf['ddnsdomain'] = $_POST['ddnsdomain'];
		$dhcpdconf['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$dhcpdconf['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$dhcpdconf['ddnsdomainkeyalgorithm'] = $_POST['ddnsdomainkeyalgorithm'];
		$dhcpdconf['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$dhcpdconf['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$dhcpdconf['ddnsforcehostname'] = ($_POST['ddnsforcehostname']) ? true : false;
		$dhcpdconf['mac_allow'] = $_POST['mac_allow'];
		$dhcpdconf['mac_deny'] = $_POST['mac_deny'];
		$dhcpdconf['ddnsclientupdates'] = $_POST['ddnsclientupdates'];

		unset($dhcpdconf['ntpserver']);
		if ($_POST['ntp1']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$dhcpdconf['ntpserver'][] = $_POST['ntp2'];
		}

		$dhcpdconf['tftp'] = $_POST['tftp'];
		$dhcpdconf['ldap'] = $_POST['ldap'];
		$dhcpdconf['netboot'] = ($_POST['netboot']) ? true : false;
		$dhcpdconf['nextserver'] = $_POST['nextserver'];
		$dhcpdconf['filename'] = $_POST['filename'];
		$dhcpdconf['filename32'] = $_POST['filename32'];
		$dhcpdconf['filename64'] = $_POST['filename64'];
		$dhcpdconf['rootpath'] = $_POST['rootpath'];
		unset($dhcpdconf['statsgraph']);
		if ($_POST['statsgraph']) {
			$dhcpdconf['statsgraph'] = $_POST['statsgraph'];
			enable_rrd_graphing();
		}
		unset($dhcpdconf['disablepingcheck']);
		if ($_POST['disablepingcheck']) {
			$dhcpdconf['disablepingcheck'] = $_POST['disablepingcheck'];
		}

		// Handle the custom options rowhelper
		if (isset($dhcpdconf['numberoptions']['item'])) {
			unset($dhcpdconf['numberoptions']['item']);
		}

		$dhcpdconf['numberoptions'] = $numberoptions;

		if (is_numeric($pool) && is_array($a_pools[$pool])) {
			$a_pools[$pool] = $dhcpdconf;
		} elseif ($act == "newpool") {
			$a_pools[] = $dhcpdconf;
		} else {
			$config['dhcpd'][$if] = $dhcpdconf;
		}

		// OMAPI Settings
		if ($_POST['omapi_port'] == ""){
			unset($dhcpdconf['omapi_port']);
			unset($dhcpdconf['omapi_key']);
			unset($dhcpdconf['omapi_key_algorithm']);

			unset($pconfig['omapi_port']);
			unset($pconfig['omapi_key']);
			unset($pconfig['omapi_key_algorithm']);
		} else {
			$dhcpdconf['omapi_port'] = $_POST['omapi_port'];
			$dhcpdconf['omapi_key'] = $_POST['omapi_key'];
			$dhcpdconf['omapi_key_algorithm'] = $_POST['omapi_key_algorithm'];
		}

		write_config(gettext("DHCP Server - Settings changed for interface " . strtoupper($if)));
	}
}

if ((isset($_POST['save']) || isset($_POST['apply'])) && (!$input_errors)) {
	$changes_applied = true;
	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
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
			clear_subsystem_dirty('hosts');
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
	if ($dhcpd_enable_changed) {
		$retvalfc |= filter_configure();
	}

	if ($retvaldhcp == 1 || $retvaldns == 1 || $retvalfc == 1) {
		$retval = 1;
	}
}

if ($act == "delpool") {
	if ($a_pools[$_POST['id']]) {
		unset($a_pools[$_POST['id']]);
		write_config();
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

if ($act == "del") {
	if (isset($a_maps[$_POST['id']])) {
		/* Remove static ARP entry, if necessary */
		if (isset($a_maps[$_POST['id']]['arp_table_static_entry'])) {
			mwexec("/usr/sbin/arp -d " . escapeshellarg($a_maps[$_POST['id']]['ipaddr']));
		}
		unset($a_maps[$_POST['id']]);
		write_config();
		if (isset($config['dhcpd'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic'])) {
				mark_subsystem_dirty('hosts');
			}
		}

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

// Build an HTML table that can be inserted into a Form_StaticText element
function build_pooltable() {
	global $a_pools, $if;

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

	if (is_array($a_pools)) {
		$i = 0;
		foreach ($a_pools as $poolent) {
			if (!empty($poolent['range']['from']) && !empty($poolent['range']['to'])) {
				$pooltbl .= '<tr>';
				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['range']['from']) . '</td>';

				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['range']['to']) . '</td>';

				$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
							htmlspecialchars($poolent['descr']) . '</td>';

				$pooltbl .= '<td><a class="fa fa-pencil" title="'. gettext("Edit pool") . '" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '"></a>';

				$pooltbl .= ' <a class="fa fa-trash" title="'. gettext("Delete pool") . '" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&act=delpool&id=' . $i . '" usepost></a></td>';
				$pooltbl .= '</tr>';
			}
		$i++;
		}
	}

	$pooltbl .=			'</tbody>';
	$pooltbl .=		'</table>';
	$pooltbl .= '</div>';

	return($pooltbl);
}

$pgtitle = array(gettext("Services"), gettext("DHCP Server"));
$pglinks = array("", "services_dhcp.php");

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = "@self";
}
$shortcut_section = "dhcp";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('staticmaps')) {
	print_apply_box(gettext("The static mapping configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
$have_small_subnet = false;

foreach ($iflist as $ifent => $ifname) {
	$oc = $config['interfaces'][$ifent];

	/* Not static IPv4 or subnet >= 31 */
	if ($oc['subnet'] >= 31) {
		$have_small_subnet = true;
		$example_name = $ifname;
		$example_cidr = $oc['subnet'];
		continue;
	}
	if (!is_ipaddrv4($oc['ipaddr']) || empty($oc['subnet'])) {
		continue;
	}

	if ($ifent == $if) {
		$active = true;
	} else {
		$active = false;
	}

	$tab_array[] = array($ifname, $active, "services_dhcp.php?if={$ifent}");
	$tabscounter++;
}

if ($tabscounter == 0) {
	if ($have_small_subnet) {
		$sentence2 = sprintf(gettext('%1$s has a CIDR mask of %2$s, which does not contain enough addresses.'), htmlspecialchars($example_name), htmlspecialchars($example_cidr));
	} else {
		$sentence2 = gettext("This system has no interfaces configured with a static IPv4 address.");
	}
	print_info_box(gettext("The DHCP Server requires a static IPv4 subnet large enough to serve addresses to clients.") . " " . $sentence2);
	include("foot.inc");
	exit;
}

display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('General Options');

if (!is_numeric($pool) && !($act == "newpool")) {
	if (isset($config['dhcrelay']['enable'])) {
		$section->addInput(new Form_Checkbox(
			'enable',
			'Enable',
			gettext("DHCP Relay is currently enabled. DHCP Server canot be enabled while the DHCP Relay is enabled on any interface."),
			$pconfig['enable']
		))->setAttribute('disabled', true);
	} else {
		$section->addInput(new Form_Checkbox(
			'enable',
			'Enable',
			sprintf(gettext("Enable DHCP server on %s interface"), htmlspecialchars($iflist[$if])),
			$pconfig['enable']
		));
	}
} else {
	print_info_box(gettext('Editing pool-specific options. To return to the Interface, click its tab above.'), 'info', false);
}

$section->addInput(new Form_Checkbox(
	'ignorebootp',
	'BOOTP',
	'Ignore BOOTP queries',
	$pconfig['ignorebootp']
));

$section->addInput(new Form_Checkbox(
	'denyunknown',
	'Deny unknown clients',
	'Only the clients defined below will get DHCP leases from this server.',
	$pconfig['denyunknown']
));

$section->addInput(new Form_Checkbox(
	'nonak',
	'Ignore denied clients',
	'Denied clients will be ignored rather than rejected.',
	$pconfig['nonak']
))->setHelp("This option is not compatible with failover and cannot be enabled when a Failover Peer IP address is configured.");

$section->addInput(new Form_Checkbox(
	'ignoreclientuids',
	'Ignore client identifiers',
	'If a client includes a unique identifier in its DHCP request, that UID will not be recorded in its lease.',
	$pconfig['ignoreclientuids']
))->setHelp("This option may be useful when a client can dual boot using different client identifiers but the same hardware (MAC) address.  Note that the resulting server behavior violates the official DHCP specification.");


if (is_numeric($pool) || ($act == "newpool")) {
	$section->addInput(new Form_Input(
		'descr',
		'Pool Description',
		'text',
		$pconfig['descr']
	));
}

$section->addInput(new Form_StaticText(
	'Subnet',
	gen_subnet($ifcfgip, $ifcfgsn)
));

$section->addInput(new Form_StaticText(
	'Subnet mask',
	gen_subnet_mask($ifcfgsn)
));

// Compose a string to display the required address ranges
$rangestr = ip_after($subnet_start) . ' - ' . ip_before($subnet_end);

if (is_numeric($pool) || ($act == "newpool")) {
	$rangestr .= '<br />' . gettext('In-use DHCP Pool Ranges:');
	if (is_array($config['dhcpd'][$if]['range'])) {
		$rangestr .= '<br />' . $config['dhcpd'][$if]['range']['from'] . ' - ' . $config['dhcpd'][$if]['range']['to'];
	}

	foreach ($a_pools as $p) {
		if (is_array($p['range'])) {
			$rangestr .= '<br />' . $p['range']['from'] . ' - ' . $p['range']['to'];
		}
	}
}

$section->addInput(new Form_StaticText(
	'Available range',
	$rangestr
));

$group = new Form_Group('*Range');

$group->add(new Form_IpAddress(
	'range_from',
	null,
	$pconfig['range_from'],
	'V4'
))->setHelp('From');

$group->add(new Form_IpAddress(
	'range_to',
	null,
	$pconfig['range_to'],
	'V4'
))->setHelp('To');

$section->add($group);

$form->add($section);

if (!is_numeric($pool) && !($act == "newpool")) {
	$section = new Form_Section('Additional Pools');

	$btnaddpool = new Form_Button(
		'btnaddpool',
		'Add pool',
		'services_dhcp.php?if=' . htmlspecialchars($if) . '&act=newpool',
		'fa-plus'
	);
	$btnaddpool->addClass('btn-success');

	$section->addInput(new Form_StaticText(
		'Add',
		$btnaddpool
	))->setHelp('If additional pools of addresses are needed inside of this subnet outside the above Range, they may be specified here.');

	if (is_array($a_pools)) {
		$section->addInput(new Form_StaticText(
			null,
			build_pooltable()
		));
	}

	$form->add($section);
}

$section = new Form_Section('Servers');

$section->addInput(new Form_IpAddress(
	'wins1',
	'WINS servers',
	$pconfig['wins1'],
	'V4'
))->setAttribute('placeholder', 'WINS Server 1');

$section->addInput(new Form_IpAddress(
	'wins2',
	null,
	$pconfig['wins2'],
	'V4'
))->setAttribute('placeholder', 'WINS Server 2');

for ($idx=1; $idx<=4; $idx++) {
	$section->addInput(new Form_IpAddress(
		'dns' . $idx,
		($idx == 1) ? 'DNS servers':null,
		$pconfig['dns' . $idx],
		'V4'
	))->setAttribute('placeholder', 'DNS Server ' . $idx)->setHelp(($idx == 4) ? 'Leave blank to use the system default DNS servers: this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise the servers configured on the System / General Setup page.':'');
}

$form->add($section);

//OMAPI
$section = new Form_Section('OMAPI');

$section->addInput(new Form_Input(
	'omapi_port',
	'OMAPI Port',
	'text',
	$pconfig['omapi_port']
))->setAttribute('placeholder', 'OMAPI Port')
  ->setHelp('Set the port that OMAPI will listen on. The default port is 7911, leave blank to disable.');

$group = new Form_Group('OMAPI Key');

$group->add(new Form_Input(
	'omapi_key',
	'OMAPI Key',
	'text',
	$pconfig['omapi_key']
))->setAttribute('placeholder', 'OMAPI Key')
  ->setHelp('Enter a key matching the selected algorithm<br />to secure connections to the OMAPI endpoint.');

$group->add(new Form_Checkbox(
	'omapi_gen_key',
	'',
	'Generate New Key',
	$pconfig['omapi_gen_key']
))->setHelp('Generate a new key based<br />on the selected algorithm.');

$section->add($group);

$section->addInput(new Form_Select(
	'omapi_key_algorithm',
	'Key Algorithm',
	empty($pconfig['omapi_key_algorithm']) ? 'hmac-sha256' : $pconfig['omapi_key_algorithm'], // Set the default algorithm if not previous defined
	array(
		'hmac-md5' => 'HMAC-MD5 (legacy default)',
		'hmac-sha1' => 'HMAC-SHA1',
		'hmac-sha224' => 'HMAC-SHA224',
		'hmac-sha256' => 'HMAC-SHA256 (current bind9 default)',
		'hmac-sha384' => 'HMAC-SHA384',
		'hmac-sha512' => 'HMAC-SHA512 (most secure)',
	)
))->setHelp('Set the algorithm that OMAPI key will use.');

$form->add($section);

$section = new Form_Section('Other Options');

$section->addInput(new Form_IpAddress(
	'gateway',
	'Gateway',
	$pconfig['gateway'],
	'V4'
))->setPattern('[.a-zA-Z0-9_]+')
  ->setHelp('The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for the network. Type "none" for no gateway assignment.');

$section->addInput(new Form_Input(
	'domain',
	'Domain name',
	'text',
	$pconfig['domain']
))->setHelp('The default is to use the domain name of this system as the default domain name provided by DHCP. An alternate domain name may be specified here.');

$section->addInput(new Form_Input(
	'domainsearchlist',
	'Domain search list',
	'text',
	$pconfig['domainsearchlist']
))->setHelp('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.');

$section->addInput(new Form_Input(
	'deftime',
	'Default lease time',
	'number',
	$pconfig['deftime']
))->setHelp('This is used for clients that do not ask for a specific expiration time. The default is 7200 seconds.');

$section->addInput(new Form_Input(
	'maxtime',
	'Maximum lease time',
	'number',
	$pconfig['maxtime']
))->setHelp('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds.');

if (!is_numeric($pool) && !($act == "newpool")) {
	$section->addInput(new Form_IpAddress(
		'failover_peerip',
		'Failover peer IP',
		$pconfig['failover_peerip'],
		'V4'
	))->setHelp('Leave blank to disable. Enter the interface IP address of the other machine. Machines must be using CARP. ' .
				'Interface\'s advskew determines whether the DHCPd process is Primary or Secondary. Ensure one machine\'s advskew &lt; 20 (and the other is &gt; 20).');

	$section->addInput(new Form_Checkbox(
		'staticarp',
		'Static ARP',
		'Enable Static ARP entries',
		$pconfig['staticarp']
	))->setHelp('This option persists even if DHCP server is disabled. Only the machines listed below will be able to communicate with the firewall on this interface.');

	$section->addInput(new Form_Checkbox(
		'dhcpleaseinlocaltime',
		'Time format change',
		'Change DHCP display lease time from UTC to local time',
		$pconfig['dhcpleaseinlocaltime']
	))->setHelp('By default DHCP leases are displayed in UTC time.	By checking this box DHCP lease time will be displayed in local time and set to the time zone selected.' .
				' This will be used for all DHCP interfaces lease time.');

	$section->addInput(new Form_Checkbox(
		'statsgraph',
		'Statistics graphs',
		'Enable RRD statistics graphs',
		$pconfig['statsgraph']
	))->setHelp('Enable this to add DHCP leases statistics to the RRD graphs. Disabled by default.');

	$section->addInput(new Form_Checkbox(
		'disablepingcheck',
		'Ping check',
		'Disable ping check',
		$pconfig['disablepingcheck']
	))->setHelp('When enabled dhcpd sends a ping to the address being assigned, and if no response has been heard, it assigns the address. Enabled by default.');
}

// DDNS
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
	null,
	'Enable registration of DHCP client names in DNS',
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
	'V4'
))->setHelp('Primary domain name server IP address for the dynamic domain name.');

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	'DNS Domain key',
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp('Dynamic DNS domain key name which will be used to register client names in the DNS server.');

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
	'DNS Domain key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp('Dynamic DNS domain key secret which will be used to register client names in the DNS server.');

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

// Advanced MAC
$btnadv = new Form_Button(
	'btnadvmac',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'MAC address control',
	$btnadv
));

$section->addInput(new Form_Input(
	'mac_allow',
	'MAC Allow',
	'text',
	$pconfig['mac_allow']
))->setHelp('List of partial MAC addresses to allow, comma separated, no spaces, e.g.: 00:00:00,01:E5:FF');

$section->addInput(new Form_Input(
	'mac_deny',
	'MAC Deny',
	'text',
	$pconfig['mac_deny']
))->setHelp('List of partial MAC addresses to deny access, comma separated, no spaces, e.g.: 00:00:00,01:E5:FF');

// Advanced NTP
$btnadv = new Form_Button(
	'btnadvntp',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'NTP',
	$btnadv
));

$section->addInput(new Form_IpAddress(
	'ntp1',
	'NTP Server 1',
	$pconfig['ntp1'],
	'HOSTV4'
));

$section->addInput(new Form_IpAddress(
	'ntp2',
	'NTP Server 2',
	$pconfig['ntp2'],
	'HOSTV4'
));

// Advanced TFTP
$btnadv = new Form_Button(
	'btnadvtftp',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'TFTP',
	$btnadv
));

$section->addInput(new Form_Input(
	'tftp',
	'TFTP Server',
	'text',
	$pconfig['tftp']
))->setHelp('Leave blank to disable. Enter a valid IP address, hostname or URL for the TFTP server.');

// Advanced LDAP
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
	'LDAP Server URI',
	'text',
	$pconfig['ldap']
))->setHelp('Leave blank to disable. Enter a full URI for the LDAP server in the form ldap://ldap.example.com/dc=example,dc=com ');

// Advanced Network Booting options
$btnadv = new Form_Button(
	'btnadvnwkboot',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'Network Booting',
	$btnadv
));

$section->addInput(new Form_Checkbox(
	'netboot',
	'Enable',
	'Enables network booting',
	$pconfig['netboot']
));

$section->addInput(new Form_IpAddress(
	'nextserver',
	'Next Server',
	$pconfig['nextserver'],
	'V4'
))->setHelp('Enter the IP address of the next server');

$section->addInput(new Form_Input(
	'filename',
	'Default BIOS file name',
	'text',
	$pconfig['filename']
));

$section->addInput(new Form_Input(
	'filename32',
	'UEFI 32 bit file name',
	'text',
	$pconfig['filename32']
));

$section->addInput(new Form_Input(
	'filename64',
	'UEFI 64 bit file name',
	'text',
	$pconfig['filename64']
))->setHelp('Both a filename and a boot server must be configured for this to work! ' .
			'All three filenames and a configured boot server are necessary for UEFI to work! ');

$section->addInput(new Form_Input(
	'rootpath',
	'Root path',
	'text',
	$pconfig['rootpath']
))->setHelp('string-format: iscsi:(servername):(protocol):(port):(LUN):targetname ');

// Advanced Additional options
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

$section = new Form_Section('Additional BOOTP/DHCP Options');
$section->addClass('adnlopts');

$section->addInput(new Form_StaticText(
	null,
	'<div class="alert alert-info"> ' . gettext('Enter the DHCP option number and the value for each item to include in the DHCP lease information.') . ' ' .
	sprintf(gettext('For a list of available options please visit this %1$s URL%2$s.%3$s'), '<a href="http://www.iana.org/assignments/bootp-dhcp-parameters/" target="_blank">', '</a>', '</div>')
));

if (!$pconfig['numberoptions']) {
	$pconfig['numberoptions'] = array();
	$pconfig['numberoptions']['item']  = array(array('number' => '', 'type' => 'text', 'value' => ''));
}

$customitemtypes = array(
	'text' => gettext('Text'), 'string' => gettext('String'), 'boolean' => gettext('Boolean'),
	'unsigned integer 8' => gettext('Unsigned 8-bit integer'), 'unsigned integer 16' => gettext('Unsigned 16-bit integer'), 'unsigned integer 32' => gettext('Unsigned 32-bit integer'),
	'signed integer 8' => gettext('Signed 8-bit integer'), 'signed integer 16' => gettext('Signed 16-bit integer'), 'signed integer 32' => gettext('Signed 32-bit integer'), 'ip-address' => gettext('IP address or host')
);

$numrows = count($item) -1;
$counter = 0;

$numrows = count($pconfig['numberoptions']['item']) -1;

foreach ($pconfig['numberoptions']['item'] as $item) {
	$number = $item['number'];
	$itemtype = $item['type'];
	$value = base64_decode($item['value']);

	$group = new Form_Group(($counter == 0) ? 'Option':null);
	$group->addClass('repeatable');

	$group->add(new Form_Input(
		'number' . $counter,
		null,
		'number',
		$number,
		['min'=>'1', 'max'=>'254']
	))->setHelp($numrows == $counter ? 'Number':null);


	$group->add(new Form_Select(
		'itemtype' . $counter,
		null,
		$itemtype,
		$customitemtypes
	))->setWidth(3)->setHelp($numrows == $counter ? 'Type':null);

	$group->add(new Form_Input(
		'value' . $counter,
		null,
		'text',
		$value
	))->setHelp($numrows == $counter ? 'Value':null);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->addClass('btn-warning');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add',
	null,
	'fa-plus'
))->addClass('btn-success');

$form->add($section);

if ($act == "newpool") {
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

if (!is_numeric($pool) && !($act == "newpool")) {

	// Decide whether display of the Client Id column is needed.
	$got_cid = false;
	if (is_array($a_maps)) {
		foreach ($a_maps as $map) {
			if (!empty($map['cid'])) {
				$got_cid = true;
				break;
			}
		}
	}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("DHCP Static Mappings for this Interface")?></h2></div>
	<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Static ARP")?></th>
						<th><?=gettext("MAC address")?></th>
<?php
	if ($got_cid):
?>
						<th><?=gettext("Client Id")?></th>
<?php
	endif;
?>
						<th><?=gettext("IP address")?></th>
						<th><?=gettext("Hostname")?></th>
						<th><?=gettext("Description")?></th>
						<th></th>
					</tr>
				</thead>
<?php
	if (is_array($a_maps)) {
		$i = 0;
?>
				<tbody>
<?php
		foreach ($a_maps as $mapent) {
?>
					<tr>
						<td class="text-center" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?php if (isset($mapent['arp_table_static_entry'])): ?>
								<i class="fa fa-check"></i>
							<?php endif; ?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['mac'])?>
						</td>
<?php
			if ($got_cid):
?>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['cid'])?>
						</td>
<?php
			endif;
?>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['ipaddr'])?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['hostname'])?>
						</td>
						<td ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
							<?=htmlspecialchars($mapent['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Edit static mapping')?>"	href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Delete static mapping')?>"	href="services_dhcp.php?if=<?=htmlspecialchars($if)?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
		$i++;
		}
?>
				</tbody>
<?php
	}
?>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?php
}
?>

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
			    empty($pconfig['ddnsdomainkeyname']) &&
			    (empty($pconfig['ddnsdomainkeyalgorithm']) || ($pconfig['ddnsdomainkeyalgorithm'] == "hmac-md5")) &&
			    (empty($pconfig['ddnsclientupdates']) || ($pconfig['ddnsclientupdates'] == "allow")) &&
			    empty($pconfig['ddnsdomainkey'])) {
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
		hideInput('ddnsdomainkeyname', !showadvdns);
		hideInput('ddnsdomainkeyalgorithm', !showadvdns);
		hideInput('ddnsdomainkey', !showadvdns);
		hideInput('ddnsclientupdates', !showadvdns);

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

	// Show advanced MAC options ======================================================================================
	var showadvmac = false;

	function show_advmac(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['mac_allow']) && empty($pconfig['mac_deny'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvmac = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvmac = !showadvmac;
		}

		hideInput('mac_allow', !showadvmac);
		hideInput('mac_deny', !showadvmac);

		if (showadvmac) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvmac').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvmac').click(function(event) {
		show_advmac();
	});

	// Show advanced NTP options ======================================================================================
	var showadvntp = false;

	function show_advntp(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['ntp1']) && empty($pconfig['ntp2'])) {
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

	// Show advanced TFTP options ======================================================================================
	var showadvtftp = false;

	function show_advtftp(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['tftp'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvtftp = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvtftp = !showadvtftp;
		}

		hideInput('tftp', !showadvtftp);

		if (showadvtftp) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvtftp').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvtftp').click(function(event) {
		show_advtftp();
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

		hideClass('adnlopts', !showadvopts);

		if (showadvopts) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvopts').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvopts').click(function(event) {
		show_advopts();
	});

	// Show advanced Network Booting options ===========================================================================
	var showadvnwkboot = false;

	function show_advnwkboot(ispageload) {
		var text;
		// On page load decide the initial state based on the data.
		if (ispageload) {
<?php
			if (empty($pconfig['netboot'])) {
				$showadv = false;
			} else {
				$showadv = true;
			}
?>
			showadvnwkboot = <?php if ($showadv) {echo 'true';} else {echo 'false';} ?>;
		} else {
			// It was a click, swap the state.
			showadvnwkboot = !showadvnwkboot;
		}

		hideCheckbox('netboot', !showadvnwkboot);
		hideInput('nextserver', !showadvnwkboot);
		hideInput('filename', !showadvnwkboot);
		hideInput('filename32', !showadvnwkboot);
		hideInput('filename64', !showadvnwkboot);
		hideInput('rootpath', !showadvnwkboot);

		if (showadvnwkboot) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#btnadvnwkboot').html('<i class="fa fa-cog"></i> ' + text);
	}

	$('#btnadvnwkboot').click(function(event) {
		show_advnwkboot();
	});

	// ---------- On initial page load ------------------------------------------------------------

	show_advdns(true);
	show_advmac(true);
	show_advntp(true);
	show_advtftp(true);
	show_advldap(true);
	show_advopts(true);
	show_advnwkboot(true);

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();
});
//]]>
</script>

<?php include("foot.inc");
