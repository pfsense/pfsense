<?php
/*
	Redirector for Contextual Help System
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
 */

require_once("guiconfig.inc");

/* Define hash of jumpto url maps */

/* Links to categories could probably be more specific. */
$helppages = array(
	/* These pages are confirmed to work and have usable content */
	'index.php' => 'https://doc.pfsense.org/index.php/Dashboard',
	'license.php' => 'https://www.pfsense.org/about-pfsense/#legal',
	'miniupnpd.xml' => 'https://doc.pfsense.org/index.php/What_are_UPnP_and_NAT-PMP',
	'status_upnp.php' => 'https://doc.pfsense.org/index.php/What_are_UPnP_and_NAT-PMP',
	'firewall_virtual_ip.php' => 'https://doc.pfsense.org/index.php/What_are_Virtual_IP_Addresses',
	'firewall_virtual_ip_edit.php' => 'https://doc.pfsense.org/index.php/What_are_Virtual_IP_Addresses',
	'firewall_aliases.php' => 'https://doc.pfsense.org/index.php/Aliases',
	'firewall_aliases_edit.php' => 'https://doc.pfsense.org/index.php/Aliases',
	'firewall_aliases_import.php' => 'https://doc.pfsense.org/index.php/Aliases',
	'firewall_nat_out.php' => 'https://doc.pfsense.org/index.php/Outbound_NAT',
	'firewall_nat_out_edit.php' => 'https://doc.pfsense.org/index.php/Outbound_NAT',
	'firewall_rules.php' => 'https://doc.pfsense.org/index.php/Firewall_Rule_Basics',
	'firewall_rules_edit.php' => 'https://doc.pfsense.org/index.php/Firewall_Rule_Basics',
	'firewall_schedule.php' => 'https://doc.pfsense.org/index.php/Firewall_Rule_Schedules',
	'firewall_schedule_edit.php' => 'https://doc.pfsense.org/index.php/Firewall_Rule_Schedules',
	'interfaces_vlan.php' => 'https://doc.pfsense.org/index.php/VLAN_Trunking',
	'interfaces_vlan_edit.php' => 'https://doc.pfsense.org/index.php/VLAN_Trunking',
	'diag_routes.php' => 'https://doc.pfsense.org/index.php/Viewing_Routes',
	'diag_packet_capture.php' => 'https://doc.pfsense.org/index.php/Sniffers,_Packet_Capture',
	'diag_system_pftop.php' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage#pftop',
	'status_rrd_graph.php' => 'https://doc.pfsense.org/index.php/RRD_Graphs',
	'status_rrd_graph_img.php' => 'https://doc.pfsense.org/index.php/RRD_Graphs',
	'status_rrd_graph_settings.php' => 'https://doc.pfsense.org/index.php/RRD_Graphs',
	'firewall_nat.php' => 'https://doc.pfsense.org/index.php/How_can_I_forward_ports_with_pfSense',
	'firewall_nat_edit.php' => 'https://doc.pfsense.org/index.php/How_can_I_forward_ports_with_pfSense',
	'diag_arp.php' => 'https://doc.pfsense.org/index.php/ARP_Table',
	'diag_backup.php' => 'https://doc.pfsense.org/index.php/Configuration_Backup_and_Restore',
	'diag_confbak.php' => 'https://doc.pfsense.org/index.php/Configuration_History',
	'diag_defaults.php' => 'https://doc.pfsense.org/index.php/Factory_Defaults',
	'firewall_shaper.php' => 'https://doc.pfsense.org/index.php/Traffic_Shaping_Guide',
	'firewall_shaper_layer7.php' => 'https://doc.pfsense.org/index.php/Layer_7',
	'firewall_shaper_queues.php' => 'https://doc.pfsense.org/index.php/Traffic_Shaping_Guide',
	'firewall_shaper_vinterface.php' => 'https://doc.pfsense.org/index.php/Limiters',
	'firewall_shaper_wizards.php' => 'https://doc.pfsense.org/index.php/Traffic_Shaping_Guide',
	'status_queues.php' => 'https://doc.pfsense.org/index.php/Traffic_Shaping_Guide',
	'status_dhcp_leases.php' => 'https://doc.pfsense.org/index.php/DHCP_Leases',
	'diag_dns.php' => 'https://doc.pfsense.org/index.php/DNS_Lookup',
	'diag_dump_states.php' => 'https://doc.pfsense.org/index.php/Show_States',
	'diag_resetstate.php' => 'https://doc.pfsense.org/index.php/Reset_States',
	'diag_logs.php' => 'https://doc.pfsense.org/index.php/System_Logs',
	'diag_logs_auth.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Authentication_Logs',
	'diag_logs_dhcp.php' => 'https://doc.pfsense.org/index.php/DHCP_Logs',
	'diag_logs_filter.php' => 'https://doc.pfsense.org/index.php/Firewall_Logs',
	'diag_logs_filter_dynamic.php' => 'https://doc.pfsense.org/index.php/Firewall_Logs',
	'diag_logs_filter_summary.php' => 'https://doc.pfsense.org/index.php/Firewall_Logs',
	'diag_logs_ntpd.php' => 'https://doc.pfsense.org/index.php/NTP_Logs',
	'diag_logs_ppp.php' => 'https://doc.pfsense.org/index.php/PPP_Logs',
	'diag_logs_relayd.php' => 'https://doc.pfsense.org/index.php/Load_Balancer_Logs',
	'diag_logs_settings.php' => 'https://doc.pfsense.org/index.php/Log_Settings',
	'diag_logs_vpn.php' => 'https://doc.pfsense.org/index.php/PPTP_VPN_Logs',
	'diag_logs_ipsec.php' => 'https://doc.pfsense.org/index.php/IPsec_Logs',
	'diag_logs_openvpn.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Logs',
	'diag_nanobsd.php' => 'https://doc.pfsense.org/index.php/NanoBSD_Diagnostics',
	'diag_patterns.php' => 'https://doc.pfsense.org/index.php/Layer7_Pattern_Diagnostics',
	'diag_ping.php' => 'https://doc.pfsense.org/index.php/Ping_Host',
	'diag_pkglogs.php' => 'https://doc.pfsense.org/index.php/Package_Logs',
	'diag_tables.php' => 'https://doc.pfsense.org/index.php/Tables',
	'diag_system_activity.php' => 'https://doc.pfsense.org/index.php/System_Activity',
	'diag_traceroute.php' => 'https://doc.pfsense.org/index.php/Traceroute',
	'easyrule.php' => 'https://doc.pfsense.org/index.php/Easy_Rule',
	'edit.php' => 'https://doc.pfsense.org/index.php/Edit_File',
	'exec.php' => 'https://doc.pfsense.org/index.php/Execute_Command',
	'firewall_nat_1to1.php' => 'https://doc.pfsense.org/index.php/1:1_NAT',
	'firewall_nat_1to1_edit.php' => 'https://doc.pfsense.org/index.php/1:1_NAT',
	'halt.php' => 'https://doc.pfsense.org/index.php/Halt_System',
	'reboot.php' => 'https://doc.pfsense.org/index.php/Reboot_System',
	'status_filter_reload.php' => 'https://doc.pfsense.org/index.php/Filter_Reload_Status',
	'status_gateway_groups.php' => 'https://doc.pfsense.org/index.php/Gateway_Status',
	'status_gateways.php' => 'https://doc.pfsense.org/index.php/Gateway_Status',
	'status_graph.php' => 'https://doc.pfsense.org/index.php/Traffic_Graph',
	'status_graph_cpu.php' => 'https://doc.pfsense.org/index.php/CPU_Load',
	'status_interfaces.php' => 'https://doc.pfsense.org/index.php/Interface_Status',
	'status_services.php' => 'https://doc.pfsense.org/index.php/Services_Status',
	'status_wireless.php' => 'https://doc.pfsense.org/index.php/Wireless_Status',
	'pkg_mgr.php' => 'https://doc.pfsense.org/index.php/Package_Manager',
	'pkg_mgr_install.php' => 'https://doc.pfsense.org/index.php/Package_Manager',
	'pkg_mgr_installed.php' => 'https://doc.pfsense.org/index.php/Package_Manager',
	'interfaces.php' => 'https://doc.pfsense.org/index.php/Interface_Settings',
	'interfaces_assign.php' => 'https://doc.pfsense.org/index.php/Assign_Interfaces',
	'interfaces_bridge.php' => 'https://doc.pfsense.org/index.php/Interface_Bridges',
	'interfaces_bridge_edit.php' => 'https://doc.pfsense.org/index.php/Interface_Bridges',
	'interfaces_gif.php' => 'https://doc.pfsense.org/index.php/GIF_Interfaces',
	'interfaces_gif_edit.php' => 'https://doc.pfsense.org/index.php/GIF_Interfaces',
	'interfaces_gre.php' => 'https://doc.pfsense.org/index.php/GRE_Interfaces',
	'interfaces_gre_edit.php' => 'https://doc.pfsense.org/index.php/GRE_Interfaces',
	'interfaces_groups.php' => 'https://doc.pfsense.org/index.php/Interface_Groups',
	'interfaces_groups_edit.php' => 'https://doc.pfsense.org/index.php/Interface_Groups',
	'interfaces_lagg.php' => 'https://doc.pfsense.org/index.php/LAGG_Interfaces',
	'interfaces_lagg_edit.php' => 'https://doc.pfsense.org/index.php/LAGG_Interfaces',
	'interfaces_ppps.php' => 'https://doc.pfsense.org/index.php/PPP_Interfaces',
	'interfaces_ppps_edit.php' => 'https://doc.pfsense.org/index.php/PPP_Interfaces',
	'interfaces_qinq.php' => 'https://doc.pfsense.org/index.php/QinQ_Interfaces',
	'interfaces_qinq_edit.php' => 'https://doc.pfsense.org/index.php/QinQ_Interfaces',
	'services_dyndns.php' => 'https://doc.pfsense.org/index.php/Dynamic_DNS',
	'services_dyndns_edit.php' => 'https://doc.pfsense.org/index.php/Dynamic_DNS',
	'services_rfc2136.php' => 'https://doc.pfsense.org/index.php/Dynamic_DNS',
	'services_rfc2136_edit.php' => 'https://doc.pfsense.org/index.php/Dynamic_DNS',
	'services_dhcp.php' => 'https://doc.pfsense.org/index.php/DHCP_Server',
	'services_dhcp_edit.php' => 'https://doc.pfsense.org/index.php/DHCP_Server',
	'services_dhcp_relay.php' => 'https://doc.pfsense.org/index.php/DHCP_Relay',
	'services_dnsmasq.php' => 'https://doc.pfsense.org/index.php/DNS_Forwarder',
	'services_dnsmasq_domainoverride_edit.php' => 'https://doc.pfsense.org/index.php/DNS_Forwarder',
	'services_dnsmasq_edit.php' => 'https://doc.pfsense.org/index.php/DNS_Forwarder',
	'services_igmpproxy.php' => 'https://doc.pfsense.org/index.php/IGMP_Proxy',
	'services_igmpproxy_edit.php' => 'https://doc.pfsense.org/index.php/IGMP_Proxy',
	'services_snmp.php' => 'https://doc.pfsense.org/index.php/SNMP_Daemon',
	'services_wol.php' => 'https://doc.pfsense.org/index.php/Wake_on_LAN',
	'services_wol_edit.php' => 'https://doc.pfsense.org/index.php/Wake_on_LAN',
	'system.php' => 'https://doc.pfsense.org/index.php/General_Setup',
	'system_advanced_admin.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup',
	'system_advanced_firewall.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup#Firewall.2FNAT',
	'system_advanced_misc.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup#Miscellaneous',
	'system_advanced_network.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup#Firewall.2FNAT',
	'system_advanced_notifications.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup#Notifications',
	'system_advanced_sysctl.php' => 'https://doc.pfsense.org/index.php/Advanced_Setup#System_Tunables',
	'system_firmware.php' => 'https://doc.pfsense.org/index.php/Firmware_Updates',
	'system_firmware_auto.php' => 'https://doc.pfsense.org/index.php/Firmware_Updates',
	'system_firmware_check.php' => 'https://doc.pfsense.org/index.php/Firmware_Updates',
	'system_firmware_settings.php' => 'https://doc.pfsense.org/index.php/Firmware_Updates',
	'system_gateway_groups.php' => 'https://doc.pfsense.org/index.php/Gateway_Settings',
	'system_gateway_groups_edit.php' => 'https://doc.pfsense.org/index.php/Gateway_Settings',
	'system_gateways.php' => 'https://doc.pfsense.org/index.php/Gateway_Settings',
	'system_gateways_edit.php' => 'https://doc.pfsense.org/index.php/Gateway_Settings',
	'system_routes.php' => 'https://doc.pfsense.org/index.php/Static_Routes',
	'system_routes_edit.php' => 'https://doc.pfsense.org/index.php/Static_Routes',
	'system_authservers.php' => 'https://doc.pfsense.org/index.php/User_Authentication_Servers',
	'system_groupmanager.php' => 'https://doc.pfsense.org/index.php/Group_Manager',
	'system_groupmanager_addprivs.php' => 'https://doc.pfsense.org/index.php/Group_Manager',
	'system_usermanager.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_usermanager_addprivs.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_usermanager_settings.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_usermanager_settings_ldapacpicker.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_usermanager_settings_test.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_usermanager_passwordmg.php' => 'https://doc.pfsense.org/index.php/User_Manager',
	'system_camanager.php' => 'https://doc.pfsense.org/index.php/Certificate_Management',
	'system_certmanager.php' => 'https://doc.pfsense.org/index.php/Certificate_Management',
	'vpn_l2tp.php' => 'https://doc.pfsense.org/index.php/L2TP_VPN_Settings',
	'vpn_l2tp_users.php' => 'https://doc.pfsense.org/index.php/L2TP_VPN_Settings',
	'vpn_l2tp_users_edit.php' => 'https://doc.pfsense.org/index.php/L2TP_VPN_Settings',
	'vpn_pppoe.php' => 'https://doc.pfsense.org/index.php/PPPoE_Server_Settings',
	'vpn_pppoe_edit.php' => 'https://doc.pfsense.org/index.php/PPPoE_Server_Settings',
	'vpn_pptp.php' => 'https://doc.pfsense.org/index.php/PPTP_VPN_Settings',
	'vpn_pptp_users.php' => 'https://doc.pfsense.org/index.php/PPTP_VPN_Settings',
	'vpn_pptp_users_edit.php' => 'https://doc.pfsense.org/index.php/PPTP_VPN_Settings',
	'diag_ipsec.php' => 'https://doc.pfsense.org/index.php/IPsec_Status',
	'diag_ipsec_sad.php' => 'https://doc.pfsense.org/index.php/IPsec_Status',
	'diag_ipsec_spd.php' => 'https://doc.pfsense.org/index.php/IPsec_Status',
	'vpn_ipsec.php' => 'https://doc.pfsense.org/index.php/IPsec_Tunnels',
	'vpn_ipsec_mobile.php' => 'https://doc.pfsense.org/index.php/IPsec_Mobile_Clients',
	'diag_ipsec_leases.php' => 'https://doc.pfsense.org/index.php/IPsec_Mobile_Clients',
	'vpn_ipsec_phase1.php' => 'https://doc.pfsense.org/index.php/IPsec_Tunnels',
	'vpn_ipsec_phase2.php' => 'https://doc.pfsense.org/index.php/IPsec_Tunnels',
	'vpn_ipsec_keys.php' => 'https://doc.pfsense.org/index.php/IPsec_Tunnels',
	'vpn_ipsec_keys_edit.php' => 'https://doc.pfsense.org/index.php/IPsec_Tunnels',
	'vpn_ipsec_settings.php' => 'https://doc.pfsense.org/index.php/Advanced_IPsec_Settings',
	'services_captiveportal.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_filemanager.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_ip.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_ip_edit.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_mac.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_mac_edit.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_hostname.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_hostname_edit.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'status_captiveportal.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Status',
	'status_captiveportal_test.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Status',
	'services_captiveportal_vouchers.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Vouchers',
	'services_captiveportal_vouchers_edit.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Vouchers',
	'status_captiveportal_voucher_rolls.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Vouchers',
	'status_captiveportal_vouchers.php' => 'https://doc.pfsense.org/index.php/Captive_Portal_Vouchers',
	'status_openvpn.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Status',
	'vpn_openvpn_client.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Settings',
	'vpn_openvpn_csc.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Settings',
	'vpn_openvpn_server.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Settings',
	'openvpn-client-export.xml' => 'https://doc.pfsense.org/index.php/OpenVPN_Client_Exporter', /* Package */
	'vpn_openvpn_export.php' => 'https://doc.pfsense.org/index.php/OpenVPN_Client_Exporter', /* Package */
	'diag_authentication.php' => 'https://doc.pfsense.org/index.php/User_Authentication_Servers',
	'diag_limiter_info.php' => 'https://doc.pfsense.org/index.php/Limiters',
	'diag_pf_info.php' => 'https://doc.pfsense.org/index.php/Packet_Filter_Information',
	'diag_smart.php' => 'https://doc.pfsense.org/index.php/SMART_Status',
	'diag_states_summary.php' => 'https://doc.pfsense.org/index.php/States_Summary',
	'interfaces_wireless.php' => 'https://doc.pfsense.org/index.php/Wireless_Interfaces',
	'interfaces_wireless_edit.php' => 'https://doc.pfsense.org/index.php/Wireless_Interfaces',
	'system_crlmanager.php' => 'https://doc.pfsense.org/index.php/Certificate_Management',
	'crash_reporter.php' => 'https://doc.pfsense.org/index.php/Unexpected_Reboot_Troubleshooting',
	'diag_dump_states_sources.php' => 'https://doc.pfsense.org/index.php/Show_Source_Tracking',
	'diag_logs_gateways.php' => 'https://doc.pfsense.org/index.php/Gateway_Logs',
	'diag_logs_resolver.php' => 'https://doc.pfsense.org/index.php/Resolver_Logs',
	'diag_logs_routing.php' => 'https://doc.pfsense.org/index.php/Routing_Logs',
	'diag_logs_wireless.php' => 'https://doc.pfsense.org/index.php/Wireless_Logs',
	'diag_ndp.php' => 'https://doc.pfsense.org/index.php/NDP_Table',
	'diag_sockets.php' => 'https://doc.pfsense.org/index.php/Diag_Sockets',
	'diag_testport.php' => 'https://doc.pfsense.org/index.php/Test_Port',
	'firewall_nat_npt.php' => 'https://doc.pfsense.org/index.php/NPt',
	'firewall_nat_npt_edit.php' => 'https://doc.pfsense.org/index.php/NPt',
	'services_captiveportal_zones.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_captiveportal_zones_edit.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'status_captiveportal_expire.php' => 'https://doc.pfsense.org/index.php/Captive_Portal',
	'services_ntpd.php' => 'https://doc.pfsense.org/index.php/NTP_Server',
	'status_ntpd.php' => 'https://doc.pfsense.org/index.php/NTP_Server',
	'services_ntpd_gps.php' => 'https://doc.pfsense.org/index.php/NTP_Server',
	'services_ntpd_pps.php' => 'https://doc.pfsense.org/index.php/NTP_Server',
	'system_firmware_restorefullbackup.php' => 'https://doc.pfsense.org/index.php/Full_Backup',
	'load_balancer_monitor.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing',
	'load_balancer_monitor_edit.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing',
	'load_balancer_pool.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing#Set_up_Load_Balancing_Pool',
	'load_balancer_pool_edit.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing#Set_up_Load_Balancing_Pool',
	'load_balancer_virtual_server.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing#Set_up_Virtual_Server',
	'load_balancer_virtual_server_edit.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing#Set_up_Virtual_Server',
	'load_balancer_setting.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing#Advanced_Settings',
	'status_lb_pool.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing_Status',
	'status_lb_vs.php' => 'https://doc.pfsense.org/index.php/Inbound_Load_Balancing_Status',
	'services_dhcpv6_relay.php' => 'https://doc.pfsense.org/index.php/DHCP_Relay',
	'status_dhcpv6_leases.php' => 'https://doc.pfsense.org/index.php/DHCPv6_Leases',
	'services_dhcpv6.php' => 'https://doc.pfsense.org/index.php/DHCPv6_Server',
	'services_dhcpv6_edit.php' => 'https://doc.pfsense.org/index.php/DHCPv6_Server',
	'services_router_advertisements.php' => 'https://doc.pfsense.org/index.php/Router_Advertisements',
	'carp_status.php' => 'https://doc.pfsense.org/index.php/CARP_Status',
	'system_hasync.php' => 'https://doc.pfsense.org/index.php/High_Availability',
	'services_unbound.php' => 'https://doc.pfsense.org/index.php/Unbound_DNS_Resolver',
	'services_unbound_advanced.php' => 'https://doc.pfsense.org/index.php/Unbound_DNS_Resolver#Advanced_Settings_Tab',
	'services_unbound_acls.php' => 'https://doc.pfsense.org/index.php/Unbound_DNS_Resolver#Access_Lists_Tab',
	'services_unbound_domainoverride_edit.php' => 'https://doc.pfsense.org/index.php/Unbound_DNS_Resolver',
	'services_unbound_host_edit.php' => 'https://doc.pfsense.org/index.php/Unbound_DNS_Resolver',
	'diag_gmirror.php' => 'https://doc.pfsense.org/index.php/Create_a_Software_RAID1_%28gmirror%29',

	/* From here down are packages. Not checking these as strictly,
	any information is better than nothing. */
	'olsrd.xml' => 'https://doc.pfsense.org/index.php/OLSR_Daemon',
	'routed.xml' => 'https://doc.pfsense.org/index.php/Routing_Information_Protocol_(RIP)', # RIP
	'autoconfigbackup.xml' => 'https://doc.pfsense.org/index.php/AutoConfigBackup',
	'phpservice.xml' => 'https://doc.pfsense.org/index.php/PHPService',
	'anyterm.xml' => 'https://doc.pfsense.org/index.php/AnyTerm_package',
	'avahi.xml' => 'https://doc.pfsense.org/index.php/Avahi_package',
	'squid.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_auth.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_cache.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_extauth.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_nac.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_ng.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_traffic.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_upstream.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squid_users.xml' => 'https://doc.pfsense.org/index.php/Category:Squid',
	'squidGuard.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_acl.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_default.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_dest.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_log.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_rewr.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'squidguard_time.xml' => 'https://doc.pfsense.org/index.php/SquidGuard_package',
	'bandwidthd.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'pfflowd.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'darkstat.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'rate.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'ntop.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'ntopng.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'vnstat.xml' => 'https://doc.pfsense.org/index.php/How_can_I_monitor_bandwidth_usage',
	'widentd.xml' => 'https://doc.pfsense.org/index.php/Widentd_package',
	'tinydns.xml' => 'https://doc.pfsense.org/index.php/Tinydns_package',
	'tinydns_domains.xml' => 'https://doc.pfsense.org/index.php/Tinydns_package',
	'tinydns_sync.xml' => 'https://doc.pfsense.org/index.php/Tinydns_package',
	'blinkled.xml' => 'https://doc.pfsense.org/index.php/BlinkLED_Package',
	'havp.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'havp_avset.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'havp_blacklist.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'havp_fscan.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'havp_trans_exclude.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'havp_whitelist.xml' => 'https://doc.pfsense.org/index.php/HAVP_Package_for_HTTP_Anti-Virus_Scanning',
	'snort.xml' => 'https://doc.pfsense.org/index.php/Setup_Snort_Package',
	'snort/snort_interfaces.php' => 'https://doc.pfsense.org/index.php/Snort_interfaces',
	'snort/snort_interfaces_global.php' => 'https://doc.pfsense.org/index.php/Snort_interfaces_global',
	'snort/snort_download_updates.php' => 'https://doc.pfsense.org/index.php/Snort_updates',
	'snort/snort_alerts.php' => 'https://doc.pfsense.org/index.php/Snort_alerts',
	'snort/snort_blocked.php' => 'https://doc.pfsense.org/index.php/Snort_blocked_hosts',
	'snort/snort_passlist.php' => 'https://doc.pfsense.org/index.php/Snort_passlist',
	'snort/snort_passlist_edit.php' => 'https://doc.pfsense.org/index.php/Snort_passlist',
	'snort/snort_interfaces_suppress.php' => 'https://doc.pfsense.org/index.php/Snort_suppress_list',
	'snort/snort_interfaces_suppress_edit.php' => 'https://doc.pfsense.org/index.php/Snort_suppress_list',
	'snort/snort_interfaces_edit.php' => 'https://doc.pfsense.org/index.php/Snort_interfaces_edit',
	'snort/snort_rulesets.php' => 'https://doc.pfsense.org/index.php/Snort_rulesets',
	'snort/snort_rules.php' => 'https://doc.pfsense.org/index.php/Snort_rules',
	'snort/snort_define_servers.php' => 'https://doc.pfsense.org/index.php/Snort_define_servers',
	'snort/snort_preprocessors.php' => 'https://doc.pfsense.org/index.php/Snort_preprocessors',
	'snort/snort_barnyard.php' => 'https://doc.pfsense.org/index.php/Snort_barnyard2',
	'snort/snort_ip_reputation.php' => 'https://doc.pfsense.org/index.php/Snort_ip_reputation_preprocessor',
	'snort/snort_ip_list_mgmt.php' => 'https://doc.pfsense.org/index.php/Snort_ip_list_mgmt',
	'snort/snort_sync.xml' => 'https://doc.pfsense.org/index.php/Snort_sync',
	'stunnel.xml' => 'https://doc.pfsense.org/index.php/Stunnel_package',
	'stunnel_certs.xml' => 'https://doc.pfsense.org/index.php/Stunnel_package',
	'openbgpd.xml' => 'https://doc.pfsense.org/index.php/OpenBGPD_package',
	'openbgpd_groups.xml' => 'https://doc.pfsense.org/index.php/OpenBGPD_package',
	'openbgpd_neighbors.xml' => 'https://doc.pfsense.org/index.php/OpenBGPD_package',
	'iperf.xml' => 'https://doc.pfsense.org/index.php/Iperf_package',
	'iperfserver.xml' => 'https://doc.pfsense.org/index.php/Iperf_package',
	'jail_template.xml' => 'https://doc.pfsense.org/index.php/PfJailctl_package',
	'jailctl.xml' => 'https://doc.pfsense.org/index.php/PfJailctl_package',
	'jailctl_defaults.xml' => 'https://doc.pfsense.org/index.php/PfJailctl_package',
	'jailctl_settings.xml' => 'https://doc.pfsense.org/index.php/PfJailctl_package',
	'siproxd.xml' => 'https://doc.pfsense.org/index.php/Siproxd_package',
	'siproxdusers.xml' => 'https://doc.pfsense.org/index.php/Siproxd_package',
	'open-vm-tools.xml' => 'https://doc.pfsense.org/index.php/Open_VM_Tools_package',
	'arping.xml' => 'https://doc.pfsense.org/index.php/Arping_package',
	'unbound.xml' => 'https://doc.pfsense.org/index.php/Unbound_package',
	'nut.xml' => 'https://doc.pfsense.org/index.php/Nut_package',

);

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
		$pagename = ltrim(parse_url($_SERVER["HTTP_REFERER"], PHP_URL_PATH), '/');
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
}

/* Using the derived page name, attempt to find in the URL mapping hash */
if (array_key_exists($pagename, $helppages)) {
	$helppage = $helppages[$pagename];
}

/* If we haven't determined a proper page, use a generic help page
	 stating that a given page does not have help yet. */

if (empty($helppage)) {
	$helppage = 'https://doc.pfsense.org/index.php/No_Help_Found';
}

/* Redirect to help page. */
header("Location: {$helppage}");

?>
