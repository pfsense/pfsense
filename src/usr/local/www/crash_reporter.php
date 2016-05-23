<?php
/*
	crash_reporter.php
*/
/* ====================================================================
 *  Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *      this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in
 *      the documentation and/or other materials provided with the
 *      distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgment:
 *      "This product includes software developed by the pfSense Project
 *       for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *       endorse or promote products derived from this software without
 *       prior written permission. For written permission, please contact
 *       coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *      nor may "pfSense" appear in their names without prior written
 *      permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *      acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  ====================================================================
 *
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
	foreach ($files as $file) {
		$post["file{$counter}"] = "@{$file}";
		$counter++;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
	curl_setopt($ch, CURLOPT_URL, $g['crashreporterurl']);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$response = curl_exec($ch);
	return $response;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Crash Reporter"));
include('head.inc');

$crash_report_header = "Crash report begins.  Anonymous machine information:\n\n";
$crash_report_header .= php_uname("m") . "\n";
$crash_report_header .= php_uname("r") . "\n";
$crash_report_header .= php_uname("v") . "\n";
$crash_report_header .= "\nCrash report details:\n";

exec("/bin/cat /tmp/PHP_errors.log", $php_errors);

	if ($_POST['Submit'] == "Yes") {
		echo gettext("Processing...");
		if (!is_dir("/var/crash")) {
			mkdir("/var/crash", 0750, true);
		}
		@file_put_contents("/var/crash/crashreport_header.txt", $crash_report_header);
		if (file_exists("/tmp/PHP_errors.log")) {
			copy("/tmp/PHP_errors.log", "/var/crash/PHP_errors.log");
		}
		exec("find /var/crash -type l -exec rm {} +");
		exec("/usr/bin/gzip /var/crash/*");
		$files_to_upload = glob("/var/crash/*");
		echo "<br/>";
		echo gettext("Uploading...");
		ob_flush();
		flush();
		if (is_array($files_to_upload)) {
			$resp = upload_crash_report($files_to_upload);
			array_map('unlink', glob("/var/crash/*"));
			// Erase the contents of the PHP error log
			fclose(fopen("/tmp/PHP_errors.log", 'w'));
			echo "<br/>";
			print_r($resp);
			echo "<p><a href=\"/\">" . gettext("Continue") . "</a>" . gettext(" and delete crash report files from local disk.") . "</p>";
		} else {
			echo gettext("Could not find any crash files.");
		}
	} else if ($_POST['Submit'] == "No") {
		array_map('unlink', glob("/var/crash/*"));
		// Erase the contents of the PHP error log
		fclose(fopen("/tmp/PHP_errors.log", 'w'));
		header("Location: /");
		exit;
	} else {
		$crash_files = glob("/var/crash/*");
		$crash_reports = $crash_report_header;
		if (count($php_errors) > 0) {
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= implode("\n", $php_errors) . "\n\n";
		}
		if (is_array($crash_files))	{
			foreach ($crash_files as $cf) {
				if (filesize($cf) < FILE_SIZE) {
					$crash_reports .= "\nFilename: {$cf}\n";
					$crash_reports .= file_get_contents($cf);
				}
			}
		} else {
			echo gettext("Could not locate any crash data.");
		}
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("Unfortunately a Programming Bug has been detected")?></h2></div>
		<div class="panel-body">
			<div class="content">
				<p>
					<?=gettext("The programming debug logs can be submitted to the pfSense developers for inspection.")?>
					<i><?=gettext("Please double check the contents to ensure this information is acceptable to disclose before submitting.")?></i>
				</p>
				<textarea readonly style="width: 100%; height: 350px;">
					<?=$crash_reports?>
				</textarea>
				<br/><br/>
				<form action="crash_reporter.php" method="post">
					<button class="btn btn-primary" name="Submit" type="submit" value="Yes">
						<i class="fa fa-upload"></i>
						<?=gettext("Yes")?> - <?=gettext("Submit this to the developers for inspection")?>
					</button>
					<button class="btn btn-warning" name="Submit" type="submit" value="No">
						<i class="fa fa-undo"></i>
						<?=gettext("No")?> - <?=gettext("Just delete the crash report and return to the Dashboard")?>
					</button>
				</form>
			</div>
		</div>
<?php
	}
?>

<?php include("foot.inc")?>
