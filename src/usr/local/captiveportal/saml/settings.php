<?
require_once("auth.inc");

$zone = $_GET["zone"];
if ( $zone != "" ) {
	if ( array_key_exists($zone,$config["captiveportal"]) ) {
		$cp_zone = $config["captiveportal"][$zone];
		if ( array_key_exists("auth_method",$cp_zone) && $cp_zone["auth_method"] == "saml" ) {
			// Settings
			$ssl = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );
			$spr = strtolower( $_SERVER['SERVER_PROTOCOL'] );
			$protocol = substr( $spr, 0, strpos( $spr, '/' ) ) . ( ( $ssl ) ? 's' : '' );
			$spBaseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/saml";
			$sp = array (
				'entityId' => $spBaseUrl . '/index.php?zone='.$zone,
				'assertionConsumerService' => array (
					'url' => $spBaseUrl.'/index.php?acs&zone='.$zone,
				),
				'singleLogoutService' => array (
					'url' => $spBaseUrl.'/index.php?sls',
				),
				'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
        		);
			// check decode base64
			if ( is_base64_encoded( $cp_zone['saml_idp_x509cert'] ) ) {
				$x509 = base64_decode( $cp_zone['saml_idp_x509cert'] );
			}
			else {
				$x509 = $cp_zone['saml_idp_x509cert'];
			}

			$idp = array (
				'entityId' => $cp_zone['saml_idp_entity_id'],
				'singleSignOnService' => array (
					'url' => $cp_zone['saml_idp_login'],
				),
				'singleLogoutService' => array (
					'url' => $cp_zone['saml_idp_logout'],
				),
				'x509cert' => $x509,
			);
			$settingsInfo = array (
				'debug' => true,
				'sp' => $sp,
				'idp' => $idp,
		        );
		}
	}
}

function is_base64_encoded($data)
{
    if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
       return TRUE;
    } else {
       return FALSE;
    }
};
