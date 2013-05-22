<?php
/* $Id$ */
/*
	services.inc
	part of the pfSense project (http://www.pfsense.com)

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2010	Ermal Luçi
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
	pfSense_BUILDER_BINARIES:	/usr/bin/killall	/bin/pgrep	/bin/sh	/usr/local/sbin/dhcpd	/usr/local/sbin/igmpproxy
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig	/usr/sbin/arp	/sbin/ifconfig	/usr/local/sbin/dnsmasq
	pfSense_BUILDER_BINARIES:	/usr/sbin/bsnmpd	/sbin/route	/usr/local/sbin/olsrd
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/miniupnpd	
	pfSense_MODULE:	utils
*/

function services_dhcpd_configure() {
	global $config, $g;
	
	if($g['services_dhcp_server_enable'] == false) 
		return;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpd_configure($if) being called $mt\n";
	}
	
	/* kill any running dhcpd */
	if(is_process_running("dhcpd"))
		mwexec("killall dhcpd", true);

	/* DHCP enabled on any interfaces? */
	if (!is_dhcp_server_enabled())
		return 0;

	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if($config['installedpackages']['olsrd'])
		foreach($config['installedpackages']['olsrd']['config'] as $olsrd)
				if($olsrd['enable'])
					$is_olsr_enabled = true;

	/* configure DHCPD chroot */
	$fd = fopen("{$g['tmp_path']}/dhcpd.sh","w");
	$status = `mount | grep "{$g['dhcpd_chroot_path']}/dev"`;
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/dev\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/etc\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/usr/local/sbin\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/var/db\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/var/run\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/usr\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/lib\n");
	fwrite($fd, "mkdir -p {$g['dhcpd_chroot_path']}/run\n");
	fwrite($fd, "chown -R dhcpd:_dhcp {$g['dhcpd_chroot_path']}/*\n");
	fwrite($fd, "cp /lib/libc.so.* {$g['dhcpd_chroot_path']}/lib/\n");
	fwrite($fd, "cp /usr/local/sbin/dhcpd {$g['dhcpd_chroot_path']}/usr/local/sbin/\n");
	fwrite($fd, "chmod a+rx {$g['dhcpd_chroot_path']}/usr/local/sbin/dhcpd\n");
	if(!trim($status))
		fwrite($fd, "mount -t devfs devfs {$g['dhcpd_chroot_path']}/dev\n");
	fclose($fd);
	mwexec("/bin/sh {$g['tmp_path']}/dhcpd.sh");

	if ($g['booting']) {
		if ($g['platform'] != "pfSense") {
			/* restore the leases, if we have them */
			if (file_exists("{$g['cf_conf_path']}/dhcpleases.tgz")) {
				$dhcprestore = "";
				$dhcpreturn = "";
				exec("cd /;LANG=C /usr/bin/tar -xzf {$g['cf_conf_path']}/dhcpleases.tgz 2>&1", $dhcprestore, $dhcpreturn);
				$dhcprestore = implode(" ", $dhcprestore);
				if($dhcpreturn <> 0) {
					log_error("DHCP leases restore failed exited with $dhcpreturn, the error is: $dhcprestore\n");
				}
			}
		}
	}

	$syscfg = $config['system'];
	$dhcpdcfg = $config['dhcpd'];
	$Iflist = get_configured_interface_list();
		
	if ($g['booting'])
		echo "Starting DHCP service...";
	else
		sleep(1);

	/* write dhcpd.conf */
	$fd = fopen("{$g['dhcpd_chroot_path']}/etc/dhcpd.conf", "w");
	if (!$fd) {
		printf("Error: cannot open dhcpd.conf in services_dhcpd_configure().\n");
		return 1;
	}

	$need_ddns_updates = false;
	$ddns_zones = array();

	$custoptions = "";
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {	
		if(is_array($dhcpifconf['numberoptions']) && is_array($dhcpifconf['numberoptions']['item'])) {
			foreach($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
				if(!empty($item['type']))
					$itemtype = $item['type'];
				else
					$itemtype = "text";
				$custoptions .= "option custom-{$dhcpif}-{$itemidx} code {$item['number']} = {$itemtype};\n";
			}
		}
	}

	$dhcpdconf = <<<EOD
	
option domain-name "{$syscfg['domain']}";
option ldap-server code 95 = text;
option domain-search-list code 119 = text;
{$custoptions}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
one-lease-per-client true;
deny duplicates;
ping-check true;

EOD;

	if(!isset($dhcpifconf['disableauthoritative']))
		$dhcpdconf .= "authoritative;\n";

	if(isset($dhcpifconf['alwaysbroadcast'])) 
		$dhcpdconf .= "always-broadcast on\n";

	$dhcpdifs = array();

	/*    loop through and determine if we need to setup
	 *    failover peer "bleh" entries
	 */
	$dhcpnum = 0;
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		interfaces_staticarp_configure($dhcpif);

		if (!isset($dhcpifconf['enable']))
			continue;

		if($dhcpifconf['failover_peerip'] <> "") {
			$int = guess_interface_from_ip($dhcpifconf['failover_peerip']);
			$intip = find_interface_ip($int);
			$real_dhcpif = convert_friendly_interface_to_real_interface_name($dhcpif);
			/*
			 *    yep, failover peer is defined.
			 *    does it match up to a defined vip?
			 */
			$skew = 110;
			$a_vip = &$config['virtualip']['vip'];
			if(is_array($a_vip)) {
				foreach ($a_vip as $vipent) {
					if($int == $real_dhcpif) {
						/* this is the interface! */
						if(is_numeric($vipent['advskew']) && ($vipent['advskew'] < "20"))
							$skew = 0;
					}
				}
			} else {
				log_error("Warning!  DHCP Failover setup and no CARP virtual IP's defined!");
			}
			if($skew > 10) {
				$type = "secondary";
				$dhcpdconf_pri  = "mclt 600;\n";
				$my_port = "520";
				$peer_port = "519";
			} else {
				$my_port = "519";
				$peer_port = "520";
				$type = "primary";
				$dhcpdconf_pri  = "split 128;\n";
				$dhcpdconf_pri .= "  mclt 600;\n";
			}
			$dhcpdconf .= <<<EOPP
failover peer "dhcp{$dhcpnum}" {
  {$type};
  address {$intip};
  port {$my_port};
  peer address {$dhcpifconf['failover_peerip']};
  peer port {$peer_port};
  max-response-delay 10;
  max-unacked-updates 10;
  {$dhcpdconf_pri}
  load balance max seconds 3;
}

EOPP;
		$dhcpnum++;
		}
	}

	$dhcpnum = 0;

	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		$newzone = array();
		$ifcfg = $config['interfaces'][$dhcpif];

		if (!isset($dhcpifconf['enable']) || !isset($Iflist[$dhcpif]))
			continue;
		$ifcfgip = get_interface_ip($dhcpif);
		$ifcfgsn = get_interface_subnet($dhcpif);
		$subnet = gen_subnet($ifcfgip, $ifcfgsn);
		$subnetmask = gen_subnet_mask($ifcfgsn);

		if (!is_ipaddr($subnet))
			continue;

		if($is_olsr_enabled == true)
			if($dhcpifconf['netmask'])
				$subnetmask = gen_subnet_mask($dhcpifconf['netmask']);

		$dnscfg = "";

		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
		}
		
		if($dhcpifconf['domainsearchlist'] <> "") {
			$dnscfg .= "	option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpifconf['domainsearchlist'])) . "\";\n";
		}

		if (isset($dhcpifconf['ddnsupdate'])) {
			$need_ddns_updates = true;
			$newzone = array();
			if($dhcpifconf['ddnsdomain'] <> "") {
				$newzone['domain-name'] = $dhcpifconf['ddnsdomain'];
				$dnscfg .= "	ddns-domainname \"{$dhcpifconf['ddnsdomain']}\";\n";
			} else {
				$newzone['domain-name'] = $config['system']['domain'];
			}
			$revsubnet = explode(".", $subnet);
			$revsubnet = array_reverse($revsubnet);
			foreach ($revsubnet as $octet) {
				if ($octet != "0")
					break;
				array_shift($revsubnet);
			}
			$newzone['ptr-domain'] = implode(".", $revsubnet) . ".in-addr.arpa";
		}

		if (is_array($dhcpifconf['dnsserver']) && ($dhcpifconf['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $dhcpifconf['dnsserver']) . ";";
			if ($newzone['domain-name'])
				$newzone['dns-servers'] = $dhcpifconf['dnsserver'];
		} else if (isset($config['dnsmasq']['enable'])) {
			$dnscfg .= "	option domain-name-servers {$ifcfgip};";
			if ($newzone['domain-name'] && is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0]))
				$newzone['dns-servers'] = $syscfg['dnsserver'];
		} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $syscfg['dnsserver']) . ";";
			if ($newzone['domain-name'])
				$newzone['dns-servers'] = $syscfg['dnsserver'];
		}

		$dhcpdconf .= "subnet {$subnet} netmask {$subnetmask} {\n";
		$dhcpdconf .= "	pool {\n";

		/* is failover dns setup? */
		if (is_array($dhcpifconf['dnsserver']) && $dhcpifconf['dnsserver'][0] <> "") {
			$dhcpdconf .= "		option domain-name-servers {$dhcpifconf['dnsserver'][0]}";
			if($dhcpifconf['dnsserver'][1] <> "")
				$dhcpdconf .= ",{$dhcpifconf['dnsserver'][1]}";
			$dhcpdconf .= ";\n";
		}

		if($dhcpifconf['failover_peerip'] <> "")
			$dhcpdconf .= "		deny dynamic bootp clients;\n";

		if (isset($dhcpifconf['denyunknown']))
		   $dhcpdconf .= "		deny unknown-clients;\n";

		if ($dhcpifconf['gateway'])
			$routers = $dhcpifconf['gateway'];
		else
			$routers = $ifcfgip;

		if($dhcpifconf['failover_peerip'] <> "") {
			$dhcpdconf .= "		failover peer \"dhcp{$dhcpnum}\";\n";
			$dhcpnum++;
		}

		$dhcpdconf .= <<<EOD
		range {$dhcpifconf['range']['from']} {$dhcpifconf['range']['to']};
	}
	option routers {$routers};
$dnscfg

EOD;
    
		// default-lease-time
		if ($dhcpifconf['defaultleasetime'])
			$dhcpdconf .= "	default-lease-time {$dhcpifconf['defaultleasetime']};\n";

		// max-lease-time
		if ($dhcpifconf['maxleasetime'])
			$dhcpdconf .= "	max-lease-time {$dhcpifconf['maxleasetime']};\n";

		// netbios-name*
		if (is_array($dhcpifconf['winsserver']) && $dhcpifconf['winsserver'][0]) {
			$dhcpdconf .= "	option netbios-name-servers " . join(",", $dhcpifconf['winsserver']) . ";\n";
			$dhcpdconf .= "	option netbios-node-type 8;\n";
		}

		// ntp-servers
		if (is_array($dhcpifconf['ntpserver']) && $dhcpifconf['ntpserver'][0])
			$dhcpdconf .= "	option ntp-servers " . join(",", $dhcpifconf['ntpserver']) . ";\n";

		// tftp-server-name
		if ($dhcpifconf['tftp'] <> "")
			$dhcpdconf .= "	option tftp-server-name \"{$dhcpifconf['tftp']}\";\n";

		// Handle option, number rowhelper values
		$dhcpdconf .= "\n";
		if($dhcpifconf['numberoptions']['item']) {
			foreach($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
				if(empty($item['type']) || $item['type'] == "text")
					$dhcpdconf .= "	option custom-{$dhcpif}-{$itemidx} \"{$item['value']}\";\n";
				else
					$dhcpdconf .= "	option custom-{$dhcpif}-{$itemidx} {$item['value']};\n";
			}
		}

		// ldap-server
		if ($dhcpifconf['ldap'] <> "")
			$dhcpdconf .= "	option ldap-server \"{$dhcpifconf['ldap']}\";\n";

		// net boot information
		if(isset($dhcpifconf['netboot'])) {
			if (($dhcpifconf['next-server'] <> "") && ($dhcpifconf['filename'] <> "")) {
				$dhcpdconf .= "	next-server {$dhcpifconf['next-server']};\n";
				$dhcpdconf .= "	filename \"{$dhcpifconf['filename']}\";\n";
			}
			if ($dhcpifconf['rootpath'] <> "") {
				$dhcpdconf .= "	option root-path \"{$dhcpifconf['rootpath']}\";\n";
      		}
		}
		
		$dhcpdconf .= <<<EOD
}

EOD;

		/* add static mappings */
		if (is_array($dhcpifconf['staticmap'])) {

			$i = 0;
			foreach ($dhcpifconf['staticmap'] as $sm) {
				$dhcpdconf .= <<<EOD
host s_{$dhcpif}_{$i} {
	hardware ethernet {$sm['mac']};

EOD;
				if ($sm['ipaddr'])
					$dhcpdconf .= "	fixed-address {$sm['ipaddr']};\n";

				if ($sm['hostname']) {
					$dhhostname = str_replace(" ", "_", $sm['hostname']);
					$dhhostname = str_replace(".", "_", $dhhostname);
					$dhcpdconf .= "	option host-name \"{$dhhostname}\";\n";
				}
				if ($sm['netbootfile'])
					$dhcpdconf .= "	filename \"{$sm['netbootfile']}\";\n";

				$dhcpdconf .= "}\n";
				$i++;
			}
		}

		$dhcpdifs[] = get_real_interface($dhcpif);
		if ($newzone['domain-name'])
			$ddns_zones[] = $newzone;
	}

	if ($need_ddns_updates) {
		$dhcpdconf .= "ddns-update-style interim;\n";
		if (is_array($ddns_zones)) {
			foreach ($ddns_zones as $zone) {
				if (!is_array($zone) || empty($zone) || !is_array($zone['dns-servers']))
					continue;
				$primary = $zone['dns-servers'][0];
				$secondary = empty($zone['dns-servers'][1]) ? "" : $zone['dns-servers'][1];
				$dhcpdconf .= "zone {$zone['domain-name']} {\n";
				$dhcpdconf .= "	primary {$primary};\n";
				if ($secondary)
					$dhcpdconf .= "	secondary {$secondary};\n";
				$dhcpdconf .= "}\n";
				$dhcpdconf .= "zone {$zone['ptr-domain']} {\n";
				$dhcpdconf .= "	primary {$primary};\n";
				if ($secondary)
					$dhcpdconf .= "	secondary {$secondary};\n";
				$dhcpdconf .= "}\n";
			}
		}
	}

	fwrite($fd, $dhcpdconf);
	fclose($fd);

	/* create an empty leases database */
	touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");
	touch("{$g['varrun_path']}/dhcpd.pid");
	

	/* fire up dhcpd in a chroot */
	mwexec("/usr/local/sbin/dhcpd -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpd.conf " .
		join(" ", $dhcpdifs));

	if ($g['booting']) {
		print "done.\n";
	}

	return 0;
}

function services_igmpproxy_configure() {
        global $config, $g;

        /* kill any running igmpproxy */
        killbyname("igmpproxy");

	if (!is_array($config['igmpproxy']['igmpentry']))
		return 1;

        $iflist = get_configured_interface_list();

        $igmpconf = <<<EOD

##------------------------------------------------------
## Enable Quickleave mode (Sends Leave instantly)
##------------------------------------------------------
quickleave

EOD;

        foreach ($config['igmpproxy']['igmpentry'] as $igmpcf) {
                unset($iflist[$igmpcf['ifname']]);
                $realif = get_real_interface($igmpcf['ifname']);
                if (empty($igmpcf['threshold']))
                        $threshld = 1;
                else
                        $threshld = $igmpcf['threshold'];
                $igmpconf .= "phyint {$realif} {$igmpcf['type']} ratelimit 0 threshold {$threshld}\n";

                if ($igmpcf['address'] <> "") {
                        $item = explode(" ", $igmpcf['address']);
                        foreach($item as $iww)
                                $igmpconf .= "altnet {$iww}\n";
                }
                $igmpconf .= "\n";
        }
        foreach ($iflist as $ifn) {
                $realif = get_real_interface($ifn);
                $igmpconf .= "phyint {$realif} disabled\n";
        }
	$igmpconf .= "\n";

        $igmpfl = fopen($g['tmp_path'] . "/igmpproxy.conf", "w");
        if (!$igmpfl) {
                log_error("Could not write Igmpproxy configuration file!");
                return;
        }
        fwrite($igmpfl, $igmpconf);
        fclose($igmpfl);

        mwexec("/usr/local/sbin/igmpproxy -c " . $g['tmp_path'] . "/igmpproxy.conf");
        log_error("Started IGMP proxy service.");

        return 0;
}

function services_dhcrelay_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcrelay_configure() being called $mt\n";
	}

	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay.pid");

	$dhcrelaycfg =& $config['dhcrelay'];

	/* DHCPRelay enabled on any interfaces? */
	if (!isset($dhcrelaycfg['enable']))
		return 0;

	if ($g['booting'])
		echo "Starting DHCP relay service...";
	else
		sleep(1);

	$iflist = get_configured_interface_list();

	$dhcifaces = explode(",", $dhcrelaycfg['interface']);
	foreach ($dhcifaces as $dhcrelayif) {
		if (!isset($iflist[$dhcrelayif]) ||
			link_interface_to_bridge($dhcrelayif))
			continue;

		if (is_ipaddr(get_interface_ip($dhcrelayif)))
			$dhcrelayifs[] = get_real_interface($dhcrelayif);
	}

	/* 
	 * In order for the relay to work, it needs to be active
	 * on the interface in which the destination server sits.
	 */
	$srvips = explode(",", $dhcrelaycfg['server']);
	foreach ($srvips as $srcidx => $srvip) {
		unset($destif);
		foreach ($iflist as $ifname) {
			$subnet = get_interface_ip($ifname);
			if (!is_ipaddr($subnet))
				continue;
			$subnet .=  "/" . get_interface_subnet($ifname);
			if (ip_in_subnet($srvip, $subnet)) {
				$destif = get_real_interface($ifname);
				break;
			}
		}
		if (!isset($destif)) {
			foreach (get_staticroutes() as $rtent) {
				if (ip_in_subnet($srvip, $rtent['network'])) {
					$a_gateways = return_gateways_array(true);
					$destif = $a_gateways[$rtent['gateway']]['interface'];
					break;
				}
			}
		}

		if (!isset($destif)) {
			/* Create a array from the existing route table */
        		exec("/usr/bin/netstat -rnWf inet", $route_str);
        		array_shift($route_str);
        		array_shift($route_str);
        		array_shift($route_str);
        		array_shift($route_str);
        		$route_arr = array();
        		foreach($route_str as $routeline) {
                		$items = preg_split("/[ ]+/i", $routeline);
				if (ip_in_subnet($srvip, $items[0])) {
					$destif = trim($items[6]);
					break;
				}
        		}
		}
	
		if (!isset($destif)) {
			if (is_array($config['gateways']['gateway_item'])) {
				foreach ($config['gateways']['gateway_item'] as $gateway) {
					if (isset($gateway['defaultgw'])) {
						$a_gateways = return_gateways_array(true);
                                        	$destif = $a_gateways[$rtent['gateway']]['interface'];
						break;
					}		
				}
			} else
				$destif = get_real_interface("wan");
		}

		if (!empty($destif))
			$dhcrelayifs[] = $destif;
	}
	$dhcrelayifs = array_unique($dhcrelayifs);

	/* fire up dhcrelay */
	if (empty($dhcrelayifs)) {
		log_error("No suitable interface found for running dhcrelay!");
		return; /* XXX */
	}

	$cmd = "/usr/local/sbin/dhcrelay -i " .  implode(" -i ", $dhcrelayifs);

	if (isset($dhcrelaycfg['agentoption']))
		$cmd .=  " -a -m replace";

	$cmd .= " " . implode(" ", $srvips);
	mwexec($cmd);

	return 0;
}

function services_dyndns_configure_client($conf) {

	if (!isset($conf['enable']))
		return;

	/* load up the dyndns.class */
	require_once("dyndns.class");

	$dns = new updatedns($dnsService = $conf['type'],
		$dnsHost = $conf['host'],
		$dnsUser = $conf['username'],
		$dnsPass = $conf['password'],
		$dnsWilcard = $conf['wildcard'],
		$dnsMX = $conf['mx'], 
		$dnsIf = "{$conf['interface']}");
}

function services_dyndns_configure($int = "") {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_configure() being called $mt\n";
	}

	$dyndnscfg = $config['dyndnses']['dyndns'];

	if (is_array($dyndnscfg)) {
		if ($g['booting']) 
			echo "Starting DynDNS clients...";

		foreach ($dyndnscfg as $dyndns) {
			if (!empty($int) && $int != $dyndns['interface'])
				continue;

			services_dyndns_configure_client($dyndns);

			sleep(1);
		}

		if ($g['booting'])
			echo "done.\n";
	}

	return 0;
}

function services_dnsmasq_configure() {
	global $config, $g;
	$return = 0;
	
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dnsmasq_configure() being called $mt\n";
	}

	/* kill any running dnsmasq */
	if (file_exists("{$g['varrun_path']}/dnsmasq.pid"))
		sigkillbypid("{$g['varrun_path']}/dnsmasq.pid", "TERM");

	if (isset($config['dnsmasq']['enable'])) {

		if ($g['booting'])
			echo "Starting DNS forwarder...";
		else
			sleep(1);

		/* generate hosts file */
		if(system_hosts_generate()!=0)
			$return = 1;

		$args = "";

		if (isset($config['dnsmasq']['regdhcp'])) {
			$args .= " --dhcp-hostsfile={$g['varetc_path']}/hosts ";
		}
		
		/* Setup forwarded domains */
		if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
			foreach($config['dnsmasq']['domainoverrides'] as $override) {
			        $args .= ' --server=/' . $override['domain'] . '/' . $override['ip'];
			}
		}

		/* Allow DNS Rebind for forwarded domains */
		if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
			if(!isset($config['system']['webgui']['nodnsrebindcheck'])) {
				foreach($config['dnsmasq']['domainoverrides'] as $override) {
					$args .= ' --rebind-domain-ok=/' . $override['domain'] . '/ ';
				}
			}
		}

		if(!isset($config['system']['webgui']['nodnsrebindcheck']))
			$dns_rebind = "--rebind-localhost-ok --stop-dns-rebind";

		if ($config['dnsmasq']['custom_options']) {
			foreach (preg_split('/\s+/', $config['dnsmasq']['custom_options']) as $c)
				$args .= " --$c";
		}

		/* run dnsmasq */
		mwexec_bg("/usr/local/sbin/dnsmasq --local-ttl 1 --all-servers {$dns_rebind} --dns-forward-max=5000 --cache-size=10000 {$args}");

		if ($g['booting'])
			echo "done.\n";
	}

	if (!$g['booting']) {
		if(services_dhcpd_configure()!=0)
			$return = 1;
	}

	return $return;
}

function services_snmpd_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_snmpd_configure() being called $mt\n";
	}

	/* kill any running snmpd */
	sigkillbypid("{$g['varrun_path']}/snmpd.pid", "TERM");
	sleep(2);
	if(is_process_running("bsnmpd")) 
		mwexec("/usr/bin/killall bsnmpd", true);

	if (isset($config['snmpd']['enable'])) {

		if ($g['booting'])
			echo "Starting SNMP daemon... ";

		/* generate snmpd.conf */
		$fd = fopen("{$g['varetc_path']}/snmpd.conf", "w");
		if (!$fd) {
			printf("Error: cannot open snmpd.conf in services_snmpd_configure().\n");
			return 1;
		}


		$snmpdconf = <<<EOD
location := "{$config['snmpd']['syslocation']}"
contact := "{$config['snmpd']['syscontact']}"
read := "{$config['snmpd']['rocommunity']}"

EOD;

/* No docs on what write strings do there for disable for now.
		if(isset($config['snmpd']['rwenable']) && preg_match('/^\S+$/', $config['snmpd']['rwcommunity'])){
		    $snmpdconf .= <<<EOD
# write string
write := "{$config['snmpd']['rwcommunity']}"

EOD;
		}
*/


		if(isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
		    $snmpdconf .= <<<EOD
# SNMP Trap support.
traphost := {$config['snmpd']['trapserver']}
trapport := {$config['snmpd']['trapserverport']}
trap := "{$config['snmpd']['trapstring']}"


EOD;
		}


		$snmpdconf .= <<<EOD
system := 1     # pfSense
%snmpd
begemotSnmpdDebugDumpPdus       = 2
begemotSnmpdDebugSyslogPri      = 7
begemotSnmpdCommunityString.0.1 = $(read)

EOD;

/* No docs on what write strings do there for disable for now.
		if(isset($config['snmpd']['rwcommunity']) && preg_match('/^\S+$/', $config['snmpd']['rwcommunity'])){
		    $snmpdconf .= <<<EOD
begemotSnmpdCommunityString.0.2 = $(write)

EOD;
		}
*/


		if(isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
		    $snmpdconf .= <<<EOD
begemotTrapSinkStatus.[$(traphost)].$(trapport) = 4
begemotTrapSinkVersion.[$(traphost)].$(trapport) = 2
begemotTrapSinkComm.[$(traphost)].$(trapport) = $(trap)

EOD;
		}


		$snmpdconf .= <<<EOD
begemotSnmpdCommunityDisable    = 1

EOD;

		if (isset($config['snmpd']['bindlan'])) {
			$config['snmpd']['bindip'] = 'lan';
			unset($config['snmpd']['bindlan']);
		}
		$bind_to_ip = "0.0.0.0";
		if(isset($config['snmpd']['bindip'])) {
			if (is_ipaddr($config['snmpd']['bindip'])) {
				$bind_to_ip = $config['snmpd']['bindip'];
			} else {
				$if = get_real_interface($config['snmpd']['bindip']);
				if (does_interface_exist($if))
					$bind_to_ip = find_interface_ip($if);
			}
		}

		if(is_port( $config['snmpd']['pollport'] )) {
		    $snmpdconf .= <<<EOD
begemotSnmpdPortStatus.{$bind_to_ip}.{$config['snmpd']['pollport']} = 1

EOD;

		}

		$snmpdconf .= <<<EOD
begemotSnmpdLocalPortStatus."/var/run/snmpd.sock" = 1
begemotSnmpdLocalPortType."/var/run/snmpd.sock" = 4

# These are bsnmp macros not php vars.
sysContact      = $(contact)
sysLocation     = $(location)
sysObjectId     = 1.3.6.1.4.1.12325.1.1.2.1.$(system)

snmpEnableAuthenTraps = 2

EOD;

		if (is_array( $config['snmpd']['modules'] )) {
		    if(isset($config['snmpd']['modules']['mibii'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."mibII"  = "/usr/lib/snmp_mibII.so"

EOD;
		    }

		    if(isset($config['snmpd']['modules']['netgraph'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."netgraph" = "/usr/lib/snmp_netgraph.so"
%netgraph
begemotNgControlNodeName = "snmpd"

EOD;
		    }

		    if(isset($config['snmpd']['modules']['pf'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."pf"     = "/usr/lib/snmp_pf.so"

EOD;
		    }

		    if(isset($config['snmpd']['modules']['hostres'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."hostres"     = "/usr/lib/snmp_hostres.so"

EOD;
		    }
		    if(isset($config['snmpd']['modules']['bridge'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."bridge"     = "/usr/lib/snmp_bridge.so"
# config must end with blank line


EOD;
		    }
		}

		fwrite($fd, $snmpdconf);
		fclose($fd);

		if (isset($config['snmpd']['bindlan'])) {
			$bindlan = "";
		}

		/* run bsnmpd */
		mwexec("/usr/sbin/bsnmpd -c {$g['varetc_path']}/snmpd.conf" .
			"{$bindlan} -p {$g['varrun_path']}/snmpd.pid");

		if ($g['booting'])
			echo "done.\n";
	}

	return 0;
}

function services_dnsupdate_process($int = "") {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dnsupdate_process() being called $mt\n";
	}

	/* Dynamic DNS updating active? */
	if (is_array($config['dnsupdates']['dnsupdate'])) {
		foreach ($config['dnsupdates']['dnsupdate'] as $i => $dnsupdate) {
			if (!isset($dnsupdate['enable']))
				continue;
			if (!empty($int) && $int != $dnsupdate['interface'])
				continue;

			/* determine interface name */
			$if = get_real_interface($dnsupdate['interface']);
			$wanip = get_interface_ip($dnsupdate['interface']);
			if ($wanip) {

				$keyname = $dnsupdate['keyname'];
				/* trailing dot */
				if (substr($keyname, -1) != ".")
					$keyname .= ".";

				$hostname = $dnsupdate['host'];
				/* trailing dot */
				if (substr($hostname, -1) != ".")
					$hostname .= ".";

				/* write private key file
				   this is dumb - public and private keys are the same for HMAC-MD5,
				   but nsupdate insists on having both */
				$fd = fopen("{$g['varetc_path']}/K{$i}{$keyname}+157+00000.private", "w");
				$privkey = <<<EOD
Private-key-format: v1.2
Algorithm: 157 (HMAC)
Key: {$dnsupdate['keydata']}

EOD;
				fwrite($fd, $privkey);
				fclose($fd);

				/* write public key file */
				if ($dnsupdate['keytype'] == "zone") {
					$flags = 257;
					$proto = 3;
				} else if ($dnsupdate['keytype'] == "host") {
					$flags = 513;
					$proto = 3;
				} else if ($dnsupdate['keytype'] == "user") {
					$flags = 0;
					$proto = 2;
				}

				$fd = fopen("{$g['varetc_path']}/K{$i}{$keyname}+157+00000.key", "w");
				fwrite($fd, "{$keyname} IN KEY {$flags} {$proto} 157 {$dnsupdate['keydata']}\n");
				fclose($fd);

				/* generate update instructions */
				$upinst = "";
				if (!empty($dnsupdate['server']))
					$upinst .= "server {$dnsupdate['server']}\n";
				$upinst .= "update delete {$dnsupdate['host']} A\n";
				$upinst .= "update add {$dnsupdate['host']} {$dnsupdate['ttl']} A {$wanip}\n";
				$upinst .= "\n";	/* mind that trailing newline! */

				$fd = fopen("{$g['varetc_path']}/nsupdatecmds{$i}", "w");
				fwrite($fd, $upinst);
				fclose($fd);

				/* invoke nsupdate */
				$cmd = "/usr/bin/nsupdate -k {$g['varetc_path']}/K{$i}{$keyname}+157+00000.key";
				if (isset($dnsupdate['usetcp']))
					$cmd .= " -v";
				$cmd .= " {$g['varetc_path']}/nsupdatecmds{$i}";
	
				mwexec_bg($cmd);
			}
		}
	}

	return 0;
}

function setup_wireless_olsr() {
	global $config, $g;
	if(!$config['installedpackages']['olsrd'] || !$config['installedpackages'])
		return;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "setup_wireless_olsr($interface) being called $mt\n";
	}
	conf_mount_rw();
	foreach($config['installedpackages']['olsrd']['config'] as $olsrd) {
		$olsr_enable = $olsrd['enable'];
		if($olsr_enable <> "on") {
			if (is_process_running("olsrd"))
				mwexec("/usr/bin/killall olsrd", true);
			return;
		}
		$fd = fopen("{$g['varetc_path']}/olsr.conf", "w");

		if($olsrd['announcedynamicroute'] or $olsrd['enableannounce'] == "on") {
			$enableannounce .= "\nHna4\n";
			$enableannounce .= "{\n";
		if($olsrd['announcedynamicroute'])
			$enableannounce .= "\t{$olsrd['announcedynamicroute']}\n";
		if($olsrd['enableannounce'] == "on")
			$enableannounce .= "0.0.0.0 0.0.0.0";
			$enableannounce .= "\n}\n";
		} else {
			$enableannounce = "";
		}

		$olsr .= <<<EODA
#
# olsr.org OLSR daemon config file
#
# Lines starting with a # are discarded
#
# This file was generated by setup_wireless_olsr() in services.inc
#

# This file is an example of a typical
# configuration for a mostly static
# network(regarding mobility) using
# the LQ extention

# Debug level(0-9)
# If set to 0 the daemon runs in the background

DebugLevel	2

# IP version to use (4 or 6)

IpVersion	4

# Clear the screen each time the internal state changes

ClearScreen     yes

{$enableannounce}

# Should olsrd keep on running even if there are
# no interfaces available? This is a good idea
# for a PCMCIA/USB hotswap environment.
# "yes" OR "no"

AllowNoInt	yes

# TOS(type of service) value for
# the IP header of control traffic.
# If not set it will default to 16

#TosValue	16

# The fixed willingness to use(0-7)
# If not set willingness will be calculated
# dynamically based on battery/power status
# if such information is available

#Willingness    	4

# Allow processes like the GUI front-end
# to connect to the daemon.

IpcConnect
{
     # Determines how many simultaneously
     # IPC connections that will be allowed
     # Setting this to 0 disables IPC

     MaxConnections  0

     # By default only 127.0.0.1 is allowed
     # to connect. Here allowed hosts can
     # be added

     Host            127.0.0.1
     #Host            10.0.0.5

     # You can also specify entire net-ranges
     # that are allowed to connect. Multiple
     # entries are allowed

     #Net             192.168.1.0 255.255.255.0
}

# Wether to use hysteresis or not
# Hysteresis adds more robustness to the
# link sensing but delays neighbor registration.
# Used by default. 'yes' or 'no'

UseHysteresis	no

# Hysteresis parameters
# Do not alter these unless you know
# what you are doing!
# Set to auto by default. Allowed
# values are floating point values
# in the interval 0,1
# THR_LOW must always be lower than
# THR_HIGH.

#HystScaling	0.50
#HystThrHigh	0.80
#HystThrLow	0.30


# Link quality level
# 0 = do not use link quality
# 1 = use link quality for MPR selection
# 2 = use link quality for MPR selection and routing
# Defaults to 0

LinkQualityLevel	{$olsrd['enablelqe']}

# Link quality window size
# Defaults to 10

LinkQualityWinSize	10

# Polling rate in seconds(float).
# Default value 0.05 sec

Pollrate	0.05


# TC redundancy
# Specifies how much neighbor info should
# be sent in TC messages
# Possible values are:
# 0 - only send MPR selectors
# 1 - send MPR selectors and MPRs
# 2 - send all neighbors
#
# defaults to 0

TcRedundancy	2

#
# MPR coverage
# Specifies how many MPRs a node should
# try select to reach every 2 hop neighbor
#
# Can be set to any integer >0
#
# defaults to 1

MprCoverage	3

# Example plugin entry with parameters:

EODA;

if($olsrd['enablehttpinfo'] == "on") {
	$olsr .= <<<EODB

LoadPlugin "/usr/local/lib/olsrd_httpinfo.so.0.1"
{
    PlParam     "port"   "{$olsrd['port']}"
    PlParam     "Net"    "{$olsrd['allowedhttpinfohost']} {$olsrd['allowedhttpinfosubnet']}"
}

EODB;

}

if($olsrd['enabledsecure'] == "on") {
	$olsr .= <<<EODC

LoadPlugin "/usr/local/lib/olsrd_secure.so.0.5"
{
    PlParam     "Keyfile"   "/usr/local/etc/olsrkey.txt"
}

EODC;

}

if($olsrd['enabledyngw'] == "on") {

	/* unset default route, olsr auto negotiates */
	mwexec("/sbin/route delete default");

	$olsr .= <<<EODE

LoadPlugin "/usr/local/lib/olsrd_dyn_gw.so.0.4"
{
    # how often to look for a inet gw, in seconds
    # defaults to 5 secs, if commented out
    PlParam     "Interval"   "{$olsrd['polling']}"

    # if one or more IPv4 addresses are given, do a ping on these in
    # descending order to validate that there is not only an entry in
    # routing table, but also a real internet connection. If any of
    # these addresses could be pinged successfully, the test was
    # succesful, i.e. if the ping on the 1st address was successful,the
    # 2nd won't be pinged
    PlParam     "Ping"       "{$olsrd['ping']}"
    #PlParam     "HNA"   "192.168.81.0 255.255.255.0"
}

EODE;

}

foreach($config['installedpackages']['olsrd']['config'] as $conf) {
	$interfaces = explode(',', $conf['iface_array']);
	foreach($interfaces as $interface) {
		$realinterface = convert_friendly_interface_to_real_interface_name($interface);
$olsr .= <<<EODAD
Interface "{$realinterface}"
{

    # Hello interval in seconds(float)
    HelloInterval    2.0

    # HELLO validity time
    HelloValidityTime	20.0

    # TC interval in seconds(float)
    TcInterval        5.0

    # TC validity time
    TcValidityTime	30.0

    # MID interval in seconds(float)
    MidInterval	5.0

    # MID validity time
    MidValidityTime	30.0

    # HNA interval in seconds(float)
    HnaInterval	5.0

    # HNA validity time
    HnaValidityTime 	30.0

    # When multiple links exist between hosts
    # the weight of interface is used to determine
    # the link to use. Normally the weight is
    # automatically calculated by olsrd based
    # on the characteristics of the interface,
    # but here you can specify a fixed value.
    # Olsrd will choose links with the lowest value.

    # Weight 0


}

EODAD;

	}
	break;
}
		fwrite($fd, $olsr);
		fclose($fd);
	}

	if (is_process_running("olsrd"))
		mwexec("/usr/bin/killall olsrd", true);

	sleep(2);

	mwexec_bg("/usr/local/sbin/olsrd -f {$g['varetc_path']}/olsr.conf");

	conf_mount_ro();
}

/* configure cron service */
function configure_cron() {
	global $g, $config;

	conf_mount_rw();
	/* preserve existing crontab entries */
	$crontab_contents = file("/etc/crontab", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	
	for ($i = 0; $i < count($crontab_contents); $i++) {
		$cron_item =& $crontab_contents[$i];
		if (strpos($cron_item, "# pfSense specific crontab entries") !== false) {
			array_splice($crontab_contents, $i - 1);
			break;
		}
	}
	$crontab_contents = implode("\n", $crontab_contents) . "\n";
	
	
	if (is_array($config['cron']['item'])) {
		$crontab_contents .= "#\n";
		$crontab_contents .= "# pfSense specific crontab entries\n";
		$crontab_contents .= "# Created: " . date("F j, Y, g:i a") . "\n";
		$crontab_contents .= "#\n";

		foreach ($config['cron']['item'] as $item) {
			$crontab_contents .= "\n{$item['minute']}\t";
			$crontab_contents .= "{$item['hour']}\t";
			$crontab_contents .= "{$item['mday']}\t";
			$crontab_contents .= "{$item['month']}\t";
			$crontab_contents .= "{$item['wday']}\t";
			$crontab_contents .= "{$item['who']}\t";
			$crontab_contents .= "{$item['command']}";
		}
    
		$crontab_contents .= "\n#\n";
		$crontab_contents .= "# If possible do not add items to this file manually.\n";
		$crontab_contents .= "# If you do so, this file must be terminated with a blank line (e.g. new line)\n";
		$crontab_contents .= "#\n\n";
	}
	
	/* please maintain the newline at the end of file */
	file_put_contents("/etc/crontab", $crontab_contents);

	/* do a HUP kill to force sync changes */
	exec('/bin/pkill -HUP cron');

	conf_mount_ro();
}

function upnp_action ($action) {
	global $g, $config;
	switch($action) {
		case "start":
			if (file_exists('/var/etc/miniupnpd.conf')) {
				@unlink("{$g['varrun_path']}/miniupnpd.pid");
				mwexec_bg("/usr/local/sbin/miniupnpd -f /var/etc/miniupnpd.conf -P {$g['varrun_path']}/miniupnpd.pid");
			}
			break;
		case "stop":
			killbypid("{$g['varrun_path']}/miniupnpd.pid");
			while((int)exec("/bin/pgrep -a miniupnpd | wc -l") > 0)
				mwexec('killall miniupnpd 2>/dev/null', true);
			mwexec('/sbin/pfctl -aminiupnpd -Fr 2>&1 >/dev/null');
			mwexec('/sbin/pfctl -aminiupnpd -Fn 2>&1 >/dev/null');
			break;
		case "restart":
			upnp_action('stop');
			upnp_action('start');
			break;
	}
}

function upnp_start() {
	global $config;

	if(!isset($config['installedpackages']['miniupnpd']['config']))
		return;

	if($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
		echo "Starting UPnP service... ";
		require_once('/usr/local/pkg/miniupnpd.inc');
		sync_package_miniupnpd();
		echo "done.\n";
	}
}

function install_cron_job($command, $active=false, $minute="0", $hour="*", $monthday="*", $month="*", $weekday="*", $who="root") {
	global $config, $g;

	$is_installed = false;

	if(!$config['cron']['item'])
		return;

	$x=0;
	foreach($config['cron']['item'] as $item) {
		if(strstr($item['command'], $command)) {
			$is_installed = true;
			break;
		}
		$x++;
	}

	if($active) {
		$cron_item = array();
		$cron_item['minute'] = $minute;
		$cron_item['hour'] = $hour;
		$cron_item['mday'] = $monthday;
		$cron_item['month'] = $month;
		$cron_item['wday'] = $weekday;
		$cron_item['who'] = $who;
		$cron_item['command'] = $command;
		if(!$is_installed) {
			$config['cron']['item'][] = $cron_item;
			write_config("Installed cron job for {$command}");
		} else {
			$config['cron']['item'][$x] = $cron_item;
			write_config("Updated cron job for {$command}");
		}
	} else {
		if(($is_installed == true) && ($x > 0)) {
			unset($config['cron']['item'][$x]);
			write_config("Remvoed cron job for {$command}");
		}
	}
	configure_cron();
}

?>
