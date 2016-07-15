<?php
/*
 * config.console.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Electric Sheep Fencing, LLC
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
		printf(gettext("%s requires *AT LEAST* %s RAM to function correctly.%s"), $g['product_name'], $g['minimum_ram_warning_text'], "\n");
		printf(gettext("Only (%s) MB RAM has been detected, with (%s) available to %s.%s"), $realmem, $physmem, $g['product_name'], "\n");
		echo "\n" . gettext("Press ENTER to continue.") . " ";
		fgets($fp);
		echo "\n";
	}

	$iflist = get_interface_list();

	/* Function flow is based on $key and $auto_assign or the lack thereof */
	$key = null;

	/* Only present auto interface option if running from the installer media and interface mismatch*/
	if ((preg_match("/cdrom/", $g['platform'])) && is_interface_mismatch()) {
		$auto_assign = false;
	}

	echo <<<EOD

Valid interfaces are:


EOD;

	if (!is_array($iflist)) {
		echo gettext("No interfaces found!") . "\n";
		$iflist = array();
	} else {
		foreach ($iflist as $iface => $ifa) {
			$friendly = convert_real_interface_to_friendly_interface_name($iface);	
			$ifstatus = pfSense_get_interface_addresses($config['interfaces'][$friendly]['if']);
			if (is_array($ifstatus) && $ifstatus['linkstateup'])
				$status = "  (up)";
			else
				$status = "(down)";
			$ifsmallist = trim($ifsmallist . " " . $iface);
			echo sprintf("% -7s%s %s %s\n", $iface, $ifa['mac'],
				$status, substr($ifa['dmesg'], 0, 48));
		}
	}

	if ($auto_assign) {
		echo <<<EOD

		!!! Installation Media Detected: Auto Interface Option !!!!
BEGIN MANUAL CONFIGURATION OR THE SYSTEM WILL PROCEED WITH AUTO CONFIGURATION.

EOD;
	}

	echo "\n" . gettext("Do VLANs need to be set up first?");
	echo "\n" .
		gettext(
			"If VLANs will not be used, or only for optional interfaces, it is typical to\n" .
			"say no here and use the webConfigurator to configure VLANs later, if required.") .
		"\n";
	echo "\n" . gettext("Should VLANs be set up now [y|n]?") . " ";

	if ($auto_assign) {
		$key = timeout();
	} else {
		$key = chop(fgets($fp));
	}

	if (!isset($key) and $auto_assign) {	// Auto Assign Interfaces
		do {
			echo <<<EOD

   !!! Auto Assigning Interfaces !!!

For setup purposes, there must be at least one NIC connected for
the LAN. If a second NIC is connected, it will be assigned to the
WAN. Otherwise, WAN will be temporarily assigned to the next
available NIC found regardless of activity. The WAN interface
should then be assigned and configured as required.

Please make the pfSense NIC connections now.
The system will continue checking until they have been made.

Searching for active interfaces...

EOD;
			unset($wanif, $lanif);

			$media_iflist = $plugged_in = array();
			$media_iflist = get_interface_list("media");
			foreach ($media_iflist as $iface => $ifa) {
				if ($ifa['up']) {
					$plugged_in[] = $iface;
				}
			}

			$lanif = array_shift($plugged_in);
			$wanif = array_shift($plugged_in);

			if (isset($lanif) && !isset($wanif)) {
				foreach ($iflist as $iface => $ifa) {
					if ($iface != $lanif) {
						$wanif = $iface;
						break;
					}
				}
			}

			echo <<<EOD

Assigned WAN to : $wanif
Assigned LAN to : $lanif

If these assignments are not suitable,
press any key to go back to manual configuration.

EOD;
			$key = timeout(20);
			if (isset($key)) {
				return;
			}
		} while (!isset($wanif));

		$config['system']['enablesshd'] = 'enabled';
		$key = 'y';

	} else {
		//Manually assign interfaces
		if (in_array($key, array('y', 'Y'))) {
			vlan_setup();
		}

		if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

			echo "\n\n" . gettext("VLAN interfaces:") . "\n\n";
			foreach ($config['vlans']['vlan'] as $vlan) {

				echo sprintf("% -16s%s\n", "{$vlan['if']}_vlan{$vlan['tag']}",
					"VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}");

				$iflist[$vlan['if'] . '_vlan' . $vlan['tag']] = array();
				$ifsmallist = trim($ifsmallist . " " . $vlan['if'] . '_vlan' . $vlan['tag']);
			}
		}

		echo <<<EOD

If the names of the interfaces are not known, auto-detection can
be used instead. To use auto-detection, please disconnect all
interfaces before pressing 'a' to begin the process.

EOD;

		do {
			echo "\n" . gettext("Enter the WAN interface name or 'a' for auto-detection") . " ";
			printf(gettext("%s(%s or a): "), "\n", $ifsmallist);
			$wanif = chop(fgets($fp));
			if ($wanif === "") {
				return;
			}
			if ($wanif === "a") {
				$wanif = autodetect_interface("WAN", $fp);
			} else if (!array_key_exists($wanif, $iflist)) {
				printf(gettext("%sInvalid interface name '%s'%s"), "\n", $wanif, "\n");
				unset($wanif);
				continue;
			}
			$ifsmallist = trim(str_replace("  ", " ", str_replace($wanif, "", $ifsmallist)));
		} while (!$wanif);

		do {
			printf(gettext("%sEnter the LAN interface name or 'a' for auto-detection %s" .
				"NOTE: this enables full Firewalling/NAT mode.%s" .
				"(%s a or nothing if finished):%s"), "\n", "\n", "\n", $ifsmallist, " ");

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
				printf(gettext("%sInvalid interface name '%s'%s"), "\n", $lanif, "\n");
				unset($lanif);
				continue;
			}
			$ifsmallist = trim(str_replace("  ", " ", str_replace($lanif, "", $ifsmallist)));
		} while (!$lanif);

		/* optional interfaces */
		$i = 0;
		$optif = array();

		if ($lanif <> "") {
			while (1) {
				if ($optif[$i]) {
					$i++;
				}
				$io = $i + 1;

				if ($config['interfaces']['opt' . $io]['descr']) {
					printf(gettext("%sOptional interface %s description found: %s"), "\n", $io, $config['interfaces']['opt' . $io]['descr']);
				}

				printf(gettext("%sEnter the Optional %s interface name or 'a' for auto-detection%s" .
					"(%s a or nothing if finished):%s"), "\n", $io, "\n", $ifsmallist, " ");

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
						printf(gettext("%sInvalid interface name '%s'%s"), "\n", $optif[$i], "\n");
						unset($optif[$i]);
						continue;
					}
					$ifsmallist = trim(str_replace("  ", " ", str_replace($optif[$i], "", $ifsmallist)));
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
					return;
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
	}

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
		} elseif (!platform_booting() && !$auto_assign) {

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

		if (platform_booting()) {
			return;
		}

		echo gettext("One moment while the settings are reloading...");
		echo gettext(" done!") . "\n";

		touch("{$g['tmp_path']}/assign_complete");

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
			printf(gettext("Detected link-up on interface %s.%s"), $ifn, "\n");
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

	$config['vlans']['vlan'] = array();
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
				if (is_jumbo_capable($iface)) {
					echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
						$ifa['up'] ? "   (up)" : "");
					$vlan_capable++;
				}
			}
		}

		if ($vlan_capable == 0) {
			echo gettext("No VLAN capable interfaces detected.") . "\n";
			return;
		}

		echo "\n" . gettext("Enter the parent interface name for the new VLAN (or nothing if finished):") . " ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist) or
			    !is_jumbo_capable($vlan['if'])) {
				printf(gettext("%sInvalid interface name '%s'%s"), "\n", $vlan['if'], "\n");
				continue;
			}
		} else {
			break;
		}

		echo gettext("Enter the VLAN tag (1-4094):") . " ";
		$vlan['tag'] = chop(fgets($fp));
		$vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			printf(gettext("%sInvalid VLAN tag '%s'%s"), "\n", $vlan['tag'], "\n");
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

function netgate_hardware_setup() {
	global $config, $g;

	$platform = system_identify_specific_platform();
	$flavor = $g['default-config-flavor'];

	/* Determine interfaces */
	switch ($platform['name']) {
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
	case 'APU':
		$config['interfaces']['wan']['if'] = 're1';
		$config['interfaces']['lan']['if'] = 're2';
		$config['interfaces']['opt1'] = array(
			'if' => 're0',
			'descr' => 'OPT1'
		);
		break;
	case 'RCC-VE':
	case 'RCC-DFF':
		/* SG-4460 or SG-8860 */
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
			if ($g['platform'] == 'cdrom') {
				$config['interfaces']['opt1']['enable'] = true;
				$config['interfaces']['opt1']['ipaddr'] = 'dhcp';
			}
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
	case 'XG-1540':
		if (does_interface_exist('igb2')) {
			/* It has 4 port Intel 1Gb expansion card */
			$config['interfaces']['wan']['if'] = 'igb4';
			$config['interfaces']['lan']['if'] = 'igb5';
			$config['interfaces']['opt1'] = array(
				'if' => 'ix0',
				'descr' => 'OPT1'
			);
			$config['interfaces']['opt2'] = array(
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
				'if' => 'ix0',
				'descr' => 'OPT2'
			);
			$config['interfaces']['opt3'] = array(
				'if' => 'igb1',
				'descr' => 'OPT3'
			);
			$config['interfaces']['opt4'] = array(
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
		if ($g['platform'] == 'cdrom') {
			$config['interfaces']['opt1']['enable'] = true;
			$config['interfaces']['opt1']['ipaddr'] = 'dhcp';
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
		}
		if ($g['platform'] == 'cdrom') {
			$config['interfaces']['opt1']['enable'] = true;
			$config['interfaces']['opt1']['ipaddr'] = 'dhcp';
		}
		$config['interfaces']['opt2'] = array(
			'if' => 'igb3',
			'descr' => 'OPT2'
		);
		$config['interfaces']['opt3'] = array(
			'if' => 'ix0',
			'descr' => 'OPT3'
		);
		$config['interfaces']['opt4'] = array(
			'if' => 'ix1',
			'descr' => 'OPT4'
		);
		break;
	}

	/* Enable powerd */
	switch ($platform['name']) {
	case 'RCC':
	case 'RCC-VE':
	case 'RCC-DFF':
	case 'XG-1540':
		$config['system']['powerd_enable'] = true;
		break;
	}

	switch ($g['default-config-flavor']) {
	case "ec2":
	case "ec2-csm":
	case "azure":
	case "kvm":
	case "bhyve":
	case "openstack-csm":
		$config['sysctl']['item'][] = array(
			'tunable' => 'debug.trace_on_panic',
			'value'   => 1,
			'descr'   => 'Print stack trace on kernel panic'
		);
		$config['sysctl']['item'][] = array(
			'tunable' => 'debug.debugger_on_panic',
			'value'   => 0,
			'descr'   => 'Run debugger on kernel panic'
		);

		$config['system']['enablesshd'] = true;

		$config['system']['doinitialsetup'] = true;

		unset($config['interfaces']['wan']['blockpriv']);
		unset($config['dhcpd']);
		break;
	}

	switch ($flavor) {
	case "kvm":
	case "bhyve":
		$config['interfaces']['wan']['if'] = 'vtnet0';
		$config['interfaces']['lan']['if'] = 'vtnet1';
		break;

	case "openstack":
	case "azure":
	case "ec2":
	case "ec2-csm":
		unset($config['interfaces']['wan']['ipaddrv6']);

		if (is_array($config['system']['user'])) {
			foreach ($config['system']['user'] as &$user) {
				if ($user['name'] == 'admin') {
					$user['priv'][] = 'user-ipsec-xauth-dialin';
					break;
				}
			}
			unset ($user);
		}

		$config['system']['webgui']['nohttpreferercheck'] = true;
		$config['system']['webgui']['max_procs'] = 2;
		$config['system']['webgui']['nodnsrebindcheck'] = true;

		if ($flavor != 'openstack') {
			$config['system']['webgui']['noantilockout'] = true;
		}

		$replace_msg = ' _replace_src_with_mgmtnet_';
		if ($flavor == 'openstack') {
			$config['interfaces']['wan']['if'] = 'vtnet0';
		} elseif ($flavor == 'azure') {
			$replace_msg = '';
			$config['interfaces']['wan']['if'] = 'hn0';
			$config['system']['webgui']['ssl-certref'] = '51b5ec443d315';
		} else {
			$config['interfaces']['wan']['if'] = 'xn0';
		}

		// there is no LAN, remove its config and dhcpdv6 as well
		unset($config['interfaces']['lan']);
		unset($config['dhcpdv6']);

		$rules = array();

		$rule = array();
		$rule['type'] = 'pass';
		$rule['interface'] = 'wan';
		$rule['ipprotocol'] = 'inet';
		$rule['statetype'] = 'keep state';
		$rule['protocol'] = 'icmp';
		$rule['source']['any'] = true;
		$rule['destination']['network'] = 'wanip';
		$rule['descr'] = 'Default ICMP rule';
		$rule['tracker'] = (int)microtime(true);
		$rules[] = $rule;

		$rule = array();
		$rule['type'] = 'pass';
		$rule['interface'] = 'wan';
		$rule['ipprotocol'] = 'inet';
		$rule['statetype'] = 'keep state';
		$rule['protocol'] = 'tcp';
		$rule['source']['any'] = true;
		$rule['destination']['network'] = 'wanip';
		$rule['destination']['port'] = 22;
		$rule['descr'] = 'Default SSH rule' . $replace_msg;
		$rule['tracker'] = (int)microtime(true);
		$rules[] = $rule;

		$rule = array();
		$rule['type'] = 'pass';
		$rule['interface'] = 'wan';
		$rule['ipprotocol'] = 'inet';
		$rule['statetype'] = 'keep state';
		$rule['protocol'] = 'tcp';
		$rule['source']['any'] = true;
		$rule['destination']['network'] = 'wanip';
		$rule['destination']['port'] = 443;
		$rule['descr'] = 'Default HTTPS rule' . $replace_msg;
		$rule['tracker'] = (int)microtime(true);
		$rules[] = $rule;

		$rule = array();
		$rule['type'] = 'pass';
		$rule['interface'] = 'wan';
		$rule['ipprotocol'] = 'inet';
		$rule['statetype'] = 'keep state';
		$rule['protocol'] = 'tcp';
		$rule['source']['any'] = true;
		$rule['destination']['network'] = 'wanip';
		$rule['destination']['port'] = 80;
		$rule['descr'] = 'Default HTTP rule' . $replace_msg;
		$rule['tracker'] = (int)microtime(true);
		$rules[] = $rule;

		if ($flavor == 'ec2') {
			$rule = array();
			$rule['type'] = 'pass';
			$rule['interface'] = 'wan';
			$rule['ipprotocol'] = 'inet';
			$rule['statetype'] = 'keep state';
			$rule['protocol'] = 'udp';
			$rule['source']['any'] = true;
			$rule['destination']['network'] = 'wanip';
			$rule['destination']['port'] = 1194;
			$rule['descr'] = 'Default OpenVPN rule';
			$rule['tracker'] = (int)microtime(true);
			$rule['disabled'] = true;
			$rules[] = $rule;

			$rule = array();
			$rule['type'] = 'pass';
			$rule['interface'] = 'openvpn';
			$rule['ipprotocol'] = 'inet';
			$rule['statetype'] = 'keep state';
			$rule['source']['any'] = true;
			$rule['destination']['any'] = true;
			$rule['descr'] = 'Default Traffic from OpenVPN tunnel rule';
			$rule['tracker'] = (int)microtime(true);
			$rule['disabled'] = true;
			$rules[] = $rule;

			$rule = array();
			$rule['type'] = 'pass';
			$rule['interface'] = 'enc0';
			$rule['ipprotocol'] = 'inet46';
			$rule['source']['any'] = true;
			$rule['destination']['any'] = true;
			$rule['descr'] = 'Default allow IPsec to any rule';
			$rule['tracker'] = (int)microtime(true);
			$rules[] = $rule;
		}

		$rule = array();
		$rule['type'] = 'pass';
		$rule['interface'] = 'lan';
		$rule['protocol'] = 'inet';
		$rule['source']['network'] = 'lan';
		$rule['destination']['any'] = true;
		$rule['descr'] = 'Default allow LAN to any rule';
		$rule['tracker'] = '0100000101';
		$rules[] = $rule;

		$config['filter']['rule'] = $rules;
		unset($rule, $rules);

		$gw = array();
		$gw['interface'] = 'wan';
		$gw['gateway'] = 'dynamic';
		$gw['name'] = 'WAN_DHCP';
		$gw['weight'] = 1;
		$gw['ipprotocol'] = 'inet';
		$gw['interval'] = true;
		$gw['descr'] = 'Interface WAN_DHCP Gateway';
		$gw['monitor_disable'] = true;
		$gw['default_gw'] = true;
		$config['gateways']['gateway_item'] = array();
		$config['gateways']['gateway_item'][] = $gw;
		unset($gw);

		if ($flavor == 'ec2-csm' || $flavor == 'openstack') {
			/* Following items are for ec2 and azure */
			break;
		}

		$vip = array();
		$vip['mode'] = 'ipalias';
		$vip['interface'] = 'lo0';
		$vip['descr'] = 'Loopback interface for IPsec client DNS';
		$vip['type'] = 'single';
		$vip['subnet_bits'] = 24;
		$vip['subnet'] = '172.19.0.1';
		$config['virtualip']['vip'] = array();
		$config['virtualip']['vip'][] = $vip;
		unset($vip);

		if ($flavor != 'ec2') {
			/* following changes are ec2 only */
			break;
		}

		$client = array();
		$client['enable'] = true;
		$client['user_source'] = 'Local Database';
		$client['group_source'] = 'none';
		$client['pool_address'] = '172.25.53.0';
		$client['pool_netbits'] = 24;
		$client['save_passwd'] = true;
		$client['dns_server1'] = '172.19.0.1';
		$client['login_banner'] = 'Welcome to the Netgate IPsec VPN on AWS';
		$config['ipsec']['client'] = $client;
		unset($client);

		$p1 = array();
		$p1['ikeid'] = 1;
		$p1['interface'] = 'wan';
		$p1['mobile'] = true;
		$p1['disabled'] = true;
		$p1['mode'] = 'aggressive';
		$p1['protocol'] = 'inet';
		$p1['myid_type'] = 'myaddress';
		$p1['myid_data'] = true;
		$p1['peerid_type'] = 'user_fqdn';
		$p1['peerid_data'] = 'admin@pfsense';
		$p1['encryption-algorithm']['name'] = 'aes';
		$p1['encryption-algorithm']['keylen'] = 128;
		$p1['hash-algorithm'] = 'sha1';
		$p1['dhgroup'] = 2;
		$p1['lifetime'] = 86400;
		$p1['pre-shared-key'] = 'netgate-aws-ipsec';
		$p1['private-key'] = true;
		$p1['certref'] = true;
		$p1['caref'] = true;
		$p1['authentication_method'] = 'xauth_psk_server';
		$p1['descr'] = 'Mobile IPsec clients';
		$p1['nat_traversal'] = 'force';
		$p1['dpd_delay'] = 10;
		$p1['dpd_maxfail'] = 5;
		$p1['iketype'] = 'ikev1';
		$config['ipsec']['phase1'] = array();
		$config['ipsec']['phase1'][] = $p1;
		unset($p1);

		$p2 = array();
		$p2['ikeid'] = 1;
		$p2['mode'] = 'tunnel';
		$p2['localid']['type'] = 'network';
		$p2['localid']['address'] = '0.0.0.0';
		$p2['localid']['netbits'] = 0;
		$p2['remoteid']['type'] = 'mobile';
		$p2['protocol'] = 'esp';
		$ealgo_entry = array();
		$ealgo_entry['name'] = 'aes';
		$ealgo_entry['keylen'] = 128;
		$p2['encryption-algorithm-option'] = array();
		$p2['encryption-algorithm-option'][] = $ealgo_entry;
		unset($ealgo_entry);
		$p2['hash-algorithm-option'] = array();
		$p2['hash-algorithm-option'][] = 'hmac_sha1';
		$p2['pfsgroup'] = 0;
		$p2['lifetime'] = 28800;
		$p2['pinghost'] = true;
		$p2['mobile'] = true;
		$p2['uniqid'] = uniqid();
		$p2['reqid'] = 1;
		$p2['descr'] = 'Netgate Mobile IPsec';
		$config['ipsec']['phase2'] = array();
		$config['ipsec']['phase2'][] = $p2;
		unset($p2);

		$alias = array();
		$alias['name'] = 'Networks_to_NAT';
		$alias['address'] = '127.0.0.0/8 172.24.42.0/24 172.25.53.0/24';
		$alias['descr'] = 'Networks that may originate internet traffic';
		$alias['type'] = 'network';
		$alias['detail'] = 'Loopback||Default Remote Access VPN||Default Mobile IPsec VPN';
		$config['aliases']['alias'] = array();
		$config['aliases']['alias'][] = $alias;
		unset($alias);

		break;
	}

	// Remove the trigger
	unset($config['system']['trigger_virt_setup']);

	write_config(gettext("Default hardware detection on first boot"));
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
		case 'RCC-VE':
			$default_device['wan'] = "igb0";
			$default_device['lan'] = "igb1";
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
