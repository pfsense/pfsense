<?php
/*
 * services_dhcp_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
	global $g, $config;

	usort($config['dhcpd'][$ifgui]['staticmap'], "staticmapcmp");
}

require_once('globals.inc');

if (!$g['services_dhcp_server_enable']) {
	header("Location: /");
	exit;
}

require_once("guiconfig.inc");

$if = $_REQUEST['if'];

if (!$if) {
	header("Location: services_dhcp.php");
	exit;
}

init_config_arr(array('dhcpd', $if, 'staticmap'));
init_config_arr(array('dhcpd', $if, 'pool'));
$a_maps = &$config['dhcpd'][$if]['staticmap'];
$a_pools = &$config['dhcpd'][$if]['pool'];
$static_arp_enabled=isset($config['dhcpd'][$if]['staticarp']);
$netboot_enabled=isset($config['dhcpd'][$if]['netboot']);
$ifcfgip = get_interface_ip($if);
$ifcfgsn = get_interface_subnet($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

$id = $_REQUEST['id'];

if (isset($id) && $a_maps[$id]) {
	$pconfig['mac'] = $a_maps[$id]['mac'];
	$pconfig['cid'] = $a_maps[$id]['cid'];
	$pconfig['hostname'] = $a_maps[$id]['hostname'];
	$pconfig['ipaddr'] = $a_maps[$id]['ipaddr'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['descr'] = $a_maps[$id]['descr'];
	$pconfig['arp_table_static_entry'] = isset($a_maps[$id]['arp_table_static_entry']);
	$pconfig['deftime'] = $a_maps[$id]['defaultleasetime'];
	$pconfig['maxtime'] = $a_maps[$id]['maxleasetime'];
	$pconfig['gateway'] = $a_maps[$id]['gateway'];
	$pconfig['domain'] = $a_maps[$id]['domain'];
	$pconfig['domainsearchlist'] = $a_maps[$id]['domainsearchlist'];
	list($pconfig['wins1'], $pconfig['wins2']) = $a_maps[$id]['winsserver'];
	list($pconfig['dns1'], $pconfig['dns2'], $pconfig['dns3'], $pconfig['dns4']) = $a_maps[$id]['dnsserver'];
	$pconfig['ddnsdomain'] = $a_maps[$id]['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $a_maps[$id]['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $a_maps[$id]['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkeyalgorithm'] = $a_maps[$id]['ddnsdomainkeyalgorithm'];
	$pconfig['ddnsdomainkey'] = $a_maps[$id]['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($a_maps[$id]['ddnsupdate']);
	$pconfig['ddnsforcehostname'] = isset($a_maps[$id]['ddnsforcehostname']);
	list($pconfig['ntp1'], $pconfig['ntp2'], $pconfig['ntp3']) = $a_maps[$id]['ntpserver'];
	$pconfig['tftp'] = $a_maps[$id]['tftp'];
	$pconfig['ldap'] = $a_maps[$id]['ldap'];
	$pconfig['netboot'] = isset($a_maps[$id]['netboot']);
	$pconfig['nextserver'] = $a_maps[$id]['nextserver'];
	$pconfig['filename'] = $a_maps[$id]['filename'];
	$pconfig['filename32'] = $a_maps[$id]['filename32'];
	$pconfig['filename64'] = $a_maps[$id]['filename64'];
	$pconfig['filename32arm'] = $dhcpdconf['filename32arm'];
	$pconfig['filename64arm'] = $dhcpdconf['filename64arm'];
	$pconfig['rootpath'] = $a_maps[$id]['rootpath'];
	$pconfig['netmask'] = $a_maps[$id]['netmask'];
	$pconfig['numberoptions'] = $a_maps[$id]['numberoptions'];
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
	$pconfig['filename32arm'] = $dhcpdconf['filename32arm'];
	$pconfig['filename64arm'] = $dhcpdconf['filename64arm'];
	$pconfig['rootpath'] = $_REQUEST['rootpath'];
	$pconfig['netmask'] = $_REQUEST['netmask'];
	$pconfig['numberoptions'] = $_REQUEST['numberoptions'];
}

if ($_POST['save']) {
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
	$reqdfields = array();
	$reqdfieldsn = array();

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	/* either MAC or Client-ID must be specified */
	if (empty($_POST['mac']) && empty($_POST['cid'])) {
		$input_errors[] = gettext("Either MAC address or Client identifier must be specified");
	}

	/* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
	$_POST['mac'] = strtolower(str_replace("-", ":", $_POST['mac']));

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
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent)) {
			continue;
		}
		if ((($mapent['mac'] == $_POST['mac']) && $mapent['mac']) ||
		    (($mapent['cid'] == $_POST['cid']) && $mapent['cid'])) {
			$input_errors[] = gettext("This MAC address or Client identifier already exists.");
			break;
		}
	}

	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddr']) {
		if (is_inrange_v4($_POST['ipaddr'], $config['dhcpd'][$if]['range']['from'], $config['dhcpd'][$if]['range']['to'])) {
			$input_errors[] = sprintf(gettext("The IP address must not be within the DHCP range for this interface."));
		}

		foreach ($a_pools as $pidx => $p) {
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

	$parent_ip = get_interface_ip($_POST['if']);
	if (is_ipaddrv4($parent_ip) && $_POST['gateway']) {
		$parent_sn = get_interface_subnet($_POST['if']);
		if (!ip_in_subnet($_POST['gateway'], gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn) && !ip_in_interface_alias_subnet($_POST['if'], $_POST['gateway'])) {
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
	if ($_POST['maxtime'] && (!is_numeric($_POST['maxtime']) || ($_POST['maxtime'] < 60) || ($_POST['maxtime'] <= $_POST['deftime']))) {
		$input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
	}
	if (($_POST['ddnsdomain'] && !is_domain($_POST['ddnsdomain']))) {
		$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
	}
	if (($_POST['ddnsdomain'] && !is_ipaddr($_POST['ddnsdomainprimary']))) {
		$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
	}
	if ($_POST['ddnsupdate']) {
		if (!is_domain($_POST['ddnsdomain'])) {
			$input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
		}
		if (!is_ipaddr($_POST['ddnsdomainprimary'])) {
			$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
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

	if (($_POST['ntp1'] && (!is_ipaddrv4($_POST['ntp1']) && !is_hostname($_POST['ntp1']))) ||
	    ($_POST['ntp2'] && (!is_ipaddrv4($_POST['ntp2']) && !is_hostname($_POST['ntp2']))) ||
	    ($_POST['ntp3'] && (!is_ipaddrv4($_POST['ntp3']) && !is_hostname($_POST['ntp3'])))) {
		$input_errors[] = gettext("A valid IP address or hostname must be specified for the primary/secondary NTP servers.");
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
		$mapent['numberoptions'] = $pconfig['numberoptions'];

		if (isset($id) && $a_maps[$id]) {
			$a_maps[$id] = $mapent;
		} else {
			$a_maps[] = $mapent;
		}
		staticmaps_sort($if);

		write_config();

		if (isset($config['dhcpd'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic'])) {
				mark_subsystem_dirty('hosts');
			}
			if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
				mark_subsystem_dirty('unbound');
			}
		}

		/* Configure static ARP entry, or remove ARP entry if this host is dynamic. See https://redmine.pfsense.org/issues/6821 */
		if ($mapent['arp_table_static_entry']) {
			mwexec("/usr/sbin/arp -S " . escapeshellarg($mapent['ipaddr']) . " " . escapeshellarg($mapent['mac']));
		} else {
			mwexec("/usr/sbin/arp -d " . escapeshellarg($mapent['ipaddr']));
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
$pgtitle = array(gettext("Services"), gettext("DHCP Server"), $ifname, gettext("Edit Static Mapping"));
$pglinks = array("", "services_dhcp.php", "services_dhcp.php?if={$if}", "@self");
$shortcut_section = "dhcp";

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section(sprintf("Static DHCP Mapping on %s", $ifcfgdescr));

$macaddress = new Form_Input(
	'mac',
	'MAC Address',
	'text',
	$pconfig['mac'],
	['placeholder' => 'xx:xx:xx:xx:xx:xx']
);

$btnmymac = new Form_Button(
	'btnmymac',
	'Copy My MAC',
	null,
	'fa-clone'
	);

$btnmymac->setAttribute('type','button')->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group = new Form_Group('MAC Address');
$group->add($macaddress);
if (!empty($mymac)) {
	$group->add($btnmymac);
}
$group->setHelp('MAC address (6 hex octets separated by colons)');
$section->add($group);

$section->addInput(new Form_Input(
	'cid',
	'Client Identifier',
	'text',
	$pconfig['cid']
));

$section->addInput(new Form_IpAddress(
	'ipaddr',
	'IP Address',
	$pconfig['ipaddr'],
	'V4'
))->setHelp('If an IPv4 address is entered, the address must be outside of the pool.%1$s' .
			'If no IPv4 address is given, one will be dynamically allocated from the pool.%1$s%1$s' .
			'The same IP address may be assigned to multiple mappings.', '<br />');

$section->addInput(new Form_Input(
	'hostname',
	'Hostname',
	'text',
	$pconfig['hostname']
))->setHelp('Name of the host, without domain part.');

if ($netboot_enabled) {
	$section->addInput(new Form_Input(
		'filename',
		'Netboot filename',
		'text',
		$pconfig['filename']
	))->setHelp('Name of the file that should be loaded when this host boots off of the network, overrides setting on main page.');

	$section->addInput(new Form_Input(
		'rootpath',
		'Root Path',
		'text',
		$pconfig['rootpath']
	))->setHelp('Enter the root-path-string, overrides setting on main page.');
}

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Checkbox(
	'arp_table_static_entry',
	'ARP Table Static Entry',
	'Create an ARP Table Static Entry for this MAC & IP Address pair.',
	$pconfig['arp_table_static_entry']
));

$group = new Form_Group('WINS Servers');

$group->add(new Form_Input(
	'wins1',
	null,
	'text',
	$pconfig['wins1'],
	['placeholder' => 'WINS 1']
));

$group->add(new Form_Input(
	'wins2',
	null,
	'text',
	$pconfig['wins2'],
	['placeholder' => 'WINS 2']
));

$section->add($group);
$group = new Form_Group('DNS Servers');

$group->add(new Form_Input(
	'dns1',
	null,
	'text',
	$pconfig['dns1'],
	['placeholder' => 'DNS 1']
));

$group->add(new Form_Input(
	'dns2',
	null,
	'text',
	$pconfig['dns2'],
	['placeholder' => 'DNS 2']
));

$group->add(new Form_Input(
	'dns3',
	null,
	'text',
	$pconfig['dns3'],
	['placeholder' => 'DNS 3']
));

$group->add(new Form_Input(
	'dns4',
	null,
	'text',
	$pconfig['dns4'],
	['placeholder' => 'DNS 4']
));

$group->setHelp('Note: leave blank to use the system default DNS servers - this interface\'s IP if DNS Forwarder or Resolver is enabled, otherwise the servers configured on the General page.');

$section->add($group);

$section->addInput(new Form_Input(
	'gateway',
	'Gateway',
	'text',
	$pconfig['gateway']
))->setHelp('The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for the network.');

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
	'Default lease time (Seconds)',
	'text',
	$pconfig['deftime']
))->setHelp('Used for clients that do not ask for a specific expiration time. The default is 7200 seconds.');

$section->addInput(new Form_Input(
	'maxtime',
	'Maximum lease time (Seconds)',
	'text',
	$pconfig['maxtime']
))->setHelp('This is the maximum lease time for clients that ask for a specific expiration time. The default is 86400 seconds.');

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

$section->addInput(new Form_Checkbox(
	'ddnsforcehostname',
	'DDNS Hostname',
	'Make dynamic DNS registered hostname the same as Hostname above.',
	$pconfig['ddnsforcehostname']
));

$section->addInput(new Form_Input(
	'ddnsdomain',
	'DDNS Domain',
	'text',
	$pconfig['ddnsdomain']
))->setHelp('Leave blank to disable dynamic DNS registration. Enter the dynamic DNS domain which will ' .
	    'be used to register client names in the DNS server. Only the first defined set of option for each ' .
	    'domain will be honored if it is used for multiple interfaces/entries.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	'DDNS Server IP',
	$pconfig['ddnsdomainprimary'],
	'BOTH'
))->setHelp('Enter the primary domain name server IP address for the dynamic domain name.');

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
	$ddnsdomainkeyalgorithms
));

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	'DDNS Domain Key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp('Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.');

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
	'btnadvtftp',
	'Display Advanced',
	null,
	'fa-cog'
);

$btnadv->setAttribute('type','button')->addClass('btn-info btn-sm');

$section->addInput(new Form_StaticText(
	'TFTP servers',
	$btnadv
));

$section->addInput(new Form_Input(
	'tftp',
	'TFTP Server',
	'text',
	$pconfig['tftp']
))->setHelp('Leave blank to disable. Enter a full hostname or IP for the TFTP server.');

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
));

$section->addInput(new Form_Input(
	'filename32arm',
	'ARM 32 bit file name',
	'text',
	$pconfig['filename32arm']
));

$section->addInput(new Form_Input(
	'filename64arm',
	'ARM 64 bit file name',
	'text',
	$pconfig['filename64arm']
))->setHelp('Both a filename and a boot server must be configured for this to work! ' .
			'All five filenames and a configured boot server are necessary for UEFI & ARM to work! ');

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
			if (!$pconfig['ddnsupdate'] && !$pconfig['ddnsforcehostname'] && empty($pconfig['ddnsdomain']) && empty($pconfig['ddnsdomainprimary']) &&
			    empty($pconfig['ddnsdomainkeyname']) && empty($pconfig['ddnsdomainkey'])) {
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
		hideInput('ddnsdomainkeyname', !showadvdns);
		hideInput('ddnsdomainkey', !showadvdns);
		hideInput('ddnsdomainkeyalgorithm', !showadvdns);

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
		hideInput('filename32arm', !showadvnwkboot);
		hideInput('filename64arm', !showadvnwkboot);
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

<?php include("foot.inc");
