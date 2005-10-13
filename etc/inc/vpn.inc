<?php
/*
	vpn.inc
	Copyright (C) 2004 Scott Ullrich
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

function vpn_ipsec_failover_configure() {
	global $config, $g;

	$sasyncd_text = "";

	if($config['installedpackages']['sasyncd']['config'] <> "")
		foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
			$enabled = isset($sasyncd['enable']);
			if(!$enabled)
				return;
			if($sasyncd['peerip'] <> "")
				$sasyncd_text .= "peer {$sasyncd['peerip']}\n";
			if($sasyncd['interface'])
				$sasyncd_text .= "carp interface {$sasyncd['interface']}\n";
			if($sasyncd['sharedkey'] <> "")
				$sasyncd_text .= "sharedkey {$sasyncd['sharedkey']}\n";
			if($sasyncd['mode'] <> "")
				$sasyncd_text .= "mode {$sasyncd['mode']}\n";
			if($sasyncd['listenon'] <> "")
				$sasyncd_text .= "listen on {$sasyncd['listenon']}\n";
			if($sasyncd['flushmodesync'] <> "")
				$sasyncd_text .= "flushmode sync {$sasyncd['flushmodesync']}\n";
		}

	$fd = fopen("{$g['varetc_path']}/sasyncd.conf", "w");
	fwrite($fd, $sasyncd_text);
	fclose($fd);
	chmod("{$g['varetc_path']}/sasyncd.conf", 0600);
	
	mwexec("killall sasyncd");
	
	/* launch sasyncd, oh wise one */
	mwexec_bg("/usr/local/sbin/sasyncd -d -v -v -v");
}

function find_last_gif_device() {
        $last_gif_found = -1;
        if (!($fp = popen("/sbin/ifconfig -l", "r"))) return -1;
        $ifconfig_data = fread($fp, 4096);
        pclose($fp);
        $ifconfig_array = split(" ", $ifconfig_data);
        foreach ($ifconfig_array as $ifconfig) {
                ereg("gif(.)", $ifconfig, $regs);
                if($regs[0]) {
                        if($regs[0] > $last_gif_found)
                                $last_gif_found = $regs[1];
                }
        }
        return $last_gif_found;
}

function vpn_ipsec_configure($ipchg = false) {
	global $config, $g;

	if(isset($config['system']['developer'])) {
		if($g['booting'] == true) {
			/* determine if we should load the via padlock module */
			$dmesg_boot = `cat /var/log/dmesg.boot | grep CPU`;
			if(stristr($dmesg_boot, "ACE") == true) {		
				echo "Enabling [VIA Padlock] ...";
				mwexec("/sbin/kldload padlock");
				mwexec("/sbin/sysctl net.inet.ipsec.crypto_support=1");
				mwexec("/usr/sbin/setkey -F");
				mwexec("/usr/sbin/setkey -FP");
				echo " done.\n";
			}
		}
	}

	if(isset($config['ipsec']['preferredoldsa'])) {
		mwexec("/sbin/sysctl net.key.preferred_oldsa=0");
	} else {
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");
	}

	$number_of_gifs = find_last_gif_device();
	for($x=0; $x<$number_of_gifs; $x++) {
		mwexec("/sbin/ifconfig gif" . $x . " delete");
	}

	$curwanip = get_current_wan_address();
	if($config['installedpackages']['sasyncd']['config'] <> "")
		foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
			if($sasyncd['ip'] <> "") 
				$curwanip = $sasyncd['ip'];
		}

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	if ($g['booting']) {
		if (!isset($ipseccfg['enable']))
			return 0;

		echo "Configuring IPsec VPN... ";
	} else {
		/* kill racoon */
		killbypid("{$g['varrun_path']}/racoon.pid");

		/* wait for process to die */
		sleep(2);

		/* send a SIGKILL to be sure */
		sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");
	}

	/* flush SPD and SAD */
	mwexec("/usr/sbin/setkey -FP");
	mwexec("/usr/sbin/setkey -F");

	if (isset($ipseccfg['enable'])) {

		/* fastforwarding is not compatible with ipsec tunnels */
		system("/sbin/sysctl net.inet.ip.fastforwarding=0 >/dev/null 2>&1");

		if (!$curwanip) {
			/* IP address not configured yet, exit */
			if ($g['booting'])
				echo "done\n";
			return 0;
		}

		if ((is_array($ipseccfg['tunnel']) && count($ipseccfg['tunnel'])) ||
				isset($ipseccfg['mobileclients']['enable'])) {

			if (is_array($ipseccfg['tunnel']) && count($ipseccfg['tunnel'])) {

				/* generate spd.conf */
				$fd = fopen("{$g['varetc_path']}/spd.conf", "w");
				if (!$fd) {
					printf("Error: cannot open spd.conf in vpn_ipsec_configure().\n");
					return 1;
				}

				$spdconf = "";

				$spdconf .= "spdadd {$lansa}/{$lansn} {$lanip}/32 any -P in none;\n";
				$spdconf .= "spdadd {$lanip}/32 {$lansa}/{$lansn} any -P out none;\n";

				foreach ($ipseccfg['tunnel'] as $tunnel) {

					if (isset($tunnel['disabled']))
						continue;

					$ep = vpn_endpoint_determine($tunnel, $curwanip);
					if (!$ep)
						continue;

					vpn_localnet_determine($tunnel['local-subnet'], $sa, $sn);

					if(is_domain($tunnel['remote-gateway'])) {
						$tmp = gethostbyname($tunnel['remote-gateway']);
						if($tmp)
							$tunnel['remote-gateway'] = $tmp;
					}

					if(isset($tunnel['creategif'])) {
						$number_of_gifs = find_last_gif_device();
						$number_of_gifs++;
						$curwanip = get_current_wan_address();
						if($config['installedpackages']['sasyncd']['config'] <> "")
							foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
								if($sasyncd['ip'] <> "") 
									$curwanip = $sasyncd['ip'];
							}						
						mwexec("/sbin/ifconfig gif" . $number_of_gifs . " tunnel" . $curwanip . " " . $tunnel['remote-gateway']);
						mwexec("/sbin/ifconfig gif" . $number_of_gifs . " {$lansa}/{$lansn} {$lanip}/32");
					}

					$spdconf .= "spdadd {$sa}/{$sn} " .
						"{$tunnel['remote-subnet']} any -P out ipsec " .
						"{$tunnel['p2']['protocol']}/tunnel/{$ep}-" .
						"{$tunnel['remote-gateway']}/unique;\n";

					$spdconf .= "spdadd {$tunnel['remote-subnet']} " .
						"{$sa}/{$sn} any -P in ipsec " .
						"{$tunnel['p2']['protocol']}/tunnel/{$tunnel['remote-gateway']}-" .
						"{$ep}/unique;\n";
				}

				fwrite($fd, $spdconf);
				fclose($fd);

				/* load SPD */
				mwexec("/usr/sbin/setkey -c < {$g['varetc_path']}/spd.conf");
			}

			/* generate racoon.conf */
			$fd = fopen("{$g['varetc_path']}/racoon.conf", "w");
			if (!$fd) {
				printf("Error: cannot open racoon.conf in vpn_ipsec_configure().\n");
				return 1;
			}

			$racoonconf = "";


	if($config['installedpackages']['sasyncd']['config'] <> "")
		foreach($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
			if($sasyncd['ip'] <> "") 
				$curwanip = $sasyncd['ip'];
				/* natt - turn on if <developer/> exists */
				if(isset($config['system']['developer']) <> "") {
					$lanip = $config['interfaces']['lan']['ipaddr'];
					if($lanip <> "") 
						$natt = "isakmp_natt {$lanip}[4500];\n";
					
				}	
				$interface_ip = $sasyncd['ip'];
				$racoonconf .= <<<EOD
listen {
	isakmp {$interface_ip} [500];
	{$natt}
}

EOD;
			}

			$racoonconf = "path pre_shared_key \"{$g['varetc_path']}/psk.txt\";\n\n";
			$racoonconf .= "path certificate  \"{$g['varetc_path']}\";\n\n";
			
			/* generate CA certificates files */
			$cacertnum = 0;
			if (is_array($ipseccfg['cacert']) && count($ipseccfg['cacert']))
				foreach ($ipseccfg['cacert'] as $cacert) {
					++$cacertnum;
					if (isset($cacert['cert'])) {
						$cert = base64_decode($cacert['cert']);
						$x509cert = openssl_x509_parse(openssl_x509_read($cert));
						if(is_array($x509cert) && isset($x509cert['hash'])) {
							$fd1 = fopen("{$g['varetc_path']}/{$x509cert['hash']}.0", "w");
							if (!$fd1) {
								printf("Error: cannot open {$x509cert['hash']}.0 in vpn.\n");
								return 1;
							}
							chmod("{$g['varetc_path']}/{$x509cert['hash']}.0", 0600);
							fwrite($fd1, $cert);
							fclose($fd1);
						}
					}
				}
						
			$tunnelnumber = 0;
			if (is_array($ipseccfg['tunnel']) && count($ipseccfg['tunnel']))
				foreach ($ipseccfg['tunnel'] as $tunnel) {
				
				++$tunnelnumber;
			
				if (isset($tunnel['disabled']))
					continue;
				
				$ep = vpn_endpoint_determine($tunnel, $curwanip);
				if (!$ep)
					continue;
			
				vpn_localnet_determine($tunnel['local-subnet'], $sa, $sn);
			
				if (isset($tunnel['p1']['myident']['myaddress'])) {
					$myidentt = "address";
					$myident = $ep;
				} else if (isset($tunnel['p1']['myident']['address'])) {
					$myidentt = "address";
					$myident = $tunnel['p1']['myident']['address'];
				} else if (isset($tunnel['p1']['myident']['fqdn'])) {
					$myidentt = "fqdn";
					$myident = $tunnel['p1']['myident']['fqdn'];
				} else if (isset($tunnel['p1']['myident']['ufqdn'])) {
					$myidentt = "user_fqdn";
					$myident = $tunnel['p1']['myident']['ufqdn'];
 				} else if (isset($tunnel['p1']['myident']['dyn_dns'])) {
					$myidentt = "dyn_dns";
					$myident = gethostbyname($tunnel['p1']['myident']['dyn_dns']);
 				}
				
				if (isset($tunnel['p1']['authentication_method'])) {
					$authmethod = $tunnel['p1']['authentication_method'];
				} else {$authmethod = 'pre_shared_key';}
				
				$certline = '';	
				
				if ($authmethod == 'rsasig') {
					if ($tunnel['p1']['cert'] && $tunnel['p1']['private-key']) {
						$cert = base64_decode($tunnel['p1']['cert']);
						$private_key = base64_decode($tunnel['p1']['private-key']);
					} else {
						/* null certificate/key */
						$cert = '';
						$private_key = '';
					}
					
					if ($tunnel['p1']['peercert']) 
						$peercert = base64_decode($tunnel['p1']['peercert']);
					else 
						$peercert = '';
					
					$fd1 = fopen("{$g['varetc_path']}/server{$tunnelnumber}-signed.pem", "w");
					if (!$fd1) {
						printf("Error: cannot open server{$tunnelnumber}-signed.pem in vpn.\n");
						return 1;
					}
					chmod("{$g['varetc_path']}/server{$tunnelnumber}-signed.pem", 0600);
					fwrite($fd1, $cert);
					fclose($fd1);
					
					$fd1 = fopen("{$g['varetc_path']}/server{$tunnelnumber}-key.pem", "w");
					if (!$fd1) {
						printf("Error: cannot open server{$tunnelnumber}-key.pem in vpn.\n");
						return 1;
					}
					chmod("{$g['varetc_path']}/server{$tunnelnumber}-key.pem", 0600);
					fwrite($fd1, $private_key);
					fclose($fd1);

					$certline = "certificate_type x509 \"server{$tunnelnumber}-signed.pem\" \"server{$tunnelnumber}-key.pem\";";
					
					if ($peercert!=''){
						$fd1 = fopen("{$g['varetc_path']}/peer{$tunnelnumber}-signed.pem", "w");
						if (!$fd1) {
							printf("Error: cannot open server{$tunnelnumber}-signed.pem in vpn.\n");
							return 1;
						}
						chmod("{$g['varetc_path']}/peer{$tunnelnumber}-signed.pem", 0600);
						fwrite($fd1, $peercert);
						fclose($fd1);		
						$certline .= <<<EOD
						
	peers_certfile "peer{$tunnelnumber}-signed.pem";
EOD;
					} 					
				} 
				$racoonconf .= <<<EOD
remote {$tunnel['remote-gateway']} \{
	exchange_mode {$tunnel['p1']['mode']};
	my_identifier {$myidentt} "{$myident}";
	{$certline}
	peers_identifier address {$tunnel['remote-gateway']};
	initial_contact on;
	support_proxy on;
	proposal_check obey;

	proposal \{
		encryption_algorithm {$tunnel['p1']['encryption-algorithm']};
		hash_algorithm {$tunnel['p1']['hash-algorithm']};
		authentication_method {$authmethod};
		dh_group {$tunnel['p1']['dhgroup']};

EOD;
				if ($tunnel['p1']['lifetime'])
					$racoonconf .= "		lifetime time {$tunnel['p1']['lifetime']} secs;\n";
				
				$racoonconf .= "	}\n";
				
				if ($tunnel['p1']['lifetime'])
					$racoonconf .= "	lifetime time {$tunnel['p1']['lifetime']} secs;\n";
					
				$racoonconf .= "}\n\n";
				
				$p2ealgos = join(",", $tunnel['p2']['encryption-algorithm-option']);
				$p2halgos = join(",", $tunnel['p2']['hash-algorithm-option']);
				
				$racoonconf .= <<<EOD
sainfo address {$sa}/{$sn} any address {$tunnel['remote-subnet']} any \{
	encryption_algorithm {$p2ealgos};
	authentication_algorithm {$p2halgos};
	compression_algorithm deflate;

EOD;

				if ($tunnel['p2']['pfsgroup'])
					$racoonconf .= "	pfs_group {$tunnel['p2']['pfsgroup']};\n";
					
				if ($tunnel['p2']['lifetime'])
					$racoonconf .= "	lifetime time {$tunnel['p2']['lifetime']} secs;\n";
					
				$racoonconf .= "}\n\n";
			}
			
			/* mobile clients? */
			if (isset($ipseccfg['mobileclients']['enable'])) {
				
				$tunnel = $ipseccfg['mobileclients'];
				
				if (isset($tunnel['p1']['myident']['myaddress'])) {
					$myidentt = "address";
					$myident = $curwanip;
				} else if (isset($tunnel['p1']['myident']['address'])) {
					$myidentt = "address";
					$myident = $tunnel['p1']['myident']['address'];
				} else if (isset($tunnel['p1']['myident']['fqdn'])) {
					$myidentt = "fqdn";
					$myident = $tunnel['p1']['myident']['fqdn'];
				} else if (isset($tunnel['p1']['myident']['ufqdn'])) {
					$myidentt = "user_fqdn";
					$myident = $tunnel['p1']['myident']['ufqdn'];
 				}
				
				if (isset($tunnel['p1']['authentication_method'])) {
					$authmethod = $tunnel['p1']['authentication_method'];
				} else {$authmethod = 'pre_shared_key';}
				
				$certline = '';					
				if ($authmethod == 'rsasig') {
					if ($tunnel['p1']['cert'] && $tunnel['p1']['private-key']) {
						$cert = base64_decode($tunnel['p1']['cert']);
						$private_key = base64_decode($tunnel['p1']['private-key']);
					} else {
						/* null certificate/key */
						$cert = '';
						$private_key = '';
					}
					
					if ($tunnel['p1']['peercert']) 
						$peercert = base64_decode($tunnel['p1']['peercert']);
					else 
						$peercert = '';
					
					$fd1 = fopen("{$g['varetc_path']}/server-mobile{$tunnelnumber}-signed.pem", "w");
					if (!$fd1) {
						printf("Error: cannot open server-mobile{$tunnelnumber}-signed.pem in vpn.\n");
						return 1;
					}
					chmod("{$g['varetc_path']}/server-mobile{$tunnelnumber}-signed.pem", 0600);
					fwrite($fd1, $cert);
					fclose($fd1);
					
					$fd1 = fopen("{$g['varetc_path']}/server-mobile{$tunnelnumber}-key.pem", "w");
					if (!$fd1) {
						printf("Error: cannot open server-mobile{$tunnelnumber}-key.pem in vpn.\n");
						return 1;
					}
					chmod("{$g['varetc_path']}/server-mobile{$tunnelnumber}-key.pem", 0600);
					fwrite($fd1, $private_key);
					fclose($fd1);

					$certline = "certificate_type x509 \"server-mobile{$tunnelnumber}-signed.pem\" \"server-mobile{$tunnelnumber}-key.pem\";";
				}
				$racoonconf .= <<<EOD
remote anonymous \{
	exchange_mode {$tunnel['p1']['mode']};
	my_identifier {$myidentt} "{$myident}";
	{$certline}
	initial_contact on;
	passive on;
	generate_policy on;
	support_proxy on;
	proposal_check obey;

	proposal \{
		encryption_algorithm {$tunnel['p1']['encryption-algorithm']};
		hash_algorithm {$tunnel['p1']['hash-algorithm']};
		authentication_method {$authmethod};
		dh_group {$tunnel['p1']['dhgroup']};

EOD;
				if ($tunnel['p1']['lifetime'])
					$racoonconf .= "		lifetime time {$tunnel['p1']['lifetime']} secs;\n";
				
				$racoonconf .= "	}\n";
				
				if ($tunnel['p1']['lifetime'])
					$racoonconf .= "	lifetime time {$tunnel['p1']['lifetime']} secs;\n";
					
				$racoonconf .= "}\n\n";
				
				$p2ealgos = join(",", $tunnel['p2']['encryption-algorithm-option']);
				$p2halgos = join(",", $tunnel['p2']['hash-algorithm-option']);
				
				$racoonconf .= <<<EOD
sainfo anonymous \{
	encryption_algorithm {$p2ealgos};
	authentication_algorithm {$p2halgos};
	compression_algorithm deflate;

EOD;

				if ($tunnel['p2']['pfsgroup'])
					$racoonconf .= "	pfs_group {$tunnel['p2']['pfsgroup']};\n";
					
				if ($tunnel['p2']['lifetime'])
					$racoonconf .= "	lifetime time {$tunnel['p2']['lifetime']} secs;\n";
					
				$racoonconf .= "}\n\n";
			}
			
			fwrite($fd, $racoonconf);
			fclose($fd);
			
			/* generate psk.txt */
			$fd = fopen("{$g['varetc_path']}/psk.txt", "w");
			if (!$fd) {
				printf("Error: cannot open psk.txt in vpn_ipsec_configure().\n");
				return 1;
			}
			
			$pskconf = "";
			
			if (is_array($ipseccfg['tunnel'])) {
				foreach ($ipseccfg['tunnel'] as $tunnel) {
					if (isset($tunnel['disabled']))
						continue;
					$pskconf .= "{$tunnel['remote-gateway']}	 {$tunnel['p1']['pre-shared-key']}\n";
				}
			}
			
			/* add PSKs for mobile clients */
			if (is_array($ipseccfg['mobilekey'])) {
				foreach ($ipseccfg['mobilekey'] as $key) {
					$pskconf .= "{$key['ident']}	{$key['pre-shared-key']}\n";
				}
			}
			
			fwrite($fd, $pskconf);
			fclose($fd);
			chmod("{$g['varetc_path']}/psk.txt", 0600);
			
			/* start racoon */
			mwexec("/usr/local/sbin/racoon -d -f {$g['varetc_path']}/racoon.conf");
			
			if (is_array($ipseccfg['tunnel'])) {
				foreach ($ipseccfg['tunnel'] as $tunnel) {
					if (isset($tunnel['auto'])) {
						$remotehost = substr($tunnel['remote-subnet'],0,strpos($tunnel['remote-subnet'],"/"));
						$srchost = vpn_endpoint_determine($tunnel, $curwanip);
						if ($srchost)
							mwexec_bg("/sbin/ping -c 10 -S {$srchost} {$remotehost}");
					}
				}
			}
		}
	}

	vpn_ipsec_failover_configure();

	if (!$g['booting']) {
		/* reload the filter */
		touch("{$g["tmp_path"]}/filter_dirty");
	}

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function vpn_pptpd_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$pptpdcfg = $config['pptpd'];

	if ($g['booting']) {
		if (!$pptpdcfg['mode'] || ($pptpdcfg['mode'] == "off"))
			return 0;

		echo "Configuring PPTP VPN service... ";
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/mpd-vpn.pid");

		/* wait for process to die */
		sleep(2);

		/* remove mpd.conf, if it exists */
		unlink_if_exists("{$g['varetc_path']}/mpd-vpn/mpd.conf");
		unlink_if_exists("{$g['varetc_path']}/mpd-vpn/mpd.links");
		unlink_if_exists("{$g['varetc_path']}/mpd-vpn/mpd.secret");
	}

	/* make sure mpd-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/mpd-vpn"))
		mkdir("{$g['varetc_path']}/mpd-vpn");

	switch ($pptpdcfg['mode']) {

		case 'server':

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.conf", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.conf in vpn_pptpd_configure().\n");
				return 1;
			}

			$mpdconf = <<<EOD
pptpd:

EOD;

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {
				$mpdconf .= "	load pt{$i}\n";
			}

			if ($config['interfaces']['wan']['ipaddr'] == "pppoe") {
				$pptp_mtu = "1440";
			} else {
				$pptp_mtu = "1460";
			}

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {

				$clientip = long2ip(ip2long($pptpdcfg['remoteip']) + $i);
				$ngif = "ng" . ($i+1);

				$mpdconf .= <<<EOD

pt{$i}:
	new -i {$ngif} pt{$i} pt{$i}
	set ipcp ranges {$pptpdcfg['localip']}/32 {$clientip}/32
	load pts

EOD;
			}

			$mpdconf .= <<<EOD

pts:
	set iface disable on-demand
	set iface enable proxy-arp
	set iface enable tcpmssfix
	set iface idle 1800
	set iface up-script /usr/local/sbin/vpn-linkup
	set iface down-script /usr/local/sbin/vpn-linkdown
	set bundle enable multilink
	set bundle enable crypt-reqd
	set link yes acfcomp protocomp
	set link no pap chap
	set link enable chap-msv2
	set link mtu {$pptp_mtu}
	set link keep-alive 10 60
	set ipcp yes vjcomp
	set bundle enable compression
	set ccp yes mppc
	set ccp yes mpp-e128
	set ccp yes mpp-stateless

EOD;

			if (!isset($pptpdcfg['req128'])) {
				$mpdconf .= <<<EOD
	set ccp yes mpp-e40
	set ccp yes mpp-e56

EOD;
			}
			if (isset($pptpdcfg["wins"])) 
				$mpdconf .= "	set ipcp nbns {$pptpdcfg['wins']}\n";
			if (isset($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . $config['interfaces']['lan']['ipaddr'];
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
				$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
			}

			if (isset($pptpdcfg['radius']['enable'])) {
				$mpdconf .= <<<EOD
	set radius server {$pptpdcfg['radius']['server']} "{$pptpdcfg['radius']['secret']}"
	set radius retries 3
	set radius timeout 10
	set bundle enable radius-auth
	set bundle disable radius-fallback

EOD;

				if (isset($pptpdcfg['radius']['accounting'])) {
					$mpdconf .= <<<EOD
	set bundle enable radius-acct

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.links", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.links in vpn_pptpd_configure().\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {
				$mpdlinks .= <<<EOD

pt{$i}:
	set link type pptp
	set pptp enable incoming
	set pptp disable originate
	set pptp disable windowing
	set pptp self 127.0.0.1

EOD;
			}

			fwrite($fd, $mpdlinks);
			fclose($fd);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.secret", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.secret in vpn_pptpd_configure().\n");
				return 1;
			}

			$mpdsecret = "";

			if (is_array($pptpdcfg['user'])) {
				foreach ($pptpdcfg['user'] as $user)
					$mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			chmod("{$g['varetc_path']}/mpd-vpn/mpd.secret", 0600);

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']}/mpd-vpn -p {$g['varrun_path']}/mpd-vpn.pid pptpd");

			break;

		case 'redir':
			break;
	}

	touch("{$g["tmp_path"]}/filter_dirty");

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function vpn_localnet_determine($adr, &$sa, &$sn) {
	global $config, $g;

	if (isset($adr)) {
		if ($adr['network']) {
			switch ($adr['network']) {
				case 'lan':
					$sn = $config['interfaces']['lan']['subnet'];
					$sa = gen_subnet($config['interfaces']['lan']['ipaddr'], $sn);
					break;
			}
		} else if ($adr['address']) {
			list($sa,$sn) = explode("/", $adr['address']);
			if (is_null($sn))
				$sn = 32;
		}
	} else {
		$sn = $config['interfaces']['lan']['subnet'];
		$sa = gen_subnet($config['interfaces']['lan']['ipaddr'], $sn);
	}
}

function vpn_endpoint_determine($tunnel, $curwanip) {

	global $g, $config;

	if ((!$tunnel['interface']) || ($tunnel['interface'] == "wan")) {
		if ($curwanip)
			return $curwanip;
		else
			return null;
	} else if ($tunnel['interface'] == "lan") {
		return $config['interfaces']['lan']['ipaddr'];
	} else {
		$oc = $config['interfaces'][$tunnel['interface']];

		if (isset($oc['enable']) && $oc['if']) {
			return $oc['ipaddr'];
		}
	}

	return null;
}

function vpn_pppoe_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$pppoecfg = $config['pppoe'];

	/* create directory if it does not exist */
	if(!is_dir("{$g['varetc_path']}/mpd-vpn"))
		mkdir("{$g['varetc_path']}/mpd-vpn");
	
	if ($g['booting']) {
		if (!$pppoecfg['mode'] || ($pppoecfg['mode'] == "off"))
			return 0;

		echo "Configuring PPPoE VPN service... ";
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/mpd-vpn.pid");

		/* wait for process to die */
		sleep(2);
		
		vpn_pptpd_configure();
	}

	/* make sure mpd-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/mpd-vpn"))
		mkdir("{$g['varetc_path']}/mpd-vpn");

	switch ($pppoecfg['mode']) {

		case 'server':

			$pppoe_interface = filter_translate_type_to_real_interface($pppoecfg['interface']);

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.conf", "a");
			if (!$fd) {
				printf("Error: cannot open mpd.conf in vpn_pppoe_configure().\n");
				return 1;
			}
			$mpdconf = "\n\n";
			$mpdconf .= <<<EOD
pppoe:

EOD;

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdconf .= "	load pppoe{$i}\n";
			}

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {

				$clientip = long2ip(ip2long($pppoecfg['remoteip']) + $i);
				$ngif = "ng" . ($i+1);

				$mpdconf .= <<<EOD

pppoe{$i}:
	new -i {$ngif} pppoe{$i} pppoe{$i}
	set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32
	load pppoe_standart

EOD;
			}

			$mpdconf .= <<<EOD

pppoe_standart:
	set link type pppoe
	set pppoe iface {$pppoe_interface}
	set pppoe service "*"
	set pppoe disable originate
	set pppoe enable incoming
	set bundle no multilink
	set bundle enable compression
	set bundle max-logins 1
	set iface idle 0
	set iface disable on-demand
	set iface disable proxy-arp
	set iface enable tcpmssfix
	set iface mtu 1500
	set link mtu 1500
	set link no pap chap
	set link enable chap
	set link keep-alive 60 180
	set ipcp yes vjcomp
	set ipcp no vjcomp
	set link max-redial -1
	set link mtu 1492
	set ccp yes mpp-e40
	set ccp yes mpp-e128
	set ccp yes mpp-stateless
	set link latency 1
	#set ipcp dns 10.10.1.3
	#set bundle accept encryption	

EOD;

			if (isset($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . $config['interfaces']['lan']['ipaddr'];
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} else if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
				$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
			}

			if (isset($pppoecfg['radius']['enable'])) {
				$mpdconf .= <<<EOD
	set radius server {$pppoecfg['radius']['server']} "{$pppoecfg['radius']['secret']}"
	set radius retries 3
	set radius timeout 10
	set bundle enable radius-auth
	set bundle disable radius-fallback

EOD;

				if (isset($pppoecfg['radius']['accounting'])) {
					$mpdconf .= <<<EOD
	set bundle enable radius-acct

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.links", "a");
			if (!$fd) {
				printf("Error: cannot open mpd.links in vpn_pppoe_configure().\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdlinks .= <<<EOD

pppoe:
	set link type pppoe
	set pppoe iface {$pppoe_interface}

EOD;
			}

			fwrite($fd, $mpdlinks);
			fclose($fd);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/mpd-vpn/mpd.secret", "a");
			if (!$fd) {
				printf("Error: cannot open mpd.secret in vpn_pppoe_configure().\n");
				return 1;
			}

			$mpdsecret = "\n\n";

			if (is_array($pppoecfg['user'])) {
				foreach ($pppoecfg['user'] as $user)
					$mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			chmod("{$g['varetc_path']}/mpd-vpn/mpd.secret", 0600);

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd -b -d {$g['varetc_path']}/mpd-vpn -p {$g['varrun_path']}/mpd-vpn.pid pppoe");

			break;

		case 'redir':
			break;
	}

	touch("{$g["tmp_path"]}/filter_dirty");

	if ($g['booting'])
		echo "done\n";

	return 0;
}

?>
