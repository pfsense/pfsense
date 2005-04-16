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
require("xmlparse_pkg.inc");

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if ($_POST['clear']) {
	exec("/usr/sbin/clog -i -s 262144 {$system_logfile}");
}

$i = 0;
$apkg = $_POST['pkg'];
if(!isset($_POST['pkg'])) { // If we aren't looking for a specific package, locate the first package that handles logging.
	if(is_array($config['installedpackages']['package']))
		foreach($config['installedpackages']['package'] as $package) {
			$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
			if(is_array($pkg_config['logging'])) {
				$apkg = $package['name'];
				$apkgid = $i;
				break;
			}
			$i++;
		}
} else {
	$apkgid = get_pkg_id($apkg);
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Diagnostics: Package logs");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle">Diagnostics: Package logs</p>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td>
  <ul id="tabnav">
    <?php
	if($apkgid == -1) {
                print_info_box_np("The specified package, {$apkg}, is not installed.");
		include("fend.inc"); ?>
		</html>
    <?php	exit();
		}
	if($i == 0) {
		print_info_box_np("No packages are currently installed.");
		include("fend.inc"); ?>
		</html>
    <?php	exit();
	
	
	foreach($config['installedpackages']['package'] as $package) {
        	$pkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $package['configurationfile'], "packagegui");
		if(is_array($pkg_config['logging'])) {
			$pkgname = $package['name'];
			$logtab = $pkg_config['logging']['logtab'];
			if(!isset($pkg_config['logging']['logtab'])) $logtab = $pkgname;
			if($apkg == $pkgname) { ?>
				<li class="tabact"><?= $pkg_config['name']; ?></li>
    <?php
			} else { ?>
				<li class="tabinact"><a href="diag_pkglogs.php?pkg=<?= $pkgname; ?>"><?= $logtab; ?></a></li>
    <?php
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
			  Last <?=$nentries;?> <?=$apkg;?> log entries</td>
		  </tr>
		  <?php
			$apkg_config = parse_xml_config_pkg("/usr/local/pkg/" . $config['installedpackages']['package'][$apkgid]['configurationfile'], "packagegui");
			if(isset($apkg_config['logging']['logfile'])) {
				$logfile = $apkg_config['logging']['logfile'];
			} else {
				$logfile = "{$g['varlog_path']}/system.log";
			}
			if(isset($apkg_config['logging']['custom_php_logging_command'])) {
				eval($apkg_config['custom_php_global_functions']);
				eval($apkg_config['logging']['custom_php_logging_command']);
			} elseif(isset($apkg_config['logging']['grepfor']) and isset($apkg_config['logging']['invertgrep'])) {
				dump_clog($logfile, $nentries, $apkg_config['logging']['grepfor'], true);
			} elseif(isset($apkg_config['logging']['grepfor'])) {
				dump_clog($logfile, $nentries, $apkg_config['logging']['grepfor']);
			} else {
				dump_clog($logfile, $nentries);
			}
		?>
		</table>
		<br><form action="diag_pkglogs.php" method="post">
<input name="clear" type="submit" class="formbtn" value="Clear log">
</form>
	</td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
