<?php
/*
	interfaces_bridge_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
/*
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-bridge-edit
##|*NAME=Interfaces: Bridge edit page
##|*DESCR=Allow access to the 'Interfaces: Bridge : Edit' page.
##|*MATCH=interfaces_bridge_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['bridges']['bridged']))
	$config['bridges']['bridged'] = array();

$a_bridges = &$config['bridges']['bridged'];

$ifacelist = get_configured_interface_with_descr();

foreach ($ifacelist as $bif => $bdescr) {
	if (substr(get_real_interface($bif), 0, 3) == "gre") {
		unset($ifacelist[$bif]);
	}
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_bridges[$id]) {
	$pconfig['enablestp'] = isset($a_bridges[$id]['enablestp']);
	$pconfig['descr'] = $a_bridges[$id]['descr'];
	$pconfig['bridgeif'] = $a_bridges[$id]['bridgeif'];
	$pconfig['members'] = $a_bridges[$id]['members'];
	$pconfig['maxaddr'] = $a_bridges[$id]['maxaddr'];
	$pconfig['timeout'] = $a_bridges[$id]['timeout'];
	if ($a_bridges[$id]['static']) {
		$pconfig['static'] = $a_bridges[$id]['static'];
	}
	if ($a_bridges[$id]['private']) {
		$pconfig['private'] = $a_bridges[$id]['private'];
	}
	if (isset($a_bridges[$id]['stp'])) {
		$pconfig['stp'] = $a_bridges[$id]['stp'];
	}
	$pconfig['maxage'] = $a_bridges[$id]['maxage'];
	$pconfig['fwdelay'] = $a_bridges[$id]['fwdelay'];
	$pconfig['hellotime'] = $a_bridges[$id]['hellotime'];
	$pconfig['priority'] = $a_bridges[$id]['priority'];
	$pconfig['proto'] = $a_bridges[$id]['proto'];
	$pconfig['holdcnt'] = $a_bridges[$id]['holdcnt'];

	if (!empty($a_bridges[$id]['ifpriority'])) {
		$pconfig['ifpriority'] = explode(",", $a_bridges[$id]['ifpriority']);
		$ifpriority = array();
		foreach ($pconfig['ifpriority'] as $cfg) {
			list ($key, $value) = explode(":", $cfg);
			$embprioritycfg[$key] = $value;
			foreach ($embprioritycfg as $key => $value) {
				$ifpriority[$key] = $value;
			}
		}
		$pconfig['ifpriority'] = $ifpriority;
	}

	if (!empty($a_bridges[$id]['ifpathcost'])) {
		$pconfig['ifpathcost'] = explode(",", $a_bridges[$id]['ifpathcost']);
		$ifpathcost = array();
		foreach ($pconfig['ifpathcost'] as $cfg) {
			list ($key, $value) = explode(":", $cfg);
			$embpathcfg[$key] = $value;
			foreach ($embpathcfg as $key => $value) {
				$ifpathcost[$key] = $value;
			}
		}
		$pconfig['ifpathcost'] = $ifpathcost;
	}

	$pconfig['span'] = $a_bridges[$id]['span'];
	if (isset($a_bridges[$id]['edge'])) {
		$pconfig['edge'] = $a_bridges[$id]['edge'];
	}
	if (isset($a_bridges[$id]['autoedge'])) {
		$pconfig['autoedge'] = $a_bridges[$id]['autoedge'];
	}
	if (isset($a_bridges[$id]['ptp'])) {
		$pconfig['ptp'] = $a_bridges[$id]['ptp'];
	}
	if (isset($a_bridges[$id]['autoptp'])) {
		$pconfig['autoptp'] = $a_bridges[$id]['autoptp'];
	}
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "members");
	$reqdfieldsn = array(gettext("Member Interfaces"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ($_POST['maxage'] && !is_numericint($_POST['maxage'])) {
		$input_errors[] = gettext("Maxage needs to be an integer between 6 and 40.");
	}
	if ($_POST['maxaddr'] && !is_numericint($_POST['maxaddr'])) {
		$input_errors[] = gettext("Maxaddr needs to be an integer.");
	}
	if ($_POST['timeout'] && !is_numericint($_POST['timeout'])) {
		$input_errors[] = gettext("Timeout needs to be an integer.");
	}
	if ($_POST['fwdelay'] && !is_numericint($_POST['fwdelay'])) {
		$input_errors[] = gettext("Forward Delay needs to be an integer between 4 and 30.");
	}
	if ($_POST['hellotime'] && !is_numericint($_POST['hellotime'])) {
		$input_errors[] = gettext("Hello time for STP needs to be an integer between 1 and 2.");
	}
	if ($_POST['priority'] && !is_numericint($_POST['priority'])) {
		$input_errors[] = gettext("Priority for STP needs to be an integer between 0 and 61440.");
	}
	if ($_POST['holdcnt'] && !is_numericint($_POST['holdcnt'])) {
		$input_errors[] = gettext("Transmit Hold Count for STP needs to be an integer between 1 and 10.");
	}
	foreach ($ifacelist as $ifn => $ifdescr) {
		if ($_POST[$ifn] <> "" && !is_numericint($_POST[$ifn])) {
			$input_errors[] = "{$ifdescr} " . gettext("interface priority for STP needs to be an integer between 0 and 240.");
		}
	}

	$i = 0;

	foreach ($ifacelist as $ifn => $ifdescr) {
		if ($_POST["{$ifn}{$i}"] <> "" && !is_numeric($_POST["{$ifn}{$i}"])) {
			$input_errors[] = "{$ifdescr} " . gettext("interface path cost for STP needs to be an integer between 1 and 200000000.");
		}
		$i++;
	}

	if (!is_array($_POST['members']) || count($_POST['members']) < 1) {
		$input_errors[] = gettext("You must select at least one member interface for a bridge.");
	}

	if (is_array($_POST['members'])) {
		foreach ($_POST['members'] as $ifmembers) {
			if (empty($config['interfaces'][$ifmembers])) {
				$input_errors[] = gettext("A member interface passed does not exist in configuration");
			}
			if (is_array($config['interfaces'][$ifmembers]['wireless']) &&
			    $config['interfaces'][$ifmembers]['wireless']['mode'] != "hostap") {
				$input_errors[] = gettext("Bridging a wireless interface is only possible in hostap mode.");
			}
			if ($_POST['span'] != "none" && $_POST['span'] == $ifmembers) {
				$input_errors[] = gettext("Span interface cannot be part of the bridge. Remove the span interface from bridge members to continue.");
			}
			foreach($a_bridges as $a_bridge) {
				if ($_POST['bridgeif'] === $a_bridge['bridgeif'])
					continue;
				$a_members = explode(',', $a_bridge['members']);
				foreach ($a_members as $a_member) {
					if ($ifmembers === $a_member)
						$input_errors[] = $ifmembers . gettext(" is part of another bridge. Remove the interface from bridge members to continue.");
				}
			}
		}
		$pconfig['members'] = implode(',', $_POST['members']);
	}

	if (!$input_errors) {
		$bridge = array();
		$bridge['members'] = implode(',', $_POST['members']);
		$bridge['enablestp'] = $_POST['enablestp'] ? true : false;
		$bridge['descr'] = $_POST['descr'];
		$bridge['maxaddr'] = $_POST['maxaddr'];
		$bridge['timeout'] = $_POST['timeout'];
		if ($_POST['static']) {
			$bridge['static'] = implode(',', $_POST['static']);
		}
		if ($_POST['private']) {
			$bridge['private'] = implode(',', $_POST['private']);
		}
		if (isset($_POST['stp'])) {
			$bridge['stp'] = implode(',', $_POST['stp']);
		}
		$bridge['maxage'] = $_POST['maxage'];
		$bridge['fwdelay'] = $_POST['fwdelay'];
		$bridge['hellotime'] = $_POST['hellotime'];
		$bridge['priority'] = $_POST['priority'];
		$bridge['proto'] = $_POST['proto'];
		$bridge['holdcnt'] = $_POST['holdcnt'];
		$i = 0;
		$ifpriority = "";
		$ifpathcost = "";

		foreach ($ifacelist as $ifn => $ifdescr) {
			if ($_POST[$ifn] <> "") {
				if ($i > 0) {
					$ifpriority .= ",";
				}
				$ifpriority .= $ifn.":".$_POST[$ifn];
			}
			if ($_POST["{$ifn}0"] <> "") {
				if ($i > 0) {
					$ifpathcost .= ",";
				}
				$ifpathcost .= $ifn.":".$_POST["{$ifn}0"];
			}
			$i++;
		}

		$bridge['ifpriority'] = $ifpriority;
		$bridge['ifpathcost'] = $ifpathcost;

		if ($_POST['span'] != "none") {
			$bridge['span'] = $_POST['span'];
		} else {
			unset($bridge['span']);
		}
		if (isset($_POST['edge'])) {
			$bridge['edge'] = implode(',', $_POST['edge']);
		}
		if (isset($_POST['autoedge'])) {
			$bridge['autoedge'] = implode(',', $_POST['autoedge']);
		}
		if (isset($_POST['ptp'])) {
			$bridge['ptp'] = implode(',', $_POST['ptp']);
		}
		if (isset($_POST['autoptp'])) {
			$bridge['autoptp'] = implode(',', $_POST['autoptp']);
		}


		$bridge['bridgeif'] = $_POST['bridgeif'];
		interface_bridge_configure($bridge);
		if ($bridge['bridgeif'] == "" || !stristr($bridge['bridgeif'], "bridge")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_bridges[$id]) {
				$a_bridges[$id] = $bridge;
			} else {
				$a_bridges[] = $bridge;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($bridge['bridgeif']);
			if ($confif <> "") {
				interface_configure($confif);
			}

			header("Location: interfaces_bridge.php");
			exit;
		}
	}
}

function build_spanport_list() {
	global $ifacelist;

	$splist = array('none' => 'None');

	foreach ($ifacelist as $ifn => $ifdescr)
		$splist[$ifn] = $ifdescr;

	return($splist);
}

function build_member_list() {
	global $pconfig, $ifacelist;

	$memberlist = array('list' => array(),
						'selected' => array());

	$members_array = explode(',', $pconfig['members']);
	foreach ($ifacelist as $ifn => $ifinfo) {
		$memberlist['list'][$ifn] = $ifinfo;

		if (in_array($ifn, $members_array)) {
			array_push($memberlist['selected'], $ifn);
		}
	}
	unset($members_array);
	return($memberlist);
}

function build_port_list($selecton) {
	global $pconfig, $ifacelist;

	$portlist = array('list' => array(), 'selected' => array());

	foreach ($ifacelist as $ifn => $ifdescr) {
		$portlist['list'][$ifn] = $ifdescr;

		if (stristr($selecton, $ifn)) {
			array_push($portlist['selected'], $ifn);
		}
	}

	return($portlist);
}

$pgtitle = array(gettext("Interfaces"),gettext("Bridge"),gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors)
	print_input_errors($input_errors);

$form = new Form();

$section = new Form_Section('Bridge Configuration');

$memberslist = build_member_list();

$section->addInput(new Form_Select(
	'members',
	'Member Interfaces',
	$memberslist['selected'],
	$memberslist['list'],
	true // Allow multiples
))->setHelp('Interfaces participating in the bridge');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
));

$section->addInput(new Form_Checkbox(
	'showadvanced',
	'Advanced',
	'Show advanced options',
	$pconfig['showadvanced']
))->toggles('.toggle-advanced');

$form->add($section);

$section = new Form_Section('Advanced Configuration');

// Set initial toggle state manually for now
if($pconfig['showadvanced'])
	$section->addClass('toggle-advanced in');
else
	$section->addClass('toggle-advanced collapse');

$section->addInput(new Form_Input(
	'maxaddr',
	'Cache Size',
	'text',
	$pconfig['maxaddr']
))->setHelp('Set the size of the bridge address cache. The default is 100 entries');

$section->addInput(new Form_Input(
	'timeout',
	'Cache expire time',
	'text',
	$pconfig['timeout']
))->setHelp('Set the timeout of address cache entries to this number of seconds. If seconds is zero, then address cache entries will not be expired. The default is 240 seconds');

$section->addInput(new Form_Select(
	'span',
	'Span Port',
	$pconfig['span'],
	build_spanport_list()
))->setHelp('Add the interface named by interface as a span port on the bridge. Span ports transmit a copy of every frame received by the bridge.' .
			'This is most useful for snooping a bridged network passively on another host connected to one of the span ports of the bridge. <br />' .
			'%sThe span interface cannot be part of the bridge member interfaces.%s', ['<strong>', '</strong>']);

$edgelist = build_port_list($pconfig['edge']);

$section->addInput(new Form_Select(
	'edge[]',
	'Edge Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Set interface as an edge port. An edge port connects directly to end stations and cannot create bridging loops in the network; this allows it to transition straight to forwarding.');

$edgelist = build_port_list($pconfig['autoedge']);

$section->addInput(new Form_Select(
	'autoedge[]',
	'Auto Edge Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Allow interface to automatically detect edge status. This is the default for all interfaces added to a bridge.' .
			'%sThis will disable the autoedge status of interfaces. %s', ['<strong>', '</strong>']);

$edgelist = build_port_list($pconfig['ptp']);

$section->addInput(new Form_Select(
	'ptp',
	'PTP Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Set the interface as a point-to-point link. This is required for straight transitions to forwarding and should be enabled on a direct link to another RSTP-capable switch.');

$edgelist = build_port_list($pconfig['autoptp']);

$section->addInput(new Form_Select(
	'autoptp[]',
	'Auto PTP Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Automatically detect the point-to-point status on interface by checking the full duplex link status. This is the default for interfaces added to the bridge.' .
			'%sThe interfaces selected here will be removed from default autoedge status. %s', ['<strong>', '</strong>']);

$edgelist = build_port_list($pconfig['static']);

$section->addInput(new Form_Select(
	'static[]',
	'Sticky Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Mark an interface as a "sticky" interface. Dynamically learned address entries are treated as static once entered into the cache. ' .
			'Sticky entries are never aged out of the cache or replaced, even if the address is seen on a different interface.');

$edgelist = build_port_list($pconfig['private']);

$section->addInput(new Form_Select(
	'private[]',
	'Private Ports',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Mark an interface as a "private" interface. A private interface does not forward any traffic to any other port that is also a private interface. ');

//	STP section
// ToDo: - Should disable spanning tree section when not checked
$section->addInput(new Form_Checkbox(
	'enablestp',
	'Enable RSTP/STP',
	null,
	$pconfig['enablestp']
));

// Show the spanning tree section
$form->add($section);
$section = new Form_Section('RSTP/STP');
if($pconfig['showadvanced'])
	$section->addClass('toggle-advanced in');
else
	$section->addClass('toggle-advanced collapse');

$section->addInput(new Form_Select(
	'proto',
	'Protocol',
	$pconfig['proto'],
	array('rstp' => 'RSTP',
		  'stp' => 'STP')
))->setHelp('Protocol used for spanning tree.');

$edgelist = build_port_list($pconfig['stp']);

$section->addInput(new Form_Select(
	'stp[]',
	'STP Interfaces',
	$edgelist['selected'],
	$edgelist['list'],
	true
))->setHelp('Enable Spanning Tree Protocol on interface. The if_bridge(4) driver has support for the IEEE 802.1D Spanning Tree Protocol (STP).' .
			'STP is used to detect and remove loops in a network topology.');

$section->addInput(new Form_Input(
	'maxage',
	'Valid time',
	'number',
	$pconfig['maxage'],
	['placeholder' => 20, 'min' => 6, 'max' => 40]
))->setHelp('Set the time that a Spanning Tree Protocol configuration is valid. The default is 20 seconds. The minimum is 6 seconds and the maximum is 40 seconds.');

$section->addInput(new Form_Input(
	'fwdelay',
	'Forward time',
	'number',
	$pconfig['fwdelay'],
	['placeholder' => 15, 'min' => 4, 'max' => 30]
))->setHelp('Set the time that must pass before an interface begins forwarding packets when Spanning Tree is enabled. The default is 15 seconds. The minimum is 4 seconds and the maximum is 30 seconds. ');

$section->addInput(new Form_Input(
	'hellotime',
	'Hello time',
	'number',
	$pconfig['hellotime'],
	['placeholder' => 2, 'min' => 1, 'max' => 2, 'step' => '0.1']
))->setHelp('Set the time in seconds between broadcasting of Spanning Tree Protocol configuration messages. The hello time may only be changed when operating in legacy STP mode.' .
			'The default is 2 seconds. The minimum is 1 second and the maximum is 2 seconds.');

$section->addInput(new Form_Input(
	'priority',
	'Priority',
	'text',
	$pconfig['priority'],
	['placeholder' => 32768, 'min' => 0, 'max' => 61440]
))->setHelp('Set the bridge priority for Spanning Tree. The default is 32768. The minimum is 0 and the maximum is 61440. ');

$section->addInput(new Form_Input(
	'holdcnt',
	'Hold Count',
	'number',
	$pconfig['holdcnt'],
	['placeholder' => 6, 'min' => 1, 'max' => 10]
))->setHelp('Set the transmit hold count for Spanning Tree. This is the number of packets transmitted before being rate limited. The default is 6. The minimum is 1 and the maximum is 10.');

foreach ($ifacelist as $ifn => $ifdescr) {
	$section->addInput(new Form_Input(
		$ifn,
		$ifdescr . ' Priority',
		'number',
		$pconfig[$ifn],
		['placeholder' => 128, 'min' => 0, 'max' => 240, 'step' => 16]
	))->setHelp('Set the Spanning Tree priority of interface to value. The default is 128. The minimum is 0 and the maximum is 240. Increments of 16.');
}

$i = 0;
foreach ($ifacelist as $ifn => $ifdescr) {
	$section->addInput(new Form_Input(
		$ifn . $i,
		$ifdescr . ' Path cost',
		'number',
		$ifpathcost[$ifn],
		[ 'placeholder' => 0, 'min' => 1, 'max' => 200000000]
	))->setHelp('Set the Spanning Tree path cost of interface to value. The default is calculated from the link speed. '.
		'To change a previously selected path cost back to automatic, set the cost to 0. The minimum is 1 and the maximum is 200000000.');
	$i++;
}

$section->addInput(new Form_Input(
	'bridgeif',
	null,
	'hidden',
	$pconfig['bridgeif']
));

if (isset($id) && $a_bridges[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
