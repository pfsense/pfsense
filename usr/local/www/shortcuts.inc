<?php
// On the page, add in like so:
//   $shortcut_section = "relayd";

$shortcuts['relayd'] = array();
$shortcuts['relayd']['main'] = "load_balancer_pool.php";
$shortcuts['relayd']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd']['status'] = "status_lb_pool.php";
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['relayd-virtualservers'] = array();
$shortcuts['relayd-virtualservers']['main'] = "load_balancer_virtual_server.php";
$shortcuts['relayd-virtualservers']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd-virtualservers']['status'] = "status_lb_vs.php";
$shortcuts['relayd-virtualservers']['service'] = "relayd";

$shortcuts['captiveportal'] = array();
$shortcuts['captiveportal']['main'] = "services_captiveportal_zones.php";
$shortcuts['captiveportal']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal']['status'] = "status_captiveportal.php";
$shortcuts['captiveportal']['service'] = "captiveportal";

$shortcuts['captiveportal-vouchers'] = array();
$shortcuts['captiveportal-vouchers']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal-vouchers']['status'] = "status_captiveportal_vouchers.php";
$shortcuts['captiveportal-vouchers']['service'] = "captiveportal";

$shortcuts['dhcp'] = array();
$shortcuts['dhcp']['main'] = "services_dhcp.php";
$shortcuts['dhcp']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp']['status'] = "status_dhcp_leases.php";
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['dhcp6'] = array();
$shortcuts['dhcp6']['main'] = "services_dhcpv6.php";
$shortcuts['dhcp6']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp6']['status'] = "status_dhcpv6_leases.php";


$shortcuts['ipsec'] = array();
$shortcuts['ipsec']['main'] = "vpn_ipsec.php";
$shortcuts['ipsec']['log'] = "diag_logs_ipsec.php";
$shortcuts['ipsec']['status'] = "diag_ipsec.php";
$shortcuts['ipsec']['service'] = "racoon";

$shortcuts['openvpn'] = array();
$shortcuts['openvpn']['main'] = "vpn_openvpn_server.php";
$shortcuts['openvpn']['log'] = "diag_logs_openvpn.php";
$shortcuts['openvpn']['status'] = "status_openvpn.php";
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['firewall'] = array();
$shortcuts['firewall']['main'] = "firewall_rules.php";
$shortcuts['firewall']['log'] = "diag_logs_filter.php";
$shortcuts['firewall']['status'] = "status_filter_reload.php";

$shortcuts['routing'] = array();
$shortcuts['routing']['main'] = "system_routes.php";
$shortcuts['routing']['log'] = "diag_logs_routing.php";
$shortcuts['routing']['status'] = "diag_routes.php";

$shortcuts['gateways'] = array();
$shortcuts['gateways']['main'] = "system_gateways.php";
$shortcuts['gateways']['log'] = "diag_logs_gateways.php";
$shortcuts['gateways']['status'] = "status_gateways.php";

$shortcuts['gateway-groups'] = array();
$shortcuts['gateway-groups']['main'] = "system_gateway_groups.php";
$shortcuts['gateway-groups']['log'] = "diag_logs_gateways.php";
$shortcuts['gateway-groups']['status'] = "status_gateway_groups.php";

$shortcuts['interfaces'] = array();
$shortcuts['interfaces']['main'] = "interfaces_assign.php";
$shortcuts['interfaces']['status'] = "status_interfaces.php";

$shortcuts['trafficshaper'] = array();
$shortcuts['trafficshaper']['main'] = "firewall_shaper.php";
$shortcuts['trafficshaper']['status'] = "status_queues.php";

$shortcuts['resolver'] = array();
$shortcuts['resolver']['main'] = "services_dnsmasq.php";
$shortcuts['resolver']['log'] = "diag_logs_resolver.php";
$shortcuts['resolver']['service'] = "dnsmasq";

$shortcuts['wireless'] = array();
$shortcuts['wireless']['main'] = "interfaces_wireless.php";
$shortcuts['wireless']['log'] = "diag_logs_wireless.php";
$shortcuts['wireless']['status'] = "status_wireless.php";

$shortcuts['ntp'] = array();
$shortcuts['ntp']['main'] = "services_ntpd.php";
$shortcuts['ntp']['log'] = "diag_logs_ntpd.php";
$shortcuts['ntp']['status'] = "status_ntpd.php";
$shortcuts['ntp']['service'] = "ntpd";

$shortcuts['upnp'] = array();
$shortcuts['upnp']['main'] = "pkg_edit.php?xml=miniupnpd.xml&id=0";
$shortcuts['upnp']['status'] = "status_upnp.php";
$shortcuts['upnp']['service'] = "miniupnpd";

$shortcuts['pptps'] = array();
$shortcuts['pptps']['main'] = "vpn_pptp.php";
$shortcuts['pptps']['log'] = "diag_logs_vpn.php";

$shortcuts['pppoes'] = array();
$shortcuts['pppoes']['main'] = "vpn_pppoe.php";
$shortcuts['pppoes']['log'] = "diag_logs_vpn.php?vpntype=poes";

$shortcuts['l2tps'] = array();
$shortcuts['l2tps']['main'] = "vpn_l2tp.php";
$shortcuts['l2tps']['log'] = "diag_logs_vpn.php?vpntype=l2tp";

$shortcuts['carp'] = array();
$shortcuts['carp']['main'] = "system_hasync.php";
$shortcuts['carp']['status'] = "carp_status.php";
?>