<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004-2006 Scott Ullrich
        Copyright (C) 2005 	Bill Marquette
	Copyright (C) 2006 	Peter Allgeyer
	Copyright (C) 2008 	Ermal Lu\xe7i
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

/* include all configuration functions */
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("notices.inc");
require_once ("shaper.inc");

/* holds the items that will be executed *AFTER* the filter is fully loaded */
$after_filter_configure_run = array();
/*  hold the ports being used by ftp-prxy to install the behind the scenes rule that 
 *  forces traffic out the primary wan until multi-wan ftp-proxy is all the rage.
 */
$used_pftpx_ports = array();

function filter_pflog_start() {
	global $config, $g;
	
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}

	mute_kernel_msgs();

	$pid = `ps awwwux | grep -v "grep" | grep "tcpdump -v -l -n -e -ttt -i pflog0"  | awk '{ print $2 }'`;
	if(!$pid)
		mwexec_bg("/usr/sbin/tcpdump -v -l -n -e -ttt -i pflog0 | logger -t pf -p local0.info");

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

/* reload filter sync */
function filter_configure_sync() {
	global $config, $g, $after_filter_configure_run;
	filter_pflog_start();
	update_filter_reload_status("Initializing");
	/* invalidate interface cache */
	get_interface_arr(true);
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}

	/* load ipfw / dummynet early on if required */
	if($config['system']['dummynetshaper']) {
			$status = intval(`kldstat | grep ipfw | wc -l | awk '{ print $1 }'`);
			if($status == "0") {
				mwexec("/sbin/kldload ipfw");
				mwexec("/sbin/kldload dummynet");
			}
		} else {	
			/* check to see if any rules reference a schedule
			 * and if so load ipfw for later usage.
			 */
			foreach($config['filter']['rule'] as $rule) {
				if($rule['sched'])
					$time_based_rules = true;
			}
			if($time_based_rules == true) {
				$status = intval(`kldstat | grep ipfw | wc -l | awk '{ print $1 }'`);
				if($status == "0") {
					mute_kernel_msgs();
					mwexec("/sbin/kldload ipfw");
					unmute_kernel_msgs();
				}
				exec("/sbin/ipfw delete set 9");
				exec("/sbin/ipfw delete 2");
				exec("/sbin/ipfw delete 3");			
			}
	}

	$lan_if = $config['interfaces']['lan']['if'];
	$wan_if = get_real_wan_interface();

	/* generate aliases */
	if($g['booting'] == true) echo ".";
	update_filter_reload_status("Creating aliases");
	$aliases = filter_generate_aliases();
	/* generate nat rules */
	  if($g['booting'] == true) echo ".";
	update_filter_reload_status("Generating NAT rules");
	$natrules = filter_nat_rules_generate();
	/* generate pfctl rules */
	if($g['booting'] == true) echo ".";
	update_filter_reload_status("Generating filter rules");
	$pfrules = filter_rules_generate();
	/* generate altq */
	if($g['booting'] == true) echo ".";
	update_filter_reload_status("Generating ALTQ queues");
	$altq_queues = filter_generate_altq_queues();

	update_filter_reload_status("Loading filter rules");

	/* enable pf if we need to, otherwise disable */
	if (!isset ($config['system']['disablefilter'])) {
		mwexec("/sbin/pfctl -e");
	} else {
		mwexec("/sbin/pfctl -d");
		unlink_if_exists("{$g['tmp_path']}/filter_loading");
		update_filter_reload_status("Filter is disabled.  Not loading rules.");
		return;
	}

	$fd = fopen("{$g['tmp_path']}/rules.debug", "w");
	$rules = $aliases . " \n";

	update_filter_reload_status("Setting up logging information");

	$rules .= setup_logging_interfaces();

	if ($config['system']['optimization'] <> "")
		$rules .= "set optimization {$config['system']['optimization']}\n";
	else
		$rules .= "set optimization normal\n";

	if ($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* User defined maximum states in Advanced menu. */
		$rules .= "set limit states {$config['system']['maximumstates']}\n";
	} else {
	  $max_states = pfsense_default_state_size();
	  $rules .= "set limit states {$max_states}\n";
	}
	$rules .= "\n";

	update_filter_reload_status("Setting up SCRUB information");
	/* get our wan interface? */
	$wanif = get_real_wan_interface();

	/* disable scrub option */
	if(!isset($config['system']['disablescrub'])) {
		/* set up MSS clamping */
		if ($config['interfaces']['wan']['mtu'] <> "" and is_numeric($config['interfaces']['wan']['mtu']))
			$mssclamp = "max-mss " . (intval($config['interfaces']['wan']['mtu'] - 40));
		else
			if ($config['interfaces']['wan']['ipaddr'] == "pppoe")
				$mssclamp = "max-mss 1452";
			else
				$mssclamp = "";
	
		/* configure no-df for linux nfs and others */
		if ($config['system']['scrubnodf'])
			$scrubnodf = "no-df random-id";
		else
			$scrubnodf = "random-id";
		$rules .= "scrub all {$scrubnodf} {$mssclamp} fragment reassemble\n"; // reassemble all directions
	} else if ($config['interfaces']['wan']['mtu'] <> "" and is_numeric($config['interfaces']['wan']['mtu'])) {
		$rules .= "scrub {$mssclamp}\n"; // reassemble all directions
	}

	$rules.= "{$altq_queues}\n";
	$rules.= "{$natrules}\n";
	$rules.= "{$pfrules}\n";
	fwrite($fd, $rules);
        fclose($fd);

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "pfctl being called at $mt\n";
	}
        $rules_loading = mwexec("/sbin/pfctl -o -f {$g['tmp_path']}/rules.debug");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "pfctl done at $mt\n";
	}

	/* check for a error while loading the rules file.  if an error has occured
	   then output the contents of the error to the caller */
	if($rules_loading <> 0) {
		$rules_error = exec_command("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
		$line_error = split("\:", $rules_error);
		$line_number = $line_error[1];
		$rules_file = `/bin/cat {$g['tmp_path']}/rules.debug`;
		$line_split = split("\n", $rules_file);
		if(is_array($line_split))
			$line_error = "The line in question reads [{$line_number}]: {$line_split[$line_number-1]}";
		if($line_error and $line_number) {
			file_notice("filter_load", "There were error(s) loading the rules: {$rules_error} {$line_error}", "Filter Reload", "");
			log_error("There were error(s) loading the rules: {$rules_error} - {$line_error}");
			update_filter_reload_status("There were error(s) loading the rules: {$rules_error} - {$line_error}");
			return;
		}
	}

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");

	/* run items scheduled for after filter configure run */
	foreach($after_filter_configure_run as $afcr) {
		$fda = fopen("/tmp/commands.txt", "w");
		fwrite($fda, $afcr . "\n");
		fclose($fda);
	}
	if(file_exists("/tmp/commands.txt")) {
		mwexec("sh /tmp/commands.txt &");
		unlink("/tmp/commands.txt");
	}

	update_filter_reload_status("Running plugins");

	/* process packager manager custom rules */
	$files = return_dir_as_array("/usr/local/pkg/pf/");
	if($files <> "") {
		foreach ($files as $file) {
			if($file) {
				$text = file_get_contents("/usr/local/pkg/pf/" . $file);
				if($text) {
					if(stristr($file, ".sh") == true) {
						mwexec("/usr/local/pkg/pf/" . $file . " start");
					} else {
						if(!stristr($file,"CVS")) {
							if($g['booting'] == true)
								echo "\t{$file}... ";
							require_once("/usr/local/pkg/pf/" . $file);
						}
					}
				}
			}
		}
	}
	update_filter_reload_status("Plugins completed.");

	system_start_ftp_helpers();
	
	if($config['system']['shapertype'] == "m0n0") {
		require_once ("/etc/inc/m0n0/shaper.inc");
		shaper_configure();	
	}

	/* if time based rules are enabled then swap in the set */
	if($time_based_rules == true) {
		tdr_install_cron(true);
		tdr_install_set();
	} else {
		tdr_install_cron(false);
	}

	/*
	    we need a way to let a user run a shell cmd after each
	    filter_configure() call.  run this xml command after
            each change.
	*/
	if($config['system']['afterfilterchangeshellcmd'] <> "")
		mwexec($config['system']['afterfilterchangeshellcmd']);

	/* sync carp entries to other firewalls */
	update_filter_reload_status("Syncing CARP data");
	carp_sync_client();

	system_routing_configure();
	
	find_dns_aliases();
	
	update_filter_reload_status("Done");

	return 0;
}

function filter_generate_aliases() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_aliases() being called $mt\n";
	}
	$aliases = "";

	$i = 0;

	if($config['interfaces']['lan']) 	
		$lanip = find_interface_ip($config['interfaces']['lan']['if']);

	$wanip = find_interface_ip(get_real_wan_interface());

	if($config['interfaces']['lan']) 	
		$lan_aliases = " " . link_ip_to_carp_interface($lanip);
	
	$wan_aliases = " " . link_ip_to_carp_interface($wanip);

	if($config['interfaces']['lan']) {
		if(link_int_to_bridge_interface("lan"))
			$lan_aliases .= " " . link_int_to_bridge_interface("lan");
	}
	if(link_int_to_bridge_interface("wan"))
		$wan_aliases .= " " . link_int_to_bridge_interface("wan");

	$aliases .= "# System Aliases \n";
	$aliases .= "loopback = \"{ lo0 }\"\n";

	if($config['interfaces']['lan']) 
		$aliases .= "lan = \"{ {$config['interfaces']['lan']['if']}{$lan_aliases} }\"\n";

	$wanif = get_real_wan_interface();
	
	/* carpdev support on WAN */
	if($config['interfaces']['wan']['ipaddr'] == "carpdev-dhcp") {
		$viparr = &$config['virtualip']['vip'];
		$counter = 0;
		if(is_array($viparr))
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "carpdev-dhcp") {
				if($vip['interface'] == "wan") {
					if($counter == 0) 
						$wanif = "";
					$wanif .= " carp{$counter}";
					$aliases .= "carp{$counter} = \"{ carp{$counter} }\"\n";					
				}
				$counter++;
			}
		}
	}

    if($config['interfaces']['wan']['ipaddr'] == "pppoe") {
		$aliases .= "pppoe0 = \"{ " . $wanif . " " . get_real_wan_interface() . " }\" \n"; 
		$aliases .= "wan = \"{ " . $wanif . "{$wan_aliases} pppoe0  }\"\n";
	} else if ($config['interfaces']['wan']['ipaddr'] == "pptp") {
		$aliases .= "pptp0 = \"{ " . $wanif . " " . get_real_wan_interface() . " }\" \n";
		$aliases .= "wan = \"{ " . $wanif . "{$wan_aliases} pptp0  }\"\n";
	} else {
		$aliases .= "wan = \"{ " . $wanif . "{$wan_aliases}  }\"\n";
	}

	$aliases .= "enc0 = \"{ enc0 }\"\n";

    /* used to count netgraph interfaces */
    $counter = 0;

    /* ng ordering is VERY important here.  do not alter order */
    if($config['pptpd']['mode'] == "server") {
		/* build pptp alias */
		$tmp = "pptp = \"{ ";
		$starting_pptp = 1;
		if($config['interfaces']['wan']['ipaddr'] == "pppoe")
			$starting_pptp = 1;
		for($x=$starting_pptp; $x<$g["n_pptp_units"]+$starting_pptp; $x++)
			$tmp .= "ng{$x} ";
		$counter = $x;
		$tmp .= "}\" \n";
		if($counter > 0)
			$aliases .= $tmp;
	}
    if($config['pppoe']['mode'] == "server") {
		/* build pppoe alias */
		$tmp = "pppoe = \"{ ";
		$starting_pppoe = 1;
		if($config['interfaces']['wan']['ipaddr'] == "pppoe")
			$starting_pppoe = 1;
		for($x=0; $x<$g["n_pppoe_units"]+$starting_pppoe; $x++) {
			$tmp .= "ng{$counter} ";
			$counter++;
		}
		$tmp .= "}\" \n";
		if($x > 0)
			$aliases .= $tmp;
	}


	/* if list */
	$ifdescrs = get_configured_interface_list(true);

	$bridgetracker = 0;
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$aliases .= convert_friendly_interface_to_friendly_descr($ifname) . " = \"{ " . filter_opt_interface_to_real($ifname);
		if(link_int_to_bridge_interface($ifname))
			$aliases .= " " . link_int_to_bridge_interface($ifname);
		$optip = find_interface_ip($config['interfaces'][$ifname]['if']);
		if($optip) {
			$opt_carp_ints = link_ip_to_carp_interface($optip);
			if($opt_carp_ints)
				$aliases .= $opt_carp_ints;
		}
		$aliases .= " }\"\n";		
		/* XXX TODO: below comment and subsequent two lines of code from
                  Adam Lebsack <adam at holonyx dot com>
                  I'm not sure what it means, marking this to look into.  cmb@
                  
                  add an alias, since much of the filter code is broken when it comes to
		finding out the real interface */
		if(preg_match("/^ppp_(.+)$/", $config['interfaces'][$ifname]['if'], $matches))
			$aliases .= "{$config['interfaces'][$ifname]['if']} = \"ppp0\"\n";
	}
	$aliases .= "# User Aliases \n";
	/* Setup pf groups */
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			$extraalias = "";
			$ip = find_interface_ip($alias['address']);
			$extraalias = " " . link_ip_to_carp_interface($ip);
			$aliases .= "{$alias['name']} = \"{ {$alias['address']}{$extralias} }\"\n";
		}
	}

	return $aliases;
}

/* returns space seperated list of vpn subnets */
function get_vpns_list() {
	global $config;
	/* build list of vpns */
	$vpns = "";
	$vpns_arr = array();
	/* ipsec */
	if ($config['ipsec']['tunnel']) {
		foreach ($config['ipsec']['tunnel'] as $tunnel) {
			if(is_subnet($tunnel['remote-subnet'])) {
				$vpns_arr[] = $tunnel['remote-subnet'];
			}
		}
	}
	/* openvpn */
	foreach (array('client', 'server') as $type) {
		$conf =& $config['installedpackages']["openvpn$type"]['config'];
		if (!is_array($conf)) continue;
		foreach ($conf as $tunnel) {
			if(is_subnet($tunnel['remote-subnet'])) {
				$vpns_arr[] = $tunnel['remote_network'];
			}
		}
	}
	/* pppoe */
	if ($config['pppoe']['remoteip']) {
		if(is_subnet($tunnel['remote-subnet'])) {
			$vpns_arr[] = $config['pppoe']['remoteip'] ."/". $config['pppoe']['pppoe_subnet'];
		}
	}
	if(!empty($vpns_arr)) {
		$vpns = implode(" ", $vpns_arr);
	}
	return $vpns;
}

/* returns space seperated list of directly connected networks */
function get_direct_networks_list() {
	global $config;
	/* build list of directly connected interfaces and networks */
	$networks = "";
	$networks_arr = array();

	/* if list */
	$iflist = get_configured_interface_list();

	foreach ($iflist as $ifent => $ifname) {
		/* do not process interfaces that will end up with gateways */
		$interface_ip = find_interface_ip(convert_friendly_interface_to_real_interface_name($ifname));
		$sn = $config['interfaces'][$ifname]['subnet'];
		if($sn == "") {
			$subnet = "{$interface_ip}/32";
		} else {
			$sa = gen_subnet($interface_ip, $config['interfaces'][$ifname]['subnet']);
			$subnet = "{$sa}/{$sn}";
		}
		if(is_subnet($subnet)) {
			$networks_arr[] = $subnet;
		}
	}

	if(!empty($networks_arr)) {
		$networks = implode(" ", $networks_arr);
	}
	return $networks;
}

function generate_optcfg_array(&$optcfg) {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "generate_optcfg_array() being called $mt\n";
	}

	/* if list */
        $iflist = get_configured_interface_list(true);

        foreach ($iflist as $if => $ifdetail) {
                $oc = $config['interfaces'][$if];

                if ($oc['if']) {
                        $oic = array();
                        $oic['if'] = $oc['if'];

                        if ($oc['bridge']) {
                                if (!strstr($oc['bridge'], "opt") ||
                                        isset($config['interfaces'][$oc['bridge']]['enable'])) {
                                        if (is_ipaddr($config['interfaces'][$oc['bridge']]['ipaddr'])) {
                                                $oic['ip'] = $config['interfaces'][$oc['bridge']]['ipaddr'];
                                                $oic['sn'] = $config['interfaces'][$oc['bridge']]['subnet'];
                                                $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
                                        }
                                }
                                $oic['bridge'] = 1;
                        } else {
                                $oic['ip'] = $oc['ipaddr'];
                                $oic['sn'] = $oc['subnet'];
                                $oic['descr'] = $oc['descr'];
                                $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
                        }

                        $optcfg[$if] = $oic;
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
	global $config, $g;

	return mwexec("/sbin/pfctl -F state");
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if($if, $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false) {
	global $config;

	/* XXX: billm - any idea if this code is needed? */
	if($src == "/32" || $src{0} == "/")
		return;

	/* Use interface name if IP isn't specified */
        if ($natip != "") 
			$tgt = "{$natip}/32";
        else 
 			$tgt = "($if)";

		if($if == $config['interfaces']['wan']['if']) {
			/* carpdev support on WAN */
			if($config['interfaces']['wan']['ipaddr'] == "carpdev-dhcp") {
				$viparr = &$config['virtualip']['vip'];
				$counter = 0;
				if(is_array($viparr))
				foreach ($viparr as $vip) {
					if ($vip['mode'] == "carpdev-dhcp") {
						if($vip['interface'] == "wan") 
							$tgt = "carp{$counter}";
						$counter++;
					}
				}
			}
		}

	/* Add the hard set source port (useful for ISAKMP) */
        if ($natport != "")
		$tgt .= " port {$natport}";

	/* sometimes this gets called with "" instead of a value */
	if ($src == "")
		$src = "any";

	/* Match on this source port */
        if ($srcport != "")
		$src .= " port {$srcport}";

	/* sometimes this gets called with "" instead of a value */
	if ($dst == "")
		$dst = "any";

	/* Match on this dest port */
        if ($dstport != "")
		$dst .= " port {$dstport}";

	/* Allow for negating NAT entries */
	if ($nonat) {
		$nat = "no nat";
		$target = "";
	} else {
		$nat = "nat";
		$target = "-> {$tgt}";
	}

	/* outgoing static-port option, hamachi, Grandstream, VOIP, etc */
	if($staticnatport)
		$staticnatport_txt = " static-port";
	else
		$staticnatport_txt = "";

	$if_friendly = convert_real_interface_to_friendly_descr($if);

	/* Put all the pieces together */
	if($if_friendly)
		$natrule = "{$nat} on \${$if_friendly} from {$src} to {$dst} {$target}{$staticnatport_txt}\n";

	return $natrule;
}

function is_one_to_one_or_server_nat_rule($iptocheck) {
	global $config, $target;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "is_one_to_one_or_server_nat_rule() being called $mt\n";
	}

	if($config['nat']['onetoone'] <> "")
		foreach($config['nat']['onetoone'] as $onetoone) {
			if(ip_in_subnet($iptocheck,$onetoone['internal']."/".$onetoone['subnet']) == true)
				return true;
			if($onetoone['internal'] == $target)
				return true;
		}

	if($config['nat']['servernat'] <> "")
		foreach($config['nat']['servernat'] as $onetoone) {
			$int = explode("/", $onetoone['ipaddr']);
				if(ip_in_subnet($iptocheck,$onetoone['ipaddr']."/".$onetoone['subnet']) == true)
					return true;
			if($onetoone['ipaddr'] == $target)
				return true;
		}

	if($config['nat']['rule'] <> "")
		foreach($config['nat']['rule'] as $onetoone) {
			$int = explode("/", $onetoone['target']);
				if(ip_in_subnet($iptocheck,$onetoone['target']."/".$onetoone['subnet']) == true)
					return true;
			if($onetoone['target'] == $target)
				return true;
		}

	return FALSE;
}

function filter_nat_rules_generate() {
	global $config, $g, $after_filter_configure_run, $used_pftpx_ports;

	$wancfg = $config['interfaces']['wan'];

	if($config['interfaces']['lan']) 
		$lancfg = $config['interfaces']['lan'];

	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];
	$wanif = get_real_wan_interface();

	if($config['interfaces']['lan']) {
		$lanif = $config['interfaces']['lan']['if'];
		$lanip = $config['interfaces']['lan']['ipaddr'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	}
	
	$natrules .= "nat-anchor \"ftp-proxy/*\"\n";

	$natrules .= "nat-anchor \"natearly/*\"\n";
	$natrules .= "nat-anchor \"natrules/*\"\n";

	$natrules .= "# FTP proxy\n";
	$natrules .= "rdr-anchor \"ftp-proxy/*\"\n";
	$natrules .= "rdr-anchor \"tftp-proxy/*\"\n";

	update_filter_reload_status("Creating 1:1 rules...");

	/* any 1:1 mappings? */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if (!is_numeric($natent['subnet']))
				$sn = 32;
			else
				$sn = $natent['subnet'];

			if (!$natent['interface'] || ($natent['interface'] == "wan"))
				$natif = $wanif;
			else
				$natif = $config['interfaces'][$natent['interface']]['if'];

			if($natif)
				$natrules .= "binat on $natif from {$natent['internal']}/{$sn} to any -> {$natent['external']}/{$sn}\n";
		}
	}

	$natrules .= "\n# Outbound NAT rules\n";

	/* outbound rules - advanced or standard */
	if (isset($config['nat']['advancedoutbound']['enable'])) {
		/* advanced outbound rules */
		if (is_array($config['nat']['advancedoutbound']['rule'])) {
			foreach ($config['nat']['advancedoutbound']['rule'] as $obent) {

				update_filter_reload_status("Creating advanced outbound rule {$obent['descr']}");

				$src = $obent['source']['network'];
				if (isset($obent['destination']['not']) && !isset($obent['destination']['any']))
					$dst = "!" . $obent['destination']['address'];
				else
					$dst = $obent['destination']['address'];


				if (!$obent['interface'] || ($obent['interface'] == "wan"))
					$natif = $wanif;
				else
					$natif = $config['interfaces'][$obent['interface']]['if'];

				$natrules .= filter_nat_rules_generate_if($natif,
					$src,
					$obent['sourceport'],
					$dst,
					$obent['dstport'],
					$obent['target'],
					$obent['natport'],
					isset($obent['nonat']),
					isset($obent['staticnatport'])
					);
			}
		}
	} else {
		/* standard outbound rules (one for each interface) */
		update_filter_reload_status("Creating outbound NAT rules");
		$target = get_current_wan_address($interface = "wan");
		if(is_ipaddr($wancfg['alias-address'])) {
			$aliastarget = $wancfg['alias-address'];
			$aliassubnet = $wancfg['alias-subnet'];
			if($config['interfaces']['lan']) 
				$natrules .= filter_nat_rules_generate_if($wanif,
					"{$lansa}/{$lancfg['subnet']}", null, "$aliastarget/$aliassubnet", null, $aliastarget, null, false);
		}
		if($config['interfaces']['lan']) {
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$lansa}/{$lancfg['subnet']}", 500, "", 500, $target, 500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$lansa}/{$lancfg['subnet']}", 4500, "", 4500, $target, 4500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$lansa}/{$lancfg['subnet']}", 5060, "", 5060, $target, 5060, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$lansa}/{$lancfg['subnet']}", null, "", null, $target, null, false);
		}

		$optints = array();
		generate_optcfg_array($optints);

		/* generate lan nat mappings for opts with a gateway opts */
		foreach($optints as $ocname => $oc) {
			$opt_interface = $oc['if'];
			if (interface_has_gateway("$opt_interface")) {
				if(is_ipaddr($config['interfaces'][$ocname]['alias-address'])) {
					$aliastarget = $config['interfaces'][$ocname]['alias-address'];
					$aliassubnet = $config['interfaces'][$ocname]['alias-subnet'];
					if($config['interfaces']['lan']) 
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$lansa}/{$lancfg['subnet']}", null, "$aliastarget/$aliassubnet", null, $aliastarget, null, false);
				}
				$target = get_current_wan_address($interface = "$ocname");
				if($config['interfaces']['lan']) {
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$lansa}/{$lancfg['subnet']}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$lansa}/{$lancfg['subnet']}", 4500, "", 4500, $target, 4500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$lansa}/{$lancfg['subnet']}", 5060, "", 5060, $target, 5060, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$lansa}/{$lancfg['subnet']}", null, "", null, $target, null, false);
				}
			}
		}

		/* optional interface if list */
        	$iflist = get_configured_interface_list(true);

		foreach ($iflist as $if => $ifname) {
			update_filter_reload_status("Creating outbound rules ({$if})");
			$optcfg = $config['interfaces'][$if];

			if ((!$optcfg['bridge']) && (!interface_has_gateway($if))) {
				$optsa = gen_subnet($optcfg['ipaddr'], $optcfg['subnet']);
				$target = get_current_wan_address($interface = "wan");
				if(is_ipaddr($wancfg['alias-address'])) {
					$aliastarget = $wancfg['alias-address'];
					$aliassubnet = $wancfg['alias-subnet'];
					if($config['interfaces']['lan']) 
						$natrules .= filter_nat_rules_generate_if($if,
							"{$lansa}/{$lancfg['subnet']}", null, "$aliastarget/$aliassubnet", null, $aliastarget, null, false);
				}
				/* create outbound nat entries for primary wan */
				if($config['interfaces']['lan'])  {
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$optsa}/{$optcfg['subnet']}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$optsa}/{$optcfg['subnet']}", 4500, "", 4500, $target, 4500, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$optsa}/{$optcfg['subnet']}", 5060, "", 5060, $target, 5060, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$optsa}/{$optcfg['subnet']}", null, "", null, $target, null, isset($optcfg['nonat']));
				}
	
				/*
				 * XXX: ermal - is this correct at all or just 
				 *		curlies mess?! 
				 */
				/* create outbound nat entries for all opt wans */
				foreach($optints as $ocname => $oc) {
					$opt_interface = $oc['if'];
					if (interface_has_gateway($opt_interface)) {
						$target = get_current_wan_address($interface = $ocname);
						if(is_ipaddr($config['interfaces'][$ocname]['alias-address'])) {
							$aliastarget = $config['interfaces'][$ocname]['alias-address'];
							$aliassubnet = $config['interfaces'][$ocname]['alias-subnet'];
							if($config['interfaces']['lan']) 
								$natrules .= filter_nat_rules_generate_if($opt_interface,
									"{$lansa}/{$lancfg['subnet']}", null, "$aliastarget/$aliassubnet", null, $aliastarget, null, false);
						}
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$optsa}/{$optcfg['subnet']}", 500, "", 500, $target, 500, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$optsa}/{$optcfg['subnet']}", 4500, "", 4500, $target, 4500, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$optsa}/{$optcfg['subnet']}", 5060, "", 5060, $target, 5060, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$optsa}/{$optcfg['subnet']}", null, "", null, $target, null, isset($optcfg['nonat']));
					}
				}
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$pptp_subnet = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptp_subnet = $config['pptp']['pptp_subnet'];
			$target = get_current_wan_address($interface = "wan");
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 500, "", 500, $target, 500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 4500, "", 4500, $target, 4500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 5060, "", 5060, $target, 5060, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pptpdcfg['remoteip']}/{$pptp_subnet}", null, "", null, $target, null, false);

			/* generate nat mappings for opts with a gateway opts */
			foreach($optints as $ocname => $oc) {
				$opt_interface = $oc['if'];
				if ((is_private_ip($pptpdcfg['remoteip'])) && (interface_has_gateway($opt_interface))) {
					$target = get_current_wan_address($interface = "$ocname");
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 4500, "", 4500, $target, 4500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pptpdcfg['remoteip']}/{$pptp_subnet}", 5060, "", 5060, $target, 5060, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pptpdcfg['remoteip']}/{$pptp_subnet}", null, "", null, $target, null, false);
				}
			}
		}

		/* PPPoE subnet */
		if ($pppoecfg['mode'] == "server") {
			$pppoe_subnet = $g['pppoe_subnet'];
			if($config['pppoe']['pppoe_subnet'] <> "")
				$pppoe_subnet = $config['pppoe']['pppoe_subnet'];
			$target = get_current_wan_address($interface = "wan");
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 500, "", 500, $target, 500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 4500, "", 4500, $target, 4500, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 5060, "", 5060, $target, 5060, false);
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pppoecfg['remoteip']}/{$pppoe_subnet}", null, "", null, $target, null, false);

			/* generate nat mappings for opts with a gateway opts */
			foreach($optints as $ocname => $oc) {
				$opt_interface = $oc['if'];
				if ((is_private_ip($pppoecfg['remoteip'])) && (interface_has_gateway($opt_interface))) {
					$target = get_current_wan_address($interface = "$ocname");
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 4500, "", 4500, $target, 4500, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pppoecfg['remoteip']}/{$pppoe_subnet}", 5060, "", 5060, $target, 5060, false);
					$natrules .= filter_nat_rules_generate_if($opt_interface,
						"{$pppoecfg['remoteip']}/{$pppoe_subnet}", null, "", null, $target, null, false);
				}
			}
		}

		/* static routes */
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				$netip = explode("/", $route['network']);
				if ((! interface_has_gateway($route['interface'])) && (is_private_ip($netip[0]))) {
					$target = get_current_wan_address($interface = "wan");
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$route['network']}", 500, "", 500, $target, 500, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$route['network']}", 4500, "", 4500, $target, 4500, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$route['network']}", 5060, "", 5060, $target, 5060, false);
					$natrules .= filter_nat_rules_generate_if($wanif,
						"{$route['network']}", null, "", null, $target, null, false);
				}
				/* generate nat mapping for static routes on opts */
				foreach($optints as $ocname => $oc) {
					$opt_interface = $oc['if'];
					if ((! interface_has_gateway($route['interface'])) && (is_private_ip($netip[0])) && (interface_has_gateway($opt_interface))) {
						$target = get_current_wan_address($interface = "$ocname");
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$route['network']}", 500, "", 500, $target, 500, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$route['network']}", 4500, "", 4500, $target, 4500, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$route['network']}", 5060, "", 5060, $target, 5060, false);
						$natrules .= filter_nat_rules_generate_if($opt_interface,
							"{$route['network']}", null, "", null, $target, null, false);
					}
				}

			}
		}

	}

	$natrules .= "\n#SSH Lockout Table\n";
	$natrules .= "table <sshlockout> persist\n\n";

	/* is SPAMD insalled? */
	if (is_package_installed("spamd") == 1) {
		$natrules .= "\n# spam table \n";

		$natrules .= "table <whitelist> persist\n";
		$natrules .= "table <blacklist> persist\n";
		$natrules .= "table <spamd> persist\n";
		if(file_exists("/var/db/whitelist.txt"))
			$natrules .= "table <spamd-white> persist file \"/var/db/whitelist.txt\"\n";
		$natrules .= "rdr pass on {$wanif} proto tcp from <blacklist> to port smtp -> 127.0.0.1 port spamd\n";
		$natrules .= "rdr pass on {$wanif} proto tcp from <spamd> to port smtp -> 127.0.0.1 port spamd\n";
		$natrules .= "rdr pass on {$wanif} proto tcp from !<spamd-white> to port smtp -> 127.0.0.1 port spamd\n";
		if($config['installedpackages']['spamdsettings']['config'])
			foreach($config['installedpackages']['spamdsettings']['config'] as $ss)
				$nextmta = $ss['nextmta'];
		if($nextmta <> "") {
			$natrules .= "rdr pass on {$wanif} proto tcp from <spamd-white> to port smtp -> {$nextmta} port smtp\n";
		}
	}

	/* load balancer anchor */
	$natrules .= "\n# Load balancing anchor\n";
        $natrules .= "rdr-anchor \"relayd/*\"\n";


	update_filter_reload_status("Setting up FTP helper");

	/* do not nat tftp proxy */
	$natrules .= "\nno nat on \$wan to port tftp\n\n";

	$natrules .= "\n# FTP Proxy/helper\n";

	/* if list */
        $iflist = get_configured_interface_list();

	$interface_counter = 0;
	$vpns_list = get_vpns_list();
	$direct_networks_list = get_direct_networks_list();
	/* prevent 1:1 ips from ftp-proxy, they will be handled by ftp-sesame */
	if($config['nat']['onetoone'])
		foreach ($config['nat']['onetoone'] as $vipent)
			$onetoone_list .= "{$vipent['internal']} ";
	if($onetoone_list)
		$natrules .= "table <onetoonelist> { $onetoone_list }\n";
	if($vpns_list)
		$natrules .= "table <vpns> { $vpns_list }\n";
	if($direct_networks_list) 
		$natrules .= "table <direct_networks> { $direct_networks_list }\n";
	/* loop through all interfaces and handle ftp-proxy redirections */
	foreach ($iflist as $ifent => $ifname) {
		/* XXX: ermal - this should be reallt unified with code above. */
		if ($ifent == "wan")
			continue;

		$ifname_lower = convert_friendly_interface_to_friendly_descr(strtolower($ifname));
		$realif = convert_friendly_interface_to_real_interface_name(strtolower($ifname));
		$int_ip = find_interface_ip($realif);
		if(isset($config['interfaces'][strtolower($ifname)]['disableftpproxy'])) {
			if($g['debug'])
				log_error("Filter: FTP proxy disabled for interface {$ifname} - ignoring.");
			$interface_counter++;
			continue;
		}
		/* are we in routed mode? no source nat rules and not a outside interface? */
		/* If we have advanced outbound nat we skip the FTP proxy, we use ftpsesame */
		if((isset($config['nat']['advancedoutbound']['enable'])) && (! interface_has_gateway($ifname))) {
			$sourcenat = 0;
			/* we are using advanced outbound nat, are we in routing mode? */
			$realif = convert_friendly_interface_to_real_interface_name($ifname);
			/* if the interface address lies within a outbound NAT source network we should skip */
			if(! empty($config['nat']['advancedoutbound']['rule'])) {
				foreach($config['nat']['advancedoutbound']['rule'] as $natnetwork) {
					if(ip_in_subnet($int_ip, $natnetwork['source']['network'])) {
						/* if the interface address is matched in the AON Rule we need the ftp proxy */
						$sourcenat++;
					}
				}
			}
			if($sourcenat == 0) {
				if($g['debug'])
					log_error("Filter: No AON rule matched for interface {$ifname} - not using the FTP proxy");
				$interface_counter++;
				continue;
			} else {
				if($g['debug'])
					log_error("Filter: AON Rule matched for interface {$ifname} - using FTP proxy");
			}
		}
		$tmp_port = 8021 + $interface_counter;
		$tmp_interface = convert_friendly_interface_to_real_interface_name($ifname);
		$ifname_lower = strtolower(convert_friendly_interface_to_friendly_descr($ifname));
		$vpns = get_vpns_list();
		/* if the user has defined, include the alias so that we do not redirect ftp
		   connections across the tunnels to ftp-proxy */
		$int_ip = find_interface_ip($tmp_interface);
		/* if interface lacks an ip, dont setup a rdr for ftp.  they are most likely on a bridged interface */
		if($int_ip and $vpns_list)
			if($ifname_lower) {
				$natrules .= "no rdr on $tmp_interface proto tcp from any to <vpns> port 21\n";
				$natrules .= "no rdr on $tmp_interface proto udp from any to <vpns> port tftp\n";
				if($onetoone_list) {
					$natrules .= "no rdr on $tmp_interface proto tcp from <onetoonelist> to any port 21\n";
					$natrules .= "no rdr on $tmp_interface proto udp from <onetoonelist> to any port tftp\n";
				}
			}
		if($ifname_lower) {
			$temp_array_holder_pftpx = array();
			$temp_array_holder_pftpx['port'] = $tmp_port;
			$temp_array_holder_pftpx['interface'] = $tmp_interface;
			$used_pftpx_ports[] = $temp_array_holder_pftpx;
			$natrules .= "rdr on $tmp_interface proto tcp from any to any port 21 -> 127.0.0.1 port {$tmp_port}\n";
			$natrules .= "rdr on $tmp_interface proto udp from any to any port tftp -> 127.0.0.1 port 6969\n";
		}
		$interface_counter++;
	}
	$natrules .= "\n";

	/* DIAG: add ipv6 NAT, if requested */
	if (isset($config['diag']['ipv6nat']['enable']) and $config['diag']['ipv6nat']['ipaddr'] <> "") {
		/* XXX: FIX ME!  IPV6 */
		$natrules .= "rdr on \$wan proto ipv6 from any to any -> {$config['diag']['ipv6nat']['ipaddr']}\n";
	}

	if(file_exists("/var/etc/inetd.conf"))
		mwexec("rm /var/etc/inetd.conf");
	touch("/var/etc/inetd.conf");

	if (isset($config['nat']['rule'])) {
		$natrules .= "# NAT Inbound Redirects\n";

		$inetd_fd = fopen("/var/etc/inetd.conf","w");

		/* add tftp protocol helper */
		fwrite($inetd_fd, "tftp\tdgram\tudp\twait\t\troot\t/usr/local/sbin/tftp-proxy -v\n");

		if(!isset($config['system']['disablenatreflection'])) {
			/* start redirects on port 19000 of localhost */
			$starting_localhost_port = 19000;
		}

		foreach ($config['nat']['rule'] as $rule) {

			update_filter_reload_status("Creating NAT rule {$rule['descr']}");

			/* if item is an alias, expand */
			$extport = "";
			unset($extport);
			if(alias_expand($rule['external-port']))
				$extport[0] = alias_expand_value($rule['external-port']);
			else
				$extport = explode("-", $rule['external-port']);

			/* if item is an alias, expand */
			if(alias_expand($rule['local-port']))
				$localport = "";
			else
				$localport = " port {$rule['local-port']}";

			$target = alias_expand_host($rule['target']);

			if (!$target) {
				$natrules .= "# Unresolvable alias {$rule['target']}\n";
				continue;       /* unresolvable alias */
			}

			# use tables for aliases in rdr
			if (!is_ipaddr($target)) {
				$natrules .= "table <{$rule['target']}> { $target }\n";
				$target = "<{$rule['target']}>";
			}

			if ($rule['external-address'])
				if($rule['external-address'] <> "any")
					$extaddr = $rule['external-address'] . "/32";
				else
					$extaddr = $rule['external-address'];
			else
				$extaddr = get_current_wan_address($rule['interface']);

			if (!$rule['interface'] || ($rule['interface'] == "wan"))
				$natif = $wanif;
			else if($rule['interface'] == "\$pptp")
				$natif = "pptp";
			else if($rule['interface'] == "\$pppoe")
				$natif = "pppoe";
			else
				$natif = $config['interfaces'][$rule['interface']]['if'];

			if($config['interfaces']['lan']) 
				$lanif = $lancfg['if'];

			/*
			 *   Expand aliases
			 *   XXX: may want to integrate this into pf macros
			 */
			if(alias_expand($target))
				$target = alias_expand($target);
			if(alias_expand($extaddr))
				$extaddr = alias_expand($extaddr);

			/*
			 *    If FTP Proxy Helper is enabled and the
			 *    operator has requested a port forward to
			 *    a ftp server then launch a helper
			 */
			$dontinstallrdr = false;
			if($target <> "") {
				if($extport[0] == "21" and !isset($config['interfaces'][strtolower($rule['interface'])]['disableftpproxy'])) {
					$external_address = $rule['external-address'];
					$helpers = exec("/bin/ps awux | grep {$target} -b {$external_address} | grep -v grep");
					if(!$helpers) {
						if($external_address == "")
							$external_address = find_interface_ip(get_real_wan_interface());
						/*   install a ftp-proxy helper, do not set a rule.  also use the delay filter configure run
						 *   routines because if this is the first bootup the filter is not completely configured
						 *   and thus pf is not fully running.   otherwise we end up with: ftp-proxy: pf is disabled
						 */
						/* Get the ftp queue for this interface */
						if (isset($config['interfaces'][$rule['interface']]['ftpqueue']))
							$shaper_queue = $config['interfaces'][$rule['interface']]['ftpqueue'];
						/* else default queue configured on shaper will get this */
						$after_filter_configure_run[] = "/usr/local/sbin/pftpx {$shaper_queue} -f {$target} -b {$external_address} -c 21 -g 21";
					}
					$dontinstallrdr = true;
				}
			}

			if($extaddr == "")
				$dontinstallrdr = true;

			$rdr_on = convert_real_interface_to_friendly_descr($rule['interface']);

			if($dontinstallrdr == false) {
				/* is rule a port range? */
				if ((!$extport[1]) || ($extport[0] == $extport[1])) {

					switch ($rule['protocol']) {
						case "tcp/udp":
							if($natif) {
								if($rule['external-port'] <> $rule['local-port'])
									$natrules .= "{$nordr}rdr on $natif proto { tcp udp } from any to {$extaddr} port { {$extport[0]} } -> {$target}{$localport}";
								else
									$natrules .= "{$nordr}rdr on $natif proto { tcp udp } from any to {$extaddr} port { {$extport[0]} } -> {$target}";
							}
							break;
						case "udp":
						case "tcp":
							if($extport[0])
								if($natif) {
									if($rule['external-port'] <> $rule['local-port'])
										$natrules .= "rdr on $natif proto {$rule['protocol']} from any to {$extaddr} port { {$extport[0]} } -> {$target}{$localport}";
									else
										$natrules .= "rdr on $natif proto {$rule['protocol']} from any to {$extaddr} port { {$extport[0]} } -> {$target}";
								}
							else
								if($natif)
									$natrules .= "rdr on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}{$localport}";
							break;
						default:
							$natrules .= "rdr on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}";
							break;
					}
				} else {
					switch ($rule['protocol']) {
						case "tcp/udp":
							if($natif)
								$natrules .= "{$nordr}rdr on $natif proto { tcp udp } from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target}{$localport}:*";
							break;
						case "udp":
						case "tcp":
							if($natif)
								$natrules .= "{$nordr}rdr on $natif proto {$rule['protocol']} from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target}{$localport}:*";
							break;
						default:
							if($natif)
								$natrules .= "{$nordr}rdr on $natif proto {$rule['protocol']} from any to {$extaddr} -> {$target}";
					}
				}
			}

			/*    does this rule redirect back to a internal host?
			 *    if so, add some extra goo to help this work.
			 */
			$rule_friendly_if  = convert_friendly_interface_to_real_interface_name($rule['interface']);
			$rule_interface_ip = find_interface_ip($rule_friendly_if);
			$rule_interface_subnet = $config['interfaces'][$rule['interface']]['subnet'];
			$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
			if($config['interfaces']['lan']) {
				if($rule['external-address'] == "any" and $rule['interface'] == "lan") {
					$natrules .= "\n";
					if($rule_friendly_if)
						$natrules .= "no nat on {$rule_friendly_if} proto tcp from {$rule_friendly_if} to {$rule_subnet}/{$rule_interface_subnet}\n";
					if($rule_friendly_if)
						$natrules .= "nat on {$rule_friendly_if} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$extport[0]} -> {$rule_friendly_if}\n";
				}
			}

			if(!isset($config['system']['disablenatreflection'])) {

				update_filter_reload_status("Setting up reflection");

				$natrules .= "\n# Reflection redirects\n";
				foreach ($iflist as $ifent => $ifname) {

					/* do not process interfaces with gateways*/
					if($config['interfaces'][$ifname]['gateway'] <> "")
						continue;

					/* do not process interfaces that will end up with gateways */
					if($config['interfaces'][$ifname]['ipaddr'] == "dhcp" or
					   $config['interfaces'][$ifname]['ipaddr'] == "bigpond" or
					   $config['interfaces'][$ifname]['ipaddr'] == "pppoe" or
					   $config['interfaces'][$ifname]['ipaddr'] == "pptp")
						continue;

					$ifname_real = convert_friendly_interface_to_real_interface_name($ifname);

					if($extport[1])
						$range_end = ($extport[1]);
					else
						$range_end = ($extport[0]);

					$range_end++;

					if($rule['local-port'])
						$lrange_start = $rule['local-port'];

					if($range_end - $extport[0] > 500) {
						$range_end = $extport[0]+1;
						log_error("Not installing nat reflection rules for a port range > 500");
					} else {
						/* only install reflection rules for < 19991 items */
						if($starting_localhost_port < 19991) {
							$loc_pt = $lrange_start;
							for($x=$extport[0]; $x<$range_end; $x++) {

								$xxx = $x;

								/*   do not install reflection rules for FTP.  This simply
								 *   opens up pandoras box.
								 */
								if($xxx == "21")
									continue;

								update_filter_reload_status("Creating reflection rule for {$rule['descr']}...");

								$ifname_real = convert_friendly_interface_to_friendly_descr(strtolower($ifname));

								if($config['system']['reflectiontimeout']) 
									$reflectiontimeout = $config['system']['reflectiontimeout'];
								else 
									$reflectiontimeout = "2000";	

								switch($rule['protocol']) {

									case "tcp/udp":
										$protocol = "{ tcp udp }";
										$toadd_array = array();
										if(is_alias($loc_pt)) {
											$loc_pt_translated = alias_expand_value($loc_pt);
											add_hostname_to_watch($loc_pt_translated);
											if(stristr($loc_pt_translated, " ")) {
												/* XXX: we should deal with multiple ports */
												$loc_pt_translated_split = split(" ", $loc_pt_translated);
												foreach($loc_pt_translated_split as $lpts)
													$toadd_array[] = $lpts;
											} else {
												$toadd_array[] = $loc_pt_translated;
											}
										} else {
											$loc_pt_translated = $loc_pt;
											$toadd_array[] = $loc_pt_translated;
										}										
										foreach($toadd_array as $tda){
											fwrite($inetd_fd, "{$starting_localhost_port}\tstream\ttcp/udp\tnowait/0\tnobody\t/usr/bin/nc nc -u -w {$reflectiontimeout} {$target} {$tda}\n");
									  		if($ifname_real)
									  			$natrules .= "rdr on \${$ifname_real} proto tcp from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
									  		$starting_localhost_port++;
									  		fwrite($inetd_fd, "{$starting_localhost_port}\tstream\ttcp/udp\tnowait/0\tnobody\t/usr/bin/nc nc -w {$reflectiontimeout} {$target} {$tda}\n");
									  		if($ifname_real)
									  			$natrules .= "rdr on \${$ifname_real} proto udp from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
									  		$xxx++;
									  		$starting_localhost_port++;
										}
										break;
									case "tcp":
									case "udp":
										$protocol = $rule['protocol'];
										$toadd_array = array();
										if(is_alias($loc_pt)) {
											$loc_pt_translated = alias_expand_value($loc_pt);
											add_hostname_to_watch($loc_pt_translated);
											if(stristr($loc_pt_translated, " ")) {
												/* XXX: we should deal with multiple ports */
												$loc_pt_translated_split = split(" ", $loc_pt_translated);
												foreach($loc_pt_translated_split as $lpts)
													$toadd_array[] = $lpts;
											} else {
												$toadd_array[] = $loc_pt_translated;
											}
										} else {
											$loc_pt_translated = $loc_pt;
											$toadd_array[] = $loc_pt_translated;
										}
										foreach($toadd_array as $tda){
											if($protocol == "udp")
												$dash_u = "-u ";
											else
												$dash_u = "";
											if($config['system']['reflectiontimeout']) 
												$reflectiontimeout = $config['system']['reflectiontimeout'];
											else 
												$reflectiontimeout = "20";												
											fwrite($inetd_fd, "{$starting_localhost_port}\tstream\t{$protocol}\tnowait/0\tnobody\t/usr/bin/nc nc {$dash_u}-w {$reflectiontimeout} {$target} {$tda}\n");
											if($ifname_real)
									  			$natrules .= "rdr on \${$ifname_real} proto {$protocol} from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
									  		$xxx++;
									  		$starting_localhost_port++;
										}
										break;
									default:
										break;
								}
								$loc_pt++;
								if($starting_localhost_port > 19990) {
									log_error("Not installing nat reflection rules. Maximum 1,000 reached.");
									$x = $range_end+1;
								}
							}
						}
					}

				}

			}

			$natrules .= "\n";
		}

		if(!isset($config['system']['disablenatreflection'])) {
			fclose($inetd_fd);
			$helpers = trim(exec("/bin/ps ax | /usr/bin/grep inetd | /usr/bin/grep -v grep | /usr/bin/grep 127"));
			if(!$helpers)
				mwexec("/usr/sbin/inetd -wW -R 0 -a 127.0.0.1 /var/etc/inetd.conf");
			else
				mwexec("/usr/bin/killall -HUP inetd");

		}
	}

	if ($pptpdcfg['mode'] && $pptpdcfg['mode'] != "off") {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = "127.0.0.1";
		else if ($pptpdcfg['mode'] == "redir")
			$pptpdtarget = $pptpdcfg['redir'];

		if ($pptpdcfg['mode'] == "redir") {

/* 
 * NB: ermal -- the rdr rule below is commented out now that we have a solution
 * 		for PPTP passthrough. This unbreaks other GRE traffic passing  
 *		through pfSense. 
 * 		After some more testing this will be removed compeletely.
 */ 
		$natrules .= <<<EOD

# PPTP
#rdr on \$wan proto gre from any to any -> $pptpdtarget
rdr on \$wan proto tcp from any to any port 1723 -> $pptpdtarget

EOD;
		}
	}

	if (is_package_installed('squid') && file_exists('/usr/local/pkg/squid.inc')) {
		require_once('squid.inc');
		$natrules .= squid_generate_rules('nat');
	}

	if (is_package_installed('clamav') && file_exists('/usr/local/pkg/clamav.inc')) {
		require_once('clamav.inc');
		$natrules .= clamav_generate_rules('nat');
	}
	if (is_package_installed('frickin') && file_exists('/usr/local/pkg/frickin.inc')) {
		require_once ('frickin.inc');
		$natrules .= frickin_generate_rules('nat');
	}
        if (is_package_installed('siproxd') && file_exists('/usr/local/pkg/siproxd.inc')) {
                require_once('siproxd.inc');
                $natrules .= siproxd_generate_rules('nat');
        }

	$natrules .= process_carp_nat_rules();

	$natrules .= "# IMSpector rdr anchor\n";
	$natrules .= "rdr-anchor \"imspector\"\n";

	$natrules .= "# UPnPd rdr anchor\n";
	$natrules .= "rdr-anchor \"miniupnpd\"\n";

	return $natrules;
}

function run_command_return_string($cmd) {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "generate_user_filter_rule() being called $mt\n";
	}

	$fd = popen($cmd, "r");
	while(!feof($fd)) {
		$tmp .= fread($fd,49);
	}
	fclose($fd);
	return $tmp;
}

function generate_user_filter_rule_arr($rule, $ngcounter) {
	global $config;
	update_filter_reload_status("Creating filter rules {$rule['descr']} ...");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "generate_user_filter_rule() being called $mt\n";
	}
	$ret = array();
	$line = generate_user_filter_rule($rule, $ngcounter);
	$ret['rule'] = $line;
	$ret['interface'] = $rule['interface'];
	if($rule['descr'] != "" and $line != "")
		$ret['descr'] = "label \"USER_RULE: " . str_replace('"', '', $rule['descr']) . "\"";
	else
		$ret['descr'] = "label \"USER_RULE\"";

	return $ret;
}

function generate_user_filter_rule($rule, $ngcounter) {
		global $config, $g;
		global $table_cache;
		global $schedule_enabled;

		if(isset($config['system']['developerspew'])) {
			$mt = microtime();
			echo "generate_user_filter_rule() being called $mt\n";
		}

		if($config['schedules']) {
			foreach($config['schedules']['schedule'] as $sched) {
				$schedule_enabled = true;
				break;
			}
		}

		/* Setup cache array if not already existing */
		if (!isset($table_cache)) {
			if ($g['debug'])
				echo "Creating table cache\n";
			$table_cache = array();
		}

		update_filter_reload_status("Creating filter rules {$rule['descr']} ...");

		$wancfg = $config['interfaces']['wan'];
		if($config['interfaces']['lan']) {
				$lancfg = $config['interfaces']['lan'];
				$lanif = $lancfg['if'];
				$lanip = $lancfg['ipaddr'];
				$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
				$lansn = $lancfg['subnet'];
		}
		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];

		$wanif = get_real_wan_interface();

		$int = "";

		$optcfg = array();
		generate_optcfg_array($optcfg);

		$curwanip = get_current_wan_address();

		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			return "# rule " . $rule['descr'] . " disabled \n";
		}

		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];

		if ($pptpdcfg['mode'] == "server") {
			$pptpip = $pptpdcfg['localip'];
			$pptpsa = $pptpdcfg['remoteip'];
			$pptpsn = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptpsn = $config['pptp']['pptp_subnet'];
		}

		if ($pppoecfg['mode'] == "server") {
			$pppoeip = $pppoecfg['localip'];
			$pppoesa = $pppoecfg['remoteip'];
			$pppoesn = $g['pppoe_subnet'];
			if($config['pppoe']['pppoe_subnet'] <> "")
				$pppoesn = $config['pppoe']['pppoe_subnet'];
		}

		/* does the rule deal with a PPTP interface? */
		if ($rule['interface'] == "pptp") {
			if ($pptpdcfg['mode'] != "server")
				return "";
			$nif = $g['n_pptp_units'];
			if($config['pptp']['n_pptp_units'] <> "")
				$nif = $config['pptp']['n_pptp_units'];
			$ispptp = true;
		} else if($rule['interface'] == "pppoe") {
			if ($pppoecfg['mode'] != "server") {
				return " # Error creating pppoe rule";
			}
			$nif = $g['n_pppoe_units'];
			if($config['pppoe']['n_pppoe_units'] <> "")
				$nif = $config['pppoe']['n_pppoe_units'];
			$ispppoe = true;
		} else {

			/* Check to see if the interface is opt and in our opt list */
			if (!isset($rule['floating']) && strstr($rule['interface'], "opt")) {
 				if (!array_key_exists($rule['interface'], $optcfg)) {
					$item = "";
					foreach($optcfg as $oc) $item .= $oc['if'];
					return "# {$real_int} {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
				}
			}

			$nif = 1;
			$ispptp = false;
			$ispppoe = false;
		}

		if ($pptpdcfg['mode'] != "server") {
			if (($rule['source']['network'] == "pptp") ||
				($rule['destination']['network'] == "pptp")) {
					return "# source network or destination network == pptp on " . $rule['descr'];
				}
		}

		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $optcfg)) {
				$optmatch = "";
				if(preg_match("/opt([0-999])/", $rule['source']['network'], $optmatch)) {
					$real_opt_int = convert_friendly_interface_to_real_interface_name("opt" . $optmatch[1]);
					$opt_ip = find_interface_ip($real_opt_int);
					if(!$opt_ip)
						return "# unresolvable optarray $real_opt_int - $optmatch[0] - $opt_ip";
				} else {
					return "# {$rule['source']['network']} !array_key_exists source network " . $rule['descr'];
				}
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $optcfg)) {
				if(preg_match("/opt([0-999])/", $rule['destination']['network'], $optmatch)) {
					$real_opt_int = convert_friendly_interface_to_real_interface_name("opt" . $optmatch[1]);
					$opt_ip = find_interface_ip($real_opt_int);
					if(!$opt_ip)
						return "# unresolvable oparray $real_opt_int - $optmatch[0] - $opt_ip";
				} else {
					return "# {$item} {$rule['destination']['network']} !array_key_exists dest network " . $rule['descr'];
				}
			}
		}

		/* check for unresolvable aliases */
		if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
			file_notice("Filter_Reload", "# unresolvable source aliases {$rule['descr']}");
			return "# unresolvable source aliases {$rule['descr']}";
		}
		if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
			file_notice("Filter_Reload", "# unresolvable dest aliases {$rule['descr']}");
			return "# unresolvable dest aliases {$rule['descr']}";
		}

		/* optional interface if list */
        	$ifdescrs = get_configured_interface_list(true);

		update_filter_reload_status("Setting up pass/block rules");

		for ($iif = 0; $iif < $nif; $iif++) {

			$type = $rule['type'];


			if ($type != "pass" && $type != "block" && $type != "reject") {
				/* default (for older rules) is pass */
				$type = "pass ";
			}

			if ($type == "reject") {
				/* special reject packet */
				if ($rule['protocol'] == "tcp") {
					$aline['type'] = "block return-rst ";
				} else if ($rule['protocol'] == "udp") {
					$aline['type'] = "block return-icmp ";
				} else if ($rule['protocol'] == "tcp/udp") {
                                        $aline['type'] = "block return ";
				} else {
					$aline['type'] = "block ";
				}
			} else {
				$aline['type'] = $type . " ";
			}

			if (isset($rule['floating']) && $rule['floating'] == "yes") {
	                       	if ($rule['direction'] != "any")
                               	$aline['direction'] = " " . $rule['direction'] . " ";
        	       	} else {
                        	/* ensure the direction is in */
                        	$aline['direction'] = " in ";
               		}

			if (isset($rule['log']))
				$aline['log'] = "log ";

			if (!isset($rule['floating']) || isset($rule['quick']))
				$aline['quick'] = " quick ";

			if ($ispptp) {
				$aline['interface'] = "on \$pptp ";
			} else if ($ispppoe) {
				$aline['interface'] = "on \$pppoe ";
			} else if ($rule['interface'] == "openvpn") {
				$aline['interface'] = "on openvpn ";
			} else if (isset($rule['floating'])) {
                 		if (isset($rule['interface']) && $rule['interface'] <> "") {
                     			$interfaces = explode(",", $rule['interface']);
		                       $aline['interface'] = " on { ";
                		       foreach ($interfaces as $iface) {
						if ($iface == "openvpn")
							$aline['interface'] = " openvpn ";
						else 
                             			$aline['interface'] .= " " .convert_friendly_interface_to_real_interface_name($iface) . " ";

                 		       }
                       			$aline['interface'] .= " }";
             		    	}
			} else {
				// translate wan, man, lan, opt to real interface.
				$interface = $rule['interface'];
				$temp = filter_get_opt_interface_descr($interface);
				if($temp <> "" && $interface != "lan" && $interface != "wan")
					$interface = $temp;
				if(isset($rule['destination']['address'])) {
					$canadd = 0; // XXX: billm - eh?  this is a nice little noop
					/* because pf will not allow a interface for proxyARP
					   type traffic lets check if its in use and if so leave
                                           off the interface */
					if(is_one_to_one_or_server_nat_rule($rule['destination']['address']))
						$canadd = 0;
				}
				if($canadd == 0 && !isset($rule['floating']))
					$aline['interface'] = "on \$" . $interface . " ";
			}


			/* set the gateway interface */
			$ri = filter_translate_type_to_real_interface($rule['interface']);

			update_filter_reload_status("Setting up pass/block rules {$rule['descr']}");

			/*
			 *    check to see if /tmp/{${ri}_router exists.  This file
			 *    is created by dhclient for 2nd wan interfaces, etc.
			 *    else get gateway from the interface config
			 */
			if(file_exists("{$g['tmp_path']}/{$ri}_router")) {
				$rg = file_get_contents("{$g['tmp_path']}/{$ri}_router");
				$rg = rtrim($rg);
			} elseif ($config['interfaces'][$rule['interface']]['gateway'] <> "")  {
				$rgwy = $config['interfaces'][$rule['interface']]['gateway'];
				if (is_array($config['gateways']['gateway_item'])) {
                                    foreach($config['gateways']['gateway_item'] as $gwy) {
                                        if ($gwy['name'] == $rgw) {
						$rg = $gwy['gateway'];
						break;
					}
                                    }
                                }
			}

			/* do not process reply-to for gateway'd rules */
			if ($rule['gateway'] == "") {
				if (is_array($config['gateways']['gateway_item'])) {
                                    foreach($config['gateways']['gateway_item'] as $gwy) {
    					if ($gwy['name'] == $config['interfaces'][$rule['interface']]['gateway']) {
    						$rg = $gwy['gateway'];
    						$aline['reply'] = "reply-to (" . $ri . " " . $rg . ") ";
    						break;
                                            }
                                    }
                                }
			}

			/* if user has selected a custom gateway, lets work with it */
			if($rule['gateway'] <> "") {
				$foundlb = 0;
				$routeto = " route-to { ";
				if(is_array($config['gateways']['gateway_group'])) {
					foreach($config['gateways']['gateway_group'] as $group) {
						update_filter_reload_status("Creating gateway group item...");
						if($group['name'] == $rule['gateway']) {
							$gateway = $rule['gateway'];
							/*
							*    is $gateway a interface name?
							*    if so, lets find out the gateway address
							*    from /tmp/router_bleh.router
							*/
							/* FIXME: this probably does not work with the new gateway code */
							if(in_array($gateway, $ifdescrs) == true) {
								if(is_file("{$g['tmp_path']}/{$gateway}_router")) {
									$return_gateway = file_get_contents("{$g['tmp_path']}/{$gateway}_router");
								} else {
									log_error("Could not find {$g['tmp_path']}/{$gateway}_router.  Needed for dhcp gateway information");
									continue;
								}
							}

							/* fetch the current gateways status */
							$gateways_status = return_gateways_status();
							/* create array with group gateways members seperated by tier */
							$tiers = array();
							foreach($group['item'] as $item) {
								$itemsplit = explode("|", $item);
								$tier = $itemsplit[1];
								$gwname = $itemsplit[0];
								/* check if the gateway is available before adding it to the array */
								foreach($gateways_status as $status) {
									/* FIXME: possibly trigger on "delay" or "loss" in the future as well "triggerlevel??" */
									if(($status['name'] == $gwname) && ($status['status'] == "None")) {
										$tiers[$tier][] = $gwname;
									}
								}
							}

							$tiers_count = count($tiers);

							/* we do not really foreach the tiers as we stop after the first tier */
							foreach($tiers as $tier) {
								/* process all gateways in this tier */
								$member_count = count($tier);
								foreach($tier as $member) {
									/* determine interface gateway */
									foreach($config['gateways']['gateway_item'] as $gateway) {
										if($gateway['name'] == $member) {
											$friendly_if = $gateway['interface'];
											if(is_ipaddr($gateway['gateway'])) {
												$gatewayip = $gateway['gateway'];
												$int = convert_friendly_interface_to_real_interface_name($friendly_if);
											} else {
												$gatewayip = lookup_gateway_ip_by_name($friendly_if);
												$int = convert_friendly_interface_to_real_interface_name($friendly_if);
											}
										}
									}
									if(($int <> "") && ($gatewayip <> "")) {
										if($g['debug'])
											log_error("Setting up route with {$gatewayip} om $int");
										if($foundlb == 1)
											$routeto .= ", ";
										$routeto .=  "( {$int} {$gatewayip} ) ";
										$foundlb = 1;
									}
								}
								/* we should have the 1st available tier now */
								break;
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
					}
					/* Add the load balanced gateways */
					if ($foundlb == 1)
						$aline['route'] = $routeto;
				}

				/*  we're not using load balancing, just setup gateway */
				if($foundlb == 0) {
					$gateway = $rule['gateway'];
					/*
					*    is $gateway a interface name?
					*    if so, lets find out the gateway address
					*    from /tmp/router_bleh.router
					*/
					if(in_array($gateway, $ifdescrs)==true) {
						$int=filter_opt_interface_to_real($gateway);
						if(is_file("{$g['tmp_path']}/{$int}_router")) {
							$gateway = file_get_contents("{$g['tmp_path']}/{$int}_router");
							$gateway = rtrim($gateway);
							if(!is_ipaddr($gateway)) {
								$gateway = lookup_gateway_ip_by_name($gateway);
							}
							if (is_ipaddr($gateway)) {
								$aline['route'] = " route-to ( {$int} {$gateway} ) ";
							}
						} else {
							log_error("Could not find {$g['tmp_path']}/{$int}_router.  Needed for dhcp gateway information");
							continue;
						}
					} else {
						if(!is_ipaddr($gateway)) {
							$gateway = lookup_gateway_ip_by_name($rule['gateway']);
						}
						/* user picked a real gateway ip */
						$gateway = lookup_gateway_ip_by_name($rule['gateway']);
						if(is_ipaddr($gateway)) {
							$int = guess_interface_from_ip($gateway);
							$aline['route'] = " route-to ( " . guess_interface_from_ip($gateway) . " {$gateway} ) ";
						}					
					}
				}
			}

			if (isset($rule['protocol'])) {
				if($rule['protocol'] == "tcp/udp")
					$aline['prot'] = " proto { tcp udp } ";
				elseif($rule['protocol'] == "icmp")
					$aline['prot'] = " inet proto icmp ";
				else
					$aline['prot'] = " proto {$rule['protocol']} ";
			} else {
				if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "") {
					$aline['prot'] = " proto tcp ";
				}
			}

			update_filter_reload_status("Creating rule {$rule['descr']}");

			/* source address */
			if (isset($rule['source']['any'])) {
				$src = "any";
			} else if ($rule['source']['network']) {

				if (strstr($rule['source']['network'], "opt")) {
					$src = $optcfg[$rule['source']['network']]['sa'] . "/" .
						$optcfg[$rule['source']['network']]['sn'];
					if (isset($rule['source']['not'])) $src = " !{$src}";
					/* check for opt$NUMip here */
					$matches = "";
					if (preg_match("/opt([0-9999])ip/", $rule['source']['network'], $matches)) {
						$optnum = $matches[1];
						$real_int = convert_friendly_interface_to_real_interface_name("opt{$optnum}");
						$src = find_interface_ip($real_int);
					}
				} else {
					switch ($rule['source']['network']) {
						case 'wanip':
							$src = $curwanip;
							break;
						case 'lanip':
							$src = $lanip;
							break;
						case 'lan':
							$src = "{$lansa}/{$lansn}";
							break;
						case 'pptp':
							$src = "{$pptpsa}/{$pptpsn}";
							break;
						case 'pppoe':
							$src = "{$pppoesa}/{$pppoesn}";
							break;
					}
					if (isset($rule['source']['not'])) $src = "!{$src}";
				}
			} else if ($rule['source']['address']) {
				$expsrc = alias_expand($rule['source']['address']);

				if (isset($rule['source']['not']))
					$not = "!";
				else
					$not = "";

				if (stristr($expsrc, "$")) {
					if($not) {
						$src = "{";
						foreach(preg_split("/[\s]+/", alias_expand_value($rule['source']['address'])) as $item) {
							if($item != "") {
								$src .= " {$not}{$item}";
							}
						}
						/* added support for tables */
						$src .= " 0/0 }";
						$src_table = "<not" . $rule['source']['address'] . ">";
					}
					else {
						$src = "{ {$not} " . alias_expand_value($rule['source']['address']) . " } ";
						$src_table = "<" . $rule['source']['address'] . ">";
					}

					/* support for tables */
					$src_table_line = "table $src_table {$src}\n";
					$src = $src_table;
				}
				else
					$src = "{ {$not} {$expsrc} }";
			}

			if (!$src || ($src == "/")) {
				return "# at the break!";
			}

			$aline['src'] = " from $src ";

			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {

				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);
					if(alias_expand($srcport[0]))
						$srcporta = alias_expand($srcport[0]);
					else
						$srcporta = $srcport[0];
					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						if(alias_expand($srcport[0]))
							$aline['srcport'] = " port {$srcporta} ";
						else
							$aline['srcport'] = " port = {$srcporta} ";
					} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($srcport[1] == 65535) {
						$aline['srcport'] = "port >= {$srcport[0]} ";
					} else if ($srcport[0] == 1) {
						$aline['srcport']= "port <= {$srcport[1]} ";
					} else {
						$srcport[0]--;
						$srcport[1]++;
						$aline['srcport'] = " port {$srcport[0]} >< {$srcport[1]} ";
					}
				}
                                /* OS signatures */
                                if (($rule['protocol'] == "tcp") && ($rule['os'] <> ""))
                                        $aline['os'] = " os {$rule['os']} ";

			}

			/* destination address */
			if (isset($rule['destination']['any'])) {
				$dst = "any";
			} else if ($rule['destination']['network']) {

				if (strstr($rule['destination']['network'], "opt")) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
					/* check for opt$NUMip here */
					$matches = "";
					if (preg_match("/opt([0-9999])ip/", $rule['destination']['network'], $matches)) {
						$optnum = $matches[1];
						$real_int = convert_friendly_interface_to_real_interface_name("opt{$optnum}");
						$dst = find_interface_ip($real_int);
					}
					if (isset($rule['destination']['not'])) $dst = " !{$dst}";
				} else {
					switch ($rule['destination']['network']) {
						case 'wanip':
							$dst = $curwanip;
							break;
						case 'lanip':
							$dst = $lanip;
							break;
						case 'lan':
							$dst = "{$lansa}/{$lansn}";
							break;
						case 'pptp':
							$dst = "{$pptpsa}/{$pptpsn}";
							break;
						case 'pppoe':
							$dst = "{$ppoesa}/{$pppoesn}";
							break;
					}
					if (isset($rule['destination']['not'])) $dst = " !{$dst}";
				}
			} else if ($rule['destination']['address']) {
				$expdst = alias_expand($rule['destination']['address']);

				if (isset($rule['destination']['not']))
					$not = "!";
				else
					$not = "";

                                if (stristr($expdst, "$")) {
                                        if($not) {
                                                $dst = "{";
                                                foreach(preg_split("/[\s]+/", alias_expand_value($rule['destination']['address'])) as $item) {
                                                        if($item != "") {
                                                                $dst .= " {$not}{$item}";
                                                        }
                                                }
                                                /* added support for tables */
						$dst .= " 0/0 }";
						$dst_table = "<not" . $rule['destination']['address'] . ">";
                                        }
                                        else {
                                                $dst = "{ {$not} " . alias_expand_value($rule['destination']['address']) . " } ";
						$dst_table = "<" . $rule['destination']['address'] . ">";
                                        }

					/* support for tables */
					$dst_table_line = "table $dst_table {$dst}\n";
					$dst = $dst_table;
                                }
				else
					$dst = "{ {$not} {$expdst} }";
			}

			if (!$dst || ($dst == "/")) {
				return "# returning at dst $dst == \"/\"";
			}

			$aline['dst'] = "to $dst ";

			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {

				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);
					if(alias_expand($dstport[0]))
						$dstporta = alias_expand($dstport[0]);
					else
						$dstporta = $dstport[0];
					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						if(alias_expand($dstport[0]))
							$aline['dstport'] = " port {$dstporta} ";
						else
							$aline['dstport'] = "port = {$dstporta} ";
					} else if (($dstport[0] == 1) && ($dstport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($dstport[1] == 65535) {
						$aline['dstport'] = " port >= {$dstport[0]} ";
					} else if ($dstport[0] == 1) {
						$aline['dstport'] = " port <= {$dstport[1]} ";
					}  else {
						$dstport[0]--;
						$dstport[1]++;
						$aline['dstport'] = " port {$dstport[0]} >< {$dstport[1]} ";
					}
				}
			}

			if (($rule['protocol'] == "icmp") && $rule['icmptype']) {
				$aline['icmp-type'] = "icmp-type {$rule['icmptype']} ";
			}

			if ($type == "pass") {

				if (isset($rule['floating'])) {
                       			if (isset($rule['tag']) && $rule['tag'] <> "")
                               			$aline['tag'] = " tag " .$rule['tag']. " ";
                       			if (isset($rule['tagged']) && $rule['tagged'] <> "")
                               			$aline['tagged'] = " tagged " .$rule['tagged'] . " ";
               			}

				if (isset($rule['dscp']) && $rule['dscp'] <> "")
					$aline['dscp'] = " dscp " . $rule['dscp'] . " ";

				if( isset($rule['source-track']) or isset($rule['max-src-nodes']) or isset($rule['max-src-states']) )
					if($rule['protocol'] == "tcp")
						$aline['flags'] = "flags S/SA ";
				/*
					# keep state
						works with TCP, UDP, and ICMP.
					# modulate state
						works only with TCP. pfSense will generate strong Initial Sequence Numbers (ISNs)
						for packets matching this rule.
					# synproxy state
						proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods.
						This option includes the functionality of keep state and modulate state combined.
					# none
						do not use state mechanisms to keep track. this is only useful if your doing advanced
						queueing in certain situations. please check the faq.
				*/
				$noadvoptions = false;
				if(isset($rule['Statetype']) && $rule['statetype'] <> "") {
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
				} else {
					$aline['flags'] = "keep state ";
				}
				if($noadvoptions == false) 
					if( isset($rule['source-track']) and $rule['source-track'] <> "" or
					  isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "" or
					   isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "" or
					    isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "" or
					     isset($rule['max-src-states']) and $rule['max-src-states'] <> "" or
					      isset($rule['statetimeout']) and $rule['statetimeout'] <> "") {
						$aline['flags'] .= "( ";
						if(isset($rule['source-track']) and $rule['source-track'] <> "")
							$aline['flags'] .= "source-track rule ";
						if(isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "")
							$aline['flags'] .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
						if(isset($rule['max-src-states']) and $rule['max-src-states'] <> "")
							$aline['flags'] .= "max-src-states " . $rule['max-src-states'] . " ";
						if(isset($rule['statetimeout']) and $rule['statetimeout'] <> "")
							$aline['flags'] .= "tcp.established " . $rule['statetimeout'] . " ";
						if(isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> ""
						   and isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "") {
							$aline['flags'] .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";
							$aline['flags'] .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
						   }
						$aline['flags'] .= " ) ";
					}
			}
			if ($type == "reject" && $rule['protocol'] == "tcp") {
				/* special reject packet */
				$aline['flags'] .= "flags S/SA ";
			}
			
			if ($type == "pass") {
				if ($rule['defaultqueue'] <> "") {
					$aline['queue'] = " queue (".$rule['defaultqueue'];
					if ($rule['ackqueue'] <> "")
						$aline['queue'] .= ",".$rule['ackqueue'];
					$aline['queue'] .= ") "; 
				}
			}
	}

	/* cache entries */
	if (isset($src_table))
		if (isset($table_cache[$src_table])) {
			if ($g['debug'])
				echo "{$src_table} found in cache\n";
		} else {
			if ($g['debug'])
				echo "{$src_table} NOT found in cache...adding\n";
			$table_cache[$src_table] = $src_table_line;
		}
	if (isset($dst_table))
		if (isset($table_cache[$dst_table])) {
			if ($g['debug'])
				echo "{$dst_table} found in cache\n";
		} else {
			if ($g['debug'])
				echo "{$dst_table} NOT found in cache...adding\n";
			$table_cache[$dst_table] = $dst_table_line;
		}

	/* exception(s) to a user rules can go here. */
	/* rules with a gateway or pool should create another rule for routing to local networks or vpns */
	/* we only trigger this for a rule with the destination of any and without a gateway */
	if (($aline['route'] <> "") && (trim($aline['type']) == "pass") && (trim($dst) == "any")) {
		/* negate VPN/PPTP/PPPoE networks for load balancer/gateway rules */
		$vpns = " to <vpns> ";
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] . $aline['prot'] .
			$aline['src'] . $aline['srcport'] . $aline['os'] . $vpns . $aline['dstport'].
			$aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] . $aline['flags'].
			$aline['queue'] . " label \"NEGATE_ROUTE: Negate policy route for local network(s)\"\n";
		/* negate directly connected networks for load balancer/gateway rules */
		$direct_networks = " to <direct_networks> ";
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] . $aline['prot'] .
			$aline['src'] . $aline['srcport'] . $aline['os'] . $direct_networks . $aline['dstport'].
			$aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] . 
			$aline['flags'] . $aline['queue'] .
			" label \"NEGATE_ROUTE: Negate policy route for local network(s)\"\n";
	}

	/* piece together the actual user rule */
	$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] . $aline['reply'] .
		$aline['route'] . $aline['prot'] . $aline['src'] . $aline['srcport'] . $aline['os'] . $aline['dst'] .
		$aline['dstport'] . $aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] . 
		$aline['flags'] . $aline['queue'];

	/* is a time based rule schedule attached? */
	if($rule['sched']) {
		if($config['schedules']) {
			foreach($config['schedules']['schedule'] as $sched) {
				if($sched['name'] == $rule['sched'])
					$schedule_xml_block = $sched;					
					$schedule_enabled = true;
			}
		}
		if($schedule_xml_block)
			$status = get_time_based_rule_status($schedule_xml_block);
		if($status) {
			if($g['debug'])
				log_error("[TDR DEBUG] status true -- rule type '$type'");
			if($type == "block") {
				// active deny rules should deny
				$ipfw_rule = tdr_create_ipfw_rule($rule, "deny");
				tdr_install_rule($ipfw_rule);
			} else {
				// active allow rules should allow
				$ipfw_rule = tdr_create_ipfw_rule($rule, "allow");
				tdr_install_rule($ipfw_rule);
			}
			return "$line";
		} else {
            /*   rule is turned off, if type == pass, deny traffic until 
             *   active else allow traffic until active 
             */
            if($type == "pass") {
            	// inactive pass rules should deny
				$ipfw_rule = tdr_create_ipfw_rule($rule, "deny");
				tdr_install_rule($ipfw_rule);
			} else {
				// inactive block rules should skipto
				$ipfw_rule = tdr_create_ipfw_rule($rule, "skipto");
				tdr_install_rule($ipfw_rule);			
			}
			return "# $line";
		}
	} else {
		if($schedule_enabled) {
			// no schedule allow rules should simply allow
			$ipfw_rule = tdr_create_ipfw_rule($rule, "allow");
			tdr_install_rule($ipfw_rule);			
		}
		return $line;			
	}
}

function filter_rules_generate() {
	global $config, $g, $table_cache, $used_pftpx_ports;

	update_filter_reload_status("Creating default rules");

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$wancfg = $config['interfaces']['wan'];
	if($config['interfaces']['lan']) {
		$lancfg = $config['interfaces']['lan'];
		$lanif = $lancfg['if'];
		$lanip = $lancfg['ipaddr'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
		$lansn = $lancfg['subnet'];
	}

	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];

	$wanif = get_real_wan_interface();
	
	$wanip = find_interface_ip(get_real_wan_interface());

	if($config['interfaces']['lan']) 
		if($lansa)
			$lansa_sn_combo = "{$lansa}/{$lansn}";
		else
			$lansa_sn_combo = "192.168.1.1/32";

	/* optional interfaces */
	$optcfg = array();
	generate_optcfg_array($optcfg);

	if (is_package_installed('clamav') && file_exists('/usr/local/pkg/clamav.inc')) {
		require_once('clamav.inc');
		$ipfrules .= clamav_generate_rules('filter');
	}

	if (is_package_installed('squid') && file_exists('/usr/local/pkg/squid.inc')) {
		require_once('squid.inc');
		$ipfrules .= squid_generate_rules('filter');
	}

	if (is_package_installed('frickin') && file_exists('/usr/local/pkg/frickin.inc')) {
		require_once ('frickin.inc');
		$ipfrules .= frickin_generate_rules('filter');
	}
        if (is_package_installed('siproxd') && file_exists('/usr/local/pkg/siproxd.inc')) {
                require_once('siproxd.inc');
                $ipfrules .= siproxd_generate_rules('filter');
        }

	/* if captive portal is enabled, ensure that access to this port
	 * is allowed on a locked down interface
	 */
	if (isset($config['captiveportal']['enable'])) {
		$cp_interface = $config['captiveportal']['interface'];
		$cp_interface_real = convert_friendly_interface_to_real_interface_name($cp_interface);
		$cp_interface_ip = find_interface_ip($cp_interface_real);
		if($cp_interface_ip and $cp_interface_real)
			$ipfrules .= "pass in quick on {$cp_interface_real} proto tcp from any to {$cp_interface_ip} port { 8000 8001 } keep state\n";
	}

	/* ftp-sesame */
	$ipfrules .= "anchor \"ftpsesame/*\" \n";

	/* relayd */
        $ipfrules .= "anchor \"relayd/*\"\n";

	# BEGIN OF firewall rules
	$ipfrules .= "anchor \"firewallrules\"\n";

	if ($pptpdcfg['mode'] == "server") {
		$pptpip = $pptpdcfg['localip'];
		$pptpsa = $pptpdcfg['remoteip'];
		$pptpsn = $g['pptp_subnet'];
		if($config['pptp']['pptp_subnet'] <> "")
			$pptpsn = $config['pptp']['pptp_subnet'];
	}

	if ($pppoecfg['mode'] == "server") {
		$pppoeip = $pppoecfg['localip'];
		$pppoesa = $pppoecfg['remoteip'];
		$pppoesn = $g['pppoe_subnet'];
		if($config['pppoe']['pppoe_subnet'] <> "")
			$pppoesn = $config['pppoe']['pppoe_subnet'];
	}

	/* default block logging? */
	if (!isset($config['syslog']['nologdefaultblock']))
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

# snort2c
table <snort2c> persist
block quick from <snort2c> to any label "Block snort2c hosts"
block quick from any to <snort2c> label "Block snort2c hosts"



# loopback
anchor "loopback"
pass in on \$loopback all label "pass loopback"
pass out on \$loopback all label "pass loopback"

# package manager early specific hook
anchor "packageearly"

# carp
anchor "carp"

EOD;

	/*
	 * Support for allow limiting of TCP connections by establishment rate
         * Useful for protecting against sudden outburts, etc.
	 */
	$ipfrules .= <<<EODF
table <virusprot>
block in quick from <virusprot> to any label "virusprot overload table"

EODF;

	/* block bogon networks on WAN */
	/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
	/* file is automatically in cron every 3000 minutes */
	if (isset($config['interfaces']['wan']['blockbogons'])) {
		$ipfrules .= <<<EOD

# block bogon networks
# http://www.cymru.com/Documents/bogon-bn-nonagg.txt
anchor "wanbogons"
table <bogons> persist file "/etc/bogons"
block in $log quick on \$wan from <bogons> to any label "block bogon networks from wan"

EOD;
	}

	/* install wan spoof check rule if lan address exists */
	if($config['interfaces']['lan']) 
		if($lansa) {
			if(!isset($config['interfaces']['wan']['spoofmac'])) {
				$ipfrules .= <<<EOD

# WAN spoof check
anchor "wanspoof"
block in $log quick on \$wan from $lansa/$lansn to any label "WAN spoof check"

EOD;

		}
	}

	foreach ($optcfg as $oc) {
		if (!$oc['bridge'] && $oc['sa'] <> "")
			$ipfrules .= "block in $log quick on \$wan from {$oc['sa']}/{$oc['sn']} to any label \"interface spoof check\"\n";
	}
	if($config['interfaces']['lan']) 	
		if($config['interfaces']['lan']['bridge'] <> "wan" and 
			$config['interfaces']['wan']['bridge'] <> "lan")
			$ipfrules .= "block in $log quick on \$wan proto udp from any port = 67 to {$lansa_sn_combo} port = 68 label \"block dhcp client out wan\"\n";

	$ipfrules .= <<<EOD

# LAN/OPT spoof check (needs to be after DHCP because of broadcast addresses)

EOD;

	/* LAN spoof check */
	$lanbridge = false;
	$wanbridge = false;
	foreach($config['interfaces'] as $int) {
		if($config['interfaces']['lan']) {
			if($int['bridge'] == "lan")
				$lanbridge = true;
		}
		if($int['bridge'] == "wan")
			$wanbridge = true;	
	}		

	/* XXX: ermal - needed?! */
	if($config['interfaces']['lan']['bridge'] == "wan") 
		$wanbridge = true;

	if($config['interfaces']['lan']) 			
		if(!$lanbridge)
			$ipfrules .= filter_rules_spoofcheck_generate('lan', $lanif, $lansa, $lansn, $log);

	/* OPT spoof check */
	foreach ($optcfg as $on => $oc) {
   		$isbridged = false;
		foreach ($optcfg as $on2 => $oc2) {
			if ($oc2['bridge'] && $oc2['bridge'] == $on) {
				$isbridged = true;
				break;
			}
		}
		if ($oc['ip'] && !(($oc['bridge'] || $isbridged) && isset($config['bridge']['filteringbridge'])))
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);
	}
	$ipfrules .= "\nanchor \"spoofing\"\n";

	/* block private networks on WAN? */
	if (isset($config['interfaces']['wan']['blockpriv'])) {
		if($wanbridge == false) {
			$ipfrules .= <<<EOD

# block anything from private networks on WAN interface
antispoof for \$wan
block in $log quick on \$wan from 10.0.0.0/8 to any label "block private networks from wan block 10/8"
block in $log quick on \$wan from 127.0.0.0/8 to any label "block private networks from wan block 127/8"
block in $log quick on \$wan from 172.16.0.0/12 to any label "block private networks from wan block 172.16/12"
block in $log quick on \$wan from 192.168.0.0/16 to any label "block private networks from wan block 192.168/16"

EOD;

		}
	}



if($wanip)
 	$ipfrules .= <<<EOD

# permit wan interface to ping out (ping_hosts.sh)
pass proto icmp from {$wanip} to any keep state

EOD;

	$ipfrules .= <<<EOD

anchor "firewallout"
# let out anything from the firewall host itself and decrypted IPsec traffic
pass out all keep state label "let out anything from firewall host itself"

EOD;

	/* permit internal ipsec outbound traffic */
        $ipfrules .="pass out on \$enc0 keep state label \"IPsec internal host to host\"";


	/* allow PPTP traffic if PPTP client is enabled on WAN */
	if ($wancfg['ipaddr'] == "pptp") {
		$ipfrules .= <<<EOD

# allow PPTP client
anchor "pptpclient"
pass in on \$wan proto gre from any to any modulate state label "allow PPTP client"
pass in on \$wan proto gre from any to any modulate state label "allow PPTP client"
pass in on \$wan proto tcp from any port = 1723 to any flags S/SA modulate state label "allow PPTP client"
pass in on \$wan proto tcp from any to any port = 1723 flags S/SA modulate state label "allow PPTP client"

EOD;
	}


	if (!isset($config['system']['webgui']['noantilockout'])) {

		if($config['interfaces']['lan']) {
			if($lansa and $lansn) {
				/* if antilockout is enabled, LAN exists and has
                                  an IP and subnet mask assigned */
				$ipfrules .= <<<EOD

# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on $lanif from any to $lanip keep state label "anti-lockout rule"

EOD;
			}
                } else {
			/* single-interface deployment, add to WAN  */
			$ipfrules .= <<<EOD

# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on $wanif from any to $wanip keep state label "anti-lockout rule"
EOD;

		}
        }

	/* PPTPd enabled? */
	if ($pptpdcfg['mode'] && ($pptpdcfg['mode'] != "off")) {

		if ($pptpdcfg['mode'] == "server")
			$pptpdtarget = get_current_wan_address();
		else
			$pptpdtarget = $pptpdcfg['redir'];

		if($pptpdtarget) {
			$ipfrules .= <<<EOD

# PPTPd rules
anchor "pptp"
pass in on \$wan proto gre from any to $pptpdtarget keep state label "allow gre pptpd"
pass in on \$wan proto tcp from any to $pptpdtarget port = 1723 modulate state label "allow pptpd {$pptpdtarget}"

EOD;

		} else {
			/*    this shouldnt ever happen but instead of breaking the clients ruleset
			 *    log an error.
                         */
			log_error("ERROR!  PPTP enabled but could not resolve the \$pptpdtarget");
		}
	}

	/* BigPond client enabled? */
	if ($wancfg['ipaddr'] == "bigpond") {

		$ipfrules .= <<<EOD

# BigPond heartbeat rules
anchor "bigpond"
pass in quick proto udp from any to any port = 5050 keep state label "BigPond heartbeat"
EOD;
}

        $ipfrules .= <<<EOD

# NAT Reflection rules

EOD;

        if (isset($config['nat']['rule'])) {
                $natrules .= "# NAT Inbound Redirects\n";

                if(!isset($config['system']['disablenatreflection'])) {
                        //$fd = fopen("/var/etc/inetd.conf","w");
                        /* start redirects on port 19000 of localhost */
                        $starting_localhost_port = 18999;
                }

                foreach ($config['nat']['rule'] as $rule) {

                        update_filter_reload_status("Creating NAT rule {$rule['descr']}");

                        /* if item is an alias, expand */
                        if(alias_expand($rule['external-port']))
                                $extport[0] = alias_expand_value($rule['external-port']);
                        else
                                $extport = explode("-", $rule['external-port']);

                        /* if item is an alias, expand */
                        if(alias_expand($rule['local-port']))
                                $localport = "";
                        else
                                $localport = " port {$rule['local-port']}";

                        $target = alias_expand_host($rule['target']);

                        if (!$target)
                                continue;       /* unresolvable alias */

                        if ($rule['external-address'])
                                if($rule['external-address'] <> "any")
                                        $extaddr = $rule['external-address'] . "/32";
                                else
                                        $extaddr = $rule['external-address'];
                        else
                                $extaddr = get_current_wan_address($rule['interface']);

                        if (!$rule['interface'] || ($rule['interface'] == "wan"))
                                $natif = $wanif;
                        else if($rule['interface'] == "\$pptp")
                                $natif = "pptp";
                        else if($rule['interface'] == "\$pppoe")
                                $natif = "pppoe";
                        else
                                $natif = $config['interfaces'][$rule['interface']]['if'];

						if($config['interfaces']['lan']) 
                        	$lanif = $lancfg['if'];

                        /*
                         *   Expand aliases
                         *   XXX: may want to integrate this into pf macros
                         */
                        if(alias_expand($target))
                                $target = alias_expand($target);
                        if(alias_expand($extaddr))
                                $extaddr = alias_expand($extaddr);

                        if(!isset($config['system']['disablenatreflection'])) {

			/* if list */
        		$iflist = get_configured_interface_list();

                        foreach ($iflist as $ifent => $ifname) {

                                /* do not process interfaces with gateways*/
                                if($config['interfaces'][$ifname]['gateway'] <> "")
                                        continue;

                                        /* do not process interfaces that will end up with gateways */
                                        if($config['interfaces'][$ifname]['ipaddr'] == "dhcp" or
                                           $config['interfaces'][$ifname]['ipaddr'] == "bigpond" or
                                           $config['interfaces'][$ifname]['ipaddr'] == "pppoe" or
                                           $config['interfaces'][$ifname]['ipaddr'] == "pptp")

                                                continue;

                                        $ifname_real = convert_friendly_interface_to_real_interface_name($ifname);

                                        if($extport[1])
                                                $range_end = ($extport[1]);
                                        else
                                                $range_end = ($extport[0]);

                                        $range_end++;

                                        if($rule['local-port'])
                                                $lrange_start = $rule['local-port'];

                                        if($range_end - $extport[0] > 500) {
                                                $range_end = $extport[0]+1;
                                                log_error("Not installing nat reflection rules for a port range > 500");
                                        } else {
                                                /* only install reflection rules for < 19991 items */
                                                if($starting_localhost_port < 19991) {
                                                        $loc_pt = $lrange_start;
                                                        for($x=$extport[0]; $x<$range_end; $x++) {

                                                                $starting_localhost_port++;
                                                                $ifname_real = convert_friendly_interface_to_friendly_descr(strtolower($ifname));

                                                                switch($rule['protocol']) {
                                                                        case "tcp/udp":
                                                                                $protocol = "{ tcp udp }";
                                                                                $ipfrules .= "pass in on \${$ifname_real} inet proto tcp from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
                                                                                $starting_localhost_port++;
                                                                                $ipfrules .= "pass in on \${$ifname_real} inet proto udp from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
                                                                                break;
                                                                        case "tcp":
                                                                        case "udp":
                                                                                $protocol = $rule['protocol'];
                                                                                $ipfrules .= "pass in on \${$ifname_real} inet proto {$rule['protocol']} from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
                                                                                break;
                                                                        default:
                                                                                break;
                                                                }
                                                                $loc_pt++;
                                                                if($starting_localhost_port > 19990) {
                                                                        log_error("Not installing nat reflection rules. Maximum 1,000 reached.");
                                                                        $x = $range_end+1;
                                                                }
                                                        }
                                                }
                                        }
                                }

                        }
                }
        }
        
    if ($wancfg['ipaddr'] == "dhcp" or $wancfg['ipaddr'] == "carodev-dhcp")
		$ipfrules .= "anchor \"wandhcp\"";
	if($config['interfaces']['lan']) 
		$ipfrules .= <<<EOD

# allow access to DHCP server on LAN
anchor "dhcpserverlan"
pass in on \$lan proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server on LAN"
pass in on \$lan proto udp from any port = 68 to $lanip port = 67 label "allow access to DHCP server on LAN"
pass out on \$lan proto udp from $lanip port = 67 to any port = 68 label "allow access to DHCP server on LAN"

EOD;

	$ipfrules .= <<<EOD

# allow WAN to use DHCP leases
pass in on \$wan proto udp from any port = 67 to any port = 68 label "allow dhcp client out wan"

EOD;
        
	if ($wancfg['ipaddr'] == "dhcp" or $wancfg['ipaddr'] == "carodev-dhcp") {

		$ipfrules .= <<<EOD
# allow our DHCP client out to the WAN
pass out on \$wan proto udp from any port = 68 to any port = 67 label "allow dhcp client out wan"

EOD;
	}

	/* allow access to DHCP server on optional interfaces */
	foreach ($optcfg as $on => $oc) {
		if ($config[interfaces][$on][ipaddr] == "dhcp" or $config[interfaces][$on][ipaddr] == "carpdev-dhcp") {
			$friendly_on = filter_get_opt_interface_descr($on);
			$ipfrules .= <<<EOD

# Not installing DHCP server firewall rules for $friendly_on which is configured for DHCP.

EOD;
		} elseif (isset($config['dhcpd'][$on]['enable']) && (!$oc['bridge'])  ||
				($oc['bridge'] && isset($config['dhcpd'][$oc['bridge']]['enable']))) {

				$friendly_on = filter_get_opt_interface_descr($on);

			$ipfrules .= <<<EOD

# allow access to DHCP server on {$on}
anchor "dhcpserver{$friendly_on}"
pass in on \${$friendly_on} proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server"
pass in on \${$friendly_on} proto udp from any port = 68 to {$oc['ip']} port = 67 label "allow access to DHCP server"
pass out on \${$friendly_on} proto udp from {$oc['ip']} port = 67 to any port = 68 label "allow access to DHCP server"

EOD;
		}
	}

	/* pass traffic between statically routed subnets and the subnet on the
	   interface in question to avoid problems with complicated routing
	   topologies */
	$sa = "";
	if (isset($config['filter']['bypassstaticroutes']) && is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			unset($sa);
			$friendly_int = convert_friendly_interface_to_friendly_descr($route['interface']);
			if ($route['interface'] == "lan") {
				$sa = $lansa;
				$sn = $lansn;
				$if = $lanif;
				$friendly_int = "lan";
			} else if (strstr($route['interface'], "opt")) {
				$oc = $optcfg[$route['interface']];
				if ($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
			}

			if ($sa) {
				$ipfrules .= <<<EOD
anchor "staticrouted"
pass in on \${$friendly_int} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass in on \${$friendly_int} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"
pass out on \${$friendly_int} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass out on \${$friendly_int} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"

EOD;
			}
		}
	}


		$ipfrules .= <<<EOD
# package manager late specific hook
anchor "packagelate"

EOD;
	
	/* XXX: ermal -- i am leaving this here for a second pass to optimize this rules. */
	$ipfrules .= "\n# SSH lockout\n";
	$ipfrules .= "block in log quick proto tcp from <sshlockout> to any port 22 label \"sshlockout\"\n\n";

	$ipfrules .= "anchor \"ftp-proxy/*\"\n";

	$ipfrules .= process_carp_rules();

	$ipfrules .= "\n\n# ftp-proxy specific\n";
	foreach($used_pftpx_ports as $pftpx) 
		$ipfrules .= "pass in on {$pftpx['interface']} inet proto tcp from any to 127.0.0.1 port {$pftpx['port']} keep state label \"FTP PROXY: Allow traffic to localhost\"\n";

	$ipfrules .= "\n";

	if (isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		$rule_arr = array();
		/* 
		 * XXX: This is a double pass but it needs to be this way.
		 * to allow users to override floating rules.
		 */
		foreach ($config['filter']['rule'] as $rule) {
 	                update_filter_reload_status("Pre-caching information for {$rule['descr']} ...");
        	        $line = "";
                	if (!isset ($rule['disabled']) && isset($rule['floating'])) {
                        	$rule_arr[] = generate_user_filter_rule_arr($rule, 0);
                	}

		}
		foreach ($config['filter']['rule'] as $rule) {
			update_filter_reload_status("Pre-caching {$rule['descr']}...");
			$line = "";
			if (!isset($rule['disabled'])&& !isset($rule['floating'])) {
				if ($rule['interface'] == "pptp") {
					/* we have a pptp rule but its turned off, ignore */
					if(!$config['pptpd']['mode'] == "server")
						continue;
					$n_pptp_units = $g['n_pptp_units'];
					if($config['pptp']['n_pptp_units'] <> "")
					$nif = $config['pptp']['n_pptp_units'];
					/*
					*   now that PPTP server are user rules, detect
					*   that user is setting the pptp server rule
					*   and setup for all netgraph interfaces
					*/
					$rule_arr[] = generate_user_filter_rule_arr($rule, 0);
				} else if($rule['interface'] == "pppoe") {
					if(!$config['pppoe']['mode'] == "server")
						continue;
					$n_pppoe_units = $g['n_pppoe_units'];
					if($config['pppoe']['n_pppoe_units'] <> "")
						$nif = $config['pppoe']['n_pppoe_units'];
					/*
					*   now that pppoe server are user rules, detect
					*   that user is setting the pppoe server rule
					*   and setup for all netgraph interfaces
					*/
					$rule_arr[] = generate_user_filter_rule_arr($rule, 0);
				} else {
					$rule_arr[] = generate_user_filter_rule_arr($rule, 0);
				}
			}
		}

		$ipfrules .= "\n# User-defined aliases follow\n";
		/* tables for aliases */
		foreach($table_cache as $table) {
			$ipfrules .= $table;
		}

		$ipfrules .= "\n# User-defined rules follow\n";
		/* Generate user rule lines */
		foreach($rule_arr as $rule) {
			$line = "";
			if (!isset($rule['disabled'])) {
				$line = $rule['rule'];
				if($line <> "") {
					/* label */
					$line .= " {$rule['descr']}";
				}
			}
			$line .= "\n";
			$ipfrules .= $line;
		}
	}

	update_filter_reload_status("Creating carp rules...");

	$ipfrules .= "\n# VPN Rules\n";
	/*     is mobile ipsec enabled?  if so lets allow some pretty
	 *     loose rules to allow mobile clients to phone in.
	 */
	$ipseccfg = $config['ipsec'];
	if (isset($ipseccfg['mobileclients']['enable'])) {
                $ifdescrs = get_configured_interface_with_descr();
		foreach($ifdescrs as $ifr => $iface) {
			/* only process interfaces with gateway */
			if(! interface_has_gateway($ifr))
				continue;

			/* FIXME: make dynamic interface compatible */
			$gateway = lookup_gateway_ip_by_name($config['interfaces'][$ifr]['gateway']);
			$interface = convert_friendly_interface_to_real_interface_name($iface);
			if((is_ipaddr($gateway)) && ($interface <> "")) {
				$route_to = " route-to ( $interface $gateway ) ";
				$reply_to = " reply-to ( $interface $gateway ) ";
			}
			$ifalias = convert_friendly_interface_to_friendly_descr($ifr);

			/* pass in rules for IPSEC with reply-to */
			$ipfrules .= "pass in on \${$ifalias} $reply_to proto udp from any to any port = 500 keep state label \"IPsec: Mobile - inbound isakmp\"\n";
			$ipfrules .= "pass in on \${$ifalias} $reply_to proto esp from any to any keep state label \"IPsec: Mobile - inbound esp proto\"\n";
			$ipfrules .= "pass in on \${$ifalias} $reply_to proto ah from any to any  keep state label \"IPsec: Mobile - inbound ah proto\"\n";
			/* Pass out rules for IPSEC with route-to */
			$ipfrules .= "pass out on \${$ifalias} $route_to proto udp from any to any port = 500 keep state label \"IPsec: Mobile - inbound isakmp\"\n";
			$ipfrules .= "pass out on \${$ifalias} $route_to proto esp from any to any keep state label \"IPsec: Mobile - inbound esp proto\"\n";
			$ipfrules .= "pass out on \${$ifalias} $route_to proto ah from any to any  keep state label \"IPsec: Mobile - inbound ah proto\"\n";
		}
	}

	if($config['interfaces']['lan']) {
		$lan_ip = $config['interfaces']['lan']['ipaddr'];
		$lan_subnet = $config['interfaces']['lan']['subnet'];
	}
	$wanif = get_real_wan_interface();
	$wan_ip = find_interface_ip($wanif);
	if($wan_ip) {
		if($config['interfaces']['lan']) 
			$internal_subnet = gen_subnet($lan_ip, $lan_subnet) . "/" . $config['interfaces']['lan']['subnet'];
		/* Is IP Compression enabled? */
		if(isset($config['ipsec']['ipcomp']))
			exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=1");
		else
			exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=0");

		/* if list */
                $ifdescrs = get_configured_interface_with_descr();

		if(is_array($config['ipsec']['tunnel']) && isset($config['ipsec']['enable']) && (! isset($ipseccfg['mobileclients']['enable']))) {
			foreach ($config['ipsec']['tunnel'] as $tunnel) {
				if(isset($tunnel['disabled']))
					continue;
				update_filter_reload_status("Creating IPsec tunnel items {$tunnel['descr']}...");
				/* if tunnel is disabled, lets skip to next item */
				$ipsec_ips = array(get_current_wan_address($tunnel['interface']));
				/* is this a dynamic dns hostname? */
				$remote_gateway = gethostbyname($tunnel['remote-gateway']);
				if($remote_gateway == "")
					$remote_gateway = $tunnel['remote-gateway'];
				/* do not add items with blank remote_gateway */
				if(!$remote_gateway) {
					$ipfrules .= "# ERROR!  Remote gateway not found on {$tunnel['remote-gateway']}\n";
					continue;
				}
				$local_subnet = return_vpn_subnet($tunnel['local-subnet']);
				foreach($ifdescrs as $ifr => $iface) {
					/* XXX: really needed?! */
					if ($ifr == "lan")
						continue;

					foreach($ipsec_ips as $interface_ip) {
						if($ifr == "wan")
							$interface_ip = find_interface_ip(get_real_wan_interface());
						else
							$interface_ip = find_interface_ip(convert_friendly_interface_to_real_interface_name($iface));
						if(!$interface_ip) 
							continue;
						if(!$remote_gateway) 
							continue;
						/* only process interfaces with gateway */
						if(! interface_has_gateway($ifr))
							continue;

						/* FIXME: make dynamic interface compatible */
						$gateway = lookup_gateway_ip_by_name($config['interfaces'][$ifr]['gateway']);
						$interface = convert_friendly_interface_to_real_interface_name($iface);
						if((is_ipaddr($gateway)) && ($interface <> "")) {
							$route_to = " route-to ( $interface $gateway ) ";
							$reply_to = " reply-to ( $interface $gateway ) ";
						}
						$ifalias = convert_friendly_interface_to_friendly_descr($ifr);			
						$ipfrules .= "pass out on \${$ifalias} $route_to proto udp from any to {$remote_gateway} port = 500 keep state label \"IPsec: {$tunnel['descr']} - outbound isakmp\"\n";
						$ipfrules .= "pass in on \${$ifalias} $reply_to proto udp from {$remote_gateway} to any port = 500 keep state label \"IPsec: {$tunnel['descr']} - inbound isakmp\"\n";
						if ($tunnel['p2']['protocol'] == 'esp') {
							$ipfrules .= "pass out on \${$ifalias} $route_to  proto esp from any to {$remote_gateway} keep state label \"IPsec: {$tunnel['descr']} - outbound esp proto\"\n";
							$ipfrules .= "pass in on \${$ifalias} $reply_to proto esp from {$remote_gateway} to any keep state label \"IPsec: {$tunnel['descr']} - inbound esp proto\"\n";
						}
						if ($tunnel['p2']['protocol'] == 'ah') {
							$ipfrules .= "pass out on \${$ifalias} $route_to proto ah from any to {$remote_gateway} keep state label \"IPsec: {$tunnel['descr']} - outbound ah proto\"\n";
							$ipfrules .= "pass in on \${$ifalias} $reply_to proto ah from {$remote_gateway} to any keep state label \"IPsec: {$tunnel['descr']} - inbound ah proto\"\n";
						}
					}
				}
			}
		}

		/*     is mobile ipsec enabled?  if so lets allow some pretty
		 *     loose rules to allow mobile clients to phone in.
		 */
		$ipseccfg = $config['ipsec'];
		if (isset($ipseccfg['mobileclients']['enable'])) {
			foreach($ifdescrs as $ifr => $iface) {
				$ifalias = convert_friendly_interface_to_friendly_descr($ifr);

				$ipfrules .= "pass in on \${$ifalias} proto udp from any to any port = 500 keep state label \"IPsec: Mobile - inbound isakmp\"\n";
				$ipfrules .= "pass in on \${$ifalias} proto esp from any to any keep state label \"IPsec: Mobile - inbound esp proto\"\n";
				$ipfrules .= "pass in on \${$ifalias} proto ah from any to any  keep state label \"IPsec: Mobile - inbound ah proto\"\n";
			}
		}
	}
	$ipfrules .= <<<EOD
# Support for allow limiting of TCP connections by establishment rate
anchor "limitingesr"
pass in on $wanif inet proto tcp from port 20 to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: PASV mode data connection"

EOD;

	if($config['interfaces']['lan']) 
		$ipfrules .= <<<EOD

		pass in on $lanif inet proto tcp from any to \$loopback port 8021 keep state label "FTP PROXY: Allow traffic to localhost"
		pass in on $lanif inet proto tcp from any to \$loopback port 21 keep state label "FTP PROXY: Allow traffic to localhost"				
EOD;

	if(!isset($config['system']['disableftpproxy'])) {

		$ipfrules .= "# enable ftp-proxy\n";

		$optcfg = array();
		generate_optcfg_array($optcfg);
		$ftp_counter = "8022";
		foreach($optcfg as $oc) {
			if(!isset($oc['gateway']) && $oc['if'] <> "") {
				$ipfrules .= "pass in on " . $oc['if'] . " inet proto tcp from any to \$loopback port {$ftp_counter} keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
				$ipfrules .= "pass in on " . $oc['if'] . " inet proto tcp from any to \$loopback port 21 keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
			}
			$ftp_counter++;
		}

		if(isset($config['system']['rfc959workaround'])) {
			$ipfrules .= <<<EODEOD

# Fix sites that violate RFC 959 which specifies that the data connection
# be sourced from the command port - 1 (typically port 20)
# This workaround doesn't expose us to any extra risk as we'll still only allow
# connections to the firewall on a port that ftp-proxy is listening on
pass in on $wanif inet proto tcp from any to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: RFC959 violation workaround"

EODEOD;

			$optcfg = array();
			generate_optcfg_array($optcfg);
			foreach($optcfg as $oc) {
				if($oc['gateway'] <> "")
					$ipfrules .= "pass in on {$oc['if']} inet proto tcp from any to ({$oc['if']}) port > 49000 user proxy flags S/SA keep state label \"FTP PROXY: RFC959 violation workaround\" \n";
			}
		}
	}

	$ipfrules .= <<<EOD

# IMSpector
anchor "imspector"

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

function setup_logging_interfaces() {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "setup_logging_interfaces() being called $mt\n";
	}
	$rules = "";
	$i = 0;

	/* if list */
	$ifdescrs = get_configured_interface_list();

	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* do not work with tun interfaces */
		if(stristr(filter_translate_type_to_real_interface($ifname), "tun") == true)
			continue;
		$int = filter_translate_type_to_real_interface($ifname);
		$rules .= "set loginterface {$int}\n";
	}
	return $rules;
}

function process_carp_nat_rules() {
	global $g, $config;

	update_filter_reload_status("Creating CARP NAT rules");

	$wan_interface = get_real_wan_interface();

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "process_carp_nat_rules() being called $mt\n";
	}
	$lines = "";
	if($config['installedpackages']['carp']['config'] != "")
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
	return $lines;
}

function process_carp_rules() {
	global $g, $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "process_carp_rules() being called $mt\n";
	}
	$lines = "";
	/* return if there are no carp configured items */
	if($config['installedpackages']['carpsettings']['config'] <> "" or
	   $config['virtualip']['vip'] <> "") {
		$lines .= "pass quick proto carp\n";
		$lines .= "pass quick proto pfsync";
	}
	return $lines;
}

function remove_special_characters($string) {
		$match_array = "";
        preg_match_all("/[a-zA-Z0-9\_\-]+/",$string,$match_array);
        $string = "";
        foreach($match_array[0] as $ma) {
                if($string <> "")
                        $string .= " ";
                $string .= $ma;
        }
        return $string;
}

function carp_sync_xml($url, $password, $sections, $port = 80, $method = 'pfsense.restore_config_section') {
	global $config, $g;

	if($g['booting'])
		return;

	update_filter_reload_status("Syncing CARP data to {$url}");

	/* make a copy of config */
	$config_copy = $config;

	/* strip out nosync items */
	for ($x = 0; $x < count($config_copy['nat']['advancedoutbound']['rule']); $x++) {
		if (isset ($config_copy['nat']['advancedoutbound']['rule'][$x]['nosync']))
			unset ($config_copy['nat']['advancedoutbound']['rule'][$x]);
		$config_copy['nat']['advancedoutbound']['rule'][$x]['descr'] = remove_special_characters($config_copy['nat']['advancedoutbound']['rule'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['nat']['rule']); $x++) {
		if (isset ($config_copy['nat']['rule'][$x]['nosync']))
			unset ($config_copy['nat']['rule'][$x]);
		$config_copy['nat']['rule'][$x]['descr'] = remove_special_characters($config_copy['nat']['rule'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['filter']['rule']); $x++) {
		if (isset ($config_copy['filter']['rule'][$x]['nosync']))
			unset ($config_copy['filter']['rule'][$x]);
		$config_copy['filter']['rule'][$x]['descr'] = remove_special_characters($config_copy['filter']['rule'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['aliases']['alias']); $x++) {
		if (isset ($config_copy['aliases']['alias'][$x]['nosync']))
			unset ($config_copy['aliases']['alias'][$x]);
		$config_copy['aliases']['alias'][$x]['descr'] = remove_special_characters($config_copy['aliases']['alias'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['dnsmasq']['hosts']); $x++) {
		if (isset ($config_copy['dnsmasq']['hosts'][$x]['nosync']))
			unset ($config_copy['dnsmasq']['hosts'][$x]);
		$config_copy['dnsmasq']['hosts'][$x]['descr'] = remove_special_characters($config_copy['dnsmasq']['hosts'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['virtualip']['vip']); $x++) {
		if (isset ($config_copy['virtualip']['vip'][$x]['nosync']) or $config_copy['virtualip']['vip'][$x]['mode'] == "proxyarp")
			unset ($config_copy['virtualip']['vip'][$x]);
		$config_copy['virtualip']['vip'][$x]['descr'] = remove_special_characters($config_copy['virtualip']['vip'][$x]['descr']);
	}
	for ($x = 0; $x < count($config_copy['ipsec']['tunnel']); $x++) {
		if (isset ($config_copy['ipsec']['tunnel'][$x]['nosync']))
			unset ($config_copy['ipsec']['tunnel'][$x]);
		$config_copy['ipsec']['tunnel'][$x]['descr'] = remove_special_characters($config_copy['ipsec']['tunnel'][$x]['descr']);
	}

	foreach($sections as $section) {
		/* we can't use array_intersect_key()
		   due to the vip 'special case' */
		if($section != 'virtualip') {
			$xml[$section] = $config_copy[$section];
		} else {
			$xml[$section] = backup_vip_config_section();
		}
	}

	$params = array(
			XML_RPC_encode($password),
			XML_RPC_encode($xml)
		);
	
	$numberofruns = 0;
	while($numberofruns < 2) {
		log_error("Beginning XMLRPC sync to {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$username = $config['system']['user'][0]['name'];
		$cli->setCredentials($username, $password);
		if($numberofruns == 1)
			$cli->setDebug(1);
		/* send our XMLRPC message and timeout after 240 seconds */
		$resp = $cli->send($msg, "240");
		if(!$resp) {
			$error = "A communications error occured while attempting XMLRPC sync with username {$username} {$url}:{$port}.";
			log_error($error);
			file_notice("sync_settings", $error, "Settings Sync", "");
		} elseif($resp->faultCode()) {
			$error = "An error code was received while attempting XMLRPC sync with username {$username} {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "Settings Sync", "");
		} else {
			log_error("XMLRPC sync successfully completed with {$url}:{$port}.");
			$numberofruns = 3;
		}
		$numberofruns++;
	}
}

function carp_sync_client() {

	global $config, $g;

	update_filter_reload_status("Building CARP sync information");

	if($g['booting'])
		return;

	if(is_array($config['installedpackages']['carpsettings']['config'])) {
	    foreach($config['installedpackages']['carpsettings']['config'] as $carp) {
		if($carp['synchronizetoip'] != "" ) {
		    /*
		     * XXX: The way we're finding the port right now is really suboptimal -
		     *	    we can't assume that the other machine is setup identically.
		     */
		    if($config['system']['webgui']['protocol'] != "") {
			$synchronizetoip = $config['system']['webgui']['protocol'];
			$synchronizetoip .= "://";
		    }
		    $port = $config['system']['webgui']['port'];
		    /* if port is empty lets rely on the protocol selection */
		    if($port == "") {
			if($config['system']['webgui']['protocol'] == "http") {
				$port = "80";
			} else {
				$port = "443";
			}
	   	    }
		    $synchronizetoip .= $carp['synchronizetoip'];
		    if($carp['synchronizerules'] != "" and is_array($config['filter'])) {
			$sections[] = 'filter';
		    }
		    if($carp['synchronizenat'] != "" and is_array($config['nat'])) {
			$sections[] = 'nat';
		    }
		    if($carp['synchronizealiases'] != "" and is_array($config['aliases'])) {
			$sections[] = 'aliases';
		    }
		    if($carp['synchronizedhcpd'] != "" and is_array($config['dhcpd'])) {
			$sections[] = 'dhcpd';
		    }
		    if($carp['synchronizewol'] != "" and is_array($config['wol'])) {
			$sections[] = 'wol';
		    }
		    if($carp['synchronizetrafficshaper'] != "" and is_array($config['shaper'])) {
			$sections[] = 'shaper';
		    }
		    if($carp['synchronizestaticroutes'] != "" and is_array($config['staticroutes'])) {
			$sections[] = 'staticroutes';
		    }
		    if($carp['synchronizevirtualip'] != "" and is_array($config['virtualip'])) {
			$sections[] = 'virtualip';
		    }
		    if($carp['synchronizelb'] != "" and is_array($config['load_balancer'])) {
			$sections[] = 'load_balancer';
		    }
		    if($carp['synchronizeipsec'] != "" and is_array($config['ipsec'])) {
			$sections[] = 'ipsec';
		    }
		    if($carp['synchronizednsforwarder'] != "" and is_array($config['dnsmasq'])) {
		        $sections[] = 'dnsmasq';
		    }
		    if($carp['synchronizeschedules'] != "" and is_array($config['schedules'])) {
		        $sections[] = 'schedules';
		    }
		    if(count($sections) > 0) {
				update_filter_reload_status("Signaling CARP reload signal...");
			    carp_sync_xml($synchronizetoip, $carp['password'], $sections, $port);
			    $cli = new XML_RPC_Client('/xmlrpc.php', $synchronizetoip, $port);
			    $msg = new XML_RPC_Message('pfsense.filter_configure', array(new XML_RPC_Value($carp['password'], 'string')));
				$username = $config['system']['user'][0]['name'];
			    $cli->setCredentials($username, $carp['password']);
			    $cli->send($msg, "900");
			    /* signal a carp reload */
			    $msg = new XML_RPC_Message('pfsense.interfaces_carp_configure');
			    $cli->send($msg, "900");
		    }
		}
	    }
	}

}

function return_vpn_subnet($adr) {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "return_vpn_subnet() being called $mt\n";
	}

        if ($adr['address']) {
                list($padr, $pmask) = explode("/", $adr['address']);
                if (is_null($pmask))
			return "{$padr}/32";
		return "{$padr}/{$pmask}";
        }

	/* XXX: do not return wan, lan, etc */
	if(strstr($adr['network'], "wan") or strstr($adr['network'], "lan") or strstr($adr['network'], "opt"))
		return convert_ip_to_network_format($config['interfaces'][$adr['network']]['ipaddr'],
		       $config['interfaces'][$adr['network']]['subnet']);

	/* fallback - error */
	return " # error - {$adr['network']} ";

}

?>
