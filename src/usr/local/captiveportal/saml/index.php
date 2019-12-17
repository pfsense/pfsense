<?php
session_start();
require_once("auth.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

$zone = $_GET["zone"];
if ( $zone != "" ) {
	if ( array_key_exists($zone,$config["captiveportal"]) ) {
		$cp_zone = $config["captiveportal"][$zone];
		if ( array_key_exists("auth_method",$cp_zone) && $cp_zone["auth_method"] == "saml" ) {
			require_once 'settings.php' ;
		}
		else {
			echo "ERROR: Captive Portal Zone $zone, method SAML not enabled";
			exit;
		}
	}
	else {
		echo "ERROR: Captive Portal Zone not valid";
		exit;
	}
}
else {
	echo "ERROR: Select Captive Portal Zone";
	exit;
}

/**
 *  SAML Handler
 */

require_once dirname(__DIR__).'/saml/_toolkit_loader.php';

$auth = new OneLogin_Saml2_Auth($settingsInfo);

if (isset($_GET['slo'])) {
    $returnTo = null;
    $parameters = array();
    $nameId = null;
    $sessionIndex = null;
    $nameIdFormat = null;

    if (isset($_SESSION['samlNameId'])) {
        $nameId = $_SESSION['samlNameId'];
    }
    if (isset($_SESSION['samlNameIdFormat'])) {
        $nameIdFormat = $_SESSION['samlNameIdFormat'];
    }
    if (isset($_SESSION['samlNameIdNameQualifier'])) {
        $nameIdNameQualifier = $_SESSION['samlNameIdNameQualifier'];
    }
    if (isset($_SESSION['samlNameIdSPNameQualifier'])) {
        $nameIdSPNameQualifier = $_SESSION['samlNameIdSPNameQualifier'];
    }
    if (isset($_SESSION['samlSessionIndex'])) {
        $sessionIndex = $_SESSION['samlSessionIndex'];
    }

    $auth->logout($returnTo, $parameters, $nameId, $sessionIndex, false, $nameIdFormat, $nameIdNameQualifier, $nameIdSPNameQualifier);

    # If LogoutRequest ID need to be saved in order to later validate it, do instead
    # $sloBuiltUrl = $auth->logout(null, $paramters, $nameId, $sessionIndex, true);
    # $_SESSION['LogoutRequestID'] = $auth->getLastRequestID();
    # header('Pragma: no-cache');
    # header('Cache-Control: no-cache, must-revalidate');
    # header('Location: ' . $sloBuiltUrl);
    # exit();

} else if (isset($_GET['acs'])) {

    if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
        $requestID = $_SESSION['AuthNRequestID'];
    } else {
        $requestID = null;
    }
    $auth->processResponse($requestID);

    $errors = $auth->getErrors();

    if (!empty($errors)) {
        echo '<p><font color="red">',implode(', ', $errors),'</font></p>';
    }

    if (!$auth->isAuthenticated()) {
        echo "<p>Not authenticated</p>";
        exit();
    }
    else {
	//echo "<p>Authenticated 👌</p>";
	$_SESSION['samlUserdata'] = $auth->getAttributes();
	$_SESSION['samlNameId'] = $auth->getNameId();
	$_SESSION['samlNameIdFormat'] = $auth->getNameIdFormat();
	$_SESSION['samlNameIdNameQualifier'] = $auth->getNameIdNameQualifier();
	$_SESSION['samlNameIdSPNameQualifier'] = $auth->getNameIdSPNameQualifier();
	$_SESSION['samlSessionIndex'] = $auth->getSessionIndex();
	unset($_SESSION['AuthNRequestID']);
	if (isset($_POST['RelayState']) && $_POST['RelayState'] != "" && OneLogin_Saml2_Utils::getSelfURL() != $_POST['RelayState']) {
		$auth->redirectTo($_POST['RelayState']);
	}
	else {
		$url = $_SERVER['REQUEST_URI'];
		$url = str_replace("/saml/","/",$url);
//echo $url;
		$auth->redirectTo($url);
	}
    }

    
} else if (isset($_GET['sls'])) {
    if (isset($_SESSION) && isset($_SESSION['LogoutRequestID'])) {
        $requestID = $_SESSION['LogoutRequestID'];
    } else {
        $requestID = null;
    }

    $auth->processSLO(false, $requestID);
    $errors = $auth->getErrors();
    if (empty($errors)) {
        echo '<p>Sucessfully logged out</p>';
    } else {
        echo '<p>', implode(', ', $errors), '</p>';
    }
}
else {
    $auth->login();

    # If AuthNRequest ID need to be saved in order to later validate it, do instead
    # $ssoBuiltUrl = $auth->login(null, array(), false, false, true);
    # $_SESSION['AuthNRequestID'] = $auth->getLastRequestID();
    # header('Pragma: no-cache');
    # header('Cache-Control: no-cache, must-revalidate');
    # header('Location: ' . $ssoBuiltUrl);
    # exit();
}
/*
if (isset($_SESSION['samlUserdata'])) {
    if (!empty($_SESSION['samlUserdata'])) {
        $attributes = $_SESSION['samlUserdata'];
        echo 'You have the following attributes:<br>';
        echo '<table><thead><th>Name</th><th>Values</th></thead><tbody>';
        foreach ($attributes as $attributeName => $attributeValues) {
            echo '<tr><td>' . htmlentities($attributeName) . '</td><td><ul>';
            foreach ($attributeValues as $attributeValue) {
                echo '<li>' . htmlentities($attributeValue) . '</li>';
            }
            echo '</ul></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo "<p>You don't have any attribute</p>";
    }

    echo '<p><a href="?slo" >Logout</a></p>';
} else {
    echo '<p><a href="?sso" >Login</a></p>';
    echo '<p><a href="?sso2" >Login and access to attrs.php page</a></p>';
}
*/