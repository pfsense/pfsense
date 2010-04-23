<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004-2006 Scott Ullrich
	Copyright (C) 2005		Bill Marquette
	Copyright (C) 2006		Peter Allgeyer
	Copyright (C) 2008		Ermal Luci
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

/* include all configuration functions */

/* holds the items that will be executed *AFTER* the filter is fully loaded */
$after_filter_configure_run = array();

/* For installing cron job of schedules */
$time_based_rules = false;

/* Used to hold the interface list that will be used on ruleset creation. */
$FilterIflist = array();

/* Used for the hostname dns resolver */
$filterdns = "";

function flowtable_configure() {
	global $config, $g;
	return;
	// Figure out how many flows we should reserve
	// sized 2x larger than the number of unique connection destinations.
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) 
		$maxstates = $config['system']['maximumstates'];
	else
		$maxstates = "150000";		
	// nmbflows cpu count * ($maxstates * 2)
	$cpus = trim(`/sbin/sysctl kern.smp.cpus | /usr/bin/cut -d' ' -f2`);
	$nmbflows = ($cpus*($maxstates*2));
	// Is flowtable enabled?
	if($config['system']['flowtable'])
		$flowtable_enable = 1;
	else 
		$flowtable_enable = 0;
	// Flowtable currently only works on 8.0
	if(get_freebsd_version() == "8") {
		if($flowtable_enable == 1) {
			mwexec("/sbin/sysctl net.inet.flowtable.nmbflows={$config['system']['maximumstates']}");
			mwexec("/sbin/sysctl net.inet.ip.output_flowtable_size={$nmbflows}");
		}
		mwexec("/sbin/sysctl net.inet.flowtable.enable={$flowtable_enable}");		
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
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}
	mute_kernel_msgs();
	$pid = 0;
	$pid = `/bin/pgrep -f "tcpdump -s 256 -v -l -n -e -ttt -i pflog0"`;
	if(!$pid)
		mwexec_bg("/usr/sbin/tcpdump -s 256 -v -l -n -e -ttt -i pflog0 | logger -t pf -p local0.info");
	unmute_kernel_msgs();
}

/* reload filter async */
function filter_configure() {
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure() being called $mt\n";
	}
	global $g;
	touch($g['tmp_path'] . "/filter_dirty");
}

function delete_states_for_down_gateways() {
	global $config;
	$a_gateways = return_gateways_array();
	foreach ($a_gateways as $gateway) {
		if($gateway['monitor'] == "down") {
			$int_ip = get_interface_ip($gateway['interface']);
			if($int_ip)
				mwexec("/sbin/pfctl -b {$int_ip}");
		}
	}
}

/* reload filter sync */
function filter_configure_sync() {
	global $config, $g, $after_filter_configure_run, $FilterIflist, $GatewaysList, $GatewayGroupsList;
	global $time_based_rules, $filterdns;

	/* Use config lock to not allow recursion and config changes during this run. */
	$filterlck = lock('config');


	filter_pflog_start();
	update_filter_reload_status("Initializing");

	/* invalidate interface cache */
	get_interface_arr(true);

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}
	/* Get interface list to work with. */
	filter_generate_optcfg_array();
	if($g['booting'] == true)
		echo "Configuring firewall";

	/* Lookup Gateways to be used in filter rules once */
	$GatewaysList = return_gateways_array();
	$GatewayGroupsList = return_gateway_groups_array();

	/* generate aliases */
	if($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Creating aliases");
	$aliases = filter_generate_aliases();
	/* generate nat rules */
	if($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating NAT rules");
	$natrules = filter_nat_rules_generate();
	/* generate pfctl rules */
	if($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating filter rules");
	$pfrules = filter_rules_generate();
	/* generate altq, limiter */
	if($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating ALTQ queues");
	$altq_queues = filter_generate_altq_queues();
	update_filter_reload_status("Generating Limiter rules");
	$dummynet_rules = filter_generate_dummynet_rules();
	update_filter_reload_status("Generating Layer7 rules");
	generate_layer7_files();
	if($g['booting'] == true)
		echo ".";
	update_filter_reload_status("Loading filter rules");
	/* enable pf if we need to, otherwise disable */
	if(!isset ($config['system']['disablefilter'])) {
		mwexec("/sbin/pfctl -e", true);
	} else {
		mwexec("/sbin/pfctl -d");
		unlink_if_exists("{$g['tmp_path']}/filter_loading");
		update_filter_reload_status("Filter is disabled.  Not loading rules.");
		if($g['booting'] == true)
			echo "done.\n";
		unlock($filterlck);
		return;
	}

	// Copy rules.debug to rules.debug.old
	if(file_exists("{$g['tmp_path']}/rules.debug"))
		copy("{$g['tmp_path']}/rules.debug", "{$g['tmp_path']}/rules.debug.old");

	$rules = "";
	$rules .= "{$aliases} \n";
	update_filter_reload_status("Setting up logging information");
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
	} else {
	  $max_states = pfsense_default_state_size();
	  $rules .= "set limit states {$max_states}\n";
	}

	$rules .= discover_pkg_rules("pfearly");
	
	// Configure flowtable support if enabled.
	flowtable_configure();
		
	$rules .= "\n";
	$rules .= "set skip on pfsync0\n";
	$rules .= "\n";
	update_filter_reload_status("Setting up SCRUB information");
	$rules .= filter_generate_scrubing();
	$rules .= "\n";
	/* NOTE: Disabled until we catch up with dummynet changes. */
	//$rules .= "{$dummynet_rules}\n";
	$rules .= "{$altq_queues}\n";
	$rules .= "{$natrules}\n";
	$rules .= "{$pfrules}\n";
	
	$rules .= discover_pkg_rules("pflate");

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
		$line_error = split("\:", $rules_error);
		$line_number = $line_error[1];
		$line_split = file("{$g['tmp_path']}/rules.debug");
		if(is_array($line_split))
			$line_error = "The line in question reads [{$line_number}]: {$line_split[$line_number-1]}";
		if($line_error and $line_number) {
			file_notice("filter_load", "There were error(s) loading the rules: {$rules_error} {$line_error}", "Filter Reload", "");
			log_error("There were error(s) loading the rules: {$rules_error} - {$line_error}");
			update_filter_reload_status("There were error(s) loading the rules: {$rules_error} - {$line_error}");
			unlock($filterlck);
			return;
		}
		/* Brutal ugly hack but required -- PF is stuck, unwedge */
		if(strstr("$rules_error", "busy")) {
			exec("/sbin/pfctl -d");
			exec("/sbin/pfctl -e");
			exec("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
			file_notice("pf_busy", "PF was wedged/busy and has been reset.", "pf_busy", "");
			log_error("PF was wedged/busy and has been reset.");
		}
	}

	update_filter_reload_status("Starting up layer7 daemon");
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
		mwexec("/usr/local/sbin/filterdns {$g['tmp_path']}/filterdns.pid 300 {$g['varetc_path']}/filterdns.conf 1");
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

	unlock($filterlck);

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

	update_filter_reload_status("Processing down interface states");
	delete_states_for_down_gateways();

	update_filter_reload_status("Running plugins");

	if(is_dir("/usr/local/pkg/pf/")) {
		/* process packager manager custom rules */
		update_filter_reload_status("Running plugins (pf)");
		run_plugins("/usr/local/pkg/pf/");
		update_filter_reload_status("Plugins completed.");
	}

	update_filter_reload_status("Done");
	if($g['booting'] == true)
		echo "done.\n";

	return 0;
}

function filter_generate_scrubing() {
	global $config, $FilterIflist;
	$scrubrules = "";
	/* disable scrub option */
	foreach ($FilterIflist as $scrubif => $scrubcfg) {
		if(isset($scrubcfg['virtual'])) 
			continue;
		/* set up MSS clamping */
		if($scrubcfg['mtu'] <> "" && is_numeric($scrubcfg['mtu']) && $scrubcfg['if'] != "pppoe" && $scrubcfg['if'] != "pptp")
			$mssclamp = "max-mss " . (intval($scrubcfg['mtu'] - 40));
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
	
	$addresses = split(" ", $alias);
	$finallist = "";
	$builtlist = "";
	$aliasnesting[$name] = $name;
	foreach ($addresses as $address) {
		if (empty($address))
			continue;
		$linelength = strlen($builtlist);
		$tmpline = "";
		if(is_alias($address)) {
			/* We already expanded this alias so there is no neccessity to do it again. */
			if(!isset($aliasnesting[$address]))
				$tmpline = filter_generate_nested_alias($address, $aliastable[$address], $aliasnesting, $aliasaddrnesting);
		} else if(!isset($aliasaddrnesting[$address])) {
			if(!is_ipaddr($address) && !is_subnet($address) && !is_port($address)) {
				$filterdns .= "{$address} = {$name}\n";
				continue;
			}
			$aliasaddrnesting[$address] = $address;
			$tmpline = " $address";
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
	global $config, $FilterIflist;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_aliases() being called $mt\n";
	}
	$alias = "#System aliases\n ";
	$aliases = "loopback = \"{ lo0 }\"\n";

	foreach ($FilterIflist as $if => $ifcfg) {
		$aliases .= "{$ifcfg['descr']} = \"{ {$ifcfg['if']}";
		$aliases .= " }\"\n";
	}

	$aliases .= "\n#SSH Lockout Table\n";
	$aliases .= "table <sshlockout> persist\n";

	$aliases .= "#Snort2C table\n";
	$aliases .= "table <snort2c>\n";

	$aliases .= "\ntable <virusprot>\n";

	$aliases .= "\n# User Aliases \n";
	/* Setup pf groups */
	if(isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			$extraalias = "";
			$ip = find_interface_ip($aliased['address']);
			$extraalias = " " . link_ip_to_carp_interface($ip);
			$aliasnesting = array();
			$aliasaddrnesting = array();
			$addrlist = filter_generate_nested_alias($aliased['name'], $aliased['address'], $aliasnesting, $aliasaddrnesting);
			if($aliased['type'] == "host" || $aliased['type'] == "network" || $aliased['type'] == "url") {
				$tableaddrs = "{$addrlist}{$extralias}";
				if(empty($tableaddrs))
					$aliases .= "table <{$aliased['name']}> persist\n";
				else
					$aliases .= "table <{$aliased['name']}> { {$addrlist}{$extralias} } \n";
				
				$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
			} else if($aliased['type'] == "openvpn") {
				$openvpncfg = array();
				if($config['openvpn']['user']) {
					/* XXX: Check if we have a correct ip? */
					foreach ($config['openvpn']['user'] as $openvpn)
						$openvpncfg[$openvpn['name']] = $openvpn['ip'];
				}
				$vpn_lines = split("\n", $addrlist);
				foreach ($vpn_lines as $vpn_line) {
					$vpn_address_split = split(" ", $vpn_line);
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
			} elseif($aliased['type'] == "urltable") {
				$urlfn = alias_expand_urltable($aliased['name']);
				if ($urlfn) {
					$aliases .= "table <{$aliased['name']}> persist file \"{$urlfn}\"\n";
					$aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
				}
			} else 
				$aliases .= "{$aliased['name']} = \"{ {$aliased['address']}{$extralias} }\"\n";
		}
	}
	$result = "{$alias} \n";
	$result .= "{$aliases}";
	return $result;
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
					$vpns_arr[] = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
				}
			}
		}
	}
	/* openvpn */
	foreach (array('client', 'server') as $type) {
		if(is_array($config['openvpn']["openvpn-$type"])) {
			foreach ($config['openvpn']["openvpn-$type"] as & $settings) {
				if(is_array($settings)) {
					if(is_subnet($settings['remote-subnet'])) {
						$vpns_arr[] = $tunnel['remote_network'];
					}
				}
			}
		}
	}
	/* pppoe */
	if($config['pppoe']['remoteip']) {
		if(is_subnet($tunnel['remote-subnet'])) {
			$vpns_arr[] = $config['pppoe']['remoteip'] ."/". $config['pppoe']['pppoe_subnet'];
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
		if(!is_ipaddr($oc['ipaddr']) && !empty($oc['ipaddr']))
			$oic['type'] = $oc['ipaddr'];
		$oic['sn'] = get_interface_subnet($if);
		$oic['mtu'] = $oc['mtu'];
		$oic['descr'] = $ifdetail;
		$oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
		$oic['nonat'] = $oc['nonat'];
		$oic['alias-address'] = $oc['alias-address'];
		$oic['alias-subnet'] = $oc['alias-subnet'];
		$oic['gateway'] = $oc['gateway'];
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
	if($config['pppoe']['mode'] == "server") {
		$oic = array();
		$oic['if'] = 'pppoe';
		$oic['descr'] = 'pppoe';
		$oic['ip'] = $config['pppoe']['localip'];
		$oic['sa'] = $config['pppoe']['remoteip'];
		$oic['mode'] = $config['pppoe']['mode'];
		$oic['virtual'] = true;
		if($config['pppoe']['pppoe_subnet'] <> "")
			$oic['sn'] = $config['pppoe']['pppoe_subnet'];
		else
			$oic['sn'] = "32";
		$FilterIflist['pppoe'] = $oic;
	}
	/* add ipsec interfaces */
	if(isset($config['ipsec']['enable']) || isset($config['ipsec']['mobileclients']['enable'])) {
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

function filter_generate_reflection($rule, $extport, &$starting_localhost_port, &$reflection_txt) {
	global $FilterIflist, $config;

	// Initialize natrules holder string
	$natrules = "";
	$reflection_txt = array();

	if(!isset($config['system']['disablenatreflection'])) {
		if($config['system']['reflectiontimeout'])
			$reflectiontimeout = $config['system']['reflectiontimeout'];
		else
			$reflectiontimeout = "2000";

		update_filter_reload_status("Setting up NAT Reflection");

		$natrules .= "\n# Reflection redirects\n";
		$rdr_if_list = "";
		foreach ($FilterIflist as $ifent => $ifname) {
			/* do not process interfaces with gateways*/
			if(interface_has_gateway($ifent))
				continue;

			$rdr_if_list .= " " . $ifname['if'];
		}
		if (!empty($rdr_if_list))
			$rdr_if_list = "{ {$rdr_if_list} }";

		update_filter_reload_status("Creating reflection rule for {$rule['descr']}...");

		if($extport[1]) 
			$range_end = ($extport[1]);
		else
			$range_end = ($extport[0]);
		$range_end++;
		/* TODO: support multiple ip's in an alias. */
		if (is_alias($rule['external-address']))
			$extaddr = filter_expand_alias($rule['external-address']);
		else if(is_ipaddr($rule['external-address']))
			$extaddr = $rule['external-address'];
		else if (is_ipaddr($FilterIflist[$rule['interface']]['ip']))
			$extaddr = $FilterIflist[$rule['interface']]['ip'];
		else
			return "\n";

		if (is_alias($rule['target']))
			$target = filter_expand_alias($rule['target']);
		else if(is_ipaddr($rule['target']))
			$target = $rule['target'];
		else if (is_ipaddr($FilterIflist[$rule['target']]['ip']))
			$target = $FilterIflist[$rule['target']]['ip'];
		else
			return "\n";

		if($rule['local-port'])
			$lrange_start = $rule['local-port'];
		if($range_end - $extport[0] > 500) {
			$range_end = $extport[0]+1;
			log_error("Not installing nat reflection rules for a port range > 500");
		/* only install reflection rules for < 19991 items */
		} else if($starting_localhost_port < 19991) {
			$loc_pt = $lrange_start;
			$rflctnorange = true;
			if(is_alias($loc_pt)) {
				$loc_pt_translated = filter_expand_alias($loc_pt);
				if(!$loc_pt_translated) {
					log_error("Reflection processing: {$loc_pt} is not a vaild port alias.");
					continue;
				}
				$toadd_array = split(" ", $loc_pt_translated);
				$rflctnorange = false;
			}

			$inetdport = $starting_localhost_port;
			if(($range_end - 1) > $extport[0]) {
				$rflctrange = "{$starting_localhost_port}";
				$delta = $range_end - $extport[0] - 1;
				if(($starting_localhost_port + $delta) > 19990) {
                                       	log_error("Installing partial nat reflection rules. Maximum 1,000 reached.");
                                       	$delta = 19990 - $starting_localhost_port;
                               	}
				$starting_localhost_port = $starting_localhost_port + $delta;
				$rflctrange .= ":{$starting_localhost_port}";
				$rflctintrange = "{$extport[0]}:{$range_end}";
				if($rflctnorange)
					$toadd_array = range($loc_pt, $loc_pt + $delta);
			} else {
				$rflctrange = $starting_localhost_port;
				$rflctintrange = $extport[0];
				if($rflctnorange)
					$toadd_array = array($loc_pt);
				$starting_localhost_port++;
			}

			switch($rule['protocol']) {
			case "tcp/udp":
				$protocol = "{ tcp udp }";
				foreach($toadd_array as $tda) {
					$reflection_txt[] = "{$inetdport}\tstream\ttcp\tnowait/0\tnobody\t/usr/bin/nc\tnc -w {$reflectiontimeout} {$target} {$tda}\n";
					$reflection_txt[] = "{$inetdport}\tdgram\tudp\tnowait/0\tnobody\t/usr/bin/nc\tnc -u -w {$reflectiontimeout} {$target} {$tda}\n";
						$inetdport++;
					}
				$natrules .= "rdr on {$rdr_if_list} proto {$protocol} from any to {$extaddr} port {$rflctintrange} tag PFREFLECT -> 127.0.0.1 port {$rflctrange}\n";
				break;
			case "tcp":
			case "udp":
				$protocol = $rule['protocol'];
				if($protocol == "udp") {
					$socktype = "dgram";
					$dash_u = "-u ";
				} else {
					$socktype = "stream";
					$dash_u = "";
				}
				foreach($toadd_array as $tda){
					$reflection_txt[] = "{$inetdport}\t{$socktype}\t{$protocol}\tnowait/0\tnobody\t/usr/bin/nc\tnc {$dash_u}-w {$reflectiontimeout} {$target} {$tda}\n";
					$inetdport++;
				}
				$natrules .= "rdr on {$rdr_if_list} proto {$protocol} from any to {$extaddr} port {$rflctintrange} tag PFREFLECT -> 127.0.0.1 port {$rflctrange}\n";
				break;
			}
		}
		$reflection_txt = array_unique($reflection_txt);
	}

	return $natrules;
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if($if, $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false, $proto = "") {
	global $config;
	/* XXX: billm - any idea if this code is needed? */
	if($src == "/32" || $src{0} == "/")
		return "# src incorrectly specified\n";
	if($natip != "") {
		$tgt = "{$natip}/32";
	} else {
		$natip = get_interface_ip($if);
		if(is_ipaddr($natip))
			$tgt = "{$natip}/32";
		else
			$tgt = "($if)";
	}
	 /* Add the protocol, if defined */
	if (!empty($proto) && $proto != "any")
		$protocol = " proto {$proto}";
	else
		$protocol = "";
	/* Add the hard set source port (useful for ISAKMP) */
	if($natport != "")
		$tgt .= " port {$natport}";
	/* sometimes this gets called with "" instead of a value */
	if($src == "")
		$src = "any";
	/* Match on this source port */
	if($srcport != "")
		$src .= " port {$srcport}";
	/* sometimes this gets called with "" instead of a value */
	if($dst == "")
		$dst = "any";
	/* Match on this dest port */
	if($dstport != "")
		$dst .= " port {$dstport}";
	/* outgoing static-port option, hamachi, Grandstream, VOIP, etc */
	if($staticnatport)
		$staticnatport_txt = " static-port";
	else
		if(!$natport)
			$staticnatport_txt = " port 1024:65535"; // set source port range
		else
		 	$staticnatport_txt = "";
	/* Allow for negating NAT entries */
	if($nonat) {
		$nat = "no nat";
		$target = "";
		$staticnatport_txt = "";
	} else {
		$nat = "nat";
		$target = "-> {$tgt}";
	}
	$if_friendly = convert_friendly_interface_to_friendly_descr($if);
	/* Put all the pieces together */
	if($if_friendly)
		$natrule = "{$nat} on \${$if_friendly} {$protocol} from {$src} to {$dst} {$target}{$staticnatport_txt}\n";
	else
		$natrule .= "# Could not convert {$if} to friendly name(alias)\n";
	return $natrule;
}

function filter_nat_rules_generate() {
	global $config, $g, $after_filter_configure_run, $FilterIflist;

	$natrules = "nat-anchor \"natearly/*\"\n";
	
	$natrules .= "nat-anchor \"natrules/*\"\n\n";
	update_filter_reload_status("Creating 1:1 rules...");

	/* any 1:1 mappings? */
	if(is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if(!is_numeric($natent['subnet']))
				$sn = 32;
			else
				$sn = $natent['subnet'];
			if(!$natent['interface'])
				$natif = "wan";
			else 
				$natif = $natent['interface'];
			$natif = $FilterIflist[$natif]['if'];
			if($natif)
				$natrules .= "binat on $natif from {$natent['internal']}/{$sn} to any -> {$natent['external']}/{$sn}\n";
		}
	}
	$natrules .= "\n# Outbound NAT rules\n";
	/* outbound rules - advanced or standard */
	if(isset($config['nat']['advancedoutbound']['enable'])) {
		/* advanced outbound rules */
		if(is_array($config['nat']['advancedoutbound']['rule'])) {
			foreach ($config['nat']['advancedoutbound']['rule'] as $obent) {
				update_filter_reload_status("Creating advanced outbound rule {$obent['descr']}");
				$src = $obent['source']['network'];
				if(isset($obent['destination']['not']) && !isset($obent['destination']['any']))
					$dst = "!" . $obent['destination']['address'];
				else
					$dst = $obent['destination']['address'];
				if(!$obent['interface'])
					$natif = "wan";
				else 
					$natif = $obent['interface'];

				if (!isset($FilterIflist[$natif]))
					continue;

				$natrules .= filter_nat_rules_generate_if($natif,
					$src,
					$obent['sourceport'],
					$dst,
					$obent['dstport'],
					$obent['target'],
					$obent['natport'],
					isset($obent['nonat']),
					isset($obent['staticnatport']),
					$obent['protocol']
				);
			}
		}
	} else {
		/* standard outbound rules (one for each interface) */
		update_filter_reload_status("Creating outbound NAT rules");
		$tonathosts = "";
		$numberofnathosts = 0;

		if(is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				$netip = explode("/", $route['network']);
				if(is_array($config['gateways']['gateway_item'])) {
				        foreach($config['gateways']['gateway_item'] as $gateway) {
						if($route['gateway'] == $gateway['name']) {
							$gatewayip = $gateway['gateway'];
							$interfacegw = $gateway['interface'];
							if((! interface_has_gateway($gateway['interface'])) && (is_private_ip($netip[0]))) {
								$numberofnathosts++;
								$tonathosts .= "{$route['network']} ";
							}
						}
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
		if(isset($FilterIflist['pptp']) && $FilterIflist['pptp']['mode'] == "server" ) {
			$pptp_subnet = $FilterIflist['pptp']['sn'];
			if(is_private_ip($FilterIflist['pptp']['sa']) && !empty($pptp_subnet)) {
				$numberofnathosts++;
				$tonathosts .= "{$FilterIflist['pptp']['sa']}/{$pptp_subnet} ";
			}
		}
		/* PPPoE subnet */
		if(isset($FilterIflist['pppoe']) && $FilterIflist['pppoe']['mode'] == "server") {
			$pppoe_subnet = $FilterIflist['pppoe']['sn'];
			if(is_private_ip($FilterIflist['pppoe']['sa']) && !empty($pppoe_subnet)) {
				$numberofnathosts++;
				$tonathosts .= "{$FilterIflist['pppoe']['sa']}/{$pppoe_subnet} ";
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
		$natrules .= "\n# Subnets to NAT \n";
		if($numberofnathosts > 4) {
			$natrules .= "table <tonatsubnets> { {$tonathosts} }\n";
			$macroortable = "<tonatsubnets>";
		} else if($numberofnathosts > 0) { 
			$natrules .= "tonatsubnets	= \"{ {$tonathosts} }\"\n";
			$macroortable = "\$tonatsubnets";
		}
		if($numberofnathosts > 0):
			foreach ($FilterIflist as $if => $ifcfg) {
				update_filter_reload_status("Creating outbound rules {$if} - ({$ifcfg['descr']})");
				if(interface_has_gateway($if)) {
					$target = $ifcfg['ip'];
					/* create outbound nat entries for all local networks */
					$natrules .= filter_nat_rules_generate_if($if,
						"{$macroortable}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($if,
						"{$macroortable}", 5060, "", 5060, $target, 5060, false);
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

	update_filter_reload_status("Setting up TFTP helper");
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
		$natrules .= "table <vpns> { $vpns_list }\n";
	if($direct_networks_list) 
		$natrules .= "table <direct_networks> { $direct_networks_list }\n";

	/* DIAG: add ipv6 NAT, if requested */
	if(isset($config['diag']['ipv6nat']['enable']) &&
		is_ipaddr($config['diag']['ipv6nat']['ipaddr']) &&
		is_array($FilterIflist['wan'])) {
		/* XXX: FIX ME!	 IPV6 */
		$natrules .= "rdr on \${$FilterIflist['wan']['descr']} proto ipv6 from any to any -> {$config['diag']['ipv6nat']['ipaddr']}\n";
	}
	if(file_exists("/var/etc/inetd.conf"))
		mwexec("rm /var/etc/inetd.conf");
	touch("/var/etc/inetd.conf");
	// Open inetd.conf write handle
	$inetd_fd = fopen("/var/etc/inetd.conf","w");
	/* add tftp protocol helper */
	fwrite($inetd_fd, "tftp-proxy\tdgram\tudp\twait\t\troot\t/usr/local/sbin/tftp-proxy\ttftp-proxy -v\n");

	if(isset($config['nat']['rule'])) {
		if(!isset($config['system']['disablenatreflection'])) {
			/* start redirects on port 19000 of localhost */
			$starting_localhost_port = 19000;
		}
		$natrules .= "# NAT Inbound Redirects\n";
		foreach ($config['nat']['rule'] as $rule) {
			update_filter_reload_status("Creating NAT rule {$rule['descr']}");
			/* if item is an alias, expand */
			$extport = "";
			$extport[0] = alias_expand($rule['external-port']);
			if(!$extport[0])
				$extport = explode("-", $rule['external-port']);
			/* if item is an alias, expand */
			$localport = alias_expand($rule['local-port']);
			if(!$localport)
				$localport = "";
			else
				$localport = " port {$localport}";
			$target = alias_expand($rule['target']);
			if(!$target) {
				$natrules .= "# Unresolvable alias {$rule['target']}\n";
				continue;		/* unresolvable alias */
			}
			if($rule['associated-rule-id'] == "pass")
				$rdrpass = "pass";
			else
				$rdrpass = "";	
			if(!$rule['interface'])
                                $natif = "wan";
                        else
                                $natif = $rule['interface'];

			if (!isset($FilterIflist[$natif]))
				continue;

			$extaddr = alias_expand($rule['external-address']);
			if($rule['external-address'] == "any")
				$extaddr = "any";
			if(!$extaddr)
				$extaddr = $FilterIflist[$natif]['ip'];
			$natif = $FilterIflist[$natif]['if'];

			if($extaddr <> "") {
				/* is rule a port range? */
				if((!$extport[1]) || ($extport[0] == $extport[1])) {
					
					switch ($rule['protocol']) {
						case "tcp/udp":
							if($natif) {
								if($rule['external-port'] <> $rule['local-port'])
									$natrules .= "{$nordr} rdr {$rdrpass} on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]} -> {$target}{$localport}";
								else
									$natrules .= "{$nordr} rdr {$rdrpass} on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]} -> {$target}";
							}
							break;
						case "udp":
						case "tcp":
							if($extport[0])
								if($natif) {
									if($rule['external-port'] <> $rule['local-port'])
										$natrules .= "rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} port {$extport[0]} -> {$target}{$localport}";
									else
										$natrules .= "rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} port {$extport[0]} -> {$target}";
								}
							else
								if($natif)
									$natrules .= "rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}{$localport}";
							break;
						default:
							$natrules .= "rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}";
							break;
					}
				} else {
					switch ($rule['protocol']) {
						case "tcp/udp":
							if($natif)
								$natrules .= "{$nordr} rdr {$rdrpass} on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target}{$localport}:*";
							break;
						case "udp":
						case "tcp":
							if($natif)
								$natrules .= "{$nordr} rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target}{$localport}:*";
							break;
						default:
							if($natif)
								$natrules .= "{$nordr} rdr {$rdrpass} on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}";
					}
				}
				/* Does this rule redirect back to a internal host? */
				if($extaddr == "any" && !interface_has_gateway($rule['interface'])) {
					$rule_interface_ip = find_interface_ip($natif);
					$rule_interface_subnet = find_interface_subnet($natif);
					$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
					$natrules .= "\n";
					$natrules .= "no nat on {$natif} proto tcp from ({$natif}) to {$rule_subnet}/{$rule_interface_subnet}\n";
					$natrules .= "nat on {$natif} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$extport[0]} -> ({$natif})\n";
				}
				$natrules .= filter_generate_reflection($rule, $extport, $starting_localhost_port, $reflection_rules);
				$natrules .= "\n";

				foreach ($reflection_rules as $txtline)
                        		fwrite($inetd_fd, $txtline);
			}
		}
	}
	fclose($inetd_fd);		// Close file handle
	// Check if inetd is running, if not start it.	If so, restart it gracefully.
	$helpers = isvalidproc("inetd");
	if(!$helpers)
		mwexec("/usr/sbin/inetd -wW -R 0 -a 127.0.0.1 /var/etc/inetd.conf");
	else
		sigkillbypid("/var/run/inetd.pid", "HUP");

	if($pptpdcfg['mode'] && $pptpdcfg['mode'] != "off") {
		if($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else if($pptpdcfg['mode'] == "redir")
			$pptpdtarget = $pptpdcfg['redir'];
		if($pptpdcfg['mode'] == "redir" && is_array($FilterIflist['wan'])) {
			/* 
			 * NB: ermal -- the rdr rule below is commented out now that we have a solution
			 *		for PPTP passthrough. This unbreaks other GRE traffic passing  
			 *		through pfSense. 
			 *		After some more testing this will be removed compeletely.
			 */ 
			$natrules .= <<<EOD

# PPTP
rdr on \${$FilterIflist['wan']['descr']} proto gre from any to any -> $pptpdtarget
rdr on \${$FilterIflist['wan']['descr']} proto tcp from any to any port 1723 -> $pptpdtarget

EOD;
		}
	}

	$natrules .= discover_pkg_rules("nat");

	$natrules .= filter_process_carp_nat_rules();
	$natrules .= "# UPnPd rdr anchor\n";
	$natrules .= "rdr-anchor \"miniupnpd\"\n";

	return $natrules;
}

function filter_generate_user_rule_arr($rule) {
	global $config;
	update_filter_reload_status("Creating filter rule {$rule['descr']} ...");
	$ret = array();
	$line = filter_generate_user_rule($rule);
	$ret['rule'] = $line;
	$ret['interface'] = $rule['interface'];
	if($rule['descr'] != "" and $line != "")
		$ret['descr'] = "label \"USER_RULE: " . str_replace('"', '', $rule['descr']) . "\"";
	else
		$ret['descr'] = "label \"USER_RULE\"";

	return $ret;
}

function filter_generate_address(& $rule, $target = "source") {
	global $FilterIflist;
	$src = "";

	if(isset($rule[$target]['any'])) {
                $src = "any";
        } else if($rule[$target]['network']) {
                if(strstr($rule[$target]['network'], "opt")) {
			$optmatch = ""; 
			$matches = "";
			if(preg_match("/opt([0-9]*)/", $rule[$target]['network'], $optmatch)) {
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
                                                $pptpsa = gen_subnet($FilterIflist['pptp']['ip'], $FilterIflist['pptp']['sn']);
                                                $pptpsn = $FilterIflist['pptp']['sn'];
                                                $src = "{$pptpsa}/{$pptpsn}";
                                                break;
                                        case 'pppoe':
                                                $pppoesa = gen_subnet($FilterIflist['pppoe']['ip'], $FilterIflist['pppoe']['sn']);
                                                $pppoesn = $FilterIflist['pppoe']['sn'];
                                                $src = "{$pppoesa}/{$pppoesn}";
                                                break;
                                }
                                if(isset($rule[$target]['not'])) $src = "!{$src}";
                        }
	} else if($rule[$target]['address']) {
                $expsrc = alias_expand($rule[$target]['address']);
                if(isset($rule[$target]['not']))
                        $not = "!";
                else
                        $not = "";
		$src = " {$not} {$expsrc}";
        }

	if(in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
                if($rule[$target]['port']) {
                        $srcport = explode("-", $rule[$target]['port']);
			$srcporta = alias_expand($srcport[0]);
			if(!$srcporta)
				log_error("filter_generate_address: {$srcport[0]} is not a valid source port.");
                        else if((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
				$src .= " port {$srcporta} ";
                        } else if(($srcport[0] == 1) && ($srcport[1] == 65535)) {
                                /* no need for a port statement here */
                        } else if(is_port($srcporta) && $srcport[1] == 65535) {
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

	return $src;
}

function filter_generate_user_rule($rule) {
	global $config, $g, $FilterIflist, $GatewaysList, $GatewayGroupsList;
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
	$pppoecfg = $config['pppoe'];
	$int = "";
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
					$aline['interface'] = " on { {$ifliste} }";
				else
					$aline['interface'] = "";
			}
			else
				$aline['interface'] = "";
	} else if(!array_key_exists($rule['interface'], $FilterIflist)) {
			foreach($FilterIflist as $oc) $item .= $oc['descr'];
				return "# {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
	} else
		$aline['interface'] = " on \$" . $FilterIflist[$rule['interface']]['descr'] . " ";
	$ifcfg = $FilterIflist[$rule['interface']];
	if($pptpdcfg['mode'] != "server") {
		if(($rule['source']['network'] == "pptp") ||
			($rule['destination']['network'] == "pptp")) 
				return "# source network or destination network == pptp on " . $rule['descr'];
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
	if($type != "pass" && $type != "block" && $type != "reject") {
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
	update_filter_reload_status("Setting up pass/block rules {$rule['descr']}");

	/* do not process reply-to for gateway'd rules */
	if($rule['gateway'] == "" && interface_has_gateway($rule['interface']) && !isset($rule['disablereplyto'])) {
		$rg = get_interface_gateway($rule['interface']);
		if(is_ipaddr($rg)) {
			$aline['reply'] = "reply-to ( {$ifcfg['if']} {$rg} ) ";
		} else {
			if($rule['interface'] <> "pptp") {
				log_error("Could not find gateway for interface({$rule['interface']}).");
			}
		}
	}
	/* if user has selected a custom gateway, lets work with it */
	else if($rule['gateway'] <> "") {
		$foundlb = 0;
		$routeto = " route-to { ";
		update_filter_reload_status("Creating gateway group item...");
		if(is_array($GatewayGroupsList[$rule['gateway']])) {
			$gateway = $rule['gateway'];
			$members = $GatewayGroupsList[$rule['gateway']];
			$member_count = count($members);
			foreach($members as $member) {
				$int = $member['int'];
				$gatewayip = $member['gwip'];
				if(($int <> "") && is_ipaddr($gatewayip)) {
					if($g['debug'])
						log_error("Setting up route with {$gatewayip} om $int");
					if($foundlb == 1)
						$routeto .= ", ";
					$routeto .=	 "( {$int} {$gatewayip} ) ";
					$foundlb = 1;
				} else {
					log_error("An error occurred while trying to find the interface got $gatewayip .  The rule has not been added.");
				}
			}
			/* If we want failover just use route-to else round-robin */
			if($member_count == 1) {
				$routeto .= "} ";
			} else {
				$routeto .= "} round-robin ";
				if(isset($config['system']['lb_use_sticky'])) 
					$routeto .= " sticky-address ";									
			}
		}
		/* Add the load balanced gateways */
		if($foundlb == 1)
			$aline['route'] = $routeto;

		/* we're not using load balancing, just setup gateway */
		else if($foundlb == 0) {
			$gateway = $rule['gateway'];
			if(!is_ipaddr($gateway)) {
				$gwip = $GatewaysList[$gateway]['gateway'];
				if($GatewaysList[$gateway]['interface'])
					$int = $GatewaysList[$gateway]['interface'];
				else
					$int = "";
			} else {
				$gwip = $gateway;
				$int = guess_interface_from_ip($gwip);
			}
			if((is_ipaddr($gwip)) && ($int <> "")) {
				$aline['route'] = " route-to ( {$int} {$gwip} ) ";
			} else {
				log_error("Could not find gateway ({$rule['gateway']}) for rule {$rule['descr']} - {$rule['interface']}.");
			}
		}
	}

	if(isset($rule['protocol'])) {
		if($rule['protocol'] == "tcp/udp")
			$aline['prot'] = " proto { tcp udp } ";
		elseif($rule['protocol'] == "icmp")
			$aline['prot'] = " inet proto icmp ";
		else
			$aline['prot'] = " proto {$rule['protocol']} ";
	} else {
		if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "")
			$aline['prot'] = " proto tcp ";
	}
	update_filter_reload_status("Creating rule {$rule['descr']}");
	
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
	if(($rule['protocol'] == "icmp") && $rule['icmptype'])
		$aline['icmp-type'] = "icmp-type {$rule['icmptype']} ";
	if(!empty($rule['tag']))
		$aline['tag'] = " tag " .$rule['tag']. " ";
	if(!empty($rule['tagged']))
		$aline['tagged'] = " tagged " .$rule['tagged'] . " ";
	if(!empty($rule['dscp']))
		$aline['dscp'] = " dscp " . $rule['dscp'] . " ";
	if($type == "pass") {
		if(isset($rule['allowopts']))
			$aline['allowopts'] = " allow-opts ";
		if( isset($rule['source-track']) or isset($rule['max']) or isset($rule['max-src-nodes']) or isset($rule['max-src-conn']) or isset($rule['max-src-states']) )
			if($rule['protocol'] == "tcp")
				$aline['flags'] = "flags S/SA ";
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
					$aline['flags'] = " no state ";
					break;
				case "modulate state":
				case "synproxy state":
					if($rule['protocol'] == "tcp")
						$aline['flags'] = "{$rule['statetype']} ";
					break;
				default:
					$aline['flags'] = "{$rule['statetype']} ";
			}
		} else
			$aline['flags'] = "keep state ";

		if($noadvoptions == false || $l7_present)
			if( isset($rule['source-track']) and $rule['source-track'] <> "" or
			    	isset($rule['max']) and $rule['max'] <> "" or
				isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "" or
				isset($rule['max-src-conn']) and $rule['max-src-conn'] <> "" or
				isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "" or
				isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "" or
				isset($rule['max-src-states']) and $rule['max-src-states'] <> "" or
				isset($rule['statetimeout']) and $rule['statetimeout'] <> "" or
				$l7_present) {
					$aline['flags'] .= "( ";
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
					if(isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> ""
				   	 and isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "") {
						$aline['flags'] .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";
						$aline['flags'] .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
					}
					if(!empty($aline['divert'])) 
						$aline['flags'] .= "max-packets 5 ";
					
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
	if(($aline['route'] <> "") && (trim($aline['type']) == "pass") && strstr($dst, "any")) {
		/* negate VPN/PPTP/PPPoE networks for load balancer/gateway rules */
		$vpns = " to <vpns> ";
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] .
			$aline['interface'] . $aline['prot'] . $aline['src'] . $aline['os'] .
			$vpns . $aline['icmp-type'] . $aline['tag'] . $aline['tagged'] .
			$aline['dscp'] . $aline['allowopts'] . $aline['flags'] .
			$aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'] .
			" label \"NEGATE_ROUTE: Negate policy route for vpn(s)\"\n";

	}
	/* piece together the actual user rule */
	$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] .
		$aline['reply'] . $aline['route'] . $aline['prot'] . $aline['src'] . $aline['os'] . $aline['dst'] .
		$aline['divert'] . $aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] .
		$aline['allowopts'] . $aline['flags'] . $aline['queue'] . $aline['dnpipe'] . $aline['schedlabel'];
		

	return $line;
}

function filter_rules_generate() {
	global $config, $g, $FilterIflist, $time_based_rules;
	
	update_filter_reload_status("Creating default rules");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];

	$ipfrules .= discover_pkg_rules("filter");

	/* if captive portal is enabled, ensure that access to this port
	 * is allowed on a locked down interface
	 */
	if(isset($config['captiveportal']['enable'])) {
		$cpinterfaces = explode(",", $config['captiveportal']['interface']);
                $cpiflist = array();
		$cpiplist = array();
                foreach ($cpinterfaces as $cpifgrp) {
                        if(!isset($FilterIflist[$cpifgrp]))
                                continue;
                        $tmpif = get_real_interface($cpifgrp);
                        if(!empty($tmpif)) {
                                $cpiflist[] = "{$tmpif}";
                                $cpipm = get_interface_ip($cpifgrp);
                                if(is_ipaddr($cpipm))
                                        $cpiplist[] = $cpipm;
                        }
                }
		 if (count($cpiplist) > 0 && count($cpiflist) > 0) {
                        $cpinterface = implode(" ", $cpiflist);
                       $cpaddresses = implode(" ", $cpiplist);
                       $ipfrules .= "pass in quick on { {$cpinterface} } proto tcp from any to { {$cpaddresses} } port { 8000 8001 } keep state(sloppy)\n";
                       $ipfrules .= "pass out quick on { {$cpinterface} } proto tcp from { {$cpaddresses} } port { 8000 8001 } to any keep state(sloppy)\n";
                }
	}
	/* relayd */
	$ipfrules .= "anchor \"relayd/*\"\n";
	# BEGIN OF firewall rules
	$ipfrules .= "anchor \"firewallrules\"\n";
	/* default block logging? */
	if(!isset($config['syslog']['nologdefaultblock']))
		$log = "log";
	else
		$log = "";
	$ipfrules .= <<<EOD
#---------------------------------------------------------------------------
# default deny rules
#---------------------------------------------------------------------------
block in $log all label "Default deny rule"
block out $log all label "Default deny rule"

# We use the mighty pf, we cannot be fooled.
block quick proto { tcp, udp } from any port = 0 to any
block quick proto { tcp, udp } from any to any port = 0


EOD;

        if(!isset($config['system']['ipv6allow'])) {
                $ipfrules .= "# Block all IPv6\n";
                $ipfrules .= "block in quick inet6 all\n";
                $ipfrules .= "block out quick inet6 all\n";
        }

        $ipfrules .= <<<EOD
	
# snort2c
block quick from <snort2c> to any label "Block snort2c hosts"
block quick from any to <snort2c> label "Block snort2c hosts"

# package manager early specific hook
anchor "packageearly"

EOD;

	$ipfrules .= <<<EOD

# carp
anchor "carp"

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
		$ipfrules .= "block in log quick proto tcp from <sshlockout> to any port {$sshport} label \"sshlockout\"\n";
	}

	/*
	 * Support for allow limiting of TCP connections by establishment rate
	 * Useful for protecting against sudden outburts, etc.
	 */
	$ipfrules .= "block in quick from <virusprot> to any label \"virusprot overload table\"\n";

	$bogontableinstalled = 0;
	foreach ($FilterIflist as $on => $oc) {
		/* block bogon networks */
		/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
		/* file is automatically in cron every 3000 minutes */
		if(isset($config['interfaces'][$on]['blockbogons'])) {
			if($bogontableinstalled == 0)
				$ipfrules .= "table <bogons> persist file \"/etc/bogons\"\n";
			$ipfrules .= <<<EOD
# block bogon networks
# http://www.cymru.com/Documents/bogon-bn-nonagg.txt
anchor "{$on}bogons"
block in $log quick on \${$oc['descr']} from <bogons> to any label "block bogon networks from {$oc['descr']}"

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
block in $log quick on \${$oc['descr']} from 10.0.0.0/8 to any label "block private networks from wan block 10/8"
block in $log quick on \${$oc['descr']} from 127.0.0.0/8 to any label "block private networks from wan block 127/8"
block in $log quick on \${$oc['descr']} from 172.16.0.0/12 to any label "block private networks from wan block 172.16/12"
block in $log quick on \${$oc['descr']} from 192.168.0.0/16 to any label "block private networks from wan block 192.168/16"

EOD;
			}
		}
		switch ($oc['type']) {
		case "pptp":
				/* XXX: The proto gre rules should really be removed when the pptp patch is guaranted to work */
				$ipfrules .= <<<EOD
# allow PPTP client
anchor "pptpclient"
pass in on \${$oc['descr']} proto gre from any to any modulate state label "allow PPTP client"
pass in on \${$oc['descr']} proto tcp from any to any port = 1723 flags S/SA modulate state label "allow PPTP client on {$oc['descr']}"

EOD;
			break;
		case "dhcp":
		case "carpdev-dhcp":
			$ipfrules .= <<<EOD
# allow our DHCP client out to the {$oc['descr']}
anchor "{$on}dhcp"
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
anchor "dhcpserver{$oc['descr']}"
pass in on \${$oc['descr']} proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server"
pass in on \${$oc['descr']} proto udp from any port = 68 to {$oc['ip']} port = 67 label "allow access to DHCP server"
pass out on \${$oc['descr']} proto udp from {$oc['ip']} port = 67 to any port = 68 label "allow access to DHCP server"

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
anchor "spoofing"

# loopback
anchor "loopback"
pass in on \$loopback all label "pass loopback"
pass out on \$loopback all label "pass loopback"

anchor "firewallout"

EOD;

	$ipfrules .= <<<EOD
# let out anything from the firewall host itself and decrypted IPsec traffic
pass out all keep state allow-opts label "let out anything from firewall host itself"

EOD;
	foreach ($FilterIflist as $ifdescr => $ifcfg) {
                if(isset($ifcfg['virtual']))
                        continue;
		$gw = get_interface_gateway($ifdescr);
		if (is_ipaddr($gw) && is_ipaddr($ifcfg['ip']))
                	$ipfrules .= "pass out route-to ( {$ifcfg['if']} {$gw} ) from {$ifcfg['ip']} to !{$ifcfg['sa']}/{$ifcfg['sn']} keep state allow-opts label \"let out anything from firewall host itself\"\n";
        }


	/* add ipsec interfaces */
	if(isset($config['ipsec']['enable']) || isset($config['ipsec']['mobileclients']['enable'])) 
		$ipfrules .= <<<EOD
pass out on \$IPsec all keep state label "IPsec internal host to host"

EOD;

	/*  pass traffic between statically routed subnets and the subnet on the
	 *  interface in question to avoid problems with complicated routing
	 *  topologies 
	 */
	if(isset($config['filter']['bypassstaticroutes']) && is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		$ipfrules .= "anchor \"staticrouted\" \n";
		foreach ($config['staticroutes']['route'] as $route) {
			$realif = guess_interface_from_ip(lookup_gateway_ip_by_name($route['gateway']));
			$friendly = convert_real_interface_to_friendly_interface_name($realif);
			if(is_array($FilterIflist[$friendly])) {
				$oc = $FilterIflist[$friendly];
				if($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
				if($sa) {
				$ipfrules .= <<<EOD
pass in quick on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} no state label "pass traffic between statically routed subnets"
pass in quick on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} no state label "pass traffic between statically routed subnets"
pass out quick on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} no state label "pass traffic between statically routed subnets"
pass out quick on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} no state label "pass traffic between statically routed subnets"

EOD;
				}
			}
		}
	}
	if(!isset($config['system']['webgui']['noantilockout'])) {
		if(count($config['interfaces']) > 1 && !empty($FilterIflist['lan']['if'])) {
				/* if antilockout is enabled, LAN exists and has
				 * an IP and subnet mask assigned 
				 */
				$lanif = $FilterIflist["lan"]['if'];
				$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on {$lanif} from any to ({$lanif}) keep state label "anti-lockout rule"

EOD;
		} else if (count($config['interfaces']) == 1) {
			/* single-interface deployment, add to WAN	*/
			$wanif = $FilterIflist["wan"]['if'];
			$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on {$wanif} from any to ({$wanif}) keep state label "anti-lockout rule"

EOD;
		}
	 }
	/* PPTPd enabled? */
	if($pptpdcfg['mode'] && ($pptpdcfg['mode'] != "off")) {
		if($pptpdcfg['mode'] == "server")
			$pptpdtarget = get_interface_ip();
		else
			$pptpdtarget = $pptpdcfg['redir'];
		if(is_ipaddr($pptpdtarget) and is_array($FilterIflist['wan'])) {
			$ipfrules .= <<<EOD
# PPTPd rules
anchor "pptp"
pass in on \${$FilterIflist['wan']['descr']} proto gre from any to $pptpdtarget keep state label "allow gre pptpd"
pass in on \${$FilterIflist['wan']['descr']} proto tcp from any to $pptpdtarget port = 1723 modulate state label "allow pptpd {$pptpdtarget}"

EOD;

		} else {
			/*	  this shouldnt ever happen but instead of breaking the clients ruleset
			 *	  log an error.
			 */
			log_error("ERROR!  PPTP enabled but could not resolve the \$pptpdtarget");
		}
	}

	$ipfrules .= "# NAT Reflection rules\n";
	if(isset($config['nat']['rule']) &&
		(!isset($config['system']['disablenatreflection']))) {
		$ipfrules .= <<<EOD
pass in inet tagged PFREFLECT keep state label "NAT REFLECT: Allow traffic to localhost"

EOD;
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
		/* Generate user rule lines */
		foreach($rule_arr as $rule) {
			if(isset($rule['disabled']))
				continue;
			if(!$rule['rule'])
				continue;
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
	}

	update_filter_reload_status("Creating IPsec rules...");
	$ipfrules .= filter_generate_ipsec_rules();

        $ipfrules .= <<<EOD
# package manager late specific hook
anchor "packagelate"

anchor "tftp-proxy/*"

anchor "limitingesr"

# uPnPd
anchor "miniupnpd"

EOD;
	
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
	log_error("Please use filter_tdr_install_cron() function tdr_install_cron will be deprecated!");
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
                                write_config("Installed 15 minute filter reload for Time Based Rules");
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
                if($timeday['desc'])
                        $desc = $timeday['desc'];
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
        $weekday        = date("w");
        if($weekday == 0)
                $weekday = 7;
        $date           = date("d");
        $defined_days = split(",", $schedule);
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
        $tmp = split("-", $schedule);
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
        $schedule_days = split(",", $schedule);
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
        $months = split(",", $schedule);
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
	foreach ($FilterIflist as $ifdescr => $ifcfg) {
		/* 
		 * XXX: This should be cleared out after a discussion
		 * between pf(4) devs is cleared out. This breaks 
		 * compatibility with OpenBSD.
		 */ 
		if(isset($ifcfg['virtual']))
                        continue;
		$rules .= "set loginterface {$ifcfg['if']}\n";
	}
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
		$lines .= "pass quick proto carp\n";
		$lines .= "pass quick proto pfsync\n";
	}
	return $lines;
}

/* Generate IPSEC Filter Items */
function filter_generate_ipsec_rules() {
	global $config, $g, $FilterIflist, $GatewaysList;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_ipsec_rules() being called $mt\n";
	}

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
				$shorttunneldescr = substr($descr, 0, 36);
				$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto udp from any to {$rgip} port = 500 keep state label \"IPsec: {$shorttunneldescr} - outbound isakmp\"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to any port = 500 keep state label \"IPsec: {$shorttunneldescr} - inbound isakmp\"

EOD;
				/* If NAT-T is enabled, add additional rules */
				if($ph1ent['nat_traversal'] != "off" ) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto udp from any to {$rgip} port = 4500 keep state label \"IPsec: {$shorttunneldescr} - outbound nat-t\"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto udp from {$rgip} to any port = 4500 keep state label \"IPsec: {$shorttunneldescr} - inbound nat-t\"

EOD;
				}
				/* Add rules to allow the protocols in use */
				if($prot_used_esp == true) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto esp from any to {$rgip} keep state label \"IPsec: {$shorttunneldescr} - outbound esp proto\"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto esp from {$rgip} to any keep state label \"IPsec: {$shorttunneldescr} - inbound esp proto\"

EOD;
				}
				if($prot_used_ah == true) {
					$ipfrules .= <<<EOD
pass out on \${$FilterIflist[$parentinterface]['descr']} $route_to proto ah from any to {$rgip} keep state label \"IPsec: {$shorttunneldescr} - outbound ah proto\"
pass in on \${$FilterIflist[$parentinterface]['descr']} $reply_to proto ah from {$rgip} to any keep state label \"IPsec: {$shorttunneldescr} - inbound ah proto\"

EOD;
				}
			}
		}
	}
	return($ipfrules);
}

function discover_pkg_rules($ruletype) {
	global $config, $g;
	if(!is_dir("/usr/local/pkg")) 
		return "";
	$files = split("\n", trim(`ls /usr/local/pkg/*.inc`));
	foreach($files as $pkg_inc) {
		if($pkg_inc == "ls: No match.") 
			continue;
		update_filter_reload_status("Checking for {$ruletype} PF hooks in package {$pkg_inc}");
		require_once($pkg_inc);
		$pkg = basename($pkg_inc, ".inc");
		$pkg_generate_rules = "{$pkg}_generate_rules";
		if(function_exists($pkg_generate_rules)) {
			update_filter_reload_status("Processing early {$ruletype} rules for package {$pkg_inc}");
			$rules .= $pkg_generate_rules("$ruletype");
			file_put_contents("{$g['tmp_path']}/rules.packages", $rules);
			$status = mwexec("/sbin/pfctl -nf {$g['tmp_path']}/rules.packages");
			if ($status <> 0) {
				$errorrules = "There was an error while parsing the package filter rules for {$pkg_inc}.\n";
				log_error($errorrules);
				file_put_contents("{$g['tmp_path']}/rules.packages.{$pkg_inc}", "#{$errorrules}\n");
				return "";
			}
		}
	}
	return $rules;
}

?>
