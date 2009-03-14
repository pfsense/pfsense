<?php

require_once("globals.inc");
require_once("functions.inc");
/* $Id$ */

function return_ext_menu($section) {
	global $config;
	$htmltext = "";
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
			$htmltext .= '<li><a href="' . $description . ' "class="navlnk">' . $menuitem['name'] . '</a></li>' . "\n";
		}
	}
	return $htmltext;
}

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
							output_menu_item("/system_advanced_admin.php", "Advanced");
							output_menu_item("/system_firmware.php", "Firmware");
							output_menu_item("/system.php", "General Setup");
							?>
						<?php if($g['platform'] == "pfSense"): ?>
							<?=output_menu_item("/pkg_mgr.php", "Packages");?>
						<?php endif; ?>
							<?=output_menu_item("/wizard.php?xml=setup_wizard.xml", "Setup Wizard");?>
							<?=output_menu_item("/system_gateways.php", "Routing");?>
							<?=output_menu_item("/system_camanager.php", "Cert Manager");?>	
							<?=output_menu_item("/system_usermanager.php", "User Manager");?>
							<li><a href="/index.php?logout" class="navlnk">Logout</a></li>
						</ul>
					</li>
					<li class="drop">
						<div>Interfaces</div>
						<ul class="subdrop">
							<?php if (!isset($config['system']['webgui']['noassigninterfaces'])): ?>
							<?=output_menu_item("/interfaces_assign.php", "(assign)");?>								
							<?php endif; ?>
							<?php $opts = get_configured_interface_with_descr(false, true); 
							foreach ($opts as $oif => $odescr): 
								if (!isset($config['interfaces'][$oif]['ovpn'])): ?>
							<?=output_menu_item("/interfaces.php?if={$oif}", htmlspecialchars($odescr))?>
							<?php endif; endforeach; ?>
							<?php echo return_ext_menu("Interfaces"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>Firewall</div>
						<ul class="subdrop">
							<?=output_menu_item("/firewall_aliases.php", "Aliases");?>	
							<?php
							if($config['interfaces']['lan']) {
								/* no use for NAT in single-interface deployments
								remove to reduce user confusion
								*/
								output_menu_item("/firewall_nat.php", "NAT");	
							}
							?>
							<?=output_menu_item("/firewall_rules.php", "Rules");?>	
							<?=output_menu_item("/firewall_schedule.php", "Schedules");?>	
							<?php
							if($config['interfaces']['lan']) {
								/* no use for traffic shaper in single-interface
								  deployments
								remove to reduce user confusion
								*/
								output_menu_item("/firewall_shaper.php", "Traffic Shaper");
							}
							?>	
							<?=output_menu_item("/firewall_virtual_ip.php", "Virtual IPs");?>	
							<?php echo return_ext_menu("Firewall"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>Services</div>
						<ul class="subdrop">
							<?=output_menu_item("/services_captiveportal.php", "Captive Portal");?>	
							<?=output_menu_item("/services_dnsmasq.php", "DNS Forwarder");?>	
							<?=output_menu_item("/services_dhcp_relay.php", "DHCP Relay");?>	
							<?php 
								if($g['services_dhcp_server_enable']) 
									output_menu_item("/services_dhcp.php", "DHCP Server");	
							?>
							<?=output_menu_item("/services_dyndns.php", "Dynamic DNS");?>
							<?=output_menu_item("/services_igmpproxy.php", "IGMP proxy");?>
							<?=output_menu_item("/load_balancer_pool.php", "Load Balancer");?>	
							<?=output_menu_item("/pkg_edit.php?xml=olsrd.xml&id=0", "OLSR");?>	
							<?=output_menu_item("/vpn_pppoe.php", "PPPoE Server");?>	
							<?=output_menu_item("/pkg_edit.php?xml=routed/routed.xml&id=0", "RIP");?>	
							<?=output_menu_item("/services_snmp.php", "SNMP");?>	
							<?php
							if($config['interfaces']['lan']) {
								/* no use for UPnP in single-interface deployments
								remove to reduce user confusion
								*/
								output_menu_item("/pkg_edit.php?xml=miniupnpd.xml&id=0", "UPnP");	
							}
							?>
							<?=output_menu_item("/pkg_edit.php?xml=openntpd.xml&id=0", "OpenNTPD");?>	
							<?=output_menu_item("/services_wol.php", "Wake on LAN");?>	
							<?php echo return_ext_menu("Services"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>VPN</div>
						<ul class="subdrop">
							<?=output_menu_item("/vpn_ipsec.php", "IPsec");?>
							<?=output_menu_item("/vpn_openvpn_server.php", "OpenVPN");?>
							<?=output_menu_item("/vpn_pptp.php", "PPTP");?>
							<?=output_menu_item("/vpn_l2tp.php", "L2TP");?>	
							<?php echo return_ext_menu("VPN"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>Status</div>
						<ul class="subdrop">
							<?php if (isset($config['captiveportal']['enable'])): ?>
							<?=output_menu_item("/status_captiveportal.php", "Captive Portal");?>
							<?php endif; ?>
							<?=output_menu_item("/carp_status.php", "CARP (failover)");?>
							<?=output_menu_item("/index.php", "Dashboard");?>
							<?=output_menu_item("/status_gateways.php", "Gateways");?>
							<?=output_menu_item("/diag_dhcp_leases.php", "DHCP Leases");?>
							<?=output_menu_item("/status_filter_reload.php", "Filter Reload");?>
							<?=output_menu_item("/status_interfaces.php", "Interfaces");?>
							<?=output_menu_item("/diag_ipsec.php", "IPsec");?>
							<?=output_menu_item("/status_slbd_pool.php", "Load Balancer");?>
							<?=output_menu_item("/status_openvpn.php", "OpenVPN");?>
							<?php if($g['platform'] == "pfSense"): ?>
							<?=output_menu_item("/diag_pkglogs.php", "Package Logs");?>
							<?php endif; ?>
							<?=output_menu_item("/status_queues.php", "Queues");?>
							<?=output_menu_item("/status_rrd_graph.php", "RRD Graphs");?>
							<?=output_menu_item("/status_services.php", "Services");?>
							<?=output_menu_item("/diag_logs.php", "System Logs");?>
							<?=output_menu_item("/status_graph.php?if=wan", "Traffic Graph");?>
							<?php
							if($config['interfaces']['lan']) {
								/* no use for UPnP in single-interface deployments
								remove to reduce user confusion
								*/
								output_menu_item("/status_upnp.php", "UPnP");	
							}
							?>
							<?php $ifentries = get_configured_interface_with_descr();
							foreach ($ifentries as $ent => $entdesc) {
								if (is_array($config['interfaces'][$ent]['wireless']) &&
									preg_match($g['wireless_regex'], $config['interfaces'][$ent]['if']))
									$ifdescrs[$ent] = $entdesc;
							}
							if (count($ifdescrs) > 0): ?>
							<?=output_menu_item("/status_wireless.php", "Wireless");?>
							<?php endif; ?>
							<?php echo return_ext_menu("Status"); ?>
						</ul>
					</li>
					<li class="lastdrop">
						<div>Diagnostics</div>
						<ul id="diag" class="subdrop">
							<?=output_menu_item("/diag_arp.php", "ARP Tables");?>
							<?=output_menu_item("/diag_backup.php", "Backup/Restore");?>
							<?=output_menu_item("/exec.php", "Command Prompt");?>
							<?=output_menu_item("/edit.php", "Edit File");?>
							<?=output_menu_item("/diag_defaults.php", "Factory Defaults");?>
							<?=output_menu_item("/halt.php", "Halt System");?>
							<?=output_menu_item("/diag_ping.php", "Ping");?>
							<?=output_menu_item("/diag_system_pftop.php", "pfTOP");?>
							<?=output_menu_item("/reboot.php", "Reboot");?>
							<?=output_menu_item("/diag_routes.php", "Routes");?>
							<?=output_menu_item("/diag_dump_states.php", "States");?>
							<?=output_menu_item("/diag_system_activity.php", "System Activity");?>
							<?=output_menu_item("/diag_traceroute.php", "Traceroute");?>
							<?=output_menu_item("/diag_packet_capture.php", "Packet Capture");?>
							<?php echo return_ext_menu("Diagnostics"); ?>
							<?php if(isset($config['system']['developer'])): ?>
							<li><hr width="80%"/></li>
							<?=output_menu_item("/restart_httpd.php", "Restart HTTPD");?>							
							<?php endif; ?>
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

function output_menu_item($url, $name) {
	if (isAllowedPage($url))
		echo "<li><a href=\"{$url}\" class=\"navlnk\">{$name}</a></li>\n";
}
	
?>

<p class="pgtitle"><?=genhtmltitle($pgtitle);?></font></p>

<?php
	$pgtitle_output = true;
?>