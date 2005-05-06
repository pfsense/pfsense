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
        <li class="tabact">Auto Update</a></li>
	<li class="tabinact"><a href="system_firmware.php">Manual Update</a></li>
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
		update_status("Found required updates. Downloading...");
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
		update_status("No updates required.");
	}

	foreach($needupdate as $toupdate) {
		if($toupdate == true) {
			$static_text .= "Installing {$upgrades[$i]} updates... ";
			$s = 0;
			foreach($versions[$i] as $aver) {
				$todownload = substr(strrchr($tofetch, '-'), 1);
				$static_text .= $todownload;
				update_output_window($static_text . " ");
				$tofetch = "pfSense-" . $upgrades[$i] . "-" . $todownload . ".tgz";
				download_file_with_progress_bar("http://www.pfSense.com/updates/{$tofetch}", "/tmp/{$tofetch}");
				update_output_window($static_text);
				system("/etc/rc.firmware delta_firmware /tmp/" . $tofetch);
				if(file_exists("/tmp/bdiff.log")) {
					$bdiff_errors[] = file_get_contents("/tmp/bdiff.log");
				}
				if($s == count($aver) - 1) {
					$static_text .= ".\n";
				} else {
					$static_text .= ", ";
				}
				update_output_window($static_text);
				$s++;
			}
		}
		$i++;
	}

	if(is_string($bdiff_errors[0])) {
		$static_text .= "\nOne or more md5 mismatches occurred during patch application.";
		update_output_window($static_text);
		file_put_contents("/tmp/bdiff.log", print_r($bdiff_errors, true));
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
		if(file_exists("/tmp/autoupdate.lock")) 
			$upgrade_lock = file("/tmp/autoupdate.lock");
		if(trim($upgrade_lock[0]) == "1") {
			$update_status = "An upgrade is already in progress.";
			update_output_window($update_status);
			exit;
		} else {
			log_error("Downloading http://www.pfSense.com/latest.tgz");
			update_status("Downloading latest version...");	
			download_file_with_progress_bar("http://www.pfSense.com/latest.tgz", "/tmp/latest.tgz");
			log_error("Downloading http://www.pfSense.com/latest.tgz");
			download_file_with_progress_bar("http://www.pfSense.com/latest.tgz.md5", "/tmp/latest.tgz.md5");
			mwexec_bg("/etc/rc.firmware_auto \"{$firmwareurl}\" \"{$firmwarename}\" \"{$http_auth_username}\" \"{$http_auth_password}\"");
			$update_status = "pfSense is now auto upgrading.  The firewall will automatically reboot if it succeeds.";
			update_status("pfSense is now upgrading.  The firewall will reboot once the operation has completed.");
			echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";
			exit;
		}
	} elseif($versions == "") {
		update_output_window("Using old checkversion method. You are running the latest version of pfSense.");
	} elseif(is_null($versions)) {
		update_output_window("Using old checkversion method. Unable to receive version information from pfSense.com.");
	} else {
		update_output_window("Using old checkversion method. An unknown error occurred.");
	}
}

echo "\n<script language=\"JavaScript\">document.progressbar.style.visibility='hidden';\n</script>";

?>
