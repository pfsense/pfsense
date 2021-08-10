<?php
/*
 * openvpn_wizard.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
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

require_once("auth.inc");
require_once("certs.inc");
require_once("config.inc");
require_once("openvpn.inc");
require_once("util.inc");

// Load valid country codes
$dn_cc = get_cert_country_codes();

function openvpn_wizard_get_dhparams() {
	global $openvpn_dh_lengths;
	$dhparams = array();
	foreach ($openvpn_dh_lengths as $value => $name) {
		$dhparams[] = array(
			"value" => $value,
			"name" => $name
		);
	}
	return $dhparams;
}

function step1_stepsubmitbeforesave() {
	global $stepid, $config;
	if (($_POST['authtype'] != NULL) && ($_POST['authtype'] != $config['ovpnserver']['step1']['type'])) {
		if (isset($config['ovpnserver']['step2'])) {
			unset($config['ovpnserver']['step2']);
		}
	}
}

function step1_submitphpaction() {
	global $stepid, $config;
	if ($_POST['authtype'] == "local") {
		$stepid = 4;
		$config['ovpnserver']['step1']['type'] = "local";
	} else if ($_POST['authtype'] == "ldap") {
		$stepid = 0;
	} else if ($_POST['authtype'] == "radius") {
		$stepid = 2;
		$config['ovpnserver']['step1']['type'] = "radius";
		if (isset($config['ovpnserver']['step1']['uselist'])) {
			unset($config['ovpnserver']['step1']['uselist']);
		}
	}
}

function step2_stepbeforeformdisplay() {
	global $pkg, $stepid;

	$fields = &$pkg['step'][1]['fields']['field'];

	$found = false;
	$authlist = auth_get_authserver_list();
	$fields[1]['options']['option'] = array();
	foreach ($authlist as $i => $auth) {
		if ($auth['type'] != "ldap")
			continue;
		$found = true;
		$opts = array();
		$opts['name'] = $auth['name'];
		$opts['value'] = $i;
		$fields[1]['options']['option'][] = $opts;
	}
	if ($found == false) {
		$stepid = 2;
	}
}

function step2_submitphpaction() {
	global $stepid;

	if (isset($_POST['next'])) {
		$_POST['uselist'] = "";
		$stepid +=3;
	}
}

function step3_submitphpaction() {
	global $stepid, $savemsg, $config;

	/* Default LDAP port is 389 for TCP and 636 for SSL */
	if (empty($_POST['port'])) {
		if (($_POST['transport'] == "Standard TCP") || ($_POST['transport'] == "STARTTLS Encrypted"))
			$config['ovpnserver']['step2']['port'] = 389;
		elseif ($_POST['transport'] == "SSL/TLS Encrypted")
			$config['ovpnserver']['step2']['port'] = 636;
	} elseif (!is_port($_POST['port'])) {
		$stepid--;
		$savemsg = "Please enter a valid port number.";
	}

	if (empty($_POST['name']) || empty($_POST['ip']) ||empty($_POST['transport']) ||
	     empty($_POST['scope']) || empty($_POST['basedn']) || empty($_POST['authscope']) || empty($_POST['nameattr'])) {
		$stepid--;
		$savemsg = "Please enter all information for authentication server.";
	} else if (!empty(auth_get_authserver($_POST['name']))) {
		$stepid--;
		$savemsg = "Please choose a different name because an authentication server with this name already exists.";
	} elseif (!is_fqdn($_POST['ip']) && !is_ipaddr($_POST['ip'])) {
		$stepid--;
		$savemsg = "Please enter a valid IP address or hostname for the authentication server.";
	} else {
		$config['ovpnserver']['step2']['uselist'] = "on";
		$_POST['uselist'] = "on";
		$stepid += 2;
	}
}

function step4_stepbeforeformdisplay() {
	global $pkg, $stepid;

	$fields = &$pkg['step'][3]['fields']['field'];

	$found = false;
	$authlist = auth_get_authserver_list();
	$fields[1]['options']['option'] = array();
	foreach ($authlist as $i => $auth) {
		if ($auth['type'] != "radius")
			continue;
		$found = true;
		$opts = array();
		$opts['name'] = $auth['name'];
		$opts['value'] = $i;
		$fields[1]['options']['option'][] = $opts;
	}
	if ($found == false)
		$stepid = 4;
}

function step4_submitphpaction() {
	global $stepid;

	if (isset($_POST['next'])) {
		$_POST['uselist'] = "";
		$stepid++;
	}
}

function step5_submitphpaction() {
	global $stepid, $savemsg, $config;

	/* Default RADIUS Auth port = 1812 */
	if (empty($_POST['port'])) {
		$config['ovpnserver']['step2']['port'] = 1812;
	} elseif (!is_port($_POST['port'])) {
		$stepid--;
		$savemsg = "Please enter a valid port number.";
	}

	if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['secret'])) {
		$stepid--;
		$savemsg = "Please enter all information for authentication server.";
	} else if (!empty(auth_get_authserver($_POST['name']))) {
		$stepid--;
		$savemsg = "Please choose a different name because an authentication server with this name already exists.";
	} elseif (!is_fqdn($_POST['ip']) && !is_ipaddr($_POST['ip'])) {
		$stepid--;
		$savemsg = "Please enter a valid IP address or hostname for the authentication server.";
	} else {
		$config['ovpnserver']['step2']['uselist'] = "on";
		$_POST['uselist'] = "on";
	}
}

function step6_stepbeforeformdisplay() {
	global $stepid, $config;

	if (!is_array($config['ca']) || count($config['ca']) < 1) {
		$stepid++;
	}
}

function step6_submitphpaction() {
	global $stepid, $config;

	if (isset($_POST['next'])) {
		$_POST['uselist'] = "";
		if (isset($config['ovpnserver']['step6']['uselist'])) {
			unset($config['ovpnserver']['step6']['uselist']);
		}
		$stepid++;
	} else {
		$config['ovpnserver']['step6']['uselist'] = "on";
		$_POST['uselist'] = "on";
	}
}

function step7_submitphpaction() {
	global $input_errors, $stepid, $savemsg, $_POST, $config, $dn_cc;

	$canames = array();
	$cacns = array();
	if (is_array($config['ca'])) {
		foreach ($config['ca'] as $ca) {
			$canames[] = $ca['descr'];
			$cainfo = cert_get_subject_hash($ca['crt']);
			$cacns[] = $cainfo["CN"];
		}
	}

	if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
		$input_errors[] = "The field 'Descriptive Name' contains invalid characters.";
	}

	if (empty($_POST['descr']) || empty($_POST['keylength']) || empty($_POST['lifetime'])) {
		$stepid--;
		$savemsg = "Please enter all information for the new Certificate Authority.";
	} elseif (!empty($_POST['country']) && !array_key_exists($_POST['country'], $dn_cc)) {
		$stepid--;
		$input_errors[] = "Please enter a valid country code.";
	} elseif (in_array($_POST['descr'], $canames) || in_array($_POST['descr'], $cacns)) {
		$stepid--;
		$savemsg = "Please enter a different name for the Certificate Authority. A Certificate Authority with that name already exists.";
	} else {
		$config['ovpnserver']['step6']['uselist'] = "on";
		$_POST['uselist'] = "on";
	}
}

function step8_stepbeforeformdisplay() {
	global $stepid, $config;

	if (count($config['cert']) < 1 ||
		(count($config['cert']) == 1 && stristr($config['cert'][0]['descr'], "webconf"))) {
		$stepid++;
	}
}

function step8_submitphpaction() {
	global $stepid, $config, $_POST;

	if (isset($_POST['next'])) {
		$_POST['uselist'] = "";
		if (isset($config['ovpnserver']['step9']['uselist'])) {
			unset($config['ovpnserver']['step9']['uselist']);
		}
		$stepid++;
	} else {
		$config['ovpnserver']['step9']['uselist'] = "on";
		$_POST['uselist'] = "on";
	}
}

function step9_stepbeforeformdisplay() {
	global $config, $pkg, $stepid;

	$pconfig = $config['ovpnserver'];

	if (isset($pconfig['step6']['uselist'])) {
		$country = $pconfig['step6']['country'];
		$state = $pconfig['step6']['state'];
		$city = $pconfig['step6']['city'];
		$org = $pconfig['step6']['organization'];
	} else {
		$ca = lookup_ca($pconfig['step6']['authcertca']);
		$cavl = cert_get_subject_hash($ca['crt']);
		$country = $cavl['C'];
		$state = $cavl['ST'];
		$city = $cavl['L'];
		$org = $cavl['O'];
	}
	$fields = &$pkg['step'][$stepid]['fields']['field'];

	foreach ($fields as $idx => $field) {
		switch ($field['name']) {
		case 'country':
			$fields[$idx]['value'] = $country;
			break;
		case 'state':
			$fields[$idx]['value'] = $state;
			break;
		case 'city':
			$fields[$idx]['value'] = $city;
			break;
		case 'organization':
			$fields[$idx]['value'] = $org;
			break;
		}
	}
}

function step9_submitphpaction() {
	global $input_errors, $stepid, $savemsg, $_POST, $config, $dn_cc;

	$certnames = array();
	$certcns = array();
	if (is_array($config['cert'])) {
		foreach ($config['cert'] as $cert) {
			$certnames[] = $cert['descr'];
			$certinfo = cert_get_subject_hash($cert['crt']);
			$certcns[] = $certinfo["CN"];
		}
	}

	if (preg_match("/[\?\>\<\&\/\\\"\']/", $_POST['descr'])) {
		$input_errors[] = "The field 'Descriptive Name' contains invalid characters.";
	}

	if (empty($_POST['descr']) || empty($_POST['keylength']) || empty($_POST['lifetime'])) {
		$stepid--;
		$savemsg = "Please enter all information for the new certificate.";
	} elseif (!array_key_exists($_POST['country'], $dn_cc)) {
		$stepid--;
		$input_errors[] = "Please enter a valid country code.";
	} elseif (in_array($_POST['descr'], $certnames) || in_array($_POST['descr'], $certcns)) {
		$stepid--;
		$savemsg = "Please enter a different name for the Certificate. A Certificate with that name/common name already exists.";
	} else {
		$config['ovpnserver']['step9']['uselist'] = "on";
		$_POST['uselist'] = "on";
	}
}

function step10_stepbeforeformdisplay() {
	global $pkg, $stepid, $netbios_nodetypes;

	foreach ($pkg['step'][$stepid]['fields']['field'] as $idx => $field) {
		if ($field['name'] == "dataciphersfallback") {
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			$cipherlist = openvpn_get_cipherlist();
			foreach ($cipherlist as $name => $desc) {
				$opt = array();
				$opt['name'] = $desc;
				$opt['value'] = $name;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "digest") {
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			$digestlist = openvpn_get_digestlist();
			foreach ($digestlist as $name => $desc) {
				$opt = array();
				$opt['name'] = $desc;
				$opt['value'] = $name;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "allowcompression") {
			global $openvpn_allow_compression;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			foreach ($openvpn_allow_compression as $name => $desc) {
				$opt = array();
				$opt['name'] = $desc;
				$opt['value'] = $name;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "compression") {
			global $openvpn_compression_modes;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			foreach ($openvpn_compression_modes as $name => $desc) {
				$opt = array();
				$opt['name'] = $desc;
				$opt['value'] = $name;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "engine") {
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			$engines = openvpn_get_engines();
			foreach ($engines as $name => $desc) {
				$opt = array();
				$opt['name'] = $desc;
				$opt['value'] = $name;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "nbttype") {
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			foreach ($netbios_nodetypes as $type => $name) {
				$opt = array();
				$opt['name'] = $name;
				$opt['value'] = $type;
			$pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		} else if ($field['name'] == "localport") {
			$pkg['step'][$stepid]['fields']['field'][$idx]['value'] = openvpn_port_next('UDP');
		}
	}
}

function step10_submitphpaction() {
	global $savemsg, $stepid;

	$input_errors = array();

	/* Default OpenVPN port to next available port if left empty. */
	if (empty($_POST['localport']))
		$pconfig["step10"]["localport"] = openvpn_port_next('UDP');

	/* input validation */
	if ($result = openvpn_validate_port($_POST['localport'], 'Local port'))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($_POST['tunnelnet'], 'Tunnel Network', false, "ipv4"))
		$input_errors[] = $result;

	if ($result = openvpn_validate_cidr($_POST['localnet'], 'Local Network', true, "ipv4"))
		$input_errors[] = $result;

	$portused = openvpn_port_used($_POST['protocol'], $_POST['interface'], $_POST['localport']);
	if ($portused != 0)
		$input_errors[] = "The specified 'Local port' is in use. Please enter a port not already in use.";

	if (!isset($_POST['generatetlskey']) && isset($_POST['tlsauthentication']))
		if (!strstr($_POST['tlssharedkey'], "-----BEGIN OpenVPN Static key V1-----") ||
			!strstr($_POST['tlssharedkey'], "-----END OpenVPN Static key V1-----"))
			$input_errors[] = "The field 'TLS Authentication Key' does not appear to be valid";

	if (!empty($_POST['dnsserver1']) && !is_ipaddr(trim($_POST['dnsserver1'])))
		$input_errors[] = "The field 'DNS Server #1' must contain a valid IP address";
	if (!empty($_POST['dnsserver2']) && !is_ipaddr(trim($_POST['dnsserver2'])))
		$input_errors[] = "The field 'DNS Server #2' must contain a valid IP address";
	if (!empty($_POST['dnsserver3']) && !is_ipaddr(trim($_POST['dnsserver3'])))
		$input_errors[] = "The field 'DNS Server #3' must contain a valid IP address";
	if (!empty($_POST['dnsserver4']) && !is_ipaddr(trim($_POST['dnsserver4'])))
		$input_errors[] = "The field 'DNS Server #4' must contain a valid IP address";

	if (!empty($_POST['ntpserver1']) && !is_ipaddr(trim($_POST['ntpserver1'])))
		$input_errors[] = "The field 'NTP Server #1' must contain a valid IP address";
	if (!empty($_POST['ntpserver2']) && !is_ipaddr(trim($_POST['ntpserver2'])))
		$input_errors[] = "The field 'NTP Server #2' must contain a valid IP address";

	if (!empty($_POST['winsserver1']) && !is_ipaddr(trim($_POST['winsserver1'])))
		$input_errors[] = "The field 'WINS Server #1' must contain a valid IP address";
	if (!empty($_POST['winsserver2']) && !is_ipaddr(trim($_POST['winsserver2'])))
		$input_errors[] = "The field 'WINS Server #2' must contain a valid IP address";

	if ($_POST['concurrentcon'] && !is_numeric($_POST['concurrentcon']))
		$input_errors[] = "The field 'Concurrent connections' must be numeric.";

	if (empty($_POST['tunnelnet']))
		$input_errors[] = "A 'Tunnel network' must be specified.";


	if (count($input_errors) > 0) {
		$savemsg = $input_errors[0];
		$stepid = $stepid - 1;
	}
}

function step12_submitphpaction() {
	global $config;

	$pconfig = $config['ovpnserver'];

	if (!is_array($config['ovpnserver'])) {
		$message = "No configuration found, please try again.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	}

	if ($pconfig['step1']['type'] == "local") {
		$auth = array();
		$auth['name'] = "Local Database";
		$auth['type'] = "local";
	} else if (isset($pconfig['step2']['uselist'])) {
		$auth = array();
		$auth['type'] = $pconfig['step1']['type'];
		$auth['refid'] = uniqid();
		$auth['name'] = $pconfig['step2']['authtype'];

		if ($auth['type'] == "ldap") {
			$auth['host'] = $pconfig['step2']['ip'];
			$auth['ldap_port'] = $pconfig['step2']['port'];
			$auth['ldap_urltype'] = $pconfig['step2']['transport'];
			$auth['ldap_caref'] = $pconfig['step2']['ldap_caref'];
			$auth['ldap_protver'] = $pconfig['step2']['ldap_protver'];
			$auth['ldap_timeout'] = $pconfig['step2']['ldap_timeout'];
			$auth['ldap_scope'] = $pconfig['step2']['scope'];
			$auth['ldap_basedn'] = $pconfig['step2']['basedn'];
			$auth['ldap_authcn'] = $pconfig['step2']['authscope'];
			$auth['ldap_extended_enabled'] = isset($pconfig['step2']['ldap_extended_enabled']);
			$auth['ldap_extended_query'] = $pconfig['step2']['ldap_extended_query'];
			$auth['ldap_binddn'] = $pconfig['step2']['userdn'];
			$auth['ldap_bindpw'] = $pconfig['step2']['passdn'];
			$auth['ldap_attr_user'] = $pconfig['step2']['nameattr'];
			$auth['ldap_attr_member'] = $pconfig['step2']['memberattr'];
			$auth['ldap_attr_group'] = $pconfig['step2']['groupattr'];
			$auth['ldap_rfc2307'] = isset($pconfig['step2']['ldap_rfc2307']);
			$auth['ldap_attr_groupobj'] = $pconfig['step2']['ldap_attr_groupobj'];
			$auth['ldap_utf8'] = isset($pconfig['step2']['ldap_utf8']);
			$auth['ldap_nostrip_at'] = isset($pconfig['step2']['ldap_nostrip_at']);
			$auth['ldap_allow_unauthenticated'] = isset($pconfig['step2']['ldap_allow_unauthenticated']);

		} else if ($auth['type'] == "radius") {
			$auth['host'] = $pconfig['step2']['ip'];
			$auth['radius_auth_port'] = $pconfig['step2']['port'];
			$auth['radius_secret'] = $pconfig['step2']['password'];
			$auth['radius_srvcs'] = "auth";
		}
		if (!is_array($config['system']['authserver']))
			$config['system']['authserver'] = array();

		$config['system']['authserver'][] = $auth;
	} else if (!isset($pconfig['step2']['uselist']) && empty($pconfig['step2']['authserv'])) {
		$message = "Please choose an authentication server .";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	} else if (!($auth = auth_get_authserver($pconfig['step2']['authserv']))) {
		$message = "An invalid authentication server has been specified.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	}

	if (isset($pconfig['step6']['uselist']) && !empty($pconfig['step6']['certca'])) {
		$ca = array();
		$ca['refid'] = uniqid();
		$ca['descr'] = $pconfig['step6']['certca'];

		$dn = array('commonName' => $pconfig['step6']['certca']);
		if (!empty($pconfig['step6']['country'])) {
			$dn['countryName'] = $pconfig['step6']['country'];
		}
		if (!empty($pconfig['step6']['state'])) {
			$dn['stateOrProvinceName'] = $pconfig['step6']['state'];
		}
		if (!empty($pconfig['step6']['city'])) {
			$dn['localityName'] = $pconfig['step6']['city'];
		}
		if (!empty($pconfig['step6']['organization'])) {
			$dn['organizationName'] = $pconfig['step6']['organization'];
		}

		ca_create($ca, $pconfig['step6']['keylength'], $pconfig['step6']['lifetime'], $dn, "sha256");
		if (!is_array($config['ca']))
			$config['ca'] = array();

		$config['ca'][] = $ca;
	} else if (!isset($pconfig['step6']['uselist']) && empty($pconfig['step6']['authcertca'])) {
		$message = "Please choose a Certificate Authority.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=5&message={$message}");
		exit;
	} else if (!($ca = lookup_ca($pconfig['step6']['authcertca']))) {
		$message = "An invalid Certificate Authority has been specified.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=5&message={$message}");
		exit;
	}

	if (isset($pconfig['step9']['uselist'])) {
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = $pconfig['step9']['certname'];

		$dn = array(
			'commonName' => $pconfig['step9']['certname'],
			'subjectAltName' => cert_add_altname_type($pconfig['step9']['certname']),
			);
		if (!empty($pconfig['step9']['country'])) {
			$dn['countryName'] = $pconfig['step9']['country'];
		}
		if (!empty($pconfig['step9']['state'])) {
			$dn['stateOrProvinceName'] = $pconfig['step9']['state'];
		}
		if (!empty($pconfig['step9']['city'])) {
			$dn['localityName'] = $pconfig['step9']['city'];
		}
		if (!empty($pconfig['step9']['organization'])) {
			$dn['organizationName'] = $pconfig['step9']['organization'];
		}

		cert_create($cert, $ca['refid'], $pconfig['step9']['keylength'], $pconfig['step9']['lifetime'], $dn, 'server', "sha256");
		if (!is_array($config['cert']))
			$config['cert'] = array();

		$config['cert'][] = $cert;
	} else if (!isset($pconfig['step9']['uselist']) && empty($pconfig['step9']['authcertname'])) {
		$message = "Please choose a Certificate.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=7&message={$message}");
		exit;
	} else if (!($cert = lookup_cert($pconfig['step9']['authcertname']))) {
		$message = "An invalid Certificate has been specified.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=7&message={$message}");
		exit;
	}
	$server = array();
	$server['vpnid'] = openvpn_vpnid_next();
	switch ($auth['type']) {
		case "ldap":
			$server['mode'] = "server_user";
			$server['authmode'] = $auth['name'];
			break;
		case "radius":
			$server['mode'] = "server_user";
			$server['authmode'] = $auth['name'];
			break;
		default:
			$server['mode'] = "server_tls_user";
			$server['authmode'] = "Local Database";
			break;
	}
	$server['protocol'] = $pconfig['step10']['protocol'];
	$server['dev_mode'] = 'tun';
	$server['interface'] = $pconfig['step10']['interface'];
	if (isset($pconfig['step10']['localport']))
		$server['local_port'] = $pconfig['step10']['localport'];

	if (strlen($pconfig['step10']['descr']) > 30)
		$pconfig['step10']['descr'] = substr($pconfig['step10']['descr'], 0, 30);
	$server['description'] = $pconfig['step10']['descr'];
	if (isset($pconfig['step10']['tlsauth'])) {
		if (isset($pconfig['step10']['gentlskey']))
			$tlskey = openvpn_create_key();
		else
			$tlskey = $pconfig['step10']['tlskey'];
		$server['tls'] = base64_encode($tlskey);
	}
	$server['tls_type'] = 'auth';
	$server['caref'] = $ca['refid'];
	$server['certref'] = $cert['refid'];
	$server['dh_length'] = $pconfig['step10']['dhkey'];
	$server['ncp_enable'] = $pconfig['step10']['ncpenable'];
	$server['data_ciphers'] = is_array($pconfig['step10']['dataciphers']) ? implode(',', $pconfig['step10']['dataciphers']) : $pconfig['step10']['dataciphers'];
	$server['data_ciphers_fallback'] = $pconfig['step10']['dataciphersfallback'];
	$server['digest'] = $pconfig['step10']['digest'];
	$server['engine'] = $pconfig['step10']['engine'];
	$server['tunnel_network'] = $pconfig['step10']['tunnelnet'];
	if (isset($pconfig['step10']['rdrgw']))
		$server['gwredir'] = $pconfig['step10']['rdrgw'];
	if (isset($pconfig['step10']['localnet']))
		$server['local_network'] = $pconfig['step10']['localnet'];
	if (isset($pconfig['step10']['concurrentcon']))
		$server['maxclients'] = $pconfig['step10']['concurrentcon'];
	if (isset($pconfig['step10']['allowcompression']))
		$server['allow_compression'] = $pconfig['step10']['allowcompression'];
	if (isset($pconfig['step10']['compression']))
		$server['compression'] = $pconfig['step10']['compression'];
	if (isset($pconfig['step10']['tos']))
		$server['passtos'] = $pconfig['step10']['tos'];
	if (isset($pconfig['step10']['interclient']))
		$server['client2client'] = $pconfig['step10']['interclient'];
	if (isset($pconfig['step10']['duplicate_cn']))
		$server['duplicate_cn'] = $pconfig['step10']['duplicate_cn'];
	if (isset($pconfig['step10']['dynip']) && ($pconfig['step10']['dynip'] == "on"))
		$server['dynamic_ip'] = "yes";
	$server['topology'] = $pconfig['step10']['topology'];
	if (isset($pconfig['step10']['defaultdomain']))
		$server['dns_domain'] = $pconfig['step10']['defaultdomain'];
	if (isset($pconfig['step10']['dns1']))
		$server['dns_server1'] = $pconfig['step10']['dns1'];
	if (isset($pconfig['step10']['dns2']))
		$server['dns_server2'] = $pconfig['step10']['dns2'];
	if (isset($pconfig['step10']['dns3']))
		$server['dns_server3'] = $pconfig['step10']['dns3'];
	if (isset($pconfig['step10']['dns4']))
		$server['dns_server4'] = $pconfig['step10']['dns4'];
	if (isset($pconfig['step10']['ntp1']))
		$server['ntp_server1'] = $pconfig['step10']['ntp1'];
	if (isset($pconfig['step10']['ntp2']))
		$server['ntp_server2'] = $pconfig['step10']['ntp2'];
	if (isset($pconfig['step10']['wins1']))
		$server['wins_server1'] = $pconfig['step10']['wins1'];
	if (isset($pconfig['step10']['wins2']))
		$server['wins_server2'] = $pconfig['step10']['wins2'];
	if (isset($pconfig['step10']['nbtenable'])) {
		$server['netbios_ntype'] = $pconfig['step10']['nbttype'];
		if (isset($pconfig['step10']['nbtscope'])) {
			$server['netbios_scope'] = $pconfig['step10']['nbtscope'];
		}

		$server['netbios_enable'] = $pconfig['step10']['nbtenable'];
	}
	$server['exit_notify'] = 1;
	$server['inactive_seconds'] = 300;

	$tracker = (int)microtime(true);
	if (isset($pconfig['step11']['ovpnrule'])) {
		$rule = array();
		$rule['descr'] = sprintf(gettext("OpenVPN %s wizard"),$server['description']);
		/* Ensure the rule descr is not too long for pf to handle */
		if (strlen($rule['descr']) > 52) {
			$rule['descr'] = substr($rule['descr'], 0, 52);
		}
		$rule['direction'] = "in";
		$rule['source']['any'] = TRUE;
		$rule['destination']['network'] = $server['interface'] . "ip";
		$rule['destination']['port'] = $server['local_port'];
		$rule['interface'] = $server['interface'];
		$rule['protocol'] = strtolower(substr($server['protocol'], 0, 3));
		$rule['type'] = "pass";
		$rule['enabled'] = "on";
		$rule['tracker'] = $tracker++;
		$rule['created'] = make_config_revision_entry(null, gettext("OpenVPN Wizard"));

		$config['filter']['rule'][] = $rule;
	}
	if (isset($pconfig['step11']['ovpnallow'])) {
		$rule = array();
		$rule['descr'] = sprintf(gettext("OpenVPN %s wizard"),$server['description']);
		/* Ensure the rule descr is not too long for pf to handle */
		if (strlen($rule['descr']) > 52)
			$rule['descr'] = substr($rule['descr'], 0, 52);
		$rule['source']['any'] = TRUE;
		$rule['destination']['any'] = TRUE;
		$rule['interface'] = "openvpn";
		//$rule['protocol'] = $server['protocol'];
		$rule['type'] = "pass";
		$rule['enabled'] = "on";
		$rule['tracker'] = $tracker++;
		$rule['created'] = make_config_revision_entry(null, gettext("OpenVPN Wizard"));
		$config['filter']['rule'][] = $rule;
	}

	if (!is_array($config['openvpn'])) {
		$config['openvpn'] = array();
	}

	if (!is_array($config['openvpn']['openvpn-server'])) {
		$config['openvpn']['openvpn-server'] = array();
	}

	$config['openvpn']['openvpn-server'][] = $server;

	openvpn_resync('server', $server);
	write_config(gettext("OpenVPN configuration saved via OpenVPN Remote Access Server setup wizard."));
	header("Location: vpn_openvpn_server.php");
	exit;
}

function openvpn_wizard_build_if_list() {
	$list = array();

	foreach (openvpn_build_if_list() as $iface => $ifacename) {
	   $list[] = array("name" => $ifacename, "value" => $iface);
	}

	return($list);
}
?>
