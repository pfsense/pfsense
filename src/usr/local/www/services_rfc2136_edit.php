<?php
/*
 * services_rfc2136_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-rfc2136edit
##|*NAME=Services: RFC 2136 Client: Edit
##|*DESCR=Allow access to the 'Services: RFC 2136 Client: Edit' page.
##|*MATCH=services_rfc2136_edit.php*
##|-PRIV

require_once("guiconfig.inc");

$tsig_key_algos = array(
	'hmac-md5'    => 'HMAC-MD5 (legacy default)',
	'hmac-sha1'   => 'HMAC-SHA1',
	'hmac-sha224' => 'HMAC-SHA224',
	'hmac-sha256' => 'HMAC-SHA256 (current bind9 default)',
	'hmac-sha384' => 'HMAC-SHA384',
	'hmac-sha512' => 'HMAC-SHA512 (most secure)',
);

config_init_path('dnsupdates/dnsupdate');

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$dup = false;
if (isset($_REQUEST['dup']) && is_numericint($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$dup = true;
}

$this_rfc2136_config = isset($id) ? config_get_path("dnsupdates/dnsupdate/{$id}") : null;
if ($this_rfc2136_config) {
	$pconfig['enable'] = isset($this_rfc2136_config['enable']);
	if (!$dup) {
		$pconfig['host'] = $this_rfc2136_config['host'];
	}
	$pconfig['ttl'] = $this_rfc2136_config['ttl'];
	if (!$pconfig['ttl']) {
		$pconfig['ttl'] = 60;
	}
	$pconfig['zone'] = $this_rfc2136_config['zone'];
	$pconfig['keyname'] = $this_rfc2136_config['keyname'];
	$pconfig['keyalgorithm'] = $this_rfc2136_config['keyalgorithm'];
	$pconfig['keydata'] = $this_rfc2136_config['keydata'];
	$pconfig['server'] = $this_rfc2136_config['server'];
	$pconfig['interface'] = $this_rfc2136_config['interface'];
	$pconfig['updatesource'] = $this_rfc2136_config['updatesource'];
	$pconfig['updatesourcefamily'] = $this_rfc2136_config['updatesourcefamily'];
	$pconfig['usetcp'] = isset($this_rfc2136_config['usetcp']);
	$pconfig['usepublicip'] = isset($this_rfc2136_config['usepublicip']);
	$pconfig['recordtype'] = $this_rfc2136_config['recordtype'];
	if (!$pconfig['recordtype']) {
		$pconfig['recordtype'] = "both";
	}
	$pconfig['descr'] = $this_rfc2136_config['descr'];

}

if ($_POST['save'] || $_POST['force']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array('host', 'ttl', 'keyname', 'keydata');
	$reqdfieldsn = array(gettext("Hostname"), gettext("TTL"), gettext("Key name"), gettext("Key"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['host'] && !is_domain($_POST['host'])) {
		$input_errors[] = gettext("The DNS update host name contains invalid characters.");
	}
	if ($_POST['zone'] && !is_domain($_POST['zone'])) {
		$input_errors[] = gettext("The DNS zone name contains invalid characters.");
	}
	if ($_POST['ttl'] && !is_numericint($_POST['ttl'])) {
		$input_errors[] = gettext("The DNS update TTL must be an integer.");
	}
	if ($_POST['keyname'] && !is_domain($_POST['keyname'])) {
		$input_errors[] = gettext("The DNS update key name contains invalid characters.");
	}
	if ($_POST['keyalgorithm'] && !array_key_exists($_POST['keyalgorithm'], $tsig_key_algos)) {
		$input_errors[] = gettext("The DNS update key algorithm is invalid.");
	}

	if (!$input_errors) {
		$rfc2136 = array();
		$rfc2136['enable'] = $_POST['enable'] ? true : false;
		$rfc2136['host'] = $_POST['host'];
		$rfc2136['zone'] = $_POST['zone'];
		$rfc2136['ttl'] = $_POST['ttl'];
		$rfc2136['keyname'] = $_POST['keyname'];
		$rfc2136['keyalgorithm'] = $_POST['keyalgorithm'];
		$rfc2136['keydata'] = $_POST['keydata'];
		$rfc2136['server'] = $_POST['server'];
		$rfc2136['usetcp'] = $_POST['usetcp'] ? true : false;
		$rfc2136['usepublicip'] = $_POST['usepublicip'] ? true : false;
		$rfc2136['recordtype'] = $_POST['recordtype'];
		$rfc2136['interface'] = $_POST['interface'];
		$rfc2136['updatesource'] = $_POST['updatesource'];
		$rfc2136['updatesourcefamily'] = $_POST['updatesourcefamily'];
		$rfc2136['descr'] = $_POST['descr'];

		if ($this_rfc2136_config && !$dup) {
			config_set_path("dnsupdates/dnsupdate/{$id}", $rfc2136);
		} else {
			config_set_path('dnsupdates/dnsupdate/', $rfc2136);
		}

		write_config(gettext("New/Edited RFC2136 dnsupdate entry was posted."));

		if ($_POST['force']) {
			$retval = services_dnsupdate_process("", $rfc2136['host'], true);
		} else {
			$retval = services_dnsupdate_process();
		}

		header("Location: services_rfc2136.php");
		exit;
	}
}

function build_if_list() {
	$list = array();

	$iflist = get_configured_interface_with_descr();

	foreach ($iflist as $if => $ifdesc) {
		$list[$if] = $ifdesc;
	}

	unset($iflist);

	$grouplist = return_gateway_groups_array();

	foreach ($grouplist as $name => $group) {
		$list[$name] = 'GW Group ' . $name;
	}

	unset($grouplist);

	return($list);
}

function build_us_list() {
	$list = array(
		'' => 'Default (use Interface above)',
		'none' => 'Do not specify',
	);

	return(array_merge($list, get_possible_listen_ips()));
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS"), gettext("RFC 2136 Clients"), gettext("Edit"));
$pglinks = array("", "services_dyndns.php", "services_rfc2136.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;

$section = new Form_Section('RFC 2136 Client');

$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	null,
	$pconfig['enable']
));

$iflist = build_if_list();

$section->addInput(new Form_Select(
	'interface',
	'*Interface',
	$pconfig['interface'],
	$iflist
))->setHelp('Interface to monitor for updates. The address of this interface will be used in the updated DNS record.');

$section->addInput(new Form_Input(
	'host',
	'*Hostname',
	'text',
	$pconfig['host']
))->setHelp('Fully qualified hostname of the host to be updated.');

$section->addInput(new Form_Input(
	'zone',
	'Zone',
	'text',
	$pconfig['zone']
))->setHelp('Hostname zone (optional).');

$section->addInput(new Form_Input(
	'ttl',
	'*TTL (seconds)',
	'number',
	$pconfig['ttl']
));

$section->addInput(new Form_Input(
	'keyname',
	'*Key name',
	'text',
	$pconfig['keyname']
))->setHelp('This must match the setting on the DNS server.');

$section->addInput(new Form_Select(
	'keyalgorithm',
	'*Key algorithm',
	$pconfig['keyalgorithm'],
	$tsig_key_algos
));

$section->addInput(new Form_Input(
	'keydata',
	'*Key',
	'text',
	$pconfig['keydata']
))->setHelp('Secret TSIG domain key.');

$section->addInput(new Form_Input(
	'server',
	'Server',
	'text',
	$pconfig['server']
));

$section->addInput(new Form_Checkbox(
	'usetcp',
	'Protocol',
	'Use TCP instead of UDP',
	$pconfig['usetcp']
));

$section->addInput(new Form_Checkbox(
	'usepublicip',
	'Use public IP',
	'If the interface IP is private, attempt to fetch and use the public IP instead.',
	$pconfig['usepublicip']
));

$uslist = build_us_list();

$section->addInput(new Form_Select(
	'updatesource',
	'Update Source',
	$pconfig['updatesource'],
	$uslist
))->setHelp('Interface or address from which the firewall will send the DNS update request.');

$section->addInput(new Form_Select(
	'updatesourcefamily',
	'Update Source Family',
	$pconfig['updatesourcefamily'],
	array(
		'' => 'Default',
		'inet' => 'IPv4',
		'inet6' => 'IPv6',
	)
))->setHelp('Address family to use for sourcing updates.');

$group = new Form_Group('*Record Type');

$group->add(new Form_Checkbox(
	'recordtype',
	'Record Type',
	'A (IPv4)',
	($pconfig['recordtype'] == 'A'),
	'A'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'recordtype',
	'Record Type',
	'AAAA (IPv6)',
	($pconfig['recordtype'] == 'AAAA'),
	'AAAA'
))->displayAsRadio();

$group->add(new Form_Checkbox(
	'recordtype',
	'Record Type',
	'Both',
	($pconfig['recordtype'] == 'both'),
	'both'
))->displayAsRadio();

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if ($this_rfc2136_config) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));

	$form->addGlobal(new Form_Button(
		'force',
		'Save & Force Update',
		null,
		'fa-solid fa-arrows-rotate'
	))->addClass('btn-info');
}

$form->add($section);
print($form);

print_info_box(sprintf(gettext('A DNS server must be configured in %1$sSystem: ' .
					'General Setup %2$sor allow the DNS server list to be overridden ' .
					'by DHCP/PPP on WAN for dynamic DNS updates to work.'), '<a href="system.php">', '</a>'));

include("foot.inc");
