<?php
/*
    services_status.php
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

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
/*	
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/openvpn	/usr/bin/killall	/bin/ps
	pfSense_MODULE:	services
*/

##|+PRIV
##|*IDENT=page-status-services
##|*NAME=Status: Services page
##|*DESCR=Allow access to the 'Status: Services' page.
##|*MATCH=status_services.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("captiveportal.inc");
require_once("service-utils.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("vslb.inc");

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

if($_GET['mode'] == "restartservice" and !empty($_GET['service'])) {
	switch($_GET['service']) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = $_GET['zone'];
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal-SSL.pid");
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
		case 'openntpd':
			system_ntp_configure();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('restart');	
			break;
		case 'racoon':
			vpn_ipsec_force_reload();
			break;
		case 'openvpn':         
			$vpnmode = $_GET['vpnmode'];
			if ($vpnmode == "server" || $vpnmode == "client") {
				$id = $_GET['id'];
				$configfile = "{$g['varetc_path']}/openvpn/{$vpnmode}{$id}.conf";
				$pidfile = $g['varrun_path'] . "/openvpn_{$vpnmode}{$id}.pid";
				if (file_exists($configfile)) {
					killbypid($pidfile);
					sleep(1);
					mwexec_bg("/usr/local/sbin/openvpn --config {$configfile}");
				}
			}
			break;
		case 'relayd':
			relayd_configure(true);
			break;
		default:
			restart_service($_GET['service']);
			break;
	}
	$savemsg = sprintf(gettext("%s has been restarted."),htmlspecialchars($_GET['service']));
	sleep(5);
}

if($_GET['mode'] == "startservice" and !empty($_GET['service'])) {
	switch($_GET['service']) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			$zone = $_GET['zone'];
			captiveportal_init_webgui_zonename($zone);
			break;
		case 'ntpd':
		case 'openntpd':
			system_ntp_configure();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('start');
			break;
		case 'racoon':
			vpn_ipsec_force_reload();
			break;
		case 'openvpn':
			$vpnmode = $_GET['vpnmode'];
			if (($vpnmode == "server") || ($vpnmode == "client")) {
				$id = $_GET['id'];
				$configfile = "{$g['varetc_path']}/openvpn/{$vpnmode}{$id}.conf";
				if (file_exists($configfile))
					mwexec_bg("/usr/local/sbin/openvpn --config {$configfile}");
			}
			break;
		case 'relayd':
			relayd_configure();
			break;
		default:
			start_service($_GET['service']);
			break;
	}
	$savemsg = sprintf(gettext("%s has been started."),htmlspecialchars($_GET['service']));
	sleep(5);
}

/* stop service */
if($_GET['mode'] == "stopservice" && !empty($_GET['service'])) {
	switch($_GET['service']) {
		case 'radvd':
			killbypid("{$g['varrun_path']}/radvd.pid");
			break;
		case 'captiveportal':
			$zone = $_GET['zone'];
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal.pid");
			killbypid("{$g['varrun_path']}/lighty-{$zone}-CaptivePortal-SSL.pid");
			break;
		case 'ntpd':
			killbyname("ntpd");
			break;		
		case 'openntpd':
			killbyname("openntpd");
			break;
		case 'bsnmpd':
			killbypid("{$g['varrun_path']}/snmpd.pid");
			break;
		case 'choparp':
			killbyname("choparp");
			break;
		case 'dhcpd':
			killbyname("dhcpd");
			break;
		case 'dhcrelay':
			killbypid("{$g['varrun_path']}/dhcrelay.pid");
			break;
		case 'dnsmasq':
			killbypid("{$g['varrun_path']}/dnsmasq.pid");
			break;
		case 'igmpproxy':
			killbyname("igmpproxy");
			break;
		case 'miniupnpd':
			upnp_action('stop');
			break;
		case 'sshd':
			killbyname("sshd");
			break;
		case 'racoon':
			exec("killall -9 racoon");
			break;
		case 'openvpn':         
			$vpnmode = $_GET['vpnmode'];
			if (($vpnmode == "server") or ($vpnmode == "client")) {
				$id = $_GET['id'];
				$pidfile = "{$g['varrun_path']}/openvpn_{$vpnmode}{$id}.pid";
				killbypid($pidfile);
			}
			break;
		case 'relayd':
			mwexec('pkill relayd');
			break;
		default:
			stop_service($_GET['service']);
			break;
	}
	$savemsg = sprintf(gettext("%s has been stopped."), htmlspecialchars($_GET['service']));
	sleep(5);
}

/* batch mode, allow other scripts to call this script */
if($_GET['batch'])
	exit;

$pgtitle = array(gettext("Status"),gettext("Services"));
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<form action="status_services.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<p>

<div id="boxarea">
<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
	<thead>
	<tr>
		<td class="listhdrr" align="center"><?=gettext("Service");?></td>
		<td class="listhdrr" align="center"><?=gettext("Description");?></td>
		<td class="listhdrr" align="center"><?=gettext("Status");?></td>
	</tr>
	</thead>
	<tbody>
<?php

if (is_array($config['installedpackages']['service']))
	$services = $config['installedpackages']['service'];
else
	$services = array();

/*    Add services that are in the base.
 *
 */
if(is_radvd_enabled()) {
	$pconfig = array();
	$pconfig['name'] = "radvd";
	$pconfig['description'] = gettext("Router Advertisement Daemon");
	$services[] = $pconfig;
}

if(isset($config['dnsmasq']['enable'])) {
	$pconfig = array();
	$pconfig['name'] = "dnsmasq";
	$pconfig['description'] = gettext("DNS Forwarder");
	$services[] = $pconfig;
}

$pconfig = array();
$pconfig['name'] = "ntpd";
$pconfig['description'] = gettext("NTP clock sync");
$services[] = $pconfig;

if (is_array($config['captiveportal'])) {
	foreach ($config['captiveportal'] as $zone => $setting) {
		if (isset($setting['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "captiveportal";
			$pconfig['zone'] = $zone;
			$pconfig['description'] = gettext("Captive Portal") . ": ".htmlspecialchars($setting['zone']);
			$services[] = $pconfig;
		}
	}
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
	$pconfig = array();
	$pconfig['name'] = "dhcrelay";
	$pconfig['description'] = gettext("DHCP Relay");
	$services[] = $pconfig;
}

if(is_dhcp_server_enabled()) {
	$pconfig = array();
	$pconfig['name'] = "dhcpd";
	$pconfig['description'] = gettext("DHCP Service");
	$services[] = $pconfig;
}

if(isset($config['snmpd']['enable'])) {
	$pconfig = array();
	$pconfig['name'] = "bsnmpd";
	$pconfig['description'] = gettext("SNMP Service");
	$services[] = $pconfig;
}

if (is_array($config['igmpproxy']['igmpentry']) && (count($config['igmpproxy']['igmpentry']) > 0)) {
	$pconfig = array();
	$pconfig['name'] = "igmpproxy";
	$pconfig['description'] = gettext("IGMP proxy");
	$services[] = $pconfig;
}

if (isset($config['installedpackages']['miniupnpd']) && $config['installedpackages']['miniupnpd']['config'][0]['enable']) {
	$pconfig = array();
	$pconfig['name'] = "miniupnpd";
	$pconfig['description'] = gettext("UPnP Service");
	$services[] = $pconfig;
}

if (isset($config['ipsec']['enable'])) {
	$pconfig = array();
	$pconfig['name'] = "racoon";
	$pconfig['description'] = gettext("IPsec VPN");
	$services[] = $pconfig;
}

foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$pconfig = array();
				$pconfig['name'] = "openvpn";
				$pconfig['mode'] = $mode;
				$pconfig['id'] = $id;
				$pconfig['vpnid'] = $setting['vpnid'];
				$pconfig['description'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
				$services[] = $pconfig;
			}
		}
	}
}

if (count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
	$pconfig = array();
	$pconfig['name'] = "relayd";
	$pconfig['description'] = gettext("Server load balancing daemon");
	$services[] = $pconfig;
}

function service_name_compare($a, $b) {
	if (strtolower($a['name']) == strtolower($b['name']))
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
}

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach($services as $service) {
		if (empty($service['name']))
			continue;
		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr">' . $service['name'] . '</td>' . "\n";
		echo '<td class="listr">' . $service['description'] . '</td>' . "\n";
		switch ($service['name']) {
			case "openvpn":
				$running = is_pid_running("{$g['varrun_path']}/openvpn_{$service['mode']}{$service['vpnid']}.pid");
				break;
			case "captiveportal":
				$running = is_pid_running("{$g['varrun_path']}/lighty-{$service['zone']}-CaptivePortal.pid");
				if (isset($config['captiveportal'][$service['zone']]['httpslogin']))
					$running = $running && is_pid_running("{$g['varrun_path']}/lighty-{$service['zone']}-CaptivePortal-SSL.pid");
				break;
			default:
				$running = is_service_running($service['name']);
		}
		if($running) {
			echo "<td class=\"listr\" align=\"center\">\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_pass.gif\"> " . gettext("Running") . "</td>\n";
		} else {
			echo "<td class=\"listbg\" align=\"center\">\n";
			echo "<img src=\"/themes/" . $g["theme"] . "/images/icons/icon_block.gif\"> <font color=\"white\">" . gettext("Stopped") . "</td>\n";
		}
		echo '<td valign="middle" class="list" nowrap>';
		if($running) {
			switch ($service['name']) {
				case "openvpn":
					echo "<a href='status_services.php?mode=restartservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
					break;
				case "captiveportal":
					echo "<a href='status_services.php?mode=restartservice&service={$service['name']}&zone={$service['zone']}'>";
					break;
				default:
					echo "<a href='status_services.php?mode=restartservice&service={$service['name']}'>";
			}
			echo "<img title='" . gettext("Restart Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_restart.gif'></a>\n";
			switch ($service['name']) {
				case "openvpn":
					echo "<a href='status_services.php?mode=stopservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
					break;
				case "captiveportal":
					echo "<a href='status_services.php?mode=stopservice&service={$service['name']}&zone={$service['zone']}'>";
					break;
				default:
					echo "<a href='status_services.php?mode=stopservice&service={$service['name']}'>";
			}
			echo "<img title='" . gettext("Stop Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_stop.gif'>";
			echo "</a>";
		} else {
			switch ($service['name']) {
				case "openvpn":
					echo "<a href='status_services.php?mode=startservice&service={$service['name']}&vpnmode={$service['mode']}&id={$service['vpnid']}'>";
					break;
				case "captiveportal":
					echo "<a href='status_services.php?mode=startservice&service={$service['name']}&zone={$service['zone']}'>";
					break;
				default:
					echo "<a href='status_services.php?mode=startservice&service={$service['name']}'>";
			}
			echo "<img title='" . gettext("Start Service") . "' border='0' src='./themes/".$g['theme']."/images/icons/icon_service_start.gif'></a>\n";
		}
		echo "</td></tr>\n";
	}
} else {
	echo "<tr><td colspan=\"3\" align=\"center\">" . gettext("No services found") . ".</td></tr>\n";
}

?>
</tbody>
</table>
</div>
</p>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
