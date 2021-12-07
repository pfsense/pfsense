
<?php
/*
 * system_certmanager.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("config.gui.inc");
require_once("certs.inc");
require_once("pfsense-utils.inc");

$cert_types = array(
	"server" => "Server Certificate",
	"user" => "User Certificate"
);

init_config_arr(array('cert'));
$a_cert = &$config['cert'];

// Gather the certificate information required to display a list of certificates in the UI.
// Return the result in an array which roughly corresponds to the display columns.
// This code was previously in /usr/local/www/system_certmanager.php but was moved here so
// other processes can access it. (MVC)
function getCertData($html = true) {
	global $a_cert;

	$certificates = array();
	$idx = 0;
	foreach ($a_cert as $cert) {
		if (is_array($cert) && !empty($cert)) {
			$certificates[] = parseCert($idx, $html);
		}

		$idx++;
	}

	return $certificates;
}

function parseCert($idx, $html) {
	global $cert_types, $a_cert;

	$cert = $a_cert[$idx];

    // Optionally eliminate the HTML tags
    $em = $notem = $ital = $notital = $bold = $notbold = ""; $br = "\n";

	if ($html) {
		$br = "<br />";
		$ital = "<i>";
		$noital = "</i>";
		$bold = "<b>";
		$notbold = "</b>";
		$em = "<em>";
		$notem = "</em>";
	}

	$pluginparams = array();
	$pluginparams['type'] = 'certificates';
	$pluginparams['event'] = 'used_certificates';
	$certificates_used_by_packages = pkg_call_plugins('plugin_certificates', $pluginparams);
	$name = htmlspecialchars($cert['descr']);

	if ($cert['crt']) {
		$subj = cert_get_subject($cert['crt']);
		$issuer = cert_get_issuer($cert['crt']);
		$purpose = cert_get_purpose($cert['crt']);

		if ($subj == $issuer) {
			$caname = $ital . gettext("self-signed") . $notital;
		} else {
			$caname = $ital. gettext("external"). $notital;
		}

		$subj = htmlspecialchars(cert_escape_x509_chars($subj, true));
	} else {
		$subj = "";
		$issuer = "";
		$purpose = "";
		$startdate = "";
		$enddate = "";
		$caname = $em . gettext("private key only") . $notem;
	}

	if ($cert['csr']) {
		$subj = htmlspecialchars(cert_escape_x509_chars(csr_get_subject($cert['csr']), true));
		$caname = $em . gettext("external - signature pending") . $notem;
	}

	$ca = lookup_ca($cert['caref']);
	if ($ca) {
		$caname = $ca['descr'];
	}

	$cinfo = array();

	$cinfo['name'] = $name . $br;
	if ($cert['type']) {
		$cinfo['name'] .= ($ital . $cert_types[$cert['type']] . $notital . $rb);
	}

	if (is_array($purpose)) {
		$cinfo['name'] .= ("CA: " . $bold . $purpose['ca'] . $notbold . $br . gettext("Server") . ": " . $bold . $purpose['server'] . $notbold . $br);
	}

	$cinfo['issuer'] = $caname;
	$cinfo['subj'] = $subj;
	$cinfo['info'] = cert_print_infoblock($cert);
	$cinfo['dates'] = cert_print_dates($cert);
	$cinfo['refid'] = $cert['refid'];
	$cinfo['csr'] = $cert['csr'];
	$cinfo['prv'] = $cert['prv'];

	$cinfo['inuse'] = "";
	if (is_cert_revoked($cert)) {
		$cinfo['inuse'] .= ($ital . gettext("Revoked") . $notital);
	}

	if (is_webgui_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("webConfigurator");
	}

	if (is_user_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("User Cert");
	}

	if (is_openvpn_server_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("OpenVPN Server");
	}

	if (is_openvpn_client_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("OpenVPN Client");
	}

	if (is_ipsec_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("IPsec Tunnel");
	}

	if (is_captiveportal_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("Captive Portal");
	}

	if (is_unbound_cert($cert['refid'])) {
		$cinfo['inuse'] .= gettext("DNS Resolver");
	}

	$cinfo['inuse'] .= cert_usedby_description($cert['refid'], $certificates_used_by_packages);

	return $cinfo;
}

// Return an array containing the IDs of all CAs
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

// Return an array containing the IDs of all CSRs
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