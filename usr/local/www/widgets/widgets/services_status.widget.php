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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("/usr/local/www/widgets/include/services_status.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

function get_pkg_descr($package_name) {
	global $config;
	if (is_array($config['installedpackages']['package'])) {
		foreach($config['installedpackages']['package'] as $pkg) {
			if($pkg['name'] == $package_name)
				return $pkg['descr'];
		}
	}
	return gettext("Not available.");
}

if (is_array($config['installedpackages']['service']))
	$services = $config['installedpackages']['service'];
else
	$services = array();

/*    Add services that are in the base.
 *
 */
if(isset($config['dnsmasq']['enable'])) {
	$svcconfig = array();
	$svcconfig['name'] = "dnsmasq";
	$svcconfig['description'] = gettext("DNS Forwarder");
	$services[] = $svcconfig;
}

$svcconfig = array();
$svcconfig['name'] = "ntpd";
$svcconfig['description'] = gettext("NTP clock sync");
$services[] = $svcconfig;

if(isset($config['captiveportal']['enable'])) {
	$svcconfig = array();
	$svcconfig['name'] = "captiveportal";
	$svcconfig['description'] = gettext("Captive Portal");
	$services[] = $svcconfig;
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
	$svcconfig = array();
	$svcconfig['name'] = "dhcrelay";
	$svcconfig['description'] = gettext("DHCP Relay");
	$services[] = $svcconfig;
}

if(is_dhcp_server_enabled()) {
	$svcconfig = array();
	$svcconfig['name'] = "dhcpd";
	$svcconfig['description'] = gettext("DHCP Service");
	$services[] = $svcconfig;
}

if(isset($config['snmpd']['enable'])) {
	$svcconfig = array();
	$svcconfig['name'] = "bsnmpd";
	$svcconfig['description'] = gettext("SNMP Service");
	$services[] = $svcconfig;
}

if (count($config['igmpproxy']['igmpentry']) > 0) {
	$svcconfig = array();
	$svcconfig['name'] = "igmpproxy";
	$svcconfig['descritption'] = gettext("IGMP proxy");
	$services[] = $svcconfig;
}

if($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
	$svcconfig = array();
	$svcconfig['name'] = "miniupnpd";
	$svcconfig['description'] = gettext("UPnP Service");
	$services[] = $svcconfig;
}

if (isset($config['ipsec']['enable'])) {
	$svcconfig = array();
	$svcconfig['name'] = "racoon";
	$svcconfig['description'] = gettext("IPsec VPN");
	$services[] = $svcconfig;
}

foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$svcconfig = array();
				$svcconfig['name'] = "openvpn";
				$svcconfig['mode'] = $mode;
				$svcconfig['id'] = $id;
				$svcconfig['vpnid'] = $setting['vpnid'];
				$svcconfig['description'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
				$services[] = $svcconfig;
			}
		}
	}
}

if (count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
	$svcconfig = array();
	$svcconfig['name'] = "relayd";
	$svcconfig['description'] = gettext("Server load balancing daemon");
	$services[] = $svcconfig;
}

if(isset($_POST['servicestatusfilter'])) {
	$config['widgets']['servicestatusfilter'] = $_POST['servicestatusfilter'];
	write_config("Saved Service Status Filter via Dashboard");
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

function service_name_compare($a, $b) {
	if (strtolower($a['name']) == strtolower($b['name']))
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
}

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach($services as $service) {
		if((!$service['name']) || (in_array($service['name'], $skipservices)))
			continue;
		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr">' . $service['name'] . '</td>' . "\n";
		echo '<td class="listr">' . $service['description'] . '</td>' . "\n";
		if ($service['name'] == "openvpn")
			$running = is_pid_running("{$g['varrun_path']}/openvpn_{$service['mode']}{$service['vpnid']}.pid");
		else if ($service['name'] == "captiveportal")
			$running = is_pid_running("{$g['varrun_path']}/lighty-CaptivePortal.pid");
		else
			$running = is_service_running($service['name']);
		if($running) {
			echo '<td class="listr"><center>' . "\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_pass.gif\"> " . gettext("Running") . "</td>\n";
		} else {
			echo '<td class="listbg"><center>' . "\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_block.gif\"> <font color=\"white\">" . gettext("Stopped") . "</td>\n";
		}
		echo '<td valign="middle" class="list" nowrap>';
		if($running) {
			if ($service['name'] == "openvpn") {
				echo "<a href='status_services.php?mode=restartservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
			} else {
				echo "<a href='status_services.php?mode=restartservice&service={$service['name']}'>";
			}
			echo "<img title='" . gettext("Restart Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_restart.gif'></a>\n";
			if ($service['name'] == "openvpn") {
				echo "<a href='status_services.php?mode=stopservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
			} else {
				echo "<a href='status_services.php?mode=stopservice&service={$service['name']}'>";
			}
			echo "<img title='" . gettext("Stop Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_stop.gif'>";
			echo "</a>";
		} else {
			if ($service['name'] == "openvpn") {
				echo "<a href='status_services.php?mode=startservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
			} else {
				echo "<a href='status_services.php?mode=startservice&service={$service['name']}'> ";
			}

			echo "<img title='" . gettext("Start Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_start.gif'></a>\n";
		}
		echo "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"3\"><center>" . gettext("No services found") . ".</td></tr>\n";
}
?>
</table>

<!-- needed to display the widget settings menu -->
<script language="javascript" type="text/javascript">
	selectIntLink = "services_status-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
</script>
