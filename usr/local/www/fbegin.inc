<?php
require_once("notices.inc");
/* $Id$ */
	function return_ext_menu($section) {
		global $config;
		$htmltext = "";
		if($config['installedpackages']['menu'] <> "") {
			foreach($config['installedpackages']['menu'] as $menuitem) {
				if($menuitem['section'] != $section) continue;
				if($menuitem['url'] <> "") {
					$description = str_replace('$myurl', getenv("HTTP_HOST"), $menuitem['url']);
				} else {
					$description = '/pkg.php?xml=' . $menuitem['configfile'];
				}
				$htmltext .= '<li><a href="' . $description . ' "class="navlnk">' . $menuitem['name'] . '</a></li>' . "\n";
			}
		}
		return $htmltext;
	}
?>

<div id="wrapper">

	<div id="header">
		<div id="header-left"></div>
		<div id="header-right">
			<span class="alert">
<?
			/**
			 * Place code here for alert system, perhaps alerts from
			 * pfsense.com for patches, security updates, etc...
			 *
			 */
?>
				&nbsp;
			</span>
			<span class="normal">
				<span class="left">webConfigurator</span>
				<span class="right"><?=$config['system']['hostname'] . "." . $config['system']['domain'];?></span>
			</span>
		</div>
	</div> <!-- Header DIV -->
	<div id="content">
		<div id="left">
			<div id="navigation">
				<ul id="menu">
					<li class="firstdrop">
						<div>System</div>
						<ul class="subdrop">
							<li><a href="/system_advanced.php" class="navlnk">Advanced</a></li>
							<li><a href="/system_firmware_check.php" class="navlnk">Firmware</a></li>
							<li><a href="/system.php" class="navlnk">General Setup</a></li>
						<?php if($g['platform'] == "pfSense"): ?>
							<li><a href="/pkg_mgr.php" class="navlnk">Packages</a></li>
						<?php endif ?>
							<li><a href="/system_routes.php" class="navlnk">Static routes</a></li>
						</ul>
					</li>
					<li class="drop">
						<div>Interfaces</div>
						<ul class="subdrop">
							<?php if (!isset($config['system']['webgui']['noassigninterfaces'])): ?><li><a href="/interfaces_assign.php" class="navlnks">(assign)</a></li><?php endif; ?>
							<li><a href="/interfaces_wan.php" class="navlnk">WAN</a></li>							
							<li><a href="/interfaces_lan.php" class="navlnk">LAN</a></li>
							<?php for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++): if (!isset($config['interfaces']['opt' . $i]['ovpn'])): ?>
							<li><a href="/interfaces_opt.php?index=<?=$i;?>" class="navlnk"><?=htmlspecialchars($config['interfaces']['opt' . $i]['descr']);?></a></li>
							<?php endif; endfor; ?>
							<?php echo return_ext_menu("Interfaces"); ?>							
						</ul>
					</li>
					<li class="drop">
						<div>Firewall</div>
						<ul class="subdrop">
							<li><a href="/firewall_aliases.php" class="navlnk">Aliases</a></li>
							<li><a href="/firewall_nat.php" class="navlnk">NAT</a></li>
							<li><a href="/firewall_rules.php" class="navlnk">Rules</a></li>              
							<li><a href="/firewall_shaper.php" class="navlnk">Traffic Shaper</a></li>
							<li><a href="/firewall_virtual_ip.php" class="navlnk">Virtual IPs</a></li>
							<?php echo return_ext_menu("Firewall"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>Services</div>
						<ul class="subdrop">
							<li><a href="/services_captiveportal.php" class="navlnk">Captive portal</a></li>
							<li><a href="/services_dnsmasq.php" class="navlnk">DNS forwarder</a></li>
							<li><a href="/services_dhcp_relay.php" class="navlnk">DHCP relay</a></li>
							<li><a href="/services_dhcp.php" class="navlnk">DHCP server</a></li>
							<li><a href="/services_dyndns.php" class="navlnk">Dynamic DNS</a></li>
							<li><a href="/services_snmp.php" class="navlnk">SNMP</a></li>
							<li><a href="/services_wol.php" class="navlnk">Wake on LAN</a></li>
							<?php echo return_ext_menu("Services"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>VPN</div>
						<ul class="subdrop">
							<li><a href="/vpn_ipsec.php" class="navlnk">IPsec</a></li>
							<li><a href="/vpn_pptp.php" class="navlnk">PPTP</a></li>
							<?php echo return_ext_menu("VPN"); ?>
						</ul>
					</li>
					<li class="drop">
						<div>Status</div>
						<ul class="subdrop">
							<?php if (isset($config['captiveportal']['enable'])): ?>
							<li><a href="/status_captiveportal.php" class="navlnk">Captive portal</a></li>
							<?php endif; ?>
							<li><a href="/carp_status.php" class="navlnk">CARP (failover)</a></li>		
							<li><a href="/diag_dhcp_leases.php" class="navlnk">DHCP leases</a></li>
							<li><a href="/status_interfaces.php" class="navlnk">Interfaces</a></li>
							<li><a href="/diag_ipsec_sad.php" class="navlnk">IPsec</a></li>
							<li><a href="/diag_pkglogs.php" class="navlnk">Package logs</a></li>
							<li><a href="/status_queues.php" class="navlnk">Queues</a></li>
							<li><a href="/status_services.php" class="navlnk">Services</a></li>
							<li><a href="/index.php" class="navlnk">System</a></li>
							<li><a href="/diag_logs.php" class="navlnk">System logs</a></li>
							<li><a href="/status_graph.php" class="navlnk">Traffic graph</a></li>
							<?php $i = 0; $ifdescrs = array();
							if (is_array($config['interfaces']['wan']['wireless']) &&
									(strstr($config['interfaces']['wan']['if'], "wi") || strstr($config['interfaces']['wan']['if'], "ath")))
									$ifdescrs['wan'] = 'WAN';
									
							if (is_array($config['interfaces']['lan']['wireless']) &&
									(strstr($config['interfaces']['lan']['if'], "wi") || strstr($config['interfaces']['lan']['if'], "ath")))
									$ifdescrs['lan'] = 'LAN';
							
							for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
								if (is_array($config['interfaces']['opt' . $j]['wireless']) &&
									isset($config['interfaces']['opt' . $j]['enable']) &&
									(strstr($config['interfaces']['opt' . $j]['if'], "wi") || strstr($config['interfaces']['opt' . $j]['if'], "ath")))
									$ifdescrs['opt' . $j] = $config['interfaces']['opt' . $j]['descr'];
							}
							if (count($ifdescrs) > 0): ?>	      
							<li><a href="/status_wireless.php" class="navlnk">Wireless</a></li>
							<?php endif; ?>
							<?php echo return_ext_menu("Status"); ?>							
						</ul>
					</li>
					<li class="lastdrop">
						<div>Diagnostics</div>
						<ul id="diag" class="subdrop">
							<li><a href="/diag_backup.php" class="navlnk">Backup/Restore</a></li>
							<li><a href="/exec.php" class="navlnk">Command Prompt</a></li>
							<li><a href="/edit.php" class="navlnk">Edit File</a></li>
							<li><a href="/diag_defaults.php" class="navlnk">Factory defaults </a></li>
							<li><a href="/halt.php" class="navlnk">Halt system</a></li>
							<li><a href="/diag_ping.php" class="navlnk">Ping</a></li>
							<li><a href="/reboot.php" class="navlnk">Reboot system</a></li>
							<li><a href="/diag_dump_states.php" class="navlnk">States</a></li>
							<?php echo return_ext_menu("Diagnostics"); ?>
							<?php if(isset($config['system']['developer'])): ?>
							<li><hr width="80%"/></li>
							<li><a href="/restart_httpd.php" class="navlnk">Restart HTTPD</a></li>
							<?php endif ?>
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
	
?>
