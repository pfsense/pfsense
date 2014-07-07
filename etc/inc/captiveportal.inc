<?php
/*
	captiveportal.inc
	part of pfSense (https://www.pfsense.org)
	Copyright (C) 2004-2011 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2009-2012 Ermal Lu�i <eri@pfsense.org>
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
	
	pfSense_BUILDER_BINARIES:	/sbin/ipfw	/sbin/route
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
	global $config, $g, $cpzone;

	$htmltext = <<<EOD
<html> 
<body> 
<form method="post" action="\$PORTAL_ACTION\$"> 
	<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
	<input name="zone" type="hidden" value="\$PORTAL_ZONE\$">
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
								<br />
								<div id='loginbox'>
								<table>
									<tr><td colspan="2"><center>Welcome to the {$g['product_name']} Captive Portal!</td></tr>
									<tr><td>&nbsp;</td></tr>
									<tr><td align="right">Username:</td><td><input name="auth_user" type="text" style="border: 1px dashed;"></td></tr>
									<tr><td align="right">Password:</td><td><input name="auth_pass" type="password" style="border: 1px dashed;"></td></tr>
									<tr><td>&nbsp;</td></tr>

EOD;

	if(isset($config['voucher'][$cpzone]['enable'])) {
	$htmltext .= <<<EOD
									<tr>
										<td align="right">Enter Voucher Code: </td>
										<td><input name="auth_voucher" type="text" style="border:1px dashed;" size="22"></td>
									</tr>

EOD;
	}

	$htmltext .= <<<EOD
									<tr>
										<td colspan="2"><center><input name="accept" type="submit" value="Continue"></center></td>
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

function captiveportal_load_modules() {
	global $config;

	mute_kernel_msgs();
	if (!is_module_loaded("ipfw.ko")) {
		mwexec("/sbin/kldload ipfw");
		/* make sure ipfw is not on pfil hooks */
		set_sysctl(array(
			"net.inet.ip.pfil.inbound" => "pf", "net.inet6.ip6.pfil.inbound" => "pf",
			"net.inet.ip.pfil.outbound" => "pf", "net.inet6.ip6.pfil.outbound" => "pf")
		);
	}
	/* Activate layer2 filtering */
	set_sysctl(array("net.link.ether.ipfw" => "1", "net.inet.ip.fw.one_pass" => "1"));

	/* Always load dummynet now that even allowed ip and mac passthrough use it. */
	if (!is_module_loaded("dummynet.ko")) {
		mwexec("/sbin/kldload dummynet");
		set_sysctl(array("net.inet.ip.dummynet.io_fast" => "1", "net.inet.ip.dummynet.hash_size" => "256"));
	}
	unmute_kernel_msgs();
}

function captiveportal_configure() {
	global $config, $cpzone, $cpzoneid;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpkey => $cp) {
			$cpzone = $cpkey;
			$cpzoneid = $cp['zoneid'];
			captiveportal_configure_zone($cp);
		}
	}
}

function captiveportal_configure_zone($cpcfg) {
	global $config, $g, $cpzone, $cpzoneid;

	$captiveportallck = lock("captiveportal{$cpzone}", LOCK_EX);
	
	if (isset($cpcfg['enable'])) {

		if ($g['booting']) {
			echo "Starting captive portal({$cpcfg['zone']})... ";

			/* remove old information */
			unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		} else
			captiveportal_syslog("Reconfiguring captive portal({$cpcfg['zone']}).");

		/* init ipfw rules */
		captiveportal_init_rules(true);

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");

		/* initialize minicron interval value */
		$croninterval = $cpcfg['croninterval'] ? $cpcfg['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10))
			$croninterval = 60;

		/* write portal page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['htmltext'])
			$htmltext = base64_decode($cpcfg['page']['htmltext']);
		else {
			/* example/template page */
			$htmltext = get_default_captive_portal_html();
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal_{$cpzone}.html", "w");
		if ($fd) {
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$htmltext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $htmltext);
			$htmltext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $htmltext);
			$htmltext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $htmltext);
			$htmltext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $htmltext);
			$htmltext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $htmltext);
			$htmltext = str_replace("\$ORIGINAL_PORTAL_IP\$", "#ORIGINAL_PORTAL_IP#", $htmltext);
			$htmltext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $htmltext);
			if($cpcfg['preauthurl']) {
				$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
				$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
			}
			fwrite($fd, $htmltext);
			fclose($fd);
		}
		unset($htmltext);

		/* write error page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['errtext'])
			$errtext = base64_decode($cpcfg['page']['errtext']);
		else {
			/* example page  */
			$errtext = get_default_captive_portal_html();
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html", "w");
		if ($fd) {
			// Special case handling.  Convert so that we can pass this page
			// through the PHP interpreter later without clobbering the vars.
			$errtext = str_replace("\$PORTAL_ZONE\$", "#PORTAL_ZONE#", $errtext);
			$errtext = str_replace("\$PORTAL_REDIRURL\$", "#PORTAL_REDIRURL#", $errtext);
			$errtext = str_replace("\$PORTAL_MESSAGE\$", "#PORTAL_MESSAGE#", $errtext);
			$errtext = str_replace("\$CLIENT_MAC\$", "#CLIENT_MAC#", $errtext);
			$errtext = str_replace("\$CLIENT_IP\$", "#CLIENT_IP#", $errtext);
			$errtext = str_replace("\$ORIGINAL_PORTAL_IP\$", "#ORIGINAL_PORTAL_IP#", $errtext);
			$errtext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $errtext);
			if($cpcfg['preauthurl']) {
				$errtext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $errtext);
				$errtext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $errtext);
			}
			fwrite($fd, $errtext);
			fclose($fd);
		}
		unset($errtext);

		/* write logout page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['logouttext'])
			$logouttext = base64_decode($cpcfg['page']['logouttext']);
		else {
			/* example page */
			$logouttext = <<<EOD
<html>
<head><title>Redirecting...</title></head>
<body>
<span style="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<b>Redirecting to <a href="<?=\$my_redirurl;?>"><?=\$my_redirurl;?></a>...</b>
</span>
<script type="text/javascript">
//<![CDATA[
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
	LogoutWin.document.write('<html>');
	LogoutWin.document.write('<head><title>Logout</title></head>') ;
	LogoutWin.document.write('<body bgcolor="#435370">');
	LogoutWin.document.write('<div align="center" style="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
	LogoutWin.document.write('<b>Click the button below to disconnect</b><p />');
	LogoutWin.document.write('<form method="POST" action="<?=\$logouturl;?>">');
	LogoutWin.document.write('<input name="logout_id" type="hidden" value="<?=\$sessionid;?>" />');
	LogoutWin.document.write('<input name="zone" type="hidden" value="<?=\$cpzone;?>" />');
	LogoutWin.document.write('<input name="logout" type="submit" value="Logout" />');
	LogoutWin.document.write('</form>');
	LogoutWin.document.write('</div></body>');
	LogoutWin.document.write('</html>');
	LogoutWin.document.close();
}

document.location.href="<?=\$my_redirurl;?>";
//]]>
</script>
</body>
</html>

EOD;
		}

		$fd = @fopen("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html", "w");
		if ($fd) {
			fwrite($fd, $logouttext);
			fclose($fd);
		}
		unset($logouttext);

		/* write elements */
		captiveportal_write_elements();

		/* kill any running mini_httpd */
		killbypid("{$g['varrun_path']}/lighty-{$cpzone}-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/lighty-{$cpzone}-CaptivePortal-SSL.pid");

		/* start up the webserving daemon */
		captiveportal_init_webgui_zone($cpcfg);

		/* Kill any existing prunecaptiveportal processes */
		if (file_exists("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid"))
			killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/cp_prunedb_{$cpzone}.pid " .
			"/etc/rc.prunecaptiveportal {$cpzone}");

		/* generate radius server database */
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
		captiveportal_init_radius_servers();

		if ($g['booting']) {
			/* send Accounting-On to server */
			captiveportal_send_server_accounting();
			echo "done\n";
		}

	} else {
		killbypid("{$g['varrun_path']}/lighty-{$cpzone}-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/lighty-{$cpzone}-CaptivePortal-SSL.pid");
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
		@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

		captiveportal_radius_stop_all();

		/* send Accounting-Off to server */
		if (!$g['booting']) {
			captiveportal_send_server_accounting(true);
		}

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");
		/* Release allocated pipes for this zone */
		captiveportal_free_dnrules();

		mwexec("/sbin/ipfw zone {$cpzoneid} destroy", true);

		if (empty($config['captiveportal']))
			set_single_sysctl("net.link.ether.ipfw", "0");
		else {
			/* Deactivate ipfw(4) if not needed */
			$cpactive = false;
			if (is_array($config['captiveportal'])) {
				foreach ($config['captiveportal'] as $cpkey => $cp) {
					if (isset($cp['enable'])) {
						$cpactive = true;
						break;
					}
				}
			}
			if ($cpactive === false)
				set_single_sysctl("net.link.ether.ipfw", "0");

		}
	}

	unlock($captiveportallck);
	
	return 0;
}

function captiveportal_init_webgui() {
	global $config, $cpzone;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpkey => $cp) {
			$cpzone = $cpkey;
			captiveportal_init_webgui_zone($cp);
		}
	}
}

function captiveportal_init_webgui_zonename($zone) {
	global $config, $cpzone;
	
	if (isset($config['captiveportal'][$zone])) {
		$cpzone = $zone;
		captiveportal_init_webgui_zone($config['captiveportal'][$zone]);
	}
}

function captiveportal_init_webgui_zone($cpcfg) {
	global $g, $config, $cpzone;

	if (!isset($cpcfg['enable']))
		return;

	if (isset($cpcfg['httpslogin'])) {
		$cert = lookup_cert($cpcfg['certref']);
		$crt = base64_decode($cert['crt']);
		$key = base64_decode($cert['prv']);
		$ca = ca_chain($cert);

		/* generate lighttpd configuration */
		if (!empty($cpcfg['listenporthttps']))
			$listenporthttps = $cpcfg['listenporthttps'];
		else
			$listenporthttps = 8001 + $cpcfg['zoneid'];
		system_generate_lighty_config("{$g['varetc_path']}/lighty-{$cpzone}-CaptivePortal-SSL.conf",
			$crt, $key, $ca, "lighty-{$cpzone}-CaptivePortal-SSL.pid", $listenporthttps, "/usr/local/captiveportal",
			"cert-{$cpzone}-portal.pem", "ca-{$cpzone}-portal.pem", $cpzone);
	}

	/* generate lighttpd configuration */
	if (!empty($cpcfg['listenporthttp']))
		$listenporthttp = $cpcfg['listenporthttp'];
	else
		$listenporthttp = 8000 + $cpcfg['zoneid'];
	system_generate_lighty_config("{$g['varetc_path']}/lighty-{$cpzone}-CaptivePortal.conf",
		"", "", "", "lighty-{$cpzone}-CaptivePortal.pid", $listenporthttp, "/usr/local/captiveportal",
		"", "", $cpzone);

	@unlink("{$g['varrun']}/lighty-{$cpzone}-CaptivePortal.pid");
	/* attempt to start lighttpd */
	$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-{$cpzone}-CaptivePortal.conf");

	/* fire up https instance */
	if (isset($cpcfg['httpslogin'])) {
		@unlink("{$g['varrun']}/lighty-{$cpzone}-CaptivePortal-SSL.pid");
		$res = mwexec("/usr/local/sbin/lighttpd -f {$g['varetc_path']}/lighty-{$cpzone}-CaptivePortal-SSL.conf");
	}
}

/* reinit will disconnect all users, be careful! */
function captiveportal_init_rules($reinit = false) {
	global $config, $g, $cpzone, $cpzoneid;

	if (!isset($config['captiveportal'][$cpzone]['enable']))
		return;

	captiveportal_load_modules();
	mwexec("/sbin/ipfw zone {$cpzoneid} create", true);

	$cpips = array();
	$ifaces = get_configured_interface_list();
	$cpinterfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
	$firsttime = 0;
	foreach ($cpinterfaces as $cpifgrp) {
		if (!isset($ifaces[$cpifgrp]))
			continue;
		$tmpif = get_real_interface($cpifgrp);
		if (!empty($tmpif)) {
			$cpipm = get_interface_ip($cpifgrp);
			if (is_ipaddr($cpipm)) {
				$carpif = link_ip_to_carp_interface($cpipm);
				if (!empty($carpif)) {
					$carpsif = explode(" ", $carpif);
					foreach ($carpsif as $cpcarp) {
						mwexec("/sbin/ipfw zone {$cpzoneid} madd {$cpcarp}", true);
						$carpip = find_interface_ip($cpcarp);
						if (is_ipaddr($carpip))
							$cpips[] = $carpip;
					}
				}
				$cpips[] = $cpipm;
			}
			mwexec("/sbin/ipfw zone {$cpzoneid} madd {$tmpif}", true);
		}
	}
	if (count($cpips) > 0) {
		$cpactive = true;
	} else
		return false;

	if ($reinit == false)
		$captiveportallck = lock("captiveportal{$cpzone}");

	$cprules =	"add 65291 allow pfsync from any to any\n";
	$cprules .= "add 65292 allow carp from any to any\n";

	$cprules .= <<<EOD
# layer 2: pass ARP
add 65301 pass layer2 mac-type arp,rarp
# pfsense requires for WPA
add 65302 pass layer2 mac-type 0x888e,0x88c7
# PPP Over Ethernet Session Stage/Discovery Stage
add 65303 pass layer2 mac-type 0x8863,0x8864

# layer 2: block anything else non-IP(v4/v6)
add 65307 deny layer2 not mac-type ip,ipv6

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
	$cprules .= "add {$rulenum} pass ip from any to {$ips} in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pass ip from {$ips} to any out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pass icmp from {$ips} to any out icmptype 0\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pass icmp from any to {$ips} in icmptype 8 \n";
	$rulenum++;
	/* Allowed ips */
	$cprules .= "add {$rulenum} pipe tablearg ip from table(3) to any in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from any to table(4) in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from table(3) to any out\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from any to table(4) out\n";
	$rulenum++;

	/* Authenticated users rules. */
	$cprules .= "add {$rulenum} pipe tablearg ip from table(1) to any in\n";
	$rulenum++;
	$cprules .= "add {$rulenum} pipe tablearg ip from any to table(2) out\n";
	$rulenum++;

	if (!empty($config['captiveportal'][$cpzone]['listenporthttp']))
		$listenporthttp = $config['captiveportal'][$cpzone]['listenporthttp'];
	else
		$listenporthttp = 8000 + $cpzoneid;

	if (isset($config['captiveportal'][$cpzone]['httpslogin'])) {
		if (!empty($config['captiveportal'][$cpzone]['listenporthttps']))
			$listenporthttps = $config['captiveportal'][$cpzone]['listenporthttps'];
		else
			$listenporthttps = 8001 + $cpzoneid;
			if (!isset($config['captiveportal'][$cpzone]['nohttpsforwards'])) {
				$cprules .= "add 65531 fwd 127.0.0.1,{$listenporthttps} tcp from any to any dst-port 443 in\n";
			}
	}
	
	$cprules .= <<<EOD

# redirect non-authenticated clients to captive portal
add 65532 fwd 127.0.0.1,{$listenporthttp} tcp from any to any dst-port 80 in 
# let the responses from the captive portal web server back out
add 65533 pass tcp from any to any out
# block everything else
add 65534 deny all from any to any

EOD;

	/* generate passthru mac database */
	$cprules .= captiveportal_passthrumac_configure(true);
	$cprules .= "\n";

	/* allowed ipfw rules to make allowed ip work */
	$cprules .= captiveportal_allowedip_configure();

	/* allowed ipfw rules to make allowed hostnames work */
	$cprules .= captiveportal_allowedhostname_configure();
	
	/* load rules */
	$cprules = "flush\n{$cprules}";
	file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", $cprules);
	mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", true);
	//@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
	unset($cprules, $tmprules);

	if ($reinit == false)
		unlock($captiveportallck);
}

/* 
 * Remove clients that have been around for longer than the specified amount of time
 * db file structure:
 * timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time,interim_interval
 * (password is in Base64 and only saved when reauthentication is enabled)
 */
function captiveportal_prune_old() {
	global $g, $config, $cpzone, $cpzoneid;

	if (empty($cpzone))
		return;

	$cpcfg = $config['captiveportal'][$cpzone];
	$vcpcfg = $config['voucher'][$cpzone];

	/* check for expired entries */
	$idletimeout = 0;
	$timeout = 0;
	if (!empty($cpcfg['timeout']) && is_numeric($cpcfg['timeout']))
		$timeout = $cpcfg['timeout'] * 60;

	if (!empty($cpcfg['idletimeout']) && is_numeric($cpcfg['idletimeout']))
		$idletimeout = $cpcfg['idletimeout'] * 60;

	/* Is there any job to do? */
	if (!$timeout && !$idletimeout && !isset($cpcfg['reauthenticate']) &&
	    !isset($cpcfg['radiussession_timeout']) && !isset($vcpcfg['enable']))
		return;

	$radiussrvs = captiveportal_get_radius_servers();

	/* Read database */
	/* NOTE: while this can be simplified in non radius case keep as is for now */
	$cpdb = captiveportal_read_db();

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
		if (empty($cpentry[11]))
			$cpentry[11] = 'first';
		$radiusservers = $radiussrvs[$cpentry[11]];

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
		if (!$timedout && $uidletimeout > 0) {
			$lastact = captiveportal_get_last_activity($cpentry[2], $cpentry[3]);
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
		if (!$timedout && isset($vcpcfg['enable']) && !empty($cpentry[7])) {
			if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$voucher_needs_sync = true;
			}
		}

		/* if radius session_timeout is enabled and the session_timeout is not null, then check if the user should be logged out */
		if (!$timedout && isset($cpcfg['radiussession_timeout']) && !empty($cpentry[7])) {
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
			if (isset($cpcfg['radacct_enable'])) {
				if ($cpcfg['reauthenticateacct'] == "stopstart") {
					/* stop and restart accounting */
					RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$cpentry[0], // start time
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						10); // NAS Request
					$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XZEROENTRY, 1, $cpentry[2], $cpentry[3]);
					$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XZEROENTRY, 2, $cpentry[2], $cpentry[3]);
					RADIUS_ACCOUNTING_START($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3]); // clientmac
				} else if ($cpcfg['reauthenticateacct'] == "interimupdate") {
					$session_time = $pruning_time - $cpentry[0];
					if (!empty($cpentry[10]) && $cpentry[10] > 60)
						$interval = $cpentry[10];
					else
						$interval = 0;
					$past_interval_min = ($session_time > $interval);
					if ($interval != 0)
						$within_interval = ($session_time % $interval >= 0 && $session_time % $interval <= 59);
					if ($interval === 0 || ($interval > 0 && $past_interval_min && $within_interval)) {
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
			}

			/* check this user against RADIUS again */
			if (isset($cpcfg['reauthenticate'])) {
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
				} else if ($auth_list['auth_val'] == 2)
					captiveportal_reapply_attributes($cpentry, $auth_list);
			}
		}
	}
	unset($cpdb);

	captiveportal_prune_old_automac();

	if ($voucher_needs_sync == true)
		/* Triger a sync of the vouchers on config */
		send_event("service sync vouchers");

	/* write database */
	if (!empty($unsetindexes))
		captiveportal_remove_entries($unsetindexes);
}

function captiveportal_prune_old_automac() {
	global $g, $config, $cpzone, $cpzoneid;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac']) && isset($config['captiveportal'][$cpzone]['passthrumacaddusername'])) {
		$tmpvoucherdb = array();
		$macrules = "";
		$writecfg = false;
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $eid => $emac) {
			if ($emac['logintype'] == "voucher") {
				if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
					if (isset($tmpvoucherdb[$emac['username']])) {
						$temac = $config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]];
						$ruleno = captiveportal_get_ipfw_passthru_ruleno($temac['mac']);
						$pipeno = captiveportal_get_dn_passthru_ruleno($temac['mac']);
						if ($ruleno) {
							captiveportal_free_ipfw_ruleno($ruleno);
							$macrules .= "delete {$ruleno}";
							++$ruleno;
							$macrules .= "delete {$ruleno}";
						}
						if ($pipeno) {
							captiveportal_free_dn_ruleno($pipeno);
							$macrules .= "pipe delete {$pipeno}\n";
							++$pipeno;
							$macrules .= "pipe delete {$pipeno}\n";
						}
						$writecfg = true;
						captiveportal_logportalauth($temac['username'], $temac['mac'], $temac['ip'], "DUPLICATE {$temac['username']} LOGIN - TERMINATING OLD SESSION");
						unset($config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]]);
					}
					$tmpvoucherdb[$emac['username']] = $eid;
				}
				if (voucher_auth($emac['username']) <= 0) {
					$ruleno = captiveportal_get_ipfw_passthru_ruleno($emac['mac']);
					$pipeno = captiveportal_get_dn_passthru_ruleno($emac['mac']);
					if ($ruleno) {
						captiveportal_free_ipfw_ruleno($ruleno);
						$macrules .= "delete {$ruleno}";
						++$ruleno;
						$macrules .= "delete {$ruleno}";
					}
					if ($pipeno) {
						captiveportal_free_dn_ruleno($pipeno);
						$macrules .= "pipe delete {$pipeno}\n";
						++$pipeno;
						$macrules .= "pipe delete {$pipeno}\n";
					}
					$writecfg = true;
					captiveportal_logportalauth($emac['username'], $emac['mac'], $emac['ip'], "EXPIRED {$emac['username']} LOGIN - TERMINATING SESSION");
					unset($config['captiveportal'][$cpzone]['passthrumac'][$eid]);
				}
			}
		}
		unset($tmpvoucherdb);
		if (!empty($macrules)) {
			@file_put_contents("{$g['tmp_path']}/macentry.prunerules.tmp", $macrules);
			unset($macrules);
			mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/macentry.prunerules.tmp");
		}
		if ($writecfg === true)
			write_config("Prune session for auto-added macs");
	}
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $radiusservers,$term_cause = 1,$stop_time = null) {
	global $g, $config, $cpzone, $cpzoneid;

	$stop_time = (empty($stop_time)) ? time() : $stop_time;

	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && !empty($radiusservers)) {
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
		/* Delete client's ip entry from tables 1 and 2. */
		$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XDEL, 1, $dbent[2], $dbent[3]);
		$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XDEL, 2, $dbent[2], $dbent[3]);
		/* XXX: Redundant?! Ensure all pf(4) states are killed. */
		$_gb = @pfSense_kill_states($dbent[2]);
		$_gb = @pfSense_kill_srcstates($dbent[2]);
	}

	/* 
	* These are the pipe numbers we use to control traffic shaping for each logged in user via captive portal
	* We could get an error if the pipe doesn't exist but everything should still be fine
	*/
	if (!empty($dbent[1])) {
		$_gb = @pfSense_pipe_action("pipe delete {$dbent[1]}");
		$_gb = @pfSense_pipe_action("pipe delete " . ($dbent[1]+1));

		/* Release the ruleno so it can be reallocated to new clients. */
		captiveportal_free_dn_ruleno($dbent[1]);
	}

	// XMLRPC Call over to the master Voucher node
	if(!empty($config['voucher'][$cpzone]['vouchersyncdbip'])) {
		$syncip   = $config['voucher'][$cpzone]['vouchersyncdbip'];
		$syncport = $config['voucher'][$cpzone]['vouchersyncport'];
		$syncpass = $config['voucher'][$cpzone]['vouchersyncpass'];
		$vouchersyncusername = $config['voucher'][$cpzone]['vouchersyncusername'];
		$remote_status = xmlrpc_sync_voucher_disconnect($dbent, $syncip, $syncport, $syncpass, $vouchersyncusername, $term_cause, $stop_time);
	}

}

/* remove a single client by sessionid */
function captiveportal_disconnect_client($sessionid, $term_cause = 1, $logoutReason = "LOGOUT") {
	global $g, $config;

	$radiusservers = captiveportal_get_radius_servers();

	/* read database */
	$result = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

	/* find entry */
	if (!empty($result)) {
		captiveportal_write_db("DELETE FROM captiveportal WHERE sessionid = '{$sessionid}'");

		foreach ($result as $cpentry) {
			if (empty($cpentry[11]))
				$cpentry[11] = 'first';
			captiveportal_disconnect($cpentry, $radiusservers[$cpentry[11]], $term_cause);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");
		}
		unset($result);
	}
}

/* send RADIUS acct stop for all current clients */
function captiveportal_radius_stop_all() {
	global $config, $cpzone;

	if (!isset($config['captiveportal'][$cpzone]['radacct_enable']))
		return;

	$radiusservers = captiveportal_get_radius_servers();
	if (!empty($radiusservers)) {
		$cpdb = captiveportal_read_db();
		foreach ($cpdb as $cpentry) {
			if (empty($cpentry[11]))
				$cpentry[11] = 'first';
			if (!empty($radiusservers[$cpentry[11]])) {
				RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
					$cpentry[4], // username
					$cpentry[5], // sessionid
					$cpentry[0], // start time
					$radiusservers[$cpentry[11]],
					$cpentry[2], // clientip
					$cpentry[3], // clientmac
					7); // Admin Reboot
			}
		}
	}
}

function captiveportal_passthrumac_configure_entry($macent) {
	global $config, $g, $cpzone;

	$bwUp = 0;
	if (!empty($macent['bw_up']))
		$bwUp = $macent['bw_up'];
	else if (isset($config['captiveportal'][$cpzone]['bwdefaultup']))
		$bwUp = $config['captiveportal'][$cpzone]['bwdefaultup'];
	$bwDown = 0;
	if (!empty($macent['bw_down']))
		$bwDown = $macent['bw_down'];
	else if (isset($config['captiveportal'][$cpzone]['bwdefaultdn']))
		$bwDown = $config['captiveportal'][$cpzone]['bwdefaultdn'];

	$ruleno = captiveportal_get_next_ipfw_ruleno();

	if ($macent['action'] == 'pass') {
		$pipeno = captiveportal_get_next_dn_ruleno();

		$pipeup = $pipeno;
		$_gb = @pfSense_pipe_action("pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16");
		$pipedown = $pipeno + 1;
		$_gb = @pfSense_pipe_action("pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16");

		$rules = "add {$ruleno} pipe {$pipeup} ip from any to any MAC any {$macent['mac']}\n";
		$ruleno++;
		$rules .= "add {$ruleno} pipe {$pipedown} ip from any to any MAC {$macent['mac']} any\n";
	}

	return $rules;
}

function captiveportal_passthrumac_delete_entry($macent) {
	$rules = "";

	if ($macent['action'] == 'pass') {
		$ruleno = captiveportal_get_ipfw_passthru_ruleno($macent['mac']);

		if (!$ruleno)
			return $rules;

		captiveportal_free_ipfw_ruleno($ruleno);

		$rules .= "delete {$ruleno}\n";
		$rules .= "delete " . ++$ruleno . "\n";

		$pipeno = captiveportal_get_dn_passthru_ruleno($macent['mac']);

		if (!empty($pipeno)) {
			captiveportal_free_dn_ruleno($pipeno);
			$rules .= "pipe delete " . $pipeno . "\n";
			$rules .= "pipe delete " . ++$pipeno . "\n";
		}
	}

	return $rules;
}

function captiveportal_passthrumac_configure($lock = false) {
	global $config, $g, $cpzone;

	$rules = "";

	if (is_array($config['captiveportal'][$cpzone]['passthrumac']))
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent)
			$rules .= captiveportal_passthrumac_configure_entry($macent);

	return $rules;
}

function captiveportal_passthrumac_findbyname($username) {
	global $config, $cpzone;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
			if ($macent['username'] == $username)
				return $macent;
		}
	}
	return NULL;
}

/* 
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 */
function captiveportal_allowedip_configure_entry($ipent, $ishostname = false) {
	global $g;

	/*  Instead of copying this entire function for something
	 *  easy such as hostname vs ip address add this check
	 */
	if ($ishostname === true) {
		if (!$g['booting']) {
			$ipaddress = gethostbyname($ipent['hostname']);
			if (!is_ipaddr($ipaddress)) 
				return;
		} else
			$ipaddress = "";
	} else
		$ipaddress = $ipent['ip'];

	$rules = "";
	$cp_filterdns_conf = "";
	$enBwup = 0;
	if (!empty($ipent['bw_up']))
		$enBwup = intval($ipent['bw_up']);
	else if (isset($config['captiveportal'][$cpzone]['bwdefaultup']))
		$enBwup = $config['captiveportal'][$cpzone]['bwdefaultup'];
	$enBwdown = 0;
	if (!empty($ipent['bw_down']))
		$enBwdown = intval($ipent['bw_down']);
	else if (isset($config['captiveportal'][$cpzone]['bwdefaultdn']))
		$enBwdown = $config['captiveportal'][$cpzone]['bwdefaultdn'];

	$pipeno = captiveportal_get_next_dn_ruleno();
	$_gb = @pfSense_pipe_action("pipe {$pipeno} config bw {$enBwup}Kbit/s queue 100 buckets 16");
	$pipedown = $pipeno + 1;
	$_gb = @pfSense_pipe_action("pipe {$pipedown} config bw {$enBwdown}Kbit/s queue 100 buckets 16");
	if ($ishostname === true) {
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} 3 pipe {$pipeno}\n";
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} 4 pipe {$pipedown}\n";
		if (!is_ipaddr($ipaddress))
			return array("", $cp_filterdns_conf);
	}
	$subnet = "";
	if (!empty($ipent['sn']))
		$subnet = "/{$ipent['sn']}";
	$rules .= "table 3 add {$ipaddress}{$subnet} {$pipeno}\n";
	$rules .= "table 4 add {$ipaddress}{$subnet} {$pipedown}\n";

	if ($ishostname === true)
		return array($rules, $cp_filterdns_conf);
	else
		return $rules;
}

function captiveportal_allowedhostname_configure() {
	global $config, $g, $cpzone;

	$rules = "";
	if (is_array($config['captiveportal'][$cpzone]['allowedhostname'])) {
		$rules = "\n# captiveportal_allowedhostname_configure()\n";
		$cp_filterdns_conf = "";
		foreach ($config['captiveportal'][$cpzone]['allowedhostname'] as $hostnameent) {
			$tmprules = captiveportal_allowedip_configure_entry($hostnameent, true);
			$rules .= $tmprules[0];
			$cp_filterdns_conf .= $tmprules[1];
		}
		$cp_filterdns_filename = "{$g['varetc_path']}/filterdns-{$cpzone}-captiveportal.conf";
		@file_put_contents($cp_filterdns_filename, $cp_filterdns_conf);
		unset($cp_filterdns_conf);
		if (isvalidpid("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid"))
			sigkillbypid("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid", "HUP");
		else
			mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid -i 300 -c {$cp_filterdns_filename} -y {$cpzone} -d 1");
	} else {
		killbypid("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
		@unlink("{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid");
	}

	return $rules;
}

function captiveportal_allowedip_configure() {
	global $config, $g, $cpzone;

	$rules = "";
	if (is_array($config['captiveportal'][$cpzone]['allowedip'])) {
		foreach ($config['captiveportal'][$cpzone]['allowedip'] as $ipent) 
			$rules .= captiveportal_allowedip_configure_entry($ipent);
	}

	return $rules;
}

/* get last activity timestamp given client IP address */
function captiveportal_get_last_activity($ip, $mac = NULL) {
	global $cpzone;

	$ipfwoutput = pfSense_ipfw_getTablestats($cpzone, 1, $ip, $mac);
	/* Reading only from one of the tables is enough of approximation. */
	if (is_array($ipfwoutput)) {
		return $ipfwoutput['timestamp'];
	}

	return 0;
}

function captiveportal_init_radius_servers() {
	global $config, $g, $cpzone;

	/* generate radius server database */
	if ($config['captiveportal'][$cpzone]['radiusip'] && (!isset($config['captiveportal'][$cpzone]['auth_method']) ||
		($config['captiveportal'][$cpzone]['auth_method'] == "radius"))) {
		$radiusip = $config['captiveportal'][$cpzone]['radiusip'];
		$radiusip2 = ($config['captiveportal'][$cpzone]['radiusip2']) ? $config['captiveportal'][$cpzone]['radiusip2'] : null;
		$radiusip3 = ($config['captiveportal'][$cpzone]['radiusip3']) ? $config['captiveportal'][$cpzone]['radiusip3'] : null;
		$radiusip4 = ($config['captiveportal'][$cpzone]['radiusip4']) ? $config['captiveportal'][$cpzone]['radiusip4'] : null;

		if ($config['captiveportal'][$cpzone]['radiusport'])
			$radiusport = $config['captiveportal'][$cpzone]['radiusport'];
		else
			$radiusport = 1812;
		if ($config['captiveportal'][$cpzone]['radiusacctport'])
			$radiusacctport = $config['captiveportal'][$cpzone]['radiusacctport'];
		else
			$radiusacctport = 1813;
		if ($config['captiveportal'][$cpzone]['radiusport2'])
			$radiusport2 = $config['captiveportal'][$cpzone]['radiusport2'];
		else
			$radiusport2 = 1812;
		if ($config['captiveportal'][$cpzone]['radiusport3'])
			$radiusport3 = $config['captiveportal'][$cpzone]['radiusport3'];
		else
			$radiusport3 = 1812;
		if ($config['captiveportal'][$cpzone]['radiusport4'])
			$radiusport4 = $config['captiveportal'][$cpzone]['radiusport4'];
		else
			$radiusport4 = 1812;

		$radiuskey = $config['captiveportal'][$cpzone]['radiuskey'];
		$radiuskey2 = $config['captiveportal'][$cpzone]['radiuskey2'];
		$radiuskey3 = $config['captiveportal'][$cpzone]['radiuskey3'];
		$radiuskey4 = $config['captiveportal'][$cpzone]['radiuskey4'];

		$cprdsrvlck = lock("captiveportalradius{$cpzone}", LOCK_EX);
		$fd = @fopen("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db", "w");
		if (!$fd) {
			captiveportal_syslog("Error: cannot open radius DB file in captiveportal_configure().\n");
			unlock($cprdsrvlck);
			return 1;
		}
		if (isset($radiusip))
			fwrite($fd,$radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey . ",first");
		if (isset($radiusip2))
			fwrite($fd,"\n" . $radiusip2 . "," . $radiusport2 . "," . $radiusacctport . "," . $radiuskey2 . ",first");
		if (isset($radiusip3))
			fwrite($fd,"\n" . $radiusip3 . "," . $radiusport3 . "," . $radiusacctport . "," . $radiuskey3 . ",second");
		if (isset($radiusip4))
			fwrite($fd,"\n" . $radiusip4 . "," . $radiusport4 . "," . $radiusacctport . "," . $radiuskey4 . ",second");
		

		fclose($fd);
		unlock($cprdsrvlck);
	}
}

/* read RADIUS servers into array */
function captiveportal_get_radius_servers() {
	global $g, $cpzone;

	$cprdsrvlck = lock("captiveportalradius{$cpzone}");
	if (file_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db")) {
		$radiusservers = array();
		$cpradiusdb = file("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db", 
		FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($cpradiusdb) {
			foreach($cpradiusdb as $cpradiusentry) {
				$line = trim($cpradiusentry);
				if ($line) {
					$radsrv = array();
						list($radsrv['ipaddr'],$radsrv['port'],$radsrv['acctport'],$radsrv['key'], $context) = explode(",",$line);
				}
				if (empty($context)) {
					if (!is_array($radiusservers['first']))
						$radiusservers['first'] = array();
					$radiusservers['first'] = $radsrv;
				} else {
					if (!is_array($radiusservers[$context]))
						$radiusservers[$context] = array();
					$radiusservers[$context][] = $radsrv;
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
		$message = "{$status}: {$user}, {$mac}, {$ip}";
	else {
		$message = trim($message);
		$message = "{$status}: {$user}, {$mac}, {$ip}, {$message}";
	}
	captiveportal_syslog($message);
}

/* log simple messages to syslog */
function captiveportal_syslog($message) {
	global $cpzone;

	$message = trim($message);
	$message .= "Zone: {$cpzone} - {$message}";
	openlog("logportalauth", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}

function radius($username,$password,$clientip,$clientmac,$type, $radiusctx = null) {
	global $g, $config, $cpzoneid;

	$pipeno = captiveportal_get_next_dn_ruleno();

	/* If the pool is empty, return appropriate message and fail authentication */
	if (empty($pipeno)) {
		$auth_list = array();
		$auth_list['auth_val'] = 1;
		$auth_list['error'] = "System reached maximum login capacity";
		return $auth_list;
	}

	$radiusservers = captiveportal_get_radius_servers();

	if (is_null($radiusctx))
		$radiusctx = 'first';

	$auth_list = RADIUS_AUTHENTICATION($username,
		$password,
		$radiusservers[$radiusctx],
		$clientip,
		$clientmac,
		$pipeno);

	if ($auth_list['auth_val'] == 2) {
		captiveportal_logportalauth($username,$clientmac,$clientip,$type);
		$sessionid = portal_allow($clientip,
			$clientmac,
			$username,
			$password,
			$auth_list,
			$pipeno,
			$radiusctx);
	} else {
	         captiveportal_free_dn_ruleno($pipeno);
	       }

	return $auth_list;
}

function captiveportal_opendb() {
	global $g, $cpzone;

	$DB = new SQLite3("{$g['vardb_path']}/captiveportal{$cpzone}.db");
	if (! $DB->exec("CREATE TABLE IF NOT EXISTS captiveportal (" .
				"allow_time INTEGER, pipeno INTEGER, ip TEXT, mac TEXT, username TEXT, " .
				"sessionid TEXT, bpassword TEXT, session_timeout INTEGER, idle_timeout INTEGER, " .
				"session_terminate_time INTEGER, interim_interval INTEGER, radiusctx TEXT); " .
			"CREATE UNIQUE INDEX IF NOT EXISTS idx_active ON captiveportal (sessionid, username); " .
			"CREATE INDEX IF NOT EXISTS user ON captiveportal (username); " .
			"CREATE INDEX IF NOT EXISTS ip ON captiveportal (ip); " .
			"CREATE INDEX IF NOT EXISTS starttime ON captiveportal (allow_time)"))
		captiveportal_syslog("Error during table {$cpzone} creation. Error message: {$DB->lastErrorMsg()}");

	return $DB;
}

/* read captive portal DB into array */
function captiveportal_read_db($query = "") {
	$cpdb = array();

	$DB = captiveportal_opendb();
	if ($DB) {
		$response = $DB->query("SELECT * FROM captiveportal {$query}");
		while ($row = $response->fetchArray())
			$cpdb[] = $row;
		$DB->close();
	}

	return $cpdb;
}

function captiveportal_remove_entries($remove) {

	if (!is_array($remove) || empty($remove))
		return;

	$query = "DELETE FROM captiveportal WHERE sessionid in (";
	foreach($remove as $idx => $unindex) {
		$query .= "'{$unindex}'";
		if ($idx < (count($remove) - 1))
			$query .= ",";
	}
	$query .= ")";
	captiveportal_write_db($query);
}

/* write captive portal DB */
function captiveportal_write_db($queries) {
	global $g;

	if (is_array($queries))
		$query = implode(";", $queries);
	else
		$query = $queries;

	$DB = captiveportal_opendb();
	if ($DB) {
		$DB->exec("BEGIN TRANSACTION");
		$result = $DB->exec($query);
		if (!$result)
			captiveportal_syslog("Trying to modify DB returned error: {$DB->lastErrorMsg()}");
		else
			$DB->exec("END TRANSACTION");
		$DB->close();
		return $result;
	} else
		return true;
}

function captiveportal_write_elements() {
	global $g, $config, $cpzone;
	
	$cpcfg = $config['captiveportal'][$cpzone];

	if (!is_dir($g['captiveportal_element_path']))
		@mkdir($g['captiveportal_element_path']);

	if (is_array($cpcfg['element'])) {
		conf_mount_rw();
		foreach ($cpcfg['element'] as $data) {
			if (!@file_put_contents("{$g['captiveportal_element_path']}/{$data['name']}", base64_decode($data['content']))) {
				printf(gettext("Error: cannot open '%s' in captiveportal_write_elements()%s"), $data['name'], "\n");
				return 1;
			}
			if (!file_exists("{$g['captiveportal_path']}/{$data['name']}"))
				@symlink("{$g['captiveportal_element_path']}/{$data['name']}", "{$g['captiveportal_path']}/{$data['name']}");
		}
		conf_mount_ro();
	}
	
	return 0;
}

function captiveportal_free_dnrules($rulenos_start = 2000, $rulenos_range_max = 64500) {
	global $cpzone;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		$ridx = $rulenos_start;
		while ($ridx < $rulenos_range_max) {
			if ($rules[$ridx] == $cpzone) {
				$rules[$ridx] = false;
				$ridx++;
				$rules[$ridx] = false;
				$ridx++;
			} else
				$ridx += 2;
		}
		file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
		unset($rules);
	}
	unlock($cpruleslck);
}

function captiveportal_get_next_dn_ruleno($rulenos_start = 2000, $rulenos_range_max = 64500) {
	global $config, $g, $cpzone;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	$ruleno = 0;
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		$ridx = $rulenos_start;
		while ($ridx < $rulenos_range_max) {
			if (empty($rules[$ridx])) {
				$ruleno = $ridx;
				$rules[$ridx] = $cpzone;
				$ridx++;
				$rules[$ridx] = $cpzone;
				break;
			} else {
				$ridx += 2;
			}
		}
	} else {
		$rules = array_pad(array(), $rulenos_range_max, false);
		$ruleno = $rulenos_start;
		$rules[$rulenos_start] = $cpzone;
		$rulenos_start++;
		$rules[$rulenos_start] = $cpzone;
	}
	file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
	unlock($cpruleslck);
	unset($rules);

	return $ruleno;
}

function captiveportal_free_dn_ruleno($ruleno) {
	global $config, $g;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		$rules[$ruleno] = false;
		$ruleno++;
		$rules[$ruleno] = false;
		file_put_contents("{$g['vardb_path']}/captiveportaldn.rules", serialize($rules));
		unset($rules);
	}
	unlock($cpruleslck);
}

function captiveportal_get_dn_passthru_ruleno($value) {
	global $config, $g, $cpzone, $cpzoneid;

	$cpcfg = $config['captiveportal'][$cpzone];
	if(!isset($cpcfg['enable']))
		return NULL;

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	$ruleno = NULL;
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		unset($output);
		$_gb = exec("/sbin/ipfw -x {$cpzoneid} show | /usr/bin/grep " . escapeshellarg($value) . " | /usr/bin/grep -v grep | /usr/bin/awk '{print $5}' | /usr/bin/head -n 1", $output);
		$ruleno = intval($output[0]);
		if (!$rules[$ruleno])
			$ruleno = NULL;
		unset($rules);
	}
	unlock($cpruleslck);

	return $ruleno;
}

/*
 * This function will calculate the lowest free firewall ruleno
 * within the range specified based on the actual logged on users
 *
 */
function captiveportal_get_next_ipfw_ruleno($rulenos_start = 2, $rulenos_range_max = 64500) {
	global $config, $g, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];
	if(!isset($cpcfg['enable']))
		return NULL;

	$cpruleslck = lock("captiveportalrules{$cpzone}", LOCK_EX);
	$ruleno = 0;
	if (file_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal_{$cpzone}.rules"));
		$ridx = $rulenos_start;
		while ($ridx < $rulenos_range_max) {
			if (empty($rules[$ridx])) {
				$ruleno = $ridx;
				$rules[$ridx] = $cpzone;
				$ridx++;
				$rules[$ridx] = $cpzone;
				break;
			} else {
				/* 
				 * This allows our traffic shaping pipes to be the in pipe the same as ruleno 
				 * and the out pipe ruleno + 1.
				 */
				$ridx += 2;
			}
		}
	} else {
		$rules = array_pad(array(), $rulenos_range_max, false);
		$ruleno = $rulenos_start;
		$rules[$rulenos_start] = $cpzone;
		$rulenos_start++;
		$rules[$rulenos_start] = $cpzone;
	}
	file_put_contents("{$g['vardb_path']}/captiveportal_{$cpzone}.rules", serialize($rules));
	unlock($cpruleslck);
	unset($rules);

	return $ruleno;
}

function captiveportal_free_ipfw_ruleno($ruleno) {
	global $config, $g, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];
	if(!isset($cpcfg['enable']))
		return NULL;

	$cpruleslck = lock("captiveportalrules{$cpzone}", LOCK_EX);
	if (file_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal_{$cpzone}.rules"));
		$rules[$ruleno] = false;
		$ruleno++;
		$rules[$ruleno] = false;
		file_put_contents("{$g['vardb_path']}/captiveportal_{$cpzone}.rules", serialize($rules));
		unset($rules);
	}
	unlock($cpruleslck);
}

function captiveportal_get_ipfw_passthru_ruleno($value) {
	global $config, $g, $cpzone, $cpzoneid;

	$cpcfg = $config['captiveportal'][$cpzone];
	if(!isset($cpcfg['enable']))
		return NULL;

	$cpruleslck = lock("captiveportalrules{$cpzone}", LOCK_EX);
	$ruleno = NULL;
	if (file_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules")) {
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportal_{$cpzone}.rules"));
		unset($output);
		$_gb = exec("/sbin/ipfw -x {$cpzoneid} show | /usr/bin/grep " . escapeshellarg($value) . " | /usr/bin/grep -v grep | /usr/bin/awk '{print $1}' | /usr/bin/head -n 1", $output);
		$ruleno = intval($output[0]);
		if (!$rules[$ruleno])
			$ruleno = NULL;
		unset($rules);
	}
	unlock($cpruleslck);

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

function getVolume($ip, $mac = NULL) {
	global $config, $cpzone;

	$reverse = empty($config['captiveportal'][$cpzone]['reverseacct']) ? false : true;
	$volume = array();
	// Initialize vars properly, since we don't want NULL vars
	$volume['input_pkts'] = $volume['input_bytes'] = $volume['output_pkts'] = $volume['output_bytes'] = 0 ;

	$ipfw = pfSense_ipfw_getTablestats($cpzone, 1, $ip, $mac);
	if (is_array($ipfw)) {
		if ($reverse) {
			$volume['output_pkts'] = $ipfw['packets'];
			$volume['output_bytes'] = $ipfw['bytes'];
		}
		else {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		}
	}

	$ipfw = pfSense_ipfw_getTablestats($cpzone, 2, $ip);
	if (is_array($ipfw)) {
		if ($reverse) {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		}
		else {
			$volume['output_pkts'] = $ipfw['packets'];
			$volume['output_bytes'] = $ipfw['bytes'];
		}
	}

	return $volume;
}

/**
 * Get the NAS-IP-Address based on the current wan address
 *
 * Use functions in interfaces.inc to find this out
 *
 */

function getNasIP()
{
	global $config, $cpzone;

	if (empty($config['captiveportal'][$cpzone]['radiussrcip_attribute'])) {
			$nasIp = get_interface_ip();
	} else {
		if (is_ipaddr($config['captiveportal'][$cpzone]['radiussrcip_attribute']))
			$nasIp = $config['captiveportal'][$cpzone]['radiussrcip_attribute'];
		else
			$nasIp = get_interface_ip($config['captiveportal'][$cpzone]['radiussrcip_attribute']);
	}
		
	if(!is_ipaddr($nasIp))
		$nasIp = "0.0.0.0";

	return $nasIp;
}

function portal_ip_from_client_ip($cliip) {
	global $config, $cpzone;

	$isipv6 = is_ipaddrv6($cliip);
	$interfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
	foreach ($interfaces as $cpif) {
		if ($isipv6) {
			$ip = get_interface_ipv6($cpif);
			$sn = get_interface_subnetv6($cpif);
		} else {
			$ip = get_interface_ip($cpif);
			$sn = get_interface_subnet($cpif);
		}
		if (ip_in_subnet($cliip, "{$ip}/{$sn}"))
			return $ip;
	}

	$inet = ($isipv6) ? '-inet6' : '-inet';
	$iface = exec_command("/sbin/route -n get {$inet} {$cliip} | /usr/bin/awk '/interface/ { print \$2; };'");
	$iface = trim($iface, "\n");
	if (!empty($iface)) {
		$ip = ($isipv6) ? find_interface_ipv6($iface) : find_interface_ip($iface);
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

function portal_hostname_from_client_ip($cliip) {
	global $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];

	if (isset($cpcfg['httpslogin'])) {
		$listenporthttps = $cpcfg['listenporthttps'] ? $cpcfg['listenporthttps'] : ($cpcfg['zoneid'] + 8001);
		$ourhostname = $cpcfg['httpsname'];
		
		if ($listenporthttps != 443)
			$ourhostname .= ":" . $listenporthttps;
	} else {
		$listenporthttp  = $cpcfg['listenporthttp']  ? $cpcfg['listenporthttp']  : ($cpcfg['zoneid'] + 8000);
		$ifip = portal_ip_from_client_ip($cliip);
		if (!$ifip)
			$ourhostname = "{$config['system']['hostname']}.{$config['system']['domain']}";
		else
			$ourhostname = (is_ipaddrv6($ifip)) ? "[{$ifip}]" : "{$ifip}";
		
		if ($listenporthttp != 80)
			$ourhostname .= ":" . $listenporthttp;
	}
	
	return $ourhostname;
}

/* functions move from index.php */

function portal_reply_page($redirurl, $type = null, $message = null, $clientmac = null, $clientip = null, $username = null, $password = null) {
	global $g, $config, $cpzone;

	/* Get captive portal layout */
	if ($type == "redir") {
		header("Location: {$redirurl}");
		return;
	} else if ($type == "login")
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
	else
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");

	$cpcfg = $config['captiveportal'][$cpzone];

	/* substitute the PORTAL_REDIRURL variable */
	if ($cpcfg['preauthurl']) {
		$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
		$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
	}

	/* substitute other variables */
	$ourhostname = portal_hostname_from_client_ip($clientip);
	$protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
	$htmltext = str_replace("\$PORTAL_ACTION\$", "{$protocol}{$ourhostname}/", $htmltext);
	$htmltext = str_replace("#PORTAL_ACTION#", "{$protocol}{$ourhostname}/", $htmltext);

	$htmltext = str_replace("\$PORTAL_ZONE\$", htmlspecialchars($cpzone), $htmltext);
	$htmltext = str_replace("\$PORTAL_REDIRURL\$", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("\$PORTAL_MESSAGE\$", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("\$CLIENT_MAC\$", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("\$CLIENT_IP\$", htmlspecialchars($clientip), $htmltext);

	// Special handling case for captive portal master page so that it can be ran 
	// through the PHP interpreter using the include method above.  We convert the
	// $VARIABLE$ case to #VARIABLE# in /etc/inc/captiveportal.inc before writing out.
	$htmltext = str_replace("#PORTAL_ZONE#", htmlspecialchars($cpzone), $htmltext);
	$htmltext = str_replace("#PORTAL_REDIRURL#", htmlspecialchars($redirurl), $htmltext);
	$htmltext = str_replace("#PORTAL_MESSAGE#", htmlspecialchars($message), $htmltext);
	$htmltext = str_replace("#CLIENT_MAC#", htmlspecialchars($clientmac), $htmltext);
	$htmltext = str_replace("#CLIENT_IP#", htmlspecialchars($clientip), $htmltext);
	$htmltext = str_replace("#USERNAME#", htmlspecialchars($username), $htmltext);
	$htmltext = str_replace("#PASSWORD#", htmlspecialchars($password), $htmltext);

	echo $htmltext;
}

function portal_mac_radius($clientmac,$clientip) {
	global $config, $cpzone;

	$radmac_secret = $config['captiveportal'][$cpzone]['radmac_secret'];

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
	global $config, $cpzone, $g;

	$dwfaultbw_up = isset($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
	$dwfaultbw_down = isset($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
	$bw_up = isset($attributes['bw_up']) ? round(intval($attributes['bw_up'])/1000, 2) : $dwfaultbw_up;
	$bw_down = isset($attributes['bw_down']) ? round(intval($attributes['bw_down'])/1000, 2) : $dwfaultbw_down;
	$bw_up_pipeno = $cpentry[1];
	$bw_down_pipeno = $cpentry[1]+1;

	$_gb = @pfSense_pipe_action("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
	$_gb = @pfSense_pipe_action("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
	//captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "RADIUS_BANDWIDTH_REAPPLY", "{$bw_up}/{$bw_down}");

	unset($bw_up_pipeno, $bw_down_pipeno, $bw_up, $bw_down);
}

function portal_allow($clientip,$clientmac,$username,$password = null, $attributes = null, $pipeno = null, $radiusctx = null)  {
	global $redirurl, $g, $config, $type, $passthrumac, $_POST, $cpzone, $cpzoneid;

	// Ensure we create an array if we are missing attributes
	if (!is_array($attributes))
		$attributes = array();

	unset($sessionid);

	/* Do not allow concurrent login execution. */
	$cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

	if ($attributes['voucher'])
		$remaining_time = $attributes['session_timeout'];

	$writecfg = false;
	/* Find an existing session */
	if ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && $passthrumac) {
		if (isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
			$mac = captiveportal_passthrumac_findbyname($username);
			if (!empty($mac)) {
				if ($_POST['replacemacpassthru']) {
					foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $idx => $macent) {
						if ($macent['mac'] == $mac['mac']) {
							$macrules = "";
							$ruleno = captiveportal_get_ipfw_passthru_ruleno($mac['mac']);
							$pipeno = captiveportal_get_dn_passthru_ruleno($mac['mac']);
							if ($ruleno) {
								captiveportal_free_ipfw_ruleno($ruleno);
								$macrules .= "delete {$ruleno}\n";
								++$ruleno;
								$macrules .= "delete {$ruleno}\n";
							}
							if ($pipeno) {
								captiveportal_free_dn_ruleno($pipeno);
								$macrules .= "pipe delete {$pipeno}\n";
								++$pipeno;
								$macrules .= "pipe delete {$pipeno}\n";
							}
							unset($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
							$mac['action'] = 'pass';
							$mac['mac'] = $clientmac;
							$config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
							$macrules .= captiveportal_passthrumac_configure_entry($mac);
							file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
							mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
							$writecfg = true;
							$sessionid = true;
							break;
						}
					}
				} else {
					portal_reply_page($redirurl, "error", "Username: {$username} is already authenticated using another MAC address.",
						$clientmac, $clientip, $username, $password);
					unlock($cpdblck);
					return;
				}
			}
		}
	}

	/* read in client database */
	$query = "WHERE ip = '{$clientip}'";
	$tmpusername = strtolower($username);
	if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins']))
		$query .= " OR (username != 'unauthenticated' AND lower(username) = '{$tmpusername}')";
	$cpdb = captiveportal_read_db($query);

	/* Snapshot the timestamp */
	$allow_time = time();
	$radiusservers = captiveportal_get_radius_servers();
	$unsetindexes = array();
	if (is_null($radiusctx))
		$radiusctx = 'first';

	foreach ($cpdb as $cpentry) {
		if (empty($cpentry[11])) {
			$cpentry[11] = 'first';
		}
		/* on the same ip */
		if ($cpentry[2] == $clientip) {
			if (isset($config['captiveportal'][$cpzone]['nomacfilter']) || $cpentry[3] == $clientmac)
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - REUSING OLD SESSION");
			else
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
			$sessionid = $cpentry[5];
			break;
		}
		elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
			// user logged in with an active voucher. Check for how long and calculate 
			// how much time we can give him (voucher credit - used time)
			$remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
			if ($remaining_time < 0)    // just in case. 
				$remaining_time = 0;

			/* This user was already logged in so we disconnect the old one */
			captiveportal_disconnect($cpentry,$radiusservers[$cpentry[11]],13);
			captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
			$unsetindexes[] = $cpentry[5];
			break;
		}
		elseif ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
			/* on the same username */
			if (strcasecmp($cpentry[4], $username) == 0) {
				/* This user was already logged in so we disconnect the old one */
				captiveportal_disconnect($cpentry,$radiusservers[$cpentry[11]],13);
				captiveportal_logportalauth($cpentry[4],$cpentry[3],$cpentry[2],"CONCURRENT LOGIN - TERMINATING OLD SESSION");
				$unsetindexes[] = $cpentry[5];
				break;
			}
		}
	}
	unset($cpdb);

	if (!empty($unsetindexes))
		captiveportal_remove_entries($unsetindexes);

	if ($attributes['voucher'] && $remaining_time <= 0)
		return 0;       // voucher already used and no time left

	if (!isset($sessionid)) {
		/* generate unique session ID */
		$tod = gettimeofday();
		$sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

		if ($passthrumac) {
			$mac = array();
			$mac['action'] = 'pass';
			$mac['mac'] = $clientmac;
			$mac['ip'] = $clientip; /* Used only for logging */
			if (isset($config['captiveportal'][$cpzone]['passthrumacaddusername'])) {
				$mac['username'] = $username;
				if ($attributes['voucher'])
					$mac['logintype'] = "voucher";
			}
			$mac['descr'] =  "Auto added pass-through MAC for user {$username}";
			if (!empty($bw_up))
				$mac['bw_up'] = $bw_up;
			if (!empty($bw_down))
				$mac['bw_down'] = $bw_down;
			if (!is_array($config['captiveportal'][$cpzone]['passthrumac']))
				$config['captiveportal'][$cpzone]['passthrumac'] = array();
			$config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
			unlock($cpdblck);
			$macrules = captiveportal_passthrumac_configure_entry($mac);
			file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
			mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
			$writecfg = true;
		} else {
			/* See if a pipeno is passed, if not start sessions because this means there isn't one atm */
			if (is_null($pipeno))
				$pipeno = captiveportal_get_next_dn_ruleno();

			/* if the pool is empty, return appropriate message and exit */
			if (is_null($pipeno)) {
				portal_reply_page($redirurl, "error", "System reached maximum login capacity");
				log_error("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
				unlock($cpdblck);
				return;
			}

			$dwfaultbw_up = isset($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
			$dwfaultbw_down = isset($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
			$bw_up = isset($attributes['bw_up']) ? round(intval($attributes['bw_up'])/1000, 2) : $dwfaultbw_up;
			$bw_down = isset($attributes['bw_down']) ? round(intval($attributes['bw_down'])/1000, 2) : $dwfaultbw_down;

			$bw_up_pipeno = $pipeno;
			$bw_down_pipeno = $pipeno + 1;
			//$bw_up /= 1000; // Scale to Kbit/s
			$_gb = @pfSense_pipe_action("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
			$_gb = @pfSense_pipe_action("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");

			$clientsn = (is_ipaddrv6($clientip)) ? 128 : 32;
			if (!isset($config['captiveportal'][$cpzone]['nomacfilter']))
				$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 1, $clientip, $clientsn, $clientmac, $bw_up_pipeno);
			else
				$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 1, $clientip, $clientsn, NULL, $bw_up_pipeno);

			if (!isset($config['captiveportal'][$cpzone]['nomacfilter']))
				$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 2, $clientip, $clientsn, $clientmac, $bw_down_pipeno);
			else
				$_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 2, $clientip, $clientsn, NULL, $bw_down_pipeno);

			if ($attributes['voucher'])
				$attributes['session_timeout'] = $remaining_time;
			
			/* handle empty attributes */
			$session_timeout = (!empty($attributes['session_timeout'])) ? $attributes['session_timeout'] : 'NULL';
			$idle_timeout = (!empty($attributes['idle_timeout'])) ? $attributes['idle_timeout'] : 'NULL';
			$session_terminate_time = (!empty($attributes['session_terminate_time'])) ? $attributes['session_terminate_time'] : 'NULL';
			$interim_interval = (!empty($attributes['interim_interval'])) ? $attributes['interim_interval'] : 'NULL';

			/* escape username */
			$safe_username = SQLite3::escapeString($username);

			/* encode password in Base64 just in case it contains commas */
			$bpassword = base64_encode($password);
			$insertquery  = "INSERT INTO captiveportal (allow_time, pipeno, ip, mac, username, sessionid, bpassword, session_timeout, idle_timeout, session_terminate_time, interim_interval, radiusctx) ";
			$insertquery .= "VALUES ({$allow_time}, {$pipeno}, '{$clientip}', '{$clientmac}', '{$safe_username}', '{$sessionid}', '{$bpassword}', ";
			$insertquery .= "{$session_timeout}, {$idle_timeout}, {$session_terminate_time}, {$interim_interval}, '{$radiusctx}')";

			/* store information to database */
			captiveportal_write_db($insertquery);
			unlock($cpdblck);
			unset($insertquery, $bpassword);

			if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && !empty($radiusservers[$radiusctx])) {
				$acct_val = RADIUS_ACCOUNTING_START($pipeno, $username, $sessionid, $radiusservers[$radiusctx], $clientip, $clientmac);
				if ($acct_val == 1)
					captiveportal_logportalauth($username,$clientmac,$clientip,$type,"RADIUS ACCOUNTING FAILED");
			}
		}
	} else {
		/* NOTE: #3062-11 If the pipeno has been allocated free it to not DoS the CP and maintain proper operation as in radius() case */
		if (!is_null($pipeno))
			captiveportal_free_dn_ruleno($pipeno);

		unlock($cpdblck);
	}

	if ($writecfg == true)
		write_config();

	/* redirect user to desired destination */
	if (!empty($attributes['url_redirection']))
		$my_redirurl = $attributes['url_redirection'];
	else if (!empty($redirurl))
		$my_redirurl = $redirurl;
	else if (!empty($config['captiveportal'][$cpzone]['redirurl']))
		$my_redirurl = $config['captiveportal'][$cpzone]['redirurl'];

	if(isset($config['captiveportal'][$cpzone]['logoutwin_enable']) && !$passthrumac) {
		$ourhostname = portal_hostname_from_client_ip($clientip);
		$protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';
		$logouturl = "{$protocol}{$ourhostname}/";

		if (isset($attributes['reply_message']))
			$message = $attributes['reply_message'];
		else
			$message = 0;

		include("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

	} else {
		portal_reply_page($my_redirurl, "redir", "Just redirect the user.");
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
	global $config, $cpzone;

	if (!empty($config['captiveportal'][$cpzone]['freelogins_count']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_count']))
		$freeloginscount = $config['captiveportal'][$cpzone]['freelogins_count'];
	else
		return false;

	if (!empty($config['captiveportal'][$cpzone]['freelogins_resettimeout']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_resettimeout']))
		$resettimeout = $config['captiveportal'][$cpzone]['freelogins_resettimeout'];
	else
		return false;

	if ($freeloginscount < 1 || $resettimeout <= 0 || !$clientmac)
		return false;

	$updatetimeouts = isset($config['captiveportal'][$cpzone]['freelogins_updatetimeouts']);

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
	global $g, $cpzone;

	$cpumaclck = lock("captiveusedmacs{$cpzone}");
	if (file_exists("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db")) {
		$usedmacs = file("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$usedmacs)
			$usedmacs = array();
	} else
		$usedmacs = array();

	unlock($cpumaclck);
	return $usedmacs;
}

function captiveportal_write_usedmacs_db($usedmacs) {
	global $g, $cpzone;

	$cpumaclck = lock("captiveusedmacs{$cpzone}", LOCK_EX);
	@file_put_contents("{$g['vardb_path']}/captiveportal_usedmacs_{$cpzone}.db", implode("\n", $usedmacs));
	unlock($cpumaclck);
}

function captiveportal_blocked_mac($mac) {
	global $config, $g, $cpzone;

	if (empty($mac) || !is_macaddr($mac))
		return false;

	if (!is_array($config['captiveportal'][$cpzone]['passthrumac']))
		return false;

	foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $passthrumac)
		if (($passthrumac['action'] == 'block') &&
		    ($passthrumac['mac'] == strtolower($mac)))
			return true;

	return false;

}

function captiveportal_send_server_accounting($off = false) {
	global $cpzone, $config;

	if (!isset($config['captiveportal'][$cpzone]['radacct_enable'])) {
		return;
	}
	if ($off) {
		$racct = new Auth_RADIUS_Acct_Off;
	} else {
		$racct = new Auth_RADIUS_Acct_On;
	}
	$radiusservers = captiveportal_get_radius_servers();
	if (empty($radiusservers)) {
		return;
	}
	foreach ($radiusservers['first'] as $radsrv) {
		// Add a new server to our instance
		$racct->addServer($radsrv['ipaddr'], $radsrv['acctport'], $radsrv['key']);
	}
	if (PEAR::isError($racct->start())) {
		$retvalue['acct_val'] = 1;
		$retvalue['error'] = $racct->getMessage();

		// If we encounter an error immediately stop this function and go back
		$racct->close();
		return $retvalue;
	}
	// Send request
	$result = $racct->send();
	// Evaluation of the response
	// 5 -> Accounting-Response
	// See RFC2866 for this.
	if (PEAR::isError($result)) {
		$retvalue['acct_val'] = 1;
		$retvalue['error'] = $result->getMessage();
	} else if ($result === true) {
		$retvalue['acct_val'] = 5 ;
	} else {
		$retvalue['acct_val'] = 1 ;
	}

	$racct->close();
	return $retvalue;
}
?>
