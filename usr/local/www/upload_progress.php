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

// sanitize the ID value
$id = $_REQUEST['uploadid'];
if (strlen($id) == 0) {
	echo "Invalid uploadid#.";
	exit;
}

// ensure the uploaded status data exists in the session
if (!array_key_exists($id, $_SESSION[self::SESSION_KEY])) {
	$_SESSION[self::SESSION_KEY][$id] = array(
		'id'       => $id,
		'finished' => false,
		'percent'  => 0,
		'total'    => 0,
		'complete' => 0
	);
}

// retrieve the data from the session so it can be updated and returned
$ret = $_SESSION[self::SESSION_KEY][$id];

// retrieve the upload data from APC
$status = apc_fetch('upload_' . $id);

// false is returned if the data isn't found
if ($status) {
	$ret['finished'] = (bool) $status['done'];
	$ret['total']    = $status['total'];
	$ret['complete'] = $status['current'];
	// calculate the completed percentage
	if ($ret['total'] > 0)
		$ret['percent'] = $ret['complete'] / $ret['total'] * 100;
	// write the changed data back to the session
	$_SESSION[self::SESSION_KEY][$id] = $ret;
}

if (!$ret) {

	if ( array_key_exists( "e", $_GET ) ) {
		echo "<html><body onLoad='window.close();'>Invalid Meter ID! {$_REQUEST['uploadid]}";
		echo ('</body></html>');
		exit;
	} else {
		echo ('<html><meta http-equiv="Refresh" CONTENT="1; url=progress.php?uploadid={$id}"><body></body></html>');
		exit;
	}

} else {

	if ($ret['percent'] > "99") {

		echo ('<html><body onLoad="window.close()"> UPLOAD completed!</body></html>');

	} else {

?>

<html>
<head>
	<meta http-equiv="Refresh" content="1; url=<?=$url?>">
	<title>Uploading Files... Please wait ...</title>
	<style type='text/css'>
		td {font-size: 10pt }
	</style>
</head>
<body bgcolor="#FFFFFF">
	<table height="100%" width="100%" cellPadding="4" cellSpacing="4" style="border:1px solid #990000;">
	<tr>
		<td>
			<font face="arial"><b><center>Uploading files...</b></center>
			<br>
			<table width="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
			<tr>
				<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" align="top"></td>
				<td>
					<table WIDTH="100%" height="15" colspacing="0" cellpadding="0" cellspacing="0" border="0" align="top" nowrap>
						<td background="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif">
							<?echo("<img src='./themes/".$g['theme']."/images/misc/bar_blue.gif' height='15' WIDTH='$meter%'>");?>
						</td>
					</table>
				</td>
				<td width="5" height="15" background="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" align="top"></td>
			</table>
			<br>
			<table width="100%">
				<tr>
					<td align="right"><font face="arial"><b>Uploaded:</td>
					<td><font face="arial"><?=$ret['finished']?></td>
					<td align="right"><font face="arial"><b>File Size:</td>
					<td><font face="arial"><?=$ret['total']?></td>
				</tr>
	   			<tr>
	   				<td align="right"><font face="arial"><b>Completed:</td>
					<td><font face="arial"><?=$ret['complete']?>%</td>
	   				<td align="right"><font face="arial"><b></td>
					<td><font face="arial"></td>
	   				</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
