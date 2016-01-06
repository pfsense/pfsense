<?php
/*
	system_certmanager.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *	Copyright (c)  2008 Shrew Soft Inc.
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-system-certmanager
##|*NAME=System: Certificate Manager
##|*DESCR=Allow access to the 'System: Certificate Manager' page.
##|*MATCH=system_certmanager.php*
##|-PRIV

require("guiconfig.inc");
require_once("certs.inc");

$cert_methods = array(
	"import" => gettext("Import an existing Certificate"),
	"internal" => gettext("Create an internal Certificate"),
	"external" => gettext("Create a Certificate Signing Request"),
);

$cert_keylens = array("512", "1024", "2048", "4096");
$cert_types = array(
	"ca" => "Certificate Authority",
	"server" => "Server Certificate",
	"user" => "User Certificate");

$altname_types = array("DNS", "IP", "email", "URI");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

$pgtitle = array(gettext("System"), gettext("Certificate Manager"), gettext("Certificates"));

if (is_numericint($_GET['userid'])) {
	$userid = $_GET['userid'];
}
if (isset($_POST['userid']) && is_numericint($_POST['userid'])) {
	$userid = $_POST['userid'];
}

if (isset($userid)) {
	$cert_methods["existing"] = gettext("Choose an existing certificate");
	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	$a_user =& $config['system']['user'];
}

if (is_numericint($_GET['id'])) {
	$id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
	$id = $_POST['id'];
}

if (!is_array($config['ca'])) {
	$config['ca'] = array();
}

$a_ca =& $config['ca'];

if (!is_array($config['cert'])) {
	$config['cert'] = array();
}

$a_cert =& $config['cert'];

$internal_ca_count = 0;
foreach ($a_ca as $ca) {
	if ($ca['prv']) {
		$internal_ca_count++;
	}
}

$act = $_GET['act'];

if ($_POST['act']) {
	$act = $_POST['act'];
}

if ($act == "del") {

	if (!isset($a_cert[$id])) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	unset($a_cert[$id]);
	write_config();
	$savemsg = sprintf(gettext("Certificate %s successfully deleted"), htmlspecialchars($a_cert[$id]['descr'])) . "<br />";
	pfSenseHeader("system_certmanager.php");
	exit;
}


if ($act == "new") {
	$pconfig['method'] = $_GET['method'];
	$pconfig['keylen'] = "2048";
	$pconfig['digest_alg'] = "sha256";
	$pconfig['csr_keylen'] = "2048";
	$pconfig['csr_digest_alg'] = "sha256";
	$pconfig['type'] = "user";
	$pconfig['lifetime'] = "3650";
}

if ($act == "exp") {

	if (!$a_cert[$id]) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_cert[$id]['descr']}.crt");
	$exp_data = base64_decode($a_cert[$id]['crt']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "req") {

	if (!$a_cert[$id]) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_cert[$id]['descr']}.req");
	$exp_data = base64_decode($a_cert[$id]['csr']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "key") {

	if (!$a_cert[$id]) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_cert[$id]['descr']}.key");
	$exp_data = base64_decode($a_cert[$id]['prv']);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "p12") {
	if (!$a_cert[$id]) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	$exp_name = urlencode("{$a_cert[$id]['descr']}.p12");
	$args = array();
	$args['friendly_name'] = $a_cert[$id]['descr'];

	$ca = lookup_ca($a_cert[$id]['caref']);
	if ($ca) {
		$args['extracerts'] = openssl_x509_read(base64_decode($ca['crt']));
	}

	$res_crt = openssl_x509_read(base64_decode($a_cert[$id]['crt']));
	$res_key = openssl_pkey_get_private(array(0 => base64_decode($a_cert[$id]['prv']) , 1 => ""));

	$exp_data = "";
	openssl_pkcs12_export($res_crt, $exp_data, $res_key, null, $args);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if ($act == "csr") {
	if (!$a_cert[$id]) {
		pfSenseHeader("system_certmanager.php");
		exit;
	}

	$pconfig['descr'] = $a_cert[$id]['descr'];
	$pconfig['csr'] = base64_decode($a_cert[$id]['csr']);
}

if ($_POST) {
	// This is just the blank altername name that is added for display purposes. We don't want to validate/save it
	if ($_POST['altname_value0'] == "") {
		unset($_POST['altname_type0']);
		unset($_POST['altname_value0']);
	}

	if ($_POST['save'] == gettext("Save")) {
		$input_errors = array();
		$pconfig = $_POST;

		/* input validation */
		if ($pconfig['method'] == "import") {
			$reqdfields = explode(" ",
				"descr cert key");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate data"),
				gettext("Key data"));
			if ($_POST['cert'] && (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))) {
				$input_errors[] = gettext("This certificate does not appear to be valid.");
			}
		}

		if ($pconfig['method'] == "internal") {
			$reqdfields = explode(" ",
				"descr caref keylen type lifetime dn_country dn_state dn_city ".
				"dn_organization dn_email dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Certificate authority"),
				gettext("Key length"),
				gettext("Certificate Type"),
				gettext("Lifetime"),
				gettext("Distinguished name Country Code"),
				gettext("Distinguished name State or Province"),
				gettext("Distinguished name City"),
				gettext("Distinguished name Organization"),
				gettext("Distinguished name Email Address"),
				gettext("Distinguished name Common Name"));
		}

		if ($pconfig['method'] == "external") {
			$reqdfields = explode(" ",
				"descr csr_keylen csr_dn_country csr_dn_state csr_dn_city ".
				"csr_dn_organization csr_dn_email csr_dn_commonname");
			$reqdfieldsn = array(
				gettext("Descriptive name"),
				gettext("Key length"),
				gettext("Distinguished name Country Code"),
				gettext("Distinguished name State or Province"),
				gettext("Distinguished name City"),
				gettext("Distinguished name Organization"),
				gettext("Distinguished name Email Address"),
				gettext("Distinguished name Common Name"));
		}

		if ($pconfig['method'] == "existing") {
			$reqdfields = array("certref");
			$reqdfieldsn = array(gettext("Existing Certificate Choice"));
		}

		$altnames = array();
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
		if ($pconfig['method'] != "import" && $pconfig['method'] != "existing") {
			/* subjectAltNames */
			foreach ($_POST as $key => $value) {
				$entry = '';
				if (!substr_compare('altname_type', $key, 0, 12)) {
					$entry = substr($key, 12);
					$field = 'type';
				} elseif (!substr_compare('altname_value', $key, 0, 13)) {
					$entry = substr($key, 13);
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
						if (!is_hostname($altname['value'], true)) {
							array_push($input_errors, "DNS subjectAltName values must be valid hostnames, FQDNs or wildcard domains.");
						}
						break;
					case "IP":
						if (!is_ipaddr($altname['value'])) {
							array_push($input_errors, "IP subjectAltName values must be valid IP Addresses");
						}
						break;
					case "email":
						if (empty($altname['value'])) {
							array_push($input_errors, "You must provide an e-mail address for this type of subjectAltName");
						}
						if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $altname['value'])) {
							array_push($input_errors, "The e-mail provided in a subjectAltName contains invalid characters.");
						}
						break;
					case "URI":
						/* Close enough? */
						if (!is_URL($altname['value'])) {
							$input_errors[] = "URI subjectAltName types must be a valid URI";
						}
						break;
					default:
						$input_errors[] = "Unrecognized subjectAltName type.";
				}
			}

			/* Make sure we do not have invalid characters in the fields for the certificate */

			if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
				array_push($input_errors, "The field 'Descriptive Name' contains invalid characters.");
			}

			for ($i = 0; $i < count($reqdfields); $i++) {
				if (preg_match('/email/', $reqdfields[$i])) { /* dn_email or csr_dn_name */
					if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST[$reqdfields[$i]])) {
						array_push($input_errors, "The field 'Distinguished name Email Address' contains invalid characters.");
					}
				} else if (preg_match('/commonname/', $reqdfields[$i])) { /* dn_commonname or csr_dn_commonname */
					if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST[$reqdfields[$i]])) {
						array_push($input_errors, "The field 'Distinguished name Common Name' contains invalid characters.");
					}
				} else if (($reqdfields[$i] != "descr") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\.\"\']/", $_POST[$reqdfields[$i]])) {
					array_push($input_errors, "The field '" . $reqdfieldsn[$i] . "' contains invalid characters.");
				}
			}

			if (($pconfig['method'] != "external") && isset($_POST["keylen"]) && !in_array($_POST["keylen"], $cert_keylens)) {
				array_push($input_errors, gettext("Please select a valid Key Length."));
			}
			if (($pconfig['method'] != "external") && !in_array($_POST["digest_alg"], $openssl_digest_algs)) {
				array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
			}

			if (($pconfig['method'] == "external") && isset($_POST["csr_keylen"]) && !in_array($_POST["csr_keylen"], $cert_keylens)) {
				array_push($input_errors, gettext("Please select a valid Key Length."));
			}
			if (($pconfig['method'] == "external") && !in_array($_POST["csr_digest_alg"], $openssl_digest_algs)) {
				array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
			}
		}

		/* if this is an AJAX caller then handle via JSON */
		if (isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}

		/* save modifications */
		if (!$input_errors) {

			if ($pconfig['method'] == "existing") {
				$cert = lookup_cert($pconfig['certref']);
				if ($cert && $a_user) {
					$a_user[$userid]['cert'][] = $cert['refid'];
				}
			} else {
				$cert = array();
				$cert['refid'] = uniqid();
				if (isset($id) && $a_cert[$id]) {
					$cert = $a_cert[$id];
				}

				$cert['descr'] = $pconfig['descr'];

				$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */

				if ($pconfig['method'] == "import") {
					cert_import($cert, $pconfig['cert'], $pconfig['key']);
				}

				if ($pconfig['method'] == "internal") {
					$dn = array(
						'countryName' => $pconfig['dn_country'],
						'stateOrProvinceName' => $pconfig['dn_state'],
						'localityName' => $pconfig['dn_city'],
						'organizationName' => $pconfig['dn_organization'],
						'emailAddress' => $pconfig['dn_email'],
						'commonName' => $pconfig['dn_commonname']);

					if (count($altnames)) {
						$altnames_tmp = "";
						foreach ($altnames as $altname) {
							$altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
						}

						$dn['subjectAltName'] = implode(",", $altnames_tmp);
					}

					if (!cert_create($cert, $pconfig['caref'], $pconfig['keylen'], $pconfig['lifetime'], $dn, $pconfig['type'], $pconfig['digest_alg'])) {
						while ($ssl_err = openssl_error_string()) {
							$input_errors = array();
							array_push($input_errors, "openssl library returns: " . $ssl_err);
						}
					}
				}

				if ($pconfig['method'] == "external") {
					$dn = array(
						'countryName' => $pconfig['csr_dn_country'],
						'stateOrProvinceName' => $pconfig['csr_dn_state'],
						'localityName' => $pconfig['csr_dn_city'],
						'organizationName' => $pconfig['csr_dn_organization'],
						'emailAddress' => $pconfig['csr_dn_email'],
						'commonName' => $pconfig['csr_dn_commonname']);
					if (count($altnames)) {
						$altnames_tmp = "";
						foreach ($altnames as $altname) {
							$altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
						}
						$dn['subjectAltName'] = implode(",", $altnames_tmp);
					}

					if (!csr_generate($cert, $pconfig['csr_keylen'], $dn, $pconfig['csr_digest_alg'])) {
						while ($ssl_err = openssl_error_string()) {
							$input_errors = array();
							array_push($input_errors, "openssl library returns: " . $ssl_err);
						}
					}
				}
				error_reporting($old_err_level);

				if (isset($id) && $a_cert[$id]) {
					$a_cert[$id] = $cert;
				} else {
					$a_cert[] = $cert;
				}

				if (isset($a_user) && isset($userid)) {
					$a_user[$userid]['cert'][] = $cert['refid'];
				}
			}

			if (!$input_errors) {
				write_config();
			}

			if ($userid) {
				post_redirect("system_usermanager.php", array('act' => 'edit', 'userid' => $userid));
				exit;
			}
		}
	}

	if ($_POST['save'] == gettext("Update")) {
		unset($input_errors);
		$pconfig = $_POST;

		/* input validation */
		$reqdfields = explode(" ", "descr cert");
		$reqdfieldsn = array(
			gettext("Descriptive name"),
			gettext("Final Certificate data"));

		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

		if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
			array_push($input_errors, "The field 'Descriptive Name' contains invalid characters.");
		}

//		old way
		/* make sure this csr and certificate subjects match */
//		$subj_csr = csr_get_subject($pconfig['csr'], false);
//		$subj_cert = cert_get_subject($pconfig['cert'], false);
//
//		if (!isset($_POST['ignoresubjectmismatch']) && !($_POST['ignoresubjectmismatch'] == "yes")) {
//			if (strcmp($subj_csr, $subj_cert)) {
//				$input_errors[] = sprintf(gettext("The certificate subject '%s' does not match the signing request subject."), $subj_cert);
//				$subject_mismatch = true;
//			}
//		}
		$mod_csr = csr_get_modulus($pconfig['csr'], false);
		$mod_cert = cert_get_modulus($pconfig['cert'], false);

		if (strcmp($mod_csr, $mod_cert)) {
			// simply: if the moduli don't match, then the private key and public key won't match
			$input_errors[] = sprintf(gettext("The certificate modulus does not match the signing request modulus."), $subj_cert);
			$subject_mismatch = true;
		}

		/* if this is an AJAX caller then handle via JSON */
		if (isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}

		/* save modifications */
		if (!$input_errors) {

			$cert = $a_cert[$id];

			$cert['descr'] = $pconfig['descr'];

			csr_complete($cert, $pconfig['cert']);

			$a_cert[$id] = $cert;

			write_config();

			pfSenseHeader("system_certmanager.php");
		}
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("CAs"), false, "system_camanager.php");
$tab_array[] = array(gettext("Certificates"), true, "system_certmanager.php");
$tab_array[] = array(gettext("Certificate Revocation"), false, "system_crlmanager.php");
display_top_tabs($tab_array);

// Load valid country codes
$dn_cc = array();
if (file_exists("/etc/ca_countries")) {
	$dn_cc_file=file("/etc/ca_countries");
	foreach ($dn_cc_file as $line) {
		if (preg_match('/^(\S*)\s(.*)$/', $line, $matches)) {
			$dn_cc[$matches[1]] = $matches[1];
		}
	}
}

if ($act == "new" || (($_POST['save'] == gettext("Save")) && $input_errors)) {
$form = new Form;

if ($act == "csr" || (($_POST['save'] == gettext("Update")) && $input_errors)) {
	$form->setAction('system_certmanager.php?act=csr');

	$section = new Form_Section('Complete Signing Request');

	if (isset($id) && $a_cert[$id]) {
		$form->addGlobal(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		));
	}

	$section->addInput(new Form_Input(
		'descr',
		'Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Textarea(
		'csr',
		'Signing request data',
		$pconfig['csr']
	))->setReadonly()->setHelp('Copy the certificate signing data from here and '.
		'forward it to your certificate authority for signing.');

	$section->addInput(new Form_Textarea(
		'cert',
		'Final certificate data',
		$pconfig['cert']
	))->setHelp('Paste the certificate received from your certificate authority here.');

	$form->add($section);
	print $form;

	include("foot.inc");
	exit;
}

$form->setAction('system_certmanager.php?act=edit');

if (isset($userid) && $a_user) {
	$form->addGlobal(new Form_Input(
		'userid',
		null,
		'hidden',
		$userid
	));
}

if (isset($id) && $a_cert[$id]) {
	$form->addGlobal(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$section = new Form_Section('Add a new certificate');

if (!isset($id)) {
	$section->addInput(new Form_Select(
		'method',
		'Method',
		$pconfig['method'],
		$cert_methods
	))->toggles();
}

$section->addInput(new Form_Input(
	'descr',
	'Descriptive name',
	'text',
	($a_user && empty($pconfig['descr'])) ? $a_user[$userid]['name'] : $pconfig['descr']
))->addClass('toggle-existing');

$form->add($section);
$section = new Form_Section('Import Certificate');
$section->addClass('toggle-import collapse');

$section->addInput(new Form_Textarea(
	'cert',
	'Certificate data',
	$pconfig['cert']
))->setHelp('Paste a certificate in X.509 PEM format here.');

$section->addInput(new Form_Textarea(
	'key',
	'Private key data',
	$pconfig['key']
))->setHelp('Paste a private key in X.509 PEM format here.');

$form->add($section);
$section = new Form_Section('Internal Certificate');
$section->addClass('toggle-internal collapse');

if (!$internal_ca_count) {
	$section->addInput(new Form_StaticText(
		'Certificate authority',
		gettext('No internal Certificate Authorities have been defined. You must ').
		'<a href="system_camanager.php?act=new&amp;method=internal"> '. gettext(" create") .'</a>'.
		gettext(' an internal CA before creating an internal certificate.')
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
		'Certificate authority',
		$pconfig['caref'],
		$allCas
	));
}

$section->addInput(new Form_Select(
	'keylen',
	'Key length',
	$pconfig['keylen'],
	array_combine($cert_keylens, $cert_keylens)
));

$section->addInput(new Form_Select(
	'digest_alg',
	'Digest Algorithm',
	$pconfig['digest_alg'],
	array_combine($openssl_digest_algs, $openssl_digest_algs)
))->setHelp('NOTE: It is recommended to use an algorithm stronger than '.
	'SHA1 when possible.');

$section->addInput(new Form_Select(
	'type',
	'Certificate Type',
	$pconfig['type'],
	$cert_types
))->setHelp('Type of certificate to generate. Used for placing '.
	'restrictions on the usage of the generated certificate.');

$section->addInput(new Form_Input(
	'lifetime',
	'Lifetime (days)',
	'number',
	$pconfig['lifetime']
));

$section->addInput(new Form_Select(
	'dn_country',
	'Country Code',
	$pconfig['dn_country'],
	$dn_cc
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
	['placeholder' => 'e.g. My Company Inc.']
));

$section->addInput(new Form_Input(
	'dn_email',
	'Email Address',
	'text',
	$pconfig['dn_email'],
	['placeholder' => 'e.g. admin@mycompany.com']
));

$section->addInput(new Form_Input(
	'dn_commonname',
	'Common Name',
	'text',
	$pconfig['dn_commonname'],
	['placeholder' => 'e.g. www.example.com']
));

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
		array(
			'DNS' => 'FQDN or Hostname',
			'IP' => 'IP address',
			'URI' => 'URI',
			'email' => 'email address',
		)
	))->setHelp(($counter == $numrows) ? 'Type':null);

	$group->add(new Form_Input(
		'altname_value' . $counter,
		null,
		'text',
		$item['value']
	))->setHelp(($counter == $numrows) ? 'Value':null);

	$group->add(new Form_Button(
		'deleterow' . $counter,
		'Delete'
	))->removeClass('btn-primary')->addClass('btn-warning');

	$group->addClass('repeatable');

	$section->add($group);

	$counter++;
}

$section->addInput(new Form_Button(
	'addrow',
	'Add'
))->removeClass('btn-primary')->addClass('btn-success');

$form->add($section);
$section = new Form_Section('External Signing Request');
$section->addClass('toggle-external collapse');

$section->addInput(new Form_Select(
	'csr_keylen',
	'Key length',
	$pconfig['csr_keylen'],
	array_combine($cert_keylens, $cert_keylens)
));

$section->addInput(new Form_Select(
	'csr_digest_alg',
	'Digest Algorithm',
	$pconfig['csr_digest_alg'],
	array_combine($openssl_digest_algs, $openssl_digest_algs)
))->setHelp('NOTE: It is recommended to use an algorithm stronger than '.
	'SHA1 when possible');

$section->addInput(new Form_Select(
	'csr_dn_country',
	'Country Code',
	$pconfig['csr_dn_country'],
	$dn_cc
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
	['placeholder' => 'e.g. My Company Inc.']
));

$section->addInput(new Form_Input(
	'csr_dn_email',
	'Email Address',
	'text',
	$pconfig['csr_dn_email'],
	['placeholder' => 'e.g. admin@mycompany.com']
));

$section->addInput(new Form_Input(
	'csr_dn_commonname',
	'Common Name',
	'text',
	$pconfig['csr_dn_commonname'],
	['placeholder' => 'e.g. internal-ca']
));

$form->add($section);
$section = new Form_Section('Choose an Existing Certificate');
$section->addClass('toggle-existing collapse');

$existCerts = array();

foreach ($config['cert'] as $cert)	{
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
		$cert['descr'] .= " <i>In Use</i>";
	}
	if (is_cert_revoked($cert)) {
		$cert['descr'] .= " <b>Revoked</b>";
	}

	$existCerts[ $cert['refid'] ] = $cert['descr'];
}


$section->addInput(new Form_Select(
	'certref',
	'Existing Certificates',
	$pconfig['certref'],
	$existCerts
));

$form->add($section);
print $form;

} else if ($act == "csr" || (($_POST['save'] == gettext("Update")) && $input_errors)) {
	$form = new Form(new Form_Button(
		'save',
		'Update'
	));

	$section = new Form_Section("Complete signing request for " . $pconfig['descr']);

	$section->addInput(new Form_Input(
		'descr',
		'Descriptive name',
		'text',
		$pconfig['descr']
	));

	$section->addInput(new Form_Textarea(
		'csr',
		'Signing request data',
		$pconfig['csr']
	))->setReadonly()
	  ->setWidth(7)
	  ->setHelp('Copy the certificate signing data from here and forward it to your certificate authority for signing.');

	$section->addInput(new Form_Textarea(
		'cert',
		'Final certificate data',
		$pconfig['cert']
	))->setWidth(7)
	  ->setHelp('Paste the certificate received from your certificate authority here.');

	 if (isset($id) && $a_cert[$id]) {
		 $section->addInput(new Form_Input(
			'id',
			null,
			'hidden',
			$id
		 ));

		 $section->addInput(new Form_Input(
			'act',
			null,
			'hidden',
			'csr'
		 ));
	 }

	$form->add($section);
	print($form);
} else {
?>
<div class="table-responsive">
<table class="table table-striped table-hover">
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
foreach ($a_cert as $i => $cert):
	$name = htmlspecialchars($cert['descr']);

	if ($cert['crt']) {
		$subj = cert_get_subject($cert['crt']);
		$issuer = cert_get_issuer($cert['crt']);
		$purpose = cert_get_purpose($cert['crt']);
		list($startdate, $enddate) = cert_get_dates($cert['crt']);

		if ($subj == $issuer) {
			$caname = '<i>'. gettext("self-signed") .'</i>';
		} else {
			$caname = '<i>'. gettext("external").'</i>';
		}

		$subj = htmlspecialchars($subj);
	}

	if ($cert['csr']) {
		$subj = htmlspecialchars(csr_get_subject($cert['csr']));
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
					CA: <b><?=$purpose['ca']?></b>, Server: <b><?=$purpose['server']?></b>
				<?php endif?>
			</td>
			<td><?=$caname?></td>
			<td>
				<?=$subj?>
				<?php if (!$cert['csr']): ?>
				<br />
				<small>
					<?=gettext("Valid From")?>: <b><?=$startdate ?></b><br /><?=gettext("Valid Until")?>: <b><?=$enddate ?></b>
				</small>
				<?php endif?>
			</td>
			<td>
				<?php if (is_cert_revoked($cert)): ?>
					<i>Revoked </i>
				<?php endif?>
				<?php if (is_webgui_cert($cert['refid'])): ?>
					webConfigurator
				<?php endif?>
				<?php if (is_user_cert($cert['refid'])): ?>
					User Cert
				<?php endif?>
				<?php if (is_openvpn_server_cert($cert['refid'])): ?>
					OpenVPN Server
				<?php endif?>
				<?php if (is_openvpn_client_cert($cert['refid'])): ?>
					OpenVPN Client
				<?php endif?>
				<?php if (is_ipsec_cert($cert['refid'])): ?>
					IPsec Tunnel
				<?php endif?>
				<?php if (is_captiveportal_cert($cert['refid'])): ?>
					Captive Portal
				<?php endif?>
			</td>
			<td>
				<?php if (!$cert['csr']): ?>
					<a href="system_certmanager.php?act=exp&amp;id=<?=$i?>" class="fa fa-sign-in" title="<?=gettext("Export Certificate")?>"></a>
					<a href="system_certmanager.php?act=key&amp;id=<?=$i?>" class="fa fa-key" title="<?=gettext("Export Key")?>"></a>
					<a href="system_certmanager.php?act=p12&amp;id=<?=$i?>" class="fa fa-key" title="<?=gettext("Export P12")?>"> P12</a>
				<?php else: ?>
					<a href="system_certmanager.php?act=csr&amp;id=<?=$i?>" class="fa fa-pencil" title="<?=gettext("Update CSR")?>"></a>
					<a href="system_certmanager.php?act=req&amp;id=<?=$i?>" class="fa fa-sign-in" title="<?=gettext("Export Request")?>"></a>
					<a href="system_certmanager.php?act=key&amp;id=<?=$i?>" class="fa fa-key" title="<?=gettext("Export Key")?>"></a>
				<?php endif?>
				<?php if (!cert_in_use($cert['refid'])): ?>
					<a href="system_certmanager.php?act=del&amp;id=<?=$i?>" class="fa fa-trash" title="<?=gettext("Delete")?>"></a>
				<?php endif?>
			</td>
		</tr>
<?php endforeach; ?>
	</tbody>
</table>
</div>

<nav class="action-buttons">
	<a href="?act=new" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<?
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

				$subject = cert_get_subject_array($ca['crt']);

?>
				case "<?=$ca['refid'];?>":
					$('#dn_country').val("<?=$subject[0]['v'];?>");
					$('#dn_state').val("<?=$subject[1]['v'];?>");
					$('#dn_city').val("<?=$subject[2]['v'];?>");
					$('#dn_organization').val("<?=$subject[3]['v'];?>");
					$('#dn_email').val("<?=$subject[4]['v'];?>");
					break;
<?php
			endforeach;
?>
		}
	}

	// ---------- Click checkbox handlers ---------------------------------------------------------

	$('#caref').on('change', function() {
		internalca_change();
	});

	// ---------- On initial page load ------------------------------------------------------------

	internalca_change();

	// Suppress "Delete row" button if there are fewer than two rows
	checkLastRow();

<?php endif; ?>


});
//]]>
</script>
<?php
include('foot.inc');
