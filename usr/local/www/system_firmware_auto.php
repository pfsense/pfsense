#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system_firmware_auto.php
	Copyright (C) 2005 Scott Ullrich

        Based originally on system_firmware.php
        (C)2003-2004 Manuel Kasper
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

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Firmware: Invoke Auto Upgrade");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<p class="pgtitle">System: Firmware: Invoke Auto Upgrade</p>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
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
		      <textarea style="border-color: #990000; background-color: #EEEEEE; border-width: 1px;" cols="60" rows="1" name="status" id="status" wrap="hard">One moment please... This will take a while!</textarea>
		      <!-- command output box -->
		      <textarea style="border-color: #990000; background-color: #EEEEEE; border-width: 1px;" cols="60" rows="25" name="output" id="output" wrap="hard"></textarea>
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

/* auto upgrade logic starts here */

update_status("Downloading current version information...");
$status = download_file_with_progress_bar("http://www.pfSense.com/pfSense/version", "/tmp/pfSense_version");

update_status("Downloading current base version information...");
$status = download_file_with_progress_bar("http://www.pfSense.com/pfSense/version_base", "/tmp/pfSense_base_version");

$current_installed_pfsense_version = return_filename_as_string("/etc/version");
$current_installed_pfsense_base_version = return_filename_as_string("/etc/version_base");

// XXX: logic to deterimine if we need a new version.
$needs_system_upgrade 	= true;
$needs_base_upgrade 	= false;
$platform 		= return_filename_as_string("/etc/platform");

if($needs_system_upgrade == true) {
	update_status("Downloading updates ...");
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest.tgz", "/tmp/latest.tgz");
	update_output_window("pfSense download complete.");
}

if($needs_base_upgrade == true) {
	update_status("Downloading base updates ...");
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest_base.tgz", "/tmp/latest_base.tgz");
	update_status("Downloading base kernel updates ...");
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest_kernel{$platform}.tgz", "/tmp/latest_kernel.tgz");
	update_output_window("pfSense base download complete.");
}

/* launch external upgrade helper */
$external_upgrade_helper_text = "/etc/rc.firmware ";
if($needs_system_upgrade == true)
	$external_upgrade_helper_text .= "/tmp/latest.tgz /tmp/latest.tgz.md5";
if($needs_base_upgrade == true)
	$external_upgrade_helper_text .= "/tmp/latest_base.tgz /tmp/latest_base.tgz.md5";

update_status("Downloading complete.");
update_output_window("pfSense is now upgrading.\\n\\nThe firewall will reboot once the operation is completed.");
echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";
exec_rc_script_async("{$external_upgrade_helper_text}");

/*
	Helper functions
*/

function download_file_with_progress_bar($url_file, $destination_file) {
	global $ch, $fout, $file_size, $downloaded, $counter;
	$file_size  = 1;
	$downloaded = 1;
	/* open destination file */
	$fout = fopen($destination_file, "wb");

	/*
		Originally by Author: Keyvan Minoukadeh
		Modified by Scott Ullrich to return Content-Length size
	*/

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_file);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'read_body');
	curl_setopt($ch, CURLOPT_NOPROGRESS, '1');

	curl_exec($ch);
	fclose($fout);
	return 1;

	if ($error = curl_error($ch)) {
	    return -1;
	}
}

function read_header($ch, $string) {
	global $file_size, $ch, $fout;
	$length = strlen($string);
	ereg("(Content-Length:) (.*)", $string, $regs);
	if($regs[2] <> "") {
		$file_size = intval($regs[2]);
	}
	return $length;
}

function read_body($ch, $string) {
	global $fout, $file_size, $downloaded, $counter;
	$length = strlen($string);
	$downloaded += intval($length);
	$downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
	$downloadProgress = 100 - $downloadProgress;
	$a = $file_size;
	$b = $downloaded;
	$c = $downloadProgress;
	$text  = "  Download Status\\n";
	$text .= "---------------------------------\\n";
	$text .= "  File size  : {$a}\\n";
	$text .= "  Downloaded : {$b}\\n";
	$text .= "  Percent    : {$c}%\\n";
	$text .= "---------------------------------\\n";
	$counter++;
	if($counter > 150) {
		update_output_window($text);
		update_progress_bar($downloadProgress);
		$counter = 0;
	}
	fwrite($fout, $string);
	return $length;
}

?>