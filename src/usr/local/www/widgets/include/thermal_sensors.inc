<?php
/*
 * thermal_sensors.inc
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


//set variable for custom title
$thermal_sensors_widget_title = gettext("Thermal Sensors");
//$thermal_sensors_widget_link = "thermal_sensors.php";
$thermal_sensors_allow_multiple_widget_copies = true;


//returns core temp data (from coretemp.ko or amdtemp.ko driver) as "|"-delimited string.
//NOTE: depends on proper config in System >> Advanced >> Miscellaneous tab >> Thermal Sensors section.
function getThermalSensorsData() {

	$_gb = exec("/sbin/sysctl -aq | grep temperature", $dfout);
	$dfout_filtered = array_filter($dfout, function($v) {
		return strpos($negsign, ' -') === false;
	});
	return join("|", $dfout_filtered);

}
?>
