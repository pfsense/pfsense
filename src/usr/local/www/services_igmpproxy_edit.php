<?php
/*
	services_igmpproxy_edit.php
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
##|*IDENT=page-services-igmpproxy-edit
##|*NAME=Services: IGMP Proxy: Edit
##|*DESCR=Allow access to the 'Services: IGMP Proxy: Edit' page.
##|*MATCH=services_igmpproxy_edit.php*
##|-PRIV

$pgtitle = array(gettext("Services"), gettext("IGMP Proxy"), gettext("Edit"));

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
	$x = 0;
	while ($_POST["address{$x}"]) {

		if ($isfirst > 0) {
			$address .= " ";
		}

		$address .= $_POST["address{$x}"];
		$address .= "/" . $_POST["address_subnet{$x}"];
		$isfirst++;
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
			'Downstream network interfaces are the distribution	interfaces to the destination networks, where multicast clients can join groups and '.
			'receive multicast data. One or more downstream interfaces must be configured.');

$section->addInput(new Form_Input(
	'threshold',
	'Threshold',
	'text',
	$pconfig['threshold']
))->setHelp('Defines the TTL threshold for the network interface. Packets with a lower TTL than the threshold value will be ignored. ' .
			'This setting is optional, and by default the threshold is 1.');

if (isset($id) && $a_igmpproxy[$id]) {
		$section->addInput(new Form_Input(
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
	))->sethelp($tracker == $rows ? 'Network/CIDR':null)->addMask('address_subnet' . $tracker, $address_subnet)->setWidth(4)->setPattern('[a-zA-Z0-9\_\.\:]+');

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$counter++;
	$section->add($group);
} // end foreach

$section->addInput(new Form_Button(
	'addrow',
	'Add network'
))->removeClass('btn-primary')->addClass('btn-success addbtn');

$form->add($section);

print($form);

include("foot.inc");
