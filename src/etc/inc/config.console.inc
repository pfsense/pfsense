<?php
/*
 * config.console.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
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

require_once("config.inc");
require_once("globals.inc");
require_once("interfaces.inc");
require_once("util.inc");

/*
 * returns:
 * -2: error
 * -1: no interface found
 *  0: interface(s) assigned
 *  1: user quit
 */
function set_networking_interfaces_ports() {
	global $noreboot;
	global $config;
	global $g;
	global $fp;

	$fp = fopen('php://stdin', 'r');

	$memory = get_memory();
	$physmem = $memory[0];
	$realmem = $memory[1];

	if ($physmem < $g['minimum_ram_warning']) {
		echo "\n\n\n";
		echo gettext("DANGER!  WARNING!  ACHTUNG!") . "\n\n";
		printf(gettext('%1$s requires *AT LEAST* %2$s RAM to function correctly.%3$s'), $g['product_label'], $g['minimum_ram_warning_text'], "\n");
		printf(gettext('Only (%1$s) MB RAM has been detected, with (%2$s) available to %3$s.%4$s'), $realmem, $physmem, $g['product_label'], "\n");
		echo "\n" . gettext("Press ENTER to continue.") . " ";
		fgets($fp);
		echo "\n";
	}

	$iflist = get_interface_list('active', 'physical', true);

	/* Function flow is based on $key or the lack thereof */
	$key = null;

	echo <<<EOD

Valid interfaces are:


EOD;

	if (!is_array($iflist)) {
		echo gettext("No interfaces found!") . "\n";
		return (-1);
	} else {
		// ifsmallist is kept with spaces at the beginning and end to assist with str_replace() operations
		$ifsmallist = " ";
		foreach ($iflist as $iface => $ifa) {
			$friendly = convert_real_interface_to_friendly_interface_name($iface);
			$ifstatus = pfSense_get_interface_addresses($config['interfaces'][$friendly]['if']);
			if (is_array($ifstatus) && $ifstatus['linkstateup'])
				$status = "  (up)";
			else
				$status = "(down)";
			$ifsmallist = $ifsmallist . $iface. " ";
			echo sprintf("%-7s %s %s %s\n", $iface, $ifa['mac'],
				$status, substr($ifa['dmesg'], 0, 46));
		}
	}

	echo "\n" . gettext("Do VLANs need to be set up first?");
	echo "\n" .
		gettext(
			"If VLANs will not be used, or only for optional interfaces, it is typical to\n" .
			"say no here and use the webConfigurator to configure VLANs later, if required.") .
		"\n";
	echo "\n" . gettext("Should VLANs be set up now [y|n]?") . " ";

	$key = chop(fgets($fp));

	//Manually assign interfaces
	if (in_array($key, array('y', 'Y'))) {
		vlan_setup();
	}

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

		echo "\n\n" . gettext("VLAN interfaces:") . "\n\n";
		foreach ($config['vlans']['vlan'] as $vlan) {

			echo sprintf("% -16s%s\n", vlan_interface($vlan),
				"VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}");

			$iflist[vlan_interface($vlan)] = array();
			$ifsmallist = $ifsmallist . vlan_interface($vlan) . " ";
		}
	}

	echo <<<EOD

If the names of the interfaces are not known, auto-detection can
be used instead. To use auto-detection, please disconnect all
interfaces before pressing 'a' to begin the process.

EOD;

	do {
		echo "\n" . gettext("Enter the WAN interface name or 'a' for auto-detection") . " ";
		printf(gettext('%1$s(%2$s or a): '), "\n", trim($ifsmallist));
		$wanif = chop(fgets($fp));
		if ($wanif === "") {
			return (1);
		}
		if ($wanif === "a") {
			$wanif = autodetect_interface("WAN", $fp);
		} else if (!array_key_exists($wanif, $iflist)) {
			printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $wanif, "\n");
			unset($wanif);
			continue;
		}
		$ifsmallist = str_replace(" " . $wanif . " ", " ", $ifsmallist);
	} while (!$wanif);

	do {
		printf(gettext('%1$sEnter the LAN interface name or \'a\' for auto-detection %2$s' .
			'NOTE: this enables full Firewalling/NAT mode.%3$s' .
			'(%4$s a or nothing if finished):%5$s'), "\n", "\n", "\n", trim($ifsmallist), " ");

		$lanif = chop(fgets($fp));

		if ($lanif == "exit") {
			exit;
		}

		if ($lanif == "") {
			/* It is OK to have just a WAN, without a LAN so break if the user does not want LAN. */
			break;
		}

		if ($lanif === "a") {
			$lanif = autodetect_interface("LAN", $fp);
		} else if (!array_key_exists($lanif, $iflist)) {
			printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $lanif, "\n");
			unset($lanif);
			continue;
		}
		$ifsmallist = str_replace(" " . $lanif . " ", " ", $ifsmallist);
	} while (!$lanif);

	/* optional interfaces */
	$i = 0;
	$optif = array();

	if ($lanif <> "") {
		while (strlen(trim($ifsmallist)) > 0) {
			if (!empty($optif[$i])) {
				$i++;
			}
			$io = $i + 1;

			if ($config['interfaces']['opt' . $io]['descr']) {
				printf(gettext('%1$sOptional interface %2$s description found: %3$s'), "\n", $io, $config['interfaces']['opt' . $io]['descr']);
			}

			printf(gettext('%1$sEnter the Optional %2$s interface name or \'a\' for auto-detection%3$s' .
				'(%4$s a or nothing if finished):%5$s'), "\n", $io, "\n", trim($ifsmallist), " ");

			$optif[$i] = chop(fgets($fp));

			if ($optif[$i]) {
				if ($optif[$i] === "a") {
					$ad = autodetect_interface(gettext("Optional") . " " . $io, $fp);
					if ($ad) {
						$optif[$i] = $ad;
					} else {
						unset($optif[$i]);
					}
				} else if (!array_key_exists($optif[$i], $iflist)) {
					printf(gettext('%1$sInvalid interface name \'%2$s\'%3$s'), "\n", $optif[$i], "\n");
					unset($optif[$i]);
					continue;
				}
				$ifsmallist = str_replace(" " . $optif[$i] . " ", " ", $ifsmallist);
			} else {
				unset($optif[$i]);
				break;
			}
		}
	}

	/* check for double assignments */
	$ifarr = array_merge(array($lanif, $wanif), $optif);

	for ($i = 0; $i < (count($ifarr)-1); $i++) {
		for ($j = ($i+1); $j < count($ifarr); $j++) {
			if ($ifarr[$i] == $ifarr[$j]) {
				echo <<<EOD

Error: The same interface name cannot be assigned twice!

EOD;
				fclose($fp);
				return (-2);
			}
		}
	}

	echo "\n" . gettext("The interfaces will be assigned as follows:") . "\n\n";

	echo "WAN  -> " . $wanif . "\n";
	if ($lanif != "") {
		echo "LAN  -> " . $lanif . "\n";
	}
	for ($i = 0; $i < count($optif); $i++) {
		echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
	}

	echo "\n" . gettext("Do you want to proceed [y|n]?") . " ";
	$key = chop(fgets($fp));

	if (in_array($key, array('y', 'Y'))) {
		if ($lanif) {
			if (is_array($config['interfaces']['lan'])) {
				$upints = pfSense_interface_listget(IFF_UP);
				if (in_array($config['interfaces']['lan']['if'], $upints))
					interface_bring_down('lan', true);
			}
			if (!is_array($config['interfaces']['lan'])) {
				$config['interfaces']['lan'] = array();
			}
			$config['interfaces']['lan']['if'] = $lanif;
			$config['interfaces']['lan']['enable'] = true;
		} elseif (!platform_booting()) {

			echo "\n" . gettext("You have chosen to remove the LAN interface.") . "\n";
			echo "\n" . gettext("Would you like to remove the LAN IP address and \nunload the interface now [y|n]?") . " ";

			if (strcasecmp(chop(fgets($fp)), "y") == 0) {
				if (isset($config['interfaces']['lan']) && $config['interfaces']['lan']['if']) {
					mwexec("/sbin/ifconfig " . $config['interfaces']['lan']['if'] . " delete");
				}
			}
			if (isset($config['interfaces']['lan'])) {
				unset($config['interfaces']['lan']);
			}
			if (isset($config['dhcpd']['lan'])) {
				unset($config['dhcpd']['lan']);
			}
			if (isset($config['dhcpdv6']['lan'])) {
				unset($config['dhcpdv6']['lan']);
			}
			if (isset($config['interfaces']['lan']['if'])) {
				unset($config['interfaces']['lan']['if']);
			}
			if (isset($config['interfaces']['wan']['blockpriv'])) {
				unset($config['interfaces']['wan']['blockpriv']);
			}
			if (isset($config['shaper'])) {
				unset($config['shaper']);
			}
			if (isset($config['ezshaper'])) {
				unset($config['ezshaper']);
			}
			if (isset($config['nat'])) {
				unset($config['nat']);
			}
		} else {
			if (isset($config['interfaces']['lan']['if'])) {
				mwexec("/sbin/ifconfig " . $config['interfaces']['lan']['if'] . " delete");
			}
			if (isset($config['interfaces']['lan'])) {
				unset($config['interfaces']['lan']);
			}
			if (isset($config['dhcpd']['lan'])) {
				unset($config['dhcpd']['lan']);
			}
			if (isset($config['dhcpdv6']['lan'])) {
				unset($config['dhcpdv6']['lan']);
			}
			if (isset($config['interfaces']['lan']['if'])) {
				unset($config['interfaces']['lan']['if']);
			}
			if (isset($config['interfaces']['wan']['blockpriv'])) {
				unset($config['interfaces']['wan']['blockpriv']);
			}
			if (isset($config['shaper'])) {
				unset($config['shaper']);
			}
			if (isset($config['ezshaper'])) {
				unset($config['ezshaper']);
			}
			if (isset($config['nat'])) {
				unset($config['nat']);
			}
		}
		if (preg_match($g['wireless_regex'], $lanif)) {
			if (is_array($config['interfaces']['lan']) &&
			    !is_array($config['interfaces']['lan']['wireless'])) {
				$config['interfaces']['lan']['wireless'] = array();
			}
		} else {
			if (isset($config['interfaces']['lan'])) {
				unset($config['interfaces']['lan']['wireless']);
			}
		}

		if (is_array($config['interfaces']['wan'])) {
			$upints = pfSense_interface_listget(IFF_UP);
			if (in_array($config['interfaces']['wan']['if'], $upints))
				interface_bring_down('wan', true);
		}
		if (!is_array($config['interfaces']['wan'])) {
			$config['interfaces']['wan'] = array();
		}
		$config['interfaces']['wan']['if'] = $wanif;
		$config['interfaces']['wan']['enable'] = true;
		if (preg_match($g['wireless_regex'], $wanif)) {
			if (is_array($config['interfaces']['wan']) &&
			    !is_array($config['interfaces']['wan']['wireless'])) {
				$config['interfaces']['wan']['wireless'] = array();
			}
		} else {
			if (isset($config['interfaces']['wan'])) {
				unset($config['interfaces']['wan']['wireless']);
			}
		}

		for ($i = 0; $i < count($optif); $i++) {
			if (is_array($config['interfaces']['opt' . ($i+1)])) {
				$upints = pfSense_interface_listget(IFF_UP);
				if (in_array($config['interfaces']['opt' . ($i+1)]['if'], $upints))
					interface_bring_down('opt' . ($i+1), true);
			}
			if (!is_array($config['interfaces']['opt' . ($i+1)])) {
				$config['interfaces']['opt' . ($i+1)] = array();
			}

			$config['interfaces']['opt' . ($i+1)]['if'] = $optif[$i];

			/* wireless interface? */
			if (preg_match($g['wireless_regex'], $optif[$i])) {
				if (!is_array($config['interfaces']['opt' . ($i+1)]['wireless'])) {
					$config['interfaces']['opt' . ($i+1)]['wireless'] = array();
				}
			} else {
				unset($config['interfaces']['opt' . ($i+1)]['wireless']);
			}

			if (empty($config['interfaces']['opt' . ($i+1)]['descr'])) {
				$config['interfaces']['opt' . ($i+1)]['descr'] = "OPT" . ($i+1);
				unset($config['interfaces']['opt' . ($i+1)]['enable']);
			}
		}

		/* remove all other (old) optional interfaces */
		for (; isset($config['interfaces']['opt' . ($i+1)]); $i++) {
			unset($config['interfaces']['opt' . ($i+1)]);
		}

		printf(gettext("%sWriting configuration..."), "\n");
		write_config(gettext("Console assignment of interfaces"));
		printf(gettext("done.%s"), "\n");

		fclose($fp);

		echo gettext("One moment while the settings are reloading...");
		touch("{$g['tmp_path']}/assign_complete");

		if (file_exists("{$g['conf_path']}/trigger_initial_wizard")) {
			// Let the system know that the interface assign part of initial setup has been done.
			touch("{$g['conf_path']}/assign_complete");
		}

		echo gettext(" done!") . "\n";

		return (0);
	}
}

function autodetect_interface($ifname, $fp) {
	$iflist_prev = get_interface_list("media");
	echo <<<EOD

Connect the {$ifname} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
	fgets($fp);
	$iflist = get_interface_list("media");

	foreach ($iflist_prev as $ifn => $ifa) {
		if (!$ifa['up'] && $iflist[$ifn]['up']) {
			printf(gettext('Detected link-up on interface %1$s.%2$s'), $ifn, "\n");
			return $ifn;
		}
	}

	printf(gettext("No link-up detected.%s"), "\n");

	return null;
}

function interfaces_setup() {
	global $iflist, $config, $g, $fp;

	$iflist = get_interface_list();
}

function vlan_setup() {
	global $iflist, $config, $g, $fp;

	$iflist = get_interface_list();

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		echo "\n" . gettext("WARNING: all existing VLANs will be cleared if you proceed!") . "\n";
		echo "\n" . gettext("Do you want to proceed [y|n]?") . " ";

		if (strcasecmp(chop(fgets($fp)), "y") != 0) {
			return;
		}
	}

	init_config_arr(array('vlans', 'vlan'));
	echo "\n";

	$vlanif = 0;

	while (1) {
		$vlan = array();

		echo "\n\n" . gettext("VLAN Capable interfaces:") . "\n\n";
		if (!is_array($iflist)) {
			echo gettext("No interfaces found!") . "\n";
		} else {
			$vlan_capable = 0;
			foreach ($iflist as $iface => $ifa) {
				echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
				    $ifa['up'] ? "   (up)" : "");
				$vlan_capable++;
			}
		}

		if ($vlan_capable == 0) {
			echo gettext("No VLAN capable interfaces detected.") . "\n";
			return;
		}

		echo "\n" . gettext("Enter the parent interface name for the new VLAN (or nothing if finished):") . " ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist)) {
				printf(gettext(
				    '%1$sInvalid interface name \'%2$s\'%3$s'),
				    "\n", $vlan['if'], "\n");
				continue;
			}
		} else {
			break;
		}

		echo gettext("Enter the VLAN tag (1-4094):") . " ";
		$vlan['tag'] = chop(fgets($fp));
		$vlan['vlanif'] = vlan_interface($vlan);
		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			printf(gettext('%1$sInvalid VLAN tag \'%2$s\'%3$s'), "\n", $vlan['tag'], "\n");
			continue;
		}

		if (is_array($config['vlans']['vlan'])) {
			foreach ($config['vlans']['vlan'] as $existingvlan) {
				if ($vlan['if'] == $existingvlan['if'] && $vlan['tag'] == $existingvlan['tag']) {
					printf("\n\n" . gettext("This parent interface and VLAN already created."));
					continue 2;
				}
			}
		}
		$config['vlans']['vlan'][] = $vlan;
		$vlanif++;
	}
}

function check_for_alternate_interfaces() {
	global $config;

	// If the WAN and/or LAN devices in the factory default config do not exist,
	// then look for alternate devices.
	// This lets many systems boot a factory default config without being
	// forced to do interface assignment on the console.

	$specplatform = system_identify_specific_platform();
	$default_device = array();

	// If we recognise the platform, then specify the devices directly.
	switch ($specplatform['name']) {
		case 'alix':
			$default_device['wan'] = "vr1";
			$default_device['lan'] = "vr0";
			break;
		case 'APU':
			$default_device['wan'] = "re1";
			$default_device['lan'] = "re2";
			break;
		case 'Turbot Dual-E':
			$config['interfaces']['wan']['if'] = 'igb0';
			$config['interfaces']['lan']['if'] = 'igb1';
			break;
		case 'C2758':
			$config['interfaces']['wan']['if'] = 'igb0';
			$config['interfaces']['lan']['if'] = 'igb1';
			$config['interfaces']['opt1'] = array(
				'if' => 'igb2',
				'descr' => 'OPT1'
			);
			$config['interfaces']['opt2'] = array(
				'if' => 'igb3',
				'descr' => 'OPT2'
			);
			break;
		case 'RCC-VE':
		case 'SG-2220':
			/* SG-4860 or SG-8860 */
			if (does_interface_exist('igb4')) {
				$config['interfaces']['wan']['if'] = 'igb1';
				$config['interfaces']['lan']['if'] = 'igb0';
			} else {
				$config['interfaces']['wan']['if'] = 'igb0';
				$config['interfaces']['lan']['if'] = 'igb1';
			}
			/* It has 4 ports */
			if (does_interface_exist('igb3')) {
				$config['interfaces']['opt1'] = array(
					'if' => 'igb2',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'if' => 'igb3',
					'descr' => 'OPT2'
				);
			}
			/* It has 6 ports */
			if (does_interface_exist('igb5')) {
				$config['interfaces']['opt3'] = array(
					'if' => 'igb4',
					'descr' => 'OPT3'
				);
				$config['interfaces']['opt4'] = array(
					'if' => 'igb5',
					'descr' => 'OPT4'
				);
			}
			break;
		case '1537':
			if (does_interface_exist('cxl0')) {
				/* It has 10G SFP+ addon */
				$config['interfaces']['wan']['if'] = 'cxl0';
				$config['interfaces']['lan']['if'] = 'cxl1';
				$config['interfaces']['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT2'
				);
				$config['interfaces']['opt3'] = array(
					'if' => 'igb1',
					'descr' => 'OPT3'
				);
				$config['interfaces']['opt4'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT4'
				);
			} elseif (does_interface_exist('igb4')) {
				/* It has 4 port ethernet addon */
				$config['interfaces']['wan']['if'] = 'igb4';
				$config['interfaces']['lan']['if'] = 'igb5';
				$config['interfaces']['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
				$config['interfaces']['opt3'] = array(
					'if' => 'igb3',
					'descr' => 'OPT3'
				);
				$config['interfaces']['opt4'] = array(
					'if' => 'igb2',
					'descr' => 'OPT4'
				);
				$config['interfaces']['opt5'] = array(
					'if' => 'igb1',
					'descr' => 'OPT5'
				);
				$config['interfaces']['opt6'] = array(
					'if' => 'igb0',
					'descr' => 'OPT6'
				);
			} else {
				$config['interfaces']['wan']['if'] = 'igb0';
				$config['interfaces']['lan']['if'] = 'igb1';
				$config['interfaces']['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
			}
			break;
		case '1540':
		case '1541':
			if (does_interface_exist('igb2')) {
				/* It has 4 port Intel 1Gb expansion card */
				$config['interfaces']['wan']['if'] = 'igb4';
				$config['interfaces']['lan']['if'] = 'igb5';
				$config['interfaces']['opt1'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT2'
				);
				$config['interfaces']['opt3'] = array(
					'if' => 'igb3',
					'descr' => 'OPT3'
				);
				$config['interfaces']['opt4'] = array(
					'if' => 'igb2',
					'descr' => 'OPT4'
				);
				$config['interfaces']['opt5'] = array(
					'if' => 'igb1',
					'descr' => 'OPT5'
				);
				$config['interfaces']['opt6'] = array(
					'if' => 'igb0',
					'descr' => 'OPT6'
				);
			} elseif (does_interface_exist('cxl0')) {
				/* It has 2 port Chelsio 10Gb expansion card */
				$config['interfaces']['wan']['if'] = 'cxl0';
				$config['interfaces']['lan']['if'] = 'cxl1';
				$config['interfaces']['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'enable' => true,
					'if' => 'ix0',
					'descr' => 'OPT2'
				);
				$config['interfaces']['opt3'] = array(
					'if' => 'igb1',
					'descr' => 'OPT3'
				);
				$config['interfaces']['opt4'] = array(
					'enable' => true,
					'if' => 'ix1',
					'descr' => 'OPT4'
				);
			} else {
				$config['interfaces']['wan']['if'] = 'ix0';
				$config['interfaces']['lan']['if'] = 'ix1';
				$config['interfaces']['opt1'] = array(
					'if' => 'igb0',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'if' => 'igb1',
					'descr' => 'OPT2'
				);
			}
			break;
		case 'RCC':
			if (does_interface_exist('igb7')) {
				// has quad port expansion card
				$config['interfaces']['opt5'] = array(
					'if' => 'igb0',
					'descr' => 'OPT5'
				);
				$config['interfaces']['opt6'] = array(
					'if' => 'igb1',
					'descr' => 'OPT6'
				);
				$config['interfaces']['opt7'] = array(
					'if' => 'igb2',
					'descr' => 'OPT7'
				);
				$config['interfaces']['opt8'] = array(
					'if' => 'igb3',
					'descr' => 'OPT8'
				);
				$config['interfaces']['wan']['if'] = 'igb4';
				$config['interfaces']['lan']['if'] = 'igb6';
				$config['interfaces']['opt1'] = array(
					'if' => 'igb5',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'if' => 'igb7',
					'descr' => 'OPT2'
				);
			} else {
				$config['interfaces']['wan']['if'] = 'igb0';
				$config['interfaces']['lan']['if'] = 'igb2';
				$config['interfaces']['opt1'] = array(
					'if' => 'igb1',
					'descr' => 'OPT1'
				);
				$config['interfaces']['opt2'] = array(
					'if' => 'igb3',
					'descr' => 'OPT2'
				);
			}
			$config['interfaces']['opt3'] = array(
				'enable' => true,
				'if' => 'ix0',
				'descr' => 'OPT3'
			);
			$config['interfaces']['opt4'] = array(
				'enable' => true,
				'if' => 'ix1',
				'descr' => 'OPT4'
			);
			break;
		case '5100':
			$config['interfaces']['wan']['if'] = 'igb0';
			$config['interfaces']['lan']['if'] = 'igb1';
			$config['interfaces']['opt1'] = array(
				'enable' => true,
				'if' => 'ix0',
				'descr' => 'OPT1'
			);
			$config['interfaces']['opt2'] = array(
				'enable' => true,
				'if' => 'ix1',
				'descr' => 'OPT2'
			);
			$config['interfaces']['opt3'] = array(
				'enable' => true,
				'if' => 'ix2',
				'descr' => 'OPT3'
			);
			$config['interfaces']['opt4'] = array(
				'enable' => true,
				'if' => 'ix3',
				'descr' => 'OPT4'
			);
			break;
		default:
			$default_device['wan'] = "";
			$default_device['lan'] = "";
			break;
	}

	// Other common device names can be put here and will be looked for
	// if the system was not one of the known platforms.
	$other_devices_arr['wan'] = array("vr1", "re1", "igb0", "em0");
	$other_devices_arr['lan'] = array("vr0", "re2", "igb1", "em1");
	$interface_assignment_changed = false;

	foreach ($other_devices_arr as $ifname => $other_devices) {
		if (!does_interface_exist($config['interfaces'][$ifname]['if'])) {
			if (does_interface_exist($default_device[$ifname])) {
				$config['interfaces'][$ifname]['if'] = $default_device[$ifname];
				$interface_assignment_changed = true;
			} else {
				foreach ($other_devices as $other_device) {
					if (does_interface_exist($other_device)) {
						$config['interfaces'][$ifname]['if'] = $other_device;
						$interface_assignment_changed = true;
						break;
					}
				}
			}
		}
	}

	if ($interface_assignment_changed) {
		write_config("Factory default boot detected WAN " . $config['interfaces']['wan']['if'] . " and LAN " . $config['interfaces']['lan']['if']);
	}
}

?>
