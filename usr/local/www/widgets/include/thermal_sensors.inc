<?php
/*
	$Id: thermal_sensors.inc
	File location:
		\usr\local\www\widgets\include\

	Used by:
		\usr\local\www\widgets\widgets\thermal_sensors.widget.php


*/

//set variable for custom title
$thermal_sensors_widget_title = "Thermal Sensors";
//$thermal_sensors_widget_link = "thermal_sensors.php";


//returns core temp data (from coretemp.ko or amdtemp.ko driver) as "|"-delimited string.
//NOTE: depends on proper config in System >> Advanced >> Miscellaneous tab >> Thermal Sensors section.
function getThermalSensorsData() {
	$_gb = exec("/sbin/sysctl -a | grep temperature", $dfout);
        $dfout_filtered = array_filter($dfout, 'filterNegatives');
        $thermalSensorsData = join("|", $dfout_filtered);
	return $thermalSensorsData;
}

function filterNegatives($negsign) {
        return strpos($negsign, ' -') === false;
}
?>
