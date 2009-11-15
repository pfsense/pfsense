<?php
/* Redirector for Contextual Help System
 * (c) 2009 Jim Pingle <jimp@pfsense.org>
 *
 */

/* Define hash of jumpto url maps */
$helppages = array(
  'index.php' => 'http://doc.pfsense.org/index.php/Dashboard_package',
  'license.php' => 'http://www.pfsense.org/index.php?option=com_content&task=view&id=42&Itemid=62',
  'miniupnpd.xml' => 'http://doc.pfsense.org/index.php/What_is_UPNP%3F'
);

/*
Filename list (2.0 box as of 2009-11-15), entries with help maps should be
removed. Also, files which cannot normally be accessed by a user can
be removed.(e.g. xmlrpc.php)

Below this is a list of .xml files from built-in and add-on packages

bandwidth_by_ip.php
carp_status.php
diag_arp.php
diag_backup.php
diag_confbak.php
diag_defaults.php
diag_dhcp_leases.php
diag_dns.php
diag_dump_states.php
diag_ipsec.php
diag_ipsec_sad.php
diag_ipsec_spd.php
diag_logs.php
diag_logs_auth.php
diag_logs_dhcp.php
diag_logs_filter.php
diag_logs_filter_dynamic.php
diag_logs_filter_summary.php
diag_logs_ipsec.php
diag_logs_ntpd.php
diag_logs_openvpn.php
diag_logs_ppp.php
diag_logs_relayd.php
diag_logs_settings.php
diag_logs_slbd.php
diag_logs_vpn.php
diag_nanobsd.php
diag_packet_capture.php
diag_patterns.php
diag_ping.php
diag_pkglogs.php
diag_resetstate.php
diag_routes.php
diag_showbogons.php
diag_system_activity.php
diag_system_pftop.php
diag_traceroute.php
easyrule.php
edit.php
exec.php
exec_raw.php
firewall_aliases.php
firewall_aliases_edit.php
firewall_aliases_import.php
firewall_nat.php
firewall_nat_1to1.php
firewall_nat_1to1_edit.php
firewall_nat_edit.php
firewall_nat_out.php
firewall_nat_out_edit.php
firewall_nat_server.php
firewall_nat_server_edit.php
firewall_rules.php
firewall_rules_edit.php
firewall_rules_schedule_logic.php
firewall_schedule.php
firewall_schedule_edit.php
firewall_shaper.php
firewall_shaper_edit.php
firewall_shaper_layer7.php
firewall_shaper_queues.php
firewall_shaper_queues_edit.php
firewall_shaper_vinterface.php
firewall_shaper_wizards.php
firewall_virtual_ip.php
firewall_virtual_ip_edit.php
getstats.php
graph.php
graph_cpu.php
halt.php
headjs.php
ifstats.php
interfaces.php
interfaces_assign.php
interfaces_bridge.php
interfaces_bridge_edit.php
interfaces_gif.php
interfaces_gif_edit.php
interfaces_gre.php
interfaces_gre_edit.php
interfaces_groups.php
interfaces_groups_edit.php
interfaces_lagg.php
interfaces_lagg_edit.php
interfaces_ppp.php
interfaces_ppp_edit.php
interfaces_qinq.php
interfaces_qinq_edit.php
interfaces_vlan.php
interfaces_vlan_edit.php
interfaces_wlan_scan.php
load_balancer_monitor.php
load_balancer_monitor_edit.php
load_balancer_pool.php
load_balancer_pool_edit.php
load_balancer_relay_action.php
load_balancer_relay_action_edit.php
load_balancer_relay_protocol.php
load_balancer_relay_protocol_edit.php
load_balancer_virtual_server.php
load_balancer_virtual_server_edit.php
pkg.php
pkg_edit.php
pkg_mgr.php
pkg_mgr_install.php
pkg_mgr_installed.php
pkg_mgr_settings.php
preload.php
progress.php
reboot.php
restart_httpd.php
services_captiveportal.php
services_captiveportal_filemanager.php
services_captiveportal_ip.php
services_captiveportal_ip_edit.php
services_captiveportal_mac.php
services_captiveportal_mac_edit.php
services_captiveportal_users.php
services_captiveportal_users_edit.php
services_captiveportal_vouchers.php
services_captiveportal_vouchers_edit.php
services_dhcp.php
services_dhcp_edit.php
services_dhcp_relay.php
services_dnsmasq.php
services_dnsmasq_domainoverride_edit.php
services_dnsmasq_edit.php
services_dyndns.php
services_dyndns_edit.php
services_igmpproxy.php
services_igmpproxy_edit.php
services_proxyarp.php
services_proxyarp_edit.php
services_rfc2136.php
services_rfc2136_edit.php
services_snmp.php
services_usermanager.php
services_wol.php
services_wol_edit.php
stats.php
status.php
status_captiveportal.php
status_captiveportal_test.php
status_captiveportal_voucher_rolls.php
status_captiveportal_vouchers.php
status_filter_reload.php
status_gateway_groups.php
status_gateways.php
status_graph.php
status_graph_cpu.php
status_interfaces.php
status_openvpn.php
status_queues.php
status_rrd_graph.php
status_rrd_graph_img.php
status_rrd_graph_settings.php
status_services.php
status_slbd_pool.php
status_slbd_vs.php
status_upnp.php
status_wireless.php
system.php
system_advanced.php
system_advanced_admin.php
system_advanced_create_certs.php
system_advanced_firewall.php
system_advanced_misc.php
system_advanced_network.php
system_advanced_notifications.php
system_advanced_sysctl.php
system_authservers.php
system_camanager.php
system_certmanager.php
system_firmware.php
system_firmware_auto.php
system_firmware_check.php
system_firmware_settings.php
system_gateway_groups.php
system_gateway_groups_edit.php
system_gateways.php
system_gateways_edit.php
system_gateways_settings.php
system_groupmanager.php
system_groupmanager_addprivs.php
system_routes.php
system_routes_edit.php
system_usermanager.php
system_usermanager_addcert.php
system_usermanager_addprivs.php
system_usermanager_settings.php
system_usermanager_settings_ldapacpicker.php
system_usermanager_settings_test.php
upload_progress.php
uploadconfig.php
vpn_ipsec.php
vpn_ipsec_ca.php
vpn_ipsec_ca_edit.php
vpn_ipsec_ca_edit_create_cert.php
vpn_ipsec_edit.php
vpn_ipsec_keys.php
vpn_ipsec_keys_edit.php
vpn_ipsec_mobile.php
vpn_ipsec_phase1.php
vpn_ipsec_phase2.php
vpn_l2tp.php
vpn_l2tp_users.php
vpn_l2tp_users_edit.php
vpn_openvpn.php
vpn_openvpn_ccd.php
vpn_openvpn_ccd_edit.php
vpn_openvpn_cli.php
vpn_openvpn_cli_edit.php
vpn_openvpn_client.php
vpn_openvpn_create_certs.php
vpn_openvpn_crl.php
vpn_openvpn_crl_edit.php
vpn_openvpn_csc.php
vpn_openvpn_server.php
vpn_openvpn_srv.php
vpn_openvpn_srv_edit.php
vpn_pppoe.php
vpn_pppoe_users.php
vpn_pppoe_users_edit.php
vpn_pptp.php
vpn_pptp_users.php
vpn_pptp_users_edit.php
wizard.php

carp.xml
carp_settings.xml
olsrd.xml
openntpd.xml
sasyncd.xml

Package .xml files (some may not be needed)
anyterm.xml
apache_mod_security.xml
apache_mod_security_settings.xml
arping.xml
arpwatch.xml
assp.xml
authng.xml
authng_wizard.xml
autoconfigbackup.xml
avahi.xml
backup.xml
bandwidthd.xml
blinkled.xml
bsdstats.xml
clamav.xml
clamsmtp.xml
config.xml
config.xml
cron.xml
darkstat.xml
dashboard.xml
ddns.xml
denyhosts.xml
developers.xml
diag_new_states.xml
dialplan.default.xml
dialplan.default.xml
dialplan.public.xml
dialplan.public.xml
dnsblacklist.xml
doorman.xml
doormanusers.xml
dspam.xml
dspam_alerts.xml
dspam_wizard.xml
dyntables.xml
fit123.xml
freenas.xml
freeradius.xml
freeradiusclients.xml
freeradiussettings.xml
freeswitch.xml
freeswitch.xml
freeswitch_modules.xml
frickin.xml
haproxy.xml
havp.xml
havp.xml
havp_avset.xml
havp_blacklist.xml
havp_fscan.xml
havp_trans_exclude.xml
havp_whitelist.xml
hula.xml
ifdepd.xml
ifstated.xml
igmpproxy.xml
imspector.xml
iperf.xml
iperfserver.xml
jail_template.xml
jailctl.xml
jailctl.xml
jailctl_defaults.xml
jailctl_settings.xml
lcdproc.xml
lcdproc_screens.xml
lightsquid.xml
messages_de.xml
messages_en.xml
miniupnpd.xml
mtr-nox11.xml
netio-newpkg.xml
netio.xml
netioserver-newpkg.xml
netioserver.xml
new_zone_wizard.xml
nmap.xml
notes.xml
nrpe2.xml
ntop.xml
nut.xml
onatproto.xml
open-vm-tools.xml
openbgpd.xml
openbgpd_groups.xml
openbgpd_neighbors.xml
openvpn-client-export.xml
ovpnenhance.xml
p3scan-pf-emer.xml
p3scan-pf-emer.xml
p3scan-pf-msg.xml
p3scan-pf-msg.xml
p3scan-pf-spam.xml
p3scan-pf-spam.xml
p3scan-pf-transex.xml
p3scan-pf-vir.xml
p3scan-pf-vir.xml
p3scan-pf.xml
p3scan-pf.xml
p3scan.xml
per-user-bandwidth-distribution.xml
pfflowd.xml
pfstat.xml
phpmrss.xml
phpservice.xml
phpsysinfo.xml
postfix.xml
powerdns.xml
pubkey.xml
pure-ftpd.xml
pure-ftpdsettings.xml
quagga.xml
rate.xml
routed.xml
sample.xml
sample_ui.xml
sample_ui2.xml
sassassin.xml
sassassin_bl.xml
sassassin_wl.xml
shellcmd.xml
siproxd.xml
siproxdusers.xml
snort.xml
snort.xml
snort.xml
snort_advanced.xml
snort_advanced.xml
snort_define_servers.xml
snort_define_servers.xml
snort_threshold.xml
snort_threshold.xml
snort_whitelist.xml
snort_whitelist.xml
spamd.xml
spamd_outlook.xml
spamd_settings.xml
spamd_whitelist.xml
squid.xml
squid.xml
squid.xml
squidGuard.xml
squid_auth.xml
squid_auth.xml
squid_auth.xml
squid_cache.xml
squid_cache.xml
squid_cache.xml
squid_extauth.xml
squid_extauth.xml
squid_nac.xml
squid_nac.xml
squid_nac.xml
squid_ng.xml
squid_ng.xml
squid_traffic.xml
squid_traffic.xml
squid_traffic.xml
squid_upstream.xml
squid_upstream.xml
squid_upstream.xml
squid_users.xml
squid_users.xml
squid_users.xml
squidguard.xml
squidguard_acl.xml
squidguard_default.xml
squidguard_dest.xml
squidguard_log.xml
squidguard_rewr.xml
squidguard_time.xml
sshterm.xml
stunnel.xml
stunnel_certs.xml
test_package.xml
tftp.xml
tinydns.xml
tinydns_domains.xml
tinydns_sync.xml
upclient.xml
viralator.xml
vnstat.xml
widentd.xml
widget-havp.xml
widget-snort.xml
zabbix-agent.xml

 */

$pagename = "";
/* Check for parameter "page". */
if ($_GET && isset($_GET['page'])) {
	$pagename = $_GET['page'];
}

/* If "page" is not found, check referring URL */
if (empty($pagename)) {
	/* Attempt to parse out filename */
	$uri_split = "";
	preg_match("/\/(.*)\?(.*)/", $_SERVER["HTTP_REFERER"], $uri_split);

	/* If there was no match, there were no parameters, just grab the filename
		Otherwise, use the matched filename from above. */
	if (empty($uri_split[0])) {
		$pagename = ltrim($_SERVER["HTTP_REFERER"], '/');
	} else {
		$pagename = $uri_split[1];
	}

	/* If the page name is still empty, the user must have requested / (index.php) */
	if (empty($pagename)) {
		$pagename = "index.php";
	}

	/* If the filename is pkg_edit.php or wizard.php, reparse looking
		for the .xml filename */
	if (($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
		$param_split = explode('&', $uri_split[2]);
		foreach ($param_split as $param) {
			if (substr($param, 0, 4) == "xml=") {
				$xmlfile = explode('=', $param);
				$pagename = $xmlfile[1];
			}
		}
	}
}

/* Using the derived page name, attempt to find in the URL mapping hash */
if (array_key_exists($pagename, $helppages)) {
	$helppage = $helppages[$pagename];
}

/* If we haven't determined a proper page, use a generic help page
   stating that a given page does not have help yet. */

if (empty($helppage)) {
	$helppage = "http://doc.pfsense.org/index.php/No_Help_Found";

}

/* Redirect to help page. */
header("Location: {$helppage}");

?>

