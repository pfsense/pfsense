<?php
/*
	interfaces_lagg_edit.php
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

##|+PRIV
##|*IDENT=page-interfaces-lagg-edit
##|*NAME=Interfaces: LAGG: Edit
##|*DESCR=Allow access to the 'Interfaces: LAGG: Edit' page.
##|*MATCH=interfaces_lagg_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['laggs']['lagg'])) {
	$config['laggs']['lagg'] = array();
}

$a_laggs = &$config['laggs']['lagg'];

$portlist = get_interface_list();
$laggprotos	  = array("none", "lacp", "failover", "fec", "loadbalance", "roundrobin");
$laggprotosuc = array("NONE", "LACP", "FAILOVER", "FEC", "LOADBALANCE", "ROUNDROBIN");

$protohelp =
'<ul>' .
	'<li>' .
		 '<strong>' . gettext($laggprotos[0]) . '</strong><br />' .
		 gettext('This protocol is intended to do nothing: it disables any ' .
				 'traffic without disabling the lagg interface itself') .
	'</li>' .
	'<li>' .
		 '<strong>' . gettext($laggprotos[1]) . '</strong><br />' .
		 gettext('Supports the IEEE 802.3ad Link Aggregation Control Protocol ' .
				  '(LACP) and the Marker Protocol.	LACP will negotiate a set ' .
				  'of aggregable links with the peer in to one or more Link ' .
				  'Aggregated Groups.  Each LAG is composed of ports of the ' .
				  'same speed, set to full-duplex operation.  The traffic will ' .
				  'be balanced across the ports in the LAG with the greatest ' .
				  'total speed, in most cases there will only be one LAG which ' .
				  'contains all ports.	In the event of changes in physical ' .
				  'connectivity, Link Aggregation will quickly converge to a ' .
				  'new configuration.') .
	'</li>' .
	'<li>' .
		'<strong>' . gettext($laggprotos[2]) . '</strong><br />' .
		gettext('Sends and receives traffic only through the master port.  If ' .
				'the master port becomes unavailable, the next active port is ' .
				'used.	The first interface added is the master port; any ' .
				'interfaces added after that are used as failover devices.') .
	'</li>' .
	'<li>' .
		'<strong>' . gettext($laggprotos[3]) . '</strong><br />' .
		gettext('Supports Cisco EtherChannel.  This is a static setup and ' .
				 'does not negotiate aggregation with the peer or exchange ' .
				 'frames to monitor the link.') .
	'</li>' .
	'<li>' .
		 '<strong>' . gettext($laggprotos[4]) . '</strong><br />' .
		 gettext('Balances outgoing traffic across the active ports based on ' .
				 'hashed protocol header information and accepts incoming ' .
				 'traffic from any active port.	 This is a static setup and ' .
				 'does not negotiate aggregation with the peer or exchange ' .
				 'frames to monitor the link.  The hash includes the Ethernet ' .
				 'source and destination address, and, if available, the VLAN ' .
				 'tag, and the IP source and destination address') .
	'</li>' .
	'<li>' .
		 '<strong>' . gettext($laggprotos[5]) . '</strong><br />' .
		 gettext('Distributes outgoing traffic using a round-robin scheduler ' .
				 'through all active ports and accepts incoming traffic from ' .
				 'any active port') .
	'</li>' .
'</ul>';

$realifchecklist = array();
/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		unset($portlist[$lagg['laggif']]);
		$laggiflist = explode(",", $lagg['members']);
		foreach ($laggiflist as $tmpif) {
			$realifchecklist[get_real_interface($tmpif)] = $tmpif;
		}
	}
}

$checklist = get_configured_interface_list(false, true);

foreach ($checklist as $tmpif) {
	$realifchecklist[get_real_interface($tmpif)] = $tmpif;
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}

if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($id) && $a_laggs[$id]) {
	$pconfig['laggif'] = $a_laggs[$id]['laggif'];
	$pconfig['members'] = $a_laggs[$id]['members'];
	$laggiflist = explode(",", $a_laggs[$id]['members']);
	foreach ($laggiflist as $tmpif) {
		unset($realifchecklist[get_real_interface($tmpif)]);
	}
	$pconfig['proto'] = $a_laggs[$id]['proto'];
	$pconfig['descr'] = $a_laggs[$id]['descr'];
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	$pconfig['members'] = implode(',', $_POST['members']);

	/* input validation */
	$reqdfields = explode(" ", "members proto");
	$reqdfieldsn = array(gettext("Member interfaces"), gettext("Lagg protocol"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (is_array($_POST['members'])) {
		foreach ($_POST['members'] as $member) {
			if (!does_interface_exist($member)) {
				$input_errors[] = gettext("Interface supplied as member is invalid");
			}
		}
	} else if (!does_interface_exist($_POST['members'])) {
		$input_errors[] = gettext("Interface supplied as member is invalid");
	}

	if (!in_array($_POST['proto'], $laggprotos)) {
		$input_errors[] = gettext("Protocol supplied is invalid");
	}

	if (!$input_errors) {
		$lagg = array();
		$lagg['members'] = implode(',', $_POST['members']);
		$lagg['descr'] = $_POST['descr'];
		$lagg['laggif'] = $_POST['laggif'];
		$lagg['proto'] = $_POST['proto'];
		if (isset($id) && $a_laggs[$id]) {
			$lagg['laggif'] = $a_laggs[$id]['laggif'];
		}

		$lagg['laggif'] = interface_lagg_configure($lagg);
		if ($lagg['laggif'] == "" || !stristr($lagg['laggif'], "lagg")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_laggs[$id]) {
				$a_laggs[$id] = $lagg;
			} else {
				$a_laggs[] = $lagg;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($lagg['laggif']);
			if ($confif != "") {
				interface_configure($confif);
			}

			header("Location: interfaces_lagg.php");
			exit;
		}
	}
}

function build_member_list() {
	global $pconfig, $portlist, $realifchecklist;

	$memberlist = array('list' => array(),
						'selected' => array());

	foreach ($portlist as $ifn => $ifinfo) {
		if (array_key_exists($ifn, $realifchecklist)) {
			continue;
		}

		$memberlist['list'][$ifn] = $ifn . '(' . $ifinfo['mac'] . ')';

		if (stristr($pconfig['members'], $ifn)) {
			array_push($memberlist['selected'], $ifn);
		}
	}

	return($memberlist);
}

$pgtitle = array(gettext("Interfaces"), gettext("LAGG"), gettext("Edit"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('LAGG Configuration');

$memberslist = build_member_list();

$section->addInput(new Form_Select(
	'members[]',
	'Parent Interfaces',
	$memberslist['selected'],
	$memberslist['list'],
	true // Allow multiples
))->setHelp('Choose the members that will be used for the link aggregation.');

$section->addInput(new Form_Select(
	'proto',
	'LAGG Protocol',
	$pconfig['proto'],
	array_combine($laggprotos, $laggprotosuc)
))->setHelp($protohelp);

$section->addInput(new Form_Input(
	'laggif',
	null,
	'hidden',
	$pconfig['laggif']
));

if (isset($id) && $a_laggs[$id]) {
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
