<?php
require_once("openvpn.inc");

function step2_submitphpaction() {
	global $stepid;
	if ($_POST['authtype'] == "local") {
		$stepid = 2;
	} else if ($_POST['authtype'] == "ldap") {
		$stepid = 0;
	} else if ($_POST['authtype'] == "radius") {
		$stepid = 1;
	}
}

function step3_stepbeforeformdisplay() {
	global $pkg;

	$fields =& $pkg['step'][1]['fields']['field'];
	
	$authlist = auth_get_authserver_list();

	$fields[2]['options']['option'] = array();
	foreach ($authlist as $i => $auth) {
		if ($auth['type'] != "ldap")
			continue;
                $opts = array();
                $opts['name'] = $auth['name'];
                $opts['value'] = $auth['name'];
                $fields[2]['options']['option'][] = $opts;
	}
}

function step3_submitphpaction() {
	global $stepid;

	if (!$_POST['uselist'] && empty($_POST['authserv'])) {
		$message = "Please choose an authentication server or create a new one.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	} else if (!$_POST['uselist'] && !empty($_POST['authserv'])) {
		$stepid = 2;
	} else if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['port']) ||
	    empty($_POST['transport']) || empty($_POST['scope']) || empty($_POST['authscope']) || empty($_POST['nameattr'])) {
		$message = "Please enter all information for authentication server.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	}
	$stepid = 2;
}

function step4_stepbeforeformdisplay() {
        global $pkg;

        $fields =& $pkg['step'][2]['fields']['field'];

        $authlist = auth_get_authserver_list();

        $fields[2]['options']['option'] = array();
        foreach ($authlist as $i => $auth) {
                if ($auth['type'] != "radius")
                        continue;
                $opts = array();
                $opts['name'] = $auth['name'];
                $opts['value'] = $auth['name'];
                $fields[2]['options']['option'][] = $opts;
        }
}

function step4_submitphpaction() {
	global $stepid;

        if ($_POST['uselist'] == "yes" && !empty($_POST['authserv'])) {
                $message = "Please choose an authentication server or create a new one.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=2&message={$message}");
                exit;
        } else if (!$_POST['uselist'] && !empty($_POST['authserv'])) {
		$stepid = 2;
        } else if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['port']) ||
            empty($_POST['passowrd'])) {
                $message = "Please enter all information for authentication server.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=2&message={$message}");
                exit;
        }
}

function step5_submitphpaction() {
	global $stepid;

        if ($_POST['uselist'] == "yes" && !empty($_POST['certca'])) {
                $message = "Please choose an certificate authority or create a new one.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
        } else if (!$_POST['uselist'] && !empty($_POST['certca'])) {
		$stepid = 3;
        } else if (empty($_POST['name']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
	    empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
	    empty($_POST['organization']) || empty($_POST['email']) || empty($_POST['cn'])) {
                $message = "Please enter all information for the new CA authority.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
        }
	$stepid = 3;
}

function step6_submitphpaction() {
	global $stepid;

        if ($_POST['uselist'] == "yes" && !empty($_POST['certname'])) {
                $message = "Please choose an certificate or create a new one.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
        } else if (!$_POST['uselist'] && !empty($_POST['certname'])) {
		$stepid = 4;
        } else if (empty($_POST['name']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
            empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
            empty($_POST['organization']) || empty($_POST['email']) || empty($_POST['cn'])) {
                $message = "Please enter all information for the new certificate.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
        }
	$stepid = 4;
}

function step7_stepbeforeformdisplay() {
	global $pkg, $stepid, $netbios_nodetypes;

	foreach ($pkg['step'][$stepid]['fields']['field'] as $idx => $field) {
		if ($field['name'] == "crypto") {
                        $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			$cipherlist = openvpn_get_cipherlist();
			foreach ($cipherlist as $name => $desc)
				$opt = array();
                                $opt['name'] = $desc;
                                $opt['value'] = $name;
                        $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
		} else if ($field['name'] == "nbttype") {
                        $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
			foreach ($netbios_nodetypes as $type => $name) {
				$opt = array();
				$opt['name'] = $name;
				$opt['value'] = $type;
                        $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
			}
		}
	}
}

function step7_submitphpaction() {
	global $savemsg, $stepid;

	/* input validation */
        if ($result = openvpn_validate_port($_POST['localport'], 'Local port'))
                $input_errors[] = $result;

        if ($result = openvpn_validate_cidr($_POST['tunnelnet'], 'Tunnel network'))
                $input_errors[] = $result;

        if ($result = openvpn_validate_cidr($_POST['remotenet'], 'Remote network'))
                $input_errors[] = $result;

        if ($result = openvpn_validate_cidr($_POST['localnet'], 'Local network'))
                $input_errors[] = $result;

	$portused = openvpn_port_used($_POST['protocol'], $_POST['localport']);
	if ($portused != 0)
                $input_errors[] = "The specified 'Local port' is in use. Please select another value";
	
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
		$input_errors[] = "You must specify a 'Tunnel network'.";

	if (count($input_errors) > 0) {
		$savemsg = $input_errors[0];
		$stepid = $stepid - 1;
	}
}

function step9_submitphpaction() {
	global $config;

	$pconfig = $config['ovpnserver'];

	if (!is_array($config['ovpnserver'])) {
		$message = "No configuration found please retry again.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
                exit;
	}

	if (isset($pconfig['step2']['uselist'])) {
		$auth = array();
		$auth['type'] = $pconfig['step1']['type'];
		$auth['refid'] = uniqid();
		$auth['name'] = $pconfig['step2']['authtype'];
		
		if ($auth['type'] == "ldap") {
			$auth['host'] = $pconfig['step2']['ip'];
			$auth['ldap_port'] = $pconfig['step2']['port'];
			if ($pconfig['step1']['transport'] == "tcp")
				$auth['ldap_urltype'] = 'TCP - Standard';
			else
				$auth['ldap_urltype'] = 'SSL - Encrypted';
			$auth['ldap_protver'] = 3;
			$auth['ldap_scope'] = $pconfig['step2']['scope'];
			$auth['ldap_authcn'] = $pconfig['step2']['authscope'];
			$auth['ldap_binddn'] = $pconfig['step2']['userdn'];
			$auth['ldap_bindpw'] = $pconfig['step2']['passdn'];
			$auth['ldap_attr_user'] = $pconfig['step1']['nameattr'];
			$auth['ldap_attr_member'] = $pconfig['step1']['memberattr'];
			$auth['ldap_attr_group'] = $pconfig['step1']['groupattr'];
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
		$message = "Not a valid authentication server has been specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
                exit;
	}

	if (isset($pconfig['step5']['uselist'])) {
		$ca = array();
		$ca['refid'] = uniqid();
		$ca['name'] = $pconfig['step5']['certname'];
		$dn = array(
			'countryName' => $pconfig['step5']['country'],
			'stateOrProvinceName' => $pconfig['step5']['state'],
			'localityName' => $pconfig['step5']['city'],
			'organizationName' => $pconfig['step5']['organization'],
			'emailAddress' => $pconfig['step5']['email'],
			'commonName' => $pconfig['step6']['cn']);

		ca_create($ca, $pconfig['step5']['keylength'], $pconfig['step5']['lifetime'], $dn);
		if (!is_array($config['system']['ca']))
			$config['system']['ca'] = array();

		$config['system']['ca'][] = $ca;
	} else if (!isset($pconfig['step5']['uselist']) && empty($pconfig['step5']['authcertca'])) {
		$message = "Please choose a CA authority.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
	} else if (!($ca = lookup_ca($pconfig['step5']['authcertca']))) {
		$message = "Not a valid CA authority specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
	}

	if (isset($pconfig['step6']['uselist'])) {
                $cert = array();
                $cert['refid'] = uniqid();
                $cert['name'] = $pconfig['step6']['certname'];
                $dn = array(
                        'countryName' => $pconfig['step6']['country'],
                        'stateOrProvinceName' => $pconfig['step6']['state'],
                        'localityName' => $pconfig['step6']['city'],
                        'organizationName' => $pconfig['step6']['organization'],
                        'emailAddress' => $pconfig['step6']['email'],
                        'commonName' => $pconfig['step6']['cn']);

                cert_create($cert, $ca['refid'], $pconfig['step6']['keylength'], $pconfig['step6']['lifetime'], $dn);
                if (!is_array($config['system']['cert']))
                        $config['system']['cert'] = array();

                $config['system']['cert'][] = $cert;
	} else if (!isset($pconfig['step6']['uselist']) && empty($pconfig['step6']['authcertname'])) {
		$message = "Please choose a Certificate.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
	} else if (!($cert = lookup_cert($pconfig['step6']['authcertname']))) {
                $message = "Not a valid Certificate specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
        }
	$server = array();
	$server['vpnid'] = openvpn_vpnid_next();
	switch ($auth['type']) {
		case "ldap":
                	$server['authmode'] = $auth['name'];
                        $server['mode'] = "server_user";
                        break;
                case "radius":
                        $server['authmode'] = $auth['name'];
                        $server['mode'] = "server_user";
                        break;
                default:
                        $server['authmode'] = "Local Database";
                        $server['mode'] = "server_tls_user";
                        break;
	}
	$server['caref'] = $ca['refid'];
	$server['certref'] = $cert['refid'];
	$server['protocol'] = $pconfig['step7']['protocol'];
	$server['interface'] = $pconfig['step7']['interface'];
	if (isset($pconfig['step7']['localport']))
		$server['localport'] = $pconfig['step7']['localport'];
	$server['description'] = $pconfig['step7']['descr'];
	$server['custom_options'] = $pconfig['step7']['advanced'];
	if (isset($pconfig['step7']['tlsauth'])) {
		if (isset($pconfig['step7']['gentlskey']))
			$tlskey = openvpn_create_key();
		else
			$tlskey = $pconfig['step7']['tlskey'];
		$server['tls'] = base64_encode($tlskey);
	}
	$server['dh_length'] = $pconfig['step7']['dhkey'];
	$server['tunnel_network'] = $pconfig['step7']['tunnelnet'];
	if (isset($pconfig['step7']['rdrgw']))
		$server['gwredir'] = $pconfig['step7']['rdrgw'];
	if (isset($pconfig['step7']['localnet']))
		$server['local_network'] = $pconfig['step7']['localnet'];
	if (isset($pconfig['step7']['remotenet']))
		$server['remote_network'] = $pconfig['step7']['remotenet'];
	if (isset($pconfig['step7']['concurrentcon']))
		$server['maxclients'] = $pconfig['step7']['concurrentcon'];
	if (isset($pconfig['step7']['compression']))
		$server['compression'] = $pconfig['step7']['compression'];
	if (isset($pconfig['step7']['tos']))
		$server['passtos'] = $pconfig['step7']['tos'];
	if (isset($pconfig['step7']['interclient']))
		$server['client2client'] = $pconfig['step7']['interclient'];
	if (isset($pconfig['step7']['addrpool']))
		$server['pool_enable'] = $pconfig['step7']['addrpool'];
	if (isset($pconfig['step7']['defaultdomain']))
		$server['dns_domain'] = $pconfig['step7']['defaultdomain'];
	if (isset($pconfig['step7']['dns1']))
		$server['dns_server1'] = $pconfig['step7']['dns1'];
	if (isset($pconfig['step7']['dns2']))
		$server['dns_server2'] = $pconfig['step7']['dns2'];
	if (isset($pconfig['step7']['dns3']))
		$server['dns_server3'] = $pconfig['step7']['dns3'];
	if (isset($pconfig['step7']['dns4']))
		$server['dns_server4'] = $pconfig['step7']['dns4'];
	if (isset($pconfig['step7']['ntp1']))
		$server['ntp_server1'] = $pconfig['step7']['ntp1'];
	if (isset($pconfig['step7']['ntp2']))
		$server['ntp_server2'] = $pconfig['step7']['ntp2'];
	if (isset($pconfig['step7']['wins1']))
		$server['wins_server1'] = $pconfig['step7']['wins1'];
	if (isset($pconfig['step7']['wins2']))
		$server['wins_server2'] = $pconfig['step7']['wins2'];
	if (isset($pconfig['step7']['nbtenable'])) {
		$server['netbios_ntype'] = $pconfig['step7']['nbttype'];
		if (isset($pconfig['step7']['nbtscope']))
			$server['netbios_scope'] = $pconfig['step7']['nbtscope'];
		$server['netbios_enable'] = $pconfig['step7']['nbtenable'];
	}
	$server['crypto'] = $pconfig['step7']['crypto'];
	
	if (!is_array($config['openvpn']['openvpn-server']))
		$config['openvpn']['openvpn-server'] = array();

	$config['openvpn']['openvpn-server'][] = $server;

	openvpn_resync('server', $server);
	write_config();
	header("Location: vpn_openvpn_server.php");
	exit;
}
?>
