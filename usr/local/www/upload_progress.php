<?php
/* $Id$ */
/*
	upload_progress.php
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
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

/* DISABLE_PHP_LINT_CHECKING */

##|+PRIV
##|*IDENT=page-upload_progress
##|*NAME=System: Firmware: Manual Update page (progress bar)
##|*DESCR=Allow access to the 'System: Firmware: Manual Update: Progress bar' page.
##|*MATCH=upload_progress*
##|-PRIV

include("guiconfig.inc");

if (!session_id())
	session_start();

// sanitize the ID value
$id = $_SESSION['uploadid'];
if (!$id) {
	echo gettext("Sorry, we could not find an uploadid code.");
	exit;
}
session_commit();

// retrieve the upload data from APC
$info = uploadprogress_get_info($id);

// false is returned if the data isn't found
if (!$info) {
	echo <<<EOF
	<html>
		<meta http-equiv="Refresh" CONTENT="1; url=upload_progress.php?uploadid={$id}">
		<body>
			<?php printf(gettext("Could not locate progress %s.  Trying again..."),$id);?>
		</body>
	</html>
EOF;
	exit;
}

if ($info['bytes_uploaded'] >= $info['bytes_total']) {
	echo <<<EOF1
	<html>
		<body onLoad="window.close();">
			&nbsp;<p>
			&nbsp;<p>
			<center>
				<b>
					<?=gettext("UPLOAD completed!");?>
				</b>
			</center>
		</body>
	</html>
EOF1;
	exit;
}

?>

<html>
<head>
	<meta http-equiv="Refresh" content="1; url=<?=$url?>">
	<title><?=gettext("Uploading Files"); ?>... <?=gettext("Please wait"); ?> ...</title>
	<style type='text/css'>
		td {font-size: 10pt }
	</style>
</head>
<body bgcolor="#FFFFFF">
	<table height="100%" width="100%" cellPadding="4" cellSpacing="4" style="border:1px solid #990000;">
	<tr>
		<td>
			<font face="arial"><b><center><?=gettext("Uploading file");?>...</b></center>
			<br>
			<table width="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
			<tr>
				<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" align="top">
				</td>
				<td>
					<table WIDTH="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
					<tr>
						<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif">
							<?php
							$meter = sprintf("%.2f", $info['bytes_uploaded'] / $info['bytes_total'] * 100);
							echo "<img src='./themes/{$g['theme']}/images/misc/bar_blue.gif' height='15' WIDTH='{$meter}%'>";
							?>
						</td>
					</tr>
					</table>
				</td>
				<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" align="top">
				</td>
			</tr>
			</table>
			<br>
			<table width="100%">
			<tr>
				<td align="right">
					<font face="arial">
					<b>
					<?=gettext("Uploaded");?>:
				</td>
				<td>
					<font face="arial">
					<b>
					<?=$info['bytes_uploaded']?>
				</td>
				<td align="right">
					<font face="arial">
					<b>
					<?=gettext("File Size");?>:
				</td>
				<td>
					<font face="arial">
					<b>
					<?=$info['bytes_total']?>
				</td>
			</tr>
   			<tr>
   				<td align="right">
					<font face="arial">
					<b>
					<?=gettext("Completed");?>:
				</td>
				<td>
					<font face="arial">
					<b>
					<?=$info['bytes_total']-$info['bytes_uploaded']?>%
				</td>
   				<td align="right">
					<font face="arial">
					<b>
					<?=gettext("Estimated");?>:
				</td>
				<td>
					<font face="arial">
					<b>
					<?=$info['est_sec']?>
				</td>
   			</tr>
			</table>
		</td>
	</tr>
	</table>
</body>
</html>
