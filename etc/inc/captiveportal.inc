<?php
/* $Id$ */
/*
	captiveportal.inc
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

	This version of captiveportal.inc has been modified by Rob Parker
	<rob.parker@keycom.co.uk> to include changes for per-user bandwidth management
	via returned RADIUS attributes. This page has been modified to delete any
	added rules which may have been created by other per-user code (index.php, etc).
	These changes are (c) 2004 Keycom PLC.
*/

/* include all configuration functions */
require_once("functions.inc");
require_once("radius_accounting.inc") ;

function captiveportal_configure() {
	global $config, $g;

	if (isset($config['captiveportal']['enable']) &&
		(($config['captiveportal']['interface'] == "lan") ||
			isset($config['interfaces'][$config['captiveportal']['interface']]['enable']))) {

		if($g['booting']) echo "Starting captive portal... ";

		/* kill any running mini_httpd */
		killbypid("{$g['varrun_path']}/mini_httpd.cp.pid");
		killbypid("{$g['varrun_path']}/mini_httpd.cps.pid");

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/minicron.pid");

		/* stop accounting on all clients */
		captiveportal_radius_stop_all() ;

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal.nextrule");
		unlink_if_exists("{$g['vardb_path']}/captiveportal.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_mac.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_ip.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius.db");

		/* write portal page */
		if ($config['captiveportal']['page']['htmltext'])
			$htmltext = base64_decode($config['captiveportal']['page']['htmltext']);
		else {
			/* example/template page */
			$htmltext = <<<EOD
<html>
<title>pfSense's captive portal</title>
<head>
 <STYLE type="text/css">
.listhdrr {
	background-color: #BBBBBB;
	padding-right: 16px;
	padding-left: 6px;
	font-weight: bold;
	border-right: 1px solid #999999;
	border-bottom: 1px solid #999999;
	font-size: 11px;
	padding-top: 5px;
	padding-bottom: 5px;
}

 </STYLE>
</head>
<body bgcolor="#990000">
<center>
<font color="white" face="arial" size="+1">Welcome to pfSense's captive portal!</font>
<p>
<form method="post" action="$PORTAL_ACTION$">
<table border="0" cellpadding="6" cellspacing="0">
<tr><td align="right" class="listhdrr"><font color="white">Username:</td><td class="listhdrr"><input name="auth_user" type="text"></td></tr>
<tr><td align="right" class="listhdrr"><font color="white">Password:</td><td class="listhdrr"><input name="auth_pass" type="password"></td></tr>
<input name="redirurl" type="hidden" value="$PORTAL_REDIRURL$">
</table>
<p>
<center><input name="accept" type="submit" value="Continue">
</form>
</center>
</body>
</html>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal.html", "w");
		if ($fd) {
			fwrite($fd, $htmltext);
			fclose($fd);
		}

		/* write error page */
		if ($config['captiveportal']['page']['errtext'])
			$errtext = base64_decode($config['captiveportal']['page']['errtext']);
		else {
			/* example page */
			$errtext = <<<EOD
<html>
<head>
<title>Authentication error</title>
</head>
<body>
<font color="#cc0000"><h2>Authentication error</h2></font>
<b>
Username and/or password invalid.
<br><br>
<a href="javascript:history.back()">Go back</a>
</b>
</body>
</html>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-error.html", "w");
		if ($fd) {
			fwrite($fd, $errtext);
			fclose($fd);
		}

		chdir($g['captiveportal_path']);

		/* start web server */
		mwexec("/usr/local/sbin/mini_httpd -a -M 0 -u root -maxproc 16" .
			" -p 8000 -i {$g['varrun_path']}/mini_httpd.cp.pid");

		/* fire up another one for HTTPS if requested */
		if (isset($config['captiveportal']['httpslogin']) &&
			$config['captiveportal']['certificate'] && $config['captiveportal']['private-key']) {

			$cert = base64_decode($config['captiveportal']['certificate']);
			$key = base64_decode($config['captiveportal']['private-key']);

			$fd = fopen("{$g['varetc_path']}/cert-portal.pem", "w");
			if (!$fd) {
				printf("Error: cannot open cert-portal.pem in system_webgui_start().\n");
				return 1;
			}
			chmod("{$g['varetc_path']}/cert-portal.pem", 0600);
			fwrite($fd, $cert);
			fwrite($fd, "\n");
			fwrite($fd, $key);
			fclose($fd);

			mwexec("/usr/local/sbin/mini_httpd -S -a -M 0 -E {$g['varetc_path']}/cert-portal.pem" .
				" -u root -maxproc 16 -p 8001" .
				" -i {$g['varrun_path']}/mini_httpd.cps.pid");
		}

		/* start pruning process (interval = 60 seconds) */
		mwexec("/usr/local/bin/minicron 60 {$g['varrun_path']}/minicron.pid " .
			"/etc/rc.prunecaptiveportal");

		/* generate passthru mac database */
		captiveportal_passthrumac_configure() ;
		/* create allowed ip database and insert pf tables to make it so */
		captiveportal_allowedip_configure() ;

		/* generate radius server database */
		if($config['captiveportal']['radiusip'] && $config['captiveportal']['auth_method']=="radius") {
			$radiusip = $config['captiveportal']['radiusip'] ;

			if($config['captiveportal']['radiusport'])
				$radiusport = $config['captiveportal']['radiusport'] ;
			else
				$radiusport = 1812;

			if($config['captiveportal']['radiusacctport'])
				$radiusacctport = $config['captiveportal']['radiusacctport'] ;
			else
				$radiusacctport = 1813;

			$radiuskey = $config['captiveportal']['radiuskey'];

			$fd = @fopen("{$g['vardb_path']}/captiveportal_radius.db", "w");
			if (!$fd) {
				printf("Error: cannot open radius DB file in captiveportal_configure().\n");
				return 1;
			} else {
				fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey) ;
			}
			fclose($fd) ;
		}

		if($g['booting']) print "done.\n";

	} else {
		killbypid("{$g['varrun_path']}/mini_httpd.cp.pid");
		killbypid("{$g['varrun_path']}/minicron.pid");
		captiveportal_radius_stop_all() ;
	}

	return 0;
}

/* remove clients that have been around for longer than the specified amount of time */
/* db file structure: timestamp,clientip,clientmac,username,sessionid */
function captiveportal_prune_old() {

	global $g, $config;

	/* check for expired entries */
	if ($config['captiveportal']['timeout'])
		$timeout = $config['captiveportal']['timeout'] * 60;
	else
		$timeout = 0;

	if ($config['captiveportal']['idletimeout'])
		$idletimeout = $config['captiveportal']['idletimeout'] * 60;
	else
		$idletimeout = 0;

	if (!$timeout && !$idletimeout)
		return;

	captiveportal_lock();

	/* read database */
	$cpdb = captiveportal_read_db();

	$radiusservers = captiveportal_get_radius_servers();

	for ($i = 0; $i < count($cpdb); $i++) {

		$timedout = false;

		/* hard timeout? */
		if ($timeout) {
			if ((time() - $cpdb[$i][0]) >= $timeout)
				$timedout = true;
		}

		/* if an idle timeout is specified, get last activity timestamp from pf */
		// XXX: we need a solution for this.
		if (!$timedout && $idletimeout) {
			$lastact = captiveportal_get_last_activity($cpdb[$i][1]);
			if ($lastact && ((time() - $lastact) >= $idletimeout))
				$timedout = true;
		}

		if ($timedout) {
			/* this client needs to be deleted - remove pf table item */
			if (isset($config['captiveportal']['radacct_enable']) && isset($radiusservers[0])) {
				RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
									   $cpdb[$i][4], // username
									   $cpdb[$i][5], // sessionid
									   $cpdb[$i][0], // start time
									   $radiusservers[0]['ipaddr'],
									   $radiusservers[0]['acctport'],
									   $radiusservers[0]['key'],
									   $cpdb[$i][2]); //clientip
				syslog(LOG_INFO,"Authenticated user $cpdb[$i][4] timed out");
			}
						
			mwexec("/sbin/pfctl -t captiveportal -T delete {$cpdb[$i][2]}");

			unset($cpdb[$i]);
		}
	}

	/* write database */
	captiveportal_write_db($cpdb);

	captiveportal_unlock();
}

/* remove a single client */
function captiveportal_disconnect_client($id) {

	global $g, $config;

	captiveportal_lock();

	/* read database */
	$cpdb = captiveportal_read_db();
	$radiusservers = captiveportal_get_radius_servers();

	/* find entry */
	for ($i = 0; $i < count($cpdb); $i++) {
		if ($cpdb[$i][1] == $id) {
			/* this client needs to be deleted - remove pf table item */
			if (isset($config['captiveportal']['radacct_enable']) && isset($radiusservers[0])) {
				RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
									   $cpdb[$i][4], // username
									   $cpdb[$i][5], // sessionid
									   $cpdb[$i][0], // start time
									   $radiusservers[0]['ipaddr'],
									   $radiusservers[0]['acctport'],
									   $radiusservers[0]['key'],
									   $cpdb[$i][2]); //clientip
				syslog(LOG_INFO,"Authenticated user $cpdb[$i][4] disconnected");
			}

			unset($cpdb[$i]);

			break;
		}
	}

	/* write database */
	captiveportal_write_db($cpdb);

	captiveportal_unlock();
}

/* send RADIUS acct stop for all current clients */
function captiveportal_radius_stop_all() {
	global $g, $config;

	captiveportal_lock() ;
	$cpdb = captiveportal_read_db() ;

	$radiusservers = captiveportal_get_radius_servers();

	if (isset($radiusservers[0])) {
		for ($i = 0; $i < count($cpdb); $i++) {
			RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
								   $cpdb[$i][4], // username
								   $cpdb[$i][5], // sessionid
								   $cpdb[$i][0], // start time
								   $radiusservers[0]['ipaddr'],
								   $radiusservers[0]['acctport'],
								   $radiusservers[0]['key'],
								   $cpdb[$i][2]); //clientip
		}
	}
	captiveportal_unlock() ;
}

function captiveportal_passthrumac_configure() {
	global $config, $g;

	/* clear out passthru macs, if necessary */
	if (file_exists("{$g['vardb_path']}/captiveportal_mac.db")) {
		unlink("{$g['vardb_path']}/captiveportal_mac.db");
	}

	if (is_array($config['captiveportal']['passthrumac'])) {

		$fd = @fopen("{$g['vardb_path']}/captiveportal_mac.db", "w");
		if (!$fd) {
			printf("Error: cannot open passthru mac DB file in captiveportal_passthrumac_configure().\n");
			return 1;
		}

		foreach ($config['captiveportal']['passthrumac'] as $macent) {
			/* record passthru mac so it can be recognized and let thru */
			fwrite($fd, $macent['mac'] . "\n");
		}

		fclose($fd);
	}

	return 0;
}

function captiveportal_allowedip_configure() {
	global $config, $g;

	captiveportal_lock() ;

	/* clear out existing allowed ips, if necessary */
	if (file_exists("{$g['vardb_path']}/captiveportal_ip.db")) {
		$fd = @fopen("{$g['vardb_path']}/captiveportal_ip.db", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = trim(fgets($fd));
				if($line) {
					list($ip,$rule) = explode(",",$line);
					mwexec("/sbin/pfctl -t captiveportal -T delete {$ip}");
				}
			}
		}
		fclose($fd) ;
		unlink("{$g['vardb_path']}/captiveportal_ip.db");
	}

	if (is_array($config['captiveportal']['allowedip'])) {

		$fd = @fopen("{$g['vardb_path']}/captiveportal_ip.db", "w");
		if (!$fd) {
			printf("Error: cannot open allowed ip DB file in captiveportal_allowedip_configure().\n");
			captiveportal_unlock() ;
			return 1;
		}

		foreach ($config['captiveportal']['allowedip'] as $ipent) {
			/* record allowed ip so it can be recognized and removed later */
			fwrite($fd, $ipent['ip'] . "," . $ruleno ."\n");

			/* insert pf table item to allow traffic */			
			mwexec("/sbin/pfctl -t captiveportal -T add {$ip}");

			$ruleno = $ip;
		}

		fclose($fd);

		/* write next rule number */
		$fd = @fopen("{$g['vardb_path']}/captiveportal.nextrule", "w");
		if ($fd) {
			fwrite($fd, $ruleno);
			fclose($fd);
		}
	}

	captiveportal_unlock() ;
	return 0;
}

/* get last activity timestamp given pf table item */
function captiveportal_get_last_activity($ruleno) {

	// XXX: this needs a solution
	//exec("/sbin/ipfw -T list {$ruleno} 2>/dev/null", $ipfwoutput);

	/* 
	if ($ipfwoutput[0]) {
		$ri = explode(" ", $ipfwoutput[0]);
		if ($ri[1])
			return $ri[1];
	}
	*/

	return 0;
}

/* read captive portal DB into array */
function captiveportal_read_db() {

	global $g;

	$cpdb = array();
	$fd = @fopen("{$g['vardb_path']}/captiveportal.db", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = trim(fgets($fd));
			if ($line) {
				$cpdb[] = explode(",", $line);
			}
		}
		fclose($fd);
	}
	return $cpdb;
}

/* write captive portal DB */
function captiveportal_write_db($cpdb) {

	global $g;

	$fd = @fopen("{$g['vardb_path']}/captiveportal.db", "w");
	if ($fd) {
		foreach ($cpdb as $cpent) {
			fwrite($fd, join(",", $cpent) . "\n");
		}
		fclose($fd);
	}
}

/* read RADIUS servers into array */
function captiveportal_get_radius_servers() {

	global $g;

	if (file_exists("{$g['vardb_path']}/captiveportal_radius.db")) {
	   	$fd = @fopen("{$g['vardb_path']}/captiveportal_radius.db","r");
		if ($fd) {
			$radiusservers = array();
			while (!feof($fd)) {
				$line = trim(fgets($fd));
				if ($line) {
					$radsrv = array();
					list($radsrv['ipaddr'],$radsrv['port'],$radsrv['acctport'],$radsrv['key']) = explode(",",$line);
					$radiusservers[] = $radsrv;
				}
			}
			fclose($fd);

			return $radiusservers;
		}
	}

	return false;
}

/* lock captive portal information, decide that the lock file is stale after
   10 seconds */
function captiveportal_lock() {

	global $g;

	$lockfile = "{$g['varrun_path']}/captiveportal.lock";

	$n = 0;
	while ($n < 10) {
		/* open the lock file in append mode to avoid race condition */
		if ($fd = @fopen($lockfile, "x")) {
			/* succeeded */
			fclose($fd);
			return;
		} else {
			/* file locked, wait and try again */
			sleep(1);
			$n++;
		}
	}
}

/* unlock configuration file */
function captiveportal_unlock() {

	global $g;

	$lockfile = "{$g['varrun_path']}/captiveportal.lock";

	if (file_exists($lockfile))
		unlink($lockfile);
}

?>
