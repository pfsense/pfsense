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

require("guiconfig.inc");

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
if(file_exists("/conf/backup/backup.cache")) {
	$confvers = unserialize(file_get_contents("/cf/conf/backup/backup.cache"));
} else {
	print_info_box("No backups found.");
}
if(is_array($confvers)) { ?>
              <table align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="30%" class="listhdrr">Date</td>
		  <td width="70%" class="listhdrr">Configuration Change</td>
                </tr>

		<?php
		  $curconfigs = array();
		  $i = 0;
		  foreach($confvers as $version) {
			$changes = array();
			if($version == $config['revision']['time'] and $i == 0) {
				$i++;
				$changes['description'] = $config['revision']['description'];
				$changes['time'] = $version;
				$changes['date'] = "Current";
			} else {	// Use backup.cache to find the next usable backup.
				if(file_exists("/conf/backup/config-{$version['time']}.xml")) {
					$changes = $version;
					$changes['date'] = date("n/j H:i:s", $changes['time']);
				} else {
					$i++;
					continue;
				}
			}

			if($changes['time'] == "") $changes['date'] = "Unknown.";

               ?>
                            <tr valign="top">
				<td class="listlr"> <?= $changes['date']  ?></td>
                                <td class="listlr"> <?= $changes['description'] ?></td>
	       <?php	if($changes['date'] != "Current") { ?>
				<td valign="middle" class="list" nowrap>
                                <a href="diag_confbak.php?newver=<?=$changes['time'];?>"><img src="plus.gif" width="17" height="17" border="0"></a>
                                </td>
	       <?php	} ?>
			    </tr>
               <?php
		  	$i++;
                  } ?>
		</table>
<?php } ?>
    </td>
  </tr>
</table>
</body>
</html>
