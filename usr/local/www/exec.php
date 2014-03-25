<?php
/* $Id$ */
/*
	Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
	Created by technologEase (http://www.technologEase.com).

	(modified for m0n0wall by Manuel Kasper <mk@neon1.net>)

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
	pfSense_MODULE:	shell
*/

##|+PRIV
##|*IDENT=page-diagnostics-command
##|*NAME=Diagnostics: Command page
##|*DESCR=Allow access to the 'Diagnostics: Command' page.
##|*MATCH=exec.php*
##|-PRIV

$allowautocomplete = true;

require("guiconfig.inc");

if (($_POST['submit'] == "Download") && file_exists($_POST['dlPath'])) {
	session_cache_limiter('public');
	$fd = fopen($_POST['dlPath'], "rb");
	header("Content-Type: application/octet-stream");
	header("Content-Length: " . filesize($_POST['dlPath']));
	header("Content-Disposition: attachment; filename=\"" .
		trim(htmlentities(basename($_POST['dlPath']))) . "\"");
	if (isset($_SERVER['HTTPS'])) {
		header('Pragma: ');
		header('Cache-Control: ');
	} else {
		header("Pragma: private");
		header("Cache-Control: private, must-revalidate");
	}

	fpassthru($fd);
	exit;
} else if (($_POST['submit'] == "Upload") && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	move_uploaded_file($_FILES['ulfile']['tmp_name'], "/tmp/" . $_FILES['ulfile']['name']);
	$ulmsg = "Uploaded file to /tmp/" . htmlentities($_FILES['ulfile']['name']);
	unset($_POST['txtCommand']);
}

if($_POST)
	conf_mount_rw();

// Function: is Blank
// Returns true or false depending on blankness of argument.

function isBlank( $arg ) { return preg_match( "/^\s*$/", $arg ); }


// Function: Puts
// Put string, Ruby-style.

function puts( $arg ) { echo "$arg\n"; }


// "Constants".

$Version    = '';
$ScriptName = $REQUEST['SCRIPT_NAME'];

// Get year.

$arrDT   = localtime();
$intYear = $arrDT[5] + 1900;

$pgtitle = array(gettext("Diagnostics"),gettext("Execute command"));
include("head.inc");
?>

<script type="text/javascript">
<!--

   // Create recall buffer array (of encoded strings).

<?php

if (isBlank( $_POST['txtRecallBuffer'] )) {
   puts( "   var arrRecallBuffer = new Array;" );
} else {
   puts( "   var arrRecallBuffer = new Array(" );
   $arrBuffer = explode( "&", $_POST['txtRecallBuffer'] );
   for ($i=0; $i < (count( $arrBuffer ) - 1); $i++) puts( "      '" . htmlspecialchars($arrBuffer[$i]) . "'," );
   puts( "      '" . htmlspecialchars($arrBuffer[count( $arrBuffer ) - 1]) . "'" );
   puts( "   );" );
}

?>

   // Set pointer to end of recall buffer.
   var intRecallPtr = arrRecallBuffer.length-1;

   // Functions to extend String class.
   function str_encode() { return escape( this ) }
   function str_decode() { return unescape( this ) }

   // Extend string class to include encode() and decode() functions.
   String.prototype.encode = str_encode
   String.prototype.decode = str_decode

   // Function: is Blank
   // Returns boolean true or false if argument is blank.
   function isBlank( strArg ) { return strArg.match( /^\s*$/ ) }

   // Function: frmExecPlus onSubmit (event handler)
   // Builds the recall buffer from the command string on submit.
   function frmExecPlus_onSubmit( form ) {

      if (!isBlank(form.txtCommand.value)) {
		  // If this command is repeat of last command, then do not store command.
		  if (form.txtCommand.value.encode() == arrRecallBuffer[arrRecallBuffer.length-1]) { return true }

		  // Stuff encoded command string into the recall buffer.
		  if (isBlank(form.txtRecallBuffer.value))
			 form.txtRecallBuffer.value = form.txtCommand.value.encode();
		  else
			 form.txtRecallBuffer.value += '&' + form.txtCommand.value.encode();
	  }

      return true;
   }

   // Function: btnRecall onClick (event handler)
   // Recalls command buffer going either up or down.
   function btnRecall_onClick( form, n ) {

      // If nothing in recall buffer, then error.
      if (!arrRecallBuffer.length) {
         alert( '<?=gettext("Nothing to recall"); ?>!' );
         form.txtCommand.focus();
         return;
      }

      // Increment recall buffer pointer in positive or negative direction
      // according to <n>.
      intRecallPtr += n;

      // Make sure the buffer stays circular.
      if (intRecallPtr < 0) { intRecallPtr = arrRecallBuffer.length - 1 }
      if (intRecallPtr > (arrRecallBuffer.length - 1)) { intRecallPtr = 0 }

      // Recall the command.
      form.txtCommand.value = arrRecallBuffer[intRecallPtr].decode();
   }

   // Function: Reset onClick (event handler)
   // Resets form on reset button click event.
   function Reset_onClick( form ) {

      // Reset recall buffer pointer.
      intRecallPtr = arrRecallBuffer.length;

      // Clear form (could have spaces in it) and return focus ready for cmd.
      form.txtCommand.value = '';
      form.txtCommand.focus();

      return true;
   }
//-->
</script>
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
<?php if (isBlank($_POST['txtCommand'])): ?>
<p class="red"><strong><?=gettext("Note: this function is unsupported. Use it " .
"on your own risk"); ?>!</strong></p>
<?php endif; ?>
<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>
<?php

if (!isBlank($_POST['txtCommand'])) {
   puts("<pre>");
   puts("\$ " . htmlspecialchars($_POST['txtCommand']));
   putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
   putenv("SCRIPT_FILENAME=" . strtok($_POST['txtCommand'], " "));	/* PHP scripts */
   $ph = popen($_POST['txtCommand'] . ' 2>&1', "r" );
   while ($line = fgets($ph)) echo htmlspecialchars($line);
   pclose($ph);
   puts("</pre>");
}


if (!isBlank($_POST['txtPHPCommand'])) {
   puts("<pre>");
   require_once("config.inc");
   require_once("functions.inc");
   echo eval($_POST['txtPHPCommand']);
   puts("</pre>");
}

?>
<div id="niftyOutter">
<form action="exec.php" method="post" enctype="multipart/form-data" name="frmExecPlus" onSubmit="return frmExecPlus_onSubmit( this );">
  <table>
	<tr>
	  <td colspan="2" valign="top" class="vnsepcell"><?=gettext("Execute Shell command"); ?></td>
	</tr>  
    <tr>
      <td class="label" align="right"><?=gettext("Command"); ?>:</td>
      <td class="type"><input id="txtCommand" name="txtCommand" type="text" class="formfld unknown" size="80" value="<?=htmlspecialchars($_POST['txtCommand']);?>"></td>
    </tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">
         <input type="hidden" name="txtRecallBuffer" value="<?=htmlspecialchars($_POST['txtRecallBuffer']) ?>">
         <input type="button" class="button" name="btnRecallPrev" value="<" onClick="btnRecall_onClick( this.form, -1 );">
         <input type="submit" class="button" value="<?=gettext("Execute"); ?>">
         <input type="button" class="button" name="btnRecallNext" value=">" onClick="btnRecall_onClick( this.form,  1 );">
         <input type="button"  class="button" value="<?=gettext("Clear"); ?>" onClick="return Reset_onClick( this.form );">
      </td>
    </tr>
	<tr>
	  <td colspan="2" valign="top" height="16"></td>
	</tr>
	<tr>
	  <td colspan="2" valign="top" class="vnsepcell"><?=gettext("Download"); ?></td>
	</tr>    
    <tr>
      <td align="right"><?=gettext("File to download"); ?>:</td>
      <td>
        <input name="dlPath" type="text" class="formfld file" id="dlPath" size="50">
	</td></tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">	
        <input name="submit" type="submit"  class="button" id="download" value="<?=gettext("Download"); ?>">
        </td>
    </tr>
	<tr>
	  <td colspan="2" valign="top" height="16"></td>
	</tr>
	<tr>
	  <td colspan="2" valign="top" class="vnsepcell"><?=gettext("Upload"); ?></td>
	</tr>    
    <tr>
      <td align="right"><?=gettext("File to upload"); ?>:</td>
      <td valign="top" class="label">
	<input name="ulfile" type="file" class="formfld file" id="ulfile">
	</td></tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">	
        <input name="submit" type="submit"  class="button" id="upload" value="<?=gettext("Upload"); ?>"></td>
    </tr>
	<tr>
	  <td colspan="2" valign="top" height="16"></td>
	</tr>
	<tr>
	  <td colspan="2" valign="top" class="vnsepcell"><?=gettext("PHP Execute"); ?></td>
	</tr>
	<tr>
		<td align="right"><?=gettext("Command"); ?>:</td>
		<td class="type"><textarea id="txtPHPCommand" name="txtPHPCommand" type="text" rows="9" cols="80"><?=htmlspecialchars($_POST['txtPHPCommand']);?></textarea></td>
	</tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">
         <input type="submit" class="button" value="<?=gettext("Execute"); ?>">
	 <p>
	 <strong><?=gettext("Example"); ?>:</strong>   interfaces_carp_setup();
      </td>
    </tr>
    
  </table>
</div>
<?php include("fend.inc"); ?>
</form>
<script language="Javascript">
document.forms[0].txtCommand.focus();
</script>
</body>
</html>

<?php

if($_POST)
	conf_mount_ro();

?>
