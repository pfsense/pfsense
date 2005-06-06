#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    system_firmware.php
    Copyright (C) 2004, 2005 Scott Ullrich and Colin Smith
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
require_once("xmlparse_pkg.inc");
require_once("xmlrpc.inc");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("System: Firmware Update");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
$versions = check_firmware_version();
print_r($versions);
?>
<p class="pgtitle">System: Firmware Update</p>
<br>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="system_firmware.php">Manual Update</a></li>
    <li class="tabact">Auto Update</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
<?php if(is_array($versions)) { ?>
              <table align="center" width="60%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="10%" class="listhdrr">Act</td>
                  <td width="30%" class="listhdrr">Category</td>
		  <td width="30%" class="listhdrr">Installed</td>
                  <td width="30%" class="listhdrr">Current<td>
                </tr>

		<?php
		  $currentvers = $versions['current'];
		  foreach($versions as $key => $version) {
			if($key == "current") continue;
			$currentver = array_shift(explode('-', $currentvers[$key]['version']));
			if($version == 1) {
                        	$img = "pass.gif";
				$pastlatest = true;
                        } elseif($currentver > $version[count($version) - 1]['version']) {
				$img = "pass.gif";
				$pastlatest = true;
			} else {
				$allinstall = true;
                        	$img = "block.gif";
			}
                            ?>
				
                            <tr valign="top">
                                <td class="listlr" nowrap align="middle"><img src="<?=$img;?>" width="11" height="11" align="absmiddle"></td>
				<td class="listlr"><?= ucfirst($key) ?></td>
                                <td class="listlr"><?= $currentver ?></td>
			<?php
			if($version == 1) {
			?>
				<td class="listlr"><?= $currentver ?></td>
			<?php
			} elseif($pastlatest) {
				$newver = $versions[$key]['version'];
			?>
				<td class="listbggrey"><font color="#FFFFFFF"><?= $newver ?></td>
			<?php
			} else {
				$newver = $versions[$key]['version'];
			?>
				<td class="listbg"><font color="#FFFFFFF"><?= $newver['version'] ?></td>
			<?php } 
			if(!$pastlatest) {
			 ?>
				<td valign="middle" class="list" nowrap>
                                <a href="system_firmware_auto.php?category=<?=$key;?>"><img src="plus.gif" width="17" height="17" border="0"></a>
                                </td>
				<?php }
                    }
		?>
		</table>
		<?php
		    if($allinstall == true) {
		?>
                                <br><br><br><table align="center"><tr><td><form action="system_firmware_auto.php" method="post" enctype="multipart/form-data">
                                        <input name="full" type="submit" class="formbtn" value="Begin Full Update">
                                </form></td></tr></table>
		<?php } ?>
		</tr>
<?php } else {
		print_info_box("Unable to receive version information.");
} ?>
    </td>
  </tr>
</table>
<?php
	include("fend.inc");
	$versions['cachetime'] = time();
	$fout = fopen("/tmp/versioncheck.cache", "w");
	fwrite($fout, serialize($versions));
	fclose($fout);
?>
</body>
</html>
