<?php

function step2_submitphpaction() {
	if ($_POST['authtype'] == "local") {
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3");
		exit;
	} else if ($_POST['authtype'] == "ldap") {
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1");
		exit;
	} else if ($_POST['authtype'] == "radius") {
		header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=2");
		exit;
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
}

function step7_submitphpaction() {

}

function step9_submitphpaction() {
	global $config;

	$pconfig = $config['ovpnserver'];

	if (!is_array($config['ovpnserver'])) {
		$message = "No configuration found please retry again.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=1&message={$message}");
                exit;
	}

	if (empty($pconfig['step5']['certca'])) {
		$message = "Please choose a CA authority.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
	} else if (!($ca = lookup_ca($pconfig['step5']['certca']))) {
		$message = "Not a valid CA authority specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=3&message={$message}");
                exit;
	}
	if (empty($pconfig['step5']['certname'])) {
		$message = "Please choose a Certificate.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
	} else if (!($cert = lookup_cert($pconfig['step5']['certname']))) {
                $message = "Not a valid Certificate specified.";
                header("Location:wizard.php?xml=openvpn_wizard.xml&stepid=4&message={$message}");
                exit;
        }
	$server = array();
	$server['vpnid'] = openvpn_vpnid_next();

}
?>
