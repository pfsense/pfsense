<?php
/* $Id$ */
/*
	filter.inc
	Copyright (C) 2004-2006 Scott Ullrich
        Copyright (C) 2005 	Bill Marquette
	Copyright (C) 2006 	Peter Allgeyer
	Copyright (C) 2008 	Ermal Luci
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
/* DISABLE_PHP_LINT_CHECKING */

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

/* Used to hold the interface list that will be used on ruleset creation. */
$FilterIflist = array();

function filter_pflog_start() {
	global $config, $g;
	
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_pflog_start() being called $mt\n";
	}

	mute_kernel_msgs();

	$pid = 0;
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
	global $config, $g, $after_filter_configure_run, $FilterIflist;
	filter_pflog_start();
	update_filter_reload_status("Initializing");
	/* invalidate interface cache */
	get_interface_arr(true);
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_configure_sync() being called $mt\n";
	}

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

	/* Get interface list to work with. */
	generate_optcfg_array();

	if ($g['booting'] == true)
		echo "Configuring firewall";
	/* generate aliases */
	if ($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Creating aliases");
	$aliases = filter_generate_aliases();
	
	/* generate nat rules */
	if ($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating NAT rules");
	$natrules = filter_nat_rules_generate();

	/* generate pfctl rules */
	if ($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating filter rules");
	$pfrules = filter_rules_generate();
	
	/* generate altq, limiter */
	if ($g['booting'] == true) 
		echo ".";
	update_filter_reload_status("Generating ALTQ queues");
	$altq_queues = filter_generate_altq_queues();
	update_filter_reload_status("Generating Limiter rules");
	$dummynet_rules = filter_generate_dummynet_rules();
	if ($g['booting'] == true)
		echo ".";

	update_filter_reload_status("Loading filter rules");

	/* enable pf if we need to, otherwise disable */
	if (!isset ($config['system']['disablefilter'])) {
		mwexec("/sbin/pfctl -e");
	} else {
		mwexec("/sbin/pfctl -d");
		unlink_if_exists("{$g['tmp_path']}/filter_loading");
		update_filter_reload_status("Filter is disabled.  Not loading rules.");
		if ($g['booting'] == true)
                	echo "done.\n";
		return;
	}

	$fd = fopen("{$g['tmp_path']}/rules.debug", "w");
	$rules .= "{$aliases} \n";

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

	$rules .= "{$dummynet_rules}\n";
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
	if ($g['booting'] == true)
                echo ".";

	system_routing_configure();
	
	find_dns_aliases();
	
	update_filter_reload_status("Done");
	if ($g['booting'] == true)
		echo "done.\n";

	return 0;
}

function filter_generate_aliases() {
	global $config, $FilterIflist;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_generate_aliases() being called $mt\n";
	}
	$alias = "#System aliases\n ";

	$aliases .= "loopback = \"{ lo0 }\"\n";

	$bridgetracker = 0;
	foreach ($FilterIflist as $if => $ifcfg) {

		$aliases .= "{$ifcfg['descr']} = \"{ {$ifcfg['if']}";

		/* XXX: Ugly but this avoids uneccesary pollution in aliases. */
		if ($ifcfg['ip'] != "carpdev-dhcp") {
			$ip = find_interface_ip($ifcfg['if']);
			if($ip) {
				$carp_ints = link_ip_to_carp_interface($ip);
				if($carp_ints)
					$aliases .= " {$carp_ints}";
			}
		}

		$aliases .= " }\"\n";
	}
	$aliases .= "# User Aliases \n";
	/* Setup pf groups */
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $aliased) {
			$extraalias = "";
			$ip = find_interface_ip($aliased['address']);
			$extraalias = " " . link_ip_to_carp_interface($ip);
			$aliases .= "{$aliased['name']} = \"{ {$aliased['address']}{$extralias} }\"\n";
		}
	}

	$result = "{$alias} \n";
	$result .= "{$aliases}";
	return $result;
}

/* returns space seperated list of vpn subnets */
function get_vpns_list() {
	global $config;
	/* build list of vpns */
	$vpns = "";
	$vpns_arr = array();

	/* ipsec */
	if (isset($config['ipsec']['enable']))
		if (is_array($config['ipsec']['phase2']))
			foreach ($config['ipsec']['phase2'] as $ph2ent)
				if(!$ph2ent['mobile'])
					$vpns_arr[] = ipsec_idinfo_to_cidr($ph2ent['remoteid']);

	/* openvpn */
	foreach (array('client', 'server') as $type)
		if (is_array($$config['openvpn']["openvpn-$type"]))
			foreach ($config['openvpn']["openvpn-$type"] as & $settings)
				if (is_array($settings))
					if(is_subnet($settings['remote-subnet']))
						$vpns_arr[] = $tunnel['remote_network'];

	/* pppoe */
	if ($config['pppoe']['remoteip'])
		if(is_subnet($tunnel['remote-subnet']))
			$vpns_arr[] = $config['pppoe']['remoteip'] ."/". $config['pppoe']['pppoe_subnet'];

	if(!empty($vpns_arr))
		$vpns = implode(" ", $vpns_arr);

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
		$interface_ip = get_current_wan_address($ifent);
		$sn = $config['interfaces'][$ifent]['subnet'];
		if($sn == "") {
			$subnet = "{$interface_ip}/32";
		} else {
			$sa = gen_subnet($interface_ip, $config['interfaces'][$ifent]['subnet']);
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

function generate_optcfg_array() 
{
	global $config, $FilterIflist;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "generate_optcfg_array() being called $mt\n";
	}

	/* if list */
	$iflist = get_configured_interface_with_descr();

	foreach ($iflist as $if => $ifdetail) {
		$oc = $config['interfaces'][$if];

		$oic = array();
		$oic['if'] = get_real_wan_interface($if);

		$oic['ip'] = $oc['ipaddr'];
		$oic['sn'] = $oc['subnet'];
		$oic['descr'] = $ifdetail;
		$oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
		$oic['nonat'] = $oc['nonat'];
		$oic['alias-address'] = $oc['alias-address'];
		$oic['alias-subnet'] = $oc['alias-subnet'];
		$oic['gateway'] = $oc['gateway'];
		$oic['spoofcheck'] = "yes";
		$oic['bridge'] = link_int_to_bridge_interface($if);

		$FilterIflist[$if] = $oic;
	}
		
	if ($config['pptpd']['mode'] == "server" || $config['pptpd']['mode'] == "redir") {
		$oic = array();
		$oic['if'] = 'pptp';
		$oic['descr'] = 'PPTP';
		$oic['ip'] = $config['pptpd']['localip'];
		$oc['sa'] = $config['pptpd']['remoteip'];
		$oc['sn'] = $g['pptp_subnet'];
		if($config['pptpd']['pptp_subnet'] <> "")
			$oc['sn'] = $config['pptpd']['pptp_subnet'];

		$FilterIflist['pptp'] = $oic;
	}

	if ($config['pppoe']['mode'] == "server") {
		$oic = array();
		$oic['if'] = 'pppoe';
		$oic['descr'] = 'PPPoE';
		$oc['ip'] = $config['pppoe']['localip'];
		$oc['sa'] = $config['pppoe']['remoteip'];
		$oc['sn'] = $g['pppoe_subnet'];
		if($config['pppoe']['pppoe_subnet'] <> "")
			$oc['sn'] = $config['pppoe']['pppoe_subnet'];

		$FilterIflist['pppoe'] = $oic;
	}

	/* add ipsec interfaces */
	if (isset($config['ipsec']['enable'])) {
		$oic = array();
		$oic['if'] = 'enc0';
		$oic['descr'] = 'IPsec';
		$oic['ip'] = "none";
		$FilterIflist['enc0'] = $oic;
	}

	/* add openvpn interfaces */
	if ($config['openvpn']['openvpn-server'] || $config['openvpn']['openvpn-client']) {

		$ovpnifs = array();
		$oic = array();
		$oic['if'] = "openvpn";
		$oic['descr'] = 'OpenVPN';
		$oic['ip'] = "none";

		$FilterIflist['openvpn'] = $oic;
	}
}

function filter_flush_nat_table() 
{
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_flush_nat_table() being called $mt\n";
	}
	return mwexec("/sbin/pfctl -F nat");
}

function filter_flush_state_table() 
{
	global $config, $g;

	return mwexec("/sbin/pfctl -F state");
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if($if, $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false) 
{
	global $config;

	/* XXX: billm - any idea if this code is needed? */
	if($src == "/32" || $src{0} == "/")
		return "# src incorrectly specified\n";

    if ($natip != "") {
            $tgt = "{$natip}/32";
    } else {
			$natip = get_current_wan_address($if);
			if($natip)
			$tgt = "{$natip}/32";
			else
            	$tgt = "($if)";
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

	$if_friendly = convert_friendly_interface_to_friendly_descr($if);

	/* Put all the pieces together */
	if ($if_friendly)
		$natrule = "{$nat} on \${$if_friendly} from {$src} to {$dst} {$target}{$staticnatport_txt}\n";
	else
		$natrule .= "# Could not convert {$if} to friendly name(alias)\n";

	return $natrule;
}

function is_one_to_one_or_server_nat_rule($iptocheck) 
{
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

function filter_nat_rules_generate() 
{
	global $config, $g, $after_filter_configure_run, $used_pftpx_ports, $FilterIflist;

	$natrules .= "nat-anchor \"ftp-proxy/*\"\n";

	$natrules .= "nat-anchor \"natearly/*\"\n";
	$natrules .= "nat-anchor \"natrules/*\"\n\n";

	/*
	$optints = array();
	generate_optcfg_array($optints);
	*/

	update_filter_reload_status("Creating 1:1 rules...");

	/* any 1:1 mappings? */
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $natent) {
			if (!is_numeric($natent['subnet']))
				$sn = 32;
			else
				$sn = $natent['subnet'];

			if (!$natent['interface'])
				$natif == "wan";
			else 
				$natif = $natent['interface'];

			$natif = get_real_wan_interface($natif);

			if ($natif)
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

				if (!$obent['interface'])
					$natif == "wan";
				else 
					$natif = $obent['interface'];

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

		$tonathosts = "";
		$numberofnathosts = 0;
		/* static routes */
		if (is_array($config['staticroutes']['route'])) {
			foreach ($config['staticroutes']['route'] as $route) {
				$netip = explode("/", $route['network']);
				if ((! interface_has_gateway($route['interface'])) && (is_private_ip($netip[0]))) {
					$numberofnathosts++;
					$tonathosts .= "{$route['network']} ";
				}
			}
		}

		/* create outbound nat entries for all local networks */
		foreach($FilterIflist as $ocname => $oc) {
			if (!interface_has_gateway($ocname)) {
				if(is_ipaddr($oc['alias-address'])) {
					$aliastarget = $oc['alias-address'];
					$aliassubnet = $oc['alias-subnet'];
					$numberofnathosts++;
					$tonathosts .= "{$oc['sa']}/{$oc['sn']} ";
				}
				if ($oc['sa']) {
					$tonathosts .= "{$oc['sa']}/{$oc['sn']} ";
					$numberofnathosts++;
				}
			}
		}

		/* PPTP subnet */
		if ($pptpdcfg['mode'] == "server") {
			$pptp_subnet = $g['pptp_subnet'];
			if ($config['pptp']['pptp_subnet'] <> "")
				$pptp_subnet = $config['pptp']['pptp_subnet'];

			if (is_private_ip($pptpdcfg['remoteip'])) {
				$numberofnathosts++;
				$tonathosts .= "{$pptpdcfg['remoteip']}/{$pptp_subnet} ";
			}
		}

		/* PPPoE subnet */
		if ($pppoecfg['mode'] == "server") {
			$pppoe_subnet = $g['pppoe_subnet'];
			if ($config['pppoe']['pppoe_subnet'] <> "")
				$pppoe_subnet = $config['pppoe']['pppoe_subnet'];
			if (is_private_ip($pppoecfg['remoteip'])) {
				$numberofnathosts++;
				$tonathosts .= "{$pppoecfg['remoteip']}/{$pppoe_subnet} ";
			}
		}

		$natrules .= "\n# Subnets to NAT \n";
		if ($numberofnathosts > 4) {
			$natrules .= "table <tonatsubnets> { {$tonathosts} }\n";
			$macroortable = "<tonatsubnets>";
		} else if ($numberofnathosts > 0) { 
			$natrules .= "tonatsubnets  = \"{ {$tonathosts} }\"\n";
			$macroortable = "\$tonatsubnets";
		}
		
		if ($numberofnathosts > 0):

		foreach ($FilterIflist as $if => $ifcfg) {
			update_filter_reload_status("Creating outbound rules {$if} - ({$ifcfg['descr']})");

			if (interface_has_gateway($if)) {
				$target = get_current_wan_address($if);
					
				/* do not nat tftp proxy */
				$natrules .= "no nat on \${$ifcfg['descr']} to port tftp\n";

				/* create outbound nat entries for all local networks */
				$natrules .= filter_nat_rules_generate_if($if,
					"{$macroortable}", 500, "", 500, $target, 500, false);
				$natrules .= filter_nat_rules_generate_if($if,
					"{$macroortable}", 4500, "", 4500, $target, 4500, false);
				$natrules .= filter_nat_rules_generate_if($if,
					"{$macroortable}", 5060, "", 5060, $target, 5060, false);
				$natrules .= filter_nat_rules_generate_if($if,
					"{$macroortable}", null, "", null, $target, null, isset($ifcfg['nonat']));
				$natrule .= "\n";

			}
		}
		
		endif;
	}

	$natrules .= "\n#SSH Lockout Table\n";
	$natrules .= "table <sshlockout> persist\n\n";

	/* is SPAMD insalled? */
	if (is_package_installed("spamd") == 1) {
		$natrules .= "\n# spam table \n";
		$wanif = get_real_wan_interface("wan");
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

	$natrules .= "\n# FTP proxy\n";
        $natrules .= "rdr-anchor \"ftp-proxy/*\"\n";
        $natrules .= "rdr-anchor \"tftp-proxy/*\"\n";

	$natrules .= "\n";

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
	foreach ($FilterIflist as $ifent => $ifcfg) {
		$realif = $ifcfg['if'];
		$int_ip = get_current_wan_address($ifent);

		if ($int_ip == "")
			continue;
		if(isset($config['interfaces'][$ifent]['disableftpproxy'])) {
			if($g['debug'])
				log_error("Filter: FTP proxy disabled for interface {$ifcfg['descr']} - ignoring.");
			$interface_counter++;
			continue;
		}
		/* are we in routed mode? no source nat rules and not a outside interface? */
		/* If we have advanced outbound nat we skip the FTP proxy, we use ftpsesame */
		if((isset($config['nat']['advancedoutbound']['enable'])) && 
			(! interface_has_gateway($ifent))) {
			$sourcenat = 0;
			/* we are using advanced outbound nat, are we in routing mode? */
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
					log_error("Filter: No AON rule matched for interface {$ifcfg['descr']} - not using the FTP proxy");
				$interface_counter++;
				continue;
			} else {
				if($g['debug'])
					log_error("Filter: AON Rule matched for interface {$ifcfg['descr']} - using FTP proxy");
			}
		}
		/* if the user has defined, include the alias so that we do not redirect ftp
		   connections across the tunnels to ftp-proxy */
		/* if interface lacks an ip, dont setup a rdr for ftp.  
		   they are most likely on a bridged interface */
		if($int_ip and $vpns_list) {
			$natrules .= "no rdr on $realif proto tcp from any to <vpns> port 21\n";
			$natrules .= "no rdr on $realif proto {tcp,udp} from any to <vpns> port tftp\n";
			if($onetoone_list) {
				$natrules .= "no rdr on $realif proto tcp from <onetoonelist> to any port 21\n";
				$natrules .= "no rdr on $realif proto udp from <onetoonelist> to any port tftp\n";
			}
		}
		$tmp_port = 8021 + $interface_counter;
		if($g['debug'])
			log_error("Filter: FTP proxy port ($tmp_port) enabled for interface {$ifcfg['descr']}");
		$temp_array_holder_pftpx = array();
		$temp_array_holder_pftpx['port'] = $tmp_port;
		$temp_array_holder_pftpx['interface'] = "\${$ifcfg['descr']}";
		$temp_array_holder_pftpx['realif'] = $realif;
		$used_pftpx_ports[] = $temp_array_holder_pftpx;
		$natrules .= "rdr on $realif proto tcp from any to any port 21 -> 127.0.0.1 port {$tmp_port}\n";
		$natrules .= "rdr on $realif proto udp from any to any port tftp -> 127.0.0.1 port 6969\n";
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

	// Open inetd.conf write handle
	$inetd_fd = fopen("/var/etc/inetd.conf","w");

	/* add tftp protocol helper */
        fwrite($inetd_fd, "tftp\tdgram\tudp\twait\t\troot\t/usr/local/sbin/tftp-proxy -v\n");

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

			if (!$rule['interface'])
				$natif == "wan";
			else 
				$natif = $rule['interface'];

			$natif = get_real_wan_interface($natif);

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
							$external_address = find_interface_ip($natif);
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
			$rule_interface_ip = find_interface_ip($natif);
			$rule_interface_subnet = $config['interfaces'][$rule['interface']]['subnet'];
			$rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
			if($config['interfaces']['lan']) {
				if($rule['external-address'] == "any" and $rule['interface'] == "lan") {
					$natrules .= "\n";
					$natrules .= "no nat on {$natif} proto tcp from ({$natif}) to {$rule_subnet}/{$rule_interface_subnet}\n";
					$natrules .= "nat on {$natif} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$extport[0]} -> ({$natif})\n";
				}
			}

			if(!isset($config['system']['disablenatreflection'])) {

				update_filter_reload_status("Setting up reflection");

				$natrules .= "\n# Reflection redirects\n";
				foreach ($FilterIflist as $ifent => $ifname) {

					/* do not process interfaces with gateways*/
					if (interface_has_gateway($ifent))
						continue;

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
									  														$natrules .= "rdr on {$ifname['if']} proto tcp from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
									  		$starting_localhost_port++;
									  		fwrite($inetd_fd, "{$starting_localhost_port}\tstream\ttcp/udp\tnowait/0\tnobody\t/usr/bin/nc nc -w {$reflectiontimeout} {$target} {$tda}\n");
									  			$natrules .= "rdr on { {$ifname['if']} } proto udp from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
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
									  			$natrules .= "rdr on { {$ifname['if']} } proto {$protocol} from any to {$extaddr} port { {$xxx} } -> 127.0.0.1 port {$starting_localhost_port}\n";
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
	}

        fclose($inetd_fd);      // Close file handle

        // Check if inetd is running, if not start it.  If so, restart it gracefully.
        $helpers = trim(exec("/bin/ps ax | /usr/bin/grep inetd | /usr/bin/grep -v grep | /usr/bin/grep 127"));
        if(!$helpers)
                mwexec("/usr/sbin/inetd -wW -R 0 -a 127.0.0.1 /var/etc/inetd.conf");
        else
                mwexec("/usr/bin/killall -HUP inetd");

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
#rdr on \$WAN proto gre from any to any -> $pptpdtarget
rdr on \$WAN proto tcp from any to any port 1723 -> $pptpdtarget

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

function run_command_return_string($cmd) 
{
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

function generate_user_filter_rule_arr($rule) 
{
	global $config;
	update_filter_reload_status("Creating filter rule {$rule['descr']} ...");
	$ret = array();
	$line = generate_user_filter_rule($rule);
	$ret['rule'] = $line;
	$ret['interface'] = $rule['interface'];
	if($rule['descr'] != "" and $line != "")
		$ret['descr'] = "label \"USER_RULE: " . str_replace('"', '', $rule['descr']) . "\"";
	else
		$ret['descr'] = "label \"USER_RULE\"";

	return $ret;
}

function generate_user_filter_rule($rule) 
{
		global $config, $g, $FilterIflist;
		global $table_cache;
		global $schedule_enabled;

		if(isset($config['system']['developerspew'])) {
			$mt = microtime();
			echo "generate_user_filter_rule() being called $mt\n";
		}

		/* don't include disabled rules */
		if (isset($rule['disabled'])) {
			return "# rule " . $rule['descr'] . " disabled \n";
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

		$pptpdcfg = $config['pptpd'];
		$pppoecfg = $config['pppoe'];

		$int = "";

		/* Check to see if the interface is in our list */
		if (isset($rule['floating'])) {
				if (isset($rule['interface']) && $rule['interface'] <> "") {
               		$interfaces = explode(",", $rule['interface']);
					$ifliste = "";
                    foreach ($interfaces as $iface) {
						if (array_key_exists($iface, $FilterIflist)) 
							$ifliste .= " " . $FilterIflist[$iface]['if'] . " ";
                    }
					if ($ifliste <> "")
	                 	$aline['interface'] = " on { {$ifliste} }";
					else
						$aline['interface'] = "";
				}
				else
					$aline['interface'] = "";
		} else if (!array_key_exists($rule['interface'], $FilterIflist)) {
				foreach($FilterIflist as $oc) $item .= $oc['descr'];
					return "# {$item} {$rule['interface']} array key does not exist for " . $rule['descr'];
       	} else
			$aline['interface'] = " on \$" . $FilterIflist[$rule['interface']]['descr'] . " ";
	
		$ifcfg = $FilterIflist[$rule['interface']];

		if ($pptpdcfg['mode'] != "server") {
			if (($rule['source']['network'] == "pptp") ||
				($rule['destination']['network'] == "pptp")) 
					return "# source network or destination network == pptp on " . $rule['descr'];
		}

		if ($rule['source']['network'] && strstr($rule['source']['network'], "opt")) {
			if (!array_key_exists($rule['source']['network'], $FilterIflist)) {
				$optmatch = "";
				if (preg_match("/opt([0-999])/", $rule['source']['network'], $optmatch)) {
					$opt_ip = get_current_wan_address("opt{$optmatch[1]}");
					if(!$opt_ip)
						return "# unresolvable optarray $optmatch[0] - $opt_ip";
				} else {
					return "# {$rule['source']['network']} !array_key_exists source network " . $rule['descr'];
				}
			}
		}
		if ($rule['destination']['network'] && strstr($rule['destination']['network'], "opt")) {
			if (!array_key_exists($rule['destination']['network'], $FilterIflist)) {
				if(preg_match("/opt([0-999])/", $rule['destination']['network'], $optmatch)) {
					$opt_ip = get_current_wan_address("opt{$optmatch[1]}");
					if(!$opt_ip)
						return "# unresolvable oparray $optmatch[0] - $opt_ip";
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

		update_filter_reload_status("Setting up pass/block rules");

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
		} else 
			$aline['type'] = $type . " ";

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

		/* set the gateway interface */
		update_filter_reload_status("Setting up pass/block rules {$rule['descr']}");

		$foundlb = 0;
		/* do not process reply-to for gateway'd rules */
		if ($rule['gateway'] == "" && interface_has_gateway($rule['interface'])) {
			$rg = get_interface_gateway($rule['interface']);
			if (is_ipaddr($rg))
    				$aline['reply'] = "reply-to ( {$ifcfg['if']} {$rg} ) ";
			else 
                        	log_error("Could not find gateway for interface({$rule['interface']}).");

		}

		/* if user has selected a custom gateway, lets work with it */
		else if($rule['gateway'] <> "") {
			$routeto = " route-to { ";
			if (is_array($config['gateways']['gateway_group'])) {
				foreach($config['gateways']['gateway_group'] as $group) {
					update_filter_reload_status("Creating gateway group item...");
					if($group['name'] == $rule['gateway']) {
						$gateway = $rule['gateway'];
						if (array_key_exists($gateway, $FilterIflist)) {
							$return_gateway = get_interface_gateway($gateway);
							if (!is_ipaddr($return_gateway)) {
								log_error("Load Balancer code could not find gateway for {$gateway} interface.");
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
						if($tiers_count == 0) {
							/* Oh dear, we have no members! Engage Plan B */
							log_error("All gateways are unavailable, proceeding with configured XML settings!");
							foreach($group['item'] as $item) {
								foreach($group['item'] as $item) {
									$itemsplit = explode("|", $item);
									$tier = $itemsplit[1];
									$gwname = $itemsplit[0];
									$tiers[$tier][] = $gwname;
								}
							}
						}

						/* we do not really foreach the tiers as we stop after the first tier */
						foreach($tiers as $tier) {
							/* process all gateways in this tier */
							$member_count = count($tier);
							foreach($tier as $member) {
								/* determine interface gateway */
								foreach($config['gateways']['gateway_item'] as $gateway) {
									if($gateway['name'] == $member) {
										$int = get_real_wan_interface($gateway['interface']);
										if(is_ipaddr($gateway['gateway'])) 
											$gatewayip = $gateway['gateway'];
										else 
											$gatewayip = lookup_gateway_ip_by_name($gateway['gateway']);
										break;
									}
								}
								if (($int <> "") && is_ipaddr($gatewayip)) {
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
                               	$gw = lookup_gateway_ip_by_name($rule['gateway']);
				if ($gw == false) {
					if (array_key_exists($gateway, $FilterIflist)) {
						$int = get_real_wan_interface($gateway);
						$gw = get_interface_gateway($gateway);
					} else {
						$gw = $gateway;
						$int = guess_interface_from_ip($gw);
					}
				}
				if (is_ipaddr($gw)) {
					if (empty($int)) 
						$int = guess_interface_from_ip($gw);
					$aline['route'] = " route-to ( {$int} {$gw} ) ";
				} else 
					log_error("Could not find gateway({$rule['gateway']}) for rule {$rule['descr']} - {$rule['interface']}.");
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
				$src = $FilterIflist[$rule['source']['network']]['sa'] . "/" .
					$FilterIflist[$rule['source']['network']]['sn'];
				if (isset($rule['source']['not'])) $src = " !{$src}";
				/* check for opt$NUMip here */
				$matches = "";
				if (preg_match("/opt([0-9999])ip/", $rule['source']['network'], $matches)) {
					$optnum = $matches[1];
					$src = get_current_wan_address("opt{$optnum}");
				}
			} else {
				switch ($rule['source']['network']) {
						case 'wanip':
							$src = get_current_wan_address("wan");
							break;
						case 'lanip':
							$src = get_current_wan_address("lan");
							break;
						case 'lan':
							$lansa = gen_subnet($FilterIflist['lan']['ip'], $FilterIflist['lan']['sn']);
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
				if (alias_expand($srcport[0]))
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
				$dst = $FilterIflist[$rule['destination']['network']]['sa'] . "/" .
					$FilterIflist[$rule['destination']['network']]['sn'];
				/* check for opt$NUMip here */
				$matches = "";
				if (preg_match("/opt([0-9999])ip/", $rule['destination']['network'], $matches)) {
					$optnum = $matches[1];
					$dst = get_current_wan_address("opt{$optnum}");
				}
				if (isset($rule['destination']['not'])) $dst = " !{$dst}";
			} else {
				switch ($rule['destination']['network']) {
					case 'wanip':
						$dst = get_current_wan_address("wan");
						break;
					case 'lanip':
						$dst = get_current_wan_address("lan");
						break;
					case 'lan':
						$lansa = gen_subnet($FilterIflist['lan']['ip'], $FilterIflist['lan']['sn']);
						$lansn = $FilterIflist['lan']['sn'];
						$dst = "{$lansa}/{$lansn}";
						break;
					case 'pptp':
						$pptpsa = gen_subnet($FilterIflist['pptp']['ip'], $FilterIflist['pptp']['sn']);
						$pptpsn = $FilterIflist['pptp']['sn'];
						$dst = "{$pptpsa}/{$pptpsn}";
						break;
					case 'pppoe':
						$pppoesa = gen_subnet($FilterIflist['pppoe']['ip'], $FilterIflist['pppoe']['sn']);
						$pppoesn = $FilterIflist['pppoe']['sn'];
						$dst = "{$pppoesa}/{$pppoesn}";
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
                    	if($item != "") 
                        	$dst .= " {$not}{$item}";
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
				if (alias_expand($dstport[0]))
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

  			if (isset($rule['tag']) && $rule['tag'] <> "")
       			$aline['tag'] = " tag " .$rule['tag']. " ";
   			if (isset($rule['tagged']) && $rule['tagged'] <> "")
       			$aline['tagged'] = " tagged " .$rule['tagged'] . " ";

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
			if (isset($rule['Statetype']) && $rule['statetype'] <> "") {
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
		
			if ($rule['dnpipe'] <> "") {
				if ($rule['dnpipe'][0] == "?") {
					$aline['dnpipe'] = " dnqueue( ";
					$aline['dnpipe'] .= substr($rule['dnpipe'],1);
                                	if ($rule['pdnpipe'] <> "")
                                        	$aline['dnpipe'] .= ",".substr($rule['pdnpipe'], 1);
				} else {
					$aline['dnpipe'] = " dnpipe ( " . $rule['dnpipe'];
					if ($rule['pdnpipe'] <> "")
						$aline['dnpipe'] .= ", " . $rule['pdnpipe'];
				}
                                $aline['dnpipe'] .= ") ";
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
			$aline['queue'] . $aline['dnpipe'] . 
			" label \"NEGATE_ROUTE: Negate policy route for local network(s)\"\n";
		/* negate directly connected networks for load balancer/gateway rules */
		$direct_networks = " to <direct_networks> ";
		$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] . $aline['prot'] .
			$aline['src'] . $aline['srcport'] . $aline['os'] . $direct_networks . $aline['dstport'].
			$aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] . 
			$aline['flags'] . $aline['queue'] . $aline['dnpipe'] .
			" label \"NEGATE_ROUTE: Negate policy route for local network(s)\"\n";
	}

	/* piece together the actual user rule */
	$line .= $aline['type'] . $aline['direction'] . $aline['log'] . $aline['quick'] . $aline['interface'] . $aline['reply'] .
		$aline['route'] . $aline['prot'] . $aline['src'] . $aline['srcport'] . $aline['os'] . $aline['dst'] .
		$aline['dstport'] . $aline['icmp-type'] . $aline['tag'] . $aline['tagged'] . $aline['dscp'] . 
		$aline['flags'] . $aline['queue'] . $aline['dnpipe'];

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

function filter_rules_generate() 
{
	global $config, $g, $table_cache, $used_pftpx_ports, $FilterIflist;

	update_filter_reload_status("Creating default rules");

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_generate() being called $mt\n";
	}

	$pptpdcfg = $config['pptpd'];
	$pppoecfg = $config['pppoe'];

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

	$bogontableinstalled = 0;
	foreach ($FilterIflist as $on => $oc) {
		/* block bogon networks */
		/* http://www.cymru.com/Documents/bogon-bn-nonagg.txt */
		/* file is automatically in cron every 3000 minutes */
		if (isset($config['interfaces'][$on]['blockbogons'])) {
			if ($bogontableinstalled == 0)
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
		if (is_array($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $oc2) {
				if (stristr($oc2['members'], $on)) {
					$isbridged = true;
					break;
				}
			}
		}
		if ($oc['ip'] && !($isbridged) && isset($oc['spoofcheck']))
			$ipfrules .= filter_rules_spoofcheck_generate($on, $oc['if'], $oc['sa'], $oc['sn'], $log);

		/* block private networks ? */
		if (isset($config['interfaces'][$on]['blockpriv'])) {
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

		switch ($oc['ip']) {
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
			if (isset($config['dhcpd'][$on]['enable']) && empty($oc['bridge'])) {
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
	 * 		If you ever add the 'quick' keyword to the antispoof rules above move the looback
	 *		rules before them.
	 */
	$ipfrules .= <<<EOD
anchor "spoofing"

# loopback
anchor "loopback"
pass in on \$loopback all label "pass loopback"
pass out on \$loopback all label "pass loopback"

anchor "firewallout"
# let out anything from the firewall host itself and decrypted IPsec traffic
pass out all keep state label "let out anything from firewall host itself"

EOD;

	/* add ipsec interfaces */
        if (isset($config['ipsec']['enable']) || isset($config['ipsec']['mobileclients']['enable'])) 
		$ipfrules .= <<<EOD
pass out on \$IPsec all keep state label "IPsec internal host to host"

EOD;


	/* pass traffic between statically routed subnets and the subnet on the
	   interface in question to avoid problems with complicated routing
	   topologies */
	if (isset($config['filter']['bypassstaticroutes']) && is_array($config['staticroutes']['route']) && count($config['staticroutes']['route'])) {
		$ipfrules .= "anchor \"staticrouted\"";
		foreach ($config['staticroutes']['route'] as $route) {
			if (is_array($FilterIflist[$route['interface']])) {
				$oc = $FilterIflist[$route['interface']];
				if ($oc['ip']) {
					$sa = $oc['sa'];
					$sn = $oc['sn'];
					$if = $oc['if'];
				}
				if ($sa) {
				$ipfrules .= <<<EOD
pass in on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass in on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"
pass out on \${$oc['descr']} from {$sa}/{$sn} to {$route['network']} label "pass traffic between statically routed subnets"
pass out on \${$oc['descr']} from {$route['network']} to {$sa}/{$sn} label "pass traffic between statically routed subnets"

EOD;
				}
			}
		}
	}


	if (!isset($config['system']['webgui']['noantilockout'])) {

		if (count($config['interfaces']) > 1) {
				/* if antilockout is enabled, LAN exists and has
                                  an IP and subnet mask assigned */
				$lanif = get_real_wan_interface("lan");
				$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on {$lanif} from any to ({$lanif}) keep state label "anti-lockout rule"

EOD;
                } else {
			/* single-interface deployment, add to WAN  */
					$wanif = get_real_wan_interface("wan");
					$ipfrules .= <<<EOD
# make sure the user cannot lock himself out of the webConfigurator or SSH
anchor "anti-lockout"
pass in quick on {$wanif} from any to ({$wanif}) keep state label "anti-lockout rule"

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
pass in on \$WAN proto gre from any to $pptpdtarget keep state label "allow gre pptpd"
pass in on \$WAN proto tcp from any to $pptpdtarget port = 1723 modulate state label "allow pptpd {$pptpdtarget}"

EOD;

		} else {
			/*    this shouldnt ever happen but instead of breaking the clients ruleset
			 *    log an error.
                         */
			log_error("ERROR!  PPTP enabled but could not resolve the \$pptpdtarget");
		}
	}


        $ipfrules .= "# NAT Reflection rules\n";

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

                        if (!$target) {
				$ipfrules .= "#Unresolvable alias not installing rule\n";
                                continue;       /* unresolvable alias */
			}

                        if ($rule['external-address']) {
                                if($rule['external-address'] <> "any")
                                        $extaddr = $rule['external-address'] . "/32";
                                else
                                        $extaddr = $rule['external-address'];
                        } else
                                $extaddr = get_current_wan_address($rule['interface']);

                        /*
                         *   Expand aliases
                         *   XXX: may want to integrate this into pf macros
                         */
                        if(alias_expand($target))
                                $target = alias_expand($target);
                        if(alias_expand($extaddr))
                                $extaddr = alias_expand($extaddr);

                        if(!isset($config['system']['disablenatreflection'])) {

                        foreach ($FilterIflist as $ifent => $ifname) {

                                /* do not process interfaces with gateways*/
                                if (interface_has_gateway($ifent))
                                        continue;

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

                                                        switch($rule['protocol']) {
                                                       	case "tcp/udp":
                                                                $protocol = "{ tcp udp }";
                                                                $ipfrules .= "pass in on { {$ifname['if']} } inet proto tcp from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
                                                                $starting_localhost_port++;
                                                                $ipfrules .= "pass in on { {$ifname['if']} } inet proto udp from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
                                                                 break;
                                                        case "tcp":
                                                        case "udp":
                                                        	$protocol = $rule['protocol'];
                                                                $ipfrules .= "pass in on { {$ifname['if']} } inet proto {$rule['protocol']} from any to \$loopback port {$starting_localhost_port} keep state label \"NAT REFLECT: Allow traffic to localhost\"\n";
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

		$ipfrules .= <<<EOD
# package manager late specific hook
anchor "packagelate"

EOD;
	
	/* XXX: ermal -- i am leaving this here for a second pass to optimize this rules. */
	$ipfrules .= "\n# SSH lockout\n";
	$ipfrules .= "block in log quick proto tcp from <sshlockout> to any port 22 label \"sshlockout\"\n";

	$ipfrules .= "anchor \"ftp-proxy/*\"\n";

	$ipfrules .= process_carp_rules();

	$ipfrules .= "\n";

        if(!isset($config['system']['disableftpproxy'])) {

                $ipfrules .= "\n# enable ftp-proxy\n";

                foreach($used_pftpx_ports as $pftpx) {
                        if(!isset($oc['gateway']) && $oc['if'] <> "") {
                                $ipfrules .= "pass in on {$pftpx['interface']} inet proto tcp from any to \$loopback port {$pftpx['port']} keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
                                $ipfrules .= "pass in on {$pftpx['interface']} inet proto tcp from any to \$loopback port 21 keep state label \"FTP PROXY: Allow traffic to localhost\"\n";
                        }

                        if(isset($config['system']['rfc959workaround'])) {
                                $ipfrules .= <<<EODEOD
# Fix sites that violate RFC 959 which specifies that the data connection
# be sourced from the command port - 1 (typically port 20)
# This workaround doesn't expose us to any extra risk as we'll still only allow
# connections to the firewall on a port that ftp-proxy is listening on

EODEOD;

                                $ipfrules .= "pass in on {$pftpx['interface']} inet proto tcp from any to ({$pftpx['realif']}) port > 49000 user proxy flags S/SA keep state label \"FTP PROXY: RFC959 violation workaround\" \n";
                                $ipfrules .= <<<EOD
# Support for allow limiting of TCP connections by establishment rate
pass in on {$pftpx['interface']} inet proto tcp from port 20 to ({$pftpx['realif']}) port > 49000 user proxy flags S/SA keep state label "FTP PROXY: PASV mode data connection"

EOD;

                        }
                }
        }


	if (isset($config['filter']['rule'])) {
		/* Pre-cache all our rules so we only have to generate them once */
		$rule_arr1 = array();
		$rule_arr2 = array();
		/* 
		 * XXX: This is a double pass but it needs to be this way.
		 * to allow users to override floating rules.
		 */
		foreach ($config['filter']['rule'] as $rule) {
			update_filter_reload_status("Pre-caching {$rule['descr']}...");
			if (!isset ($rule['disabled'])) {
				if(isset($rule['floating']))
					$rule_arr1[] = generate_user_filter_rule_arr($rule);
				else
					$rule_arr2[] = generate_user_filter_rule_arr($rule);
			}
		}
		$rule_arr = array_merge($rule_arr1,$rule_arr2);

		$ipfrules .= "\n# User-defined aliases follow\n";
		/* tables for aliases */
		foreach($table_cache as $table)
			$ipfrules .= $table;

		$ipfrules .= "\n# User-defined rules follow\n";
		/* Generate user rule lines */
		foreach($rule_arr as $rule) {
			if (isset($rule['disabled']))
				continue;
			if (!$rule['rule'])
				continue;
			$ipfrules .= "{$rule['rule']} {$rule['descr']}\n";
		}
	}

	update_filter_reload_status("Creating IPsec rules...");

	$ipfrules .= "\n# VPN Rules\n";

	/* Is IP Compression enabled? */
	if (isset($config['ipsec']['ipcomp']))
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=1");
	else
		exec("/sbin/sysctl net.inet.ipcomp.ipcomp_enable=0");

	if (isset($config['ipsec']['enable']) && 
	   is_array($config['ipsec']['phase1'])) {

		/* step through all phase1 entries */
		foreach ($config['ipsec']['phase1'] as $ph1ent) {

			if (isset ($ph1ent['disabled']))
				continue;

			update_filter_reload_status("Creating IPsec phase1 items for {$ph1ent['descr']}...");

			/* determine local and remote peer addresses */

			if (!isset($ph1ent['mobile'])) {
				$rgip = ipsec_get_phase1_dst($ph1ent);
				if (!$rgip) {
					$ipfrules .= "# ERROR! Unable to determine remote IPsec peer address for {$ph1ent['remote-gateway']}\n";
					continue;
				}
			} else
				$rgip = " any ";

			/* Determine best description */
			if ($ph1ent['descr'])
				$descr = $ph1ent['descr'];
			else
				$descr = $rgip;

			/*
			 * Step through all phase2 entries and determine
			 * which protocols are in use with this peer
			 */

			$prot_used_esp = false;
			$prot_used_ah  = false;

			if (is_array($config['ipsec']['phase2'])) {

				foreach ($config['ipsec']['phase2'] as $ph2ent) {

					/* only evaluate ph2's bound to our ph1 */
					if ($ph2ent['ikeid'] != $ph1ent['ikeid'])
						continue;

					if ($ph2ent['protocol'] == 'esp')
						$prot_used_esp = true;

					if ($ph2ent['protocol'] == 'ah')
						$prot_used_ah = true;
				}
			}

			foreach ($FilterIflist as $ifr => $ifcfg) {

				/* Only process interfaces with gateway */
				if (! interface_has_gateway($ifr)) 
					continue;
				
				$gateway = get_interface_gateway($ifr);
				$interface = get_real_wan_interface($ifr);
				/* Just in case */
				if (!is_ipaddr($gateway) || empty($interface)) {
					$route_to = " ";
					$reply_to = " ";
				} else { 
					$route_to = " route-to ( $interface $gateway ) ";
					$reply_to = " reply-to ( $interface $gateway ) ";
				}

				/* Add rules to allow IKE to pass */
				$shorttunneldescr = substr($descr, 0, 36);
				$ipfrules .= <<<EOD
pass out on \${$ifcfg['descr']} $route_to proto udp from any to {$rgip} port = 500 keep state label \"IPsec: {$shorttunneldescr} - outbound isakmp\"
pass in on \${$ifcfg['descr']} $reply_to proto udp from {$rgip} to any port = 500 keep state label \"IPsec: {$shorttunneldescr} - inbound isakmp\"

EOD;
				/* If NAT-T is enabled, add additional rules */
				if ($ph1ent['nat_traversal'] != "off" ) {
					$ipfrules .= <<<EOD
pass out on \${$ifcfg['descr']} $route_to proto udp from any to {$rgip} port = 4500 keep state label \"IPsec: {$shorttunneldescr} - outbound nat-t\"
pass in on \${$ifcfg['descr']} $reply_to proto udp from {$rgip} to any port = 4500 keep state label \"IPsec: {$shorttunneldescr} - inbound nat-t\"

EOD;
				}

				/* Add rules to allow the protocols in use */
				if ($prot_used_esp == true) {
					$ipfrules .= <<<EOD
pass out on \${$ifcfg['descr']} $route_to proto esp from any to {$rgip} keep state label \"IPsec: {$shorttunneldescr} - outbound esp proto\"
pass in on \${$ifcfg['descr']} $reply_to proto esp from {$rgip} to any keep state label \"IPsec: {$shorttunneldescr} - inbound esp proto\"

EOD;
				}
				if ($prot_used_ah == true) {
					$ipfrules .= <<<EOD
pass out on \${$ifcfg['descr']} $route_to proto ah from any to {$rgip} keep state label \"IPsec: {$shorttunneldescr} - outbound ah proto\"
pass in on \${$ifcfg['descr']} $reply_to proto ah from {$rgip} to any keep state label \"IPsec: {$shorttunneldescr} - inbound ah proto\"

EOD;
				}
			}
		}
	}

	$ipfrules .= <<<EOD

anchor "limitingesr"

# IMSpector
anchor "imspector"

# uPnPd
anchor "miniupnpd"

EOD;

	return $ipfrules;
}

function filter_rules_spoofcheck_generate($ifname, $if, $sa, $sn, $log) 
{

	global $g, $config;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "filter_rules_spoofcheck_generate() being called $mt\n";
	}

	$ipfrules = "antispoof for {$if}\n";

	return $ipfrules;

}

function setup_logging_interfaces() 
{
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
		if(stristr(get_real_wan_interface($ifname), "tun") == true)
			continue;
		$int = get_real_wan_interface($ifname);
		$rules .= "set loginterface {$int}\n";
	}
	return $rules;
}

function process_carp_nat_rules() 
{
	global $g, $config;

	update_filter_reload_status("Creating CARP NAT rules");

	$wan_interface = get_real_wan_interface();

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "process_carp_nat_rules() being called $mt\n";
	}

	$lines = "";

	if (is_array($config['installedpackages']['carp']['config'])) {
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

function process_carp_rules() 
{
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

function remove_special_characters($string) 
{
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

function carp_sync_xml($url, $password, $sections, $port = 80, $method = 'pfsense.restore_config_section') 
{
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

function carp_sync_client() 
{

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

function return_vpn_subnet($adr) 
{
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