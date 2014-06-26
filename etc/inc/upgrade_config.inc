<?php
/*
	Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/find	/bin/cd	/usr/local/bin/rrdtool	/usr/bin/nice
	pfSense_MODULE:	config
*/

if(!function_exists("dump_rrd_to_xml"))
	require("rrd.inc");

/* Upgrade functions must be named:
*    upgrade_XXX_to_YYY
	* where XXX == previous version, zero padded, and YYY == next version, zero padded
	*/
function upgrade_010_to_011() {
	global $config;
	$opti = 1;
	$ifmap = array('lan' => 'lan', 'wan' => 'wan', 'pptp' => 'pptp');

	/* convert DMZ to optional, if necessary */
	if (isset($config['interfaces']['dmz'])) {

		$dmzcfg = &$config['interfaces']['dmz'];

		if ($dmzcfg['if']) {
			$config['interfaces']['opt' . $opti] = array();
			$optcfg = &$config['interfaces']['opt' . $opti];

			$optcfg['enable'] = $dmzcfg['enable'];
			$optcfg['descr'] = "DMZ";
			$optcfg['if'] = $dmzcfg['if'];
			$optcfg['ipaddr'] = $dmzcfg['ipaddr'];
			$optcfg['subnet'] = $dmzcfg['subnet'];

			$ifmap['dmz'] = "opt" . $opti;
			$opti++;
		}

		unset($config['interfaces']['dmz']);
	}

	/* convert WLAN1/2 to optional, if necessary */
	for ($i = 1; isset($config['interfaces']['wlan' . $i]); $i++) {

		if (!$config['interfaces']['wlan' . $i]['if']) {
			unset($config['interfaces']['wlan' . $i]);
			continue;
		}

		$wlancfg = &$config['interfaces']['wlan' . $i];
		$config['interfaces']['opt' . $opti] = array();
		$optcfg = &$config['interfaces']['opt' . $opti];

		$optcfg['enable'] = $wlancfg['enable'];
		$optcfg['descr'] = "WLAN" . $i;
		$optcfg['if'] = $wlancfg['if'];
		$optcfg['ipaddr'] = $wlancfg['ipaddr'];
		$optcfg['subnet'] = $wlancfg['subnet'];
		$optcfg['bridge'] = $wlancfg['bridge'];

		$optcfg['wireless'] = array();
		$optcfg['wireless']['mode'] = $wlancfg['mode'];
		$optcfg['wireless']['ssid'] = $wlancfg['ssid'];
		$optcfg['wireless']['channel'] = $wlancfg['channel'];
		$optcfg['wireless']['wep'] = $wlancfg['wep'];

		$ifmap['wlan' . $i] = "opt" . $opti;

		unset($config['interfaces']['wlan' . $i]);
		$opti++;
	}

	/* convert filter rules */
	$n = count($config['filter']['rule']);
	for ($i = 0; $i < $n; $i++) {

		$fr = &$config['filter']['rule'][$i];

		/* remap interface */
		if (array_key_exists($fr['interface'], $ifmap))
			$fr['interface'] = $ifmap[$fr['interface']];
		else {
			/* remove the rule */
			printf(gettext("%sWarning: filter rule removed " .
				"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
			unset($config['filter']['rule'][$i]);
			continue;
		}

		/* remap source network */
		if (isset($fr['source']['network'])) {
			if (array_key_exists($fr['source']['network'], $ifmap))
				$fr['source']['network'] = $ifmap[$fr['source']['network']];
			else {
				/* remove the rule */
				printf(gettext("%sWarning: filter rule removed " .
					"(source network '%s' does not exist anymore)."), "\n", $fr['source']['network']);
				unset($config['filter']['rule'][$i]);
				continue;
			}
		}

		/* remap destination network */
		if (isset($fr['destination']['network'])) {
			if (array_key_exists($fr['destination']['network'], $ifmap))
				$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
			else {
				/* remove the rule */
				printf(gettext("%sWarning: filter rule removed " .
					"(destination network '%s' does not exist anymore)."), "\n", $fr['destination']['network']);
				unset($config['filter']['rule'][$i]);
				continue;
			}
		}
	}

	/* convert shaper rules */
	$n = count($config['pfqueueing']['rule']);
	if (is_array($config['pfqueueing']['rule']))
	for ($i = 0; $i < $n; $i++) {

		$fr = &$config['pfqueueing']['rule'][$i];

		/* remap interface */
		if (array_key_exists($fr['interface'], $ifmap))
			$fr['interface'] = $ifmap[$fr['interface']];
		else {
			/* remove the rule */
			printf(gettext("%sWarning: traffic shaper rule removed " .
				"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
			unset($config['pfqueueing']['rule'][$i]);
			continue;
		}

		/* remap source network */
		if (isset($fr['source']['network'])) {
			if (array_key_exists($fr['source']['network'], $ifmap))
				$fr['source']['network'] = $ifmap[$fr['source']['network']];
			else {
				/* remove the rule */
				printf(gettext("%sWarning: traffic shaper rule removed " .
					"(source network '%s' does not exist anymore)."), "\n", $fr['source']['network']);
				unset($config['pfqueueing']['rule'][$i]);
				continue;
			}
		}

		/* remap destination network */
		if (isset($fr['destination']['network'])) {
			if (array_key_exists($fr['destination']['network'], $ifmap))
				$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
			else {
				/* remove the rule */
				printf(gettext("%sWarning: traffic shaper rule removed " .
					"(destination network '%s' does not exist anymore)."), "\n", $fr['destination']['network']);
				unset($config['pfqueueing']['rule'][$i]);
				continue;
			}
		}
	}
}


function upgrade_011_to_012() {
	global $config;
	/* move LAN DHCP server config */
	$tmp = $config['dhcpd'];
	$config['dhcpd'] = array();
	$config['dhcpd']['lan'] = $tmp;

	/* encrypt password */
	$config['system']['password'] = crypt($config['system']['password']);
}


function upgrade_012_to_013() {
	global $config;
	/* convert advanced outbound NAT config */
	for ($i = 0; isset($config['nat']['advancedoutbound']['rule'][$i]); $i++) {
		$curent = &$config['nat']['advancedoutbound']['rule'][$i];
		$src = $curent['source'];
		$curent['source'] = array();
		$curent['source']['network'] = $src;
		$curent['destination'] = array();
		$curent['destination']['any'] = true;
	}

	/* add an explicit type="pass" to all filter rules to make things consistent */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
		$config['filter']['rule'][$i]['type'] = "pass";
	}
}


function upgrade_013_to_014() {
	global $config;
	/* convert shaper rules (make pipes) */
	if (is_array($config['pfqueueing']['rule'])) {
		$config['pfqueueing']['pipe'] = array();

		for ($i = 0; isset($config['pfqueueing']['rule'][$i]); $i++) {
			$curent = &$config['pfqueueing']['rule'][$i];

			/* make new pipe and associate with this rule */
			$newpipe = array();
			$newpipe['descr'] = $curent['descr'];
			$newpipe['bandwidth'] = $curent['bandwidth'];
			$newpipe['delay'] = $curent['delay'];
			$newpipe['mask'] = $curent['mask'];
			$config['pfqueueing']['pipe'][$i] = $newpipe;

			$curent['targetpipe'] = $i;

			unset($curent['bandwidth']);
			unset($curent['delay']);
			unset($curent['mask']);
		}
	}
}


function upgrade_014_to_015() {
	global $config;
	/* Default route moved */
	if (isset($config['interfaces']['wan']['gateway']))
		if ($config['interfaces']['wan']['gateway'] <> "")
		$config['interfaces']['wan']['gateway'] = $config['interfaces']['wan']['gateway'];
	unset($config['interfaces']['wan']['gateway']);

	/* Queues are no longer interface specific */
	if (isset($config['interfaces']['lan']['schedulertype']))
		unset($config['interfaces']['lan']['schedulertype']);
	if (isset($config['interfaces']['wan']['schedulertype']))
		unset($config['interfaces']['wan']['schedulertype']);

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if(isset($config['interfaces']['opt' . $i]['schedulertype']))
			unset($config['interfaces']['opt' . $i]['schedulertype']);
	}
}


function upgrade_015_to_016() {
	global $config;
	/* Alternate firmware URL moved */
	if (isset($config['system']['firmwareurl']) && isset($config['system']['firmwarename'])) { // Only convert if *both* are defined.
		$config['system']['alt_firmware_url'] = array();
		$config['system']['alt_firmware_url']['enabled'] = "";
		$config['system']['alt_firmware_url']['firmware_base_url'] = $config['system']['firmwareurl'];
		$config['system']['alt_firmware_url']['firmware_filename'] = $config['system']['firmwarename'];
		unset($config['system']['firmwareurl'], $config['system']['firmwarename']);
	} else {
		unset($config['system']['firmwareurl'], $config['system']['firmwarename']);
	}
}


function upgrade_016_to_017() {
	global $config;
	/* wipe previous shaper configuration */
	unset($config['shaper']['queue']);
	unset($config['shaper']['rule']);
	unset($config['interfaces']['wan']['bandwidth']);
	unset($config['interfaces']['wan']['bandwidthtype']);
	unset($config['interfaces']['lan']['bandwidth']);
	unset($config['interfaces']['lan']['bandwidthtype']);
	$config['shaper']['enable'] = FALSE;
}


function upgrade_017_to_018() {
	global $config;
	if(isset($config['proxyarp']) && is_array($config['proxyarp']['proxyarpnet'])) {
		$proxyarp = &$config['proxyarp']['proxyarpnet'];
		foreach($proxyarp as $arpent){
			$vip = array();
			$vip['mode'] = "proxyarp";
			$vip['interface'] = $arpent['interface'];
			$vip['descr'] = $arpent['descr'];
			if (isset($arpent['range'])) {
				$vip['range'] = $arpent['range'];
				$vip['type'] = "range";
			} else {
				$subnet = explode('/', $arpent['network']);
				$vip['subnet'] = $subnet[0];
				if (isset($subnet[1])) {
					$vip['subnet_bits'] = $subnet[1];
					$vip['type'] = "network";
				} else {
					$vip['subnet_bits'] = "32";
					$vip['type'] = "single";
				}
			}
			$config['virtualip']['vip'][] = $vip;
		}
		unset($config['proxyarp']);
	}
	if(isset($config['installedpackages']) && isset($config['installedpackages']['carp']) && is_array($config['installedpackages']['carp']['config'])) {
		$carp = &$config['installedpackages']['carp']['config'];
		foreach($carp as $carpent){
			$vip = array();
			$vip['mode'] = "carp";
			$vip['interface'] = "AUTO";
			$vip['descr'] = sprintf(gettext("CARP vhid %s"), $carpent['vhid']);
			$vip['type'] = "single";
			$vip['vhid'] = $carpent['vhid'];
			$vip['advskew'] = $carpent['advskew'];
			$vip['password'] = $carpent['password'];
			$vip['subnet'] = $carpent['ipaddress'];
			$vip['subnet_bits'] = $carpent['netmask'];
			$config['virtualip']['vip'][] = $vip;
		}
		unset($config['installedpackages']['carp']);
	}
	/* Server NAT is no longer needed */
	unset($config['nat']['servernat']);

	/* enable SSH */
	if ($config['version'] == "1.8") {
		$config['system']['sshenabled'] = true;
	}
}


function upgrade_018_to_019() {
	global $config;
	$config['theme']="metallic";
}


function upgrade_019_to_020() {
	global $config;
	if(is_array($config['ipsec']['tunnel'])) {
		reset($config['ipsec']['tunnel']);
		while (list($index, $tunnel) = each($config['ipsec']['tunnel'])) {
			/* Sanity check on required variables */
			/* This fixes bogus <tunnel> entries - remnant of bug #393 */
			if (!isset($tunnel['local-subnet']) && !isset($tunnel['remote-subnet'])) {
				unset($config['ipsec']['tunnel'][$tunnel]);
			}
		}
	}
}

function upgrade_020_to_021() {
	global $config;
	/* shaper scheduler moved */
	if(isset($config['system']['schedulertype'])) {
		$config['shaper']['schedulertype'] = $config['system']['schedulertype'];
		unset($config['system']['schedulertype']);
	}
}


function upgrade_021_to_022() {
	global $config;
	/* move gateway to wan interface */
	$config['interfaces']['wan']['gateway'] = $config['system']['gateway'];
}

function upgrade_022_to_023() {
	global $config;
	if(isset($config['shaper'])) {
		/* wipe previous shaper configuration */
		unset($config['shaper']);
	}
}


function upgrade_023_to_024() {
	global $config;
}


function upgrade_024_to_025() {
	global $config;
	$config['interfaces']['wan']['use_rrd_gateway'] = $config['system']['use_rrd_gateway'];
	unset($config['system']['use_rrd_gateway']);
}


function upgrade_025_to_026() {
	global $config;
	$cron_item = array();
	$cron_item['minute'] = "0";
	$cron_item['hour'] = "*";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 newsyslog";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "1,31";
	$cron_item['hour'] = "0-5";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 adjkerntz -a";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "1";
	$cron_item['hour'] = "*";
	$cron_item['mday'] = "1";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /etc/rc.update_bogons.sh";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "*/60";
	$cron_item['hour'] = "*";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshlockout";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "1";
	$cron_item['hour'] = "1";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /etc/rc.dyndns.update";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "*/60";
	$cron_item['hour'] = "*";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 virusprot";

	$config['cron']['item'][] = $cron_item;

	$cron_item = array();
	$cron_item['minute'] = "*/60";
	$cron_item['hour'] = "*";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -t 1800 snort2c";

	$config['cron']['item'][] = $cron_item;
}


function upgrade_026_to_027() {
	global $config;
}


function upgrade_027_to_028() {
	global $config;
}


function upgrade_028_to_029() {
	global $config;
	$rule_item = array();
	$a_filter = &$config['filter']['rule'];
	$rule_item['interface'] = "enc0";
	$rule_item['type'] = "pass";
	$rule_item['source']['any'] = true;
	$rule_item['destination']['any'] = true;
	$rule_item['descr'] = gettext("Permit IPsec traffic.");
	$rule_item['statetype'] = "keep state";
	$a_filter[] = $rule_item;
}


function upgrade_029_to_030() {
	global $config;
	/* enable the rrd config setting by default */
	$config['rrd']['enable'] = true;
}


function upgrade_030_to_031() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_031_to_032() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_032_to_033() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_033_to_034() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_034_to_035() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_035_to_036() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_036_to_037() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_037_to_038() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_038_to_039() {
	global $config;
	/* Insert upgrade code here */
}


function upgrade_039_to_040() {
	global $config, $g;
	$config['system']['webgui']['auth_method'] = "session";
	$config['system']['webgui']['backing_method'] = "htpasswd";

	if (isset ($config['system']['username'])) {
		$config['system']['group'] = array();
		$config['system']['group'][0]['name'] = "admins";
		$config['system']['group'][0]['description'] = gettext("System Administrators");
		$config['system']['group'][0]['scope'] = "system";
		$config['system']['group'][0]['priv'] = "page-all";
		$config['system']['group'][0]['home'] = "index.php";
		$config['system']['group'][0]['gid'] = "110";

		$config['system']['user'] = array();
		$config['system']['user'][0]['name'] = "{$config['system']['username']}";
		$config['system']['user'][0]['descr'] = "System Administrator";
		$config['system']['user'][0]['scope'] = "system";
		$config['system']['user'][0]['groupname'] = "admins";
		$config['system']['user'][0]['password'] = "{$config['system']['password']}";
		$config['system']['user'][0]['uid'] = "0";
		/* Ensure that we follow what this new "admin" username should be in the session. */
		$_SESSION["Username"] = "{$config['system']['username']}";

		$config['system']['user'][0]['priv'] = array();
		$config['system']['user'][0]['priv'][0]['id'] = "lockwc";
		$config['system']['user'][0]['priv'][0]['name'] = "Lock webConfigurator";
		$config['system']['user'][0]['priv'][0]['descr'] = gettext("Indicates whether this user will lock access to the webConfigurator for other users.");
		$config['system']['user'][0]['priv'][1]['id'] = "lock-ipages";
		$config['system']['user'][0]['priv'][1]['name'] = "Lock individual pages";
		$config['system']['user'][0]['priv'][1]['descr'] = gettext("Indicates whether this user will lock individual HTML pages after having accessed a particular page (the lock will be freed if the user leaves or saves the page form).");
		$config['system']['user'][0]['priv'][2]['id'] = "hasshell";
		$config['system']['user'][0]['priv'][2]['name'] = "Has shell access";
		$config['system']['user'][0]['priv'][2]['descr'] = gettext("Indicates whether this user is able to login for example via SSH.");
		$config['system']['user'][0]['priv'][3]['id'] = "copyfiles";
		$config['system']['user'][0]['priv'][3]['name'] = "Is allowed to copy files";
		$config['system']['user'][0]['priv'][3]['descr'] = sprintf(gettext("Indicates whether this user is allowed to copy files onto the %s appliance via SCP/SFTP. If you are going to use this privilege, you must install scponly on the appliance (Hint: pkg_add -r scponly)."), $g['product_name']);
		$config['system']['user'][0]['priv'][4]['id'] = "isroot";
		$config['system']['user'][0]['priv'][4]['name'] = "Is root user";
		$config['system']['user'][0]['priv'][4]['descr'] = gettext("This user is associated with the UNIX root user (you should associate this privilege only with one single user).");

		$config['system']['nextuid'] = "111";
		$config['system']['nextgid'] = "111";

		/* wipe previous auth configuration */
		unset ($config['system']['username']);
		unset ($config['system']['password']);
	}
}

function upgrade_040_to_041() {
	global $config;
	if(!$config['sysctl']) {
		$config['sysctl']['item'] = array();

		$config['sysctl']['item'][0]['tunable'] = "net.inet.tcp.blackhole";
		$config['sysctl']['item'][0]['descr'] =    gettext("Drop packets to closed TCP ports without returning a RST");
		$config['sysctl']['item'][0]['value'] =   "default";

		$config['sysctl']['item'][1]['tunable'] = "net.inet.udp.blackhole";
		$config['sysctl']['item'][1]['descr'] =    gettext("Do not send ICMP port unreachable messages for closed UDP ports");
		$config['sysctl']['item'][1]['value'] =   "default";

		$config['sysctl']['item'][2]['tunable'] = "net.inet.ip.random_id";
		$config['sysctl']['item'][2]['descr'] =    gettext("Randomize the ID field in IP packets (default is 0: sequential IP IDs)");
		$config['sysctl']['item'][2]['value'] =   "default";

		$config['sysctl']['item'][3]['tunable'] = "net.inet.tcp.drop_synfin";
		$config['sysctl']['item'][3]['descr'] =    gettext("Drop SYN-FIN packets (breaks RFC1379, but nobody uses it anyway)");
		$config['sysctl']['item'][3]['value'] =   "default";

		$config['sysctl']['item'][4]['tunable'] = "net.inet.ip.redirect";
		$config['sysctl']['item'][4]['descr'] =    gettext("Sending of IPv4 ICMP redirects");
		$config['sysctl']['item'][4]['value'] =   "default";

		$config['sysctl']['item'][5]['tunable'] = "net.inet6.ip6.redirect";
		$config['sysctl']['item'][5]['descr'] =    gettext("Sending of IPv6 ICMP redirects");
		$config['sysctl']['item'][5]['value'] =   "default";

		$config['sysctl']['item'][6]['tunable'] = "net.inet.tcp.syncookies";
		$config['sysctl']['item'][6]['descr'] =    gettext("Generate SYN cookies for outbound SYN-ACK packets");
		$config['sysctl']['item'][6]['value'] =   "default";

		$config['sysctl']['item'][7]['tunable'] = "net.inet.tcp.recvspace";
		$config['sysctl']['item'][7]['descr'] =    gettext("Maximum incoming TCP datagram size");
		$config['sysctl']['item'][7]['value'] =   "default";

		$config['sysctl']['item'][8]['tunable'] = "net.inet.tcp.sendspace";
		$config['sysctl']['item'][8]['descr'] =    gettext("Maximum outgoing TCP datagram size");
		$config['sysctl']['item'][8]['value'] =   "default";

		$config['sysctl']['item'][9]['tunable'] = "net.inet.ip.fastforwarding";
		$config['sysctl']['item'][9]['descr'] =    gettext("Fastforwarding (see http://lists.freebsd.org/pipermail/freebsd-net/2004-January/002534.html)");
		$config['sysctl']['item'][9]['value'] =   "default";

		$config['sysctl']['item'][10]['tunable'] = "net.inet.tcp.delayed_ack";
		$config['sysctl']['item'][10]['descr'] =    gettext("Do not delay ACK to try and piggyback it onto a data packet");
		$config['sysctl']['item'][10]['value'] =   "default";

		$config['sysctl']['item'][11]['tunable'] = "net.inet.udp.maxdgram";
		$config['sysctl']['item'][11]['descr'] =    gettext("Maximum outgoing UDP datagram size");
		$config['sysctl']['item'][11]['value'] =   "default";

		$config['sysctl']['item'][12]['tunable'] = "net.link.bridge.pfil_onlyip";
		$config['sysctl']['item'][12]['descr'] =    gettext("Handling of non-IP packets which are not passed to pfil (see if_bridge(4))");
		$config['sysctl']['item'][12]['value'] =   "default";

		$config['sysctl']['item'][13]['tunable'] = "net.link.tap.user_open";
		$config['sysctl']['item'][13]['descr'] =    gettext("Allow unprivileged access to tap(4) device nodes");
		$config['sysctl']['item'][13]['value'] =   "default";

		$config['sysctl']['item'][15]['tunable'] = "kern.randompid";
		$config['sysctl']['item'][15]['descr'] =    gettext("Randomize PID's (see src/sys/kern/kern_fork.c: sysctl_kern_randompid())");
		$config['sysctl']['item'][15]['value'] =   "default";

		$config['sysctl']['item'][16]['tunable'] = "net.inet.tcp.inflight.enable";
		$config['sysctl']['item'][16]['descr'] =    gettext("The system will attempt to calculate the bandwidth delay product for each connection and limit the amount of data queued to the network to just the amount required to maintain optimum throughput. ");
		$config['sysctl']['item'][16]['value'] =   "default";

		$config['sysctl']['item'][17]['tunable'] = "net.inet.icmp.icmplim";
		$config['sysctl']['item'][17]['descr'] =    gettext("Set ICMP Limits");
		$config['sysctl']['item'][17]['value'] =   "default";

		$config['sysctl']['item'][18]['tunable'] = "net.inet.tcp.tso";
		$config['sysctl']['item'][18]['descr'] =    gettext("TCP Offload engine");
		$config['sysctl']['item'][18]['value'] =   "default";

		$config['sysctl']['item'][19]['tunable'] = "net.inet.ip.portrange.first";
		$config['sysctl']['item'][19]['descr'] =    "Set the ephemeral port range starting port";
		$config['sysctl']['item'][19]['value'] =   "default";

		$config['sysctl']['item'][20]['tunable'] = "hw.syscons.kbd_reboot";
		$config['sysctl']['item'][20]['descr'] =    "Enables ctrl+alt+delete";
		$config['sysctl']['item'][20]['value'] =   "default";

		$config['sysctl']['item'][21]['tunable'] = "kern.ipc.maxsockbuf";
		$config['sysctl']['item'][21]['descr'] =    "Maximum socket buffer size";
		$config['sysctl']['item'][21]['value'] =   "default";

	}
}


function upgrade_041_to_042() {
	global $config;
	if (isset($config['shaper']))
		unset($config['shaper']);
	if (isset($config['ezshaper']))
		unset($config['ezshaper']);
}


function upgrade_042_to_043() {
	global $config;
	/* migrate old interface gateway to the new gateways config */
	$iflist = get_configured_interface_list(false, true);
	$gateways = array();
	$i = 0;
	foreach($iflist as $ifname => $interface) {
		if(! interface_has_gateway($ifname)) {
			continue;
		}
		$config['gateways']['gateway_item'][$i] = array();
		if(is_ipaddr($config['interfaces'][$ifname]['gateway'])) {
			$config['gateways']['gateway_item'][$i]['gateway'] = $config['interfaces'][$ifname]['gateway'];
			$config['gateways']['gateway_item'][$i]['descr'] = sprintf(gettext("Interface %s Static Gateway"), $ifname);
		} else {
			$config['gateways']['gateway_item'][$i]['gateway'] = "dynamic";
			$config['gateways']['gateway_item'][$i]['descr'] = sprintf(gettext("Interface %s Dynamic Gateway"), $ifname);
		}
		$config['gateways']['gateway_item'][$i]['interface'] = $ifname;
		$config['gateways']['gateway_item'][$i]['name'] = "GW_" . strtoupper($ifname);
		/* add default gateway bit for wan on upgrade */
		if($ifname == "wan") {
			$config['gateways']['gateway_item'][$i]['defaultgw'] = true;
		}
		if(is_ipaddr($config['interfaces'][$ifname]['use_rrd_gateway'])) {
			$config['gateways']['gateway_item'][$i]['monitor'] = $config['interfaces'][$ifname]['use_rrd_gateway'];
			unset($config['interfaces'][$ifname]['use_rrd_gateway']);
		}
		$config['interfaces'][$ifname]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];

		/* Update all filter rules which might reference this gateway */
		$j = 0;
		foreach($config['filter']['rule'] as $rule) {
			if(is_ipaddr($rule['gateway'])) {
				if ($rule['gateway'] == $config['gateways']['gateway_item'][$i]['gateway'])
					$config['filter']['rule'][$j]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];
				else if ($rule['gateway'] == $ifname)
					$config['filter']['rule'][$j]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];
			}
			$j++;
		}

		/* rename old Quality RRD files in the process */
		$rrddbpath = "/var/db/rrd";
		$gwname = "GW_" . strtoupper($ifname);
		if(is_readable("{$rrddbpath}/{$ifname}-quality.rrd")) {
			rename("{$rrddbpath}/{$ifname}-quality.rrd", "{$rrddbpath}/{$gwname}-quality.rrd");
		}
		$i++;
	}
}


function upgrade_043_to_044() {
	global $config;

	/* migrate static routes to the new gateways config */
	$gateways = return_gateways_array(true);
	$i = 0;
	if (is_array($config['staticroutes']['route'])) {
		$gwmap = array();
		foreach ($config['staticroutes']['route'] as $idx => $sroute) {
			$found = false;
			foreach ($gateways as $gwname => $gw) {
				if ($gw['gateway'] == $sroute['gateway']) {
					$config['staticroutes']['route'][$idx]['gateway'] = $gwname;
					$found = true;
					break;
				}
			}
			if($gwmap[$sroute['gateway']]) {
				/* We already added a gateway name for this IP */
				$config['staticroutes']['route'][$idx]['gateway'] = "{$gwmap[$sroute['gateway']]}";
				$found = true;
			}

			if ($found == false) {
				$gateway = array();
				$gateway['name'] = "SROUTE{$i}";
				$gwmap[$sroute['gateway']] = $gateway['name'];
				$gateway['gateway'] = $sroute['gateway'];
				$gateway['interface'] = $sroute['interface'];
				$gateway['descr'] = sprintf(gettext("Upgraded static route for %s"), $sroute['network']);
				if (!is_array($config['gateways']['gateway_item']))
					$config['gateways']['gateway_item'] = array();
				$config['gateways']['gateway_item'][] = $gateway;
				$config['staticroutes']['route'][$idx]['gateway'] = $gateway['name'];
				$i++;
			}
		}
	}
}


function upgrade_044_to_045() {
	global $config;
	$iflist = get_configured_interface_list(false, true);
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		$i = 0;
		foreach ($config['vlans']['vlan'] as $id => $vlan) {
			/* Make sure to update the interfaces section with the right name */
			$vlan_name = "{$vlan['if']}_vlan{$vlan['tag']}";
			foreach($iflist as $ifname) {
				if($config['interfaces'][$ifname]['if'] == "vlan{$i}") {
					$config['interfaces'][$ifname]['if'] = $vlan_name;
					continue;
				}
			}
			$config['vlans']['vlan'][$i]['vlanif'] = "{$vlan_name}";
			$i++;
		}
	}
}


function upgrade_045_to_046() {
	global $config;
	/* Load up monitors that are in the default config for 2.0 but not in 1.2.3
		thus wouldn't be in an upgraded config. */
	$config['load_balancer']['monitor_type'] = array (
		array ( 'name' => 'ICMP',
			'type' => 'icmp',
			'descr' => 'ICMP',
			'options' => '',
		),
		array ( 'name' => 'TCP',
			'type' => 'tcp',
			'descr' => 'Generic TCP',
			'options' => '',
		),
		array ( 'name' => 'HTTP',
			'type' => 'http',
			'descr' => 'Generic HTTP',
			'options' =>
			array ( 'path' => '/',
				'host' => '',
				'code' => '200',
			),
		),
		array ( 'name' => 'HTTPS',
			'type' => 'https',
			'descr' => 'Generic HTTPS',
			'options' =>
			array ( 'path' => '/',
				'host' => '',
				'code' => '200',
			),
		),
		array ( 'name' => 'SMTP',
			'type' => 'send',
			'descr' => 'Generic SMTP',
			'options' =>
			array ( 'send' => '',
				'expect' => '220 *',
			),
		),
	);
	/* Upgrade load balancer from slb to relayd */
	if (is_array($config['load_balancer']['virtual_server']) && count($config['load_balancer']['virtual_server'])) {
		$vs_a = &$config['load_balancer']['virtual_server'];
		$pool_a = &$config['load_balancer']['lbpool'];
		$pools = array();
		/* Index pools by name */
		if(is_array($pool_a)) {
			for ($i = 0; isset($pool_a[$i]); $i++) {
				if($pool_a[$i]['type'] == "server") {
					$pools[$pool_a[$i]['name']] = $pool_a[$i];
				}
			}
		}
		/* Convert sitedown entries to pools and re-attach */
		for ($i = 0; isset($vs_a[$i]); $i++) {
			/* Set mode while we're here. */
			$vs_a[$i]['mode'] = "redirect_mode";
			if (isset($vs_a[$i]['sitedown'])) {
				$pool = array();
				$pool['type'] = 'server';
				$pool['behaviour'] = 'balance';
				$pool['name'] = "{$vs_a[$i]['name']}-sitedown";
				$pool['descr'] = sprintf(gettext("Sitedown pool for VS: %s"), $vs_a[$i]['name']);
				if (is_array($vs_a[$i]['pool']))
					$vs_a[$i]['pool'] = $vs_a[$i]['pool'][0];
				$pool['port'] = $pools[$vs_a[$i]['pool']]['port'];
				$pool['servers'] = array();
				$pool['servers'][] = $vs_a[$i]['sitedown'];
				$pool['monitor'] = $pools[$vs_a[$i]['pool']]['monitor'];
				$pool_a[] = $pool;
				$vs_a[$i]['sitedown'] = $pool['name'];
			}
		}
	}
	if(count($config['load_balancer']) == 0) {
		unset($config['load_balancer']);
	}
	mwexec('/usr/sbin/pw groupadd -n _relayd -g 913');
	mwexec('/usr/sbin/pw useradd -n _relayd -c "Relay Daemon" -d /var/empty -s /usr/sbin/nologin -u 913 -g 913');
}


function upgrade_046_to_047() {
	global $config;
	/* Upgrade IPsec from tunnel to phase1/phase2 */

	if(is_array($config['ipsec']['tunnel'])) {

		$a_phase1 = array();
		$a_phase2 = array();
		$ikeid = 0;

		foreach ($config['ipsec']['tunnel'] as $tunnel) {

			unset($ph1ent);
			unset($ph2ent);

			/*
				*  attempt to locate an enabled phase1
				*  entry that matches the peer gateway
				*/

			if (!isset($tunnel['disabled'])) {

				$remote_gateway = $tunnel['remote-gateway'];

				foreach ($a_phase1 as $ph1tmp) {
					if ($ph1tmp['remote-gateway'] == $remote_gateway) {
						$ph1ent = $ph1tmp;
						break;
					}
				}
			}

			/* none found, create a new one */

			if (!isset( $ph1ent )) {

				/* build new phase1 entry */

				$ph1ent = array();

				$ph1ent['ikeid'] = ++$ikeid;

				if (isset($tunnel['disabled']))
					$ph1ent['disabled'] = $tunnel['disabled'];

				/* convert to the new vip[$vhid] name */
				if(preg_match("/^carp/", $tunnel['interface'])) {
					$carpid = str_replace("carp", "", $tunnel['interface']);
					$tunnel['interface'] = "vip" . $config['virtualip']['vip'][$carpid]['vhid'];
				}
				$ph1ent['interface'] = $tunnel['interface'];
				$ph1ent['remote-gateway'] = $tunnel['remote-gateway'];
				$ph1ent['descr'] = $tunnel['descr'];

				$ph1ent['mode'] = $tunnel['p1']['mode'];

				if (isset($tunnel['p1']['myident']['myaddress']))
					$ph1ent['myid_type'] = "myaddress";
				if (isset($tunnel['p1']['myident']['address'])) {
					$ph1ent['myid_type'] = "address";
					$ph1ent['myid_data'] = $tunnel['p1']['myident']['address'];
				}
				if (isset($tunnel['p1']['myident']['fqdn'])) {
					$ph1ent['myid_type'] = "fqdn";
					$ph1ent['myid_data'] = $tunnel['p1']['myident']['fqdn'];
				}
				if (isset($tunnel['p1']['myident']['ufqdn'])) {
					$ph1ent['myid_type'] = "user_fqdn";
					$ph1ent['myid_data'] = $tunnel['p1']['myident']['ufqdn'];
				}
				if (isset($tunnel['p1']['myident']['asn1dn'])) {
					$ph1ent['myid_type'] = "asn1dn";
					$ph1ent['myid_data'] = $tunnel['p1']['myident']['asn1dn'];
				}
				if (isset($tunnel['p1']['myident']['dyn_dns'])) {
					$ph1ent['myid_type'] = "dyn_dns";
					$ph1ent['myid_data'] = $tunnel['p1']['myident']['dyn_dns'];
				}

				$ph1ent['peerid_type'] = "peeraddress";

				switch ($tunnel['p1']['encryption-algorithm']) {
					case "des":
					$ph1alg = array( 'name' => 'des' );
					break;
					case "3des":
					$ph1alg = array( 'name' => '3des' );
					break;
					case "blowfish":
					$ph1alg = array( 'name' => 'blowfish', 'keylen' => '128'  );
					break;
					case "cast128":
					$ph1alg = array( 'name' => 'cast128' );
					break;
					case "rijndael":
					$ph1alg = array( 'name' => 'aes', 'keylen' => '128' );
					break;
					case "rijndael 256":
					case "aes 256":
					$ph1alg = array( 'name' => 'aes', 'keylen' => '256' );
					break;
				}

				$ph1ent['encryption-algorithm'] = $ph1alg;
				$ph1ent['hash-algorithm'] = $tunnel['p1']['hash-algorithm'];
				$ph1ent['dhgroup'] = $tunnel['p1']['dhgroup'];
				$ph1ent['lifetime'] = $tunnel['p1']['lifetime'];
				$ph1ent['authentication_method'] = $tunnel['p1']['authentication_method'];

				if (isset($tunnel['p1']['pre-shared-key']))
					$ph1ent['pre-shared-key'] = $tunnel['p1']['pre-shared-key'];
				if (isset($tunnel['p1']['cert']))
					$ph1ent['cert'] = $tunnel['p1']['cert'];
				if (isset($tunnel['p1']['peercert']))
					$ph1ent['peercert'] = $tunnel['p1']['peercert'];
				if (isset($tunnel['p1']['private-key']))
					$ph1ent['private-key'] = $tunnel['p1']['private-key'];

				$ph1ent['nat_traversal'] = "on";
				$ph1ent['dpd_enable'] = 1;
				$ph1ent['dpd_delay'] = 10;
				$ph1ent['dpd_maxfail'] = 5;

				$a_phase1[] = $ph1ent;
			}

			/* build new phase2 entry */

			$ph2ent = array();

			$ph2ent['ikeid'] = $ph1ent['ikeid'];

			if (isset($tunnel['disabled']))
				$ph1ent['disabled'] = $tunnel['disabled'];

			$ph2ent['descr'] = sprintf(gettext("phase2 for %s"), $tunnel['descr']);

			$type = "lan";
			if ($tunnel['local-subnet']['network'])
				$type = $tunnel['local-subnet']['network'];
			if ($tunnel['local-subnet']['address']) {
				list($address,$netbits) = explode("/",$tunnel['local-subnet']['address']);
				if (is_null($netbits))
					$type = "address";
				else
					$type = "network";
			}

			switch ($type) {
				case "address":
				$ph2ent['localid'] = array('type' => $type,'address' => $address);
				break;
				case "network":
				$ph2ent['localid'] = array('type' => $type,'address' => $address,'netbits' => $netbits);
				break;
				default:
				$ph2ent['localid'] = array('type' => $type);
				break;
			}

			list($address,$netbits) = explode("/",$tunnel['remote-subnet']);
			$ph2ent['remoteid'] = array('type' => 'network','address' => $address,'netbits' => $netbits);

			$ph2ent['protocol'] = $tunnel['p2']['protocol'];

			$aes_count = 0;
			foreach( $tunnel['p2']['encryption-algorithm-option'] as $tunalg ) {
				$aes_found = false;
				switch ($tunalg) {
					case "des":
					$ph2alg = array( 'name' => 'des' );
					break;
					case "3des":
					$ph2alg = array( 'name' => '3des' );
					break;
					case "blowfish":
					$ph2alg = array( 'name' => 'blowfish', 'keylen' => 'auto'  );
					break;
					case "cast128":
					$ph2alg = array( 'name' => 'cast128' );
					break;
					case "rijndael":
					case "rijndael 256":
					case "aes 256":
					$ph2alg = array( 'name' => 'aes', 'keylen' => 'auto' );
					$aes_found = true;
					$aes_count++;
					break;
				}

				if( !$aes_found || ($aes_count < 2))
					$ph2ent['encryption-algorithm-option'][] = $ph2alg;
			}

			$ph2ent['hash-algorithm-option'] = $tunnel['p2']['hash-algorithm-option'];
			$ph2ent['pfsgroup'] = $tunnel['p2']['pfsgroup'];
			$ph2ent['lifetime'] = $tunnel['p2']['lifetime'];

			if (isset($tunnel['pinghost']['pinghost']))
				$ph2ent['pinghost'] = $tunnel['pinghost'];

			$a_phase2[] = $ph2ent;
		}

		unset($config['ipsec']['tunnel']);
		$config['ipsec']['phase1'] = $a_phase1;
		$config['ipsec']['phase2'] = $a_phase2;
	}

	/* Upgrade Mobile IPsec */
	if (isset($config['ipsec']['mobileclients'])
		&& is_array($config['ipsec']['mobileclients'])
		&& is_array($config['ipsec']['mobileclients']['p1'])
		&& is_array($config['ipsec']['mobileclients']['p2'])) {

		if (isset($config['ipsec']['mobileclients']['enable'])) {
			$config['ipsec']['client']['enable'] = true;
			$config['ipsec']['client']['user_source'] = 'system';
			$config['ipsec']['client']['group_source'] = 'system';
		}

		$mobilecfg = $config['ipsec']['mobileclients'];

		$ph1ent = array();
		$ph1ent['ikeid'] = ++$ikeid;

		if (!isset($mobilecfg['enable']))
			$ph1ent['disabled'] = true;

		/* Assume WAN since mobile tunnels couldn't be on a separate interface on 1.2.x */
		$ph1ent['interface'] = 'wan';
		$ph1ent['descr'] = "Mobile Clients (upgraded)";
		$ph1ent['mode'] = $mobilecfg['p1']['mode'];

		if (isset($mobilecfg['p1']['myident']['myaddress']))
			$ph1ent['myid_type'] = "myaddress";
		if (isset($mobilecfg['p1']['myident']['address'])) {
			$ph1ent['myid_type'] = "address";
			$ph1ent['myid_data'] = $mobilecfg['p1']['myident']['address'];
		}
		if (isset($mobilecfg['p1']['myident']['fqdn'])) {
			$ph1ent['myid_type'] = "fqdn";
			$ph1ent['myid_data'] = $mobilecfg['p1']['myident']['fqdn'];
		}
		if (isset($mobilecfg['p1']['myident']['ufqdn'])) {
			$ph1ent['myid_type'] = "user_fqdn";
			$ph1ent['myid_data'] = $mobilecfg['p1']['myident']['ufqdn'];
		}
		if (isset($mobilecfg['p1']['myident']['asn1dn'])) {
			$ph1ent['myid_type'] = "asn1dn";
			$ph1ent['myid_data'] = $mobilecfg['p1']['myident']['asn1dn'];
		}
		if (isset($mobilecfg['p1']['myident']['dyn_dns'])) {
			$ph1ent['myid_type'] = "dyn_dns";
			$ph1ent['myid_data'] = $mobilecfg['p1']['myident']['dyn_dns'];
		}
		$ph1ent['peerid_type'] = "fqdn";
		$ph1ent['peerid_data'] = "";

		switch ($mobilecfg['p1']['encryption-algorithm']) {
			case "des":
			$ph1alg = array( 'name' => 'des' );
			break;
			case "3des":
			$ph1alg = array( 'name' => '3des' );
			break;
			case "blowfish":
			$ph1alg = array( 'name' => 'blowfish', 'keylen' => '128'  );
			break;
			case "cast128":
			$ph1alg = array( 'name' => 'cast128' );
			break;
			case "rijndael":
			$ph1alg = array( 'name' => 'aes', 'keylen' => '128' );
			break;
			case "rijndael 256":
			case "aes 256":
			$ph1alg = array( 'name' => 'aes', 'keylen' => '256' );
			break;
		}

		$ph1ent['encryption-algorithm'] = $ph1alg;
		$ph1ent['hash-algorithm'] = $mobilecfg['p1']['hash-algorithm'];
		$ph1ent['dhgroup'] = $mobilecfg['p1']['dhgroup'];
		$ph1ent['lifetime'] = $mobilecfg['p1']['lifetime'];
		$ph1ent['authentication_method'] = $mobilecfg['p1']['authentication_method'];

		if (isset($mobilecfg['p1']['cert']))
			$ph1ent['cert'] = $mobilecfg['p1']['cert'];
		if (isset($mobilecfg['p1']['peercert']))
			$ph1ent['peercert'] = $mobilecfg['p1']['peercert'];
		if (isset($mobilecfg['p1']['private-key']))
			$ph1ent['private-key'] = $mobilecfg['p1']['private-key'];

		$ph1ent['nat_traversal'] = "on";
		$ph1ent['dpd_enable'] = 1;
		$ph1ent['dpd_delay'] = 10;
		$ph1ent['dpd_maxfail'] = 5;
		$ph1ent['mobile'] = true;

		$ph2ent = array();
		$ph2ent['ikeid'] = $ph1ent['ikeid'];
		$ph2ent['descr'] = "phase2 for ".$mobilecfg['descr'];
		$ph2ent['localid'] = array('type' => 'none');
		$ph2ent['remoteid'] = array('type' => 'mobile');
		$ph2ent['protocol'] = $mobilecfg['p2']['protocol'];

		$aes_count = 0;
		foreach( $mobilecfg['p2']['encryption-algorithm-option'] as $tunalg ) {
			$aes_found = false;
			switch ($tunalg) {
				case "des":
				$ph2alg = array( 'name' => 'des' );
				break;
				case "3des":
				$ph2alg = array( 'name' => '3des' );
				break;
				case "blowfish":
				$ph2alg = array( 'name' => 'blowfish', 'keylen' => 'auto'  );
				break;
				case "cast128":
				$ph2alg = array( 'name' => 'cast128' );
				break;
				case "rijndael":
				case "rijndael 256":
				case "aes 256":
				$ph2alg = array( 'name' => 'aes', 'keylen' => 'auto' );
				$aes_found = true;
				$aes_count++;
				break;
			}

			if( !$aes_found || ($aes_count < 2))
				$ph2ent['encryption-algorithm-option'][] = $ph2alg;
		}
		$ph2ent['hash-algorithm-option'] = $mobilecfg['p2']['hash-algorithm-option'];
		$ph2ent['pfsgroup'] = $mobilecfg['p2']['pfsgroup'];
		$ph2ent['lifetime'] = $mobilecfg['p2']['lifetime'];
		$ph2ent['mobile'] = true;

		$config['ipsec']['phase1'][] = $ph1ent;
		$config['ipsec']['phase2'][] = $ph2ent;
		unset($config['ipsec']['mobileclients']);
	}
}


function upgrade_047_to_048() {
	global $config;
	if (!empty($config['dyndns'])) {
		$config['dyndnses'] = array();
		$config['dyndnses']['dyndns'] = array();
		if(isset($config['dyndns'][0]['host'])) {
			$tempdyn = array();
			$tempdyn['enable'] = isset($config['dyndns'][0]['enable']);
			$tempdyn['type'] = $config['dyndns'][0]['type'];
			$tempdyn['wildcard'] = isset($config['dyndns'][0]['wildcard']);
			$tempdyn['username'] = $config['dyndns'][0]['username'];
			$tempdyn['password'] = $config['dyndns'][0]['password'];
			$tempdyn['host'] = $config['dyndns'][0]['host'];
			$tempdyn['mx'] = $config['dyndns'][0]['mx'];
			$tempdyn['interface'] = "wan";
			$tempdyn['descr'] = sprintf(gettext("Upgraded Dyndns %s"), $tempdyn['type']);
			$config['dyndnses']['dyndns'][] = $tempdyn;
		}
		unset($config['dyndns']);
	}
	if (!empty($config['dnsupdate'])) {
		$pconfig = $config['dnsupdate'][0];
		if (!$pconfig['ttl'])
			$pconfig['ttl'] = 60;
		if (!$pconfig['keytype'])
			$pconfig['keytype'] = "zone";
		$pconfig['interface'] = "wan";
		$config['dnsupdates']['dnsupdate'][] = $pconfig;
		unset($config['dnsupdate']);
	}

	if (is_array($config['pppoe']) && is_array($config['pppoe'][0])) {
		$pconfig = array();
		$pconfig['username'] = $config['pppoe'][0]['username'];
		$pconfig['password'] = $config['pppoe'][0]['password'];
		$pconfig['provider'] = $config['pppoe'][0]['provider'];
		$pconfig['ondemand'] = isset($config['pppoe'][0]['ondemand']);
		$pconfig['timeout'] = $config['pppoe'][0]['timeout'];
		unset($config['pppoe']);
		$config['interfaces']['wan']['pppoe_username'] = $pconfig['username'];
		$config['interfaces']['wan']['pppoe_password'] = $pconfig['password'];
		$config['interfaces']['wan']['provider'] = $pconfig['provider'];
		$config['interfaces']['wan']['ondemand'] = isset($pconfig['ondemand']);
		$config['interfaces']['wan']['timeout'] = $pconfig['timeout'];
	}
	if (is_array($config['pptp'])) {
		$pconfig = array();
		$pconfig['username'] = $config['pptp']['username'];
		$pconfig['password'] = $config['pptp']['password'];
		$pconfig['provider'] = $config['pptp']['provider'];
		$pconfig['ondemand'] = isset($config['pptp']['ondemand']);
		$pconfig['timeout'] = $config['pptp']['timeout'];
		unset($config['pptp']);
		$config['interfaces']['wan']['pptp_username'] = $pconfig['username'];
		$config['interfaces']['wan']['pptp_password'] = $pconfig['password'];
		$config['interfaces']['wan']['provider'] = $pconfig['provider'];
		$config['interfaces']['wan']['ondemand'] = isset($pconfig['ondemand'] );
		$config['interfaces']['wan']['timeout'] = $pconfig['timeout'];
	}
}


function upgrade_048_to_049() {
	global $config;
	/* setup new all users group */
	$all = array();
	$all['name'] = "all";
	$all['description'] = gettext("All Users");
	$all['scope'] = "system";
	$all['gid'] = 1998;
	$all['member'] = array();

	if (!is_array($config['system']['user']))
		$config['system']['user'] = array();
	if (!is_array($config['system']['group']))
		$config['system']['group'] = array();

	/* work around broken uid assignments */
	$config['system']['nextuid'] = 2000;
	foreach ($config['system']['user'] as & $user) {
		if (isset($user['uid']) && !$user['uid'])
			continue;
		$user['uid'] = $config['system']['nextuid']++;
	}

	/* work around broken gid assignments */
	$config['system']['nextgid'] = 2000;
	foreach ($config['system']['group'] as & $group) {
		if ($group['name'] == $g['admin_group'])
			$group['gid'] = 1999;
		else
			$group['gid'] = $config['system']['nextgid']++;
	}

	/* build group membership information */
	foreach ($config['system']['group'] as & $group) {
		$group['member'] = array();
		foreach ($config['system']['user'] as & $user) {
			$groupnames = explode(",", $user['groupname']);
			if (in_array($group['name'],$groupnames))
				$group['member'][] = $user['uid'];
		}
	}

	/* reset user group information */
	foreach ($config['system']['user'] as & $user) {
		unset($user['groupname']);
		$all['member'][] = $user['uid'];
	}

	/* reset group scope information */
	foreach ($config['system']['group'] as & $group)
		if ($group['name'] != $g['admin_group'])
		$group['scope'] = "user";

	/* insert new all group */
	$groups = Array();
	$groups[] = $all;
	$groups = array_merge($config['system']['group'],$groups);
	$config['system']['group'] = $groups;
}


function upgrade_049_to_050() {
	global $config;

	if (!is_array($config['system']['user']))
		$config['system']['user'] = array();
	/* update user privileges */
	foreach ($config['system']['user'] as & $user) {
		$privs = array();
		if (!is_array($user['priv'])) {
			unset($user['priv']);
			continue;
		}
		foreach ($user['priv'] as $priv) {
			switch($priv['id']) {
				case "hasshell":
				$privs[] = "user-shell-access";
				break;
				case "copyfiles":
				$privs[] = "user-copy-files";
				break;
			}
		}
		$user['priv'] = $privs;
	}

	/* update group privileges */
	foreach ($config['system']['group'] as & $group) {
		$privs = array();
		if (!is_array($group['pages'])) {
			unset($group['pages']);
			continue;
		}
		foreach ($group['pages'] as $page) {
			$priv = map_page_privname($page);
			if ($priv)
				$privs[] = $priv;
		}
		unset($group['pages']);
		$group['priv'] = $privs;
	}

	/* sync all local account information */
	local_sync_accounts();
}


function upgrade_050_to_051() {
	global $config;
	$pconfig = array();
	$pconfig['descr'] = "Set to 0 to disable filtering on the incoming and outgoing member interfaces.";
	$pconfig['tunable'] = "net.link.bridge.pfil_member";
	$pconfig['value'] = "1";
	$config['sysctl']['item'][] = $pconfig;
	$pconfig = array();
	$pconfig['descr'] = "Set to 1 to enable filtering on the bridge interface";
	$pconfig['tunable'] = "net.link.bridge.pfil_bridge";
	$pconfig['value'] = "0";
	$config['sysctl']['item'][] = $pconfig;

	unset($config['bridge']);

	$convert_bridges = false;
	foreach($config['interfaces'] as $intf) {
		if (isset($intf['bridge']) && $intf['bridge'] <> "") {
			$config['bridges'] = array();
			$config['bridges']['bridged'] = array();
			$convert_bridges = true;
			break;
		}
	}
	if ($convert_bridges == true) {
		$i = 0;
		foreach ($config['interfaces'] as $ifr => &$intf) {
			if (isset($intf['bridge']) && $intf['bridge'] <> "") {
				$nbridge = array();
				$nbridge['members'] = "{$ifr},{$intf['bridge']}";
				$nbridge['descr'] = sprintf(gettext("Converted bridged %s"), $ifr);
				$nbridge['bridgeif'] = "bridge{$i}";
				$config['bridges']['bridged'][] = $nbridge;
				unset($intf['bridge']);
				$i++;
			}
		}
	}
}


function upgrade_051_to_052() {
	global $config;
	$config['openvpn'] = array();
	if (!is_array($config['ca']))
		$config['ca'] = array();
	if (!is_array($config['cert']))
		$config['cert'] = array();

	$vpnid = 1;

	/* openvpn server configurations */
	if (is_array($config['installedpackages']['openvpnserver'])) {
		$config['openvpn']['openvpn-server'] = array();

		$index = 1;
		foreach($config['installedpackages']['openvpnserver']['config'] as $server) {

			if (!is_array($server))
				continue;

			if ($server['auth_method'] == "pki") {

				/* create ca entry */
				$ca = array();
				$ca['refid'] = uniqid();
				$ca['descr'] = "OpenVPN Server CA #{$index}";
				$ca['crt'] = $server['ca_cert'];
				$config['ca'][] = $ca;

				/* create ca reference */
				unset($server['ca_cert']);
				$server['caref'] = $ca['refid'];

				/* create a crl entry if needed */
				if (!empty($server['crl'][0])) {
					$crl = array();
					$crl['refid'] = uniqid();
					$crl['descr'] = "Imported OpenVPN CRL #{$index}";
					$crl['caref'] = $ca['refid'];
					$crl['text'] = $server['crl'][0];
					if(!is_array($config['crl']))
						$config['crl'] = array();
					$config['crl'][] = $crl;
					$server['crlref'] = $crl['refid'];
				}
				unset($server['crl']);

				/* create cert entry */
				$cert = array();
				$cert['refid'] = uniqid();
				$cert['descr'] = "OpenVPN Server Certificate #{$index}";
				$cert['crt'] = $server['server_cert'];
				$cert['prv'] = $server['server_key'];
				$config['cert'][] = $cert;

				/* create cert reference */
				unset($server['server_cert']);
				unset($server['server_key']);
				$server['certref'] = $cert['refid'];

				$index++;
			}

			/* determine operational mode */
			if ($server['auth_method'] == 'pki') {
				if($server['nopool']) {
					$server['mode'] = "p2p_tls";
				} else {
					$server['mode'] = "server_tls";
				}
			} else {
				$server['mode'] = "p2p_shared_key";
			}
			unset($server['auth_method']);

			/* modify configuration values */
			$server['dh_length'] = 1024;
			unset($server['dh_params']);
			if (!$server['interface'])
				$server['interface'] = 'any';
			$server['tunnel_network'] = $server['addresspool'];
			unset($server['addresspool']);
			if (isset($server['use_lzo']) && ($server['use_lzo'] == "on")) {
				$server['compression'] = "on";
				unset($server['use_lzo']);
			}
			if ($server['nopool'])
				$server['pool_enable'] = false;
			else
				$server['pool_enable'] = "yes";
			unset($server['nopool']);
			$server['dns_domain'] = $server['dhcp_domainname'];
			unset($server['dhcp_domainname']);

			$tmparr = explode(";", $server['dhcp_dns'], 4);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$server["dns_server{$d}"] = $tmpa;
				$d++;
			}
			unset($server['dhcp_dns']);

			$tmparr = explode(";", $server['dhcp_ntp'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$server["ntp_server{$d}"] = $tmpa;
				$d++;
			}
			unset($server['dhcp_ntp']);

			if ($server['dhcp_nbtdisable'])
				$server['netbios_enable'] = false;
			else
				$server['netbios_enable'] = "yes";
			unset($server['dhcp_nbtdisable']);
			$server['netbios_ntype'] = $server['dhcp_nbttype'];
			unset($server['dhcp_nbttype']);
			$server['netbios_scope'] = $server['dhcp_nbtscope'];
			unset($server['dhcp_nbtscope']);

			$tmparr = explode(";", $server['dhcp_nbdd'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$server["nbdd_server{$d}"] = $tmpa;
				$d++;
			}
			unset($server['dhcp_nbdd']);

			$tmparr = explode(";", $server['dhcp_wins'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$server["wins_server{$d}"] = $tmpa;
				$d++;
			}
			unset($server['dhcp_wins']);

			if (!empty($server['disable']))
				$server['disable'] = true;
			else
				unset($server['disable']);

			/* allocate vpnid */
			$server['vpnid'] = $vpnid++;

			if (!empty($server['custom_options'])) {
				$cstmopts = array();
				$tmpcstmopts = explode(";", $server['custom_options']);
				$assigned_if = "";
				$tmpstr = "";
				foreach ($tmpcstmopts as $tmpcstmopt) {
					$tmpstr = str_replace(" ", "", $tmpcstmopt);
					if (substr($tmpstr,0 ,6) == "devtun") {
						$assigned_if = substr($tmpstr, 3);
						continue;
					} else if (substr($tmpstr, 0, 5) == "local") {
						$localip = substr($tmpstr, 5);
						$server['ipaddr'] = str_replace("\n", "", $localip);
					} else
						$cstmopts[] = $tmpcstmopt;
				}
				$server['custom_options'] = implode(";", $cstmopts);
				if (!empty($assigned_if)) {
					foreach ($config['interfaces'] as $iface => $cfgif) {
						if ($cfgif['if'] == $assigned_if) {
							$config['interfaces'][$iface]['if'] = "ovpns{$server['vpnid']}";
							break;
						}
					}
				}
			}

			$config['openvpn']['openvpn-server'][] = $server;
		}
		unset($config['installedpackages']['openvpnserver']);
	}

	/* openvpn client configurations */
	if (is_array($config['installedpackages']['openvpnclient'])) {
		$config['openvpn']['openvpn-client'] = array();

		$index = 1;
		foreach($config['installedpackages']['openvpnclient']['config'] as $client) {

			if (!is_array($client))
				continue;

			if ($client['auth_method'] == "pki") {

				/* create ca entry */
				$ca = array();
				$ca['refid'] = uniqid();
				$ca['descr'] = "OpenVPN Client CA #{$index}";
				$ca['crt'] = $client['ca_cert'];
				$ca['crl'] = $client['crl'];
				$config['ca'][] = $ca;

				/* create ca reference */
				unset($client['ca_cert']);
				unset($client['crl']);
				$client['caref'] = $ca['refid'];

				/* create cert entry */
				$cert = array();
				$cert['refid'] = uniqid();
				$cert['descr'] = "OpenVPN Client Certificate #{$index}";
				$cert['crt'] = $client['client_cert'];
				$cert['prv'] = $client['client_key'];
				$config['cert'][] = $cert;

				/* create cert reference */
				unset($client['client_cert']);
				unset($client['client_key']);
				$client['certref'] = $cert['refid'];

				$index++;
			}

			/* determine operational mode */
			if ($client['auth_method'] == 'pki')
				$client['mode'] = "p2p_tls";
			else
				$client['mode'] = "p2p_shared_key";
			unset($client['auth_method']);

			/* modify configuration values */
			if (!$client['interface'])
				$client['interface'] = 'wan';
			$client['tunnel_network'] = $client['interface_ip'];
			unset($client['interface_ip']);
			$client['server_addr'] = $client['serveraddr'];
			unset($client['serveraddr']);
			$client['server_port'] = $client['serverport'];
			unset($client['serverport']);
			$client['proxy_addr'] = $client['poxy_hostname'];
			unset($client['proxy_addr']);
			if (isset($client['use_lzo']) && ($client['use_lzo'] == "on")) {
				$client['compression'] = "on";
				unset($client['use_lzo']);
			}
			$client['resolve_retry'] = $client['infiniteresolvretry'];
			unset($client['infiniteresolvretry']);

			/* allocate vpnid */
			$client['vpnid'] = $vpnid++;

			if (!empty($client['custom_options'])) {
				$cstmopts = array();
				$tmpcstmopts = explode(";", $client['custom_options']);
				$assigned_if = "";
				$tmpstr = "";
				foreach ($tmpcstmopts as $tmpcstmopt) {
					$tmpstr = str_replace(" ", "", $tmpcstmopt);
					if (substr($tmpstr,0 ,6) == "devtun") {
						$assigned_if = substr($tmpstr, 3);
						continue;
					} else if (substr($tmpstr, 0, 5) == "local") {
						$localip = substr($tmpstr, 5);
						$client['ipaddr'] = str_replace("\n", "", $localip);
					} else
						$cstmopts[] = $tmpcstmopt;
				}
				$client['custom_options'] = implode(";", $cstmopts);
				if (!empty($assigned_if)) {
					foreach ($config['interfaces'] as $iface => $cfgif) {
						if ($cfgif['if'] == $assigned_if) {
							$config['interfaces'][$iface]['if'] = "ovpnc{$client['vpnid']}";
							break;
						}
					}
				}
			}

			if (!empty($client['disable']))
				$client['disable'] = true;
			else
				unset($client['disable']);

			$config['openvpn']['openvpn-client'][] = $client;
		}

		unset($config['installedpackages']['openvpnclient']);
	}

	/* openvpn client specific configurations */
	if (is_array($config['installedpackages']['openvpncsc'])) {
		$config['openvpn']['openvpn-csc'] = array();

		foreach($config['installedpackages']['openvpncsc']['config'] as $csc) {

			if (!is_array($csc))
				continue;

			/* modify configuration values */
			$csc['common_name'] = $csc['commonname'];
			unset($csc['commonname']);
			$csc['tunnel_network'] = $csc['ifconfig_push'];
			unset($csc['ifconfig_push']);
			$csc['dns_domain'] = $csc['dhcp_domainname'];
			unset($csc['dhcp_domainname']);

			$tmparr = explode(";", $csc['dhcp_dns'], 4);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$csc["dns_server{$d}"] = $tmpa;
				$d++;
			}
			unset($csc['dhcp_dns']);

			$tmparr = explode(";", $csc['dhcp_ntp'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$csc["ntp_server{$d}"] = $tmpa;
				$d++;
			}
			unset($csc['dhcp_ntp']);

			if ($csc['dhcp_nbtdisable'])
				$csc['netbios_enable'] = false;
			else
				$csc['netbios_enable'] = "yes";
			unset($csc['dhcp_nbtdisable']);
			$csc['netbios_ntype'] = $csc['dhcp_nbttype'];
			unset($csc['dhcp_nbttype']);
			$csc['netbios_scope'] = $csc['dhcp_nbtscope'];
			unset($csc['dhcp_nbtscope']);

			$tmparr = explode(";", $csc['dhcp_nbdd'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$csc["nbdd_server{$d}"] = $tmpa;
				$d++;
			}
			unset($csc['dhcp_nbdd']);

			$tmparr = explode(";", $csc['dhcp_wins'], 2);
			$d=1;
			foreach ($tmparr as $tmpa) {
				$csc["wins_server{$d}"] = $tmpa;
				$d++;
			}
			unset($csc['dhcp_wins']);

			if (!empty($csc['disable']))
				$csc['disable'] = true;
			else
				unset($csc['disable']);

			$config['openvpn']['openvpn-csc'][] = $csc;
		}

		unset($config['installedpackages']['openvpncsc']);
	}

	if (count($config['openvpn']['openvpn-server']) > 0 ||
		count($config['openvpn']['openvpn-client']) > 0) {
		$ovpnrule = array();
		$ovpnrule['type'] = "pass";
		$ovpnrule['interface'] = "openvpn";
		$ovpnrule['statetype'] = "keep state";
		$ovpnrule['source'] = array();
		$ovpnrule['destination'] = array();
		$ovpnrule['source']['any'] = true;
		$ovpnrule['destination']['any'] = true;
		$ovpnrule['descr'] = gettext("Auto added OpenVPN rule from config upgrade.");
		$config['filter']['rule'][] = $ovpnrule;
	}

	/*
		* FIXME: hack to keep things working with no installedpackages
		* or carp array in the configuration data.
		*/
	if (!is_array($config['installedpackages']))
		$config['installedpackages'] = array();
	if (!is_array($config['installedpackages']['carp']))
		$config['installedpackages']['carp'] = array();

}


function upgrade_052_to_053() {
	global $config;
	if (!is_array($config['ca']))
		$config['ca'] = array();
	if (!is_array($config['cert']))
		$config['cert'] = array();

	/* migrate advanced admin page webui ssl to certifcate mngr */
	if ($config['system']['webgui']['certificate'] &&
	$config['system']['webgui']['private-key']) {

		/* create cert entry */
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = "webConfigurator SSL Certificate";
		$cert['crt'] = $config['system']['webgui']['certificate'];
		$cert['prv'] = $config['system']['webgui']['private-key'];
		$config['cert'][] = $cert;

		/* create cert reference */
		unset($config['system']['webgui']['certificate']);
		unset($config['system']['webgui']['private-key']);
		$config['system']['webgui']['ssl-certref'] = $cert['refid'];
	}

	/* migrate advanced admin page ssh keys to user manager */
	if ($config['system']['ssh']['authorizedkeys']) {
		$admin_user =& getUserEntryByUID(0);
		$admin_user['authorizedkeys'] = $config['system']['ssh']['authorizedkeys'];
		unset($config['system']['ssh']['authorizedkeys']);
	}
}


function upgrade_053_to_054() {
	global $config;
	if(is_array($config['load_balancer']['lbpool'])) {
		$lbpool_arr = $config['load_balancer']['lbpool'];
		$lbpool_srv_arr = array();
		$gateway_group_arr = array();
		$gateways = return_gateways_array();
		$group_name_changes = array();
		if (! is_array($config['gateways']['gateway_item']))
			$config['gateways']['gateway_item'] = array();

		$a_gateways =& $config['gateways']['gateway_item'];
		foreach($lbpool_arr as $lbpool) {
			if($lbpool['type'] == "gateway") {
				// Gateway Groups have to have valid names in pf, old lb pools did not. Clean them up.
				$group_name = preg_replace("/[^A-Za-z0-9]/", "", $lbpool['name'] );
				// If we made and changes, check for collisions and note the change.
				if ($group_name != $lbpool['name']) {
					// Make sure the name isn't already in use.
					foreach ($gateway_group_arr as $gwg) {
						// If the name is in use, add some random bits to avoid collision.
						if ($gwg['name'] == $group_name)
							$group_name .= uniqid();
					}
					$group_name_changes[$lbpool['name']] = $group_name;
				}
				$gateway_group['name'] = $group_name;
				$gateway_group['descr'] = $lbpool['descr'];
				$gateway_group['trigger'] = "down";
				$gateway_group['item'] = array();
				$i = 0;
				foreach($lbpool['servers'] as $member) {
					$split = explode("|", $member);
					$interface = $split[0];
					$monitor = $split[1];
					/* on static upgraded configuration we automatically prepend GW_ */
					$static_name = "GW_" . strtoupper($interface);
					if(is_ipaddr($monitor))
						foreach ($a_gateways as & $gw)
							if ($gw['name'] == $static_name)
								$gw['monitor'] = $monitor;

					/* on failover increment tier. Else always assign 1 */
					if($lbpool['behaviour'] == "failover") {
						$i++;
					} else {
						$i = 1;
					}
					$gateway_group['item'][] = "$static_name|$i";
				}
				$gateway_group_arr[] = $gateway_group;
			} else {
				$lbpool_srv_arr[] = $lbpool;
			}
		}
		$config['load_balancer']['lbpool'] = $lbpool_srv_arr;
		$config['gateways']['gateway_group'] = $gateway_group_arr;
	}
	// Unset lbpool if we no longer have any server pools
	if (count($lbpool_srv_arr) == 0) {
		if(empty($config['load_balancer'])) {
			unset($config['load_balancer']);
		} else {
			unset($config['load_balancer']['lbpool']);
		}
	} else {
		$config['load_balancer']['lbpool'] = $lbpool_srv_arr;
	}
	// Only set the gateway group array if we converted any
	if (count($gateway_group_arr) != 0) {
		$config['gateways']['gateway_group'] = $gateway_group_arr;
		// Update any rules that had a gateway change, if any.
		if (count($group_name_changes) > 0)
			foreach ($config['filter']['rule'] as & $rule)
				if (!empty($rule["gateway"]) && array_key_exists($rule["gateway"], $group_name_changes))
					$rule["gateway"] = $group_name_changes[$rule["gateway"]];
	}
}


function upgrade_054_to_055() {
	global $config;
	global $g;

	/* RRD files changed for quality, traffic and packets graphs */
	//ini_set("max_execution_time", "1800");
	/* convert traffic RRD file */
	global $parsedcfg, $listtags;
	$listtags = array("ds", "v", "rra", "row");

	$rrddbpath = "/var/db/rrd/";
	$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";
	if ($g['platform'] != "pfSense") {
		/* restore the databases, if we have one */
		if (restore_rrd()) {
			/* Make sure to move the rrd backup out of the way. We will make a new one after converting. */
			rename("{$g['cf_conf_path']}/rrd.tgz", "{$g['cf_conf_path']}/backup");
		}
	}

	$rrdinterval = 60;
	$valid = $rrdinterval * 2;

	/* Asume GigE for now */
	$downstream = 125000000;
	$upstream = 125000000;

	/* build a list of quality databases */
	/* roundtrip has become delay */
	function divide_delay($delayval) {
		$delayval = floatval($delayval);
		$delayval = ($delayval / 1000);
		$delayval = " ". sprintf("%1.10e", $delayval) ." ";
		return $delayval;
	}
	/* the roundtrip times need to be divided by 1000 to get seconds, really */
	$databases = array();
	if (!file_exists($rrddbpath))
		@mkdir($rrddbpath);
	chdir($rrddbpath);
	$databases = glob("*-quality.rrd");
	rsort($databases);
	foreach($databases as $database) {
		$xmldump = "{$database}.old.xml";
		$xmldumpnew = "{$database}.new.xml";

		if ($g['booting'])
			echo "Migrate RRD database {$database} to new format for IPv6 \n";
		mwexec("$rrdtool tune {$rrddbpath}{$database} -r roundtrip:delay 2>&1");

		dump_rrd_to_xml("{$rrddbpath}/{$database}", "{$g['tmp_path']}/{$xmldump}");
		$rrdold = xml2array(file_get_contents("{$g['tmp_path']}/{$xmldump}"), 1, "tag");
		$rrdold = $rrdold['rrd'];

		$i = 0;
		foreach($rrdold['rra'] as $rra) {
			$l = 0;
			foreach($rra['database']['row'] as $row) {
				$vnew = divide_delay($row['v'][1]);
				$rrdold['rra'][$i]['database']['row'][$l]['v'][1] = $vnew;
				$l++;
			}
			$i++;
		}

		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", dump_xml_config_raw($rrdold, "rrd"));
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");

		unset($rrdold);
	}
	/* let apinger recreate required files */
	setup_gateways_monitor();

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach($databases as $database) {
		$databasetmp = "{$database}.tmp";
		$xmldump = "{$database}.old.xml";
		$xmldumptmp = "{$database}.tmp.xml";
		$xmldumpnew = "{$database}.new.xml";

		if ($g['booting'])
			echo "Migrate RRD database {$database} to new format \n";
		/* rename DS source */
		mwexec("$rrdtool tune {$rrddbpath}/{$database} -r in:inpass 2>&1");
		mwexec("$rrdtool tune {$rrddbpath}/{$database} -r out:outpass 2>71");

		/* dump contents to xml and move database out of the way */
		dump_rrd_to_xml("{$rrddbpath}/{$database}", "{$g['tmp_path']}/{$xmldump}");

		/* create new rrd database file */
		$rrdcreate = "$rrdtool create {$g['tmp_path']}/{$databasetmp} --step $rrdinterval ";
		$rrdcreate .= "DS:inpass:COUNTER:$valid:0:$downstream ";
		$rrdcreate .= "DS:outpass:COUNTER:$valid:0:$upstream ";
		$rrdcreate .= "DS:inblock:COUNTER:$valid:0:$downstream ";
		$rrdcreate .= "DS:outblock:COUNTER:$valid:0:$upstream ";
		$rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
		$rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
		$rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
		$rrdcreate .= "RRA:AVERAGE:0.5:720:1000 ";

		create_new_rrd("$rrdcreate");
		/* create temporary xml from new RRD */
		dump_rrd_to_xml("{$g['tmp_path']}/{$databasetmp}", "{$g['tmp_path']}/{$xmldumptmp}");

		$rrdold = xml2array(file_get_contents("{$g['tmp_path']}/{$xmldump}"), 1, "tag");
		$rrdold = $rrdold['rrd'];

		$rrdnew = xml2array(file_get_contents("{$g['tmp_path']}/{$xmldumptmp}"), 1, "tag");
		$rrdnew = $rrdnew['rrd'];

		/* remove any MAX RRA's. Not needed for traffic. */
		$i = 0;
		foreach ($rrdold['rra'] as $rra) {
			if(trim($rra['cf']) == "MAX") {
				unset($rrdold['rra'][$i]);
			}
			$i++;
		}

		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", dump_xml_config_raw(migrate_rrd_format($rrdold, $rrdnew), "rrd"));
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");
		/* we now have the rrd with the new fields, adjust the size now. */
		/* RRA 2 is 60 minutes, RRA 3 is 720 minutes */
		mwexec("/bin/sync");
		mwexec("$rrdtool resize {$rrddbpath}/{$database} 2 GROW 2000;/bin/mv resize.rrd {$rrddbpath}/{$database} 2>&1");
		mwexec("/bin/sync");
		mwexec("$rrdtool resize {$rrddbpath}/{$database} 3 GROW 2000;/bin/mv resize.rrd {$rrddbpath}/{$database} 2>&1");
		unset($rrdxmlarray);
	}
	enable_rrd_graphing();
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C NO_REMOUNT=1 RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	if ($g['booting'])
		echo "Updating configuration...";
}


function upgrade_055_to_056() {
	global $config;

	if (!is_array($config['ca']))
		$config['ca'] = array();
	if (!is_array($config['cert']))
		$config['cert'] = array();

	/* migrate ipsec ca's to cert manager */
	if (is_array($config['ipsec']['cacert'])) {
		foreach($config['ipsec']['cacert'] as & $cacert) {
			$ca = array();
			$ca['refid'] = uniqid();
			if (is_array($cacert['cert']))
				$ca['crt'] = $cacert['cert'][0];
			else
				$ca['crt'] = $cacert['cert'];
			$ca['descr'] = $cacert['ident'];
			$config['ca'][] = $ca;
		}
		unset($config['ipsec']['cacert']);
	}

	/* migrate phase1 certificates to cert manager */
	if (is_array($config['ipsec']['phase1'])) {
		foreach($config['ipsec']['phase1'] as & $ph1ent) {
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = "IPsec Peer {$ph1ent['remote-gateway']} Certificate";
			if (is_array($ph1ent['cert']))
				$cert['crt'] = $ph1ent['cert'][0];
			else
				$cert['crt'] = $ph1ent['cert'];
			$cert['prv'] = $ph1ent['private-key'];
			$config['cert'][] = $cert;
			$ph1ent['certref'] = $cert['refid'];
			if ($ph1ent['cert'])
				unset($ph1ent['cert']);
			if ($ph1ent['private-key'])
				unset($ph1ent['private-key']);
			if ($ph1ent['peercert'])
				unset($ph1ent['peercert']);
		}
	}
}


function upgrade_056_to_057() {
	global $config;

	if (!is_array($config['system']['user']))
		$config['system']['user'] = array();
	/* migrate captivate portal to user manager */
	if (is_array($config['captiveportal']['user'])) {
		foreach($config['captiveportal']['user'] as $user) {
			// avoid user conflicts
			$found = false;
			foreach ($config['system']['user'] as $userent) {
				if ($userent['name'] == $user['name']) {
					$found = true;
					break;
				}
			}
			if ($found)
				continue;
			$user['scope'] = "user";
			if (isset($user['expirationdate'])) {
				$user['expires'] = $user['expirationdate'];
				unset($user['expirationdate']);
			}
			if (isset($user['password'])) {
				$user['md5-hash'] = $user['password'];
				unset($user['password']);
			}
			$user['uid'] = $config['system']['nextuid']++;
			$config['system']['user'][] = $user;
		}
		unset($config['captiveportal']['user']);
	}
}

function upgrade_057_to_058() {
	global $config;
	/* set all phase2 entries to tunnel mode */
	if (is_array($config['ipsec']['phase2']))
		foreach($config['ipsec']['phase2'] as & $ph2ent)
			$ph2ent['mode'] = 'tunnel';
}

function upgrade_058_to_059() {
	global $config;

	if (is_array($config['schedules']['schedule'])) {
		foreach ($config['schedules']['schedule'] as & $schedl)
			$schedl['schedlabel'] = uniqid();
	}
}

function upgrade_059_to_060() {
	global $config;
	require_once("/etc/inc/certs.inc");
	if (is_array($config['ca'])) {
		/* Locate issuer for all CAs */
		foreach ($config['ca'] as & $ca) {
			$subject = cert_get_subject($ca['crt']);
			$issuer = cert_get_issuer($ca['crt']);
			if($issuer <> $subject) {
				$issuer_crt =& lookup_ca_by_subject($issuer);
				if($issuer_crt)
					$ca['caref'] = $issuer_crt['refid'];
			}
		}

		/* Locate issuer for all certificates */
		if (is_array($config['cert'])) {
			foreach ($config['cert'] as & $cert) {
				$subject = cert_get_subject($cert['crt']);
				$issuer = cert_get_issuer($cert['crt']);
				if($issuer <> $subject) {
					$issuer_crt =& lookup_ca_by_subject($issuer);
					if($issuer_crt)
						$cert['caref'] = $issuer_crt['refid'];
				}
			}
		}
	}
}

function upgrade_060_to_061() {
	global $config;

	if (is_array($config['interfaces']['wan']))
		$config['interfaces']['wan']['enable'] = true;
	if (is_array($config['interfaces']['lan']))
		$config['interfaces']['lan']['enable'] = true;

	/* On 1.2.3 the "mtu" field adjusted MSS.
	   On 2.x the "mtu" field is actually the MTU. Rename accordingly.
	   See redmine ticket #1886
	*/
	foreach ($config['interfaces'] as $ifr => &$intf) {
		if (isset($intf['mtu']) && is_numeric($intf['mtu'])) {
			$intf['mss'] = $intf['mtu'];
			unset($intf['mtu']);
		}
	}
}

function upgrade_061_to_062() {
	global $config;

	/* Convert NAT port forwarding rules */
	if (is_array($config['nat']['rule'])) {
		$a_nat = &$config['nat']['rule'];

		foreach ($a_nat as &$natent) {
			$natent['disabled'] = false;
			$natent['nordr']    = false;

			$natent['source'] = array(
				"not"     => false,
				"any"     => true,
				"port"    => ""
			);

			$natent['destination'] = array(
				"not"     => false,
				"address" => $natent['external-address'],
				"port"    => $natent['external-port']
			);

			if (empty($natent['destination']['address'])) {
				unset($natent['destination']['address']);
				$natent['destination']['network'] = $natent['interface'] . 'ip';
			} else if ($natent['destination']['address'] == 'any') {
				unset($natent['destination']['address']);
				$natent['destination']['any'] = true;
			}

			unset($natent['external-address']);
			unset($natent['external-port']);
		}

		unset($natent);
	}
}

function upgrade_062_to_063() {
	/* Upgrade legacy Themes to the new pfsense_ng */
	global $config;

	switch($config['theme']) {
		case "nervecenter":
			$config['theme'] = "pfsense_ng";
			break;
	}

}

function upgrade_063_to_064() {
	global $config;
	$j=0;
	$ifcfg = &$config['interfaces'];

	if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			$config['ppps']['ppp'][$pppid]['if'] = "ppp".$j;
			$config['ppps']['ppp'][$pppid]['ptpid'] = $j;
			$j++;
			if (isset($ppp['port'])){
				$config['ppps']['ppp'][$pppid]['ports'] = $ppp['port'];
				unset($config['ppps']['ppp'][$pppid]['port']);
			}
			if (!isset($ppp['type'])){
				$config['ppps']['ppp'][$pppid]['type'] = "ppp";
			}
			if (isset($ppp['defaultgw']))
				unset($config['ppps']['ppp'][$pppid]['defaultgw']);
		}
	}

	if (!is_array($config['ppps']['ppp']))
		$config['ppps']['ppp'] = array();
	$a_ppps = &$config['ppps']['ppp'];

	foreach ($ifcfg as $ifname => $ifinfo) {
		$ppp = array();
		// For pppoe conversion
		if ($ifinfo['ipaddr'] == "pppoe" || $ifinfo['ipaddr'] == "pptp"){
			if (isset($ifinfo['ptpid']))
				continue;
			$ppp['ptpid'] =  $j;
			$ppp['type'] = $ifinfo['ipaddr'];
			$ppp['if'] = $ifinfo['ipaddr'].$j;
			$ppp['ports'] = $ifinfo['if'];
			if ($ifinfo['ipaddr'] == "pppoe"){
				$ppp['username'] = $ifinfo['pppoe_username'];
				$ppp['password'] = base64_encode($ifinfo['pppoe_password']);
			}
			if ($ifinfo['ipaddr'] == "pptp"){
				$ppp['username'] = $ifinfo['pptp_username'];
				$ppp['password'] = base64_encode($ifinfo['pptp_password']);
			}

			if (isset($ifinfo['provider']))
				$ppp['provider'] = $ifinfo['provider'];
			if (isset($ifinfo['ondemand']))
				$ppp['ondemand'] = true;
			if (isset($ifinfo['timeout']))
				$ppp['idletimeout'] = $ifinfo['timeout'];
			if (isset($ifinfo['pppoe']['pppoe-reset-type'])){
				$ppp['pppoe-reset-type'] = $ifinfo['pppoe']['pppoe-reset-type'];
				if (is_array($config['cron']['item'])) {
					for ($i = 0; $i < count($config['cron']['item']); $i++) {
						$item = $config['cron']['item'][$i];
						if (strpos($item['command'], "/conf/pppoe{$ifname}restart") !== false)
							$config['cron']['item'][$i]['command'] = "/var/etc/pppoe_restart_" . $ppp['if'];
					}
				}
			}
			if (isset($ifinfo['local']))
				$ppp['localip'] = $ifinfo['local'];
			if (isset($ifinfo['subnet']))
				$ppp['subnet'] = $ifinfo['subnet'];
			if (isset($ifinfo['remote']))
				$ppp['gateway'] = $ifinfo['remote'];

			$ifcfg[$ifname]['if'] = $ifinfo['ipaddr'].$j;
			$j++;

			unset($ifcfg[$ifname]['pppoe_username']);
			unset($ifcfg[$ifname]['pppoe_password']);
			unset($ifcfg[$ifname]['provider']);
			unset($ifcfg[$ifname]['ondemand']);
			unset($ifcfg[$ifname]['timeout']);
			unset($ifcfg[$ifname]['pppoe_reset']);
			unset($ifcfg[$ifname]['pppoe_preset']);
			unset($ifcfg[$ifname]['pppoe']);
			unset($ifcfg[$ifname]['pptp_username']);
			unset($ifcfg[$ifname]['pptp_password']);
			unset($ifcfg[$ifname]['local']);
			unset($ifcfg[$ifname]['subnet']);
			unset($ifcfg[$ifname]['remote']);

			$a_ppps[] = $ppp;

		}
	}
}

function upgrade_064_to_065() {
	/* Disable TSO and LRO in upgraded configs */
	global $config;
	$config['system']['disablesegmentationoffloading'] = true;
	$config['system']['disablelargereceiveoffloading'] = true;
}

function upgrade_065_to_066() {
	global $config;

	$dhcrelaycfg =& $config['dhcrelay'];

	if (is_array($dhcrelaycfg)) {
		$dhcrelayifs = array();
		$foundifs = false;
		/* DHCPRelay enabled on any interfaces? */
		foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
			if (isset($dhcrelayifconf['enable'])) {
				$dhcrelayifs[] = $dhcrelayif;
				unset($dhcrelaycfg['dhcrelayif']);
				$foundifs = true;
			}
		}
		if ($foundifs == true)
			$dhcrelaycfg['interface'] = implode(",", $dhcrelayifs);
	}
}

function upgrade_066_to_067() {
	global $config;
	if (isset($config['system']['ca'])) {
		$config['ca'] = $config['system']['ca'];
	}
	if (isset($config['system']['cert'])) {
		$config['cert'] = $config['system']['cert'];
	}
}

function upgrade_067_to_068() {
	global $config;

	if (!empty($config['pppoe'])) {
		$config['pppoes'] = array();
		$config['pppoes']['pppoe'] = array();
		$config['pppoes']['pppoe'][] = $config['pppoe'][0];

		if (is_array($config['pppoe']['user'])) {
			$username = array();
			foreach ($config['pppoe']['user'] as $user) {
				$usr = $user['name'] . ":" . base64_encode($user['password']);
				if ($user['ip'])
					$usr .= ":{$user['ip']}";
				$username[] = $usr;
			}
			$config['pppoes']['pppoe'][0]['username'] = implode(" ", $username);
		}
		unset($config['pppoe']);
	}
}

function upgrade_068_to_069() {
	global $config;
	if (!is_array($config['system']['user']))
		return;
	foreach ($config['system']['user'] as & $user) {
		if (!is_array($user['cert']))
			continue;
		$rids = array();
		foreach ($user['cert'] as $id => $cert) {
			if (!isset($cert['descr']))
				continue;
			$tcert = $cert;
			// Make sure each cert gets a refid
			if (!isset($tcert['refid']))
				$tcert['refid'] = uniqid();
			// Keep the cert references for this user
			$rids[] = $tcert['refid'];
			$config['cert'][] = $tcert;
		}
		// Replace user certs with cert references instead.
		if (count($rids) > 0)
			$user['cert'] = $rids;
	}
}

function upgrade_069_to_070() {
	global $config;

	/* Convert NAT 1:1 rules */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $nidx => $natent) {
			if ($natent['subnet'] == 32)
				$config['nat']['onetoone'][$nidx]['source'] = array("address" => $natent['internal']);
			else
				$config['nat']['onetoone'][$nidx]['source'] = array("address" => $natent['internal'] . "/" . $natent['subnet']);

			$config['nat']['onetoone'][$nidx]['destination'] = array("any" => true);

			unset($config['nat']['onetoone'][$nidx]['internal']);
			unset($config['nat']['onetoone'][$nidx]['subnet']);
		}

		unset($natent);
	}
}

function upgrade_070_to_071() {
	global $config;

	if (is_array($config['cron']['item'])) {
		foreach($config['cron']['item'] as $idx => $cronitem) {
			if(stristr($cronitem['command'], "checkreload.sh")) {
				unset($config['cron']['item'][$idx]);
				break;
			}
		}
	}
}

function rename_field(& $section, $oldname, $newname) {
	if (is_array($section)) {
		foreach($section as & $item) {
			if (is_array($item) && !empty($item[$oldname]))
				$item[$newname] = $item[$oldname];
			if (is_array($item) && isset($item[$oldname]))
				unset($item[$oldname]);
		}
	}
}

function upgrade_071_to_072() {
	global $config;
	if (is_array($config['sysctl']) && is_array($config['sysctl']['item']))
		rename_field($config['sysctl']['item'], 'desc', 'descr');
}

function upgrade_072_to_073() {
	global $config;
	if (!is_array($config['load_balancer']))
		return;
	if (is_array($config['load_balancer']['monitor_type']))
		rename_field($config['load_balancer']['monitor_type'], 'desc', 'descr');
	if (is_array($config['load_balancer']['lbpool']))
		rename_field($config['load_balancer']['lbpool'], 'desc', 'descr');
	if (is_array($config['load_balancer']['lbaction']))
		rename_field($config['load_balancer']['lbaction'], 'desc', 'descr');
	if (is_array($config['load_balancer']['lbprotocol']))
		rename_field($config['load_balancer']['lbprotocol'], 'desc', 'descr');
	if (is_array($config['load_balancer']['virtual_server']))
		rename_field($config['load_balancer']['virtual_server'], 'desc', 'descr');
}

function upgrade_073_to_074() {
	global $config;
	rename_field($config['system']['user'], 'fullname', 'descr');
}

function upgrade_074_to_075() {
	global $config;
	if (is_array($config['ca']))
		rename_field($config['ca'], 'name', 'descr');
	if (is_array($config['cert']))
		rename_field($config['cert'], 'name', 'descr');
	if (is_array($config['crl']))
		rename_field($config['crl'], 'name', 'descr');
}

function upgrade_075_to_076() {
	global $config;
	$cron_item = array();
	$cron_item['minute'] = "30";
	$cron_item['hour'] = "12";
	$cron_item['mday'] = "*";
	$cron_item['month'] = "*";
	$cron_item['wday'] = "*";
	$cron_item['who'] = "root";
	$cron_item['command'] = "/usr/bin/nice -n20 /etc/rc.update_urltables";
	$config['cron']['item'][] = $cron_item;
}

function upgrade_076_to_077() {
	global $config;
	foreach($config['filter']['rule'] as & $rule) {
	if (isset($rule['protocol']) && !empty($rule['protocol']))
		$rule['protocol'] = strtolower($rule['protocol']);
	}
}

function upgrade_077_to_078() {
	global $config;
	if (is_array($config['pptpd']) && is_array($config['pptpd']['radius'])
		&& !is_array($config['pptpd']['radius']['server'])) {
		$radarr = array();
		$radsvr = array();
		$radsvr['ip'] = $config['pptpd']['radius']['server'];
		$radsvr['secret'] = $config['pptpd']['radius']['secret'];
		$radsvr['port'] = 1812;
		$radsvr['acctport'] = 1813;
		$radsvr['enable'] = isset($config['pptpd']['radius']['enable']);
		$radarr['accounting'] = isset($config['pptpd']['radius']['accounting']);
		if ($radarr['accounting'])
			$radarr['acct_update'] = $radsvr['ip'];
		$radarr['server'] = $radsvr;
		$config['pptpd']['radius'] = $radarr;
	}
	if (is_array($config['pptpd'])) {
		$config['pptpd']['n_pptp_units'] = empty($config['pptpd']['n_pptp_units']) ? 16 : $config['pptpd']['n_pptp_units'];
	}
}
function upgrade_078_to_079() {
	global $g;
	/* Delete old and unused RRD file */
	unlink_if_exists("{$g['vardb_path']}/rrd/captiveportal-totalusers.rrd");
}

function upgrade_079_to_080() {
	global $config;

	/* Upgrade config in 1.2.3 specifying a username other than admin for synching. */
	if (!empty($config['system']['username']) && is_array($config['installedpackages']['carpsettings']) &&
		is_array($config['installedpackages']['carpsettings']['config'])) {
		$config['installedpackages']['carpsettings']['config'][0]['username'] = $config['system']['username'];
		unset($config['system']['username']);
	}
}

function upgrade_080_to_081() {
	global $config;
	global $g;
	/* Welcome to the 2.1 migration path */

	/* tag all the existing gateways as being IPv4 */
	$i = 0;
	if(is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as $gw) {
			$config['gateways']['gateway_item'][$i]['ipprotocol'] = "inet";
			$i++;
		}
	}

	/* RRD files changed for quality, traffic and packets graphs */
	/* convert traffic RRD file */
	global $parsedcfg, $listtags;
	$listtags = array("ds", "v", "rra", "row");

	$rrddbpath = "/var/db/rrd/";
	$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";

	if ($g['platform'] != "pfSense") {
		/* restore the databases, if we have one */
		if (restore_rrd()) {
			/* Make sure to move the rrd backup out of the way. We will make a new one after converting. */
			@rename("{$g['cf_conf_path']}/rrd.tgz", "{$g['cf_conf_path']}/backup/rrd.tgz");
		}
	}

	$rrdinterval = 60;
	$valid = $rrdinterval * 2;

	/* Asume GigE for now */
	$downstream = 125000000;
	$upstream = 125000000;

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach($databases as $database) {
		$xmldump = "{$database}.old.xml";
		$xmldumpnew = "{$database}.new.xml";

		if ($g['booting'])
			echo "Migrate RRD database {$database} to new format for IPv6.\n";

		/* dump contents to xml and move database out of the way */
		dump_rrd_to_xml("{$rrddbpath}/{$database}", "{$g['tmp_path']}/{$xmldump}");

		/* search and replace tags to add data sources */
		$ds_search = "<!-- Round Robin Archives -->";
		$ds_arr = array();
		$ds_arr[] = "	<ds>
				<name> inpass6 </name>
				<type> COUNTER </type>
				<minimal_heartbeat> {$valid} </minimal_heartbeat>
				<min> 0.0000000000e+00 </min>
				<max> 1.2500000000e+08 </max>

				<!-- PDP Status -->
				<last_ds> 0 </last_ds>
				<value> NaN </value>
				<unknown_sec> 3 </unknown_sec>
			</ds>
			";
		$ds_arr[] = "	<ds>
				<name> outpass6 </name>
				<type> COUNTER </type>
				<minimal_heartbeat> {$valid} </minimal_heartbeat>
				<min> 0.0000000000e+00 </min>
				<max> 1.2500000000e+08 </max>

				<!-- PDP Status -->
				<last_ds> 0 </last_ds>
				<value> NaN </value>
				<unknown_sec> 3 </unknown_sec>
			</ds>
			";
		$ds_arr[] = "	<ds>
				<name> inblock6 </name>
				<type> COUNTER </type>
				<minimal_heartbeat> {$valid} </minimal_heartbeat>
				<min> 0.0000000000e+00 </min>
				<max> 1.2500000000e+08 </max>

				<!-- PDP Status -->
				<last_ds> 0 </last_ds>
				<value> NaN </value>
				<unknown_sec> 3 </unknown_sec>
			</ds>
			";
		$ds_arr[] = "	<ds>
				<name> outblock6 </name>
				<type> COUNTER </type>
				<minimal_heartbeat> {$valid} </minimal_heartbeat>
				<min> 0.0000000000e+00 </min>
				<max> 1.2500000000e+08 </max>

				<!-- PDP Status -->
				<last_ds> 0 </last_ds>
				<value> NaN </value>
				<unknown_sec> 3 </unknown_sec>
			</ds>
			";

		$cdp_search = "<\/cdp_prep>";
		$cdp_replace = "</cdp_prep>";
		$cdp_arr = array();
		$cdp_arr[] = "			<ds>
					<primary_value> NaN </primary_value>
					<secondary_value> 0.0000000000e+00 </secondary_value>
					<value> NaN </value>
					<unknown_datapoints> 0 </unknown_datapoints>
					</ds>
		";
		$cdp_arr[] = "			<ds>
					<primary_value> NaN </primary_value>
					<secondary_value> 0.0000000000e+00 </secondary_value>
					<value> NaN </value>
					<unknown_datapoints> 0 </unknown_datapoints>
					</ds>
		";
		$cdp_arr[] = "			<ds>
					<primary_value> NaN </primary_value>
					<secondary_value> 0.0000000000e+00 </secondary_value>
					<value> NaN </value>
					<unknown_datapoints> 0 </unknown_datapoints>
					</ds>
		";
		$cdp_arr[] = "			<ds>
					<primary_value> NaN </primary_value>
					<secondary_value> 0.0000000000e+00 </secondary_value>
					<value> NaN </value>
					<unknown_datapoints> 0 </unknown_datapoints>
					</ds>
		";

		$value_search = "<\/row>";
		$value_replace = "</row>";
		$value = "<v> NaN </v>";

		$xml = file_get_contents("{$g['tmp_path']}/{$xmldump}");
		foreach($ds_arr as $ds) {
			$xml = preg_replace("/$ds_search/s", "$ds{$ds_search}", $xml);
		}
		foreach($cdp_arr as $cdp) {
			$xml = preg_replace("/$cdp_search/s", "$cdp{$cdp_replace}", $xml);
		}
		foreach($ds_arr as $ds) {
			$xml = preg_replace("/$value_search/s", "$value{$value_replace}", $xml);
		}
		
		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", $xml);
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");
		unset($xml);
		# Default /tmp tmpfs is ~40mb, do not leave temp files around
		unlink_if_exists("{$g['tmp_path']}/{$xmldump}");
		unlink_if_exists("{$g['tmp_path']}/{$xmldumpnew}");
	}
	enable_rrd_graphing();
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C NO_REMOUNT=1 RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	if ($g['booting'])
		echo "Updating configuration...";
	foreach($config['filter']['rule'] as & $rule) {
		if (isset($rule['protocol']) && !empty($rule['protocol']))
			$rule['protocol'] = strtolower($rule['protocol']);
	}
	unset($rule);
}

function upgrade_081_to_082() {
	/* don't enable the allow IPv6 toggle */
}

function upgrade_082_to_083() {
	global $config;

	/* Upgrade captiveportal config */
	if (!empty($config['captiveportal'])) {
		$tmpcp = $config['captiveportal'];
		$config['captiveportal'] = array();
		$config['captiveportal']['cpzone'] = array();
		$config['captiveportal']['cpzone'] = $tmpcp;
		$config['captiveportal']['cpzone']['zoneid'] = 8000;
		$config['captiveportal']['cpzone']['zone'] = "cpzone";
		if ($config['captiveportal']['cpzone']['auth_method'] == "radius")
			$config['captiveportal']['cpzone']['radius_protocol'] = "PAP";
	}
	if (!empty($config['voucher'])) {
		$tmpcp = $config['voucher'];
		$config['voucher'] = array();
		$config['voucher']['cpzone'] = array();
		$config['voucher']['cpzone'] = $tmpcp;
	}
}

function upgrade_083_to_084() {
	global $config;
	if (!isset($config['hasync'])) {
		if (!empty($config['installedpackages']) &&
		    !empty($config['installedpackages']['carpsettings']) &&
		    !empty($config['installedpackages']['carpsettings']['config'])) {
			$config['hasync'] = $config['installedpackages']['carpsettings']['config'][0];
			unset($config['installedpackages']['carpsettings']);
		}
		if (empty($config['installedpackages']['carpsettings'])) {
			unset($config['installedpackages']['carpsettings']);
		}
		if (empty($config['installedpackages'])) {
			unset($config['installedpackages']);
		}
	}
}

function upgrade_084_to_085() {
	global $config;

	$gateway_group_arr = array();
	$gateways = return_gateways_array();
	$oldnames = array();
	/* setup translation array */
	foreach($gateways as $name => $gw) {
		if(isset($gw['dynamic'])){
			$oldname = strtoupper($config['interfaces'][$gw['friendlyiface']]['descr']);
			$oldnames[$oldname] = $name;
		} else {
			$oldnames[$name] = $name;
		}
	}

	/* process the old array */
	if(is_array($config['gateways']['gateway_group'])) {
		$group_array_new = array();
		foreach($config['gateways']['gateway_group'] as $name => $group) {
			if(is_array($group['item'])) {
				$newlist = array();
				foreach($group['item'] as $entry) {
					$elements = explode("|", $entry);
					if($oldnames[$elements[0]] <> "") {
						$newlist[] = "{$oldnames[$elements[0]]}|{$elements[1]}";
					} else {
						$newlist[] = "{$elements[0]}|{$elements[1]}";
					}
				}
				$group['item'] = $newlist;
				$group_array_new[$name] = $group;
			}
		}
		$config['gateways']['gateway_group'] = $group_array_new;
	}
	/* rename old Quality RRD files in the process */
	$rrddbpath = "/var/db/rrd";
	foreach($oldnames as $old => $new) {
		if(is_readable("{$rrddbpath}/{$old}-quality.rrd")) {
			@rename("{$rrddbpath}/{$old}-quality.rrd", "{$rrddbpath}/{$new}-quality.rrd");
		}
	}
	unset($gateways, $oldnames, $gateway_group_arr);
}

function upgrade_085_to_086() {
	global $config, $g;

	/* XXX: Gross hacks in sight */
	if (is_array($config['virtualip']['vip'])) {
		$vipchg = array();
		foreach ($config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] != "carp")
				continue;
			$config = array_replace_values_recursive(
				$config,
				'^vip' . $vip['vhid'] . '$',
				"{$vip['interface']}_vip{$vip['vhid']}"
			);
		}
	}
}

function upgrade_086_to_087() {
	global $config, $dummynet_pipe_list;

	if (!is_array($config['filter']) || !is_array($config['filter']['rule']))
		return;
	if (!is_array($config['dnshaper']) || !is_array($config['dnshaper']['queue']))
		return;

	$dnqueue_number = 1;
	$dnpipe_number = 1;

	foreach ($config['dnshaper']['queue'] as $idx => $dnpipe) {
		$config['dnshaper']['queue'][$idx]['number'] = $dnpipe_number;
		$dnpipe_number++;
		if (is_array($dnpipe['queue'])) {
			foreach ($dnpipe['queue'] as $qidx => $dnqueue) {
				$config['dnshaper']['queue'][$idx]['queue'][$qidx]['number'] = $dnqueue_number;
				$dnqueue_number++;
			}
		}
	}

	unset($dnqueue_number, $dnpipe_number, $qidx, $idx, $dnpipe, $dnqueue);

	require_once("shaper.inc");
	read_dummynet_config();

	$dn_list = array();
	if (is_array($dummynet_pipe_list)) {
		foreach ($dummynet_pipe_list as $dn) {
			$tmplist =& $dn->get_queue_list();
			foreach ($tmplist as $qname => $link) {
				$dn_list[$link] = $qname;
			}
		}
		unset($dummynet_pipe_list);
	}

	foreach ($config['filter']['rule'] as $idx => $rule) {
		if (!empty($rule['dnpipe'])) {
			if (!empty($dn_list[$rule['dnpipe']]))
				$config['filter']['rule'][$idx]['dnpipe'] = $dn_list[$rule['dnpipe']];
		}
		if (!empty($rule['pdnpipe'])) {
			if (!empty($dn_list[$rule['pdnpipe']]))
				$config['filter']['rule'][$idx]['pdnpipe'] = $dn_list[$rule['pdnpipe']];
		}
	}
}
function upgrade_087_to_088() {
	global $config;
	if (isset($config['system']['glxsb_enable'])) {
		unset($config['system']['glxsb_enable']);
		$config['system']['crypto_hardware'] = "glxsb";
	}
}

function upgrade_088_to_089() {
	global $config;
	if (!is_array($config['ca']))
		$config['ca'] = array();
	if (!is_array($config['cert']))
		$config['cert'] = array();

	/* migrate captive portal ssl to certifcate mngr */
	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $id => &$setting) {
			if (isset($setting['httpslogin'])) {
				/* create cert entry */
				$cert = array();
				$cert['refid'] = uniqid();
				$cert['descr'] = "Captive Portal Cert - {$setting['zone']}";
				$cert['crt'] = $setting['certificate'];
				$cert['prv'] = $setting['private-key'];

				if (!empty($setting['cacertificate'])) {
					/* create ca entry */
					$ca = array();
					$ca['refid'] = uniqid();
					$ca['descr'] = "Captive Portal CA - {$setting['zone']}";
					$ca['crt'] = $setting['cacertificate'];
					$config['ca'][] = $ca;

					/* add ca reference to certificate */
					$cert['caref'] = $ca['refid'];
				}

				$config['cert'][] = $cert;

				/* create cert reference */
				$setting['certref'] = $cert['refid'];

				unset($setting['certificate']);
				unset($setting['private-key']);
				unset($setting['cacertificate']);

			}
		}
	}
}

function upgrade_089_to_090() {
	global $config;
	if (is_array($config['load_balancer']['virtual_server']) && count($config['load_balancer']['virtual_server'])) {
		$vs_a = &$config['load_balancer']['virtual_server'];
		for ($i = 0; isset($vs_a[$i]); $i++) {
			if (is_array($vs_a[$i]['pool'])) {
				$vs_a[$i]['poolname'] = $vs_a[$i]['pool'][0];
				unset($vs_a[$i]['pool']);
			} elseif (!empty($vs_a[$i]['pool'])) {
				$vs_a[$i]['poolname'] = $vs_a[$i]['pool'];
				unset($vs_a[$i]['pool']);
			}
		}
	}
}

function upgrade_090_to_091() {
	global $config;

	if (is_array($config['dnshaper']) && is_array($config['dnshaper']['queue'])) {
		foreach ($config['dnshaper']['queue'] as $idx => $dnqueue) {
			if (!empty($dnqueue['bandwidth'])) {
				$bw = array();
				$bw['bw'] = $dnqueue['bandwidth'];
				$bw['bwscale'] = $dnqueue['bandwidthtype'];
				$bw['bwsched'] = "none";
				$config['dnshaper']['queue'][$idx]['bandwidth'] = array();
				$config['dnshaper']['queue'][$idx]['bandwidth']['item'] = array();
				$config['dnshaper']['queue'][$idx]['bandwidth']['item'][] = $bw;
			}
		}
	}
}

function upgrade_091_to_092() {
	global $config;

	if (is_array($config['nat']['advancedoutbound']) && is_array($config['nat']['advancedoutbound']['rule'])) {
		$nat_rules = &$config['nat']['advancedoutbound']['rule'];
		for ($i = 0; isset($nat_rules[$i]); $i++) {
			if (empty($nat_rules[$i]['interface'])) {
				$nat_rules[$i]['interface'] = 'wan';
			}
		}
	}
}

function upgrade_092_to_093() {
	global $g;

	$suffixes = array("concurrent", "loggedin");

	foreach ($suffixes as $suffix)
		if (file_exists("{$g['vardb_path']}/rrd/captiveportal-{$suffix}.rrd"))
			rename("{$g['vardb_path']}/rrd/captiveportal-{$suffix}.rrd",
				"{$g['vardb_path']}/rrd/captiveportal-cpZone-{$suffix}.rrd");

	enable_rrd_graphing();
}

function upgrade_093_to_094() {
	global $config;

	if (isset($config['system']['powerd_mode'])) {
		$config['system']['powerd_ac_mode'] = $config['system']['powerd_mode'];
		$config['system']['powerd_battery_mode'] = $config['system']['powerd_mode'];
		unset($config['system']['powerd_mode']);
	}
}

function upgrade_094_to_095() {
	global $config;

	if (!isset($config['interfaces']) || !is_array($config['interfaces']))
		return;

	foreach ($config['interfaces'] as $iface => $cfg)
		if (isset($cfg['ipaddrv6']) && ($cfg['ipaddrv6'] == "track6"))
			if (!isset($cfg['track6-prefix-id']) || ($cfg['track6-prefix-id'] == ""))
				$config['interfaces'][$iface]['track6-prefix-id'] = 0;
}

function upgrade_095_to_096() {
	global $config, $g;

	$names = array("inpass", "outpass", "inblock", "outblock",
		"inpass6", "outpass6", "inblock6", "outblock6");
	$rrddbpath = "/var/db/rrd";
	$rrdtool = "/usr/local/bin/rrdtool";

	if ($g['platform'] != "pfSense") {
		/* restore the databases, if we have one */
		if (restore_rrd()) {
			/* Make sure to move the rrd backup out of the way. We will make a new one after converting. */
			rename("{$g['cf_conf_path']}/rrd.tgz", "{$g['cf_conf_path']}/backup");
		}
	}

	/* Assume 2*10GigE for now */
	$stream = 2500000000;

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach($databases as $database) {
		if ($g['booting'])
			echo "Update RRD database {$database}.\n";

		$cmd = "{$rrdtool} tune {$rrddbpath}/{$database}";
		foreach ($names as $name)
			$cmd .= " -a {$name}:{$stream}";
		mwexec("{$cmd} 2>&1");

	}
	enable_rrd_graphing();
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C NO_REMOUNT=1 RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
}

function upgrade_096_to_097() {
	global $config, $g;
	/* If the user had disabled default block rule logging before, then bogon/private network logging was already off, so respect their choice. */
	if (isset($config['syslog']['nologdefaultblock'])) {
		$config['syslog']['nologbogons'] = true;
		$config['syslog']['nologprivatenets'] = true;
	}
}

function upgrade_097_to_098() {
	global $config, $g;
	/* Disable kill_states by default */
	$config['system']['kill_states'] = true;
}

function upgrade_098_to_099() {
	global $config;

	if (empty($config['dhcpd']) || !is_array($config['dhcpd']))
		return;

	foreach ($config['dhcpd'] as & $dhcpifconf) {
		if (isset($dhcpifconf['next-server'])) {
			$dhcpifconf['nextserver'] = $dhcpifconf['next-server'];
			unset($dhcpifconf['next-server']);
		}
	}
}

function upgrade_099_to_100() {
	require_once("/etc/inc/services.inc");
	install_cron_job("/usr/bin/nice -n20 newsyslog", false);
}

function upgrade_100_to_101() {
	global $config, $g;

	if (!is_array($config['voucher']))
		return;

	foreach ($config['voucher'] as $cpzone => $cp) {
		if (!is_array($cp['roll']))
			continue;
		foreach ($cp['roll'] as $ridx => $rcfg) {
			if (!empty($rcfg['comment']))
				$config['voucher'][$cpzone]['roll'][$ridx]['descr'] = $rcfg['comment'];
		}
	}
}
?>
