#!/usr/local/bin/php
<?php
/*
	$Id$

	diag_pkglogs.php
	Copyright (C) 2005 Colin Smith
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

require("guiconfig.inc");
//require_once("pkg-utils.inc");

if(!($nentries = $config['syslog']['nentries'])) $nentries = 50;

if ($_POST['clear']) {
	exec("/usr/sbin/clog -i -s 262144 {$logfile}");
}
$i = 0;
$pkgwithlogging = false;
$apkg = $_POST['pkg'];
if(!$apkg) { // If we aren't looking for a specific package, locate the first package that handles logging.
	if($config['installedpackages']['package'] <> "") {
		foreach($config['installedpackages']['package'] as $package) {
			if(is_array($package['logging'])) {
				$pkgwithlogging = true;
				$apkg = $package['name'];
				$apkgid = $i;
				break;
			}
			$i++;
		}
	}
} elseif($apkg) {
	$pkgwithlogging = true;
	$apkgid = get_pkg_id($apkg);
	$i = $apkgid;
}

$pgtitle = "Diagnostics: Package logs";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Diagnostics: Package logs</p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <?php
	if($pkgwithlogging == false) {
		print_info_box("No packages with logging facilities are currently installed.");
		echo '</ul></td></tr></table>';
		include("fend.inc");
		exit;
	}
	foreach($config['installedpackages']['package'] as $package) {
		if(is_array($package['logging'])) {
			if(!($logtab = $package['logging']['logtab'])) $logtab = $package['name'];
			if($apkg == $package['name']) { 
				$curtab = $logtab;
				echo '<li class="tabact">' . $logtab . '</li>';
			} else {
				Echo '<li class="tabinact"><a href="diag_pkglogs.php?pkg=' . htmlspecialchars($package['name']) . '">' . $logtab . '</a></li>';
			}
		}
       	 }
    ?> 
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
		  <tr>
			<td colspan="2" class="listtopic">
			  Last <?= $nentries ?> <?= $curtab ?> log entries</td>
		  </tr>
		  <?php
			$package =& $config['installedpackages']['package'][$apkgid];
			dump_clog($g['varlog_path'] . '/' . $package['logging']['logfilename'], $nentries);
		?>
		</table>
		<br>
<!--
<form action="diag_pkglogs.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log">
</form>
-->
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
