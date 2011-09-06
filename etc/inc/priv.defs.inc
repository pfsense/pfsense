<?php
/*
 * priv.defs.inc - Generated privilege definitions
 *
 */

$priv_list = array();

$priv_list['page-all'] = array();
$priv_list['page-all']['name'] = "WebCfg - All pages";
$priv_list['page-all']['descr'] = "Allow access to all pages";
$priv_list['page-all']['match'] = array();
$priv_list['page-all']['match'][] = "*";

$priv_list['page-status-carp'] = array();
$priv_list['page-status-carp']['name'] = "WebCfg - Status: CARP page";
$priv_list['page-status-carp']['descr'] = "Allow access to the 'Status: CARP' page.";
$priv_list['page-status-carp']['match'] = array();
$priv_list['page-status-carp']['match'][] = "carp_status.php*";

$priv_list['page-diagnostics-crash-reporter'] = array();
$priv_list['page-diagnostics-crash-reporter']['name'] = "WebCfg - Crash reporter";
$priv_list['page-diagnostics-crash-reporter']['descr'] = "Uploads crash reports to pfSense and or deletes crash reports.";
$priv_list['page-diagnostics-crash-reporter']['match'] = array();
$priv_list['page-diagnostics-crash-reporter']['match'][] = "crash_reporter.php*";

$priv_list['page-diagnostics-arptable'] = array();
$priv_list['page-diagnostics-arptable']['name'] = "WebCfg - Diagnostics: ARP Table page";
$priv_list['page-diagnostics-arptable']['descr'] = "Allow access to the 'Diagnostics: ARP Table' page.";
$priv_list['page-diagnostics-arptable']['match'] = array();
$priv_list['page-diagnostics-arptable']['match'][] = "diag_arp.php*";

$priv_list['page-diagnostics-authentication'] = array();
$priv_list['page-diagnostics-authentication']['name'] = "WebCfg - Diagnostics: Authentication page";
$priv_list['page-diagnostics-authentication']['descr'] = "Allow access to the 'Diagnostics: Authentication' page.";
$priv_list['page-diagnostics-authentication']['match'] = array();
$priv_list['page-diagnostics-authentication']['match'][] = "diag_authentication.php*";

$priv_list['page-diagnostics-backup/restore'] = array();
$priv_list['page-diagnostics-backup/restore']['name'] = "WebCfg - Diagnostics: Backup/restore page";
$priv_list['page-diagnostics-backup/restore']['descr'] = "Allow access to the 'Diagnostics: Backup/restore' page.";
$priv_list['page-diagnostics-backup/restore']['match'] = array();
$priv_list['page-diagnostics-backup/restore']['match'][] = "diag_backup.php*";

$priv_list['page-diagnostics-configurationhistory'] = array();
$priv_list['page-diagnostics-configurationhistory']['name'] = "WebCfg - Diagnostics: Configuration History page";
$priv_list['page-diagnostics-configurationhistory']['descr'] = "Allow access to the 'Diagnostics: Configuration History' page.";
$priv_list['page-diagnostics-configurationhistory']['match'] = array();
$priv_list['page-diagnostics-configurationhistory']['match'][] = "diag_confbak.php*";

$priv_list['page-diagnostics-factorydefaults'] = array();
$priv_list['page-diagnostics-factorydefaults']['name'] = "WebCfg - Diagnostics: Factory defaults page";
$priv_list['page-diagnostics-factorydefaults']['descr'] = "Allow access to the 'Diagnostics: Factory defaults' page.";
$priv_list['page-diagnostics-factorydefaults']['match'] = array();
$priv_list['page-diagnostics-factorydefaults']['match'][] = "diag_defaults.php*";

$priv_list['page-diagnostics-showstates'] = array();
$priv_list['page-diagnostics-showstates']['name'] = "WebCfg - Diagnostics: Show States page";
$priv_list['page-diagnostics-showstates']['descr'] = "Allow access to the 'Diagnostics: Show States' page.";
$priv_list['page-diagnostics-showstates']['match'] = array();
$priv_list['page-diagnostics-showstates']['match'][] = "diag_dump_states.php*";

$priv_list['page-status-ipsec'] = array();
$priv_list['page-status-ipsec']['name'] = "WebCfg - Status: IPsec page";
$priv_list['page-status-ipsec']['descr'] = "Allow access to the 'Status: IPsec' page.";
$priv_list['page-status-ipsec']['match'] = array();
$priv_list['page-status-ipsec']['match'][] = "diag_ipsec.php*";

$priv_list['page-status-ipsec-sad'] = array();
$priv_list['page-status-ipsec-sad']['name'] = "WebCfg - Status: IPsec: SAD page";
$priv_list['page-status-ipsec-sad']['descr'] = "Allow access to the 'Status: IPsec: SAD' page.";
$priv_list['page-status-ipsec-sad']['match'] = array();
$priv_list['page-status-ipsec-sad']['match'][] = "diag_ipsec_sad.php*";

$priv_list['page-status-ipsec-spd'] = array();
$priv_list['page-status-ipsec-spd']['name'] = "WebCfg - Status: IPsec: SPD page";
$priv_list['page-status-ipsec-spd']['descr'] = "Allow access to the 'Status: IPsec: SPD' page.";
$priv_list['page-status-ipsec-spd']['match'] = array();
$priv_list['page-status-ipsec-spd']['match'][] = "diag_ipsec_spd.php*";

$priv_list['page-ipsecxml'] = array();
$priv_list['page-ipsecxml']['name'] = "WebCfg - Diag IPsec XML page";
$priv_list['page-ipsecxml']['descr'] = "Allow access to the 'Diag IPsec XML' page.";
$priv_list['page-ipsecxml']['match'] = array();
$priv_list['page-ipsecxml']['match'][] = "diag_ipsec_xml.php";

$priv_list['page-diag-system-activity'] = array();
$priv_list['page-diag-system-activity']['name'] = "WebCfg - Diagnostics: System Activity";
$priv_list['page-diag-system-activity']['descr'] = "Allows access to the 'Diagnostics: System Activity' page";
$priv_list['page-diag-system-activity']['match'] = array();
$priv_list['page-diag-system-activity']['match'][] = "diag_system_activity*";

$priv_list['page-diagnostics-logs-system'] = array();
$priv_list['page-diagnostics-logs-system']['name'] = "WebCfg - Diagnostics: Logs: System page";
$priv_list['page-diagnostics-logs-system']['descr'] = "Allow access to the 'Diagnostics: Logs: System' page.";
$priv_list['page-diagnostics-logs-system']['match'] = array();
$priv_list['page-diagnostics-logs-system']['match'][] = "diag_logs.php*";

$priv_list['page-status-systemlogs-portalauth'] = array();
$priv_list['page-status-systemlogs-portalauth']['name'] = "WebCfg - Status: System logs: Portal Auth page";
$priv_list['page-status-systemlogs-portalauth']['descr'] = "Allow access to the 'Status: System logs: Portal Auth' page.";
$priv_list['page-status-systemlogs-portalauth']['match'] = array();
$priv_list['page-status-systemlogs-portalauth']['match'][] = "diag_logs_auth.php*";

$priv_list['page-diagnostics-logs-dhcp'] = array();
$priv_list['page-diagnostics-logs-dhcp']['name'] = "WebCfg - Diagnostics: Logs: DHCP page";
$priv_list['page-diagnostics-logs-dhcp']['descr'] = "Allow access to the 'Diagnostics: Logs: DHCP' page.";
$priv_list['page-diagnostics-logs-dhcp']['match'] = array();
$priv_list['page-diagnostics-logs-dhcp']['match'][] = "diag_logs_dhcp.php*";

$priv_list['page-diagnostics-logs-firewall'] = array();
$priv_list['page-diagnostics-logs-firewall']['name'] = "WebCfg - Diagnostics: Logs: Firewall page";
$priv_list['page-diagnostics-logs-firewall']['descr'] = "Allow access to the 'Diagnostics: Logs: Firewall' page.";
$priv_list['page-diagnostics-logs-firewall']['match'] = array();
$priv_list['page-diagnostics-logs-firewall']['match'][] = "diag_logs_filter.php*";

$priv_list['page-hidden-nolongerincluded'] = array();
$priv_list['page-hidden-nolongerincluded']['name'] = "WebCfg - Hidden: No longer included page";
$priv_list['page-hidden-nolongerincluded']['descr'] = "Allow access to the 'Hidden: No longer included' page.";
$priv_list['page-hidden-nolongerincluded']['match'] = array();
$priv_list['page-hidden-nolongerincluded']['match'][] = "diag_logs_filter_dynamic.php*";

$priv_list['page-status-systemlogs-ipsecvpn'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['name'] = "WebCfg - Status: System logs: IPsec VPN page";
$priv_list['page-status-systemlogs-ipsecvpn']['descr'] = "Allow access to the 'Status: System logs: IPsec VPN' page.";
$priv_list['page-status-systemlogs-ipsecvpn']['match'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['match'][] = "diag_logs_ipsec.php*";

$priv_list['page-status-systemlogs-openntpd'] = array();
$priv_list['page-status-systemlogs-openntpd']['name'] = "WebCfg - Status: System logs: OpenNTPD page";
$priv_list['page-status-systemlogs-openntpd']['descr'] = "Allow access to the 'Status: System logs: OpenNTPD' page.";
$priv_list['page-status-systemlogs-openntpd']['match'] = array();
$priv_list['page-status-systemlogs-openntpd']['match'][] = "diag_logs_ntpd.php*";

$priv_list['page-status-systemlogs-openvpn'] = array();
$priv_list['page-status-systemlogs-openvpn']['name'] = "WebCfg - Status: System logs: OpenVPN page";
$priv_list['page-status-systemlogs-openvpn']['descr'] = "Allow access to the 'Status: System logs: OpenVPN' page.";
$priv_list['page-status-systemlogs-openvpn']['match'] = array();
$priv_list['page-status-systemlogs-openvpn']['match'][] = "diag_logs_openvpn.php*";

$priv_list['page-status-systemlogs-ppp'] = array();
$priv_list['page-status-systemlogs-ppp']['name'] = "WebCfg - Status: System logs: IPsec VPN page";
$priv_list['page-status-systemlogs-ppp']['descr'] = "Allow access to the 'Status: System logs: IPsec VPN' page.";
$priv_list['page-status-systemlogs-ppp']['match'] = array();
$priv_list['page-status-systemlogs-ppp']['match'][] = "diag_logs_ppp.php*";

$priv_list['page-status-systemlogs-loadbalancer'] = array();
$priv_list['page-status-systemlogs-loadbalancer']['name'] = "WebCfg - Status: System logs: Load Balancer page";
$priv_list['page-status-systemlogs-loadbalancer']['descr'] = "Allow access to the 'Status: System logs: Load Balancer' page.";
$priv_list['page-status-systemlogs-loadbalancer']['match'] = array();
$priv_list['page-status-systemlogs-loadbalancer']['match'][] = "diag_logs_relayd.php*";

$priv_list['page-diagnostics-logs-settings'] = array();
$priv_list['page-diagnostics-logs-settings']['name'] = "WebCfg - Diagnostics: Logs: Settings page";
$priv_list['page-diagnostics-logs-settings']['descr'] = "Allow access to the 'Diagnostics: Logs: Settings' page.";
$priv_list['page-diagnostics-logs-settings']['match'] = array();
$priv_list['page-diagnostics-logs-settings']['match'][] = "diag_logs_settings.php*";

$priv_list['page-diagnostics-logs-pptpvpn'] = array();
$priv_list['page-diagnostics-logs-pptpvpn']['name'] = "WebCfg - Diagnostics: Logs: VPN page";
$priv_list['page-diagnostics-logs-pptpvpn']['descr'] = "Allow access to the 'Diagnostics: Logs: VPN' page.";
$priv_list['page-diagnostics-logs-pptpvpn']['match'] = array();
$priv_list['page-diagnostics-logs-pptpvpn']['match'][] = "diag_logs_vpn.php*";

$priv_list['page-diagnostics-nanobsd'] = array();
$priv_list['page-diagnostics-nanobsd']['name'] = "WebCfg - Diagnostics: NanoBSD";
$priv_list['page-diagnostics-nanobsd']['descr'] = "Allow access to the 'Diagnostics: NanoBSD' page.";
$priv_list['page-diagnostics-nanobsd']['match'] = array();
$priv_list['page-diagnostics-nanobsd']['match'][] = "diag_nanobsd.php*";

$priv_list['page-diagnostics-packetcapture'] = array();
$priv_list['page-diagnostics-packetcapture']['name'] = "WebCfg - Diagnostics: Packet Capture page";
$priv_list['page-diagnostics-packetcapture']['descr'] = "Allow access to the 'Diagnostics: Packet Capture' page.";
$priv_list['page-diagnostics-packetcapture']['match'] = array();
$priv_list['page-diagnostics-packetcapture']['match'][] = "diag_packet_capture.php*";

$priv_list['page-diagnostics-patters'] = array();
$priv_list['page-diagnostics-patters']['name'] = "WebCfg - Diagnostics: Patterns page";
$priv_list['page-diagnostics-patters']['descr'] = "Allow access to the 'Diagnostics: Patterns' page.";
$priv_list['page-diagnostics-patters']['match'] = array();
$priv_list['page-diagnostics-patters']['match'][] = "patterns.php*";

$priv_list['page-diag-system-activity'] = array();
$priv_list['page-diag-system-activity']['name'] = "WebCfg - Diagnostics: System Activity";
$priv_list['page-diag-system-activity']['descr'] = "Allows access to the 'Diagnostics: System Activity' page";
$priv_list['page-diag-system-activity']['match'] = array();
$priv_list['page-diag-system-activity']['match'][] = "diag_system_activity*";

$priv_list['page-diagnostics-ping'] = array();
$priv_list['page-diagnostics-ping']['name'] = "WebCfg - Diagnostics: Ping page";
$priv_list['page-diagnostics-ping']['descr'] = "Allow access to the 'Diagnostics: Ping' page.";
$priv_list['page-diagnostics-ping']['match'] = array();
$priv_list['page-diagnostics-ping']['match'][] = "diag_ping.php*";

$priv_list['page-status-packagelogs'] = array();
$priv_list['page-status-packagelogs']['name'] = "WebCfg - Status: Package logs page";
$priv_list['page-status-packagelogs']['descr'] = "Allow access to the 'Status: Package logs' page.";
$priv_list['page-status-packagelogs']['match'] = array();
$priv_list['page-status-packagelogs']['match'][] = "diag_pkglogs.php*";

$priv_list['page-diagnostics-resetstate'] = array();
$priv_list['page-diagnostics-resetstate']['name'] = "WebCfg - Diagnostics: Reset state page";
$priv_list['page-diagnostics-resetstate']['descr'] = "Allow access to the 'Diagnostics: Reset state' page.";
$priv_list['page-diagnostics-resetstate']['match'] = array();
$priv_list['page-diagnostics-resetstate']['match'][] = "diag_resetstate.php*";

$priv_list['page-diagnostics-routingtables'] = array();
$priv_list['page-diagnostics-routingtables']['name'] = "WebCfg - Diagnostics: Routing tables page";
$priv_list['page-diagnostics-routingtables']['descr'] = "Allow access to the 'Diagnostics: Routing tables' page.";
$priv_list['page-diagnostics-routingtables']['match'] = array();
$priv_list['page-diagnostics-routingtables']['match'][] = "diag_routes.php*";

$priv_list['page-diagnostics-statessummary'] = array();
$priv_list['page-diagnostics-statessummary']['name'] = "WebCfg - Diagnostics: States Summary page";
$priv_list['page-diagnostics-statessummary']['descr'] = "Allow access to the 'Diagnostics: States Summary' page.";
$priv_list['page-diagnostics-statessummary']['match'] = array();
$priv_list['page-diagnostics-statessummary']['match'][] = "diag_states_summary.php*";

$priv_list['page-diag-system-activity'] = array();
$priv_list['page-diag-system-activity']['name'] = "WebCfg - Diagnostics: System Activity";
$priv_list['page-diag-system-activity']['descr'] = "Allows access to the 'Diagnostics: System Activity' page";
$priv_list['page-diag-system-activity']['match'] = array();
$priv_list['page-diag-system-activity']['match'][] = "diag_system_activity*";

$priv_list['page-diag-system-activity'] = array();
$priv_list['page-diag-system-activity']['name'] = "WebCfg - Diagnostics: System Activity";
$priv_list['page-diag-system-activity']['descr'] = "Allows access to the 'Diagnostics: System Activity' page";
$priv_list['page-diag-system-activity']['match'] = array();
$priv_list['page-diag-system-activity']['match'][] = "diag_system_pftop.php*";

$priv_list['page-diagnostics-tables'] = array();
$priv_list['page-diagnostics-tables']['name'] = "WebCfg - Diagnostics: PF Table IP addresses";
$priv_list['page-diagnostics-tables']['descr'] = "Allow access to the 'Diagnostics: Tables' page.";
$priv_list['page-diagnostics-tables']['match'] = array();
$priv_list['page-diagnostics-tables']['match'][] = "diag_tables.php*";

$priv_list['page-diagnostics-traceroute'] = array();
$priv_list['page-diagnostics-traceroute']['name'] = "WebCfg - Diagnostics: Traceroute page";
$priv_list['page-diagnostics-traceroute']['descr'] = "Allow access to the 'Diagnostics: Traceroute' page.";
$priv_list['page-diagnostics-traceroute']['match'] = array();
$priv_list['page-diagnostics-traceroute']['match'][] = "diag_traceroute.php*";

$priv_list['page-diagnostics-edit'] = array();
$priv_list['page-diagnostics-edit']['name'] = "WebCfg - Diagnostics: Edit FIle";
$priv_list['page-diagnostics-edit']['descr'] = "Allow access to the 'Diagnostics: Edit File' page.";
$priv_list['page-diagnostics-edit']['match'] = array();
$priv_list['page-diagnostics-edit']['match'][] = "edit.php*";

$priv_list['page-diagnostics-command'] = array();
$priv_list['page-diagnostics-command']['name'] = "WebCfg - Diagnostics: Command page";
$priv_list['page-diagnostics-command']['descr'] = "Allow access to the 'Diagnostics: Command' page.";
$priv_list['page-diagnostics-command']['match'] = array();
$priv_list['page-diagnostics-command']['match'][] = "exec.php*";

$priv_list['page-hidden-execraw'] = array();
$priv_list['page-hidden-execraw']['name'] = "WebCfg - Hidden: Exec Raw page";
$priv_list['page-hidden-execraw']['descr'] = "Allow access to the 'Hidden: Exec Raw' page.";
$priv_list['page-hidden-execraw']['match'] = array();
$priv_list['page-hidden-execraw']['match'][] = "exec_raw.php*";

$priv_list['page-firewall-aliases'] = array();
$priv_list['page-firewall-aliases']['name'] = "WebCfg - Firewall: Aliases page";
$priv_list['page-firewall-aliases']['descr'] = "Allow access to the 'Firewall: Aliases' page.";
$priv_list['page-firewall-aliases']['match'] = array();
$priv_list['page-firewall-aliases']['match'][] = "firewall_aliases.php*";

$priv_list['page-firewall-alias-edit'] = array();
$priv_list['page-firewall-alias-edit']['name'] = "WebCfg - Firewall: Alias: Edit page";
$priv_list['page-firewall-alias-edit']['descr'] = "Allow access to the 'Firewall: Alias: Edit' page.";
$priv_list['page-firewall-alias-edit']['match'] = array();
$priv_list['page-firewall-alias-edit']['match'][] = "firewall_aliases_edit.php*";

$priv_list['page-firewall-alias-import'] = array();
$priv_list['page-firewall-alias-import']['name'] = "WebCfg - Firewall: Alias: Import page";
$priv_list['page-firewall-alias-import']['descr'] = "Allow access to the 'Firewall: Alias: Import' page.";
$priv_list['page-firewall-alias-import']['match'] = array();
$priv_list['page-firewall-alias-import']['match'][] = "firewall_aliases_import.php*";

$priv_list['page-firewall-nat-portforward'] = array();
$priv_list['page-firewall-nat-portforward']['name'] = "WebCfg - Firewall: NAT: Port Forward page";
$priv_list['page-firewall-nat-portforward']['descr'] = "Allow access to the 'Firewall: NAT: Port Forward' page.";
$priv_list['page-firewall-nat-portforward']['match'] = array();
$priv_list['page-firewall-nat-portforward']['match'][] = "firewall_nat.php*";

$priv_list['page-firewall-nat-1-1'] = array();
$priv_list['page-firewall-nat-1-1']['name'] = "WebCfg - Firewall: NAT: 1:1 page";
$priv_list['page-firewall-nat-1-1']['descr'] = "Allow access to the 'Firewall: NAT: 1:1' page.";
$priv_list['page-firewall-nat-1-1']['match'] = array();
$priv_list['page-firewall-nat-1-1']['match'][] = "firewall_nat_1to1.php*";

$priv_list['page-firewall-nat-1-1-edit'] = array();
$priv_list['page-firewall-nat-1-1-edit']['name'] = "WebCfg - Firewall: NAT: 1:1: Edit page";
$priv_list['page-firewall-nat-1-1-edit']['descr'] = "Allow access to the 'Firewall: NAT: 1:1: Edit' page.";
$priv_list['page-firewall-nat-1-1-edit']['match'] = array();
$priv_list['page-firewall-nat-1-1-edit']['match'][] = "firewall_nat_1to1_edit.php*";

$priv_list['page-firewall-nat-portforward-edit'] = array();
$priv_list['page-firewall-nat-portforward-edit']['name'] = "WebCfg - Firewall: NAT: Port Forward: Edit page";
$priv_list['page-firewall-nat-portforward-edit']['descr'] = "Allow access to the 'Firewall: NAT: Port Forward: Edit' page.";
$priv_list['page-firewall-nat-portforward-edit']['match'] = array();
$priv_list['page-firewall-nat-portforward-edit']['match'][] = "firewall_nat_edit.php*";

$priv_list['page-firewall-nat-outbound'] = array();
$priv_list['page-firewall-nat-outbound']['name'] = "WebCfg - Firewall: NAT: Outbound page";
$priv_list['page-firewall-nat-outbound']['descr'] = "Allow access to the 'Firewall: NAT: Outbound' page.";
$priv_list['page-firewall-nat-outbound']['match'] = array();
$priv_list['page-firewall-nat-outbound']['match'][] = "firewall_nat_out.php*";

$priv_list['page-firewall-nat-outbound-edit'] = array();
$priv_list['page-firewall-nat-outbound-edit']['name'] = "WebCfg - Firewall: NAT: Outbound: Edit page";
$priv_list['page-firewall-nat-outbound-edit']['descr'] = "Allow access to the 'Firewall: NAT: Outbound: Edit' page.";
$priv_list['page-firewall-nat-outbound-edit']['match'] = array();
$priv_list['page-firewall-nat-outbound-edit']['match'][] = "firewall_nat_out_edit.php*";

$priv_list['page-firewall-rules'] = array();
$priv_list['page-firewall-rules']['name'] = "WebCfg - Firewall: Rules page";
$priv_list['page-firewall-rules']['descr'] = "Allow access to the 'Firewall: Rules' page.";
$priv_list['page-firewall-rules']['match'] = array();
$priv_list['page-firewall-rules']['match'][] = "firewall_rules.php*";

$priv_list['page-firewall-rules-edit'] = array();
$priv_list['page-firewall-rules-edit']['name'] = "WebCfg - Firewall: Rules: Edit page";
$priv_list['page-firewall-rules-edit']['descr'] = "Allow access to the 'Firewall: Rules: Edit' page.";
$priv_list['page-firewall-rules-edit']['match'] = array();
$priv_list['page-firewall-rules-edit']['match'][] = "firewall_rules_edit.php*";

$priv_list['page-firewall-schedules'] = array();
$priv_list['page-firewall-schedules']['name'] = "WebCfg - Firewall: Schedules page";
$priv_list['page-firewall-schedules']['descr'] = "Allow access to the 'Firewall: Schedules' page.";
$priv_list['page-firewall-schedules']['match'] = array();
$priv_list['page-firewall-schedules']['match'][] = "firewall_schedule.php*";

$priv_list['page-firewall-schedules-edit'] = array();
$priv_list['page-firewall-schedules-edit']['name'] = "WebCfg - Firewall: Schedules: Edit page";
$priv_list['page-firewall-schedules-edit']['descr'] = "Allow access to the 'Firewall: Schedules: Edit' page.";
$priv_list['page-firewall-schedules-edit']['match'] = array();
$priv_list['page-firewall-schedules-edit']['match'][] = "firewall_schedule_edit.php*";

$priv_list['page-firewall-trafficshaper'] = array();
$priv_list['page-firewall-trafficshaper']['name'] = "WebCfg - Firewall: Traffic Shaper page";
$priv_list['page-firewall-trafficshaper']['descr'] = "Allow access to the 'Firewall: Traffic Shaper' page.";
$priv_list['page-firewall-trafficshaper']['match'] = array();
$priv_list['page-firewall-trafficshaper']['match'][] = "firewall_shaper.php*";

$priv_list['page-firewall-trafficshaper-layer7'] = array();
$priv_list['page-firewall-trafficshaper-layer7']['name'] = "WebCfg - Firewall: Traffic Shaper: Layer7 page";
$priv_list['page-firewall-trafficshaper-layer7']['descr'] = "Allow access to the 'Firewall: Traffic Shaper: Layer7' page.";
$priv_list['page-firewall-trafficshaper-layer7']['match'] = array();
$priv_list['page-firewall-trafficshaper-layer7']['match'][] = "firewall_shaper_layer7.php*";

$priv_list['page-firewall-trafficshaper-queues'] = array();
$priv_list['page-firewall-trafficshaper-queues']['name'] = "WebCfg - Firewall: Traffic Shaper: Queues page";
$priv_list['page-firewall-trafficshaper-queues']['descr'] = "Allow access to the 'Firewall: Traffic Shaper: Queues' page.";
$priv_list['page-firewall-trafficshaper-queues']['match'] = array();
$priv_list['page-firewall-trafficshaper-queues']['match'][] = "firewall_shaper_queues.php*";

$priv_list['page-firewall-trafficshaper-limiter'] = array();
$priv_list['page-firewall-trafficshaper-limiter']['name'] = "WebCfg - Firewall: Traffic Shaper: Limiter page";
$priv_list['page-firewall-trafficshaper-limiter']['descr'] = "Allow access to the 'Firewall: Traffic Shaper: Limiter' page.";
$priv_list['page-firewall-trafficshaper-limiter']['match'] = array();
$priv_list['page-firewall-trafficshaper-limiter']['match'][] = "firewall_shaper_vinterface.php*";

$priv_list['page-firewall-trafficshaper-wizard'] = array();
$priv_list['page-firewall-trafficshaper-wizard']['name'] = "WebCfg - Firewall: Traffic Shaper: Wizard page";
$priv_list['page-firewall-trafficshaper-wizard']['descr'] = "Allow access to the 'Firewall: Traffic Shaper: Wizard' page.";
$priv_list['page-firewall-trafficshaper-wizard']['match'] = array();
$priv_list['page-firewall-trafficshaper-wizard']['match'][] = "firewall_shaper_wizards.php*";

$priv_list['page-firewall-virtualipaddresses'] = array();
$priv_list['page-firewall-virtualipaddresses']['name'] = "WebCfg - Firewall: Virtual IP Addresses page";
$priv_list['page-firewall-virtualipaddresses']['descr'] = "Allow access to the 'Firewall: Virtual IP Addresses' page.";
$priv_list['page-firewall-virtualipaddresses']['match'] = array();
$priv_list['page-firewall-virtualipaddresses']['match'][] = "firewall_virtual_ip.php*";

$priv_list['page-firewall-virtualipaddress-edit'] = array();
$priv_list['page-firewall-virtualipaddress-edit']['name'] = "WebCfg - Firewall: Virtual IP Address: Edit page";
$priv_list['page-firewall-virtualipaddress-edit']['descr'] = "Allow access to the 'Firewall: Virtual IP Address: Edit' page.";
$priv_list['page-firewall-virtualipaddress-edit']['match'] = array();
$priv_list['page-firewall-virtualipaddress-edit']['match'][] = "firewall_virtual_ip_edit.php*";

$priv_list['page-getserviceproviders'] = array();
$priv_list['page-getserviceproviders']['name'] = "WebCfg - AJAX: Get Service Providers";
$priv_list['page-getserviceproviders']['descr'] = "Allow access to the 'AJAX: Service Providers' page.";
$priv_list['page-getserviceproviders']['match'] = array();
$priv_list['page-getserviceproviders']['match'][] = "getserviceproviders.php*";

$priv_list['page-getstats'] = array();
$priv_list['page-getstats']['name'] = "WebCfg - AJAX: Get Stats";
$priv_list['page-getstats']['descr'] = "Allow access to the 'AJAX: Get Stats' page.";
$priv_list['page-getstats']['match'] = array();
$priv_list['page-getstats']['match'][] = "getstats.php*";

$priv_list['page-diagnostics-interfacetraffic'] = array();
$priv_list['page-diagnostics-interfacetraffic']['name'] = "WebCfg - Diagnostics: Interface Traffic page";
$priv_list['page-diagnostics-interfacetraffic']['descr'] = "Allow access to the 'Diagnostics: Interface Traffic' page.";
$priv_list['page-diagnostics-interfacetraffic']['match'] = array();
$priv_list['page-diagnostics-interfacetraffic']['match'][] = "graph.php*";

$priv_list['page-diagnostics-cpuutilization'] = array();
$priv_list['page-diagnostics-cpuutilization']['name'] = "WebCfg - Diagnostics: CPU Utilization page";
$priv_list['page-diagnostics-cpuutilization']['descr'] = "Allow access to the 'Diagnostics: CPU Utilization' page.";
$priv_list['page-diagnostics-cpuutilization']['match'] = array();
$priv_list['page-diagnostics-cpuutilization']['match'][] = "graph_cpu.php*";

$priv_list['page-diagnostics-haltsystem'] = array();
$priv_list['page-diagnostics-haltsystem']['name'] = "WebCfg - Diagnostics: Halt system page";
$priv_list['page-diagnostics-haltsystem']['descr'] = "Allow access to the 'Diagnostics: Halt system' page.";
$priv_list['page-diagnostics-haltsystem']['match'] = array();
$priv_list['page-diagnostics-haltsystem']['match'][] = "halt.php*";

$priv_list['page-requiredforjavascript'] = array();
$priv_list['page-requiredforjavascript']['name'] = "WebCfg - Required for javascript page";
$priv_list['page-requiredforjavascript']['descr'] = "Allow access to the 'Required for javascript' page.";
$priv_list['page-requiredforjavascript']['match'] = array();
$priv_list['page-requiredforjavascript']['match'][] = "headjs.php*";

$priv_list['page-xmlrpcinterfacestats'] = array();
$priv_list['page-xmlrpcinterfacestats']['name'] = "WebCfg - XMLRPC Interface Stats page";
$priv_list['page-xmlrpcinterfacestats']['descr'] = "Allow access to the 'XMLRPC Interface Stats' page.";
$priv_list['page-xmlrpcinterfacestats']['match'] = array();
$priv_list['page-xmlrpcinterfacestats']['match'][] = "ifstats.php*";

$priv_list['page-system-login/logout'] = array();
$priv_list['page-system-login/logout']['name'] = "WebCfg - System: Login / Logout page / Dashboard";
$priv_list['page-system-login/logout']['descr'] = "Allow access to the 'System: Login / Logout' page and Dashboard.";
$priv_list['page-system-login/logout']['match'] = array();
$priv_list['page-system-login/logout']['match'][] = "index.php*";

$priv_list['page-interfaces'] = array();
$priv_list['page-interfaces']['name'] = "WebCfg - Interfaces: WAN page";
$priv_list['page-interfaces']['descr'] = "Allow access to the 'Interfaces' page.";
$priv_list['page-interfaces']['match'] = array();
$priv_list['page-interfaces']['match'][] = "interfaces.php*";

$priv_list['page-interfaces-assignnetworkports'] = array();
$priv_list['page-interfaces-assignnetworkports']['name'] = "WebCfg - Interfaces: Assign network ports page";
$priv_list['page-interfaces-assignnetworkports']['descr'] = "Allow access to the 'Interfaces: Assign network ports' page.";
$priv_list['page-interfaces-assignnetworkports']['match'] = array();
$priv_list['page-interfaces-assignnetworkports']['match'][] = "interfaces_assign.php*";

$priv_list['page-interfaces-bridge'] = array();
$priv_list['page-interfaces-bridge']['name'] = "WebCfg - Interfaces: Bridge page";
$priv_list['page-interfaces-bridge']['descr'] = "Allow access to the 'Interfaces: Bridge' page.";
$priv_list['page-interfaces-bridge']['match'] = array();
$priv_list['page-interfaces-bridge']['match'][] = "interfaces_bridge.php*";

$priv_list['page-interfaces-bridge-edit'] = array();
$priv_list['page-interfaces-bridge-edit']['name'] = "WebCfg - Interfaces: Bridge edit page";
$priv_list['page-interfaces-bridge-edit']['descr'] = "Allow access to the 'Interfaces: Bridge : Edit' page.";
$priv_list['page-interfaces-bridge-edit']['match'] = array();
$priv_list['page-interfaces-bridge-edit']['match'][] = "interfaces_bridge_edit.php*";

$priv_list['page-interfaces-gif'] = array();
$priv_list['page-interfaces-gif']['name'] = "WebCfg - Interfaces: GIF page";
$priv_list['page-interfaces-gif']['descr'] = "Allow access to the 'Interfaces: GIF' page.";
$priv_list['page-interfaces-gif']['match'] = array();
$priv_list['page-interfaces-gif']['match'][] = "interfaces_gif.php*";

$priv_list['page-interfaces-gif-edit'] = array();
$priv_list['page-interfaces-gif-edit']['name'] = "WebCfg - Interfaces: GIF: Edit page";
$priv_list['page-interfaces-gif-edit']['descr'] = "Allow access to the 'Interfaces: GIF: Edit' page.";
$priv_list['page-interfaces-gif-edit']['match'] = array();
$priv_list['page-interfaces-gif-edit']['match'][] = "interfaces_gif_edit.php*";

$priv_list['page-interfaces-gre'] = array();
$priv_list['page-interfaces-gre']['name'] = "WebCfg - Interfaces: GRE page";
$priv_list['page-interfaces-gre']['descr'] = "Allow access to the 'Interfaces: GRE' page.";
$priv_list['page-interfaces-gre']['match'] = array();
$priv_list['page-interfaces-gre']['match'][] = "interfaces_gre.php*";

$priv_list['page-interfaces-gre-edit'] = array();
$priv_list['page-interfaces-gre-edit']['name'] = "WebCfg - Interfaces: GRE: Edit page";
$priv_list['page-interfaces-gre-edit']['descr'] = "Allow access to the 'Interfaces: GRE: Edit' page.";
$priv_list['page-interfaces-gre-edit']['match'] = array();
$priv_list['page-interfaces-gre-edit']['match'][] = "interfaces_gre_edit.php*";

$priv_list['page-interfaces-groups'] = array();
$priv_list['page-interfaces-groups']['name'] = "WebCfg - Interfaces: Groups page";
$priv_list['page-interfaces-groups']['descr'] = "Create interface groups";
$priv_list['page-interfaces-groups']['match'] = array();
$priv_list['page-interfaces-groups']['match'][] = "interfaces_groups.php*";

$priv_list['page-interfacess-groups'] = array();
$priv_list['page-interfacess-groups']['name'] = "WebCfg - Interfaces: Groups: Edit page";
$priv_list['page-interfacess-groups']['descr'] = "Edit Interface groups";
$priv_list['page-interfacess-groups']['match'] = array();
$priv_list['page-interfacess-groups']['match'][] = "interfaces_groups_edit.php*";

$priv_list['page-interfacess-lagg'] = array();
$priv_list['page-interfacess-lagg']['name'] = "WebCfg - Interfaces: LAGG: page";
$priv_list['page-interfacess-lagg']['descr'] = "Edit Interface LAGG";
$priv_list['page-interfacess-lagg']['match'] = array();
$priv_list['page-interfacess-lagg']['match'][] = "interfaces_lagg.php*";

$priv_list['page-interfacess-lagg'] = array();
$priv_list['page-interfacess-lagg']['name'] = "WebCfg - Interfaces: LAGG: Edit page";
$priv_list['page-interfacess-lagg']['descr'] = "Edit Interface LAGG";
$priv_list['page-interfacess-lagg']['match'] = array();
$priv_list['page-interfacess-lagg']['match'][] = "interfaces_lagg_edit.php*";

$priv_list['page-interfaces-ppps'] = array();
$priv_list['page-interfaces-ppps']['name'] = "WebCfg - Interfaces: ppps page";
$priv_list['page-interfaces-ppps']['descr'] = "Allow access to the 'Interfaces: ppps' page.";
$priv_list['page-interfaces-ppps']['match'] = array();
$priv_list['page-interfaces-ppps']['match'][] = "interfaces_ppps.php*";

$priv_list['page-interfaces-ppps-edit'] = array();
$priv_list['page-interfaces-ppps-edit']['name'] = "WebCfg - Interfaces: PPPs: Edit page";
$priv_list['page-interfaces-ppps-edit']['descr'] = "Allow access to the 'Interfaces: PPPs: Edit' page.";
$priv_list['page-interfaces-ppps-edit']['match'] = array();
$priv_list['page-interfaces-ppps-edit']['match'][] = "interfaces_ppps_edit.php*";

$priv_list['page-interfaces-qinq'] = array();
$priv_list['page-interfaces-qinq']['name'] = "WebCfg - Interfaces: QinQ page";
$priv_list['page-interfaces-qinq']['descr'] = "Allow access to the 'Interfaces: QinQ' page.";
$priv_list['page-interfaces-qinq']['match'] = array();
$priv_list['page-interfaces-qinq']['match'][] = "interfaces_qinq.php*";

$priv_list['page-interfacess-qinq'] = array();
$priv_list['page-interfacess-qinq']['name'] = "WebCfg - Interfaces: QinQ: Edit page";
$priv_list['page-interfacess-qinq']['descr'] = "Edit Interface qinq";
$priv_list['page-interfacess-qinq']['match'] = array();
$priv_list['page-interfacess-qinq']['match'][] = "interfaces_qinq_edit.php*";

$priv_list['page-interfaces-vlan'] = array();
$priv_list['page-interfaces-vlan']['name'] = "WebCfg - Interfaces: VLAN page";
$priv_list['page-interfaces-vlan']['descr'] = "Allow access to the 'Interfaces: VLAN' page.";
$priv_list['page-interfaces-vlan']['match'] = array();
$priv_list['page-interfaces-vlan']['match'][] = "interfaces_vlan.php*";

$priv_list['page-interfaces-vlan-edit'] = array();
$priv_list['page-interfaces-vlan-edit']['name'] = "WebCfg - Interfaces: VLAN: Edit page";
$priv_list['page-interfaces-vlan-edit']['descr'] = "Allow access to the 'Interfaces: VLAN: Edit' page.";
$priv_list['page-interfaces-vlan-edit']['match'] = array();
$priv_list['page-interfaces-vlan-edit']['match'][] = "interfaces_vlan_edit.php*";

$priv_list['page-interfaces-wireless'] = array();
$priv_list['page-interfaces-wireless']['name'] = "WebCfg - Interfaces: Wireless page";
$priv_list['page-interfaces-wireless']['descr'] = "Allow access to the 'Interfaces: Wireless' page.";
$priv_list['page-interfaces-wireless']['match'] = array();
$priv_list['page-interfaces-wireless']['match'][] = "interfaces_wireless.php*";

$priv_list['page-interfaces-wireless-edit'] = array();
$priv_list['page-interfaces-wireless-edit']['name'] = "WebCfg - Interfaces: Wireless edit page";
$priv_list['page-interfaces-wireless-edit']['descr'] = "Allow access to the 'Interfaces: Wireless : Edit' page.";
$priv_list['page-interfaces-wireless-edit']['match'] = array();
$priv_list['page-interfaces-wireless-edit']['match'][] = "interfaces_wireless_edit.php*";

$priv_list['page-system-license'] = array();
$priv_list['page-system-license']['name'] = "WebCfg - System: License page";
$priv_list['page-system-license']['descr'] = "Allow access to the 'System: License' page.";
$priv_list['page-system-license']['match'] = array();
$priv_list['page-system-license']['match'][] = "license.php*";

$priv_list['page-services-loadbalancer-monitor'] = array();
$priv_list['page-services-loadbalancer-monitor']['name'] = "WebCfg - Services: Load Balancer: Monitors page";
$priv_list['page-services-loadbalancer-monitor']['descr'] = "Allow access to the 'Services: Load Balancer: Monitors' page.";
$priv_list['page-services-loadbalancer-monitor']['match'] = array();
$priv_list['page-services-loadbalancer-monitor']['match'][] = "load_balancer_monitor.php*";

$priv_list['page-services-loadbalancer-monitor-edit'] = array();
$priv_list['page-services-loadbalancer-monitor-edit']['name'] = "WebCfg - Services: Load Balancer: Monitor: Edit page";
$priv_list['page-services-loadbalancer-monitor-edit']['descr'] = "Allow access to the 'Services: Load Balancer: Monitor: Edit' page.";
$priv_list['page-services-loadbalancer-monitor-edit']['match'] = array();
$priv_list['page-services-loadbalancer-monitor-edit']['match'][] = "load_balancer_monitor_edit.php*";

$priv_list['page-loadbalancer-pool'] = array();
$priv_list['page-loadbalancer-pool']['name'] = "WebCfg - Load Balancer: Pool page";
$priv_list['page-loadbalancer-pool']['descr'] = "Allow access to the 'Load Balancer: Pool' page.";
$priv_list['page-loadbalancer-pool']['match'] = array();
$priv_list['page-loadbalancer-pool']['match'][] = "load_balancer_pool.php*";

$priv_list['page-loadbalancer-pool-edit'] = array();
$priv_list['page-loadbalancer-pool-edit']['name'] = "WebCfg - Load Balancer: Pool: Edit page";
$priv_list['page-loadbalancer-pool-edit']['descr'] = "Allow access to the 'Load Balancer: Pool: Edit' page.";
$priv_list['page-loadbalancer-pool-edit']['match'] = array();
$priv_list['page-loadbalancer-pool-edit']['match'][] = "load_balancer_pool_edit.php*";

$priv_list['page-services-loadbalancer-relay-action'] = array();
$priv_list['page-services-loadbalancer-relay-action']['name'] = "WebCfg - Services: Load Balancer: Relay Actions page";
$priv_list['page-services-loadbalancer-relay-action']['descr'] = "Allow access to the 'Services: Load Balancer: Relay Actions' page.";
$priv_list['page-services-loadbalancer-relay-action']['match'] = array();
$priv_list['page-services-loadbalancer-relay-action']['match'][] = "load_balancer_relay_action.php*";

$priv_list['page-services-loadbalancer-relay-action-edit'] = array();
$priv_list['page-services-loadbalancer-relay-action-edit']['name'] = "WebCfg - Services: Load Balancer: Relay Action: Edit page";
$priv_list['page-services-loadbalancer-relay-action-edit']['descr'] = "Allow access to the 'Services: Load Balancer: Relay Action: Edit' page.";
$priv_list['page-services-loadbalancer-relay-action-edit']['match'] = array();
$priv_list['page-services-loadbalancer-relay-action-edit']['match'][] = "load_balancer_relay_action_edit.php*";

$priv_list['page-services-loadbalancer-relay-protocol'] = array();
$priv_list['page-services-loadbalancer-relay-protocol']['name'] = "WebCfg - Services: Load Balancer: Relay Protocols page";
$priv_list['page-services-loadbalancer-relay-protocol']['descr'] = "Allow access to the 'Services: Load Balancer: Relay Protocols' page.";
$priv_list['page-services-loadbalancer-relay-protocol']['match'] = array();
$priv_list['page-services-loadbalancer-relay-protocol']['match'][] = "load_balancer_relay_protocol.php*";

$priv_list['page-services-loadbalancer-relay-protocol-edit'] = array();
$priv_list['page-services-loadbalancer-relay-protocol-edit']['name'] = "WebCfg - Services: Load Balancer: Relay Protocol: Edit page";
$priv_list['page-services-loadbalancer-relay-protocol-edit']['descr'] = "Allow access to the 'Services: Load Balancer: Relay Protocol: Edit' page.";
$priv_list['page-services-loadbalancer-relay-protocol-edit']['match'] = array();
$priv_list['page-services-loadbalancer-relay-protocol-edit']['match'][] = "load_balancer_relay_protocol_edit.php*";

$priv_list['page-services-loadbalancer-virtualservers'] = array();
$priv_list['page-services-loadbalancer-virtualservers']['name'] = "WebCfg - Services: Load Balancer: Virtual Servers page";
$priv_list['page-services-loadbalancer-virtualservers']['descr'] = "Allow access to the 'Services: Load Balancer: Virtual Servers' page.";
$priv_list['page-services-loadbalancer-virtualservers']['match'] = array();
$priv_list['page-services-loadbalancer-virtualservers']['match'][] = "load_balancer_virtual_server.php*";

$priv_list['page-loadbalancer-virtualserver-edit'] = array();
$priv_list['page-loadbalancer-virtualserver-edit']['name'] = "WebCfg - Load Balancer: Virtual Server: Edit page";
$priv_list['page-loadbalancer-virtualserver-edit']['descr'] = "Allow access to the 'Load Balancer: Virtual Server: Edit' page.";
$priv_list['page-loadbalancer-virtualserver-edit']['match'] = array();
$priv_list['page-loadbalancer-virtualserver-edit']['match'][] = "load_balancer_virtual_server_edit.php*";

$priv_list['page-package-settings'] = array();
$priv_list['page-package-settings']['name'] = "WebCfg - Package: Settings page";
$priv_list['page-package-settings']['descr'] = "Allow access to the 'Package: Settings' page.";
$priv_list['page-package-settings']['match'] = array();
$priv_list['page-package-settings']['match'][] = "pkg.php*";

$priv_list['page-package-edit'] = array();
$priv_list['page-package-edit']['name'] = "WebCfg - Package: Edit page";
$priv_list['page-package-edit']['descr'] = "Allow access to the 'Package: Edit' page.";
$priv_list['page-package-edit']['match'] = array();
$priv_list['page-package-edit']['match'][] = "pkg_edit.php*";

$priv_list['page-system-packagemanager'] = array();
$priv_list['page-system-packagemanager']['name'] = "WebCfg - System: Package Manager page";
$priv_list['page-system-packagemanager']['descr'] = "Allow access to the 'System: Package Manager' page.";
$priv_list['page-system-packagemanager']['match'] = array();
$priv_list['page-system-packagemanager']['match'][] = "pkg_mgr.php*";

$priv_list['page-system-packagemanager-installpackage'] = array();
$priv_list['page-system-packagemanager-installpackage']['name'] = "WebCfg - System: Package Manager: Install Package page";
$priv_list['page-system-packagemanager-installpackage']['descr'] = "Allow access to the 'System: Package Manager: Install Package' page.";
$priv_list['page-system-packagemanager-installpackage']['match'] = array();
$priv_list['page-system-packagemanager-installpackage']['match'][] = "pkg_mgr_install.php*";

$priv_list['page-system-packagemanager-installed'] = array();
$priv_list['page-system-packagemanager-installed']['name'] = "WebCfg - System: Package Manager: Installed page";
$priv_list['page-system-packagemanager-installed']['descr'] = "Allow access to the 'System: Package Manager: Installed' page.";
$priv_list['page-system-packagemanager-installed']['match'] = array();
$priv_list['page-system-packagemanager-installed']['match'][] = "pkg_mgr_installed.php*";

$priv_list['page-pkg-mgr-settings'] = array();
$priv_list['page-pkg-mgr-settings']['name'] = "WebCfg - Packages: Settings page";
$priv_list['page-pkg-mgr-settings']['descr'] = "Allow access to the 'Packages: Settings' page.";
$priv_list['page-pkg-mgr-settings']['match'] = array();
$priv_list['page-pkg-mgr-settings']['match'][] = "pkg_mgr_settings.php*";

$priv_list['page-diagnostics-rebootsystem'] = array();
$priv_list['page-diagnostics-rebootsystem']['name'] = "WebCfg - Diagnostics: Reboot System page";
$priv_list['page-diagnostics-rebootsystem']['descr'] = "Allow access to the 'Diagnostics: Reboot System' page.";
$priv_list['page-diagnostics-rebootsystem']['match'] = array();
$priv_list['page-diagnostics-rebootsystem']['match'][] = "reboot.php*";

$priv_list['page-diagnostics-restart-httpd'] = array();
$priv_list['page-diagnostics-restart-httpd']['name'] = "WebCfg - Diagnostics: Restart HTTPD : System page";
$priv_list['page-diagnostics-restart-httpd']['descr'] = "Allow access to the 'Diagnostics: Restart HTTPD: System' page.";
$priv_list['page-diagnostics-restart-httpd']['match'] = array();
$priv_list['page-diagnostics-restart-httpd']['match'][] = "restart_httpd.php*";

$priv_list['page-services-captiveportal'] = array();
$priv_list['page-services-captiveportal']['name'] = "WebCfg - Services: Captive portal page";
$priv_list['page-services-captiveportal']['descr'] = "Allow access to the 'Services: Captive portal' page.";
$priv_list['page-services-captiveportal']['match'] = array();
$priv_list['page-services-captiveportal']['match'][] = "services_captiveportal.php*";

$priv_list['page-services-captiveportal-filemanager'] = array();
$priv_list['page-services-captiveportal-filemanager']['name'] = "WebCfg - Services: Captive portal: File Manager page";
$priv_list['page-services-captiveportal-filemanager']['descr'] = "Allow access to the 'Services: Captive portal: File Manager' page.";
$priv_list['page-services-captiveportal-filemanager']['match'] = array();
$priv_list['page-services-captiveportal-filemanager']['match'][] = "services_captiveportal_filemanager.php*";

$priv_list['page-services-captiveportal-allowedhostnames'] = array();
$priv_list['page-services-captiveportal-allowedhostnames']['name'] = "WebCfg - Services: Captive portal: Allowed IPs page";
$priv_list['page-services-captiveportal-allowedhostnames']['descr'] = "Allow access to the 'Services: Captive portal: Allowed IPs' page.";
$priv_list['page-services-captiveportal-allowedhostnames']['match'] = array();
$priv_list['page-services-captiveportal-allowedhostnames']['match'][] = "services_captiveportal_ip.php*";

$priv_list['page-services-captiveportal-editallowedhostnames'] = array();
$priv_list['page-services-captiveportal-editallowedhostnames']['name'] = "WebCfg - Services: Captive portal: Edit Allowed IPs page";
$priv_list['page-services-captiveportal-editallowedhostnames']['descr'] = "Allow access to the 'Services: Captive portal: Edit Allowed IPs' page.";
$priv_list['page-services-captiveportal-editallowedhostnames']['match'] = array();
$priv_list['page-services-captiveportal-editallowedhostnames']['match'][] = "services_captiveportal_hostname_edit.php*";

$priv_list['page-services-captiveportal-allowedips'] = array();
$priv_list['page-services-captiveportal-allowedips']['name'] = "WebCfg - Services: Captive portal: Allowed IPs page";
$priv_list['page-services-captiveportal-allowedips']['descr'] = "Allow access to the 'Services: Captive portal: Allowed IPs' page.";
$priv_list['page-services-captiveportal-allowedips']['match'] = array();
$priv_list['page-services-captiveportal-allowedips']['match'][] = "services_captiveportal_ip.php*";

$priv_list['page-services-captiveportal-editallowedips'] = array();
$priv_list['page-services-captiveportal-editallowedips']['name'] = "WebCfg - Services: Captive portal: Edit Allowed IPs page";
$priv_list['page-services-captiveportal-editallowedips']['descr'] = "Allow access to the 'Services: Captive portal: Edit Allowed IPs' page.";
$priv_list['page-services-captiveportal-editallowedips']['match'] = array();
$priv_list['page-services-captiveportal-editallowedips']['match'][] = "services_captiveportal_ip_edit.php*";

$priv_list['page-services-captiveportal-macaddresses'] = array();
$priv_list['page-services-captiveportal-macaddresses']['name'] = "WebCfg - Services: Captive portal: Mac Addresses page";
$priv_list['page-services-captiveportal-macaddresses']['descr'] = "Allow access to the 'Services: Captive portal: Mac Addresses' page.";
$priv_list['page-services-captiveportal-macaddresses']['match'] = array();
$priv_list['page-services-captiveportal-macaddresses']['match'][] = "services_captiveportal_mac.php*";

$priv_list['page-services-captiveportal-editmacaddresses'] = array();
$priv_list['page-services-captiveportal-editmacaddresses']['name'] = "WebCfg - Services: Captive portal: Edit MAC Addresses page";
$priv_list['page-services-captiveportal-editmacaddresses']['descr'] = "Allow access to the 'Services: Captive portal: Edit MAC Addresses' page.";
$priv_list['page-services-captiveportal-editmacaddresses']['match'] = array();
$priv_list['page-services-captiveportal-editmacaddresses']['match'][] = "services_captiveportal_mac_edit.php*";

$priv_list['page-services-captiveportal-vouchers'] = array();
$priv_list['page-services-captiveportal-vouchers']['name'] = "WebCfg - Services: Captive portal Vouchers page";
$priv_list['page-services-captiveportal-vouchers']['descr'] = "Allow access to the 'Services: Captive portal Vouchers' page.";
$priv_list['page-services-captiveportal-vouchers']['match'] = array();
$priv_list['page-services-captiveportal-vouchers']['match'][] = "services_captiveportal_vouchers.php*";

$priv_list['page-services-captiveportal-voucher-edit'] = array();
$priv_list['page-services-captiveportal-voucher-edit']['name'] = "WebCfg - Services: Captive portal Voucher Rolls page";
$priv_list['page-services-captiveportal-voucher-edit']['descr'] = "Allow access to the 'Services: Captive portal Edit Voucher Rolls' page.";
$priv_list['page-services-captiveportal-voucher-edit']['match'] = array();
$priv_list['page-services-captiveportal-voucher-edit']['match'][] = "services_captiveportal_vouchers_edit.php*";

$priv_list['page-services-dhcpserver'] = array();
$priv_list['page-services-dhcpserver']['name'] = "WebCfg - Services: DHCP server page";
$priv_list['page-services-dhcpserver']['descr'] = "Allow access to the 'Services: DHCP server' page.";
$priv_list['page-services-dhcpserver']['match'] = array();
$priv_list['page-services-dhcpserver']['match'][] = "services_dhcp.php*";

$priv_list['page-services-dhcpserver-editstaticmapping'] = array();
$priv_list['page-services-dhcpserver-editstaticmapping']['name'] = "WebCfg - Services: DHCP Server : Edit static mapping page";
$priv_list['page-services-dhcpserver-editstaticmapping']['descr'] = "Allow access to the 'Services: DHCP Server : Edit static mapping' page.";
$priv_list['page-services-dhcpserver-editstaticmapping']['match'] = array();
$priv_list['page-services-dhcpserver-editstaticmapping']['match'][] = "services_dhcp_edit.php*";

$priv_list['page-services-dhcprelay'] = array();
$priv_list['page-services-dhcprelay']['name'] = "WebCfg - Services: DHCP Relay page";
$priv_list['page-services-dhcprelay']['descr'] = "Allow access to the 'Services: DHCP Relay' page.";
$priv_list['page-services-dhcprelay']['match'] = array();
$priv_list['page-services-dhcprelay']['match'][] = "services_dhcp_relay.php*";

$priv_list['page-services-dnsforwarder'] = array();
$priv_list['page-services-dnsforwarder']['name'] = "WebCfg - Services: DNS Forwarder page";
$priv_list['page-services-dnsforwarder']['descr'] = "Allow access to the 'Services: DNS Forwarder' page.";
$priv_list['page-services-dnsforwarder']['match'] = array();
$priv_list['page-services-dnsforwarder']['match'][] = "services_dnsmasq.php*";

$priv_list['page-services-dnsforwarder-editdomainoverride'] = array();
$priv_list['page-services-dnsforwarder-editdomainoverride']['name'] = "WebCfg - Services: DNS Forwarder: Edit Domain Override page";
$priv_list['page-services-dnsforwarder-editdomainoverride']['descr'] = "Allow access to the 'Services: DNS Forwarder: Edit Domain Override' page.";
$priv_list['page-services-dnsforwarder-editdomainoverride']['match'] = array();
$priv_list['page-services-dnsforwarder-editdomainoverride']['match'][] = "services_dnsmasq_domainoverride_edit.php*";

$priv_list['page-services-dnsforwarder-edithost'] = array();
$priv_list['page-services-dnsforwarder-edithost']['name'] = "WebCfg - Services: DNS Forwarder: Edit host page";
$priv_list['page-services-dnsforwarder-edithost']['descr'] = "Allow access to the 'Services: DNS Forwarder: Edit host' page.";
$priv_list['page-services-dnsforwarder-edithost']['match'] = array();
$priv_list['page-services-dnsforwarder-edithost']['match'][] = "services_dnsmasq_edit.php*";

$priv_list['page-services-dynamicdnsclients'] = array();
$priv_list['page-services-dynamicdnsclients']['name'] = "WebCfg - Services: Dynamic DNS clients page";
$priv_list['page-services-dynamicdnsclients']['descr'] = "Allow access to the 'Services: Dynamic DNS clients' page.";
$priv_list['page-services-dynamicdnsclients']['match'] = array();
$priv_list['page-services-dynamicdnsclients']['match'][] = "services_dyndns.php*";

$priv_list['page-services-dynamicdnsclient'] = array();
$priv_list['page-services-dynamicdnsclient']['name'] = "WebCfg - Services: Dynamic DNS client page";
$priv_list['page-services-dynamicdnsclient']['descr'] = "Allow access to the 'Services: Dynamic DNS client' page.";
$priv_list['page-services-dynamicdnsclient']['match'] = array();
$priv_list['page-services-dynamicdnsclient']['match'][] = "services_dyndns_edit.php*";

$priv_list['page-services-igmpproxy'] = array();
$priv_list['page-services-igmpproxy']['name'] = "WebCfg - Services: Igmpproxy page";
$priv_list['page-services-igmpproxy']['descr'] = "Allow access to the 'Services: Igmpproxy' page.";
$priv_list['page-services-igmpproxy']['match'] = array();
$priv_list['page-services-igmpproxy']['match'][] = "services_igmpproxy.php*";

$priv_list['page-services-igmpproxy'] = array();
$priv_list['page-services-igmpproxy']['name'] = "WebCfg - Firewall: Igmpproxy: Edit page";
$priv_list['page-services-igmpproxy']['descr'] = "Allow access to the 'Firewall: Igmpproxy' page.";
$priv_list['page-services-igmpproxy']['match'] = array();
$priv_list['page-services-igmpproxy']['match'][] = "services_igmpproxy_edit.php*";

$priv_list['page-services-rfc2136clients'] = array();
$priv_list['page-services-rfc2136clients']['name'] = "WebCfg - Services: RFC 2136 clients page";
$priv_list['page-services-rfc2136clients']['descr'] = "Allow access to the 'Services: RFC 2136 clients' page.";
$priv_list['page-services-rfc2136clients']['match'] = array();
$priv_list['page-services-rfc2136clients']['match'][] = "services_rfc2136.php*";

$priv_list['page-services-snmp'] = array();
$priv_list['page-services-snmp']['name'] = "WebCfg - Services: SNMP page";
$priv_list['page-services-snmp']['descr'] = "Allow access to the 'Services: SNMP' page.";
$priv_list['page-services-snmp']['match'] = array();
$priv_list['page-services-snmp']['match'][] = "services_snmp.php*";

$priv_list['page-services-wakeonlan'] = array();
$priv_list['page-services-wakeonlan']['name'] = "WebCfg - Services: Wake on LAN page";
$priv_list['page-services-wakeonlan']['descr'] = "Allow access to the 'Services: Wake on LAN' page.";
$priv_list['page-services-wakeonlan']['match'] = array();
$priv_list['page-services-wakeonlan']['match'][] = "services_wol.php*";

$priv_list['page-services-wakeonlan-edit'] = array();
$priv_list['page-services-wakeonlan-edit']['name'] = "WebCfg - Services: Wake on LAN: Edit page";
$priv_list['page-services-wakeonlan-edit']['descr'] = "Allow access to the 'Services: Wake on LAN: Edit' page.";
$priv_list['page-services-wakeonlan-edit']['match'] = array();
$priv_list['page-services-wakeonlan-edit']['match'][] = "services_wol_edit.php*";

$priv_list['page-diagnostics-cpuutilization'] = array();
$priv_list['page-diagnostics-cpuutilization']['name'] = "WebCfg - Diagnostics: CPU Utilization page";
$priv_list['page-diagnostics-cpuutilization']['descr'] = "Allow access to the 'Diagnostics: CPU Utilization' page.";
$priv_list['page-diagnostics-cpuutilization']['match'] = array();
$priv_list['page-diagnostics-cpuutilization']['match'][] = "stats.php*";

$priv_list['page-hidden-detailedstatus'] = array();
$priv_list['page-hidden-detailedstatus']['name'] = "WebCfg - Hidden: Detailed Status page";
$priv_list['page-hidden-detailedstatus']['descr'] = "Allow access to the 'Hidden: Detailed Status' page.";
$priv_list['page-hidden-detailedstatus']['match'] = array();
$priv_list['page-hidden-detailedstatus']['match'][] = "status.php*";

$priv_list['page-status-captiveportal'] = array();
$priv_list['page-status-captiveportal']['name'] = "WebCfg - Status: Captive portal page";
$priv_list['page-status-captiveportal']['descr'] = "Allow access to the 'Status: Captive portal' page.";
$priv_list['page-status-captiveportal']['match'] = array();
$priv_list['page-status-captiveportal']['match'][] = "status_captiveportal.php*";

$priv_list['page-status-captiveportal-test'] = array();
$priv_list['page-status-captiveportal-test']['name'] = "WebCfg - Status: Captive portal test Vouchers page";
$priv_list['page-status-captiveportal-test']['descr'] = "Allow access to the 'Status: Captive portal Test Vouchers' page.";
$priv_list['page-status-captiveportal-test']['match'] = array();
$priv_list['page-status-captiveportal-test']['match'][] = "status_captiveportal_test.php*";

$priv_list['page-status-captiveportal-voucher-rolls'] = array();
$priv_list['page-status-captiveportal-voucher-rolls']['name'] = "WebCfg - Status: Captive portal Voucher Rolls page";
$priv_list['page-status-captiveportal-voucher-rolls']['descr'] = "Allow access to the 'Status: Captive portal Voucher Rolls' page.";
$priv_list['page-status-captiveportal-voucher-rolls']['match'] = array();
$priv_list['page-status-captiveportal-voucher-rolls']['match'][] = "status_captiveportal_voucher_rolls.php*";

$priv_list['page-status-captiveportal-vouchers'] = array();
$priv_list['page-status-captiveportal-vouchers']['name'] = "WebCfg - Status: Captive portal Vouchers page";
$priv_list['page-status-captiveportal-vouchers']['descr'] = "Allow access to the 'Status: Captive portal Vouchers' page.";
$priv_list['page-status-captiveportal-vouchers']['match'] = array();
$priv_list['page-status-captiveportal-vouchers']['match'][] = "status_captiveportal_vouchers.php*";

$priv_list['page-status-dhcpleases'] = array();
$priv_list['page-status-dhcpleases']['name'] = "WebCfg - Status: DHCP leases page";
$priv_list['page-status-dhcpleases']['descr'] = "Allow access to the 'Status: DHCP leases' page.";
$priv_list['page-status-dhcpleases']['match'] = array();
$priv_list['page-status-dhcpleases']['match'][] = "status_dhcp_leases.php*";

$priv_list['page-status-filterreloadstatus'] = array();
$priv_list['page-status-filterreloadstatus']['name'] = "WebCfg - Status: Filter Reload Status page";
$priv_list['page-status-filterreloadstatus']['descr'] = "Allow access to the 'Status: Filter Reload Status' page.";
$priv_list['page-status-filterreloadstatus']['match'] = array();
$priv_list['page-status-filterreloadstatus']['match'][] = "status_filter_reload.php*";

$priv_list['page-status-gatewaygroups'] = array();
$priv_list['page-status-gatewaygroups']['name'] = "WebCfg - Status: Gateway Groups page";
$priv_list['page-status-gatewaygroups']['descr'] = "Allow access to the 'Status: Gateway Groups' page.";
$priv_list['page-status-gatewaygroups']['match'] = array();
$priv_list['page-status-gatewaygroups']['match'][] = "status_gateway_groups.php*";

$priv_list['page-status-gateways'] = array();
$priv_list['page-status-gateways']['name'] = "WebCfg - Status: Gateways page";
$priv_list['page-status-gateways']['descr'] = "Allow access to the 'Status: Gateways' page.";
$priv_list['page-status-gateways']['match'] = array();
$priv_list['page-status-gateways']['match'][] = "status_gateways.php*";

$priv_list['page-status-trafficgraph'] = array();
$priv_list['page-status-trafficgraph']['name'] = "WebCfg - Status: Traffic Graph page";
$priv_list['page-status-trafficgraph']['descr'] = "Allow access to the 'Status: Traffic Graph' page.";
$priv_list['page-status-trafficgraph']['match'] = array();
$priv_list['page-status-trafficgraph']['match'][] = "status_graph.php*";
$priv_list['page-status-trafficgraph']['match'][] = "bandwidth_by_ip.php*";

$priv_list['page-status-cpuload'] = array();
$priv_list['page-status-cpuload']['name'] = "WebCfg - Status: CPU load page";
$priv_list['page-status-cpuload']['descr'] = "Allow access to the 'Status: CPU load' page.";
$priv_list['page-status-cpuload']['match'] = array();
$priv_list['page-status-cpuload']['match'][] = "status_graph_cpu.php*";

$priv_list['page-status-interfaces'] = array();
$priv_list['page-status-interfaces']['name'] = "WebCfg - Status: Interfaces page";
$priv_list['page-status-interfaces']['descr'] = "Allow access to the 'Status: Interfaces' page.";
$priv_list['page-status-interfaces']['match'] = array();
$priv_list['page-status-interfaces']['match'][] = "status_interfaces.php*";

$priv_list['page-status-loadbalancer-pool'] = array();
$priv_list['page-status-loadbalancer-pool']['name'] = "WebCfg - Status: Load Balancer: Pool page";
$priv_list['page-status-loadbalancer-pool']['descr'] = "Allow access to the 'Status: Load Balancer: Pool' page.";
$priv_list['page-status-loadbalancer-pool']['match'] = array();
$priv_list['page-status-loadbalancer-pool']['match'][] = "status_lb_pool.php*";

$priv_list['page-status-loadbalancer-virtualserver'] = array();
$priv_list['page-status-loadbalancer-virtualserver']['name'] = "WebCfg - Status: Load Balancer: Virtual Server page";
$priv_list['page-status-loadbalancer-virtualserver']['descr'] = "Allow access to the 'Status: Load Balancer: Virtual Server' page.";
$priv_list['page-status-loadbalancer-virtualserver']['match'] = array();
$priv_list['page-status-loadbalancer-virtualserver']['match'][] = "status_lb_vs.php*";

$priv_list['page-status-openvpn'] = array();
$priv_list['page-status-openvpn']['name'] = "WebCfg - Status: OpenVPN page";
$priv_list['page-status-openvpn']['descr'] = "Allow access to the 'Status: OpenVPN' page.";
$priv_list['page-status-openvpn']['match'] = array();
$priv_list['page-status-openvpn']['match'][] = "status_openvpn.php*";

$priv_list['page-status-trafficshaper-queues'] = array();
$priv_list['page-status-trafficshaper-queues']['name'] = "WebCfg - Status: Traffic shaper: Queues page";
$priv_list['page-status-trafficshaper-queues']['descr'] = "Allow access to the 'Status: Traffic shaper: Queues' page.";
$priv_list['page-status-trafficshaper-queues']['match'] = array();
$priv_list['page-status-trafficshaper-queues']['match'][] = "status_queues.php*";

$priv_list['page-status-rrdgraphs'] = array();
$priv_list['page-status-rrdgraphs']['name'] = "WebCfg - Status: RRD Graphs page";
$priv_list['page-status-rrdgraphs']['descr'] = "Allow access to the 'Status: RRD Graphs' page.";
$priv_list['page-status-rrdgraphs']['match'] = array();
$priv_list['page-status-rrdgraphs']['match'][] = "status_rrd_graph.php*";
$priv_list['page-status-rrdgraphs']['match'][] = "status_rrd_graph_img.php*";

$priv_list['page-status-rrdgraph-settings'] = array();
$priv_list['page-status-rrdgraph-settings']['name'] = "WebCfg - Status: RRD Graphs settings page";
$priv_list['page-status-rrdgraph-settings']['descr'] = "Allow access to the 'Status: RRD Graphs: settings' page.";
$priv_list['page-status-rrdgraph-settings']['match'] = array();
$priv_list['page-status-rrdgraph-settings']['match'][] = "status_rrd_graph_settings.php*";

$priv_list['page-status-services'] = array();
$priv_list['page-status-services']['name'] = "WebCfg - Status: Services page";
$priv_list['page-status-services']['descr'] = "Allow access to the 'Status: Services' page.";
$priv_list['page-status-services']['match'] = array();
$priv_list['page-status-services']['match'][] = "status_services.php*";

$priv_list['page-status-upnpstatus'] = array();
$priv_list['page-status-upnpstatus']['name'] = "WebCfg - Status: UPnP Status page";
$priv_list['page-status-upnpstatus']['descr'] = "Allow access to the 'Status: UPnP Status' page.";
$priv_list['page-status-upnpstatus']['match'] = array();
$priv_list['page-status-upnpstatus']['match'][] = "status_upnp.php*";

$priv_list['page-diagnostics-wirelessstatus'] = array();
$priv_list['page-diagnostics-wirelessstatus']['name'] = "WebCfg - Status: Wireless page";
$priv_list['page-diagnostics-wirelessstatus']['descr'] = "Allow access to the 'Status: Wireless' page.";
$priv_list['page-diagnostics-wirelessstatus']['match'] = array();
$priv_list['page-diagnostics-wirelessstatus']['match'][] = "status_wireless.php*";

$priv_list['page-system-generalsetup'] = array();
$priv_list['page-system-generalsetup']['name'] = "WebCfg - System: General Setup page";
$priv_list['page-system-generalsetup']['descr'] = "Allow access to the 'System: General Setup' page.";
$priv_list['page-system-generalsetup']['match'] = array();
$priv_list['page-system-generalsetup']['match'][] = "system.php*";

$priv_list['page-system-advanced-admin'] = array();
$priv_list['page-system-advanced-admin']['name'] = "WebCfg - System: Advanced: Admin Access Page";
$priv_list['page-system-advanced-admin']['descr'] = "Allow access to the 'System: Advanced: Admin Access' page.";
$priv_list['page-system-advanced-admin']['match'] = array();
$priv_list['page-system-advanced-admin']['match'][] = "system_advanced_admin.php*";

$priv_list['page-system-advanced-firewall'] = array();
$priv_list['page-system-advanced-firewall']['name'] = "WebCfg - System: Advanced: Firewall and NAT page";
$priv_list['page-system-advanced-firewall']['descr'] = "Allow access to the 'System: Advanced: Firewall and NAT' page.";
$priv_list['page-system-advanced-firewall']['match'] = array();
$priv_list['page-system-advanced-firewall']['match'][] = "system_advanced.php*";

$priv_list['page-system-advanced-misc'] = array();
$priv_list['page-system-advanced-misc']['name'] = "WebCfg - System: Advanced: Miscellaneous page";
$priv_list['page-system-advanced-misc']['descr'] = "Allow access to the 'System: Advanced: Miscellaneous' page.";
$priv_list['page-system-advanced-misc']['match'] = array();
$priv_list['page-system-advanced-misc']['match'][] = "system_advanced.php*";

$priv_list['page-system-advanced-network'] = array();
$priv_list['page-system-advanced-network']['name'] = "WebCfg - System: Advanced: Network page";
$priv_list['page-system-advanced-network']['descr'] = "Allow access to the 'System: Advanced: Networking' page.";
$priv_list['page-system-advanced-network']['match'] = array();
$priv_list['page-system-advanced-network']['match'][] = "system_advanced-network.php*";

$priv_list['page-system-advanced-notifications'] = array();
$priv_list['page-system-advanced-notifications']['name'] = "WebCfg - System: Advanced: Tunables page";
$priv_list['page-system-advanced-notifications']['descr'] = "Allow access to the 'System: Advanced: Tunables' page.";
$priv_list['page-system-advanced-notifications']['match'] = array();
$priv_list['page-system-advanced-notifications']['match'][] = "system_advanced-sysctrl.php*";

$priv_list['page-system-advanced-sysctl'] = array();
$priv_list['page-system-advanced-sysctl']['name'] = "WebCfg - System: Advanced: Tunables page";
$priv_list['page-system-advanced-sysctl']['descr'] = "Allow access to the 'System: Advanced: Tunables' page.";
$priv_list['page-system-advanced-sysctl']['match'] = array();
$priv_list['page-system-advanced-sysctl']['match'][] = "system_advanced-sysctl.php*";

$priv_list['page-system-authservers'] = array();
$priv_list['page-system-authservers']['name'] = "WebCfg - System: Authentication Servers";
$priv_list['page-system-authservers']['descr'] = "Allow access to the 'System: Authentication Servers' page.";
$priv_list['page-system-authservers']['match'] = array();
$priv_list['page-system-authservers']['match'][] = "system_authservers.php*";

$priv_list['page-system-camanager'] = array();
$priv_list['page-system-camanager']['name'] = "WebCfg - System: CA Manager";
$priv_list['page-system-camanager']['descr'] = "Allow access to the 'System: CA Manager' page.";
$priv_list['page-system-camanager']['match'] = array();
$priv_list['page-system-camanager']['match'][] = "system_camanager.php*";

$priv_list['page-system-certmanager'] = array();
$priv_list['page-system-certmanager']['name'] = "WebCfg - System: Certificate Manager";
$priv_list['page-system-certmanager']['descr'] = "Allow access to the 'System: Certificate Manager' page.";
$priv_list['page-system-certmanager']['match'] = array();
$priv_list['page-system-certmanager']['match'][] = "system_certmanager.php*";

$priv_list['page-system-crlmanager'] = array();
$priv_list['page-system-crlmanager']['name'] = "WebCfg - System: CRL Manager";
$priv_list['page-system-crlmanager']['descr'] = "Allow access to the 'System: CRL Manager' page.";
$priv_list['page-system-crlmanager']['match'] = array();
$priv_list['page-system-crlmanager']['match'][] = "system_crlmanager.php*";

$priv_list['page-system-firmware-manualupdate'] = array();
$priv_list['page-system-firmware-manualupdate']['name'] = "WebCfg - System: Firmware: Manual Update page";
$priv_list['page-system-firmware-manualupdate']['descr'] = "Allow access to the 'System: Firmware: Manual Update' page.";
$priv_list['page-system-firmware-manualupdate']['match'] = array();
$priv_list['page-system-firmware-manualupdate']['match'][] = "system_firmware.php*";

$priv_list['page-system-firmware-checkforupdate'] = array();
$priv_list['page-system-firmware-checkforupdate']['name'] = "WebCfg - System: Firmware: Check For Update page";
$priv_list['page-system-firmware-checkforupdate']['descr'] = "Allow access to the 'System: Firmware: Check For Update' page.";
$priv_list['page-system-firmware-checkforupdate']['match'] = array();
$priv_list['page-system-firmware-checkforupdate']['match'][] = "system_firmware_auto.php*";

$priv_list['page-system-firmware-autoupdate'] = array();
$priv_list['page-system-firmware-autoupdate']['name'] = "WebCfg - System: Firmware: Auto Update page";
$priv_list['page-system-firmware-autoupdate']['descr'] = "Allow access to the 'System: Firmware: Auto Update' page.";
$priv_list['page-system-firmware-autoupdate']['match'] = array();
$priv_list['page-system-firmware-autoupdate']['match'][] = "system_firmware_check.php*";

$priv_list['page-system-firmware-settings'] = array();
$priv_list['page-system-firmware-settings']['name'] = "WebCfg - System: Firmware: Settings page";
$priv_list['page-system-firmware-settings']['descr'] = "Allow access to the 'System: Firmware: Settings' page.";
$priv_list['page-system-firmware-settings']['match'] = array();
$priv_list['page-system-firmware-settings']['match'][] = "system_firmware_settings.php*";

$priv_list['page-system-gatewaygroups'] = array();
$priv_list['page-system-gatewaygroups']['name'] = "WebCfg - System: Gateway Groups page";
$priv_list['page-system-gatewaygroups']['descr'] = "Allow access to the 'System: Gateway Groups' page.";
$priv_list['page-system-gatewaygroups']['match'] = array();
$priv_list['page-system-gatewaygroups']['match'][] = "system_gateway_groups.php*";

$priv_list['page-system-gateways-editgatewaygroups'] = array();
$priv_list['page-system-gateways-editgatewaygroups']['name'] = "WebCfg - System: Gateways: Edit Gateway Groups page";
$priv_list['page-system-gateways-editgatewaygroups']['descr'] = "Allow access to the 'System: Gateways: Edit Gateway Groups' page.";
$priv_list['page-system-gateways-editgatewaygroups']['match'] = array();
$priv_list['page-system-gateways-editgatewaygroups']['match'][] = "system_gateway_groups_edit.php*";

$priv_list['page-system-gateways'] = array();
$priv_list['page-system-gateways']['name'] = "WebCfg - System: Gateways page";
$priv_list['page-system-gateways']['descr'] = "Allow access to the 'System: Gateways' page.";
$priv_list['page-system-gateways']['match'] = array();
$priv_list['page-system-gateways']['match'][] = "system_gateways.php*";

$priv_list['page-system-gateways-editgateway'] = array();
$priv_list['page-system-gateways-editgateway']['name'] = "WebCfg - System: Gateways: Edit Gateway page";
$priv_list['page-system-gateways-editgateway']['descr'] = "Allow access to the 'System: Gateways: Edit Gateway' page.";
$priv_list['page-system-gateways-editgateway']['match'] = array();
$priv_list['page-system-gateways-editgateway']['match'][] = "system_gateways_edit.php*";

$priv_list['page-system-groupmanager'] = array();
$priv_list['page-system-groupmanager']['name'] = "WebCfg - System: Group manager page";
$priv_list['page-system-groupmanager']['descr'] = "Allow access to the 'System: Group manager' page.";
$priv_list['page-system-groupmanager']['match'] = array();
$priv_list['page-system-groupmanager']['match'][] = "system_groupmanager.php*";

$priv_list['page-system-groupmanager-addprivs'] = array();
$priv_list['page-system-groupmanager-addprivs']['name'] = "WebCfg - System: Group Manager: Add Privileges page";
$priv_list['page-system-groupmanager-addprivs']['descr'] = "Allow access to the 'System: Group Manager: Add Privileges' page.";
$priv_list['page-system-groupmanager-addprivs']['match'] = array();
$priv_list['page-system-groupmanager-addprivs']['match'][] = "system_groupmanager_addprivs.php*";

$priv_list['page-system-staticroutes'] = array();
$priv_list['page-system-staticroutes']['name'] = "WebCfg - System: Static Routes page";
$priv_list['page-system-staticroutes']['descr'] = "Allow access to the 'System: Static Routes' page.";
$priv_list['page-system-staticroutes']['match'] = array();
$priv_list['page-system-staticroutes']['match'][] = "system_routes.php*";

$priv_list['page-system-staticroutes-editroute'] = array();
$priv_list['page-system-staticroutes-editroute']['name'] = "WebCfg - System: Static Routes: Edit route page";
$priv_list['page-system-staticroutes-editroute']['descr'] = "Allow access to the 'System: Static Routes: Edit route' page.";
$priv_list['page-system-staticroutes-editroute']['match'] = array();
$priv_list['page-system-staticroutes-editroute']['match'][] = "system_routes_edit.php*";

$priv_list['page-system-usermanager'] = array();
$priv_list['page-system-usermanager']['name'] = "WebCfg - System: User Manager page";
$priv_list['page-system-usermanager']['descr'] = "Allow access to the 'System: User Manager' page.";
$priv_list['page-system-usermanager']['match'] = array();
$priv_list['page-system-usermanager']['match'][] = "system_usermanager.php*";

$priv_list['page-system-usermanager-addprivs'] = array();
$priv_list['page-system-usermanager-addprivs']['name'] = "WebCfg - System: User Manager: Add Privileges page";
$priv_list['page-system-usermanager-addprivs']['descr'] = "Allow access to the 'System: User Manager: Add Privileges' page.";
$priv_list['page-system-usermanager-addprivs']['match'] = array();
$priv_list['page-system-usermanager-addprivs']['match'][] = "system_usermanager_addprivs.php*";

$priv_list['page-system-usermanager-passwordmg'] = array();
$priv_list['page-system-usermanager-passwordmg']['name'] = "WebCfg - System: User Password Manager page";
$priv_list['page-system-usermanager-passwordmg']['descr'] = "Allow access to the 'System: User Password Manager' page.";
$priv_list['page-system-usermanager-passwordmg']['match'] = array();
$priv_list['page-system-usermanager-passwordmg']['match'][] = "system_usermanager_passwordmg.php*";

$priv_list['page-system-usermanager-settings'] = array();
$priv_list['page-system-usermanager-settings']['name'] = "WebCfg - System: User manager: settings page";
$priv_list['page-system-usermanager-settings']['descr'] = "Allow access to the 'System: User manager: settings' page.";
$priv_list['page-system-usermanager-settings']['match'] = array();
$priv_list['page-system-usermanager-settings']['match'][] = "system_usermanager_settings.php*";

$priv_list['page-system-usermanager-settings-testldap'] = array();
$priv_list['page-system-usermanager-settings-testldap']['name'] = "WebCfg - System: User Manager: Settings: Test LDAP page";
$priv_list['page-system-usermanager-settings-testldap']['descr'] = "Allow access to the 'System: User Manager: Settings: Test LDAP' page.";
$priv_list['page-system-usermanager-settings-testldap']['match'] = array();
$priv_list['page-system-usermanager-settings-testldap']['match'][] = "system_usermanager_settings_test.php*";

$priv_list['page-upload_progress'] = array();
$priv_list['page-upload_progress']['name'] = "WebCfg - System: Firmware: Manual Update page (progress bar)";
$priv_list['page-upload_progress']['descr'] = "Allow access to the 'System: Firmware: Manual Update: Progress bar' page.";
$priv_list['page-upload_progress']['match'] = array();
$priv_list['page-upload_progress']['match'][] = "upload_progress*";

$priv_list['page-hidden-uploadconfiguration'] = array();
$priv_list['page-hidden-uploadconfiguration']['name'] = "WebCfg - Hidden: Upload Configuration page";
$priv_list['page-hidden-uploadconfiguration']['descr'] = "Allow access to the 'Hidden: Upload Configuration' page.";
$priv_list['page-hidden-uploadconfiguration']['match'] = array();
$priv_list['page-hidden-uploadconfiguration']['match'][] = "uploadconfig.php*";

$priv_list['page-vpn-ipsec'] = array();
$priv_list['page-vpn-ipsec']['name'] = "WebCfg - VPN: IPsec page";
$priv_list['page-vpn-ipsec']['descr'] = "Allow access to the 'VPN: IPsec' page.";
$priv_list['page-vpn-ipsec']['match'] = array();
$priv_list['page-vpn-ipsec']['match'][] = "vpn_ipsec.php*";

$priv_list['page-vpn-ipsec-listkeys'] = array();
$priv_list['page-vpn-ipsec-listkeys']['name'] = "WebCfg - VPN: IPsec: Pre-Shared Keys List";
$priv_list['page-vpn-ipsec-listkeys']['descr'] = "Allow access to the 'VPN: IPsec: Pre-Shared Keys List' page.";
$priv_list['page-vpn-ipsec-listkeys']['match'] = array();
$priv_list['page-vpn-ipsec-listkeys']['match'][] = "vpn_ipsec_keys.php*";

$priv_list['page-vpn-ipsec-editkeys'] = array();
$priv_list['page-vpn-ipsec-editkeys']['name'] = "WebCfg - VPN: IPsec: Edit Pre-Shared Keys";
$priv_list['page-vpn-ipsec-editkeys']['descr'] = "Allow access to the 'VPN: IPsec: Edit Pre-Shared Keys' page.";
$priv_list['page-vpn-ipsec-editkeys']['match'] = array();
$priv_list['page-vpn-ipsec-editkeys']['match'][] = "vpn_ipsec_keys_edit.php*";

$priv_list['page-vpn-ipsec-mobile'] = array();
$priv_list['page-vpn-ipsec-mobile']['name'] = "WebCfg - VPN: IPsec: Mobile page";
$priv_list['page-vpn-ipsec-mobile']['descr'] = "Allow access to the 'VPN: IPsec: Mobile' page.";
$priv_list['page-vpn-ipsec-mobile']['match'] = array();
$priv_list['page-vpn-ipsec-mobile']['match'][] = "vpn_ipsec_mobile.php*";

$priv_list['page-vpn-ipsec-editphase1'] = array();
$priv_list['page-vpn-ipsec-editphase1']['name'] = "WebCfg - VPN: IPsec: Edit Phase 1 page";
$priv_list['page-vpn-ipsec-editphase1']['descr'] = "Allow access to the 'VPN: IPsec: Edit Phase 1' page.";
$priv_list['page-vpn-ipsec-editphase1']['match'] = array();
$priv_list['page-vpn-ipsec-editphase1']['match'][] = "vpn_ipsec_phase1.php*";

$priv_list['page-vpn-ipsec-editphase2'] = array();
$priv_list['page-vpn-ipsec-editphase2']['name'] = "WebCfg - VPN: IPsec: Edit Phase 2 page";
$priv_list['page-vpn-ipsec-editphase2']['descr'] = "Allow access to the 'VPN: IPsec: Edit Phase 2' page.";
$priv_list['page-vpn-ipsec-editphase2']['match'] = array();
$priv_list['page-vpn-ipsec-editphase2']['match'][] = "vpn_ipsec_phase2.php*";

$priv_list['page-vpn-vpnl2tp'] = array();
$priv_list['page-vpn-vpnl2tp']['name'] = "WebCfg - VPN: VPN L2TP page";
$priv_list['page-vpn-vpnl2tp']['descr'] = "Allow access to the 'VPN: VPN L2TP' page.";
$priv_list['page-vpn-vpnl2tp']['match'] = array();
$priv_list['page-vpn-vpnl2tp']['match'][] = "vpn_l2tp.php*";

$priv_list['page-vpn-vpnl2tp-users'] = array();
$priv_list['page-vpn-vpnl2tp-users']['name'] = "WebCfg - VPN: VPN L2TP : Users page";
$priv_list['page-vpn-vpnl2tp-users']['descr'] = "Allow access to the 'VPN: VPN L2TP : Users' page.";
$priv_list['page-vpn-vpnl2tp-users']['match'] = array();
$priv_list['page-vpn-vpnl2tp-users']['match'][] = "vpn_l2tp_users.php*";

$priv_list['page-vpn-vpnl2tp-users-edit'] = array();
$priv_list['page-vpn-vpnl2tp-users-edit']['name'] = "WebCfg - VPN: VPN L2TP : Users : Edit page";
$priv_list['page-vpn-vpnl2tp-users-edit']['descr'] = "Allow access to the 'VPN: VPN L2TP : Users : Edit' page.";
$priv_list['page-vpn-vpnl2tp-users-edit']['match'] = array();
$priv_list['page-vpn-vpnl2tp-users-edit']['match'][] = "vpn_l2tp_users_edit.php*";

$priv_list['page-openvpn-client'] = array();
$priv_list['page-openvpn-client']['name'] = "WebCfg - OpenVPN: Client page";
$priv_list['page-openvpn-client']['descr'] = "Allow access to the 'OpenVPN: Client' page.";
$priv_list['page-openvpn-client']['match'] = array();
$priv_list['page-openvpn-client']['match'][] = "vpn_openvpn_client.php*";

$priv_list['page-openvpn-csc'] = array();
$priv_list['page-openvpn-csc']['name'] = "WebCfg - OpenVPN: Client Specific Override page";
$priv_list['page-openvpn-csc']['descr'] = "Allow access to the 'OpenVPN: Client Specific Override' page.";
$priv_list['page-openvpn-csc']['match'] = array();
$priv_list['page-openvpn-csc']['match'][] = "vpn_openvpn_csc.php*";

$priv_list['page-openvpn-server'] = array();
$priv_list['page-openvpn-server']['name'] = "WebCfg - OpenVPN: Server page";
$priv_list['page-openvpn-server']['descr'] = "Allow access to the 'OpenVPN: Server' page.";
$priv_list['page-openvpn-server']['match'] = array();
$priv_list['page-openvpn-server']['match'][] = "vpn_openvpn_server.php*";

$priv_list['page-services-pppoeserver'] = array();
$priv_list['page-services-pppoeserver']['name'] = "WebCfg - Services: PPPoE Server page";
$priv_list['page-services-pppoeserver']['descr'] = "Allow access to the 'Services: PPPoE Server' page.";
$priv_list['page-services-pppoeserver']['match'] = array();
$priv_list['page-services-pppoeserver']['match'][] = "vpn_pppoe.php*";

$priv_list['page-services-pppoeserver-eidt'] = array();
$priv_list['page-services-pppoeserver-eidt']['name'] = "WebCfg - Services: PPPoE Server: Edit page";
$priv_list['page-services-pppoeserver-eidt']['descr'] = "Allow access to the 'Services: PPPoE Server: Edit' page.";
$priv_list['page-services-pppoeserver-eidt']['match'] = array();
$priv_list['page-services-pppoeserver-eidt']['match'][] = "vpn_pppoe_edit.php*";

$priv_list['page-vpn-vpnpptp'] = array();
$priv_list['page-vpn-vpnpptp']['name'] = "WebCfg - VPN: VPN PPTP page";
$priv_list['page-vpn-vpnpptp']['descr'] = "Allow access to the 'VPN: VPN PPTP' page.";
$priv_list['page-vpn-vpnpptp']['match'] = array();
$priv_list['page-vpn-vpnpptp']['match'][] = "vpn_pptp.php*";

$priv_list['page-vpn-vpnpptp-users'] = array();
$priv_list['page-vpn-vpnpptp-users']['name'] = "WebCfg - VPN: VPN PPTP: Users page";
$priv_list['page-vpn-vpnpptp-users']['descr'] = "Allow access to the 'VPN: VPN PPTP: Users' page.";
$priv_list['page-vpn-vpnpptp-users']['match'] = array();
$priv_list['page-vpn-vpnpptp-users']['match'][] = "vpn_pptp_users.php*";

$priv_list['page-vpn-vpnpptp-user-edit'] = array();
$priv_list['page-vpn-vpnpptp-user-edit']['name'] = "WebCfg - VPN: VPN PPTP: User: Edit page";
$priv_list['page-vpn-vpnpptp-user-edit']['descr'] = "Allow access to the 'VPN: VPN PPTP: User: Edit' page.";
$priv_list['page-vpn-vpnpptp-user-edit']['match'] = array();
$priv_list['page-vpn-vpnpptp-user-edit']['match'][] = "vpn_pptp_users_edit.php*";

$priv_list['page-pfsensewizardsubsystem'] = array();
$priv_list['page-pfsensewizardsubsystem']['name'] = "WebCfg - pfSense wizard subsystem page";
$priv_list['page-pfsensewizardsubsystem']['descr'] = "Allow access to the 'pfSense wizard subsystem' page.";
$priv_list['page-pfsensewizardsubsystem']['match'] = array();
$priv_list['page-pfsensewizardsubsystem']['match'][] = "wizard.php*";

$priv_list['page-xmlrpclibrary'] = array();
$priv_list['page-xmlrpclibrary']['name'] = "WebCfg - XMLRPC Library page";
$priv_list['page-xmlrpclibrary']['descr'] = "Allow access to the 'XMLRPC Library' page.";
$priv_list['page-xmlrpclibrary']['match'] = array();
$priv_list['page-xmlrpclibrary']['match'][] = "xmlrpc.php*";


$priv_rmvd = array();

?>
