<script language="javascript">
<!--
var tri_open = "";
var tri_closed = "";

window.onload = preload;

function preload() {
	if (document.images) {
		tri_open = new Image(14,10);
		tri_closed = new Image(14,10);
		tri_open.src = "/tri_o.gif";
		tri_closed.src = "/tri_c.gif";
	}
}

function showhide(tspan, tri) {
	tspanel = document.getElementById(tspan);
	triel = document.getElementById(tri);
	if (tspanel.style.display == 'none') {
		tspanel.style.display = '';
		triel.src = "/tri_o.gif";
	} else {
		tspanel.style.display = 'none';
		triel.src = "/tri_c.gif";
	}
}
-->
</script>
<table width="750" border="0" cellspacing="0" cellpadding="2">
  <tr valign="bottom">
    <td width="150" height="65" align="center" valign="middle"> <strong><a href="/" target="_blank"><img src="/logo.gif" width="150" height="47" border="0"></a></strong></td>
    <td height="65" bgcolor="#7F3B00">
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	<tr><td align="left" valign="bottom"><span class="tfrtitle">&nbsp;webConfigurator</span></td>
	  <td align="right" valign="bottom">
	  <span class="hostname"><?=$config['system']['hostname'] . "." . $config['system']['domain'];?>&nbsp;</span>
	  </td></tr></table>
	</td>
  </tr>
  <tr valign="top">
    <td width="150" bgcolor="#000000">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
        <tr>
          <td><span class="navlnk"><font color="#FFFFFF"> <strong>System</strong>
              <br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/system.php" class="navlnk">General
              setup</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/system_routes.php" class="navlnk">Static
              routes</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/system_firmware.php" class="navlnk">Firmware</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/system_advanced.php" class="navlnk">Advanced</a><br>
              <strong>Interfaces</strong>
			  <?php if (!isset($config['system']['webgui']['noassigninterfaces'])): ?>
			  <a href="/interfaces_assign.php" class="navlnks">(assign)</a>
			  <?php endif; ?>
			  <br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/interfaces_lan.php" class="navlnk">LAN</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/interfaces_wan.php" class="navlnk">WAN</a><br>
			  <?php for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++): if (!isset($config['interfaces']['opt' . $i]['ovpn'])): ?>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/interfaces_opt.php?index=<?=$i;?>" class="navlnk"><?=htmlspecialchars($config['interfaces']['opt' . $i]['descr']);?></a><br>
			  <?php endif; endfor; ?>
              <strong>Firewall</strong><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/firewall_rules.php" class="navlnk">Rules</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/firewall_nat.php" class="navlnk">NAT</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/firewall_aliases.php" class="navlnk">Aliases</a><br>
              <strong>Services</strong><br>
			  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_dnsmasq.php" class="navlnk">DNS forwarder</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_dyndns.php" class="navlnk">Dynamic
              DNS</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_dhcp.php" class="navlnk">DHCP server</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_dhcp_relay.php" class="navlnk">DHCP relay</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_snmp.php" class="navlnk">SNMP</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_proxyarp.php" class="navlnk">Proxy ARP</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_captiveportal.php" class="navlnk">Captive portal</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/services_wol.php" class="navlnk">Wake on LAN</a><br>
              <strong>VPN</strong><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/vpn_ipsec.php" class="navlnk">IPsec</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/vpn_pptp.php" class="navlnk">PPTP</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/vpn_openvpn.php" class="navlnk">OpenVPN</a><br>
              <strong>Status</strong><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/index.php" class="navlnk">System</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/status_interfaces.php" class="navlnk">Interfaces</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/status_graph.php" class="navlnk">Traffic graph</a><br>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/status_wireless.php" class="navlnk">Wireless</a><br>
			  <?php if (isset($config['captiveportal']['enable'])): ?>
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/status_captiveportal.php" class="navlnk">Captive portal</a><br>
			  <?php endif; ?>
<?php
/* extensions section */
if (is_dir("{$g['www_path']}/ext")):
?>
              <strong>Extensions</strong><br>
<?php
$dh = @opendir("{$g['www_path']}/ext");
if ($dh) {
	while (($extd = readdir($dh)) !== false) {
		if (($extd === ".") || ($extd === ".."))
			continue;
		@include("{$g['www_path']}/ext/" . $extd . "/menu.inc");
	}
	closedir($dh);
}
endif;
?>
			  <?php if (isset($config['system']['webgui']['expanddiags']) || strstr($_SERVER['SCRIPT_FILENAME'], "diag_") || strstr($_SERVER['SCRIPT_FILENAME'], "reboot")): ?>
			  <a href="javascript:showhide('diag','tri_diag')"><img src="/tri_o.gif" id="tri_diag" width="14" height="10" border="0"></a><strong><a href="javascript:showhide('diag','tri_diag')" class="navlnk">Diagnostics</a></strong><br>
			  <span id="diag">
			  <?php else: ?>
			  <a href="javascript:showhide('diag','tri_diag')"><img src="/tri_c.gif" id="tri_diag" width="14" height="10" border="0"></a><strong><a href="javascript:showhide('diag','tri_diag')" class="navlnk">Diagnostics</a></strong><br>
			  <span id="diag" style="display: none">
			  <?php endif; ?>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_logs.php" class="navlnk">System
				  logs</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_dhcp_leases.php" class="navlnk">DHCP leases</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_ipsec_sad.php" class="navlnk">IPsec</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_ping.php" class="navlnk">Ping</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_resetstate.php" class="navlnk">Reset
				  state</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_backup.php" class="navlnk">Backup/Restore</a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/diag_defaults.php" class="navlnk">Factory
				  defaults </a><br>
				  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/reboot.php" class="navlnk">Reboot
				  system</a>
			  </span>
			  </font></span>
            </td>
        </tr></table></td>
    <td width="600"><table width="100%" border="0" cellpadding="10" cellspacing="0">
        <tr><td>
