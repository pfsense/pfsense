<?php
/*
 * vpn.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
 * All rights reserved.
 *
 * originally part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("ipsec.inc");
require_once("filter.inc");
require_once("auth.inc");
require_once("certs.inc");

/* master setup for vpn (mpd) */
function vpn_setup() {
	/* start pppoe server */
	vpn_pppoes_configure();

	/* setup l2tp */
	vpn_l2tp_configure();
}

function vpn_ppp_server_get_dns($dns1 = '', $dns2 = '') {
	$ipcp_dns = '';
	$dns_servers = [];

	if (is_ipaddrv4($dns1)) {
		/* Use manual DNS servers if they are valid */
		$dns_servers[] = $dns1;
		if (is_ipaddrv4($dns2)) {
			$dns_servers[] = $dns2;
		}
	} else {
		/* Get system defined DNS servers */
		$host_dns_servers = get_dns_nameservers(false, true);

		/* Add LAN address if the Resolver or Forwarder are enabled */
		if (config_path_enabled('unbound') ||
		    config_path_enabled('dnsmasq')) {
			$dns_servers[] = get_interface_ip("lan");
		}

		/* Add host DNS servers if they are usable */
		foreach ($host_dns_servers as $dns) {
			if (is_ipaddrv4($dns) && !ip_in_subnet($dns, "127.0.0.0/8")) {
				$dns_servers[] = $dns;
			}
		}
	}

	if (!empty($dns_servers)) {
		/* Use the first two DNS servers since that is all MPD currently allows */
		$ipcp_dns = 'set ipcp dns ' . join(' ', array_slice($dns_servers, 0, 2));
	}
	return $ipcp_dns;
}

function vpn_pppoes_configure() {
	foreach (config_get_path("pppoes/pppoe", []) as $pppoe) {
		if (!empty($pppoe)) {
			vpn_pppoe_configure($pppoe);
		}
	}
}

function vpn_pppoe_configure(&$pppoecfg) {
	global $g;

	if (empty($pppoecfg) || !is_array($pppoecfg)) {
		return false;
	}

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn")) {
		mkdir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn");
	}

	if (is_platform_booting()) {
		if (!$pppoecfg['mode'] || ($pppoecfg['mode'] == "off")) {
			return 0;
		}

		echo gettext("Configuring PPPoE Server service... ");
	} else {
		/* kill mpd */
		if (isvalidpid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid")) {
			killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");
		}

		/* wait for process to die */
		sleep(2);

	}

	if ($pppoecfg['mode'] != 'server') {
		if (is_platform_booting()) {
			echo gettext("done") . "\n";
		}
		return true;
	}

	$pppoe_interface = get_real_interface($pppoecfg['interface']);

	if ($pppoecfg['paporchap'] == "chap") {
		$paporchap = "set link enable chap";
	} else {
		$paporchap = "set link enable pap";
	}

	/* write mpd.conf */
	$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open mpd.conf in vpn_pppoe_configure().") . "\n");
		return 1;
	}

	$issue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 ";

	if (array_path_enabled($pppoecfg, 'radius', 'radiusissueips') &&
	    array_path_enabled($pppoecfg, 'radius/server')) {
		$issue_ip_type .= "0.0.0.0/0";
	} else {
		$issue_ip_type .= "ippool p0";
	}

	$ippool_p0 = ip_after($pppoecfg['remoteip'], $pppoecfg['n_pppoe_units'] - 1);

	if (is_numeric($pppoecfg['n_pppoe_maxlogin']) && ($pppoecfg['n_pppoe_maxlogin'] > 0)) {
		$pppoemaxlogins = $pppoecfg['n_pppoe_maxlogin'];
	} else {
		$pppoemaxlogins = 1;
	}

	$ipcp_dns = vpn_ppp_server_get_dns($pppoecfg['dns1'], $pppoecfg['dns2']);

	$mpdconf = <<<EOD
startup:

poes:
	set ippool add p0 {$pppoecfg['remoteip']} {$ippool_p0}

	create bundle template poes_b
	set bundle enable compression

	set ccp yes mppc
	set mppc yes e40
	set mppc yes e128
	set mppc yes stateless

	set iface name poes{$pppoecfg['pppoeid']}-
	set iface group pppoe
	set iface up-script /usr/local/sbin/vpn-linkup-poes
	set iface down-script /usr/local/sbin/vpn-linkdown-poes
	set iface idle 0
	set iface disable on-demand
	set iface disable proxy-arp
	set iface enable tcpmssfix
	set iface mtu 1500

	set ipcp no vjcomp
	{$issue_ip_type}
	{$ipcp_dns}

	create link template poes_l pppoe
	set link action bundle poes_b

	set auth max-logins {$pppoemaxlogins}

	set pppoe iface {$pppoe_interface}

	set link no multilink
	set link no pap chap
	{$paporchap}
	set link keep-alive 60 180
	set link max-redial -1
	set link mru 1492
	set link latency 1
	set link enable incoming

EOD;

	if (array_path_enabled($pppoecfg, 'radius/server')) {
		$radiusport = array_get_path($pppoecfg, 'radius/server/port', '');
		$radiusacctport = array_get_path($pppoecfg, 'radius/server/acctport', '');
		$radiusip = array_get_path($pppoecfg, 'radius/server/ip', '');
		$radiussecret = array_get_path($pppoecfg, 'radius/server/secret', '');
		$mpdconf .= "\tset radius server {$radiusip} \"{$radiussecret}\" {$radiusport} {$radiusacctport}\n";

		if (array_path_enabled($pppoecfg, 'radius/server2')) {
			$radiusport = array_get_path($pppoecfg, 'radius/server2/port', '');
			$radiusacctport = array_get_path($pppoecfg, 'radius/server2/acctport', '');
			$radiusip = array_get_path($pppoecfg, 'radius/server2/ip', '');
			$radiussecret = array_get_path($pppoecfg, 'radius/server2/secret', '');
			$mpdconf .= "\tset radius server {$radiusip} \"{$radiussecret}\" {$radiusport} {$radiusacctport}\n";
		}

		$mpdconf .=<<<EOD
	set radius retries 3
	set radius timeout 10
	set auth enable radius-auth

EOD;

		if (array_path_enabled($pppoecfg, 'radius', 'accounting')) {
			$mpdconf .=<<<EOD
	set auth enable radius-acct

EOD;
		}
		$radiusacctupdate = array_get_path($pppoecfg, 'radius/server/acct_update', '');
		if (!empty($radiusacctupdate)) {
			$mpdconf .= "\tset auth acct-update {$radiusacctupdate}\n";
		}
		$radiusnasip = array_get_path($pppoecfg, 'radius/server/nasip', '');
		if (!empty($radiusnasip)) {
			$mpdconf .= "\tset radius me {$radiusnasip}\n";
		}
	}

	fwrite($fd, $mpdconf);
	fclose($fd);
	unset($mpdconf);

	vpn_pppoe_updatesecret($pppoecfg);

	/* Check if previous instance is still up */
	while (file_exists("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid") && isvalidpid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid")) {
		killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");
	}

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn -p {$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

	if (is_platform_booting()) {
		echo gettext("done") . "\n";
	}

	return 0;
}

function vpn_pppoe_updatesecret(&$pppoecfg) {
	global $g;

	if ($pppoecfg['username']) {
		/* write mpd.secret */
		$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open mpd.secret in vpn_pppoe_configure().") . "\n");
			return 1;
		}

		$mpdsecret = "\n\n";

		if (!empty($pppoecfg['username'])) {
			$item = explode(" ", $pppoecfg['username']);
			foreach ($item as $userdata) {
				$data = explode(":", $userdata);
				/* Escape double quotes, do not allow password to start with '!'
				 * https://redmine.pfsense.org/issues/10275 */
				$pass = str_replace('"', '\"', ltrim(base64_decode($data[1]), '!'));
				$mpdsecret .= "{$data[0]} \"{$pass}\" {$data[2]}\n";
			}
		}

		fwrite($fd, $mpdsecret);
		fclose($fd);
		unset($mpdsecret);
		chmod("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", 0600);
		return 0;
	}
}

function vpn_l2tp_configure($interface = '') {
	global $g;

	$l2tpcfg = config_get_path('l2tp', []);
	if (empty($l2tpcfg)) {
		return false;
	}

	$dns_servers = get_dns_nameservers(false, true);
	if (empty($dns_servers) || !is_array($dns_servers)) {
		$dns_servers = ['127.0.0.1'];
	}

	$mode = array_get_path($l2tpcfg, 'mode', 'off');
	if (is_platform_booting()) {
		if ($mode == 'off') {
			rmdir_recursive("{$g['varetc_path']}/l2tp-vpn");
			return 0;
		}

		echo gettext("Configuring l2tp VPN service... ");
	} else {
		if (!empty($interface) &&
		    (array_get_path($l2tpcfg, 'interface') != $interface)) {
			return 0;
		}
		/* kill mpd */
		if (isvalidpid("{$g['varrun_path']}/l2tp-vpn.pid")) {
			killbypid("{$g['varrun_path']}/l2tp-vpn.pid");
		}

		/* wait for process to die */
		sleep(8);

	}

	if ($mode != 'server') {
		rmdir_recursive("{$g['varetc_path']}/l2tp-vpn");
		if (is_platform_booting()) {
			echo gettext("done") . "\n";
		}
		return true;
	}

	$l2tp_listen="";
	$ipaddr = get_interface_ip(get_failover_interface(array_get_path($l2tpcfg, 'interface')));
	if (is_ipaddrv4($ipaddr)) {
		$l2tp_listen="set l2tp self {$ipaddr}";
	}

	switch (array_get_path($l2tpcfg, 'paporchap')) {
		case 'chap':
			$paporchap = "set link enable chap";
			break;
		case 'chap-msv2':
			$paporchap = "set link enable chap-msv2";
			break;
		default:
			$paporchap = "set link enable pap";
			break;
	}

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/l2tp-vpn")) {
		mkdir("{$g['varetc_path']}/l2tp-vpn");
	}

	/* write mpd.conf */
	$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.conf", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
		return 1;
	}


	$remoteip = array_get_path($l2tpcfg, 'remoteip');
	$ippool_p0 = ip_after($remoteip, (array_get_path($l2tpcfg, 'n_l2tp_units') - 1));

	$issue_ip_type = 'set ipcp ranges ' . array_get_path($l2tpcfg, 'localip') . '/32 ';

	if (array_path_enabled($l2tpcfg, 'radius', 'radiusissueips') &&
	    array_path_enabled($l2tpcfg, 'radius')) {
		$issue_ip_type .= "0.0.0.0/0";
		$ippool = "";
	} else {
		$issue_ip_type .= "ippool p0";
		$ippool = "set ippool add p0 {$remoteip} {$ippool_p0}";
	}

	$ipcp_dns = vpn_ppp_server_get_dns($l2tpcfg['dns1'], $l2tpcfg['dns2']);

	$mpdconf =<<<EOD

startup:

l2tps:
	{$ippool}

	create bundle template l2tp_b
	set bundle enable compression
	set bundle yes crypt-reqd

	set ccp yes mppc

	set iface name l2tps
	set iface group l2tp
	set iface up-script /usr/local/sbin/vpn-linkup-l2tp
	set iface down-script /usr/local/sbin/vpn-linkdown-l2tp
	set iface disable on-demand
	set iface enable proxy-arp

	set ipcp yes vjcomp
	{$issue_ip_type}
	{$ipcp_dns}

	create link template l2tp_l l2tp
	set link action bundle l2tp_b

	set link yes acfcomp protocomp
	set link enable multilink
	set link no pap chap chap-msv2
	{$paporchap}
	{$l2tp_listen}
	set link keep-alive 10 180
	set link enable incoming

EOD;

	if (!empty($l2tpcfg['mtu'])) {
		$mpdconf .=<<<EOD
	set link mtu {$l2tpcfg['mtu']}

EOD;
	}

	if (!empty($l2tpcfg['secret'])) {
		$secret = str_replace('"', '\"', $l2tpcfg['secret']);
		$mpdconf .=<<<EOD
	set l2tp secret "{$secret}"

EOD;
	}

	if (array_path_enabled($l2tpcfg, 'radius')) {
		$radiusip = array_get_path($l2tpcfg, 'radius/server', '');
		$radiussecret = array_get_path($l2tpcfg, 'radius/secret', '');
		$mpdconf .=<<<EOD
	set radius server {$radiusip} "{$radiussecret}"
	set radius retries 3
	set radius timeout 10
	set auth disable internal
	set auth enable radius-auth

EOD;

		if (array_path_enabled($l2tpcfg, 'radius', 'accounting')) {
			$mpdconf .=<<<EOD
	set auth enable radius-acct

EOD;
		}
	}

	fwrite($fd, $mpdconf);
	fclose($fd);
	unset($mpdconf);

	vpn_l2tp_updatesecret();

	/* fire up mpd */
	mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid -s l2tps l2tps");

	if (is_platform_booting()) {
		echo "done\n";
	}

	return 0;
}

function vpn_l2tp_updatesecret() {
	global $g;
	$l2tpcfg = config_get_path('l2tp', []);

	if (empty($l2tpcfg)) {
		return false;
	}

	if (array_get_path($l2tpcfg, 'mode', 'off') != 'server') {
		return 0;
	}

	$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.secret", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_updatesecret().") . "\n");
		return 1;
	}

	$mpdsecret = "\n\n";

	foreach (array_get_path($l2tpcfg, 'user', []) as $user) {
		if (empty($user)) {
			continue;
		}
		/* Escape double quotes, do not allow password to start with '!'
		 * https://redmine.pfsense.org/issues/10275 */
		$pass = str_replace('"', '\"', ltrim($user['password'], '!'));
		$mpdsecret .= "{$user['name']} \"{$pass}\" {$user['ip']}\n";
	}

	fwrite($fd, $mpdsecret);
	fclose($fd);
	unset($mpdsecret);
	chmod("{$g['varetc_path']}/l2tp-vpn/mpd.secret", 0600);

	return 0;
}

function l2tpusercmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function l2tp_users_sort() {
	$users = config_get_path('l2tp/user', []);

	if (empty($users)) {
		return;
	}

	usort($users, "l2tpusercmp");
	config_set_path('l2tp/user', $users);
}

?>
