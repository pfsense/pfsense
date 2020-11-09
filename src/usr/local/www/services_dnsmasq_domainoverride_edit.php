<?php
/*
 * services_dnsmasq_domainoverride_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Bob Zoller <bob@kludgebox.com>
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
##|*IDENT=page-services-dnsforwarder-editdomainoverride
##|*NAME=Services: DNS Forwarder: Edit Domain Override
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit Domain Override' page.
##|*MATCH=services_dnsmasq_domainoverride_edit.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('dnsmasq', 'domainoverrides'));
$a_domainOverrides = &$config['dnsmasq']['domainoverrides'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_domainOverrides[$id]) {
	$pconfig['domain'] = $a_domainOverrides[$id]['domain'];
	if (is_ipaddr($a_domainOverrides[$id]['ip']) && ($a_domainOverrides[$id]['ip'] != '#')) {
		$pconfig['ip'] = $a_domainOverrides[$id]['ip'];
	} else {
		$dnsmasqpieces = explode('@', $a_domainOverrides[$id]['ip'], 2);
		$pconfig['ip'] = $dnsmasqpieces[0];
		$pconfig['dnssrcip'] = $dnsmasqpieces[1];
	}
	$pconfig['descr'] = $a_domainOverrides[$id]['descr'];
}

if ($_POST['save']) {
		unset($input_errors);
		$pconfig = $_POST;

		/* input validation */
		$reqdfields = explode(" ", "domain ip");
		$reqdfieldsn = array(gettext("Domain"), gettext("IP address"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		function String_Begins_With($needle, $haystack) {
			return (substr($haystack, 0, strlen($needle)) == $needle);
		}

		if (String_Begins_With('_msdcs', $_POST['domain'])) {
			$subdomainstr = substr($_POST['domain'], 7);

			if ($subdomainstr && !is_domain($subdomainstr)) {
				$input_errors[] = gettext("A valid domain must be specified after _msdcs.");
			}
		} elseif ($_POST['domain'] && !is_domain($_POST['domain'])) {
			$input_errors[] = gettext("A valid domain must be specified.");
		}

		if ($_POST['ip'] && !is_ipaddr($_POST['ip']) && ($_POST['ip'] != '#') && ($_POST['ip'] != '!')) {
			$input_errors[] = gettext("A valid IP address must be specified, or # for an exclusion or ! to not forward at all.");
		}

		if ($_POST['dnssrcip'] && !in_array($_POST['dnssrcip'], get_configured_ip_addresses())) {
			$input_errors[] = gettext("An interface IP address must be specified for the DNS query source.");
		}

		if (!$input_errors) {
			$doment = array();
			$doment['domain'] = $_POST['domain'];

			if (empty($_POST['dnssrcip'])) {
				$doment['ip'] = $_POST['ip'];
			} else {
				$doment['ip'] = $_POST['ip'] . "@" . $_POST['dnssrcip'];
			}

			$doment['descr'] = $_POST['descr'];

		if (isset($id) && $a_domainOverrides[$id]) {
			$a_domainOverrides[$id] = $doment;
		} else {
			$a_domainOverrides[] = $doment;
		}

		$retval = services_dnsmasq_configure();

		write_config();

		header("Location: services_dnsmasq.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"), gettext("DNS Forwarder"), gettext("Edit Domain Override"));
$pglinks = array("", "services_dnsmasq.php", "@self");
$shortcut_section = "forwarder";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Domain Override Options');

$section->addInput(new Form_Input(
	'domain',
	'*Domain',
	'text',
	$pconfig['domain']
))->setHelp('Domain to override (NOTE: this does not have to be a valid TLD!)%1$s' .
			'e.g.: test or nas.home.arpa or mycompany.localdomain or 1.168.192.in-addr.arpa', '<br />');

$section->addInput(new Form_IpAddress(
	'ip',
	'*IP Address',
	$pconfig['ip']
))->setPattern('[a-f0-9:.#!]*')
  ->setHelp('IP address of the authoritative DNS server for this domain%1$s' .
			'e.g.: 192.168.100.100%1$s' .
			'Or enter # for an exclusion to pass through this host/subdomain to standard nameservers instead of a previous override.%1$s' .
			'Or enter ! for lookups for this host/subdomain to NOT be forwarded anywhere.', '<br />');

$section->addInput(new Form_IpAddress(
	'dnssrcip',
	'Source IP',
	$pconfig['dnssrcip']
))->setHelp('Source IP address for queries to the DNS server for the override domain. Leave blank unless the DNS server is accessed through a VPN tunnel.');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_domainOverrides[$id]) {
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
