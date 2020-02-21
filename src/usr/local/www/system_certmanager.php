<?php
/*
 * system_certmanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Shrew Soft Inc
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

##|+PRIV
##|*IDENT=page-system-certmanager
##|*NAME=System: Certificate Manager
##|*DESCR=Allow access to the 'System: Certificate Manager' page.
##|*MATCH=system_certmanager.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("certs.inc");
require_once("pfsense-utils.inc");

$cert_methods = array(
	"internal" => gettext("Create an internal Certificate"),
	"import" => gettext("Import an existing Certificate"),
	"external" => gettext("Create a Certificate Signing Request"),
	"sign" => gettext("Sign a Certificate Signing Request")
);

$cert_keylens = array("1024", "2048", "3072", "4096", "6144", "7680", "8192", "15360", "16384");
$cert_keytypes = array("RSA", "ECDSA");
$cert_types = array(
	"server" => "Server Certificate",
	"user" => "User Certificate");

global $cert_altname_types;
global $openssl_digest_algs;
global $cert_strict_values;
$max_lifetime = cert_get_max_lifetime();
$default_lifetime = min(3650, $max_lifetime);
$openssl_ecnames = cert_build_curve_list();
$class = "success";

if (isset($_REQUEST['userid']) && is_numericint($_REQUEST['userid'])) {
	$userid = $_REQUEST['userid'];
}

if (isset($userid)) {
	$cert_methods["existing"] = gettext("Choose an existing certificate");
	init_config_arr(array('system', 'user'));
	$a_user =& $config['system']['user'];
}

init_config_arr(array('ca'));
$a_ca = &$config['ca'];

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

$internal_ca_count = 0;
foreach ($a_ca as $ca) {
	if ($ca['prv']) {
		$internal_ca_count++;
	}
}

if ($_REQUEST['exportp12']) {
	$act = 'p12';
} elseif ($_REQUEST['exportpkey']) {
	$act = 'key';
} else {
	$act = $_REQUEST['act'];
}

if ($act == 'edit') {
	$cert_methods = array(
		'edit' => gettext("Edit an existing certificate")
	);
}

if (isset($_REQUEST['id']) && ctype_alnum($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}
if (!empty($id)) {
	$thiscert =& lookup_cert($id);
}

/* Actions other than 'new' require an ID.
 * 'del' action must be submitted via POST. */
if ((!empty($act) &&
    ($act != 'new') &&
    !$thiscert) ||
    (($act == 'del') && empty($_POST))) {
	pfSenseHeader("system_certmanager.php");
	exit;
}

switch ($act) {
	case 'del':
		$name = htmlspecialchars($thiscert['descr']);
		if (cert_in_use($id)) {
			$savemsg = sprintf(gettext("Certificate %s is in use and cannot be deleted"), $name);
			$class = "danger";
		} else {
			foreach ($a_cert as $cid => $acrt) {
				if ($acrt['refid'] == $thiscert['refid']) {
					unset($a_cert[$cid]);
				}
			}
			$savemsg = sprintf(gettext("Deleted certificate %s"), $name);
			write_config($savemsg);
		}
		unset($act);
		break;
	case 'new':
		/* New certificate, so set default values */
		$pconfig['method'] = $_POST['method'];
		$pconfig['keytype'] = "RSA";
		$pconfig['keylen'] = "2048";
		$pconfig['ecname'] = "prime256v1";
		$pconfig['digest_alg'] = "sha256";
		$pconfig['csr_keytype'] = "RSA";
		$pconfig['csr_keylen'] = "2048";
		$pconfig['csr_ecname'] = "prime256v1";
		$pconfig['csr_digest_alg'] = "sha256";
		$pconfig['csrsign_digest_alg'] = "sha256";
		$pconfig['type'] = "user";
		$pconfig['lifetime'] = $default_lifetime;
		break;
	case 'edit':
		/* Editing a certificate, so populate values */
		$pconfig['descr'] = $thiscert['descr'];
		$pconfig['cert'] = base64_decode($thiscert['crt']);
		$pconfig['key'] = base64_decode($thiscert['prv']);
		break;
	case 'csr':
		/* Editing a CSR, so populate values */
		$pconfig['descr'] = $thiscert['descr'];
		$pconfig['csr'] = base64_decode($thiscert['csr']);
		break;
	case 'exp':
		/* Exporting a certificate */
		send_user_download('data', base64_decode($thiscert['crt']), "{$thiscert['descr']}.crt");
		break;
	case 'req':
		/* Exporting a certificate signing request */
		send_user_download('data', base64_decode($thiscert['csr']), "{$thiscert['descr']}.req");
		break;
	case 'key':
		/* Exporting a private key */
		$keyout = base64_decode($thiscert['prv']);
		if (isset($_POST['exportpass']) && !empty($_POST['exportpass'])) {
			if ((strlen($_POST['exportpass']) < 4) or (strlen($_POST['exportpass']) > 1023)) {
				$savemsg = gettext("Export password must be in 4 to 1023 characters.");
				$class = 'danger';
				break;
			} else {
				$res_key = openssl_pkey_get_private($keyout);
				if ($res_key) {
					$args = array('encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC);
					openssl_pkey_export($res_key, $keyout, $_POST['exportpass'], $args);
				} else {
					$savemsg = gettext("Unable to export password-protected private key.");
					$class = 'danger';
				}
			}
		}
		if (!empty($keyout)) {
			send_user_download('data', $keyout, "{$thiscert['descr']}.key");
		}
		break;
	case 'p12':
		/* Exporting a PKCS#12 file containing the certificate, key, and (if present) CA */
		if (isset($_POST['exportpass']) && !empty($_POST['exportpass'])) {
			if ((strlen($_POST['exportpass']) < 4) or (strlen($_POST['exportpass']) > 1023)) {
				$savemsg = gettext("Export password must be in 4 to 1023 characters.");
				$class = 'danger';
				break;
			} else {
				$password = $_POST['exportpass'];
			}
		} else {
			$password = null;
		}
		$args = array();
		$args['friendly_name'] = $thiscert['descr'];
		$args['encrypt_key_cipher'] = OPENSSL_CIPHER_AES_256_CBC;
		$ca = lookup_ca($thiscert['caref']);
		if ($ca) {
			/* If the CA can be found, then add the CA to the container */
			$args['extracerts'] = openssl_x509_read(base64_decode($ca['crt']));
		}
		$res_crt = openssl_x509_read(base64_decode($thiscert['crt']));
		$res_key = openssl_pkey_get_private(base64_decode($thiscert['prv']));
		$exp_data = "";
		openssl_pkcs12_export($res_crt, $exp_data, $res_key, $password, $args);
		send_user_download('data', $exp_data, "{$thiscert['descr']}.p12");
		break;
	default:
		break;
}

if ($_POST['save'] == gettext("Save")) {
	/* Creating a new entry */
	$input_errors = array();
	$pconfig = $_POST;

	switch ($pconfig['method']) {
		case 'sign':
			$reqdfields = explode(" ",
				"descr catosignwith");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("CA to sign with"));

			if (($_POST['csrtosign'] === "new") &&
			    ((!strstr($_POST['csrpaste'], "BEGIN CERTIFICATE REQUEST") || !strstr($_POST['csrpaste'], "END CERTIFICATE REQUEST")) &&
			    (!strstr($_POST['csrpaste'], "BEGIN NEW CERTIFICATE REQUEST") || !strstr($_POST['csrpaste'], "END NEW CERTIFICATE REQUEST")))) {
				$input_errors[] = gettext("This signing request does not appear to be valid.");
			}

			if ( (($_POST['csrtosign'] === "new") && (strlen($_POST['keypaste']) > 0)) && 
			    ((!strstr($_POST['keypaste'], "BEGIN PRIVATE KEY") && !strstr($_POST['keypaste'], "BEGIN EC PRIVATE KEY")) || 
			    (strstr($_POST['keypaste'], "BEGIN PRIVATE KEY") && !strstr($_POST['keypaste'], "END PRIVATE KEY")) ||
			    (strstr($_POST['keypaste'], "BEGIN EC PRIVATE KEY") && !strstr($_POST['keypaste'], "END EC PRIVATE KEY")))) {
				$input_errors[] = gettext("This private does not appear to be valid.");
				$input_errors[] = gettext("Key data field should be blank, or a valid x509 private key");
			}
			if ($_POST['lifetime'] > $max_lifetime) {
				$input_errors[] = gettext("Lifetime is longer than the maximum allowed value. Use a shorter lifetime.");
			}
			break;
		case 'edit':
		case 'import':
			$reqdfields = explode(" ",
				"descr cert key");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate data"),
				gettext("Key data"));
			if ($_POST['cert'] && (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))) {
				$input_errors[] = gettext("This certificate does not appear to be valid.");
			}

			if (cert_get_publickey($_POST['cert'], false) != cert_get_publickey($_POST['key'], false, 'prv')) {
				$input_errors[] = gettext("The submitted private key does not match the submitted certificate data.");
			}
			break;
		case 'internal':
			$reqdfields = explode(" ",
				"descr caref keylen ecname keytype type lifetime dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate authority"),
				gettext("Key length"),
				gettext("Elliptic Curve Name"),
				gettext("Key type"),
				gettext("Certificate Type"),
				gettext("Lifetime"),
				gettext("Common Name"));
			if ($_POST['lifetime'] > $max_lifetime) {
				$input_errors[] = gettext("Lifetime is longer than the maximum allowed value. Use a shorter lifetime.");
			}
			break;
		case 'external':
			$reqdfields = explode(" ",
				"descr csr_keylen csr_ecname csr_keytype csr_dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Key length"),
				gettext("Elliptic Curve Name"),
				gettext("Key type"),
				gettext("Common Name"));
			break;
		case 'existing':
			$reqdfields = array("certref");
			$reqdfieldsn = array(gettext("Existing Certificate Choice"));
			break;
		default:
			break;
	}

	$altnames = array();
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!in_array($pconfig['method'], array('edit', 'import', 'existing'))) {
		/* subjectAltNames */
		$san_typevar = 'altname_type';
		$san_valuevar = 'altname_value';
		// This is just the blank alternate name that is added for display purposes. We don't want to validate/save it
		if ($_POST["{$san_valuevar}0"] == "") {
			unset($_POST["{$san_typevar}0"]);
			unset($_POST["{$san_valuevar}0"]);
		}
		foreach ($_POST as $key => $value) {
			$entry = '';
			if (!substr_compare($san_typevar, $key, 0, strlen($san_typevar))) {
				$entry = substr($key, strlen($san_typevar));
				$field = 'type';
			} elseif (!substr_compare($san_valuevar, $key, 0, strlen($san_valuevar))) {
				$entry = substr($key, strlen($san_valuevar));
				$field = 'value';
			}

			if (ctype_digit($entry)) {
				$entry++;	// Pre-bootstrap code is one-indexed, but the bootstrap code is 0-indexed
				$altnames[$entry][$field] = $value;
			}
		}

		$pconfig['altnames']['item'] = $altnames;

		/* Input validation for subjectAltNames */
		foreach ($altnames as $idx => $altname) {
			switch ($altname['type']) {
				case "DNS":
					if (!is_hostname($altname['value'], true) || is_ipaddr($altname['value'])) {
						$input_errors[] = gettext("DNS subjectAltName values must be valid hostnames, FQDNs or wildcard domains.");
					}
					break;
				case "IP":
					if (!is_ipaddr($altname['value'])) {
						$input_errors[] = gettext("IP subjectAltName values must be valid IP Addresses");
					}
					break;
				case "email":
					if (empty($altname['value'])) {
						$input_errors[] = gettext("An e-mail address must be provided for this type of subjectAltName");
					}
					if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $altname['value'])) {
						$input_errors[] = gettext("The e-mail provided in a subjectAltName contains invalid characters.");
					}
					break;
				case "URI":
					/* Close enough? */
					if (!is_URL($altname['value'])) {
						$input_errors[] = gettext("URI subjectAltName types must be a valid URI");
					}
					break;
				default:
					$input_errors[] = gettext("Unrecognized subjectAltName type.");
			}
		}

		/* Make sure we do not have invalid characters in the fields for the certificate */
		if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
			$input_errors[] = gettext("The field 'Descriptive Name' contains invalid characters.");
		}

		switch ($pconfig['method']) {
			case "internal":
				if (isset($_POST["keytype"]) && !in_array($_POST["keytype"], $cert_keytypes)) {
					$input_errors[] = gettext("Please select a valid Key Type.");
				}
				if (isset($_POST["keylen"]) && !in_array($_POST["keylen"], $cert_keylens)) {
					$input_errors[] = gettext("Please select a valid Key Length.");
				}
				if (isset($_POST["ecname"]) && !in_array($_POST["ecname"], array_keys($openssl_ecnames))) {
					$input_errors[] = gettext("Please select a valid Elliptic Curve Name.");
				}
				if (!in_array($_POST["digest_alg"], $openssl_digest_algs)) {
					$input_errors[] = gettext("Please select a valid Digest Algorithm.");
				}
				break;
			case "external":
				if (isset($_POST["csr_keytype"]) && !in_array($_POST["csr_keytype"], $cert_keytypes)) {
					$input_errors[] = gettext("Please select a valid Key Type.");
				}
				if (isset($_POST["csr_keylen"]) && !in_array($_POST["csr_keylen"], $cert_keylens)) {
					$input_errors[] = gettext("Please select a valid Key Length.");
				}
				if (isset($_POST["csr_ecname"]) && !in_array($_POST["csr_ecname"], array_keys($openssl_ecnames))) {
					$input_errors[] = gettext("Please select a valid Elliptic Curve Name.");
				}
				if (!in_array($_POST["csr_digest_alg"], $openssl_digest_algs)) {
					$input_errors[] = gettext("Please select a valid Digest Algorithm.");
				}
				break;
			case "sign":
				if (!in_array($_POST["csrsign_digest_alg"], $openssl_digest_algs)) {
					$input_errors[] = gettext("Please select a valid Digest Algorithm.");
				}
				break;
			default:
				break;
		}
	}

	/* save modifications */
	if (!$input_errors) {
		$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page breaking menu tabs */

		if (isset($id) && $thiscert) {
			$cert = $thiscert;
		} else {
			$cert = array();
			$cert['refid'] = uniqid();
		}

		$cert['descr'] = $pconfig['descr'];

		switch($pconfig['method']) {
			case 'existing':
				/* Add an existing certificate to a user */
				$ucert = lookup_cert($pconfig['certref']);
				if ($ucert && $a_user) {
					$a_user[$userid]['cert'][] = $ucert['refid'];
					$savemsg = sprintf(gettext("Added certificate %s to user %s"), $ucert['descr'], $a_user[$userid]['name']);
				}
				unset($cert);
				break;
			case 'sign':
				/* Sign a CSR */
				$csrid = lookup_cert($pconfig['csrtosign']);
				$ca = & lookup_ca($pconfig['catosignwith']);
				// Read the CSR from $config, or if a new one, from the textarea
				if ($pconfig['csrtosign'] === "new") {
					$csr = $pconfig['csrpaste'];
				} else {
					$csr = base64_decode($csrid['csr']);
				}
				if (count($altnames)) {
					foreach ($altnames as $altname) {
						$altnames_tmp[] = "{$altname['type']}:" . cert_escape_x509_chars($altname['value']);
					}
					$altname_str = implode(",", $altnames_tmp);
				}
				$n509 = csr_sign($csr, $ca, $pconfig['csrsign_lifetime'], $pconfig['type'], $altname_str, $pconfig['csrsign_digest_alg']);
				if ($n509) {
					// Gather the details required to save the new cert
					$newcert = array();
					$newcert['refid'] = uniqid();
					$newcert['caref'] = $pconfig['catosignwith'];
					$newcert['descr'] = $pconfig['descr'];
					$newcert['type'] = $pconfig['type'];
					$newcert['crt'] = base64_encode($n509);
					if ($pconfig['csrtosign'] === "new") {
						$newcert['prv'] = base64_encode($pconfig['keypaste']);
					} else {
						$newcert['prv'] = $csrid['prv'];
					}
					// Add it to the config file
					$config['cert'][] = $newcert;
					$savemsg = sprintf(gettext("Signed certificate %s"), $newcert['descr']);
				}
				unset($cert);
				break;
			case 'edit':
				cert_import($cert, $pconfig['cert'], $pconfig['key']);
				$savemsg = sprintf(gettext("Edited certificate %s"), $cert['descr']);
				break;
			case 'import':
				/* Import an external certificate+key */
				cert_import($cert, $pconfig['cert'], $pconfig['key']);
				$savemsg = sprintf(gettext("Imported certificate %s"), $cert['descr']);
				break;
			case 'internal':
				/* Create an internal certificate */
				$dn = array('commonName' => cert_escape_x509_chars($pconfig['dn_commonname']));
				if (!empty($pconfig['dn_country'])) {
					$dn['countryName'] = $pconfig['dn_country'];
				}
				if (!empty($pconfig['dn_state'])) {
					$dn['stateOrProvinceName'] = cert_escape_x509_chars($pconfig['dn_state']);
				}
				if (!empty($pconfig['dn_city'])) {
					$dn['localityName'] = cert_escape_x509_chars($pconfig['dn_city']);
				}
				if (!empty($pconfig['dn_organization'])) {
					$dn['organizationName'] = cert_escape_x509_chars($pconfig['dn_organization']);
				}
				if (!empty($pconfig['dn_organizationalunit'])) {
					$dn['organizationalUnitName'] = cert_escape_x509_chars($pconfig['dn_organizationalunit']);
				}
				$altnames_tmp = array();
				$cn_altname = cert_add_altname_type($pconfig['dn_commonname']);
				if (!empty($cn_altname)) {
					$altnames_tmp[] = $cn_altname;
				}
				if (count($altnames)) {
					foreach ($altnames as $altname) {
						// The CN is added as a SAN automatically, do not add it again.
						if ($altname['value'] != $pconfig['dn_commonname']) {
							$altnames_tmp[] = "{$altname['type']}:" . cert_escape_x509_chars($altname['value']);
						}
					}
				}
				if (!empty($altnames_tmp)) {
					$dn['subjectAltName'] = implode(",", $altnames_tmp);
				}
				if (!cert_create($cert, $pconfig['caref'], $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['type'], $pconfig['digest_alg'], $pconfig['keytype'], $pconfig['ecname'])) {
					$input_errors = array();
					while ($ssl_err = openssl_error_string()) {
						if (strpos($ssl_err, 'NCONF_get_string:no value') === false) {
							$input_errors[] = sprintf(gettext("OpenSSL Library Error: %s"), $ssl_err);
						}
					}
				}
				$savemsg = sprintf(gettext("Created internal certificate %s"), $cert['descr']);
				break;
			case 'external':
				/* Create a certificate signing request */
				$dn = array('commonName' => cert_escape_x509_chars($pconfig['csr_dn_commonname']));
				if (!empty($pconfig['csr_dn_country'])) {
					$dn['countryName'] = $pconfig['csr_dn_country'];
				}
				if (!empty($pconfig['csr_dn_state'])) {
					$dn['stateOrProvinceName'] = cert_escape_x509_chars($pconfig['csr_dn_state']);
				}
				if (!empty($pconfig['csr_dn_city'])) {
					$dn['localityName'] = cert_escape_x509_chars($pconfig['csr_dn_city']);
				}
				if (!empty($pconfig['csr_dn_organization'])) {
					$dn['organizationName'] = cert_escape_x509_chars($pconfig['csr_dn_organization']);
				}
				if (!empty($pconfig['csr_dn_organizationalunit'])) {
					$dn['organizationalUnitName'] = cert_escape_x509_chars($pconfig['csr_dn_organizationalunit']);
				}
				$altnames_tmp = array();
				$cn_altname = cert_add_altname_type($pconfig['csr_dn_commonname']);
				if (!empty($cn_altname)) {
					$altnames_tmp[] = $cn_altname;
				}
				if (count($altnames)) {
					foreach ($altnames as $altname) {
						// The CN is added as a SAN automatically, do not add it again.
						if ($altname['value'] != $pconfig['csr_dn_commonname']) {
							$altnames_tmp[] = "{$altname['type']}:" . cert_escape_x509_chars($altname['value']);
						}
					}
				}
				if (!empty($altnames_tmp)) {
					$dn['subjectAltName'] = implode(",", $altnames_tmp);
				}
				if (!csr_generate($cert, $pconfig['csr_keylen'], $dn, $pconfig['type'], $pconfig['csr_digest_alg'], $pconfig['csr_keytype'], $pconfig['csr_ecname'])) {
					$input_errors = array();
					while ($ssl_err = openssl_error_string()) {
						if (strpos($ssl_err, 'NCONF_get_string:no value') === false) {
							$input_errors[] = sprintf(gettext("OpenSSL Library Error: %s"), $ssl_err);
						}
					}
				}
				$savemsg = sprintf(gettext("Created certificate signing request %s"), $cert['descr']);
				break;
			default:
				break;
		}
		error_reporting($old_err_level);

		if (isset($id) && $thiscert) {
			$thiscert = $cert;
		} elseif ($cert) {
			$a_cert[] = $cert;
		}

		if (isset($a_user) && isset($userid)) {
			$a_user[$userid]['cert'][] = $cert['refid'];
		}

		if (!$input_errors) {
			write_config($savemsg);
		}

		if ((isset($userid) && is_numeric($userid)) && !$input_errors) {
			post_redirect("system_usermanager.php", array('act' => 'edit', 'userid' => $userid));
			exit;
		}
	}
} elseif ($_POST['save'] == gettext("Update")) {
	/* Updating a certificate signing request */
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "descr cert");
	$reqdfieldsn = array(
		gettext("Descriptive name"),
		gettext("Final Certificate data"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
		$input_errors[] = gettext("The field 'Descriptive Name' contains invalid characters.");
	}

	$mod_csr = cert_get_publickey($pconfig['csr'], false, 'csr');
	$mod_cert = cert_get_publickey($pconfig['cert'], false);

	if (strcmp($mod_csr, $mod_cert)) {
		// simply: if the moduli don't match, then the private key and public key won't match
		$input_errors[] = gettext("The certificate public key does not match the signing request public key.");
		$subject_mismatch = true;
	}

	/* save modifications */
	if (!$input_errors) {
		$cert = $thiscert;
		$cert['descr'] = $pconfig['descr'];
		csr_complete($cert, $pconfig['cert']);
		$thiscert = $cert;
		$savemsg = sprintf(gettext("Updated certificate signing request %s"), $pconfig['descr']);
		write_config($savemsg);
		pfSenseHeader("system_certmanager.php");
	}
}

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("Certificates"));
$pglinks = array("", "system_camanager.php", "system_certmanager.php");

if (($act == "new" || ($_POST['save'] == gettext("Save") && $input_errors)) ||
    ($act == "csr" || ($_POST['save'] == gettext("Update") && $input_errors))) {
	$pgtitle[] = gettext('Edit');
	$pglinks[] = "@self";
}
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, $class);
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), true, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
display_top_tabs($tab_array);

if (in_array($act, array('new', 'edit')) || (($_POST['save'] == gettext("Save")) && $input_errors)) {
	$form = new Form();
	$form->setAction('system_certmanager.php');

	if (isset($userid) && $a_user) {
		$form->addGlobal(new Form_Input(
			'userid',
			null,
			'hidden',
			$userid
		));
	}

	if (isset($id) && $thiscert) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	switch ($act) {
		case 'edit':
			$maintitle = gettext('Edit an Existing Certificate');
			break;
		case 'new':
		default:
			$maintitle = gettext('Add/Sign a New Certificate');
			break;
	}

	$section = new Form_Section($maintitle);

	if (!isset($id) || ($act == 'edit')) {
		$section->addInput(new Form_Select(
			'method',
			'*Method',
			$pconfig['method'],
			$cert_methods
		))->toggles();
	}

	$section->addInput(new Form_Input(
		'descr',
		'*Descriptive name',
		'text',
		($a_user && empty($pconfig['descr'])) ? $a_user[$userid]['name'] : $pconfig['descr']
	))->addClass('toggle-internal toggle-import toggle-edit toggle-external toggle-sign toggle-existing collapse');

	if (!empty($pconfig['cert'])) {
		$section->addInput(new Form_StaticText(
			"Subject",
			htmlspecialchars(cert_get_subject($pconfig['cert'], false))
		))->addClass('toggle-edit collapse');
	}

	$form->add($section);

	// Return an array containing the IDs od all CAs
	function list_cas() {
		global $a_ca;
		$allCas = array();

		foreach ($a_ca as $ca) {
			if ($ca['prv']) {
				$allCas[$ca['refid']] = $ca['descr'];
			}
		}

		return $allCas;
	}

	// Return an array containing the IDs od all CSRs
	function list_csrs() {
		global $config;
		$allCsrs = array();

		foreach ($config['cert'] as $cert) {
			if ($cert['csr']) {
				$allCsrs[$cert['refid']] = $cert['descr'];
			}
		}

		return ['new' => gettext('New CSR (Paste below)')] + $allCsrs;
	}

	$section = new Form_Section('Sign CSR');
	$section->addClass('toggle-sign collapse');

	$section->AddInput(new Form_Select(
		'catosignwith',
		'*CA to sign with',
		$pconfig['catosignwith'],
		list_cas()
	));

	$section->AddInput(new Form_Select(
		'csrtosign',
		'*CSR to sign',
		isset($pconfig['csrtosign']) ? $pconfig['csrtosign'] : 'new',
		list_csrs()
	));

	$section->addInput(new Form_Textarea(
		'csrpaste',
		'CSR data',
		$pconfig['csrpaste']
	))->setHelp('Paste a Certificate Signing Request in X.509 PEM format here.');

	$section->addInput(new Form_Textarea(
		'keypaste',
		'Key data',
		$pconfig['keypaste']
	))->setHelp('Optionally paste a private key here. The key will be associated with the newly signed certificate in pfSense');

	$section->addInput(new Form_Input(
		'csrsign_lifetime',
		'*Certificate Lifetime (days)',
		'number',
		$pconfig['csrsign_lifetime'] ? $pconfig['csrsign_lifetime']:$default_lifetime,
		['max' => $max_lifetime]
	))->setHelp('The length of time the signed certificate will be valid, in days. %1$s' .
		'Server certificates should not have a lifetime over %2$s days or some platforms ' .
		'may consider the certificate invalid.', '<br/>', $cert_strict_values['max_server_cert_lifetime']);
	$section->addInput(new Form_Select(
		'csrsign_digest_alg',
		'*Digest Algorithm',
		$pconfig['csrsign_digest_alg'],
		array_combine($openssl_digest_algs, $openssl_digest_algs)
	))->setHelp('The digest method used when the certificate is signed. %1$s' .
		'The best practice is to use an algorithm stronger than SHA1. '.
		'Some platforms may consider weaker digest algorithms invalid', '<br/>');

	$form->add($section);

	if ($act == 'edit') {
		$editimport = gettext("Edit Certificate");
	} else {
		$editimport = gettext("Import Certificate");
	}

	$section = new Form_Section($editimport);
	$section->addClass('toggle-import toggle-edit collapse');

	$section->addInput(new Form_Textarea(
		'cert',
		'*Certificate data',
		$pconfig['cert']
	))->setHelp('Paste a certificate in X.509 PEM format here.');

	$section->addInput(new Form_Textarea(
		'key',
		'*Private key data',
		$pconfig['key']
	))->setHelp('Paste a private key in X.509 PEM format here.');

	if ($act == 'edit') {
		$section->addInput(new Form_Input(
			'exportpass',
			'Export Password',
			'password',
			null,
			['placeholder' => gettext('Export Password'), 'autocomplete' => 'new-password']
		))->setHelp('Enter the password to use when using the export buttons below (not stored)')->addClass('toggle-edit collapse');
	}

	$form->add($section);
	$section = new Form_Section('Internal Certificate');
	$section->addClass('toggle-internal collapse');

	if (!$internal_ca_count) {
		$section->addInput(new Form_StaticText(
			'*Certificate authority',
			gettext('No internal Certificate Authorities have been defined. ') .
			gettext('An internal CA must be defined in order to create an internal certificate. ') .
			sprintf(gettext('%1$sCreate%2$s an internal CA.'), '<a href="system_camanager.php?act=new&amp;method=internal"> ', '</a>')
		));
	} else {
		$allCas = array();
		foreach ($a_ca as $ca) {
			if (!$ca['prv']) {
				continue;
			}

			$allCas[ $ca['refid'] ] = $ca['descr'];
		}

		$section->addInput(new Form_Select(
			'caref',
			'*Certificate authority',
			$pconfig['caref'],
			$allCas
		));
	}

	$section->addInput(new Form_Select(
		'keytype',
		'*Key type',
		$pconfig['keytype'],
		array_combine($cert_keytypes, $cert_keytypes)
	));

	$group = new Form_Group($i == 0 ? '*Key length':'');
	$group->addClass('rsakeys');
	$group->add(new Form_Select(
		'keylen',
		null,
		$pconfig['keylen'],
		array_combine($cert_keylens, $cert_keylens)
	))->setHelp('The length to use when generating a new RSA key, in bits. %1$s' .
		'The Key Length should not be lower than 2048 or some platforms ' .
		'may consider the certificate invalid.', '<br/>');
	$section->add($group);

	$group = new Form_Group($i == 0 ? '*Elliptic Curve Name':'');
	$group->addClass('ecnames');
	$group->add(new Form_Select(
		'ecname',
		null,
		$pconfig['ecname'],
		$openssl_ecnames
	))->setHelp('Curves may not be compatible with all uses. Known compatible curve uses are denoted in brackets.');
	$section->add($group);

	$section->addInput(new Form_Select(
		'digest_alg',
		'*Digest Algorithm',
		$pconfig['digest_alg'],
		array_combine($openssl_digest_algs, $openssl_digest_algs)
	))->setHelp('The digest method used when the certificate is signed. %1$s' .
		'The best practice is to use an algorithm stronger than SHA1. '.
		'Some platforms may consider weaker digest algorithms invalid', '<br/>');

	$section->addInput(new Form_Input(
		'lifetime',
		'*Lifetime (days)',
		'number',
		$pconfig['lifetime'],
		['max' => $max_lifetime]
	))->setHelp('The length of time the signed certificate will be valid, in days. %1$s' .
		'Server certificates should not have a lifetime over %2$s days or some platforms ' .
		'may consider the certificate invalid.', '<br/>', $cert_strict_values['max_server_cert_lifetime']);

	$section->addInput(new Form_Input(
		'dn_commonname',
		'*Common Name',
		'text',
		$pconfig['dn_commonname'],
		['placeholder' => 'e.g. www.example.com']
	));

	$section->addInput(new Form_StaticText(
		null,
		gettext('The following certificate subject components are optional and may be left blank.')
	));

	$section->addInput(new Form_Select(
		'dn_country',
		'Country Code',
		$pconfig['dn_country'],
		get_cert_country_codes()
	));

	$section->addInput(new Form_Input(
		'dn_state',
		'State or Province',
		'text',
		$pconfig['dn_state'],
		['placeholder' => 'e.g. Texas']
	));

	$section->addInput(new Form_Input(
		'dn_city',
		'City',
		'text',
		$pconfig['dn_city'],
		['placeholder' => 'e.g. Austin']
	));

	$section->addInput(new Form_Input(
		'dn_organization',
		'Organization',
		'text',
		$pconfig['dn_organization'],
		['placeholder' => 'e.g. My Company Inc']
	));

	$section->addInput(new Form_Input(
		'dn_organizationalunit',
		'Organizational Unit',
		'text',
		$pconfig['dn_organizationalunit'],
		['placeholder' => 'e.g. My Department Name (optional)']
	));

	$form->add($section);
	$section = new Form_Section('External Signing Request');
	$section->addClass('toggle-external collapse');

	$section->addInput(new Form_Select(
		'csr_keytype',
		'*Key type',
		$pconfig['csr_keytype'],
		array_combine($cert_keytypes, $cert_keytypes)
	));

	$group = new Form_Group($i == 0 ? '*Key length':'');
	$group->addClass('csr_rsakeys');
	$group->add(new Form_Select(
		'csr_keylen',
		null,
		$pconfig['csr_keylen'],
		array_combine($cert_keylens, $cert_keylens)
	))->setHelp('The length to use when generating a new RSA key, in bits. %1$s' .
		'The Key Length should not be lower than 2048 or some platforms ' .
		'may consider the certificate invalid.', '<br/>');
	$section->add($group);

	$group = new Form_Group($i == 0 ? '*Elliptic Curve Name':'');
	$group->addClass('csr_ecnames');
	$group->add(new Form_Select(
		'csr_ecname',
		null,
		$pconfig['csr_ecname'],
		$openssl_ecnames
	));
	$section->add($group);

	$section->addInput(new Form_Select(
		'csr_digest_alg',
		'*Digest Algorithm',
		$pconfig['csr_digest_alg'],
		array_combine($openssl_digest_algs, $openssl_digest_algs)
	))->setHelp('The digest method used when the certificate is signed. %1$s' .
		'The best practice is to use an algorithm stronger than SHA1. '.
		'Some platforms may consider weaker digest algorithms invalid', '<br/>');

	$section->addInput(new Form_Input(
		'csr_dn_commonname',
		'*Common Name',
		'text',
		$pconfig['csr_dn_commonname'],
		['placeholder' => 'e.g. internal-ca']
	));

	$section->addInput(new Form_StaticText(
		null,
		gettext('The following certificate subject components are optional and may be left blank.')
	));

	$section->addInput(new Form_Select(
		'csr_dn_country',
		'Country Code',
		$pconfig['csr_dn_country'],
		get_cert_country_codes()
	));

	$section->addInput(new Form_Input(
		'csr_dn_state',
		'State or Province',
		'text',
		$pconfig['csr_dn_state'],
		['placeholder' => 'e.g. Texas']
	));

	$section->addInput(new Form_Input(
		'csr_dn_city',
		'City',
		'text',
		$pconfig['csr_dn_city'],
		['placeholder' => 'e.g. Austin']
	));

	$section->addInput(new Form_Input(
		'csr_dn_organization',
		'Organization',
		'text',
		$pconfig['csr_dn_organization'],
		['placeholder' => 'e.g. My Company Inc']
	));

	$section->addInput(new Form_Input(
		'csr_dn_organizationalunit',
		'Organizational Unit',
		'text',
		$pconfig['csr_dn_organizationalunit'],
		['placeholder' => 'e.g. My Department Name (optional)']
	));

	$form->add($section);
	$section = new Form_Section('Choose an Existing Certificate');
	$section->addClass('toggle-existing collapse');

	$existCerts = array();

	foreach ($config['cert'] as $cert) {
		if (!is_array($cert) || empty($cert)) {
			continue;
		}
		if (is_array($config['system']['user'][$userid]['cert'])) { // Could be MIA!
			if (isset($userid) && in_array($cert['refid'], $config['system']['user'][$userid]['cert'])) {
				continue;
			}
		}

		$ca = lookup_ca($cert['caref']);
		if ($ca) {
			$cert['descr'] .= " (CA: {$ca['descr']})";
		}

		if (cert_in_use($cert['refid'])) {
			$cert['descr'] .= " (In Use)";
		}
		if (is_cert_revoked($cert)) {
			$cert['descr'] .= " (Revoked)";
		}

		$existCerts[ $cert['refid'] ] = $cert['descr'];
	}

	$section->addInput(new Form_Select(
		'certref',
		'*Existing Certificates',
		$pconfig['certref'],
		$existCerts
	));

	$form->add($section);

	$section = new Form_Section('Certificate Attributes');
	$section->addClass('toggle-external toggle-internal toggle-sign collapse');

	$section->addInput(new Form_StaticText(
		gettext('Attribute Notes'),
		'<span class="help-block">'.
		gettext('The following attributes are added to certificates and ' .
		'requests when they are created or signed. These attributes behave ' .
		'differently depending on the selected mode.') .
		'<br/><br/>' .
		'<span class="toggle-internal collapse">' . gettext('For Internal Certificates, these attributes are added directly to the certificate as shown.') . '</span>' .
		'<span class="toggle-external collapse">' .
		gettext('For Certificate Signing Requests, These attributes are added to the request but they may be ignored or changed by the CA that signs the request. ') .
		'<br/><br/>' .
		gettext('If this CSR will be signed using the Certificate Manager on this firewall, set the attributes when signing instead as they cannot be carried over.') . '</span>' .
		'<span class="toggle-sign collapse">' . gettext('When Signing a Certificate Request, existing attributes in the request cannot be copied. The attributes below will be applied to the resulting certificate.') . '</span>' .
		'</span>'
	));

	$section->addInput(new Form_Select(
		'type',
		'*Certificate Type',
		$pconfig['type'],
		$cert_types
	))->setHelp('Add type-specific usage attributes to the signed certificate.' .
		' Used for placing usage restrictions on, or granting abilities to, ' .
		'the signed certificate.');

	if (empty($pconfig['altnames']['item'])) {
		$pconfig['altnames']['item'] = array(
			array('type' => null, 'value' => null)
		);
	}

	$counter = 0;
	$numrows = count($pconfig['altnames']['item']) - 1;

	foreach ($pconfig['altnames']['item'] as $item) {

		$group = new Form_Group($counter == 0 ? 'Alternative Names':'');

		$group->add(new Form_Select(
			'altname_type' . $counter,
			'Type',
			$item['type'],
			$cert_altname_types
		))->setHelp(($counter == $numrows) ? 'Type':null);

		$group->add(new Form_Input(
			'altname_value' . $counter,
			null,
			'text',
			$item['value']
		))->setHelp(($counter == $numrows) ? 'Value':null);

		$group->add(new Form_Button(
			'deleterow' . $counter,
			'Delete',
			null,
			'fa-trash'
		))->addClass('btn-warning');

		$group->addClass('repeatable');

		$group->setHelp('Enter additional identifiers for the certificate ' .
			'in this list. The Common Name field is automatically ' .
			'added to the certificate as an Alternative Name. ' .
			'The signing CA may ignore or change these values.');

		$section->add($group);

		$counter++;
	}

	$section->addInput(new Form_Button(
		'addrow',
		'Add',
		null,
		'fa-plus'
	))->addClass('btn-success');

	$form->add($section);

	if ($act == 'edit') {
		$form->addGlobal(new Form_Button(
			'exportpkey',
			'Export Private Key',
			null,
			'fa-key'
		))->addClass('btn-primary');
		$form->addGlobal(new Form_Button(
			'exportp12',
			'Export PKCS#12',
			null,
			'fa-archive'
		))->addClass('btn-primary');
	}

	print $form;

} elseif ($act == "csr" || (($_POST['save'] == gettext("Update")) && $input_errors)) {
	$form = new Form(false);
	$form->setAction('system_certmanager.php?act=csr');

	$section = new Form_Section("Complete Signing Request for " . $pconfig['descr']);

	$section->addInput(new Form_Input(
		'descr',
		'*Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Textarea(
		'csr',
		'Signing request data',
		$pconfig['csr']
	))->setReadonly()
	  ->setWidth(7)
	  ->setHelp('Copy the certificate signing data from here and forward it to a certificate authority for signing.');

	$section->addInput(new Form_Textarea(
		'cert',
		'*Final certificate data',
		$pconfig['cert']
	))->setWidth(7)
	  ->setHelp('Paste the certificate received from the certificate authority here.');

	if (isset($id) && $thiscert) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));

		$form->addGlobal(new Form_Input(
			'act',
			null,
			'hidden',
			'csr'
		));
	}

	$form->add($section);

	$form->addGlobal(new Form_Button(
		'save',
		'Update',
		null,
		'fa-save'
	))->addClass('btn-primary');

	print($form);
} else {
?>
<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="0"><?=gettext("Name")?></option>
					<option value="1"><?=gettext("Distinguished Name")?></option>
					<option value="2" selected><?=gettext("Both")?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to search certificate names and distinguished names.')?></span>
			</div>
		</div>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Certificates')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
		<table class="table table-striped table-hover sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("Name")?></th>
					<th><?=gettext("Issuer")?></th>
					<th><?=gettext("Distinguished Name")?></th>
					<th><?=gettext("In Use")?></th>

					<th class="col-sm-2"><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php

$pluginparams = array();
$pluginparams['type'] = 'certificates';
$pluginparams['event'] = 'used_certificates';
$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);
foreach ($a_cert as $cert):
	if (!is_array($cert) || empty($cert)) {
		continue;
	}
	$name = htmlspecialchars($cert['descr']);
	if ($cert['crt']) {
		$subj = cert_get_subject($cert['crt']);
		$issuer = cert_get_issuer($cert['crt']);
		$purpose = cert_get_purpose($cert['crt']);

		if ($subj == $issuer) {
			$caname = '<i>'. gettext("self-signed") .'</i>';
		} else {
			$caname = '<i>'. gettext("external").'</i>';
		}

		$subj = htmlspecialchars(cert_escape_x509_chars($subj, true));
	} else {
		$subj = "";
		$issuer = "";
		$purpose = "";
		$startdate = "";
		$enddate = "";
		$caname = "<em>" . gettext("private key only") . "</em>";
	}

	if ($cert['csr']) {
		$subj = htmlspecialchars(cert_escape_x509_chars(csr_get_subject($cert['csr']), true));
		$caname = "<em>" . gettext("external - signature pending") . "</em>";
	}

	$ca = lookup_ca($cert['caref']);
	if ($ca) {
		$caname = $ca['descr'];
	}
?>
				<tr>
					<td>
						<?=$name?><br />
						<?php if ($cert['type']): ?>
							<i><?=$cert_types[$cert['type']]?></i><br />
						<?php endif?>
						<?php if (is_array($purpose)): ?>
							CA: <b><?=$purpose['ca']?></b><br/>
							<?=gettext("Server")?>: <b><?=$purpose['server']?></b><br/>
						<?php endif?>
					</td>
					<td><?=$caname?></td>
					<td>
						<?=$subj?>
						<?= cert_print_infoblock($cert); ?>
						<?php cert_print_dates($cert);?>
					</td>
					<td>
						<?php if (is_cert_revoked($cert)): ?>
							<i><?=gettext("Revoked")?></i>
						<?php endif?>
						<?php if (is_webgui_cert($cert['refid'])): ?>
							<?=gettext("webConfigurator")?>
						<?php endif?>
						<?php if (is_user_cert($cert['refid'])): ?>
							<?=gettext("User Cert")?>
						<?php endif?>
						<?php if (is_openvpn_server_cert($cert['refid'])): ?>
							<?=gettext("OpenVPN Server")?>
						<?php endif?>
						<?php if (is_openvpn_client_cert($cert['refid'])): ?>
							<?=gettext("OpenVPN Client")?>
						<?php endif?>
						<?php if (is_ipsec_cert($cert['refid'])): ?>
							<?=gettext("IPsec Tunnel")?>
						<?php endif?>
						<?php if (is_captiveportal_cert($cert['refid'])): ?>
							<?=gettext("Captive Portal")?>
						<?php endif?>
						<?php echo cert_usedby_description($cert['refid'], $certificates_used_by_packages); ?>
					</td>
					<td>
						<?php if (!$cert['csr']): ?>
							<a href="system_certmanager.php?act=edit&amp;id=<?=$cert['refid']?>" class="fa fa-pencil" title="<?=gettext("Edit Certificate")?>"></a>
							<a href="system_certmanager.php?act=exp&amp;id=<?=$cert['refid']?>" class="fa fa-certificate" title="<?=gettext("Export Certificate")?>"></a>
							<?php if ($cert['prv']): ?>
								<a href="system_certmanager.php?act=key&amp;id=<?=$cert['refid']?>" class="fa fa-key" title="<?=gettext("Export Key")?>"></a>
							<?php endif?>
							<?php if (is_cert_locally_renewable($cert)): ?>
								<a href="system_certmanager_renew.php?type=cert&amp;refid=<?=$cert['refid']?>" class="fa fa-repeat" title="<?=gettext("Reissue/Renew")?>"></a>
							<?php endif ?>
							<a href="system_certmanager.php?act=p12&amp;id=<?=$cert['refid']?>" class="fa fa-archive" title="<?=gettext("Export P12")?>"></a>
						<?php else: ?>
							<a href="system_certmanager.php?act=csr&amp;id=<?=$cert['refid']?>" class="fa fa-pencil" title="<?=gettext("Update CSR")?>"></a>
							<a href="system_certmanager.php?act=req&amp;id=<?=$cert['refid']?>" class="fa fa-sign-in" title="<?=gettext("Export Request")?>"></a>
							<a href="system_certmanager.php?act=key&amp;id=<?=$cert['refid']?>" class="fa fa-key" title="<?=gettext("Export Key")?>"></a>
						<?php endif?>
						<?php if (!cert_in_use($cert['refid'])): ?>
							<a href="system_certmanager.php?act=del&amp;id=<?=$cert['refid']?>" class="fa fa-trash" title="<?=gettext("Delete Certificate")?>" usepost></a>
						<?php endif?>
					</td>
				</tr>
<?php
	endforeach; ?>
			</tbody>
		</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add/Sign")?>
	</a>
</nav>
<script type="text/javascript">
//<![CDATA[

events.push(function() {

	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				shortname = $tds.eq(0).text().trim().toLowerCase(),
				dn = $tds.eq(2).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(shortname) && (where != 1)) && !(regexp.test(dn) && (where != 0))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
		if (event.keyCode == 13) {
			$("#btnsearch").get(0).click();
		}
	});
});
//]]>
</script>
<?php
	include("foot.inc");
	exit;
}


?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

<?php if ($internal_ca_count): ?>
	function internalca_change() {

		caref = $('#caref').val();

		switch (caref) {
<?php
			foreach ($a_ca as $ca):
				if (!$ca['prv']) {
					continue;
				}

				$subject = @cert_get_subject_hash($ca['crt']);
				if (!is_array($subject) || empty($subject)) {
					continue;
				}
?>
				case "<?=$ca['refid'];?>":
					$('#dn_country').val(<?=json_encode(cert_escape_x509_chars($subject['C'], true));?>);
					$('#dn_state').val(<?=json_encode(cert_escape_x509_chars($subject['ST'], true));?>);
					$('#dn_city').val(<?=json_encode(cert_escape_x509_chars($subject['L'], true));?>);
					$('#dn_organization').val(<?=json_encode(cert_escape_x509_chars($subject['O'], true));?>);
					$('#dn_organizationalunit').val(<?=json_encode(cert_escape_x509_chars($subject['OU'], true));?>);
					break;
<?php
			endforeach;
?>
		}
	}

	function set_csr_ro() {
		var newcsr = ($('#csrtosign').val() == "new");

		$('#csrpaste').attr('readonly', !newcsr);
		$('#keypaste').attr('readonly', !newcsr);
		setRequired('csrpaste', newcsr);
	}

	function check_lifetime() {
		var maxserverlife = <?= $cert_strict_values['max_server_cert_lifetime'] ?>;
		var ltid = '#lifetime';
		if ($('#method').val() == "sign") {
			ltid = '#csrsign_lifetime';
		}
		if (($('#type').val() == "server") && (parseInt($(ltid).val()) > maxserverlife)) {
			$(ltid).parent().parent().removeClass("text-normal").addClass("text-warning");
			$(ltid).removeClass("text-normal").addClass("text-warning");
		} else {
			$(ltid).parent().parent().removeClass("text-warning").addClass("text-normal");
			$(ltid).removeClass("text-warning").addClass("text-normal");
		}
	}
	function check_keylen() {
		var min_keylen = <?= $cert_strict_values['min_private_key_bits'] ?>;
		var klid = '#keylen';
		if ($('#method').val() == "external") {
			klid = '#csr_keylen';
		}
		/* Color the Parent/Label */
		if (parseInt($(klid).val()) < min_keylen) {
			$(klid).parent().parent().removeClass("text-normal").addClass("text-warning");
		} else {
			$(klid).parent().parent().removeClass("text-warning").addClass("text-normal");
		}
		/* Color individual options */
		$(klid + " option").filter(function() {
			return parseInt($(this).val()) < min_keylen;
		}).removeClass("text-normal").addClass("text-warning").siblings().removeClass("text-warning").addClass("text-normal");
	}

	function check_digest() {
		var weak_algs = <?= json_encode($cert_strict_values['digest_blacklist']) ?>;
		var daid = '#digest_alg';
		if ($('#method').val() == "external") {
			daid = '#csr_digest_alg';
		} else if ($('#method').val() == "sign") {
			daid = '#csrsign_digest_alg';
		}
		/* Color the Parent/Label */
		if (jQuery.inArray($(daid).val(), weak_algs) > -1) {
			$(daid).parent().parent().removeClass("text-normal").addClass("text-warning");
		} else {
			$(daid).parent().parent().removeClass("text-warning").addClass("text-normal");
		}
		/* Color individual options */
		$(daid + " option").filter(function() {
			return (jQuery.inArray($(this).val(), weak_algs) > -1);
		}).removeClass("text-normal").addClass("text-warning").siblings().removeClass("text-warning").addClass("text-normal");
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#type').on('change', function() {
		check_lifetime();
	});
	$('#method').on('change', function() {
		check_lifetime();
		check_keylen();
		check_digest();
	});
	$('#lifetime').on('change', function() {
		check_lifetime();
	});
	$('#csrsign_lifetime').on('change', function() {
		check_lifetime();
	});

	$('#keylen').on('change', function() {
		check_keylen();
	});
	$('#csr_keylen').on('change', function() {
		check_keylen();
	});

	$('#digest_alg').on('change', function() {
		check_digest();
	});
	$('#csr_digest_alg').on('change', function() {
		check_digest();
	});

	$('#caref').on('change', function() {
		internalca_change();
	});

	$('#csrtosign').change(function () {
		set_csr_ro();
	});

	function change_keytype() {
		hideClass('rsakeys', ($('#keytype').val() != 'RSA'));
		hideClass('ecnames', ($('#keytype').val() != 'ECDSA'));
	}

	$('#keytype').change(function () {
		change_keytype();
	});

	function change_csrkeytype() {
		hideClass('csr_rsakeys', ($('#csr_keytype').val() != 'RSA'));
		hideClass('csr_ecnames', ($('#csr_keytype').val() != 'ECDSA'));
	}

	$('#csr_keytype').change(function () {
		change_csrkeytype();
	});

	// ---------- On initial page load ------------------------------------------------------------

	internalca_change();
	set_csr_ro();
	change_keytype();
	change_csrkeytype();
	check_lifetime();
	check_keylen();
	check_digest();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();


<?php endif; ?>


});
//]]>
</script>
<?php
include('foot.inc');
