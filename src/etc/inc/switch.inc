<?php
/*
 * switch.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
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

/* include all configuration functions */
require_once("globals.inc");

function switch_default_get_system_vlans($dot1q_only) {

	/* Defaults to a single switch. */
	$swdevices = switch_get_devices();
	if (!is_array($swdevices)) {
		return (NULL);
	}
	$swdevice = $swdevices[0];
	$swinfo = pfSense_etherswitch_getinfo($swdevice);
	if ($swinfo == NULL) {
		return (NULL);
	}
	for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
		$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $i);
		if ($vgroup == NULL) {
			continue;
		}
		if (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "PORT") {
			$vgroup['switchname'] = $swinfo['name'];
			$vlans[] = $vgroup;
		} elseif (isset($swinfo['vlan_mode']) && $swinfo['vlan_mode'] == "DOT1Q" && $vgroup['vid'] == 1) {
			$vgroup['switchname'] = $swinfo['name'];
			$vlans[] = $vgroup;
		}
	}
	return ($vlans);
}

function ufw_get_system_vlans_array()
{
	$ports = array(
		array("port" => "cpsw0", "ofw_node" => "/ocp/ethernet@4a100000/slave@4a100200"),   /* Port 0 */
		array("port" => "cpsw1", "ofw_node" => "/ocp/ethernet@4a100000/slave@4a100300"),   /* Port 1 */
	);

	$vlans = array();

	for ($i = 0; $i < count($ports); $i++) {
		$ofw_node = $ports[$i]['ofw_node'];
		$handle = popen("/usr/sbin/ofwdump -P dual_emac_res_vlan -R $ofw_node 2>&1", 'rb');
		$read = fread($handle, 16);
		pclose($handle);
		$array = unpack("N", $read);
		if (isset($array[1]))
			$vlans[] = array("if" => $ports[$i]['port'], "vlan" => $array[1]);
	}
	return ($vlans);
}

function switch_ufw_get_system_vlans($dot1q_only) {

	/* Only one switch on uFW. */
	$swdevice = "/dev/etherswitch0";
	$vlans_system = ufw_get_system_vlans_array();
	$swinfo = pfSense_etherswitch_getinfo($swdevice);
	if ($swinfo == NULL) {
		return (NULL);
	}
	$vlans = array();
	for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
		$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $i);
		if ($vgroup == NULL) {
			continue;
		}
		for ($j = 0; $j < count($vlans_system); $j++) {
			if ($vlans_system[$j]['vlan'] != $vgroup['vid'])
				continue;
			$vgroup['if'] = $vlans_system[$j]['if'];
			$vgroup['switchname'] = $swinfo['name'];
			$vlans[] = $vgroup;
			break;
		}
	}
	return ($vlans);
}

function switch_get_system_vlans($dot1q_only = false) {

	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
	case "A37XX":
	case "PLCC-B":
	case "ROGUE-1":
		return (switch_default_get_system_vlans($dot1q_only));
		break;
	case "uFW":
		return (switch_ufw_get_system_vlans($dot1q_only));
		break;
	}

	return (NULL);
}

function switch_espressobin_map_port($port) {

	$ports = array("LAN Uplink", "WAN", "LAN 1", "LAN 2");

	if ($port < 0)
		$port = 0;
	if ($port >= count($ports))
		$port = count($ports) - 1;

	return ($ports[$port]);
}

function switch_ufw_map_port($port) {

	$ports = array("host", "cpsw0", "cpsw1");

	if ($port < 0)
		$port = 0;
	if ($port >= count($ports))
		$port = count($ports) - 1;

	return ($ports[$port]);
}

function switch_plcc_map_port($port) {

	$ports = array("iCPU", "ETH1", "ETH2", "ETH3", "ETH4", "ETH5",
	    "ETH6", "ETH7", "ETH8", "Uplink 2", "Uplink 1");

	if ($port < 0)
		$port = 0;
	if ($port >= count($ports))
		$port = count($ports) - 1;

	return ($ports[$port]);
}

function switch_r1_map_port($port) {

	$ports = array("lan0", "LAN 1", "LAN 2", "LAN 3", "LAN 4", "LAN Uplink");

	if ($port < 0)
		$port = 0;
	if ($port >= count($ports))
		$port = count($ports) - 1;

	return ($ports[$port]);
}

function switch_map_port($port) {

	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
	case "A37XX":
		return (switch_espressobin_map_port($port));
		break;
	case "PLCC-B":
		return (switch_plcc_map_port($port));
		break;
	case "ROGUE-1":
		return (switch_r1_map_port($port));
		break;
	case "uFW":
		return (switch_ufw_map_port($port));
		break;
	}

	return (NULL);
}

function switch_get_devices() {

	$swdevices = array();
	$platform = system_identify_specific_platform();
	if (!isset($platform) || !is_array($platform))
		return ($swdevices);

	switch ($platform['name']) {
	case "A37XX":
	case "PLCC-B":
	case "ROGUE-1":
	case "uFW":
		/* Only one switch. */
		$swdevices[] = "/dev/etherswitch0";
		break;
	}

	return ($swdevices);
}

function switch_get_title() {

	$platform = system_identify_specific_platform();
	if (!isset($platform) || !is_array($platform))
		return ("");

	switch ($platform['name']) {
	case "A37XX":
		return ("Armada 3700");
		break;
	case "PLCC-B":
		return ("XG-7100");
		break;
	case "ROGUE-1":
		return ("SG-3100");
		break;
	case "uFW":
		return ("SG-1000");
		break;
	}

	return ("");
}

function switch_setvgroup($swidx, $vgroup, $vlan, $members = NULL) {

	$devices = switch_get_devices();
	if ($swidx >= count($devices)) {
		return (false);
	}

	return (pfSense_etherswitch_setvlangroup($devices[$swidx], $vgroup,
	    $vlan, $members));  
}

function switch_findvgroup($swidx, $vlan) {

	$devices = switch_get_devices();
	if ($swidx >= count($devices)) {
		return (-1);
	}

	$swinfo = pfSense_etherswitch_getinfo($swdevice);
	if ($swinfo == NULL) {
		return (-1);
	}

	for ($i = 0; $i < $swinfo['nvlangroups']; $i++) {
		$vgroup = pfSense_etherswitch_getvlangroup($swdevice, $i);
		if ($vgroup == NULL) {
			continue;
		}
		if ($vlan == $vgroup['vid'])
			return ($i);
	}

	return (-1);
}

function switch_get_configured_interface_with_descr($swdevice = NULL) {

	$rtrn = array();
	if ($swdevice == NULL)
		return ($rtrn);

	if (strncasecmp($swdevice, "/dev/etherswitch", 16) != 0)
		return ($rtrn);
	$swdev = substr($swdevice, 16);
	$swinfo = pfSense_etherswitch_getinfo($swdevice);
	for ($i = 0; $i < $swinfo['nports']; $i++) {
		if (isset($swinfo['switch_caps']['PORTS_MASK']) &&
		    $swinfo['switch_caps']['PORTS_MASK'] == 1 &&
		    (!isset($swinfo['ports_mask'][$i]) ||
		    $swinfo['ports_mask'][$i] != 1)) {
			continue;
		}
		$port = pfSense_etherswitch_getport($swdevice, $i);
		if ($port == NULL) {
			continue;
		}

		$swport = switch_map_port($port['port']);
		if ($swport != NULL) {
			$rtrn["switch".$swdev.".port".$i] = "$swport";
		} else {
			$rtrn["switch".$swdev.".port".$i] = htmlspecialchars($port['port']);
		}
	}

	return ($rtrn);
}

function switch_port_is_enabled($swinfo = NULL, $port = NULL) {

	if ($swinfo == NULL || !is_array($swinfo) ||
	    $port == NULL || !is_numeric($port)) {
		return (false);
	}
	if ($port < 0 || $port >= $swinfo['nports']) {
		return (false);
	}
	if ($swinfo['switch_caps']['PORTS_MASK'] == 1 &&
	    (!isset($swinfo['ports_mask'][$port]) || $swinfo['ports_mask'][$port] != 1)) {
		return (false);
	}
	return (true);
}

function switch_set_vlan_mode($swdev = NULL, $vlanmode = NULL) {

	if ($swdev == NULL || $vlanmode == NULL) {
		return (false);
	}

	switch ($vlanmode) {
	case "DOT1Q":
		mwexec("/sbin/etherswitchcfg config vlan_mode dot1q");
		break;
	case "PORT":
		mwexec("/sbin/etherswitchcfg config vlan_mode port");
		break;
	default:
		return (false);
	}

	return (true);
}

function espressobin_switch_initial_setup() {
	global $config;

	if (!isset($config['switches']['switch']) || !is_array($config['switches']['switch'])) {
		unset($config['switches']['switch']);
		$config['switches']['switch'] = array();
	}

	/* Default settings for the espressobin switch. */
	$switches = &$config['switches']['switch'];
	$switch = array();
	$switch['device'] = "/dev/etherswitch0";
	$switch['vlanmode'] = "DOT1Q";
	$switch['vlangroups']['vlangroup'] = array();
	$vgroup = array();
	$vgroup['vgroup'] = "0";
	$vgroup['vlanid'] = "1";
	$vgroup['members'] = "0";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$vgroup = array();
	$vgroup['vgroup'] = "1";
	$vgroup['vlanid'] = "4090";
	$vgroup['descr'] = "WAN";
	$vgroup['members'] = "0t 1";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$vgroup = array();
	$vgroup['vgroup'] = "2";
	$vgroup['vlanid'] = "4091";
	$vgroup['descr'] = "LAN";
	$vgroup['members'] = "0t 2 3";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$switch['swports']['swport'] = array();
	$swport = array();
	$swport['port'] = "1";
	$swport['pvid'] = "4090";
	$switch['swports']['swport'][] = $swport;
	$swport = array();
	$swport['port'] = "2";
	$swport['pvid'] = "4091";
	$switch['swports']['swport'][] = $swport;
	$swport = array();
	$swport['port'] = "3";
	$swport['pvid'] = "4091";
	$switch['swports']['swport'][] = $swport;
	$switches[] = $switch;
	write_config();
}

function plccb_switch_initial_setup() {
	global $config;

	if (!isset($config['switches']['switch']) || !is_array($config['switches']['switch'])) {
		unset($config['switches']['switch']);
		$config['switches']['switch'] = array();
	}

	/* Default settings for the espressobin switch. */
	$switches = &$config['switches']['switch'];
	$switch = array();
	$switch['device'] = "/dev/etherswitch0";
	$switch['vlanmode'] = "DOT1Q";
	$switch['vlangroups']['vlangroup'] = array();
	$vgroup = array();
	$vgroup['vgroup'] = "0";
	$vgroup['vlanid'] = "1";
	$vgroup['members'] = "0";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$vgroup = array();
	$vgroup['vgroup'] = "1";
	$vgroup['vlanid'] = "4090";
	$vgroup['descr'] = "WAN";
	$vgroup['members'] = "10t 1";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$vgroup = array();
	$vgroup['vgroup'] = "2";
	$vgroup['vlanid'] = "4091";
	$vgroup['descr'] = "LAN";
	$vgroup['members'] = "10t 2 3 4 5 6 7 8";
	$switch['vlangroups']['vlangroup'][] = $vgroup;
	$switch['swports']['swport'] = array();
	$swport = array();
	$swport['port'] = "1";
	$swport['pvid'] = "4090";
	$switch['swports']['swport'][] = $swport;
	for ($i = 2; $i <= 8; $i++) {
		$swport = array();
		$swport['port'] = "${i}";
		$swport['pvid'] = "4091";
		$switch['swports']['swport'][] = $swport;
	}
	$switches[] = $switch;
	write_config();
}

function r1_switch_initial_setup() {
	global $config;

	if (!isset($config['switches']['switch']) || !is_array($config['switches']['switch'])) {
		unset($config['switches']['switch']);
		$config['switches']['switch'] = array();
	}

	/* Default settings for the SG-3100 switch. */
	$switches = &$config['switches']['switch'];
	$switch = array();
	$switch['device'] = "/dev/etherswitch0";
	$switch['vlanmode'] = "PORT";
	$switches[] = $switch;
	write_config();
}

function default_switch_configure() {
	global $config;

	$switches = $config['switches']['switch'];
	if (!isset($switches) || !is_array($switches)) {
		$platform = system_identify_specific_platform();
		switch ($platform['name']) {
		case "A37XX":
			espressobin_switch_initial_setup();
			break;
		case "PLCC-B":
			plccb_switch_initial_setup();
			break;
		case "ROGUE-1":
			r1_switch_initial_setup();
			return;
			break;
		default:
			return;
		}
	}
	/* Find the default switch settings. */
	$swid = -1;
	$switches = $config['switches']['switch'];	/* Update our copy. Can be modified by above xxx_initial_setup(). */
	foreach($switches as $sid => $switch) {
		if ($switch['device'] != "/dev/etherswitch0") {
			continue;
		}
		$swid = $sid;
		break;
	}
	if ($swid == -1) {
		return;
	}
	$switch = $switches[$swid];

	/* Set switch mode. */
	if (isset($switch['vlanmode'])) {
		switch_set_vlan_mode($switch['device'], $switch['vlanmode']);
	}

	/* Set vlangroups. */
	$vgroups = $switch['vlangroups']['vlangroup'];
	if (isset($vgroups) && is_array($vgroups)) {
		foreach($vgroups as $vgid => $vgroup) {

			$vgmembers = array();
			if (isset($vgroup['members'])) {
				$members = explode(" ", $vgroup['members']);
			} else {
				$members = array();
			}
			foreach($members as $m) {
				if ($m[1] == "t") {
					$m = substr($m, 0, 1);
					$vgmembers[$m] = array();
					$vgmembers[$m]['tagged'] = 1;
				} else {
					$vgmembers[$m] = array();
				}
			}

			pfSense_etherswitch_setvlangroup($switch['device'],
			    $vgroup['vgroup'], $vgroup['vlanid'], $vgmembers);
		}
	}

	/* Set PVID. */
	$swports = $switch['swports']['swport'];
	if (!isset($swports) || !is_array($swports)) {
		return;
	}
	foreach($swports as $pid => $swport) {
		if (isset($swport['pvid'])) {
			pfSense_etherswitch_setport($switch['device'],
			    $swport['port'], $swport['pvid']);
		}
	}
}

function switches_configure() {

	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
	case "A37XX":
	case "PLCC-B":
	case "ROGUE-1":
	case "uFW":
		break;
	default:
		return;
	}
	if (!platform_booting()) {
		return;
	}
	echo gettext("Configuring switch...");
	switch ($platform['name']) {
	case "A37XX":
	case "PLCC-B":
	case "ROGUE-1":
		default_switch_configure();
		break;
	case "uFW":
		break;
	}
	echo gettext("done.") . "\n";
}
?>
