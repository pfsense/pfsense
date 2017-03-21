<?php
/*
 * interfaces_assign.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Written by Jim McBeath based on existing m0n0wall files
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
##|*IDENT=page-interfaces-assignnetworkports
##|*NAME=Interfaces: Interface Assignments
##|*DESCR=Allow access to the 'Interfaces: Interface Assignments' page.
##|*MATCH=interfaces_assign.php*
##|-PRIV

$pgtitle = array(gettext("Interfaces"), gettext("Interface Assignments"));
$shortcut_section = "interfaces";

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("captiveportal.inc");
require_once("rrd.inc");

function interface_assign_description($portinfo, $portname) {
	global $ovpn_descrs;
	if ($portinfo['isvlan']) {
		$descr = sprintf(gettext('VLAN %1$s on %2$s'), $portinfo['tag'], $portinfo['if']);
		$iface = convert_real_interface_to_friendly_interface_name($portinfo['if']);
		if (isset($iface) && strlen($iface) > 0) {
			$descr .= " - $iface";
		}
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['iswlclone']) {
		$descr = $portinfo['cloneif'];
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['isppp']) {
		$descr = $portinfo['descr'];
	} elseif ($portinfo['isbridge']) {
		$descr = strtoupper($portinfo['bridgeif']);
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['isgre']) {
		$descr = "GRE {$portinfo['remote-addr']}";
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['isgif']) {
		$descr = "GIF {$portinfo['remote-addr']}";
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['islagg']) {
		$descr = strtoupper($portinfo['laggif']);
		if ($portinfo['descr']) {
			$descr .= " (" . $portinfo['descr'] . ")";
		}
	} elseif ($portinfo['isqinq']) {
		$descr = $portinfo['descr'];
	} elseif (substr($portname, 0, 4) == 'ovpn') {
		$descr = $portname . " (" . $ovpn_descrs[substr($portname, 5)] . ")";
	} else {
		$descr = $portname . " (" . $portinfo['mac'] . ")";
	}

	return htmlspecialchars($descr);
}

/*
	In this file, "port" refers to the physical port name,
	while "interface" refers to LAN, WAN, or OPTn.
*/

/* get list without VLAN interfaces */
$portlist = get_interface_list();

/* add wireless clone interfaces */
if (is_array($config['wireless']['clone']) && count($config['wireless']['clone'])) {
	foreach ($config['wireless']['clone'] as $clone) {
		$portlist[$clone['cloneif']] = $clone;
		$portlist[$clone['cloneif']]['iswlclone'] = true;
	}
}

/* add VLAN interfaces */
if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist[$vlan['vlanif']] = $vlan;
		$portlist[$vlan['vlanif']]['isvlan'] = true;
	}
}

/* add Bridge interfaces */
if (is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
	foreach ($config['bridges']['bridged'] as $bridge) {
		$portlist[$bridge['bridgeif']] = $bridge;
		$portlist[$bridge['bridgeif']]['isbridge'] = true;
	}
}

/* add GIF interfaces */
if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
	foreach ($config['gifs']['gif'] as $gif) {
		$portlist[$gif['gifif']] = $gif;
		$portlist[$gif['gifif']]['isgif'] = true;
	}
}

/* add GRE interfaces */
if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
	foreach ($config['gres']['gre'] as $gre) {
		$portlist[$gre['greif']] = $gre;
		$portlist[$gre['greif']]['isgre'] = true;
	}
}

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		$portlist[$lagg['laggif']] = $lagg;
		$portlist[$lagg['laggif']]['islagg'] = true;
		/* LAGG members cannot be assigned */
		$lagifs = explode(',', $lagg['members']);
		foreach ($lagifs as $lagif) {
			if (isset($portlist[$lagif])) {
				unset($portlist[$lagif]);
			}
		}
	}
}

/* add QinQ interfaces */
if (is_array($config['qinqs']['qinqentry']) && count($config['qinqs']['qinqentry'])) {
	foreach ($config['qinqs']['qinqentry'] as $qinq) {
		$portlist["{$qinq['vlanif']}"]['descr'] = "VLAN {$qinq['tag']} on {$qinq['if']}";
		$portlist["{$qinq['vlanif']}"]['isqinq'] = true;
		/* QinQ members */
		$qinqifs = explode(' ', $qinq['members']);
		foreach ($qinqifs as $qinqif) {
			$portlist["{$qinq['vlanif']}_{$qinqif}"]['descr'] = "QinQ {$qinqif} on VLAN {$qinq['tag']} on {$qinq['if']}";
			$portlist["{$qinq['vlanif']}_{$qinqif}"]['isqinq'] = true;
		}
	}
}

/* add PPP interfaces */
if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
	foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
		$portname = $ppp['if'];
		$portlist[$portname] = $ppp;
		$portlist[$portname]['isppp'] = true;
		$ports_base = basename($ppp['ports']);
		if (isset($ppp['descr'])) {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['descr']}";
		} else if (isset($ppp['username'])) {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['username']}";
		} else {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base})";
		}
	}
}

$ovpn_descrs = array();
if (is_array($config['openvpn'])) {
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $s) {
			$portname = "ovpns{$s['vpnid']}";
			$portlist[$portname] = $s;
			$ovpn_descrs[$s['vpnid']] = $s['description'];
		}
	}
	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $c) {
			$portname = "ovpnc{$c['vpnid']}";
			$portlist[$portname] = $c;
			$ovpn_descrs[$c['vpnid']] = $c['description'];
		}
	}
}

if (isset($_REQUEST['add']) && isset($_REQUEST['if_add'])) {
	/* Be sure this port is not being used */
	$portused = false;
	foreach ($config['interfaces'] as $ifname => $ifdata) {
		if ($ifdata['if'] == $_REQUEST['if_add']) {
			$portused = true;
			break;
		}
	}

	if ($portused === false) {
		/* find next free optional interface number */
		if (!$config['interfaces']['lan']) {
			$newifname = gettext("lan");
			$descr = gettext("LAN");
		} else {
			for ($i = 1; $i <= count($config['interfaces']); $i++) {
				if (!$config['interfaces']["opt{$i}"]) {
					break;
				}
			}
			$newifname = 'opt' . $i;
			$descr = "OPT" . $i;
		}

		$config['interfaces'][$newifname] = array();
		$config['interfaces'][$newifname]['descr'] = $descr;
		$config['interfaces'][$newifname]['if'] = $_POST['if_add'];
		if (preg_match($g['wireless_regex'], $_POST['if_add'])) {
			$config['interfaces'][$newifname]['wireless'] = array();
			interface_sync_wireless_clones($config['interfaces'][$newifname], false);
		}

		uksort($config['interfaces'], "compare_interface_friendly_names");

		/* XXX: Do not remove this. */
		unlink_if_exists("{$g['tmp_path']}/config.cache");

		write_config();

		$action_msg = gettext("Interface has been added.");
		$class = "success";
	}

} else if (isset($_POST['apply'])) {
	if (file_exists("/var/run/interface_mismatch_reboot_needed")) {
		system_reboot();
		$rebootingnow = true;
	} else {
		write_config();

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
	}

} else if (isset($_POST['Submit'])) {

	unset($input_errors);

	/* input validation */

	/* Build a list of the port names so we can see how the interfaces map */
	$portifmap = array();
	foreach ($portlist as $portname => $portinfo) {
		$portifmap[$portname] = array();
	}

	/* Go through the list of ports selected by the user,
	build a list of port-to-interface mappings in portifmap */
	foreach ($_POST as $ifname => $ifport) {
		if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt')) {
			$portifmap[$ifport][] = strtoupper($ifname);
		}
	}

	/* Deliver error message for any port with more than one assignment */
	foreach ($portifmap as $portname => $ifnames) {
		if (count($ifnames) > 1) {
			$errstr = sprintf(gettext('Port %1$s '.
				' was assigned to %2$s' .
				' interfaces:'), $portname, count($ifnames));

			foreach ($portifmap[$portname] as $ifn) {
				$errstr .= " " . convert_friendly_interface_to_friendly_descr(strtolower($ifn)) . " (" . $ifn . ")";
			}

			$input_errors[] = $errstr;
		} else if (count($ifnames) == 1 && preg_match('/^bridge[0-9]/', $portname) && is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $bridge) {
				if ($bridge['bridgeif'] != $portname) {
					continue;
				}

				$members = explode(",", strtoupper($bridge['members']));
				foreach ($members as $member) {
					if ($member == $ifnames[0]) {
						$input_errors[] = sprintf(gettext('Cannot set port %1$s to interface %2$s because this interface is a member of %3$s.'), $portname, $member, $portname);
						break;
					}
				}
			}
		}
	}

	if (is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (does_interface_exist($vlan['if']) == false) {
				$input_errors[] = sprintf(gettext('Vlan parent interface %1$s does not exist anymore so vlan id %2$s cannot be created please fix the issue before continuing.'), $vlan['if'], $vlan['tag']);
			}
		}
	}

	if (!$input_errors) {
		/* No errors detected, so update the config */
		foreach ($_POST as $ifname => $ifport) {

			if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt')) {

				if (!is_array($ifport)) {
					$reloadif = false;
					if (!empty($config['interfaces'][$ifname]['if']) && $config['interfaces'][$ifname]['if'] <> $ifport) {
						interface_bring_down($ifname);
						/* Mark this to be reconfigured in any case. */
						$reloadif = true;
					}
					$config['interfaces'][$ifname]['if'] = $ifport;
					if (isset($portlist[$ifport]['isppp'])) {
						$config['interfaces'][$ifname]['ipaddr'] = $portlist[$ifport]['type'];
					}

					if (substr($ifport, 0, 3) == 'gre' || substr($ifport, 0, 3) == 'gif') {
						unset($config['interfaces'][$ifname]['ipaddr']);
						unset($config['interfaces'][$ifname]['subnet']);
						unset($config['interfaces'][$ifname]['ipaddrv6']);
						unset($config['interfaces'][$ifname]['subnetv6']);
					}

					/* check for wireless interfaces, set or clear ['wireless'] */
					if (preg_match($g['wireless_regex'], $ifport)) {
						if (!is_array($config['interfaces'][$ifname]['wireless'])) {
							$config['interfaces'][$ifname]['wireless'] = array();
						}
					} else {
						unset($config['interfaces'][$ifname]['wireless']);
					}

					/* make sure there is a descr for all interfaces */
					if (!isset($config['interfaces'][$ifname]['descr'])) {
						$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
					}

					if ($reloadif == true) {
						if (preg_match($g['wireless_regex'], $ifport)) {
							interface_sync_wireless_clones($config['interfaces'][$ifname], false);
						}
						/* Reload all for the interface. */
						interface_configure($ifname, true);
					}
				}
			}
		}

		write_config();

		enable_rrd_graphing();
	}
} else {
	unset($delbtn);
	if (!empty($_POST['del'])) {
		$delbtn = key($_POST['del']);
	}

	if (isset($delbtn)) {
		$id = $delbtn;

		if (link_interface_to_group($id)) {
			$input_errors[] = gettext("The interface is part of a group. Please remove it from the group to continue");
		} else if (link_interface_to_bridge($id)) {
			$input_errors[] = gettext("The interface is part of a bridge. Please remove it from the bridge to continue");
		} else if (link_interface_to_gre($id)) {
			$input_errors[] = gettext("The interface is part of a gre tunnel. Please delete the tunnel to continue");
		} else if (link_interface_to_gif($id)) {
			$input_errors[] = gettext("The interface is part of a gif tunnel. Please delete the tunnel to continue");
		} else if (interface_has_queue($id)) {
			$input_errors[] = gettext("The interface has a traffic shaper queue configured.\nPlease remove all queues on the interface to continue.");
		} else {
			unset($config['interfaces'][$id]['enable']);
			$realid = get_real_interface($id);
			interface_bring_down($id);   /* down the interface */

			unset($config['interfaces'][$id]);	/* delete the specified OPTn or LAN*/

			if (is_array($config['dhcpd']) && is_array($config['dhcpd'][$id])) {
				unset($config['dhcpd'][$id]);
				services_dhcpd_configure('inet');
			}

			if (is_array($config['dhcpdv6']) && is_array($config['dhcpdv6'][$id])) {
				unset($config['dhcpdv6'][$id]);
				services_dhcpd_configure('inet6');
			}

			if (count($config['filter']['rule']) > 0) {
				foreach ($config['filter']['rule'] as $x => $rule) {
					if ($rule['interface'] == $id) {
						unset($config['filter']['rule'][$x]);
					}
				}
			}
			if (is_array($config['nat']['rule']) && count($config['nat']['rule']) > 0) {
				foreach ($config['nat']['rule'] as $x => $rule) {
					if ($rule['interface'] == $id) {
						unset($config['nat']['rule'][$x]['interface']);
					}
				}
			}

			write_config();

			/* If we are in firewall/routing mode (not single interface)
			 * then ensure that we are not running DHCP on the wan which
			 * will make a lot of ISP's unhappy.
			 */
			if ($config['interfaces']['lan'] && $config['dhcpd']['wan']) {
				unset($config['dhcpd']['wan']);
			}

			link_interface_to_vlans($realid, "update");

			$action_msg = gettext("Interface has been deleted.");
			$class = "success";
		}
	}
}

/* Create a list of unused ports */
$unused_portlist = array();
foreach ($portlist as $portname => $portinfo) {
	$portused = false;
	foreach ($config['interfaces'] as $ifname => $ifdata) {
		if ($ifdata['if'] == $portname) {
			$portused = true;
			break;
		}
	}
	if ($portused === false) {
		$unused_portlist[$portname] = $portinfo;
	}
}

include("head.inc");

if (file_exists("/var/run/interface_mismatch_reboot_needed")) {
	if ($_POST) {
		if ($rebootingnow) {
			$action_msg = gettext("The system is now rebooting. Please wait.");
			$class = "success";
		} else {
			$applymsg = gettext("Reboot is needed. Please apply the settings in order to reboot.");
			$class = "warning";
		}
	} else {
		$action_msg = gettext("Interface mismatch detected. Please resolve the mismatch, save and then click 'Apply Changes'. The firewall will reboot afterwards.");
		$class = "warning";
	}
}

if (file_exists("/tmp/reload_interfaces")) {
	echo "<p>\n";
	print_apply_box(gettext("The interface configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
	echo "<br /></p>\n";
} elseif ($applymsg) {
	print_apply_box($applymsg);
} elseif ($action_msg) {
	print_info_box($action_msg, $class);
} elseif ($changes_applied) {
	print_apply_result_box($retval);
}

pfSense_handle_custom_code("/usr/local/pkg/interfaces_assign/pre_input_errors");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), true, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);
?>
<form action="interfaces_assign.php" method="post">
	<div class="table-responsive">
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("Interface")?></th>
			<th><?=gettext("Network port")?></th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach ($config['interfaces'] as $ifname => $iface):
		if ($iface['descr']) {
			$ifdescr = $iface['descr'];
		} else {
			$ifdescr = strtoupper($ifname);
		}
?>
		<tr>
			<td><a href="/interfaces.php?if=<?=$ifname?>"><?=$ifdescr?></a></td>
			<td>
				<select name="<?=$ifname?>" id="<?=$ifname?>" class="form-control">
<?php foreach ($portlist as $portname => $portinfo):?>
					<option value="<?=$portname?>" <?=($portname == $iface['if']) ? ' selected': ''?>>
						<?=interface_assign_description($portinfo, $portname)?>
					</option>
<?php endforeach;?>
				</select>
			</td>
			<td>
<?php if ($ifname != 'wan'):?>
				<button type="submit" name="del[<?=$ifname?>]" class="btn btn-danger btn-sm" title="<?=gettext("Delete interface")?>">
					<i class="fa fa-trash icon-embed-btn"></i>
					<?=gettext("Delete")?>
				</button>
<?php endif;?>
			</td>
		</tr>
<?php endforeach;
	if (count($config['interfaces']) < count($portlist)):
?>
		<tr>
			<th>
				<?=gettext("Available network ports:")?>
			</th>
			<td>
				<select name="if_add" id="if_add" class="form-control">
<?php foreach ($unused_portlist as $portname => $portinfo):?>
					<option value="<?=$portname?>" <?=($portname == $iface['if']) ? ' selected': ''?>>
						<?=interface_assign_description($portinfo, $portname)?>
					</option>
<?php endforeach;?>
				</select>
			</td>
			<td>
				<button type="submit" name="add" title="<?=gettext("Add selected interface")?>" value="add interface" class="btn btn-success btn-sm" >
					<i class="fa fa-plus icon-embed-btn"></i>
					<?=gettext("Add")?>
				</button>
			</td>
		</tr>
<?php endif;?>
		</tbody>
	</table>
	</div>

	<button name="Submit" type="submit" class="btn btn-primary" value="<?=gettext('Save')?>"><i class="fa fa-save icon-embed-btn"></i><?=gettext('Save')?></button>
</form>
<br />

<?php
print_info_box(gettext("Interfaces that are configured as members of a lagg(4) interface will not be shown.") .
    '<br/><br/>' .
    gettext("Wireless interfaces must be created on the Wireless tab before they can be assigned."), 'info', false);
?>

<?php include("foot.inc")?>
