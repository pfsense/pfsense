<?php
/* $Id$ */
/*
	system_firmware.php
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.
	
	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$d_isfwfile = 1;
require("guiconfig.inc");

$curcfg = $config['system']['firmware'];

include("head.inc");

?>

<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="all.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<p class="pgtitle">System: Firmware: Auto Upgrade</p>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", false, "system_firmware.php");
	$tab_array[1] = array("Auto Update Check", true, "system_firmware_check.php");
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
		      <!-- command output box -->
		      <textarea border='1' bordercolordark='#000000' bordercolorlight='#000000' cols='80' rows='7' name='output' id='output' wrap='hard'>
		      </textarea>                      
		      </center>
 			<p>
			<center><input id='invokeupgrade' style='visibility:hidden' type="submit" value="Invoke Auto Upgrade">
		  </td>
		</tr>
	      </table>
	  </td>
	</tr>
</table>

<p>

<?php

/* Define necessary variables. */
$firmware_version =	trim(file_get_contents('/etc/version'));

$static_text = "Downloading current version information... ";
update_output_window($static_text);

$static_text .= "done.\n";
update_output_window($static_text);

if(isset($curcfg['alturl']['enable']))
	$updater_url = "{$config['system']['firmware']['alturl']['firmwareurl']}";
else 
	$updater_url = $g['update_url'];

update_status("Downloading current version information...");
$latest_version = download_file_with_progress_bar("{$updater_url}/version", "/tmp/{$g['product_name']}_version");

if(strstr($latest_version,"404")) {
	update_output_window("Could not download version information file {$updater_url}/version");
	include("fend.inc");
	exit;	
}

$current_installed_pfsense_version = str_replace("\n", "", file_get_contents("/etc/version"));
$latest_version = str_replace("\n", "", file_get_contents("/tmp/{$g['product_name']}_version"));

$needs_system_upgrade = false;
if($current_installed_pfsense_version <> $latest_version) 
	$needs_system_upgrade = true;

if(!$latest_version) {
		if(isset($curcfg['alturl']['enable']))
			update_output_window("Could not contact custom update server.");
		else 
			update_output_window("Could not contact {$g['product_name']} update server {$updater_url}.");
} else {
	if($needs_system_upgrade) {
		echo "\n<script language=\"JavaScript\">$('invokeupgrade').style.visibility = 'visible';</script>";
		$txt  = "A new version is now available \\n\\n";
		$txt .= "New version:     {$latest_version}\\n";
		$txt .= "Current version: {$current_installed_pfsense_version}\\n";
		$txt .= "Update source:   {$g['update_url']}\\n";
		update_output_window($txt);
	} else {
		update_output_window("You are on the latest version.");
	}
}
?>

</form>
<?php include("fend.inc"); ?>
</body>
</html>


</body>
</html>