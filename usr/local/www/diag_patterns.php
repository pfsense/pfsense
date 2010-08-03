<?php
/* $Id$ */
/*
	Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
	Created by André Ribeiro and Hélder Pereira

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

/*	
		pfSense_MODULE:	shaper
*/

##|+PRIV
##|*IDENT=page-diagnostics-patters
##|*NAME=Diagnostics: Patterns page
##|*DESCR=Allow access to the 'Diagnostics: Patterns' page.
##|*MATCH=patterns.php*
##|-PRIV

require("guiconfig.inc");

//Move the upload file to /usr/local/share/protocols (is_uploaded_file must use tmp_name as argument)
if (($_POST['submit'] == gettext("Upload")) && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	if(fileExtension($_FILES['ulfile']['name'])) {
		move_uploaded_file($_FILES['ulfile']['tmp_name'], "/usr/local/share/protocols/" . $_FILES['ulfile']['name']);
		$ulmsg = gettext("Uploaded file to") . " /usr/local/share/protocols/" . htmlentities($_FILES['ulfile']['name']);
	}
	else
		$ulmsg = gettext("Warning: You must upload a file with .pat extension.");
}

//Check if file has correct extension (.pat)
function fileExtension($nameFile) {
	$format = substr($nameFile, -4);	
	return ($format == ".pat");	
}

$pgtitle = array(gettext("Diagnostics"), gettext("Add layer7 pattern"));
include("head.inc");
?>

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
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p><strong><?=gettext("You can upload new layer7 patterns to your system!");?></strong></p>
<?php if ($ulmsg) echo "<p class=\"red\"><strong>" . $ulmsg . "</strong></p>\n"; ?>
<div id="niftyOutter">
<form action="diag_patterns.php" method="POST" enctype="multipart/form-data" name="frmPattern">
  <table>
	
  <tr>
    <td colspan="2" valign="top" class="vnsepcell"><?=gettext("Upload");?></td>
  </tr>    
    <tr>
      <td align="right"><?=gettext("File to upload");?>:</td>
      <td valign="top" class="label">
	<input name="ulfile" type="file" class="formfld file" id="ulfile">
	</td></tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">	
	<input name="submit" type="submit"  class="button" id="upload" value="<?=gettext("Upload");?>"></td>
    </tr>
	<tr>
	  <td colspan="2" valign="top" height="16"></td>
	</tr>
	    
  </table>
</div>
<?php include("fend.inc"); ?>
</form>
</body>
</html>
