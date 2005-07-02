#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system_firmware_auto.php
	part of pfSense (http://www.pfsense.com)

	Copyright (C) 2005 Scott Ullrich and Colin Smith

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

	TODO:
		* modify pfSense.com XMLRPC server to return md5 hashes of firmware updates.
*/

require_once("guiconfig.inc");
require_once("xmlrpc.inc");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Firmware: Firmware Updater");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<p class="pgtitle">System: Firmware: Firmware Updater</p>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", false, "system_firmware.php");
	$tab_array[1] = array("Auto Update", true, "system_firmware_check.php");
	$tab_array[2] = array("Updater Settings", false, "system_firmware_settings.php");
	display_top_tabs($tab_array);
?>
    </td>
  </tr>
	<tr>
	  <td class="tabcont">
	      <table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
		  <td>
		      <!-- progress bar -->
		      <center>
		      <table id="progholder" name="progholder" height='20' border='1' bordercolor='black' width='420' bordercolordark='#000000' bordercolorlight='#000000' style='border-collapse: collapse' colspacing='2' cellpadding='2' cellspacing='2'><tr><td><img border='0' src='progress_bar.gif' width='280' height='23' name='progressbar' id='progressbar'></td></tr></table>
		      <br>
		      <!-- status box -->
		      <textarea border='1' bordercolordark='#000000' bordercolorlight='#000000' cols="60" rows="1" name="status" id="status" wrap="hard">
		      Beginning system autoupdate...
		      </textarea>
		      <!-- command output box -->
		      <textarea border='1' bordercolordark='#000000' bordercolorlight='#000000' cols="60" rows="25" name="output" id="output" wrap="hard">
		      </textarea>
		      </center>
		  </td>
		</tr>
	      </table>
	  </td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

/* Define necessary variables. */
$update_types = array('full', 'diff');
$didupdate = false;

if($_GET['category'] == 'full') {
	$tocheck = 'all';
	$categories = array('firmware', 'kernel', 'base');
} else {
	$tocheck = array($_GET['category']);
	$categories = $tocheck;
}

$static_output = "Downloading current version information... ";
update_status($static_output);
update_output_window($static_output);

if(file_exists("/tmp/versioncheck.cache")) {
	$versions = unserialize("/tmp/versioncheck.cache");
	if(time() - $versions['cachetime'] > 300) { // Our cached data is stale, get a new copy.
		$versions = check_firmware_version($tocheck);
	} else { // Our cached data is relatively currently, remove the cachetime label.
		unset($versions['cachetime']);
	}
}

$static_output .= "done.\n";
update_output_window($static_output);

foreach($categories as $index => $key) {
	$bdiff_errors = array();
	if(is_array($versions[$key][0])) { // Make sure we really need to update this section.
		$didupdate = true;
		update_status("Found required " . $key . " updates. Downloading...");
		$static_output .= "Downloading " . $key . " updates... ";
		update_output_window($static_output);
		foreach($versions[$key] as $ver) { // Begin system updates.
			foreach($update_types as $type) if(in_array($type, array_keys($ver))) $url_type = $type;
			$tofetch = "pfSense-" . ucfirst($url_type) . "-" . ucfirst($key) . "-Update-" . $ver['version'] . ".tgz";
			$static_output_bak = $static_output;
			$static_output .= "\n\t" . $ver['version'] . "-" . $ver['name'] . " ";
			update_output_window($static_output);
			download_file_with_progress_bar("http://www.pfsense.com/updates/" . $tofetch, "/tmp/" . $tofetch);
			if($url_type == "binary") {
				exec("/etc/rc.firmware delta_update " . "/tmp/" . $tofetch, $bdiff_errors);
				if(is_string($bdiff_errors[0])) {
					unlink_if_exists("/tmp/" . $tofetch);
					$static_output .= "failed!\n";
					update_output_window($static_output);
					break;
				}
			} else {
				$tofetch = "pfSense-" . ucfirst($url_type) . "-Update-" . $ver['version'] . ".tgz";
				exec("/etc/rc.firmware pfSenseupgrade " . "/tmp/" . $tofetch);
				unlink_if_exists("/tmp/" . $tofetch);
			}
			$static_output = $static_output_bak . "done.\n";
		}
	}
}

if($didupdate == true) {
	update_status("Update finished. Rebooting...");
	exec("/etc/rc.reboot");
} else {
	update_status("No updates required.");
}

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";

?>
