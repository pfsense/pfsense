<?php
// On the page, add in like so:
//   $shortcut_section = "relayd";

$shortcuts['relayd'] = array();
$shortcuts['relayd']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd']['status'] = "status_lb_pool.php";
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['relayd-virtualservers'] = array();
$shortcuts['relayd-virtualservers']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd-virtualservers']['status'] = "status_lb_vs.php";
$shortcuts['relayd-virtualservers']['service'] = "relayd";

$shortcuts['captiveportal'] = array();
$shortcuts['captiveportal']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal']['status'] = "status_captiveportal.php";
//$shortcuts['captiveportal']['service'] = "captiveportal";

$shortcuts['captiveportal-vouchers'] = array();
$shortcuts['captiveportal-vouchers']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal-vouchers']['status'] = "status_captiveportal_vouchers.php";
//$shortcuts['captiveportal-vouchers']['service'] = "captiveportal";

$shortcuts['dhcp'] = array();
$shortcuts['dhcp']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp']['status'] = "status_dhcp_leases.php";
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['dhcp6'] = array();
$shortcuts['dhcp6']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp6']['status'] = "status_dhcpv6_leases.php";


$shortcuts['ipsec'] = array();
$shortcuts['ipsec']['log'] = "diag_logs_ipsec.php";
$shortcuts['ipsec']['status'] = "diag_ipsec.php";
$shortcuts['ipsec']['service'] = "racoon";

$shortcuts['openvpn'] = array();
$shortcuts['openvpn']['log'] = "diag_logs_openvpn.php";
$shortcuts['openvpn']['status'] = "status_openvpn.php";
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['firewall'] = array();
$shortcuts['firewall']['log'] = "diag_logs_filter.php";
$shortcuts['firewall']['status'] = "status_filter_reload.php";

$shortcuts['routing'] = array();
$shortcuts['routing']['log'] = "diag_logs_routing.php";
$shortcuts['routing']['status'] = "diag_routes.php";

$shortcuts['gateways'] = array();
$shortcuts['gateways']['log'] = "diag_logs_gateways.php";
$shortcuts['gateways']['status'] = "status_gateways.php";

$shortcuts['gateway-groups'] = array();
$shortcuts['gateway-groups']['log'] = "diag_logs_gateways.php";
$shortcuts['gateway-groups']['status'] = "status_gateway_groups.php";

$shortcuts['interfaces'] = array();
$shortcuts['interfaces']['status'] = "status_interfaces.php";

$shortcuts['trafficshaper'] = array();
$shortcuts['trafficshaper']['status'] = "status_queues.php";

$shortcuts['resolver']['log'] = array();
$shortcuts['resolver']['log'] = "diag_logs_resolver.php";
$shortcuts['resolver']['service'] = "dnsmasq";

$shortcuts['wireless']['log'] = array();
$shortcuts['wireless']['log'] = "diag_logs_wireless.php";
$shortcuts['wireless']['status'] = "status_wireless.php";

$shortcuts['ntp'] = array();
$shortcuts['ntp']['log'] = "diag_logs_ntpd.php";
$shortcuts['ntp']['status'] = "status_ntpd.php";
$shortcuts['ntp']['service'] = "ntpd";

$shortcuts['upnp'] = array();
$shortcuts['upnp']['status'] = "status_upnp.php";
$shortcuts['upnp']['service'] = "miniupnpd";

$shortcuts['pptps'] = array();
$shortcuts['pptps']['log'] = "diag_logs_vpn.php";

$shortcuts['pppoes'] = array();
$shortcuts['pppoes']['log'] = "diag_logs_vpn.php?vpntype=poes";

$shortcuts['l2tps'] = array();
$shortcuts['l2tps']['log'] = "diag_logs_vpn.php?vpntype=l2tp";

$shortcuts['carp'] = array();
$shortcuts['carp']['status'] = "carp_status.php";
?>