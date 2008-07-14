<?php

/*
	vpn.inc
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
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
require_once ("functions.inc");

function vpn_ipsec_failover_configure() {
	global $config, $g;

	$sasyncd_text = "";

	if ($config['installedpackages']['sasyncd'] <> "")
		foreach ($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
			$enabled = isset ($sasyncd['enable']);
			if (!$enabled)
				return;
			if ($sasyncd['peerip'] <> "")
				$sasyncd_text .= "peer {$sasyncd['peerip']}\n";
			if ($sasyncd['interface'])
				$sasyncd_text .= "carp interface {$sasyncd['interface']}\n";
			if ($sasyncd['sharedkey'] <> "")
				$sasyncd_text .= "sharedkey {$sasyncd['sharedkey']}\n";
			if ($sasyncd['mode'] <> "")
				$sasyncd_text .= "mode {$sasyncd['mode']}\n";
			if ($sasyncd['listenon'] <> "")
				$sasyncd_text .= "listen on {$sasyncd['listenon']}\n";
			if ($sasyncd['flushmodesync'] <> "")
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
	$regs = "";
	if (!($fp = popen("/sbin/ifconfig -l", "r")))
		return -1;
	$ifconfig_data = fread($fp, 4096);
	pclose($fp);
	$ifconfig_array = split(" ", $ifconfig_data);
	foreach ($ifconfig_array as $ifconfig) {
		ereg("gif(.)", $ifconfig, $regs);
		if ($regs[0] && $regs[0] > $last_gif_found) {
			$last_gif_found = $regs[1];
		}
	}
	return $last_gif_found;
}

function vpn_ipsec_configure($ipchg = false)
{
	global $config, $g, $sa, $sn, $p1_ealgos, $p2_ealgos;

	mwexec("/sbin/ifconfig enc0 up");

	/* get the automatic /etc/ping_hosts.sh ready */
	unlink_if_exists("/var/db/ipsecpinghosts");
	touch("/var/db/ipsecpinghosts");

	if ($g['booting'] == true) {
		/* determine if we should load the via padlock module */
		$dmesg_boot = `/usr/bin/grep CPU {$g['varlog_path']}/dmesg.boot`;
		if (stristr($dmesg_boot, "ACE") == true) {
			//echo "Enabling [VIA Padlock] ...";
			//mwexec("/sbin/kldload padlock");
			//mwexec("/sbin/sysctl net.inet.ipsec.crypto_support=1");
			//mwexec("/usr/local/sbin/setkey -F");
			//mwexec("/usr/local/sbin/setkey -FP");
			//echo " done.\n";
		}
	}

	if(isset($config['ipsec']['preferredoldsa']))
		mwexec("/sbin/sysctl net.key.preferred_oldsa=0");
	else
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");

	$number_of_gifs = find_last_gif_device();
	for ($x = 0; $x < $number_of_gifs; $x++)
		mwexec("/sbin/ifconfig gif" . $x . " delete");

	$curwanip = get_current_wan_address();

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];
	$a_phase1 = $config['ipsec']['phase1'];
	$a_phase2 = $config['ipsec']['phase2'];
	$a_client = $config['ipsec']['client'];
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$lansa = gen_subnet($lancfg['ipaddr'], $lancfg['subnet']);
	$lansn = $lancfg['subnet'];

	if (!isset($ipseccfg['enable'])) {
		mwexec("/sbin/ifconfig enc0 down");
		mwexec("/sbin/ifconfig enc0 destroy");

		/* kill racoon */
		mwexec("/usr/bin/killall racoon");
		killbypid("{$g['varrun_path']}/dnswatch-ipsec.pid");
		
		/* wait for racoon process to die */
		sleep(2);

		/* send a SIGKILL to be sure */
		sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");

		/* flush SPD and SAD */
		mwexec("/usr/local/sbin/setkey -FP");
		mwexec("/usr/local/sbin/setkey -F");

		return true;
	}

	if ($g['booting'])
		echo "Configuring IPsec VPN... ";

	if (isset ($ipseccfg['enable'])) {
		/* fastforwarding is not compatible with ipsec tunnels */
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=0");

		if (!$curwanip) {
			/* IP address not configured yet, exit */
			if ($g['booting'])
				echo "done\n";
			return 0;
		}

		/* resolve all local, peer addresses and setup pings */
		$ipmap = array();
		$rgmap = array();
		$dnswatch_list = array();
		if (is_array($a_phase1) && count($a_phase1)) {
			foreach ($a_phase1 as $ph1ent) {
				if (isset($ph1ent['disabled']))
					continue;

				$ep = vpn_endpoint_determine($ph1ent, $curwanip);
				if (!$ep)
					continue;

				if(!in_array($ep,$ipmap))
					$ipmap[] = $ep;

				/* see if this tunnel has a hostname for the remote-gateway. If so,
				   try to resolve it now and add it to the list for dnswatch */

				if (isset ($ph1ent['mobile']))
					continue;

				$rg = $ph1ent['remote-gateway'];

				if (!is_ipaddr($rg)) {
					$dnswatch_list[] = $rg;
					$rg = resolve_retry($rg);

					if (!$rgip)
						continue;
				}

				$rgmap[$ph1ent['remote-gateway']] = $rg;

				/* add an ipsec pinghosts entry */

				if ($ph1ent['pinghost']) {
					$pfd = fopen("/var/db/ipsecpinghosts", "a");
					$iflist = array("lan" => "lan", "wan" => "wan");
					for ($i = 1; isset($config['interfaces']['opt' . $i]); $i++)
						$iflist['opt' . $i] = "opt{$i}";
					foreach ($iflist as $ifent => $ifname) {
						$interface_ip = find_interface_ip($config['interfaces'][$ifname]['if']);
						if (ip_in_subnet($interface_ip, $sa . "/" . $sn))
						$srcip = find_interface_ip($config['interfaces'][$ifname]['if']);
					}
					$dstip = $ph1ent['pinghost'];
					fwrite($pfd, "$srcip|$dstip|3\n");
					fclose($pfd);
				}
			}
		}

		/* generate CA certificates files */
		$cacertnum = 0;
		if (is_array($ipseccfg['cacert']) && count($ipseccfg['cacert'])) {
			foreach ($ipseccfg['cacert'] as $cacert) {
				++ $cacertnum;
				if (isset ($cacert['cert'])) {
					$cert = base64_decode($cacert['cert']);
					$x509cert = openssl_x509_parse(openssl_x509_read($cert));
					if (is_array($x509cert) && isset ($x509cert['hash'])) {
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
		}
		
		/* generate psk.txt */
		$fd = fopen("{$g['varetc_path']}/psk.txt", "w");
		if (!$fd) {
			printf("Error: cannot open psk.txt in vpn_ipsec_configure().\n");
			return 1;
		}

		$pskconf = "";

		if (is_array($a_phase1) && count($a_phase1)) {
			foreach ($a_phase1 as $ph1ent) {

				if (isset($ph1ent['disabled']))
					continue;

				if (strstr($ph1ent['authentication_method'],'rsa'))
					continue;

				$peerid_type = $ph1ent['peerid_type'];

				switch ($peerid_type) {
					case "peeraddress":
						$peerid_type = "address";
						$peerid_data = $rgmap[$ph1ent['remote-gateway']];
						break;

					case "address";
						$peerid_data = $ph1ent['peerid_data'];
						break;

					case "fqdn";
					case "keyid tag";
					case "user_fqdn";
						$peerid_data = $ph1ent['peerid_data'];
						break;
				}

				$pskconf .= "{$peerid_data}\t{$ph1ent['pre-shared-key']}\n";
			}
		}

		fwrite($fd, $pskconf);
		fclose($fd);
		chmod("{$g['varetc_path']}/psk.txt", 0600);
			
		/* begin racoon.conf */
		if ((is_array($a_phase1) && count($a_phase1)) ||
			(is_array($a_phase2) && count($a_phase2))) {

			$fd = fopen("{$g['varetc_path']}/racoon.conf", "w");
			if (!$fd) {
				printf("Error: cannot open racoon.conf in vpn_ipsec_configure().\n");
				return 1;
			}

			$racoonconf = "";

			$racoonconf .= "path pre_shared_key \"{$g['varetc_path']}/psk.txt\";\n\n";
			$racoonconf .= "path certificate  \"{$g['varetc_path']}\";\n\n";

			/* begin listen section */
			if (count($ipmap)) {
				$racoonconf .= "\nlisten\n";
				$racoonconf .= "{\n";
				foreach ($ipmap as $addr) {
					$racoonconf .= "\tisakmp {$addr} [500];\n";
					$racoonconf .= "\tisakmp_natt {$addr} [4500];\n";
				}
				$racoonconf .= "}\n\n";
			}

			/* begin mode_cfg section */
			if (is_array($a_client) && isset($a_client['enable'])) {

				$racoonconf .= "\nmode_cfg\n";
				$racoonconf .= "{\n";

				if ($a_client['user_source'])
					$racoonconf .= "\tauth_source {$a_client['user_source']};\n";
				if ($a_client['group_source'])
					$racoonconf .= "\tgroup_source {$a_client['group_source']};\n";

				if ($a_client['pool_address'] && $a_client['pool_netbits']) {
					$pool_address = $a_client['pool_address'];
					$pool_netmask = gen_subnet_mask($a_client['pool_netbits']);

					$pool_address = long2ip(ip2long($pool_address)+1);
					$pool_size = ~ip2long($pool_netmask) - 2;

					$racoonconf .= "\tpool_size {$pool_size};\n";
					$racoonconf .= "\tnetwork4 {$pool_address};\n";
					$racoonconf .= "\tnetmask4 {$pool_netmask};\n";
				}

				if (isset($a_client['net_list'])) {

					$net_list = '';

					foreach ($a_phase2 as $ph2ent) {

						if (isset($ph2ent['disabled']))
							continue;

						if (!isset($ph2ent['mobile']))
							continue;

						$localid = ipsec_idinfo_to_cidr($ph2ent['localid'],true);

						if ($net_list)
							$net_list .= ", ";
						$net_list .= $localid;
					}

					if ($net_list)
						$racoonconf .= "\tsplit_network include {$net_list};\n";
				}

				if ($a_client['dns_server1'])
					$racoonconf .= "\tdns4 {$a_client['dns_server1']};\n";
				if ($a_client['dns_server2'])
					$racoonconf .= "\tdns4 {$a_client['dns_server2']};\n";
				if ($a_client['dns_server3'])
					$racoonconf .= "\tdns4 {$a_client['dns_server3']};\n";
				if ($a_client['dns_server4'])
					$racoonconf .= "\tdns4 {$a_client['dns_server4']};\n";

				if ($a_client['wins_server1'])
					$racoonconf .= "\twins4 {$a_client['wins_server1']};\n";
				if ($a_client['wins_server2'])
					$racoonconf .= "\twins4 {$a_client['wins_server2']};\n";

				if ($a_client['dns_domain'])
					$racoonconf .= "\tdefault_domain \"{$a_client['dns_domain']}\";\n";

				if ($a_client['pfs_group'])
					$racoonconf .= "\tpfs_group {$a_client['pfs_group']};\n";

				if ($a_client['login_banner']) {
					$fn = "{$g['varetc_path']}/racoon.motd";
					$fd1 = fopen($fn, "w");
					if (!$fd1) {
						printf("Error: cannot open server{$fn} in vpn.\n");
						return 1;
					}

					fwrite($fd1, $a_client['login_banner']);
					fclose($fd1);

					$racoonconf .= "\tbanner \"{$fn}\";\n";
				}

				$racoonconf .= "}\n\n";
			}
			/* end mode_cfg section */

			/* begin remote sections */
			if (is_array($a_phase1) && count($a_phase1)) {
				/* begin remote */
				foreach ($a_phase1 as $ph1ent) {

					if (isset($ph1ent['disabled']))
						continue;

					if (isset($ph1ent['mobile']) && !isset($a_client['enable']))
						continue;

					$ikeid = $ph1ent['ikeid'];

					$ep = vpn_endpoint_determine($ph1ent, $curwanip);
					if (!$ep)
						continue;

					if (!isset($ph1ent['mobile'])) {
						$rgip = $rgmap[$ph1ent['remote-gateway']];
						if (!$rgip)
							continue;
					}

					$myid_type = $ph1ent['myid_type'];

					switch ($myid_type) {

						case "myaddress":
							$myid_type = "address";
							$myid_data = $ep;
							break;

						case "dyn_dns":
							$myid_data = gethostbyname($ph1ent['myid_data']);
							break;

						case "address";
							$myid_data = $ph1ent['myid_data'];
							break;

						case "fqdn";
						case "keyid tag";
						case "user_fqdn";
						case "asn1dn";
							$myid_data = $ph1ent['myid_data'];
							if( $myid_data )
								$myid_data = "\"".$myid_data."\"";
							break;
					}

					$peerid_type = $ph1ent['peerid_type'];

					switch ($peerid_type) {
						case "peeraddress":
							$peerid_type = "address";
							$peerid_data = $rgip;
							break;

						case "address";
							$peerid_data = $ph1ent['peerid_data'];
							break;

						case "fqdn";
						case "keyid tag";
						case "user_fqdn";
						case "asn1dn";
							$peerid_data = $ph1ent['peerid_data'];
							if( $peerid_data )
								$peerid_data = "\"".$peerid_data."\"";
							break;
					}

					$natt = "off";
					if (isset($ph1ent['nat_traversal']))
						$natt = $ph1ent['nat_traversal'];

					$init = "on";
					$genp = "off";
					if (isset($ph1ent['mobile'])) {
						$rgip = "anonymous";
						$init = "off";
						$genp = "unique";
					}

					$dpdline1 = '';
					$dpdline2 = '';
					if ($ph1ent['dpd_delay'] && $ph1ent['dpd_maxfail']) {
						$dpdline1 = "dpd_delay = {$ph1ent['dpd_delay']};";
						$dpdline2 = "dpd_maxfail = {$ph1ent['dpd_maxfail']};";
					}

					if (isset ($ph1ent['authentication_method']))
						$authmethod = $ph1ent['authentication_method'];
					else
						$authmethod = 'pre_shared_key';

					$certline = '';

					if (strstr($authmethod,'rsa')) {
						if ($ph1ent['cert'] && $ph1ent['private-key']) {
							$cert = base64_decode($ph1ent['cert']);
							$private_key = base64_decode($ph1ent['private-key']);
						} else {
							/* null certificate/key */
							$cert = '';
							$private_key = '';
						}

						if ($ph1ent['peercert'])
							$peercert = base64_decode($ph1ent['peercert']);
						else
							$peercert = '';

						$fd1 = fopen("{$g['varetc_path']}/server{$ikeid}-signed.pem", "w");
						if (!$fd1) {
							printf("Error: cannot open server{$ikeid}-signed.pem in vpn.\n");
							return 1;
						}
						
						chmod("{$g['varetc_path']}/server{$ikeid}-signed.pem", 0600);
						fwrite($fd1, $cert);
						fclose($fd1);

						$fd1 = fopen("{$g['varetc_path']}/server{$ikeid}-key.pem", "w");
						if (!$fd1) {
							printf("Error: cannot open server{$ikeid}-key.pem in vpn.\n");
							return 1;
						}
						chmod("{$g['varetc_path']}/server{$ikeid}-key.pem", 0600);
						fwrite($fd1, $private_key);
						fclose($fd1);

						$certline = "certificate_type x509 \"server{$ikeid}-signed.pem\" \"server{$ikeid}-key.pem\";";

						if ($peercert != '') {
							$fd1 = fopen("{$g['varetc_path']}/peer{$ikeid}-signed.pem", "w");
							if (!$fd1) {
								printf("Error: cannot open server{$ikeid}-signed.pem in vpn.\n");
								return 1;
							}
							chmod("{$g['varetc_path']}/peer{$ikeid}-signed.pem", 0600);
							fwrite($fd1, $peercert);
							fclose($fd1);
							$certline .="peers_certfile \"peer{$ikeid}-signed.pem\"";
						}
					}

					$ealgos = '';
					$ealg_id = $ph1ent['encryption-algorithm']['name'];
					$ealg_kl = $ph1ent['encryption-algorithm']['keylen'];
					if ($ealg_kl)
						$ealgos = $ealgos.$ealg_id." ".$ealg_kl;
					else
						$ealgos = $ealgos.$ealg_id;

					$lifeline = '';
					if ($ph1ent['lifetime'])
						$lifeline = "lifetime time {$ph1ent['lifetime']} secs;";

					/* add remote section to configuration */

					$racoonconf .=<<<EOD

remote {$rgip}
{
	ph1id {$ikeid};
	exchange_mode {$ph1ent['mode']};
	my_identifier {$myid_type} {$myid_data};
	peers_identifier {$peerid_type} {$peerid_data};
	ike_frag on;
	generate_policy = {$genp};
	initial_contact = {$init};
	nat_traversal = {$natt};
	{$certline}
	{$dpdline1}
	{$dpdline2}
	support_proxy on;
	proposal_check claim;

	proposal
	{
		authentication_method {$authmethod};
		encryption_algorithm ${ealgos};
		hash_algorithm {$ph1ent['hash-algorithm']};
		dh_group {$ph1ent['dhgroup']};
		${lifeline}
	}
}

EOD;
				}
				/* end remote */
			}
			/* end remote sections */
		
			/* begin sainfo sections */
			if (is_array($a_phase2) && count($a_phase2)) {

				/* begin sainfo */
				foreach ($a_phase2 as $ph2ent) {

					$ikeid = $ph2ent['ikeid'];

					if (isset($ph2ent['disabled']))
						continue;

					if (isset($ph2ent['mobile']) && !isset($a_client['enable']))
						continue;

					$localid_type = $ph2ent['localid']['type'];
					if ($localid_type != "address")
						$localid_type = "subnet";

					$localid_data = ipsec_idinfo_to_cidr($ph2ent['localid']);
					$localid_spec = $localid_type." ".$localid_data." any";

					if (!isset($ph2ent['mobile'])) {

						$remoteid_type = $ph2ent['remoteid']['type'];
						if ($remoteid_type != "address")
							$remoteid_type = "subnet";

						$remoteid_data = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
						$remoteid_spec = $remoteid_type." ".$remoteid_data." any";

					} else
						$remoteid_spec = "anonymous";

					$ealgos = '';
					$halgos = join(",", $ph2ent['hash-algorithm-option']);

					$pfsline = '';
					if ($ph2ent['pfsgroup'])
						$pfsline = "pfs_group {$ph2ent['pfsgroup']};";
					if (isset($a_client['pfs_group'])) {
						$pfsline = '';
						if ($a_client['pfs_group'])
							$pfsline = "pfs_group {$a_client['pfs_group']};";
					}

					$lifeline = '';
					if ($ph2ent['lifetime'])
						$lifeline = "lifetime time {$ph2ent['lifetime']} secs;";

					foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {

						$ealg_id = $ealg['name'];
						$ealg_kl = $ealg['keylen'];

						if ($ealg_kl) {
							if( $ealg_kl == "auto" ) {
								$key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
								$key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
								$key_step = $p2_ealgos[$ealg_id]['keysel']['step'];

								for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
									if( $ealgos )
										$ealgos = $ealgos.", ";
									$ealgos = $ealgos.$ealg_id." ".$keylen;
								}
							} else {
								if ($ealgos)
									$ealgos = $ealgos.", ";
								$ealgos = $ealgos.$ealg_id." ".$ealg_kl;
							}
						} else {
							if ($ealgos)
								$ealgos = $ealgos.", ";
							$ealgos = $ealgos.$ealg_id;
						}
					}

					/* add sainfo section to configuration */
					
					$racoonconf .=<<<EOD
					
sainfo {$localid_spec} {$remoteid_spec}
{
	remoteid {$ikeid};
	encryption_algorithm {$ealgos};
	authentication_algorithm {$halgos};
	compression_algorithm deflate;
	{$pfsline}
	{$lifeline}
}

EOD;
				}
				/* end sainfo */
			}
			/* end sainfo sections */

			fwrite($fd, $racoonconf);
			fclose($fd);
		}
		/* end racoon.conf */

		/* generate IPsec policies */
		if (is_array($a_phase2) && count($a_phase2)) {
			/* generate spd.conf */
			$fd = fopen("{$g['varetc_path']}/spd.conf", "w");
			if (!$fd) {
				printf("Error: cannot open spd.conf in vpn_ipsec_configure().\n");
				return 1;
			}

			$spdconf = "";

			/* What are these SPD entries for?
			 * -mgrooms 07/10/2008
			 */
			$spdconf .= "spdadd {$lanip}/32 {$lansa}/{$lansn} any -P out none;\n";
			$spdconf .= "spdadd {$lansa}/{$lansn} {$lanip}/32 any -P in none;\n";

			foreach ($a_phase2 as $ph2ent) {

				if( !ipsec_lookup_phase1($ph2ent,$ph1ent))
					continue;

				if (isset($ph1ent['mobile']))
					continue;

				if (isset($ph1ent['disabled']))
					continue;

				if (isset($ph2ent['disabled']))
					continue;

				$ep = vpn_endpoint_determine($ph1ent, $curwanip);
				if (!$ep)
					continue;

				$rgip = $rgmap[$ph1ent['remote-gateway']];

				$localid = ipsec_idinfo_to_cidr($ph2ent['localid'],true);
				$remoteid = ipsec_idinfo_to_cidr($ph2ent['remoteid'],true);

				if (isset ($ph2ent['creategif'])) {
					$number_of_gifs = find_last_gif_device();
					$number_of_gifs++;
					$curwanip = get_current_wan_address();
					if ($config['installedpackages']['sasyncd']['config'] <> "") {
						foreach ($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
							if ($sasyncd['ip'] <> "")
								$curwanip = $sasyncd['ip'];
						}
					}
					mwexec("/sbin/ifconfig gif" . $number_of_gifs . " tunnel" . $curwanip . " " . $rgip);
					mwexec("/sbin/ifconfig gif" . $number_of_gifs . " {$lansa}/{$lansn} {$lanip}/32");
				}

				$spdconf .= "spdadd {$localid} {$remoteid} any -P out ipsec " .
					"{$ph2ent['protocol']}/tunnel/{$ep}-{$rgip}/unique;\n";

				$spdconf .= "spdadd {$remoteid} {$localid} any -P in ipsec " .
					"{$ph2ent['protocol']}/tunnel/{$rgip}-{$ep}/unique;\n";

				/* static route needed? */
				if (preg_match("/^carp/i", $ph1ent['interface']))
					$parentinterface = link_carp_interface_to_parent($ph1ent['interface']);
				else
					$parentinterface = $ph1ent['interface'];

				if ($parentinterface <> "wan") {
					/* add endpoint routes to correct gateway on interface */
					if (interface_has_gateway($parentinterface)) {
						$gatewayip = get_interface_gateway("$parentinterface");
						$interfaceip = $config['interfaces'][$parentinterface]['ipaddr'];
						$subnet_bits = $config['interfaces'][$parentinterface]['subnet'];
						$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
						/* if the remote gateway is in the local subnet, then don't add a route */
						if (! ip_in_subnet($rgip, "{$subnet_ip}/{$subnet_bits}")) {
							if(is_ipaddr($gatewayip)) {
								log_error("IPSEC interface is not WAN but {$parentinterface}, adding static route for VPN endpoint {$rgip} via {$gatewayip}");
								mwexec("/sbin/route delete -host {$rgip};/sbin/route add -host {$rgip} {$gatewayip}");
							}
						}
					}
				}
				else
					mwexec("/sbin/route delete -host {$rgip}");
			}

			fwrite($fd, $spdconf);
			fclose($fd);
		}

		/* mange racoon process */
		if (is_process_running("racoon")) {
			/* We are already online, reload */
			mwexec("/usr/bin/killall -HUP racoon");
			/* flush SPD entries */
			mwexec("/usr/local/sbin/setkey -FP");
			mwexec("/usr/local/sbin/setkey -F");
			/* load SPD */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/spd.conf");
			sleep(1);
			/* We are already online, reload */
			mwexec("/usr/bin/killall -HUP racoon");
			sleep(1);
			mwexec("/usr/bin/killall -HUP racoon");
		} else {
			/* start racoon */
			mwexec("/usr/local/sbin/racoon -f {$g['varetc_path']}/racoon.conf");
			/* flush SA + SPD entries */
			mwexec("/usr/local/sbin/setkey -FP");
			mwexec("/usr/local/sbin/setkey -F");
			/* load SPD */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/spd.conf");
			sleep(1);
			/* We are already online, reload */
			mwexec("/usr/bin/killall -HUP racoon");
			sleep(1);
			mwexec("/usr/bin/killall -HUP racoon");

			/* start dnswatch, if necessary */
			if (count($dnswatch_list) > 0) {
				$interval = 60;
				if ($ipseccfg['dns-interval'])
					$interval = $ipseccfg['dns-interval'];

				$hostnames = "";
				foreach ($dnswatch_list as $dns)
					$hostnames .= " " . escapeshellarg($dns);

				mwexec("/usr/local/bin/dnswatch {$g['varrun_path']}/dnswatch-ipsec.pid $interval " .
				escapeshellarg("/etc/rc.newipsecdns") . $hostname);
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

/* XXX: This is totally broken. */
function vpn_localnet_determine($adr, & $sa, & $sn) {
	global $config, $g;

	if (isset ($adr)) {
		if ($adr['network']) {
			switch ($adr['network']) {
				case 'lan' :
					$sn = $config['interfaces']['lan']['subnet'];
					$sa = gen_subnet($config['interfaces']['lan']['ipaddr'], $sn);
					break;
			}
		} else
			if ($adr['address']) {
				list ($sa, $sn) = explode("/", $adr['address']);
				if (is_null($sn))
					$sn = 32;
			}
	} else {
		$sn = $config['interfaces']['lan']['subnet'];
		$sa = gen_subnet($config['interfaces']['lan']['ipaddr'], $sn);
	}
}

/* XXX: is there a need for this get_current_wan_address() does already this?! */
function vpn_endpoint_determine($ph1ent, $curwanip) {

	global $g, $config;

	if ((!$ph1ent['interface']) || ($ph1ent['interface'] == "wan")) {
		if ($curwanip)
			return $curwanip;
		else
			return null;
	} elseif ($ph1ent['interface'] == "lan") {
		return $config['interfaces']['lan']['ipaddr'];
	} else {
		$iface = $config['interfaces'][$ph1ent['interface']]['if'];
		$oc = $config['interfaces'][$ph1ent['interface']];
		/* carp ips, etc */
		$ip = find_interface_ip($iface);
		if($ip) 
			return $ip;
			
		if (isset ($oc['enable']) && $oc['if']) {
			return $oc['ipaddr'];
		}
	}

	return null;
}

/* Forcefully restart IPsec
 * This is required for when dynamic interfaces reload
 * For all other occasions the normal vpn_ipsec_configure()
 * will gracefully reload the settings without restarting
 */
function vpn_ipsec_force_reload() {
	global $config;
	global $g;

	$ipseccfg = $config['ipsec'];

	/* kill racoon */
	mwexec("/usr/bin/killall racoon");

	/* wait for process to die */
	sleep(4);

	/* send a SIGKILL to be sure */
	sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");

	/* wait for flushing to finish */
	sleep(1);

	/* if ipsec is enabled, start up again */
	if (isset($ipseccfg['enable'])) {
		log_error("Forcefully reloading IPsec racoon daemon");
		vpn_ipsec_configure();
	}

}

/* master setup for vpn (mpd) */
function vpn_setup() {
	/* start pptpd */
	vpn_pptpd_configure();

	/* start pppoe server */
	vpn_pppoe_configure();

	/* setup l2tp */
	vpn_l2tp_configure();
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
		killbypid("{$g['varrun_path']}/pptp-vpn.pid");

		/* wait for process to die */
		sleep(3);

		if (is_process_running("mpd -b")) {
			killbypid("{$g['varrun_path']}/pptp-vpn.pid");
			log_error("Could not kill mpd within 3 seconds.   Trying again.");
		}

		/* remove mpd.conf, if it exists */
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.conf");
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.links");
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.secret");
	}

	/* make sure pptp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/pptp-vpn"))
		mkdir("{$g['varetc_path']}/pptp-vpn");

	switch ($pptpdcfg['mode']) {
		case 'server' :
			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.conf", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.conf in vpn_pptpd_configure().\n");
				return 1;
			}

			$mpdconf =<<<EOD
pptpd:

EOD;

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {
				$mpdconf .= "	load pt{$i}\n";
			}

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {

				$clientip = long2ip(ip2long($pptpdcfg['remoteip']) + $i);

				if(isset($pptpdcfg['radius']['radiusissueips']) && isset($pptpdcfg['radius']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$pptpdcfg['localip']}/32 0.0.0.0/0";
					$isssue_ip_type .="\n\tset ipcp yes radius-ip";
				} else {
					$isssue_ip_type = "set ipcp ranges {$pptpdcfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

pt{$i}:
	new pt{$i} pt{$i}
	{$isssue_ip_type}
	load pts

EOD;
			}

			$mpdconf .=<<<EOD

pts:
	set iface disable on-demand
	set iface enable proxy-arp
	set iface enable tcpmssfix
	set iface idle 1800
	set iface up-script /usr/local/sbin/pptp-linkup
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

			if (!isset ($pptpdcfg['req128'])) {
				$mpdconf .=<<<EOD
	set ccp yes mpp-e40
	set ccp yes mpp-e56

EOD;
			}

			if  (isset($pptpdcfg["wins"]))
				$mpdconf  .=  "	set ipcp nbns {$pptpdcfg['wins']}\n";
			if (is_array($pptpdcfg['dnsserver']) && ($pptpdcfg['dnsserver'][0])) {
				$mpdconf .= "	set ipcp dns " . join(" ", $pptpdcfg['dnsserver']) . "\n";
			} else
				if (isset ($config['dnsmasq']['enable'])) {
					$mpdconf .= "	set ipcp dns " . $config['interfaces']['lan']['ipaddr'];
					if ($syscfg['dnsserver'][0])
						$mpdconf .= " " . $syscfg['dnsserver'][0];
					$mpdconf .= "\n";
				} else
					if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
						$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
					}

			if (isset ($pptpdcfg['radius']['enable'])) {
				$authport = isset($pptpdcfg['radius']['port']) ? $pptpdcfg['radius']['port'] : 1812;
				$acctport = $authport + 1;
				$mpdconf .=<<<EOD
	set radius server {$pptpdcfg['radius']['server']} "{$pptpdcfg['radius']['secret']}" {$authport} {$acctport}
	set radius retries 3
	set radius timeout 10
	set bundle enable radius-auth
	set bundle disable radius-fallback

EOD;

				if (isset ($pptpdcfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set bundle enable radius-acct
	set radius acct-update 300

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.links", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.links in vpn_pptpd_configure().\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $g['n_pptp_units']; $i++) {
				$mpdlinks .=<<<EOD

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
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.secret", "w");
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
			chmod("{$g['varetc_path']}/pptp-vpn/mpd.secret", 0600);

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pptp-vpn -p {$g['varrun_path']}/pptp-vpn.pid -f mpd.conf pptpd");

			break;

		case 'redir' :
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

function vpn_pppoe_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$pppoecfg = $config['pppoe'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/pppoe-vpn"))
		mkdir("{$g['varetc_path']}/pppoe-vpn");

	if ($g['booting']) {
		if (!$pppoecfg['mode'] || ($pppoecfg['mode'] == "off"))
			return 0;

		echo "Configuring PPPoE VPN service... ";
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/pppoe-vpn.pid");

		/* wait for process to die */
		sleep(2);

	}

	/* make sure pppoe-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/pppoe-vpn"))
		mkdir("{$g['varetc_path']}/pppoe-vpn");

	switch ($pppoecfg['mode']) {

		case 'server' :

			$pppoe_interface = filter_translate_type_to_real_interface($pppoecfg['interface']);

			if ($pppoecfg['paporchap'] == "chap")
				$paporchap = "set link enable chap";
			else
				$paporchap = "set link enable pap";

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/pppoe-vpn/mpd.conf", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.conf in vpn_pppoe_configure().\n");
				return 1;
			}
			$mpdconf = "\n\n";
			$mpdconf .=<<<EOD
pppoe:

EOD;

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdconf .= "	load pppoe{$i}\n";
			}

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {

				$clientip = long2ip(ip2long($pppoecfg['remoteip']) + $i);

				if (isset ($pppoecfg['radius']['radiusissueips']) && isset ($pppoecfg['radius']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 0.0.0.0/0";
					$isssue_ip_type .= "\n\tset ipcp yes radius-ip";
				} else {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

pppoe{$i}:
	new pppoe{$i} pppoe{$i}
	{$isssue_ip_type}
	load pppoe_standart

EOD;
			}

			$mpdconf .=<<<EOD

pppoe_standart:
	set bundle no multilink
	set bundle enable compression
	set auth max-logins 1
	set iface up-script /usr/local/sbin/pppoe-linkup
        set iface down-script /usr/local/sbin/vpn-linkdown
	set iface idle 0
	set iface disable on-demand
	set iface disable proxy-arp
	set iface enable tcpmssfix
	set iface mtu 1500
	set link no pap chap
	{$paporchap}
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

			if (isset ($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . $config['interfaces']['lan']['ipaddr'];
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} else
				if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
					$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
				}

			if (isset ($pppoecfg['radius']['enable'])) {
				$mpdconf .=<<<EOD
	set radius server {$pppoecfg['radius']['server']} "{$pppoecfg['radius']['secret']}"
	set ipcp radius-ip
	set radius retries 3
	set radius timeout 10
	set bundle enable radius-auth
	set bundle disable radius-fallback

EOD;

				if (isset ($pppoecfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set bundle enable radius-acct

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/pppoe-vpn/mpd.links", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.links in vpn_pppoe_configure().\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdlinks .=<<<EOD
			
pppoe{$i}:
	set phys type pppoe
        set pppoe iface {$pppoe_interface}
        set pppoe service "*"
        set pppoe disable originate
        set pppoe enable incoming

EOD;
			}

			fwrite($fd, $mpdlinks);
			fclose($fd);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/pppoe-vpn/mpd.secret", "w");
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
			chmod("{$g['varetc_path']}/pppoe-vpn/mpd.secret", 0600);

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pppoe-vpn -p {$g['varrun_path']}/pppoe-vpn.pid pppoe");

			break;

		case 'redir' :
			break;
	}

	touch("{$g["tmp_path"]}/filter_dirty");

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function vpn_l2tp_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$l2tpcfg = $config['l2tp'];

	mwexec("/sbin/kldload /boot/kernel/ng_l2tp.ko");

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/l2tp-vpn"))
		mkdir("{$g['varetc_path']}/l2tp-vpn");

	if ($g['booting']) {
		if (!$l2tpcfg['mode'] || ($l2tpcfg['mode'] == "off"))
			return 0;

		echo "Configuring l2tp VPN service... ";
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/l2tp-vpn.pid");

		/* wait for process to die */
		sleep(2);

	}

	/* make sure l2tp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/l2tp-vpn"))
		mkdir("{$g['varetc_path']}/l2tp-vpn");

	switch ($l2tpcfg['mode']) {

		case 'server' :

			$l2tp_interface = filter_translate_type_to_real_interface($l2tpcfg['interface']);

			if ($l2tpcfg['paporchap'] == "chap")
				$paporchap = "set link enable chap";
			else
				$paporchap = "set link enable pap";

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.conf", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.conf in vpn_l2tp_configure().\n");
				return 1;
			}
			$mpdconf = "\n\n";
			$mpdconf .=<<<EOD
l2tp:

EOD;

			for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {
				$mpdconf .= "	load l2tp{$i}\n";
			}

			for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {

				$clientip = long2ip(ip2long($l2tpcfg['remoteip']) + $i);

				if (isset ($l2tpcfg['radius']['radiusissueips']) && isset ($l2tpcfg['radius']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 0.0.0.0/0";
					$isssue_ip_type .= "\n\tset ipcp yes radius-ip";
				} else {
					$isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

l2tp{$i}:
	new l2tp{$i} l2tp{$i}
	{$isssue_ip_type}
	load l2tp_standard

EOD;
			}

			$mpdconf .=<<<EOD

l2tp_standard:
        set bundle disable multilink
        set bundle enable compression
        set bundle yes crypt-reqd
        set ipcp yes vjcomp
        # set ipcp ranges 131.188.69.161/32 131.188.69.170/28
        set ccp yes mppc
        set iface disable on-demand
        set iface enable proxy-arp
	set iface up-script /usr/local/sbin/l2tp-linkup
        set iface down-script /usr/local/sbin/vpn-linkdown
        set link yes acfcomp protocomp
        set link no pap chap
        set link enable chap
        set link keep-alive 10 180

EOD;

			if (isset ($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . $config['interfaces']['lan']['ipaddr'];
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} else
				if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
					$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
				}

			if (isset ($l2tpcfg['radius']['enable'])) {
				$mpdconf .=<<<EOD
	set radius server {$l2tpcfg['radius']['server']} "{$l2tpcfg['radius']['secret']}"
	set ipcp radius-ip
	set radius retries 3
	set radius timeout 10
	set bundle enable radius-auth
	set bundle disable radius-fallback

EOD;

				if (isset ($l2tpcfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set bundle enable radius-acct

EOD;
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.links", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.links in vpn_l2tp_configure().\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {
				$mpdlinks .=<<<EOD

l2tp:
	set link type l2tp
	set l2tp iface {$l2tp_interface}

EOD;
			}

			fwrite($fd, $mpdlinks);
			fclose($fd);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.secret", "w");
			if (!$fd) {
				printf("Error: cannot open mpd.secret in vpn_l2tp_configure().\n");
				return 1;
			}

			$mpdsecret = "\n\n";

			if (is_array($l2tpcfg['user'])) {
				foreach ($l2tpcfg['user'] as $user)
					$mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			chmod("{$g['varetc_path']}/l2tp-vpn/mpd.secret", 0600);

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid l2tp");

			break;

		case 'redir' :
			break;
	}

	touch("{$g["tmp_path"]}/filter_dirty");

	if ($g['booting'])
		echo "done\n";

	return 0;
}
?>
