#!/usr/local/bin/php
<?php
/*
    carp_status.php
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
require_once("xmlparse.inc");

function gentitle_pkg($pgname) {
	global $config;
	return $config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pgname;
}

$status = get_carp_status();
if($_POST['disablecarp'] <> "") {
	if($status == true) {
		$carp_counter=find_number_of_created_carp_interfaces();
		mwexec("/sbin/sysctl net.inet.carp.allow=0");
		for($x=0; $x<$carp_counter; $x++) {
			mwexec("/sbin/ifconfig carp{$x} down");
			mwexec("/sbin/ifconfig carp{$x} destroy");
		}
		$savemsg = "{$carp_counter} IPs have been disabled.";
	} else {
		$savemsg = "CARP has been enabled.";
		mwexec("/sbin/sysctl net.inet.carp.allow=1");
		interfaces_carp_configure();
		interfaces_carp_bringup();
	}
}

$status = get_carp_status();

$pgtitle = "CARP: Status";
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<p class="pgtitle"><?=$pgtitle?></p>
<form action="carp_status.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td class="tabcont">

<?php
	if($status == false) {
		$carp_enabled = false;
		echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"Enable Carp\">";
	} else {
		$carp_enabled = true;
		echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"Disable Carp\">";
	}

if(is_array($config['virtualip']['vip'])) {
	foreach($config['virtualip']['vip'] as $carp) {
		if ($carp['mode'] == "carp") $carpcount++;
	}
	if ($carpcount == 0) {
	echo "</td></tr></table><center><br>Could not locate any defined CARP interfaces.";
	echo "</center>";

	include("fend.inc");
	echo "</body></html>";
	exit;
	}
}
?>

<p>

<table width="100%" border="0" cellpadding="6" cellspacing="0">
</tr>
<tr>
  <td class="listhdrr"><b><center>Carp Interface</center></b></td>
  <td class="listhdrr"><b><center>Virtual IP</center></b></td>
  <td class="listhdrr"><b><center>Status</center></b></td>
</tr>
<?php

if(is_array($config['virtualip']['vip'])) {
	$carpint=0;
	foreach($config['virtualip']['vip'] as $carp) {
		if ($carp['mode'] != "carp") continue;
		$ipaddress = $carp['subnet'];
		$password = $carp['password'];
		$netmask = $carp['subnet_bits'];
		$vhid = $carp['vhid'];
		$advskew = $carp['advskew'];
		$carp_int = find_carp_interface($ipaddress);
		$status = get_carp_interface_status($carp_int);
		echo "<tr>";
		$align = "valign='middle'";
		if($carp_enabled == false) {
			$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_block.gif'>";
			$status = "DISABLED";
			$carp_int = "carp" . $carpint;
		} else {
			if($status == "MASTER") {
				$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_pass.gif'>";
			} else if($status == "BACKUP") {
				$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_pass_d.gif'>";
			} else if($status == "INIT") {
				$icon = "<img {$align} src='/themes/".$g['theme']."/images/icons/icon_log.gif'>";
			}
		}
		echo "<td class=\"listlr\"><center>" . $carp_int . "&nbsp;</td>";
		echo "<td class=\"listlr\"><center>" . $ipaddress . "&nbsp;</td>";
		echo "<td class=\"listlr\"><center>{$icon}&nbsp;&nbsp;" . $status . "&nbsp;</td>";
		echo "</tr>";
		$carpint++;
	}
}
?>
<tr><td>
<center>
<?php
  echo "<br>pfSync nodes:<br>";
  echo "<pre>";
  system("/sbin/pfctl -vvss | /usr/bin/grep creator | /usr/bin/cut -d\" \" -f7 | /usr/bin/sort -u");
  echo "</pre>";
?>
</center>
</td></tr>
</table>
</td></tr>
</table>
</div>

<?php include("fend.inc"); ?>
<script type="text/javascript">
NiftyCheck();
Rounded("div#mainlevel","all","#FFF","#eeeeee","smooth");
</script>

</body>
</html>
