<?php
/*
 * ipsec.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc.
 * Copyright (c) 2007-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2019 Rubicon Communications, LLC (Netgate)
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

/* IPsec defines */
global $ipsec_loglevels;
$ipsec_loglevels = array(
	"dmn" => gettext("Daemon"),
	"mgr" => gettext("SA Manager"),
	"ike" => gettext("IKE SA"),
	"chd" => gettext("IKE Child SA"),
	"job" => gettext("Job Processing"),
	"cfg" => gettext("Configuration backend"),
	"knl" => gettext("Kernel Interface"),
	"net" => gettext("Networking"),
	"asn" => gettext("ASN encoding"),
	"enc" => gettext("Message encoding"),
	"imc" => gettext("Integrity checker"),
	"imv" => gettext("Integrity Verifier"),
	"pts" => gettext("Platform Trust Service"),
	"tls" => gettext("TLS handler"),
	"esp" => gettext("IPsec traffic"),
	"lib" => gettext("StrongSwan Lib")
);

global $ipsec_log_sevs;
$ipsec_log_sevs = array(
	'-1' => gettext('Silent'),
	'0' => gettext('Audit'),
	'1' => gettext('Control'),
	'2' => gettext('Diag'),
	'3' => gettext('Raw'),
	'4' => gettext('Highest')
);

global $ipsec_log_cats;
$ipsec_log_cats = array(
	"dmn" => gettext("Daemon"),
	"mgr" => gettext("SA Manager"),
	"ike" => gettext("IKE SA"),
	"chd" => gettext("IKE Child SA"),
	"job" => gettext("Job Processing"),
	"cfg" => gettext("Configuration backend"),
	"knl" => gettext("Kernel Interface"),
	"net" => gettext("Networking"),
	"asn" => gettext("ASN encoding"),
	"enc" => gettext("Message encoding"),
	"imc" => gettext("Integrity checker"),
	"imv" => gettext("Integrity Verifier"),
	"pts" => gettext("Platform Trust Service"),
	"tls" => gettext("TLS handler"),
	"esp" => gettext("IPsec traffic"),
	"lib" => gettext("StrongSwan Lib")
);

global $ipsec_identifier_list;
$ipsec_identifier_list = array(
	// 'ipv4' => array('desc' => gettext('IPv4 address'), 'mobile' => true),
	// 'ipv6' => array('desc' => gettext('IPv6 address'), 'mobile' => true),
	// 'rfc822' => array('desc' => gettext('RFC822'), 'mobile' => true),
	'none' => array('desc' => '', 'mobile' => true),
	'email' => array('desc' => gettext('E-mail address'), 'mobile' => true),
	'userfqdn' => array('desc' => gettext('User Fully Qualified Domain Name'), 'mobile' => true)
	// 'fqdn' => array('desc' => gettext('Fully Qualified Domain Name'), 'mobile' => true),
	// 'dns' => array('desc' => gettext('DNS'), 'mobile' => true),
	// 'asn1dn' => array('desc' => gettext('ASN.1 Distinguished Name'), 'mobile' => true),
	// 'asn1gn' => array('desc' => gettext('ASN.1 GN'), 'mobile' => true),
	// 'keyid' => array('desc' => gettext('KeyID'), 'mobile' => true)
);

global $my_identifier_list;
$my_identifier_list = array(
	'myaddress' => array('desc' => gettext('My IP address'), 'mobile' => true),
	'address' => array('desc' => gettext('IP address'), 'mobile' => true),
	'fqdn' => array('desc' => gettext('Distinguished name'), 'mobile' => true),
	'user_fqdn' => array('desc' => gettext('User distinguished name'), 'mobile' => true),
	'asn1dn' => array('desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true),
	'keyid tag' => array('desc' => gettext('KeyID tag'), 'mobile' => true),
	'dyn_dns' => array('desc' => gettext('Dynamic DNS'), 'mobile' => true)
);

global $peer_identifier_list;
$peer_identifier_list = array(
	'any' => array('desc' => gettext('Any'), 'mobile' => true),
	'peeraddress' => array('desc' => gettext('Peer IP address'), 'mobile' => false),
	'address' => array('desc' => gettext('IP address'), 'mobile' => false),
	'fqdn' => array('desc' => gettext('Distinguished name'), 'mobile' => true),
	'user_fqdn' => array('desc' => gettext('User distinguished name'), 'mobile' => true),
	'asn1dn' => array('desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true),
	'keyid tag' => array('desc' =>gettext('KeyID tag'), 'mobile' => true)
);

global $ipsec_idhandling;
$ipsec_idhandling = array(
	'yes' => 'YES', 'no' => 'NO', 'never' => 'NEVER', 'keep' => 'KEEP'
);

global $p1_ealgos;
$p1_ealgos = array(
	'aes' => array('name' => 'AES', 'keysel' => array('lo' => 128, 'hi' => 256, 'step' => 64)),
	'aes128gcm' => array('name' => 'AES128-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'aes192gcm' => array('name' => 'AES192-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'aes256gcm' => array('name' => 'AES256-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'blowfish' => array('name' => 'Blowfish', 'keysel' => array('lo' => 128, 'hi' => 256, 'step' => 64)),
	'3des' => array('name' => '3DES'),
	'cast128' => array('name' => 'CAST128')
);

global $p2_ealgos;
$p2_ealgos = array(
	'aes' => array('name' => 'AES', 'keysel' => array('lo' => 128, 'hi' => 256, 'step' => 64)),
	'aes128gcm' => array('name' => 'AES128-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'aes192gcm' => array('name' => 'AES192-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'aes256gcm' => array('name' => 'AES256-GCM', 'keysel' => array('lo' => 64, 'hi' => 128, 'step' => 32)),
	'blowfish' => array('name' => 'Blowfish', 'keysel' => array('lo' => 128, 'hi' => 256, 'step' => 64)),
	'3des' => array('name' => '3DES'),
	'cast128' => array('name' => 'CAST128')
);

global $p1_halgos;
$p1_halgos = array(
	'md5' => 'MD5',
	'sha1' => 'SHA1',
	'sha256' => 'SHA256',
	'sha384' => 'SHA384',
	'sha512' => 'SHA512',
	'aesxcbc' => 'AES-XCBC'
);

global $p1_dhgroups;
$p1_dhgroups = array(
	1  => '1 (768 bit)',
	2  => '2 (1024 bit)',
	5  => '5 (1536 bit)',
	14 => '14 (2048 bit)',
	15 => '15 (3072 bit)',
	16 => '16 (4096 bit)',
	17 => '17 (6144 bit)',
	18 => '18 (8192 bit)',
	19 => '19 (nist ecp256)',
	20 => '20 (nist ecp384)',
	21 => '21 (nist ecp521)',
	22 => '22 (1024(sub 160) bit)',
	23 => '23 (2048(sub 224) bit)',
	24 => '24 (2048(sub 256) bit)',
	25 => '25 (nist ecp192)',
	26 => '26 (nist ecp224)',
	27 => '27 (brainpool ecp224)',
	28 => '28 (brainpool ecp256)',
	29 => '29 (brainpool ecp384)',
	30 => '30 (brainpool ecp512)',
	31 => '31 (Elliptic Curve 25519, 256 bit)',
	32 => '32 (Elliptic Curve 25519, 448 bit)',
);

global $p2_halgos;
$p2_halgos = array(
	'hmac_md5' => 'MD5',
	'hmac_sha1' => 'SHA1',
	'hmac_sha256' => 'SHA256',
	'hmac_sha384' => 'SHA384',
	'hmac_sha512' => 'SHA512',
	'aesxcbc' => 'AES-XCBC'
);

global $p1_authentication_methods;
$p1_authentication_methods = array(
	'hybrid_rsa_server' => array('name' => gettext('Hybrid RSA + Xauth'), 'mobile' => true),
	'xauth_rsa_server' => array('name' => gettext('Mutual RSA + Xauth'), 'mobile' => true),
	'xauth_psk_server' => array('name' => gettext('Mutual PSK + Xauth'), 'mobile' => true),
	'eap-tls' => array('name' => gettext('EAP-TLS'), 'mobile' => true),
	'eap-radius' => array('name' => gettext('EAP-RADIUS'), 'mobile' => true),
	'eap-mschapv2' => array('name' => gettext('EAP-MSChapv2'), 'mobile' => true),
	'rsasig' => array('name' => gettext('Mutual RSA'), 'mobile' => false),
	'pkcs11' => array('name' => gettext('Mutual RSA (PKCS#11)'), 'mobile' => false),
	'pre_shared_key' => array('name' => gettext('Mutual PSK'), 'mobile' => false)
);

global $ipsec_preshared_key_type;
$ipsec_preshared_key_type = array(
	'PSK' => 'PSK',
	'EAP' => 'EAP'
);

global $ipsec_closeactions;
$ipsec_closeactions = array(
	'' => gettext('Default'),
	'none' => gettext('No action'),
	'restart' => gettext('Restart/Reconnect'),
	'clear' => gettext('Close connection and clear SA'),
	'hold' => gettext('Close connection and reconnect on demand'),
);

global $p2_modes;
$p2_modes = array(
	'tunnel' => gettext('Tunnel IPv4'),
	'tunnel6' => gettext('Tunnel IPv6'),
	'transport' => gettext('Transport'),
	'vti' => gettext('Routed (VTI)')
);

global $p2_protos;
$p2_protos = array(
	'esp' => 'ESP',
	'ah' => 'AH'
);

global $p2_pfskeygroups;
$p2_pfskeygroups = array(
	0 => gettext('off'),
	1  => gettext('1 (768 bit)'),
	2  => gettext('2 (1024 bit)'),
	5  => gettext('5 (1536 bit)'),
	14 => gettext('14 (2048 bit)'),
	15 => gettext('15 (3072 bit)'),
	16 => gettext('16 (4096 bit)'),
	17 => gettext('17 (6144 bit)'),
	18 => gettext('18 (8192 bit)'),
	19 => gettext('19 (nist ecp256)'),
	20 => gettext('20 (nist ecp384)'),
	21 => gettext('21 (nist ecp521)'),
	22 => gettext('22 (1024(sub 160) bit)'),
	23 => gettext('23 (2048(sub 224) bit)'),
	24 => gettext('24 (2048(sub 256) bit)'),
	25 => gettext('25 (nist ecp192)'),
	26 => gettext('26 (nist ecp224)'),
	27 => gettext('27 (brainpool ecp224)'),
	28 => gettext('28 (brainpool ecp256)'),
	29 => gettext('29 (brainpool ecp384)'),
	30 => gettext('30 (brainpool ecp512)'),
	31 => gettext('31 (Elliptic Curve 25519, 256 bit)'),
	32 => gettext('32 (Elliptic Curve 25519, 448 bit)'),
);

function ipsec_enabled() {
	global $config;

	if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
		return false;
	}

	/* Check if we have at least one phase 1 entry. */
	if (!isset($config['ipsec']['phase1']) ||
	    !is_array($config['ipsec']['phase1']) ||
	    empty($config['ipsec']['phase1'])) {
		return false;
	}
	/* Check if at least one phase 1 entry is enabled. */
	foreach ($config['ipsec']['phase1'] as $phase1) {
		if (!isset($phase1['disabled'])) {
			return true;
		}
	}

	return false;
}

/*
 * ikeid management functions
 */

function ipsec_ikeid_used($ikeid) {
	global $config;

	foreach ($config['ipsec']['phase1'] as $ph1ent) {
		if ($ikeid == $ph1ent['ikeid']) {
			return true;
		}
	}

	return false;
}

function ipsec_ikeid_next() {

	$ikeid = 1;
	while (ipsec_ikeid_used($ikeid)) {
		$ikeid++;
	}

	return $ikeid;
}

/*
 * Return phase1 local address
 */
function ipsec_get_phase1_src(& $ph1ent) {

	if ($ph1ent['interface']) {
		if (substr($ph1ent['interface'], 0, 4) == "_vip") {
			$if = $ph1ent['interface'];
		} else {
			$if = get_failover_interface($ph1ent['interface']);
		}
	} else {
		$if = "wan";
	}
	$ip6 = get_interface_ipv6($if);
	$ip4 = get_interface_ip($if);
	if ($ph1ent['protocol'] == "inet6") {
		$interfaceip = $ip6;
	} elseif ($ph1ent['protocol'] == "inet") {
		$interfaceip = $ip4;
	} elseif ($ph1ent['protocol'] == "both") {
		$ifips = array();
		if (!empty($ip4)) {
			$ifips[] = $ip4;
		}
		if (!empty($ip6)) {
			$ifips[] = $ip6;
		}
		$interfaceip = implode(',', $ifips);
	}

	return $interfaceip;
}

/*
 * Return phase1 local address
 */
function ipsec_get_phase1_dst(& $ph1ent) {
	global $g;

	if (empty($ph1ent['remote-gateway'])) {
		return false;
	}
	$rg = $ph1ent['remote-gateway'];
	if (!is_ipaddr($rg)) {
		if (!platform_booting()) {
			return resolve_retry($rg);
		}
	}
	if (!is_ipaddr($rg)) {
		return false;
	}

	return $rg;
}

/*
 * Return phase2 idinfo in cidr format
 */
function ipsec_idinfo_to_cidr(& $idinfo, $addrbits = false, $mode = "") {
	global $config;

	switch ($idinfo['type']) {
		case "address":
			if ($addrbits) {
				if ($mode == "tunnel6") {
					return $idinfo['address']."/128";
				} else {
					return $idinfo['address']."/32";
				}
			} else {
				return $idinfo['address'];
			}
			break; /* NOTREACHED */
		case "network":
			return "{$idinfo['address']}/{$idinfo['netbits']}";
			break; /* NOTREACHED */
		case "none":
		case "mobile":
			return '0.0.0.0/0';
			break; /* NOTREACHED */
		default:
			if (empty($mode) && !empty($idinfo['mode'])) {
				$mode = $idinfo['mode'];
			}

			if ($mode == "tunnel6") {
				$address = get_interface_ipv6($idinfo['type']);
				$netbits = get_interface_subnetv6($idinfo['type']);
				$address = gen_subnetv6($address, $netbits);
				return "{$address}/{$netbits}";
			} else {
				$address = get_interface_ip($idinfo['type']);
				$netbits = get_interface_subnet($idinfo['type']);
				$address = gen_subnet($address, $netbits);
				return "{$address}/{$netbits}";
			}
			break; /* NOTREACHED */
	}
}

/*
 * Return phase2 idinfo in address/netmask format
 */
function ipsec_idinfo_to_subnet(& $idinfo, $addrbits = false) {
	global $config;

	switch ($idinfo['type']) {
		case "address":
			if ($addrbits) {
				if ($idinfo['mode'] == "tunnel6") {
					return $idinfo['address']."/128";
				} else {
					return $idinfo['address']."/255.255.255.255";
				}
			} else {
				return $idinfo['address'];
			}
			break; /* NOTREACHED */
		case "none":
		case "network":
			return $idinfo['address']."/".gen_subnet_mask($idinfo['netbits']);
			break; /* NOTREACHED */
		case "mobile":
			return "0.0.0.0/0";
			break; /* NOTREACHED */
		default:
			if ($idinfo['mode'] == "tunnel6") {
				$address = get_interface_ipv6($idinfo['type']);
				$netbits = get_interface_subnetv6($idinfo['type']);
				$address = gen_subnetv6($address, $netbits);
				return $address."/".$netbits;
			} else {
				$address = get_interface_ip($idinfo['type']);
				$netbits = get_interface_subnet($idinfo['type']);
				$address = gen_subnet($address, $netbits);
				return $address."/".$netbits;
			}
			break; /* NOTREACHED */
	}
}

/*
 *  Return phase2 idinfo in text format
 */
function ipsec_idinfo_to_text(& $idinfo) {
	global $config;

	switch ($idinfo['type']) {
		case "address":
			return $idinfo['address'];
			break; /* NOTREACHED */
		case "network":
			return $idinfo['address']."/".$idinfo['netbits'];
			break; /* NOTREACHED */
		case "mobile":
			return gettext("Mobile Client");
			break; /* NOTREACHED */
		case "none":
			return gettext("None");
			break; /* NOTREACHED */
		default:
			if (!empty($config['interfaces'][$idinfo['type']])) {
				return convert_friendly_interface_to_friendly_descr($idinfo['type']);
			} else {
				return strtoupper($idinfo['type']);
			}
			break; /* NOTREACHED */
	}
}

/*
 * Return phase1 association for phase2
 */
function ipsec_lookup_phase1(& $ph2ent, & $ph1ent) {
	global $config;

	if (!is_array($config['ipsec'])) {
		return false;
	}
	if (!is_array($config['ipsec']['phase1'])) {
		return false;
	}
	if (empty($config['ipsec']['phase1'])) {
		return false;
	}

	foreach ($config['ipsec']['phase1'] as $ph1tmp) {
		if ($ph1tmp['ikeid'] == $ph2ent['ikeid']) {
			$ph1ent = $ph1tmp;
			return $ph1ent;
		}
	}

	return false;
}

/*
 * Check phase1 communications status
 */
function ipsec_phase1_status(&$ipsec_status, $ikeid) {

	foreach ($ipsec_status as $ike) {
		if ($ike['id'] == $ikeid) {
			if ($ike['status'] == 'established') {
				return true;
			}
		}
	}

	return false;
}

/*
 * Check phase2 communications status
 */
function ipsec_phase2_status(&$ipsec_status, &$phase2) {

	if (ipsec_lookup_phase1($ph2ent, $ph1ent)) {
		return ipsec_phase1_status($ipsec_status, $ph1ent['ikeid']);
	}

	return false;
}

/*
 * Wrapper to call pfSense_ipsec_list_sa() when IPsec is enabled
 */
function ipsec_list_sa() {

	if (ipsec_enabled()) {
		return pfSense_ipsec_list_sa();
	}

	return array();
}

/*
 * Return dump of SPD table
 */
function ipsec_dump_spd() {
	$fd = @popen("/sbin/setkey -DP", "r");
	$spd = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line) {
				continue;
			}
			if ($line == "No SPD entries.") {
				break;
			}
			if ($line[0] != "\t") {
				if (is_array($cursp)) {
					$spd[] = $cursp;
				}
				$cursp = array();
				$linea = explode(" ", $line);
				$cursp['srcid'] = substr($linea[0], 0, strpos($linea[0], "["));
				$cursp['dstid'] = substr($linea[1], 0, strpos($linea[1], "["));
				$i = 0;
			} else if (is_array($cursp)) {
				$line = trim($line, "\t\r\n ");
				$linea = explode(" ", $line);
				switch ($i) {
					case 1:
						if ($linea[1] == "none")	/* don't show default anti-lockout rule */ {
							unset($cursp);
						} else {
							$cursp['dir'] = $linea[0];
						}
						break;
					case 2:
						$upperspec = explode("/", $linea[0]);
						$cursp['proto'] = $upperspec[0];
						list($cursp['src'], $cursp['dst']) = explode("-", $upperspec[2]);
						$cursp['reqid'] = substr($upperspec[3], strpos($upperspec[3], "#")+1);
						break;
				}
			}
			$i++;
		}
		if (is_array($cursp) && count($cursp)) {
			$spd[] = $cursp;
		}
		pclose($fd);
	}

	return $spd;
}

/*
 * Return dump of SAD table
 */
function ipsec_dump_sad() {
	$fd = @popen("/sbin/setkey -D", "r");
	$sad = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line || $line[0] == " ") {
				continue;
			}
			if ($line == "No SAD entries.") {
				break;
			}
			if ($line[0] != "\t") {
				if (is_array($cursa)) {
					$sad[] = $cursa;
				}
				$cursa = array();
				list($cursa['src'], $cursa['dst']) = explode(" ", $line);
			} else {
				$line = trim($line, "\t\n\r ");
				$linea = explode(" ", $line);
				foreach ($linea as $idx => $linee) {
					if ($linee == 'esp' || $linee == 'ah' || $linee[0] == '#') {
						$cursa['proto'] = $linee;
					} else if (substr($linee, 0, 3) == 'spi') {
						$cursa['spi'] = substr($linee, strpos($linee, 'x') + 1, -1);
					} else if (substr($linee, 0, 5) == 'reqid') {
						$cursa['reqid'] = substr($linee, strpos($linee, 'x') + 1, -1);
					} else if (substr($linee, 0, 2) == 'E:') {
						$cursa['ealgo'] = $linea[$idx + 1];
						break;
					} else if (substr($linee, 0, 2) == 'A:') {
						$cursa['aalgo'] = $linea[$idx + 1];
						break;
					} else if (substr($linee, 0, 8) == 'current:') {
						$cursa['data'] = substr($linea[$idx + 1], 0, strpos($linea[$idx + 1], 'bytes') - 1) . ' B';
						break;
					}
				}
			}
		}
		if (is_array($cursa) && count($cursa)) {
			$sad[] = $cursa;
		}
		pclose($fd);
	}

	return $sad;
}

/*
 * Return dump of mobile user list
 */
function ipsec_dump_mobile() {
	global $g, $config;

	if(!isset($config['ipsec']['client']['enable'])) {
		return array();
	}

	$_gb = exec("/usr/local/sbin/ipsec leases 2>/dev/null", $output, $rc);

	if ($rc != 0) {
		log_error(gettext("Unable to find IPsec daemon leases file. Could not display mobile user stats!"));
		return array();
	}

	$response = array();
	$id = -1;

	/* Leases in pool '10.7.200.0/24', usage: 1/254, 1 online */
	$lease_regex='/^Leases *in *pool *\'(?P<name>.+)\', *usage: *(?P<usage>\d+)\/(?P<size>\d+), *(?P<online>\d+) *online/';
	/* 10.7.200.1   online   'jimp' */
	$pool_regex='/\s*(?P<host>[\d\.]+)\s+(?P<status>online|offline)\s+\'(?P<id>.*)\'/';
	/* no matching leases found */
	$nopool_regex='/no *matching *leases *found/';

	$lease=false;
	foreach ($output as $line) {
		if (preg_match($lease_regex, $line, $matches)) {
			$id++;
			$response['pool'][$id] = array(
			    'name'   => $matches['name'],
			    'usage'  => $matches['usage'],
			    'size'   => $matches['size'],
			    'online' => $matches['online'],
			);
			$lease=true;
		} else if ($lease) {
			if (preg_match($nopool_regex, $line)) {
				$response['pool'][$id]['lease'][] = array();
				$lease=false;
			} else if (preg_match($pool_regex, $line, $matches)) {
				$response['pool'][$id]['lease'][] = array(
				    'host'   => $matches['host'],
				    'status' => $matches['status'],
				    'id'     => $matches['id']
				);
			}
		}
	}

	unset($_gb, $lease, $output, $rc, $id, $lease_regex, $pool_regex,
	    $nopool_regex);

	return $response;
}

function ipsec_mobilekey_sort() {
	global $config;

	function mobilekeycmp($a, $b) {
		return strcmp($a['ident'][0], $b['ident'][0]);
	}

	usort($config['ipsec']['mobilekey'], "mobilekeycmp");
}

function ipsec_get_number_of_phase2($ikeid) {
	global $config;
	$a_phase2 = $config['ipsec']['phase2'];

	$nbph2 = 0;

	if (is_array($a_phase2) && count($a_phase2)) {
		foreach ($a_phase2 as $ph2tmp) {
			if ($ph2tmp['ikeid'] == $ikeid) {
				$nbph2++;
			}
		}
	}

	return $nbph2;
}

function ipsec_get_descr($ikeid) {
	global $config;

	if (!isset($config['ipsec']['phase1']) ||
	    !is_array($config['ipsec']['phase1'])) {
		return '';
	}

	foreach ($config['ipsec']['phase1'] as $p1) {
		if ($p1['ikeid'] == $ikeid) {
			return $p1['descr'];
		}
	}

	return '';
}

function ipsec_get_phase1($ikeid) {
		global $config;

		if (!isset($config['ipsec']['phase1']) ||
		    !is_array($config['ipsec']['phase1'])) {
			return '';
		}

		$a_phase1 = $config['ipsec']['phase1'];
		foreach ($a_phase1 as $p1) {
			if ($p1['ikeid'] == $ikeid) {
				return $p1;
			}
		}
		unset($a_phase1);
}

function ipsec_fixup_ip($ipaddr) {
	if (is_ipaddrv6($ipaddr) || is_subnetv6($ipaddr)) {
		return text_to_compressed_ip6($ipaddr);
	} else {
		return $ipaddr;
	}
}

function ipsec_find_id(& $ph1ent, $side = "local", $rgmap = array()) {
	if ($side == "local") {
		$id_type = $ph1ent['myid_type'];
		$id_data = $ph1ent['myid_data'];

		$addr = ipsec_get_phase1_src($ph1ent);
		if (!$addr) {
			return array();
		}
		/* When automatically guessing, use the first address. */
		$addr = explode(',', $addr);
		$addr = $addr[0];
	} elseif ($side == "peer") {
		$id_type = $ph1ent['peerid_type'];
		$id_data = $ph1ent['peerid_data'];

		if (isset($ph1ent['mobile'])) {
			$addr = "%any";
		} else {
			$addr = $ph1ent['remote-gateway'];
		}
	} else {
		return array();
	}


	$thisid_type = $id_type;
	switch ($thisid_type) {
		case 'myaddress':
			$thisid_type = 'address';
			$thisid_data = $addr;
			break;
		case 'dyn_dns':
			$thisid_type = 'dns';
			$thisid_data = $id_data;
			break;
		case 'peeraddress':
			$thisid_type = 'address';
			$thisid_data = $rgmap[$ph1ent['remote-gateway']];
			break;
		case 'address':
			$thisid_data = $id_data;
			break;
		case 'fqdn':
			$thisid_data = "{$id_data}";
			break;
		case 'keyid tag':
			$thisid_type = 'keyid';
			$thisid_data = "{$id_data}";
			break;
		case 'user_fqdn':
			$thisid_type = 'userfqdn';
			$thisid_data = "{$id_data}";
			break;
		case 'asn1dn':
			$thisid_data = $id_data;
			break;
	}
	return array($thisid_type, $thisid_data);
}

/*
 * Fixup ID type/data to include prefix when necessary, add quotes, etc.
 */
function ipsec_fixup_id($type, $data) {
	/* List of types to pass through as-is without changes or adding prefix */
	$auto_types = array('address');
	/* List of types which need the resulting string double quoted. */
	$quote_types = array('keyid', 'asn1dn');

	/* If the first character of asn1dn type data is not #, then rely on
	 * automatic parsing https://redmine.pfsense.org/issues/4792 */
	if (($type == 'asn1dn') && !empty($data) && ($data[0] != '#')) {
		$auto_types[] = 'asn1dn';
	}

	if ($type == 'any') {
		$idstring = "";
	} elseif (!in_array($type, $auto_types)) {
		$idstring = "{$type}:{$data}";
	} else {
		$idstring = $data;
	}

	if (in_array($type, $quote_types)) {
		$idstring = "\"{$idstring}\"";
	}

	return $idstring;
}

function ipsec_fixup_network($network) {
	if (substr($network, -3) == '|/0') {
		$result = substr($network, 0, -3);
	} else {
		$tmp = explode('|', $network);
		if (isset($tmp[1])) {
			$result = $tmp[1];
		} else {
			$result = $tmp[0];
		}
		unset($tmp);
	}

	return $result;
}

function ipsec_new_reqid() {
	global $config;

	if (!is_array($config['ipsec']) || !is_array($config['ipsec']['phase2'])) {
		return;
	}

	$ipsecreqid = lock('ipsecreqids', LOCK_EX);
	$keyids = array();
	$keyid = 1;
	foreach ($config['ipsec']['phase2'] as $ph2) {
		$keyids[$ph2['reqid']] = $ph2['reqid'];
	}

	for ($i = 1; $i < 16000; $i++) {
		if (!isset($keyids[$i])) {
			$keyid = $i;
			break;
		}
	}
	unlock($ipsecreqid);

	return $keyid;
}

function ipsec_get_loglevels() {
	global $config, $ipsec_log_cats;
	$def_loglevel = '1';

	$levels = array();

	foreach (array_keys($ipsec_log_cats) as $cat) {
		if (isset($config['ipsec']['logging'][$cat])) {
			$levels[$cat] = $config['ipsec']['logging'][$cat];
		} elseif (in_array($cat, array('ike', 'chd', 'cfg'))) {
			$levels[$cat] = "2";
		} else {
			$levels[$cat] = $def_loglevel;
		}
	}
	return $levels;
}

function ipsec_vti($ph1ent, $returnaddresses = false) {
	global $config;
	if (empty($ph1ent) || !is_array($ph1ent) || !is_array($config['ipsec']['phase2'])) {
		return false;
	}

	$is_vti = false;
	$vtisubnet_spec = array();

	foreach ($config['ipsec']['phase2'] as $ph2ent) {
		if ($ph1ent['ikeid'] != $ph2ent['ikeid']) {
			continue;
		}
		if ($ph2ent['mode'] == 'vti') {
			if ($returnaddresses) {
				$vtisubnet_spec[] = array(
					'left' => ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']),
					'right' => ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']),
					'descr' => $ph2ent['descr'],
				);
			}
			$is_vti = true;
		}
	}
	return ($returnaddresses) ? $vtisubnet_spec : $is_vti;
}

/* Called when IPsec is reloaded through rc.newipsecdns and similar, gives
 * packages an opportunity to execute custom code when IPsec reloads.
 */
function ipsec_reload_package_hook() {
	global $g, $config;

	init_config_arr(array('installedpackages', 'package'));
	foreach ($config['installedpackages']['package'] as $package) {
		if (!file_exists("/usr/local/pkg/" . $package['configurationfile'])) {
			continue;
		}

		$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], 'packagegui');
		if (!empty($pkg_config['include_file']) &&
		    file_exists($pkg_config['include_file'])) {
			require_once($pkg_config['include_file']);
		}
		if (empty($pkg_config['ipsec_reload_function'])) {
			continue;
		}
		$pkg_ipsec_reload = $pkg_config['ipsec_reload_function'];
		if (!function_exists($pkg_ipsec_reload)) {
			continue;
		}
		$pkg_ipsec_reload();
	}
}

?>
