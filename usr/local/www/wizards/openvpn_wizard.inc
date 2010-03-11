<?php
require_once("openvpn.inc");

function step1_submitphpaction() {
	global $stepid, $config;
	if ($_POST['authtype'] == "local") {
		$config['ovpnserver']['step1']['type'] = "local";
        	write_config();
        	header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=5");
        	exit;
	} else if ($_POST['authtype'] == "ldap") {
		$stepid = 0;
	} else if ($_POST['authtype'] == "radius") {
		$stepid = 1;
		$config['ovpnserver']['step1']['type'] = "radius";
		unset($config['ovpnserver']['step1']['uselist']);
                write_config();
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=2");
                exit;
	}
}

function step2_stepbeforeformdisplay() {
	global $pkg;

	$fields =& $pkg['step'][1]['fields']['field'];
	
	$count = 0;
	$authlist = auth_get_authserver_list();
	$fields[0]['options']['option'] = array();
	foreach ($authlist as $i => $auth) {
		if ($auth['type'] != "ldap") {
			$count++;
			continue;
		}
               	$opts = array();
               	$opts['name'] = $auth['name'];
               	$opts['value'] = $auth['name'];
               	$fields[2]['options']['option'][] = $opts;
	}
	if ($count < 1)
                $stepid = 2;
}

function step2_submitphpaction() {
	global $stepid, $config;

	$config['ovpnserver']['step2']['authserv'] = $_POST['authserv'];
	unset($config['ovpnserver']['step2']['uselist']);
	write_config();
	header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3");
	exit;
}

function step3_submitphpaction() {

	if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['port']) ||
	    empty($_POST['transport']) || empty($_POST['scope']) || empty($_POST['authscope']) || empty($_POST['nameattr'])) {
		$message = "Please enter all information for authentication server.";
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
		exit;
	}
	$_POST['uselist'] = "on";
}

function step4_stepbeforeformdisplay() {
        global $pkg;

        $fields =& $pkg['step'][3]['fields']['field'];

	$count = 0;
        $authlist = auth_get_authserver_list();
       	$fields[0]['options']['option'] = array();
       	foreach ($authlist as $i => $auth) {
               	if ($auth['type'] != "radius") {
			$count++;
                       	continue;
		}
               	$opts = array();
               	$opts['name'] = $auth['name'];
               	$opts['value'] = $auth['name'];
               	$fields[2]['options']['option'][] = $opts;
        }
	if ($count < 1)
                $stepid = 4;
}

function step4_submitphpaction() {
        global $stepid, $config;

        $config['ovpnserver']['step2']['authserv'] = $_POST['authserv'];
        unset($config['ovpnserver']['step2']['uselist']);
	write_config();
        header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4");
        exit;
}

function step5_submitphpaction() {
	global $stepid;

        if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['port']) ||
            empty($_POST['passowrd'])) {
                $message = "Please enter all information for authentication server.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
        }
	$_POST['uselist'] = "on";
}

function step6_stepbeforeformdisplay() {
	global $stepid, $config;

	if (count($config['system']['ca']) < 1) {
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=6");
                exit;
	}
}

function step6_submitphpaction() {
	global $config;

	$config['ovpnserver']['step6']['authcertca'] = $_POST['authcertca'];
        unset($config['ovpnserver']['step6']['uselist']);
	write_config();
        header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=7");
        exit;
}

function step7_submitphpaction() {
	
	if (empty($_POST['name']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
            empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
            empty($_POST['organization']) || empty($_POST['email']) || empty($_POST['cn'])) {
                $message = "Please enter all information for the new CA authority.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=6&message={$message}");
                exit;
        }
	$_POST['uselist'] = "on";
}

function step8_stepbeforeformdisplay() {
        global $stepid, $config;

        if (count($config['system']['cert']) < 1 ||
		(count($config['system']['cert']) == 1 && stristr($config['system']['cert'][0]['name'], "webconf"))) {
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=8");
                exit;
        }
}

function step8_submitphpaction() {
	global $config;

        $config['ovpnserver']['step9']['authcertname'] = $_POST['authcertname'];
        unset($config['ovpnserver']['step6']['uselist']);
        write_config();
        header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=9");
        exit;
}

function step9_submitphpaction() {
	global $stepid;

        if (empty($_POST['name']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
	    empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
	    empty($_POST['organization']) || empty($_POST['email']) || empty($_POST['cn'])) {
                $message = "Please enter all information for the new certificate.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=8&message={$message}");
                exit;
        }
	$_POST['uselist'] = "on";
}

function step10_stepbeforeformdisplay() {
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

function step10_submitphpaction() {
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

function step12_submitphpaction() {
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

	if (isset($pconfig['step6']['uselist'])) {
		$ca = array();
		$ca['refid'] = uniqid();
		$ca['name'] = $pconfig['step6']['certname'];
		$dn = array(
			'countryName' => $pconfig['step6']['country'],
			'stateOrProvinceName' => $pconfig['step6']['state'],
			'localityName' => $pconfig['step6']['city'],
			'organizationName' => $pconfig['step6']['organization'],
			'emailAddress' => $pconfig['step6']['email'],
			'commonName' => $pconfig['step6']['cn']);

		ca_create($ca, $pconfig['step6']['keylength'], $pconfig['step6']['lifetime'], $dn);
		if (!is_array($config['system']['ca']))
			$config['system']['ca'] = array();

		$config['system']['ca'][] = $ca;
	} else if (!isset($pconfig['step6']['uselist']) && empty($pconfig['step6']['authcertca'])) {
		$message = "Please choose a CA authority.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=5&message={$message}");
                exit;
	} else if (!($ca = lookup_ca($pconfig['step6']['authcertca']))) {
		$message = "Not a valid CA authority specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=5&message={$message}");
                exit;
	}

	if (isset($pconfig['step9']['uselist'])) {
                $cert = array();
                $cert['refid'] = uniqid();
                $cert['name'] = $pconfig['step9']['certname'];
                $dn = array(
                        'countryName' => $pconfig['step9']['country'],
                        'stateOrProvinceName' => $pconfig['step9']['state'],
                        'localityName' => $pconfig['step9']['city'],
                        'organizationName' => $pconfig['step9']['organization'],
                        'emailAddress' => $pconfig['step9']['email'],
                        'commonName' => $pconfig['step9']['cn']);

                cert_create($cert, $ca['refid'], $pconfig['step9']['keylength'], $pconfig['step9']['lifetime'], $dn);
                if (!is_array($config['system']['cert']))
                        $config['system']['cert'] = array();

                $config['system']['cert'][] = $cert;
	} else if (!isset($pconfig['step6']['uselist']) && empty($pconfig['step9']['authcertname'])) {
		$message = "Please choose a Certificate.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=7&message={$message}");
                exit;
	} else if (!($cert = lookup_cert($pconfig['step9']['authcertname']))) {
                $message = "Not a valid Certificate specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=7&message={$message}");
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
	$server['protocol'] = $pconfig['step10']['protocol'];
	$server['interface'] = $pconfig['step10']['interface'];
	if (isset($pconfig['step10']['localport']))
		$server['localport'] = $pconfig['step10']['localport'];
	$server['description'] = $pconfig['step10']['descr'];
	$server['custom_options'] = $pconfig['step10']['advanced'];
	if (isset($pconfig['step10']['tlsauth'])) {
		if (isset($pconfig['step10']['gentlskey']))
			$tlskey = openvpn_create_key();
		else
			$tlskey = $pconfig['step10']['tlskey'];
		$server['tls'] = base64_encode($tlskey);
	}
	$server['dh_length'] = $pconfig['step10']['dhkey'];
	$server['tunnel_network'] = $pconfig['step10']['tunnelnet'];
	if (isset($pconfig['step10']['rdrgw']))
		$server['gwredir'] = $pconfig['step10']['rdrgw'];
	if (isset($pconfig['step10']['localnet']))
		$server['local_network'] = $pconfig['step10']['localnet'];
	if (isset($pconfig['step10']['remotenet']))
		$server['remote_network'] = $pconfig['step10']['remotenet'];
	if (isset($pconfig['step10']['concurrentcon']))
		$server['maxclients'] = $pconfig['step10']['concurrentcon'];
	if (isset($pconfig['step10']['compression']))
		$server['compression'] = $pconfig['step10']['compression'];
	if (isset($pconfig['step10']['tos']))
		$server['passtos'] = $pconfig['step10']['tos'];
	if (isset($pconfig['step10']['interclient']))
		$server['client2client'] = $pconfig['step10']['interclient'];
	if (isset($pconfig['step10']['addrpool']))
		$server['pool_enable'] = $pconfig['step10']['addrpool'];
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
		if (isset($pconfig['step10']['nbtscope']))
			$server['netbios_scope'] = $pconfig['step10']['nbtscope'];
		$server['netbios_enable'] = $pconfig['step10']['nbtenable'];
	}
	$server['crypto'] = $pconfig['step10']['crypto'];
	
	if (!is_array($config['openvpn']['openvpn-server']))
		$config['openvpn']['openvpn-server'] = array();

	$config['openvpn']['openvpn-server'][] = $server;

	openvpn_resync('server', $server);
	write_config();
	header("Location: vpn_openvpn_server.php");
	exit;
}

?>
