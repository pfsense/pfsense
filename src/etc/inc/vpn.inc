<?php
/*
 * vpn.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

function vpn_netgraph_support() {
	$iflist = get_configured_interface_list();
	foreach ($iflist as $iface) {
		$realif = get_real_interface($iface);
		/* Get support for netgraph(4) from the nic */
		$ifinfo = pfSense_get_interface_addresses($realif);
		if (!empty($ifinfo) && in_array($ifinfo['iftype'], array("ether", "vlan", "bridge"))) {
			pfSense_ngctl_attach(".", $realif);
		}
	}
}

function vpn_pppoes_configure() {
	global $config;

	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			vpn_pppoe_configure($pppoe);
		}
	}
}

function vpn_pppoe_configure(&$pppoecfg) {
	global $config, $g;

	$syscfg = $config['system'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn")) {
		mkdir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn");
	}

	if (platform_booting()) {
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

	switch ($pppoecfg['mode']) {

		case 'server':

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
			if (isset($pppoecfg['radius']['radiusissueips']) && isset($pppoecfg['radius']['server']['enable'])) {
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

			$ipcp_dns = '';
			if (!empty($pppoecfg['dns1'])) {
				$ipcp_dns = "set ipcp dns " . $pppoecfg['dns1'];
				if (!empty($pppoecfg['dns2'])) {
					$ipcp_dns .= " " . $pppoecfg['dns2'];
				}
			} elseif (isset($config['dnsmasq']['enable']) ||
			    isset ($config['unbound']['enable'])) {
				$ipcp_dns = "set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0]) {
					$ipcp_dns .= " " . $syscfg['dnsserver'][0];
				}
			} elseif (is_array($syscfg['dnsserver']) &&
			    ($syscfg['dnsserver'][0])) {
				$ipcp_dns = "set ipcp dns " . join(" ", $syscfg['dnsserver']);
			}

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

			if (isset ($pppoecfg['radius']['server']['enable'])) {
				$radiusport = "";
				$radiusacctport = "";
				if (isset($pppoecfg['radius']['server']['port'])) {
					$radiusport = $pppoecfg['radius']['server']['port'];
				}
				if (isset($pppoecfg['radius']['server']['acctport'])) {
					$radiusacctport = $pppoecfg['radius']['server']['acctport'];
				}
				$mpdconf .=<<<EOD
	set radius server {$pppoecfg['radius']['server']['ip']} "{$pppoecfg['radius']['server']['secret']}" {$radiusport} {$radiusacctport}
	set radius retries 3
	set radius timeout 10
	set auth enable radius-auth

EOD;

				if (isset ($pppoecfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set auth enable radius-acct

EOD;
				}
				if (!empty($pppoecfg['radius']['nasip'])) {
					$mpdconf .= "\tset radius me {$pppoecfg['radius']['nasip']}\n";
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);
			unset($mpdconf);

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
			}

			/* Check if previous instance is still up */
			while (file_exists("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid") && isvalidpid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid")) {
				killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");
			}

			/* Get support for netgraph(4) from the nic */
			pfSense_ngctl_attach(".", $pppoe_interface);
			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn -p {$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

			break;
	}

	if (platform_booting()) {
		echo gettext("done") . "\n";
	}

	return 0;
}

function vpn_l2tp_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$l2tpcfg = $config['l2tp'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/l2tp-vpn")) {
		mkdir("{$g['varetc_path']}/l2tp-vpn");
	}

	if (platform_booting()) {
		if (!$l2tpcfg['mode'] || ($l2tpcfg['mode'] == "off")) {
			return 0;
		}

		echo gettext("Configuring l2tp VPN service... ");
	} else {
		/* kill mpd */
		if (isvalidpid("{$g['varrun_path']}/l2tp-vpn.pid")) {
			killbypid("{$g['varrun_path']}/l2tp-vpn.pid");
		}

		/* wait for process to die */
		sleep(8);

	}

	/* make sure l2tp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/l2tp-vpn")) {
		mkdir("{$g['varetc_path']}/l2tp-vpn");
	}

	switch ($l2tpcfg['mode']) {

		case 'server':
			$l2tp_listen="";
			$ipaddr = get_interface_ip(get_failover_interface($l2tpcfg['interface']));
			if (is_ipaddrv4($ipaddr)) {
				$l2tp_listen="set l2tp self $ipaddr";
			}

			switch ($l2tpcfg['paporchap']) {
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

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.conf", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
				return 1;
			}

			$ippool_p0 = ip_after($l2tpcfg['remoteip'], $l2tpcfg['n_l2tp_units'] - 1);

			$issue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 ";
			if (isset($l2tpcfg['radius']['radiusissueips']) && isset($l2tpcfg['radius']['enable'])) {
				$issue_ip_type .= "0.0.0.0/0";
				$ippool = "";
			} else {
				$issue_ip_type .= "ippool p0";
				$ippool = "set ippool add p0 {$l2tpcfg['remoteip']} {$ippool_p0}";
			}

			$ipcp_dns = '';
			if (is_ipaddr($l2tpcfg['dns1'])) {
				$ipcp_dns = "set ipcp dns " . $l2tpcfg['dns1'];
				if (is_ipaddr($l2tpcfg['dns2'])) {
					$ipcp_dns .= " " . $l2tpcfg['dns2'];
				}
			} elseif (isset ($config['dnsmasq']['enable']) ||
			    isset ($config['unbound']['enable'])) {
				$ipcp_dns = "set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0]) {
					$ipcp_dns .= " " . $syscfg['dnsserver'][0];
				}
			} elseif (is_array($syscfg['dnsserver']) &&
			    ($syscfg['dnsserver'][0])) {
				$ipcp_dns = "set ipcp dns " . join(" ", $syscfg['dnsserver']);
			}

			$mpdconf =<<<EOD

startup:

l2tps:
	{$ippool}

	create bundle template l2tp_b
	set bundle enable compression
	set bundle yes crypt-reqd

	set ccp yes mppc

	set iface name l2tp
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
			if (isset($l2tpcfg['secret'])) {
				$secret = str_replace('"', '\"', $l2tpcfg['secret']);
				$mpdconf .=<<<EOD
	set l2tp secret "{$secret}"

EOD;
			}


			if (isset ($l2tpcfg['radius']['enable'])) {
				$mpdconf .=<<<EOD
	set radius server {$l2tpcfg['radius']['server']} "{$l2tpcfg['radius']['secret']}"
	set radius retries 3
	set radius timeout 10
	set auth disable internal
	set auth enable radius-auth

EOD;

				if (isset ($l2tpcfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set auth enable radius-acct

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);
			unset($mpdconf);

			vpn_l2tp_updatesecret();
			vpn_netgraph_support();

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid -s l2tps l2tps");

			break;

		case 'redir':
			break;
	}

	if (platform_booting()) {
		echo "done\n";
	}

	return 0;
}

function vpn_l2tp_updatesecret() {
	global $config, $g;
	$l2tpcfg = $config['l2tp'];

	$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.secret", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_updatesecret().") . "\n");
		return 1;
	}

	$mpdsecret = "\n\n";

	if (is_array($l2tpcfg['user'])) {
		foreach ($l2tpcfg['user'] as $user) {
			/* Escape double quotes, do not allow password to start with '!'
			 * https://redmine.pfsense.org/issues/10275 */
			$pass = str_replace('"', '\"', ltrim($user['password'], '!'));
			$mpdsecret .= "{$user['name']} \"{$pass}\" {$user['ip']}\n";
		}
	}

	fwrite($fd, $mpdsecret);
	fclose($fd);
	unset($mpdsecret);
	chmod("{$g['varetc_path']}/l2tp-vpn/mpd.secret", 0600);

	return 0;
}

?>
