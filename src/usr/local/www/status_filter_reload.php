<?php
/*
	status_filter_reload.php
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
/*
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-status-filterreloadstatus
##|*NAME=Status: Filter Reload Status
##|*DESCR=Allow access to the 'Status: Filter Reload Status' page.
##|*MATCH=status_filter_reload.php*
##|-PRIV

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("functions.inc");

$pgtitle = array(gettext("Status"), gettext("Filter Reload"));
$shortcut_section = "firewall";

if (file_exists("{$g['varrun_path']}/filter_reload_status")) {
	$status = file_get_contents("{$g['varrun_path']}/filter_reload_status");
}

if ($_GET['getstatus']) {
	echo "|{$status}|";
	exit;
}
if ($_POST['reloadfilter']) {
	send_event("filter reload");
	header("Location: status_filter_reload.php");
	exit;
}
if ($_POST['syncfilter']) {
	send_event("filter sync");
	header("Location: status_filter_reload.php");
	exit;
}

include("head.inc");
?>


<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Filter Reload</h2></div>
	<div class="panel-body">
		<form action="status_filter_reload.php" method="post" name="filter">
			<input type="submit" class="btn btn-success" value="Reload Filter" name="reloadfilter" id="reloadfilter" />
<?php
if ($config['hasync'] && $config['hasync']["synchronizetoip"] != ""): ?>
		<input type="submit" class="btn btn-default" value="Force Config Sync" name="syncfilter" id="syncfilter" />
<?php
endif;
?>
		</form>

		<br />

		<div id="status" class="panel panel-default">
			<?=$status; ?>
		</div>

		<div id="doneurl">
		</div>

		<br/>

		<div id="reloadinfo"><?=gettext("This page will automatically refresh every 3 seconds until the filter is done reloading"); ?>.</div>

	</div>
</div>

<script type="text/javascript">
//<![CDATA[
/* init update "thread */
function update_status_thread() {
	getURL('status_filter_reload.php?getstatus=true', update_data);
}

function update_data(obj) {
	var result_text = obj.content;
	var result_text_split = result_text.split("|");
	result_text = result_text_split[1];
	result_text = result_text.replace("\n", "");
	result_text = result_text.replace("\r", "");
	if (result_text) {
		jQuery('#status').html(result_text + '...');
	} else {
		jQuery('#status').html('Obtaining filter status...');
	}
	if (result_text == "Initializing") {
		jQuery('#status').html('Initializing...');
	} else if (result_text == "Done") {
		jQuery('#status').effect('highlight');
		jQuery('#status').html('Done.  The filter rules have been reloaded.');
		jQuery('#reloadinfo').css("visibility", "hidden");
		jQuery('#doneurl').css("visibility", "visible");
		jQuery('#doneurl').html("<p><a href='status_queues.php'>Queue Status<\/a><\/p>");
	}
	window.setTimeout('update_status_thread()', 2500);
}
//]]>
</script>

<script type="text/javascript">
//<![CDATA[
/*
 * getURL is a proprietary Adobe function, but it's simplicity has made it very
 * popular. If getURL is undefined we spin our own by wrapping XMLHttpRequest.
 */
if (typeof getURL == 'undefined') {
	getURL = function(url, callback) {
		if (!url) {
			throw 'No URL for getURL';
		}

		try {
			if (typeof callback.operationComplete == 'function') {
				callback = callback.operationComplete;
			}
		} catch (e) {}
		if (typeof callback != 'function') {
			throw 'No callback function for getURL';
		}

		var http_request = null;
		if (typeof XMLHttpRequest != 'undefined') {
			http_request = new XMLHttpRequest();
		} else if (typeof ActiveXObject != 'undefined') {
			try {
				http_request = new ActiveXObject('Msxml2.XMLHTTP');
			} catch (e) {
				try {
					http_request = new ActiveXObject('Microsoft.XMLHTTP');
				} catch (e) {}
			}
		}
		if (!http_request) {
			throw 'Both getURL and XMLHttpRequest are undefined';
		}

		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				callback( { success : true,
					content : http_request.responseText,
					contentType : http_request.getResponseHeader("Content-Type") } );
			}
		}
		http_request.open('GET', url, true);
		http_request.send(null);
	}
}

window.setTimeout('update_status_thread()', 2500);
//]]>
</script>

<?php include("foot.inc");
