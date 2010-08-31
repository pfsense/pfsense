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
require_once("service-utils.inc");
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
	$sconfig['name'] = "dnsmasq";
	$sconfig['description'] = "DNS Forwarder";
	$services[] = $sconfig;
	unset($sconfig);
}

$sconfig['name'] = "ntpd";
$sconfig['description'] = "NTP clock sync";
$services[] = $sconfig;
unset($sconfig);

if(isset($config['captiveportal']['enable'])) {
	$sconfig['name'] = "lighttpd";
	$sconfig['description'] = "Captive Portal";
	$services[] = $sconfig;
	$sconfig = "";
	unset($sconfig);
}

$iflist = array();
$ifdescrs = get_configured_interface_list();
foreach ($ifdescrs as $if) {
	$oc = $config['interfaces'][$if];
	if ($oc['if'] && (!link_interface_to_bridge($if)))
		$iflist[$if] = $if;
}
$show_dhcprelay = false;
foreach($iflist as $if) {
	if(isset($config['dhcrelay'][$if]['enable']))
		$show_dhcprelay = true;
}

if($show_dhcprelay == true) {
	$sconfig['name'] = "dhcrelay";
	$sconfig['description'] = "DHCP Relay";
	$services[] = $sconfig;
	unset($sconfig);
}

if(is_dhcp_server_enabled()) {
	$sconfig['name'] = "dhcpd";
	$sconfig['description'] = "DHCP Service";
	$services[] = $sconfig;
	unset($sconfig);
}

if(isset($config['snmpd']['enable'])) {
	$sconfig['name'] = "bsnmpd";
	$sconfig['description'] = "SNMP Service";
	$services[] = $sconfig;
	unset($sconfig);
}

if (count($config['igmpproxy']['igmpentry']) > 0) {
	$sconfig['name'] = "igmpproxy";
	$sconfig['description'] = "IGMP proxy";
	$services[] = $sconfig;
	unset($sconfig);
}

if($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
	$sconfig['name'] = "miniupnpd";
	$sconfig['description'] = gettext("UPnP Service");
	$services[] = $sconfig;
	unset($sconfig);
}

if (isset($config['ipsec']['enable'])) {
	$sconfig['name'] = "racoon";
	$sconfig['description'] = gettext("IPsec VPN");
	$services[] = $sconfig;
	unset($sconfig);
}

foreach (array('server', 'client') as $mode) {
	if (is_array($config['installedpackages']["openvpn$mode"]['config'])) {
		foreach ($config['installedpackages']["openvpn$mode"]['config'] as $id => $settings) {
			$setting = $config['installedpackages']["openvpn$mode"]['config'][$id];
			if (!$setting['disable']) {
				$sconfig['name'] = "openvpn";
				$sconfig['mode'] = $mode;
				$sconfig['id'] = $id;
				$sconfig['description'] = "OpenVPN ".$mode.": ".htmlspecialchars($setting['description']);
				$services[] = $sconfig;
				unset($sconfig);
			}
		}
	}
}

if(isset($_POST['servicestatusfilter'])) {
	$config['widgets']['servicestatusfilter'] = $_POST['servicestatusfilter'];
	write_config("Saved Service Status Filter via Dashboard");
	Header("Location: /");
}
?>
<input type="hidden" id="services_status-config" name="services_status-config" value="">
<div id="services_status-settings" name="services_status-settings" class="widgetconfigdiv" style="display:none;">
	<form action="/widgets/widgets/services_status.widget.php" method="post" name="iforma">
		Comma separated list of services to NOT display in the widget<br />
		<input type="text" length="30" name="servicestatusfilter" class="formfld unknown" id="servicestatusfilter" value="<?= $config['widgets']['servicestatusfilter'] ?>">
		<input id="submita" name="submita" type="submit" class="formbtn" value="Save" />
    </form>
</div>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
	  <td class="widgetsubheader"><b><center>Service</center></b></td>
	  <td class="widgetsubheader"><b><center>Description</center></b></td>
		<td class="widgetsubheader"><b><center>Status</center></b></td>
		<td class="widgetsubheader">&nbsp;</td>
	</tr>
<?php
$skipservices = explode(",", str_replace(" ", "", $config['widgets']['servicestatusfilter']));

if($services) {
	foreach($services as $service) {
		if((!$service['name']) || (in_array($service['name'], $skipservices))) continue;
		if(!$service['description']) $service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr">' . $service['name'] . "</td>\n";
		echo '<td class="listr">' . substr($service['description'],0 ,20) . "</td>\n";
		if(is_service_running($service['name'])) {
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

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "services_status-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
