<?php
/*
 * services_dnsmasq_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-dnsforwarder-edithost
##|*NAME=Services: DNS Forwarder: Edit host
##|*DESCR=Allow access to the 'Services: DNS Forwarder: Edit host' page.
##|*MATCH=services_dnsmasq_edit.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('dnsmasq', 'hosts'));
$a_hosts = &$config['dnsmasq']['hosts'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}


if (isset($id) && $a_hosts[$id]) {
	$pconfig['host'] = $a_hosts[$id]['host'];
	$pconfig['domain'] = $a_hosts[$id]['domain'];
	$pconfig['ip'] = $a_hosts[$id]['ip'];
	$pconfig['descr'] = $a_hosts[$id]['descr'];
	$pconfig['aliases'] = $a_hosts[$id]['aliases'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "domain ip");
	$reqdfieldsn = array(gettext("Domain"), gettext("IP address"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['host']) {
		if (!is_hostname($_POST['host'])) {
			$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'. It may not start or end with '-'.");
		} else {
			if (!is_unqualified_hostname($_POST['host'])) {
				$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
			}
		}
	}

	if (($_POST['domain'] && !is_domain($_POST['domain']))) {
		$input_errors[] = gettext("A valid domain must be specified.");
	}

	if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
		$input_errors[] = gettext("A valid IP address must be specified.");
	}

	/* collect aliases */
	$aliases = array();

	if (!empty($_POST['aliashost0'])) {
		foreach ($_POST as $key => $value) {
			$entry = '';
			if (!substr_compare('aliashost', $key, 0, 9)) {
				$entry = substr($key, 9);
				$field = 'host';
			} elseif (!substr_compare('aliasdomain', $key, 0, 11)) {
				$entry = substr($key, 11);
				$field = 'domain';
			} elseif (!substr_compare('aliasdescription', $key, 0, 16)) {
				$entry = substr($key, 16);
				$field = 'description';
			}
			if (ctype_digit($entry)) {
				$aliases[$entry][$field] = $value;
			}
		}
		$pconfig['aliases']['item'] = $aliases;

		/* validate aliases */
		foreach ($aliases as $idx => $alias) {
			$aliasreqdfields = array('aliasdomain' . $idx);
			$aliasreqdfieldsn = array(gettext("Alias Domain"));

			do_input_validation($_POST, $aliasreqdfields, $aliasreqdfieldsn, $input_errors);
			if ($alias['host']) {
				if (!is_hostname($alias['host'])) {
					$input_errors[] = gettext("Hostnames in an alias list can only contain the characters A-Z, 0-9 and '-'. They may not start or end with '-'.");
				} else {
					if (!is_unqualified_hostname($alias['host'])) {
						$input_errors[] = gettext("A valid alias hostname is specified, but the domain name part should be omitted");
					}
				}
			}

			if (($alias['domain'] && !is_domain($alias['domain']))) {
				$input_errors[] = gettext("A valid domain must be specified in alias list.");
			}
		}
	}

	/* check for overlaps */
	foreach ($a_hosts as $hostent) {
		if (isset($id) && ($a_hosts[$id]) && ($a_hosts[$id] === $hostent)) {
			continue;
		}

		if (($hostent['host'] == $_POST['host']) &&
		    ($hostent['domain'] == $_POST['domain'])) {
			if (is_ipaddrv4($hostent['ip']) && is_ipaddrv4($_POST['ip'])) {
				$input_errors[] = gettext("This host/domain override combination already exists with an IPv4 address.");
				break;
			}
			if (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($_POST['ip'])) {
				$input_errors[] = gettext("This host/domain override combination already exists with an IPv6 address.");
				break;
			}
		}
	}

	if (!$input_errors) {
		$hostent = array();
		$hostent['host'] = $_POST['host'];
		$hostent['domain'] = $_POST['domain'];
		$hostent['ip'] = $_POST['ip'];
		$hostent['descr'] = $_POST['descr'];
		$hostent['aliases']['item'] = $aliases;

		if (isset($id) && $a_hosts[$id]) {
			$a_hosts[$id] = $hostent;
		} else {
			$a_hosts[] = $hostent;
		}

		mark_subsystem_dirty('hosts');

		write_config("DNS Forwarder host override saved");

		header("Location: services_dnsmasq.php");
		exit;
	}
}

// Delete a row in the options table
if ($_POST['act'] == "delopt") {
	$idx = $_POST['id'];

	if ($pconfig['aliases'] && is_array($pconfig['aliases']['item'][$idx])) {
	   unset($pconfig['aliases']['item'][$idx]);
	}
}

// Add an option row
if ($_REQUEST['act'] == "addopt") {
    if (!is_array($pconfig['aliases']['item'])) {
        $pconfig['aliases']['item'] = array();
	}

	array_push($pconfig['aliases']['item'], array('host' => null, 'domain' => null, 'description' => null));
}

$pgtitle = array(gettext("Services"), gettext("DNS Forwarder"), gettext("Edit Host Override"));
$pglinks = array("", "services_dnsmasq.php", "@self");
$shortcut_section = "forwarder";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('Host Override Options');

$section->addInput(new Form_Input(
	'host',
	'Host',
	'text',
	$pconfig['host']
))->setHelp('Name of the host, without the domain part%1$s' .
			'e.g.: "myhost"', '<br />');

$section->addInput(new Form_Input(
	'domain',
	'*Domain',
	'text',
	$pconfig['domain']
))->setHelp('Domain of the host%1$s' .
			'e.g.: "example.com"', '<br />');

$section->addInput(new Form_IpAddress(
	'ip',
	'*IP Address',
	$pconfig['ip']
))->setHelp('IP address of the host%1$s' .
			'e.g.: 192.168.100.100 or fd00:abcd::1', '<br />');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

if (isset($id) && $a_hosts[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);

$section = new Form_Section('Additional Names for this Host');

if (!is_array($pconfig['aliases'])) {
	$pconfig['aliases'] = array();
}

if (!$pconfig['aliases']['item']) {
	$pconfig['aliases']['item'] = array('host' => "");
}

if ($pconfig['aliases']['item']) {
	$counter = 0;
	$last = count($pconfig['aliases']['item']) - 1;

	foreach ($pconfig['aliases']['item'] as $item) {
		$group = new Form_Group(null);
		$group->addClass('repeatable');

		$group->add(new Form_Input(
			'aliashost' . $counter,
			null,
			'text',
			$item['host']
		))->setHelp($counter == $last ? 'Host name':null);

		$group->add(new Form_Input(
			'aliasdomain' . $counter,
			null,
			'text',
			$item['domain']
		))->setHelp($counter == $last ? 'Domain':null);

		$group->add(new Form_Input(
			'aliasdescription' . $counter,
			null,
			'text',
			$item['description']
		))->setHelp($counter == $last ? 'Description':null);

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete',
			null,
			'fa-trash'
		))->addClass('btn-warning')->addClass('nowarn');

		$section->add($group);
		$counter++;
	}
}

$form->addGlobal(new Form_Button(
	'addrow',
	'Add Host Name',
	null,
	'fa-plus'
))->addClass('btn-success addbtn');

$form->add($section);
print($form);

include("foot.inc");
