
<script src="/javascript/sorttable.js"></script>

<?php

require_once("globals.inc");
require_once("functions.inc");
/* $Id$ */

function return_ext_menu($section) {
	global $config;
	$htmltext = "";
	$extarray = array();
	if($config['installedpackages']['menu'] <> "") {
		foreach($config['installedpackages']['menu'] as $menuitem) {
			if($menuitem['section'] != $section) continue;
			if($menuitem['url'] <> "") {
			 	$addresswithport = getenv("HTTP_HOST");
				$colonpos = strpos($addresswithport, ":");
				if ($colonpos !== False){
					//my url is actually just the IP address of the pfsense box
					$myurl = substr($addresswithport, 0, $colonpos);
				}
				else
				{
					$myurl = $addresswithport;
				}

				$description = str_replace('$myurl', $myurl, $menuitem['url']);
			} else {
				$description = '/pkg.php?xml=' . $menuitem['configfile'];
			}
			$extarray[] = array($menuitem['name'], $description);
		}
	}
	return $extarray;
}

function output_menu($arrayitem) {
	foreach($arrayitem as $item) {
		echo '<li><a href="' . $item[1] . ' "class="navlnk">' . $item[0] . '</a></li>' . "\n";
	}
}

// System
$system = array();
$system[] = array("Advanced", "/system_advanced_admin.php");
$system[] = array("Firmware", "/system_firmware.php");
$system[] = array("General Setup", "/system.php");
$system[] = array("Logout", "/index.php?logout");
if ($g['platform'] == "pfSense" or $g['platform'] == "nanobsd")
	$system[] = array("Packages", "/pkg_mgr.php");
$system[] = array("Setup Wizard", "/wizard.php?xml=setup_wizard.xml");
$system[] = array("Routing", "/system_gateways.php");
$system[] = array("Cert Manager", "/system_camanager.php");
$system[] = array("User Manager", "/system_usermanager.php");
$system = msort(array_merge($system, return_ext_menu("System")),0);

// Interfaces
$interfaces = array();
if (!isset($config['system']['webgui']['noassigninterfaces']))
	$interfaces[] = array("(assign)", "/interfaces_assign.php");
$opts = get_configured_interface_with_descr(false, true);
foreach ($opts as $oif => $odescr)
	if (!isset($config['interfaces'][$oif]['ovpn']))
		$interfaces[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
$interfaces = msort(array_merge($interfaces, return_ext_menu("Interfaces")),0);

// Firewall
$firewall = array();
$firewall[] = array("Aliases", "/firewall_aliases.php");
if($config['interfaces']['lan']) 
	$firewall[] = array("NAT", "/firewall_nat.php");
$firewall[] = array("Rules", "/firewall_rules.php");
$firewall[] = array("Schedules", "/firewall_schedule.php");
if($config['interfaces']['lan']) 
	$firewall[] = array("Traffic Shaper", "/firewall_shaper.php");
$firewall[] = array("Virtual IPs", "/firewall_virtual_ip.php");
$firewall = msort(array_merge($firewall, return_ext_menu("Firewall")),0);

// Services
$services = array();
$services[] = array("Captive Portal", "/services_captiveportal.php");
$services[] = array("DNS Forwarder", "/services_dnsmasq.php");
$services[] = array("DHCP Relay", "/services_dhcp_relay.php");
if($g['services_dhcp_server_enable'])
	$services[] = array("DHCP Server", "/services_dhcp.php");
$services[] = array("Dynamic DNS", "/services_dyndns.php");
$services[] = array("IGMP proxy", "/services_igmpproxy.php");
$services[] = array("Load Balancer", "/load_balancer_pool.php");
$services[] = array("OLSR", "/pkg_edit.php?xml=olsrd.xml&id=0");
$services[] = array("PPPoE Server", "/vpn_pppoe.php");
$services[] = array("RIP", "/pkg_edit.php?xml=routed/routed.xml&id=0");
$services[] = array("SNMP", "/services_snmp.php");
if($config['interfaces']['lan']) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services[] = array("UPnP", "/pkg_edit.php?xml=miniupnpd.xml&id=0");
}
$services[] = array("OpenNTPD", "/pkg_edit.php?xml=openntpd.xml&id=0");
$services[] = array("Wake on LAN", "/services_wol.php");
$services = msort(array_merge($services, return_ext_menu("Services")),0);

// VPN
$vpn = array();
$vpn[] = array("IPsec", "/vpn_ipsec.php");
$vpn[] = array("OpenVPN", "/vpn_openvpn_server.php");
$vpn[] = array("PPTP", "/vpn_pptp.php");
$vpn[] = array("L2TP", "/vpn_l2tp.php");
$vpn = msort(array_merge($vpn, return_ext_menu("VPN")),0);

// Status
$status_menu = array();
if (isset($config['captiveportal']['enable']))
	$status_menu[] = array("Captive Portal", "/status_captiveportal.php");
$status_menu[] = array("CARP (failover)", "/carp_status.php");
$status_menu[] = array("Dashboard", "/index.php");
$status_menu[] = array("Gateways", "/status_gateways.php");
$status_menu[] = array("DHCP Leases", "/diag_dhcp_leases.php");
$status_menu[] = array("Filter Reload", "/status_filter_reload.php");
$status_menu[] = array("Interfaces", "/status_interfaces.php");
$status_menu[] = array("IPsec", "/diag_ipsec.php");
$status_menu[] = array("Load Balancer", "/status_slbd_pool.php");
$status_menu[] = array("OpenVPN", "/status_openvpn.php");
if ($g['platform'] == "pfSense")
	$status_menu[] = array("Package Logs", "/diag_pkglogs.php");
$status_menu[] = array("Queues", "/status_queues.php");
$status_menu[] = array("RRD Graphs", "/status_rrd_graph.php");
$status_menu[] = array("Services", "/status_services.php");
$status_menu[] = array("System Logs", "/diag_logs.php");
$status_menu[] = array("Traffic Graph", "/status_graph.php?if=wan");
if($config['interfaces']['lan']) 
	$status_menu[] = array("UPnP", "/status_upnp.php");
$ifentries = get_configured_interface_with_descr();
foreach ($ifentries as $ent => $entdesc) {
	if (is_array($config['interfaces'][$ent]['wireless']) &&
		preg_match($g['wireless_regex'], $config['interfaces'][$ent]['if']))
		$ifdescrs[$ent] = $entdesc;
}
if (count($ifdescrs) > 0)
	$status_menu[] = array("Wireless", "/status_wireless.php");
$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")),0);

// Diagnostics
$diagnostics = array();
$diagnostics[] = array("ARP Tables", "/diag_arp.php");
$diagnostics[] = array("Backup/Restore", "/diag_backup.php");
$diagnostics[] = array("Command Prompt", "/exec.php");
$diagnostics[] = array("DNS Lookup", "/diag_dns.php");
$diagnostics[] = array("Edit File", "/edit.php");
$diagnostics[] = array("Factory Defaults", "/diag_defaults.php");
$diagnostics[] = array("Halt System", "/halt.php" );
$diagnostics[] = array("Ping", "/diag_ping.php");
$diagnostics[] = array("pfTOP", "/diag_system_pftop.php");
$diagnostics[] = array("Reboot", "/reboot.php");
$diagnostics[] = array("Routes", "/diag_routes.php");
$diagnostics[] = array("Show Bogons", "/diag_showbogons.php");
$diagnostics[] = array("States", "/diag_dump_states.php");
$diagnostics[] = array("System Activity", "/diag_system_activity.php");
$diagnostics[] = array("Traceroute", "/diag_traceroute.php");
$diagnostics[] = array("Packet Capture", "/diag_packet_capture.php");
if($g['platform'] == "nanobsd")
	$diagnostics[] = array("/diag_nanobsd.php", "NanoBSD");

if (isset($config['system']['developer'])) {
	echo "<li><hr width=\"80%\"/></li>";
	$diagnostics[] = array("/restart_httpd.php", "Restart HTTPD");
}
$diagnostics = msort(array_merge($diagnostics, return_ext_menu("Diagnostics")),0);

/* NOTICE ACKNOWLEDGE CODE by Erik Kristensen */
if ($_REQUEST['noticeaction'] == 'acknowledge') {
	$notice_id = $_REQUEST['noticeid'];
	close_notice($notice_id);
}
/**********************************************/

?>

<div id="wrapper">

	<div id="header">
		<div id="header-left"><a href="index.php" id="status-link"><img src="/themes/<?= $g['theme']; ?>/images/transparent.gif" border="0"></img></a></div>
		<div id="header-right">
			<div class="container">
				<div class="left">webConfigurator</div>
				<div class="right">
<?
				if (are_notices_pending()) {
					$notices = get_notices();

					$requests=array();

					## Get Query Arguments from URL ###
					foreach ($_REQUEST as $key => $value) {
						if ($key != "PHPSESSID")
							$requests[] = $key.'='.$value;
					}
					if(is_array($requests))
						$request_string = implode("&", $requests);

					if(is_array($notices)) {
						foreach ($notices as $key => $value) {
							$date = date("m-d-y H:i:s", $key);
							$noticemsg = str_replace("'", "", $value['notice']);
							$noticemsg = str_replace('"', "", $noticemsg);
							$noticemsg = str_replace("\n", "", $noticemsg);
							$noticemsg = str_replace("<p>", "", $noticemsg);
							$noticemsg = str_replace("<pre>", "", $noticemsg);
							$noticemsg = str_replace("</pre>", "", $noticemsg);
							$noticemsg = str_replace("</p>", "", $noticemsg);
							$noticemsg = str_replace("<br>", "", $noticemsg);
							$extra_args = "";
							if($_GET['xml']) 
								$extraargs="&xml=" . $_GET['xml'];
							if($_POST['xml']) 
								$extraargs="&xml=" . $_POST['xml'];
							if($_GET['id']) 
								$extraargs="&xml=" . $_GET['id'];
							if($_POST['id'])
								$extraargs="&xml=" . $_POST['id'];
							$notice_msgs = '<a href="?noticeaction=acknowledge&noticeid=all' . $extraargs . '">Acknowledge All</a> &nbsp;&nbsp;&nbsp;&nbsp;.:.&nbsp;&nbsp;&nbsp;&nbsp; ';
							if ($value['url']) {
								$notice_msgs .= $date.' - <a href="'.$url.'?'.$request_string.'&noticeaction=acknowledge&noticeid='.$key.'">['.$value['id'].']</a>';
							} else {
								$notice_msgs .= $date.' - <a href="?'.$request_string.'&noticeaction=acknowledge&noticeid='.$key.'">['.$value['id'].']'.$noticemsg.'</a>';
							}
							$notice_msgs .= " &nbsp;&nbsp;&nbsp;&nbsp;.:.&nbsp;&nbsp;&nbsp;&nbsp; ";
						}
					}
?>
					<div id="alerts">
						<script type="text/javascript">
							var content='<div id="marquee-text"><?= $notice_msgs; ?></div>'
						</script>
						<script type="text/javascript" src="/javascript/ticker.js"></script>
					</div>
<?
				} else {
?>
					<div id="hostname">
						<? print $config['system']['hostname'] . "." . $config['system']['domain']; ?>
					</div>
<?
				}
?>
				</div>
			</div>
		</div>
	</div> <!-- Header DIV -->
	<div id="content">
		<div id="left">
			<div id="navigation" style="z-index:1000">
				<ul id="menu">
					<li class="firstdrop">
						<div>System</div>
						<ul class="subdrop">
						<?php
							output_menu($system);
						?>
						</ul>
					</li>
					<li class="drop">
						<div>Interfaces</div>
						<ul class="subdrop">
						<?php
							output_menu($interfaces);
						?>
						</ul>
					</li>
					<li class="drop">
						<div>Firewall</div>
						<ul class="subdrop">
						<?php
							output_menu($firewall);
						?>
						</ul>
					</li>
					<li class="drop">
						<div>Services</div>
						<ul class="subdrop">
						<?
							output_menu($services);
						?>
						</ul>
					</li>
					<li class="drop">
						<div>VPN</div>
						<ul class="subdrop">
						<?php
							output_menu($vpn);
						?>
						</ul>
					</li>
					<li class="drop">
						<div>Status</div>
						<ul class="subdrop">
						<?php
							output_menu($status_menu);
						?>
						</ul>
					</li>
					<li class="lastdrop">
						<div>Diagnostics</div>
						<ul id="diag" class="subdrop">
						<?
							output_menu($diagnostics);
						?>
						</ul>
					</li>
				</ul>
			</div>

		</div> <!-- Left DIV -->

		<div id="right">

<?php

	/* display a top alert bar if need be */
	$need_alert_display = false;
	$found_notices = are_notices_pending();
	if($found_notices == true) {
		$notices = get_notices();
		if(!$notices) {
			$need_alert_display = true;
			$display_text = print_notices() . "<br>";
		}
	}
	if($need_alert_display == true) {
                echo "<div style=\"background-color:#000000\" id=\"roundalert\">";
                echo "<table>";
		echo "<tr><td><font color=\"#ffffff\">";
		echo "&nbsp;&nbsp;<img align=\"middle\" src=\"/top_notification.gif\">&nbsp;&nbsp;&nbsp;";
		echo $display_text;
		echo "</td>";
		echo "</tr>";
		echo "</table>";
		echo "</div>";
	}

function add_to_menu($url, $name) {
	if (isAllowedPage($url))
		echo "<li><a href=\"{$url}\" class=\"navlnk\">{$name}</a></li>\n";
}
	
?>

<p class="pgtitle"><?=genhtmltitle($pgtitle);?></font></p>

<?php
	$pgtitle_output = true;
?>
