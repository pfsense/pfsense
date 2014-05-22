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
/*
	pfSense_MODULE:	shell
*/

##|+PRIV
##|*IDENT=page-diagnostics-edit
##|*NAME=Diagnostics: Edit FIle
##|*DESCR=Allow access to the 'Diagnostics: Edit File' page.
##|*MATCH=edit.php*
##|*MATCH=browser.php*
##|*MATCH=filebrowser/browser.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Edit file"));
require("guiconfig.inc");

if($_POST['action']) {
	switch($_POST['action']) {
		case 'load':
			if(strlen($_POST['file']) < 1) {
				echo "|5|" . gettext("No file name specified") . ".|";
			} elseif(is_dir($_POST['file'])) {
				echo "|4|" . gettext("Loading a directory is not supported") . ".|";
			} elseif(! is_file($_POST['file'])) {
				echo "|3|" . gettext("File does not exist or is not a regular file") . ".|";
			} else {
				$data = file_get_contents(urldecode($_POST['file']));
				if($data === false) {
					echo "|1|" . gettext("Failed to read file") . ".|";
				} else {
					$data = base64_encode($data);
					echo "|0|{$_POST['file']}|{$data}|";	
				}
			}
			exit;
		case 'save':
			if(strlen($_POST['file']) < 1) {
				echo "|" . gettext("No file name specified") . ".|";
			} else {
				conf_mount_rw();
				$_POST['data'] = str_replace("\r", "", base64_decode($_POST['data']));
				$ret = file_put_contents($_POST['file'], $_POST['data']);
				conf_mount_ro();
				if($_POST['file'] == "/conf/config.xml" || $_POST['file'] == "/cf/conf/config.xml") {
					if(file_exists("/tmp/config.cache"))
						unlink("/tmp/config.cache");
					disable_security_checks();
				}
				if($ret === false) {
					echo "|" . gettext("Failed to write file") . ".|";
				} elseif($ret <> strlen($_POST['data'])) {
					echo "|" . gettext("Error while writing file") . ".|";
				} else {
					echo "|" . gettext("File successfully saved") . ".|";
				}
			}
			exit;
	}
	exit;
}

$closehead = false;
require("head.inc");
outputCSSFileInline("code-syntax-highlighter/SyntaxHighlighter.css");
outputJavaScriptFileInline("filebrowser/browser.js");
outputJavaScriptFileInline("javascript/base64.js");

?>
</head>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">	
//<![CDATA[
	function loadFile() {
		jQuery("#fileStatus").html("<?=gettext("Loading file"); ?> ...");
		jQuery("#fileStatusBox").show(500);

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>", {
				type: "post",
				data: "action=load&file=" + jQuery("#fbTarget").val(),
				complete: loadComplete
			}
		);
	}

	function loadComplete(req) {
		jQuery("#fileContent").show(1000);
		var values = req.responseText.split("|");
		values.shift(); values.pop();

		if(values.shift() == "0") {
			var file = values.shift();
			var fileContent = Base64.decode(values.join("|"));
			jQuery("#fileStatus").html("<?=gettext("File successfully loaded"); ?>.");
			jQuery("#fileContent").val(fileContent);

			var lang = "none";
				 if(file.indexOf(".php") > 0) lang = "php";
			else if(file.indexOf(".inc") > 0) lang = "php";
			else if(file.indexOf(".xml") > 0) lang = "xml";
			else if(file.indexOf(".js" ) > 0) lang = "js";
			else if(file.indexOf(".css") > 0) lang = "css";

			if(jQuery("#highlight").checked && lang != "none") {
				jQuery("fileContent").prop("className",lang + ":showcolumns");
				dp.SyntaxHighlighter.HighlightAll("fileContent", true, false);
			}
		}
		else {
			jQuery("#fileStatus").html(values[0]);
			jQuery("#fileContent").val("");
		}
		jQuery("#fileContent").show(1000);
	}

	function saveFile(file) {
		jQuery("#fileStatus").html("<?=gettext("Saving file"); ?> ...");
		jQuery("#fileStatusBox").show(500);
		
		var fileContent = Base64.encode(jQuery("#fileContent").val());
		fileContent = fileContent.replace(/\+/g,"%2B");
		
		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>", {
				type: "post",
				data: "action=save&file=" + jQuery("#fbTarget").val() +
							"&data=" + fileContent,
				complete: function(req) {
					var values = req.responseText.split("|");
					jQuery("#fileStatus").html(values[1]);
				}
			}
		);
	}
//]]>
</script>

<!-- file status box -->
<div style="display:none; background:#eeeeee;" id="fileStatusBox">
	<div class="vexpl" style="padding-left:15px;">
		<strong id="fileStatus"></strong>
	</div>
</div>

<br />

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="file editor">
	<tr>
		<td class="tabcont" align="center">

<!-- controls -->
<table width="100%" cellpadding="9" cellspacing="9" summary="controls">
	<tr>
		<td align="center" class="list">
			<?=gettext("Save / Load from path"); ?>:
			<input type="text"   class="formfld file" id="fbTarget"         size="45" />
			<input type="button" class="formbtn"      onclick="loadFile();" value="<?=gettext('Load');?>" />
			<input type="button" class="formbtn"      id="fbOpen"           value="<?=gettext('Browse');?>" />
			<input type="button" class="formbtn"      onclick="saveFile();" value="<?=gettext('Save');?>" />
			<br />
			<?php
			/*
			<input type="checkbox" id="highlight" /><?=gettext("Enable syntax highlighting");
			*/
			?>
		</td>
	</tr>
</table>

<!-- filebrowser -->
<div id="fbBrowser" style="display:none; border:1px dashed gray; width:98%;"></div>

<!-- file viewer/editor -->
<table width="100%" summary="file editor">
	<tr>
		<td valign="top">
			<div style="background:#eeeeee;" id="fileOutput">
				<script type="text/javascript">
				//<![CDATA[
				window.onload=function(){
					document.getElementById("fileContent").wrap='off';
				}
				//]]>
				</script>
				<textarea id="fileContent" name="fileContent" style="width:100%;" rows="30" cols=""></textarea>
			</div>
		</td>
	</tr>
</table>

		</td>
	</tr>
</table>

<script type="text/javascript" src="/code-syntax-highlighter/shCore.js"></script>
<script type="text/javascript" src="/code-syntax-highlighter/shBrushCss.js"></script>
<script type="text/javascript" src="/code-syntax-highlighter/shBrushJScript.js"></script>
<script type="text/javascript" src="/code-syntax-highlighter/shBrushPhp.js"></script>
<script type="text/javascript" src="/code-syntax-highlighter/shBrushXml.js"></script>
<script type="text/javascript">
//<![CDATA[
	jQuery(window).load(
		function() {
			jQuery("#fbTarget").focus();

			NiftyCheck();
			Rounded("div#fileStatusBox", "all", "#ffffff", "#eeeeee", "smooth");
		}
	);

	<?php if($_GET['action'] == "load"): ?>
		jQuery(window).load(
			function() {
				jQuery("#fbTarget").val("<?=$_GET['path'];?>");
				loadFile();
			}
		);
	<?php endif; ?>
//]]>
</script>

<?php include("fend.inc"); ?>
</body>
</html>
