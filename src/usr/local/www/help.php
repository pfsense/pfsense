<?php
/*
 * help.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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
	'index.php' => 'https://docs.netgate.com/pfsense/en/latest/config/dashboard.html',

	'crash_reporter.php' => 'https://docs.netgate.com/pfsense/en/latest/hardware/unexpected-reboot-troubleshooting.html',
	'diag_arp.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/arp-table.html',
	'diag_authentication.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/user-authentication-servers.html',
	'diag_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/configuration-backup-and-restore.html',
	'diag_command.php' => 'https://docs.netgate.com/pfsense/en/latest/development/execute-command.html',
	'diag_confbak.php' => 'https://docs.netgate.com/pfsense/en/latest/config/configuration-history.html',
	'diag_defaults.php' => 'https://docs.netgate.com/pfsense/en/latest/config/factory-defaults.html',
	'diag_dns.php' => 'https://docs.netgate.com/pfsense/en/latest/dns/dns-lookup.html',
	'diag_dump_states.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/show-states.html',
	'diag_dump_states_sources.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/show-source-tracking.html',
	'diag_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/development/edit-file.html',
	'diag_halt.php' => 'https://docs.netgate.com/pfsense/en/latest/hardware/halt-system.html',
	'diag_limiter_info.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/limiters.html',
	'diag_ndp.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/ndp-table.html',
	'diag_packet_capture.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/performing-a-packet-capture.html',
	'diag_pf_info.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/packet-filter-information.html',
	'diag_pftop.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/monitoring-bandwidth-usage.html',
	'diag_ping.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/ping-host.html',
	'diag_reboot.php' => 'https://docs.netgate.com/pfsense/en/latest/hardware/reboot-system.html',
	'diag_resetstate.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/reset-states.html',
	'diag_routes.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/viewing-routes.html',
	'diag_smart.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/smart-status.html',
	'diag_sockets.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/viewing-active-network-sockets.html',
	'diag_states_summary.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/states-summary.html',
	'diag_system_activity.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/system-activity.html',
	'diag_tables.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/tables.html',
	'diag_testport.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/test-port.html',
	'diag_traceroute.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/traceroute.html',
	'firewall_aliases_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/aliases.html',
	'firewall_aliases_import.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/aliases.html',
	'firewall_aliases.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/aliases.html',
	'firewall_nat_1to1_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/1-1-nat.html#configuring-1-1-nat',
	'firewall_nat_1to1.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/1-1-nat.html',
	'firewall_nat_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/port-forwards.html#adding-port-forwards',
	'firewall_nat_npt_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/ipv6-network-prefix-translation-npt.html',
	'firewall_nat_npt.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/ipv6-network-prefix-translation-npt.html',
	'firewall_nat_out_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/outbound-nat.html#working-with-manual-outbound-nat-rules',
	'firewall_nat_out.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/outbound-nat.html',
	'firewall_nat.php' => 'https://docs.netgate.com/pfsense/en/latest/book/nat/port-forwards.html',
	'firewall_rules_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/configuring-firewall-rules.html',
	'firewall_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/introduction-to-the-firewall-rules-screen.html',
	'firewall_schedule_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/time-based-rules.html#defining-times-for-a-schedule',
	'firewall_schedule.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/time-based-rules.html',
	'firewall_shaper.php' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/index.html',
	'firewall_shaper_queues.php' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/index.html',
	'firewall_shaper_vinterface.php' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/limiters.html',
	'firewall_shaper_wizards.php' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/configuring-the-altq-traffic-shaper-with-the-wizard.html',
	'firewall_virtual_ip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/virtual-ip-addresses.html',
	'firewall_virtual_ip.php' => 'https://docs.netgate.com/pfsense/en/latest/book/firewall/virtual-ip-addresses.html',
	'interfaces_assign.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/interface-configuration.html',
	'interfaces_bridge_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/bridging/creating-a-bridge.html',
	'interfaces_bridge.php' => 'https://docs.netgate.com/pfsense/en/latest/book/bridging/index.html',
	'interfaces_gif_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-gif.html',
	'interfaces_gif.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-gif.html',
	'interfaces_gre_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-gre.html',
	'interfaces_gre.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-gre.html',
	'interfaces_groups_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-groups.html',
	'interfaces_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-groups.html',
	'interfaces_lagg_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-lagg.html',
	'interfaces_lagg.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-lagg.html',
	'interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interface-configuration.html',
	'interfaces_ppps_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-ppps.html',
	'interfaces_ppps.php' => 'https://docs.netgate.com/pfsense/en/latest/book/interfaces/interfacetypes-ppps.html',
	'interfaces_qinq_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/vlan/pfsense-qinq-configuration.html',
	'interfaces_qinq.php' => 'https://docs.netgate.com/pfsense/en/latest/book/vlan/pfsense-qinq-configuration.html',
	'interfaces_vlan_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/vlan/pfsense-vlan-configuration.html#web-interface-vlan-configuration',
	'interfaces_vlan.php' => 'https://docs.netgate.com/pfsense/en/latest/book/vlan/index.html',
	'interfaces_wireless_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/wireless/working-with-virtual-access-point-wireless-interfaces.html',
	'interfaces_wireless.php' => 'https://docs.netgate.com/pfsense/en/latest/book/wireless/index.html',
	'miniupnpd.xml' => 'https://docs.netgate.com/pfsense/en/latest/book/services/upnp-and-nat-pmp.html',
	'openvpn_wizard.xml' => 'https://docs.netgate.com/pfsense/en/latest/book/openvpn/using-the-openvpn-server-wizard-for-remote-access.html',
	'openvpn-client-export.xml' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'pkg_mgr_installed.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/package-manager.html',
	'pkg_mgr_install.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/package-manager.html',
	'pkg_mgr.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/package-manager.html',
	'services_acb_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/book/backup/using-the-autoconfigbackup-package.html',
	'services_acb_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/book/backup/using-the-autoconfigbackup-package.html',
	'services_acb.php' => 'https://docs.netgate.com/pfsense/en/latest/book/backup/using-the-autoconfigbackup-package.html',
	'services_captiveportal_filemanager.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/file-manager.html',
	'services_captiveportal_hostname_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/allowed-hostnames.html',
	'services_captiveportal_hostname.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/allowed-hostnames.html',
	'services_captiveportal_ip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/allowed-ip-address.html',
	'services_captiveportal_ip.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/allowed-ip-address.html',
	'services_captiveportal_mac_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/mac-address-control.html',
	'services_captiveportal_mac.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/mac-address-control.html',
	'services_captiveportal.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'services_captiveportal_vouchers_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'services_captiveportal_vouchers.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/vouchers.html',
	'services_captiveportal_zones_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/captive-portal-zones.html',
	'services_captiveportal_zones.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/index.html',
	'services_checkip_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns-chceckip.html',
	'services_checkip.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns-chceckip.html',
	'services_dhcp_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dhcp/dhcp-server.html',
	'services_dhcp.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ipv4-dhcp-server.html',
	'services_dhcp_relay.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dhcp-and-dhcpv6-relay.html',
	'services_dhcpv6_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dhcp/dhcpv6-server.html',
	'services_dhcpv6.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ipv6-dhcp-server-and-router-advertisements.html',
	'services_dhcpv6_relay.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ipv4-dhcp-server.html',
	'services_dnsmasq_domainoverride_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dns/dns-forwarder.html',
	'services_dnsmasq_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dns/dns-forwarder.html',
	'services_dnsmasq.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dns-forwarder.html',
	'services_dyndns_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns-client.html',
	'services_dyndns.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns.html',
	'services_igmpproxy_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/igmp-proxy.html',
	'services_igmpproxy.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/igmp-proxy.html',
	'services_ntpd_acls.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ntpd-server.html#access-restrictions',
	'services_ntpd_gps.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ntpd-gps.html',
	'services_ntpd.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ntpd-server.html',
	'services_ntpd_pps.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ntpd-pps.html',
	'services_pppoe_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/pppoe-server.html',
	'services_pppoe.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/pppoe-server.html',
	'services_rfc2136_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns-rfc2136.html',
	'services_rfc2136.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dynamic-dns-rfc2136.html',
	'services_router_advertisements.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/ipv6-dhcp-server-and-router-advertisements.html',
	'services_snmp.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/snmp.html',
	'services_unbound_acls.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dns-resolver-acls.html',
	'services_unbound_advanced.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dns-resolver-advanced.html',
	'services_unbound_domainoverride_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dns/unbound-dns-resolver.html',
	'services_unbound_host_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/dns/unbound-dns-resolver.html',
	'services_unbound.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dns-resolver.html',
	'services_wol_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/wake-on-lan.html#storing-mac-addresses',
	'services_wol.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/wake-on-lan.html',
	'status_captiveportal_expire.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'status_captiveportal.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'status_captiveportal_test.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'status_captiveportal_voucher_rolls.php' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'status_captiveportal_vouchers.php' => 'https://docs.netgate.com/pfsense/en/latest/book/captiveportal/index.html',
	'status_carp.php' => 'https://docs.netgate.com/pfsense/en/latest/book/highavailability/verifying-failover-functionality.html#check-carp-status',
	'status_dhcp_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/dhcp/dhcp-leases.html',
	'status_dhcpv6_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/dhcp/dhcpv6-leases.html',
	'status_filter_reload.php' => 'https://docs.netgate.com/pfsense/en/latest/firewall/filter-reload-status.html',
	'status_gateway_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/gateway-status.html',
	'status_gateways.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/gateway-status.html',
	'status_graph.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/traffic-graph.html',
	'status_interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/interface-status.html',
	'status_ipsec_leases.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-mobile-clients.html',
	'status_ipsec.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-status.html',
	'status_ipsec_sad.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-status.html',
	'status_ipsec_spd.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-status.html',
	'status_logs_filter_dynamic.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/firewall-logs.html',
	'status_logs_filter.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/firewall-logs.html',
	'status_logs_filter_summary.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/firewall-logs.html',
	'status_logs.php-dhcpd' => 'https://docs.netgate.com/pfsense/en/latest/dhcp/dhcp-logs.html',
	'status_logs.php-gateways' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/gateway-logs.html',
	'status_logs.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/system-logs.html',
	'status_logs.php-ipsec' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-logs.html',
	'status_logs.php-ntpd' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/ntp-logs.html',
	'status_logs.php-openvpn' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'status_logs.php-portalauth' => 'https://docs.netgate.com/pfsense/en/latest/captiveportal/captive-portal.html',
	'status_logs.php-ppp' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/ppp-logs.html',
	'status_logs.php-resolver' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/resolver-logs.html',
	'status_logs.php-routing' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/routing-logs.html',
	'status_logs.php-wireless' => 'https://docs.netgate.com/pfsense/en/latest/wireless/wireless-logs.html',
	'status_logs_packages.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/package-logs.html',
	'status_logs_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/log-settings.html',
	'status_logs_vpn.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/system-logs.html',
	'status_monitoring.php' => 'https://docs.netgate.com/pfsense/en/latest/book/monitoring/graphs.html',
	'status_ntpd.php' => 'https://docs.netgate.com/pfsense/en/latest/services/ntp-server.html',
	'status_openvpn.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'status_queues.php' => 'https://docs.netgate.com/pfsense/en/latest/trafficshaper/traffic-shaping-guide.html',
	'status_services.php' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/services-status.html',
	'status_unbound.php' => 'https://docs.netgate.com/pfsense/en/latest/book/services/dns-resolver.html',
	'status_upnp.php' => 'https://docs.netgate.com/pfsense/en/latest/services/configuring-upnp-and-nat-pmp.html',
	'status_wireless.php' => 'https://docs.netgate.com/pfsense/en/latest/wireless/wireless-status.html',
	'system_advanced_admin.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-admin.html',
	'system_advanced_firewall.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-firewall-nat.html',
	'system_advanced_misc.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-misc.html',
	'system_advanced_network.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-networking.html',
	'system_advanced_notifications.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-notifications.html',
	'system_advanced_sysctl.php' => 'https://docs.netgate.com/pfsense/en/latest/book/config/advanced-tunables.html',
	'system_authservers.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/user-authentication-servers.html',
	'system_camanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/certificate-management.html',
	'system_certmanager_renew.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/certificate-management.html',
	'system_certmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/certificate-management.html',
	'system_crlmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/certificate-management.html',
	'system_gateway_groups_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-settings.html',
	'system_gateway_groups.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-settings.html',
	'system_gateways_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-settings.html',
	'system_gateways.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/gateway-settings.html',
	'system_groupmanager_addprivs.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/group-manager.html',
	'system_groupmanager.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/group-manager.html',
	'system_hasync.php' => 'https://docs.netgate.com/pfsense/en/latest/highavailability/index.html',
	'system.php' => 'https://docs.netgate.com/pfsense/en/latest/config/general-setup.html',
	'system_routes_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/static-routes.html',
	'system_routes.php' => 'https://docs.netgate.com/pfsense/en/latest/routing/static-routes.html',
	'system_update_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/install/upgrading-pfsense-software-installations.html',
	'system_usermanager_addprivs.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/managing-local-users.html',
	'system_usermanager_passwordmg.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/managing-local-users.html',
	'system_usermanager.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/managing-local-users.html',
	'system_usermanager_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/managing-local-users.html',
	'system_user_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/managing-local-users.html',
	'traffic_shaper_wizard_dedicated.xml' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/configuring-the-altq-traffic-shaper-with-the-wizard.html',
	'traffic_shaper_wizard_multi_all.xml' => 'https://docs.netgate.com/pfsense/en/latest/book/trafficshaper/configuring-the-altq-traffic-shaper-with-the-wizard.html',
	'vpn_ipsec_keys_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/ipsec/ipsec-tunnels.html',
	'vpn_ipsec_keys.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/mobile-ipsec-example-ikev2.html#mobile-ipsec-user-creation',
	'vpn_ipsec_mobile.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/mobile-ipsec.html',
	'vpn_ipsec_phase1.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/choosing-configuration-options.html#phase-1-settings',
	'vpn_ipsec_phase2.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/choosing-configuration-options.html#phase-2-settings',
	'vpn_ipsec.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/index.html',
	'vpn_ipsec_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/book/ipsec/ipsec-advanced-settings.html',
	'vpn_l2tp.php' => 'https://docs.netgate.com/pfsense/en/latest/book/l2tp/l2tp-server-configuration.html',
	'vpn_l2tp_users_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/l2tp-vpn-settings.html',
	'vpn_l2tp_users.php' => 'https://docs.netgate.com/pfsense/en/latest/book/l2tp/l2tp-server-configuration.html#adding-users',
	'vpn_openvpn_client.php' => 'https://docs.netgate.com/pfsense/en/latest/book/openvpn/index.html',
	'vpn_openvpn_csc.php' => 'https://docs.netgate.com/pfsense/en/latest/book/openvpn/index.html',
	'vpn_openvpn_export.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_export_shared.php' => 'https://docs.netgate.com/pfsense/en/latest/vpn/openvpn/index.html',
	'vpn_openvpn_server.php' => 'https://docs.netgate.com/pfsense/en/latest/book/openvpn/index.html',

	/* Packages from here on down. Not checked as strictly. Pages may not yet exist. */
	'acme/acme_accountkeys_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'acme/acme_accountkeys.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'acme/acme_certificates_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'acme/acme_certificates.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'acme/acme_generalsettings.php' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'acme.xml' => 'https://docs.netgate.com/pfsense/en/latest/certificates/acme-package.html',
	'arping.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/arping-package.html',
	'autoconfigbackup_backup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup_stats.php' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'autoconfigbackup.xml' => 'https://docs.netgate.com/pfsense/en/latest/backup/autoconfigbackup.html',
	'avahi.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/avahi-package.html',
	'darkstat.xml' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/monitoring-bandwidth-usage.html',
	'freeradiusauthorizedmacs.xml' => 'https://www.netgate.com/docs/pfsense/usermanager/plain-mac-authentication-with-freeradius.rst',
	'freeradiuscerts.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradiusclients.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradiuseapconf.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/using-eap-and-peap-with-freeradius.html',
	'freeradiusinterfaces.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradiusmodulesldap.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradiussettings.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradiussqlconf.xml' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/using-mysql-with-freeradius.html',
	'freeradiussync.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradius_view_config.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'freeradius.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/freeradius-package.html',
	'haproxy/haproxy_files.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_global.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_listeners_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_listeners.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_pool_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_pools.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_stats.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy/haproxy_templates.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'haproxy.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/haproxy-package.html',
	'iperfserver.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/iperf-package.html',
	'iperf.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/iperf-package.html',
	'ladvd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/lldp-on-pfsense.html',
	'nmap.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/nmap-package.html',
	'ntopng.xml' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/monitoring-bandwidth-usage.html',
	'nut_settings.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut-package.html',
	'nut_status.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut-package.html',
	'nut.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/nut-package.html',
	'openbgpd_groups.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd-package.html',
	'openbgpd_neighbors.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd-package.html',
	'openbgpd_raw.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd-package.html',
	'openbgpd_status.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd-package.html',
	'openbgpd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/openbgpd-package.html',
	'open-vm-tools.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/open-vm-tools-package.html',
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
	'routed.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/routing-information-protocol-rip.html',
	'shellcmd.xml' => 'https://docs.netgate.com/pfsense/en/latest/development/executing-commands-at-boot-time.html',
	'siproxd_registered_phones.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd-package.html',
	'siproxdusers.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd-package.html',
	'siproxd.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/siproxd-package.html',
	'snort/snort_alerts.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-alerts.html',
	'snort/snort_barnyard.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-barnyard2.html',
	'snort/snort_blocked.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-blocked-hosts.html',
	'snort/snort_define_servers.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-define-servers.html',
	'snort/snort_download_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-updates.html',
	'snort/snort_download_updates.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-updates.html',
	'snort/snort_ftp_client_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_ftp_server_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_httpinspect_engine.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_interfaces_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-interfaces.html',
	'snort/snort_interfaces_global.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-interfaces.html',
	'snort/snort_interfaces.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-interfaces.html',
	'snort/snort_interfaces_suppress_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-suppress-list.html',
	'snort/snort_interfaces_suppress.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-suppress-list.html',
	'snort/snort_ip_list_mgmt.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-ip-list-mgmt.html',
	'snort/snort_iprep_list_browser.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-ip-reputation.html',
	'snort/snort_ip_reputation.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-ip-reputation.html',
	'snort/snort_passlist_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-passlist.html',
	'snort/snort_passlist.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-passlist.html',
	'snort/snort_preprocessors.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-preprocessors.html',
	'snort/snort_rules_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-rules.html',
	'snort/snort_rulesets.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-rules.html',
	'snort/snort_rules_flowbits.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-rules.html',
	'snort/snort_rules.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-rules.html',
	'snort/snort_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/snort-xmlrpc-synchronization.html',
	'snort.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/setup-snort-package.html',
	'softflowd.xml' => 'https://docs.netgate.com/pfsense/en/latest/monitoring/exporting-netflow-with-softflowd.html',
	'squid_antivirus.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_auth.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_cache.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squidguard_acl.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard_default.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard_dest.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard_rewr.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidGuard/squidguard_blacklist.php' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidGuard/squidguard_log.php' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard_time.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squidguard.xml' => 'https://docs.netgate.com/pfsense/en/latest/cache-proxy/squidguard-package.html',
	'squid_log_parser.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_monitor_data.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_monitor.php' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_reverse_general.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_reverse_peer.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_reverse_redir.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_reverse_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_reverse_uri.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_sync.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_traffic.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_upstream.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid_users.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'squid.xml' => 'https://docs.netgate.com/pfsense/en/latest/ids-ips/index.html',
	'status_ladvd.php' => 'https://docs.netgate.com/pfsense/en/latest/packages/lldp-on-pfsense.html',
	'stunnel_certs.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/stunnel-package.html',
	'stunnel.xml' => 'https://docs.netgate.com/pfsense/en/latest/packages/stunnel-package.html',
	'sudo.xml' => 'https://docs.netgate.com/pfsense/en/latest/usermanager/sudo-package.html',
	'system_patches_edit.php' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'system_patches.php' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'systempatches.xml' => 'https://docs.netgate.com/pfsense/en/latest/development/system-patches.html',
	'vpc_vpn_wizard.xml' => 'https://docs.netgate.com/pfsense/en/latest/solutions/aws-vpn-appliance/vpc-wizard-guide.html',
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
