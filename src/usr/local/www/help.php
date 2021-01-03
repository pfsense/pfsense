<?php
/*
 * help.php
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

require_once("guiconfig.inc");

/* Define hash of jumpto url maps */
$helppages = array(
	'index.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/dashboard.html',

	'crash_reporter.php' => 'https://docs.netgate.com/pfsense/en/latest/development/panic-information.html',
	'diag_arp.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/arp.html',
	'diag_authentication.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/authentication-servers.html',
	'diag_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/restore.html',
	'diag_command.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/command-prompt.html',
	'diag_confbak.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/restore.html',
	'diag_defaults.php' => 'https://docs.netgate.com/pfsense/en/latest/config/factory-defaults.html',
	'diag_dns.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/dns.html',
	'diag_dump_states.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-states-gui.html',
	'diag_dump_states_sources.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-states-sources.html',
	'diag_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/edit-file.html',
	'diag_halt.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/system-halt.html',
	'diag_limiter_info.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/limiters.html',
	'diag_ndp.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/ndp.html',
	'diag_packet_capture.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/packetcapture/webgui.html',
	'diag_pf_info.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/pfinfo.html',
	'diag_pftop.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/graphs/bandwidth-usage.html',
	'diag_ping.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/ping.html',
	'diag_reboot.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/system-reboot.html',
	'diag_resetstate.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-states-reset.html',
	'diag_routes.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/routes.html',
	'diag_smart.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/smart.html',
	'diag_sockets.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/sockets.html',
	'diag_states_summary.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-states-summary.html',
	'diag_system_activity.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/system-activity.html',
	'diag_tables.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/firewall-tables.html',
	'diag_testport.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/test-port.html',
	'diag_traceroute.php' => 'https://docs.netgate.com/pfsense/en/latest/diagnostics/traceroute.html',
	'firewall_aliases_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/aliases.html',
	'firewall_aliases_import.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/aliases.html',
	'firewall_aliases.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/aliases.html',
	'firewall_nat_1to1_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/1-1.html',
	'firewall_nat_1to1.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/1-1.html',
	'firewall_nat_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/port-forwards.html',
	'firewall_nat_npt_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/npt.html',
	'firewall_nat_npt.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/npt.html',
	'firewall_nat_out_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/outbound.html',
	'firewall_nat_out.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/outbound.html',
	'firewall_nat.php' => 'https://docs.netgate.com/pfsense/en/latest/nat/port-forwards.html',
	'firewall_rules_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/configure.html',
	'firewall_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/rule-list-intro.html',
	'firewall_schedule_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/time-based-rules.html',
	'firewall_schedule.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/time-based-rules.html',
	'firewall_shaper.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/index.html',
	'firewall_shaper_queues.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/index.html',
	'firewall_shaper_vinterface.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/limiters.html',
	'firewall_shaper_wizards.php' => 'https://docs.netgate.com/pfsense/en/latest/recipes/traffic-shaper-altq-wizard.html',
	'firewall_virtual_ip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/virtual-ip-addresses.html',
	'firewall_virtual_ip.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/virtual-ip-addresses.html',
	'interfaces_assign.php' => 'https://docs.netgate.com/pfsense/en/latest/config/interface-configuration.html',
	'interfaces_bridge_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/bridges/create.html',
	'interfaces_bridge.php' => 'https://docs.netgate.com/pfsense/en/latest/bridges/index.html',
	'interfaces_gif_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/gif.html',
	'interfaces_gif.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/gif.html',
	'interfaces_gre_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/gre.html',
	'interfaces_gre.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/gre.html',
	'interfaces_groups_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/groups.html',
	'interfaces_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/groups.html',
	'interfaces_lagg_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/lagg.html',
	'interfaces_lagg.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/lagg.html',
	'interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/configure.html',
	'interfaces_ppps_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/ppp.html',
	'interfaces_ppps.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/ppp.html',
	'interfaces_qinq_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/qinq.html',
	'interfaces_qinq.php' => 'https://docs.netgate.com/pfsense/en/latest/interfaces/qinq.html',
	'interfaces_vlan_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/vlan/configuration.html',
	'interfaces_vlan.php' => 'https://docs.netgate.com/pfsense/en/latest/vlan/index.html',
	'interfaces_wireless_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/wireless/vap.html',
	'interfaces_wireless.php' => 'https://docs.netgate.com/pfsense/en/latest/wireless/index.html',
	'miniupnpd.xml' => 'https://docs.netgate.com/pfsense/en/latest/services/upnp.html',
	'openvpn_wizard.xml' => 'https://docs.netgate.com/pfsense/en/latest/recipes/openvpn-ra.html',
	'openvpn-client-export.xml' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'pkg_mgr_installed.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/manager.html',
	'pkg_mgr_install.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/manager.html',
	'pkg_mgr.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/manager.html',
	'services_acb_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'services_acb_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'services_acb.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'services_captiveportal_filemanager.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/file-manager.html',
	'services_captiveportal_hostname_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/allowed-hostnames.html',
	'services_captiveportal_hostname.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/allowed-hostnames.html',
	'services_captiveportal_ip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/allowed-ip-address.html',
	'services_captiveportal_ip.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/allowed-ip-address.html',
	'services_captiveportal_mac_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/mac-address-control.html',
	'services_captiveportal_mac.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/mac-address-control.html',
	'services_captiveportal.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'services_captiveportal_vouchers_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'services_captiveportal_vouchers.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/vouchers.html',
	'services_captiveportal_zones_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'services_captiveportal_zones.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/index.html',
	'services_checkip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/check-services.html',
	'services_checkip.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/check-services.html',
	'services_dhcp_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv4.html',
	'services_dhcp.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv4.html',
	'services_dhcp_relay.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/relay.html',
	'services_dhcpv6_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv6.html',
	'services_dhcpv6.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv6-ra.html',
	'services_dhcpv6_relay.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv4.html',
	'services_dnsmasq_domainoverride_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/forwarder.html',
	'services_dnsmasq_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/forwarder.html',
	'services_dnsmasq.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/forwarder.html',
	'services_dyndns_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/client.html',
	'services_dyndns.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/index.html',
	'services_igmpproxy_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/igmp-proxy.html',
	'services_igmpproxy.php' => 'https://docs.netgate.com/pfsense/en/latest/services/igmp-proxy.html',
	'services_ntpd_acls.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntpd/server.html',
	'services_ntpd_gps.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntpd/gps.html',
	'services_ntpd.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntpd/server.html',
	'services_ntpd_pps.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntpd/pps.html',
	'services_pppoe_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/pppoe-server.html',
	'services_pppoe.php' => 'https://docs.netgate.com/pfsense/en/latest/services/pppoe-server.html',
	'services_rfc2136_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/rfc2136.html',
	'services_rfc2136.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dyndns/rfc2136.html',
	'services_router_advertisements.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dhcp/ipv6-ra.html',
	'services_snmp.php' => 'https://docs.netgate.com/pfsense/en/latest/services/snmp.html',
	'services_unbound_acls.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver-acls.html',
	'services_unbound_advanced.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver-advanced.html',
	'services_unbound_domainoverride_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver.html',
	'services_unbound_host_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver.html',
	'services_unbound.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver.html',
	'services_wol_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/services/wake-on-lan.html',
	'services_wol.php' => 'https://docs.netgate.com/pfsense/en/latest/services/wake-on-lan.html',
	'status_captiveportal_expire.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'status_captiveportal.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'status_captiveportal_test.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'status_captiveportal_voucher_rolls.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'status_captiveportal_vouchers.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/index.html',
	'status_carp.php' => 'https://docs.netgate.com/pfsense/en/latest/highavailability/test.html',
	'status_dhcp_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/dhcp-ipv4.html',
	'status_dhcpv6_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/dhcp-ipv6.html',
	'status_filter_reload.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/filter-reload.html',
	'status_gateway_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/gateways.html',
	'status_gateways.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/gateways.html',
	'status_graph.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/graphs/traffic.html',
	'status_interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/interfaces.html',
	'status_ipsec_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/mobile-clients.html',
	'status_ipsec.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/ipsec.html',
	'status_ipsec_sad.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/ipsec.html',
	'status_ipsec_spd.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/ipsec.html',
	'status_logs_filter_dynamic.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/firewall.html',
	'status_logs_filter.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/firewall.html',
	'status_logs_filter_summary.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/firewall.html',
	'status_logs.php-dhcpd' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/dhcp.html',
	'status_logs.php-gateways' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/gateway.html',
	'status_logs.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/index.html',
	'status_logs.php-ipsec' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/ipsec.html',
	'status_logs.php-ntpd' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/ntp.html',
	'status_logs.php-openvpn' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'status_logs.php-portalauth' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/zones.html',
	'status_logs.php-ppp' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/ppp.html',
	'status_logs.php-resolver' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/resolver.html',
	'status_logs.php-routing' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/routing.html',
	'status_logs.php-wireless' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/wireless.html',
	'status_logs_packages.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/package.html',
	'status_logs_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/settings.html',
	'status_logs_vpn.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/logs/index.html',
	'status_monitoring.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/graphs/index.html',
	'status_ntpd.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntpd/server.html',
	'status_openvpn.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'status_queues.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/index.html',
	'status_services.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/services.html',
	'status_unbound.php' => 'https://docs.netgate.com/pfsense/en/latest/services/dns/resolver.html',
	'status_upnp.php' => 'https://docs.netgate.com/pfsense/en/latest/services/upnp.html',
	'status_wireless.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/status/wireless.html',
	'system_advanced_admin.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-admin.html',
	'system_advanced_firewall.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-firewall-nat.html',
	'system_advanced_misc.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-misc.html',
	'system_advanced_network.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-networking.html',
	'system_advanced_notifications.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-notifications.html',
	'system_advanced_sysctl.php' => 'https://docs.netgate.com/pfsense/en/latest/config/advanced-tunables.html',
	'system_authservers.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/authentication-servers.html',
	'system_camanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/index.html',
	'system_certmanager_renew.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/index.html',
	'system_certmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/index.html',
	'system_crlmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/index.html',
	'system_gateway_groups_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-configure.html',
	'system_gateway_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-configure.html',
	'system_gateways_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-configure.html',
	'system_gateways.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-configure.html',
	'system_groupmanager_addprivs.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/groups.html',
	'system_groupmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/groups.html',
	'system_hasync.php' => 'https://docs.netgate.com/pfsense/en/latest/highavailability/index.html',
	'system.php' => 'https://docs.netgate.com/pfsense/en/latest/config/general.html',
	'system_routes_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/static.html',
	'system_routes.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/static.html',
	'system_update_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/install/upgrade-guide.html',
	'system_usermanager_addprivs.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/users.html',
	'system_usermanager_passwordmg.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/users.html',
	'system_usermanager.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/users.html',
	'system_usermanager_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/users.html',
	'system_user_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/users.html',
	'traffic_shaper_wizard_dedicated.xml' => 'https://docs.netgate.com/pfsense/en/latest/recipes/traffic-shaper-altq-wizard.html',
	'traffic_shaper_wizard_multi_all.xml' => 'https://docs.netgate.com/pfsense/en/latest/recipes/traffic-shaper-altq-wizard.html',
	'vpn_ipsec_keys_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/tunnels.html',
	'vpn_ipsec_keys.php' => 'https://docs.netgate.com/pfsense/en/latest/recipes/ipsec-mobile-ikev2-eap-mschapv2.html',
	'vpn_ipsec_mobile.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/mobile-clients.html',
	'vpn_ipsec_phase1.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/configure.html',
	'vpn_ipsec_phase2.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/configure.html',
	'vpn_ipsec.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/index.html',
	'vpn_ipsec_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/ipsec-advanced-settings.html',
	'vpn_l2tp.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/l2tp/configuration.html',
	'vpn_l2tp_users_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/l2tp/configuration.html',
	'vpn_l2tp_users.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/l2tp/configuration.html',
	'vpn_openvpn_client.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_csc.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_export.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_export_shared.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_server.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',

	/* Packages from here on down. Not checked as strictly. Pages may not yet exist. */
	'acme/acme_accountkeys_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'acme/acme_accountkeys.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'acme/acme_certificates_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'acme/acme_certificates.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'acme/acme_generalsettings.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'acme.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/acme/index.html',
	'arping.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/arping.html',
	'autoconfigbackup_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup_stats.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup.xml' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'avahi.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/avahi.html',
	'darkstat.xml' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/graphs/bandwidth-usage.html',
	'freeradiusauthorizedmacs.xml' => 'https://www.netgate.com/docs/pfsense/usermanager/plain-mac-authentication-with-freeradius.rst',
	'freeradiuscerts.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradiusclients.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradiuseapconf.xml' => 'https://docs.netgate.com/pfsense/en/latest/recipes/freeradius-eap.html',
	'freeradiusinterfaces.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradiusmodulesldap.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradiussettings.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradiussqlconf.xml' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/using-mysql-with-freeradius.html',
	'freeradiussync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradius_view_config.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'freeradius.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius.html',
	'haproxy/haproxy_files.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_global.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_listeners_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_listeners.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_pool_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_pools.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_stats.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy/haproxy_templates.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'haproxy.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy.html',
	'iperfserver.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/iperf.html',
	'iperf.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/iperf.html',
	'ladvd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/lldp.html',
	'nmap.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/nmap.html',
	'ntopng.xml' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/graphs/bandwidth-usage.html',
	'nut_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut.html',
	'nut_status.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut.html',
	'nut.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut.html',
	'openbgpd_groups.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd.html',
	'openbgpd_neighbors.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd.html',
	'openbgpd_raw.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd.html',
	'openbgpd_status.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd.html',
	'openbgpd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd.html',
	'open-vm-tools.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/open-vm-tools.html',
	'pfblockerng/pfblockerng_alerts_ar.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_alerts.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_dnsbl_easylist.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_dnsbl_lists.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_dnsbl.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_log.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_threats.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_update.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_v4lists.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/pfblockerng_v6lists.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng/www/index.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'pfblockerng.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/pfblocker.html',
	'routed.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/routed.html',
	'shellcmd.xml' => 'https://docs.netgate.com/pfsense/en/latest/development/boot-commands.html',
	'siproxd_registered_phones.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd.html',
	'siproxdusers.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd.html',
	'siproxd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd.html',
	'snort/snort_alerts.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/alerts.html',
	'snort/snort_barnyard.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-barnyard2.html',
	'snort/snort_blocked.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/blocked-hosts.html',
	'snort/snort_define_servers.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/define-servers.html',
	'snort/snort_download_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/updates.html',
	'snort/snort_download_updates.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/updates.html',
	'snort/snort_ftp_client_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_ftp_server_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_httpinspect_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_interfaces_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/interfaces.html',
	'snort/snort_interfaces_global.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/interfaces.html',
	'snort/snort_interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/interfaces.html',
	'snort/snort_interfaces_suppress_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/suppress-list.html',
	'snort/snort_interfaces_suppress.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/suppress-list.html',
	'snort/snort_ip_list_mgmt.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/ip-list-mgmt.html',
	'snort/snort_iprep_list_browser.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/ip-reputation.html',
	'snort/snort_ip_reputation.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/ip-reputation.html',
	'snort/snort_passlist_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/passlist.html',
	'snort/snort_passlist.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/passlist.html',
	'snort/snort_preprocessors.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_rules_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/rules.html',
	'snort/snort_rulesets.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/rules.html',
	'snort/snort_rules_flowbits.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/rules.html',
	'snort/snort_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/rules.html',
	'snort/snort_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-xmlrpc-synchronization.html',
	'snort.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/setup.html',
	'softflowd.xml' => 'https://docs.netgate.com/pfsense/en/latest/recipes/netflow-with-softflowd.html',
	'squid_antivirus.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_auth.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_cache.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squidguard_acl.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard_default.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard_dest.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard_rewr.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidGuard/squidguard_blacklist.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidGuard/squidguard_log.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard_time.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squidguard.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/cache-proxy/squidguard.html',
	'squid_log_parser.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_monitor_data.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_monitor.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_reverse_general.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_reverse_peer.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_reverse_redir.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_reverse_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_reverse_uri.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_traffic.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_upstream.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid_users.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'squid.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/snort/index.html',
	'status_ladvd.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/lldp.html',
	'stunnel_certs.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/stunnel.html',
	'stunnel.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/stunnel.html',
	'sudo.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/sudo.html',
	'system_patches_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'system_patches.php' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'systempatches.xml' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'vpc_vpn_wizard.xml' => 'https://docs.netgate.com/pfsense/en/latest/solutions/aws-vpn-appliance/vpc-wizard-guide.html'
);

$pagename = "";
/* Check for parameter "page". */
if ($_REQUEST && isset($_REQUEST['page'])) {
	$pagename = $_REQUEST['page'];
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

	/* If the referrer was index.php then this was a redirect to help.php
	   because help.php was the first page the user has priv to.
	   In that case we do not want to redirect off to the dashboard help. */
	if ($pagename == "index.php") {
		$pagename = "";
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
if (strlen($pagename) > 0) {
	if (array_key_exists($pagename, $helppages)) {
		$helppage = $helppages[$pagename];
	} else {
		// If no specific page was found, use a generic help page
		$helppage = 'https://docs.netgate.com/pfsense/en/latest/index.html';
	}

	/* Redirect to help page. */
	header("Location: {$helppage}");
}

// No page name was determined, so show a message.
$pgtitle = array(gettext("Help"), gettext("About this Page"));
require_once("head.inc");

if (is_array($allowedpages) && str_replace('*', '', $allowedpages[0]) == "help.php") {
	if (count($allowedpages) == 1) {
		print_info_box(gettext("The Help page is the only page this user has privilege for."));
	} else {
		print_info_box(gettext("Displaying the Help page because it is the first page this user has privilege for."));
	}
} else {
	print_info_box(gettext("Help page accessed directly without any page parameter."));
}

include("foot.inc");
?>
