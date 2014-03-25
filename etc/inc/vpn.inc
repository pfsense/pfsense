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
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/ipsec	/usr/local/libexec/ipsec/charon /usr/local/libexec/ipsec/starter
	pfSense_BUILDER_BINARIES:	/usr/local/sbin/filterdns	/usr/local/sbin/mpd4
	pfSense_MODULE:	vpn
*/

require_once("ipsec.inc");

function vpn_ipsec_configure_loglevels()
{
	global $config, $ipsec_loglevels;

	foreach ($ipsec_loglevels as $lkey => $ldescr) {
		if (empty($config['ipsec']["ipsec_{$lkey}"]))
			mwexec("/usr/local/sbin/ipsec stroke loglevel {$lkey} -1", false);
		else if (is_numeric($config['ipsec']["ipsec_{$lkey}"]) &&
		    intval($config['ipsec']["ipsec_{$lkey}"]) >= 1 && intval($config['ipsec']["ipsec_{$lkey}"]) <= 4)
			mwexec("/usr/local/sbin/ipsec stroke loglevel {$lkey} " . (intval($config['ipsec']["ipsec_{$lkey}"]) - 1) , false);
	}
}

/* include all configuration functions */
function vpn_ipsec_convert_to_modp($index)
{

	$convertion = "";
	switch ($index) {
	case '1':
		$convertion = "modp768";
		break;
	case '2':
		$convertion = "modp1024";
		break;
	case '5':
		$convertion = "modp1536";
		break;
	case '14':
		$convertion = "modp2048";
		break;
	case '15':
		$convertion = "modp3072";
		break;
	case '16':      
		$convertion = "modp4096";
		break;
	case '17':
		$convertion = "modp6144";
		break;
	case '18':
		$convertion = "modp8192";
		break;
	}

	return $convertion;
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
		/* try to stop charon */
		mwexec("/usr/local/sbin/ipsec stop");
		/* Stop dynamic monitoring */
		killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");

		/* wait for process to die */
		sleep(2);

		/* flush SPD and SAD 
		mwexec("/usr/local/sbin/setkey -F");
		mwexec("/usr/local/sbin/setkey -FP");
		*/

		/* disallow IPSEC, it is off */
		mwexec("/sbin/ifconfig enc0 down");
		exec("/sbin/sysctl net.inet.ip.ipsec_in_use=0");

		return 0;
	} else {
		mwexec("/sbin/ifconfig enc0 up");
		mwexec("/sbin/sysctl net.inet.ip.ipsec_in_use=1");
		/* needed for config files */
		if (!is_dir("{$g['varetc_path']}/ipsec"))
			mkdir("{$g['varetc_path']}/ipsec");
		if (!is_dir("{$g['varetc_path']}/ipsec/cacerts"))
			mkdir("{$g['varetc_path']}/ipsec/cacerts");
		if (!is_dir("{$g['varetc_path']}/ipsec/private"))
			mkdir("{$g['varetc_path']}/ipsec/private");
		if (!is_dir("{$g['varetc_path']}/ipsec/crls"))
			mkdir("{$g['varetc_path']}/ipsec/crls");
		if (!is_dir("{$g['varetc_path']}/ipsec/certs"))
			mkdir("{$g['varetc_path']}/ipsec/certs");
		if (!is_dir("{$g['varetc_path']}/ipsec/aacerts"))
			mkdir("{$g['varetc_path']}/ipsec/aacerts");
		if (!is_dir("{$g['varetc_path']}/ipsec/acerts"))
			mkdir("{$g['varetc_path']}/ipsec/acerts");
		if (!is_dir("{$g['varetc_path']}/ipsec/reqs"))
			mkdir("{$g['varetc_path']}/ipsec/reqs");
		

		if ($g['booting'])
			echo gettext("Configuring IPsec VPN... ");

		/* fastforwarding is not compatible with ipsec tunnels */
		mwexec("/sbin/sysctl net.inet.ip.fastforwarding=0");

		/* resolve all local, peer addresses and setup pings */
		$ipmap = array();
		$rgmap = array();
		$filterdns_list = array();
		$listeniflist = array();
		unset($iflist);
		if (is_array($a_phase1) && count($a_phase1)) {

			$ipsecpinghosts = "";
			/* step through each phase1 entry */
			foreach ($a_phase1 as $ph1ent) {
				if (isset($ph1ent['disabled']))
					continue;

				$listeniflist = get_real_interface($a_phase1['interface']);

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
							foreach ($iflist as $ifent => $ifname) {
								if(is_ipaddrv6($ph2ent['pinghost'])) {
									$interface_ip = get_interface_ipv6($ifent);
									if(!is_ipaddrv6($interface_ip))
										continue;
									$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
									if (ip_in_subnet($interface_ip, $local_subnet)) {
										$srcip = $interface_ip;
										break;
									}
								} else {
									$interface_ip = get_interface_ip($ifent);
									if(!is_ipaddrv4($interface_ip))
										continue;
									$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
									if ($local_subnet == "0.0.0.0/0" || ip_in_subnet($interface_ip, $local_subnet)) {
										$srcip = $interface_ip;
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
		unset($iflist);

		$strongswan = <<<EOD

#Automatically generated please do not modify
starter {
    load_warning = no
}

charon {

        # number of worker threads in charon
        threads = 16

	# And two loggers using syslog. The subsections define the facility to log
	# to, currently one of: daemon, auth.
	syslog {

		identifier = charon
		# default level to the LOG_DAEMON facility
		daemon {
		}
		# very minimalistic IKE auditing logs to LOG_AUTHPRIV
		auth {
		    default = -1
		    ike = 1
		    ike_name = yes
		}
	}

EOD;

		if (is_array($a_client) && isset($a_client['enable']) && !empty($a_client['net_list']))
			$strongswan .= "\tcisco_unity = yes\n";

		$strongswan .= "\tplugins {\n";

		if (is_array($a_client) && isset($a_client['enable'])) {
			$strongswan .= "\t\tattr {\n";
			if ($a_client['pool_address'] && $a_client['pool_netbits']) {
				$pool_address = $a_client['pool_address'];
				$pool_netmask = gen_subnet_mask($a_client['pool_netbits']);
				$pool_address = long2ip32(ip2long($pool_address)+1);

				$strongswan .= "\t\taddress = {$pool_address}\n";
				$strongswan .= "\t\tnetmask = {$pool_netmask}\n";
			}

			$cfgservers = array();
			if (!empty($a_client['dns_server1']))
				$cfgservers[] = $a_client['dns_server1'];
			if (!empty($a_client['dns_server2']))
				$cfgservers[] = $a_client['dns_server2'];
			if (!empty($a_client['dns_server3']))
				$cfgservers[] = $a_client['dns_server3'];
			if (!empty($a_client['dns_server4']))
				$cfgservers[] = $a_client['dns_server4'];

			if (!empty($cfgservers))
				$strongswan .= "\t\tdns = " . implode(",", $cfgservers) . "\n";
			unset($cfgservers);
			$cfgservers = array();
			if (!empty($a_client['wins_server1']))
				$cfgservers[] = $a_client['wins_server1'];
			if (!empty($a_client['wins_server2']))
				$cfgservers[] = $a_client['wins_server2'];
			if (!empty($cfgservers))
				$strongswan .= "\t\tnbns = " . implode(",", $cfgservers) . "\n";
			unset($cfgservers);

			if (!empty($a_client['net_list'])) {
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

				if (!empty($net_list)) {
					$strongswan .= "\t\tsubnet = {$net_list}\n";
					$strongswan .= "\t\tsplit-include = {$net_list}\n";
					unset($net_list);
				}
			}

			if (!empty($a_client['dns_domain'])) {
				$strongswan .= "\t\t# Search domain and default domain\n";
				$strongswan .= "\t\t28674 = {$a_client['dns_domain']}\n";
				if (empty($a_client['dns_split']))
					$strongswan .= "\t\t28675 = {$a_client['dns_domain']}";
				$strongswan .= "\n";
			}

			if (!empty($a_client['dns_split'])) {
				$strongswan .= "\t\t28675 = {$a_client['dns_split']}\n";
			}

			if (!empty($a_client['login_banner']))
				$strongswan .= "\t\t28672 = {$a_client['login_banner']}\n";

			if (isset($a_client['save_passwd']))
				$strongswan .= "\t\t28673 = yes\n";

			if ($a_client['pfs_group'])
				$strongswan .= "\t\t28679 = {$a_client['pfs_group']}\n";
			$strongswan .= "\t\t}\n";

			if ($a_client['user_source'] != "none") {
				$strongswan .= "\txauth-generic {\n";
				$strongswan .= "\t\tscript = /etc/inc/ipsec.auth-user.php\n";
				$strongswan .= "\t\tauthcfg = ";
				$firstsed = 0;
				$authcfgs = explode(",", $a_client['user_source']);
				foreach ($authcfgs as $authcfg) {
					if ($firstsed > 0)
						$strongswan .= ",";
					if ($authcfg == "system")
						$authcfg = "Local Database";
					$strongswan .= $authcfg;
					$firstsed = 1;
				}
				$strongswan .= "\n";
				$strongswan .= "\t}\n";
			}
		}

		$strongswan .= "\t}\n}\n";
		@file_put_contents("{$g['varetc_path']}/ipsec/strongswan.conf", $strongswan);
		unset($strongswan);

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
				$fname = "{$g['varetc_path']}/ipsec/cacerts/{$x509cert['hash']}.0";
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

				if (strstr($ph1ent['authentication_method'],'rsa')) {
					$certline = '';

					if (strstr($authmethod,'rsa')) {

						$cert = lookup_cert($ph1ent['certref']);

						if (!$cert) {
							log_error(sprintf(gettext("Error: Invalid phase1 certificate reference for %s"), $ph1ent['name']));
							continue;
						}

						chmod($certpath, 0600);

						$keyfile = "cert-{$ikeid}.key";
						$keypath = "{$g['varetc_path']}/ipsec/{$keyfile}";

						if (!file_put_contents($keypath, base64_decode($cert['prv']))) {
							log_error(sprintf(gettext("Error: Cannot write phase1 key file for %s"), $ph1ent['name']));
							continue;
						}

						chmod($keypath, 0600);
						/* XXX" Traffic selectors? */
						$pskconf .= " : RSA {$keypath}\n";

						$ca = lookup_ca($ph1ent['caref']);
						if ($ca) {
							$cafile = "ca-{$ikeid}.crt";
							$capath = "{$g['varetc_path']}/ipsec/cacerts/{$cafile}";

							if (!file_put_contents($capath, base64_decode($ca['crt'])))
							{
								log_error(sprintf(gettext("Error: Cannot write phase1 CA certificate file for %s"), $ph1ent['name']));
								continue;
							}

							chmod($capath, 0600);
						}
					}
				} else {

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
						$pskconf .= trim($peerid_data) . " : PSK \"" . trim($ph1ent['pre-shared-key']) . "\"\n";
				}
			}
		}

		/* Add user PSKs */
		foreach ($config['system']['user'] as $user) {
			if (!empty($user['ipsecpsk'])) {
				$pskconf .= "{$user['name']} : PSK \"{$user['ipsecpsk']}\"\n";
			}
		}

		/* add PSKs for mobile clients */
		if (is_array($ipseccfg['mobilekey'])) {
			foreach ($ipseccfg['mobilekey'] as $key) {
				$pskconf .= "{$key['ident']} : PSK \"{$key['pre-shared-key']}\"\n";
			}
		}

		@file_put_contents("{$g['varetc_path']}/ipsec/ipsec.secrets", $pskconf);
		chmod("{$g['varetc_path']}/ipsec/ipsec.secrets", 0600);
		unset($pskconf);

		$natfilterrules = false;
		/* begin ipsec.conf */
		$ipsecconf = "";
		if ((is_array($a_phase1) && count($a_phase1)) || (is_array($a_phase2) && count($a_phase2))) {

			$ipsecconf .= "# This file is automatically generated. Do not edit\n";
			if (is_array($a_phase2) && count($a_phase2)) {
				$ipsecconf .= "config setup\n\tuniqueids = yes\n";

				foreach ($a_phase2 as $ph2ent) {
					$ikeid = $ph2ent['ikeid'];

					$ph1ent = false;
					if (!ipsec_lookup_phase1($ph2ent,$ph1ent))
						continue;

					if (isset($ph1ent['disabled']))
						continue;

					if (isset($ph2ent['disabled']))
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
							$myid_data = "\"{$myid_data}\"";
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
							$peerid_data = "\"{$peerid_data}\"";
						break;
					}

					$genp = "no";
					if (!empty($ph1ent['generate_policy']) && $ph1ent['generate_policy'] != "off")
						$genp = "yes";
						
					$passive = "start";
					if (isset($ph1ent['mobile'])) {
						$rgip = "%any";
						$passive = "route";
					}

					$keyexchange = "ikev1";
					if (!empty($ph1ent['iketype']) && $ph1ent['iketype'] != "ikev1")
						$keyexchange = "ikev2";

					if (is_array($ph1ent['encryption-algorithm']) && !empty($ph1ent['encryption-algorithm']['name']) && !empty($ph1ent['hash-algorithm'])) {
						$ealgosp1 = '';
						$ealg_id = $ph1ent['encryption-algorithm']['name'];
						$ealg_kl = $ph1ent['encryption-algorithm']['keylen'];
						if ($ealg_kl)
							$ealgosp1 = "ike = {$ealg_id}{$ealg_kl}-{$ph1ent['hash-algorithm']}";
						else
							$ealgosp1 = "ike = {$ealg_id}-{$ph1ent['hash-algorithm']}";

						$modp = vpn_ipsec_convert_to_modp($ph1ent['dhgroup']);
						if (!empty($modp))
							$ealgosp1 .= "-{$modp}";

						if ($keyexchange == "ikev1")
							$ealgosp1 .= "!";
					}

					if ($ph1ent['dpd_delay'] && $ph1ent['dpd_maxfail']) {
						if ($passive == "start")
							$dpdline = "dpdaction = restart";
						else
							$dpdline = "dpdaction = clear";
						$dpdline .= "\n\tdpddelay = {$ph1ent['dpd_delay']}s";
						$dpdline .= "\n\tdpdtimeout = {$ph1ent['dpd_maxfail']}s";
					} else
						$dpdline = "dpdaction = none";

					if (!empty($ph1ent['authentication_method']) && (strstr($ph1ent['authentication_method'], "xauth") || strstr($ph1ent['authentication_method'], "hybrid")))
						$xauth = "xauth = server";


					$lifeline = '';
					if ($ph1ent['lifetime'])
						$lifeline = "ikelifetime = {$ph1ent['lifetime']}s";

					/* Only specify peer ID if we are not dealing with a mobile PSK-only tunnel */
					$peerid_spec = '';
					if (!(($ph1ent['authentication_method'] == "pre_shared_key") && isset($ph1ent['mobile']))) {
						$peerid_spec = $peerid_data;
					}

					if (empty($ph1ent['mode']))
						$aggressive = "no";
					else if ($ph1ent['mode'] == "aggressive")
						$aggressive = "yes";
					else if ($ph1ent['mode'] == "main")
						$aggressive = "no";
					else
						$aggressive = "no";

					if (isset($ph2ent['mobile']) && !isset($a_client['enable']))
						continue;

					if (($ph2ent['mode'] == 'tunnel') or ($ph2ent['mode'] == 'tunnel6')) {
						$tunneltype = "type = tunnel";

						$localid_type = $ph2ent['localid']['type'];
						$localid_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
						/* Do not print localid in some cases, such as a pure-psk or psk/xauth single phase2 mobile tunnel */
						if (($localid_type == "none" || $localid_type == "mobile")
							&& isset($ph1ent['mobile'])
							&& (ipsec_get_number_of_phase2($ikeid)==1))
							$localid_spec = "%any";
						else {
							if ($localid_type != "address") {
								$localid_type = "subnet";
							}
							// Don't let an empty subnet into config, it can cause parse errors. Ticket #2201.
							if (!is_ipaddr($localid_data) && !is_subnet($localid_data) && ($localid_data != "0.0.0.0/0")) {
								log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
								continue;
							}
							$localid_spec = $ep;
							if (!empty($ph2ent['natlocalid'])) {
								$natlocalid_data =  ipsec_idinfo_to_cidr($ph2ent['natlocalid'], false, $ph2ent['mode']);
								if ($ph2ent['natlocalid']['type'] != "address") {
									if (is_subnet($natlocalid_data))
										$localid_data = "{$natlocalid_data}|{$localid_data}";
								} else {
									if (is_ipaddr($natlocalid_data))
										$localid_data = "{$natlocalid_data}|{$localid_data}";
								}
								$natfilterrules = true;
							}
						}

						if (!isset($ph2ent['mobile'])) {
							$remoteid_type = $ph2ent['remoteid']['type'];
							if ($remoteid_type != "address")
								$remoteid_type = "subnet";

							$remoteid_data = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
							$remoteid_spec = $remoteid_data;
						}

					} else {
						$tunneltype = "type = transport";
						$rgip = $rgmap[$ph1ent['remote-gateway']];

						if ((($ph1ent['authentication_method'] == "xauth_psk_server") ||
							($ph1ent['authentication_method'] == "pre_shared_key"))
							&& isset($ph1ent['mobile']))
							$localid_spec = "%any";
						else {
							$localid_data = ipsec_get_phase1_src($ph1ent);
							$localid_spec = $ep;
						}
						if (!isset($ph2ent['mobile'])) {
							$remoteid_data = $rgmap[$ph1ent['remote-gateway']];
							$remoteid_spec = $remoteid_data;
						}
					}
					$authentication = "";
					switch ($ph1ent['authentication_method']) {
					case 'xauth_rsa_server':
						$authentication = "leftauth = pubkey\n\trightauth = pubkey";
						$authentication .= "\n\leftauth2 = xauth-generic";
						break;
					case 'xauth_psk_server':
						$authentication = "leftauth = psk\n\trightauth = psk";
						$authentication .= "\n\tleftauth2 = xauth-generic";
						break;
					case 'pre_shared_key':
						$authentication = "leftauth = psk\n\trightauth = psk";
						break;
					case 'rsasig':
						$authentication = "leftauth = pubkey\n\trightauth = pubkey";
						break;
					case 'hybrid_rsa_server':
						$authentication = "leftauth = xauth-generic\n\trightauth = pubkey";
						$authentication .= "\n\trightauth2 = xauth";
						break;
					}

					if (isset($a_client['pfs_group']))
						$ph2ent['pfsgroup'] = $a_client['pfs_group'];
					$ealgosp2 = '';
					if ($ph2ent['protocol'] == 'esp') {
						if (is_array($ph2ent['encryption-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
							$ealgosp2arr = array();
							foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {
								$ealg_id = $ealg['name'];
								$ealg_kl = $ealg['keylen'];

								if (!empty($ealg_kl) && $ealg_kl == "auto") {
									if (empty($p2_ealgos) || !is_array($p2_ealgos))
										require("ipsec.inc");
									$key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
									$key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
									$key_step = $p2_ealgos[$ealg_id]['keysel']['step'];
									/* XXX: in some cases where include ordering is suspect these variables
									 * are somehow 0 and we enter this loop forever and timeout after 900
									 * seconds wrecking bootup */
									if ($key_hi != 0 and $key_lo !=0 and $key_step !=0) {
										for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
											foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
												$halgo = str_replace('hmac_', '', $halgo);
												$tmpealgo = "{$ealg_id}{$keylen}-{$halgo}";
												$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
												if (!empty($modp))
													$tmpealgo .= "-{$modp}";
												$ealgosp2arr[] = $tmpealgo;
											}
										}
									}
								} else {
									foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
										$halgo = str_replace('hmac_', '', $halgo);
										$tmpealgo = "{$ealg_id}{$ealg_kl}-{$halgo}";
										$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
										if (!empty($modp))
											$tmpealgo .= "-{$modp}";
										$ealgosp2arr[] = $tmpealgo;
									}
								}
							}
							$ealgosp2 = "esp = " . join(",", $ealgosp2arr);
							unset($ealgosp2arr);
							$ealgosp2 .= "!";
						}
					} else if ($ph2ent['protocol'] == 'ah') {
						if (is_array($ph2ent['hash-algorithm-option'])) {
							$ealgosp2 = "ah = " . join(",", $ph2ent['hash-algorithm-option']);
							$ealgosp2 = str_replace('hmac_', '', $ealgosp2);
							$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
							if (!empty($modp))
								$ealgosp2 .= "-{$modp}";
							$ealgosp2 .= "!";
						}
					}


					if ($ph2ent['lifetime'])
						$lifeline = "ikelifetime = {$ph2ent['lifetime']}s";

					$ipsecconf .=<<<EOD

conn con{$ph2ent['ikeid']}-{$ph2ent['ikeid']}
	aggressive = {$aggressive}
	fragmentation = yes
	keyexchange = {$keyexchange}
	keyingtries = %forever
	reauth = yes
	reqid = {$ikeid}
	installpolicy = yes
	{$lifeline}
	{$tunneltype}
	{$dpdline}
	auto = {$passive}
	left = {$localid_spec}
	leftsubnet = {$localid_data}
	right = {$rgip}
	leftid = {$myid_data}

EOD;

					if (!empty($remoteid_spec))
						$ipsecconf .= "\trightsubnet = $remoteid_spec\n";
					if (!empty($ealgosp1))
						$ipsecconf .= "\t{$ealgosp1}\n";
					if (!empty($ealgosp2))
						$ipsecconf .= "\t{$ealgosp2}\n";
					if (!empty($authentication))
						$ipsecconf .= "\t{$authentication}\n";
					if (!empty($peerid_spec))
						$ipsecconf .= "\trightid = {$peerid_spec}\n";
				}
			}
		}
	}
	@file_put_contents("{$g['varetc_path']}/ipsec/ipsec.conf", $ipsecconf);
	unset($ipsecconf);
	/* end ipsec.conf */

	/* mange process */
	if (isvalidpid("{$g['varrun_path']}/charon.pid")) {
		/* Read secrets */
		mwexec("/usr/local/sbin/ipsec rereadall", false);
		/* Update configuration changes */
		mwexec("/usr/local/sbin/ipsec update", false);
	} else {
		mwexec("/usr/local/sbin/ipsec start", false); 
	}
	vpn_ipsec_configure_loglevels();
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

	if ($g['booting'])
		echo "done\n";

	return count($filterdns_list);
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
			log_error(sprintf(gettext("Ignoring IPsec reload since there are no tunnels on interface %s"), $interface));
			return;
		}
	}

	/* if ipsec is enabled, start up again */
	if (isset($ipseccfg['enable'])) {
		log_error(gettext("Forcefully reloading IPsec"));
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

function vpn_ipsec_configure_preferoldsa() {
	global $config;
	if(isset($config['ipsec']['preferoldsa']))
		mwexec("/sbin/sysctl -w net.key.preferred_oldsa=-30");
	else
		mwexec("/sbin/sysctl net.key.preferred_oldsa=0");
}

?>
