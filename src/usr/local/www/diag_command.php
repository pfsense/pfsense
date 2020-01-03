<?php
/*
 * diag_command.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Exec+ v1.02-000 - Copyright 2001-2003, All rights reserved
 * Created by technologEase (http://www.technologEase.com)
 * (modified for m0n0wall by Manuel Kasper <mk@neon1.net>)\
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-diagnostics-command
##|*NAME=Diagnostics: Command
##|*DESCR=Allow access to the 'Diagnostics: Command' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_command.php*
##|-PRIV

$allowautocomplete = true;

require_once("guiconfig.inc");

if ($_POST['submit'] == "DOWNLOAD" && file_exists($_POST['dlPath'])) {
	session_cache_limiter('public');
	send_user_download('file', $_POST['dlPath']);
} else if ($_POST['submit'] == "UPLOAD" && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	move_uploaded_file($_FILES['ulfile']['tmp_name'], $g["tmp_path"] . "/" . $_FILES['ulfile']['name']);
	$ulmsg = sprintf(gettext('Uploaded file to %s.'), $g["tmp_path"] . "/" . htmlentities($_FILES['ulfile']['name']));
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

$pgtitle = array(gettext("Diagnostics"), gettext("Command Prompt"));
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
	print_callout(gettext("The capabilities offered here can be dangerous. No support is available. Use them at your own risk!"), 'danger', gettext('Advanced Users Only'));
}

if ($_POST['submit'] == "EXEC" && !isBlank($_POST['txtCommand'])):?>
	<div class="panel panel-success responsive">
		<div class="panel-heading"><h2 class="panel-title"><?=sprintf(gettext('Shell Output - %s'), htmlspecialchars($_POST['txtCommand']))?></h2></div>
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

<form action="diag_command.php" method="post" enctype="multipart/form-data" name="frmExecPlus" onsubmit="return frmExecPlus_onSubmit( this );">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Execute Shell Command')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input id="txtCommand" name="txtCommand" placeholder="Command" type="text" class="col-sm-7"	 value="<?=htmlspecialchars($_POST['txtCommand'])?>" />
				<br /><br />
				<input type="hidden" name="txtRecallBuffer" value="<?=htmlspecialchars($_POST['txtRecallBuffer']) ?>" />

				<div class="btn-group">
					<button type="button" class="btn btn-success btn-sm" name="btnRecallPrev" onclick="btnRecall_onClick( this.form, -1 );" title="<?=gettext("Recall Previous Command")?>">
						<i class="fa fa-angle-double-left"></i>
					</button>
					<button name="submit" type="submit" class="btn btn-warning btn-sm" value="EXEC" title="<?=gettext("Execute the entered command")?>">
						<i class="fa fa-bolt"></i>
						<?=gettext("Execute"); ?>
					</button>
					<button type="button" class="btn btn-success btn-sm" name="btnRecallNext" onclick="btnRecall_onClick( this.form,  1 );" title="<?=gettext("Recall Next Command")?>">
						<i class="fa fa-angle-double-right"></i>
					</button>
					<button style="margin-left: 10px;" type="button" class="btn btn-default btn-sm" onclick="return Reset_onClick( this.form );" title="<?=gettext("Clear command entry")?>">
						<i class="fa fa-undo"></i>
						<?=gettext("Clear"); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Download File')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input name="dlPath" type="text" id="dlPath" placeholder="File to download" class="col-sm-4" value="<?=htmlspecialchars($_REQUEST['dlPath']);?>"/>
				<br /><br />
				<button name="submit" type="submit" class="btn btn-primary btn-sm" id="download" value="DOWNLOAD">
					<i class="fa fa-download icon-embed-btn"></i>
					<?=gettext("Download")?>
				</button>
			</div>
		</div>
	</div>

<?php
	if ($ulmsg) {
		print_info_box($ulmsg, 'success', false);
	}
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Upload File')?></h2></div>
		<div class="panel-body">
			<div class="content">
				<input name="ulfile" type="file" class="btn btn-default btn-sm btn-file" id="ulfile" />
				<br />
				<button name="submit" type="submit" class="btn btn-primary btn-sm" id="upload" value="UPLOAD">
					<i class="fa fa-upload icon-embed-btn"></i>
					<?=gettext("Upload")?>
				</button>
			</div>
		</div>
	</div>
<?php

	// Experimental version. Writes the user's php code to a file and executes it via a new instance of PHP
	// This is intended to prevent bad code from breaking the GUI
	if ($_POST['submit'] == "EXECPHP" && !isBlank($_POST['txtPHPCommand'])) {

		safe_mkdir($g['tmp_path_user_code']);     //create if doesn't exist
		$tmpfile = tempnam($g['tmp_path_user_code'], "");
		$phpcode = <<<END_FILE
<?php
require_once("/etc/inc/config.inc");
require_once("/etc/inc/functions.inc");

// USER CODE STARTS HERE:

%s
?>
END_FILE;
		$lineno_correction = 6;  // line numbering correction, this should be the number of lines added above, BEFORE the user's code

		file_put_contents($tmpfile, sprintf($phpcode, $_POST['txtPHPCommand']));

		$output = $matches = array();
		$retval = 0;
		exec("/usr/local/bin/php -d log_errors=off {$tmpfile}", $output, $retval);

		puts('<div class="panel panel-success responsive"><div class="panel-heading"><h2 class="panel-title">PHP Response</h2></div>');

		// Help user to find bad code line, if it gave an error
		$errmsg_found = preg_match("`error.*:.* (?:in|File:) {$tmpfile}(?:\(| on line |, Line: )(\d+)(?:, Message:|\).* eval\(\)'d code|$)`i", implode("\n", $output), $matches);
		if ($retval || $errmsg_found) {
			/* Trap failed code - test both retval and output message
			 * Typical messages as at 2.3.x:
			 *   "Parse error: syntax error, ERR_DETAILS in FILE on line NN"
			 *   "PHP ERROR: Type: NN, File: FILE, Line: NN, Message: ERR_DETAILS"
			 *   "Parse error: syntax error, unexpected end of file in FILE(NN) : eval()'d code on line 1" [the number in (..) is the error line]
			*/
			if ($matches[1] > $lineno_correction) {
				$errline = $matches[1] - $lineno_correction;
				$errtext = sprintf(gettext('Line %s appears to have generated an error, and has been highlighted. The full response is below.'), $errline);
			} else {
				$errline = -1;
				$errtext = gettext('The code appears to have generated an error, but the line responsible cannot be identified. The full response is below.');
			}
			$errtext .= '<br/>' . sprintf(gettext('Note that the line number in the full PHP response will be %s lines too large. Nested code and eval() errors may incorrectly point to "line 1".'), $lineno_correction);
			$syntax_output = array();
			$html = "";
			exec("/usr/local/bin/php -s -d log_errors=off {$tmpfile}", $syntax_output);
			// Lines 0, 2 and 3 are CSS wrapper for the syntax highlighted code which is at line 1 <br> separated.
			$syntax_output = explode("<br />", $syntax_output[1]);
			$margin_layout = '%3s %' . strlen(count($syntax_output)) . 'd:';
			for ($lineno = 1; $lineno < count($syntax_output) - $lineno_correction; $lineno++) {
				$margin = str_replace(' ', '&nbsp;', sprintf($margin_layout, ($lineno == $errline ? '&gt;&gt;&gt;' : ''), $lineno));
				$html .= "<span style='color:black;backgroundcolor:lightgrey'><tt>{$margin}</tt></span>&nbsp;&nbsp;{$syntax_output[$lineno + $lineno_correction - 1]}<br/>\n";
			}
			print_info_box($errtext, 'danger');
			print "<div style='margin:20px'><b>" . gettext("Error locator:") . "</b>\n";
			print "<div id='errdiv' style='height:7em; width:60%; overflow:auto; white-space: nowrap; border:darkgrey solid 1px; margin-top: 20px'>\n";
			print $html . "\n</div></div>\n";
		}

		$output = implode("\n", $output);
		print("<pre>" . htmlspecialchars($output) . "</pre>");

//		echo eval($_POST['txtPHPCommand']);

		puts("</div>");

		unlink($tmpfile);
?>
<script type="text/javascript">
//<![CDATA[
	events.push(function() {
		// scroll error locator if needed (does nothing if no error)
		$('#errdiv').scrollTop(<?=max($errline - ($lineno_correction - 3.5), 0);?> * parseFloat($('#errdiv').css('line-height')));

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
				<button name="submit" type="submit" class="btn btn-warning btn-sm" value="EXECPHP" title="<?=gettext("Execute this PHP Code")?>">
					<i class="fa fa-bolt"></i>
					<?=gettext("Execute")?>
				</button>
				<?=gettext("Example"); ?>: <code>print("Hello World!");</code>
			</div>
		</div>
	</div>
</form>

<?php
include("foot.inc");

if ($_POST) {
}
