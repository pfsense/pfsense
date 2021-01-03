<?php
/*
 * status_logs_packages.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
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

/*
	To add logging support to a package, add the following to the info.xml
	file for a package under files/usr/local/share/<package port name>/info.xml
	inside the <package>...</package> tag:

	<logging>
		<!-- Name of the logging tab on this page -->
		<logtab>arpwatch</logtab>

		<!-- syslog facility name to use in the syslog configuration.
			Can be multiple values, comma separated (no spaces) -->
		<facilityname>programname</facilityname>

		<!-- Filename for the package log, relative to /var/log -->
		<logfilename>filename.log</logfilename>

		<!-- Add a syslogd log socket directory -->
		<logsocket>/path/to/pkgchroot/var/run/log</logsocket>

		<!-- user:group for the log, set after rotation -->
		<logowner>root:wheel</logowner>

		<!-- File mode for the log, set after rotation -->
		<logmode>600</logmode>

		<!-- Total number of log files to keep during rotation -->
		<rotatecount>7</rotatecount>

		<!-- File size (in bytes) at which to rotate the log, or '*' to
			disable size-based rotation -->
		<logfilesize>512000</logfilesize>

		<!-- newsyslog format time (ISO 8601 restricted time format or
			Day/week/month format) for time-based rotation. Omit or
			'*' for size-based rotation.
			See https://www.freebsd.org/cgi/man.cgi?query=newsyslog.conf&apropos=0&sektion=0&manpath=FreeBSD+12.0-RELEASE+and+Ports&arch=default&format=html
			-->
		<rotatetime>@T00</rotatetime>

		<!-- Extra newsyslog flags for this log. 'C' is always assumed,
			and the compression flag is set globally. -->
		<rotateflags>p</rotateflags>

		<!-- PID to signal, or path to cmd to run after rotation -->
		<pidcmd>/var/run/program.pid</pidcmd>

		<!-- Signal to send the PID found in pidcmd -->
		<signal>30</signal>
	</logging>

*/

##|+PRIV
##|*IDENT=page-status-packagelogs
##|*NAME=Status: Package logs
##|*DESCR=Allow access to the 'Status: Package logs' page.
##|*MATCH=status_logs_packages.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");
require_once("status_logs_common.inc");

global $g;
if (!($nentries = $config['syslog']['nentries'])) {
	$nentries = $g['default_log_entries'];
}

$i = 0;
$pkgwithlogging = false;
$apkg = $_REQUEST['pkg'];
if (!$apkg) { // If we aren't looking for a specific package, locate the first package that handles logging.
	if (isset($config['installedpackages']['package'])) {
		foreach ($config['installedpackages']['package'] as $package) {
			if (isset($package['logging']['logfilename']) && $package['logging']['logfilename'] != '') {
				$pkgwithlogging = true;
				$apkg = $package['name'];
				$apkgid = $i;
				break;
			}
			$i++;
		}
	}
} elseif ($apkg) {
	$apkgid = get_package_id($apkg);
	if ($apkgid != -1) {
		$pkgwithlogging = true;
		$i = $apkgid;
	}
}

// Log Filter Submit - System
log_filter_form_system_submit();

// Status Logs Common - Code
status_logs_common_code();

/* We do not necessarily know the format of package logs, so assume raw. */
$rawfilter = true;

/* Hide management icon/form since packages determine their own log settings. */
$system_logs_manage_log_form_hidden = false;

if ($filtertext) {
	$filtertextmeta="?filtertext=$filtertext";
}

$pgtitle = array(gettext("Status"), gettext("Package Logs"));
$pglinks = array("", "status_logs_packages.php");

if ($pkgwithlogging && !empty($apkg)) {
	$pgtitle[] = $apkg;
	$pglinks[] = "@self";
}
include("head.inc");

tab_array_logs_common();

// Filter Section/Form - System
filter_form_system();

$allowed_logs = array();

if ($pkgwithlogging == false) {
	print_info_box(gettext("No packages with logging facilities are currently installed."));
} else {
	$tab_array = array();
	foreach ($config['installedpackages']['package'] as $package) {
		if (is_array($package['logging'])) {
			if (!($logtab = $package['logging']['logtab'])) {
				$logtab = $package['name'];
			}

			if ($apkg == $package['name']) {
				$curtab = $logtab;
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), true, "status_logs_packages.php?pkg=".$package['name']);
			} else {
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), false, "status_logs_packages.php?pkg=".$package['name']);
			}
			$allowed_logs[$package['logging']['logfilename']] = array(
				"name" => gettext($logtab),
				"shortcut" => $package['name'],
			);
		}
	}
	display_top_tabs($tab_array);
	$logfile = $config['installedpackages']['package'][$apkgid]['logging']['logfilename'];
	$logfile_path = $g['varlog_path'] . '/' . $logfile;
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">
<?php
	print(system_log_table_panel_title());
?>
		</h2>
	</div>
	<div class="table table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr class="text-nowrap">
					<th style="width:100%"><?=gettext("Message")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	$inverse = null;
	system_log_filter();
?>
			</tbody>
		</table>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$("#count").html(<?=$rows?>);
});
//]]>
</script>

<?php
	if ($rows == 0) {
		print_info_box(gettext('No logs to display.'));
	}
?>
	</div>
</div>


<?php }

include("foot.inc"); ?>
