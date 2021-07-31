<?php
/*
 * certs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc. All rights reserved.
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

define("OPEN_SSL_CONF_PATH", "/etc/ssl/openssl.cnf");

require_once("functions.inc");

global $openssl_digest_algs;
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

global $openssl_crl_status;
/* Numbers are set in the RFC: https://www.ietf.org/rfc/rfc5280.txt */
$openssl_crl_status = array(
	-1 => "No Status (default)",
	0  => "Unspecified",
	1 => "Key Compromise",
	2 => "CA Compromise",
	3 => "Affiliation Changed",
	4 => "Superseded",
	5 => "Cessation of Operation",
	6 => "Certificate Hold",
	9 => 'Privilege Withdrawn',
);

global $cert_altname_types;
$cert_altname_types = array(
	'DNS' => gettext('FQDN or Hostname'),
	'IP' => gettext('IP address'),
	'URI' => gettext('URI'),
	'email' => gettext('email address'),
);

global $cert_max_lifetime;
$cert_max_lifetime = 12000;

function & lookup_ca($refid) {
	global $config;

	if (is_array($config['ca'])) {
		foreach ($config['ca'] as & $ca) {
			if ($ca['refid'] == $refid) {
				return $ca;
			}
		}
	}

	return false;
}

function & lookup_ca_by_subject($subject) {
	global $config;

	if (is_array($config['ca'])) {
		foreach ($config['ca'] as & $ca) {
			$ca_subject = cert_get_subject($ca['crt']);
			if ($ca_subject == $subject) {
				return $ca;
			}
		}
	}

	return false;
}

function & lookup_cert($refid) {
	global $config;

	if (is_array($config['cert'])) {
		foreach ($config['cert'] as & $cert) {
			if ($cert['refid'] == $refid) {
				return $cert;
			}
		}
	}

	return false;
}

function & lookup_cert_by_name($name) {
	global $config;
	if (is_array($config['cert'])) {
		foreach ($config['cert'] as & $cert) {
			if ($cert['descr'] == $name) {
				return $cert;
			}
		}
	}
}

function & lookup_crl($refid) {
	global $config;

	if (is_array($config['crl'])) {
		foreach ($config['crl'] as & $crl) {
			if ($crl['refid'] == $refid) {
				return $crl;
			}
		}
	}

	return false;
}

function ca_chain_array(& $cert) {
	if ($cert['caref']) {
		$chain = array();
		$crt = lookup_ca($cert['caref']);
		$chain[] = $crt;
		while ($crt) {
			$caref = $crt['caref'];
			if ($caref) {
				$crt = lookup_ca($caref);
			} else {
				$crt = false;
			}
			if ($crt) {
				$chain[] = $crt;
			}
		}
		return $chain;
	}
	return false;
}

function ca_chain(& $cert) {
	if ($cert['caref']) {
		$ca = "";
		$cas = ca_chain_array($cert);
		if (is_array($cas)) {
			foreach ($cas as & $ca_cert) {
				$ca .= base64_decode($ca_cert['crt']);
				$ca .= "\n";
			}
		}
		return $ca;
	}
	return "";
}

function ca_import(& $ca, $str, $key = "", $serial = "") {
	global $config;

	$ca['crt'] = base64_encode($str);
	if (!empty($key)) {
		$ca['prv'] = base64_encode($key);
	}
	if (empty($serial)) {
		$ca['serial'] = 0;
	} else {
		$ca['serial'] = $serial;
	}
	$subject = cert_get_subject($str, false);
	$issuer = cert_get_issuer($str, false);
	$serialNumber = cert_get_serial($str, false);

	// Find my issuer unless self-signed
	if ($issuer <> $subject) {
		$issuer_crt =& lookup_ca_by_subject($issuer);
		if ($issuer_crt) {
			$ca['caref'] = $issuer_crt['refid'];
		}
	}

	/* Correct if child certificate was loaded first */
	if (is_array($config['ca'])) {
		foreach ($config['ca'] as & $oca) {
			// check by serial number if CA already exists
			$osn = cert_get_serial($oca['crt']);
			if (($ca['refid'] <> $oca['refid']) && ($serialNumber == $osn)) {
				return false;
			}
			$issuer = cert_get_issuer($oca['crt']);
			if (($ca['refid'] <> $oca['refid']) && ($issuer == $subject)) {
				$oca['caref'] = $ca['refid'];
			}
		}
	}
	if (is_array($config['cert'])) {
		foreach ($config['cert'] as & $cert) {
			$issuer = cert_get_issuer($cert['crt']);
			if ($issuer == $subject) {
				$cert['caref'] = $ca['refid'];
			}
		}
	}
	return true;
}

function ca_create(& $ca, $keylen, $lifetime, $dn, $digest_alg = "sha256", $keytype = "RSA", $ecname = "prime256v1") {

	$args = array(
		"x509_extensions" => "v3_ca",
		"digest_alg" => $digest_alg,
		"encrypt_key" => false);
	if ($keytype == 'ECDSA') {
		$args["curve_name"] = $ecname;
		$args["private_key_type"] = OPENSSL_KEYTYPE_EC;
	} else {
		$args["private_key_bits"] = (int)$keylen;
		$args["private_key_type"] = OPENSSL_KEYTYPE_RSA;
	}

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) {
		return false;
	}

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) {
		return false;
	}

	// self sign the certificate
	$res_crt = openssl_csr_sign($res_csr, null, $res_key, $lifetime, $args, cert_get_random_serial());
	if (!$res_crt) {
		return false;
	}

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt)) {
		return false;
	}

	// return our ca information
	$ca['crt'] = base64_encode($str_crt);
	$ca['prv'] = base64_encode($str_key);
	$ca['serial'] = 0;

	return true;
}

function ca_inter_create(& $ca, $keylen, $lifetime, $dn, $caref, $digest_alg = "sha256", $keytype = "RSA", $ecname = "prime256v1") {
	// Create Intermediate Certificate Authority
	$signing_ca =& lookup_ca($caref);
	if (!$signing_ca) {
		return false;
	}

	$signing_ca_res_crt = openssl_x509_read(base64_decode($signing_ca['crt']));
	$signing_ca_res_key = openssl_pkey_get_private(array(0 => base64_decode($signing_ca['prv']) , 1 => ""));
	if (!$signing_ca_res_crt || !$signing_ca_res_key) {
		return false;
	}
	$signing_ca_serial = ++$signing_ca['serial'];

	$args = array(
		"x509_extensions" => "v3_ca",
		"digest_alg" => $digest_alg,
		"encrypt_key" => false);
	if ($keytype == 'ECDSA') {
		$args["curve_name"] = $ecname;
		$args["private_key_type"] = OPENSSL_KEYTYPE_EC;
	} else {
		$args["private_key_bits"] = (int)$keylen;
		$args["private_key_type"] = OPENSSL_KEYTYPE_RSA;
	}

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) {
		return false;
	}

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) {
		return false;
	}

	// Sign the certificate
	$res_crt = openssl_csr_sign($res_csr, $signing_ca_res_crt, $signing_ca_res_key, $lifetime, $args, $signing_ca_serial);
	if (!$res_crt) {
		return false;
	}

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt)) {
		return false;
	}

	// return our ca information
	$ca['crt'] = base64_encode($str_crt);
	$ca['prv'] = base64_encode($str_key);
	$ca['serial'] = 0;
	$ca['caref'] = $caref;

	return true;
}

function cert_import(& $cert, $crt_str, $key_str) {

	$cert['crt'] = base64_encode($crt_str);
	$cert['prv'] = base64_encode($key_str);

	$subject = cert_get_subject($crt_str, false);
	$issuer = cert_get_issuer($crt_str, false);

	// Find my issuer unless self-signed
	if ($issuer <> $subject) {
		$issuer_crt =& lookup_ca_by_subject($issuer);
		if ($issuer_crt) {
			$cert['caref'] = $issuer_crt['refid'];
		}
	}
	return true;
}

function cert_create(& $cert, $caref, $keylen, $lifetime, $dn, $type = "user", $digest_alg = "sha256", $keytype = "RSA", $ecname = "prime256v1") {

	$cert['type'] = $type;

	if ($type != "self-signed") {
		$cert['caref'] = $caref;
		$ca =& lookup_ca($caref);
		if (!$ca) {
			return false;
		}

		$ca_str_crt = base64_decode($ca['crt']);
		$ca_str_key = base64_decode($ca['prv']);
		$ca_res_crt = openssl_x509_read($ca_str_crt);
		$ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
		if (!$ca_res_key) {
			return false;
		}

		/* Get the next available CA serial number. */
		$ca_serial = ca_get_next_serial($ca);
	}

	$cert_type = cert_type_config_section($type);

	// in case of using Subject Alternative Names use other sections (with postfix '_san')
	// pass subjectAltName over environment variable 'SAN'
	if ($dn['subjectAltName']) {
		putenv("SAN={$dn['subjectAltName']}"); // subjectAltName can be set _only_ via configuration file
		$cert_type .= '_san';
		unset($dn['subjectAltName']);
	}

	$args = array(
		"x509_extensions" => $cert_type,
		"digest_alg" => $digest_alg,
		"encrypt_key" => false);
	if ($keytype == 'ECDSA') {
		$args["curve_name"] = $ecname;
		$args["private_key_type"] = OPENSSL_KEYTYPE_EC;
	} else {
		$args["private_key_bits"] = (int)$keylen;
		$args["private_key_type"] = OPENSSL_KEYTYPE_RSA;
	}

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) {
		return false;
	}

	// If this is a self-signed cert, blank out the CA and sign with the cert's key
	if ($type == "self-signed") {
		$ca           = null;
		$ca_res_crt   = null;
		$ca_res_key   = $res_key;
		$ca_serial    = cert_get_random_serial();
		$cert['type'] = "server";
	}

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) {
		return false;
	}

	// sign the certificate using an internal CA
	$res_crt = openssl_csr_sign($res_csr, $ca_res_crt, $ca_res_key, $lifetime,
				 $args, $ca_serial);
	if (!$res_crt) {
		return false;
	}

	// export our certificate data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt)) {
		return false;
	}

	// return our certificate information
	$cert['crt'] = base64_encode($str_crt);
	$cert['prv'] = base64_encode($str_key);

	return true;
}

function csr_generate(& $cert, $keylen, $dn, $type = "user", $digest_alg = "sha256", $keytype = "RSA", $ecname = "prime256v1") {

	$cert_type = cert_type_config_section($type);

	// in case of using Subject Alternative Names use other sections (with postfix '_san')
	// pass subjectAltName over environment variable 'SAN'
	if ($dn['subjectAltName']) {
		putenv("SAN={$dn['subjectAltName']}"); // subjectAltName can be set _only_ via configuration file
		$cert_type .= '_san';
		unset($dn['subjectAltName']);
	}

	$args = array(
		"x509_extensions" => $cert_type,
		"req_extensions" => "req_{$cert_type}",
		"digest_alg" => $digest_alg,
		"encrypt_key" => false);
	if ($keytype == 'ECDSA') {
		$args["curve_name"] = $ecname;
		$args["private_key_type"] = OPENSSL_KEYTYPE_EC;
	} else {
		$args["private_key_bits"] = (int)$keylen;
		$args["private_key_type"] = OPENSSL_KEYTYPE_RSA;
	}

	// generate a new key pair
	$res_key = openssl_pkey_new($args);
	if (!$res_key) {
		return false;
	}

	// generate a certificate signing request
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	if (!$res_csr) {
		return false;
	}

	// export our request data
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_csr_export($res_csr, $str_csr)) {
		return false;
	}

	// return our request information
	$cert['csr'] = base64_encode($str_csr);
	$cert['prv'] = base64_encode($str_key);

	return true;
}

function csr_sign($csr, & $ca, $duration, $type = "user", $altnames, $digest_alg = "sha256") {
	global $config;
	$old_err_level = error_reporting(0);

	// Gather the information required for signed cert
	$ca_str_crt = base64_decode($ca['crt']);
	$ca_str_key = base64_decode($ca['prv']);
	$ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
	if (!$ca_res_key) {
		return false;
	}

	/* Get the next available CA serial number. */
	$ca_serial = ca_get_next_serial($ca);

	$cert_type = cert_type_config_section($type);

	if (!empty($altnames)) {
		putenv("SAN={$altnames}"); // subjectAltName can be set _only_ via configuration file
		$cert_type .= '_san';
	}

	$args = array(
		"x509_extensions" => $cert_type,
		"digest_alg" => $digest_alg,
		"req_extensions" => "req_{$cert_type}"
	);

	// Sign the new cert and export it in x509 format
	openssl_x509_export(openssl_csr_sign($csr, $ca_str_crt, $ca_str_key, $duration, $args, $ca_serial), $n509);
	error_reporting($old_err_level);

	return $n509;
}

function csr_complete(& $cert, $str_crt) {
	$str_key = base64_decode($cert['prv']);
	cert_import($cert, $str_crt, $str_key);
	unset($cert['csr']);
	return true;
}

function csr_get_subject($str_crt, $decode = true) {

	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}

	$components = openssl_csr_get_subject($str_crt);

	if (empty($components) || !is_array($components)) {
		return "unknown";
	}

	ksort($components);
	foreach ($components as $a => $v) {
		if (!strlen($subject)) {
			$subject = "{$a}={$v}";
		} else {
			$subject = "{$a}={$v}, {$subject}";
		}
	}

	return $subject;
}

function cert_get_subject($str_crt, $decode = true) {

	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}

	$inf_crt = openssl_x509_parse($str_crt);
	$components = $inf_crt['subject'];

	if (empty($components) || !is_array($components)) {
		return "unknown";
	}

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

	if (!is_array($components)) {
		return;
	}

	$subject_array = array();

	foreach ($components as $a => $v) {
		$subject_array[] = array('a' => $a, 'v' => $v);
	}

	return $subject_array;
}

function cert_get_subject_hash($crt) {
	$str_crt = base64_decode($crt);
	$inf_crt = openssl_x509_parse($str_crt);
	return $inf_crt['subject'];
}

function cert_get_sans($str_crt, $decode = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$sans = array();
	$crt_details = openssl_x509_parse($str_crt);
	if (!empty($crt_details['extensions']['subjectAltName'])) {
		$sans = explode(',', $crt_details['extensions']['subjectAltName']);
	}
	return $sans;
}

function cert_get_issuer($str_crt, $decode = true) {

	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}

	$inf_crt = openssl_x509_parse($str_crt);
	$components = $inf_crt['issuer'];

	if (empty($components) || !is_array($components)) {
		return "unknown";
	}

	ksort($components);
	foreach ($components as $a => $v) {
		if (is_array($v)) {
			ksort($v);
			foreach ($v as $w) {
				$aissuer = "{$a}={$w}";
				$issuer = (strlen($issuer)) ? "{$aissuer}, {$issuer}" : $aissuer;
			}
		} else {
			$aissuer = "{$a}={$v}";
			$issuer = (strlen($issuer)) ? "{$aissuer}, {$issuer}" : $aissuer;
		}
	}

	return $issuer;
}

/* Works for both RSA and ECC (crt) and key (prv) */
function cert_get_publickey($str_crt, $decode = true, $type = "crt") {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$certfn = tempnam('/tmp', 'CGPK');
	file_put_contents($certfn, $str_crt);
	switch ($type) {
		case 'prv':
			exec("/usr/bin/openssl pkey -in {$certfn} -pubout", $out);
			break;
		case 'crt':
			exec("/usr/bin/openssl x509 -in {$certfn} -inform pem -noout -pubkey", $out);
			break;
		case 'csr':
			exec("/usr/bin/openssl req -in {$certfn} -inform pem -noout -pubkey", $out);
			break;
		default:
			$out = array();
			break;
	}
	unlink($certfn);
	return implode("\n", $out);
}

function cert_get_purpose($str_crt, $decode = true) {
	$extended_oids = array(
		"1.3.6.1.5.5.8.2.2" => "IP Security IKE Intermediate",
	);
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$crt_details = openssl_x509_parse($str_crt);
	$purpose = array();
	if (!empty($crt_details['extensions']['keyUsage'])) {
		$purpose['ku'] = explode(',', $crt_details['extensions']['keyUsage']);
		foreach ($purpose['ku'] as & $ku) {
			$ku = trim($ku);
			if (array_key_exists($ku, $extended_oids)) {
				$ku = $extended_oids[$ku];
			}
		}
	} else {
		$purpose['ku'] = array();
	}
	if (!empty($crt_details['extensions']['extendedKeyUsage'])) {
		$purpose['eku'] = explode(',', $crt_details['extensions']['extendedKeyUsage']);
		foreach ($purpose['eku'] as & $eku) {
			$eku = trim($eku);
			if (array_key_exists($eku, $extended_oids)) {
				$eku = $extended_oids[$eku];
			}
		}
	} else {
		$purpose['eku'] = array();
	}
	$purpose['ca'] = (stristr($crt_details['extensions']['basicConstraints'], 'CA:TRUE') === false) ? 'No': 'Yes';
	$purpose['server'] = (in_array('TLS Web Server Authentication', $purpose['eku'])) ? 'Yes': 'No';

	return $purpose;
}

function cert_get_ocspstaple($str_crt, $decode = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$crt_details = openssl_x509_parse($str_crt);
	if (($crt_details['extensions']['tlsfeature'] == "status_request") ||
	    !empty($crt_details['extensions']['1.3.6.1.5.5.7.1.24'])) {
		return true;
	}
	return false;
}

function cert_format_date($validTS, $validTS_time_t, $outputstring = true) {
	$now = new DateTime("now");

	/* Try to create a DateTime object from the full time string */
	$date = DateTime::createFromFormat('ymdHis', rtrim($validTS, 'Z'), new DateTimeZone('Z'));
	/* If that failed, try using a four digit year */
	if ($date === false) {
		$date = DateTime::createFromFormat('YmdHis', rtrim($validTS, 'Z'), new DateTimeZone('Z'));
	}
	/* If that failed, try to create it from the UNIX timestamp */
	if (($date === false) && (!empty($validTS_time_t))) {
		$date = new DateTime('@' . $validTS_time_t, new DateTimeZone('Z'));
	}
	/* If we have a valid DateTime object, format it in a nice way */
	if ($date !== false) {
		$date->setTimezone($now->getTimeZone());
		if ($outputstring) {
			$date = $date->format(DateTimeInterface::RFC2822);
		}
	}
	return $date;
}

function cert_get_dates($str_crt, $decode = true, $outputstring = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$crt_details = openssl_x509_parse($str_crt);

	$start = cert_format_date($crt_details['validFrom'], $crt_details['validFrom_time_t'], $outputstring);
	$end   = cert_format_date($crt_details['validTo'], $crt_details['validTo_time_t'], $outputstring);

	return array($start, $end);
}

function cert_get_serial($str_crt, $decode = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$crt_details = openssl_x509_parse($str_crt);
	if (isset($crt_details['serialNumber'])) {
		return $crt_details['serialNumber'];
	} else {
		return NULL;
	}
}

function cert_get_sigtype($str_crt, $decode = true) {
	if ($decode) {
		$str_crt = base64_decode($str_crt);
	}
	$crt_details = openssl_x509_parse($str_crt);

	$signature = array();
	if (isset($crt_details['signatureTypeSN']) && !empty($crt_details['signatureTypeSN'])) {
		$signature['shortname'] = $crt_details['signatureTypeSN'];
	}
	if (isset($crt_details['signatureTypeLN']) && !empty($crt_details['signatureTypeLN'])) {
		$signature['longname'] = $crt_details['signatureTypeLN'];
	}
	if (isset($crt_details['signatureTypeNID']) && !empty($crt_details['signatureTypeNID'])) {
		$signature['nid'] = $crt_details['signatureTypeNID'];
	}

	return $signature;
}

function is_openvpn_server_ca($caref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-server'])) {
		return;
	}
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if ($ovpns['caref'] == $caref) {
			return true;
		}
	}
	return false;
}

function is_openvpn_client_ca($caref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-client'])) {
		return;
	}
	foreach ($config['openvpn']['openvpn-client'] as $ovpnc) {
		if ($ovpnc['caref'] == $caref) {
			return true;
		}
	}
	return false;
}

function is_ipsec_peer_ca($caref) {
	global $config;
	if (!is_array($config['ipsec']['phase1'])) {
		return;
	}
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if ($ipsec['caref'] == $caref) {
			return true;
		}
	}
	return false;
}

function is_ldap_peer_ca($caref) {
	global $config;
	if (!is_array($config['system']['authserver'])) {
		return;
	}
	foreach ($config['system']['authserver'] as $authserver) {
		if (($authserver['ldap_caref'] == $caref) &&
		    ($authserver['ldap_urltype'] != 'Standard TCP')) {
			return true;
		}
	}
	return false;
}

function ca_in_use($caref) {
	return (is_openvpn_server_ca($caref) ||
		is_openvpn_client_ca($caref) ||
		is_ipsec_peer_ca($caref) ||
		is_ldap_peer_ca($caref));
}

function is_user_cert($certref) {
	global $config;
	if (!is_array($config['system']['user'])) {
		return;
	}
	foreach ($config['system']['user'] as $user) {
		if (!is_array($user['cert'])) {
			continue;
		}
		foreach ($user['cert'] as $cert) {
			if ($certref == $cert) {
				return true;
			}
		}
	}
	return false;
}

function is_openvpn_server_cert($certref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-server'])) {
		return;
	}
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if ($ovpns['certref'] == $certref) {
			return true;
		}
	}
	return false;
}

function is_openvpn_client_cert($certref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-client'])) {
		return;
	}
	foreach ($config['openvpn']['openvpn-client'] as $ovpnc) {
		if ($ovpnc['certref'] == $certref) {
			return true;
		}
	}
	return false;
}

function is_ipsec_cert($certref) {
	global $config;
	if (!is_array($config['ipsec']['phase1'])) {
		return;
	}
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if ($ipsec['certref'] == $certref) {
			return true;
		}
	}
	return false;
}

function is_webgui_cert($certref) {
	global $config;
	if (($config['system']['webgui']['ssl-certref'] == $certref) &&
	    ($config['system']['webgui']['protocol'] != "http")) {
		return true;
	}
}

function is_package_cert($certref) {
	$pluginparams = array();
	$pluginparams['type'] = 'certificates';
	$pluginparams['event'] = 'used_certificates';

	$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);

	/* Check if any package is using certificate */
	foreach ($certificates_used_by_packages as $name => $package) {
		if (is_array($package['certificatelist'][$certref]) &&
		    isset($package['certificatelist'][$certref]) > 0) {
			return true;
		}
	}
}

function is_captiveportal_cert($certref) {
	global $config;
	if (!is_array($config['captiveportal'])) {
		return;
	}
	foreach ($config['captiveportal'] as $portal) {
		if (isset($portal['enable']) && isset($portal['httpslogin']) && ($portal['certref'] == $certref)) {
			return true;
		}
	}
	return false;
}

function is_unbound_cert($certref) {
	global $config;
	if (($config['unbound']['sslcertref'] == $certref) &&
	    isset($config['unbound']['enablessl']) &&
	    isset($config['unbound']['enable'])) {
		return true;
	}
}

function cert_in_use($certref) {

	return (is_webgui_cert($certref) ||
		is_user_cert($certref) ||
		is_openvpn_server_cert($certref) ||
		is_openvpn_client_cert($certref) ||
		is_ipsec_cert($certref) ||
		is_captiveportal_cert($certref) ||
		is_unbound_cert($certref) ||
		is_package_cert($certref));
}

function cert_usedby_description($refid, $certificates_used_by_packages) {
	$result = "";
	if (is_array($certificates_used_by_packages)) {
		foreach ($certificates_used_by_packages as $name => $package) {
			if (isset($package['certificatelist'][$refid])) {
				$hint = "" ;
				if (is_array($package['certificatelist'][$refid])) {
					foreach ($package['certificatelist'][$refid] as $cert_used) {
						$hint = $hint . $cert_used['usedby']."\n";
					}
				}
				$count = count($package['certificatelist'][$refid]);
				$result .= "<div title='".htmlspecialchars($hint)."'>";
				$result .= htmlspecialchars($package['pkgname'])." ($count)<br />";
				$result .= "</div>";
			}
		}
	}
	return $result;
}

/* Detect a rollover at 2038 on some platforms (e.g. ARM)
 * See: https://redmine.pfsense.org/issues/9098 */
function cert_get_max_lifetime() {
	global $cert_max_lifetime;
	$max = $cert_max_lifetime;

	$current_time = time();
	while ((int)($current_time + ($max * 24 * 60 * 60)) < 0) {
		$max--;
	}
	return min($max, $cert_max_lifetime);
}

function crl_create(& $crl, $caref, $name, $serial = 0, $lifetime = 3650) {
	global $config;
	$max_lifetime = cert_get_max_lifetime();
	$ca =& lookup_ca($caref);
	if (!$ca) {
		return false;
	}
	$crl['descr'] = $name;
	$crl['caref'] = $caref;
	$crl['serial'] = $serial;
	$crl['lifetime'] = ($lifetime > $max_lifetime) ? $max_lifetime : $lifetime;
	$crl['cert'] = array();
	$config['crl'][] = $crl;
	return $crl;
}

function crl_update(& $crl) {
	require_once('ASN1.php');
	require_once('ASN1_UTF8STRING.php');
	require_once('ASN1_ASCIISTRING.php');
	require_once('ASN1_BITSTRING.php');
	require_once('ASN1_BOOL.php');
	require_once('ASN1_GENERALTIME.php');
	require_once('ASN1_INT.php');
	require_once('ASN1_ENUM.php');
	require_once('ASN1_NULL.php');
	require_once('ASN1_OCTETSTRING.php');
	require_once('ASN1_OID.php');
	require_once('ASN1_SEQUENCE.php');
	require_once('ASN1_SET.php');
	require_once('ASN1_SIMPLE.php');
	require_once('ASN1_TELETEXSTRING.php');
	require_once('ASN1_UTCTIME.php');
	require_once('OID.php');
	require_once('X509.php');
	require_once('X509_CERT.php');
	require_once('X509_CRL.php');

	global $config;
	$max_lifetime = cert_get_max_lifetime();
	$ca =& lookup_ca($crl['caref']);
	if (!$ca) {
		return false;
	}
	// If we have text but no certs, it was imported and cannot be updated.
	if (($crl["method"] != "internal") && (!empty($crl['text']) && empty($crl['cert']))) {
		return false;
	}
	$crl['serial']++;
	$ca_cert = \Ukrbublik\openssl_x509_crl\X509::pem2der(base64_decode($ca['crt']));
	$ca_pkey = openssl_pkey_get_private(base64_decode($ca['prv']));

	$crlconf = array(
		'no' => $crl['serial'],
		'version' => 2,
		'days' => ($crl['lifetime'] > $max_lifetime) ? $max_lifetime : $crl['lifetime'],
		'alg' => OPENSSL_ALGO_SHA1,
		'revoked' => array()
	);

	if (is_array($crl['cert']) && (count($crl['cert']) > 0)) {
		foreach ($crl['cert'] as $cert) {
			/* Determine the serial number to revoke */
			if (isset($cert['serial'])) {
				$serial = $cert['serial'];
			} elseif (isset($cert['crt'])) {
				$serial = cert_get_serial($cert['crt'], true);
			} else {
				continue;
			}
			$crlconf['revoked'][] = array(
				'serial' => $serial,
				'rev_date' => $cert['revoke_time'],
				'reason' => ($cert['reason'] == -1) ? null : (int) $cert['reason'],
			);
		}
	}

	$crl_data = \Ukrbublik\openssl_x509_crl\X509_CRL::create($crlconf, $ca_pkey, $ca_cert);
	$crl['text'] = base64_encode(\Ukrbublik\openssl_x509_crl\X509::der2pem4crl($crl_data));

	return $crl['text'];
}

function cert_revoke($cert, & $crl, $reason = OCSP_REVOKED_STATUS_UNSPECIFIED) {
	global $config;
	if (is_cert_revoked($cert, $crl['refid'])) {
		return true;
	}
	// If we have text but no certs, it was imported and cannot be updated.
	if (!is_crl_internal($crl)) {
		return false;
	}

	if (!is_array($cert)) {
		/* If passed a not an array but a serial string, set it up as an
		 * array with the serial number defined */
		$rcert = array();
		$rcert['serial'] = $cert;
	} else {
		/* If passed a certificate entry, read out the serial and store
		 * it separately. */
		$rcert = $cert;
		$rcert['serial'] = cert_get_serial($cert['crt']);
	}
	$rcert['reason'] = $reason;
	$rcert['revoke_time'] = time();
	$crl['cert'][] = $rcert;
	crl_update($crl);
	return true;
}

function cert_unrevoke($cert, & $crl) {
	global $config;
	if (!is_crl_internal($crl)) {
		return false;
	}

	$serial = crl_get_entry_serial($cert);

	foreach ($crl['cert'] as $id => $rcert) {
		/* Check for a match by refid, name, or serial number */
		if (($rcert['refid'] == $cert['refid']) ||
		    ($rcert['descr'] == $cert['descr']) ||
		    (crl_get_entry_serial($rcert) == $serial)) {
			unset($crl['cert'][$id]);
			if (count($crl['cert']) == 0) {
				// Protect against accidentally switching the type to imported, for older CRLs
				if (!isset($crl['method'])) {
					$crl['method'] = "internal";
				}
				crl_update($crl);
			} else {
				crl_update($crl);
			}
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
	if ((cert_get_issuer($c1, false) == cert_get_issuer($c2, false)) &&
	    (cert_get_subject($c1, false) == cert_get_subject($c2, false)) &&
	    (cert_get_serial($c1, false) == cert_get_serial($c2, false)) &&
	    (cert_get_publickey($c1, false) == cert_get_publickey($c2, false))) {
		return true;
	}
	return false;
}

/****f* certs/crl_get_entry_serial
 * NAME
 *   crl_get_entry_serial - Take a CRL entry and determine the associated serial
 * INPUTS
 *   $entry: CRL certificate list entry to inspect, or serial string
 * RESULT
 *   The requested serial string, if present, or null if it cannot be determined.
 ******/

function crl_get_entry_serial($entry) {
	/* Check the passed entry several ways to determine the serial */
	if (isset($entry['serial']) && (strlen($entry['serial']) > 0)) {
		/* Entry is an array with a viable 'serial' element */
		return $entry['serial'];
	} elseif (isset($entry['crt'])) {
		/* Entry is an array with certificate text which can be used to
		 * determine the serial */
		return cert_get_serial($entry['crt'], true);
	} elseif (cert_validate_serial($entry, false, true) != null) {
		/* Entry is a valid serial string */
		return $entry;
	}
	/* Unable to find or determine a serial number */
	return null;
}

/****f* certs/cert_validate_serial
 * NAME
 *   cert_validate_serial - Validate a given string to test if it can be used as
 *                          a certificate serial.
 * INPUTS
 *   $serial     : Serial number string to test
 *   $returnvalue: Whether to return the parsed value or true/false
 * RESULT
 *   If $returnvalue is true, then the parsed ASN.1 integer value string for
 *     $serial or null if invalid
 *   If $returnvalue is false, then true/false based on whether or not $serial
 *     is valid.
 ******/

function cert_validate_serial($serial, $returnvalue = false, $allowlarge = false) {
	require_once('ASN1.php');
	require_once('ASN1_INT.php');
	/* The ASN.1 parsing function will throw an exception if the value is
	 * invalid, so take advantage of that to catch other error as well. */
	try {
		/* If the serial is not a string, then do not bother with
		 * further tests. */
		if (!is_string($serial)) {
			throw new Exception('Not a string');
		}
		/* Process a hex string */
		if ((substr($serial, 0, 2) == '0x')) {
			/* If the string is hex, then it must contain only
			 * valid hex digits */
			if (!ctype_xdigit(substr($serial, 2))) {
				throw new Exception('Not a valid hex string');
			}
			/* Convert to decimal */
			$serial = base_convert($serial, 16, 10);
		}

		/* Unfortunately, PHP openssl_csr_sign() limits serial numbers to a
		 * PHP integer, so we cannot generate large numbers up to the maximum
		 * allowed ASN.1 size (2^159). We are limited to PHP_INT_MAX --
		 * As such, numbers larger than that limit should be rejected */
		if ($serial > PHP_INT_MAX) {
			throw new Exception('Serial too large for PHP OpenSSL');
		}

		/* Attempt to create an ASN.1 integer, if it fails, an exception will be thrown */
		$asn1serial = new \Ukrbublik\openssl_x509_crl\ASN1_INT( $serial );
		return ($returnvalue) ? $asn1serial->content : true;
	} catch (Exception $ex) {
		/* No mattter what the error is, return null or false depending
		 * on what was requested. */
		return ($returnvalue) ? null : false;
	}
}

/****f* certs/cert_generate_serial
 * NAME
 *   cert_generate_serial - Generate a random positive integer usable as a
 *                          certificate serial number
 * INPUTS
 *   None
 * RESULT
 *   Integer representing an ASN.1 compatible certificate serial number.
 ******/

function cert_generate_serial() {
	/* Use a separate function for this to make it easier to use a better
	 * randomization function in the future. */

	/* Unfortunately, PHP openssl_csr_sign() limits serial numbers to a
	 * PHP integer, so we cannot generate large numbers up to the maximum
	 * allowed ASN.1 size (2^159). We are limited to PHP_INT_MAX */
	return random_int(1, PHP_INT_MAX);
}

/****f* certs/ca_has_serial
 * NAME
 *   ca_has_serial - Check if a serial number is used by any certificate in a given CA
 * INPUTS
 *   $ca    : Certificate Authority to check
 *   $serial: Serial number to check
 * RESULT
 *   true if the serial number is in use by a certificate issued by this CA,
 *   false otherwise.
 ******/

function ca_has_serial($caref, $serial) {
	global $config;

	/* Check certs first -- more likely to find a hit */
	foreach ($config['cert'] as $cert) {
		if (($cert['caref'] == $caref) &&
		    (cert_get_serial($cert['crt'], true) == $serial)) {
			/* If this certificate is issued by the CA in question
			 * and has a matching serial number, stop processing
			 * and return true. */
			return true;
		}
	}

	/* Check the CA iteself */
	$this_ca = lookup_ca($caref);
	$this_serial = cert_get_serial($this_ca['crt'], true);
	if ($serial == $this_serial) {
		return true;
	}

	/* Check other CAs for a match (intermediates signed by this CA) */
	foreach ($config['ca'] as $ca) {
		if (($ca['caref'] == $caref) &&
		    (cert_get_serial($ca['crt'], true) == $serial)) {
			/* If this CA is issued by the CA in question
			 * and has a matching serial number, stop processing
			 * and return true. */
			return true;
		}
	}

	return false;
}

/****f* certs/cert_get_random_serial
 * NAME
 *   cert_get_random_serial - Generate a random certificate serial unique in a CA
 * INPUTS
 *   $caref : Certificate Authority refid to test for serial uniqueness.
 * RESULT
 *   Random serial number which is not in use by any known certificate in a CA
 ******/

function cert_get_random_serial($caref = '') {
	/* Number of attempts to generate a usable serial. Multiple attempts
	 *  are necessary to ensure that the number is usable and unique. */
	$attempts = 10;

	/* Default value, -1 indicates an error */
	$serial = -1;

	for ($i=0; $i < $attempts; $i++) {
		/* Generate a random serial */
		$serial = cert_generate_serial();
		/* Check that the serial number is usable and unique:
		 *  * Cannot be 0
		 *  * Must be a valid ASN.1 serial number
		 *  * Cannot be used by any other certificate on this CA */
		if (($serial != 0) &&
		    cert_validate_serial($serial) &&
		    !ca_has_serial($caref, $serial)) {
			/* If all conditions are met, we have a good serial, so stop. */
			break;
		}
	}
	return $serial;
}

/****f* certs/ca_get_next_serial
 * NAME
 *   ca_get_next_serial - Get the next available serial number for a CA
 * INPUTS
 *   $ca: Reference to a CA entry
 * RESULT
 *   A randomized serial number (if enabled for a CA) or the next sequential value.
 ******/

function ca_get_next_serial(& $ca) {
	$ca_serial = null;
	/* Get a randomized serial if enabled */
	if ($ca['randomserial'] == 'enabled') {
		$ca_serial = cert_get_random_serial($ca['refid']);
	}
	/* Initialize the sequential serial to be safe */
	if (empty($ca['serial'])) {
		$ca['serial'] = 0;
	}
	/* If not using a randomized serial, or randomizing the serial
	 * failed, then fall back to sequential serials. */
	return (empty($ca_serial) || ($ca_serial == -1)) ? ++$ca['serial'] : $ca_serial;
}

/****f* certs/crl_contains_cert
 * NAME
 *   crl_contains_cert - Check if a certificate is present in a CRL
 * INPUTS
 *   $crl : CRL to check
 *   $cert: Certificate to test
 * RESULT
 *   true if the CRL contains the certificate, false otherwise
 ******/

function crl_contains_cert($crl, $cert) {
	global $config;
	if (!is_array($config['crl']) ||
	    !is_array($crl['cert'])) {
		return false;
	}

	/* Find the issuer of this CRL */
	$ca = lookup_ca($crl['caref']);
	$crlissuer = is_array($cert) ? cert_get_subject($ca['crt']) : null;
	$serial = crl_get_entry_serial($cert);

	/* Skip issuer match when sarching by serial instead of certificate */
	$issuer = is_array($cert) ? cert_get_issuer($cert['crt']) : null;

	/* If the requested certificate was not issued by the
	 * same CA as the CRL, then do not bother checking this
	 * CRL. */
	if ($issuer != $crlissuer) {
		return false;
	}

	/* Check CRL entries to see if the certificate serial is revoked */
	foreach ($crl['cert'] as $rcert) {
		if (crl_get_entry_serial($rcert) == $serial) {
			return true;
		}
	}

	/* Certificate was not found in the CRL */
	return false;
}

/****f* certs/is_cert_revoked
 * NAME
 *   is_cert_revoked - Test if a given certificate or serial is revoked
 * INPUTS
 *   $cert  : Certificate entry or serial number to test
 *   $crlref: CRL to check for revoked entries, or empty to check all CRLs
 * RESULT
 *   true if the requested entry is revoked
 *   false if the requested entry is not revoked
 ******/

function is_cert_revoked($cert, $crlref = "") {
	global $config;
	if (!is_array($config['crl'])) {
		return false;
	}

	if (!empty($crlref)) {
		$crl = lookup_crl($crlref);
		return crl_contains_cert($crl, $cert);
	} else {
		if (!is_array($cert)) {
			/* If passed a serial, then it cannot be definitively
			 * matched in this way since we do not know the CA
			 * associated with the bare serial. */
			return null;
		}

		/* Check every CRL in the configuration for a match */
		foreach ($config['crl'] as $crl) {
			if (!is_array($crl['cert'])) {
				continue;
			}
			if (crl_contains_cert($crl, $cert)) {
				return true;
			}
		}
	}
	return false;
}

function is_openvpn_server_crl($crlref) {
	global $config;
	if (!is_array($config['openvpn']['openvpn-server'])) {
		return;
	}
	foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
		if (!empty($ovpns['crlref']) && ($ovpns['crlref'] == $crlref)) {
			return true;
		}
	}
	return false;
}

function is_package_crl($crlref) {
	$pluginparams = array();
	$pluginparams['type'] = 'certificates';
	$pluginparams['event'] = 'used_crl';

	$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);

	/* Check if any package is using CRL */
	foreach ($certificates_used_by_packages as $name => $package) {
		if (is_array($package['certificatelist'][$crlref]) &&
		    (count($package['certificatelist'][$crlref]) > 0)) {
			return true;
		}
	}
}

// Keep this general to allow for future expansion. See cert_in_use() above.
function crl_in_use($crlref) {
	return (is_openvpn_server_crl($crlref) ||
		is_package_crl($crlref));
}

function is_crl_internal($crl) {
	return (!(!empty($crl['text']) && empty($crl['cert'])) || ($crl["method"] == "internal"));
}

function cert_get_cn($crt, $isref = false) {
	/* If this is a certref, not an actual cert, look up the cert first */
	if ($isref) {
		$cert = lookup_cert($crt);
		/* If it's not a valid cert, bail. */
		if (!(is_array($cert) && !empty($cert['crt']))) {
			return "";
		}
		$cert = $cert['crt'];
	} else {
		$cert = $crt;
	}
	$sub = cert_get_subject_array($cert);
	if (is_array($sub)) {
		foreach ($sub as $s) {
			if (strtoupper($s['a']) == "CN") {
				return $s['v'];
			}
		}
	}
	return "";
}

function cert_escape_x509_chars($str, $reverse = false) {
	/* Characters which need escaped when present in x.509 fields.
	 * See https://www.ietf.org/rfc/rfc4514.txt
	 *
	 * The backslash (\) must be listed first in these arrays!
	 */
	$cert_directory_string_special_chars = array('\\', '"', '#', '+', ',', ';', '<', '=', '>');
	$cert_directory_string_special_chars_esc = array('\\\\', '\"', '\#', '\+', '\,', '\;', '\<', '\=', '\>');
	if ($reverse) {
		return str_replace($cert_directory_string_special_chars_esc, $cert_directory_string_special_chars, $str);
	} else {
		/* First unescape and then escape again, to prevent possible double escaping. */
		return str_replace($cert_directory_string_special_chars, $cert_directory_string_special_chars_esc, cert_escape_x509_chars($str, true));
	}
}

function cert_add_altname_type($str) {
	$type = "";
	if (is_ipaddr($str)) {
		$type = "IP";
	} elseif (is_hostname($str, true)) {
		$type = "DNS";
	} elseif (is_URL($str)) {
		$type = "URI";
	} elseif (filter_var($str, FILTER_VALIDATE_EMAIL)) {
		$type = "email";
	}
	if (!empty($type)) {
		return "{$type}:" . cert_escape_x509_chars($str);
	} else {
		return null;
	}
}

function cert_type_config_section($type) {
	switch ($type) {
		case "ca":
			$cert_type = "v3_ca";
			break;
		case "server":
		case "self-signed":
			$cert_type = "server";
			break;
		default:
			$cert_type = "usr_cert";
			break;
	}
	return $cert_type;
}

/****f* certs/is_cert_locally_renewable
 * NAME
 *   is_cert_locally_renewable - Check to see if an existing certificate can be
 *                               renewed by a local internal CA.
 * INPUTS
 *   $cert : The certificate to be tested
 * RESULT
 *   true if the certificate can be locally renewed, false otherwise.
 ******/

function is_cert_locally_renewable($cert) {
	/* If there is no certificate or private key string, this entry is either
	 * invalid or cannot be renewed. */
	if (empty($cert['crt']) || empty($cert['prv'])) {
		return false;
	}

	/* Get subject and issuer values to test for self-signed state */
	$subj = cert_get_subject($cert['crt']);
	$issuer = cert_get_issuer($cert['crt']);

	/* Lookup CA for this certificate */
	$ca = array();
	if (!empty($cert['caref'])) {
		$ca = lookup_ca($cert['caref']);
	}

	/* If the CA exists and we have the private key, or if the cert is
	 *  self-signed, then it can be locally renewed. */
	return ((!empty($ca) && !empty($ca['prv'])) || ($subj == $issuer));
}

/* Strict certificate requirements based on
 * https://redmine.pfsense.org/issues/9825
 */
global $cert_strict_values;
$cert_strict_values = array(
	'max_server_cert_lifetime' => 398,
	'digest_blacklist' => array('md4', 'RSA-MD4',  'md5', 'RSA-MD5', 'md5-sha1',
					'mdc2', 'RSA-MDC2', 'sha1', 'RSA-SHA1',
					'RSA-SHA1-2'),
	'min_private_key_bits' => 2048,
	'ec_curve' => 'prime256v1',
);

/****f* certs/cert_renew
 * NAME
 *   cert_renew - Renew an existing internal CA or certificate
 * INPUTS
 *   $cert : The entry to be renewed (used as a reference so it can be altered directly)
 *   $reusekey : Whether or not to reuse the existing key for the certificate
 *      true: Reuse the existing key (Default)
 *      false: Generate a new key based on current (or enforced minimum) parameters
 *   $strictsecurity : Whether or not to enforce stricter security for specific attributes
 *      true: Enforce maximum lifetime for server certs, minimum digest type, and
 *            minimum private key size. See https://redmine.pfsense.org/issues/9825
 *      false: Use existing values as-is (Default).
 * RESULT
 *   true if successful, false if failure.
 * NOTES
 *   See https://redmine.pfsense.org/issues/9842 for more information on behavior.
 *   Does NOT run write_config(), that must be performed by the caller.
 ******/

function cert_renew(& $cert, $reusekey = true, $strictsecurity = false) {
	global $cert_strict_values, $cert_curve_compatible, $curve_compatible_list;

	/* If there is no certificate or private key string, this entry is either
	 *  invalid or cannot be renewed by this function. */
	if (empty($cert['crt']) || empty($cert['prv'])) {
		return false;
	}

	/* Read certificate information necessary to create a new request */
	$cert_details = openssl_x509_parse(base64_decode($cert['crt']));

	/* No details, must not be valid in some way */
	if (!array($cert_details) || empty($cert_details)) {
		return false;
	}

	$subj = cert_get_subject($cert['crt']);
	$issuer = cert_get_issuer($cert['crt']);
	$purpose = cert_get_purpose($cert['crt']);

	$res_key = openssl_pkey_get_private(base64_decode($cert['prv']));
	$key_details = openssl_pkey_get_details($res_key);

	/* Form a new Distinguished Name from the existing values.
	 * Note: Deprecated/unsupported DN fields may not be carried forward, but
	 *       may be preserved to avoid altering a subject.
	 */
	$subject_map = array(
		'CN' => 'commonName',
		'C' => 'countryName',
		'ST' => 'stateOrProvinceName',
		'L' => 'localityName',
		'O' => 'organizationName',
		'OU' => 'organizationalUnitName',
		'emailAddress' => 'emailAddress', /* deprecated, but commonly found in older entries */
	);
	$dn = array();
	/* This is necessary to ensure the order of subject components is
	 * identical on the old and new certificate. */
	foreach ($cert_details['subject'] as $p => $v) {
		if (array_key_exists($p, $subject_map)) {
			$dn[$subject_map[$p]] = $v;
		}
	}

	/* Test for self-signed or signed by a CA */
	$selfsigned = ($subj == $issuer);

	/* Determine the type if it is not specified directly */
	if (array_key_exists('serial', $cert)) {
		/* If a serial value is present, this must be a CA */
		$cert['type'] = 'ca';
	} elseif (empty($cert['type'])) {
		/* Automatically determine certificate type if unset based on purpose value */
		$cert['type'] = ($purpose['server'] == 'Yes') ? 'server' : 'user';
	}

	/* Convert the internal certificate type to an openssl.cnf section name */
	$cert_type = cert_type_config_section($cert['type']);

	/* Reuse lifetime (convert seconds to days) */
	$lifetime = (int) round(($cert_details['validTo_time_t'] - $cert_details['validFrom_time_t']) / 86400);

	/* If we are enforcing strict security, then cap the lifetime for server certificates */
	if (($cert_type == 'server') && $strictsecurity &&
	    ($lifetime > $cert_strict_values['max_server_cert_lifetime'])) {
		$lifetime = $cert_strict_values['max_server_cert_lifetime'];
	}

	/* Reuse SAN list, or, if empty, add CN as SAN. */
	$sans = str_replace("IP Address", "IP", $cert_details['extensions']['subjectAltName']);
	if (empty($sans)) {
		$sans = cert_add_altname_type($dn['commonName']);
	}

	/* Do not setup SANs if the SAN list is empty (e.g. no SAN list and/or
	 * CN cannot be mapped to a valid SAN type) */
	if (!empty($sans)) {
		if ($cert['type'] != 'ca') {
			$cert_type .= '_san';
		}
		/* subjectAltName can be set _only_ via configuration file, so put the
		 * value into the environment where it will be read from the configuration */
		putenv("SAN={$sans}");
	}

	/* If we are enforcing strict security, then check the digest against a
	 * blacklist of insecure digest methods. */
	$digest_alg = $cert_details['signatureTypeSN'];
	if ($strictsecurity &&
	    (in_array($digest_alg, $cert_strict_values['digest_blacklist']))) {
		$digest_alg = 'sha256';
	}

	/* Validate key type, assume RSA if it cannot be read. */
	if (is_array($key_details) && array_key_exists('type', $key_details)) {
		$private_key_type = $key_details['type'];
	} else {
		$private_key_type = OPENSSL_KEYTYPE_RSA;
	}

	/* Setup certificate and key arguments */
	$args = array(
		"x509_extensions" => $cert_type,
		"digest_alg" => $digest_alg,
		"private_key_type" => $private_key_type,
		"encrypt_key" => false);

	/* If we are enforcing strict security, then ensure the private key size
	 * is at least 2048 bits or NIST P-256 elliptic curve*/
	$private_key_bits = $key_details['bits'];
	if ($strictsecurity) {
		if (($key_details['type'] == OPENSSL_KEYTYPE_RSA) &&
		    ($private_key_bits < $cert_strict_values['min_private_key_bits'])) {
			$private_key_bits = $cert_strict_values['min_private_key_bits'];
			$reusekey = false;
		} else if (!in_array($key_details['ec']['curve_name'], $curve_compatible_list)) {
			$ec_curve = $cert_strict_values['ec_curve'];
			$reusekey = false;
		}
	}

	/* Set key parameters. */
	if ($key_details['type'] ==  OPENSSL_KEYTYPE_RSA) {
		$args['private_key_bits'] = (int)$private_key_bits;
	} else if ($ec_curve) {
		$args['curve_name'] = $ec_curve;
	} else {
		$args['curve_name'] = $key_details['ec']['curve_name'];
	}

	/* Make a new key if necessary */
	if (!$res_key || !$reusekey) {
		$res_key = openssl_pkey_new($args);
		if (!$res_key) {
			return false;
		}
	}

	/* Create a new CSR from derived parameters and key */
	$res_csr = openssl_csr_new($dn, $res_key, $args);
	/* If the CSR could not be created, bail */
	if (!$res_csr) {
		return false;
	}

	if (!empty($cert['caref'])) {
		/* The certificate was signed by a CA, so read the CA details. */
		$ca = & lookup_ca($cert['caref']);
		/* If the referenced CA cannot be found, bail. */
		if (!$ca) {
			return false;
		}
		$ca_str_crt = base64_decode($ca['crt']);
		$ca_str_key = base64_decode($ca['prv']);
		$ca_res_crt = openssl_x509_read($ca_str_crt);
		$ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
		if (!$ca_res_key) {
			/* If the CA key cannot be read, bail. */
			return false;
		}
		/* If the CA does not have a serial number, assume 0. */
		if (empty($ca['serial'])) {
			$ca['serial'] = 0;
		}
		/* Get the next available CA serial number. */
		$ca_serial = ca_get_next_serial($ca);
	} elseif ($selfsigned) {
		/* For self-signed CAs & certificates, set the CA details to self and
		 * use the key for this entry to sign itself.
		 */
		$ca_res_crt   = null;
		$ca_res_key   = $res_key;
		/* Use random serial from this CA/Self-Signed Cert */
		$ca_serial    = cert_get_random_serial($cert['refid']);
	}

	/* Sign the CSR */
	$res_crt = openssl_csr_sign($res_csr, $ca_res_crt, $ca_res_key, $lifetime,
				 $args, $ca_serial);
	/* If the CSR could not be signed, bail */
	if (!$res_crt) {
		return false;
	}

	/* Attempt to read the key and certificate and if that fails, bail */
	if (!openssl_pkey_export($res_key, $str_key) ||
	    !openssl_x509_export($res_crt, $str_crt)) {
		return false;
	}

	/* Load the new certificate string and key into the configuration */
	$cert['crt'] = base64_encode($str_crt);
	$cert['prv'] = base64_encode($str_key);

	return true;
}

/****f* certs/cert_get_all_services
 * NAME
 *   cert_get_all_services - Locate services using a given certificate
 * INPUTS
 *   $refid: The refid of a certificate to check
 * RESULT
 *   array containing the services which use this certificate, including:
 *     webgui: Present and true if the WebGUI uses this certificate. Unset otherwise.
 *     services: Array of service definitions using this certificate, with:
 *       name: Name of the service
 *       extras: Extra information needed by some services, such as OpenVPN or Captive Portal.
 *     packages: Array containing package names using this certificate.
 ******/

function cert_get_all_services($refid) {
	global $config;
	$services = array();
	$services['services'] = array();
	$services['packages'] = array();

	/* Only set if true, otherwise leave unset. */
	if (is_webgui_cert($refid)) {
		$services['webgui'] = true;
	}

	init_config_arr(array('openvpn', 'openvpn-server'));
	init_config_arr(array('openvpn', 'openvpn-client'));
	/* Find all OpenVPN clients and servers which use this certificate */
	foreach(array('server', 'client') as $mode) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $ovpn) {
			if ($ovpn['certref'] == $refid) {
				/* OpenVPN instances are restarted individually,
				 * so we need to note the mode and ID. */
				$services['services'][] = array(
					'name' => 'openvpn',
					'extras' => array(
						'vpnmode' => $mode,
						'id' => $ovpn['vpnid']
					)
				);
			}
		}
	}

	/* If any one IPsec tunnel uses this certificate then the whole service
	 * needs a bump. */
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if (($ipsec['authentication_method'] == 'cert') &&
		    ($ipsec['certref'] == $refid)) {
			$services['services'][] = array('name' => 'ipsec');
			/* Stop after finding one, no need to search for more. */
			break;
		}
	}

	/* Check to see if any HTTPS-enabled Captive Portal zones use this
	 * certificate. */
	init_config_arr(array('captiveportal'));
	foreach ($config['captiveportal'] as $zone => $portal) {
		if (isset($portal['enable']) && isset($portal['httpslogin']) &&
		    ($portal['certref'] == $refid)) {
			/* Captive Portal zones are restarted individually, so
			 * we need to note the zone name. */
			$services['services'][] = array(
				'name' => 'captiveportal',
				'extras' => array(
					'zone' => $zone,
				)
			);
		}
	}

	/* Locate any packages using this certificate */
	$pkgcerts = pkg_call_plugins('plugin_certificates', array('type' => 'certificates', 'event' => 'used_certificates'));
	foreach ($pkgcerts as $name => $package) {
		if (is_array($package['certificatelist'][$refid]) &&
		    isset($package['certificatelist'][$refid]) > 0) {
			$services['packages'][] = $name;
		}
	}

	return $services;
}

/****f* certs/ca_get_all_services
 * NAME
 *   ca_get_all_services - Locate services using a given certificate authority or its decendents
 * INPUTS
 *   $refid: The refid of a certificate authority to check
 * RESULT
 *   array containing the services which use this certificate authority, including:
 *     webgui: Present and true if the WebGUI uses this certificate. Unset otherwise.
 *     services: Array of service definitions using this certificate, with:
 *       name: Name of the service
 *       extras: Extra information needed by some services, such as OpenVPN or Captive Portal.
 *     packages: Array containing package names using this certificate.
 * NOTES
 *   This searches recursively to find entries using this CA as well as intermediate
 *   CAs and certificates signed by this CA, and returns a single set of all services.
 *   This avoids restarting affected services multiple times when there is overlapping
 *   usage.
 ******/
function ca_get_all_services($refid) {
	global $config;
	$services = array();
	$services['services'] = array();

	init_config_arr(array('openvpn', 'openvpn-server'));
	init_config_arr(array('openvpn', 'openvpn-client'));
	foreach(array('server', 'client') as $mode) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $ovpn) {
			if ($ovpn['caref'] == $refid) {
				$services['services'][] = array(
					'name' => 'openvpn',
					'extras' => array(
						'vpnmode' => $mode,
						'id' => $ovpn['vpnid']
					)
				);
			}
		}
	}
	init_config_arr(array('ipsec', 'phase1'));
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if ($ipsec['certref'] == $refid) {
			break;
		}
	}
	foreach ($config['ipsec']['phase1'] as $ipsec) {
		if (($ipsec['authentication_method'] == 'cert') &&
		    ($ipsec['caref'] == $refid)) {
			$services['services'][] = array('name' => 'ipsec');
			break;
		}
	}

	/* Loop through all certs and get their services as well */
	init_config_arr(array('cert'));
	foreach ($config['cert'] as $cert) {
		if ($cert['caref'] == $refid) {
			$services = array_merge_recursive_unique($services, cert_get_all_services($cert['refid']));
		}
	}

	/* Look for intermediate certs and services */
	init_config_arr(array('ca'));
	foreach ($config['ca'] as $cert) {
		if ($cert['caref'] == $refid) {
			$services = array_merge_recursive_unique($services, ca_get_all_services($cert['refid']));
		}
	}

	return $services;
}

/****f* certs/cert_restart_services
 * NAME
 *   cert_restart_services - Restarts services specific to CA/Certificate usage
 * INPUTS
 *   $services: An array of services returned by cert_get_all_services or ca_get_all_services
 * RESULT
 *   Services in the given array are restarted
 *   returns false if the input is invalid
 *   returns true at the end of execution
 ******/

function cert_restart_services($services) {
	require_once("service-utils.inc");
	/* If the input is not an array, it is invalid. */
	if (!is_array($services)) {
		return false;
	}

	/* Base string to log when restarting a service */
	$restart_string = gettext('Restarting %s %s due to certificate change');

	/* Restart GUI: */
	if ($services['webgui']) {
		ob_flush();
		flush();
		log_error(sprintf($restart_string, gettext('service'), 'WebGUI'));
		send_event("service restart webgui");
	}

	/* Restart other base services: */
	if (is_array($services['services'])) {
		foreach ($services['services'] as $service) {
			switch ($service['name']) {
				case 'openvpn':
					$service_name = "{$service['name']} {$service['extras']['vpnmode']} {$service['extras']['id']}";
					break;
				case 'captiveportal':
					$service_name = "{$service['name']} zone {$service['extras']['zone']}";
					break;
				default:
					$service_name = $service['name'];
			}
			log_error(sprintf($restart_string, gettext('service'), $service_name));
			service_control_restart($service['name'], $service['extras']);
		}
	}

	/* Restart Packages: */
	if (is_array($services['packages'])) {
		foreach ($services['packages'] as $service) {
			log_error(sprintf($restart_string, gettext('package'), $service));
			restart_service($service);
		}
	}
	return true;
}

/****f* certs/cert_get_lifetime
 * NAME
 *   cert_get_lifetime - Returns the number of days the certificate is valid
 * INPUTS
 *   $untilexpire: Boolean
 *     true: The number of days returned is from now until the certificate expiration.
 *     false (default): The number of days returned is the total lifetime of the certificate.
 * RESULT
 *   Integer number of days in the certificate total or remaining lifetime
 ******/

function cert_get_lifetime($cert, $untilexpire = false) {
	/* If the certificate is not valid, bail. */
	if (!is_array($cert) || empty($cert['crt'])) {
		return null;
	}
	/* Read certificate details */
	list($startdate, $enddate) = cert_get_dates($cert['crt'], true, false);

	/* If either of the dates are invalid, there is nothing we can do here. */
	if (($startdate === false) || ($enddate === false)) {
		return false;
	}

	/* Determine which start time to use (now, or cert start) */
	$startdate = ($untilexpire) ? new DateTime("now") : $startdate;

	/* Calculate the requested intervals */
	$interval = $startdate->diff($enddate);

	/* DateTime diff is always positive, check if we need to negate the result. */
	return ($startdate > $enddate) ? -1 * $interval->days : $interval->days;
}

/****f* certs/cert_analyze_lifetime
 * NAME
 *   cert_analyze_lifetime - Analyze a certificate lifetime for expiration notices
 * INPUTS
 *   $expiredays: Number of days until the certificate expires (See cert_get_lifetime())
 * RESULT
 *   An array of two entries:
 *   0/$lrclass: A bootstrap name for use with classes like text-<x>
 *   1/$expstring: A text analysis describing the expiration timeframe.
 ******/

function cert_analyze_lifetime($expiredays) {
	global $config, $g;
	/* Number of days at which to warn of expiration. */
	init_config_arr(array('notifications', 'certexpire'));
	if (!isset($config['notifications']['certexpire']['expiredays']) ||
	    empty($config['notifications']['certexpire']['expiredays'])) {
		$warning_days = $g['default_cert_expiredays'];
	} else {
		$warning_days = $config['notifications']['certexpire']['expiredays'];
	}

	if ($expiredays > $warning_days) {
		/* Not expiring soon */
		$lrclass = 'normal';
		$expstring = gettext("%d %s until expiration");
	} elseif ($expiredays >= 0) {
		/* Still valid but expiring soon */
		$lrclass = 'warning';
		$expstring = gettext("Expiring soon, in %d %s");
	} else {
		/* Certificate has expired */
		$lrclass = 'danger';
		$expstring = gettext("Expired %d %s ago");
	}
	$days = (abs($expiredays) == 1) ? gettext('day') : gettext('days');
	$expstring = sprintf($expstring, abs($expiredays), $days);
	return array($lrclass, $expstring);
}

/****f* certs/cert_print_dates
 * NAME
 *   cert_print_dates - Print the start and end timestamps for the given certificate
 * INPUTS
 *   $cert: CA or Cert entry for which the dates will be printed
 * RESULT
 *   Returns null if the passed entry is invalid
 *   Otherwise, outputs the dates to the user with formatting.
 ******/

function cert_print_dates($cert) {
	/* If the certificate is not valid, bail. */
	if (!is_array($cert) || empty($cert['crt'])) {
		return null;
	}
	/* Attempt to extract the dates from the certificate */
	list($startdate, $enddate) = cert_get_dates($cert['crt']);
	/* If either of the timestamps are empty, then do not print anything.
	 * The entry may not be valid or it may just be missing date information */
	if (empty($startdate) || empty($enddate)) {
		return null;
	}
	/* Get the expiration days */
	$expiredays = cert_get_lifetime($cert, true);
	/* Analyze the lifetime value */
	list($lrclass, $expstring) = cert_analyze_lifetime($expiredays);
	/* Output the dates, with a tooltip showing days until expiration, and
	 * a visual indication of warning/expired status. */
	?>
	<br />
	<small>
	<?=gettext("Valid From")?>: <b><?=$startdate ?></b><br />
	<?=gettext("Valid Until")?>:
	<span class="text-<?=$lrclass?>" data-toggle="tooltip" data-placement="bottom" title="<?= $expstring ?>">
	<b><?=$enddate ?></b>
	</span>
	</small>
	<?php
}

/****f* certs/cert_print_infoblock
 * NAME
 *   cert_print_infoblock - Print an information block containing certificate details
 * INPUTS
 *   $cert: CA or Cert entry for which the information will be printed
 * RESULT
 *   Returns null if the passed entry is invalid
 *   Otherwise, outputs information to the user with formatting.
 ******/

function cert_print_infoblock($cert) {
	/* If the certificate is not valid, bail. */
	if (!is_array($cert) || empty($cert['crt'])) {
		return null;
	}
	/* Variable to hold the formatted information */
	$certextinfo = "";

	/* Serial number */
	$cert_details = openssl_x509_parse(base64_decode($cert['crt']));
	if (isset($cert_details['serialNumber']) && (strlen($cert_details['serialNumber']) > 0)) {
		$certextinfo .= '<b>' . gettext("Serial: ") . '</b> ';
		$certextinfo .= htmlspecialchars(cert_escape_x509_chars($cert_details['serialNumber'], true));
		$certextinfo .= '<br/>';
	}

	/* Digest type */
	$certsig = cert_get_sigtype($cert['crt']);
	if (is_array($certsig) && !empty($certsig) && !empty($certsig['shortname'])) {
		$certextinfo .= '<b>' . gettext("Signature Digest: ") . '</b> ';
		$certextinfo .= htmlspecialchars(cert_escape_x509_chars($certsig['shortname'], true));
		$certextinfo .= '<br/>';
	}

	/* Subject Alternative Name (SAN) list */
	$sans = cert_get_sans($cert['crt']);
	if (is_array($sans) && !empty($sans)) {
		$certextinfo .= '<b>' . gettext("SAN: ") . '</b> ';
		$certextinfo .= htmlspecialchars(implode(', ', cert_escape_x509_chars($sans, true)));
		$certextinfo .= '<br/>';
	}

	/* Key usage */
	$purpose = cert_get_purpose($cert['crt']);
	if (is_array($purpose) && !empty($purpose['ku'])) {
		$certextinfo .= '<b>' . gettext("KU: ") . '</b> ';
		$certextinfo .= htmlspecialchars(implode(', ', $purpose['ku']));
		$certextinfo .= '<br/>';
	}

	/* Extended key usage */
	if (is_array($purpose) && !empty($purpose['eku'])) {
		$certextinfo .= '<b>' . gettext("EKU: ") . '</b> ';
		$certextinfo .= htmlspecialchars(implode(', ', $purpose['eku']));
		$certextinfo .= '<br/>';
	}

	/* OCSP / Must Staple */
	if (cert_get_ocspstaple($cert['crt'])) {
		$certextinfo .= '<b>' . gettext("OCSP: ") . '</b> ';
		$certextinfo .= gettext("Must Staple");
		$certextinfo .= '<br/>';
	}

	/* Private key information */
	if (!empty($cert['prv'])) {
		$res_key = openssl_pkey_get_private(base64_decode($cert['prv']));
		$certextinfo .= '<b>' . gettext("Key Type: ") . '</b> ';
		if ($res_key) {
			$key_details = openssl_pkey_get_details($res_key);
			/* Key type (RSA or EC) */
			if ($key_details['type'] == OPENSSL_KEYTYPE_RSA) {
				/* RSA Key size */
				$certextinfo .= 'RSA<br/>';
				$certextinfo .= '<b>' . gettext("Key Size: ") . '</b> ';
				$certextinfo .= $key_details['bits'] . '<br/>';
			} else {
				/* Elliptic curve (EC) key curve name */
				$certextinfo .= 'ECDSA<br/>';
				$curve = cert_get_pkey_curve($cert['prv']);
				if (!empty($curve)) {
					$certextinfo .= '<b>' . gettext("Elliptic curve name:") . ' </b>';
					$certextinfo .= $curve . '<br/>';
				}
			}
		} else {
			$certextinfo .= '<i>' . gettext("Unknown (Key could not be parsed)") . '</i><br/>';
		}
	}

	/* Distinguished name (DN) */
	if (!empty($cert_details['name'])) {
		$certextinfo .= '<b>' . gettext("DN: ") . '</b> ';
		/* UTF8 DN support, see https://redmine.pfsense.org/issues/12041 */
		$certdnstring = preg_replace_callback('/\\\\x([0-9A-F]{2})/', function ($a) { return pack('H*', $a[1]); }, $cert_details['name']);
		$certextinfo .= htmlspecialchars(cert_escape_x509_chars($certdnstring, true));
		$certextinfo .= '<br/>';
	}

	/* Hash value */
	if (!empty($cert_details['hash'])) {
		$certextinfo .= '<b>' . gettext("Hash: ") . '</b> ';
		$certextinfo .= htmlspecialchars(cert_escape_x509_chars($cert_details['hash'], true));
		$certextinfo .= '<br/>';
	}

	/* Subject Key Identifier (SKID) */
	if (is_array($cert_details["extensions"]) && !empty($cert_details["extensions"]["subjectKeyIdentifier"])) {
		$certextinfo .= '<b>' . gettext("Subject Key ID: ") . '</b> ';
		$certextinfo .= htmlspecialchars(cert_escape_x509_chars($cert_details["extensions"]["subjectKeyIdentifier"], true));
		$certextinfo .= '<br/>';
	}

	/* Authority Key Identifier (AKID) */
	if (is_array($cert_details["extensions"]) && !empty($cert_details["extensions"]["authorityKeyIdentifier"])) {
		$certextinfo .= '<b>' . gettext("Authority Key ID: ") . '</b> ';
		$certextinfo .= str_replace("\n", '<br/>', htmlspecialchars(cert_escape_x509_chars($cert_details["extensions"]["authorityKeyIdentifier"], true)));
		$certextinfo .= '<br/>';
	}

	/* Total Lifetime (days from cert start to end) */
	$lifetime = cert_get_lifetime($cert);
	if ($lifetime !== false) {
		$certextinfo .= '<b>' . gettext("Total Lifetime: ") . '</b> ';
		$certextinfo .= sprintf("%d %s", $lifetime, (abs($lifetime) == 1) ? gettext('day') : gettext('days'));
		$certextinfo .= '<br/>';

		/* Lifetime before certificate expires (days from now to end) */
		$expiredays = cert_get_lifetime($cert, true);
		list($lrclass, $expstring) = cert_analyze_lifetime($expiredays);
		$certextinfo .= '<b>' . gettext("Lifetime Remaining: ") . '</b> ';
		$certextinfo .= "<span class=\"text-{$lrclass}\">{$expstring}</span>";
		$certextinfo .= '<br/>';
	}

	if ($purpose['ca'] == 'Yes') {
		/* CA Trust store presence */
		$certextinfo .= '<b>' . gettext("Trust Store: ") . '</b> ';
		$certextinfo .= (isset($cert['trust']) && ($cert['trust'] == "enabled")) ? gettext('Included') : gettext('Excluded');
		$certextinfo .= '<br/>';

		if (!empty($cert['prv'])) {
			/* CA Next/Randomize Serial */
			$certextinfo .= '<b>' . gettext("Next Serial: ") . '</b> ';
			$certextinfo .= (isset($cert['randomserial']) && ($cert['randomserial'] == "enabled")) ? gettext('Randomized') : $cert['serial'];
			$certextinfo .= '<br/>';
		}
	}

	/* Output the infoblock */
	if (!empty($certextinfo)) { ?>
		<div class="infoblock">
		<? print_info_box($certextinfo, 'info', false); ?>
		</div>
	<?php
	}
}

/****f* certs/cert_notify_expiring
 * NAME
 *   cert_notify_expiring - Notify admin about expiring certificates
 * INPUTS
 *   None
 * RESULT
 *   File a notice containing expiring certificate information, which is then
 *   logged, displayed in the GUI, and sent via e-mail (if enabled).
 ******/

function cert_notify_expiring() {
	global $config;

	/* If certificate expiration notifications are disabled, there is nothing to do. */
	init_config_arr(array('notifications', 'certexpire'));
	if ($config['notifications']['certexpire']['enable'] == "disabled") {
		return;
	}

	$notifications = array();

	/* Check all CA and Cert entries at once */
	init_config_arr(array('ca'));
	init_config_arr(array('cert'));
	$all_certs = array_merge_recursive($config['ca'], $config['cert']);

	foreach ($all_certs as $cert) {
		if (empty($cert)) {
			continue;
		}
		/* Proceed only for not revoked certificate if ignore setting enabled */
		if (($config['notifications']['certexpire']['ignore_revoked'] == "enabled") && is_cert_revoked($cert)) {
			continue;
		}
		/* Fetch and analyze expiration */
		$expiredays = cert_get_lifetime($cert, true);
		/* If the result is null, then the lifetime data is missing, so skip the invalid entry. */
		if ($expiredays === null) {
			continue;
		}
		list($lrclass, $expstring) = cert_analyze_lifetime($expiredays);
		/* Only notify if the certificate is expiring soon, or has
		 * already expired */
		if ($lrclass != 'normal') {
			$notify_string = (array_key_exists('serial', $cert)) ? gettext('Certificate Authority') : gettext('Certificate');
			$notify_string .= ": {$cert['descr']} ({$cert['refid']}): {$expstring}";
			$notifications[] = $notify_string;
		}
	}
	if (!empty($notifications)) {
		$message = gettext("The following CA/Certificate entries are expiring:") . "\n" .
			implode("\n", $notifications);
		file_notice("Certificate Expiration", $message, "Certificate Manager");
	}
}

/****f* certs/ca_setup_trust_store
 * NAME
 *   ca_setup_trust_store - Setup local CA trust store so that CA entries in the
 *                          configuration may be trusted by the operating system.
 * INPUTS
 *   None
 * RESULT
 *   CAs marked as trusted in the configuration will be setup in the OS trust store.
 ******/

function ca_setup_trust_store() {
	global $config;

	/* This directory is trusted by OpenSSL on FreeBSD by default */
	$trust_store_directory = '/etc/ssl/certs';

	/* Create the directory if it does not already exist, and clean it up if it does. */
	safe_mkdir($trust_store_directory);
	unlink_if_exists("{$trust_store_directory}/*.0");

	init_config_arr(array('ca'));
	foreach ($config['ca'] as $ca) {
		/* If the entry is invalid or is not trusted, skip it. */
		if (!is_array($ca) ||
		    empty($ca['crt']) ||
		    !isset($ca['trust']) ||
		    ($ca['trust'] != "enabled")) {
			continue;
		}

		ca_setup_capath($ca, $trust_store_directory);
	}
}

/****f* certs/ca_setup_capath
 * NAME
 *   ca_setup_capath - Setup CApath structure so that CA chains and related CRLs
 *                     may be written and validated by the -CApath option in
 *                     OpenSSL and other compatible validators.
 * INPUTS
 *   $ca     : A CA (not a refid) to write
 *   $basedir: The directory which will contain the CA structure.
 *   $crl    : A CRL (not a refid) associated with the CA to write.
 *   $refresh: Refresh CRLs -- When true, perform no cleanup and increment suffix
 * RESULT
 *   $basedir is populated with CA and CRL files in a format usable by OpenSSL
 *   CApath. This has the filenames as the CA hash with the CA named <hash>.0
 *   and CRLs named <hash>.r0
 ******/

function ca_setup_capath($ca, $basedir, $crl = "", $refresh = false) {
	global $config;
	/* Check for an invalid CA */
	if (!$ca || !is_array($ca)) {
		return false;
	}
	/* Check for an invalid CRL, but do not consider it fatal if it's wrong */
	if (!$crl || !is_array($crl) || ($crl['caref'] != $ca['refid'])) {
		unset($crl);
	}

	/* Check for an empty base directory, which is invalid */
	if (empty($basedir)) {
		return false;
	}

	/* Ensure that $basedir exists and is a directory */
	if (!is_dir($basedir)) {
		/* If it's a file, remove it, otherwise the directory cannot
		 * be created. */
		if (file_exists($basedir)) {
			@unlink_if_exists($basedir);
		}
		@safe_mkdir($basedir);
	}
	/* Decode the certificate contents */
	$cert_contents = base64_decode($ca['crt']);
	/* Get hash value to use for filename */
	$cert_details = openssl_x509_parse($cert_contents);
	$fprefix = "{$basedir}/{$cert_details['hash']}";


	$ca_filename = "{$fprefix}.0";
	/* Cleanup old CA/CRL files for this hash */
	@unlink_if_exists($ca_filename);
	/* Write CA to base dir and ensure it has correct permissions. */
	file_put_contents($ca_filename, $cert_contents);
	chmod($ca_filename, 0644);
	chown($ca_filename, 'root');
	chgrp($ca_filename, 'wheel');

	/* If there is a CRL, process it. */
	if ($crl) {
		$crl_filename = "{$fprefix}.r";
		if (!$refresh) {
			/* Cleanup old CA/CRL files for this hash */
			@unlink_if_exists("{$crl_filename}*");
		}
		/* Find next suffix based on how many existing files there are (start=0) */
		$crl_filename .= count(glob("{$crl_filename}*"));
		/* Write CRL to base dir and ensure it has correct permissions. */
		file_put_contents($crl_filename, base64_decode($crl['text']));
		chmod($crl_filename, 0644);
		chown($crl_filename, 'root');
		chgrp($crl_filename, 'wheel');
	}

	return true;
}

/****f* certs/cert_get_pkey_curve
 * NAME
 *   cert_get_pkey_curve - Get the ECDSA curve of a private key
 * INPUTS
 *   $pkey  : The private key to check
 *   $decode: true: base64 decode the string before use, false to use as-is.
 * RESULT
 *   false if the private key is not ECDSA or the private key is not present.
 *   Otherwise, the name of the ECDSA curve used for the private key.
 ******/

function cert_get_pkey_curve($pkey, $decode = true) {
	if ($decode) {
		$pkey = base64_decode($pkey);
	}

	/* Attempt to read the private key, and if successful, its details. */
	$res_key = openssl_pkey_get_private($pkey);
	if ($res_key) {
		$key_details = openssl_pkey_get_details($res_key);
		/* If this is an EC key, and the curve name is not empty, return
		 * that curve name. */
		if ($key_details['type'] ==  OPENSSL_KEYTYPE_EC) {
			if (!empty($key_details['ec']['curve_name'])) {
				return $key_details['ec']['curve_name'];
			} else {
				return $key_details['ec']['curve_oid'];
			}
		}
	}

	/* Either the private key could not be read, or this is not an EC certificate. */
	return false;
}

/* Array containing ECDSA curve names allowed in certain contexts. For instance,
 * HTTPS servers and web browsers only support specific curves in TLSv1.3. */
global $cert_curve_compatible, $curve_compatible_list;
$cert_curve_compatible = array(
	/* HTTPS list per TLSv1.3 spec and Mozilla compatibility list */
	'HTTPS' => array('prime256v1', 'secp384r1'),
	/* IPsec/EAP/TLS list per strongSwan docs/issues */
	'IPsec' => array('prime256v1', 'secp384r1', 'secp521r1'),
	/* OpenVPN bug limits usable curves, see https://redmine.pfsense.org/issues/9744 */
	'OpenVPN' => array('prime256v1', 'secp384r1', 'secp521r1'),
);
$curve_compatible_list = array_unique(call_user_func_array('array_merge', $cert_curve_compatible));

/****f* certs/cert_build_curve_list
 * NAME
 *   cert_build_curve_list - Build an option list of ECDSA curves with notations
 *                           about known compatible uses.
 * INPUTS
 *   None
 * RESULT
 *   Returns an option list of OpenSSL EC names with added notes. This can be
 *   used directly in form option selection lists.
 ******/

function cert_build_curve_list() {
	global $cert_curve_compatible;
	/* Get the default list of curve names */
	$openssl_ecnames = openssl_get_curve_names();
	/* Turn this into a hashed array where key==value */
	$curvelist = array_combine($openssl_ecnames, $openssl_ecnames);
	/* Check all known compatible curves and note matches */
	foreach ($cert_curve_compatible as $consumer => $validcurves) {
		/* $consumer will be a name like HTTPS or IPsec
		 * $validcurves will be an array of curves compatible with the consumer */
		foreach ($validcurves as $vc) {
			/* If the valid curve is present in the curve list, add
			 * a note with the consumer name to the value visible to
			 * the user. */
			if (array_key_exists($vc, $curvelist)) {
				$curvelist[$vc] .= " [{$consumer}]";
			}
		}
	}
	return $curvelist;
}

/****f* certs/cert_check_pkey_compatibility
 * NAME
 *   cert_check_pkey_compatibility - Check a private key to see if it can be
 *                                   used in a specific compatible context.
 * INPUTS
 *   $pkey    : The private key to check
 *   $consumer: The consumer name used to validate the curve. See the contents
 *                 of $cert_curve_compatible for details.
 * RESULT
 *   true if the private key may be used in requested area, or if there are no
 *        restrictions.
 *   false if the private key cannot be used.
 ******/

function cert_check_pkey_compatibility($pkey, $consumer) {
	global $cert_curve_compatible;

	/* Read the curve name from the key */
	$curve = cert_get_pkey_curve($pkey);
	/* Return true if any of the following conditions are met:
	 *  * This is not an EC key
	 *  * The private key cannot be read
	 *  * There are no restrictions
	 *  * The requested curve is compatible */
	return (($curve === false) ||
		!array_key_exists($consumer, $cert_curve_compatible) ||
		in_array($curve, $cert_curve_compatible[$consumer]));
}

/****f* certs/cert_build_list
 * NAME
 *   cert_build_list - Build an option list of cert or CA entries, checked
 *                     against a specific consumer name.
 * INPUTS
 *   $type    : 'ca' for certificate authority entries, 'cert' for certificates.
 *   $consumer: The consumer name used to filter certificates out of the result.
 *                 See the contents of $cert_curve_compatible for details.
 *   $selectsource: Then true, outputs in a format usable by select_source in
 *                  packages.
 *   $addnone: When true, a 'none' choice is added to the list.
 * RESULT
 *   Returns an option list of entries with incompatible entries removed. This
 *   can be used directly in form option selection lists.
 * NOTES
 *   This can be expanded in the future to allow for other types of restrictions.
 ******/

function cert_build_list($type = 'cert', $consumer = '', $selectsource = false, $addnone = false) {
	global $config;

	/* Ensure that $type is valid */
	if (!in_array($type, array('ca', 'cert'))) {
		return array();
	}

	/* Initialize arrays */
	init_config_arr(array($type));
	$list = array();

	if ($addnone) {
		if ($selectsource) {
			$list[] = array('refid' => 'none', 'descr' => 'None');
		} else {
			$list['none'] = "None";
		}
	}

	/* Create a hashed array with the certificate refid as the key and
	 * descriptive name as the value. Exclude incompatible certificates. */
	foreach ($config[$type] as $cert) {
		if (empty($cert['prv']) && ($type == 'cert')) {
			continue;
		} else if (cert_check_pkey_compatibility($cert['prv'], $consumer)) {
			if ($selectsource) {
				$list[] = array('refid' => $cert['refid'],
						'descr' => $cert['descr']);
			} else {
				$list[$cert['refid']] = $cert['descr'];
			}
		}
	}

	return $list;
}

?>
