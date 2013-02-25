<?php
/*
    $Id: coretemp_widget.inc
  File location: 
		\usr\local\www\widgets\include\

	Used by:
		\usr\local\www\widgets\widgets\coretemp_widget.widget.php
		\usr\local\www\widgets\include\coretemp_widget.php
		
	Depends on:
		32bit: http://files.pfsense.org/jimp/ko-8.1/i386/coretemp.ko
		64bit: http://files.pfsense.org/jimp/ko-8.1/amd64/coretemp.ko  (?)


*/

//set variable for custom title
$coretemp_widget_title = "Core Temperatures";
//$coretemp_widget_link = "coretemp.php";


//returns core temp data (from coretemp.ko driver) as "|"-delimited string
function getCoreTempData(){

	exec("/sbin/sysctl -a | grep temperature", $dfout);
	$coretempData = join("|", $dfout);
	return $coretempData;
}
?>
