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
require_once("dyndns.class");

function services_dhcpd_configure() {
	global $config, $g;

	/* kill any running dhcpd */
	killbypid("{$g['varrun_path']}/dhcpd.pid");

	$syscfg = $config['system'];
	$dhcpdcfg = $config['dhcpd'];

	/* DHCP enabled on any interfaces? */
	$dhcpdenable = false;
	foreach ($dhcpdcfg as $dhcpif => $dhcpifconf) {
		if (isset($dhcpifconf['enable']) &&
			(($dhcpif == "lan") ||
			(isset($config['interfaces'][$dhcpif]['enable']) &&
			$config['interfaces'][$dhcpif]['if'] && (!$config['interfaces'][$dhcpif]['bridge']))))
			$dhcpdenable = true;
	}

	if (!$dhcpdenable)
		return 0;

	if ($g['booting'])
		echo "Starting DHCP service... ";
	else
		sleep(1);

	/* write dhcpd.conf */
	$fd = fopen("{$g['varetc_path']}/dhcpd.conf", "w");
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
			$skew = 1;
			$a_vip = &$config['virtualip']['vip'];
			foreach ($a_vip as $vipent) {
				$int = guess_interface_from_ip($dhcpifconf['failover_peerip']);
				$intip = find_interface_ip($int);
				$real_dhcpif = convert_friendly_interface_to_real_interface_name($dhcpif);
				if($int == $real_dhcpif) {
					/* this is the interface! */
					if($vipent['advskew'] == "0")
						$skew = 0;
				}
			}
			if($skew > 0) {
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
  max-response-delay 60;
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
			(($dhcpif != "lan") &&
			(!isset($ifcfg['enable']) || !$ifcfg['if'] || $ifcfg['bridge'])))
			continue;

		$subnet = gen_subnet($ifcfg['ipaddr'], $ifcfg['subnet']);
		$subnetmask = gen_subnet_mask($ifcfg['subnet']);

		$dnscfg = "";

		if ($dhcpifconf['domain']) {
			$dnscfg .= "	option domain-name \"{$dhcpifconf['domain']}\";\n";
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
		
		if($dhcpifconf['failover_peerip'] <> "") 
			$dhcpdconf .= "	deny dynamic bootp clients;\n";
		
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

		if (is_array($dhcpifconf['winsserver']) && $dhcpifconf['winsserver'][0]) {
			$dhcpdconf .= "	option domain-name-servers " . join(",", $dhcpifconf['dns']) . ";\n";
		}

		if ($dhcpifconf['next-server'])
			$dhcpdconf .= "	next-server {$dhcpifconf['next-server']};\n";
		if ($dhcpifconf['filename'])
			$dhcpdconf .= "	filename \"{$dhcpifconf['filename']}\";\n";

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

				$dhcpdconf .= "}\n";
				$i++;
			}
		}

		$dhcpdifs[] = $ifcfg['if'];
	}

	fwrite($fd, $dhcpdconf);
	fclose($fd);

	/* create an empty leases database */
	touch("{$g['vardb_path']}/dhcpd.leases");

	/* fire up dhcpd */
	mwexec("/usr/local/sbin/dhcpd -cf {$g['varetc_path']}/dhcpd.conf " .
		join(" ", $dhcpdifs));

	if ($g['booting']) {
                print "done.\n";
	}

	return 0;
}

function interfaces_staticarp_configure($if) {
        global $config, $g;
        
        $ifcfg = $config['interfaces'][$if];

        /* Enable staticarp, if enabled */
        if(isset($config['dhcpd'][$if]['staticarp'])) {
                mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " staticarp " );
                mwexec("/usr/sbin/arp -ad > /dev/null 2>&1 ");
                if (is_array($config['dhcpd'][$if]['staticmap'])) {

                        foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
                                mwexec("/usr/sbin/arp -s " . escapeshellarg($arpent['ipaddr']) . " " . escapeshellarg($arpent['mac']));

                        }
                        
                }
        } else {
                mwexec("/sbin/ifconfig " . escapeshellarg($ifcfg['if']) . " -staticarp " );
                mwexec("/usr/sbin/arp -ad > /dev/null 2>&1 ");
        }

        return 0;
}

function services_dhcrelay_configure() {
	global $config, $g;

	/* kill any running dhcrelay */
	killbypid("{$g['varrun_path']}/dhcrelay.pid");

	$dhcrelaycfg = $config['dhcrelay'];

	/* DHCPRelay enabled on any interfaces? */
	$dhcrelayenable = false;
	foreach ($dhcrelaycfg as $dhcrelayif => $dhcrelayifconf) {
		if (isset($dhcrelayifconf['enable']) &&
			(($dhcrelayif == "lan") ||
			(isset($config['interfaces'][$dhcrelayif]['enable']) &&
			$config['interfaces'][$dhcrelayif]['if'] && (!$config['interfaces'][$dhcrelayif]['bridge']))))
			$dhcrelayenable = true;
	}

	if (!$dhcrelayenable)
		return 0;

	if ($g['booting'])
		echo "Starting DHCP relay service... ";
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

	if (file_exists("{$g['vardb_path']}/ez-ipupdate.cache")) {
		unlink("{$g['vardb_path']}/ez-ipupdate.cache");
	}

	if (file_exists("{$g['conf_path']}/ez-ipupdate.cache")) {
		conf_mount_rw();
		unlink("{$g['conf_path']}/ez-ipupdate.cache");
		conf_mount_ro();
	}

	return 0;
}

function services_dyndns_configure() {
	global $config, $g;

	$dyndnscfg = $config['dyndns'];
	$wancfg = $config['interfaces']['wan'];

	if (isset($dyndnscfg['enable'])) {

		if ($g['booting'])
			echo "Starting DynDNS client... ";
		else
			sleep(1);

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

function services_dnsmasq_configure() {
	global $config, $g;

	/* kill any running dnsmasq */
	sigkillbypid("{$g['varrun_path']}/dnsmasq.pid", "TERM");

	if (isset($config['dnsmasq']['enable'])) {

		if ($g['booting'])
			echo "Starting DNS forwarder... ";
		else
			sleep(1);

		/* generate hosts file */
		system_hosts_generate();

		$args = "";

		if (isset($config['dnsmasq']['regdhcp'])) {

			$args .= " -l {$g['vardb_path']}/dhcpd.leases" .
				" -s {$config['system']['domain']}";
		}

                if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
                        foreach($config['dnsmasq']['domainoverrides'] as $override) {
                                $args .= ' --server=/' . $override['domain'] . '/' . $override['ip'];
                        }
                }

		/* run dnsmasq */
		mwexec("/usr/local/sbin/dnsmasq {$args}");

		if ($g['booting'])
			echo "done.\n";
	}

	if (!$g['booting']) {
		services_dhcpd_configure();
	}

	return 0;
}

function services_snmpd_configure() {
	global $config, $g;

	/* kill any running snmpd */
	sigkillbypid("{$g['varrun_path']}/snmpd.pid", "TERM");

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

		if(is_port( $config['snmpd']['pollport'] )) {
		    $snmpdconf .= <<<EOD
begemotSnmpdPortStatus.0.0.0.0.{$config['snmpd']['pollport']} = 1

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
# config must end with blank line


EOD;
		    }
		}

		fwrite($fd, $snmpdconf);
		fclose($fd);

		/* run bsnmpd */
		mwexec("/usr/sbin/bsnmpd -c {$g['varetc_path']}/snmpd.conf" .
			" -p {$g['varrun_path']}/snmpd.pid");

		if ($g['booting'])
			echo "done.\n";
	}

	return 0;
}

function services_proxyarp_configure() {
	global $config, $g;

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
			$upinst =  "update delete {$config['dnsupdate']['host']} A\n";
			$upinst .= "update add {$config['dnsupdate']['host']} {$config['dnsupdate']['ttl']} A {$wanip}\n";
			$upinst .= "\n";	/* mind that trailing newline! */
			
			$fd = fopen("{$g['varetc_path']}/nsupdatecmds", "w");
			fwrite($fd, $upinst);
			fclose($fd);
			
			/* invoke nsupdate */
			$cmd = "/usr/sbin/nsupdate -k {$g['varetc_path']}:{$keyname}";
			if (isset($config['dnsupdate']['usetcp']))
				$cmd .= " -v";
			$cmd .= " {$g['varetc_path']}/nsupdatecmds";
			
			mwexec_bg($cmd);
		}
	}
	
	return 0;
}

function setup_wireless_olsr($interface) {
	$fd = fopen("{$g['varetc_path']}/{$interface}_olsr.conf", "w");
	$olsr .= <<<EOD
#
# olsr.org OLSR daemon config file
#
# Lines starting with a # are discarded
#
# This file was shipped with olsrd 0.X.X
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

# HNA IPv4 routes
# syntax: netaddr netmask
# Example Internet gateway:
# 0.0.0.0 0.0.0.0

Hna4
{
#   Internet gateway:
#   0.0.0.0      0.0.0.0
#   more entries can be added:
#   192.168.1.0  255.255.255.0
}

# HNA IPv6 routes
# syntax: netaddr prefix
# Example Internet gateway:
Hna6
{
#   Internet gateway:
#   ::              0
#   more entries can be added:
#   fec0:2200:106:: 48
}


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

LinkQualityLevel	2

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


# Olsrd plugins to load
# This must be the absolute path to the file
# or the loader will use the following scheme:
# - Try the paths in the LD_LIBRARY_PATH 
#   environment variable.
# - The list of libraries cached in /etc/ld.so.cache
# - /lib, followed by /usr/lib

# Example plugin entry with parameters:

#LoadPlugin "olsrd_dyn_gw.so.0.3"
#{
    # Here parameters are set to be sent to the
    # plugin. Theese are on the form "key" "value".
    # Parameters ofcause, differs from plugin to plugin.
    # Consult the documentation of your plugin for details.

    # Example: dyn_gw params

    # how often to check for Internet connectivity
    # defaults to 5 secs
#   PlParam     "Interval"   "40"
    
    # if one or more IPv4 addresses are given, do a ping on these in
    # descending order to validate that there is not only an entry in
    # routing table, but also a real internet connection. If any of
    # these addresses could be pinged successfully, the test was
    # succesful, i.e. if the ping on the 1st address was successful,the
    # 2nd won't be pinged
#   PlParam     "Ping"       "141.1.1.1"
#   PlParam     "Ping"       "194.25.2.129"
#}



# Interfaces and their rules
# Omitted options will be set to the
# default values. Multiple interfaces
# can be specified in the same block
# and multiple blocks can be set.

# !!CHANGE THE INTERFACE LABEL(s) TO MATCH YOUR INTERFACE(s)!!
# (eg. wlan0 or eth1):

Interface "{$interface}" 
{

    # IPv4 broadcast address to use. The
    # one usefull example would be 255.255.255.255
    # If not defined the broadcastaddress
    # every card is configured with is used

    # Ip4Broadcast		255.255.255.255

    # IPv6 address scope to use.
    # Must be 'site-local' or 'global'

    # Ip6AddrType		site-local

    # IPv6 multicast address to use when
    # using site-local addresses.
    # If not defined, ff05::15 is used

    # Ip6MulticastSite		ff05::11

    # IPv6 multicast address to use when
    # using global addresses
    # If not defined, ff0e::1 is used

    # Ip6MulticastGlobal	ff0e::1


    # Emission intervals.
    # If not defined, RFC proposed values will
    # be used in most cases.

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
EOD;
	fwrite($fd, $olsr);
	fclose($fd);
	mwexec_bg("/usr/local/sbin/olsrd -f {$g['varetc_path']}/{$interface}_olsr.conf");
}

?>