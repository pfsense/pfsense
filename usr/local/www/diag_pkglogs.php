<?php
/*
	$Id$

	diag_pkglogs.php
	Copyright (C) 2005 Colin Smith
	All rights reserved.

	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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

	<logging>
		<logtab>arpwatch</logtab>
		<grepfor>arpwatch</logtab>
	</logging>

		<invertgrep/>
		<logfile>/var/log/arpwatch.log</logfile>

*/

/*
	pfSense_BUILDER_BINARIES:	/usr/bin/netstat
	pfSense_MODULE:	pkgs
*/

##|+PRIV
##|*IDENT=page-status-packagelogs
##|*NAME=Status: Package logs page
##|*DESCR=Allow access to the 'Status: Package logs' page.
##|*MATCH=diag_pkglogs.php*
##|-PRIV

require("guiconfig.inc");
require("pkg-utils.inc");

if (!($nentries = $config['syslog']['nentries'])) {
	$nentries = 50;
}

$i = 0;
$pkgwithlogging = false;
$apkg = $_GET['pkg'];
if (!$apkg) { // If we aren't looking for a specific package, locate the first package that handles logging.
	if ($config['installedpackages']['package'] <> "") {
		foreach ($config['installedpackages']['package'] as $package) {
			if (is_array($package['logging'])) {
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

$pgtitle = array(gettext("Status"), gettext("Package logs"));
include("head.inc");

if($pkgwithlogging == false) {
	print_info_box(gettext("No packages with logging facilities are currently installed."));
} else {
	$tab_array = array();
	foreach($config['installedpackages']['package'] as $package) {
		if(is_array($package['logging'])) {
			if(!($logtab = $package['logging']['logtab']))
				$logtab = $package['name'];

			if($apkg == $package['name']) {
				$curtab = $logtab;
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), true, "diag_pkglogs.php?pkg=".$package['name']);
			} else {
				$tab_array[] = array(sprintf(gettext("%s"), $logtab), false, "diag_pkglogs.php?pkg=".$package['name']);
			}
		}
	}
	display_top_tabs($tab_array);
?>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=printf(gettext('Last %1$s %2$s log entries'),$nentries,$curtab)?></h2></div>
		<div>class="panel-body">
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