<?php
/*
	vpn.inc
	Copyright (C) 2004-2006 Scott Ullrich
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

/* master setup for vpn (mpd) */
function vpn_setup() {
	/* start pptpd */
	vpn_pptpd_configure();

	/* start pppoe server */
	vpn_pppoe_configure();
}

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

	mwexec("killall sasyncd", true);

	/* launch sasyncd, oh wise one */
	/* mwexec_bg("/usr/local/sbin/sasyncd -d -v -v -v"); */
}

function find_last_gif_device() {
	 	$regs = "";
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
	global $config, $g, $sa, $sn;

	mwexec("/sbin/ifconfig enc0 create", true);
	mwexec("/sbin/ifconfig enc0 up", true);
	
	exec("/sbin/sysctl net.enc.out.ipsec_bpf_mask=0x00000000");
	exec("/sbin/sysctl net.enc.out.ipsec_filter_mask=0x00000001");
	exec("/sbin/sysctl net.enc.in.ipsec_bpf_mask=0x00000000");
	exec("/sbin/sysctl net.enc.in.ipsec_filter_mask=0x00000002");

	/* get the automatic /etc/ping_hosts.sh ready */
	unlink_if_exists("/var/db/ipsecpinghosts");
	touch("/var/db/ipsecpinghosts");

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

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	if (!isset($ipseccfg['enable'])) {
		mwexec("/sbin/ifconfig enc0 down");
		mwexec("/sbin/ifconfig enc0 destroy");

		/* kill racoon */
		mwexec("/usr/bin/killall racoon", true);
		killbypid("{$g['varrun_path']}/dnswatch-ipsec.pid");


		/* wait for process to die */
		sleep(2);

		/* send a SIGKILL to be sure */
		sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");

		/* flush SPD and SAD */
		mwexec("/usr/local/sbin/setkey -FP");
		mwexec("/usr/local/sbin/setkey -F");

		return true;
	}

	if ($g['booting']) {
		echo "Configuring IPsec VPN... ";
	}

	if (isset($ipseccfg['enable'])) {

		/* fastforwarding is not compatible with ipsec tunnels */
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=0");

		if (!$curwanip) {
			/* IP address not configured yet, exit */
			if ($g['booting'])
				echo "done\n";
			return 0;
		}

		/* this loads a route table which is used to determine if a route needs to be removed. */
		exec("/usr/bin/netstat -rn", $route_arr, $retval);
		$route_str = implode("\n", $route_arr);

		if ((is_array($ipseccfg['tunnel']) && count($ipseccfg['tunnel'])) ||
				isset($ipseccfg['mobileclients']['enable'])) {

			$dnswatch_list = array();
			$rgmap = array();

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
					/* see if this tunnel has a hostname for the remote-gateway, and if so,
					 * try to resolve it now and add it to the list for dnswatch */
					if (!is_ipaddr($tunnel['remote-gateway'])) {
						$dnswatch_list[] = $tunnel['remote-gateway'];
						$rgip = resolve_retry($tunnel['remote-gateway']);
						add_hostname_to_watch($tunnel['remote-gateway']);
						if (!$rgip) {
							log_error("Could not deterimine VPN endpoint for {$tunnel['descr']}");
							continue;
						}
					} else {
						$rgip = $tunnel['remote-gateway'];
					}
					$rgmap[$tunnel['remote-gateway']] = $rgip;
					if (!$ep) {
						log_error("Could not deterimine VPN endpoint for {$tunnel['descr']}");
						continue;	
					}


					vpn_localnet_determine($tunnel['local-subnet'], $sa, $sn);

					if(is_domain($tunnel['remote-gateway'])) {
						$tmp = gethostbyname($tunnel['remote-gateway']);
						if($tmp) {
							$tunnel['remote-gateway'] = $tmp;
						}
					}

					/* add entry to host pinger */
					if ($tunnel['pinghost']) {
						$pfd = fopen("/var/db/ipsecpinghosts", "a");
						$iflist = array("lan" => "lan", "wan" => "wan");
				          	for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
							$iflist['opt' . $i] = "opt{$i}";

						foreach ($iflist as $ifent => $ifname) {
							$interface_ip = find_interface_ip($config['interfaces'][$ifname]['if']);
							if (ip_in_subnet($interface_ip, $sa . "/" . $sn))
							$srcip = find_interface_ip($config['interfaces'][$ifname]['if']);
						}
						$dstip = $tunnel['pinghost'];
						fwrite($pfd, "$srcip|$dstip|3\n");
						fclose($pfd);
					}
					if(isset($tunnel['creategif'])) {
						$number_of_gifs = find_last_gif_device();
						$number_of_gifs++;
						$curwanip = get_current_wan_address();

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
				
					/* static route needed? */
					if(preg_match("/^carp/i", $tunnel['interface'])) {
						$parentinterface = link_carp_interface_to_parent($tunnel['interface']);
					} else {
						$parentinterface = $tunnel['interface'];
					}
					if($parentinterface <> "wan") {
						/* add endpoint routes to correct gateway on interface */
						if(interface_has_gateway($parentinterface)) {
							$gatewayip = get_interface_gateway("$parentinterface");
							$interfaceip = $config['interfaces'][$parentinterface]['ipaddr'];
							$subnet_bits = $config['interfaces'][$parentinterface]['subnet'];
							$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
							/* if the remote gateway is in the local subnet, then don't add a route */
							if(! ip_in_subnet($tunnel['remote-gateway'], "{$subnet_ip}/{$subnet_bits}")) {
								if(is_ipaddr($gatewayip)) {
									log_error("IPSEC interface is not WAN but {$tunnel['interface']}, adding static route for VPN endpoint {$tunnel['remote-gateway']} via {$gatewayip}");
									mwexec("/sbin/route delete -host {$tunnel['remote-gateway']}");
                                                                        mwexec("/sbin/route add -host {$tunnel['remote-gateway']} {$gatewayip}");
								}
							}
						}
					} else {
						if(stristr($route_str, "/{$tunnel['remote-gateway']}/")) {
							mwexec("/sbin/route delete -host {$tunnel['remote-gateway']}");
						}
					}
				}

				fwrite($fd, $spdconf);
				fclose($fd);
			}

			/* generate racoon.conf */
			$fd = fopen("{$g['varetc_path']}/racoon.conf", "w");
			if (!$fd) {
				printf("Error: cannot open racoon.conf in vpn_ipsec_configure().\n");
				return 1;
			}

			$racoonconf = "# This file is automatically generated. Do not edit\n";
			$racoonconf .= "listen {\n";
			$racoonconf .= "	adminsock \"/var/run/racoon.sock\" \"root\" \"wheel\" 0660;\n";
			$racoonconf .= "}\n";

			$racoonconf .= "path pre_shared_key \"{$g['varetc_path']}/psk.txt\";\n\n";
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

				$rgip = $rgmap[$tunnel['remote-gateway']];
				if (!$rgip)
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

				if (!($myidentt == "asn1dn" && $myident == "")) {
					$myident = " \"".$myident."\"";
				}
				
				$nattline = '';
				if (isset($tunnel['natt'])) {
					$nattline = "nat_traversal on;";
				}

				$dpdline = '';
				if (is_numeric($tunnel['dpddelay'])) {
					$dpdline = "dpd_delay {$tunnel['dpddelay']};";
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
remote {$rgmap[$tunnel['remote-gateway']]} \{
	exchange_mode {$tunnel['p1']['mode']};
	my_identifier {$myidentt}{$myident};
	{$nattline}
	{$certline}
	peers_identifier address {$rgmap[$tunnel['remote-gateway']]};
	initial_contact on;
	{$dpdline}
	ike_frag on;
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
					$myidentt = "address ";
					$myident = $curwanip;
				} else if (isset($tunnel['p1']['myident']['address'])) {
					$myidentt = "address ";
					$myident = $tunnel['p1']['myident']['address'];
				} else if (isset($tunnel['p1']['myident']['fqdn'])) {
					$myidentt = "fqdn ";
					$myident = $tunnel['p1']['myident']['fqdn'];
				} else if (isset($tunnel['p1']['myident']['ufqdn'])) {
					$myidentt = "user_fqdn ";
					$myident = $tunnel['p1']['myident']['ufqdn'];
 				}
				
				$nattline = '';
				if (isset($tunnel['natt'])) {
					$nattline = "nat_traversal on;";
				}
				$dpdline = '';
				if (isset($tunnel['dpddelay'])) {
					$dpdline = "dpd_delay {$tunnel['dpddelay']};";
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
	my_identifier {$myidentt}"{$myident}";	
	{$nattline}
	{$certline}
	initial_contact on;
	{$dpdline}
	ike_frag on;
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

					$rgip = $rgmap[$tunnel['remote-gateway']];
					if (!$rgip)
						continue;

					$pskconf .= "{$rgip}     {$tunnel['p1']['pre-shared-key']}\n";
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

			if(is_process_running("racoon")) {
				log_error("IPSEC: Send a reload signal to the IPsec process");
				sleep("0.1");
				mwexec("/usr/local/sbin/racoonctl -s /var/run/racoon.sock reload-config", false);
				// mwexec("/usr/bin/killall -HUP racoon", false);
			} else {
				/* flush SA + SPD entries */
				mwexec("/usr/local/sbin/setkey -FP", false);
				sleep("0.1");
				mwexec("/usr/local/sbin/setkey -F", false);
				sleep("0.1");
				/* start racoon */
				mwexec("/usr/local/sbin/racoon -f {$g['varetc_path']}/racoon.conf", false);
				sleep("0.1");
				/* load SPD */
				mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/spd.conf", false);
				/* We are already online, reload */
				sleep("0.1");
				mwexec("/usr/bin/killall -HUP racoon", false);
			}

			/* start dnswatch, if necessary */
			if (count($dnswatch_list) > 0) {
				$interval = 60;
				if ($ipseccfg['dns-interval'])
					$interval = $ipseccfg['dns-interval'];

				$hostnames = "";
				foreach ($dnswatch_list as $dns)
					$hostnames .= " " . escapeshellarg($dns);
				killbypid("{$g['varrun_path']}/dnswatch-ipsec.pid");
				mwexec("/usr/local/sbin/dnswatch {$g['varrun_path']}/dnswatch-ipsec.pid $interval " .
				escapeshellarg("/etc/rc.newipsecdns") . $hostnames, false);
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
		sleep(3);

		if(is_process_running("mpd -b")) {
			killbypid("{$g['varrun_path']}/mpd-vpn.pid");
			log_error("Could not kill mpd within 3 seconds.   Trying again.");
		}

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
			if  (isset($pptpdcfg["wins"]))
				$mpdconf  .=  "	set ipcp nbns {$pptpdcfg['wins']}\n";
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

	if(!$tunnel['interface']) {
		return null;
	}
	if(is_ipaddr($curwanip)) {
		if(preg_match("/^carp/i", $tunnel['interface'])) {
			$iface = $tunnel['interface'];
		} else {
			if($config['interfaces'][$tunnel['interface']]['ipaddr'] == "pppoe" OR 
				$config['interfaces'][$tunnel['interface']]['ipaddr'] == "pptp") {
				$iface = "ng0";
			} else {
				$iface = $config['interfaces'][$tunnel['interface']]['if'];
			}
		}
		$oc = $config['interfaces'][$tunnel['interface']];
		/* carp ips, etc */
		$ip = find_interface_ip($iface);
		if($ip)
			return $ip;

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

				if(isset($pppoecfg['radius']['radiusissueips']) && isset($pppoecfg['radius']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 0.0.0.0/0";
					$isssue_ip_type .="\n\tset ipcp yes radius-ip";
				} else {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .= <<<EOD

pppoe{$i}:
	new -i {$ngif} pppoe{$i} pppoe{$i}
	{$isssue_ip_type}
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
	set link no pap chap
	set link enable chap
	set link keep-alive 60 180
	set ipcp yes vjcomp
	set ipcp no vjcomp
	set link max-redial -1
	set link mtu 1492
	set link mru 1492
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
	set radius acct-update 300
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

/* Forcefully restart IPSEC
 * This is required for when dynamic interfaces reload
 * For all other occasions the normal vpn_ipsec_configure()
 * will gracefully reload the settings without restarting
 */
function vpn_ipsec_force_reload() {
	global $config;
	global $g;

	$ipseccfg = $config['ipsec'];

	/* kill racoon */
	mwexec("/usr/bin/killall racoon", true);

	/* wait for process to die */
	sleep(4);

	/* send a SIGKILL to be sure */
	sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");

	/* wait for flushing to finish */
	sleep(1);

	/* if ipsec is enabled, start up again */
	if (isset($ipseccfg['enable'])) {
		log_error("Forcefully reloading IPSEC racoon daemon");
		vpn_ipsec_configure();
	}

}

/* Walk the tunnels for hostname endpoints. If the hostnames 
 * resolve to a different IP now compared to the DNS cache
 * we reload the policies if the endpoint has changed */
function vpn_ipsec_refresh_policies() {
	global $config;
	global $g;

	$ipseccfg = $config['ipsec'];

	if (! isset($ipseccfg['enable'])) {
		return true;
	}

	/* Walk the Ipsec tunnel array */
	if (is_array($ipseccfg['tunnel']) && count($ipseccfg['tunnel'])) {
		foreach ($ipseccfg['tunnel'] as $tunnel) {
			if (isset($tunnel['disabled'])) {
				continue;
			}
			if (is_ipaddr($tunnel['remote-gateway'])) {
				continue;
			}

			if (!is_ipaddr($tunnel['remote-gateway'])) {
				$dnscache = compare_hostname_to_dnscache($tunnel['remote-gateway']);
				$dnscache = trim($dnscache);
				/* we should have the old IP addresses in the dnscache now */
				if($dnscache <> "") {
					$oldtunnel = $tunnel;
					$oldtunnel['remote-gateway'] = trim($dnscache);
					reload_tunnel_spd_policy ($tunnel, $oldtunnel);
				}
			}
		}
	}

	/* process all generated spd.conf files from tmp which are left behind
	 * behind by either changes of dynamic tunnels or manual edits
	 * scandir() is only available in PHP5 */
	$tmpfiles = array();
	$dh  = opendir($g['tmp_path']);
	while (false !== ($filename = readdir($dh))) {
		$tmpfiles[] = $filename;
	}
	sort($tmpfiles);
	foreach($tmpfiles as $tmpfile) {
		if(preg_match("/^spd.conf./", $tmpfile)) {
			$ret = mwexec("/usr/local/sbin/setkey -f {$g['tmp_path']}/{$tmpfile} 2>&1", false);
			if($ret == 0) {
				unlink("{$g['tmp_path']}/{$tmpfile}");
			} else {
				rename("{$g['tmp_path']}/{$tmpfile}", ("{$g['tmp_path']}/failed.{$tmpfile}"));
			}
		}
	}
}

function reload_tunnel_spd_policy($tunnel, $oldtunnel) {
	global $config;
	global $g;

	/* if we are not passed a old tunnel array we create one */
	if(empty($oldtunnel)) {
		$oldtunnel = $tunnel;
	}

	$curwanip = get_current_wan_address();
	$sad_arr = return_ipsec_sad_array();

	$ep = vpn_endpoint_determine($tunnel, $curwanip);
	vpn_localnet_determine($tunnel['local-subnet'], $sa, $sn);

	/* make sure we pass the oldtunnel array with a IP for the remote gw */
	$oldgw = trim($oldtunnel['remote-gateway']);
	$oldep = vpn_endpoint_determine($oldtunnel, $curwanip);
	vpn_localnet_determine($oldtunnel['local-subnet'], $oldsa, $oldsn);

	/* see if this tunnel has a hostname for the remote-gateway, and if so,
	 * try to resolve it now and add it to the list for dnswatch */
	if (!is_ipaddr($tunnel['remote-gateway'])) {
		$rgip = resolve_retry($tunnel['remote-gateway']);
		add_hostname_to_watch($tunnel['remote-gateway']);
		if (!$rgip) {
			log_error("Could not determine VPN endpoint for {$tunnel['descr']}");
			return false;
		}
	} else {
		$rgip = $tunnel['remote-gateway'];
	}
	if (!$ep) {
		log_error("Could not determine VPN endpoint for {$tunnel['descr']}");
		return false;
	}

	$spdconf = "";

	/* Delete old SPD policies if there are changes between the old and new */
	if(($tunnel != $oldtunnel) && (is_ipaddr($oldgw))) {
		$spdconf .= "spddelete {$oldsa}/{$oldsn} " .
			"{$oldtunnel['remote-subnet']} any -P out ipsec " .
			"{$oldtunnel['p2']['protocol']}/tunnel/{$oldep}-" .
			"{$oldgw}/unique;\n";
		$spdconf .= "spddelete {$oldtunnel['remote-subnet']} " .
			"{$oldsa}/{$oldsn} any -P in ipsec " .
			"{$oldtunnel['p2']['protocol']}/tunnel/{$oldgw}-" .
			"{$oldep}/unique;\n";

		/* zap any existing SA entries */
		foreach($sad_arr as $sad) {
			if(($sad['dst'] == $oldep) && ($sad['src'] == $oldgw)) {
				$spdconf .= "delete {$oldep} {$oldgw} {$tunnel['p2']['protocol']} 0x{$sad['spi']};\n";
			}
			if(($sad['src'] == $oldep) && ($sad['dst'] == $oldgw)) {
				$spdconf .= "delete {$oldgw} {$oldep} {$tunnel['p2']['protocol']} 0x{$sad['spi']};\n";
			}
		}
	}

	/* Create new SPD entries for the new configuration */
	/* zap any existing SA entries beforehand */
	foreach($sad_arr as $sad) {
		if(($sad['dst'] == $ep) && ($sad['src'] == $rgip)) {
			$spdconf .= "delete {$rgip} {$ep} {$tunnel['p2']['protocol']} 0x{$sad['spi']};\n";
		}
		if(($sad['src'] == $ep) && ($sad['dst'] == $rgip)) {
			$spdconf .= "delete {$ep} {$rgip} {$tunnel['p2']['protocol']} 0x{$sad['spi']};\n";
		}
	}
	/* add new SPD policies to replace them */
	$spdconf .= "spdadd {$sa}/{$sn} " .
		"{$tunnel['remote-subnet']} any -P out ipsec " .
		"{$tunnel['p2']['protocol']}/tunnel/{$ep}-" .
		"{$rgip}/unique;\n";
	$spdconf .= "spdadd {$tunnel['remote-subnet']} " .
		"{$sa}/{$sn} any -P in ipsec " .
		"{$tunnel['p2']['protocol']}/tunnel/{$rgip}-" .
		"{$ep}/unique;\n";

	log_error("IPSEC: Tunnel '{$tunnel['descr']}' has changed IP from '{$oldgw}' to '{$rgip}', reloading policy");

	$now = time();
	$spdfile = tempnam("{$g['tmp_path']}", "spd.conf.reload.{$now}.");
	/* generate temporary spd.conf */
	file_put_contents($spdfile, $spdconf);
	return true;
}

/* Dump SAD database to array */
function return_ipsec_sad_array() {
	/* query SAD */
	$fd = @popen("/usr/local/sbin/setkey -D", "r");
	$sad = array();
	if ($fd) {
	        while (!feof($fd)) {
	                $line = chop(fgets($fd));
       	         if (!$line)
       	                 continue;
       	         if ($line == "No SAD entries.")
       	                 break;
       	         if ($line[0] != "\t") {
       	                 if (is_array($cursa))
       	                         $sad[] = $cursa;
       	                 $cursa = array();
       	                 list($cursa['src'],$cursa['dst']) = explode(" ", $line);
        	                $i = 0;
        		        } else {
                        $linea = explode(" ", trim($line));
                        if ($i == 1) {
                                $cursa['proto'] = $linea[0];
                                $cursa['spi'] = substr($linea[2], strpos($linea[2], "x")+1, -1);
                        } else if ($i == 2) {
                                $cursa['ealgo'] = $linea[1];
                        } else if ($i == 3) {
                                $cursa['aalgo'] = $linea[1];
                        }
                }
                $i++;
        }
        if (is_array($cursa) && count($cursa))
                $sad[] = $cursa;
        pclose($fd);
	}
	return($sad);
}

?>
