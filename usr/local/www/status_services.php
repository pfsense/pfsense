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

$services = get_services();

if (count($services) > 0) {
	uasort($services, "service_name_compare");
	foreach($services as $service) {
		if (empty($service['name']))
			continue;
		if (empty($service['description']))
			$service['description'] = get_pkg_descr($service['name']);
		echo '<tr><td class="listlr" width="20%">' . $service['name'] . '</td>' . "\n";
		echo '<td class="listr" width="55%">' . $service['description'] . '</td>' . "\n";
		echo get_service_status_icon($service, true, true);
		echo '<td valign="middle" class="list" nowrap>';
		echo get_service_control_links($service);
		$scut = get_shortcut_by_service_name($service['name']);
		if (!empty($scut)) {
			echo get_shortcut_main_link($scut, true, $service);
			echo get_shortcut_status_link($scut, true, $service);
			echo get_shortcut_log_link($scut, true);
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
