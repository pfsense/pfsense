<?php
/*
	captiveportal.inc
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2009 Ermal Luçi
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
	
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/sbin/ipfw	/sbin/sysctl	/sbin/kldunload
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/lighttpd	/usr/local/bin/minicron	/sbin/pfctl
	pfSense_BUILDER_BINARIES:	/bin/hostname	/bin/cp	
	pfSense_MODULE:	captiveportal
*/

/* include all configuration functions */
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("radius.inc");
require_once("voucher.inc");

function captiveportal_configure() {
	global $config, $g;

	$captiveportallck = lock('captiveportal');
	
	$cpactive = false;
	if (isset($config['captiveportal']['enable'])) {
		$cpips = array();
		$ifaces = get_configured_interface_list();
		foreach ($ifaces as $kiface => $kiface2) {
			$tmpif = get_real_interface($kiface);
			mwexec("/sbin/ifconfig {$tmpif} -ipfwfilter");
		}
		$cpinterfaces = explode(",", $config['captiveportal']['interface']);
		$firsttime = 0;
		foreach ($cpinterfaces as $cpifgrp) {
			if (!isset($ifaces[$cpifgrp]))
				continue;
			$tmpif = get_real_interface($cpifgrp);
			if (!empty($tmpif)) {
				if ($firsttime > 0)
					$cpinterface .= " or ";
				$cpinterface .= "via {$tmpif}"; 
				$firsttime = 1;
				$cpipm = get_interface_ip($cpifgrp);
				if (is_ipaddr($cpipm)) {
					$carpif = link_ip_to_carp_interface($cpipm);
					if (!empty($carpif)) {
						$carpsif = explode(" ", $carpif);
						foreach ($carpsif as $cpcarp) {
							mwexec("/sbin/ifconfig {$cpcarp} ipfwfilter");
							$carpip = find_interface_ip($cpcarp);
							if (is_ipaddr($carpip))
								$cpips[] = $carpip;
						}
					}
					$cpips[] = $cpipm;
					mwexec("/sbin/ifconfig {$tmpif} ipfwfilter");
				}
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

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_mac.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_ip.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius.db");
		mwexec("/sbin/ipfw -q table all flush");

		/* setup new database in case someone tries to access the status -> captive portal page */
		touch("{$g['vardb_path']}/captiveportal.db");

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/minicron.pid");

		/* make sure ipfw is loaded */
		if (!is_module_loaded("ipfw.ko"))
			filter_load_ipfw();
		/* Always load dummynet now that even allowed ip and mac passthrough use it. */
		if (!is_module_loaded("dummynet.ko"))
                        mwexec("/sbin/kldload dummynet");

		/* generate ipfw rules */
		captiveportal_init_ipfw_ruleno();
		$cprules = captiveportal_rules_generate($cpinterface, $cpips);
		$cprules .= "\n";
		/* generate passthru mac database */
		$cprules .= captiveportal_passthrumac_configure(true);
		$cprules .= "\n";
		/* allowed ipfw rules to make allowed ip work */
		$cprules .= captiveportal_allowedip_configure();

		/* stop accounting on all clients */
		captiveportal_radius_stop_all(true);

		/* initialize minicron interval value */
		$croninterval = $config['captiveportal']['croninterval'] ? $config['captiveportal']['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10)) { $croninterval = 60; }

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
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$htmltext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $htmltext);
			$htmltext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $htmltext);
			$htmltext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $htmltext);
			$htmltext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $htmltext);
			$htmltext = str_replace("\$ORIGINAL_PORTAL_IP\$", "#ORIGINAL_PORTAL_IP#", $htmltext);
			$htmltext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $htmltext);
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
<a href="javascript:history.back(); ">Go back</a>
</b>
</body>
</html>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-error.html", "w");
		if ($fd) {
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$errtext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $errtext);
			$errtext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $errtext);
			$errtext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $errtext);
			$errtext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $errtext);
			$errtext = str_replace("\$ORIGINAL_PORTAL_IP\$", "#ORIGINAL_PORTAL_IP#", $errtext);
			$errtext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $errtext);
			fwrite($fd, $errtext);
			fclose($fd);
		}

		/* write elements */
		captiveportal_write_elements();

		/* load rules */
		mwexec("/sbin/ipfw -q flush");

		/* ipfw cannot accept rules directly on stdin,
		   so we have to write them to a temporary file first */
		$fd = @fopen("{$g['tmp_path']}/ipfw.cp.rules", "w");
		if (!$fd) {
			printf("Cannot open ipfw.cp.rules in captiveportal_configure()\n");
			return 1;
		}

		fwrite($fd, $cprules);
		fclose($fd);

		mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw.cp.rules");

		@unlink("{$g['tmp_path']}/ipfw.cp.rules");

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
			if (isset($config['captiveportal']['cacertificate']))
				$cacert = base64_decode($config['captiveportal']['cacertificate']);
			else
				$cacert = "";
			$key = base64_decode($config['captiveportal']['private-key']);
			/* generate lighttpd configuration */
			system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal-SSL.conf",
				$cert, $key, $cacert, "lighty-CaptivePortal-ssl.pid", "8001", "/usr/local/captiveportal/",
					"cert-portal.pem", "ca-portal.pem", "1", $maxproc, $use_fastcgi, true);
		}

		/* generate lighttpd configuration */
		system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal.conf",
			"", "", "", "lighty-CaptivePortal.pid", "8000", "/usr/local/captiveportal/",
				"cert-portal.pem", "ca-portal.pem", "1", $maxproc, $use_fastcgi, true);

		/* attempt to start lighttpd */
		$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal.conf");

		/* fire up https instance */
		if(isset($config['captiveportal']['httpslogin']))
			$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal-SSL.conf");

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/minicron.pid " .
			"/etc/rc.prunecaptiveportal");

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
		if (is_module_loaded("ipfw.ko"))		
			mwexec("/sbin/kldunload ipfw.ko");
		$listifs = get_configured_interface_list_by_realif();
		foreach ($listifs as $listrealif => $listif) {
			if (!empty($listrealif)) {
				if (does_interface_exist($listrealif)) {
					mwexec("/sbin/ifconfig {$listrealif} -ipfwfilter");
					$carpif = link_ip_to_carp_interface(find_interface_ip($listrealif));
                        		if (!empty($carpif)) {
						$carpsif = explode(" ", $carpif);
						foreach ($carpsif as $cpcarp)
							mwexec("/sbin/ifconfig {$cpcarp} -ipfwfilter");
					}
				}
			}
		}
	}

	unlock($captiveportallck);
	
	return 0;
}

function captiveportal_rules_generate($cpif, &$cpiparray) {
	global $config, $g;

	$cprules =  "add 65291 set 1 allow pfsync from any to any\n";
	$cprules .= "add 65292 set 1 allow carp from any to any\n";

	$cprules .= <<<EOD
# add 65300 set 1 skipto 65534 all from any to any not layer2
# layer 2: pass ARP
add 65301 set 1 pass layer2 mac-type arp
# pfsense requires for WPA
add 65302 set 1 pass layer2 mac-type 0x888e
add 65303 set 1 pass layer2 mac-type 0x88c7

# PPP Over Ethernet Discovery Stage
add 65304 set 1 pass layer2 mac-type 0x8863
# PPP Over Ethernet Session Stage
add 65305 set 1 pass layer2 mac-type 0x8864
# Allow WPA
add 65306 set 1 pass layer2 mac-type 0x888e

# layer 2: block anything else non-IP
add 65307 set 1 deny layer2 not mac-type ip

EOD;

	$rulenum = 65310;
	$ips = "255.255.255.255 ";
	foreach ($cpiparray as $cpip)
		$ips .= "or {$cpip} ";
	$ips = "{ {$ips} }";
	//# allow access to our DHCP server (which needs to be able to ping clients as well)
	$cprules .= "add {$rulenum} set 1 pass udp from any 68 to {$ips} 67 in \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass udp from any 68 to {$ips} 67 in \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass udp from {$ips} 67 to any 68 out \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass icmp from {$ips} to any out icmptype 0\n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass icmp from any to {$ips} in icmptype 8 \n";
	$rulenum++;
	//# allow access to our DNS forwarder
	$cprules .= "add {$rulenum} set 1 pass udp from any to {$ips} 53 in \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass udp from {$ips} 53 to any out \n";
	$rulenum++;
	# allow access to our web server
	$cprules .= "add {$rulenum} set 1 pass tcp from any to {$ips} 8000 in \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass tcp from {$ips} 8000 to any out \n";

	if (isset($config['captiveportal']['httpslogin'])) {
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass tcp from any to {$ips} 8001 in \n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pass tcp from {$ips} 8001 to any out \n";
	}
	if (!empty($config['system']['webgui']['port']))
		$port = $config['system']['webgui']['port'];
	else if ($config['system']['webgui']['proto'] == "http")
		$port = 80;
	else
		$port = 443;
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass tcp from any to {$ips} {$port} in \n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass tcp from {$ips} {$port} to any out \n";
	$rulenum++;

	/* Allowed ips */
	$cprules .= "add {$rulenum} allow ip from table(3) to any in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} allow ip from any to table(4) out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from table(5) to any in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from any to table(6) out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} allow ip from any to table(7) in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} allow ip from table(8) to any out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from any to table(9) in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from table(10) to any out\n";
	$rulenum++;

	/* Authenticated users rules. */
	if (isset($config['captiveportal']['peruserbw'])) {
		$cprules .= "add {$rulenum} set 1 pipe tablearg ip from table(1) to any in\n";
		$rulenum++;
		$cprules .= "add {$rulenum} set 1 pipe tablearg ip from any to table(2) out\n";
		$rulenum++;
	} else {
		$cprules .= "add {$rulenum} set 1 allow ip from table(1) to any in\n";
                $rulenum++;
                $cprules .= "add {$rulenum} set 1 allow ip from any to table(2) out\n";
                $rulenum++;
	}
	
       $cprules .= <<<EOD

# redirect non-authenticated clients to captive portal
add 65531 set 1 fwd 127.0.0.1,8000 tcp from any to any in
# let the responses from the captive portal web server back out
add 65532 set 1 pass tcp from any to any out
# block everything else
add 65533 set 1 deny all from any to any
# pass everything else on layer2
add 65534 set 1 pass all from any to any layer2

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

    if (!$timeout && !$idletimeout && !isset($config['captiveportal']['reauthenticate']) && 
		!isset($config['captiveportal']['radiussession_timeout']) && !isset($config['voucher']['enable']))
        return;

    $captiveportallck = lock('captiveportal');

    /* read database */
    $cpdb = captiveportal_read_db();

    $radiusservers = captiveportal_get_radius_servers();

    /*  To make sure we iterate over ALL accounts on every run the count($cpdb) is moved
     *  outside of the loop. Otherwise the loop would evaluate count() on every iteration
     *  and since $i would increase and count() would decrement they would meet before we
     *  had a chance to iterate over all accounts.
     */
    $unsetindexes = array();
    $no_users = count($cpdb);
    for ($i = 0; $i < $no_users; $i++) {

        $timedout = false;
        $term_cause = 1;

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
            $lastact = captiveportal_get_last_activity($cpdb[$i][2]);
			/*  If the user has logged on but not sent any traffic they will never be logged out.
			 *  We "fix" this by setting lastact to the login timestamp. 
			 */
			$lastact = $lastact ? $lastact : $cpdb[$i][0];
            if ($lastact && ((time() - $lastact) >= $idletimeout)) {
                $timedout = true;
                $term_cause = 4; // Idle-Timeout
                $stop_time = $lastact; // Entry added to comply with WISPr
            }
        }

	/* if vouchers are configured, activate session timeouts */
	if (!$timedout && isset($config['voucher']['enable']) && !empty($cpdb[$i][7])) {
		if (time() >= ($cpdb[$i][0] + $cpdb[$i][7])) {
			$timedout = true;
			$term_cause = 5; // Session-Timeout
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
	    $unsetindexes[$i] = $i;
        }

        /* do periodic RADIUS reauthentication? */
        if (!$timedout && isset($config['captiveportal']['reauthenticate']) &&
            !empty($radiusservers)) {

            if (isset($config['captiveportal']['radacct_enable'])) {
                if ($config['captiveportal']['reauthenticateacct'] == "stopstart") {
                    /* stop and restart accounting */
                    RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
                                           $cpdb[$i][4], // username
                                           $cpdb[$i][5], // sessionid
                                           $cpdb[$i][0], // start time
                                           $radiusservers,
                                           $cpdb[$i][2], // clientip
                                           $cpdb[$i][3], // clientmac
                                           10); // NAS Request
                    exec("/sbin/ipfw table 1 entryzerostats {$cpdb[$i][2]}");
                    exec("/sbin/ipfw table 2 entryzerostats {$cpdb[$i][2]}");
                    RADIUS_ACCOUNTING_START($cpdb[$i][1], // ruleno
                                            $cpdb[$i][4], // username
                                            $cpdb[$i][5], // sessionid
                                            $radiusservers,
                                            $cpdb[$i][2], // clientip
                                            $cpdb[$i][3]); // clientmac
                } else if ($config['captiveportal']['reauthenticateacct'] == "interimupdate") {
                    RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
                                           $cpdb[$i][4], // username
                                           $cpdb[$i][5], // sessionid
                                           $cpdb[$i][0], // start time
                                           $radiusservers,
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
	        $unsetindexes[$i] = $i;
            }
        }
    }
    /* This is a kludge to overcome some php weirdness */
    foreach($unsetindexes as $unsetindex)
	unset($cpdb[$unsetindex]);

    /* write database */
    captiveportal_write_db($cpdb);

    unlock($captiveportallck);
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $radiusservers,$term_cause = 1,$stop_time = null) {

	global $g, $config;

	$stop_time = (empty($stop_time)) ? time() : $stop_time;

	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal']['radacct_enable']) && !empty($radiusservers)) {
		RADIUS_ACCOUNTING_STOP($dbent[1], // ruleno
							   $dbent[4], // username
							   $dbent[5], // sessionid
							   $dbent[0], // start time
							   $radiusservers,
							   $dbent[2], // clientip
							   $dbent[3], // clientmac
							   $term_cause, // Acct-Terminate-Cause
							   false,
							   $stop_time);
	}
	/* Delete client's ip entry from tables 3 and 4. */
	mwexec("/sbin/ipfw table 1 delete {$dbent[2]}");
	mwexec("/sbin/ipfw table 2 delete {$dbent[2]}");

	/* Release the ruleno so it can be reallocated to new clients. */
	captiveportal_free_ipfw_ruleno($dbent[1]);

	/* 
	* These are the pipe numbers we use to control traffic shaping for each logged in user via captive portal
	* We could get an error if the pipe doesn't exist but everything should still be fine
	*/
	if (isset($config['captiveportal']['peruserbw'])) {
		mwexec("/sbin/ipfw pipe " . ($dbent[1]+20000) . " delete");
		mwexec("/sbin/ipfw pipe " . ($dbent[1]+20001) . " delete");
	}

	/* XXX: Redundant?! Ensure all pf(4) states are killed. */
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
	$tmpindex = 0;
	$cpdbcount = count($cpdb);
	for ($i = 0; $i < $cpdbcount; $i++) {
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
	if (!empty($radiusservers)) {
		for ($i = 0; $i < count($cpdb); $i++) {
			RADIUS_ACCOUNTING_STOP($cpdb[$i][1], // ruleno
								   $cpdb[$i][4], // username
								   $cpdb[$i][5], // sessionid
								   $cpdb[$i][0], // start time
								   $radiusservers,
								   $cpdb[$i][2], // clientip
								   $cpdb[$i][3], // clientmac
								   7); // Admin Reboot
		}
	}
	if (!$lock)
		unlock($captiveportallck);
}

function captiveportal_passthrumac_configure_entry($macent) {
	$rules = "";
        $enBwup = isset($macent['bw_up']);
        $enBwdown = isset($macent['bw_down']);
	$actionup = "allow";
	$actiondown = "allow";

        if ($enBwup && $enBwdown)
                $ruleno = captiveportal_get_next_ipfw_ruleno(2000, 49899, true);
        else
                $ruleno = captiveportal_get_next_ipfw_ruleno(2000, 49899, false);

	if ($enBwup) {
                $bw_up = $ruleno + 20000;
                $rules .= "pipe {$bw_up} config bw {$macent['bw_up']}Kbit/s queue 100\n";
		$actionup = "pipe {$bw_up}";
        }
        if ($enBwdown) {
		$bw_down = $ruleno + 20001;
		$rules .= "pipe {$bw_down} config bw {$macent['bw_down']}Kbit/s queue 100\n";
		$actiondown = "pipe {$bw_down}";
        }
	$rules .= "add {$ruleno} {$actionup} ip from any to any MAC {$macent['mac']} any\n";
	$ruleno++;
	$rules .= "add {$ruleno} {$actiondown} ip from any to any MAC any {$macent['mac']}\n";

	return $rules;
}

function captiveportal_passthrumac_configure($lock = false) {
	global $config, $g;

	$rules = "";

	if (is_array($config['captiveportal']['passthrumac'])) {
		$macdb = array();
		foreach ($config['captiveportal']['passthrumac'] as $macent) {
			$rules .= captiveportal_passthrumac_configure_entry($macent);
			$macdb[$macent['mac']]['active']  = true;

		}
	}

	return $rules;
}

/* 
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 * table (5=IN)/(6=OUT) hold allowed ip's with bw limit.
 */
function captiveportal_allowedip_configure_entry($ipent) {

	$rules = "";
	$enBwup = isset($ipent['bw_up']);
	$enBwdown = isset($ipent['bw_down']);
	$bw_up = "";
        $bw_down = "";
        $tablein = array();
        $tableout = array();

	if ($enBwup && $enBwdown)
		$ruleno = captiveportal_get_next_ipfw_ruleno(2000, 49899, true);
	else
		$ruleno = captiveportal_get_next_ipfw_ruleno(2000, 49899, false);

        if ($ipent['dir'] == "from") {
        	if ($enBwup)
                	$tablein[] = 5;
                else
                	$tablein[] = 3;
                if ($enBwdown)
                        $tableout[] = 6;
                else
                        $tableout[] = 4;
        } else if ($ipent['dir'] == "to") {
                if ($enBwup)
                	$tablein[] = 9;
                else
                        $tablein[] = 7;
                if ($enBwdown)
                        $tableout[] = 10;
                else
                        $tableout[] = 8;
        } else if ($ipent['dir'] == "both") {
                if ($enBwup) {
                        $tablein[] = 5;
                        $tablein[] = 9;
                } else {
                        $tablein[] = 3;
                        $tablein[] = 7;
                }
        	if ($enBwdown) {
                        $tableout[] = 6;
                        $tableout[] = 10;
                } else {
                        $tableout[] = 4;
                	$tableout[] = 8;
                }
        }
        if ($enBwup) {
                $bw_up = $ruleno + 20000;
        	$rules .= "pipe {$bw_up} config bw {$ipent['bw_up']}Kbit/s queue 100\n";
        }
	foreach ($tablein as $table)
               $rules .= "table {$table} add {$ipent['ip']} {$bw_up}\n";
        if ($enBwdown) {
               $bw_down = $ruleno + 20001;
               $rules .= "pipe {$bw_down} config bw {$ipent['bw_down']}Kbit/s queue 100\n";
        }
        foreach ($tableout as $table)
        	$rules .= "table {$table} add {$ipent['ip']} {$bw_down}\n";

	return $rules;
}

function captiveportal_allowedip_configure() {
	global $config, $g;

	$rules = "";
	if (is_array($config['captiveportal']['allowedip'])) {
		foreach ($config['captiveportal']['allowedip'] as $ipent) {
			$rules .= captiveportal_allowedip_configure_entry($ipent);
		}
	}

	return $rules;
}

/* get last activity timestamp given client IP address */
function captiveportal_get_last_activity($ip) {

	$ipfwoutput = "";

	exec("/sbin/ipfw table 1 entrystats {$ip} 2>/dev/null", $ipfwoutput);
	/* Reading only from one of the tables is enough of approximation. */
	if ($ipfwoutput[0]) {
		$ri = explode(" ", $ipfwoutput[0]);
		if ($ri[4])
			return $ri[4];
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

    /* If the pool is empty, return appropriate message and fail authentication */
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

function captiveportal_init_ipfw_ruleno($rulenos_start = 2000, $rulenos_range_max = 49899) {
	global $g;

	@unlink("{$g['vardb_path']}/captiveportal.rules");
	$rules = array_pad(array(), $rulenos_range_max - $rulenos_start, false);
	file_put_contents("{$g['vardb_path']}/captiveportal.rules", serialize($rules));
}

/*
 * This function will calculate the lowest free firewall ruleno
 * within the range specified based on the actual logged on users
 *
 */
function captiveportal_get_next_ipfw_ruleno($rulenos_start = 2000, $rulenos_range_max = 49899, $usebw = false) {
	global $config, $g;

	if(!isset($config['captiveportal']['enable']))
		return NULL;

	$ruleno = 0;
	if (file_exists("{$g['vardb_path']}/captiveportal.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal.rules"));
		for ($ridx = 2; $ridx < ($rulenos_range_max - $rulenos_start); $ridx++) {
			if ($rules[$ridx]) {
				/* 
	 			 * This allows our traffic shaping pipes to be the in pipe the same as ruleno 
	 			 * and the out pipe ruleno + 1. This removes limitation that where present in 
	 			 * previous version of the peruserbw.
	 			 */
				if (isset($config['captiveportal']['peruserbw']))
					$ridx++;
				continue;
			}
			$ruleno = $ridx;
			$rules[$ridx] = "used";
			if (isset($config['captiveportal']['peruserbw']) || $usebw == true)
				$rules[++$ridx] = "used";
			break;
		}
	} else {
		$rules = array_pad(array(), $rulenos_range_max - $rulenos_start, false);
		$rules[2] = "used";
		$ruleno = 2;
	}
	file_put_contents("{$g['vardb_path']}/captiveportal.rules", serialize($rules));
	return $ruleno;
}

function captiveportal_free_ipfw_ruleno($ruleno, $usedbw = false) {
	global $config, $g;

	if(!isset($config['captiveportal']['enable']))
		return NULL;

	if (file_exists("{$g['vardb_path']}/captiveportal.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal.rules"));
		$rules[$ruleno] = false;
		if (isset($config['captiveportal']['peruserbw']) || $usedbw == true)
			$rules[++$ruleno] = false;
		file_put_contents("{$g['vardb_path']}/captiveportal.rules", serialize($rules));
	}
}

function captiveportal_get_ipfw_passthru_ruleno($value) {
	global $config, $g;

	if(!isset($config['captiveportal']['enable']))
                return NULL;

        if (file_exists("{$g['vardb_path']}/captiveportal.rules")) {
                $rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal.rules"));
		$ruleno = intval(`/sbin/ipfw show | /usr/bin/grep {$value} |  /usr/bin/grep -v grep | /usr/bin/cut -d " " -f 1 | /usr/bin/head -n 1`);
		if ($rules[$ruleno])
			return $ruleno;
        }

	return NULL;
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

function getVolume($ip) {

    $volume = array();

    // Initialize vars properly, since we don't want NULL vars
    $volume['input_pkts'] = $volume['input_bytes'] = $volume['output_pkts'] = $volume['output_bytes'] = 0 ;

    // Ingress
    $ipfwin = "";
    $ipfwout = "";
    $matchesin = "";
    $matchesout = "";
    exec("/sbin/ipfw table 1 entrystats {$ip}", $ipfwin);
    if ($ipfwin[0]) {
		$ipfwin = split(" ", $ipfwin[0]);
		$volume['input_pkts'] = $ipfwin[2];
		$volume['input_bytes'] = $ipfwin[3];
    }

    exec("/sbin/ipfw table 2 entrystats {$ip}", $ipfwout);
    if ($ipfwout[0]) {
        $ipfwout = split(" ", $ipfwout[0]);
        $volume['output_pkts'] = $ipfwout[2];
        $volume['output_bytes'] = $ipfwout[3];
    }

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

function portal_ip_from_client_ip($cliip) {
	global $config;

	$interfaces = explode(",", $config['captiveportal']['interface']);
	foreach ($interfaces as $cpif) {
		$ip = get_interface_ip($cpif);
		$sn = get_interface_subnet($cpif);
		if (ip_in_subnet($cliip, "{$ip}/{$sn}"))
			return $ip;
	}

	// doesn't match up to any particular interface
	// so let's set the portal IP to what PHP says 
	// the server IP issuing the request is. 
	// allows same behavior as 1.2.x where IP isn't 
	// in the subnet of any CP interface (static routes, etc.)
	// rather than forcing to DNS hostname resolution
	$ip = $_SERVER['SERVER_ADDR'];
	if (is_ipaddr($ip))
		return $ip;

	return false;
}

?>
