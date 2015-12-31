<?php
/****h* pfSense/config
 * NAME
 *   config.inc - Functions to manipulate config.xml
 * DESCRIPTION
 *   This include contains various config.xml specific functions.
 * HISTORY
 * $Id$
 ******

	config.console.inc
	Copyright (C) 2004-2010 Scott Ullrich
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

	/* Only present auto interface option if running from LiveCD and interface mismatch*/
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
			$ifsmallist = trim($ifsmallist . " " . $iface);
			echo sprintf("% -7s%s %s %s\n", $iface, $ifa['mac'],
				$ifa['up'] ? "  (up)" : "(down)", substr($ifa['dmesg'], 0, 48));
		}
	}

	if ($auto_assign) {
		echo <<<EOD

		!!! LiveCD Detected: Auto Interface Option !!!!
BEGIN MANUAL CONFIGURATION OR WE WILL PROCEED WITH AUTO CONFIGURATION.

EOD;
	}

	echo "\n" . gettext("Do you want to set up VLANs first?");
	echo "\n" .
		gettext(
			"If you are not going to use VLANs, or only for optional interfaces, you should\n" .
			"say no here and use the webConfigurator to configure VLANs later, if required.") .
		"\n";
	echo "\n" . gettext("Do you want to set up VLANs now [y|n]?") . " ";

	if ($auto_assign) {
		$key = timeout();
	} else {
		$key = chop(fgets($fp));
	}

	if (!isset($key) and $auto_assign) {	// Auto Assign Interfaces
		do {
			echo <<<EOD

   !!! Auto Assigning Interfaces !!!

For installation purposes, you must plug in at least one NIC
for the LAN connection. If you plug in a second NIC it will be
assigned to WAN. Otherwise, we'll temporarily assign WAN to the
next available NIC found regardless of activity. You should
assign and configure the WAN interface according to your requirements

If you haven't plugged in any network cables yet,
now is the time to do so.
We'll keep trying until you do.

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

If you don't like this assignment,
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

If you do not know the names of your interfaces, you may choose to use
auto-detection. In that case, disconnect all interfaces now before
hitting 'a' to initiate auto detection.

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

Error: you cannot assign the same interface name twice!

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
		write_config("Console assignment of interfaces");
		printf(gettext("done.%s"), "\n");

		fclose($fp);

		if (platform_booting()) {
			return;
		}

		echo gettext("One moment while we reload the settings...");
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
