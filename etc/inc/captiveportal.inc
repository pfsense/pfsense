<?php
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
require_once("radius_authentication.inc");
require_once("radius_accounting.inc") ;

function captiveportal_configure() {
	global $config, $g;
	
	if (isset($config['captiveportal']['enable']) &&
		(($config['captiveportal']['interface'] == "lan") ||
			isset($config['interfaces'][$config['captiveportal']['interface']]['enable']))) {
	
		if ($g['booting'])
			echo "Starting captive portal... ";
		
		/* kill any running mini_httpd */
		killbypid("{$g['varrun_path']}/mini_httpd.cp.pid");
		killbypid("{$g['varrun_path']}/mini_httpd.cps.pid");
		
		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/minicron.pid");
		
		/* generate ipfw rules */
		$cprules = captiveportal_rules_generate();
		
		/* make sure ipfw is loaded */
		mwexec("/sbin/kldload ipfw");
		
		/* stop accounting on all clients */
		captiveportal_radius_stop_all();

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
<head>
<title>m0n0wall captive portal</title>
</head>
<body>
<h2>m0n0wall captive portal</h2>
<p>This is the default captive portal page. Please upload your own custom HTML file on the <em>Services: Captive portal</em> screen in the m0n0wall webGUI.</p>
<form method="post" action="\$PORTAL_ACTION\$">
  <input name="accept" type="submit" value="Continue">
  <input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
</form>
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

		/* load rules */
		mwexec("/sbin/ipfw -f delete set 1");
		mwexec("/sbin/ipfw -f delete set 2");
		mwexec("/sbin/ipfw -f delete set 3");
		
		/* XXX - seems like ipfw cannot accept rules directly on stdin,
		   so we have to write them to a temporary file first */
		$fd = @fopen("{$g['tmp_path']}/ipfw.cp.rules", "w");
		if (!$fd) {
			printf("Cannot open ipfw.cp.rules in captiveportal_configure()\n");
			return 1;
		}
			
		fwrite($fd, $cprules);
		fclose($fd);
		
		mwexec("/sbin/ipfw {$g['tmp_path']}/ipfw.cp.rules");
		
		unlink("{$g['tmp_path']}/ipfw.cp.rules");
		
		/* filter on layer2 as well so we can check MAC addresses */
		mwexec("/sbin/sysctl net.link.ether.ipfw=1");
		
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
		captiveportal_passthrumac_configure();
		/* create allowed ip database and insert ipfw rules to make it so */
		captiveportal_allowedip_configure();

		/* generate radius server database */
		if ($config['captiveportal']['radiusip'] && (!isset($config['captiveportal']['auth_method']) ||
				($config['captiveportal']['auth_method'] == "radius"))) {
			$radiusip = $config['captiveportal']['radiusip'];

			if ($config['captiveportal']['radiusport'])
				$radiusport = $config['captiveportal']['radiusport'];
			else
				$radiusport = 1812;

			if ($config['captiveportal']['radiusacctport'])
				$radiusacctport = $config['captiveportal']['radiusacctport'];
			else
				$radiusacctport = 1813;

			$radiuskey = $config['captiveportal']['radiuskey'];

			$fd = @fopen("{$g['vardb_path']}/captiveportal_radius.db", "w");
			if (!$fd) {
				printf("Error: cannot open radius DB file in captiveportal_configure().\n");
				return 1;
			} else {
				fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey);
			}
			fclose($fd);
		}

		if ($g['booting'])
			echo "done\n";
		
	} else {
		killbypid("{$g['varrun_path']}/mini_httpd.cp.pid");
		killbypid("{$g['varrun_path']}/mini_httpd.cps.pid");
		killbypid("{$g['varrun_path']}/minicron.pid");

		captiveportal_radius_stop_all();

		mwexec("/sbin/sysctl net.link.ether.ipfw=0");

		if (!isset($config['shaper']['enable'])) {
			/* unload ipfw */
			mwexec("/sbin/kldunload ipfw");
		} else {
			/* shaper is on - just remove our rules */
			mwexec("/sbin/ipfw -f delete set 1");
			mwexec("/sbin/ipfw -f delete set 2");
			mwexec("/sbin/ipfw -f delete set 3");
		}
	}
	
	return 0;
}

function captiveportal_rules_generate() {
	global $config, $g;
	
	$cpifn = $config['captiveportal']['interface'];
	$cpif = $config['interfaces'][$cpifn]['if'];
	$cpip = $config['interfaces'][$cpifn]['ipaddr'];

	/* note: the captive portal daemon inserts all pass rules for authenticated
	   clients as skipto 50000 rules to make traffic shaping work */

	$cprules = "";
	
	/* captive portal on LAN interface? */
	if ($cpifn == "lan") {
		/* add anti-lockout rules */
		$cprules .= <<<EOD
add 500 set 1 pass all from $cpip to any out via $cpif
add 501 set 1 pass all from any to $cpip in via $cpif

EOD;
	}

	$cprules .= <<<EOD
# skip to traffic shaper if not on captive portal interface
add 1000 set 1 skipto 50000 all from any to any not layer2 not via $cpif
# pass all layer2 traffic on other interfaces
add 1001 set 1 pass layer2 not via $cpif

# layer 2: pass ARP
add 1100 set 1 pass layer2 mac-type arp
# layer 2: block anything else non-IP
add 1101 set 1 deny layer2 not mac-type ip
# layer 2: check if MAC addresses of authenticated clients are correct
add 1102 set 1 skipto 20000 layer2

# allow access to our DHCP server (which needs to be able to ping clients as well)
add 1200 set 1 pass udp from any 68 to 255.255.255.255 67 in
add 1201 set 1 pass udp from any 68 to $cpip 67 in
add 1202 set 1 pass udp from $cpip 67 to any 68 out
add 1203 set 1 pass icmp from $cpip to any out icmptype 8
add 1204 set 1 pass icmp from any to $cpip in icmptype 0

# allow access to our DNS forwarder
add 1300 set 1 pass udp from any to $cpip 53 in
add 1301 set 1 pass udp from $cpip 53 to any out

# allow access to our web server
add 1302 set 1 pass tcp from any to $cpip 8000 in
add 1303 set 1 pass tcp from $cpip 8000 to any out

EOD;

	if (isset($config['captiveportal']['httpslogin'])) {
		$cprules .= <<<EOD
add 1304 set 1 pass tcp from any to $cpip 8001 in
add 1305 set 1 pass tcp from $cpip 8001 to any out

EOD;
	}
	
	$cprules .= <<<EOD

# ... 10000-19899: rules per authenticated client go here...

# redirect non-authenticated clients to captive portal
add 19900 set 1 fwd 127.0.0.1,8000 tcp from any to any 80 in
# let the responses from the captive portal web server back out
add 19901 set 1 pass tcp from any 80 to any out
# block everything else
add 19902 set 1 deny all from any to any

# ... 20000-29899: layer2 block rules per authenticated client go here...

# pass everything else on layer2
add 29900 set 1 pass all from any to any layer2

EOD;

	return $cprules;
}

/* remove clients that have been around for longer than the specified amount of time */
/* db file structure: timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password */
/* (password is in Base64 and only saved when reauthentication is enabled) */
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
	
	if (!$timeout && !$idletimeout && !isset($config['captiveportal']['reauthenticate']))
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
		
		/* if an idle timeout is specified, get last activity timestamp from ipfw */
		if (!$timedout && $idletimeout) {
			$lastact = captiveportal_get_last_activity($cpdb[$i][1]);
			if ($lastact && ((time() - $lastact) >= $idletimeout))
				$timedout = true;
		}
		
		if ($timedout) {
			captiveportal_disconnect($cpdb[$i], $radiusservers);
			captiveportal_logportalauth($cpdb[$i][4], $cpdb[$i][3], $cpdb[$i][2], "TIMEOUT");
			unset($cpdb[$i]);
		}
		
		/* do periodic RADIUS reauthentication? */
		if (!$timedout && isset($config['captiveportal']['reauthenticate']) &&
			($radiusservers !== false)) {
		
			if (isset($config['captiveportal']['radacct_enable'])) {
				if ($config['captiveportal']['reauthenticateacct'] == "stopstart") {
					/* stop and restart accounting */
					RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
										   $cpdb[$i][4], // username
										   $cpdb[$i][5], // sessionid
										   $cpdb[$i][0], // start time
										   $radiusservers[0]['ipaddr'],
										   $radiusservers[0]['acctport'],
										   $radiusservers[0]['key'],
										   $cpdb[$i][2]); //clientip
					exec("/sbin/ipfw zero {$cpdb[$i][1]}");
					RADIUS_ACCOUNTING_START($cpdb[$i][4],
											$cpdb[$i][5],
											$radiusservers[0]['ipaddr'],
											$radiusservers[0]['acctport'],
											$radiusservers[0]['key'],
											$cpdb[$i][2]);
				} else if ($config['captiveportal']['reauthenticateacct'] == "interimupdate") {
					RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
										   $cpdb[$i][4], // username
										   $cpdb[$i][5], // sessionid
										   $cpdb[$i][0], // start time
										   $radiusservers[0]['ipaddr'],
										   $radiusservers[0]['acctport'],
										   $radiusservers[0]['key'],
										   $cpdb[$i][2], //clientip
										   true);
				}
			}
		
			/* check this user against RADIUS again */
			$auth_val = RADIUS_AUTHENTICATION($cpdb[$i][4],
										  base64_decode($cpdb[$i][6]),
							  			  $radiusservers[0]['ipaddr'],
							  			  $radiusservers[0]['port'],
							  			  $radiusservers[0]['key']);
			
			if ($auth_val == 3) {
				captiveportal_disconnect($cpdb[$i], $radiusservers);
				captiveportal_logportalauth($cpdb[$i][4], $cpdb[$i][3], $cpdb[$i][2], "RADIUS_DISCONNECT");
				unset($cpdb[$i]);
			}
		}
	}
	
	/* write database */
	captiveportal_write_db($cpdb);
	
	captiveportal_unlock();
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $radiusservers) {
	
	global $g, $config;
	
	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal']['radacct_enable']) && isset($radiusservers[0])) {
		RADIUS_ACCOUNTING_STOP($dbent[1], // ruleno
							   $dbent[4], // username
							   $dbent[5], // sessionid
							   $dbent[0], // start time
							   $radiusservers[0]['ipaddr'],
							   $radiusservers[0]['acctport'],
							   $radiusservers[0]['key'],
							   $dbent[2]); //clientip
	}
	
	mwexec("/sbin/ipfw delete " . $dbent[1] . " " . ($dbent[1]+10000));
	
	//KEYCOM: we need to delete +40500 and +45500 as well...
	//these are the rule numbers we use to control traffic shaping for each logged in user via captive portal
	//we only need to remove our rules if peruserbw is turned on.
	if (isset($config['captiveportal']['peruserbw'])) {
		mwexec("/sbin/ipfw delete " . ($dbent[1]+40500));
		mwexec("/sbin/ipfw delete " . ($dbent[1]+45500));
	}
}

/* remove a single client by ipfw rule number */
function captiveportal_disconnect_client($id) {
	
	global $g, $config;
	
	captiveportal_lock();
	
	/* read database */
	$cpdb = captiveportal_read_db();
	$radiusservers = captiveportal_get_radius_servers();
	
	/* find entry */	
	for ($i = 0; $i < count($cpdb); $i++) {
		if ($cpdb[$i][1] == $id) {
			captiveportal_disconnect($cpdb[$i], $radiusservers);
			captiveportal_logportalauth($cpdb[$i][4], $cpdb[$i][3], $cpdb[$i][2], "DISCONNECT");
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

	captiveportal_lock();
	$cpdb = captiveportal_read_db();
	
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
	captiveportal_unlock();
}

function captiveportal_passthrumac_configure() {
	global $config, $g;
	
	captiveportal_lock();
	
	/* clear out passthru macs, if necessary */
	unlink_if_exists("{$g['vardb_path']}/captiveportal_mac.db");
	
	if (is_array($config['captiveportal']['passthrumac'])) {
		
		$fd = @fopen("{$g['vardb_path']}/captiveportal_mac.db", "w");
		if (!$fd) {
			printf("Error: cannot open passthru mac DB file in captiveportal_passthrumac_configure().\n");
			captiveportal_unlock();
			return 1;		
		}
		
		foreach ($config['captiveportal']['passthrumac'] as $macent) {
			/* record passthru mac so it can be recognized and let thru */
			fwrite($fd, $macent['mac'] . "\n");
		}
		
		fclose($fd); 
	}
	
	captiveportal_unlock();
	
	return 0;
}

function captiveportal_allowedip_configure() {
	global $config, $g;
	
	captiveportal_lock();

	/* clear out existing allowed ips, if necessary */
	if (file_exists("{$g['vardb_path']}/captiveportal_ip.db")) {
		$fd = @fopen("{$g['vardb_path']}/captiveportal_ip.db", "r");
		if ($fd) {
			while (!feof($fd)) {
				$line = trim(fgets($fd));
				if ($line) {
					list($ip,$rule) = explode(",",$line);
					mwexec("/sbin/ipfw delete $rule");
				}	
			}
		}
		fclose($fd);
		unlink("{$g['vardb_path']}/captiveportal_ip.db");
	}

	/* get next ipfw rule number */
	if (file_exists("{$g['vardb_path']}/captiveportal.nextrule"))
		$ruleno = trim(file_get_contents("{$g['vardb_path']}/captiveportal.nextrule"));
	if (!$ruleno)
		$ruleno = 10000;	/* first rule number */
	
	if (is_array($config['captiveportal']['allowedip'])) {
		
		$fd = @fopen("{$g['vardb_path']}/captiveportal_ip.db", "w");
		if (!$fd) {
			printf("Error: cannot open allowed ip DB file in captiveportal_allowedip_configure().\n");
			captiveportal_unlock();
			return 1;		
		}
		
		foreach ($config['captiveportal']['allowedip'] as $ipent) {
		
			/* record allowed ip so it can be recognized and removed later */
			fwrite($fd, $ipent['ip'] . "," . $ruleno ."\n");
			
			/* insert ipfw rule to allow ip thru */
			if ($ipent['dir'] == "from") {
				mwexec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from " . $ipent['ip'] . " to any in");
				mwexec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from any to " . $ipent['ip'] . " out");
			} else {
				mwexec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from any to " . $ipent['ip'] . " in");
				mwexec("/sbin/ipfw add $ruleno set 2 skipto 50000 ip from " . $ipent['ip'] . " to any out");
			}
			
			$ruleno++;
			if ($ruleno > 19899)
				$ruleno = 10000;
		}
		
		fclose($fd); 

		/* write next rule number */
		$fd = @fopen("{$g['vardb_path']}/captiveportal.nextrule", "w");
		if ($fd) {
			fwrite($fd, $ruleno);
			fclose($fd);
		}
	}
	
	captiveportal_unlock();
	return 0;
}

/* get last activity timestamp given ipfw rule number */
function captiveportal_get_last_activity($ruleno) {
	
	exec("/sbin/ipfw -T list {$ruleno} 2>/dev/null", $ipfwoutput);
	
	/* in */
	if ($ipfwoutput[0]) {
		$ri = explode(" ", $ipfwoutput[0]);
		if ($ri[1])
			return $ri[1];
	}
	
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

/* log successful captive portal authentication to syslog */
/* part of this code from php.net */
function captiveportal_logportalauth($user,$mac,$ip,$status) {
	define_syslog_variables();
	openlog("logportalauth", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, "$status: $user, $mac, $ip");
	closelog();
}

?>
