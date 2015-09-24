<?php
/*
	vpn_ipsec_phase2.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

##|+PRIV
##|*IDENT=page-vpn-ipsec-editphase2
##|*NAME=VPN: IPsec: Edit Phase 2 page
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 2' page.
##|*MATCH=vpn_ipsec_phase2.php*
##|-PRIV

require("functions.inc");
require("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

if (!is_array($config['ipsec']['client'])) {
	$config['ipsec']['client'] = array();
}

$a_client = &$config['ipsec']['client'];

if (!is_array($config['ipsec']['phase1'])) {
	$config['ipsec']['phase1'] = array();
}

if (!is_array($config['ipsec']['phase2'])) {
	$config['ipsec']['phase2'] = array();
}

$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

if (!empty($_GET['p2index'])) {
	$uindex = $_GET['p2index'];
}
if (!empty($_POST['uniqid'])) {
	$uindex = $_POST['uniqid'];
}

if (!empty($_GET['dup'])) {
	$uindex = $_GET['dup'];
}

$ph2found = false;
if (isset($uindex)) {
	foreach ($a_phase2 as $p2index => $ph2) {
		if ($ph2['uniqid'] == $uindex) {
			$ph2found = true;
			break;
		}
	}
}

if ($ph2found === true) {
	$pconfig['ikeid'] = $ph2['ikeid'];
	$pconfig['disabled'] = isset($ph2['disabled']);
	$pconfig['mode'] = $ph2['mode'];
	$pconfig['descr'] = $ph2['descr'];
	$pconfig['uniqid'] = $ph2['uniqid'];

	if (!empty($ph2['natlocalid'])) {
		idinfo_to_pconfig("natlocal", $ph2['natlocalid'], $pconfig);
	}
	idinfo_to_pconfig("local", $ph2['localid'], $pconfig);
	idinfo_to_pconfig("remote", $ph2['remoteid'], $pconfig);

	$pconfig['proto'] = $ph2['protocol'];
	ealgos_to_pconfig($ph2['encryption-algorithm-option'], $pconfig);
	$pconfig['halgos'] = $ph2['hash-algorithm-option'];
	$pconfig['pfsgroup'] = $ph2['pfsgroup'];
	$pconfig['lifetime'] = $ph2['lifetime'];
	$pconfig['pinghost'] = $ph2['pinghost'];
	$pconfig['reqid'] = $ph2['reqid'];

	if (isset($ph2['mobile'])) {
		$pconfig['mobile'] = true;
	}
} else {
	$pconfig['ikeid'] = $_GET['ikeid'];

	/* defaults */
	$pconfig['localid_type'] = "lan";
	$pconfig['remoteid_type'] = "network";
	$pconfig['proto'] = "esp";
	$pconfig['ealgos'] = explode(",", "aes");
	$pconfig['halgos'] = explode(",", "hmac_sha1");
	$pconfig['pfsgroup'] = "0";
	$pconfig['lifetime'] = "3600";
	$pconfig['uniqid'] = uniqid();

	/* mobile client */
	if ($_GET['mobile']) {
		$pconfig['mobile']=true;
	}
}

unset($ph2);
if (!empty($_GET['dup'])) {
	unset($uindex);
	unset($p2index);
	$pconfig['uniqid'] = uniqid();
	$pconfig['reqid'] = ipsec_new_reqid();
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!isset($_POST['ikeid'])) {
		$input_errors[] = gettext("A valid ikeid must be specified.");
	}

	/* input validation */
	$reqdfields = explode(" ", "localid_type uniqid");
	$reqdfieldsn = array(gettext("Local network type"), gettext("Unique Identifier"));
	if (!isset($pconfig['mobile'])) {
		$reqdfields[] = "remoteid_type";
		$reqdfieldsn[] = gettext("Remote network type");
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($pconfig['mode'] == "tunnel") || ($pconfig['mode'] == "tunnel6")) {
		switch ($pconfig['localid_type']) {
			case "network":
				if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numeric($pconfig['localid_netbits'])) {
					$input_errors[] = gettext("A valid local network bit count must be specified.");
				}
			case "address":
				if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address'])) {
					$input_errors[] = gettext("A valid local network IP address must be specified.");
				} elseif (is_ipaddrv4($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel")) {
					$input_errors[] = gettext("A valid local network IPv4 address must be specified or you need to change Mode to IPv6");
				} elseif (is_ipaddrv6($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel6")) {
					$input_errors[] = gettext("A valid local network IPv6 address must be specified or you need to change Mode to IPv4");
				}
				break;
		}
		/* Check if the localid_type is an interface, to confirm if it has a valid subnet. */
		if (is_array($config['interfaces'][$pconfig['localid_type']])) {
			// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
			$address = get_interface_ip($pconfig['localid_type']);
			$netbits = get_interface_subnet($pconfig['localid_type']);

			if (empty($address) || empty($netbits)) {
				$input_errors[] = gettext("Invalid Local Network.") . " " . convert_friendly_interface_to_friendly_descr($pconfig['localid_type']) . " " . gettext("has no subnet.");
			}
		}

		if (!empty($pconfig['natlocalid_address'])) {
			switch ($pconfig['natlocalid_type']) {
				case "network":
					if (($pconfig['natlocalid_netbits'] != 0 && !$pconfig['natlocalid_netbits']) || !is_numeric($pconfig['natlocalid_netbits'])) {
						$input_errors[] = gettext("A valid NAT local network bit count must be specified.");
					}
					if ($pconfig['localid_type'] == "address") {
						$input_errors[] = gettext("You cannot configure a network type address for NAT while only an address type is selected for local source.");
					}
				case "address":
					if (!empty($pconfig['natlocalid_address']) && !is_ipaddr($pconfig['natlocalid_address'])) {
						$input_errors[] = gettext("A valid NAT local network IP address must be specified.");
					} elseif (is_ipaddrv4($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel")) {
						$input_errors[] = gettext("A valid NAT local network IPv4 address must be specified or you need to change Mode to IPv6");
					} elseif (is_ipaddrv6($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel6")) {
						$input_errors[] = gettext("A valid NAT local network IPv6 address must be specified or you need to change Mode to IPv4");
					}
					break;
			}

			if (is_array($config['interfaces'][$pconfig['natlocalid_type']])) {
				// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
				$address = get_interface_ip($pconfig['natlocalid_type']);
				$netbits = get_interface_subnet($pconfig['natlocalid_type']);

				if (empty($address) || empty($netbits)) {
					$input_errors[] = gettext("Invalid Local Network.") . " " . convert_friendly_interface_to_friendly_descr($pconfig['natlocalid_type']) . " " . gettext("has no subnet.");
				}
			}
		}

		switch ($pconfig['remoteid_type']) {
			case "network":
				if (($pconfig['remoteid_netbits'] != 0 && !$pconfig['remoteid_netbits']) || !is_numeric($pconfig['remoteid_netbits'])) {
					$input_errors[] = gettext("A valid remote network bit count must be specified.");
				}
			case "address":
				if (!$pconfig['remoteid_address'] || !is_ipaddr($pconfig['remoteid_address'])) {
					$input_errors[] = gettext("A valid remote network IP address must be specified.");
				} elseif (is_ipaddrv4($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel")) {
					$input_errors[] = gettext("A valid remote network IPv4 address must be specified or you need to change Mode to IPv6");
				} elseif (is_ipaddrv6($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel6")) {
					$input_errors[] = gettext("A valid remote network IPv6 address must be specified or you need to change Mode to IPv4");
				}
				break;
		}
	}
	/* Validate enabled phase2's are not duplicates */
	if (isset($pconfig['mobile'])) {
		/* User is adding phase 2 for mobile phase1 */
		foreach ($a_phase2 as $key => $name) {
			if (isset($name['mobile']) && $name['uniqid'] != $pconfig['uniqid']) {
				/* check duplicate localids only for mobile clents */
				$localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
				$entered = array();
				$entered['type'] = $pconfig['localid_type'];
				
				if (isset($pconfig['localid_address'])) 
				    $entered['address'] = $pconfig['localid_address'];
				    
				if (isset($pconfig['localid_netbits'])) 
				    $entered['netbits'] = $pconfig['localid_netbits'];
				    
				$entered_localid_data = ipsec_idinfo_to_cidr($entered, false, $pconfig['mode']);
				if ($localid_data == $entered_localid_data) {
					/* adding new p2 entry */
					$input_errors[] = gettext("Phase2 with this Local Network is already defined for mobile clients.");
					break;
				}
			}
		}
	} else {
		/* User is adding phase 2 for site-to-site phase1 */
		$input_error = 0;
		foreach ($a_phase2 as $key => $name) {
			if (!isset($name['mobile']) && $pconfig['ikeid'] == $name['ikeid'] && $pconfig['uniqid'] != $name['uniqid']) {
				/* check duplicate subnets only for given phase1 */
				$localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
				$remoteid_data = ipsec_idinfo_to_cidr($name['remoteid'], false, $name['mode']);
				$entered_local = array();
				$entered_local['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) {
					$entered_local['address'] = $pconfig['localid_address'];
				}
				if (isset($pconfig['localid_netbits'])) {
					$entered_local['netbits'] = $pconfig['localid_netbits'];
				}
				$entered_localid_data = ipsec_idinfo_to_cidr($entered_local, false, $pconfig['mode']);
				$entered_remote = array();
				$entered_remote['type'] = $pconfig['remoteid_type'];
				if (isset($pconfig['remoteid_address'])) {
					$entered_remote['address'] = $pconfig['remoteid_address'];
				}
				if (isset($pconfig['remoteid_netbits'])) {
					$entered_remote['netbits'] = $pconfig['remoteid_netbits'];
				}
				$entered_remoteid_data = ipsec_idinfo_to_cidr($entered_remote, false, $pconfig['mode']);
				if ($localid_data == $entered_localid_data && $remoteid_data == $entered_remoteid_data) {
					/* adding new p2 entry */
					$input_errors[] = gettext("Phase2 with this Local/Remote networks combination is already defined for this Phase1.");
					break;
				}
			}
		}
		foreach ($a_phase1 as $phase1) {
			if ($phase1['ikeid'] == $pconfig['ikeid']) {
				/* This is the P1 for this entry, validate its remote-gateway and local interface isn't within tunnel */
				$entered_local = array();
				$entered_local['type'] = $pconfig['localid_type'];
				if (isset($pconfig['localid_address'])) {
					$entered_local['address'] = $pconfig['localid_address'];
				}
				if (isset($pconfig['localid_netbits'])) {
					$entered_local['netbits'] = $pconfig['localid_netbits'];
				}
				$entered_localid_data = ipsec_idinfo_to_cidr($entered_local, false, $pconfig['mode']);
				list($entered_local_network, $entered_local_mask) = explode('/', $entered_localid_data);
				$entered_remote = array();
				$entered_remote['type'] = $pconfig['remoteid_type'];
				if (isset($pconfig['remoteid_address'])) {
					$entered_remote['address'] = $pconfig['remoteid_address'];
				}
				if (isset($pconfig['remoteid_netbits'])) {
					$entered_remote['netbits'] = $pconfig['remoteid_netbits'];
				}
				$entered_remoteid_data = ipsec_idinfo_to_cidr($entered_remote, false, $pconfig['mode']);
				list($entered_remote_network, $entered_remote_mask) = explode('/', $entered_remoteid_data);
				if ($phase1['protocol'] == "inet6") {
					$if = get_failover_interface($phase1['interface'], "inet6");
					$interfaceip = get_interface_ipv6($if);
				} else {
					$if = get_failover_interface($phase1['interface']);
					$interfaceip = get_interface_ip($if);
				}
				/* skip validation for hostnames, they're subject to change anyway */
				if (is_ipaddr($phase1['remote-gateway'])) {
					if ($pconfig['mode'] == "tunnel") {
						if (check_subnets_overlap($interfaceip, 32, $entered_local_network, $entered_local_mask) && check_subnets_overlap($phase1['remote-gateway'], 32, $entered_remote_network, $entered_remote_mask)) {
							$input_errors[] = gettext("The local and remote networks of a phase 2 entry cannot overlap the outside of the tunnel (interface and remote gateway) configured in its phase 1.");
							break;
						}
					} else if ($pconfig['mode'] == "tunnel6") {
						if (check_subnetsv6_overlap($interfaceip, 128, $entered_local_network, $entered_local_mask) && check_subnets_overlap($phase1['remote-gateway'], 128, $entered_remote_network, $entered_remote_mask)) {
							$input_errors[] = gettext("The local and remote networks of a phase 2 entry cannot overlap the outside of the tunnel (interface and remote gateway) configured in its phase 1.");
							break;
						}
					}
				}
			}
		}
	}

	/* For ESP protocol, handle encryption algorithms */
	if ($pconfig['proto'] == "esp") {
		$ealgos = pconfig_to_ealgos($pconfig);

		if (!count($ealgos)) {
			$input_errors[] = gettext("At least one encryption algorithm must be selected.");
		} else {
			foreach ($ealgos as $ealgo) {
				if (isset($config['system']['crypto_hardware'])) {
					if ($config['system']['crypto_hardware'] == "glxsb") {
						if ($ealgo['name'] == "aes" && $ealgo['keylen'] != "128") {
							$input_errors[] = gettext("Only 128 bit AES can be used where the glxsb crypto accelerator is enabled.");
						}
					}
				}
				if (empty($pconfig['halgos'])) {
					if (!strpos($ealgo['name'], "gcm")) {
						$input_errors[] = gettext("At least one hashing algorithm needs to be selected.");
						break;
					}
				}
			}
		}
	}
	if (($_POST['lifetime'] && !is_numeric($_POST['lifetime']))) {
		$input_errors[] = gettext("The P2 lifetime must be an integer.");
	}

	if (!$input_errors) {

		$ph2ent = array();
		$ph2ent['ikeid'] = $pconfig['ikeid'];
		$ph2ent['uniqid'] = $pconfig['uniqid'];
		$ph2ent['mode'] = $pconfig['mode'];
		$ph2ent['disabled'] = $pconfig['disabled'] ? true : false;
		if (!isset($pconfig['reqid'])) {
			$ph2ent['reqid'] = ipsec_new_reqid();
		} else {
			$ph2ent['reqid'] = $pconfig['reqid'];
		}

		if (($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6")) {
			if (!empty($pconfig['natlocalid_address'])) {
				$ph2ent['natlocalid'] = pconfig_to_idinfo("natlocal", $pconfig);
			}
			$ph2ent['localid'] = pconfig_to_idinfo("local", $pconfig);
			$ph2ent['remoteid'] = pconfig_to_idinfo("remote", $pconfig);
		}

		$ph2ent['protocol'] = $pconfig['proto'];
		$ph2ent['encryption-algorithm-option'] = $ealgos;
		if (!empty($pconfig['halgos'])) {
			$ph2ent['hash-algorithm-option'] = $pconfig['halgos'];
		} else {
			unset($ph2ent['hash-algorithm-option']);
		}
		$ph2ent['pfsgroup'] = $pconfig['pfsgroup'];
		$ph2ent['lifetime'] = $pconfig['lifetime'];
		$ph2ent['pinghost'] = $pconfig['pinghost'];
		$ph2ent['descr'] = $pconfig['descr'];

		if (isset($pconfig['mobile'])) {
			$ph2ent['mobile'] = true;
		}

		if ($ph2found === true && $a_phase2[$p2index]) {
			$a_phase2[$p2index] = $ph2ent;
		} else {
			$a_phase2[] = $ph2ent;
		}

		write_config();
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec.php");
		exit;
	}
}

if ($pconfig['mobile']) {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Edit Phase 2"), gettext("Mobile Client"));
} else {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Edit Phase 2"));
}
$shortcut_section = "ipsec";

include("head.inc");

function pconfig_to_ealgos(& $pconfig) {
	global $p2_ealgos;

	$ealgos = array();
	if (is_array($pconfig['ealgos'])) {
		foreach ($p2_ealgos as $algo_name => $algo_data) {
			if (in_array($algo_name, $pconfig['ealgos'])) {
				$ealg = array();
				$ealg['name'] = $algo_name;
				if (is_array($algo_data['keysel'])) {
					$ealg['keylen'] = $_POST["keylen_".$algo_name];
				}
				$ealgos[] = $ealg;
			}
		}
	}

	return $ealgos;
}

function ealgos_to_pconfig(& $ealgos,& $pconfig) {

	$pconfig['ealgos'] = array();
	foreach ($ealgos as $algo_data) {
		$pconfig['ealgos'][] = $algo_data['name'];
		if (isset($algo_data['keylen'])) {
			$pconfig["keylen_".$algo_data['name']] = $algo_data['keylen'];
		}
	}

	return $ealgos;
}

function pconfig_to_idinfo($prefix,& $pconfig) {

	$type = $pconfig[$prefix."id_type"];
	$address = $pconfig[$prefix."id_address"];
	$netbits = $pconfig[$prefix."id_netbits"];

	switch ($type) {
		case "address":
			return array('type' => $type, 'address' => $address);
		case "network":
			return array('type' => $type, 'address' => $address, 'netbits' => $netbits);
		default:
			return array('type' => $type);
	}
}

function idinfo_to_pconfig($prefix,& $idinfo,& $pconfig) {

	switch ($idinfo['type']) {
		case "address":
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			$pconfig[$prefix."id_address"] = $idinfo['address'];
			break;
		case "network":
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			$pconfig[$prefix."id_address"] = $idinfo['address'];
			$pconfig[$prefix."id_netbits"] = $idinfo['netbits'];
			break;
		default:
			$pconfig[$prefix."id_type"] = $idinfo['type'];
			break;
	}
}

if ($input_errors)
	print_input_errors($input_errors);

$tab_array = array();
$tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
$tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

require_once('classes/Form.class.php');

$form = new Form();

$section = new Form_Section('General Information');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this phase 2 entry without removing it from the list. ',
	$pconfig['disabled']
));

$section->addInput(new Form_Select(
	'mode',
	'Mode',
	$pconfig['mode'],
	$p2_modes
));

$group = new Form_Group('Local Network');
$group->addClass('opt_localid');

$subnetarray = get_configured_interface_with_descr();
foreach($subnetarray as $ifname => $ifdescr)
	$subnetarray[$ifname] = $ifdescr . ' subnet';

$group->add(new Form_Select(
	'localid_type',
	null,
	$pconfig['localid_type'],
	array_merge(array('address' => 'Address', 'network' => 'Network'), $subnetarray)
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'localid_address',
	null,
	$pconfig['localid_address']
))->setHelp('Address')->addMask(localid_netbits, $pconfig['localid_netbits'], 128, 0);

$section->add($group);

$group = new Form_Group('NAT/BINAT translation');
$group->addClass('opt_natid');

$subnetarray = get_configured_interface_with_descr();
foreach($subnetarray as $ifname => $ifdescr)
	$subnetarray[$ifname] = $ifdescr . ' subnet';

// Tack none, address & network on the beginning
$subnetarray = array('none' => gettext('None'), 'address' => 'Address', 'network' => 'Network') + $subnetarray;

$group->add(new Form_Select(
	'natlocalid_type',
	null,
	$pconfig['natlocalid_type'],
	$subnetarray
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'natlocalid_address',
	null,
	$pconfig['localid_address']
))->setHelp('Address')->addMask(natlocalid_netbits, $pconfig['natlocalid_netbits'], 128, 0);

$group->setHelp('If NAT/BINAT is required on this network specify the address to be translated');
$section->add($group);

$group = new Form_Group('Remote Network');
$group->addClass('opt_remoteid');

$group->add(new Form_Select(
	'remoteid_type',
	null,
	$pconfig['remoteid_type'],
	array('address' => 'Address', 'network' => 'Network')
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'remoteid_address',
	null,
	$pconfig['remoteid_address']
))->setHelp('Address')->addMask(remoteid_netbits, $pconfig['remoteid_netbits'], 128, 0);

$section->add($group);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('You may enter a description here for your reference (not parsed).');

$form->add($section);

$section = new Form_Section('Phase 2 proposal (SA/Key Exchange)');

$section->addInput(new Form_Select(
	'proto',
	'Protocol',
	$pconfig['proto'],
	$p2_protos
))->setHelp('ESP is encryption, AH is authentication only.');

$i = 0;
$rows = count($p2_ealgos) - 1;

foreach ($p2_ealgos as $algo => $algodata) {
	$group = new Form_Group($i == 0 ? 'Encryption Algorithms':'');
	$group->addClass('encalg');

	$group->add(new Form_Checkbox(
		'ealgos[]',
		null,
		$algodata['name'],
		(is_array($pconfig['ealgos']) && in_array($algo,$pconfig['ealgos'])),
		$algo
	))->addClass('multi');



	if(is_array($algodata['keysel'])) {
		$list = array();
		$key_hi = $algodata['keysel']['hi'];
		$key_lo = $algodata['keysel']['lo'];
		$key_step = $algodata['keysel']['step'];
		for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
			$list[$keylen] = $keylen . ' bits';
		}

		$group->add(new Form_Select(
			'keylen_' . $algo,
			null,
			$keylen == $pconfig["keylen_".$algo],
			array_merge(array('auto' => 'Auto'), $list)
		));
	}


	if($i == $rows)
		$group->setHelp('Use 3DES for best compatibility or if you have a hardware crypto accelerator card. Blowfish is usually the fastest in software encryption.');

	$i++;
	$section->add($group);
}

$group = new Form_Group('Hash Algorithms');

foreach ($p2_halgos as $algo => $algoname) {
	$group->add(new Form_Checkbox(
		'halgos[]',
		null,
		$algoname,
		(in_array($algo, $pconfig['halgos'])),
		$algo
	))->addClass('multi');
}

$section->add($group);

$sm = (!isset($pconfig['mobile']) || !isset($a_client['pfs_group']));

$section->addInput(new Form_Select(
	'pfsgroup',
	'PFS key group',
	$pconfig['psgroup'],
	$sm ? $p2_pfskeygroups:array()
))->setHelp($sm ? '':'Set globally in mobile client options');

$section->addInput(new Form_Input(
	'lifetime',
	'Lifetime',
	'number',
	$pconfig['lifetime']
))->setHelp('Seconds');

$form->add($section);

$section = new Form_Section('Advanced Configuration');

$section->addInput(new Form_IpAddress(
	'pinghost',
	'Automatically ping host',
	$pconfig['pinghost']
))->setHelp('IP Address');

// Hidden inputs
if ($pconfig['mobile']) {
	$section->addInput(new Form_Input(
		'mobile',
		null,
		'hidden',
		'true'
	));
}

$section->addInput(new Form_Input(
	'ikeid',
	null,
	'hidden',
	$pconfig['ikeid']
));

if (!empty($pconfig['reqid'])) {
	$section->addInput(new Form_Input(
		'reqid',
		null,
		'hidden',
		$pconfig['reqid']
	));
}

$section->addInput(new Form_Input(
	'uniqid',
	null,
	'hidden',
	$pconfig['uniqid']
));

$form->add($section);

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function(){

    // ---------- On changing "Mode" ----------------------------------------------------------------------------------
	function change_mode() {

		value = $('#mode').val();

		if ((value == 'tunnel') || (value == 'tunnel6')) {
			hideClass('opt_localid', false);
			hideClass('opt_natid', false);

<?php	if (!isset($pconfig['mobile'])): ?>
			hideClass('opt_remoteid', false);
			hideClass('opt_natid', false);
<?php	endif; ?>
		} else {
			hideClass('opt_localid', true);
			hideClass('opt_natid', true);
<?php	if (!isset($pconfig['mobile'])): ?>
			hideClass('opt_remoteid', true);
<?php	endif; ?>
		}
	}

    // ---------- On changing "NAT/BINAT" -----------------------------------------------------------------------------
	function typesel_change_natlocal(bits) {
		var value = $('#mode').val();

		if (typeof(bits) === "undefined") {
			if (value === "tunnel") {
				bits = 24;
			}
			else if (value === "tunnel6") {
				bits = 64;
			}
		}

		var address_is_blank = !/\S/.test($('#natlocalid_address').val());

		switch ($("#natlocalid_type option:selected").index()) {
			case 0: /* single */
				disableInput('natlocalid_address', false);

				if (address_is_blank) {
					$('#natlocalid_netbits').val(0);
				}

				disableInput('natlocalid_netbits', true);
				break;
			case 1: /* network */
				disableInput('natlocalid_address', false);
				
				if (address_is_blank) {
					$('#natlocalid_netbits').val(bits);
				}
				
				disableInput('natlocalid_netbits', false);
				break;
			case 3: /* none */
				disableInput('natlocalid_address', true);
				disableInput('natlocalid_netbits', true);
				break;
			default:
				$('#natlocalid_address').val("");
				disableInput('natlocalid_address', true);
				
				if (address_is_blank) {
					$('#natlocalid_netbits').val(0);
				}
				
				disableInput('natlocalid_netbits', true);
				break;
		}
	}

    // ---------- On changing "Local Network" -------------------------------------------------------------------------
	function typesel_change_local(bits) {
		var value = $('#mode').val();

		if (typeof(bits) === "undefined") {
			if (value === "tunnel") {
				bits = 24;
			}
			else if (value === "tunnel6") {
				bits = 64;
			}
		}

		var address_is_blank = !/\S/.test($('#localid_address').val());

		switch ($("#localid_type option:selected").index()) {
			case 0: /* single */
				disableInput('localid_address', false);

				if (address_is_blank) {
					$('#localid_netbits').val(0);
				}

				disableInput('localid_netbits', true);
				break;
			case 1: /* network */
				disableInput('localid_address', false);
				
				if (address_is_blank) {
					$('#localid_netbits').val(bits);
				}
				
				disableInput('localid_netbits', false);
				break;
			case 3: /* none */
				disableInput('localid_address', true);
				disableInput('localid_netbits', true);
				break;
			default:
				$('#localid_address').val("");
				disableInput('localid_address', true);
				
				if (address_is_blank) {
					$('#localid_netbits').val(0);
				}
				
				disableInput('localid_netbits', true);
				break;
		}
	}

<?php

    // ---------- On changing "Remote Network" ------------------------------------------------------------------------
	if (!isset($pconfig['mobile'])): ?>

		function typesel_change_remote(bits) {

			var value = $('#mode').val();

			if (typeof(bits) === "undefined") {
				if (value === "tunnel") {
					bits = 24;
				}
				else if (value === "tunnel6") {
					bits = 64;
				}
			}

			var address_is_blank = !/\S/.test($('#remoteid_address').val());

			switch ($("#remoteid_type option:selected").index()) {
				case 0: /* single */
					disableInput('remoteid_address', false);

					if (address_is_blank) {
						$('#remoteid_netbits').val(0);
					}

					disableInput('remoteid_netbits', true);
					break;
				case 1: /* network */
					disableInput('remoteid_address', false);
					
					if (address_is_blank) {
						$('#remoteid_netbits').val(bits);
					}
					
					disableInput('remoteid_netbits', false);
					break;
				case 3: /* none */
					disableInput('remoteid_address', true);
					disableInput('remoteid_netbits', true);
					break;
				default:
					$('#remoteid_address').val("");
					disableInput('remoteid_address', true);
					
					if (address_is_blank) {
						$('#remoteid_netbits').val(0);
					}
					
					disableInput('remoteid_netbits', true);
					break;
			}
		}

	<?php endif; ?>

	function change_protocol() {
			hideClass('encalg', ($('#proto').val() != 'esp'));
	}

	// ---------- Library of show/hide functions --------------------------------------------------

	// Hides the <div> in which the specified input element lives so that the input,
	// its label and help text are hidden
	function hideInput(id, hide) {
		if(hide)
			$('#' + id).parent().parent('div').addClass('hidden');
		else
			$('#' + id).parent().parent('div').removeClass('hidden');
	}

	// Hides the <div> in which the specified group input element lives so that the input,
	// its label and help text are hidden
	function hideGroupInput(id, hide) {
		if(hide)
			$('#' + id).parent('div').addClass('hidden');
		else
			$('#' + id).parent('div').removeClass('hidden');
	}

	// Hides the <div> in which the specified checkbox lives so that the checkbox,
	// its label and help text are hidden
	function hideCheckbox(id, hide) {
		if(hide)
			$('#' + id).parent().parent().parent('div').addClass('hidden');
		else
			$('#' + id).parent().parent().parent('div').removeClass('hidden');
	}

	// Disables the specified input element
	function disableInput(id, disable) {
		$('#' + id).prop("disabled", disable);
	}

	// Hides all elements of the specified class. This will usually be a section or group
	function hideClass(s_class, hide) {
		if(hide)
			$('.' + s_class).hide();
		else
			$('.' + s_class).show();
	}

	// ---------- Monitor elements for change and call the appropriate display functions ----------

	 // Protocol
	$('#proto').click(function () {
		change_protocol();
	});

	 // Localid
	$('#localid_type').click(function () {
		typesel_change_local(<?=htmlspecialchars($pconfig['localid_netbits'])?>);
	});

	 // Remoteid
	$('#remoteid_type').click(function () {
		typesel_change_remote(<?=htmlspecialchars($pconfig['remoteid_netbits'])?>);
	});

	 // NATLocalid
	$('#natlocalid_type').click(function () {
		typesel_change_natlocal(<?=htmlspecialchars($pconfig['natlocalid_netbits'])?>);
	});

	 // Mode
	$('#mode').click(function () {
		change_mode();
	});

	// ---------- On initial page load ------------------------------------------------------------
    hideInput('ikeid', true);
    hideInput('uniqid', true);

	change_mode();
	change_protocol();
	typesel_change_local(<?=htmlspecialchars($pconfig['localid_netbits'])?>);
	typesel_change_natlocal(<?=htmlspecialchars($pconfig['natlocalid_netbits'])?>);
<?php
	if (!isset($pconfig['mobile'])):
?>
		typesel_change_remote(<?=htmlspecialchars($pconfig['remoteid_netbits'])?>);
<?php
endif;
?>
});
//]]>
</script>
<?php
include("foot.inc");
