<?php
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");
require_once('notices.inc');

header('Content-Type: text/html; charset=utf-8');

$pagetitle = gentitle($pgtitle);

if (isset($config['system']['webgui']['pagenamefirst'])) {
	$tabtitle = $pagetitle . " - " . htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']);
} else {
	$tabtitle = htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']) . " - " . $pagetitle;
}

$cssfile = "/bootstrap/css/pfSense.css";

if (isset($config['system']['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/bootstrap/css/" . $config['system']['webgui']['webguicss'])) {
		$cssfile = "/bootstrap/css/" . $config['system']['webgui']['webguicss'];
	}
}

// set default colmns to two if unset
if (!isset($config['system']['webgui']['dashboardcolumns'])) {
	$config['system']['webgui']['dashboardcolumns'] = 2;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?=$cssfile?>" />
	<link rel="stylesheet" href="/font-awesome/css/font-awesome.min.css">
	<link rel="stylesheet" href="/sortable/sortable-theme-bootstrap.css">
	<title><?=$tabtitle?></title>
	<script type="text/javascript">
	//<![CDATA[
	var events = events || [];
	//]]>
	</script>
</head>

<?php

/* Determine automated help URL. Should output the page name and parameters
   separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);

/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}

/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is pkg_edit.php or wizard.php, reparse looking
	for the .xml filename */
if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
}

// Build the full help URL.
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

// Create a menu entry of any installed packages in the specified category
// (Now reads the menu information from $config['installedpackages']['menu'] only)
function return_ext_menu($section) {
	global $config;

	$htmltext = "";
	$extarray = array();

	if ((!empty($config['installedpackages']['package'])) && (!empty($config['installedpackages']['menu']))) {
		foreach ($config['installedpackages']['menu'] as $menu) {
//			print('Name: ' . $menu['name'] . ', Pkg category: ' . $menu['category'] . ', Section: ' . $section . '<br />');
			if ($menu['section'] != $section) {
				continue;
			}

			if ($menu['url'] != "") {
				$test_url = $menu['url'];
				$addresswithport = getenv("HTTP_HOST");
				$colonpos = strpos($addresswithport, ":");

				if ($colonpos !== false) {
					//my url is actually just the IP address of the pfsense box
					$myurl = substr($addresswithport, 0, $colonpos);
				} else {
					$myurl = $addresswithport;
				}
				$description = str_replace('$myurl', $myurl, $menu['url']);
			} else {
				$description = '/pkg.php?xml=' . $menu['configfile'];
				$test_url=$description;
			}

			if (isAllowedPage($test_url)) {
				$extarray[] = array($menu['name'], $description);
			}
		}
	}

	return $extarray;
}

function output_menu($arrayitem, $target = null) {
	foreach ($arrayitem as $item) {
		if (isAllowedPage($item[1]) || $item[1] == "/index.php?logout") {
			$attr = sprintf("href=\"%s\"", htmlentities($item[1]));
			if ($target) {
				$attr .= sprintf(" target=\"%s\"", htmlentities($target));
			}
			$class = "navlnk";
			if ($item['class']) {
				$class .= " {$item['class']}";
			}
			$attr .= sprintf(" class=\"%s\"", htmlentities($class));
			if ($item['style']) {
				$attr .= sprintf(" style=\"%s\"", htmlentities($item['style']));
			}
			echo "<li>". sprintf("<a %s>%s</a>", $attr, $item[0]). "</li>\n";
		}
	}
}

// System
$system_menu = array();
$system_menu[] = array(gettext("Logout"), "/index.php?logout");
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php");
$system_menu[] = array(gettext("Update"), "/pkg_mgr_install.php?id=firmware");
$system_menu[] = array(gettext("General Setup"), "/system.php");
$system_menu[] = array(gettext("High Avail. Sync"), "/system_hasync.php");
if ($g['platform'] == $g['product_name'] or $g['platform'] == "nanobsd") {
	$system_menu[] = array(gettext("Packages"), "/pkg_mgr_installed.php");
}
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php");
$system_menu[] = array(gettext("Cert Manager"), "/system_camanager.php");
if (!isAllowedPage("system_usermanager.php*")) {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager_passwordmg.php");
} else {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php");
}

$system_menu = msort(array_merge($system_menu, return_ext_menu("System")), 0);

// Interfaces
$interfaces_menu = array();
if (!isset($config['system']['webgui']['noassigninterfaces'])) {
	$interfaces_menu[] = array(gettext("(assign)"), "/interfaces_assign.php");
}
$opts = get_configured_interface_with_descr(false, true);
foreach ($opts as $oif => $odescr) {
	if (!isset($config['interfaces'][$oif]['ovpn'])) {
		$interfaces_menu[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
	}
}
$interfaces_menu = msort(array_merge($interfaces_menu, return_ext_menu("Interfaces")), 0);

// Firewall
$firewall_menu = array();
$firewall_menu[] = array(gettext("Aliases"), "/firewall_aliases.php");
$firewall_menu[] = array(gettext("NAT"), "/firewall_nat.php");
$firewall_menu[] = array(gettext("Rules"), "/firewall_rules.php");
$firewall_menu[] = array(gettext("Schedules"), "/firewall_schedule.php");
$firewall_menu[] = array(gettext("Traffic Shaper"), "/firewall_shaper.php");
$firewall_menu[] = array(gettext("Virtual IPs"), "/firewall_virtual_ip.php");
$firewall_menu = msort(array_merge($firewall_menu, return_ext_menu("Firewall")), 0);

// Services
$services_menu = array();
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php");
$services_menu[] = array(gettext("DHCP Relay"), "/services_dhcp_relay.php");
$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");

if ($g['services_dhcp_server_enable']) {
	$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp.php");
	$services_menu[] = array(gettext("DHCPv6 Server/RA"), "/services_dhcpv6.php");
}

$services_menu[] = array(gettext("Dynamic DNS"), "/services_dyndns.php");
$services_menu[] = array(gettext("IGMP Proxy"), "/services_igmpproxy.php");
$services_menu[] = array(gettext("Load Balancer"), "/load_balancer_pool.php");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php");
$services_menu[] = array(gettext("PPPoE Server"), "/services_pppoe.php");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php");

if (count($config['interfaces']) > 1) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/pkg_edit.php?xml=miniupnpd.xml");
}

$services_menu[] = array(gettext("Wake on LAN"), "/services_wol.php");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")), 0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php");
//$vpn_menu[] = array(gettext("PPTP"), "/vpn_pptp.php");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")), 0);

// Status
$status_menu = array();
$status_menu[] = array(gettext("Captive Portal"), "/status_captiveportal.php");
$status_menu[] = array(gettext("CARP (failover)"), "/status_carp.php");
$status_menu[] = array(gettext("Dashboard"), "/index.php");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php");
$status_menu[] = array(gettext("DHCPv6 Leases"), "/status_dhcpv6_leases.php");
$status_menu[] = array(gettext("Filter Reload"), "/status_filter_reload.php");
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php");
$status_menu[] = array(gettext("IPsec"), "/status_ipsec.php");
$status_menu[] = array(gettext("Load Balancer"), "/status_lb_pool.php");
$status_menu[] = array(gettext("NTP"), "/status_ntpd.php");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php");

if ($g['platform'] == $g['product_name']) {
	$status_menu[] = array(gettext("Package Logs"), "/status_pkglogs.php");
}

$status_menu[] = array(gettext("Queues"), "/status_queues.php");
$status_menu[] = array(gettext("RRD Graphs"), "/status_rrd_graph.php");
$status_menu[] = array(gettext("Services"), "/status_services.php");
$status_menu[] = array(gettext("System Logs"), "/status_logs.php");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php?if=wan");

if (count($config['interfaces']) > 1) {
	$status_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/status_upnp.php");
}

$ifentries = get_configured_interface_with_descr();
foreach ($ifentries as $ent => $entdesc) {
	if (is_array($config['interfaces'][$ent]['wireless']) &&
	    preg_match($g['wireless_regex'], $config['interfaces'][$ent]['if'])) {
		$wifdescrs[$ent] = $entdesc;
	}
}

if (count($wifdescrs) > 0) {
	$status_menu[] = array(gettext("Wireless"), "/status_wireless.php");
}

$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")), 0);

// Diagnostics
$diagnostics_menu = array();
$diagnostics_menu[] = array(gettext("ARP Table"), "/diag_arp.php");
$diagnostics_menu[] = array(gettext("Authentication"), "/diag_authentication.php");
$diagnostics_menu[] = array(gettext("Backup/Restore"), "/diag_backup.php");
$diagnostics_menu[] = array(gettext("Command Prompt"), "/exec.php");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php");
$diagnostics_menu[] = array(gettext("Edit File"), "/edit.php");
$diagnostics_menu[] = array(gettext("Factory Defaults"), "/diag_defaults.php");

if (file_exists("/var/run/gmirror_active")) {
	$diagnostics_menu[] = array(gettext("GEOM Mirrors"), "/diag_gmirror.php");
}

$diagnostics_menu[] = array(gettext("Halt System"), "/diag_halt.php");
$diagnostics_menu[] = array(gettext("Limiter Info"), "/diag_limiter_info.php");
$diagnostics_menu[] = array(gettext("NDP Table"), "/diag_ndp.php");
$diagnostics_menu[] = array(gettext("Tables"), "/diag_tables.php");
$diagnostics_menu[] = array(gettext("Ping"), "/diag_ping.php");
$diagnostics_menu[] = array(gettext("Test Port"), "/diag_testport.php");
$diagnostics_menu[] = array(gettext("pfInfo"), "/diag_pf_info.php");
$diagnostics_menu[] = array(gettext("pfTop"), "/diag_pftop.php");
$diagnostics_menu[] = array(gettext("Reboot"), "/diag_reboot.php");
$diagnostics_menu[] = array(gettext("Routes"), "/diag_routes.php");
$diagnostics_menu[] = array(gettext("SMART Status"), "/diag_smart.php");
$diagnostics_menu[] = array(gettext("Sockets"), "/diag_sockets.php");
$diagnostics_menu[] = array(gettext("States"), "/diag_dump_states.php");
$diagnostics_menu[] = array(gettext("States Summary"), "/diag_states_summary.php");
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php");
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php");

if ($g['platform'] == "nanobsd") {
	$diagnostics_menu[] = array(gettext("NanoBSD"), "/diag_nanobsd.php");
}

if (isset($config['system']['developer'])) {
	$diagnostics_menu[] = array(gettext("Restart HTTPD"), "/restart_httpd.php", "style" => "font-weight: bold; color: yellow;");
}

$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")), 0);

$gold_menu = array();
$gold_menu[] = array(gettext("pfSense Gold"), "https://www.pfsense.org/gold");
$gold_menu = msort(array_merge($gold_menu, return_ext_menu("Gold")), 0);

if (!$g['disablehelpmenu']) {
	$help_menu = array();
	$help_menu[] = array(gettext("About this Page"), $helpurl);
	if ($g['product_name'] == "pfSense") {
		$help_menu[] = array(gettext("Bug Database"), "https://www.pfsense.org/j.php?jumpto=redmine");
	}

	$help_menu[] = array(gettext("User Forum"), "https://www.pfsense.org/j.php?jumpto=forum");
	$help_menu[] = array(gettext("Documentation"), "https://www.pfsense.org/j.php?jumpto=doc");
	$help_menu[] = array(gettext("Developers Wiki"), "https://www.pfsense.org/j.php?jumpto=devwiki");
	$help_menu[] = array(gettext("Paid Support"), "https://www.pfsense.org/j.php?jumpto=portal");
	$help_menu[] = array(gettext("pfSense Book"), "https://www.pfsense.org/j.php?jumpto=book");
	$help_menu[] = array(gettext("Search Portal"), "https://www.pfsense.org/j.php?jumpto=searchportal");
	$help_menu[] = array(gettext("FreeBSD Handbook"), "https://www.pfsense.org/j.php?jumpto=fbsdhandbook");
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")), 0);
}

$menuclass = "static";

if (isset($config['system']['webgui']['webguifixedmenu'])) {
	$menuclass = "fixed";
}

$numColumns = $config['system']['webgui']['dashboardcolumns'];

if (($pagename === "index.php") && ($numColumns > 2)) {
	$columnsContainer = 'style="max-width: ' . 585*$numColumns . 'px;width: 100%"';
}
?>
<body id="<?=$numColumns?>">
<nav id="topmenu" class="navbar navbar-<?=$menuclass?>-top navbar-inverse">
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#pf-navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/"><img src="/logo.png" alt="pfSense" /></a>
		</div>
		<div class="collapse navbar-collapse" id="pf-navbar">
			<ul class="nav navbar-nav">
			<?php foreach ([
					['name' => 'System',	  'menu' => $system_menu,	   'href' => null],
					['name' => 'Interfaces',  'menu' => $interfaces_menu,  'href' => null],
					['name' => 'Firewall',	  'menu' => $firewall_menu,	   'href' => null],
					['name' => 'Services',	  'menu' => $services_menu,	   'href' => null],
					['name' => 'VPN',		  'menu' => $vpn_menu,		   'href' => null],
					['name' => 'Status',	  'menu' => $status_menu,	   'href' => null],
					['name' => 'Diagnostics', 'menu' => $diagnostics_menu, 'href' => null],
					['name' => 'Gold',		  'menu' => $gold_menu,		   'href' => '_blank'],
					['name' => 'Help',		  'menu' => $help_menu,		   'href' => '_blank']
				] as $item):
					if ($item['name'] == 'Help' && $g['disablehelpmenu']) {
						continue;
					} ?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
						<?=gettext($item['name'])?>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu"><?=output_menu($item['menu'], $item['href'])?></ul>
				</li>
			<?php endforeach?>
				<?php if (are_notices_pending()):?>
					<?php $notices = get_notices()?>
					<li class="dropdown">
						<a href="#" data-toggle="modal" data-target="#notices" role="button" aria-expanded="false">
							<i class="fa fa-bell text-danger" title="<?=gettext("Notices")?>"></i>
							<span class="badge bg-danger"><?=count($notices)?></span>
						</a>
					</li>
				<?php
					endif;
				?>
					<li class="dropdown">
						<a href="/index.php?logout">
							<i class="fa fa-sign-out" title="<?=gettext("Log out")?>"></i>
						</a>
					</li>
			</ul>
		</div>
	</div>
</nav>

<div class="container <?=$menuclass?>" <?=$columnsContainer?>>
	<header class="header">

<?php
	// If you set $notitle = true BEFORE including head.inc, the page title will be supressed
	if (isset($notitle)) {
		print('<br />');
		unset($notitle);
	} else {
		print(genhtmltitle($pgtitle));
	}
?>
		<ul class="context-links">
<?php

if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service'])) {
	$ssvc = array();
	switch ($shortcut_section) {
		case "openvpn":
			$ssvc = find_service_by_openvpn_vpnid($vpnid);
			break;
		case "captiveportal":
			$ssvc = find_service_by_cp_zone($cpzone);
			break;
		default:
			$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);
	}
	if (!empty($ssvc)) {
		// echo '<li>'. get_service_status_icon($ssvc, false). '</li>'; TODO: Add missing function
		echo '<li>'. get_service_control_GET_links($ssvc, false). '</li>';
	}
}

echo '<li>'. get_shortcut_main_link($shortcut_section, false). '</li>';
echo '<li>'. get_shortcut_status_link($shortcut_section, false). '</li>';
echo '<li>'. get_shortcut_log_link($shortcut_section, false). '</li>';

?>
	<?php if (!$g['disablehelpicon']): ?>
		<li>
			<a href="<?=$helpurl?>" target="_blank" title="<?=gettext("Help for items on this page")?>" class="help-icon">
				<i class="fa fa-question-circle"></i>
			</a>
		</li>
	<?php endif?>
		</ul>
	</header>
<?php
/* if upgrade in progress, alert user */
if (is_subsystem_dirty('packagelock') || file_exists('/conf/needs_package_sync' && platform_booting())) {
	if (file_exists('/conf/needs_package_sync') && platform_booting()) {
		$info_text = sprintf(gettext("%s is booting then packages will be reinstalled in the background.<p>Do not make changes in the GUI until this is complete."), $g['product_name']);
	} else {
		$pgtitle = array(gettext("System"), gettext("Package Manager"));
		$info_text = gettext("Packages are currently being reinstalled in the background.<p>Do not make changes in the GUI until this is complete.");
		$info_text .= gettext("<p>If the above message is still displayed after a couple of hours, use the 'Clear Package Lock' button on the <a href='diag_backup.php' title='Backup/Restore'>Backup/Restore page</a> and reinstall packages manually.");
	}

	print_info_box($info_text);
}

$pgtitle_output = true;

/*	If this page is being remotely managed then do not allow the loading of the contents. */
if ($config['remote_managed_pages']['item']) {
	foreach ($config['remote_managed_pages']['item'] as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			print_info_box_np("This page is currently being managed by a remote machine.");
			include("foot.inc");
			exit;
		}
	}
}

// Modal notices window
// The notices modal needs to be outside of the page display div or things get messy
if (are_notices_pending()):?>

<div id="notices" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>

				<h3 class="modal-title" id="myModalLabel">Notices</h3>
			</div>

			<div class="modal-body">
<?php
	$noticeCategories = array();

	if (is_array($notices)) {
		foreach ($notices as $time => $notice) {
			if (!isset($noticeCategories[ $notice['category'] ])) {
				$noticeCategories[ $notice['category'] ] = array();
			}

			$notice['time'] = $time;
			array_push($noticeCategories[ $notice['category'] ], $notice);
		}
	}

	foreach ($noticeCategories as $category => $catNotices):?>
				<h4><?=$category?></h4>
				<ul>
<?php
	foreach ($catNotices as $notice):
?>
					<li>
						<b>
<?php if (!empty($notice['url'])):?>
							<a href="<?=$notice['url']?>"><?=$notice['id']?></a> -
<?php endif;?>
						</b>
						<?=$notice['notice']?>
						<i>@ <?=date('Y-m-d H:i:s', $notice['time'])?></i>
					</li>
<?php	endforeach;?>
				</ul>
<?php endforeach;?>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button type="button" id="clearallnotices" class="btn btn-primary"><?=gettext("Mark all as read")?></button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
	    $('#clearallnotices').click(function() {
			ajaxRequest = $.ajax({
				url: "/index.php",
				type: "post",
				data: { closenotice: "all"},
				success: function() {
					window.location = window.location.href;
				},
				failure: function() {
					alert("Error clearing notices!");
				}
			});
		});
	});
//]]>
</script>

<?php endif;

// Get the flash Messages
get_flash_message();
