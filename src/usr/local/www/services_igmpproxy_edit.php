<?php
/* $Id$ */
/*
	services_igmpproxy_edit.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE: igmpproxy
*/

##|+PRIV
##|*IDENT=page-services-igmpproxy-edit
##|*NAME=Firewall: Igmpproxy: Edit page
##|*DESCR=Allow access to the 'Services: Igmpproxy: Edit' page.
##|*MATCH=services_igmpproxy_edit.php*
##|-PRIV

$pgtitle = array(gettext("Firewall"), gettext("IGMP Proxy"), gettext("Edit"));

require("guiconfig.inc");

if (!is_array($config['igmpproxy']['igmpentry'])) {
	$config['igmpproxy']['igmpentry'] = array();
}

//igmpproxy_sort();
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_igmpproxy[$id]) {
	$pconfig['ifname'] = $a_igmpproxy[$id]['ifname'];
	$pconfig['threshold'] = $a_igmpproxy[$id]['threshold'];
	$pconfig['type'] = $a_igmpproxy[$id]['type'];
	$pconfig['address'] = $a_igmpproxy[$id]['address'];
	$pconfig['descr'] = html_entity_decode($a_igmpproxy[$id]['descr']);
}

// Add a row to the network table
if($_GET['act'] && $_GET['act'] == 'addrow')
	$pconfig['address'] .= '/32';

// Remove a row from the network table
if($_GET['act'] && $_GET['act'] == 'delrow') {
	$row = $_GET['row'];

	$addresses = explode(" ", $pconfig['address']);

	$pconfig['address'] = "";

	$idx = 0;
	foreach($addresses as $address) {
		if($idx != $row)
			$pconfig['address'] .= ($idx > 0 ? ' ':null) . $address;

		$idx++;
	}
}

if ($_POST) {
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

	$igmpentry = array();
	$igmpentry['ifname'] = $_POST['ifname'];
	$igmpentry['threshold'] = $_POST['threshold'];
	$igmpentry['type'] = $_POST['type'];
	$address = "";
	$isfirst = 0;
	/* item is a normal igmpentry type */
	for ($x = 0; $x < 4999; $x++) {
		if ($_POST["address{$x}"] <> "") {
			if ($isfirst > 0) {
				$address .= " ";
			}
			$address .= $_POST["address{$x}"];
			$address .= "/" . $_POST["address_subnet{$x}"];
			$isfirst++;
		}
	}

	if (!$input_errors) {
		$igmpentry['address'] = $address;
		$igmpentry['descr'] = $_POST['descr'];

		if (isset($id) && $a_igmpproxy[$id]) {
			$a_igmpproxy[$id] = $igmpentry;
		} else {
			$a_igmpproxy[] = $igmpentry;
		}

		write_config();

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

if ($input_errors)
	print_input_errors($input_errors);

require_once('classes/Form.class.php');

// These two inputs appear inthe original file. Don't know what they are for
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

foreach ($iflist as $ifnam => $ifdescr)
	$optionlist[$ifnam] = $ifdescr;

$section->addInput(new Form_Select(
	'ifname',
	'Interface',
	$pconfig['ifname'],
	$optionlist
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$section->addInput(new Form_Select(
	'type',
	'Type',
	$pconfig['type'],
	['upstream' => gettext('Upstream Interface'), 'downstream' => gettext('Downstream Interface')]
))->setHelp('The upstream network interface is the outgoing interface which is responsible for communicating to available multicast data sources .' .
			'There can only be one upstream interface.' . '<br />' .
			'Downstream network interfaces are the distribution	 interfaces to	the destination	 networks, where multicast clients can join groups and '.
			'receive multicast data. One or more downstream interfaces must be configured.');

$section->addInput(new Form_Input(
	'threshold',
	'Threshold',
	'text',
	$pconfig['threshold']
))->setHelp('Defines the TTL threshold for the network interface. Packets with a lower TTL than the threshold value will be ignored. ' .
			'This setting is optional, and by default the threshold is 1.');

if (isset($id) && $a_igmpproxy[$id]){
		$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$counter = 0;
$address = $pconfig['address'];

if ($address != "") {
	$item = explode(" ", $address);
	$rows = count($item) -1;
	foreach($item as $ww) {
		$address = $item[$counter];
		$address_subnet = "";
		$item2 = explode("/", $address);
		foreach($item2 as $current) {
			if($item2[1] != "") {
				$address = $item2[0];
				$address_subnet = $item2[1];
			}
		}
		$item4 = $item3[$counter];
		$tracker = $counter;

		$group = new Form_group($tracker == 0? 'Network':null);

		$group->add(new Form_Input(
			'address' . $tracker,
			null,
			'text',
			$address,
			['placeholder' => 'Address']
		))->sethelp($tracker == $rows ? 'Network':null);

		$group->add(new Form_Select(
			'ifname',
			'Interface',
			$address_subnet,
			array_combine(range(32, 1, -1), range(32, 1, -1))
		))->sethelp($tracker == $rows ? 'CIDR':null);;

		$btndel = new Form_Button (
			'removerow',
			'Remove',
			'services_igmpproxy_edit.php?act=delrow&row=' . $tracker
			);

		$btndel->removeClass('btn-primary')->addClass('btn-danger btn-sm');
		$group->add($btndel);

			$counter++;
			$section->add($group);
	} // end foreach
} // end if

$btnadd = new Form_Button (
		'addrow',
		'Add Network',
		'services_igmpproxy_edit.php?act=addrow'
		);

$btnadd->removeClass('btn-primary')->addClass('btn-success btn-sm');

$section->addInput(new Form_StaticText(
	null,
	$btnadd . ' (Save after each Add or Delete)'
));

$form->add($section);

print($form);

include("foot.inc");