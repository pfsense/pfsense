<?php
/* $Id$ */
/*
	services.inc
	part of the pfSense project (http://www.pfsense.com)

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2010	Ermal Luci
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
	pfSense_BUILDER_BINARIES:	/usr/sbin/bsnmpd	/sbin/route
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/miniupnpd	/usr/sbin/radvd		/usr/local/sbin/unbound
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/dhcleases6
	pfSense_MODULE:	utils
*/

/* implement ipv6 route advertising deamon */
function services_radvd_configure() {
	global $config, $g;
	
	if ($g['platform'] == 'jail') 
		return;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_radvd_configure() being called $mt\n";
	}

	if (!is_array($config['dhcpdv6']))
		$config['dhcpdv6'] = array();

	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();

	$radvdconf = "# Automatically Generated, do not edit\n";

	/* Process all links which need the router advertise daemon */
	$radvdnum = 0;
	$radvdifs = array();

	/* handle manually configured DHCP6 server settings first */
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if(!isset($config['interfaces'][$dhcpv6if]['enable']))
			continue;

		if(!isset($dhcpv6ifconf['ramode']))
			$dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];

		/* are router advertisements enabled? */
		if($dhcpv6ifconf['ramode'] == "disabled")
			continue;

		if(!isset($dhcpv6ifconf['rapriority']))
			$dhcpv6ifconf['rapriority'] = "medium";

		/* always start with the real parent, we override with the carp if later */
		$realif = get_real_interface($dhcpv6if);
		$carpif = false;
		/* check if we need to listen on a CARP interface */
		$carplist = get_configured_carp_interface_list();
		if($dhcpv6ifconf['rainterface'] <> "") {
			if($carplist[$dhcpv6ifconf['rainterface']] <> "") {
				$realif = $dhcpv6ifconf['rainterface'];
				$carpif = true;
			}
		}

		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		if(in_array($realif, $radvdifs))
			continue;

		$ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		$subnetmaskv6 = gen_subnet_mask($ifcfgsnv6);

		$radvdifs[] = $realif;
		$radvdifs[] = get_real_interface($realif);
		
		$radvdconf .= "# Generated for DHCPv6 Server $dhcpv6if\n";
		$radvdconf .= "interface {$realif} {\n";
		$radvdconf .= "\tAdvSendAdvert on;\n";
		$radvdconf .= "\tMinRtrAdvInterval 5;\n";
		$radvdconf .= "\tMaxRtrAdvInterval 20;\n";
		$radvdconf .= "\tAdvLinkMTU 1280;\n";
		// $radvdconf .= "\tDeprecatePrefix on;\n";
		switch($dhcpv6ifconf['rapriority']) {
			case "low":
				$radvdconf .= "\tAdvDefaultPreference low;\n";
				break;
			case "high":
				$radvdconf .= "\tAdvDefaultPreference high;\n";
				break;
			default:
				$radvdconf .= "\tAdvDefaultPreference medium;\n";
				break;
		}
		switch($dhcpv6ifconf['ramode']) {
			case "managed":
				$radvdconf .= "\tAdvManagedFlag on;\n";
				break;
			case "assist":
				$radvdconf .= "\tAdvOtherConfigFlag on;\n";
				break;
		}
		$radvdconf .= "\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
		if($carpif == true) {
			$radvdconf .= "\t\tDeprecatePrefix off;\n";
		} else {
			$radvdconf .= "\t\tDeprecatePrefix on;\n";
		}
		switch($dhcpv6ifconf['ramode']) {
			case "managed":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous off;\n";
				$radvdconf .= "\t\tAdvRouterAddr on;\n";
				break;
			case "router":
				$radvdconf .= "\t\tAdvOnLink off;\n";
				$radvdconf .= "\t\tAdvAutonomous off;\n";
				$radvdconf .= "\t\tAdvRouterAddr on;\n";
				break;
			case "assist":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous on;\n";
				$radvdconf .= "\t\tAdvRouterAddr on;\n";
				break;
			case "unmanaged":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous on;\n";
				$radvdconf .= "\t\tAdvRouterAddr on;\n";
				break;				
		}
		$radvdconf .= "\t};\n";

		if($carpif == true) {
			$radvdconf .= "\troute ::/0 {\n";
			$radvdconf .= "\t\tRemoveRoute off;\n";
			$radvdconf .= "\t};\n";
		} else {
			$radvdconf .= "\troute ::/0 {\n";
			$radvdconf .= "\t\tRemoveRoute on;\n";
			$radvdconf .= "\t};\n";
		}

		/* add DNS servers */
		$dnslist = array();
		if(!empty($dhcpv6ifconf['dnsserver'][0])) {
			foreach($dhcpv6ifconf['dnsserver'] as $server) {
				if(is_ipaddrv6($server))
					$dnslist[] = $server;
			}
		} elseif (isset($config['dnsmasq']['enable'])) {
			$dnslist[] = get_interface_ipv6($realif);
		} elseif (!empty($config['system']['dnsserver'][0])) {
			foreach($config['system']['dnsserver'] as $server) {
				if(is_ipaddrv6($server))
					$dnslist[] = $server;
			}
		}
		if(count($dnslist) > 0) {
			$dnsstring = implode(" ", $dnslist);
			if($dnsstring <> "")
				$radvdconf .= "\tRDNSS {$dnsstring} { };\n";
		}
		if($dhcpv6ifconf['domain'] <> "") {
			$radvdconf .= "\tDNSSL {$dhcpv6ifconf['domain']} { };\n";
		} elseif ($config['system']['domain'] <> "") {
			$radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
		}
		$radvdconf .= "};\n";
		$radvdnum++;
	}

	/* handle DHCP-PD prefixes and 6RD dynamic interfaces */
	foreach ($Iflist as $if => $ifdescr) {
		if(!isset($config['interfaces'][$if]['track6-interface']))
			continue;
		if(!isset($config['interfaces'][$if]['enable']))
			continue;
			
		$realif = get_real_interface($if);
		/* prevent duplicate entries, manual overrides */
		if(in_array($realif, $radvdifs))
			continue;

		$ifcfgipv6 = get_interface_ipv6($if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		$ifcfgsnv6 = get_interface_subnetv6($if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		$subnetmaskv6 = gen_subnet_mask($ifcfgsnv6);
		$trackif = $config['interfaces'][$if]['track6-interface'];
		$radvdifs[] = $realif;

		$autotype = $config['interfaces'][$trackif]['ipaddrv6'];
	
		log_error("configuring RA on {$if} for type {$autotype} radvd subnet {$subnetv6}/{$ifcfgsnv6}");

		$dnslist = array();
		if(is_ipaddrv6($subnetv6)) {
			$radvdconf .= "# Generated config for {$autotype} delegation from {$trackif} on {$if}\n";
			$radvdconf .= "interface {$realif} {\n";
				$radvdconf .= "\tAdvSendAdvert on;\n";
				$radvdconf .= "\tMinRtrAdvInterval 3;\n";
				$radvdconf .= "\tMaxRtrAdvInterval 10;\n";
				$radvdconf .= "\tAdvLinkMTU 1280;\n";
				$radvdconf .= "\tAdvOtherConfigFlag on;\n";
					$radvdconf .= "\t\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
					$radvdconf .= "\t\tAdvOnLink on;\n";
					$radvdconf .= "\t\tAdvAutonomous on;\n";
					$radvdconf .= "\t\tAdvRouterAddr on;\n";
				$radvdconf .= "\t};\n";

				/* add DNS servers */
				$dnslist = array();
				if (isset($config['dnsmasq']['enable'])) {
						$dnslist[] = $ifcfgipv6;
				} elseif (!empty($config['system']['dnsserver'][0])) {
					foreach($config['system']['dnsserver'] as $server) {
						if(is_ipaddrv6($server))
							$dnslist[] = $server;
					}
				}
				if(count($dnslist) > 0) {
					$dnsstring = implode(" ", $dnslist);
					if($dnsstring <> "")
						$radvdconf .= "\tRDNSS {$dnsstring} { };\n";
				}
				if ($config['system']['domain'] <> "") {
					$radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
				}
			$radvdconf .= "};\n";
			$radvdnum++;
		}
	}

	/* write radvd.conf */
	if (!@file_put_contents("{$g['varetc_path']}/radvd.conf", $radvdconf))
		printf("Error: cannot open radvd.conf in services_radvd_configure().\n");
	unset($radvdconf);

	if(count($radvdifs) > 0) {
		if(is_process_running("radvd")) {
			mwexec("killall -1 radvd");
		} else {
			mwexec("/usr/local/sbin/radvd -C {$g['varetc_path']}/radvd.conf -m syslog");
		}
	} else {
		/* we need to shut down the radvd cleanly, it will send out the prefix
		 * information with a lifetime of 0 to notify clients of a (possible) new prefix */
		if(is_process_running("radvd")) {
			log_error("Shutting down Router Advertisment daemon cleanly");
			mwexec("killall radvd");
		}
	}
	return 0;
}

function services_dhcpd_configure() {
	global $config, $g;

	/* configure DHCPD chroot once */
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

	services_dhcpdv4_configure();
	services_dhcpdv6_configure();
	services_radvd_configure();
	return;

}
function services_dhcpdv4_configure() {
	global $config, $g;

	if($g['services_dhcp_server_enable'] == false)
		return;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpdv4_configure($if) being called $mt\n";
	}

	/* kill any running dhcpd */
	if(is_process_running("dhcpd")) {
		killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpd.pid");
	}

	/* DHCP enabled on any interfaces? */
	if (!is_dhcp_server_enabled())
		return 0;

	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if($config['installedpackages']['olsrd'])
		foreach($config['installedpackages']['olsrd']['config'] as $olsrd)
				if($olsrd['enable'])
					$is_olsr_enabled = true;

	if ($g['booting']) {
		if ($g['platform'] != "pfSense") {
			/* restore the leases, if we have them */
			if (file_exists("{$g['cf_conf_path']}/dhcpleases.tgz")) {
				$dhcprestore = "";
				$dhcpreturn = "";
				exec("cd /;LANG=C /usr/bin/tar -xzf {$g['cf_conf_path']}/dhcpleases.tgz 2>&1", $dhcprestore, $dhcpreturn);
				$dhcprestore = implode(" ", $dhcprestore);
				if($dhcpreturn <> 0) {
					log_error(sprintf(gettext('DHCP leases restore failed exited with %1$s, the error is: %2$s%3$s'), $dhcpreturn, $dhcprestore, "\n"));
				}
			}
		}
	}

	$syscfg = $config['system'];
	if (!is_array($config['dhcpd']))
		$config['dhcpd'] = array();
	$dhcpdcfg = $config['dhcpd'];
	$Iflist = get_configured_interface_list();

	if ($g['booting'])
		echo gettext("Starting DHCP service...");
	else
		sleep(1);

	/* write dhcpd.conf */
	$fd = fopen("{$g['dhcpd_chroot_path']}/etc/dhcpd.conf", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open dhcpd.conf in services_dhcpdv4_configure().%s"), "\n");
		return 1;
	}

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
ddns-update-style none;
one-lease-per-client true;
deny duplicates;
ping-check true;

EOD;

	if(!isset($dhcpifconf['disableauthoritative']))
		$dhcpdconf .= "authoritative;\n";

	if(isset($dhcpifconf['alwaysbroadcast']))
		$dhcpdconf .= "always-broadcast on\n";

	$dhcpdifs = array();
	$add_routers = false;
	$gateways_arr = return_gateways_array();
	/* only add a routers line if the system has any IPv4 gateway at all */
	/* a static route has a gateway, manually overriding this field always works */
	foreach($gateways_arr as $gwitem) {
		if($gwitem['ipprotocol'] == "inet") {
			$add_routers = true;
			break;
		}
	}

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
				log_error(gettext("Warning!  DHCP Failover setup and no CARP virtual IP's defined!"));
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

		$all_pools = array();
		$all_pools[] = $dhcpifconf;
		if (is_array($dhcpifconf['pool'])) {
			$all_pools = array_merge($all_pools, $dhcpifconf['pool']);
		}

		$dnscfg = "";

		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
		}

		if($dhcpifconf['domainsearchlist'] <> "") {
			$dnscfg .= "	option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpifconf['domainsearchlist'])) . "\";\n";
		}

		if (isset($dhcpifconf['ddnsupdate'])) {
			if($dhcpifconf['ddnsdomain'] <> "") {
				$dnscfg .= "	ddns-domainname \"{$dhcpifconf['ddnsdomain']}\";\n";
			}
			$dnscfg .= "	ddns-update-style interim;\n";
		}

		if (is_array($dhcpifconf['dnsserver']) && ($dhcpifconf['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $dhcpifconf['dnsserver']) . ";";
		} else if (isset($config['dnsmasq']['enable'])) {
			$dnscfg .= "	option domain-name-servers {$ifcfgip};";
		} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $syscfg['dnsserver']) . ";";
		}

		/* Create classes - These all contain comma separated lists. Join them into one 
		   big comma separated string then split them all up. */
		$all_mac_strings = array();
		if (is_array($dhcpifconf['pool'])) {
			foreach($all_pools as $poolconf) {
				$all_mac_strings[] = $poolconf['mac_allow'];
				$all_mac_strings[] = $poolconf['mac_deny'];
			}
		}
		$all_mac_strings[] = $dhcpifconf['mac_allow'];
		$all_mac_strings[] = $dhcpifconf['mac_deny'];
		$all_mac_list = array_unique(explode(',', implode(',', $all_mac_strings)));
		foreach ($all_mac_list as $mac) {
			if (empty($mac))
				continue;
			$dhcpdconf .= 'class "' . str_replace(':', '', $mac) . '" {' . "\n";
			// Skip the first octet of the MAC address - for media type, typically Ethernet ("01") and match the rest.
			$dhcpdconf .= '	match if substring (hardware, 1, ' . (substr_count($mac, ':') + 1) . ') = ' . $mac . ';' . "\n";
			$dhcpdconf .= '}' . "\n";
		}

		$dhcpdconf .= "subnet {$subnet} netmask {$subnetmask} {\n";

// Setup pool options
		foreach($all_pools as $poolconf) {
			$dhcpdconf .= "	pool {\n";
			/* is failover dns setup? */
			if (is_array($poolconf['dnsserver']) && $poolconf['dnsserver'][0] <> "") {
				$dhcpdconf .= "		option domain-name-servers {$poolconf['dnsserver'][0]}";
				if($poolconf['dnsserver'][1] <> "")
					$dhcpdconf .= ",{$poolconf['dnsserver'][1]}";
				$dhcpdconf .= ";\n";
			}

			/* allow/deny MACs */
			$mac_allow_list = array_unique(explode(',', $poolconf['mac_allow']));
			foreach ($mac_allow_list as $mac) {
				if (empty($mac))
					continue;
				$dhcpdconf .= "		allow members of \"" . str_replace(':', '', $mac) . "\";\n";
			}
			$mac_deny_list = array_unique(explode(',', $poolconf['mac_deny']));
			foreach ($mac_deny_list as $mac) {
				if (empty($mac))
					continue;
				$dhcpdconf .= "		deny members of \"" . str_replace(':', '', $mac) . "\";\n";
			}

			if($poolconf['failover_peerip'] <> "")
				$dhcpdconf .= "		deny dynamic bootp clients;\n";

			if (isset($poolconf['denyunknown']))
			   $dhcpdconf .= "		deny unknown-clients;\n";

			if ($poolconf['gateway'] && ($poolconf['gateway'] != $dhcpifconf['gateway']))
				$dhcpdconf .= "		option routers {$poolconf['gateway']};\n";

			if($dhcpifconf['failover_peerip'] <> "") {
				$dhcpdconf .= "		failover peer \"dhcp{$dhcpnum}\";\n";
				$dhcpnum++;
			}

			$pdnscfg = "";

			if ($poolconf['domain'] && ($poolconf['domain'] != $dhcpifconf['domain'])) {
				$pdnscfg .= "		option domain-name \"{$poolconf['domain']}\";\n";
			}

			if(!empty($poolconf['domainsearchlist']) && ($poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
				$pdnscfg .= "		option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $poolconf['domainsearchlist'])) . "\";\n";
			}

			if (isset($poolconf['ddnsupdate']) && ($poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
				if($poolconf['ddnsdomain'] <> "") {
					$pdnscfg .= "		ddns-domainname \"{$poolconf['ddnsdomain']}\";\n";
				}
				$pdnscfg .= "		ddns-update-style interim;\n";
			}

			if (is_array($poolconf['dnsserver']) && ($poolconf['dnsserver'][0]) && ($poolconf['dnsserver'][0] != $poolconf['dnsserver'][0])) {
				$pdnscfg .= "		option domain-name-servers " . join(",", $poolconf['dnsserver']) . ";\n";
			}
			$dhcpdconf .= "{$pdnscfg}";

			// default-lease-time
			if ($poolconf['defaultleasetime'] && ($poolconf['defaultleasetime'] != $dhcpifconf['defaultleasetime']))
				$dhcpdconf .= "		default-lease-time {$poolconf['defaultleasetime']};\n";

			// max-lease-time
			if ($poolconf['maxleasetime'] && ($poolconf['maxleasetime'] != $dhcpifconf['maxleasetime']))
				$dhcpdconf .= "		max-lease-time {$poolconf['maxleasetime']};\n";

			// netbios-name*
			if (is_array($poolconf['winsserver']) && $poolconf['winsserver'][0] && ($poolconf['winsserver'][0] != $poolconf['winsserver'][0])) {
				$dhcpdconf .= "		option netbios-name-servers " . join(",", $poolconf['winsserver']) . ";\n";
				$dhcpdconf .= "		option netbios-node-type 8;\n";
			}

			// ntp-servers
			if (is_array($poolconf['ntpserver']) && $poolconf['ntpserver'][0] && ($poolconf['ntpserver'][0] != $poolconf['ntpserver'][0]))
				$dhcpdconf .= "		option ntp-servers " . join(",", $poolconf['ntpserver']) . ";\n";

			// tftp-server-name
			if (!empty($poolconf['tftp']) && ($poolconf['tftp'] != $dhcpifconf['tftp']))
				$dhcpdconf .= "		option tftp-server-name \"{$poolconf['tftp']}\";\n";

			// ldap-server
			if (!empty($poolconf['ldap']) && ($poolconf['ldap'] != $dhcpifconf['ldap']))
				$dhcpdconf .= "		option ldap-server \"{$poolconf['ldap']}\";\n";

			// net boot information
			if(isset($poolconf['netboot'])) {
				if (!empty($poolconf['nextserver']) && ($poolconf['nextserver'] != $dhcpifconf['nextserver'])) {
					$dhcpdconf .= "		next-server {$poolconf['nextserver']};\n";
				}
				if (!empty($poolconf['filename']) && ($poolconf['filename'] != $dhcpifconf['filename'])) {
					$dhcpdconf .= "		filename \"{$poolconf['filename']}\";\n";
				}
				if (!empty($poolconf['rootpath']) && ($poolconf['rootpath'] != $dhcpifconf['rootpath'])) {
					$dhcpdconf .= "		option root-path \"{$poolconf['rootpath']}\";\n";
				}
			}
			$dhcpdconf .= "		range {$poolconf['range']['from']} {$poolconf['range']['to']};\n";
			$dhcpdconf .= "	}\n\n";
		}
// End of settings inside pools

		if ($dhcpifconf['gateway']) {
			$routers = $dhcpifconf['gateway'];
			$add_routers = true;
		} else {
			$routers = $ifcfgip;
		}
		if($add_routers)
			$dhcpdconf .= "	option routers {$routers};\n";

		$dhcpdconf .= <<<EOD
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
			if ($dhcpifconf['nextserver'] <> "") {
				$dhcpdconf .= "	next-server {$dhcpifconf['nextserver']};\n";
			}
			if ($dhcpifconf['filename'] <> "") {
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
				if ($sm['filename'])
					$dhcpdconf .= "	filename \"{$sm['filename']}\";\n";

				if ($sm['rootpath'])
					$dhcpdconf .= "	option root-path \"{$sm['rootpath']}\";\n";

				$dhcpdconf .= "}\n";
				$i++;
			}
		}

		$dhcpdifs[] = get_real_interface($dhcpif);
	}

	fwrite($fd, $dhcpdconf);
	fclose($fd);
	unset($dhcpdconf);

	/* create an empty leases database */
	touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");


	/* fire up dhcpd in a chroot */
	if(count($dhcpdifs) > 0) {
		mwexec("/usr/local/sbin/dhcpd -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpd.conf -pf {$g['varrun_path']}/dhcpd.pid " .
			join(" ", $dhcpdifs));
	}

	if ($g['booting']) {
		print "done.\n";
	}

	return 0;
}

function services_dhcpdv6_configure() {
	global $config, $g;

	if($g['services_dhcp_server_enable'] == false)
		return;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpd_configure($if) being called $mt\n";
	}

	/* kill any running dhcpd */
	if(is_process_running("dhcpd")) {
		killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid");
		killbypid("{$g['varrun_path']}/dhcpleases6.pid");
	}

	/* DHCP enabled on any interfaces? */
	if (!is_dhcpv6_server_enabled())
		return 0;

	if ($g['booting']) {
		if ($g['platform'] != "pfSense") {
			/* restore the leases, if we have them */
			if (file_exists("{$g['cf_conf_path']}/dhcp6leases.tgz")) {
				$dhcprestore = "";
				$dhcpreturn = "";
				exec("cd /;LANG=C /usr/bin/tar -xzf {$g['cf_conf_path']}/dhcp6leases.tgz 2>&1", $dhcprestore, $dhcpreturn);
				$dhcprestore = implode(" ", $dhcprestore);
				if($dhcpreturn <> 0) {
					log_error("DHCP leases v6 restore failed exited with $dhcpreturn, the error is: $dhcprestore\n");
				}
			}
		}
	}

	$syscfg = $config['system'];
	if (!is_array($config['dhcpdv6']))
		$config['dhcpdv6'] = array();
	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();

	if ($g['booting'])
		echo "Starting DHCPv6 service...";
	else
		sleep(1);

	/* we add a fake entry for interfaces that are set to track6 another WAN */
	foreach($Iflist as $ifname) {
		$realif = get_real_interface($ifname);
		$ifcfgipv6 = find_interface_ipv6($realif);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;
		$ifcfgipv6 = Net_IPv6::getNetmask($ifcfgipv6, 64);
		if($config['interfaces'][$ifname]['track6-interface'] <> "") {
			$trackifname = $config['interfaces'][$ifname]['track6-interface'];
			$trackcfg = $config['interfaces'][$trackifname];
			$pdlen = calculate_ipv6_delegation_length($trackifname);
			$ifcfgipv6arr =explode(":", $ifcfgipv6);
			$dhcpdv6cfg[$ifname] = array();
			$dhcpdv6cfg[$ifname]['enable'] = true;
			/* range */
			$ifcfgipv6arr[7] = "1000";
			$dhcpdv6cfg[$ifname]['range']['from'] = Net_IPv6::compress(implode(":", $ifcfgipv6arr));
			$ifcfgipv6arr[7] = "2000";
			$dhcpdv6cfg[$ifname]['range']['to'] = Net_IPv6::compress(implode(":", $ifcfgipv6arr));;
			/* prefix length > 0? We can add dhcp6 prefix delegation server */
			if($pdlen > 2) {
				$pdlenmax = $pdlen;
				$pdlenhalf = $pdlenmax -1;
				$pdlenmin = (64 - ceil($pdlenhalf / 4));
				$dhcpdv6cfg[$ifname]['prefixrange']['prefixlength'] = $pdlenmin;

				/* set the delegation start to half the current address block */
				$range = Net_IPv6::parseAddress($ifcfgipv6, (64 - $pdlenmax));
				$range['start'] = Net_IPv6::getNetmask($range['end'], (64 - $pdlenhalf));

				/* set the end range to a multiple of the prefix delegation size, required by dhcpd */
				$range = Net_IPv6::parseAddress($range['end'], (64 - $pdlenhalf));
				$range['end'] = Net_IPv6::getNetmask($range['end'], (64 - round($pdlen / 2)));

				$dhcpdv6cfg[$ifname]['prefixrange']['from'] = Net_IPv6::compress($range['start']);
				$dhcpdv6cfg[$ifname]['prefixrange']['to'] = Net_IPv6::compress($range['end']);
			}
		}
	}

	/* write dhcpdv6.conf */
	$fdv6 = fopen("{$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf", "w");
	if (! $fdv6) {
		printf("Error: cannot open dhcpdv6.conf in services_dhcpdv6_configure().\n");
		return 1;
	}

	$custoptionsv6 = "";
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if(is_array($dhcpv6ifconf['numberoptions']) && is_array($dhcpv6ifconf['numberoptions']['item'])) {
			foreach($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
				$custoptionsv6 .= "option custom-{$dhcpv6if}-{$itemv6idx} code {$itemv6['number']} = text;\n";
			}
		}
	}

	$dhcpdv6conf = <<<EOD

option domain-name "{$syscfg['domain']}";
option ldap-server code 95 = text;
option domain-search-list code 119 = text;
{$custoptions}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
ddns-update-style none;
one-lease-per-client true;
deny duplicates;
ping-check true;

EOD;

	if(!isset($dhcpv6ifconf['disableauthoritative']))
		$dhcpdv6conf .= "authoritative;\n";

	if(isset($dhcpv6ifconf['alwaysbroadcast']))
		$dhcpdv6conf .= "always-broadcast on\n";

	$dhcpdv6ifs = array();

	/*    loop through and determine if we need to setup
	 *    failover peer "bleh" entries
	 */
	$dhcpv6num = 0;
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {

		if (!isset($dhcpv6ifconf['enable']))
			continue;

		if($dhcpv6ifconf['failover_peerip'] <> "") {
			$intv6 = guess_interface_from_ip($dhcpv6ifconf['failover_peerip']);
			$intipv6 = find_interface_ipv6($intv6);
			$real_dhcpv6if = convert_friendly_interface_to_real_interface_name($dhcpv6if);
			/*
			 *    yep, failover peer is defined.
			 *    does it match up to a defined vip?
			 */
			$skew = 110;
			$a_vip = &$config['virtualip']['vip'];
			if(is_array($a_vip)) {
				foreach ($a_vip as $vipent) {
					if($intv6 == $real_dhcpv6if) {
						/* this is the interface! */
						if(is_numeric($vipent['advskew']) && ($vipent['advskew'] < "20"))
							$skew = 0;
					}
				}
			} else {
				log_error("Warning!  DHCPv6 Failover setup and no CARP virtual IPv6's defined!");
			}
			if($skew > 10) {
				$typev6 = "secondary";
				$dhcpdv6conf_pri  = "mclt 600;\n";
				$my_portv6 = "520";
				$peer_portv6 = "519";
			} else {
				$my_portv6 = "519";
				$peer_portv6 = "520";
				$typev6 = "primary";
				$dhcpdv6conf_pri  = "split 128;\n";
				$dhcpdv6conf_pri .= "  mclt 600;\n";
			}
			$dhcpdv6conf .= <<<EOPP
failover peer "dhcpv6{$dhcpv6num}" {
  {$typev6};
  address {$intipv6};
  port {$my_portv6};
  peer address {$dhcpv6ifconf['failover_peerip']};
  peer port {$peer_portv6};
  max-response-delay 10;
  max-unacked-updates 10;
  {$dhcpdv6conf_pri}
  load balance max seconds 3;
}

EOPP;
		$dhcpv6num++;
		}
	}

	$dhcpv6num = 0;
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {

		$ifcfgv6 = $config['interfaces'][$dhcpv6if];

		if (!isset($dhcpv6ifconf['enable']) || !isset($Iflist[$dhcpv6if]))
			continue;
		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		$ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		$subnetmaskv6 = gen_subnet_mask($ifcfgsnv6);

		if($is_olsr_enabled == true)
			if($dhcpv6ifconf['netmask'])
				$subnetmask = gen_subnet_maskv6($dhcpv6ifconf['netmask']);

		$dnscfgv6 = "";

		if ($dhcpv6ifconf['domain']) {
			$dnscfgv6 .= "	option domain-name \"{$dhcpv6ifconf['domain']}\";\n";
		}

    		if($dhcpv6ifconf['domainsearchlist'] <> "") {
			$dnscfgv6 .= "	option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpv6ifconf['domainsearchlist'])) . "\";\n";
    		}

		if (isset($dhcpv6ifconf['ddnsupdate'])) {
			if($dhcpv6ifconf['ddnsdomain'] <> "") {
				$dnscfgv6 .= "	ddns-domainname \"{$dhcpv6ifconf['ddnsdomain']}\";\n";
			}
			$dnscfgv6 .= "	ddns-update-style interim;\n";
		}

		if (is_array($dhcpv6ifconf['dnsserver']) && ($dhcpv6ifconf['dnsserver'][0])) {
			$dnscfgv6 .= "	option dhcp6.name-servers " . join(",", $dhcpv6ifconf['dnsserver']) . ";";
		} else if (isset($config['dnsmasq']['enable'])) {
			$dnscfgv6 .= "	option dhcp6.name-servers {$ifcfgipv6};";
		} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
			$dns_arrv6 = array();
			foreach($syscfg['dnsserver'] as $dnsserver) {
				if(is_ipaddrv6($dnsserver)) {
					$dns_arrv6[] = $dnsserver;
				}
			}
			if(!empty($dns_arrv6))
				$dnscfgv6 .= "	option dhcp6.name-servers " . join(",", $dns_arrv6) . ";";
		}

		$subnet6 = Net_IPv6::compress(gen_subnetv6($ifcfgipv6, $ifcfgsnv6));
		$dhcpdv6conf .= "subnet6 {$subnet6}/{$ifcfgsnv6} {\n";

		if($dhcpv6ifconf['failover_peerip'] <> "")
			$dhcpdv6conf .= "		deny dynamic bootp clients;\n";

		if (isset($dhcpv6ifconf['denyunknown']))
		   $dhcpdv6conf .= "		deny unknown-clients;\n";

		if($dhcpv6ifconf['failover_peerip'] <> "") {
			$dhcpdv6conf .= "		failover peer \"dhcpv6{$dhcpv6num}\";\n";
			$dhcpv6num++;
		}

		$dhcpdv6conf .= <<<EOD
	range6 {$dhcpv6ifconf['range']['from']} {$dhcpv6ifconf['range']['to']};
$dnscfgv6

EOD;

		if(is_ipaddrv6($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['to'])) {
			$dhcpdv6conf .= "	prefix6 {$dhcpv6ifconf['prefixrange']['from']} {$dhcpv6ifconf['prefixrange']['to']}/{$dhcpv6ifconf['prefixrange']['prefixlength']};\n";

		}
    		// default-lease-time
		if ($dhcpv6ifconf['defaultleasetime'])
			$dhcpdv6conf .= "	default-lease-time {$dhcpv6ifconf['defaultleasetime']};\n";

		// max-lease-time
		if ($dhcpv6ifconf['maxleasetime'])
			$dhcpdv6conf .= "	max-lease-time {$dhcpv6ifconf['maxleasetime']};\n";

		// ntp-servers
		if (is_array($dhcpv6ifconf['ntpserver']) && $dhcpv6ifconf['ntpserver'][0]) {
			$ntpservers = array();
			foreach($dhcpv6ifconf['ntpserver'] as $ntpserver) {
				if(is_ipaddrv6($ntpserver))
					$ntpservers[] = $ntpserver;
			}
			if(count($ntpservers) > 0 )
				$dhcpdv6conf .= "       option dhcp6.sntp-servers " . join(",", $dhcpv6ifconf['ntpserver']) . ";\n";
		}
		// tftp-server-name
		/* Needs ISC DHCPD support
		 if ($dhcpv6ifconf['tftp'] <> "")
			$dhcpdv6conf .= "	option tftp-server-name \"{$dhcpv6ifconf['tftp']}\";\n";
		*/

		// Handle option, number rowhelper values
		$dhcpdv6conf .= "\n";
		if($dhcpv6ifconf['numberoptions']['item']) {
			foreach($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
				$dhcpdv6conf .= "	option custom-{$dhcpv6if}-{$itemv6idx} \"{$itemv6['value']}\";\n";
			}
		}

		// ldap-server
		if ($dhcpv6ifconf['ldap'] <> "")
			$dhcpdv6conf .= "	option ldap-server \"{$dhcpv6ifconf['ldap']}\";\n";

		// net boot information
		if(isset($dhcpv6ifconf['netboot'])) {
			if ($dhcpv6ifconf['nextserver'] <> "") {
				$dhcpdv6conf .= "	next-server {$dhcpv6ifconf['nextserver']};\n";
			}
			if ($dhcpv6ifconf['filename'] <> "") {
				$dhcpdv6conf .= "	filename \"{$dhcpv6ifconf['filename']}\";\n";
			}
			if ($dhcpv6ifconf['rootpath'] <> "") {
				$dhcpdv6conf .= "	option root-path \"{$dhcpv6ifconf['rootpath']}\";\n";
      		}
	}

		$dhcpdv6conf .= <<<EOD
}

EOD;

		/* add static mappings */
		/* Needs to use DUID */
		if (is_array($dhcpv6ifconf['staticmap'])) {

			$i = 0;
			foreach ($dhcpv6ifconf['staticmap'] as $sm) {
				$dhcpdv6conf .= <<<EOD
host s_{$dhcpv6if}_{$i} {
	host-identifier option dhcp6.client-id {$sm['duid']};

EOD;
				if ($sm['ipaddrv6'])
					$dhcpdv6conf .= "	fixed-address6 {$sm['ipaddrv6']};\n";

				if ($sm['hostname']) {
					$dhhostname = str_replace(" ", "_", $sm['hostname']);
					$dhhostname = str_replace(".", "_", $dhhostname);
					$dhcpdv6conf .= "	option host-name {$dhhostname};\n";
				}
				if ($sm['filename'])
					$dhcpdv6conf .= "	filename \"{$sm['filename']}\";\n";

				if ($sm['rootpath'])
					$dhcpdv6conf .= "	option root-path \"{$sm['rootpath']}\";\n";

				$dhcpdv6conf .= "}\n";
				$i++;
			}
		}

		if($config['dhcpdv6'][$dhcpv6if]['ramode'] <> "unmanaged") {
			$realif = escapeshellcmd(get_real_interface($dhcpv6if));
			$dhcpdv6ifs[] = $realif;
			exec("/sbin/ifconfig {$realif} |awk  '/ether/ {print $2}'", $mac);
			$v6address = generate_ipv6_from_mac($mac[0]);
			/* Create link local address for bridges */
			if(stristr("$realif", "bridge")) {
				mwexec("/sbin/ifconfig {$realif} inet6 {$v6address}");
			}
		}
	}

	fwrite($fdv6, $dhcpdv6conf);
	fclose($fdv6);
	unset($dhcpdv6conf);

	/* create an empty leases v6 database */
	touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");

	/* fire up dhcpd in a chroot */
	if(count($dhcpdv6ifs) > 0) {
		mwexec("/usr/local/sbin/dhcpd -6 -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpdv6.conf -pf {$g['varrun_path']}/dhcpdv6.pid " .
			join(" ", $dhcpdv6ifs));
		mwexec("/usr/local/sbin/dhcpleases6 -c \"/usr/local/bin/php -f /usr/local/sbin/prefixes.php|/bin/sh\" -l {$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");
	}

	if ($g['booting']) {
		print gettext("done.") . "\n";
	}

	return 0;
}

function services_igmpproxy_configure() {
        global $config, $g;

        /* kill any running igmpproxy */
        killbyname("igmpproxy");

	if (!is_array($config['igmpproxy']['igmpentry']) || (count($config['igmpproxy']['igmpentry']) == 0))
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
                log_error(gettext("Could not write Igmpproxy configuration file!"));
                return;
        }
        fwrite($igmpfl, $igmpconf);
        fclose($igmpfl);
	unset($igmpconf);

        mwexec("/usr/local/sbin/igmpproxy -c {$g['tmp_path']}/igmpproxy.conf");
        log_error(gettext("Started IGMP proxy service."));

        return 0;
}

function services_dhcrelay_configure() {
	global $config, $g;
	if ($g['platform'] == 'jail')
		return;
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
		echo gettext("Starting DHCP relay service...");
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
	unset($cmd);

	return 0;
}

function services_dhcrelay6_configure() {
	global $config, $g;
	if ($g['platform'] == 'jail')
		return;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcrelay_configure() being called $mt\n";
	}

	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay6.pid");

	$dhcrelaycfg =& $config['dhcrelay6'];

	/* DHCPv6 Relay enabled on any interfaces? */
	if (!isset($dhcrelaycfg['enable']))
		return 0;

	if ($g['booting'])
		echo gettext("Starting DHCPv6 relay service...");
	else
		sleep(1);

	$iflist = get_configured_interface_list();

	$dhcifaces = explode(",", $dhcrelaycfg['interface']);
	foreach ($dhcifaces as $dhcrelayif) {
		if (!isset($iflist[$dhcrelayif]) ||
			link_interface_to_bridge($dhcrelayif))
			continue;

		if (is_ipaddrv6(get_interface_ipv6($dhcrelayif)))
			$dhcrelayifs[] = get_real_interface($dhcrelayif);
	}
	$dhcrelayifs = array_unique($dhcrelayifs);

	/*
	 * In order for the relay to work, it needs to be active
	 * on the interface in which the destination server sits.
	 */
	$srvips = explode(",", $dhcrelaycfg['server']);
        $srvifaces = array();
	foreach ($srvips as $srcidx => $srvip) {
		unset($destif);
		foreach ($iflist as $ifname) {
			$subnet = get_interface_ipv6($ifname);
			if (!is_ipaddrv6($subnet))
				continue;
			$subnet .=  "/" . get_interface_subnetv6($ifname);
			if (ip_in_subnet($srvip, $subnet)) {
				$destif = get_real_interface($ifname);
				break;
			}
		}
		if (!isset($destif)) {
			if (is_array($config['staticroutes']['route'])) {
				foreach ($config['staticroutes']['route'] as $rtent) {
					if (ip_in_subnet($srvip, $rtent['network'])) {
						$a_gateways = return_gateways_array(true);
						$destif = $a_gateways[$rtent['gateway']]['interface'];
						break;
					}
				}
			}
		}

		if (!isset($destif)) {
			/* Create a array from the existing route table */
        		exec("/usr/bin/netstat -rnWf inet6", $route_str);
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

		if (!empty($destif)) {
			$srvifaces[] = "{$srvip}%{$destif}";
		}
	}

	/* fire up dhcrelay */
	if (empty($dhcrelayifs) || empty($srvifaces) ) {
		log_error("No suitable interface found for running dhcrelay -6!");
		return; /* XXX */
	}

	$cmd = "/usr/local/sbin/dhcrelay -6 -pf \"{$g['varetc_path']}/dhcrelay6.pid\"";
	foreach ($dhcrelayifs as $dhcrelayif) {
		$cmd .= " -l {$dhcrelayif}";
	}
	foreach ($srvifaces as $srviface) {
		$cmd .= " -u \"{$srviface}\"";
	}
	mwexec($cmd);
	unset($cmd);

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
		$dnsIf = "{$conf['interface']}",
		$dnsBackMX = NULL,
		$dnsServer = NULL,
		$dnsPort = NULL,
		$dnsUpdateURL = "{$conf['updateurl']}",
		$forceUpdate = $conf['force'],
                $dnsZoneID=$conf['zoneid'],
                $dnsTTL=$conf['ttl'],
		$dnsResultMatch = "{$conf['resultmatch']}",
		$dnsRequestIf = "{$conf['requestif']}",
		$dnsID = "{$conf['id']}");
}

function services_dyndns_configure($int = "") {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_configure() being called $mt\n";
	}

	$dyndnscfg = $config['dyndnses']['dyndns'];
	$gwgroups = return_gateway_groups_array();
	if (is_array($dyndnscfg)) {
		if ($g['booting'])
			echo gettext("Starting DynDNS clients...");

		foreach ($dyndnscfg as $dyndns) {
			if ((empty($int)) || ($int == $dyndns['interface']) || (is_array($gwgroups[$dyndns['interface']]))) {
				services_dyndns_configure_client($dyndns);
				sleep(1);
			}
		}

		if ($g['booting'])
			echo gettext("done.") . "\n";
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
			echo gettext("Starting DNS forwarder...");
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
		unset($args);

		if ($g['booting'])
			echo gettext("done.") . "\n";
	}

	if (!$g['booting']) {
		if(services_dhcpd_configure()!=0)
			$return = 1;
	}

	return $return;
}

function services_unbound_configure() {
	global $config, $g;
	$return = 0;

	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_unbound_configure() being called $mt\n";
	}

	/* kill any running unbound */
	sigkillbypid("{$g['varrun_path']}/unbound.pid", "TERM");

	if (isset($config['unbound']['enable'])) {

		if ($g['booting'])
			echo "Starting Unbound DNS...";
		else
			sleep(1);

		/* Setup Unbound DHCP Chroot environment */
		$fd = fopen("{$g['tmp_path']}/unbound.sh","w");
		$status = `/sbin/mount | /usr/bin/grep "{$g['unbound_chroot_path']}/dev"`;
		fwrite($fd, "mkdir -p {$g['unbound_chroot_path']}\n");
		fwrite($fd, "mkdir -p {$g['unbound_chroot_path']}/dev\n");
		fwrite($fd, "mkdir -p {$g['unbound_chroot_path']}/etc\n");
		if(!trim($status))
			fwrite($fd, "mount -t devfs devfs {$g['unbound_chroot_path']}/dev\n");
		fclose($fd);
		mwexec("/bin/sh {$g['tmp_path']}/unbound.sh");

		/* generate Unbound config file */
		if(unbound_generate_config()!=0) {
			log_error("Problem generating Unbound configuration.");
			$return = 1;
		}

		/* run Unbound */
		mwexec("/usr/local/sbin/unbound -c {$g['unbound_chroot_path']}/etc/unbound.conf");

		if ($g['booting'])
			echo "done.\n";
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
			echo gettext("Starting SNMP daemon... ");

		/* generate snmpd.conf */
		$fd = fopen("{$g['varetc_path']}/snmpd.conf", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open snmpd.conf in services_snmpd_configure().%s"),"\n");
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
			if(isset($config['snmpd']['modules']['ucd'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."ucd"     = "/usr/local/lib/snmp_ucd.so"

EOD;
			}
			if(isset($config['snmpd']['modules']['regex'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."regex"     = "/usr/local/lib/snmp_regex.so"

EOD;
			}
		}

		fwrite($fd, $snmpdconf);
		fclose($fd);
		unset($snmpdconf);

		if (isset($config['snmpd']['bindlan'])) {
			$bindlan = "";
		}

		/* run bsnmpd */
		mwexec("/usr/sbin/bsnmpd -c {$g['varetc_path']}/snmpd.conf" .
			"{$bindlan} -p {$g['varrun_path']}/snmpd.pid");

		if ($g['booting'])
			echo gettext("done.") . "\n";
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

				@file_put_contents("{$g['varetc_path']}/nsupdatecmds{$i}", $upinst);
				unset($upinst);

				/* invoke nsupdate */
				$cmd = "/usr/bin/nsupdate -k {$g['varetc_path']}/K{$i}{$keyname}+157+00000.key";
				if (isset($dnsupdate['usetcp']))
					$cmd .= " -v";
				$cmd .= " {$g['varetc_path']}/nsupdatecmds{$i}";

				mwexec_bg($cmd);
				unset($cmd);
			}
		}
	}

	return 0;
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
		$crontab_contents .= "# " . gettext("pfSense specific crontab entries") . "\n";
		$crontab_contents .= "# " .gettext( "Created:") . " " . date("F j, Y, g:i a") . "\n";
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
		$crontab_contents .= "# " . gettext("If possible do not add items to this file manually.") . "\n";
		$crontab_contents .= "# " . gettext("If you do so, this file must be terminated with a blank line (e.g. new line)") . "\n";
		$crontab_contents .= "#\n\n";
	}

	/* please maintain the newline at the end of file */
	file_put_contents("/etc/crontab", $crontab_contents);
	unset($crontab_contents);

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
		echo gettext("Starting UPnP service... ");
		require_once('/usr/local/pkg/miniupnpd.inc');
		sync_package_miniupnpd();
		echo "done.\n";
	}
}

function install_cron_job($command, $active=false, $minute="0", $hour="*", $monthday="*", $month="*", $weekday="*", $who="root") {
	global $config, $g;

	$is_installed = false;

	if (!is_array($config['cron']))
		$config['cron'] = array();
	if (!is_array($config['cron']['item']))
		$config['cron']['item'] = array();

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
			write_config(sprintf(gettext("Installed cron job for %s"), $command));
		} else {
			$config['cron']['item'][$x] = $cron_item;
			write_config(sprintf(gettext("Updated cron job for %s"), $command));
		}
	} else {
		if(($is_installed == true) && ($x > 0)) {
			unset($config['cron']['item'][$x]);
			write_config(sprintf(gettext("Remvoed cron job for %s"), $command));
		}
	}
	configure_cron();
}

?>
