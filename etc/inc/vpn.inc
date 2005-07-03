<?php
/*
	vpn.inc
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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
	
function vpn_ipsec_configure($ipchg = false) {
	global $config, $g;
	
	$curwanip = get_current_wan_address();
	
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
	
	/* prefer old SAs only for 30 seconds, then use the new one */
	if (!isset($config['ipsec']['preferoldsa']))
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");
	else
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=1");
	
	if (isset($ipseccfg['enable'])) {
		
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
							mwexec_bg("/sbin/ping -c 1 -S {$srchost} {$remotehost}");
					}
				}
			}
		}
	}
	
	if (!$g['booting']) {
		/* reload the filter */
		filter_configure();
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
	set link mtu 1460
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
			
			if (is_array($pptpdcfg['dnsserver']) && ($pptpdcfg['dnsserver'][0])) {
				$mpdconf .= "	set ipcp dns " . join(" ", $pptpdcfg['dnsserver']) . "\n";
			} else if (isset($config['dnsmasq']['enable'])) {
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
	
	if (!$g['booting']) {
		/* reload the filter */
		filter_configure();
	}
	
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
	
?>
