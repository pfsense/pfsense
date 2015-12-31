<?php
/*
	services_dhcp_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-services-dhcpserver-editstaticmapping
##|*NAME=Services: DHCP Server: Edit static mapping
##|*DESCR=Allow access to the 'Services: DHCP Server: Edit static mapping' page.
##|*MATCH=services_dhcp_edit.php*
##|-PRIV

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

require("guiconfig.inc");

$if = $_GET['if'];

if ($_POST['if']) {
	$if = $_POST['if'];
}

if (!$if) {
	header("Location: services_dhcp.php");
	exit;
}

if (!is_array($config['dhcpd'])) {
	$config['dhcpd'] = array();
}
if (!is_array($config['dhcpd'][$if])) {
	$config['dhcpd'][$if] = array();
}
if (!is_array($config['dhcpd'][$if]['staticmap'])) {
	$config['dhcpd'][$if]['staticmap'] = array();
}

if (!is_array($config['dhcpd'][$if]['pool'])) {
	$config['dhcpd'][$if]['pool'] = array();
}
$a_pools = &$config['dhcpd'][$if]['pool'];

$static_arp_enabled=isset($config['dhcpd'][$if]['staticarp']);
$netboot_enabled=isset($config['dhcpd'][$if]['netboot']);
$a_maps = &$config['dhcpd'][$if]['staticmap'];
$ifcfgip = get_interface_ip($if);
$ifcfgsn = get_interface_subnet($if);
$ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

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
	$pconfig['ddnsdomainkey'] = $a_maps[$id]['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($a_maps[$id]['ddnsupdate']);
	list($pconfig['ntp1'], $pconfig['ntp2']) = $a_maps[$id]['ntpserver'];
	$pconfig['tftp'] = $a_maps[$id]['tftp'];
} else {
	$pconfig['mac'] = $_GET['mac'];
	$pconfig['cid'] = $_GET['cid'];
	$pconfig['hostname'] = $_GET['hostname'];
	$pconfig['filename'] = $_GET['filename'];
	$pconfig['rootpath'] = $_GET['rootpath'];
	$pconfig['descr'] = $_GET['descr'];
	$pconfig['arp_table_static_entry'] = $_GET['arp_table_static_entry'];
	$pconfig['deftime'] = $_GET['defaultleasetime'];
	$pconfig['maxtime'] = $_GET['maxleasetime'];
	$pconfig['gateway'] = $_GET['gateway'];
	$pconfig['domain'] = $_GET['domain'];
	$pconfig['domainsearchlist'] = $_GET['domainsearchlist'];
	$pconfig['wins1'] = $_GET['wins1'];
	$pconfig['wins2'] = $_GET['wins2'];
	$pconfig['dns1'] = $_GET['dns1'];
	$pconfig['dns2'] = $_GET['dns2'];
	$pconfig['dns3'] = $_GET['dns3'];
	$pconfig['dns4'] = $_GET['dns4'];
	$pconfig['ddnsdomain'] = $_GET['ddnsdomain'];
	$pconfig['ddnsdomainprimary'] = $_GET['ddnsdomainprimary'];
	$pconfig['ddnsdomainkeyname'] = $_GET['ddnsdomainkeyname'];
	$pconfig['ddnsdomainkey'] = $_GET['ddnsdomainkey'];
	$pconfig['ddnsupdate'] = isset($_GET['ddnsupdate']);
	$pconfig['ntp1'] = $_GET['ntp1'];
	$pconfig['ntp2'] = $_GET['ntp2'];
	$pconfig['tftp'] = $_GET['tftp'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

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

	if (($_POST['ipaddr'] && !is_ipaddr($_POST['ipaddr']))) {
		$input_errors[] = gettext("A valid IP address must be specified.");
	}

	if (($_POST['mac'] && !is_macaddr($_POST['mac']))) {
		$input_errors[] = gettext("A valid MAC address must be specified.");
	}
	if ($static_arp_enabled && !$_POST['ipaddr']) {
		$input_errors[] = gettext("Static ARP is enabled.  You must specify an IP address.");
	}

	/* check for overlaps */
	foreach ($a_maps as $mapent) {
		if (isset($id) && ($a_maps[$id]) && ($a_maps[$id] === $mapent)) {
			continue;
		}
		/* The fully qualified hostname (hostname + '.' + domainname) must be unique.
		 * The unqualified hostname does not have to be unique as long as the fully
		 * qualified hostname is unique. */
		$existingFqn = "{$mapent['hostname']}.{$mapent['domain']}";
		$candidateFqn = "{$_POST['hostname']}.{$_POST['domain']}";
		if ((($existingFqn == $candidateFqn) && $mapent['hostname']) ||
		    (($mapent['mac'] == $_POST['mac']) && $mapent['mac']) ||
		    (($mapent['ipaddr'] == $_POST['ipaddr']) && $mapent['ipaddr']) ||
		    (($mapent['cid'] == $_POST['cid']) && $mapent['cid'])) {
			$input_errors[] = gettext("This fully qualified hostname (Hostname + Domainname), IP, MAC address or Client identifier already exists.");
			break;
		}
	}

	/* make sure it's not within the dynamic subnet */
	if ($_POST['ipaddr']) {
		$dynsubnet_start = ip2ulong($config['dhcpd'][$if]['range']['from']);
		$dynsubnet_end = ip2ulong($config['dhcpd'][$if]['range']['to']);
		if ((ip2ulong($_POST['ipaddr']) >= $dynsubnet_start) &&
		    (ip2ulong($_POST['ipaddr']) <= $dynsubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must not be within the DHCP range for this interface."));
		}

		foreach ($a_pools as $pidx => $p) {
			if (is_inrange_v4($_POST['ipaddr'], $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext("The IP address must not be within the range configured on a DHCP pool for this interface.");
				break;
			}
		}

		$lansubnet_start = ip2ulong(gen_subnetv4($ifcfgip, $ifcfgsn));
		$lansubnet_end = ip2ulong(gen_subnetv4_max($ifcfgip, $ifcfgsn));
		$ipaddr_int = ip2ulong($_POST['ipaddr']);
		if (($ipaddr_int < $lansubnet_start) ||
		    ($ipaddr_int > $lansubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must lie in the %s subnet."), $ifcfgdescr);
		}

		if ($ipaddr_int == $lansubnet_start) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s network address."), $ifcfgdescr);
		}

		if ($ipaddr_int == $lansubnet_end) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s broadcast address."), $ifcfgdescr);
		}
	}

	if (($_POST['gateway'] && !is_ipaddrv4($_POST['gateway']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the gateway.");
	}
	if (($_POST['wins1'] && !is_ipaddrv4($_POST['wins1'])) || ($_POST['wins2'] && !is_ipaddrv4($_POST['wins2']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
	}

	$parent_ip = get_interface_ip($POST['if']);
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
		$input_errors[] = gettext("A valid IP address must be specified for each of the DNS servers.");
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
	if (($_POST['ddnsdomain'] && !is_ipaddrv4($_POST['ddnsdomainprimary']))) {
		$input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
	}
	if (($_POST['ddnsdomainkey'] && !$_POST['ddnsdomainkeyname']) ||
	    ($_POST['ddnsdomainkeyname'] && !$_POST['ddnsdomainkey'])) {
		$input_errors[] = gettext("You must specify both a valid domain key and key name.");
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

	if (($_POST['ntp1'] && !is_ipaddrv4($_POST['ntp1'])) || ($_POST['ntp2'] && !is_ipaddrv4($_POST['ntp2']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
	}
	if ($_POST['tftp'] && !is_ipaddrv4($_POST['tftp']) && !is_domain($_POST['tftp']) && !is_URL($_POST['tftp'])) {
		$input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
	}
	if (($_POST['nextserver'] && !is_ipaddrv4($_POST['nextserver']))) {
		$input_errors[] = gettext("A valid IP address must be specified for the network boot server.");
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
		$mapent['ddnsdomainkey'] = $_POST['ddnsdomainkey'];
		$mapent['ddnsupdate'] = ($_POST['ddnsupdate']) ? true : false;

		unset($mapent['ntpserver']);
		if ($_POST['ntp1']) {
			$mapent['ntpserver'][] = $_POST['ntp1'];
		}
		if ($_POST['ntp2']) {
			$mapent['ntpserver'][] = $_POST['ntp2'];
		}

		$mapent['tftp'] = $_POST['tftp'];
		$mapent['ldap'] = $_POST['ldap'];

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

		header("Location: services_dhcp.php?if={$if}");
		exit;
	}
}

// Get our MAC address
$ip = $_SERVER['REMOTE_ADDR'];
$mymac = `/usr/sbin/arp -an | grep '('{$ip}')' | cut -d" " -f4`;
$mymac = str_replace("\n", "", $mymac);

$pgtitle = array(gettext("Services"), gettext("DHCP"), gettext("Edit static mapping"));
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
	'Copy My MAC'
	);

$btnmymac->removeClass('btn-primary')->addClass('btn-success btn-sm');

$group = new Form_Group('MAC controls');
$group->add($macaddress);
$group->add($btnmymac);
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
	$pconfig['ipaddr']
))->setHelp('If an IPv4 address is entered, the address must be outside of the pool.' . '<br />' .
			'If no IPv4 address is given, one will be dynamically allocated from the pool.');

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
))->setHelp('You may enter a description here for your reference (not parsed).');

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
))->setHelp('The default is to use the IP on this interface of the firewall as the gateway. Specify an alternate gateway here if this is not the correct gateway for your network.');

$section->addInput(new Form_Input(
	'domain',
	'Domain name',
	'text',
	$pconfig['domain']
))->setHelp('The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here. ');

$section->addInput(new Form_Input(
	'domainsearchlist',
	'Domain search list',
	'text',
	$pconfig['domainsearchlist']
))->setHelp('The DHCP server can optionally provide a domain search list. Use the semicolon character as separator');

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

$btndyndns = new Form_Button(
	'btndyndns',
	'Advanced'
);

$btndyndns->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'Dynamic DNS',
	$btndyndns . '&nbsp;' . 'Show dynamic DNS settings'
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
))->setHelp('Leave blank to disable dynamic DNS registration. Enter the dynamic DNS domain which will be used to register client names in the DNS server.');

$section->addInput(new Form_IpAddress(
	'ddnsdomainprimary',
	'DDNS Server IP',
	$pconfig['ddnsdomainprimary']
))->setHelp('Enter the primary domain name server IP address for the dynamic domain name.');

$section->addInput(new Form_Input(
	'ddnsdomainkeyname',
	'DDNS Domain Key name',
	'text',
	$pconfig['ddnsdomainkeyname']
))->setHelp('Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.');

$section->addInput(new Form_Input(
	'ddnsdomainkey',
	'DDNS Domain Key secret',
	'text',
	$pconfig['ddnsdomainkey']
))->setHelp('Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.');

$btnntp = new Form_Button(
	'btnntp',
	'Advanced'
);

$btnntp->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'NTP servers',
	$btnntp . '&nbsp;' . 'Show NTP Configuration'
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

$group->addClass('ntpclass');

$section->add($group);

$btntftp = new Form_Button(
	'btntftp',
	'Advanced'
);

$btntftp->removeClass('btn-primary')->addClass('btn-default btn-sm');

$section->addInput(new Form_StaticText(
	'TFTP servers',
	$btntftp . '&nbsp;' . 'Show TFTP Configuration'
));

$section->addInput(new Form_Input(
	'tftp',
	'TFTP Server',
	'text',
	$pconfig['tftp']
))->setHelp('Leave blank to disable. Enter a full hostname or IP for the TFTP server.');

$form->add($section);
print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function hideDDNS(hide) {
		hideCheckbox('ddnsupdate', hide);
		hideInput('ddnsdomain', hide);
		hideInput('ddnsdomainprimary', hide);
		hideInput('ddnsdomainkeyname', hide);
		hideInput('ddnsdomainkey', hide);
	}

	// Make the ‘Copy My MAC’ button a plain button, not a submit button
	$("#btnmymac").prop('type','button');

	// On click, copy the hidden 'mymac' text to the 'mac' input
	$("#btnmymac").click(function() {
		$('#mac').val('<?=$mymac?>');
	});

	// Make the ‘tftp’ button a plain button, not a submit button
	$("#btntftp").prop('type','button');

	// Show tftp controls
	$("#btntftp").click(function() {
		hideInput('tftp', false);
	});

	// Make the ‘ntp’ button a plain button, not a submit button
	$("#btnntp").prop('type','button');

	// Show ntp controls
	$("#btnntp").click(function() {
		hideClass('ntpclass', false);
	});

	// Make the ‘ddns’ button a plain button, not a submit button
	$("#btndyndns").prop('type','button');

	// Show ddns controls
	$("#btndyndns").click(function() {
		hideDDNS(false);
	});

	// On initial load
	hideDDNS(true);
	hideClass('ntpclass', true);
	hideInput('tftp', true);
});
//]]>
</script>

<?php include("foot.inc");
