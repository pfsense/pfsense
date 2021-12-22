<?php
/*
 * crash_reporter.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-crash-reporter
##|*NAME=Crash reporter
##|*DESCR=Uploads crash reports to pfSense and or deletes crash reports.
##|*MATCH=crash_reporter.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");
require_once("system.inc");

define("FILE_SIZE", 450000);

function download_crashdata_file($name) {
	if (!file_exists($name)) {
		exit;
	}
	session_cache_limiter('public');
	send_user_download('file', $name);
}

if (!empty($_POST['Download'])) {
	if ($_POST['Download'] == "PHP") {
		/* Send PHP log */
		download_crashdata_file("/tmp/PHP_errors.log");
	} else {
		$filename = "/var/crash/" . basename($_POST['Download']);
		if (file_exists($filename)) {
			download_crashdata_file($filename);
		}
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("Crash Reporter"));
include('head.inc');

$crash_report_header = "Crash report begins.  Anonymous machine information:\n\n";
$crash_report_header .= php_uname("m") . "\n";
$crash_report_header .= php_uname("r") . "\n";
$crash_report_header .= php_uname("v") . "\n";
$crash_report_header .= "\nCrash report details:\n";

if ($_POST['Submit'] == "No") {
	array_map('unlink', glob("/var/crash/*"));
	// Erase the contents of the PHP error log
	fclose(fopen("/tmp/PHP_errors.log", 'w'));
	header("Location: /");
	exit;
} else {
	$crash_reports = $crash_report_header;
	if (system_has_php_errors()) {
		if (filesize("/tmp/PHP_errors.log") < FILE_SIZE) {
			$php_errors = file_get_contents("/tmp/PHP_errors.log");
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= $php_errors . "\n\n";
		} else {
			$crash_reports .= "\n/tmp/PHP_errors.log file is too large to display.\n";
		}
	} else {
		$crash_reports .= "\nNo PHP errors found.\n";
	}

	$crash_files = cleanup_crash_file_list(glob("/var/crash/*"));
	if (count($crash_files) > 0) {
		foreach ($crash_files as $cf) {
			if (filesize($cf) < FILE_SIZE) {
				$crash_reports .= "\nFilename: {$cf}\n";
				$crash_reports .= file_get_contents($cf);
			}
		}
	} else {
		$crash_reports .= "\nNo FreeBSD crash data found.\n";
	}
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("The firewall has encountered an error")?></h2></div>
	<div class="panel-body">
		<div class="content">
			<p>
				<?=sprintf(gettext("Debugging output can be collected to share with %s developers or others providing support or assistance."), $g['product_label'])?>
				<br/><br/>
				<i><?=gettext("Inspect the contents to ensure this information is acceptable to disclose before distributing these files.")?></i>
			</p>
			<textarea readonly style="width: 100%; height: 350px;">
<?=$crash_reports?>
			</textarea>
			<br/><br/>
			<form action="crash_reporter.php" method="post">
				<button class="btn btn-warning" name="Submit" type="submit" value="No">
					<i class="fa fa-undo"></i>
					<?=gettext("Delete the crash report data and return to the Dashboard")?>
				</button>
			<br/><br/>

<?php	if ((count($crash_files) > 0) || system_has_php_errors()): ?>
			Click a button below to download an individual debugging data file:
			<br/><br/>
	<?php	if (system_has_php_errors()): ?>
				<button class="btn btn-info" name="Download" type="submit" value="PHP">
					<i class="fa fa-download"></i>
					<?=gettext("Download PHP Error Log")?>
				</button>
				<br/><br/>
	<?php	endif;
		foreach ($crash_files as $cf):
				$tfn = htmlspecialchars(basename($cf)); ?>
				<button class="btn btn-info" name="Download" type="submit" value="<?= $tfn ?>">
					<i class="fa fa-download"></i>
					<?=gettext("Download")?> <?= $tfn ?>
				</button>
				<br/><br/>
	<?php	endforeach;
	endif;
?>
			</form>
		</div>
	</div>
<?php
}
?>

<?php include("foot.inc")?>
