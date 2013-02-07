<?php

global $priv_list;

$priv_list['user-services-captiveportal-login'] = array();
$priv_list['user-services-captiveportal-login']['name']  = gettext("User - Services - Captive portal login");
$priv_list['user-services-captiveportal-login']['descr'] = gettext("Indicates whether the user is able to login on the captive portal.");

$priv_list['page-help-all'] = array();
$priv_list['page-help-all']['name'] = "WebCfg - Help pages";
$priv_list['page-help-all']['descr'] = "Show all items on help menu";
$priv_list['page-help-all']['match'] = array();
$priv_list['page-help-all']['match'][] = "*www.pfsense.org*";

$priv_list['page-dashboard-all'] = array();
$priv_list['page-dashboard-all']['name'] = "WebCfg - Dashboard (all)";
$priv_list['page-dashboard-all']['descr'] = "Allow access to all pages required for the dashboard.";
$priv_list['page-dashboard-all']['match'] = array();
$priv_list['page-dashboard-all']['match'][] = "index.php*";
$priv_list['page-dashboard-all']['match'][] = "*.widget.php*";
$priv_list['page-dashboard-all']['match'][] = "graph.php*";
$priv_list['page-dashboard-all']['match'][] = "graph_cpu.php*";
$priv_list['page-dashboard-all']['match'][] = "getstats.php*";
$priv_list['page-dashboard-all']['match'][] = "ifstats.php*";
$priv_list['page-dashboard-all']['match'][] = "diag_logs_filter_dynamic.php*";

$priv_list['page-dashboard-widgets'] = array();
$priv_list['page-dashboard-widgets']['name'] = "WebCfg - Dashboard widgets (direct access).";
$priv_list['page-dashboard-widgets']['descr'] = "Allow direct access to all Dashboard widget pages, required for some widgets using AJAX.";
$priv_list['page-dashboard-widgets']['match'] = array();
$priv_list['page-dashboard-widgets']['match'][] = "*.widget.php*";

$priv_list['user-config-readonly'] = array();
$priv_list['user-config-readonly']['name']  = "User - Config - Deny Config Write";
$priv_list['user-config-readonly']['descr'] = "If present, ignores requests from this user to write config.xml.";

$priv_list['user-shell-access'] = array();
$priv_list['user-shell-access']['name']  = "User - System - Shell account access";
$priv_list['user-shell-access']['descr'] = "Indicates whether the user is able to login for ".
										   "example via SSH.";

$priv_list['user-copy-files'] = array();
$priv_list['user-copy-files']['name']  = "User - System - Copy files";
$priv_list['user-copy-files']['descr'] = "Indicates whether the user is allowed to copy files ".
										 "onto the {$g['product_name']} appliance via SCP/SFTP. ".
										 "If you are going to use this privilege, you must install ".
										 "scponly on the appliance (Hint: pkg_add -r scponly).";

$priv_list['user-ssh-tunnel'] = array();
$priv_list['user-ssh-tunnel']['name']  = "User - System - SSH tunneling";
$priv_list['user-ssh-tunnel']['descr'] = "Indicates whether the user is able to login for ".
										"tunneling via SSH when they have no shell access. ".
										"Note: User - System - Copy files conflicts with ".
										"this privilege.";

$priv_list['user-ipsec-xauth-dialin'] = array();
$priv_list['user-ipsec-xauth-dialin']['name'] = "User - VPN - IPsec xauth Dialin";
$priv_list['user-ipsec-xauth-dialin']['descr'] = "Indicates whether the user is allowed to dial in via IPsec xauth ".
										"(Note: Does not allow shell access, but may allow ".
										"the user to create ssh tunnels)";

$priv_list['user-l2tp-dialin'] = array();
$priv_list['user-l2tp-dialin']['name'] = "User - VPN - L2TP Dialin";
$priv_list['user-l2tp-dialin']['descr'] = "Indicates whether the user is allowed to dial in via L2TP";

$priv_list['user-pptp-dialin'] = array();
$priv_list['user-pptp-dialin']['name'] = "User - VPN - PPTP Dialin";
$priv_list['user-pptp-dialin']['descr'] = "Indicates whether the user is allowed to dial in via PPTP";

$priv_list['user-pppoe-dialin'] = array();
$priv_list['user-pppoe-dialin']['name'] = "User - VPN - PPPOE Dialin";
$priv_list['user-pppoe-dialin']['descr'] = "Indicates whether the user is allowed to dial in via PPPOE";

?>
