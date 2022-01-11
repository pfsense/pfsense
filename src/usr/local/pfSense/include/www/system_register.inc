<?php
/*
 * system_register.inc
 *
 * part of pfSense plus (https://www.netgate.com)
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 */

$FQDN = "https://ews.netgate.com/plus";
$idfile = "/var/db/uniqueid";

// Transmit the NDI and activation token so that user registration is recorded
// $action = "check" determines if this instance is a candidate for registion
// "register" performs the registration
function activate_plus($action, $token) {
	global $g, $FQDN, $idfile;

	$product = (strpos($g['product_label'], 'Plus') === false) ? "CE":"Plus";

	if (file_exists($idfile)) {
		if (function_exists('curl_version')) {
			$post = ['act' => $action, 'uid' => file_get_contents($idfile), 'token' => $token, 'product' => $product];
			$url = $FQDN;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_label'] . '/' . $g['product_version']);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,4);
			$response = curl_exec($ch);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($status == 200) {
				return trim($response);
			}
		}
	}

	return "FAIL";
}
?>