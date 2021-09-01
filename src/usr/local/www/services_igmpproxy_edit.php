<?php
/*
 * services_igmpproxy_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-services-igmpproxy-edit
##|*NAME=Services: IGMP Proxy: Edit
##|*DESCR=Allow access to the 'Services: IGMP Proxy: Edit' page.
##|*MATCH=services_igmpproxy_edit.php*
##|-PRIV

$pgtitle = array(gettext("Services"), gettext("IGMP Proxy"), gettext("Edit"));
$pglinks = array("", "services_igmpproxy.php", "@self");

require_once("guiconfig.inc");

//igmpproxy_sort();

init_config_arr(array('igmpproxy', 'igmpentry'));
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_igmpproxy[$id]) {
	$pconfig['ifname'] = $a_igmpproxy[$id]['ifname'];
	$pconfig['threshold'] = $a_igmpproxy[$id]['threshold'];
	$pconfig['type'] = $a_igmpproxy[$id]['type'];
	$pconfig['address'] = $a_igmpproxy[$id]['address'];
	$pconfig['descr'] = html_entity_decode($a_igmpproxy[$id]['descr']);
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['type'] == "upstream") {
		foreach ($a_igmpproxy as $pid => $proxyentry) {
			if (isset($id) && $id == $pid) {
				continue;
			}

			if ($proxyentry['type'] == "upstream" && $proxyentry['ifname'] != $_POST['interface']) {
				$input_errors[] = gettext("Only one 'upstream' interface can be configured.");
			}
		}
	}

	if (!empty($_POST['threshold']) && (!is_numeric($_POST['threshold']) ||
	    ($_POST['threshold'] < -1) || ($_POST['threshold'] > 256))) {
		$input_errors[] = gettext("Threshold value should be between -1 and 256.");
	} 

	$igmpentry = array();
	$igmpentry['ifname'] = $_POST['ifname'];
	$igmpentry['threshold'] = $_POST['threshold'];
	$igmpentry['type'] = $_POST['type'];
	$address = "";
	$isfirst = 0;

	/* item is a normal igmpentry type */
	$x = 0;
	while ($_POST["address{$x}"]) {

		if ($isfirst > 0) {
			$address .= " ";
		}

		$this_addr =  $_POST["address{$x}"] . "/" . $_POST["address_subnet{$x}"];
		if (is_subnetv4($this_addr)) {
			$address .= $this_addr;
			$isfirst++;
		} else {
			$input_errors[] = sprintf(gettext("The following submitted address is invalid: %s"), $this_addr);
		}

		$x++;
	}

	if (!$input_errors) {
		$igmpentry['address'] = $address;
		$igmpentry['descr'] = $_POST['descr'];

		if (isset($id) && $a_igmpproxy[$id]) {
			$a_igmpproxy[$id] = $igmpentry;
		} else {
			$a_igmpproxy[] = $igmpentry;
		}

		write_config("IGMP Proxy item saved");

		mark_subsystem_dirty('igmpproxy');
		header("Location: services_igmpproxy.php");
		exit;
	} else {
		//we received input errors, copy data to prevent retype
		$pconfig['descr'] = $_POST['descr'];
		$pconfig['address'] = $address;
		$pconfig['type'] = $_POST['type'];
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

// These two inputs appear in the original file. Don't know what they are for
// but they are here just in case.

$h1 = new Form_Input(
	'address_type',
	null,
	'textbox',
	'hidden'
	);

$h2 = new Form_Input(
	'address_subnet_type',
	null,
	'select',
	'hidden'
	);

$form = new Form;

$section = new Form_Section('IGMP Proxy Edit');

$optionlist = array();
$iflist = get_configured_interface_with_descr();

foreach ($iflist as $ifnam => $ifdescr) {
	$optionlist[$ifnam] = $ifdescr;
}

$section->addInput(new Form_Select(
	'ifname',
	'*Interface',
	$pconfig['ifname'],
	$optionlist
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Select(
	'type',
	'*Type',
	$pconfig['type'],
	['upstream' => gettext('Upstream Interface'), 'downstream' => gettext('Downstream Interface')]
))->setHelp('The upstream network interface is the outgoing interface which is responsible for communicating to available multicast data sources. ' .
			'There can only be one upstream interface.%1$s' .
			'Downstream network interfaces are the distribution	interfaces to the destination networks, where multicast clients can join groups and '.
			'receive multicast data. One or more downstream interfaces must be configured.', '<br />');

$section->addInput(new Form_Input(
	'threshold',
	'Threshold',
	'text',
	$pconfig['threshold']
))->setHelp('Defines the TTL threshold for the network interface. Packets with a lower TTL than the threshold value will be ignored. ' .
			'This setting is optional, and by default the threshold is 1.');

if (isset($id) && $a_igmpproxy[$id]) {
		$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$counter = 0;
$address = $pconfig['address'];

//if ($address == "") {
//	$address = "/";
//}

$item = explode(" ", $address);
$rows = count($item) -1;

foreach ($item as $ww) {
	$address = $item[$counter];
	$address_subnet = "";
	$item2 = explode("/", $address);

	foreach ($item2 as $current) {
		if ($item2[1] != "") {
			$address = $item2[0];
			$address_subnet = $item2[1];
		}
	}

	$item4 = $item3[$counter];
	$tracker = $counter;

	$group = new Form_group($tracker == 0? 'Networks':null);
	$group->addClass("repeatable");

	$group->add(new Form_IpAddress(
		'address' . $tracker,
		null,
		$address,
		['placeholder' => 'Address']
	))->sethelp($tracker == $rows ? 'Network/CIDR':null)->addMask('address_subnet' . $tracker, $address_subnet, 32)->setWidth(4);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete',
		null,
		'fa-trash'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$counter++;
	$section->add($group);
} // end foreach

$section->addInput(new Form_Button(
	'addrow',
	'Add network',
	null,
	'fa-plus'
))->removeClass('btn-primary')->addClass('btn-success addbtn');

$form->add($section);

print($form);

include("foot.inc");
