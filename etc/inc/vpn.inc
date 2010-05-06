<?php

/*
	vpn.inc
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2008 Ermal Luçi
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

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/killall	/usr/local/sbin/sasyncd	/sbin/ifconfig	/sbin/sysctl
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/setkey	/usr/bin/netstat	/sbin/route	/bin/mkdir
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/racoonctl	/usr/local/sbin/racoon
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/dnswatch	/usr/local/sbin/mpd4	
	pfSense_MODULE:	vpn
*/

/* include all configuration functions */

function vpn_ipsec_failover_configure() {
	global $config, $g;


	if (is_array($config['installedpackages']['sasyncd'])) {
		$sasyncd_text = "";
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

		file_put_contents("{$g['varetc_path']}/sasyncd.conf", $sasyncd_text);
		chmod("{$g['varetc_path']}/sasyncd.conf", 0600);

		if(is_process_running("sasyncd"))
			mwexec("killall sasyncd", true);

		/* launch sasyncd, oh wise one */
		mwexec_bg("/usr/local/sbin/sasyncd -d -v -v -v");
	}
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

	/* get the automatic ping_hosts.sh ready */
	unlink_if_exists("{$g['vardb_path']}/ipsecpinghosts");
	touch("{$g['vardb_path']}/ipsecpinghosts");

	if(isset($config['ipsec']['preferredoldsa']))
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");
	else
		mwexec("/sbin/sysctl net.key.preferred_oldsa=0");

	$number_of_gifs = find_last_gif_device();
	for ($x = 0; $x < $number_of_gifs; $x++)
		mwexec("/sbin/ifconfig gif" . $x . " delete");

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];
	$a_phase1 = $config['ipsec']['phase1'];
	$a_phase2 = $config['ipsec']['phase2'];
	$a_client = $config['ipsec']['client'];
	$lanip = get_interface_ip("lan");
	$lansn = get_interface_subnet("lan");
	$lansa = gen_subnet($lanip, $lansn);

	if (!isset($ipseccfg['enable'])) {
		mwexec("/sbin/ifconfig enc0 down");

		/* kill racoon */
		if(is_process_running("racoon"))
			mwexec("/usr/bin/killall racoon", true);
		killbypid("{$g['varrun_path']}/dnswatch-ipsec.pid");
		
		/* wait for racoon process to die */
		sleep(2);

		/* send a SIGKILL to be sure */
		sigkillbypid("{$g['varrun_path']}/racoon.pid", "KILL");

		/* flush SPD and SAD */
		mwexec("/usr/local/sbin/setkey -FP");
		mwexec("/usr/local/sbin/setkey -F");
		
		/* disallow IPSEC, it is off */
//		exec("/sbin/sysctl net.inet.ip.ipsec_in_use=0");

		return true;
	} else {
		if ($g['booting'])
			echo "Configuring IPsec VPN... ";

		/* fastforwarding is not compatible with ipsec tunnels */
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=0");

		/* this loads a route table which is used to determine if a route needs to be removed. */
		exec("/usr/bin/netstat -rnf inet", $route_arr, $retval);
		$route_str = implode("\n", $route_arr);

		/* resolve all local, peer addresses and setup pings */
		$ipmap = array();
		$rgmap = array();
		$dnswatch_list = array();
		if (is_array($a_phase1) && count($a_phase1)) {

			/* step through each phase1 entry */
			foreach ($a_phase1 as $ph1ent) {
				if (isset($ph1ent['disabled']))
					continue;

				$ep = ipsec_get_phase1_src($ph1ent);
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
					add_hostname_to_watch($rg);
					$rg = resolve_retry($rg);
					if (!$rg)
						continue;
				}

				$rgmap[$ph1ent['remote-gateway']] = $rg;

				/* step through each phase2 entry */
				$ipsecpinghosts = "";
				foreach ($a_phase2 as $ph2ent) {

					$ikeid = $ph2ent['ikeid'];

					if (isset($ph2ent['disabled']))
						continue;

					if ($ikeid != $ph1ent['ikeid'])
						continue;

					/* add an ipsec pinghosts entry */
					if ($ph2ent['pinghost']) {
						$iflist = get_configured_interface_list();
						foreach ($iflist as $ifent => $ifname) {
							$interface_ip = get_interface_ip($ifent);
							$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true);
							if (ip_in_subnet($interface_ip, $local_subnet)) {
								$srcip = $interface_ip;
								break;
							}
						}
						$dstip = $ph2ent['pinghost'];
						if (is_ipaddr($srcip))
							$ipsecpinghosts .= "{$srcip}|{$dstip}|3\n";
					}
				}
				$pfd = fopen("{$g['vardb_path']}/ipsecpinghosts", "w");
				if ($pfd) {
					fwrite($pfd, $ipsecpinghosts);
					fclose($pfd);
				}
				
			}
		}

		/* generate CA certificates files */
		if (is_array($config['system']['ca']) && count($config['system']['ca'])) {
			foreach ($config['system']['ca'] as $ca) {
				if (!isset($ca['crt'])) {
					log_error("Error: Invalid certificate info for {$ca['name']}");
					continue;
				}
				$cert = base64_decode($ca['crt']);
				$x509cert = openssl_x509_parse(openssl_x509_read($cert));
				if (!is_array($x509cert) || !isset($x509cert['hash'])) {
					log_error("Error: Invalid certificate hash info for {$ca['name']}");
					continue;
				}
				$fname = $g['varetc_path']."/".$x509cert['hash'].".0";
				if (!file_put_contents($fname, $cert)) {
					log_error("Error: Cannot write IPsec CA file for {$ca['name']}");
					continue;
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

		/* add PSKs for mobile clients */
		if (is_array($ipseccfg['mobilekey'])) {
			foreach ($ipseccfg['mobilekey'] as $key) {
				$pskconf .= "{$key['ident']}	{$key['pre-shared-key']}\n";
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

			$racoonconf = "# This file is automatically generated. Do not edit\n";			
			$racoonconf .= "path pre_shared_key \"{$g['varetc_path']}/psk.txt\";\n\n";
			$racoonconf .= "path certificate  \"{$g['varetc_path']}\";\n\n";

			/* begin listen section */
			if (count($ipmap)) {
				$racoonconf .= "\nlisten\n";
				$racoonconf .= "{\n";
				$racoonconf .= "	adminsock \"/var/db/racoon/racoon.sock\" \"root\" \"wheel\" 0660;\n";
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

					$ep = ipsec_get_phase1_src($ph1ent);
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
							$myid_type = "address";
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
						if ($ph1ent['authentication_method'] == "pre_shared_key") {
							$genp = "on";
						} else {
							$init = "off";
							$genp = "unique";
						}
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

						$cert = lookup_cert($ph1ent['certref']);

						if (!$cert)
						{
							log_error("Error: Invalid phase1 certificate reference for {$ph1ent['name']}");
							continue;
						}

						$certfile = "cert-".$ikeid.".crt";
						$certpath = $g['varetc_path']."/".$certfile;

						if (!file_put_contents($certpath, base64_decode($cert['crt'])))
						{
							log_error("Error: Cannot write phase1 certificate file for {$ph1ent['name']}");
							continue;
						}

						chmod($certpath, 0600);

						$keyfile = "cert-".$ikeid.".key";
						$keypath = $g['varetc_path']."/".$keyfile;

						if (!file_put_contents($keypath, base64_decode($cert['prv'])))
						{
							log_error("Error: Cannot write phase1 key file for {$ph1ent['name']}");
							continue;
						}

						chmod($keypath, 0600);

						$certline = "certificate_type x509 \"".basename($certpath)."\" \"".basename($keypath)."\";";
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

					/* Only specify peer ID if we are not dealing with a mobile PSK-only tunnel */
					if (!(($ph1ent['authentication_method'] == "pre_shared_key") && isset($ph1ent['mobile']))) {
						$peerid_spec = "peers_identifier {$peerid_type} {$peerid_data};";
					}

					/* add remote section to configuration */

					$racoonconf .=<<<EOD

remote {$rgip}
{
	ph1id {$ikeid};
	exchange_mode {$ph1ent['mode']};
	my_identifier {$myid_type} {$myid_data};
	{$peerid_spec}
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

					if( !ipsec_lookup_phase1($ph2ent,$ph1ent))
						continue;

					if (isset($ph1ent['disabled']))
						continue;

					if (isset($ph2ent['disabled']))
						continue;

					if (isset($ph2ent['mobile']) && !isset($a_client['enable']))
						continue;

					if ($ph2ent['mode'] == 'tunnel') {

						$localid_type = $ph2ent['localid']['type'];
						$localid_data = ipsec_idinfo_to_cidr($ph2ent['localid']);
						if (($localid_type == "none") || ($ph1ent['authentication_method'] == "pre_shared_key") && isset($ph1ent['mobile']))
							$localid_spec = " ";
						else if ($localid_type != "address") {
							$localid_type = "subnet";
							$localid_spec = $localid_type." ".$localid_data." any";
						}

						if (!isset($ph2ent['mobile'])) {
							$remoteid_type = $ph2ent['remoteid']['type'];
							if ($remoteid_type != "address")
								$remoteid_type = "subnet";

							$remoteid_data = ipsec_idinfo_to_cidr($ph2ent['remoteid']);
							$remoteid_spec = $remoteid_type." ".$remoteid_data." any";
						} else
							$remoteid_spec = "anonymous";

					} else {

						$rgip = $rgmap[$ph1ent['remote-gateway']];

						$localid_data = ipsec_get_phase1_src($ph1ent);
						if($ph2ent['mode'] == 'transport') { $localid_data="$localid_data any"; }
						$localid_spec = "address {$localid_data}";

						$remoteid_data = $rgmap[$ph1ent['remote-gateway']];
						if($ph2ent['mode'] == 'transport') { $remoteid_data="$remoteid_data any"; }
						$remoteid_spec = "address {$remoteid_data}";
					}

					if($ph2ent['protocol'] == 'esp') {

						$ealgos = '';

						foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {

							$ealg_id = $ealg['name'];
							$ealg_kl = $ealg['keylen'];

							if ($ealg_kl) {
								if( $ealg_kl == "auto" ) {
									/*   This seems to be required on my system and was not reproducable
									 *   on other systems.   For some reason $p2_ealgos is not defined
									 *   and needs to be read back in!?  -sullrich Aug 26, 2009 
									 */
									if(!$p2_ealgos)
										require("ipsec.inc");
									$key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
									$key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
									$key_step = $p2_ealgos[$ealg_id]['keysel']['step'];
									/* in some cases where include ordering is suspect these variables
									   are somehow 0 and we enter this loop forever and timeout after 900
									   seconds wrecking bootup */
									if($key_hi != 0 and $key_lo !=0 and $key_step !=0) {
										for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
//											Uncomment the next line if you want to test the comment 5 lines up.											
//											echo "$keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step \n";
											if ($ealgos)
												$ealgos = $ealgos.", ";
											$ealgos = $ealgos.$ealg_id." ".$keylen;
										}
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

						$ealgosline = "encryption_algorithm {$ealgos};";

					} else {

						$ealgosline = "encryption_algorithm null_enc;";
					}

					$halgos = join(",", $ph2ent['hash-algorithm-option']);
					$halgosline = "authentication_algorithm {$halgos};";

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

					/* add sainfo section to configuration */
					
					$racoonconf .=<<<EOD
					
sainfo {$localid_spec} {$remoteid_spec}
{
	remoteid {$ikeid};
	{$ealgosline}
	{$halgosline}
	{$pfsline}
	{$lifeline}
	compression_algorithm deflate;
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

			/* Try to prevent people from locking themselves out of webgui. Just in case. */
			if ($config['interfaces']['lan']) {
				$spdconf .= "spdadd {$lanip}/32 {$lansa}/{$lansn} any -P out none;\n";
				$spdconf .= "spdadd {$lansa}/{$lansn} {$lanip}/32 any -P in none;\n";
			}

			foreach ($a_phase2 as $ph2ent) {

				if( !ipsec_lookup_phase1($ph2ent,$ph1ent))
					continue;

				if (isset($ph1ent['mobile']))
					continue;

				if (isset($ph1ent['disabled']))
					continue;

				if (isset($ph2ent['disabled']))
					continue;

				$ep = ipsec_get_phase1_src($ph1ent);
				if (!$ep)
					continue;

				$rgip = $rgmap[$ph1ent['remote-gateway']];

				$localid = ipsec_idinfo_to_cidr($ph2ent['localid'],true);
				$remoteid = ipsec_idinfo_to_cidr($ph2ent['remoteid'],true);

				if (isset ($ph2ent['creategif'])) {
					$number_of_gifs = find_last_gif_device();
					$number_of_gifs++;
					$curwanip = get_interface_ip();
					if ($config['installedpackages']['sasyncd']['config'] <> "") {
						foreach ($config['installedpackages']['sasyncd']['config'] as $sasyncd) {
							if ($sasyncd['ip'] <> "")
								$curwanip = $sasyncd['ip'];
						}
					}
					mwexec("/sbin/ifconfig gif" . $number_of_gifs . " tunnel" . $curwanip . " " . $rgip);
					if ($config['interfaces']['lan'])
						mwexec("/sbin/ifconfig gif" . $number_of_gifs . " {$lansa}/{$lansn} {$lanip}/32");
				}

				if($ph2ent['mode'] == "tunnel") {

					$spdconf .= "spdadd {$localid} {$remoteid} any -P out ipsec " .
						"{$ph2ent['protocol']}/tunnel/{$ep}-{$rgip}/unique;\n";

					$spdconf .= "spdadd {$remoteid} {$localid} any -P in ipsec " .
						"{$ph2ent['protocol']}/tunnel/{$rgip}-{$ep}/unique;\n";

				} else {

					$localid_data = ipsec_get_phase1_src($ph1ent);
					$remoteid_data = $rgmap[$ph1ent['remote-gateway']];

					$spdconf .= "spdadd {$localid_data} {$remoteid_data} any -P out ipsec " .
						"{$ph2ent['protocol']}/transport//require;\n";

					$spdconf .= "spdadd {$remoteid_data} {$localid_data} any -P in ipsec " .
						"{$ph2ent['protocol']}/transport//require;\n";

				}

				/* static route needed? */
				if (preg_match("/^carp|^vip/i", $ph1ent['interface']))
					$parentinterface = link_carp_interface_to_parent($ph1ent['interface']);
				else
					$parentinterface = $ph1ent['interface'];

				if ($parentinterface <> "wan") {
					/* add endpoint routes to correct gateway on interface */
					if (interface_has_gateway($parentinterface)) {
						$gatewayip = get_interface_gateway("$parentinterface");
						$interfaceip = get_interface_ip($parentinterface);
						$subnet_bits = get_interface_subnet($parentinterface);
						$subnet_ip = gen_subnet("{$interfaceip}", "{$subnet_bits}");
						/* if the remote gateway is in the local subnet, then don't add a route */
						if (! ip_in_subnet($rgip, "{$subnet_ip}/{$subnet_bits}")) {
							if(is_ipaddr($gatewayip)) {
								/* FIXME: does adding route-to and reply-to on the in/outbound
								 * rules fix this? smos@ 13-01-2009 */
								log_error("IPSEC interface is not WAN but {$parentinterface}, adding static route for VPN endpoint {$rgip} via {$gatewayip}");
								mwexec("/sbin/route delete -host {$rgip}");
								mwexec("/sbin/route add -host {$rgip} {$gatewayip}");
							}
						}
					}
				} else {
					if(stristr($route_str, "{$rgip}")) {
						mwexec("/sbin/route delete -host {$rgip}", true);
					}
				}
			}

			fwrite($fd, $spdconf);
			fclose($fd);
		}

		/* needed for racoonctl admin socket */
		if (!is_dir("/var/db/racoon"))
			mkdir("/var/db/racoon/");
		
		/* mange racoon process */
		if (is_process_running("racoon")) {
			sleep("0.1");
			mwexec("/usr/local/sbin/racoonctl -s /var/db/racoon/racoon.sock reload-config", false);
			/* load SPD without flushing to be safe on config additions or changes. */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/spd.conf", false);
		} else {
			/* flush SA + SPD entries */
			mwexec("/usr/local/sbin/setkey -FP", false);
 			sleep("0.1");
			mwexec("/usr/local/sbin/setkey -F", false);
 			sleep("0.1");
//			exec("/sbin/sysctl net.inet.ip.ipsec_in_use=1");
 			/* start racoon */
			mwexec("/usr/local/sbin/racoon -f {$g['varetc_path']}/racoon.conf", false);
 			sleep("0.1");
 			/* load SPD */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/spd.conf", false);

			/* start dnswatch, if necessary */
			if (count($dnswatch_list) > 0) {
				$interval = 60;
				if ($ipseccfg['dns-interval'])
					$interval = $ipseccfg['dns-interval'];

				$hostnames = "";
				array_unique($dnswatch_list);
				$hostnames = implode("\n", $dnswatch_list);
				file_put_contents("{$g['varetc_path']}/dnswatch-ipsec.hosts", $hostnames);

				killbypid("{$g['varrun_path']}/dnswatch-ipsec.pid");
				mwexec("/usr/local/sbin/dnswatch {$g['varrun_path']}/dnswatch-ipsec.pid $interval /etc/rc.newipsecdns {$g['varetc_path']}/dnswatch-ipsec.hosts");
			}
		}
	
		vpn_ipsec_failover_configure();

		if ($g['booting'])
			echo "done\n";
	}

	return 0;
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
	if(is_process_running("racoon"))
		mwexec("/usr/bin/killall racoon", true);

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

			$mpdconf = <<<EOD
pptpd:

EOD;

			for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {
				$mpdconf .= "	load pt{$i}\n";
			}

			for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {

				$clientip = long2ip(ip2long($pptpdcfg['remoteip']) + $i);

				$mpdconf .= <<<EOD

pt{$i}:
	new -i pptpd{$i} pt{$i} pt{$i}
	set ipcp ranges {$pptpdcfg['localip']}/32 {$clientip}/32
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

			if  (isset($pptpdcfg["wins"]) && $pptpdcfg['wins'] != "")
				$mpdconf  .=  "	set ipcp nbns {$pptpdcfg['wins']}\n";
			if (is_array($pptpdcfg['dnsserver']) && ($pptpdcfg['dnsserver'][0])) {
				$mpdconf .= "	set ipcp dns " . join(" ", $pptpdcfg['dnsserver']) . "\n";
			} else
				if (isset ($config['dnsmasq']['enable'])) {
					$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
					if ($syscfg['dnsserver'][0])
						$mpdconf .= " " . $syscfg['dnsserver'][0];
					$mpdconf .= "\n";
				} else
					if (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
						$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
					}

			if (isset ($pptpdcfg['radius']['server']['enable'])) {
				$authport = (isset($pptpdcfg['radius']['server']['port']) && strlen($pptpdcfg['radius']['server']['port']) > 1) ? $pptpdcfg['radius']['server']['port'] : 1812;
				$acctport = $authport + 1;
				$mpdconf .=<<<EOD
	set radius server {$pptpdcfg['radius']['server']['ip']} "{$pptpdcfg['radius']['server']['secret']}" {$authport} {$acctport}

EOD;
			if (isset ($pptpdcfg['radius']['server2']['enable'])) {
				$authport = (isset($pptpdcfg['radius']['server2']['port']) && strlen($pptpdcfg['radius']['server2']['port']) > 1) ? $pptpdcfg['radius']['server2']['port'] : 1812;
				$acctport = $authport + 1;
				$mpdconf .=<<<EOD
	set radius server {$pptpdcfg['radius']['server2']['ip']} "{$pptpdcfg['radius']['server2']['secret']}" {$authport} {$acctport}

EOD;
			}
			$mpdconf .=<<<EOD
	set radius retries 3
	set radius timeout 10
	set auth enable radius-auth

EOD;

				if (isset ($pptpdcfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set auth enable radius-acct
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

			for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {
				$mpdlinks .=<<<EOD

pt{$i}:
	set link type pptp
	set pptp enable incoming
	set pptp disable originate
	set pptp disable windowing

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
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pptp-vpn -p {$g['varrun_path']}/pptp-vpn.pid -s pptp -f mpd.conf pptpd");

			break;

		case 'redir' :
			break;
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

			$pppoe_interface = interface_translate_type_to_real($pppoecfg['interface']);

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
				} else {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

pppoe{$i}:
	new -i ppoed{$i} pppoe{$i} pppoe{$i}
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
				$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
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
	set radius retries 3
	set radius timeout 10
	set auth enable radius-auth

EOD;

				if (isset ($pppoecfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set auth enable radius-acct

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
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pppoe-vpn -p {$g['varrun_path']}/pppoe-vpn.pid -s pppoe pppoe");

			break;

		case 'redir' :
			break;
	}

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function vpn_l2tp_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$l2tpcfg = $config['l2tp'];

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
		sleep(8);

	}

	/* make sure l2tp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/l2tp-vpn"))
		mkdir("{$g['varetc_path']}/l2tp-vpn");

	switch ($l2tpcfg['mode']) {

		case 'server' :
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
				$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
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
	set radius retries 3
	set radius timeout 10
	set auth enable radius-auth

EOD;

				if (isset ($l2tpcfg['radius']['accounting'])) {
					$mpdconf .=<<<EOD
	set auth enable radius-acct

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

l2tp{$i}:
	set link type l2tp
        set l2tp enable incoming
        set l2tp disable originate

EOD;
			if (!empty($l2tpcfg['secret']))
					$mpdlinks .= "set l2tp secret {$l2tpcfg['secret']}\n";
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
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid -s l2tp l2tp");

			break;

		case 'redir' :
			break;
	}

	if ($g['booting'])
		echo "done\n";

	return 0;
}

/* Walk the tunnels for hostname endpoints. If the hostnames 
 * resolve to a different IP now compared to the DNS cache
 * we reload the policies if the endpoint has changed */
function vpn_ipsec_refresh_policies() {
	global $config;
	global $g;

	$ipseccfg = $config['ipsec'];
	$a_phase1 = $config['ipsec']['phase1'];
	$a_phase2 = $config['ipsec']['phase2'];

	if (isset($ipseccfg['disable'])) {
		return true;
	}

	/* Walk the Ipsec tunnel array */
	if (!is_array($a_phase1) || (!count($a_phase1))) {
		return;
	}

	foreach ($a_phase1 as $phase1) {
		if (isset($phase1['disabled'])) {
			continue;
		}
		if (is_ipaddr($phase1['remote-gateway'])) {
			continue;
		}
		if (!is_ipaddr($phase1['remote-gateway'])) {
			$dnscache = compare_hostname_to_dnscache($phase1['remote-gateway']);
			$dnscache = trim($dnscache);
			/* we should have the old IP addresses in the dnscache now */
			if($dnscache <> "") {
				$oldphase1 = $phase1;
				$oldphase1['remote-gateway'] = trim($dnscache);
				/* now we need to find all tunnels for this host */
				if (!is_array($a_phase2) || (!count($a_phase2))) {
					continue;
				}
				foreach ($a_phase2 as $phase2) {
					if($phase2['ikeid'] == $phase1['ikeid']) {
						reload_tunnel_spd_policy ($phase1, $phase2, $oldphase1, $oldphase2);
					}
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
		if(preg_match("/^spd.conf.reload./", $filename)) {
			$tmpfiles[] = $filename;
		}
	}
	sort($tmpfiles);
	foreach($tmpfiles as $tmpfile) {
		$ret = mwexec("/usr/local/sbin/setkey -f {$g['tmp_path']}/{$tmpfile} 2>&1", false);
		if($ret == 0) {
			unlink_if_exists("{$g['tmp_path']}/{$tmpfile}");
		} else {
			rename("{$g['tmp_path']}/{$tmpfile}", ("{$g['tmp_path']}/failed.{$tmpfile}"));
		}
	}
}

/* reloads the tunnel configuration for a tunnel item
 * Will remove and add SPD polices */
function reload_tunnel_spd_policy($phase1, $phase2, $old_phase1, $old_phase2) {
	global $config;
	global $g;

	/* if we are not passed a old tunnel array we create one */
	if(empty($old_phase1)) {
		$old_phase1 = $phase1;
	}
	if(empty($old_phase2)) {
		$old_phase2 = $phase2;
	}

	$sad_arr = ipsec_dump_sad();

	$ep = ipsec_get_phase1_src($phase1);
	$local_subnet = ipsec_idinfo_to_cidr($phase2['localid']);
	$remote_subnet = ipsec_idinfo_to_cidr($phase2['remoteid']);

	/* make sure we pass the oldtunnel array with a IP for the remote gw */
	$old_gw = trim($old_phase1['remote-gateway']);

	$old_ep = ipsec_get_phase1_src($old_phase1);
	$old_local_subnet = ipsec_idinfo_to_cidr($old_phase2['localid']);
	$old_remote_subnet = ipsec_idinfo_to_cidr($old_phase2['remoteid']);

	/* see if this tunnel has a hostname for the remote-gateway, and if so,
	 * try to resolve it now and add it to the list for dnswatch */
	if (!is_ipaddr($phase1['remote-gateway'])) {
		$rgip = resolve_retry($phase1['remote-gateway']);
		add_hostname_to_watch($phase1['remote-gateway']);
		if (!$rgip) {
			log_error("Could not determine VPN endpoint for '{$phase1['descr']}'");
			return false;
		}
	} else {
		$rgip = $phase1['remote-gateway'];
	}
	if (!$ep) {
		log_error("Could not determine VPN endpoint for '{$phase1['descr']}'");
		return false;
	}

	if((!is_ipaddr($old_ep)) || (! is_ipaddr($ep))) {
		log_error("IPSEC: ERROR: One of the endpoints is not a IP address. Old EP '{$old_ep}' new EP '{$ep}'");
	}
	if((! is_ipaddr($rgip)) || (! is_ipaddr($old_gw))) {
		log_error("IPSEC: ERROR: One of the remote endpoints is not a IP address. Old RG '{$old_gw}' new RG '{$rgip}'");
	}

	$spdconf = "";
	/* Delete old SPD policies if there are changes between the old and new */
	if(($phase1 != $old_phase1) || ($phase2 != $old_phase2)) {
		$spdconf .= "spddelete {$old_local_subnet} " .
			"{$old_remote_subnet} any -P out ipsec " .
			"{$old_phase2['protocol']}/tunnel/{$old_ep}-" .
			"{$old_gw}/unique;\n";
		$spdconf .= "spddelete {$old_remote_subnet} " .
			"{$old_local_subnet} any -P in ipsec " .
			"{$old_phase2['protocol']}/tunnel/{$old_gw}-" .
			"{$old_ep}/unique;\n";

		/* zap any existing SA entries */
		foreach($sad_arr as $sad) {
			if(($sad['dst'] == $old_ep) && ($sad['src'] == $old_gw)) {
				$spdconf .= "delete {$old_ep} {$old_gw} {$old_phase2['protocol']} 0x{$sad['spi']};\n";
			}
			if(($sad['src'] == $oldep) && ($sad['dst'] == $old_gw)) {
				$spdconf .= "delete {$old_gw} {$old_ep} {$old_phase2['protocol']} 0x{$sad['spi']};\n";
			}
		}
	}

	/* Create new SPD entries for the new configuration */
	/* zap any existing SA entries beforehand */
	foreach($sad_arr as $sad) {
		if(($sad['dst'] == $ep) && ($sad['src'] == $rgip)) {
			$spdconf .= "delete {$rgip} {$ep} {$phase2['protocol']} 0x{$sad['spi']};\n";
		}
		if(($sad['src'] == $ep) && ($sad['dst'] == $rgip)) {
			$spdconf .= "delete {$ep} {$rgip} {$phase2['protocol']} 0x{$sad['spi']};\n";
		}
	}
	/* add new SPD policies to replace them */
	$spdconf .= "spdadd {$local_subnet} " .
		"{$remote_subnet} any -P out ipsec " .
		"{$phase2['protocol']}/tunnel/{$ep}-" .
		"{$rgip}/unique;\n";
	$spdconf .= "spdadd {$remote_subnet} " .
		"{$local_subnet} any -P in ipsec " .
		"{$phase2['protocol']}/tunnel/{$rgip}-" .
		"{$ep}/unique;\n";

	log_error("Reloading IPsec tunnel '{$phase1['descr']}'. Previous IP '{$old_gw}', current IP '{$rgip}'. Reloading policy");

	$now = time();
	$spdfile = tempnam("{$g['tmp_path']}", "spd.conf.reload.{$now}.");
	/* generate temporary spd.conf */
	file_put_contents($spdfile, $spdconf);
	return true;
}

?>
