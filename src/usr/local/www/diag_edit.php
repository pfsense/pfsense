<?php
/*
	diag_edit.php
*/
/* ====================================================================
 *	Copyright (c)  2004-2015  Electric Sheep Fencing, LLC. All rights reserved.
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
##|*IDENT=page-diagnostics-edit
##|*NAME=Diagnostics: Edit File
##|*DESCR=Allow access to the 'Diagnostics: Edit File' page.
##|*MATCH=diag_edit.php*
##|*MATCH=browser.php*
##|*MATCH=vendor/filebrowser/browser.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Edit File"));
require("guiconfig.inc");

if ($_POST['action']) {
	switch ($_POST['action']) {
		case 'load':
			if (strlen($_POST['file']) < 1) {
				print('|5|');
				print_info_box(gettext("No file name specified."), 'danger');
				print('|');
			} elseif (is_dir($_POST['file'])) {
				print('|4|');
				print_info_box(gettext("Loading a directory is not supported."), 'danger');
				print('|');
			} elseif (!is_file($_POST['file'])) {
				print('|3|');
				print_info_box(gettext("File does not exist or is not a regular file."), 'danger');
				print('|');
			} else {
				$data = file_get_contents(urldecode($_POST['file']));
				if ($data === false) {
					print('|1|');
					print_info_box(gettext("Failed to read file."), 'danger');
					print('|');
				} else {
					$data = base64_encode($data);
					print("|0|{$_POST['file']}|{$data}|");
				}
			}
			exit;

		case 'save':
			if (strlen($_POST['file']) < 1) {
				print('|');
				print_info_box(gettext("No file name specified."), 'danger');
				print('|');
			} else {
				conf_mount_rw();
				$_POST['data'] = str_replace("\r", "", base64_decode($_POST['data']));
				$ret = file_put_contents($_POST['file'], $_POST['data']);
				conf_mount_ro();
				if ($_POST['file'] == "/conf/config.xml" || $_POST['file'] == "/cf/conf/config.xml") {
					if (file_exists("/tmp/config.cache")) {
						unlink("/tmp/config.cache");
					}
					disable_security_checks();
				}
				if ($ret === false) {
					print('|');
					print_info_box(gettext("Failed to write file."), 'danger');
					print('|');
				} elseif ($ret != strlen($_POST['data'])) {
					print('|');
					print_info_box(gettext("Error while writing file."), 'danger');
					print('|');
				} else {
					print('|');
					print_info_box(gettext("File saved successfully."), 'success');
					print('|');
				}
			}
			exit;
	}
	exit;
}

require("head.inc");

print_callout(gettext("The capabilities offered here can be dangerous. No support is available. Use them at your own risk!"), 'danger', gettext('Advanced Users Only'));

?>
<!-- file status box -->
<div style="display:none; background:#eeeeee;" id="fileStatusBox">
	<div id="fileStatus"></div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Save / Load a File from the Filesystem")?></h2></div>
	<div class="panel-body">
		<div class="content">
			<form>
				<p><input type="text" class="form-control" id="fbTarget"/></p>
				<div class="btn-group">
					<p>
						<button type="button" class="btn btn-default btn-sm" onclick="loadFile();"	value="<?=gettext('Load')?>">
							<i class="fa fa-file-text-o"></i>
							<?=gettext('Load')?>
						</button>
						<button type="button" class="btn btn-default btn-sm" id="fbOpen"		value="<?=gettext('Browse')?>">
							<i class="fa fa-list"></i>
							<?=gettext('Browse')?>
						</button>
						<button type="button" class="btn btn-default btn-sm" onclick="saveFile();"	value="<?=gettext('Save')?>">
							<i class="fa fa-save"></i>
							<?=gettext('Save')?>
						</button>
					</p>
				</div>
				<p class="pull-right">
					<button id="btngoto" class="btn btn-default btn-sm"><i class="fa fa-forward"></i><?=gettext("GoTo Line #")?></button> <input type="number" id="gotoline" size="6" style="padding: 3px 0px;"/>
				</p>
			</form>

			<div id="fbBrowser" style="display:none; border:1px dashed gray; width:98%; padding:10px"></div>

			<script type="text/javascript">
			//<![CDATA[
			window.onload=function() {
				document.getElementById("fileContent").wrap='off';
			}
			//]]>
			</script>
			<textarea id="fileContent" name="fileContent" class="form-control" rows="30" cols="20"></textarea>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function(){

		function showLine(tarea, lineNum) {

			lineNum--; // array starts at 0
			var lines = tarea.value.split("\n");

			// calculate start/end
			var startPos = 0, endPos = tarea.value.length;
			for(var x = 0; x < lines.length; x++) {
				if(x == lineNum) {
					break;
				}
				startPos += (lines[x].length+1);

			}

			var endPos = lines[lineNum].length+startPos;

			// do selection
			// Chrome / Firefox

			if(typeof(tarea.selectionStart) != "undefined") {
				tarea.focus();
				tarea.selectionStart = startPos;
				tarea.selectionEnd = endPos;
				return true;
			}

			// IE
			if (document.selection && document.selection.createRange) {
				tarea.focus();
				tarea.select();
				var range = document.selection.createRange();
				range.collapse(true);
				range.moveEnd("character", endPos);
				range.moveStart("character", startPos);
				range.select();
				return true;
			}

			return false;
		}

		$("#btngoto").prop('type','button');

		$('#btngoto').click(function() {
			var tarea = document.getElementById("fileContent");
			showLine(tarea, $('#gotoline').val());
		});
	});

	function loadFile() {
		$("#fileStatus").html("");
		$("#fileStatusBox").show(500);
		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME']?>", {
				type: "post",
				data: "action=load&file=" + $("#fbTarget").val(),
				complete: loadComplete
			}
		);
	}

	function loadComplete(req) {
		$("#fileContent").show(1000);
		var values = req.responseText.split("|");
		values.shift(); values.pop();

		if (values.shift() == "0") {
			var file = values.shift();
			var fileContent = window.atob(values.join("|"));

			$("#fileContent").val(fileContent);
		} else {
			$("#fileStatus").html(values[0]);
			$("#fileContent").val("");
		}

		$("#fileContent").show(1000);
	}

	function saveFile(file) {
		$("#fileStatus").html("");
		$("#fileStatusBox").show(500);

		var fileContent = Base64.encode($("#fileContent").val());
		fileContent = fileContent.replace(/\+/g, "%2B");

		$.ajax(
			"<?=$_SERVER['SCRIPT_NAME']?>", {
				type: "post",
				data: "action=save&file=" + $("#fbTarget").val() +
							"&data=" + fileContent,
				complete: function(req) {
					var values = req.responseText.split("|");
					$("#fileStatus").html(values[1]);
				}
			}
		);
	}

/**
 *
 *	Base64 encode / decode
 *	http://www.webtoolkit.info/
 *	http://www.webtoolkit.info/licence
 **/

var Base64 = {

	// private property
	_keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

	// public method for encoding
	encode : function (input) {
		var output = "";
		var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
		var i = 0;

		input = Base64._utf8_encode(input);

		while (i < input.length) {

			chr1 = input.charCodeAt(i++);
			chr2 = input.charCodeAt(i++);
			chr3 = input.charCodeAt(i++);

			enc1 = chr1 >> 2;
			enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
			enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
			enc4 = chr3 & 63;

			if (isNaN(chr2)) {
				enc3 = enc4 = 64;
			} else if (isNaN(chr3)) {
				enc4 = 64;
			}

			output = output +
			this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
			this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);

		}

		return output;
	},

	// public method for decoding
	decode : function (input) {
		var output = "";
		var chr1, chr2, chr3;
		var enc1, enc2, enc3, enc4;
		var i = 0;

		input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

		while (i < input.length) {

			enc1 = this._keyStr.indexOf(input.charAt(i++));
			enc2 = this._keyStr.indexOf(input.charAt(i++));
			enc3 = this._keyStr.indexOf(input.charAt(i++));
			enc4 = this._keyStr.indexOf(input.charAt(i++));

			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;

			output = output + String.fromCharCode(chr1);

			if (enc3 != 64) {
				output = output + String.fromCharCode(chr2);
			}
			if (enc4 != 64) {
				output = output + String.fromCharCode(chr3);
			}

		}

		output = Base64._utf8_decode(output);

		return output;

	},

	// private method for UTF-8 encoding
	_utf8_encode : function (string) {
		string = string.replace(/\r\n/g,"\n");
		var utftext = "";

		for (var n = 0; n < string.length; n++) {

			var c = string.charCodeAt(n);

			if (c < 128) {
				utftext += String.fromCharCode(c);
			} else if ((c > 127) && (c < 2048)) {
				utftext += String.fromCharCode((c >> 6) | 192);
				utftext += String.fromCharCode((c & 63) | 128);
			} else {
				utftext += String.fromCharCode((c >> 12) | 224);
				utftext += String.fromCharCode(((c >> 6) & 63) | 128);
				utftext += String.fromCharCode((c & 63) | 128);
			}

		}

		return utftext;
	},

	// private method for UTF-8 decoding
	_utf8_decode : function (utftext) {
		var string = "";
		var i = 0;
		var c = c1 = c2 = 0;

		while (i < utftext.length) {

			c = utftext.charCodeAt(i);

			if (c < 128) {
				string += String.fromCharCode(c);
				i++;
			} else if ((c > 191) && (c < 224)) {
				c2 = utftext.charCodeAt(i+1);
				string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
				i += 2;
			} else {
				c2 = utftext.charCodeAt(i+1);
				c3 = utftext.charCodeAt(i+2);
				string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
				i += 3;
			}

		}

		return string;
	}

};

	<?php if ($_GET['action'] == "load"): ?>
		events.push(function() {
			$("#fbTarget").val("<?=htmlspecialchars($_GET['path'])?>");
			loadFile();
		});
	<?php endif; ?>
//]]>
</script>

<?php include("foot.inc");

outputJavaScriptFileInline("vendor/filebrowser/browser.js");
