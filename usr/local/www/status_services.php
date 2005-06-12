#!/usr/local/bin/php
<?php
/*
    services_status.php
    Copyright (C) 2004, 2005 Scott Ullrich
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

require("guiconfig.inc");
require("xmlparse_pkg.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

function find_package_description($package) {
    global $g, $config;
    if(!file_exists("{$g['tmp_path']}/pkg_config.xml"))
	    fetch_latest_pkg_config();
    $pkg_config = parse_xml_config_pkg("{$g['tmp_path']}/pkg_config.xml", "pfsensepkgs");
    foreach($pkg_config['packages']['package'] as $index) {
	if($index['name'] == $package) 
	    return $index['descr'];
    }
    return;
}

function get_package_rcd_details($extd) {
	global $package_name, $executable_name, $description, $raw_name;
	$raw_name = str_replace(".sh","",$extd);
	$package_name = "";
	$executable_name = "";
	/* XXX: needs a get_pkg_description($packagename) function */
	$description = "";	
	$file_contents = return_filename_as_string("/usr/local/etc/rc.d/{$extd}");
        if (preg_match_all("/\# PACKAGE\: (.*)\n/",$file_contents,$match_array))
            $package_name = $match_array[1][0];
        if (preg_match_all("/\# EXECUTABLE\: (.*)\n/",$file_contents,$match_array))
            $executable_name = $match_array[1][0];
	/* if we cannot locate it atleast return what they passed us */
	if($package_name == "") 
		$package_name = str_replace(".sh","",$extd);
	if($executable_name == "") 
		$executable_name = str_replace(".sh","",$extd);
	$description = find_package_description($raw_name);
	if($description == "")
		$description = "&nbsp;";
}

if($_GET['service'] <> "") 
	get_package_rcd_details($_GET['service'] . ".sh");

if($_GET['restartservice'] == "true") {
	mwexec("/usr/bin/killall {$executable_name}");
	sleep(1);
	mwexec("/bin/sh /usr/local/etc/rc.d/{$raw_name}.sh start");
	$status = is_service_running($executable_name);
	if($status == 1) {
		$savemsg = "{$package_name} has been restarted.";
	} else {
		$error_message = exec_command("/bin/sh /usr/local/etc/rc.d/{$raw_name}.sh start");
		$savemsg = "There was a error restarting {$package_name}.<p>{$error_message}";
	}
}

if($_GET['stopservice'] == "true") {
	mwexec("/usr/bin/killall {$executable_name}");
	sleep(1);
	$status = is_service_running($executable_name);
	if($status == 1) {
		$savemsg = "There was an error stopping {$package_name} - {$executable_name}.";
	} else {
		$savemsg = "{$package_name} has been stopped.";
	}
}

if($_GET['startservice'] == "true") {	
	mwexec("/bin/sh /usr/local/etc/rc.d/{$raw_name}.sh start");
	$status = is_service_running($executable_name);
	if($status == 1) {
		$savemsg = "{$package_name} has been started.";
	} else {
		$error_message = exec_command("/bin/sh /usr/local/etc/rc.d/{$raw_name}.sh start");
		$savemsg = "There was a error restarting {$package_name}.<p>{$error_message}";
	}
}

/* batch mode, allow other scripts to call this script */
if($_GET['batch'] <> "") exit;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<?php $title = "Status: Services"; ?>
<title><?=gentitle_pkg($title);?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle"><?=$title?></p>
<form action="status_services.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<p>


<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="tabcont">
    <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
	  <td class="listhdrr"><b><center>Service</center></b></td>
	  <td class="listhdrr"><b><center>Description</center></b></td>
	  <td class="listhdrr"><b><center>Status</center></b></td>
	  <td class="listhdrr"><b><center>Maintenance</center></b></td>
	</tr>

<?php

$dh = @opendir("/usr/local/etc/rc.d/");
if ($dh)
	while (($extd = readdir($dh)) !== false) {
		if (($extd == ".") || ($extd == ".."))
			continue;
		get_package_rcd_details($extd);
		if($executable_name == "")
			continue;
		if($package_name == "")
			continue;
		if(get_pkg_id(strtolower($package_name)) == -1)
			continue;
		$status = is_service_running($executable_name);
		if($status == 1)
			$status_txt = "<font color='green'>Running</font>";
		else
			$status_txt = "<font color='red'>Stopped</font>";
		echo "<tr><td class=\"listlr\">{$package_name}</td>";
		echo "<td class=\"listlr\">{$description}</td>";
		echo "<td class=\"listlr\">{$status_txt}</td>";
		echo "<td class=\"listlr\"><center>";
		if($status == 1) {
			echo "<a href='status_services.php?restartservice=true&service={$raw_name}'>";
			echo "<img title='Restart Service' border='0' src='/service_restart.gif'></a> ";
			echo "<a href='status_services.php?stopservice=true&service={$raw_name}'>";
			echo "<img title='Stop Service' border='0' src='/service_stop.gif'> ";
			echo "</a> ";
		} else {
			echo "<a href='status_services.php?startservice=true&service={$raw_name}'> ";
			echo "<img title='Start Service' border='0' src='/service_start.gif'></a> ";
		}
		echo "</center></td>";
		echo "</tr>";
		$counter++;
	}

if($counter == 0) 
	echo "<tr><td colspan=5><center>Could not locate any services.</td></tr>";

?>
<tr><td>
</td></tr>
</table>

</td>
</tr></table>

<?php include("fend.inc"); ?>
</body>
</html>
