<?php
/*
	captiveportal.inc
	part of pfSense (http://www.pfSense.org)
	Copyright (C) 2004-2011 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2009 Ermal Luçi <ermal.luci@gmail.com>
	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.

	originally part of m0n0wall (http://m0n0.ch/wall)
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
	
	pfSense_BUILDER_BINARIES:	/sbin/ipfw	/sbin/sysctl	/sbin/kldunload
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/lighttpd	/usr/local/bin/minicron /sbin/pfctl
	pfSense_BUILDER_BINARIES:	/bin/hostname	/bin/cp 
	pfSense_MODULE: captiveportal
*/

/* include all configuration functions */
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("radius.inc");
require_once("voucher.inc");

function get_default_captive_portal_html() {
	global $config, $g;
	// Detect if vouchers are being used and default to the voucher page
	if(isset($config['voucher']['enable'])) {
			$htmltext = <<<EOD
<html> 
	<body> 
		<form method="post" action="\$PORTAL_ACTION\$"> 
			<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$"> 
			<center>
				<table cellpadding="6" cellspacing="0" width="550" height="380" style="border:1px solid #000000">
					<tr height="10" bgcolor="#990000">
						<td style="border-bottom:1px solid #000000">
							<font color='white'>
								<b>
									Guest Voucher code required to continue
								</b>
							</font>
						</td>
					</tr>
					<tr>
						<td>
							<div id="mainlevel">
								<center>
									<table width="100%" border="0" cellpadding="5" cellspacing="0">
										<tr>
											<td>
												<center>
													<div id="mainarea">
														<center>
															<table width="100%" border="0" cellpadding="5" cellspacing="5">
																<tr>
																	<td>
																		<div id="maindivarea">
																			<center>
																				<div id='statusbox'>
																					<font color='red' face='arial' size='+1'>
																						<b>
																							\$PORTAL_MESSAGE\$
																						</b>
																					</font>
																				</div>
																				<p/>
																				<div id='loginbox'>
																					Enter Voucher Code: 
																					<input name="auth_voucher" type="text" style="border:1px dashed;" size="22"> 
																					<input name="accept" type="submit" value="Continue"> 
																				</div>
																			</center>
																		</div>
																	</td>
																</tr>
															</table>
														</center>
													</div>
												</center>
											</td>
										</tr>
									</table>
								</center>
							</div>
						</td>
					</tr>
				</table>
			</center>
		</form>
	</body> 
</html>

EOD;
		return $htmltext;
	}

	// Vouchers are not found, return the normal user/pass auth page
	$htmltext = <<<EOD
<html> 
	<body> 
		<form method="post" action="\$PORTAL_ACTION\$"> 
		<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
			<center>
				<table cellpadding="6" cellspacing="0" width="550" height="380" style="border:1px solid #000000">
					<tr height="10" bgcolor="#990000">
						<td style="border-bottom:1px solid #000000">
							<font color='white'>
								<b>
									{$g['product_name']} captive portal
								</b>
							</font>
						</td>
					</tr>
					<tr>
						<td>
							<div id="mainlevel">
								<center>
									<table width="100%" border="0" cellpadding="5" cellspacing="0">
										<tr>
											<td>
												<center>
													<div id="mainarea">
														<center>
															<table width="100%" border="0" cellpadding="5" cellspacing="5">
																<tr>
																	<td>
																		<div id="maindivarea">
																			<center>
																				<div id='statusbox'>
																					<font color='red' face='arial' size='+1'>
																						<b>
																							\$PORTAL_MESSAGE\$
																						</b>
																					</font>
																				</div>
																				<br/>
																				<div id='loginbox'>
																					<table>
																					   <tr><td colspan="2"><center>Welcome to the {$g['product_name']} Captive Portal!</td></tr>
																					   <tr><td>&nbsp;</td></tr>
																					   <tr><td align="right">Username:</td><td><input name="auth_user" type="text" style="border: 1px dashed;"></td></tr>
																					   <tr><td align="right">Password:</td><td><input name="auth_pass" type="password" style="border: 1px dashed;"></td></tr>
																					   <tr><td>&nbsp;</td></tr>
																					   <tr>
																						 <td colspan="2">
																							<center><input name="accept" type="submit" value="Continue"></center>
																						 </td>
																					   </tr>
																					</table>
																				</div>
																			</center>
																		</div>
																	</td>
																</tr>
															</table>
														</center>
													</div>
												</center>
											</td>
										</tr>
									</table>
								</center>
							</div>
						</td>
					</tr>
				</table>
			</center>
		</form>
	</body> 
</html>

EOD;

	return $htmltext;
}

function captiveportal_configure() {
	global $config, $g;

	$captiveportallck = lock('captiveportal', LOCK_EX);
	
	if (isset($config['captiveportal']['enable'])) {

		if ($g['booting'])
			echo "Starting captive portal... ";
		else
			captiveportal_syslog("Restarting captive portal.");

		/* kill any running mini_httpd */
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal-SSL.pid");

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_mac.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_ip.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius.db");

		/* setup new database in case someone tries to access the status -> captive portal page */
		touch("{$g['vardb_path']}/captiveportal.db");

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/cp_prunedb.pid");

		/* init ipfw rules */
		captiveportal_init_rules(true);

		/* stop accounting on all clients */
		captiveportal_radius_stop_all();

		/* initialize minicron interval value */
		$croninterval = $config['captiveportal']['croninterval'] ? $config['captiveportal']['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10))
			$croninterval = 60;

		/* write portal page */
		if ($config['captiveportal']['page']['htmltext'])
			$htmltext = base64_decode($config['captiveportal']['page']['htmltext']);
		else {
			/* example/template page */
			$htmltext = get_default_captive_portal_html();
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
			if($config['captiveportal']['preauthurl']) {
				$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$config['captiveportal']['preauthurl']}", $htmltext);
				$htmltext = str_replace("#PORTAL_REDIRURL#", "{$config['captiveportal']['preauthurl']}", $htmltext);
			}
			fwrite($fd, $htmltext);
			fclose($fd);
		}

		/* write error page */
		if ($config['captiveportal']['page']['errtext'])
			$errtext = base64_decode($config['captiveportal']['page']['errtext']);
		else {
			/* example page  */
			$errtext = get_default_captive_portal_html();
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
			if($config['captiveportal']['preauthurl']) {
				$errtext = str_replace("\$PORTAL_REDIRURL\$", "{$config['captiveportal']['preauthurl']}", $errtext);
				$errtext = str_replace("#PORTAL_REDIRURL#", "{$config['captiveportal']['preauthurl']}", $errtext);
			}
			fwrite($fd, $errtext);
			fclose($fd);
		}

		/* write error page */
		if ($config['captiveportal']['page']['logouttext'])
			$logouttext = base64_decode($config['captiveportal']['page']['logouttext']);
		else {
			/* example page */
			$logouttext = <<<EOD
<HTML>
<HEAD><TITLE>Redirecting...</TITLE></HEAD>
<BODY>
<SPAN STYLE="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<B>Redirecting to <A HREF="<?=\$my_redirurl;?>"><?=\$my_redirurl;?></A>...</B>
</SPAN>
<SCRIPT LANGUAGE="JavaScript">
<!--
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
	LogoutWin.document.write('<HTML>');
	LogoutWin.document.write('<HEAD><TITLE>Logout</TITLE></HEAD>') ;
	LogoutWin.document.write('<BODY BGCOLOR="#435370">');
	LogoutWin.document.write('<DIV ALIGN="center" STYLE="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
	LogoutWin.document.write('<B>Click the button below to disconnect</B><P>');
	LogoutWin.document.write('<FORM METHOD="POST" ACTION="<?=\$logouturl;?>">');
	LogoutWin.document.write('<INPUT NAME="logout_id" TYPE="hidden" VALUE="<?=\$sessionid;?>">');
	LogoutWin.document.write('<INPUT NAME="logout" TYPE="submit" VALUE="Logout">');
	LogoutWin.document.write('</FORM>');
	LogoutWin.document.write('</DIV></BODY>');
	LogoutWin.document.write('</HTML>');
	LogoutWin.document.close();
}

document.location.href="<?=\$my_redirurl;?>";
-->
</SCRIPT>
</BODY>
</HTML>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-logout.html", "w");
		if ($fd) {
			fwrite($fd, $logouttext);
			fclose($fd);
		}
		/* write elements */
		captiveportal_write_elements();

		/* start up the webserving daemon */
		captiveportal_init_webgui();

		/* Kill any existing prunecaptiveportal processes */
		if(file_exists("{$g['varrun_path']}/cp_prunedb.pid"))
			killbypid("{$g['varrun_path']}/cp_prunedb.pid");

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/cp_prunedb.pid " .
			"/etc/rc.prunecaptiveportal");

		/* generate radius server database */
		captiveportal_init_radius_servers();

		if ($g['booting'])
			echo "done\n";

	} else {
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/lighty-CaptivePortal-SSL.pid");
		killbypid("{$g['varrun_path']}/cp_prunedb.pid");

		captiveportal_radius_stop_all();

		mwexec("/sbin/sysctl net.link.ether.ipfw=0");

		/* unload ipfw */
		if (is_module_loaded("ipfw.ko"))		
			mwexec("/sbin/kldunload ipfw.ko");
		$listifs = get_configured_interface_list_by_realif();
		foreach ($listifs as $listrealif => $listif) {
			if (!empty($listrealif)) {
				if (does_interface_exist($listrealif)) {
					pfSense_interface_flags($listrealif, -IFF_IPFW_FILTER);
					$carpif = link_ip_to_carp_interface(find_interface_ip($listrealif));
					if (!empty($carpif)) {
						$carpsif = explode(" ", $carpif);
						foreach ($carpsif as $cpcarp)
							pfSense_interface_flags($cpcarp, -IFF_IPFW_FILTER);
					}
				}
			}
		}
	}

	unlock($captiveportallck);
	
	return 0;
}

function captiveportal_init_webgui() {
	global $g, $config;

	 if (!isset($config['captiveportal']['enable']))
				return;

	if ($config['captiveportal']['maxprocperip'])
		$maxproc = $config['captiveportal']['maxprocperip'];
	else
		$maxproc = 16;

	$use_fastcgi = true;

	if (isset($config['captiveportal']['httpslogin'])) {
		$cert = base64_decode($config['captiveportal']['certificate']);
		if (isset($config['captiveportal']['cacertificate']))
			$cacert = base64_decode($config['captiveportal']['cacertificate']);
		else
			$cacert = "";
		$key = base64_decode($config['captiveportal']['private-key']);
		/* generate lighttpd configuration */
		system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal-SSL.conf",
			$cert, $key, $cacert, "lighty-CaptivePortal-SSL.pid", "8001", "/usr/local/captiveportal/",
			"cert-portal.pem", "ca-portal.pem", "1", $maxproc, $use_fastcgi, true);
	}

	/* generate lighttpd configuration */
	system_generate_lighty_config("{$g['varetc_path']}/lighty-CaptivePortal.conf",
		"", "", "", "lighty-CaptivePortal.pid", "8000", "/usr/local/captiveportal/",
		"cert-portal.pem", "ca-portal.pem", "1", $maxproc, $use_fastcgi, true);

	/* attempt to start lighttpd */
	$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal.conf");

	/* fire up https instance */
	if (isset($config['captiveportal']['httpslogin']))
		$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-CaptivePortal-SSL.conf");
}

/* reinit will disconnect all users, be careful! */
function captiveportal_init_rules($reinit = false) {
	global $config, $g;

	if (!isset($config['captiveportal']['enable']))
		return;

	$cpips = array();
	$ifaces = get_configured_interface_list();
	foreach ($ifaces as $kiface => $kiface2) {
		$tmpif = get_real_interface($kiface);
		pfSense_interface_flags($tmpif, -IFF_IPFW_FILTER);
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
						pfSense_interface_flags($cpcarp, IFF_IPFW_FILTER);
						$carpip = find_interface_ip($cpcarp);
						if (is_ipaddr($carpip))
							$cpips[] = $carpip;
					}
				}
				$cpips[] = $cpipm;
				pfSense_interface_flags($tmpif, IFF_IPFW_FILTER);
			}
		}
	}
	if (count($cpips) > 0) {
		$cpactive = true;
		$cpinterface = "{ {$cpinterface} } ";
		} else
		return false;

	if ($reinit == false)
		$captiveportallck = lock('captiveportal');

	/* init dummynet/ipfw rules number database */
	captiveportal_init_ipfw_ruleno();

	/* make sure ipfw is loaded */
	filter_load_ipfw();
	/* Always load dummynet now that even allowed ip and mac passthrough use it. */
	if (!is_module_loaded("dummynet.ko"))
		mwexec("/sbin/kldload dummynet");

	$cprules =	"add 65291 set 1 allow pfsync from any to any\n";
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

# layer 2: block anything else non-IP
add 65307 set 1 deny layer2 not mac-type ip

EOD;

	$rulenum = 65310;
	$ipcount = 0;
	$ips = "";
	foreach ($cpips as $cpip) {
		if($ipcount == 0) {
			$ips = "{$cpip} ";
		} else {
			$ips .= "or {$cpip} ";
		}
		$ipcount++;
	}
	$ips = "{ 255.255.255.255 or {$ips} }";
	$cprules .= "add {$rulenum} set 1 pass ip from any to {$ips} in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass ip from {$ips} to any out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass icmp from {$ips} to any out icmptype 0\n";
	$rulenum++;
	$cprules .= "add {$rulenum} set 1 pass icmp from any to {$ips} in icmptype 8 \n";
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

	/* generate passthru mac database */
	$cprules .= captiveportal_passthrumac_configure(true);
	$cprules .= "\n";

	/* allowed ipfw rules to make allowed ip work */
	$cprules .= captiveportal_allowedip_configure();

	/* allowed ipfw rules to make allowed hostnames work */
	$cprules .= captiveportal_allowedhostname_configure();
	
	/* load rules */
	if ($reinit == true)
		$cprules = "table all flush\nflush\n{$cprules}";
	else {
		$tmprules = "table 3 flush\n";
		$tmprules .= "table 4 flush\n";
		$tmprules .= "table 5 flush\n";
		$tmprules .= "table 6 flush\n";
		$tmprules .= "table 7 flush\n";
		$tmprules .= "table 8 flush\n";
		$tmprules .= "table 9 flush\n";
		$tmprules .= "table 10 flush\n";
		$tmprules .= "flush\n";
		$cprules = "{$tmprules}\n{$cprules}";
	}

	file_put_contents("{$g['tmp_path']}/ipfw.cp.rules", $cprules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw.cp.rules", true);
	//@unlink("{$g['tmp_path']}/ipfw.cp.rules");

	if ($reinit == false)
		unlock($captiveportallck);

	/* activate ipfw(4) so CP can work */
	mwexec("/sbin/sysctl net.link.ether.ipfw=1");
	/* Make sure not re-entrancy is allowed in ipfw(4) */
	mwexec("/sbin/sysctl net.inet.ip.fw.one_pass=1");

	return $cprules;
}

/* remove clients that have been around for longer than the specified amount of time
 * db file structure:
 * timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time
 * (password is in Base64 and only saved when reauthentication is enabled)
 */
function captiveportal_prune_old() {
	global $g, $config;

	/* check for expired entries */
	if (empty($config['captiveportal']['timeout']) ||
	!is_numeric($config['captiveportal']['timeout']))
		$timeout = 0;
	else
		$timeout = $config['captiveportal']['timeout'] * 60;

	if (empty($config['captiveportal']['idletimeout']) ||
	!is_numeric($config['captiveportal']['idletimeout']))
		$idletimeout = 0;
	else
		$idletimeout = $config['captiveportal']['idletimeout'] * 60;

	if (!$timeout && !$idletimeout && !isset($config['captiveportal']['reauthenticate']) && 
	!isset($config['captiveportal']['radiussession_timeout']) && !isset($config['voucher']['enable']))
		return;

	$radiusservers = captiveportal_get_radius_servers();

	/* read database */
	$cpdb = captiveportal_read_db();

	/*	To make sure we iterate over ALL accounts on every run the count($cpdb) is moved
	 *	outside of the loop. Otherwise the loop would evaluate count() on every iteration
	 *	and since $i would increase and count() would decrement they would meet before we
	 *	had a chance to iterate over all accounts.
	 */
	$unsetindexes = array();
	$voucher_needs_sync = false;
	/* 
	 * Snapshot the time here to use for calculation to speed up the process.
	 * If something is missed next run will catch it!
	 */
	$pruning_time = time();
	$stop_time = $pruning_time;
	foreach ($cpdb as $cpentry) {

		$timedout = false;
		$term_cause = 1;

		/* hard timeout? */
		if ($timeout) {
			if (($pruning_time - $cpentry[0]) >= $timeout) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
			}
		}

		/* Session-Terminate-Time */
		if (!$timedout && !empty($cpentry[9])) {
			if ($pruning_time >= $cpentry[9]) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
			}
		}

		/* check if the radius idle_timeout attribute has been set and if its set change the idletimeout to this value */
		$uidletimeout = (is_numeric($cpentry[8])) ? $cpentry[8] : $idletimeout;
		/* if an idle timeout is specified, get last activity timestamp from ipfw */
		if (!$timedout && $uidletimeout) {
			$lastact = captiveportal_get_last_activity($cpentry[2]);
			/*	If the user has logged on but not sent any traffic they will never be logged out.
			 *	We "fix" this by setting lastact to the login timestamp. 
			 */
			$lastact = $lastact ? $lastact : $cpentry[0];
			if ($lastact && (($pruning_time - $lastact) >= $uidletimeout)) {
				$timedout = true;
				$term_cause = 4; // Idle-Timeout
				$stop_time = $lastact; // Entry added to comply with WISPr
			}
		}

		/* if vouchers are configured, activate session timeouts */
		if (!$timedout && isset($config['voucher']['enable']) && !empty($cpentry[7])) {
			if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$voucher_needs_sync = true;
			}
		}

		/* if radius session_timeout is enabled and the session_timeout is not null, then check if the user should be logged out */
		if (!$timedout && isset($config['captiveportal']['radiussession_timeout']) && !empty($cpentry[7])) {
			if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
			}
		}

		if ($timedout) {
			captiveportal_disconnect($cpentry, $radiusservers,$term_cause,$stop_time);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "TIMEOUT");
			$unsetindexes[] = $cpentry[5];
		}

		/* do periodic RADIUS reauthentication? */
		if (!$timedout && !empty($radiusservers)) {
			if (isset($config['captiveportal']['radacct_enable'])) {
				if ($config['captiveportal']['reauthenticateacct'] == "stopstart") {
					/* stop and restart accounting */
					RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$cpentry[0], // start time
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						10); // NAS Request
					exec("/sbin/ipfw table 1 entryzerostats {$cpentry[2]}");
					exec("/sbin/ipfw table 2 entryzerostats {$cpentry[2]}");
					RADIUS_ACCOUNTING_START($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3]); // clientmac
				} else if ($config['captiveportal']['reauthenticateacct'] == "interimupdate") {
					RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$cpentry[0], // start time
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						10, // NAS Request
						true); // Interim Updates
				}
			}

			/* check this user against RADIUS again */
			if (isset($config['captiveportal']['reauthenticate'])) {
				$auth_list = RADIUS_AUTHENTICATION($cpentry[4], // username
					base64_decode($cpentry[6]), // password
					$radiusservers,
					$cpentry[2], // clientip
					$cpentry[3], // clientmac
					$cpentry[1]); // ruleno
				if ($auth_list['auth_val'] == 3) {
					captiveportal_disconnect($cpentry, $radiusservers, 17);
					captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "RADIUS_DISCONNECT", $auth_list['reply_message']);
					$unsetindexes[] = $cpentry[5];
				} else if ($auth_list['auth_val'] == 2) // SUCCESS, check if any optional attributes are returned
					captiveportal_reapply_attributes($cpentry, $auth_list);
			}
		}
	}

	if ($voucher_needs_sync == true)
		/* Triger a sync of the vouchers on config */
		send_event("service sync vouchers");

	/* write database */
	if (!empty($unsetindexes))
		captiveportal_write_db($cpdb, false, $unsetindexes);
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
	
	if (is_ipaddr($dbent[2])) {
		/* Delete client's ip entry from tables 3 and 4. */
		mwexec("/sbin/ipfw table 1 delete {$dbent[2]}");
		mwexec("/sbin/ipfw table 2 delete {$dbent[2]}");
		/* XXX: Redundant?! Ensure all pf(4) states are killed. */
		mwexec("pfctl -k {$dbent[2]}");
		mwexec("pfctl -K {$dbent[2]}");
	}

	/* 
	* These are the pipe numbers we use to control traffic shaping for each logged in user via captive portal
	* We could get an error if the pipe doesn't exist but everything should still be fine
	*/
	if (isset($config['captiveportal']['peruserbw'])) {
		mwexec("/sbin/ipfw pipe " . ($dbent[1]+20000) . " delete");
		mwexec("/sbin/ipfw pipe " . ($dbent[1]+20001) . " delete");
	}

	/* Release the ruleno so it can be reallocated to new clients. */
	captiveportal_free_ipfw_ruleno($dbent[1]);

	// XMLRPC Call over to the master Voucher node
	if(!empty($config['voucher']['vouchersyncdbip'])) {
		$syncip   = $config['voucher']['vouchersyncdbip'];
		$syncport = $config['voucher']['vouchersyncport'];
		$syncpass = $config['voucher']['vouchersyncpass'];
		$vouchersyncusername = $config['voucher']['vouchersyncusername'];
		$remote_status = xmlrpc_sync_voucher_disconnect($dbent, $syncip, $syncport, $syncpass, $vouchersyncusername, $term_cause, $stop_time);
	}

}

/* remove a single client by sessionid */
function captiveportal_disconnect_client($sessionid, $term_cause = 1, $logoutReason = "LOGOUT") {
	global $g, $config;

	$radiusservers = captiveportal_get_radius_servers();
	$unsetindex = array();

	/* read database */
	$cpdb = captiveportal_read_db();

	/* find entry */
	if (isset($cpdb[$sessionid])) {
		$cpentry = $cpdb[$sessionid];
		/* write database */
		$unsetindex[] = $sessionid;
		captiveportal_write_db($cpdb, false, $unsetindex);

		captiveportal_disconnect($cpentry, $radiusservers, $term_cause);
		captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");
	}		
}

/* send RADIUS acct stop for all current clients */
function captiveportal_radius_stop_all() {
	global $config;

	if (!isset($config['captiveportal']['radacct_enable']))
		return;

	$radiusservers = captiveportal_get_radius_servers();
	if (!empty($radiusservers)) {
		$cpdb = captiveportal_read_db();
		foreach ($cpdb as $cpentry) {
			RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
				$cpentry[4], // username
				$cpentry[5], // sessionid
				$cpentry[0], // start time
				$radiusservers,
				$cpentry[2], // clientip
				$cpentry[3], // clientmac
				7); // Admin Reboot
		}
	}
}

function captiveportal_passthrumac_configure_entry($macent) {
	$rules = "";
	$enBwup = isset($macent['bw_up']);
	$enBwdown = isset($macent['bw_down']);
	$actionup = "allow";
	$actiondown = "allow";

	$ruleno = captiveportal_get_next_ipfw_ruleno(2000, 49899, true);

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
	$rules .= "add {$ruleno} {$actiondown} ip from any to any MAC {$macent['mac']} any\n";
	$ruleno++;
	$rules .= "add {$ruleno} {$actionup} ip from any to any MAC any {$macent['mac']}\n";

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

function captiveportal_passthrumac_findbyname($username) {
	global $config;

	if (is_array($config['captiveportal']['passthrumac'])) {
		foreach ($config['captiveportal']['passthrumac'] as $macent) {
			if ($macent['username'] == $username)
				return $macent;
		}
	}
	return NULL;
}

/* 
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 * table (5=IN)/(6=OUT) hold allowed ip's with bw limit.
 */
function captiveportal_allowedip_configure_entry($ipent) {

	/* This function can deal with hostname or ipaddress */
	if($ipent['ip']) 	
		$ipaddress = $ipent['ip'];

	/*  Instead of copying this entire function for something
	 *  easy such as hostname vs ip address add this check
	 */
	if($ipent['hostname']) {
		$ipaddress = gethostbyname($ipent['hostname']);
		if(!is_ipaddr($ipaddress)) 
			return;
	}

	$rules = "";
	$enBwup = intval($ipent['bw_up']);
	$enBwdown = intval($ipent['bw_down']);
	$bw_up = "";
	$bw_down = "";
	$tablein = array();
	$tableout = array();

	if (intval($enBwup) > 0 or intval($enBwdown) > 0)
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
	$subnet = "";
	if (!empty($ipent['sn']))
		$subnet = "/{$ipent['sn']}";
	foreach ($tablein as $table)
		$rules .= "table {$table} add {$ipaddress}{$subnet} {$bw_up}\n";
	if ($enBwdown) {
		$bw_down = $ruleno + 20001;
		$rules .= "pipe {$bw_down} config bw {$ipent['bw_down']}Kbit/s queue 100\n";
	}
	foreach ($tableout as $table)
		$rules .= "table {$table} add {$ipaddress}{$subnet} {$bw_down}\n";

	return $rules;
}

/* 
	Adds a dnsfilter entry and watches for hostname changes.
	A change results in reloading the ruleset.
*/
function setup_dnsfilter_entries() {
	global $g, $config;

	$cp_filterdns_filename = "{$g['varetc_path']}/filterdns-captiveportal.conf";
	$cp_filterdns_conf = "";
	if (is_array($config['captiveportal']['allowedhostname'])) {
		foreach ($config['captiveportal']['allowedhostname'] as $hostnameent)  {
			$cp_filterdns_conf .= "ipfw {$hostnameent['hostname']} 3\n";
			$cp_filterdns_conf .= "ipfw {$hostnameent['hostname']} 4\n";
			$cp_filterdns_conf .= "ipfw {$hostnameent['hostname']} 7\n";
			$cp_filterdns_conf .= "ipfw {$hostnameent['hostname']} 8\n";
		}
	}
	file_put_contents($cp_filterdns_filename, $cp_filterdns_conf);
	killbypid("{$g['tmp_path']}/filterdns-cpah.pid");
	mwexec("/usr/local/sbin/filterdns -p {$g['tmp_path']}/filterdns-cpah.pid -i 300 -c {$cp_filterdns_filename} -d 1");
}

function captiveportal_allowedhostname_configure() {
	global $config, $g;

	$rules = "\n# captiveportal_allowedhostname_configure()\n";
	setup_dnsfilter_entries();
	if (is_array($config['captiveportal']['allowedhostname'])) {
		foreach ($config['captiveportal']['allowedhostname'] as $hostnameent) 
			$rules .= captiveportal_allowedip_configure_entry($hostnameent);
	}
	return $rules;
}

function captiveportal_allowedip_configure() {
	global $config, $g;

	$rules = "";
	if (is_array($config['captiveportal']['allowedip'])) {
		foreach ($config['captiveportal']['allowedip'] as $ipent) 
			$rules .= captiveportal_allowedip_configure_entry($ipent);
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

function captiveportal_init_radius_servers() {
	global $config, $g;

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

		$cprdsrvlck = lock('captiveportalradius', LOCK_EX);
		$fd = @fopen("{$g['vardb_path']}/captiveportal_radius.db", "w");
		if (!$fd) {
			captiveportal_syslog("Error: cannot open radius DB file in captiveportal_configure().\n");
			unlock($cprdsrvlck);
			return 1;
		} else if (isset($radiusip2, $radiuskey2))
			fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey . "\n"
			. $radiusip2 . "," . $radiusport2 . "," . $radiusacctport . "," . $radiuskey2);
		else
			fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey);
		fclose($fd);
		unlock($cprdsrvlck);
	}
}

/* read RADIUS servers into array */
function captiveportal_get_radius_servers() {
	global $g;

	$cprdsrvlck = lock('captiveportalradius');
	if (file_exists("{$g['vardb_path']}/captiveportal_radius.db")) {
		$radiusservers = array();
		$cpradiusdb = file("{$g['vardb_path']}/captiveportal_radius.db", 
		FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($cpradiusdb) {
			foreach($cpradiusdb as $cpradiusentry) {
				$line = trim($cpradiusentry);
				if ($line) {
					$radsrv = array();
					list($radsrv['ipaddr'],$radsrv['port'],$radsrv['acctport'],$radsrv['key']) = explode(",",$line);
					$radiusservers[] = $radsrv;
				}
			}
		}
		unlock($cprdsrvlck);
		return $radiusservers;
	}

	unlock($cprdsrvlck);
	return false;
}

/* log successful captive portal authentication to syslog */
/* part of this code from php.net */
function captiveportal_logportalauth($user,$mac,$ip,$status, $message = null) {
	// Log it
	if (!$message)
		$message = "$status: $user, $mac, $ip";
	else {
		$message = trim($message);
		$message = "$status: $user, $mac, $ip, $message";
	}
	captiveportal_syslog($message);
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

	$ruleno = captiveportal_get_next_ipfw_ruleno();

	/* If the pool is empty, return appropriate message and fail authentication */
	if (is_null($ruleno)) {
		$auth_list = array();
		$auth_list['auth_val'] = 1;
		$auth_list['error'] = "System reached maximum login capacity";
		return $auth_list;
	}

	$radiusservers = captiveportal_get_radius_servers();

	$auth_list = RADIUS_AUTHENTICATION($username,
		$password,
		$radiusservers,
		$clientip,
		$clientmac,
		$ruleno);

	if ($auth_list['auth_val'] == 2) {
		captiveportal_logportalauth($username,$clientmac,$clientip,$type);
		$sessionid = portal_allow($clientip,
			$clientmac,
			$username,
			$password,
			$auth_list,
			$ruleno);
	}

	return $auth_list;
}

/* read captive portal DB into array */
function captiveportal_read_db($locked = false, $index = 5 /* sessionid by default */) {
	global $g;

	$cpdb = array();

	if ($locked == false)
		$cpdblck = lock('captiveportaldb');
	$fd = @fopen("{$g['vardb_path']}/captiveportal.db", "r");
	if ($fd) {
		while (!feof($fd)) {
			$line = trim(fgets($fd));
			if ($line) {
				$cpe = explode(",", $line);
				/* Hash by session id */
				$cpdb[$cpe[$index]] = $cpe;
			}
		}
		fclose($fd);
	}
	if ($locked == false)
		unlock($cpdblck);
	return $cpdb;
}

/* write captive portal DB */
function captiveportal_write_db($cpdb, $locked = false, $remove = false) {
	global $g;

	if ($locked == false)
		$cpdblck = lock('captiveportaldb', LOCK_EX);

	if (is_array($remove)) {
		if (!empty($remove)) {
			$cpdb = captiveportal_read_db(true);
			foreach ($remove as $key) {
				if (is_array($key))
					log_error("Captive portal Array passed as unset index: " . print_r($key, true));
				else
					unset($cpdb[$key]);
			}
		} else {
			if ($locked == false)
				unlock($cpdblck);
			return; //This makes sure no record removal calls
		}
	}
	$fd = @fopen("{$g['vardb_path']}/captiveportal.db", "w");
	if ($fd) {
		foreach ($cpdb as $cpent) {
			fwrite($fd, join(",", $cpent) . "\n");
		}
		fclose($fd);
	}
	if ($locked == false)
		unlock($cpdblck);
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

	$cpruleslck = lock('captiveportalrules', LOCK_EX);
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
				if (isset($config['captiveportal']['peruserbw']) || $usebw == true)
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
	unlock($cpruleslck);
	return $ruleno;
}

function captiveportal_free_ipfw_ruleno($ruleno, $usedbw = false) {
	global $config, $g;

	if(!isset($config['captiveportal']['enable']))
		return NULL;

	$cpruleslck = lock('captiveportalrules', LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportal.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal.rules"));
		$rules[$ruleno] = false;
		if (isset($config['captiveportal']['peruserbw']) || $usedbw == true)
			$rules[++$ruleno] = false;
		file_put_contents("{$g['vardb_path']}/captiveportal.rules", serialize($rules));
	}
	unlock($cpruleslck);
}

function captiveportal_get_ipfw_passthru_ruleno($value) {
	global $config, $g;

	if(!isset($config['captiveportal']['enable']))
		return NULL;

	$cpruleslck = lock('captiveportalrules', LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportal.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal.rules"));
		$ruleno = intval(`/sbin/ipfw show | /usr/bin/grep {$value} |  /usr/bin/grep -v grep | /usr/bin/cut -d " " -f 1 | /usr/bin/head -n 1`);
		if ($rules[$ruleno]) {
			unlock($cpruleslck);
			return $ruleno;
		}
	}

	unlock($cpruleslck);
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
	global $config;

	if (empty($config['captiveportal']['radiussrcip_attribute'])) {
			$nasIp = get_interface_ip();
	} else {
		if (is_ipaddr($config['captiveportal']['radiussrcip_attribute']))
			$nasIp = $config['captiveportal']['radiussrcip_attribute'];
		else
			$nasIp = get_interface_ip($config['captiveportal']['radiussrcip_attribute']);
	}
		
	if(!is_ipaddr($nasIp))
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

	$iface = exec_command("/sbin/route -n get {$cliip} | /usr/bin/awk '/interface/ { print \$2; };'");
	$iface = trim($iface, "\n");
	if (!empty($iface)) {
		$ip = find_interface_ip($iface);
		if (is_ipaddr($ip))
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

/* functions move from index.php */

function portal_reply_page($redirurl, $type = null, $message = null, $clientmac = null, $clientip = null, $username = null, $password = null) {
	global $g, $config;

	/* Get captive portal layout */
	if ($type == "redir") {
		header("Location: {$redirurl}");
		return;
	} else if ($type == "login")
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal.html");
	else
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-error.html");

	/* substitute the PORTAL_REDIRURL variable */
	if ($config['captiveportal']['preauthurl']) {
		$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$config['captiveportal']['preauthurl']}", $htmltext);
		$htmltext = str_replace("#PORTAL_REDIRURL#", "{$config['captiveportal']['preauthurl']}", $htmltext);
	}

	/* substitute other variables */
	if (isset($config['captiveportal']['httpslogin'])) {
		$htmltext = str_replace("\$PORTAL_ACTION\$", "https://{$config['captiveportal']['httpsname']}:8001/", $htmltext);
		$htmltext = str_replace("#PORTAL_ACTION#", "https://{$config['captiveportal']['httpsname']}:8001/", $htmltext);
	} else {
		$ifip = portal_ip_from_client_ip($clientip);
		if (!$ifip)
			$ourhostname = $config['system']['hostname'] . ":8000";
		else
			$ourhostname = "{$ifip}:8000";
		$htmltext = str_replace("\$PORTAL_ACTION\$", "http://{$ourhostname}/", $htmltext);
		$htmltext = str_replace("#PORTAL_ACTION#", "http://{$ourhostname}/", $htmltext);
	}

	$htmltext = str_replace("\$PORTAL_REDIRURL\$", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("\$PORTAL_MESSAGE\$", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("\$CLIENT_MAC\$", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("\$CLIENT_IP\$", htmlspecialchars($clientip), $htmltext);

	// Special handling case for captive portal master page so that it can be ran 
	// through the PHP interpreter using the include method above.  We convert the
	// $VARIABLE$ case to #VARIABLE# in /etc/inc/captiveportal.inc before writing out.
	$htmltext = str_replace("#PORTAL_REDIRURL#", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("#PORTAL_MESSAGE#", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("#CLIENT_MAC#", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("#CLIENT_IP#", htmlspecialchars($clientip), $htmltext);
	$htmltext = str_replace("#USERNAME#", htmlspecialchars($username), $htmltext);
	$htmltext = str_replace("#PASSWORD#", htmlspecialchars($password), $htmltext);

    echo $htmltext;
}

function portal_mac_radius($clientmac,$clientip) {
    global $config ;

    $radmac_secret = $config['captiveportal']['radmac_secret'];

    /* authentication against the radius server */
    $username = mac_format($clientmac);
    $auth_list = radius($username,$radmac_secret,$clientip,$clientmac,"MACHINE LOGIN");
    if ($auth_list['auth_val'] == 2)
        return TRUE;
    if (!empty($auth_list['url_redirection']))
	portal_reply_page($auth_list['url_redirection'], "redir");

    return FALSE;
}

function captiveportal_reapply_attributes($cpentry, $attributes) {
	global $config, $g;

	/* Add rules for traffic shaping
	 * We don't need to add extra rules since traffic will pass due to the following kernel option
	 * net.inet.ip.fw.one_pass: 1
	 */
	$peruserbw = isset($config['captiveportal']['peruserbw']);

	$bw_up = isset($attributes['bw_up']) ? round(intval($attributes['bw_up'])/1000, 2) : $config['captiveportal']['bwdefaultup'];
	$bw_down = isset($attributes['bw_down']) ? round(intval($attributes['bw_down'])/1000, 2) : $config['captiveportal']['bwdefaultdn'];
	$bw_up_pipeno = $cpentry[1]+20000;
	$bw_down_pipeno = $cpentry[1]+20001;
	$commands = "";

	if ($peruserbw && !empty($bw_up) && is_numeric($bw_up)) {
		$commands .= "pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100\n";

		if (!isset($config['captiveportal']['nomacfilter'])) {
			$commands .= "table 1 del {$cpentry[2]} mac {$cpentry[3]}\n";
			$commands .= "table 1 add {$cpentry[2]} mac {$cpentry[3]} {$bw_up_pipeno}\n";
		} else {
			$commands .= "table 1 del {$cpentry[2]}\n";
			$commands .= "table 1 add {$cpentry[2]} {$bw_up_pipeno}\n";
		}
	}
	if ($peruserbw && !empty($bw_down) && is_numeric($bw_down)) {
		$commands .= "pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100\n";

		if (!isset($config['captiveportal']['nomacfilter'])) {
			$commands .= "table 2 del {$cpentry[2]} mac {$cpentry[3]}\n";
			$commands .= "table 2 add {$cpentry[2]} mac {$cpentry[3]} {$bw_down_pipeno}\n";
		} else {
			$commands .= "table 2 del {$cpentry[2]}\n";
			$commands .= "table 2 add {$cpentry[2]} {$bw_down_pipeno}\n";
		}
	}

	if (!empty($commands)) {
		@file_put_contents("{$g['tmp_path']}/reattribute.rule.tmp", $commands);
		mwexec("/sbin/ipfw -q {$g['tmp_path']}/reattribute.rule.tmp");
		//captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "RADIUS_BANDWIDTH_REAPPLY", "{$bw_up}/{$bw_down}");
	}

	unset($bw_up_pipeno, $bw_Down_pipeno, $bw_up, $bw_down);
}

function portal_allow($clientip,$clientmac,$username,$password = null, $attributes = null, $ruleno = null)  {

	global $redirurl, $g, $config, $type, $passthrumac, $_POST;

	/* See if a ruleno is passed, if not start sessions because this means there isn't one atm */
	if ($ruleno == null)
		$ruleno = captiveportal_get_next_ipfw_ruleno();

	/* if the pool is empty, return appropriate message and exit */
	if (is_null($ruleno)) {
		portal_reply_page($redirurl, "error", "System reached maximum login capacity");
		log_error("WARNING!  Captive portal has reached maximum login capacity");
		exit;
	}

	// Ensure we create an array if we are missing attributes
	if (!is_array($attributes))
		$attributes = array();

	$radiusservers = captiveportal_get_radius_servers();

	/* Do not allow concurrent login execution. */
	$cpdblck = lock('captiveportaldb', LOCK_EX);

	unset($sessionid);

	/* read in client database */
	$cpdb = captiveportal_read_db(true);

	if ($attributes['voucher'])
		$remaining_time = $attributes['session_timeout'];

	$writecfg = false;
	/* Find an existing session */
	if ((isset($config['captiveportal']['noconcurrentlogins'])) && $passthrumac) {
		if (isset($config['captiveportal']['passthrumacadd'])) {
			$mac = captiveportal_passthrumac_findbyname($username);
			if (!empty($mac)) {
				if ($_POST['replacemacpassthru']) {
					foreach ($config['captiveportal']['passthrumac'] as $idx => $macent) {
						if ($macent['mac'] == $mac['mac']) {
							$macrules = "";
							$ruleno = captiveportal_get_ipfw_passthru_ruleno($mac['mac']);
                                			if ($ruleno) {
								captiveportal_free_ipfw_ruleno($ruleno, true);
                                        			$macrules .= "delete {$ruleno}\n";
								++$ruleno;
                                        			$macrules .= "delete {$ruleno}\n";
                                			}
							unset($config['captiveportal']['passthrumac'][$idx]);
							$mac['mac'] = $clientmac;
							$config['captiveportal']['passthrumac'][] = $mac;
							$macrules .= captiveportal_passthrumac_configure_entry($mac);
							file_put_contents("{$g['tmp_path']}/macentry.rules.tmp", $macrules);
							mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.rules.tmp");
							$writecfg = true;
							$sessionid = true;
							break;
						}
					}
                                } else {
					portal_reply_page($redirurl, "error", "Username: {$username} is already authenticated using another MAC address.",
						$clientmac, $clientip, $username, $password);
					exit;
				}
			}
		}
	}

	/* Snaphost the timestamp */
	$allow_time = time();

	foreach ($cpdb as $sid => $cpentry) {
		/* on the same ip */
		if($cpentry[2] == $clientip) {
			if (isset($config['captiveportal']['nomacfilter']) || $cpentry[3] == $clientmac)
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - REUSING OLD SESSION");
			else
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
			$sessionid = $sid;
			break;
		}
		elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
			// user logged in with an active voucher. Check for how long and calculate 
			// how much time we can give him (voucher credit - used time)
			$remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
			if ($remaining_time < 0)    // just in case. 
				$remaining_time = 0;

			/* This user was already logged in so we disconnect the old one */
			captiveportal_disconnect($cpentry,$radiusservers,13);
			captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
			unset($cpdb[$sid]);
			break;
		}
		elseif ((isset($config['captiveportal']['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
			/* on the same username */
			if (strcasecmp($cpentry[4], $username) == 0) {
				/* This user was already logged in so we disconnect the old one */
				captiveportal_disconnect($cpentry,$radiusservers,13);
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
				unset($cpdb[$sid]);
				break;
			}
		}
	}

	if ($attributes['voucher'] && $remaining_time <= 0)
		return 0;       // voucher already used and no time left

	if (!isset($sessionid)) {
		/* generate unique session ID */
		$tod = gettimeofday();
		$sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

		/* Add rules for traffic shaping
		 * We don't need to add extra rules since traffic will pass due to the following kernel option
		 * net.inet.ip.fw.one_pass: 1
		 */
		$peruserbw = isset($config['captiveportal']['peruserbw']);

		$bw_up = isset($attributes['bw_up']) ? round(intval($attributes['bw_up'])/1000, 2) : $config['captiveportal']['bwdefaultup'];
		$bw_down = isset($attributes['bw_down']) ? round(intval($attributes['bw_down'])/1000, 2) : $config['captiveportal']['bwdefaultdn'];

		if ($passthrumac) {
			$mac = array();
			$mac['mac'] = $clientmac;
			if (isset($config['captiveportal']['passthrumacaddusername']))
				$mac['username'] = $username;
			$mac['descr'] =  "Auto added pass-through MAC for user {$username}";
			if (!empty($bw_up))
				$mac['bw_up'] = $bw_up;
			if (!empty($bw_down))
				$mac['bw_down'] = $bw_down;
			if (!is_array($config['captiveportal']['passthrumac']))
				$config['captiveportal']['passthrumac'] = array();
			$config['captiveportal']['passthrumac'][] = $mac;
			unlock($cpdblck);
			$macrules = captiveportal_passthrumac_configure_entry($mac);
			@file_put_contents("{$g['tmp_path']}/macentry.rules.tmp", $macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.rules.tmp");
			$writecfg = true;
		} else {
			if ($peruserbw && !empty($bw_up) && is_numeric($bw_up)) {
				$bw_up_pipeno = $ruleno + 20000;
				//$bw_up /= 1000; // Scale to Kbit/s
				mwexec("/sbin/ipfw pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100");

				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 1 add {$clientip} mac {$clientmac} {$bw_up_pipeno}");
				else
					mwexec("/sbin/ipfw table 1 add {$clientip} {$bw_up_pipeno}");
			} else {
				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 1 add {$clientip} mac {$clientmac}");
				else
					mwexec("/sbin/ipfw table 1 add {$clientip}");
			}
			if ($peruserbw && !empty($bw_down) && is_numeric($bw_down)) {
				$bw_down_pipeno = $ruleno + 20001;
				//$bw_down /= 1000; // Scale to Kbit/s
				mwexec("/sbin/ipfw pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100");

				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 2 add {$clientip} mac {$clientmac} {$bw_down_pipeno}");
				else
					mwexec("/sbin/ipfw table 2 add {$clientip} {$bw_down_pipeno}");
			} else {
				if (!isset($config['captiveportal']['nomacfilter']))
					mwexec("/sbin/ipfw table 2 add {$clientip} mac {$clientmac}");
				else
					mwexec("/sbin/ipfw table 2 add {$clientip}");
			}

			if ($attributes['voucher'])
				$attributes['session_timeout'] = $remaining_time;

			/* encode password in Base64 just in case it contains commas */
			$bpassword = base64_encode($password);
			$cpdb[] = array($allow_time, $ruleno, $clientip, $clientmac, $username, $sessionid, $bpassword,
				$attributes['session_timeout'], $attributes['idle_timeout'], $attributes['session_terminate_time']);

			/* rewrite information to database */
			captiveportal_write_db($cpdb, true);
			unlock($cpdblck);

			if (isset($config['captiveportal']['radacct_enable']) && !empty($radiusservers)) {
				$acct_val = RADIUS_ACCOUNTING_START($ruleno,
                                		$username, $sessionid, $radiusservers, $clientip, $clientmac);
				if ($acct_val == 1)
					captiveportal_logportalauth($username,$clientmac,$clientip,$type,"RADIUS ACCOUNTING FAILED");
			}
		}
	} else
		unlock($cpdblck);

	if ($writecfg == true)
		write_config();

	/* redirect user to desired destination */
	if (!empty($attributes['url_redirection']))
		$my_redirurl = $attributes['url_redirection'];
	else if (!empty($config['captiveportal']['redirurl']))
		$my_redirurl = $config['captiveportal']['redirurl'];
	else
		$my_redirurl = $redirurl;

	if(isset($config['captiveportal']['logoutwin_enable']) && !$passthrumac) {

		if (isset($config['captiveportal']['httpslogin']))
			$logouturl = "https://{$config['captiveportal']['httpsname']}:8001/";
		else {
			$ifip = portal_ip_from_client_ip($clientip);
			if (!$ifip)
				$ourhostname = $config['system']['hostname'] . ":8000";
			else
				$ourhostname = "{$ifip}:8000";
			$logouturl = "http://{$ourhostname}/";
		}

		if (isset($attributes['reply_message']))
			$message = $attributes['reply_message'];
		else
			$message = 0;

		include("{$g['varetc_path']}/captiveportal-logout.html");

	} else {
		header("Location: " . $my_redirurl);
	}

	return $sessionid;
}

/*
 * Used for when pass-through credits are enabled.
 * Returns true when there was at least one free login to deduct for the MAC.
 * Expired entries are removed as they are seen.
 * Active entries are updated according to the configuration.
 */
function portal_consume_passthrough_credit($clientmac) {
	global $config;

	if (!empty($config['captiveportal']['freelogins_count']) && is_numeric($config['captiveportal']['freelogins_count']))
		$freeloginscount = $config['captiveportal']['freelogins_count'];
	else
		return false;

	if (!empty($config['captiveportal']['freelogins_resettimeout']) && is_numeric($config['captiveportal']['freelogins_resettimeout']))
		$resettimeout = $config['captiveportal']['freelogins_resettimeout'];
	else
		return false;

	if ($freeloginscount < 1 || $resettimeout <= 0 || !$clientmac)
		return false;

	$updatetimeouts = isset($config['captiveportal']['freelogins_updatetimeouts']);

	/*
	 * Read database of used MACs.  Lines are a comma-separated list
	 * of the time, MAC, then the count of pass-through credits remaining.
	 */
	$usedmacs = captiveportal_read_usedmacs_db();

	$currenttime = time();
	$found = false;
	foreach ($usedmacs as $key => $usedmac) {
		$usedmac = explode(",", $usedmac);

		if ($usedmac[1] == $clientmac) {
			if ($usedmac[0] + ($resettimeout * 3600) > $currenttime) {
				if ($usedmac[2] < 1) {
					if ($updatetimeouts) {
						$usedmac[0] = $currenttime;
						unset($usedmacs[$key]);
						$usedmacs[] = implode(",", $usedmac);
						captiveportal_write_usedmacs_db($usedmacs);
					}

					return false;
				} else {
					$usedmac[2] -= 1;
					$usedmacs[$key] = implode(",", $usedmac);
				}

				$found = true;
			} else
				unset($usedmacs[$key]);

			break;
		} else if ($usedmac[0] + ($resettimeout * 3600) <= $currenttime)
				unset($usedmacs[$key]);
	}

	if (!$found) {
		$usedmac = array($currenttime, $clientmac, $freeloginscount - 1);
		$usedmacs[] = implode(",", $usedmac);
	}

	captiveportal_write_usedmacs_db($usedmacs);
	return true;
}

function captiveportal_read_usedmacs_db() {
	global $g;

	$cpumaclck = lock('captiveusedmacs');
	if (file_exists("{$g['vardb_path']}/captiveportal_usedmacs.db")) {
		$usedmacs = file("{$g['vardb_path']}/captiveportal_usedmacs.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$usedmacs)
			$usedmacs = array();
	} else
		$usedmacs = array();

	unlock($cpumaclck);
	return $usedmacs;
}

function captiveportal_write_usedmacs_db($usedmacs) {
	global $g;

	$cpumaclck = lock('captiveusedmacs', LOCK_EX);
	@file_put_contents("{$g['vardb_path']}/captiveportal_usedmacs.db", implode("\n", $usedmacs));
	unlock($cpumaclck);
}

?>
