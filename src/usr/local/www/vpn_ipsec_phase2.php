<?php
/*
 * vpn_ipsec_phase2.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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
##|*IDENT=page-vpn-ipsec-editphase2
##|*NAME=VPN: IPsec: Edit Phase 2
##|*DESCR=Allow access to the 'VPN: IPsec: Edit Phase 2' page.
##|*MATCH=vpn_ipsec_phase2.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");

init_config_arr(array('ipsec', 'client'));
$a_client = &$config['ipsec']['client'];
init_config_arr(array('ipsec', 'phase1'));
init_config_arr(array('ipsec', 'phase2'));
$a_phase1 = &$config['ipsec']['phase1'];
$a_phase2 = &$config['ipsec']['phase2'];

if (!empty($_REQUEST['p2index'])) {
	$uindex = $_REQUEST['p2index'];
}

if (!empty($_REQUEST['uniqid'])) {
	$uindex = $_REQUEST['uniqid'];
}

if (!empty($_REQUEST['dup'])) {
	$uindex = $_REQUEST['dup'];
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
	$pconfig['rekey_time'] = $ph2['rekey_time'];
	$pconfig['rand_time'] = $ph2['rand_time'];
	$pconfig['pinghost'] = $ph2['pinghost'];
	$pconfig['keepalive'] = ($ph2['keepalive'] == 'enabled');
	$pconfig['reqid'] = $ph2['reqid'];

	if (isset($ph2['mobile'])) {
		$pconfig['mobile'] = true;
		$pconfig['remoteid_type'] = "mobile";
	}
} else {
	$pconfig['ikeid'] = $_REQUEST['ikeid'];

	/* defaults */
	$pconfig['localid_type'] = "lan";
	$pconfig['remoteid_type'] = "network";
	$pconfig['proto'] = "esp";
	$pconfig['ealgos'] = explode(",", "aes,aes128gcm");
	$pconfig['keylen_aes'] = 128;
	$pconfig['keylen_aes128gcm'] = 128;
	$pconfig['halgos'] = explode(",", "hmac_sha256");
	$pconfig['pfsgroup'] = "14";
	$pconfig['lifetime'] = "3600";
	$pconfig['uniqid'] = uniqid();

	/* mobile client */
	if ($_REQUEST['mobile']) {
		$pconfig['mobile']=true;
		$pconfig['remoteid_type'] = "mobile";
	}
}

unset($ph2);
if (!empty($_REQUEST['dup'])) {
	unset($uindex);
	unset($p2index);
	$pconfig['uniqid'] = uniqid();
	$pconfig['reqid'] = ipsec_new_reqid();
}

if ($_POST['save']) {

	unset($input_errors);

	/* Check if the user is switching away from VTI */
	$vti_switched = ($ph2found && ($pconfig['mode'] == "vti") && ($_POST['mode'] != "vti"));

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

	if (($pconfig['mode'] == "tunnel") || ($pconfig['mode'] == "tunnel6") || ($pconfig['mode'] == "vti")) {
		switch ($pconfig['localid_type']) {
			case "network":
				if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numericint($pconfig['localid_netbits'])) {
					$input_errors[] = gettext("A valid local network bit count must be specified.");
				}
			case "address":
				if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address'])) {
					$input_errors[] = gettext("A valid local network IP address must be specified.");
				} elseif ($pconfig['mode'] == "vti") {
					if (!is_ipaddr($pconfig['localid_address'])) {
						$input_errors[] = gettext("VTI requires a valid local address");
					}
				} elseif (is_ipaddrv4($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel")) {
					$input_errors[] = gettext("An IPv4 local address was specified but the mode is not set to tunnel");
				} elseif (is_ipaddrv6($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel6")) {
					$input_errors[] = gettext("An IPv6 local address was specified but the mode is not set to tunnel6");
				}
				break;
			default:
				if (($pconfig['mode'] == "vti") && !is_ipaddr($pconfig['localid_address'])) {
					$input_errors[] = gettext("VTI requires a valid local network or IP address for its endpoint address, it cannot use a network macro for a different interface (e.g. LAN).");
				}

		}
		/* Check if the localid_type is an interface, to confirm if it has a valid subnet. */
		if (is_array($config['interfaces'][$pconfig['localid_type']])) {
			// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
			if ($pconfig['mode'] == 'tunnel6') {
				$address = get_interface_ipv6($pconfig['localid_type']);
				$netbits = get_interface_subnetv6($pconfig['localid_type']);
			} else {
				$address = get_interface_ip($pconfig['localid_type']);
				$netbits = get_interface_subnet($pconfig['localid_type']);
			}

			if (empty($address) || empty($netbits)) {
				$input_errors[] = gettext("Invalid Local Network.") . " " . sprintf(gettext("%s has no subnet."), convert_friendly_interface_to_friendly_descr($pconfig['localid_type']));
			}
		}

		if (!empty($pconfig['natlocalid_address'])) {
			switch ($pconfig['natlocalid_type']) {
				case "network":
					if (($pconfig['natlocalid_netbits'] != 0 && !$pconfig['natlocalid_netbits']) || !is_numericint($pconfig['natlocalid_netbits'])) {
						$input_errors[] = gettext("A valid NAT local network bit count must be specified.");
					}
					if ($pconfig['localid_type'] == "address") {
						$input_errors[] = gettext("A network type address cannot be configured for NAT while only an address type is selected for local source.");
					}
					if (((($pconfig['mode'] == "tunnel") && ($pconfig['natlocalid_netbits'] != 32)) ||
					    (($pconfig['mode'] == "tunnel6") && ($pconfig['natlocalid_netbits'] != 128))) &&
					    ((is_numeric($pconfig['localid_netbits']) && 
					    ($pconfig['natlocalid_netbits'] != $pconfig['localid_netbits'])) ||
					    (is_numeric($netbits) && 
					    ($pconfig['natlocalid_netbits'] != $netbits)))) { 
						$input_errors[] = gettext("Local network subnet size and NAT local network subnet size cannot be different.");
					}
				case "address":
					if (!empty($pconfig['natlocalid_address']) && !is_ipaddr($pconfig['natlocalid_address'])) {
						$input_errors[] = gettext("A valid NAT local network IP address must be specified.");
					} elseif (is_ipaddrv4($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel")) {
						$input_errors[] = gettext("A valid NAT local network IPv4 address must be specified or Mode needs to be changed to IPv6");
					} elseif (is_ipaddrv6($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel6")) {
						$input_errors[] = gettext("A valid NAT local network IPv6 address must be specified or Mode needs to be changed to IPv4");
					}
					break;
			}

			if (is_array($config['interfaces'][$pconfig['natlocalid_type']])) {
				// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
				$address = get_interface_ip($pconfig['natlocalid_type']);
				$netbits = get_interface_subnet($pconfig['natlocalid_type']);

				if (empty($address) || empty($netbits)) {
					$input_errors[] = gettext("Invalid Local Network.") . " " . sprintf(gettext("%s has no subnet."), convert_friendly_interface_to_friendly_descr($pconfig['natlocalid_type']));
				}
			}
		}

		switch ($pconfig['remoteid_type']) {
			case "network":
				if (($pconfig['remoteid_netbits'] != 0 && !$pconfig['remoteid_netbits']) || !is_numericint($pconfig['remoteid_netbits'])) {
					$input_errors[] = gettext("A valid remote network bit count must be specified.");
				}
			case "address":
				if (!$pconfig['remoteid_address'] || !is_ipaddr($pconfig['remoteid_address'])) {
					$input_errors[] = gettext("A valid remote network IP address must be specified.");
				} elseif ($pconfig['mode'] == "vti") {
					if (!is_ipaddr($pconfig['remoteid_address'])) {
						$input_errors[] = gettext("VTI requires a valid remote address");
					}
				} elseif (is_ipaddrv4($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel")) {
					$input_errors[] = gettext("An IPv4 remote network was specified but the mode is not set to tunnel");
				} elseif (is_ipaddrv6($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel6")) {
					$input_errors[] = gettext("An IPv6 remote network was specified but the mode is not set to tunnel6");
				}
				break;
		}
	}
	/* Validate enabled phase2's are not duplicates */
	if (isset($pconfig['mobile'])) {
		/* User is adding phase 2 for mobile phase1 */
		if ($pconfig['mode'] == "vti") {
			$input_errors[] = gettext("VTI is not compatible with mobile IPsec.");
		}

		foreach ($a_phase2 as $key => $name) {
			if (isset($name['mobile']) && $name['uniqid'] != $pconfig['uniqid']) {
				/* check duplicate localids only for mobile clients */
				$localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
				$entered = array();
				$entered['type'] = $pconfig['localid_type'];

				if (isset($pconfig['localid_address'])) {
					$entered['address'] = $pconfig['localid_address'];
				}

				if (isset($pconfig['localid_netbits'])) {
					$entered['netbits'] = $pconfig['localid_netbits'];
				}

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
				/* This is the P1 for this entry */
				if ($vti_switched) {
					/* Determine what this P2 interface would be */
					if (is_interface_ipsec_vti_assigned($a_phase2[$p2index])) {
						$input_errors[] = gettext("Cannot switch away from VTI while the interface is assigned. Remove the interface assignment before switching away from VTI.");
					}
				}
				/* validate its remote-gateway and local interface isn't within tunnel */
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
					} elseif ($pconfig['mode'] == "tunnel6") {
						if (check_subnetsv6_overlap($interfaceip, 128, $entered_local_network, $entered_local_mask) && check_subnets_overlap($phase1['remote-gateway'], 128, $entered_remote_network, $entered_remote_mask)) {
							$input_errors[] = gettext("The local and remote networks of a phase 2 entry cannot overlap the outside of the tunnel (interface and remote gateway) configured in its phase 1.");
							break;
						}
					} elseif ($pconfig['mode'] == "vti") {
						if (($phase1['remote-gateway'] == '0.0.0.0') ||
						    (is_ipaddrv6($phase1['remote-gateway']) &&
						    (text_to_compressed_ip6($phase1['remote-gateway']) == '::'))) { 
							$input_errors[] = gettext("A remote gateway address of \"0.0.0.0\" or \"::\" is not compatible with a child Phase 2 in VTI mode.");
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
				if (empty($pconfig['halgos'])) {
					if (!strpos($ealgo['name'], "gcm")) {
						$input_errors[] = gettext("At least one hashing algorithm needs to be selected.");
						break;
					}
				}
			}
		}
	}

	if (!empty($pconfig['lifetime'])) {
		if (!is_numericint($pconfig['lifetime'])) {
			$input_errors[] = gettext("Life Time must be an integer.");
		}
		if (!empty($pconfig['rekey_time']) && ($pconfig['lifetime'] == $pconfig['rekey_time'])) {
			$input_errors[] = gettext("Life Time cannot be set to the same value as Rekey Time.");
		}
		if ($pconfig['rekey_time'] > $pconfig['lifetime']) {
			$input_errors[] = gettext("Life Time must be larger than Rekey Time.");
		}
	}
	if (!empty($pconfig['rekey_time']) && !is_numericint($pconfig['rekey_time'])) {
		$input_errors[] = gettext("Rekey Time must be an integer.");
	}
	if (!empty($pconfig['rand_time']) && !is_numericint($pconfig['rand_time'])) {
		$input_errors[] = gettext("Rand Time must be an integer.");
	}

	if (($pconfig['mode'] == "vti") && $pconfig['disabled']) {
		$input_errors[] = gettext("Cannot disable a VTI Phase 2 while the interface is assigned. Remove the interface assignment before disabling this P2.");
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

		if (($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6") || ($ph2ent['mode'] == "vti")) {
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
		$ph2ent['rekey_time'] = $pconfig['rekey_time'];
		$ph2ent['rand_time'] = $pconfig['rand_time'];
		$ph2ent['pinghost'] = $pconfig['pinghost'];
		$ph2ent['keepalive'] = ($pconfig['keepalive']) ? 'enabled' : 'disabled';
		$ph2ent['descr'] = $pconfig['descr'];

		if (isset($pconfig['mobile'])) {
			$ph2ent['mobile'] = true;
		}

		if ($ph2found === true && $a_phase2[$p2index]) {
			$a_phase2[$p2index] = $ph2ent;
		} else {
			$a_phase2[] = $ph2ent;
		}

		write_config(gettext("Saved IPsec tunnel Phase 2 configuration."));
		mark_subsystem_dirty('ipsec');

		header("Location: vpn_ipsec.php");
		exit;
	}
}

$localid_help_tunnel  = "Local network component of this IPsec security association.";
$localid_help_vti     = "Local point-to-point IPsec interface tunnel network address.";
$localid_help_mobile  = "Network reachable by mobile IPsec clients.";
$remoteid_help_tunnel = "Remote network component of this IPsec security association.";
$remoteid_help_vti    = "Remote point-to-point IPsec interface tunnel network address.";

if ($pconfig['mobile']) {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Mobile Clients"), gettext("Edit Phase 2"));
	$pglinks = array("", "vpn_ipsec.php", "vpn_ipsec_mobile.php", "@self");
	$editing_mobile = true;
} else {
	$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Tunnels"), gettext("Edit Phase 2"));
	$pglinks = array("", "vpn_ipsec.php", "vpn_ipsec.php", "@self");
	$editing_mobile = false;
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

function ealgos_to_pconfig(& $ealgos, & $pconfig) {

	$pconfig['ealgos'] = array();
	foreach ($ealgos as $algo_data) {
		$pconfig['ealgos'][] = $algo_data['name'];
		if (isset($algo_data['keylen'])) {
			$pconfig["keylen_".$algo_data['name']] = $algo_data['keylen'];
		}
	}

	return $ealgos;
}

function pconfig_to_idinfo($prefix, & $pconfig) {

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

function idinfo_to_pconfig($prefix, & $idinfo, & $pconfig) {

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

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[0] = array(gettext("Tunnels"), !$editing_mobile, "vpn_ipsec.php");
$tab_array[1] = array(gettext("Mobile Clients"), $editing_mobile, "vpn_ipsec_mobile.php");
$tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
$tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);

$form = new Form();

$section = new Form_Section('General Information');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Checkbox(
	'disabled',
	'Disabled',
	'Disable this phase 2 entry without removing it from the list. ',
	$pconfig['disabled']
));

$section->addInput(new Form_Select(
	'mode',
	'*Mode',
	$pconfig['mode'],
	$p2_modes
));

if (!empty($pconfig['ikeid'])) {
	$p1 = ipsec_get_phase1($pconfig['ikeid']);
	if (!empty($p1['descr'])) {
		$p1name = $p1['descr'];
	} else {
		$p1name = '<i>' . gettext('No description') . '</i> ';
	}
	$p1name .= ' (IKE ID ' . $pconfig['ikeid'];
	if ($pconfig['mobile']) {
		$p1name .= ', ' . gettext('Mobile');
	}
	$p1name .= ')';
	$section->addInput(new Form_StaticText(
		'Phase 1',
		$p1name .
		' <a class="fa fa-pencil" href="vpn_ipsec_phase1.php?ikeid=' . $p1['ikeid'] . '" title="' . gettext("Edit Phase 1 Entry") . '"></a>'
	));
}
if (!empty($pconfig['reqid'])) {
	$section->addInput(new Form_StaticText(
		'P2 reqid',
		$pconfig['reqid']
	));
}

$form->add($section);

$section = new Form_Section('Networks');

$group = new Form_Group('*Local Network');
$group->addClass('opt_localid');

$subnetarray = get_configured_interface_with_descr();
foreach ($subnetarray as $ifname => $ifdescr) {
	$subnetarray[$ifname] = $ifdescr . ' subnet';
}

$group->add(new Form_Select(
	'localid_type',
	null,
	$pconfig['localid_type'],
	['address' => gettext('Address'), 'network' => gettext('Network')] + $subnetarray
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'localid_address',
	null,
	$pconfig['localid_address']
))->setHelp('Address')->addMask('localid_netbits', $pconfig['localid_netbits'], 128, 0);

$group->setHelp('%s', '<span id="opt_localid_help"></span>');
$section->add($group);

$group = new Form_Group('NAT/BINAT translation');
$group->addClass('opt_natid');

$subnetarray = get_configured_interface_with_descr();
foreach ($subnetarray as $ifname => $ifdescr) {
	$subnetarray[$ifname] = $ifdescr . ' subnet';
}

// Tack none, address & network on the beginning
$subnetarray = array('none' => gettext('None'), 'address' => gettext('Address'), 'network' => gettext('Network')) + $subnetarray;

$group->add(new Form_Select(
	'natlocalid_type',
	null,
	$pconfig['natlocalid_type'],
	$subnetarray
))->setHelp('Type');

$group->add(new Form_IpAddress(
	'natlocalid_address',
	null,
	$pconfig['natlocalid_address']
))->setHelp('Address')->addMask('natlocalid_netbits', $pconfig['natlocalid_netbits'], 128, 0);

$group->setHelp('If NAT/BINAT is required on this network specify the address to be translated');
$section->add($group);

if (!isset($pconfig['mobile'])) {
	$group = new Form_Group('*Remote Network');
	$group->addClass('opt_remoteid');

	$group->add(new Form_Select(
		'remoteid_type',
		null,
		$pconfig['remoteid_type'],
		array('address' => gettext('Address'), 'network' => gettext('Network'))
	))->setHelp('Type');

	$group->add(new Form_IpAddress(
		'remoteid_address',
		null,
		$pconfig['remoteid_address']
	))->setHelp('Address')->addMask('remoteid_netbits', $pconfig['remoteid_netbits'], 128, 0);

	$group->setHelp('%s', '<span id="opt_remoteid_help"></span>');
	$section->add($group);
}

$form->add($section);

$section = new Form_Section('Phase 2 Proposal (SA/Key Exchange)');

$section->addInput(new Form_Select(
	'proto',
	'*Protocol',
	$pconfig['proto'],
	$p2_protos
))->setHelp('Encapsulating Security Payload (ESP) performs encryption and authentication, Authentication Header (AH) is authentication only.');

$i = 0;
$rows = count($p2_ealgos) - 1;

foreach ($p2_ealgos as $algo => $algodata) {
	$group = new Form_Group($i == 0 ? '*Encryption Algorithms':'');
	$group->addClass('encalg');

	// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
	$group->add(new Form_Checkbox(
		'ealgos[]',
		null,
		$algodata['name'],
		(is_array($pconfig['ealgos']) && in_array($algo, $pconfig['ealgos'])),
		$algo
	))->addClass('multi ealgoschk')->setAttribute('id', $algodata['name']);

	if (is_array($algodata['keysel'])) {
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
			$pconfig["keylen_".$algo],
			['auto' => gettext('Auto')] + $list
		));
	}


	if ($i == $rows) {
		$group->setHelp('Note: Blowfish, 3DES, and CAST128 provide weak security and should be avoided.');
	}

	$i++;
	$section->add($group);
}

$group = new Form_Group('*Hash Algorithms');

foreach ($p2_halgos as $algo => $algoname) {
	// Note: ID attribute of each element created is to be unique.  Not being used, suppressing it.
	$group->add(new Form_Checkbox(
		'halgos[]',
		null,
		$algoname,
		(empty($pconfig['halgos']) ? '' : in_array($algo, $pconfig['halgos'])),
		$algo
	))->addClass('multi')->setAttribute('id');

	$group->setHelp('Note: Hash is ignored with GCM algorithms. MD5 and SHA1 provide weak security and should be avoided.');
}

$section->add($group);

$sm = (!isset($pconfig['mobile']) || !isset($a_client['pfs_group']));
$helpstr = $sm ? '':'Set globally in mobile client options. ';
$helpstr .= 'Note: Groups 1, 2, 5, 22, 23, and 24 provide weak security and should be avoided.';

$section->addInput(new Form_Select(
	'pfsgroup',
	'PFS key group',
	$pconfig['pfsgroup'],
	$sm ? $p2_pfskeygroups:array()
))->setHelp($helpstr);

$form->add($section);

$section = new Form_Section('Expiration and Replacement');

$section->addInput(new Form_Input(
	'lifetime',
	'Life Time',
	'number',
	$pconfig['lifetime'],
	["placeholder" => ipsec_get_life_time($pconfig)]
))->setHelp('Hard Child SA life time, in seconds, after which the Child SA will be expired. ' .
		'Must be larger than Rekey Time. ' .
		'Cannot be set to the same value as Rekey Time. ' .
		'If left empty, defaults to 110% of Rekey Time. ' .
		'If both Life Time and Rekey Time are empty, defaults to 3960.');

$section->addInput(new Form_Input(
	'rekey_time',
	'Rekey Time',
	'number',
	$pconfig['rekey_time'],
	['min' => 0, "placeholder" => ipsec_get_rekey_time($pconfig)]
))->setHelp('Time, in seconds, before a Child SA establishes new keys. This works without interruption. ' .
		'Cannot be set to the same value as Life Time. ' .
		'Leave blank to use a default value of 90% Life Time. ' .
		'If both Life Time and Rekey Time are empty, defaults to 3600. ' .
		'Enter a value of 0 to disable, but be aware that when rekey is disabled, ' .
		'connections can be interrupted while new Child SA entries are negotiated.');

$section->addInput(new Form_Input(
	'rand_time',
	'Rand Time',
	'number',
	$pconfig['rand_time'],
	['min' => 0, "placeholder" => ipsec_get_rand_time($pconfig)]
))->setHelp('A random value up to this amount will be subtracted from Rekey Time to avoid simultaneous renegotiation. ' .
		'If left empty, defaults to 10% of Life Time. ' .
		'Enter 0 to disable randomness, but be aware that simultaneous renegotiation can lead to duplicate security associations.');

$form->add($section);

// Hidden inputs
if ($pconfig['mobile']) {
	$form->addGlobal(new Form_Input(
		'mobile',
		null,
		'hidden',
		'true'
	));
} else {
	$section = new Form_Section('Keep Alive');

	$section->addInput(new Form_IpAddress(
		'pinghost',
		'Automatically ping host',
		$pconfig['pinghost']
	))->setHelp('Sends an ICMP echo request inside the tunnel to the specified IP Address. ' .
			'Can trigger initiation of a tunnel mode P2, but does not trigger initiation of a VTI mode P2. ');

	$section->addInput(new Form_Checkbox(
		'keepalive',
		'Keep Alive',
		'Enable periodic keep alive check',
		$pconfig['keepalive']
	))->setHelp('Periodically checks to see if the P2 is disconnected and initiates when it is down. ' .
			'Does not send traffic inside the tunnel. Works for VTI and tunnel mode P2 entries. ' .
			'For IKEv2 without split connections, this only needs enabled on one P2.');
	$form->add($section);
}

$form->addGlobal(new Form_Input(
	'ikeid',
	null,
	'hidden',
	$pconfig['ikeid']
));

if (!empty($pconfig['reqid'])) {
	$form->addGlobal(new Form_Input(
		'reqid',
		null,
		'hidden',
		$pconfig['reqid']
	));
}

$form->addGlobal(new Form_Input(
	'uniqid',
	null,
	'hidden',
	$pconfig['uniqid']
));

print($form);

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$("form").submit(function() {
		disableInput('localid_type', false);
		disableInput('remoteid_type', false);
	});

	// ---------- On changing "Mode" ----------------------------------------------------------------------------------
	function change_mode() {

		value = $('#mode').val();

		disableInput('localid_type', false);
		disableInput('remoteid_type', false);
		if ((value == 'tunnel') || (value == 'tunnel6')) {
			hideClass('opt_localid', false);
			hideClass('opt_natid', false);
			$('#opt_localid_help').html("<?=$localid_help_mobile?>");

<?php	if (!isset($pconfig['mobile'])): ?>
			hideClass('opt_remoteid', false);
			hideClass('opt_natid', false);
			$('#opt_localid_help').html("<?=$localid_help_tunnel?>");
			$('#opt_remoteid_help').html("<?=$remoteid_help_tunnel?>");
<?php	endif; ?>
		} else if (value == 'vti') {
			hideClass('opt_localid', false);
			hideClass('opt_natid', true);
			hideClass('opt_remoteid', false);
			$('#localid_type').val('address');
			disableInput('localid_type', false);
			typesel_change_local(30);
			$('#remoteid_type').val('address');
			disableInput('remoteid_type', false);
			typesel_change_remote(30);
			$('#opt_localid_help').html("<?=$localid_help_vti?>");
			$('#opt_remoteid_help').html("<?=$remoteid_help_vti?>");
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
			} else if (value === "tunnel6") {
				bits = 64;
			}
		}

		var address_is_blank = !/\S/.test($('#natlocalid_address').val());

		switch ($("#natlocalid_type option:selected").index()) {
			case 0: /* none */
				disableInput('natlocalid_address', true);

				if (address_is_blank) {
					$('#natlocalid_netbits').val(0);
				}

				disableInput('natlocalid_netbits', true);
				break;
			case 1: /* address */
				disableInput('natlocalid_address', false);

				if (address_is_blank) {
					$('#natlocalid_netbits').val(bits);
				}

				disableInput('natlocalid_netbits', true);
				break;
			case 2: /* network */
				disableInput('natlocalid_address', false);
				disableInput('natlocalid_netbits', false);
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
			} else if (value === "tunnel6") {
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
				} else if (value === "tunnel6") {
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
		var hide = ($('#proto').val() != 'esp');
		hideClass('encalg', hide);
		$("input[name='halgos[]']").prop("disabled", !hide);
	}

	function change_aead() {
		var notaead = ['AES', 'Blowfish', '3DES', 'CAST128'];
		var arrayLength = notaead.length;
		for (var i = 0; i < arrayLength; i++) {
			if ($('#' + notaead[i]).prop('checked') || ($('#proto').val() != 'esp')) {
				$("input[name='halgos[]']").prop("disabled", false);
				return;
			} 
		}
		$("input[name='halgos[]']").prop("disabled", true);
	}

	// ---------- Monitor elements for change and call the appropriate display functions ----------

	 // Protocol
	$('#proto').change(function () {
		change_protocol();
	});

	// AEAD
	$(".ealgoschk").click(function () {
		change_aead();
	});

	 // Localid
	$('#localid_type').change(function () {
		typesel_change_local(<?=htmlspecialchars($pconfig['localid_netbits'])?>);
	});

	 // Remoteid
	$('#remoteid_type').change(function () {
		typesel_change_remote(<?=htmlspecialchars($pconfig['remoteid_netbits'])?>);
	});

	 // NATLocalid
	$('#natlocalid_type').change(function () {
		typesel_change_natlocal(<?=htmlspecialchars($pconfig['natlocalid_netbits'])?>);
	});

	 // Mode
	$('#mode').change(function () {
		change_mode();
	});

	// ---------- On initial page load ------------------------------------------------------------

	change_mode();
	change_protocol();
	change_aead();
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
