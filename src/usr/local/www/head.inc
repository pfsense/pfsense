<?php
/*
 * head.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");
require_once('notices.inc');

header('Content-Type: text/html; charset=utf-8');

$pagetitle = gentitle($pgtitle);
$system_url = $config['system']['hostname'] . "." . $config['system']['domain'];

if ($user_settings['webgui']['pagenamefirst']) {
	$tabtitle = $pagetitle . " - " . htmlspecialchars($system_url);
} else {
	$tabtitle = htmlspecialchars($system_url) . " - " . $pagetitle;
}

$cssfile = "/css/pfSense.css";

if (isset($user_settings['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
		$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
	}
}

// set default columns to two if unset
if (!isset($config['system']['webgui']['dashboardcolumns'])) {
	$config['system']['webgui']['dashboardcolumns'] = 2;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="apple-touch-icon-precomposed" href="/apple-touch/apple-touch-icon-iphone-60x60-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="60x60" href="/apple-touch/apple-touch-icon-ipad-76x76-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="/apple-touch/apple-touch-icon-iphone-retina-120x120-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="/apple-touch/apple-touch-icon-ipad-retina-152x152-precomposed.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/manifest.json">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/all.min.css')?>">
	<link rel="stylesheet" href="/vendor/font-awesome/css/v4-shims.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/v4-shims.css')?>">
	<link rel="stylesheet" href="/vendor/sortable/sortable-theme-bootstrap.css?v=<?=filemtime('/usr/local/www/vendor/sortable/sortable-theme-bootstrap.css')?>">
	<link rel="stylesheet" href="<?=$cssfile?>?v=<?=filemtime('/usr/local/www/' . $cssfile)?>" />

	<title><?=$tabtitle?></title>
	<script type="text/javascript">
	//<![CDATA[
	var events = events || [];
	var newSeperator = false;
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
} else if ($pagename == "status_logs.php") {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 8) == "logfile=") {
			$logtype = explode('=', $param);
			$pagename .= '-' . $logtype[1];
		}
	}
}

// Build the full help URL.
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

/*
 * Read files from $g['ext_menu_path']/*.xml and fill an array with menu info
 */
function read_ext_menu_path_data() {
	global $g;

	$result = array();

	if (!is_dir($g['ext_menu_path'])) {
		return $result;
	}

	foreach (glob("{$g['ext_menu_path']}/*.xml") as $menu_xml) {
		$xml_data = parse_xml_config_pkg($menu_xml, "packagegui");
		if (empty($xml_data['menu'])) {
			continue;
		}
		foreach ($xml_data['menu'] as $menu) {
			$result[] = $menu;
		}
	}

	return $result;
}

// Create a menu entry of any installed packages in the specified category
// (Now reads the menu information from $config['installedpackages']['menu'] only)
function return_ext_menu($section) {
	global $config, $ext_menu_path_data;

	$htmltext = "";
	$extarray = array();
	$ext_menu_entries = array();

	if ((!empty($config['installedpackages']['package'])) && (!empty($config['installedpackages']['menu']))) {
		foreach ($config['installedpackages']['menu'] as $menu) {
			if (isset($menu['name']) && ($menu['name'] != "AutoConfigBackup")) { // AutoConfigBackup was moved to a built-in function
	//			print('Name: ' . $menu['name'] . ', Pkg category: ' . $menu['category'] . ', Section: ' . $section . '<br />');
				if (isset($menu['section']) && ($menu['section'] == $section)) {
					$ext_menu_entries[] = $menu;
				}
			}
		}
	}

	foreach ($ext_menu_path_data as $menu) {
		if ($menu['section'] == $section) {
			$ext_menu_entries[] = $menu;
		}
	}

	foreach ($ext_menu_entries as $menu) {
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

	return $extarray;
}

function output_menu($arrayitem, $target = null, $section = "") {
	$output = "";

	foreach ($arrayitem as $item) {

		/* If the user has access to help pages, also show the full help menu. See #5909 */
		if (isAllowedPage($item[1]) || $item[1] == "/index.php?logout" ||
		    (($section == "Help") && isAllowedPage("help.php")) ||
		    (substr($item[1], 0, 8) == "https://")) {
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


			if ($item[0] == '-DIVIDER-') {
				$output .= ' <li class="divider"></li>';
			} else {
				$output .= "<li>". sprintf("<a %s %s>%s</a>", $attr, ($item[1] == "/index.php?logout") ? "usepost":"",$item[0]) . "</li>\n";
			}
		}
	}

	return $output;
}

$ext_menu_path_data = read_ext_menu_path_data();

// System
$system_menu = array();
$system_menu[] = array(gettext("Logout") . " (" . $_SESSION['Username'] . ")", "/index.php?logout");
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php");
$system_menu[] = array(gettext("Update"), "/pkg_mgr_install.php?id=firmware");
$system_menu[] = array(gettext("General Setup"), "/system.php");
$system_menu[] = array(gettext("High Avail. Sync"), "/system_hasync.php");
$system_menu[] = array(gettext("Package Manager"), "/pkg_mgr_installed.php");
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php");
$system_menu[] = array(gettext("Cert. Manager"), "/system_camanager.php");
if (!isAllowedPage("system_usermanager.php")) {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager_passwordmg.php");
} else {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php");
}

if ($user_settings['customsettings'] && isAllowedPage("system_user_settings.php")) {
	$system_menu[] = array(gettext("User Settings"), "/system_user_settings.php");
}

$system_menu = msort(array_merge($system_menu, return_ext_menu("System")), 0);

// Interfaces
// NOTE:
// Now that menus are sorted, adding a DIVIDER must be done after the sorting so an array is formed of the
// items above the divider and another for below it. These are then sorted and combined with the divider
$interfaces_menu = array();
$interfaces_top = array();
$interfaces_bottom = array();

if (!isset($config['system']['webgui']['noassigninterfaces'])) {
	$interfaces_top[] = array(gettext("Assignments"), "/interfaces_assign.php");
	$div = true;
}

$platform = system_identify_specific_platform();

if ($platform['name'] == "uFW") {
	$interfaces_top[] = array(gettext("Switches"), "/switch_system.php");
}

$opts = get_configured_interface_with_descr(true);

foreach ($opts as $oif => $odescr) {
	if (!isset($config['interfaces'][$oif]['ovpn'])) {
		$interfaces_bottom[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
	}
}

$interfaces_bottom = array_merge($interfaces_bottom, return_ext_menu("Interfaces"));

if ($user_settings['webgui']['interfacessort']) {
	$interfaces_bottom = msort($interfaces_bottom, 0);
}

// Combine the top section, the divider and the bottom section of this menu
$interfaces_menu = array_merge($interfaces_top, [array(0 => "-DIVIDER-")], $interfaces_bottom);

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
$services_menu[] = array(gettext("Auto Config Backup"), "/services_acb.php");
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php");
$services_menu[] = array(gettext("DHCP Relay"), "/services_dhcp_relay.php");
$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");

if ($g['services_dhcp_server_enable']) {
	$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp.php");
	$services_menu[] = array(htmlspecialchars(gettext("DHCPv6 Server & RA")), "/services_dhcpv6.php");
}

$services_menu[] = array(gettext("Dynamic DNS"), "/services_dyndns.php");
$services_menu[] = array(gettext("IGMP Proxy"), "/services_igmpproxy.php");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php");
$services_menu[] = array(gettext("PPPoE Server"), "/services_pppoe.php");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php");

if (count($config['interfaces']) > 1) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/pkg_edit.php?xml=miniupnpd.xml");
}

$services_menu[] = array(gettext("Wake-on-LAN"), "/services_wol.php");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")), 0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php");
//$vpn_menu[] = array(gettext("PPTP"), "/vpn_pptp.php");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php");
$vpn_menu[] = array(gettext("WireGuard"), "/vpn_wg.php");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")), 0);

// Status
$status_menu = array();
$status_menu[] = array(gettext("Captive Portal"), "/status_captiveportal.php");
$status_menu[] = array(gettext("CARP (failover)"), "/status_carp.php");
$status_menu[] = array(gettext("Dashboard"), "/index.php");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php");
$status_menu[] = array(gettext("DHCPv6 Leases"), "/status_dhcpv6_leases.php");
$status_menu[] = array(gettext("DNS Resolver"), "/status_unbound.php");
$status_menu[] = array(gettext("Filter Reload"), "/status_filter_reload.php?user=true");
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php");
$status_menu[] = array(gettext("IPsec"), "/status_ipsec.php");
$status_menu[] = array(gettext("NTP"), "/status_ntpd.php");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php");
$status_menu[] = array(gettext("Queues"), "/status_queues.php");
$status_menu[] = array(gettext("Services"), "/status_services.php");
$status_menu[] = array(gettext("System Logs"), "/status_logs.php");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php");

if (count($config['interfaces']) > 1) {
	$status_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/status_upnp.php");
}

$wifdescrs = array();
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
$diagnostics_menu[] = array(htmlspecialchars(gettext("Backup & Restore")), "/diag_backup.php");
$diagnostics_menu[] = array(gettext("Command Prompt"), "/diag_command.php");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php");
$diagnostics_menu[] = array(gettext("Edit File"), "/diag_edit.php");
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
$diagnostics_menu[] = array(gettext("S.M.A.R.T. Status"), "/diag_smart.php");
$diagnostics_menu[] = array(gettext("Sockets"), "/diag_sockets.php");
$diagnostics_menu[] = array(gettext("States"), "/diag_dump_states.php");
$diagnostics_menu[] = array(gettext("States Summary"), "/diag_states_summary.php");
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php");
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php");

$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")), 0);

if (!$g['disablehelpmenu']) {
	$help_menu = array();
	$help_menu[] = array(gettext("About this Page"), $helpurl);
	if ($g['product_name'] == "pfSense") {
		$help_menu[] = array(gettext("Bug Database"), "https://redirects.netgate.com/issues");
	}

	$help_menu[] = array(gettext("User Forum"), "https://redirects.netgate.com/forum");
	$help_menu[] = array(gettext("Documentation"), "https://redirects.netgate.com/docs");
	$help_menu[] = array(gettext("Paid Support"), "https://redirects.netgate.com/support");
	$help_menu[] = array(gettext("pfSense Book"), "https://redirects.netgate.com/book");
	$help_menu[] = array(gettext("FreeBSD Handbook"), "https://redirects.netgate.com/fbsdhandbook");
	$help_menu[] = array(gettext("User survey"), "https://redirects.netgate.com/survey_1");
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")), 0);
}

$menuclass = "static";

if ($user_settings['webgui']['webguifixedmenu'] == "fixed") {
	$menuclass = "fixed";
}

$numColumns = (int) $user_settings['webgui']['dashboardcolumns'];

if (($pagename === "index.php") && ($numColumns > 2)) {
	$columnsContainer = 'style="max-width: ' . 585*$numColumns . 'px;width: 100%"';
}

$display_notices = false;
$allow_clear_notices = false;

if (are_notices_pending()) {
	// Evaluate user privs to determine if notices should be displayed, and if the user can clear them.
	$user_entry = getUserEntry($_SESSION['Username']);
	if (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "user-view-clear-notices") || userHasPrivilege($user_entry, "page-all")) {
		$display_notices = true;
		$allow_clear_notices = true;
	} elseif (userHasPrivilege($user_entry, "user-view-notices")) {
		$display_notices = true;
	}
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
			<a class="navbar-brand" href="/">
				<?php include("/usr/local/www/logo.svg"); ?>
				<span style="color:white;font-size:.5em;text-transform:uppercase;letter-spacing:1px;">Community Edition</span>
			</a>
		</div>
		<div class="collapse navbar-collapse" id="pf-navbar">
			<ul class="nav navbar-nav">
			<?php
                if ($user_settings['webgui']['webguihostnamemenu'] == 'hostonly') {
                    $help_menu_title = htmlspecialchars($config['system']['hostname']);
                }
                elseif ($user_settings['webgui']['webguihostnamemenu'] == 'fqdn') {
                    $help_menu_title = htmlspecialchars($system_url);
                }
                else {
                    $help_menu_title = 'Help';
                }
                foreach ([
					['name' => 'System',	     'menu' => $system_menu,	  'href' => null],
					['name' => 'Interfaces',     'menu' => $interfaces_menu,  'href' => null],
					['name' => 'Firewall',	     'menu' => $firewall_menu,	  'href' => null],
					['name' => 'Services',	     'menu' => $services_menu,	  'href' => null],
					['name' => 'VPN',		     'menu' => $vpn_menu,		  'href' => null],
					['name' => 'Status',	     'menu' => $status_menu,	  'href' => null],
					['name' => 'Diagnostics',    'menu' => $diagnostics_menu, 'href' => null],
                    ['name' => $help_menu_title, 'menu' => $help_menu,		  'href' => '_blank']
				] as $item):
					if ($item['name'] == 'Help' && $g['disablehelpmenu']) {
						continue;
					}

					$menu_output = output_menu($item['menu'], $item['href'], $item['name']);

					if (strlen($menu_output) > 0):
?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
						<?=gettext($item['name'])?>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu"><?=$menu_output?></ul>
				</li>

<?php
					endif;
			 	endforeach?>
			</ul>
			<ul class="nav navbar-nav navbar-right">
				<?php if ($display_notices):?>
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
						<a href="/index.php?logout" usepost>
							<i class="fa fa-sign-out" title="<?=gettext("Logout") . " (" . $_SESSION['Username'] . "@" . htmlspecialchars($system_url) . ")"?>"></i>
						</a>
					</li>
			</ul>
		</div>
	</div>
</nav>

<div class="container <?=$menuclass?>" <?=$columnsContainer?>>

<?php
	// Print a warning if current user = admin and the password hash is still set to the default value
	if ($_SESSION['Username'] == "admin") {
		$cu = getUserEntry("admin");

		$hash = (empty($cu['bcrypt-hash']) ? $cu['password'] : $cu['bcrypt-hash']);

		if (password_verify($g['factory_shipped_password'], $hash)) {
			print('<div class="alert alert-danger">' .
				sprintf(gettext('%sWARNING:%s The \'admin\' account password is set to the default value. ' .
				' %s Change the password in the User Manager.%s'),
				'<strong>', '</strong>', '<a href="/system_usermanager.php?act=edit&userid=' . $cu['uid'] . '">', '</a>') .
				'</div>');
		}
	}
?>

	<header class="header">

<?php
	// If you set $notitle = true BEFORE including head.inc, the page title will be supressed
	if (isset($notitle)) {
		print('<br />');
		unset($notitle);
	} else {
		if (isset($pglinks)) {
			print(genhtmltitle($pgtitle, $pglinks));
		} else {
			print(genhtmltitle($pgtitle));
		}
	}
?>
		<ul class="context-links">

	<?php if (isset($widgets)): ?>
		<li>
			<a href="#" title="<?=gettext("Save dashboard layout")?>" id="btnstore" class="invisible">
				<i class="fa fa-save icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($dashboard_available_widgets_hidden): ?>
		<li>
			<a onclick="$('#widget-available').toggle(360);" title="<?=gettext("Available widgets")?>">
				<i class="fa fa-plus icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($system_logs_filter_form_hidden): ?>
		<li>
			<a onclick="$('#filter-form').toggle(360)" title="<?=gettext("Log filter")?>">
				<i class="fa fa-filter icon-pointer"></i>
			</a>
		</li>
	<?php endif ?>

	<?php if ($system_logs_manage_log_form_hidden):
			/* If the user does not have access to status logs settings page, then exclude the manage log panel icon from the title bar. */
			if (isAllowedPage("status_logs_settings.php")) {
	?>
		<li>
			<a onclick="$('#manage-log-form').toggle(360)" title="<?=gettext("Manage log")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php	}
		endif
	?>

	<?php if ($monitoring_settings_form_hidden): ?>
		<li>
			<a onclick="$('#monitoring-settings-form').toggle(360);" title="<?=gettext("Settings")?>">
				<i class="fa fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($status_monitoring): ?>
		<li>
			<a class="update-graph" title="<?=gettext("Refresh Graph")?>">
				<i class="fa fa-repeat icon-pointer"></i>
			</a>
		</li>
		<li>
			<a class="export-graph" id="export-graph" title="<?=gettext("Export Graph")?>">
				<i class="fa fa-download icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

<?php
/* Determine shortcut section for XML-based packages */
if (empty($shortcut_section) && !empty($xmlfile)) {
	$shortcut_section = basename($pagename, '.xml');
}

if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service']) && isAllowedPage('status_services.php')) {
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
		echo '<li>'. get_service_control_links($ssvc, false). '</li>';
	}
}

if (('' != ($link = get_shortcut_main_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['main']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_status_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['status']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_log_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['log']))) {
	echo '<li>' . $link . '</li>';
}

?>
	<?php if (!$g['disablehelpicon'] && isAllowedPage("help.php")): ?>
		<li>
			<a href="<?=$helpurl?>" target="_blank" title="<?=gettext("Help for items on this page")?>">
				<i class="fa fa-question-circle"></i>
			</a>
		</li>
	<?php endif?>
		</ul>
	</header>
<?php
/* if upgrade in progress, alert user */
$warning_text = "";
if (file_exists('/conf/needs_package_sync') && platform_booting()) {
	$warning_text = sprintf(gettext(
	    '%1$s%3$s is booting, then packages will be reinstalled in the ' .
	    'background.%2$s%1$sDo not make changes in the GUI until this is ' .
	    'complete.%2$s'), '<p>', '</p>', $g['product_label_html']);
} elseif (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	$warning_text = sprintf(gettext('%1$sPackages are currently being ' .
	    'reinstalled in the background.%2$s%1$sDo not make changes in ' .
	    'the GUI until this is complete.%2$s'), '<p>', '</p>');
	$warning_text .= sprintf(gettext('%1$sIf the above message is still ' .
	    'displayed after a couple of hours, use the \'Clear Package ' .
	    'Lock\' button on the %3$s page and reinstall packages manually.' .
	    '%2$s'), '<p>', '</p>', sprintf('<a href="diag_backup.php" ' .
	    'title="%1$s &gt; %2$s">%1$s &gt; %2$s</a>', gettext('Diagnostics'),
	    htmlspecialchars(gettext('Backup & Restore'))));
}

if (!empty($warning_text)) {
	print_info_box($warning_text);
}

/*	If this page is being remotely managed then do not allow the loading of the contents. */
if ($config['remote_managed_pages']['item']) {
	foreach ($config['remote_managed_pages']['item'] as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			print_info_box(gettext("This page is currently being managed by a remote machine."));
			include("foot.inc");
			exit;
		}
	}
}

// Modal notices window
// The notices modal needs to be outside of the page display div or things get messy
if ($display_notices):
?>

<div id="notices" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>

				<h3 class="modal-title" id="myModalLabel"><?=gettext("Notices")?></h3>
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
							<a href="<?=htmlspecialchars($notice['url'])?>"><?=htmlspecialchars($notice['id'])?></a> -
<?php endif;?>
						</b>
						<?=str_replace("\n", "<br/>", htmlspecialchars($notice['notice']))?>
						<i>@ <?=date('Y-m-d H:i:s', $notice['time'])?></i>
					</li>
<?php	endforeach;?>
				</ul>
<?php endforeach;?>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-info" data-dismiss="modal"><i class="fa fa-times icon-embed-btn"></i><?=gettext("Close")?></button>
<?php if ($allow_clear_notices && isAllowedPage("/index.php")):?>
				<button type="button" id="clearallnotices" class="btn btn-primary"><i class="fa fa-trash-o icon-embed-btn"></i><?=gettext("Mark All as Read")?></button>
<?php endif;?>
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

<?php
endif; // ($display_notices)

// Get the flash Messages
get_flash_message();
