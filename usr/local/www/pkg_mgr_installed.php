#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    pkg_mgr.php
    Copyright (C) 2004 Scott Ullrich
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

require_once("guiconfig.inc");
require_once("pkg-utils.inc");

foreach($config['installedpackages']['package'] as $instpkg) {
	$tocheck[] = $instpkg['name'];
}
$currentvers = get_pkg_info($tocheck, array('version'));

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Package Manager");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle">System: Package Manager</p>
<?php if ($savemsg) print_info_box($savemsg); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="pkg_mgr.php">Available Packages</a></li>
    <li class="tabact">Installed Packages</li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="25%" class="listhdrr">Package Name</td>
                  <td width="20%" class="listhdrr">Category</td>
                  <td width="10%" class="listhdrr">Version</td>
                  <td width="45%" class="listhdr">Description</td>
                </tr>

		<?php
                 if($config['installedpackages']['package'] != "") {
		    $instpkgs = array();
		    foreach($config['installedpackages']['package'] as $instpkg) $instpkgs[] = $instpkg['name'];
		    asort($instpkgs);
		    foreach ($instpkgs as $index => $pkgname){
			if(!is_string($pkgname)) {
				echo "<tr><td colspan=\"3\"><center>There are currently no packages installed.</td></tr>";
				break;
			}
			$pkg = $config['installedpackages']['package'][$index];
                        if($pkg['name'] <> "") {
                            ?>
                            <tr valign="top">
                                <td class="listlr">
                                    <?= $pkg['name'] ?>
                                </td>
                                <td class="listlr">
                                    <?= $pkg['category'] ?>
                                </td>
                                <td class="listlr">
                                    <?php
                                        $latest_version = $currentvers[$pkg['name']]['version'];
					if($latest_version == false) {
						// We can't determine this package's version status.
						echo "Upgrade: Unknown.<br>Installed: " . $pkg['version'];
                                        } elseif($pkg['version'] <> $latest_version) {
                                            /* a new version of the package is available */
                                            $id = get_pkg_id($pkg['name']);
                                            echo "Upgrade: <a href='pkg_mgr_delete.php?upgrade=true&pkg={$pkg['name']}'>" . $latest_version . "</a>";
                                            echo "<br>Installed: " . $pkg['version'];
                                        } else {
                                            echo $pkg['version'];
                                        }
                                    ?>
                                </td>
                                <td class="listbg">
                                    <font color="#FFFFFFF">
                                    <?= $pkg['descr'] ?>
                                </td>
                                <td valign="middle" class="list" nowrap>
                                    <a onclick="return confirm('Do you really want to remove this package?')" href="pkg_mgr_delete.php?pkg=<?= $pkg['name']; ?>&version=<?= $pkg['version']; ?>"><img src="x.gif" width="17" height="17" border="0"></a>
                                </td>
                            </tr>
                            <?php
                        }
		    }
                 } else {
                    echo "<tr><td colspan=\"3\"><center>There are currently no packages installed.</td></tr>";
                 }
		?>
        </table>
    </td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>

<?php
    conf_mount_ro();
?>
