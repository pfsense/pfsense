<?php
/*
 * priv.defs.inc - Generated privilege definitions
 *
 */

$priv_list = array();

$priv_list['page-all'] = array();
$priv_list['page-all']['name'] = gettext("WebCfg - All pages");
$priv_list['page-all']['descr'] = gettext("Allow access to all pages");
$priv_list['page-all']['match'] = array();
$priv_list['page-all']['match'][] = "*";

$priv_list['page-status-carp'] = array();
$priv_list['page-status-carp']['name'] = gettext("WebCfg - Status: CARP page");
$priv_list['page-status-carp']['descr'] = gettext("Allow access to the 'Status: CARP' page.");
$priv_list['page-status-carp']['match'] = array();
$priv_list['page-status-carp']['match'][] = "carp_status.php*";

$priv_list['page-diagnostics-crash-reporter'] = array();
$priv_list['page-diagnostics-crash-reporter']['name'] = gettext("WebCfg - Crash reporter");
$priv_list['page-diagnostics-crash-reporter']['descr'] = gettext("Uploads crash reports to pfSense and or deletes crash reports.");
$priv_list['page-diagnostics-crash-reporter']['match'] = array();
$priv_list['page-diagnostics-crash-reporter']['match'][] = "crash_reporter.php*";

$priv_list['page-diagnostics-arptable'] = array();
$priv_list['page-diagnostics-arptable']['name'] = gettext("WebCfg - Diagnostics: ARP Table page");
$priv_list['page-diagnostics-arptable']['descr'] = gettext("Allow access to the 'Diagnostics: ARP Table' page.");
$priv_list['page-diagnostics-arptable']['match'] = array();
$priv_list['page-diagnostics-arptable']['match'][] = "diag_arp.php*";

$priv_list['page-diagnostics-authentication'] = array();
$priv_list['page-diagnostics-authentication']['name'] = gettext("WebCfg - Diagnostics: Authentication page");
$priv_list['page-diagnostics-authentication']['descr'] = gettext("Allow access to the 'Diagnostics: Authentication' page.");
$priv_list['page-diagnostics-authentication']['match'] = array();
$priv_list['page-diagnostics-authentication']['match'][] = "diag_authentication.php*";

$priv_list['page-diagnostics-backup/restore'] = array();
$priv_list['page-diagnostics-backup/restore']['name'] = gettext("WebCfg - Diagnostics: Backup/restore page");
$priv_list['page-diagnostics-backup/restore']['descr'] = gettext("Allow access to the 'Diagnostics: Backup/restore' page.");
$priv_list['page-diagnostics-backup/restore']['match'] = array();
$priv_list['page-diagnostics-backup/restore']['match'][] = "diag_backup.php*";

$priv_list['page-diagnostics-configurationhistory'] = array();
$priv_list['page-diagnostics-configurationhistory']['name'] = gettext("WebCfg - Diagnostics: Configuration History page");
$priv_list['page-diagnostics-configurationhistory']['descr'] = gettext("Allow access to the 'Diagnostics: Configuration History' page.");
$priv_list['page-diagnostics-configurationhistory']['match'] = array();
$priv_list['page-diagnostics-configurationhistory']['match'][] = "diag_confbak.php*";

$priv_list['page-diagnostics-factorydefaults'] = array();
$priv_list['page-diagnostics-factorydefaults']['name'] = gettext("WebCfg - Diagnostics: Factory defaults page");
$priv_list['page-diagnostics-factorydefaults']['descr'] = gettext("Allow access to the 'Diagnostics: Factory defaults' page.");
$priv_list['page-diagnostics-factorydefaults']['match'] = array();
$priv_list['page-diagnostics-factorydefaults']['match'][] = "diag_defaults.php*";

$priv_list['page-diagnostics-ndptable'] = array();
$priv_list['page-diagnostics-ndptable']['name'] = gettext("Webcfg - Diagnostics: NDP Table page");
$priv_list['page-diagnostics-ndptable']['descr'] = gettext("Allow access to the 'Diagnostics: NDP Table' page.");
$priv_list['page-diagnostics-ndptable']['match'] = array();
$priv_list['page-diagnostics-ndptable']['match'][] = "diag_ndp.php*";

$priv_list['page-diagnostics-restore-full-backup'] = array();
$priv_list['page-diagnostics-restore-full-backup']['name'] = gettext("Webcfg - Diagnostics: Restore full backup");
$priv_list['page-diagnostics-restore-full-backup']['descr'] = gettext("Allow access to the 'Diagnostics: Restore Full Backup' page.");
$priv_list['page-diagnostics-restore-full-backup']['match'] = array();
$priv_list['page-diagnostics-restore-full-backup']['match'][] = "system_firmware_restorefullbackup.php";

$priv_list['page-diagnostics-showstates'] = array();
$priv_list['page-diagnostics-showstates']['name'] = gettext("WebCfg - Diagnostics: Show States page");
$priv_list['page-diagnostics-showstates']['descr'] = gettext("Allow access to the 'Diagnostics: Show States' page.");
$priv_list['page-diagnostics-showstates']['match'] = array();
$priv_list['page-diagnostics-showstates']['match'][] = "diag_dump_states.php*";

$priv_list['page-diagnostics-sockets'] = array();
$priv_list['page-diagnostics-sockets']['name'] = gettext("WebCfg - Diagnostics: Sockets page");
$priv_list['page-diagnostics-sockets']['descr'] = gettext("Allow access to the 'Diagnostics: Sockets' page.");
$priv_list['page-diagnostics-sockets']['match'] = array();
$priv_list['page-diagnostics-sockets']['match'][] = "diag_sockets.php*";

$priv_list['page-diagnostics-testport'] = array();
$priv_list['page-diagnostics-testport']['name'] = gettext("Webcfg - Diagnostics: Test Port");
$priv_list['page-diagnostics-testport']['descr'] = gettext("Allow access to the 'Diagnostics: Test Port' page.");
$priv_list['page-diagnostics-testport']['match'] = array();
$priv_list['page-diagnostics-testport']['match'][] = "diag_testport.php*";

$priv_list['page-status-ipsec'] = array();
$priv_list['page-status-ipsec']['name'] = gettext("WebCfg - Status: IPsec page");
$priv_list['page-status-ipsec']['descr'] = gettext("Allow access to the 'Status: IPsec' page.");
$priv_list['page-status-ipsec']['match'] = array();
$priv_list['page-status-ipsec']['match'][] = "diag_ipsec.php*";

$priv_list['page-status-ipsec-leases'] = array();
$priv_list['page-status-ipsec-leases']['name'] = gettext("WebCfg - Status: IPsec: Leasespage");
$priv_list['page-status-ipsec-leases']['descr'] = gettext("Allow access to the 'Status: IPsec: Leases' page.");
$priv_list['page-status-ipsec-leases']['match'] = array();
$priv_list['page-status-ipsec-leases']['match'][] = "diag_ipsec_leases.php*";

$priv_list['page-status-ipsec-sad'] = array();
$priv_list['page-status-ipsec-sad']['name'] = gettext("WebCfg - Status: IPsec: SAD page");
$priv_list['page-status-ipsec-sad']['descr'] = gettext("Allow access to the 'Status: IPsec: SAD' page.");
$priv_list['page-status-ipsec-sad']['match'] = array();
$priv_list['page-status-ipsec-sad']['match'][] = "diag_ipsec_sad.php*";

$priv_list['page-status-ipsec-spd'] = array();
$priv_list['page-status-ipsec-spd']['name'] = gettext("WebCfg - Status: IPsec: SPD page");
$priv_list['page-status-ipsec-spd']['descr'] = gettext("Allow access to the 'Status: IPsec: SPD' page.");
$priv_list['page-status-ipsec-spd']['match'] = array();
$priv_list['page-status-ipsec-spd']['match'][] = "diag_ipsec_spd.php*";

$priv_list['page-status-ntp'] = array();
$priv_list['page-status-ntp']['name'] = gettext("Webcfg - Status: NTP page");
$priv_list['page-status-ntp']['descr'] = gettext("Allow access to the 'Status: NTP' page.");
$priv_list['page-status-ntp']['match'] = array();
$priv_list['page-status-ntp']['match'][] = "status_ntpd.php*";

$priv_list['page-ipsecxml'] = array();
$priv_list['page-ipsecxml']['name'] = gettext("WebCfg - Diag IPsec XML page");
$priv_list['page-ipsecxml']['descr'] = gettext("Allow access to the 'Diag IPsec XML' page.");
$priv_list['page-ipsecxml']['match'] = array();
$priv_list['page-ipsecxml']['match'][] = "diag_ipsec_xml.php";

$priv_list['page-diagnostics-logs-system'] = array();
$priv_list['page-diagnostics-logs-system']['name'] = gettext("WebCfg - Diagnostics: Logs: System page");
$priv_list['page-diagnostics-logs-system']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: System' page.");
$priv_list['page-diagnostics-logs-system']['match'] = array();
$priv_list['page-diagnostics-logs-system']['match'][] = "diag_logs.php*";

$priv_list['page-status-systemlogs-portalauth'] = array();
$priv_list['page-status-systemlogs-portalauth']['name'] = gettext("WebCfg - Status: System logs: Portal Auth page");
$priv_list['page-status-systemlogs-portalauth']['descr'] = gettext("Allow access to the 'Status: System logs: Portal Auth' page.");
$priv_list['page-status-systemlogs-portalauth']['match'] = array();
$priv_list['page-status-systemlogs-portalauth']['match'][] = "diag_logs_auth.php*";

$priv_list['page-diagnostics-logs-dhcp'] = array();
$priv_list['page-diagnostics-logs-dhcp']['name'] = gettext("WebCfg - Diagnostics: Logs: DHCP page");
$priv_list['page-diagnostics-logs-dhcp']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: DHCP' page.");
$priv_list['page-diagnostics-logs-dhcp']['match'] = array();
$priv_list['page-diagnostics-logs-dhcp']['match'][] = "diag_logs_dhcp.php*";

$priv_list['page-diagnostics-logs-firewall'] = array();
$priv_list['page-diagnostics-logs-firewall']['name'] = gettext("WebCfg - Diagnostics: Logs: Firewall page");
$priv_list['page-diagnostics-logs-firewall']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: Firewall' page.");
$priv_list['page-diagnostics-logs-firewall']['match'] = array();
$priv_list['page-diagnostics-logs-firewall']['match'][] = "diag_logs_filter.php*";

$priv_list['page-diagnostics-logs-gateways'] = array();
$priv_list['page-diagnostics-logs-gateways']['name'] = gettext("WebCfg - Diagnostics: Logs: Gateways page");
$priv_list['page-diagnostics-logs-gateways']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: System: Gateways' page.");
$priv_list['page-diagnostics-logs-gateways']['match'] = array();
$priv_list['page-diagnostics-logs-gateways']['match'][] = "diag_logs_gateways.php*";

$priv_list['page-diagnostics-logs-resolver'] = array();
$priv_list['page-diagnostics-logs-resolver']['name'] = gettext("WebCfg - Diagnostics: Logs: Resolver page");
$priv_list['page-diagnostics-logs-resolver']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: System: Resolver' page.");
$priv_list['page-diagnostics-logs-resolver']['match'] = array();
$priv_list['page-diagnostics-logs-resolver']['match'][] = "diag_logs_resolver.php*";

$priv_list['page-hidden-nolongerincluded'] = array();
$priv_list['page-hidden-nolongerincluded']['name'] = gettext("WebCfg - Hidden: No longer included page");
$priv_list['page-hidden-nolongerincluded']['descr'] = gettext("Allow access to the 'Hidden: No longer included' page.");
$priv_list['page-hidden-nolongerincluded']['match'] = array();
$priv_list['page-hidden-nolongerincluded']['match'][] = "diag_logs_filter_dynamic.php*";

$priv_list['page-status-systemlogs-ipsecvpn'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['name'] = gettext("WebCfg - Status: System logs: IPsec VPN page");
$priv_list['page-status-systemlogs-ipsecvpn']['descr'] = gettext("Allow access to the 'Status: System logs: IPsec VPN' page.");
$priv_list['page-status-systemlogs-ipsecvpn']['match'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['match'][] = "diag_logs_ipsec.php*";

$priv_list['page-status-systemlogs-ntpd'] = array();
$priv_list['page-status-systemlogs-ntpd']['name'] = gettext("WebCfg - Status: System logs: NTP page");
$priv_list['page-status-systemlogs-ntpd']['descr'] = gettext("Allow access to the 'Status: System logs: NTP' page.");
$priv_list['page-status-systemlogs-ntpd']['match'] = array();
$priv_list['page-status-systemlogs-ntpd']['match'][] = "diag_logs_ntpd.php*";

$priv_list['page-status-systemlogs-openvpn'] = array();
$priv_list['page-status-systemlogs-openvpn']['name'] = gettext("WebCfg - Status: System logs: OpenVPN page");
$priv_list['page-status-systemlogs-openvpn']['descr'] = gettext("Allow access to the 'Status: System logs: OpenVPN' page.");
$priv_list['page-status-systemlogs-openvpn']['match'] = array();
$priv_list['page-status-systemlogs-openvpn']['match'][] = "diag_logs_openvpn.php*";

$priv_list['page-status-systemlogs-ppp'] = array();
$priv_list['page-status-systemlogs-ppp']['name'] = gettext("WebCfg - Status: System logs: IPsec VPN page");
$priv_list['page-status-systemlogs-ppp']['descr'] = gettext("Allow access to the 'Status: System logs: IPsec VPN' page.");
$priv_list['page-status-systemlogs-ppp']['match'] = array();
$priv_list['page-status-systemlogs-ppp']['match'][] = "diag_logs_ppp.php*";

$priv_list['page-status-systemlogs-loadbalancer'] = array();
$priv_list['page-status-systemlogs-loadbalancer']['name'] = gettext("WebCfg - Status: System logs: Load Balancer page");
$priv_list['page-status-systemlogs-loadbalancer']['descr'] = gettext("Allow access to the 'Status: System logs: Load Balancer' page.");
$priv_list['page-status-systemlogs-loadbalancer']['match'] = array();
$priv_list['page-status-systemlogs-loadbalancer']['match'][] = "diag_logs_relayd.php*";

$priv_list['page-status-systemlogs-routing'] = array();
$priv_list['page-status-systemlogs-routing']['name'] = gettext("Webcfg - Status: System logs: Routing page");
$priv_list['page-status-systemlogs-routing']['descr'] = gettext("Allow access to the 'Status: System logs: System: Routing' page.");
$priv_list['page-status-systemlogs-routing']['match'] = array();
$priv_list['page-status-systemlogs-routing']['match'][] = "diag_logs_routing.php*";

$priv_list['page-status-systemlogs-wireless'] = array();
$priv_list['page-status-systemlogs-wireless']['name'] = gettext("Webcfg - Status: System logs: Wireless page");
$priv_list['page-status-systemlogs-wireless']['descr'] = gettext("Allow access to the 'Status: System logs: System: Wireless' page.");
$priv_list['page-status-systemlogs-wireless']['match'] = array();
$priv_list['page-status-systemlogs-wireless']['match'][] = "diag_logs_wireless.php*";

$priv_list['page-diagnostics-logs-settings'] = array();
$priv_list['page-diagnostics-logs-settings']['name'] = gettext("WebCfg - Diagnostics: Logs: Settings page");
$priv_list['page-diagnostics-logs-settings']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: Settings' page.");
$priv_list['page-diagnostics-logs-settings']['match'] = array();
$priv_list['page-diagnostics-logs-settings']['match'][] = "diag_logs_settings.php*";

$priv_list['page-diagnostics-logs-pptpvpn'] = array();
$priv_list['page-diagnostics-logs-pptpvpn']['name'] = gettext("WebCfg - Diagnostics: Logs: VPN page");
$priv_list['page-diagnostics-logs-pptpvpn']['descr'] = gettext("Allow access to the 'Diagnostics: Logs: VPN' page.");
$priv_list['page-diagnostics-logs-pptpvpn']['match'] = array();
$priv_list['page-diagnostics-logs-pptpvpn']['match'][] = "diag_logs_vpn.php*";

$priv_list['page-diagnostics-nanobsd'] = array();
$priv_list['page-diagnostics-nanobsd']['name'] = gettext("WebCfg - Diagnostics: NanoBSD");
$priv_list['page-diagnostics-nanobsd']['descr'] = gettext("Allow access to the 'Diagnostics: NanoBSD' page.");
$priv_list['page-diagnostics-nanobsd']['match'] = array();
$priv_list['page-diagnostics-nanobsd']['match'][] = "diag_nanobsd.php*";

$priv_list['page-diagnostics-packetcapture'] = array();
$priv_list['page-diagnostics-packetcapture']['name'] = gettext("WebCfg - Diagnostics: Packet Capture page");
$priv_list['page-diagnostics-packetcapture']['descr'] = gettext("Allow access to the 'Diagnostics: Packet Capture' page.");
$priv_list['page-diagnostics-packetcapture']['match'] = array();
$priv_list['page-diagnostics-packetcapture']['match'][] = "diag_packet_capture.php*";

$priv_list['page-diagnostics-patters'] = array();
$priv_list['page-diagnostics-patters']['name'] = gettext("WebCfg - Diagnostics: Patterns page");
$priv_list['page-diagnostics-patters']['descr'] = gettext("Allow access to the 'Diagnostics: Patterns' page.");
$priv_list['page-diagnostics-patters']['match'] = array();
$priv_list['page-diagnostics-patters']['match'][] = "patterns.php*";

$priv_list['page-diagnostics-limiter-info'] = array();
$priv_list['page-diagnostics-limiter-info']['name'] = gettext("Diagnostics: Limiter Info");
$priv_list['page-diagnostics-limiter-info']['descr'] = gettext("Allows access to the 'Diagnostics: Limiter Info' page");
$priv_list['page-diagnostics-limiter-info']['match'] = array();
$priv_list['page-diagnostics-limiter-info']['match'][] = "diag_limiter_info.php*";

$priv_list['page-diagnostics-pf-info'] = array();
$priv_list['page-diagnostics-pf-info']['name'] = gettext("Diagnostics: pfInfo");
$priv_list['page-diagnostics-pf-info']['descr'] = gettext("Allows access to the 'Diagnostics: pfInfo' page");
$priv_list['page-diagnostics-pf-info']['match'] = array();
$priv_list['page-diagnostics-pf-info']['match'][] = "diag_pf_info.php*";

$priv_list['page-diagnostics-system-activity'] = array();
$priv_list['page-diagnostics-system-activity']['name'] = gettext("WebCfg - Diagnostics: System Activity");
$priv_list['page-diagnostics-system-activity']['descr'] = gettext("Allows access to the 'Diagnostics: System Activity' page");
$priv_list['page-diagnostics-system-activity']['match'] = array();
$priv_list['page-diagnostics-system-activity']['match'][] = "diag_system_activity.php*";

$priv_list['page-diagnostics-system-pftop'] = array();
$priv_list['page-diagnostics-system-pftop']['name'] = gettext("Diagnostics: pfTop");
$priv_list['page-diagnostics-system-pftop']['descr'] = gettext("Allows access to the 'Diagnostics: pfTop' page");
$priv_list['page-diagnostics-system-pftop']['match'] = array();
$priv_list['page-diagnostics-system-pftop']['match'][] = "diag_system_pftop.php*";

$priv_list['page-diagnostics-ping'] = array();
$priv_list['page-diagnostics-ping']['name'] = gettext("WebCfg - Diagnostics: Ping page");
$priv_list['page-diagnostics-ping']['descr'] = gettext("Allow access to the 'Diagnostics: Ping' page.");
$priv_list['page-diagnostics-ping']['match'] = array();
$priv_list['page-diagnostics-ping']['match'][] = "diag_ping.php*";

$priv_list['page-status-packagelogs'] = array();
$priv_list['page-status-packagelogs']['name'] = gettext("WebCfg - Status: Package logs page");
$priv_list['page-status-packagelogs']['descr'] = gettext("Allow access to the 'Status: Package logs' page.");
$priv_list['page-status-packagelogs']['match'] = array();
$priv_list['page-status-packagelogs']['match'][] = "diag_pkglogs.php*";

$priv_list['page-diagnostics-resetstate'] = array();
$priv_list['page-diagnostics-resetstate']['name'] = gettext("WebCfg - Diagnostics: Reset state page");
$priv_list['page-diagnostics-resetstate']['descr'] = gettext("Allow access to the 'Diagnostics: Reset state' page.");
$priv_list['page-diagnostics-resetstate']['match'] = array();
$priv_list['page-diagnostics-resetstate']['match'][] = "diag_resetstate.php*";

$priv_list['page-diagnostics-routingtables'] = array();
$priv_list['page-diagnostics-routingtables']['name'] = gettext("WebCfg - Diagnostics: Routing tables page");
$priv_list['page-diagnostics-routingtables']['descr'] = gettext("Allow access to the 'Diagnostics: Routing tables' page.");
$priv_list['page-diagnostics-routingtables']['match'] = array();
$priv_list['page-diagnostics-routingtables']['match'][] = "diag_routes.php*";

$priv_list['page-diagnostics-statessummary'] = array();
$priv_list['page-diagnostics-statessummary']['name'] = gettext("WebCfg - Diagnostics: States Summary page");
$priv_list['page-diagnostics-statessummary']['descr'] = gettext("Allow access to the 'Diagnostics: States Summary' page.");
$priv_list['page-diagnostics-statessummary']['match'] = array();
$priv_list['page-diagnostics-statessummary']['match'][] = "diag_states_summary.php*";

$priv_list['page-diagnostics-tables'] = array();
$priv_list['page-diagnostics-tables']['name'] = gettext("WebCfg - Diagnostics: PF Table IP addresses");
$priv_list['page-diagnostics-tables']['descr'] = gettext("Allow access to the 'Diagnostics: Tables' page.");
$priv_list['page-diagnostics-tables']['match'] = array();
$priv_list['page-diagnostics-tables']['match'][] = "diag_tables.php*";

$priv_list['page-diagnostics-traceroute'] = array();
$priv_list['page-diagnostics-traceroute']['name'] = gettext("WebCfg - Diagnostics: Traceroute page");
$priv_list['page-diagnostics-traceroute']['descr'] = gettext("Allow access to the 'Diagnostics: Traceroute' page.");
$priv_list['page-diagnostics-traceroute']['match'] = array();
$priv_list['page-diagnostics-traceroute']['match'][] = "diag_traceroute.php*";

$priv_list['page-diagnostics-edit'] = array();
$priv_list['page-diagnostics-edit']['name'] = gettext("WebCfg - Diagnostics: Edit FIle");
$priv_list['page-diagnostics-edit']['descr'] = gettext("Allow access to the 'Diagnostics: Edit File' page.");
$priv_list['page-diagnostics-edit']['match'] = array();
$priv_list['page-diagnostics-edit']['match'][] = "edit.php*";
$priv_list['page-diagnostics-edit']['match'][] = "browser.php*";
$priv_list['page-diagnostics-edit']['match'][] = "filebrowser/browser.php*";

$priv_list['page-diagnostics-command'] = array();
$priv_list['page-diagnostics-command']['name'] = gettext("WebCfg - Diagnostics: Command page");
$priv_list['page-diagnostics-command']['descr'] = gettext("Allow access to the 'Diagnostics: Command' page.");
$priv_list['page-diagnostics-command']['match'] = array();
$priv_list['page-diagnostics-command']['match'][] = "exec.php*";

$priv_list['page-firewall-aliases'] = array();
$priv_list['page-firewall-aliases']['name'] = gettext("WebCfg - Firewall: Aliases page");
$priv_list['page-firewall-aliases']['descr'] = gettext("Allow access to the 'Firewall: Aliases' page.");
$priv_list['page-firewall-aliases']['match'] = array();
$priv_list['page-firewall-aliases']['match'][] = "firewall_aliases.php*";

$priv_list['page-firewall-alias-edit'] = array();
$priv_list['page-firewall-alias-edit']['name'] = gettext("WebCfg - Firewall: Alias: Edit page");
$priv_list['page-firewall-alias-edit']['descr'] = gettext("Allow access to the 'Firewall: Alias: Edit' page.");
$priv_list['page-firewall-alias-edit']['match'] = array();
$priv_list['page-firewall-alias-edit']['match'][] = "firewall_aliases_edit.php*";

$priv_list['page-firewall-alias-import'] = array();
$priv_list['page-firewall-alias-import']['name'] = gettext("WebCfg - Firewall: Alias: Import page");
$priv_list['page-firewall-alias-import']['descr'] = gettext("Allow access to the 'Firewall: Alias: Import' page.");
$priv_list['page-firewall-alias-import']['match'] = array();
$priv_list['page-firewall-alias-import']['match'][] = "firewall_aliases_import.php*";

$priv_list['page-firewall-nat-npt'] = array();
$priv_list['page-firewall-nat-npt']['name'] = gettext("Webcfg - Firewall: NAT: NPT page");
$priv_list['page-firewall-nat-npt']['descr'] = gettext("Allow access to the 'Firewall: NAT: NPT' page.");
$priv_list['page-firewall-nat-npt']['match'] = array();
$priv_list['page-firewall-nat-npt']['match'][] = "firewall_nat_npt.php*";

$priv_list['page-firewall-nat-npt-edit'] = array();
$priv_list['page-firewall-nat-npt-edit']['name'] = gettext("Webcfg - Firewall: NAT: NPt: Edit page");
$priv_list['page-firewall-nat-npt-edit']['descr'] = gettext("Allow access to the 'Firewall: NAT: NPt: Edit' page.");
$priv_list['page-firewall-nat-npt-edit']['match'] = array();
$priv_list['page-firewall-nat-npt-edit']['match'][] = "firewall_nat_npt_edit.php*";

$priv_list['page-firewall-nat-portforward'] = array();
$priv_list['page-firewall-nat-portforward']['name'] = gettext("WebCfg - Firewall: NAT: Port Forward page");
$priv_list['page-firewall-nat-portforward']['descr'] = gettext("Allow access to the 'Firewall: NAT: Port Forward' page.");
$priv_list['page-firewall-nat-portforward']['match'] = array();
$priv_list['page-firewall-nat-portforward']['match'][] = "firewall_nat.php*";

$priv_list['page-firewall-nat-1-1'] = array();
$priv_list['page-firewall-nat-1-1']['name'] = gettext("WebCfg - Firewall: NAT: 1:1 page");
$priv_list['page-firewall-nat-1-1']['descr'] = gettext("Allow access to the 'Firewall: NAT: 1:1' page.");
$priv_list['page-firewall-nat-1-1']['match'] = array();
$priv_list['page-firewall-nat-1-1']['match'][] = "firewall_nat_1to1.php*";

$priv_list['page-firewall-nat-1-1-edit'] = array();
$priv_list['page-firewall-nat-1-1-edit']['name'] = gettext("WebCfg - Firewall: NAT: 1:1: Edit page");
$priv_list['page-firewall-nat-1-1-edit']['descr'] = gettext("Allow access to the 'Firewall: NAT: 1:1: Edit' page.");
$priv_list['page-firewall-nat-1-1-edit']['match'] = array();
$priv_list['page-firewall-nat-1-1-edit']['match'][] = "firewall_nat_1to1_edit.php*";

$priv_list['page-firewall-nat-portforward-edit'] = array();
$priv_list['page-firewall-nat-portforward-edit']['name'] = gettext("WebCfg - Firewall: NAT: Port Forward: Edit page");
$priv_list['page-firewall-nat-portforward-edit']['descr'] = gettext("Allow access to the 'Firewall: NAT: Port Forward: Edit' page.");
$priv_list['page-firewall-nat-portforward-edit']['match'] = array();
$priv_list['page-firewall-nat-portforward-edit']['match'][] = "firewall_nat_edit.php*";

$priv_list['page-firewall-nat-outbound'] = array();
$priv_list['page-firewall-nat-outbound']['name'] = gettext("WebCfg - Firewall: NAT: Outbound page");
$priv_list['page-firewall-nat-outbound']['descr'] = gettext("Allow access to the 'Firewall: NAT: Outbound' page.");
$priv_list['page-firewall-nat-outbound']['match'] = array();
$priv_list['page-firewall-nat-outbound']['match'][] = "firewall_nat_out.php*";

$priv_list['page-firewall-nat-outbound-edit'] = array();
$priv_list['page-firewall-nat-outbound-edit']['name'] = gettext("WebCfg - Firewall: NAT: Outbound: Edit page");
$priv_list['page-firewall-nat-outbound-edit']['descr'] = gettext("Allow access to the 'Firewall: NAT: Outbound: Edit' page.");
$priv_list['page-firewall-nat-outbound-edit']['match'] = array();
$priv_list['page-firewall-nat-outbound-edit']['match'][] = "firewall_nat_out_edit.php*";

$priv_list['page-firewall-rules'] = array();
$priv_list['page-firewall-rules']['name'] = gettext("WebCfg - Firewall: Rules page");
$priv_list['page-firewall-rules']['descr'] = gettext("Allow access to the 'Firewall: Rules' page.");
$priv_list['page-firewall-rules']['match'] = array();
$priv_list['page-firewall-rules']['match'][] = "firewall_rules.php*";

$priv_list['page-firewall-rules-edit'] = array();
$priv_list['page-firewall-rules-edit']['name'] = gettext("WebCfg - Firewall: Rules: Edit page");
$priv_list['page-firewall-rules-edit']['descr'] = gettext("Allow access to the 'Firewall: Rules: Edit' page.");
$priv_list['page-firewall-rules-edit']['match'] = array();
$priv_list['page-firewall-rules-edit']['match'][] = "firewall_rules_edit.php*";

$priv_list['page-firewall-schedules'] = array();
$priv_list['page-firewall-schedules']['name'] = gettext("WebCfg - Firewall: Schedules page");
$priv_list['page-firewall-schedules']['descr'] = gettext("Allow access to the 'Firewall: Schedules' page.");
$priv_list['page-firewall-schedules']['match'] = array();
$priv_list['page-firewall-schedules']['match'][] = "firewall_schedule.php*";

$priv_list['page-firewall-schedules-edit'] = array();
$priv_list['page-firewall-schedules-edit']['name'] = gettext("WebCfg - Firewall: Schedules: Edit page");
$priv_list['page-firewall-schedules-edit']['descr'] = gettext("Allow access to the 'Firewall: Schedules: Edit' page.");
$priv_list['page-firewall-schedules-edit']['match'] = array();
$priv_list['page-firewall-schedules-edit']['match'][] = "firewall_schedule_edit.php*";

$priv_list['page-firewall-trafficshaper'] = array();
$priv_list['page-firewall-trafficshaper']['name'] = gettext("WebCfg - Firewall: Traffic Shaper page");
$priv_list['page-firewall-trafficshaper']['descr'] = gettext("Allow access to the 'Firewall: Traffic Shaper' page.");
$priv_list['page-firewall-trafficshaper']['match'] = array();
$priv_list['page-firewall-trafficshaper']['match'][] = "firewall_shaper.php*";

$priv_list['page-firewall-trafficshaper-layer7'] = array();
$priv_list['page-firewall-trafficshaper-layer7']['name'] = gettext("WebCfg - Firewall: Traffic Shaper: Layer7 page");
$priv_list['page-firewall-trafficshaper-layer7']['descr'] = gettext("Allow access to the 'Firewall: Traffic Shaper: Layer7' page.");
$priv_list['page-firewall-trafficshaper-layer7']['match'] = array();
$priv_list['page-firewall-trafficshaper-layer7']['match'][] = "firewall_shaper_layer7.php*";

$priv_list['page-firewall-trafficshaper-queues'] = array();
$priv_list['page-firewall-trafficshaper-queues']['name'] = gettext("WebCfg - Firewall: Traffic Shaper: Queues page");
$priv_list['page-firewall-trafficshaper-queues']['descr'] = gettext("Allow access to the 'Firewall: Traffic Shaper: Queues' page.");
$priv_list['page-firewall-trafficshaper-queues']['match'] = array();
$priv_list['page-firewall-trafficshaper-queues']['match'][] = "firewall_shaper_queues.php*";

$priv_list['page-firewall-trafficshaper-limiter'] = array();
$priv_list['page-firewall-trafficshaper-limiter']['name'] = gettext("WebCfg - Firewall: Traffic Shaper: Limiter page");
$priv_list['page-firewall-trafficshaper-limiter']['descr'] = gettext("Allow access to the 'Firewall: Traffic Shaper: Limiter' page.");
$priv_list['page-firewall-trafficshaper-limiter']['match'] = array();
$priv_list['page-firewall-trafficshaper-limiter']['match'][] = "firewall_shaper_vinterface.php*";

$priv_list['page-firewall-trafficshaper-wizard'] = array();
$priv_list['page-firewall-trafficshaper-wizard']['name'] = gettext("WebCfg - Firewall: Traffic Shaper: Wizard page");
$priv_list['page-firewall-trafficshaper-wizard']['descr'] = gettext("Allow access to the 'Firewall: Traffic Shaper: Wizard' page.");
$priv_list['page-firewall-trafficshaper-wizard']['match'] = array();
$priv_list['page-firewall-trafficshaper-wizard']['match'][] = "firewall_shaper_wizards.php*";

$priv_list['page-firewall-virtualipaddresses'] = array();
$priv_list['page-firewall-virtualipaddresses']['name'] = gettext("WebCfg - Firewall: Virtual IP Addresses page");
$priv_list['page-firewall-virtualipaddresses']['descr'] = gettext("Allow access to the 'Firewall: Virtual IP Addresses' page.");
$priv_list['page-firewall-virtualipaddresses']['match'] = array();
$priv_list['page-firewall-virtualipaddresses']['match'][] = "firewall_virtual_ip.php*";

$priv_list['page-firewall-virtualipaddress-edit'] = array();
$priv_list['page-firewall-virtualipaddress-edit']['name'] = gettext("WebCfg - Firewall: Virtual IP Address: Edit page");
$priv_list['page-firewall-virtualipaddress-edit']['descr'] = gettext("Allow access to the 'Firewall: Virtual IP Address: Edit' page.");
$priv_list['page-firewall-virtualipaddress-edit']['match'] = array();
$priv_list['page-firewall-virtualipaddress-edit']['match'][] = "firewall_virtual_ip_edit.php*";

$priv_list['page-getserviceproviders'] = array();
$priv_list['page-getserviceproviders']['name'] = gettext("WebCfg - AJAX: Get Service Providers");
$priv_list['page-getserviceproviders']['descr'] = gettext("Allow access to the 'AJAX: Service Providers' page.");
$priv_list['page-getserviceproviders']['match'] = array();
$priv_list['page-getserviceproviders']['match'][] = "getserviceproviders.php*";

$priv_list['page-getstats'] = array();
$priv_list['page-getstats']['name'] = gettext("WebCfg - AJAX: Get Stats");
$priv_list['page-getstats']['descr'] = gettext("Allow access to the 'AJAX: Get Stats' page.");
$priv_list['page-getstats']['match'] = array();
$priv_list['page-getstats']['match'][] = "getstats.php*";

$priv_list['page-diagnostics-interfacetraffic'] = array();
$priv_list['page-diagnostics-interfacetraffic']['name'] = gettext("WebCfg - Diagnostics: Interface Traffic page");
$priv_list['page-diagnostics-interfacetraffic']['descr'] = gettext("Allow access to the 'Diagnostics: Interface Traffic' page.");
$priv_list['page-diagnostics-interfacetraffic']['match'] = array();
$priv_list['page-diagnostics-interfacetraffic']['match'][] = "graph.php*";

$priv_list['page-diagnostics-cpuutilization'] = array();
$priv_list['page-diagnostics-cpuutilization']['name'] = gettext("WebCfg - Diagnostics: CPU Utilization page");
$priv_list['page-diagnostics-cpuutilization']['descr'] = gettext("Allow access to the 'Diagnostics: CPU Utilization' page.");
$priv_list['page-diagnostics-cpuutilization']['match'] = array();
$priv_list['page-diagnostics-cpuutilization']['match'][] = "graph_cpu.php*";

$priv_list['page-diagnostics-haltsystem'] = array();
$priv_list['page-diagnostics-haltsystem']['name'] = gettext("WebCfg - Diagnostics: Halt system page");
$priv_list['page-diagnostics-haltsystem']['descr'] = gettext("Allow access to the 'Diagnostics: Halt system' page.");
$priv_list['page-diagnostics-haltsystem']['match'] = array();
$priv_list['page-diagnostics-haltsystem']['match'][] = "halt.php*";

$priv_list['page-requiredforjavascript'] = array();
$priv_list['page-requiredforjavascript']['name'] = gettext("WebCfg - Required for javascript page");
$priv_list['page-requiredforjavascript']['descr'] = gettext("Allow access to the 'Required for javascript' page.");
$priv_list['page-requiredforjavascript']['match'] = array();
$priv_list['page-requiredforjavascript']['match'][] = "headjs.php*";

$priv_list['page-xmlrpcinterfacestats'] = array();
$priv_list['page-xmlrpcinterfacestats']['name'] = gettext("WebCfg - XMLRPC Interface Stats page");
$priv_list['page-xmlrpcinterfacestats']['descr'] = gettext("Allow access to the 'XMLRPC Interface Stats' page.");
$priv_list['page-xmlrpcinterfacestats']['match'] = array();
$priv_list['page-xmlrpcinterfacestats']['match'][] = "ifstats.php*";

$priv_list['page-system-login/logout'] = array();
$priv_list['page-system-login/logout']['name'] = gettext("WebCfg - System: Login / Logout page / Dashboard");
$priv_list['page-system-login/logout']['descr'] = gettext("Allow access to the 'System: Login / Logout' page and Dashboard.");
$priv_list['page-system-login/logout']['match'] = array();
$priv_list['page-system-login/logout']['match'][] = "index.php*";

$priv_list['page-interfaces'] = array();
$priv_list['page-interfaces']['name'] = gettext("WebCfg - Interfaces: WAN page");
$priv_list['page-interfaces']['descr'] = gettext("Allow access to the 'Interfaces' page.");
$priv_list['page-interfaces']['match'] = array();
$priv_list['page-interfaces']['match'][] = "interfaces.php*";

$priv_list['page-interfaces-assignnetworkports'] = array();
$priv_list['page-interfaces-assignnetworkports']['name'] = gettext("WebCfg - Interfaces: Assign network ports page");
$priv_list['page-interfaces-assignnetworkports']['descr'] = gettext("Allow access to the 'Interfaces: Assign network ports' page.");
$priv_list['page-interfaces-assignnetworkports']['match'] = array();
$priv_list['page-interfaces-assignnetworkports']['match'][] = "interfaces_assign.php*";

$priv_list['page-interfaces-bridge'] = array();
$priv_list['page-interfaces-bridge']['name'] = gettext("WebCfg - Interfaces: Bridge page");
$priv_list['page-interfaces-bridge']['descr'] = gettext("Allow access to the 'Interfaces: Bridge' page.");
$priv_list['page-interfaces-bridge']['match'] = array();
$priv_list['page-interfaces-bridge']['match'][] = "interfaces_bridge.php*";

$priv_list['page-interfaces-bridge-edit'] = array();
$priv_list['page-interfaces-bridge-edit']['name'] = gettext("WebCfg - Interfaces: Bridge edit page");
$priv_list['page-interfaces-bridge-edit']['descr'] = gettext("Allow access to the 'Interfaces: Bridge : Edit' page.");
$priv_list['page-interfaces-bridge-edit']['match'] = array();
$priv_list['page-interfaces-bridge-edit']['match'][] = "interfaces_bridge_edit.php*";

$priv_list['page-interfaces-gif'] = array();
$priv_list['page-interfaces-gif']['name'] = gettext("WebCfg - Interfaces: GIF page");
$priv_list['page-interfaces-gif']['descr'] = gettext("Allow access to the 'Interfaces: GIF' page.");
$priv_list['page-interfaces-gif']['match'] = array();
$priv_list['page-interfaces-gif']['match'][] = "interfaces_gif.php*";

$priv_list['page-interfaces-gif-edit'] = array();
$priv_list['page-interfaces-gif-edit']['name'] = gettext("WebCfg - Interfaces: GIF: Edit page");
$priv_list['page-interfaces-gif-edit']['descr'] = gettext("Allow access to the 'Interfaces: GIF: Edit' page.");
$priv_list['page-interfaces-gif-edit']['match'] = array();
$priv_list['page-interfaces-gif-edit']['match'][] = "interfaces_gif_edit.php*";

$priv_list['page-interfaces-gre'] = array();
$priv_list['page-interfaces-gre']['name'] = gettext("WebCfg - Interfaces: GRE page");
$priv_list['page-interfaces-gre']['descr'] = gettext("Allow access to the 'Interfaces: GRE' page.");
$priv_list['page-interfaces-gre']['match'] = array();
$priv_list['page-interfaces-gre']['match'][] = "interfaces_gre.php*";

$priv_list['page-interfaces-gre-edit'] = array();
$priv_list['page-interfaces-gre-edit']['name'] = gettext("WebCfg - Interfaces: GRE: Edit page");
$priv_list['page-interfaces-gre-edit']['descr'] = gettext("Allow access to the 'Interfaces: GRE: Edit' page.");
$priv_list['page-interfaces-gre-edit']['match'] = array();
$priv_list['page-interfaces-gre-edit']['match'][] = "interfaces_gre_edit.php*";

$priv_list['page-interfaces-groups'] = array();
$priv_list['page-interfaces-groups']['name'] = gettext("WebCfg - Interfaces: Groups page");
$priv_list['page-interfaces-groups']['descr'] = gettext("Create interface groups");
$priv_list['page-interfaces-groups']['match'] = array();
$priv_list['page-interfaces-groups']['match'][] = "interfaces_groups.php*";

$priv_list['page-interfaces-groups-edit'] = array();
$priv_list['page-interfaces-groups-edit']['name'] = gettext("Interfaces: Groups: Edit page");
$priv_list['page-interfaces-groups-edit']['descr'] = gettext("Allow access to the 'Interfaces: Groups: Edit' page.");
$priv_list['page-interfaces-groups-edit']['match'] = array();
$priv_list['page-interfaces-groups-edit']['match'][] = "interfaces_groups_edit.php*";

$priv_list['page-interfaces-lagg'] = array();
$priv_list['page-interfaces-lagg']['name'] = gettext("WebCfg - Interfaces: LAGG: page");
$priv_list['page-interfaces-lagg']['descr'] = gettext("Edit Interface LAGG");
$priv_list['page-interfaces-lagg']['match'] = array();
$priv_list['page-interfaces-lagg']['match'][] = "interfaces_lagg.php*";

$priv_list['page-interfaces-lagg-edit'] = array();
$priv_list['page-interfaces-lagg-edit']['name'] = gettext("Interfaces: LAGG: Edit page");
$priv_list['page-interfaces-lagg-edit']['descr'] = gettext("Allow access to the 'Interfaces: LAGG: Edit' page.");
$priv_list['page-interfaces-lagg-edit']['match'] = array();
$priv_list['page-interfaces-lagg-edit']['match'][] = "interfaces_lagg_edit.php*";

$priv_list['page-interfaces-ppps'] = array();
$priv_list['page-interfaces-ppps']['name'] = gettext("WebCfg - Interfaces: ppps page");
$priv_list['page-interfaces-ppps']['descr'] = gettext("Allow access to the 'Interfaces: ppps' page.");
$priv_list['page-interfaces-ppps']['match'] = array();
$priv_list['page-interfaces-ppps']['match'][] = "interfaces_ppps.php*";

$priv_list['page-interfaces-ppps-edit'] = array();
$priv_list['page-interfaces-ppps-edit']['name'] = gettext("WebCfg - Interfaces: PPPs: Edit page");
$priv_list['page-interfaces-ppps-edit']['descr'] = gettext("Allow access to the 'Interfaces: PPPs: Edit' page.");
$priv_list['page-interfaces-ppps-edit']['match'] = array();
$priv_list['page-interfaces-ppps-edit']['match'][] = "interfaces_ppps_edit.php*";

$priv_list['page-interfaces-qinq'] = array();
$priv_list['page-interfaces-qinq']['name'] = gettext("WebCfg - Interfaces: QinQ page");
$priv_list['page-interfaces-qinq']['descr'] = gettext("Allow access to the 'Interfaces: QinQ' page.");
$priv_list['page-interfaces-qinq']['match'] = array();
$priv_list['page-interfaces-qinq']['match'][] = "interfaces_qinq.php*";

$priv_list['page-interfaces-qinq-edit'] = array();
$priv_list['page-interfaces-qinq-edit']['name'] = gettext("Interfaces: QinQ: Edit page");
$priv_list['page-interfaces-qinq-edit']['descr'] = gettext("Allow access to 'Interfaces: QinQ: Edit' page");
$priv_list['page-interfaces-qinq-edit']['match'] = array();
$priv_list['page-interfaces-qinq-edit']['match'][] = "interfaces_qinq_edit.php*";

$priv_list['page-interfaces-vlan'] = array();
$priv_list['page-interfaces-vlan']['name'] = gettext("WebCfg - Interfaces: VLAN page");
$priv_list['page-interfaces-vlan']['descr'] = gettext("Allow access to the 'Interfaces: VLAN' page.");
$priv_list['page-interfaces-vlan']['match'] = array();
$priv_list['page-interfaces-vlan']['match'][] = "interfaces_vlan.php*";

$priv_list['page-interfaces-vlan-edit'] = array();
$priv_list['page-interfaces-vlan-edit']['name'] = gettext("WebCfg - Interfaces: VLAN: Edit page");
$priv_list['page-interfaces-vlan-edit']['descr'] = gettext("Allow access to the 'Interfaces: VLAN: Edit' page.");
$priv_list['page-interfaces-vlan-edit']['match'] = array();
$priv_list['page-interfaces-vlan-edit']['match'][] = "interfaces_vlan_edit.php*";

$priv_list['page-interfaces-wireless'] = array();
$priv_list['page-interfaces-wireless']['name'] = gettext("WebCfg - Interfaces: Wireless page");
$priv_list['page-interfaces-wireless']['descr'] = gettext("Allow access to the 'Interfaces: Wireless' page.");
$priv_list['page-interfaces-wireless']['match'] = array();
$priv_list['page-interfaces-wireless']['match'][] = "interfaces_wireless.php*";

$priv_list['page-interfaces-wireless-edit'] = array();
$priv_list['page-interfaces-wireless-edit']['name'] = gettext("WebCfg - Interfaces: Wireless edit page");
$priv_list['page-interfaces-wireless-edit']['descr'] = gettext("Allow access to the 'Interfaces: Wireless : Edit' page.");
$priv_list['page-interfaces-wireless-edit']['match'] = array();
$priv_list['page-interfaces-wireless-edit']['match'][] = "interfaces_wireless_edit.php*";

$priv_list['page-system-license'] = array();
$priv_list['page-system-license']['name'] = gettext("WebCfg - System: License page");
$priv_list['page-system-license']['descr'] = gettext("Allow access to the 'System: License' page.");
$priv_list['page-system-license']['match'] = array();
$priv_list['page-system-license']['match'][] = "license.php*";

$priv_list['page-services-loadbalancer-monitor'] = array();
$priv_list['page-services-loadbalancer-monitor']['name'] = gettext("WebCfg - Services: Load Balancer: Monitors page");
$priv_list['page-services-loadbalancer-monitor']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Monitors' page.");
$priv_list['page-services-loadbalancer-monitor']['match'] = array();
$priv_list['page-services-loadbalancer-monitor']['match'][] = "load_balancer_monitor.php*";

$priv_list['page-services-loadbalancer-monitor-edit'] = array();
$priv_list['page-services-loadbalancer-monitor-edit']['name'] = gettext("WebCfg - Services: Load Balancer: Monitor: Edit page");
$priv_list['page-services-loadbalancer-monitor-edit']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Monitor: Edit' page.");
$priv_list['page-services-loadbalancer-monitor-edit']['match'] = array();
$priv_list['page-services-loadbalancer-monitor-edit']['match'][] = "load_balancer_monitor_edit.php*";

$priv_list['page-loadbalancer-pool'] = array();
$priv_list['page-loadbalancer-pool']['name'] = gettext("WebCfg - Load Balancer: Pool page");
$priv_list['page-loadbalancer-pool']['descr'] = gettext("Allow access to the 'Load Balancer: Pool' page.");
$priv_list['page-loadbalancer-pool']['match'] = array();
$priv_list['page-loadbalancer-pool']['match'][] = "load_balancer_pool.php*";

$priv_list['page-loadbalancer-pool-edit'] = array();
$priv_list['page-loadbalancer-pool-edit']['name'] = gettext("WebCfg - Load Balancer: Pool: Edit page");
$priv_list['page-loadbalancer-pool-edit']['descr'] = gettext("Allow access to the 'Load Balancer: Pool: Edit' page.");
$priv_list['page-loadbalancer-pool-edit']['match'] = array();
$priv_list['page-loadbalancer-pool-edit']['match'][] = "load_balancer_pool_edit.php*";

$priv_list['page-services-loadbalancer-relay-action'] = array();
$priv_list['page-services-loadbalancer-relay-action']['name'] = gettext("WebCfg - Services: Load Balancer: Relay Actions page");
$priv_list['page-services-loadbalancer-relay-action']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Relay Actions' page.");
$priv_list['page-services-loadbalancer-relay-action']['match'] = array();
$priv_list['page-services-loadbalancer-relay-action']['match'][] = "load_balancer_relay_action.php*";

$priv_list['page-services-loadbalancer-relay-action-edit'] = array();
$priv_list['page-services-loadbalancer-relay-action-edit']['name'] = gettext("WebCfg - Services: Load Balancer: Relay Action: Edit page");
$priv_list['page-services-loadbalancer-relay-action-edit']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Relay Action: Edit' page.");
$priv_list['page-services-loadbalancer-relay-action-edit']['match'] = array();
$priv_list['page-services-loadbalancer-relay-action-edit']['match'][] = "load_balancer_relay_action_edit.php*";

$priv_list['page-services-loadbalancer-relay-protocol'] = array();
$priv_list['page-services-loadbalancer-relay-protocol']['name'] = gettext("WebCfg - Services: Load Balancer: Relay Protocols page");
$priv_list['page-services-loadbalancer-relay-protocol']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Relay Protocols' page.");
$priv_list['page-services-loadbalancer-relay-protocol']['match'] = array();
$priv_list['page-services-loadbalancer-relay-protocol']['match'][] = "load_balancer_relay_protocol.php*";

$priv_list['page-services-loadbalancer-relay-protocol-edit'] = array();
$priv_list['page-services-loadbalancer-relay-protocol-edit']['name'] = gettext("WebCfg - Services: Load Balancer: Relay Protocol: Edit page");
$priv_list['page-services-loadbalancer-relay-protocol-edit']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Relay Protocol: Edit' page.");
$priv_list['page-services-loadbalancer-relay-protocol-edit']['match'] = array();
$priv_list['page-services-loadbalancer-relay-protocol-edit']['match'][] = "load_balancer_relay_protocol_edit.php*";

$priv_list['page-services-loadbalancer-setting'] = array();
$priv_list['page-services-loadbalancer-setting']['name'] = gettext("Webcfg - Services: Load Balancer: setting page");
$priv_list['page-services-loadbalancer-setting']['descr'] = gettext("Allow access to the 'Settings: Load Balancer: Settings' page.");
$priv_list['page-services-loadbalancer-setting']['match'] = array();
$priv_list['page-services-loadbalancer-setting']['match'][] = "load_balancer_setting.php*";

$priv_list['page-services-loadbalancer-virtualservers'] = array();
$priv_list['page-services-loadbalancer-virtualservers']['name'] = gettext("WebCfg - Services: Load Balancer: Virtual Servers page");
$priv_list['page-services-loadbalancer-virtualservers']['descr'] = gettext("Allow access to the 'Services: Load Balancer: Virtual Servers' page.");
$priv_list['page-services-loadbalancer-virtualservers']['match'] = array();
$priv_list['page-services-loadbalancer-virtualservers']['match'][] = "load_balancer_virtual_server.php*";

$priv_list['page-services-ntpd'] = array();
$priv_list['page-services-ntpd']['name'] = gettext("Webcfg - Services: NTP");
$priv_list['page-services-ntpd']['descr'] = gettext("Allow access to the 'Services: NTP' page.");
$priv_list['page-services-ntpd']['match'] = array();
$priv_list['page-services-ntpd']['match'][] = "services_ntpd.php*";

$priv_list['page-services-ntp-gps'] = array();
$priv_list['page-services-ntp-gps']['name'] = gettext("Webcfg - Status: NTP GPS page");
$priv_list['page-services-ntp-gps']['descr'] = gettext("Allow access to the 'Status: NTP Serial GPS' page.");
$priv_list['page-services-ntp-gps']['match'] = array();
$priv_list['page-services-ntp-gps']['match'][] = "status_ntpd_gps.php*";

$priv_list['page-services-ntp-pps'] = array();
$priv_list['page-services-ntp-pps']['name'] = gettext("Webcfg - Status: NTP PPS page");
$priv_list['page-services-ntp-pps']['descr'] = gettext("Allow access to the 'Status: NTP PPS' page.");
$priv_list['page-services-ntp-pps']['match'] = array();
$priv_list['page-services-ntp-pps']['match'][] = "status_ntpd_pps.php*";

$priv_list['page-loadbalancer-virtualserver-edit'] = array();
$priv_list['page-loadbalancer-virtualserver-edit']['name'] = gettext("WebCfg - Load Balancer: Virtual Server: Edit page");
$priv_list['page-loadbalancer-virtualserver-edit']['descr'] = gettext("Allow access to the 'Load Balancer: Virtual Server: Edit' page.");
$priv_list['page-loadbalancer-virtualserver-edit']['match'] = array();
$priv_list['page-loadbalancer-virtualserver-edit']['match'][] = "load_balancer_virtual_server_edit.php*";

$priv_list['page-package-settings'] = array();
$priv_list['page-package-settings']['name'] = gettext("WebCfg - Package: Settings page");
$priv_list['page-package-settings']['descr'] = gettext("Allow access to the 'Package: Settings' page.");
$priv_list['page-package-settings']['match'] = array();
$priv_list['page-package-settings']['match'][] = "pkg.php*";

$priv_list['page-package-edit'] = array();
$priv_list['page-package-edit']['name'] = gettext("WebCfg - Package: Edit page");
$priv_list['page-package-edit']['descr'] = gettext("Allow access to the 'Package: Edit' page.");
$priv_list['page-package-edit']['match'] = array();
$priv_list['page-package-edit']['match'][] = "pkg_edit.php*";

$priv_list['page-system-packagemanager'] = array();
$priv_list['page-system-packagemanager']['name'] = gettext("WebCfg - System: Package Manager page");
$priv_list['page-system-packagemanager']['descr'] = gettext("Allow access to the 'System: Package Manager' page.");
$priv_list['page-system-packagemanager']['match'] = array();
$priv_list['page-system-packagemanager']['match'][] = "pkg_mgr.php*";

$priv_list['page-system-packagemanager-installpackage'] = array();
$priv_list['page-system-packagemanager-installpackage']['name'] = gettext("WebCfg - System: Package Manager: Install Package page");
$priv_list['page-system-packagemanager-installpackage']['descr'] = gettext("Allow access to the 'System: Package Manager: Install Package' page.");
$priv_list['page-system-packagemanager-installpackage']['match'] = array();
$priv_list['page-system-packagemanager-installpackage']['match'][] = "pkg_mgr_install.php*";

$priv_list['page-system-packagemanager-installed'] = array();
$priv_list['page-system-packagemanager-installed']['name'] = gettext("WebCfg - System: Package Manager: Installed page");
$priv_list['page-system-packagemanager-installed']['descr'] = gettext("Allow access to the 'System: Package Manager: Installed' page.");
$priv_list['page-system-packagemanager-installed']['match'] = array();
$priv_list['page-system-packagemanager-installed']['match'][] = "pkg_mgr_installed.php*";

$priv_list['page-pkg-mgr-settings'] = array();
$priv_list['page-pkg-mgr-settings']['name'] = gettext("WebCfg - Packages: Settings page");
$priv_list['page-pkg-mgr-settings']['descr'] = gettext("Allow access to the 'Packages: Settings' page.");
$priv_list['page-pkg-mgr-settings']['match'] = array();
$priv_list['page-pkg-mgr-settings']['match'][] = "pkg_mgr_settings.php*";

$priv_list['page-diagnostics-rebootsystem'] = array();
$priv_list['page-diagnostics-rebootsystem']['name'] = gettext("WebCfg - Diagnostics: Reboot System page");
$priv_list['page-diagnostics-rebootsystem']['descr'] = gettext("Allow access to the 'Diagnostics: Reboot System' page.");
$priv_list['page-diagnostics-rebootsystem']['match'] = array();
$priv_list['page-diagnostics-rebootsystem']['match'][] = "reboot.php*";

$priv_list['page-diagnostics-restart-httpd'] = array();
$priv_list['page-diagnostics-restart-httpd']['name'] = gettext("WebCfg - Diagnostics: Restart HTTPD : System page");
$priv_list['page-diagnostics-restart-httpd']['descr'] = gettext("Allow access to the 'Diagnostics: Restart HTTPD: System' page.");
$priv_list['page-diagnostics-restart-httpd']['match'] = array();
$priv_list['page-diagnostics-restart-httpd']['match'][] = "restart_httpd.php*";

$priv_list['page-services-captiveportal'] = array();
$priv_list['page-services-captiveportal']['name'] = gettext("WebCfg - Services: Captive portal page");
$priv_list['page-services-captiveportal']['descr'] = gettext("Allow access to the 'Services: Captive portal' page.");
$priv_list['page-services-captiveportal']['match'] = array();
$priv_list['page-services-captiveportal']['match'][] = "services_captiveportal.php*";

$priv_list['page-services-captiveportal-filemanager'] = array();
$priv_list['page-services-captiveportal-filemanager']['name'] = gettext("WebCfg - Services: Captive portal: File Manager page");
$priv_list['page-services-captiveportal-filemanager']['descr'] = gettext("Allow access to the 'Services: Captive portal: File Manager' page.");
$priv_list['page-services-captiveportal-filemanager']['match'] = array();
$priv_list['page-services-captiveportal-filemanager']['match'][] = "services_captiveportal_filemanager.php*";

$priv_list['page-services-captiveportal-allowedips'] = array();
$priv_list['page-services-captiveportal-allowedips']['name'] = gettext("WebCfg - Services: Captive portal: Allowed IPs page");
$priv_list['page-services-captiveportal-allowedips']['descr'] = gettext("Allow access to the 'Services: Captive portal: Allowed IPs' page.");
$priv_list['page-services-captiveportal-allowedips']['match'] = array();
$priv_list['page-services-captiveportal-allowedips']['match'][] = "services_captiveportal_ip.php*";

$priv_list['page-services-captiveportal-editallowedips'] = array();
$priv_list['page-services-captiveportal-editallowedips']['name'] = gettext("WebCfg - Services: Captive portal: Edit Allowed IPs page");
$priv_list['page-services-captiveportal-editallowedips']['descr'] = gettext("Allow access to the 'Services: Captive portal: Edit Allowed IPs' page.");
$priv_list['page-services-captiveportal-editallowedips']['match'] = array();
$priv_list['page-services-captiveportal-editallowedips']['match'][] = "services_captiveportal_ip_edit.php*";

$priv_list['page-services-captiveportal-macaddresses'] = array();
$priv_list['page-services-captiveportal-macaddresses']['name'] = gettext("WebCfg - Services: Captive portal: Mac Addresses page");
$priv_list['page-services-captiveportal-macaddresses']['descr'] = gettext("Allow access to the 'Services: Captive portal: Mac Addresses' page.");
$priv_list['page-services-captiveportal-macaddresses']['match'] = array();
$priv_list['page-services-captiveportal-macaddresses']['match'][] = "services_captiveportal_mac.php*";

$priv_list['page-services-captiveportal-editmacaddresses'] = array();
$priv_list['page-services-captiveportal-editmacaddresses']['name'] = gettext("WebCfg - Services: Captive portal: Edit MAC Addresses page");
$priv_list['page-services-captiveportal-editmacaddresses']['descr'] = gettext("Allow access to the 'Services: Captive portal: Edit MAC Addresses' page.");
$priv_list['page-services-captiveportal-editmacaddresses']['match'] = array();
$priv_list['page-services-captiveportal-editmacaddresses']['match'][] = "services_captiveportal_mac_edit.php*";

$priv_list['page-services-captiveportal-allowedhostnames'] = array();
$priv_list['page-services-captiveportal-allowedhostnames']['name'] = gettext("WebCfg - Services: Captive portal: Allowed Hostnames page");
$priv_list['page-services-captiveportal-allowedhostnames']['descr'] = gettext("Allow access to the 'Services: Captive portal: Allowed Hostnames' page.");
$priv_list['page-services-captiveportal-allowedhostnames']['match'] = array();
$priv_list['page-services-captiveportal-allowedhostnames']['match'][] = "services_captiveportal_hostname.php*";

$priv_list['page-services-captiveportal-editallowedhostnames'] = array();
$priv_list['page-services-captiveportal-editallowedhostnames']['name'] = gettext("WebCfg - Services: Captive portal: Edit Allowed Hostnames page");
$priv_list['page-services-captiveportal-editallowedhostnames']['descr'] = gettext("Allow access to the 'Services: Captive portal: Allowed Hostnames' page.");
$priv_list['page-services-captiveportal-editallowedhostnames']['match'] = array();
$priv_list['page-services-captiveportal-editallowedhostnames']['match'][] = "services_captiveportal_hostname_edit.php*";

$priv_list['page-services-captiveportal-editzones'] = array();
$priv_list['page-services-captiveportal-editzones']['name'] = gettext("Webcfg - Services: Captive portal: Edit Zones page");
$priv_list['page-services-captiveportal-editzones']['descr'] = gettext("Allow access to the 'Services: Captive portal: Edit Zones' page.");
$priv_list['page-services-captiveportal-editzones']['match'] = array();
$priv_list['page-services-captiveportal-editzones']['match'][] = "services_captiveportal_zones_edit.php*";

$priv_list['page-services-captiveportal-vouchers'] = array();
$priv_list['page-services-captiveportal-vouchers']['name'] = gettext("WebCfg - Services: Captive portal Vouchers page");
$priv_list['page-services-captiveportal-vouchers']['descr'] = gettext("Allow access to the 'Services: Captive portal Vouchers' page.");
$priv_list['page-services-captiveportal-vouchers']['match'] = array();
$priv_list['page-services-captiveportal-vouchers']['match'][] = "services_captiveportal_vouchers.php*";

$priv_list['page-services-captiveportal-voucher-edit'] = array();
$priv_list['page-services-captiveportal-voucher-edit']['name'] = "WebCfg - Services: Captive portal Voucher Rolls page";
$priv_list['page-services-captiveportal-voucher-edit']['descr'] = "Allow access to the 'Services: Captive portal Edit Voucher Rolls' page.";
$priv_list['page-services-captiveportal-voucher-edit']['match'] = array();
$priv_list['page-services-captiveportal-voucher-edit']['match'][] = "services_captiveportal_vouchers_edit.php*";

$priv_list['page-services-captiveportal-zones'] = array();
$priv_list['page-services-captiveportal-zones']['name'] = gettext("WebCfg - Services: Captiveprotal Zones page");
$priv_list['page-services-captiveportal-zones']['descr'] = gettext("Allow access to the 'Services: CaptivePortal Zones' page.");
$priv_list['page-services-captiveportal-zones']['match'] = array();
$priv_list['page-services-captiveportal-zones']['match'][] = "services_captiveportal_zones.php*";

$priv_list['page-services-dhcpserver'] = array();
$priv_list['page-services-dhcpserver']['name'] = gettext("WebCfg - Services: DHCP server page");
$priv_list['page-services-dhcpserver']['descr'] = gettext("Allow access to the 'Services: DHCP server' page.");
$priv_list['page-services-dhcpserver']['match'] = array();
$priv_list['page-services-dhcpserver']['match'][] = "services_dhcp.php*";

$priv_list['page-services-dhcpserver-editstaticmapping'] = array();
$priv_list['page-services-dhcpserver-editstaticmapping']['name'] = gettext("WebCfg - Services: DHCP Server : Edit static mapping page");
$priv_list['page-services-dhcpserver-editstaticmapping']['descr'] = gettext("Allow access to the 'Services: DHCP Server : Edit static mapping' page.");
$priv_list['page-services-dhcpserver-editstaticmapping']['match'] = array();
$priv_list['page-services-dhcpserver-editstaticmapping']['match'][] = "services_dhcp_edit.php*";

$priv_list['page-services-dhcprelay'] = array();
$priv_list['page-services-dhcprelay']['name'] = gettext("WebCfg - Services: DHCP Relay page");
$priv_list['page-services-dhcprelay']['descr'] = gettext("Allow access to the 'Services: DHCP Relay' page.");
$priv_list['page-services-dhcprelay']['match'] = array();
$priv_list['page-services-dhcprelay']['match'][] = "services_dhcp_relay.php*";

$priv_list['page-services-dhcpv6server'] = array();
$priv_list['page-services-dhcpv6server']['name'] = gettext("Webcfg - Services: DHCPv6 server page");
$priv_list['page-services-dhcpv6server']['descr'] = gettext("Allow access to the 'Services: DHCPv6 server' page.");
$priv_list['page-services-dhcpv6server']['match'] = array();
$priv_list['page-services-dhcpv6server']['match'][] = "services_dhcpv6.php*";

$priv_list['page-services-dhcpserverv6-editstaticmapping'] = array();
$priv_list['page-services-dhcpserverv6-editstaticmapping']['name'] = gettext("Webcfg - Services: DHCPv6 Server : Edit static mapping page");
$priv_list['page-services-dhcpserverv6-editstaticmapping']['descr'] = gettext("Allow access to the 'Services: DHCPv6 Server : Edit static mapping' page.");
$priv_list['page-services-dhcpserverv6-editstaticmapping']['match'] = array();
$priv_list['page-services-dhcpserverv6-editstaticmapping']['match'][] = "services_dhcpv6_edit.php*";

$priv_list['page-services-dhcpv6relay'] = array();
$priv_list['page-services-dhcpv6relay']['name'] = gettext("Webcfg - Services: DHCPv6 Relay page");
$priv_list['page-services-dhcpv6relay']['descr'] = gettext("Allow access to the 'Services: DHCPv6 Relay' page.");
$priv_list['page-services-dhcpv6relay']['match'] = array();
$priv_list['page-services-dhcpv6relay']['match'][] = "services_dhcpv6_relay.php*";

$priv_list['page-services-dnsforwarder'] = array();
$priv_list['page-services-dnsforwarder']['name'] = gettext("WebCfg - Services: DNS Forwarder page");
$priv_list['page-services-dnsforwarder']['descr'] = gettext("Allow access to the 'Services: DNS Forwarder' page.");
$priv_list['page-services-dnsforwarder']['match'] = array();
$priv_list['page-services-dnsforwarder']['match'][] = "services_dnsmasq.php*";

$priv_list['page-services-dnsforwarder-editdomainoverride'] = array();
$priv_list['page-services-dnsforwarder-editdomainoverride']['name'] = gettext("WebCfg - Services: DNS Forwarder: Edit Domain Override page");
$priv_list['page-services-dnsforwarder-editdomainoverride']['descr'] = gettext("Allow access to the 'Services: DNS Forwarder: Edit Domain Override' page.");
$priv_list['page-services-dnsforwarder-editdomainoverride']['match'] = array();
$priv_list['page-services-dnsforwarder-editdomainoverride']['match'][] = "services_dnsmasq_domainoverride_edit.php*";

$priv_list['page-services-dnsforwarder-edithost'] = array();
$priv_list['page-services-dnsforwarder-edithost']['name'] = gettext("WebCfg - Services: DNS Forwarder: Edit host page");
$priv_list['page-services-dnsforwarder-edithost']['descr'] = gettext("Allow access to the 'Services: DNS Forwarder: Edit host' page.");
$priv_list['page-services-dnsforwarder-edithost']['match'] = array();
$priv_list['page-services-dnsforwarder-edithost']['match'][] = "services_dnsmasq_edit.php*";

$priv_list['page-services-dnsresolver'] = array();
$priv_list['page-services-dnsresolver']['name'] = gettext("WebCfg - Services: DNS Resolver page");
$priv_list['page-services-dnsresolver']['descr'] = gettext("Allow access to the 'Services: DNS Resolver' page.");
$priv_list['page-services-dnsresolver']['match'] = array();
$priv_list['page-services-dnsresolver']['match'][] = "services_unbound.php*";

$priv_list['page-services-dnsresolver-advanced'] = array();
$priv_list['page-services-dnsresolver-advanced']['name'] = gettext("WebCfg - Services: DNS Resolver: Advanced page");
$priv_list['page-services-dnsresolver-advanced']['descr'] = gettext("Allow access to the 'Services: DNS Resolver: Advanced' page.");
$priv_list['page-services-dnsresolver-advanced']['match'] = array();
$priv_list['page-services-dnsresolver-advanced']['match'][] = "services_unbound_advanced.php*";

$priv_list['page-services-dnsresolver-acls'] = array();
$priv_list['page-services-dnsresolver-acls']['name'] = gettext("WebCfg - Services: DNS Resolver: Access Lists page");
$priv_list['page-services-dnsresolver-acls']['descr'] = gettext("Allow access to the 'Services: DNS Resolver: Access Lists' page.");
$priv_list['page-services-dnsresolver-acls']['match'] = array();
$priv_list['page-services-dnsresolver-acls']['match'][] = "services_unbound_acls.php*";

$priv_list['page-services-dnsresolver-editacls'] = array();
$priv_list['page-services-dnsresolver-editacls']['name'] = gettext("WebCfg - Services: DNS Resolver: Access Lists: Edit page");
$priv_list['page-services-dnsresolver-editacls']['descr'] = gettext("Allow access to the 'Services: DNS Resolver: Access Lists: Edit' page.");
$priv_list['page-services-dnsresolver-editacls']['match'] = array();
$priv_list['page-services-dnsresolver-editacls']['match'][] = "services_unbound_acls_edit.php*";

$priv_list['page-services-dnsresolver-editdomainoverride'] = array();
$priv_list['page-services-dnsresolver-editdomainoverride']['name'] = gettext("WebCfg - Services: DNS Resolver: Edit Domain Override page");
$priv_list['page-services-dnsresolver-editdomainoverride']['descr'] = gettext("Allow access to the 'Services: DNS Resolver: Edit Domain Override' page.");
$priv_list['page-services-dnsresolver-editdomainoverride']['match'] = array();
$priv_list['page-services-dnsresolver-editdomainoverride']['match'][] = "services_unbound_domainoverride_edit.php*";

$priv_list['page-services-dnsresolver-edithost'] = array();
$priv_list['page-services-dnsresolver-edithost']['name'] = gettext("WebCfg - Services: DNS Resolver: Edit host page");
$priv_list['page-services-dnsresolver-edithost']['descr'] = gettext("Allow access to the 'Services: DNS Resolver: Edit host' page.");
$priv_list['page-services-dnsresolver-edithost']['match'] = array();
$priv_list['page-services-dnsresolver-edithost']['match'][] = "services_unbound_host_edit.php*";

$priv_list['page-services-dynamicdnsclients'] = array();
$priv_list['page-services-dynamicdnsclients']['name'] = gettext("WebCfg - Services: Dynamic DNS clients page");
$priv_list['page-services-dynamicdnsclients']['descr'] = gettext("Allow access to the 'Services: Dynamic DNS clients' page.");
$priv_list['page-services-dynamicdnsclients']['match'] = array();
$priv_list['page-services-dynamicdnsclients']['match'][] = "services_dyndns.php*";

$priv_list['page-services-dynamicdnsclient'] = array();
$priv_list['page-services-dynamicdnsclient']['name'] = gettext("WebCfg - Services: Dynamic DNS client page");
$priv_list['page-services-dynamicdnsclient']['descr'] = gettext("Allow access to the 'Services: Dynamic DNS client' page.");
$priv_list['page-services-dynamicdnsclient']['match'] = array();
$priv_list['page-services-dynamicdnsclient']['match'][] = "services_dyndns_edit.php*";

$priv_list['page-services-igmpproxy'] = array();
$priv_list['page-services-igmpproxy']['name'] = gettext("WebCfg - Services: Igmpproxy page");
$priv_list['page-services-igmpproxy']['descr'] = gettext("Allow access to the 'Services: Igmpproxy' page.");
$priv_list['page-services-igmpproxy']['match'] = array();
$priv_list['page-services-igmpproxy']['match'][] = "services_igmpproxy.php*";

$priv_list['page-services-igmpproxy-edit'] = array();
$priv_list['page-services-igmpproxy-edit']['name'] = gettext("Firewall: Igmpproxy: Edit page");
$priv_list['page-services-igmpproxy-edit']['descr'] = gettext("Allow access to the 'Services: Igmpproxy: Edit' page.");
$priv_list['page-services-igmpproxy-edit']['match'] = array();
$priv_list['page-services-igmpproxy-edit']['match'][] = "services_igmpproxy_edit.php*";

$priv_list['page-services-rfc2136clients'] = array();
$priv_list['page-services-rfc2136clients']['name'] = gettext("WebCfg - Services: RFC 2136 clients page");
$priv_list['page-services-rfc2136clients']['descr'] = gettext("Allow access to the 'Services: RFC 2136 clients' page.");
$priv_list['page-services-rfc2136clients']['match'] = array();
$priv_list['page-services-rfc2136clients']['match'][] = "services_rfc2136.php*";

$priv_list['page-services-router-advertisements'] = array();
$priv_list['page-services-router-advertisements']['name'] = gettext("Webcfg - Services: Router advertisementspage");
$priv_list['page-services-router-advertisements']['descr'] = gettext("Allow access to the 'Services: Router Advertisements' page.");
$priv_list['page-services-router-advertisements']['match'] = array();
$priv_list['page-services-router-advertisements']['match'][] = "services_router_advertisements.php*";

$priv_list['page-services-snmp'] = array();
$priv_list['page-services-snmp']['name'] = gettext("WebCfg - Services: SNMP page");
$priv_list['page-services-snmp']['descr'] = gettext("Allow access to the 'Services: SNMP' page.");
$priv_list['page-services-snmp']['match'] = array();
$priv_list['page-services-snmp']['match'][] = "services_snmp.php*";

$priv_list['page-services-wakeonlan'] = array();
$priv_list['page-services-wakeonlan']['name'] = gettext("WebCfg - Services: Wake on LAN page");
$priv_list['page-services-wakeonlan']['descr'] = gettext("Allow access to the 'Services: Wake on LAN' page.");
$priv_list['page-services-wakeonlan']['match'] = array();
$priv_list['page-services-wakeonlan']['match'][] = "services_wol.php*";

$priv_list['page-services-wakeonlan-edit'] = array();
$priv_list['page-services-wakeonlan-edit']['name'] = gettext("WebCfg - Services: Wake on LAN: Edit page");
$priv_list['page-services-wakeonlan-edit']['descr'] = gettext("Allow access to the 'Services: Wake on LAN: Edit' page.");
$priv_list['page-services-wakeonlan-edit']['match'] = array();
$priv_list['page-services-wakeonlan-edit']['match'][] = "services_wol_edit.php*";

$priv_list['page-diagnostics-cpuutilization'] = array();
$priv_list['page-diagnostics-cpuutilization']['name'] = gettext("WebCfg - Diagnostics: CPU Utilization page");
$priv_list['page-diagnostics-cpuutilization']['descr'] = gettext("Allow access to the 'Diagnostics: CPU Utilization' page.");
$priv_list['page-diagnostics-cpuutilization']['match'] = array();
$priv_list['page-diagnostics-cpuutilization']['match'][] = "stats.php*";

$priv_list['page-hidden-detailedstatus'] = array();
$priv_list['page-hidden-detailedstatus']['name'] = gettext("WebCfg - Hidden: Detailed Status page");
$priv_list['page-hidden-detailedstatus']['descr'] = gettext("Allow access to the 'Hidden: Detailed Status' page.");
$priv_list['page-hidden-detailedstatus']['match'] = array();
$priv_list['page-hidden-detailedstatus']['match'][] = "status.php*";

$priv_list['page-status-captiveportal'] = array();
$priv_list['page-status-captiveportal']['name'] = gettext("WebCfg - Status: Captive portal page");
$priv_list['page-status-captiveportal']['descr'] = gettext("Allow access to the 'Status: Captive portal' page.");
$priv_list['page-status-captiveportal']['match'] = array();
$priv_list['page-status-captiveportal']['match'][] = "status_captiveportal.php*";

$priv_list['page-status-captiveportal-expire'] = array();
$priv_list['page-status-captiveportal-expire']['name'] = gettext("Webcfg - Status: Captive portal Expire Vouchers page");
$priv_list['page-status-captiveportal-expire']['descr'] = gettext("Allow access to the 'Status: Captive portal Expire Vouchers' page.");
$priv_list['page-status-captiveportal-expire']['match'] = array();
$priv_list['page-status-captiveportal-expire']['match'][] = "status_captiveportal_expire.php*";

$priv_list['page-status-captiveportal-test'] = array();
$priv_list['page-status-captiveportal-test']['name'] = gettext("WebCfg - Status: Captive portal test Vouchers page");
$priv_list['page-status-captiveportal-test']['descr'] = gettext("Allow access to the 'Status: Captive portal Test Vouchers' page.");
$priv_list['page-status-captiveportal-test']['match'] = array();
$priv_list['page-status-captiveportal-test']['match'][] = "status_captiveportal_test.php*";

$priv_list['page-status-captiveportal-voucher-rolls'] = array();
$priv_list['page-status-captiveportal-voucher-rolls']['name'] = gettext("WebCfg - Status: Captive portal Voucher Rolls page");
$priv_list['page-status-captiveportal-voucher-rolls']['descr'] = gettext("Allow access to the 'Status: Captive portal Voucher Rolls' page.");
$priv_list['page-status-captiveportal-voucher-rolls']['match'] = array();
$priv_list['page-status-captiveportal-voucher-rolls']['match'][] = "status_captiveportal_voucher_rolls.php*";

$priv_list['page-status-captiveportal-vouchers'] = array();
$priv_list['page-status-captiveportal-vouchers']['name'] = gettext("WebCfg - Status: Captive portal Vouchers page");
$priv_list['page-status-captiveportal-vouchers']['descr'] = gettext("Allow access to the 'Status: Captive portal Vouchers' page.");
$priv_list['page-status-captiveportal-vouchers']['match'] = array();
$priv_list['page-status-captiveportal-vouchers']['match'][] = "status_captiveportal_vouchers.php*";

$priv_list['page-status-dhcpleases'] = array();
$priv_list['page-status-dhcpleases']['name'] = gettext("WebCfg - Status: DHCP leases page");
$priv_list['page-status-dhcpleases']['descr'] = gettext("Allow access to the 'Status: DHCP leases' page.");
$priv_list['page-status-dhcpleases']['match'] = array();
$priv_list['page-status-dhcpleases']['match'][] = "status_dhcp_leases.php*";

$priv_list['page-status-dhcpv6leases'] = array();
$priv_list['page-status-dhcpv6leases']['name'] = gettext("Webcfg - Status: DHCPv6 leases page");
$priv_list['page-status-dhcpv6leases']['descr'] = gettext("Allow access to the 'Status: DHCPv6 leases' page.");
$priv_list['page-status-dhcpv6leases']['match'] = array();
$priv_list['page-status-dhcpv6leases']['match'][] = "status_dhcpv6_leases.php*";

$priv_list['page-status-filterreloadstatus'] = array();
$priv_list['page-status-filterreloadstatus']['name'] = gettext("WebCfg - Status: Filter Reload Status page");
$priv_list['page-status-filterreloadstatus']['descr'] = gettext("Allow access to the 'Status: Filter Reload Status' page.");
$priv_list['page-status-filterreloadstatus']['match'] = array();
$priv_list['page-status-filterreloadstatus']['match'][] = "status_filter_reload.php*";

$priv_list['page-status-gatewaygroups'] = array();
$priv_list['page-status-gatewaygroups']['name'] = gettext("WebCfg - Status: Gateway Groups page");
$priv_list['page-status-gatewaygroups']['descr'] = gettext("Allow access to the 'Status: Gateway Groups' page.");
$priv_list['page-status-gatewaygroups']['match'] = array();
$priv_list['page-status-gatewaygroups']['match'][] = "status_gateway_groups.php*";

$priv_list['page-status-gateways'] = array();
$priv_list['page-status-gateways']['name'] = gettext("WebCfg - Status: Gateways page");
$priv_list['page-status-gateways']['descr'] = gettext("Allow access to the 'Status: Gateways' page.");
$priv_list['page-status-gateways']['match'] = array();
$priv_list['page-status-gateways']['match'][] = "status_gateways.php*";

$priv_list['page-status-trafficgraph'] = array();
$priv_list['page-status-trafficgraph']['name'] = gettext("WebCfg - Status: Traffic Graph page");
$priv_list['page-status-trafficgraph']['descr'] = gettext("Allow access to the 'Status: Traffic Graph' page.");
$priv_list['page-status-trafficgraph']['match'] = array();
$priv_list['page-status-trafficgraph']['match'][] = "status_graph.php*";
$priv_list['page-status-trafficgraph']['match'][] = "bandwidth_by_ip.php*";
$priv_list['page-status-trafficgraph']['match'][] = "graph.php*";
$priv_list['page-status-trafficgraph']['match'][] = "ifstats.php*";

$priv_list['page-status-cpuload'] = array();
$priv_list['page-status-cpuload']['name'] = gettext("WebCfg - Status: CPU load page");
$priv_list['page-status-cpuload']['descr'] = gettext("Allow access to the 'Status: CPU load' page.");
$priv_list['page-status-cpuload']['match'] = array();
$priv_list['page-status-cpuload']['match'][] = "status_graph_cpu.php*";

$priv_list['page-status-interfaces'] = array();
$priv_list['page-status-interfaces']['name'] = gettext("WebCfg - Status: Interfaces page");
$priv_list['page-status-interfaces']['descr'] = gettext("Allow access to the 'Status: Interfaces' page.");
$priv_list['page-status-interfaces']['match'] = array();
$priv_list['page-status-interfaces']['match'][] = "status_interfaces.php*";

$priv_list['page-status-loadbalancer-pool'] = array();
$priv_list['page-status-loadbalancer-pool']['name'] = gettext("WebCfg - Status: Load Balancer: Pool page");
$priv_list['page-status-loadbalancer-pool']['descr'] = gettext("Allow access to the 'Status: Load Balancer: Pool' page.");
$priv_list['page-status-loadbalancer-pool']['match'] = array();
$priv_list['page-status-loadbalancer-pool']['match'][] = "status_lb_pool.php*";

$priv_list['page-status-loadbalancer-virtualserver'] = array();
$priv_list['page-status-loadbalancer-virtualserver']['name'] = gettext("WebCfg - Status: Load Balancer: Virtual Server page");
$priv_list['page-status-loadbalancer-virtualserver']['descr'] = gettext("Allow access to the 'Status: Load Balancer: Virtual Server' page.");
$priv_list['page-status-loadbalancer-virtualserver']['match'] = array();
$priv_list['page-status-loadbalancer-virtualserver']['match'][] = "status_lb_vs.php*";

$priv_list['page-status-openvpn'] = array();
$priv_list['page-status-openvpn']['name'] = gettext("WebCfg - Status: OpenVPN page");
$priv_list['page-status-openvpn']['descr'] = gettext("Allow access to the 'Status: OpenVPN' page.");
$priv_list['page-status-openvpn']['match'] = array();
$priv_list['page-status-openvpn']['match'][] = "status_openvpn.php*";

$priv_list['page-status-trafficshaper-queues'] = array();
$priv_list['page-status-trafficshaper-queues']['name'] = gettext("WebCfg - Status: Traffic shaper: Queues page");
$priv_list['page-status-trafficshaper-queues']['descr'] = gettext("Allow access to the 'Status: Traffic shaper: Queues' page.");
$priv_list['page-status-trafficshaper-queues']['match'] = array();
$priv_list['page-status-trafficshaper-queues']['match'][] = "status_queues.php*";

$priv_list['page-status-rrdgraphs'] = array();
$priv_list['page-status-rrdgraphs']['name'] = gettext("WebCfg - Status: RRD Graphs page");
$priv_list['page-status-rrdgraphs']['descr'] = gettext("Allow access to the 'Status: RRD Graphs' page.");
$priv_list['page-status-rrdgraphs']['match'] = array();
$priv_list['page-status-rrdgraphs']['match'][] = "status_rrd_graph.php*";
$priv_list['page-status-rrdgraphs']['match'][] = "status_rrd_graph_img.php*";

$priv_list['page-status-rrdgraph-settings'] = array();
$priv_list['page-status-rrdgraph-settings']['name'] = gettext("WebCfg - Status: RRD Graphs settings page");
$priv_list['page-status-rrdgraph-settings']['descr'] = gettext("Allow access to the 'Status: RRD Graphs: settings' page.");
$priv_list['page-status-rrdgraph-settings']['match'] = array();
$priv_list['page-status-rrdgraph-settings']['match'][] = "status_rrd_graph_settings.php*";

$priv_list['page-status-services'] = array();
$priv_list['page-status-services']['name'] = gettext("WebCfg - Status: Services page");
$priv_list['page-status-services']['descr'] = gettext("Allow access to the 'Status: Services' page.");
$priv_list['page-status-services']['match'] = array();
$priv_list['page-status-services']['match'][] = "status_services.php*";

$priv_list['page-status-upnpstatus'] = array();
$priv_list['page-status-upnpstatus']['name'] = gettext("WebCfg - Status: UPnP Status page");
$priv_list['page-status-upnpstatus']['descr'] = gettext("Allow access to the 'Status: UPnP Status' page.");
$priv_list['page-status-upnpstatus']['match'] = array();
$priv_list['page-status-upnpstatus']['match'][] = "status_upnp.php*";

$priv_list['page-diagnostics-wirelessstatus'] = array();
$priv_list['page-diagnostics-wirelessstatus']['name'] = gettext("WebCfg - Status: Wireless page");
$priv_list['page-diagnostics-wirelessstatus']['descr'] = gettext("Allow access to the 'Status: Wireless' page.");
$priv_list['page-diagnostics-wirelessstatus']['match'] = array();
$priv_list['page-diagnostics-wirelessstatus']['match'][] = "status_wireless.php*";

$priv_list['page-system-generalsetup'] = array();
$priv_list['page-system-generalsetup']['name'] = gettext("WebCfg - System: General Setup page");
$priv_list['page-system-generalsetup']['descr'] = gettext("Allow access to the 'System: General Setup' page.");
$priv_list['page-system-generalsetup']['match'] = array();
$priv_list['page-system-generalsetup']['match'][] = "system.php*";

$priv_list['page-system-advanced-admin'] = array();
$priv_list['page-system-advanced-admin']['name'] = gettext("WebCfg - System: Advanced: Admin Access Page");
$priv_list['page-system-advanced-admin']['descr'] = gettext("Allow access to the 'System: Advanced: Admin Access' page.");
$priv_list['page-system-advanced-admin']['match'] = array();
$priv_list['page-system-advanced-admin']['match'][] = "system_advanced_admin.php*";

$priv_list['page-system-advanced-firewall'] = array();
$priv_list['page-system-advanced-firewall']['name'] = gettext("WebCfg - System: Advanced: Firewall and NAT page");
$priv_list['page-system-advanced-firewall']['descr'] = gettext("Allow access to the 'System: Advanced: Firewall and NAT' page.");
$priv_list['page-system-advanced-firewall']['match'] = array();
$priv_list['page-system-advanced-firewall']['match'][] = "system_advanced_firewall.php*";

$priv_list['page-system-advanced-misc'] = array();
$priv_list['page-system-advanced-misc']['name'] = gettext("WebCfg - System: Advanced: Miscellaneous page");
$priv_list['page-system-advanced-misc']['descr'] = gettext("Allow access to the 'System: Advanced: Miscellaneous' page.");
$priv_list['page-system-advanced-misc']['match'] = array();
$priv_list['page-system-advanced-misc']['match'][] = "system_advanced_misc.php*";

$priv_list['page-system-advanced-network'] = array();
$priv_list['page-system-advanced-network']['name'] = gettext("WebCfg - System: Advanced: Network page");
$priv_list['page-system-advanced-network']['descr'] = gettext("Allow access to the 'System: Advanced: Networking' page.");
$priv_list['page-system-advanced-network']['match'] = array();
$priv_list['page-system-advanced-network']['match'][] = "system_advanced_network.php*";

$priv_list['page-system-advanced-notifications'] = array();
$priv_list['page-system-advanced-notifications']['name'] = gettext("WebCfg - System: Advanced: Notifications page");
$priv_list['page-system-advanced-notifications']['descr'] = gettext("Allow access to the 'System: Advanced: Notifications' page.");
$priv_list['page-system-advanced-notifications']['match'] = array();
$priv_list['page-system-advanced-notifications']['match'][] = "system_advanced_notifications.php*";

$priv_list['page-system-advanced-sysctl'] = array();
$priv_list['page-system-advanced-sysctl']['name'] = gettext("WebCfg - System: Advanced: Tunables page");
$priv_list['page-system-advanced-sysctl']['descr'] = gettext("Allow access to the 'System: Advanced: Tunables' page.");
$priv_list['page-system-advanced-sysctl']['match'] = array();
$priv_list['page-system-advanced-sysctl']['match'][] = "system_advanced_sysctl.php*";

$priv_list['page-system-authservers'] = array();
$priv_list['page-system-authservers']['name'] = gettext("WebCfg - System: Authentication Servers");
$priv_list['page-system-authservers']['descr'] = gettext("Allow access to the 'System: Authentication Servers' page.");
$priv_list['page-system-authservers']['match'] = array();
$priv_list['page-system-authservers']['match'][] = "system_authservers.php*";

$priv_list['page-system-camanager'] = array();
$priv_list['page-system-camanager']['name'] = gettext("WebCfg - System: CA Manager");
$priv_list['page-system-camanager']['descr'] = gettext("Allow access to the 'System: CA Manager' page.");
$priv_list['page-system-camanager']['match'] = array();
$priv_list['page-system-camanager']['match'][] = "system_camanager.php*";

$priv_list['page-system-certmanager'] = array();
$priv_list['page-system-certmanager']['name'] = gettext("WebCfg - System: Certificate Manager");
$priv_list['page-system-certmanager']['descr'] = gettext("Allow access to the 'System: Certificate Manager' page.");
$priv_list['page-system-certmanager']['match'] = array();
$priv_list['page-system-certmanager']['match'][] = "system_certmanager.php*";

$priv_list['page-system-crlmanager'] = array();
$priv_list['page-system-crlmanager']['name'] = gettext("WebCfg - System: CRL Manager");
$priv_list['page-system-crlmanager']['descr'] = gettext("Allow access to the 'System: CRL Manager' page.");
$priv_list['page-system-crlmanager']['match'] = array();
$priv_list['page-system-crlmanager']['match'][] = "system_crlmanager.php*";

$priv_list['page-system-firmware-manualupdate'] = array();
$priv_list['page-system-firmware-manualupdate']['name'] = gettext("WebCfg - System: Firmware: Manual Update page");
$priv_list['page-system-firmware-manualupdate']['descr'] = gettext("Allow access to the 'System: Firmware: Manual Update' page.");
$priv_list['page-system-firmware-manualupdate']['match'] = array();
$priv_list['page-system-firmware-manualupdate']['match'][] = "system_firmware.php*";

$priv_list['page-system-firmware-checkforupdate'] = array();
$priv_list['page-system-firmware-checkforupdate']['name'] = gettext("WebCfg - System: Firmware: Check For Update page");
$priv_list['page-system-firmware-checkforupdate']['descr'] = gettext("Allow access to the 'System: Firmware: Check For Update' page.");
$priv_list['page-system-firmware-checkforupdate']['match'] = array();
$priv_list['page-system-firmware-checkforupdate']['match'][] = "system_firmware_auto.php*";

$priv_list['page-system-firmware-autoupdate'] = array();
$priv_list['page-system-firmware-autoupdate']['name'] = gettext("WebCfg - System: Firmware: Auto Update page");
$priv_list['page-system-firmware-autoupdate']['descr'] = gettext("Allow access to the 'System: Firmware: Auto Update' page.");
$priv_list['page-system-firmware-autoupdate']['match'] = array();
$priv_list['page-system-firmware-autoupdate']['match'][] = "system_firmware_check.php*";

$priv_list['page-system-firmware-settings'] = array();
$priv_list['page-system-firmware-settings']['name'] = gettext("WebCfg - System: Firmware: Settings page");
$priv_list['page-system-firmware-settings']['descr'] = gettext("Allow access to the 'System: Firmware: Settings' page.");
$priv_list['page-system-firmware-settings']['match'] = array();
$priv_list['page-system-firmware-settings']['match'][] = "system_firmware_settings.php*";

$priv_list['page-system-gatewaygroups'] = array();
$priv_list['page-system-gatewaygroups']['name'] = gettext("WebCfg - System: Gateway Groups page");
$priv_list['page-system-gatewaygroups']['descr'] = gettext("Allow access to the 'System: Gateway Groups' page.");
$priv_list['page-system-gatewaygroups']['match'] = array();
$priv_list['page-system-gatewaygroups']['match'][] = "system_gateway_groups.php*";

$priv_list['page-system-gateways-editgatewaygroups'] = array();
$priv_list['page-system-gateways-editgatewaygroups']['name'] = gettext("WebCfg - System: Gateways: Edit Gateway Groups page");
$priv_list['page-system-gateways-editgatewaygroups']['descr'] = gettext("Allow access to the 'System: Gateways: Edit Gateway Groups' page.");
$priv_list['page-system-gateways-editgatewaygroups']['match'] = array();
$priv_list['page-system-gateways-editgatewaygroups']['match'][] = "system_gateway_groups_edit.php*";

$priv_list['page-system-gateways'] = array();
$priv_list['page-system-gateways']['name'] = gettext("WebCfg - System: Gateways page");
$priv_list['page-system-gateways']['descr'] = gettext("Allow access to the 'System: Gateways' page.");
$priv_list['page-system-gateways']['match'] = array();
$priv_list['page-system-gateways']['match'][] = "system_gateways.php*";

$priv_list['page-system-gateways-editgateway'] = array();
$priv_list['page-system-gateways-editgateway']['name'] = gettext("WebCfg - System: Gateways: Edit Gateway page");
$priv_list['page-system-gateways-editgateway']['descr'] = gettext("Allow access to the 'System: Gateways: Edit Gateway' page.");
$priv_list['page-system-gateways-editgateway']['match'] = array();
$priv_list['page-system-gateways-editgateway']['match'][] = "system_gateways_edit.php*";

$priv_list['page-system-groupmanager'] = array();
$priv_list['page-system-groupmanager']['name'] = gettext("WebCfg - System: Group manager page");
$priv_list['page-system-groupmanager']['descr'] = gettext("Allow access to the 'System: Group manager' page.");
$priv_list['page-system-groupmanager']['match'] = array();
$priv_list['page-system-groupmanager']['match'][] = "system_groupmanager.php*";

$priv_list['page-system-groupmanager-addprivs'] = array();
$priv_list['page-system-groupmanager-addprivs']['name'] = gettext("WebCfg - System: Group Manager: Add Privileges page");
$priv_list['page-system-groupmanager-addprivs']['descr'] = gettext("Allow access to the 'System: Group Manager: Add Privileges' page.");
$priv_list['page-system-groupmanager-addprivs']['match'] = array();
$priv_list['page-system-groupmanager-addprivs']['match'][] = "system_groupmanager_addprivs.php*";

$priv_list['page-system-hasync'] = array();
$priv_list['page-system-hasync']['name'] = gettext("Webcfg - System: High Availability Sync");
$priv_list['page-system-hasync']['descr'] = gettext("Allow access to the 'System: High Availability Sync' page.");
$priv_list['page-system-hasync']['match'] = array();
$priv_list['page-system-hasync']['match'][] = "system_hasync.php*";

$priv_list['page-system-staticroutes'] = array();
$priv_list['page-system-staticroutes']['name'] = gettext("WebCfg - System: Static Routes page");
$priv_list['page-system-staticroutes']['descr'] = gettext("Allow access to the 'System: Static Routes' page.");
$priv_list['page-system-staticroutes']['match'] = array();
$priv_list['page-system-staticroutes']['match'][] = "system_routes.php*";

$priv_list['page-system-staticroutes-editroute'] = array();
$priv_list['page-system-staticroutes-editroute']['name'] = gettext("WebCfg - System: Static Routes: Edit route page");
$priv_list['page-system-staticroutes-editroute']['descr'] = gettext("Allow access to the 'System: Static Routes: Edit route' page.");
$priv_list['page-system-staticroutes-editroute']['match'] = array();
$priv_list['page-system-staticroutes-editroute']['match'][] = "system_routes_edit.php*";

$priv_list['page-system-usermanager'] = array();
$priv_list['page-system-usermanager']['name'] = gettext("WebCfg - System: User Manager page");
$priv_list['page-system-usermanager']['descr'] = gettext("Allow access to the 'System: User Manager' page.");
$priv_list['page-system-usermanager']['match'] = array();
$priv_list['page-system-usermanager']['match'][] = "system_usermanager.php*";

$priv_list['page-system-usermanager-addprivs'] = array();
$priv_list['page-system-usermanager-addprivs']['name'] = gettext("WebCfg - System: User Manager: Add Privileges page");
$priv_list['page-system-usermanager-addprivs']['descr'] = gettext("Allow access to the 'System: User Manager: Add Privileges' page.");
$priv_list['page-system-usermanager-addprivs']['match'] = array();
$priv_list['page-system-usermanager-addprivs']['match'][] = "system_usermanager_addprivs.php*";

$priv_list['page-system-usermanager-passwordmg'] = array();
$priv_list['page-system-usermanager-passwordmg']['name'] = gettext("WebCfg - System: User Password Manager page");
$priv_list['page-system-usermanager-passwordmg']['descr'] = gettext("Allow access to the 'System: User Password Manager' page.");
$priv_list['page-system-usermanager-passwordmg']['match'] = array();
$priv_list['page-system-usermanager-passwordmg']['match'][] = "system_usermanager_passwordmg.php*";

$priv_list['page-system-usermanager-settings'] = array();
$priv_list['page-system-usermanager-settings']['name'] = gettext("WebCfg - System: User Manager: settings page");
$priv_list['page-system-usermanager-settings']['descr'] = gettext("Allow access to the 'System: User Manager: settings' page.");
$priv_list['page-system-usermanager-settings']['match'] = array();
$priv_list['page-system-usermanager-settings']['match'][] = "system_usermanager_settings.php*";

$priv_list['page-system-usermanager-settings-testldap'] = array();
$priv_list['page-system-usermanager-settings-testldap']['name'] = gettext("WebCfg - System: User Manager: Settings: Test LDAP page");
$priv_list['page-system-usermanager-settings-testldap']['descr'] = gettext("Allow access to the 'System: User Manager: Settings: Test LDAP' page.");
$priv_list['page-system-usermanager-settings-testldap']['match'] = array();
$priv_list['page-system-usermanager-settings-testldap']['match'][] = "system_usermanager_settings_test.php*";

$priv_list['page-upload_progress'] = array();
$priv_list['page-upload_progress']['name'] = gettext("WebCfg - System: Firmware: Manual Update page (progress bar)");
$priv_list['page-upload_progress']['descr'] = gettext("Allow access to the 'System: Firmware: Manual Update: Progress bar' page.");
$priv_list['page-upload_progress']['match'] = array();
$priv_list['page-upload_progress']['match'][] = "upload_progress*";

$priv_list['page-hidden-uploadconfiguration'] = array();
$priv_list['page-hidden-uploadconfiguration']['name'] = gettext("WebCfg - Hidden: Upload Configuration page");
$priv_list['page-hidden-uploadconfiguration']['descr'] = gettext("Allow access to the 'Hidden: Upload Configuration' page.");
$priv_list['page-hidden-uploadconfiguration']['match'] = array();
$priv_list['page-hidden-uploadconfiguration']['match'][] = "uploadconfig.php*";

$priv_list['page-vpn-ipsec'] = array();
$priv_list['page-vpn-ipsec']['name'] = gettext("WebCfg - VPN: IPsec page");
$priv_list['page-vpn-ipsec']['descr'] = gettext("Allow access to the 'VPN: IPsec' page.");
$priv_list['page-vpn-ipsec']['match'] = array();
$priv_list['page-vpn-ipsec']['match'][] = "vpn_ipsec.php*";

$priv_list['page-vpn-ipsec-listkeys'] = array();
$priv_list['page-vpn-ipsec-listkeys']['name'] = gettext("WebCfg - VPN: IPsec: Pre-Shared Keys List");
$priv_list['page-vpn-ipsec-listkeys']['descr'] = gettext("Allow access to the 'VPN: IPsec: Pre-Shared Keys List' page.");
$priv_list['page-vpn-ipsec-listkeys']['match'] = array();
$priv_list['page-vpn-ipsec-listkeys']['match'][] = "vpn_ipsec_keys.php*";

$priv_list['page-vpn-ipsec-editkeys'] = array();
$priv_list['page-vpn-ipsec-editkeys']['name'] = gettext("WebCfg - VPN: IPsec: Edit Pre-Shared Keys");
$priv_list['page-vpn-ipsec-editkeys']['descr'] = gettext("Allow access to the 'VPN: IPsec: Edit Pre-Shared Keys' page.");
$priv_list['page-vpn-ipsec-editkeys']['match'] = array();
$priv_list['page-vpn-ipsec-editkeys']['match'][] = "vpn_ipsec_keys_edit.php*";

$priv_list['page-vpn-ipsec-mobile'] = array();
$priv_list['page-vpn-ipsec-mobile']['name'] = gettext("WebCfg - VPN: IPsec: Mobile page");
$priv_list['page-vpn-ipsec-mobile']['descr'] = gettext("Allow access to the 'VPN: IPsec: Mobile' page.");
$priv_list['page-vpn-ipsec-mobile']['match'] = array();
$priv_list['page-vpn-ipsec-mobile']['match'][] = "vpn_ipsec_mobile.php*";

$priv_list['page-vpn-ipsec-editphase1'] = array();
$priv_list['page-vpn-ipsec-editphase1']['name'] = gettext("WebCfg - VPN: IPsec: Edit Phase 1 page");
$priv_list['page-vpn-ipsec-editphase1']['descr'] = gettext("Allow access to the 'VPN: IPsec: Edit Phase 1' page.");
$priv_list['page-vpn-ipsec-editphase1']['match'] = array();
$priv_list['page-vpn-ipsec-editphase1']['match'][] = "vpn_ipsec_phase1.php*";

$priv_list['page-vpn-ipsec-editphase2'] = array();
$priv_list['page-vpn-ipsec-editphase2']['name'] = gettext("WebCfg - VPN: IPsec: Edit Phase 2 page");
$priv_list['page-vpn-ipsec-editphase2']['descr'] = gettext("Allow access to the 'VPN: IPsec: Edit Phase 2' page.");
$priv_list['page-vpn-ipsec-editphase2']['match'] = array();
$priv_list['page-vpn-ipsec-editphase2']['match'][] = "vpn_ipsec_phase2.php*";

$priv_list['page-vpn-vpnl2tp'] = array();
$priv_list['page-vpn-vpnl2tp']['name'] = gettext("WebCfg - VPN: VPN L2TP page");
$priv_list['page-vpn-vpnl2tp']['descr'] = gettext("Allow access to the 'VPN: VPN L2TP' page.");
$priv_list['page-vpn-vpnl2tp']['match'] = array();
$priv_list['page-vpn-vpnl2tp']['match'][] = "vpn_l2tp.php*";

$priv_list['page-vpn-vpnl2tp-users'] = array();
$priv_list['page-vpn-vpnl2tp-users']['name'] = gettext("WebCfg - VPN: VPN L2TP : Users page");
$priv_list['page-vpn-vpnl2tp-users']['descr'] = gettext("Allow access to the 'VPN: VPN L2TP : Users' page.");
$priv_list['page-vpn-vpnl2tp-users']['match'] = array();
$priv_list['page-vpn-vpnl2tp-users']['match'][] = "vpn_l2tp_users.php*";

$priv_list['page-vpn-vpnl2tp-users-edit'] = array();
$priv_list['page-vpn-vpnl2tp-users-edit']['name'] = gettext("WebCfg - VPN: VPN L2TP : Users : Edit page");
$priv_list['page-vpn-vpnl2tp-users-edit']['descr'] = gettext("Allow access to the 'VPN: VPN L2TP : Users : Edit' page.");
$priv_list['page-vpn-vpnl2tp-users-edit']['match'] = array();
$priv_list['page-vpn-vpnl2tp-users-edit']['match'][] = "vpn_l2tp_users_edit.php*";

$priv_list['page-openvpn-client'] = array();
$priv_list['page-openvpn-client']['name'] = gettext("WebCfg - OpenVPN: Client page");
$priv_list['page-openvpn-client']['descr'] = gettext("Allow access to the 'OpenVPN: Client' page.");
$priv_list['page-openvpn-client']['match'] = array();
$priv_list['page-openvpn-client']['match'][] = "vpn_openvpn_client.php*";

$priv_list['page-openvpn-csc'] = array();
$priv_list['page-openvpn-csc']['name'] = gettext("WebCfg - OpenVPN: Client Specific Override page");
$priv_list['page-openvpn-csc']['descr'] = gettext("Allow access to the 'OpenVPN: Client Specific Override' page.");
$priv_list['page-openvpn-csc']['match'] = array();
$priv_list['page-openvpn-csc']['match'][] = "vpn_openvpn_csc.php*";

$priv_list['page-openvpn-server'] = array();
$priv_list['page-openvpn-server']['name'] = gettext("WebCfg - OpenVPN: Server page");
$priv_list['page-openvpn-server']['descr'] = gettext("Allow access to the 'OpenVPN: Server' page.");
$priv_list['page-openvpn-server']['match'] = array();
$priv_list['page-openvpn-server']['match'][] = "vpn_openvpn_server.php*";

$priv_list['page-services-pppoeserver'] = array();
$priv_list['page-services-pppoeserver']['name'] = gettext("WebCfg - Services: PPPoE Server page");
$priv_list['page-services-pppoeserver']['descr'] = gettext("Allow access to the 'Services: PPPoE Server' page.");
$priv_list['page-services-pppoeserver']['match'] = array();
$priv_list['page-services-pppoeserver']['match'][] = "vpn_pppoe.php*";

$priv_list['page-services-pppoeserver-edit'] = array();
$priv_list['page-services-pppoeserver-edit']['name'] = gettext("WebCfg - Services: PPPoE Server: Edit page");
$priv_list['page-services-pppoeserver-edit']['descr'] = gettext("Allow access to the 'Services: PPPoE Server: Edit' page.");
$priv_list['page-services-pppoeserver-edit']['match'] = array();
$priv_list['page-services-pppoeserver-edit']['match'][] = "vpn_pppoe_edit.php*";

$priv_list['page-vpn-vpnpptp'] = array();
$priv_list['page-vpn-vpnpptp']['name'] = gettext("WebCfg - VPN: VPN PPTP page");
$priv_list['page-vpn-vpnpptp']['descr'] = gettext("Allow access to the 'VPN: VPN PPTP' page.");
$priv_list['page-vpn-vpnpptp']['match'] = array();
$priv_list['page-vpn-vpnpptp']['match'][] = "vpn_pptp.php*";

$priv_list['page-vpn-vpnpptp-users'] = array();
$priv_list['page-vpn-vpnpptp-users']['name'] = gettext("WebCfg - VPN: VPN PPTP: Users page");
$priv_list['page-vpn-vpnpptp-users']['descr'] = gettext("Allow access to the 'VPN: VPN PPTP: Users' page.");
$priv_list['page-vpn-vpnpptp-users']['match'] = array();
$priv_list['page-vpn-vpnpptp-users']['match'][] = "vpn_pptp_users.php*";

$priv_list['page-vpn-vpnpptp-user-edit'] = array();
$priv_list['page-vpn-vpnpptp-user-edit']['name'] = gettext("WebCfg - VPN: VPN PPTP: User: Edit page");
$priv_list['page-vpn-vpnpptp-user-edit']['descr'] = gettext("Allow access to the 'VPN: VPN PPTP: User: Edit' page.");
$priv_list['page-vpn-vpnpptp-user-edit']['match'] = array();
$priv_list['page-vpn-vpnpptp-user-edit']['match'][] = "vpn_pptp_users_edit.php*";

$priv_list['page-pfsensewizardsubsystem'] = array();
$priv_list['page-pfsensewizardsubsystem']['name'] = gettext("WebCfg - pfSense wizard subsystem page");
$priv_list['page-pfsensewizardsubsystem']['descr'] = gettext("Allow access to the 'pfSense wizard subsystem' page.");
$priv_list['page-pfsensewizardsubsystem']['match'] = array();
$priv_list['page-pfsensewizardsubsystem']['match'][] = "wizard.php*";

$priv_list['page-xmlrpclibrary'] = array();
$priv_list['page-xmlrpclibrary']['name'] = gettext("WebCfg - XMLRPC Library page");
$priv_list['page-xmlrpclibrary']['descr'] = gettext("Allow access to the 'XMLRPC Library' page.");
$priv_list['page-xmlrpclibrary']['match'] = array();
$priv_list['page-xmlrpclibrary']['match'][] = "xmlrpc.php*";

$priv_list['page-firewall-easyrule'] = array();
$priv_list['page-firewall-easyrule']['name'] = gettext("WebCfg - Firewall: Easy Rule add/status page");
$priv_list['page-firewall-easyrule']['descr'] = gettext("Allow access to the 'Firewall: Easy Rule' add/status page.");
$priv_list['page-firewall-easyrule']['match'] = array();
$priv_list['page-firewall-easyrule']['match'][] = "easyrule.php*";

$priv_rmvd = array();

?>
