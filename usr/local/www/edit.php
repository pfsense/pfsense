#!/usr/local/bin/php
<?php
/*
    edit.php
    Copyright (C) 2004, 2005 Scott Ullrich
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

if (($_POST['submit'] == "Load") && file_exists($_POST['savetopath'])) {
	$fd = fopen($_POST['savetopath'], "r");
	$content = fread($fd, filesize($_POST['savetopath']));
	fclose($fd);
	$edit_area="";
	$ulmsg = "Loaded text from " . $_POST['savetopath'];
} else if (($_POST['submit'] == "Save")) {
	$content = ereg_replace("\r","",$_POST['content']) ;
	$fd = fopen($_POST['savetopath'], "w");
	fwrite($fd, $content);
	fclose($fd);
	$edit_area="";
	$ulmsg = "Saved text to " . $_POST['savetopath'];
} else if (($_POST['submit'] == "Load") && !file_exists($_POST['savetopath'])) {
	$ulmsg = "File not found " . $_POST['savetopath'];
	$content = "";
	$_POST['savetopath'] = "";
}

if($_POST['rows'] <> "")
	$rows = $_POST['rows'];
else
	$rows = 40;

if($_POST['cols'] <> "")
	$cols = $_POST['cols'];
else
	$cols = 80;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<?php

/*
	Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
	Created by technologEase (http://www.technologEase.com).
	(modified for m0n0wall by Manuel Kasper <mk@neon1.net>)
        (modified for pfSense Edit/Save file by Scott Ullrich, Copyright 2004, 2005)
*/

include("fbegin.inc");

// Function: is Blank
// Returns true or false depending on blankness of argument.

function isBlank( $arg ) { return ereg( "^\s*$", $arg ); }

// Function: Puts
// Put string, Ruby-style.

function puts( $arg ) { echo "$arg\n"; }

// "Constants".

$Version    = '';
$ScriptName = $HTTP_SERVER_VARS['SCRIPT_NAME'];
$Title      = gentitle("edit file");

// Get year.

$arrDT   = localtime();
$intYear = $arrDT[5] + 1900;

?>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title><?=$Title ?></title>
<link href="gui.css" rel="stylesheet" type="text/css">
<style>
<!--

input {
   font-family: courier new, courier;
   font-weight: normal;
   font-size: 9pt;
}

pre {
   border: 2px solid #435370;
   background: #F0F0F0;
   padding: 1em;
   font-family: courier new, courier;
   white-space: pre;
   line-height: 10pt;
   font-size: 10pt;
}

.label {
   font-family: tahoma, verdana, arial, helvetica;
   font-size: 11px;
   font-weight: bold;
}

.button {
   font-family: tahoma, verdana, arial, helvetica;
   font-weight: bold;
   font-size: 11px;
}

-->
</style>
</head>
<script language="Javascript">
function sf() { document.forms[0].savetopath.focus(); }
</script>
<body onLoad="sf();">
<p><span class="pgtitle"><?=$Title ?></span>
<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>

<form action="<?=$ScriptName ?>" method="POST">
  <table>
    <tr>
      <td>
        Save/Load from path: <input size="42" id="savetopath" name="savetopath" value="<?php echo $_POST['savetopath']; ?>"> |
	Rows: <input size="3" name="rows" value="<? echo $rows; ?>"> |
	Cols: <input size="3" name="cols" value="<? echo $cols; ?>"> |
        <input name="submit" type="submit"  class="button" id="Load" value="Load"> | <input name="submit" type="submit"  class="button" id="Save" value="Save">
	<br><hr noshade width=100%>
        </td>
    </tr>
    <tr>
      <td valign="top" class="label">
	<textarea rows="<?php echo $rows; ?>" cols="<?php echo $cols; ?>" name="content"><?php echo $content; ?></textarea><br>
        <p>
    </td>
    </tr>
  </table>
<?php include("fend.inc"); ?>
</form>
</body>
</html>


<script language="Javascript">
sf();
</script>