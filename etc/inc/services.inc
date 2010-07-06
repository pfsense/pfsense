<?php
/* $Id$ */
/*
	services.inc
	part of m0n0wall (http://m0n0.ch/wall)

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

function load_balancer_use_sticky() {
	global $config, $g;
	if (isset ($config['system']['lb_use_sticky']))
		touch("/var/etc/use_pf_pool__stickyaddr");
	else
		unlink_if_exists("/var/etc/use_pf_pool__stickyaddr");
}

function services_dhcpd_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dhcpd_configure($if) being called $mt\n";
	}

	/* if OLSRD is enabled, allow WAN to house DHCP. */
	if($config['installedpackages']['olsrd'])
		foreach($config['installedpackages']['olsrd']['config'] as $olsrd)
				if($olsrd['enable'])
					$is_olsr_enabled = true;

	/* configure DHCPD chroot */
	$fd = fopen("/tmp/dhcpd.sh","w");
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
	mwexec("/bin/sh /tmp/dhcpd.sh");

	/* kill any running dhcpd */
	if(is_process_running("dhcpd"))
		mwexec("killall dhcpd", true);

	$syscfg = $config['system'];
	$dhcpdcfg = $config['dhcpd'];

	/* DHCP enabled on any interfaces? */
	$dhcpdenable = false;
	if(is_array($dhcpdcfg))
		foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
			if (isset($dhcpifconf['enable']) &&
				(($dhcpif == "lan") ||
				(isset($config['interfaces'][$dhcpif]['enable']) &&
				$config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
				$dhcpdenable = true;
			if (isset($dhcpifconf['enable']) &&
				(($dhcpif == "wan") || (isset($config['interfaces'][$dhcpif]['enable']) &&
				$config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
				$dhcpdenable = true;
		}

	if (!$dhcpdenable)
		return 0;

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



	$dhcpdconf = <<<EOD
option domain-name "{$syscfg['domain']}";
default-lease-time 7200;
max-lease-time 86400;
authoritative;
log-facility local7;
ddns-update-style none;
one-lease-per-client true;
deny duplicates;

EOD;

	$dhcpdifs = array();

	/*    loop through and deterimine if we need to setup
	 *    failover peer "bleh" entries
	 */
	$dhcpnum = 0;
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		if($dhcpifconf['failover_peerip'] <> "") {
			/*
			 *    yep, failover peer is defined.
			 *    does it match up to a defined vip?
			 */
			$skew = 110;
			$a_vip = &$config['virtualip']['vip'];
			if(is_array($a_vip)) {
				foreach ($a_vip as $vipent) {
					$int = guess_interface_from_ip($dhcpifconf['failover_peerip']);
					$intip = find_interface_ip($int);
					$real_dhcpif = convert_friendly_interface_to_real_interface_name($dhcpif);
					if($int == $real_dhcpif) {
						/* this is the interface! */
						if($vipent['advskew'] < "20")
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

		$ifcfg = $config['interfaces'][$dhcpif];

		if (!isset($dhcpifconf['enable']) ||
			($ifcfg['ipaddr'] == "dhcp") ||
			(($dhcpif != "lan") &&
			(!isset($ifcfg['enable']) || !$ifcfg['if'] || $ifcfg['bridge'])))
			continue;

		if($dhcpif == "lan" && $ifcfg['bridge'])
			log_error("NOTE: DHCP Server on LAN is enabled.");

		$subnet = gen_subnet($ifcfg['ipaddr'], $ifcfg['subnet']);
		$subnetmask = gen_subnet_mask($ifcfg['subnet']);

		if($is_olsr_enabled == true)
			if($dhcpifconf['netmask'])
				$subnetmask = gen_subnet_mask($dhcpifconf['netmask']);

		$dnscfg = "";

		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
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
			$dnscfg .= "	option domain-name-servers " . $ifcfg['ipaddr'] . ";";
		} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
			$dnscfg .= "	option domain-name-servers " . join(",", $syscfg['dnsserver']) . ";";
		}

		$dhcpdconf .= "subnet $subnet netmask $subnetmask {\n";
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
		   $dhcpdconf .= "		deny unknown clients;\n";

		if ($dhcpifconf['gateway'])
			$routers = $dhcpifconf['gateway'];
		else
			$routers = $ifcfg['ipaddr'];

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

		if ($dhcpifconf['defaultleasetime'])
			$dhcpdconf .= "	default-lease-time {$dhcpifconf['defaultleasetime']};\n";
		if ($dhcpifconf['maxleasetime'])
			$dhcpdconf .= "	max-lease-time {$dhcpifconf['maxleasetime']};\n";

		if (is_array($dhcpifconf['winsserver']) && $dhcpifconf['winsserver'][0]) {
			$dhcpdconf .= "	option netbios-name-servers " . join(",", $dhcpifconf['winsserver']) . ";\n";
			$dhcpdconf .= "	option netbios-node-type 8;\n";
		}

		if (is_array($dhcpifconf['ntpserver']) && $dhcpifconf['ntpserver'][0])
			$dhcpdconf .= "	option ntp-servers " . join(",", $dhcpifconf['ntpserver']) . ";\n";

		if(isset($dhcpifconf['netboot'])) {
			if (($dhcpifconf['next-server'] <> "") && ($dhcpifconf['filename'] <> "")) {
				$dhcpdconf .= "	next-server {$dhcpifconf['next-server']};\n";
				$dhcpdconf .= "	filename \"{$dhcpifconf['filename']}\";\n";
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

				if ($sm['hostname'])
					$dhcpdconf .= " option host-name {$sm['hostname']};\n";
				
				$dhcpdconf .= "}\n";
				$i++;
			}
		}

		$dhcpdifs[] = $ifcfg['if'];
	}

	fwrite($fd, $dhcpdconf);
	fclose($fd);

	/* create an empty leases database */
	touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");

	/* fire up dhcpd in a chroot */
	mwexec("/usr/local/sbin/dhcpd -user dhcpd -group _dhcp -chroot {$g['dhcpd_chroot_path']} -cf {$g['dhcpd_chroot_path']}/etc/dhcpd.conf " .
		join(" ", $dhcpdifs));

	if ($g['booting']) {
		print "done.\n";
	}

	return 0;
}

function interfaces_staticarp_configure($if) {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "interfaces_staticarp_configure($if) being called $mt\n";
	}

        $ifcfg = $config['interfaces'][$if];

        /* Enable staticarp, if enabled */
        if(isset($config['dhcpd'][$if]['staticarp'])) {
                mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " staticarp " );
                mwexec("/usr/sbin/arp -ad > /dev/null 2>&1 ");
                if (is_array($config['dhcpd'][$if]['staticmap'])) {

                        foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
                                mwexec("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));
								log_error("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));
                        }

                }
        } else {
                mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " -staticarp " );
                mwexec("/usr/sbin/arp -da > /dev/null 2>&1 ");
        }

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

	$dhcrelaycfg = $config['dhcrelay'];

	/* DHCPRelay enabled on any interfaces? */
	$dhcrelayenable = false;
	if(is_array($dhcrelaycfg)) {
		foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
			if (isset($dhcrelayifconf['enable']) &&
				(($dhcrelayif == "lan") ||
				(isset($config['interfaces'][$dhcrelayif]['enable']) &&
				$config['interfaces'][$dhcrelayif]['if'] && (!$config['interfaces'][$dhcrelayif]['bridge']))))
				$dhcrelayenable = true;
		}
	}

	if (!$dhcrelayenable)
		return 0;

	if ($g['booting'])
		echo "Starting DHCP relay service...";
	else
		sleep(1);

	$dhcrelayifs = array();
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {

		$ifcfg = $config['interfaces'][$dhcrelayif];

		if (!isset($dhcrelayifconf['enable']) ||
			(($dhcrelayif != "lan") &&
			(!isset($ifcfg['enable']) || !$ifcfg['if'] || $ifcfg['bridge'])))
			continue;

		$dhcrelayifs[] = $ifcfg['if'];
	}

	/* In order for the relay to work, it needs to be active on the
	   interface in which the destination server sits */
	foreach ($config['interfaces'] as $ifname) {
		$subnet = $ifname['ipaddr'] . "/" . $ifname['subnet'];
		if (ip_in_subnet($dhcrelaycfg['server'],$subnet))
			$destif = $ifname['if'];
	}

	if (!isset($destif))
		$destif = $config['interfaces']['wan']['if'];

	$dhcrelayifs[] = $destif;
	$dhcrelayifs = array_unique($dhcrelayifs);

	/* fire up dhcrelay */
	$cmd = "/usr/local/sbin/dhcrelay -i " .  join(" -i ", $dhcrelayifs);

	if (isset($dhcrelaycfg['agentoption']))
		$cmd .=  " -a -m replace";

	$cmd .= " {$dhcrelaycfg['server']}";
	mwexec($cmd);

	if (!$g['booting']) {
		/* set the reload filter dity flag */
		touch("{$g['tmp_path']}/filter_dirty");
	}

	return 0;
}

function services_dyndns_reset() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_reset() being called $mt\n";
	}

	if (file_exists("{$g['vardb_path']}/ez-ipupdate.cache")) {
		conf_mount_rw();
		unlink("{$g['vardb_path']}/ez-ipupdate.cache");
		conf_mount_ro();
	}

	if (file_exists("{$g['conf_path']}/ez-ipupdate.cache")) {
		conf_mount_rw();
		unlink("{$g['conf_path']}/ez-ipupdate.cache");
		conf_mount_ro();
	}
	
	if (file_exists("{$g['conf_path']}/dyndns.cache")) {
		conf_mount_rw();
		unlink("{$g['conf_path']}/dyndns.cache");
		conf_mount_ro();
	}

	return 0;
}

function services_dyndns_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_configure() being called $mt\n";
	}

	$dyndnscfg = $config['dyndns'];
	$wancfg = $config['interfaces']['wan'];

	if (isset($dyndnscfg['enable'])) {

		if ($g['booting']) {
			echo "Starting DynDNS client...";
			if(isset($config['system']['use_old_dyndns'])) {
				echo " [Using ez-ipupdate] ";
				services_dyndns_configure_old();
				return;
			}
		} else {
			sleep(1);
			if(isset($config['system']['use_old_dyndns'])) {
				services_dyndns_configure_old();
				return;
			}
		}

		/* load up the dyndns.class */
		require_once("dyndns.class");

		log_error("DynDns: Running updatedns()");

		/* determine WAN interface name */
		$wanif = get_real_wan_interface();
		/* get ip */
		$ip = find_interface_ip($wanif);

		$dns = new updatedns($dnsService = $config['dyndns']['type'],
							 $dnsHost = $config['dyndns']['host'],
							 $dnsUser = $config['dyndns']['username'],
							 $dnsPass = $config['dyndns']['password'],
							 $dnsWilcard = $config['dyndns']['wildcard'],
							 $dnsMX = $config['dyndns']['mx']);

		if ($g['booting'])
			echo "done.\n";
	}

	return 0;
}

function services_dyndns_configure_old() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dyndns_configure_old() being called $mt\n";
	}

        /* kill any running ez-ipupdate */
        /* ez-ipupdate needs SIGQUIT instead of SIGTERM */
        sigkillbypid("{$g['varrun_path']}/ez-ipupdate.pid", "QUIT");

        $dyndnscfg = $config['dyndns'];
        $wancfg = $config['interfaces']['wan'];

        if (isset($dyndnscfg['enable'])) {

                if ($g['booting'])
                        echo "Starting DynDNS client...";
                else
                        sleep(1);

                /* determine WAN interface name */
                $wanif = get_real_wan_interface();

                /* write ez-ipupdate.conf */
                $fd = fopen("{$g['varetc_path']}/ez-ipupdate.conf", "w");
                if (!$fd) {
                        printf("Error: cannot open ez-ipupdate.conf in services_dyndns_configure().\n");
                        return 1;
                }

                $ezipupdateconf = <<<EOD
service-type={$dyndnscfg['type']}
user={$dyndnscfg['username']}:{$dyndnscfg['password']}
host={$dyndnscfg['host']}
interface={$wanif}
max-interval=2073600
pid-file={$g['varrun_path']}/ez-ipupdate.pid
cache-file={$g['vardb_path']}/ez-ipupdate.cache
execute=/etc/rc.dyndns.storecache
daemon

EOD;

                /* enable server[:port]? */
                if ($dyndnscfg['server']) {
                        if ($dyndnscfg['port'])
                                $ezipupdateconf .= "server={$dyndnscfg['server']}:{$dyndnscfg['port']}\n";
                        else
                                $ezipupdateconf .= "server={$dyndnscfg['server']}\n";
                }

                /* enable MX? */
                if ($dyndnscfg['mx']) {
                        $ezipupdateconf .= "mx={$dyndnscfg['mx']}\n";
                }

                /* enable wildcards? */
                if (isset($dyndnscfg['wildcard'])) {
                        $ezipupdateconf .= "wildcard\n";
                }

                fwrite($fd, $ezipupdateconf);
                fclose($fd);

                /* if we're booting, copy the cache file from /conf */
                if ($g['booting']) {
                        if (file_exists("{$g['conf_path']}/ez-ipupdate.cache")) {
                                copy("{$g['conf_path']}/ez-ipupdate.cache", "{$g['vardb_path']}/ez-ipupdate.cache");
                       }
                }

                /* run ez-ipupdate */
                mwexec("/usr/local/bin/ez-ipupdate -c {$g['varetc_path']}/ez-ipupdate.conf");

                if ($g['booting'])
                        echo "done\n";
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

			$args .= " -l {$g['dhcpd_chroot_path']}/var/db/dhcpd.leases" .
				" -s {$config['system']['domain']}";
		}

                if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
                        foreach($config['dnsmasq']['domainoverrides'] as $override) {
                                $args .= ' --server=/' . $override['domain'] . '/' . $override['ip'];
                        }
                }

		/* suppose that dnsmasq handles our domain and don't send
		requests for our local domain to upstream servers */
		//if (!empty($config['system']['domain'])) {
		//	$args .= sprintf(' --local=/%s/', $config['system']['domain']);
		//}

		/* run dnsmasq */
		mwexec("/usr/local/sbin/dnsmasq --stop-dns-rebind --all-servers {$args}");

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

		if(isset($config['snmpd']['bindlan'])) {
			$bind_to_ip = $config['interfaces']['lan']['ipaddr'];
		} else {
			$bind_to_ip = "0.0.0.0";
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

function services_proxyarp_configure() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_proxyarp_configure() being called $mt\n";
	}

	/* kill any running choparp */
	killbyname("choparp");

	if (isset($config['virtualip']) && is_array($config['virtualip']['vip'])) {
		$paa = array();

		/* group by interface */
		foreach ($config['virtualip']['vip'] as $vipent) {
			if ($vipent['mode'] === "proxyarp") {
				if ($vipent['interface'])
					$if = $vipent['interface'];
				else
					$if = "wan";

				if (!is_array($paa[$if]))
					$paa[$if] = array();

				$paa[$if][] = $vipent;
			}
		}

		if (count($paa))
		foreach ($paa as $paif => $paents) {
			if ($paif == "wan" && !(is_ipaddr($config['interfaces']['wan']['ipaddr']) ||
                                       ($config['interfaces']['wan']['ipaddr'] == "dhcp") ||
                                       ($config['interfaces']['wan']['ipaddr'] == "bigpond")))
                               continue;

			$args = $config['interfaces'][$paif]['if'] . " auto";

			foreach ($paents as $paent) {

				if (isset($paent['subnet']))
					$args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
				else if (isset($paent['range']))
					$args .= " " . escapeshellarg($paent['range']['from'] . "-" .
						$paent['range']['to']);
			}

			mwexec_bg("/usr/local/sbin/choparp " . $args);
		}
	}
}

function services_dnsupdate_process() {
	global $config, $g;
	if(isset($config['system']['developerspew'])) {
		$mt = microtime();
		echo "services_dnsupdate_process() being called $mt\n";
	}

	/* Dynamic DNS updating active? */
	if (isset($config['dnsupdate']['enable'])) {

		$wanip = get_current_wan_address();
		if ($wanip) {

			$keyname = $config['dnsupdate']['keyname'];
			/* trailing dot */
			if (substr($keyname, -1) != ".")
				$keyname .= ".";

			$hostname = $config['dnsupdate']['host'];
			/* trailing dot */
			if (substr($hostname, -1) != ".")
				$hostname .= ".";

			/* write private key file
			   this is dumb - public and private keys are the same for HMAC-MD5,
			   but nsupdate insists on having both */
			$fd = fopen("{$g['varetc_path']}/K{$keyname}+157+00000.private", "w");
			$privkey .= <<<EOD
Private-key-format: v1.2
Algorithm: 157 (HMAC)
Key: {$config['dnsupdate']['keydata']}

EOD;
			fwrite($fd, $privkey);
			fclose($fd);

			/* write public key file */
			if ($config['dnsupdate']['keytype'] == "zone") {
				$flags = 257;
				$proto = 3;
			} else if ($config['dnsupdate']['keytype'] == "host") {
				$flags = 513;
				$proto = 3;
			} else if ($config['dnsupdate']['keytype'] == "user") {
				$flags = 0;
				$proto = 2;
			}

			$fd = fopen("{$g['varetc_path']}/K{$keyname}+157+00000.key", "w");
			fwrite($fd, "{$keyname} IN KEY {$flags} {$proto} 157 {$config['dnsupdate']['keydata']}\n");
			fclose($fd);

			/* generate update instructions */
			$upinst = "";
			if ($config['dnsupdate']['server'])
				$upinst .=  "server {$config['dnsupdate']['server']}\n";
			$upinst .= "update delete {$config['dnsupdate']['host']} A\n";
			$upinst .= "update add {$config['dnsupdate']['host']} {$config['dnsupdate']['ttl']} A {$wanip}\n";
			$upinst .= "\n";	/* mind that trailing newline! */

			$fd = fopen("{$g['varetc_path']}/nsupdatecmds", "w");
			fwrite($fd, $upinst);
			fclose($fd);

			/* invoke nsupdate */
			$cmd = "/usr/bin/nsupdate -k {$g['varetc_path']}/K{$keyname}+157+00000.key";
			if (isset($config['dnsupdate']['usetcp']))
				$cmd .= " -v";
			$cmd .= " {$g['varetc_path']}/nsupdatecmds";

			mwexec_bg($cmd);
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
		if($olsr_enable <> "on")
			return;
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

	if(is_process_running("olsrd"))
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
	$crontab_contents = file_get_contents("/etc/crontab");
	$crontab_contents_a = split("\n", $crontab_contents);
	
	for ($i = 0; $i < count($crontab_contents_a); $i++) {
		$item =& $crontab_contents_a[$i];
		if (strpos($item, "# pfSense specific crontab entries") !== false) {
			array_splice($crontab_contents_a, $i - 1);
			break;
		}
	}
	$crontab_contents = implode("\n", $crontab_contents_a) . "\n";
	
	
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
	
	if (!$g['booting'])
		conf_mount_ro();
}

function upnp_action ($action) {
	switch($action) {
		case "start":
			if(file_exists('/var/etc/miniupnpd.conf'))
				mwexec_bg('/usr/local/sbin/miniupnpd -f /var/etc/miniupnpd.conf');
			break;
		case "stop":
			while((int)exec("pgrep miniupnpd | wc -l") > 0)
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
	global $config, $g;
	if($config['installedpackages']['miniupnpd']['config'][0]['enable']) {
		if($g['booting']) {
			echo "Starting UPnP service...";
			include('/usr/local/pkg/miniupnpd.inc');
			sync_package_miniupnpd();
			echo "done.\n";
		}
		else {
			upnp_action('start');
		}
	}
}

?>
