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

function switch_ufw_get_system_vlans() {

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

function switch_get_system_vlans() {

	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
	case "uFW":
		return (switch_ufw_get_system_vlans());
		break;
	}

	return (NULL);
}

function switch_ufw_map_port($port) {

	$ports = array("host", "cpsw0", "cpsw1");

	if ($port < 0)
		$port = 0;
	if ($port >= count($ports))
		$port = count($ports) - 1;

	return ($ports[$port]);
}

function switch_map_port($port) {

	$platform = system_identify_specific_platform();
	switch ($platform['name']) {
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
	case "ROGUE-1":
		return ("ROGUE-1");
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

?>
