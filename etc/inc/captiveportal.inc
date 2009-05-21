<?php
/*
	captiveportal.inc
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
require_once("globals.inc");
require_once("util.inc");
require_once("radius_authentication.inc");
require_once("radius_accounting.inc");
require_once("radius.inc");

$lockfile = "{$g['varrun_path']}/captiveportal.lock";

function captiveportal_configure() {
	global $config, $g;

	$captiveportallck = lock('captiveportal');
	
	$cpactive = false;
	if (isset($config['captiveportal']['enable'])) {
		$cpips = array();
		$ifaces = get_configured_interface_list();
		$cpinterfaces = explode(",", $config['captiveportal']['interface']);
		$firsttime = 0;
		foreach ($cpinterfaces as $cpifgrp) {
			if (!isset($ifaces[$cpifgrp]))
				continue;
			if ($firsttime > 0)
				$cpinterface .= " or ";
			$firsttime = 1;
			$tmpif = get_real_interface($cpifgrp);
			if (!empty($tmpif)) {
				$cpinterface .= "via {$tmpif}"; 
				$cpips[] = get_interface_ip($cpifgrp);
			}
		}
		if (count($cpips) > 0) {
			$cpactive = true;
			$cpinterface = "{ {$cpinterface} } ";
		}
	}

	if ($cpactive == true) {

		if ($g['booting'])
			echo "Starting captive portal... ";

		/* kill any running mini_httpd */
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal-SSL.pid");

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/minicron.pid");

		/* make sure ipfw is loaded */
		if (!is_module_loaded("ipfw.ko"))
			filter_load_ipfw();
		if (isset($config['captiveportal']['peruserbw']) && !is_module_loaded("dummynet.ko"))
                        mwexec("/sbin/kldload dummynet");

		/* generate ipfw rules */
		$cprules = captiveportal_rules_generate($cpinterface, $cpips);

		/* stop accounting on all clients */
		captiveportal_radius_stop_all(true);

		/* initialize minicron interval value */
		$croninterval = $config['captiveportal']['croninterval'] ? $config['captiveportal']['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10)) { $croninterval = 60; }

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
<title>{$g['product_name']} captive portal</title>
</head>
<body>
<center>
<h2>{$g['product_name']} captive portal</h2>
Welcome to the {$g['product_name']} Captive Portal!  This is the default page since a custom page has not been defined.
<p>
<form method="post" action="\$PORTAL_ACTION\$">
<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
<table>
   <tr><td>Username:</td><td><input name="auth_user" type="text"></td></tr>
   <tr><td>Password:</td><td><input name="auth_pass" type="password"></td></tr>
   <tr><td>&nbsp;</td></tr>
   <tr>
     <td colspan="2">
	<center><input name="accept" type="submit" value="Continue"></center>
     </td>
   </tr>
</table>
</center>
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

		/* write elements */
		captiveportal_write_elements();

		/* load rules */
		mwexec("/sbin/ipfw -f delete set 1");
		mwexec("/sbin/ipfw -f delete set 2");
		mwexec("/sbin/ipfw -f delete set 3");

		/* ipfw cannot accept rules directly on stdin,
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

		if ($config['captiveportal']['maxproc'])
			$maxproc = $config['captiveportal']['maxproc'];
		else
			$maxproc = 16;

		$use_fastcgi = true;

		if(isset($config['captiveportal']['httpslogin'])) {
			$cert = base64_decode($config['captiveportal']['certificate']);
			$key = base64_decode($config['captiveportal']['private-key']);
			/* generate lighttpd configuration */
			system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal-SSL.conf",
				$cert, $key, "lighty-CaptivePortal-ssl.pid", "8001", "/usr/local/captiveportal/",
					"cert-portal.pem", "1", $maxproc, $use_fastcgi, true);
		}

		/* generate lighttpd configuration */
		system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal.conf",
			"", "", "lighty-CaptivePortal.pid", "8000", "/usr/local/captiveportal/",
				"cert-portal.pem", "1", $maxproc, $use_fastcgi, true);

		/* attempt to start lighttpd */
		$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal.conf");

		/* fire up https instance */
		if(isset($config['captiveportal']['httpslogin']))
			$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal-SSL.conf");

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/minicron.pid " .
			"/etc/rc.prunecaptiveportal");

		/* generate passthru mac database */
		captiveportal_passthrumac_configure(true);
		/* create allowed ip database and insert ipfw rules to make it so */
		captiveportal_allowedip_configure(true);

		/* generate radius server database */
		if ($config['captiveportal']['radiusip'] && (!isset($config['captiveportal']['auth_method']) ||
				($config['captiveportal']['auth_method'] == "radius"))) {
			$radiusip = $config['captiveportal']['radiusip'];
			$radiusip2 = ($config['captiveportal']['radiusip2']) ? $config['captiveportal']['radiusip2'] : null;

			if ($config['captiveportal']['radiusport'])
				$radiusport = $config['captiveportal']['radiusport'];
			else
				$radiusport = 1812;

			if ($config['captiveportal']['radiusacctport'])
				$radiusacctport = $config['captiveportal']['radiusacctport'];
			else
				$radiusacctport = 1813;

			if ($config['captiveportal']['radiusport2'])
				$radiusport2 = $config['captiveportal']['radiusport2'];
			else
				$radiusport2 = 1812;

			$radiuskey = $config['captiveportal']['radiuskey'];
			$radiuskey2 = ($config['captiveportal']['radiuskey2']) ? $config['captiveportal']['radiuskey2'] : null;

			$fd = @fopen("{$g['vardb_path']}/captiveportal_radius.db", "w");
			if (!$fd) {
				printf("Error: cannot open radius DB file in captiveportal_configure().\n");
				return 1;
			} else if (isset($radiusip2, $radiuskey2)) {
				fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey . "\n"
					 . $radiusip2 . "," . $radiusport2 . "," . $radiusacctport . "," . $radiuskey2);
			} else {
				fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey);
			}
			fclose($fd);
		}

		if ($g['booting'])
			echo "done\n";

	} else {
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/minicron.pid");

		captiveportal_radius_stop_all(true);

		mwexec("/sbin/sysctl net.link.ether.ipfw=0");

		/* unload ipfw */
		mwexec("/sbin/kldunload ipfw.ko");
	}

	unlock($captiveportallck);
	
	return 0;
}

function captiveportal_rules_generate($cpif, &$cpiparray) {
	global $config, $g;

	$cpifn = $config['captiveportal']['interface'];
	$lanip = get_interface_ip("lan");
	
	/* note: the captive portal daemon inserts all pass rules for authenticated
	   clients as skipto 50000 rules to make traffic shaping work */

	$cprules =  "add 500 set 1 allow pfsync from any to any\n";
	$cprules .= "add 500 set 1 allow carp from any to any\n";

	/*   allow nat redirects to work  see
	     http://cvstrac.pfsense.com/tktview?tn=651
	 */
        /* if list */
        $iflist = get_configured_interface_list();
	foreach ($iflist as $ifent => $ifname) {
		if(stristr($cpifn, $ifname))
			continue;
		$int = get_real_interface($ifname);
		$cprules .= "add 30 set 1 skipto 50000 all from any to any in via {$int} keep-state\n";
	}

	/* captive portal on LAN interface? */
	if (stristr($cpifn,  "lan")) {
		/* add anti-lockout rules */
		$cprules .= <<<EOD
add 500 set 1 pass all from $lanip to any out $cpif
add 501 set 1 pass all from any to $lanip in $cpif

EOD;
	}

	$cprules .= <<<EOD
# skip to traffic shaper if not on captive portal interface
add 1000 set 1 skipto 50000 all from any to any not layer2 not $cpif
# pass all layer2 traffic on other interfaces
add 1001 set 1 pass layer2 not $cpif

# layer 2: pass ARP
add 1100 set 1 pass layer2 mac-type arp
# pfsense requires for WPA
add 1100 set 1 pass layer2 mac-type 0x888e
add 1100 set 1 pass layer2 mac-type 0x88c7

# PPP Over Ethernet Discovery Stage
add 1100 set 1 pass layer2 mac-type 0x8863
# PPP Over Ethernet Session Stage
add 1100 set 1 pass layer2 mac-type 0x8864
# Allow WPA
add 1100 set 1 pass layer2 mac-type 0x888e

# layer 2: block anything else non-IP
add 1101 set 1 deny layer2 not mac-type ip
# layer 2: check if MAC addresses of authenticated clients are correct
add 1102 set 1 skipto 20000 layer2

EOD;

	$rulenum = 1200;
	foreach ($cpiparray as $cpip) {
		//# allow access to our DHCP server (which needs to be able to ping clients as well)
		$cprules .= "add {$rulenum} set 1 pass udp from any 68 to 255.255.255.255 67 in \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass udp from any 68 to {$cpip} 67 in \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass udp from {$cpip} 67 to any 68 out \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass icmp from {$cpip} to any out icmptype 8\n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass icmp from any to {$cpip} in icmptype 0 \n";
		$rulenum++;
		//# allow access to our DNS forwarder
		$cprules .= "add {$rulenum} set 1 pass udp from any to {$cpip} 53 in \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass udp from {$cpip} 53 to any out \n";
		$rulenum++;
		# allow access to our web server
		$cprules .= "add {$rulenum} set 1 pass tcp from any to {$cpip} 8000 in \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass tcp from {$cpip} 8000 to any out \n";

		if (isset($config['captiveportal']['httpslogin'])) {
			$rulenum++;
			$cprules .= "add {$rulenum} set 1 pass tcp from any to {$cpip} 8001 in \n";
			$rulenum++;
			$cprules .= "add {$rulenum} set 1 pass tcp from {$cpip} 8001 to any out \n";
		}
	}

	$rulenum++;
        //# allow access to our DNS forwarder if it incorrectly resolves the hostname to $lanip
        $cprules .= "add {$rulenum} set 1 pass udp from any to {$lanip} 53 in \n";
        $rulenum++;
        $cprules .= "add {$rulenum} set 1 pass udp from {$lanip} 53 to any out \n";
	//# allow access to lan web server incase the dns name resolves incorrectly to $lanip
	$rulenum++;
        $cprules .= "add {$rulenum} set 1 pass tcp from any to {$lanip} 8000 in \n";
        $rulenum++;
        $cprules .= "add {$rulenum} set 1 pass tcp from {$lanip} 8000 to any out \n";

        $cprules .= <<<EOD

# ... 10000-19899: rules per authenticated client go here...

# redirect non-authenticated clients to captive portal
add 19902 set 1 fwd 127.0.0.1,8000 tcp from any to any 80 in
# let the responses from the captive portal web server back out
add 19903 set 1 pass tcp from any 80 to any out
# block everything else
add 19904 set 1 deny all from any to any

# ... 20000-29899: layer2 block rules per authenticated client go here...

# pass everything else on layer2
add 29900 set 1 pass all from any to any layer2

EOD;

    return $cprules;
}

/* remove clients that have been around for longer than the specified amount of time */
/* db file structure:
timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time */

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

    if (!$timeout && !$idletimeout && !isset($config['captiveportal']['reauthenticate']) && !isset($config['captiveportal']['radiussession_timeout']))
        return;

    $captiveportallck = lock('captiveportal');

    /* read database */
    $cpdb = captiveportal_read_db();

    $radiusservers = captiveportal_get_radius_servers();

 	/*  To make sure we iterate over ALL accounts on every run the count($cpdb) is moved outside of the loop. Otherwise
     *  the loop would evalate count() on every iteration and since $i would increase and count() would decrement they
     *  would meet before we had a chance to iterate over all accounts.
     */
    $no_users = count($cpdb);
    for ($i = 0; $i < $no_users; $i++) {

        $timedout = false;
        $term_cause = 1;

		/* no pruning for fixed mac address entry */
		if (portal_mac_fixed($cpdb[$i][3])) {
			continue; // check next value
		}
        /* hard timeout? */
        if ($timeout) {
            if ((time() - $cpdb[$i][0]) >= $timeout) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
            }
        }

        /* Session-Terminate-Time */
        if (!$timedout && !empty($cpdb[$i][9])) {
            if (time() >= $cpdb[$i][9]) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
            }
        }

        /* check if the radius idle_timeout attribute has been set and if its set change the idletimeout to this value */
        $idletimeout = (is_numeric($cpdb[$i][8])) ? $cpdb[$i][8] : $idletimeout;
        /* if an idle timeout is specified, get last activity timestamp from ipfw */
        if (!$timedout && $idletimeout) {
            $lastact = captiveportal_get_last_activity($cpdb[$i][1]);
			/*  if the user has logged on but not sent any trafic they will never be logged out.
			 *  We "fix" this by setting lastact to the login timestamp 
			 */
			$lastact = $lastact ? $lastact : $cpdb[$i][0];
            if ($lastact && ((time() - $lastact) >= $idletimeout)) {
                $timedout = true;
                $term_cause = 4; // Idle-Timeout
                $stop_time = $lastact; // Entry added to comply with WISPr
            }
        }

        /* if radius session_timeout is enabled and the session_timeout is not null, then check if the user should be logged out */
        if (!$timedout && isset($config['captiveportal']['radiussession_timeout']) && !empty($cpdb[$i][7])) {
            if (time() >= ($cpdb[$i][0] + $cpdb[$i][7])) {
                $timedout = true;
                $term_cause = 5; // Session-Timeout
            }
        }

        if ($timedout) {
            captiveportal_disconnect($cpdb[$i], $radiusservers,$term_cause,$stop_time);
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
                                           $cpdb[$i][2], // clientip
                                           $cpdb[$i][3], // clientmac
                                           10); // NAS Request
                    exec("/sbin/ipfw zero {$cpdb[$i][1]}");
                    RADIUS_ACCOUNTING_START($cpdb[$i][1], // ruleno
                                            $cpdb[$i][4], // username
                                            $cpdb[$i][5], // sessionid
                                            $radiusservers[0]['ipaddr'],
                                            $radiusservers[0]['acctport'],
                                            $radiusservers[0]['key'],
                                            $cpdb[$i][2], // clientip
                                            $cpdb[$i][3]); // clientmac
                } else if ($config['captiveportal']['reauthenticateacct'] == "interimupdate") {
                    RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
                                           $cpdb[$i][4], // username
                                           $cpdb[$i][5], // sessionid
                                           $cpdb[$i][0], // start time
                                           $radiusservers[0]['ipaddr'],
                                           $radiusservers[0]['acctport'],
                                           $radiusservers[0]['key'],
                                           $cpdb[$i][2], // clientip
                                           $cpdb[$i][3], // clientmac
                                           10, // NAS Request
                                           true); // Interim Updates
                }
            }

            /* check this user against RADIUS again */
            $auth_list = RADIUS_AUTHENTICATION($cpdb[$i][4], // username
                                          base64_decode($cpdb[$i][6]), // password
                                            $radiusservers,
                                          $cpdb[$i][2], // clientip
                                          $cpdb[$i][3], // clientmac
                                          $cpdb[$i][1]); // ruleno

            if ($auth_list['auth_val'] == 3) {
                captiveportal_disconnect($cpdb[$i], $radiusservers, 17);
                captiveportal_logportalauth($cpdb[$i][4], $cpdb[$i][3], $cpdb[$i][2], "RADIUS_DISCONNECT", $auth_list['reply_message']);
                unset($cpdb[$i]);
            }
        }
    }

    /* write database */
    captiveportal_write_db($cpdb);

    unlock($captiveportallck);
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $radiusservers,$term_cause = 1,$stop_time = null) {

	global $g, $config;

	$stop_time = (empty($stop_time)) ? time() : $stop_time;

	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal']['radacct_enable']) && isset($radiusservers[0])) {
		RADIUS_ACCOUNTING_STOP($dbent[1], // ruleno
							   $dbent[4], // username
							   $dbent[5], // sessionid
							   $dbent[0], // start time
							   $radiusservers[0]['ipaddr'],
							   $radiusservers[0]['acctport'],
							   $radiusservers[0]['key'],
							   $dbent[2], // clientip
							   $dbent[3], // clientmac
							   $term_cause, // Acct-Terminate-Cause
							   false,
							   $stop_time);
	}

	mwexec("/sbin/ipfw delete " . $dbent[1] . " " . ($dbent[1]+10000));

    /* We need to delete +40500 and +45500 as well...
     * these are the pipe numbers we use to control traffic shaping for each logged in user via captive portal
     * We could get an error if the pipe doesn't exist but everything should still be fine
     */
    if (isset($config['captiveportal']['peruserbw'])) {
        mwexec("/sbin/ipfw pipe " . ($dbent[1]+40500) . " delete");
        mwexec("/sbin/ipfw pipe " . ($dbent[1]+45500) . " delete");
    }

	/* pfSense: ensure all pf states are killed (pfSense) */
	mwexec("pfctl -k {$dbent[2]}");
	mwexec("pfctl -K {$dbent[2]}");

}

/* remove a single client by ipfw rule number */
function captiveportal_disconnect_client($id,$term_cause = 1) {

	global $g, $config;

	$captiveportallck = lock('captiveportal');

	/* read database */
	$cpdb = captiveportal_read_db();
	$radiusservers = captiveportal_get_radius_servers();

	/* find entry */
	for ($i = 0; $i < count($cpdb); $i++) {
		if ($cpdb[$i][1] == $id) {
			captiveportal_disconnect($cpdb[$i], $radiusservers, $term_cause);
			captiveportal_logportalauth($cpdb[$i][4], $cpdb[$i][3], $cpdb[$i][2], "DISCONNECT");
			unset($cpdb[$i]);
			break;
		}
	}

	/* write database */
	captiveportal_write_db($cpdb);

	unlock($captiveportallck);
}

/* send RADIUS acct stop for all current clients */
function captiveportal_radius_stop_all($lock = false) {
	global $g, $config;

	if (!isset($config['captiveportal']['radacct_enable']))
		return;

	if (!$lock)
		$captiveportallck = lock('captiveportal');

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
								   $cpdb[$i][2], // clientip
								   $cpdb[$i][3], // clientmac
								   7); // Admin Reboot
		}
	}
	if (!$lock)
		unlock($captiveportallck);
}

function captiveportal_passthrumac_configure($lock = false) {
	global $config, $g;

	if (!$lock)
		$captiveportallck = lock('captiveportal');

	/* clear out passthru macs, if necessary */
	unlink_if_exists("{$g['vardb_path']}/captiveportal_mac.db");

	if (is_array($config['captiveportal']['passthrumac'])) {

		$fd = @fopen("{$g['vardb_path']}/captiveportal_mac.db", "w");
		if (!$fd) {
			printf("Error: cannot open passthru mac DB file in captiveportal_passthrumac_configure().\n");
			unlock($captiveportallck);
			return 1;
		}

		foreach ($config['captiveportal']['passthrumac'] as $macent) {
			/* record passthru mac so it can be recognized and let thru */
			fwrite($fd, $macent['mac'] . "\n");
		}

		fclose($fd);
	}

	/*    pfSense:
	 * 	  pass through mac entries should always exist.  the reason
	 *    for this is because we do not have native mac address filtering
	 *    mechanisms.  this allows us to filter by mac address easily
	 *    and get around this limitation.   I consider this a bug in
	 *    m0n0wall and pfSense as m0n0wall does not have native mac
	 *    filtering mechanisms as well. -Scott Ullrich
	 */
	if (is_array($config['captiveportal']['passthrumac'])) {
		mwexec("/sbin/ipfw delete 50");
		foreach($config['captiveportal']['passthrumac'] as $ptm) {
			/* create the pass through mac entry */
			//system("echo /sbin/ipfw add 50 skipto 65535 ip from any to any MAC {$ptm['mac']} any > /tmp/cp");
			mwexec("/sbin/ipfw add 50 skipto 29900 ip from any to any MAC {$ptm['mac']} any keep-state");
			mwexec("/sbin/ipfw add 50 skipto 29900 ip from any to any MAC any {$ptm['mac']} keep-state");
		}
	}

	if (!$lock)
		unlock($captiveportallck);

	return 0;
}

function captiveportal_allowedip_configure($lock = false) {
	global $config, $g;

	if (!$lock)
		$captiveportallck = lock('captiveportal');

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
			unlock($captiveportallck);
			return 1;
		}

		foreach ($config['captiveportal']['allowedip'] as $ipent) {
            /* get next ipfw rule number */
            $ruleno = captiveportal_get_next_ipfw_ruleno();

            /* if the pool is empty, return apprioriate message and fail */
            if (is_null($ruleno)) {
                printf("Error: system reached maximum login capacity, no free FW rulenos in captiveportal_allowedip_configure().\n");
                fclose($fd);
                unlock($captiveportallck);
                return 1;
            }

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

        }

        fclose($fd);
    }

	if (!$lock)
    		unlock($captiveportallck);
    return 0;
}

/* get last activity timestamp given ipfw rule number */
function captiveportal_get_last_activity($ruleno) {

	$ipfwoutput = "";

	exec("/sbin/ipfw -T list {$ruleno} 2>/dev/null", $ipfwoutput);

	/* in */
	if ($ipfwoutput[0]) {
		$ri = explode(" ", $ipfwoutput[0]);
		if ($ri[1])
			return $ri[1];
	}

	return 0;
}

/* read RADIUS servers into array */
function captiveportal_get_radius_servers() {

        global $g;

        if (file_exists("{$g['vardb_path']}/captiveportal_radius.db")) {
                $radiusservers = array();
		$cpradiusdb = file("{$g['vardb_path']}/captiveportal_radius.db", 
			FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($cpradiusdb)
		foreach($cpradiusdb as $cpradiusentry) {
                	$line = trim($cpradiusentry);
                        if ($line) {
                        	$radsrv = array();
                                list($radsrv['ipaddr'],$radsrv['port'],$radsrv['acctport'],$radsrv['key']) = explode(",",$line);
                        	$radiusservers[] = $radsrv;
                        }
		}

		return $radiusservers;
        }

        return false;
}

/* log successful captive portal authentication to syslog */
/* part of this code from php.net */
function captiveportal_logportalauth($user,$mac,$ip,$status, $message = null) {
	$message = trim($message);
	// Log it
	if (!$message)
		$message = "$status: $user, $mac, $ip";
	else
		$message = "$status: $user, $mac, $ip, $message";
	captiveportal_syslog($message);
	closelog();
}

/* log simple messages to syslog */
function captiveportal_syslog($message) {
	define_syslog_variables();
	$message = trim($message);
	openlog("logportalauth", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}

function radius($username,$password,$clientip,$clientmac,$type) {
    global $g, $config;

    /* Start locking from the beginning of an authentication session */
    $captiveportallck = lock('captiveportal');

    $ruleno = captiveportal_get_next_ipfw_ruleno();

    /* if the pool is empty, return apprioriate message and fail authentication */
    if (is_null($ruleno)) {
        $auth_list = array();
        $auth_list['auth_val'] = 1;
        $auth_list['error'] = "System reached maximum login capacity";
        unlock($captiveportallck);
        return $auth_list;
    }

    /*
     * Drop the lock since radius takes some time to finish.
     * The implementation is reentrant so we gain speed with this.
     */
    unlock($captiveportallck);

    $radiusservers = captiveportal_get_radius_servers();

    $auth_list = RADIUS_AUTHENTICATION($username,
                    $password,
                    $radiusservers,
                    $clientip,
                    $clientmac,
                    $ruleno);

    $captiveportallck = lock('captiveportal');

    if ($auth_list['auth_val'] == 2) {
        captiveportal_logportalauth($username,$clientmac,$clientip,$type);
        $sessionid = portal_allow($clientip,
                    $clientmac,
                    $username,
                    $password,
                    $auth_list,
                    $ruleno);
    }

    unlock($captiveportallck);

    return $auth_list;

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

function captiveportal_write_elements() {
    global $g, $config;
    
    /* delete any existing elements */
    if (is_dir($g['captiveportal_element_path'])) {
        $dh = opendir($g['captiveportal_element_path']);
        while (($file = readdir($dh)) !== false) {
            if ($file != "." && $file != "..")
                unlink($g['captiveportal_element_path'] . "/" . $file);
        }
        closedir($dh);
    } else {
        @mkdir($g['captiveportal_element_path']);
    }
    
	if (is_array($config['captiveportal']['element'])) {
		conf_mount_rw();
		foreach ($config['captiveportal']['element'] as $data) {
			$fd = @fopen($g['captiveportal_element_path'] . '/' . $data['name'], "wb");
			if (!$fd) {
				printf("Error: cannot open '{$data['name']}' in captiveportal_write_elements().\n");
				return 1;
			}
			$decoded = base64_decode($data['content']);
			fwrite($fd,$decoded);
			fclose($fd);
			unlink_if_exists("{$g['captiveportal_path']}/{$data['name']}");
			unlink_if_exists("{$g['captiveportal_path']}/{$data['name']}");
			mwexec("cd {$g['captiveportal_path']}/ && ln -s {$g['captiveportal_element_path']}/{$data['name']} {$data['name']}");
		}
		conf_mount_ro();
	}
    
    return 0;
}

/*
 * This function will calculate the lowest free firewall ruleno
 * within the range specified based on the actual installed rules
 *
 */
function captiveportal_get_next_ipfw_ruleno($rulenos_start = 10000, $rulenos_range_max = 9899) {

	$fwrules = "";
	$matches = "";
	exec("/sbin/ipfw show", $fwrules);
	foreach ($fwrules as $fwrule) {
		preg_match("/^(\d+)\s+/", $fwrule, $matches);
		$rulenos_used[] = $matches[1];
	}
	$rulenos_used = array_unique($rulenos_used);
	$rulenos_range = count($rulenos_used);
	if ($rulenos_range > $rulenos_range_max) {
		return NULL;
	}
	$rulenos_pool = range($rulenos_start, ($rulenos_start + $rulenos_range));
	$rulenos_free = array_diff($rulenos_pool, $rulenos_used);
	$ruleno = array_shift($rulenos_free);

	return $ruleno;
}

/**
 * This function will calculate the traffic produced by a client
 * based on its firewall rule
 *
 * Point of view: NAS
 *
 * Input means: from the client
 * Output means: to the client
 *
 */

function getVolume($ruleno) {

    $volume = array();

    // Initialize vars properly, since we don't want NULL vars
    $volume['input_pkts'] = $volume['input_bytes'] = $volume['output_pkts'] = $volume['output_bytes'] = 0 ;

    // Ingress
    $ipfw = "";
    $matches = "";
    exec("/sbin/ipfw show {$ruleno}", $ipfw);
    preg_match("/(\d+)\s+(\d+)\s+(\d+)\s+.*/", $ipfw[0], $matches);
    $volume['input_pkts'] = $matches[2];
    $volume['input_bytes'] = $matches[3];

    // Flush internal buffer
    unset($matches);

    // Outgress
    preg_match("/(\d+)\s+(\d+)\s+(\d+)\s+.*/", $ipfw[1], $matches);
    $volume['output_pkts'] = $matches[2];
    $volume['output_bytes'] = $matches[3];

    return $volume;
}

/**
 * Get the NAS-Identifier
 *
 * We will use our local hostname to make up the nas_id
 */
function getNasID()
{
    $nasId = "";
    exec("/bin/hostname", $nasId);
    if(!$nasId[0])
        $nasId[0] = "{$g['product_name']}";
    return $nasId[0];
}

/**
 * Get the NAS-IP-Address based on the current wan address
 *
 * Use functions in interfaces.inc to find this out
 *
 */

function getNasIP()
{
    $nasIp = get_interface_ip();
    if(!$nasIp)
        $nasIp = "0.0.0.0";
    return $nasIp;
}

function portal_mac_fixed($clientmac) {
    global $g ;

    /* open captive portal mac db */
    if (file_exists("{$g['vardb_path']}/captiveportal_mac.db")) {
        $fd = @fopen("{$g['vardb_path']}/captiveportal_mac.db","r") ;
        if (!$fd) {
            return FALSE;
        }
        while (!feof($fd)) {
            $mac = trim(fgets($fd)) ;
            if(strcasecmp($clientmac, $mac) == 0) {
                fclose($fd) ;
                return TRUE ;
            }
        }
        fclose($fd) ;
    }
    return FALSE ;
}

function portal_ip_from_client_ip($cliip) {
	global $config;

	$interfaces = explode(",", $config['captiveportal']['interface']);
	foreach ($interfaces as $cpif) {
		$ip = get_interface_ip($cpif);
		$sn = get_interface_subnet($cpif);
		if (ip_in_subnet($cliip, "{$ip}/{$sn}"))
			return $ip;
	}

	return false;
}

?>
