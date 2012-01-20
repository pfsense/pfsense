<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004-2006 Scott Ullrich
	Copyright (C) 2005		Bill Marquette
	Copyright (C) 2006		Peter Allgeyer
	Copyright (C) 2008-2010		Ermal Luci
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

	pfSense_BUILDER_BINARIES:	/sbin/sysctl	/sbin/kldload	/usr/sbin/tcpdump	/sbin/pfctl	/bin/rm
	pfSense_BUILDER_BINARIES:	/usr/sbin/inetd
	pfSense_MODULE:	filter
*/

/* DISABLE_PHP_LINT_CHECKING */
// vim: ts=4 sw=4 noexpandtab

/* include all configuration functions */

/* holds the items that will be executed *AFTER* the filter is fully loaded */
$after_filter_configure_run = array();

/* For installing cron job of schedules */
$time_based_rules = false;

/* Used to hold the interface list that will be used on ruleset creation. */
$FilterIflist = array();

/* Create a global array to avoid errors on rulesets. */
$GatewaysList = array();

/* Used for the hostname dns resolver */
$filterdns = "";

/* Used for aliases and interface macros */
$aliases = "";

function flowtable_configure() {
	global $config, $g;

	if (empty($config['system']['flowtable'])) {
		mwexec("/sbin/sysctl net.inet.flowtable.enable=0", true);
		return;
	}

	// Figure out how many flows we should reserve
	// sized 2x larger than the number of unique connection destinations.
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates']))
		$maxstates = $config['system']['maximumstates'];
	else
		$maxstates = 150000;
	// nmbflows cpu count * ($maxstates * 2)
	$cpus = trim(`/sbin/sysctl -n kern.smp.cpus`, " \n");
	$nmbflows = ($cpus*($maxstates*2));
	// Flowtable currently only works on 8.0
	if(get_freebsd_version() == "8") {
		mwexec("/sbin/sysctl net.inet.flowtable.nmbflows={$nmbflows}");
		mwexec("/sbin/sysctl net.inet.ip.output_flowtable_size={$maxstates}");
		mwexec("/sbin/sysctl net.inet.flowtable.enable=1");
	}
}

function filter_load_ipfw() {
	global $config;

	if(!is_module_loaded("ipfw.ko")) {
		mute_kernel_msgs();
		mwexec("/sbin/kldload ipfw");
		unmute_kernel_msgs();
		/*
		 * make sure ipfw is the first hook to make CP and schedules work
		 * correctly on Multi-WAN.
		 */
		mwexec("/sbin/sysctl net.inet.ip.pfil.inbound=\"pf\"");
		/*
		 * TODO: Check if disabling ipfw hook
		 * does not break accounting on CP.
		 * XXX Not sure if we really do outbound filtering with ipfw!
		 */
		mwexec("/sbin/sysctl net.inet.ip.pfil.outbound=\"pf\"");
	}

	/* Set ipfw state limit */
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* Set ipfw states to user defined maximum states in Advanced menu. */
		mwexec("sysctl net.inet.ip.fw.dyn_max={$config['system']['maximumstates']}");
	} else {
		/* Set to default 10,000 */
		mwexec("sysctl net.inet.ip.fw.dyn_max=10000");
	}
}

function filter_pflog_start() {
	global $config, $g;
	if ($g['platform'] == 'jail')
		return;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}
	mute_kernel_msgs();
	$output = 0;
	exec("/bin/pgrep -af 'tcpdump -s 256 -v -l -n -e -ttt -i pflog0'", $output, $retval);
	if($retval != 0)
		mwexec_bg("/usr/sbin/tcpdump -s 256 -v -l -n -e -ttt -i pflog0 | logger -t pf -p local0.info");
	unmute_kernel_msgs();
}

/* reload filter async */
function filter_configure() {
	global $g;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure() being called $mt\n";
	}

	/*
	 * NOTE: Check here for bootup status since this should not be triggered during bootup.
	 *	 The reason is that rc.bootup calls filter_configure_sync directly which does this too.
	 */
	if (!$g['booting'])
		send_event("filter reload");
}

function filter_delete_states_for_down_gateways() {
	global $config, $GatewaysList;

	if (isset($config['system']['kill_states']))
		return;

	$a_gateways = return_gateways_status();
	if (is_array($GatewaysList)) {
                foreach ($GatewaysList as $gwname => $gateway) {
			if (empty($gateway['monitor']))
				continue;
			if (!is_ipaddr($gateway['monitor']))
				continue;
			if (strstr($gateway['monitor'], "127.0.0."))
				continue;
			if (empty($a_gateways[$gateway['monitor']]))
				continue;
			$gwstatus =& $a_gateways[$gateway['monitor']];
			if (strstr($gwstatus['status'], "down")) {
                        	$gwip = $gateway['gateway'];
                        	if (!is_ipaddr($gwip))
                                	$gwip = get_interface_gateway($gateway['friendlyiface']);
                        	if (is_ipaddr($gwstatus['srcip'])) {
					$cmd = "/sbin/pfctl -b {$gwstatus['srcip']}/32 ";
					if (is_ipaddr($gwip))
						$cmd .= "-b {$gwip}/32";
					else
						$cmd .= "-b 0.0.0.0/32";
					mwexec($cmd);
				}
			}
		}
	}
}

/* reload filter sync */
function filter_configure_sync($delete_states_if_needed = true) {
	global $config, $g, $after_filter_configure_run, $FilterIflist;
	global $time_based_rules, $filterdns, $aliases;

	/* Use filter lock to not allow concurrent filter reloads during this run. */
	$filterlck = lock('filter', LOCK_EX);


	filter_pflog_start();
	update_filter_reload_status(gettext("Initializing"));

	/* invalidate interface cache */
	get_interface_arr(true);

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}
	/* Get interface list to work with. */
	filter_generate_optcfg_array();
	if($g['booting'] == true)
		echo gettext("Configuring firewall");

	/* generate aliases */
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status(gettext("Creating aliases"));
	$aliases = filter_generate_aliases();
	$gateways = filter_generate_gateways();
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status(gettext("Generating NAT rules"));
	/* generate nat rules */
	$natrules = filter_nat_rules_generate();
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status(gettext("Generating filter rules"));
	/* generate pfctl rules */
	$pfrules = filter_rules_generate();
	/* generate altq, limiter */
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status(gettext("Generating ALTQ queues"));
	$altq_queues = filter_generate_altq_queues();
	update_filter_reload_status(gettext("Generating Limiter rules"));
	$dummynet_rules = filter_generate_dummynet_rules();
	update_filter_reload_status(gettext("Generating Layer7 rules"));
	generate_layer7_files();
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status(gettext("Loading filter rules"));
	/* enable pf if we need to, otherwise disable */
	if(!isset ($config['system']['disablefilter'])) {
		mwexec("/sbin/pfctl -e", true);
	} else {
		mwexec("/sbin/pfctl -d", true);
		unlink_if_exists("{$g['tmp_path']}/filter_loading");
		update_filter_reload_status(gettext("Filter is disabled.  Not loading rules."));
		if($g['booting'] == true)
			echo gettext("done.") . "\n";
		unlock($filterlck);
		return;
	}

	// Copy rules.debug to rules.debug.old
	if(file_exists("{$g['tmp_path']}/rules.debug"))
		copy("{$g['tmp_path']}/rules.debug", "{$g['tmp_path']}/rules.debug.old");

	$rules = "";
	$rules .= "{$aliases} \n";
	$rules .= "{$gateways} \n";
	update_filter_reload_status(gettext("Setting up logging information"));
	$rules .= filter_setup_logging_interfaces();
	if($config['system']['optimization'] <> "") {
		$rules .= "set optimization {$config['system']['optimization']}\n";
		if($config['system']['optimization'] == "conservative") {
			$rules .= "set timeout { udp.first 300, udp.single 150, udp.multiple 900 }\n";
		}
	} else {
		$rules .= "set optimization normal\n";
	}
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* User defined maximum states in Advanced menu. */
		$rules .= "set limit states {$config['system']['maximumstates']}\n";
		$rules .= "set limit src-nodes {$config['system']['maximumstates']}\n";
	} else {
		$max_states = pfsense_default_state_size();
		$rules .= "set limit states {$max_states}\n";
		$rules .= "set limit src-nodes {$max_states}\n";
	}

	if($config['system']['maximumtableentries'] <> "" && is_numeric($config['system']['maximumtableentries'])) {
		/* User defined maximum table entries in Advanced menu. */
		$rules .= "set limit table-entries {$config['system']['maximumtableentries']}\n";
	}
	if (isset($config['system']['lb_use_sticky']) && is_numeric($config['system']['srctrack']) && ($config['system']['srctrack'] > 0))
		$rules .= "set timeout src.track {$config['system']['srctrack']}\n";

	// Configure flowtable support if enabled.
	flowtable_configure();

	$rules .= "\n";
	$rules .= "set skip on pfsync0\n";
	$rules .= "\n";
	update_filter_reload_status(gettext("Setting up SCRUB information"));
	$rules .= filter_generate_scrubing();
	$rules .= "\n";
	/* NOTE: Disabled until we catch up with dummynet changes. */
	//$rules .= "{$dummynet_rules}\n";
	$rules .= "{$altq_queues}\n";
	$rules .= "{$natrules}\n";
	$rules .= "{$pfrules}\n";

	$rules .= discover_pkg_rules("filter");

	if(!file_put_contents("{$g['tmp_path']}/rules.debug", $rules, LOCK_EX)) {
		log_error("WARNING: Could not write new rules!");
		unlock($filterlck);
		return;
	}

	/*
	 * XXX: This are not being used for now so just comment them out.
	$rules = "1"; // force to be diff from oldrules
	$oldrules = "2"; // force to be diff from rules

	if(file_exists("{$g['tmp_path']}/rules.debug"))
		$rules = file_get_contents("{$g['tmp_path']}/rules.debug");
	if(file_exists("{$g['tmp_path']}/rules.debug.old"))
		$oldrules = file_get_contents("{$g['tmp_path']}/rules.debug.old");
	 *
	 */
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "pfctl being called at $mt\n";
	}
	$rules_loading = mwexec("/sbin/pfctl -o basic -f {$g['tmp_path']}/rules.debug");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "pfctl done at $mt\n";
	}
	/* check for a error while loading the rules file.	if an error has occured
	 * then output the contents of the error to the caller
	 */
	if($rules_loading <> 0) {
		$rules_error = exec_command("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
		$line_error = explode(":", $rules_error);
		$line_number = $line_error[1];
		$line_split = file("{$g['tmp_path']}/rules.debug");
		if(is_array($line_split))
			$line_error = sprintf(gettext('The line in question reads [%1$d]: %2$s'), $line_number, $line_split[$line_number-1]);
		if($line_error and $line_number) {
			file_notice("filter_load", sprintf(gettext('There were error(s) loading the rules: %1$s - %2$s'), $rules_error, $line_error), "Filter Reload", "");
			log_error("There were error(s) loading the rules: {$rules_error} - {$line_error}");
			update_filter_reload_status(sprintf(gettext('There were error(s) loading the rules: %1$s - %2$s'), $rules_error, $line_error));
			unlock($filterlck);
			return;
		}
		/* Brutal ugly hack but required -- PF is stuck, unwedge */
		if(strstr("$rules_error", "busy")) {
			exec("/sbin/pfctl -d");
			exec("/sbin/pfctl -e");
			exec("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
			$error_msg = gettext("PF was wedged/busy and has been reset.");
			file_notice("pf_busy", $error_msg, "pf_busy", "");
			log_error($error_msg);
		}
	}

	update_filter_reload_status(gettext("Starting up layer7 daemon"));
	layer7_start_l7daemon();

	if(!empty($filterdns)) {
		$filterdnsfd = fopen("{$g['varetc_path']}/filterdns.conf", "w");
		if($filterdnsfd) {
			fwrite($filterdnsfd, $filterdns);
			fclose($filterdnsfd);
		}
		killbypid("{$g['tmp_path']}/filterdns.pid");
		/*
		 * FilterDNS has three debugging levels. The default choosen is 1.
		 * Availabe are level 2 and greater then 2.
		 */
		mwexec("/usr/local/sbin/filterdns -p {$g['tmp_path']}/filterdns.pid -i 300 -c {$g['varetc_path']}/filterdns.conf -d 1");
	}

	/* run items scheduled for after filter configure run */
	$fda = fopen("{$g['tmp_path']}/commands.txt", "w");
	if($fda) {
		if($after_filter_configure_run)
			foreach($after_filter_configure_run as $afcr)
				fwrite($fda, $afcr . "\n");
		/*
		 *      we need a way to let a user run a shell cmd after each
		 *      filter_configure() call.  run this xml command after
		 *      each change.
		 */
		if($config['system']['afterfilterchangeshellcmd'] <> "")
			fwrite($fda, $config['system']['afterfilterchangeshellcmd'] . "\n");

		fclose($fda);
	}

	if(file_exists("{$g['tmp_path']}/commands.txt")) {
		mwexec("sh {$g['tmp_path']}/commands.txt &");
		unlink("{$g['tmp_path']}/commands.txt");
	}
	/* if time based rules are enabled then swap in the set */
	if($time_based_rules == true)
		filter_tdr_install_cron(true);
	else
		filter_tdr_install_cron(false);

	if($g['booting'] == true)
		echo ".";

	if($delete_states_if_needed) {
		update_filter_reload_status(gettext("Processing down interface states"));
		filter_delete_states_for_down_gateways();
	}

	update_filter_reload_status(gettext("Running plugins"));

	if(is_dir("/usr/local/pkg/pf/")) {
		/* process packager manager custom rules */
		update_filter_reload_status(gettext("Running plugins (pf)"));
		run_plugins("/usr/local/pkg/pf/");
		update_filter_reload_status(gettext("Plugins completed."));
	}

	update_filter_reload_status(gettext("Done"));
	if($g['booting'] == true)
		echo gettext("done.") . "\n";

	unlock($filterlck);
	return 0;
}

function filter_generate_scrubing() {
	global $config, $FilterIflist;
	$scrubrules = "";

	if (isset($config['system']['maxmss_enable'])) {
		$maxmss = 1400;
		if (!empty($config['system']['maxmss']))
			$maxmss = $config['system']['maxmss'];

		$scrubrules .= "scrub in from any to <vpn_networks> max-mss {$maxmss}\n";
	}
	/* disable scrub option */
	foreach ($FilterIflist as $scrubif => $scrubcfg) {
		if(isset($scrubcfg['virtual']) || empty($scrubcfg['descr']))
			continue;
		/* set up MSS clamping */
		if($scrubcfg['mss'] <> "" && is_numeric($scrubcfg['mss']) && $scrubcfg['if'] != "pppoe" && $scrubcfg['if'] != "pptp" &&
			$scrubif['if'] != "l2tp")
			$mssclamp = "max-mss " . (intval($scrubcfg['mss'] - 40));
		else
			$mssclamp = "";
		/* configure no-df for linux nfs and others */
		if($config['system']['scrubnodf'])
			$scrubnodf = "no-df";
		else
			$scrubnodf = "";
		if($config['system']['scrubrnid'])
			$scrubrnid = "random-id";
		else
			$scrubrnid = "";
		if(!isset($config['system']['disablescrub']))
			$scrubrules .= "scrub in on \${$scrubcfg['descr']} all {$scrubnodf} {$scrubrnid} {$mssclamp} fragment reassemble\n"; // reassemble all directions
		else if(!empty($mssclamp))
			$scrubrules .= "scrub in on \${$scrubcfg['descr']} {$mssclamp}\n";
	}
	return $scrubrules;
}

function filter_generate_nested_alias($name, $alias, &$aliasnesting, &$aliasaddrnesting) {
	global $aliastable, $filterdns;

	$addresses = explode(" ", $alias);
	$finallist = "";
	$builtlist = "";
	$urltable_nesting = "";
	$aliasnesting[$name] = $name;
	foreach ($addresses as $address) {
		if (empty($address))
			continue;
		$linelength = strlen($builtlist);
		$tmpline = "";
		if(is_alias($address)) {
			if (alias_get_type($address) == 'urltable') {
				// Feature#1603. For this type of alias we do not need to recursively call filter_generate_nested_alias. Just load IPs from the file.
				$urltable_netsting = alias_expand_urltable($address);
				if (!empty($urltable_nesting)) {
					$urlfile_as_arr = file($urltable_nesting);
					foreach($urlfile_as_arr as $line) {
						$address= rtrim($line);
						if ((strlen($tmpline) + $linelength) > 4036) {
							$finallist .= "{$tmpline} \\\n";
							$tmpline = "";
						}
						$tmpline .= " {$address}";
					}
				}
			}
			/* We already expanded this alias so there is no neccessity to do it again. */
			else if(!isset($aliasnesting[$address]))
				$tmpline = filter_generate_nested_alias($name, $aliastable[$address], $aliasnesting, $aliasaddrnesting);
		} else if(!isset($aliasaddrnesting[$address])) {
			if (!is_ipaddr($address) && !is_subnet($address) && !is_port($address)) {
				$filterdns .= "pf {$address} {$name}\n";
				/* Guarantee unique dns names on filterdns config */
				$aliasaddrnesting[$address] = $address;
				continue;
			}
			$aliasaddrnesting[$address] = $address;
			$tmpline = " {$address}";
		}
		if ((strlen($tmpline)+ $linelength) > 4036) {
			$finallist .= "{$builtlist} \\\n";
			$builtlist = "";
		}
		$builtlist .= " {$tmpline}";
	}
	$finallist .= $builtlist;
	return $finallist;
}

function filter_expand_alias($alias_name)
{
	global $config;

	if(isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			if($aliased['name'] == $alias_name) {
				$aliasnesting = array();
				$aliasaddrnesting = array();
				return filter_generate_nested_alias($aliased['name'], $aliased['address'], $aliasnesting, $aliasaddrnesting);
			}
		}
	}
}

function filter_generate_aliases() {
	global $config, $FilterIflist, $after_filter_configure_run;

	if(isset($config['system']['developerspew'])) {
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
			$aliases .= "{$ifcfg['descr']} = \"{ {$ifcfg['if']}";
			$aliases .= " }\"\n";
		}
	}

	$aliases .= "\n#SSH Lockout Table\n";
	$aliases .= "table <sshlockout> persist\n";
	$aliases .= "table <webConfiguratorlockout> persist\n";

	$aliases .= "#Snort tables\n";
	$aliases .= "table <snort2c>\n";

	$aliases .= "\ntable <virusprot>\n";

	$aliases .= "\n# User Aliases \n";
	/* Setup pf groups */
	if(isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			$extralias = "";
			/* 
			 * XXX: i am not sure what this does so i am commenting it out for now, because as it is
			 * its quite dangerous!
			 * $ip = find_interface_ip($aliased['address']);
			 * $extraalias = " " . link_ip_to_carp_interface($ip);
			 */
			$aliasnesting = array();
			$aliasaddrnesting = array();
			$addrlist = filter_generate_nested_alias($aliased['name'], $aliased['address'], $aliasnesting, $aliasaddrnesting);
			switch ($aliased['type']) {
			case "host":
			case "network":
			case "url":
				$tableaddrs = "{$addrlist}{$extralias}";
				if(empty($tableaddrs)) {
					$aliases .= "table <{$aliased['name']}> persist\n";
					if (empty($aliased['address']))
						$after_filter_configure_run[] = "/sbin/pfctl -T flush -t " . escapeshellarg($aliased['name']);
				} else
					$aliases .= "table <{$aliased['name']}> { {$addrlist}{$extralias} } \n";

				$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				break;
			case "openvpn":
				$openvpncfg = array();
				if($config['openvpn']['user']) {
					/* XXX: Check if we have a correct ip? */
					foreach ($config['openvpn']['user'] as $openvpn)
						$openvpncfg[$openvpn['name']] = $openvpn['ip'];
				}
				$vpn_lines = explode("\n", $addrlist);
				foreach ($vpn_lines as $vpn_line) {
					$vpn_address_split = explode(" ", $vpn_line);
					foreach($vpn_address_split as $vpnsplit) {
						if(isset($openvpncfg[$vpnsplit])) {
							$newaddress .= " ";
							$newaddress .= $openvpn[$vpnsplit];
							break;
						}
					}
				}
				$aliases .= "table <{$aliased['name']}> { {$newaddress}{$extralias} } \n";
				$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				break;
			case "urltable":
				$urlfn = alias_expand_urltable($aliased['name']);
				if ($urlfn) {
					$aliases .= "table <{$aliased['name']}> persist file \"{$urlfn}\"\n";
					$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				}
				break;
			case "port":
				$aliases .= "{$aliased['name']} = \"{ {$addrlist} }\"\n";
				break;
			default:
				$aliases .= "{$aliased['name']} = \"{ {$aliased['address']}{$extralias} }\"\n";
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
        $GatewayGroupsList = return_gateway_groups_array();

	if (is_array($GatewaysList)) {
		foreach ($GatewaysList as $gwname => $gateway) {
			$int = $gateway['interface'];
			$gwip = $gateway['gateway'];
			$route = "";
			if (!is_ipaddr($gwip))
				$gwip = get_interface_gateway($gateway['friendlyiface']);
			if (is_ipaddr($gwip) && !empty($int))
				$route = "route-to ( {$int} {$gwip} )";
			$rules .= "GW{$gwname} = \" {$route} \"\n";
		}
	}

	if (is_array($GatewayGroupsList)) {
		foreach ($GatewayGroupsList as $gateway => $members) {
			$route = "";
			if (count($members) > 0) {
				$foundlb = 0;
				$routeto = "";
				foreach($members as $idx => $member) {
					$int = $member['int'];
					$gatewayip = $member['gwip'];
					if (($int <> "") && is_ipaddr($gatewayip)) {
						if ($g['debug'])
							log_error(sprintf(gettext('Setting up route with %1$s on %2$s'), $gatewayip, $int));
						if ($member['weight'] > 1) {
							$routeto .= str_repeat("( {$int} {$gatewayip} ) ", $member['weight']);
						} else
							$routeto .= "( {$int} {$gatewayip} ) ";
						$foundlb++;
					} else
						log_error(sprintf(gettext("An error occurred while trying to find the interface got %s .  The rule has not been added."), $gatewayip));
				}
				$route = "";
				if ($foundlb > 0) {
					$route = " route-to { {$routeto} } ";
					if($foundlb > 1) {
						$route .= " round-robin ";
						if (isset($config['system']['lb_use_sticky']))
							$route .= " sticky-address ";
					}
				}
			}
			$rules .= "GW{$gateway} = \" {$route} \"\n";
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
	/* build list of vpns */
	$vpns = "";
	$vpns_arr = array();
	/* ipsec */
	if(isset($config['ipsec']['enable'])){
		if(is_array($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase2'] as $ph2ent) {
				if((!$ph2ent['mobile']) && ($ph2ent['mode'] != 'transport')) {
					require_once("ipsec.inc");
					$vpns_subnet = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
					if ($vpns_subnet == "0.0.0.0/0")
						continue;
					$vpns_arr[] = $vpns_subnet;
				}
			}
		}
	}
	/* openvpn */
	foreach (array('client', 'server') as $type) {
		if(is_array($config['openvpn']["openvpn-$type"])) {
			foreach ($config['openvpn']["openvpn-$type"] as & $settings) {
				if(is_array($settings)) {
					if(is_subnet($settings['remote_network']) && $settings['remote_network'] <> "0.0.0.0/0") {
						$vpns_arr[] = $settings['remote_network'];
					}
					if(is_subnet($settings['tunnel_network']) && $settings['tunnel_network'] <> "0.0.0.0/0") {
						$vpns_arr[] = $settings['tunnel_network'];
					}
				}
			}
		}
	}
	/* pppoe */
	if (is_array($config['pppoes']['pppoe'])) {
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				if(is_ipaddr($pppoe['remoteip'])) {
					$pppoesub = gen_subnet($pppoe['remoteip'], $pppoe['pppoe_subnet']);
					if (is_subnet($pppoesub))
						$vpns_arr[] = $pppoesub;
				}
			}
		}
	}
	if(!empty($vpns_arr)) {
		$vpns = implode(" ", $vpns_arr);
	}
	return $vpns;
}

/* returns space separated list of directly connected networks */
function filter_get_direct_networks_list() {
	global $config, $FilterIflist;
	/* build list of directly connected interfaces and networks */
	$networks = "";
	$networks_arr = array();
	if(empty($FilterIflist)) {
		filter_generate_optcfg_array();
	}
	foreach ($FilterIflist as $ifent => $ifcfg) {
		$subnet = "{$ifcfg['sa']}/{$ifcfg['sn']}";
		if(is_subnet($subnet)) {
			$networks_arr[] = $subnet;
		}
	}
	if(is_array($config['staticroutes']['route'])) {
		foreach($config['staticroutes']['route'] as $netent) {
			if(is_ipaddr($netent['network'])) {
				$networks_arr[] = $netent['network'];
			}
		}
	}
	if(!empty($networks_arr)) {
		$networks = implode(" ", $networks_arr);
	}
	return $networks;
}

function filter_generate_optcfg_array() {
	global $config, $FilterIflist;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_optcfg_array() being called $mt\n";
	}

	read_layer7_config();
	/* if list */
	$iflist = get_configured_interface_with_descr();
	foreach ($iflist as $if => $ifdetail) {
		$oc = $config['interfaces'][$if];
		$oic = array();
		$oic['if'] = get_real_interface($if);
		if (!does_interface_exist($oic['if']))
			continue;
		$oic['ip'] = get_interface_ip($if);
		$oic['ipv6'] = get_interface_ipv6($if);
		if(!is_ipaddrv4($oc['ipaddr']) && !empty($oc['ipaddr']))
			$oic['type'] = $oc['ipaddr'];
		if(!is_ipaddrv6($oc['ipaddrv6']) && !empty($oc['ipaddrv6']))
			$oic['type6'] = $oc['ipaddrv6'];
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
		$FilterIflist[$if] = $oic;
	}

	if($config['pptpd']['mode'] == "server" || $config['pptpd']['mode'] == "redir") {
		$oic = array();
		$oic['if'] = 'pptp';
		$oic['descr'] = 'pptp';
		$oic['ip'] = $config['pptpd']['localip'];
		$oic['sa'] = $config['pptpd']['remoteip'];
		$oic['mode'] = $config['pptpd']['mode'];
		$oic['virtual'] = true;
		if($config['pptpd']['pptp_subnet'] <> "")
			$oic['sn'] = $config['pptpd']['pptp_subnet'];
		else
			$oic['sn'] = "32";
		$FilterIflist['pptp'] = $oic;
	}
	if($config['l2tp']['mode'] == "server") {
		$oic = array();
		$oic['if'] = 'l2tp';
		$oic['descr'] = 'L2TP';
		$oic['ip'] = $config['l2tp']['localip'];
		$oic['sa'] = $config['l2tp']['remoteip'];
		if($config['l2tp']['l2tp_subnet'] <> "")
			$oic['sn'] = $config['l2tp']['l2tp_subnet'];
		else
			$oic['sn'] = "32";
		$oic['mode'] = $config['l2tp']['mode'];
		$oic['virtual'] = true;
		$FilterIflist['l2tp'] = $oic;
	}
	if (is_array($config['pppoes']['pppoe']) && (count($config['pppoes']['pppoe']) > 0)) {
		$pppoeifs = array();
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				$oic = array();
				$oic['if'] = 'pppoe';
				$oic['descr'] = 'pppoe';
				$oic['ip'] = $pppoe['localip'];
				$oic['sa'] = $pppoe['remoteip'];
				$oic['mode'] = $pppoe['mode'];
				$oic['virtual'] = true;
				if($pppoe['pppoe_subnet'] <> "")
					$oic['sn'] = $pppoe['pppoe_subnet'];
				else
					$oic['sn'] = "32";
				$pppoeifs[] = $oic;
			}
		}
		if (count($pppoeifs))
			$FilterIflist['pppoe'] = $pppoeifs;
	}
	/* add ipsec interfaces */
	if(isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
		$oic = array();
		$oic['if'] = 'enc0';
		$oic['descr'] = 'IPsec';
		$oic['type'] = "none";
		$oic['virtual'] = true;
		$FilterIflist['enc0'] = $oic;
	}
	/* add openvpn interfaces */
	if($config['openvpn']['openvpn-server'] || $config['openvpn']['openvpn-client']) {
		$oic = array();
		$oic['if'] = "openvpn";
		$oic['descr'] = 'OpenVPN';
		$oic['type'] = "none";
		$oic['virtual'] = true;
		$FilterIflist['openvpn'] = $oic;
	}
	/* add interface groups */
	if(is_array($config['ifgroups']['ifgroupentry'])) {
		foreach($config['ifgroups']['ifgroupentry'] as $ifgen) {
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
	if(isset($config['system']['developerspew'])) {
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
		if($ifname['if'] == $natif)
			continue;

		/* Do not add reflection redirects for interfaces with gateways */
		if(interface_has_gateway($ifent))
			continue;

		$nat_if_list[] = $ifname['if'];
	}

	return $nat_if_list;
}

function filter_generate_reflection_nat($rule, &$route_table, $nat_ifs, $protocol, $target, $target_ip, $target_subnet = "") {
	global $config;

	if(!isset($config['system']['enablenatreflectionhelper']))
		return "";

	// Initialize natrules holder string
	$natrules = "";

	update_filter_reload_status(sprintf(gettext("Creating reflection NAT rule for %s..."), $rule['descr']));

	/* TODO: Add this option to port forwards page. */
	if(isset($rule['staticnatport'])) {
		$static_port = " static-port";
	} else {
		$static_port = " port 1024:65535";
	}

	if(!empty($protocol)) {
		$protocol_text = " proto {$protocol}";
	} else {
		$protocol_text = "";
	}

	if(empty($target_subnet) || !is_numeric($target_subnet))
		$target_subnet = 32;

	if(!is_array($route_table)) {
		$route_table = array();
		/* create a route table we can search */
		exec("netstat -rnWf inet", $route_table);
	}

	/* Search for matching subnets in the routing table */
	foreach($route_table as $line) {
		if(preg_match("/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\/[0-9]+[ ]+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+|link[#])/", $line)) {
			$fields = preg_split("/[ ]+/", $line);
			$subnet = $fields[0];
			$subnet_split = explode("/", $subnet);
			$subnet_if = $fields[6];
			if(in_array($subnet_if, $nat_ifs) && check_subnets_overlap($target_ip, $target_subnet, $subnet_split[0], $subnet_split[1])) {
				$natrules .= "no nat on {$subnet_if}{$protocol_text} from {$subnet_if} to {$target}\n";
				$natrules .= "nat on {$subnet_if}{$protocol_text} from {$subnet} to {$target} -> {$subnet_if}{$static_port}\n";
			}
		}
	}

	return $natrules;
}

function filter_generate_reflection($rule, $nordr, $rdr_ifs, $srcaddr, $dstaddr_port, &$starting_localhost_port, &$reflection_txt) {
	global $FilterIflist, $config;

	// Initialize natrules holder string
	$natrules = "";
	$reflection_txt = array();

	if(!empty($rdr_ifs)) {
		if($config['system']['reflectiontimeout'])
			$reflectiontimeout = $config['system']['reflectiontimeout'];
		else
			$reflectiontimeout = "2000";

		update_filter_reload_status(sprintf(gettext("Creating reflection rule for %s..."), $rule['descr']));

		$rdr_if_list = implode(" ", $rdr_ifs);
		if(count($rdr_ifs) > 1)
			$rdr_if_list = "{ {$rdr_if_list} }";

		$natrules .= "\n# Reflection redirects\n";

		$localport = $rule['local-port'];
		if(!empty($localport) && is_alias($localport)) {
			$localport = filter_expand_alias($localport);
			$localport = explode(" ", trim($localport));
			// The translation port for rdr, when specified, does not support more than one port or range.
			// Emulating for behavior consistent with the original port forward.
			$localport = $localport[0];
		}

		if(is_alias($rule['destination']['port'])) {
			if(empty($localport) || $rule['destination']['port'] == $rule['local-port']) {
				$dstport = filter_expand_alias($rule['destination']['port']);
				$dstport = array_filter(explode(" ", trim($dstport)));
				$localport = "";
			} else if(!empty($localport)) {
				$dstport = array($localport);
			}
		} else {
			$dstport = array(str_replace("-", ":", $rule['destination']['port']));
			$dstport_split = explode(":", $dstport[0]);

			if(!empty($localport) && $dstport_split[0] != $rule['local-port']) {
				if(!is_alias($rule['local-port']) && $dstport_split[1] && $dstport_split[0] != $dstport_split[1]) {
					$localendport = $localport + ($dstport_split[1] - $dstport_split[0]);
					$localport .= ":$localendport";
				}

				$dstport = array($localport);
			} else
				$localport = "";
		}

		$dstaddr = explode(" ", $dstaddr_port);
		if($dstaddr[2])
			$rflctintrange = $dstaddr[2];
		else
			return "";
		$dstaddr = $dstaddr[0];
		if(empty($dstaddr) || trim($dstaddr) == "0.0.0.0" || strtolower(trim($dstaddr)) == "port")
			return "";

		if(isset($rule['destination']['any'])) {
			if(!$rule['interface'])
				$natif = "wan";
			else
				$natif = $rule['interface'];

			if(!isset($FilterIflist[$natif]))
				return "";
			if(is_ipaddr($FilterIflist[$natif]['ip']))
				$dstaddr = $FilterIflist[$natif]['ip'];
			else
				return "";

			if(!empty($FilterIflist[$natif]['sn']))
				$dstaddr = gen_subnet($dstaddr, $FilterIflist[$natif]['sn']) . '/' . $FilterIflist[$natif]['sn'];
		}

		switch($rule['protocol']) {
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

		if(!empty($nordr)) {
			$natrules .= "no rdr on {$rdr_if_list} proto {$protocol} from {$srcaddr} to {$dstaddr} port {$rflctintrange}\n";
			return $natrules;
		}

		if (is_alias($rule['target']))
			$target = filter_expand_alias($rule['target']);
		else if(is_ipaddr($rule['target']))
			$target = $rule['target'];
		else if (is_ipaddr($FilterIflist[$rule['target']]['ip']))
			$target = $FilterIflist[$rule['target']]['ip'];
		else
			return "";
		$starting_localhost_port_tmp = $starting_localhost_port;
		$toomanyports = false;
		/* only install reflection rules for < 19991 items */
		foreach($dstport as $loc_pt) {
			if($starting_localhost_port < 19991) {
				$toadd_array = array();
				$inetdport = $starting_localhost_port;
				$rflctrange = $starting_localhost_port;

				$loc_pt = explode(":", $loc_pt);
				if($loc_pt[1] && $loc_pt[1] > $loc_pt[0])
					$delta = $loc_pt[1] - $loc_pt[0];
				else
					$delta = 0;

				if(($inetdport + $delta + 1) - $starting_localhost_port_tmp > 500) {
					log_error("Not installing nat reflection rules for a port range > 500");
					$inetdport = $starting_localhost_port;
					$toadd_array = array();
					$toomanyports = true;
					break;
				} else if(($inetdport + $delta) > 19990) {
					log_error("Installing partial nat reflection rules. Maximum 1,000 reached.");
					$delta = 19990 - $inetdport;
					$loc_pt[1] = $loc_pt[0] + $delta;
					if($delta == 0)
						unset($loc_pt[1]);
					$toomanyports = true;

					if(!empty($localport)) {
						if(is_alias($rule['destination']['port'])) {
							$rflctintrange = alias_expand($rule['destination']['port']);
						} else {
							if($dstport_split[1])
								$dstport_split[1] = $dstport_split[0] + $inetdport + $delta - $starting_localhost_port;
							$rflctintrange = implode(":", $dstport_split);
						}
					}
				}

				if(empty($localport))
					$rflctintrange = implode(":", $loc_pt);
				if($inetdport + $delta > $starting_localhost_port)
					$rflctrange .= ":" . ($inetdport + $delta);
				$starting_localhost_port = $inetdport + $delta + 1;
				$toadd_array = array_merge($toadd_array, range($loc_pt[0], $loc_pt[0] + $delta));

				if(!empty($toadd_array)) {
					$rtarget = explode(" ", trim($target));
					foreach($toadd_array as $tda) {
						if (empty($tda))
							continue;
						foreach($reflect_protos as $reflect_proto) {
							if($reflect_proto == "udp") {
								$socktype = "dgram";
								$dash_u = "-u ";
							} else {
								$socktype = "stream";
								$dash_u = "";
							}
							foreach ($rtarget as $targip) {
								if (empty($targip))
									continue;
								$reflection_txt[] = "{$inetdport}\t{$socktype}\t{$reflect_proto}\tnowait/0\tnobody\t/usr/bin/nc\tnc {$dash_u}-w {$reflectiontimeout} {$targip} {$tda}\n";
							}
						}
						$inetdport++;
					}
					$natrules .= "rdr on {$rdr_if_list} proto {$protocol} from {$srcaddr} to {$dstaddr} port {$rflctintrange} tag PFREFLECT -> 127.0.0.1 port {$rflctrange}\n";
				}
			}

			if($toomanyports)
				break;
		}

		$reflection_txt = array_unique($reflection_txt);
	}

	return $natrules;
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if($if, $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false, $proto = "", $poolopts = "") {
	global $config, $FilterIflist;
	/* XXX: billm - any idea if this code is needed? */
	if($src == "/32" || $src{0} == "/")
		return "# src incorrectly specified\n";
	if($natip != "") {
		if (is_subnet($natip))
			$tgt = $natip;
		elseif (is_alias($natip))
			$tgt = "\${$natip}";
		else
			$tgt = "{$natip}/32";
	} else {
		$natip = get_interface_ip($if);
		if(is_ipaddr($natip))
			$tgt = "{$natip}/32";
		else
			$tgt = "(" . $FilterIflist[$if]['if'] . ")";
	}
	/* Add the protocol, if defined */
	if (!empty($proto) && $proto != "any") {
		if ($proto == "tcp/udp")
			$protocol = " proto { tcp udp }";
		else
			$protocol = " proto {$proto}";
	} else
		$protocol = "";
	/* Add the hard set source port (useful for ISAKMP) */
	if($natport != "")
		$tgt .= " port {$natport}";
	/* sometimes this gets called with "" instead of a value */
	if($src == "")
		$src = "any";
	/* Match on this source port */
	if($srcport != "") {
		$srcportexpand = alias_expand($srcport);
		if(!$srcportexpand)
			$srcportexpand = $srcport;
		$src .= " port {$srcportexpand}";
	}
	/* sometimes this gets called with "" instead of a value */
	if($dst == "")
		$dst = "any";
	/* Match on this dest port */
	if($dstport != "") {
		$dstportexpand = alias_expand($dstport);
		if(!$dstportexpand)
			$dstportexpand = $dstport;
		$dst .= " port {$dstportexpand}";
	}
	/* outgoing static-port option, hamachi, Grandstream, VOIP, etc */
	$staticnatport_txt = "";
	if($staticnatport)
		$staticnatport_txt = "static-port";
	elseif(!$natport)
		$tgt .= " port 1024:65535"; // set source port range
	/* Allow for negating NAT entries */
	if($nonat) {
		$nat = "no nat";
		$target = "";
		$staticnatport_txt = "";
		$poolopts = "";
	} else {
		$nat = "nat";
		$target = "-> {$tgt}";
	}
	$if_friendly = $FilterIflist[$if]['descr'];
	/* Put all the pieces together */
	if($if_friendly)
		$natrule = "{$nat} on \${$if_friendly} {$protocol} from {$src} to {$dst} {$target} {$poolopts} {$staticnatport_txt}\n";
	else
		$natrule .= "# Could not convert {$if} to friendly name(alias)\n";
	return $natrule;
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
	if(is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $rule) {
			if (isset($rule['disabled']))
				continue;

			$sn = "";
			$sn1 = "";
			$target = alias_expand($rule['external']);
                        if (!$target) {
                                $natrules .= "# Unresolvable alias {$rule['target']}\n";
                                continue;               /* unresolvable alias */
			}

			if (!$rule['interface'])
				$natif = "wan";
			else
				$natif = $rule['interface'];
			if (!isset($FilterIflist[$natif]))
                                continue;

			$srcaddr = filter_generate_address($rule, 'source');
                        $dstaddr = filter_generate_address($rule, 'destination');
			if(!$dstaddr)
                                $dstaddr = $FilterIflist[$natif]['ip'];

			$srcaddr = trim($srcaddr);
			$dstaddr = trim($dstaddr);

			$tmp = explode('/', $srcaddr);
			$srcip = $tmp[0];
			if (!empty($tmp[1]) && is_numeric($tmp[1])) {
				$sn = $tmp[1];
				$sn1 = "/{$sn}";
			}

			$natif = $FilterIflist[$natif]['if'];

			/*
			 * If reflection is enabled, turn on extra redirections
			 * for this rule by adding other interfaces to an rdr rule.
			 */
			if ((isset($config['system']['enablebinatreflection']) || $rule['natreflection'] == "enable")
			   && $rule['natreflection'] != "disable")
				$nat_if_list = filter_get_reflection_interfaces($natif);
			else
				$nat_if_list = array();

			$natrules .= "binat on {$natif} from {$srcaddr} to {$dstaddr} -> {$target}{$sn1}\n";
			if (!empty($nat_if_list)) {
				$binat_if_list = implode(" ", $nat_if_list);
				$binat_if_list = "{ {$binat_if_list} }";
				$reflection_txt .= "rdr on {$binat_if_list} from {$dstaddr} to {$target}{$sn1} -> {$srcaddr} bitmask\n";
			}

			$nat_if_list = array_merge(array($natif), $nat_if_list);
			$reflection_txt .= filter_generate_reflection_nat($rule, $route_table, $nat_if_list, "", $srcaddr, $srcip, $sn);
		}
	}

	/* Add binat rules for Network Prefix translation */
	if(is_array($config['nat']['npt'])) {
		foreach ($config['nat']['npt'] as $rule) {
			if (isset($rule['disabled']))
				continue;

			if (!$rule['interface'])
				$natif = "wan";
			else
				$natif = $rule['interface'];
			if (!isset($FilterIflist[$natif]))
                                continue;

			$srcaddr = filter_generate_address($rule, 'source');
                        $dstaddr = filter_generate_address($rule, 'destination');

			$srcaddr = trim($srcaddr);
			$dstaddr = trim($dstaddr);

			$natif = $FilterIflist[$natif]['descr'];

			$natrules .= "binat on \${$natif} from {$srcaddr} to any -> {$dstaddr}\n";
			$natrules .= "binat on \${$natif} from any to {$dstaddr} -> {$srcaddr}\n";

		}
	}


	$natrules .= "\n# Outbound NAT rules\n";
	/* outbound rules - advanced or standard */
	if(isset($config['nat']['advancedoutbound']['enable'])) {
		/* advanced outbound rules */
		if(is_array($config['nat']['advancedoutbound']['rule'])) {
			foreach ($config['nat']['advancedoutbound']['rule'] as $obent) {
				update_filter_reload_status(sprintf(gettext("Creating advanced outbound rule %s"), $obent['descr']));
				$src = alias_expand($obent['source']['network']);
				if(!$src)
					$src = $obent['source']['network'];
				$dst = alias_expand($obent['destination']['address']);
				if(!$dst)
					$dst = $obent['destination']['address'];
				if(isset($obent['destination']['not']) && !isset($obent['destination']['any']))
					$dst = "!" . $dst;
				if(!$obent['interface'])
					$natif = "wan";
				else
					$natif = $obent['interface'];

				$obtarget = ($obent['target'] == "other-subnet") ? $obent['targetip'] . '/' . $obent['targetip_subnet']: $obent['target'];
				$poolopts = (is_subnet($obtarget) || is_alias($obtarget)) ? $obent['poolopts'] : "";

				if (!isset($FilterIflist[$natif]))
					continue;

				$natrules .= filter_nat_rules_generate_if($natif,
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
	} else {
		/* standard outbound rules (one for each interface) */
		update_filter_reload_status(gettext("Creating outbound NAT rules"));
		$tonathosts = "";
		$numberofnathosts = 0;

		if(is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				$netip = explode("/", $route['network']);
				if (isset($GatewaysList[$route['gateway']])) {
					$gateway =& $GatewaysList[$route['gateway']];
					$gatewayip = $gateway['gateway'];
					$interfacegw = $gateway['interface'];
					if(!interface_has_gateway($gateway['interface']) && is_private_ip($netip[0])) {
						$numberofnathosts++;
						$tonathosts .= "{$route['network']} ";
					}
				}
			}
		}
		/* create outbound nat entries for all local networks */
		foreach($FilterIflist as $ocname => $oc) {
			if(!interface_has_gateway($ocname)) {
				if(is_ipaddr($oc['alias-address'])) {
					$aliastarget = $oc['alias-address'];
					$aliassubnet = $oc['alias-subnet'];
					$numberofnathosts++;
					$tonathosts .= "{$oc['sa']}/{$oc['sn']} ";
				}
				if($oc['sa']) {
					$tonathosts .= "{$oc['sa']}/{$oc['sn']} ";
					$numberofnathosts++;
				}
			}
		}
		/* PPTP subnet */
		if(($config['pptpd']['mode'] == "server" ) && is_private_ip($config['pptpd']['remoteip'])) {
			if (isset($config['pptpd']['n_pptp_units']) && is_numeric($config['pptpd']['n_pptp_units']))
				$pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'], long2ip32(ip2long($config['pptpd']['remoteip'])+($config['pptpd']['n_pptp_units']-1)));
			else
				$pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'], long2ip32(ip2long($config['pptpd']['remoteip'])));
			$numberofnathosts += count($pptp_subnets);
			$tonathosts .= implode(" ", $pptp_subnets) . " ";
		}
		/* PPPoE subnet */
		if (is_array($FilterIflist['pppoe'])) {
			foreach ($FilterIflist['pppoe'] as $pppoe) {
				if(is_private_ip($pppoe['ip'])) {
					$numberofnathosts++;
					$tonathosts .= "{$pppoe['sa']}/{$pppoe['sn']} ";
				}
			}
		}
		/* L2TP subnet */
		if(isset($FilterIflist['l2tp']) && $FilterIflist['l2tp']['mode'] == "server") {
			$l2tp_subnet = $FilterIflist['l2tp']['sn'];
			if(is_private_ip($FilterIflist['l2tp']['sa']) && !empty($l2tp_subnet)) {
				$numberofnathosts++;
				$tonathosts .= "{$FilterIflist['l2tp']['sa']}/{$l2tp_subnet} ";
			}
		}
		/* add openvpn interfaces */
		if(is_array($config['openvpn']['openvpn-server'])) {
			foreach ($config['openvpn']['openvpn-server'] as $ovpnsrv) {
				if (!empty($ovpnsrv['tunnel_network'])) {
					$numberofnathosts++;
					$tonathosts .= "{$ovpnsrv['tunnel_network']} ";
				}
			}
		}
		if(is_array($config['openvpn']['openvpn-client'])) {
			foreach ($config['openvpn']['openvpn-client'] as $ovpnsrv) {
				if (!empty($ovpnsrv['tunnel_network'])) {
					$numberofnathosts++;
					$tonathosts .= "{$ovpnsrv['tunnel_network']} ";
				}
			}
		}
		/* IPsec mode_cfg subnet */
		if (isset($config['ipsec']['client']['enable']) &&
			!empty($config['ipsec']['client']['pool_address']) &&
			!empty($config['ipsec']['client']['pool_netbits'])) {
			$tonathosts .= "{$config['ipsec']['client']['pool_address']}/{$config['ipsec']['client']['pool_netbits']} ";
		}
		$natrules .= "\n# Subnets to NAT \n";
		$tonathosts .= "127.0.0.0/8 ";
		if($numberofnathosts > 4) {
			$natrules .= "table <tonatsubnets> { {$tonathosts} }\n";
			$macroortable = "<tonatsubnets>";
		} else if($numberofnathosts > 0) {
			$natrules .= "tonatsubnets	= \"{ {$tonathosts} }\"\n";
			$macroortable = "\$tonatsubnets";
		}
		if($numberofnathosts > 0):
			foreach ($FilterIflist as $if => $ifcfg) {
				if (substr($ifcfg['if'], 0, 4) == "ovpn")
					continue;
				update_filter_reload_status(sprintf(gettext('Creating outbound rules %1$s - (%2$s)'), $if, $ifcfg['descr']));
				if(interface_has_gateway($if)) {
					$target = $ifcfg['ip'];
					/* create outbound nat entries for all local networks */
					$natrules .= filter_nat_rules_generate_if($if,
						"{$macroortable}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($if,
						"{$macroortable}", null, "", null, $target, null, isset($ifcfg['nonat']));
					$natrules .= "\n";
				}
			}
		endif;
	}

	/* load balancer anchor */
	$natrules .= "\n# Load balancing anchor\n";
	$natrules .= "rdr-anchor \"relayd/*\"\n";

	update_filter_reload_status(gettext("Setting up TFTP helper"));
	$natrules .= "# TFTP proxy\n";
	$natrules .= "rdr-anchor \"tftp-proxy/*\"\n";

	if (!empty($config['system']['tftpinterface'])) {
		$tftpifs = explode(",", $config['system']['tftpinterface']);
		foreach($tftpifs as $tftpif) {
			if ($FilterIflist[$tftpif])
				$natrules .= "rdr pass on {$FilterIflist[$tftpif]['if']} proto udp from any to any port tftp -> 127.0.0.1 port 6969\n";
		}
	}

	$interface_counter = 0;
	$vpns_list = filter_get_vpns_list();
	$direct_networks_list = filter_get_direct_networks_list();
	if($vpns_list)
		$natrules .= "table <vpn_networks> { $vpns_list }\n";
	
	/* add a Negate_networks table */
	$natrules .= "table <negate_networks> {";
	if($direct_networks_list)
		$natrules .= " $direct_networks_list ";
	if($vpns_list)
		$natrules .= " $vpns_list ";
	$natrules .= "}\n";

	/* DIAG: add ipv6 NAT, if requested */
	if(isset($config['diag']['ipv6nat']['enable']) &&
		is_ipaddr($config['diag']['ipv6nat']['ipaddr']) &&
		is_array($FilterIflist['wan'])) {
		/* XXX: FIX ME!	 IPV6 */
		$natrules .= "rdr on \${$FilterIflist['wan']['descr']} proto ipv6 from any to any -> {$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	if(file_exists("/var/etc/inetd.conf"))
		@unlink("/var/etc/inetd.conf");
	// Open inetd.conf write handle
	$inetd_fd = fopen("/var/etc/inetd.conf","w");
	/* add tftp protocol helper */
	fwrite($inetd_fd, "tftp-proxy\tdgram\tudp\twait\t\troot\t/usr/libexec/tftp-proxy\ttftp-proxy -v\n");

	if(isset($config['nat']['rule'])) {
		/* start reflection redirects on port 19000 of localhost */
		$starting_localhost_port = 19000;
		$natrules .= "# NAT Inbound Redirects\n";
		foreach ($config['nat']['rule'] as $rule) {
			update_filter_reload_status(sprintf(gettext("Creating NAT rule %s"), $rule['descr']));

			if(isset($rule['disabled']))
				continue;

			/* if item is an alias, expand */
			$dstport = "";
			$dstport[0] = alias_expand($rule['destination']['port']);
			if(!$dstport[0])
				$dstport = explode("-", $rule['destination']['port']);

			/* if item is an alias, expand */
			$localport = alias_expand($rule['local-port']);
			if(!$localport || $dstport[0] == $localport) {
				$localport = "";
			} else if(is_alias($rule['local-port'])) {
				$localport = filter_expand_alias($rule['local-port']);
				if($localport) {
					$localport = explode(" ", trim($localport));
					$localport = $localport[0];
					$localport = " port {$localport}";
				}
			} else if(is_alias($rule['destination']['port'])) {
				$localport = " port {$localport}";
			} else {
				if(($dstport[1]) && ($dstport[0] != $dstport[1])) {
					$localendport = $localport + ($dstport[1] - $dstport[0]);

					$localport .= ":$localendport";
				}

				$localport = " port {$localport}";
			}

			switch(strtolower($rule['protocol'])) {
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

			$target = alias_expand($rule['target']);
			if(!$target && !isset($rule['nordr'])) {
				$natrules .= "# Unresolvable alias {$rule['target']}\n";
				continue;		/* unresolvable alias */
			}

			if($rule['associated-rule-id'] == "pass")
				$rdrpass = "pass ";
			else
				$rdrpass = "";
			if(!$rule['interface'])
				$natif = "wan";
			else
				$natif = $rule['interface'];

			if (!isset($FilterIflist[$natif]))
				continue;

			$srcaddr = filter_generate_address($rule, 'source', true);
			$dstaddr = filter_generate_address($rule, 'destination', true);

			if(!$dstaddr)
				$dstaddr = $FilterIflist[$natif]['ip'];

			$natif = $FilterIflist[$natif]['if'];

			if (isset($rule['nordr'])) {
				$nordr = "no ";
				$rdrpass = "";
			} else
				$nordr = "";

			if((!isset($config['system']['disablenatreflection']) || $rule['natreflection'] == "enable")
			   && $rule['natreflection'] != "disable") {
				$nat_if_list = filter_get_reflection_interfaces($natif);
			} else {
				$nat_if_list = array();
			}

			if($srcaddr <> "" && $dstaddr <> "" && $natif) {
				$srcaddr = trim($srcaddr);
				$dstaddr = trim($dstaddr);

				$natrules .= "{$nordr}rdr {$rdrpass}on {$natif} proto {$protocol} from {$srcaddr} to {$dstaddr}" . ($nordr == "" ? " -> {$target}{$localport}" : "");

				/* Does this rule redirect back to a internal host? */
				if(isset($rule['destination']['any']) && !interface_has_gateway($rule['interface']) && !isset($rule['nordr'])) {
					$rule_interface_ip = find_interface_ip($natif);
					$rule_interface_subnet = find_interface_subnet($natif);
					$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
					$natrules .= "\n";
					$natrules .= "no nat on {$natif} proto tcp from ({$natif}) to {$rule_subnet}/{$rule_interface_subnet}\n";
					$natrules .= "nat on {$natif} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$dstport[0]} -> ({$natif})\n";
				}
				$natrules .= filter_generate_reflection($rule, $nordr, $nat_if_list, $srcaddr, $dstaddr, $starting_localhost_port, $reflection_rules);
				$natrules .= "\n";

				foreach ($reflection_rules as $txtline)
					fwrite($inetd_fd, $txtline);
			}
		}
	}
	fclose($inetd_fd);		// Close file handle

	if (isset($config['pptpd']['mode']) && ($config['pptpd']['mode'] != "off")) {
		if ($config['pptpd']['mode'] == "redir") {
			$pptpdtarget = $config['pptpd']['redir'];
			$natrules .= "# PPTP\n";
			$natrules .= "rdr on \${$FilterIflist['wan']['descr']} proto gre from any to any -> {$pptpdtarget}\n";
			$natrules .= "rdr on \${$FilterIflist['wan']['descr']} proto tcp from any to any port 1723 -> {$pptpdtarget}\n";
		}
	}

	$natrules .= discover_pkg_rules("nat");

	$natrules .= filter_process_carp_nat_rules();
	$natrules .= "# UPnPd rdr anchor\n";
	$natrules .= "rdr-anchor \"miniupnpd\"\n";

	if(!empty($reflection_txt))
		$natrules .= "\n# Reflection redirects and NAT for 1:1 mappings\n" . $reflection_txt;

	// Check if inetd is running, if not start it.	If so, restart it gracefully.
	$helpers = isvalidproc("inetd");
	if(file_exists("/var/etc/inetd.conf")) {
		if(!$helpers)
			mwexec("/usr/sbin/inetd -wW -R 0 -a 127.0.0.1 /var/etc/inetd.conf");
		else
			sigkillbypid("/var/run/inetd.pid", "HUP");
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
	if($rule['descr'] != "" and $line != "")
		$ret['descr'] = "label \"USER_RULE: " . str_replace('"', '', substr($rule['descr'], 0, 52)) . "\"";
	else
		$ret['descr'] = "label \"USER_RULE\"";

	return $ret;
}

function filter_generate_address(& $rule, $target = "source", $isnat = false) {
	global $FilterIflist, $config;
	$src = "";

	if(isset($rule[$target]['any'])) {
		$src = "any";
	} else if($rule[$target]['network']) {
		if(strstr($rule[$target]['network'], "opt")) {
			$optmatch = "";
			$matches = "";
			if($rule['ipprotocol'] == "inet6") {
				if(preg_match("/opt([0-9]*)$/", $rule[$target]['network'], $optmatch)) {
					$opt_ip = $FilterIflist["opt{$optmatch[1]}"]['ipv6'];
					if(!is_ipaddr($opt_ip))
						return "";
					$src = $opt_ip . "/" .
					$FilterIflist["opt{$optmatch[1]}"]['snv6'];
				/* check for opt$NUMip here */
				} else if(preg_match("/opt([0-9]*)ip/", $rule[$target]['network'], $matches)) {
					$src = $FilterIflist["opt{$matches[1]}"]['ipv6'];
					if(!is_ipaddr($src))
						return "";
				}
				if(isset($rule[$target]['not']))
					$src = " !{$src}";
			} else {
				if(preg_match("/opt([0-9]*)$/", $rule[$target]['network'], $optmatch)) {
					$opt_ip = $FilterIflist["opt{$optmatch[1]}"]['ip'];
					if(!is_ipaddr($opt_ip))
						return "";
					$src = $opt_ip . "/" .
					$FilterIflist["opt{$optmatch[1]}"]['sn'];
				/* check for opt$NUMip here */
				} else if(preg_match("/opt([0-9]*)ip/", $rule[$target]['network'], $matches)) {
					$src = $FilterIflist["opt{$matches[1]}"]['ip'];
					if(!is_ipaddr($src))
						return "";
				}
				if(isset($rule[$target]['not']))
					$src = " !{$src}";
			}
		} else {
			if($rule['ipprotocol'] == "inet6") {
				switch ($rule[$target]['network']) {
					case 'wan':
						$wansa = $FilterIflist['wan']['sav6'];
						$wansn = $FilterIflist['wan']['snv6'];
						$src = "{$wansa}/{$wansn}";
						break;
					case 'wanip':
						$src = $FilterIflist["wan"]['ipv6'];
						break;
					case 'lanip':
						$src = $FilterIflist["lan"]['ipv6'];
						break;
					case 'lan':
						$lansa = $FilterIflist['lan']['sav6'];
						$lansn = $FilterIflist['lan']['snv6'];
						$src = "{$lansa}/{$lansn}";
						break;
					case 'pptp':
						$pptpsav6 = gen_subnetv6($FilterIflist['pptp']['sav6'], $FilterIflist['pptp']['snv6']);
						$pptpsnv6 = $FilterIflist['pptp']['snv6'];
						$src = "{$pptpsav6}/{$pptpsnv6}";
						break;
					case 'pppoe':
						if (is_array($FilterIflist['pppoe'])) {
							$pppoesav6 = gen_subnetv6($FilterIflist['pppoe'][0]['ipv6'], $FilterIflist['pppoe'][0]['snv6']);
							$pppoesnv6 = $FilterIflist['pppoe'][0]['snv6'];
							$src = "{$pppoesav6}/{$pppoesnv6}";
						}
					}
			} else {
				switch ($rule[$target]['network']) {
					case 'wan':
						$wansa = $FilterIflist['wan']['sa'];
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
						$lansn = $FilterIflist['lan']['sn'];
						$src = "{$lansa}/{$lansn}";
						break;
					case 'pptp':
						if (isset($config['pptpd']['n_pptp_units']) && is_numeric($config['pptpd']['n_pptp_units']))
							$pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'], long2ip32(ip2long($config['pptpd']['remoteip'])+($config['pptpd']['n_pptp_units']-1)));
						else
							$pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'], long2ip32(ip2long($config['pptpd']['remoteip'])));
						if (empty($pptp_subnets))
							return "";
						$src = "{ " . implode(" ", $pptp_subnets) . " }";
						break;
					case 'pppoe':
						/* XXX: This needs to be fixed somehow! */
						if (is_array($FilterIflist['pppoe'])) {
							$pppoesa = gen_subnet($FilterIflist['pppoe'][0]['ip'], $FilterIflist['pppoe'][0]['sn']);
							$pppoesn = $FilterIflist['pppoe'][0]['sn'];
							$src = "{$pppoesa}/{$pppoesn}";
						}
						break;
				}
			}
		}
	} else if($rule[$target]['address']) {
		$expsrc = alias_expand($rule[$target]['address']);
		if(isset($rule[$target]['not']))
			$not = "!";
		else
			$not = "";
		$src = " {$not} {$expsrc}";
	}

	$rule['protocol'] = strtolower($rule['protocol']);
	if(in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
		if($rule[$target]['port']) {
			$srcport = explode("-", $rule[$target]['port']);
			$srcporta = alias_expand($srcport[0]);
			if(!$srcporta)
				log_error(sprintf(gettext("filter_generate_address: %s is not a valid source port."), $srcport[0]));
			else if((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
				$src .= " port {$srcporta} ";
			} else if(($srcport[0] == 1) && ($srcport[1] == 65535)) {
			/* no need for a port statement here */
			} else if ($isnat) {
				$src .= " port {$srcport[0]}:{$srcport[1]}";
			} else {
				if(is_port($srcporta) && $srcport[1] == 65535) {
					$src .= " port >= {$srcporta} ";
				} else if($srcport[0] == 1) {
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

function filter_generate_user_rule($rule) {
	global $config, $g, $FilterIflist, $GatewaysList;
	global $layer7_rules_list;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_user_rule() being called $mt\n";
	}
	/* don't include disabled rules */
	if(isset($rule['disabled'])) {
		return "# rule " . $rule['descr'] . " disabled \n";
	}
	update_filter_reload_status("Creating filter rules {$rule['descr']} ...");
	$pptpdcfg = $config['pptpd'];
	$int = "";
	$aline = array();

	/* Check to see if the interface is in our list */
	if(isset($rule['floating'])) {
		if(isset($rule['interface']) && $rule['interface'] <> "") {
			$interfaces = explode(",", $rule['interface']);
			$ifliste = "";
			foreach ($interfaces as $iface) {
				if(array_key_exists($iface, $FilterIflist))
					$ifliste .= " " . $FilterIflist[$iface]['if'] . " ";
			}
			if($ifliste <> "")
				$aline['interface'] = " on { {$ifliste} } ";
			else
				$aline['interface'] = "";
		} else
			$aline['interface'] = "";
	} else if(!array_key_exists($rule['interface'], $FilterIflist)) {
			foreach($FilterIflist as $oc) $item .= $oc['descr'];
				return "# {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
	} else if((array_key_exists($rule['interface'], $FilterIflist))
		&& (is_array($FilterIflist[$rule['interface']]))
		&& (is_array($FilterIflist[$rule['interface']][0]))) {
		/* Currently this only case for this is the pppoe server. There should be an existing macro with this name. */
		$aline['interface'] = " on \$" . $rule['interface'] . " ";
	} else
		$aline['interface'] = " on \$" . $FilterIflist[$rule['interface']]['descr'] . " ";
	$ifcfg = $FilterIflist[$rule['interface']];
	if($pptpdcfg['mode'] != "server") {
		if(($rule['source']['network'] == "pptp") ||
			($rule['destination']['network'] == "pptp"))
				return "# source network or destination network == pptp on " . $rule['descr'];
	}

	if(isset($rule['ipprotocol'])) {
		switch($rule['ipprotocol']) {
			case "inet":
				$aline['ipprotocol'] = "inet";
				break;
			case "inet6":
				$aline['ipprotocol'] = "inet6";
				break;
		}
	}

	/* check for unresolvable aliases */
	if($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
		file_notice("Filter_Reload", "# unresolvable source aliases {$rule['descr']}");
		return "# unresolvable source aliases {$rule['descr']}";
	}
	if($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
		file_notice("Filter_Reload", "# unresolvable dest aliases {$rule['descr']}");
		return "# unresolvable dest aliases {$rule['descr']}";
	}
	update_filter_reload_status("Setting up pass/block rules");
	$type = $rule['type'];
	if($type != "pass" && $type != "block" && $type != "reject" && $type != "match") {
		/* default (for older rules) is pass */
		$type = "pass ";
	}
	if($type == "reject") {
		$aline['type'] = "block return ";
	} else
		$aline['type'] = $type . " ";
	if(isset($rule['floating']) && $rule['floating'] == "yes") {
		if($rule['direction'] != "any")
			$aline['direction'] = " " . $rule['direction'] . " ";
	} else {
		/* ensure the direction is in */
		$aline['direction'] = " in ";
	}
	if(isset($rule['log']))
		$aline['log'] = "log ";
	if(!isset($rule['floating']) || isset($rule['quick']))
		$aline['quick'] = " quick ";

	/* set the gateway interface */
	update_filter_reload_status(sprintf(gettext("Setting up pass/block rules %s"), $rule['descr']));

	/* do not process reply-to for gateway'd rules */
	if($rule['gateway'] == "" && $aline['direction'] <> "" && interface_has_gateway($rule['interface']) && !isset($config['system']['disablereplyto']) && !isset($rule['disablereplyto']) && $type != "match") {
		if($rule['ipprotocol'] == "inet6") {
			$rg = get_interface_gateway_v6($rule['interface']);
			if(is_ipaddrv6($rg)) {
				$aline['reply'] = "reply-to ( {$ifcfg['if']} {$rg} ) ";
			} else {
				if($rule['interface'] <> "pptp") {
					log_error("Could not find IPv6 gateway for interface({$rule['interface']}).");
				}
			}
		} else {
			$rg = get_interface_gateway($rule['interface']);
			if(is_ipaddrv4($rg)) {
				$aline['reply'] = "reply-to ( {$ifcfg['if']} {$rg} ) ";
			} else {
				if($rule['interface'] <> "pptp") {
					log_error(sprintf(gettext("Could not find IPv4 gateway for interface (%s)."), $rule['interface']));
				}
			}
		}
	}
	/* if user has selected a custom gateway, lets work with it */
	else if($rule['gateway'] <> "" && $type == "pass") {
		if (isset($GatewaysList[$rule['gateway']]))
			/* Add the load balanced gateways */
			$aline['route'] = " \$GW{$rule['gateway']} ";
		else
			log_error("The gateway: {$rule['gateway']} is invalid or unknown, not using it.");
	}

	if (isset($rule['protocol']) && !empty($rule['protocol'])) {
		if($rule['protocol'] == "tcp/udp")
			$aline['prot'] = " proto { tcp udp } ";
		elseif(($rule['protocol'] == "icmp") && ($rule['ipprotocol'] == "inet6"))
			$aline['prot'] = " proto ipv6-icmp ";
		elseif($rule['protocol'] == "icmp")
			$aline['prot'] = " proto icmp ";
		else
			$aline['prot'] = " proto {$rule['protocol']} ";
	} else {
		if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "")
			$aline['prot'] = " proto tcp ";
	}
	update_filter_reload_status(sprintf(gettext("Creating rule %s"), $rule['descr']));

	/* source address */
	$src = filter_generate_address($rule, "source");
	if(empty($src) || ($src == "/")) {
		return "# at the break!";
	}
	$aline['src'] = " from $src ";

	/* OS signatures */
	if(($rule['protocol'] == "tcp") && ($rule['os'] <> ""))
		$aline['os'] = " os {$rule['os']} ";

	/* destination address */
	$dst = filter_generate_address($rule, "destination");
	if(empty($dst) || ($dst == "/")) {
		return "# returning at dst $dst == \"/\"";
	}
	$aline['dst'] = "to $dst ";

	//Layer7 support
	$l7_present = false;
	$l7_structures = array();
	if(isset($rule['l7container']) && $rule['l7container'] != "none") {
		$l7_present = true;
		$l7rule =& $layer7_rules_list[$rule['l7container']];
		$l7_structures = $l7rule->get_unique_structures();
		$aline['divert'] = "divert " . $l7rule->GetRPort() . " ";
	}
	if(($rule['protocol'] == "icmp") && $rule['icmptype'] && ($rule['ipprotocol'] == "inet"))
		$aline['icmp-type'] = "icmp-type {$rule['icmptype']} ";
	if(($rule['protocol'] == "icmp") && $rule['icmptype'] && ($rule['ipprotocol'] == "inet6"))
		$aline['icmp6-type'] = "icmp6-type {$rule['icmptype']} ";
	if(!empty($rule['tag']))
		$aline['tag'] = " tag " .$rule['tag']. " ";
	if(!empty($rule['tagged']))
		$aline['tagged'] = " tagged " .$rule['tagged'] . " ";
	if(!empty($rule['dscp']))
		$aline['dscp'] = " dscp " . $rule['dscp'] . " ";
	if($type == "pass") {
		if(isset($rule['allowopts']))
			$aline['allowopts'] = " allow-opts ";

		$aline['flags'] = "";
		if($rule['protocol'] == "tcp") {
			if (isset($rule['tcpflags_any']))
				$aline['flags'] = "flags any ";
			else if (!empty($rule['tcpflags2'])) {
				$aline['flags'] = "flags ";
				if (!empty($rule['tcpflags1'])) {
					$flags1 = explode(",", $rule['tcpflags1']);
					foreach ($flags1 as $flag1)
						$aline['flags'] .= strtoupper($flag1[0]);
				}
				$aline['flags'] .= "/";
				if (!empty($rule['tcpflags2'])) {
					$flags2 = explode(",", $rule['tcpflags2']);
					foreach ($flags2 as $flag2)
						$aline['flags'] .= strtoupper($flag2[0]);
				}
				$aline['flags'] .= " ";
			} else
				$aline['flags'] = "flags S/SA ";
		}

		/*
		 *	# keep state
		 *		works with TCP, UDP, and ICMP.
		 *	# modulate state
		 *		works only with TCP. pfSense will generate strong Initial Sequence Numbers (ISNs)
		 *		for packets matching this rule.
		 *	# synproxy state
		 *		proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods.
		 *		This option includes the functionality of keep state and modulate state combined.
		 *	# none
		 *		do not use state mechanisms to keep track. this is only useful if your doing advanced
		 *		queueing in certain situations. please check the faq.
		 */
		$noadvoptions = false;
		if(isset($rule['statetype']) && $rule['statetype'] <> "") {
			switch($rule['statetype']) {
				case "none":
					$noadvoptions = true;
					$aline['flags'] .= " no state ";
					break;
				case "modulate state":
				case "synproxy state":
					if($rule['protocol'] == "tcp")
						$aline['flags'] .= "{$rule['statetype']} ";
					break;
				case "sloppy state":
					$aline['flags'] .= "keep state ";
					$rule['sloppy'] = true;
					break;
				default:
					$aline['flags'] .= "{$rule['statetype']} ";
					break;
			}
		} else
			$aline['flags'] .= "keep state ";

		if($noadvoptions == false || $l7_present)
			if( (isset($rule['source-track']) and $rule['source-track'] <> "") or
			    (isset($rule['max']) and $rule['max'] <> "") or
			    (isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "") or
			    (isset($rule['max-src-conn']) and $rule['max-src-conn'] <> "") or
			    (isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "") or
			    (isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "") or
			    (isset($rule['max-src-states']) and $rule['max-src-states'] <> "") or
			    (isset($rule['statetimeout']) and $rule['statetimeout'] <> "") or
			    isset($rule['sloppy']) or $l7_present) {
					$aline['flags'] .= "( ";
					if (isset($rule['sloppy']))
						$aline['flags'] .= "sloppy ";
					if(isset($rule['source-track']) and $rule['source-track'] <> "")
						$aline['flags'] .= "source-track rule ";
					if(isset($rule['max']) and $rule['max'] <> "")
						$aline['flags'] .= "max " . $rule['max'] . " ";
					if(isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "")
						$aline['flags'] .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
					if(isset($rule['max-src-conn']) and $rule['max-src-conn'] <> "")
						$aline['flags'] .= "max-src-conn " . $rule['max-src-conn'] . " ";
					if(isset($rule['max-src-states']) and $rule['max-src-states'] <> "")
						$aline['flags'] .= "max-src-states " . $rule['max-src-states'] . " ";
					if(isset($rule['statetimeout']) and $rule['statetimeout'] <> "")
						$aline['flags'] .= "tcp.established " . $rule['statetimeout'] . " ";
					if(isset($rule['max-src-conn-rate'])
							and $rule['max-src-conn-rate'] <> ""
							and isset($rule['max-src-conn-rates'])
							and $rule['max-src-conn-rates'] <> "") {
						$aline['flags'] .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";
						$aline['flags'] .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
					}

					if(!empty($aline['divert']))
						$aline['flags'] .= "max-packets 8 ";

					$aline['flags'] .= " ) ";
				}
	}
	if($type == "reject" && $rule['protocol'] == "tcp") {
		/* special reject packet */
		$aline['flags'] .= "flags S/SA ";
	}
	if($rule['defaultqueue'] <> "") {
		$aline['queue'] = " queue (".$rule['defaultqueue'];
		if($rule['ackqueue'] <> "")
			$aline['queue'] .= ",".$rule['ackqueue'];
		$aline['queue'] .= ") ";
	}
	if($rule['dnpipe'] <> "") {
		if($rule['dnpipe'][0] == "?") {
			$aline['dnpipe'] = " dnqueue( ";
			$aline['dnpipe'] .= substr($rule['dnpipe'],1);
			if($rule['pdnpipe'] <> "")
				$aline['dnpipe'] .= ",".substr($rule['pdnpipe'], 1);
		} else {
			$aline['dnpipe'] = " dnpipe ( " . $rule['dnpipe'];
			if($rule['pdnpipe'] <> "")
				$aline['dnpipe'] .= ", " . $rule['pdnpipe'];
		}
		$aline['dnpipe'] .= ") ";
	}

	/* is a time based rule schedule attached? */
	if(!empty($rule['sched']) && !empty($config['schedules'])) {
		$aline['schedlabel'] = "";
		foreach ($config['schedules']['schedule'] as $sched) {
			if($sched['name'] == $rule['sched']) {
				if(!filter_get_time_based_rule_status($sched)) {
					if(!isset($config['system']['schedule_states']))
						mwexec("/sbin/pfctl -y {$sched['schedlabel']}");
					return "# schedule finished - {$rule['descr']}";
				} else if($g['debug'])
					log_error("[TDR DEBUG] status true -- rule type '$type'");

				$aline['schedlabel'] = " schedule \"{$sched['schedlabel']}\" ";
				break;
			}
		}
	}

	$line = "";
	/* exception(s) to a user rules can go here. */
	/* rules with a gateway or pool should create another rule for routing to vpns */
	if((($aline['route'] <> "") && (trim($aline['type']) == "pass") && strstr($dst, "any")) && (!isset($config['system']['disablenegate']))) {
		/* negate VPN/PPTP/PPPoE/Static Route networks for load balancer/gateway rules */
		$negate_networks = " to <negate_networks> ";
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] .
			$aline['interface'] . $aline['ipprotocol'] . $aline['prot'] . $aline['src'] . $aline['os'] .
			$negate_networks . $aline['icmp-type'] . $aline['icmp6-type'] . $aline['tag'] . $aline['tagged'] .
			$aline['dscp'] . $aline['allowopts'] . $aline['flags'] .
			$aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'] .
			" label \"NEGATE_ROUTE: Negate policy routing for destination\"\n";

	}
	/* piece together the actual user rule */
	$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] .
		$aline['reply'] . $aline['route'] . $aline['ipprotocol'] . $aline['prot'] . $aline['src'] . $aline['os'] . $aline['dst'] .
		$aline['divert'] . $aline['icmp-type'] . $aline['icmp6-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] .
		$aline['allowopts'] . $aline['flags'] . $aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'];


	return $line;
}

function filter_rules_generate() {
	global $config, $g, $FilterIflist, $time_based_rules, $GatewaysList;

	update_filter_reload_status(gettext("Creating default rules"));
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$pptpdcfg = $config['pptpd'];

	$ipfrules = "";
	$ipfrules .= discover_pkg_rules("pfearly");

	/* relayd */
	$ipfrules .= "anchor \"relayd/*\"\n";
	# BEGIN OF firewall rules
	/* default block logging? */
	if(!isset($config['syslog']['nologdefaultblock']))
		$log = "log";
	else
		$log = "";
	$ipfrules .= <<<EOD
#---------------------------------------------------------------------------
# default deny rules
#---------------------------------------------------------------------------
block in $log inet all label "Default deny rule IPv4"
block out $log inet all label "Default deny rule IPv4"
block in $log inet6 all label "Default deny rule IPv6"
block out $log inet6 all label "Default deny rule IPv6"

# IPv6 ICMP is not auxilary, it is required for operation
# See man icmp6(4)
# 1    unreach         Destination unreachable
# 2    toobig          Packet too big
# 128  echoreq         Echo service request
# 129  echorep         Echo service reply
# 133  routersol       Router solicitation
# 134  routeradv       Router advertisement
# 135  neighbrsol      Neighbor solicitation
# 136  neighbradv      Neighbor advertisement
pass quick inet6 proto ipv6-icmp from any to any icmp6-type {1,2,135,136} keep state

# Allow only bare essential icmpv6 packets (NS, NA, and RA, echoreq, echorep)
pass out quick inet6 proto ipv6-icmp from fe80::/10 to fe80::/10 icmp6-type {128,133,134,135,136} keep state
pass out quick inet6 proto ipv6-icmp from fe80::/10 to ff02::/16 icmp6-type {128,133,134,135,136} keep state
pass in quick inet6 proto ipv6-icmp from fe80::/10 to fe80::/10 icmp6-type {129,133,134,135,136} keep state
pass in quick inet6 proto ipv6-icmp from ff02::/16 to fe80::/10 icmp6-type {129,133,134,135,136} keep state
pass in quick inet6 proto ipv6-icmp from fe80::/10 to ff02::/16 icmp6-type {129,133,134,135,136} keep state

# We use the mighty pf, we cannot be fooled.
block quick inet proto { tcp, udp } from any port = 0 to any
block quick inet proto { tcp, udp } from any to any port = 0
block quick inet6 proto { tcp, udp } from any port = 0 to any
block quick inet6 proto { tcp, udp } from any to any port = 0


EOD;

	if(!isset($config['system']['ipv6allow'])) {
		$ipfrules .= "# Block all IPv6\n";
		$ipfrules .= "block in inet6 all label \"Default Deny ipv6 rule\"\n";
		$ipfrules .= "block out inet6 all label \"Default Deny ipv6 rule\"\n";
	}

	$ipfrules .= <<<EOD

# Snort package
block quick from <snort2c> to any label "Block snort2c hosts"
block quick from any to <snort2c> label "Block snort2c hosts"

EOD;

	$ipfrules .= filter_process_carp_rules();

	$ipfrules .= "\n# SSH lockout\n";
	if(is_array($config['system']['ssh']) && !empty($config['system']['ssh']['port'])) {
		$ipfrules .= "block in log quick proto tcp from <sshlockout> to any port ";
		$ipfrules .= $config['system']['ssh']['port'];
		$ipfrules .= " label \"sshlockout\"\n";
	} else {
		if($config['system']['ssh']['port'] <> "")
			$sshport = $config['system']['ssh']['port'];
		else
			$sshport = 22;
		if($sshport)
			$ipfrules .= "block in log quick proto tcp from <sshlockout> to any port {$sshport} label \"sshlockout\"\n";
	}

	$ipfrules .= "\n# webConfigurator lockout\n";
	if(!$config['system']['webgui']['port']) {
		if($config['system']['webgui']['protocol'] == "http") 
			$webConfiguratorlockoutport = "80";
		else
			$webConfiguratorlockoutport = "443";
	} else {
		$webConfiguratorlockoutport = $config['system']['webgui']['port'];
	}
	if($webConfiguratorlockoutport)
		$ipfrules .= "block in log quick proto tcp from <webConfiguratorlockout> to any port {$webConfiguratorlockoutport} label \"webConfiguratorlockout\"\n";

	/*
	 * Support for allow limiting of TCP connections by establishment rate
	 * Useful for protecting against sudden outburts, etc.
	 */
	$ipfrules .= "block in quick from <virusprot> to any label \"virusprot overload table\"\n";

	/* if captive portal is enabled, ensure that access to this port
         * is allowed on a locked down interface
         */
        if(is_array($config['captiveportal'])) {
                foreach ($config['captiveportal'] as $cpcfg) {
                        $cpinterfaces = explode(",", $cpcfg['interface']);
                        $cpiflist = array();
                        $cpiplist = array();
                        foreach ($cpinterfaces as $cpifgrp) {
                                if(!isset($FilterIflist[$cpifgrp]))
                                        continue;
                                $tmpif = get_real_interface($cpifgrp);
                                if(!empty($tmpif)) {
                                        $cpiflist[] = "{$tmpif}";
                                        $cpipm = get_interface_ip($cpifgrp);
                                        if(is_ipaddr($cpipm)) {
                                                $carpif = link_ip_to_carp_interface($cpipm);
                                                if (!empty($carpif)) {
                                                        $cpiflist[] = $carpif;
                                                        $carpsif = explode(" ", $carpif);
                                                        foreach ($carpsif as $cpcarp) {
                                                                $carpip = find_interface_ip($cpcarp);
                                                                if (is_ipaddr($carpip))
                                                                $cpiplist[] = $carpip;
                                                        }
                                                }
                                                $cpiplist[] = $cpipm;
                                        }
                                }
                        }
                        if (count($cpiplist) > 0 && count($cpiflist) > 0) {
                                $cpinterface = implode(" ", $cpiflist);
                                $cpaddresses = implode(" ", $cpiplist);
                                $portalias = $cpcfg['zoneid'] + 1;
                                $portalias .= " {$cpcfg['zoneid']}";
                                $ipfrules .= "pass in {$log} quick on { {$cpinterface} } proto tcp from any to { {$cpaddresses} } port { {$portalias} } keep state(sloppy)\n";
                                $ipfrules .= "pass out {$log} quick on { {$cpinterface} } proto tcp from any to any flags any keep state(sloppy)\n";
                        }
                }
        }

	$bogontableinstalled = 0;
	foreach ($FilterIflist as $on => $oc) {
		/* block bogon networks */
		/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
		/* file is automatically in cron every 3000 minutes */
		if(isset($config['interfaces'][$on]['blockbogons'])) {
			if($bogontableinstalled == 0) {
				$ipfrules .= "table <bogons> persist file \"/etc/bogons\"\n";
				$ipfrules .= "table <bogonsv6> persist file \"/etc/bogonsv6\"\n";
			}
			$ipfrules .= <<<EOD
# block bogon networks
# http://www.cymru.com/Documents/bogon-bn-nonagg.txt
# http://www.team-cymru.org/Services/Bogons/fullbogons-ipv6.txt
block in $log quick on \${$oc['descr']} from <bogons> to any label "block bogon IPv4 networks from {$oc['descr']}"
block in $log quick on \${$oc['descr']} from <bogonsv6> to any label "block bogon IPv6 networks from {$oc['descr']}"

EOD;
			$bogontableinstalled++;
		}
		$isbridged = false;
		if(is_array($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $oc2) {
				if(stristr($oc2['members'], $on)) {
					$isbridged = true;
					break;
				}
			}
		}
		if($oc['ip'] && !($isbridged) && isset($oc['spoofcheck']))
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);
		/* block private networks ? */
		if(isset($config['interfaces'][$on]['blockpriv'])) {
			if($isbridged == false) {
				$ipfrules .= <<<EOD
# block anything from private networks on interfaces with the option set
antispoof for \${$oc['descr']}
block in $log quick on \${$oc['descr']} from 10.0.0.0/8 to any label "Block private networks from {$oc['descr']} block 10/8"
block in $log quick on \${$oc['descr']} from 127.0.0.0/8 to any label "Block private networks from {$oc['descr']} block 127/8"
block in $log quick on \${$oc['descr']} from 172.16.0.0/12 to any label "Block private networks from {$oc['descr']} block 172.16/12"
block in $log quick on \${$oc['descr']} from 192.168.0.0/16 to any label "Block private networks from {$oc['descr']} block 192.168/16"
block in $log quick on \${$oc['descr']} from fc00::/7 to any label "Block ULA networks from {$oc['descr']} block fc00::/7"

EOD;
			}
		}
		switch ($oc['type']) {
		case "pptp":
				$ipfrules .= <<<EOD
# allow PPTP client
pass in on \${$oc['descr']} proto tcp from any to any port = 1723 flags S/SA modulate state label "allow PPTP client on {$oc['descr']}"
pass in on \${$oc['descr']} proto gre from any to any keep state label "allow PPTP client on {$oc['descr']}"

EOD;
			break;
		case "dhcp":
		case "carpdev-dhcp":
			$ipfrules .= <<<EOD
# allow our DHCP client out to the {$oc['descr']}
pass in on \${$oc['descr']} proto udp from any port = 67 to any port = 68 label "allow dhcp client out {$oc['descr']}"
pass out on \${$oc['descr']} proto udp from any port = 68 to any port = 67 label "allow dhcp client out {$oc['descr']}"
# Not installing DHCP server firewall rules for {$oc['descr']} which is configured for DHCP.

EOD;

			break;
		case "pppoe":
		case "none":
			/* XXX: Nothing to do in this case?! */
			break;
		default:
			/* allow access to DHCP server on interfaces */
			if(isset($config['dhcpd'][$on]['enable'])) {
				$ipfrules .= <<<EOD

# allow access to DHCP server on {$oc['descr']}
pass in quick on \${$oc['descr']} proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server"
pass in quick on \${$oc['descr']} proto udp from any port = 68 to {$oc['ip']} port = 67 label "allow access to DHCP server"
pass out quick on \${$oc['descr']} proto udp from {$oc['ip']} port = 67 to any port = 68 label "allow access to DHCP server"

EOD;
				if($config['dhcpd'][$on]['failover_peerip'] <> "") {
					$ipfrules .= <<<EOD
# allow access to DHCP failover on {$oc['descr']} from {$config['dhcpd'][$on]['failover_peerip']}
pass in quick on \${$oc['descr']} proto { tcp udp } from {$config['dhcpd'][$on]['failover_peerip']} to {$oc['ip']} port = 519 label "allow access to DHCP failover"
pass in quick on \${$oc['descr']} proto { tcp udp } from {$config['dhcpd'][$on]['failover_peerip']} to {$oc['ip']} port = 520 label "allow access to DHCP failover"

EOD;
				}

			}
			break;
		}
		switch($oc['type6']) {
		case "dhcp6":
			$ipfrules .= <<<EOD

# allow our DHCPv6 client out to the {$oc['descr']}
pass in on \${$oc['descr']} proto udp from any port = 547 to any port = 546 label "allow dhcpv6 client out {$oc['descr']}"
pass out on \${$oc['descr']} proto udp from any port = 546 to any port = 547 label "allow dhcpv6 client out {$oc['descr']}"

EOD;
			break;
		default:
			if(isset($config['dhcpdv6'][$on]['enable'])) {
				$ipfrules .= <<<EOD

# allow access to DHCPv6 server on {$oc['descr']}
anchor "dhcpv6server{$oc['descr']}"
# We need inet6 icmp for stateless autoconfig and dhcpv6
pass in on \${$oc['descr']} inet6 proto udp from fe80::/10 to ff02::/16 port = 546 label "allow access to DHCPv6 server"
pass in on \${$oc['descr']} inet6 proto udp from fe80::/10 to ff02::/16 port = 547 label "allow access to DHCPv6 server"
pass in on \${$oc['descr']} inet6 proto udp from fe80::/10 to {$oc['ipv6']} port = 546 label "allow access to DHCPv6 server"
pass out on \${$oc['descr']} inet6 proto udp from {$oc['ipv6']} port = 547 to fe80::/10 label "allow access to DHCPv6 server"

EOD;
			}
			break;
		}
	}
	/*
	 * NB: The loopback rules are needed here since the antispoof would take precedence then.
	 *		If you ever add the 'quick' keyword to the antispoof rules above move the looback
	 *		rules before them.
	 */
	$ipfrules .= <<<EOD

# loopback
pass in on \$loopback inet all label "pass IPv4 loopback"
pass out on \$loopback inet all label "pass IPv4 loopback"
pass in on \$loopback inet6 all label "pass IPv6 loopback"
pass out on \$loopback inet6 all label "pass IPv6 loopback"

EOD;

	$ipfrules .= <<<EOD
# let out anything from the firewall host itself and decrypted IPsec traffic
pass out inet all keep state allow-opts label "let out anything IPv4 from firewall host itself"
pass out inet6 all keep state allow-opts label "let out anything IPv6 from firewall host itself"

EOD;
	foreach ($FilterIflist as $ifdescr => $ifcfg) {
		if(isset($ifcfg['virtual']))
			continue;

		$gw = get_interface_gateway($ifdescr);
		if (is_ipaddr($gw) && is_ipaddr($ifcfg['ip']))
                	$ipfrules .= "pass out route-to ( {$ifcfg['if']} {$gw} ) from {$ifcfg['ip']} to !{$ifcfg['sa']}/{$ifcfg['sn']} keep state allow-opts label \"let out anything from firewall host itself\"\n";

		$gwv6 = get_interface_gateway_v6($ifdescr);
		if (is_ipaddrv6($gwv6) && is_ipaddrv6($ifcfg['ipv6']))
                	$ipfrules .= "pass out route-to ( {$ifcfg['if']} {$gwv6} ) inet6 from {$ifcfg['ipv6']} to !{$ifcfg['sav6']}/{$ifcfg['snv6']} keep state allow-opts label \"let out anything from firewall host itself\"\n";
	}
	

	/* add ipsec interfaces */
	if(isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
		$ipfrules .= <<<EOD
pass out on \$IPsec all keep state label "IPsec internal host to host"

EOD;

	if(!isset($config['system']['webgui']['noantilockout'])) {
		$portarg = 80;
		if (isset($config['system']['webgui']['port']) && $config['system']['webgui']['port'] <> "")
			$portarg = "{$config['system']['webgui']['port']}";
		if ($config['system']['webgui']['protocol'] == "https")
			$portarg .= " 443 ";
		$sshport = "";
		if (isset($config['system']['enablesshd'])) {
			$sshport = 22;
			if($config['system']['ssh']['port'] <> "")
				$sshport = $config['system']['ssh']['port'];
		}
		if(count($config['interfaces']) > 1 && !empty($FilterIflist['lan']['if'])) {
				/* if antilockout is enabled, LAN exists and has
				 * an IP and subnet mask assigned
				 */
				$lanif = $FilterIflist['lan']['if'];
				$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
pass in quick on {$lanif} proto tcp from any to ({$lanif}) port { $portarg $sshport } keep state label "anti-lockout rule"

EOD;
		} else if (count($config['interfaces']) == 1) {
			/* single-interface deployment, add to WAN	*/
			$wanif = $FilterIflist["wan"]['if'];
			$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
pass in quick on {$wanif} proto tcp from any to ({$wanif}) port { $portarg $sshport } keep state label "anti-lockout rule"

EOD;
		}
	}
	/* PPTPd enabled? */
	if($pptpdcfg['mode'] && ($pptpdcfg['mode'] != "off") && !isset($config['system']['disablevpnrules'])) {
		if($pptpdcfg['mode'] == "server")
			$pptpdtarget = get_interface_ip();
		else
			$pptpdtarget = $pptpdcfg['redir'];
		if(is_ipaddr($pptpdtarget) and is_array($FilterIflist['wan'])) {
			$ipfrules .= <<<EOD
# PPTPd rules
pass in on \${$FilterIflist['wan']['descr']} proto tcp from any to $pptpdtarget port = 1723 modulate state label "allow pptpd {$pptpdtarget}"
pass in on \${$FilterIflist['wan']['descr']} proto gre from any to any keep state label "allow gre pptpd"

EOD;

		} else {
			/*	  this shouldnt ever happen but instead of breaking the clients ruleset
			 *	  log an error.
			 */
			log_error("ERROR!  PPTP enabled but could not resolve the \$pptpdtarget");
		}
	}

	if(isset($config['nat']['rule']) && is_array($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $rule) {
			if((!isset($config['system']['disablenatreflection']) || $rule['natreflection'] == "enable")
			   && $rule['natreflection'] != "disable") {
				$ipfrules .= "# NAT Reflection rules\n";
				$ipfrules .= <<<EOD
pass in inet tagged PFREFLECT keep state label "NAT REFLECT: Allow traffic to localhost"

EOD;
				break;
			}
		}
	}

	if(isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		$rule_arr1 = array();
		$rule_arr2 = array();
		/*
		 * NB: Floating rules need to be written before regular once.
		 */
		foreach ($config['filter']['rule'] as $rule) {
			update_filter_reload_status("Pre-caching {$rule['descr']}...");
			if(!isset ($rule['disabled'])) {
				if(isset($rule['floating'])) {
					$rule_arr1[] = filter_generate_user_rule_arr($rule);
				} else {
					$rule_arr2[] = filter_generate_user_rule_arr($rule);
				}
				if($rule['sched'])
					$time_based_rules = true;
			}
		}
		$rule_arr = array_merge($rule_arr1,$rule_arr2);

		$ipfrules .= "\n# User-defined rules follow\n";
		$ipfrules .= "\nanchor \"userrules/*\"\n";
		/* Generate user rule lines */
		foreach($rule_arr as $rule) {
			if(isset($rule['disabled']))
				continue;
			if(!$rule['rule'])
				continue;
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
	}

	/*  pass traffic between statically routed subnets and the subnet on the
	 *  interface in question to avoid problems with complicated routing
	 *  topologies
	 */
	if(isset($config['filter']['bypassstaticroutes']) && is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		$ipfrules .= "# Add rules to bypass firewall rules for static routes\n";
		foreach ($config['staticroutes']['route'] as $route) {
			$friendly = $GatewaysList[$route['gateway']]['friendlyiface'];
			if(is_array($FilterIflist[$friendly])) {
				$oc = $FilterIflist[$friendly];
				$routeent = explode("/", $route['network']);
				if($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
				if($sa && is_ipaddrv4($routeent[0])) {
					$ipfrules .= <<<EOD
pass quick on \${$oc['descr']} proto tcp from {$sa}/{$sn} to {$route['network']} flags any keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} proto tcp from {$route['network']} to {$sa}/{$sn} flags any keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} keep state(sloppy) label "pass traffic between statically routed subnets"

EOD;
				}
				if($oc['ipv6']) {
					$sa = $oc['sav6'];
					$sn = $oc['snv6'];
					$if = $oc['if'];
				}
				if($sa && is_ipaddrv6($routeent[0])) {
					$ipfrules .= <<<EOD
pass quick on \${$oc['descr']} inet6 proto tcp from {$sa}/{$sn} to {$route['network']} flags any keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} inet6 from {$sa}/{$sn} to {$route['network']} keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} inet6 proto tcp from {$route['network']} to {$sa}/{$sn} flags any keep state(sloppy) label "pass traffic between statically routed subnets"
pass quick on \${$oc['descr']} inet6 from {$route['network']} to {$sa}/{$sn} keep state(sloppy) label "pass traffic between statically routed subnets"

EOD;
				}
			}
		}
	}

	update_filter_reload_status(gettext("Creating IPsec rules..."));
	$ipfrules .= filter_generate_ipsec_rules();

	$ipfrules .= <<<EOD
anchor "tftp-proxy/*"

EOD;

	update_filter_reload_status("Creating uPNP rules...");
	if(isset($config['installedpackages']['miniupnpd']) && isset($config['installedpackages']['miniupnpd']['config'][0]['enable'])) {

	$ipfrules .= <<<EOD
# uPnPd
anchor "miniupnpd"

EOD;
	
		$upnp_interfaces = explode(",", $config['installedpackages']['miniupnpd'][0]['config']['iface_array']);
		foreach($upnp_interfaces as $upnp_if) {
			if(is_array($FilterIflist[$upnp_if])) {
				$oc = $FilterIflist[$upnp_if];
				if($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
				if($sa) {
					$ipfrules .= <<<EOD

pass in on \${$oc['descr']} proto tcp from {$sa}/{$sn} to 239.255.255.250/32 port 1900 keep state label "pass multicast traffic to miniupnpd"

EOD;
				}
			}
		}
	}


	return $ipfrules;
}

function filter_rules_spoofcheck_generate($ifname, $if, $sa, $sn, $log) {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_spoofcheck_generate() being called $mt\n";
	}
	$ipfrules = "antispoof for {$if}\n";
	return $ipfrules;
}

/* COMPAT Function */
function tdr_install_cron($should_install) {
	log_error(gettext("Please use filter_tdr_install_cron() function tdr_install_cron will be deprecated!"));
	filter_tdr_install_cron($should_install);
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

	if($g['booting']==true)
		return;

	$is_installed = false;

	if(!$config['cron']['item'])
		return;

	$x=0;
	foreach($config['cron']['item'] as $item) {
		if(strstr($item['command'], "filter_configure_sync")) {
			$is_installed = true;
			break;
		}
		$x++;
	}

	switch($should_install) {
		case true:
			if(!$is_installed) {
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
			if($is_installed == true) {
				if($x > 0) {
					unset($config['cron']['item'][$x]);
					write_config();
				}
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
	$should_add_rule = false;
	/* no schedule? rule should be installed */
	if(empty($schedule))
		return true;
	/*
	 * iterate through time blocks and determine
	 * if the rule should be installed or not.
	 */
	foreach($schedule['timerange'] as $timeday) {
		if($timeday['month'])
			$month = $timeday['month'];
		else
			$week = "";
		if($timeday['day'])
			$day = $timeday['day'];
		else
			$day = "";
		if($timeday['hour'])
			$hour = $timeday['hour'];
		else
			$hour = "";
		if($timeday['position'])
			$position = $timeday['position'];
		else
			$position = "";
		if($timeday['descr'])
			$desc = $timeday['descr'];
		else
			$desc = "";
		if($month) {
			$monthstatus = filter_tdr_month($month);
		} else {
			$monthstatus = true;
		}
		if($day) {
			$daystatus = filter_tdr_day($day);
		} else {
			$daystatus = true;
		}
		if($hour) {
			$hourstatus = filter_tdr_hour($hour);
		} else {
			$hourstatus = true;
		}
		if($position) {
			$positionstatus = filter_tdr_position($position);
		} else {
			$positionstatus = true;
		}

		if($monthstatus == true && $daystatus == true && $positionstatus == true && $hourstatus == true)
			$should_add_rule = true;
	}

	return $should_add_rule;
}

function filter_tdr_day($schedule) {
	global $g;
	/*
	 * Calculate day of month.
	 * IE: 29th of may
	 */
	$weekday = date("w");
	if($weekday == 0)
		$weekday = 7;
	$date = date("d");
	$defined_days = explode(",", $schedule);
	if($g['debug'])
		log_error("[TDR DEBUG] filter_tdr_day($schedule)");
	foreach($defined_days as $dd) {
		if($date == $dd)
			return true;
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
	if($g['debug'])
		log_error("[TDR DEBUG] S: $starting_time E: $ending_time N: $now");
	if($now >= $starting_time and $now <= $ending_time)
		return true;
	return false;
}

function filter_tdr_position($schedule) {
	global $g;
	/*
	 * Calculate possition, ie: day of week.
	 * Sunday = 7, Monday = 1, Tuesday = 2
	 * Weds = 3, Thursday = 4, Friday = 5,
	 * Saturday = 6
	 * ...
	 */
	$weekday = date("w");
	if($g['debug'])
		log_error("[TDR DEBUG] filter_tdr_position($schedule) $weekday");
	if($weekday == 0)
		$weekday = 7;
	$schedule_days = explode(",", $schedule);
	foreach($schedule_days as $day) {
		if($day == $weekday)
			return true;
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
	if($g['debug'])
		log_error("[TDR DEBUG] filter_tdr_month($schedule)");
	foreach($months as $month) {
		if($month == $todays_month)
			return true;
	}
	return false;
}

function filter_setup_logging_interfaces() {
	global $config, $FilterIflist;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_setup_logging_interfaces() being called $mt\n";
	}
	$rules = "";
	if (isset($FilterIflist['lan']))
		$rules .= "set loginterface {$FilterIflist['lan']['if']}\n";
	else if (isset($FilterIflist['wan']))
		$rules .= "set loginterface {$FilterIflist['wan']['if']}\n";

	return $rules;
}

function filter_process_carp_nat_rules() {
	global $g, $config;
	update_filter_reload_status("Creating CARP NAT rules");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_process_carp_nat_rules() being called $mt\n";
	}
	$lines = "";
	if(isset($config['installedpackages']['carp']['config']) &&
			is_array($config['installedpackages']['carp']['config'])) {
		foreach($config['installedpackages']['carp']['config'] as $carp) {
			$ip = $carp['ipaddress'];
			if($ip <> "any") {
				$ipnet = "any";
			} else {
				$int = find_ip_interface($ip);
				$carp_int = find_carp_interface($ip);
			}
			if($int != false and $int != $wan_interface) {
				$ipnet = convert_ip_to_network_format($ip, $carp['netmask']);
				if($int)
					$lines .= "nat on {$int} inet from {$ipnet} to any -> ({$carp_int}) \n";
			}
		}
	}
	return $lines;
}

function filter_process_carp_rules() {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_process_carp_rules() being called $mt\n";
	}
	$lines = "";
	/* return if there are no carp configured items */
	if(isset($config['installedpackages']['carp']['config']) &&
			 $config['installedpackages']['carpsettings']['config'] <> "" or
			 $config['virtualip']['vip'] <> "") {
		$lines .= "block in log quick proto carp from (self) to any\n";
		$lines .= "pass quick proto carp\n";
		$lines .= "pass quick proto pfsync\n";
	}
	return $lines;
}

/* Generate IPSEC Filter Items */
function filter_generate_ipsec_rules() {
	global $config, $g, $FilterIflist;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_ipsec_rules() being called $mt\n";
	}

	if (isset($config['system']['disablevpnrules']))
		return "\n# VPN Rules not added disabled in System->Advanced.\n";

	$ipfrules = "\n# VPN Rules\n";
	/* Is IP Compression enabled? */
	if(isset($config['ipsec']['ipcomp']))
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=1");
	else
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=0");

	if(isset($config['ipsec']['enable']) &&
		is_array($config['ipsec']['phase1'])) {
		/* step through all phase1 entries */
		foreach ($config['ipsec']['phase1'] as $ph1ent) {
			if(isset ($ph1ent['disabled']))
				continue;
			/* determine local and remote peer addresses */
			if(!isset($ph1ent['mobile'])) {
				$rgip = ipsec_get_phase1_dst($ph1ent);
				if(!$rgip) {
					$ipfrules .= "# ERROR! Unable to determine remote IPsec peer address for {$ph1ent['remote-gateway']}\n";
					continue;
				}
			} else
				$rgip = " any ";
			/* Determine best description */
			if($ph1ent['descr'])
				$descr = $ph1ent['descr'];
			else
				$descr = $rgip;
			/*
			 * Step through all phase2 entries and determine
			 * which protocols are in use with this peer
			 */
			$prot_used_esp = false;
			$prot_used_ah  = false;
			if(is_array($config['ipsec']['phase2'])) {
				foreach ($config['ipsec']['phase2'] as $ph2ent) {
					/* only evaluate ph2's bound to our ph1 */
					if($ph2ent['ikeid'] != $ph1ent['ikeid'])
						continue;
					if($ph2ent['protocol'] == 'esp')
						$prot_used_esp = true;
					if($ph2ent['protocol'] == 'ah')
						$prot_used_ah = true;
				}
			}

			if(preg_match("/^vip/i", $ph1ent['interface'])) {
				$parentinterface = link_carp_interface_to_parent($ph1ent['interface']);
			} else {
				$parentinterface = $ph1ent['interface'];
			}

			/* add endpoint routes to correct gateway on interface */
			if(interface_has_gateway($parentinterface)) {
				$gateway = get_interface_gateway($parentinterface);
				$interface = $FilterIflist[$parentinterface]['if'];

				/* Just in case */
				if(!is_ipaddr($gateway) || empty($interface)) {
					$route_to = " ";
					$reply_to = " ";
				} else {
					$route_to = " route-to ( $interface $gateway ) ";
					$reply_to = " reply-to ( $interface $gateway ) ";
				}

				/* Add rules to allow IKE to pass */
				$shorttunneldescr = substr($descr, 0, 35);
				$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto udp from any to {$rgip} port = 500 keep state label "IPsec: {$shorttunneldescr} - outbound isakmp"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to any port = 500 keep state label "IPsec: {$shorttunneldescr} - inbound isakmp"

EOD;
				/* If NAT-T is enabled, add additional rules */
				if($ph1ent['nat_traversal'] != "off" ) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto udp from any to {$rgip} port = 4500 keep state label "IPsec: {$shorttunneldescr} - outbound nat-t"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to any port = 4500 keep state label "IPsec: {$shorttunneldescr} - inbound nat-t"

EOD;
				}
				/* Add rules to allow the protocols in use */
				if($prot_used_esp == true) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto esp from any to {$rgip} keep state label "IPsec: {$shorttunneldescr} - outbound esp proto"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto esp from {$rgip} to any keep state label "IPsec: {$shorttunneldescr} - inbound esp proto"

EOD;
				}
				if($prot_used_ah == true) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto ah from any to {$rgip} keep state label "IPsec: {$shorttunneldescr} - outbound ah proto"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto ah from {$rgip} to any keep state label "IPsec: {$shorttunneldescr} - inbound ah proto"

EOD;
				}
			}
		}
	}
	return($ipfrules);
}

function discover_pkg_rules($ruletype) {
	global $config, $g, $aliases;

	/* Bail if there is no pkg directory, or if the package files might be out of sync. */
	if(!is_dir("/usr/local/pkg") || file_exists('/conf/needs_package_sync'))
		return "";

	$rules = "";
	$files = glob("/usr/local/pkg/*.inc");
	foreach($files as $pkg_inc) {
		update_filter_reload_status(sprintf(gettext('Checking for %1$s PF hooks in package %2$s'), $ruletype, $pkg_inc));
		require_once($pkg_inc);
		$pkg = basename($pkg_inc, ".inc");
		$pkg_generate_rules = "{$pkg}_generate_rules";
		if(function_exists($pkg_generate_rules)) {
			update_filter_reload_status(sprintf(gettext('Processing early %1$s rules for package %2$s'), $ruletype, $pkg_inc));
			$tmprules = $pkg_generate_rules("$ruletype");
			file_put_contents("{$g['tmp_path']}/rules.test.packages", $aliases . $tmprules);
			$status = mwexec("/sbin/pfctl -nf {$g['tmp_path']}/rules.test.packages");
			if ($status <> 0) {
				$errorrules = sprintf(gettext("There was an error while parsing the package filter rules for %s."), $pkg_inc) . "\n";
				log_error($errorrules);
				file_put_contents("{$g['tmp_path']}/rules.packages.{$pkg}", "#{$errorrules}\n");
				continue;
			}
			$rules .= $tmprules;
		}
	}
	return $rules;
}

?>
