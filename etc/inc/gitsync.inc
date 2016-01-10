<?php
/*   cvs_sync
 *   Written by Scott Ullrich
 *   (C)2005-2007 Scott Ullrich
 *   (C)2010-2012 Erik Fonnesbeck
 *   Part of the pfSense project pfSsh.php subsystem
 */

require_once("globals.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("rrd.inc");
require_once("pfsense-utils.inc");

function post_cvssync_commands() {
	echo "===> Removing FAST-CGI temporary files...\n";
	exec("find /tmp -name \"php-fastcgi.socket*\" -exec rm -rf {} \;");
	exec("find /tmp -name \"*.tmp\" -exec rm -rf {} \;");

	exec("rm -rf /tmp/xcache/* 2>/dev/null");

	echo "===> Upgrading configuration (if needed)...\n";
	convert_config();

	echo "===> Configuring filter...";
	exec("/etc/rc.filter_configure_sync");
	exec("pfctl -f /tmp/rules.debug");
	echo "\n";

	if(file_exists("/etc/rc.php_ini_setup")) {
		echo "===> Running /etc/rc.php_ini_setup...";
		exec("/etc/rc.php_ini_setup");
		echo "\n";
	}

	/* lock down console if necessary */
	echo "===> Locking down the console if needed...\n";
	reload_ttys();
	
	echo "===> Signaling PHP and Lighty restart...";
	$fd = fopen("/tmp/restart_lighty", "w");
	fwrite($fd, "#!/bin/sh\n");
	fwrite($fd, "sleep 5\n");
	fwrite($fd, "/usr/local/sbin/pfSctl -c 'service restart webgui'\n");
	if(file_exists("/var/etc/lighty-CaptivePortal.conf"))
		fwrite($fd, "/usr/local/sbin/lighttpd -f /var/etc/lighty-CaptivePortal.conf\n");
	fclose($fd);
	mwexec_bg("sh /tmp/restart_lighty");
	echo "\n";

}

function isUrl($url = "") {
	if($url) 
		if(strstr($url, "rcs.pfsense.org") or 
			strstr($url, "mainline") or 
				strstr($url, ".git") or strstr($url, "git://"))
					return true;
	return false;
}

function run_cmds($cmds) {
	global $debug;
	foreach($cmds as $cmd) {
		if($debug)
			echo "Running $cmd";
		exec($cmd);
	}
}