<?php
/*
 * firewall_nat_nat64_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014-2020 Nick Whaley
 * Copyright (c) 2011 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-firewall-nat-nat64-edit
##|*NAME=Firewall: NAT: NAT64: Edit
##|*DESCR=Allow access to the 'Firewall: NAT: NAT64: Edit' page.
##|*MATCH=firewall_nat_nat64_edit.php
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");

init_config_arr(array('nat', 'nat64', 'rule'));
unset($a_nat64);
$a_nat64 = &$config['nat']['nat64']['rule'];

//defaults
$pconfig['prefix4'] = '';
$pconfig['prefix4mask'] = 24;
$pconfig['prefix6'] = '64:ff9b::';
$pconfig['prefix4mask'] = 96;
//$pconfig['allowrfc1918'];
//$pconfig['max_ports'] = 2048;
//$pconfig['host_del_age'] = 3600;
//$pconfig['pg_del_age'] = 900;
//$pconfig['tcp_syn_age'] = 10; 
//$pconfig['tcp_est_age'] = 7200;
//$pconfig['tcp_close_age'] = 180;
//$pconfig['udp_age'] = 120;
//$pconfig['icmp_age'] = 60;

if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($_REQUEST['after'])) {
	$after = $_REQUEST['after'];
}

if (isset($_REQUEST['dup'])) {
	$id = $_REQUEST['dup'];
	$after = $_REQUEST['dup'];
}

if (isset($id) && $a_nat64[$id]) {
	$pconfig['disabled'] = isset($a_nat64[$id]['disabled']);
	$pconfig['allowrfc1918'] = isset($a_nat64[$id]['allowrfc1918']);
	$pconfig['max_ports'] = $a_nat64[$id]['max_ports'];
	$pconfig['host_del_age'] = $a_nat64[$id]['host_del_age'];
	$pconfig['pg_del_age'] = $a_nat64[$id]['pg_del_age'];
	$pconfig['tcp_syn_age'] = $a_nat64[$id]['tcp_syn_age'];
	$pconfig['tcp_est_age'] = $a_nat64[$id]['tcp_est_age'];
	$pconfig['tcp_close_age'] = $a_nat64[$id]['tcp_close_age'];
	$pconfig['udp_age'] = $a_nat64[$id]['udp_age'];
	$pconfig['icmp_age'] = $a_nat64[$id]['icmp_age'];

	$dummyvar = 0;
	address_to_pconfig($a_nat64[$id]['prefix4'], $pconfig['prefix4'], $pconfig['prefix4mask'],
		$dummyvar, $dummyvar, $dummyvar);
	address_to_pconfig($a_nat64[$id]['prefix6'], $pconfig['prefix6'], $pconfig['prefix6mask'],
		$dummyvar, $dummyvar, $dummyvar);

	$pconfig['descr'] = $a_nat64[$id]['descr'];
}

if (isset($_REQUEST['dup'])) {
	unset($id);
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields[] = "prefix4";
	$reqdfieldsn[] = gettext("IPv4 prefix");
	$reqdfields[] = "prefix6";
	$reqdfieldsn[] = gettext("IPv6 prefix");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddrv4(trim($_POST['prefix4'])))
		$input_errors[] = gettext("The specified IPv4 prefix is not valid.");
	if (!is_ipaddrv6(trim($_POST['prefix6'])))
		$input_errors[] = gettext("The specified IPv6 prefix is not valid.");

	if (!in_array($_POST['prefix6mask'], array(32, 40, 48, 56, 64, 96)))
		$input_errors[] = gettext("IPv6 prefix mask must be one of the following: 32, 40, 48, 56, 64, or 96.");
	if (ip6_to_bin(trim($_POST['prefix6'])) == ip6_to_bin('64:ff9b::') && $_POST['prefix6mask'] != 96)
		$input_errors[] = gettext("The Well-Known IPv6 Prefix 64:ff9b:: must be 96 bits long.");

	if (!$input_errors) {
		$natent = array();

		$natent['disabled'] = isset($_POST['disabled']) ? true:false;
		$natent['allowrfc1918'] = isset($_POST['allowrfc1918']) ? true:false;
		$natent['max_ports'] = !empty($_POST['max_ports']) ? trim($_POST['max_ports']):false;
		$natent['host_del_age'] = !empty($_POST['host_del_age']) ? trim($_POST['host_del_age']):false;
		$natent['pg_del_age'] = !empty($_POST['pg_del_age']) ? trim($_POST['pg_del_age']):false;
		$natent['tcp_syn_age'] = !empty($_POST['tcp_syn_age']) ? trim($_POST['tcp_syn_age']):false;
		$natent['tcp_close_age'] = !empty($_POST['tcp_close_age']) ? trim($_POST['tcp_close_age']):false;
		$natent['tcp_est_age'] = !empty($_POST['tcp_est_age']) ? trim($_POST['tcp_est_age']):false;
		$natent['udp_age'] = !empty($_POST['udp_age']) ? trim($_POST['udp_age']):false;
		$natent['icmp_age'] = !empty($_POST['icmp_age']) ? trim($_POST['icmp_age']):false;
		$natent['descr'] = $_POST['descr'];

		if ($_POST['prefix4'])
			$_POST['prefix4'] = trim($_POST['prefix4']);
		if ($_POST['prefix6'])
			$_POST['prefix6'] = trim($_POST['prefix6']);

		pconfig_to_address($natent['prefix4'], $_POST['prefix4'], $_POST['prefix4mask']);

		pconfig_to_address($natent['prefix6'], $_POST['prefix6'], $_POST['prefix6mask']);

		if (isset($id) && $a_nat64[$id]) {
			$a_nat64[$id] = $natent;
		} else {
			if (is_numeric($after)) {
				array_splice($a_nat64, $after+1, 0, array($natent));
			} else {
				$a_nat64[] = $natent;
			}
		}

		if (write_config(gettext("Firewall: NAT: NAT64 - saved/edited NAT64 mapping."))) {
			mark_subsystem_dirty('nat64');
		}
		
		header("Location: firewall_nat_nat64.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("NAT64"), gettext("Edit"));
$pglinks = array("", "firewall_nat.php", "firewall_nat_nat64.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Edit NAT64 Entry');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this rule',
	$pconfig['disabled']
));

$section->addInput(new Form_IpAddress(
	'prefix4',
	'*IPv4 Addresses',
	$pconfig['prefix4'],
	'V4'
))->addMask('prefix4mask', $pconfig['prefix4mask'])->setHelp('The IPv4 prefix with mask defines the pool of IPv4 '
.'addresses used as source address after translation. Stateful NAT64 module translates IPv6 source address of client to '
.'one IPv4 address from this pool. Note that incoming IPv4 packets that don\'t have corresponding state entry in the states '
.'table will be dropped by translator. Make sure that translation rules handle packets, destined to configured prefix. ');

$section->addInput(new Form_IpAddress(
	'prefix6',
	'*IPv6 Addresses',
	$pconfig['prefix6'],
	'V6'
))->addMask('prefix6mask', $pconfig['prefix6mask'])->setHelp('The IPv6 prefix defines IPv4-embedded IPv6 addresses '.
'used by translator to represent IPv4 addresses. This IPv6 prefix should be configured in DNS64. The translator '.
'implementation follows RFC6052, that restricts the length of prefixes to one of following: 32, 40, 48, 56, 64, or 96. '.
'The Well-Known IPv6 Prefix 64:ff9b:: must be 96 bits long. ');
	
$section->addInput(new Form_Checkbox(
	'allowrfc1918',
	'Allow RFC1918',
	'Allow private RFC1918 addresses',
	$pconfig['allowrfc1918']
))->setHelp('Turn on processing private IPv4 addresses. '.
'By default IPv6 packets with destinations mapped to private address ranges defined by RFC1918 are not processed. ');

$section->addInput(new Form_Input(
	'max_ports',
	'Maximum Ports',
	'number',
	$pconfig['max_ports'],	
	['placeholder' => '2048']
))->setHelp('Maximum number of ports reserved for upper level protocols to one IPv6 client. All reserved ports are divided '.
'into chunks between supported protocols. The number of connections from one IPv6 client is limited by this option. Note '.
'that closed TCP connections still remain in the list of connections until tcp_close_age interval will not expire. Default '.
'value is 2048.');

$section->addInput(new Form_Input(
	'host_del_age',
	'IPv6 Host Age',
	'number',
	$pconfig['host_del_age'],	
	['placeholder' => '3600']
))->setHelp('The number of seconds until the host entry for a IPv6 client will be deleted and all its resources will be '.
'released due to inactivity. Default value is 3600.');
 		
$section->addInput(new Form_Input(
	'pg_del_age',
	'Port Group Age',
	'number',
	$pconfig['pg_del_age'],	
	['placeholder' => '900']
))->setHelp('The number of seconds until a ports group with unused state entries will be released. Default value is 900.');
 	
$section->addInput(new Form_Input(
	'tcp_syn_age',
	'TCP SYN Age',
	'number',
	$pconfig['tcp_syn_age'],	
	['placeholder' => '10']
))->setHelp('The number of seconds while a state entry for TCP connection with only SYN sent will be kept. If TCP connection '
.'establishing will not be finished, state entry will be deleted. Default value is 10.');
 	
$section->addInput(new Form_Input(
	'tcp_close_age',
	'TCP FIN Age',
	'number',
	$pconfig['tcp_close_age'],	
	['placeholder' => '180']
))->setHelp('The number of seconds while a state entry for closed TCP connection will be kept. Keeping state entries for '.
'closed connections is needed, because IPv4 servers typically keep closed connections in a TIME_WAIT state for a several '.
'minutes. Since translator\'s IPv4 addresses are shared among all IPv6 clients, new connections from the same addresses and '.
'ports may be rejected by server, because these connections are still in a TIME_WAIT state. Keeping them in translator\'s '.
'state table protects from such rejects. Default value is 180.');
	
$section->addInput(new Form_Input(
	'tcp_est_age',
	'TCP Established Age',
	'number',
	$pconfig['tcp_est_age'],	
	['placeholder' => '7200']
))->setHelp('The number of seconds while a state entry for established TCP connection will be kept. Default value is 7200.');

$section->addInput(new Form_Input(
	'udp_age',
	'UDP Age',
	'number',
	$pconfig['udp_age'],	
	['placeholder' => '120']
))->setHelp('The number of seconds while translator keeps state entry in a waiting for reply to the sent UDP datagram. Default value is 120.');

$section->addInput(new Form_Input(
	'icmp_age',
	'ICMP Age',
	'number',
	$pconfig['icmp_age'],	
	['placeholder' => '60']
))->setHelp('The number of seconds while translator keeps state entry in a waiting for reply to the sent ICMP message. Default value is 60.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_nat64[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
