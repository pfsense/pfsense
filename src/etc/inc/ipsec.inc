<?php
/*
 * ipsec.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Shrew Soft Inc.
 * Copyright (c) 2007-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
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

require_once("filter.inc");
require_once("auth.inc");
require_once("certs.inc");
require_once("interfaces.inc");

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
	'replace' => 'Yes (Replace)', 'no' => 'No', 'never' => 'Never', 'keep' => 'Keep'
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
	'hybrid_cert_server' => array('name' => gettext('Hybrid Certificate + Xauth'), 'mobile' => true),
	'xauth_cert_server' => array('name' => gettext('Mutual Certificate + Xauth'), 'mobile' => true),
	'xauth_psk_server' => array('name' => gettext('Mutual PSK + Xauth'), 'mobile' => true),
	'eap-tls' => array('name' => gettext('EAP-TLS'), 'mobile' => true),
	'eap-radius' => array('name' => gettext('EAP-RADIUS'), 'mobile' => true),
	'eap-mschapv2' => array('name' => gettext('EAP-MSChapv2'), 'mobile' => true),
	'cert' => array('name' => gettext('Mutual Certificate'), 'mobile' => false),
	'pkcs11' => array('name' => gettext('Mutual Certificate (PKCS#11)'), 'mobile' => false),
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
	'none' => gettext('Close connection and clear SA'),
	'start' => gettext('Restart/Reconnect'),
	'trap' => gettext('Close connection and reconnect on demand'),
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
			return resolve_retry($rg, $ph1ent['protocol']);
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
				} elseif (($mode == "vti") && is_ipaddrv4($idinfo['address'])) {
					return $idinfo['address']."/30";
				} elseif (($mode == "vti") && is_ipaddrv6($idinfo['address'])) {
					return $idinfo['address']."/64";
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

	/* Fetch the pool contents and leases from swanctl. */
	$_gb = exec("/usr/local/sbin/swanctl --list-pools --leases 2>/dev/null", $output, $rc);

	if ($rc != 0) {
		log_error(gettext("Unable to find IPsec daemon leases file. Could not display mobile user stats!"));
		return array();
	}

	$response = array(
		'pool' => array(),
	);

	/* swanctl --list-pools --leases example output, field names can be confirmed by
	 * looking at --raw or --pretty output. */
	/* mobile-pool          10.7.200.0                          0 / 1 / 254 */
	$pool_regex='/^(?P<name>\S+)\s+(?P<base>\S+)\s+(?P<online>\d+) \/ (?P<offline>\d+) \/ (?P<size>\d+)/';
	/*   10.7.200.1                     online   'jimp' */
	$lease_regex='/\s*(?P<host>[\d\.]+)\s+(?P<status>online|offline)\s+\'(?P<id>.*)\'/';
	foreach ($output as $line) {
		if (preg_match($pool_regex, $line, $matches)) {
			$id++;
			$response['pool'][$id] = array(
			    'name'   => $matches['name'],
			    'base'   => $matches['base'],
			    'online' => $matches['online'],
			    'offline'  => $matches['offline'],
			    'size'   => $matches['size'],
			);
		} elseif (preg_match($lease_regex, $line, $matches)) {
			$response['pool'][$id]['lease'][] = array(
			    'host'   => $matches['host'],
			    'status' => $matches['status'],
			    'id'     => $matches['id']
			);
		}
	}

	unset($_gb, $output, $rc, $pool_regex);

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

function ipsec_vti($ph1ent, $returnaddresses = false, $skipdisabled = true) {
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
			if (!$skipdisabled && isset($ph2ent['disabled'])) {
				continue;
			} else {
				$is_vti = true;
			}
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

/****f* certs/ipsec_convert_to_modp
 * NAME
 *   ipsec_convert_to_modp - Take a DH Group number value and return the
 *                           associated name
 * INPUTS
 *   $index: DH group index number to look up
 * RESULT
 *   Returns the Diffie Hellman Group keyword associated with the group number.
 ******/
function ipsec_convert_to_modp($index) {
	$modpmap = array(
		'1' => 'modp768',
		'2' => 'modp1024',
		'5' => 'modp1536',
		'14' => 'modp2048',
		'15' => 'modp3072',
		'16' => 'modp4096',
		'17' => 'modp6144',
		'18' => 'modp8192',
		'19' => 'ecp256',
		'20' => 'ecp384',
		'21' => 'ecp521',
		'22' => 'modp1024s160',
		'23' => 'modp2048s224',
		'24' => 'modp2048s256',
		'25' => 'ecp192',
		'26' => 'ecp224',
		'27' => 'ecp224bp',
		'28' => 'ecp256bp',
		'29' => 'ecp384bp',
		'30' => 'ecp512bp',
		'31' => 'curve25519',
		'32' => 'curve448',
	);

	return $modpmap[$index];
}

/*
 * Forcefully restart IPsec
 * This is required for when dynamic interfaces reload
 * For all other occasions the normal ipsec_configure()
 * will gracefully reload the settings without restarting
 */
function ipsec_force_reload($interface = "") {
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
	ipsec_configure();
}

/****f* ipsec/ipsec_strongswan_confgen
 * NAME
 *   ipsec_strongswan_confgen - Generate strongswan.conf style formatted output
 *                              From a multi-dimensional array.
 * INPUTS
 *   $confarr: An array of key=value pairs or key=array entries for subsections.
 *   $indent : A string of tabs used when indenting lines.
 * RESULT
 *   Returns a string of key=value pairs with proper indenting, and with named
 *   subsections enclosed in braces.
 * NOTES
 *   Values starting with a "#" (comments) or "include " will be printed
 *   directly without their associated key name.
 ******/
function ipsec_strongswan_confgen($confarr, $indent = "") {
	$confstr = "";
	/* Only process the contents if it's an array. */
	if (is_array($confarr)) {
		foreach ($confarr as $key => $value) {
			if (is_array($value)) {
				/* If the array is empty, do not print anything */
				if (empty($value)) {
					continue;
				}
				/* If this is an array, setup the subsection name
				 * and structure, then call this function
				 * recursively to walk the lower array. */
				$confstr .= "{$indent}{$key} {\n";
				$confstr .= ipsec_strongswan_confgen($value, $indent . "\t");
				$confstr .= "{$indent}}\n";
			} else {
				$confstr .= "{$indent}";
				if ((substr($value, 0 , 1) != '#') &&
				    (substr($value, 0 , 8) != 'include ')) {
					/* Not a comment or include, print the key */
					$confstr .= "{$key} = ";
				}
				$confstr .= "{$value}\n";
			}
		}
	}
	return $confstr;
}

global $g, $ipsec_swanctl_basedir, $ipsec_swanctl_dirs;
$ipsec_swanctl_basedir = "{$g['varetc_path']}/ipsec";
$ipsec_swanctl_dirs = array(
	'conf'     => "{$ipsec_swanctl_basedir}/conf.d",
	'certpath' => "{$ipsec_swanctl_basedir}/x509",
	'capath'   => "{$ipsec_swanctl_basedir}/x509ca",
	'aapath'   => "{$ipsec_swanctl_basedir}/x509aa",
	'ocsppath' => "{$ipsec_swanctl_basedir}/x509ocsp",
	'crlpath'  => "{$ipsec_swanctl_basedir}/x509crl",
	'acpath'   => "{$ipsec_swanctl_basedir}/x509ac",
	'rsakeys'  => "{$ipsec_swanctl_basedir}/rsa",
	'eckeys'   => "{$ipsec_swanctl_basedir}/ecdsa",
	'keypath'  => "{$ipsec_swanctl_basedir}/private",
	'pubkpath' => "{$ipsec_swanctl_basedir}/pubkey",
	'blisspath' => "{$ipsec_swanctl_basedir}/bliss",
	'pkcs8path' => "{$ipsec_swanctl_basedir}/pkcs8",
	'pkcs12path' => "{$ipsec_swanctl_basedir}/pkcs12",
);

/****f* ipsec/ipsec_create_dirs
 * NAME
 *   ipsec_create_dirs - Create default set of strongSwan configuration directories.
 * INPUTS
 *   None
 * RESULT
 *   Cleans up and creates strongSwan configuration directories and links.
 ******/
function ipsec_create_dirs() {
	global $config, $g, $ipsec_swanctl_basedir, $ipsec_swanctl_dirs;

	/* Cleanup base paths to ensure old files are not left behind (#5238) */
	if (!empty($ipsec_swanctl_basedir)) {
		@rmdir_recursive($ipsec_swanctl_basedir, false);
	}
	/* Create directory structure */
	array_map('safe_mkdir', array_values($ipsec_swanctl_dirs));

	/* Create and link strongSwan config */
	$ssdpath = "{$g['varetc_path']}/ipsec/strongswan.d";
	if (!file_exists($ssdpath) || !is_link($ssdpath)) {
		if (is_dir($ssdpath) && !is_link($ssdpath)) {
			@rmdir_recursive($ssdpath, false);
		} else {
			@unlink($ssdpath);
		}
		@symlink("/usr/local/etc/strongswan.d",
		    $ssdpath);
	}
	if (!file_exists("/usr/local/etc/strongswan.conf") ||
	    !is_link("/usr/local/etc/strongswan.conf")) {
		@unlink("/usr/local/etc/strongswan.conf");
		@symlink("{$g['varetc_path']}/ipsec/strongswan.conf",
		    "/usr/local/etc/strongswan.conf");
	}
}

/****f* ipsec/ipsec_setup_gwifs
 * NAME
 *   ipsec_setup_gwifs - Setup IPsec-related interfaces and gateways
 * INPUTS
 *   None
 * RESULT
 *   Sets up VTI interfaces and gateways, populates ipsecpinghosts
 ******/
function ipsec_setup_gwifs() {
	global $g, $a_phase1, $a_phase2, $rgmap, $filterdns_list,
		$aggressive_mode_psk, $mobile_ipsec_auth, $ifacesuse,
		$ipsecpinghostsactive;
	/* resolve all local, peer addresses and setup pings */
	unset($iflist);
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

			$local_spec = ipsec_get_phase1_src($ph1ent);
			/* When automatically guessing, use the first address. */
			$local_spec  = explode(',', $local_spec);
			$local_spec  = $local_spec[0];
			if (!is_ipaddr($local_spec)) {
				log_error(sprintf(gettext("IPsec ERROR: Could not find phase 1 source for connection %s. Omitting from configuration file."), $ph1ent['descr']));
				continue;
			}

			if (isset($ph1ent['mobile'])) {
				$mobile_ipsec_auth = $ph1ent['authentication_method'];
				continue;
			}

			/* see if this tunnel has a hostname for the remote-gateway. If so,
			   try to resolve it now and add it to the list for filterdns */
			$rg = $ph1ent['remote-gateway'];
			if (!is_ipaddr($rg)) {
				$filterdns_list[] = "{$rg}";
				add_hostname_to_watch($rg);
				if (!platform_booting()) {
					$rg = resolve_retry($rg, $ph1ent['protocol']);
				}
				if (!is_ipaddr($rg)) {
					continue;
				}
			}
			if (!isset($ph1ent['gw_duplicates']) && array_search($rg, $rgmap)) {
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
					if (isset($ph2ent['disabled']) || ($ikeid != $ph2ent['ikeid'])) {
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
						$family = "inet" . (is_ipaddrv6($dstip)) ? "6" : "";
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
}

/****f* ipsec/ipsec_logging_config
 * NAME
 *   ipsec_logging_config - Generate an array containing strongSwan logging
 *                          values.
 * INPUTS
 *   None
 * RESULT
 *   Returns an array which can be merged into the strongswan daemon logging
 *   values so that each logging category is represented, even those not listed
 *   in config.xml.
 ******/
function ipsec_logging_config() {
	global $config, $ipsec_log_cats, $ipsec_log_sevs;
	$logcfg = array();
	$log_levels = ipsec_get_loglevels();
	foreach (array_keys($ipsec_log_cats) as $cat) {
		if (is_numeric($log_levels[$cat]) &&
		    in_array(intval($log_levels[$cat]), array_keys($ipsec_log_sevs), true)) {
			$logcfg[$cat] = $log_levels[$cat];
		}
	}
	return $logcfg;
}

/****f* ipsec/ipsec_setup_strongswan
 * NAME
 *   ipsec_setup_strongswan - Generate the strongswan.conf configuration file.
 * INPUTS
 *   None
 * RESULT
 *   Determines strongswan.conf values and crafts the configuration file in the
 *   appropriate format.
 * NOTES
 *   Called from ipsec_configure()
 ******/
function ipsec_setup_strongswan() {
	global $config, $g, $mobile_ipsec_auth, $a_client, $a_phase2, $aggressive_mode_psk, $ifacesuse;

	/* strongswan.conf array */
	$ssconf = array();
	$ssconf[] = "# Automatically generated config file - DO NOT MODIFY. Changes will be overwritten.";
	$ssconf['starter'] = array();
	$ssconf['starter']['load_warning'] = "no";

	$ssconf['charon'] = array();
	$ssconf['charon'][] = '# number of worker threads in charon';
	$ssconf['charon']['threads'] = '16';
	$ssconf['charon']['ikesa_table_size'] = '32';
	$ssconf['charon']['ikesa_table_segments'] = '4';
	$ssconf['charon']['init_limit_half_open'] = '1000';
	$ssconf['charon']['install_routes'] = 'no';
	$ssconf['charon']['load_modular'] = 'yes';
	$ssconf['charon']['ignore_acquire_ts'] = 'yes';

	if ($aggressive_mode_psk) {
		$stronconf = '';
		if (file_exists("{$g['varetc_path']}/ipsec/strongswan.conf")) {
			$stronconf = file_get_contents("{$g['varetc_path']}/ipsec/strongswan.conf");
		}
		log_error("WARNING: Setting i_dont_care_about_security_and_use_aggressive_mode_psk option because a phase 1 is configured using aggressive mode with pre-shared keys. This is not a secure configuration.");
		$restart = (!empty($stronconf) && strpos($stronconf, 'i_dont_care_about_security_and_use_aggressive_mode_psk') === FALSE);
		unset($stronconf);
		$ssconf['charon']['i_dont_care_about_security_and_use_aggressive_mode_psk'] = "yes";
	}

	if (isset($config['ipsec']['acceptunencryptedmainmode'])) {
		$ssconf['charon']['accept_unencrypted_mainmode_messages'] = "yes";
	}

	if (isset($config['ipsec']['maxexchange'])) {
		$ssconf['charon']['max_ikev1_exchanges'] = $config['ipsec']['maxexchange'];
	}

	$unity_enabled = isset($config['ipsec']['unityplugin']) ? 'yes' : 'no';
	$ssconf['charon']['cisco_unity'] = $unity_enabled;

	if (isset($config['ipsec']['enableinterfacesuse']) && !empty($ifacesuse)) {
		$ssconf['charon']['interfaces_use'] = implode(',', array_unique($ifacesuse));
	}

	if (isset($config['ipsec']['makebeforebreak'])) {
		$ssconf['charon']['make_before_break'] = "yes";
	}

	if (isset($config['ipsec']['port'])) {
		$ssconf['charon']['port'] = $config['ipsec']['port'];
	}

	if (isset($config['ipsec']['port_nat_t'])) {
		$ssconf['charon']['port_nat_t'] = $config['ipsec']['port_nat_t'];
	}

	$ssconf['charon']['syslog'] = array();
	$ssconf['charon']['syslog']['identifier'] = 'charon';
	$ssconf['charon']['syslog'][] = "# log everything under daemon since it ends up in the same place regardless with our syslog.conf";
	$ssconf['charon']['syslog']['daemon'] = array();
	$ssconf['charon']['syslog']['daemon']['ike_name'] = 'yes';
	$ssconf['charon']['syslog']['daemon'] = array_merge($ssconf['charon']['syslog']['daemon'], ipsec_logging_config());
	$ssconf['charon']['syslog'][] = '# disable logging under auth so logs aren\'t duplicated';
	$ssconf['charon']['syslog']['auth'] = array();
	$ssconf['charon']['syslog']['auth']['default'] = -1;

	$ssconf['charon']['plugins'] = array();
	$ssconf['charon']['plugins'][] = "# Load defaults";
	$ssconf['charon']['plugins'][] = "include {$g['varetc_path']}/ipsec/strongswan.d/charon/*.conf";
	$ssconf['charon']['plugins']['unity'] = array('load' => $unity_enabled);
	$ssconf['charon']['plugins']['curve25519'] = array('load' => "yes");

	$ssconf['charon']['plugins']['pkcs11'] = array();
	$ssconf['charon']['plugins']['pkcs11']['load'] = "yes";
	$ssconf['charon']['plugins']['pkcs11']['modules'] = array();
	$ssconf['charon']['plugins']['pkcs11']['modules']['opensc'] = array();
	$ssconf['charon']['plugins']['pkcs11']['modules']['opensc']['load_certs'] = "yes";
	$ssconf['charon']['plugins']['pkcs11']['modules']['opensc']['path'] = "/usr/local/lib/opensc-pkcs11.so";

	/* Find RADIUS servers designated for Mobile IPsec user auth */
	$radius_servers = array();
	foreach (explode(',', $config['ipsec']['client']['user_source']) as $user_source) {
		$auth_server = auth_get_authserver($user_source);
		$nice_user_source = strtolower(preg_replace('/[\s\.]+/', '_', $user_source));
		if ($auth_server && ($auth_server['type'] === 'radius')) {
			$radius_servers[$nice_user_source] = array();
			$radius_servers[$nice_user_source]['address'] = $auth_server['host'];
			$radius_servers[$nice_user_source]['secret'] = "\"{$auth_server['radius_secret']}\"";
			$radius_servers[$nice_user_source]['auth_port'] = empty($auth_server['radius_auth_port']) ? 1812 : $auth_server['radius_auth_port'];;
			$radius_servers[$nice_user_source]['acct_port'] = empty($auth_server['radius_acct_port']) ? 1813 : $auth_server['radius_acct_port'];
		}
	}

	/* Generate an eap-radius config section if appropriate */
	if (count($radius_servers) && ($mobile_ipsec_auth === "eap-radius")) {
		$ssconf['charon']['plugins']['eap-radius'] = array();
		$ssconf['charon']['plugins']['eap-radius']['load'] = "2";
		$ssconf['charon']['plugins']['eap-radius']['class_group'] = "yes";
		$ssconf['charon']['plugins']['eap-radius']['eap_start'] = "no";
		/* Activate RADIUS accounting only if it was selected on the IPsec Mobile Clients tab */
		if ($auth_server && isset($auth_server['radius_acct_port']) &&
		    (is_array($a_client) && ($a_client['radiusaccounting'] == "enabled"))){
			$ssconf['charon']['plugins']['eap-radius']['accounting'] = "yes";
			$ssconf['charon']['plugins']['eap-radius']['accounting_close_on_timeout'] = "no";
		}
		$ssconf['charon']['plugins']['eap-radius']['servers'] = $radius_servers;
	}

	if (is_array($a_client) && isset($a_client['enable'])) {
		if ($a_client['user_source'] != "none") {
			$ssconf['charon']['plugins']['xauth-generic'] = array();
			$ssconf['charon']['plugins']['xauth-generic']['script'] = "/etc/inc/ipsec.auth-user.php";
			$authcfgs = array();
			foreach (explode(",", $a_client['user_source']) as $authcfg) {
				$authcfgs[] = ($authcfg == "system") ? "Local Database" : $authcfg;
			}
			$ssconf['charon']['plugins']['xauth-generic']['authcfg'] = implode(",", $authcfgs);
		}
	}

	@file_put_contents("{$g['varetc_path']}/ipsec/strongswan.conf", ipsec_strongswan_confgen($ssconf));
}

/****f* ipsec/ipsec_setup_pools
 * NAME
 *   ipsec_setup_pools - Generate primary mobile pool configuration for swanctl
  * INPUTS
 *   None
 * RESULT
 *   Adds configured mobile pool settings to $scconf
 * NOTES
 *   These values were formerly in strongswan.conf but may now be configured in
 *   pools, making future expansion to support multiple pools possible.
 ******/
function ipsec_setup_pools() {
	global $config, $g, $mobile_ipsec_auth, $a_client, $a_phase2, $scconf;
	if (!is_array($a_client) || !isset($a_client['enable'])) {
		return;
	}
	if (($mobile_ipsec_auth == "eap-radius") && empty($a_client['pool_address']) &&
	    empty($a_client['pool_address_v6'])) {
		return;
	}
	$scconf['mobile-pool'] = array();
	$scconf['pools'] = array();
	$pool_common =& $scconf['mobile-pool'];

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
		$pool_common['dns'] = implode(',', $rightdnsservers);
	}

	$cfgservers = array();
	if (!empty($a_client['wins_server1'])) {
		$cfgservers[] = $a_client['wins_server1'];
	}
	if (!empty($a_client['wins_server2'])) {
		$cfgservers[] = $a_client['wins_server2'];
	}
	if (!empty($cfgservers)) {
		$pool_common['nbns'] = implode(",", $cfgservers);
	}
	unset($cfgservers);

	$net_list4 = array();
	$net_list6 = array();
	if (isset($a_client['net_list']) && is_array($a_phase2)) {
		foreach ($a_phase2 as $ph2ent) {
			if (isset($ph2ent['disabled']) ||
			    !isset($ph2ent['mobile'])) {
				continue;
			}
			$nlent = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
			if (is_subnetv4($nlent)) {
				$net_list4[] = $nlent;
			} elseif (is_subnetv6($nlent)) {
				$net_list6[] = $nlent;
			}
			unset($nlent);
		}
	}
	if (!empty($a_client['dns_domain'])) {
		$pool_common[] = "# Search domain and default domain";
		$pool_common['28674'] = "\"{$a_client['dns_domain']}\"";
		if (empty($a_client['dns_split'])) {
			$pool_common['28675'] = "\"{$a_client['dns_domain']}\"";
		}
	}

	if (!empty($a_client['dns_split'])) {
		$pool_common['28675'] = "\"{$a_client['dns_split']}\"";
	}

	if (!empty($a_client['login_banner'])) {
		$pool_common['28672'] = "\"{$a_client['login_banner']}\"";
	}

	if (isset($a_client['save_passwd'])) {
		$pool_common['28673'] = "1";
	}

	if ($a_client['pfs_group']) {
		$pool_common['28679'] = "\"{$a_client['pfs_group']}\"";
	}

	if (!empty($a_client['pool_address'])) {
		$scconf['pools']['mobile-pool-v4 : mobile-pool'] = array();
		$v4pool =& $scconf['pools']['mobile-pool-v4 : mobile-pool'];
		$v4pool['addrs'] = "{$a_client['pool_address']}/{$a_client['pool_netbits']}";
		if (!empty($net_list4)) {
			$v4pool['subnet'] = implode(",", $net_list4);
			$v4pool['split_include'] = implode(",", $net_list4);
			unset($net_list4);
		}
	}
	if (!empty($a_client['pool_address_v6'])) {
		$scconf['pools']['mobile-pool-v6 : mobile-pool'] = array();
		$v6pool =& $scconf['pools']['mobile-pool-v6 : mobile-pool'];
		$v6pool['addrs'] = "{$a_client['pool_address_v6']}/{$a_client['pool_netbits_v6']}";
		if (!empty($net_list6)) {
			$v6pool['subnet'] = implode(",", $net_list6);
			$v6pool['split_include'] = implode(",", $net_list6);
			unset($net_list6);
		}
	}

	return;
}

/****f* ipsec/ipsec_setup_userpools
 * NAME
 *   ipsec_setup_userpools - Generate per-user custom pool settings for swanctl
  * INPUTS
 *   None
 * RESULT
 *   Adds configured per-user pool settings to $scconf using the primary mobile
 *   pool as a base configuration.
 * NOTES
 *   Given this new flexible format, it is now possible to override any valid
 *   pool setting, so future expansion of per-user settings is possible.
 ******/
function ipsec_setup_userpools() {
	global $config, $scconf;
	$a_mobilekey = $config['ipsec']['mobilekey'];

	/* Do not waste time if we do not have all the necessary information. */
	if (!is_array($a_mobilekey) ||
	    empty($a_mobilekey) ||
	    !is_array($scconf['connections']) ||
	    !is_array($scconf['con-mobile-defaults']) ||
	    !is_array($scconf['pools']) ||
	    !is_array($scconf['mobile-pool'])) {
		return;
	}

	$suffix = 1;
	foreach ($a_mobilekey as $mkent) {
		if (($mkent['type'] != "EAP") ||
		    !isset($mkent['ident_type']) ||
		    !isset($mkent['pool_address']) ||
		    !isset($mkent['pool_netbits']) ||
		    (strlen($mkent['pool_address']) < 1) ||
		    !is_ipaddr($mkent['pool_address'])) {
			continue;
		}
		/* The name of just this pool */
		$upbase = "mobile-userpool-{$suffix}";
		/* The full connection name including a reference to the primary
		 * mobile connection */
		$upconn = "con-{$upbase} : con-mobile-defaults";
		/* The full pool name including a reference to the primary
		 * mobile pool */
		$upname = "{$upbase} : mobile-pool";

		$scconf['connections'][$upconn] = array();
		$scconf['pools'][$upname] = array();

		$clientid = ($mkent['ident_type'] == "none") ? "\"{$mkent['ident']}\"" : "{$mkent['ident_type']}:{$mkent['ident']}";
		$clienteapid = ($ph1ent['authentication_method'] == "eap-mschapv2") ? $clientid : '%any';

		/* Craft a cloned connection with the ID information to match */
		$scconf['connections'][$upconn]['remote'] = array();
		$scconf['connections'][$upconn]['remote']['id'] = $clientid;
		$scconf['connections'][$upconn]['remote']['eap_id'] = $clienteapid;
		$scconf['connections'][$upconn]['pools'] = $upbase;

		/* Craft a cloned pool with pool settings to override for this user */
		$scconf['pools'][$upname]['addrs'] = "{$mkent['pool_address']}/{$mkent['pool_netbits']}";
		if (isset($mkent['dns_address']) && strlen($mkent['dns_address']) > 0 && is_ipaddr($mkent['dns_address'])) {
			$scconf['pools'][$upname]['dns'] = $mkent['dns_address'];
		}
		$suffix++;
	}
	return;
}

/****f* ipsec/ipsec_setup_bypass
 * NAME
 *   ipsec_setup_bypass - Generate a bypass connection for LAN-LAN and custom rules traffic
 * INPUTS
 *   None
 * RESULT
 *   Sets up a bypass connection to prevent local traffic from being caught by
 *   IPsec policies.
 ******/
function ipsec_setup_bypass() {
	global $config, $scconf;

	$scconf['connections']['bypass'] = array();
	/* Prevents the connection from being considered for remote peers */
	$scconf['connections']['bypass']['remote_addrs'] = "127.0.0.1";
	$scconf['connections']['bypass']['children'] = array();

	/* Locate the LAN IPv4 and IPv6 subnets */
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
	/* If we have viable targets, setup a bypass LAN connection */
	if (!empty($bypassnets) && !isset($config['ipsec']['noshuntlaninterfaces'])) {
		$bypass = implode(',', $bypassnets);
		$scconf['connections']['bypass']['children']['bypasslan'] = array();
		$scconf['connections']['bypass']['children']['bypasslan']['local_ts'] = $bypass;
		$scconf['connections']['bypass']['children']['bypasslan']['remote_ts'] = $bypass;
		$scconf['connections']['bypass']['children']['bypasslan']['mode'] = "pass";
		$scconf['connections']['bypass']['children']['bypasslan']['start_action'] = "trap";
	}

	/* Setup custom bypass rules */
	if ((isset($config['ipsec']['ipsecbypass']) && $config['ipsec']['bypassrules']) &&
	    is_array($config['ipsec']['bypassrules'])) {
		foreach ($config['ipsec']['bypassrules']['rule'] as $id => $rule) {
			$scconf['connections']['bypass']['children']["rule{$id}"] = array();
			$scconf['connections']['bypass']['children']["rule{$id}"]["local_ts"] = $rule['source'] .
			       	"/" . $rule['srcmask'];
			$scconf['connections']['bypass']['children']["rule{$id}"]["remote_ts"] = $rule['destination'] .
			       	"/" . $rule['dstmask'];
			$scconf['connections']['bypass']['children']["rule{$id}"]["mode"] = "pass";
			$scconf['connections']['bypass']['children']["rule{$id}"]["start_action"] = "trap";
		}
	}

	return;
}

/****f* ipsec/ipsec_setup_routes
 * NAME
 *   ipsec_setup_routes - Setup OS routing table static routes for remote peers.
 *                        This ensures that IPsec connections are routed out of
 *                        the expected interface on egress.
 * INPUTS
 *   $interface : The interface upon which routes will be configured.
 *   $family    : The address family ('inet' or 'inet6')
 *   $sourcehost: The local source address used for initiating connections.
 *   $duplicates: If the ipsec tunnel allows duplicates remote peers
 * RESULT
 *   Static routes in the OS routing table for IPsec peers
 ******/
function ipsec_setup_routes($interface, $family, $sourcehost, $duplicates) {
	if (substr($interface, 0, 4) == "_vip") {
		$vpninterface = get_configured_vip_interface($interface);
		if (substr($vpninterface, 0, 4) == "_vip") {
			// vips are nested if its a ipalias with a carp parent
			$vpninterface = get_configured_vip_interface($vpninterface);
		}
		$ifacesuse = get_real_interface($vpninterface);
	} else {
		$ifacesuse = get_failover_interface($interface);
		if (substr($ifacesuse, 0, 4) == "_vip") {
			$vpninterface = get_configured_vip_interface($ifacesuse);
			$ifacesuse = get_real_interface($vpninterface);
		} else {
			$vpninterface = convert_real_interface_to_friendly_interface_name($ifacesuse);
		}
	}
	if ($family == 'inet' && !empty($ifacesuse) &&
	    interface_has_gateway($vpninterface)) {
		$gatewayip = get_interface_gateway($vpninterface);
		$interfaceip = get_interface_ip($vpninterface);
		$subnet_bits = get_interface_subnet($vpninterface);
		$subnet_ip = gen_subnetv4($interfaceip, $subnet_bits);
		/*
		 * if the remote gateway is in the local subnet, then don't add
		 * a route
		 */
		if (is_ipaddrv4($sourcehost) &&
		    !ip_in_subnet($sourcehost, "{$subnet_ip}/{$subnet_bits}") &&
		    is_ipaddrv4($gatewayip) && !$duplicates) {
			route_add_or_change($sourcehost, $gatewayip);
		}
	} else if ($family == 'inet6' && !empty($ifacesuse) &&
	    interface_has_gatewayv6($vpninterface)) {
		$gatewayip = get_interface_gateway_v6($vpninterface);
		$interfaceip = get_interface_ipv6($vpninterface);
		$subnet_bits = get_interface_subnetv6($vpninterface);
		$subnet_ip = gen_subnetv6($interfaceip, $subnet_bits);
		/*
		 * if the remote gateway is in the local subnet, then don't add
		 * a route
		 */
		if (is_ipaddrv6($sourcehost) &&
		    !ip_in_subnet($sourcehost, "{$subnet_ip}/{$subnet_bits}") &&
		    is_ipaddrv6($gatewayip) && !$duplicates) {
			route_add_or_change($sourcehost, $gatewayip);
		}
	}
	return $ifacesuse;
}

/****f* ipsec/ipsec_setup_authentication
 * NAME
 *   ipsec_setup_authentication - Generate an array with local/remote
 *                                authentication information for a given IPsec
 *                                Phase 1.
 * INPUTS
 *   $ph1ent: An IPsec Phase 1 configuration
 *   $conn  : A swanctl connection array corresponding to the IPsec Phase 1.
 * RESULT
 *   Populates $conn with local and remote arrays containing authentication
 *   details.
 ******/
function ipsec_setup_authentication(& $ph1ent, & $conn) {
	global $rgmap, $ipsec_swanctl_dirs, $config;
	$local = array();
	$remote = array();
	$remote2 = array();

	list($myid_type, $myid_data) = ipsec_find_id($ph1ent, 'local');
	$localid = ipsec_fixup_id($myid_type, $myid_data);
	if (!empty($localid)) {
		$local['id'] = $localid;
	}

	// Only specify peer ID if we are not dealing with mobile PSK
	if (!(isset($ph1ent['mobile']) &&
	    in_array($ph1ent['authentication_method'], array("pre_shared_key", "xauth_psk_server")))) {
		list ($remoteid_type, $remoteid_data) = ipsec_find_id($ph1ent, 'peer', $rgmap);
		$remoteid = ipsec_fixup_id($remoteid_type, $remoteid_data);
	}
	if (!empty($remoteid)) {
		$remote['id'] = $remoteid;
	}

	if (!empty($ph1ent['caref'])) {
		$ca = lookup_ca($ph1ent['caref']);
		if ($ca) {
			/* Get hash value to use for filename */
			$ca_details = openssl_x509_parse(base64_decode($ca['crt']));
			$cafn = "{$ipsec_swanctl_dirs['capath']}/{$ca_details['hash']}.0";
		}
	}

	$authentication = "";
	switch ($ph1ent['authentication_method']) {
		case 'eap-mschapv2':
			if (isset($ph1ent['mobile'])) {
				$local['auth'] = "pubkey";
				$remote['eap_id'] = "%any";
				$remote['auth'] = "eap-mschapv2";
			}
			break;
		case 'eap-tls':
			if (isset($ph1ent['mobile'])) {
				$local['auth'] = "pubkey";
				$remote['eap_id'] = "%any";
			} else {
				$local['auth'] = "eap-tls";
			}
			$remote['auth'] = "eap-tls";
			break;
		case 'eap-radius':
			if (isset($ph1ent['mobile'])) {
				$local['auth'] = "pubkey";
				$remote['eap_id'] = "%any";
			} else {
				$local['auth'] = "eap-radius";
			}
			if (($config['ipsec']['client']['group_source'] == 'enabled') &&
			    !empty($config['ipsec']['client']['auth_groups'])) {
				$remote['groups'] = $config['ipsec']['client']['auth_groups'];
			}
			$remote['auth'] = "eap-radius";
			break;
		case 'xauth_cert_server':
			$local['auth'] = "pubkey";
			$remote['auth'] = "pubkey";
			$remote2['auth'] = "xauth-generic";
			break;
		case 'xauth_psk_server':
			$local['auth'] = "psk";
			$remote['auth'] = "psk";
			$remote2['auth'] = "xauth-generic";
			break;
		case 'pre_shared_key':
			$local['auth'] = "psk";
			$remote['auth'] = "psk";
			break;
		case 'cert':
		case 'pkcs11':
			$local['auth'] = "pubkey";
			$remote['auth'] = "pubkey";
			break;
		case 'hybrid_cert_server':
			$local['auth'] = "pubkey";
			$remote['auth'] = "xauth-generic";
			break;
	}
	if (in_array($ph1ent['authentication_method'], array('eap-mschapv2', 'eap-tls', 'eap-radius', 'xauth_cert_server', 'cert', 'hybrid_cert_server')) &&
	    !empty($ph1ent['certref'])) {
		$local['cert'] = array('file' => "{$ipsec_swanctl_dirs['certpath']}/cert-{$ph1ent['ikeid']}.crt");
		$conn['send_cert'] = "always";
	}
	if ($ph1ent['authentication_method'] == 'pkcs11') {
		$local['cert'] = array('handle' => "{$ph1ent['pkcs11certref']}");
		$conn['send_cert'] = "always";
	}
	if (in_array($ph1ent['authentication_method'], array('eap-tls', 'xauth_cert_server', 'cert', 'pkcs11')) &&
	    isset($cafn)) {
		$remote['cacerts'] = $cafn;
	}

	$conn['local'] = $local;
	/* If there is data for a second remote auth round, setup two remote
	 * arrays with unique names, otherwise setup a single remote. */
	if (empty($remote2)) {
		$conn['remote'] = $remote;
	} else {
		$conn['remote-1'] = $remote;
		$conn['remote-2'] = $remote2;
	}
}

/****f* ipsec/ipsec_setup_proposal_algo
 * NAME
 *   ipsec_setup_proposal_algo - Form a single proposal algorithm string
 * INPUTS
 *   $ealg_id: Encryption algorithm ID
 *   $keylen : Key length for the encryption algorithm
 *   $halgo  : Hash algorithm
 *   $modp   : DH Group number
 * RESULT
 *   Returns a string using the available information to form a single proposal
 *   algorithm definition.
 * NOTES
 *   Values left empty will not be added to the string. Some combinations may
 *   require one or more parts to be omitted.
 ******/
function ipsec_setup_proposal_algo($ealg_id, $keylen, $halgo, $prfalgo, $modp) {
	$palgo = "";

	/* Add the encryption algorithm (if present) */
	if (!empty($ealg_id)) {
		$palgo .= "{$ealg_id}";
	}

	/* Add the key length (if present) */
	if (!empty($keylen)) {
		$palgo .= "{$keylen}";
	}

	/* Add the hash algorithm (if present) */
	if (!empty($halgo)) {
		/* If there is some content in the propsal already, add a
		 * separator */
		if (!empty($palgo)) {
			$palgo .= "-";
		}
		$halgo = str_replace('hmac_', '', $halgo);
		$palgo .= "{$halgo}";
	}

	if (!empty($prfalgo)) {
		$palgo .= "-prf{$prfalgo}";
	}

	/* Convert the DH group to its keyword and add (if present) */
	$modp = ipsec_convert_to_modp($modp);
	if (!empty($modp)) {
		$palgo .= "-{$modp}";
	}

	return $palgo;
}

/****f* ipsec/ipsec_setup_proposal_entry
 * NAME
 *   ipsec_setup_proposal_entry - Generate a full proposal string for an IPsec
 *                                Phase 2 configuration.
 * INPUTS
 *   $ph2ent  : An IPsec Phase 2 configuration
 *   $algo_arr: An array in which all proposal algorithms are collected
 *   $ealg_id : Encryption algorithm ID
 *   $keylen  : Key length for the encryption algorithm
 * RESULT
 *   Returns a string containing all proposal elements, and merges the entries
 *   to $algo_arr as well.
 ******/
function ipsec_setup_proposal_entry(& $ph2ent, & $algo_arr, $ealg_id, $keylen) {
	global $config, $p2_ealgos;
	$proposal = array();

	/* If multiple hash algorithms are present, loop through and add them all. */
	if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option']) 
	    && !strpos($ealg_id, "gcm")) {
		foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
			$proposal[] = ipsec_setup_proposal_algo($ealg_id, $keylen, $halgo, false, $ph2ent['pfsgroup']);
		}
	} else {
		$proposal[] = ipsec_setup_proposal_algo($ealg_id, $keylen, false, false, $ph2ent['pfsgroup']);
	}

	/* Add to master list */
	$algo_arr = array_merge($algo_arr, $proposal);
	return implode(',', $proposal);
}

/****f* ipsec/ipsec_setup_vtireq
 * NAME
 *   ipsec_setup_vtireq - Setup a VTI type IPsec request
 * INPUTS
 *   $child                : A swanctl child array
 *   $ipsec_vti_cleanup_ifs: An array of VTI interface names for later cleanup
 *   $ikeid                : The IKE ID of this child
 *   $idx                  : The index of this child (for split connections/IKEv1)
 * RESULT
 *   Sets up VTI-specific values for a request.
 ******/
function ipsec_setup_vtireq(& $child, & $ipsec_vti_cleanup_ifs, $ikeid, $idx = 0) {
	$child['reqid'] = get_ipsecifnum($ikeid, $idx);
	/* This interface will be a valid IPsec interface, so remove it from the cleanup list. */
	$ipsec_vti_cleanup_ifs = array_diff($ipsec_vti_cleanup_ifs, array("ipsec{$child['reqid']}"));
	$child['local_ts'] .= ",0.0.0.0/0";
	$child['remote_ts'] .= ",0.0.0.0/0";
}

/****f* ipsec/ipsec_setup_tunnels
 * NAME
 *   ipsec_setup_tunnels - Configure all P1/P2 entries as swanctl connections
 * INPUTS
 *   None
 * RESULT
 *   Sets up a swanctl array for all connections in the configuration along with
 *   their children, authentication, etc.
 ******/
function ipsec_setup_tunnels() {
	global $aggressive_mode_psk, $a_client, $config,
		$filterdns_list, $g, $ifacesuse, $ipsec_idhandling, $ipsec_log_cats,
		$ipsec_log_sevs, $ipsec_swanctl_basedir, $ipsec_swanctl_dirs,
		$ipseccfg, $mobile_ipsec_auth, $natfilterrules, $p1_ealgos,
		$p2_ealgos, $rgmap, $sa, $sn, $scconf, $conn, $tunnels,
		$ipsec_vti_cleanup_ifs, $conn_defaults;

	foreach ($tunnels as $ph1ent) {
		/* Skip disabled entries */
		if (isset($ph1ent['disabled'])) {
			continue;
		}
		/* If the local source is invalid, skip this entry. */
		$local_spec = ipsec_get_phase1_src($ph1ent);
		if (!$local_spec) {
			continue;
		}

		/* Determine the name of this connection, either con-mobile for
		 * mobile clients, or a name based on the IKE ID otherwise. */
		if (isset($ph1ent['mobile'])) {
			$cname = "con-mobile";
			/* Start with common default values */
			$scconf["{$cname}-defaults"] = $conn_defaults;
			/* Array reference to make things easier */
			$conn =& $scconf["{$cname}-defaults"];
			$scconf['connections']["{$cname} : {$cname}-defaults"] = array("# Stub to load con-mobile-defaults");
		} else {
			$cname = "con" . get_ipsecifnum($ph1ent['ikeid'], 0);
			/* Start with common default values */
			$scconf['connections'][$cname] = $conn_defaults;
			/* Array reference to make things easier */
			$conn =& $scconf['connections'][$cname];
		}

		/* Common parameters for all children */
		$child_params = array();
		if (!empty($ph1ent['closeaction'])) {
			$child_params['close_action'] = $ph1ent['closeaction'];
		}

		$ikeid = $ph1ent['ikeid'];

		/* "trap" adds policies to start a tunnel when interesting
		 * traffic is observed by the host. */
		$start_action = "trap";

		/* Set the IKE version appropriately (empty = IKEv1) */
		switch ($ph1ent['iketype']) {
			case 'auto':
				$ikeversion = 0;
				break;
			case 'ikev2':
				$ikeversion = 2;
				break;
			case 'ikev1':
			default:
				$ikeversion = 1;
				break;
		}
		$conn['version'] = $ikeversion;

		/* For IKEv1 or auto, setup aggressive mode if configured */
		if ($ikeversion != 2){
			$conn['aggressive'] = ($ph1ent['mode'] == "aggressive") ? "yes" : "no";
		}

		if (isset($ph1ent['mobile'])) {
			/* Mobile tunnels allow 'any' as a peer */
			$remote_spec = "0.0.0.0/0,::/0";
			/* Mobile tunnels cannot start automatically */
			$start_action = 'none';
		} else {
			$remote_spec = $ph1ent['remote-gateway'];
			$sourcehost = (is_ipaddr($remote_spec)) ? $remote_spec : $rgmap[$remote_spec];
			$ifacesuse = ipsec_setup_routes($ph1ent['interface'], $ph1ent['protocol'], $sourcehost, isset($ph1ent['gw_duplicates']));
		}

		/* Setup IKE proposals */
		if (is_array($ph1ent['encryption']['item'])) {
			$ciphers = array();
			foreach($ph1ent['encryption']['item'] as $p1enc) {
				if (!is_array($p1enc['encryption-algorithm']) ||
						empty($p1enc['encryption-algorithm']['name']) ||
						empty($p1enc['hash-algorithm'])) {
					continue;
				}
				if ($ph1ent['prfselect_enable'] != 'yes') {
					$p1enc['prf-algorithm'] = false;
				}
				$ciphers[] = ipsec_setup_proposal_algo($p1enc['encryption-algorithm']['name'],
									$p1enc['encryption-algorithm']['keylen'],
									$p1enc['hash-algorithm'],
									$p1enc['prf-algorithm'],
									$p1enc['dhgroup']);
			}
			$conn['proposals'] = implode(",", $ciphers);
		}

		/* Configure DPD values. The DPD action is a per-child parameter,
		 * not per-connection like the delay and timeout. */
		if ($ph1ent['dpd_delay'] && $ph1ent['dpd_maxfail']) {
			if ($start_action == "trap") {
				$child_params['dpd_action'] = "restart";
			} else {
				$child_params['dpd_action'] = "clear";
			}
			$conn['dpd_delay'] = "{$ph1ent['dpd_delay']}s";
			$conn['dpd_timeout'] =  $ph1ent['dpd_delay'] * ($ph1ent['dpd_maxfail'] + 1) . "s";
		} else {
			$child_params['dpd_action'] = "none";
		}

		/* Reauth (Any) */
		$conn['reauth_time'] = (empty($ph1ent['reauth_time']) ? "0" : $ph1ent['reauth_time']) . "s";

		/* Rekey (IKEv2 or Auto only, not supported by IKEv1) */
		if (($ikeversion == 0) || ($ikeversion == 2)) {
			$conn['rekey_time'] = (empty($ph1ent['rekey_time']) ? "0" : $ph1ent['rekey_time']) . "s";
		}

		/* Over Time */
		if (!empty($ph1ent['over_time'])) {
			$conn['over_time'] = "{$ph1ent['over_time']}s";
		}

		/* NAT Traversal */
		$conn['encap'] = ($ph1ent['nat_traversal'] == 'force') ? "yes" : "no";

		/* MOBIKE support */
		$conn['mobike'] = ($ph1ent['mobike'] == 'on') ? "yes" : "no";

		/* TFC Padding */
		if (isset($ph1ent['tfc_enable'])) {
			$conn['tfc_padding'] = (isset($ph1ent['tfc_bytes']) && is_numericint($ph1ent['tfc_bytes'])) ? $ph1ent['tfc_bytes'] : "mtu";
		}

		/* Custom Ports */
		if (isset($ph1ent['ikeport'])) {
			/* For a connection without encapsulation, do not set local_port */
			$conn['remote_port'] = $ph1ent['ikeport'];
		} elseif (isset($ph1ent['nattport'])) {
			/* For an encapsulated connection,
			 * set local_port to the server NAT-T port value or default (4500) */
			$conn['local_port'] = isset($config['ipsec']['port_nat_t']) ? $config['ipsec']['port_nat_t'] : 4500;
			$conn['remote_port'] = $ph1ent['nattport'];
		}

		/* Arrays for P2s/children */
		$children = array();
		$remote_ts_spec = array();
		$local_ts_spec = array();
		$reqids = array();
		$has_vti = false;
		$ealgoAHsp2arr = array();
		$ealgoESPsp2arr = array();
		$suffix = 0;

		foreach ($ph1ent['p2'] as $ph2ent) {
			/* If this entry is disabled, or cannot be configured, skip. */
			if (isset($ph2ent['disabled']) ||
			    (isset($ph2ent['mobile']) && !isset($a_client['enable']))) {
				continue;
			}
			$child = array();
			$local_ts = "";
			$remote_ts = "";
			if (($ph2ent['mode'] == 'tunnel') or ($ph2ent['mode'] == 'tunnel6')) {
				/* Normal tunnel child config */
				$child['mode'] = "tunnel";
				$child['policies'] = "yes";

				$localid_type = $ph2ent['localid']['type'];
				$localsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);

				/* Do not print localid in some cases, such as a pure-psk or psk/xauth single phase2 mobile tunnel */
				if (($localid_type == "none" || $localid_type == "mobile") &&
				    isset($ph1ent['mobile']) && (ipsec_get_number_of_phase2($ikeid) == 1)) {
					$local_spec = '0.0.0.0/0,::/0';
				} else {
					if ($localid_type != "address") {
						$localid_type = "subnet";
					}
					// Don't let an empty subnet into config, it can cause parse errors. Ticket #2201.
					if (!is_ipaddr($localsubnet_data) && !is_subnet($localsubnet_data) && ($localsubnet_data != "0.0.0.0/0")) {
						log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
						continue;
					}
					if (!empty($ph2ent['natlocalid'])) {
						$natlocalsubnet_data = ipsec_idinfo_to_cidr($ph2ent['natlocalid'], false, $ph2ent['mode']);
						if ($ph2ent['natlocalid']['type'] != "address") {
							if (is_subnet($natlocalsubnet_data)) {
								$localsubnet_data = "{$natlocalsubnet_data}|{$localsubnet_data}";
							}
						} else {
							if (is_ipaddr($natlocalsubnet_data)) {
								$localsubnet_data = "{$natlocalsubnet_data}|{$localsubnet_data}";
							}
						}
						$natfilterrules = true;
					}
				}

				$local_ts = $localsubnet_data;

				if (!isset($ph2ent['mobile'])) {
					$remote_ts = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
				} else if (!empty($a_client['pool_address'])) {
					$remote_ts = "{$a_client['pool_address']}/{$a_client['pool_netbits']}";
				}

			} elseif ($ph2ent['mode'] == 'vti') {
				/* VTI-specific child config */
				$child['policies'] = "no";
				/* VTI cannot use trap policies, so start automatically instead */
				$start_action = 'start';
				$localid_type = $ph2ent['localid']['type'];
				$localsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
				$local_ts = $localsubnet_data;
				$remote_ts = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
				$has_vti = true;
			} else {
				/* Transport mode child config */
				$child['mode'] = "transport";
				$child['policies'] = "yes";

				if ((($ph1ent['authentication_method'] == "xauth_psk_server") ||
				    ($ph1ent['authentication_method'] == "pre_shared_key")) &&
				    isset($ph1ent['mobile'])) {
					$local_spec = "0.0.0.0/0,::/0";
				} else {
					$local_ts = ipsec_get_phase1_src($ph1ent);
				}

				if (!isset($ph2ent['mobile'])) {
					$remote_ts = $remote_spec;
				}
			}

			if (!empty($local_ts)) {
				$local_ts_spec[] = $local_ts;
			}
			if (!empty($remote_ts)) {
				$remote_ts_spec[] = $remote_ts;
			}

			/* If a PFS group is configured on the Mobile Clients tab,
			 * and this is a mobile P2, use it here. */
			if (isset($a_client['pfs_group']) && isset($ph2ent['mobile'])) {
				$ph2ent['pfsgroup'] = $a_client['pfs_group'];
			}

			$reqids[] = $ph2ent['reqid'];

			if (!empty($ph2ent['lifetime'])) {
				$child['life_time'] = intval($ph2ent['lifetime']);
			}

			/* If we are to act only as a responder, disable the start action */
			$child['start_action'] = isset($ph1ent['responderonly']) ? 'none' : $start_action;

			/* Setup child SA proposals */
			$proposal = array();
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
									$proposal[] = ipsec_setup_proposal_entry($ph2ent, $ealgoESPsp2arr, $ealg_id, $keylen);
								}
							}
						} else {
							$proposal[] = ipsec_setup_proposal_entry($ph2ent, $ealgoESPsp2arr, $ealg_id, $ealg_kl);
						}
					}
				}
			} else if ($ph2ent['protocol'] == 'ah') {
				$proposal[] = ipsec_setup_proposal_entry($ph2ent, $ealgoAHsp2arr, '', '');
			}

			/* Not mobile, and IKEv1 or Split Connections active */
			if (!isset($ph1ent['mobile']) && (($ikeversion == 1) || isset($ph1ent['splitconn']))) {
				if (!empty($remote_ts)) {
					/* Setup child sub-connections using unique names with a suffix */
					$subconname = "con" . get_ipsecifnum($ph1ent['ikeid'], $idx) . $suffix;
					$children[$subconname] = $child;
					$children[$subconname]['local_ts'] = $local_ts;
					$children[$subconname]['remote_ts'] = $remote_ts;
					if ($has_vti) {
						ipsec_setup_vtireq($children[$subconname], $ipsec_vti_cleanup_ifs, $ph1ent['ikeid'], $suffix);
					}
					if (!empty($ph2ent['protocol']) && !empty($proposal)) {
						$children[$subconname][$ph2ent['protocol'] . '_proposals'] = implode(',', $proposal);
					}
				} else {
					log_error(sprintf(gettext("No phase2 specifications for tunnel with REQID = %s"), $ikeid));
				}
			} else {
				/* TODO: Fix this to nicely merge all P2 params for single child case */
				if (!is_array($children[$cname])) {
					$children[$cname] = array();
				}
				$children[$cname] = array_merge($children[$cname], $child);
			}
			$suffix++;
		}

		$conn['local_addrs'] = $local_spec;
		$conn['remote_addrs'] = $remote_spec;

		$pools = array();
		if (isset($ph1ent['mobile'])) {
			if (($ph1ent['authentication_method'] == 'eap-radius') && 
			    empty($a_client['pool_address']) && empty($a_client['pool_address_v6'])) {
				$pools[] = "radius";
			} else {
				if (!empty($a_client['pool_address'])) {
					$pools[] = "mobile-pool-v4";
				}
				if (!empty($a_client['pool_address_v6'])) {
					$pools[] = "mobile-pool-v6";
				}
				if (isset($a_client['radius_ip_priority_enable'])) {
					$pools[] .= "radius";
				}
			}
		}
		$conn['pools'] = implode(', ', $pools);

		/* For IKEv2 without Split Connections, setup combined sets of
		 * local/remote traffic selectors and propsals */
		if (!(!isset($ph1ent['mobile']) && (($ikeversion == 1) || isset($ph1ent['splitconn'])))) {
			if (!isset($ph1ent['mobile']) && !empty($remote_ts_spec)) {
				$children[$cname]['remote_ts'] = implode(",", $remote_ts_spec);
			}
			if (!empty($local_ts_spec)) {
				$children[$cname]['local_ts'] = implode(",", $local_ts_spec);
			}
			if ($has_vti) {
				ipsec_setup_vtireq($children[$cname], $ipsec_vti_cleanup_ifs, $ph1ent['ikeid']);
			}
			if (!empty($ealgoAHsp2arr)) {
				$children[$cname]['ah_proposals'] = implode(',', array_unique($ealgoAHsp2arr));
			}
			if (!empty($ealgoESPsp2arr)) {
				$children[$cname]['esp_proposals'] = implode(',', array_unique($ealgoESPsp2arr));
			}
		}

		/* Setup connection authentication */
		ipsec_setup_authentication($ph1ent, $conn);

		/* Add children to this connection, including default child parameters */
		if (count($children)) {
			foreach($children as $name => $child) {
				$conn['children'][$name] = array_merge($child_params, $child);
			}
		}

	}

	return;
}

/****f* ipsec/ipsec_setup_secrets
 * NAME
 *   ipsec_setup_secrets - Setup swanctl authentication secrets
 * INPUTS
 *   None
 * RESULT
 *   Returns a swanctl array containing secrets (PSKs, certs, etc) and writes out
 *   necessary CA, CRL, and certificate data.
 ******/
function ipsec_setup_secrets() {
	global $config, $a_phase1, $ipsec_swanctl_dirs, $ipseccfg, $rgmap, $scconf;

	$suffix = 0;

	/* write out CRL files */
	if (is_array($config['crl']) && count($config['crl'])) {
		foreach ($config['crl'] as $crl) {
			if (!isset($crl['text'])) {
				log_error(sprintf(gettext("Warning: Missing CRL data for %s"), $crl['descr']));
				continue;
			}
			$fpath = "{$ipsec_swanctl_dirs['crlpath']}/{$crl['refid']}.crl";
			if (!@file_put_contents($fpath, base64_decode($crl['text']))) {
				log_error(sprintf(gettext("Error: Cannot write IPsec CRL file for %s"), $crl['descr']));
				continue;
			}
		}
	}

	$vpncas = array();
	if (is_array($a_phase1) && count($a_phase1)) {
		foreach ($a_phase1 as $ph1ent) {
			if (isset($ph1ent['disabled'])) {
				continue;
			}

			if (strstr($ph1ent['authentication_method'], 'cert') ||
			    in_array($ph1ent['authentication_method'], array('eap-mschapv2', 'eap-tls', 'eap-radius'))) {
				/* Write certificate and private key, point to private key */
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

				@chmod($ipsec_swanctl_dirs['certpath'], 0600);

				$ph1keyfile = "{$ipsec_swanctl_dirs['keypath']}/cert-{$ikeid}.key";
				if (!file_put_contents($ph1keyfile, base64_decode($cert['prv']))) {
					log_error(sprintf(gettext("Error: Cannot write phase1 key file for %s"), $ph1ent['name']));
					continue;
				}
				@chmod($ph1keyfile, 0600);

				$ph1certfile = "{$ipsec_swanctl_dirs['certpath']}/cert-{$ikeid}.crt";
				if (!file_put_contents($ph1certfile, base64_decode($cert['crt']))) {
					log_error(sprintf(gettext("Error: Cannot write phase1 certificate file for %s"), $ph1ent['name']));
					@unlink($ph1keyfile);
					continue;
				}
				@chmod($ph1certfile, 0600);

				$scconf['secrets']['private-' . $suffix++] = array('file' => $ph1keyfile);
			} else if (strstr($ph1ent['authentication_method'], 'pkcs11')) {
				$p11_id = array();
				$output = shell_exec('/usr/local/bin/pkcs15-tool -c');
				preg_match_all('/ID\s+: (.*)/', $output, $p11_id);
				if (!empty($ph1ent['pkcs11certref']) && in_array($ph1ent['pkcs11certref'], $p11_id[1])) {
					$scconf['secrets']['token-' . $suffix++] = array(
						'handle' => $ph1ent['pkcs11certref'],
						'pin' => $ph1ent['pkcs11pin'],
					);
				} else {
					log_error(sprintf(gettext("Error: Invalid phase1 PKCS#11 certificate reference or PKCS#11 is not present for %s"), $ph1ent['name']));
					continue;
				}
			} else {
				/* Setup pre-shared keys */
				list($myid_type, $myid_data) = ipsec_find_id($ph1ent, 'local');
				list($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, 'peer', $rgmap);
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
					$scconf['secrets']['ike-' . $suffix++] = array(
						'secret' => '0s' . base64_encode(trim($ph1ent['pre-shared-key'])),
						'id-0' => $myid,
						'id-1' => $peerid,
					);
					if (isset($ph1ent['mobile'])) {
						$scconf['secrets']['ike-' . $suffix++] = array(
							'secret' => '0s' . base64_encode(trim($ph1ent['pre-shared-key'])),
							'id-0' => $myid,
							'id-1' => '%any',
						);
						$scconf['secrets']['ike-' . $suffix++] = array(
							'secret' => '0s' . base64_encode(trim($ph1ent['pre-shared-key'])),
						);
					}
				}
			}

			/* if the client authenticates with a cert add the
			 * client cert CA chain to the list of CAs to write
			 */
			if (in_array($ph1ent['authentication_method'],
			    array('cert', 'eap-tls', 'xauth_cert_server', 'pkcs11'))) {
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

	/* Write the required CAs */
	foreach ($vpncas as $carefid => $cadata) {
		$cacrt = base64_decode($cadata['crt']);
		$cacrtattrs = openssl_x509_parse($cacrt);
		if (!is_array($cacrtattrs) || !isset($cacrtattrs['hash'])) {
			log_error(sprintf(gettext("Error: Invalid certificate hash info for %s"), $cadata['descr']));
			continue;
		}
		$cafilename = "{$ipsec_swanctl_dirs['capath']}/{$cacrtattrs['hash']}.0";
		if (!@file_put_contents($cafilename, $cacrt)) {
				log_error(sprintf(gettext("Error: Cannot write IPsec CA file for %s"), $cadata['descr']));
				continue;
		}
	}

	/* Add user PSKs */
	if (is_array($config['system']) && is_array($config['system']['user'])) {
		foreach ($config['system']['user'] as $user) {
			if (!empty($user['ipsecpsk'])) {
				$scconf['secrets']['ike-' . $suffix++] = array(
					'secret' => '0s' . base64_encode(trim($user['ipsecpsk'])),
					'id-0' => $myid,
					'id-1' => $user['name'],
				);
			}
		}
		unset($user);
	}

	/* add PSKs for mobile clients */
	if (is_array($ipseccfg['mobilekey'])) {
		foreach ($ipseccfg['mobilekey'] as $key) {
			if (($key['ident'] == 'allusers') ||
			    ($key['ident'] == 'any')) {
				$key['ident'] = '%any';
			}
			$userkeyprefix = (strtolower($key['type']) == 'eap') ? 'eap' : 'ike';
			$scconf['secrets'][$userkeyprefix . '-' . $suffix++] = array(
				'secret' => '0s' . base64_encode(trim($key['pre-shared-key'])),
				'id-0' => $key['ident'],
			);
		}
		unset($key);
	}
	return;
}

/****f* ipsec/ipsec_configure
 * NAME
 *   ipsec_configure - Configure IPsec
 * INPUTS
 *   $restart: Boolean (default false), whether or not to restart the IPsec
 *             daemons.
 * RESULT
 *   IPsec-related configuration files are written, daemon is started or stopped
 *   appropriately.
 ******/
function ipsec_configure($restart = false) {
	global $aggressive_mode_psk, $a_client, $a_phase1, $a_phase2, $config,
		$filterdns_list, $g, $ifacesuse, $ipsec_idhandling, $ipsec_log_cats,
		$ipsec_log_sevs, $ipsec_swanctl_basedir, $ipsec_swanctl_dirs,
		$ipseccfg, $mobile_ipsec_auth, $natfilterrules, $p1_ealgos,
		$p2_ealgos, $rgmap, $sa, $sn, $scconf, $tunnels, $mobile_configured,
		$ipsec_vti_cleanup_ifs, $conn_defaults, $pool_addrs;

	/* service may have been enabled, disabled, or otherwise changed in a
	 *way requiring rule updates */
	filter_configure();

	if (!ipsec_enabled()) {
		/* IPsec is disabled */
		/* Stop charon */
		mwexec("/usr/local/sbin/strongswanrc stop");
		/* Stop dynamic monitoring */
		killbypid("{$g['varrun_path']}/filterdns-ipsec.pid");
		/* Wait for process to die */
		sleep(2);
		/* Shutdown enc0 interface*/
		mwexec("/sbin/ifconfig enc0 down");
		ipsec_gre_default_mtu(); 
		return 0;
	} else {
		/* Startup enc0 interface */
		mwexec("/sbin/ifconfig enc0 up");
	}

	if (platform_booting()) {
		echo gettext("Configuring IPsec VPN... ");
	}

	$ipsecstartlock = lock('ipsec', LOCK_EX);

	/* Prepare automatic ping_hosts.sh data */
	unlink_if_exists("{$g['vardb_path']}/ipsecpinghosts");
	touch("{$g['vardb_path']}/ipsecpinghosts");
	$ipsecpinghostsactive = false;

	/* Populate convenience variables */
	$syscfg = $config['system'];
	init_config_arr(array('ipsec', 'phase1'));
	$ipseccfg = $config['ipsec'];
	$a_phase1 = $config['ipsec']['phase1'];
	init_config_arr(array('ipsec', 'phase2'));
	$a_phase2 = $config['ipsec']['phase2'];
	init_config_arr(array('ipsec', 'client'));
	$a_client = $config['ipsec']['client'];

	$mobile_configured = false;

	/* Setup a single structured array to process, to avoid repeatedly
	 * looping through the arrays to locate entries later. */
	$tunnels = array();
	foreach ($a_phase1 as $p1) {
		if (empty($p1)) {
			continue;
		}
		if (isset($p1['mobile']) && !isset($p1['disabled'])) {
			$mobile_configured = true;
		}
		$tunnels[$p1['ikeid']] = $p1;
		$tunnels[$p1['ikeid']]['p2'] = array();
	}
	foreach ($a_phase2 as $p2) {
		$tunnels[$p2['ikeid']]['p2'][] = $p2;
	}

	$ipsec_vti_cleanup_ifs = array();
	$rgmap = array();
	$filterdns_list = array();
	$aggressive_mode_psk = false;
	$mobile_ipsec_auth = "";
	$ifacesuse = array();
	$natfilterrules = false;

	/* Configure asynchronous crypto. See https://redmine.pfsense.org/issues/8772 */
	set_sysctl(array('net.inet.ipsec.async_crypto' => (int) (isset($ipseccfg['async_crypto']) && ($ipseccfg['async_crypto'] == "enabled"))));

	/* Build a list of all IPsec interfaces configured on the firewall at the OS level */
	foreach (get_interface_arr() as $thisif) {
		if (substr($thisif, 0, 5) == "ipsec") {
			$ipsec_vti_cleanup_ifs[] = $thisif;
		}
	}

	/* Create directory structure for IPsec */
	ipsec_create_dirs();

	/* Setup gateways and interfaces */
	ipsec_setup_gwifs();

	/* Setup and write strongswan.conf */
	ipsec_setup_strongswan();

	/* Start Global Connection default values */
	$conn_defaults = array();
	/* Fragmentation is on for everyone */
	$conn_defaults['fragmentation'] = "yes";
	/* Default to 'replace' for unique IDs (was 'yes' in ipsec.conf previously) */
	$conn_defaults['unique'] = 'replace';
	/* If the configuration has a valid alternate value for unique ID handling,
	 * use it instead. */
	if (!empty($config['ipsec']['uniqueids']) &&
	    array_key_exists($config['ipsec']['uniqueids'], $ipsec_idhandling)) {
		$conn_defaults['unique'] = $config['ipsec']['uniqueids'];
	}
	if (isset($config['ipsec']['strictcrlpolicy'])) {
		$conn_defaults['strictcrlpolicy'] = "yes";
	}
	/* Disable ipcomp for now. redmine #6167
	if (isset($config['ipsec']['compression'])) {
		$conn_defaults['compress'] = "yes";
	}
	set_single_sysctl('net.inet.ipcomp.ipcomp_enable', (isset($config['ipsec']['compression'])) ? 1 : 0);
	*/
	/* End Global Connection Defaults */

	/* Start swanctl configuration (scconf) */
	$scconf = array();
	$scconf[] = "# This file is automatically generated. Do not edit";
	$scconf['connections'] = array();
	/* Setup IPsec bypass */
	ipsec_setup_bypass();
	/* Setup connections */
	ipsec_setup_tunnels();
	$scconf['pools'] = array();
	if ($mobile_configured) {
		/* Setup mobile address pools */
		ipsec_setup_pools();
		/* Setup per-user pools */
		ipsec_setup_userpools();
	}
	/* Setup secret data */
	$scconf['secrets'] = array();
	ipsec_setup_secrets();

	@file_put_contents("{$g['varetc_path']}/ipsec/swanctl.conf", ipsec_strongswan_confgen($scconf));

	/* Clean up unused VTI interfaces */
	foreach ($ipsec_vti_cleanup_ifs as $cleanif) {
		if (does_interface_exist($cleanif)) {
			mwexec("/sbin/ifconfig " . escapeshellarg($cleanif) . " destroy", false);
		}
	}

	/* set default MTU to 1400 for GRE over IPsec, othewise to 1476 */
	ipsec_gre_default_mtu(); 

	/* Manage process */
	if ($restart === true) {
		mwexec_bg("/usr/local/sbin/strongswanrc restart", false);
	} else {
		if (isvalidpid("{$g['varrun_path']}/charon.pid")) {
			mwexec_bg("/usr/local/sbin/strongswanrc reload", false);
		} else {
			mwexec_bg("/usr/local/sbin/strongswanrc start", false);
		}
	}

	// Run ping_hosts.sh once if it's enabled to avoid wait for minicron
	if ($ipsecpinghostsactive) {
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
			mwexec_bg("/usr/local/sbin/filterdns -p {$g['varrun_path']}/filterdns-ipsec.pid -i {$interval} -c {$g['varetc_path']}/ipsec/filterdns-ipsec.hosts -d 1");
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

function ipsec_gre_default_mtu() {
	global $config;

	foreach ($config['interfaces'] as $if => $ifdetail) { 
		if (interface_is_type($ifdetail['if'], 'gre') && !isset($ifdetail['mtu'])) {
			if (is_greipsec($ifdetail['if'])) {
				set_interface_mtu($ifdetail['if'], 1400);
			} else {
				set_interface_mtu($ifdetail['if'], 1476);
			}
		}
	}
}
?>
