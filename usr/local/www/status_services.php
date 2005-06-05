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

function get_package_rcd_details($extd) {
	global $package_name, $executable_name, $description;
	$file_contents = return_filename_as_string("/usr/local/etc/rc.d/{$extd}");
        if (preg_match_all("/\# PACKAGE\: (.*)\n/",$file_contents,$match_array))
            $package_name = $match_array[1][0];
        if (preg_match_all("/\# EXECUTABLE\: (.*)\n/",$file_contents,$match_array))
            $executable_name = $match_array[1][0];
	/* if we cannot locate it atleast return what they passed us */
	if($package_name = "") 
		$package_name = str_replace(".xml","",$extd);
	if($executable_name = "") 
		$executable_name = str_replace(".xml","",$extd);
	/* XXX: needs a get_pkg_description($packagename) function */
	$description = "";
}

if($_GET['service'] <> "")
	get_package_rcd_details($_GET['service'] . ".sh");

if($_GET['restartservice'] == "true") {
	mwexec("/sbin/killall $executable_name");
	mwexec("/bin/sh /usr/local/etc/rc.d/{$service}.sh");
	$savemsg = "{$package_name} has been restarted.";
}

if($_GET['stopservice'] == "true") {
	mwexec("/sbin/killall $executable_name");
	$savemsg = "{$package_name} has been stopped.";
}

if($_GET['startservice'] == "true") {	
	mwexec("/bin/sh /usr/local/etc/rc.d/{$service}.sh");
	$savemsg = "{$package_name} has been started.";
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

<table width="100%" border="0" cellpadding="6" cellspacing="0">
</tr>
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
		if (($extd === ".") || ($extd === ".."))
			continue;
		get_package_rcd_details($extd);
		if($executable_name= "")
			continue;
		$status = is_service_running($executable_name);
		if($status == 1)
			$status_txt = "Running";
		else
			$status_txt = "Stopped";
		echo "<tr><td>{$package_name}</td><td>{$description}</td><td>{$status_txt}</td>";
		echo "<td>";
		if($status == 1) {
			echo "<a href='status_services.php?restartservice=true&service={$package_name}'>Restart</a> ";
			echo "<a href='status_services.php?stopservice=true&service={$package_name}'>Stop</a> ";
		} else {
			echo "<a href='status_services.php?startservice=true&service={$package_name}'>Start</a> ";
		}
		echo "</td>";
		echo "</tr>";
	}

?>
<tr><td>
</td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
