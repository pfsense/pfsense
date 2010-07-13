<?php

global $priv_list;

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