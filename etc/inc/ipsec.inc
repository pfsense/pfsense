<?php
/*
	ipsec.inc
	Copyright (C) 2007 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
	All rights reserved.

	Parts of this code was originally based on vpn_ipsec_sad.php
	Copyright (C) 2003-2004 Manuel Kasper

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

	pfSense_BUILDER_BINARIES:	/sbin/setkey	/sbin/route
	pfSense_MODULE:	ipsec

*/

/* IPsec defines */
global $ipsec_loglevels;
$ipsec_loglevels = array("dmn" => "Daemon", "mgr" => "SA Manager", "ike" => "IKE SA", "chd" => "IKE Child SA",
	"job" => "Job Processing", "cfg" => "Configuration backend", "knl" => "Kernel Interface",
	"net" => "Networking", "asn" => "ASN encoding", "enc" => "Message encoding",
	"imc" => "Integrity checker", "imv" => "Integrity Verifier", "pts" => "Platform Trust Service",
	"tls" => "TLS handler", "esp" => "IPsec traffic", "lib" => "StrongSwan Lib");

global $my_identifier_list;
$my_identifier_list = array(
	'myaddress' => array( 'desc' => gettext('My IP address'), 'mobile' => true ),
	'address' => array( 'desc' => gettext('IP address'), 'mobile' => true ),
	'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
	'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
	'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
	'keyid tag' => array( 'desc' => gettext('KeyID tag'), 'mobile' => true ),
	'dyn_dns' => array( 'desc' => gettext('Dynamic DNS'), 'mobile' => true ));

global $peer_identifier_list;
$peer_identifier_list = array(
	'peeraddress' => array( 'desc' => gettext('Peer IP address'), 'mobile' => false ),
	'address' => array( 'desc' => gettext('IP address'), 'mobile' => false ),
	'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
	'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
	'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
	'keyid tag' => array( 'desc' =>gettext('KeyID tag'), 'mobile' => true ));

global $ipsec_idhandling;
$ipsec_idhandling = array(
	'yes' => 'YES', 'no' => 'NO', 'never' => 'NEVER', 'keep' => 'KEEP'
	);

global $p1_ealgos;
$p1_ealgos = array(
	'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'3des' => array( 'name' => '3DES' ),
	'cast128' => array( 'name' => 'CAST128' ),
	'des' => array( 'name' => 'DES' ));

global $p2_ealgos;
$p2_ealgos = array(
	'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'aes128gcm' => array( 'name' => 'AES128-GCM', 'keysel' => array( 'lo' => 64, 'hi' => 128, 'step' => 32 ) ),
	'aes192gcm' => array( 'name' => 'AES192-GCM', 'keysel' => array( 'lo' => 64, 'hi' => 128, 'step' => 32 ) ),
	'aes256gcm' => array( 'name' => 'AES256-GCM', 'keysel' => array( 'lo' => 64, 'hi' => 128, 'step' => 32 ) ),
	'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'3des' => array( 'name' => '3DES' ),
	'cast128' => array( 'name' => 'CAST128' ),
	'des' => array( 'name' => 'DES' ));

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
	22 => '22 (1024(sub 160) bit)',
	23 => '23 (2048(sub 224) bit)',
	24 => '24 (2048(sub 256) bit)'
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
	'hybrid_rsa_server' => array( 'name' => 'Hybrid RSA + Xauth', 'mobile' => true ),
	'xauth_rsa_server' => array( 'name' => 'Mutual RSA + Xauth', 'mobile' => true ),
	'xauth_psk_server' => array( 'name' => 'Mutual PSK + Xauth', 'mobile' => true ),
	'eap-tls' => array( 'name' => 'EAP-TLS', 'mobile' => true),
	'eap-mschapv2' => array( 'name' => 'EAP-MSChapv2', 'mobile' => true),
	'rsasig' => array( 'name' => 'Mutual RSA', 'mobile' => false ),
	'pre_shared_key' => array( 'name' => 'Mutual PSK', 'mobile' => false ) );

global $ipsec_preshared_key_type;
$ipsec_preshared_key_type = array(
	'PSK' => 'PSK',
	'EAP' => 'EAP'
	);

global $p2_modes;
$p2_modes = array(
	'tunnel' => 'Tunnel IPv4',
	'tunnel6' => 'Tunnel IPv6',
	'transport' => 'Transport');

global $p2_protos;
$p2_protos = array(
	'esp' => 'ESP',
	'ah' => 'AH');

global $p2_pfskeygroups;
$p2_pfskeygroups = array(
	0 => 'off',
	1  => '1 (768 bit)',
	2  => '2 (1024 bit)',
	5  => '5 (1536 bit)',
	14 => '14 (2048 bit)',
	15 => '15 (3072 bit)',
	16 => '16 (4096 bit)',
	17 => '17 (6144 bit)',
	18 => '18 (8192 bit)'
);

/*
 * ikeid management functions
 */

function ipsec_ikeid_used($ikeid) {
	global $config;

	foreach ($config['ipsec']['phase1'] as $ph1ent) {
		if ( $ikeid == $ph1ent['ikeid'] ) {
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
		if (!is_ipaddr($ph1ent['interface'])) {
			if (strpos($ph1ent['interface'], '_vip')) {
				$if = $ph1ent['interface'];
			} else {
				$if = get_failover_interface($ph1ent['interface']);
			}
			if ($ph1ent['protocol'] == "inet6") {
				$interfaceip = get_interface_ipv6($if);
			} else {
				$interfaceip = get_interface_ip($if);
			}
		} else {
			$interfaceip=$ph1ent['interface'];
		}
	} else {
		$if = "wan";
		if ($ph1ent['protocol'] == "inet6") {
			$interfaceip = get_interface_ipv6($if);
		} else {
			$interfaceip = get_interface_ip($if);
		}
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
		if (! platform_booting()) {
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
			} else
				return $idinfo['address'];
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
				$address = gen_subnetv6($address,$netbits);
				return "{$address}/{$netbits}";
			} else {
				$address = get_interface_ip($idinfo['type']);
				$netbits = get_interface_subnet($idinfo['type']);
				$address = gen_subnet($address,$netbits);
				return "{$address}/{$netbits}";
			}
			break; /* NOTREACHED */
	}
}

/*
 * Return phase2 idinfo in address/netmask format
 */
function ipsec_idinfo_to_subnet(& $idinfo,$addrbits = false) {
	global $config;

	switch ($idinfo['type']) {
		case "address":
			if ($addrbits) {
				if ($idinfo['mode'] == "tunnel6") {
					return $idinfo['address']."/128";
				} else {
					return $idinfo['address']."/255.255.255.255";
				}
			} else
				return $idinfo['address'];
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
				$address = gen_subnetv6($address,$netbits);
				return $address."/".$netbits;
			} else {
				$address = get_interface_ip($idinfo['type']);
				$netbits = get_interface_subnet($idinfo['type']);
				$address = gen_subnet($address,$netbits);
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
function ipsec_lookup_phase1(& $ph2ent,& $ph1ent) {
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

	if (ipsec_lookup_phase1($ph2ent,$ph1ent)) {
		return ipsec_phase1_status($ipsec_status, $ph1ent['ikeid']);
	}

	return false;
}

function ipsec_smp_dump_status() {
	global $config, $g, $custom_listtags;

	if (!file_exists("{$g['varrun_path']}/charon.xml")) {
		log_error("IPsec daemon not running or has a problem!");
		return;
	}

	$fd = @fsockopen("unix://{$g['varrun_path']}/charon.xml");
	if (!$fd) {
		log_error("Could not read status from IPsec");
		return;
	}
	$query = '<?xml version="1.0"?><message xmlns="http://www.strongswan.org/smp/1.0" type="request" id="1">';
	$query .= '<query><ikesalist/></query></message>';

	@fwrite($fd, $query);
	$response = "";
	while (!strstr($sread, "</message>")) {
		$sread = fgets($fd);
		if ($sread === false) {
			break;
		}
		$response .= $sread;
	}
	fclose($fd);

	if ($sread === false) {
		log_error("Error during reading of status from IPsec");
		return;
	}

	@file_put_contents("{$g['tmp_path']}/smp_status.xml", $response);
	unset($response, $sread);

	$custom_listtags = array('ikesa', 'childsa', 'network', 'auth');
	$response = parse_xml_config("{$g['tmp_path']}/smp_status.xml", "message");
	@unlink("{$g['tmp_path']}/smp_status.xml");
	unset($custom_listtags);

	return $response;
}

/*
 * Return dump of SPD table
 */
function ipsec_dump_spd()
{
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
						$cursp['reqid'] =  substr($upperspec[3], strpos($upperspec[3], "#")+1);
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
function ipsec_dump_sad()
{
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
				list($cursa['src'],$cursa['dst']) = explode(" ", $line);
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
	global $g, $custom_listtags;

	$_gb = exec("/usr/local/sbin/ipsec stroke leases > {$g['tmp_path']}/strongswan_leases.xml");

	if (!file_exists("{$g['tmp_path']}/strongswan_leases.xml")) {
		log_error(gettext("Unable to find IPsec daemon leases file. Could not display mobile user stats!"));
		return array();
	}

	/* This is needed for fixing #4130 */
	if (filesize("{$g['tmp_path']}/strongswan_leases.xml") < 200) {
		return array();
	}

	$custom_listtags = array('lease', 'pool');
	$response = parse_xml_config("{$g['tmp_path']}/strongswan_leases.xml", "leases");
	@unlink("{$g['tmp_path']}/strongswan_leases.xml");
	unset($custom_listtags, $_gb);

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

	$nbph2=0;

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
		return Net_IPv6::compress(Net_IPv6::uncompress($ipaddr));
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
			$thisid_data = "{$thisid_data}";
			break;
		case 'user_fqdn':
			$thisid_type = 'userfqdn';
			$thisid_data = "{$id_data}";
			break;
		case 'asn1dn':
			$thisid_data = $id_data;
			if ($thisid_data && $thisid_data[0] != '"') {
				$thisid_data = "\"{$id_data}\"";
			}
			break;
	}
	return array($thisid_type, $thisid_data);
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

?>
