<?php
/*
 * captiveportal.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2006 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/* include all configuration functions */
require_once("config.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("radius_accounting.inc");
require_once("radius_authentication.inc");
require_once("voucher.inc");

function get_default_captive_portal_html() {
	global $config, $g, $cpzone;

	$translated_text1 = sprintf(gettext("%s captive portal"), $g['product_name']);
	$translated_text2 = sprintf(gettext("Welcome to the %s Captive Portal!"), $g['product_name']);
	$translated_text3 = gettext("Username:");
	$translated_text4 = gettext("Password:");
	$htmltext = <<<EOD
<html>
<body>
<form method="post" action="\$PORTAL_ACTION\$">
	<input name="redirurl" type="hidden" value="\$PORTAL_REDIRURL\$">
	<input name="zone" type="hidden" value="\$PORTAL_ZONE\$">
	<center>
	<table cellpadding="6" cellspacing="0" width="550" height="380" style="border:1px solid #000000">
	<tr height="10" style="background-color:#990000">
		<td style="border-bottom:1px solid #000000">
			<font color='white'>
			<b>
				{$translated_text1}
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
									<tr><td colspan="2"><center>{$translated_text2}</td></tr>
									<tr><td>&nbsp;</td></tr>
									<tr><td class="text-right">{$translated_text3}</td><td><input name="auth_user" type="text" style="border: 1px dashed;"></td></tr>
									<tr><td class="text-right">{$translated_text4}</td><td><input name="auth_pass" type="password" style="border: 1px dashed;"></td></tr>
									<tr><td>&nbsp;</td></tr>

EOD;

	if (isset($config['voucher'][$cpzone]['enable'])) {
		$translated_text = gettext("Enter Voucher Code:");
		$htmltext .= <<<EOD
									<tr>
										<td class="text-right">{$translated_text} </td>
										<td><input name="auth_voucher" type="text" style="border:1px dashed;" size="22"></td>
									</tr>

EOD;
	}

	$translated_text = gettext("Continue");
	$htmltext .= <<<EOD
									<tr>
										<td colspan="2"><center><input name="accept" type="submit" value="{$translated_text}"></center></td>
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
		    "net.inet.ip.pfil.inbound" => "pf",
		    "net.inet6.ip6.pfil.inbound" => "pf",
		    "net.inet.ip.pfil.outbound" => "pf",
		    "net.inet6.ip6.pfil.outbound" => "pf"
		));
	}
	/* Activate layer2 filtering */
	set_sysctl(array(
	    "net.link.ether.ipfw" => "1",
	    "net.inet.ip.fw.one_pass" => "1",
	    "net.inet.ip.fw.tables_max" => "65534"
	));

	/* Always load dummynet now that even allowed ip and mac passthrough use it. */
	if (!is_module_loaded("dummynet.ko")) {
		mwexec("/sbin/kldload dummynet");
		set_sysctl(array(
		    "net.inet.ip.dummynet.io_fast" => "1",
		    "net.inet.ip.dummynet.hash_size" => "256"
		));
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

		if (platform_booting()) {
			echo "Starting captive portal({$cpcfg['zone']})... ";

			/* remove old information */
			unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		} else {
			captiveportal_syslog("Reconfiguring captive portal({$cpcfg['zone']}).");
		}

		/* init ipfw rules */
		captiveportal_init_rules(true);

		/* kill any running minicron */
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");

		/* initialize minicron interval value */
		$croninterval = $cpcfg['croninterval'] ? $cpcfg['croninterval'] : 60;

		/* double check if the $croninterval is numeric and at least 10 seconds. If not we set it to 60 to avoid problems */
		if ((!is_numeric($croninterval)) || ($croninterval < 10)) {
			$croninterval = 60;
		}

		/* write portal page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['htmltext']) {
			$htmltext = base64_decode($cpcfg['page']['htmltext']);
		} else {
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
			$htmltext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $htmltext);
			if ($cpcfg['preauthurl']) {
				$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
				$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
			}
			fwrite($fd, $htmltext);
			fclose($fd);
		}
		unset($htmltext);

		/* write error page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['errtext']) {
			$errtext = base64_decode($cpcfg['page']['errtext']);
		} else {
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
			$errtext = str_replace("\$PORTAL_ACTION\$", "#PORTAL_ACTION#", $errtext);
			if ($cpcfg['preauthurl']) {
				$errtext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $errtext);
				$errtext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $errtext);
			}
			fwrite($fd, $errtext);
			fclose($fd);
		}
		unset($errtext);

		/* write logout page */
		if (is_array($cpcfg['page']) && $cpcfg['page']['logouttext']) {
			$logouttext = base64_decode($cpcfg['page']['logouttext']);
		} else {
			/* example page */
			$translated_text1 = gettext("Redirecting...");
			$translated_text2 = gettext("Redirecting to");
			$translated_text3 = gettext("Logout");
			$translated_text4 = gettext("Click the button below to disconnect");
			$logouttext = <<<EOD
<html>
<head><title>{$translated_text1}</title></head>
<body>
<span style="font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">
<b>{$translated_text2} <a href="<?=\$my_redirurl;?>"><?=\$my_redirurl;?></a>...</b>
</span>
<script type="text/javascript">
//<![CDATA[
LogoutWin = window.open('', 'Logout', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=256,height=64');
if (LogoutWin) {
	LogoutWin.document.write('<html>');
	LogoutWin.document.write('<head><title>{$translated_text3}</title></head>') ;
	LogoutWin.document.write('<body style="background-color:#435370">');
	LogoutWin.document.write('<div class="text-center" style="color: #ffffff; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; font-size: 11px;">') ;
	LogoutWin.document.write('<b>{$translated_text4}</b><p />');
	LogoutWin.document.write('<form method="POST" action="<?=\$logouturl;?>">');
	LogoutWin.document.write('<input name="logout_id" type="hidden" value="<?=\$sessionid;?>" />');
	LogoutWin.document.write('<input name="zone" type="hidden" value="<?=\$cpzone;?>" />');
	LogoutWin.document.write('<input name="logout" type="submit" value="{$translated_text3}" />');
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

		/* kill any running CP nginx instances */
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");

		/* start up the webserving daemon */
		captiveportal_init_webgui_zone($cpcfg);

		/* Kill any existing prunecaptiveportal processes */
		if (file_exists("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid")) {
			killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
		}

		/* start pruning process (interval defaults to 60 seconds) */
		mwexec("/usr/local/bin/minicron $croninterval {$g['varrun_path']}/cp_prunedb_{$cpzone}.pid " .
			"/etc/rc.prunecaptiveportal {$cpzone}");

		/* generate radius server database */
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
		captiveportal_init_radius_servers();

		if (platform_booting()) {
			/* send Accounting-On to server */
			captiveportal_send_server_accounting();
			echo "done\n";
		}

	} else {
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal.pid");
		killbypid("{$g['varrun_path']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
		killbypid("{$g['varrun_path']}/cp_prunedb_{$cpzone}.pid");
		@unlink("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");
		@unlink("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

		captiveportal_radius_stop_all(10); // NAS-Request

		captiveportal_filterdns_configure();

		/* remove old information */
		unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db");
		unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");
		/* Release allocated pipes for this zone */
		$pipes_to_remove = captiveportal_free_dnrules();

		captiveportal_delete_rules($pipes_to_remove);

		if (empty($config['captiveportal'])) {
			set_single_sysctl("net.link.ether.ipfw", "0");
		} else {
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
			if ($cpactive === false) {
				set_single_sysctl("net.link.ether.ipfw", "0");
			}
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

	if (!isset($cpcfg['enable'])) {
		return;
	}

	if (isset($cpcfg['httpslogin'])) {
		$cert = lookup_cert($cpcfg['certref']);
		$crt = base64_decode($cert['crt']);
		$key = base64_decode($cert['prv']);
		$ca = ca_chain($cert);

		/* generate nginx configuration */
		if (!empty($cpcfg['listenporthttps'])) {
			$listenporthttps = $cpcfg['listenporthttps'];
		} else {
			$listenporthttps = 8001 + $cpcfg['zoneid'];
		}
		system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf",
			$crt, $key, $ca, "nginx-{$cpzone}-CaptivePortal-SSL.pid", $listenporthttps, "/usr/local/captiveportal",
			"cert-{$cpzone}-portal.pem", "ca-{$cpzone}-portal.pem", $cpzone, false);
	}

	/* generate nginx configuration */
	if (!empty($cpcfg['listenporthttp'])) {
		$listenporthttp = $cpcfg['listenporthttp'];
	} else {
		$listenporthttp = 8000 + $cpcfg['zoneid'];
	}
	system_generate_nginx_config("{$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf",
		"", "", "", "nginx-{$cpzone}-CaptivePortal.pid", $listenporthttp, "/usr/local/captiveportal",
		"", "", $cpzone, false);

	@unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal.pid");
	/* attempt to start nginx */
	$res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal.conf");

	/* fire up https instance */
	if (isset($cpcfg['httpslogin'])) {
		@unlink("{$g['varrun']}/nginx-{$cpzone}-CaptivePortal-SSL.pid");
		$res = mwexec("/usr/local/sbin/nginx -c {$g['varetc_path']}/nginx-{$cpzone}-CaptivePortal-SSL.conf");
	}
}

function captiveportal_init_rules_byinterface($interface) {
	global $cpzone, $cpzoneid, $config;

	if (!is_array($config['captiveportal'])) {
		return;
	}

	foreach ($config['captiveportal'] as $cpkey => $cp) {
		$cpzone = $cpkey;
		$cpzoneid = $cp['zoneid'];
		$cpinterfaces = explode(",", $cp['interface']);
		if (in_array($interface, $cpinterfaces)) {
			captiveportal_init_rules();
			break;
		}
	}
}

/* Create basic rules used by all zones */
function captiveportal_init_general_rules($flush = false) {
	global $g;

	$flush_rule = '';
	if ($flush) {
		$flush_rule = 'flush';
	}

	/* Already loaded */
	if (!$flush && (mwexec("/sbin/ipfw list 1000", true) == 0)) {
		return;
	}

	$cprules = <<<EOD
{$flush_rule}
# Table with interfaces that have CP enabled
table cp_ifaces create type iface valtype skipto

# Redirect each CP interface to its specific rule
add 1000 skipto tablearg all from any to any via table(cp_ifaces)

# This interface has no cp zone configured
add 1100 allow all from any to any

# block everything else
add 65534 deny all from any to any
EOD;

	/* load rules */
	file_put_contents("{$g['tmp_path']}/ipfw.cp.rules", $cprules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw.cp.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
	unset($cprules);
}

/* Create a string with ipfw rule and increase rulenum */
function captiveportal_create_ipfw_rule($cmd, &$rulenum, $args) {
	$rule = "{$cmd} {$rulenum} {$args}\n";
	$rulenum++;

	return $rule;
}

/* Return first rule number for a cp zone */
function captiveportal_ipfw_ruleno($id) {
	global $g;

	return 2000 + $id * $g['captiveportal_rules_interval'];
}

/* reinit will disconnect all users, be careful! */
function captiveportal_init_rules($reinit = false) {
	global $config, $g, $cpzone, $cpzoneid;

	if (!isset($config['captiveportal'][$cpzone]['enable'])) {
		return;
	}

	captiveportal_load_modules();
	captiveportal_init_general_rules();

	/* Cleanup so nothing is leaked */
	captiveportal_free_dnrules();
	unlink_if_exists("{$g['vardb_path']}/captiveportal_{$cpzone}.rules");

	$skipto = captiveportal_ipfw_ruleno($cpzoneid);

	$cprules = '';

	$cpips = array();
	$ifaces = get_configured_interface_list();
	$cpinterfaces = explode(",", $config['captiveportal'][$cpzone]['interface']);
	$firsttime = 0;
	foreach ($cpinterfaces as $cpifgrp) {
		if (!isset($ifaces[$cpifgrp])) {
			continue;
		}
		$tmpif = get_real_interface($cpifgrp);
		if (empty($tmpif)) {
			continue;
		}

		$cpipm = get_interface_ip($cpifgrp);

		if (!is_ipaddr($cpipm)) {
			continue;
		}

		$cpips[] = $cpipm;
		if (is_array($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $vip) {
				if (($vip['interface'] == $cpifgrp) &&
				    (($vip['mode'] == "carp") ||
				    ($vip['mode'] == "ipalias"))) {
					$cpips[] = $vip['subnet'];
				}
			}
		}

		$cprules .= "table cp_ifaces add {$tmpif} {$skipto}\n";
	}
	if (count($cpips) > 0) {
		$cpactive = true;
	} else {
		return false;
	}

	if ($reinit == false) {
		$captiveportallck = lock("captiveportal{$cpzone}");
	}

	$rulenum = $skipto;
	$cprules .= "table {$cpzone}_pipe_mac create type mac valtype pipe\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg MAC table({$cpzone}_pipe_mac)");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "allow pfsync from any to any");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "allow carp from any to any\n");
	$cprules .= "# layer 2: pass ARP\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type arp,rarp");
	$cprules .= "# pfsense requires for WPA\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type 0x888e,0x88c7");
	$cprules .= "# PPP Over Ethernet Session Stage/Discovery Stage\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass layer2 mac-type 0x8863,0x8864\n");
	$cprules .= "# layer 2: block anything else non-IP(v4/v6)\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "deny layer2 not mac-type ip,ipv6");

	/* These tables contain host ips */
	$cprules .= "table {$cpzone}_host_ips create type addr\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from any to table({$cpzone}_host_ips) in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from table({$cpzone}_host_ips) to any out");
	foreach ($cpips as $cpip) {
		$cprules .= "table {$cpzone}_host_ips add {$cpip}\n";
	}
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from any to 255.255.255.255 in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass ip from 255.255.255.255 to any out");

	/* Allowed ips */
	$cprules .= "table {$cpzone}_allowed_up create type addr valtype pipe\n";
	$cprules .= "table {$cpzone}_allowed_down create type addr valtype pipe\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from table({$cpzone}_allowed_up) to any in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from any to table({$cpzone}_allowed_down) in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from table({$cpzone}_allowed_up) to any out");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from any to table({$cpzone}_allowed_down) out");

	/* Authenticated users rules. */
	$cprules .= "table {$cpzone}_auth_up create type addr valtype pipe\n";
	$cprules .= "table {$cpzone}_auth_down create type addr valtype pipe\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from table({$cpzone}_auth_up) to any layer2 in");
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pipe tablearg ip from any to table({$cpzone}_auth_down) layer2 out");

	if (!empty($config['captiveportal'][$cpzone]['listenporthttp'])) {
		$listenporthttp = $config['captiveportal'][$cpzone]['listenporthttp'];
	} else {
		$listenporthttp = 8000 + $cpzoneid;
	}

	if (isset($config['captiveportal'][$cpzone]['httpslogin'])) {
		if (!empty($config['captiveportal'][$cpzone]['listenporthttps'])) {
			$listenporthttps = $config['captiveportal'][$cpzone]['listenporthttps'];
		} else {
			$listenporthttps = 8001 + $cpzoneid;
		}
		if (!isset($config['captiveportal'][$cpzone]['nohttpsforwards'])) {
			$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
			    "fwd 127.0.0.1,{$listenporthttps} tcp from any to any dst-port 443 in");
		}
	}

	$cprules .= "# redirect non-authenticated clients to captive portal\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "fwd 127.0.0.1,{$listenporthttp} tcp from any to any dst-port 80 in");
	$cprules .= "# let the responses from the captive portal web server back out\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "pass tcp from any to any out");
	$cprules .= "# This CP zone is over, skip to last rule\n";
	$cprules .= captiveportal_create_ipfw_rule("add", $rulenum,
	    "skipto 65534 all from any to any");

	/* generate passthru mac database */
	$cprules .= captiveportal_passthrumac_configure(true);
	$cprules .= "\n";

	/* allowed ipfw rules to make allowed ip work */
	$cprules .= captiveportal_allowedip_configure();

	/* allowed ipfw rules to make allowed hostnames work */
	$cprules .= captiveportal_allowedhostname_configure();

	/* load rules */
	captiveportal_delete_rules();
	file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", $cprules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.cp.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.cp.rules");
	unset($cprules);

	captiveportal_filterdns_configure();

	if ($reinit == false) {
		unlock($captiveportallck);
	}
}

/* Delete all rules related to specific cpzone */
function captiveportal_delete_rules($pipes_to_remove = array()) {
	global $g, $cpzoneid, $cpzone;

	$skipto1 = captiveportal_ipfw_ruleno($cpzoneid);
	$skipto2 = $skipto1 + $g['captiveportal_rules_interval'];

	$cp_ifaces = pfSense_ipfw_table_list("cp_ifaces");
	if (is_array($cp_ifaces)) {
		foreach ($cp_ifaces as $cp_iface) {
			if (empty($cp_iface['skipto']) ||
			    $cp_iface['skipto'] != $skipto1) {
				continue;
			}

			pfSense_ipfw_table("cp_ifaces", IP_FW_TABLE_XDEL,
			    $cp_iface['iface']);
		}
	}

	mwexec("/sbin/ipfw delete {$skipto1}-{$skipto2}", true);

	$tables = captiveportal_get_ipfw_table_names();

	$delrules = "";
	foreach ($tables as $table) {
		$delrules .= "table {$table} destroy\n";
	}

	foreach ($pipes_to_remove as $pipeno) {
		$delrules .= "pipe delete {$pipeno}\n";
	}

	if (empty($delrules)) {
		return;
	}

	file_put_contents("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", $delrules);
	mwexec("/sbin/ipfw -q {$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules", true);
	@unlink("{$g['tmp_path']}/ipfw_{$cpzone}.deltable.rules");
}

/*
 * Remove clients that have been around for longer than the specified amount of time
 * db file structure:
 * timestamp,ipfw_rule_no,clientip,clientmac,username,sessionid,password,session_timeout,idle_timeout,session_terminate_time,interim_interval,traffic_quota,radiusctx
 * (password is in Base64 and only saved when reauthentication is enabled)
 */
function captiveportal_prune_old() {
	global $g, $config, $cpzone, $cpzoneid;

	if (empty($cpzone)) {
		return;
	}

	$cpcfg = $config['captiveportal'][$cpzone];
	$vcpcfg = $config['voucher'][$cpzone];

	/* check for expired entries */
	$idletimeout = 0;
	$timeout = 0;
	if (!empty($cpcfg['timeout']) && is_numeric($cpcfg['timeout'])) {
		$timeout = $cpcfg['timeout'] * 60;
	}

	if (!empty($cpcfg['idletimeout']) && is_numeric($cpcfg['idletimeout'])) {
		$idletimeout = $cpcfg['idletimeout'] * 60;
	}

	/* check for entries exceeding their traffic quota */
	$trafficquota = 0;
	if (!empty($cpcfg['trafficquota']) && is_numeric($cpcfg['trafficquota'])) {
		$trafficquota = $cpcfg['trafficquota'] * 1048576;
	}

	/* Is there any job to do? */
	if (!$timeout && !$idletimeout && !$trafficquota && !isset($cpcfg['reauthenticate']) &&
	    !isset($cpcfg['radiussession_timeout']) && !isset($cpcfg['radiustraffic_quota']) &&
	    !isset($vcpcfg['enable'])) {
		return;
	}

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
	foreach ($cpdb as $cpentry) {
		$stop_time = $pruning_time;

		$timedout = false;
		$term_cause = 1;
		$logout_cause = 'TIMEOUT';
		if (empty($cpentry[12])) {
			$cpentry[12] = 'first';
		}
		$radiusservers = $radiussrvs[$cpentry[12]];

		/* hard timeout or session_timeout from radius if enabled */
		if (isset($cpcfg['radiussession_timeout'])) {
			$timeout = (is_numeric($cpentry[7])) ? $cpentry[7] : $timeout;
		}
		if ($timeout) {
			if (($pruning_time - $cpentry[0]) >= $timeout) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
			}
		}

		/* Session-Terminate-Time */
		if (!$timedout && !empty($cpentry[9])) {
			if ($pruning_time >= $cpentry[9]) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
			}
		}

		/* check if the radius idle_timeout attribute has been set and if its set change the idletimeout to this value */
		$uidletimeout = (is_numeric($cpentry[8])) ? $cpentry[8] : $idletimeout;
		/* if an idle timeout is specified, get last activity timestamp from ipfw */
		if (!$timedout && $uidletimeout > 0) {
			$lastact = captiveportal_get_last_activity($cpentry[2]);
			/*	If the user has logged on but not sent any traffic they will never be logged out.
			 *	We "fix" this by setting lastact to the login timestamp.
			 */
			$lastact = $lastact ? $lastact : $cpentry[0];
			if ($lastact && (($pruning_time - $lastact) >= $uidletimeout)) {
				$timedout = true;
				$term_cause = 4; // Idle-Timeout
				$logout_cause = 'IDLE TIMEOUT';
				if (!isset($config['captiveportal'][$cpzone]['includeidletime'])) {
					$stop_time = $lastact;
				}
			}
		}

		/* if vouchers are configured, activate session timeouts */
		if (!$timedout && isset($vcpcfg['enable']) && !empty($cpentry[7])) {
			if ($pruning_time >= ($cpentry[0] + $cpentry[7])) {
				$timedout = true;
				$term_cause = 5; // Session-Timeout
				$logout_cause = 'SESSION TIMEOUT';
				$voucher_needs_sync = true;
			}
		}

		/* traffic quota, value retrieved from the radius attribute if the option is enabled */
		if (isset($cpcfg['radiustraffic_quota'])) {
			$trafficquota = (is_numeric($cpentry[11])) ? $cpentry[11] : $trafficquota;
		}
		if (!$timedout && $trafficquota > 0) {
			$volume = getVolume($cpentry[2], $cpentry[3]);
			if (($volume['input_bytes'] + $volume['output_bytes']) > $trafficquota) {
				$timedout = true;
				$term_cause = 10; // NAS-Request
				$logout_cause = 'QUOTA EXCEEDED';
			}
		}

		if ($timedout) {
			captiveportal_disconnect($cpentry, $radiusservers, $term_cause, $stop_time);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], $logout_cause);
			$unsetindexes[] = $cpentry[5];
		}

		/* do periodic RADIUS reauthentication? */
		if (!$timedout && !empty($radiusservers)) {
			if (isset($cpcfg['radacct_enable'])) {
				if (substr($cpcfg['reauthenticateacct'], 0, 9) == "stopstart") {
					/* stop and restart accounting */
					if ($cpcfg['reauthenticateacct'] == "stopstartfreeradius") {
						$rastart_time = 0;
						$rastop_time = 60;
					} else {
						$rastart_time = $cpentry[0];
						$rastop_time = null;
					}
					RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$rastart_time, // start time
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						10, // NAS Request
						false, // Not an interim request
						$rastop_time); // Stop Time
					$clientsn = (is_ipaddrv6($cpentry[2])) ? 128 : 32;
					pfSense_ipfw_table_zerocnt("{$cpzone}_auth_up", "{$cpentry[2]}/{$clientsn}");
					pfSense_ipfw_table_zerocnt("{$cpzone}_auth_down", "{$cpentry[2]}/{$clientsn}");
					if ($cpcfg['reauthenticateacct'] == "stopstartfreeradius") {
						/* Need to pause here or the FreeRADIUS server gets confused about packet ordering. */
						sleep(1);
					}
					RADIUS_ACCOUNTING_START($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$radiusservers,
						$cpentry[2], // clientip
						$cpentry[3]); // clientmac
				} else if ($cpcfg['reauthenticateacct'] == "interimupdate") {
					$session_time = $pruning_time - $cpentry[0];
					if (!empty($cpentry[10]) && $cpentry[10] > 60) {
						$interval = $cpentry[10];
					} else {
						$interval = 0;
					}
					$past_interval_min = ($session_time > $interval);
					if ($interval != 0) {
						$within_interval = ($session_time % $interval >= 0 && $session_time % $interval <= 59);
					}
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
				} else if ($auth_list['auth_val'] == 2) {
					captiveportal_reapply_attributes($cpentry, $auth_list);
				}
			}
		}
	}
	unset($cpdb);

	captiveportal_prune_old_automac();

	if ($voucher_needs_sync == true) {
		/* Trigger a sync of the vouchers on config */
		send_event("service sync vouchers");
	}

	/* write database */
	if (!empty($unsetindexes)) {
		captiveportal_remove_entries($unsetindexes);
	}
}

function captiveportal_prune_old_automac() {
	global $g, $config, $cpzone, $cpzoneid;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac']) &&
	    isset($config['captiveportal'][$cpzone]['passthrumacaddusername'])) {
		$tmpvoucherdb = array();
		$macrules = "";
		$writecfg = false;
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $eid => $emac) {
			if ($emac['logintype'] != "voucher") {
				continue;
			}
			if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
				if (isset($tmpvoucherdb[$emac['username']])) {
					$temac = $config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]];
					$pipeno = captiveportal_get_dn_passthru_ruleno($temac['mac']);
					if ($pipeno) {
						captiveportal_free_dn_ruleno($pipeno);
						$macrules .= "table {$cpzone}_pipe_mac delete any,{$temac['mac']}\n";
						$macrules .= "table {$cpzone}_pipe_mac delete {$temac['mac']},any\n";
						$macrules .= "pipe delete {$pipeno}\n";
						++$pipeno;
						$macrules .= "pipe delete {$pipeno}\n";
					}
					$writecfg = true;
					captiveportal_logportalauth($temac['username'], $temac['mac'],
					    $temac['ip'], "DUPLICATE {$temac['username']} LOGIN - TERMINATING OLD SESSION");
					unset($config['captiveportal'][$cpzone]['passthrumac'][$tmpvoucherdb[$emac['username']]]);
				}
				$tmpvoucherdb[$emac['username']] = $eid;
			}
			if (voucher_auth($emac['username']) <= 0) {
				$pipeno = captiveportal_get_dn_passthru_ruleno($emac['mac']);
				if ($pipeno) {
					captiveportal_free_dn_ruleno($pipeno);
					$macrules .= "table {$cpzone}_pipe_mac delete any,{$emac['mac']}\n";
					$macrules .= "table {$cpzone}_pipe_mac delete {$emac['mac']},any\n";
					$macrules .= "pipe delete {$pipeno}\n";
					++$pipeno;
					$macrules .= "pipe delete {$pipeno}\n";
				}
				$writecfg = true;
				captiveportal_logportalauth($emac['username'], $emac['mac'],
				    $emac['ip'], "EXPIRED {$emac['username']} LOGIN - TERMINATING SESSION");
				unset($config['captiveportal'][$cpzone]['passthrumac'][$eid]);
			}
		}
		unset($tmpvoucherdb);
		if (!empty($macrules)) {
			@file_put_contents("{$g['tmp_path']}/macentry.prunerules.tmp", $macrules);
			unset($macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry.prunerules.tmp");
		}
		if ($writecfg === true) {
			write_config("Prune session for auto-added macs");
		}
	}
}

/* remove a single client according to the DB entry */
function captiveportal_disconnect($dbent, $radiusservers, $term_cause = 1,
    $stop_time = null) {
	global $g, $config, $cpzone, $cpzoneid;

	$stop_time = (empty($stop_time)) ? time() : $stop_time;

	/* this client needs to be deleted - remove ipfw rules */
	if (isset($config['captiveportal'][$cpzone]['radacct_enable']) &&
	    !empty($radiusservers)) {
		if ($config['captiveportal'][$cpzone]['reauthenticateacct'] ==
		    "stopstartfreeradius") {
			/*
			 * Interim updates are on so the session time must be
			 * reported as the elapsed time since the previous
			 * interim update.
			 */
			$session_time = ($stop_time - $dbent[0]) % 60;
			$start_time = $stop_time - $session_time;
		} else {
			$start_time = $dbent[0];
		}
		RADIUS_ACCOUNTING_STOP($dbent[1], // ruleno
			$dbent[4], // username
			$dbent[5], // sessionid
			$start_time, // start time
			$radiusservers,
			$dbent[2], // clientip
			$dbent[3], // clientmac
			$term_cause, // Acct-Terminate-Cause
			false,
			$stop_time);
	}

	if (is_ipaddr($dbent[2])) {
		/*
		 * Delete client's ip entry from tables auth_up and auth_down.
		 * It's not necessary to explicit specify mac address here
		 */
		$cpsession = captiveportal_isip_logged($dbent[2]);
		if (!empty($cpsession)) {
			$clientsn = (is_ipaddrv6($dbent[2])) ? 128 : 32;
			pfSense_ipfw_table("{$cpzone}_auth_up",
			    IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
			pfSense_ipfw_table("{$cpzone}_auth_down",
			    IP_FW_TABLE_XDEL, "{$dbent[2]}/{$clientsn}");
		}
		/* XXX: Redundant?! Ensure all pf(4) states are killed. */
		$_gb = @pfSense_kill_states($dbent[2]);
		$_gb = @pfSense_kill_srcstates($dbent[2]);
	}

	/*
	 * These are the pipe numbers we use to control traffic shaping for
	 * each logged in user via captive portal
	 * We could get an error if the pipe doesn't exist but everything
	 * should still be fine
	 */
	if (!empty($dbent[1])) {
		/*
		 * Call captiveportal_free_dnrules() in dry_run mode to verify
		 * if there are pipes to be removed and prevent the attempt to
		 * delete invalid pipes
		 */
		$removed_pipes = captiveportal_free_dnrules($dbent[1],
		    $dbent[1]+1, true);

		if (!empty($removed_pipes)) {
			$_gb = @pfSense_ipfw_pipe("pipe delete {$dbent[1]}");
			$_gb = @pfSense_ipfw_pipe("pipe delete " .
			    ($dbent[1]+1));

			/*
			 * Release the ruleno so it can be reallocated to new
			 * clients
			 */
			captiveportal_free_dn_ruleno($dbent[1]);
		}
	}

	// XMLRPC Call over to the master Voucher node
	if (xmlrpc_sync_voucher_details($syncip, $syncport,
	    $vouchersyncusername, $syncpass)) {
		$remote_status = xmlrpc_sync_voucher_disconnect($dbent, $syncip,
		    $syncport, $syncpass, $vouchersyncusername, $term_cause,
		    $stop_time);
	}

}

/* remove a single client by sessionid */
function captiveportal_disconnect_client($sessionid, $term_cause = 1, $logoutReason = "LOGOUT") {
	global $g, $config;

	$sessionid = SQLite3::escapeString($sessionid);
	$radiusservers = captiveportal_get_radius_servers();

	/* read database */
	$result = captiveportal_read_db("WHERE sessionid = '{$sessionid}'");

	/* find entry */
	if (!empty($result)) {

		foreach ($result as $cpentry) {
			if (empty($cpentry[12])) {
				$cpentry[12] = 'first';
			}
			captiveportal_disconnect($cpentry, $radiusservers[$cpentry[12]], $term_cause);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");
		}
		captiveportal_write_db("DELETE FROM captiveportal WHERE sessionid = '{$sessionid}'");
		unset($result);
	}
}

/* remove all clients */
function captiveportal_disconnect_all($term_cause = 6, $logoutReason = "DISCONNECT") {
	global $g, $config, $cpzone, $cpzoneid;

	/* check if we're pruning old entries and eventually wait */
	$rcprunelock = try_lock("rcprunecaptiveportal{$cpzone}", 15);

	/* if we still don't have the lock, unlock forcefully and take it */
	if (!$rcprunelock) {
		log_error("CP zone ${cpzone}: could not obtain the lock for more than 15 seconds, lock taken forcefully to disconnect all users");
		unlock_force("rcprunecaptiveportal{$cpzone}");
		$rcprunelock = lock("rcprunecaptiveportal{$cpzone}", LOCK_EX);
	}

	/* take a lock so new users won't be able to log in */
	$cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

	captiveportal_radius_stop_all($term_cause, $logoutReason);

	/* remove users from the database */
	$cpdb = captiveportal_read_db();
	$unsetindexes = array_column($cpdb,5);
	if (!empty($unsetindexes)) {
		captiveportal_remove_entries($unsetindexes);
	}

	/* reinit ipfw rules */
	captiveportal_init_rules(true);

	unlock($cpdblck);
	unlock($rcprunelock);
}

/* send RADIUS acct stop for all current clients */
function captiveportal_radius_stop_all($term_cause = 6, $logoutReason = "DISCONNECT") {
	global $g, $config, $cpzone, $cpzoneid;

	$cpdb = captiveportal_read_db();

	$radacct = isset($config['captiveportal'][$cpzone]['radacct_enable']) ? true : false;
	if ($radacct) {
		$radiusservers = captiveportal_get_radius_servers();
	}

	foreach ($cpdb as $cpentry) {
		if ($radacct) {
			if (!empty($radiusservers)) {
				if (empty($cpentry[12])) {
					$cpentry[12] = 'first';
				}
				if (!empty($radiusservers[$cpentry[12]])) {
					RADIUS_ACCOUNTING_STOP($cpentry[1], // ruleno
						$cpentry[4], // username
						$cpentry[5], // sessionid
						$cpentry[0], // start time
						$radiusservers[$cpentry[12]],
						$cpentry[2], // clientip
						$cpentry[3], // clientmac
						$term_cause);
				}
			}
		}
		captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], $logoutReason);
	}
	unset($cpdb);
}

function captiveportal_passthrumac_configure_entry($macent, $pipeinrule = false) {
	global $config, $g, $cpzone;

	$bwUp = 0;
	if (!empty($macent['bw_up'])) {
		$bwUp = $macent['bw_up'];
	} else if (!empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
		$bwUp = $config['captiveportal'][$cpzone]['bwdefaultup'];
	}
	$bwDown = 0;
	if (!empty($macent['bw_down'])) {
		$bwDown = $macent['bw_down'];
	} else if (!empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
		$bwDown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
	}

	if ($macent['action'] == 'pass') {
		$rules = "";

		$pipeno = captiveportal_get_next_dn_ruleno();

		$pipeup = $pipeno;
		if ($pipeinrule == true) {
			$_gb = @pfSense_ipfw_pipe("pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16");
		} else {
			$rules .= "pipe {$pipeno} config bw {$bwUp}Kbit/s queue 100 buckets 16\n";
		}

		$pipedown = $pipeno + 1;
		if ($pipeinrule == true) {
			$_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16");
		} else {
			$rules .= "pipe {$pipedown} config bw {$bwDown}Kbit/s queue 100 buckets 16\n";
		}

		$rules .= "table {$cpzone}_pipe_mac add any,{$macent['mac']} {$pipeup}\n";
		$rules .= "table {$cpzone}_pipe_mac add {$macent['mac']},any {$pipedown}\n";
	}

	return $rules;
}

function captiveportal_passthrumac_delete_entry($macent) {
	global $cpzone;
	$rules = "";

	if ($macent['action'] == 'pass') {
		$pipeno = captiveportal_get_dn_passthru_ruleno($macent['mac']);

		if (!empty($pipeno)) {
			captiveportal_free_dn_ruleno($pipeno);
			$rules .= "table {$cpzone}_pipe_mac delete any,{$macent['mac']}\n";
			$rules .= "table {$cpzone}_pipe_mac delete {$macent['mac']},any\n";
			$rules .= "pipe delete " . $pipeno . "\n";
			$rules .= "pipe delete " . ++$pipeno . "\n";
		}
	}

	return $rules;
}

function captiveportal_passthrumac_configure($filename = false, $startindex = 0, $stopindex = 0) {
	global $config, $g, $cpzone;

	$rules = "";

	if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		if ($stopindex > 0) {
			$fd = fopen($filename, "w");
			for ($idx = $startindex; $idx <= $stopindex; $idx++) {
				if (isset($config['captiveportal'][$cpzone]['passthrumac'][$idx])) {
					$rules = captiveportal_passthrumac_configure_entry($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
					fwrite($fd, $rules);
				}
			}
			fclose($fd);

			return;
		} else {
			$nentries = count($config['captiveportal'][$cpzone]['passthrumac']);
			if ($nentries > 2000) {
				$nloops = $nentries / 1000;
				$remainder= $nentries % 1000;
				for ($i = 0; $i < $nloops; $i++) {
					mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . ((($i+1) * 1000) - 1) . "\"");
				}
				if ($remainder > 0) {
					mwexec_bg("/usr/local/sbin/fcgicli -f /etc/rc.captiveportal_configure_mac -d \"cpzone={$cpzone}&startidx=" . ($i * 1000) . "&stopidx=" . (($i* 1000) + $remainder) ."\"");
				}
			} else {
				foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
					$rules .= captiveportal_passthrumac_configure_entry($macent, true);
				}
			}
		}
	}

	return $rules;
}

function captiveportal_passthrumac_findbyname($username) {
	global $config, $cpzone;

	if (is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $macent) {
			if ($macent['username'] == $username) {
				return $macent;
			}
		}
	}
	return NULL;
}

/*
 * table (3=IN)/(4=OUT) hold allowed ip's without bw limits
 */
function captiveportal_allowedip_configure_entry($ipent, $ishostname = false) {
	global $g, $config, $cpzone;

	/*  Instead of copying this entire function for something
	 *  easy such as hostname vs ip address add this check
	 */
	if ($ishostname === true) {
		if (!platform_booting()) {
			$ipaddress = gethostbyname($ipent['hostname']);
			if (!is_ipaddr($ipaddress)) {
				return;
			}
		} else {
			$ipaddress = "";
		}
	} else {
		$ipaddress = $ipent['ip'];
	}

	$rules = "";
	$cp_filterdns_conf = "";
	$enBwup = 0;
	if (!empty($ipent['bw_up'])) {
		$enBwup = intval($ipent['bw_up']);
	} else if (!empty($config['captiveportal'][$cpzone]['bwdefaultup'])) {
		$enBwup = $config['captiveportal'][$cpzone]['bwdefaultup'];
	}
	$enBwdown = 0;
	if (!empty($ipent['bw_down'])) {
		$enBwdown = intval($ipent['bw_down']);
	} else if (!empty($config['captiveportal'][$cpzone]['bwdefaultdn'])) {
		$enBwdown = $config['captiveportal'][$cpzone]['bwdefaultdn'];
	}

	$pipeup = captiveportal_get_next_dn_ruleno();
	$_gb = @pfSense_ipfw_pipe("pipe {$pipeup} config bw {$enBwup}Kbit/s queue 100 buckets 16");
	$pipedown = $pipeup + 1;
	$_gb = @pfSense_ipfw_pipe("pipe {$pipedown} config bw {$enBwdown}Kbit/s queue 100 buckets 16");

	if ($ishostname === true) {
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_up pipe {$pipeup}\n";
		$cp_filterdns_conf .= "ipfw {$ipent['hostname']} {$cpzone}_allowed_down pipe {$pipedown}\n";
		if (!is_ipaddr($ipaddress)) {
			return array("", $cp_filterdns_conf);
		}
	}

	$subnet = "";
	if (!empty($ipent['sn'])) {
		$subnet = "/{$ipent['sn']}";
	}
	$rules .= "table {$cpzone}_allowed_up add {$ipaddress}{$subnet} {$pipeup}\n";
	$rules .= "table {$cpzone}_allowed_down add {$ipaddress}{$subnet} {$pipedown}\n";

	if ($ishostname === true) {
		return array($rules, $cp_filterdns_conf);
	} else {
		return $rules;
	}
}

function captiveportal_allowedhostname_configure() {
	global $config, $g, $cpzone, $cpzoneid;

	$rules = "";
	if (!is_array($config['captiveportal'][$cpzone]['allowedhostname'])) {
		return $rules;
	}

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

	return $rules;
}

function captiveportal_filterdns_configure() {
	global $config, $g, $cpzone, $cpzoneid;

	$cp_filterdns_filename = $g['varetc_path'] .
	    "/filterdns-{$cpzone}-captiveportal.conf";

	if (isset($config['captiveportal'][$cpzone]['enable']) &&
	    is_array($config['captiveportal'][$cpzone]['allowedhostname']) &&
	    file_exists($cp_filterdns_filename)) {
		if (isvalidpid($g['varrun_path'] .
		    "/filterdns-{$cpzone}-cpah.pid")) {
			sigkillbypid($g['varrun_path'] .
			    "/filterdns-{$cpzone}-cpah.pid", "HUP");
		} else {
			mwexec("/usr/local/sbin/filterdns -p " .
			    "{$g['varrun_path']}/filterdns-{$cpzone}-cpah.pid" .
			    " -i 300 -c {$cp_filterdns_filename} -d 1");
		}
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
		foreach ($config['captiveportal'][$cpzone]['allowedip'] as $ipent) {
			$rules .= captiveportal_allowedip_configure_entry($ipent);
		}
	}

	return $rules;
}

/* get last activity timestamp given client IP address */
function captiveportal_get_last_activity($ip) {
	global $cpzone;

	/* Reading only from one of the tables is enough of approximation. */
	$tables = array("{$cpzone}_allowed_up", "{$cpzone}_auth_up");

	foreach ($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup($table, $ip);
		if (is_array($ipfw)) {
			/* Workaround for #46652 */
			if ($ipfw['packets'] > 0) {
				return $ipfw['timestamp'];
			} else {
				return 0;
			}
		}
	}

	return 0;
}

function captiveportal_init_radius_servers() {
	global $config, $g, $cpzone;

	/* generate radius server database */
	if ($config['captiveportal'][$cpzone]['radiusip'] &&
	    (!isset($config['captiveportal'][$cpzone]['auth_method']) || $config['captiveportal'][$cpzone]['auth_method'] == "radius")) {
		$radiusip = $config['captiveportal'][$cpzone]['radiusip'];
		$radiusip2 = ($config['captiveportal'][$cpzone]['radiusip2']) ? $config['captiveportal'][$cpzone]['radiusip2'] : null;
		$radiusip3 = ($config['captiveportal'][$cpzone]['radiusip3']) ? $config['captiveportal'][$cpzone]['radiusip3'] : null;
		$radiusip4 = ($config['captiveportal'][$cpzone]['radiusip4']) ? $config['captiveportal'][$cpzone]['radiusip4'] : null;

		if ($config['captiveportal'][$cpzone]['radiusport']) {
			$radiusport = $config['captiveportal'][$cpzone]['radiusport'];
		} else {
			$radiusport = 1812;
		}
		if ($config['captiveportal'][$cpzone]['radiusacctport']) {
			$radiusacctport = $config['captiveportal'][$cpzone]['radiusacctport'];
		} else {
			$radiusacctport = 1813;
		}
		if ($config['captiveportal'][$cpzone]['radiusport2']) {
			$radiusport2 = $config['captiveportal'][$cpzone]['radiusport2'];
		} else {
			$radiusport2 = 1812;
		}
		if ($config['captiveportal'][$cpzone]['radiusport3']) {
			$radiusport3 = $config['captiveportal'][$cpzone]['radiusport3'];
		} else {
			$radiusport3 = 1812;
		}
		if ($config['captiveportal'][$cpzone]['radiusport4']) {
			$radiusport4 = $config['captiveportal'][$cpzone]['radiusport4'];
		} else {
			$radiusport4 = 1812;
		}

		$radiuskey = $config['captiveportal'][$cpzone]['radiuskey'];
		$radiuskey2 = $config['captiveportal'][$cpzone]['radiuskey2'];
		$radiuskey3 = $config['captiveportal'][$cpzone]['radiuskey3'];
		$radiuskey4 = $config['captiveportal'][$cpzone]['radiuskey4'];

		$cprdsrvlck = lock("captiveportalradius{$cpzone}", LOCK_EX);
		$fd = @fopen("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db", "w");
		if (!$fd) {
			captiveportal_syslog("Error: cannot open RADIUS DB file in captiveportal_configure().\n");
			unlock($cprdsrvlck);
			return 1;
		}
		if (isset($radiusip)) {
			fwrite($fd, $radiusip . "," . $radiusport . "," . $radiusacctport . "," . $radiuskey . ",first");
		}
		if (isset($radiusip2)) {
			fwrite($fd, "\n" . $radiusip2 . "," . $radiusport2 . "," . $radiusacctport . "," . $radiuskey2 . ",first");
		}
		if (isset($radiusip3)) {
			fwrite($fd, "\n" . $radiusip3 . "," . $radiusport3 . "," . $radiusacctport . "," . $radiuskey3 . ",second");
		}
		if (isset($radiusip4)) {
			fwrite($fd, "\n" . $radiusip4 . "," . $radiusport4 . "," . $radiusacctport . "," . $radiuskey4 . ",second");
		}

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
			foreach ($cpradiusdb as $cpradiusentry) {
				$line = trim($cpradiusentry);
				if ($line) {
					$radsrv = array();
					list($radsrv['ipaddr'], $radsrv['port'], $radsrv['acctport'], $radsrv['key'], $context) = explode(",", $line);
				}
				if (empty($context)) {
					if (!is_array($radiusservers['first'])) {
						$radiusservers['first'] = array();
					}
					$radiusservers['first'] = $radsrv;
				} else {
					if (!is_array($radiusservers[$context])) {
						$radiusservers[$context] = array();
					}
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
function captiveportal_logportalauth($user, $mac, $ip, $status, $message = null) {
	// Log it
	if (!$message) {
		$message = "{$status}: {$user}, {$mac}, {$ip}";
	} else {
		$message = trim($message);
		$message = "{$status}: {$user}, {$mac}, {$ip}, {$message}";
	}
	captiveportal_syslog($message);
}

/* log simple messages to syslog */
function captiveportal_syslog($message) {
	global $cpzone;

	$message = trim($message);
	$message = "Zone: {$cpzone} - {$message}";
	openlog("logportalauth", LOG_PID, LOG_LOCAL4);
	// Log it
	syslog(LOG_INFO, $message);
	closelog();
}

function radius($username, $password, $clientip, $clientmac, $type, $radiusctx = null) {
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

	if (is_null($radiusctx)) {
		$radiusctx = 'first';
	}

	$auth_list = RADIUS_AUTHENTICATION($username,
		$password,
		$radiusservers[$radiusctx],
		$clientip,
		$clientmac,
		$pipeno);

	if ($auth_list['auth_val'] == 2) {
		captiveportal_logportalauth($username, $clientmac, $clientip, $type);
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
	global $g, $config, $cpzone, $cpzoneid;

	$db_path = "{$g['vardb_path']}/captiveportal{$cpzone}.db";
	$createquery = "CREATE TABLE IF NOT EXISTS captiveportal (" .
				"allow_time INTEGER, pipeno INTEGER, ip TEXT, mac TEXT, username TEXT, " .
				"sessionid TEXT, bpassword TEXT, session_timeout INTEGER, idle_timeout INTEGER, " .
				"session_terminate_time INTEGER, interim_interval INTEGER, traffic_quota INTEGER, " .
				"radiusctx TEXT); " .
			"CREATE UNIQUE INDEX IF NOT EXISTS idx_active ON captiveportal (sessionid, username); " .
			"CREATE INDEX IF NOT EXISTS user ON captiveportal (username); " .
			"CREATE INDEX IF NOT EXISTS ip ON captiveportal (ip); " .
			"CREATE INDEX IF NOT EXISTS starttime ON captiveportal (allow_time)";

	try {
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
	} catch (Exception $e) {
		captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Trying again.");
		unlink_if_exists($db_path);
		try {
			$DB = new SQLite3($db_path);
			$DB->busyTimeout(60000);
		} catch (Exception $e) {
			captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: " . $e->getMessage() . " -- Remove the database file manually and ensure there is enough free space.");
			return;
		}
	}

	if (!$DB) {
		captiveportal_syslog("Could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Trying again.");
		unlink_if_exists($db_path);
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
		if (!$DB) {
			captiveportal_syslog("Still could not open {$db_path} as an sqlite database for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and ensure there is enough free space.");
			return;
		}
	}

	if (! $DB->exec($createquery)) {
		captiveportal_syslog("Error during table {$cpzone} creation. Error message: {$DB->lastErrorMsg()}. Resetting and trying again.");

		/* If unable to initialize the database, reset and try again. */
		$DB->close();
		unset($DB);
		unlink_if_exists($db_path);
		$DB = new SQLite3($db_path);
		$DB->busyTimeout(60000);
		if ($DB->exec($createquery)) {
			captiveportal_syslog("Successfully reinitialized tables for {$cpzone} -- database has been reset.");
			if (!is_numericint($cpzoneid)) {
				if (is_array($config['captiveportal'])) {
					foreach ($config['captiveportal'] as $cpkey => $cp) {
						if ($cpzone == $cpkey) {
							$cpzoneid = $cp['zoneid'];
						}
					}
				}
			}
			if (is_numericint($cpzoneid)) {
				$table_names = captiveportal_get_ipfw_table_names();
				foreach ($table_names as $table_name) {
					mwexec("/sbin/ipfw table {$table_name} flush");
				}
				captiveportal_syslog("Flushed tables for {$cpzone} after database reset.");
			}
		} else {
			captiveportal_syslog("Still unable to create tables for {$cpzone}. Error message: {$DB->lastErrorMsg()}. Remove the database file manually and try again.");
		}
	}

	return $DB;
}

/* Get all tables for specific cpzone */
function captiveportal_get_ipfw_table_names() {
	global $cpzone;

	$result = array();
	$tables = pfSense_ipfw_tables_list();

	if (!is_array($tables)) {
		return $result;
	}

	$len = strlen($cpzone) + 1;
	foreach ($tables as $table) {
		if (substr($table['name'], 0, $len) != $cpzone . '_') {
			continue;
		}

		$result[] = $table['name'];
	}

	return $result;
}

/* read captive portal DB into array */
function captiveportal_read_db($query = "") {
	$cpdb = array();

	$DB = captiveportal_opendb();
	if ($DB) {
		$response = $DB->query("SELECT * FROM captiveportal {$query}");
		if ($response != FALSE) {
			while ($row = $response->fetchArray()) {
				$cpdb[] = $row;
			}
		}
		$DB->close();
	}

	return $cpdb;
}

function captiveportal_remove_entries($remove) {

	if (!is_array($remove) || empty($remove)) {
		return;
	}

	$query = "DELETE FROM captiveportal WHERE sessionid in (";
	foreach ($remove as $idx => $unindex) {
		$query .= "'{$unindex}'";
		if ($idx < (count($remove) - 1)) {
			$query .= ",";
		}
	}
	$query .= ")";
	captiveportal_write_db($query);
}

/* write captive portal DB */
function captiveportal_write_db($queries) {
	global $g;

	if (is_array($queries)) {
		$query = implode(";", $queries);
	} else {
		$query = $queries;
	}

	$DB = captiveportal_opendb();
	if ($DB) {
		$DB->exec("BEGIN TRANSACTION");
		$result = $DB->exec($query);
		if (!$result) {
			captiveportal_syslog("Trying to modify DB returned error: {$DB->lastErrorMsg()}");
		} else {
			$DB->exec("END TRANSACTION");
		}
		$DB->close();
		return $result;
	} else {
		return true;
	}
}

function captiveportal_write_elements() {
	global $g, $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];

	if (!is_dir($g['captiveportal_element_path'])) {
		@mkdir($g['captiveportal_element_path']);
	}

	if (is_array($cpcfg['element'])) {
		foreach ($cpcfg['element'] as $data) {
			/* Do not attempt to decode or write out empty files. */
			if (empty($data['content']) || empty(base64_decode($data['content']))) {
				unlink_if_exists("{$g['captiveportal_element_path']}/{$data['name']}");
				touch("{$g['captiveportal_element_path']}/{$data['name']}");
			} elseif (!@file_put_contents("{$g['captiveportal_element_path']}/{$data['name']}", base64_decode($data['content']))) {
				printf(gettext('Error: cannot open \'%1$s\' in captiveportal_write_elements()%2$s'), $data['name'], "\n");
				return 1;
			}
			if (!file_exists("{$g['captiveportal_path']}/{$data['name']}")) {
				@symlink("{$g['captiveportal_element_path']}/{$data['name']}", "{$g['captiveportal_path']}/{$data['name']}");
			}
		}
	}

	return 0;
}

function captiveportal_free_dnrules($rulenos_start = 2000,
    $rulenos_range_max = 64500, $dry_run = false) {
	global $g, $cpzone;

	$removed_pipes = array();

	if (!file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		return $removed_pipes;
	}

	if (!$dry_run) {
		$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	}

	$rules = unserialize(file_get_contents(
	    "{$g['vardb_path']}/captiveportaldn.rules"));
	$ridx = $rulenos_start;
	while ($ridx < $rulenos_range_max) {
		if ($rules[$ridx] == $cpzone) {
			if (!$dry_run) {
				$rules[$ridx] = false;
			}
			$removed_pipes[] = $ridx;
			$ridx++;
			if (!$dry_run) {
				$rules[$ridx] = false;
			}
			$removed_pipes[] = $ridx;
			$ridx++;
		} else {
			$ridx += 2;
		}
	}

	if (!$dry_run) {
		file_put_contents("{$g['vardb_path']}/captiveportaldn.rules",
		    serialize($rules));
		unlock($cpruleslck);
	}

	unset($rules);

	return $removed_pipes;
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
	if (!isset($cpcfg['enable'])) {
		return NULL;
	}

	$cpruleslck = lock("captiveportalrulesdn", LOCK_EX);
	$ruleno = NULL;
	if (file_exists("{$g['vardb_path']}/captiveportaldn.rules")) {
		unset($output);
		$item = pfSense_ipfw_table_lookup("{$cpzone}_pipe_mac",
		    "any,{$value}");
		if (!is_array($item) || empty($item['pipe'])) {
			unlock($cpruleslck);
			return NULL;
		}

		$ruleno = intval($item['pipe']);
		$rules = unserialize(file_get_contents("{$g['vardb_path']}/captiveportaldn.rules"));
		if (!$rules[$ruleno]) {
			$ruleno = NULL;
		}
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

function getVolume($ip) {
	global $config, $cpzone;

	$reverse = isset($config['captiveportal'][$cpzone]['reverseacct'])
	    ? true : false;
	$volume = array();
	// Initialize vars properly, since we don't want NULL vars
	$volume['input_pkts'] = $volume['input_bytes'] = 0;
	$volume['output_pkts'] = $volume['output_bytes'] = 0;

	$tables = array("allowed", "auth");

	foreach($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_up", $ip);
		if (!is_array($ipfw)) {
			continue;
		}
		if ($reverse) {
			$volume['output_pkts'] = $ipfw['packets'];
			$volume['output_bytes'] = $ipfw['bytes'];
		} else {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		}
	}

	foreach($tables as $table) {
		$ipfw = pfSense_ipfw_table_lookup("{$cpzone}_{$table}_down",
		    $ip);
		if (!is_array($ipfw)) {
			continue;
		}
		if ($reverse) {
			$volume['input_pkts'] = $ipfw['packets'];
			$volume['input_bytes'] = $ipfw['bytes'];
		} else {
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

function getNasIP() {
	global $config, $cpzone;

	if (empty($config['captiveportal'][$cpzone]['radiussrcip_attribute'])) {
			$nasIp = get_interface_ip();
	} else {
		if (is_ipaddr($config['captiveportal'][$cpzone]['radiussrcip_attribute'])) {
			$nasIp = $config['captiveportal'][$cpzone]['radiussrcip_attribute'];
		} else {
			$nasIp = get_interface_ip($config['captiveportal'][$cpzone]['radiussrcip_attribute']);
		}
	}

	if (!is_ipaddr($nasIp)) {
		$nasIp = "0.0.0.0";
	}

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
		if (ip_in_subnet($cliip, "{$ip}/{$sn}")) {
			return $ip;
		}
	}

	$inet = ($isipv6) ? '-inet6' : '-inet';
	$iface = exec_command("/sbin/route -n get {$inet} {$cliip} | /usr/bin/awk '/interface/ { print \$2; };'");
	$iface = trim($iface, "\n");
	if (!empty($iface)) {
		$ip = ($isipv6) ? find_interface_ipv6($iface) : find_interface_ip($iface);
		if (is_ipaddr($ip)) {
			return $ip;
		}
	}

	// doesn't match up to any particular interface
	// so let's set the portal IP to what PHP says
	// the server IP issuing the request is.
	// allows same behavior as 1.2.x where IP isn't
	// in the subnet of any CP interface (static routes, etc.)
	// rather than forcing to DNS hostname resolution
	$ip = $_SERVER['SERVER_ADDR'];
	if (is_ipaddr($ip)) {
		return $ip;
	}

	return false;
}

function portal_hostname_from_client_ip($cliip) {
	global $config, $cpzone;

	$cpcfg = $config['captiveportal'][$cpzone];

	if (isset($cpcfg['httpslogin'])) {
		$listenporthttps = $cpcfg['listenporthttps'] ? $cpcfg['listenporthttps'] : ($cpcfg['zoneid'] + 8001);
		$ourhostname = $cpcfg['httpsname'];

		if ($listenporthttps != 443) {
			$ourhostname .= ":" . $listenporthttps;
		}
	} else {
		$listenporthttp = $cpcfg['listenporthttp'] ? $cpcfg['listenporthttp'] : ($cpcfg['zoneid'] + 8000);
		$ifip = portal_ip_from_client_ip($cliip);
		if (!$ifip) {
			$ourhostname = "{$config['system']['hostname']}.{$config['system']['domain']}";
		} else {
			$ourhostname = (is_ipaddrv6($ifip)) ? "[{$ifip}]" : "{$ifip}";
		}

		if ($listenporthttp != 80) {
			$ourhostname .= ":" . $listenporthttp;
		}
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
	} else if ($type == "login") {
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal_{$cpzone}.html");
	} else {
		$htmltext = get_include_contents("{$g['varetc_path']}/captiveportal-{$cpzone}-error.html");
	}

	$cpcfg = $config['captiveportal'][$cpzone];

	/* substitute the PORTAL_REDIRURL variable */
	if ($cpcfg['preauthurl']) {
		$htmltext = str_replace("\$PORTAL_REDIRURL\$", "{$cpcfg['preauthurl']}", $htmltext);
		$htmltext = str_replace("#PORTAL_REDIRURL#", "{$cpcfg['preauthurl']}", $htmltext);
	}

	/* substitute other variables */
	$ourhostname = portal_hostname_from_client_ip($clientip);
	$protocol = (isset($cpcfg['httpslogin'])) ? 'https://' : 'http://';
	$htmltext = str_replace("\$PORTAL_ACTION\$", "{$protocol}{$ourhostname}/index.php?zone={$cpzone}", $htmltext);
	$htmltext = str_replace("#PORTAL_ACTION#", "{$protocol}{$ourhostname}/index.php?zone={$cpzone}", $htmltext);

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

function portal_mac_radius($clientmac, $clientip) {
	global $config, $cpzone;

	$radmac_secret = $config['captiveportal'][$cpzone]['radmac_secret'];

	/* authentication against the radius server */
	$username = mac_format($clientmac);
	$auth_list = radius($username, $radmac_secret, $clientip, $clientmac, "MACHINE LOGIN");
	if ($auth_list['auth_val'] == 2) {
		return TRUE;
	}

	if (!empty($auth_list['url_redirection'])) {
		portal_reply_page($auth_list['url_redirection'], "redir");
	}

	return FALSE;
}

function captiveportal_reapply_attributes($cpentry, $attributes) {
	global $config, $cpzone, $g;

	if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
		$dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
		$dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
	} else {
		$dwfaultbw_up = $dwfaultbw_down = 0;
	}
	/* pipe throughputs must always be an integer, enforce that restriction again here. */
	if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
		$bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
		$bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
	} else {
		$bw_up = round($dwfaultbw_up,0);
		$bw_down = round($dwfaultbw_down,0);
	}

	$bw_up_pipeno = $cpentry[1];
	$bw_down_pipeno = $cpentry[1]+1;

	$_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
	$_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");
	//captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "RADIUS_BANDWIDTH_REAPPLY", "{$bw_up}/{$bw_down}");

	unset($bw_up_pipeno, $bw_down_pipeno, $bw_up, $bw_down);
}

function portal_allow($clientip, $clientmac, $username, $password = null, $attributes = null, $pipeno = null, $radiusctx = null) {
	global $redirurl, $g, $config, $type, $passthrumac, $_POST, $cpzone, $cpzoneid;

	// Ensure we create an array if we are missing attributes
	if (!is_array($attributes)) {
		$attributes = array();
	}

	unset($sessionid);

	/* Do not allow concurrent login execution. */
	$cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

	if ($attributes['voucher']) {
		$remaining_time = $attributes['session_timeout'];
	// Set RADIUS-Attribute to Voucher to prevent ReAuth-Reqeuest for Vouchers Bug: #2155
		$radiusctx="voucher";
	}

	$writecfg = false;
	/* Find an existing session */
	if ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) &&
	    $passthrumac &&
	    isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
		$mac = captiveportal_passthrumac_findbyname($username);
		if (!empty($mac) && $_POST['replacemacpassthru']) {
			foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $idx => $macent) {
				if ($macent['mac'] != $mac['mac']) {
					continue;
				}

				$macrules = "";
				$pipeno = captiveportal_get_dn_passthru_ruleno($mac['mac']);
				if ($pipeno) {
					captiveportal_free_dn_ruleno($pipeno);
					$macrules .= "table {$cpzone}_pipe_mac delete any,{$mac['mac']}\n";
					$macrules .= "table {$cpzone}_pipe_mac delete {$mac['mac']},any\n";
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
				mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
				$writecfg = true;
				$sessionid = true;
				break;
			}
		} elseif (!empty($mac)) {
			portal_reply_page($redirurl, "error", "Username: {$username} is already authenticated using another MAC address.",
				$clientmac, $clientip, $username, $password);
			unlock($cpdblck);
			return;
		}
	}

	/* read in client database */
	$query = "WHERE ip = '{$clientip}'";
	$tmpusername = SQLite3::escapeString(strtolower($username));
	if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
		$query .= " OR (username != 'unauthenticated' AND lower(username) = '{$tmpusername}')";
	}
	$cpdb = captiveportal_read_db($query);

	/* Snapshot the timestamp */
	$allow_time = time();
	$radiusservers = captiveportal_get_radius_servers();
	$unsetindexes = array();
	if (is_null($radiusctx)) {
		$radiusctx = 'first';
	}

	foreach ($cpdb as $cpentry) {
		if (empty($cpentry[12])) {
			$cpentry[12] = 'first';
		}
		/* on the same ip */
		if ($cpentry[2] == $clientip) {
			if (isset($config['captiveportal'][$cpzone]['nomacfilter']) || $cpentry[3] == $clientmac) {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING OLD SESSION");
			} else {
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
			}
			$sessionid = $cpentry[5];
			break;
		} elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
			// user logged in with an active voucher. Check for how long and calculate
			// how much time we can give him (voucher credit - used time)
			$remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
			if ($remaining_time < 0) { // just in case.
				$remaining_time = 0;
			}

			/* This user was already logged in so we disconnect the old one */
			captiveportal_disconnect($cpentry, $radiusservers[$cpentry[12]], 13);
			captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
			$unsetindexes[] = $cpentry[5];
			break;
		} elseif ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
			/* on the same username */
			if (strcasecmp($cpentry[4], $username) == 0) {
				/* This user was already logged in so we disconnect the old one */
				captiveportal_disconnect($cpentry, $radiusservers[$cpentry[12]], 13);
				captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
				$unsetindexes[] = $cpentry[5];
				break;
			}
		}
	}
	unset($cpdb);

	if (!empty($unsetindexes)) {
		captiveportal_remove_entries($unsetindexes);
	}

	if ($attributes['voucher'] && $remaining_time <= 0) {
		return 0;       // voucher already used and no time left
	}

	if (!isset($sessionid)) {
		/* generate unique session ID */
		$tod = gettimeofday();
		$sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

		if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
			$dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
			$dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
		} else {
			$dwfaultbw_up = $dwfaultbw_down = 0;
		}
		/* pipe throughputs must always be an integer, enforce that restriction again here. */
		if (isset($config['captiveportal'][$cpzone]['radiusperuserbw'])) {
			$bw_up = round(!empty($attributes['bw_up']) ? intval($attributes['bw_up'])/1000 : $dwfaultbw_up, 0);
			$bw_down = round(!empty($attributes['bw_down']) ? intval($attributes['bw_down'])/1000 : $dwfaultbw_down, 0);
		} else {
			$bw_up = round($dwfaultbw_up,0);
			$bw_down = round($dwfaultbw_down,0);
		}

		if ($passthrumac) {

			$mac = array();
			$mac['action'] = 'pass';
			$mac['mac'] = $clientmac;
			$mac['ip'] = $clientip; /* Used only for logging */
			if (isset($config['captiveportal'][$cpzone]['passthrumacaddusername'])) {
				$mac['username'] = $username;
				if ($attributes['voucher']) {
					$mac['logintype'] = "voucher";
				}
			}
			if ($username == "unauthenticated") {
				$mac['descr'] = "Auto-added";
			} else {
				$mac['descr'] = "Auto-added for user {$username}";
			}
			if (!empty($bw_up)) {
				$mac['bw_up'] = $bw_up;
			}
			if (!empty($bw_down)) {
				$mac['bw_down'] = $bw_down;
			}
			if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
				$config['captiveportal'][$cpzone]['passthrumac'] = array();
			}
			//check for mac duplicates before adding it to config.
			$mac_duplicate = false;
			foreach($config['captiveportal'][$cpzone]['passthrumac'] as $mac_check){
				if($mac_check['mac'] == $mac['mac']){
					$mac_duplicate = true;
				}
			}
			if(!$mac_duplicate){
				$config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
			}
			unlock($cpdblck);
			$macrules = captiveportal_passthrumac_configure_entry($mac);
			file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
			mwexec("/sbin/ipfw -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
			$writecfg = true;
		} else {
			/* See if a pipeno is passed, if not start sessions because this means there isn't one atm */
			if (is_null($pipeno)) {
				$pipeno = captiveportal_get_next_dn_ruleno();
			}

			/* if the pool is empty, return appropriate message and exit */
			if (is_null($pipeno)) {
				portal_reply_page($redirurl, "error", "System reached maximum login capacity");
				log_error("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
				unlock($cpdblck);
				return;
			}

			$bw_up_pipeno = $pipeno;
			$bw_down_pipeno = $pipeno + 1;
			//$bw_up /= 1000; // Scale to Kbit/s
			$_gb = @pfSense_ipfw_pipe("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
			$_gb = @pfSense_ipfw_pipe("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");

			$rule_entry = "{$clientip}/" . (is_ipaddrv6($clientip) ? "128" : "32");
			if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
				$rule_entry .= ",{$clientmac}";
			}
			$_gb = @pfSense_ipfw_table("{$cpzone}_auth_up", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_up_pipeno);
			$_gb = @pfSense_ipfw_table("{$cpzone}_auth_down", IP_FW_TABLE_XADD, "{$rule_entry}", $bw_down_pipeno);

			if ($attributes['voucher']) {
				$attributes['session_timeout'] = $remaining_time;
			}

			/* handle empty attributes */
			$session_timeout = (!empty($attributes['session_timeout'])) ? $attributes['session_timeout'] : 'NULL';
			$idle_timeout = (!empty($attributes['idle_timeout'])) ? $attributes['idle_timeout'] : 'NULL';
			$session_terminate_time = (!empty($attributes['session_terminate_time'])) ? $attributes['session_terminate_time'] : 'NULL';
			$interim_interval = (!empty($attributes['interim_interval'])) ? $attributes['interim_interval'] : 'NULL';
			$traffic_quota = (!empty($attributes['maxbytes'])) ? $attributes['maxbytes'] : 'NULL';

			/* escape username */
			$safe_username = SQLite3::escapeString($username);

			/* encode password in Base64 just in case it contains commas */
			$bpassword = (isset($config['captiveportal'][$cpzone]['reauthenticate'])) ? base64_encode($password) : '';
			$insertquery = "INSERT INTO captiveportal (allow_time, pipeno, ip, mac, username, sessionid, bpassword, session_timeout, idle_timeout, session_terminate_time, interim_interval, traffic_quota, radiusctx) ";
			$insertquery .= "VALUES ({$allow_time}, {$pipeno}, '{$clientip}', '{$clientmac}', '{$safe_username}', '{$sessionid}', '{$bpassword}', ";
			$insertquery .= "{$session_timeout}, {$idle_timeout}, {$session_terminate_time}, {$interim_interval}, {$traffic_quota}, '{$radiusctx}')";

			/* store information to database */
			captiveportal_write_db($insertquery);
			unlock($cpdblck);
			unset($insertquery, $bpassword);

			if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && !empty($radiusservers[$radiusctx])) {
				$acct_val = RADIUS_ACCOUNTING_START($pipeno, $username, $sessionid, $radiusservers[$radiusctx], $clientip, $clientmac);
				if ($acct_val == 1) {
					captiveportal_logportalauth($username, $clientmac, $clientip, $type, "RADIUS ACCOUNTING FAILED");
				}
			}
		}
	} else {
		/* NOTE: #3062-11 If the pipeno has been allocated free it to not DoS the CP and maintain proper operation as in radius() case */
		if (!is_null($pipeno)) {
			captiveportal_free_dn_ruleno($pipeno);
		}

		unlock($cpdblck);
	}

	if ($writecfg == true) {
		write_config(gettext("Captive Portal allowed users configuration changed"));
	}

	/* redirect user to desired destination */
	if (!empty($attributes['url_redirection'])) {
		$my_redirurl = $attributes['url_redirection'];
	} else if (!empty($redirurl)) {
		$my_redirurl = $redirurl;
	} else if (!empty($config['captiveportal'][$cpzone]['redirurl'])) {
		$my_redirurl = $config['captiveportal'][$cpzone]['redirurl'];
	}

	if (isset($config['captiveportal'][$cpzone]['logoutwin_enable']) && !$passthrumac) {
		$ourhostname = portal_hostname_from_client_ip($clientip);
		$protocol = (isset($config['captiveportal'][$cpzone]['httpslogin'])) ? 'https://' : 'http://';
		$logouturl = "{$protocol}{$ourhostname}/";

		if (isset($attributes['reply_message'])) {
			$message = $attributes['reply_message'];
		} else {
			$message = 0;
		}

		include_once("{$g['varetc_path']}/captiveportal-{$cpzone}-logout.html");

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

	if (!empty($config['captiveportal'][$cpzone]['freelogins_count']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_count'])) {
		$freeloginscount = $config['captiveportal'][$cpzone]['freelogins_count'];
	} else {
		return false;
	}

	if (!empty($config['captiveportal'][$cpzone]['freelogins_resettimeout']) && is_numeric($config['captiveportal'][$cpzone]['freelogins_resettimeout'])) {
		$resettimeout = $config['captiveportal'][$cpzone]['freelogins_resettimeout'];
	} else {
		return false;
	}

	if ($freeloginscount < 1 || $resettimeout <= 0 || !$clientmac) {
		return false;
	}

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
			} else {
				unset($usedmacs[$key]);
			}

			break;
		} else if ($usedmac[0] + ($resettimeout * 3600) <= $currenttime) {
			unset($usedmacs[$key]);
		}
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
		if (!$usedmacs) {
			$usedmacs = array();
		}
	} else {
		$usedmacs = array();
	}

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

	if (empty($mac) || !is_macaddr($mac)) {
		return false;
	}

	if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
		return false;
	}

	foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $passthrumac) {
		if (($passthrumac['action'] == 'block') &&
		    ($passthrumac['mac'] == strtolower($mac))) {
			return true;
		}
	}

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

function captiveportal_isip_logged($clientip) {
	global $g, $cpzone;

	/* read in client database */
	$query = "WHERE ip = '{$clientip}'";
	$cpdb = captiveportal_read_db($query);
	foreach ($cpdb as $cpentry) {
		return $cpentry;
	}
}
?>
