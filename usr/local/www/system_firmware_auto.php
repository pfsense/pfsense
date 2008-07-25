<?php
/* $Id$ */
/*
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.
	
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

$curcfg = $config['system']['firmware'];

if(isset($curcfg['alturl']['enable']))
	$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
else
	$updater_url = $g['update_url'];
	
$pgtitle = array("Diagnostics","Firmware","Auto Update");
include("head.inc");

?>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

<p/>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", true, "system_firmware.php");
	$tab_array[1] = array("Auto Update", false, "system_firmware_check.php");
	$tab_array[2] = array("Updater Settings", false, "system_firmware_settings.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
	<tr>
	  <td class="tabcont">
	      <table width="100%" border="0" cellpadding="6" cellspacing="0">
			  <tr>
			    <td class="tabcont">
					<table width="100%" border="0" cellpadding="6" cellspacing="0">
						<tr>
							<td>
								<center>
								<table height='15' width='420' border='0' colspacing='0' cellpadding='0' cellspacing='0'>

								<tr>
									<td background="./themes/the_wall/images/misc/bar_left.gif" height='15' width='5'>
									</td>
									<td>
									<table id="progholder" name="progholder" height='15' width='410' border='0' colspacing='0' cellpadding='0' cellspacing='0'>
										<td background="./themes/the_wall/images/misc/bar_gray.gif" valign="top" align="left">
											<img src='./themes/the_wall/images/misc/bar_blue.gif' width='0' height='15' name='progressbar' id='progressbar'>
										</td>
									</table>
								</td>
								<td background="./themes/the_wall/images/misc/bar_right.gif" height='15' width='5'>
								</td>
							</tr>
						</table>
						<br>
						<!-- status box -->
						<textarea cols="60" rows="1" name="status" id="status" wrap="hard">Beginning package installation.</textarea>
						<!-- command output box -->
						<textarea cols="60" rows="25" name="output" id="output" wrap="hard"></textarea>
					</center>
					</td>
				</tr>
	      </table>
		</table>
	  </td>
	</tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>

<?php

update_status("Downloading current version information...");
$latest_version = download_file_with_progress_bar("{$updater_url}/version", "/tmp/{$g['product_name']}_version");

$current_installed_pfsense_version = str_replace("\n", "", file_get_contents("/etc/version"));
$latest_version = str_replace("\n", "", file_get_contents("/tmp/{$g['product_name']}_version"));

if($current_installed_pfsense_version <> $latest_version) 
	$needs_system_upgrade = true;

if($needs_system_upgrade == true) {
	update_status("Downloading updates ...");
	$status = download_file_with_progress_bar("{$updater_url}/latest.tgz", "/tmp/latest.tgz");	
	$status = download_file_with_progress_bar("{$updater_url}/latest.tgz.sha256", "/tmp/latest.tgz.sha256");
	update_output_window("{$g['product_name']} download complete.");
}

/* launch external upgrade helper */
$external_upgrade_helper_text = "/etc/rc.firmware pfSenseupgrade ";
if($needs_system_upgrade == true)
	$external_upgrade_helper_text .= "/tmp/latest.tgz";

$downloaded_latest_tgz_sha256 = str_replace("\n", "", `sha256 /tmp/latest.tgz  | awk '{ print $4 }'`);
$upgrade_latest_tgz_sha256 = str_replace("\n", "", `cat /tmp/latest.tgz.sha256 | awk '{ print $4 }'`);

$sigchk = 0;

if(!isset($curcfg['alturl']['enable']))
	$sigchk = verify_digital_signature("/tmp/latest.tgz");

if ($sigchk == 1)
	$sig_warning = "The digital signature on this image is invalid.";
else if ($sigchk == 2)
	$sig_warning = "This image is not digitally signed.";
else if (($sigchk == 3) || ($sigchk == 4))
	$sig_warning = "There has been an error verifying the signature on this image.";

if (!verify_gzip_file("/tmp/latest.tgz")) {
	update_status("The image file is corrupt.");
	update_output_window("Update cannot continue");
	unlink("{$g['upload_path']}/latest.tgz");
	require("fend.inc");
	exit;
}

if ($sigchk) {
	update_status($sig_warning);
	update_output_window("Update cannot continue");
	unlink("{$g['upload_path']}/latest.tgz");
	require("fend.inc");
	exit;
}

if($downloaded_latest_tgz_sha256 <> $upgrade_latest_tgz_sha256) {
	update_status("Downloading complete but sha256 does not match.");
	update_output_window("Auto upgrade aborted.  \n\nDownloaded SHA256: $downloaded_latest_tgz_sha256 \n\nNeeded SHA256: $upgrade_latest_tgz_sha256");	
} else {
	update_output_window("{$g['product_name']} is now upgrading.\\n\\nThe firewall will reboot once the operation is completed.");
	echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";
	exec_rc_script_async("{$external_upgrade_helper_text}");
}

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
	global $fout, $file_size, $downloaded, $counter, $version, $latest_version, $current_installed_pfsense_version;
	$length = strlen($string);
	$downloaded += intval($length);
	$downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
	$downloadProgress = 100 - $downloadProgress;
	$a = $file_size;
	$b = $downloaded;
	$c = $downloadProgress;
	$text  = "  Auto Update Download Status\\n";
	$text .= "---------------------------------\\n";
	$text .= "  Latest Version  : {$latest_version}\\n";
	$text .= "  Current Version : {$current_installed_pfsense_version}\\n";
	$text .= "  File size       : {$a}\\n";
	$text .= "  Downloaded      : {$b}\\n";
	$text .= "  Percent         : {$c}%\\n";
	$text .= "---------------------------------\\n";
	$counter++;
	if($counter > 150) {
		update_output_window($text);
		update_progress_bar($downloadProgress);
		$counter = 0;
	}
	fwrite($fout, $string);
	echo "<script language='javascript'>'>document.progressbar.style.width=\"$c%\";</script>\n";
	return $length;
}

?>