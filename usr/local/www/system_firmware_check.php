#!/usr/local/bin/php
<?php
/* $Id$ */
/*
	system_firmware.php
	part of m0n0wall (http://m0n0.ch/wall)

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
require("xmlrpc_client.inc");

function old_checkversion() {
        global $g;
        $versioncheck_base_url = 'www.pfSense.com';
        $versioncheck_path = '/pfSense/checkversion.php';
        $post = "platform=" . rawurlencode(trim(file_get_contents("/etc/platform"))) .
                "&version=" . rawurlencode(trim(file_get_contents("/etc/version")));
        $rfd = @fsockopen($versioncheck_base_url, 80, $errno, $errstr, 3);
        if ($rfd) {
                $hdr = "POST {$versioncheck_path} HTTP/1.0\r\n";
                $hdr .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $hdr .= "User-Agent: pfSense-webConfigurator/1.0\r\n";
                $hdr .= "Host: www.pfSense.com\r\n";
                $hdr .= "Content-Length: " . strlen($post) . "\r\n\r\n";

                fwrite($rfd, $hdr);
                fwrite($rfd, $post);

                $inhdr = true;
                $resp = "";
                while (!feof($rfd)) {
                        $line = fgets($rfd);
                        if ($inhdr) {
                                if (trim($line) == "")
                                        $inhdr = false;
                        } else {
                                $resp .= $line;
                        }
                }

                fclose($rfd);

                if($_GET['autoupgrade'] <> "")
                    return;

                return $resp;
        }

        return null;
}

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
<p class="pgtitle">System: Firmware: Auto Upgrade</p>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
      <ul id="tabnav">
        <li class="tabact">Auto Update</a></li>
	<li class="tabinact"><a href="system_firmware.php">Firmware Update</a></li>
      </ul>
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
		      <!-- command output box -->
		      <textarea border='1' bordercolordark='#000000' bordercolorlight='#000000' cols='60' rows='7' name='output' id='output' wrap='hard'>
		      </textarea>                      
		      </center>
                      <p>
                      <center><input type="submit" value="Invoke Auto Upgrade">
		  </td>
		</tr>
	      </table>
	  </td>
	</tr>
</table>

<p>

<?php

/* Define necessary variables. */
$platform =		trim(file_get_contents('/etc/platform'));
$firmware_version =	trim(file_get_contents('/etc/version'));
$kernel_version =	trim(file_get_contents('/etc/version_kernel'));
$base_version =		trim(file_get_contents('/etc/version_base'));
$use_old_checkversion = true;

$static_text = "Downloading current version information... ";
update_output_window($static_text);
if($use_old_checkversion == true) {
	$versions = old_checkversion();
} else {
	$versions = check_firmware_version();
}
$static_text .= "done.\n";
update_output_window($static_text);

if($use_old_checkversion == false) {
	$upgrades = array('firmware', 'kernel', 'base');
	$bdiff_errors = array();
	if(array_shift($versions) == true) {
		$i = 0;
		$need_update = array();
		update_output_window("Found required updates");
		foreach($versions as $ver) {
			if(is_string($ver[0])) {
				$static_text .= ucfirst($upgrades[$i]) . "\n\tInstalled: " . $firmware_version . "\n\tCurrent: " . $ver[count($ver) -1] . "\n";
				$needupdate[] = true;
			} else {
				$needupdate[] = false;
			}
			$i++;
		}
		$i = 0;
		//              if(isset($versions[3])) $static_text = $versions[4] . '\n'; // If we have additional data (a CHANGELOG etc) to display, do so.
	} else {
		update_output_window("No updates required.");
	}


} else {
	if($versions != "") {
		update_output_window("There are updates available: \n\n" . $versions . "\n\n");
		$http_auth_username = "";
		$http_auth_password = "";
		if($config['system']['proxy_auth_username'])
			$http_auth_username = $config['system']['proxy_auth_username'];
		if($config['system']['proxy_auth_password'])
			$http_auth_password = $config['system']['proxy_auth_password'];

		/* custom firmware option */
		if (isset($config['system']['alt_firmware_url']['enabled'])) {
			$firmwareurl=$config['system']['alt_firmware_url']['firmware_base_url'];
			$firmwarename=$config['system']['alt_firmware_url']['firmware_filename'];
		} else {
			$firmwareurl=$g['firmwarebaseurl'];
			$firmwarename=$g['firmwarefilename'];
		}
	} elseif($versions == "") {
		update_output_window("You are running the latest version of pfSense.");
	} elseif(is_null($versions)) {
		update_output_window("Unable to receive version information from pfSense.com.");
	} else {
		update_output_window("An unknown error occurred.");
	}
}

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";

?>

</form>
<?php include("fend.inc"); ?>
</body>
</html>


</body>
</html>