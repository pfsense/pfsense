<?php
/*
 * services_dhcp_edit.php
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
##|*IDENT=page-services-dhcpserver-editstaticmapping
##|*NAME=Services: DHCP Server: Edit static mapping
##|*DESCR=Allow access to the 'Services: DHCP Server: Edit static mapping' page.
##|*MATCH=services_dhcp_edit.php*
##|-PRIV

global $ddnsdomainkeyalgorithms;

function staticmapcmp($a, $b) {
	return ipcmp($a['ipaddr'], $b['ipaddr']);
}

function staticmaps_sort($ifgui) {
	global $g;

	$dhcpd_config = config_get_path("dhcpd/{$ifgui}/staticmap");
	usort($dhcpd_config, "staticmapcmp");
	config_set_path("dhcpd/{$ifgui}/staticmap", $dhcpd_config);
}

require_once('globals.inc');

if (!g_get('services_dhcp_server_enable')) {
	header("Location: /");
	exit;
}

require_once("guiconfig.inc");

$if = $_REQUEST['if'];

if (!$if) {
	header("Location: services_dhcp.php");
	exit;
}

config_init_path("dhcpd/{$if}/staticmap");
config_init_path("dhcpd/{$if}/pool");
$static_arp_enabled = config_path_enabled("dhcpd/{$if}", 'staticarp');
$ifcfgip = get_interface_ip($if);
$ifcfgsn = get_interface_subnet($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

$id = is_numericint($_REQUEST['id']) ? $_REQUEST['id'] : null;

$this_map_config = isset($id) ? config_get_path("dhcpd/{$if}/staticmap/{$id}") : null;
if ($this_map_config) {
	$pconfig['mac'] = $this_map_config['mac'];
	$pconfig['cid'] = $this_map_config['cid'];
	$pconfig['hostname'] = $this_map_config['hostname'];
	$pconfig['ipaddr'] = $this_map_config['ipaddr'];
	$pconfig['filename'] = $this_map_config['filename'];
	$pconfig['rootpath'] = $this_map_config['rootpath'];
	$pconfig['descr'] = $this_map_config['descr'];
	$pconfig['arp_table_static_entry'] = isset($this_map_config['arp_table_static_entry']);
	$pconfig['deftime'] = $this_map_config['defaultleasetime'];
	$pconfig['maxtime'] = $this_map_config['maxleasetime'];
	$pconfig['gateway'] = $this_map_config['gateway'];
	$pconfig['domain'] = $this_map_config['domain'];
	$pconfig['domainsearchlist'] = $this_map_config['domainsearchlist'];
	list($pconfig['wins1'], $pconfig['wins2']) = $this_map_config['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $this_map_config['dnsserver'];
	$pconfig['ddnsdomain'] = $this_map_config['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $this_map_config['ddnsdomainprimary'];
	$pconfig['ddnsdomainsecondary'] = $this_map_config['ddnsdomainsecondary'];
	$pconfig['ddnsdomainkeyname'] = $this_map_config['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $this_map_config['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $this_map_config['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($this_map_config['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($this_map_config['ddnsforcehostname']);
	list($pconfig['ntp1'], $pconfig['ntp2'], $pconfig['ntp3']) = $this_map_config['ntpserver'];
	$pconfig['tftp'] = $this_map_config['tftp'];
	$pconfig['ldap'] = $this_map_config['ldap'];
	$pconfig['netboot'] = isset($this_map_config['netboot']);
	$pconfig['nextserver'] = $this_map_config['nextserver'];
	$pconfig['filename32'] = $this_map_config['filename32'];
	$pconfig['filename64'] = $this_map_config['filename64'];
	$pconfig['filename32arm'] = $this_map_config['filename32arm'];
	$pconfig['filename64arm'] = $this_map_config['filename64arm'];
	$pconfig['uefihttpboot'] = $this_map_config['uefihttpboot'];
	$pconfig['netmask'] = $this_map_config['netmask'];
	$pconfig['numberoptions'] = $this_map_config['numberoptions'];
} else {
	$pconfig['mac'] = $_REQUEST['mac'];
	$pconfig['cid'] = $_REQUEST['cid'];
	$pconfig['hostname'] = $_REQUEST['hostname'];
	$pconfig['filename'] = $_REQUEST['filename'];
	$pconfig['rootpath'] = $_REQUEST['rootpath'];
	$pconfig['descr'] = $_REQUEST['descr'];
	$pconfig['arp_table_static_entry'] = $_REQUEST['arp_table_static_entry'];
	$pconfig['deftime'] = $_REQUEST['defaultleasetime'];
	$pconfig['maxtime'] = $_REQUEST['maxleasetime'];
	$pconfig['gateway'] = $_REQUEST['gateway'];
	$pconfig['domain'] = $_REQUEST['domain'];
	$pconfig['domainsearchlist'] = $_REQUEST['domainsearchlist'];
	$pconfig['wins1'] = $_REQUEST['wins1'];
	$pconfig['wins2'] = $_REQUEST['wins2'];
	$pconfig['dns1'] = $_REQUEST['dns1'];
	$pconfig['dns2'] = $_REQUEST['dns2'];
	$pconfig['dns3'] = $_REQUEST['dns3'];
	$pconfig['dns4'] = $_REQUEST['dns4'];
	$pconfig['ddnsdomain'] = $_REQUEST['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $_REQUEST['ddnsdomainprimary'];
	$pconfig['ddnsdomainsecondary'] = $_REQUEST['ddnsdomainsecondary'];
	$pconfig['ddnsdomainkeyname'] = $_REQUEST['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $_REQUEST['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $_REQUEST['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($_REQUEST['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($_REQUEST['ddnsforcehostname']);
	$pconfig['ntp1'] = $_REQUEST['ntp1'];
	$pconfig['ntp2'] = $_REQUEST['ntp2'];
	$pconfig['ntp3'] = $_REQUEST['ntp3'];
	$pconfig['tftp'] = $_REQUEST['tftp'];
	$pconfig['ldap'] = $_REQUEST['ldap'];
	$pconfig['netboot'] = isset($_REQUEST['netboot']);
	$pconfig['nextserver'] = $_REQUEST['nextserver'];
	$pconfig['filename'] = $_REQUEST['filename'];
	$pconfig['filename32'] = $_REQUEST['filename32'];
	$pconfig['filename64'] = $_REQUEST['filename64'];
	$pconfig['filename32arm'] = $_REQUEST['filename32arm'];
	$pconfig['filename64arm'] = $_REQUEST['filename64arm'];
	$pconfig['uefihttpboot'] = $_REQUEST['uefihttpboot'];
	$pconfig['rootpath'] = $_REQUEST['rootpath'];
	$pconfig['netmask'] = $_REQUEST['netmask'];
	$pconfig['numberoptions'] = $_REQUEST['numberoptions'];
}

if ($_POST['save']) {
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
	$reqdfields = array();
	$reqdfieldsn = array();

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* either MAC or Client-ID must be specified */
	if (empty($_POST['mac']) && empty($_POST['cid'])) {
		$input_errors[] = gettext("Either MAC address or Client identifier must be specified");
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['mac'] = trim(strtolower(str_replace("-", ":", $_POST['mac'])));

	if ($_POST['hostname']) {
		preg_match("/\-\$/", $_POST['hostname'], $matches);
		if ($matches) {
			$input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");
		}
		if (!is_hostname($_POST['hostname'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['hostname'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}

	if (($_POST['ipaddr'] && !is_ipaddrv4($_POST['ipaddr']))) {
		$input_errors[] = gettext("A valid IPv4 address must be specified.");
	}

	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if ($static_arp_enabled && !$_POST['ipaddr']) {
		$input_errors[] = gettext("Static ARP is enabled.  An IP address must be specified.");
	}

	/* check for overlaps */
	foreach (config_get_path("dhcpd/{$if}/staticmap", []) as $mapent) {
		if ($this_map_config && ($this_map_config === $mapent)) {
			continue;
		}
		if ((($mapent['mac'] == $_POST['mac']) && $mapent['mac']) ||
		    (($mapent['cid'] == $_POST['cid']) && $mapent['cid'])) {
			$input_errors[] = gettext("This MAC address or Client identifier already exists.");
			break;
		}
		if (($mapent['ipaddr'] == $_POST['ipaddr']) && $mapent['ipaddr']) {
			set_flash_message('alert-info', sprintf(gettext('The IP address %1$s is in use by another static DHCP mapping. ' .
			'This has the potential to cause an IP conflict.'), $mapent['ipaddr']));
			break;
		}
	}

	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddr']) {
		if (is_inrange_v4($_POST['ipaddr'], config_get_path("dhcpd/{$if}/range/from"), config_get_path("dhcpd/{$if}/range/to"))) {
			$input_errors[] = sprintf(gettext("The IP address must not be within the DHCP range for this interface."));
		}

		foreach (config_get_path("dhcpd/{$if}/pool", []) as $p) {
			if (is_inrange_v4($_POST['ipaddr'], $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext("The IP address must not be within the range configured on a DHCP pool for this interface.");
				break;
			}
		}

		$lansubnet_start = gen_subnetv4($ifcfgip, $ifcfgsn);
		$lansubnet_end = gen_subnetv4_max($ifcfgip, $ifcfgsn);
		if (!is_inrange_v4($_POST['ipaddr'], $lansubnet_start, $lansubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must lie in the %s subnet."), $ifcfgdescr);
		}

		if ($_POST['ipaddr'] == $lansubnet_start) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s network address."), $ifcfgdescr);
		}

		if ($_POST['ipaddr'] == $lansubnet_end) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s broadcast address."), $ifcfgdescr);
		}
	}

	if (($_POST['gateway'] && !is_ipaddrv4($_POST['gateway']))) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for the gateway.");
	}
	if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2']))) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for the primary/secondary WINS servers.");
	}

	$parent_ip = get_interface_ip($if);
	if (is_ipaddrv4($parent_ip) && $_POST['gateway']) {
		$parent_sn = get_interface_subnet($if);
		if (!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($if, $_POST['gateway'])) {
			$input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
		}
	}
	if (($_POST['dns1'] && !is_ipaddrv4($_POST['dns1'])) ||
	    ($_POST['dns2'] && !is_ipaddrv4($_POST['dns2'])) ||
	    ($_POST['dns3'] && !is_ipaddrv4($_POST['dns3'])) ||
	    ($_POST['dns4'] && !is_ipaddrv4($_POST['dns4']))) {
		$input_errors[] = gettext("A valid IPV4 address must be specified for each of the DNS servers.");
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
		$domain_array=preg_split("/[ ;]+/", $_POST['domainsearchlist']);
		foreach ($domain_array as $curdomain) {
			if (!is_domain($curdomain)) {
				$input_errors[] = gettext("A valid domain search list must be specified.");
				break;
			}
		}
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
		$input_errors[] = gettext("A valid IPv4 address, hostname or URL must be specified for the TFTP server.");
	}
	if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver']))) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for the network boot server.");
	}
	if (isset($_POST['arp_table_static_entry']) && empty($_POST['mac'])) {
		$input_errors[] = gettext("A valid MAC address must be specified for use with static ARP.");
	}
	if (isset($_POST['arp_table_static_entry']) && empty($_POST['ipaddr'])) {
		$input_errors[] = gettext("A valid IPv4 address must be specified for use with static ARP.");
	}

	/* Redmine #13584 */
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


	if (!$input_errors) {
		$mapent = array();
		$mapent['mac'] = $_POST['mac'];
		$mapent['cid'] = $_POST['cid'];
		$mapent['ipaddr'] = $_POST['ipaddr'];
		$mapent['hostname'] = $_POST['hostname'];
		$mapent['descr'] = $_POST['descr'];
		$mapent['arp_table_static_entry'] = ($_POST['arp_table_static_entry']) ? true : false;
		$mapent['filename'] = $_POST['filename'];
		$mapent['rootpath'] = $_POST['rootpath'];
		$mapent['defaultleasetime'] = $_POST['deftime'];
		$mapent['maxleasetime'] = $_POST['maxtime'];

		unset($mapent['winsserver']);
		if ($_POST['wins1']) {
			$mapent['winsserver'][] = $_POST['wins1'];
		}
		if ($_POST['wins2']) {
			$mapent['winsserver'][] = $_POST['wins2'];
		}

		unset($mapent['dnsserver']);
		if ($_POST['dns1']) {
			$mapent['dnsserver'][] = $_POST['dns1'];
		}
		if ($_POST['dns2']) {
			$mapent['dnsserver'][] = $_POST['dns2'];
		}
		if ($_POST['dns3']) {
			$mapent['dnsserver'][] = $_POST['dns3'];
		}
		if ($_POST['dns4']) {
			$mapent['dnsserver'][] = $_POST['dns4'];
		}

		$mapent['gateway'] = $_POST['gateway'];
		$mapent['domain'] = $_POST['domain'];
		$mapent['domainsearchlist'] = $_POST['domainsearchlist'];
		$mapent['ddnsdomain'] = $_POST['ddnsdomain'];
		$mapent['ddnsdomainprimary'] = $_POST['ddnsdomainprimary'];
		$mapent['ddnsdomainsecondary'] = $_POST['ddnsdomainsecondary'];
		$mapent['ddnsdomainkeyname'] = $_POST['ddnsdomainkeyname'];
		$mapent['ddnsdomainkeyalgorithm'] = $_POST['ddnsdomainkeyalgorithm'];
		$mapent['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$mapent['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;
		$mapent['ddnsforcehostname'] = ($_POST['ddnsforcehostname']) ? true : false;

		unset($mapent['ntpserver']);
		if ($_POST['ntp1']) {
			$mapent['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$mapent['ntpserver'][] = $_POST['ntp2'];
		}
		if ($_POST['ntp3']) {
			$mapent['ntpserver'][] = $_POST['ntp3'];
		}

		$mapent['tftp'] = $_POST['tftp'];
		$mapent['ldap'] = $_POST['ldap'];
		$mapent['netboot'] = ($_POST['netboot']) ? true : false;
		$mapent['nextserver'] = $_POST['nextserver'];
		$mapent['filename'] = $_POST['filename'];
		$mapent['filename32'] = $_POST['filename32'];
		$mapent['filename64'] = $_POST['filename64'];
		$mapent['filename32arm'] = $_POST['filename32arm'];
		$mapent['filename64arm'] = $_POST['filename64arm'];
		$mapent['uefihttpboot'] = $_POST['uefihttpboot'];
		$mapent['numberoptions'] = $pconfig['numberoptions'];

		if ($this_map_config) {
			config_set_path("dhcpd/{$if}/staticmap/{$id}", $mapent);
		} else {
			config_set_path("dhcpd/{$if}/staticmap/", $mapent);
		}
		staticmaps_sort($if);

		write_config("DHCP Server settings saved");

		if (config_path_enabled("dhcpd/{$if}")) {
			mark_subsystem_dirty('dhcpd');
			if (config_path_enabled('dnsmasq') && config_path_enabled('dnsmasq', 'regdhcpstatic')) {
				mark_subsystem_dirty('hosts');
			}
			if (config_path_enabled('unbound') && config_path_enabled('unbound', 'regdhcpstatic')) {
				mark_subsystem_dirty('unbound');
			}
		}

		/* Configure static ARP entry, or remove ARP entry if this host is dynamic. See https://redmine.pfsense.org/issues/6821 */
		if ($mapent['arp_table_static_entry']) {
			mwexec("/usr/sbin/arp -S " . escapeshellarg($mapent['ipaddr']) . " " . escapeshellarg($mapent['mac']) . " >/dev/null", true);
		} else {
			mwexec("/usr/sbin/arp -d " . escapeshellarg($mapent['ipaddr']) . " >/dev/null", true);
		}

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

// Get our MAC address
$ip = $_SERVER['REMOTE_ADDR'];
$mymac = arp_get_mac_by_ip($ip, false);

$iflist = get_configured_interface_with_descr();
$ifname = '';

if (!empty($if) && isset($iflist[$if])) {
	$ifname = $iflist[$if];
}
$pgtitle = [gettext('Services'), gettext('DHCP Server'), $ifname, gettext('Static Mapping'), gettext('Edit')];
$pglinks = ['', 'services_dhcp.php', 'services_dhcp.php?if='.$if, '@self', '@self'];
$shortcut_section = 'dhcp';
if (dhcp_is_backend('kea')) {
	$shortcut_section = 'kea-dhcp4';
}

include('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

display_isc_warning();

$form = new Form();

$section = new Form_Section(gettext('Static DHCP Mapping'));

if (!dhcp_is_backend('kea')):
$section->addInput(new Form_StaticText(
	gettext('DHCP Backend'),
	match (dhcp_get_backend()) {
		'isc' => gettext('ISC DHCP'),
		'kea' => gettext('Kea DHCP'),
		default => gettext('Unknown')
	}
));
endif;

$macaddress = new Form_Input(
	'mac',
	gettext('MAC Address'),
	'text',
	$pconfig['mac'],
	['placeholder' => 'xx:xx:xx:xx:xx:xx']
);

$macaddress->addClass('autotrim');

$btnmymac = new Form_Button(
	'btnmymac',
	gettext('Copy My MAC'),
	null,
	'fa-regular fa-clone'
	);

$btnmymac->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group = new Form_Group(gettext('MAC Address'));
$group->add($macaddress);
if (!empty($mymac)) {
	$group->add($btnmymac);
}
$group->setHelp(gettext('MAC address of the client to match (6 hex octets separated by colons).'));
$section->add($group);

$cid_help = gettext('An optional identifier to match based on the value sent by the client (RFC 2132).');
if (dhcp_is_backend('kea')) {
	$cid_help .= '<br /><br />';
	$cid_help .= gettext('Kea DHCP will match on MAC address if both MAC address and client identifier are set for a static mapping.');
}

$section->addInput(new Form_Input(
	'cid',
	gettext('Client Identifier'),
	'text',
	$pconfig['cid']
))->addClass('autotrim')
  ->setHelp($cid_help);

$section->addInput(new Form_IpAddress(
	'ipaddr',
	gettext('IP Address'),
	$pconfig['ipaddr'],
	'V4'
))->addClass('autotrim')
  ->setHelp(gettext('IPv4 address to assign this client.%1$s%1$s' .
		'Address must be outside of any defined pools. ' .
		'If no IPv4 address is given, one will be dynamically allocated from a pool.%1$s' .
		'The same IP address may be assigned to multiple mappings.'), '<br />');

$section->addInput(new Form_Checkbox(
	'arp_table_static_entry',
	gettext('ARP Table Static Entry'),
	gettext('Create an ARP Table Static Entry for this MAC & IP Address pair.'),
	$pconfig['arp_table_static_entry']
));

$section->addInput(new Form_Input(
	'hostname',
	gettext('Hostname'),
	'text',
	$pconfig['hostname']
))->addClass('autotrim')
  ->setHelp(gettext('Name of the client host without the domain part.'));

$section->addInput(new Form_Input(
	'descr',
	gettext('Description'),
	'text',
	$pconfig['descr']
))->setHelp(gettext('A description for administrative reference (not parsed).'));

$form->add($section);

$section = new Form_Section(gettext('Server Options'));

$winsserver_holder = config_get_path('dhcpd/'.$if.'/winsserver', []);

$section->addInput(new Form_IpAddress(
	'wins1',
	gettext('WINS Servers'),
	$pconfig['wins1'],
	'V4'
))->addClass('autotrim')
  ->setAttribute('placeholder', ($winsserver_holder[0] ?? gettext('WINS Server 1')));

$section->addInput(new Form_IpAddress(
	'wins2',
	null,
	$pconfig['wins2'],
	'V4'
))->addClass('autotrim')
  ->setAttribute('placeholder', ($winsserver_holder[1] ?? gettext('WINS Server 2')));

$dns_holder = [];
foreach (config_get_path('system/dnsserver', []) as $dnsserver) {
	if (is_ipaddrv4($dnsserver)) {
		$dns_holder[] = $dnsserver;
	}
}

if (config_path_enabled('dnsmasq') ||
    config_path_enabled('unbound')) {
    $dns_holder = [get_interface_ip($if)];
}

$subnet_dnsservers = config_get_path('dhcpd/'.$if.'/dnsserver', []);
if (!empty($subnet_dnsservers)) {
	$dns_holder = $subnet_dnsservers;
}

for ($idx=1; $idx<=4; $idx++) {
	$section->addInput(new Form_IpAddress(
		'dns' . $idx,
		($idx == 1) ? gettext('DNS Servers') : null,
		$pconfig['dns' . $idx],
		'V4'
	))->addClass('autotrim')
	  ->setAttribute('placeholder', ($dns_holder[$idx - 1] ?? sprintf(gettext('DNS Server %s'), $idx)))->setHelp(($idx == 4) ? 'Leave blank to use the system default DNS servers: The IP address of this firewall interface if DNS Resolver or Forwarder is enabled, otherwise the servers configured in General settings or those obtained dynamically.':'');
}

$form->add($section);

$section = new Form_Section(gettext('Other DHCP Options'));

$ifip = get_interface_ip($if);

/* interface ip has lowest priority */
$gateway_holder = $ifip;

/* subnet/primary pool has highest priority */
$subnet_gateway = config_get_path('dhcpd/'.$if.'/gateway');
if (!empty($subnet_gateway)) {
	$gateway_holder = $subnet_gateway;
}

$section->addInput(new Form_Input(
	'gateway',
	gettext('Gateway'),
	'text',
	$pconfig['gateway']
))->addClass('autotrim')
  ->setAttribute('placeholder', $gateway_holder)
  ->setHelp(gettext('The default is to use the IP address of this firewall interface as the gateway. Specify an alternate gateway here if this is not the correct gateway for the network.'));

$domain_holder = config_get_path('system/domain');

$subnet_domain = config_get_path('dhcpd/'.$if.'/domain');
if (!empty($subnet_domain)) {
	$domain_holder = $subnet_domain;
}

$section->addInput(new Form_Input(
	'domain',
	gettext('Domain Name'),
	'text',
	$pconfig['domain']
))->addClass('autotrim')
  ->setAttribute('placeholder', $domain_holder)
  ->setHelp(gettext('The default is to use the domain name of this firewall as the default domain name provided by DHCP. An alternate domain name may be specified here.'));

$searchlist_holder = config_get_path('dhcpd/'.$if.'/domainsearchlist');
if (empty($searchlist_holder)) {
	$searchlist_holder = 'example.com;sub.example.com';
}

$section->addInput(new Form_Input(
	'domainsearchlist',
	gettext('Domain Search List'),
	'text',
	$pconfig['domainsearchlist']
))->addClass('autotrim')
  ->setAttribute('placeholder', $searchlist_holder)
  ->setHelp(gettext('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.'));

if (dhcp_is_backend('isc')):
$section->addInput(new Form_Input(
	'deftime',
	gettext('Default Lease Time'),
	'text',
	$pconfig['deftime']
))->setHelp(gettext('Used for clients that do not ask for a specific expiration time. The default is 7200 seconds.'));

$section->addInput(new Form_Input(
	'maxtime',
	gettext('Maximum Lease Time'),
	'text',
	$pconfig['maxtime']
))->setHelp(gettext('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds.'));
endif;

if (dhcp_is_backend('isc')):
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
	gettext('DHCP Registration'),
	gettext('Enable registration of DHCP client names in DNS.'),
	$pconfig['ddnsupdate']
));

$section->addInput(new Form_Checkbox(
	'ddnsforcehostname',
	gettext('DDNS Hostname'),
	gettext('Make dynamic DNS registered hostname the same as Hostname above.'),
	$pconfig['ddnsforcehostname']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	gettext('DDNS Domain'),
	'text',
	$pconfig['ddnsdomain']
))->setHelp(gettext('Leave blank to disable dynamic DNS registration. Enter the dynamic DNS domain which will ' .
	    'be used to register client names in the DNS server. Only the first defined set of option for each ' .
	    'domain will be honored if it is used for multiple interfaces/entries.'));

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	gettext('Primary DDNS Address'),
	$pconfig['ddnsdomainprimary'],
	'BOTH'
))->setHelp(gettext('Primary domain name server IP address for the dynamic domain name.'));

$section->addInput(new Form_IpAddress(
	'ddnsdomainsecondary',
	gettext('Secondary DDNS Address'),
	$pconfig['ddnsdomainsecondary'],
	'BOTH'
))->setHelp(gettext('Secondary domain name server IP address for the dynamic domain name.'));

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	gettext('DDNS Domain Key Name'),
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp(gettext('Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.'));

$section->addInput(new Form_Select(
	'ddnsdomainkeyalgorithm',
	gettext('Key Algorithm'),
	$pconfig['ddnsdomainkeyalgorithm'],
	$ddnsdomainkeyalgorithms
));

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	gettext('DDNS Domain Key Secret'),
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp(gettext('Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.'));
endif;

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


for ($idx = 1; $idx <= 4; $idx++) {
	$section->addInput(new Form_IpAddress(
		'ntp'.$idx,
		sprintf(gettext('NTP Server %s'), $idx),
		$pconfig['ntp'.$idx],
		'HOSTV4'
	))->addClass('autotrim')
	  ->setAttribute('placeholder', sprintf(gettext('NTP Server %s'), $idx));
}

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
  ->setHelp(gettext('Leave blank to disable. Enter a full hostname or IP for the TFTP server.'));

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

if (dhcp_is_backend('isc')):
$section->addInput(new Form_IpAddress(
	'nextserver',
	gettext('Next Server'),
	$pconfig['nextserver'],
	'V4'
))->setHelp(gettext('Enter the IP address of the next server'));
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
			'All five filenames and a configured boot server are necessary for UEFI & ARM to work!'));

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
		gettext('Delete'),
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
endif;


$form->add($section);

print($form);
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
		hideCheckbox('ddnsforcehostname', !showadvdns);
		hideInput('ddnsdomain', !showadvdns);
		hideInput('ddnsdomainprimary', !showadvdns);
		hideInput('ddnsdomainsecondary', !showadvdns);
		hideInput('ddnsdomainkeyname', !showadvdns);
		hideInput('ddnsdomainkey', !showadvdns);
		hideInput('ddnsdomainkeyalgorithm', !showadvdns);

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

	// On click, copy the hidden 'mymac' text to the 'mac' input
	$("#btnmymac").click(function() {
		$('#mac').val('<?=$mymac?>');
	});

	// On initial load
	show_advdns(true);
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
include("foot.inc");
