<?php
/*
 * upgrade_config.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
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
if (!function_exists("get_specialnet")) {
	require_once("/etc/inc/util.inc");
}

/* Upgrade functions must be named:
 *    upgrade_XXX_to_YYY
 * where XXX == previous version, zero padded, and YYY == next version, zero
 * padded
 */
function upgrade_010_to_011() {
	$opti = 1;
	$ifmap = array('lan' => 'lan', 'wan' => 'wan', 'pptp' => 'pptp');
	$if_config = config_get_path('interfaces');

	/* convert DMZ to optional, if necessary */
	if (isset($if_config['dmz'])) {

		$dmzcfg = &$if_config['dmz'];

		if ($dmzcfg['if']) {
			$if_config["opt{$opti}"] = array();
			$optcfg = &$if_config["opt{$opti}"];

			$optcfg['enable'] = $dmzcfg['enable'];
			$optcfg['descr'] = "DMZ";
			$optcfg['if'] = $dmzcfg['if'];
			$optcfg['ipaddr'] = $dmzcfg['ipaddr'];
			$optcfg['subnet'] = $dmzcfg['subnet'];

			$ifmap['dmz'] = "opt" . $opti;
			$opti++;
		}

		unset($if_config['dmz']);
	}

	/* convert WLAN1/2 to optional, if necessary */
	for ($i = 1; isset($if_config["wlan{$i}"]); $i++) {

		if (!$if_config["wlan{$i}"]['if']) {
			unset($if_config["wlan{$i}"]);
			continue;
		}

		$wlancfg = &$if_config["wlan{$i}"];
		$if_config["opt{$opti}"] = array();
		$optcfg = &$if_config["opt{$opti}"];

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

		unset($if_config["wlan{$i}"]);
		$opti++;
	}

	config_set_path('interfaces', $if_config);

	/* convert filter rules */
	config_init_path('filter/rule');
	$filter_rule_config = config_get_path('filter/rule');
	$n = count($filter_rule_config);
	for ($i = 0; $i < $n; $i++) {

		$fr = &$filter_rule_config[$i];

		/* remap interface */
		if (array_key_exists($fr['interface'], $ifmap)) {
			$fr['interface'] = $ifmap[$fr['interface']];
		} else {
			/* remove the rule */
			printf(gettext("%sWarning: filter rule removed " .
				"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
			unset($filter_rule_config[$i]);
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
				unset($filter_rule_config[$i]);
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
				unset($filter_rule_config[$i]);
				continue;
			}
		}
	}

	config_set_path('filter/rule', $filter_rule_config);

	/* convert shaper rules */
	config_init_path('pfqueueing/rule');
	$shaper_rule_config = config_get_path('pfqueueing/rule');
	$n = count($shaper_rule_config);
	for ($i = 0; $i < $n; $i++) {

		$fr = &$shaper_rule_config[$i];

		/* remap interface */
		if (array_key_exists($fr['interface'], $ifmap)) {
			$fr['interface'] = $ifmap[$fr['interface']];
		} else {
			/* remove the rule */
			printf(gettext("%sWarning: traffic shaper rule removed " .
				"(interface '%s' does not exist anymore)."), "\n", $fr['interface']);
			unset($shaper_rule_config[$i]);
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
				unset($shaper_rule_config[$i]);
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
				unset($shaper_rule_config[$i]);
				continue;
			}
		}
	}

	config_set_path('pfqueueing/rule', $shaper_rule_config);
}


function upgrade_011_to_012() {
	/* move LAN DHCP server config */
	$tmp = config_get_path('dhcpd');
	config_init_path('dhcpd/lan');
	config_set_path('dhcpd/lan', $tmp);

	/* encrypt password */
	config_set_path('system/password', password_hash(config_get_path('system/password'), PASSWORD_BCRYPT));
}


function upgrade_012_to_013() {
	/* convert advanced outbound NAT config */
	$onat_rule_config = config_get_path('nat/advancedoutbound/rule');
	for ($i = 0; isset($onat_rule_config[$i]); $i++) {
		$curent = &$onat_rule_config[$i];
		$src = $curent['source'];
		$curent['source'] = array();
		$curent['source']['network'] = $src;
		$curent['destination'] = array();
		$curent['destination']['any'] = true;
	}
	config_set_path('nat/advancedoutbound/rule', $onat_rule_config);

	/* add an explicit type="pass" to all filter rules to make things consistent */
	$filter_rule_config = config_get_path('filter/rule');
	for ($i = 0; isset($filter_rule_config[$i]); $i++) {
		$filter_rule_config[$i]['type'] = "pass";
	}
	config_set_path('nat/advancedoutbound/rule', $filter_rule_config);
}


function upgrade_013_to_014() {
	/* convert shaper rules (make pipes) */
	$shaper_rule_config = config_get_path('pfqueueing/rule');
	if (is_array($shaper_rule_config)) {
		config_init_path('pfqueueing/pipe');
		$shaper_pipe_config = config_get_path('pfqueueing/pipe');

		for ($i = 0; isset($shaper_rule_config[$i]); $i++) {
			$curent = &$shaper_rule_config[$i];

			/* make new pipe and associate with this rule */
			$newpipe = array();
			$newpipe['descr'] = $curent['descr'];
			$newpipe['bandwidth'] = $curent['bandwidth'];
			$newpipe['delay'] = $curent['delay'];
			$newpipe['mask'] = $curent['mask'];
			$shaper_pipe_config[$i] = $newpipe;

			$curent['targetpipe'] = $i;

			unset($curent['bandwidth']);
			unset($curent['delay']);
			unset($curent['mask']);
		}

		config_set_path('pfqueueing/rule', $shaper_rule_config);
		config_set_path('pfqueueing/pipe', $shaper_pipe_config);
	}
}


function upgrade_014_to_015() {
	/* Default route moved */
	$default_gateway_config = config_get_path('interfaces/wan/gateway');
	if (isset($default_gateway_config)) {
		if ($default_gateway_config <> "") {
			config_set_path('system/gateway', $default_gateway_config);
		}
		config_del_path('interfaces/wan/gateway');
	}

	/* Queues are no longer interface specific */
	config_del_path('interfaces/lan/schedulertype');
	config_del_path('interfaces/wan/schedulertype');

	$if_config = config_get_path('interfaces');
	for ($i = 1; isset($if_config["opt{$i}"]); $i++) {
		config_del_path("interfaces/opt{$i}/schedulertype");
	}
}


function upgrade_015_to_016() {
	/* Alternate firmware URL moved */
	$firmware_url_config = config_get_path('system/firmwareurl');
	$firmware_name_config = config_get_path('system/firmwarename');
	if (isset($firmware_url_config) && isset($firmware_name_config)) { // Only convert if *both* are defined.
		$alt_firmware_config = [
			'enabled' => "",
			'firmware_base_url' => $firmware_url_config,
			'firmware_filename' => $firmware_name_config
		];
		config_set_path('system/alt_firmware_url', $alt_firmware_config);
	}
	config_del_path('system/firmwareurl');
	config_del_path('system/firmwarename');
}


function upgrade_016_to_017() {
	/* wipe previous shaper configuration */
	config_del_path('shaper/queue');
	config_del_path('shaper/rule');
	config_del_path('interfaces/wan/bandwidth');
	config_del_path('interfaces/wan/bandwidthtype');
	config_del_path('interfaces/lan/bandwidth');
	config_del_path('interfaces/lan/bandwidthtype');
	config_set_path('shaper/enable', FALSE);
}


function upgrade_017_to_018() {
	$proxyarp_config = config_get_path('proxyarp/proxyarpnet');
	if (is_array($proxyarp_config)) {
		$proxyarp = &$proxyarp_config;
		$vip_config = config_get_path('virtualip/vip', []);
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
			$vip_config[] = $vip;
		}
		config_set_path('virtualip/vip', $vip_config);
		config_del_path('proxyarp');
	}
	$carp_pkg_config = config_get_path('installedpackages/carp/config');
	if (is_array($carp_pkg_config)) {
		$vip_config = config_get_path('virtualip/vip', []);
		foreach ($carp_pkg_config as $carpent) {
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
			$vip_config[] = $vip;
		}
		config_set_path('virtualip/vip', $vip_config);
		config_del_path('installedpackages/carp');
	}
	/* Server NAT is no longer needed */
	config_del_path('nat/servernat');

	/* enable SSH */
	if (config_get_path('version') == "1.8") {
		config_set_path('system/sshenabled', true);
	}
}


function upgrade_018_to_019() {
}


function upgrade_019_to_020() {
	$ipsec_config = config_get_path('ipsec/tunnel');
	if (is_array($ipsec_config)) {
		reset($ipsec_config);
		foreach ($ipsec_config as $idnex => $tunnel) {
			/* Sanity check on required variables */
			/* This fixes bogus <tunnel> entries - remnant of bug #393 */
			if (!isset($tunnel['local-subnet']) && !isset($tunnel['remote-subnet'])) {
				unset($ipsec_config[$tunnel]);
			}
		}
		config_set_path('ipsec/tunnel', $ipsec_config);
	}
}

function upgrade_020_to_021() {
	/* shaper scheduler moved */
	$shaper_config = config_get_path('system/schedulertype');
	if (isset($shaper_config)) {
		config_set_path('shaper/schedulertype', $shaper_config);
		config_del_path('system/schedulertype');
	}
}


function upgrade_021_to_022() {
	/* move gateway to wan interface */
	config_set_path('interfaces/wan/gateway', config_get_path('system/gateway'));
}

function upgrade_022_to_023() {
	config_del_path('shaper');
}


function upgrade_023_to_024() {
}


function upgrade_024_to_025() {
	config_set_path('interfaces/wan/use_rrd_gateway', config_get_path('system/use_rrd_gateway'));
	config_del_path('system/use_rrd_gateway');
}

function upgrade_025_to_026() {
	$cron_config = config_get_path('cron/item');

	$cron_config[] = [
		'minute' => '0',
		'hour' => '*',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 newsyslog'
	];
	$cron_config[] = [
		'minute' => '1,31',
		'hour' => '0-5',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 adjkerntz -a'
	];
	$cron_config[] = [
		'minute' => '1',
		'hour' => '*',
		'mday' => '1',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /etc/rc.update_bogons.sh'
	];
	$cron_config[] = [
		'minute' => '*/60',
		'hour' => '*',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard'
	];
	$cron_config[] = [
		'minute' => '1',
		'hour' => '1',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /etc/rc.dyndns.update'
	];
	$cron_config[] = [
		'minute' => '*/60',
		'hour' => '*',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 virusprot'
	];
	$cron_config[] = [
		'minute' => '*/60',
		'hour' => '*',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /usr/local/sbin/expiretable -t 1800 snort2c'
	];

	config_set_path('cron/item', $cron_config);
}


function upgrade_026_to_027() {
}


function upgrade_027_to_028() {
}


function upgrade_028_to_029() {
	$rule_item = array();
	$rule_item['interface'] = "enc0";
	$rule_item['type'] = "pass";
	$rule_item['source']['any'] = true;
	$rule_item['destination']['any'] = true;
	$rule_item['descr'] = gettext("Permit IPsec traffic.");
	$rule_item['statetype'] = "keep state";
	config_set_path('filter/rule/', $rule_item);
}


function upgrade_029_to_030() {
	/* enable the rrd config setting by default */
	config_set_path('rrd/enable', true);
}


function upgrade_030_to_031() {
	/* Insert upgrade code here */
}


function upgrade_031_to_032() {
	/* Insert upgrade code here */
}


function upgrade_032_to_033() {
	/* Insert upgrade code here */
}


function upgrade_033_to_034() {
	/* Insert upgrade code here */
}


function upgrade_034_to_035() {
	/* Insert upgrade code here */
}


function upgrade_035_to_036() {
	/* Insert upgrade code here */
}


function upgrade_036_to_037() {
	/* Insert upgrade code here */
}


function upgrade_037_to_038() {
	/* Insert upgrade code here */
}


function upgrade_038_to_039() {
	/* Insert upgrade code here */
}


function upgrade_039_to_040() {
	global $g;
	$webgui_config = config_get_path('system/webgui');
	$webgui_config['auth_method'] = "session";
	$webgui_config['backing_method'] = "htpasswd";
	config_set_path('system/webgui', $webgui_config);

	$username_config = config_get_path('system/username', '');
	if (!empty($username_config)) {
		/* Ensure that we follow what this new "admin" username should be in the session. */
		$_SESSION["Username"] = $username_config;

		$group_config = [[
			'name' => 'admins',
			'description' => gettext('System Administrators'),
			'scope' => 'system',
			'priv' => 'page-all',
			'home' => 'index.php',
			'gid' => '110'
		]];
		config_set_path('system/group', $group_config);

		$user_config = [[
			'name' => $username_config,
			'descr' => 'System Administrator',
			'scope' => 'system',
			'groupname' => 'admins',
			'password' => config_get_path('system/password', ''),
			'uid' => '0',
			'priv' => [
				[
					'id' => 'lockwc',
					'name' => 'Lock webConfigurator',
					'descr' => gettext('Indicates whether this user will lock access to the webConfigurator for other users.'),
				],
				[
					'id' => 'lock-ipages',
					'name' => 'Lock individual pages',
					'descr' => gettext('Indicates whether this user will lock individual HTML pages after having accessed a particular page (the lock will be freed if the user leaves or saves the page form).'),
				],
				[
					'id' => 'hasshell',
					'name' => 'Has shell access',
					'descr' => gettext('Indicates whether this user is able to login for example via SSH.'),
				],
				[
					'id' => 'copyfiles',
					'name' => 'Is allowed to copy files',
					'descr' => sprintf(gettext('Indicates whether this user is allowed to copy files onto the %s appliance via SCP/SFTP.'), g_get('product_label')),
				],
				[
					'id' => 'isroot',
					'name' => 'Is root user',
					'descr' => gettext('This user is associated with the UNIX root user (this privilege should only be associated with one single user).'),	
				]
			]
		]];
		config_set_path('system/user', $user_config);

		config_set_path('system/nextuid', '111');
		config_set_path('system/nextgid', '111');

		config_del_path('system/username');
		config_del_path('system/password');
	}
}

function upgrade_040_to_041() {
	$sysctl_config = config_get_path('sysctl');
	if (!$sysctl_config) {
		$sysctl_config['item'] = array();

		$sysctl_config['item'][0]['tunable'] = "net.inet.tcp.blackhole";
		$sysctl_config['item'][0]['descr'] =    gettext("Drop packets to closed TCP ports without returning a RST");
		$sysctl_config['item'][0]['value'] =   "default";

		$sysctl_config['item'][1]['tunable'] = "net.inet.udp.blackhole";
		$sysctl_config['item'][1]['descr'] =    gettext("Do not send ICMP port unreachable messages for closed UDP ports");
		$sysctl_config['item'][1]['value'] =   "default";

		$sysctl_config['item'][2]['tunable'] = "net.inet.ip.random_id";
		$sysctl_config['item'][2]['descr'] =    gettext("Randomize the ID field in IP packets (default is 1: Assign random IP IDs)");
		$sysctl_config['item'][2]['value'] =   "default";

		$sysctl_config['item'][3]['tunable'] = "net.inet.tcp.drop_synfin";
		$sysctl_config['item'][3]['descr'] =    gettext("Drop SYN-FIN packets (breaks RFC1379, but nobody uses it anyway)");
		$sysctl_config['item'][3]['value'] =   "default";

		$sysctl_config['item'][4]['tunable'] = "net.inet.ip.redirect";
		$sysctl_config['item'][4]['descr'] =    gettext("Sending of IPv4 ICMP redirects");
		$sysctl_config['item'][4]['value'] =   "default";

		$sysctl_config['item'][5]['tunable'] = "net.inet6.ip6.redirect";
		$sysctl_config['item'][5]['descr'] =    gettext("Sending of IPv6 ICMP redirects");
		$sysctl_config['item'][5]['value'] =   "default";

		$sysctl_config['item'][6]['tunable'] = "net.inet.tcp.syncookies";
		$sysctl_config['item'][6]['descr'] =    gettext("Generate SYN cookies for outbound SYN-ACK packets");
		$sysctl_config['item'][6]['value'] =   "default";

		$sysctl_config['item'][7]['tunable'] = "net.inet.tcp.recvspace";
		$sysctl_config['item'][7]['descr'] =    gettext("Maximum incoming TCP datagram size");
		$sysctl_config['item'][7]['value'] =   "default";

		$sysctl_config['item'][8]['tunable'] = "net.inet.tcp.sendspace";
		$sysctl_config['item'][8]['descr'] =    gettext("Maximum outgoing TCP datagram size");
		$sysctl_config['item'][8]['value'] =   "default";

		$sysctl_config['item'][9]['tunable'] = "net.inet.tcp.delayed_ack";
		$sysctl_config['item'][9]['descr'] =    gettext("Do not delay ACK to try and piggyback it onto a data packet");
		$sysctl_config['item'][9]['value'] =   "default";

		$sysctl_config['item'][10]['tunable'] = "net.inet.udp.maxdgram";
		$sysctl_config['item'][10]['descr'] =    gettext("Maximum outgoing UDP datagram size");
		$sysctl_config['item'][10]['value'] =   "default";

		$sysctl_config['item'][11]['tunable'] = "net.link.bridge.pfil_onlyip";
		$sysctl_config['item'][11]['descr'] =    gettext("Handling of non-IP packets which are not passed to pfil (see if_bridge(4))");
		$sysctl_config['item'][11]['value'] =   "default";

		$sysctl_config['item'][12]['tunable'] = "net.link.tap.user_open";
		$sysctl_config['item'][12]['descr'] =    gettext("Allow unprivileged access to tap(4) device nodes");
		$sysctl_config['item'][12]['value'] =   "default";

		$sysctl_config['item'][13]['tunable'] = "kern.randompid";
		$sysctl_config['item'][13]['descr'] =    gettext("Randomize PID's (see src/sys/kern/kern_fork.c: sysctl_kern_randompid())");
		$sysctl_config['item'][13]['value'] =   "default";

		$sysctl_config['item'][14]['tunable'] = "net.inet.tcp.inflight.enable";
		$sysctl_config['item'][14]['descr'] =    gettext("The system will attempt to calculate the bandwidth delay product for each connection and limit the amount of data queued to the network to just the amount required to maintain optimum throughput. ");
		$sysctl_config['item'][14]['value'] =   "default";

		$sysctl_config['item'][15]['tunable'] = "net.inet.icmp.icmplim";
		$sysctl_config['item'][15]['descr'] =    gettext("Set ICMP Limits");
		$sysctl_config['item'][15]['value'] =   "default";

		$sysctl_config['item'][16]['tunable'] = "net.inet.tcp.tso";
		$sysctl_config['item'][16]['descr'] =    gettext("TCP Offload engine");
		$sysctl_config['item'][16]['value'] =   "default";

		$sysctl_config['item'][17]['tunable'] = "net.inet.ip.portrange.first";
		$sysctl_config['item'][17]['descr'] =    "Set the ephemeral port range starting port";
		$sysctl_config['item'][17]['value'] =   "default";

		$sysctl_config['item'][18]['tunable'] = "hw.syscons.kbd_reboot";
		$sysctl_config['item'][18]['descr'] =    "Enables ctrl+alt+delete";
		$sysctl_config['item'][18]['value'] =   "default";

		$sysctl_config['item'][19]['tunable'] = "kern.ipc.maxsockbuf";
		$sysctl_config['item'][19]['descr'] =    "Maximum socket buffer size";
		$sysctl_config['item'][19]['value'] =   "default";

		config_set_path('sysctl', $sysctl_config);
	}
}


function upgrade_041_to_042() {
	config_del_path('shaper');
	config_del_path('ezshaper');
}


function upgrade_042_to_043() {
	/* migrate old interface gateway to the new gateways config */
	$gateways_config = config_get_path('gateways/gateway_item');
	$if_config = config_get_path('interfaces');
	$filter_rule_config = config_get_path('filter/rule');
	$iflist = get_configured_interface_list(true);
	$gateways = array();
	$i = 0;
	foreach ($iflist as $ifname => $interface) {
		if (!interface_has_gateway($ifname)) {
			continue;
		}
		$gateways_config[$i] = array();
		if (is_ipaddr($if_config[$ifname]['gateway'])) {
			$gateways_config[$i]['gateway'] = $if_config[$ifname]['gateway'];
			$gateways_config[$i]['descr'] = sprintf(gettext("Interface %s Static Gateway"), $ifname);
		} else {
			$gateways_config[$i]['gateway'] = "dynamic";
			$gateways_config[$i]['descr'] = sprintf(gettext("Interface %s Dynamic Gateway"), $ifname);
		}
		$gateways_config[$i]['interface'] = $ifname;
		$gateways_config[$i]['name'] = "GW_" . strtoupper($ifname);
		/* add default gateway bit for wan on upgrade */
		if ($ifname == "wan") {
			$gateways_config[$i]['defaultgw'] = true;
		}
		if (is_ipaddr($if_config[$ifname]['use_rrd_gateway'])) {
			$gateways_config[$i]['monitor'] = $if_config[$ifname]['use_rrd_gateway'];
			array_del_path($if_config, "{$ifname}/use_rrd_gateway");
		}
		$if_config[$ifname]['gateway'] = $gateways_config[$i]['name'];

		/* Update all filter rules which might reference this gateway */
		$j = 0;
		foreach ($filter_rule_config as &$rule) {
			if (is_ipaddr($rule['gateway'])) {
				if ($rule['gateway'] == $gateways_config[$i]['gateway']) {
					$rule[$j]['gateway'] = $gateways_config[$i]['name'];
				} else if ($rule['gateway'] == $ifname) {
					$rule[$j]['gateway'] = $gateways_config[$i]['name'];
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
	config_set_path('gateways/gateway_item', $gateways_config);
	config_set_path('interfaces', $if_config);
	config_set_path('filter/rule', $filter_rule_config);
}


function upgrade_043_to_044() {
	/* migrate static routes to the new gateways config */
	$gateways = get_gateways(GW_CACHE_DISABLED);
	$i = 0;
	$static_routes_config = config_get_path('staticroutes/route');
	if (is_array($static_routes_config)) {
		config_init_path('gateways/gateway_item');
		$gateways_config = config_get_path('gateways/gateway_item');
		$gwmap = array();
		foreach ($static_routes_config as $idx => $sroute) {
			$found = false;
			foreach ($gateways as $gwname => $gw) {
				if ($gw['gateway'] == $sroute['gateway']) {
					$static_routes_config[$idx]['gateway'] = $gwname;
					$found = true;
					break;
				}
			}
			if ($gwmap[$sroute['gateway']]) {
				/* We already added a gateway name for this IP */
				$static_routes_config[$idx]['gateway'] = "{$gwmap[$sroute['gateway']]}";
				$found = true;
			}

			if ($found == false) {
				$gateway = array();
				$gateway['name'] = "SROUTE{$i}";
				$gwmap[$sroute['gateway']] = $gateway['name'];
				$gateway['gateway'] = $sroute['gateway'];
				$gateway['interface'] = $sroute['interface'];
				$gateway['descr'] = sprintf(gettext("Upgraded static route for %s"), $sroute['network']);
				$gateways_config[] = $gateway;
				$static_routes_config[$idx]['gateway'] = $gateway['name'];
				$i++;
			}
		}
		config_set_path('staticroutes/route', $static_routes_config);
		config_set_path('gateways/gateway_item', $gateways_config);
	}
}


function upgrade_044_to_045() {
	$iflist = get_configured_interface_list(true);
	$vlans_config = config_get_path('vlans/vlan');
	if (is_array($vlans_config) && count($vlans_config)) {
		$if_config = config_get_path('interfaces');
		$i = 0;
		foreach ($vlans_config as $id => $vlan) {
			/* Make sure to update the interfaces section with the right name */
			$vlan_name = "{$vlan['if']}_vlan{$vlan['tag']}";
			foreach ($iflist as $ifname) {
				if ($if_config[$ifname]['if'] == "vlan{$i}") {
					$if_config[$ifname]['if'] = $vlan_name;
					continue;
				}
			}
			$vlans_config[$i]['vlanif'] = "{$vlan_name}";
			$i++;
		}
		config_set_path('vlans/vlan', $vlans_config);
		config_set_path('interfaces', $if_config);
	}
}


function upgrade_045_to_046() {
	$load_balancer_config = config_get_path('load_balancer');
	/* Load up monitors that are in the default config for 2.0 but not in 1.2.3
		thus wouldn't be in an upgraded config. */
	$load_balancer_config['monitor_type'] = array (
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
	if (is_array($load_balancer_config['virtual_server']) && count($load_balancer_config['virtual_server'])) {
		$vs_a = &$load_balancer_config['virtual_server'];
		array_init_path($load_balancer_config, 'lbpool');
		$pool_a = &$load_balancer_config['lbpool'];
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
	config_set_path('load_balancer', $load_balancer_config);
	if (count(config_get_path('load_balancer', [])) == 0) {
		config_del_path('load_balancer');
	}
}


function upgrade_046_to_047() {
	/* Upgrade IPsec from tunnel to phase1/phase2 */

	$ipsec_config = config_get_path('ipsec/tunnel');
	if (is_array($ipsec_config)) {

		$a_phase1 = array();
		$a_phase2 = array();
		$ikeid = 0;

		$virtualip_config = config_get_path('virtualip/vip');
		foreach ($ipsec_config as $tunnel) {

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
					$tunnel['interface'] = "vip{$virtualip_config[$carpid]['vhid']}";
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

		config_del_path('ipsec/tunnel');
		config_set_path('ipsec/phase1', $a_phase1);
		config_set_path('ipsec/phase2', $a_phase2);
	}

	/* Upgrade Mobile IPsec */
	$ipsec_mobile_config = config_get_path('ipsec/mobileclients');
	if (isset($ipsec_mobile_config) &&
	    is_array($ipsec_mobile_config) &&
	    is_array($ipsec_mobile_config['p1']) &&
	    is_array($ipsec_mobile_config['p2'])) {

		if (config_get_path('ipsec/mobileclients') !== null) {
			$ipsec_client_config = config_get_path('ipsec/client');
			$ipsec_client_config['enable'] = true;
			$ipsec_client_config['user_source'] = 'system';
			$ipsec_client_config['group_source'] = 'system';
			config_set_path('ipsec/client', $ipsec_client_config);
		}

		$mobilecfg = $ipsec_mobile_config;

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

		config_set_path('ipsec/phase1/', $a_phase1);
		config_set_path('ipsec/phase2/', $a_phase2);
		config_del_path('ipsec/mobileclients');
	}
}


function upgrade_047_to_048() {
	$dyndns_config = config_get_path('dyndns');
	if (!empty($dyndns_config)) {
		if (isset($dyndns_config[0]['host'])) {
			$tempdyn = array();
			$tempdyn['enable'] = isset($dyndns_config[0]['enable']);
			$tempdyn['type'] = $dyndns_config[0]['type'];
			$tempdyn['wildcard'] = isset($dyndns_config[0]['wildcard']);
			$tempdyn['username'] = $dyndns_config[0]['username'];
			$tempdyn['password'] = $dyndns_config[0]['password'];
			$tempdyn['host'] = $dyndns_config[0]['host'];
			$tempdyn['mx'] = $dyndns_config[0]['mx'];
			$tempdyn['interface'] = "wan";
			$tempdyn['descr'] = sprintf(gettext("Upgraded Dyndns %s"), $tempdyn['type']);
			config_set_path('dyndnses/dyndns', [$tempdyn]);
		}
		config_del_path('dyndns');
	}
	if (!empty(config_get_path('dnsupdate'))) {
		$pconfig = config_get_path('dnsupdate/0');
		if (!$pconfig['ttl']) {
			$pconfig['ttl'] = 60;
		}
		if (!$pconfig['keytype']) {
			$pconfig['keytype'] = "zone";
		}
		$pconfig['interface'] = "wan";
		config_set_path('dnsupdates/dnsupdate/', $pconfig);
		config_del_path('dnsupdate');
	}

	$wan_config = config_get_path('interfaces/wan');
	$pppoe_config = config_get_path('pppoe/0');
	if (is_array($pppoe_config)) {
		config_del_path('pppoe');
		$wan_config['pppoe_username'] = $pppoe_config['username'];
		$wan_config['pppoe_password'] = $pppoe_config['password'];
		$wan_config['provider'] = $pppoe_config['provider'];
		$wan_config['ondemand'] = isset($pppoe_config['ondemand']);
		$wan_config['timeout'] = $pppoe_config['timeout'];
	}
	$pptp_config = config_get_path('pptp');
	if (is_array($pptp_config)) {
		config_del_path('pptp');
		$wan_config['pppoe_username'] = $pptp_config['username'];
		$wan_config['pppoe_password'] = $pptp_config['password'];
		$wan_config['provider'] = $pptp_config['provider'];
		$wan_config['ondemand'] = isset($pptp_config['ondemand']);
		$wan_config['timeout'] = $pptp_config['timeout'];
	}
	config_set_path('interfaces/wan', $wan_config);
}


function upgrade_048_to_049() {
	/* setup new all users group */
	$all = array();
	$all['name'] = "all";
	$all['description'] = gettext("All Users");
	$all['scope'] = "system";
	$all['gid'] = 1998;
	$all['member'] = array();

	config_init_path('system/user');
	$user_config = config_get_path('system/user');
	config_init_path('system/group');
	$group_config = config_get_path('system/group');

	/* work around broken uid assignments */
	$nextuid = 2000;
	foreach ($user_config  as & $user) {
		if (isset($user['uid']) && !$user['uid']) {
			continue;
		}
		$user['uid'] = $nextuid++;
	}
	config_set_path('system/nextuid', $nextuid);

	/* work around broken gid assignments */
	$nextgid = 2000;
	foreach ($group_config as & $group) {
		if ($group['name'] == g_get('admin_group')) {
			$group['gid'] = 1999;
		} else {
			$group['gid'] = $nextgid++;
		}
	}
	config_set_path('system/nextgid', $nextgid);

	/* build group membership information */
	foreach ($group_config as & $group) {
		$group['member'] = array();
		foreach ($user_config as & $user) {
			$groupnames = explode(",", $user['groupname']);
			if (in_array($group['name'], $groupnames)) {
				$group['member'][] = $user['uid'];
			}
		}
	}

	/* reset user group information */
	foreach ($user_config as & $user) {
		unset($user['groupname']);
		$all['member'][] = $user['uid'];
	}

	/* reset group scope information */
	foreach ($user_config as & $group) {
		if ($group['name'] != g_get('admin_group')) {
			$group['scope'] = "user";
		}
	}

	/* insert new all group */
	$group_config = array_merge($group_config, [[$all]]);

	config_set_path('system/user', $user_config);
	config_set_path('system/group', $group_config);
}


function upgrade_049_to_050() {
	config_init_path('system/user');
	$user_config = config_get_path('system/user');
	/* update user privileges */
	foreach ($user_config as & $user) {
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

	config_init_path('system/group');
	$group_config = config_get_path('system/group');
	/* update group privileges */
	foreach ($group_config as & $group) {
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

	config_set_path('system/user', $user_config);
	config_set_path('system/group', $group_config);

	/* sync all local account information */
	local_reset_accounts();
}


function upgrade_050_to_051() {
	$sysctl_config = config_get_path('sysctl/item');
	$pconfig = array();
	$pconfig['descr'] = "Set to 0 to disable filtering on the incoming and outgoing member interfaces.";
	$pconfig['tunable'] = "net.link.bridge.pfil_member";
	$pconfig['value'] = "1";
	$sysctl_config[] = $pconfig;
	$pconfig = array();
	$pconfig['descr'] = "Set to 1 to enable filtering on the bridge interface";
	$pconfig['tunable'] = "net.link.bridge.pfil_bridge";
	$pconfig['value'] = "0";
	$sysctl_config[] = $pconfig;
	config_set_path('sysctl/item', $sysctl_config);
	config_del_path('bridge');

	$convert_bridges = false;
	foreach (config_get_path('interfaces', []) as $intf) {
		if (isset($intf['bridge']) && $intf['bridge'] <> "") {
			config_init_path('bridges/bridged');
			$convert_bridges = true;
			break;
		}
	}
	if ($convert_bridges == true) {
		$bridge_config = config_get_path('bridges/bridged');
		$if_config = config_get_path('interfaces');
		$i = 0;
		foreach ($if_config as $ifr => &$intf) {
			if (isset($intf['bridge']) && $intf['bridge'] <> "") {
				$nbridge = array();
				$nbridge['members'] = "{$ifr},{$intf['bridge']}";
				$nbridge['descr'] = sprintf(gettext("Converted bridged %s"), $ifr);
				$nbridge['bridgeif'] = "bridge{$i}";
				$bridge_config[] = $nbridge;
				unset($intf['bridge']);
				$i++;
			}
		}
		config_set_path('interfaces', $if_config);
		config_set_path('bridges/bridged', $bridge_config);
	}
}


function upgrade_051_to_052() {
	config_set_path('openvpn', []);
	config_init_path('ca');
	config_init_path('cert');

	$vpnid = 1;

	/* openvpn server configurations */
	if (is_array(config_get_path('installedpackages/openvpnserver'))) {
		config_set_path('openvpn/openvpn-server', []);
		$index = 1;
		foreach (config_get_path('installedpackages/openvpnserver/config', []) as $server) {

			if (!is_array($server)) {
				continue;
			}

			if ($server['auth_method'] == "pki") {

				/* create ca entry */
				$ca = array();
				$ca['refid'] = uniqid();
				$ca['descr'] = "OpenVPN Server CA #{$index}";
				$ca['crt'] = $server['ca_cert'];
				config_set_path('ca/', $ca);

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
					config_set_path('crl/', $crl);
					$server['crlref'] = $crl['refid'];
				}
				unset($server['crl']);

				/* create cert entry */
				$cert = array();
				$cert['refid'] = uniqid();
				$cert['descr'] = "OpenVPN Server Certificate #{$index}";
				$cert['crt'] = $server['server_cert'];
				$cert['prv'] = $server['server_key'];
				config_set_path('cert/', $cert);

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
					foreach (config_get_path('interfaces', []) as $iface => $cfgif) {
						if ($cfgif['if'] == $assigned_if) {
							config_set_path("interfaces/{$iface}/if", "ovpns{$server['vpnid']}");
							break;
						}
					}
				}
			}

			config_set_path('openvpn/openvpn-server/', $server);
		}
		config_del_path('installedpackages/openvpnserver');
	}

	/* openvpn client configurations */
	if (is_array(config_get_path('installedpackages/openvpnclient'))) {
		config_set_path('openvpn/openvpn-client', []);

		$index = 1;
		foreach (config_get_path('installedpackages/openvpnclient/config', []) as $client) {

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
				config_set_path('ca/', $ca);

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
				config_set_path('cert/', $cert);

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
					foreach (config_get_path('interfaces', []) as $iface => $cfgif) {
						if ($cfgif['if'] == $assigned_if) {
							config_set_path("interfaces/{$iface}/if", "ovpnc{$client['vpnid']}");
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

			config_set_path('openvpn/openvpn-client/', $client);
		}

		config_del_path('installedpackages/openvpnclient');
	}

	/* openvpn client specific configurations */
	if (is_array(config_get_path('installedpackages/openvpncsc'))) {
		config_set_path('openvpn/openvpn-csc', []);

		foreach (config_get_path('installedpackages/openvpncsc/config', []) as $csc) {

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

			config_set_path('openvpn/openvpn-csc/', $csc);
		}

		config_del_path('installedpackages/openvpncsc');
	}

	if (count(config_get_path('openvpn-server', [])) > 0 ||
	    count(config_get_path('openvpn-client', [])) > 0) {
		config_set_path('filter/rule/', [
			'type' => 'pass',
			'interface' => 'openvpn',
			'statetype' => 'keep state',
			'source' => [
				'any' => true
			],
			'destination' => [
				'any' => true
			],
			'descr' => gettext('Auto added OpenVPN rule from config upgrade.')
		]);
	}

	/*
		* FIXME: hack to keep things working with no installedpackages
		* or carp array in the configuration data.
		*/
	config_init_path('installedpackages/carp');

}


function upgrade_052_to_053() {
	config_init_path('ca');
	config_init_path('cert');

	/* migrate advanced admin page webui ssl to certificate manager */
	$webgui_config = config_get_path('system/webgui');
	if ($webgui_config['certificate'] &&
	    $webgui_config['private-key']) {

		/* create cert entry */
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = "webConfigurator SSL/TLS Certificate";
		$cert['crt'] = $webgui_config['certificate'];
		$cert['prv'] = $webgui_config['private-key'];
		config_set_path('cert/', $cert);

		config_del_path('system/webgui/certificate');
		config_del_path('system/webgui/private-key');
		$webgui_config['ssl-certref'] = $cert['refid'];
	}
	config_set_path('system/webgui', $webgui_config);

	/* migrate advanced admin page ssh keys to user manager */
	$authorizedkeys = config_get_path('system/ssh/authorizedkeys');
	if (!empty($authorizedkeys)) {
		$admin_user = getUserEntryByUID(0);
		if (!empty($admin_user['idx'])) {
			config_set_path("system/user/{$admin_user['idx']}/authorizedkeys", $authorizedkeys);
		}
		config_del_path('system/ssh/authorizedkeys');
	}
}


function upgrade_053_to_054() {
	$loadbalancer_pool_config =  config_get_path('load_balancer/lbpool');
	if (is_array($loadbalancer_pool_config)) {
		$lbpool_arr = $loadbalancer_pool_config;
		$lbpool_srv_arr = array();
		$gateway_group_arr = array();
		$gateways = get_gateways();
		$group_name_changes = array();
		config_init_path('gateways/gateway_item');
		$a_gateways = config_get_path('gateways/gateway_item');
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
		config_set_path('load_balancer/lbpool', $lbpool_srv_arr);
		config_set_path('gateways/gateway_group', $gateway_group_arr);
		config_set_path('gateways/gateway_item', $a_gateways);
	}
	// Unset lbpool if we no longer have any server pools
	if (count($lbpool_srv_arr) == 0) {
		if (empty(config_get_path('load_balancer'))) {
			config_del_path('load_balancer');
		} else {
			config_del_path('load_balancer/lbpool');
		}
	} else {
		config_set_path('load_balancer/lbpool', $lbpool_srv_arr);
	}
	// Only set the gateway group array if we converted any
	if (count($gateway_group_arr) != 0) {
		config_set_path('gateways/gateway_group', $gateway_group_arr);
		// Update any rules that had a gateway change, if any.
		if (count($group_name_changes) > 0) {
			$filter_rule_config =  config_get_path('filter/rule');
			foreach ($filter_rule_config as & $rule) {
				if (!empty($rule["gateway"]) && array_key_exists($rule["gateway"], $group_name_changes)) {
					$rule["gateway"] = $group_name_changes[$rule["gateway"]];
				}
			}
			config_set_path('filter/rule', $filter_rule_config);
		}
	}
}


function upgrade_054_to_055() {
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

		if (is_platform_booting()) {
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

		if (is_platform_booting()) {
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
	if (!is_platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	unlink_if_exists("{$g['vardb_path']}/rrd/*.xml");
	if (is_platform_booting()) {
		echo "Updating configuration...";
	}
}


function upgrade_055_to_056() {
	config_init_path('ca');
	config_init_path('cert');
	$ca_config = config_get_path('ca');
	$cert_config = config_get_path('cert');
	$ipsec_config = config_get_path('ipsec');

	/* migrate ipsec ca's to cert manager */
	if (is_array($ipsec_config['cacert'])) {
		foreach ($ipsec_config['cacert'] as & $cacert) {
			$ca = array();
			$ca['refid'] = uniqid();
			if (is_array($cacert['cert'])) {
				$ca['crt'] = $cacert['cert'][0];
			} else {
				$ca['crt'] = $cacert['cert'];
			}
			$ca['descr'] = $cacert['ident'];
			$ca_config[] = $ca;
		}
		unset($ipsec_config['cacert']);
	}

	/* migrate phase1 certificates to cert manager */
	if (is_array($ipsec_config['phase1'])) {
		foreach ($ipsec_config['phase1'] as & $ph1ent) {
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = "IPsec Peer {$ph1ent['remote-gateway']} Certificate";
			if (is_array($ph1ent['cert'])) {
				$cert['crt'] = $ph1ent['cert'][0];
			} else {
				$cert['crt'] = $ph1ent['cert'];
			}
			$cert['prv'] = $ph1ent['private-key'];
			$cert_config[] = $cert;
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

	config_set_path('ca', $ca_config);
	config_set_path('cert', $cert_config);
	config_set_path('ipsec', $ipsec_config);
}


function upgrade_056_to_057() {
	config_init_path('system/user');
	$user_config = config_get_path('system/user');

	/* migrate captivate portal to user manager */
	if (is_array(config_get_path('captiveportal/user'))) {
		$nextuid = config_get_path('system/nextuid');
		foreach (config_get_path('captiveportal/user', []) as $user) {
			// avoid user conflicts
			$found = false;
			foreach ($user_config as $userent) {
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
			$user['uid'] = $nextuid++;
			$user_config[] = $user;
		}
		config_del_path('captiveportal/user');
		config_set_path('system/nextuid', $nextuid);
		config_set_path('system/user', $user_config);
	}
}

function upgrade_057_to_058() {
	/* set all phase2 entries to tunnel mode */
	$ipsec_phase2_config = config_get_path('ipsec/phase2');
	if (is_array($ipsec_phase2_config)) {
		foreach ($ipsec_phase2_config as & $ph2ent) {
			$ph2ent['mode'] = 'tunnel';
		}
		config_set_path('ipsec/phase2', $ipsec_phase2_config);
	}
}

function upgrade_058_to_059() {
	$schedule_config = config_get_path('schedules/schedule');
	if (is_array($schedule_config)) {
		foreach ($schedule_config as & $schedl) {
			$schedl['schedlabel'] = uniqid();
		}
		config_set_path('schedules/schedule', $schedule_config);
	}
}

function upgrade_059_to_060() {
	require_once("/etc/inc/certs.inc");
	config_init_path('ca');
	config_init_path('cert');
	$ca_config = config_get_path('ca');
	$cert_config = config_get_path('cert');
	if (is_array($ca_config)) {
		/* Locate issuer for all CAs */
		foreach ($ca_config as & $ca) {
			$subject = cert_get_subject($ca['crt']);
			$issuer = cert_get_issuer($ca['crt']);
			if ($issuer <> $subject) {
				$issuer_crt = lookup_ca_by_subject($issuer);
				$issuer_crt = $issuer_crt['item'];
				if ($issuer_crt) {
					$ca['caref'] = $issuer_crt['refid'];
				}
			}
		}

		/* Locate issuer for all certificates */
		if (is_array($cert_config)) {
			foreach ($cert_config as & $cert) {
				$subject = cert_get_subject($cert['crt']);
				$issuer = cert_get_issuer($cert['crt']);
				if ($issuer <> $subject) {
					$issuer_crt = lookup_ca_by_subject($issuer);
					$issuer_crt = $issuer_crt['item'];
					if ($issuer_crt) {
						$cert['caref'] = $issuer_crt['refid'];
					}
				}
			}
		}

		config_set_path('ca', $ca_config);
		config_set_path('cert', $cert_config);
	}
}

function upgrade_060_to_061() {
	$if_config = config_get_path('interfaces');

	if (is_array($if_config['wan'])) {
		config_set_path('interfaces/wan/enable', true);
	}
	if (is_array($if_config['lan'])) {
		config_set_path('interfaces/lan/enable', true);
	}

	/* On 1.2.3 the "mtu" field adjusted MSS.
	   On 2.x the "mtu" field is actually the MTU. Rename accordingly.
	   See redmine ticket #1886
	*/
	foreach ($if_config as $ifr => &$intf) {
		if (isset($intf['mtu']) && is_numeric($intf['mtu'])) {
			$intf['mss'] = $intf['mtu'];
			unset($intf['mtu']);
		}
	}
	config_set_path('interfaces', $if_config);
}

function upgrade_061_to_062() {
	$nat_rule_config = config_get_path('nat/rule');

	/* Convert NAT port forwarding rules */
	if (is_array($nat_rule_config)) {
		$a_nat = &$nat_rule_config;

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
		config_set_path('nat/rule', $nat_rule_config);
	}
}

function upgrade_062_to_063() {
	/* Upgrade legacy Themes to the new pfsense_ng */
	// Not supported in 2.3+

}

function upgrade_063_to_064() {
	$j = 0;
	config_init_path('ppps/ppp');
	config_init_path('interfaces');
	$ppp_config = config_get_path('ppps/ppp');
	$ifcfg = config_get_path('interfaces');
	$cron_config = config_get_path('cron/item');

	if (count($ppp_config)) {
		foreach ($ppp_config as $pppid => &$ppp) {
			$ppp['if'] = "ppp".$j;
			$ppp['ptpid'] = $j;
			$j++;
			if (isset($ppp['port'])) {
				$ppp['ports'] = $ppp['port'];
				unset($ppp['port']);
			}
			if (!isset($ppp['type'])) {
				$ppp['type'] = "ppp";
			}
			if (isset($ppp['defaultgw'])) {
				unset($ppp['defaultgw']);
			}
		}
	}

	$a_ppps = &$ppp_config;

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
				if (is_array($cron_config)) {
					for ($i = 0; $i < count($cron_config); $i++) {
						$item = $cron_config[$i];
						if (strpos($item['command'], "/conf/pppoe{$ifname}restart") !== false) {
							$cron_config[$i]['command'] = "/var/etc/pppoe_restart_" . $ppp['if'];
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

	if ($ppp_config !== null) {
		config_set_path('ppps/ppp', $ppp_config);
	}
	if ($ifcfg !== null) {
		config_set_path('interfaces', $ifcfg);
	}
	if ($cron_config !== null) {
		config_set_path('cron/item', $cron_config);
	}
}

function upgrade_064_to_065() {
	/* Disable TSO and LRO in upgraded configs */
	config_set_path('system/disablesegmentationoffloading', true);
	config_set_path('system/disablelargereceiveoffloading', true);
}

function upgrade_065_to_066() {
	config_init_path('dhcrelay');
	$dhcrelaycfg = config_get_path('dhcrelay');

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
		config_set_path('dhcrelay', $dhcrelaycfg);
	}
}

function upgrade_066_to_067() {
	if (config_get_path('system/ca') !== null) {
		config_set_path('ca', config_get_path('system/ca'));
		config_del_path('system/ca');
	}
	if (config_get_path('system/cert') !== null) {
		config_set_path('cert', config_get_path('system/cert'));
		config_del_path('system/cert');
	}
}

function upgrade_067_to_068() {
	$pppoe_config = config_get_path('pppoe');
	if (!empty($pppoe_config)) {
		config_set_path('pppoes/pppoe/0', $pppoe_config[0]);

		if (is_array($pppoe_config['user'])) {
			$username = array();
			foreach ($pppoe_config['user'] as $user) {
				$usr = $user['name'] . ":" . base64_encode($user['password']);
				if ($user['ip']) {
					$usr .= ":{$user['ip']}";
				}
				$username[] = $usr;
			}
			config_set_path('pppoes/pppoe/0/username', implode(" ", $username));
		}
		config_del_path('pppoe');
	}
}

function upgrade_068_to_069() {
	$user_config = config_get_path('system/user');
	if (!is_array($user_config)) {
		return;
	}

	foreach ($user_config as & $user) {
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
			config_set_path('cert/', $tcert);
		}
		// Replace user certs with cert references instead.
		if (count($rids) > 0) {
			$user['cert'] = $rids;
		}
	}
	config_set_path('system/user', $user_config);
}

function upgrade_069_to_070() {
	$binat_config = config_get_path('nat/onetoone');
	/* Convert NAT 1:1 rules */
	if (is_array($binat_config)) {
		foreach ($binat_config as $nidx => &$natent) {
			if ($natent['subnet'] == 32) {
				$natent[$nidx]['source'] = array("address" => $natent['internal']);
			} else {
				$natent[$nidx]['source'] = array("address" => $natent['internal'] . "/" . $natent['subnet']);
			}

			$natent[$nidx]['destination'] = array("any" => true);

			unset($natent[$nidx]['internal']);
			unset($natent[$nidx]['subnet']);
		}

		unset($natent);
		config_set_path('nat/onetoone', $binat_config);
	}
}

function upgrade_070_to_071() {
	$cron_config = config_get_path('cron/item');

	if (is_array($cron_config)) {
		foreach ($cron_config as $idx => $cronitem) {
			if (stristr($cronitem['command'], "checkreload.sh")) {
				config_del_path("cron/item/{$idx}");
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
	$sysctl_config = config_get_path('sysctl/item');
	if (is_array($sysctl_config)) {
		rename_field($sysctl_config, 'desc', 'descr');
		config_set_path('sysctl/item', $sysctl_config);
	}
}

function upgrade_072_to_073() {
	$loadbalancer_config = config_get_path('load_balancer');
	if (!is_array($loadbalancer_config)) {
		return;
	}
	if (is_array($loadbalancer_config['monitor_type'])) {
		rename_field($loadbalancer_config['monitor_type'], 'desc', 'descr');
	}
	if (is_array($loadbalancer_config['lbpool'])) {
		rename_field($loadbalancer_config['lbpool'], 'desc', 'descr');
	}
	if (is_array($loadbalancer_config['lbaction'])) {
		rename_field($loadbalancer_config['lbaction'], 'desc', 'descr');
	}
	if (is_array($loadbalancer_config['lbprotocol'])) {
		rename_field($loadbalancer_config['lbprotocol'], 'desc', 'descr');
	}
	if (is_array($loadbalancer_config['virtual_server'])) {
		rename_field($loadbalancer_config['virtual_server'], 'desc', 'descr');
	}
	config_set_path('load_balancer', $loadbalancer_config);
}

function upgrade_073_to_074() {
	$user_config = config_get_path('system/user');
	rename_field($user_config, 'fullname', 'descr');
	config_set_path('system/user', $user_config);
}

function upgrade_074_to_075() {
	$ca_config = config_get_path('ca');
	$cert_config = config_get_path('cert');
	$crl_config = config_get_path('crl');
	if (is_array($ca_config)) {
		rename_field($ca_config, 'name', 'descr');
		config_set_path('ca', $ca_config);
	}
	if (is_array($cert_config)) {
		rename_field($cert_config, 'name', 'descr');
		config_set_path('cert', $cert_config);
	}
	if (is_array($crl_config)) {
		rename_field($crl_config, 'name', 'descr');
		config_set_path('crl', $crl_config);
	}
}

function upgrade_075_to_076() {
	$cron_config = config_get_path('cron/item');
	$cron_config[] = [
		'minute' => '30',
		'hour' => '12',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => '/usr/bin/nice -n20 /etc/rc.update_urltables'
	];
	config_set_path('cron/item', $cron_config);
}

function upgrade_076_to_077() {
	$filter_rule_config = config_get_path('filter/rule');
	foreach ($filter_rule_config as & $rule) {
		if (isset($rule['protocol']) && !empty($rule['protocol'])) {
			$rule['protocol'] = strtolower($rule['protocol']);
		}
	}
	config_set_path('filter/rule', $filter_rule_config);
}

function upgrade_077_to_078() {
	$pptpd_config = config_get_path('pptpd');
	if (is_array($pptpd_config) && is_array($pptpd_config['radius']) &&
	    !is_array($pptpd_config['radius']['server'])) {
		$radarr = array();
		$radsvr = array();
		$radsvr['ip'] = $pptpd_config['radius']['server'];
		$radsvr['secret'] = $pptpd_config['radius']['secret'];
		$radsvr['port'] = 1812;
		$radsvr['acctport'] = 1813;
		$radsvr['enable'] = isset($pptpd_config['radius']['enable']);
		$radarr['accounting'] = isset($pptpd_config['radius']['accounting']);
		if ($radarr['accounting']) {
			$radarr['acct_update'] = $radsvr['ip'];
		}
		$radarr['server'] = $radsvr;
		$pptpd_config['radius'] = $radarr;
	}
	if (is_array($pptpd_config)) {
		$pptpd_config['n_pptp_units'] = empty($pptpd_config['n_pptp_units']) ? 16 : $pptpd_config['n_pptp_units'];
	}
	config_set_path('pptpd', $pptpd_config);
}
function upgrade_078_to_079() {
	global $g;
	/* Delete old and unused RRD file */
	unlink_if_exists("{$g['vardb_path']}/rrd/captiveportal-totalusers.rrd");
}

function upgrade_079_to_080() {
	$username_config = config_get_path('system/username');
	$carp_pkg_config = config_get_path('installedpackages/carpsettings/config');
	/* Upgrade config in 1.2.3 specifying a username other than admin for syncing. */
	if (!empty($username_config) && is_array($carp_pkg_config)) {
		$carp_pkg_config[0]['username'] = $username_config;
		config_del_path('system/username');
		config_set_path('installedpackages/carpsettings/config', $carp_pkg_config);
	}
}

function upgrade_080_to_081() {
	global $g;
	/* Welcome to the 2.1 migration path */

	/* tag all the existing gateways as being IPv4 */
	$gateways_config = config_get_path('gateways/gateway_item');
	if (is_array($gateways_config)) {
		foreach ($gateways_config as &$gw) {
			$gw['ipprotocol'] = "inet";
		}
		config_set_path('gateways/gateway_item', $gateways_config);
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

		if (is_platform_booting()) {
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
			$xml = preg_replace("/{$ds_search}/s", "$ds{$ds_search}", $xml);
		}
		foreach ($cdp_arr as $cdp) {
			$xml = preg_replace("/{$cdp_search}/s", "$cdp{$cdp_replace}", $xml);
		}
		foreach ($ds_arr as $ds) {
			$xml = preg_replace("/{$value_search}/s", "$value{$value_replace}", $xml);
		}

		file_put_contents("{$g['tmp_path']}/{$xmldumpnew}", $xml);
		mwexec("$rrdtool restore -f {$g['tmp_path']}/{$xmldumpnew} {$rrddbpath}/{$database} 2>&1");
		unset($xml);
		# Default /tmp tmpfs is ~40mb, do not leave temp files around
		unlink_if_exists("{$g['tmp_path']}/{$xmldump}");
		unlink_if_exists("{$g['tmp_path']}/{$xmldumpnew}");
	}
	if (!is_platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
	if (is_platform_booting()) {
		echo "Updating configuration...";
	}
	$filter_rule_config = config_get_path('filter/rule');
	foreach ($filter_rule_config as & $rule) {
		if (isset($rule['protocol']) && !empty($rule['protocol'])) {
			$rule['protocol'] = strtolower($rule['protocol']);
		}
	}
	unset($rule);
	config_set_path('filter/rule', $filter_rule_config);
}

function upgrade_081_to_082() {
	/* don't enable the allow IPv6 toggle */
}

function upgrade_082_to_083() {
	/* Upgrade captiveportal config */
	$cp_config = config_get_path('captiveportal');
	if (!empty($cp_config)) {
		$cp_config['cpzone'] = $cp_config;
		$cp_config['cpzone']['zoneid'] = 8000;
		$cp_config['cpzone']['zone'] = "cpzone";
		if ($cp_config['cpzone']['auth_method'] == "radius") {
			$cp_config['cpzone']['radius_protocol'] = "PAP";
		}
		config_set_path('captiveportal', $cp_config);
	}
	$voucher_config = config_get_path('voucher');
	if (!empty($voucher_config)) {
		$voucher_config['cpzone'] = $voucher_config;
		config_set_path('voucher', $voucher_config);
	}
}

function upgrade_083_to_084() {
	if (config_get_path('hasync') === null) {
		if (!empty(config_get_path('installedpackages/carpsettings/config'))) {
			config_set_path('hasync', config_get_path('installedpackages/carpsettings/config/0'));
			config_del_path('installedpackages/carpsettings');
		}
		if (empty(config_get_path('installedpackages/carpsettings')) && (config_get_path('installedpackages/carpsettings') !== null)) {
			config_del_path('installedpackages/carpsettings');
		}
		if (empty(config_get_path('installedpackages')) && (config_get_path('installedpackages') !== null)) {
			config_del_path('installedpackages');
		}
	}
}

function upgrade_084_to_085() {
	$gateway_group_arr = array();
	$gateways = get_gateways();
	$oldnames = array();
	/* setup translation array */
	foreach ($gateways as $name => $gw) {
		if (isset($gw['dynamic'])) {
			$oldname = strtoupper(config_get_path("interfaces/{$gw['friendlyiface']}/descr"));
			$oldnames[$oldname] = $name;
		} else {
			$oldnames[$name] = $name;
		}
	}

	/* process the old array */
	if (is_array(config_get_path('gateways/gateway_group'))) {
		$group_array_new = array();
		foreach (config_get_path('gateways/gateway_group', []) as $name => $group) {
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
		config_set_path('gateways/gateway_group', $group_array_new);
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
	$temp_config = config_get_path('');

	/* XXX: Gross hacks in sight */
	if (is_array($temp_config['virtualip']['vip'])) {
		$vipchg = array();
		foreach ($temp_config['virtualip']['vip'] as $vip) {
			if ($vip['mode'] != "carp") {
				continue;
			}
			$temp_config = array_replace_values_recursive(
				$temp_config,
				'^vip' . $vip['vhid'] . '$',
				"{$vip['interface']}_vip{$vip['vhid']}"
			);
		}
		config_set_path('', $temp_config);
	}
}

function upgrade_086_to_087() {
	global $dummynet_pipe_list;

	$shaper_config = config_get_path('dnshaper/queue');
	if (!is_array($shaper_config)) {
		return;
	}

	$dnqueue_number = 1;
	$dnpipe_number = 1;

	foreach ($shaper_config as &$dnpipe) {
		$dnpipe['number'] = $dnpipe_number;
		$dnpipe_number++;
		if (is_array($dnpipe['queue'])) {
			foreach ($dnpipe['queue'] as &$dnqueue) {
				$dnqueue['number'] = $dnqueue_number;
				$dnqueue_number++;
			}
		}
	}

	unset($dnqueue_number, $dnpipe_number, $dnpipe, $dnqueue);
	config_set_path('dnshaper/queue', $shaper_config);

	$filter_rule_config = config_get_path('filter/rule');
	if (!is_array($filter_rule_config)) {
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

	foreach ($filter_rule_config as &$rule) {
		if (!empty($rule['dnpipe'])) {
			if (!empty($dn_list[$rule['dnpipe']])) {
				$rule['dnpipe'] = $dn_list[$rule['dnpipe']];
			}
		}
		if (!empty($rule['pdnpipe'])) {
			if (!empty($dn_list[$rule['pdnpipe']])) {
				$rule['pdnpipe'] = $dn_list[$rule['pdnpipe']];
			}
		}
	}
	config_set_path('filter/rule', $filter_rule_config);
}
function upgrade_087_to_088() {
	if (config_path_enabled('system', 'glxsb_enable')) {
		config_del_path('system/glxsb_enable');
		config_set_path('system/crypto_hardware', 'glxsb');
	}
}

function upgrade_088_to_089() {
	config_init_path('ca');
	config_init_path('cert');
	$ca_config = config_get_path('ca');
	$cert_config = config_get_path('cert');
	$cp_config = config_get_path('captiveportal');

	/* migrate captive portal ssl to certificate manager */
	if (is_array($cp_config)) {
		foreach ($cp_config as &$setting) {
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
					$ca_config[] = $ca;

					/* add ca reference to certificate */
					$cert['caref'] = $ca['refid'];
				}

				$cert_config[] = $cert;

				/* create cert reference */
				$setting['certref'] = $cert['refid'];

				unset($setting['certificate']);
				unset($setting['private-key']);
				unset($setting['cacertificate']);

			}
		}
		config_set_path('ca', $ca_config);
		config_set_path('cert', $cert_config);
		config_set_path('captiveportal', $cp_config);
	}
}

function upgrade_089_to_090() {
	$loadbalancer_config = config_get_path('load_balancer/virtual_server');
	if (is_array($loadbalancer_config) && count($loadbalancer_config)) {
		$vs_a = &$loadbalancer_config;
		for ($i = 0; isset($vs_a[$i]); $i++) {
			if (is_array($vs_a[$i]['pool'])) {
				$vs_a[$i]['poolname'] = $vs_a[$i]['pool'][0];
				unset($vs_a[$i]['pool']);
			} elseif (!empty($vs_a[$i]['pool'])) {
				$vs_a[$i]['poolname'] = $vs_a[$i]['pool'];
				unset($vs_a[$i]['pool']);
			}
		}
		config_set_path('load_balancer/virtual_server', $loadbalancer_config);
	}
}

function upgrade_090_to_091() {
	$shaper_config = config_get_path('dnshaper/queue');

	if (is_array($shaper_config)) {
		foreach ($shaper_config as &$dnqueue) {
			if (!empty($dnqueue['bandwidth'])) {
				$bw = array();
				$bw['bw'] = $dnqueue['bandwidth'];
				$bw['bwscale'] = $dnqueue['bandwidthtype'];
				$bw['bwsched'] = "none";
				$dnqueue['bandwidth']['item'] = [$bw];
			}
		}
		config_set_path('dnshaper/queue', $shaper_config);
	}
}

function upgrade_091_to_092() {
	$nat_rule_config = config_get_path('nat/advancedoutbound/rule');

	if (is_array($nat_rule_config)) {
		$nat_rules = &$nat_rule_config;
		for ($i = 0; isset($nat_rules[$i]); $i++) {
			if (empty($nat_rules[$i]['interface'])) {
				$nat_rules[$i]['interface'] = 'wan';
			}
		}
		config_set_path('nat/advancedoutbound/rule', $nat_rule_config);
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

	if (!is_platform_booting()) {
		enable_rrd_graphing();
	}
}

function upgrade_093_to_094() {
	if (config_get_path('system/powerd_mode') !== null) {
		config_set_path('system/powerd_ac_mode', config_get_path('system/powerd_mode'));
		config_set_path('system/powerd_battery_mode', config_get_path('system/powerd_mode'));
		config_del_path('system/powerd_mode');
	}
}

function upgrade_094_to_095() {
	$if_config = config_get_path('interfaces');
	if (!is_array($if_config)) {
		return;
	}

	foreach ($if_config as &$cfg) {
		if (isset($cfg['ipaddrv6']) && ($cfg['ipaddrv6'] == "track6")) {
			if (!isset($cfg['track6-prefix-id']) || ($cfg['track6-prefix-id'] == "")) {
				$cfg['track6-prefix-id'] = 0;
			}
		}
	}
	config_set_path('interfaces', $if_config);
}

function upgrade_095_to_096() {
	global $g;

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
		if (is_platform_booting()) {
			echo "Update RRD database {$database}.\n";
		}

		$cmd = "{$rrdtool} tune {$rrddbpath}/{$database}";
		foreach ($names as $name) {
			$cmd .= " -a {$name}:{$stream}";
		}
		mwexec("{$cmd} 2>&1");

	}
	if (!is_platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
}

function upgrade_096_to_097() {
	global $g;
	/* If the user had disabled default block rule logging before, then bogon/private network logging was already off, so respect their choice. */
	if (config_path_enabled('syslog', 'nologdefaultblock')) {
		config_set_path('syslog/nologbogons', true);
		config_set_path('syslog/nologprivatenets', true);
	}
}

function upgrade_097_to_098() {
	// no longer used (used to set kill_states)
	return;
}

function upgrade_098_to_099() {
	$dhcpd_config = config_get_path('dhcpd');

	if (!is_array($dhcpd_config)) {
		return;
	}

	foreach ($dhcpd_config as & $dhcpifconf) {
		if (isset($dhcpifconf['next-server'])) {
			$dhcpifconf['nextserver'] = $dhcpifconf['next-server'];
			unset($dhcpifconf['next-server']);
		}
	}
	config_set_path('dhcpd', $dhcpd_config);
}

function upgrade_099_to_100() {
	require_once("/etc/inc/services.inc");
	/* See #7146 for detail on why the extra parameters are needed for the time being. */
	install_cron_job("/usr/bin/nice -n20 newsyslog", false, null, null, null, null, null, null, false);
}

function upgrade_100_to_101() {
	global $g;

	$voucher_config = config_get_path('voucher');

	if (!is_array($voucher_config)) {
		return;
	}

	foreach ($voucher_config as &$cp) {
		if (!is_array($cp['roll'])) {
			continue;
		}
		foreach ($cp['roll'] as &$rcfg) {
			if (!empty($rcfg['comment'])) {
				$rcfg['descr'] = $rcfg['comment'];
			}
		}
	}
	config_set_path('voucher', $voucher_config);
}

function upgrade_101_to_102() {
	global $g;

	$cp_config = config_get_path('captiveportal');
	if (is_array($cp_config)) {
		foreach ($cp_config as &$cp) {
			if (!is_array($cp['passthrumac'])) {
				continue;
			}

			foreach ($cp['passthrumac'] as &$passthrumac) {
				$passthrumac['action'] = 'pass';
			}
		}
		config_set_path('captiveportal', $cp_config);
	}

	/* Convert OpenVPN Compression option to the new style */
	// Nothing to do if there is no OpenVPN tag
	$openvpn_config = config_get_path('openvpn');
	if (is_array($openvpn_config)) {
		if (is_array($openvpn_config['openvpn-server'])) {
			foreach ($openvpn_config['openvpn-server'] as &$vpn) {
				if (!empty($vpn['compression'])) {
					$vpn['compression'] = "adaptive";
				}
			}
		}
		if (is_array($openvpn_config['openvpn-client'])) {
			foreach ($openvpn_config['openvpn-client'] as &$vpn) {
				if (!empty($vpn['compression'])) {
					$vpn['compression'] = "adaptive";
				}
			}
		}
		config_set_path('openvpn', $openvpn_config);
	}
}

function upgrade_102_to_103() {
	if (config_get_path('nat/advancedoutbound') !== null) {
		config_set_path('nat/advancedoutbound/mode', "advanced");
		config_del_path('nat/advancedoutbound/enable');
	} else {
		config_set_path('nat/advancedoutbound/mode', "automatic");
	}

	config_set_path('nat/outbound', config_get_path('nat/advancedoutbound'));
	config_del_path('nat/ipsecpassthru');
	config_del_path('nat/advancedoutbound');
}

function upgrade_103_to_104() {
	$changed_privs = array(
		"page-diag-system-activity" => "page-diagnostics-system-activity",
		"page-interfacess-groups" => "page-interfaces-groups",
		"page-interfacess-lagg" => "page-interfaces-lagg",
		"page-interfacess-qinq" => "page-interfaces-qinq"
	);

	$user_config = config_get_path('system/user', []);
	$group_config = config_get_path('system/group', []);

	/* update user privileges */
	foreach ($user_config as & $user) {
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
	foreach ($group_config as & $group) {
		if (!is_array($group['priv'])) {
			continue;
		}
		foreach ($group['priv'] as & $priv) {
			if (array_key_exists($priv, $changed_privs)) {
				$priv = $changed_privs[$priv];
			}
		}
	}


	config_set_path('system/user', $user_config);
	config_set_path('system/group', $group_config);

	/* sync all local account information */
	local_reset_accounts();
}

function upgrade_104_to_105() {
	$cp_config = config_get_path('captiveportal');

	if (is_array($cp_config)) {
		$zoneid = 2;
		foreach ($cp_config as &$cpcfg) {
			if (empty($cpcfg['zoneid'])) {
				$cpcfg['zoneid'] = $zoneid;
				$zoneid += 2;
			} else if ($cpcfg['zoneid'] > 4000) {
				$cpcfg['zoneid'] = $zoneid;
				$zoneid += 2;
			}
		}
		config_set_path('captiveportal', $cp_config);
	}
}

function upgrade_105_to_106() {
	/* NOTE: This upgrade code was reverted. See redmine ticket #3967 and
	   https://github.com/pfsense/pfsense/commit/6f55af1c25f5232ffe905a90f5f97aad4c87bdfa */
}

function upgrade_106_to_107() {
	$filter_rule_config = config_get_path('filter/rule');

	if (is_array($filter_rule_config)) {
		$tracker = (int)microtime(true);
		foreach ($filter_rule_config as &$rule) {
			if (empty($rule['tracker'])) {
				$rule['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $rule);
		config_set_path('filter/rule', $filter_rule_config);
	}
	$nat_rule_config = config_get_path('nat/rule');
	if (is_array($nat_rule_config)) {
		$tracker = (int)microtime(true);
		foreach ($nat_rule_config as &$rule) {
			if (empty($rule['tracker'])) {
				$rule['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $rule);
		config_set_path('nat/rule', $nat_rule_config);
	}
}

function upgrade_107_to_108() {
	if (config_path_enabled('system/webgui', 'noautocomplete')) {
		config_del_path('system/webgui/noautocomplete');
	} else {
		config_set_path('system/webgui/loginautocomplete', true);
	}
}

function upgrade_108_to_109() {
	$filter_rule_config = config_get_path('filter/rule');

	if (!is_array($filter_rule_config)) {
		return;
	}

	foreach ($filter_rule_config as &$rule) {
		if (!isset($rule['dscp']) || empty($rule['dscp'])) {
			continue;
		}

		$pos = strpos($rule['dscp'], ' ');
		if ($pos !== false) {
			$rule['dscp'] = substr($rule['dscp'], 0, $pos);
		}
		unset($pos);
	}
	config_set_path('filter/rule', $filter_rule_config);
}

function upgrade_109_to_110() {
	$ipsec_phase2_config = config_get_path('ipsec/phase2');

	if (!is_array($ipsec_phase2_config)) {
		return;
	}

	foreach ($ipsec_phase2_config as &$rule) {
		if (!empty($rule['uniqid'])) {
			continue;
		}

		$rule['uniqid'] = uniqid();
	}
	config_set_path('ipsec/phase2', $ipsec_phase2_config);
}

function upgrade_110_to_111() {
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
	$menu_pkg_config = config_get_path('installedpackages/menu');
	if (is_array($menu_pkg_config)) {
		foreach ($menu_pkg_config as $idx => $menu) {
			if ($menu['name'] != 'Unbound DNS') {
				continue;
			}

			unset($menu_pkg_config[$idx]);
			break;
		}
		config_set_path('installedpackages/menu', $menu_pkg_config);
	}

	$service_pkg_config = config_get_path('installedpackages/service');
	if (is_array($service_pkg_config)) {
		foreach ($service_pkg_config as $idx => $service) {
			if ($service['name'] != 'unbound') {
				continue;
			}
			unset($service_pkg_config[$idx]);
			break;
		}
		config_set_path('installedpackages/service', $service_pkg_config);
	}

	$pkg = config_get_path('installedpackages/unbound/config/0');
	if (!isset($pkg)) {
		return;
	}

	$pkg = array_merge($pkg, config_get_path('installedpackages/unboundadvanced/config/0', []));

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

	$unboundacl_pkg_config = config_get_path('installedpackages/unboundacls/config');
	$new['acls'] = array();
	if (is_array($unboundacl_pkg_config)) {
		foreach ($unboundacl_pkg_config as $acl) {
			$new['acls'][] = $acl;
		}
	}

	config_set_path('unbound', $new);
	config_del_path('installedpackages/unbound');
	config_del_path('installedpackages/unboundadvanced');
	config_del_path('installedpackages/unboundacls');

	unset($pkg, $new);
}

function upgrade_111_to_112() {
	$cron_config = config_get_path('cron/item');

	$cron_config[] = array(
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
	if (config_path_enabled('notifications/smtp', 'ssl')) {
		if (config_get_path('notifications/smtp/ssl') == "checked") {
			config_set_path('notifications/smtp/ssl', true);
		} else {
			config_del_path('notifications/smtp/ssl');
		}
	}

	if (config_path_enabled('notifications/smtp', 'tls')) {
		if (config_get_path('notifications/smtp/tls') == "checked") {
			config_set_path('notifications/smtp/tls', true);
		} else {
			config_del_path('notifications/smtp/tls');
		}
	}
}

function upgrade_113_to_114() {
	$ipsec_phase1_config = config_get_path('ipsec/phase1');

	if (!is_array($ipsec_phase1_config)) {
		return;
	}

	foreach ($ipsec_phase1_config as &$ph1ent) {
		if (!isset($ph1ent['iketype'])) {
			$ph1ent['iketype'] = 'ikev1';
		}
	}
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

function upgrade_114_to_115() {
	if (config_get_path('unbound/custom_options') !== null) {
		config_set_path('unbound/custom_options', base64_encode(config_get_path('unbound/custom_options')));
	}
}

function upgrade_115_to_116() {
	$ipsec_phase2_config = config_get_path('ipsec/phase2');

	if (!is_array($ipsec_phase2_config)) {
		return;
	}

	$keyid = 1;
	foreach ($ipsec_phase2_config as &$ph2) {
		$ph2['reqid'] = $keyid;
		$keyid++;
	}
	config_set_path('ipsec/phase2', $ipsec_phase2_config);
}

function upgrade_116_to_117() {
	if (empty(config_get_path('ipsec/client/dns_split'))) {
		return;
	}

	config_set_path('ipsec/client/dns_split',
		preg_replace('/\s*,\s*/', ' ', trim(config_get_path('ipsec/client/dns_split'))));

}

function upgrade_117_to_118() {
	// Unset any old CA and Cert in the system section that might still be there from when upgrade_066_to_067 did not unset them.
	config_del_path('system/ca');
	config_del_path('system/cert');

	config_init_path('ipsec/phase1');
	$a_phase1 = config_get_path('ipsec/phase1');

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
	config_set_path('ipsec/phase1', $a_phase1);
}

function upgrade_118_to_119() {
	if (config_get_path('ipsec/phase1') === null) {
		return;
	}

	// change peerid_type to 'any' for EAP types to retain previous behavior of omitting rightid
	config_init_path('ipsec/phase1');
	$a_phase1 = config_get_path('ipsec/phase1');

	foreach ($a_phase1 as &$ph1_entry) {
		if (strstr($ph1_entry['authentication_method'], 'eap')) {
			$ph1_entry['peerid_type'] = "any";
		}
	}
	config_set_path('ipsec/phase1', $a_phase1);
}

function upgrade_119_to_120() {
	require_once("ipsec.inc");
	global $ipsec_log_cats;

	$ipsec_config = config_get_path('ipsec');
	if (!is_array($ipsec_config)) {
		return;
	}

	// add 1 to configured log levels as part of redmine #5340
	foreach ($ipsec_log_cats as $lkey => $ldescr) {
		if (isset($ipsec_config["ipsec_{$lkey}"])) {
			$ipsec_config["ipsec_{$lkey}"] = $ipsec_config["ipsec_{$lkey}"] + 1;
		}
	}
	config_set_path('ipsec', $ipsec_config);
}


function upgrade_120_to_121() {
	$miniupnpd = config_get_path('installedpackages/miniupnpd/config/0');
	if (!isset($miniupnpd)) {
		return;
	}

	$miniupnpd['row'] = array();

	for ($i = 1; $i <= 4; $i++) {
		if (isset($miniupnpd["permuser{$i}"]) && !empty($miniupnpd["permuser{$i}"])) {
			$miniupnpd['row'][] = array('permuser' => $miniupnpd["permuser{$i}"]);
		}
		unset($miniupnpd["permuser{$i}"]);
	}
	config_set_path('installedpackages/miniupnpd/config/0', $miniupnpd);
}

function upgrade_121_to_122() {
	$user_config = config_get_path('system/user', []);
	foreach ($user_config as &$user) {
		if (isset($user['nt-hash'])) {
			unset($user['nt-hash']);
		}
	}
	config_set_path('system/user', $user_config);
}

function upgrade_122_to_123() {
	// PPTP server was removed
	config_del_path('pptpd');

	$filter_rules_config = config_get_path('filter/rule');
	// Cleanup firewall rules
	if (is_array($filter_rules_config)) {
		$rules = &$filter_rules_config;
		$last_rule = count($rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($rules[$i]['interface']) && $rules[$i]['interface'] == 'pptp') {
				unset($rules[$i]);
				continue;
			}
			if (isset($rules[$i]['source']['network']) && $rules[$i]['source']['network'] == 'pptp') {
				unset($rules[$i]);
				continue;
			}
			if (isset($rules[$i]['destination']['network']) && $rules[$i]['destination']['network'] == 'pptp') {
				unset($rules[$i]);
				continue;
			}
		}
		config_set_path('filter/rule', $filter_rules_config);
	}

	$binat_config = config_get_path('nat/onetoone');
	// Cleanup 1:1 NAT rules
	if (is_array($binat_config)) {
		$onetoone = &$binat_config;
		$last_rule = count($onetoone) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($onetoone[$i]['interface']) && $onetoone[$i]['interface'] == 'pptp') {
				unset($onetoone[$i]);
				continue;
			}
			if (isset($onetoone[$i]['source']['network']) && $onetoone[$i]['source']['network'] == 'pptp') {
				unset($onetoone[$i]);
				continue;
			}
			if (isset($onetoone[$i]['destination']['network']) && $onetoone[$i]['destination']['network'] == 'pptp') {
				unset($onetoone[$i]);
				continue;
			}
		}
		config_set_path('nat/onetoone', $binat_config);
	}

	$npt_config = config_get_path('nat/npt');
	// Cleanup npt NAT rules
	if (is_array($npt_config)) {
		$npt = &$npt_config;
		$last_rule = count($npt) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($npt[$i]['interface']) && $npt[$i]['interface'] == 'pptp') {
				unset($npt_config[$i]);
				continue;
			}
		}
		config_set_path('nat/npt', $npt_config);
	}

	$nat_rule_config = config_get_path('nat/rule');
	// Cleanup Port-forward NAT rules
	if (is_array($nat_rule_config)) {
		$nat_rules = &$nat_rule_config;
		$last_rule = count($nat_rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($nat_rules[$i]['interface']) && $nat_rules[$i]['interface'] == 'pptp') {
				unset($nat_rules[$i]);
				continue;
			}
			if (isset($nat_rules[$i]['source']['network']) && $nat_rules[$i]['source']['network'] == 'pptp') {
				unset($nat_rules[$i]);
				continue;
			}
			if (isset($nat_rules[$i]['destination']['network']) && $nat_rules[$i]['destination']['network'] == 'pptp') {
				unset($nat_rules[$i]);
				continue;
			}
		}
		config_set_path('nat/rule', $nat_rule_config);
	}

	$onat_rule_config = config_get_path('nat/outbound/rule');
	// Cleanup Port-forward NAT rules
	if (is_array($onat_rule_config)) {
		$out_rules = &$onat_rule_config;
		$last_rule = count($out_rules) - 1;
		// Process in reverse order to be able to unset items
		for ($i = $last_rule; $i >= 0; $i--) {
			if (isset($out_rules[$i]['interface']) && $out_rules[$i]['interface'] == 'pptp') {
				unset($out_rules[$i]);
				continue;
			}
		}
		config_set_path('nat/rule', $onat_rule_config);
	}
}

function upgrade_123_to_124() {
	config_del_path('system/altpkgrepo');
	config_del_path('theme');
}

function upgrade_124_to_125() {
	/* Find interfaces with WEP configured. */
	foreach (config_get_path('interfaces', []) as $ifname => $intf) {
		if (!is_array($intf['wireless'])) {
			continue;
		}

		/* Generate a notice, disable interface, remove WEP settings */
		if (isset($intf['wireless']['wep']['enable'])) {
			if (!function_exists("file_notice")) {
				require_once("notices.inc");
			}
			file_notice("WirelessSettings", sprintf(gettext("WEP is no longer supported. It will be disabled on the %s interface and the interface will be disabled. Please reconfigure the interface."), $ifname));
			config_del_path("interfaces/{$ifname}/wireless/wep");
			if (isset($intf['enable'])) {
				config_del_path("interfaces/{$ifname}/enable");
			}
		}
	}
}

function upgrade_125_to_126() {
	require_once("ipsec.inc");
	global $ipsec_log_cats, $ipsec_log_sevs;

	$ipsec_config = config_get_path('ipsec');
	$def_loglevel = 1;
	if (!is_array(config_get_path('ipsec'))) {
		return;
	}

	config_init_path('ipsec/logging');

	/* subtract 2 from ipsec log levels. the value stored in the config.xml
	 * will now match the strongswan level exactly.
	 */
	foreach (array_keys($ipsec_log_cats) as $cat) {
		if (!isset($ipsec_config["ipsec_{$cat}"])) {
			$new_level = $def_loglevel;
		} else {
			$new_level = intval($ipsec_config["ipsec_{$cat}"]) - 2;
		}

		if (in_array($new_level, array_keys($ipsec_log_sevs))) {
			$ipsec_config['logging'][$cat] = $new_level;
		} else {
			$ipsec_config['logging'][$cat] = $def_loglevel;
		}
		unset($ipsec_config["ipsec_{$cat}"]);
	}
	config_set_path('ipsec', $ipsec_config);
}

// prior to v2.3 <widgets><sequence> contains a list of widgets with display types:
//		none, close, hide, & show
// v2.3 & later uses:
//		close & open
// widgets not in use are simply not in the list
function upgrade_126_to_127() {
	$widgets_config = config_get_path('widgets/sequence');

	if (!isset($widgets_config)) {
		return;
	}

	$cur_widgets = explode(',', trim($widgets_config));
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

	$widgets_config = implode(',', $new_widgets);
	config_set_path('widgets/sequence', $widgets_config);

}

function upgrade_127_to_128() {
	// If bindip is not already specified then migrate the old SNMP bindlan flag to a bindip setting
	if (config_get_path('snmpd/bindlan') !== null) {
		if (config_get_path('snmpd/bindip') === null) {
			config_set_path('snmpd/bindip', 'lan');
		}
		config_del_path('snmpd/bindlan');
	}
}

function upgrade_128_to_129() {
	/* net.inet.ip.fastforwarding does not exist in 2.3. */
	if (!is_array(config_get_path('sysctl/item'))) {
		return;
	}

	foreach (config_get_path('sysctl/item', []) as $idx => $sysctl) {
		if ($sysctl['tunable'] == "net.inet.ip.fastforwarding") {
			config_del_path("sysctl/item/{$idx}");
		}
		if ($sysctl['tunable'] == "net.inet.ipsec.debug") {
			config_set_path("sysctl/item/{$idx}value", "0");
		}
	}

	/* IPSEC is always on in 2.3. */
	if (config_path_enabled('ipsec')) {
		config_del_path('ipsec/enable');
	} else if (is_array(config_get_path('ipsec/phase1'))) {
		/*
		 * If IPsec was globally disabled, disable all
		 * phase1 entries
		 */
		foreach (config_get_path('ipsec/phase1', []) as $idx => $p1) {
			config_set_path("ipsec/phase1/{$idx}/disabled", true);
		}
	}
}

function upgrade_129_to_130() {
	$openvpn_config = config_get_path('openvpn/openvpn-server');
	/* Change OpenVPN topology_subnet checkbox into topology multi-select #5526 */
	if (is_array($openvpn_config)) {
		foreach ($openvpn_config as & $serversettings) {
			if (strtolower($serversettings['topology_subnet']) == "yes") {
				unset($serversettings['topology_subnet']);
				$serversettings['topology'] = "subnet";
			} else {
				$serversettings['topology'] = "net30";
			}
		}
	}
	config_set_path('openvpn/openvpn-server', $openvpn_config);
}

function upgrade_130_to_131() {
	// Default dpinger parameters at time of this upgrade (2.3)
	$default_interval = 500;
	$default_alert_interval = 1000;
	$default_loss_interval = 2000;
	$default_time_period = 60000;

	if (config_get_path('syslog/apinger') !== null) {
		config_set_path('syslog/dpinger', true);
		config_del_path('syslog/apinger');
	}
	config_del_path('system/apinger_debug');

	$gateways_config = config_get_path('gateways/gateway_item');
	if (!is_array($gateways_config)) {
		return;
	}

	if (is_array($gateways_config)) {
		foreach ($gateways_config as &$gw) {
			// dpinger uses milliseconds
			if (isset($gw['interval']) &&
				is_numeric($gw['interval'])) {
				$gw['interval'] = $gw['interval'] * 1000;
			}

			if (!empty($gw['interval'])) {
				$effective_interval = $gw['interval'];
			} else {
				$effective_interval = $default_interval;
			}

			if (isset($gw['down']) &&
				is_numeric($gw['down'])) {
				$gw['time_period'] = $gw['down'] * 1000;
				unset($gw['down']);
			}

			if (!empty($gw['time_period'])) {
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

			if (!empty($gw['loss_interval'])) {
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
		config_set_path('gateways/gateway_item', $gateways_config);
	}
}

function upgrade_131_to_132() {
	if (config_path_enabled('system', 'usefifolog')) {
		config_del_path('system/usefifolog');
		clear_all_log_files(false);
	}
}

function upgrade_132_to_133() {
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	if (is_array($ipsec_phase1_config)) {
		foreach ($ipsec_phase1_config as &$p1) {
			if (isset($p1['encryption-algorithm']['name']) &&
			    $p1['encryption-algorithm']['name'] == 'des') {
				$p1['disabled'] = true;
				file_notice("IPsec",
				    sprintf(gettext("DES is no longer supported, IPsec phase 1 item '%s' is being disabled."), $p1['descr']));
			}
		}
		config_set_path('ipsec/phase1', $ipsec_phase1_config);
	}

	$ipsec_phase2_config = config_get_path('ipsec/phase2');
	if (is_array($ipsec_phase2_config)) {
		foreach ($ipsec_phase2_config as &$p2) {
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
		config_set_path('ipsec/phase2', $ipsec_phase2_config);
	}
}

// Determine the highest column number in use and set dashboardcolumns accordingly
function upgrade_133_to_134() {
	if (!config_path_enabled('widgets', 'sequence') || config_path_enabled('system/webgui', 'dashboardcolumns')) {
		return;
	}

	$cur_widgets = explode(',', trim(config_get_path('widgets/sequence')));
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

	config_set_path('system/webgui/dashboardcolumns', ($maxcols % 10));
}

function upgrade_134_to_135() {
	if (config_path_enabled('syslog', 'nologlighttpd')) {
		config_del_path('syslog/nologlighttpd');
		config_set_path('syslog/nolognginx', true);
	}
}

function upgrade_135_to_136() {
	$l7_active = false;
	if (config_get_path('l7shaper') !== null) {
		config_del_path('l7shaper');
		$filter_rule_config = config_get_path('filter/rule');
		if (is_array($filter_rule_config)) {
			foreach ($filter_rule_config as &$rule) {
				if (isset($rule['l7container'])) {
					unset($rule['l7container']);
					$l7_active = true;
				}
			}
			config_set_path('filter/rule', $filter_rule_config);
		}
		if ($l7_active) {
			file_notice("L7shaper", gettext("Layer 7 shaping is no longer supported. Its configuration has been removed."));
		}
	}
}

function upgrade_136_to_137() {
	$dhcpd_config = config_get_path('dhcpd');
	if (is_array($dhcpd_config)) {
		foreach ($dhcpd_config as &$dhcpd) {
			if (!is_array($dhcpd['numberoptions']['item'])) {
				continue;
			}

			foreach ($dhcpd['numberoptions']['item'] as &$item) {
				$item['value'] = base64_encode($item['value']);
			}
		}
		config_set_path('dhcpd', $dhcpd_config);
	}

	$dhcpdv6_config = config_get_path('dhcpdv6');
	if (is_array($dhcpdv6_config)) {
		foreach ($dhcpdv6_config as &$dhcpdv6) {
			if (!is_array($dhcpdv6['numberoptions']['item'])) {
				continue;
			}

			foreach ($dhcpdv6['numberoptions']['item'] as &$item) {
				$item['value'] = base64_encode($item['value']);
			}
		}
		config_set_path('dhcpdv6', $dhcpdv6_config);
	}
}

function upgrade_137_to_138() {
	// the presence of unityplugin tag used to disable loading of unity plugin
	// it's now disabled by default, and config tag is to enable. Unset accordingly.
	config_del_path('ipsec/unityplugin');
}

function upgrade_138_to_139() {
	// clean up state killing on gateway failure. having kill_states set used to mean it was disabled
	// now set gw_down_kill_states if enabled.
	if (!config_path_enabled('system', 'kill_states')) {
		config_set_path('system/gw_down_kill_states', true);
	} else {
		config_del_path('system/kill_states');
	}
}

function upgrade_139_to_140() {
	$virtualip_config = config_get_path('virtualip/vip');
	if (is_array($virtualip_config)) {
		foreach ($virtualip_config as &$vip) {
			if ($vip['mode'] == "carp") {
				if (!isset($vip['uniqid'])) {
					$vip['uniqid'] = uniqid();
				}
			}
		}
		config_set_path('virtualip/vip', $virtualip_config);
	}
}

function upgrade_140_to_141() {
	$openvpn_config = config_get_path('openvpn/openvpn-client');
	// retain OpenVPN's net30 default topology for upgraded client configs so they still work
	// This is for 2.3 ALPHA to a later 2.3, not 2.2.x upgrades, which had no topology setting on clients
	if (is_array($openvpn_config)) {
		foreach ($openvpn_config as &$ovpnclient) {
			if (!isset($ovpnclient['topology'])) {
				$ovpnclient['topology'] = "net30";
			}
		}
		config_set_path('openvpn/openvpn-client', $openvpn_config);
	}

	$filter_rule_config = config_get_path('filter/rule');
	// repeat addition of filter tracker IDs from 106_to_107 where missing since associated filter rules were missing them
	if (is_array($filter_rule_config)) {
		$tracker = (int)microtime(true);
		foreach ($filter_rule_config as &$rule) {
			if (empty($rule['tracker'])) {
				$rule['tracker'] = $tracker;
				$tracker++;
			}
		}
		unset($tracker, $rule);
		config_set_path('filter/rule', $filter_rule_config);
	}

}

function upgrade_141_to_142() {
	/* Convert Namecheap type DynDNS entries to the new split hostname and domain format */

	config_init_path('dyndnses/dyndns');
	$a_dyndns = config_get_path('dyndnses/dyndns');

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
	config_set_path('dyndnses/dyndns', $a_dyndns);

	/* unset old pppoerestart cron job if it exists. redmine 1905 */
	$cron_config = config_get_path('cron/item');
	if (is_array($cron_config)) {
		foreach ($cron_config as $idx => $cronitem) {
			if ($cronitem['command'] == "/etc/pppoerestart") {
				unset($cron_config[$idx]);
			}
		}
		config_set_path('cron/item', $cron_config);
	}
}

// Updated to check for empty separator definitions via is_array()
function upgrade_142_to_143() {
	$filter_config = config_get_path('filter');

	/* Re-index firewall rule separators per interface */
	if (is_array($filter_config['separator'])) {
		foreach ($filter_config['separator'] as &$separators) {

			if (is_array($separators)) {
				foreach ($separators as $sepn => &$separator) {

					$seprow = substr($separator['row']['0'], 2);
					$sepif  = $separator['if'];

					// Determine position of separator within the interface rules.
					$i = -1; $j = 0;
					foreach ($filter_config['rule'] as &$filterent) {

						if ($i == $seprow) {
							// Set separator row to it's position within the interface rules.
							$filter_config['separator'][$sepif][$sepn]['row'] = 'fr' . $j;
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
		unset($separators, $separator, $filterent);
		config_set_path('filter', $filter_config);
	}

	$nat_separator_config = config_get_path('nat/separator');
	/* Re-index nat rule separators */
	if (is_array($nat_separator_config)) {
		foreach ($nat_separator_config as &$separator) {
			if (is_array($separator)) {
				$seprow = substr($separator['row']['0'], 2);
				$separator['row'] = 'fr' . ($seprow + 1);
			}
		}
		config_set_path('nat/separator', $nat_separator_config);
	}
}

function get_vip_from_ip_alias($ipalias) {
	foreach (config_get_path('virtualip/vip', []) as $idx => $vip) {
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
	foreach (config_get_path('virtualip/vip', []) as $idx => $vip) {
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
	$virtualip_config = config_get_path('virtualip/vip');
	if (is_array($virtualip_config)) {
		foreach ($virtualip_config as &$vip) {
			if ($vip['mode'] == "ipalias") {
				if (!isset($vip['uniqid'])) {
					$vip['uniqid'] = uniqid();
				}
			}
		}
		unset($vip);
		config_set_path('virtualip/vip', $virtualip_config);
	}

	/* Convert IPsec phase 1 entries. */
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	if (is_array($ipsec_phase1_config)) {
		foreach ($ipsec_phase1_config as &$ph1ent) {
			if (is_ipaddr($ph1ent['interface']) || is_ipaddrv6($ph1ent['interface'])) {
				$ph1ent['interface'] = get_vip_from_ip_alias($ph1ent['interface']);
			} else if (strpos($ph1ent['interface'], "_vip")) {
				$ph1ent['interface'] = get_vip_from_oldcarp($ph1ent['interface']);
			}
		}
		unset($ph1ent);
		config_set_path('ipsec/phase1', $ipsec_phase1_config);
	}

	/* Convert openvpn. */
	$openvpn_server_config = config_get_path('openvpn/openvpn-server');
	if (is_array($openvpn_server_config)) {
		foreach ($openvpn_server_config as &$ovpn) {
			if (empty($ovpn['interface'])) {
				continue;
			}
			if (is_ipaddr($ovpn['interface']) || is_ipaddrv6($ovpn['interface'])) {
				$ovpn['interface'] = get_vip_from_ip_alias($ovpn['interface']);
			} else if (strpos($ovpn['interface'], "_vip")) {
				$ovpn['interface'] = get_vip_from_oldcarp($ovpn['interface']);
			}
		}
		unset($ovpn);
		config_set_path('openvpn/openvpn-server', $openvpn_server_config);
	}
	$openvpn_client_config = config_get_path('openvpn/openvpn-client');
	if (is_array($openvpn_client_config)) {
		foreach ($openvpn_client_config as &$ovpn) {
			if (empty($ovpn['interface'])) {
				continue;
			}
			if (is_ipaddr($ovpn['interface']) || is_ipaddrv6($ovpn['interface'])) {
				$ovpn['interface'] = get_vip_from_ip_alias($ovpn['interface']);
			} else if (strpos($ovpn['interface'], "_vip")) {
				$ovpn['interface'] = get_vip_from_oldcarp($ovpn['interface']);
			}
		}
		unset($ovpn);
		config_set_path('openvpn/openvpn-client', $openvpn_client_config);
	}

	/* Convert unbound. */
	$unbound_if_config = config_get_path('unbound/active_interface');
	if (!empty($unbound_if_config)) {
		$active_ifs = explode(",", $unbound_if_config);
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
		$unbound_if_config = implode(",", $ifs);
		config_set_path('unbound/active_interface', $unbound_if_config);
	}

	/* Convert dnsmasq. */
	$dnsmasq_if_config = config_get_path('dnsmasq/interface');
	if (!empty($dnsmasq_if_config)) {
		$active_ifs = explode(",", $dnsmasq_if_config);
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
		$dnsmasq_if_config = implode(",", $ifs);
		config_set_path('dnsmasq/interface', $dnsmasq_if_config);
	}
}

function upgrade_144_to_145() {
	$if_config = config_get_path('interfaces');
	$dhcpdv6_config = config_get_path('dhcpdv6');

	// Enable DHCPv6 server and radvd config for track6 interfaces,
	// matching what used to be automatically enabled with no user
	// configurability.
	if (is_array($if_config)) {
		foreach ($if_config as $ifname => $ifcfg) {
			if (isset($ifcfg['enable'])) {
				if ($ifcfg['ipaddrv6'] == "track6") {
					$dhcpdv6_config[$ifname]['enable'] = true;
					$dhcpdv6_config[$ifname]['range']['from'] = "::1000";
					$dhcpdv6_config[$ifname]['range']['to'] = "::2000";
					$dhcpdv6_config[$ifname]['ramode'] = "assist";
					$dhcpdv6_config[$ifname]['rapriority'] = "medium";
				}
			}
		}
	}

	if ($dhcpdv6_config !== null) {
		config_set_path('dhcpdv6', $dhcpdv6_config);
	}
}

function upgrade_145_to_146() {
	// Add standard deviation to the quality rrds
	global $g;

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

		if (is_platform_booting()) {
			echo "Update RRD database {$database}.\n";
		}

		exec("$rrdtool dump {$rrddbpath}/{$database} | {$awkcmd} > {$xmldump}");
		exec("$rrdtool restore -f {$xmldump} {$rrddbpath}/{$database}");
		@unlink("{$xmldump}");
	}

	if (!is_platform_booting()) {
		enable_rrd_graphing();
	}
	/* Let's save the RRD graphs after we run enable RRD graphing */
	/* The function will restore the rrd.tgz so we will save it after */
	exec("cd /; LANG=C RRDDBPATH='{$rrddbpath}' CF_CONF_PATH='{$g['cf_conf_path']}' /etc/rc.backup_rrd.sh");
}

function upgrade_bgpd_146_to_147() {
	$openbgpd_conf = config_get_path('installedpackages/openbgpd/config/0');

	if (!is_array($openbgpd_conf)) {
		return;
	}
	if (!isset($openbgpd_conf['carpstatusip']) &&
	    !is_ipaddr($openbgpd_conf['carpstatusip'])) {
		return;
	}

	if (!is_array(config_get_path('virtualip/vip')))
		return;
	foreach (config_get_path('virtualip/vip', []) as $idx => $vip) {
		if ($vip['subnet'] == $openbgpd_conf['carpstatusip']) {
			$openbgpd_conf['carpstatusvid'] = "_vip{$vip['uniqid']}";
			unset($openbgpd_conf['carpstatusip']);
			config_set_path('installedpackages/openbgpd/config/0', $openbgpd_conf);
			return;
		}
	}
}

function upgrade_quagga_146_to_147() {
	$ospfd_conf = config_get_path('installedpackages/quaggaospfd/config/0');

	if (!is_array($ospfd_conf)) {
		return;
	}
	if (!isset($ospfd_conf['carpstatusip']) &&
	    !is_ipaddr($ospfd_conf['carpstatusip'])) {
		return;
	}

	if (!is_array(config_get_path('virtualip/vip')))
		return;
	foreach (config_get_path('virtualip/vip', []) as $idx => $vip) {
		if ($vip['subnet'] == $ospfd_conf['carpstatusip']) {
			$ospfd_conf['carpstatusvid'] = "_vip{$vip['uniqid']}";
			unset($ospfd_conf['carpstatusip']);
			config_set_path('installedpackages/quaggaospfd/config/0', $ospfd_conf);
			return;
		}
	}
}

function upgrade_146_to_147() {

	upgrade_bgpd_146_to_147();
	upgrade_quagga_146_to_147();
}

function upgrade_147_to_148() {
	$group_config = config_get_path('system/group');

	// Ensure there are no spaces in group names by
	// replacing spaces with underscores
	if (is_array($group_config)) {
		$cleargroups = false;
		foreach ($group_config as &$grp) {
			if (strstr($grp['name'], " ")) {
				$cleargroups = true;
				$grp['scope'] = "remote";
			}
		}
		config_set_path('system/group', $group_config);

		// if there was a space in a group name, there may be multiple
		// groups with the same name in the group file. To prevent pw
		// from getting into a neverending loop, delete all user-defined
		// groups here. local_reset_accounts will run shortly after this
		// and add them back. redmine #6012
		if ($cleargroups) {
			foreach ($group_config as $grp) {
				mwexec("/usr/sbin/pw groupdel -g {$grp['gid']}");
			}
		}
	}
}

function upgrade_148_to_149() {
	global $altq_list_queues;

        if (!is_array(config_get_path('shaper/queue')))
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
	$dhcpdv6_config = config_get_path('dhcpdv6');

	if (is_array($dhcpdv6_config)) {
        foreach ($dhcpdv6_config as &$dhcpdv6) {
			if (isset($dhcpdv6['rainterface'])) {
				if (strstr($dhcpdv6['rainterface'], "_vip")) {
					$dhcpdv6['rainterface'] = get_vip_from_oldcarp($dhcpdv6['rainterface']);
				}
			}
		}
		config_set_path('dhcpdv6', $dhcpdv6_config);
	}
}

function upgrade_150_to_151() {
	// Default dpinger parameters at time of this upgrade (2.3.1)
	$default_interval = 500;
	$default_alert_interval = 1000;
	$default_loss_interval = 2000;
	$default_time_period = 60000;
	$default_latencyhigh = 500;

	$gateways_config = config_get_path('gateways/gateway_item');
	// Check advanced gateway parameter relationships in case they are incorrect
	if (is_array($gateways_config)) {
		foreach ($gateways_config as &$gw) {
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
		config_set_path('gateways/gateway_item', $gateways_config);
	}
}

function upgrade_151_to_152() {
	require_once("/etc/inc/services.inc");

	// Remove these cron jobs on full install if not using ramdisk.
	if (!config_path_enabled('system', 'use_mfs_tmpvar')) {
		/* See #7146 for detail on why the extra parameters are needed for the time being. */
		install_cron_job("/etc/rc.backup_rrd.sh", false, null, null, null, null, null, null, false);
		install_cron_job("/etc/rc.backup_dhcpleases.sh", false, null, null, null, null, null, null, false);
	}
}

function upgrade_152_to_153() {
	if (is_array(config_get_path('virtualip/vip'))) {
		foreach (config_get_path('virtualip/vip', []) as $idx => $vip) {
			if (substr($vip['interface'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($vip['interface'], "_vip")) {
				// using old VIP format, update
				config_set_path("virtualip/vip/{$idx}/interface", get_vip_from_oldcarp($vip['interface']));
			}
		}
	}

	// upgrade GIFs using VIP to new format
	if (is_array(config_get_path('gifs/gif'))) {
		foreach (config_get_path('gifs/gif', []) as $idx => $gif) {
			if (substr($gif['if'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($gif['if'], "_vip")) {
				// using old VIP format, update
				config_set_path("gifs/gif/{$idx}/if", get_vip_from_oldcarp($gif['if']));
			}
		}
	}

	// upgrade GREs using VIP to new format
	if (is_array(config_get_path('gres/gre'))) {
		foreach (config_get_path('gres/gre', []) as $idx => $gre) {
			if (substr($gre['if'], 0, 4) == "_vip") {
				// using new VIP format
				continue;
			} else if (strstr($gre['if'], "_vip")) {
				// using old VIP format, update
				config_set_path("gres/gre/{$idx}/if", get_vip_from_oldcarp($gre['if']));
			}
		}
	}

	// upgrade gateway groups using VIPs
	if (is_array(config_get_path('gateways/gateway_group'))) {
		foreach (config_get_path('gateways/gateway_group', []) as $idx => $gw) {
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
					config_set_path("gateways/gateway_group/{$idx}/item", $newitems);
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
	foreach (config_get_path('gifs/gif', []) as $idx => $gif) {
		config_del_path("gifs/gif/{$idx}/link0");
	}

	foreach (config_get_path('gres/gre', []) as $idx => $gre) {
		config_del_path("gres/gre/{$idx}/link0");
		config_del_path("gres/gre/{$idx}/link2");
	}
}

function upgrade_155_to_156() {
	// Unused
}

function upgrade_156_to_157() {
	/* Convert Cloudflare and GratisDNS type DynDNS entries to the new split hostname and domain format */

	config_init_path('dyndnses/dyndns');
	$a_dyndns = config_get_path('dyndnses/dyndns');

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
	config_set_path('dyndnses/dyndns', $a_dyndns);

	/* unset old pppoerestart cron job if it exists. redmine 1905 */
	if (is_array(config_get_path('cron/item'))) {
		foreach (config_get_path('cron/item', []) as $idx => $cronitem) {
			if ($cronitem['command'] == "/etc/pppoerestart") {
				config_del_path("cron/item/{$idx}");
			}
		}
	}
}

function upgrade_157_to_158() {
	/* Convert Dynamic DNS passwords to base64 encoding. Redmine #6688 */

	config_init_path('dyndnses/dyndns');
	$a_dyndns = config_get_path('dyndnses/dyndns');

	foreach ($a_dyndns as &$dyndns) {
		$dyndns['password'] = base64_encode($dyndns['password']);
	}
	config_set_path('dyndnses/dyndns', $a_dyndns);
}

/* Unset references to glxsb in the config. See #6755 */
function upgrade_158_to_159() {
	if (config_get_path('system/crypto_hardware') == "glxsb") {
		config_del_path('system/crypto_hardware');
	}
}

/* Convert OpenVPN "protocol" to new style for OpenVPN 2.4, old udp/tcp was
 * IPv4 only, now is dual stack, so change it to udp4/tcp4
 */
function upgrade_159_to_160() {
	$openvpn_server_config = config_get_path('openvpn/openvpn-server', []);
	foreach ($openvpn_server_config as &$vpn) {
		if ($vpn['protocol'] == "UDP") {
			$vpn['protocol'] = "UDP4";
		}
		if ($vpn['protocol'] == "TCP") {
			$vpn['protocol'] = "TCP4";
		}
	}
	unset($vpn);
	config_set_path('openvpn/openvpn-server', $openvpn_server_config);

	$openvpn_client_config = config_get_path('openvpn/openvpn-client', []);
	foreach ($openvpn_client_config as &$vpn) {
		if ($vpn['protocol'] == "UDP") {
			$vpn['protocol'] = "UDP4";
		}
		if ($vpn['protocol'] == "TCP") {
			$vpn['protocol'] = "TCP4";
		}
	}
	config_set_path('openvpn/openvpn-client', $openvpn_client_config);
}

/* RAM Disk Management */
function upgrade_160_to_161() {
	global $g;

	if (!config_path_enabled('system', 'use_mfs_tmpvar')) {
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
	if (empty(config_get_path('system/crypto_hardware'))) {
		config_set_path('system/crypto_hardware', "cryptodev");
	}
}

/* Traffic graphs widget settings are now stored in a layout similar
 * to other widgets. Migrate any old settings.
 */
function upgrade_162_to_163() {
	require_once("ipsec.inc");
	foreach (array('refreshinterval', 'invert', 'size', 'backgroundupdate') as $setting) {
		if (config_get_path("widgets/trafficgraphs/{$setting}") !== null) {
			config_set_path("widgets/traffic_graphs/{$setting}", config_get_path("widgets/trafficgraphs/{$setting}"));
			config_del_path("widgets/trafficgraphs/{$setting}");
		}
	}

	if (config_get_path('widgets/trafficgraphs/shown') !== null) {
		if (is_array(config_get_path('widgets/trafficgraphs/shown/item'))) {
			$ifdescrs = get_configured_interface_with_descr();

			if (ipsec_enabled()) {
				$ifdescrs['enc0'] = "IPsec";
			}

			$validNames = array();

			foreach ($ifdescrs as $ifdescr => $ifname) {
				array_push($validNames, $ifdescr);
			}

			config_set_path('widgets/traffic_graphs/filter', implode(',', array_diff($validNames, config_get_path('widgets/trafficgraphs/shown/item'))));
		}

		config_del_path('widgets/trafficgraphs/shown');
	}
}

/* Dashboard widget settings config format has changed to support having possibly multiple
 * of a widget on the dashboard. Migrate any old settings.
 */
function convert_widget_164($oldname, $newname, $settings_keys) {
	$user_config = config_get_path('system/user');

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
			if (config_get_path("widgets/{$oldkey}") !== null) {
				config_set_path("widgets/{$newname}/{$newkey}", config_get_path("widgets/{$oldkey}"));
				config_del_path("widgets/{$oldkey}");
			}

			// Modify any user-specific entries
			foreach ($user_config as & $user) {
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
			if (config_get_path("widgets/{$oldname}/{$oldkey}") !== null) {
				config_set_path("widgets/{$newname}/{$newkey}", config_get_path("widgets/{$oldname}/{$oldkey}"));
				config_del_path("widgets/{$oldname}/{$oldkey}");
			}

			// Modify any user-specific entries
			foreach ($user_config as & $user) {
				if (isset($user['widgets'][$oldname][$oldkey])) {
					$user['widgets'][$newname][$newkey] = $user['widgets'][$oldname][$oldkey];
					unset($user['widgets'][$oldname][$oldkey]);
				}

				if (isset($user['widgets'][$oldname])) {
					unset($user['widgets'][$oldname]);
				}
			}
		}
		config_del_path("widgets/{$oldname}");
	}
	config_set_path('system/user', $user_config);
}

function upgrade_163_to_164() {
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
	$filter_rule_config = config_get_path('filter/rule');
	foreach ($filter_rule_config as & $rule) {
		if ($rule['destination']['port'] == "137-139-137-139") {
			$rule['destination']['port'] = "137-139";
		}
	}
	config_set_path('filter/rule', $filter_rule_config);
}

/* Fixup digest algorithm selection for OpenVPN clients and servers so they do not use aliased names. */
function upgrade_165_to_166() {
	require_once('openvpn.inc');
	$openvpn_config = config_get_path('openvpn');
	if (is_array($openvpn_config)) {
		if (is_array($openvpn_config['openvpn-server'])) {
			foreach ($openvpn_config['openvpn-server'] as &$vpn) {
				$vpn['digest'] = openvpn_remap_digest($vpn['digest']);
			}
			unset($vpn);
		}
		if (is_array($openvpn_config['openvpn-client'])) {
			foreach ($openvpn_config['openvpn-client'] as &$vpn) {
				$vpn['digest'] = openvpn_remap_digest($vpn['digest']);
			}
		}
		config_set_path('openvpn', $openvpn_config);
	}
}

/* Force the Netgate Services and Support widget to be active on upgrade.
   New widget is added at the top of column 2 */
function upgrade_166_to_167() {
	if (strpos(config_get_path('widgets/sequence'),
	    'netgate_services_and_support') === false) {
		$widgets = array_filter(explode(",", config_get_path('widgets/sequence')));
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

		config_set_path('widgets/sequence', implode(",", $newsequence));
	}
}

function upgrade_167_to_168() {
	upgrade_166_to_167();
}

function upgrade_168_to_169() {
	config_del_path('cron/rc_update_pkg_metadata');

	$command = '/usr/bin/nice -n20 /etc/rc.update_pkg_metadata';
	config_init_path('cron/item');
	$cron_config = config_get_path('cron/item');
	if (is_array($cron_config)) {
		foreach ($cron_config as $entry) {
			if ($entry['command'] == $command) {
				return;
			}
		}
	}

	$cron_config[] = array(
		'minute' => '1',
		'hour' => '0',
		'mday' => '*',
		'month' => '*',
		'wday' => '*',
		'who' => 'root',
		'command' => $command
	);
	config_set_path('cron/item', $cron_config);
}

/* Upgrade wireless interfaces to the format required for 2.4
 * Each wireless interface now needs to be a cloned instance, the card itself
 * Can no longer be assigned. https://redmine.pfsense.org/issues/6770 */
function upgrade_169_to_170() {
	foreach (config_get_path('interfaces', []) as $friendly => & $iface) {
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
				config_init_path('wireless/clone');
				config_set_path('wireless/clone/', $wlan);
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
	$vlan_config = config_get_path('vlans/vlan');
	$if_config = config_get_path('interfaces');

	if (!is_array($vlan_config) || count($vlan_config) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($vlan_config as $id => $vlan) {
		/* Make sure to update the interfaces section with the new name. */
		$vlan_name = "{$vlan['if']}_vlan{$vlan['tag']}";
		foreach ($iflist as $ifname) {
			if ($if_config[$ifname]['if'] == $vlan_name) {
				$if_config[$ifname]['if'] = vlan_interface($vlan);
			}
		}
		$vlan_config[$id]['vlanif'] = vlan_interface($vlan);
	}
	config_set_path('vlans/vlan', $vlan_config);
	config_set_path('interfaces', $if_config);
}

/* Upgrade the QinQ interface names to use $if.$tag instead of $if_$tag.
 * This helps keep the interface names smaller than the limit (but they are still
 * big with the QinQ subtag).
 */
function upgrade_171_to_172() {
	$qinq_config = config_get_path('qinqs/qinqentry');
	$if_config = config_get_path('interfaces');

	if (!is_array($qinq_config) || count($qinq_config) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($qinq_config as &$qinq) {
		$qinq['vlanif'] = vlan_interface($qinq);

		if (!isset($qinq['members'])) {
			continue;
		}
		foreach (explode(" ", $qinq['members']) as $tag) {
			/* Make sure to update the interfaces section with the new name. */
			$vlan_name = "{$qinq['if']}_{$qinq['tag']}_{$tag}";
			foreach ($iflist as $ifname) {
				if ($if_config[$ifname]['if'] == $vlan_name) {
					$if_config[$ifname]['if'] = qinq_interface($qinq, $tag);
				}
			}
		}
	}
	config_set_path('qinqs/qinqentry', $qinq_config);
	config_set_path('interfaces', $if_config);
}

/*
 * Upgrade the VLAN interface names to use $if.$tag on PPP items
 */
function upgrade_172_to_173() {
	$ppp_config = config_get_path('ppps/ppp');

	if (!is_array($ppp_config) ||
	    count($ppp_config) == 0) {
		return;
	}
	$iflist = get_configured_interface_list(true);
	foreach ($ppp_config as &$ppp) {
		if (empty($ppp['ports']) ||
		    strpos($ppp['ports'], "_vlan") == false) {
			continue;
		}

		$ppp['ports'] = str_replace('_vlan', '.',
		    $ppp['ports']);
	}
	config_set_path('ppps/ppp', $ppp_config);
}

/*
 * Dynamic DNS nsupdate keyfiles have been replaced with a simpler ddns-confgen style file.
 */
function upgrade_173_to_174() {
	$dnsupdate_config = config_get_path('dnsupdates/dnsupdate');

	/* Stop if there is nothing to do. */
	if (!is_array($dnsupdate_config)) {
		return;
	}
	/* Remove unused keytype field. */
	foreach ($dnsupdate_config as $i => &$dnsupdate) {
		unset($dnsupdate['keytype']);
	}
	config_set_path('dnsupdates/dnsupdate', $dnsupdate_config);
}

/* IPsec Phase1 now supports multiple authentication ciphers to be specified from the webgui.
 * This is useful for mobile users using different OS's supporting different ciphers.
 */
function upgrade_174_to_175() {
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	if (count($ipsec_phase1_config)) {
		$a_phase1 = &$ipsec_phase1_config;
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
		config_set_path('ipsec/phase1', $ipsec_phase1_config);
	}
}

/* igmp always was enabled by default if settings were present.
 * So enable it once on upgrade if settings are there.
 * And provide the option through gui to disable it again
 */
function upgrade_175_to_176() {
	if ((count(config_get_path('igmpproxy/igmpentry', [])) > 0)) {
		config_set_path('igmpproxy/enable', true);
	}
}

/* Placeholder for a factory update. */
function upgrade_176_to_177() {
}

// The image displayed by the picture widget is now stored on the file system
function upgrade_177_to_178() {
	$widgets_config = config_get_path('widgets');
	if (isset($widgets_config)) {
		$idx = 0;

		while (isset($widgets_config['picture-' . $idx])) {
			file_put_contents("/conf/widget_image.picture-" . $idx, base64_decode($widgets_config['picture-' . $idx]['picturewidget']));
			$widgets_config['picture-' . $idx]['picturewidget'] = "/conf/widget_image.picture-". $idx;
			$idx++;
		}
		config_set_path('widgets', $widgets_config);
	}
}

/* Placeholder for a factory update. */
function upgrade_178_to_179() {
}

function upgrade_179_to_180() {
	global $g;

	/* Change default to 400000 to make sure bogonsv6 works */
	if (empty(config_get_path('system/maximumtableentries'))) {
		config_set_path('system/maximumtableentries',
		    g_get('minimumtableentries_bogonsv6'));
	}
}

/*
 * Automatically enable retrieving captive portal bandwidth limits from RADIUS for each captive portal
 */
function upgrade_180_to_181() {
	if (is_array(config_get_path('captiveportal'))) {
		foreach (config_get_path('captiveportal', []) as $cpzone => $cpcfg) {
			if ($cpcfg['auth_method'] == "radius") {
				config_set_path("captiveportal/{$cpzone}/radiusperuserbw", true);
			}
		}
	}
}

function upgrade_181_to_182() {
	/*
	 * Some gateways did not have an ipprotocol set, and some configurations
	 * did not have a default set so one was assumed. To avoid leaving the
	 * user without a default, fix these situations first.
	 */
	$defgw_v4_found = false;
	$defgw_v6_found = false;
	$defgw_v4_candidate = array();
	$defgw_v6_candidate = array();
	$gateway_config = config_get_path('gateways/gateway_item');
	if (is_array($gateway_config)) {
		foreach($gateway_config as &$item) {
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
		unset($item);
	}
	/* If there was no other default gateway, use the one of last resort. */
	if (!$defgw_v4_found && !empty($defgw_v4_candidate)) {
		$defgw_v4_candidate['defaultgw'] = true;
	}
	if (!$defgw_v6_found && !empty($defgw_v6_candidate)) {
		$defgw_v6_candidate['defaultgw'] = true;
	}
	config_set_path('gateways/gateway_item', $gateway_config);

	if (config_path_enabled('system', 'gw_switch_default')) {
		// default gateway switching was enabled, convert gatewaygroup
		$newgroup4 = array();
		$newgroup6 = array();
		$tiernr4 = 2;
		$tiernr6 = 2;
		$gateways_config = config_get_path('gateways');
		if (is_array($gateways_config)) {
			foreach($gateways_config['gateway_item'] as &$item) {
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
			unset($item);
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
			$gateways_config['gateway_group'][] = $newgroup4;
			$gateways_config['defaultgw4'] = $newname;
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
			$gateways_config['gateway_group'][] = $newgroup6;
			$gateways_config['defaultgw6'] = $newname;
		}
		config_set_path('gateways', $gateways_config);
		config_del_path('system/gw_switch_default');// remove old setting, if a group is used switching is already implied
	} else {
		// set new defaultgw selection boxes to old selected default
		$gateways_config = config_get_path('gateways');
		if (is_array($gateways_config) && is_array($gateways_config['gateway_item'])) {
			foreach($gateways_config['gateway_item'] as &$item) {
				if (isset($item['defaultgw'])) {
					if ($item['ipprotocol'] == 'inet') {
						$gateways_config['defaultgw4'] = $item['name'];
					} else {
						$gateways_config['defaultgw6'] = $item['name'];
					}
					unset($item['defaultgw']);
				}
			}
			unset($item);
			config_set_path('gateways', $gateways_config);
		}
	}
}

/* Correct gateway group trigger level values.
 * See https://redmine.pfsense.org/issues/8586
 */
function upgrade_182_to_183() {
	$gateways_config = config_get_path('gateways/gateway_item');
	if (!is_array($gateways_config)) {
		/* No gateway groups, nothing to do. */
		return;
	}
	foreach ($gateways_config as &$gwg) {
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
	config_set_path('gateways/gateway_item', $gateways_config);
}

function upgrade_183_to_184() {
	/* 'none' was kinda confusing and didnt really do none
	 * now use the new 'automatic' mode if it was set to none. */
	if (config_get_path('gateways/defaultgw4', "") === "-") {
		config_set_path('gateways/defaultgw4', "");
	}
	if (config_get_path('gateways/defaultgw6', "") === "-") {
		config_set_path('gateways/defaultgw6', "");
	}
}

// Migrate AutoConfigBackup package settings to integrated ACB system
// and remove package
function upgrade_184_to_185() {
	$acb_pkg_config = config_get_path('installedpackages/autoconfigbackup/config/0');

	if (is_array($acb_pkg_config)) {
		$acbpkg = &$acb_pkg_config;

		config_init_path('system/acb');
		$acb = config_get_path('system/acb');
		$acb['enable'] = ($acbpkg['enable_acb'] != 'disabled') ?  'yes':'no';
		$acb['gold_encryption_password'] = $acbpkg['crypto_password'];

		// If no encryption password has been set up yet, we might as well import the "Gold" password
		// The user can update it later
		if (!isset($acb['encryption_password'])) {
			$acb['encryption_password'] = $acbpkg['crypto_password'];
		}

		$acb['gold_password'] = $acbpkg['password'];
		$acb['gold_username'] = $acbpkg['username'];
		config_set_path('system/acb', $acb);

		config_del_path('installedpackages/autoconfigbackup/config');
	}
}

function upgrade_185_to_186() {
	/* FEC LAGG is deprecated, replace with loadbalance */
	if (!function_exists("file_notice")) {
		require_once("notices.inc");
	}
	$lagg_config = config_get_path('laggs/lagg');
	if (is_array($lagg_config)) {
		foreach ($lagg_config as &$lagg) {
			if ($lagg['proto'] == 'fec') {
				$lagg['proto'] = 'failover';
				file_notice("Interfaces", sprintf(gettext("The FEC LAGG protocol is deprecated. The %s LAGG interface has been set to failover."), $lagg['laggif']));
			}
		}
		config_set_path('laggs/lagg', $lagg_config);
	}
}

function generate_usermanager_radius_config($cpzone, $counter, $protocol, $ip, $key, $port, $radiussrcip_attribute, $is_accounting=false, $accounting_port=false) {
	$pconfig = array();
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

	config_set_path('system/authserver/', $pconfig);

	return 'radius - '.$pconfig['name'];
}

function upgrade_186_to_187() {
	global $g;

	if (is_array(config_get_path('captiveportal'))) {
		foreach (config_get_path('captiveportal', []) as $cpzone => $cp) {
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

			config_set_path("captiveportal/{$cpzone}", $cp);
		}
	}
}

function upgrade_187_to_188() {
	$old_cmd = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshlockout";
	$new_cmd = "/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard";
	config_init_path('cron/item');
	foreach (config_get_path('cron/item', []) as $idx => $entry) {
		if ($entry['command'] == $old_cmd) {
			config_set_path("cron/item/{$idx}/command", $new_cmd);
			break;
		}
	}
}

function upgrade_188_to_189() {
	/* Migrate ssh setting to new location */
	if (config_path_enabled('system', 'enablesshd')) {
		config_init_path('system/ssh');
		config_set_path('system/ssh/enable', "enabled");
		config_del_path('system/enablesshd');
	}
	/* Remove accidentally duplicated ssh config
	 * See https://redmine.pfsense.org/issues/8974 */
	config_del_path('system/sshd');
}

/* Older preexisting IPsec P1 entries may not have had the protocol explicitly
 * defined. Fill in the default value of 'inet'.
 * https://redmine.pfsense.org/issues/9207 */
function upgrade_189_to_190() {
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	foreach ($ipsec_phase1_config as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		if (!isset($ph1ent['protocol']) || empty($ph1ent['protocol'])) {
			$ph1ent['protocol'] = 'inet';
		}
	}
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

/* sshguard cron jobs are not necessary.
 * See https://redmine.pfsense.org/issues/9223 */
function upgrade_190_to_191() {
	install_cron_job("/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 sshguard", false, null, null, null, null, null, null, false);
	install_cron_job("/usr/bin/nice -n20 /usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout", false, null, null, null, null, null, null, false);
}

/* Deprecate relayd Load Balancer
 * See https://redmine.pfsense.org/issues/9386 */
function upgrade_191_to_192() {
	/* Backup LB config */
	$backup_file = "/conf/deprecated_load_balancer.xml";
	unlink_if_exists($backup_file);
	file_put_contents($backup_file, backup_config_section('load_balancer'));

	/* Determine if LB was active and notify (or log if not) */
	$deprecation_notice = sprintf(gettext("The built-in Load Balancer service has been deprecated. The active Load Balancer configuration has been stored in %s. Consider migrating to the HAProxy package."), $backup_file);
	$loadbalancer_config = config_get_path('load_balancer');
	if (is_array($loadbalancer_config['virtual_server']) &&
	    count($loadbalancer_config['virtual_server']) &&
	    count($loadbalancer_config['lbpool'])) {

		if (!function_exists("file_notice")) {
			require_once("notices.inc");
		}
		file_notice("Load Balancer", $deprecation_notice);
	} else {
		log_error("INFO: {$deprecation_notice}");
	}

	config_del_path('load_balancer');

	/* Remove LB HA Sync Config */
	config_del_path('hasync/synchronizelb');

	/* If the LB widget is present, remove it*/
	if (config_path_enabled('widgets', 'sequence') &&
	    (strpos(config_get_path('widgets/sequence'), 'load_balancer_status') !== false)) {
		$widgets = explode(',', trim(config_get_path('widgets/sequence')));
		foreach ($widgets as $idx => &$widget) {
			if (substr( $widget, 0, 20 ) === "load_balancer_status") {
				unset($widgets[$idx]);
			}
		}
		config_set_path('widgets/sequence', implode(',', $widgets));
	}

	/* Per-log settings */
	config_del_path('syslog/relayd_settings');
}

/* Deprecate growl notifications */
function upgrade_192_to_193() {
	config_del_path('notifications/growl');
}

function upgrade_193_to_194() {
	global $g;

	if (is_array(config_get_path('captiveportal'))) {
		foreach (config_get_path('captiveportal', []) as $cpzone => $cp) {
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
	global $g;

	install_cron_job('/usr/sbin/newsyslog', true, "*/1", '*', '*', '*', '*', 'root', false);
}

/* Add periodic cron jobs */
function upgrade_197_to_198() {
	global $g;

	install_cron_job('/etc/rc.periodic daily',   true, "1",  '3', '*', '*', '*', 'root', false);
	install_cron_job('/etc/rc.periodic weekly',  true, "15", '4', '*', '*', '6', 'root', false);
	install_cron_job('/etc/rc.periodic monthly', true, "30", '5', '1', '*', '*', 'root', false);
}

/* Update IPsec authentication method names
 * https://redmine.pfsense.org/issues/9903 */
function upgrade_198_to_199() {
	/* "RSA" methods changed to the more generic "cert" since they are not only RSA. */
	$namechanges = array(
		'hybrid_rsa_server' => 'hybrid_cert_server',
		'xauth_rsa_server' => 'xauth_cert_server',
		'rsasig' => 'cert',
	);
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	foreach ($ipsec_phase1_config as & $ph1ent) {
		/* If the auth method for this P1 is in the list to change, change it */
		if (array_key_exists($ph1ent['authentication_method'], $namechanges)) {
			$ph1ent['authentication_method'] = $namechanges[$ph1ent['authentication_method']];
		}
	}
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

/* Superceded. See https://redmine.pfsense.org/issues/11219 and upgrade_212_to_213() */
function upgrade_199_to_200() {
}

/* Update LDAP transport values */
function upgrade_200_to_201() {
	/* Normalize/correct names (All are TCP) */
	$namechanges = array(
		'TCP - Standard' => 'Standard TCP',
		'TCP - STARTTLS' => 'STARTTLS Encrypted',
		'SSL - Encrypted' => 'SSL/TLS Encrypted',
	);
	config_init_path('system/authserver');
	$authserver_config = config_get_path('system/authserver');
	foreach ($authserver_config as & $authserver) {
		if (array_key_exists($authserver['ldap_urltype'], $namechanges)) {
			$authserver['ldap_urltype'] = $namechanges[$authserver['ldap_urltype']];
		}
	}
	config_set_path('system/authserver', $authserver_config);
}

/* #10525: Handle Chinese (HongKong / Taiwan) locale rename */
function upgrade_201_to_202() {
	if (config_get_path('system/language') == 'zh_HK') {
		config_set_path('system/language', 'zh_Hans_HK');
	} elseif (config_get_path('system/language') == 'zh_TW') {
		config_set_path('system/language', 'zh_Hant_TW');
	}
}

function upgrade_202_to_203() {
	$gre_config = config_get_path('gres/gre');
	// Upgrade GREs with IPv6 tunnel networks to new dual stack format
	if (is_array($gre_config)) {
		foreach ($gre_config as $idx => &$gre) {
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
		config_set_path('gres/gre', $gre_config);
	}
}

/*
 * Change IPsec close_action values
 * See https://redmine.pfsense.org/issues/10632
 */

function upgrade_203_to_204() {
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	foreach ($ipsec_phase1_config as & $ph1ent) {
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
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

function upgrade_204_to_205() {
	global $g;

	$cp_config = config_get_path('captiveportal');
	if (is_array($cp_config)) {
		foreach ($cp_config as $cpzone => $cp) {
			unlink_if_exists("{$g['vardb_path']}/captiveportal{$cpzone}.db");

			if (!empty(config_get_path("voucher/{$cpzone}/vouchersyncdbip"))) {
				$cp_config[$cpzone]['enablebackwardsync'] = '';
				$cp_config[$cpzone]['backwardsyncip'] = config_get_path("voucher/{$cpzone}/vouchersyncdbip");
				$cp_config[$cpzone]['backwardsyncuser'] = config_get_path("voucher/{$cpzone}/vouchersyncusername");
				$cp_config[$cpzone]['backwardsyncpassword'] = config_get_path("voucher/{$cpzone}/vouchersyncpass");
			}
		}
		config_set_path('captiveportal', $cp_config);
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
	config_set_path('system/hn_altq_enable', true);
}

/* Update IPsec VTI to new VTIMAP format
 * https://redmine.pfsense.org/issues/9592
 */
function upgrade_208_to_209() {
	require_once("interfaces.inc");

	config_init_path('ipsec/vtimaps/item');
	$ipsec_config = config_get_path('ipsec');

	if (!is_array($ipsec_config['phase1']) ||
	    !is_array($ipsec_config['phase2'])) {
		return;
	}

	foreach ($ipsec_config['phase1'] as $ph1ent) {
		if (!isset($ph1ent['mobile']) &&
		    ($ph1ent['iketype'] == 'ikev1' ||
		    isset($ph1ent['splitconn']))) {
			$vtisubnet_spec = ipsec_vti($ph1ent, true, false);
			if (empty($vtisubnet_spec)) {
				continue;
			}
			foreach ($vtisubnet_spec as $idx => $vtisub) {
				$ipsec_config['vtimaps']['item'][] = array(
					"reqid" => $ph1ent['ikeid'],
					"index" => $idx,
					"ifnum" => "{$ph1ent['ikeid']}00{$idx}"
				);
			}
		} else {
			$ipsec_config['vtimaps']['item'][] = array(
				"reqid" => $ph1ent['ikeid'],
				"index" => "0",
				"ifnum" => "{$ph1ent['ikeid']}000"
			);
		}
	}
	config_set_path('ipsec', $ipsec_config);
}

function upgrade_209_to_210() {
	if (config_get_path('system/dnslocalhost') !== null) {
		config_set_path('system/dnslocalhost', 'remote');
	}
}

/* OpenVPN Data Cipher changes
 * https://redmine.pfsense.org/issues/10919 */
function upgrade_210_to_211() {
	config_init_path('openvpn/openvpn-server');
	config_init_path('openvpn/openvpn-client');
	$openvpn_config = config_get_path('openvpn');
	foreach(array('server', 'client') as $mode) {
		foreach ($openvpn_config["openvpn-{$mode}"] as & $settings) {
			/* Rename ncp-ciphers to data_ciphers */
			if (!empty($settings['ncp-ciphers'])) {
				$settings['data_ciphers'] = $settings['ncp-ciphers'];
			} elseif ($settings['crypto'] == 'none') {
				$settings['data_ciphers'] = 'none';
			} else {
				$settings['data_ciphers'] = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305';
			}
			if (isset($settings['ncp-ciphers'])) {
				unset($settings['ncp-ciphers']);
			}
			/* Add crypto to data_ciphers */
			if (!empty($settings['crypto']) &&
			    ($settings['crypto'] != 'none') &&
			    !in_array($settings['crypto'], explode(',', $settings['data_ciphers']))) {
				$settings['data_ciphers'] .= ',' . $settings['crypto'];
			}
			/* Rename crypto to data_ciphers_fallback */
			if (isset($settings['crypto'])) {
				$settings['data_ciphers_fallback'] = $settings['crypto'];
				unset($settings['crypto']);
			}
			/* Forcefully enable data cipher negotiation since
			 * disabling negotiation is now deprecated */
			$settings['ncp_enable'] = "enabled";
		}
		unset($settings);
	}
	config_set_path('openvpn', $openvpn_config);
}

function upgrade_211_to_212() {
	if (config_path_enabled('unbound', 'sslport')) {
		config_set_path('unbound/tlsport', config_get_path('unbound/sslport'));
		config_del_path('unbound/sslport');
	}
}

/* IPsec Expiration and Replacement values which need updated for swanctl format
 * https://redmine.pfsense.org/issues/11219
 * https://redmine.pfsense.org/issues/9983
 */
function upgrade_212_to_213() {
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	foreach ($ipsec_phase1_config as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}

		if (isset($ph1ent['reauth_enable'])) {
			/* Disable reauth */
			$ph1ent['reauth_time'] = "0";
		} elseif (!empty($ph1ent['margintime'])) {
			/* If margintime is set, use that to calculte reauth_time */
			$ph1ent['reauth_time'] = ($ph1ent['lifetime'] - $ph1ent['margintime']);
		}
		/* Auto or IKEv2, rekey items */
		if (($ph1ent['iketype'] == 'ikev2') || ($ph1ent['iketype'] == 'auto')) {
			if (isset($ph1ent['rekey_enable'])) {
				/* Disable rekey */
				$ph1ent['rekey_time'] = "0";
				$ph1ent['reauth_time'] = "0";
			} elseif (!empty($ph1ent['margintime'])) {
				/* If margintime is set, use that to calculate rekey_time */
				$ph1ent['rekey_time'] = ($ph1ent['lifetime'] - $ph1ent['margintime']);
			}
		}

		if (!empty($ph1ent['margintime'])) {
			$ph1ent['rand_time'] = $ph1ent['margintime'];
		}

		/* Older snaps had over_time, now need lifetime back. */
		if (!empty($ph1ent['over_time']) && empty($ph1ent['lifetime'])) {
			$ph1ent['lifetime'] = $ph1ent['over_time'] + max($ph1ent['rekey_time'], $ph1ent['reauth_time']);
		}

		if (isset($ph1ent['reauth_enable'])) {
			unset($ph1ent['reauth_enable']);
		}
		if (isset($ph1ent['rekey_enable'])) {
			unset($ph1ent['rekey_enable']);
		}
		if (isset($ph1ent['margintime'])) {
			unset($ph1ent['margintime']);
		}
		if (isset($ph1ent['over_time'])) {
			unset($ph1ent['over_time']);
		}
	}
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

/* VXLAN support was removed */
function upgrade_213_to_214() {
	config_del_path('vxlans');
}

/* WireGuard support was removed */
function upgrade_214_to_215() {
	config_del_path('wireguard');
}

/* Fix VTI interface numbers */
function upgrade_215_to_216() {
	if (count(config_get_path('ipsec/vtimaps/item', [])) == 0) {
		return;
	}

	/* Deprecated method. */
	function upgrade216_ipsec_create_vtimap($ikeid, $idx) {
		$assigned = array_column(config_get_path('ipsec/vtimaps/item', []), 'ifnum');
		asort($assigned, SORT_NUMERIC);
		$new = 1;
		foreach ($assigned as $ipsecifnum) {
			if ($ipsecifnum != $new) {
				break;
			}
			if ($new++ > 32767) {
				return(NULL);
			}
		}
		return array(
			"reqid" => $ikeid,
			"index" => $idx,
			"ifnum" => $new
		);
	}

	$iflist = get_configured_interface_list_by_realif(true);

	foreach (config_get_path('ipsec/vtimaps/item', []) as $idx => $vtimap) {
		if ($vtimap['ifnum'] <= 32767) {
			continue;
		}

		$new_vtimap = upgrade216_ipsec_create_vtimap($vtimap['reqid'],
		    $vtimap['index']);

		/*
		 * NULL means 32767 limit was reached.  It should never hit
		 * this
		 */
		if ($new_vtimap == NULL) {
			break;
		}

		$old_if = 'ipsec' . $vtimap['ifnum'];

		/* Interface is assigned */
		if (isset($iflist[$old_if])) {
			config_set_path('interfaces/' . $iflist[$old_if] . '/if', 'ipsec' . $new_vtimap['ifnum']);
		}

		config_set_path('ipsec/vtimaps/item/' . $idx, $new_vtimap);
	}
}

/*
 * Child SA Start Action has replaced the Responder Only option. Update P1
 * to match.
 * https://redmine.pfsense.org/issues/11576
 */
function upgrade_216_to_217() {
	config_init_path('ipsec/phase1');
	$ipsec_phase1_config = config_get_path('ipsec/phase1');
	foreach ($ipsec_phase1_config as & $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		if (isset($ph1ent['responderonly'])) {
			$ph1ent['startaction'] = 'none';
			unset($ph1ent['responderonly']);
		}
	}
	config_set_path('ipsec/phase1', $ipsec_phase1_config);
}

/*
 * Disable PC/SC Smart Card Daemon if PKCS#11 authentication is not used
 * https://redmine.pfsense.org/issues/11933
 */
function upgrade_217_to_218() {
	config_init_path('ipsec/phase1');
	foreach (config_get_path('ipsec/phase1', []) as $ph1ent) {
		if (empty($ph1ent)) {
			continue;
		}
		if (($ph1ent['authentication_method'] == 'pkcs11') &&
		    !isset($ph1ent['disabled'])) {
			config_set_path('ipsec/pkcs11support', true);
			break;
		}
	}
}

/*
 * Convert VTI interface names to new format
 */
function upgrade_218_to_219() {
	config_init_path('ipsec/phase1');
	config_init_path('ipsec/phase2');
	config_init_path('ipsec/vtimaps/item');

	/* Deprecated method.
	 * $ipsecifnum = get_ipsecifnum($ikeid, $idx);
	 * locates and returns an ipsecifnum in the config.
	 */
	function upgrade219_get_ipsecifnum($ikeid, $idx) {
		foreach (config_get_path('ipsec/vtimaps/item', []) as $vtimap) {
			if (($vtimap['reqid'] == $ikeid) &&
			    ($vtimap['index'] == $idx)) {
				return $vtimap['ifnum'];
			}
		}
		return false;
	}

	/* If IPsec is disabled or there are no P1 or P2 entries, there cannot
	 * be any current assignments, so bail early */
	if (!ipsec_enabled() ||
	    empty(config_get_path('ipsec/phase1')) ||
	    empty(config_get_path('ipsec/phase2'))) {
		return false;
	}

	/* Make an associative array with old name as key and new name as value for all VTI tunnels */
	$ipsecifs = array();
	foreach (config_get_path('ipsec/phase1', []) as $ph1ent) {
		if (empty($ph1ent) || !is_array($ph1ent)) {
			continue;
		}
		$ifent = array();
		/* If there is data here, then it's a VTI tunnel */
		$vtisubnet_spec = ipsec_vti($ph1ent, true);
		if (!$vtisubnet_spec || !is_array($vtisubnet_spec)) {
			/* Not VTI, so skip it. */
			continue;
		}
		if (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] == 'ikev1' || isset($ph1ent['splitconn']))) {
			foreach ($vtisubnet_spec as $idx => $vtisub) {
				/* Determine old name */
				$old = "ipsec" . upgrade219_get_ipsecifnum($ph1ent['ikeid'], $idx);
				/* Determine new name */
				$new = ipsec_get_ifname($ph1ent, $vtisub['reqid']);
				$ipsecifs[$old] = $new;
			}
		} else {
			/* For IKEv2, only create one interface with additional addresses as aliases */
			/* Determine old name */
			$old = "ipsec" . upgrade219_get_ipsecifnum($ph1ent['ikeid'], 0);
			/* Determine new name */
			$new = ipsec_get_ifname($ph1ent);
			$ipsecifs[$old] = $new;
		}
	}

	/* If there are no VTI interfaces, we have nothing to do */
	if (empty($ipsecifs)) {
		return null;
	}

	$if_config = config_get_path('interfaces');
	foreach ($if_config as &$ifcfg) {
		/* Check current interface assignments and see if any match a value we want */
		if (array_key_exists($ifcfg['if'], $ipsecifs)) {
			/* Update assignment to new name */
			$ifcfg['if'] = $ipsecifs[$ifcfg['if']];
		}
	}
	config_set_path('interfaces', $if_config);
	config_del_path('ipsec/vtimaps');
}

/*
 * Ensure the ACB cron job is installed after upgrade if ACB is enabled
 * If the cron job already exists, no harm is done
 */
function upgrade_219_to_220() {
	config_init_path('system/acb');

	if (config_get_path('system/acb/enable') == "yes" && file_exists("/usr/local/sbin/acbupload.php")) {
		install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php /usr/local/sbin/acbupload.php", true, "*");
	}
}

/*
 * Add new disk widget to dashboard if user already had the system information
 * wiget configured to show disk usage stats.
 */
function upgrade_220_to_221() {
	$widgets = explode(',', config_get_path('widgets/sequence'));

	foreach ($widgets as $idx => $widget) {
		[$name, $col, $state, $index] = explode(':', $widget);

		if ($name === 'system_information') {
			$widget_settings_key = "{$name}-{$index}";

			$filter = explode(',', config_get_path("widgets/{$widget_settings_key}/filter"));

			if (!in_array('disk_usage', $filter)) {
				$disk_widget = implode(':', array_filter(['disks', $col, $state, $index]));

				if (!in_array($disk_widget, $widgets)) {
					array_splice($widgets, ($idx + 1), 0, $disk_widget);
				}
			}
		}
	}

	config_set_path('widgets/sequence', implode(',', $widgets));
}

function upgrade_221_to_222() {
}

function upgrade_222_to_223() {
	$user_config = config_get_path('system/user');

	foreach ($user_config as & $user) {
		if ($user['name'] == 'admin') {
			$user_home = "/root";
		} else {
			$user_home = "/home/{$user['name']}";
		}
		$fn = "{$user_home}/.keephistory";
		if (file_exists($fn)) {
			$user['keephistory'] = true;
			@unlink($fn);
		}
	}
	config_set_path('system/user', $user_config);
}

function upgrade_223_to_224() {
	config_init_path('filter/rule');
	$filter_rule_config = config_get_path('filter/rule');
	foreach ($filter_rule_config as & $rule) {
		if (isset($rule['floating']) && !isset($rule['interface'])) {
			$rule['interface'] = 'any';
		}
	}
	config_set_path('filter/rule', $filter_rule_config);
}

function upgrade_224_to_225() {
	$if_config = config_get_path('interfaces');

	/* DHCP6 now uses single config for all interfaces
	 * see https://redmine.pfsense.org/issues/6880 */
	foreach ($if_config as & $inf) {
		if (isset($inf['dhcp6debug'])) {
			config_set_path('system/dhcp6debug', true);
			unset($inf['dhcp6debug']);
		}
		if (isset($inf['dhcp6norelease'])) {
			config_set_path('system/dhcp6norelease', true);
			unset($inf['dhcp6norelease']);
		}
	}
	config_set_path('interfaces', $if_config);
}

function upgrade_225_to_226() {
	/* Update value of state killing on gateway failure.
	 * https://redmine.pfsense.org/issues/12092
	 */
	if (config_path_enabled('system', 'gw_down_kill_states')) {
		config_set_path('system/gw_down_kill_states', 'all');
	}
}

function upgrade_226_to_227() {
	/* Convert dnsmasq (forwarder) custom options to base64.
	 * https://redmine.pfsense.org/issues/13105
	 */
	if (!empty(config_get_path('dnsmasq/custom_options'))) {
		config_set_path('dnsmasq/custom_options', base64_encode(config_get_path('dnsmasq/custom_options')));
	}
}

function upgrade_227_to_228() {
	$ipsec_config = config_get_path('ipsec');

	$any_removed = false;
	/* We no longer support 3des, blowfish, cast128 or md5 and sha1
	 * authentication for IPSec. */
	if (is_array($ipsec_config)) {
		if (is_array($ipsec_config['phase1'])) {
			foreach ($ipsec_config['phase1'] as & $phase1) {
				if (! isset($phase1['encryption']) || !is_array($phase1['encryption']['item']))
					continue;

				$bad_count = 0;
				foreach ($phase1['encryption']['item'] as $k => $enc) {
					$bad = false;
					if (isset($enc['encryption-algorithm']['name']) &&
					    in_array($enc['encryption-algorithm']['name'],
					    array("blowfish", "3des", "cast128"))) {
						$bad = true;
					}
					if (isset($enc['hash-algorithm']) && $enc['hash-algorithm'] == "md5") {
						$bad = true;
					}
					if ($bad) {
						/* Remove this item as it contains deprecated encryption or hashing */
						unset($phase1['encryption']['item'][$k]);
						$bad_count++;
					}
				}
				if ($bad_count > 0) {
					$any_removed = true;
					/* Only notify once per P1 */
					if (count($phase1['encryption']['item']) == 0) {
						/* Only disable P1 if there are no valid encryption options left. */
						$phase1['disabled'] = true;
						unset($phase1['encryption']);
						file_notice("IPsec", sprintf(gettext("IPsec Phase 1 '%s' disabled after removing deprecated encryption and hashing algorithms as it has no remaining valid entries."), $phase1['descr']));
					} else {
						/* Let the user know that the P1 was adjusted */
						file_notice("IPsec", sprintf(gettext("Removed deprecated encryption options from IPsec Phase 1 '%s'."), $phase1['descr']));
					}
				}
			}
		}
		if (is_array($ipsec_config['phase2'])) {
			foreach ($ipsec_config['phase2'] as & $phase2) {

				$bad_count = 0;
				if (is_array($phase2['encryption-algorithm-option'])) {
					foreach ($phase2['encryption-algorithm-option'] as $k => $opt) {
						if (in_array($opt['name'], array("blowfish", "3des", "cast128"))) {
							/* Remove this item as it contains deprecated encryption */
							unset($phase2['encryption-algorithm-option'][$k]);
							$bad_count++;
						}
					}
				}
				if (is_array($phase2['hash-algorithm-option'])) {
					foreach ($phase2['hash-algorithm-option'] as $k => $opt) {
						if ($opt == "hmac_md5") {
							/* Remove this item as it contains deprecated hashing */
							unset($phase2['hash-algorithm-option'][$k]);
							$bad_count++;
						}
					}
				}

				if ($bad_count > 0) {
					$any_removed = true;
					/* Only notify once per P2 */
					if ((count($phase2['encryption-algorithm-option']) == 0) ||
					    (count($phase2['hash-algorithm-option']) == 0)) {
						/* Only disable P2 if there are no valid encryption options left. */
						$phase2['disabled'] = true;
						file_notice("IPsec", sprintf(gettext("IPsec Phase 2 '%s' disabled after removing deprecated encryption and hashing algorithms as it has no remaining valid combinations of options."), $phase2['descr']));
					} else {
						/* Let the user know that the P2 was adjusted */
						file_notice("IPsec", sprintf(gettext("Removed deprecated encryption options from IPsec Phase 2 '%s'."), $phase2['descr']));
					}
				}
			}
		}
		config_set_path('ipsec', $ipsec_config);
	}

	/* Only list deprecated types once */
	if ($any_removed) {
		file_notice("IPsec", gettext("One or more IPsec entries contained deprecated algorithms. The following are no longer supported: 3DES encryption, Blowfish encryption, CAST128 encryption, MD5 hashing."));
	}
}

function upgrade_228_to_229() {
	global $g;
	/* Update System Memory RRD file with new data sources
	 * https://redmine.pfsense.org/issues/14011
	 */
	$rrddbpath = "/var/db/rrd/";
	$database = "system-memory.rrd";
	$rrdtool = "/usr/bin/nice -n20 /usr/local/bin/rrdtool";
	$rrdinterval = 60;
	$valid = $rrdinterval * 2;
	if (is_platform_booting()) {
		echo "Migrating System Memory RRD file to new format\n";
	}
	mwexec("$rrdtool tune {$rrddbpath}{$database} DS:userwire:GAUGE:{$valid}:0:10000000 2>&1");
	mwexec("$rrdtool tune {$rrddbpath}{$database} DS:laundry:GAUGE:{$valid}:0:10000000 2>&1");
	mwexec("$rrdtool tune {$rrddbpath}{$database} DS:buffers:GAUGE:{$valid}:0:10000000 2>&1");
}

function upgrade_229_to_230() {
	/* The "target" GUI for outbound NAT rules now behaves similarly to other pages. */
	$obn_rules = config_get_path('nat/outbound/rule', []);
	foreach ($obn_rules as & $obent) {
		if (empty($obent['target'])) {
			// migrate interface address selection
			$obent['target'] = $obent['interface'] . 'ip';
			$obent['target_subnet'] = '';
		} elseif ($obent['target'] == 'other-subnet') {
			// migrate custom subnets
			$obent['target'] = $obent['targetip'];
			$obent['target_subnet'] = (get_specialnet($obent['target'], [SPECIALNET_IFADDR])) ? '' : $obent['targetip_subnet'];
		} else {
			// migrate VIPs and aliases
			$obent['target_subnet'] = '';
		}
		foreach (['targetip', 'targetip_subnet'] as $old) {
			if (array_key_exists($old, $obent)) {
				unset($obent[$old]);
			}
		}
		// migrate destination address
		if (array_key_exists('address', $obent['destination'])) {
			$obent['destination']['network'] = $obent['destination']['address'];
			unset($obent['destination']['address']);
		}
	}
	if (isset($obent)) {
		unset($obent);
	}
	config_set_path('nat/outbound/rule', $obn_rules);

	/* A reserved keyword suffix has been added; migrate conflicting aliases */
	$aliases = config_get_path('aliases/alias', []);
	$aliases_rename = [];
	$rename_suffix = '__RENAMED';
	$reserved_suffix = '__NETWORK';
	$interfaces = array_merge(array_keys(config_get_path('interfaces', [])),
	                          array_column(config_get_path('ifgroups/ifgroupentry', []), 'ifname'));
	$interfaces = array_map('strtoupper', $interfaces);
	foreach ($aliases as & $alias) {
		// varify that a name exists; alias names cannot start with a number
		if (empty($alias['name'])) {
			continue;
		}

		// check if the alias could conflict
		if ($alias['name'] == $reserved_suffix || !str_ends_with($alias['name'], $reserved_suffix)) {
			continue;
		}

		// check if the alias would conflict with an interface name
		if (!in_array(stristr($alias['name'], $reserved_suffix, true), $interfaces)) {
			continue;
		}

		// alias conflicts, attempt to rename it and flag it for references update
		if (in_array($alias['name'] . $rename_suffix, array_column($aliases, 'name'))) {
			file_notice('Alias', gettext('The following alias conflicts with a reserved keyword and must' .
			            ' be manually renamed: ' . $alias['name']));
		} else {
			$aliases_rename[] = $alias['name'];
			$alias['name'] .= $rename_suffix;
		}
	}
	if (isset($alias)) {
		unset($alias);
	}
	if (!empty($aliases_rename)) {
		// commit alias changes
		config_set_path('aliases/alias', $aliases);
		// update alias references
		foreach ($aliases_rename as $conflicting_name) {
			update_alias_name($conflicting_name . $rename_suffix, $conflicting_name);
		}
		file_notice('Alias', gettext('The following aliases conflict with a reserved keyword and have been renamed: ') .
		            implode(', ', $aliases_rename));
	}
}

/*
 * Check OpenVPN instances for deprecated algorithms and weak certificate
 * digests.
 * https://redmine.pfsense.org/issues/14677
 * https://redmine.pfsense.org/issues/14686
 */
function upgrade_230_to_231() {
	/* Load lists of current usable algorithms */
	$cipher_validation_list = array_keys(openvpn_get_cipherlist());
	$digest_validation_list = array_keys(openvpn_get_digestlist());
	$changed = false;
	$disabled = false;
	foreach(array('server', 'client') as $mode) {
		foreach (config_get_path("openvpn/openvpn-{$mode}", []) as $idx => $settings) {
			$thischanged = false;
			$thisdisabled = false;
			/* Check data_ciphers and filter out any deprecated items */
			if (!empty($settings['data_ciphers'])) {
				$dc = explode(',', $settings['data_ciphers']);
				/* Retain valid entries only, in the original order */
				$dc = array_intersect($dc, $cipher_validation_list);
				$dc = implode(',', $dc);
				if ($settings['data_ciphers'] != $dc) {
					$settings['data_ciphers'] = $dc;
					$thischanged = true;
				}
			}
			/* If no supported ciphers remain, replace with default set. */
			if (empty($settings['data_ciphers'])) {
				$settings['data_ciphers'] = 'AES-256-GCM,AES-128-GCM,CHACHA20-POLY1305';
				$thischanged = true;
			}

			/* Check data_ciphers_fallback and if it's deprecated, replace with 'AES-256-CBC' */
			if (!in_array($settings['data_ciphers_fallback'], $cipher_validation_list)) {
				$settings['data_ciphers_fallback'] = 'AES-256-CBC';
				$thischanged = true;
			}

			/* Check digest and if it's deprecated, replace with 'SHA256' */
			if (!empty($settings['digest']) &&
			    !in_array($settings['digest'], $digest_validation_list)) {
				$settings['digest'] = 'SHA256';
				$thischanged = true;
			}

			/* If using SSL/TLS, check if (server|client) certificate is weak, if so, disable tunnel */
			if (!empty($settings['certref'])) {
				$cert = lookup_cert($settings['certref']);
				$cert = $cert['item'];
				/* Disable only if weak and not already disabled */
				if (cert_has_weak_digest($cert['crt']) &&
					!isset($settings['disable'])) {
					/* Weak digest, disable tunnel. */
					$settings['disable'] = true;
					$thisdisabled = true;
				}
			}

			/* If any changes were made, alert user (clarify changed vs disabled). */
			if ($thisdisabled) {
				$disabled = true;
			}
			if ($thischanged) {
				$changed = true;
			}

			/* Save changes (if any were made) */
			if ($thisdisabled || $thischanged) {
				config_set_path("openvpn/openvpn-{$mode}/{$idx}", $settings);
			}
		}
	}
	if ($changed) {
		file_notice("OpenVPN", gettext("One or more OpenVPN entries used deprecated algorithms and has been updated to secure defaults."));
	}
	if ($disabled) {
		file_notice("OpenVPN", gettext("One or more OpenVPN entries has been disabled because it used a certificate with a deprecated weak digest algorithm."));
	}
}

/*
 * Check GUI and Captive Portal zones for certs with weak digests
 * https://redmine.pfsense.org/issues/14672
 */
function upgrade_231_to_232() {
	/* Check GUI if it is set for HTTPS */
	if (config_get_path('system/webgui/protocol') == "https" &&
	    !empty(config_get_path('system/webgui/ssl-certref'))) {
		$cert = lookup_cert(config_get_path('system/webgui/ssl-certref'));
		$cert = $cert['item'];
		/* If the GUI certificate is invalid or weak, replace it */
		if (!is_array($cert) || !$cert['crt'] || !$cert['prv'] ||
		    cert_chain_has_weak_digest($cert)) {
			$cert = cert_create_selfsigned('', '', false);
			if (is_array($cert) && !empty($cert)) {
				config_set_path('system/webgui/ssl-certref', $cert['refid']);
				file_notice("GUI", gettext("The GUI HTTPS certificate used a deprecated algorithm and has been replaced with a secure default."));
			}
		}
	}

	/* Check each portal zone */
	foreach (config_get_path('captiveportal', []) as $zone => $portal) {
		/* Only act if the portal is enabled and using HTTPS login */
		if (!isset($portal['enable']) ||
		    !isset($portal['httpslogin'])||
		    empty($portal['certref'])) {
			continue;
		}

		$cert = lookup_cert($portal['certref']);
		$cert = $cert['item'];

		/* If the cert is invalid or weak, generate a new self-signed
		 * cert using configured HTTPS server name, if present */
		if (!is_array($cert) ||
		    !$cert['crt'] ||
		    !$cert['prv'] ||
		    cert_chain_has_weak_digest($cert)) {

			if (!empty($portal['httpsname']) &&
			    (is_hostname($portal['httpsname']) ||
			    is_fqdn($portal['httpsname']))) {
				$hostname = $portal['httpsname'];
			} else {
				$hostname = '';
			}
			$cert = cert_create_selfsigned("Captive Portal Zone {$zone}", $hostname, false);
			if (is_array($cert) && !empty($cert)) {
				config_set_path("captiveportal/{$zone}/certref", $cert['refid']);

				file_notice("Captive Portal",
					sprintf(gettext("The HTTPS certificate for Captive Portal Zone %s used ".
							"a deprecated algorithm and has been replaced with a secure default."), $zone));
			}
		}
	}
}

/* Update language internal IDs to match OS Locales where possible
 * Some languages do not have direct equivalents.
 * https://redmine.pfsense.org/issues/13776 */
function upgrade_232_to_233() {
	$lang_changes= [
		"nl" => "nl_NL",
		"fr" => "fr_FR",
		"it" => "it_IT",
		"ko" => "ko_KR",
		"nb" => "nb_NO",
		"pl" => "pl_PL",
		"ru" => "ru_RU",
		"es" => "es_ES",
	];
	$curlang = config_get_path('system/language');

	/* If the language is set and is one in our list of languages to rename,
	 * then update the language appropriately. */
	if (!empty($curlang) &&
	    array_key_exists($curlang, $lang_changes)) {
		config_set_path('system/language', $lang_changes[$curlang]);
	}
}

/* OpenVPN Client-Specific Override options have changed.
 * https://redmine.pfsense.org/issues/12522 */
 function upgrade_233_to_234() {
	$openvpn_csc_config = config_get_path('openvpn/openvpn-csc', []);
	if (empty($openvpn_csc_config)) {
		return;
	}
	foreach ($openvpn_csc_config as &$settings) {
		if (!is_array($settings)) {
			continue;
		}

		// Migrate "remove_route"
		if (isset($settings['remove_route'])) {
			$settings['remove_options'] = 'remove_route';
			unset($settings['remove_route']);
		}
	}
	config_set_path('openvpn/openvpn-csc', $openvpn_csc_config);
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
