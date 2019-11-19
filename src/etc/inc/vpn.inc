<?php
/*
 * vpn.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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

require_once("ipsec.inc");
require_once("filter.inc");
require_once("auth.inc");
require_once("certs.inc");

function vpn_update_daemon_loglevel($category, $level) {
	global $ipsec_log_cats, $ipsec_log_sevs;

	if (in_array($category, array_keys($ipsec_log_cats), true) && in_array(intval($level), array_keys($ipsec_log_sevs), true)) {

		/* if you're setting to -1, need to add "--" to args */
		$argterm = "";
		if ($level == "-1") {
			$argterm = "--";
		}

		mwexec("/usr/local/sbin/ipsec stroke loglevel {$category} {$argterm} {$level}");
	}
}

function vpn_logging_cfgtxt() {
	global $config, $ipsec_log_cats, $ipsec_log_sevs;

	$cfgtext = array();
	$log_levels = ipsec_get_loglevels();
	foreach (array_keys($ipsec_log_cats) as $cat) {
		if (is_numeric($log_levels[$cat]) &&
		    in_array(intval($log_levels[$cat]), array_keys($ipsec_log_sevs), true)) {
			$cfgtext[] = "${cat} = {$log_levels[$cat]}";
		}
	}

	return $cfgtext;
}

/* include all configuration functions */
function vpn_ipsec_convert_to_modp($index) {

	$conversion = "";
	switch ($index) {
		case '1':
			$conversion = "modp768";
			break;
		case '2':
			$conversion = "modp1024";
			break;
		case '5':
			$conversion = "modp1536";
			break;
		case '14':
			$conversion = "modp2048";
			break;
		case '15':
			$conversion = "modp3072";
			break;
		case '16':
			$conversion = "modp4096";
			break;
		case '17':
			$conversion = "modp6144";
			break;
		case '18':
			$conversion = "modp8192";
			break;
		case '19':
			$conversion = "ecp256";
			break;
		case '20':
			$conversion = "ecp384";
			break;
		case '21':
			$conversion = "ecp521";
			break;
		case '22':
			$conversion = "modp1024s160";
			break;
		case '23':
			$conversion = "modp2048s224";
			break;
		case '24':
			$conversion = "modp2048s256";
			break;
		case '25':
			$conversion = "ecp192";
			break;
		case '26':
			$conversion = "ecp224";
			break;
		case '27':
			$conversion = "ecp224bp";
			break;
		case '28':
			$conversion = "ecp256bp";
			break;
		case '29':
			$conversion = "ecp384bp";
			break;
		case '30':
			$conversion = "ecp512bp";
			break;
		case '31':
			$conversion = "curve25519";
			break;
		case '32':
			$conversion = "curve448";
			break;
	}

	return $conversion;
}

function vpn_ipsec_configure($restart = false) {
	global $config, $g, $sa, $sn, $p1_ealgos, $p2_ealgos, $ipsec_idhandling;

	$ipsecstartlock = lock('ipsec', LOCK_EX);

	/* get the automatic ping_hosts.sh ready */
	unlink_if_exists("{$g['vardb_path']}/ipsecpinghosts");
	touch("{$g['vardb_path']}/ipsecpinghosts");
	$ipsecpinghostsactive = false;

	/* service may have been enabled, disabled, or otherwise changed in a way requiring rule updates */
	filter_configure();

	$syscfg = $config['system'];
	$ipseccfg = $config['ipsec'];

	/* Configure asynchronous crypto. See https://redmine.pfsense.org/issues/8772 */
	set_sysctl(array('net.inet.ipsec.async_crypto' => (int) (isset($ipseccfg['async_crypto']) && ($ipseccfg['async_crypto'] == "enabled"))));

	if (!ipsec_enabled()) {
		/* try to stop charon */
		mwexec("/usr/local/sbin/ipsec stop");
		/* Stop dynamic monitoring */
		killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");

		/* wait for process to die */
		sleep(2);

		/* IPSEC is off, shutdown enc interface.*/
		mwexec("/sbin/ifconfig enc0 down");

		unlock($ipsecstartlock);
		return 0;
	}

	$a_phase1 = $config['ipsec']['phase1'];
	$a_phase2 = $config['ipsec']['phase2'];
	$a_client = $config['ipsec']['client'];

	$certpath = "{$g['varetc_path']}/ipsec/ipsec.d/certs";
	$capath = "{$g['varetc_path']}/ipsec/ipsec.d/cacerts";
	$keypath = "{$g['varetc_path']}/ipsec/ipsec.d/private";
	$crlpath = "{$g['varetc_path']}/ipsec/ipsec.d/crls";

	mwexec("/sbin/ifconfig enc0 up");
	$ipsec_vti_cleanup_ifs = array();

	/* needed for config files */
	safe_mkdir("{$g['varetc_path']}/ipsec");
	safe_mkdir("{$g['varetc_path']}/ipsec/ipsec.d");

	// delete these paths first to ensure old CAs, certs and CRLs aren't left behind. redmine #5238
	rmdir_recursive($capath);
	rmdir_recursive($keypath);
	rmdir_recursive($crlpath);
	rmdir_recursive($certpath);

	safe_mkdir($capath);
	safe_mkdir($keypath);
	safe_mkdir($crlpath);
	safe_mkdir($certpath);
	safe_mkdir("{$g['varetc_path']}/ipsec/ipsec.d/aacerts");
	safe_mkdir("{$g['varetc_path']}/ipsec/ipsec.d/acerts");
	safe_mkdir("{$g['varetc_path']}/ipsec/ipsec.d/ocspcerts");
	safe_mkdir("{$g['varetc_path']}/ipsec/ipsec.d/reqs");

	if (!file_exists("/usr/local/etc/ipsec.d") ||
	    !is_link("/usr/local/etc/ipsec.d")) {
		if (file_exists("/usr/local/etc/ipsec.d")) {
			rmdir_recursive("/usr/local/etc/ipsec.d");
		}
		@symlink("{$g['varetc_path']}/ipsec/ipsec.d",
		    "/usr/local/etc/ipsec.d");
	}
	if (!file_exists("{$g['varetc_path']}/etc/strongswan.d") ||
	    !is_link("{$g['varetc_path']}/etc/strongswan.d")) {
		if (is_link("{$g['varetc_path']}/etc/strongswan.d")) {
			@unlink("{$g['varetc_path']}/etc/strongswan.d");
		} else {
			rmdir_recursive("{$g['varetc_path']}/etc/strongswan.d");
		}
		@symlink("/usr/local/etc/strongswan.d",
		    "{$g['varetc_path']}/ipsec/strongswan.d");
	}
	if (!file_exists("/usr/local/etc/strongswan.conf") ||
	    !is_link("/usr/local/etc/strongswan.conf")) {
		@unlink("/usr/local/etc/strongswan.conf");
		@symlink("{$g['varetc_path']}/ipsec/strongswan.conf",
		    "/usr/local/etc/strongswan.conf");
	}
	if (!file_exists("/usr/local/etc/ipsec.conf") ||
	    !is_link("/usr/local/etc/ipsec.conf")) {
		@unlink("/usr/local/etc/ipsec.conf");
		@symlink("{$g['varetc_path']}/ipsec/ipsec.conf",
		    "/usr/local/etc/ipsec.conf");
	}

	if (platform_booting()) {
		echo gettext("Configuring IPsec VPN... ");
	}

	/* resolve all local, peer addresses and setup pings */
	$ipmap = array();
	$rgmap = array();
	$filterdns_list = array();
	$aggressive_mode_psk = false;
	unset($iflist);
	$ifacesuse = array();
	$mobile_ipsec_auth = "";
	if (is_array($a_phase1) && count($a_phase1)) {

		$ipsecpinghosts = array();
		/* step through each phase1 entry */
		foreach ($a_phase1 as $ph1ent) {
			if (isset($ph1ent['disabled'])) {
				continue;
			}

			if (substr($ph1ent['interface'], 0, 4) == "_vip") {
				$vpninterface = get_configured_vip_interface($ph1ent['interface']);
				$ifacesuse[] = get_real_interface($vpninterface);
			} else {
				$vpninterface = get_failover_interface($ph1ent['interface']);
				if (substr($vpninterface, 0, 4) == "_vip") {
					$vpninterface = get_configured_vip_interface($vpninterface);
					$ifacesuse[] = get_real_interface($vpninterface);
				} elseif (!empty($vpninterface)) {
					$ifacesuse[] = $vpninterface;
				}
			}

			if ($ph1ent['mode'] == "aggressive" && ($ph1ent['authentication_method'] == "pre_shared_key" || $ph1ent['authentication_method'] == "xauth_psk_server")) {
				$aggressive_mode_psk = true;
			}

			$ikeid = $ph1ent['ikeid'];

			$ep = ipsec_get_phase1_src($ph1ent);
			/* When automatically guessing, use the first address. */
			$ep  = explode(',', $ep);
			$ep  = $ep[0];
			if (!is_ipaddr($ep)) {
				log_error(sprintf(gettext("IPsec ERROR: Could not find phase 1 source for connection %s. Omitting from configuration file."), $ph1ent['descr']));
				continue;
			}

			if (!in_array($ep, $ipmap)) {
				$ipmap[] = $ep;
			}

			/* see if this tunnel has a hostname for the remote-gateway. If so,
			   try to resolve it now and add it to the list for filterdns */

			if (isset ($ph1ent['mobile'])) {
				$mobile_ipsec_auth = $ph1ent['authentication_method'];
				continue;
			}

			$rg = $ph1ent['remote-gateway'];

			if (!is_ipaddr($rg)) {
				$filterdns_list[] = "{$rg}";
				add_hostname_to_watch($rg);
				if (!platform_booting()) {
					$rg = resolve_retry($rg);
				}
				if (!is_ipaddr($rg)) {
					continue;
				}
			}
			if (array_search($rg, $rgmap)) {
				log_error(sprintf(gettext("The remote gateway %s already exists on another phase 1 entry"), $rg));
				continue;
			}
			$rgmap[$ph1ent['remote-gateway']] = $rg;

			$is_vti = false;
			if (is_array($a_phase2)) {
				/* step through each phase2 entry */
				foreach ($a_phase2 as $ph2ent) {
					if ($ph2ent['mode'] == 'vti') {
						$is_vti = true;
					}
					if (isset($ph2ent['disabled'])) {
						continue;
					}

					if ($ikeid != $ph2ent['ikeid']) {
						continue;
					}

					/* add an ipsec pinghosts entry */
					if ($ph2ent['pinghost']) {
						if (!is_array($iflist)) {
							$iflist = get_configured_interface_list();
						}
						$srcip = null;
						$local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
						if (is_ipaddrv6($ph2ent['pinghost'])) {
							foreach ($iflist as $ifent => $ifname) {
								$interface_ip = get_interface_ipv6($ifent);
								if (!is_ipaddrv6($interface_ip)) {
									continue;
								}
								if (ip_in_subnet($interface_ip, $local_subnet)) {
									$srcip = $interface_ip;
									break;
								}
							}
						} else {
							foreach ($iflist as $ifent => $ifname) {
								$interface_ip = get_interface_ip($ifent);
								if (!is_ipaddrv4($interface_ip)) {
									continue;
								}
								if ($local_subnet == "0.0.0.0/0" || ip_in_subnet($interface_ip, $local_subnet)) {
									$srcip = $interface_ip;
									break;
								}
							}
						}
						/* if no valid src IP was found in configured interfaces, try the vips */
						if (is_null($srcip)) {
							$viplist = get_configured_vip_list();
							foreach ($viplist as $vip => $address) {
								if (ip_in_subnet($address, $local_subnet)) {
									$srcip = $address;
									break;
								}
							}
						}
						$dstip = $ph2ent['pinghost'];
						if (is_ipaddrv6($dstip)) {
							$family = "inet6";
						} else {
							$family = "inet";
						}
						if (is_ipaddr($srcip)) {
							$ipsecpinghosts[] = "{$srcip}|{$dstip}|3|||||{$family}|\n";
							$ipsecpinghostsactive = true;
						}
					}
				}
			}
			if ($is_vti) {
				interface_ipsec_vti_configure($ph1ent);
			}
		}
		@file_put_contents("{$g['vardb_path']}/ipsecpinghosts", $ipsecpinghosts);
		unset($ipsecpinghosts);
	}
	unset($iflist);
	/* Build a list of all IPsec interfaces configured on the firewall at the OS level */
	foreach (get_interface_arr() as $thisif) {
		if (substr($thisif, 0, 5) == "ipsec") {
			$ipsec_vti_cleanup_ifs[] = $thisif;
		}
	}

	$accept_unencrypted = "";
	if (isset($config['ipsec']['acceptunencryptedmainmode'])) {
		$accept_unencrypted = "accept_unencrypted_mainmode_messages = yes";
	}

	$stronconf = '';
	if (file_exists("{$g['varetc_path']}/ipsec/strongswan.conf")) {
		$stronconf = file_get_contents("{$g['varetc_path']}/ipsec/strongswan.conf");
	}

	$i_dont_care_about_security_and_use_aggressive_mode_psk = "";
	if ($aggressive_mode_psk) {
		log_error("WARNING: Setting i_dont_care_about_security_and_use_aggressive_mode_psk option because a phase 1 is configured using aggressive mode with pre-shared keys. This is not a secure configuration.");
		if (!empty($stronconf) && strpos($stronconf, 'i_dont_care_about_security_and_use_aggressive_mode_psk') === FALSE) {
			$restart = true;
		}
		$i_dont_care_about_security_and_use_aggressive_mode_psk = "i_dont_care_about_security_and_use_aggressive_mode_psk=yes";
	}

	$unity_enabled = isset($config['ipsec']['unityplugin']) ? 'yes' : 'no';

	$makebeforebreak = '';
	if (isset($config['ipsec']['makebeforebreak'])) {
		$makebeforebreak = 'make_before_break = yes';
	}

	if (isset($config['ipsec']['enableinterfacesuse'])) {
		if (!empty($ifacesuse)) {
			$ifacesuse = 'interfaces_use = ' . implode(',', array_unique($ifacesuse));
		} else {
			$ifacesuse = '';
		}
	} else {
		$ifacesuse = '';
	}

	unset($stronconf);

	$strongswanlog = "";
	$ipsecloglevels = vpn_logging_cfgtxt();
	if (is_array($ipsecloglevels)) {
		foreach ($ipsecloglevels as $loglevel) {
			$strongswanlog .= "\t\t\t" . $loglevel . "\n";
		}
	}
	$strongswan = <<<EOD

# Automatically generated config file - DO NOT MODIFY. Changes will be overwritten.
starter {
	load_warning = no
	config_file = {$g['varetc_path']}/ipsec/ipsec.conf
}

charon {
# number of worker threads in charon
	threads = 16
	ikesa_table_size = 32
	ikesa_table_segments = 4
	init_limit_half_open = 1000
	install_routes = no
	load_modular = yes
	ignore_acquire_ts = yes
	{$i_dont_care_about_security_and_use_aggressive_mode_psk}
	{$accept_unencrypted}
	cisco_unity = {$unity_enabled}
	{$ifacesuse}
	{$makebeforebreak}

	syslog {
		identifier = charon
		# log everything under daemon since it ends up in the same place regardless with our syslog.conf
		daemon {
			ike_name = yes
{$strongswanlog}
		}
		# disable logging under auth so logs aren't duplicated
		auth {
			default = -1
		}
	}

	plugins {
		# Load defaults
		include {$g['varetc_path']}/ipsec/strongswan.d/charon/*.conf

		stroke {
			secrets_file = {$g['varetc_path']}/ipsec/ipsec.secrets
		}

		unity {
			load = {$unity_enabled}
		}

		curve25519 {
			load = yes
		}

EOD;

	/* Find RADIUS servers designated for Mobile IPsec user auth */
	$radius_server_txt = "";
	$user_sources = explode(',', $config['ipsec']['client']['user_source']);
	foreach ($user_sources as $user_source) {
		$auth_server = auth_get_authserver($user_source);
		$nice_user_source = strtolower(preg_replace('/[\s\.]+/', '_', $user_source));
		if ($auth_server && $auth_server['type'] === 'radius') {
			$radius_server_txt .= <<<EOD
				{$nice_user_source} {
					address = {$auth_server['host']}
					secret = "{$auth_server['radius_secret']}"
					auth_port = {$auth_server['radius_auth_port']}
					acct_port = {$auth_server['radius_acct_port']}
				}

EOD;
		}
	}

	/* Activate RADIUS accounting if it was selected on the auth server view */
	$radius_accounting = "";
	if($auth_server && isset($auth_server['radius_acct_port'])){
		$radius_accounting = 'accounting = yes';
	}

	/* write an eap-radius config section if appropriate */
	if (strlen($radius_server_txt) && ($mobile_ipsec_auth === "eap-radius")) {
		$strongswan .= <<<EOD
		eap-radius {
			class_group = yes
			eap_start = no
			{$radius_accounting}
			servers {
{$radius_server_txt}
			}
		}

EOD;
	}

	if (is_array($a_client) && isset($a_client['enable'])) {
		$strongswan .= "\t\tattr {\n";

		$cfgservers = array();
		if (!empty($a_client['wins_server1'])) {
			$cfgservers[] = $a_client['wins_server1'];
		}
		if (!empty($a_client['wins_server2'])) {
			$cfgservers[] = $a_client['wins_server2'];
		}
		if (!empty($cfgservers)) {
			$strongswan .= "\t\t\tnbns = " . implode(",", $cfgservers) . "\n";
		}
		unset($cfgservers);

		if (isset($a_client['net_list']) && is_array($a_phase2)) {
			$net_list = '';
			foreach ($a_phase2 as $ph2ent) {
				if (isset($ph2ent['disabled'])) {
					continue;
				}

				if (!isset($ph2ent['mobile'])) {
					continue;
				}

				$localid = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);

				if (!empty($net_list)) {
					$net_list .= ",";
				}
				$net_list .= $localid;
			}

			if (!empty($net_list)) {
				$strongswan .= "\t\t\tsubnet = {$net_list}\n";
				$strongswan .= "\t\t\tsplit-include = {$net_list}\n";
				unset($net_list);
			}
		}

		if (!empty($a_client['dns_domain'])) {
			$strongswan .= "\t\t\t# Search domain and default domain\n";
			$strongswan .= "\t\t\t28674 = \"{$a_client['dns_domain']}\"\n";
			if (empty($a_client['dns_split'])) {
				$strongswan .= "\t\t\t28675 = \"{$a_client['dns_domain']}\"";
			}
			$strongswan .= "\n";
		}

		if (!empty($a_client['dns_split'])) {
			$strongswan .= "\t\t\t28675 = {$a_client['dns_split']}\n";
		}

		if (!empty($a_client['login_banner'])) {
			$strongswan .= "\t\t\t28672 = \"{$a_client['login_banner']}\"\n";
		}

		if (isset($a_client['save_passwd'])) {
			$strongswan .= "\t\t\t28673 = 1\n";
		}

		if ($a_client['pfs_group']) {
			$strongswan .= "\t\t\t28679 = \"{$a_client['pfs_group']}\"\n";
		}
		$strongswan .= "\t\t}\n";

		if ($a_client['user_source'] != "none") {
			$strongswan .= "\t\txauth-generic {\n";
			$strongswan .= "\t\t\tscript = /etc/inc/ipsec.auth-user.php\n";
			$strongswan .= "\t\t\tauthcfg = ";
			$firstsed = 0;
			$authcfgs = explode(",", $a_client['user_source']);
			foreach ($authcfgs as $authcfg) {
				if ($firstsed > 0) {
					$strongswan .= ",";
				}
				if ($authcfg == "system") {
					$authcfg = "Local Database";
				}
				$strongswan .= $authcfg;
				$firstsed = 1;
			}
			$strongswan .= "\n";
			$strongswan .= "\t\t}\n";
		}
	}

	$strongswan .= "\n\t}\n}\n";
	@file_put_contents("{$g['varetc_path']}/ipsec/strongswan.conf", $strongswan);
	unset($strongswan);

	/* write out CRL files */
	if (is_array($config['crl']) && count($config['crl'])) {
		foreach ($config['crl'] as $crl) {
			if (!isset($crl['text'])) {
				log_error(sprintf(gettext("Warning: Missing CRL data for %s"), $crl['descr']));
				continue;
			}
			$fpath = "{$crlpath}/{$crl['refid']}.crl";
			if (!@file_put_contents($fpath, base64_decode($crl['text']))) {
				log_error(sprintf(gettext("Error: Cannot write IPsec CRL file for %s"), $crl['descr']));
				continue;
			}
		}
	}

	$pskconf = "";

	$vpncas = array();
	if (is_array($a_phase1) && count($a_phase1)) {
		foreach ($a_phase1 as $ph1ent) {

			if (isset($ph1ent['disabled'])) {
				continue;
			}

			if (strstr($ph1ent['authentication_method'], 'cert') ||
			    in_array($ph1ent['authentication_method'], array('eap-mschapv2', 'eap-tls', 'eap-radius'))) {
				$certline = '';

				$ikeid = $ph1ent['ikeid'];
				$cert = lookup_cert($ph1ent['certref']);

				if (!$cert) {
					log_error(sprintf(gettext("Error: Invalid phase1 certificate reference for %s"), $ph1ent['name']));
					continue;
				}

				/* add signing CA cert chain of server cert
				 * to the list of CAs to write
				 */
				$cachain = ca_chain_array($cert);
				if ($cachain && is_array($cachain)) {
					foreach ($cachain as $cacrt) {
						$vpncas[$cacrt['refid']] = $cacrt;
					}
				}

				@chmod($certpath, 0600);

				$ph1keyfile = "{$keypath}/cert-{$ikeid}.key";
				if (!file_put_contents($ph1keyfile, base64_decode($cert['prv']))) {
					log_error(sprintf(gettext("Error: Cannot write phase1 key file for %s"), $ph1ent['name']));
					continue;
				}
				@chmod($ph1keyfile, 0600);

				$ph1certfile = "{$certpath}/cert-{$ikeid}.crt";
				if (!file_put_contents($ph1certfile, base64_decode($cert['crt']))) {
					log_error(sprintf(gettext("Error: Cannot write phase1 certificate file for %s"), $ph1ent['name']));
					@unlink($ph1keyfile);
					continue;
				}
				@chmod($ph1certfile, 0600);

				/* XXX" Traffic selectors? */
				$pskconf .= " : ";
				$pskconf .= (cert_get_pkey_curve($cert['prv']) === false) ? "RSA" : "ECDSA";
				$pskconf .= " {$ph1keyfile}\n";
			} else {
				list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, 'local');
				list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, 'peer', $rgmap);

				$myid = trim($myid_data);

				if (empty($peerid_data)) {
					continue;
				}

				if ($myid_type == 'fqdn' && !empty($myid)) {
					$myid = "@{$myid}";
				}

				$myid = isset($ph1ent['mobile']) ? trim($myid_data) : "%any";

				$peerid = ($peerid_data != 'allusers') ? trim($peerid_data) : '';

				if ($peerid_type == 'fqdn' && !empty($peerid)) {
					$peerid = "@{$peerid}";
				}

				if (!empty($ph1ent['pre-shared-key'])) {
					$pskconf .= "{$myid} {$peerid} : PSK 0s" . base64_encode(trim($ph1ent['pre-shared-key'])) . "\n";
					if (isset($ph1ent['mobile'])) {
						$pskconf .= "{$myid} %any : PSK 0s" . base64_encode(trim($ph1ent['pre-shared-key'])) . "\n";
						$pskconf .= " : PSK 0s" . base64_encode(trim($ph1ent['pre-shared-key'])) . "\n";
					}
				}
			}

			/* if the client authenticates with a cert add the
			 * client cert CA chain to the list of CAs to write
			 */
			if (in_array($ph1ent['authentication_method'],
			array('cert', 'eap-tls', 'xauth_cert_server'))) {

				if (!empty($ph1ent['caref']) && !array_key_exists($ph1ent['caref'], $vpncas)) {
					$thisca = lookup_ca($ph1ent['caref']);
					$vpncas[$ph1ent['caref']] = $thisca;

					/* follow chain up to root */
					$cachain = ca_chain_array($thisca);
					if ($cachain and is_array($cachain)) {
						foreach ($cachain as $cacrt) {
							$vpncas[$cacrt['refid']] = $cacrt;
						}
					}
				}
			}
		}
	}

	/* write the required CAs */
	foreach ($vpncas as $carefid => $cadata) {
		$cacrt = base64_decode($cadata['crt']);
		$cacrtattrs = openssl_x509_parse($cacrt);
		if (!is_array($cacrtattrs) || !isset($cacrtattrs['hash'])) {
			log_error(sprintf(gettext("Error: Invalid certificate hash info for %s"), $cadata['descr']));
			continue;
		}
		$cafilename = "{$capath}/{$cacrtattrs['hash']}.0.crt";
		if (!@file_put_contents($cafilename, $cacrt)) {
				log_error(sprintf(gettext("Error: Cannot write IPsec CA file for %s"), $cadata['descr']));
				continue;
		}
	}

	/* Add user PSKs */
	if (is_array($config['system']) && is_array($config['system']['user'])) {
		foreach ($config['system']['user'] as $user) {
			if (!empty($user['ipsecpsk'])) {
				$pskconf .= "{$myid} {$user['name']} : PSK 0s" . base64_encode($user['ipsecpsk']) . "\n";
			}
		}
		unset($user);
	}

	/* add PSKs for mobile clients */
	if (is_array($ipseccfg['mobilekey'])) {
		foreach ($ipseccfg['mobilekey'] as $key) {
			if ($key['ident'] == "allusers") {
				$key['ident'] = '%any';
			}
			if ($key['ident'] == "any") {
				$key['ident'] = '%any';
			}
			if (empty($key['type'])) {
				$key['type'] = 'PSK';
			}
			$pskconf .= " {$key['ident']} : {$key['type']} 0s" . base64_encode($key['pre-shared-key']) . "\n";
		}
		unset($key);
	}

	@file_put_contents("{$g['varetc_path']}/ipsec/ipsec.secrets", $pskconf);
	chmod("{$g['varetc_path']}/ipsec/ipsec.secrets", 0600);
	unset($pskconf);

	$uniqueids = 'yes';
	if (!empty($config['ipsec']['uniqueids'])) {
		if (array_key_exists($config['ipsec']['uniqueids'], $ipsec_idhandling)) {
			$uniqueids = $config['ipsec']['uniqueids'];
		}
	}
	$natfilterrules = false;
	/* begin ipsec.conf */
	$ipsecconf = "";
	$enablecompression = false;
	if (is_array($a_phase1) && count($a_phase1)) {

		$ipsecconf .= "# This file is automatically generated. Do not edit\n";
		$ipsecconf .= "config setup\n\tuniqueids = {$uniqueids}\n";

		if (isset($config['ipsec']['strictcrlpolicy'])) {
			$ipsecconf .= "\tstrictcrlpolicy = yes \n";
		}

		if (!isset($config['ipsec']['noshuntlaninterfaces'])) {
			$bypassnets = array();
			if ($config['interfaces']['lan']) {
				$lanip = get_interface_ip("lan");
				if (!empty($lanip) && is_ipaddrv4($lanip)) {
					$lansn = get_interface_subnet("lan");
					$lansa = gen_subnetv4($lanip, $lansn);
					if (!empty($lansa) && !empty($lansn)) {
						$bypassnets[] = "{$lansa}/{$lansn}";
					}
				}
				$lanip6 = get_interface_ipv6("lan");
				if (!empty($lanip6) && is_ipaddrv6($lanip6)) {
					$lansn6 = get_interface_subnetv6("lan");
					$lansa6 = gen_subnetv6($lanip6, $lansn6);
					if (!empty($lansa6) && !empty($lansn6)) {
						$bypassnets[] = "{$lansa6}/{$lansn6}";
					}
				}
			}
			if (!empty($bypassnets)) {
				$bypass = implode(',', $bypassnets);
				$ipsecconf .= <<<EOD

conn bypasslan
	leftsubnet = {$bypass}
	rightsubnet = {$bypass}
	authby = never
	type = passthrough
	auto = route

EOD;
			}
		}

		foreach ($a_phase1 as $ph1ent) {
			if (isset($ph1ent['disabled'])) {
				continue;
			}

			if ($ph1ent['mode'] == "aggressive") {
				$aggressive = "yes";
			} else {
				$aggressive = "no";
			}

			$ep = ipsec_get_phase1_src($ph1ent);
			if (!$ep) {
				continue;
			}

			$ikeid = $ph1ent['ikeid'];
			$keyexchange = "ikev1";
			$passive = "route";

			$closeaction = "";
			if (!empty($ph1ent['closeaction'])) {
				$closeaction = "closeaction = {$ph1ent['closeaction']}";
			}

			if (!empty($ph1ent['iketype'])) {
				if ($ph1ent['iketype'] == "ikev2") {
					$keyexchange = "ikev2";
				} elseif ($ph1ent['iketype'] == "auto") {
					$keyexchange = "ike";
				}
			}

			if (isset($ph1ent['mobile'])) {
				$right_spec = "%any";
				$passive = 'add';
			} else {
				if (isset($ph1ent['responderonly'])) {
					$passive = 'add';
				}

				$right_spec = $ph1ent['remote-gateway'];
				if (is_ipaddr($right_spec)) {
					$sourcehost = $right_spec;
				} else {
					$sourcehost = $rgmap[$right_spec];
				}

				if (substr($ph1ent['interface'], 0, 4) == "_vip") {
					$vpninterface = get_configured_vip_interface($ph1ent['interface']);
					if (substr($vpninterface, 0, 4) == "_vip") {
						// vips are nested if its a ipalias with a carp parent
						$vpninterface = get_configured_vip_interface($vpninterface);
					}
					$ifacesuse = get_real_interface($vpninterface);
				} else {
					$ifacesuse = get_failover_interface($ph1ent['interface']);
					if (substr($ifacesuse, 0, 4) == "_vip") {
						$vpninterface = get_configured_vip_interface($ifacesuse);
						$ifacesuse = get_real_interface($vpninterface);
					} else {
						$vpninterface = convert_real_interface_to_friendly_interface_name($ifacesuse);
					}
				}
				if ($ph1ent['protocol'] == 'inet') {
					if (!empty($ifacesuse) && interface_has_gateway($vpninterface)) {
						$gatewayip = get_interface_gateway($vpninterface);
						$interfaceip = get_interface_ip($vpninterface);
						$subnet_bits = get_interface_subnet($vpninterface);
						$subnet_ip = gen_subnetv4($interfaceip, $subnet_bits);
						/* if the remote gateway is in the local subnet, then don't add a route */
						if (is_ipaddrv4($sourcehost) &&
						    !ip_in_subnet($sourcehost, "{$subnet_ip}/{$subnet_bits}")) {
							if (is_ipaddrv4($gatewayip)) {
								// log_error("IPSEC interface is not WAN but {$ifacesuse}, adding static route for VPN endpoint {$rgip} via {$gatewayip}");
								route_add_or_change("-host {$sourcehost} {$gatewayip}");
							}
						}
					}
				} else if ($ph1ent['protocol'] == 'inet6') {
					if (!empty($ifacesuse) && interface_has_gatewayv6($vpninterface)) {
						$gatewayip = get_interface_gateway_v6($vpninterface);
						$interfaceip = get_interface_ipv6($vpninterface);
						$subnet_bits = get_interface_subnetv6($vpninterface);
						$subnet_ip = gen_subnetv6($interfaceip, $subnet_bits);
						/* if the remote gateway is in the local subnet, then don't add a route */
						if (is_ipaddrv6($sourcehost) &&
						    !ip_in_subnet($sourcehost, "{$subnet_ip}/{$subnet_bits}")) {
							if (is_ipaddrv6($gatewayip)) {
								// log_error("IPSEC interface is not WAN but {$ifacesuse}, adding static route for VPN endpoint {$rgip} via {$gatewayip}");
								route_add_or_change("-inet6 -host {$sourcehost} {$gatewayip}");
							}
						}
					}
				}
			}

			list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, 'local');
			$leftid = ipsec_fixup_id($myid_type, $myid_data);
			if (!empty($leftid)) {
				$leftid = "leftid = {$leftid}";
			}

			$peerid_spec = '';
			if (isset($ph1ent['mobile']) && ($ph1ent['authentication_method'] == "pre_shared_key" || $ph1ent['authentication_method'] == "xauth_psk_server")) {
				// Only specify peer ID if we are not dealing with mobile PSK
			} else {
				list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, 'peer', $rgmap);
				$peerid_spec = ipsec_fixup_id($peerid_type, $peerid_data);
			}

			$ealgosp1 = '';
			if (is_array($ph1ent['encryption']['item'])) {
				$ciphers = "";
				foreach($ph1ent['encryption']['item'] as $p1enc) {
					if (!is_array($p1enc['encryption-algorithm']) ||
							empty($p1enc['encryption-algorithm']['name']) ||
							empty($p1enc['hash-algorithm'])) {
						continue;
					}
					$ciphers .= ",";
					$ciphers .= $p1enc['encryption-algorithm']['name'];
					$ealg_kl = $p1enc['encryption-algorithm']['keylen'];
					if ($ealg_kl) {
						$ciphers .= "{$ealg_kl}";
					}
					$ciphers .= "-{$p1enc['hash-algorithm']}";

					$modp = vpn_ipsec_convert_to_modp($p1enc['dhgroup']);
					if (!empty($modp)) {
						$ciphers .= "-{$modp}";
					}
				}
				$ciphers = substr($ciphers, 1);
				$ealgosp1 = "ike = {$ciphers}!";
			}

			if ($ph1ent['dpd_delay'] && $ph1ent['dpd_maxfail']) {
				if ($passive == "route") {
					$dpdline = "dpdaction = restart";
				} else {
					$dpdline = "dpdaction = clear";
				}
				$dpdline .= "\n\tdpddelay = {$ph1ent['dpd_delay']}s";
				$dpdtimeout = $ph1ent['dpd_delay'] * ($ph1ent['dpd_maxfail'] + 1);
				$dpdline .= "\n\tdpdtimeout = {$dpdtimeout}s";
			} else {
				$dpdline = "dpdaction = none";
			}

			$ikelifeline = '';
			if ($ph1ent['lifetime']) {
				$ikelifeline = "ikelifetime = {$ph1ent['lifetime']}s";
			}

			$rightsourceip = NULL;
			$rightdnsserver = NULL;
			if (isset($ph1ent['mobile'])) {
				$rightsourceips = array();
				if (!empty($a_client['pool_address'])) {
					$rightsourceips[] = "{$a_client['pool_address']}/{$a_client['pool_netbits']}";
				}
				if (!empty($a_client['pool_address_v6'])) {
					$rightsourceips[] = "{$a_client['pool_address_v6']}/{$a_client['pool_netbits_v6']}";
				}
				if ($ph1ent['authentication_method'] == "eap-radius" && !count($rightsourceips)) {
					$rightsourceips[] = "%radius";
				}
				if (count($rightsourceips)) {
					$rightsourceip = "\trightsourceip = " . implode(',', $rightsourceips) . "\n";
				}

				$rightdnsservers = array();
				if (!empty($a_client['dns_server1'])) {
					$rightdnsservers[] = $a_client['dns_server1'];
				}
				if (!empty($a_client['dns_server2'])) {
					$rightdnsservers[] = $a_client['dns_server2'];
				}
				if (!empty($a_client['dns_server3'])) {
					$rightdnsservers[] = $a_client['dns_server3'];
				}
				if (!empty($a_client['dns_server4'])) {
					$rightdnsservers[] = $a_client['dns_server4'];
				}

				if (count($rightdnsservers)) {
					$rightdnsserver = "\trightdns = " . implode(',', $rightdnsservers) . "\n";
				}
			}

			if (!empty($ph1ent['caref'])) {
				$ca = lookup_ca($ph1ent['caref']);
				if ($ca) {
					$casubarr = cert_get_subject_array($ca['crt']);
					$casub = "";
					foreach ($casubarr as $casubfield) {
						if (empty($casub)) {
							$casub = "/";
						}
						/* The subfield value could be an array. To avoid duplicating code,
						 * always make it an array then iterate.
						 * See https://redmine.pfsense.org/issues/7929
						 */
						if (!is_array($casubfield['v'])) {
							$casubfield['v'] = array($casubfield['v']);
						}
						foreach ($casubfield['v'] as $casubval) {
							$casub .= "{$casubfield['a']}={$casubval}/";
						}
					}

				}
			}

			$authentication = "";
			switch ($ph1ent['authentication_method']) {
				case 'eap-mschapv2':
					if (isset($ph1ent['mobile'])) {
						$authentication = "eap_identity=%any\n\t";
						$authentication .= "leftauth=pubkey\n\trightauth=eap-mschapv2";
						if (!empty($ph1ent['certref'])) {
							$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
							$authentication .= "\n\tleftsendcert=always";
						}
					}
					break;
				case 'eap-tls':
					if (isset($ph1ent['mobile'])) {
						$authentication = "eap_identity=%identity\n\t";
						$authentication .= "leftauth=pubkey\n\trightauth=eap-tls";
						if (!empty($ph1ent['certref'])) {
							$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
							$authentication .= "\n\tleftsendcert=always";
						}
					} else {
						$authentication = "leftauth=eap-tls\n\trightauth=eap-tls";
						if (!empty($ph1ent['certref'])) {
							$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
							$authentication .= "\n\tleftsendcert=always";
						}
					}
					if (isset($casub)) {
						$authentication .= "\n\trightca=\"$casub\"";
					}
					break;
				case 'eap-radius':
					if (isset($ph1ent['mobile'])) {
						$authentication = "eap_identity=%identity\n\t";
						$authentication .= "leftauth=pubkey\n\trightauth=eap-radius";
						if (!empty($ph1ent['certref'])) {
							$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
							$authentication .= "\n\tleftsendcert=always";
						}
					} else {
						$authentication = "leftauth=eap-radius\n\trightauth=eap-radius";
						if (!empty($ph1ent['certref'])) {
							$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
							$authentication .= "\n\tleftsendcert=always";
						}
					}
					break;
				case 'xauth_cert_server':
					$authentication = "leftauth = pubkey\n\trightauth = pubkey";
					$authentication .= "\n\trightauth2 = xauth-generic";
					if (!empty($ph1ent['certref'])) {
						$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
						$authentication .= "\n\tleftsendcert=always";
					}
					if (isset($casub)) {
						$authentication .= "\n\trightca=\"$casub\"";
					}
					break;
				case 'xauth_psk_server':
					$authentication = "leftauth = psk\n\trightauth = psk";
					$authentication .= "\n\trightauth2 = xauth-generic";
					break;
				case 'pre_shared_key':
					$authentication = "leftauth = psk\n\trightauth = psk";
					break;
				case 'cert':
					$authentication = "leftauth = pubkey\n\trightauth = pubkey";
					if (!empty($ph1ent['certref'])) {
						$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
						$authentication .= "\n\tleftsendcert=always";
					}
					if (isset($casub)) {
						$authentication .= "\n\trightca=\"$casub\"";
					}
					break;
				case 'hybrid_cert_server':
					$authentication = "leftauth = pubkey\n\trightauth = xauth-generic";
					if (!empty($ph1ent['certref'])) {
						$authentication .= "\n\tleftcert={$certpath}/cert-{$ph1ent['ikeid']}.crt";
						$authentication .= "\n\tleftsendcert=always";
					}
					break;
			}

			$left_spec = $ep;

			if (isset($ph1ent['reauth_enable'])) {
				$reauth = "reauth = no";
			} else {
				$reauth = "reauth = yes";
			}

			if (isset($ph1ent['rekey_enable'])) {
				$rekeyline = "rekey = no";
			} else {
				$rekeyline = "rekey = yes";
				if(!empty($ph1ent['margintime'])){
					$rekeyline .= "\n\tmargintime = {$ph1ent['margintime']}s";
				}
			}

			if ($ph1ent['nat_traversal'] == 'off') {
				$forceencaps = 'forceencaps = no';
			} else if ($ph1ent['nat_traversal'] == 'force') {
				$forceencaps = 'forceencaps = yes';
			} else {
				$forceencaps = 'forceencaps = no';
			}

			if ($ph1ent['mobike'] == 'on') {
				$mobike = 'mobike = yes';
			} else {
				$mobike = 'mobike = no';
			}

			if (isset($ph1ent['tfc_enable'])) {
				if (isset($ph1ent['tfc_bytes']) && is_numericint($ph1ent['tfc_bytes'])) {
					$tfc = "tfc = {$ph1ent['tfc_bytes']}";
				} else {
					$tfc = "tfc = %mtu";
				}
			}

			$ipseclifetime = 0;
			$rightsubnet_spec = array();
			$leftsubnet_spec = array();
			$reqids = array();
			$vtireq = array();
			$ealgoAHsp2arr = array();
			$ealgoESPsp2arr = array();
			if (is_array($a_phase2) && count($a_phase2)) {
				foreach ($a_phase2 as $ph2ent) {
					if ($ikeid != $ph2ent['ikeid']) {
						continue;
					}

					if (isset($ph2ent['disabled'])) {
						continue;
					}

					if (isset($ph2ent['mobile']) && !isset($a_client['enable'])) {
						continue;
					}

					if (($ph2ent['mode'] == 'tunnel') or ($ph2ent['mode'] == 'tunnel6')) {
						$tunneltype = "type = tunnel";
						$installpolicy = "installpolicy = yes";

						$localid_type = $ph2ent['localid']['type'];
						$leftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);

						/* Do not print localid in some cases, such as a pure-psk or psk/xauth single phase2 mobile tunnel */
						if (($localid_type == "none" || $localid_type == "mobile") &&
						    isset($ph1ent['mobile']) && (ipsec_get_number_of_phase2($ikeid) == 1)) {
							$left_spec = '%any';
						} else {
							if ($localid_type != "address") {
								$localid_type = "subnet";
							}
							// Don't let an empty subnet into config, it can cause parse errors. Ticket #2201.
							if (!is_ipaddr($leftsubnet_data) && !is_subnet($leftsubnet_data) && ($leftsubnet_data != "0.0.0.0/0")) {
								log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
								continue;
							}
							if (!empty($ph2ent['natlocalid'])) {
								$natleftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['natlocalid'], false, $ph2ent['mode']);
								if ($ph2ent['natlocalid']['type'] != "address") {
									if (is_subnet($natleftsubnet_data)) {
										$leftsubnet_data = "{$natleftsubnet_data}|{$leftsubnet_data}";
									}
								} else {
									if (is_ipaddr($natleftsubnet_data)) {
										$leftsubnet_data = "{$natleftsubnet_data}|{$leftsubnet_data}";
									}
								}
								$natfilterrules = true;
							}
						}

						$leftsubnet_spec[] = $leftsubnet_data;

						if (!isset($ph2ent['mobile'])) {
							$tmpsubnet = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
							$rightsubnet_spec[] = $tmpsubnet;
						} else if (!empty($a_client['pool_address'])) {
							$rightsubnet_spec[] = "{$a_client['pool_address']}/{$a_client['pool_netbits']}";
						}
					} elseif ($ph2ent['mode'] == 'vti') {
						$tunneltype = "";
						$installpolicy = "installpolicy = no";
						$passive = 'start';

						$localid_type = $ph2ent['localid']['type'];
						$leftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
						$leftsubnet_spec[] = $leftsubnet_data;

						$tmpsubnet = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
						$rightsubnet_spec[] = $tmpsubnet;
						$vtireq[] = $ph2ent['reqid'];
					} else {
						$tunneltype = "type = transport";
						$installpolicy = "installpolicy = yes";

						if ((($ph1ent['authentication_method'] == "xauth_psk_server") ||
						    ($ph1ent['authentication_method'] == "pre_shared_key")) &&
						    isset($ph1ent['mobile'])) {
							$left_spec = "%any";
						} else {
							$tmpsubnet = ipsec_get_phase1_src($ph1ent);
							$leftsubnet_spec[] = $tmpsubnet;
						}

						if (!isset($ph2ent['mobile'])) {
							$rightsubnet_spec[] = $right_spec;
						}
					}

					if (isset($a_client['pfs_group']) && isset($ph2ent['mobile'])) {
						$ph2ent['pfsgroup'] = $a_client['pfs_group'];
					}

					if ($ph2ent['protocol'] == 'esp') {
						if (is_array($ph2ent['encryption-algorithm-option'])) {
							foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {
								$ealg_id = $ealg['name'];
								$ealg_kl = $ealg['keylen'];

								if (!empty($ealg_kl) && $ealg_kl == "auto") {
									if (empty($p2_ealgos) || !is_array($p2_ealgos)) {
										require_once("ipsec.inc");
									}
									$key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
									$key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
									$key_step = $p2_ealgos[$ealg_id]['keysel']['step'];
									/* XXX: in some cases where include ordering is suspect these variables
									 * are somehow 0 and we enter this loop forever and timeout after 900
									 * seconds wrecking bootup */
									if ($key_hi != 0 and $key_lo != 0 and $key_step != 0) {
										for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
											if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
												foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
													$halgo = str_replace('hmac_', '', $halgo);
													$tmpealgo = "{$ealg_id}{$keylen}-{$halgo}";
													$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
													if (!empty($modp)) {
														$tmpealgo .= "-{$modp}";
													}
													if (!in_array($tmpealgo, $ealgoESPsp2arr)) {
														$ealgoESPsp2arr[] = $tmpealgo;
													}
												}
											} else {
												$tmpealgo = "{$ealg_id}{$keylen}";
												$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
												if (!empty($modp)) {
													$tmpealgo .= "-{$modp}";
												}
												if (!in_array($tmpealgo, $ealgoESPsp2arr)) {
													$ealgoESPsp2arr[] = $tmpealgo;
												}
											}
										}
									}
								} else {
									if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
										foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
											$halgo = str_replace('hmac_', '', $halgo);
											$tmpealgo = "{$ealg_id}{$ealg_kl}-{$halgo}";
											$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
											if (!empty($modp)) {
												$tmpealgo .= "-{$modp}";
											}
											if (!in_array($tmpealgo, $ealgoESPsp2arr)) {
												$ealgoESPsp2arr[] = $tmpealgo;
											}
										}
									} else {
										$tmpealgo = "{$ealg_id}{$ealg_kl}";
										$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
										if (!empty($modp)) {
											$tmpealgo .= "-{$modp}";
										}
										if (!in_array($tmpealgo, $ealgoESPsp2arr)) {
											$ealgoESPsp2arr[] = $tmpealgo;
										}
									}
								}
							}
						}
					} else if ($ph2ent['protocol'] == 'ah') {
						if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
							$modp = vpn_ipsec_convert_to_modp($ph2ent['pfsgroup']);
							foreach ($ph2ent['hash-algorithm-option'] as $tmpAHalgo) {
								$tmpAHalgo = str_replace('hmac_', '', $tmpAHalgo);
								if (!empty($modp)) {
									$tmpAHalgo = "-{$modp}";
								}
								if (!in_array($tmpAHalgo, $ealgoAHsp2arr)) {
									$ealgoAHsp2arr[] = $tmpAHalgo;
								}
							}
						}
					}

					$reqids[] = $ph2ent['reqid'];
					if ($ph2ent['mode'] != 'vti') {
						$vtireq[] = null;
					}

					if (!empty($ph2ent['lifetime'])) {
						if ($ipseclifetime == 0 || intval($ipseclifetime) > intval($ph2ent['lifetime'])) {
							$ipseclifetime = intval($ph2ent['lifetime']);
						}
					}

				}
			}

			$ipsecconnect =<<<EOD
	fragmentation = yes
	keyexchange = {$keyexchange}
	{$reauth}
	{$forceencaps}
	{$mobike}
	{$tfc}
	{$rekeyline}
	{$installpolicy}
	{$tunneltype}
	{$dpdline}
	{$closeaction}
	auto = {$passive}
	left = {$left_spec}
	right = {$right_spec}
	{$leftid}

EOD;

			/* Disable ipcomp for now. redmine #6167
			if (isset($config['ipsec']['compression'])) {
				$ipsecconnect .= "\tcompress = yes\n";
				$enablecompression = true;
			} */
			if (!empty($ikelifeline)) {
				$ipsecconnect .= "\t{$ikelifeline}\n";
			}
			if ($ipseclifetime > 0) {
				$ipsecconnect .= "\tlifetime = {$ipseclifetime}s\n";
			}
			if (!empty($rightsourceip)) {
				$ipsecconnect .= "{$rightsourceip}";
			}
			if (!empty($rightdnsserver)) {
				$ipsecconnect .= "{$rightdnsserver}";
			}
			if (!empty($ealgosp1)) {
				$ipsecconnect .= "\t{$ealgosp1}\n";
			}
			if (!empty($ealgoAHsp2arr)) {
				$ipsecconnect .= "\tah = " . join(',', $ealgoAHsp2arr) . "!\n";
			}
			if (!empty($ealgoESPsp2arr)) {
				$ipsecconnect .= "\tesp = " . join(',', $ealgoESPsp2arr) . "!\n";
			}
			if (!empty($authentication)) {
				$ipsecconnect .= "\t{$authentication}\n";
			}
			if (!empty($peerid_spec)) {
				$ipsecconnect .= "\trightid = {$peerid_spec}\n";
			}
			if ($keyexchange != 'ikev2') {
				$ipsecconnect .= "\taggressive = {$aggressive}\n";
			}

			if (!isset($ph1ent['mobile']) && ($keyexchange == 'ikev1' || isset($ph1ent['splitconn']))) {
				if (!empty($rightsubnet_spec)) {
					$ipsecfin = '';
					foreach ($rightsubnet_spec as $idx => $rsubnet) {
						$ipsecfin .= "\nconn con{$ph1ent['ikeid']}00{$idx}\n";
						//if (!empty($reqids[$idx])) {
						//	$ipsecfin .= "\treqid = " . $reqids[$idx] . "\n";
						//}
						$rightadd = "";
						$leftadd = "";
						if (!empty($vtireq[$idx])) {
							$ipsecfin .= "\treqid = {$ph1ent['ikeid']}00{$idx}\n";
							/* This interface will be a valid IPsec interface, so remove it from the cleanup list. */
							$ipsec_vti_cleanup_ifs = array_diff($ipsec_vti_cleanup_ifs, array("ipsec{$ph1ent['ikeid']}00{$idx}"));
							$rightadd = ",0.0.0.0/0";
							$leftadd = ",0.0.0.0/0";
						}
						$ipsecfin .= $ipsecconnect;
						$ipsecfin .= "\trightsubnet = {$rsubnet}{$rightadd}\n";
						$ipsecfin .= "\tleftsubnet = " . $leftsubnet_spec[$idx] . "{$leftadd}\n";
					}
				} else {
					log_error(sprintf(gettext("No phase2 specifications for tunnel with REQID = %s"), $ikeid));
				}
			} else {
				if (isset($ph1ent['mobile'])) {
					$ipsecfin = "\nconn con-mobile\n";
				}
				else {
					$ipsecfin = "\nconn con{$ph1ent['ikeid']}000\n";
				}
				//if (!empty($reqids[$idx])) {
				//	$ipsecfin .= "\treqid = " . $reqids[0] . "\n";
				//}
				$rightadd = "";
				$leftadd = "";
				if (!empty($vtireq[0])) {
					$ipsecfin .= "\treqid = {$ph1ent['ikeid']}000\n";
					/* This interface will be a valid IPsec interface, so remove it from the cleanup list. */
					$ipsec_vti_cleanup_ifs = array_diff($ipsec_vti_cleanup_ifs, array("ipsec{$ph1ent['ikeid']}000"));
					$rightadd = ",0.0.0.0/0";
					$leftadd = ",0.0.0.0/0";
				}
				$ipsecfin .= $ipsecconnect;
				if (!isset($ph1ent['mobile']) && !empty($rightsubnet_spec)) {
					$tempsubnets = array();
					foreach ($rightsubnet_spec as $rightsubnet) {
						$tempsubnets[$rightsubnet] = $rightsubnet;
					}
					$ipsecfin .= "\trightsubnet = " . join(",", $tempsubnets) . "{$rightadd}\n";
					unset($tempsubnets, $rightsubnet);
				}
				if (!empty($leftsubnet_spec)) {
					$tempsubnets = array();
					foreach ($leftsubnet_spec as $leftsubnet) {
						$tempsubnets[$leftsubnet] = $leftsubnet;
					}
					$ipsecfin .= "\tleftsubnet = " . join(",", $tempsubnets) . "{$leftadd}\n";
					unset($tempsubnets, $leftsubnet);
				}
			}
			$ipsecconf .= $ipsecfin;
			unset($ipsecfin);
		}
	}

	$a_mobilekey = $config['ipsec']['mobilekey'];

	if (is_array($a_phase1) && count($a_phase1)) {
		foreach ($a_phase1 as $ph1ent) {
			if (!isset($ph1ent['mobile'])) {
				continue;
			}
			if (isset($ph1ent['disabled'])) {
				continue;
			}

			if (is_array($a_mobilekey) && count($a_mobilekey)) {
				$ipsecfin = '';
				$mobilekey_counter = 1;
				foreach ($a_mobilekey as $mkent) {
					if ($mkent['type'] != "EAP") {
						continue;
					}

					if (!isset($mkent['ident_type']) || !isset($mkent['pool_address']) || !isset($mkent['pool_netbits'])) {
						continue;
					}

					if (strlen($mkent['pool_address']) < 1 || !is_ipaddr($mkent['pool_address'])) {
						continue;
					}
					$clientid = ($mkent['ident_type'] == "none") ? "\"{$mkent['ident']}\"" : "{$mkent['ident_type']}:{$mkent['ident']}";
					$clienteapid = ($ph1ent['authentication_method'] == "eap-mschapv2") ? $clientid : '%identity';
					$ipsecfin .= "\nconn mobile-{$mobilekey_counter}\n";
					$ipsecfin .= "\talso = con-mobile\n";
					$ipsecfin .= "\teap_identity = {$clienteapid}\n";
					$ipsecfin .= "\trightsourceip = {$mkent['pool_address']}/{$mkent['pool_netbits']}\n";

					if (isset($mkent['dns_address']) && strlen($mkent['dns_address']) > 0 && is_ipaddr($mkent['dns_address'])) {
						$ipsecfin .= "\trightdns = {$mkent['dns_address']}\n";
					}

					$ipsecfin .= "\trightid = {$clientid}\n";

					// optional: define left|rightid more granular
					// supported: ipv4, ipv6, rfc822, email, userfqdn, fqdn, dns, asn1dn, asn1gn, keyid
					// example: $ipsecfin = "\trightid = email:your@email.address\n";

					// note: comma separated list for access restriction, regardless of firewall rules
					// example: $ipsecfin = "\tleftsubnet = 1.1.1.1/32,1.1.1.2/32,2.2.2.0/24\n";

					$mobilekey_counter++;
				}
				$ipsecconf .= $ipsecfin;
				unset($ipsecfin);
				unset($mobilekey_counter);
			}
		}
	}

	@file_put_contents("{$g['varetc_path']}/ipsec/ipsec.conf", $ipsecconf);
	unset($ipsecconf);
	/* end ipsec.conf */

	foreach ($ipsec_vti_cleanup_ifs as $cleanif) {
		if (does_interface_exist($cleanif)) {
			mwexec("/sbin/ifconfig " . escapeshellarg($cleanif) . " destroy", false);
		}
	}

	if ($enablecompression === true) {
		set_single_sysctl('net.inet.ipcomp.ipcomp_enable', 1);
	} else {
		set_single_sysctl('net.inet.ipcomp.ipcomp_enable', 0);
	}

	/* manage process */
	if ($restart === true) {
		mwexec("/usr/local/sbin/ipsec restart", false);
	} else {
		if (isvalidpid("{$g['varrun_path']}/starter.charon.pid")) {
			/* Update configuration changes */
			/* Read secrets */
			mwexec("/usr/local/sbin/ipsec rereadall", false);
			mwexec("/usr/local/sbin/ipsec reload", false);
		} else {
			mwexec("/usr/local/sbin/ipsec start", false);
		}
	}

	// run ping_hosts.sh once if it's enabled to avoid wait for minicron
	if ($ipsecpinghostsactive == true) {
		mwexec_bg("/usr/local/bin/ping_hosts.sh");
	}

	if ($natfilterrules == true) {
		filter_configure();
	}
	/* start filterdns, if necessary */
	if (count($filterdns_list) > 0) {
		$interval = 60;
		if (!empty($ipseccfg['dns-interval']) && is_numeric($ipseccfg['dns-interval'])) {
			$interval = $ipseccfg['dns-interval'];
		}

		$hostnames = "";
		array_unique($filterdns_list);
		foreach ($filterdns_list as $hostname) {
			$hostnames .= "cmd {$hostname} '/usr/local/sbin/pfSctl -c \"service reload ipsecdns\"'\n";
		}
		file_put_contents("{$g['varetc_path']}/ipsec/filterdns-ipsec.hosts", $hostnames);
		unset($hostnames);

		if (isvalidpid("{$g['varrun_path']}/filterdns-ipsec.pid")) {
			sigkillbypid("{$g['varrun_path']}/filterdns-ipsec.pid", "HUP");
		} else {
			mwexec("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-ipsec.pid -i {$interval} -c {$g['varetc_path']}/ipsec/filterdns-ipsec.hosts -d 1");
		}
	} else {
		killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");
		@unlink("{$g['varrun_path']}/filterdns-ipsec.pid");
	}

	if (platform_booting()) {
		echo "done\n";
	}

	unlock($ipsecstartlock);
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

	if (!ipsec_enabled()) {
		return;
	}

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

	/* If we get this far then we need to take action. */
	log_error(gettext("Forcefully reloading IPsec"));
	vpn_ipsec_configure();
}

/* master setup for vpn (mpd) */
function vpn_setup() {
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
		if (!empty($ifinfo) && in_array($ifinfo['iftype'], array("ether", "vlan", "bridge"))) {
			pfSense_ngctl_attach(".", $realif);
		}
	}
}

function vpn_pppoes_configure() {
	global $config;

	if (is_array($config['pppoes']['pppoe'])) {
		foreach ($config['pppoes']['pppoe'] as $pppoe) {
			vpn_pppoe_configure($pppoe);
		}
	}
}

function vpn_pppoe_configure(&$pppoecfg) {
	global $config, $g;

	$syscfg = $config['system'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn")) {
		mkdir("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn");
	}

	if (platform_booting()) {
		if (!$pppoecfg['mode'] || ($pppoecfg['mode'] == "off")) {
			return 0;
		}

		echo gettext("Configuring PPPoE Server service... ");
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");

		/* wait for process to die */
		sleep(2);

	}

	switch ($pppoecfg['mode']) {

		case 'server':

			$pppoe_interface = get_real_interface($pppoecfg['interface']);

			if ($pppoecfg['paporchap'] == "chap") {
				$paporchap = "set link enable chap";
			} else {
				$paporchap = "set link enable pap";
			}

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.conf in vpn_pppoe_configure().") . "\n");
				return 1;
			}

			$issue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 ";
			if (isset($pppoecfg['radius']['radiusissueips']) && isset($pppoecfg['radius']['server']['enable'])) {
				$issue_ip_type .= "0.0.0.0/0";
			} else {
				$issue_ip_type .= "ippool p0";
			}

			$ippool_p0 = ip_after($pppoecfg['remoteip'], $pppoecfg['n_pppoe_units'] - 1);

			if (is_numeric($pppoecfg['n_pppoe_maxlogin']) && ($pppoecfg['n_pppoe_maxlogin'] > 0)) {
				$pppoemaxlogins = $pppoecfg['n_pppoe_maxlogin'];
			} else {
				$pppoemaxlogins = 1;
			}

			$ipcp_dns = '';
			if (!empty($pppoecfg['dns1'])) {
				$ipcp_dns = "set ipcp dns " . $pppoecfg['dns1'];
				if (!empty($pppoecfg['dns2'])) {
					$ipcp_dns .= " " . $pppoecfg['dns2'];
				}
			} elseif (isset($config['dnsmasq']['enable']) ||
			    isset ($config['unbound']['enable'])) {
				$ipcp_dns = "set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0]) {
					$ipcp_dns .= " " . $syscfg['dnsserver'][0];
				}
			} elseif (is_array($syscfg['dnsserver']) &&
			    ($syscfg['dnsserver'][0])) {
				$ipcp_dns = "set ipcp dns " . join(" ", $syscfg['dnsserver']);
			}

			$mpdconf = <<<EOD
startup:

poes:
	set ippool add p0 {$pppoecfg['remoteip']} {$ippool_p0}

	create bundle template poes_b
	set bundle enable compression

	set ccp yes mppc
	set mppc yes e40
	set mppc yes e128
	set mppc yes stateless

	set iface group pppoe
	set iface up-script /usr/local/sbin/vpn-linkup-poes
	set iface down-script /usr/local/sbin/vpn-linkdown-poes
	set iface idle 0
	set iface disable on-demand
	set iface disable proxy-arp
	set iface enable tcpmssfix
	set iface mtu 1500

	set ipcp no vjcomp
	{$issue_ip_type}
	{$ipcp_dns}

	create link template poes_l pppoe
	set link action bundle poes_b

	set auth max-logins {$pppoemaxlogins}

	set pppoe iface {$pppoe_interface}

	set link no multilink
	set link no pap chap
	{$paporchap}
	set link keep-alive 60 180
	set link max-redial -1
	set link mru 1492
	set link latency 1
	set link enable incoming

EOD;

			if (isset ($pppoecfg['radius']['server']['enable'])) {
				$radiusport = "";
				$radiusacctport = "";
				if (isset($pppoecfg['radius']['server']['port'])) {
					$radiusport = $pppoecfg['radius']['server']['port'];
				}
				if (isset($pppoecfg['radius']['server']['acctport'])) {
					$radiusacctport = $pppoecfg['radius']['server']['acctport'];
				}
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
				if (!empty($pppoecfg['radius']['nasip'])) {
					$mpdconf .= "\tset radius me {$pppoecfg['radius']['nasip']}\n";
				}
			}

			fwrite($fd, $mpdconf);
			fclose($fd);
			unset($mpdconf);

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
					foreach ($item as $userdata) {
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
			while (file_exists("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid") && isvalidpid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid")) {
				killbypid("{$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid");
			}

			/* Get support for netgraph(4) from the nic */
			pfSense_ngctl_attach(".", $pppoe_interface);
			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/pppoe{$pppoecfg['pppoeid']}-vpn -p {$g['varrun_path']}/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

			break;
	}

	if (platform_booting()) {
		echo gettext("done") . "\n";
	}

	return 0;
}

function vpn_l2tp_configure() {
	global $config, $g;

	$syscfg = $config['system'];
	$l2tpcfg = $config['l2tp'];

	/* create directory if it does not exist */
	if (!is_dir("{$g['varetc_path']}/l2tp-vpn")) {
		mkdir("{$g['varetc_path']}/l2tp-vpn");
	}

	if (platform_booting()) {
		if (!$l2tpcfg['mode'] || ($l2tpcfg['mode'] == "off")) {
			return 0;
		}

		echo gettext("Configuring l2tp VPN service... ");
	} else {
		/* kill mpd */
		killbypid("{$g['varrun_path']}/l2tp-vpn.pid");

		/* wait for process to die */
		sleep(8);

	}

	/* make sure l2tp-vpn directory exists */
	if (!file_exists("{$g['varetc_path']}/l2tp-vpn")) {
		mkdir("{$g['varetc_path']}/l2tp-vpn");
	}

	switch ($l2tpcfg['mode']) {

		case 'server':
			$l2tp_listen="";
			$ipaddr = get_interface_ip(get_failover_interface($l2tpcfg['interface']));
			if (is_ipaddrv4($ipaddr)) {
				$l2tp_listen="set l2tp self $ipaddr";
			}

			switch ($l2tpcfg['paporchap']) {
				case 'chap':
					$paporchap = "set link enable chap";
					break;
				case 'chap-msv2':
					$paporchap = "set link enable chap-msv2";
					break;
				default:
					$paporchap = "set link enable pap";
					break;
			}

			/* write mpd.conf */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.conf", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
				return 1;
			}

			$ippool_p0 = ip_after($l2tpcfg['remoteip'], $l2tpcfg['n_l2tp_units'] - 1);

			$issue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 ";
			if (isset($l2tpcfg['radius']['radiusissueips']) && isset($l2tpcfg['radius']['server']['enable'])) {
				$issue_ip_type .= "0.0.0.0/0";
			} else {
				$issue_ip_type .= "ippool p0";
			}

			$ipcp_dns = '';
			if (is_ipaddr($l2tpcfg['dns1'])) {
				$ipcp_dns = "set ipcp dns " . $l2tpcfg['dns1'];
				if (is_ipaddr($l2tpcfg['dns2'])) {
					$ipcp_dns .= " " . $l2tpcfg['dns2'];
				}
			} elseif (isset ($config['dnsmasq']['enable']) ||
			    isset ($config['unbound']['enable'])) {
				$ipcp_dns = "set ipcp dns " . get_interface_ip("lan");
				if ($syscfg['dnsserver'][0]) {
					$ipcp_dns .= " " . $syscfg['dnsserver'][0];
				}
			} elseif (is_array($syscfg['dnsserver']) &&
			    ($syscfg['dnsserver'][0])) {
				$ipcp_dns = "set ipcp dns " . join(" ", $syscfg['dnsserver']);
			}

			$mpdconf =<<<EOD

startup:

l2tps:
	set ippool add p0 {$l2tpcfg['remoteip']} {$ippool_p0}

	create bundle template l2tp_b
	set bundle enable compression
	set bundle yes crypt-reqd

	set ccp yes mppc

	set iface name l2tp
	set iface group l2tp
	set iface up-script /usr/local/sbin/vpn-linkup-l2tp
	set iface down-script /usr/local/sbin/vpn-linkdown-l2tp
	set iface disable on-demand
	set iface enable proxy-arp

	set ipcp yes vjcomp
	{$issue_ip_type}
	{$ipcp_dns}

	create link template l2tp_l l2tp
	set link action bundle l2tp_b

	set link yes acfcomp protocomp
	set link enable multilink
	set link no pap chap chap-msv2
	{$paporchap}
	{$l2tp_listen}
	set link keep-alive 10 180
	set link enable incoming

EOD;


			if (isset ($l2tpcfg['radius']['enable'])) {
				$mpdconf .=<<<EOD
	set radius server {$l2tpcfg['radius']['server']} "{$l2tpcfg['radius']['secret']}"
	set radius retries 3
	set radius timeout 10
	set auth disable internal
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

			/* write mpd.secret */
			$fd = fopen("{$g['varetc_path']}/l2tp-vpn/mpd.secret", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_configure().") . "\n");
				return 1;
			}

			$mpdsecret = "\n\n";

			if (is_array($l2tpcfg['user'])) {
				foreach ($l2tpcfg['user'] as $user) {
					$mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
				}
			}

			fwrite($fd, $mpdsecret);
			fclose($fd);
			unset($mpdsecret);
			chmod("{$g['varetc_path']}/l2tp-vpn/mpd.secret", 0600);

			vpn_netgraph_support();

			/* fire up mpd */
			mwexec("/usr/local/sbin/mpd5 -b -d {$g['varetc_path']}/l2tp-vpn -p {$g['varrun_path']}/l2tp-vpn.pid -s l2tps l2tps");

			break;

		case 'redir':
			break;
	}

	if (platform_booting()) {
		echo "done\n";
	}

	return 0;
}

?>
