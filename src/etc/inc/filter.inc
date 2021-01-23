<?php
/*
 * filter.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2006 Peter Allgeyer
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

/* holds the items that will be executed *AFTER* the filter is fully loaded */
$after_filter_configure_run = array();

/* For installing cron job of schedules */
$time_based_rules = false;

/* Used to hold the interface list that will be used on ruleset creation. */
$FilterIflist = array();

/* Create a global array to avoid errors on rulesets. */
$GatewaysList = array();

/* Used for the hostname dns resolver */
$filterdns = array();

/* Used for aliases and interface macros */
$aliases = "";

/* ICMP v4+v6 subtypes */
$icmptypes = array(
	'althost'	=> array('descrip' => gettext('Alternate Host'), 'valid4' => true, 'valid6' => false),
 	'dataconv'	=> array('descrip' => gettext('Datagram conversion error'), 'valid4' => true, 'valid6' => false),
 	'echorep'	=> array('descrip' => gettext('Echo reply'), 'valid4' => true, 'valid6' => true),
 	'echoreq'	=> array('descrip' => gettext('Echo request'), 'valid4' => true, 'valid6' => true),
 	'fqdnrep'	=> array('descrip' => gettext('FQDN reply'), 'valid4' => false, 'valid6' => true),
 	'fqdnreq'	=> array('descrip' => gettext('FQDN query'), 'valid4' => false, 'valid6' => true),
 	'groupqry'	=> array('descrip' => gettext('Group membership query'), 'valid4' => false, 'valid6' => true),
 	'grouprep'	=> array('descrip' => gettext('Group membership report'), 'valid4' => false, 'valid6' => true),
 	'groupterm'	=> array('descrip' => gettext('Group membership termination'), 'valid4' => false, 'valid6' => true),
 	'inforep'	=> array('descrip' => gettext('Information reply'), 'valid4' => true, 'valid6' => false),
 	'inforeq'	=> array('descrip' => gettext('Information request'), 'valid4' => true, 'valid6' => false),
 	'ipv6-here'	=> array('descrip' => gettext('IPv6 I-am-here'), 'valid4' => true, 'valid6' => false),
 	'ipv6-where'	=> array('descrip' => gettext('IPv6 where-are-you'), 'valid4' => true, 'valid6' => false),
 	'listendone'	=> array('descrip' => gettext('Multicast listener done'), 'valid4' => false, 'valid6' => true),
 	'listenrep'	=> array('descrip' => gettext('Multicast listener report'), 'valid4' => false, 'valid6' => true),
 	'listqry'	=> array('descrip' => gettext('Multicast listener query'), 'valid4' => false, 'valid6' => true),
 	'maskrep'	=> array('descrip' => gettext('Address mask reply'), 'valid4' => true, 'valid6' => false),
 	'maskreq'	=> array('descrip' => gettext('Address mask request'), 'valid4' => true, 'valid6' => false),
 	'mobredir'	=> array('descrip' => gettext('Mobile host redirect'), 'valid4' => true, 'valid6' => false),
 	'mobregrep'	=> array('descrip' => gettext('Mobile registration reply'), 'valid4' => true, 'valid6' => false),
 	'mobregreq'	=> array('descrip' => gettext('Mobile registration request'), 'valid4' => true, 'valid6' => false),
 	'mtrace'	=> array('descrip' => gettext('mtrace messages'), 'valid4' => false, 'valid6' => true),
 	'mtraceresp'	=> array('descrip' => gettext('mtrace resp'), 'valid4' => false, 'valid6' => true),
 	'neighbradv'	=> array('descrip' => gettext('Neighbor advertisement'), 'valid4' => false, 'valid6' => true),
 	'neighbrsol'	=> array('descrip' => gettext('Neighbor solicitation'), 'valid4' => false, 'valid6' => true),
 	'niqry'		=> array('descrip' => gettext('Node information request'), 'valid4' => false, 'valid6' => true),
 	'nirep'		=> array('descrip' => gettext('Node information reply'), 'valid4' => false, 'valid6' => true),
 	'paramprob'	=> array('descrip' => gettext('Parameter problem (invalid IP header)'), 'valid4' => true, 'valid6' => true),
 	'photuris'	=> array('descrip' => gettext('Photuris'), 'valid4' => true, 'valid6' => false),
 	'redir'		=> array('descrip' => gettext('Redirect'), 'valid4' => true, 'valid6' => true),
 	'routeradv'	=> array('descrip' => gettext('Router advertisement'), 'valid4' => true, 'valid6' => true),
 	'routersol'	=> array('descrip' => gettext('Router solicitation'), 'valid4' => true, 'valid6' => true),
 	'routrrenum'	=> array('descrip' => gettext('Router renumbering'), 'valid4' => false, 'valid6' => true),
 	'skip'		=> array('descrip' => gettext('SKIP'), 'valid4' => true, 'valid6' => false),
 	'squench'	=> array('descrip' => gettext('Source quench'), 'valid4' => true, 'valid6' => false),
 	'timerep'	=> array('descrip' => gettext('Timestamp reply'), 'valid4' => true, 'valid6' => false),
 	'timereq'	=> array('descrip' => gettext('Timestamp'), 'valid4' => true, 'valid6' => false),
 	'timex'		=> array('descrip' => gettext('Time exceeded'), 'valid4' => true, 'valid6' => true),
 	'toobig'	=> array('descrip' => gettext('Packet too big'), 'valid4' => false, 'valid6' => true),
 	'trace'		=> array('descrip' => gettext('Traceroute'), 'valid4' => true, 'valid6' => false),
 	'unreach'	=> array('descrip' => gettext('Destination unreachable'), 'valid4' => true, 'valid6' => true),
 	'wrurep'	=> array('descrip' => gettext('Who are you reply'), 'valid4' => false, 'valid6' => true),
 	'wrureq'	=> array('descrip' => gettext('Who are you request'), 'valid4' => false, 'valid6' => true)
);

/*
 * Interfaces that can be assigned but do not directly support filtering in pf.
 * Specify the driver prefix to match (from the left)
 * https://redmine.pfsense.org/issues/8685
 */
global $filter_interface_blacklist;
$filter_interface_blacklist = array("ipsec");

/*
 * Fixed tracker values (used to group and track usage in GUI):
 *
 * anti-lockout rules:	10000
 * bogons rules:	11000
 * RFC1918 rules:	12000
 *
 */

define("ANTILOCKOUT_TRACKER_START", 10000);
define("ANTILOCKOUT_TRACKER_END", 10999);
define("BOGONS_TRACKER_START", 11000);
define("BOGONS_TRACKER_END", 11999);
define("RFC1918_TRACKER_START", 12000);
define("RFC1918_TRACKER_END", 12999);
define("PFLABEL_MAXLEN", 63);
define("USER_LABEL_INTRO", "USER_RULE: ");

$tracker = 1000000000;
$negate_tracker = 10000000;
$antilockout_tracker = ANTILOCKOUT_TRACKER_START;
$bogons_tracker = BOGONS_TRACKER_START;
$rfc1918_tracker = RFC1918_TRACKER_START;

function filter_antilockout_tracker() {
	global $antilockout_tracker;

	return (++$antilockout_tracker);
}

function filter_bogons_tracker() {
	global $bogons_tracker;

	return (++$bogons_tracker);
}

function filter_rfc1918_tracker() {
	global $rfc1918_tracker;

	return (++$rfc1918_tracker);
}

function filter_rule_tracker() {
	global $tracker;

	return (++$tracker);
}

function filter_negaterule_tracker() {
        global $negate_tracker;

        ++$negate_tracker;
        return "tracker {$negate_tracker} ";
}

function user_rule_descr_maxlen() {
	return PFLABEL_MAXLEN - strlen(USER_LABEL_INTRO);
}

function fix_rule_label($descr) {
	$descr = str_replace('"', '', $descr);
	if (strlen($descr) > PFLABEL_MAXLEN) {
		$dots = "...";
		return substr($descr, 0, PFLABEL_MAXLEN - strlen($dots)) . $dots;
	} else {
		return $descr;
	}
}

function is_bogonsv6_used($force = false) {
	global $config, $g;
	# Only use bogonsv6 table if IPv6 Allow is on, and at least 1 enabled interface also has "blockbogons" enabled.
	$usebogonsv6 = false;
	if (isset($config['system']['ipv6allow']) || $force) {
		foreach ($config['interfaces'] as $ifacedata) {
			if (isset($ifacedata['enable']) && isset($ifacedata['blockbogons'])) {
				$usebogonsv6 = true;
				break;
			}
		}
	}
	return $usebogonsv6;
}

function filter_pflog_start($kill_first = false) {
	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}
	if ((!file_exists("{$g['varrun_path']}/filterlog.pid")) ||
	    (!isvalidpid("{$g['varrun_path']}/filterlog.pid"))) {
		mwexec("/usr/local/sbin/filterlog -i pflog0 -p {$g['varrun_path']}/filterlog.pid");
	}
}

/* reload filter async */
function filter_configure() {
	global $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure() being called $mt\n";
	}

	/*
	 * NOTE: Check here for bootup status since this should not be triggered during bootup.
	 *	 The reason is that rc.bootup calls filter_configure_sync directly which does this too.
	 */
	if (!platform_booting()) {
		send_event("filter reload");
	}
}

function filter_delete_states_for_down_gateways() {
	global $config, $GatewaysList;

	if (!isset($config['system']['gw_down_kill_states'])) {
		return;
	}

	$any_gateway_down = false;
	$a_gateways = return_gateways_status();
	if (is_array($GatewaysList)) {
		foreach ($GatewaysList as $gwname => $gateway) {
			if (empty($gateway['monitor'])) {
				continue;
			}
			if (!is_ipaddr($gateway['monitor'])) {
				continue;
			}
			if (strstr($gateway['monitor'], "127.0.0.")) {
				continue;
			}
			if (empty($a_gateways[$gateway['monitor']])) {
				continue;
			}
			$gwstatus = &$a_gateways[$gateway['monitor']];
			if (strstr($gwstatus['status'], "down")) {
				$any_gateway_down = true;
				break;
			}
		}
	}
	if ($any_gateway_down == true) {
		mwexec("/sbin/pfctl -Fs");
	}
}

/* reload filter sync */
function filter_configure_sync($delete_states_if_needed = true) {
	global $config, $g, $after_filter_configure_run, $FilterIflist;
	global $time_based_rules, $filterdns, $aliases, $dummynet_name_list;

	/* Use filter lock to not allow concurrent filter reloads during this run. */
	$filterlck = lock('filter', LOCK_EX);

	filter_pflog_start();
	update_filter_reload_status(gettext("Initializing"), true);  // second argument = overwrite existing file

	/* invalidate interface cache */
	get_interface_arr(true);

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}
	/* Get interface list to work with. */
	filter_generate_optcfg_array();
	if (platform_booting() == true) {
		echo gettext("Configuring firewall");
	}

	/* generate aliases */
	if (platform_booting() == true) {
		echo ".";
	}
	update_filter_reload_status(gettext("Creating aliases"));
	$aliases = filter_generate_aliases();
	$gateways = filter_generate_gateways();
	if (platform_booting() == true) {
		echo ".";
	}
	update_filter_reload_status(gettext("Generating Limiter rules"));
	$dummynet_rules = filter_generate_dummynet_rules();
	$dummynet_name_list = get_unique_dnqueue_list();
	update_filter_reload_status(gettext("Generating NAT rules"));
	/* generate nat rules */
	$natrules = filter_nat_rules_generate();
	if (platform_booting() == true) {
		echo ".";
	}
	update_filter_reload_status(gettext("Generating filter rules"));
	/* generate pfctl rules */
	$pfrules = filter_rules_generate();
	/* generate altq, limiter */
	if (platform_booting() == true) {
		echo ".";
	}
	update_filter_reload_status(gettext("Generating ALTQ queues"));
	$altq_queues = filter_generate_altq_queues();
	if (platform_booting() == true) {
		echo ".";
	}
	update_filter_reload_status(gettext("Loading filter rules"));
	/* enable pf if we need to, otherwise disable */
	if (!isset ($config['system']['disablefilter'])) {
		mwexec("/sbin/pfctl -e", true);
	} else {
		mwexec("/sbin/pfctl -d", true);
		unlink_if_exists("{$g['tmp_path']}/filter_loading");
		update_filter_reload_status(gettext("Filter is disabled.  Not loading rules."));
		if (platform_booting() == true) {
			echo gettext("done.") . "\n";
		}
		unlock($filterlck);
		return;
	}

	$limitrules = "";
	/* User defined maximum table entries in Advanced menu. */
	if ($config['system']['maximumtableentries'] <> "" && is_numeric($config['system']['maximumtableentries'])) {
		$limitrules .= "set limit table-entries {$config['system']['maximumtableentries']}\n";
	}

	if ($config['system']['optimization'] <> "") {
		$limitrules .= "set optimization {$config['system']['optimization']}\n";
		if ($config['system']['optimization'] == "conservative") {
			$limitrules .= "set timeout { udp.first 300, udp.single 150, udp.multiple 900 }\n";
		}
	} else {
		$limitrules .= "set optimization normal\n";
	}

	$timeoutlist = "";
	if (isset($config['system']['tcpfirsttimeout']) && is_numericint($config['system']['tcpfirsttimeout'])) {
		$timeoutlist .= " tcp.first {$config['system']['tcpfirsttimeout']} ";
	}
	if (isset($config['system']['tcpopeningtimeout']) && is_numericint($config['system']['tcpopeningtimeout'])) {
		$timeoutlist .= " tcp.opening {$config['system']['tcpopeningtimeout']} ";
	}
	if (isset($config['system']['tcpestablishedtimeout']) && is_numericint($config['system']['tcpestablishedtimeout'])) {
		$timeoutlist .= " tcp.established {$config['system']['tcpestablishedtimeout']} ";
	}
	if (isset($config['system']['tcpclosingtimeout']) && is_numericint($config['system']['tcpclosingtimeout'])) {
		$timeoutlist .= " tcp.closing {$config['system']['tcpclosingtimeout']} ";
	}
	if (isset($config['system']['tcpfinwaittimeout']) && is_numericint($config['system']['tcpfinwaittimeout'])) {
		$timeoutlist .= " tcp.finwait {$config['system']['tcpfinwaittimeout']} ";
	}
	if (isset($config['system']['tcpclosedtimeout']) && is_numericint($config['system']['tcpclosedtimeout'])) {
		$timeoutlist .= " tcp.closed {$config['system']['tcpclosedtimeout']} ";
	}
	if (isset($config['system']['tcptsdifftimeout']) && is_numericint($config['system']['tcptsdifftimeout'])) {
		$timeoutlist .= " tcp.tsdiff {$config['system']['tcptsdifftimeout']} ";
	}
	if (isset($config['system']['udpfirsttimeout']) && is_numericint($config['system']['udpfirsttimeout'])) {
		$timeoutlist .= " udp.first {$config['system']['udpfirsttimeout']} ";
	}
	if (isset($config['system']['udpsingletimeout']) && is_numericint($config['system']['udpsingletimeout'])) {
		$timeoutlist .= " udp.single {$config['system']['udpsingletimeout']} ";
	}
	if (isset($config['system']['udpmultipletimeout']) && is_numericint($config['system']['udpmultipletimeout'])) {
		$timeoutlist .= " udp.multiple {$config['system']['udpmultipletimeout']} ";
	}
	if (isset($config['system']['icmpfirsttimeout']) && is_numericint($config['system']['icmpfirsttimeout'])) {
		$timeoutlist .= " icmp.first {$config['system']['icmpfirsttimeout']} ";
	}
	if (isset($config['system']['icmperrortimeout']) && is_numericint($config['system']['icmperrortimeout'])) {
		$timeoutlist .= " icmp.error {$config['system']['icmperrortimeout']} ";
	}
	if (isset($config['system']['otherfirsttimeout']) && is_numericint($config['system']['otherfirsttimeout'])) {
		$timeoutlist .= " other.first {$config['system']['otherfirsttimeout']} ";
	}
	if (isset($config['system']['othersingletimeout']) && is_numericint($config['system']['othersingletimeout'])) {
		$timeoutlist .= " other.single {$config['system']['othersingletimeout']} ";
	}
	if (isset($config['system']['othermultipletimeout']) && is_numericint($config['system']['othermultipletimeout'])) {
		$timeoutlist .= " other.multiple {$config['system']['othermultipletimeout']} ";
	}

	if ($timeoutlist <> "") {
		$limitrules .= "set timeout { $timeoutlist }\n";
	}

	if (is_numericint($config['system']['adaptivestart']) && is_numericint($config['system']['adaptiveend'])) {
		$limitrules .= "set timeout { adaptive.start {$config['system']['adaptivestart']}, adaptive.end {$config['system']['adaptiveend']} }\n";
	}

	if ($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* User defined maximum states in Advanced menu. */
		$limitrules .= "set limit states {$config['system']['maximumstates']}\n";
		$limitrules .= "set limit src-nodes {$config['system']['maximumstates']}\n";
	} else {
		$max_states = pfsense_default_state_size();
		$limitrules .= "set limit states {$max_states}\n";
		$limitrules .= "set limit src-nodes {$max_states}\n";
	}

	/* Frag limit. pf default is 5000 */
	if ($config['system']['maximumfrags'] <> "" && is_numeric($config['system']['maximumfrags'])) {
		$limitrules .= "set limit frags {$config['system']['maximumfrags']}\n";
	}

	if (isset($config['system']['lb_use_sticky']) && is_numeric($config['system']['srctrack']) && ($config['system']['srctrack'] > 0)) {
		$limitrules .= "set timeout src.track {$config['system']['srctrack']}\n";
	}

	$rules = "";
	$rules = "{$limitrules}\n";
	$rules .= "{$aliases} \n";
	$rules .= "{$gateways} \n";
	update_filter_reload_status(gettext("Setting up logging information"));
	$rules .= filter_setup_logging_interfaces();
	$rules .= "\n";
	$rules .= "set skip on pfsync0\n";
	$rules .= "\n";
	update_filter_reload_status(gettext("Setting up SCRUB information"));
	$rules .= filter_generate_scrubing();
	$rules .= "\n";
	$rules .= "{$altq_queues}\n";
	$rules .= "{$natrules}\n";
	$rules .= "{$pfrules}\n";
	$rules .= discover_pkg_rules("filter");

	unset($aliases, $gateways, $altq_queues, $natrules, $pfrules);

	if (!@file_put_contents("{$g['tmp_path']}/rules.debug", $rules, LOCK_EX)) {
		log_error("WARNING: Could not write new rules!");
		unlock($filterlck);
		return;
	}

	@file_put_contents("{$g['tmp_path']}/rules.limits", $limitrules);
	mwexec("/sbin/pfctl -Of {$g['tmp_path']}/rules.limits");
	unset($rules, $limitrules);

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "pfctl being called at $mt\n";
	}

	for ($pfctl_try = 1; $pfctl_try <= 10; $pfctl_try++) {
		unset($rules_loading, $rules_error);
		$_grbg = exec("/sbin/pfctl -o basic -f {$g['tmp_path']}/rules.debug 2>&1", $rules_error, $rules_loading);
		if (isset($config['system']['developerspew'])) {
			$mt = microtime();
			echo "pfctl done at $mt\n";
		}
		if ($rules_loading == 0) {
			// continue when successful
			if (isset($config['system']['developerspew']) && $pfctl_try > 1) {
				file_notice("filter_load", sprintf(gettext('pf was busy but succeeded after %s tries'), $pfctl_try), "Filter Reload", "");
			}
			break;
		}
		if (strstr($_grbg, "DIOCADDALTQ: Device busy") ||
		    strstr($_grbg, "DIOCADDRULE: Device busy") ||
		    strstr($_grbg, "DIOCXCOMMIT: Device busy")) {
			// when busy status is returned retry after a short pause
			usleep(200000);//try again after 200 ms..unless it still fails after 10x
		} else {
			// rule loading failed, no need to retry with the same ruleset
			break;
		}
	}
	/*
	 * check for a error while loading the rules file.	if an error has occurred
	 * then output the contents of the error to the caller
	 */
	if ($rules_loading <> 0) {
		foreach ($rules_error as $errorline) {
			$saved_line_error = $errorline;
			$line_error = explode(":", $errorline);
			$line_number = $line_error[1];
			$line_split = file("{$g['tmp_path']}/rules.debug");
			if (is_array($line_split)) {
				$line_error = sprintf(gettext('The line in question reads [%1$d]: %2$s'), $line_number, $line_split[(int) $line_number - 1]);
			}
			unset($line_split);

			/* Brutal ugly hack but required -- PF is stuck, unwedge */
			if (strstr("$rules_error[0]", "busy")) {
				exec("/sbin/pfctl -d; /sbin/pfctl -e; /sbin/pfctl -f {$g['tmp_path']}/rules.debug");
				$error_msg = gettext("PF was wedged/busy and has been reset.");
				file_notice("pf_busy", $error_msg, "pf_busy", "");
			} elseif (file_exists("{$g['cf_conf_path']}/rules.debug.old")) {
				$_grbg = exec("/sbin/pfctl -o basic -f {$g['cf_conf_path']}/rules.debug.old 2>&1");
			}
			if ($line_error and $line_number) {
				file_notice("filter_load", sprintf(gettext('There were error(s) loading the rules: %1$s - %2$s'), $saved_line_error, $line_error), "Filter Reload", "");
				update_filter_reload_status(sprintf(gettext('There were error(s) loading the rules: %1$s - %2$s'), $saved_line_error, $line_error));
				unlock($filterlck);
				return;
			}
		}
		unset($rules_loading, $rules_error);
	} else {
		/* Save the last version that works */
		@copy("{$g['tmp_path']}/rules.debug",
		    "{$g['cf_conf_path']}/rules.debug.old");
	}

	# If we are not using bogonsv6 then we can remove any bogonsv6 table from the running pf (if the table is not there, the kill is still fine).
	if (!is_bogonsv6_used()) {
		$_grbg = exec("/sbin/pfctl -t bogonsv6 -T kill 2>/dev/null");
	}

	global $reserved_table_names;
	// get aliases names from config
	$aliasesnames = array();
	init_config_arr(array('aliases', 'alias'));
	foreach ($config['aliases']['alias'] as $alias) {
		array_push($aliasesnames, $alias['name']);
	}
	// get aliases names from pfctl -sT
	exec("/sbin/pfctl -sT", $pftables);
	// compare config with pfctl aliases and reserved table names
	$diff = array_diff($pftables, $aliasesnames, $reserved_table_names);
	// delete unused aliases
	foreach ($diff as $alias) {
		$_grbg = exec("/sbin/pfctl -t " . escapeshellarg($alias) . " -T kill 2>/dev/null");
	}

	if (!platform_booting()) {
		if (!empty($filterdns)) {
			@file_put_contents("{$g['varetc_path']}/filterdns.conf", implode("", $filterdns));
			unset($filterdns);
			if (isvalidpid("{$g['varrun_path']}/filterdns.pid")) {
				sigkillbypid("{$g['varrun_path']}/filterdns.pid", "HUP");
			} else {
				/*
				 * FilterDNS has three debugging levels. The default chosen is 1.
				 * Available are level 2 and greater then 2.
				 */
				if (isset($config['system']['aliasesresolveinterval']) && is_numeric($config['system']['aliasesresolveinterval'])) {
					$resolve_interval = $config['system']['aliasesresolveinterval'];
				} else {
					$resolve_interval = 300;
				}
				mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns.pid -i {$resolve_interval} -c {$g['varetc_path']}/filterdns.conf -d 1");
			}
		} else {
			killbypid("{$g['varrun_path']}/filterdns.pid");
			@unlink("{$g['varrun_path']}/filterdns.pid");
		}
	}

	/* run items scheduled for after filter configure run */
	$fda = fopen("{$g['tmp_path']}/commands.txt", "w");
	if ($fda) {
		if ($after_filter_configure_run) {
			foreach ($after_filter_configure_run as $afcr) {
				fwrite($fda, $afcr . "\n");
			}
			unset($after_filter_configure_run);
		}

		/*
		 *      we need a way to let a user run a shell cmd after each
		 *      filter_configure() call.  run this xml command after
		 *      each change.
		 */
		if ($config['system']['afterfilterchangeshellcmd'] <> "") {
			fwrite($fda, $config['system']['afterfilterchangeshellcmd'] . "\n");
		}

		fclose($fda);
	}

	if (file_exists("{$g['tmp_path']}/commands.txt")) {
		mwexec("sh {$g['tmp_path']}/commands.txt &");
		unlink("{$g['tmp_path']}/commands.txt");
	}

	/* if time based rules are enabled then swap in the set */
	if ($time_based_rules == true) {
		filter_tdr_install_cron(true);
	} else {
		filter_tdr_install_cron(false);
	}

	if (platform_booting() == true) {
		echo ".";
	}

	if ($delete_states_if_needed) {
		update_filter_reload_status(gettext("Processing down interface states"));
		filter_delete_states_for_down_gateways();
	}

	update_filter_reload_status(gettext("Running plugins"));

	if (is_dir("/usr/local/pkg/pf/")) {
		/* process packager manager custom rules */
		update_filter_reload_status(gettext("Running plugins (pf)"));
		run_plugins("/usr/local/pkg/pf/");
		update_filter_reload_status(gettext("Plugins completed."));
	}

	update_filter_reload_status(gettext("Done"));
	if (platform_booting() == true) {
		echo gettext("done.") . "\n";
	}

	unlock($filterlck);
	return 0;
}

function filter_generate_scrubing() {
	global $config, $FilterIflist;
	$scrubrules = "";

	if (isset($config['system']['maxmss_enable'])) {
		$maxmss = 1400;
		if (!empty($config['system']['maxmss'])) {
			$maxmss = $config['system']['maxmss'];
		}

		$scrubrules .= "scrub from any to <vpn_networks> max-mss {$maxmss}\n";
		$scrubrules .= "scrub from <vpn_networks> to any max-mss {$maxmss}\n";
	}
	/* disable scrub option */
	foreach ($FilterIflist as $scrubif => $scrubcfg) {
		if (isset($scrubcfg['virtual']) || empty($scrubcfg['descr'])) {
			continue;
		}
		/* set up MSS clamping */
		if (($scrubcfg['mss'] <> "") &&
		    (is_numeric($scrubcfg['mss']))) {
			$mssclamp = "max-mss " . (intval($scrubcfg['mss'] - 40));
		} else {
			$mssclamp = "";
		}
		/* configure no-df for linux nfs and others */
		if ($config['system']['scrubnodf']) {
			$scrubnodf = "no-df";
		} else {
			$scrubnodf = "";
		}
		if ($config['system']['scrubrnid']) {
			$scrubrnid = "random-id";
		} else {
			$scrubrnid = "";
		}
		if (!isset($config['system']['disablescrub'])) {
			$scrubrules .= "scrub on \${$scrubcfg['descr']} all {$scrubnodf} {$scrubrnid} {$mssclamp} fragment reassemble\n"; // reassemble all directions
		} else if (!empty($mssclamp)) {
			$scrubrules .= "scrub on \${$scrubcfg['descr']} {$mssclamp}\n";
		}
	}
	return $scrubrules;
}

function filter_generate_nested_alias($name) {
	global $aliastable;

	$aliasnesting = array();
	$aliasaddrnesting = array();

	if (($name == "") || !isset($aliastable[$name])) {
		return "";
	}

	return filter_generate_nested_alias_recurse($name, $aliastable[$name], $aliasnesting, $aliasaddrnesting);
}

function filter_generate_nested_alias_recurse($name, $alias, &$aliasnesting, &$aliasaddrnesting, &$use_filterdns = false) {
	global $aliastable, $filterdns;

	$addresses = explode(" ", $alias);
	$finallist = "";
	$builtlist = "";
	$urltable_nesting = "";
	$aliasnesting[$name] = $name;
	$alias_type = alias_get_type($name);
	foreach ($addresses as $address) {
		if (empty($address)) {
			continue;
		}
		$linelength = strlen($builtlist);
		$tmpline = "";
		if (is_alias($address)) {
			if (alias_get_type($address) == 'urltable') {
				// Feature#1603. For this type of alias we do not need to recursively call filter_generate_nested_alias_recurse. Just load IPs from the file.
				$urltable_nesting = alias_expand_urltable($address);
				if (!empty($urltable_nesting)) {
					$urlfile_as_arr = file($urltable_nesting);
					foreach ($urlfile_as_arr as $line) {
						$address= rtrim($line);
						if ((strlen($tmpline) + $linelength) > 4036) {
							$finallist .= "{$tmpline} \\\n";
							$tmpline = "";
						}
						$tmpline .= " {$address}";
					}
				}
			}
			/* We already expanded this alias so there is no necessity to do it again. */
			else if (!isset($aliasnesting[$address])) {
				$tmpline = filter_generate_nested_alias_recurse($name, $aliastable[$address], $aliasnesting, $aliasaddrnesting, $use_filterdns);
			}
		} else if (!isset($aliasaddrnesting[$address])) {
			if (!is_ipaddr($address) && !is_subnet($address) && !((($alias_type == 'port') || ($alias_type == 'url_ports')) && is_port_or_range($address)) && is_hostname($address)) {
				$use_filterdns = true;
				if (!isset($filterdns["{$address}{$name}"])) {
					$filterdns["{$address}{$name}"] = "pf {$address} {$name}\n";
				}
				continue;
			}
			$aliasaddrnesting[$address] = $address;
			$tmpline = " {$address}";
		}
		if ((strlen($tmpline)+ $linelength) > 4036) {
			$finallist .= "{$builtlist} \\\n";
			$builtlist = "";
		}
		if (!empty($tmpline)) {
			$builtlist .= " {$tmpline}";
		}
	}
	$finallist .= $builtlist;

	if ($use_filterdns === true && !empty($finallist)) {
		foreach (explode(" ", $finallist) as $address) {
			if (empty($address)) {
				continue;
			}
			if ((is_ipaddr($address) || is_subnet($address)) && !isset($filterdns["{$address}{$name}"])) {
				$filterdns["{$address}{$name}"] = "pf {$address} {$name}\n";
			}
		}
		$finallist = '';
	}

	return $finallist;
}

function filter_expand_alias($alias_name) {
	global $config;

	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			if ($aliased['name'] == $alias_name) {
				return filter_generate_nested_alias($aliased['name']);
			}
		}
	}
}

function filter_expand_alias_array($alias_name) {
	$expansion = filter_expand_alias($alias_name);
	return explode(" ", preg_replace('/\s+/', ' ', trim($expansion)));
}

function filter_generate_aliases() {
	global $g, $config, $FilterIflist, $after_filter_configure_run;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_aliases() being called $mt\n";
	}

	$alias = "#System aliases\n ";
	$aliases = "loopback = \"{ lo0 }\"\n";

	foreach ($FilterIflist as $if => $ifcfg) {
		if (is_array($ifcfg[0])) {
			if ($ifcfg[0]['if'] == 'pppoe') {
				$aliases .= "{$ifcfg[0]['descr']} = \"{ {$ifcfg[0]['if']}";
				$aliases .= " }\"\n";
			}
		} elseif (!empty($ifcfg['descr']) && !empty($ifcfg['if'])) {
			if (($ifcfg['type6'] == '6rd') || ($ifcfg['type6'] == '6to4')) {
				$aliases .= "{$ifcfg['descr']} = \"{ {$ifcfg['if']} {$if}_stf }\"\n";
				$aliases .= "{$ifcfg['descr']}_STF = \"{ {$if}_stf";
			} else {
				$aliases .= "{$ifcfg['descr']} = \"{ {$ifcfg['if']}";

				if ($ifcfg['type'] == 'pptp') {
					foreach (get_parent_interface($ifcfg['if']) as $parent_if) {
						if ($parent_if != $ifcfg['if']) {
							$aliases .= " {$parent_if}";
						}
					}
				}
			}
			$aliases .= " }\"\n";
		}
	}

	$aliases .= "\n#SSH Lockout Table\n";
	$aliases .= "table <sshguard> persist\n";

	$aliases .= "#Snort tables\n";
	$aliases .= "table <snort2c>\n";
	$aliases .= "table <virusprot>\n";
	if (!file_exists("/etc/bogons") || !file_exists("/etc/bogonsv6")) {
		if (!file_exists("/etc/bogons")) {
			@file_put_contents("/etc/bogons", "");
		}
		if (!file_exists("/etc/bogonsv6")) {
			@file_put_contents("/etc/bogonsv6", "");
		}
	}
	$aliases .= "table <bogons> persist file \"/etc/bogons\"\n";
	if (is_bogonsv6_used()) {
		if (!isset($config['system']['maximumtableentries']) ||
		     $config['system']['maximumtableentries'] <
		     $g['minimumtableentries_bogonsv6']) {
			file_notice("Filter_Reload", sprintf(gettext(
			    "To block bogon IPv6 networks the Firewall Maximum Table Entries value in System / Advanced / Firewall must be increased at least to %s"),
			    $g['minimumtableentries_bogonsv6']));
		} else {
			$aliases .= "table <bogonsv6> persist file \"/etc/bogonsv6\"\n";
		}
	}

	$vpns_list = filter_get_vpns_list();
	if ($vpns_list) {
		$aliases .= "table <vpn_networks> { $vpns_list }\n";
	}

	/* add a Negate_networks table */
	$aliases .= "table <negate_networks> ";
	if ($vpns_list) {
		$aliases .= "{ $vpns_list }";
	}
	$aliases .= "\n";

	$aliases .= "\n# User Aliases \n";
	/* Setup pf groups */
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			if (is_numericint($aliased['name'])) {
				// skip aliases with numeric-only names. redmine #4289
				file_notice("Filter_Reload", sprintf(gettext("Aliases with numeric-only names are not valid. Skipping alias %s"), $aliased['name']));
				continue;
			}
			$addrlist = filter_generate_nested_alias($aliased['name']);
			switch ($aliased['type']) {
			case "host":
			case "network":
			case "url":
				$tableaddrs = "{$addrlist}";
				if (empty($tableaddrs)) {
					$aliases .= "table <{$aliased['name']}> persist\n";
					if (empty($aliased['address'])) {
						$after_filter_configure_run[] = "/sbin/pfctl -T flush -t " . escapeshellarg($aliased['name']);
					}
				} else {
					$aliases .= "table <{$aliased['name']}> { {$addrlist} } \n";
				}

				$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				break;
			case "urltable":
				$urlfn = alias_expand_urltable($aliased['name']);
				if ($urlfn) {
					$aliases .= "table <{$aliased['name']}> persist file \"{$urlfn}\"\n";
					$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				}
				break;
			case "urltable_ports":
				// TODO: Change it when pf supports tables with ports
				$urlfn = alias_expand_urltable($aliased['name']);
				if ($urlfn) {
					$ports_tmp = parse_aliases_file($urlfn, "urltable_ports", "-1", false);
					$aliases .= "{$aliased['name']} = \"{ " . preg_replace("/\n/", " ", implode("\n", $ports_tmp)) . " }\"\n";
				}
				break;
			case "port":
			case "url_ports":
				$aliases .= "{$aliased['name']} = \"{ {$addrlist} }\"\n";
				break;
			default:
				$aliases .= "{$aliased['name']} = \"{ {$aliased['address']} }\"\n";
				break;
			}
		}
	}
	$result = "{$alias} \n";
	$result .= "{$aliases}";

	return $result;
}

function filter_generate_gateways() {
	global $config, $g, $GatewaysList;

	$rules = "# Gateways\n";

	update_filter_reload_status(gettext("Creating gateway group item..."));

	/* Lookup Gateways to be used in filter rules once */
	$GatewaysList = return_gateways_array();
	$GatewayGroupsList = return_gateway_groups_array(true);
	$GatewaysStatus = return_gateways_status(true);

	if (is_array($GatewaysList)) {
		foreach ($GatewaysList as $gwname => $gateway) {
			$int = $gateway['interface'];
			$gwip = $gateway['gateway'];
			$route = "";
			if (!is_ipaddr($gwip)) {
				$gwip = get_interface_gateway($gateway['friendlyiface']);
			}
			if (is_ipaddr($gwip) && !empty($int) && !isset($gateway['force_down']) &&
			    (($GatewaysStatus[$gwname]['status'] != 'down') || isset($gateway['action_disable']))) {
				$route = "route-to ( {$int} {$gwip} )";
			}
			if (($route === "") && isset($config['system']['skip_rules_gw_down'])) {
				unset($GatewaysList[$gwname]);
			} else {
				$rules .= "GW{$gwname} = \" {$route} \"\n";
			}
		}
	}

	if (is_array($GatewayGroupsList)) {
		foreach ($GatewayGroupsList as $gateway => $members) {
			$route = "";
			/* hey, that's not a group member! */
			unset($members['ipprotocol']);
			unset($members['descr']);
			if (count($members) > 0) {
				$foundlb = 0;
				$routeto = "";
				$routetomembers = 0;
				$trigger = "";
				foreach ($config['gateways']['gateway_group'] as $group) {
					if ($group['name'] == $gateway) {
						$trigger = $group['trigger'];
						break;
					}
				}
				/* see "Load balancing fails when one gateway has a weight of 1 
				* and another gateway has a weight >1", https://redmine.pfsense.org/issues/6025 */
				foreach ($members as $idx => $member) {
					if ((int) $member['weight'] == 1) {
						$weight1 = true;
					} elseif ((int) $member['weight'] > 1) {
						$weightgt1 = true;
					}
				}
				if ($weight1 && $weightgt1) {
					$mult = 2;
				} else {
					$mult = 1;
				}
				foreach ($members as $idx => $member) {
					$int = $member['int'];
					$gatewayip = $member['gwip'];
					if (!empty($int) && is_ipaddr($gatewayip) &&
					    isset($GatewaysList[$member['gw']]) &&
					    (isset($GatewaysList[$member['gw']]['action_disable']) ||
					    (!isset($GatewaysList[$member['gw']]['force_down']) &&
					    ((($GatewaysStatus[$member['gw']]['status'] != 'down') &&
					    ($trigger == 'down')) ||
					    (($GatewaysStatus[$member['gw']]['substatus'] != 'highloss') &&
					    stristr($trigger, 'loss')) ||
					    (($GatewaysStatus[$member['gw']]['substatus'] != 'highdelay') &&
					    stristr($trigger, 'latency')))))) {
						if ($g['debug']) {
							log_error(sprintf(gettext('Setting up route with %1$s on %2$s'), $gatewayip, $int));
						}
						if ($routetomembers + ((int) $member['weight'] * $mult) > 384) {
							// would create invalid ruleset, bail
							log_error(sprintf(gettext("Too many members in group %s, gateway group truncated in ruleset."), $member['name']));
							continue;
						}
						if ((int) $member['weight'] > 0) {
							$routeto .= str_repeat("( {$int} {$gatewayip} ) ", $member['weight'] * $mult);
							$routetomembers += (int) $member['weight'];
						} else {
							$routeto .= "( {$int} {$gatewayip} ) ";
							$routetomembers++;
						}
						$foundlb++;
					} else {
						log_error(sprintf(gettext("An error occurred while trying to find the interface got %s .  The rule has not been added."), $gatewayip));
					}
				}
				$route = "";
				if ($foundlb > 0) {
					$route = " route-to { {$routeto} } ";
					if ($foundlb > 1) {
						$route .= " round-robin ";
						if (isset($config['system']['lb_use_sticky'])) {
							$route .= " sticky-address ";
						}
					}
				}
			}
			if (($route === "") && isset($config['system']['skip_rules_gw_down'])) {
				unset($GatewayGroupsList[$gateway]);
			} else {
				$rules .= "GW{$gateway} = \" {$route} \"\n";
			}
		}
	}

	/* Create a global array to avoid errors on rulesets. */
	$GatewaysList = $GatewaysList + $GatewayGroupsList;

	$rules .= "\n";

	return $rules;
}

/* returns space separated list of vpn subnets */
function filter_get_vpns_list() {
	global $config;

	$vpns = "";
	$vpns_arr = array();

	/* ipsec */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		/* Include mobile IPsec client subnet in the VPN network list.
		   See https://redmine.pfsense.org/issues/7005 */
		if (is_array($config['ipsec']['client'])
		    && isset($config['ipsec']['client']['enable'])
		    && isset($config['ipsec']['client']['pool_address'])
		    && isset($config['ipsec']['client']['pool_netbits'])) {
			$client_subnet = "{$config['ipsec']['client']['pool_address']}/{$config['ipsec']['client']['pool_netbits']}";
			if (is_subnet($client_subnet)) {
				 $vpns_arr[] = $client_subnet;
			}
		}
		if (is_array($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase2'] as $ph2ent) {
				if ((!$ph2ent['mobile']) && ($ph2ent['mode'] != 'transport') &&
				    !isset($ph2ent['disabled'])) {
					if (!is_array($ph2ent['remoteid'])) {
						continue;
					}
					$ph2ent['remoteid']['mode'] = $ph2ent['mode'];
					$vpns_subnet = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
					if (!is_subnet($vpns_subnet) || $vpns_subnet == "0.0.0.0/0") {
						continue;
					}
					$vpns_arr[] = $vpns_subnet;
				}
			}
		}
	}

	/* openvpn */
	foreach (array('client', 'server') as $type) {
		if (is_array($config['openvpn']["openvpn-$type"])) {
			foreach ($config['openvpn']["openvpn-$type"] as $settings) {
				if (is_array($settings)) {
					if (!isset($settings['disable'])) {
						$remote_networks = explode(',', $settings['remote_network']);
						foreach ($remote_networks as $remote_network) {
							if (is_subnet($remote_network) && ($remote_network <> "0.0.0.0/0")) {
								$vpns_arr[] = $remote_network;
							}
						}
						if (is_subnet($settings['tunnel_network']) && $settings['tunnel_network'] <> "0.0.0.0/0") {
							$vpns_arr[] = $settings['tunnel_network'];
						}
					}
				}
			}
		}
	}
	/* pppoe */
	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				if (is_ipaddr($pppoe['remoteip'])) {
					$pppoesub = gen_subnet($pppoe['remoteip'], $pppoe['pppoe_subnet']);
					if (is_subnet($pppoesub)) {
						$vpns_arr[] = $pppoesub;
					}
				}
			}
		}
	}

	/* wireguard */
	if (is_wg_enabled()) {
		foreach ($config['wireguard']['tunnel'] as $wg) {
			if (empty($wg['enabled'])) {
				continue;
			}
			if (!is_array($wg['peers']['wgpeer']) ||
			    count($wg['peers']['wgpeer']) == 0) {
				continue;
			}
			$wgpeer = $wg['peers']['wgpeer'];
			foreach (explode(",", $wgpeer['allowedips']) as $ip) {
				$vpns_arr[] = $ip;
			}
		}
	}

	if (!empty($vpns_arr)) {
		$vpns = implode(" ", $vpns_arr);
	}

	return $vpns;
}

/* returns space separated list of directly connected networks
 * optionally returns an array instead, including friendly interface and gateway (if applicable)
 */
function filter_get_direct_networks_list($returnsubnetsonly = true) {
	global $config, $FilterIflist, $GatewaysList;
	/* build list of directly connected interfaces and networks */
	$networks = "";
	$networks_arr = array();
	if (empty($FilterIflist)) {
		filter_generate_optcfg_array();
	}
	foreach ($FilterIflist as $ifent => $ifcfg) {
		$subnet = "{$ifcfg['sa']}/{$ifcfg['sn']}";
		if (is_subnet($subnet)) {
			if ($returnsubnetsonly) {
				$networks_arr[] = $subnet;
			} else {
				$networks_arr[] = array(
					'subnet' => $subnet,
					'if' => $ifent,
					'ip' => $ifcfg['ip']);
			}
		}
	}
	$viplist = get_configured_vip_list();
	foreach ($viplist as $vid => $address) {
		$vip = get_configured_vip($vid);
		$subnet = "{$vip['subnet']}/{$vip['subnet_bits']}";
		if (is_subnet($subnet) && !(is_subnetv4($subnet) && $vip['subnet_bits'] == 32) && !(is_subnetv6($subnet) && $vip['subnet_bits'] == 128)) {
			if (is_subnetv4($subnet)) {
				$subnet = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
			} else if (is_subnetv6($subnet)) {
				$subnet = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
			}
			if ($returnsubnetsonly) {
				$networks_arr[] = $subnet;
			} else {
				$networks_arr[] = array(
					'subnet' => $subnet,
					'if' => $vip['interface'],
					'ip' => $vip['subnet']);
			}
		}
	}
	// Add any enabled static routes
	foreach (get_staticroutes(false, false, true) as $netent) {
		if (is_subnet($netent['network'])) {
			if ($returnsubnetsonly) {
				$networks_arr[] = $netent['network'];
			} else if (isset($GatewaysList[$netent['gateway']])) {
				$networks_arr[] = array(
					'subnet' => $netent['network'],
					'if' => $GatewaysList[$netent['gateway']]['friendlyiface'],
					'gateway' => $GatewaysList[$netent['gateway']]['gateway']);
			}
		}
	}
	if ($returnsubnetsonly) {
		if (!empty($networks_arr)) {
			$networks = implode(" ", $networks_arr);
		}
		return $networks;
	} else {
		return $networks_arr;
	}
}

function filter_generate_optcfg_array() {
	global $config, $FilterIflist;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_optcfg_array() being called $mt\n";
	}

	/* if list */
	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $if => $ifdetail) {
		$oc = $config['interfaces'][$if];
		$oic = array();
		$oic['if'] = get_real_interface($if);
		if (!does_interface_exist($oic['if'])) {
			continue;
		}
		$oic['ifv6'] = get_real_interface($if, "inet6");
		$oic['ip'] = get_interface_ip($if);
		$oic['ipv6'] = get_interface_ipv6($if);
		if (!is_ipaddrv4($oc['ipaddr']) && !empty($oc['ipaddr'])) {
			$oic['type'] = $oc['ipaddr'];
		}
		if (!is_ipaddrv6($oc['ipaddrv6']) && !empty($oc['ipaddrv6'])) {
			$oic['type6'] = $oc['ipaddrv6'];
		}
		if (!empty($oc['track6-interface'])) {
			$oic['track6-interface'] = $oc['track6-interface'];
		}
		$oic['sn'] = get_interface_subnet($if);
		$oic['snv6'] = get_interface_subnetv6($if);
		$oic['mtu'] = empty($oc['mtu']) ? 1500 : $oc['mtu'];
		$oic['mss'] = empty($oc['mss']) ? '' : $oc['mss'];
		$oic['descr'] = $ifdetail;
		$oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
		$oic['sav6'] = gen_subnetv6($oic['ipv6'], $oic['snv6']);
		$oic['nonat'] = $oc['nonat'];
		$oic['alias-address'] = $oc['alias-address'];
		$oic['alias-subnet'] = $oc['alias-subnet'];
		$oic['gateway'] = $oc['gateway'];
		$oic['gatewayv6'] = $oc['gatewayv6'];
		$oic['spoofcheck'] = "yes";
		$oic['bridge'] = link_interface_to_bridge($if);
		$vips = link_interface_to_vips($if);
		if (!empty($vips)) {
			foreach ($vips as $vipidx => $vip) {
				if (is_ipaddrv4($vip['subnet'])) {
					if (!is_array($oic['vips'])) {
						$oic['vips'] = array();
					}
					$oic['vips'][$vipidx]['mode'] = $vip['mode'];
					$oic['vips'][$vipidx]['ip'] = $vip['subnet'];
					if (empty($vip['subnet_bits'])) {
						$oic['vips'][$vipidx]['sn'] = 32;
					} else {
						$oic['vips'][$vipidx]['sn'] = $vip['subnet_bits'];
					}
				} else if (is_ipaddrv6($vip['subnet'])) {
					if (!is_array($oic['vips6'])) {
						$oic['vips6'] = array();
					}
					$oic['vips6'][$vipidx]['mode'] = $vip['mode'];
					$oic['vips6'][$vipidx]['ip'] = $vip['subnet'];
					if (empty($vip['subnet_bits'])) {
						$oic['vips6'][$vipidx]['sn'] = 128;
					} else {
						$oic['vips6'][$vipidx]['sn'] = $vip['subnet_bits'];
					}
				}
			}
		}
		unset($vips);
		$FilterIflist[$if] = $oic;
	}

	if ($config['l2tp']['mode'] == "server") {
		$oic = array();
		$oic['if'] = 'l2tp';
		$oic['descr'] = 'L2TP';
		$oic['ip'] = $config['l2tp']['localip'];
		$oic['sa'] = $config['l2tp']['remoteip'];
		if ($config['l2tp']['l2tp_subnet'] <> "") {
			$oic['sn'] = $config['l2tp']['l2tp_subnet'];
		} else {
			$oic['sn'] = "32";
		}
		$oic['mode'] = $config['l2tp']['mode'];
		$oic['virtual'] = true;
		$FilterIflist['l2tp'] = $oic;
	}
	if (is_array($config['pppoes']['pppoe']) && (count($config['pppoes']['pppoe']) > 0)) {
		$pppoeifs = array();
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				$oic = array();
				$oic['if'] = 'pppoe';
				$oic['descr'] = 'pppoe';
				$oic['ip'] = $pppoe['localip'];
				$oic['sa'] = $pppoe['remoteip'];
				$oic['mode'] = $pppoe['mode'];
				$oic['virtual'] = true;
				if ($pppoe['pppoe_subnet'] <> "") {
					$oic['sn'] = $pppoe['pppoe_subnet'];
				} else {
					$oic['sn'] = "32";
				}
				$pppoeifs[] = $oic;
			}
		}
		if (count($pppoeifs)) {
			$FilterIflist['pppoe'] = $pppoeifs;
		}
	}
	/* add ipsec interfaces */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		$oic = array();
		$oic['if'] = 'enc0';
		$oic['descr'] = 'IPsec';
		$oic['type'] = "none";
		$oic['virtual'] = true;
		$FilterIflist['enc0'] = $oic;
	}
	/* add openvpn interfaces */
	if ($config['openvpn']['openvpn-server'] || $config['openvpn']['openvpn-client']) {
		$oic = array();
		$oic['if'] = "openvpn";
		$oic['descr'] = 'OpenVPN';
		$oic['type'] = "none";
		$oic['virtual'] = true;
		$FilterIflist['openvpn'] = $oic;
	}
	/* add wireguard interfaces */
	if (is_wg_enabled()) {
		$oic = array();
		$oic['if'] = "wg";
		$oic['descr'] = 'WireGuard';
		$oic['type'] = "none";
		$oic['virtual'] = true;
		$FilterIflist['wireguard'] = $oic;
	}
	/* add interface groups */
	if (isset($config['ifgroups']['ifgroupentry']) && is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			$oc = array();
			$oc['if'] = $ifgen['ifname'];
			$oc['descr'] = $ifgen['ifname'];
			$oc['virtual'] = true;
			$FilterIflist[$ifgen['ifname']] = $oc;
		}
	}
}

function filter_flush_nat_table() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_flush_nat_table() being called $mt\n";
	}
	return mwexec("/sbin/pfctl -F nat");
}

function filter_flush_state_table() {
	return mwexec("/sbin/pfctl -F state");
}

function filter_get_reflection_interfaces($natif = "") {
	global $FilterIflist;

	$nat_if_list = array();

	foreach ($FilterIflist as $ifent => $ifname) {
		if ($ifname['if'] == $natif) {
			continue;
		}

		/* Do not add reflection redirects for interfaces with gateways */
		if (interface_has_gateway($ifent)) {
			continue;
		}

		$nat_if_list[] = $ifname['if'];
	}

	return $nat_if_list;
}

function filter_generate_reflection_nat($rule, &$route_table, $nat_ifs, $protocol, $target, $target_ip, $target_subnet = "") {
	global $config, $FilterIflist;

	if (!isset($config['system']['enablenatreflectionhelper'])) {
		return "";
	}

	// Initialize natrules holder string
	$natrules = "";

	update_filter_reload_status(sprintf(gettext("Creating reflection NAT rule for %s..."), $rule['descr']));

	/* TODO: Add this option to port forwards page. */
	if (isset($rule['staticnatport'])) {
		$static_port = " static-port";
	} else {
		$static_port = " port 1024:65535";
	}

	if (!empty($protocol)) {
		$protocol_text = " proto {$protocol}";
	} else {
		$protocol_text = "";
	}

	if (empty($target_subnet) || !is_numeric($target_subnet)) {
		$target_subnet = 32;
	}

	if (!is_array($route_table)) {
		/* get a simulated IPv4-only route table based on the config */
		$route_table = filter_get_direct_networks_list(false);
		foreach ($route_table as $rt_key => $rt_ent) {
			if (!is_subnetv4($rt_ent['subnet'])) {
				unset($route_table[$rt_key]);
			}
			if (isset($route_table[$rt_key]) && isset($FilterIflist[$rt_ent['if']]['if'])) {
				$route_table[$rt_key]['if'] = $FilterIflist[$rt_ent['if']]['if'];
			}
		}
	}

	/* Check if the target is accessed through a static route */
	foreach ($route_table as $route) {
		if (isset($route['gateway']) && is_ipaddr($route['gateway'])) {
			$subnet_split = explode("/", $route['subnet']);
			if (in_array($route['if'], $nat_ifs) && check_subnets_overlap($target_ip, $target_subnet, $subnet_split[0], $subnet_split[1])) {
				$target_ip = $route['gateway'];
				$target_subnet = 32;
				break;
			}
		}
	}

	/* Search for matching subnets in the routing table */
	foreach ($route_table as $route) {
		$subnet = $route['subnet'];
		$subnet_split = explode("/", $subnet);
		$subnet_if = $route['if'];
		/* Blacklist invalid "from" sources since they can be picked up accidentally and cause rule errors. */
		$no_reflect_from = array("l2tp");
		if (in_array($subnet_if, $nat_ifs) && check_subnets_overlap($target_ip, $target_subnet, $subnet_split[0], $subnet_split[1])) {
			$ifsubnet_ip = "";
			/* Find interface IP to use for NAT */
			foreach ($route_table as $ifnetwork) {
				if (isset($ifnetwork['ip']) && is_ipaddr($ifnetwork['ip']) && $ifnetwork['if'] == $subnet_if && ip_in_subnet($ifnetwork['ip'], $subnet)) {
					$ifsubnet_ip = $ifnetwork['ip'];
					break;
				}
			}
			if (!empty($ifsubnet_ip) && !in_array($subnet, $no_reflect_from)) {
				$subnets = array($subnet);
				/* Find static routes that also need to be referenced in the NAT rule */
				foreach ($route_table as $rtentry) {
					if (isset($rtentry['gateway']) && is_ipaddr($rtentry['gateway']) && $rtentry['if'] == $subnet_if && ip_in_subnet($rtentry['gateway'], $subnet)) {
						$subnets[] = $rtentry['subnet'];
					}
				}
				if (count($subnets) > 1) {
					$subnet = "{ " . implode(" ", $subnets) . " }";
				}
				/* Do not generate a rule with an interface source if that interface has no IP address.
				 * See https://redmine.pfsense.org/issues/8604 */
				if (!empty(get_interface_ip($subnet_if))) {
					$natrules .= "no nat on {$subnet_if}{$protocol_text} from ({$subnet_if}) to {$target}\n";
				}
				$natrules .= "nat on {$subnet_if}{$protocol_text} from {$subnet} to {$target} -> {$ifsubnet_ip}{$static_port}\n";
			}
		}
	}

	if (!empty($natrules)) {
		$natrules .= "\n";
	}

	return $natrules;
}

function filter_generate_reflection_proxy($rule, $nordr, $rdr_ifs, $srcaddr, $dstaddr_port, &$starting_localhost_port, &$reflection_rules) {
	global $FilterIflist, $config;

	// Initialize natrules holder string
	$natrules = "";
	$reflection_rules = array();

	if (!empty($rdr_ifs)) {
		if ($config['system']['reflectiontimeout']) {
			$reflectiontimeout = $config['system']['reflectiontimeout'];
		} else {
			$reflectiontimeout = "2000";
		}

		update_filter_reload_status(sprintf(gettext("Creating reflection rule for %s..."), $rule['descr']));

		$rdr_if_list = implode(" ", $rdr_ifs);
		if (count($rdr_ifs) > 1) {
			$rdr_if_list = "{ {$rdr_if_list} }";
		}

		$natrules .= "\n# Reflection redirects\n";

		$localport = $rule['local-port'];
		if (!empty($localport) && is_alias($localport)) {
			$localport = filter_expand_alias($localport);
			$localport = explode(" ", trim($localport));
			// The translation port for rdr, when specified, does not support more than one port or range.
			// Emulating for behavior consistent with the original port forward.
			$localport = $localport[0];
		}

		if (is_alias($rule['destination']['port'])) {
			if (empty($localport) || $rule['destination']['port'] == $rule['local-port']) {
				$dstport = filter_expand_alias($rule['destination']['port']);
				$dstport = array_filter(explode(" ", trim($dstport)));
				$localport = "";
			} else if (!empty($localport)) {
				$dstport = array($localport);
			}
		} else {
			$dstport = array(str_replace("-", ":", $rule['destination']['port']));
			$dstport_split = explode(":", $dstport[0]);

			if (!empty($localport) && $dstport_split[0] != $rule['local-port']) {
				if (!is_alias($rule['local-port']) && $dstport_split[1] && $dstport_split[0] != $dstport_split[1]) {
					$localendport = $localport + ($dstport_split[1] - $dstport_split[0]);
					$localport .= ":$localendport";
				}

				$dstport = array($localport);
			} else {
				$localport = "";
			}
		}

		$dstaddr = explode(" ", $dstaddr_port);
		if ($dstaddr[2]) {
			$rflctintrange = array_pop($dstaddr);
			array_pop($dstaddr);
		} else {
			return "";
		}
		$dstaddr = implode(" ", $dstaddr);
		if (empty($dstaddr) || trim($dstaddr) == "0.0.0.0" || strtolower(trim($dstaddr)) == "port") {
			return "";
		}

		if (isset($rule['destination']['any'])) {
			if (!$rule['interface']) {
				$natif = "wan";
			} else {
				$natif = $rule['interface'];
			}

			if (!isset($FilterIflist[$natif])) {
				return "";
			}
			if (is_ipaddr($FilterIflist[$natif]['ip'])) {
				$dstaddr = $FilterIflist[$natif]['ip'];
			} else {
				return "";
			}

			if (!empty($FilterIflist[$natif]['sn'])) {
				$dstaddr = gen_subnet($dstaddr, $FilterIflist[$natif]['sn']) . '/' . $FilterIflist[$natif]['sn'];
			}
		}

		switch ($rule['protocol']) {
			case "tcp/udp":
				$protocol = "{ tcp udp }";
				$reflect_protos = array('tcp', 'udp');
				break;
			case "tcp":
			case "udp":
				$protocol = $rule['protocol'];
				$reflect_protos = array($rule['protocol']);
				break;
			default:
				return "";
				break;
		}

		if (!empty($nordr)) {
			$natrules .= "no rdr on {$rdr_if_list} proto {$protocol} from {$srcaddr} to {$dstaddr} port {$rflctintrange}\n";
			return $natrules;
		}

		if (is_alias($rule['target'])) {
			$target = filter_expand_alias($rule['target']);
		} else if (is_ipaddr($rule['target'])) {
			$target = $rule['target'];
		} else if (is_ipaddr($FilterIflist[$rule['target']]['ip'])) {
			$target = $FilterIflist[$rule['target']]['ip'];
		} else {
			return "";
		}
		$starting_localhost_port_tmp = $starting_localhost_port;
		$toomanyports = false;
		/* only install reflection rules for < 19991 items */
		foreach ($dstport as $loc_pt) {
			if ($starting_localhost_port < 19991) {
				$toadd_array = array();
				$inetdport = $starting_localhost_port;
				$rflctrange = $starting_localhost_port;

				$loc_pt = explode(":", $loc_pt);
				if ($loc_pt[1] && $loc_pt[1] > $loc_pt[0]) {
					$delta = $loc_pt[1] - $loc_pt[0];
				} else {
					$delta = 0;
				}

				if (($inetdport + $delta + 1) - $starting_localhost_port_tmp > 500) {
					log_error(gettext("Not installing NAT reflection rules for a port range > 500"));
					$inetdport = $starting_localhost_port;
					$toadd_array = array();
					$toomanyports = true;
					break;
				} else if (($inetdport + $delta) > 19990) {
					log_error(gettext("Installing partial NAT reflection rules. Maximum 1,000 reached."));
					$delta = 19990 - $inetdport;
					$loc_pt[1] = $loc_pt[0] + $delta;
					if ($delta == 0) {
						unset($loc_pt[1]);
					}
					$toomanyports = true;

					if (!empty($localport)) {
						if (is_alias($rule['destination']['port'])) {
							$rflctintrange = alias_expand($rule['destination']['port']);
						} else {
							if ($dstport_split[1]) {
								$dstport_split[1] = $dstport_split[0] + $inetdport + $delta - $starting_localhost_port;
							}
							$rflctintrange = implode(":", $dstport_split);
						}
					}
				}

				if (empty($localport)) {
					$rflctintrange = implode(":", $loc_pt);
				}
				if ($inetdport + $delta > $starting_localhost_port) {
					$rflctrange .= ":" . ($inetdport + $delta);
				}
				$starting_localhost_port = $inetdport + $delta + 1;
				$toadd_array = array_merge($toadd_array, range($loc_pt[0], $loc_pt[0] + $delta));

				if (!empty($toadd_array)) {
					$rtarget = explode(" ", trim($target));
					foreach ($toadd_array as $tda) {
						if (empty($tda)) {
							continue;
						}
						foreach ($reflect_protos as $reflect_proto) {
							if ($reflect_proto == "udp") {
								$socktype = "dgram";
								$dash_u = "-u ";
								$wait = "yes";
							} else {
								$socktype = "stream";
								$dash_u = "";
								$wait = "no";
							}
							foreach ($rtarget as $targip) {
								if (empty($targip)) {
									continue;
								}
								$reflection_rule = array(
									'port' => $inetdport,
									'socket_type' => $socktype,
									'protocol' => $reflect_proto,
									'wait' => $wait,
									'user' => 'nobody',
									'server' => '/usr/bin/nc',
									'server_args' => "{$dash_u}-w {$reflectiontimeout} {$targip} {$tda}"
								);
								$reflection_rules[] = $reflection_rule;
								unset($reflection_rule);
							}
						}
						$inetdport++;
					}
					$natrules .= "rdr on {$rdr_if_list} proto {$protocol} from {$srcaddr} to {$dstaddr} port {$rflctintrange} tag PFREFLECT -> 127.0.0.1 port {$rflctrange}\n";
				}
			}

			if ($toomanyports) {
				break;
			}
		}
	}

	return $natrules;
}

function filter_nat_rules_automatic_tonathosts($with_descr = false) {
	global $config, $FilterIflist, $GatewaysList;

	$tonathosts = array("127.0.0.0/8", "::1/128");
	$descriptions = array(gettext("localhost"), gettext("localhost"));

	// Add any enabled static routes
	foreach (get_staticroutes(false, false, true) as $route) {
		$netip = explode("/", $route['network']);
		if (isset($GatewaysList[$route['gateway']])) {
			$gateway = &$GatewaysList[$route['gateway']];
			if (!interface_has_gateway($gateway['interface']) && is_private_ip($netip[0])) {
				$tonathosts[] = $route['network'];
				$descriptions[] = gettext("static route");
			}
		}
	}

	/* create outbound nat entries for all local networks */
	foreach ($FilterIflist as $ocname => $oc) {
		if (interface_has_gateway($ocname)) {
			continue;
		}
		if (is_ipaddr($oc['alias-address'])) {
			$tonathosts[] = "{$oc['alias-address']}/{$oc['alias-subnet']}";
			$descriptions[] = $oc['descr'] . " " . gettext("DHCP alias address");
		}
		if ($oc['sa']) {
			$tonathosts[] = "{$oc['sa']}/{$oc['sn']}";
			$descriptions[] = $oc['descr'];
			if (isset($oc['vips']) && is_array($oc['vips'])) {
				$if_subnets = array("{$oc['sa']}/{$oc['sn']}");
				foreach ($oc['vips'] as $vip) {
					if (!is_ipaddrv4($vip['ip'])) {
						continue;
					}

					foreach ($if_subnets as $subnet) {
						if (ip_in_subnet($vip['ip'], $subnet)) {
							continue 2;
						}
					}

					$network = gen_subnet($vip['ip'], $vip['sn']);
					array_unshift($tonathosts, $network . '/' . $vip['sn']);
					array_unshift($descriptions, "Virtual IP ({$oc['descr']})");
					$if_subnets[] = $network . '/' . $vip['sn'];
					unset($network);
				}
				unset($if_subnets);
			}
		}
	}

	/* PPPoE subnet */
	if (is_array($FilterIflist['pppoe'])) {
		foreach ($FilterIflist['pppoe'] as $pppoe) {
			if (is_private_ip($pppoe['ip'])) {
				$tonathosts[] = "{$pppoe['sa']}/{$pppoe['sn']}";
				$descriptions[] = gettext("PPPoE server");
			}
		}
	}

	/* add openvpn interfaces */
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $ovpnsrv) {
			if (!isset($ovpnsrv['disable']) && !empty($ovpnsrv['tunnel_network'])) {
				$tonathosts[] = $ovpnsrv['tunnel_network'];
				$descriptions[] = gettext("OpenVPN server");
			}
		}
	}

	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $ovpncli) {
			if (!isset($ovpncli['disable']) && !empty($ovpncli['tunnel_network'])) {
				$tonathosts[] = $ovpncli['tunnel_network'];
				$descriptions[] = gettext("OpenVPN client");
			}
		}
	}

	foreach (wg_get_tunnel_networks('ipv4') as $wgn) {
		$tonathosts[] = $wgn;
		$descriptions[] = gettext('WireGuard');
	}

	/* IPsec mode_cfg subnet */
	if ((isset($config['ipsec']['client']['enable'])) &&
	    (!empty($config['ipsec']['client']['pool_address'])) &&
	    (!empty($config['ipsec']['client']['pool_netbits']))) {
		$tonathosts[] = "{$config['ipsec']['client']['pool_address']}/{$config['ipsec']['client']['pool_netbits']}";
		$descriptions[] = gettext("IPsec client");
	}
	if (isset($config['ipsec']['client']['enable']) &&
	    isset($config['ipsec']['mobilekey'])) {
		foreach ($config['ipsec']['mobilekey'] as $key) {
			if (in_array("{$key['pool_address']}/{$key['pool_netbits']}", $tonathosts)) {
				continue;
			}
			if (!empty($key['pool_address']) &&
			    !empty($key['pool_netbits'])) {
				$tonathosts[] = "{$key['pool_address']}/{$key['pool_netbits']}";
				$descriptions[] = gettext("IPsec client");
			}
		}
	}
	if (is_array($config['ipsec']) && is_array($config['ipsec']['phase1']) && is_array($config['ipsec']['phase2'])) {
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			$vti_addrs = ipsec_vti($ph1ent, true);
			// Skip non-VTI tunnels
			if (!$vti_addrs || !is_array($vti_addrs)) {
				continue;
			}
			// If any of the VTI remotes is v4, then we can make a v4 gw
			foreach ($vti_addrs as $vtia) {
				if (is_ipaddrv4($vtia['right'])) {
					$tonathosts[] = $vtia['right'];
					$descriptions[] = gettext("IPsec VTI: {$ph1ent['descr']}");
				}
			}
		}
	}

	if ($with_descr) {
		$combined = array();
		foreach ($tonathosts as $idx => $subnet) {
			$combined[] = array(
				"subnet" => $subnet,
				"descr" => $descriptions[$idx]);
		}

		return $combined;
	} else {
		return $tonathosts;
	}
}

function filter_nat_rules_outbound_automatic($src) {
	global $config, $FilterIflist;

	$rules = array();
	foreach ($FilterIflist as $if => $ifcfg) {
		if ((substr($ifcfg['if'], 0, 4) == "ovpn") ||
		    (substr($ifcfg['if'], 0, 5) == "ipsec") ||
		    (substr($ifcfg['if'], 0, 2) == "wg")) {
			continue;
		}
		if (!interface_has_gateway($if)) {
			continue;
		}

		$natent = array();
		$natent['interface'] = $if;
		$natent['source']['network'] = $src;
		$natent['dstport'] = "500";
		$natent['target'] = "";
		$natent['destination']['any'] = true;
		$natent['staticnatport'] = true;
		$natent['descr'] = gettext('Auto created rule for ISAKMP');
		$rules[] = $natent;

		$natent = array();
		$natent['interface'] = $if;
		$natent['source']['network'] = $src;
		$natent['sourceport'] = "";
		$natent['target'] = "";
		$natent['destination']['any'] = true;
		$natent['natport'] = "";
		$natent['descr'] = gettext('Auto created rule');
		if (isset($ifcfg['nonat'])) {
			$natent['nonat'] = true;
		}
		$rules[] = $natent;
	}

	return $rules;
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if ($if, $descr = "", $ipprotocol = "", $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false, $proto = "", $poolopts = "") {
	global $config, $FilterIflist;

	/* XXX: billm - any idea if this code is needed? */
	if ($src == "/32" || $src == "/128" || $src[0] == "/") {
		return "# src incorrectly specified\n";
	}
	$if_friendly = $FilterIflist[$if]['descr'];
	if (!$if_friendly) {
		return "# Could not convert {$if} to friendly name(alias) {$descr}\n";
	}

	$inet = "";
	if (is_v4($src) || is_v4($dst) || is_v4($natip)) {
		$inet = "inet";
	}
	if (is_v6($src) || is_v6($dst) || is_v6($natip)) {
		if ($inet == "inet") {
			return "# conflicting ipv4/ipv6 settings in nat rule: " . $descr;
		}
		$inet = "inet6";
	}
	if (($ipprotocol == 'inet' && $inet == 'inet6') ||
	    ($ipprotocol == 'inet6' && $inet == 'inet')) {
		return "# ipv4/ipv6 settings dont match selected ipprotocol in nat rule: " . $descr;
	}
	if (!empty($ipprotocol)) {
		$inet = $ipprotocol; //if ipprotocol is set specifically to ipv4 or ipv6 then that has to be used
	}

	/* Set tgt4 for IPv4 */
	if ($inet == "" || $inet == 'inet') {
		if ($natip != "") {
			if (is_subnetv4($natip)) {
				$tgt4 = $natip;
			} elseif (is_alias($natip)) {
				$tgt4 = "\${$natip}";
			} else {
				$tgt4 = "{$natip}/32";
			}
		} else {
			$ifip = get_interface_ip($if);
			if (is_ipaddrv4($ifip)) {
				$tgt4 = "{$ifip}/32";
			} else {
				$tgt4 = "(" . $FilterIflist[$if]['if'] . ")";
			}
		}
	}
	/* Set tgt6 for IPv6 */
	if ($inet == "" || $inet == 'inet6') {
		if ($natip != "") {
			if (is_subnetv6($natip)) {
				$tgt6 = $natip;
			} elseif (is_alias($natip)) {
				$tgt6 = "\${$natip}";
			} else {
				$tgt6 = "{$natip}/128";
			}
		} else {
			$ifip = get_interface_ipv6($if);
			if (is_ipaddrv6($ifip)) {
				$tgt6 = "{$ifip}/128";
			} else {
				$tgt6 = "(" . $FilterIflist[$if]['if'] . ")";
			}
		}
	}
	/* Add the protocol, if defined */
	if (!empty($proto) && $proto != "any") {
		if ($proto == "tcp/udp") {
			$protocol = " proto { tcp udp }";
		} else {
			$protocol = " proto {$proto}";
		}
	} else {
		$protocol = "";
	}


	$tgtport = "";
	/* Add the hard set source port (useful for ISAKMP) */
	if ($natport != "") {
		$tgtport .= " port {$natport}";
	}
	/* sometimes this gets called with "" instead of a value */
	if ($src == "") {
		$src = "any";
	}
	/* Match on this source port */
	if ($srcport != "") {
		$srcportexpand = alias_expand($srcport);
		if (!$srcportexpand) {
			$srcportexpand = $srcport;
		}
		$src .= " port {$srcportexpand}";
	}
	/* sometimes this gets called with "" instead of a value */
	if ($dst == "") {
		$dst = "any";
	}
	/* Match on this dest port */
	if ($dstport != "") {
		$dstportexpand = alias_expand($dstport);
		if (!$dstportexpand) {
			$dstportexpand = $dstport;
		}
		$dst .= " port {$dstportexpand}";
	}
	/* outgoing static-port option, hamachi, Grandstream, VOIP, etc */
	$staticnatport_txt = "";
	if ($staticnatport) {
		$staticnatport_txt = " static-port";
	} elseif (!$natport) {
		$tgtport = " port 1024:65535"; // set source port range
	}
	if (!empty($descr)) {
		$descr = " # " . $descr;
	}

	if (empty($if_friendly) || empty($src) || empty($dst)) {
		return "# validation of rule properties failed to verify the interface/source/destination" . " in nat rule: {$descr}\n";
	}
	/* Put all the pieces together */
	$natrules = "";
	if ($nonat) {
		/* Allow for negating NAT entries */
		$natrules .= "no nat on \${$if_friendly} {$inet}{$protocol} from {$src} to {$dst}{$descr}\n";
	} else {
		if ($inet == "" || $inet == 'inet') {
			if (empty($tgt4)) {
				$natrules .= "# validation of rule properties failed to verify the IPv4 target" . " in nat rule: {$descr}\n";
			} else {
				$natrules .= "nat on \${$if_friendly} inet{$protocol} from {$src} to {$dst} -> {$tgt4}{$tgtport} {$poolopts}{$staticnatport_txt}{$descr}\n";
			}
		}
		if ($inet == "" || $inet == 'inet6') {
			if (empty($tgt6)) {
				$natrules .= "# validation of rule properties failed to verify the IPv6 target" . " in nat rule: {$descr}\n";
			} else {
				$natrules .= "nat on \${$if_friendly} inet6{$protocol} from {$src} to {$dst} -> {$tgt6}{$tgtport} {$poolopts}{$staticnatport_txt}{$descr}\n";
			}
		}
	}
	return $natrules;
}

function xinetd_service_entry($entry_array) {
	$entry = <<<EOD
service {$entry_array['port']}-{$entry_array['protocol']}
{
	type = unlisted
	bind = 127.0.0.1
	port = {$entry_array['port']}
	socket_type = {$entry_array['socket_type']}
	protocol = {$entry_array['protocol']}
	wait = {$entry_array['wait']}
	user = {$entry_array['user']}
	server = {$entry_array['server']}
	server_args = {$entry_array['server_args']}
}


EOD;
	return $entry;
}

function filter_nat_rules_generate() {
	global $config, $g, $after_filter_configure_run, $FilterIflist, $GatewaysList, $aliases;

	$natrules = "no nat proto carp\n";
	$natrules .= "no rdr proto carp\n";
	$natrules .= "nat-anchor \"natearly/*\"\n";

	$natrules .= "nat-anchor \"natrules/*\"\n\n";
	update_filter_reload_status(gettext("Creating 1:1 rules..."));

	$reflection_txt = "";
	$route_table = "";

	/* any 1:1 mappings? */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $rule) {
			if (isset($rule['disabled'])) {
				continue;
			}

			$sn = "";
			$sn1 = "";

			if (($rule['ipprotocol'] == 'inet6') || is_ipaddrv6($rule['external'])) {
				$ipproto = 'inet6';
			} else {
				$ipproto = 'inet';
			}

			if (is_ipaddr($rule['external'])) {
				$target = $rule['external'];
			} else {
				$tmprule = array();
				$tmprule['external']['network'] = $rule['external'];
				$tmprule['ipprotocol'] = $ipproto;
				$target = filter_generate_address($tmprule, 'external');
			}
			if (!$target) {
				$natrules .= "# Unresolvable alias {$rule['target']}\n";
				continue;               /* unresolvable alias */
			}

			if (!$rule['interface']) {
				$natif = "wan";
			} else {
				$natif = $rule['interface'];
			}
			if (!isset($FilterIflist[$natif])) {
				continue;
			}

			$srcaddr = filter_generate_address($rule, 'source');
			$dstaddr = filter_generate_address($rule, 'destination');
			$srcaddr = trim($srcaddr);
			$dstaddr = trim($dstaddr);

			if (empty($srcaddr) || empty($dstaddr)) {
				continue;
			}

			if (!is_ipaddr($srcaddr) && !is_subnet($srcaddr)) {
				$srcaddr = explode(' ', $srcaddr)[1];
			}
			$tmp = explode('/', $srcaddr);
			$srcip = $tmp[0];
			if (!empty($tmp[1]) && is_numeric($tmp[1])) {
				$sn = $tmp[1];
				$sn1 = "/{$sn}";
			}

			$natifname = $FilterIflist[$natif]['if'];
			if (is_ipaddrv6($tmp[0]) && (($FilterIflist[$natif]['type6'] == '6rd') ||
			    ($FilterIflist[$natif]['type6'] == '6to4'))) {
				$natif = strtolower($FilterIflist[$natif]['descr']) . "_stf";
			} else {
				$natif = $natifname;
			}

			$nat_if_list = array();
			if (isset($rule['nobinat'])) {
				$natrules .= "no binat on {$natif} {$ipproto} from {$srcaddr} to {$dstaddr}\n";
			} else {
				/*
				 * If reflection is enabled, turn on extra redirections
				 * for this rule by adding other interfaces to an rdr rule.
				 */
				if ((isset($config['system']['enablebinatreflection']) || $rule['natreflection'] == "enable") &&
				    ($rule['natreflection'] != "disable")) {
					$nat_if_list = filter_get_reflection_interfaces($natifname);
				}

				$natrules .= "binat on {$natif} {$ipproto} from {$srcaddr} to {$dstaddr} -> {$target}{$sn1}\n";

				if (!empty($nat_if_list)) {
					$binat_if_list = implode(" ", $nat_if_list);
					$binat_if_list = "{ {$binat_if_list} }";
					$reflection_txt .= "rdr on {$binat_if_list} {$ipproto} from {$dstaddr} to {$target}{$sn1} -> {$srcaddr} bitmask\n";
				}
			}

			$nat_if_list = array_merge(array($natif), $nat_if_list);
			$reflection_txt .= filter_generate_reflection_nat($rule, $route_table, $nat_if_list, "", $srcaddr, $srcip, $sn);
		}
	}

	/* Add binat rules for Network Prefix translation */
	if (is_array($config['nat']['npt'])) {
		foreach ($config['nat']['npt'] as $rule) {
			if (isset($rule['disabled'])) {
				continue;
			}

			if (!$rule['interface']) {
				$natif = "wan";
			} else {
				$natif = $rule['interface'];
			}
			if (!isset($FilterIflist[$natif])) {
				continue;
			}

			$srcaddr = filter_generate_address($rule, 'source');
			$dstaddr = filter_generate_address($rule, 'destination');

			$srcaddr = trim($srcaddr);
			$dstaddr = trim($dstaddr);

			if (($FilterIflist[$natif]['type6'] == '6rd') || ($FilterIflist[$natif]['type6'] == '6to4')) {
				$natif = $FilterIflist[$natif]['descr'] . "_STF";
			} else {
				$natif = $FilterIflist[$natif]['descr'];
			}

			/* Do not form an invalid NPt rule.
			 * See https://redmine.pfsense.org/issues/8575 */
			if (!(is_subnetv6($srcaddr) || is_ipaddrv6($srcaddr)) ||
			    !(is_subnetv6($dstaddr) || is_ipaddrv6($dstaddr))) {
				continue;
			}

			$natrules .= "binat on \${$natif} inet6 from {$srcaddr} to any -> {$dstaddr}\n";
			$natrules .= "binat on \${$natif} inet6 from any to {$dstaddr} -> {$srcaddr}\n";

		}
	}

	/* ipsec nat */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		if (is_array($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase2'] as $ph2ent) {
				if ($ph2ent['mode'] != 'transport' && !empty($ph2ent['natlocalid']) && !isset($ph2ent['disabled'])) {
					ipsec_lookup_phase1($ph2ent, $ph1ent);
					if (!is_array($ph1ent)) {
						continue;
					}
					if (isset($ph1ent['disabled'])) {
						continue;
					}
					if (!is_array($ph2ent['localid'])) {
						$ph2ent['localid'] = array();
					}
					$ph2ent['localid']['mode'] = $ph2ent['mode'];
					$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid']);
					if (empty($local_subnet) || $local_subnet == "0.0.0.0/0") {
						continue;
					}
					if (!is_subnet($local_subnet) && !is_ipaddr($local_subnet)) {
						continue;
					}
					if (!is_array($ph2ent['natlocalid'])) {
						$ph2ent['natlocalid'] = array();
					}
					$ph2ent['natlocalid']['mode'] = $ph2ent['mode'];
					$natlocal_subnet = ipsec_idinfo_to_cidr($ph2ent['natlocalid']);
					if (empty($natlocal_subnet) || $natlocal_subnet == "0.0.0.0/0") {
						continue;
					}
					if (!is_subnet($natlocal_subnet) && !is_ipaddr($natlocal_subnet)) {
						continue;
					}
					if (!is_array($ph2ent['remoteid'])) {
						$ph2ent['remoteid'] = array();
					}
					$ph2ent['remoteid']['mode'] = $ph2ent['mode'];
					$remote_subnet = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
					if (empty($remote_subnet)) {
						continue;
					}
					if (!is_subnet($remote_subnet) && !is_ipaddr($remote_subnet)) {
						continue;
					}
					if ($remote_subnet == "0.0.0.0/0") {
						$remote_subnet = "any";
					}
					if (is_ipaddr($natlocal_subnet) && !is_ipaddr($local_subnet)) {
						$nattype = "nat";
					} else {
						list($natnet, $natmask) = explode('/', $natlocal_subnet);
						list($locnet, $locmask) = explode('/', $local_subnet);
						if (intval($natmask) != intval($locmask)) {
							$nattype = "nat";
						} else {
							$nattype = "binat";
						}
						unset($natnet, $natmask, $locnet, $locmask);
					}
					$natrules .= "{$nattype} on enc0 from {$local_subnet} to {$remote_subnet} -> {$natlocal_subnet}\n";
				}
			}
		}
	}

	if ($config['nat']['outbound']['mode'] == "disabled") {
		$natrules .= "\n# Outbound NAT rules are disabled\n";
	}

	if ($config['nat']['outbound']['mode'] == "advanced" || $config['nat']['outbound']['mode'] == "hybrid") {
		$natrules .= "\n# Outbound NAT rules (manual)\n";
		/* advanced outbound rules */
		if (is_array($config['nat']['outbound']['rule'])) {
			foreach ($config['nat']['outbound']['rule'] as $obent) {
				if (isset($obent['disabled'])) {
					continue;
				}
				update_filter_reload_status(sprintf(gettext("Creating advanced outbound rule %s"), $obent['descr']));
				$src = alias_expand($obent['source']['network']);
				if (!$src) {
					$src = $obent['source']['network'];
				}
				$dst = alias_expand($obent['destination']['address']);
				if (!$dst) {
					$dst = $obent['destination']['address'];
				}
				if (isset($obent['destination']['not']) && !isset($obent['destination']['any'])) {
					$dst = "!" . $dst;
				}

				if (!$obent['interface'] || !isset($FilterIflist[$obent['interface']])) {
					continue;
				}

				$obtarget = ($obent['target'] == "other-subnet") ? $obent['targetip'] . '/' . $obent['targetip_subnet']: $obent['target'];
				$poolopts = (is_subnet($obtarget) || is_alias($obtarget)) ? $obent['poolopts'] : "";

				/* pool option source-hash allows specification of an optional source-hash key */
				if ($poolopts == "source-hash" && !empty($obent['source_hash_key'])) {
					$poolopts = "source-hash ".$obent['source_hash_key'];
				}

				$natrules .= filter_nat_rules_generate_if($obent['interface'],
					$obent['descr'],
					$obent['ipprotocol'],
					$src,
					$obent['sourceport'],
					$dst,
					$obent['dstport'],
					$obtarget,
					$obent['natport'],
					isset($obent['nonat']),
					isset($obent['staticnatport']),
					$obent['protocol'],
					$poolopts
				);
			}
		}
	}

	/* outbound rules */
	if ((!isset($config['nat']['outbound']['mode'])) ||
	    ($config['nat']['outbound']['mode'] == "automatic") ||
	    ($config['nat']['outbound']['mode'] == "hybrid")) {
		$natrules .= "\n# Outbound NAT rules (automatic)\n";
		/* standard outbound rules (one for each interface) */
		update_filter_reload_status(gettext("Creating outbound NAT rules"));
		$tonathosts_array = filter_nat_rules_automatic_tonathosts();
		$tonathosts = implode(" ", $tonathosts_array);
		$numberofnathosts = count($tonathosts_array);

		$natrules .= "\n# Subnets to NAT \n";
		if ($numberofnathosts > 0) {
			update_filter_reload_status(gettext('Creating automatic outbound rules'));

			if ($numberofnathosts > 4) {
				$natrules .= "table <tonatsubnets> { {$tonathosts} }\n";
				$macroortable = "<tonatsubnets>";
			} else {
				$natrules .= "tonatsubnets	= \"{ {$tonathosts} }\"\n";
				$macroortable = "\$tonatsubnets";
			}

			$a_outs = filter_nat_rules_outbound_automatic($macroortable);
			foreach ($a_outs as $a_out) {
				$natrules .= filter_nat_rules_generate_if($a_out['interface'],
					"",
					$a_out['ipprotocol'],
					$a_out['source']['network'],
					$a_out['sourceport'],
					$a_out['destination']['address'],
					$a_out['dstport'],
					$a_out['target'],
					$a_out['natport'],
					isset($a_out['nonat']),
					isset($a_out['staticnatport']));
			}
		}
		unset($tonathosts, $tonathosts_array, $numberofnathosts);
	}

	update_filter_reload_status(gettext("Setting up TFTP helper"));
	$natrules .= "# TFTP proxy\n";
	$natrules .= "rdr-anchor \"tftp-proxy/*\"\n";

	if (!empty($config['system']['tftpinterface'])) {
		$tftpifs = explode(",", $config['system']['tftpinterface']);
		foreach ($tftpifs as $tftpif) {
			if ($FilterIflist[$tftpif]) {
				$natrules .= "rdr pass on {$FilterIflist[$tftpif]['if']} proto udp from any to any port tftp -> 127.0.0.1 port 6969\n";
			}
		}
	}

	/* DIAG: add ipv6 NAT, if requested */
	if ((isset($config['diag']['ipv6nat']['enable'])) &&
	    (is_ipaddr($config['diag']['ipv6nat']['ipaddr'])) &&
	    (is_array($FilterIflist['wan']))) {
		/* XXX: FIX ME!	 IPV6 */
		$natrules .= "rdr on \${$FilterIflist['wan']['descr']} proto ipv6 from any to any -> {$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	unlink_if_exists("{$g['varetc_path']}/xinetd.conf");
	// Open xinetd.conf write handle
	$xinetd_fd = fopen("{$g['varetc_path']}/xinetd.conf", "w");

	if (!empty($config['system']['tftpinterface'])) {
		/* add tftp helper */
		$ftp_proxy_entry = array(
			'port' => 6969,
			'socket_type' => 'dgram',
			'protocol' => 'udp',
			'wait' => 'yes',
			'user' => 'root',
			'server' => '/usr/libexec/tftp-proxy',
			'server_args' => '-v'
		);
		fwrite($xinetd_fd, xinetd_service_entry($ftp_proxy_entry));
	}

	if (isset($config['nat']['rule'])) {
		/* start reflection redirects on port 19000 of localhost */
		$starting_localhost_port = 19000;
		$natrules .= "# NAT Inbound Redirects\n";
		foreach ($config['nat']['rule'] as $rule) {
			update_filter_reload_status(sprintf(gettext("Creating NAT rule %s"), $rule['descr']));

			if (isset($rule['disabled'])) {
				continue;
			}

			/* if item is an alias, expand */
			$dstport = array();
			$dstport[0] = alias_expand($rule['destination']['port']);
			if (!$dstport[0]) {
				$dstport = explode("-", $rule['destination']['port']);
			}

			/* if item is an alias, expand */
			$localport = alias_expand($rule['local-port']);
			if (!$localport || $dstport[0] == $localport) {
				$localport = "";
			} else if (is_alias($rule['local-port'])) {
				$localport = filter_expand_alias($rule['local-port']);
				if ($localport) {
					$localport = explode(" ", trim($localport));
					$localport = $localport[0];
					$localport = " port {$localport}";
				}
			} else if (is_alias($rule['destination']['port'])) {
				$localport = " port {$localport}";
			} else {
				if (($dstport[1]) && ($dstport[0] != $dstport[1])) {
					$localendport = $localport + ($dstport[1] - $dstport[0]);

					$localport .= ":$localendport";
				}

				$localport = " port {$localport}";
			}

			if ($rule['ipprotocol'] == 'inet6') {
				$ipproto = 'inet6';
			} else {
				$ipproto = 'inet';
			}

			switch (strtolower($rule['protocol'])) {
				case "tcp/udp":
					$protocol = "{ tcp udp }";
					break;
				case "tcp":
				case "udp":
					$protocol = strtolower($rule['protocol']);
					break;
				default:
					$protocol = strtolower($rule['protocol']);
					$localport = "";
					break;
			}

			if (is_ipaddr($rule['target'])) {
				$target = $rule['target'];
			} elseif (is_alias($rule['target'])) {
				$target = alias_expand($rule['target']);
			} else {
				$tmprule = array();
				$tmprule['localip']['network'] = $rule['target'];
				$tmprule['ipprotocol'] = $ipproto;
				$target = filter_generate_address($tmprule, 'localip');
			}
			if (!$target && !isset($rule['nordr'])) {
				$natrules .= "# Unresolvable alias {$rule['target']}\n";
				continue;		/* unresolvable alias */
			}

			if (is_alias($rule['target'])) {
				$target_ip = filter_expand_alias($rule['target']);
			} else if (is_ipaddr($rule['target'])) {
				$target_ip = $rule['target'];
			} else if (is_ipaddr($FilterIflist[$rule['target']]['ip'])) {
				$target_ip = $FilterIflist[$rule['target']]['ip'];
			} else {
				$target_ip = $rule['target'];
			}
			$target_ip = trim($target_ip);

			if ($rule['associated-rule-id'] == "pass") {
				$rdrpass = "pass ";
			} else {
				$rdrpass = "";
			}

			if (isset($rule['nordr'])) {
				$nordr = "no ";
				$rdrpass = "";
			} else {
				$nordr = "";
			}

			if (!$rule['interface']) {
				$natif = "wan";
			} else {
				$natif = $rule['interface'];
			}

			if (!isset($FilterIflist[$natif])) {
				continue;
			}

			if (($ipproto == 'inet6') && is_stf_interface($natif)) {
				$rdrif = $natif . "_stf";
			} else {
				$rdrif = $FilterIflist[$natif]['if'];
			}

			$srcaddr = filter_generate_address($rule, 'source', true);
			$dstaddr = filter_generate_address($rule, 'destination', true);
			$srcaddr = trim($srcaddr);
			$dstaddr = trim($dstaddr);

			$dstaddr_port = explode(" ", $dstaddr);
			if (empty($dstaddr_port[0]) || strtolower(trim($dstaddr_port[0])) == "port") {
				continue; // Skip port forward if no destination address found
			}
			$dstaddr_reflect = $dstaddr;
			if (isset($rule['destination']['any'])) {
				/* With reflection enabled, destination of 'any' has side effects
				 * that most people would not expect, so change it on reflection rules. */

				if (!empty($FilterIflist[$natif]['ip'])) {
					$dstaddr_reflect = $FilterIflist[$natif]['ip'];
				} else {
					// no IP, bail
					continue;
				}

				if (!empty($FilterIflist[$natif]['sn'])) {
					$dstaddr_reflect = gen_subnet($dstaddr_reflect, $FilterIflist[$natif]['sn']) . '/' . $FilterIflist[$natif]['sn'];
				}

				if ($dstaddr_port[count($dstaddr_port)-1]) {
					$dstaddr_reflect .= " port " . $dstaddr_port[count($dstaddr_port)-1];
				}
			}

			$natif = $FilterIflist[$natif]['if'];

			$reflection_type = "none";
			if (($rule['natreflection'] != "disable") && ($dstaddr_port[0] != "0.0.0.0") && 
			    ($dstaddr_port[0] != "::")) {
				if ($rule['natreflection'] == "enable") {
					$reflection_type = "proxy";
				} else if ($rule['natreflection'] == "purenat") {
					$reflection_type = "purenat";
				} else if (!isset($config['system']['disablenatreflection'])) {
					if (isset($config['system']['enablenatreflectionpurenat'])) {
						$reflection_type = "purenat";
					} else {
						$reflection_type = "proxy";
					}
				}
			}

			if ($reflection_type != "none") {
				$nat_if_list = filter_get_reflection_interfaces($natif);
			} else {
				$nat_if_list = array();
			}

			if (empty($nat_if_list)) {
				$reflection_type = "none";
			}

			$localport_nat = $localport;
			if (empty($localport_nat) && is_port($dstaddr_port[count($dstaddr_port)-1])) {
				$localport_nat = " port " . $dstaddr_port[count($dstaddr_port)-1];
			}

			if ($srcaddr <> "" && $dstaddr <> "" && $natif) {
				$natrules .= "{$nordr}rdr {$rdrpass}on {$rdrif} {$ipproto} proto {$protocol} from {$srcaddr} to {$dstaddr}" . ($nordr == "" ? " -> {$target}{$localport}" : "");

				/* Does this rule redirect back to a internal host? */
				if (isset($rule['destination']['any']) && !isset($rule['nordr']) && !isset($config['system']['enablenatreflectionhelper']) && !interface_has_gateway($rule['interface'])) {
					if ($ipproto == 'inet') {
						$rule_interface_ip = find_interface_ip($natif);
						$rule_interface_subnet = find_interface_subnet($natif);
					} else {
						$rule_interface_ip = find_interface_ipv6($natif);
						$rule_interface_subnet = find_interface_subnetv6($natif);
					}
					if (!empty($rule_interface_ip) && !empty($rule_interface_subnet)) {
						$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
						$natrules .= "\n";
						/* Do not generate a rule with an interface source if that interface has no IP address.
						 * See https://redmine.pfsense.org/issues/8604 */
						if ((($ipproto == 'inet') && !empty(get_interface_ip($natif))) || 
						    (($ipproto == 'inet6') && !empty(get_interface_ipv6($natif)))) {
							$natrules .= "no nat on {$rdrif} {$ipproto} proto tcp from ({$natif}) to {$rule_subnet}/{$rule_interface_subnet}\n";
						}
						$natrules .= "nat on {$rdrif} {$ipproto} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$dstport[0]} -> ({$natif})\n";
					}
				}

				if ($reflection_type != "none") {
					if ($reflection_type == "proxy" && !isset($rule['nordr'])) {
						$natrules .= filter_generate_reflection_proxy($rule, $nordr, $nat_if_list, $srcaddr, $dstaddr, $starting_localhost_port, $reflection_rules);
						$nat_if_list = array($natif);
						foreach ($reflection_rules as $reflection_rule) {
							fwrite($xinetd_fd, xinetd_service_entry($reflection_rule));
						}
					} else if ($reflection_type == "purenat" || isset($rule['nordr'])) {
						$rdr_if_list = implode(" ", $nat_if_list);
						if (count($nat_if_list) > 1) {
							$rdr_if_list = "{ {$rdr_if_list} }";
						}
						$natrules .= "\n# Reflection redirect\n";
						$natrules .= "{$nordr}rdr {$rdrpass}on {$rdr_if_list} {$ipproto} proto {$protocol} from {$srcaddr} to {$dstaddr_reflect}" . ($nordr == "" ? " -> {$target}{$localport}" : "");
						$nat_if_list = array_merge(array($natif), $nat_if_list);
					}
				}

				if (empty($nat_if_list)) {
					$nat_if_list = array($natif);
				}

				$natrules .= "\n";
				if (!isset($rule['nordr'])) {
					$natrules .= filter_generate_reflection_nat($rule, $route_table, $nat_if_list, $protocol, "{$target}{$localport_nat}", $target_ip);
				}
			}
		}
	}
	fclose($xinetd_fd);		// Close file handle

	$natrules .= discover_pkg_rules("nat");

	$natrules .= "# UPnPd rdr anchor\n";
	$natrules .= "rdr-anchor \"miniupnpd\"\n";

	if (!empty($reflection_txt)) {
		$natrules .= "\n# Reflection redirects and NAT for 1:1 mappings\n" . $reflection_txt;
	}

	// Check if xinetd is running, if not start it.	If so, restart it gracefully.
	if (file_exists("{$g['varetc_path']}/xinetd.conf") && (filesize("{$g['varetc_path']}/xinetd.conf") > 0)) {
		if (isvalidpid("{$g['varrun_path']}/xinetd.pid")) {
			sigkillbypid("{$g['varrun_path']}/xinetd.pid", "HUP");
		} else {
			mwexec("/usr/local/sbin/xinetd " .
			    "-syslog daemon " .
			    "-f {$g['varetc_path']}/xinetd.conf " .
			    "-pidfile {$g['varrun_path']}/xinetd.pid");
		}
	} elseif (isvalidpid("{$g['varrun_path']}/xinetd.pid")) {
		killbypid("{$g['varrun_path']}/xinetd.pid");
	}

	return $natrules;
}

function filter_generate_user_rule_arr($rule) {
	global $config;
	update_filter_reload_status(sprintf(gettext("Creating filter rule %s ..."), $rule['descr']));
	$ret = array();
	$line = filter_generate_user_rule($rule);
	$ret['rule'] = $line;
	$ret['interface'] = $rule['interface'];
	if ($rule['descr'] != "" and $line != "") {
		$ret['descr'] = "label \"" . fix_rule_label(USER_LABEL_INTRO . "{$rule['descr']}") . "\"";
	} else {
		$ret['descr'] = "label \"USER_RULE\"";
	}

	return $ret;
}

function filter_generate_port(& $rule, $target = "source", $isnat = false) {

	$src = "";

	$rule['protocol'] = strtolower($rule['protocol']);
	if (in_array($rule['protocol'], array("tcp", "udp", "tcp/udp"))) {
		if ($rule[$target]['port']) {
			$srcport = explode("-", $rule[$target]['port']);
			$srcporta = alias_expand($srcport[0]);
			if (!$srcporta) {
				log_error(sprintf(gettext('filter_generate_port: %1$s is not a valid %2$s port.'), $srcport[0], $target));
			} else if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
				$src .= " port {$srcporta} ";
			} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
			/* no need for a port statement here */
			} else if ($isnat) {
				$src .= " port {$srcport[0]}:{$srcport[1]}";
			} else {
				if (is_port($srcporta) && $srcport[1] == 65535) {
					$src .= " port >= {$srcporta} ";
				} else if ($srcport[0] == 1) {
					$src .= " port <= {$srcport[1]} ";
				} else {
					$srcport[0]--;
					$srcport[1]++;
					$src .= " port {$srcport[0]} >< {$srcport[1]} ";
				}
			}
		}
	}

	return $src;
}

function filter_address_add_vips_subnets(&$subnets, $if, $not) {
	global $FilterIflist;

	$if_subnets = array($subnets);
	$vips = array();
	$vips6 = array();

	if ($not == true) {
		$subnets = "!{$subnets}";
	}

	if ((!isset($FilterIflist[$if]['vips']) || !is_array($FilterIflist[$if]['vips'])) &&
	    (!isset($FilterIflist[$if]['vips6']) || !is_array($FilterIflist[$if]['vips6']))) {
		return;
	}

	if (isset($FilterIflist[$if]['vips']) && is_array($FilterIflist[$if]['vips'])) {
		$vips = $FilterIflist[$if]['vips'];
	}

	if (isset($FilterIflist[$if]['vips6']) && is_array($FilterIflist[$if]['vips6'])) {
		$vips6 = $FilterIflist[$if]['vips6'];
	}

	foreach (array_merge($vips, $vips6) as $vip) {
		foreach ($if_subnets as $subnet) {
			if (ip_in_subnet($vip['ip'], $subnet)) {
				continue 2;
			}
		}

		if (is_ipaddrv4($vip['ip'])) {
			if (!is_subnetv4($if_subnets[0])) {
				continue;
			}

			$network = gen_subnet($vip['ip'], $vip['sn']);
		} else if (is_ipaddrv6($vip['ip'])) {
			if (!is_subnetv6($if_subnets[0])) {
				continue;
			}

			$network = gen_subnetv6($vip['ip'], $vip['sn']);
		} else {
			continue;
		}

		$subnets .= ' ' . ($not == true ? '!' : '') . $network . '/' . $vip['sn'];
		$if_subnets[] = $network . '/' . $vip['sn'];
	}
	unset($if_subnets);

	if (strpos($subnets, ' ') !== false) {
		$subnets = "{ {$subnets} }";
	}
}

function filter_generate_address(& $rule, $target = "source", $isnat = false) {
	global $FilterIflist, $config;
	$src = "";

	if (isset($rule[$target]['any'])) {
		$src = "any";
	} else if ($rule[$target]['network']) {
		if (strstr($rule[$target]['network'], "opt")) {
			$optmatch = "";
			$matches = "";
			if ($rule['ipprotocol'] == "inet6") {
				if (preg_match("/opt([0-9]*)$/", $rule[$target]['network'], $optmatch)) {
					$opt_sa = $FilterIflist["opt{$optmatch[1]}"]['sav6'];
					if (!is_ipaddrv6($opt_sa)) {
						return "";
					}
					$src = $opt_sa . "/" . $FilterIflist["opt{$optmatch[1]}"]['snv6'];
				/* check for opt$NUMip here */
				} else if (preg_match("/opt([0-9]*)ip/", $rule[$target]['network'], $matches)) {
					$src = $FilterIflist["opt{$matches[1]}"]['ipv6'];
					if (!is_ipaddrv6($src)) {
						return "";
					}
					if (isset($rule[$target]['not'])) {
						$src = " !{$src}";
					}
				}
			} else {
				if (preg_match("/opt([0-9]*)$/", $rule[$target]['network'], $optmatch)) {
					$opt_sa = $FilterIflist["opt{$optmatch[1]}"]['sa'];
					if (!is_ipaddrv4($opt_sa)) {
						return "";
					}
					$src = $opt_sa . "/" . $FilterIflist["opt{$optmatch[1]}"]['sn'];
				/* check for opt$NUMip here */
				} else if (preg_match("/opt([0-9]*)ip/", $rule[$target]['network'], $matches)) {
					$src = $FilterIflist["opt{$matches[1]}"]['ip'];
					if (!is_ipaddrv4($src)) {
						return "";
					}
					if (isset($rule[$target]['not'])) {
						$src = " !{$src}";
					}
				}
			}
		} else {
			if ($rule['ipprotocol'] == "inet6") {
				switch ($rule[$target]['network']) {
					case 'wan':
						$wansa = $FilterIflist['wan']['sav6'];
						if (!is_ipaddrv6($wansa)) {
							return "";
						}
						$wansn = $FilterIflist['wan']['snv6'];
						$src = "{$wansa}/{$wansn}";
						break;
					case 'wanip':
						$src = $FilterIflist["wan"]['ipv6'];
						if (!is_ipaddrv6($src)) {
							return "";
						}
						break;
					case 'lanip':
						$src = $FilterIflist["lan"]['ipv6'];
						if (!is_ipaddrv6($src)) {
							return "";
						}
						break;
					case 'lan':
						$lansa = $FilterIflist['lan']['sav6'];
						if (!is_ipaddrv6($lansa)) {
							return "";
						}
						$lansn = $FilterIflist['lan']['snv6'];
						$src = "{$lansa}/{$lansn}";
						break;
					case '(self)':
						$src = "(self)";
						break;
					case 'pppoe':
						if (is_array($FilterIflist['pppoe'])) {
							$src = "{ ";
							foreach ($FilterIflist['pppoe'] as $pppoe) {
								$pppoesav6 = gen_subnetv6($pppoe['ipv6'], $pppoe['snv6']);
								$pppoesnv6 = $pppoe['snv6'];
								$src .= "{$pppoesav6}/{$pppoesnv6} ";
							}
							$src .= "}";
						}
					}
				if (isset($rule[$target]['not']) && !is_subnet($src)) {
					$src = " !{$src}";
				}
			} else {
				switch ($rule[$target]['network']) {
					case 'wan':
						$wansa = $FilterIflist['wan']['sa'];
						if (!is_ipaddrv4($wansa)) {
							return "";
						}
						$wansn = $FilterIflist['wan']['sn'];
						$src = "{$wansa}/{$wansn}";
						break;
					case 'wanip':
						$src = $FilterIflist["wan"]['ip'];
						break;
					case 'lanip':
						$src = $FilterIflist["lan"]['ip'];
						break;
					case 'lan':
						$lansa = $FilterIflist['lan']['sa'];
						if (!is_ipaddrv4($lansa)) {
							return "";
						}
						$lansn = $FilterIflist['lan']['sn'];
						$src = "{$lansa}/{$lansn}";
						break;
					case '(self)':
						$src = "(self)";
						break;
					case 'pppoe':
						if (is_array($FilterIflist['pppoe'])) {
							$src = "{ ";
							foreach ($FilterIflist['pppoe'] as $pppoe) {
								$pppoesa = gen_subnet($pppoe['sa'], $pppoe['sn']);
								$pppoesn = $pppoe['sn'];
								$src .= "{$pppoesa}/{$pppoesn} ";
							}
							$src .= "}";
						}
						break;
				}
				if ((isset($rule[$target]['not'])) &&
				    (!is_subnet($src)) &&
				    (strpos($src, '{') === false)) {
					$src = " !{$src}";
				}
			}
		}
		if (is_subnet($src)) {
			filter_address_add_vips_subnets($src, $rule[$target]['network'], isset($rule[$target]['not']));
		}
	} else if ($rule[$target]['address']) {
		$expsrc = alias_expand($rule[$target]['address']);
		if (isset($rule[$target]['not'])) {
			$not = "!";
		} else {
			$not = "";
		}
		$src = " {$not} {$expsrc}";
	}

	if (empty($src)) {
		return '';
	}

	$src .= filter_generate_port($rule, $target, $isnat);

	return $src;
}

function filter_generate_user_rule($rule) {
	global $config, $g, $FilterIflist, $GatewaysList;
	global $dummynet_name_list, $vlanprio_values;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_user_rule() being called $mt\n";
	}
	/* don't include disabled rules */
	if (isset($rule['disabled'])) {
		return "# rule " . $rule['descr'] . " disabled \n";
	}
	update_filter_reload_status(sprintf(gettext("Creating filter rules %s ..."), $rule['descr']));
	$int = "";
	$aline = array();

	/* Check to see if the interface is in our list */
	if (isset($rule['floating'])) {
		if (isset($rule['interface']) && $rule['interface'] <> "") {
			$interfaces = explode(",", $rule['interface']);
			$ifliste = "";
			foreach ($interfaces as $iface) {
				if (array_key_exists($iface, $FilterIflist)) {
					if (isset($FilterIflist[$iface]['if'])) {
						$ifliste .= " " . $FilterIflist[$iface]['if'] . " ";
						if (($FilterIflist[$iface]['type6'] == '6rd') ||
						    ($FilterIflist[$iface]['type6'] == '6to4')) {
							$ifliste .= " " . $FilterIflist[$iface]['ifv6'] . " ";
						}
					} elseif (isset($FilterIflist[$iface][0]['if'])) {
						$ifliste .= " " . $FilterIflist[$iface][0]['if'] . " ";
					}
				}
			}
			if (!empty($ifliste)) {
				$aline['interface'] = " on { {$ifliste} } ";
			} else {
				$aline['interface'] = "";
			}
		} else {
			$aline['interface'] = "";
		}
	} else if (!array_key_exists($rule['interface'], $FilterIflist)) {
		$items = "";
		foreach ($FilterIflist as $oc) {
			$items .= $oc['descr'] . " ";
		}
		return "# array key \"{$rule['interface']}\" does not exist for \"" . $rule['descr'] . "\" in array: {{$items}}";
	} else if ((array_key_exists($rule['interface'], $FilterIflist)) &&
	    (is_array($FilterIflist[$rule['interface']])) &&
	    (is_array($FilterIflist[$rule['interface']][0]))) {
		/* Currently the only case for this is the pppoe server. There should be an existing macro with this name. */
		$aline['interface'] = " on \$" . $rule['interface'] . " ";
	} else {
		$aline['interface'] = " on \$" . $FilterIflist[$rule['interface']]['descr'] . " ";
	}
	$ifcfg = $FilterIflist[$rule['interface']];

	switch ($rule['ipprotocol']) {
		case "inet":
			$aline['ipprotocol'] = "inet";
			break;
		case "inet6":
			$aline['ipprotocol'] = "inet6";
			break;
		default:
			$aline['ipprotocol'] = "inet";
			break;
	}

	/* check for unresolvable aliases */
	if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
		$error_text = sprintf(gettext("Unresolvable source alias '%1\$s' for rule '%2\$s'"), $rule['source']['address'], $rule['descr']);
		file_notice("Filter_Reload", $error_text);
		return "# {$error_text}";
	}
	if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
		$error_text = sprintf(gettext("Unresolvable destination alias '%1\$s' for rule '%2\$s'"), $rule['destination']['address'], $rule['descr']);
		file_notice("Filter_Reload", $error_text);
		return "# {$error_text}";
	}
	if ($rule['source']['port']
	    && !is_port_or_range(str_replace("-", ":", $rule['source']['port']))) {
		$error_text = "";

		// It is not a literal port or port range, so alias should exist, and expand to something non-empty
		if (!alias_expand($rule['source']['port'])) {
			$error_text = sprintf(gettext("Unresolvable source port alias '%1\$s' for rule '%2\$s'"), $rule['source']['port'], $rule['descr']);
		} else if (trim(filter_generate_nested_alias($rule['source']['port'])) == "") {
			$error_text = sprintf(gettext("Empty source port alias '%1\$s' for rule '%2\$s'"), $rule['source']['port'], $rule['descr']);
		}

		if ($error_text) {
			file_notice("Filter_Reload", $error_text);
			return "# {$error_text}";
		}
	}
	if ($rule['destination']['port']
	    && !is_port_or_range(str_replace("-", ":", $rule['destination']['port']))) {
		$error_text = "";

		// It is not a literal port or port range, so alias should exist, and expand to something non-empty
		if (!alias_expand($rule['destination']['port'])) {
			$error_text = sprintf(gettext("Unresolvable destination port alias '%1\$s' for rule '%2\$s'"), $rule['destination']['port'], $rule['descr']);
		} else if (trim(filter_generate_nested_alias($rule['destination']['port'])) == "") {
			$error_text = sprintf(gettext("Empty destination port alias '%1\$s' for rule '%2\$s'"), $rule['destination']['port'], $rule['descr']);
		}

		if ($error_text) {
			file_notice("Filter_Reload", $error_text);
			return "# {$error_text}";
		}
	}
	update_filter_reload_status(gettext("Setting up pass/block rules"));
	$type = $rule['type'];
	if ($type != "pass" && $type != "block" && $type != "reject" && $type != "match") {
		/* default (for older rules) is pass */
		$type = "pass";
	}
	if ($type == "reject") {
		$aline['type'] = "block return ";
	} else {
		$aline['type'] = $type . " ";
	}
	if (isset($rule['floating']) && $rule['floating'] == "yes") {
		if ($rule['direction'] != "any") {
			$aline['direction'] = " " . $rule['direction'] . " ";
		}
	} else {
		/* ensure the direction is in */
		$aline['direction'] = " in ";
	}
	if (isset($rule['log'])) {
		$aline['log'] = "log ";
	}
	if (!isset($rule['floating']) || isset($rule['quick'])) {
		$aline['quick'] = " quick ";
	}

	/* set the gateway interface */
	update_filter_reload_status(sprintf(gettext("Setting up pass/block rules %s"), $rule['descr']));

	/* do not process reply-to for gateway'd rules */
	if ($rule['gateway'] == "" && $aline['direction'] <> "" && (interface_has_gateway($rule['interface']) || interface_has_gatewayv6($rule['interface'])) && !isset($config['system']['disablereplyto']) && !isset($rule['disablereplyto']) && $type != "match") {
		if ($rule['ipprotocol'] == "inet6") {
			$rg = get_interface_gateway_v6($rule['interface']);
			if (is_ipaddrv6($rg)) {
				$aline['reply'] = "reply-to ( {$ifcfg['ifv6']} {$rg} ) ";
			}
		} else {
			$rg = get_interface_gateway($rule['interface']);
			if (is_ipaddrv4($rg)) {
				$aline['reply'] = "reply-to ( {$ifcfg['if']} {$rg} ) ";
			}
		}
	}
	/* if user has selected a custom gateway, lets work with it */
	else if ($rule['gateway'] <> "" && $type == "pass") {
		if (isset($GatewaysList[$rule['gateway']])) {
			/* Add the load balanced gateways */
			$aline['route'] = " \$GW{$rule['gateway']} ";
		} else if (isset($config['system']['skip_rules_gw_down'])) {
			return "# rule " . $rule['descr'] . " disabled because gateway " . $rule['gateway'] . " is down ";
		} else {
			log_error(sprintf(gettext("The gateway: %s is invalid or unknown, not using it."), $rule['gateway']));
		}
	}

	if (isset($rule['protocol']) && !empty($rule['protocol'])) {
		if ($rule['protocol'] == "tcp/udp") {
			$aline['prot'] = " proto { tcp udp } ";
		} elseif (($rule['protocol'] == "icmp") && ($rule['ipprotocol'] == "inet6")) {
			$aline['prot'] = " proto ipv6-icmp ";
		} elseif ($rule['protocol'] == "icmp") {
			$aline['prot'] = " proto icmp ";
		} else {
			$aline['prot'] = " proto {$rule['protocol']} ";
		}
	} else {
		if ($rule['source']['port'] <> "" || $rule['destination']['port'] <> "") {
			$aline['prot'] = " proto tcp ";
		}
	}
	update_filter_reload_status(sprintf(gettext("Creating rule %s"), $rule['descr']));

	/* source address */
	$src = trim(filter_generate_address($rule, "source"));
	if (empty($src) || ($src == "/")) {
		return "# at the break!";
	}
	$aline['src'] = " from $src ";

	/* OS signatures */
	if (($rule['protocol'] == "tcp") && ($rule['os'] <> "")) {
		$aline['os'] = " os \"{$rule['os']}\" ";
	}

	/* destination address */
	$dst = trim(filter_generate_address($rule, "destination"));
	if (empty($dst) || ($dst == "/")) {
		return "# returning at dst $dst == \"/\"";
	}
	$aline['dst'] = "to $dst ";

	if ($rule['protocol'] == "icmp" && $rule['icmptype'] && ($rule['icmptype'] != 'any')) {
		$icmptype_key = ($rule['ipprotocol'] == 'inet6' ? 'icmp6-type' : 'icmp-type');
		// XXX: Bug #7372
		$icmptype_text = replace_element_in_list($rule['icmptype'], ',', 'skip', '39');
		$icmptype_text = (strpos($icmptype_text, ",") === false ? $icmptype_text : '{ ' . $icmptype_text . ' }');
		$aline[$icmptype_key] = "{$icmptype_key} {$icmptype_text} ";
	}

	if (!empty($rule['tag'])) {
		$aline['tag'] = " tag \"" .$rule['tag']. "\" ";
	}
	if (!empty($rule['tagged'])) {
		$aline['tagged'] = " tagged \"" .$rule['tagged']. "\" ";
	}
	if (!empty($rule['dscp'])) {
		switch (strtolower($rule['dscp'])) {
			case 'va':
				$aline['dscp'] = " dscp \"44\" ";
				break;
			case 'VA':
				$aline['dscp'] = " dscp \"44\" ";
				break;
			case 'cs1':
				$aline['dscp'] = " dscp \"8\" ";
				break;
			case 'cs2':
				$aline['dscp'] = " dscp \"16\" ";
				break;
			case 'cs3':
				$aline['dscp'] = " dscp \"24\" ";
				break;
			case 'cs4':
				$aline['dscp'] = " dscp \"32\" ";
				break;
			case 'cs5':
				$aline['dscp'] = " dscp \"40\" ";
				break;
			case 'cs6':
				$aline['dscp'] = " dscp \"48\" ";
				break;
			case 'cs7':
				$aline['dscp'] = " dscp \"56\" ";
				break;
			default:
				$aline['dscp'] = " dscp " . $rule['dscp'] . " ";
				break;
		}
	}
	if (!empty($rule['vlanprio']) && ($rule['vlanprio'] != "none")) {
		$aline['vlanprio'] = " prio " . $vlanprio_values[$rule['vlanprio']] . " ";
	}
	if (!empty($rule['vlanprioset']) && ($rule['vlanprioset'] != "none")) {
		$aline['vlanprioset'] = " set prio " . $vlanprio_values[$rule['vlanprioset']] . " ";
	}
	if (!empty($rule['tagged']) && isset($rule['nottagged'])) {
		$aline['nottagged'] = " ! ";
	}
	if ($type == "pass") {
		if (isset($rule['allowopts'])) {
			$aline['allowopts'] = " allow-opts ";
		}
	}
	$aline['flags'] = "";
	if ($rule['protocol'] == "tcp") {
		if (isset($rule['tcpflags_any'])) {
			$aline['flags'] = "flags any ";
		} else if (!empty($rule['tcpflags2'])) {
			$aline['flags'] = "flags ";
			if (!empty($rule['tcpflags1'])) {
				$flags1 = explode(",", $rule['tcpflags1']);
				foreach ($flags1 as $flag1) {
					// CWR flag needs special treatment
					if ($flag1[0] == "c") {
						$aline['flags'] .= "W";
					} else {
						$aline['flags'] .= strtoupper($flag1[0]);
					}
				}
			}
			$aline['flags'] .= "/";
			if (!empty($rule['tcpflags2'])) {
				$flags2 = explode(",", $rule['tcpflags2']);
				foreach ($flags2 as $flag2) {
					// CWR flag needs special treatment
					if ($flag2[0] == "c") {
						$aline['flags'] .= "W";
					} else {
						$aline['flags'] .= strtoupper($flag2[0]);
					}
				}
			}
			$aline['flags'] .= " ";
		} else {
			$aline['flags'] = "flags S/SA ";
		}
	}
	if ($type == "pass") {
		/*
		 *	# keep state
		 *		works with TCP, UDP, and ICMP.
		 *	# modulate state
		 *		deprecated
		 *	# synproxy state
		 *		proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods.
		 *	# none
		 *		do not use state mechanisms to keep track. this is only useful if your doing advanced
		 *		queueing in certain situations. please check the faq.
		 */
		$noadvoptions = false;
		if (isset($rule['statetype']) && $rule['statetype'] <> "") {
			switch ($rule['statetype']) {
				case "none":
					$noadvoptions = true;
					$aline['flags'] .= " no state ";
					break;
				case "modulate state":
				case "synproxy state":
					if ($rule['protocol'] == "tcp") {
						$aline['flags'] .= "{$rule['statetype']} ";
					}
					break;
				case "sloppy state":
					$aline['flags'] .= "keep state ";
					$rule['sloppy'] = true;
					break;
				default:
					$aline['flags'] .= "{$rule['statetype']} ";
					break;
			}
		} else {
			$aline['flags'] .= "keep state ";
		}

		if ($noadvoptions == false && isset($rule['nopfsync'])) {
			$rule['nopfsync'] = true;
		}

		if ($noadvoptions == false) {
			if ((isset($rule['source-track']) and $rule['source-track'] <> "") or
			    (isset($rule['max']) and $rule['max'] <> "") or
			    (isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "") or
			    (isset($rule['max-src-states']) and $rule['max-src-states'] <> "") or
			    (isset($rule['statetimeout']) and $rule['statetimeout'] <> "") or
			    ((in_array($rule['protocol'], array("tcp", "tcp/udp"))) and
			      ((isset($rule['max-src-conn']) and $rule['max-src-conn'] <> "") or
			      (isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "") or
			      (isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> ""))) or
			    (isset($rule['sloppy'])) or
			    (isset($rule['nopfsync']))) {
				$aline['flags'] .= "( ";
				if (isset($rule['sloppy'])) {
					$aline['flags'] .= "sloppy ";
				}
				if (isset($rule['nopfsync'])) {
					$aline['flags'] .= "no-sync ";
				}
				if (isset($rule['source-track']) and $rule['source-track'] <> "") {
					$aline['flags'] .= "source-track rule ";
				}
				if (isset($rule['max']) and $rule['max'] <> "") {
					$aline['flags'] .= "max " . $rule['max'] . " ";
				}
				if (isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "") {
					$aline['flags'] .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
				}
				if ((in_array($rule['protocol'], array("tcp", "tcp/udp"))) and
				    (isset($rule['max-src-conn'])) and
				    ($rule['max-src-conn'] <> "")) {
					$aline['flags'] .= "max-src-conn " . $rule['max-src-conn'] . " ";
				}
				if (isset($rule['max-src-states']) and $rule['max-src-states'] <> "") {
					$aline['flags'] .= "max-src-states " . $rule['max-src-states'] . " ";
				}
				if (isset($rule['statetimeout']) and $rule['statetimeout'] <> "") {
					if (in_array($rule['protocol'], array("tcp", "tcp/udp")) ||
					    empty($rule['protocol'])) { 
						$aline['flags'] .= "tcp.established " . $rule['statetimeout'] . " ";
					}
					if (in_array($rule['protocol'], array("udp", "tcp/udp")) ||
					    empty($rule['protocol'])) { 
						$aline['flags'] .= "udp.multiple " . $rule['statetimeout'] . " ";
					}
					if (!in_array($rule['protocol'], array("udp", "tcp", "tcp/udp")) ||
					    empty($rule['protocol'])) { 
						$aline['flags'] .= "other.multiple " . $rule['statetimeout'] . " ";
					}
				}
				if ((in_array($rule['protocol'], array("tcp", "tcp/udp"))) and
				    (isset($rule['max-src-conn-rate'])) and
				    ($rule['max-src-conn-rate'] <> "") and
				    (isset($rule['max-src-conn-rates'])) and
				    ($rule['max-src-conn-rates'] <> "")) {
					$aline['flags'] .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";
					$aline['flags'] .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
				}

				$aline['flags'] .= " ) ";
			}
		}
	}
	if ($rule['defaultqueue'] <> "") {
		$aline['queue'] = " queue (".$rule['defaultqueue'];
		if ($rule['ackqueue'] <> "") {
			$aline['queue'] .= "," . $rule['ackqueue'];
		}
		$aline['queue'] .= ") ";
	}
	if ($rule['dnpipe'] <> "") {
		if (!empty($dummynet_name_list[$rule['dnpipe']])) {
			if ($dummynet_name_list[$rule['dnpipe']][0] == "?") {
				$aline['dnpipe'] = " dnqueue( ";
				$aline['dnpipe'] .= substr($dummynet_name_list[$rule['dnpipe']], 1);
				if ($rule['pdnpipe'] <> "") {
					$aline['dnpipe'] .= "," . substr($dummynet_name_list[$rule['pdnpipe']], 1);
				}
			} else {
				$aline['dnpipe'] = " dnpipe ( " . $dummynet_name_list[$rule['dnpipe']];
				if ($rule['pdnpipe'] <> "") {
					$aline['dnpipe'] .= "," . $dummynet_name_list[$rule['pdnpipe']];
				}
			}
			$aline['dnpipe'] .= ") ";
		}
	}

	/* is a time based rule schedule attached? */
	if (!empty($rule['sched']) && !empty($config['schedules'])) {
		$aline['schedlabel'] = "";
		foreach ($config['schedules']['schedule'] as $sched) {
			if ($sched['name'] == $rule['sched']) {
				if (!filter_get_time_based_rule_status($sched)) {
					if (!isset($config['system']['schedule_states'])) {
						mwexec("/sbin/pfctl -y {$sched['schedlabel']}");
					}
					return "# schedule finished - {$rule['descr']}";
				} else if ($g['debug']) {
					log_error(sprintf(gettext("[TDR DEBUG] status true -- rule type '%s'"), $type));
				}

				$aline['schedlabel'] = " schedule \"{$sched['schedlabel']}\" ";
				break;
			}
		}
	}

	if (!empty($rule['tracker'])) {
		$aline['tracker'] = "tracker {$rule['tracker']} ";
	}

	$line = "";
	/* exception(s) to a user rules can go here. */
	/* rules with a gateway or pool should create another rule for routing to vpns */
	if ((($aline['route'] <> "") && (trim($aline['type']) == "pass") && strstr($dst, "any")) && (!isset($config['system']['disablenegate']))) {
		/* negate VPN/PPTP/PPPoE/Static Route networks for load balancer/gateway rules */
		$negate_networks = " to <negate_networks> " . filter_generate_port($rule, "destination");
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] .
			$aline['interface'] . $aline['ipprotocol'] . $aline['prot'] . $aline['src'] . $aline['os'] .
			$negate_networks . $aline['icmp-type'] . $aline['icmp6-type'] . $aline['tag'] . $aline['nottagged'] . $aline['tagged'] .
			$aline['vlanprio'] . $aline['vlanprioset'] . $aline['dscp'] . filter_negaterule_tracker() . $aline['allowopts'] . $aline['flags'] .
			$aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'] .
			" label \"NEGATE_ROUTE: Negate policy routing for destination\"\n";

	}
	/* piece together the actual user rule */
	$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] .
		$aline['reply'] . $aline['route'] . $aline['ipprotocol'] . $aline['prot'] . $aline['src'] . $aline['os'] . $aline['dst'] .
		$aline['icmp-type'] . $aline['icmp6-type'] . $aline['tag'] . $aline['nottagged'] . $aline['tagged'] . $aline['dscp'] . $aline['tracker'] .
		$aline['vlanprio'] . $aline['vlanprioset'] . $aline['allowopts'] . $aline['flags'] . $aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'];

	unset($aline);

	return $line;
}

function filter_rules_generate() {
	global $config, $g, $FilterIflist, $time_based_rules, $GatewaysList,
	       $tracker, $vlanprio_values, $antilockout_tracker,
	       $bogons_tracker, $rfc1918_tracker;

	$fix_rule_label = 'fix_rule_label';
	$increment_tracker = 'filter_rule_tracker';
	$antilockout_increment = 'filter_antilockout_tracker';
	$bogons_increment = 'filter_bogons_tracker';
	$rfc1918_increment = 'filter_rfc1918_tracker';

	update_filter_reload_status(gettext("Creating default rules"));
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$ipfrules = "";
	$ipfrules .= discover_pkg_rules("pfearly");

	/* OpenVPN user rules from radius */
	$ipfrules .= "anchor \"openvpn/*\"\n";
	/* IPsec user rules from radius */
	$ipfrules .= "anchor \"ipsec/*\"\n";
	# BEGIN OF firewall rules
	/* default block logging? */
	$log = array();
	if (!isset($config['syslog']['nologdefaultblock'])) {
		$log['block'] = "log";
	}
	if (isset($config['syslog']['nologdefaultpass'])) {
		$log['pass'] = "log";
	}

	$saved_tracker = $tracker;

	if (!isset($config['system']['ipv6allow'])) {
		$ipfrules .= "# Allow IPv6 on loopback\n";
		$ipfrules .= "pass in {$log['pass']} quick on \$loopback inet6 all tracker {$increment_tracker()} label \"pass IPv6 loopback\"\n";
		$ipfrules .= "pass out {$log['pass']} quick on \$loopback inet6 all tracker {$increment_tracker()} label \"pass IPv6 loopback\"\n";
		$ipfrules .= "# Block all IPv6\n";
		$ipfrules .= "block in {$log['block']} quick inet6 all tracker {$increment_tracker()} label \"Block all IPv6\"\n";
		$ipfrules .= "block out {$log['block']} quick inet6 all tracker {$increment_tracker()} label \"Block all IPv6\"\n";
	}

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	if (!isset($config['system']['no_apipa_block'])) {
		$ipfrules .= <<<EOD
# block IPv4 link-local. Per RFC 3927, link local "MUST NOT" be forwarded by a routing device,
# and clients "MUST NOT" send such packets to a router. FreeBSD won't route 169.254./16, but
# route-to can override that, causing problems such as in redmine #2073
block in {$log['block']} quick from 169.254.0.0/16 to any tracker {$increment_tracker()} label "Block IPv4 link-local"
block in {$log['block']} quick from any to 169.254.0.0/16 tracker {$increment_tracker()} label "Block IPv4 link-local"

EOD;
	}

	$ipfrules .= <<<EOD
#---------------------------------------------------------------------------
# default deny rules
#---------------------------------------------------------------------------
block in {$log['block']} inet all tracker {$increment_tracker()} label "Default deny rule IPv4"
block out {$log['block']} inet all tracker {$increment_tracker()} label "Default deny rule IPv4"
block in {$log['block']} inet6 all tracker {$increment_tracker()} label "Default deny rule IPv6"
block out {$log['block']} inet6 all tracker {$increment_tracker()} label "Default deny rule IPv6"

EOD;

	if (isset($config['system']['ipv6allow'])) {
		$ipfrules .= <<<EOD

# IPv6 ICMP is not auxiliary, it is required for operation
# See man icmp6(4)
# 1    unreach         Destination unreachable
# 2    toobig          Packet too big
# 128  echoreq         Echo service request
# 129  echorep         Echo service reply
# 133  routersol       Router solicitation
# 134  routeradv       Router advertisement
# 135  neighbrsol      Neighbor solicitation
# 136  neighbradv      Neighbor advertisement
pass {$log['pass']} quick inet6 proto ipv6-icmp from any to any icmp6-type {1,2,135,136} tracker {$increment_tracker()} keep state

# Allow only bare essential icmpv6 packets (NS, NA, and RA, echoreq, echorep)
pass out {$log['pass']} quick inet6 proto ipv6-icmp from fe80::/10 to fe80::/10 icmp6-type {129,133,134,135,136} tracker {$increment_tracker()} keep state
pass out {$log['pass']} quick inet6 proto ipv6-icmp from fe80::/10 to ff02::/16 icmp6-type {129,133,134,135,136} tracker {$increment_tracker()} keep state
pass in {$log['pass']} quick inet6 proto ipv6-icmp from fe80::/10 to fe80::/10 icmp6-type {128,133,134,135,136} tracker {$increment_tracker()} keep state
pass in {$log['pass']} quick inet6 proto ipv6-icmp from ff02::/16 to fe80::/10 icmp6-type {128,133,134,135,136} tracker {$increment_tracker()} keep state
pass in {$log['pass']} quick inet6 proto ipv6-icmp from fe80::/10 to ff02::/16 icmp6-type {128,133,134,135,136} tracker {$increment_tracker()} keep state
pass in {$log['pass']} quick inet6 proto ipv6-icmp from :: to ff02::/16 icmp6-type {128,133,134,135,136} tracker {$increment_tracker()} keep state

EOD;
	}
	$ipfrules .= <<<EOD
# We use the mighty pf, we cannot be fooled.
block {$log['block']} quick inet proto { tcp, udp } from any port = 0 to any tracker {$increment_tracker()} label "Block traffic from port 0"
block {$log['block']} quick inet proto { tcp, udp } from any to any port = 0 tracker {$increment_tracker()} label "Block traffic to port 0"

EOD;
	if (isset($config['system']['ipv6allow'])) {
		$ipfrules .= <<<EOD
block {$log['block']} quick inet6 proto { tcp, udp } from any port = 0 to any tracker {$increment_tracker()} label "Block traffic from port 0"
block {$log['block']} quick inet6 proto { tcp, udp } from any to any port = 0 tracker {$increment_tracker()} label "Block traffic to port 0"

EOD;
	}
	$ipfrules .= <<<EOD

# Snort package
block {$log['block']} quick from <snort2c> to any tracker {$increment_tracker()} label "Block snort2c hosts"
block {$log['block']} quick from any to <snort2c> tracker {$increment_tracker()} label "Block snort2c hosts"

EOD;

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	$ipfrules .= filter_process_carp_rules($log);

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	$ipfrules .= "\n# SSH lockout\n";
	if (is_array($config['system']['ssh']) && !empty($config['system']['ssh']['port'])) {
		$ipfrules .= "block in {$log['block']} quick proto tcp from <sshguard> to (self) port ";
		$ipfrules .= $config['system']['ssh']['port'];
		$ipfrules .= " tracker {$increment_tracker()} label \"sshguard\"\n";
	} else {
		if ($config['system']['ssh']['port'] <> "") {
			$sshport = $config['system']['ssh']['port'];
		} else {
			$sshport = 22;
		}
		if ($sshport) {
			$ipfrules .= "block in {$log['block']} quick proto tcp from <sshguard> to (self) port {$sshport} tracker {$increment_tracker()} label \"sshguard\"\n";
		}
	}

	$saved_tracker += 50;
	$tracker = $saved_tracker;

	$ipfrules .= "\n# webConfigurator lockout\n";
	if (!$config['system']['webgui']['port']) {
		if ($config['system']['webgui']['protocol'] == "http") {
			$webConfiguratorlockoutport = "80";
		} else {
			$webConfiguratorlockoutport = "443";
		}
	} else {
		$webConfiguratorlockoutport = $config['system']['webgui']['port'];
	}
	if ($webConfiguratorlockoutport) {
		$ipfrules .= "block in {$log['block']} quick proto tcp from <sshguard> to (self) port {$webConfiguratorlockoutport} tracker {$increment_tracker()} label \"GUI Lockout\"\n";
	}

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	/*
	 * Support for allow limiting of TCP connections by establishment rate
	 * Useful for protecting against sudden outbursts, etc.
	 */
	$ipfrules .= "block in {$log['block']} quick from <virusprot> to any tracker 1000000400 label \"virusprot overload table\"\n";

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	/* if captive portal is enabled, ensure that access to this port
	 * is allowed on a locked down interface
	 */
	if (is_array($config['captiveportal'])) {
		foreach ($config['captiveportal'] as $cpcfg) {
			if (!isset($cpcfg['enable'])) {
				continue;
			}
			$cpinterfaces = explode(",", $cpcfg['interface']);
			$cpiflist = array();
			$cpiplist = array();
			foreach ($cpinterfaces as $cpifgrp) {
				if (!isset($FilterIflist[$cpifgrp])) {
					continue;
				}
				$tmpif = get_real_interface($cpifgrp);
				if (!empty($tmpif)) {
					$cpiflist[] = "{$tmpif}";
					$cpipm = get_interface_ip($cpifgrp);
					if (is_ipaddr($cpipm)) {
						$cpiplist[] = $cpipm;
						if (!is_array($config['virtualip']) || !is_array($config['virtualip']['vip'])) {
							continue;
						}
						foreach ($config['virtualip']['vip'] as $vip) {
							if (($vip['interface'] == $cpifgrp) && (($vip['mode'] == "carp") || ($vip['mode'] == "ipalias"))) {
								$cpiplist[] = $vip['subnet'];
							}
						}
					}
				}
			}
			if (count($cpiplist) > 0 && count($cpiflist) > 0) {
				$cpinterface = implode(" ", $cpiflist);
				$cpaddresses = implode(" ", $cpiplist);
				$listenporthttps = $cpcfg['listenporthttps'] ? $cpcfg['listenporthttps'] : 8000 + ($cpcfg['zoneid'] + 1);
				$listenporthttp  = $cpcfg['listenporthttp']  ? $cpcfg['listenporthttp']  : 8000 + $cpcfg['zoneid'];
				$portalias = $listenporthttps;
				$portalias .= " {$listenporthttp}";
				$ipfrules .= "pass in {$log['pass']} quick on { {$cpinterface} } proto tcp from any to { {$cpaddresses} } port { {$portalias} } tracker {$increment_tracker()} keep state(sloppy)\n";
				$ipfrules .= "pass out {$log['pass']} quick on { {$cpinterface} } proto tcp from any to any flags any tracker {$increment_tracker()} keep state(sloppy)\n";
			}
		}
	}

	$bogontableinstalled = 0;
	foreach ($FilterIflist as $on => $oc) {
		$saved_tracker += 10;
		$tracker = $saved_tracker;

		if ($oc['type'] == "dhcp") {
			$vlantag = isset($config['interfaces'][$on]['dhcpvlanenable']) ? "set prio {$vlanprio_values[$config['interfaces'][$on]['dhcpcvpt']]}" : "";
			$ipfrules .= <<<EOD
# allow our DHCP client out to the {$oc['descr']}
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 67 to any port = 68 tracker {$increment_tracker()} label "{$fix_rule_label("allow dhcp client out {$oc['descr']}")}"
pass out {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 68 to any port = 67 tracker {$increment_tracker()} label "{$fix_rule_label("allow dhcp client out {$oc['descr']}")}" {$vlantag}
# Not installing DHCP server firewall rules for {$oc['descr']} which is configured for DHCP.

EOD;
		}

		if (isset($config['system']['ipv6allow']) && ($oc['type6'] == "slaac" || $oc['type6'] == "dhcp6")) {
			// The DHCPv6 client rules ***MUST BE ABOVE BOGONSV6!***  https://redmine.pfsense.org/issues/3395
			$vlantag = isset($config['interfaces'][$on]['dhcp6vlanenable']) ? "set prio {$vlanprio_values[$config['interfaces'][$on]['dhcp6cvpt']]}" : "";

			$ipfrules .= <<<EOD
# allow our DHCPv6 client out to the {$oc['descr']}
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from fe80::/10 port = 546 to fe80::/10 port = 546 tracker {$increment_tracker()} label "{$fix_rule_label("allow dhcpv6 client in {$oc['descr']}")}"
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 547 to any port = 546 tracker {$increment_tracker()} label "{$fix_rule_label("allow dhcpv6 client in {$oc['descr']}")}"
# Add Priority to dhcp6c packets if enabled
pass out {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 546 to any port = 547 tracker {$increment_tracker()} label "{$fix_rule_label("allow dhcpv6 client out {$oc['descr']}")}" {$vlantag}

EOD;
		}

		/* XXX: Not static but give a step of 1000 for each interface to at least be able to match rules. */
		$saved_tracker += 1000;
		$tracker = $saved_tracker;

		/* block bogon networks */
		/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
		/* file is automatically in cron every 3000 minutes */
		if (!isset($config['syslog']['nologbogons'])) {
			$bogonlog = "log";
		} else {
			$bogonlog = "";
		}

		if (isset($config['interfaces'][$on]['blockbogons'])) {
			$ipfrules .= <<<EOD
# block bogon networks (IPv4)
# http://www.cymru.com/Documents/bogon-bn-nonagg.txt
block in $bogonlog quick on \${$oc['descr']} from <bogons> to any tracker {$bogons_increment()} label "{$fix_rule_label("block bogon IPv4 networks from {$oc['descr']}")}"

EOD;

			if (isset($config['system']['ipv6allow'])) {
				$ipfrules .= <<<EOD
# block bogon networks (IPv6)
# http://www.team-cymru.org/Services/Bogons/fullbogons-ipv6.txt
block in $bogonlog quick on \${$oc['descr']} from <bogonsv6> to any tracker {$bogons_increment()} label "{$fix_rule_label("block bogon IPv6 networks from {$oc['descr']}")}"

EOD;
			}
		}

		$saved_tracker += 10;
		$tracker = $saved_tracker;

		$isbridged = false;
		if (isset($config['bridges']['bridged']) && is_array($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $oc2) {
				if (stristr($oc2['members'], $on)) {
					$isbridged = true;
					break;
				}
			}
		}

		if ($oc['ip'] && !($isbridged) && isset($oc['spoofcheck'])) {
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc, $log);
		}

		/* block private networks ? */
		if (!isset($config['syslog']['nologprivatenets'])) {
			$privnetlog = "log";
		} else {
			$privnetlog = "";
		}

		$saved_tracker += 10;
		$tracker = $saved_tracker;

		if (isset($config['interfaces'][$on]['blockpriv'])) {
			if ($isbridged == false) {
				$ipfrules .= <<<EOD
# block anything from private networks on interfaces with the option set
block in $privnetlog quick on \${$oc['descr']} from 10.0.0.0/8 to any tracker {$rfc1918_increment()} label "{$fix_rule_label("Block private networks from {$oc['descr']} block 10/8")}"
block in $privnetlog quick on \${$oc['descr']} from 127.0.0.0/8 to any tracker {$rfc1918_increment()} label "{$fix_rule_label("Block private networks from {$oc['descr']} block 127/8")}"
block in $privnetlog quick on \${$oc['descr']} from 172.16.0.0/12 to any tracker {$rfc1918_increment()} label "{$fix_rule_label("Block private networks from {$oc['descr']} block 172.16/12")}"
block in $privnetlog quick on \${$oc['descr']} from 192.168.0.0/16 to any tracker {$rfc1918_increment()} label "{$fix_rule_label("Block private networks from {$oc['descr']} block 192.168/16")}"
block in $privnetlog quick on \${$oc['descr']} from fc00::/7 to any tracker {$rfc1918_increment()} label "{$fix_rule_label("Block ULA networks from {$oc['descr']} block fc00::/7")}"

EOD;
			}
		}

		$saved_tracker += 10;
		$tracker = $saved_tracker;

		switch ($oc['type']) {
			case "pptp":
				$ipfrules .= <<<EOD
# allow PPTP client
pass in {$log['pass']} on \${$oc['descr']} proto gre from any to any keep state tracker {$increment_tracker()} label "{$fix_rule_label("allow PPTP client on {$oc['descr']}")}"

EOD;
				break;
			case "pppoe":
			case "none":
			/* XXX: Nothing to do in this case?! */
				break;
			default:
			/* allow access to DHCP server on interfaces */
				if (isset($config['dhcpd'][$on]['enable'])) {
					$ipfrules .= <<<EOD
# allow access to DHCP server on {$oc['descr']}
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 68 to 255.255.255.255 port = 67 tracker {$increment_tracker()} label "allow access to DHCP server"

EOD;
					if (is_ipaddrv4($oc['ip'])) {
						$ipfrules .= <<<EOD
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 68 to {$oc['ip']} port = 67 tracker {$increment_tracker()} label "allow access to DHCP server"
pass out {$log['pass']} quick on \${$oc['descr']} proto udp from {$oc['ip']} port = 67 to any port = 68 tracker {$increment_tracker()} label "allow access to DHCP server"

EOD;
					}

					if (is_ipaddrv4($oc['ip']) && $config['dhcpd'][$on]['failover_peerip'] <> "") {
						$ipfrules .= <<<EOD
# allow access to DHCP failover on {$oc['descr']} from {$config['dhcpd'][$on]['failover_peerip']}
pass in {$log['pass']} quick on \${$oc['descr']} proto { tcp udp } from {$config['dhcpd'][$on]['failover_peerip']} to {$oc['ip']} port = 519 tracker {$increment_tracker()} label "allow access to DHCP failover"
pass in {$log['pass']} quick on \${$oc['descr']} proto { tcp udp } from {$config['dhcpd'][$on]['failover_peerip']} to {$oc['ip']} port = 520 tracker {$increment_tracker()} label "allow access to DHCP failover"

EOD;
					}

				}
				/* allow access to DHCP relay on interfaces */
				if (isset($config['dhcrelay']['enable'])) {
					$dhcifaces = explode(",", $config['dhcrelay']['interface']);
					foreach ($dhcifaces as $dhcrelayif) {
						if ($dhcrelayif == $on) {
							$ipfrules .= <<<EOD
# allow access to DHCP relay on {$oc['descr']}
pass in {$log['pass']} quick on \${$oc['descr']} proto udp from any port = 68 to 255.255.255.255 port = 67 tracker {$increment_tracker()} label "allow access to DHCP relay"

EOD;
						}
					}
				}
				break;
		}

		$saved_tracker += 10;
		$tracker = $saved_tracker;
		switch ($oc['type6']) {
			case "6rd":
			    if (is_ipaddrv4($config['interfaces'][$on]['gateway-6rd'])) {
				$ipfrules .= <<<EOD
# allow our proto 41 traffic from the 6RD border relay in
pass in {$log['pass']} on \${$oc['descr']} proto 41 from {$config['interfaces'][$on]['gateway-6rd']} to any tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic in for 6rd on {$oc['descr']}")}"
pass out {$log['pass']} on \${$oc['descr']} proto 41 from any to {$config['interfaces'][$on]['gateway-6rd']} tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic out for 6rd on {$oc['descr']}")}"

EOD;
			    }

				/* XXX: Really need to allow 6rd traffic coming in for v6 this is against default behaviour! */
				if (0 && is_ipaddrv6($oc['ipv6'])) {
					$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$oc['descr']} inet6 from any to {$oc['ipv6']}/{$oc['snv6']} tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6rd traffic in for 6rd on {$oc['descr']}")}"
pass out {$log['pass']} on \${$oc['descr']} inet6 from {$oc['ipv6']}/{$oc['snv6']} to any tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6rd traffic out for 6rd on {$oc['descr']}")}"

EOD;
				}
				break;
			case "6to4":
				if (is_ipaddrv4($oc['ip'])) {
					$ipfrules .= <<<EOD
# allow our proto 41 traffic from the 6to4 border relay in
pass in {$log['pass']} on \${$oc['descr']} proto 41 from any to {$oc['ip']} tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic in for 6to4 on {$oc['descr']}")}"
pass out {$log['pass']} on \${$oc['descr']} proto 41 from {$oc['ip']} to any tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic out for 6to4 on {$oc['descr']}")}"

EOD;
				}
				/* XXX: Really need to allow 6to4 traffic coming in for v6 this is against default behaviour! */
				if (0 && is_ipaddrv6($oc['ipv6'])) {
					$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$oc['descr']} inet6 from any to {$oc['ipv6']}/{$oc['snv6']} tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic in for 6to4 on {$oc['descr']}")}"
pass out {$log['pass']} on \${$oc['descr']} inet6 from {$oc['ipv6']}/{$oc['snv6']} to any tracker {$increment_tracker()} label "{$fix_rule_label("Allow 6in4 traffic out for 6to4 on {$oc['descr']}")}"

EOD;
				}
				break;
			default:
				if ((is_array($config['dhcpdv6'][$on]) && isset($config['dhcpdv6'][$on]['enable'])) ||
				    (isset($oc['track6-interface'])) ||
				    (is_array($config['dhcrelay6']) && !empty($config['dhcrelay6']['interface']) && in_array($on, explode(',', $config['dhcrelay6']['interface'])))) {
					$ipfrules .= <<<EOD
# allow access to DHCPv6 server on {$oc['descr']}
# We need inet6 icmp for stateless autoconfig and dhcpv6
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from fe80::/10 to fe80::/10 port = 546 tracker {$increment_tracker()} label "allow access to DHCPv6 server"
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from fe80::/10 to ff02::/16 port = 546 tracker {$increment_tracker()} label "allow access to DHCPv6 server"
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from fe80::/10 to ff02::/16 port = 547 tracker {$increment_tracker()} label "allow access to DHCPv6 server"
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from ff02::/16 to fe80::/10 port = 547 tracker {$increment_tracker()} label "allow access to DHCPv6 server"

EOD;
					if (is_ipaddrv6($oc['ipv6'])) {
						$ipfrules .= <<<EOD
pass in {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from fe80::/10 to {$oc['ipv6']} port = 546 tracker {$increment_tracker()} label "allow access to DHCPv6 server"
pass out {$log['pass']} quick on \${$oc['descr']} inet6 proto udp from {$oc['ipv6']} port = 547 to fe80::/10 tracker {$increment_tracker()} label "allow access to DHCPv6 server"

EOD;
					}
				}
				break;
		}
	}

	$saved_tracker += 10;
	$tracker = $saved_tracker;

	/*
	 * NB: The loopback rules are needed here since the antispoof would take precedence then.
	 *	If you ever add the 'quick' keyword to the antispoof rules above move the loopback
	 *	rules before them.
	 */
	$ipfrules .= <<<EOD

# loopback
pass in {$log['pass']} on \$loopback inet all tracker {$increment_tracker()} label "pass IPv4 loopback"
pass out {$log['pass']} on \$loopback inet all tracker {$increment_tracker()} label "pass IPv4 loopback"

EOD;
	if (isset($config['system']['ipv6allow'])) {
		$ipfrules .= <<<EOD
pass in {$log['pass']} on \$loopback inet6 all tracker {$increment_tracker()} label "pass IPv6 loopback"
pass out {$log['pass']} on \$loopback inet6 all tracker {$increment_tracker()} label "pass IPv6 loopback"

EOD;
	}
	$ipfrules .= <<<EOD
# let out anything from the firewall host itself and decrypted IPsec traffic
pass out {$log['pass']} inet all keep state allow-opts tracker {$increment_tracker()} label "let out anything IPv4 from firewall host itself"

EOD;
	if (isset($config['system']['ipv6allow'])) {
		$ipfrules .= <<<EOD
pass out {$log['pass']} inet6 all keep state allow-opts tracker {$increment_tracker()} label "let out anything IPv6 from firewall host itself"

EOD;
	}
	$ipfrules .= "\n";

	$saved_tracker += 100;
	$tracker = $saved_tracker;
	foreach ($FilterIflist as $ifdescr => $ifcfg) {
		if (isset($ifcfg['virtual'])) {
			continue;
		}

		$gw = get_interface_gateway($ifdescr);
		$routeto = (substr($ifcfg['if'], 0, 5) != "ipsec") ? "route-to ( {$ifcfg['if']} {$gw} )" : "";
		if (is_ipaddrv4($gw) && is_ipaddrv4($ifcfg['ip']) && is_subnetv4("{$ifcfg['sa']}/{$ifcfg['sn']}")) {
			$ipfrules .= "pass out {$log['pass']} {$routeto} from {$ifcfg['ip']} to !{$ifcfg['sa']}/{$ifcfg['sn']} tracker {$increment_tracker()} keep state allow-opts label \"let out anything from firewall host itself\"\n";
			if (is_array($ifcfg['vips'])) {
				foreach ($ifcfg['vips'] as $vip) {
					if ($vip['mode'] == "proxyarp") {
						continue;
					}
					if (!is_ipaddrv4($vip['ip']) || !is_subnetv4("{$vip['ip']}/{$vip['sn']}")) {
						continue;
					}
					if (ip_in_subnet($vip['ip'], "{$ifcfg['sa']}/{$ifcfg['sn']}")) {
						$ipfrules .= "pass out {$log['pass']} {$routeto} from {$vip['ip']} to !{$ifcfg['sa']}/{$ifcfg['sn']} tracker {$increment_tracker()} keep state allow-opts label \"let out anything from firewall host itself\"\n";
					} else {
						$ipfrules .= "pass out {$log['pass']} {$routeto} from {$vip['ip']} to !" . gen_subnet($vip['ip'], $vip['sn']) . "/{$vip['sn']} tracker {$increment_tracker()} keep state allow-opts label \"let out anything from firewall host itself\"\n";
					}
				}
			}
		}

		$gwv6 = get_interface_gateway_v6($ifdescr);
		$stf = get_real_interface($ifdescr, "inet6");
		$pdlen = 64 - (int) calculate_ipv6_delegation_length($ifdescr);
		$routeto = (substr($ifcfg['if'], 0, 5) != "ipsec") ? "route-to ( {$stf} {$gwv6} )" : "";
		if (is_ipaddrv6($gwv6) && is_ipaddrv6($ifcfg['ipv6']) && is_subnetv6("{$ifcfg['ipv6']}/{$pdlen}")) {
			$ipfrules .= "pass out {$log['pass']} {$routeto} inet6 from {$ifcfg['ipv6']} to !{$ifcfg['ipv6']}/{$pdlen} tracker {$increment_tracker()} keep state allow-opts label \"let out anything from firewall host itself\"\n";
			if (is_array($ifcfg['vips6'])) {
				foreach ($ifcfg['vips6'] as $vip) {
					if (!is_ipaddrv6($vip['ip']) || !is_subnetv6("{$vip['ip']}/{$pdlen}")) {
						continue;
					}
					$ipfrules .= "pass out {$log['pass']} {$routeto} inet6 from {$vip['ip']} to !{$vip['ip']}/{$pdlen} tracker {$increment_tracker()} keep state allow-opts label \"let out anything from firewall host itself\"\n";
				}
			}
		}
	}


	$saved_tracker += 300;
	$tracker = $saved_tracker;
	/* add ipsec interfaces */
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		$ipfrules .= "pass out {$log['pass']} on \$IPsec all tracker {$increment_tracker()} tracker {$increment_tracker()} keep state label \"IPsec internal host to host\"\n";
	}

	$saved_tracker += 10;
	$tracker = $saved_tracker;
	if (is_array($config['system']['webgui']) && !isset($config['system']['webgui']['noantilockout'])) {
		$alports = filter_get_antilockout_ports();

		if (count($config['interfaces']) > 1 && !empty($FilterIflist['lan']['if'])) {
			/* if antilockout is enabled, LAN exists and has
			 * an IP and subnet mask assigned
			 */
			$lanif = $FilterIflist['lan']['if'];
			$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
pass in {$log['pass']} quick on {$lanif} proto tcp from any to ({$lanif}) port { {$alports} } tracker {$antilockout_increment()} keep state label "anti-lockout rule"

EOD;
		} else if (count($config['interfaces']) == 1) {
			/* single-interface deployment, add to WAN	*/
			$wanif = $FilterIflist["wan"]['if'];
			$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
pass in {$log['pass']} quick on {$wanif} proto tcp from any to ({$wanif}) port { {$alports} } tracker {$antilockout_increment()} keep state label "anti-lockout rule"

EOD;
		}
		unset($alports);
	}

	$saved_tracker += 10;
	$tracker = $saved_tracker;
	if (isset($config['nat']['rule']) && is_array($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $rule) {
			if ((!isset($config['system']['disablenatreflection']) || $rule['natreflection'] == "enable") &&
			    ($rule['natreflection'] != "disable")) {
				$ipfrules .= "# NAT Reflection rules\n";
				$ipfrules .= <<<EOD
pass in {$log['pass']} inet tagged PFREFLECT tracker {$increment_tracker()} keep state label "NAT REFLECT: Allow traffic to localhost"

EOD;
				break;
			}
		}
	}

	if (isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		$rule_arr1 = array();
		$rule_arr2 = array();
		$rule_arr3 = array();
		$vpn_and_ppp_ifs = array("l2tp", "pptp", "pppoe", "enc0", "openvpn", "wireguard");
		/*
		 * NB: The order must be: Floating rules, then interface group and then regular ones.
		 */
		foreach ($config['filter']['rule'] as $rule) {
			update_filter_reload_status("Pre-caching {$rule['descr']}...");
			if (isset ($rule['disabled'])) {
				continue;
			}

			// Webgui separator bar. Not a real rule
			if (isset($rule['separator'])) {
				continue;
			}

			if (!empty($rule['ipprotocol']) && $rule['ipprotocol'] == "inet46") {
				if (isset($rule['floating'])) {
					$rule['ipprotocol'] = "inet";
					$rule_arr1[] = filter_generate_user_rule_arr($rule);
					$rule['ipprotocol'] = "inet6";
					$rule_arr1[] = filter_generate_user_rule_arr($rule);
				} else if (is_interface_group($rule['interface']) || in_array($rule['interface'], $vpn_and_ppp_ifs)) {
					$rule['ipprotocol'] = "inet";
					$rule_arr2[] = filter_generate_user_rule_arr($rule);
					$rule['ipprotocol'] = "inet6";
					$rule_arr2[] = filter_generate_user_rule_arr($rule);
				} else {
					$rule['ipprotocol'] = "inet";
					$rule_arr3[] = filter_generate_user_rule_arr($rule);
					$rule['ipprotocol'] = "inet6";
					$rule_arr3[] = filter_generate_user_rule_arr($rule);
				}
				$rule['ipprotocol'] = "inet46";
			} else {
				if (isset($rule['floating'])) {
					$rule_arr1[] = filter_generate_user_rule_arr($rule);
				} else if (is_interface_group($rule['interface']) || in_array($rule['interface'], $vpn_and_ppp_ifs)) {
					$rule_arr2[] = filter_generate_user_rule_arr($rule);
				} else {
					$rule_arr3[] = filter_generate_user_rule_arr($rule);
				}
			}
			if ($rule['sched']) {
				$time_based_rules = true;
			}
		}

		$ipfrules .= "\n# User-defined rules follow\n";
		$ipfrules .= "\nanchor \"userrules/*\"\n";
		/* Generate user rule lines */
		foreach ($rule_arr1 as $rule) {
			if (isset($rule['disabled'])) {
				continue;
			}
			if (!$rule['rule']) {
				continue;
			}
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
		foreach ($rule_arr2 as $rule) {
			if (isset($rule['disabled'])) {
				continue;
			}
			if (!$rule['rule']) {
				continue;
			}
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
		foreach ($rule_arr3 as $rule) {
			if (isset($rule['disabled'])) {
				continue;
			}
			if (!$rule['rule']) {
				continue;
			}
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
		unset($rule_arr1, $rule_arr2, $rule_arr3);
	}

	$saved_tracker += 100;
	$tracker = $saved_tracker;

	/*  pass traffic between statically routed subnets and the subnet on the
	 *  interface in question to avoid problems with complicated routing
	 *  topologies
	 */
	if (isset($config['filter']['bypassstaticroutes']) && is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		$ipfrules .= "# Add rules to bypass firewall rules for static routes\n";
		foreach (get_staticroutes(false, false, true) as $route) { // Parameter 3 returnenabledroutesonly
			$friendly = $GatewaysList[$route['gateway']]['friendlyiface'];
			if (is_array($FilterIflist[$friendly])) {
				$oc = $FilterIflist[$friendly];
				$routeent = explode("/", $route['network']);
				unset($sa);
				if (is_ipaddrv4($oc['ip'])) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
				}
				if ($sa && is_ipaddrv4($routeent[0])) {
					$ipfrules .= <<<EOD
pass {$log['pass']} quick on \${$oc['descr']} proto tcp from {$sa}/{$sn} to {$route['network']} flags any tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} proto tcp from {$route['network']} to {$sa}/{$sn} flags any tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"

EOD;
				}
				unset($sa);
				if (is_ipaddrv6($oc['ipv6'])) {
					$sa = $oc['sav6'];
					$sn = $oc['snv6'];
				}
				if ($sa && is_ipaddrv6($routeent[0])) {
					$ipfrules .= <<<EOD
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto tcp from {$sa}/{$sn} to {$route['network']} flags any tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} inet6 from {$sa}/{$sn} to {$route['network']} tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} inet6 proto tcp from {$route['network']} to {$sa}/{$sn} flags any tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"
pass {$log['pass']} quick on \${$oc['descr']} inet6 from {$route['network']} to {$sa}/{$sn} tracker {$increment_tracker()} keep state(sloppy) label "pass traffic between statically routed subnets"

EOD;
				}
			}
		}
	}

	update_filter_reload_status(gettext("Creating IPsec rules..."));
	$saved_tracker += 100000;
	$tracker = $saved_tracker;
	$ipfrules .= filter_generate_ipsec_rules($log);

	$ipfrules .= "\nanchor \"tftp-proxy/*\"\n";

	$saved_tracker += 200;
	$tracker = $saved_tracker;
	update_filter_reload_status("Creating uPNP rules...");
	if (is_array($config['installedpackages']['miniupnpd']) && is_array($config['installedpackages']['miniupnpd']['config'][0])) {
		if (isset($config['installedpackages']['miniupnpd']['config'][0]['enable'])) {
			$ipfrules .= "anchor \"miniupnpd\"\n";
		}

		if (is_array($config['installedpackages']['miniupnpd'][0]['config'])) {
			$upnp_interfaces = explode(",", $config['installedpackages']['miniupnpd'][0]['config']['iface_array']);
			foreach ($upnp_interfaces as $upnp_if) {
				if (is_array($FilterIflist[$upnp_if])) {
					$oc = $FilterIflist[$upnp_if];
					unset($sa);
					if ($oc['ip']) {
						$sa = $oc['sa'];
						$sn = $oc['sn'];
					}
					if ($sa) {
						$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$oc['descr']} proto tcp from {$sa}/{$sn} to 239.255.255.250/32 port 1900 tracker {$increment_tracker()} keep state label "pass multicast traffic to miniupnpd"

EOD;
					}
				}
			}
		}
	}

	return $ipfrules;
}

function filter_rules_spoofcheck_generate($ifname, $ifcfg, $log) {
	global $g, $config, $tracker;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_spoofcheck_generate() being called $mt\n";
	}
	$ipfrules = "antispoof {$log['block']} for \${$ifcfg['descr']} tracker {$tracker}\n";
	$tracker++;

	return $ipfrules;
}

/****f* filter/filter_tdr_install_cron
 * NAME
 *   filter_tdr_install_cron
 * INPUTS
 *   $should_install true if the cron entry should be installed, false
 *   if the entry should be removed if it is present
 * RESULT
 *   none
 ******/
function filter_tdr_install_cron($should_install) {
	global $config, $g;

	if (platform_booting() == true) {
		return;
	}

	if (!is_array($config['cron'])) {
		$config['cron'] = array();
	}
	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}

	$x = 0;
	$is_installed = false;
	foreach ($config['cron']['item'] as $item) {
		if (strstr($item['command'], "filter_configure_sync")) {
			$is_installed = true;
			break;
		}
		$x++;
	}

	switch ($should_install) {
		case true:
			if (!$is_installed) {
				$cron_item = array();
				$cron_item['minute'] = "0,15,30,45";
				$cron_item['hour'] = "*";
				$cron_item['mday'] = "*";
				$cron_item['month'] = "*";
				$cron_item['wday'] = "*";
				$cron_item['who'] = "root";
				$cron_item['command'] = "/etc/rc.filter_configure_sync";
				$config['cron']['item'][] = $cron_item;
				write_config(gettext("Installed 15 minute filter reload for Time Based Rules"));
				configure_cron();
			}
			break;
		case false:
			if ($is_installed) {
				unset($config['cron']['item'][$x]);
				write_config(gettext("Removed 15 minute filter reload for Time Based Rules"));
				configure_cron();
			}
			break;
	}
}

/****f* filter/filter_get_time_based_rule_status
 * NAME
 *   filter_get_time_based_rule_status
 * INPUTS
 *   xml schedule block
 * RESULT
 *   true/false - true if the rule should be installed
 ******/
/*
 <schedules>
   <schedule>
     <name>ScheduleMultipleTime</name>
     <descr>main descr</descr>
     <time>
       <position>0,1,2</position>
       <hour>0:0-24:0</hour>
       <desc>time range 2</desc>
     </time>
     <time>
       <position>4,5,6</position>
       <hour>0:0-24:0</hour>
       <desc>time range 1</desc>
     </time>
   </schedule>
 </schedules>
*/
function filter_get_time_based_rule_status($schedule) {

	/* no schedule? rule should be installed */
	if (empty($schedule)) {
		return true;
	}
	/*
	 * iterate through time blocks and determine
	 * if the rule should be installed or not.
	 */
	foreach ($schedule['timerange'] as $timeday) {
		if (empty($timeday['month'])) {
			$monthstatus = true;
		} else {
			$monthstatus = filter_tdr_month($timeday['month']);
		}
		if (empty($timeday['day'])) {
			$daystatus = true;
		} else {
			$daystatus = filter_tdr_day($timeday['day']);
		}
		if (empty($timeday['hour'])) {
			$hourstatus = true;
		} else {
			$hourstatus = filter_tdr_hour($timeday['hour']);
		}
		if (empty($timeday['position'])) {
			$positionstatus = true;
		} else {
			$positionstatus = filter_tdr_position($timeday['position']);
		}

		if ($monthstatus == true && $daystatus == true && $positionstatus == true && $hourstatus == true) {
			return true;
		}
	}

	return false;
}

function filter_tdr_day($schedule) {
	global $g;

	if ($g['debug']) {
		log_error("[TDR DEBUG] filter_tdr_day($schedule)");
	}

	/*
	 * Calculate day of month.
	 * IE: 29th of may
	 */
	$date = date("d");
	$defined_days = explode(",", $schedule);
	foreach ($defined_days as $dd) {
		if ($date == $dd) {
			return true;
		}
	}
	return false;
}
function filter_tdr_hour($schedule) {
	global $g;

	/* $schedule should be a string such as 16:00-19:00 */
	$tmp = explode("-", $schedule);
	$starting_time = strtotime($tmp[0]);
	$ending_time = strtotime($tmp[1]);
	$now = strtotime("now");
	if ($g['debug']) {
		log_error("[TDR DEBUG] S: $starting_time E: $ending_time N: $now");
	}
	if ($now >= $starting_time and $now < $ending_time) {
		return true;
	}
	return false;
}

function filter_tdr_position($schedule) {
	global $g;

	/*
	 * Calculate position, ie: day of week.
	 * Sunday = 7, Monday = 1, Tuesday = 2
	 * Weds = 3, Thursday = 4, Friday = 5,
	 * Saturday = 6
	 * ...
	 */
	$weekday = date("w");
	if ($g['debug']) {
		log_error("[TDR DEBUG] filter_tdr_position($schedule) $weekday");
	}
	if ($weekday == 0) {
		$weekday = 7;
	}
	$schedule_days = explode(",", $schedule);
	foreach ($schedule_days as $day) {
		if ($day == $weekday) {
			return true;
		}
	}
	return false;
}

function filter_tdr_month($schedule) {
	global $g;

	/*
	 * Calculate month
	 */
	$todays_month = date("n");
	$months = explode(",", $schedule);
	if ($g['debug']) {
		log_error("[TDR DEBUG] filter_tdr_month($schedule)");
	}
	foreach ($months as $month) {
		if ($month == $todays_month) {
			return true;
		}
	}
	return false;
}

function filter_setup_logging_interfaces() {
	global $config, $FilterIflist;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_setup_logging_interfaces() being called $mt\n";
	}
	$rules = "";
	if (isset($FilterIflist['lan'])) {
		$rules .= "set loginterface {$FilterIflist['lan']['if']}\n";
	} else if (isset($FilterIflist['wan'])) {
		$rules .= "set loginterface {$FilterIflist['wan']['if']}\n";
	}

	return $rules;
}

function filter_process_carp_rules($log) {
	global $g, $config, $tracker;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_process_carp_rules() being called $mt\n";
	}

	$increment_tracker = 'filter_rule_tracker';
	$lines = "";
	/* return if there are no carp configured items */
	if (!empty($config['hasync']) or !empty($config['virtualip']['vip'])) {
		$lines .= "\n# CARP rules\n";
		$lines .= "block in {$log['block']} quick proto carp from (self) to any tracker {$increment_tracker()}\n";
		$lines .= "pass {$log['pass']} quick proto carp tracker {$increment_tracker()} no state\n";
	}
	return $lines;
}

/* Generate IPsec Filter Items */
function filter_generate_ipsec_rules($log = array()) {
	global $config, $g, $FilterIflist, $tracker;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_ipsec_rules() being called $mt\n";
	}

	if (isset($config['system']['disablevpnrules'])) {
		return "\n# VPN Rules not added disabled in System->Advanced.\n";
	}

	$increment_tracker = 'filter_rule_tracker';

	$ipfrules = "\n# VPN Rules\n";
	if (!function_exists('ipsec_enabled')) {
		require_once("ipsec.inc");
	}
	if (ipsec_enabled()) {
		/* step through all phase1 entries */
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			$tracker += 10;

			if (isset ($ph1ent['disabled']) || isset ($ph1ent['gw_duplicates'])) {
				continue;
			}
			/* determine local and remote peer addresses */
			if (!isset($ph1ent['mobile'])) {
				$rgip = ipsec_get_phase1_dst($ph1ent);
				if (!$rgip) {
					$ipfrules .= "# ERROR! Unable to determine remote IPsec peer address for {$ph1ent['remote-gateway']}\n";
					continue;
				}
			} else {
				$rgip = " any ";
			}
			/* Determine best description */
			if ($ph1ent['descr']) {
				$descr = $ph1ent['descr'];
			} else {
				$descr = $rgip;
			}
			/*
			 * Step through all phase2 entries and determine
			 * which protocols are in use with this peer
			 */
			$prot_used_esp = false;
			$prot_used_ah  = false;
			if (is_array($config['ipsec']['phase2'])) {
				foreach ($config['ipsec']['phase2'] as $ph2ent) {
					/* only evaluate ph2's bound to our ph1 */
					if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
						continue;
					}
					if ($ph2ent['protocol'] == 'esp') {
						$prot_used_esp = true;
					}
					if ($ph2ent['protocol'] == 'ah') {
						$prot_used_ah = true;
					}
				}
			}

			$a_groups = return_gateway_groups_array(true);
			if (is_array($a_groups[$ph1ent['interface']])) {
				// bound to gateway group
				$parentinterface = get_failover_interface($ph1ent['interface']);
				if (substr($parentinterface, 0, 4) == "_vip") {
					$parentinterface = get_configured_vip_interface($parentinterface);
					/* IP Alias -> CARP */
					if (substr($parentinterface, 0, 4) == "_vip") {
						$parentinterface = get_configured_vip_interface($parentinterface);
					}
				} else {
					$parentinterface = convert_real_interface_to_friendly_interface_name($parentinterface);
				}
			} elseif (substr($ph1ent['interface'], 0, 4) == "_vip") {
				$parentinterface = get_configured_vip_interface($ph1ent['interface']);
				/* IP Alias -> CARP */
				if (substr($parentinterface, 0, 4) == "_vip") {
					$parentinterface = get_configured_vip_interface($parentinterface);
				}
			} else {
				$parentinterface = $ph1ent['interface'];
			}
			if (empty($FilterIflist[$parentinterface]['descr'])) {
				$ipfrules .= "# Could not locate interface for IPsec: {$descr}\n";
				continue;
			}

			unset($gateway);
			/* add endpoint routes to correct gateway on interface if the
			remote endpoint is not on this interface's subnet  */
			if ((isset($ph1ent['mobile']) || is_ipaddrv4($rgip)) && (interface_has_gateway($parentinterface))) {
				$parentifsubnet = get_interface_ip($parentinterface) . "/" . get_interface_subnet($parentinterface);
				if (isset($ph1ent['mobile']) || !ip_in_subnet($rgip, $parentifsubnet)) {
					$gateway = get_interface_gateway($parentinterface);
					$interface = $FilterIflist[$parentinterface]['if'];

					$route_to = " route-to ( $interface $gateway ) ";
					$reply_to = " reply-to ( $interface $gateway ) ";
				}
			} else if ((isset($ph1ent['mobile']) || is_ipaddrv6($rgip)) && (interface_has_gatewayv6($parentinterface))) {
				$parentifsubnet = get_interface_ipv6($parentinterface) . "/" . get_interface_subnetv6($parentinterface);
				if (isset($ph1ent['mobile']) || !ip_in_subnet($rgip, $parentifsubnet)) {
					$gateway = get_interface_gateway_v6($parentinterface);
					$interface = $FilterIflist[$parentinterface]['if'];

					$route_to = " route-to ( $interface $gateway ) ";
					$reply_to = " reply-to ( $interface $gateway ) ";
				}
			}

			/* Just in case */
			if ((!is_ipaddr($gateway) || empty($interface))) {
				$route_to = " ";
				$reply_to = " ";
			}

			/* Add rules to allow IKE to pass */
			$shorttunneldescr = substr($descr, 0, 35);
			// don't add "pass out" rules where $rgip is any as it will over-match and often break VPN clients behind the system in multi-WAN scenarios. redmine #5819
			if ($rgip != " any ") {
				$ike_out = isset($ph1ent['ikeport']) ? $ph1ent['ikeport'] : 500;
				$ipfrules .= "pass out {$log['pass']} $route_to proto udp from (self) to {$rgip} port = {$ike_out} tracker {$increment_tracker()} keep state label \"IPsec: {$shorttunneldescr} - outbound isakmp\"\n";
			}
			$ike_in = isset($config['ipsec']['port']) ? $config['ipsec']['port'] : 500;
			$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to (self) port = {$ike_in} tracker {$increment_tracker()} keep state label "IPsec: {$shorttunneldescr} - inbound isakmp"

EOD;
			if ($rgip != " any ") {
				$natt_out = isset($ph1ent['nattport']) ? $ph1ent['nattport'] : 4500;
				$ipfrules .= "pass out {$log['pass']} $route_to proto udp from (self) to {$rgip} port = {$natt_out} tracker {$increment_tracker()} keep state label \"IPsec: {$shorttunneldescr} - outbound nat-t\"\n";
			}
			$natt_in = isset($config['ipsec']['port_nat_t']) ? $config['ipsec']['port_nat_t'] : 4500;
			$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to (self) port = {$natt_in} tracker {$increment_tracker()} keep state label "IPsec: {$shorttunneldescr} - inbound nat-t"

EOD;
			/* Add rules to allow the protocols in use */
			if ($prot_used_esp) {
				if ($rgip != " any ") {
					$ipfrules .= "pass out {$log['pass']} $route_to proto esp from (self) to {$rgip} tracker {$increment_tracker()} keep state label \"IPsec: {$shorttunneldescr} - outbound esp proto\"\n";
				}
				$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto esp from {$rgip} to (self) tracker {$increment_tracker()} keep state label "IPsec: {$shorttunneldescr} - inbound esp proto"

EOD;
			}
			if ($prot_used_ah) {
				if ($rgip != " any ") {
					$ipfrules .= "pass out {$log['pass']} $route_to proto ah from (self) to {$rgip} tracker {$increment_tracker()} keep state label \"IPsec: {$shorttunneldescr} - outbound ah proto\"\n";
				}
				$ipfrules .= <<<EOD
pass in {$log['pass']} on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto ah from {$rgip} to (self) tracker {$increment_tracker()} keep state label "IPsec: {$shorttunneldescr} - inbound ah proto"

EOD;
			}
		}
	}
	return($ipfrules);
}

function discover_pkg_rules($ruletype) {
	global $config, $g, $aliases;

	/*
	 * Bail if there is no pkg directory, if there are no installed
	 * packages or if the package files might be out of sync.
	 */
	if (!is_array($config['installedpackages']['package']) ||
	    !is_dir("/usr/local/pkg") ||
	    is_subsystem_dirty('packagelock')) {
		return "";
	}

	$rules = "";
	foreach ($config['installedpackages']['package'] as $package) {
		if (empty($package['filter_rule_function'])) {
			continue;
		}
		if (!file_exists("/usr/local/pkg/" .
		    $package['configurationfile'])) {
			continue;
		}

		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" .
		    $package['configurationfile'], 'packagegui');
		$pkgname = substr(reverse_strrchr($package['configurationfile'],
		    "."), 0, -1);
		$pkg_generate_rules = $package['filter_rule_function'];

		update_filter_reload_status(sprintf(gettext(
		    'Checking for %1$s PF hooks in package %2$s'), $ruletype,
		    $pkg_config['include_file']));

		if (!empty($pkg_config['include_file']) &&
		    file_exists($pkg_config['include_file'])) {
			require_once($pkg_config['include_file']);
		}

		if (!function_exists($pkg_generate_rules)) {
			continue;
		}

		update_filter_reload_status(sprintf(gettext(
		    'Processing early %1$s rules for package %2$s'), $ruletype,
		    $pkg_config['include_file']));

		$tmprules = $pkg_generate_rules("$ruletype");
		file_put_contents("{$g['tmp_path']}/rules.test.packages",
		    $aliases . $tmprules);
		$status = mwexec(
		    "/sbin/pfctl -nf {$g['tmp_path']}/rules.test.packages");
		if ($status <> 0) {
			$errorrules = sprintf(gettext(
			    "There was an error while parsing the package filter rules for %s."),
			    $pkg_config['include_file']) . "\n";
			log_error($errorrules);
			file_put_contents("{$g['tmp_path']}/rules.packages.{$pkgname}",
			    "#{$errorrules}\n{$tmprules}\n");
			continue;
		}
		$rules .= $tmprules;
	}

	return $rules;
}

function filter_get_antilockout_ports($wantarray = false) {
	global $config;

	$lockoutports = array();
	$guiport = ($config['system']['webgui']['protocol'] == "https") ? "443" : "80";
	$guiport = empty($config['system']['webgui']['port']) ? $guiport : $config['system']['webgui']['port'];
	$lockoutports[] = $guiport;

	if (($config['system']['webgui']['protocol'] == "https") && !isset($config['system']['webgui']['disablehttpredirect']) && ($guiport != "80")) {
		$lockoutports[] = "80";
	}

	if (isset($config['system']['ssh']['enable'])) {
		$lockoutports[] = empty($config['system']['ssh']['port']) ? "22" : $config['system']['ssh']['port'];
	}

	if ($wantarray) {
		return $lockoutports;
	} else {
		return implode(" ", $lockoutports);
	}

}

/* get rule index within interface */
function ifridx($if, $ridx) {
	global $config;

	if ($ridx < 0) {
		return $ridx;
	}

	$i = $ifridx = 0;
	if (is_array($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rulen => $filterent) {
			if (($filterent['interface'] == $if && !isset($filterent['floating'])) || (isset($filterent['floating']) && "FloatingRules" == $if)) {
				if ($i == $ridx) {
					return $ifridx;
				}
				$ifridx++;
			}
			$i++;
		}
	}
	return $ifridx;
}

/* display rules separators */
function display_separator($separators, $nrules, $columns_in_table) {
	if (is_array($separators)) {
		foreach ($separators as $sepn => $separator) {
			if ($separator['row'][0] == "fr" . $nrules) {
				$cellcolor = $separator['color'];
				print('<tr class="ui-sortable-handle separator">' .
					'<td class="' . $cellcolor . '" colspan="' . ($columns_in_table -1) . '">' . '<span class="' . $cellcolor . '">' . htmlspecialchars($separator['text']) . '</span></td>' .
					'<td  class="' . $cellcolor . '"><a href="#"><i class="fa fa-trash no-confirm sepdel" title="' . gettext("delete this separator") . '"></i></a></td>' .
					'</tr>' . "\n");
			}
		}
	}
}

/* Return a list of separator rows */
function separator_rows($separators) {
	$seprowns = array();
	if (is_array($separators)) {
		foreach ($separators as $sepn => $separator) {
			$seprows[substr($separator['row']['0'], 2)] = true;
		}
	}
	return $seprows;
}

/* If the separator is located after the place ($ridx) where the rule was added or deleted, move the separator ($mvrows) */
function move_separators(&$a_separators, $ridx, $mvnrows) {
	if (is_array($a_separators)) {
		foreach ($a_separators as $sepi => $separator) {
			$seprow = substr($separator['row']['0'], 2);
			if ($seprow > $ridx) {
				$a_separators[$sepi]['row']['0'] = 'fr' . ($seprow + $mvnrows);
			}
		}
	}
}

function filter_get_interface_list() {
	global $filter_interface_blacklist;
	$iflist = create_interface_list();
	$filter_ifs = array();
	foreach ($iflist as $ifent => $ifname) {
		$realifname = get_real_interface($ifent);
		foreach ($filter_interface_blacklist as $ifbl) {
			if (substr($realifname, 0, strlen($ifbl)) == $ifbl) {
				continue 2;
			}
		}
		$filter_ifs[$ifent] = $ifname;
	}
	return $filter_ifs;
}

?>
