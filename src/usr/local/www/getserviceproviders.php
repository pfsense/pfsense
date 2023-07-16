<?php
/*
 * getserviceproviders.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Vinicius Coque <vinicius.coque@bluepex.com>
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
##|*IDENT=page-getserviceproviders
##|*NAME=AJAX: Get Service Providers
##|*DESCR=Allow access to the 'AJAX: Service Providers' page.
##|*MATCH=getserviceproviders.php*
##|-PRIV
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

$serviceproviders_xml = "/usr/local/share/mobile-broadband-provider-info/serviceproviders.xml";
$serviceproviders_contents = file_get_contents($serviceproviders_xml);
$serviceproviders_attr = xml2array($serviceproviders_contents, 1, "attr");

$serviceproviders = &$serviceproviders_attr['serviceproviders']['country'];

function get_country_providers($country) {
	global $serviceproviders;
	foreach ($serviceproviders as $sp) {
		if (array_get_path($sp, 'attr/code', '') == strtolower($country)) {
			return is_array(array_get_path($sp, 'provider/0')) ? array_get_path($sp, 'provider') : [ array_get_path($sp, 'provider') ];
		}
	}
	$provider_list = (is_array($provider_list)) ? $provider_list : array();
	return $provider_list;
}

function country_list() {
	global $serviceproviders;
	$country_list = get_country_name();
	foreach ($serviceproviders as $sp) {
		foreach ($country_list as $country) {
			if (!is_array($country) || empty($country)) {
				continue;
			}
			if (strtoupper(array_get_path($sp, 'attr/code')) == array_get_path($country, 'code')) {
				echo array_get_path($country, 'name', '') . ":" . array_get_path($country, 'code', '') . "\n";
			}
		}
	}
}

function providers_list($country) {
	$serviceproviders = get_country_providers($country);
	if (is_array($serviceproviders)) {
		foreach ($serviceproviders as $sp) {
			echo array_get_path($sp, 'name/value', '') . "\n";
		}
	} else {
		$serviceproviders = array();
	}
}

function provider_plan_data($country, $provider, $connection) {
	header("Content-type: application/xml;");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<connection>\n";
	$serviceproviders = get_country_providers($country);
	foreach ($serviceproviders as $sp) {
		if (strtolower(array_get_path($sp, 'name/value', '')) == strtolower($provider)) {
			if (strtoupper($connection) == "CDMA") {
				$conndata = array_get_path($sp, 'cdma');
			} else {
				if (!is_array(array_get_path($sp, 'gsm/apn/0'))) {
					$conndata = array_get_path($sp, 'gsm/apn');
					$connection = array_get_path($sp, 'gsm/apn/attr/value', $connection);
				} else {
					foreach (array_get_path($sp, 'gsm/apn', []) as $apn) {
						if (array_get_path($apn, 'attr/value') == $connection) {
							$conndata = $apn;
							break;
						}
					}
				}
			}
			if (is_array($conndata)) {
				echo "<apn>" . htmlentities($connection) . "</apn>\n";
				echo "<username>" . htmlentities(array_get_path($conndata, 'username/value', '')) . "</username>\n";
				echo "<password>" . htmlentities(array_get_path($conndata, 'password/value', '')) . "</password>\n";

				$dns_arr = is_array(array_get_path($conndata, 'dns/0')) ? array_get_path($conndata, 'dns') : [ array_get_path($conndata, 'dns') ];
				foreach ($dns_arr as $dns) {
					if (is_array($dns) && !empty($dns)) {
						echo '<dns>' . array_get_path($dns, 'value') . "</dns>\n";
					}
				}
			}
			break;
		}
	}
	echo "</connection>";
}

function provider_plans_list($country, $provider) {
	$serviceproviders = get_country_providers($country);
	foreach ($serviceproviders as $sp) {
		if (strtolower(array_get_path($sp, 'name/value', '')) == strtolower($provider)) {
			if (array_key_exists('gsm', $sp)) {
				if (array_key_exists('attr', array_get_path($sp, 'gsm/apn', []))) {
					$name = array_get_path($sp, 'gsm/apn/name/value', array_get_path($sp, 'name/value', ''));
					echo $name . ":" . array_get_path($sp, 'gsm/apn/attr/value', '');
				} else {
					foreach (array_get_path($sp, 'gsm/apn', []) as $apn_info) {
						$name = array_get_path($apn_info, 'name/value', array_get_path($apn_info, 'gsm/apn/name', ''));
						echo $name . ":" . array_get_path($apn_info, 'attr/value', '') . "\n";
					}
				}
			}
			if (array_key_exists('cdma', $sp)) {
				$name = array_get_path($sp, 'cdma/name/value', array_get_path($sp, 'name/value', ''));
				echo $name . ":" . "CDMA";
			}
		}
	}
}

if (!empty($_POST)) {
	if (isset($_POST['country']) && !isset($_POST['provider'])) {
		providers_list($_POST['country']);
	} elseif (isset($_POST['country']) && isset($_POST['provider'])) {
		if (isset($_POST['plan'])) {
			provider_plan_data($_POST['country'], $_POST['provider'], $_POST['plan']);
		} else {
			provider_plans_list($_POST['country'], $_POST['provider']);
		}
	} else {
		country_list();
	}
}
?>
