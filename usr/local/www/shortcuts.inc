<?php
$shortcuts['relayd']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd']['status'] = "status_lb_pool.php";
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['relayd-virtualservers']['log'] = "diag_logs_relayd.php";
$shortcuts['relayd-virtualservers']['status'] = "status_lb_vs.php";
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['captiveportal']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal']['status'] = "status_captiveportal.php";
$shortcuts['captiveportal']['service'] = "captiveportal";
$shortcuts['captiveportal-vouchers']['log'] = "diag_logs_auth.php";
$shortcuts['captiveportal-vouchers']['status'] = "status_captiveportal_vouchers.php";
$shortcuts['captiveportal-vouchers']['service'] = "captiveportal";

$shortcuts['dhcp']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp']['status'] = "status_dhcp_leases.php";
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['dhcp6']['log'] = "diag_logs_dhcp.php";
$shortcuts['dhcp6']['status'] = "status_dhcpv6_leases.php";

$shortcuts['ipsec']['log'] = "diag_logs_ipsec.php";
$shortcuts['ipsec']['status'] = "diag_ipsec.php";
$shortcuts['ipsec']['service'] = "racoon";

$shortcuts['openvpn']['log'] = "diag_logs_openvpn.php";
$shortcuts['openvpn']['status'] = "status_openvpn.php";
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['firewall']['log'] = "diag_logs_filter.php";
$shortcuts['firewall']['status'] = "status_filter_reload.php";

$shortcuts['routing']['log'] = "diag_logs_routing.php";
$shortcuts['routing']['status'] = "diag_routes.php";

$shortcuts['gateways']['log'] = "diag_logs_gateways.php";
$shortcuts['gateways']['status'] = "status_gateways.php";
$shortcuts['gateway-groups']['log'] = "diag_logs_gateways.php";
$shortcuts['gateway-groups']['status'] = "status_gateway_groups.php";

$shortcuts['interfaces']['status'] = "status_interfaces.php";

$shortcuts['trafficshaper']['status'] = "status_queues.php";

$shortcuts['resolver']['log'] = "diag_logs_resolver.php";
$shortcuts['resolver']['service'] = "dnsmasq";

$shortcuts['wireless']['log'] = "diag_logs_wireless.php";
$shortcuts['wireless']['status'] = "status_wireless.php";

$shortcuts['ntp']['log'] = "diag_logs_ntpd.php";
$shortcuts['ntp']['status'] = "status_ntpd.php";
$shortcuts['ntp']['service'] = "ntpd";

$shortcuts['upnp']['status'] = "status_upnp.php";
$shortcuts['upnp']['service'] = "miniupnpd";

$shortcuts['pptps']['log'] = "diag_logs_vpn.php";

$shortcuts['pppoes']['log'] = "diag_logs_vpn.php?vpntype=poes";

$shortcuts['l2tps']['log'] = "diag_logs_vpn.php?vpntype=l2tp";

$shortcuts['carp']['status'] = "carp_status.php";


/*
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_filemanager.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_hostname.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_hostname_edit.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_ip.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_ip_edit.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_mac.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_mac_edit.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_vouchers.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_vouchers_edit.php
$logurl = "diag_logs_auth.php"; - usr/local/www/services_captiveportal_zones_edit.php
$logurl = "diag_logs_dhcp.php"; - usr/local/www/services_dhcp.php
$logurl = "diag_logs_dhcp.php"; - usr/local/www/services_dhcp_edit.php
$logurl = "diag_logs_dhcp.php"; - usr/local/www/services_dhcpv6.php
$logurl = "diag_logs_dhcp.php"; - usr/local/www/services_dhcpv6_edit.php
$logurl = "diag_logs_filter.php"; - usr/local/www/firewall_rules.php
$logurl = "diag_logs_filter.php"; - usr/local/www/firewall_rules_edit.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec_keys.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec_keys_edit.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec_mobile.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec_phase1.php
$logurl = "diag_logs_ipsec.php"; - usr/local/www/vpn_ipsec_phase2.php
$logurl = "diag_logs_openvpn.php"; - usr/local/www/vpn_openvpn_client.php
$logurl = "diag_logs_openvpn.php"; - usr/local/www/vpn_openvpn_csc.php
$logurl = "diag_logs_openvpn.php"; - usr/local/www/vpn_openvpn_server.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_monitor.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_monitor_edit.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_pool.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_pool_edit.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_relay_action.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_relay_action_edit.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_relay_protocol.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_relay_protocol_edit.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_setting.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_virtual_server.php
$logurl = "diag_logs_relayd.php"; - usr/local/www/load_balancer_virtual_server_edit.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec_keys.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec_keys_edit.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec_mobile.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec_phase1.php
$statusurl = "diag_ipsec.php"; - usr/local/www/vpn_ipsec_phase2.php
$statusurl = "diag_routes.php"; - usr/local/www/system_routes.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_filemanager.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_hostname.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_hostname_edit.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_ip.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_ip_edit.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_mac.php
$statusurl = "status_captiveportal.php"; - usr/local/www/services_captiveportal_mac_edit.php
$statusurl = "status_captiveportal_vouchers.php"; - usr/local/www/services_captiveportal_vouchers.php
$statusurl = "status_captiveportal_vouchers.php"; - usr/local/www/services_captiveportal_vouchers_edit.php
$statusurl = "status_captiveportal_zones_edit.php"; - usr/local/www/services_captiveportal_zones_edit.php
$statusurl = "status_dhcp_leases.php"; - usr/local/www/services_dhcp.php
$statusurl = "status_dhcp_leases.php"; - usr/local/www/services_dhcp_edit.php
$statusurl = "status_dhcpv6_leases.php"; - usr/local/www/services_dhcpv6.php
$statusurl = "status_dhcpv6_leases.php"; - usr/local/www/services_dhcpv6_edit.php
$statusurl = "status_filter_reload.php"; - usr/local/www/firewall_rules.php
$statusurl = "status_filter_reload.php"; - usr/local/www/firewall_rules_edit.php
$statusurl = "status_gateway_groups.php"; - usr/local/www/system_gateway_groups.php
$statusurl = "status_gateway_groups.php"; - usr/local/www/system_gateway_groups_edit.php
$statusurl = "status_gateways.php"; - usr/local/www/system_gateways.php
$statusurl = "status_gateways.php"; - usr/local/www/system_gateways_edit.php
$statusurl = "status_interfaces.php"; - usr/local/www/interfaces.php
$statusurl = "status_interfaces.php"; - usr/local/www/interfaces_assign.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_monitor.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_monitor_edit.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_pool.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_pool_edit.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_relay_action.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_relay_action_edit.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_relay_protocol.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_relay_protocol_edit.php
$statusurl = "status_lb_pool.php"; - usr/local/www/load_balancer_setting.php
$statusurl = "status_lb_vs.php"; - usr/local/www/load_balancer_virtual_server.php
$statusurl = "status_lb_vs.php"; - usr/local/www/load_balancer_virtual_server_edit.php
$statusurl = "status_openvpn.php"; - usr/local/www/vpn_openvpn_client.php
$statusurl = "status_openvpn.php"; - usr/local/www/vpn_openvpn_csc.php
$statusurl = "status_openvpn.php"; - usr/local/www/vpn_openvpn_server.php
$statusurl = "status_queues.php"; - usr/local/www/firewall_shaper.php
$statusurl = "status_queues.php"; - usr/local/www/firewall_shaper_layer7.php
$statusurl = "status_queues.php"; - usr/local/www/firewall_shaper_queues.php
$statusurl = "status_queues.php"; - usr/local/www/firewall_shaper_vinterface.php
$statusurl = "status_queues.php"; - usr/local/www/firewall_shaper_wizards.php

*/
?>