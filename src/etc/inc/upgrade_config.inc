<?php
/*
 * upgrade_config.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

if (!function_exists("dump_rrd_to_xml")) {
	require_once("rrd.inc");
}
if (!function_exists("read_altq_config")) {
	require_once("shaper.inc");
}
if (!function_exists("console_configure")) {
	require_once("/etc/inc/pfsense-utils.inc");
}

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
	init_config_arr(array('filter', 'rule'));
	$n = count($config['filter']['rule']);
	for ($i = 0; $i < $n; $i++) {

		$fr = &$config['filter']['rule'][$i];

		/* remap interface */
		if (array_key_exists($fr['interface'], $ifmap)) {
			$fr['interface'] = $ifmap[$fr['interface']];
		} else {
			/* remove the rule */
			printf(gettext("%sWarning: filter rule removed " .
				"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
			unset($config['filter']['rule'][$i]);
			continue;
		}

		/* remap source network */
		if (isset($fr['source']['network'])) {
			if (array_key_exists($fr['source']['network'], $ifmap)) {
				$fr['source']['network'] = $ifmap[$fr['source']['network']];
			} else {
				/* remove the rule */
				printf(gettext("%sWarning: filter rule removed " .
					"(source network '%s' does not exist anymore)."), "\n", $fr['source']['network']);
				unset($config['filter']['rule'][$i]);
				continue;
			}
		}

		/* remap destination network */
		if (isset($fr['destination']['network'])) {
			if (array_key_exists($fr['destination']['network'], $ifmap)) {
				$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
			} else {
				/* remove the rule */
				printf(gettext("%sWarning: filter rule removed " .
					"(destination network '%s' does not exist anymore)."), "\n", $fr['destination']['network']);
				unset($config['filter']['rule'][$i]);
				continue;
			}
		}
	}

	/* convert shaper rules */
	init_config_arr(array('pfqueueing', 'rule'));
	$n = count($config['pfqueueing']['rule']);
	if (is_array($config['pfqueueing']['rule'])) {
		for ($i = 0; $i < $n; $i++) {

			$fr = &$config['pfqueueing']['rule'][$i];

			/* remap interface */
			if (array_key_exists($fr['interface'], $ifmap)) {
				$fr['interface'] = $ifmap[$fr['interface']];
			} else {
				/* remove the rule */
				printf(gettext("%sWarning: traffic shaper rule removed " .
					"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
				unset($config['pfqueueing']['rule'][$i]);
				continue;
			}

			/* remap source network */
			if (isset($fr['source']['network'])) {
				if (array_key_exists($fr['source']['network'], $ifmap)) {
					$fr['source']['network'] = $ifmap[$fr['source']['network']];
				} else {
					/* remove the rule */
					printf(gettext("%sWarning: traffic shaper rule removed " .
						"(source network '%s' does not exist anymore)."), "\n", $fr['source']['network']);
					unset($config['pfqueueing']['rule'][$i]);
					continue;
				}
			}

			/* remap destination network */
			if (isset($fr['destination']['network'])) {
				if (array_key_exists($fr['destination']['network'], $ifmap)) {
					$fr['destination']['network'] = $ifmap[$fr['destination']['network']];
				} else {
					/* remove the rule */
					printf(gettext("%sWarning: traffic shaper rule removed " .
						"(destination network '%s' does not exist anymore)."), "\n", $fr['destination']['network']);
					unset($config['pfqueueing']['rule'][$i]);
					continue;
				}
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
		init_config_arr(array('pfqueueing', 'pipe'));
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
	if (isset($config['interfaces']['wan']['gateway'])) {
		if ($config['interfaces']['wan']['gateway'] <> "") {
			$config['system']['gateway'] = $config['interfaces']['wan']['gateway'];
		}
		unset($config['interfaces']['wan']['gateway']);
	}

	/* Queues are no longer interface specific */
	if (isset($config['interfaces']['lan']['schedulertype'])) {
		unset($config['interfaces']['lan']['schedulertype']);
	}
	if (isset($config['interfaces']['wan']['schedulertype'])) {
		unset($config['interfaces']['wan']['schedulertype']);
	}

	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
		if (isset($config['interfaces']['opt' . $i]['schedulertype'])) {
			unset($config['interfaces']['opt' . $i]['schedulertype']);
		}
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
	}
	if (isset($config['system']['firmwareurl'])) {
		unset($config['system']['firmwareurl']);
	}
	if (isset($config['system']['firmwarename'])) {
		unset($config['system']['firmwarename']);
	}
}


function upgrade_016_to_017() {
	global $config;
	/* wipe previous shaper configuration */
	if (isset($config['shaper']['queue'])) {
		unset($config['shaper']['queue']);
	}
	if (isset($config['shaper']['rule'])) {
		unset($config['shaper']['rule']);
	}
	if (isset($config['interfaces']['wan']['bandwidth'])) {
		unset($config['interfaces']['wan']['bandwidth']);
	}
	if (isset($config['interfaces']['wan']['bandwidthtype'])) {
		unset($config['interfaces']['wan']['bandwidthtype']);
	}
	if (isset($config['interfaces']['lan']['bandwidth'])) {
		unset($config['interfaces']['lan']['bandwidth']);
	}
	if (isset($config['interfaces']['lan']['bandwidthtype'])) {
		unset($config['interfaces']['lan']['bandwidthtype']);
	}
	$config['shaper']['enable'] = FALSE;
}


function upgrade_017_to_018() {
	global $config;
	if (isset($config['proxyarp']) && is_array($config['proxyarp']['proxyarpnet'])) {
		$proxyarp = &$config['proxyarp']['proxyarpnet'];
		foreach ($proxyarp as $arpent) {
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
	if (isset($config['installedpackages']) && isset($config['installedpackages']['carp']) && is_array($config['installedpackages']['carp']['config'])) {
		$carp = &$config['installedpackages']['carp']['config'];
		foreach ($carp as $carpent) {
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
	if (isset($config['nat']['servernat'])) {
		unset($config['nat']['servernat']);
	}

	/* enable SSH */
	if ($config['version'] == "1.8") {
		$config['system']['sshenabled'] = true;
	}
}


function upgrade_018_to_019() {
	global $config;
}


function upgrade_019_to_020() {
	global $config;
	if (is_array($config['ipsec']['tunnel'])) {
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
	if (isset($config['system']['schedulertype'])) {
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
	if (isset($config['shaper'])) {
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
	if (isset($config['system']['use_rrd_gateway'])) {
		unset($config['system']['use_rrd_gateway']);
	}
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
	$cron_item['command'] = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard";

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
	init_config_arr(array('filter', 'rule'));
	$a_filter = &$config['filter']['rule'];
	$rule_item = array();
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

	if (isset($config['system']['username'])) {
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
		$config['system']['user'][0]['priv'][3]['descr'] = sprintf(gettext("Indicates whether this user is allowed to copy files onto the %s appliance via SCP/SFTP."), $g['product_name']);
		$config['system']['user'][0]['priv'][4]['id'] = "isroot";
		$config['system']['user'][0]['priv'][4]['name'] = "Is root user";
		$config['system']['user'][0]['priv'][4]['descr'] = gettext("This user is associated with the UNIX root user (this privilege should only be associated with one single user).");

		$config['system']['nextuid'] = "111";
		$config['system']['nextgid'] = "111";

		/* wipe previous auth configuration */
		unset($config['system']['username']);
		if (isset($config['system']['password'])) {
			unset($config['system']['password']);
		}
	}
}

function upgrade_040_to_041() {
	global $config;
	if (!$config['sysctl']) {
		$config['sysctl']['item'] = array();

		$config['sysctl']['item'][0]['tunable'] = "net.inet.tcp.blackhole";
		$config['sysctl']['item'][0]['descr'] =    gettext("Drop packets to closed TCP ports without returning a RST");
		$config['sysctl']['item'][0]['value'] =   "default";

		$config['sysctl']['item'][1]['tunable'] = "net.inet.udp.blackhole";
		$config['sysctl']['item'][1]['descr'] =    gettext("Do not send ICMP port unreachable messages for closed UDP ports");
		$config['sysctl']['item'][1]['value'] =   "default";

		$config['sysctl']['item'][2]['tunable'] = "net.inet.ip.random_id";
		$config['sysctl']['item'][2]['descr'] =    gettext("Randomize the ID field in IP packets (default is 1: Assign random IP IDs)");
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

		$config['sysctl']['item'][9]['tunable'] = "net.inet.tcp.delayed_ack";
		$config['sysctl']['item'][9]['descr'] =    gettext("Do not delay ACK to try and piggyback it onto a data packet");
		$config['sysctl']['item'][9]['value'] =   "default";

		$config['sysctl']['item'][10]['tunable'] = "net.inet.udp.maxdgram";
		$config['sysctl']['item'][10]['descr'] =    gettext("Maximum outgoing UDP datagram size");
		$config['sysctl']['item'][10]['value'] =   "default";

		$config['sysctl']['item'][11]['tunable'] = "net.link.bridge.pfil_onlyip";
		$config['sysctl']['item'][11]['descr'] =    gettext("Handling of non-IP packets which are not passed to pfil (see if_bridge(4))");
		$config['sysctl']['item'][11]['value'] =   "default";

		$config['sysctl']['item'][12]['tunable'] = "net.link.tap.user_open";
		$config['sysctl']['item'][12]['descr'] =    gettext("Allow unprivileged access to tap(4) device nodes");
		$config['sysctl']['item'][12]['value'] =   "default";

		$config['sysctl']['item'][13]['tunable'] = "kern.randompid";
		$config['sysctl']['item'][13]['descr'] =    gettext("Randomize PID's (see src/sys/kern/kern_fork.c: sysctl_kern_randompid())");
		$config['sysctl']['item'][13]['value'] =   "default";

		$config['sysctl']['item'][14]['tunable'] = "net.inet.tcp.inflight.enable";
		$config['sysctl']['item'][14]['descr'] =    gettext("The system will attempt to calculate the bandwidth delay product for each connection and limit the amount of data queued to the network to just the amount required to maintain optimum throughput. ");
		$config['sysctl']['item'][14]['value'] =   "default";

		$config['sysctl']['item'][15]['tunable'] = "net.inet.icmp.icmplim";
		$config['sysctl']['item'][15]['descr'] =    gettext("Set ICMP Limits");
		$config['sysctl']['item'][15]['value'] =   "default";

		$config['sysctl']['item'][16]['tunable'] = "net.inet.tcp.tso";
		$config['sysctl']['item'][16]['descr'] =    gettext("TCP Offload engine");
		$config['sysctl']['item'][16]['value'] =   "default";

		$config['sysctl']['item'][17]['tunable'] = "net.inet.ip.portrange.first";
		$config['sysctl']['item'][17]['descr'] =    "Set the ephemeral port range starting port";
		$config['sysctl']['item'][17]['value'] =   "default";

		$config['sysctl']['item'][18]['tunable'] = "hw.syscons.kbd_reboot";
		$config['sysctl']['item'][18]['descr'] =    "Enables ctrl+alt+delete";
		$config['sysctl']['item'][18]['value'] =   "default";

		$config['sysctl']['item'][19]['tunable'] = "kern.ipc.maxsockbuf";
		$config['sysctl']['item'][19]['descr'] =    "Maximum socket buffer size";
		$config['sysctl']['item'][19]['value'] =   "default";

	}
}


function upgrade_041_to_042() {
	global $config;
	if (isset($config['shaper'])) {
		unset($config['shaper']);
	}
	if (isset($config['ezshaper'])) {
		unset($config['ezshaper']);
	}
}


function upgrade_042_to_043() {
	global $config;
	/* migrate old interface gateway to the new gateways config */
	$iflist = get_configured_interface_list(true);
	$gateways = array();
	$i = 0;
	foreach ($iflist as $ifname => $interface) {
		if (!interface_has_gateway($ifname)) {
			continue;
		}
		$config['gateways']['gateway_item'][$i] = array();
		if (is_ipaddr($config['interfaces'][$ifname]['gateway'])) {
			$config['gateways']['gateway_item'][$i]['gateway'] = $config['interfaces'][$ifname]['gateway'];
			$config['gateways']['gateway_item'][$i]['descr'] = sprintf(gettext("Interface %s Static Gateway"), $ifname);
		} else {
			$config['gateways']['gateway_item'][$i]['gateway'] = "dynamic";
			$config['gateways']['gateway_item'][$i]['descr'] = sprintf(gettext("Interface %s Dynamic Gateway"), $ifname);
		}
		$config['gateways']['gateway_item'][$i]['interface'] = $ifname;
		$config['gateways']['gateway_item'][$i]['name'] = "GW_" . strtoupper($ifname);
		/* add default gateway bit for wan on upgrade */
		if ($ifname == "wan") {
			$config['gateways']['gateway_item'][$i]['defaultgw'] = true;
		}
		if (is_ipaddr($config['interfaces'][$ifname]['use_rrd_gateway'])) {
			$config['gateways']['gateway_item'][$i]['monitor'] = $config['interfaces'][$ifname]['use_rrd_gateway'];
			unset($config['interfaces'][$ifname]['use_rrd_gateway']);
		}
		$config['interfaces'][$ifname]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];

		/* Update all filter rules which might reference this gateway */
		$j = 0;
		foreach ($config['filter']['rule'] as $rule) {
			if (is_ipaddr($rule['gateway'])) {
				if ($rule['gateway'] == $config['gateways']['gateway_item'][$i]['gateway']) {
					$config['filter']['rule'][$j]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];
				} else if ($rule['gateway'] == $ifname) {
					$config['filter']['rule'][$j]['gateway'] = $config['gateways']['gateway_item'][$i]['name'];
				}
			}
			$j++;
		}

		/* rename old Quality RRD files in the process */
		$rrddbpath = "/var/db/rrd";
		$gwname = "GW_" . strtoupper($ifname);
		if (is_readable("{$rrddbpath}/{$ifname}-quality.rrd")) {
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
			if ($gwmap[$sroute['gateway']]) {
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
				if (!is_array($config['gateways']['gateway_item'])) {
					$config['gateways']['gateway_item'] = array();
				}
				$config['gateways']['gateway_item'][] = $gateway;
				$config['staticroutes']['route'][$idx]['gateway'] = $gateway['name'];
				$i++;
			}
		}
	}
}


function upgrade_044_to_045() {
	global $config;
	$iflist = get_configured_interface_list(true);
	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
		$i = 0;
		foreach ($config['vlans']['vlan'] as $id => $vlan) {
			/* Make sure to update the interfaces section with the right name */
			$vlan_name = "{$vlan['if']}_vlan{$vlan['tag']}";
			foreach ($iflist as $ifname) {
				if ($config['interfaces'][$ifname]['if'] == "vlan{$i}") {
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
		array ('name' => 'ICMP',
			'type' => 'icmp',
			'descr' => 'ICMP',
			'options' => '',
		),
		array ('name' => 'TCP',
			'type' => 'tcp',
			'descr' => 'Generic TCP',
			'options' => '',
		),
		array ('name' => 'HTTP',
			'type' => 'http',
			'descr' => 'Generic HTTP',
			'options' =>
			array ('path' => '/',
				'host' => '',
				'code' => '200',
			),
		),
		array ('name' => 'HTTPS',
			'type' => 'https',
			'descr' => 'Generic HTTPS',
			'options' =>
			array ('path' => '/',
				'host' => '',
				'code' => '200',
			),
		),
		array ('name' => 'SMTP',
			'type' => 'send',
			'descr' => 'Generic SMTP',
			'options' =>
			array ('send' => '',
				'expect' => '220 *',
			),
		),
	);
	/* Upgrade load balancer from slb to relayd */
	if (is_array($config['load_balancer']['virtual_server']) && count($config['load_balancer']['virtual_server'])) {
		$vs_a = &$config['load_balancer']['virtual_server'];
		init_config_arr(array('load_balancer', 'lbpool'));
		$pool_a = &$config['load_balancer']['lbpool'];
		$pools = array();
		/* Index pools by name */
		if (is_array($pool_a)) {
			for ($i = 0; isset($pool_a[$i]); $i++) {
				if ($pool_a[$i]['type'] == "server") {
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
				if (is_array($vs_a[$i]['pool'])) {
					$vs_a[$i]['pool'] = $vs_a[$i]['pool'][0];
				}
				$pool['port'] = $pools[$vs_a[$i]['pool']]['port'];
				$pool['servers'] = array();
				$pool['servers'][] = $vs_a[$i]['sitedown'];
				$pool['monitor'] = $pools[$vs_a[$i]['pool']]['monitor'];
				$pool_a[] = $pool;
				$vs_a[$i]['sitedown'] = $pool['name'];
			}
		}
	}
	if (count($config['load_balancer']) == 0) {
		unset($config['load_balancer']);
	}
}


function upgrade_046_to_047() {
	global $config;
	/* Upgrade IPsec from tunnel to phase1/phase2 */

	if (is_array($config['ipsec']['tunnel'])) {

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

			if (!isset($ph1ent)) {

				/* build new phase1 entry */

				$ph1ent = array();

				$ph1ent['ikeid'] = ++$ikeid;

				if (isset($tunnel['disabled'])) {
					$ph1ent['disabled'] = $tunnel['disabled'];
				}

				/* convert to the new vip[$vhid] name */
				if (preg_match("/^carp/", $tunnel['interface'])) {
					$carpid = str_replace("carp", "", $tunnel['interface']);
					$tunnel['interface'] = "vip" . $config['virtualip']['vip'][$carpid]['vhid'];
				}
				$ph1ent['interface'] = $tunnel['interface'];
				$ph1ent['remote-gateway'] = $tunnel['remote-gateway'];
				$ph1ent['descr'] = $tunnel['descr'];

				$ph1ent['mode'] = $tunnel['p1']['mode'];

				if (isset($tunnel['p1']['myident']['myaddress'])) {
					$ph1ent['myid_type'] = "myaddress";
				}
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
						$ph1alg = array('name' => 'des');
						break;
					case "3des":
						$ph1alg = array('name' => '3des');
						break;
					case "blowfish":
						$ph1alg = array('name' => 'blowfish', 'keylen' => '128');
						break;
					case "cast128":
						$ph1alg = array('name' => 'cast128');
						break;
					case "rijndael":
						$ph1alg = array('name' => 'aes', 'keylen' => '128');
						break;
					case "rijndael 256":
					case "aes 256":
						$ph1alg = array('name' => 'aes', 'keylen' => '256');
						break;
				}

				$ph1ent['encryption-algorithm'] = $ph1alg;
				$ph1ent['hash-algorithm'] = $tunnel['p1']['hash-algorithm'];
				$ph1ent['dhgroup'] = $tunnel['p1']['dhgroup'];
				$ph1ent['lifetime'] = $tunnel['p1']['lifetime'];
				$ph1ent['authentication_method'] = $tunnel['p1']['authentication_method'];

				if (isset($tunnel['p1']['pre-shared-key'])) {
					$ph1ent['pre-shared-key'] = $tunnel['p1']['pre-shared-key'];
				}
				if (isset($tunnel['p1']['cert'])) {
					$ph1ent['cert'] = $tunnel['p1']['cert'];
				}
				if (isset($tunnel['p1']['peercert'])) {
					$ph1ent['peercert'] = $tunnel['p1']['peercert'];
				}
				if (isset($tunnel['p1']['private-key'])) {
					$ph1ent['private-key'] = $tunnel['p1']['private-key'];
				}

				$ph1ent['nat_traversal'] = "on";
				$ph1ent['dpd_enable'] = 1;
				$ph1ent['dpd_delay'] = 10;
				$ph1ent['dpd_maxfail'] = 5;

				$a_phase1[] = $ph1ent;
			}

			/* build new phase2 entry */

			$ph2ent = array();

			$ph2ent['ikeid'] = $ph1ent['ikeid'];

			if (isset($tunnel['disabled'])) {
				$ph1ent['disabled'] = $tunnel['disabled'];
			}

			$ph2ent['descr'] = sprintf(gettext("phase2 for %s"), $tunnel['descr']);

			$type = "lan";
			if ($tunnel['local-subnet']['network']) {
				$type = $tunnel['local-subnet']['network'];
			}
			if ($tunnel['local-subnet']['address']) {
				list($address, $netbits) = explode("/", $tunnel['local-subnet']['address']);
				if (is_null($netbits)) {
					$type = "address";
				} else {
					$type = "network";
				}
			}

			switch ($type) {
				case "address":
					$ph2ent['localid'] = array('type' => $type, 'address' => $address);
					break;
				case "network":
					$ph2ent['localid'] = array('type' => $type, 'address' => $address, 'netbits' => $netbits);
					break;
				default:
					$ph2ent['localid'] = array('type' => $type);
					break;
			}

			list($address, $netbits) = explode("/", $tunnel['remote-subnet']);
			$ph2ent['remoteid'] = array('type' => 'network', 'address' => $address, 'netbits' => $netbits);

			$ph2ent['protocol'] = $tunnel['p2']['protocol'];

			$aes_count = 0;
			foreach ($tunnel['p2']['encryption-algorithm-option'] as $tunalg) {
				$aes_found = false;
				switch ($tunalg) {
					case "des":
						$ph2alg = array('name' => 'des');
						break;
					case "3des":
						$ph2alg = array('name' => '3des');
						break;
					case "blowfish":
						$ph2alg = array('name' => 'blowfish', 'keylen' => 'auto');
						break;
					case "cast128":
						$ph2alg = array('name' => 'cast128');
						break;
					case "rijndael":
					case "rijndael 256":
					case "aes 256":
						$ph2alg = array('name' => 'aes', 'keylen' => 'auto');
						$aes_found = true;
						$aes_count++;
						break;
				}

				if (!$aes_found || ($aes_count < 2)) {
					$ph2ent['encryption-algorithm-option'][] = $ph2alg;
				}
			}

			$ph2ent['hash-algorithm-option'] = $tunnel['p2']['hash-algorithm-option'];
			$ph2ent['pfsgroup'] = $tunnel['p2']['pfsgroup'];
			$ph2ent['lifetime'] = $tunnel['p2']['lifetime'];

			if (isset($tunnel['pinghost']['pinghost'])) {
				$ph2ent['pinghost'] = $tunnel['pinghost'];
			}

			$a_phase2[] = $ph2ent;
		}

		unset($config['ipsec']['tunnel']);
		$config['ipsec']['phase1'] = $a_phase1;
		$config['ipsec']['phase2'] = $a_phase2;
	}

	/* Upgrade Mobile IPsec */
	if (isset($config['ipsec']['mobileclients']) &&
	    is_array($config['ipsec']['mobileclients']) &&
	    is_array($config['ipsec']['mobileclients']['p1']) &&
	    is_array($config['ipsec']['mobileclients']['p2'])) {

		if (isset($config['ipsec']['mobileclients']['enable'])) {
			$config['ipsec']['client']['enable'] = true;
			$config['ipsec']['client']['user_source'] = 'system';
			$config['ipsec']['client']['group_source'] = 'system';
		}

		$mobilecfg = $config['ipsec']['mobileclients'];

		$ph1ent = array();
		$ph1ent['ikeid'] = ++$ikeid;

		if (!isset($mobilecfg['enable'])) {
			$ph1ent['disabled'] = true;
		}

		/* Assume WAN since mobile tunnels couldn't be on a separate interface on 1.2.x */
		$ph1ent['interface'] = 'wan';
		$ph1ent['descr'] = "Mobile Clients (upgraded)";
		$ph1ent['mode'] = $mobilecfg['p1']['mode'];

		if (isset($mobilecfg['p1']['myident']['myaddress'])) {
			$ph1ent['myid_type'] = "myaddress";
		}
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
				$ph1alg = array('name' => 'des');
				break;
			case "3des":
				$ph1alg = array('name' => '3des');
				break;
			case "blowfish":
				$ph1alg = array('name' => 'blowfish', 'keylen' => '128');
				break;
			case "cast128":
				$ph1alg = array('name' => 'cast128');
				break;
			case "rijndael":
				$ph1alg = array('name' => 'aes', 'keylen' => '128');
				break;
			case "rijndael 256":
			case "aes 256":
				$ph1alg = array('name' => 'aes', 'keylen' => '256');
				break;
		}

		$ph1ent['encryption-algorithm'] = $ph1alg;
		$ph1ent['hash-algorithm'] = $mobilecfg['p1']['hash-algorithm'];
		$ph1ent['dhgroup'] = $mobilecfg['p1']['dhgroup'];
		$ph1ent['lifetime'] = $mobilecfg['p1']['lifetime'];
		$ph1ent['authentication_method'] = $mobilecfg['p1']['authentication_method'];

		if (isset($mobilecfg['p1']['cert'])) {
			$ph1ent['cert'] = $mobilecfg['p1']['cert'];
		}
		if (isset($mobilecfg['p1']['peercert'])) {
			$ph1ent['peercert'] = $mobilecfg['p1']['peercert'];
		}
		if (isset($mobilecfg['p1']['private-key'])) {
			$ph1ent['private-key'] = $mobilecfg['p1']['private-key'];
		}

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
		foreach ($mobilecfg['p2']['encryption-algorithm-option'] as $tunalg) {
			$aes_found = false;
			switch ($tunalg) {
				case "des":
					$ph2alg = array('name' => 'des');
					break;
				case "3des":
					$ph2alg = array('name' => '3des');
					break;
				case "blowfish":
					$ph2alg = array('name' => 'blowfish', 'keylen' => 'auto');
					break;
				case "cast128":
					$ph2alg = array('name' => 'cast128');
					break;
				case "rijndael":
				case "rijndael 256":
				case "aes 256":
					$ph2alg = array('name' => 'aes', 'keylen' => 'auto');
					$aes_found = true;
					$aes_count++;
					break;
			}

			if (!$aes_found || ($aes_count < 2)) {
				$ph2ent['encryption-algorithm-option'][] = $ph2alg;
			}
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
		if (isset($config['dyndns'][0]['host'])) {
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
		if (!$pconfig['ttl']) {
			$pconfig['ttl'] = 60;
		}
		if (!$pconfig['keytype']) {
			$pconfig['keytype'] = "zone";
		}
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
		$config['interfaces']['wan']['ondemand'] = isset($pconfig['ondemand']);
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

	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	if (!is_array($config['system']['group'])) {
		$config['system']['group'] = array();
	}

	/* work around broken uid assignments */
	$config['system']['nextuid'] = 2000;
	foreach ($config['system']['user'] as & $user) {
		if (isset($user['uid']) && !$user['uid']) {
			continue;
		}
		$user['uid'] = $config['system']['nextuid']++;
	}

	/* work around broken gid assignments */
	$config['system']['nextgid'] = 2000;
	foreach ($config['system']['group'] as & $group) {
		if ($group['name'] == $g['admin_group']) {
			$group['gid'] = 1999;
		} else {
			$group['gid'] = $config['system']['nextgid']++;
		}
	}

	/* build group membership information */
	foreach ($config['system']['group'] as & $group) {
		$group['member'] = array();
		foreach ($config['system']['user'] as & $user) {
			$groupnames = explode(",", $user['groupname']);
			if (in_array($group['name'], $groupnames)) {
				$group['member'][] = $user['uid'];
			}
		}
	}

	/* reset user group information */
	foreach ($config['system']['user'] as & $user) {
		unset($user['groupname']);
		$all['member'][] = $user['uid'];
	}

	/* reset group scope information */
	foreach ($config['system']['group'] as & $group) {
		if ($group['name'] != $g['admin_group']) {
			$group['scope'] = "user";
		}
	}

	/* insert new all group */
	$groups = Array();
	$groups[] = $all;
	$groups = array_merge($config['system']['group'], $groups);
	$config['system']['group'] = $groups;
}


function upgrade_049_to_050() {
	global $config;

	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	/* update user privileges */
	foreach ($config['system']['user'] as & $user) {
		$privs = array();
		if (!is_array($user['priv'])) {
			unset($user['priv']);
			continue;
		}
		foreach ($user['priv'] as $priv) {
			switch ($priv['id']) {
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
			if ($priv) {
				$privs[] = $priv;
			}
		}
		unset($group['pages']);
		$group['priv'] = $privs;
	}

	/* sync all local account information */
	local_reset_accounts();
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

	if (isset($config['bridge'])) {
		unset($config['bridge']);
	}

	$convert_bridges = false;
	foreach ($config['interfaces'] as $intf) {
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
	if (!is_array($config['ca'])) {
		$config['ca'] = array();
	}
	if (!is_array($config['cert'])) {
		$config['cert'] = array();
	}

	$vpnid = 1;

	/* openvpn server configurations */
	if (is_array($config['installedpackages']['openvpnserver'])) {
		$config['openvpn']['openvpn-server'] = array();

		$index = 1;
		foreach ($config['installedpackages']['openvpnserver']['config'] as $server) {

			if (!is_array($server)) {
				continue;
			}

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
					if (!is_array($config['crl'])) {
						$config['crl'] = array();
					}
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
				if ($server['nopool']) {
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
			if (!$server['interface']) {
				$server['interface'] = 'any';
			}
			$server['tunnel_network'] = $server['addresspool'];
			unset($server['addresspool']);
			if (isset($server['use_lzo']) && ($server['use_lzo'] == "on")) {
				$server['compression'] = "on";
				unset($server['use_lzo']);
			}
			if ($server['nopool']) {
				$server['pool_enable'] = false;
			} else {
				$server['pool_enable'] = "yes";
			}
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

			if ($server['dhcp_nbtdisable']) {
				$server['netbios_enable'] = false;
			} else {
				$server['netbios_enable'] = "yes";
			}
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

			if (!empty($server['disable'])) {
				$server['disable'] = true;
			} else {
				unset($server['disable']);
			}

			/* allocate vpnid */
			$server['vpnid'] = $vpnid++;

			if (!empty($server['custom_options'])) {
				$cstmopts = array();
				$tmpcstmopts = explode(";", $server['custom_options']);
				$assigned_if = "";
				$tmpstr = "";
				foreach ($tmpcstmopts as $tmpcstmopt) {
					$tmpstr = str_replace(" ", "", $tmpcstmopt);
					if (substr($tmpstr, 0, 6) == "devtun") {
						$assigned_if = substr($tmpstr, 3);
						continue;
					} else if (substr($tmpstr, 0, 5) == "local") {
						$localip = substr($tmpstr, 5);
						$server['ipaddr'] = str_replace("\n", "", $localip);
					} else {
						$cstmopts[] = $tmpcstmopt;
					}
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
		foreach ($config['installedpackages']['openvpnclient']['config'] as $client) {

			if (!is_array($client)) {
				continue;
			}

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
			if ($client['auth_method'] == 'pki') {
				$client['mode'] = "p2p_tls";
			} else {
				$client['mode'] = "p2p_shared_key";
			}
			unset($client['auth_method']);

			/* modify configuration values */
			if (!$client['interface']) {
				$client['interface'] = 'wan';
			}
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
					if (substr($tmpstr, 0, 6) == "devtun") {
						$assigned_if = substr($tmpstr, 3);
						continue;
					} else if (substr($tmpstr, 0, 5) == "local") {
						$localip = substr($tmpstr, 5);
						$client['ipaddr'] = str_replace("\n", "", $localip);
					} else {
						$cstmopts[] = $tmpcstmopt;
					}
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

			if (!empty($client['disable'])) {
				$client['disable'] = true;
			} else {
				unset($client['disable']);
			}

			$config['openvpn']['openvpn-client'][] = $client;
		}

		unset($config['installedpackages']['openvpnclient']);
	}

	/* openvpn client specific configurations */
	if (is_array($config['installedpackages']['openvpncsc'])) {
		$config['openvpn']['openvpn-csc'] = array();

		foreach ($config['installedpackages']['openvpncsc']['config'] as $csc) {

			if (!is_array($csc)) {
				continue;
			}

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

			if ($csc['dhcp_nbtdisable']) {
				$csc['netbios_enable'] = false;
			} else {
				$csc['netbios_enable'] = "yes";
			}
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

			if (!empty($csc['disable'])) {
				$csc['disable'] = true;
			} else {
				unset($csc['disable']);
			}

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
	if (!is_array($config['installedpackages'])) {
		$config['installedpackages'] = array();
	}
	if (!is_array($config['installedpackages']['carp'])) {
		$config['installedpackages']['carp'] = array();
	}

}


function upgrade_052_to_053() {
	global $config;
	if (!is_array($config['ca'])) {
		$config['ca'] = array();
	}
	if (!is_array($config['cert'])) {
		$config['cert'] = array();
	}

	/* migrate advanced admin page webui ssl to certificate manager */
	if ($config['system']['webgui']['certificate'] &&
	    $config['system']['webgui']['private-key']) {

		/* create cert entry */
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = "webConfigurator SSL/TLS Certificate";
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
	if (is_array($config['load_balancer']['lbpool'])) {
		$lbpool_arr = $config['load_balancer']['lbpool'];
		$lbpool_srv_arr = array();
		$gateway_group_arr = array();
		$gateways = return_gateways_array();
		$group_name_changes = array();
		init_config_arr(array('gateways', 'gateway_item'));
		$a_gateways = &$config['gateways']['gateway_item'];
		foreach ($lbpool_arr as $lbpool) {
			if ($lbpool['type'] == "gateway") {
				// Gateway Groups have to have valid names in pf, old lb pools did not. Clean them up.
				$group_name = preg_replace("/[^A-Za-z0-9]/", "", $lbpool['name']);
				// If we made and changes, check for collisions and note the change.
				if ($group_name != $lbpool['name']) {
					// Make sure the name isn't already in use.
					foreach ($gateway_group_arr as $gwg) {
						// If the name is in use, add some random bits to avoid collision.
						if ($gwg['name'] == $group_name) {
							$group_name .= uniqid();
						}
					}
					$group_name_changes[$lbpool['name']] = $group_name;
				}
				$gateway_group['name'] = $group_name;
				$gateway_group['descr'] = $lbpool['descr'];
				$gateway_group['trigger'] = "down";
				$gateway_group['item'] = array();
				$i = 0;
				foreach ($lbpool['servers'] as $member) {
					$split = explode("|", $member);
					$interface = $split[0];
					$monitor = $split[1];
					/* on static upgraded configuration we automatically prepend GW_ */
					$static_name = "GW_" . strtoupper($interface);
					if (is_ipaddr($monitor)) {
						foreach ($a_gateways as & $gw) {
							if ($gw['name'] == $static_name) {
								$gw['monitor'] = $monitor;
							}
						}
					}

					/* on failover increment tier. Else always assign 1 */
					if ($lbpool['behaviour'] == "failover") {
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
		if (empty($config['load_balancer'])) {
			unset($config['load_balancer']);
		} else {
			if (isset($config['load_balancer']['lbpool'])) {
				unset($config['load_balancer']['lbpool']);
			}
		}
	} else {
		$config['load_balancer']['lbpool'] = $lbpool_srv_arr;
	}
	// Only set the gateway group array if we converted any
	if (count($gateway_group_arr) != 0) {
		$config['gateways']['gateway_group'] = $gateway_group_arr;
		// Update any rules that had a gateway change, if any.
		if (count($group_name_changes) > 0) {
			foreach ($config['filter']['rule'] as & $rule) {
				if (!empty($rule["gateway"]) && array_key_exists($rule["gateway"], $group_name_changes)) {
					$rule["gateway"] = $group_name_changes[$rule["gateway"]];
				}
			}
		}
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

	$rrdinterval = 60;
	$valid = $rrdinterval * 2;

	/* Assume GigE for now */
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
	if (!file_exists($rrddbpath)) {
		@mkdir($rrddbpath);
	}
	chdir($rrddbpath);
	$databases = glob("*-quality.rrd");
	rsort($databases);
	foreach ($databases as $database) {
		$xmldump = "{$database}.old.xml";
		$xmldumpnew = "{$database}.new.xml";

		if (platform_booting()) {
			echo "Migrate RRD database {$database} to new format for IPv6 \n";
		}
		mwexec("$rrdtool tune {$rrddbpath}{$database} -r roundtrip:delay 2>&1");

		dump_rrd_to_xml("{$rrddbpath}/{$database}", "{$g['tmp_path']}/{$xmldump}");
		$rrdold = xml2array(file_get_contents("{$g['tmp_path']}/{$xmldump}"), 1, "tag");
		$rrdold = $rrdold['rrd'];

		$i = 0;
		foreach ($rrdold['rra'] as $rra) {
			$l = 0;
			foreach ($rra['database']['row'] as $row) {
				$vnew = divide_delay($row['v'][1]);
				$rrdold['rra'][$i]['database']['row'][$l]['v'][1] = $vnew;
				$l++;
			}
			$i++;
		}

		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", dump_xml_config_raw($rrdold, "rrd"));
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");

		unset($rrdold);
		@unlink("{$g['tmp_path']}/{$xmldump}");
		@unlink("{$g['tmp_path']}/{$xmldumpnew}");
	}

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach ($databases as $database) {
		$databasetmp = "{$database}.tmp";
		$xmldump = "{$database}.old.xml";
		$xmldumptmp = "{$database}.tmp.xml";
		$xmldumpnew = "{$database}.new.xml";

		if (platform_booting()) {
			echo "Migrate RRD database {$database} to new format \n";
		}
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
			if (trim($rra['cf']) == "MAX") {
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
		@unlink("{$g['tmp_path']}/{$xmldump}");
		@unlink("{$g['tmp_path']}/{$xmldumpnew}");
	}
	if (!platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	unlink_if_exists("{$g['vardb_path']}/rrd/*.xml");
	if (platform_booting()) {
		echo "Updating configuration...";
	}
}


function upgrade_055_to_056() {
	global $config;

	if (!is_array($config['ca'])) {
		$config['ca'] = array();
	}
	if (!is_array($config['cert'])) {
		$config['cert'] = array();
	}

	/* migrate ipsec ca's to cert manager */
	if (is_array($config['ipsec']['cacert'])) {
		foreach ($config['ipsec']['cacert'] as & $cacert) {
			$ca = array();
			$ca['refid'] = uniqid();
			if (is_array($cacert['cert'])) {
				$ca['crt'] = $cacert['cert'][0];
			} else {
				$ca['crt'] = $cacert['cert'];
			}
			$ca['descr'] = $cacert['ident'];
			$config['ca'][] = $ca;
		}
		unset($config['ipsec']['cacert']);
	}

	/* migrate phase1 certificates to cert manager */
	if (is_array($config['ipsec']['phase1'])) {
		foreach ($config['ipsec']['phase1'] as & $ph1ent) {
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = "IPsec Peer {$ph1ent['remote-gateway']} Certificate";
			if (is_array($ph1ent['cert'])) {
				$cert['crt'] = $ph1ent['cert'][0];
			} else {
				$cert['crt'] = $ph1ent['cert'];
			}
			$cert['prv'] = $ph1ent['private-key'];
			$config['cert'][] = $cert;
			$ph1ent['certref'] = $cert['refid'];
			if ($ph1ent['cert']) {
				unset($ph1ent['cert']);
			}
			if ($ph1ent['private-key']) {
				unset($ph1ent['private-key']);
			}
			if ($ph1ent['peercert']) {
				unset($ph1ent['peercert']);
			}
		}
	}
}


function upgrade_056_to_057() {
	global $config;

	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	/* migrate captivate portal to user manager */
	if (is_array($config['captiveportal']['user'])) {
		foreach ($config['captiveportal']['user'] as $user) {
			// avoid user conflicts
			$found = false;
			foreach ($config['system']['user'] as $userent) {
				if ($userent['name'] == $user['name']) {
					$found = true;
					break;
				}
			}
			if ($found) {
				continue;
			}
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
	if (is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase2'] as & $ph2ent) {
			$ph2ent['mode'] = 'tunnel';
		}
	}
}

function upgrade_058_to_059() {
	global $config;

	if (is_array($config['schedules']['schedule'])) {
		foreach ($config['schedules']['schedule'] as & $schedl) {
			$schedl['schedlabel'] = uniqid();
		}
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
			if ($issuer <> $subject) {
				$issuer_crt =& lookup_ca_by_subject($issuer);
				if ($issuer_crt) {
					$ca['caref'] = $issuer_crt['refid'];
				}
			}
		}

		/* Locate issuer for all certificates */
		if (is_array($config['cert'])) {
			foreach ($config['cert'] as & $cert) {
				$subject = cert_get_subject($cert['crt']);
				$issuer = cert_get_issuer($cert['crt']);
				if ($issuer <> $subject) {
					$issuer_crt =& lookup_ca_by_subject($issuer);
					if ($issuer_crt) {
						$cert['caref'] = $issuer_crt['refid'];
					}
				}
			}
		}
	}
}

function upgrade_060_to_061() {
	global $config;

	if (is_array($config['interfaces']['wan'])) {
		$config['interfaces']['wan']['enable'] = true;
	}
	if (is_array($config['interfaces']['lan'])) {
		$config['interfaces']['lan']['enable'] = true;
	}

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
	// Not supported in 2.3+

}

function upgrade_063_to_064() {
	global $config;
	$j = 0;
	init_config_arr(array('ppps', 'ppp'));
	init_config_arr(array('interfaces'));
	$ifcfg = &$config['interfaces'];

	if (count($config['ppps']['ppp'])) {
		foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
			$config['ppps']['ppp'][$pppid]['if'] = "ppp".$j;
			$config['ppps']['ppp'][$pppid]['ptpid'] = $j;
			$j++;
			if (isset($ppp['port'])) {
				$config['ppps']['ppp'][$pppid]['ports'] = $ppp['port'];
				unset($config['ppps']['ppp'][$pppid]['port']);
			}
			if (!isset($ppp['type'])) {
				$config['ppps']['ppp'][$pppid]['type'] = "ppp";
			}
			if (isset($ppp['defaultgw'])) {
				unset($config['ppps']['ppp'][$pppid]['defaultgw']);
			}
		}
	}

	$a_ppps = &$config['ppps']['ppp'];

	foreach ($ifcfg as $ifname => $ifinfo) {
		$ppp = array();
		// For pppoe conversion
		if ($ifinfo['ipaddr'] == "pppoe" || $ifinfo['ipaddr'] == "pptp") {
			if (isset($ifinfo['ptpid'])) {
				continue;
			}
			$ppp['ptpid'] = $j;
			$ppp['type'] = $ifinfo['ipaddr'];
			$ppp['if'] = $ifinfo['ipaddr'].$j;
			$ppp['ports'] = $ifinfo['if'];
			if ($ifinfo['ipaddr'] == "pppoe") {
				$ppp['username'] = $ifinfo['pppoe_username'];
				$ppp['password'] = base64_encode($ifinfo['pppoe_password']);
			}
			if ($ifinfo['ipaddr'] == "pptp") {
				$ppp['username'] = $ifinfo['pptp_username'];
				$ppp['password'] = base64_encode($ifinfo['pptp_password']);
			}

			if (isset($ifinfo['provider'])) {
				$ppp['provider'] = $ifinfo['provider'];
			}
			if (isset($ifinfo['ondemand'])) {
				$ppp['ondemand'] = true;
			}
			if (isset($ifinfo['timeout'])) {
				$ppp['idletimeout'] = $ifinfo['timeout'];
			}
			if (isset($ifinfo['pppoe']['pppoe-reset-type'])) {
				$ppp['pppoe-reset-type'] = $ifinfo['pppoe']['pppoe-reset-type'];
				if (is_array($config['cron']['item'])) {
					for ($i = 0; $i < count($config['cron']['item']); $i++) {
						$item = $config['cron']['item'][$i];
						if (strpos($item['command'], "/conf/pppoe{$ifname}restart") !== false) {
							$config['cron']['item'][$i]['command'] = "/var/etc/pppoe_restart_" . $ppp['if'];
						}
					}
				}
			}
			if (isset($ifinfo['local'])) {
				$ppp['localip'] = $ifinfo['local'];
			}
			if (isset($ifinfo['subnet'])) {
				$ppp['subnet'] = $ifinfo['subnet'];
			}
			if (isset($ifinfo['remote'])) {
				$ppp['gateway'] = $ifinfo['remote'];
			}

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

	init_config_arr(array('dhcrelay'));
	$dhcrelaycfg = &$config['dhcrelay'];

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
		if ($foundifs == true) {
			$dhcrelaycfg['interface'] = implode(",", $dhcrelayifs);
		}
	}
}

function upgrade_066_to_067() {
	global $config;
	if (isset($config['system']['ca'])) {
		$config['ca'] = $config['system']['ca'];
		unset($config['system']['ca']);
	}
	if (isset($config['system']['cert'])) {
		$config['cert'] = $config['system']['cert'];
		unset($config['system']['cert']);
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
				if ($user['ip']) {
					$usr .= ":{$user['ip']}";
				}
				$username[] = $usr;
			}
			$config['pppoes']['pppoe'][0]['username'] = implode(" ", $username);
		}
		unset($config['pppoe']);
	}
}

function upgrade_068_to_069() {
	global $config;
	if (!is_array($config['system']['user'])) {
		return;
	}
	foreach ($config['system']['user'] as & $user) {
		if (!is_array($user['cert'])) {
			continue;
		}
		$rids = array();
		foreach ($user['cert'] as $id => $cert) {
			if (!isset($cert['descr'])) {
				continue;
			}
			$tcert = $cert;
			// Make sure each cert gets a refid
			if (!isset($tcert['refid'])) {
				$tcert['refid'] = uniqid();
			}
			// Keep the cert references for this user
			$rids[] = $tcert['refid'];
			$config['cert'][] = $tcert;
		}
		// Replace user certs with cert references instead.
		if (count($rids) > 0) {
			$user['cert'] = $rids;
		}
	}
}

function upgrade_069_to_070() {
	global $config;

	/* Convert NAT 1:1 rules */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $nidx => $natent) {
			if ($natent['subnet'] == 32) {
				$config['nat']['onetoone'][$nidx]['source'] = array("address" => $natent['internal']);
			} else {
				$config['nat']['onetoone'][$nidx]['source'] = array("address" => $natent['internal'] . "/" . $natent['subnet']);
			}

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
		foreach ($config['cron']['item'] as $idx => $cronitem) {
			if (stristr($cronitem['command'], "checkreload.sh")) {
				unset($config['cron']['item'][$idx]);
				break;
			}
		}
	}
}

function rename_field(& $section, $oldname, $newname) {
	if (is_array($section)) {
		foreach ($section as & $item) {
			if (is_array($item) && !empty($item[$oldname])) {
				$item[$newname] = $item[$oldname];
			}
			if (is_array($item) && isset($item[$oldname])) {
				unset($item[$oldname]);
			}
		}
	}
}

function upgrade_071_to_072() {
	global $config;
	if (is_array($config['sysctl']) && is_array($config['sysctl']['item'])) {
		rename_field($config['sysctl']['item'], 'desc', 'descr');
	}
}

function upgrade_072_to_073() {
	global $config;
	if (!is_array($config['load_balancer'])) {
		return;
	}
	if (is_array($config['load_balancer']['monitor_type'])) {
		rename_field($config['load_balancer']['monitor_type'], 'desc', 'descr');
	}
	if (is_array($config['load_balancer']['lbpool'])) {
		rename_field($config['load_balancer']['lbpool'], 'desc', 'descr');
	}
	if (is_array($config['load_balancer']['lbaction'])) {
		rename_field($config['load_balancer']['lbaction'], 'desc', 'descr');
	}
	if (is_array($config['load_balancer']['lbprotocol'])) {
		rename_field($config['load_balancer']['lbprotocol'], 'desc', 'descr');
	}
	if (is_array($config['load_balancer']['virtual_server'])) {
		rename_field($config['load_balancer']['virtual_server'], 'desc', 'descr');
	}
}

function upgrade_073_to_074() {
	global $config;
	rename_field($config['system']['user'], 'fullname', 'descr');
}

function upgrade_074_to_075() {
	global $config;
	if (is_array($config['ca'])) {
		rename_field($config['ca'], 'name', 'descr');
	}
	if (is_array($config['cert'])) {
		rename_field($config['cert'], 'name', 'descr');
	}
	if (is_array($config['crl'])) {
		rename_field($config['crl'], 'name', 'descr');
	}
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
	foreach ($config['filter']['rule'] as & $rule) {
		if (isset($rule['protocol']) && !empty($rule['protocol'])) {
			$rule['protocol'] = strtolower($rule['protocol']);
		}
	}
}

function upgrade_077_to_078() {
	global $config;
	if (is_array($config['pptpd']) && is_array($config['pptpd']['radius']) &&
	    !is_array($config['pptpd']['radius']['server'])) {
		$radarr = array();
		$radsvr = array();
		$radsvr['ip'] = $config['pptpd']['radius']['server'];
		$radsvr['secret'] = $config['pptpd']['radius']['secret'];
		$radsvr['port'] = 1812;
		$radsvr['acctport'] = 1813;
		$radsvr['enable'] = isset($config['pptpd']['radius']['enable']);
		$radarr['accounting'] = isset($config['pptpd']['radius']['accounting']);
		if ($radarr['accounting']) {
			$radarr['acct_update'] = $radsvr['ip'];
		}
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

	/* Upgrade config in 1.2.3 specifying a username other than admin for syncing. */
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
	if (is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as $gw) {
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

	$rrdinterval = 60;
	$valid = $rrdinterval * 2;

	/* Assume GigE for now */
	$downstream = 125000000;
	$upstream = 125000000;

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach ($databases as $database) {
		$xmldump = "{$database}.old.xml";
		$xmldumpnew = "{$database}.new.xml";

		if (platform_booting()) {
			echo "Migrate RRD database {$database} to new format for IPv6.\n";
		}

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
		foreach ($ds_arr as $ds) {
			$xml = preg_replace("/$ds_search/s", "$ds{$ds_search}", $xml);
		}
		foreach ($cdp_arr as $cdp) {
			$xml = preg_replace("/$cdp_search/s", "$cdp{$cdp_replace}", $xml);
		}
		foreach ($ds_arr as $ds) {
			$xml = preg_replace("/$value_search/s", "$value{$value_replace}", $xml);
		}

		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", $xml);
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");
		unset($xml);
		# Default /tmp tmpfs is ~40mb, do not leave temp files around
		unlink_if_exists("{$g['tmp_path']}/{$xmldump}");
		unlink_if_exists("{$g['tmp_path']}/{$xmldumpnew}");
	}
	if (!platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	if (platform_booting()) {
		echo "Updating configuration...";
	}
	foreach ($config['filter']['rule'] as & $rule) {
		if (isset($rule['protocol']) && !empty($rule['protocol'])) {
			$rule['protocol'] = strtolower($rule['protocol']);
		}
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
		if ($config['captiveportal']['cpzone']['auth_method'] == "radius") {
			$config['captiveportal']['cpzone']['radius_protocol'] = "PAP";
		}
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
		if (empty($config['installedpackages']['carpsettings']) && isset($config['installedpackages']['carpsettings'])) {
			unset($config['installedpackages']['carpsettings']);
		}
		if (empty($config['installedpackages']) && isset($config['installedpackages'])) {
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
	foreach ($gateways as $name => $gw) {
		if (isset($gw['dynamic'])) {
			$oldname = strtoupper($config['interfaces'][$gw['friendlyiface']]['descr']);
			$oldnames[$oldname] = $name;
		} else {
			$oldnames[$name] = $name;
		}
	}

	/* process the old array */
	if (is_array($config['gateways']['gateway_group'])) {
		$group_array_new = array();
		foreach ($config['gateways']['gateway_group'] as $name => $group) {
			if (is_array($group['item'])) {
				$newlist = array();
				foreach ($group['item'] as $entry) {
					$elements = explode("|", $entry);
					if ($oldnames[$elements[0]] <> "") {
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
	foreach ($oldnames as $old => $new) {
		if (is_readable("{$rrddbpath}/{$old}-quality.rrd")) {
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
			if ($vip['mode'] != "carp") {
				continue;
			}
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

	if (!is_array($config['dnshaper']) || !is_array($config['dnshaper']['queue'])) {
		return;
	}

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

	if (!is_array($config['filter']) || !is_array($config['filter']['rule'])) {
		return;
	}

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
			if (!empty($dn_list[$rule['dnpipe']])) {
				$config['filter']['rule'][$idx]['dnpipe'] = $dn_list[$rule['dnpipe']];
			}
		}
		if (!empty($rule['pdnpipe'])) {
			if (!empty($dn_list[$rule['pdnpipe']])) {
				$config['filter']['rule'][$idx]['pdnpipe'] = $dn_list[$rule['pdnpipe']];
			}
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
	if (!is_array($config['ca'])) {
		$config['ca'] = array();
	}
	if (!is_array($config['cert'])) {
		$config['cert'] = array();
	}

	/* migrate captive portal ssl to certificate manager */
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

	if (is_array($config['nat']['advancedoutbound']['rule'])) {
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

	foreach ($suffixes as $suffix) {
		if (file_exists("{$g['vardb_path']}/rrd/captiveportal-{$suffix}.rrd")) {
			rename("{$g['vardb_path']}/rrd/captiveportal-{$suffix}.rrd",
				"{$g['vardb_path']}/rrd/captiveportal-cpZone-{$suffix}.rrd");
		}
	}

	if (!platform_booting()) {
		enable_rrd_graphing();
	}
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

	if (!isset($config['interfaces']) || !is_array($config['interfaces'])) {
		return;
	}

	foreach ($config['interfaces'] as $iface => $cfg) {
		if (isset($cfg['ipaddrv6']) && ($cfg['ipaddrv6'] == "track6")) {
			if (!isset($cfg['track6-prefix-id']) || ($cfg['track6-prefix-id'] == "")) {
				$config['interfaces'][$iface]['track6-prefix-id'] = 0;
			}
		}
	}
}

function upgrade_095_to_096() {
	global $config, $g;

	$names = array("inpass", "outpass", "inblock", "outblock",
		"inpass6", "outpass6", "inblock6", "outblock6");
	$rrddbpath = "/var/db/rrd";
	$rrdtool = "/usr/local/bin/rrdtool";

	/* Assume 2*10GigE for now */
	$stream = 2500000000;

	/* build a list of traffic and packets databases */
	$databases = return_dir_as_array($rrddbpath, '/-(traffic|packets)\.rrd$/');
	rsort($databases);
	foreach ($databases as $database) {
		if (platform_booting()) {
			echo "Update RRD database {$database}.\n";
		}

		$cmd = "{$rrdtool} tune {$rrddbpath}/{$database}";
		foreach ($names as $name) {
			$cmd .= " -a {$name}:{$stream}";
		}
		mwexec("{$cmd} 2>&1");

	}
	if (!platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
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
	// no longer used (used to set kill_states)
	return;
}

function upgrade_098_to_099() {
	global $config;

	if (empty($config['dhcpd']) || !is_array($config['dhcpd'])) {
		return;
	}

	foreach ($config['dhcpd'] as & $dhcpifconf) {
		if (isset($dhcpifconf['next-server'])) {
			$dhcpifconf['nextserver'] = $dhcpifconf['next-server'];
			unset($dhcpifconf['next-server']);
		}
	}
}

function upgrade_099_to_100() {
	require_once("/etc/inc/services.inc");
	/* See #7146 for detail on why the extra parameters are needed for the time being. */
	install_cron_job("/usr/bin/nice -n20 newsyslog", false, null, null, null, null, null, null, false);
}

function upgrade_100_to_101() {
	global $config, $g;

	if (!is_array($config['voucher'])) {
		return;
	}

	foreach ($config['voucher'] as $cpzone => $cp) {
		if (!is_array($cp['roll'])) {
			continue;
		}
		foreach ($cp['roll'] as $ridx => $rcfg) {
			if (!empty($rcfg['comment'])) {
				$config['voucher'][$cpzone]['roll'][$ridx]['descr'] = $rcfg['comment'];
			}
		}
	}
}

function upgrade_101_to_102() {
	global $config, $g;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cp) {
			if (!is_array($cp['passthrumac'])) {
				continue;
			}

			foreach ($cp['passthrumac'] as $idx => $passthrumac) {
				$config['captiveportal'][$cpzone]['passthrumac'][$idx]['action'] = 'pass';
			}
		}
	}

	/* Convert OpenVPN Compression option to the new style */
	// Nothing to do if there is no OpenVPN tag
	if (isset($config['openvpn']) && is_array($config['openvpn'])) {
		if (is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as &$vpn) {
				if (!empty($vpn['compression'])) {
					$vpn['compression'] = "adaptive";
				}
			}
		}
		if (is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as &$vpn) {
				if (!empty($vpn['compression'])) {
					$vpn['compression'] = "adaptive";
				}
			}
		}
	}
}

function upgrade_102_to_103() {
	global $config;

	if (isset($config['nat']['advancedoutbound']['enable'])) {
		$config['nat']['advancedoutbound']['mode'] = "advanced";
		unset($config['nat']['advancedoutbound']['enable']);
	} else {
		$config['nat']['advancedoutbound']['mode'] = "automatic";
	}

	$config['nat']['outbound'] = $config['nat']['advancedoutbound'];

	if (isset($config['nat']['ipsecpassthru'])) {
		unset($config['nat']['ipsecpassthru']);
	}
	if (isset($config['nat']['advancedoutbound'])) {
		unset($config['nat']['advancedoutbound']);
	}
}

function upgrade_103_to_104() {
	global $config;

	$changed_privs = array(
		"page-diag-system-activity" => "page-diagnostics-system-activity",
		"page-interfacess-groups" => "page-interfaces-groups",
		"page-interfacess-lagg" => "page-interfaces-lagg",
		"page-interfacess-qinq" => "page-interfaces-qinq"
	);

	/* update user privileges */
	foreach ($config['system']['user'] as & $user) {
		if (!is_array($user['priv'])) {
			continue;
		}
		foreach ($user['priv'] as & $priv) {
			if (array_key_exists($priv, $changed_privs)) {
				$priv = $changed_privs[$priv];
			}
		}
	}

	/* update group privileges */
	foreach ($config['system']['group'] as & $group) {
		if (!is_array($group['priv'])) {
			continue;
		}
		foreach ($group['priv'] as & $priv) {
			if (array_key_exists($priv, $changed_privs)) {
				$priv = $changed_privs[$priv];
			}
		}
	}

	/* sync all local account information */
	local_reset_accounts();
}

function upgrade_104_to_105() {
	global $config;

	if (is_array($config['captiveportal'])) {
		$zoneid = 2;
		foreach ($config['captiveportal'] as $cpzone => $cpcfg) {
			if (empty($cpcfg['zoneid'])) {
				$config['captiveportal'][$cpzone]['zoneid'] = $zoneid;
				$zoneid += 2;
			} else if ($cpcfg['zoneid'] > 4000) {
				$config['captiveportal'][$cpzone]['zoneid'] = $zoneid;
				$zoneid += 2;
			}
		}
	}
}

function upgrade_105_to_106() {
	/* NOTE: This upgrade code was reverted. See redmine ticket #3967 and
	   https://github.com/pfsense/pfsense/commit/6f55af1c25f5232ffe905a90f5f97aad4c87bdfa */
}

function upgrade_106_to_107() {
	global $config;

	if (is_array($config['filter']) && is_array($config['filter']['rule'])) {
		$tracker = (int)microtime(true);
		foreach ($config['filter']['rule'] as $ridx => $rule) {
			if (empty($rule['tracker'])) {
				$config['filter']['rule'][$ridx]['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $ridx);
	}
	if (is_array($config['nat']) && is_array($config['nat']['rule'])) {
		$tracker = (int)microtime(true);
		foreach ($config['nat']['rule'] as $ridx => $rule) {
			if (empty($rule['tracker'])) {
				$config['nat']['rule'][$ridx]['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $ridx);
	}
}

function upgrade_107_to_108() {
	global $config;

	if (isset($config['system']['webgui']['noautocomplete'])) {
		unset($config['system']['webgui']['noautocomplete']);
	} else {
		$config['system']['webgui']['loginautocomplete'] = true;
	}
}

function upgrade_108_to_109() {
	global $config;

	if (!isset($config['filter']['rule']) || !is_array($config['filter']['rule'])) {
		return;
	}

	foreach ($config['filter']['rule'] as &$rule) {
		if (!isset($rule['dscp']) || empty($rule['dscp'])) {
			continue;
		}

		$pos = strpos($rule['dscp'], ' ');
		if ($pos !== false) {
			$rule['dscp'] = substr($rule['dscp'], 0, $pos);
		}
		unset($pos);
	}
}

function upgrade_109_to_110() {
	global $config;

	if (!is_array($config['ipsec']) || !is_array($config['ipsec']['phase2'])) {
		return;
	}

	foreach ($config['ipsec']['phase2'] as &$rule) {
		if (!empty($rule['uniqid'])) {
			continue;
		}

		$rule['uniqid'] = uniqid();
	}
}

function upgrade_110_to_111() {
	global $config;

	/* Make sure unbound user exist */
	mwexec('/usr/sbin/pw groupadd -n unbound -g 59', true);
	mwexec('/usr/sbin/pw useradd -n unbound -c "Unbound DNS Resolver" -d /var/unbound -s /usr/sbin/nologin -u 59 -g 59', true);

	/* cleanup old unbound package stuffs */
	unlink_if_exists("/usr/local/pkg/unbound.xml");
	unlink_if_exists("/usr/local/pkg/unbound.inc");
	unlink_if_exists("/usr/local/pkg/unbound_advanced.xml");
	unlink_if_exists("/usr/local/www/unbound_status.php");
	unlink_if_exists("/usr/local/www/unbound_acls.php");
	unlink_if_exists("/usr/local/bin/unbound_monitor.sh");
	unlink_if_exists("/usr/local/etc/rc.d/unbound.sh");

	/* Remove old menu and service entries */
	if (isset($config['installedpackages']['menu']) && is_array($config['installedpackages']['menu'])) {
		foreach ($config['installedpackages']['menu'] as $idx => $menu) {
			if ($menu['name'] != 'Unbound DNS') {
				continue;
			}

			unset($config['installedpackages']['menu'][$idx]);
			break;
		}
	}

	if (isset($config['installedpackages']['service']) && is_array($config['installedpackages']['service'])) {
		foreach ($config['installedpackages']['service'] as $idx => $service) {
			if ($service['name'] != 'unbound') {
				continue;
			}
			unset($config['installedpackages']['service'][$idx]);
			break;
		}
	}

	if (!isset($config['installedpackages']['unbound']['config'][0])) {
		return;
	}

	$pkg = $config['installedpackages']['unbound']['config'][0];

	if (isset($config['installedpackages']['unboundadvanced']['config'][0])) {
		$pkg = array_merge($pkg, $config['installedpackages']['unboundadvanced']['config'][0]);
	}

	$new = array();

	/* deal first with boolean fields */
	$fields = array(
		"enable" => "enable",
		"dnssec_status" => "dnssec",
		"forwarding_mode" => "forwarding",
		"regdhcp" => "regdhcp",
		"regdhcpstatic" => "regdhcpstatic",
		"txtsupport" => "txtsupport",
		"hide_id" => "hideidentity",
		"hide_version" => "hideversion",
		"prefetch" => "prefetch",
		"prefetch_key" => "prefetchkey",
		"harden_glue" => "hardenglue",
		"harden_dnssec_stripped" => "dnssec_stripped");

	foreach ($fields as $oldk => $newk) {
		if (isset($pkg[$oldk])) {
			if ($pkg[$oldk] == 'on') {
				$new[$newk] = true;
			}
			unset($pkg[$oldk]);
		}
	}

	$fields = array(
		"active_interface" => "network_interface",
		"query_interface" => "outgoing_interface",
		"unbound_verbosity" => "log_verbosity",
		"msg_cache_size" => "msgcachesize",
		"outgoing_num_tcp" => "outgoing_num_tcp",
		"incoming_num_tcp" => "incoming_num_tcp",
		"edns_buffer_size" => "edns_buffer_size",
		"num_queries_per_thread" => "num_queries_per_thread",
		"jostle_timeout" => "jostle_timeout",
		"cache_max_ttl" => "cache_max_ttl",
		"cache_min_ttl" => "cache_min_ttl",
		"infra_host_ttl" => "infra_host_ttl",
		"infra_cache_numhosts" => "infra_cache_numhosts",
		"unwanted_reply_threshold" => "unwanted_reply_threshold",
		"custom_options" => "custom_options");

	foreach ($fields as $oldk => $newk) {
		if (isset($pkg[$oldk])) {
			$new[$newk] = $pkg[$oldk];
			unset($pkg[$oldk]);
		}
	}

	if (isset($new['custom_options']) && !empty($new['custom_options'])) {
		$new['custom_options'] = str_replace("\r\n", "\n", $new['custom_options']);
	}

	/* Following options were removed, bring them as custom_options */
	if (isset($pkg['stats']) && $pkg['stats'] == "on") {
		if (isset($pkg['stats_interval'])) {
			$new['custom_options'] .= (empty($new['custom_options']) ? "" : "\n") . "statistics-interval: {$pkg['stats_interval']}";
		}
		if (isset($pkg['cumulative_stats'])) {
			$new['custom_options'] .= (empty($new['custom_options']) ? "" : "\n") . "statistics-cumulative: {$pkg['cumulative_stats']}";
		}
		if (isset($pkg['extended_stats']) && $pkg['extended_stats'] == "on") {
			$new['custom_options'] .= (empty($new['custom_options']) ? "" : "\n") . "extended-statistics: yes";
		} else {
			$new['custom_options'] .= (empty($new['custom_options']) ? "" : "\n") . "extended-statistics: no";
		}
	}

	$new['acls'] = array();
	if (isset($config['installedpackages']['unboundacls']['config']) &&
	    is_array($config['installedpackages']['unboundacls']['config'])) {
		foreach ($config['installedpackages']['unboundacls']['config'] as $acl) {
			$new['acls'][] = $acl;
		}
	}

	$config['unbound'] = $new;

	if (isset($config['installedpackages']['unbound'])) {
		unset($config['installedpackages']['unbound']);
	}
	if (isset($config['installedpackages']['unboundadvanced'])) {
		unset($config['installedpackages']['unboundadvanced']);
	}
	if (isset($config['installedpackages']['unboundacls'])) {
		unset($config['installedpackages']['unboundacls']);
	}

	unset($pkg, $new);
}

function upgrade_111_to_112() {
	global $config;

	$config['cron']['item'][] = array(
		'minute' => '*/60',
		'hour' => '*',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout'
	);
}

function upgrade_112_to_113() {
	global $config;

	if (isset($config['notifications']['smtp']['ssl'])) {
		if ($config['notifications']['smtp']['ssl'] == "checked") {
			$config['notifications']['smtp']['ssl'] = true;
		} else {
			unset($config['notifications']['smtp']['ssl']);
		}
	}

	if (isset($config['notifications']['smtp']['tls'])) {
		if ($config['notifications']['smtp']['tls'] == "checked") {
			$config['notifications']['smtp']['tls'] = true;
		} else {
			unset($config['notifications']['smtp']['tls']);
		}
	}
}

function upgrade_113_to_114() {
	global $config;

	if (!isset($config['ipsec']['phase1']) ||
	    !is_array($config['ipsec']['phase1'])) {
		return;
	}

	foreach ($config['ipsec']['phase1'] as &$ph1ent) {
		if (!isset($ph1ent['iketype'])) {
			$ph1ent['iketype'] = 'ikev1';
		}
	}
}

function upgrade_114_to_115() {
	global $config;

	if (isset($config['unbound']['custom_options'])) {
		$config['unbound']['custom_options'] = base64_encode($config['unbound']['custom_options']);
	}
}

function upgrade_115_to_116() {
	global $config;

	if (!is_array($config['ipsec']) || !is_array($config['ipsec']['phase2'])) {
		return;
	}

	$keyid = 1;
	foreach ($config['ipsec']['phase2'] as $idx => $ph2) {
		$config['ipsec']['phase2'][$idx]['reqid'] = $keyid;
		$keyid++;
	}
}

function upgrade_116_to_117() {
	global $config;

	if (!isset($config['ipsec']['client']) ||
	    !isset($config['ipsec']['client']['dns_split']) ||
	    empty($config['ipsec']['client']['dns_split'])) {
		return;
	}

	$config['ipsec']['client']['dns_split'] =
		preg_replace('/\s*,\s*/', ' ', trim($config['ipsec']['client']['dns_split']));

}

function upgrade_117_to_118() {
	global $config;

	// Unset any old CA and Cert in the system section that might still be there from when upgrade_066_to_067 did not unset them.
	if (isset($config['system']['ca'])) {
		unset($config['system']['ca']);
	}
	if (isset($config['system']['cert'])) {
		unset($config['system']['cert']);
	}

	init_config_arr(array('ipsec', 'phase1'));
	$a_phase1 = &$config['ipsec']['phase1'];

	foreach ($a_phase1 as &$ph1_entry) {
		// update asn1dn strings from racoon's format to strongswan's
		if (isset($ph1_entry['myid_type']) && $ph1_entry['myid_type'] == 'asn1dn') {
			$ph1_entry['myid_data'] =
			    preg_replace('/\/\s*emailAddress\s*=\s*/', ', E=', $ph1_entry['myid_data']);
		}
		if (isset($ph1_entry['peerid_type']) && $ph1_entry['peerid_type'] == 'asn1dn') {
			$ph1_entry['peerid_data'] =
			    preg_replace('/\/\s*emailAddress\s*=\s*/', ', E=', $ph1_entry['peerid_data']);
		}
	}
}

function upgrade_118_to_119() {
	global $config;

	if (!isset($config['ipsec']['phase1'])) {
		return;
	}

	// change peerid_type to 'any' for EAP types to retain previous behavior of omitting rightid
	init_config_arr(array('ipsec', 'phase1'));
	$a_phase1 = &$config['ipsec']['phase1'];

	foreach ($a_phase1 as &$ph1_entry) {
		if (strstr($ph1_entry['authentication_method'], 'eap')) {
			$ph1_entry['peerid_type'] = "any";
		}
	}
}

function upgrade_119_to_120() {
	require_once("ipsec.inc");
	global $config, $ipsec_log_cats;

	if (!is_array($config['ipsec'])) {
		return;
	}

	// add 1 to configured log levels as part of redmine #5340
	foreach ($ipsec_log_cats as $lkey => $ldescr) {
		if (isset($config['ipsec']["ipsec_{$lkey}"])) {
			$config['ipsec']["ipsec_{$lkey}"] = $config['ipsec']["ipsec_{$lkey}"] + 1;
		}
	}

}


function upgrade_120_to_121() {
	global $config;

	if (!isset($config['installedpackages']['miniupnpd']['config'][0])) {
		return;
	}

	$miniupnpd = &$config['installedpackages']['miniupnpd']['config'][0];

	$miniupnpd['row'] = array();

	for ($i = 1; $i <= 4; $i++) {
		if (isset($miniupnpd["permuser{$i}"]) && !empty($miniupnpd["permuser{$i}"])) {
			$miniupnpd['row'][] = array('permuser' => $miniupnpd["permuser{$i}"]);
		}
		unset($miniupnpd["permuser{$i}"]);
	}
}

function upgrade_121_to_122() {
	global $config;
	foreach ($config['system']['user'] as &$user) {
		if (isset($user['nt-hash'])) {
			unset($user['nt-hash']);
		}
	}
}

function upgrade_122_to_123() {
	global $config;

	// PPTP server was removed
	if (isset($config['pptpd'])) {
		unset($config['pptpd']);
	}

	// Cleanup firewall rules
	if (isset($config['filter']['rule']) && is_array($config['filter']['rule'])) {
		$rules = &$config['filter']['rule'];
		$last_rule = count($rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($rules[$i]['interface']) && $rules[$i]['interface'] == 'pptp') {
				unset($config['filter']['rule'][$i]);
				continue;
			}
			if (isset($rules[$i]['source']['network']) && $rules[$i]['source']['network'] == 'pptp') {
				unset($config['filter']['rule'][$i]);
				continue;
			}
			if (isset($rules[$i]['destination']['network']) && $rules[$i]['destination']['network'] == 'pptp') {
				unset($config['filter']['rule'][$i]);
				continue;
			}
		}
	}

	// Cleanup 1:1 NAT rules
	if (isset($config['nat']['onetoone']) && is_array($config['nat']['onetoone'])) {
		$onetoone = &$config['nat']['onetoone'];
		$last_rule = count($onetoone) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($onetoone[$i]['interface']) && $onetoone[$i]['interface'] == 'pptp') {
				unset($config['nat']['onetoone'][$i]);
				continue;
			}
			if (isset($onetoone[$i]['source']['network']) && $onetoone[$i]['source']['network'] == 'pptp') {
				unset($config['nat']['onetoone'][$i]);
				continue;
			}
			if (isset($onetoone[$i]['destination']['network']) && $onetoone[$i]['destination']['network'] == 'pptp') {
				unset($config['nat']['onetoone'][$i]);
				continue;
			}
		}
	}

	// Cleanup npt NAT rules
	if (isset($config['nat']['npt']) && is_array($config['nat']['npt'])) {
		$npt = &$config['nat']['npt'];
		$last_rule = count($npt) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($npt[$i]['interface']) && $npt[$i]['interface'] == 'pptp') {
				unset($config['nat']['npt'][$i]);
				continue;
			}
		}
	}

	// Cleanup Port-forward NAT rules
	if (isset($config['nat']['rule']) && is_array($config['nat']['rule'])) {
		$nat_rules = &$config['nat']['rule'];
		$last_rule = count($nat_rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($nat_rules[$i]['interface']) && $nat_rules[$i]['interface'] == 'pptp') {
				unset($config['nat']['rule'][$i]);
				continue;
			}
			if (isset($nat_rules[$i]['source']['network']) && $nat_rules[$i]['source']['network'] == 'pptp') {
				unset($config['nat']['rule'][$i]);
				continue;
			}
			if (isset($nat_rules[$i]['destination']['network']) && $nat_rules[$i]['destination']['network'] == 'pptp') {
				unset($config['nat']['rule'][$i]);
				continue;
			}
		}
	}

	// Cleanup Port-forward NAT rules
	if (isset($config['nat']['outbound']['rule']) && is_array($config['nat']['outbound']['rule'])) {
		$out_rules = &$config['nat']['outbound']['rule'];
		$last_rule = count($out_rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($out_rules[$i]['interface']) && $out_rules[$i]['interface'] == 'pptp') {
				unset($config['nat']['outbound']['rule'][$i]);
				continue;
			}
		}
	}
}

function upgrade_123_to_124() {
	if (isset($config['system']['altpkgrepo'])) {
		unset($config['system']['altpkgrepo']);
	}

	if (isset($config['theme'])) {
		unset($config['theme']);
	}
}

function upgrade_124_to_125() {
	global $config;

	/* Find interfaces with WEP configured. */
	foreach ($config['interfaces'] as $ifname => $intf) {
		if (!is_array($intf['wireless'])) {
			continue;
		}

		/* Generate a notice, disable interface, remove WEP settings */
		if (isset($intf['wireless']['wep']['enable'])) {
			if (!function_exists("file_notice")) {
				require_once("notices.inc");
			}
			file_notice("WirelessSettings", sprintf(gettext("WEP is no longer supported. It will be disabled on the %s interface and the interface will be disabled. Please reconfigure the interface."), $ifname));
			unset($config['interfaces'][$ifname]['wireless']['wep']);
			if (isset($intf['enable'])) {
				unset($config['interfaces'][$ifname]['enable']);
			}
		}
	}
}

function upgrade_125_to_126() {
	require_once("ipsec.inc");
	global $config, $ipsec_log_cats, $ipsec_log_sevs;

	$def_loglevel = 1;
	if (!is_array($config['ipsec'])) {
		return;
	}

	if (!isset($config['ipsec']['logging']) || !is_array($config['ipsec']['logging'])) {
		$config['ipsec']['logging'] = array();
	}

	/* subtract 2 from ipsec log levels. the value stored in the config.xml
	 * will now match the strongswan level exactly.
	 */
	foreach (array_keys($ipsec_log_cats) as $cat) {
		if (!isset($config['ipsec']["ipsec_{$cat}"])) {
			$new_level = $def_loglevel;
		} else {
			$new_level = intval($config['ipsec']["ipsec_{$cat}"]) - 2;
		}

		if (in_array($new_level, array_keys($ipsec_log_sevs))) {
			$config['ipsec']['logging'][$cat] = $new_level;
		} else {
			$config['ipsec']['logging'][$cat] = $def_loglevel;
		}
		unset($config['ipsec']["ipsec_{$cat}"]);
	}
}

// prior to v2.3 <widgets><sequence> contains a list of widgets with display types:
//		none, close, hide, & show
// v2.3 & later uses:
//		close & open
// widgets not in use are simply not in the list
function upgrade_126_to_127() {
	global $config;

	if (!isset($config['widgets']['sequence'])) {
		return;
	}

	$cur_widgets = explode(',', trim($config['widgets']['sequence']));
	$new_widgets = array();

	foreach ($cur_widgets as $widget) {
		list($file, $col, $display) = explode(':', $widget);

		switch ($display) {
			case 'hide':
				$display = 'close';
				break;
			case 'show':
				$display = 'open';
				break;
			case 'open':
				break;
			default:
				continue 2;
		}

		/* Remove '-container' from widget name */
		$file = preg_replace('/-container$/', '', $file);

		$new_widgets[] = "{$file}:{$col}:{$display}";
	}

	$config['widgets']['sequence'] = implode(',', $new_widgets);

}

function upgrade_127_to_128() {
	global $config;

	// If bindip is not already specified then migrate the old SNMP bindlan flag to a bindip setting
	if (isset($config['snmpd']['bindlan'])) {
		if (!isset($config['snmpd']['bindip'])) {
			$config['snmpd']['bindip'] = 'lan';
		}
		unset($config['snmpd']['bindlan']);
	}
}

function upgrade_128_to_129() {
	global $config;

	/* net.inet.ip.fastforwarding does not exist in 2.3. */
	if (!isset($config['sysctl']['item']) ||
	    !is_array($config['sysctl']['item'])) {
		return;
	}

	foreach ($config['sysctl']['item'] as $idx => $sysctl) {
		if ($sysctl['tunable'] == "net.inet.ip.fastforwarding") {
			unset($config['sysctl']['item'][$idx]);
		}
		if ($sysctl['tunable'] == "net.inet.ipsec.debug") {
			$config['sysctl']['item'][$idx]['value'] = "0";
		}
	}

	/* IPSEC is always on in 2.3. */
	if (isset($config['ipsec']['enable'])) {
		unset($config['ipsec']['enable']);
	} else if (is_array($config['ipsec']['phase1'])) {
		/*
		 * If IPsec was globally disabled, disable all
		 * phase1 entries
		 */
		foreach ($config['ipsec']['phase1'] as $idx => $p1) {
			$config['ipsec']['phase1'][$idx]['disabled'] = true;
		}
	}
}

function upgrade_129_to_130() {
	global $config;

	/* Change OpenVPN topology_subnet checkbox into topology multi-select #5526 */
	if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as & $serversettings) {
			if (strtolower($serversettings['topology_subnet']) == "yes") {
				unset($serversettings['topology_subnet']);
				$serversettings['topology'] = "subnet";
			} else {
				$serversettings['topology'] = "net30";
			}
		}
	}
}

function upgrade_130_to_131() {
	global $config;

	// Default dpinger parameters at time of this upgrade (2.3)
	$default_interval = 500;
	$default_alert_interval = 1000;
	$default_loss_interval = 2000;
	$default_time_period = 60000;

	if (isset($config['syslog']['apinger'])) {
		$config['syslog']['dpinger'] = true;
		unset($config['syslog']['apinger']);
	}

	if (isset($config['system']['apinger_debug'])) {
		unset($config['system']['apinger_debug']);
	}

	if (!isset($config['gateways']['gateway_item']) ||
	    !is_array($config['gateways']['gateway_item'])) {
		return;
	}

	if (is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as &$gw) {
			// dpinger uses milliseconds
			if (isset($gw['interval']) &&
				is_numeric($gw['interval'])) {
				$gw['interval'] = $gw['interval'] * 1000;
			}

			if (isset($gw['interval'])) {
				$effective_interval = $gw['interval'];
			} else {
				$effective_interval = $default_interval;
			}

			if (isset($gw['down']) &&
				is_numeric($gw['down'])) {
				$gw['time_period'] = $gw['down'] * 1000;
				unset($gw['down']);
			}

			if (isset($gw['time_period'])) {
				$effective_time_period = $gw['time_period'];
			} else {
				$effective_time_period = $default_time_period;
			}

			if (isset($gw['latencyhigh'])) {
				// Default loss_interval is 2000, but must be set
				// higher if latencyhigh is higher.
				if ($gw['latencyhigh'] > $default_loss_interval) {
					$gw['loss_interval'] = $gw['latencyhigh'];
				}
			}

			if (isset($gw['loss_interval'])) {
				$effective_loss_interval = $gw['loss_interval'];
			} else {
				$effective_loss_interval = $default_loss_interval;
			}

			if (isset($gw['interval'])) {
				// Default alert_interval is 1000, but must be set
				// higher if interval is higher.
				if ($gw['interval'] > $default_alert_interval) {
					$gw['alert_interval'] = $gw['interval'];
				}
			}

			if ((($effective_interval * 2) + $effective_loss_interval) >= $effective_time_period) {
				$gw['time_period'] = ($effective_interval * 2) + $effective_loss_interval + 1;
			}

			if (isset($gw['avg_delay_samples'])) {
				unset($gw['avg_delay_samples']);
			}
			if (isset($gw['avg_delay_samples_calculated'])) {
				unset($gw['avg_delay_samples_calculated']);
			}
			if (isset($gw['avg_loss_samples'])) {
				unset($gw['avg_loss_samples']);
			}
			if (isset($gw['avg_loss_samples_calculated'])) {
				unset($gw['avg_loss_samples_calculated']);
			}
			if (isset($gw['avg_loss_delay_samples'])) {
				unset($gw['avg_loss_delay_samples']);
			}
			if (isset($gw['avg_loss_delay_samples_calculated'])) {
				unset($gw['avg_loss_delay_samples_calculated']);
			}
		}
	}
}

function upgrade_131_to_132() {
	global $config;
	if (isset($config['system']['usefifolog'])) {
		unset($config['system']['usefifolog']);
		clear_all_log_files(false);
	}
}

function upgrade_132_to_133() {
	global $config;

	if (isset($config['ipsec']['phase1']) &&
	    is_array($config['ipsec']['phase1'])) {
		foreach ($config['ipsec']['phase1'] as &$p1) {
			if (isset($p1['encryption-algorithm']['name']) &&
			    $p1['encryption-algorithm']['name'] == 'des') {
				$p1['disabled'] = true;
				file_notice("IPsec",
				    sprintf(gettext("DES is no longer supported, IPsec phase 1 item '%s' is being disabled."), $p1['descr']));
			}
		}
	}

	if (isset($config['ipsec']['phase2']) &&
	    is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase2'] as &$p2) {
			if (!isset($p2['encryption-algorithm-option']) ||
			    !is_array($p2['encryption-algorithm-option'])) {
				continue;
			}

			foreach ($p2['encryption-algorithm-option'] as $ealgo) {
				if ($ealgo['name'] == 'des') {
					$p2['disabled'] = true;
					file_notice("IPsec",
					    sprintf(gettext("DES is no longer supported, IPsec phase 2 item '%s' is being disabled."), $p2['descr']));
				}
			}
		}
	}
}

// Determine the highest column number in use and set dashboardcolumns accordingly
function upgrade_133_to_134() {
	global $config;

	if (!isset($config['widgets']['sequence']) || isset($config['system']['webgui']['dashboardcolumns'])) {
		return;
	}

	$cur_widgets = explode(',', trim($config['widgets']['sequence']));
	$maxcols = 2;

	foreach ($cur_widgets as $widget) {
		list($file, $col, $display) = explode(':', $widget);

		if (($display != 'none') && ($display != 'hide')) {
			preg_match('#[0-9]+$#', $col, $column);
			if ($column[0] > $maxcols) {
				$maxcols = $column[0];
			}
		}
	}

	$config['system']['webgui']['dashboardcolumns'] = $maxcols % 10;
}

function upgrade_134_to_135() {
	global $config;

	if (isset($config['syslog']['nologlighttpd'])) {
		unset($config['syslog']['nologlighttpd']);
		$config['syslog']['nolognginx'] = true;
	}
}

function upgrade_135_to_136() {
	global $config;

	$l7_active = false;
	if (isset($config['l7shaper'])) {
		unset($config['l7shaper']);
		if (is_array($config['filter']['rule'])) {
			foreach ($config['filter']['rule'] as $idx => $rule) {
				if (isset($rule['l7container'])) {
					unset($config['filter']['rule'][$idx]['l7container']);
					$l7_active = true;
				}
			}
		}
		if ($l7_active) {
			file_notice("L7shaper", gettext("Layer 7 shaping is no longer supported. Its configuration has been removed."));
		}
	}
}

function upgrade_136_to_137() {
	global $config;

	if (is_array($config['dhcpd'])) {
		foreach ($config['dhcpd'] as &$dhcpd) {
			if (!is_array($dhcpd['numberoptions']['item'])) {
				continue;
			}

			foreach ($dhcpd['numberoptions']['item'] as &$item) {
				$item['value'] = base64_encode($item['value']);
			}
		}
	}

	if (is_array($config['dhcpdv6'])) {
		foreach ($config['dhcpdv6'] as &$dhcpdv6) {
			if (!is_array($dhcpdv6['numberoptions']['item'])) {
				continue;
			}

			foreach ($dhcpdv6['numberoptions']['item'] as &$item) {
				$item['value'] = base64_encode($item['value']);
			}
		}
	}
}

function upgrade_137_to_138() {
	global $config;

	// the presence of unityplugin tag used to disable loading of unity plugin
	// it's now disabled by default, and config tag is to enable. Unset accordingly.
	if (is_array($config['ipsec'])) {
		if (isset($config['ipsec']['unityplugin'])) {
			unset($config['ipsec']['unityplugin']);
		}
	}
}

function upgrade_138_to_139() {
	global $config;

	// clean up state killing on gateway failure. having kill_states set used to mean it was disabled
	// now set gw_down_kill_states if enabled.
	if (!isset($config['system']['kill_states'])) {
		$config['system']['gw_down_kill_states'] = true;
	} else {
		unset($config['system']['kill_states']);
	}
}

function upgrade_139_to_140() {
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $idx => $vip) {
			if ($vip['mode'] == "carp") {
				if (!isset($vip['uniqid'])) {
					$config['virtualip']['vip'][$idx]['uniqid'] = uniqid();
				}
			}
		}
	}
}

function upgrade_140_to_141() {
	global $config;

	// retain OpenVPN's net30 default topology for upgraded client configs so they still work
	// This is for 2.3 ALPHA to a later 2.3, not 2.2.x upgrades, which had no topology setting on clients
	if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $idx => $ovpnclient) {
			if (!isset($ovpnclient['topology'])) {
				$config['openvpn']['openvpn-client'][$idx]['topology'] = "net30";
			}
		}
	}

	// repeat addition of filter tracker IDs from 106_to_107 where missing since associated filter rules were missing them
	if (is_array($config['filter']) && is_array($config['filter']['rule'])) {
		$tracker = (int)microtime(true);
		foreach ($config['filter']['rule'] as $ridx => $rule) {
			if (empty($rule['tracker'])) {
				$config['filter']['rule'][$ridx]['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $ridx);
	}

}

function upgrade_141_to_142() {
	global $config;
	/* Convert Namecheap type DynDNS entries to the new split hostname and domain format */

	init_config_arr(array('dyndnses', 'dyndns'));
	$a_dyndns = &$config['dyndnses']['dyndns'];

	foreach ($a_dyndns as &$dyndns) {
		if ($dyndns['type'] == "namecheap") {
			/* Use the old style logic to split the host and domain one last time. */
			$dparts = explode(".", trim($dyndns['host']));
			$domain_part_count = ($dparts[count($dparts)-1] == "uk") ? 3 : 2;
			$domain_offset = count($dparts) - $domain_part_count;
			$dyndns['host'] = implode(".", array_slice($dparts, 0, $domain_offset));
			$dyndns['domainname'] = implode(".", array_slice($dparts, $domain_offset));
		}
	}

	/* unset old pppoerestart cron job if it exists. redmine 1905 */
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $idx => $cronitem) {
			if ($cronitem['command'] == "/etc/pppoerestart") {
				unset($config['cron']['item'][$idx]);
			}
		}
	}
}

// Updated to check for empty separator definitions via is_array()
function upgrade_142_to_143() {
	global $config;

	/* Re-index firewall rule separators per interface */
	if (is_array($config['filter']['separator'])) {
		foreach ($config['filter']['separator'] as $interface => $separators) {

			if (is_array($separators)) {
				foreach ($separators as $sepn => $separator) {

					$seprow = substr($separator['row']['0'], 2);
					$sepif  = $separator['if'];

					// Determine position of separator within the interface rules.
					$i = -1; $j = 0;
					foreach ($config['filter']['rule'] as $rulen => $filterent) {

						if ($i == $seprow) {
							// Set separator row to it's position within the interface rules.
							$config['filter']['separator'][$sepif][$sepn]['row'] = 'fr' . $j;
							continue 2;	// Advance to next separator
						}

						// Position within the interface rules.
						if (($filterent['interface'] == $sepif && !isset($filterent['floating'])) || (isset($filterent['floating']) && "floatingrules" == $sepif)) {
							$j++;
						}
						$i++;
					}
				}
			}
		}
	}

	/* Re-index nat rule separators */
	if (is_array($config['nat']['separator'])) {
		foreach ($config['nat']['separator'] as $sepn => $separator) {
			if (is_array($separator)) {
				$seprow = substr($separator['row']['0'], 2);
				$config['nat']['separator'][$sepn]['row'] = 'fr' . ($seprow + 1);
			}
		}
	}
}

function get_vip_from_ip_alias($ipalias) {
	global $config;

	foreach ($config['virtualip']['vip'] as $idx => $vip) {
		if ($vip['mode'] != "ipalias") {
			continue;
		}
		if ($ipalias == $vip['subnet']) {
			return ("_vip{$vip['uniqid']}");
		}
	}

	return ($ipalias);
}

function get_vip_from_oldcarp($carp) {
	global $config;

	foreach ($config['virtualip']['vip'] as $idx => $vip) {
		if ($vip['mode'] != "carp") {
			continue;
		}
		if ($carp == "{$vip['interface']}_vip{$vip['vhid']}") {
			return ("_vip{$vip['uniqid']}");
		}
	}

	return ($carp);
}

function upgrade_143_to_144() {
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $idx => $vip) {
			if ($vip['mode'] == "ipalias") {
				if (!isset($vip['uniqid'])) {
					$config['virtualip']['vip'][$idx]['uniqid'] = uniqid();
				}
			}
		}
	}

	/* Convert IPsec phase 1 entries. */
	if (is_array($config['ipsec']['phase1'])) {
		foreach ($config['ipsec']['phase1'] as $idx => $ph1ent) {
			if (is_ipaddr($ph1ent['interface']) || is_ipaddrv6($ph1ent['interface'])) {
				$config['ipsec']['phase1'][$idx]['interface'] = get_vip_from_ip_alias($ph1ent['interface']);
			} else if (strpos($ph1ent['interface'], "_vip")) {
				$config['ipsec']['phase1'][$idx]['interface'] = get_vip_from_oldcarp($ph1ent['interface']);
			}
		}
	}

	/* Convert openvpn. */
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $idx => $ovpn) {
			if (empty($ovpn['interface'])) {
				continue;
			}
			if (is_ipaddr($ovpn['interface']) || is_ipaddrv6($ovpn['interface'])) {
				$config['openvpn']['openvpn-server'][$idx]['interface'] = get_vip_from_ip_alias($ovpn['interface']);
			} else if (strpos($ovpn['interface'], "_vip")) {
				$config['openvpn']['openvpn-server'][$idx]['interface'] = get_vip_from_oldcarp($ovpn['interface']);
			}
		}
	}
	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $idx => $ovpn) {
			if (empty($ovpn['interface'])) {
				continue;
			}
			if (is_ipaddr($ovpn['interface']) || is_ipaddrv6($ovpn['interface'])) {
				$config['openvpn']['openvpn-client'][$idx]['interface'] = get_vip_from_ip_alias($ovpn['interface']);
			} else if (strpos($ovpn['interface'], "_vip")) {
				$config['openvpn']['openvpn-client'][$idx]['interface'] = get_vip_from_oldcarp($ovpn['interface']);
			}
		}
	}

	/* Convert unbound. */
	if (is_array($config['unbound']) && !empty($config['unbound']['active_interface'])) {
		$active_ifs = explode(",", $config['unbound']['active_interface']);
		$ifs = array();
		foreach ($active_ifs as $if) {
			if (is_ipaddr($if) || is_ipaddrv6($if)) {
				$ifs[] = get_vip_from_ip_alias($if);
			} else if (strpos($if, "_vip")) {
				$ifs[] = get_vip_from_oldcarp($if);
			} else {
				$ifs[] = $if;
			}
		}
		$config['unbound']['active_interface'] = implode(",", $ifs);
	}

	/* Convert dnsmasq. */
	if (is_array($config['dnsmasq']) && !empty($config['dnsmasq']['interface'])) {
		$active_ifs = explode(",", $config['dnsmasq']['interface']);
		$ifs = array();
		foreach ($active_ifs as $if) {
			if (is_ipaddr($if) || is_ipaddrv6($if)) {
				$ifs[] = get_vip_from_ip_alias($if);
			} else if (strpos($if, "_vip")) {
				$ifs[] = get_vip_from_oldcarp($if);
			} else {
				$ifs[] = $if;
			}
		}
		$config['dnsmasq']['interface'] = implode(",", $ifs);
	}
}

function upgrade_144_to_145() {
	global $config;

	// Enable DHCPv6 server and radvd config for track6 interfaces,
	// matching what used to be automatically enabled with no user
	// configurability.
	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $ifname => $ifcfg) {
			if (isset($ifcfg['enable'])) {
				if ($ifcfg['ipaddrv6'] == "track6") {
					init_config_arr(array('dhcpdv6', $ifname, 'range'));
					$config['dhcpdv6'][$ifname]['enable'] = true;
					$config['dhcpdv6'][$ifname]['range']['from'] = "::1000";
					$config['dhcpdv6'][$ifname]['range']['to'] = "::2000";
					$config['dhcpdv6'][$ifname]['ramode'] = "assist";
					$config['dhcpdv6'][$ifname]['rapriority'] = "medium";
				}
			}
		}
	}
}

function upgrade_145_to_146() {
	// Add standard deviation to the quality rrds
	global $config, $g;

	$rrddbpath = "/var/db/rrd";
	$rrdtool = "/usr/local/bin/rrdtool";

	$awkcmd = "/usr/bin/awk '";
	$awkcmd .= "{\n";
	$awkcmd .= "    if (sub(/<\\/v><\\/row>/, \"</v><v>NaN</v></row>\") == 0)\n";
	$awkcmd .= "    {\n";
	$awkcmd .= "        if (/<\\/cdp_prep>/)\n";
	$awkcmd .= "        {\n";
	$awkcmd .= "            print \"			<ds>\"\n";
	$awkcmd .= "            print \"			<primary_value> 0.0000000000e+00 </primary_value>\"\n";
	$awkcmd .= "            print \"			<secondary_value> 0.0000000000e+00 </secondary_value>\"\n";
	$awkcmd .= "            print \"			<value> NaN </value>\"\n";
	$awkcmd .= "            print \"			<unknown_datapoints> 0 </unknown_datapoints>\"\n";
	$awkcmd .= "            print \"			</ds>\"\n";
	$awkcmd .= "        }\n";
	$awkcmd .= "        else if (/<!-- Round Robin Archives -->/)\n";
	$awkcmd .= "        {\n";
	$awkcmd .= "            print \"	<ds>\"\n";
	$awkcmd .= "            print \"		<name> stddev </name>\"\n";
	$awkcmd .= "            print \"		<type> GAUGE </type>\"\n";
	$awkcmd .= "            print \"		<minimal_heartbeat> 120 </minimal_heartbeat>\"\n";
	$awkcmd .= "            print \"		<min> 0.0000000000e+00 </min>\"\n";
	$awkcmd .= "            print \"		<max> 1.0000000000e+05 </max>\\n\"\n";
	$awkcmd .= "            print \"		<!-- PDP Status -->\"\n";
	$awkcmd .= "            print \"		<last_ds> 0 </last_ds>\"\n";
	$awkcmd .= "            print \"		<value> 0.0000000000e+00 </value>\"\n";
	$awkcmd .= "            print \"		<unknown_sec> 0 </unknown_sec>\"\n";
	$awkcmd .= "            print \"	</ds>\\n\"\n";
	$awkcmd .= "        }\n";
	$awkcmd .= "    }\n";
	$awkcmd .= "    print;\n";
	$awkcmd .= "}'";

	$databases = return_dir_as_array($rrddbpath, '/-quality\.rrd$/');
	foreach ($databases as $database) {
		$xmldump = "{$g['tmp_path']}/{$database}.xml";

		if (platform_booting()) {
			echo "Update RRD database {$database}.\n";
		}

		exec("$rrdtool dump {$rrddbpath}/{$database} | {$awkcmd} > {$xmldump}");
		exec("$rrdtool restore -f {$xmldump} {$rrddbpath}/{$database}");
		@unlink("{$xmldump}");
	}

	if (!platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
}

function upgrade_bgpd_146_to_147() {
	global $config;

	if (!isset($config['installedpackages']['openbgpd']['config']) ||
	    !is_array($config['installedpackages']['openbgpd']['config'])) {
		return;
	}
	$openbgpd_conf = &$config['installedpackages']['openbgpd']['config'][0];
	if (!isset($openbgpd_conf['carpstatusip']) &&
	    !is_ipaddr($openbgpd_conf['carpstatusip'])) {
		return;
	}

	if (!is_array($config['virtualip']['vip']))
		return;
	foreach ($config['virtualip']['vip'] as $idx => $vip) {
		if ($vip['subnet'] == $openbgpd_conf['carpstatusip']) {
			$openbgpd_conf['carpstatusvid'] = "_vip{$vip['uniqid']}";
			unset($openbgpd_conf['carpstatusip']);
			return;
		}
	}
}

function upgrade_quagga_146_to_147() {
	global $config;

	if (!isset($config['installedpackages']['quaggaospfd']['config']) ||
	    !is_array($config['installedpackages']['quaggaospfd']['config'])) {
		return;
	}
	$ospfd_conf = &$config['installedpackages']['quaggaospfd']['config'][0];
	if (!isset($ospfd_conf['carpstatusip']) &&
	    !is_ipaddr($ospfd_conf['carpstatusip'])) {
		return;
	}

	if (!is_array($config['virtualip']['vip']))
		return;
	foreach ($config['virtualip']['vip'] as $idx => $vip) {
		if ($vip['subnet'] == $ospfd_conf['carpstatusip']) {
			$ospfd_conf['carpstatusvid'] = "_vip{$vip['uniqid']}";
			unset($ospfd_conf['carpstatusip']);
			return;
		}
	}
}

function upgrade_146_to_147() {

	upgrade_bgpd_146_to_147();
	upgrade_quagga_146_to_147();
}

function upgrade_147_to_148() {
	global $config;

	// Ensure there are no spaces in group names by
	// replacing spaces with underscores
	if (is_array($config['system']['group'])) {
		$cleargroups = false;
		foreach ($config['system']['group'] as $idx => $grp) {
			if (strstr($grp['name'], " ")) {
				$cleargroups = true;
				$config['system']['group'][$idx]['scope'] = "remote";
			}
		}

		// if there was a space in a group name, there may be multiple
		// groups with the same name in the group file. To prevent pw
		// from getting into a neverending loop, delete all user-defined
		// groups here. local_reset_accounts will run shortly after this
		// and add them back. redmine #6012
		if ($cleargroups) {
			foreach ($config['system']['group'] as $grp) {
				mwexec("/usr/sbin/pw groupdel -g {$grp['gid']}");
			}
		}
	}
}

function upgrade_148_to_149() {
	global $config;
	global $altq_list_queues;

        if (!isset($config['shaper']['queue']) || !is_array($config['shaper']['queue']))
                return;

	read_altq_config();

	/* Set root queue bandwidth. */
	foreach ($altq_list_queues as $altq) {
		$sum = $altq->GetTotalBw();
		while ($sum > get_queue_bandwidth($altq)) {
			if (intval(($sum / 1000) * 1.2) < (1024 * 1024)) {
				/* 1Gb where possible. */
				$bw = 1024 * 1024;
			} else {
				/* Increase by 20% until it fits. */
				$bw = intval(($sum / 1000) * 1.2);
			}
			$altq->SetBandwidth($bw);
			$altq->SetBwscale("Kb");
			$altq->wconfig();
			$sum = $altq->GetTotalBw();
		}
	}
}

function upgrade_149_to_150() {
	global $config;

	if (is_array($config['dhcpdv6'])) {
                foreach ($config['dhcpdv6'] as &$dhcpdv6) {
			if (isset($dhcpdv6['rainterface'])) {
				if (strstr($dhcpdv6['rainterface'], "_vip")) {
					$dhcpdv6['rainterface'] = get_vip_from_oldcarp($dhcpdv6['rainterface']);
				}
			}
		}
	}
}

function upgrade_150_to_151() {
	global $config;

	// Default dpinger parameters at time of this upgrade (2.3.1)
	$default_interval = 500;
	$default_alert_interval = 1000;
	$default_loss_interval = 2000;
	$default_time_period = 60000;
	$default_latencyhigh = 500;

	// Check advanced gateway parameter relationships in case they are incorrect
	if (is_array($config['gateways']['gateway_item'])) {
		foreach ($config['gateways']['gateway_item'] as &$gw) {
			if (isset($gw['interval'])) {
				$effective_interval = $gw['interval'];
			} else {
				$effective_interval = $default_interval;
			}

			if (isset($gw['alert_interval'])) {
				$effective_alert_interval = $gw['alert_interval'];
			} else {
				$effective_alert_interval = $default_alert_interval;
			}

			if (isset($gw['loss_interval'])) {
				$effective_loss_interval = $gw['loss_interval'];
			} else {
				$effective_loss_interval = $default_loss_interval;
			}

			if (isset($gw['time_period'])) {
				$effective_time_period = $gw['time_period'];
			} else {
				$effective_time_period = $default_time_period;
			}

			if (isset($gw['latencyhigh'])) {
				$effective_latencyhigh = $gw['latencyhigh'];
			} else {
				$effective_latencyhigh = $default_latencyhigh;
			}

			// Loss interval has to be at least as big as high latency.
			if ($effective_latencyhigh > $effective_loss_interval) {
				$effective_loss_interval = $gw['loss_interval'] = $effective_latencyhigh;
			}

			// Alert interval has to be at least as big as probe interval.
			if ($effective_interval > $effective_alert_interval) {
				$gw['alert_interval'] = $effective_interval;
			}

			// The time period for averaging has to be more than 2 probes plus the loss interval.
			if ((($effective_interval * 2) + $effective_loss_interval) >= $effective_time_period) {
				$gw['time_period'] = ($effective_interval * 2) + $effective_loss_interval + 1;
			}
		}
	}
}

function upgrade_151_to_152() {
	global $g, $config;

	require_once("/etc/inc/services.inc");

	// Remove these cron jobs on full install if not using ramdisk.
	if (!isset($config['system']['use_mfs_tmpvar'])) {
		/* See #7146 for detail on why the extra parameters are needed for the time being. */
		install_cron_job("/etc/rc.backup_rrd.sh", false, null, null, null, null, null, null, false);
		install_cron_job("/etc/rc.backup_dhcpleases.sh", false, null, null, null, null, null, null, false);
	}
}

function upgrade_152_to_153() {
	global $config;

	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $idx => $vip) {
			if (substr($vip['interface'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($vip['interface'], "_vip")) {
				// using old VIP format, update
				$config['virtualip']['vip'][$idx]['interface'] = get_vip_from_oldcarp($vip['interface']);
			}
		}
	}

	// upgrade GIFs using VIP to new format
	if (is_array($config['gifs']['gif'])) {
		foreach ($config['gifs']['gif'] as $idx => $gif) {
			if (substr($gif['if'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($gif['if'], "_vip")) {
				// using old VIP format, update
				$config['gifs']['gif'][$idx]['if'] = get_vip_from_oldcarp($gif['if']);
			}
		}
	}

	// upgrade GREs using VIP to new format
	if (is_array($config['gres']['gre'])) {
		foreach ($config['gres']['gre'] as $idx => $gre) {
			if (substr($gre['if'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($gre['if'], "_vip")) {
				// using old VIP format, update
				$config['gres']['gre'][$idx]['if'] = get_vip_from_oldcarp($gre['if']);
			}
		}
	}

	// upgrade gateway groups using VIPs
	if (is_array($config['gateways']['gateway_group'])) {
		foreach ($config['gateways']['gateway_group'] as $idx => $gw) {
			if (is_array($gw['item'])) {
				$newitems = array();
				$gwvipchange = false;
				foreach ($gw['item'] as $item) {
					if (strstr($item, "|_vip")) {
						// using new VIP format
						$newitems[] = $item;
						continue;
					} else if (strstr($item, "_vip")) {
						// using old VIP format, update
						$gwitemarr = explode("|", $item);
						$gwitemarr[2] = get_vip_from_oldcarp($gwitemarr[2]);
						$newitems[] = implode("|", $gwitemarr);
						$gwvipchange = true;
					} else {
						$newitems[] = $item;
					}
				}
				if ($gwvipchange) {
					$config['gateways']['gateway_group'][$idx]['item'] = $newitems;
				}
			}
		}
	}
}

function upgrade_153_to_154() {
	/* NOTE: This upgrade code was reverted. See redmine ticket #6118 and
	   https://github.com/pfsense/pfsense/commit/538a3c04a6b6671151e913b06b2f340b6f8ee222 */
}

/* Clean up old GRE/GIF options. See Redmine tickets #6586 and #6587 */
function upgrade_154_to_155() {
	global $config;

	if (is_array($config['gifs']['gif'])) {
		foreach ($config['gifs']['gif'] as $idx => $gif) {
			if (isset($gif['link0'])) {
				unset($config['gifs']['gif'][$idx]['link0']);
			}
		}
	}

	if (is_array($config['gres']['gre'])) {
		foreach ($config['gres']['gre'] as $idx => $gre) {
			if (isset($gre['link0'])) {
				unset($config['gres']['gre'][$idx]['link0']);
			}
			if (isset($gre['link2'])) {
				unset($config['gres']['gre'][$idx]['link2']);
			}
		}
	}
}

function upgrade_155_to_156() {
	// Unused
}

function upgrade_156_to_157() {
	global $config;
	/* Convert Cloudflare and GratisDNS type DynDNS entries to the new split hostname and domain format */

	init_config_arr(array('dyndnses', 'dyndns'));
	$a_dyndns = &$config['dyndnses']['dyndns'];

	foreach ($a_dyndns as &$dyndns) {
		if (($dyndns['type'] == "cloudflare") || ($dyndns['type'] == "cloudflare-v6") || ($dyndns['type'] == "gratisdns")) {
			/* Use the old style logic to split the host and domain one last time. */
			$dparts = explode(".", trim($dyndns['host']));
			$domain_part_count = ($dparts[count($dparts)-1] == "uk") ? 3 : 2;
			$domain_offset = count($dparts) - $domain_part_count;
			$dyndns['host'] = implode(".", array_slice($dparts, 0, $domain_offset));
			$dyndns['domainname'] = implode(".", array_slice($dparts, $domain_offset));
		}
	}

	/* unset old pppoerestart cron job if it exists. redmine 1905 */
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $idx => $cronitem) {
			if ($cronitem['command'] == "/etc/pppoerestart") {
				unset($config['cron']['item'][$idx]);
			}
		}
	}
}

function upgrade_157_to_158() {
	global $config;
	/* Convert Dynamic DNS passwords to base64 encoding. Redmine #6688 */

	init_config_arr(array('dyndnses', 'dyndns'));
	$a_dyndns = &$config['dyndnses']['dyndns'];

	foreach ($a_dyndns as &$dyndns) {
		$dyndns['password'] = base64_encode($dyndns['password']);
	}
}

/* Unset references to glxsb in the config. See #6755 */
function upgrade_158_to_159() {
	global $config;

	if ($config['system']['crypto_hardware'] == "glxsb") {
		unset($config['system']['crypto_hardware']);
	}
}

/* Convert OpenVPN "protocol" to new style for OpenVPN 2.4, old udp/tcp was
 * IPv4 only, now is dual stack, so change it to udp4/tcp4
 */
function upgrade_159_to_160() {
	global $config;

	if (isset($config['openvpn']) && is_array($config['openvpn'])) {
		if (is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as &$vpn) {
				if ($vpn['protocol'] == "UDP") {
					$vpn['protocol'] = "UDP4";
				}
				if ($vpn['protocol'] == "TCP") {
					$vpn['protocol'] = "TCP4";
				}
			}
		}
		if (is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as &$vpn) {
				if ($vpn['protocol'] == "UDP") {
					$vpn['protocol'] = "UDP4";
				}
				if ($vpn['protocol'] == "TCP") {
					$vpn['protocol'] = "TCP4";
				}
			}
		}
	}
}

/* RAM Disk Management */
function upgrade_160_to_161() {
	global $g, $config;

	if (!isset($config['system']['use_mfs_tmpvar'])) {
		return;
	}

	// Move existing RRD backup to the RAM Disk Store if it don't already exist there.
	// Restore existing RRD XML dump backup.
	if (file_exists("{$g['cf_conf_path']}/rrd.tgz") && !file_exists("{$g['cf_conf_path']}/RAM_Disk_Store/rrd.tgz")) {
		$rrddbpath = "{$g['vardb_path']}/rrd/";
		$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";

		$rrdrestore = "";
		$rrdreturn = "";
		unlink_if_exists("{$rrddbpath}/*.xml");

		unset($rrdrestore);
		$_gb = exec("LANG=C /usr/bin/tar -tf {$g['cf_conf_path']}/rrd.tgz", $rrdrestore, $rrdreturn);
		if ($rrdreturn != 0) {
			log_error(sprintf(gettext('RRD restore failed exited with %1$s, the error is: %2$s'), $rrdreturn, $rrdrestore));
		} else {
			foreach ($rrdrestore as $xml_file) {
				$rrd_file = '/' . substr($xml_file, 0, -4) . '.rrd';
				unlink_if_exists("{$rrd_file}");

				file_put_contents("{$g['tmp_path']}/rrd_restore", $xml_file);
				$_gb = exec("LANG=C /usr/bin/tar -xf {$g['cf_conf_path']}/rrd.tgz -C / -T {$g['tmp_path']}/rrd_restore");
				if (!file_exists("/{$xml_file}")) {
					log_error(sprintf(gettext("Could not extract %s RRD xml file from archive!"), $xml_file));
					continue;
				}
				$_gb = exec("$rrdtool restore -f '/{$xml_file}' '{$rrd_file}'", $output, $status);
				if ($status) {
					log_error(sprintf(gettext("rrdtool restore -f '%1\$s' '%2\$s' failed returning %3\$s."), $xml_file, $rrd_file, $status));
					continue;
				}
				unset($output);
				@unlink("/{$xml_file}");
			}
			unset($rrdrestore);
			@unlink("{$g['tmp_path']}/rrd_restore");

			// Create a new RRD backup to the RAM Disk Store (without RRD XML dump).
			exec("/etc/rc.backup_rrd.sh");
			$ramds_updated = true;

			// Rename previous RRD backup so it will not restore again.  Don't delete in case needed for recovery.
			rename("{$g['cf_conf_path']}/rrd.tgz", "{$g['cf_conf_path']}/rrd.tgz.old");
		}
	}

	// Move existing DHCP leases backup to the RAM Disk Store if it don't already exist there.
	if (file_exists("{$g['cf_conf_path']}/dhcpleases.tgz") && ! file_exists("{$g['cf_conf_path']}/RAM_Disk_Store/dhcpleases.tgz")) {
		rename("{$g['cf_conf_path']}/dhcpleases.tgz", "{$g['cf_conf_path']}/RAM_Disk_Store/dhcpleases.tgz");
		$ramds_updated = true;
	}

	// Move existing alias table backups to the RAM Disk Store if they don't already exist there.
	$dbpath = "{$g['vardb_path']}/aliastables/";
	$files = glob("{$g['cf_conf_path']}/RAM_Disk_Store{$dbpath}*.tgz");
	if (count($files)) {
		foreach ($files as $file) {
			if (! file_exists("{$g['cf_conf_path']}/RAM_Disk_Store/".basename($file))) {
				rename($file, "{$g['cf_conf_path']}/RAM_Disk_Store/".basename($file));
				$ramds_updated = true;
			}
		}
		// Remove existing alias table backups directory if empty.
		@rmdir("{$g['cf_conf_path']}/RAM_Disk_Store/var/db/aliastables");
		@rmdir("{$g['cf_conf_path']}/RAM_Disk_Store/var/db/");
		@rmdir("{$g['cf_conf_path']}/RAM_Disk_Store/var/");
	}

	// Restore RAM Disk Store if updated.
	if ($ramds_updated) {
		exec("/etc/rc.restore_ramdisk_store");
	}
}

/* Previous versions of pfSense had cryptodev built into the kernel.
 * To retain the expected behavior on upgrade, load the cryptodev
 * module for users that did not choose a module.
 */
function upgrade_161_to_162() {
	global $config;
	if (empty($config['system']['crypto_hardware'])) {
		$config['system']['crypto_hardware'] = "cryptodev";
	}
}

/* Traffic graphs widget settings are now stored in a layout similar
 * to other widgets. Migrate any old settings.
 */
function upgrade_162_to_163() {
	require_once("ipsec.inc");
	global $config;

	foreach (array('refreshinterval', 'invert', 'size', 'backgroundupdate') as $setting) {
		if (isset($config['widgets']['trafficgraphs'][$setting])) {
			$config['widgets']['traffic_graphs'][$setting] = $config['widgets']['trafficgraphs'][$setting];
			unset($config['widgets']['trafficgraphs'][$setting]);
		}
	}

	if (isset($config['widgets']['trafficgraphs']['shown'])) {
		if (is_array($config['widgets']['trafficgraphs']['shown']['item'])) {
			$ifdescrs = get_configured_interface_with_descr();

			if (ipsec_enabled()) {
				$ifdescrs['enc0'] = "IPsec";
			}

			$validNames = array();

			foreach ($ifdescrs as $ifdescr => $ifname) {
				array_push($validNames, $ifdescr);
			}

			$config['widgets']['traffic_graphs']['filter'] = implode(',', array_diff($validNames, $config['widgets']['trafficgraphs']['shown']['item']));
		}

		unset($config['widgets']['trafficgraphs']['shown']);
	}
}

/* Dashboard widget settings config format has changed to support having possibly multiple
 * of a widget on the dashboard. Migrate any old settings.
 */
function convert_widget_164($oldname, $newname, $settings_keys) {
	global $config;

	if ($newname == '') {
		$newname = $oldname . '-0';
	}

	if ($oldname == '') {
		// These settings were stored directly in $config['widgets']
		// Move them down under their new key.
		// e.g. $config['widgets']['filterlogentries']
		// becomes $config['widgets']['log-0']['filterlogentries']
		foreach ($settings_keys as $oldkey => $newkey) {
			if ($newkey == '') {
				$newkey = $oldkey;
			}

			// Modify the system-wide entry
			if (isset($config['widgets'][$oldkey])) {
				$config['widgets'][$newname][$newkey] = $config['widgets'][$oldkey];
				unset($config['widgets'][$oldkey]);
			}

			// Modify any user-specific entries
			foreach ($config['system']['user'] as & $user) {
				if (isset($user['widgets'][$oldkey])) {
					$user['widgets'][$newname][$newkey] = $user['widgets'][$oldkey];
					unset($user['widgets'][$oldkey]);
				}
			}
		}
	} else {
		// These settings were stored in some key under 'widgets',
		// e.g. $config['widgets']['gateways_widget']['display_type']
		// becomes $config['widgets']['gateways-0']['display_type']
		foreach ($settings_keys as $oldkey => $newkey) {
			if ($newkey == '') {
				$newkey = $oldkey;
			}

			// Modify the system-wide entry
			if (isset($config['widgets'][$oldname][$oldkey])) {
				$config['widgets'][$newname][$newkey] = $config['widgets'][$oldname][$oldkey];
				unset($config['widgets'][$oldname][$oldkey]);
			}

			// Modify any user-specific entries
			foreach ($config['system']['user'] as & $user) {
				if (isset($user['widgets'][$oldname][$oldkey])) {
					$user['widgets'][$newname][$newkey] = $user['widgets'][$oldname][$oldkey];
					unset($user['widgets'][$oldname][$oldkey]);
				}

				if (isset($user['widgets'][$oldname])) {
					unset($user['widgets'][$oldname]);
				}
			}
		}

		if (isset($config['widgets'][$oldname])) {
			unset($config['widgets'][$oldname]);
		}
	}
}

function upgrade_163_to_164() {
	global $config;

	convert_widget_164('dyn_dns_status', '', array('filter' => ''));
	convert_widget_164('gateways_widget', 'gateways-0', array('display_type' => '', 'gatewaysfilter' => ''));
	convert_widget_164('interface_statistics', '', array('iffilter' => ''));
	convert_widget_164('interfaces', '', array('iffilter' => ''));
	convert_widget_164('', 'log-0',
		array(
			'filterlogentries' => '',
			'filterlogentriesacts' => '',
			'filterlogentriesinterfaces' => '',
			'filterlogentriesinterval' => ''));
	convert_widget_164('openvpn', '', array('filter' => ''));
	convert_widget_164('', 'picture-0', array('picturewidget' => '', 'picturewidget_filename' => ''));
	convert_widget_164('', 'rss-0', array('rssfeed' => '', 'rssmaxitems' => '', 'rsswidgetheight' => '', 'rsswidgettextlength' => ''));
	convert_widget_164('', 'services_status-0', array('servicestatusfilter' => 'filter'));
	convert_widget_164('smart_status', '', array('filter' => ''));
	convert_widget_164('system_information', '', array('filter' => ''));
	convert_widget_164('thermal_sensors_widget', 'thermal_sensors-0',
		array(
			'thermal_sensors_widget_zone_warning_threshold' => '',
			'thermal_sensors_widget_zone_critical_threshold' => '',
			'thermal_sensors_widget_core_warning_threshold' => '',
			'thermal_sensors_widget_core_critical_threshold' => '',
			'thermal_sensors_widget_show_raw_output' => '',
			'thermal_sensors_widget_show_full_sensor_name' => '',
			'thermal_sensors_widget_pulsate_warning' => '',
			'thermal_sensors_widget_pulsate_critical' => ''
		));
	convert_widget_164('wol', 'wake_on_lan-0', array('filter' => ''));
}

/* Work around broken wizard rules. See https://redmine.pfsense.org/issues/7434 */
function upgrade_164_to_165() {
	global $config;
	foreach ($config['filter']['rule'] as & $rule) {
		if ($rule['destination']['port'] == "137-139-137-139") {
			$rule['destination']['port'] = "137-139";
		}
	}
}

/* Fixup digest algorithm selection for OpenVPN clients and servers so they do not use aliased names. */
function upgrade_165_to_166() {
	require_once('openvpn.inc');
	global $config;

	if (isset($config['openvpn']) && is_array($config['openvpn'])) {
		if (is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as &$vpn) {
				$vpn['digest'] = openvpn_remap_digest($vpn['digest']);
			}
		}
		if (is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as &$vpn) {
				$vpn['digest'] = openvpn_remap_digest($vpn['digest']);
			}
		}
	}
}

/* Force the Netgate Services and Support widget to be active on upgrade.
   New widget is added at the top of column 2 */
function upgrade_166_to_167() {
	global $config;

	if (strpos($config['widgets']['sequence'],
	    'netgate_services_and_support') === false) {
		$widgets = explode(",", $config['widgets']['sequence']);
		$cnt = count($widgets);
		$col2 = $cnt;
		$newsequence = array();

		// Locate the firt column 2 widget
		for ($idx=0;$idx<$cnt;$idx++) {
			if (strpos($widgets[$idx], 'col2') !== false) {
				$col2 = $idx;
				break;
			}
		}

		/*
		 * Loop through the widgets inserting the new widget before
		 * the first col2 widget
		 */
		for ($old=0,$new=0;$old<$cnt;$old++,$new++) {
			$newsequence[$new] = $widgets[$old];

			if ($old != ($col2 - 1)) {
				continue;
			}
			$new++;
			$newsequence[$new] =
			    "netgate_services_and_support:col2:open:0";
		}

		$config['widgets']['sequence'] = implode(",", $newsequence);
	}
}

function upgrade_167_to_168() {
	upgrade_166_to_167();
}

function upgrade_168_to_169() {
	global $config;

	/* Remove workaround added in 2.3 */
	unset($config['cron']['rc_update_pkg_metadata']);

	$command = '/usr/bin/nice -n20 /etc/rc.update_pkg_metadata';
	if (!is_array($config['cron'])) {
		$config['cron'] = array();
	}
	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $entry) {
			if ($entry['command'] == $command) {
				return;
			}
		}
	}

	$config['cron']['item'][] = array(
		'minute' => '1',
		'hour' => '0',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => $command
	);
}

/* Upgrade wireless interfaces to the format required for 2.4
 * Each wireless interface now needs to be a cloned instance, the card itself
 * Can no longer be assigned. https://redmine.pfsense.org/issues/6770 */
function upgrade_169_to_170() {
	global $config;
	foreach ($config['interfaces'] as $friendly => & $iface) {
		if (is_array($iface['wireless']) && !empty($iface['wireless']['mode'])) {
			/* This test can only be true for one instance per card, so it is safe. */
			if (stristr($iface['if'], '_wlan') === false) {
				$wlan = array();
				$wlan['if'] = $iface['if'];
				$wlan['mode'] = $iface['wireless']['mode'];
				$wlan['descr'] = "Wireless interface {$friendly}";
				/* It was not possible to create clones of _wlan0 before, so this is safe. */
				$wlan['cloneif'] = "{$iface['if']}_wlan0";
				/* Make sure this entry is placed in the list of wireless interface clones. */
				if (!is_array($config['wireless'])) {
					$config['wireless'] = array();
					$config['wireless']['clone'] = array();
				}
				$config['wireless']['clone'][] = $wlan;
				/* The interface assignment must now be the cloned interface name. */
				$iface['if'] = $wlan['cloneif'];
			}
		}
	}
}

/* Upgrade the VLAN interface names to use $if.$tag instead of $if_vlan$tag.
 * This helps keep the interface names smaller than the limit.
 */
function upgrade_170_to_171() {
	global $config;

	if (!is_array($config['vlans']['vlan']) || count($config['vlans']['vlan']) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($config['vlans']['vlan'] as $id => $vlan) {
		/* Make sure to update the interfaces section with the new name. */
		$vlan_name = "{$vlan['if']}_vlan{$vlan['tag']}";
		foreach ($iflist as $ifname) {
			if ($config['interfaces'][$ifname]['if'] == $vlan_name) {
				$config['interfaces'][$ifname]['if'] = vlan_interface($vlan);
			}
		}
		$config['vlans']['vlan'][$id]['vlanif'] = vlan_interface($vlan);
	}
}

/* Upgrade the QinQ interface names to use $if.$tag instead of $if_$tag.
 * This helps keep the interface names smaller than the limit (but they are still
 * big with the QinQ subtag).
 */
function upgrade_171_to_172() {
	global $config;

	if (!is_array($config['qinqs']['qinqentry']) || count($config['qinqs']['qinqentry']) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($config['qinqs']['qinqentry'] as $id => $qinq) {
		$config['qinqs']['qinqentry'][$id]['vlanif'] = vlan_interface($qinq);

		if (!isset($qinq['members'])) {
			continue;
		}
		foreach (explode(" ", $qinq['members']) as $tag) {
			/* Make sure to update the interfaces section with the new name. */
			$vlan_name = "{$qinq['if']}_{$qinq['tag']}_{$tag}";
			foreach ($iflist as $ifname) {
				if ($config['interfaces'][$ifname]['if'] == $vlan_name) {
					$config['interfaces'][$ifname]['if'] = qinq_interface($qinq, $tag);
				}
			}
		}
	}
}

/*
 * Upgrade the VLAN interface names to use $if.$tag on PPP items
 */
function upgrade_172_to_173() {
	global $config;

	if (!is_array($config['ppps']['ppp']) ||
	    count($config['ppps']['ppp']) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($config['ppps']['ppp'] as $id => $ppp) {
		if (empty($ppp['ports']) ||
		    strpos($ppp['ports'], "_vlan") == false) {
			continue;
		}

		$config['ppps']['ppp'][$id]['ports'] = str_replace('_vlan', '.',
		    $ppp['ports']);
	}
}

/*
 * Dynamic DNS nsupdate keyfiles have been replaced with a simpler ddns-confgen style file.
 */
function upgrade_173_to_174() {
	global $config;

	/* Stop if there is nothing to do. */
	if (!is_array($config['dnsupdates']['dnsupdate'])) {
		return;
	}
	/* Remove unused keytype field. */
	foreach ($config['dnsupdates']['dnsupdate'] as $i => &$dnsupdate) {
		unset($dnsupdate['keytype']);
	}
}

/* IPsec Phase1 now supports multiple authentication ciphers to be specified from the webgui.
 * This is usefull for mobile users using different OS's supporting different ciphers.
 */
function upgrade_174_to_175() {
	global $config;
	init_config_arr(array('ipsec', 'phase1'));
	if (count($config['ipsec']['phase1'])) {
		$a_phase1 = &$config['ipsec']['phase1'];
		foreach($a_phase1 as &$phase1) {
			if (empty($phase1) || !is_array($phase1)) {
				continue;
			}
			$item = array();
			if (isset($phase1['encryption-algorithm']) && !empty($phase1['encryption-algorithm'])) {
				$item['encryption-algorithm'] = $phase1['encryption-algorithm'];
				unset($phase1['encryption-algorithm']);
			}
			if (isset($phase1['hash-algorithm']) && !empty($phase1['hash-algorithm'])) {
				$item['hash-algorithm'] = $phase1['hash-algorithm'];
				unset($phase1['hash-algorithm']);
			}
			if (isset($phase1['dhgroup']) && !empty($phase1['dhgroup'])) {
				$item['dhgroup'] = $phase1['dhgroup'];
				unset($phase1['dhgroup']);
			}
			if (!empty($item)) {
				if (!is_array($phase1['encryption'])) {
					$phase1['encryption'] = array();
				}
				if (!is_array($phase1['encryption']['item'])) {
					$phase1['encryption']['item'] = array();
				}
				$phase1['encryption']['item'][] = $item;
			}
		}
	}
}

/* igmp always was enabled by default if settings were present.
 * So enable it once on upgrade if settings are there.
 * And provide the option through gui to disable it again
 */
function upgrade_175_to_176() {
	global $config;
	if (is_array($config['igmpproxy']['igmpentry']) && (count($config['igmpproxy']['igmpentry']) > 0)) {
		$config['igmpproxy']['enable'] = true;
	}
}

/* Placeholder for a factory update. */
function upgrade_176_to_177() {
}

// The image displayed by the picture widget is now stored on the file system
function upgrade_177_to_178() {
	global $config;

	if (isset($config['widgets'])) {
		$idx = 0;

		while (isset($config['widgets']['picture-' . $idx])) {
			file_put_contents("/conf/widget_image.picture-" . $idx, base64_decode($config['widgets']['picture-' . $idx]['picturewidget']));
			$config['widgets']['picture-' . $idx]['picturewidget'] = "/conf/widget_image.picture-". $idx;
			$idx++;
		}
	}
}

/* Placeholder for a factory update. */
function upgrade_178_to_179() {
}

function upgrade_179_to_180() {
	global $config, $g;

	/* Change default to 400000 to make sure bogonsv6 works */
	if (empty($config['system']['maximumtableentries'])) {
		$config['system']['maximumtableentries'] =
		    $g['minimumtableentries_bogonsv6'];
	}
}

/*
 * Automatically enable retrieving captive portal bandwidth limits from RADIUS for each captive portal
 */
function upgrade_180_to_181() {
	global $config;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cpcfg) {
			if ($cpcfg['auth_method'] == "radius") {
				$config['captiveportal'][$cpzone]['radiusperuserbw'] = true;
			}
		}
	}
}

function upgrade_181_to_182() {
	global $config;

	/*
	 * Some gateways did not have an ipprotocol set, and some configurations
	 * did not have a default set so one was assumed. To avoid leaving the
	 * user without a default, fix these situations first.
	 */
	$defgw_v4_found = false;
	$defgw_v6_found = false;
	$defgw_v4_candidate = array();
	$defgw_v6_candidate = array();
	if (is_array($config['gateways']) && is_array($config['gateways']['gateway_item'])) {
		foreach($config['gateways']['gateway_item'] as &$item) {
			/* Attempt to determine IP protocol for static gateways
			 * missing the protocol definition */
			if (empty($item['ipprotocol'])) {
				if (is_ipaddrv4($item['gateway'])) {
					$item['ipprotocol'] = 'inet';
				} elseif (is_ipaddrv6($item['gateway'])) {
					$item['ipprotocol'] = 'inet6';
				}
			}
			/* Check if we have found a default gw */
			if (isset($item['defaultgw'])) {
				if ($item['ipprotocol'] == 'inet') {
					$defgw_v4_found = true;
				} elseif ($item['ipprotocol'] == 'inet6') {
					$defgw_v6_found = true;
				}
			} else {
				/* This isn't a default gateway, but could it be? */
				if ($item['ipprotocol'] == 'inet') {
					if (!$defgw_v4_found &&
					    ($item['interface'] == "wan")) {
						$defgw_v4_candidate = &$item;
					}
				} elseif ($item['ipprotocol'] == 'inet6') {
					if (!$defgw_v6_found &&
					    ($item['interface'] == "wan")) {
						$defgw_v6_candidate = &$item;
					}
				}
			}
		}
	}
	/* If there was no other default gateway, use the one of last resort. */
	if (!$defgw_v4_found && !empty($defgw_v4_candidate)) {
		$defgw_v4_candidate['defaultgw'] = true;
	}
	if (!$defgw_v6_found && !empty($defgw_v6_candidate)) {
		$defgw_v6_candidate['defaultgw'] = true;
	}

	if (isset($config['system']['gw_switch_default'])) {
		// default gateway switching was enabled, convert gatewaygroup
		$newgroup4 = array();
		$newgroup6 = array();
		$tiernr4 = 2;
		$tiernr6 = 2;
		if (is_array($config['gateways']) && is_array($config['gateways']['gateway_item'])) {
			foreach($config['gateways']['gateway_item'] as &$item) {
				if ($item['ipprotocol'] == 'inet') {
					if (isset($item['defaultgw'])) {
						$tier = 1;
						unset($item['defaultgw']);
					} else {
						$tier = $tiernr4;
					}
					$newgroup4['item'][] = $item['name']."|$tier|address";
					if ($tiernr4 < 5) {
						$tiernr4++;
					}
				}
				if ($item['ipprotocol'] == 'inet6') {
					if (isset($item['defaultgw'])) {
						$tier = 1;
						unset($item['defaultgw']);
					} else {
						$tier = $tiernr6;
					}
					$newgroup6['item'][] = $item['name']."|$tier|address";
					if ($tiernr6 < 5) {
						$tiernr6++;
					}
				}
			}
		}
		if (is_array($newgroup4['item']) && count($newgroup4['item']) > 0) {
			$newname = "Default_Gateway_Group_ipv4";
			if (gateway_or_gwgroup_exists($newname)) { //make sure we create a new name
				$id = 2;
				while (gateway_or_gwgroup_exists($newname."_".$id)) {
					$id++;
				}
				$newname .= "_".$id;
			}
			$newgroup4['name'] = $newname;
			$newgroup4['trigger'] = 0;
			$newgroup4['descr'] = "Default gateway group IPv4";
			$config['gateways']['gateway_group'][] = $newgroup4;
			$config['gateways']['defaultgw4'] = $newname;
		}
		if (is_array($newgroup6['item']) && count($newgroup6['item']) > 0) {
			$newname = "Default_Gateway_Group_ipv6";
			if (gateway_or_gwgroup_exists($newname)) { //make sure we create a new name
				$id = 2;
				while (gateway_or_gwgroup_exists($newname."_".$id)) {
					$id++;
				}
				$newname .= "_".$id;
			}
			$newgroup6['name'] = $newname;
			$newgroup6['trigger'] = 0;
			$newgroup6['descr'] = "Default gateway group IPv6";
			$config['gateways']['gateway_group'][] = $newgroup6;
			$config['gateways']['defaultgw6'] = $newname;
		}
		unset($config['system']['gw_switch_default']);// remove old setting, if a group is used switching is already implied
	} else {
		// set new defaultgw selection boxes to old selected default
		if (is_array($config['gateways']) && is_array($config['gateways']['gateway_item'])) {
			foreach($config['gateways']['gateway_item'] as &$item) {
				if (isset($item['defaultgw'])) {
					if ($item['ipprotocol'] == 'inet') {
						$config['gateways']['defaultgw4'] = $item['name'];
					} else {
						$config['gateways']['defaultgw6'] = $item['name'];
					}
					unset($item['defaultgw']);
				}
			}
		}
	}
}

/* Correct gateway group trigger level values.
 * See https://redmine.pfsense.org/issues/8586
 */
function upgrade_182_to_183() {
	global $config;
	if (!is_array($config['gateways']) ||
	    !is_array($config['gateways']['gateway_group'])) {
		/* No gateway groups, nothing to do. */
		return;
	}
	foreach ($config['gateways']['gateway_group'] as &$gwg) {
		switch ($gwg['trigger']) {
			case "0":
				/* '0' => gettext('Member down'), */
				/* 'down' => gettext("Member Down"), */
				$gwg['trigger'] = "down";
				break;
			case "1":
				/* '1' => gettext('Packet Loss'), */
				/* 'downloss' => gettext("Packet Loss"), */
				$gwg['trigger'] = "downloss";
				break;
			case "2":
				/* '2' => gettext('High Latency'), */
				/* 'downlatency' => gettext("High Latency"), */
				$gwg['trigger'] = "downlatency";
				break;
			case "3":
				/* '3' => gettext('Packet Loss or High latency') */
				/* 'downlosslatency' => gettext("Packet Loss or High Latency")); */
				$gwg['trigger'] = "downlosslatency";
				break;
		}
	}
}

function upgrade_183_to_184() {
	/* 'none' was kinda confusing and didnt really do none
	 * now use the new 'automatic' mode if it was set to none. */
	global $config;
	if ($config['gateways']['defaultgw4'] === "-") {
		$config['gateways']['defaultgw4'] = "";
	}
	if ($config['gateways']['defaultgw6'] === "-") {
		$config['gateways']['defaultgw6'] = "";
	}
}

// Migrate AutoConfigBackup package settings to integtrated ACB system
// and remove package
function upgrade_184_to_185() {
	global $config;

	if (is_array($config['installedpackages']['autoconfigbackup']['config'][0])) {
		$acbpkg = &$config['installedpackages']['autoconfigbackup']['config'][0];

		init_config_arr(array('system', 'acb'));
		$acb = &$config['system']['acb'];
		$acb['enable'] = ($acbpkg['enable_acb'] != 'disabled') ?  'yes':'no';
		$acb['gold_encryption_password'] = $acbpkg['crypto_password'];

		// If no encryption password has been set up yet, we might as well import the "Gold" password
		// The user can update it later
		if (!isset($acb['encryption_password'])) {
			$acb['encryption_password'] = $acbpkg['crypto_password'];
		}

		$acb['gold_password'] = $acbpkg['password'];
		$acb['gold_username'] = $acbpkg['username'];

		unset($config['installedpackages']['autoconfigbackup']['config']);
	}
}

function upgrade_185_to_186() {
	global $config;

	/* FEC LAGG is deprecated, replace with loadbalance */
	if (!function_exists("file_notice")) {
		require_once("notices.inc");
	}
	if (is_array($config['laggs']) &&
	    is_array($config['laggs']['lagg'])) {
		foreach ($config['laggs']['lagg'] as &$lagg) {
			if ($lagg['proto'] == 'fec') {
				$lagg['proto'] = 'failover';
				file_notice("Interfaces", sprintf(gettext("The FEC LAGG protocol is deprecated. The %s LAGG interface has been set to failover."), $lagg['laggif']));
			}
		}
	}
}

function generate_usermanager_radius_config($cpzone, $counter, $protocol, $ip, $key, $port, $radiussrcip_attribute, $is_accounting=false, $accounting_port=false) {
	global $config;
	$pconfig = array();

	if (!is_array($config['system']['authserver'])) {
		$config['system']['authserver'] = array();
	}

	$pconfig['name'] = "Auto generated from Captive Portal {$cpzone}";
	if ($counter != 1) {
		$pconfig['name'] .= " {$counter}";
	}
	$pconfig['radius_srvcs'] = "auth";
	$pconfig['type'] = 'radius';
	$pconfig['radius_protocol'] = $protocol;
	$pconfig['host'] = $ip;
	$pconfig['radius_secret'] = $key;
	$pconfig['radius_timeout'] = 3;
	$pconfig['radius_auth_port'] = $port;
	$pconfig['radius_nasip_attribute'] = $radiussrcip_attribute;

	if($is_accounting) {
		$pconfig['radius_srvcs'] = "both";
		$pconfig['radius_acct_port'] = $accounting_port;
	}

	$config['system']['authserver'][] = $pconfig;

	return 'radius - '.$pconfig['name'];
}

function upgrade_186_to_187() {
	global $config;
	global $g;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cp) {
			// we flush any existing sqlite3 db.
			// It will be automatically re-generated on next captiveportal_readdb()/captiveportal_writedb()
			$db_path = "{$g['vardb_path']}/captiveportal{$cpzone}.db";
			unlink_if_exists($db_path);

			if ($cp['auth_method'] === 'radius') { // Radius Auth
				$auth_servers = array();
				$auth_servers2 = array();
				$radiuscounter = 1;

				if (intval($cp['radiusport']) == 0) {
					$cp['radiusport'] = 1812;
				}
				if (intval($cp['radiusacctport']) == 0) {
					$cp['radiusacctport'] = 1813;
				}
				if (!isset($cp['radiussrcip_attribute'])) {
					$cp['radiussrcip_attribute'] = 'wan';
				}
				$auth_servers[] = generate_usermanager_radius_config($cpzone, $radiuscounter, $cp['radius_protocol'], $cp['radiusip'], $cp['radiuskey'], $cp['radiusport'], $cp['radiussrcip_attribute'], isset($cp['radacct_enable']), $cp['radiusacctport']);

				if (!empty($cp['radiusip2'])) {
					$radiuscounter++;
					if (intval($cp['radiusport2']) == 0) {
						$cp['radiusport2'] = 1812;
					}
					$auth_servers[] = generate_usermanager_radius_config($cpzone, $radiuscounter, $cp['radius_protocol'], $cp['radiusip2'], $cp['radiuskey2'], $cp['radiusport2'], $cp['radiussrcip_attribute'], false, 0);
				}
				if (!empty($cp['radiusip3'])) {
					$radiuscounter++;
					if (intval($cp['radiusport3']) == 0) {
						$cp['radiusport3'] = 1812;
					}
					$auth_servers2[] = generate_usermanager_radius_config($cpzone, $radiuscounter, $cp['radius_protocol'], $cp['radiusip3'], $cp['radiuskey3'], $cp['radiusport3'], $cp['radiussrcip_attribute'], false, 0);
				}
				if (!empty($cp['radiusip4'])) {
					$radiuscounter++;
					if (intval($cp['radiusport4']) == 0) {
						$cp['radiusport4'] = 1812;
					}
					$auth_servers2[] = generate_usermanager_radius_config($cpzone, $radiuscounter, $cp['radius_protocol'], $cp['radiusip4'], $cp['radiuskey4'], $cp['radiusport4'], $cp['radiussrcip_attribute'], false, 0);
				}

				$cp['auth_method'] = 'authserver';
				$cp['auth_server'] = implode(",", $auth_servers);
				$cp['auth_server2'] = implode(",", $auth_servers2);

				if (isset($cp['radmac_enable'])) { // RadMac
					$cp['auth_method'] = 'radmac';
				}
				if (isset($cp['radacct_enable'])) { // If accounting was enabled : we select the primary radius server for accounting
					$cp['radacct_server'] = "Auto generated from Captive Portal {$cpzone}";
					if ($cp['reauthenticateacct'] === "") {
						$cp['reauthenticateacct'] = 'none';
					}
				}
			} elseif ($cp['auth_method'] === 'local') { // Local Auth
				$cp['auth_method'] = 'authserver';
				$cp['auth_server'] = "Local Auth - Local Database";
			}
			// we don't need to update anything when "none" auth method is selected

			$config['captiveportal'][$cpzone] = $cp;
		}
	}
}

function upgrade_187_to_188() {
	global $config;

	$old_cmd = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshlockout";
	$new_cmd = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard";
	if (!is_array($config['cron'])) {
		$config['cron'] = array();
	}
	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}
	if (is_array($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $idx => $entry) {
			if ($entry['command'] == $old_cmd) {
				$config['cron']['item'][$idx]['command'] = $new_cmd;
				break;
			}
		}
	}
}

function upgrade_188_to_189() {
	global $config;

	/* Migrate ssh setting to new location */
	if (isset($config['system']['enablesshd'])) {
		init_config_arr(array('system', 'ssh'));
		$config['system']['ssh']['enable'] = "enabled";
		unset($config['system']['enablesshd']);
	}
	/* Remove accidentally duplicated ssh config
	 * See https://redmine.pfsense.org/issues/8974 */
	if (isset($config['system']['sshd'])) {
		unset($config['system']['sshd']);
	}
}

/* Older pre-existing IPsec P1 entries may not have had the protocol explicitly
 * defined. Fill in the default value of 'inet'.
 * https://redmine.pfsense.org/issues/9207 */
function upgrade_189_to_190() {
	global $config;
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		if (!isset($ph1ent['protocol']) || empty($ph1ent['protocol'])) {
			$ph1ent['protocol'] = 'inet';
		}
	}
}

/* sshguard cron jobs are not necessary.
 * See https://redmine.pfsense.org/issues/9223 */
function upgrade_190_to_191() {
	global $config;
	install_cron_job("/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard", false, null, null, null, null, null, null, false);
	install_cron_job("/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout", false, null, null, null, null, null, null, false);
}

/* Deprecate relayd Load Balancer
 * See https://redmine.pfsense.org/issues/9386 */
function upgrade_191_to_192() {
	global $config;

	/* Backup LB config */
	$backup_file = "/conf/deprecated_load_balancer.xml";
	unlink_if_exists($backup_file);
	file_put_contents($backup_file, backup_config_section('load_balancer'));

	/* Determine if LB was active and notify (or log if not) */
	$deprecation_notice = sprintf(gettext("The built-in Load Balancer service has been deprecated. The active Load Balancer configuration has been stored in %s. Consider migrating to the HAProxy package."), $backup_file);
	if (is_array($config['load_balancer']['virtual_server']) &&
	    count($config['load_balancer']['virtual_server']) &&
	    count($config['load_balancer']['lbpool'])) {

		if (!function_exists("file_notice")) {
			require_once("notices.inc");
		}
		file_notice("Load Balancer", $deprecation_notice);
	} else {
		log_error("INFO: {$deprecation_notice}");
	}

	/* Clear old config */
	unset($config['load_balancer']);

	/* Remove LB HA Sync Config */
	if (isset($config['hasync']) &&
	    is_array($config['hasync']) &&
	    isset($config['hasync']['synchronizelb'])) {
		unset($config['hasync']['synchronizelb']);
	}

	/* If the LB widget is present, remove it*/
	if (isset($config['widgets']) &&
	    isset($config['widgets']['sequence']) &&
	    (strpos($config['widgets']['sequence'], 'load_balancer_status') !== false)) {
		$widgets = explode(',', trim($config['widgets']['sequence']));
		foreach ($widgets as $idx => &$widget) {
			if (substr( $widget, 0, 20 ) === "load_balancer_status") {
				unset($widgets[$idx]);
			}
		}
		$config['widgets']['sequence'] = implode(',', $widgets);
	}

	/* Per-log settings */
	if (isset($config['syslog']) &&
	    is_array($config['syslog']) &&
	    isset($config['syslog']['relayd_settings'])) {
		unset($config['syslog']['relayd_settings']);
	}
}

/* Deprecate growl notifications */
function upgrade_192_to_193() {
	global $config;

	if (isset($config['notifications']['growl'])) {
		unset($config['notifications']['growl']);
	}
}

function upgrade_193_to_194() {
	global $config, $g;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cp) {
			unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");
		}
	}
}

/*
 * Reset all log files, including package logs, on upgrade since old logs are in
 * binary clog format.
 * Conversion is not possible since the clog binary will not be present.
 * https://redmine.pfsense.org/issues/8350
 */
function upgrade_194_to_195() {
	global $g;

	$logfiles = system_syslogd_get_all_logfilenames();

	foreach ($logfiles as $logfile) {
		if (substr($logfile, -4) != '.log') {
			$logfile .= ".log";
		}
		$logpath = "{$g['varlog_path']}/{$logfile}";
		exec("/usr/bin/truncate -s 0 " . escapeshellarg($logpath));
	}
}

/* Skipped. See https://redmine.pfsense.org/issues/9730 */
function upgrade_195_to_196() {
}

/* Add newsyslog cron job */
function upgrade_196_to_197() {
	global $g, $config;

	install_cron_job('/usr/sbin/newsyslog', true, "*/1", '*', '*', '*', '*', 'root', false);
}

/* Add periodic cron jobs */
function upgrade_197_to_198() {
	global $g, $config;

	install_cron_job('/etc/rc.periodic daily',   true, "1",  '3', '*', '*', '*', 'root', false);
	install_cron_job('/etc/rc.periodic weekly',  true, "15", '4', '*', '*', '6', 'root', false);
	install_cron_job('/etc/rc.periodic monthly', true, "30", '5', '1', '*', '*', 'root', false);
}

/* Update IPsec authentication method names
 * https://redmine.pfsense.org/issues/9903 */
function upgrade_198_to_199() {
	global $config;
	/* "RSA" methods changed to the more generic "cert" since they are not only RSA. */
	$namechanges = array(
		'hybrid_rsa_server' => 'hybrid_cert_server',
		'xauth_rsa_server' => 'xauth_cert_server',
		'rsasig' => 'cert',
	);
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as & $ph1ent) {
		/* If the auth method for this P1 is in the list to change, change it */
		if (array_key_exists($ph1ent['authentication_method'], $namechanges)) {
			$ph1ent['authentication_method'] = $namechanges[$ph1ent['authentication_method']];
		}
	}
}

/* Update IPsec reauth and rekey variable names and values based on existing settings
 * https://redmine.pfsense.org/issues/9983
 */
function upgrade_199_to_200() {
	global $config;
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		/* Convert lifetime to reauth_time since that's how previous
		 * versions behaved. */
		if (isset($ph1ent['reauth_enable'])) {
			$ph1ent['reauth_time'] = "0";
			/* If reauth is disabled, set hard P1 lifetime  limit */
			$ph1ent['over_time'] = $ph1ent['lifetime'];
		} else {
			/* Reauth at 90% of requested lifetime, set hard limit at full (relative) lifetime */
			$ph1ent['reauth_time'] = (int)($ph1ent['lifetime'] * 0.90);
			$ph1ent['over_time'] = (int)($ph1ent['lifetime'] * 0.10);
		}
		/* Auto or IKEv2, rekey items */
		if (($ph1ent['iketype'] == 'ikev2') || ($ph1ent['iketype'] == 'auto')) {
			if (isset($ph1ent['rekey_enable'])) {
				/* Disable rekey */
				$ph1ent['rekey_time'] = "0";
				$ph1ent['reauth_time'] = "0";
				/* If rekey is disabled, set hard P1 lifetime limit */
				$ph1ent['over_time'] = $ph1ent['lifetime'];
			} elseif (!empty($ph1ent['margintime'])) {
				/* If margintime is set, use that to calculate over_time and reauth_time */
				$ph1ent['reauth_time'] = ($ph1ent['lifetime'] - $ph1ent['margintime']);
				$ph1ent['over_time'] = $ph1ent['margintime'];
			}
		}
		unset($ph1ent['lifetime'], $ph1ent['reauth_enable'], $ph1ent['rekey_enable'], $ph1ent['margintime']);
	}
}

/* Update LDAP transport values */
function upgrade_200_to_201() {
	global $config;
	/* Normalize/correct names (All are TCP) */
	$namechanges = array(
		'TCP - Standard' => 'Standard TCP',
		'TCP - STARTTLS' => 'STARTTLS Encrypted',
		'SSL - Encrypted' => 'SSL/TLS Encrypted',
	);
	init_config_arr(array('system', 'authserver'));
	foreach ($config['system']['authserver'] as & $authserver) {
		if (array_key_exists($authserver['ldap_urltype'], $namechanges)) {
			$authserver['ldap_urltype'] = $namechanges[$authserver['ldap_urltype']];
		}
	}
}

/* #10525: Handle Chinese (HongKong / Taiwan) locale rename */
function upgrade_201_to_202() {
	global $config;

	if (!empty($config['system']['language'])) {
		if ($config['system']['language'] == 'zh_HK') {
			$config['system']['language'] = 'zh_Hans_HK';
		} elseif ($config['system']['language'] == 'zh_TW') {
			$config['system']['language'] = 'zh_Hant_TW';
		}
	}
}

function upgrade_202_to_203() {
	global $config;
	// Upgrade GREs with IPv6 tunnel networks to new dual stack format
	if (is_array($config['gres']['gre'])) {
		foreach ($config['gres']['gre'] as $idx => &$gre) {
			if (is_ipaddrv6($gre['tunnel-local-addr'])) {
				$gre['tunnel-local-addr6'] = $gre['tunnel-local-addr'];
				$gre['tunnel-remote-addr6'] = $gre['tunnel-remote-addr'];
				$gre['tunnel-remote-net6'] = $gre['tunnel-remote-net'];
				$gre['tunnel-local-addr'] = '';
				$gre['tunnel-remote-addr'] = '';
				$gre['tunnel-remote-net'] = '';
			} else {
				$gre['tunnel-local-addr6'] = '';
				$gre['tunnel-remote-addr6'] = '';
				$gre['tunnel-remote-net6'] = '';
			}
		}
	}
}

/*
 * Change IPsec close_action values
 * See https://redmine.pfsense.org/issues/10632
 */

function upgrade_203_to_204() {
	global $config;
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		if (isset($ph1ent['closeaction'])) {
			switch ($ph1ent['closeaction']) {
				case 'clear':
					/* swanctl.conf combined "clear" and "none" */
					$ph1ent['closeaction'] = "none";
					break;
				case 'restart':
					/* swanctl.conf uses "start" not "restart" */
					$ph1ent['closeaction'] = "start";
					break;
				case 'hold':
					/* swanctl.conf uses "trap" not "hold" */
					$ph1ent['closeaction'] = "trap";
					break;
				default:
					/* "none" does not need changed. */
			}
		}
	}
}

function upgrade_204_to_205() {
	global $config, $g;

	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpzone => $cp) {
			unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");

			if (is_array($config['voucher'][$cpzone])) {
				if (!empty($config['voucher'][$cpzone]['vouchersyncdbip'])) {
					$config['captiveportal'][$cpzone]['enablebackwardsync'] = '';
					$config['captiveportal'][$cpzone]['backwardsyncip'] = $config['voucher'][$cpzone]['vouchersyncdbip'];
					$config['captiveportal'][$cpzone]['backwardsyncuser'] = $config['voucher'][$cpzone]['vouchersyncusername'];
					$config['captiveportal'][$cpzone]['backwardsyncpassword'] = $config['voucher'][$cpzone]['vouchersyncpass'];
				}
			}
		}
	}
}

function upgrade_205_to_206() {
	/*
	 * Trigger a boot loader settings update to make sure the contents will
	 * be updated before the reboot.
	 */
	console_configure();
}

function upgrade_206_to_207() {
	/*
	 * Trigger a boot loader settings update to make sure the contents will
	 * be updated before the reboot.
	 */
	console_configure();
}

function upgrade_207_to_208() {
	global $config;

	$config['system']['hn_altq_enable'] = true;
}

/* Update IPsec VTI to new VTIMAP format 
 * https://redmine.pfsense.org/issues/9592
 */
function upgrade_208_to_209() {
	require_once("interfaces.inc");
	global $config;

	init_config_arr(array('ipsec', 'vtimaps', 'item'));
	if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
				$vtisubnet_spec = ipsec_vti($ph1ent, true, false);
				if (!empty($vtisubnet_spec)) {
					foreach ($vtisubnet_spec as $idx => $vtisub) {
						$config['ipsec']['vtimaps']['item'][] = array(
							"reqid" => $ph1ent['ikeid'],
							"index" => $idx,
							"ifnum" => "{$ph1ent['ikeid']}00{$idx}"
						);
					}
				}
			} else {
				$config['ipsec']['vtimaps']['item'][] = array(
					"reqid" => $ph1ent['ikeid'],
					"index" => "0",
					"ifnum" => "{$ph1ent['ikeid']}000"
				);
			}
		}
	}
}

/*
 * Special function that is called independent of current config version. It's
 * a workaround to have config_upgrade running on older versions after next
 * config version was already taken by newer pfSense.
 *
 * XXX Change the way we handle config version to make it based on product
 *     version
 */
function additional_config_upgrade() {
}

?>
