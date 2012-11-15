<?php

/* make sure we are coming from 127.0.0.1 */
$ip = $HTTP_SERVER_VARS['REMOTE_ADDR'];
if($ip <> "127.0.0.1") 
	die(gettext("You are not allowed to access this page."));

/* preload */

$files=array("functions.inc",
	"config.inc",
	"IPv6.inc",
	"itemid.inc",
	"shaper.inc",
	"PEAR.inc",
	"led.inc",
	"array_intersect_key.inc",
	"dyndns.class",
	"meta.inc",
	"smtp.inc",
	"auth.inc",
	"easyrule.inc",
	"notices.inc",
	"system.inc",
	"filter.inc",
	"upgrade_config.inc",
	"openvpn.inc",
	"pfsense-utils.inc",
	"uuid.php",
	"captiveportal.inc",
	"voucher.inc",
	"certs.inc",
	"filter_log.inc",
	"pkg-utils.inc",
	"vpn.inc",
	"cmd_chain.inc",
	"vslb.inc",
	"config.gui.inc",
	"globals.inc",
	"priv.defs.inc",
	"xmlparse.inc",
	"priv.inc",
	"config.inc",
	"growl.class",
	"radius.inc",
	"xmlrpc.inc",
	"gwlb.inc",
	"rrd.inc",
	"xmlrpc_client.inc",
	"config.lib.inc",
	"interfaces.inc",
	"service-utils.inc",
	"xmlrpc_server.inc",
	"ipsec.inc",
	"services.inc");

foreach($files as $file) 
	require_once($file);

require("guiconfig.inc");
require_once("authgui.inc");
include('/usr/local/www/includes/functions.inc.php');
include("fbegin.inc");
include("fend.inc"); 

?>
