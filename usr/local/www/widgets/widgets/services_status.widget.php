<?php
/*
    services_status.php
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

    services_status.widget.php
    Copyright (C) 2007 Sam Wenham

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

function get_pkg_descr($package_name) {
	global $config;
	foreach($config['installedpackages']['package'] as $pkg) {
		if($pkg['name'] == $package_name)
			return $pkg['descr'];
	}
	return "Not available.";
}

exec("/bin/ps ax | awk '{ print $5 }'", $psout);
array_shift($psout);
foreach($psout as $line) {
	$ps[] = trim(array_pop(explode(' ', array_pop(explode('/', $line)))));
}

$services = $config['installedpackages']['service'];

/*    Add services that are in the base.
 *
 */
if(isset($config['dnsmasq']['enable'])) {
	$pconfig2['name'] = "dnsmasq";
	$pconfig2['description'] = "DNS Forwarder";
	$services[] = $pconfig2;
	unset($pconfig2);
}

if(isset($config['captiveportal']['enable'])) {
	$pconfig2['name'] = "lighttpd";
	$pconfig2['description'] = "Captive Portal";
	$services[] = $pconfig2;
	$pconfig2 = "";
	unset($pconfig2);
}

$iflist = array("lan" => "LAN");
for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
	$oc = $config['interfaces']['opt' . $i];
	if (isset($oc['enable']) && $oc['if'] && (!$oc['bridge']))
		$iflist['opt' . $i] = "opt{$i}";
}
$show_dhcprelay = false;
foreach($iflist as $if) {
	if(isset($config['dhcrelay'][$if]['enable']))
		$show_dhcprelay = true;
}

if($show_dhcprelay == true) {
	$pconfig2['name'] = "dhcrelay";
	$pconfig2['description'] = "DHCP Relay";
	$services[] = $pconfig2;
	unset($pconfig2);
}

if(is_dhcp_server_enabled()) {
	$pconfig2['name'] = "dhcpd";
	$pconfig2['description'] = "DHCP Service";
	$services[] = $pconfig2;
	unset($pconfig2);
}

if(isset($config['snmpd']['enable'])) {
	$pconfig2['name'] = "bsnmpd";
	$pconfig2['description'] = "SNMP Service";
	$services[] = $pconfig2;
	unset($pconfig2);
}

if(isset($config['proxyarp']['proxyarpnet'])) {
	$pconfig2['name'] = "choparp";
	$pconfig2['description'] = "Proxy Arp";
	$services[] = $pconfig2;
	unset($pconfig2);
}

if($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
    $pconfig2['name'] = "miniupnpd";
    $pconfig2['description'] = gettext("UPnP Service");
    $services[] = $pconfig2;
    unset($pconfig2);
}

if (isset($config['ipsec']['enable'])) {
    $pconfig2['name'] = "racoon";
    $pconfig2['description'] = gettext("IPsec VPN");
    $services[] = $pconfig2;
    unset($pconfig2);
}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td class="widgetsubheader"><b><center>Service</center></b></td>
	  <td class="widgetsubheader"><b><center>Description</center></b></td>
		<td class="widgetsubheader"><b><center>Status</center></b></td>
		<td class="widgetsubheader">&nbsp;</td>
	</tr>
<?php
if($services) {
	foreach($services as $service) {
		if(!$service['name']) continue;
		if(!$service['description']) $service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr">' . $service['name'] . "</td>\n";
		echo '<td class="listr">' . substr($service['description'],0 ,20) . "</td>\n";
		if(is_service_running($service['name'], $ps) or is_process_running($service['name']) ) {
			echo '<td class="listr"><center>';
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_pass.gif\"> Running</td>\n";
			$running = true;
		} else {
			echo '<td class="listbg"><center>';
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_block.gif\"> <font color=\"white\">Stopped</td>\n";
			$running = false;
		}
		echo '<td valign="middle" class="listr" nowrap><center>';
		if($running) {
			echo "<a href='status_services.php?mode=restartservice&service={$service['name']}'>";
			echo "<img title='Restart Service' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_restart.gif'></a> ";
			echo "<a href='status_services.php?mode=stopservice&service={$service['name']}'>";
			echo "<img title='Stop Service' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_stop.gif'> ";
			echo "</a>";
		} else {
			echo "<a href='status_services.php?mode=startservice&service={$service['name']}'> ";
			echo "<img title='Start Service' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_start.gif'></a> ";
		}
		echo "</center></td>\n";
		echo "</tr>\n";
	}
} else {
	echo "<tr><td colspan=\"4\"><center>No services found.</td></tr>\n";
}
?>
</table>