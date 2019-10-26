<?php

global $priv_list;

$priv_list['system-xmlrpc-ha-sync'] = array();
$priv_list['system-xmlrpc-ha-sync']['name'] = gettext("System - HA node sync");
$priv_list['system-xmlrpc-ha-sync']['descr'] = gettext("Allow access to authenticate this user for HA sync via XMLRPC");
$priv_list['system-xmlrpc-ha-sync']['warn'] = "standard-warning-root";

$priv_list['user-services-captiveportal-login'] = array();
$priv_list['user-services-captiveportal-login']['name']  = gettext("User - Services: Captive Portal login");
$priv_list['user-services-captiveportal-login']['descr'] = gettext("Indicates whether the user is able to login on the captive portal.");

$priv_list['page-help-all'] = array();
$priv_list['page-help-all']['name'] = gettext("WebCfg - Help pages");
$priv_list['page-help-all']['descr'] = gettext("Show all items on help menu and allow access to Help shortcut links");
$priv_list['page-help-all']['match'] = array();
$priv_list['page-help-all']['match'][] = "help.php*";

$priv_list['page-dashboard-all'] = array();
$priv_list['page-dashboard-all']['name'] = gettext("WebCfg - Dashboard (all)");
$priv_list['page-dashboard-all']['descr'] = gettext("Allow access to all pages required for the dashboard.");
$priv_list['page-dashboard-all']['match'] = array();
$priv_list['page-dashboard-all']['match'][] = "index.php*";
$priv_list['page-dashboard-all']['match'][] = "widgets/widgets/*.widget.php*";
$priv_list['page-dashboard-all']['match'][] = "graph.php*";
$priv_list['page-dashboard-all']['match'][] = "graph_cpu.php*";
$priv_list['page-dashboard-all']['match'][] = "getstats.php*";
$priv_list['page-dashboard-all']['match'][] = "ifstats.php*";
$priv_list['page-dashboard-all']['match'][] = "status_logs_filter_dynamic.php*";

$priv_list['page-dashboard-widgets'] = array();
$priv_list['page-dashboard-widgets']['name'] = gettext("WebCfg - Dashboard widgets (direct access).");
$priv_list['page-dashboard-widgets']['descr'] = gettext("Allow direct access to all Dashboard widget pages, required for some widgets using AJAX.");
$priv_list['page-dashboard-widgets']['match'] = array();
$priv_list['page-dashboard-widgets']['match'][] = "widgets/widgets/*.widget.php*";

$priv_list['user-config-readonly'] = array();
$priv_list['user-config-readonly']['name']  = gettext("User - Config: Deny Config Write");
$priv_list['user-config-readonly']['descr'] = gettext("If present, ignores requests from this user to write config.xml.");

$priv_list['user-view-notices'] = array();
$priv_list['user-view-notices']['name']  = gettext("User - Notices: View");
$priv_list['user-view-notices']['descr'] = gettext("This user can view system notices.");

$priv_list['user-view-clear-notices'] = array();
$priv_list['user-view-clear-notices']['name']  = gettext("User - Notices: View and Clear");
$priv_list['user-view-clear-notices']['descr'] = gettext("This user can view and clear system notices.");

$priv_list['user-shell-access'] = array();
$priv_list['user-shell-access']['name']  = gettext("User - System: Shell account access");
$priv_list['user-shell-access']['descr'] = gettext("Indicates whether the user is able to login for example via SSH.");
$priv_list['user-shell-access']['warn'] = "standard-warning-root";

$priv_list['user-copy-files'] = array();
$priv_list['user-copy-files']['name']  = gettext("User - System: Copy files (scp)");
$priv_list['user-copy-files']['descr'] = gettext("Indicates whether this user is allowed to copy files onto the {$g['product_name']} appliance via SCP/SFTP.");
$priv_list['user-copy-files']['warn'] = "standard-warning-root";

$priv_list['user-copy-files-chroot'] = array();
$priv_list['user-copy-files-chroot']['name']  = gettext("User - System: Copy files to home directory (chrooted scp)");
$priv_list['user-copy-files-chroot']['descr'] = gettext("Indicates whether this user is allowed to copy files to the home directory via SCP/SFTP.".
										"Note: User - System - Copy files (scp) conflicts with this privilege.".
						       				"Warning: Manual chroot setup required, see /usr/local/etc/rc.d/scponlyc.");

$priv_list['user-ssh-tunnel'] = array();
$priv_list['user-ssh-tunnel']['name']  = gettext("User - System: SSH tunneling");
$priv_list['user-ssh-tunnel']['descr'] = gettext("Indicates whether the user is able to login for ".
										"tunneling via SSH when they have no shell access. ".
										"Note: User - System - Copy files (scp) and ".
						 				"System: Copy files to home directory (chrooted scp) ".
										"conflict with this privilege.");

$priv_list['user-ipsec-xauth-dialin'] = array();
$priv_list['user-ipsec-xauth-dialin']['name'] = gettext("User - VPN: IPsec xauth Dialin");
$priv_list['user-ipsec-xauth-dialin']['descr'] = gettext("Indicates whether the user is allowed to dial in via IPsec xauth ".
										"(Note: Does not allow shell access, but may allow ".
										"the user to create SSH tunnels)");

$priv_list['user-l2tp-dialin'] = array();
$priv_list['user-l2tp-dialin']['name'] = gettext("User - VPN: L2TP Dialin");
$priv_list['user-l2tp-dialin']['descr'] = gettext("Indicates whether the user is allowed to dial in via L2TP");

$priv_list['user-pppoe-dialin'] = array();
$priv_list['user-pppoe-dialin']['name'] = gettext("User - VPN: PPPOE Dialin");
$priv_list['user-pppoe-dialin']['descr'] = gettext("Indicates whether the user is allowed to dial in via PPPOE");

$priv_list['page-status-systemlogs-portalauth'] = array();
$priv_list['page-status-systemlogs-portalauth']['name'] = gettext("WebCfg - Status: System Logs: Portal Auth");
$priv_list['page-status-systemlogs-portalauth']['descr'] = gettext("Allow access to the 'Status: System Logs: Portal Auth' page.");
$priv_list['page-status-systemlogs-portalauth']['match'] = array();
$priv_list['page-status-systemlogs-portalauth']['match'][] = "status_logs.php?logfile=portalauth";

$priv_list['page-diagnostics-logs-dhcp'] = array();
$priv_list['page-diagnostics-logs-dhcp']['name'] = gettext("WebCfg - Status: Logs: DHCP");
$priv_list['page-diagnostics-logs-dhcp']['descr'] = gettext("Allow access to the 'Status: Logs: DHCP' page.");
$priv_list['page-diagnostics-logs-dhcp']['match'] = array();
$priv_list['page-diagnostics-logs-dhcp']['match'][] = "status_logs.php?logfile=dhcpd";

$priv_list['page-diagnostics-logs-gateways'] = array();
$priv_list['page-diagnostics-logs-gateways']['name'] = gettext("WebCfg - Status: Logs: Gateways");
$priv_list['page-diagnostics-logs-gateways']['descr'] = gettext("Allow access to the 'Status: Logs: System: Gateways' page.");
$priv_list['page-diagnostics-logs-gateways']['match'] = array();
$priv_list['page-diagnostics-logs-gateways']['match'][] = "status_logs.php?logfile=gateways";

$priv_list['page-diagnostics-logs-resolver'] = array();
$priv_list['page-diagnostics-logs-resolver']['name'] = gettext("WebCfg - Status: Logs: Resolver");
$priv_list['page-diagnostics-logs-resolver']['descr'] = gettext("Allow access to the 'Status: Logs: System: Resolver' page.");
$priv_list['page-diagnostics-logs-resolver']['match'] = array();
$priv_list['page-diagnostics-logs-resolver']['match'][] = "status_logs.php?logfile=resolver";

$priv_list['page-status-systemlogs-ipsecvpn'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['name'] = gettext("WebCfg - Status: System Logs: IPsec VPN");
$priv_list['page-status-systemlogs-ipsecvpn']['descr'] = gettext("Allow access to the 'Status: System Logs: IPsec VPN' page.");
$priv_list['page-status-systemlogs-ipsecvpn']['match'] = array();
$priv_list['page-status-systemlogs-ipsecvpn']['match'][] = "status_logs.php?logfile=ipsec";

$priv_list['page-status-systemlogs-ntpd'] = array();
$priv_list['page-status-systemlogs-ntpd']['name'] = gettext("WebCfg - Status: System Logs: NTP");
$priv_list['page-status-systemlogs-ntpd']['descr'] = gettext("Allow access to the 'Status: System Logs: NTP' page.");
$priv_list['page-status-systemlogs-ntpd']['match'] = array();
$priv_list['page-status-systemlogs-ntpd']['match'][] = "status_logs.php?logfile=ntpd";

$priv_list['page-status-systemlogs-openvpn'] = array();
$priv_list['page-status-systemlogs-openvpn']['name'] = gettext("WebCfg - Status: System Logs: OpenVPN");
$priv_list['page-status-systemlogs-openvpn']['descr'] = gettext("Allow access to the 'Status: System Logs: OpenVPN' page.");
$priv_list['page-status-systemlogs-openvpn']['match'] = array();
$priv_list['page-status-systemlogs-openvpn']['match'][] = "status_logs.php?logfile=openvpn";

$priv_list['page-status-systemlogs-ppp'] = array();
$priv_list['page-status-systemlogs-ppp']['name'] = gettext("WebCfg - Status: System Logs: IPsec VPN");
$priv_list['page-status-systemlogs-ppp']['descr'] = gettext("Allow access to the 'Status: System Logs: IPsec VPN' page.");
$priv_list['page-status-systemlogs-ppp']['match'] = array();
$priv_list['page-status-systemlogs-ppp']['match'][] = "status_logs.php?logfile=ppp";

$priv_list['page-status-systemlogs-routing'] = array();
$priv_list['page-status-systemlogs-routing']['name'] = gettext("WebCfg - Status: System Logs: Routing");
$priv_list['page-status-systemlogs-routing']['descr'] = gettext("Allow access to the 'Status: System Logs: System: Routing' page.");
$priv_list['page-status-systemlogs-routing']['match'] = array();
$priv_list['page-status-systemlogs-routing']['match'][] = "status_logs.php?logfile=routing";

$priv_list['page-status-systemlogs-wireless'] = array();
$priv_list['page-status-systemlogs-wireless']['name'] = gettext("WebCfg - Status: System Logs: Wireless");
$priv_list['page-status-systemlogs-wireless']['descr'] = gettext("Allow access to the 'Status: System Logs: System: Wireless' page.");
$priv_list['page-status-systemlogs-wireless']['match'] = array();
$priv_list['page-status-systemlogs-wireless']['match'][] = "status_logs.php?logfile=wireless";

$priv_list['page-services-upnp'] = array();
$priv_list['page-services-upnp']['name'] = gettext("WebCfg - Services: UPnP");
$priv_list['page-services-upnp']['descr'] = gettext("Allow access to the 'Services: UPnP' page.");
$priv_list['page-services-upnp']['match'] = array();
$priv_list['page-services-upnp']['match'][] = "pkg_edit.php?xml=miniupnpd.xml";

$priv_list['page-openvpn-server-advanced'] = array();
$priv_list['page-openvpn-server-advanced']['name'] = gettext("WebCfg - OpenVPN: Clients Edit Advanced");
$priv_list['page-openvpn-server-advanced']['descr'] = gettext("Allow edit access to the 'OpenVPN: Clients' Advanced settings field.");
$priv_list['page-openvpn-server-advanced']['warn'] = "standard-warning-root";

$priv_list['page-openvpn-csc-advanced'] = array();
$priv_list['page-openvpn-csc-advanced']['name'] = gettext("WebCfg - OpenVPN: Client Specific Override Edit Advanced");
$priv_list['page-openvpn-csc-advanced']['descr'] = gettext("Allow edit access to the 'OpenVPN: Client Specific Override' advanced settings field.");
$priv_list['page-openvpn-csc-advanced']['warn'] = "standard-warning-root";

$priv_list['page-openvpn-client-advanced'] = array();
$priv_list['page-openvpn-client-advanced']['name'] = gettext("WebCfg - OpenVPN: Servers Edit Advanced");
$priv_list['page-openvpn-client-advanced']['descr'] = gettext("Allow edit access to the 'OpenVPN: Servers' Advanced settings field.");
$priv_list['page-openvpn-client-advanced']['warn'] = "standard-warning-root";

?>
