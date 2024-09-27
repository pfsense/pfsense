<?php
/*
 * services_dhcp.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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

require_once('guiconfig.inc');
require_once('filter.inc');
require_once('rrd.inc');
require_once('shaper.inc');
require_once('util.inc');
require_once('services_dhcp.inc');

global $ddnsdomainkeyalgorithms;

if (!g_get('services_dhcp_server_enable')) {
	header("Location: /");
	exit;
}

$if = $_REQUEST['if'];
$iflist = get_configured_interface_with_descr();

/* set the starting interface */
if (!$if || !isset($iflist[$if])) {
	$found_starting_if = false;
	// First look for an interface with DHCP already enabled.
	foreach (array_keys($iflist) as $ifent) {
		if (config_path_enabled("dhcpd/{$ifent}") &&
		    is_ipaddrv4(config_get_path("interfaces/{$ifent}/ipaddr")) &&
		    ((int) config_get_path("interfaces/{$ifent}/subnet", 0) < 31)) {
			$if = $ifent;
			$found_starting_if = true;
			break;
		}
	}

	/*
	 * If there is no DHCP-enabled interface and LAN is a candidate,
	 * then choose LAN.
	 */
	if (!$found_starting_if &&
	    !empty(array_get_path($iflist, 'lan')) &&
	    is_ipaddrv4(config_get_path("interfaces/lan/ipaddr")) &&
	    ((int) config_get_path("interfaces/lan/subnet", 0) < 31)) {
		$if = 'lan';
		$found_starting_if = true;
	}

	// At the last select whatever can be found.
	$fallback = "";
	if (!$found_starting_if) {
		foreach (array_keys($iflist) as $ifent) {
			/* Not static IPv4 or subnet >= 31 */
			if (!is_ipaddrv4(config_get_path("interfaces/{$ifent}/ipaddr")) ||
			    empty(config_get_path("interfaces/{$ifent}/subnet")) ||
			    ((int) config_get_path("interfaces/{$ifent}/subnet", 0) >= 31)) {
				continue;
			} elseif (empty($fallback)) {
				/* First potential fallback in case no interfaces
				 * have DHCP enabled. */
				$fallback = $ifent;
			}

			/* If this interface has does not have DHCP enabled,
			 * skip it for now. */
			if (!config_path_enabled("dhcpd/{$ifent}")) {
				continue;
			}

			$if = $ifent;
			break;
		}
		if (empty($if) || !empty($fallback)) {
			$if = $fallback;
		}
	}
}

$act = $_REQUEST['act'];

if (!empty(config_get_path("dhcpd/{$if}"))) {
	$pool = $_REQUEST['pool'];
	if (is_numeric($_POST['pool'])) {
		$pool = $_POST['pool'];
	}

	// If we have a pool but no interface name, that's not valid. Redirect away.
	if (is_numeric($pool) && empty($if)) {
		header("Location: services_dhcp.php");
		exit;
	}

	config_init_path("dhcpd/{$if}/pool");

	if (is_numeric($pool) && config_get_path("dhcpd/{$if}/pool/{$pool}")) {
		$dhcpdconf = config_get_path("dhcpd/{$if}/pool/{$pool}");
	} elseif ($act == "newpool") {
		$dhcpdconf = array();
	} else {
		$dhcpdconf = config_get_path("dhcpd/{$if}");
	}

	array_init_path($dhcpd_if_config, "staticmap");
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
		foreach (config_get_path('dhcpd', []) as $dhcpdifitem) {
			if (empty($dhcpdifitem)) {
				continue;
			}
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

	if (isset($dhcpdconf['denyunknown'])) {
		$pconfig['denyunknown'] = empty($dhcpdconf['denyunknown']) ? "enabled" : $dhcpdconf['denyunknown'];
	} else {
		$pconfig['denyunknown'] = "disabled";
	}

	$pconfig['ignoreclientuids'] = isset($dhcpdconf['ignoreclientuids']);
	$pconfig['nonak'] = isset($dhcpdconf['nonak']);
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
	$pconfig['mac_allow'] = $dhcpdconf['mac_allow'];
	$pconfig['mac_deny'] = $dhcpdconf['mac_deny'];
	list($pconfig['ntp1'], $pconfig['ntp2'], $pconfig['ntp3'], $pconfig['ntp4']) = $dhcpdconf['ntpserver'];
	$pconfig['tftp'] = $dhcpdconf['tftp'];
	$pconfig['ldap'] = $dhcpdconf['ldap'];
	$pconfig['netboot'] = isset($dhcpdconf['netboot']);
	$pconfig['nextserver'] = $dhcpdconf['nextserver'];
	$pconfig['filename'] = $dhcpdconf['filename'];
	$pconfig['filename32'] = $dhcpdconf['filename32'];
	$pconfig['filename64'] = $dhcpdconf['filename64'];
	$pconfig['filename32arm'] = $dhcpdconf['filename32arm'];
	$pconfig['filename64arm'] = $dhcpdconf['filename64arm'];
	$pconfig['uefihttpboot'] = $dhcpdconf['uefihttpboot'];
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

$ifcfgip = config_get_path("interfaces/{$if}/ipaddr");
$ifcfgsn = config_get_path("interfaces/{$if}/subnet");

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
		if (isset($_POST["number{$x}"]) && ctype_digit(strval($_POST["number{$x}"]))) {
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

	// Default value if it's empty
	$deftime = (is_numeric($_POST['deftime'])) ? $_POST['deftime'] : 7200;
	foreach (config_get_path('captiveportal', []) as $cpZone => $cpdata) {
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
		if ($_POST['ddnsdomainprimaryport'] && !is_port($_POST['ddnsdomainprimaryport'])) {
			$input_errors[] = gettext('A valid primary DDNS port number must be specified.');
		}
		if ($_POST['ddnsdomainsecondaryport'] && !is_port($_POST['ddnsdomainsecondaryport'])) {
			$input_errors[] = gettext('A valid secondary DDNS port number must be specified.');
		}
		if (!empty($_POST['ddnsdomainsecondary']) && !is_ipaddr($_POST['ddnsdomainsecondary'])) {
			$input_errors[] = gettext("A valid secondary domain name server IP address must be specified for the dynamic domain name.");
		}
		if (dhcp_is_backend('isc')) {
			if (!$_POST['ddnsdomainkeyname'] || !$_POST['ddnsdomainkeyalgorithm'] || !$_POST['ddnsdomainkey']) {
				$input_errors[] = gettext("A valid domain key name, algorithm and secret must be specified.");
			}
		} elseif (dhcp_is_backend('kea')) {
			if (!$_POST['ddnsdomainkeyalgorithm'] || !$_POST['ddnsdomainkey']) {
				$input_errors[] = gettext("A valid domain key algorithm and secret must be specified.");
			}
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

	// Validate MACs
	if (!empty($_POST['mac_allow']) && !validate_partial_mac_list($_POST['mac_allow'])) {
		$input_errors[] = gettext("If a mac allow list is specified, it must contain only valid partial MAC addresses.");
	}
	if (!empty($_POST['mac_deny']) && !validate_partial_mac_list($_POST['mac_deny'])) {
		$input_errors[] = gettext("If a mac deny list is specified, it must contain only valid partial MAC addresses.");
	}

	if (($_POST['ntp1'] && !(is_ipaddrv4($_POST['ntp1']) || (dhcp_is_backend('isc') && is_hostname($_POST['ntp1'])))) ||
	    ($_POST['ntp2'] && !(is_ipaddrv4($_POST['ntp2']) || (dhcp_is_backend('isc') && is_hostname($_POST['ntp2'])))) ||
	    ($_POST['ntp3'] && !(is_ipaddrv4($_POST['ntp3']) || (dhcp_is_backend('isc') && is_hostname($_POST['ntp3'])))) ||
	    ($_POST['ntp4'] && !(is_ipaddrv4($_POST['ntp4']) || (dhcp_is_backend('isc') && is_hostname($_POST['ntp4']))))) {
		if (dhcp_is_backend('isc')) {
			$input_errors[] = gettext("A valid IP address or hostname must be specified for the NTP servers.");
		} else {
			$input_errors[] = gettext("A valid IP address must be specified for the NTP servers.");
		}
	}
	if ($_POST['domain'] && (!is_domain($_POST['domain'], false, false))) {
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
	foreach (config_get_path('virtualip/vip', []) as $vip) {
		if ($vip['interface'] == $if) {
			if ($vip['subnet'] && is_inrange_v4($vip['subnet'], $_POST['range_from'], $_POST['range_to'])) {
				$input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IP address %s."), $vip['subnet']);
			}
		}
	}

	$noip = false;
	foreach (config_get_path("dhcpd/{$if}/staticmap", []) as $map) {
		if (empty($map['ipaddr'])) {
			$noip = true;
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
		if ($_POST['enable'] && config_path_enabled('dhcrelay') &&
		    (stristr(config_get_path('dhcrelay/interface', ''), $if) !== false)) {
			$input_errors[] = sprintf(gettext(
			    "The DHCP relay on the %s interface must be disabled before enabling the DHCP server."),
			    $iflist[$if]);
		}

		/* If disabling DHCP Server, make sure that DHCP registration isn't enabled for DNS forwarder/resolver */
		if (!$_POST['enable']) {
			/* Find out how many other interfaces have DHCP enabled. */
			$dhcp_enabled_count = 0;
			foreach (config_get_path('dhcpd', []) as $dhif => $dhcps) {
				if ($dhif == $if) {
					/* Skip this interface, we only want to know how many others are enabled. */
					continue;
				}
				if (config_path_enabled("dhcpd/{$dhif}")) {
					$dhcp_enabled_count++;
				}
			}

			if (config_path_enabled('dnsmasq') &&
			    ($dhcp_enabled_count == 0) &&
			    (config_path_enabled('dnsmasq', 'regdhcp') ||
			    config_path_enabled('dnsmasq', 'regdhcpstatic') ||
			    config_path_enabled('dnsmasq', 'dhcpfirst'))) {
				$input_errors[] = gettext(
				    "DHCP Registration features in the DNS Forwarder are active and require at least one enabled DHCP Server.");
			}
			if (config_path_enabled('unbound') &&
			    ($dhcp_enabled_count == 0) &&
			    (config_path_enabled('unbound', 'regdhcp') ||
			    config_path_enabled('unbound', 'regdhcpstatic'))) {
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

		if (is_numeric($pool) || ($act === 'newpool')) {
			if (is_inrange_v4($_POST['range_from'],
				config_get_path("dhcpd/{$if}/range/from"),
				config_get_path("dhcpd/{$if}/range/to")) ||
				is_inrange_v4($_POST['range_to'],
				config_get_path("dhcpd/{$if}/range/from"),
				config_get_path("dhcpd/{$if}/range/to"))) {
				$input_errors[] = gettext('The specified range must not be within the primary address range for this interface.');
			}
		}

		foreach (config_get_path("dhcpd/{$if}/pool", []) as $id => $p) {
			if (is_numeric($pool) && ($id == $pool)) {
				continue;
			}

			if (is_inrange_v4($_POST['range_from'], $p['range']['from'], $p['range']['to']) ||
			    is_inrange_v4($_POST['range_to'], $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext('The specified range must not be within the range configured on another DHCP pool for this interface.');
				break;
			}
		}

		foreach (config_get_path("dhcpd/{$if}/staticmap", []) as $map) {
			if (empty($map['ipaddr'])) {
				continue;
			}
			if (is_inrange_v4($map['ipaddr'], $_POST['range_from'], $_POST['range_to'])) {
				$input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
				break;
			}
		}
	}

	if (!$input_errors) {
		if (!is_numeric($pool)) {
			if ($act == "newpool") {
				$dhcpdconf = array();
			} else {
				config_init_path("dhcpd/{$if}");
				$dhcpdconf = config_get_path("dhcpd/{$if}");
			}
		} else {
			if (is_array(config_get_path("dhcpd/{$if}/pool/{$pool}"))) {
				$dhcpdconf = config_get_path("dhcpd/{$if}/pool/{$pool}");
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

		$old_dhcpdconf = $dhcpdconf;

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
			foreach (config_get_path('dhcpd', []) as $dhcpdifkey => $keyvalue) {
				if (empty($keyvalue)) {
					continue;
				}
				if (isset($_POST['dhcpleaseinlocaltime'])) {
					config_set_path("dhcpd/{$dhcpdifkey}/dhcpleaseinlocaltime", $_POST['dhcpleaseinlocaltime']);
				} else {
					config_del_path("dhcpd/{$dhcpdifkey}/dhcpleaseinlocaltime");
				}
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

		if (in_array($_POST['denyunknown'], array("enabled", "class"))) {
			$dhcpdconf['denyunknown'] = $_POST['denyunknown'];
		} else {
			unset($dhcpdconf['denyunknown']);
		}

		$dhcpdconf['ignoreclientuids'] = ($_POST['ignoreclientuids']) ? true : false;
		$dhcpdconf['nonak'] = ($_POST['nonak']) ? true : false;
		$dhcpdconf['ddnsdomain'] = $_POST['ddnsdomain'];
		$dhcpdconf['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$dhcpdconf['ddnsdomainprimaryport'] = $_POST['ddnsdomainprimaryport'];
		$dhcpdconf['ddnsdomainsecondary'] = (!empty($_POST['ddnsdomainsecondary'])) ? $_POST['ddnsdomainsecondary'] : '';
		$dhcpdconf['ddnsdomainsecondaryport'] = $_POST['ddnsdomainsecondaryport'];
		$dhcpdconf['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$dhcpdconf['ddnsdomainkeyalgorithm'] = $_POST['ddnsdomainkeyalgorithm'];
		$dhcpdconf['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$dhcpdconf['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$dhcpdconf['ddnsforcehostname'] = ($_POST['ddnsforcehostname']) ? true : false;
		$dhcpdconf['mac_allow'] = $_POST['mac_allow'];
		$dhcpdconf['mac_deny'] = $_POST['mac_deny'];
		if ($_POST['disablepingcheck']) {
			$dhcpdconf['disablepingcheck'] = $_POST['disablepingcheck'];
		} else {
			unset($dhcpdconf['disablepingcheck']);
		}
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
		$dhcpdconf['nextserver'] = $_POST['nextserver'];
		$dhcpdconf['filename'] = $_POST['filename'];
		$dhcpdconf['filename32'] = $_POST['filename32'];
		$dhcpdconf['filename64'] = $_POST['filename64'];
		$dhcpdconf['filename32arm'] = $_POST['filename32arm'];
		$dhcpdconf['filename64arm'] = $_POST['filename64arm'];
		$dhcpdconf['uefihttpboot'] = $_POST['uefihttpboot'];
		$dhcpdconf['rootpath'] = $_POST['rootpath'];

		if (empty($_POST['statsgraph']) == isset($dhcpdconf['statsgraph'])) {
			$enable_rrd_graphing = true;
		}
		if (!empty($_POST['statsgraph'])) {
			$dhcpdconf['statsgraph'] = $_POST['statsgraph'];
		} elseif (isset($dhcpdconf['statsgraph'])) {
			unset($dhcpdconf['statsgraph']);
		}
		if ($enable_rrd_graphing) {
			enable_rrd_graphing();
		}

		// Handle the custom options rowhelper
		if (isset($dhcpdconf['numberoptions']['item'])) {
			unset($dhcpdconf['numberoptions']['item']);
		}

		$dhcpdconf['numberoptions'] = $numberoptions;

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

		if (is_numeric($pool) && is_array(config_get_path("dhcpd/{$if}/pool/{$pool}"))) {
			config_set_path("dhcpd/{$if}/pool/{$pool}", $dhcpdconf);
		} elseif ($act == "newpool") {
			config_set_path("dhcpd/{$if}/pool/", $dhcpdconf);
		} else {
			config_set_path("dhcpd/{$if}", $dhcpdconf);
		}

		mark_subsystem_dirty('dhcpd');

		write_config(gettext("DHCP Server - Settings changed for interface " . strtoupper($if)));

		/* redirect back to the primary pool when creating/saving an additional address pool */
		if (is_numeric($pool) || ($act === 'newpool')) {
			header('Location: /services_dhcp.php?if='.$if);
		}
	}
}

if (isset($_POST['apply'])) {
	$changes_applied = true;
	$retval = 0;
	$retvaldhcp = 0;
	$retvaldns = 0;
	/* dnsmasq_configure calls dhcpd_configure */
	/* no need to restart dhcpd twice */
	if (config_path_enabled('dnsmasq') &&
	    config_path_enabled('dnsmasq', 'regdhcpstatic') &&
	    dhcp_is_backend('isc')) {
		$retvaldns |= services_dnsmasq_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd');
		}
	} elseif (config_path_enabled('unbound') &&
		   config_path_enabled('unbound', 'regdhcpstatic') &&
		   dhcp_is_backend('isc')) {
		$retvaldns |= services_unbound_configure();
		if ($retvaldns == 0) {
			clear_subsystem_dirty('unbound');

			clear_subsystem_dirty('hosts');
			clear_subsystem_dirty('dhcpd');
		}
	} else {
		$retvaldhcp |= services_dhcpd_configure();
		if ($retvaldhcp == 0) {
			clear_subsystem_dirty('dhcpd');
		}
	}
	/* BIND package - Bug #3710 */
	if (!function_exists('is_package_installed')) {
		require_once('pkg-utils.inc');
	}
	if (is_package_installed('pfSense-pkg-bind') &&
	    config_path_enabled('installedpackages/bind/config/0', 'enable_bind') &&
	    dhcp_is_backend('isc')) {
		$reloadbind = false;
		$bindzone = config_get_path('installedpackages/bindzone/config', []);

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
	if (config_get_path("dhcpd/{$if}/pool/{$_POST['id']}")) {
		config_del_path("dhcpd/{$if}/pool/{$_POST['id']}");
		write_config("DHCP Server pool deleted");
		mark_subsystem_dirty('dhcpd');
		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

if ($act == "del") {
	if (config_get_path("dhcpd/{$if}/staticmap/{$_POST['id']}") !== null) {
		/* Remove static ARP entry, if necessary */
		if (config_get_path("dhcpd/{$if}/staticmap/{$_POST['id']}/arp_table_static_entry") !== null) {
			mwexec("/usr/sbin/arp -d " . escapeshellarg(config_get_path("dhcpd/{$if}/staticmap/{$_POST['id']}/ipaddr")));
		}
		config_del_path("dhcpd/{$if}/staticmap/{$_POST['id']}");
		write_config("DHCP Server static map deleted");
		if (config_path_enabled("dhcpd/{$if}")) {
			mark_subsystem_dirty('dhcpd');
			if (config_path_enabled('dnsmasq') && config_get_path('dnsmasq/regdhcpstatic', false)) {
				mark_subsystem_dirty('hosts');
			}
		}

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

// Build an HTML table that can be inserted into a Form_StaticText element
function build_pooltable() {
	global $if;

	$pooltbl =	'<div class="contains-table table-responsive">';
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
	foreach (config_get_path("dhcpd/{$if}/pool", []) as $poolent) {
		if (!empty($poolent['range']['from']) && !empty($poolent['range']['to'])) {
			$pooltbl .= '<tr>';
			$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['range']['from']) . '</td>';

			$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['range']['to']) . '</td>';

			$pooltbl .= '<td ondblclick="document.location=\'services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '\';">' .
						htmlspecialchars($poolent['descr']) . '</td>';

			$pooltbl .= '<td><a class="fa-solid fa-pencil" title="'. gettext("Edit pool") . '" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&pool=' . $i . '"></a>';

			$pooltbl .= ' <a class="fa-solid fa-trash-can text-danger" title="'. gettext("Delete pool") . '" href="services_dhcp.php?if=' . htmlspecialchars($if) . '&act=delpool&id=' . $i . '" usepost></a></td>';
			$pooltbl .= '</tr>';
		}
		$i++;
	}

	$pooltbl .=			'</tbody>';
	$pooltbl .=		'</table>';
	$pooltbl .= '</div>';

	return($pooltbl);
}

$pgtitle = array(gettext("Services"), gettext("DHCP Server"));
$pglinks = array("", "services_dhcp_settings.php");

if (!empty($if) && isset($iflist[$if])) {
	$pgtitle[] = $iflist[$if];
	$pglinks[] = '/services_dhcp.php?if='.$if;

	if (is_numeric($pool) || ($act === 'newpool')) {
		$pgtitle[] = gettext('Address Pool');
		$pglinks[] = '@self';
		$pgtitle[] = gettext('Edit');
		$pglinks[] = '@self';
	}
}

$shortcut_section = 'dhcp';
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp4';
}

include('head.inc');

if (config_path_enabled('dhcrelay')) {
	print_info_box(gettext('DHCP Relay is currently enabled. DHCP Server canot be enabled while the DHCP Relay is enabled on any interface.'), 'danger', false);
}

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('dhcpd')) {
	print_apply_box(gettext('The DHCP Server configuration has changed.') . '<br />' . gettext('The changes must be applied for them to take effect.'));
}

display_isc_warning();

/* active tabs */
$tab_array = array();
$tabscounter = 0;
$i = 0;
$have_small_subnet = false;

if (dhcp_is_backend('kea')) {
	$tab_array[] = [gettext('Settings'), false, 'services_dhcp_settings.php'];
}

foreach ($iflist as $ifent => $ifname) {
	$oc = config_get_path("interfaces/{$ifent}");

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

if (!is_numeric($pool) && !($act == "newpool")) {
	if (config_path_enabled('dhcrelay')) {
		$section->addInput(new Form_Checkbox(
			'enable',
			gettext('Enable'),
			gettext("DHCP Relay is currently enabled. DHCP Server canot be enabled while the DHCP Relay is enabled on any interface."),
			$pconfig['enable']
		))->setAttribute('disabled', true);
	} else {
		$section->addInput(new Form_Checkbox(
			'enable',
			gettext('Enable'),
			sprintf(gettext("Enable DHCP server on %s interface"), $iflist[$if]),
			$pconfig['enable']
		));
	}
} else {
	print_info_box(gettext('Editing pool-specific options. To return to the Interface, click its tab above.'), 'info', false);
}

if (dhcp_is_backend('isc')):
$section->addInput(new Form_Checkbox(
	'ignorebootp',
	'BOOTP',
	'Ignore BOOTP queries',
	$pconfig['ignorebootp']
));
endif; /* dhcp_is_backend('isc') */

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
	'If set to %3$sAllow known clients from any interface%4$s, any DHCP client with a MAC address listed in a static mapping on %1$s%3$sany%4$s%2$s scope(s)/interface(s) will get an IP address. ' .
	'If set to %3$sAllow known clients from only this interface%4$s, only MAC addresses listed in static mappings on this interface will get an IP address within this scope/range.'),
	'<i>', '</i>', '<b>', '</b>');

if (dhcp_is_backend('isc')):
$section->addInput(new Form_Checkbox(
	'nonak',
	gettext('Ignore Denied Clients'),
	'Ignore denied clients rather than reject',
	$pconfig['nonak']
))->setHelp(gettext('This option is not compatible with failover and cannot be enabled when a Failover Peer IP address is configured.'));
endif; /* dhcp_is_backend('isc') */

if (dhcp_is_backend('isc') ||
    (dhcp_is_backend('kea') && (!is_numeric($pool) && !($act === 'newpool')))):
$section->addInput(new Form_Checkbox(
	'ignoreclientuids',
	gettext('Ignore Client Identifiers'),
	gettext('Do not record a unique identifier (UID) in client lease data if present in the client DHCP request'),
	$pconfig['ignoreclientuids']
))->setHelp(gettext('This option may be useful when a client can dual boot using different client identifiers but the same hardware (MAC) address.  Note that the resulting server behavior violates the official DHCP specification.'));
endif;

if (is_numeric($pool) || ($act == "newpool")) {
	$section->addInput(new Form_Input(
		'descr',
		gettext('Description'),
		'text',
		$pconfig['descr']
	))->setHelp(gettext('Description for administrative reference (not parsed).'));
}

$form->add($section);

$pool_title = gettext('Primary Address Pool');
if (is_numeric($pool) || ($act === 'newpool')) {
	$pool_title = gettext('Additional Address Pool');
}

$section = new Form_Section($pool_title);

$section->addInput(new Form_StaticText(
	gettext('Subnet'),
	gen_subnet($ifcfgip, $ifcfgsn) . '/' . $ifcfgsn
));

$section->addInput(new Form_StaticText(
	gettext('Subnet Range'),
	sprintf('%s - %s', ip_after($subnet_start), ip_before($subnet_end))
));

if (is_numeric($pool) || ($act === 'newpool')) {
	$ranges = [];
	$subnet_range = config_get_path('dhcpd/'.$if.'/range', []);
	if (!empty($subnet_range)) {
		$subnet_range['descr'] = gettext('Primary Pool');
		$ranges[] = $subnet_range;
	}

	foreach (config_get_path("dhcpd/{$if}/pool", []) as $p) {
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

$group = new Form_Group('*'.gettext('Address Pool Range'));

$group->add(new Form_IpAddress(
	'range_from',
	null,
	$pconfig['range_from'],
	'V4'
))->addClass('autotrim')
  ->setHelp(gettext('From'));

$group->add(new Form_IpAddress(
	'range_to',
	null,
	$pconfig['range_to'],
	'V4'
))->addClass('autotrim')
  ->setHelp(gettext('To'));

$group->setHelp(gettext('The specified range for this pool must not be within the range configured on any other address pool for this interface.'));
$section->add($group);

if (!is_numeric($pool) && !($act == "newpool")) {
	$has_pools = false;
	if (isset($if) && (count(config_get_path("dhcpd/{$if}/pool", [])) > 0)) {
		$section->addInput(new Form_StaticText(
			gettext('Additional Pools'),
			build_pooltable()
		));
		$has_pools = true;
	}

	$btnaddpool = new Form_Button(
		'btnaddpool',
		gettext('Add Address Pool'),
		'services_dhcp.php?if=' . $if . '&act=newpool',
		'fa-solid fa-plus'
	);
	$btnaddpool->addClass('btn-success');

	$section->addInput(new Form_StaticText(
		(!$has_pools ? gettext('Additional Pools') : null),
		$btnaddpool
	))->setHelp(gettext('If additional pools of addresses are needed inside of this subnet outside the above range, they may be specified here.'));
}

$form->add($section);

$section = new Form_Section(gettext('Server Options'));

$section->addInput(new Form_IpAddress(
	'wins1',
	gettext('WINS Servers'),
	$pconfig['wins1'],
	'V4'
))->addClass('autotrim')
  ->setAttribute('placeholder', gettext('WINS Server 1'));

$section->addInput(new Form_IpAddress(
	'wins2',
	null,
	$pconfig['wins2'],
	'V4'
))->addClass('autotrim')
  ->setAttribute('placeholder', gettext('WINS Server 2'));

$ifip = get_interface_ip($if);

/* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
$dns_arrv4 = [];
foreach (config_get_path('system/dnsserver', []) as $dnsserver) {
	if (is_ipaddrv4($dnsserver)) {
		$dns_arrv4[] = $dnsserver;
	}
}

/* prefer the interface IP if dnsmasq or unbound is enabled */
if (config_path_enabled('dnsmasq') ||
    config_path_enabled('unbound')) {
    	$dns_arrv4 = [$ifip];
}

/* additional pools should inherit from the subnet/primary pool */
if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_dnsservers = config_get_path('dhcpd/'.$if.'/dnsserver', []);
	if (!empty($subnet_dnsservers)) {
		$dns_arrv4 = $subnet_dnsservers;
	}
}

for ($idx = 1; $idx <= 4; $idx++) {
	$last = $section->addInput(new Form_IpAddress(
		'dns' . $idx,
		($idx == 1) ? gettext('DNS Servers') : null,
		$pconfig['dns' . $idx],
		'V4'
	))->addClass('autotrim')
	  ->setAttribute('placeholder', $dns_arrv4[$idx - 1] ?? sprintf(gettext('DNS Server %s'), $idx));
}
$last->setHelp(($idx == 4) ? gettext('Leave blank to use the IP address of this firewall interface if DNS Resolver or Forwarder is enabled, the servers configured in General settings or those obtained dynamically.') : '');

$form->add($section);

// OMAPI
if (dhcp_is_backend('isc')):
$section = new Form_Section('OMAPI');

$section->addInput(new Form_Input(
	'omapi_port',
	'OMAPI Port',
	'text',
	$pconfig['omapi_port']
))->setAttribute('placeholder', 'OMAPI Port')
  ->setHelp('Set the port that OMAPI will listen on. The default port is 7911, leave blank to disable.' .
	    'Only the first OMAPI configuration is used.');

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
endif; /* dhcp_is_backend('isc') */

$section = new Form_Section(gettext('Other DHCP Options'));

/* the interface address has lowest priority */
$gateway_holder = $ifip;

/* additional pools should inherit from subnet/primary pool */
if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_gateway = config_get_path('dhcpd/'.$if.'/gateway');
	if (!empty($subnet_gateway)) {
		$gateway_holder = $subnet_gateway;
	}
}

$section->addInput(new Form_IpAddress(
	'gateway',
	gettext('Gateway'),
	$pconfig['gateway'],
	'V4'
))->addClass('autotrim')
  ->setPattern('[.a-zA-Z0-9_]+')
  ->setAttribute('placeholder', $gateway_holder)
  ->setHelp(gettext('The default is to use the IP address of this firewall interface as the gateway. Specify an alternate gateway here if this is not the correct gateway for the network. Enter "none" for no gateway assignment.'));

/* the system domain name has lowest priority */
$domain_holder = config_get_path('system/domain');

/* additional pools should inherit from subnet/primary pool */
if (is_numeric($pool) || ($act === 'newpool')) {
	$subnet_domain = config_get_path('dhcpd/'.$if.'/domain');
	if (!empty($subnet_domain)) {
		$domain_holder = $subnet_domain;
	}
}

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
	'number',
	$pconfig['deftime']
))->setAttribute('placeholder', '7200')
  ->setHelp(gettext('This is used for clients that do not ask for a specific expiration time. The default is 7200 seconds.'));

$section->addInput(new Form_Input(
	'maxtime',
	gettext('Maximum Lease Time'),
	'number',
	$pconfig['maxtime']
))->setAttribute('placeholder', '86400')
  ->setHelp(gettext('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds.'));
endif;

if (!is_numeric($pool) && !($act == "newpool")) {
if (dhcp_is_backend('isc')):
	$section->addInput(new Form_IpAddress(
		'failover_peerip',
		'Failover peer IP',
		$pconfig['failover_peerip'],
		'V4'
	))->setHelp('Leave blank to disable. Enter the interface IP address of the other firewall (failover peer) in this subnet. Firewalls must be using CARP. ' .
			'Advertising skew of the CARP VIP on this interface determines whether the DHCP daemon is Primary or Secondary. ' .
			'Ensure the advertising skew for the VIP on one firewall is &lt; 20 and the other is &gt; 20.');

	$section->addInput(new Form_Checkbox(
		'staticarp',
		'Static ARP',
		'Enable Static ARP entries',
		$pconfig['staticarp']
	))->setHelp('Restricts communication with the firewall to only hosts listed in static mappings containing both IP addresses and MAC addresses. ' .
			'No other hosts will be able to communicate with the firewall on this interface. ' .
			'This behavior is enforced even when DHCP server is disabled.');

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
		'Enable monitoring graphs for DHCP lease statistics',
		$pconfig['statsgraph']
	))->setHelp('Enable this to add DHCP leases statistics to the Monitoring graphs. Disabled by default.');

	$section->addInput(new Form_Checkbox(
		'disablepingcheck',
		'Ping check',
		'Disable ping check',
		$pconfig['disablepingcheck']
	))->setHelp('When enabled dhcpd sends a ping to the address being assigned, and if no response has been heard, it assigns the address. Enabled by default.');
endif; /* dhcp_is_backend('isc') */
}

if (dhcp_is_backend('isc')):
// DDNS
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
	gettext('Enable'),
	gettext('Enable DDNS registration of DHCP clients'),
	$pconfig['ddnsupdate']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	gettext('DDNS Domain'),
	'text',
	$pconfig['ddnsdomain']
))->setAttribute('placeholder', $domain_holder)
  ->setHelp(gettext('Enter the dynamic DNS domain which will be used to register client names in the DNS server.'));

$section->addInput(new Form_Checkbox(
	'ddnsforcehostname',
	gettext('DDNS Hostnames'),
	gettext('Force dynamic DNS hostname to be the same as configured hostname for Static Mappings'),
	$pconfig['ddnsforcehostname']
))->setHelp(gettext('Default registers host name option supplied by DHCP client.'));

$group = new Form_Group(gettext('Primary DDNS Server'));

$group->add(new Form_IpAddress(
	'ddnsdomainprimary',
	gettext('Primary DDNS Server'),
	$pconfig['ddnsdomainprimary'],
	'BOTH'
))->setHelp('Primary domain name server IPv4 address.');

$group->add(new Form_Input(
	'ddnsdomainprimaryport',
	'53',
	'text',
	$pconfig['ddnsdomainprimaryport'],
))->setHelp(gettext('The port on which the server listens for DDNS requests.'));

$section->add($group);

$group = new Form_Group(gettext('Secondary DDNS Server'));
$group->add(new Form_IpAddress(
	'ddnsdomainsecondary',
	gettext('Secondary DDNS Server'),
	$pconfig['ddnsdomainsecondary'],
	'BOTH'
))->setHelp(gettext('Secondary domain name server IPv4 address.'));

$group->add(new Form_Input(
	'ddnsdomainsecondaryport',
	'53',
	'text',
	$pconfig['ddnsdomainsecondaryport'],
))->setHelp(gettext('The port on which the server listens for DDNS requests.'));

$section->add($group);

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	gettext('DNS Domain Key'),
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp(gettext('Dynamic DNS domain key name which will be used to register client names in the DNS server.'));

$section->addInput(new Form_Select(
	'ddnsdomainkeyalgorithm',
	gettext('Key Algorithm'),
	$pconfig['ddnsdomainkeyalgorithm'],
	$ddnsdomainkeyalgorithms
));

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	gettext('DNS Domain Key Secret'),
	'text',
	$pconfig['ddnsdomainkey']
))->setAttribute('placeholder', gettext('base64 encoded string'))
->setHelp(gettext('Dynamic DNS domain key secret which will be used to register client names in the DNS server.'));

$section->addInput(new Form_Select(
	'ddnsclientupdates',
	gettext('DDNS Client Updates'),
	$pconfig['ddnsclientupdates'],
	array(
	    'allow' => gettext('Allow'),
	    'deny' => gettext('Deny'),
	    'ignore' => gettext('Ignore'))
))->setHelp(gettext('How Forward entries are handled when client indicates they wish to update DNS.  ' .
	    'Allow prevents DHCP from updating Forward entries, Deny indicates that DHCP will ' .
	    'do the updates and the client should not, Ignore specifies that DHCP will do the ' .
	    'update and the client can also attempt the update usually using a different domain name.'));
endif; /* dhcp_is_backend('isc') */

// Advanced MAC
$btnadv = new Form_Button(
	'btnadvmac',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('MAC Address Control'),
	$btnadv
));

$mac_placeholder = '00:11:22:33:44:55,66:77:88:99,AA';
$section->addInput(new Form_Input(
	'mac_allow',
	gettext('MAC Allow'),
	'text',
	$pconfig['mac_allow']
))->addClass('autotrim')
  ->setAttribute('placeholder', $mac_placeholder)
  ->setHelp(gettext('List of full or partial MAC addresses to allow access in this scope/pool. Implicitly denies any MACs not listed. Does not define known/unknown clients. Enter addresses as comma separated without spaces.'));

$section->addInput(new Form_Input(
	'mac_deny',
	gettext('MAC Deny'),
	'text',
	$pconfig['mac_deny']
))->addClass('autotrim')
  ->setAttribute('placeholder', $mac_placeholder)
  ->setHelp(gettext('List of full or partial MAC addresses to deny access in this scope/pool. Implicitly allows any MACs not listed. Does not define known/unknown clients. Enter addresses as comma separated without spaces.'));

// Advanced NTP
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
	'HOSTV4'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[0] ?? gettext('NTP Server 1'));

$section->addInput(new Form_IpAddress(
	'ntp2',
	gettext('NTP Server 2'),
	$pconfig['ntp2'],
	'HOSTV4'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[1] ?? gettext('NTP Server 2'));

$section->addInput(new Form_IpAddress(
	'ntp3',
	gettext('NTP Server 3'),
	$pconfig['ntp3'],
	'HOSTV4'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[2] ?? gettext('NTP Server 3'));

$section->addInput(new Form_IpAddress(
	'ntp4',
	gettext('NTP Server 4'),
	$pconfig['ntp4'],
	'HOSTV4'
))->addClass('autotrim')
  ->setAttribute('placeholder', $ntp_holder[3] ?? gettext('NTP Server 4'));

// Advanced TFTP
$btnadv = new Form_Button(
	'btnadvtftp',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('TFTP'),
	$btnadv
));

$section->addInput(new Form_Input(
	'tftp',
	gettext('TFTP Server'),
	'text',
	$pconfig['tftp']
))->addClass('autotrim')
  ->setAttribute('placeholder', gettext('TFTP Server'))
  ->setHelp(gettext('Leave blank to disable. Enter a valid IP address, hostname or URL for the TFTP server.'));

// Advanced LDAP
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

// Advanced Network Booting options
$btnadv = new Form_Button(
	'btnadvnwkboot',
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

if (dhcp_is_backend('isc') ||
    (dhcp_is_backend('kea') && (!is_numeric($pool) && !($act === 'newpool')))):
$section->addInput(new Form_IpAddress(
	'nextserver',
	gettext('Next Server'),
	$pconfig['nextserver'],
	'V4'
))->setHelp(gettext('Enter the IPv4 address of the next server'));
endif;

$section->addInput(new Form_Input(
	'filename',
	gettext('Default BIOS File Name'),
	'text',
	$pconfig['filename']
));

$section->addInput(new Form_Input(
	'filename32',
	gettext('UEFI 32 bit File Name'),
	'text',
	$pconfig['filename32']
));

$section->addInput(new Form_Input(
	'filename64',
	gettext('UEFI 64 bit File Name'),
	'text',
	$pconfig['filename64']
));

$section->addInput(new Form_Input(
	'filename32arm',
	gettext('ARM 32 bit File Name'),
	'text',
	$pconfig['filename32arm']
));

$section->addInput(new Form_Input(
	'filename64arm',
	gettext('ARM 64 bit File Name'),
	'text',
	$pconfig['filename64arm']
))->setHelp(gettext('Both a filename and a boot server must be configured for this to work! ' .
			'All five filenames and a configured boot server are necessary for UEFI & ARM to work! '));

$section->addInput(new Form_Input(
	'uefihttpboot',
	gettext('UEFI HTTPBoot URL'),
	'text',
	$pconfig['uefihttpboot']
))->setHelp('string-format: http://(servername)/(firmwarepath)');

$section->addInput(new Form_Input(
	'rootpath',
	gettext('Root Path'),
	'text',
	$pconfig['rootpath']
))->setHelp('string-format: iscsi:(servername):(protocol):(port):(LUN):targetname ');

if (dhcp_is_backend('isc')):
// Advanced Additional options
$btnadv = new Form_Button(
	'btnadvopts',
	gettext('Display Advanced'),
	null,
	'fa-solid fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	gettext('Custom DHCP Options'),
	$btnadv
));

$form->add($section);

$section = new Form_Section(gettext('Custom DHCP Options'));
$section->addClass('adnlopts');

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

	$group = new Form_Group(($counter == 0) ? gettext('Custom Option') : null);
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
		'fa-solid fa-trash-can'
	))->addClass('btn-sm btn-warning');

	$section->add($group);

	$counter++;
}

$group = new Form_Group(null);
$group->add(new Form_Button(
	'addrow',
	gettext('Add Custom Option'),
	null,
	'fa-solid fa-plus'
))->addClass('btn-success')
  ->setHelp(gettext('Enter the DHCP option number, type and the value for each item to include in the DHCP lease information.'));
$section->add($group);
endif; /* dhcp_is_backend(isc') */

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
	foreach (config_get_path("dhcpd/{$if}/staticmap", []) as $map) {
		if (!empty($map['cid'])) {
			$got_cid = true;
			break;
		}
	}
?>

<div class="panel panel-default">
<?php
	$title = gettext('DHCP Static Mappings');
?>
	<div class="panel-heading"><h2 class="panel-title"><?=$title?></h2></div>
	<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Static ARP")?></th>
						<th><?=gettext("MAC Address")?></th>
<?php
	if ($got_cid):
?>
						<th><?=gettext("Client ID")?></th>
<?php
	endif;
?>
						<th><?=gettext("IP Address")?></th>
						<th><?=gettext("Hostname")?></th>
						<th><?=gettext("Description")?></th>
						<th></th>
					</tr>
				</thead>
<?php
	$i = 0;
?>
			<tbody>
<?php
	foreach (config_get_path("dhcpd/{$if}/staticmap", []) as $mapent) {
?>
				<tr>
					<td class="text-center" ondblclick="document.location='services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>';">
						<?php if (isset($mapent['arp_table_static_entry'])): ?>
							<i class="fa-solid fa-check"></i>
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
						<a class="fa-solid fa-pencil" title="<?=gettext('Edit static mapping')?>"	href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>&amp;id=<?=$i?>"></a>
						<a class="fa-solid fa-trash-can text-danger" title="<?=gettext('Delete static mapping')?>"	href="services_dhcp.php?if=<?=htmlspecialchars($if)?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
		$i++;
	}
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if)?>" class="btn btn-success">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext('Add Static Mapping')?>
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
				empty($pconfig['ddnsdomainsecondary']) &&
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
		hideInput('ddnsdomainsecondary', !showadvdns);
		hideInput('ddnsdomainkeyname', !showadvdns);
		hideInput('ddnsdomainkeyalgorithm', !showadvdns);
		hideInput('ddnsdomainkey', !showadvdns);
		hideInput('ddnsclientupdates', !showadvdns);

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
		var children = $('#btnadvmac').children();
		$('#btnadvmac').text(text).prepend(children);
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
			if (empty($pconfig['ntp1']) && empty($pconfig['ntp2']) && empty($pconfig['ntp3']) ) {
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
		var children = $('#btnadvtftp').children();
		$('#btnadvtftp').text(text).prepend(children);
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
		var children = $('#btnadvldap').children();
		$('#btnadvldap').text(text).prepend(children);
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
		var children = $('#btnadvopts').children();
		$('#btnadvopts').text(text).prepend(children);
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
		hideInput('filename32arm', !showadvnwkboot);
		hideInput('filename64arm', !showadvnwkboot);
		hideInput('uefihttpboot', !showadvnwkboot);
		hideInput('rootpath', !showadvnwkboot);

		if (showadvnwkboot) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		var children = $('#btnadvnwkboot').children();
		$('#btnadvnwkboot').text(text).prepend(children);
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

<?php
include('foot.inc');
