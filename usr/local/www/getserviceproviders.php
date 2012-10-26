<?php
/*
    getserviceproviders.php
    Copyright (C) 2010 Vinicius Coque <vinicius.coque@bluepex.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	ajax
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
$serviceproviders_attr = xml2array($serviceproviders_contents,1,"attr");

$serviceproviders = &$serviceproviders_attr['serviceproviders']['country'];

function get_country_providers($country) {
	global $serviceproviders;
	foreach($serviceproviders as $sp) {
		if($sp['attr']['code'] == strtolower($country)) {
			return is_array($sp['provider'][0]) ? $sp['provider'] : array($sp['provider']);
		}
	}
	return $provider_list;
}

function country_list() {
	global $serviceproviders;
	$country_list = get_country_name("ALL");
	foreach($serviceproviders as $sp) {
		foreach($country_list as $country) {
			if(strtoupper($sp['attr']['code']) == $country['code']) {
				echo $country['name'] . ":" . $country['code'] . "\n";
			}
		}
	}
}

function providers_list($country) {
	$serviceproviders = get_country_providers($country);
	foreach($serviceproviders as $sp) {
		echo $sp['name']['value'] . "\n";
	}
}

function provider_plan_data($country,$provider,$connection) {
	Header("Content-type: application/xml; charset=iso-8859-1");
	echo "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>\n";
	echo "<connection>\n";
	$serviceproviders = get_country_providers($country);
	foreach($serviceproviders as $sp) {
		if(strtolower($sp['name']['value']) == strtolower($provider)) {
			if(strtoupper($connection) == "CDMA") {
				$conndata = $sp['cdma'];
			} else {
				if(!is_array($sp['gsm']['apn'][0])) {
					$conndata = $sp['gsm']['apn'];
				} else {
					foreach($sp['gsm']['apn'] as $apn) {
						if($apn['attr']['value'] == $connection) {
							$conndata = $apn;
							break;
						}
					}
				}
			}
			if(is_array($conndata)) {
				echo "<apn>" . $connection . "</apn>\n";
				echo "<username>" . $conndata['username']['value'] . "</username>\n";
				echo "<password>" . $conndata['password']['value'] . "</password>\n";

				$dns_arr = is_array($conndata['dns'][0]) ? $conndata['dns'] : array( $conndata['dns'] );
				foreach($dns_arr as $dns) {
					echo '<dns>' . $dns['value'] . "</dns>\n";
				}
			}
			break;
		}
	}
	echo "</connection>";
}

function provider_plans_list($country,$provider) {
	$serviceproviders = get_country_providers($country);
	foreach($serviceproviders as $sp) {
		if(strtolower($sp['name']['value']) == strtolower($provider)) {
			if(array_key_exists('gsm',$sp)) {
				if(array_key_exists('attr',$sp['gsm']['apn'])) {
					$name = ($sp['gsm']['apn']['name'] ? $sp['gsm']['apn']['name'] : $sp['name']['value']);
					echo $name . ":" . $sp['gsm']['apn']['attr']['value'];
				} else {
					foreach($sp['gsm']['apn'] as $apn_info) {
						$name = ($apn_info['name']['value'] ? $apn_info['name']['value'] : $apn_info['gsm']['apn']['name']);
						echo $name . ":" . $apn_info['attr']['value'] . "\n";
					}
				}
			}
			if(array_key_exists('cdma',$sp)) {
				$name = $sp['cdma']['name']['value'] ? $sp['cdma']['name']['value']:$sp['name']['value'];
				echo $name . ":" . "CDMA";
			}
		}
	}
}

$_GET_OR_POST = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

if(isset($_GET_OR_POST['country']) && !isset($_GET_OR_POST['provider'])) {
	providers_list($_GET_OR_POST['country']);
} elseif(isset($_GET_OR_POST['country']) && isset($_GET_OR_POST['provider'])) {
	if(isset($_GET_OR_POST['plan']))
		provider_plan_data($_GET_OR_POST['country'],$_GET_OR_POST['provider'],$_GET_OR_POST['plan']);
	else
		provider_plans_list($_GET_OR_POST['country'],$_GET_OR_POST['provider']);
} else {
	country_list();
}
?>
