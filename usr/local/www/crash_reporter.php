<?php
/* $Id$ */
/*
	crash_reporter.php
	part of pfSense
	Copyright (C) 2011 Scott Ullrich
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
/*
	pfSense_MODULE:	header
*/

##|+PRIV
##|*IDENT=page-diagnostics-crash-reporter
##|*NAME=Crash reporter
##|*DESCR=Uploads crash reports to pfSense and or deletes crash reports.
##|*MATCH=crash_reporter.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");

define("FILE_SIZE", 450000);

function upload_crash_report($files) {
	global $g;
	$post = array();
	$counter = 0;
	foreach($files as $file) {
		$post["file{$counter}"] = "@{$file}";
		$counter++;
	}
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
    curl_setopt($ch, CURLOPT_URL, $g['crashreporterurl']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
    $response = curl_exec($ch);
	return $response;
}

function output_crash_reporter_html($crash_reports) {
	echo "<strong>" . gettext("Unfortunately we have detected a programming bug.") . "</strong></p>";
	echo gettext("Would you like to submit the programming debug logs to the pfSense developers for inspection?") . "</p>";
	echo "<p>";
	echo "<i>" . gettext("Please double check the contents to ensure you are comfortable sending this information before clicking Yes.") . "</i><br />";
	echo "<p>";
	echo gettext("Contents of crash reports") . ":<br />";
	echo "<textarea readonly rows='40' cols='65' name='crashreports'>{$crash_reports}</textarea>";
	echo "<p/>";
	echo "<input name=\"Submit\" type=\"submit\" class=\"formbtn\" value=\"" . gettext("Yes") .  "\">" . gettext(" - Submit this to the developers for inspection");
	echo "<p/><input name=\"Submit\" type=\"submit\" class=\"formbtn\" value=\"" . gettext("No") .  "\">" . gettext(" - Just delete the crash report and take me back to the Dashboard");
	echo "<p/>";
	echo "</form>";
}

$pgtitle = array(gettext("Diagnostics"),gettext("Crash reporter"));
include('head.inc');

$crash_report_header = "Crash report begins.  Anonymous machine information:\n\n";
$crash_report_header .= php_uname("m") . "\n";
$crash_report_header .= php_uname("r") . "\n";
$crash_report_header .= php_uname("v") . "\n";
$crash_report_header .= "\nCrash report details:\n";

exec("/usr/bin/grep -vi warning /tmp/PHP_errors.log", $php_errors);

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

	<form action="crash_reporter.php" method="post">

<?php
	if (gettext($_POST['Submit']) == "Yes") {
		echo gettext("Processing...");
		if (!is_dir("/var/crash"))
			mkdir("/var/crash", 0750, true);
		@file_put_contents("/var/crash/crashreport_header.txt", $crash_report_header);
		if(file_exists("/tmp/PHP_errors.log"))
			copy("/tmp/PHP_errors.log", "/var/crash/PHP_errors.log");
		exec("/usr/bin/gzip /var/crash/*");
		$files_to_upload = glob("/var/crash/*");
		echo "<p/>";
		echo gettext("Uploading...");
		ob_flush();
		flush();
		if(is_array($files_to_upload)) {
			$resp = upload_crash_report($files_to_upload);
			array_map('unlink', glob("/var/crash/*"));
			// Erase the contents of the PHP error log
			fclose(fopen("/tmp/PHP_errors.log", 'w'));
			echo "<p/>";
			print_r($resp);
			echo "<p/><a href='/'>" . gettext("Continue") . "</a>" . gettext(" and delete crash report files from local disk.");
		} else {
			echo "Could not find any crash files.";
		}
	} else if(gettext($_POST['Submit']) == "No") {
		array_map('unlink', glob("/var/crash/*"));
		// Erase the contents of the PHP error log
		fclose(fopen("/tmp/PHP_errors.log", 'w'));
		Header("Location: /");
		exit;
	} else {
		$crash_files = glob("/var/crash/*");
		$crash_reports = $crash_report_header;
		if (count($php_errors) > 0) {
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= implode("\n", $php_errors) . "\n\n";
		}
		if(is_array($crash_files))	{
			foreach($crash_files as $cf) {
				if(filesize($cf) < FILE_SIZE) {
					$crash_reports .= "\nFilename: {$cf}\n";
					$crash_reports .= file_get_contents($cf);
				}
			}
		} else { 
			echo "Could not locate any crash data.";
		}
		output_crash_reporter_html($crash_reports);
	}
?>

<?php include("fend.inc"); ?>

</body>
</html>
