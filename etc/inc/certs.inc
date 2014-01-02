<?php
/* $Id$ */
/*
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
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

		DISABLE_PHP_LINT_CHECKING
		pfSense_MODULE:	certificate_managaer
*/

define("OPEN_SSL_CONF_PATH", "/etc/ssl/openssl.cnf");

require_once("functions.inc");

global $openssl_digest_algs;
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

global $openssl_crl_status;
$openssl_crl_status = array(
	OCSP_REVOKED_STATUS_NOSTATUS              => "No Status (default)",
	OCSP_REVOKED_STATUS_UNSPECIFIED           => "Unspecified",
	OCSP_REVOKED_STATUS_KEYCOMPROMISE         => "Key Compromise",
	OCSP_REVOKED_STATUS_CACOMPROMISE          => "CA Compromise",
	OCSP_REVOKED_STATUS_AFFILIATIONCHANGED    => "Affiliation Changed",
	OCSP_REVOKED_STATUS_SUPERSEDED            => "Superseded",
	OCSP_REVOKED_STATUS_CESSATIONOFOPERATION  => "Cessation of Operation",
	OCSP_REVOKED_STATUS_CERTIFICATEHOLD       => "Certificate Hold"
);

function & lookup_ca($refid) {
	global $config;

	if (is_array($config['ca']))
		foreach ($config['ca'] as & $ca)
			if ($ca['refid'] == $refid)
				return $ca;

	return false;
}

function & lookup_ca_by_subject($subject) {
	global $config;

	if (is_array($config['ca']))
		foreach ($config['ca'] as & $ca)
		{
			$ca_subject = cert_get_subject($ca['crt']);
			if ($ca_subject == $subject)
				return $ca;
		}

	return false;
}

function & lookup_cert($refid) {
	global $config;

	if (is_array($config['cert']))
		foreach ($config['cert'] as & $cert)
			if ($cert['refid'] == $refid)
				return $cert;

	return false;
}

function & lookup_cert_by_name($name) {
	global $config;
	if (is_array($config['cert']))
		foreach ($config['cert'] as & $cert)
			if ($cert['descr'] == $name)
				return $cert;
}

function & lookup_crl($refid) {
	global $config;

	if (is_array($config['crl']))
		foreach ($config['crl'] as & $crl)
			if ($crl['refid'] == $refid)
				return $crl;

	return false;
}

function ca_chain_array(& $cert) {
	if($cert['caref']) {
		$chain = array();
		$crt = lookup_ca($cert['caref']);
		$chain[] = $crt;
		while ($crt) {
			$caref = $crt['caref'];
			if($caref)
				$crt = lookup_ca($caref);
			else
				$crt = false;
			if($crt)
				$chain[] = $crt;
		}
		return $chain;
	}
	return false;
}

function ca_chain(& $cert) {
	if($cert['caref']) {
		$ca = "";
		$cas = ca_chain_array($cert);
		if (is_array($cas))
			foreach ($cas as & $ca_cert)
			{
				$ca .= base64_decode($ca_cert['crt']);
				$ca .= "\n";
			}
		return $ca;
	}
	return "";
}

function ca_import(& $ca, $str, $key="", $serial=0) {
	global $config;

	$ca['crt'] = base64_encode($str);
	if (!empty($key))
		$ca['prv'] = base64_encode($key);
	if (!empty($serial))
		$ca['serial'] = $serial;
	$subject = cert_get_subject($str, false);
	$issuer = cert_get_issuer($str, false);
	
	// Find my issuer unless self-signed
	if($issuer <> $subject) {
		$issuer_crt =& lookup_ca_by_subject($issuer);
		if($issuer_crt)
			$ca['caref'] = $issuer_crt['refid'];
	}

	/* Correct if child certificate was loaded first */
	if (is_array($config['ca']))
		foreach ($config['ca'] as & $oca)
		{
			$issuer = cert_get_issuer($oca['crt']);
			if($ca['refid']<>$oca['refid'] && $issuer==$subject)
				$oca['caref'] = $ca['refid'];
		}
	if (is_array($config['cert']))
		foreach ($config['cert'] as & $cert)
		{
			$issuer = cert_get_issuer($cert['crt']);
			if($issuer==$subject)
				$cert['caref'] = $ca['refid'];
		}
	return true;
}

function ca_create(& $ca, $keylen, $lifetime, $dn, $digest_alg = "sha256") {

	$args = array(
		"x509_extensions" => "v3_ca",
		"digest_alg" => $digest_alg,
		"private_key_bits" => (int)$keylen,
		"private_key_type" => OPENSSL_KEYTYPE_RSA,
		"encrypt_key" => false);

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) return false;

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) return false;

	// self sign the certificate
	$res_crt = openssl_csr_sign($res_csr, null, $res_key, $lifetime, $args);
	if (!$res_crt) return false;

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt))
		return false;

	// return our ca information
	$ca['crt'] = base64_encode($str_crt);
	$ca['prv'] = base64_encode($str_key);
	$ca['serial'] = 0;

	return true;
}

function ca_inter_create(& $ca, $keylen, $lifetime, $dn, $caref, $digest_alg = "sha256") {
	// Create Intermediate Certificate Authority
	$signing_ca =& lookup_ca($caref);
	if (!$signing_ca)
		return false;

	$signing_ca_res_crt = openssl_x509_read(base64_decode($signing_ca['crt']));
	$signing_ca_res_key = openssl_pkey_get_private(array(0 => base64_decode($signing_ca['prv']) , 1 => ""));
	if (!$signing_ca_res_crt || !$signing_ca_res_key) return false;
	$signing_ca_serial = ++$signing_ca['serial'];

	$args = array(
		"x509_extensions" => "v3_ca",
		"digest_alg" => $digest_alg,
		"private_key_bits" => (int)$keylen,
		"private_key_type" => OPENSSL_KEYTYPE_RSA,
		"encrypt_key" => false);

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) return false;

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) return false;

	// Sign the certificate
	$res_crt = openssl_csr_sign($res_csr, $signing_ca_res_crt, $signing_ca_res_key, $lifetime, $args, $signing_ca_serial);
	if (!$res_crt) return false;

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt))
		return false;

	// return our ca information
	$ca['crt'] = base64_encode($str_crt);
	$ca['prv'] = base64_encode($str_key);
	$ca['serial'] = 0;

	return true;
}

function cert_import(& $cert, $crt_str, $key_str) {

	$cert['crt'] = base64_encode($crt_str);
	$cert['prv'] = base64_encode($key_str);

	$subject = cert_get_subject($crt_str, false);
	$issuer = cert_get_issuer($crt_str, false);
	
	// Find my issuer unless self-signed
	if($issuer <> $subject) {
		$issuer_crt =& lookup_ca_by_subject($issuer);
		if($issuer_crt)
			$cert['caref'] = $issuer_crt['refid'];
	}
	return true;
}

function cert_create(& $cert, $caref, $keylen, $lifetime, $dn, $type="user", $digest_alg = "sha256") {

	$ca =& lookup_ca($caref);
	if (!$ca)
		return false;

	$ca_str_crt = base64_decode($ca['crt']);
	$ca_str_key = base64_decode($ca['prv']);
	$ca_res_crt = openssl_x509_read($ca_str_crt);
	$ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
	if(!$ca_res_key) return false;
	$ca_serial = ++$ca['serial'];

	switch ($type) {
		case "ca":
			$cert_type = "v3_ca";
			break;
		case "server":
			$cert_type = "server";
			break;
		default:
			$cert_type = "usr_cert";
			break;
	}

	$args = array(
		"x509_extensions" => $cert_type,
		"digest_alg" => $digest_alg,
		"private_key_bits" => (int)$keylen,
		"private_key_type" => OPENSSL_KEYTYPE_RSA,
		"encrypt_key" => false);

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if(!$res_key) return false;

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if(!$res_csr) return false;

	// self sign the certificate
	$res_crt = openssl_csr_sign($res_csr, $ca_res_crt, $ca_res_key, $lifetime,
				 $args, $ca_serial);
	if(!$res_crt) return false;

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt))
		return false;

	// return our certificate information
	$cert['caref'] = $caref;
	$cert['crt'] = base64_encode($str_crt);
	$cert['prv'] = base64_encode($str_key);
	$cert['type'] = $type;

	return true;
}

function csr_generate(& $cert, $keylen, $dn, $digest_alg = "sha256") {

	$args = array(
		"x509_extensions" => "v3_req",
		"digest_alg" => $digest_alg,
		"private_key_bits" => (int)$keylen,
		"private_key_type" => OPENSSL_KEYTYPE_RSA,
		"encrypt_key" => false);

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if(!$res_key) return false;

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if(!$res_csr) return false;

	// export our request data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_csr_export($res_csr, $str_csr))
		return false;

	// return our request information
	$cert['csr'] = base64_encode($str_csr);
	$cert['prv'] = base64_encode($str_key);

	return true;
}

function csr_complete(& $cert, $str_crt) {

	// return our request information
	$cert['crt'] = base64_encode($str_crt);
	unset($cert['csr']);

	return true;
}

function csr_get_subject($str_crt, $decode = true) {

	if ($decode)
		$str_crt = base64_decode($str_crt);

	$components = openssl_csr_get_subject($str_crt);

	if (empty($components) || !is_array($components))
		return "unknown";

	ksort($components);
	foreach ($components as $a => $v) {
		if (!strlen($subject))
			$subject = "{$a}={$v}";
		else
			$subject = "{$a}={$v}, {$subject}";
	}

	return $subject;
}

function cert_get_subject($str_crt, $decode = true) {

	if ($decode)
		$str_crt = base64_decode($str_crt);

	$inf_crt = openssl_x509_parse($str_crt);
	$components = $inf_crt['subject'];

	if (empty($components) || !is_array($components))
		return "unknown";

	ksort($components);
	foreach ($components as $a => $v) {
		if (is_array($v)) {
			ksort($v);
			foreach ($v as $w) {
				$asubject = "{$a}={$w}";
				$subject = (strlen($subject)) ? "{$asubject}, {$subject}" : $asubject;
			}
		} else {
			$asubject = "{$a}={$v}";
			$subject = (strlen($subject)) ? "{$asubject}, {$subject}" : $asubject;
		}
	}

	return $subject;
}

function cert_get_subject_array($crt) {
	$str_crt = base64_decode($crt);
	$inf_crt = openssl_x509_parse($str_crt);
	$components = $inf_crt['subject'];

	if (!is_array($components))
		return;

	$subject_array = array();

	foreach($components as $a => $v)
		$subject_array[] = array('a' => $a, 'v' => $v);

	return $subject_array;
}

function cert_get_subject_hash($crt) {
	$str_crt = base64_decode($crt);
	$inf_crt = openssl_x509_parse($str_crt);
	return $inf_crt['subject'];
}

function cert_get_issuer($str_crt, $decode = true) {

	if ($decode)
		$str_crt = base64_decode($str_crt);

	$inf_crt = openssl_x509_parse($str_crt);
	$components = $inf_crt['issuer'];
	
	if (empty($components) || !is_array($components))
		return "unknown";

	ksort($components);
	foreach ($components as $a => $v) {
		if (!strlen($issuer))
			$issuer = "{$a}={$v}";
		else
			$issuer = "{$a}={$v}, {$issuer}";
	}

	return $issuer;
}

/* this function works on x509 (crt), rsa key (prv), and req(csr) */
function cert_get_modulus($str_crt, $decode = true, $type = "crt"){
	if ($decode)
		$str_crt = base64_decode($str_crt);

	$modulus = "";
	if ( in_array($type, array("crt", "prv", "csr")) ) {
			$type = str_replace( array("crt","prv","csr"), array("x509","rsa","req"), $type);
			$modulus = exec("echo \"{$str_crt}\" | openssl {$type} -noout -modulus");
	}
	return $modulus;
}
function csr_get_modulus($str_crt, $decode = true){
	return cert_get_modulus($str_crt, $decode, "csr");
}

function cert_get_purpose($str_crt, $decode = true) {
	if ($decode)
		$str_crt = base64_decode($str_crt);
	$crt_details = openssl_x509_parse($str_crt);
	$purpose = array();
	$purpose['ca'] = (stristr($crt_details['extensions']['basicConstraints'], 'CA:TRUE') === false) ? 'No': 'Yes';
	$purpose['server'] = ($crt_details['extensions']['nsCertType'] == "SSL Server") ? 'Yes': 'No';
	return $purpose;
}

function cert_get_dates($str_crt, $decode = true) {
	if ($decode)
		$str_crt = base64_decode($str_crt);
	$crt_details = openssl_x509_parse($str_crt);
	if ($crt_details['validFrom_time_t'] > 0)
		$start = date('r', $crt_details['validFrom_time_t']);
	if ($crt_details['validTo_time_t'] > 0)
		$end = date('r', $crt_details['validTo_time_t']);
	return array($start, $end);
}

function cert_get_serial($str_crt, $decode = true) {
	if ($decode)
		$str_crt = base64_decode($str_crt);
	$crt_details = openssl_x509_parse($str_crt);
	if (isset($crt_details['serialNumber']) && !empty($crt_details['serialNumber']))
		return $crt_details['serialNumber'];
	else
		return NULL;
}

function prv_get_modulus($str_crt, $decode = true){
	return cert_get_modulus($str_crt, $decode, "prv");
}

function is_user_cert($certref) {
	global $config;
	if (!is_array($config['system']['user']))
		return;
	foreach ($config['system']['user'] as $user) {
		if (!is_array($user['cert']))
			continue;
		foreach ($user['cert'] as $cert) {
			if ($certref == $cert)
				return true;
		}
	}
	return false;
}

function is_openvpn_server_cert($certref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-server']))
		return;
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if ($ovpns['certref'] == $certref)
			return true;
	}
	return false;
}

function is_openvpn_client_cert($certref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-client']))
		return;
	foreach ($config['openvpn']['openvpn-client'] as $ovpnc) {
		if ($ovpnc['certref'] == $certref)
			return true;
	}
	return false;
}

function is_ipsec_cert($certref) {
	global $config;
	if (!is_array($config['ipsec']['phase1']))
		return;
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if ($ipsec['certref'] == $certref)
			return true;
	}
	return false;
}

function is_webgui_cert($certref) {
	global $config;
	if (($config['system']['webgui']['ssl-certref'] == $certref)
		&& ($config['system']['webgui']['protocol'] != "http"))
		return true;
}

function is_captiveportal_cert($certref) {
	global $config;
	if (!is_array($config['captiveportal']))
		return;
	foreach ($config['captiveportal'] as $portal) {
		if (isset($portal['enable']) && isset($portal['httpslogin']) && ($portal['certref'] == $certref))
			return true;
	}
	return false;
}

function cert_in_use($certref) {
	return (is_webgui_cert($certref) ||
		is_user_cert($certref) ||
		is_openvpn_server_cert($certref) ||
		is_openvpn_client_cert($certref) ||
		is_ipsec_cert($certref) ||
		is_captiveportal_cert($certref));
}

function crl_create(& $crl, $caref, $name, $serial=0, $lifetime=9999) {
	global $config;
	$ca =& lookup_ca($caref);
	if (!$ca)
		return false;
	$crl['descr'] = $name;
	$crl['caref'] = $caref;
	$crl['serial'] = $serial;
	$crl['lifetime'] = $lifetime;
	$crl['cert'] = array();
	$crl_res = crl_update($crl);
	$config['crl'][] = $crl;
	return $crl_res;
}

function crl_update(& $crl) {
	global $config;
	$ca =& lookup_ca($crl['caref']);
	if (!$ca)
		return false;
	// If we have text but no certs, it was imported and cannot be updated.
	if (($crl["method"] != "internal") && (!empty($crl['text']) && empty($crl['cert'])))
		return false;
	$crl['serial']++;
	$ca_str_crt = base64_decode($ca['crt']);
	$ca_str_key = base64_decode($ca['prv']);
	$crl_res = openssl_crl_new($ca_str_crt, $crl['serial'], $crl['lifetime']);
	if (is_array($crl['cert']) && (count($crl['cert']) > 0)) {
		foreach ($crl['cert'] as $cert) {
			openssl_crl_revoke_cert($crl_res, base64_decode($cert["crt"]), $cert["revoke_time"], $cert["reason"]);
		}
	}
	openssl_crl_export($crl_res, $crl_text, $ca_str_key);
	$crl['text'] = base64_encode($crl_text);
	return $crl_res;
}

function cert_revoke($cert, & $crl, $reason=OCSP_REVOKED_STATUS_UNSPECIFIED) {
	global $config;
	if (is_cert_revoked($cert, $crl['refid']))
		return true;
	// If we have text but no certs, it was imported and cannot be updated.
	if (!is_crl_internal($crl))
		return false;
	$cert["reason"] = $reason;
	$cert["revoke_time"] = time();
	$crl["cert"][] = $cert;
	crl_update($crl);
	return true;
}

function cert_unrevoke($cert, & $crl) {
	global $config;
	if (!is_crl_internal($crl))
		return false;
	foreach ($crl['cert'] as $id => $rcert) {
		if (($rcert['refid'] == $cert['refid']) || ($rcert['descr'] == $cert['descr'])) {
			unset($crl['cert'][$id]);
			if (count($crl['cert']) == 0) {
				// Protect against accidentally switching the type to imported, for older CRLs
				if (!isset($crl['method']))
					$crl['method'] = "internal";
				crl_update($crl);
			} else
				crl_update($crl);
			return true;
		}
	}
	return false;
}

/* Compare two certificates to see if they match. */
function cert_compare($cert1, $cert2) {
	/* Ensure two certs are identical by first checking that their issuers match, then
		subjects, then serial numbers, and finally the moduli. Anything less strict
		could accidentally count two similar, but different, certificates as
		being identical. */
	$c1 = base64_decode($cert1['crt']);
	$c2 = base64_decode($cert2['crt']);
	if ((cert_get_issuer($c1, false) == cert_get_issuer($c2, false))
		&& (cert_get_subject($c1, false) == cert_get_subject($c2, false))
		&& (cert_get_serial($c1, false) == cert_get_serial($c2, false))
		&& (cert_get_modulus($c1, false) == cert_get_modulus($c2, false)))
		return true;
	return false;
}

function is_cert_revoked($cert, $crlref = "") {
	global $config;
	if (!is_array($config['crl']))
		return false;

	if (!empty($crlref)) {
		$crl = lookup_crl($crlref);
		if (!is_array($crl['cert']))
			return false;
		foreach ($crl['cert'] as $rcert) {
			if (cert_compare($rcert, $cert))
				return true;
		}
	} else {
		foreach ($config['crl'] as $crl) {
			if (!is_array($crl['cert']))
				continue;
			foreach ($crl['cert'] as $rcert) {
				if (cert_compare($rcert, $cert))
					return true;
			}
		}
	}
	return false;
}

function is_openvpn_server_crl($crlref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-server']))
		return;
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if (!empty($ovpns['crlref']) && ($ovpns['crlref'] == $crlref))
			return true;
	}
	return false;
}

// Keep this general to allow for future expansion. See cert_in_use() above.
function crl_in_use($crlref) {
	return (is_openvpn_server_crl($crlref));
}

function is_crl_internal($crl) {
	return (!(!empty($crl['text']) && empty($crl['cert'])) || ($crl["method"] == "internal"));
}

function cert_get_cn($crt, $isref = false) {
	/* If this is a certref, not an actual cert, look up the cert first */
	if ($isref) {
		$cert = lookup_cert($crt);
		/* If it's not a valid cert, bail. */
		if (!(is_array($cert) && !empty($cert['crt'])))
			return "";
		$cert = $cert['crt'];
	} else {
		$cert = $crt;
	}
	$sub = cert_get_subject_array($cert);
	if (is_array($sub)) {
		foreach ($sub as $s) {
			if (strtoupper($s['a']) == "CN")
				return $s['v'];
		}
	}
	return "";
}

?>
