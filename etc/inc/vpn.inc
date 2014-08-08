<?php

/*
	vpn.inc
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2008 Ermal Lu�i
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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/setkey	/sbin/route	/bin/mkdir
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/racoonctl	/usr/local/sbin/racoon
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/filterdns	/usr/local/sbin/mpd4
	pfSense_MODULE:	vpn
*/

require_once("ipsec.inc");

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

function vpn_ipsec_configure($ipchg = false)
{
	global $config, $g, $sa, $sn, $p1_ealgos, $p2_ealgos;

	if ($g['platform'] == 'jail')
		return;

	/* get the automatic ping_hosts.sh ready */
	unlink_if_exists("{$g['vardb_path']}/ipsecpinghosts");
	touch("{$g['vardb_path']}/ipsecpinghosts");

	vpn_ipsec_configure_preferoldsa();

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];
	$a_phase1 = $config['ipsec']['phase1'];
	$a_phase2 = $config['ipsec']['phase2'];
	$a_client = $config['ipsec']['client'];

	if (!isset($ipseccfg['enable'])) {
		/* try to stop racoon*/
		killbypid("{$g['varrun_path']}/racoon.pid");
		/* Stop dynamic monitoring */
		killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");

		/* kill racoon forcefully */
		if (is_process_running("racoon"))
			mwexec("/usr/bin/killall -9 racoon", true);

		/* wait for racoon process to die */
		sleep(2);

		/* flush SPD and SAD */
		mwexec("/usr/local/sbin/setkey -F");
		mwexec("/usr/local/sbin/setkey -FP");

		/* disallow IPSEC, it is off */
		mwexec("/sbin/ifconfig enc0 down");
		exec("/sbin/sysctl net.inet.ip.ipsec_in_use=0");

		return 0;
	} else {
		mwexec("/sbin/ifconfig enc0 up");
		mwexec("/sbin/sysctl net.inet.ip.ipsec_in_use=1");
		/* needed for racoonctl admin socket */
		if (!is_dir("/var/db/racoon"))
			mkdir("/var/db/racoon/");
		/* needed for config files */
		if (!is_dir("{$g['varetc_path']}/ipsec"))
			mkdir("{$g['varetc_path']}/ipsec");

		if ($g['booting'])
			echo gettext("Configuring IPsec VPN... ");

		/* fastforwarding is not compatible with ipsec tunnels */
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=0");

		/* resolve all local, peer addresses and setup pings */
		$ipmap = array();
		$rgmap = array();
		$filterdns_list = array();
		unset($iflist);
		if (is_array($a_phase1) && count($a_phase1)) {

			$ipsecpinghosts = "";
			/* step through each phase1 entry */
			foreach ($a_phase1 as $ph1ent) {
				if (isset($ph1ent['disabled']))
					continue;

				$ep = ipsec_get_phase1_src($ph1ent);
				if (!is_ipaddr($ep))
					continue;

				if(!in_array($ep,$ipmap))
					$ipmap[] = $ep;

				/* see if this tunnel has a hostname for the remote-gateway. If so,
				   try to resolve it now and add it to the list for filterdns */

				if (isset ($ph1ent['mobile']))
					continue;

				$rg = $ph1ent['remote-gateway'];

				if (!is_ipaddr($rg)) {
					$filterdns_list[] = "{$rg}";
					add_hostname_to_watch($rg);
					if(! $g['booting'])
						$rg = resolve_retry($rg);
					if (!is_ipaddr($rg))
						continue;
				}
				if(array_search($rg, $rgmap)) {
					log_error("The remote gateway {$rg} already exists on another phase 1 entry");
					continue;
				}
				$rgmap[$ph1ent['remote-gateway']] = $rg;

				if (is_array($a_phase2)) {
					/* step through each phase2 entry */
					foreach ($a_phase2 as $ph2ent) {
						$ikeid = $ph2ent['ikeid'];

						if (isset($ph2ent['disabled']))
							continue;

						if ($ikeid != $ph1ent['ikeid'])
							continue;

						/* add an ipsec pinghosts entry */
						if ($ph2ent['pinghost']) {
							if (!is_array($iflist))
								$iflist = get_configured_interface_list();
							$viplist = get_configured_vips_list();
							$srcip = null;
							$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
							if(is_ipaddrv6($ph2ent['pinghost'])) {
								foreach ($iflist as $ifent => $ifname) {
									$interface_ip = get_interface_ipv6($ifent);
									if(!is_ipaddrv6($interface_ip))
										continue;
									$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
									if (ip_in_subnet($interface_ip, $local_subnet)) {
										$srcip = $interface_ip;
										break;
									}
								}
							} else {
								foreach ($iflist as $ifent => $ifname) {
									$interface_ip = get_interface_ip($ifent);
									if(!is_ipaddrv4($interface_ip))
										continue;
									if ($local_subnet == "0.0.0.0/0" || ip_in_subnet($interface_ip, $local_subnet)) {
										$srcip = $interface_ip;
										break;
									}
								}
							}
							/* if no valid src IP was found in configured interfaces, try the vips */
							if (is_null($srcip)) {
								foreach ($viplist as $vip) {
									if (ip_in_subnet($vip['ipaddr'], $local_subnet)) {
										$srcip = $vip['ipaddr'];
										break;
									}
								}
							}
							$dstip = $ph2ent['pinghost'];
							if(is_ipaddrv6($dstip)) {
								$family = "inet6";
							} else {
								$family = "inet";
							}
							if (is_ipaddr($srcip))
								$ipsecpinghosts[] = "{$srcip}|{$dstip}|3|||||{$family}|\n";
						}
					}
				}
			}
			@file_put_contents("{$g['vardb_path']}/ipsecpinghosts", $ipsecpinghosts);
			unset($ipsecpinghosts);
		}

		/* generate CA certificates files */
		if (is_array($config['ca']) && count($config['ca'])) {
			foreach ($config['ca'] as $ca) {
				if (!isset($ca['crt'])) {
					log_error(sprintf(gettext("Error: Invalid certificate info for %s"), $ca['descr']));
					continue;
				}
				$cert = base64_decode($ca['crt']);
				$x509cert = openssl_x509_parse(openssl_x509_read($cert));
				if (!is_array($x509cert) || !isset($x509cert['hash'])) {
					log_error(sprintf(gettext("Error: Invalid certificate hash info for %s"), $ca['descr']));
					continue;
				}
				$fname = "{$g['varetc_path']}/ipsec/{$x509cert['hash']}.0";
				if (!@file_put_contents($fname, $cert)) {
					log_error(sprintf(gettext("Error: Cannot write IPsec CA file for %s"), $ca['descr']));
					continue;
				}
				unset($cert);
			}
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

				if (!empty($peerid_data) && !empty($ph1ent['pre-shared-key']))
					$pskconf .= trim($peerid_data) . "\t" . trim($ph1ent['pre-shared-key']) . "\n";
			}
		}

		/* Add user PSKs */
		foreach ($config['system']['user'] as $user) {
			if (!empty($user['ipsecpsk'])) {
				$pskconf .= "{$user['name']}\t{$user['ipsecpsk']}\n";
			}
		}

		/* add PSKs for mobile clients */
		if (is_array($ipseccfg['mobilekey'])) {
			foreach ($ipseccfg['mobilekey'] as $key) {
				$pskconf .= "{$key['ident']}\t{$key['pre-shared-key']}\n";
			}
		}

		@file_put_contents("{$g['varetc_path']}/ipsec/psk.txt", $pskconf);
		chmod("{$g['varetc_path']}/ipsec/psk.txt", 0600);
		unset($pskconf);

		/* begin racoon.conf */
		$racoonconf = "";
		$peerid_verify = "";
		if ((is_array($a_phase1) && count($a_phase1)) || (is_array($a_phase2) && count($a_phase2))) {

			$racoonconf .= "# This file is automatically generated. Do not edit\n";
			$racoonconf .= "path pre_shared_key \"{$g['varetc_path']}/ipsec/psk.txt\";\n\n";
			$racoonconf .= "path certificate  \"{$g['varetc_path']}/ipsec\";\n\n";

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

				if (!empty($a_client['user_source']))
					$racoonconf .= "\tauth_source external;\n";
				if (!empty($a_client['group_source']) && $a_client['group_source'] != "none")
					$racoonconf .= "\tgroup_source {$a_client['group_source']};\n";

				if ($a_client['pool_address'] && $a_client['pool_netbits']) {
					$pool_address = $a_client['pool_address'];
					$pool_netmask = gen_subnet_mask($a_client['pool_netbits']);

					$pool_address = long2ip32(ip2long($pool_address)+1);
					$pool_size = (~ip2long($pool_netmask) & 0xFFFFFFFF) - 2;

					if ($pool_size < 0) {
						log_error(sprintf(gettext("Invalid mobile IPsec pool size: %s, using 0"), $pool_size));
						$pool_size = 0;
					}

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

						$localid = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);

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

				if ($a_client['dns_domain']) {
					$racoonconf .= "\tdefault_domain \"{$a_client['dns_domain']}\";\n";
					if (empty($a_client['dns_split']))
						$racoonconf .= "\tsplit_dns \"{$a_client['dns_domain']}\";\n";
				}

				if ($a_client['dns_split']) {
					$domain_array = preg_split("/[ ,]+/",$a_client['dns_split']);
					$domain_string = implode('", "', $domain_array);
					$racoonconf .= "\tsplit_dns \"{$domain_string}\";\n";
				}

				if ($a_client['pfs_group'])
					$racoonconf .= "\tpfs_group {$a_client['pfs_group']};\n";

				if ($a_client['login_banner']) {
					@file_put_contents("{$g['varetc_path']}/ipsec/racoon.motd", $a_client['login_banner']);
					$racoonconf .= "\tbanner \"{$g['varetc_path']}/ipsec/racoon.motd\";\n";
				}

				if (isset($a_client['save_passwd']))
					$racoonconf .= "\tsave_passwd on;\n";

				$racoonconf .= "}\n\n";
			}
			/* end mode_cfg section */

			if ($a_client['user_source'] != "none") {
				$authcfgs = explode(",", $a_client['user_source']);
				$sed = "\$authmodes=array(";
				$firstsed = 0;
				foreach ($authcfgs as $authcfg) {
					if ($authcfg == "system")
						$authcfg = "Local Database";
					if ($firstsed > 0)
						$sed .= ",";
					$firstsed = 1;
					$sed .= "\"{$authcfg}\"";
				}
				$sed .= ");\\\n";
				if ($a_client['strictusercn'])
					$sed .= "\$strictusercn = true;";
				mwexec("/bin/cat /etc/inc/ipsec.auth-user.php | /usr/bin/sed 's/\/\/<template>/{$sed}/g' >  {$g['varetc_path']}/ipsec/ipsec.php");
				mwexec("/bin/chmod a+x {$g['varetc_path']}/ipsec/ipsec.php");
				$racoonconf .= "extcfg { script \"{$g['varetc_path']}/ipsec/ipsec.php\" }\n";
			}

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
							$myid_data = resolve_retry($ph1ent['myid_data']);
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
					$genp = !empty($ph1ent['generate_policy']) ? $ph1ent['generate_policy'] : "off";
					$pcheck = !empty($ph1ent['proposal_check']) ? $ph1ent['proposal_check'] : $pcheck = "claim";
					$passive = "";
					if (isset($ph1ent['mobile'])) {
						$rgip = "anonymous";
						$passive = "passive on;";
						$pcheck = !empty($ph1ent['proposal_check']) ? $ph1ent['proposal_check'] : $pcheck = "obey";
						/* Mimic 1.2.3's behavior for pure-psk mobile tunnels */
						if ($ph1ent['authentication_method'] == "pre_shared_key") {
							$genp = !empty($ph1ent['generate_policy']) ? $ph1ent['generate_policy'] : "on";
						} else {
							$init = "off";
							$genp = !empty($ph1ent['generate_policy']) ? $ph1ent['generate_policy'] : "unique";
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
							log_error(sprintf(gettext("Error: Invalid phase1 certificate reference for %s"), $ph1ent['name']));
							continue;
						}

						$certfile = "cert-{$ikeid}.crt";
						$certpath = "{$g['varetc_path']}/ipsec/{$certfile}";

						if (!file_put_contents($certpath, base64_decode($cert['crt'])))
						{
							log_error(sprintf(gettext("Error: Cannot write phase1 certificate file for %s"), $ph1ent['name']));
							continue;
						}

						chmod($certpath, 0600);

						$keyfile = "cert-{$ikeid}.key";
						$keypath = "{$g['varetc_path']}/ipsec/{$keyfile}";

						if (!file_put_contents($keypath, base64_decode($cert['prv'])))
						{
							log_error(sprintf(gettext("Error: Cannot write phase1 key file for %s"), $ph1ent['name']));
							continue;
						}

						chmod($keypath, 0600);

						$ca = lookup_ca($ph1ent['caref']);
						if ($ca) {
							$cafile = "ca-{$ikeid}.crt";
							$capath = "{$g['varetc_path']}/ipsec/{$cafile}";

							if (!file_put_contents($capath, base64_decode($ca['crt'])))
							{
								log_error(sprintf(gettext("Error: Cannot write phase1 CA certificate file for %s"), $ph1ent['name']));
								continue;
							}

							chmod($capath, 0600);
							$caline = "ca_type x509 \"{$cafile}\";";
						}

						$certline = "certificate_type x509 \"{$certfile}\" \"{$keyfile}\";";

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
						if (isset($ph1ent['verify_identifier']))
							$peerid_verify = "verify_identifier on;";
					}

					/* add remote section to configuration */

					$racoonconf .=<<<EOD

remote {$rgip}
{
	ph1id {$ikeid};
	exchange_mode {$ph1ent['mode']};
	my_identifier {$myid_type} {$myid_data};
	{$peerid_spec}
	{$peerid_verify}
	ike_frag on;
	generate_policy = {$genp};
	initial_contact = {$init};
	nat_traversal = {$natt};
	{$certline}
	{$caline}
	{$dpdline1}
	{$dpdline2}
	support_proxy on;
	proposal_check {$pcheck};
	{$passive}

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

					if (($ph2ent['mode'] == 'tunnel') or ($ph2ent['mode'] == 'tunnel6')) {

						$localid_type = $ph2ent['localid']['type'];
						$localid_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
						/* Do not print localid in some cases, such as a pure-psk or psk/xauth single phase2 mobile tunnel */
						if (($localid_type == "none") ||
							(($ph1ent['authentication_method'] == "xauth_psk_server") ||
							($ph1ent['authentication_method'] == "pre_shared_key"))
							&& isset($ph1ent['mobile'])
							&& (ipsec_get_number_of_phase2($ikeid)==1))
							$localid_spec = " ";
						else {
							if ($localid_type != "address") {
								$localid_type = "subnet";
							}
							// Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
							if (!is_ipaddr($localid_data) && !is_subnet($localid_data) && ($localid_data != "0.0.0.0/0")) {
								log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
								continue;
							}
							$localid_spec = "{$localid_type} {$localid_data} any";
							if (!empty($ph2ent['natlocalid'])) {
								$natlocalid_data =  ipsec_idinfo_to_cidr($ph2ent['natlocalid'], false, $ph2ent['mode']);
								if ($ph2ent['natlocalid']['type'] != "address") {
									if (is_subnet($natlocalid_data))
										$localid_spec .= " nat subnet {$natlocalid_data} any";
								} else {
									if (is_ipaddr($natlocalid_data))
										$localid_spec .= " nat address {$natlocalid_data} any";
								}
							}
						}

						if (!isset($ph2ent['mobile'])) {
							$remoteid_type = $ph2ent['remoteid']['type'];
							if ($remoteid_type != "address")
								$remoteid_type = "subnet";

							$remoteid_data = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
							$remoteid_spec = $remoteid_type." ".$remoteid_data." any";
						} else
							$remoteid_spec = "anonymous";

					} else {
						$rgip = $rgmap[$ph1ent['remote-gateway']];

						if ((($ph1ent['authentication_method'] == "xauth_psk_server") ||
							($ph1ent['authentication_method'] == "pre_shared_key"))
							&& isset($ph1ent['mobile']))
							$localid_spec = " ";
						else {
							$localid_data = ipsec_get_phase1_src($ph1ent);
							if($ph2ent['mode'] == 'transport') { $localid_data="$localid_data any"; }
							$localid_spec = "address {$localid_data}";
						}
						if (!isset($ph2ent['mobile'])) {
							$remoteid_data = $rgmap[$ph1ent['remote-gateway']];
							if($ph2ent['mode'] == 'transport') { $remoteid_data="$remoteid_data any"; }
							$remoteid_spec = "address {$remoteid_data}";
						} else
							$remoteid_spec = "anonymous";
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
		}
		@file_put_contents("{$g['varetc_path']}/ipsec/racoon.conf", $racoonconf);
		unset($racoonconf);
		/* end racoon.conf */

		/* generate IPsec policies */
		/* generate spd.conf */
		$spdconf = "";
		$natfilterrules = false;
		if (is_array($a_phase2) && count($a_phase2)) {
			/* Try to prevent people from locking themselves out of webgui. Just in case. */
			if ($config['interfaces']['lan']) {
				$lanip = get_interface_ip("lan");
				if (!empty($lanip) && is_ipaddrv4($lanip)) {
					$lansn = get_interface_subnet("lan");
					$lansa = gen_subnet($lanip, $lansn);
					$spdconf .= "spdadd -4 {$lanip}/32 {$lansa}/{$lansn} any -P out none;\n";
					$spdconf .= "spdadd -4 {$lansa}/{$lansn} {$lanip}/32 any -P in none;\n";
				}
				$lanipv6 = get_interface_ipv6("lan");
				if (!empty($lanipv6) && is_ipaddrv6($lanipv6)) {
					$lansnv6 = get_interface_subnetv6("lan");
					$lansav6 = gen_subnetv6($lanipv6, $lansnv6);
					$spdconf .= "spdadd -6 {$lanipv6}/128 {$lansav6}/{$lansnv6} any -P out none;\n";
					$spdconf .= "spdadd -6 {$lansav6}/{$lansnv6} {$lanipv6}/128 any -P in none;\n";
				}
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
				if(!is_ipaddr($rgip))
					continue;

				$localid = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
				$remoteid = ipsec_idinfo_to_cidr($ph2ent['remoteid'], true, $ph2ent['mode']);

				if(($ph2ent['mode'] == "tunnel") or ($ph2ent['mode'] == 'tunnel6')) {
					// Error will be logged above, no need to log this twice. #2201
					if (!is_subnet($localid) && ($localid != "0.0.0.0/0"))
						continue;

					if($ph2ent['mode'] == "tunnel6")
						$family = "-6";
					else
						$family = "-4";

					$spdconf .= "spdadd {$family} {$localid} {$remoteid} any -P out ipsec " .
						"{$ph2ent['protocol']}/tunnel/{$ep}-{$rgip}/unique;\n";

					if (!empty($ph2ent['natlocalid'])) {
						$natlocalid = ipsec_idinfo_to_cidr($ph2ent['natlocalid'], true, $ph2ent['mode']);
						$spdconf .= "spdadd {$family} {$remoteid} {$natlocalid} any -P in ipsec " .
							"{$ph2ent['protocol']}/tunnel/{$rgip}-{$ep}/unique;\n";
						$natfilterrules = true;
					} else
						$spdconf .= "spdadd {$family} {$remoteid} {$localid} any -P in ipsec " .
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
				if (is_ipaddr($ph1ent['interface'])) {
					$vip = find_virtual_ip_alias($ph1ent['interface']);
					if (preg_match("/^carp|^[a-z0-9]+_vip/i", $vip['interface']))
						$parentinterface = link_carp_interface_to_parent($vip['interface']);
					else
						$parentinterface = $vip['interface'];
				} else if (preg_match("/^carp|^[a-z0-9]+_vip/i", $ph1ent['interface']))
					$parentinterface = link_carp_interface_to_parent($ph1ent['interface']);
				else
					$parentinterface = $ph1ent['interface'];

				if (is_ipaddr($rgip)) {
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
								// log_error("IPSEC interface is not WAN but {$parentinterface}, adding static route for VPN endpoint {$rgip} via {$gatewayip}");
								mwexec("/sbin/route change -host {$rgip} {$gatewayip}", true);
							}
						}
					}
				}
			}
		}
		@file_put_contents("{$g['varetc_path']}/ipsec/spd.conf", $spdconf);
		unset($spdconf);

		/* mange racoon process */
		if (is_process_running("racoon")) {
			sleep("0.1");
			mwexec("/usr/local/sbin/racoonctl -s /var/db/racoon/racoon.sock reload-config", false);
			/* load SPD without flushing to be safe on config additions or changes. */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/ipsec/spd.conf", false);
		} else {
			/* flush SA + SPD entries */
			mwexec("/usr/local/sbin/setkey -FP", false);
			sleep("0.1");
			mwexec("/usr/local/sbin/setkey -F", false);
			sleep("0.1");
			/* start racoon */
			$ipsecdebug = isset($config['ipsec']['racoondebug']) ? "-d -v" : "";
			mwexec("/usr/local/sbin/racoon {$ipsecdebug} -f {$g['varetc_path']}/ipsec/racoon.conf", false);
			sleep("0.1");
			/* load SPD */
			mwexec("/usr/local/sbin/setkey -f {$g['varetc_path']}/ipsec/spd.conf", false);

		}
		if ($natfilterrules == true)
			filter_configure();
		/* start filterdns, if necessary */
		if (count($filterdns_list) > 0) {
			$interval = 60;
			if (!empty($ipseccfg['dns-interval']) && is_numeric($ipseccfg['dns-interval']))
				$interval = $ipseccfg['dns-interval'];

			$hostnames = "";
			array_unique($filterdns_list);
			foreach ($filterdns_list as $hostname)
				$hostnames .= "cmd {$hostname} '/usr/local/sbin/pfSctl -c \"service reload ipsecdns\"'\n";
			file_put_contents("{$g['varetc_path']}/ipsec/filterdns-ipsec.hosts", $hostnames);
			unset($hostnames);

			if (isvalidpid("{$g['varrun_path']}/filterdns-ipsec.pid"))
				sigkillbypid("{$g['varrun_path']}/filterdns-ipsec.pid", "HUP");
			else {
				mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-ipsec.pid -i {$interval} -c {$g['varetc_path']}/ipsec/filterdns-ipsec.hosts -d 1");
			}
		} else {
			killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");
			@unlink("{$g['varrun_path']}/filterdns-ipsec.pid");
		}

		vpn_ipsec_failover_configure();

		if ($g['booting'])
			echo "done\n";

		return count($filterdns_list);
	}
}

/*
 * Forcefully restart IPsec
 * This is required for when dynamic interfaces reload
 * For all other occasions the normal vpn_ipsec_configure()
 * will gracefully reload the settings without restarting
 */
function vpn_ipsec_force_reload($interface = "") {
	global $g, $config;

	$ipseccfg = $config['ipsec'];

	if (!empty($interface) && is_array($ipseccfg['phase1'])) {
		$found = false;
		foreach ($ipseccfg['phase1'] as $ipsec) {
			if (!isset($ipsec['disabled']) && ($ipsec['interface'] == $interface)) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			log_error(sprintf(gettext("Ignoring IPsec racoon daemon reload since there are no tunnels on interface %s"), $interface));
			return;
		}
	}

	/* send a SIGKILL to be sure */
	killbypid("{$g['varrun_path']}/racoon.pid");

	/* wait for process to die */
	sleep(4);

	/* kill racoon forcefully */
	if (is_process_running("racoon"))
		mwexec("/usr/bin/killall -9 racoon", true);

	/* wait for flushing to finish */
	sleep(1);

	/* if ipsec is enabled, start up again */
	if (isset($ipseccfg['enable'])) {
		log_error(gettext("Forcefully reloading IPsec racoon daemon"));
		vpn_ipsec_configure();
	}
}

/* master setup for vpn (mpd) */
function vpn_setup() {
	global $g;

	if ($g['platform'] == 'jail')
		return;

	/* start pptpd */
	vpn_pptpd_configure();

	/* start pppoe server */
	vpn_pppoes_configure();

	/* setup l2tp */
	vpn_l2tp_configure();
}

function vpn_netgraph_support() {
	$iflist = get_configured_interface_list();
	foreach ($iflist as $iface) {
		$realif = get_real_interface($iface);
		/* Get support for netgraph(4) from the nic */
		$ifinfo = pfSense_get_interface_addresses($realif);
		if (!empty($ifinfo) && in_array($ifinfo['iftype'], array("ether", "vlan", "bridge")))
			pfSense_ngctl_attach(".", $realif);
	}
}

function vpn_pptpd_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$pptpdcfg = $config['pptpd'];

	if ($g['booting']) {
		if (!$pptpdcfg['mode'] || ($pptpdcfg['mode'] == "off"))
			return 0;

		echo gettext("Configuring PPTP VPN service... ");
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/pptp-vpn.pid");

		/* wait for process to die */
		sleep(3);

		if (is_process_running("mpd -b")) {
			killbypid("{$g['varrun_path']}/pptp-vpn.pid");
			log_error(gettext("Could not kill mpd within 3 seconds.   Trying again."));
		}

		/* remove mpd.conf, if it exists */
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.conf");
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.links");
		unlink_if_exists("{$g['varetc_path']}/pptp-vpn/mpd.secret");
	}

	if (empty($pptpdcfg['n_pptp_units'])) {
		log_error("Something wrong in the PPTPd configuration. Preventing starting the daemon because issues would arise.");
		return;
	}

	/* make sure pptp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/pptp-vpn"))
		mkdir("{$g['varetc_path']}/pptp-vpn");

	switch ($pptpdcfg['mode']) {
		case 'server' :
			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.conf", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.conf in vpn_pptpd_configure().") . "\n");
				return 1;
			}

			$mpdconf = <<<EOD
pptps:

EOD;

			for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {
				$mpdconf .= "	load pt{$i}\n";
			}

			for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {

				$clientip = long2ip32(ip2long($pptpdcfg['remoteip']) + $i);

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

			if (!isset ($pptpdcfg['req128'])) {
				$mpdconf .=<<<EOD
	set ccp yes mpp-e40
	set ccp yes mpp-e56

EOD;
			}

			if  (isset($pptpdcfg["wins"]) && $pptpdcfg['wins'] != "")
				$mpdconf  .=  "	set ipcp nbns {$pptpdcfg['wins']}\n";

			if (!empty($pptpdcfg['dns1'])) {
				$mpdconf .= "	set ipcp dns " . $pptpdcfg['dns1'];
				if (!empty($pptpdcfg['dns2']))
					$mpdconf .= " " . $pptpdcfg['dns2'];
				$mpdconf .= "\n";
			} elseif (isset ($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
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
	set radius server {$pptpdcfg['radius']['server2']['ip']} "{$pptpdcfg['radius']['server2']['secret2']}" {$authport} {$acctport}

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
			unset($mpdconf);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.links", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.links in vpn_pptpd_configure().") . "\n");
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
			unset($mpdlinks);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/pptp-vpn/mpd.secret", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.secret in vpn_pptpd_configure().") . "\n");
				return 1;
			}

			$mpdsecret = "";

			if (is_array($pptpdcfg['user'])) {
				foreach ($pptpdcfg['user'] as $user) {
					$pass = str_replace('\\', '\\\\', $user['password']);
					$pass = str_replace('"', '\"', $pass);
					$mpdsecret .= "{$user['name']} \"{$pass}\" {$user['ip']}\n";
				}
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			unset($mpdsecret);
			chmod("{$g['varetc_path']}/pptp-vpn/mpd.secret", 0600);

			vpn_netgraph_support();

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pptp-vpn -p {$g['varrun_path']}/pptp-vpn.pid -s pptps pptps");

			break;

		case 'redir' :
			break;
	}

	if ($g['booting'])
		echo "done\n";

	return 0;
}

function vpn_pppoes_configure() {
	global $config;

	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe)
			vpn_pppoe_configure($pppoe);
	}
}

function vpn_pppoe_configure(&$pppoecfg) {
	global $config, $g;

	$syscfg = $config['system'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn"))
		mkdir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn");

	if ($g['booting']) {
		if (!$pppoecfg['mode'] || ($pppoecfg['mode'] == "off"))
			return 0;

		echo gettext("Configuring PPPoE VPN service... ");
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");

		/* wait for process to die */
		sleep(2);

	}

	switch ($pppoecfg['mode']) {

		case 'server' :

			$pppoe_interface = get_real_interface($pppoecfg['interface']);

			if ($pppoecfg['paporchap'] == "chap")
				$paporchap = "set link enable chap";
			else
				$paporchap = "set link enable pap";

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.conf in vpn_pppoe_configure().") . "\n");
				return 1;
			}
			$mpdconf = "\n\n";
			$mpdconf .= "poes:\n";

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdconf .= "	load poes{$pppoecfg['pppoeid']}{$i}\n";
			}

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {

				$clientip = long2ip32(ip2long($pppoecfg['remoteip']) + $i);

				if (isset($pppoecfg['radius']['radiusissueips']) && isset($pppoecfg['radius']['server']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 0.0.0.0/0";
				} else {
					$isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

poes{$pppoecfg['pppoeid']}{$i}:
	new -i poes{$pppoecfg['pppoeid']}{$i} poes{$pppoecfg['pppoeid']}{$i} poes{$pppoecfg['pppoeid']}{$i}
	{$isssue_ip_type}
	load pppoe_standard

EOD;
			}

			$mpdconf .=<<<EOD

pppoe_standard:
	set bundle no multilink
	set bundle enable compression
	set auth max-logins 1
	set iface up-script /usr/local/sbin/vpn-linkup
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

			if (!empty($pppoecfg['dns1'])) {
				$mpdconf .= "	set ipcp dns " . $pppoecfg['dns1'];
				if (!empty($pppoecfg['dns2']))
					$mpdconf .= " " . $pppoecfg['dns2'];
				$mpdconf .= "\n";
			} elseif (isset ($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
					$mpdconf .= "	set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
			}

			if (isset ($pppoecfg['radius']['server']['enable'])) {
				$radiusport = "";
				$radiusacctport = "";
				if (isset($pppoecfg['radius']['server']['port']))
					$radiusport = $pppoecfg['radius']['server']['port'];
				if (isset($pppoecfg['radius']['server']['acctport']))
					$radiusacctport = $pppoecfg['radius']['server']['acctport'];
				$mpdconf .=<<<EOD
	set radius server {$pppoecfg['radius']['server']['ip']} "{$pppoecfg['radius']['server']['secret']}" {$radiusport} {$radiusacctport}
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
			unset($mpdconf);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.links", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.links in vpn_pppoe_configure().") . "\n");
				return 1;
			}

			$mpdlinks = "";

			for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
				$mpdlinks .=<<<EOD

poes{$pppoecfg['pppoeid']}{$i}:
	set phys type pppoe
	set pppoe iface {$pppoe_interface}
	set pppoe service "*"
	set pppoe disable originate
	set pppoe enable incoming

EOD;
			}

			fwrite($fd, $mpdlinks);
			fclose($fd);
			unset($mpdlinks);

			if ($pppoecfg['username']) {
				/* write mpd.secret */
				$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", "w");
				if (!$fd) {
					printf(gettext("Error: cannot open mpd.secret in vpn_pppoe_configure().") . "\n");
					return 1;
				}

				$mpdsecret = "\n\n";

				if (!empty($pppoecfg['username'])) {
					$item = explode(" ", $pppoecfg['username']);
					foreach($item as $userdata) {
						$data = explode(":", $userdata);
						$mpdsecret .= "{$data[0]} \"" . base64_decode($data[1]) . "\" {$data[2]}\n";
					}
				}

				fwrite($fd, $mpdsecret);
				fclose($fd);
				unset($mpdsecret);
				chmod("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", 0600);
			}

			/* Check if previous instance is still up */
			while (file_exists("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid") && isvalidpid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid"))
				killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");

			/* Get support for netgraph(4) from the nic */
			pfSense_ngctl_attach(".", $pppoe_interface);
			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn -p {$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

			break;
	}

	if ($g['booting'])
		echo gettext("done") . "\n";

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

		echo gettext("Configuring l2tp VPN service... ");
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
				printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
				return 1;
			}
			$mpdconf = "\n\n";
			$mpdconf .=<<<EOD
l2tps:

EOD;

			for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {
				$mpdconf .= "	load l2tp{$i}\n";
			}

			for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {

				$clientip = long2ip32(ip2long($l2tpcfg['remoteip']) + $i);

				if (isset ($l2tpcfg['radius']['radiusissueips']) && isset ($l2tpcfg['radius']['enable'])) {
					$isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 0.0.0.0/0";
				} else {
					$isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 {$clientip}/32";
				}

				$mpdconf .=<<<EOD

l2tp{$i}:
	new -i l2tp{$i} l2tp{$i} l2tp{$i}
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
	set iface up-script /usr/local/sbin/vpn-linkup
	set iface down-script /usr/local/sbin/vpn-linkdown
	set link yes acfcomp protocomp
	set link no pap chap
	set link enable chap
	set link keep-alive 10 180

EOD;

			if (is_ipaddr($l2tpcfg['wins'])) {
				$mpdconf .= "	set ipcp nbns {$l2tpcfg['wins']}\n";
			}
			if (is_ipaddr($l2tpcfg['dns1'])) {
				$mpdconf .= "	set ipcp dns " . $l2tpcfg['dns1'];
				if (is_ipaddr($l2tpcfg['dns2']))
					$mpdconf .= " " . $l2tpcfg['dns2'];
				$mpdconf .= "\n";
			} elseif (isset ($config['dnsmasq']['enable'])) {
				$mpdconf .= "	set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0])
					$mpdconf .= " " . $syscfg['dnsserver'][0];
				$mpdconf .= "\n";
			} elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
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
			unset($mpdconf);

			/* write mpd.links */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.links", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.links in vpn_l2tp_configure().") . "\n");
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
			unset($mpdlinks);

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.secret", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_configure().") . "\n");
				return 1;
			}

			$mpdsecret = "\n\n";

			if (is_array($l2tpcfg['user'])) {
				foreach ($l2tpcfg['user'] as $user)
					$mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			unset($mpdsecret);
			chmod("{$g['varetc_path']}/l2tp-vpn/mpd.secret", 0600);

			vpn_netgraph_support();

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd4 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid -s l2tps l2tps");

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
	if (!is_array($a_phase1) || (!count($a_phase1)))
		return;

	foreach ($a_phase1 as $phase1) {
		if (isset($phase1['disabled']))
			continue;
		if (is_ipaddr($phase1['remote-gateway']))
			continue;
		$dnscache = compare_hostname_to_dnscache($phase1['remote-gateway']);
		$dnscache = trim($dnscache);
		/* we should have the old IP addresses in the dnscache now */
		if(!empty($dnscache)) {
			$oldphase1 = $phase1;
			$oldphase1['remote-gateway'] = $dnscache;
			/* now we need to find all tunnels for this host */
			if (!is_array($a_phase2) || (!count($a_phase2)))
				continue;
			foreach ($a_phase2 as $phase2) {
				if ($phase2['ikeid'] == $phase1['ikeid'])
					reload_tunnel_spd_policy ($phase1, $phase2, $oldphase1, $oldphase2);
			}
		}
	}

	/* process all generated temporary spd.conf files */
	$tmpfiles = glob("{$g['tmp_path']}/spd.conf.reload.*");
	foreach($tmpfiles as $tmpfile) {
		$ret = mwexec("/usr/local/sbin/setkey -f {$tmpfile} 2>&1", false);
		if ($ret == 0)
			unlink_if_exists($tmpfile);
		else {
			$tmpfile = basename($tmpfile);
			@rename("{$g['tmp_path']}/{$tmpfile}", ("{$g['tmp_path']}/failed.{$tmpfile}"));
		}
	}
}

/* remove SPD polices */
function remove_tunnel_spd_policy($phase1,$phase2) {
	global $config;
	global $g;

	if (!$phase1 || !$phase2)
		return false;

	if (isset($phase1['mobile']))
		return false;

	$spdconf = "";
	$ep = ipsec_get_phase1_src($phase1);
	$gw = trim($phase1['remote-gateway']);
	$sad_arr = ipsec_dump_sad();
	$remote_subnet = ipsec_idinfo_to_cidr($phase2['remoteid'], false, $phase2['mode']);

	if (!empty($phase2['natlocalid']))
		$local_subnet = ipsec_idinfo_to_cidr($phase2['natlocalid'], false, $phase2['mode']);
	else
		$local_subnet = ipsec_idinfo_to_cidr($phase2['localid'], false, $phase2['mode']);

	if ($phase2['mode'] == "tunnel6")
		$family = "-6";
	else
		$family = "-4";

	$spdconf .= "spddelete {$family} {$local_subnet} " .
		"{$remote_subnet} any -P out ipsec " .
		"{$phase2['protocol']}/tunnel/{$ep}-" .
		"{$gw}/unique;\n";

	$spdconf .= "spddelete {$family} {$remote_subnet} " .
		"{$local_subnet} any -P in ipsec " .
		"{$phase2['protocol']}/tunnel/{$gw}-" .
		"{$ep}/unique;\n";

	/* zap any existing SA entries */
	foreach($sad_arr as $sad) {
		if(($sad['dst'] == $ep) && ($sad['src'] == $gw))
			$spdconf .= "delete {$family} {$ep} {$gw} {$phase2['protocol']} 0x{$sad['spi']};\n";
		if(($sad['src'] == $ep) && ($sad['dst'] == $_gw))
			$spdconf .= "delete {$family} {$gw} {$ep} {$phase2['protocol']} 0x{$sad['spi']};\n";
	}

	log_error(sprintf(gettext("Removing SPDs from tunnel gw '%1\$s'. Local Subnet '%2\$s' and Remote Subnet '%3\$s'. Reloading policy"),$phase1['remote-gateway'],$local_subnet,$remote_subnet));

	$now = time();
	$spdfile = tempnam("{$g['tmp_path']}", "spd.conf.reload.{$now}.");
	/* generate temporary spd.conf */
	@file_put_contents($spdfile, $spdconf);
	unset($spdconf);

	return true;
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
	$local_subnet = ipsec_idinfo_to_cidr($phase2['localid'], false, $phase2['mode']);
	$remote_subnet = ipsec_idinfo_to_cidr($phase2['remoteid'], false, $phase2['mode']);

	/* make sure we pass the oldtunnel array with a IP for the remote gw */
	$old_gw = trim($old_phase1['remote-gateway']);

	$old_ep = ipsec_get_phase1_src($old_phase1);
	$old_local_subnet = ipsec_idinfo_to_cidr($old_phase2['localid'], false, $old_phase2['mode']);
	$old_remote_subnet = ipsec_idinfo_to_cidr($old_phase2['remoteid'], false, $old_phase2['mode']);

	/* see if this tunnel has a hostname for the remote-gateway, and if so,
	 * try to resolve it now and add it to the list for filterdns */
	$rgip = "";
	if (!is_ipaddr($phase1['remote-gateway'])) {
		if(! $g['booting']) {
			$rgip = resolve_retry($phase1['remote-gateway']);
			add_hostname_to_watch($phase1['remote-gateway']);
		} else {
			add_hostname_to_watch($phase1['remote-gateway']);
		}
		if (isset($phase1['mobile'])) {
			/* Don't log anything here, it's normal and we should skip it. */
			return false;
		} elseif (!is_ipaddr($rgip)) {
			log_error("Could not determine VPN endpoint for '{$phase1['descr']}'");
			return false;
		}
	} else {
		$rgip = $phase1['remote-gateway'];
	}
	if (!$ep) {
		log_error(sprintf(gettext("Could not determine VPN endpoint for '%s'"), $phase1['descr']));
		return false;
	}

	if((!is_ipaddr($old_ep)) || (! is_ipaddr($ep))) {
		log_error(sprintf(gettext("IPSEC: ERROR: One of the endpoints is not a IP address. Old EP '%1\$s' new EP '%2\$s'"), $old_ep, $ep));
	}
	if((! is_ipaddr($rgip)) || (! is_ipaddr($old_gw))) {
		log_error(sprintf(gettext("IPSEC: ERROR: One of the remote endpoints is not a IP address. Old RG '%1\$s' new RG '%2\$s'"), $old_gw, $rgip));
	}

	$spdconf = "";
	/* Delete old SPD policies if there are changes between the old and new */
	if(($phase1 != $old_phase1) || ($phase2 != $old_phase2)) {
		if($old_phase2['mode'] == "tunnel6")
			$family = "-6";
		else
			$family = "-4";

		$spdconf .= "spddelete {$family} {$old_local_subnet} " .
			"{$old_remote_subnet} any -P out ipsec " .
			"{$old_phase2['protocol']}/tunnel/{$old_ep}-" .
			"{$old_gw}/unique;\n";
		if (!empty($old_phase2['natlocalid']))
			$old_local_subnet = ipsec_idinfo_to_cidr($old_phase2['natlocalid'], false, $old_phase2['mode']);
		$spdconf .= "spddelete {$family} {$old_remote_subnet} " .
			"{$old_local_subnet} any -P in ipsec " .
			"{$old_phase2['protocol']}/tunnel/{$old_gw}-" .
			"{$old_ep}/unique;\n";

		/* zap any existing SA entries */
		foreach($sad_arr as $sad) {
			if(($sad['dst'] == $old_ep) && ($sad['src'] == $old_gw)) {
				$spdconf .= "delete {$family} {$old_ep} {$old_gw} {$old_phase2['protocol']} 0x{$sad['spi']};\n";
			}
			if(($sad['src'] == $oldep) && ($sad['dst'] == $old_gw)) {
				$spdconf .= "delete {$family} {$old_gw} {$old_ep} {$old_phase2['protocol']} 0x{$sad['spi']};\n";
			}
		}
	}

	if($phase2['mode'] == "tunnel6")
		$family = "-6";
	else
		$family = "-4";

	/* Create new SPD entries for the new configuration */
	/* zap any existing SA entries beforehand */
	foreach($sad_arr as $sad) {
		if(($sad['dst'] == $ep) && ($sad['src'] == $rgip)) {
			$spdconf .= "delete {$family} {$rgip} {$ep} {$phase2['protocol']} 0x{$sad['spi']};\n";
		}
		if(($sad['src'] == $ep) && ($sad['dst'] == $rgip)) {
			$spdconf .= "delete {$family} {$ep} {$rgip} {$phase2['protocol']} 0x{$sad['spi']};\n";
		}
	}
	/* add new SPD policies to replace them */
	if (!isset($phase1['disabled']) && !isset($phase2['disabled'])) {
		$spdconf .= "spdadd {$family} {$local_subnet} " .
			"{$remote_subnet} any -P out ipsec " .
			"{$phase2['protocol']}/tunnel/{$ep}-" .
			"{$rgip}/unique;\n";

		if (!empty($phase2['natlocalid']))
			$local_subnet = ipsec_idinfo_to_cidr($phase2['natlocalid'], false, $phase2['mode']);
		$spdconf .= "spdadd {$family} {$remote_subnet} " .
			"{$local_subnet} any -P in ipsec " .
			"{$phase2['protocol']}/tunnel/{$rgip}-" .
			"{$ep}/unique;\n";
	}

	log_error(sprintf(gettext("Reloading IPsec tunnel '%1\$s'. Previous IP '%2\$s', current IP '%3\$s'. Reloading policy"), $phase1['descr'], $old_gw, $rgip));

	$now = time();
	$spdfile = tempnam("{$g['tmp_path']}", "spd.conf.reload.{$now}.");
	/* generate temporary spd.conf */
	@file_put_contents($spdfile, $spdconf);
	unset($spdconf);
	/* remove static route to old gw */
	if (is_ipaddr($old_gw))
		mwexec("/sbin/route delete {$old_gw}", true);
	return true;
}

function vpn_ipsec_configure_preferoldsa() {
	global $config;
	if(isset($config['ipsec']['preferoldsa']))
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");
	else
		mwexec("/sbin/sysctl net.key.preferred_oldsa=0");
}

?>
