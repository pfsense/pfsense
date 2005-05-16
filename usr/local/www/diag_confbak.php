#!/usr/local/bin/php
<?php
/* $Id$ */
/*
    diag_confbak.php
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
*/

require("guiconfig.inc");

if($_GET['newver'] != "") {
	if(config_restore($g['conf_path'] . '/backup/config-' . $_GET['newver'] . '.xml') == 0) {
		$savemsg = "Successfully reverted to " . $_GET['newver'];
	} else {
		$savemsg = "Unable to revert to " . $_GET['newver'];
	}
}

if($_GET['rmver'] != "") {
	unlink_if_exists($g['conf_path'] . '/backup/config-' . $_GET['rmver'] . '.xml');
	$savemsg = "Deleted " . $_GET['rmver'];
}

cleanup_backupcache();
$confvers = get_backups();
unset($confvers['versions']);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title><?=gentitle("Diagnostics: Configuration Restore");?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link href="gui.css" rel="stylesheet" type="text/css">
</head>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
if($savemsg) print_info_box($savemsg);
?>
<p class="pgtitle">Diagnostics: Local Restore</p>
<br>
<table width="100%" border="0" cellpadding="0" cellspacing="0">  <tr><td>
  <ul id="tabnav">
    <li class="tabinact"><a href="diag_backup.php">Remote</a></li>
    <li class="tabact">Local</a></li>
  </ul>
  </td></tr>
  <tr>
    <td class="tabcont">
<?php
if(is_array($confvers)) { 
	      $i = 0;
		?>
              <table align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="30%" class="listhdrr">Date</td>
		  <td width="70%" class="listhdrr">Configuration Change</td>
                </tr>

		<?php
		  foreach($confvers as $version) {
			if($version['time'] != 0) {
				$date = date("n/j H:i:s", $version['time']);
			} else {
				$date = "Unknown";
			}
			$desc = $version['description'];
               ?>
                            <tr valign="top">
				<td class="listlr"> <?= $date ?></td>
                                <td class="listlr"> <?= $desc ?></td>
	       <?php	if($i != 0) { ?>
				<td valign="middle" class="list" nowrap>
                                <a href="diag_confbak.php?newver=<?=$version['time'];?>"><img src="plus.gif" width="17" height="17" border="0"></a>
                                </td>
				<td valign="middle" class="list" nowrap>
				<a href="diag_confbak.php?rmver=<?=$version['time'];?>"><img src="x.gif" width="17" height="17" border="0"></a>
	       <?php	} ?>
			    </tr>
               <?php
		  	$i++;
                  } ?>
		</table>
<?php } else {
		print_info_box("No backups found.");
      }
?>

    </td>
  </tr>
</table>
</body>
</html>
<?php include("fend.inc"); ?>
