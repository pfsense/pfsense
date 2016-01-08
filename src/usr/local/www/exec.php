<?php
/*
	exec.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
 *
 *	Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
 *	Created by technologEase (http://www.technologEase.com)
 *	(modified for m0n0wall by Manuel Kasper <mk@neon1.net>)\
 *
 *	Some or all of this file is based on the m0n0wall project which is
 *	Copyright (c)  2004 Manuel Kasper (BSD 2 clause)
 *
 *	Redistribution and use in source and binary forms, with or without modification,
 *	are permitted provided that the following conditions are met:
 *
 *	1. Redistributions of source code must retain the above copyright notice,
 *		this list of conditions and the following disclaimer.
 *
 *	2. Redistributions in binary form must reproduce the above copyright
 *		notice, this list of conditions and the following disclaimer in
 *		the documentation and/or other materials provided with the
 *		distribution.
 *
 *	3. All advertising materials mentioning features or use of this software
 *		must display the following acknowledgment:
 *		"This product includes software developed by the pfSense Project
 *		 for use in the pfSense software distribution. (http://www.pfsense.org/).
 *
 *	4. The names "pfSense" and "pfSense Project" must not be used to
 *		 endorse or promote products derived from this software without
 *		 prior written permission. For written permission, please contact
 *		 coreteam@pfsense.org.
 *
 *	5. Products derived from this software may not be called "pfSense"
 *		nor may "pfSense" appear in their names without prior written
 *		permission of the Electric Sheep Fencing, LLC.
 *
 *	6. Redistributions of any form whatsoever must retain the following
 *		acknowledgment:
 *
 *	"This product includes software developed by the pfSense Project
 *	for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *	OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *	====================================================================
 *
 */

##|+PRIV
##|*IDENT=page-diagnostics-command
##|*NAME=Diagnostics: Command
##|*DESCR=Allow access to the 'Diagnostics: Command' page.
##|*MATCH=exec.php*
##|-PRIV

$allowautocomplete = true;

require("guiconfig.inc");

if (($_POST['submit'] == "DOWNLOAD") && file_exists($_POST['dlPath'])) {
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
} else if (($_POST['submit'] == "UPLOAD") && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	move_uploaded_file($_FILES['ulfile']['tmp_name'], "/tmp/" . $_FILES['ulfile']['name']);
	$ulmsg = "Uploaded file to /tmp/" . htmlentities($_FILES['ulfile']['name']);
	unset($_POST['txtCommand']);
}

if ($_POST) {
	conf_mount_rw();
}

// Function: is Blank
// Returns true or false depending on blankness of argument.

function isBlank($arg) {
	return preg_match("/^\s*$/", $arg);
}

// Function: Puts
// Put string, Ruby-style.

function puts($arg) {
	echo "$arg\n";
}

// "Constants".

$Version = '';
$ScriptName = $REQUEST['SCRIPT_NAME'];

// Get year.

$arrDT = localtime();
$intYear = $arrDT[5] + 1900;

$pgtitle = array(gettext("Diagnostics"), gettext("Execute command"));
include("head.inc");
?>
<script type="text/javascript">
//<![CDATA[
	// Create recall buffer array (of encoded strings).
<?php

if (isBlank($_POST['txtRecallBuffer'])) {
	puts("	 var arrRecallBuffer = new Array;");
} else {
	puts("	 var arrRecallBuffer = new Array(");
	$arrBuffer = explode("&", $_POST['txtRecallBuffer']);
	for ($i = 0; $i < (count($arrBuffer) - 1); $i++) {
		puts("		'" . htmlspecialchars($arrBuffer[$i], ENT_QUOTES | ENT_HTML401) . "',");
	}
	puts("		'" . htmlspecialchars($arrBuffer[count($arrBuffer) - 1], ENT_QUOTES | ENT_HTML401) . "'");
	puts("	 );");
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
			if (isBlank(form.txtRecallBuffer.value)) {
				form.txtRecallBuffer.value = form.txtCommand.value.encode();
			} else {
				form.txtRecallBuffer.value += '&' + form.txtCommand.value.encode();
			}
		}

		return true;
	}

	// Function: btnRecall onClick (event handler)
	// Recalls command buffer going either up or down.
	function btnRecall_onClick( form, n ) {

		// If nothing in recall buffer, then error.
		if (!arrRecallBuffer.length) {
			alert('<?=gettext("Nothing to recall"); ?>!');
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
//]]>
</script>
<?php

if (isBlank($_POST['txtCommand']) && isBlank($_POST['txtPHPCommand']) && isBlank($ulmsg)) {
	print('<div class="alert alert-warning" role="alert">'.gettext("The capabilities offered here can be dangerous. No support is available. Use them at your own risk!").'</div>');
}

if (!isBlank($_POST['txtCommand'])):?>
	<div class="panel panel-success responsive">
		<div class="panel-heading"><h2 class="panel-title">Shell Output - <?=htmlspecialchars($_POST['txtCommand'])?></h2></div>
		<div class="panel-body">
			<div class="content">
<?php
	putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
	putenv("SCRIPT_FILENAME=" . strtok($_POST['txtCommand'], " "));
	$output = array();
	exec($_POST['txtCommand'] . ' 2>&1', $output);

	$output = implode("\n", $output);
	print("<pre>" . htmlspecialchars($output) . "</pre>");
?>
			</div>
		</div>
	</div>
<?php endif; ?>

<form action="exec.php" method="post" enctype="multipart/form-data" name="frmExecPlus" onsubmit="return frmExecPlus_onSubmit( this );">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Execute Shell Command')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input id="txtCommand" name="txtCommand" placeholder="Command" type="text" class="col-sm-4"	 value="<?=htmlspecialchars($_POST['txtCommand'])?>" />
				<br /><br />
				<input type="hidden" name="txtRecallBuffer" value="<?=htmlspecialchars($_POST['txtRecallBuffer']) ?>" />
				<input type="button" class="btn btn-default btn-sm" name="btnRecallPrev" value="<" onclick="btnRecall_onClick( this.form, -1 );" />
				<button type="submit" class="btn btn-default btn-sm" value="EXEC"><?=gettext("Execute"); ?></button>
				<input type="button" class="btn btn-default btn-sm" name="btnRecallNext" value=">" onclick="btnRecall_onClick( this.form,  1 );" />
				<input type="button" class="btn btn-default btn-sm" value="<?=gettext("Clear"); ?>" onclick="return Reset_onClick( this.form );" />
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Download file')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input name="dlPath" type="text" id="dlPath" placeholder="File to download" class="col-sm-4" value="<?php echo htmlspecialchars($_GET['dlPath']) ?>"/>
				<br /><br />
				<button name="submit" type="submit" class="btn btn-default btn-sm" id="download" value="DOWNLOAD"><?=gettext("Download")?></button>
			</div>
		</div>
	</div>

<?php
	if ($ulmsg) {
		print('<div class="alert alert-success" role="alert">' . $ulmsg .'</div>');
	}
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Upload file')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input name="ulfile" type="file" class="btn btn-default btn-sm btn-file" id="ulfile" />
				<br />
				<button name="submit" type="submit" class="btn btn-default btn-sm" id="upload" value="UPLOAD"><?=gettext("Upload")?></button>
			</div>
		</div>
	</div>
<?php
	// Experimental version. Writes the user's php code to a file and executes it via a new instance of PHP
	// This is intended to prevent bad code from breaking the GUI
	if (!isBlank($_POST['txtPHPCommand'])) {
		puts("<div class=\"panel panel-success responsive\"><div class=\"panel-heading\">PHP response</div>");

		$tmpname = tempnam("/tmp", "");
		$phpfile = fopen($tmpname, "w");
		fwrite($phpfile, "<?php\n");
		fwrite($phpfile, "require_once(\"/etc/inc/config.inc\");\n");
		fwrite($phpfile, "require_once(\"/etc/inc/functions.inc\");\n\n");
		fwrite($phpfile, $_POST['txtPHPCommand'] . "\n");
		fwrite($phpfile, "?>\n");
		fclose($phpfile);

		$output = array();
		exec("/usr/local/bin/php " . $tmpname, $output);

		unlink($tmpname);

		$output = implode("\n", $output);
		print("<pre>" . htmlspecialchars($output) . "</pre>");

//		echo eval($_POST['txtPHPCommand']);
		puts("</div>");
?>
<script type="text/javascript">
//<![CDATA[
	events.push(function() {
		// Scroll to the bottom of the page to more easily see the results of a PHP exec command
		$("html, body").animate({ scrollTop: $(document).height() }, 1000);
	});
//]]>
</script>
<?php
}
?>
	<div class="panel panel-default responsive">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Execute PHP Commands')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<textarea id="txtPHPCommand" placeholder="Command" name="txtPHPCommand" rows="9" cols="80"><?=htmlspecialchars($_POST['txtPHPCommand'])?></textarea>
				<br />
				<input type="submit" class="btn btn-default btn-sm" value="<?=gettext("Execute")?>" />
				<?=gettext("Example"); ?>: <code>print("Hello World!");</code>
			</div>
		</div>
	</div>
</form>

<?php
include("foot.inc");

if ($_POST) {
	conf_mount_ro();
}
