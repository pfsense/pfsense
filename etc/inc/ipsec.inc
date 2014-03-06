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

	pfSense_BUILDER_BINARIES:	/usr/local/sbin/setkey
	pfSense_MODULE:	ipsec

*/

/* IPsec defines */
$ipsec_loglevels = array("dmn" => "Daemon", "mgr" => "SA Manager", "ike" => "IKE SA", "chd" => "IKE Child SA",
	"job" => "Job Processing", "cfg" => "Configuration backend", "knl" => "Kernel Interface",
	"net" => "Networking", "asn" => "ASN encoding", "enc" => "Message encoding",
	"imc" => "Integrity checker", "imv" => "Integrity Verifier", "pts" => "Platform Trust Service",
	"tls" => "TLS handler", "app" => "Not daemon", "esp" => "IPSec traffic", "lib" => "StrongSWAN Lib");

$my_identifier_list = array(
	'myaddress' => array( 'desc' => gettext('My IP address'), 'mobile' => true ),
	'address' => array( 'desc' => gettext('IP address'), 'mobile' => true ),
	'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
	'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
	'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
	'keyid tag' => array( 'desc' => gettext('KeyID tag'), 'mobile' => true ),
	'dyn_dns' => array( 'desc' => gettext('Dynamic DNS'), 'mobile' => true ));

$peer_identifier_list = array(
	'peeraddress' => array( 'desc' => gettext('Peer IP address'), 'mobile' => false ),
	'address' => array( 'desc' => gettext('IP address'), 'mobile' => false ),
	'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
	'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
	'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
	'keyid tag' => array( 'desc' =>gettext('KeyID tag'), 'mobile' => true ));

$p1_ealgos = array(
	'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'3des' => array( 'name' => '3DES' ),
	'cast128' => array( 'name' => 'CAST128' ),
	'des' => array( 'name' => 'DES' ));

$p2_ealgos = array(
	'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
	'3des' => array( 'name' => '3DES' ),
	'cast128' => array( 'name' => 'CAST128' ),
	'des' => array( 'name' => 'DES' ));

$p1_halgos = array(
	'md5' => 'MD5',
	'sha1' => 'SHA1',
	'sha256' => 'SHA256',
	'sha384' => 'SHA384',
	'sha512' => 'SHA512'
);

$p1_dhgroups = array(
	1  => '1 (768 bit)',
	2  => '2 (1024 bit)',
	5  => '5 (1536 bit)',
	14 => '14 (2048 bit)',
	15 => '15 (3072 bit)',
	16 => '16 (4096 bit)',
	17 => '17 (6144 bit)',
	18 => '18 (8192 bit)'
);

$p2_halgos = array(
	'hmac_md5' => 'MD5',
	'hmac_sha1' => 'SHA1',
	'hmac_sha256' => 'SHA256',
	'hmac_sha384' => 'SHA384',
	'hmac_sha512' => 'SHA512'
);

$p1_authentication_methods = array(
	'hybrid_rsa_server' => array( 'name' => 'Hybrid RSA + Xauth', 'mobile' => true ),
	'xauth_rsa_server' => array( 'name' => 'Mutual RSA + Xauth', 'mobile' => true ),
	'xauth_psk_server' => array( 'name' => 'Mutual PSK + Xauth', 'mobile' => true ),
	'rsasig' => array( 'name' => 'Mutual RSA', 'mobile' => false ),
	'pre_shared_key' => array( 'name' => 'Mutual PSK', 'mobile' => false ) );

$p2_modes = array(
	'tunnel' => 'Tunnel IPv4',
	'tunnel6' => 'Tunnel IPv6',
	'transport' => 'Transport');

$p2_protos = array(
	'esp' => 'ESP',
	'ah' => 'AH');

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

	foreach ($config['ipsec']['phase1'] as $ph1ent)
		if( $ikeid == $ph1ent['ikeid'] )
			return true;

	return false;
}

function ipsec_ikeid_next() {

	$ikeid = 1;
	while(ipsec_ikeid_used($ikeid))
		$ikeid++;

	return $ikeid;
}

/*
 * Return phase1 local address
 */
function ipsec_get_phase1_src(& $ph1ent) {

	if ($ph1ent['interface']) {
		if (!is_ipaddr($ph1ent['interface'])) {
			if ($ph1ent['protocol'] == "inet6") { 
				$if = get_failover_interface($ph1ent['interface'], "inet6");
				$interfaceip = get_interface_ipv6($if);
			} else {
				$if = get_failover_interface($ph1ent['interface']);
				$interfaceip = get_interface_ip($if);
			}
		} else {
			$interfaceip=$ph1ent['interface'];
		}
	} else {
		$if = "wan";
		if ($ph1ent['protocol'] == "inet6")
			$interfaceip = get_interface_ipv6($if);
		else
			$interfaceip = get_interface_ip($if);
	}

	return $interfaceip;
}

/*
 * Return phase1 local address
 */
function ipsec_get_phase1_dst(& $ph1ent) {
	global $g;

	if (empty($ph1ent['remote-gateway']))
		return false;
	$rg = $ph1ent['remote-gateway'];
	if (!is_ipaddr($rg)) {
		if(! $g['booting'])
			return resolve_retry($rg);
	}
	if(!is_ipaddr($rg))
		return false;

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
				if ($mode == "tunnel6")
					return $idinfo['address']."/128";
				else
					return $idinfo['address']."/32";
			} else
				return $idinfo['address'];
			break; /* NOTREACHED */
		case "network":
			return "{$idinfo['address']}/{$idinfo['netbits']}";
			break; /* NOTREACHED */
		case "none":
		case "mobile":
			return "0.0.0.0/0";
			break; /* NOTREACHED */
		default:
			if (empty($mode) && !empty($idinfo['mode']))
				$mode = $idinfo['mode'];

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
				if ($idinfo['mode'] == "tunnel6")
					return $idinfo['address']."/128";
				else
					return $idinfo['address']."/255.255.255.255";
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
		if (!empty($config['interfaces'][$idinfo['type']]))
			return convert_friendly_interface_to_friendly_descr($idinfo['type']);
		else
			return strtoupper($idinfo['type']);
		break; /* NOTREACHED */
	}
}

/*
 * Return phase1 association for phase2
 */
function ipsec_lookup_phase1(& $ph2ent,& $ph1ent) {
	global $config;

	if (!is_array($config['ipsec']))
		return;
	if (!is_array($config['ipsec']['phase1']))
		return;
	if (empty($config['ipsec']['phase1']))
		return;

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
function ipsec_phase1_status(& $ph1ent) {

	$loc_ip = get_ipsec_tunnel_src($ph1ent);
	$rmt_ip = $ph1ent['remote-gateway'];

	if (ipsec_lookup_ipsakmp_sa($loc_ip,$rmt_ip))
		return true;

	return false;
}

/*
 * Check phase2 communications status
 */
function ipsec_phase2_status(& $spd,& $sad,& $ph1ent,& $ph2ent) {

	$loc_ip = ipsec_get_phase1_src($ph1ent);
	$rmt_ip = ipsec_get_phase1_dst($ph1ent);

	$loc_id = ipsec_idinfo_to_cidr($ph2ent['localid'],true,$ph2ent['mode']);
	if (!empty($ph2ent['natlocalid']))
		$natloc_id = ipsec_idinfo_to_cidr($ph2ent['natlocalid'],true,$ph2ent['mode']);
	$rmt_id = ipsec_idinfo_to_cidr($ph2ent['remoteid'],true,$ph2ent['mode']);

	/* check for established SA in both directions */
	if( ipsec_lookup_ipsec_sa($spd,$sad,"out",$loc_ip,$rmt_ip,$loc_id,$rmt_id)) {
		if (empty($ph2ent['natlocalid']) && ipsec_lookup_ipsec_sa($spd,$sad,"in",$rmt_ip,$loc_ip,$rmt_id,$loc_id))
			return true;
		else if (!empty($ph2ent['natlocalid']) && ipsec_lookup_ipsec_sa($spd,$sad,"out",$loc_ip,$rmt_ip,$loc_id,$rmt_id))
			return true;
	}

	return false;
}

/*
 * Return ISAKMP SA details
 */
function ipsec_lookup_isakmp_sa($in_srcip,$in_dstip) {
	/* TODO : use racconctl to lookup iskamp SA */
	return NULL;
}

/*
 * Return IPsec SA details
 */
function ipsec_lookup_ipsec_sa(& $spd,& $sad,$dir,$in_srcip,$in_dstip,$in_srcid,$in_dstid) {

	/* match the phase1/2 to an SP */
	$in_srcip = ipsec_fixup_ip($in_srcip);
	$in_dstip = ipsec_fixup_ip($in_dstip);
	$in_srcid = ipsec_fixup_ip($in_srcid);
	$in_dstid = ipsec_fixup_ip($in_dstid);

	foreach($spd as $sp) {

		/* match direction */

		if($dir != $sp['dir'])
			continue;

		/* match IPs */

		if($in_srcip != ipsec_fixup_ip($sp['src']))
			continue;
		if($in_dstip != ipsec_fixup_ip($sp['dst']))
			continue;

		/* add netbits for address IDs */

		$sp_srcid = $sp['srcid'];
		$sp_dstid = $sp['dstid'];

		if (!strstr($sp_srcid,"/")) {
			if (is_ipaddrv4($sp_srcid))
				$sp_srcid .= '/32';
			elseif (is_ipaddrv6($sp_srcid))
				$sp_srcid .= '/128';
		}
		if (!strstr($sp_dstid,"/")) {
			if (is_ipaddrv4($sp_dstid))
				$sp_dstid .= '/32';
			elseif (is_ipaddrv6($sp_dstid))
				$sp_dstid .= '/128';
		}

		/* match IDs */

		if($in_srcid != ipsec_fixup_ip($sp_srcid))
			continue;
		if($in_dstid != ipsec_fixup_ip($sp_dstid))
			continue;

		/* match the SP to a unique SA by reqid */

		foreach($sad as $sa) {

			/* match REQIDs */

			if($sa[reqid] != $sp[reqid])
				continue;

			/* sanitize for NAT-T ports */

			$sa_srcip = $sa['src'];
			$sa_dstip = $sa['dst'];

			if (strstr($sa_srcip,"["))
				$sa_srcip = substr($sa_srcip,0,strcspn($sa_srcip,"["));
			if (strstr($sa_dstip,"["))
				$sa_dstip = substr($sa_dstip,0,strcspn($sa_dstip,"["));

			/* match IPs */

			if($in_srcip != ipsec_fixup_ip($sa_srcip))
				continue;
			if($in_dstip != ipsec_fixup_ip($sa_dstip))
				continue;

			return $sa;
		}
	}

	return NULL;
}

function ipsec_smp_dump_status() {
	global $config, $g, $custom_listtags;

	if (!file_exists("{$g['varrun_path']}/charon.xml")) {
		log_error("IPSec daemon seems to have issues or not running!");
		return;
	}

	$fd = @fsockopen("unix://{$g['varrun_path']}/charon.xml");
	if (!$fd) {
		log_error("Could not read status from ipsec");
		return;
	}
	$query = '<?xml version="1.0"?><message xmlns="http://www.strongswan.org/smp/1.0" type="request" id="1">';
	$query .= '<query><ikesalist/></query></message>';

	@fwrite($fd, $query);
	$response = "";
	while (!strstr($sread, "</message>")) {
		$sread = fgets($fd);
		$response .= $sread;
	}
	fclose($fd);

	@file_put_contents("{$g['tmp_path']}/smp_status.xml", $response);
	unset($response, $sread);

	$custom_listtags = array('ikesa', 'childsa', 'network');
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
	$fd = @popen("/usr/local/sbin/setkey -DP", "r");
	$spd = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line)
				continue;
			if ($line == "No SPD entries.")
				break;
			if ($line[0] != "\t") {
				if (is_array($cursp))
					$spd[] = $cursp;
				$cursp = array();
				$linea = explode(" ", $line);
				$cursp['srcid'] = substr($linea[0], 0, strpos($linea[0], "["));
				$cursp['dstid'] = substr($linea[1], 0, strpos($linea[1], "["));
				$i = 0;
			} else if (is_array($cursp)) {
				$linea = explode(" ", trim($line));
				switch($i)
				{
					case 1:
						if ($linea[1] == "none")	/* don't show default anti-lockout rule */
							unset($cursp);
						else
							$cursp['dir'] = $linea[0];
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
		if (is_array($cursp) && count($cursp))
			$spd[] = $cursp;
		pclose($fd);
	}

	return $spd;
}

/*
 * Return dump of SAD table
 */
function ipsec_dump_sad()
{
	$fd = @popen("/usr/local/sbin/setkey -D", "r");
	$sad = array();
	if ($fd) {
		while (!feof($fd)) {
			$line = chop(fgets($fd));
			if (!$line || $line[0] == " ")
				continue;
			if ($line == "No SAD entries.")
				break;
			if ($line[0] != "\t")
			{
				if (is_array($cursa))
					$sad[] = $cursa;
				$cursa = array();
				list($cursa['src'],$cursa['dst']) = explode(" ", $line);
				$i = 0;
			}
			else
			{
				$linea = explode(" ", trim($line));
				switch ($i) {
					case 1:
						$cursa['proto'] = $linea[0];
						$cursa['spi'] = substr($linea[2], strpos($linea[2], "x")+1, -1);
						$reqid = substr($linea[3], strpos($linea[3], "=")+1);
						$cursa['reqid'] = substr($reqid, 0, strcspn($reqid,"("));
						break;
					case 2:
						$cursa['ealgo'] = $linea[1];
						break;
					case 3:
						$cursa['aalgo'] = $linea[1];
						break;
					case 8:
						$sadata = explode("(", $linea[1]);
						$cursa['data'] = $sadata[0] . " B";
						break;
				}
			}
			$i++;
		}
		if (is_array($cursa) && count($cursa))
			$sad[] = $cursa;
		pclose($fd);
	}

	return $sad;
}

/*
 * Return dump of mobile user list
 */
function ipsec_dump_mobile() {
	$command = "/usr/local/sbin/racoonctl show-users";
	$fd = @popen($command, "r");
	$mobile = array();
	if ($fd) {
		while (!feof($fd)) {
			$user = array();
			$line = chop(fgets($fd));
			if (!$line)
				continue;
			if ($line == "User|Source|Destination|CreatedOn|SPI")
				continue;

			// jim|192.168.20.243:4500|192.168.20.5:24146|2012-05-25 09:54:39|989d10e1e2d4eca4:7243830d5fd2afe7
			$linea = explode("|", trim($line));
			$user['username'] = $linea[0];
			$user['local'] = $linea[1];
			$user['remote'] = $linea[2];
			$user['logintime'] = $linea[3];
			$user['spi'] = $linea[4];
			if (!empty($user['username']))
				$mobile[] = $user;
		}
		pclose($fd);
	}

	return $mobile;
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

function ipsec_disconnect_mobile($username) {
	if (empty($username))
		return false;
	exec("/usr/local/sbin/racoonctl logout-user " . escapeshellarg($username));
}

function ipsec_fixup_ip($ipaddr) {
	if (is_ipaddrv6($ipaddr) || is_subnetv6($ipaddr))
		return Net_IPv6::compress(Net_IPv6::uncompress($ipaddr));
	else
		return $ipaddr;
}

?>
