<?php
/*
 * status_pkglogs.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
	<logging>
		<logtab>arpwatch</logtab>
		<grepfor>arpwatch</logtab>
	</logging>

		<invertgrep/>
		<logfile>/var/log/arpwatch.log</logfile>

*/

##|+PRIV
##|*IDENT=page-status-packagelogs
##|*NAME=Status: Package logs
##|*DESCR=Allow access to the 'Status: Package logs' page.
##|*MATCH=status_pkglogs.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

if (!($nentries = $config['syslog']['nentries'])) {
	$nentries = 50;
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

$pgtitle = array(gettext("Status"), gettext("Package Logs"));
$pglinks = array("", "status_pkglogs.php");

if ($pkgwithlogging && !empty($apkg)) {
	$pgtitle[] = $apkg;
	$pglinks[] = "@self";
}
include("head.inc");

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
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), true, "status_pkglogs.php?pkg=".$package['name']);
			} else {
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), false, "status_pkglogs.php?pkg=".$package['name']);
			}
		}
	}
	display_top_tabs($tab_array);
?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext('Last %1$s %2$s Log Entries'), $nentries, $curtab)?></h2></div>
		<div class="panel-body">
			<pre>
<?php
			$package = $config['installedpackages']['package'][$apkgid];
			dump_clog_no_table($g['varlog_path'] . '/' . $package['logging']['logfilename'], $nentries, true, array());
?>
			</pre>
		</div>
	</div>

<?php }

include("foot.inc"); ?>
