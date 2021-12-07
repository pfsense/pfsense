<?php
/*
 * services.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
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


define('DYNDNS_PROVIDER_VALUES', 'all-inkl azure azurev6 citynetwork cloudflare cloudflare-v6 cloudns custom custom-v6 desec desec-v6 digitalocean digitalocean-v6 dnsexit dnsimple dnsmadeeasy dnsomatic domeneshop domeneshop-v6 dreamhost dreamhost-v6 duiadns duiadns-v6 dyfi dyndns dyndns-custom dyndns-static dyns dynv6 dynv6-v6 easydns easydns-v6 eurodns freedns freedns-v6 freedns2 freedns2-v6 glesys gandi-livedns gandi-livedns-v6 godaddy godaddy-v6 googledomains gratisdns he-net he-net-v6 he-net-tunnelbroker hover linode linode-v6 loopia mythicbeasts mythicbeasts-v6 namecheap nicru nicru-v6 noip noip-v6 noip-free noip-free-v6 onecom onecom-v6 ods opendns ovh-dynhost route53 route53-v6 selfhost spdyn spdyn-v6 strato yandex yandex-v6 zoneedit');
define('DYNDNS_PROVIDER_DESCRIPTIONS', 'All-Inkl.com,Azure DNS,Azure DNS (v6),City Network,Cloudflare,Cloudflare (v6),ClouDNS,Custom,Custom (v6),deSEC,deSEC (v6),DigitalOcean,DigitalOcean (v6),DNSexit,DNSimple,DNS Made Easy,DNS-O-Matic,Domeneshop,Domeneshop (v6),DreamHost,Dreamhost (v6),DuiaDns.net,DuiaDns.net (v6),DY.fi,DynDNS (dynamic),DynDNS (custom),DynDNS (static),DyNS,Dynv6,Dynv6 (v6),easyDNS,easyDNS (v6),Euro Dns,freeDNS,freeDNS (v6),freeDNS API Version 2, freeDNS API Version 2 (v6),GleSYS,Gandi Live DNS,Gandi Live DNS (v6),GoDaddy,GoDaddy (v6),Google Domains,GratisDNS,HE.net,HE.net (v6),HE.net Tunnelbroker,Hover,Linode,Linode (v6),Loopia,Mythic Beasts,Mythic Beasts (v6),Namecheap,NIC.RU,NIC.RU (v6),No-IP,No-IP (v6),No-IP (free),No-IP (free-v6),One.com,One.com (v6),ODS.org,OpenDNS,OVH DynHOST,Route 53,Route 53 (v6),SelfHost,SPDYN,SPDYN (v6),Strato,Yandex,Yandex (v6),ZoneEdit');

/* implement ipv6 route advertising daemon */
function services_radvd_configure($blacklist = array()) {
	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_radvd_configure() being called $mt\n";
	}

	if (!is_array($config['dhcpdv6'])) {
		$config['dhcpdv6'] = array();
	}

	$Iflist = get_configured_interface_list();
	$Iflist = array_merge($Iflist, get_configured_pppoe_server_interfaces());

	$radvdconf = "# Automatically Generated, do not edit\n";

	/* Process all links which need the router advertise daemon */
	$radvdifs = array();

	/* handle manually configured DHCP6 server settings first */
	foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
		if (!is_array($config['interfaces'][$dhcpv6if])) {
			continue;
		}
		if (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
			continue;
		}

		/* Do not put in the config an interface which is down */
		if (isset($blacklist[$dhcpv6if])) {
			continue;
		}
		if (!isset($dhcpv6ifconf['ramode'])) {
			$dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];
		}

		/* are router advertisements enabled? */
		if ($dhcpv6ifconf['ramode'] == "disabled") {
			continue;
		}

		if (!isset($dhcpv6ifconf['rapriority'])) {
			$dhcpv6ifconf['rapriority'] = "medium";
		}

		$racarpif = false;
		$rasrcaddr = "";
		/* check if binding to CARP IP */
		if (!empty($dhcpv6ifconf['rainterface'])) {
			if (strstr($dhcpv6ifconf['rainterface'], "_vip")) {
				if (is_linklocal(get_configured_vip_ipv6($dhcpv6ifconf['rainterface']))) {
					$rasrcaddr = get_configured_vip_ipv6($dhcpv6ifconf['rainterface']);
				}
				elseif (get_carp_interface_status($dhcpv6ifconf['rainterface']) == "MASTER") {
					$dhcpv6if = $dhcpv6ifconf['rainterface'];
					$racarpif = true;
				} else {
					continue;
				}
			}
		}

		$realif = get_real_interface($dhcpv6if, "inet6");

		if (isset($radvdifs[$realif])) {
			continue;
		}

		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if (!is_ipaddrv6($ifcfgipv6)) {
			continue;
		}

		$ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		if (!is_subnetv6($subnetv6 . "/" . $ifcfgsnv6)) {
			log_error("radvd: skipping configuration for interface $dhcpv6if because its subnet or prefix length is invalid.");
			continue;
		}
		$radvdifs[$realif] = $realif;

		$radvdconf .= "# Generated for DHCPv6 Server $dhcpv6if\n";
		$radvdconf .= "interface {$realif} {\n";
		if (strstr($realif, "ovpn")) {
			$radvdconf .= "\tUnicastOnly on;\n";
		}
		$radvdconf .= "\tAdvSendAdvert on;\n";

		if ($rasrcaddr) {
			$radvdconf .= "\tAdvRASrcAddress {\n";
			$radvdconf .= "\t\t{$rasrcaddr};\n";
			$radvdconf .= "\t};\n";
		}

		if (is_numericint($dhcpv6ifconf['raminrtradvinterval'])) {
			$radvdconf .= "\tMinRtrAdvInterval {$dhcpv6ifconf['raminrtradvinterval']};\n";
		} else {
			$radvdconf .= "\tMinRtrAdvInterval 200;\n";
		}

		if (is_numericint($dhcpv6ifconf['ramaxrtradvinterval'])) {
			$ramaxrtradvinterval = $dhcpv6ifconf['ramaxrtradvinterval'];
		} else {
			$ramaxrtradvinterval = 600;
		}
		$radvdconf .= "\tMaxRtrAdvInterval {$ramaxrtradvinterval};\n";
		if (is_numericint($dhcpv6ifconf['raadvdefaultlifetime'])) {
			$raadvdefaultlifetime = $dhcpv6ifconf['raadvdefaultlifetime'];
		} else {
			$raadvdefaultlifetime = $ramaxrtradvinterval * 3;
		}
		$radvdconf .= "\tAdvDefaultLifetime {$raadvdefaultlifetime};\n";

		$mtu = get_interface_mtu($realif);
		if (is_numeric($mtu)) {
			$radvdconf .= "\tAdvLinkMTU {$mtu};\n";
		} else {
			$radvdconf .= "\tAdvLinkMTU 1280;\n";
		}
		switch ($dhcpv6ifconf['rapriority']) {
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
		switch ($dhcpv6ifconf['ramode']) {
			case "managed":
			case "assist":
				$radvdconf .= "\tAdvManagedFlag on;\n";
				$radvdconf .= "\tAdvOtherConfigFlag on;\n";
				break;
			case "stateless_dhcp":
				$radvdconf .= "\tAdvManagedFlag off;\n";
				$radvdconf .= "\tAdvOtherConfigFlag on;\n";
				break;
		}
		$radvdconf .= "\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
		if ($racarpif == true || $rasrcaddr) {
			$radvdconf .= "\t\tDeprecatePrefix off;\n";
		} else {
			$radvdconf .= "\t\tDeprecatePrefix on;\n";
		}
		switch ($dhcpv6ifconf['ramode']) {
			case "managed":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous off;\n";
				break;
			case "router":
				$radvdconf .= "\t\tAdvOnLink off;\n";
				$radvdconf .= "\t\tAdvAutonomous off;\n";
				break;
			case "stateless_dhcp":
			case "assist":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous on;\n";
				break;
			case "unmanaged":
				$radvdconf .= "\t\tAdvOnLink on;\n";
				$radvdconf .= "\t\tAdvAutonomous on;\n";
				break;
		}

		if (is_numericint($dhcpv6ifconf['ravalidlifetime'])) {
		  $radvdconf .= "\t\tAdvValidLifetime {$dhcpv6ifconf['ravalidlifetime']};\n";
		} else {
		  $radvdconf .= "\t\tAdvValidLifetime 86400;\n";
		}

		if (is_numericint($dhcpv6ifconf['rapreferredlifetime'])) {
		  $radvdconf .= "\t\tAdvPreferredLifetime {$dhcpv6ifconf['rapreferredlifetime']};\n";
		} else {
		  $radvdconf .= "\t\tAdvPreferredLifetime 14400;\n";
		}

		$radvdconf .= "\t};\n";

		if (is_array($dhcpv6ifconf['subnets']['item'])) {
			foreach ($dhcpv6ifconf['subnets']['item'] as $subnet) {
				if (is_subnetv6($subnet)) {
					$radvdconf .= "\tprefix {$subnet} {\n";
					$radvdconf .= "\t\tDeprecatePrefix on;\n";
					switch ($dhcpv6ifconf['ramode']) {
						case "managed":
							$radvdconf .= "\t\tAdvOnLink on;\n";
							$radvdconf .= "\t\tAdvAutonomous off;\n";
							break;
						case "router":
							$radvdconf .= "\t\tAdvOnLink off;\n";
							$radvdconf .= "\t\tAdvAutonomous off;\n";
							break;
						case "assist":
							$radvdconf .= "\t\tAdvOnLink on;\n";
							$radvdconf .= "\t\tAdvAutonomous on;\n";
							break;
						case "unmanaged":
							$radvdconf .= "\t\tAdvOnLink on;\n";
							$radvdconf .= "\t\tAdvAutonomous on;\n";
							break;
					}
					$radvdconf .= "\t};\n";
				}
			}
		}
		$radvdconf .= "\troute ::/0 {\n";
		switch ($dhcpv6ifconf['rapriority']) {
			case "low":
				$radvdconf .= "\t\tAdvRoutePreference low;\n";
				break;
			case "high":
				$radvdconf .= "\t\tAdvRoutePreference high;\n";
				break;
			default:
				$radvdconf .= "\t\tAdvRoutePreference medium;\n";
				break;
		}

		if ($rasrcaddr) {
			$radvdconf .= "\t\tRemoveRoute off;\n";
		}
		else {
			$radvdconf .= "\t\tRemoveRoute on;\n";
		}
		$radvdconf .= "\t};\n";

		/* add DNS servers */
		if ($dhcpv6ifconf['radvd-dns'] != 'disabled') {
			$dnslist = array();
			if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && is_array($dhcpv6ifconf['dnsserver']) && !empty($dhcpv6ifconf['dnsserver'])) {
				foreach ($dhcpv6ifconf['dnsserver'] as $server) {
					if (is_ipaddrv6($server)) {
						$dnslist[] = $server;
					}
				}
			} elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && isset($dhcpv6ifconf['radnsserver']) && is_array($dhcpv6ifconf['radnsserver'])) {
				foreach ($dhcpv6ifconf['radnsserver'] as $server) {
					if (is_ipaddrv6($server)) {
						$dnslist[] = $server;
					}
				}
			} elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
				$dnslist[] = get_interface_ipv6($realif);
			} elseif (is_array($config['system']['dnsserver']) && !empty($config['system']['dnsserver'])) {
				foreach ($config['system']['dnsserver'] as $server) {
					if (is_ipaddrv6($server)) {
						$dnslist[] = $server;
					}
				}
			}
			$raadvdnsslifetime = $ramaxrtradvinterval * 3;
			if (count($dnslist) > 0) {
				$dnsstring = implode(" ", $dnslist);
				if ($dnsstring <> "") {
					/* 
					 * The value of Lifetime SHOULD by default be at least
					 * 3 * MaxRtrAdvInterval, where MaxRtrAdvInterval is the
					 * maximum RA interval as defined in [RFC4861].
					 * see https://redmine.pfsense.org/issues/11105 
					 */
					$radvdconf .= "\tRDNSS {$dnsstring} {\n";
					$radvdconf .= "\t\tAdvRDNSSLifetime {$raadvdnsslifetime};\n";
					$radvdconf .= "\t};\n";
				}
			}

			$searchlist = array();
			$domainsearchlist = explode(';', $dhcpv6ifconf['radomainsearchlist']);
			foreach ($domainsearchlist as $sd) {
				$sd = trim($sd);
				if (is_hostname($sd)) {
					$searchlist[] = $sd;
				}
			}
			if (count($searchlist) > 0) {
				$searchliststring = trim(implode(" ", $searchlist));
			}
			if (!empty($dhcpv6ifconf['domain'])) {
				/* 
				 * Lifetime SHOULD by default be at least 3 * MaxRtrAdvInterval
				 * see https://redmine.pfsense.org/issues/12173 
				 */
				$radvdconf .= "\tDNSSL {$dhcpv6ifconf['domain']} {$searchliststring} {\n";
				$radvdconf .= "\t\tAdvDNSSLLifetime {$raadvdnsslifetime};\n";
				$radvdconf .= "\t};\n";
			} elseif (!empty($config['system']['domain'])) {
				$radvdconf .= "\tDNSSL {$config['system']['domain']} {$searchliststring} {\n";
				$radvdconf .= "\t\tAdvDNSSLLifetime {$raadvdnsslifetime};\n";
				$radvdconf .= "\t};\n";
			}
		}
		$radvdconf .= "};\n";
	}

	/* handle DHCP-PD prefixes and 6RD dynamic interfaces */
	foreach ($Iflist as $if => $ifdescr) {
		if (!isset($config['interfaces'][$if]['track6-interface']) ||
		    !isset($config['interfaces'][$if]['ipaddrv6']) ||
		    $config['interfaces'][$if]['ipaddrv6'] != 'track6') {
			continue;
		}
		if (!isset($config['interfaces'][$if]['enable'])) {
			continue;
		}
		if ($config['dhcpdv6'][$if]['ramode'] == "disabled") {
			continue;
		}
		/* Do not put in the config an interface which is down */
		if (isset($blacklist[$if])) {
			continue;
		}
		$trackif = $config['interfaces'][$if]['track6-interface'];
		if (empty($config['interfaces'][$trackif])) {
			continue;
		}

		$realif = get_real_interface($if, "inet6");

		/* prevent duplicate entries, manual overrides */
		if (isset($radvdifs[$realif])) {
			continue;
		}

		$ifcfgipv6 = get_interface_ipv6($if);
		if (!is_ipaddrv6($ifcfgipv6)) {
			$subnetv6 = "::";
			$ifcfgsnv6 = "64";
		} else {
			$ifcfgsnv6 = get_interface_subnetv6($if);
			$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		}
		$radvdifs[$realif] = $realif;

		$autotype = $config['interfaces'][$trackif]['ipaddrv6'];

		if ($g['debug']) {
			log_error("configuring RA on {$if} for type {$autotype} radvd subnet {$subnetv6}/{$ifcfgsnv6}");
		}

		$radvdconf .= "# Generated config for {$autotype} delegation from {$trackif} on {$if}\n";
		$radvdconf .= "interface {$realif} {\n";
		$radvdconf .= "\tAdvSendAdvert on;\n";
		if (is_numericint($dhcpv6ifconf['raminrtradvinterval'])) {
			$radvdconf .= "\tMinRtrAdvInterval {$dhcpv6ifconf['raminrtradvinterval']};\n";
		} else {
			$radvdconf .= "\tMinRtrAdvInterval 200;\n";
                }
		if (is_numericint($dhcpv6ifconf['ramaxrtradvinterval'])) {
			$radvdconf .= "\tMaxRtrAdvInterval {$dhcpv6ifconf['ramaxrtradvinterval']};\n";
			$raadvdnsslifetime = $dhcpv6ifconf['ramaxrtradvinterval'] * 3;
		} else {
			$radvdconf .= "\tMaxRtrAdvInterval 600;\n";
			$raadvdnsslifetime = 1800;
		}
		$mtu = get_interface_mtu($realif);
		if (is_numeric($mtu)) {
			$radvdconf .= "\tAdvLinkMTU {$mtu};\n";
		} else {
			$radvdconf .= "\tAdvLinkMTU 1280;\n";
		}
		$radvdconf .= "\tAdvOtherConfigFlag on;\n";
		$radvdconf .= "\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
		$radvdconf .= "\t\tAdvOnLink on;\n";
		$radvdconf .= "\t\tAdvAutonomous on;\n";
		$radvdconf .= "\t};\n";

		/* add DNS servers */
		if ($dhcpv6ifconf['radvd-dns'] != 'disabled') {
			$dnslist = array();
			if (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
				$dnslist[] = $ifcfgipv6;
			} elseif (is_array($config['system']['dnsserver']) && !empty($config['system']['dnsserver'])) {
				foreach ($config['system']['dnsserver'] as $server) {
					if (is_ipaddrv6($server)) {
						$dnslist[] = $server;
					}
				}
			}
			if (count($dnslist) > 0) {
				$dnsstring = implode(" ", $dnslist);
				if (!empty($dnsstring)) {
					$radvdconf .= "\tRDNSS {$dnsstring} { };\n";
				}
			}
			if (!empty($config['system']['domain'])) {
				$radvdconf .= "\tDNSSL {$config['system']['domain']} {\n";
				$radvdconf .= "\t\tAdvDNSSLLifetime {$raadvdnsslifetime};\n";
				$radvdconf .= "\t};\n";
			}
		}
		$radvdconf .= "};\n";
	}

	/* write radvd.conf */
	if (!@file_put_contents("{$g['varetc_path']}/radvd.conf", $radvdconf)) {
		log_error(gettext("Error: cannot open radvd.conf in services_radvd_configure()."));
		if (platform_booting()) {
			printf("Error: cannot open radvd.conf in services_radvd_configure().\n");
		}
	}
	unset($radvdconf);

	if (count($radvdifs) > 0) {
		if (isvalidpid("{$g['varrun_path']}/radvd.pid")) {
			sigkillbypid("{$g['varrun_path']}/radvd.pid", "HUP");
		} else {
			mwexec("/usr/local/sbin/radvd -p {$g['varrun_path']}/radvd.pid -C {$g['varetc_path']}/radvd.conf -m syslog");
		}
	} else {
		/* we need to shut down the radvd cleanly, it will send out the prefix
		 * information with a lifetime of 0 to notify clients of a (possible) new prefix */
		if (isvalidpid("{$g['varrun_path']}/radvd.pid")) {
			log_error(gettext("Shutting down Router Advertisment daemon cleanly"));
			killbypid("{$g['varrun_path']}/radvd.pid");
			@unlink("{$g['varrun_path']}/radvd.pid");
		}
	}
	return 0;
}

function services_dhcpd_configure($family = "all", $blacklist = array()) {
	global $config, $g;

	$dhcpdconfigurelck = lock("dhcpdconfigure", LOCK_EX);

	/* configure DHCPD chroot once */
	$fd = fopen("{$g['tmp_path']}/dhcpd.sh", "w");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/dev\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/etc\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/usr/local/sbin\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/db\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/run\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/usr\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/lib\n");
	fwrite($fd, "/bin/mkdir -p {$g['dhcpd_chroot_path']}/run\n");
	fwrite($fd, "/usr/sbin/chown -R dhcpd:_dhcp {$g['dhcpd_chroot_path']}/*\n");
	fwrite($fd, "/bin/cp -n /lib/libc.so.* {$g['dhcpd_chroot_path']}/lib/\n");
	fwrite($fd, "/bin/cp -n /usr/local/sbin/dhcpd {$g['dhcpd_chroot_path']}/usr/local/sbin/\n");
	fwrite($fd, "/bin/chmod a+rx {$g['dhcpd_chroot_path']}/usr/local/sbin/dhcpd\n");

	$status = `/sbin/mount | /usr/bin/grep -v grep | /usr/bin/grep "{$g['dhcpd_chroot_path']}/dev"`;
	if (!trim($status)) {
		fwrite($fd, "/sbin/mount -t devfs devfs {$g['dhcpd_chroot_path']}/dev\n");
	}
	fclose($fd);
	mwexec("/bin/sh {$g['tmp_path']}/dhcpd.sh");

	if ($family == "all" || $family == "inet") {
		services_dhcpdv4_configure();
	}
	if ($family == "all" || $family == "inet6") {
		services_dhcpdv6_configure($blacklist);
		services_radvd_configure($blacklist);
	}

	unlock($dhcpdconfigurelck);
}

function services_dhcpdv4_configure() {
	global $config, $g;
	$need_ddns_updates = false;
	$ddns_zones = array();

	if ($g['services_dhcp_server_enable'] == false) {
		return;
	}

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpdv4_configure($if) being called $mt\n";
	}

	/* kill any running dhcpd */
	if (isvalidpid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpd.pid")) {
		killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpd.pid");
	}

	/* DHCP enabled on any interfaces? */
	if (!is_dhcp_server_enabled()) {
		return 0;
	}

	$syscfg = $config['system'];
	if (!is_array($config['dhcpd'])) {
		$config['dhcpd'] = array();
	}
	$dhcpdcfg = $config['dhcpd'];
	$Iflist = get_configured_interface_list();

	/* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
	$dns_arrv4 = array();
	if (is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $dnsserver) {
			if (is_ipaddrv4($dnsserver)) {
				$dns_arrv4[] = $dnsserver;
			}
		}
	}

	if (platform_booting()) {
		echo gettext("Starting DHCP service...");
	} else {
		sleep(1);
	}

	$custoptions = "";
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		if (is_array($dhcpifconf['numberoptions']) && is_array($dhcpifconf['numberoptions']['item'])) {
			foreach ($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
				if (!empty($item['type'])) {
					$itemtype = $item['type'];
				} else {
					$itemtype = "text";
				}
				$custoptions .= "option custom-{$dhcpif}-{$itemidx} code {$item['number']} = {$itemtype};\n";
			}
		}
		if (is_array($dhcpifconf['pool'])) {
			foreach ($dhcpifconf['pool'] as $poolidx => $poolconf) {
				if (is_array($poolconf['numberoptions']) && is_array($poolconf['numberoptions']['item'])) {
					foreach ($poolconf['numberoptions']['item'] as $itemidx => $item) {
						if (!empty($item['type'])) {
							$itemtype = $item['type'];
						} else {
							$itemtype = "text";
						}
						$custoptions .= "option custom-{$dhcpif}-{$poolidx}-{$itemidx} code {$item['number']} = {$itemtype};\n";
					}
				}
			}
		}
		if (is_array($dhcpifconf['staticmap'])) {
			$i = 0;
			foreach ($dhcpifconf['staticmap'] as $sm) {
				if (is_array($sm['numberoptions']) && is_array($sm['numberoptions']['item'])) {
					foreach ($sm['numberoptions']['item'] as $itemidx => $item) {
						if (!empty($item['type'])) {
							$itemtype = $item['type'];
						} else {
							$itemtype = "text";
						}
						$custoptions .= "option custom-s_{$dhcpif}_{$i}-{$itemidx} code {$item['number']} = {$itemtype};\n";
					}
				}
				$i++;
			}
		}
	}

	$dhcpdconf = <<<EOD

option domain-name "{$syscfg['domain']}";
option ldap-server code 95 = text;
option domain-search-list code 119 = text;
option arch code 93 = unsigned integer 16; # RFC4578
{$custoptions}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
one-lease-per-client true;
deny duplicates;
update-conflict-detection false;

EOD;

	/* take these settings from the first DHCP configured interface,
	 * see https://redmine.pfsense.org/issues/10270 
	 * TODO: Global Settings tab, see https://redmine.pfsense.org/issues/5080 */
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		if (!isset($dhcpifconf['disableauthoritative'])) {
			$dhcpdconf .= "authoritative;\n";
		}

		if (isset($dhcpifconf['alwaysbroadcast'])) {
			$dhcpdconf .= "always-broadcast on\n";
		}

		// OMAPI Settings
		if (isset($dhcpifconf['omapi_port']) && is_numeric($dhcpifconf['omapi_port'])) {
			$dhcpdconf .= <<<EOD

key omapi_key {
  algorithm {$dhcpifconf['omapi_key_algorithm']};
  secret "{$dhcpifconf['omapi_key']}";
};
omapi-port {$dhcpifconf['omapi_port']};
omapi-key omapi_key;

EOD;

		}
		break;
	}

	$dhcpdifs = array();
	$enable_add_routers = false;
	$gateways_arr = return_gateways_array();
	/* only add a routers line if the system has any IPv4 gateway at all */
	/* a static route has a gateway, manually overriding this field always works */
	foreach ($gateways_arr as $gwitem) {
		if ($gwitem['ipprotocol'] == "inet") {
			$enable_add_routers = true;
			break;
		}
	}

	/*    loop through and determine if we need to setup
	 *    failover peer "bleh" entries
	 */
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {

		if (!isset($config['interfaces'][$dhcpif]['enable'])) {
			continue;
		}

		interfaces_staticarp_configure($dhcpif);

		if (!isset($dhcpifconf['enable'])) {
			continue;
		}

		if ($dhcpifconf['failover_peerip'] <> "") {
			$intip = get_interface_ip($dhcpif);
			/*
			 *    yep, failover peer is defined.
			 *    does it match up to a defined vip?
			 */
			$skew = 110;
			if (is_array($config['virtualip']['vip'])) {
				foreach ($config['virtualip']['vip'] as $vipent) {
					if ($vipent['mode'] != 'carp') {
						continue;
					}
					if ($vipent['interface'] == $dhcpif) {
						$carp_nw = gen_subnet($config['interfaces'][$dhcpif]['ipaddr'],
						    $config['interfaces'][$dhcpif]['subnet']);
						$carp_nw .= "/{$config['interfaces'][$dhcpif]['subnet']}";
						if (ip_in_subnet($dhcpifconf['failover_peerip'], $carp_nw)) {
							/* this is the interface! */
							if (is_numeric($vipent['advskew']) && (intval($vipent['advskew']) < 20)) {
								$skew = 0;
								break;
							}
						}
					}
				}
			} else {
				log_error(gettext("Warning!  DHCP Failover setup and no CARP virtual IPs defined!"));
			}
			if ($skew > 10) {
				$type = "secondary";
				$my_port = "520";
				$peer_port = "519";
				$dhcpdconf_pri = '';
			} else {
				$my_port = "519";
				$peer_port = "520";
				$type = "primary";
				$dhcpdconf_pri = "split 128;\n";
				$dhcpdconf_pri .= "  mclt 600;\n";
			}

			if (is_ipaddrv4($intip)) {
				$dhcpdconf .= <<<EOPP
failover peer "dhcp_{$dhcpif}" {
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
\n
EOPP;
			}
		}
	}

	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {

		$newzone = array();
		$ifcfg = $config['interfaces'][$dhcpif];

		if (!isset($dhcpifconf['enable']) || !isset($Iflist[$dhcpif])) {
			continue;
		}
		$ifcfgip = get_interface_ip($dhcpif);
		$ifcfgsn = get_interface_subnet($dhcpif);
		$subnet = gen_subnet($ifcfgip, $ifcfgsn);
		$subnetmask = gen_subnet_mask($ifcfgsn);

		if (!is_ipaddr($subnet)) {
			continue;
		}

		$all_pools = array();
		$all_pools[] = $dhcpifconf;
		if (is_array($dhcpifconf['pool'])) {
			$all_pools = array_merge($all_pools, $dhcpifconf['pool']);
		}

		$dnscfg = "";

		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
		}

		if ($dhcpifconf['domainsearchlist'] <> "") {
			$dnscfg .= "	option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpifconf['domainsearchlist'])) . "\";\n";
		}

		if (isset($dhcpifconf['ddnsupdate'])) {
			$need_ddns_updates = true;
			$newzone = array();
			if ($dhcpifconf['ddnsdomain'] <> "") {
				$newzone['domain-name'] = $dhcpifconf['ddnsdomain'];
				$dnscfg .= "	ddns-domainname \"{$dhcpifconf['ddnsdomain']}\";\n";
			} else {
				$newzone['domain-name'] = $config['system']['domain'];
			}

			if (empty($dhcpifconf['ddnsclientupdates'])) {
				$ddnsclientupdates = 'allow';
			} else {
				$ddnsclientupdates = $dhcpifconf['ddnsclientupdates'];
			}

			$dnscfg .= "	{$ddnsclientupdates} client-updates;\n";

			$revsubnet = array_reverse(explode('.',$subnet));

			$subnet_mask_bits = 32 - $ifcfgsn;
			$start_octet = $subnet_mask_bits >> 3;
			$octet_mask_bits = $subnet_mask_bits & ($subnet_mask_bits % 8);
			if ($octet_mask_bits) {
			    $octet_mask = (1 << $octet_mask_bits) - 1;
			    $octet_start = $revsubnet[$start_octet] & ~$octet_mask;
			    $revsubnet[$start_octet] = $octet_start . "-" . ($octet_start + $octet_mask);
			}

			$ptr_domain = '';
			for ($octet = 0; $octet <= 3; $octet++) {
				if ($octet < $start_octet) {
					continue;
				}
				$ptr_domain .= ((empty($ptr_domain) && $ptr_domain !== "0") ? '' : '.');
				$ptr_domain .= $revsubnet[$octet];
			}
			$ptr_domain .= ".in-addr.arpa";
			$newzone['ptr-domain'] = $ptr_domain;
			unset($ptr_domain, $revsubnet, $start_octet);
		}

		if (is_array($dhcpifconf['dnsserver']) && ($dhcpifconf['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $dhcpifconf['dnsserver']) . ";";
			if ($newzone['domain-name']) {
				$newzone['dns-servers'] = $dhcpifconf['dnsserver'];
			}
		} else if (isset($config['dnsmasq']['enable'])) {
			$dnscfg .= "	option domain-name-servers {$ifcfgip};";
			if ($newzone['domain-name'] && is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
				$newzone['dns-servers'] = $syscfg['dnsserver'];
			}
		} else if (isset($config['unbound']['enable'])) {
			$dnscfg .= "	option domain-name-servers {$ifcfgip};";
		} else if (!empty($dns_arrv4)) {
			$dnscfg .= "	option domain-name-servers " . join(",", $dns_arrv4) . ";";
			if ($newzone['domain-name']) {
				$newzone['dns-servers'] = $dns_arrv4;
			}
		}

		/* Create classes - These all contain comma separated lists. Join them into one
		   big comma separated string then split them all up. */
		$all_mac_strings = array();
		if (is_array($dhcpifconf['pool'])) {
			foreach ($all_pools as $poolconf) {
				$all_mac_strings[] = $poolconf['mac_allow'];
				$all_mac_strings[] = $poolconf['mac_deny'];
			}
		}
		$all_mac_strings[] = $dhcpifconf['mac_allow'];
		$all_mac_strings[] = $dhcpifconf['mac_deny'];
		if (!empty($all_mac_strings)) {
			$all_mac_list = array_unique(explode(',', implode(',', $all_mac_strings)));
			foreach ($all_mac_list as $mac) {
				if (empty($mac)) {
					continue;
				}
				$dhcpdconf .= 'class "' . str_replace(':', '', $mac) . '" {' . "\n";
				// Skip the first octet of the MAC address - for media type, typically Ethernet ("01") and match the rest.
				$dhcpdconf .= '	match if substring (hardware, 1, ' . (substr_count($mac, ':') + 1) . ') = ' . $mac . ';' . "\n";
				$dhcpdconf .= '}' . "\n";
			}
		}

		// instantiate class before pool definition
		$dhcpdconf .= "class \"s_{$dhcpif}\" {\n	match pick-first-value (option dhcp-client-identifier, hardware);\n}\n";

		$dhcpdconf .= "subnet {$subnet} netmask {$subnetmask} {\n";

		// Setup pool options
		foreach ($all_pools as $all_pools_idx => $poolconf) {
			if (!(ip_in_subnet($poolconf['range']['from'], "{$subnet}/{$ifcfgsn}") && ip_in_subnet($poolconf['range']['to'], "{$subnet}/{$ifcfgsn}"))) {
				// If the user has changed the subnet from the interfaces page and applied,
				// but has not updated the DHCP range, then the range to/from of the pool can be outside the subnet.
				// This can also happen when implementing the batch of changes when the setup wizard reloads the new settings.
				$error_msg = sprintf(gettext('Invalid DHCP pool %1$s - %2$s for %3$s subnet %4$s/%5$s detected. Please correct the settings in Services, DHCP Server'), $poolconf['range']['from'], $poolconf['range']['to'], convert_real_interface_to_friendly_descr($dhcpif), $subnet, $ifcfgsn);
				$do_file_notice = true;
				$conf_ipv4_address = $ifcfg['ipaddr'];
				$conf_ipv4_subnetmask = $ifcfg['subnet'];
				if (is_ipaddrv4($conf_ipv4_address) && is_subnet("{$conf_ipv4_address}/{$conf_ipv4_subnetmask}")) {
					$conf_subnet_base = gen_subnet($conf_ipv4_address, $conf_ipv4_subnetmask);
					if (ip_in_subnet($poolconf['range']['from'], "{$conf_subnet_base}/{$conf_ipv4_subnetmask}") &&
					    ip_in_subnet($poolconf['range']['to'], "{$conf_subnet_base}/{$conf_ipv4_subnetmask}")) {
						// Even though the running interface subnet does not match the pool range,
						// the interface subnet in the config file contains the pool range.
						// We are somewhere part-way through a settings reload, e.g. after running the setup wizard.
						// services_dhcpdv4_configure will be called again later when the new interface settings from
						// the config are applied and at that time everything will match up.
						// Ignore this pool on this interface for now and just log the error to the system log.
						log_error($error_msg);
						$do_file_notice = false;
					}
				}
				if ($do_file_notice) {
					file_notice("DHCP", $error_msg);
				}
				continue;
			}
			$dhcpdconf .= "	pool {\n";
			/* is failover dns setup? */
			if (is_array($poolconf['dnsserver']) && $poolconf['dnsserver'][0] <> "") {
				$dhcpdconf .= "		option domain-name-servers {$poolconf['dnsserver'][0]}";
				if ($poolconf['dnsserver'][1] <> "") {
					$dhcpdconf .= ",{$poolconf['dnsserver'][1]}";
				}
				if ($poolconf['dnsserver'][2] <> "") {
					$dhcpdconf .= ",{$poolconf['dnsserver'][2]}";
				}
				if ($poolconf['dnsserver'][3] <> "") {
					$dhcpdconf .= ",{$poolconf['dnsserver'][3]}";
				}
				$dhcpdconf .= ";\n";
			}

			/* allow/deny MACs */
			$mac_allow_list = array_unique(explode(',', $poolconf['mac_allow']));
			foreach ($mac_allow_list as $mac) {
				if (empty($mac)) {
					continue;
				}
				$dhcpdconf .= "		allow members of \"" . str_replace(':', '', $mac) . "\";\n";
			}
			$deny_action = "deny";
			if (isset($poolconf['nonak']) && empty($poolconf['failover_peerip'])) {
				$deny_action = "ignore";
			}
			$mac_deny_list = array_unique(explode(',', $poolconf['mac_deny']));
			foreach ($mac_deny_list as $mac) {
				if (empty($mac)) {
					continue;
				}
				$dhcpdconf .= "		$deny_action members of \"" . str_replace(':', '', $mac) . "\";\n";
			}

			if ($poolconf['failover_peerip'] <> "") {
				$dhcpdconf .= "		$deny_action dynamic bootp clients;\n";
			}

			// set pool MAC limitations
			if (isset($poolconf['denyunknown'])) {
				if ($poolconf['denyunknown'] == "class") {
					$dhcpdconf .= "		allow members of \"s_{$dhcpif}\";\n";
					$dhcpdconf .= "		$deny_action unknown-clients;\n";
				} else if ($poolconf['denyunknown'] == "disabled") {
					// add nothing to $dhcpdconf; condition added to prevent next condition applying if ever engine changes such that: isset("disabled") == true
				} else {	// "catch-all" covering "enabled" value post-PR#4066, and covering non-upgraded boolean option (i.e. literal value "enabled")
					$dhcpdconf .= "		$deny_action unknown-clients;\n";
				}
			}

			if ($poolconf['gateway'] && $poolconf['gateway'] != "none" && ($poolconf['gateway'] != $dhcpifconf['gateway'])) {
				$dhcpdconf .= "		option routers {$poolconf['gateway']};\n";
			}

			if ($dhcpifconf['failover_peerip'] <> "") {
				$dhcpdconf .= "		failover peer \"dhcp_{$dhcpif}\";\n";
			}

			$pdnscfg = "";

			if ($poolconf['domain'] && ($poolconf['domain'] != $dhcpifconf['domain'])) {
				$pdnscfg .= "		option domain-name \"{$poolconf['domain']}\";\n";
			}

			if (!empty($poolconf['domainsearchlist']) && ($poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
				$pdnscfg .= "		option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $poolconf['domainsearchlist'])) . "\";\n";
			}

			if (isset($poolconf['ddnsupdate'])) {
				if (($poolconf['ddnsdomain'] <> "") && ($poolconf['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
					$pdnscfg .= "		ddns-domainname \"{$poolconf['ddnsdomain']}\";\n";
				}
				$pdnscfg .= "		ddns-update-style interim;\n";
			}

			$dhcpdconf .= "{$pdnscfg}";

			// default-lease-time
			if ($poolconf['defaultleasetime'] && ($poolconf['defaultleasetime'] != $dhcpifconf['defaultleasetime'])) {
				$dhcpdconf .= "		default-lease-time {$poolconf['defaultleasetime']};\n";
			}

			// max-lease-time
			if ($poolconf['maxleasetime'] && ($poolconf['maxleasetime'] != $dhcpifconf['maxleasetime'])) {
				$dhcpdconf .= "		max-lease-time {$poolconf['maxleasetime']};\n";
			}

			// ignore bootp
			if (isset($poolconf['ignorebootp'])) {
				$dhcpdconf .= "		ignore bootp;\n";
			}

			// ignore-client-uids
			if (isset($poolconf['ignoreclientuids'])) {
				$dhcpdconf .= "		ignore-client-uids true;\n";
			}

			// netbios-name*
			if (is_array($poolconf['winsserver']) && $poolconf['winsserver'][0] && ($poolconf['winsserver'][0] != $dhcpifconf['winsserver'][0])) {
				$dhcpdconf .= "		option netbios-name-servers " . join(",", $poolconf['winsserver']) . ";\n";
				$dhcpdconf .= "		option netbios-node-type 8;\n";
			}

			// ntp-servers
			if (is_array($poolconf['ntpserver']) && $poolconf['ntpserver'][0] && ($poolconf['ntpserver'][0] != $dhcpifconf['ntpserver'][0])) {
				$dhcpdconf .= "		option ntp-servers " . join(",", $poolconf['ntpserver']) . ";\n";
			}

			// tftp-server-name
			if (!empty($poolconf['tftp']) && ($poolconf['tftp'] != $dhcpifconf['tftp'])) {
				$dhcpdconf .= "		option tftp-server-name \"{$poolconf['tftp']}\";\n";
			}

			// Handle pool-specific options
			$dhcpdconf .= "\n";
			// Ignore the first pool, which is the "overall" pool when $all_pools_idx is 0 - those are put outside the pool block later
			if (isset($poolconf['numberoptions']['item']) && is_array($poolconf['numberoptions']['item']) && ($all_pools_idx > 0)) {
				// Use the "real" pool index from the config, excluding the "overall" pool, and based from 0.
				// This matches the way $poolidx was used when generating the $custoptions string earlier.
				$poolidx = $all_pools_idx - 1;
				foreach ($poolconf['numberoptions']['item'] as $itemidx => $item) {
					$item_value = base64_decode($item['value']);
					if (empty($item['type']) || $item['type'] == "text") {
						$dhcpdconf .= "		option custom-{$dhcpif}-{$poolidx}-{$itemidx} \"{$item_value}\";\n";
					} else {
						$dhcpdconf .= "		option custom-{$dhcpif}-{$poolidx}-{$itemidx} {$item_value};\n";
					}
				}
			}

			// ldap-server
			if (!empty($poolconf['ldap']) && ($poolconf['ldap'] != $dhcpifconf['ldap'])) {
				$dhcpdconf .= "		option ldap-server \"{$poolconf['ldap']}\";\n";
			}

			// net boot information
			if (isset($poolconf['netboot'])) {
				if (!empty($poolconf['nextserver']) && ($poolconf['nextserver'] != $dhcpifconf['nextserver'])) {
					$dhcpdconf .= "		next-server {$poolconf['nextserver']};\n";
				}

				$pxe_files = array();
				if (!empty($poolconf['filename']) &&
				    (!isset($dhcpifconf['filename']) ||
				    ($poolconf['filename'] != $dhcpifconf['filename']))) {
					$filename = $poolconf['filename'];
				}
				if (!empty($poolconf['uefihttpboot']) &&
				    (!isset($dhcpifconf['uefihttpboot']) ||
				    ($poolconf['uefihttpboot'] != $dhcpifconf['uefihttpboot']))) {
					$pxe_files[] = array('HTTPClient', $poolconf['uefihttpboot']);
				}
				if (!empty($poolconf['filename32']) &&
				    (!isset($dhcpifconf['filename32']) ||
				    ($poolconf['filename32'] != $dhcpifconf['filename32']))) {
					$pxe_files[] = array('00:06', $poolconf['filename32']);
				}
				if (!empty($poolconf['filename64']) &&
				    (!isset($dhcpifconf['filename64']) ||
				    ($poolconf['filename64'] != $dhcpifconf['filename64']))) {
					$pxe_files[] = array('00:07', $poolconf['filename64']);
					$pxe_files[] = array('00:09', $poolconf['filename64']);
				}
				if (!empty($poolconf['filename32arm']) &&
				    (!isset($dhcpifconf['filename32arm']) ||
				    ($poolconf['filename32arm'] != $dhcpifconf['filename32arm']))) {
					$pxe_files[] = array('00:0a', $poolconf['filename32arm']);
				}
				if (!empty($poolconf['filename64arm']) &&
				    (!isset($dhcpifconf['filename64arm']) ||
				    ($poolconf['filename64arm'] != $dhcpifconf['filename64arm']))) {
					$pxe_files[] = array('00:0b', $poolconf['filename64arm']);
				}

				$pxeif = false;
				if (is_array($pxe_files) && !empty($pxe_files)) {
					foreach ($pxe_files as $pxe) {
						if ($pxe[0] == 'HTTPClient') {
							$expr = "substring (option vendor-class-identifier, 0, 10) = \"HTTPClient\" {\n";
						} else {
							$expr = "option arch = {$pxe[0]} {\n";
						}
						if (!$pxeif) {
							$dhcpdconf .= "		if " . $expr;
							$pxeif = true;
						} else {
							$dhcpdconf .= " else if " . $expr;
						}
						$dhcpdconf .= "			filename \"{$pxe[1]}\";\n";
						$dhcpdconf .= "		}";
					}
					if ($filename) {
						$dhcpdconf .= " else {\n";
						$dhcpdconf .= "			filename \"{$filename}\";\n";
						$dhcpdconf .= "		}";
					}
					$dhcpdconf .= "\n\n";
				} elseif (!empty($filename)) {
					$dhcpdconf .= "		filename \"{$filename}\";\n";
				}
				unset($filename);

				if (!empty($poolconf['rootpath']) && ($poolconf['rootpath'] != $dhcpifconf['rootpath'])) {
					$dhcpdconf .= "		option root-path \"{$poolconf['rootpath']}\";\n";
				}
			}
			$dhcpdconf .= "		range {$poolconf['range']['from']} {$poolconf['range']['to']};\n";
			$dhcpdconf .= "	}\n\n";
		}
// End of settings inside pools

		if ($dhcpifconf['gateway'] && $dhcpifconf['gateway'] != "none") {
			$routers = $dhcpifconf['gateway'];
			$add_routers = true;
		} elseif ($dhcpifconf['gateway'] == "none") {
			$add_routers = false;
		} else {
			$add_routers = $enable_add_routers;
			$routers = $ifcfgip;
		}
		if ($add_routers) {
			$dhcpdconf .= "	option routers {$routers};\n";
		}

		$dhcpdconf .= <<<EOD
$dnscfg

EOD;
		// default-lease-time
		if ($dhcpifconf['defaultleasetime']) {
			$dhcpdconf .= "	default-lease-time {$dhcpifconf['defaultleasetime']};\n";
		}

		// max-lease-time
		if ($dhcpifconf['maxleasetime']) {
			$dhcpdconf .= "	max-lease-time {$dhcpifconf['maxleasetime']};\n";
		}

		if (!isset($dhcpifconf['disablepingcheck'])) {
			$dhcpdconf .= "	ping-check true;\n";
		} else {
			$dhcpdconf .= "	ping-check false;\n";
		}

		// netbios-name*
		if (is_array($dhcpifconf['winsserver']) && $dhcpifconf['winsserver'][0]) {
			$dhcpdconf .= "	option netbios-name-servers " . join(",", $dhcpifconf['winsserver']) . ";\n";
			$dhcpdconf .= "	option netbios-node-type 8;\n";
		}

		// ntp-servers
		if (is_array($dhcpifconf['ntpserver']) && $dhcpifconf['ntpserver'][0]) {
			$dhcpdconf .= "	option ntp-servers " . join(",", $dhcpifconf['ntpserver']) . ";\n";
		}

		// tftp-server-name
		if ($dhcpifconf['tftp'] <> "") {
			$dhcpdconf .= "	option tftp-server-name \"{$dhcpifconf['tftp']}\";\n";
		}

		// Handle option, number rowhelper values
		$dhcpdconf .= "\n";
		if (isset($dhcpifconf['numberoptions']['item']) && is_array($dhcpifconf['numberoptions']['item'])) {
			foreach ($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
				$item_value = base64_decode($item['value']);
				if (empty($item['type']) || $item['type'] == "text") {
					$dhcpdconf .= "	option custom-{$dhcpif}-{$itemidx} \"{$item_value}\";\n";
				} else {
					$dhcpdconf .= "	option custom-{$dhcpif}-{$itemidx} {$item_value};\n";
				}
			}
		}

		// ldap-server
		if ($dhcpifconf['ldap'] <> "") {
			$dhcpdconf .= "	option ldap-server \"{$dhcpifconf['ldap']}\";\n";
		}

		// net boot information
		if (isset($dhcpifconf['netboot'])) {
			if ($dhcpifconf['nextserver'] <> "") {
				$dhcpdconf .= "	next-server {$dhcpifconf['nextserver']};\n";
			}

			$pxe_files = array();
			if (!empty($dhcpifconf['filename'])) {
				$filename = $dhcpifconf['filename'];
			}
			if (!empty($dhcpifconf['uefihttpboot'])) {
				$pxe_files[] = array('HTTPClient', $dhcpifconf['uefihttpboot']);
			}
			if (!empty($dhcpifconf['filename32'])) {
				$pxe_files[] = array('00:06', $dhcpifconf['filename32']);
			}
			if (!empty($dhcpifconf['filename64'])) {
				$pxe_files[] = array('00:07', $dhcpifconf['filename64']);
				$pxe_files[] = array('00:09', $dhcpifconf['filename64']);
			}
			if (!empty($dhcpifconf['filename32arm'])) {
				$pxe_files[] = array('00:0a', $dhcpifconf['filename32arm']);
			}
			if (!empty($dhcpifconf['filename64arm'])) {
				$pxe_files[] = array('00:0b', $dhcpifconf['filename64arm']);
			}

			$pxeif = false;
			if (is_array($pxe_files) && !empty($pxe_files)) {
				foreach ($pxe_files as $pxe) {
					if ($pxe[0] == 'HTTPClient') {
						$expr = "substring (option vendor-class-identifier, 0, 10) = \"HTTPClient\" {\n";
					} else {
						$expr = "option arch = {$pxe[0]} {\n";
					}
					if (!$pxeif) {
						$dhcpdconf .= "	if " . $expr; 
						$pxeif = true;
					} else {
						$dhcpdconf .= " else if " . $expr; 
					}
					$dhcpdconf .= "		filename \"{$pxe[1]}\";\n";
					$dhcpdconf .= "	}";
				}
				if ($filename) {
					$dhcpdconf .= " else {\n";
					$dhcpdconf .= "		filename \"{$filename}\";\n";
					$dhcpdconf .= "	}";
				}
				$dhcpdconf .= "\n\n";
			} elseif (!empty($filename)) {
				$dhcpdconf .= "		filename \"{$filename}\";\n";
			}
			if (!empty($dhcpifconf['rootpath'])) {
				$dhcpdconf .= "	option root-path \"{$dhcpifconf['rootpath']}\";\n";
			}
		}

		$dhcpdconf .= <<<EOD
}

EOD;

		/* add static mappings */
		if (is_array($dhcpifconf['staticmap'])) {

			$i = 0;
			$sm_newzone[] = array();
			$need_sm_ddns_updates = false;
			foreach ($dhcpifconf['staticmap'] as $sm) {
				$cid = '';
				$dhcpdconf .= "host s_{$dhcpif}_{$i} {\n";

				if ($sm['mac']) {
					$dhcpdconf .= "	hardware ethernet {$sm['mac']};\n";
				}

				if ($sm['cid']) {
					$cid = str_replace('"', '\"', $sm['cid']);
					$dhcpdconf .= "	option dhcp-client-identifier \"{$cid}\";\n";
				}

				if ($sm['ipaddr']) {
					$dhcpdconf .= "	fixed-address {$sm['ipaddr']};\n";
				}

				if ($sm['hostname']) {
					$dhhostname = str_replace(" ", "_", $sm['hostname']);
					$dhhostname = str_replace(".", "_", $dhhostname);
					$dhcpdconf .= "	option host-name \"{$dhhostname}\";\n";
					if ((isset($dhcpifconf['ddnsupdate']) || isset($sm['ddnsupdate'])) && (isset($dhcpifconf['ddnsforcehostname']) || isset($sm['ddnsforcehostname']))) {
						$dhcpdconf .= "	ddns-hostname \"{$dhhostname}\";\n";
					}
				}
				if ($sm['filename']) {
					$dhcpdconf .= "	filename \"{$sm['filename']}\";\n";
				}

				if ($sm['rootpath']) {
					$dhcpdconf .= "	option root-path \"{$sm['rootpath']}\";\n";
				}

				if ($sm['gateway'] && ($sm['gateway'] != $dhcpifconf['gateway'])) {
					$dhcpdconf .= "	option routers {$sm['gateway']};\n";
				}

				$smdnscfg = "";

				if ($sm['domain'] && ($sm['domain'] != $dhcpifconf['domain'])) {
					$smdnscfg .= "	option domain-name \"{$sm['domain']}\";\n";
				}

				if (!empty($sm['domainsearchlist']) && ($sm['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
					$smdnscfg .= "	option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $sm['domainsearchlist'])) . "\";\n";
				}

				if (isset($sm['ddnsupdate'])) {
					if (($sm['ddnsdomain'] <> "") && ($sm['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
						$smdnscfg .= "	ddns-domainname \"{$sm['ddnsdomain']}\";\n";
				 		$need_sm_ddns_updates = true;	
					}
					$smdnscfg .= "	ddns-update-style interim;\n";
				}

				if (is_array($sm['dnsserver']) && ($sm['dnsserver'][0]) && ($sm['dnsserver'][0] != $dhcpifconf['dnsserver'][0])) {
					$smdnscfg .= "	option domain-name-servers " . join(",", $sm['dnsserver']) . ";\n";
				}
				$dhcpdconf .= "{$smdnscfg}";

				// default-lease-time
				if ($sm['defaultleasetime'] && ($sm['defaultleasetime'] != $dhcpifconf['defaultleasetime'])) {
					$dhcpdconf .= "	default-lease-time {$sm['defaultleasetime']};\n";
				}

				// max-lease-time
				if ($sm['maxleasetime'] && ($sm['maxleasetime'] != $dhcpifconf['maxleasetime'])) {
					$dhcpdconf .= "	max-lease-time {$sm['maxleasetime']};\n";
				}

				// netbios-name*
				if (is_array($sm['winsserver']) && $sm['winsserver'][0] && ($sm['winsserver'][0] != $dhcpifconf['winsserver'][0])) {
					$dhcpdconf .= "	option netbios-name-servers " . join(",", $sm['winsserver']) . ";\n";
					$dhcpdconf .= "	option netbios-node-type 8;\n";
				}

				// ntp-servers
				if (is_array($sm['ntpserver']) && $sm['ntpserver'][0] && ($sm['ntpserver'][0] != $dhcpifconf['ntpserver'][0])) {
					$dhcpdconf .= "	option ntp-servers " . join(",", $sm['ntpserver']) . ";\n";
				}

				// tftp-server-name
				if (!empty($sm['tftp']) && ($sm['tftp'] != $dhcpifconf['tftp'])) {
					$dhcpdconf .= "	option tftp-server-name \"{$sm['tftp']}\";\n";
				}

				// Handle option, number rowhelper values
				$dhcpdconf .= "\n";
				if (isset($sm['numberoptions']['item']) && is_array($sm['numberoptions']['item'])) {
					foreach ($sm['numberoptions']['item'] as $itemidx => $item) {
						$item_value = base64_decode($item['value']);
						if (empty($item['type']) || $item['type'] == "text") {
							$dhcpdconf .= "	option custom-s_{$dhcpif}_{$i}-{$itemidx} \"{$item_value}\";\n";
						} else {
							$dhcpdconf .= "	option custom-s_{$dhcpif}_{$i}-{$itemidx} {$item_value};\n";
						}
					}
				}

				// ldap-server
				if (!empty($sm['ldap']) && ($sm['ldap'] != $dhcpifconf['ldap'])) {
					$dhcpdconf .= "	option ldap-server \"{$sm['ldap']}\";\n";
				}

				// net boot information
				if (isset($sm['netboot'])) {
					if ($sm['nextserver'] <> "") {
						$dhcpdconf .= "	next-server {$sm['nextserver']};\n";
					}

					$pxe_files = array();
					if (!empty($sm['filename'])) {
						$filename = $sm['filename'];
					}
					if (!empty($sm['filename32'])) {
						$pxe_files[] = array('00:06', $sm['filename32']);
					}
					if (!empty($sm['filename64'])) {
						$pxe_files[] = array('00:07', $sm['filename64']);
						$pxe_files[] = array('00:09', $sm['filename64']);
					}
					if (!empty($sm['filename32arm'])) {
						$pxe_files[] = array('00:0a', $sm['filename32arm']);
					}
					if (!empty($sm['filename64arm'])) {
						$pxe_files[] = array('00:0b', $sm['filename64arm']);
					}

					$pxeif = false;
					if (is_array($pxe_files) && !empty($pxe_files)) {
						foreach ($pxe_files as $pxe) {
							if (!$pxeif) {
								$dhcpdconf .= "	if option arch = {$pxe[0]} {\n";
								$pxeif = true;
							} else {
								$dhcpdconf .= " else if option arch = {$pxe[0]} {\n";
							}
							$dhcpdconf .= "		filename \"{$pxe[1]}\";\n";
							$dhcpdconf .= "	}";
						}
						if ($filename) {
							$dhcpdconf .= " else {\n";
							$dhcpdconf .= "		filename \"{$filename}\";\n";
							$dhcpdconf .= "	}";
						}
						$dhcpdconf .= "\n\n";
					} elseif (!empty($filename)) {
						$dhcpdconf .= "		filename \"{$filename}\";\n";
					}
					if (!empty($dhcpifconf['rootpath'])) {
						$dhcpdconf .= "	option root-path \"{$sm['rootpath']}\";\n";
					}
				}

				$dhcpdconf .= "}\n";

				// add zone DDNS key/server to $ddns_zone[] if required
				if ($need_sm_ddns_updates) {
					$ddnsduplicate = false;
					foreach ($ddns_zones as $ddnszone) {
						if ($ddnszone['domain-name'] == $sm['ddnsdomain']) {
							$ddnsduplicate = true;
						}
					}
					if (!$ddnsduplicate) {
						$sm_newzone['dns-servers'] = array($sm['ddnsdomainprimary'], $sm['ddnsdomainsecondary']);
						$sm_newzone['domain-name'] = $sm['ddnsdomain'];
						$sm_newzone['ddnsdomainkeyname'] = $sm['ddnsdomainkeyname'];
						$sm_newzone['ddnsdomainkeyalgorithm'] = $sm['ddnsdomainkeyalgorithm'];
						$sm_newzone['ddnsdomainkey'] = $sm['ddnsdomainkey'];
						$dhcpdconf .= dhcpdkey($sm_newzone);
						$ddns_zones[] = $sm_newzone;
						$need_ddns_updates = true;
					}
				}

				// subclass for DHCP limiting
				if (!empty($sm['mac'])) {
					// assuming ALL addresses are ethernet hardware type ("1:" prefix)
					$dhcpdconf .= "subclass \"s_{$dhcpif}\" 1:{$sm['mac']};\n";
				}
				if (!empty($cid)) {
					$dhcpdconf .= "subclass \"s_{$dhcpif}\" \"{$cid}\";\n";
				}


				$i++;
			}
		}

		$dhcpdifs[] = get_real_interface($dhcpif);
		if ($newzone['domain-name']) {
			if ($need_ddns_updates) {
				$newzone['dns-servers'] = array($dhcpifconf['ddnsdomainprimary'], $dhcpifconf['ddnsdomainsecondary']);
				$newzone['ddnsdomainkeyname'] = $dhcpifconf['ddnsdomainkeyname'];
				$newzone['ddnsdomainkeyalgorithm'] = $dhcpifconf['ddnsdomainkeyalgorithm'];
				$newzone['ddnsdomainkey'] = $dhcpifconf['ddnsdomainkey'];
				$dhcpdconf .= dhcpdkey($dhcpifconf);
			}
			$ddns_zones[] = $newzone;
		}
	}

	if ($need_ddns_updates) {
		$dhcpdconf .= "ddns-update-style interim;\n";
		$dhcpdconf .= "update-static-leases on;\n";

		$dhcpdconf .= dhcpdzones($ddns_zones);
	}

	/* write dhcpd.conf */
	if (!@file_put_contents("{$g['dhcpd_chroot_path']}/etc/dhcpd.conf", $dhcpdconf)) {
		printf(gettext("Error: cannot open dhcpd.conf in services_dhcpdv4_configure().%s"), "\n");
		unset($dhcpdconf);
		return 1;
	}
	unset($dhcpdconf);

	/* create an empty leases database */
	if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases")) {
		@touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");
	}

	/* make sure there isn't a stale dhcpd.pid file, which can make dhcpd fail to start.   */
	/* if we get here, dhcpd has been killed and is not started yet                        */
	unlink_if_exists("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpd.pid");

	/* fire up dhcpd in a chroot */
	if (count($dhcpdifs) > 0) {
		mwexec("/usr/local/sbin/dhcpd -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpd.conf -pf {$g['varrun_path']}/dhcpd.pid " .
			join(" ", $dhcpdifs));
	}

	if (platform_booting()) {
		print "done.\n";
	}

	return 0;
}

function dhcpdkey($dhcpifconf) {
	$dhcpdconf = "";
	if (!empty($dhcpifconf['ddnsdomainkeyname']) && !empty($dhcpifconf['ddnsdomainkey'])) {
		$algorithm = empty($dhcpifconf['ddnsdomainkeyalgorithm']) ? 'hmac-md5' : $dhcpifconf['ddnsdomainkeyalgorithm'];
		$dhcpdconf .= "key \"{$dhcpifconf['ddnsdomainkeyname']}\" {\n";
		$dhcpdconf .= "	algorithm {$algorithm};\n";
		$dhcpdconf .= "	secret {$dhcpifconf['ddnsdomainkey']};\n";
		$dhcpdconf .= "}\n";
	}

	return $dhcpdconf;
}

function dhcpdzones($ddns_zones) {
	$dhcpdconf = "";

	if (is_array($ddns_zones)) {
		$added_zones = array();
		foreach ($ddns_zones as $zone) {
			if (!is_array($zone) || empty($zone) || !is_array($zone['dns-servers'])) {
				continue;
			}
			$primary = $zone['dns-servers'][0];
			$secondary = empty($zone['dns-servers'][1]) ? "" : $zone['dns-servers'][1];

			// Make sure we aren't using any invalid servers.
			if (!is_ipaddr($primary)) {
				if (is_ipaddr($secondary)) {
					$primary = $secondary;
					$secondary = "";
				} else {
					continue;
				}
			}

			// We don't need to add zones multiple times.
			if ($zone['domain-name'] && !in_array($zone['domain-name'], $added_zones)) {
				$dhcpdconf .= "zone {$zone['domain-name']}. {\n";
				if (is_ipaddrv4($primary)) {
					$dhcpdconf .= "	primary {$primary};\n";
				} else {
					$dhcpdconf .= "	primary6 {$primary};\n";
				}
				if (is_ipaddrv4($secondary)) {
					$dhcpdconf .= "	secondary {$secondary};\n";
				} elseif (is_ipaddrv6($secondary)) {
					$dhcpdconf .= "	secondary6 {$secondary};\n";
				}
				if ($zone['ddnsdomainkeyname'] <> "" && $zone['ddnsdomainkey'] <> "") {
					$dhcpdconf .= "	key \"{$zone['ddnsdomainkeyname']}\";\n";
				}
				$dhcpdconf .= "}\n";
				$added_zones[] = $zone['domain-name'];
			}
			if ($zone['ptr-domain'] && !in_array($zone['ptr-domain'], $added_zones)) {
				$dhcpdconf .= "zone {$zone['ptr-domain']}. {\n";
				if (is_ipaddrv4($primary)) {
					$dhcpdconf .= "	primary {$primary};\n";
				} else {
					$dhcpdconf .= "	primary6 {$primary};\n";
				}
				if (is_ipaddrv4($secondary)) {
					$dhcpdconf .= "	secondary {$secondary};\n";
				} elseif (is_ipaddrv6($secondary)) {
					$dhcpdconf .= "	secondary6 {$secondary};\n";
				}
				if ($zone['ddnsdomainkeyname'] <> "" && $zone['ddnsdomainkey'] <> "") {
					$dhcpdconf .= "	key \"{$zone['ddnsdomainkeyname']}\";\n";
				}
				$dhcpdconf .= "}\n";
				$added_zones[] = $zone['ptr-domain'];
			}
		}
	}

	return $dhcpdconf;
}

function services_dhcpdv6_configure($blacklist = array()) {
	global $config, $g;

	if ($g['services_dhcp_server_enable'] == false) {
		return;
	}

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpd_configure($if) being called $mt\n";
	}

	/* kill any running dhcpd */
	if (isvalidpid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid")) {
		killbypid("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid");
	}
	if (isvalidpid("{$g['varrun_path']}/dhcpleases6.pid")) {
		killbypid("{$g['varrun_path']}/dhcpleases6.pid");
	}

	/* DHCP enabled on any interfaces? */
	if (!is_dhcpv6_server_enabled()) {
		return 0;
	}

	$syscfg = $config['system'];
	if (!is_array($config['dhcpdv6'])) {
		$config['dhcpdv6'] = array();
	}
	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();
	$Iflist = array_merge($Iflist, get_configured_pppoe_server_interfaces());


	if (platform_booting()) {
		echo "Starting DHCPv6 service...";
	} else {
		sleep(1);
	}

	$custoptionsv6 = "";
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if (is_array($dhcpv6ifconf['numberoptions']) && is_array($dhcpv6ifconf['numberoptions']['item'])) {
			foreach ($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
				$custoptionsv6 .= "option custom-{$dhcpv6if}-{$itemv6idx} code {$itemv6['number']} = text;\n";
			}
		}
	}

	if (isset($dhcpv6ifconf['netboot']) && !empty($dhcpv6ifconf['bootfile_url'])) {
		$custoptionsv6 .= "option dhcp6.bootfile-url code 59 = string;\n";
	}

	$dhcpdv6conf = <<<EOD

option domain-name "{$syscfg['domain']}";
option ldap-server code 95 = text;
option domain-search-list code 119 = text;
{$custoptionsv6}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
one-lease-per-client true;
deny duplicates;
ping-check true;
update-conflict-detection false;

EOD;

	if (!isset($dhcpv6ifconf['disableauthoritative'])) {
		$dhcpdv6conf .= "authoritative;\n";
	}

	if (isset($dhcpv6ifconf['alwaysbroadcast'])) {
		$dhcpdv6conf .= "always-broadcast on\n";
	}

	$dhcpdv6ifs = array();

	$dhcpv6num = 0;
	$nsupdate = false;

	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {

		$ddns_zones = array();

		$ifcfgv6 = $config['interfaces'][$dhcpv6if];

		if (!isset($dhcpv6ifconf['enable']) || !isset($Iflist[$dhcpv6if]) ||
		    (!isset($ifcfgv6['enable']) && !preg_match("/poes/", $dhcpv6if))) {
			continue;
		}
		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if (!is_ipaddrv6($ifcfgipv6) && !preg_match("/poes/", $dhcpv6if)) {
			continue;
		}
		$ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
		// We might have some prefix-delegation on WAN (e.g. /48),
		// but then it is split and given out to individual interfaces
		// (LAN, OPT1, OPT2...) as multiple /64 subnets. So the size
		// of each subnet here is always /64.
		$pdlen = 64;

		$dnscfgv6 = "";

		if ($dhcpv6ifconf['domain']) {
			$dnscfgv6 .= "	option domain-name \"{$dhcpv6ifconf['domain']}\";\n";
		}

		if ($dhcpv6ifconf['domainsearchlist'] <> "") {
			$dnscfgv6 .= "	option dhcp6.domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpv6ifconf['domainsearchlist'])) . "\";\n";
		}

		if (isset($dhcpv6ifconf['ddnsupdate'])) {
			if ($dhcpv6ifconf['ddnsdomain'] <> "") {
				$dnscfgv6 .= "	ddns-domainname \"{$dhcpv6ifconf['ddnsdomain']}\";\n";
			}
			if (empty($dhcpv6ifconf['ddnsclientupdates'])) {
				$ddnsclientupdates = 'allow';
			} else {
				$ddnsclientupdates = $dhcpv6ifconf['ddnsclientupdates'];
			}
			$dnscfgv6 .= "	{$ddnsclientupdates} client-updates;\n";
			$nsupdate = true;
		} else {
			$dnscfgv6 .= "	do-forward-updates false;\n";
		}

		if ($dhcpv6ifconf['dhcp6c-dns'] != 'disabled') {
			if (is_array($dhcpv6ifconf['dnsserver']) && ($dhcpv6ifconf['dnsserver'][0])) {
				$dnscfgv6 .= "	option dhcp6.name-servers " . join(",", $dhcpv6ifconf['dnsserver']) . ";\n";
			} else if (((isset($config['dnsmasq']['enable'])) || isset($config['unbound']['enable'])) && (is_ipaddrv6($ifcfgipv6))) {
				$dnscfgv6 .= "	option dhcp6.name-servers {$ifcfgipv6};\n";
			} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
				$dns_arrv6 = array();
				foreach ($syscfg['dnsserver'] as $dnsserver) {
					if (is_ipaddrv6($dnsserver)) {
						if ($ifcfgv6['ipaddrv6'] == 'track6' &&
						    Net_IPv6::isInNetmask($dnsserver, '::', $pdlen)) {
							$dnsserver = merge_ipv6_delegated_prefix($ifcfgipv6, $dnsserver, $pdlen);
						}
						$dns_arrv6[] = $dnsserver;
					}
				}
				if (!empty($dns_arrv6)) {
					$dnscfgv6 .= "	option dhcp6.name-servers " . join(",", $dns_arrv6) . ";\n";
				}
			}
		} else {
			$dnscfgv6 .= "	#option dhcp6.name-servers --;\n";
		}

		if (!is_ipaddrv6($ifcfgipv6)) {
			$ifcfgsnv6 = "64";
			$subnetv6 = gen_subnetv6($dhcpv6ifconf['range']['from'], $ifcfgsnv6);
		}

		$dhcpdv6conf .= "subnet6 {$subnetv6}/{$ifcfgsnv6}";

		if (isset($dhcpv6ifconf['ddnsupdate']) &&
		    !empty($dhcpv6ifconf['ddnsdomain'])) {
			$newzone = array();
			$newzone['domain-name'] = $dhcpv6ifconf['ddnsdomain'];
			$newzone['dns-servers'] = array($dhcpv6ifconf['ddnsdomainprimary'], $dhcpv6ifconf['ddnsdomainsecondary']);
			$newzone['ddnsdomainkeyname'] = $dhcpv6ifconf['ddnsdomainkeyname'];
			$newzone['ddnsdomainkey'] = $dhcpv6ifconf['ddnsdomainkey'];
			$ddns_zones[] = $newzone;
			if (isset($dhcpv6ifconf['ddnsreverse'])) {
				$ptr_zones = get_v6_ptr_zones($subnetv6, $ifcfgsnv6);
				foreach ($ptr_zones as $ptr_zone) {
					$reversezone = array();
					$reversezone['ptr-domain'] = $ptr_zone;
					$reversezone['dns-servers'] = array($dhcpv6ifconf['ddnsdomainprimary'], $dhcpv6ifconf['ddnsdomainsecondary']);
					$reversezone['ddnsdomainkeyname'] = $dhcpv6ifconf['ddnsdomainkeyname'];
					$reversezone['ddnsdomainkey'] = $dhcpv6ifconf['ddnsdomainkey'];
					$ddns_zones[] = $reversezone;
				}
			}
		}

		$dhcpdv6conf .= " {\n";

		$range_from = $dhcpv6ifconf['range']['from'];
		$range_to = $dhcpv6ifconf['range']['to'];
		if ($ifcfgv6['ipaddrv6'] == 'track6') {
			$range_from = merge_ipv6_delegated_prefix($ifcfgipv6, $range_from, $pdlen);
			$range_to = merge_ipv6_delegated_prefix($ifcfgipv6, $range_to, $pdlen);
		}

		if (!empty($dhcpv6ifconf['range']['from']) && !empty($dhcpv6ifconf['range']['to'])) {
			$dhcpdv6conf .= "	range6 {$range_from} {$range_to};\n";
		}

		$dhcpdv6conf .= $dnscfgv6;

		if (is_ipaddrv6($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['to'])) {
			$dhcpdv6conf .= "	prefix6 {$dhcpv6ifconf['prefixrange']['from']} {$dhcpv6ifconf['prefixrange']['to']} /{$dhcpv6ifconf['prefixrange']['prefixlength']};\n";
		}
		if (is_ipaddrv6($dhcpv6ifconf['dns6ip'])) {
			$dns6ip = $dhcpv6ifconf['dns6ip'];
			if ($ifcfgv6['ipaddrv6'] == 'track6' &&
			    Net_IPv6::isInNetmask($dns6ip, '::', $pdlen)) {
				$dns6ip = merge_ipv6_delegated_prefix($ifcfgipv6, $dns6ip, $pdlen);
			}
			$dhcpdv6conf .= "	option dhcp6.name-servers {$dns6ip};\n";
		}
		// default-lease-time
		if ($dhcpv6ifconf['defaultleasetime']) {
			$dhcpdv6conf .= "	default-lease-time {$dhcpv6ifconf['defaultleasetime']};\n";
		}

		// max-lease-time
		if ($dhcpv6ifconf['maxleasetime']) {
			$dhcpdv6conf .= "	max-lease-time {$dhcpv6ifconf['maxleasetime']};\n";
		}

		// ntp-servers
		if (is_array($dhcpv6ifconf['ntpserver']) && $dhcpv6ifconf['ntpserver'][0]) {
			$ntpservers = array();
			foreach ($dhcpv6ifconf['ntpserver'] as $ntpserver) {
				if (!is_ipaddrv6($ntpserver)) {
					continue;
				}
				if ($ifcfgv6['ipaddrv6'] == 'track6' &&
				    Net_IPv6::isInNetmask($ntpserver, '::', $pdlen)) {
					$ntpserver = merge_ipv6_delegated_prefix($ifcfgipv6, $ntpserver, $pdlen);
				}
				$ntpservers[] = $ntpserver;
			}
			if (count($ntpservers) > 0) {
				$dhcpdv6conf .= "        option dhcp6.sntp-servers " . join(",", $dhcpv6ifconf['ntpserver']) . ";\n";
			}
		}
		// tftp-server-name
		/* Needs ISC DHCPD support
		 if ($dhcpv6ifconf['tftp'] <> "") {
			$dhcpdv6conf .= "	option tftp-server-name \"{$dhcpv6ifconf['tftp']}\";\n";
		 }
		*/

		// Handle option, number rowhelper values
		$dhcpdv6conf .= "\n";
		if (isset($dhcpv6ifconf['numberoptions']['item']) && is_array($dhcpv6ifconf['numberoptions']['item'])) {
			foreach ($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
				$itemv6_value = base64_decode($itemv6['value']);
				$dhcpdv6conf .= "	option custom-{$dhcpv6if}-{$itemv6idx} \"{$itemv6_value}\";\n";
			}
		}

		// ldap-server
		if ($dhcpv6ifconf['ldap'] <> "") {
			$ldapserver = $dhcpv6ifconf['ldap'];
			if ($ifcfgv6['ipaddrv6'] == 'track6' &&
			    Net_IPv6::isInNetmask($ldapserver, '::', $pdlen)) {
				$ldapserver = merge_ipv6_delegated_prefix($ifcfgipv6, $ldapserver, $pdlen);
			}
			$dhcpdv6conf .= "	option ldap-server \"{$ldapserver}\";\n";
		}

		// net boot information
		if (isset($dhcpv6ifconf['netboot'])) {
			if (!empty($dhcpv6ifconf['bootfile_url'])) {
				$dhcpdv6conf .= "	option dhcp6.bootfile-url \"{$dhcpv6ifconf['bootfile_url']}\";\n";
			}
		}

		$dhcpdv6conf .= "}\n";

		/* add static mappings */
		/* Needs to use DUID */
		if (is_array($dhcpv6ifconf['staticmap'])) {
			$i = 0;
			foreach ($dhcpv6ifconf['staticmap'] as $sm) {
				$dhcpdv6conf .= <<<EOD
host s_{$dhcpv6if}_{$i} {
	host-identifier option dhcp6.client-id {$sm['duid']};

EOD;
				if ($sm['ipaddrv6']) {
					$ipaddrv6 = $sm['ipaddrv6'];
					if ($ifcfgv6['ipaddrv6'] == 'track6') {
						$ipaddrv6 = merge_ipv6_delegated_prefix($ifcfgipv6, $ipaddrv6, $pdlen);
					}
					$dhcpdv6conf .= "	fixed-address6 {$ipaddrv6};\n";
				}

				if ($sm['hostname']) {
					$dhhostname = str_replace(" ", "_", $sm['hostname']);
					$dhhostname = str_replace(".", "_", $dhhostname);
					$dhcpdv6conf .= "	option host-name {$dhhostname};\n";
					if (isset($dhcpv6ifconf['ddnsupdate']) &&
					    isset($dhcpv6ifconf['ddnsforcehostname'])) {
						$dhcpdv6conf .= "	ddns-hostname \"{$dhhostname}\";\n";
					}
				}
				if ($sm['filename']) {
					$dhcpdv6conf .= "	filename \"{$sm['filename']}\";\n";
				}

				if ($sm['rootpath']) {
					$dhcpdv6conf .= "	option root-path \"{$sm['rootpath']}\";\n";
				}

				$dhcpdv6conf .= "}\n";
				$i++;
			}
		}

		if ($dhcpv6ifconf['ddnsdomain']) {
			$dhcpdv6conf .= dhcpdkey($dhcpv6ifconf);
			$dhcpdv6conf .= dhcpdzones($ddns_zones);
		}

		if (($config['dhcpdv6'][$dhcpv6if]['ramode'] != "unmanaged") &&
		    (isset($config['interfaces'][$dhcpv6if]['enable']) ||
		    preg_match("/poes/", $dhcpv6if))) {
			if (preg_match("/poes/si", $dhcpv6if)) {
				/* magic here */
				$dhcpdv6ifs = array_merge($dhcpdv6ifs, get_pppoes_child_interfaces($dhcpv6if));
			} else {
				$realif = get_real_interface($dhcpv6if, "inet6");
				if (stristr("$realif", "bridge")) {
					$mac = get_interface_mac($realif);
					$v6address = generate_ipv6_from_mac($mac);
					/* Create link local address for bridges */
					mwexec("/sbin/ifconfig {$realif} inet6 {$v6address}");
				}
				$realif = escapeshellcmd($realif);
				$dhcpdv6ifs[] = $realif;
			}
		}
	}

	if ($nsupdate) {
		$dhcpdv6conf .= "ddns-update-style interim;\n";
		$dhcpdv6conf .= "update-static-leases on;\n";
	} else {
		$dhcpdv6conf .= "ddns-update-style none;\n";
	}

	/* write dhcpdv6.conf */
	if (!@file_put_contents("{$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf", $dhcpdv6conf)) {
		log_error("Error: cannot open {$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf in services_dhcpdv6_configure().\n");
		if (platform_booting()) {
			printf("Error: cannot open {$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf in services_dhcpdv6_configure().\n");
		}
		unset($dhcpdv6conf);
		return 1;
	}
	unset($dhcpdv6conf);

	/* create an empty leases v6 database */
	if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases")) {
		@touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");
	}

	/* make sure there isn't a stale dhcpdv6.pid file, which may make dhcpdv6 fail to start.  */
	/* if we get here, dhcpdv6 has been killed and is not started yet                         */
	unlink_if_exists("{$g['dhcpd_chroot_path']}{$g['varrun_path']}/dhcpdv6.pid");

	/* fire up dhcpd in a chroot */
	if (count($dhcpdv6ifs) > 0) {
		mwexec("/usr/local/sbin/dhcpd -6 -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpdv6.conf -pf {$g['varrun_path']}/dhcpdv6.pid " .
			join(" ", $dhcpdv6ifs));
		mwexec("/usr/local/sbin/dhcpleases6 -c \"/usr/local/bin/php-cgi -f /usr/local/sbin/prefixes.php\" -l {$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");
	}
	if (platform_booting()) {
		print gettext("done.") . "\n";
	}

	return 0;
}

function services_igmpproxy_configure() {
	global $config, $g;

	/* kill any running igmpproxy */
	killbyname("igmpproxy");

	if (!isset($config['igmpproxy']['enable'])) {
		return 0;
	}
	if (!is_array($config['igmpproxy']['igmpentry']) || (count($config['igmpproxy']['igmpentry']) == 0)) {
		return 1;
	}

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
		if (empty($igmpcf['threshold'])) {
			$threshld = 1;
		} else {
			$threshld = $igmpcf['threshold'];
		}
		$igmpconf .= "phyint {$realif} {$igmpcf['type']} ratelimit 0 threshold {$threshld}\n";

		if ($igmpcf['address'] <> "") {
			$item = explode(" ", $igmpcf['address']);
			foreach ($item as $iww) {
				$igmpconf .= "altnet {$iww}\n";
			}
		}
		$igmpconf .= "\n";
	}
	foreach ($iflist as $ifn) {
		$realif = get_real_interface($ifn);
		$igmpconf .= "phyint {$realif} disabled\n";
	}
	$igmpconf .= "\n";

	$igmpfl = fopen($g['varetc_path'] . "/igmpproxy.conf", "w");
	if (!$igmpfl) {
		log_error(gettext("Could not write Igmpproxy configuration file!"));
		return;
	}
	fwrite($igmpfl, $igmpconf);
	fclose($igmpfl);
	unset($igmpconf);

	if (isset($config['syslog']['igmpxverbose'])) {
		mwexec_bg("/usr/local/sbin/igmpproxy -v {$g['varetc_path']}/igmpproxy.conf");
	} else {
		mwexec_bg("/usr/local/sbin/igmpproxy {$g['varetc_path']}/igmpproxy.conf");
	}

	log_error(gettext("Started IGMP proxy service."));

	return 0;
}

function services_dhcrelay_configure() {
	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcrelay_configure() being called $mt\n";
	}

	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay.pid");

	init_config_arr(array('dhcrelay'));
	$dhcrelaycfg = &$config['dhcrelay'];

	/* DHCPRelay enabled on any interfaces? */
	if (!isset($dhcrelaycfg['enable'])) {
		return 0;
	}

	/* Start/Restart DHCP Relay, if a CARP VIP is set, check its status and act
	* appropriately. */
	if (isset($dhcrelaycfg['carpstatusvip']) && ($dhcrelaycfg['carpstatusvip'] != "none")) {
		$status = get_carp_interface_status($dhcrelaycfg['carpstatusvip']);
		switch (strtoupper($status)) {
			// Do not start DHCP Relay service if the VIP is in BACKUP or INIT state.
			case "BACKUP":
			case "INIT":
				log_error("Stopping DHCP Relay (CARP BACKUP/INIT)");
				return 0;
				break;
			// Start the service if the VIP is MASTER state.
			case "MASTER":
			// Assume it's up if the status can't be determined.
			default:
				break;
		}
	}

	if (platform_booting()) {
		echo gettext("Starting DHCP Relay service...");
	} else {
		sleep(1);
	}

	$iflist = get_configured_interface_list();

	$dhcrelayifs = array();
	$dhcifaces = explode(",", $dhcrelaycfg['interface']);
	foreach ($dhcifaces as $dhcrelayif) {
		if (!isset($iflist[$dhcrelayif])) {
			continue;
		}

		if (get_interface_ip($dhcrelayif)) {
			$dhcrelayifs[] = get_real_interface($dhcrelayif);
		}
	}
	$dhcrelayifs = array_unique($dhcrelayifs);

	/*
	 * In order for the relay to work, it needs to be active
	 * on the interface in which the destination server sits.
	 */
	$srvips = explode(",", $dhcrelaycfg['server']);
	if (!is_array($srvips)) {
		log_error(gettext("No destination IP has been configured!"));
		return;
	}
	$srvifaces = array();
	foreach ($srvips as $srcidx => $srvip) {
		$destif = guess_interface_from_ip($srvip);
		if (!empty($destif) && !is_pseudo_interface($destif)) {
			$srvifaces[] = $destif;
		}
	}
	$srvifaces = array_unique($srvifaces);

	/* Check for relays in the same subnet as clients so they can bind for
	 * either direction (up or down) */
	$srvrelayifs = array_intersect($dhcrelayifs, $srvifaces);

	/* The server interface(s) should not be in this list */
	$dhcrelayifs = array_diff($dhcrelayifs, $srvifaces);

	/* Remove the dual-role interfaces from up and down lists */
	$srvifaces = array_diff($srvifaces, $srvrelayifs);
	$dhcrelayifs = array_diff($dhcrelayifs, $srvrelayifs);

	/* fire up dhcrelay */
	if (empty($dhcrelayifs) && empty($srvrelayifs)) {
		log_error(gettext("No suitable downstream interfaces found for running dhcrelay!"));
		return; /* XXX */
	}
	if (empty($srvifaces) && empty($srvrelayifs)) {
		log_error(gettext("No suitable upstream interfaces found for running dhcrelay!"));
		return; /* XXX */
	}

	$cmd = "/usr/local/sbin/dhcrelay";

	if (!empty($dhcrelayifs)) {
		$cmd .= " -id " . implode(" -id ", $dhcrelayifs);
	}
	if (!empty($srvifaces)) {
		$cmd .= " -iu " . implode(" -iu ", $srvifaces);
	}
	if (!empty($srvrelayifs)) {
		$cmd .= " -i " . implode(" -i ", $srvrelayifs);
	}

	if (isset($dhcrelaycfg['agentoption'])) {
		$cmd .= " -a -m replace";
	}

	$cmd .= " " . implode(" ", $srvips);
	mwexec($cmd);
	unset($cmd);

	return 0;
}

function services_dhcrelay6_configure() {
	global $config, $g;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcrelay6_configure() being called $mt\n";
	}

	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay6.pid");

	init_config_arr(array('dhcrelay6'));
	$dhcrelaycfg = &$config['dhcrelay6'];

	/* DHCPv6 Relay enabled on any interfaces? */
	if (!isset($dhcrelaycfg['enable'])) {
		return 0;
	}

	/* Start/Restart DHCPv6 Relay, if a CARP VIP is set, check its status and act
	* appropriately. */
	if (isset($dhcrelaycfg['carpstatusvip']) && ($dhcrelaycfg['carpstatusvip'] != "none")) {
		$status = get_carp_interface_status($dhcrelaycfg['carpstatusvip']);
		switch (strtoupper($status)) {
			// Do not start DHCP Relay service if the VIP is in BACKUP or INIT state.
			case "BACKUP":
			case "INIT":
				log_error("Stopping DHCPv6 Relay (CARP BACKUP/INIT)");
				return 0;
				break;
			// Start the service if the VIP is MASTER state.
			case "MASTER":
			// Assume it's up if the status can't be determined.
			default:
				break;
		}
	}

	if (platform_booting()) {
		echo gettext("Starting DHCPv6 Relay service...");
	} else {
		sleep(1);
	}

	$iflist = get_configured_interface_list();

	$dhcifaces = explode(",", $dhcrelaycfg['interface']);
	foreach ($dhcifaces as $dhcrelayif) {
		if (!isset($iflist[$dhcrelayif])) {
			continue;
		}

		if (get_interface_ipv6($dhcrelayif)) {
			$dhcrelayifs[] = get_real_interface($dhcrelayif);
		}
	}
	$dhcrelayifs = array_unique($dhcrelayifs);

	/*
	 * In order for the relay to work, it needs to be active
	 * on the interface in which the destination server sits.
	 */
	$srvips = explode(",", $dhcrelaycfg['server']);
	$srvifaces = array();
	foreach ($srvips as $srcidx => $srvip) {
		$destif = guess_interface_from_ip($srvip);
		if (!empty($destif) && !is_pseudo_interface($destif)) {
			$srvifaces[] = "{$srvip}%{$destif}";
		}
	}

	/* fire up dhcrelay */
	if (empty($dhcrelayifs) || empty($srvifaces)) {
		log_error(gettext("No suitable interface found for running dhcrelay -6!"));
		return; /* XXX */
	}

	$cmd = "/usr/local/sbin/dhcrelay -6 -pf \"{$g['varrun_path']}/dhcrelay6.pid\"";
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

	if (!isset($conf['enable'])) {
		return;
	}

	/* load up the dyndns.class */
	require_once("dyndns.class");

	$dns = new updatedns($dnsService = $conf['type'],
		$dnsHost = $conf['host'],
		$dnsDomain = $conf['domainname'],
		$dnsUser = $conf['username'],
		$dnsPass = $conf['password'],
		$dnsWildcard = $conf['wildcard'],
		$dnsProxied = $conf['proxied'],
		$dnsMX = $conf['mx'],
		$dnsIf = "{$conf['interface']}",
		$dnsBackMX = NULL,
		$dnsServer = NULL,
		$dnsPort = NULL,
		$dnsUpdateURL = "{$conf['updateurl']}",
		$forceUpdate = $conf['force'],
		$dnsZoneID = $conf['zoneid'],
		$dnsTTL = $conf['ttl'],
		$dnsResultMatch = "{$conf['resultmatch']}",
		$dnsRequestIf = "{$conf['requestif']}",
		$dnsMaxCacheAge = $conf['maxcacheage'],
		$dnsID = "{$conf['id']}",
		$dnsVerboseLog = $conf['verboselog'],
		$curlIpresolveV4 = $conf['curl_ipresolve_v4'],
		$curlSslVerifypeer = $conf['curl_ssl_verifypeer'],
		$curlProxy = $conf['curl_proxy']);
}

function services_dyndns_configure($int = "") {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_configure() being called $mt\n";
	}

	if (isset($config['dyndnses']['dyndns']) && is_array($config['dyndnses']['dyndns'])) {
		$dyndnscfg = $config['dyndnses']['dyndns'];
	} else {
		return 0;
	}
	$gwgroups = return_gateway_groups_array(true);
	if (is_array($dyndnscfg)) {
		if (platform_booting()) {
			echo gettext("Starting DynDNS clients...");
		}

		foreach ($dyndnscfg as $dyndns) {
			/*
			 * If it's using a gateway group, check if interface is
			 * the active gateway for that group
			 */
			$group_int = '';
			$friendly_group_int = '';
			$gwgroup_member = false;
			if (is_array($gwgroups[$dyndns['interface']])) {
				if (!empty($gwgroups[$dyndns['interface']][0]['vip'])) {
					$group_int = $gwgroups[$dyndns['interface']][0]['vip'];
				} else {
					$group_int = $gwgroups[$dyndns['interface']][0]['int'];
					$friendly_group_int =
					    convert_real_interface_to_friendly_interface_name(
						$group_int);
					if (!empty($int)) {
						$gwgroup_member =
						    interface_gateway_group_member(get_real_interface($int),
						    $dyndns['interface']);
					}
				}
			}
			if ((empty($int)) || ($int == $dyndns['interface']) || $gwgroup_member ||
			    ($int == $group_int) || ($int == $friendly_group_int)) {
				$dyndns['verboselog'] = isset($dyndns['verboselog']);
				$dyndns['curl_ipresolve_v4'] = isset($dyndns['curl_ipresolve_v4']);
				$dyndns['curl_ssl_verifypeer'] = isset($dyndns['curl_ssl_verifypeer']);
				$dyndns['proxied'] = isset($dyndns['proxied']);
				$dyndns['wildcard'] = isset($dyndns['wildcard']);
				services_dyndns_configure_client($dyndns);
				sleep(1);
			}
		}

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	return 0;
}

function dyndnsCheckIP($int) {
	global $config, $factory_default_checkipservice;
	$ip_address = get_interface_ip($int);
	if (is_private_ip($ip_address)) {
		$gateways_status = return_gateways_status(true);
		// If the gateway for this interface is down, then the external check cannot work.
		// Avoid the long wait for the external check to timeout.
		if (stristr($gateways_status[$config['interfaces'][$int]['gateway']]['status'], "down")) {
			return "down";
		}

		// Append the factory default check IP service to the list (if not disabled).
		if (!isset($config['checkipservices']['disable_factory_default'])) {
			if (!is_array($config['checkipservices'])) {
				$config['checkipservices'] = array();
			}
			if (!is_array($config['checkipservices']['checkipservice'])) {
				$config['checkipservices']['checkipservice'] = array();
			}
			$config['checkipservices']['checkipservice'][] = $factory_default_checkipservice;
		}

		// Use the first enabled check IP service as the default.
		if (is_array($config['checkipservices']['checkipservice'])) {
			foreach ($config['checkipservices']['checkipservice'] as $i => $checkipservice) {
				if (isset($checkipservice['enable'])) {
					$url = $checkipservice['url'];
					$username = $checkipservice['username'];
					$password = $checkipservice['password'];
					$verifysslpeer = isset($checkipservice['verifysslpeer']);
					$curl_proxy = isset($checkipservice['curl_proxy']);
					break;
				}
			}
		}

		$hosttocheck = $url;
		$ip_ch = curl_init($hosttocheck);
		curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ip_ch, CURLOPT_SSL_VERIFYPEER, $verifysslpeer);
		curl_setopt($ip_ch, CURLOPT_INTERFACE, 'host!' . $ip_address);
		curl_setopt($ip_ch, CURLOPT_CONNECTTIMEOUT, '30');
		curl_setopt($ip_ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ip_ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ip_ch, CURLOPT_USERPWD, "{$username}:{$password}");
		if ($curl_proxy) {
			set_curlproxy($ip_ch);
		}
		$ip_result_page = curl_exec($ip_ch);
		curl_close($ip_ch);
		$ip_result_decoded = urldecode($ip_result_page);
		preg_match('=Current IP Address: (.*)</body>=siU', $ip_result_decoded, $matches);

		if ($matches[1]) {
			$parsed_ip = trim($matches[1]);
		} else {
			$parsed_ip = trim($ip_result_decoded);
		}

		preg_match('=((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])=', $parsed_ip, $matches);
		if ($matches[0]) {
			$ip_address = $matches[0];
		}
	}
	return $ip_address;
}

function services_dnsmasq_configure($restart_dhcp = true) {
	global $config, $g;
	$return = 0;

	// hard coded args: will be removed to avoid duplication if specified in custom_options
	$standard_args = array(
		"dns-forward-max" => "--dns-forward-max=5000",
		"cache-size" => "--cache-size=10000",
		"local-ttl" => "--local-ttl=1"
	);


	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dnsmasq_configure() being called $mt\n";
	}

	/* kill any running dnsmasq */
	if (file_exists("{$g['varrun_path']}/dnsmasq.pid")) {
		sigkillbypid("{$g['varrun_path']}/dnsmasq.pid", "TERM");
	}

	if (isset($config['dnsmasq']['enable'])) {

		if (platform_booting()) {
			echo gettext("Starting DNS forwarder...");
		} else {
			sleep(1);
		}

		/* generate hosts file */
		if (system_hosts_generate() != 0) {
			$return = 1;
		}

		$args = "";

		if (isset($config['dnsmasq']['regdhcp'])) {
			$args .= " --dhcp-hostsfile={$g['etc_path']}/hosts ";
		}

		/* Setup listen port, if non-default */
		if (is_port($config['dnsmasq']['port'])) {
			$args .= " --port={$config['dnsmasq']['port']} ";
		}

		$listen_addresses = "";
		if (isset($config['dnsmasq']['interface'])) {
			$interfaces = explode(",", $config['dnsmasq']['interface']);
			foreach ($interfaces as $interface) {
				$if = get_real_interface($interface);
				if (does_interface_exist($if)) {
					$laddr = get_interface_ip($interface);
					if (is_ipaddrv4($laddr)) {
						$listen_addresses .= " --listen-address={$laddr} ";
					}
					$laddr6 = get_interface_ipv6($interface);
					if (is_ipaddrv6($laddr6) && !isset($config['dnsmasq']['strictbind'])) {
						/*
						 * XXX: Since dnsmasq does not support link-local address
						 * with scope specified. These checks are being done.
						 */
						if (is_linklocal($laddr6) && strstr($laddr6, "%")) {
							$tmpaddrll6 = explode("%", $laddr6);
							$listen_addresses .= " --listen-address={$tmpaddrll6[0]} ";
						} else {
							$listen_addresses .= " --listen-address={$laddr6} ";
						}
					}
				}
			}
			if (!empty($listen_addresses)) {
				$args .= " {$listen_addresses} ";
				if (isset($config['dnsmasq']['strictbind'])) {
					$args .= " --bind-interfaces ";
				}
			}
		}

		/* If selected, then first forward reverse lookups for private IPv4 addresses to nowhere. */
		/* Only make entries for reverse domains that do not have a matching domain override. */
		if (isset($config['dnsmasq']['no_private_reverse'])) {
			/* Note: Carrier Grade NAT (CGN) addresses 100.64.0.0/10 are intentionally not here. */
			/* End-users should not be aware of CGN addresses, so reverse lookups for these should not happen. */
			/* Just the pfSense WAN might get a CGN address from an ISP. */

			// Build an array of domain overrides to help in checking for matches.
			$override_a = array();
			if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
				foreach ($config['dnsmasq']['domainoverrides'] as $override) {
					$override_a[$override['domain']] = "y";
				}
			}

			// Build an array of the private reverse lookup domain names
			$reverse_domain_a = array("10.in-addr.arpa", "168.192.in-addr.arpa");
			// Unfortunately the 172.16.0.0/12 range does not map nicely to the in-addr.arpa scheme.
			for ($subnet_num = 16; $subnet_num < 32; $subnet_num++) {
				$reverse_domain_a[] = "$subnet_num.172.in-addr.arpa";
			}

			// Set the --server parameter to nowhere for each reverse domain name that was not specifically specified in a domain override.
			foreach ($reverse_domain_a as $reverse_domain) {
				if (!isset($override_a[$reverse_domain])) {
					$args .= " --server=/$reverse_domain/ ";
				}
			}
			unset($override_a);
			unset($reverse_domain_a);
		}

		/* Setup forwarded domains */
		if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
			foreach ($config['dnsmasq']['domainoverrides'] as $override) {
				if ($override['ip'] == "!") {
					$override['ip'] = "";
				}
				$args .= ' --server=/' . $override['domain'] . '/' . $override['ip'];
			}
		}

		/* Allow DNS Rebind for forwarded domains */
		if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
			if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
				foreach ($config['dnsmasq']['domainoverrides'] as $override) {
					$args .= ' --rebind-domain-ok=/' . $override['domain'] . '/ ';
				}
			}
		}

		if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
			$dns_rebind = "--rebind-localhost-ok --stop-dns-rebind";
		}

		if (isset($config['dnsmasq']['strict_order'])) {
			$args .= " --strict-order ";
		}

		if (isset($config['dnsmasq']['domain_needed'])) {
			$args .= " --domain-needed ";
		}

		if ($config['dnsmasq']['custom_options']) {
			foreach (preg_split('/\s+/', $config['dnsmasq']['custom_options']) as $c) {
				$args .= " " . escapeshellarg("--{$c}");
				$p = explode('=', $c);
				if (array_key_exists($p[0], $standard_args)) {
					unset($standard_args[$p[0]]);
				}
			}
		}
		$args .= ' ' . implode(' ', array_values($standard_args));

		/* run dnsmasq. Use "-C /dev/null" since we use command line args only (Issue #6730) */
		$cmd = "/usr/local/sbin/dnsmasq --all-servers -C /dev/null {$dns_rebind} {$args}";
		//log_error("dnsmasq command: {$cmd}");
		mwexec_bg($cmd);
		unset($args);

		system_dhcpleases_configure();

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	if (!platform_booting() && $restart_dhcp) {
		if (services_dhcpd_configure() != 0) {
			$return = 1;
		}
	}

	return $return;
}

function services_unbound_configure($restart_dhcp = true) {
	global $config, $g;
	$return = 0;

	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_unbound_configure() being called $mt\n";
	}

	if (isset($config['unbound']['enable'])) {
		require_once('/etc/inc/unbound.inc');

		/* Stop Unbound using TERM */
		if (file_exists("{$g['varrun_path']}/unbound.pid")) {
			sigkillbypid("{$g['varrun_path']}/unbound.pid", "TERM");
		}

		/* If unbound is still running, wait up to 30 seconds for it to terminate. */
		for ($i=1; $i <= 30; $i++) {
			if (is_process_running('unbound')) {
				sleep(1);
			}
		}

		$python_mode = false;
		if (isset($config['unbound']['python']) && !empty($config['unbound']['python_script'])) {
			$python_mode = true;
		}

		/* Include any additional functions as defined by python script include file */
		if (file_exists("{$g['unbound_chroot_path']}/{$config['unbound']['python_script']}_include.inc")) {
			exec("/usr/local/bin/php -l " . escapeshellarg("{$g['unbound_chroot_path']}/{$config['unbound']['python_script']}_include.inc")
				. " 2>&1", $py_output, $py_retval);
			if ($py_retval == 0) {
				require_once("{$g['unbound_chroot_path']}/{$config['unbound']['python_script']}_include.inc");
			}
		}

		/* DNS Resolver python integration */
		if ($python_mode) {
			if (!is_dir("{$g['unbound_chroot_path']}/dev")) {
				safe_mkdir("{$g['unbound_chroot_path']}/dev");
			}
			$status = "/sbin/mount | /usr/bin/grep " .  escapeshellarg("{$g['unbound_chroot_path']}/dev");
			if (!trim($status)) {
				exec("/sbin/mount -t devfs devfs " . escapeshellarg("{$g['unbound_chroot_path']}/dev"));
			}
		} else {
			if (is_dir("{$g['unbound_chroot_path']}/dev")) {
				exec("/sbin/umount -f " . escapeshellarg("{$g['unbound_chroot_path']}/dev"));
			}
		}
		$base_folder = '/usr/local';
		foreach (array('/bin', '/lib') as $dir) {
			$validate = exec("/sbin/mount | /usr/bin/grep " . escapeshellarg("{$g['unbound_chroot_path']}{$base_folder}{$dir} (nullfs") . " 2>&1");
			if ($python_mode) {

				// Add DNS Resolver python integration
				if (empty($validate)) {
					if (!is_dir("{$g['unbound_chroot_path']}{$base_folder}{$dir}")) {
						safe_mkdir("{$g['unbound_chroot_path']}{$base_folder}{$dir}");
					}
					$output = $retval = '';
					exec("/sbin/mount_nullfs -o ro " . escapeshellarg("/usr/local{$dir}") . ' '
					    . escapeshellarg("{$g['unbound_chroot_path']}{$base_folder}{$dir}") . " 2>&1", $output, $retval);

					// Disable Unbound python on mount failure
					if ($retval != 0) {
						$config['unbound']['python'] = '';
						$log_msg = "[Unbound-pymod]: Disabling Unbound python due to failed mount";
						write_config($log_msg);
						log_error($log_msg);
					}
				}
			}

			// Remove DNS Resolver python integration
			elseif (!empty($validate)) {
				exec("/sbin/umount -t nullfs " . escapeshellarg("{$g['unbound_chroot_path']}{$base_folder}{$dir}") . " 2>&1", $output, $retval);
				if ($retval == 0) {
					foreach (array( "/usr/local{$dir}", '/usr/local', '/usr') as $folder) {
						if (!empty($g['unbound_chroot_path']) && $g['unbound_chroot_path'] != '/' && is_dir("{$g['unbound_chroot_path']}{$folder}")) {
							@rmdir(escapeshellarg("{$g['unbound_chroot_path']}{$folder}"));
						}

						// Delete remaining subfolders on next loop
						if ($dir == '/bin') {
							break;
						}
					}
				}
				else {
					log_error("[Unbound-pymod]: Failed to unmount!");
				}
			}
		}

		if (platform_booting()) {
			echo gettext("Starting DNS Resolver...");
		} else {
			sleep(1);
		}

		/* generate hosts file */
		if (system_hosts_generate() != 0) {
			$return = 1;
		}

		/* Check here for dhcp6 complete - wait upto 10 seconds */
		if($config['interfaces']["wan"]['ipaddrv6'] == 'dhcp6') {
			$wanif = get_real_interface("wan", "inet6");
			if (platform_booting()) {
				for ($i=1; $i <= 10; $i++) {
					if (!file_exists("/tmp/{$wanif}_dhcp6_complete")) {
						log_error(gettext("Unbound start waiting on dhcp6c."));
						sleep(1);
					} else {
						unlink_if_exists("/tmp/{$wanif}_dhcp6_complete");
						log_error(gettext("dhcp6 init complete. Continuing"));
						break;
					}
				}
			}
		}

		sync_unbound_service();
		if (platform_booting()) {
			log_error(gettext("sync unbound done."));
			echo gettext("done.") . "\n";
		}

		system_dhcpleases_configure();
	} else {
		/* kill Unbound since it should not be enabled */
		if (file_exists("{$g['varrun_path']}/unbound.pid")) {
			sigkillbypid("{$g['varrun_path']}/unbound.pid", "KILL");
		}
	}

	if (!platform_booting() && $restart_dhcp) {
		if (services_dhcpd_configure() != 0) {
			$return = 1;
		}
	}

	return $return;
}

function services_snmpd_configure() {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_snmpd_configure() being called $mt\n";
	}

	/* kill any running snmpd */
	sigkillbypid("{$g['varrun_path']}/snmpd.pid", "TERM");
	sleep(2);
	if (is_process_running("bsnmpd")) {
		mwexec("/usr/bin/killall bsnmpd", true);
	}

	if (isset($config['snmpd']['enable'])) {

		if (platform_booting()) {
			echo gettext("Starting SNMP daemon... ");
		}

		/* Make sure a printcap file exists or else bsnmpd will log errors. See https://redmine.pfsense.org/issues/6838 */
		if (!file_exists('/etc/printcap')) {
			@file_put_contents('/etc/printcap', "# Empty file to prevent bsnmpd from logging errors.\n");
		}

		/* generate snmpd.conf */
		$fd = fopen("{$g['varetc_path']}/snmpd.conf", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open snmpd.conf in services_snmpd_configure().%s"), "\n");
			return 1;
		}


		$snmpdconf = <<<EOD
location := "{$config['snmpd']['syslocation']}"
contact := "{$config['snmpd']['syscontact']}"
read := "{$config['snmpd']['rocommunity']}"

EOD;

/* No docs on what write strings do there so disable for now.
		if (isset($config['snmpd']['rwenable']) && preg_match('/^\S+$/', $config['snmpd']['rwcommunity'])) {
			$snmpdconf .= <<<EOD
# write string
write := "{$config['snmpd']['rwcommunity']}"

EOD;
		}
*/


		if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])) {
			$snmpdconf .= <<<EOD
# SNMP Trap support.
traphost := {$config['snmpd']['trapserver']}
trapport := {$config['snmpd']['trapserverport']}
trap := "{$config['snmpd']['trapstring']}"


EOD;
		}

		$sysDescr = "{$g['product_label']} {$config['system']['hostname']}.{$config['system']['domain']}" .
			" {$g['product_version_string']} " . php_uname("s") .
			" " . php_uname("r") . " " . php_uname("m");

		$snmpdconf .= <<<EOD
system := 1     # pfSense
%snmpd
sysDescr			= "{$sysDescr}"
begemotSnmpdDebugDumpPdus       = 2
begemotSnmpdDebugSyslogPri      = 7
begemotSnmpdCommunityString.0.1 = $(read)

EOD;

/* No docs on what write strings do there so disable for now.
		if (isset($config['snmpd']['rwcommunity']) && preg_match('/^\S+$/', $config['snmpd']['rwcommunity'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdCommunityString.0.2 = $(write)

EOD;
		}
*/


		if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])) {
			$snmpdconf .= <<<EOD
begemotTrapSinkStatus.[$(traphost)].$(trapport) = 4
begemotTrapSinkVersion.[$(traphost)].$(trapport) = 2
begemotTrapSinkComm.[$(traphost)].$(trapport) = $(trap)

EOD;
		}


		$snmpdconf .= <<<EOD
begemotSnmpdCommunityDisable    = 1

EOD;

		$bind_to_ips = array();
		$bind_to_ip6s = array();
		if (isset($config['snmpd']['bindip'])) {
			if (!isset($config['snmpd']['ipprotocol']) ||
			    (isset($config['snmpd']['ipprotocol']) &&
			    strstr($config['snmpd']['ipprotocol'], "4"))) {
				$inet4 = true;
			}
			if (isset($config['snmpd']['ipprotocol']) &&
			    strstr($config['snmpd']['ipprotocol'], "6")) {
				$inet6 = true;
			}
			foreach (explode(",", $config['snmpd']['bindip']) as $bind_to_ip) {
				if (is_ipaddr($bind_to_ip)) {
					$bind_to_ips[] = $bind_to_ip;
				} else {
					$if = get_real_interface($bind_to_ip);
					if (does_interface_exist($if)) {
						if ($inet4) {
							$bindip = get_interface_ip($bind_to_ip);
							if (is_ipaddrv4($bindip)) {
								$bind_to_ips[] = $bindip;
							}
						}
						if ($inet6) {
							$bindip6 = get_interface_ipv6($bind_to_ip);
							if (is_ipaddrv6($bindip6)) {
								$bind_to_ip6s[] = $bindip6;
							}
						}
					}
				}
			}
		}
		if (!count($bind_to_ips) && $inet4) {
			$bind_to_ips = array("0.0.0.0");
		}
		if (!count($bind_to_ip6s) && $inet6) {
			$bind_to_ip6s = array("::");
		}

		if (is_port($config['snmpd']['pollport'])) {
			foreach ($bind_to_ips as $bind_to_ip) {
				$snmpdconf .= <<<EOD
begemotSnmpdPortStatus.{$bind_to_ip}.{$config['snmpd']['pollport']} = 1

EOD;

			}
			foreach ($bind_to_ip6s as $bind_to_ip6) {
				$bind_to_ip6 = ip6_to_asn1($bind_to_ip6);
				$snmpdconf .= <<<EOD
begemotSnmpdTransInetStatus.2.16.{$bind_to_ip6}{$config['snmpd']['pollport']}.1 = 4

EOD;

			}
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

		if (is_array($config['snmpd']['modules'])) {
			if (isset($config['snmpd']['modules']['mibii'])) {
			$snmpdconf .= <<<EOD
begemotSnmpdModulePath."mibII"  = "/usr/lib/snmp_mibII.so"

EOD;
			}

			if (isset($config['snmpd']['modules']['netgraph'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."netgraph" = "/usr/lib/snmp_netgraph.so"
%netgraph
begemotNgControlNodeName = "snmpd"

EOD;
			}

			if (isset($config['snmpd']['modules']['pf'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."pf"     = "/usr/lib/snmp_pf.so"

EOD;
			}

			if (isset($config['snmpd']['modules']['hostres'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."hostres"     = "/usr/lib/snmp_hostres.so"

EOD;
			}

			if (isset($config['snmpd']['modules']['bridge'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."bridge"     = "/usr/lib/snmp_bridge.so"
# config must end with blank line

EOD;
			}
			if (isset($config['snmpd']['modules']['ucd'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."ucd"     = "/usr/local/lib/snmp_ucd.so"

EOD;
			}
			if (isset($config['snmpd']['modules']['regex'])) {
				$snmpdconf .= <<<EOD
begemotSnmpdModulePath."regex"     = "/usr/local/lib/snmp_regex.so"

EOD;
			}
		}

		fwrite($fd, $snmpdconf);
		fclose($fd);
		unset($snmpdconf);

		/* run bsnmpd */
		mwexec("/usr/sbin/bsnmpd -c {$g['varetc_path']}/snmpd.conf" .
			" -p {$g['varrun_path']}/snmpd.pid");

		if (platform_booting()) {
			echo gettext("done.") . "\n";
		}
	}

	return 0;
}

function services_dnsupdate_process($int = "", $updatehost = "", $forced = false) {
	global $config, $g;
	if (isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dnsupdate_process() being called $mt\n";
	}

	/* Dynamic DNS updating active? */
	if (!is_array($config['dnsupdates']['dnsupdate'])) {
		return 0;
	}

	$notify_text = "";
	$gwgroups = return_gateway_groups_array(true);
	foreach ($config['dnsupdates']['dnsupdate'] as $i => $dnsupdate) {
		if (!isset($dnsupdate['enable'])) {
			continue;
		}
		/*
		 * If it's using a gateway group, check if interface is
		 * the active gateway for that group
		 */
		$group_int = '';
		$friendly_group_int = '';
		$gwgroup_member = false;
		if (is_array($gwgroups[$dnsupdate['interface']])) {
			if (!empty($gwgroups[$dnsupdate['interface']][0]['vip'])) {
				$group_int = $gwgroups[$dnsupdate['interface']][0]['vip'];
			} else {
				$group_int = $gwgroups[$dnsupdate['interface']][0]['int'];
				$friendly_group_int =
				    convert_real_interface_to_friendly_interface_name(
					$group_int);
				if (!empty($int)) {
					$gwgroup_member =
					    interface_gateway_group_member(get_real_interface($int),
					    $dnsupdate['interface']);
				}
			}
		}
		if (!empty($int) && ($int != $dnsupdate['interface']) && !$gwgroup_member &&
		    ($int != $group_int) && ($int != $friendly_group_int)) {
			continue;
		}
		if (!empty($updatehost) && ($updatehost != $dnsupdate['host'])) {
			continue;
		}

		/* determine interface name */
		$if = get_failover_interface($dnsupdate['interface']);

		/* Determine address to update and default binding address */
		if (isset($dnsupdate['usepublicip'])) {
			$wanip = dyndnsCheckIP($if);
			$bindipv4 = get_interface_ip($if);
		} else {
			$wanip = get_interface_ip($if);
			$bindipv4 = $wanip;
		}
		if (is_stf_interface($dnsupdate['interface'])) { 
			$wanipv6 = get_interface_ipv6($dnsupdate['interface'] . '_stf');
		} else {
			$wanipv6 = get_interface_ipv6($if);
		}
		$bindipv6 = $wanipv6;

		/* Handle non-default interface bindings */
		if ($dnsupdate['updatesource'] == "none") {
			/* When empty, the directive will be omitted. */
			$bindipv4 = "";
			$bindipv6 = "";
		} elseif (!empty($dnsupdate['updatesource'])) {
			/* Find the alternate binding address */
			$bindipv4 = get_interface_ip($dnsupdate['updatesource']);
			if (is_stf_interface($dnsupdate['interface'])) { 
				$bindipv6 = get_interface_ipv6($dnsupdate['updatesource'] . '_stf');
			} else {
				$bindipv6 = get_interface_ipv6($dnsupdate['updatesource']);
			}
		}

		/* Handle IPv4/IPv6 selection for the update source interface/VIP */
		switch ($dnsupdate['updatesourcefamily']) {
			case "inet":
				$bindip = $bindipv4;
				break;
			case "inet6":
				$bindip = $bindipv6;
				break;
			case "":
			default:
				/* Try IPv4 first, if that is empty, try IPv6. */
				/* Only specify the address if it's present, otherwise omit. */
				if (!empty($bindipv4)) {
					$bindip = $bindipv4;
				} elseif (!empty($bindipv6)) {
					$bindip = $bindipv6;
				}
				break;
		}

		$cacheFile = $g['conf_path'] .
		    "/dyndns_{$dnsupdate['interface']}_rfc2136_" .
		    escapeshellarg($dnsupdate['host']) .
		    "_{$dnsupdate['server']}.cache";
		$cacheFilev6 = $g['conf_path'] .
		    "/dyndns_{$dnsupdate['interface']}_rfc2136_" .
		    escapeshellarg($dnsupdate['host']) .
		    "_{$dnsupdate['server']}_v6.cache";
		$currentTime = time();

		if (!$wanip && !$wanipv6) {
			continue;
		}

		$keyname = $dnsupdate['keyname'];
		/* trailing dot */
		if (substr($keyname, -1) != ".") {
			$keyname .= ".";
		}

		$hostname = $dnsupdate['host'];
		/* trailing dot */
		if (substr($hostname, -1) != ".") {
			$hostname .= ".";
		}

		/* write key file */
		$algorithm = empty($dnsupdate['keyalgorithm']) ? 'hmac-md5' : $dnsupdate['keyalgorithm'];
		$upkey = <<<EOD
key "{$keyname}" {
	algorithm {$algorithm};
	secret "{$dnsupdate['keydata']}";
};

EOD;
		@file_put_contents("{$g['varetc_path']}/nsupdatekey{$i}", $upkey);

		/* generate update instructions */
		$upinst = "";
		if (!empty($dnsupdate['server'])) {
			$upinst .= "server {$dnsupdate['server']}\n";
		}

		if (!empty($dnsupdate['zone'])) {
			$upinst .= "zone {$dnsupdate['zone']}\n";
		}

		$cachedipv4 = '';
		$cacheTimev4 = 0;
		if (file_exists($cacheFile)) {
			list($cachedipv4, $cacheTimev4) = explode("|",
			    file_get_contents($cacheFile));
		}
		$cachedipv6 = '';
		$cacheTimev6 = 0;
		if (file_exists($cacheFilev6)) {
			list($cachedipv6, $cacheTimev6) = explode("|",
			    file_get_contents($cacheFilev6));
		}

		// 25 Days
		$maxCacheAgeSecs = 25 * 24 * 60 * 60;
		$need_update = false;

		/* Update IPv4 if we have it. */
		if (is_ipaddrv4($wanip) && $dnsupdate['recordtype'] != "AAAA") {
			if (($wanip != $cachedipv4) || $forced ||
			    (($currentTime - $cacheTimev4) > $maxCacheAgeSecs)) {
				$upinst .= "update delete " .
				    "{$dnsupdate['host']}. A\n";
				$upinst .= "update add {$dnsupdate['host']}. " .
				    "{$dnsupdate['ttl']} A {$wanip}\n";
				if (!empty($bindip)) {
					$upinst .= "local {$bindip}\n";
				}
				$need_update = true;
			} else {
				log_error(sprintf(gettext(
				    "phpDynDNS: Not updating %s A record because the IP address has not changed."),
				    $dnsupdate['host']));
			}
		} else {
			@unlink($cacheFile);
			unset($cacheFile);
		}

		/* Update IPv6 if we have it. */
		if (is_ipaddrv6($wanipv6) && $dnsupdate['recordtype'] != "A") {
			if (($wanipv6 != $cachedipv6) || $forced ||
			    (($currentTime - $cacheTimev6) > $maxCacheAgeSecs)) {
				$upinst .= "update delete " .
				    "{$dnsupdate['host']}. AAAA\n";
				$upinst .= "update add {$dnsupdate['host']}. " .
				    "{$dnsupdate['ttl']} AAAA {$wanipv6}\n";
				$need_update = true;
			} else {
				log_error(sprintf(gettext(
				    "phpDynDNS: Not updating %s AAAA record because the IPv6 address has not changed."),
				    $dnsupdate['host']));
			}
		} else {
			@unlink($cacheFilev6);
			unset($cacheFilev6);
		}

		$upinst .= "\n";	/* mind that trailing newline! */

		if (!$need_update) {
			continue;
		}

		@file_put_contents("{$g['varetc_path']}/nsupdatecmds{$i}", $upinst);
		unset($upinst);
		/* invoke nsupdate */
		$cmd = "/usr/local/bin/nsupdate -k {$g['varetc_path']}/nsupdatekey{$i}";

		if (isset($dnsupdate['usetcp'])) {
			$cmd .= " -v";
		}

		$cmd .= " {$g['varetc_path']}/nsupdatecmds{$i}";

		if (mwexec($cmd) == 0) {
			if (!empty($cacheFile)) {
				@file_put_contents($cacheFile,
				    "{$wanip}|{$currentTime}");
				log_error(sprintf(gettext(
				    'phpDynDNS: updating cache file %1$s: %2$s'),
				    $cacheFile, $wanip));
				$notify_text .= sprintf(gettext(
				    'DynDNS updated IP Address (A) for %1$s on %2$s (%3$s) to %4$s'),
				    $dnsupdate['host'],
				    convert_real_interface_to_friendly_descr($if),
				    $if, $wanip) . "\n";
			}
			if (!empty($cacheFilev6)) {
				@file_put_contents($cacheFilev6,
				    "{$wanipv6}|{$currentTime}");
				log_error(sprintf(gettext(
				    'phpDynDNS: updating cache file %1$s: %2$s'),
				    $cacheFilev6, $wanipv6));
				$notify_text .= sprintf(gettext(
				    'DynDNS updated IPv6 Address (AAAA) for %1$s on %2$s (%3$s) to %4$s'),
				    $dnsupdate['host'],
				    convert_real_interface_to_friendly_descr($if),
				    $if, $wanipv6) . "\n";
			}
		} else {
			if (!empty($cacheFile)) {
				log_error(sprintf(gettext(
				    'phpDynDNS: ERROR while updating IP Address (A) for %1$s (%2$s)'),
				    $dnsupdate['host'], $wanip));
			}
			if (!empty($cacheFilev6)) {
				log_error(sprintf(gettext(
				    'phpDynDNS: ERROR while updating IP Address (AAAA) for %1$s (%2$s)'),
				    $dnsupdate['host'], $wanipv6));
			}
		}
		unset($cmd);
	}

	if (!empty($notify_text)) {
		notify_all_remote($notify_text);
	}

	return 0;
}

/* configure cron service */
function configure_cron() {
	global $g, $config;

	/* preserve existing crontab entries */
	$crontab_contents = file("/etc/crontab", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	for ($i = 0; $i < count($crontab_contents); $i++) {
		$cron_item = &$crontab_contents[$i];
		if (strpos($cron_item, "# pfSense specific crontab entries") !== false) {
			array_splice($crontab_contents, $i - 1);
			break;
		}
	}
	$crontab_contents = implode("\n", $crontab_contents) . "\n";


	if (is_array($config['cron']['item'])) {
		$crontab_contents .= "#\n";
		$crontab_contents .= "# pfSense specific crontab entries\n";
		$crontab_contents .= "# " .gettext("Created:") . " " . date("F j, Y, g:i a") . "\n";
		$crontab_contents .= "#\n";

		if (isset($config['system']['proxyurl']) && !empty($config['system']['proxyurl'])) {
			$http_proxy = $config['system']['proxyurl'];
			if (isset($config['system']['proxyport']) && !empty($config['system']['proxyport'])) {
				$http_proxy .= ':' . $config['system']['proxyport'];
			}
			$crontab_contents .= "HTTP_PROXY={$http_proxy}";

			if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
				$crontab_contents .= "HTTP_PROXY_AUTH=basic:*:{$config['system']['proxyuser']}:{$config['system']['proxypass']}";
			}
		}

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
		$crontab_contents .= "# " . gettext("If done so, this file must be terminated with a blank line (e.g. new line)") . "\n";
		$crontab_contents .= "#\n\n";
	}

	/* please maintain the newline at the end of file */
	file_put_contents("/etc/crontab", $crontab_contents);
	unset($crontab_contents);

	/* make sure that cron is running and start it if it got killed somehow */
	if (!is_process_running("cron")) {
		exec("cd /tmp && /usr/sbin/cron -s 2>/dev/null");
	} else {
	/* do a HUP kill to force sync changes */
		sigkillbypid("{$g['varrun_path']}/cron.pid", "HUP");
	}

}

function upnp_action ($action) {
	global $g, $config;
	switch ($action) {
		case "start":
			if (file_exists('/var/etc/miniupnpd.conf')) {
				@unlink("{$g['varrun_path']}/miniupnpd.pid");
				mwexec_bg("/usr/local/sbin/miniupnpd -f /var/etc/miniupnpd.conf -P {$g['varrun_path']}/miniupnpd.pid");
			}
			break;
		case "stop":
			killbypid("{$g['varrun_path']}/miniupnpd.pid");
			while ((int)exec("/bin/pgrep -a miniupnpd | wc -l") > 0) {
				mwexec('/usr/bin/killall miniupnpd 2>/dev/null', true);
			}
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

	if (!isset($config['installedpackages']['miniupnpd']['config'])) {
		return;
	}

	if ($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
		echo gettext("Starting UPnP service... ");
		require_once('/usr/local/pkg/miniupnpd.inc');
		sync_package_miniupnpd();
		echo "done.\n";
	}
}

function install_cron_job($command, $active = false, $minute = "0", $hour = "*", $monthday = "*", $month = "*", $weekday = "*", $who = "root", $write_config = true) {
	global $config, $g;

	$is_installed = false;
	$cron_changed = true;
	$change_message = "";

	if (!is_array($config['cron'])) {
		$config['cron'] = array();
	}
	if (!is_array($config['cron']['item'])) {
		$config['cron']['item'] = array();
	}

	$x = 0;
	foreach ($config['cron']['item'] as $item) {
		if (strstr($item['command'], $command)) {
			$is_installed = true;
			break;
		}
		$x++;
	}

	if ($active) {
		$cron_item = array();
		$cron_item['minute'] = $minute;
		$cron_item['hour'] = $hour;
		$cron_item['mday'] = $monthday;
		$cron_item['month'] = $month;
		$cron_item['wday'] = $weekday;
		$cron_item['who'] = $who;
		$cron_item['command'] = $command;
		if (!$is_installed) {
			$config['cron']['item'][] = $cron_item;
			$change_message = "Installed cron job for %s";
		} else {
			if ($config['cron']['item'][$x] == $cron_item) {
				$cron_changed = false;
			} else {
				$config['cron']['item'][$x] = $cron_item;
				$change_message = "Updated cron job for %s";
			}
		}
	} else {
		if ($is_installed == true) {
			array_splice($config['cron']['item'], $x, 1);
			$change_message = "Removed cron job for %s";
		} else {
			$cron_changed = false;
		}
	}

	if ($cron_changed) {
		/* Optionally write the configuration if this function made changes.
		 * Performing a write_config() in this way can have unintended side effects. See #7146
		 * Base system instances of this function do not need to write, packages may.
		 */
		if ($write_config) {
			write_config(sprintf(gettext($change_message), $command));
		}
		configure_cron();
	}
}

?>
