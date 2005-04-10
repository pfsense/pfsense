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
require_once("xmlrpc_client.inc");

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
<p class="pgtitle">System: Firmware: Invoke Auto Upgrade</p>

<form action="system_firmware_auto.php" method="post">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
      <ul id="tabnav">
	<li class="tabinact"><a href="system_firmware.php">Firmware Update</a></li>
    	<li class="tabact">Auto Update</a></li>
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
$platform =		trim(file_get_contents('/etc/platform'));
$firmware_version =	trim(file_get_contents('/etc/version'));
$kernel_version =	trim(file_get_contents('/etc/version_kernel'));
$base_version =		trim(file_get_contents('/etc/version_base'));
$use_old_checkversion = true;

update_status("Downloading current version information...");
if($use_old_checkversion == true) {
	$versions = old_checkversion();
	print $versions;
} else {
	$versions = check_firmware_version();
}

if($use_old_checkversion == false) {
if($versions != -1) {
	if($versions[0] == true) {
		if($versions[1] != $firmware_version) $needs_firmware_upgrade = true;
		if($versions[2] != $kernel_version) $needs_kernel_upgrade = true;
		if($versions[3] != $base_version) $needs_base_version = true;
		if(isset($versions[4])) $static_text = $versions[4] . '\n'; // If we have additional data (a CHANGELOG etc) to display, do so.
		update_output_window($static_text);
	} else {
		update_status("No updates required.");
	}
} else {
	update_status("Could not retrieve version information.");
	exit();
}

if($needs_firmware_upgrade == true) {
	$static_status = "Downloading firmware update... ";
	update_status($static_status);
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest.tgz", "/tmp/latest.tgz");
	$static_status .= "done. ";
}

if($needs_kernel_upgrade == true) {
	$static_status .= "Downloading kernel update... ";
	update_status($static_status);
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest_kernel{$platform}.tgz", "/tmp/latest_kernel.tgz");
	$static_status .= "done. ";
}

if($needs_base_upgrade == true) {
	$static_status .= "Downloading base update... ";
	update_status($static_status);
	$status = download_file_with_progress_bar("http://www.pfSense.com/latest_base.tgz", "/tmp/latest_base.tgz");
	$static_status .= "done. ";
	update_status($static_status);
}

/* launch external upgrade helper */
$external_upgrade_helper_text = "";
if($needs_system_upgrade == true) {
	// XXX: check md5 of downloaded file.
	exec_rc_script_async("/etc/rc.firmware pfSense");
}

if($needs_kernel_upgrade == true) {
	// XXX: check md5 of downloaded file.
	exec_rc_script_async("/etc/rc.firmware pfSense_kernel");
}

if($needs_base_upgrade == true) {
	// XXX: check md5 of downloaded file.
	exec_rc_script_async("/etc/rc.firmware pfSense_base");
}
} else {
	if($versions != "") {
		update_output_window("Using old checkversion method. Text returned from pfSense.com:\n\n" . $versions . "\n\nUpgrading...");
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

		exec_rc_script_async("/etc/rc.firmware_auto {$firmwareurl} {$firmwarename} {$http_auth_username} {$http_auth_password}");
		$update_status = "pfSense is now auto upgrading.  The firewall will automatically reboot if it succeeds.";
	} elseif($versions == "") {
		update_output_window("Using old checkversion method. You are running the latest version of pfSense.");
	} elseif(is_null($versions)) {
		update_output_window("Using old checkversion method. Unable to receive version information from pfSense.com.");
	} else {
		update_output_window("Using old checkversion method. An unknown error occurred.");
	}
}
update_status("pfSense is now upgrading.  The firewall will reboot once the operation has completed.");

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";

/* end of upgrade script */



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
	/*
	$a = $file_size;
	$b = $downloaded;
	$c = $downloadProgress;
	$text = "  Download Status\\n";
	$text .= "---------------------------------\\n";
	$text .= "  File size  : {$a}\\n";
	$text .= "  Downloaded : {$b}\\n";
	$text .= "  Percent    : {$c}%\\n";
	$text .= "---------------------------------\\n";
	*/
	$counter++;
	if($counter > 150) {
		$tostatus = $static_status . $downloadProgress;
		update_status($tostatus);
		update_progress_bar($downloadProgress);
		$counter = 0;
	}
	fwrite($fout, $string);
	return $length;
}

?>
