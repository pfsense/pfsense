<?php
/*
 * status_interfaces.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-status-interfaces
##|*NAME=Status: Interfaces
##|*DESCR=Allow access to the 'Status: Interfaces' page.
##|*MATCH=status_interfaces.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");
require_once("util.inc");

global $config;

if ($_POST['ifdescr'] && $_POST['submit']) {
	$interface = $_POST['ifdescr'];
	if ($_POST['status'] == "up") {
		if ($_POST['relinquish_lease']) {
			dhcp_relinquish_lease($_POST['if'], $_POST['ifdescr'], $_POST['ipv']);
		}
		interface_bring_down($interface);
	} else {
		interface_configure($interface);
	}
	header("Location: status_interfaces.php");
	exit;
}

$formtemplate = '<form name="%s" action="status_interfaces.php" method="post">' .
					'<input type="hidden" name="ifdescr" value="%s" />' .
					'<input type="hidden" name="status" value="%s" />' .
					'%s' .
					'<button type="submit" name="submit" class="btn btn-danger btn-xs" value="%s">' .
					'<i class="fa fa-refresh icon-embed-btn"></i>' .
					'%s' .
					'</button>' .
					'%s' .
					'</form>';

// Display a term/definition pair
function showDef($show, $term, $def) {
	// Choose an icon by interface status
	if ($term == "Status") {
		if ($def == "up" || $def == "associated") {
			$icon = 'arrow-up text-success';
		} elseif ($def == "no carrier") {
			$icon = 'times-circle text-danger';
		} elseif ($def == "down") {
			$icon = 'arrow-down text-danger';
		} else {
			$icon = '';
		}
	}
	if ($show) {
		print('<dt>' . $term . '</dt>');
		print('<dd>' . htmlspecialchars($def) . ' <i class="fa fa-' . $icon . '"></i></dd>');
	}
}

// Display a term/definition pair with a button
function showDefBtn($show, $term, $def, $ifdescr, $btnlbl, $chkbox_relinquish_lease) {
	global $formtemplate;

	if ($show) {
		print('<dt>' . $term . '</dt>');
		print('<dd>');
		printf($formtemplate, $term, $ifdescr, $show, htmlspecialchars($def)	. ' ', $btnlbl, $btnlbl, $chkbox_relinquish_lease);
		print('</dd>');
	}
}

// Relinquish the DHCP lease from the server.
function dhcp_relinquish_lease($if, $ifdescr, $ipv) {
	$leases_db = '/var/db/dhclient.leases.' . $if;
	$conf_file = '/var/etc/dhclient_'.$ifdescr.'.conf';
	$script_file = '/usr/local/sbin/pfSense-dhclient-script';
	$ipv = ((int) $ipv == 6) ? '-6' : '-4';

	if (file_exists($leases_db) && file_exists($script_file)) {
		mwexec('/usr/local/sbin/dhclient {$ipv} -d -r' .
			' -lf ' . escapeshellarg($leases_db) .
			' -cf ' . escapeshellarg($conf_file) .
			' -sf ' . escapeshellarg($script_file));
	}
}

$pgtitle = array(gettext("Status"), gettext("Interfaces"));
$shortcut_section = "interfaces";
include("head.inc");

$ifdescrs = get_configured_interface_with_descr(true);
$ifinterrupts = interfaces_interrupts();

foreach ($ifdescrs as $ifdescr => $ifname):
	$ifinfo = get_interface_info($ifdescr);
	$mac_man = load_mac_manufacturer_table();

	$chkbox_relinquish_lease = 	'&nbsp;&nbsp;&nbsp;' .
								'<input type="checkbox" name="relinquish_lease" value="true" title="' . gettext("Send a gratuitous DHCP release packet to the server.") . '" /> ' . gettext("Relinquish Lease") .
								'<input type="hidden" name="if" value='.$ifinfo['if'].' />';
	$chkbox_relinquish_lease_v4 = $chkbox_relinquish_lease . '<input type="hidden" name="ipv" value=4 />';
	$chkbox_relinquish_lease_v6 = $chkbox_relinquish_lease . '<input type="hidden" name="ipv" value=6 />';

	$ifhwinfo = $ifinfo['hwif'];
	$vlan = interface_is_vlan($ifinfo['hwif']);
	if ($vlan && is_array($config['switches']['switch'][0]['vlangroups']['vlangroup'])) {
		foreach ($config['switches']['switch'][0]['vlangroups']['vlangroup'] as $vlangroup) {
			if ($vlangroup['vlanid'] == $vlan['tag']) {
				$ifhwinfo .= ', switchports: ' . $vlangroup['members'];
				break;
			}
		}
	}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars($ifname)?><?=gettext(" Interface "); ?>(<?=htmlspecialchars($ifdescr)?>, <?=htmlspecialchars($ifhwinfo)?>)</h2></div>
	<div class="panel-body">
		<dl class="dl-horizontal">
<?php
		showDef(true, gettext("Status"), $ifinfo['enable'] ? $ifinfo['status'] : gettext('disabled'));
		showDefBtn($ifinfo['dhcplink'], 'DHCP', $ifinfo['dhcplink'], $ifdescr, (($ifinfo['dhcplink'] == "up") ? gettext("Release") : gettext("Renew")) . " {$ifname}", $ifinfo['dhcplink'] == "up" ? $chkbox_relinquish_lease_v4 : '');
		showDefBtn($ifinfo['dhcp6link'], 'DHCP6', $ifinfo['dhcp6link'], $ifdescr, (($ifinfo['dhcp6link'] == "up") ? gettext("Release") : gettext("Renew")) . " {$ifname}", $ifinfo['dhcp6link'] == "up" ? $chkbox_relinquish_lease_v6 : '');
		showDefBtn($ifinfo['pppoelink'], 'PPPoE', $ifinfo['pppoelink'], $ifdescr, (($ifinfo['pppoelink'] == "up") ? gettext("Disconnect") : gettext("Connect")) . " {$ifname}", '');
		showDefBtn($ifinfo['pptplink'], 'PPTP', $ifinfo['pptplink'], $ifdescr, (($ifinfo['pptplink'] == "up") ? gettext("Disconnect") : gettext("Connect")) . " {$ifname}", '');
		showDefBtn($ifinfo['l2tplink'], 'L2TP', $ifinfo['l2tplink'], $ifdescr, (($ifinfo['l2tplink'] == "up") ? gettext("Disconnect") : gettext("Connect")) . " {$ifname}", '');
		showDefBtn($ifinfo['ppplink'], 'PPP', $ifinfo['ppplink'], $ifdescr, (($ifinfo['ppplink'] == "up" && !$ifinfo['nodevice']) ? gettext("Disconnect") : gettext("Connect")) . " {$ifname}", '');
		showDef($ifinfo['ppp_uptime'] || $ifinfo['ppp_uptime_accumulated'], gettext("Uptime") . ' ' . ($ifinfo['ppp_uptime_accumulated'] ? gettext('(historical)'):''), $ifinfo['ppp_uptime'] . $ifinfo['ppp_uptime_accumulated']);
		showDef($ifinfo['cell_rssi'], gettext("Cell Signal (RSSI)"), $ifinfo['cell_rssi']);
		showDef($ifinfo['cell_mode'], gettext("Cell Mode"), $ifinfo['cell_mode']);
		showDef($ifinfo['cell_simstate'], gettext("Cell SIM State"), $ifinfo['cell_simstate']);
		showDef($ifinfo['cell_service'], gettext("Cell Service"), $ifinfo['cell_service']);
		showDef($ifinfo['cell_bwupstream'], gettext("Cell Upstream"), $ifinfo['cell_bwupstream']);
		showDef($ifinfo['cell_bwdownstream'], gettext("Cell Downstream"), $ifinfo['cell_bwdownstream']);
		showDef($ifinfo['cell_upstream'], gettext("Cell Current Up"), $ifinfo['cell_upstream']);
		showDef($ifinfo['cell_downstream'], gettext("Cell Current Down"), $ifinfo['cell_downstream']);

		if ($ifinfo['macaddr']) {
			$mac=$ifinfo['macaddr'];
			$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
			showDef( $ifinfo['macaddr'], gettext('MAC Address'), $mac . (isset($mac_man[$mac_hi]) ? ' - ' . $mac_man[$mac_hi] : ''));
		}

		if ($ifinfo['status'] != "down") {
			if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down") {
				showDef($ifinfo['ipaddr'], gettext('IPv4 Address'), $ifinfo['ipaddr']);
				showDef($ifinfo['subnet'], gettext('Subnet mask IPv4'), $ifinfo['subnet']);
				showDef($ifinfo['gateway'], gettext('Gateway IPv4'), $ifinfo['gateway']);
				showDef($ifinfo['linklocal'], gettext('IPv6 Link Local'), $ifinfo['linklocal']);
				showDef($ifinfo['ipaddrv6'], gettext('IPv6 Address'), $ifinfo['ipaddrv6']);
				showDef($ifinfo['subnetv6'], gettext('Subnet mask IPv6'), $ifinfo['subnetv6']);
				showDef($ifinfo['gatewayv6'], gettext("Gateway IPv6"), $config['interfaces'][$ifdescr]['gatewayv6'] . " " . $ifinfo['gatewayv6']);

				$dns_servers = get_dynamic_nameservers($ifdescr);
				$dnscnt = 0;
				foreach ($dns_servers as $dns) {
					showDef(true, $dnscnt == 0 ? gettext('DNS servers'):'', $dns);
					$dnscnt++;
				}
			}

			if ($ifinfo['laggport']) {
				$laggport = get_lagg_ports($ifinfo['laggport']);
			}

			showDef($ifinfo['mtu'], gettext("MTU"), $ifinfo['mtu']);
			showDef($ifinfo['media'], gettext("Media"), $ifinfo['media']);
			showDef($ifinfo['laggproto'], gettext("LAGG Protocol"), $ifinfo['laggproto']);
			showDef($ifinfo['laggport'], gettext("LAGG Ports"), $laggport);
			showDef($ifinfo['channel'], gettext("Channel"), $ifinfo['channel']);
			showDef($ifinfo['ssid'], gettext("SSID"), $ifinfo['ssid']);
			showDef($ifinfo['bssid'], gettext("BSSID"), $ifinfo['bssid']);
			showDef($ifinfo['rate'], gettext("Rate"), $ifinfo['rate']);
			showDef($ifinfo['rssi'], gettext("RSSI"), $ifinfo['rssi']);
			showDef(true, gettext("In/out packets"),
			    $ifinfo['inpkts'] . '/' . $ifinfo['outpkts'] . " (" . format_bytes($ifinfo['inbytes']) . "/" . format_bytes($ifinfo['outbytes']) . ")");
			showDef(true, gettext("In/out packets (pass)"),
			    $ifinfo['inpktspass'] . '/' . $ifinfo['outpktspass'] . " (" . format_bytes($ifinfo['inbytespass']) . "/" . format_bytes($ifinfo['outbytespass']) . ")");
			showDef(true, gettext("In/out packets (block)"),
			    $ifinfo['inpktsblock'] . '/' . $ifinfo['outpktsblock'] . " (" . format_bytes($ifinfo['inbytesblock']) . "/" . format_bytes($ifinfo['outbytesblock']) . ")");
			showDef(isset($ifinfo['inerrs']), gettext("In/out errors"), $ifinfo['inerrs'] . "/" . $ifinfo['outerrs']);
			showDef(isset($ifinfo['collisions']), gettext("Collisions"), $ifinfo['collisions']);
		} // e-o-if ($ifinfo['status'] != "down")

		showDef($ifinfo['bridge'], sprintf(gettext('Bridge (%1$s)'), $ifinfo['bridgeint']), $ifinfo['bridge']);

		if (is_array($ifinterrupts[$ifinfo['hwif']])) {
			$interrupt_total = $ifinterrupts[$ifinfo['hwif']]['total'];
			$interrupt_sec = $ifinterrupts[$ifinfo['hwif']]['rate'];
			showDef($interrupt_total, gettext('Interrupts'), $interrupt_total . " (" . $interrupt_sec . "/s)");
		}
?>
		</dl>
	</div>
</div>

<?php
	endforeach;

print_info_box(sprintf(gettext('Using dial-on-demand will bring the connection up again if any packet ' .
	    'triggers it. To substantiate this point: disconnecting manually ' .
	    'will %1$snot%2$s prevent dial-on-demand from making connections ' .
	    'to the outside! Don\'t use dial-on-demand if the line ' .
	    'is to be kept disconnected.'), '<strong>', '</strong>'), 'warning', false);
include("foot.inc");
?>
