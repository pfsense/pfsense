#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
*/

require("guiconfig.inc");

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
list($pconfig['dns1'],$pconfig['dns2']) = $config['system']['dnsserver'];

$pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
$pconfig['username'] = $config['system']['username'];
if (!$pconfig['username'])
	$pconfig['username'] = "admin";
$pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
if (!$pconfig['webguiproto'])
	$pconfig['webguiproto'] = "http";
$pconfig['webguiport'] = $config['system']['webgui']['port'];
$pconfig['timezone'] = $config['system']['timezone'];
$pconfig['timeupdateinterval'] = $config['system']['time-update-interval'];
$pconfig['timeservers'] = $config['system']['timeservers'];

if (!isset($pconfig['timeupdateinterval']))
	$pconfig['timeupdateinterval'] = 300;
if (!$pconfig['timezone'])
	$pconfig['timezone'] = "Etc/UTC";
if (!$pconfig['timeservers'])
	$pconfig['timeservers'] = "pool.ntp.org";

$changedesc = "System: ";
$changecount = 0;

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

exec('/usr/bin/tar -tzf /usr/share/zoneinfo.tgz', $timezonelist);
$timezonelist = array_filter($timezonelist, 'is_timezone');
sort($timezonelist);

if ($_POST) {

	$changecount++;

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = split(" ", "hostname domain username");
	$reqdfieldsn = split(",", "Hostname,Domain,Username");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['hostname'] && !is_hostname($_POST['hostname'])) {
		$input_errors[] = "The hostname may only contain the characters a-z, 0-9 and '-'.";
	}
	if ($_POST['domain'] && !is_domain($_POST['domain'])) {
		$input_errors[] = "The domain may only contain the characters a-z, 0-9, '-' and '.'.";
	}
	if (($_POST['dns1'] && !is_ipaddr($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddr($_POST['dns2']))) {
		$input_errors[] = "A valid IP address must be specified for the primary/secondary DNS server.";
	}
	if ($_POST['username'] && !preg_match("/^[a-zA-Z0-9]*$/", $_POST['username'])) {
		$input_errors[] = "The username may only contain the characters a-z, A-Z and 0-9.";
	}
	if ($_POST['webguiport'] && (!is_numericint($_POST['webguiport']) ||
			($_POST['webguiport'] < 1) || ($_POST['webguiport'] > 65535))) {
		$input_errors[] = "A valid TCP/IP port must be specified for the webGUI port.";
	}
	if (($_POST['password']) && ($_POST['password'] != $_POST['password2'])) {
		$input_errors[] = "The passwords do not match.";
	}

	$t = (int)$_POST['timeupdateinterval'];
	if (($t < 0) || (($t > 0) && ($t < 6)) || ($t > 1440)) {
		$input_errors[] = "The time update interval must be either 0 (disabled) or between 6 and 1440.";
	}
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts)) {
			$input_errors[] = "A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.";
		}
	}

	if (!$input_errors) {
		update_if_changed("hostname", $config['system']['hostname'], strtolower($_POST['hostname']));
		update_if_changed("domain", $config['system']['domain'], strtolower($_POST['domain']));
		update_if_changed("username", $config['system']['username'], $_POST['username']);

		if (update_if_changed("webgui protocol", $config['system']['webgui']['protocol'], $pconfig['webguiproto'])  || update_if_changed("webgui port", $config['system']['webgui']['port'], $pconfig['webguiport']))
			$restart_webgui = true;

		update_if_changed("timezone", $config['system']['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $config['system']['timeservers'], strtolower($_POST['timeservers']));
		update_if_changed("NTP update interval", $config['system']['time-update-interval'], $_POST['timeupdateinterval']);

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		unset($config['system']['dnsserver']);
		if ($_POST['dns1'])
			$config['system']['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['system']['dnsserver'][] = $_POST['dns2'];

		$olddnsallowoverride = $config['system']['dnsallowoverride'];
		
		unset($config['system']['dnsallowoverride']);
		$config['system']['dnsallowoverride'] = $_POST['dnsallowoverride'] ? true : false;

		if ($_POST['password']) {
			$config['system']['password'] = crypt($_POST['password']);
			$fd = popen("/usr/sbin/pw usermod -n root -H 0", "w");
			$salt = md5(time());
			$crypted_pw = crypt($_POST['password'],$salt);
			fwrite($fd, $crypted_pw);
			pclose($fd);
			update_changedesc("password changed");
		}

		if ($changecount > 0)
			write_config($changedesc);

		$retval = 0;
		if (!file_exists($d_sysrebootreqd_path)) {
			config_lock();
			$retval = system_hostname_configure();
			$retval |= system_hosts_generate();
			$retval |= system_resolvconf_generate();
			$retval |= system_password_configure();
			$retval |= services_dnsmasq_configure();
			$retval |= system_timezone_configure();
 			$retval |= system_ntp_configure();

 			if ($olddnsallowoverride != $config['system']['dnsallowoverride'])
 				$retval |= interfaces_wan_configure();

			config_unlock();
		}

		$savemsg = get_std_save_message($retval);
	}
}

$pgtitle = "System: General Setup";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="system.php" method="post">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Hostname</td>
                  <td width="78%" class="vtable"> <input name="hostname" type="text" class="formfld" id="hostname" size="40" value="<?=htmlspecialchars($pconfig['hostname']);?>">
                    <br> <span class="vexpl">name of the firewall host, without
                    domain part<br>
                    e.g. <em>firewall</em></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Domain</td>
                  <td width="78%" class="vtable"> <input name="domain" type="text" class="formfld" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>">
                    <br> <span class="vexpl">e.g. <em>mycorp.com</em> </span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">DNS servers</td>
                  <td width="78%" class="vtable"> <p>
                      <input name="dns1" type="text" class="formfld" id="dns1" size="20" value="<?=htmlspecialchars($pconfig['dns1']);?>">
                      <br>
                      <input name="dns2" type="text" class="formfld" id="dns22" size="20" value="<?=htmlspecialchars($pconfig['dns2']);?>">
                      <br>
                      <span class="vexpl">IP addresses; these are also used for
                      the DHCP service, DNS forwarder and for PPTP VPN clients<br>
                      <br>
                      <input name="dnsallowoverride" type="checkbox" id="dnsallowoverride" value="yes" <?php if ($pconfig['dnsallowoverride']) echo "checked"; ?>>
                      <strong>Allow DNS server list to be overridden by DHCP/PPP
                      on WAN</strong><br>
                      If this option is set, pfSense will use DNS servers assigned
                      by a DHCP/PPP server on WAN for its own purposes (including
                      the DNS forwarder). They will not be assigned to DHCP and
                      PPTP VPN clients, though.</span></p></td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">Username</td>
                  <td class="vtable"> <input name="username" type="text" class="formfld" id="username" size="20" value="<?=$pconfig['username'];?>">
                    <br>
                     <span class="vexpl">If you want
                    to change the username for accessing the webGUI, enter it
                    here.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Password</td>
                  <td width="78%" class="vtable"> <input name="password" type="password" class="formfld" id="password" size="20">
                    <br> <input name="password2" type="password" class="formfld" id="password2" size="20">
                    &nbsp;(confirmation) <br> <span class="vexpl">If you want
                    to change the password for accessing the webGUI, enter it
                    here twice.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">webGUI protocol</td>
                  <td width="78%" class="vtable"> <input name="webguiproto" type="radio" value="http" <?php if ($pconfig['webguiproto'] == "http") echo "checked"; ?>>
                    HTTP &nbsp;&nbsp;&nbsp; <input type="radio" name="webguiproto" value="https" <?php if ($pconfig['webguiproto'] == "https") echo "checked"; ?>>
                    HTTPS</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">webGUI port</td>
                  <td class="vtable"> <input name="webguiport" type="text" class="formfld" id="webguiport" "size="5" value="<?=htmlspecialchars($pconfig['webguiport']);?>">
                    <br>
                    <span class="vexpl">Enter a custom port number for the webGUI
                    above if you want to override the default (80 for HTTP, 443
                    for HTTPS). Changes will take effect immediately after save.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Time zone</td>
                  <td width="78%" class="vtable"> <select name="timezone" id="timezone">
                      <?php foreach ($timezonelist as $value): ?>
                      <option value="<?=htmlspecialchars($value);?>" <?php if ($value == $pconfig['timezone']) echo "selected"; ?>>
                      <?=htmlspecialchars($value);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Select the location closest
                    to you</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Time update interval</td>
                  <td width="78%" class="vtable"> <input name="timeupdateinterval" type="text" class="formfld" id="timeupdateinterval" size="4" value="<?=htmlspecialchars($pconfig['timeupdateinterval']);?>">
                    <br> <span class="vexpl">Minutes between network time sync.;
                    300 recommended, or 0 to disable </span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">NTP time server</td>
                  <td width="78%" class="vtable"> <input name="timeservers" type="text" class="formfld" id="timeservers" size="40" value="<?=htmlspecialchars($pconfig['timeservers']);?>">
                    <br> <span class="vexpl">Use a space to separate multiple
                    hosts (only one required). Remember to set up at least one
                    DNS server if you enter a host name here!</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
<?php
	// restart webgui if proto or port changed
	if ($restart_webgui) {
		global $_SERVER;
		if ($pconfig['webguiport']) {
			$url="{$pconfig['webguiproto']}://{$_SERVER['SERVER_ADDR']}:{$pconfig['webguiport']}/system.php";
		} else {
			$url = "{$pconfig['webguiproto']}://{$_SERVER['SERVER_ADDR']}/system.php";
		}
		echo "<p>One moment... switching https mode/port...</p>";
		echo "<meta http-equiv=\"refresh\" content=\"10;url={$url}\">";
	}

?>
</body>
</html>
<?php
	if ($restart_webgui) {
		system_webgui_start();
	}
?>