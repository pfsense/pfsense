<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004,2005 Scott Ullrich
        Copyright (C) 2005 	Bill Marquette
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
require_once("shaper.inc");

/* holds the items that will be executed *AFTER* the filter is fully loaded */
$after_filter_configure_run = array();

function filter_pflog_start() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}	

	mute_kernel_msgs();

	mwexec_bg("/usr/sbin/tcpdump -l -n -e -ttt -v -i pflog0 | logger -t pf -p local0.info");
	
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
	update_filter_reload_status("Initializing");
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}	
	unlink_if_exists("{$g['tmp_path']}/filter_dirty");

	$lan_if = $config['interfaces']['lan']['if'];
	$wan_if = get_real_wan_interface();
	
	/* generate aliases */
	if($g['booting'] == true) echo " aliases ";
	update_filter_reload_status("Creating aliases");
	$aliases = filter_generate_aliases();
	/* generate nat rules */
	  if($g['booting'] == true) echo " rules ";
	update_filter_reload_status("Generating NAT rules");
	$natrules = filter_nat_rules_generate();
	/* generate pfctl rules */
	if($g['booting'] == true) echo " filtering ";
	update_filter_reload_status("Generating filter rules");
	$pfrules = filter_rules_generate();

	if(isset($config['shaper']['enable'])) {
		/* generate altq interface setup parms */
		if($g['booting'] == true) echo " altq-interfaces ";
		update_filter_reload_status("Generating ALTQ interfaces");
		$altq_ints = filter_setup_altq_interfaces();
		/* generate altq queues */
		if($g['booting'] == true) echo " altq-queues ";
		update_filter_reload_status("Generating ALTQ queues");
		$altq_queues = filter_generate_altq_queues($altq_ints);
		/* generate altq rules */
		if($g['booting'] == true) echo " altq-rules ";
		/* Setup a default rule that tags ALL packets as unshaped
		 * we'll match only unshaped packets in the shaper code later
		 * this allows the shaper to be first match
		 */
		$pf_altq_rules = "block in all tag unshaped label \"SHAPER: first match rule\"\n";
		update_filter_reload_status("Generating ALTQ rules");
		$pf_altq_rules .= filter_generate_pf_altq_rules();
	}
	
	update_filter_reload_status("Loading filter rules");
	
	/* enable pf if we need to, otherwise disable */
	if( !isset( $config['system']['disablefilter'] ) ) {
		mwexec("/sbin/pfctl -e");
	} else {
		mwexec("/sbin/pfctl -d");
		return;
	}

	/* get our wan interface? */
	$wanif = get_real_wan_interface();

	/* set up MSS clamping */
       if ($config['interfaces']['wan']['mtu'] <> "" and is_numeric($config['interfaces']['wan']['mtu']))
		$mssclamp = "max-mss " . (intval($config['interfaces']['wan']['mtu'] - 40));
	else if ($config['interfaces']['wan']['ipaddr'] == "pppoe")
		$mssclamp = "max-mss 1452";
	else
		$mssclamp = "";

	/* configure no-df for linux nfs and others */

	if ($config['system']['scrubnodf'])
		$scrubnodf = "no-df random-id";
	else
		$scrubnodf = "";

        $fd = fopen("{$g['tmp_path']}/rules.debug", "w");
	$rules = $aliases . " \n";

	update_filter_reload_status("Setting up logging information");

	$rules .= setup_logging_interfaces();

	if($config['system']['optimization'] <> "")
        	$rules.= "set optimization {$config['system']['optimization']}\n";
	else
        	$rules.= "set optimization normal\n";
		
	if($config['system']['maximumstates'] <> "" && is_numeric($config['system']['maximumstates'])) {
		/* User defined maximum states in Advanced menu. */
		$rules.= "set limit states {$config['system']['maximumstates']}\n";
	}
	$rules.= "\n";
	$rules.= "scrub on {$wanif} all {$scrubnodf} {$mssclamp}\n"; // reassemble all directions
    
	update_filter_reload_status("Setting up SCRUB information");
	
	/* loop through optional interfaces.  if a gateway is set, lets scrub em down! */
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		if($config['interfaces']["opt" . $j]['gateway'] <> "") {
			$if = convert_friendly_interface_to_real_interface_name("opt{$j}");
			if($if)
				$rules.= "scrub on {$if} all {$scrubnodf} {$mssclamp}\n"; // reassemble all directions
		}
	}

	$rules.= "{$altq_ints}\n";
	$rules.= "{$altq_queues}\n";
	$rules.= "{$natrules}\n";
        $rules.= "{$pf_altq_rules}\n";
        $rules.= "{$pfrules}\n";
	fwrite($fd, $rules);
        fclose($fd);

        $rules_loading = mwexec("/sbin/pfctl -f {$g['tmp_path']}/rules.debug");
	if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
		foreach ($config['shaper']['queue'] as $queue) {
        		mwexec("/sbin/pfctl -a {$queue['name']} -f {$g['tmp_path']}/{$queue['name']}.rules");
		}
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
		file_notice("filter_load", "There were error(s) loading the rules: {$rules_error} {$line_error}", "Filter Reload", "");
		log_error("There were error(s) loading the rules: {$rules_error} - {$line_error}");
		update_filter_reload_status("There were error(s) loading the rules: {$rules_error} - {$line_error}");
		return;
	}

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");
	
	update_filter_reload_status("Running plugins");
	
	/* process packager manager custom rules */
	$files = return_dir_as_array("/usr/local/pkg/pf/");
	if($files <> "") {
		foreach ($files as $file) {
			if($file <> "") {
				$text = file_get_contents("/usr/local/pkg/pf/" . $file);
				if($text) {
					if(stristr($file, ".sh") == true) {
						mwexec("/usr/local/pkg/pf/" . $file . " start");
					} else {
						if(stristr($file,"CVS") == false) {
							if($g['booting'] == true)
								echo "\t{$file}... ";
							eval($text);
							if($g['booting'] == true) 
								echo "done.\n";
						}
					}
				}
			}
		}
	}
	
	update_filter_reload_status("Syncing CARP data");
	
	/* sync carp entries to other firewalls */
	carp_sync_client();
	
	/*
	    we need a way to let a user run a shell cmd after each
	    filter_configure() call.  run this xml command after
            each change.
	*/
	if($config['system']['afterfilterchangeshellcmd'] <> "") {
		mwexec($config['system']['afterfilterchangeshellcmd']);
	}
	
	/* run items scheduled for after filter configure run */
	foreach($after_filter_configure_run as $afcr) {
		mwexec($afcr);
	}
	
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

	$lanip = find_interface_ip($config['interfaces']['lan']['if']);
	$wanip = find_interface_ip(get_real_wan_interface());

	$lan_aliases = " " . link_ip_to_carp_interface($lanip);
	$wan_aliases = " " . link_ip_to_carp_interface($wanip);

	$aliases .= "# System Aliases \n";
	$aliases .= "loopback = \"{ lo0 }\"\n";
	$aliases .= "lan = \"{ {$config['interfaces']['lan']['if']}{$lan_aliases} }\"\n";

    if($config['interfaces']['wan']['ipaddr'] == "pppoe") {
		$aliases .= "ng0 = \"{ " . $config['interfaces']['wan']['if'] . " " . get_real_wan_interface() . " }\" \n";
		$aliases .= "wan = \"{ " . get_real_wan_interface() . "{$wan_aliases} }\"\n";
	} else {
		$aliases .= "wan = \"{ " . get_real_wan_interface() . "{$wan_aliases} ng0 }\"\n";		
	}
	
    /* used to count netgraph interfaces */
    $counter = 0;
    
    /* ng ordering is VERY important here.  do not alter orer */
    if($config['pptpd']['mode'] == "server") {
		/* build pptp alias */
		$tmp = "pptp = \"{ ";
		for($x=0; $x<$g["n_pptp_units"]; $x++) 
			$tmp .= "ng{$x} ";
		$counter = $x;
		$tmp .= "}\" \n";
		if($counter > 0)
			$aliases .= $tmp;
	}
    if($config['pppoe']['mode'] == "server") {		
		/* build pppoe alias */
		$tmp = "pppoe = \"{ ";
		for($x=0; $x<$g["n_pppoe_units"]; $x++) {
			$tmp .= "ng{$counter} ";
			$counter++;
		}
		$tmp .= "}\" \n";
		if($x > 0)
			$aliases .= $tmp;
	}

	$ifdescrs = array();
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	$bridgetracker = 0;
	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* do not process tun interfaces */
		/* do process tun interfaces for openvpn compatibility */
		/* if(stristr(filter_opt_interface_to_real($ifname), "tun") == true) continue; */
		$aliases .= convert_friendly_interface_to_friendly_descr($ifname) . " = \"{ " . filter_opt_interface_to_real($ifname);
		if($config['interfaces'][$ifname]['bridge'] <> "") {
			$aliases .= " bridge{$bridgetracker} ";
			$bridgetracker++;
		}
		$aliases .= " }\"\n";
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

function get_vpns_list() {
	global $config;
	/* build list of vpns */
	$vpns = "";
	$isfirst = true;
	if($config['ipsec']['tunnel']) {
		foreach($config['ipsec']['tunnel'] as $tunnel) {
			if($isfirst == false)
				$vpns .= " ";
			$vpns .= $tunnel['remote-subnet'];
			$isfirst = false;
		}
	}
	return $vpns;
}

function generate_optcfg_array(& $optcfg) {
	global $config;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "generate_optcfg_array() being called $mt\n";
	}

        for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
                $oc = $config['interfaces']['opt' . $i];

                if (isset($oc['enable']) && $oc['if']) {
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
                                $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
                        }

                        $optcfg['opt' . $i] = $oic;
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
	global $config, $g, $after_filter_configure_run;

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];

	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];
	$wanif = get_real_wan_interface();

	$lanif = $config['interfaces']['lan']['if'];
	$lanip = $config['interfaces']['lan']['ipaddr'];

	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);

	$natrules = "";
	$natrules .= "nat-anchor \"pftpx/*\"\n";

	$natrules .= "nat-anchor \"natearly/*\"\n";
	$natrules .= "nat-anchor \"natrules/*\"\n";

	$natrules .= "# FTP proxy\n";
	$natrules .= "rdr-anchor \"pftpx/*\"\n";

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

			$natrules .= "binat on {$natif} from {$natent['internal']}/{$sn} to any -> {$natent['external']}/{$sn}\n";
		}
	}

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
		/* create ipsec passthru rule if requested */
		if (isset($config['nat']['ipsecpassthru']['enable'])) {
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$lansa}/{$lancfg['subnet']}", 500, "", 500, null, 500, false);
		}
		
		update_filter_reload_status("Creating outbound rules");
		
		$natrules .= filter_nat_rules_generate_if($wanif, "{$lansa}/{$lancfg['subnet']}");
		
		/* optional interfaces */
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) {
			update_filter_reload_status("Creating outbound rules (opt{$i})");
			$optcfg = $config['interfaces']['opt' . $i];

			if (isset($optcfg['enable']) && !$optcfg['bridge']) {
				$optsa = gen_subnet($optcfg['ipaddr'], $optcfg['subnet']);
				
				/*    setup nat mappings for lan -> opt[$i]
				 *    interface if a gateway is defined
                                 */
				if($optcfg['gateway'] <> "") 
					$natrules .= filter_nat_rules_generate_if($optcfg['if'],
						"{$lansa}/{$lancfg['subnet']}", null, "", null, null, null, isset($optcfg['nonat']));
					
				$natrules .= filter_nat_rules_generate_if($wanif,
					"{$optsa}/{$optcfg['subnet']}", null, "", null, null, null, isset($optcfg['nonat']));
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$pptp_subnet = $g['pptp_subnet'];
			if($config['pptp']['pptp_subnet'] <> "")
				$pptp_subnet = $config['pptp']['pptp_subnet'];
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pptpdcfg['remoteip']}/{$pptp_subnet}");
		}

		/* PPPoE subnet */
		if ($pppoecfg['mode'] == "server") {
			$pppoe_subnet = $g['pppoe_subnet'];
			if($config['pppoe']['pppoe_subnet'] <> "")
				$pppoe_subnet = $config['pppoe']['pppoe_subnet'];
			$natrules .= filter_nat_rules_generate_if($wanif,
				"{$pppoecfg['remoteip']}/{$pppoe_subnet}");
		}

		/* static routes */
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				if ($route['interface'] != "wan")
					$natrules .= filter_nat_rules_generate_if($wanif,
						$route['network'], "", null);
			}
		}

	}

	$natrules .= "#SSH Lockout Table\n";
	$natrules .= "table <sshlockout> persist\n\n";

	/* is SPAMD insalled? */
	if (is_package_installed("spamd") == 1) {
		$natrules .= "\n# spam table \n";
		
		$natrules .= "table <whitelist> persist\n";
		$natrules .= "table <blacklist> persist\n";
		$natrules .= "table <spamd> persist\n";
		$natrules .= "table <spamd-white> persist\n";
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
	$natrules .= "\n# Load balancing anchor - slbd updates\n";
	$natrules .= "rdr-anchor \"slb\"\n";

	update_filter_reload_status("Setting up FTP helper");

	$natrules .= "\n# FTP Proxy/helper\n";
	/* build an array of interfaces to work with */
	$iflist = array("lan" => "LAN");
	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) 
		$iflist['opt' . $i] = "opt{$i}";
	$interface_counter = 0;	
	/* loop through all interfaces and handle pftpx redirections */
	foreach ($iflist as $ifent => $ifname) {
		$ifname_lower = convert_friendly_interface_to_friendly_descr(strtolower($ifname));
		if(isset($config['interfaces'][strtolower($ifname)]['disableftpproxy'])) {
			log_error("FTP proxy disabled for interface {$ifname} - ignoring.");
			$interface_counter++;
			continue;
		}
		$tmp_port = 8021 + $interface_counter;
		$tmp_interface = convert_friendly_interface_to_real_interface_name($ifname);
		$vpns = get_vpns_list();
		/* if the user has defined, include the alias so that we do not redirect ftp
		   connections across the tunnels to pftpx */
		$vpns_list = get_vpns_list();
		if($vpns_list)
			$vpns = "{ ! $vpns_list }";
		else
			$vpns = "any";		
		$natrules .= "rdr on \${$ifname_lower} proto tcp from any to {$vpns} port 21 -> 127.0.0.1 port {$tmp_port}\n";
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
		
		if(!isset($config['system']['disablenatreflection'])) {
			$fd = fopen("/var/etc/inetd.conf","w");		
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
				continue;	/* unresolvable alias */

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
					$helpers = exec("/bin/ps awux | grep {$target} -b {$external_address} | grep -v grep");
					if(!$helpers) {
						$external_address = $rule['external-address'];
						if($external_address == "")
							$external_address = find_interface_ip(get_real_wan_interface());
						/*   install a pftpx helper, do not set a rule.  also use the delay filter configure run
						 *   routines because if this is the first bootup the filter is not completely configured 
                                                 *   and thus pf is not fully running.   otherwise we end up with: pftpx: pf is disabled
                                                 */
						$after_filter_configure_run[] = "/usr/local/sbin/pftpx -f {$target} -b {$external_address} -c 21 -g 21 2>>/tmp/pftpx_errors";
					}
					$dontinstallrdr = true;
				}
			}
			
			if($extaddr == "")
				$dontinstallrdr = true;
				
			$rdr_on = convert_real_interface_to_friendly_descr($rule['interface']);

			if($dontinstallrdr == false) {
				if ((!$extport[1]) || ($extport[0] == $extport[1])) {
					if($rule['protocol'] == "tcp/udp")
						$natrules .=
						 "rdr on \${$rdr_on} proto { tcp udp } from any to {$extaddr} port { {$extport[0]} } -> {$target}{$localport}";
					else
						if($extport[0]) 
							$natrules .=
							"rdr on \${$rdr_on} proto {$rule['protocol']} from any to {$extaddr} port { {$extport[0]} } -> {$target}{$localport}";
						else 
							$natrules .=
							"rdr on \${$rdr_on} proto {$rule['protocol']} from any to {$extaddr} } -> {$target}{$localport}";	
				} else {
					if($rule['protocol'] == "tcp/udp")
						$natrules .=
						 "rdr on \${$rdr_on} proto { tcp udp } from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target} port {$extport[0]}:*";
					else
						$natrules .=
						 "rdr on \${$rdr_on} proto {$rule['protocol']} from any to {$extaddr} port {$extport[0]}:{$extport[1]} -> {$target} port {$extport[0]}:*";
				}
			}

			/*    does this rule redirect back to a internal host?
			 *    if so, add some extra goo to help this work.
			 */
			$rule_friendly_if  = convert_friendly_interface_to_real_interface_name($rule['interface']);
			$rule_interface_ip = find_interface_ip($rule_friendly_if);
			$rule_interface_subnet = $config['interfaces'][$rule['interface']]['subnet'];
			$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
			if($rule['external-address'] == "any" and $rule['interface'] == "lan") {
				$natrules .= "\n";
				$natrules .= "no nat on {$rule_friendly_if} proto tcp from {$rule_friendly_if} to {$rule_subnet}/{$rule_interface_subnet}\n";
				$natrules .= "nat on {$rule_friendly_if} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$extport[0]} -> {$rule_friendly_if}\n";
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
					
					if($range_end - $extport[0] > 500) {
						$range_end = $extport[0]+1;
						log_error("Not installing nat reflection rules for a port range > 500");
					} else {					
						/* only install reflection rules for < 19991 items */
						if($starting_localhost_port < 19991) {
							for($x=$extport[0]; $x<$range_end; $x++) {
								
								update_filter_reload_status("Creating reflection rule for {$rule['descr']}...");
								
								$starting_localhost_port++;
								
								if($rule['protocol'] == "tcp/udp") {
									$protocol = "{ tcp udp }";
									fwrite($fd, "{$starting_localhost_port}\tstream\ttcp/udp\tnowait\tnobody\t/usr/bin/nc nc -w 20 {$target} {$x}\n");
								} else {
									$protocol = $rule['protocol'];
									fwrite($fd, "{$starting_localhost_port}\tstream\t{$protocol}\tnowait\tnobody\t/usr/bin/nc nc -w 20 {$target} {$x}\n");
								}
								
								$ifname_lower = convert_friendly_interface_to_friendly_descr(strtolower($ifname));
								
								$natrules .=
								  "rdr on \${$ifname_lower} proto {$protocol} from any to {$extaddr} port { {$x} } -> 127.0.0.1 port {$starting_localhost_port}\n";
								
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
			fclose($fd);				
			$helpers = trim(exec("/bin/ps ax | /usr/bin/grep inetd | /usr/bin/grep -v grep | /usr/bin/grep 127"));
			if(!$helpers)
				mwexec("/usr/sbin/inetd -a 127.0.0.1 /var/etc/inetd.conf");
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

		$natrules .= <<<EOD

# PPTP
rdr on \$wan proto gre from any to any -> $pptpdtarget
rdr on \$wan proto tcp from any to any port 1723 -> $pptpdtarget

EOD;
		}
	}

	if (is_package_installed("squid") == 1) {
		if(is_process_running("squid")) {
			$transparent_proxy = $config['installedpackages']['squid']['config'][0]['transparent_proxy'];
			if(isset($transparent_proxy) && ($transparent_proxy == "on")) {
				$natrules .= "rdr on {$lanif} inet proto tcp from any to !{$lanip} port www -> 127.0.0.1 port 3128\n";
			}
			unset($transparent_proxy);
		} else {
			if($g['booting'] == false) {
				log_error("SQUID is installed but failed to start.  Not installing redirector rule.");
			}
		}
	}

	$natrules .= process_carp_nat_rules();

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
	if($rule['descr'] != "" and $line != "")
		$ret['descr'] = "label \"USER_RULE: {$rule['descr']}\"";
	else
		$ret['descr'] = "label \"USER_RULE\"";
	$ret['ackq'] = get_ack_queue($rule['interface']);
		
	return $ret;	
}	

function generate_user_filter_rule($rule, $ngcounter) {
		global $config, $g;

		if(isset($config['system']['developerspew'])) {
			$mt = microtime();
			echo "generate_user_filter_rule() being called $mt\n";
		}

		update_filter_reload_status("Creating filter rules {$rule['descr']} ...");

		$wancfg = $config['interfaces']['wan'];
		$lancfg = $config['interfaces']['lan'];
		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];
		
		$lanif = $lancfg['if'];
		$wanif = get_real_wan_interface();
		
		$lanip = $lancfg['ipaddr'];
		$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
		$lansn = $lancfg['subnet'];
		
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
			if (strstr($rule['interface'], "opt")) {
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
				return "# {$rule['source']['network']} !array_key_exists source network " . $rule['descr'];
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $optcfg)) {
				$item = "";
				foreach($optcfg as $oc) $item .= $oc;
				return "# {$item} {$rule['destination']['network']} !array_key_exists dest network " . $rule['descr'];
			}
		}
		
		/* check for unresolvable aliases */
		if ($rule['source']['address'] && !alias_expand($rule['source']['address'])) {
			return "# unresolvable source aliases {$rule['descr']}";
		}
		if ($rule['destination']['address'] && !alias_expand($rule['destination']['address'])) {
			return "# unresolvable dest aliases {$rule['descr']}";
		}

		$ifdescrs = array();
		for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++) 
			$ifdescrs[] = "opt" . $i;
		
		update_filter_reload_status("Setting up pass/block rules");
		
		for ($iif = 0; $iif < $nif; $iif++) {
			$type = $rule['type'];
			if ($type != "pass" && $type != "block" && $type != "reject") {
				/* default (for older rules) is pass */
				$type = "pass";
			}
			
			if ($type == "reject") {
				/* special reject packet */
				if ($rule['protocol'] == "tcp") {
					$line = "block return-rst";
				} else if ($rule['protocol'] == "udp") {
					$line = "block return-icmp";
				} else if ($rule['protocol'] == "tcp/udp") {
                                        $line = "block return";
				} else {
					$line = "block";
				}
			} else {
				$line = $type;
			}
			
			/* ensure the direction is in */
			$line .= " in ";
			
			if (isset($rule['log']))
				$line .= "log ";
			
			$line .= "quick ";
			
			if ($ispptp) {
				$line .= "on ng" . (intval($ngcounter)+1) . " ";
			} else if ($ispppoe) {
				$line .= "on \$pppoe ";
			} else {
				// translate wan, man, lan, opt to real interface.
				$interface = $rule['interface'];
				$temp = filter_get_opt_interface_descr($interface);
				if($temp <> "") $interface = $temp;
				if(isset($rule['destination']['address'])) {
					$canadd = 0; // XXX: billm - eh?  this is a nice little noop
					/* because pf will not allow a interface for proxyARP
					   type traffic lets check if its in use and if so leave
                                           off the interface */
					if(is_one_to_one_or_server_nat_rule($rule['destination']['address']))
						$canadd = 0;
				}
				if($canadd == 0)
					$line .= "on \$" . convert_real_interface_to_friendly_descr($rule['interface']) . " ";
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
				$rg = $config['interfaces'][$rule['interface']]['gateway'];
			}

			/* do not process reply-to for gateway'd rules */
			if(($rule['gateway'] == "") and ($ri != "") and ($rg != "") and (stristr($rule['interface'],"opt") == true)) {
				$line .= "reply-to (" . $ri . " " . $rg . ") ";
			}

			/* if user has selected a custom gateway, lets work with it */
			if($rule['gateway'] <> "") {
				$foundlb = 0;
				if(is_array($config['load_balancer']['lbpool'])) {
					foreach($config['load_balancer']['lbpool'] as $lb) {
						update_filter_reload_status("Creating load balancing item...");
						if($lb['name'] == $rule['gateway']) {
							$gateway = $rule['gateway'];
							/*
							*    is $gateway a interface name?
							*    if so, lets find out the gateway address
							*    from /tmp/router_bleh.router
							*/
							if(in_array($gateway, $ifdescrs)==true) {
								if(is_file("{$g['tmp_path']}/{$gateway}_router")) {
									$return_gateway = file_get_contents("{$g['tmp_path']}/{$gateway}_router");
								} else {
									log_error("Could not find {$g['tmp_path']}/{$gateway}_router.  Needed for dhcp gateway information");
									continue;
								}
							}
							$line .= " route-to { ";
							/* if /tmp/$lbname.pool exists then read in our gateway hints from slbd */
							if(file_exists("{$g['tmp_path']}/{$lb['name']}.pool")) {
								$lbs = split("\n", file_get_contents("{$g['tmp_path']}/{$lb['name']}.pool"));
							} else {
								$lbs = $lb['servers'];
							}
							/* iterate through $lbs and setup items accordingly */
							foreach($lbs as $server) {
								if($foundlb == 1)
									$line .= ", ";
								/*   we are seperating the monitor address from the gateway
								 *   using the | character
								 */  
								if(stristr($server, "|") == true) {
									$split_ip = split("\|", $server);
									$rule['gateway'] = $split_ip[0];
									$gateway = $split_ip[0];
								}
								/* determine interface from gateway address */
								$int = guess_interface_from_ip($gateway);
								$line .=  "( {$int} {$gateway} ) ";
								if($int == "") {
									$line = "# error resolving load balancing {$gateway}";
								}
								$foundlb = 1;
							}
							$line .= "} round-robin ";
						}
					}
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
							$gatewayip = file_get_contents("{$g['tmp_path']}/{$int}_router");
							$gatewayip = rtrim($gatewayip);
							$line .= " route-to ( {$int} {$gatewayip} ) ";
						} else {
							log_error("Could not find {$g['tmp_path']}/{$int}_router.  Needed for dhcp gateway information");
							continue;
						}
					} else {
						/* user picked a real gateway ip */
						$gatewayip = $rule['gateway'];
						$int = guess_interface_from_ip($gatewayip);
						$line .= " route-to ( {$int} {$gatewayip} ) ";
					}
				}
			}
			
			if (isset($rule['protocol'])) {
				if($rule['protocol'] == "tcp/udp")
					$line .= "proto { tcp udp } ";
				elseif($rule['protocol'] == "icmp")
					$line .= "inet proto icmp ";
				else
					$line .= "proto {$rule['protocol']} ";
			} else {
				if($rule['source']['port'] <> "" || $rule['destination']['port'] <> "") {
					$line .= "proto tcp ";
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
				} else {
					switch ($rule['source']['network']) {
						case 'wanip':
							$src = $curwanip;
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
						$src .= " }";
					}
					else {
						$src = "{ {$not} " . alias_expand_value($rule['source']['address']) . " } ";
					}
				}
				else
					$src = "{ {$not} {$expsrc} }";
			}
			
			if (!$src || ($src == "/")) {
				return "# at the break!";
			}
			
			$line .= "from $src ";
			
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
			
				if ($rule['source']['port']) {
					$srcport = explode("-", $rule['source']['port']);
					if(alias_expand($srcport[0]))
						$srcporta = alias_expand($srcport[0]);
					else
						$srcporta = $srcport[0];
					if ((!$srcport[1]) || ($srcport[0] == $srcport[1])) {
						if(alias_expand($srcport[0]))
							$line .= "port { {$srcporta} } ";
						else
							$line .= "port = {$srcporta} ";
					} else if (($srcport[0] == 1) && ($srcport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($srcport[1] == 65535) {
						$line .= "port >= {$srcport[0]} ";
					} else if ($srcport[0] == 1) {
						$line .= "port <= {$srcport[1]} ";
					} else {
						$srcport[0]--;
						$srcport[1]++;
						$line .= "port {$srcport[0]} >< {$srcport[1]} ";
					}
				}
                                /* OS signatures */
                                if (($rule['protocol'] == "tcp") && ($rule['os'] <> ""))
                                        $line .= "os {$rule['os']} ";

			}

			/* destination address */
			if (isset($rule['destination']['any'])) {
				$dst = "any";
			} else if ($rule['destination']['network']) {

				if (strstr($rule['destination']['network'], "opt")) {
					$dst = $optcfg[$rule['destination']['network']]['sa'] . "/" .
						$optcfg[$rule['destination']['network']]['sn'];
					if (isset($rule['destination']['not'])) $dst = " !{$dst}";
				} else {
					switch ($rule['destination']['network']) {
						case 'wanip':
							$dst = $curwanip;
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
						$dst .= " }";
					}
					else {
						$dst = "{ {$not} " . alias_expand_value($rule['destination']['address']) . " } ";
					}
				}
				else
					$dst = "{ {$not} {$expdst} }";
			}
			
			if (!$dst || ($dst == "/")) {
				return "# returning at $dst == \"/\"";
			}
			
			$line .= "to $dst ";
			
			if (in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
			
				if ($rule['destination']['port']) {
					$dstport = explode("-", $rule['destination']['port']);
					if(alias_expand($dstport[0]))
						$dstporta = alias_expand($dstport[0]);
					else
						$dstporta = $dstport[0];
					if ((!$dstport[1]) || ($dstport[0] == $dstport[1])) {
						if(alias_expand($dstport[0]))
							$line .= "port {$dstporta} ";
						else
							$line .= "port = {$dstporta} ";
					} else if (($dstport[0] == 1) && ($dstport[1] == 65535)) {
						/* no need for a port statement here */
					} else if ($dstport[1] == 65535) {
						$line .= "port >= {$dstport[0]} ";
					} else if ($dstport[0] == 1) {
						$line .= "port <= {$dstport[1]} ";
					}  else {
						$dstport[0]--;
						$dstport[1]++;
						$line .= "port {$dstport[0]} >< {$dstport[1]} ";
					}
				}
			}

			if (($rule['protocol'] == "icmp") && $rule['icmptype']) {
				$line .= "icmp-type {$rule['icmptype']} ";
			}

			if ($type == "pass") {

				if( isset($rule['source-track']) or isset($rule['max-src-nodes']) or isset($rule['max-src-states']) )
					if($rule['protocol'] == "tcp")
						$line .= "flags S/SA ";
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
				if(isset($rule['statetype']) && $rule['statetype'] <> "") {
					switch($rule['statetype']) {
						case "none":
							break;
						case "modulate state":
						case "synproxy state":
							if($rule['protocol'] == "tcp")
								$line .= "{$rule['statetype']} ";
							break;
						default:
							$line .= "{$rule['statetype']} ";
					}
				} else {
					$line .= "keep state ";
				}
				if( isset($rule['source-track']) and $rule['source-track'] <> "" or 
				  isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "" or 
				   isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> "" or 
				    isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "" or 
				     isset($rule['max-src-states']) and $rule['max-src-states'] <> "" ) {
					$line .= "( ";
					if(isset($rule['source-track']) and $rule['source-track'] <> "")
						$line .= "source-track rule ";
					if(isset($rule['max-src-nodes']) and $rule['max-src-nodes'] <> "")
						$line .= "max-src-nodes " . $rule['max-src-nodes'] . " ";
					if(isset($rule['max-src-states']) and $rule['max-src-states'] <> "")
						$line .= "max-src-states " . $rule['max-src-states'] . " ";
					if(isset($rule['statetimeout']) and $rule['statetimeout'] <> "")
						$line .= "tcp.established " . $rule['statetimeout'] . " ";
					if(isset($rule['max-src-conn-rate']) and $rule['max-src-conn-rate'] <> ""
					   and isset($rule['max-src-conn-rates']) and $rule['max-src-conn-rates'] <> "") {
						$line .= "max-src-conn-rate " . $rule['max-src-conn-rate'] . " ";						
						$line .= "/" . $rule['max-src-conn-rates'] . ", overload <virusprot> flush global ";
					   }
					$line .= " ) ";
				}
			}
			if ($type == "reject" && $rule['protocol'] == "tcp") {
				/* special reject packet */
				$line .= "flags S/SA ";
			}
	}
	return $line;
}

function filter_rules_generate() {
	global $config, $g;

	update_filter_reload_status("Creating default rules");

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$wancfg = $config['interfaces']['wan'];
	$lancfg = $config['interfaces']['lan'];
	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];

	$lanif = $lancfg['if'];
	$wanif = get_real_wan_interface();

	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	if($lansa) 
		$lansa_sn_combo = "{$lansa}/{$lansn}";
	else
		$lansa_sn_combo = "192.168.1.1/32";

	/* optional interfaces */
	$optcfg = array();
	generate_optcfg_array($optcfg);

	/* ftp-sesame */
	$ipfrules .= "anchor \"ftpsesame/*\" \n";

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

	/* if squid is installed, lets install its rule */
	if (is_package_installed("squid") == 1) {
		$ipfrules .= "pass in quick on {$lanif} inet proto tcp from any to 127.0.0.1 port 3128 flags S/SA keep state label \"transparent proxy\"\n";
		$ipfrules .= "pass out quick on {$wanif} inet proto tcp from ($wanif) to any port www flags S/SA keep state label \"transparent proxy\"\n";
	}

	$ipfrules .= <<<EOD

# loopback
anchor "loopback"
pass in quick on \$loopback all label "pass loopback"
pass out quick on \$loopback all label "pass loopback"

# package manager early specific hook
anchor "packageearly"


# carp
anchor "carp"

EOD;

	if(!isset($config['system']['disableftpproxy'])) {
		
		$ipfrules .= "# enable ftp-proxy\n";
		
		$optcfg = array();
		generate_optcfg_array($optcfg);
		$ftp_counter = "8022";
		foreach($optcfg as $oc) {
			if(!isset($oc['gateway']) && $oc['if'] <> "") {
				$ipfrules .= "pass in quick on " . $oc['if'] . " inet proto tcp from any to \$loopback port {$ftp_counter} keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
				$ipfrules .= "pass in quick on " . $oc['if'] . " inet proto tcp from any to \$loopback port 21 keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
			}
			$ftp_counter++;
		}
		
	$ipfrules .= <<<EOD

anchor "ftpproxy"
anchor "pftpx/*"
pass in quick on $lanif inet proto tcp from any to \$loopback port 8021 keep state label "FTP PROXY: Allow traffic to localhost"
pass in quick on $lanif inet proto tcp from any to \$loopback port 21 keep state label "FTP PROXY: Allow traffic to localhost"
pass in quick on $wanif inet proto tcp from port 20 to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: PASV mode data connection"

EOD;


		if(isset($config['system']['rfc959workaround'])) {
		$ipfrules .= <<<EOD

# Fix sites that violate RFC 959 which specifies that the data connection
# be sourced from the command port - 1 (typically port 20)
# This workaround doesn't expose us to any extra risk as we'll still only allow
# connections to the firewall on a port that ftp-proxy is listening on
pass in quick on $wanif inet proto tcp from any to ($wanif) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: RFC959 violation workaround"

EOD;

			$optcfg = array();
			generate_optcfg_array($optcfg);	
			foreach($optcfg as $oc) {
				if($oc['gateway'] <> "")
					$ipfrules .= "pass in quick on {$oc['if']} inet proto tcp from any to ({$oc['if']}) port > 49000 user proxy flags S/SA keep state label \"FTP PROXY: RFC959 violation workaround\" \n";
			}
		}
	}

	$ipfrules .= <<<EOD

# allow access to DHCP server on LAN
anchor "dhcpserverlan"
pass in quick on $lanif proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server on LAN"
pass in quick on $lanif proto udp from any port = 68 to $lanip port = 67 label "allow access to DHCP server on LAN"
pass out quick on $lanif proto udp from $lanip port = 67 to any port = 68 label "allow access to DHCP server on LAN"

EOD;

	/* allow access to DHCP server on optional interfaces */
	foreach ($optcfg as $on => $oc) {
		if (isset($config['dhcpd'][$on]['enable']) && (!$oc['bridge'])) {
			$ipfrules .= <<<EOD

# allow access to DHCP server on {$on}
anchor "dhcpserver"
pass in quick on {$oc['if']} proto udp from any port = 68 to 255.255.255.255 port = 67 label "allow access to DHCP server"
pass in quick on {$oc['if']} proto udp from any port = 68 to {$oc['ip']} port = 67 label "allow access to DHCP server"
pass out quick on {$oc['if']} proto udp from {$oc['ip']} port = 67 to any port = 68 label "allow access to DHCP server"
pass out on {$oc['if']} proto icmp from any to any keep state label "allow access to DHCP server"

EOD;
		}
	}

	/* pass traffic between statically routed subnets and the subnet on the
	   interface in question to avoid problems with complicated routing
	   topologies */
	$sa = "";
	if (is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			unset($sa);

			if ($route['interface'] == "lan") {
				$sa = $lansa;
				$sn = $lansn;
				$if = $lanif;
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
pass in quick on {$if} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass in quick on {$if} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"
pass out quick on {$if} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass out quick on {$if} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"

EOD;
			}
		}
	}

	/* install wan spoof check rule if lan address exists */
	if($lansa) {
		if(!isset($config['interfaces']['wan']['spoofmac'])) {
			$ipfrules .= <<<EOD

# WAN spoof check
anchor "wanspoof"
block in $log quick on $wanif from $lansa/$lansn to any label "WAN spoof check"

EOD;

		}
	}

	foreach ($optcfg as $oc) {
		if (!$oc['bridge'])
			if($oc['sa'] <> "") 
				$ipfrules .= "block in $log quick on $wanif from {$oc['sa']}/{$oc['sn']} to any label \"interface spoof check\"\n";
	}

	/* allow PPTP traffic if PPTP client is enabled on WAN */
	if ($wancfg['ipaddr'] == "pptp") {
		$ipfrules .= <<<EOD

# allow PPTP client
anchor "pptpclient"
pass in quick on {$wancfg['if']} proto gre from any to any modulate state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto gre from any to any modulate state label "allow PPTP client"
pass in quick on {$wancfg['if']} proto tcp from any port = 1723 to any flags S/SA modulate state label "allow PPTP client"
pass out quick on {$wancfg['if']} proto tcp from any to any port = 1723 flags S/SA modulate state label "allow PPTP client"

EOD;
	}

	if ($wancfg['ipaddr'] == "dhcp") {

		$ipfrules .= <<<EOD

# allow our DHCP client out to the WAN
anchor "wandhcp"
pass out quick on $wanif proto udp from any port = 68 to any port = 67 label "allow dhcp client out wan"

EOD;
	}

if($config['interfaces']['lan']['bridge'] <> "wan" and $config['interfaces']['wan']['bridge'] <> "lan")
	$ipfrules .= "block in $log quick on $wanif proto udp from any port = 67 to {$lansa_sn_combo} port = 68 label \"allow dhcp client out wan\"\n";

	$ipfrules .= <<<EOD

pass in quick on $wanif proto udp from any port = 67 to any port = 68 label "allow dhcp client out wan"

# LAN/OPT spoof check (needs to be after DHCP because of broadcast addresses)

EOD;

	/* LAN spoof check */
	$ipfrules .= filter_rules_spoofcheck_generate('lan', $lanif, $lansa, $lansn, $log);

	/* OPT spoof check */
	foreach ($optcfg as $on => $oc) {
		if ($oc['ip'])
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);
	}

	/* block private networks on WAN? */
	if (isset($config['interfaces']['wan']['blockpriv'])) {
		$ipfrules .= <<<EOD


# block anything from private networks on WAN interface
anchor "spoofing"
block in $log quick on $wanif from 10.0.0.0/8 to any label "block private networks from wan block 10/8"
block in $log quick on $wanif from 127.0.0.0/8 to any label "block private networks from wan block 127/8"
block in $log quick on $wanif from 172.16.0.0/12 to any label "block private networks from wan block 172.16/12"
block in $log quick on $wanif from 192.168.0.0/16 to any label "block private networks from wan block 192.168/16"

EOD;

	}
	
	/*
	 * Support for allow limiting of TCP connections by establishment rate
         * Useful for protecting against sudden outburts, etc.
	 */
	$ipfrules .= <<<EODF
# Support for allow limiting of TCP connections by establishment rate
anchor "limitingesr"
table <virusprot>
	
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
block in $log quick on $wanif from <bogons> to any label "block bogon networks from wan"

EOD;
	}

	$ipfrules .= <<<EOD

# let out anything from the firewall host itself and decrypted IPsec traffic
# pass out quick on $wanif all keep state label "let out anything from firewall host itself"

EOD;

	$ipfrules .= create_firewall_outgoing_rules_to_itself();

	/* group heads for optional interfaces */
	foreach ($optcfg as $on => $oc) {
		$ipfrules .= <<<EOD


# let out anything from the firewall host itself and decrypted IPsec traffic
pass out quick on {$oc['if']} all keep state label "let out anything from firewall host itself"

EOD;

	}

	if (!isset($config['system']['webgui']['noantilockout'])) {

		if($lansa and $lansn) {

			$ipfrules .= <<<EOD

# make sure the user cannot lock himself out of the webGUI or SSH
anchor "anti-lockout"
pass in quick from {$lansa}/{$lansn} to $lanip keep state label "anti-lockout web rule"

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
pass in quick proto gre from any to $pptpdtarget modulate state label "allow gre pptpd"
pass in quick proto tcp from any to $pptpdtarget port = 1723 modulate state label "allow pptpd {$pptpdtarget}"

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

# package manager late specific hook
anchor "packagelate"



EOD;
	}

	$ipfrules .= "\n# SSH lockout\n";
	$ipfrules .= "block in log proto tcp from <sshlockout> to any port 22 label \"sshlockout\"\n\n";

	$ipfrules .= "\n# User-defined rules follow\n";

	if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue']) && isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		/* XXX: billm - twice really, this needs to be made useable for "normal rules too" */
		$rule_arr = array();
		$ipfrules .= "# Anchors for rules that might be matched by queues\n";
		foreach ($config['filter']['rule'] as $rule) {
			update_filter_reload_status("Pre-caching {$rule['descr']}...");
			$line = "";
			if (!isset($rule['disabled'])) {
				if ($rule['interface'] == "pptp") {
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

		/* This is ugly, but we generate one anchor per queue */
		foreach ($config['shaper']['queue'] as $queue) {
			update_filter_reload_status("Creating filter anchor for {$queue['name']} ...");
			/* Add anchor to rules */
			$ipfrules .= "anchor {$queue['name']} tagged {$queue['name']}\n";
			/* Create rules for anchors */
			$fd = fopen("{$g['tmp_path']}/{$queue['name']}.rules", "w");
			/* aliases don't recurse to anchors */
			$line = filter_generate_aliases();
			fwrite($fd, $line);
			foreach($rule_arr as $rule) {
				if($rule['ackq'] != "")
				$line = "{$rule['rule'] } queue ({$queue['name']}, {$rule['ackq']}) {$rule['descr']}\n";
				else
				$line = "{$rule['rule'] } queue {$queue['name']} {$rule['descr']}\n";
				fwrite($fd, $line);
			}
			fclose($fd);
		}
	}
	
	if (isset($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			/* Pre-cache all our rules so we only have to generate them once */
			update_filter_reload_status("Pre-caching information for {$rule['descr']} ...");
			$line = "";
			if (!isset($rule['disabled'])) {
				$line = generate_user_filter_rule($rule, 0);
				if($line <> "") 
					if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
						$defq = find_default_queue($rule['interface']);
						$ackq = get_ack_queue($rule['interface']);
						if (($defq != "") and ($ackq != ""))
							$line .= " queue ({$defq}, {$ackq}) ";
					}
				// label
				if($rule['descr'] <> "" and $line <> "")
					$line .= " label \"USER_RULE: {$rule['descr']}\" ";
				else
					$line .= " label \"USER_RULE\" ";
			}
			$line .= "\n";
			$ipfrules .= $line;
		}
	}
	
	$ipfrules .= process_carp_rules();
	
	update_filter_reload_status("Creating carp rules...");

	$ipfrules .= "\n# VPN Rules\n";
	$lan_ip = $config['interfaces']['lan']['ipaddr'];
	$lan_subnet = $config['interfaces']['lan']['subnet'];
	$wanif = get_real_wan_interface();
	$wan_ip = find_interface_ip($wanif);
	if($wan_ip) {
		$internal_subnet = gen_subnet($lan_ip, $lan_subnet) . "/" . $config['interfaces']['lan']['subnet'];
		/* Is IP Compression enabled? */
		if(isset($config['ipsec']['ipcomp']))
			exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=1");
		else
			exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=0");
		/* determine ipsec address */
		$ipsec_ip = $wan_ip;
		/* set failover ip if defined */
		if(isset($config['installedpackages']['sasyncd']['config']))
		foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
			if($sasyncd['ip'] <> "") 
				$ipsec_ip = $sasyncd['ip'];
		}
		
		if(is_array($config['ipsec']['tunnel']) && isset($config['ipsec']['enable'])) {
			foreach ($config['ipsec']['tunnel'] as $tunnel) {
				update_filter_reload_status("Creating IPSEC tunnel items {$tunnel['descr']}...");
				/* if tunnel is disabled, lets skip to next item */
				if(isset($tunnel['disabled']))
					continue;
				/* is this a dynamic dns hostname? */
				$remote_gateway = gethostbyname($tunnel['remote-gateway']);
				if($remote_gateway == "")
					$remote_gateway = $tunnel['remote-gateway'];
				/* do not add items with blank remote_gateway */
				if(!$remote_gateway) {
					$ipfrules .= "# ERROR!  Remote gateway not found on ... pass quick on {$wanif} proto udp from {$ipsec_ip} to {$remote_gateway} port = 500 keep state label \"IPSEC: {$tunnel['descr']} udp\"\n";
					continue;	
				}
				$local_subnet = return_vpn_subnet($tunnel['local-subnet']);
				$ipfrules .= "pass out quick on {$wanif} proto udp from {$ipsec_ip} to {$remote_gateway} port = 500 keep state label \"IPSEC: {$tunnel['descr']} - outbound isakmp\"\n";
				$ipfrules .= "pass in quick on {$wanif} proto udp from {$remote_gateway} to {$ipsec_ip} port = 500 keep state label \"IPSEC: {$tunnel['descr']} - inbound isakmp\"\n";
	
				$ipfrules .= "pass out quick on {$wanif} proto esp from {$ipsec_ip} to {$remote_gateway} keep state label \"IPSEC: {$tunnel['descr']} - outbound esp proto\"\n";
				$ipfrules .= "pass in quick on {$wanif} proto esp from {$remote_gateway} to {$ipsec_ip} keep state label \"IPSEC: {$tunnel['descr']} - inbound esp proto\"\n";
	
				$ipfrules .= "pass out quick on {$wanif} proto ah from {$ipsec_ip} to {$remote_gateway} keep state label \"IPSEC: {$tunnel['descr']} - outbound ah proto\"\n";
				$ipfrules .= "pass in quick on {$wanif} proto ah from {$remote_gateway} to {$ipsec_ip} keep state label \"IPSEC: {$tunnel['descr']} - inbound ah proto\"\n";
	
				//$ipfrules .= "pass out quick on {$lanif} from {$tunnel['remote-subnet']} to {$local_subnet} keep state label \"IPSEC: {$tunnel['descr']} - remote to local\"\n";
				//$ipfrules .= "pass in quick on {$lanif} from {$local_subnet} to {$tunnel['remote-subnet']} keep state label \"IPSEC:  {$tunnel['descr']} - local to remote\"\n";
			}
		}
	
		/*     is mobile ipsec enabled?  if so lets allow some pretty 
		 *     loose rules to allow mobile clients to phone in.
		 */
		$ipseccfg = $config['ipsec'];
		if (isset($ipseccfg['mobileclients']['enable'])) {
			$ipfrules .= "pass in quick on {$wanif} proto udp from any to any port = 500 keep state label \"IPSEC: Mobile - inbound isakmp\"\n";
			$ipfrules .= "pass in quick on {$wanif} proto esp from any to any keep state label \"IPSEC: Mobile - inbound esp proto\"\n";
			$ipfrules .= "pass in quick on {$wanif} proto ah from any to any  keep state label \"IPSEC: Mobile - inbound ah proto\"\n";
		}
	}
	$ipfrules .= <<<EOD

#---------------------------------------------------------------------------
# default rules (just to be sure)
#---------------------------------------------------------------------------
block in $log quick all label "Default block all just to be sure."
block out $log quick all label "Default block all just to be sure."

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
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++) {
		$ifdescrs['opt' . $j] = "opt" . $j;
	}
	foreach ($ifdescrs as $ifdescr => $ifname) {
		/* do not work with tun interfaces */
		if(stristr(filter_translate_type_to_real_interface($ifname), "tun") == true) continue;
		$int = filter_translate_type_to_real_interface($ifname);
		$rules .= "set loginterface {$int}\n";
	}
	return $rules;
}

function create_firewall_outgoing_rules_to_itself() {
	global $config, $g;
	
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "create_firewall_outgoing_rules_to_itself() being called $mt\n";
	}	
	
	$i = 0;
	$rule .= "# pass traffic from firewall -> out\n";
	$rule .= "anchor \"firewallout\"\n";
	$ifdescrs = array('wan', 'lan');
	for ($j = 1; isset($config['interfaces']['opt' . $j]); $j++)
		$ifdescrs['opt' . $j] = "opt" . $j;
	
	/* go through primary and optional interfaces */
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$return_gateway = $config['interfaces'][$ifname]['gateway'];
		$ints = array();
		$int = filter_translate_type_to_real_interface($ifname);
		/* if the interface is pppoe, set the ng0 interface */
		update_filter_reload_status("Creating IPSEC tunnel items {$tunnel['descr']}...");
		$ip = find_interface_ip($int);
		if ($config['interfaces'][$ifname]['ipaddr'] == "pppoe")
			$int = " { " . filter_translate_type_to_real_interface($ifname) . " ng0 } ";
		if (isset($config['shaper']['enable']) && is_array($config['shaper']['queue'])) {
			$ackq = get_ack_queue($ifname);
			$defq = find_default_queue($ifname);
			/* Handle all tagged packets */
			foreach ($config['shaper']['queue'] as $queue) {
				if ($ackq == "" || $defq == "") {
					/* Shaper must not be enabled on this interface */
					$q = "";
				} else {
					$q = "queue ({$queue['name']}, {$ackq})";
				}
				$rule .="pass out quick on {$int} all keep state tagged {$queue['name']} {$q} label \"let out anything from firewall host itself\"\n";
			}
			/* Handle untagged packets */
			if ($ackq == "" || $defq == "") {
				/* Shaper must not be enabled on this interface */
				$q = "";
			} else {
				$q = "queue ({$defq}, {$ackq})";
			}
			$rule .="pass out quick on {$int} all keep state {$q} label \"let out anything from firewall host itself\"\n";
		} else {
			/* first add a rule for the real interface, then for ng0 */
			$rule .="pass out quick on {$int} all keep state label \"let out anything from firewall host itself\"\n";
		}
	}
	
	update_filter_reload_status("Setting up bridging items");
	/* is bridging turned on? */
	for($x=0; $x<10; $x++) {
		if(does_interface_exist("bridge{$x}") == true) 
			$rule .="pass out quick on bridge{$x} all keep state label \"let out anything from firewall host itself\"\n";
	}
	
	update_filter_reload_status("Setting up pptp items");
	if($config['pptpd']['mode'] == "server")
		$rule .="pass out quick on \$pptp all keep state label \"let out anything from firewall host itself pptp\"\n";
	
	update_filter_reload_status("Setting up pppoe items");
	if($config['pppoe']['mode'] == "server")
		$rule .="pass out quick on \$pppoe all keep state label \"let out anything from firewall host itself pppoe\"\n";
	
	update_filter_reload_status("Setting up gif tunnels");
	/* setup outgoing gif tunnels */
	$number_of_gifs = find_last_gif_device();
	$number_of_gifs++;
	for($x=0; $x<$number_of_gifs; $x++) {
		if(does_interface_exist("gif{$x}") == true) 
			$rule .="pass out quick on gif{$x} all keep state label \"let out anything from firewall host itself ipsec gif\"\n";
	}

	update_filter_reload_status("Setting up tun interfaces (openvpn)");
	/* openvpn tun interfaces.  check for 100. */
	for($x=0; $x<100; $x++) {
		if(does_interface_exist("tun{$x}") == true) 
			$rule .="pass quick on tun{$x} all keep state label \"let out anything from firewall host itself openvpn\"\n";
	}
	
	return $rule;
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
		$lines .= "pass quick proto carp keep state\n";
		$lines .= "pass quick proto pfsync";
	}
	return $lines;
}

function carp_sync_xml($url, $password, $sections, $port = 80, $method = 'pfsense.restore_config_section') {
	global $config, $g;

	if($g['booting'])
		return;
	
	update_filter_reload_status("Syncing CARP data to {$url}");

	$config_copy = $config;

	/* strip out nosync items */
	for($x=0; $x<count($config_copy['nat']['advancedoutbound']['rule']); $x++) {
		if(isset($config_copy['nat']['advancedoutbound']['rule'][$x]['nosync']))
			unset($config_copy['nat']['advancedoutbound']['rule'][$x]['nosync']);
	}
	for($x=0; $x<count($config_copy['nat']['rule']); $x++) {
		if(isset($config_copy['nat']['rule'][$x]['nosync']))
			unset($config_copy['nat']['rule'][$x]['nosync']);
	}
	for($x=0; $x<count($config_copy['filter']['rule']); $x++) {
		if(isset($config_copy['filter']['rule'][$x]['nosync']))
			unset($config_copy['filter']['rule'][$x]['nosync']);
	}

	if($g['booting'])
		return;

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

	log_error("Beginning XMLRPC sync to {$url}:{$port}.");
	$msg = new XML_RPC_Message($method, $params);
	$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
	$cli->setCredentials('admin', $password);
	if($g['debug']) $cli->setDebug(1);
	/* send our XMLRPC message and timeout after 240 seconds */
	$resp = $cli->send($msg, "240");
	if(!$resp) {
		$error = "A communications error occured while attempting XMLRPC sync with {$url}:{$port}.";
		log_error($error);
		file_notice("sync_settings", $error, "Settings Sync", "");
	} elseif($resp->faultCode()) {
		$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
		log_error($error);
		file_notice("sync_settings", $error, "Settings Sync", "");	
	} else {
		log_error("XMLRPC sync successfully completed with {$url}:{$port}.");
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
		    if(count($sections) > 0) {
				update_filter_reload_status("Signaling CARP reload signal...");
			    carp_sync_xml($synchronizetoip, $carp['password'], $sections, $port);
			    $cli = new XML_RPC_Client('/xmlrpc.php', $synchronizetoip, $port);
			    $msg = new XML_RPC_Message('pfsense.filter_configure', array(new XML_RPC_Value($carp['password'], 'string')));
			    $cli->setCredentials('admin', $carp['password']);
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