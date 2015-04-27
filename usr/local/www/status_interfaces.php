<?php
/* $Id$ */
/*
	status_interfaces.php
	part of pfSense
	Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>.
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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
/*
	pfSense_MODULE: interfaces
*/

##|+PRIV
##|*IDENT=page-status-interfaces
##|*NAME=Status: Interfaces page
##|*DESCR=Allow access to the 'Status: Interfaces' page.
##|*MATCH=status_interfaces.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("shaper.inc");
require_once("filter.inc");

if ($_POST['if'] && $_POST['submit']) {
	$interface = $_POST['if'];
	if ($_POST['status'] == "up")
		interface_bring_down($interface);
	else
		interface_configure($interface);
	header("Location: status_interfaces.php");
	exit;
}

$formtemplate = '<form name="%s" action="status_interfaces.php" method="post">' .
					'<input type="hidden" name="if" value="%s" />' .
					'<input type="hidden" name="status" value="%s" />' .
					'%s' .
					'<input type="submit" name="submit" class="btn btn-warning btn-xs" value="%s" />' .
					'</form>';

// Display a term/definition pair
function showDef($show, $term, $def) {
	if($show) {
		print('<dt>' . $term . '</dt>');
		print('<dd>' . $def . '</dd>');
	}
}

// Display a term/definition pair with a button
function showDefBtn($show, $term, $def, $ifval, $btnlbl) {
	global $formtemplate;

	if($show) {
		print('<dt>' . $term . '</dt>');
		print('<dd>');
		printf($formtemplate, $term, $ifvalue, $show, $def	. ' ', $btnlbl);
		print('</dd>');
	}
}

$pgtitle = array(gettext("Status"),gettext("Interfaces"));
$shortcut_section = "interfaces";
include("head.inc");

		$ifdescrs = get_configured_interface_with_descr(false, true);

		foreach ($ifdescrs as $ifdescr => $ifname):
			$ifinfo = get_interface_info($ifdescr);
			$mac_man = load_mac_manufacturer_table();
?>

<div class="panel panel-default">
	<div class="panel-heading"><?=htmlspecialchars($ifname)?><?=gettext(" interface "); ?>(<?=htmlspecialchars($ifdescr)?>, <?=htmlspecialchars($ifinfo['hwif'])?>)</div>
		<div class="panel-body">
			<dl class="dl-horizontal">
<?php
			showDef(true, gettext("Status"), $ifinfo['status']);
			showDefBtn($ifinfo['dhcplink'], 'DHCP', htmlspecialchars($ifinfo['dhcplink']), $ifdescr, $ifinfo['dhcplink'] == "up" ? gettext("Release") : gettext("Renew"));
			showDefBtn($ifinfo['dhcp6link'], 'DHCP6', htmlspecialchars($ifinfo['dhcp6link']), $ifdescr, $ifinfo['dhcp6link'] == "up" ? gettext("Release") : gettext("Renew"));
			showDefBtn($ifinfo['pppoelink'], 'PPPoE', htmlspecialchars($ifinfo['pppoelink']), $ifdescr, $ifinfo['pppoelink'] == "up" ? gettext("Disconnect") : gettext("Connect"));
			showDefBtn($ifinfo['pptplink'], 'PPTP', htmlspecialchars($ifinfo['pptplink']), $ifdescr, $ifinfo['pptplink'] == "up" ? gettext("Disconnect") : gettext("Connect"));
			showDefBtn($ifinfo['l2tplink'], 'L2TP', htmlspecialchars($ifinfo['l2tplink']), $ifdescr, $ifinfo['l2tplink'] == "up" ? gettext("Disconnect") : gettext("Connect"));
			showDefBtn($ifinfo['ppplink'], 'L2TP', htmlspecialchars($ifinfo['ppplink']), $ifdescr, $ifinfo['ppplink'] == "up" && !$ifinfo['nodevice'] ? gettext("Disconnect") : gettext("Connect"));
			showDef($ifinfo['ppp_uptime'] || $ifinfo['ppp_uptime_accumulated'], gettext("Uptime ") . $ifinfo['ppp_uptime_accumulated'] ? '(historical)':'', htmlspecialchars($ifinfo['ppp_uptime']) . htmlspecialchars($ifinfo['ppp_uptime_accumulated']));
			showDef($ifinfo['cell_rssi'], gettext("Cell Signal (RSSI)"), htmlspecialchars($ifinfo['cell_rssi']));
			showDef($ifinfo['cell_mode'], gettext("Cell Mode"), htmlspecialchars($ifinfo['cell_mode']));
			showDef($ifinfo['cell_simstate'], gettext("Cell SIM State"), htmlspecialchars($ifinfo['cell_simstate']));
			showDef($ifinfo['cell_service'], gettext("Cell Service"), htmlspecialchars($ifinfo['cell_service']));
			showDef($ifinfo['cell_bwupstream'], gettext("Cell Upstream"), htmlspecialchars($ifinfo['cell_bwupstream']));
			showDef($ifinfo['cell_bwdownstream'], gettext("Cell Downstream"), htmlspecialchars($ifinfo['cell_bwdownstream']));
			showDef($ifinfo['cell_upstream'], gettext("Cell Current Up"), htmlspecialchars($ifinfo['cell_upstream']));
			showDef($ifinfo['cell_downstream'], gettext("Cell Current Down"), htmlspecialchars($ifinfo['cell_downstream']));

			if ($ifinfo['macaddr']) {
				$mac=$ifinfo['macaddr'];
				$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
				showDef(isset($mac_man[$mac_hi]), gettext('MAC Address'),  ' - ' . htmlspecialchars($mac_man[$mac_hi]));
			}

			if ($ifinfo['status'] != "down"):
				if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down"):
					showDef($ifinfo['ipaddr'], gettext('IPv4 Address'), htmlspecialchars($ifinfo['ipaddr']));
					showDef($ifinfo['subnet'], gettext('Subnet mask IPv4'), $ifinfo['subnet']);
					showDef($ifinfo['gateway'], gettext('Gateway IPv4'), $ifinfo['gateway']);
					showDef($ifinfo['linklocal'], gettext('IPv6 Link Local'), htmlspecialchars($ifinfo['linklocal']));
					showDef($ifinfo['ipaddrv6'], gettext('IPv6 Address'), htmlspecialchars($ifinfo['ipaddrv6']));
					showDef($ifinfo['subnetv6'], gettext('Subnet mask IPv6'), htmlspecialchars($ifinfo['subnetv6']));
					showDef($ifinfo['gatewayv6'], gettext("Gateway IPv6"), htmlspecialchars($config['interfaces'][$ifdescr]['gatewayv6']) . htmlspecialchars($ifinfo['gatewayv6']));
					if ($ifdescr == "wan" && file_exists("{$g['varetc_path']}/resolv.conf")) {
						$dns_servers = get_dns_servers();
						$dnscnt = 0;
						foreach($dns_servers as $dns) {
							showDef(true, $dnscnt == 0 ? gettext('ISP DNS servers'):'', $dns);
							$dnscnt++;
						}
					}
					endif;

				showDef($ifinfo['mtu'], gettext("MTU"), htmlspecialchars($ifinfo['mtu']));
				showDef($ifinfo['media'], gettext("Media"), htmlspecialchars($ifinfo['media']));
				showDef($ifinfo['laggproto'], gettext("LAGG Protocol"), htmlspecialchars($ifinfo['laggproto']));
				showDef($ifinfo['laggport'],gettext("LAGG Ports"),htmlspecialchars($laggport));
				showDef($ifinfo['channel'],gettext("Channel"),htmlspecialchars($ifinfo['channel']));
				showDef($ifinfo['ssid'],gettext("SSID"),htmlspecialchars($ifinfo['ssid']));
				showDef($ifinfo['bssid'],gettext("BSSID"),htmlspecialchars($ifinfo['bssid']));
				showDef($ifinfo['rate'],gettext("Rate"),htmlspecialchars($ifinfo['rate']));
				showDef($ifinfo['rssi'],gettext("RSSI"),htmlspecialchars($ifinfo['rssi']));
				showDef(true,gettext("In/out packets"),htmlspecialchars($ifinfo['inpkts'] . '/' . $ifinfo['outpkts']));
				showDef(true,gettext("In/out packets (pass)"),htmlspecialchars($ifinfo['inpktspass'] . "/" . $ifinfo['outpktspass']));
				showDef(true,gettext("In/out packets (block)"),htmlspecialchars($ifinfo['inpktsblock'] . "/" . $ifinfo['outpktsblock']));
				showDef(isset($ifinfo['inerrs']),gettext("In/out errors"),htmlspecialchars($ifinfo['inerrs'] . "/" . $ifinfo['outerrs']));
				showDef(isset($ifinfo['collisions']),gettext("Collisions"),htmlspecialchars($ifinfo['collisions']));
			endif;

			showDef($ifinfo['bridge'], gettext('Bridge (') . $ifinfo['bridgeint'] . ')', $ifinfo['bridge']);

			if(file_exists("/usr/bin/vmstat")) {
				$real_interface = "";
				$interrupt_total = "";
				$interrupt_sec = "";
				$real_interface = $ifinfo['hwif'];
				$interrupt_total = `vmstat -i | grep $real_interface | awk '{ print $3 }'`;
				$interrupt_sec = `vmstat -i | grep $real_interface | awk '{ print $4 }'`;

				if(strstr($interrupt_total, "hci")) {
					$interrupt_total = `vmstat -i | grep $real_interface | awk '{ print $4 }'`;
					$interrupt_sec = `vmstat -i | grep $real_interface | awk '{ print $5 }'`;
				}

				unset($interrupt_total);

				showDef($interrupt_total, gettext('Total interrupts'), $interrupt_total);
				showDef($interrupt_total, '', $interrupt_sec . " " . $interrupt_total);
			}
			break;
		endforeach;
?>
			</dl>
		</div>
	</div>
	<div class="alert alert-warning" role="alert">
		<?=gettext("Using dial-on-demand will bring the connection up again if any packet ".
		"triggers it. To substantiate this point: disconnecting manually ".
		"will <strong>not</strong> prevent dial-on-demand from making connections ".
		"to the outside! Don't use dial-on-demand if you want to make sure that the line ".
		"is kept disconnected.");?>
	</div>
</div>

<?php include("foot.inc"); ?>

