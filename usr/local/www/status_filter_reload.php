<?php
/* $Id$ */
/*
	status_filter_reload.php
	Copyright (C) 2006 Scott Ullrich
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
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-status-filterreloadstatus
##|*NAME=Status: Filter Reload Status page
##|*DESCR=Allow access to the 'Status: Filter Reload Status' page.
##|*MATCH=status_filter_reload.php*
##|-PRIV

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("functions.inc");

$pgtitle = array(gettext("Status"),gettext("Filter Reload Status"));
$shortcut_section = "firewall";

if(file_exists("{$g['varrun_path']}/filter_reload_status"))
	$status = file_get_contents("{$g['varrun_path']}/filter_reload_status");

if($_GET['getstatus']) {
	echo "|{$status}|";
	exit;
}
if($_POST['reloadfilter']) {
	send_event("filter reload");
	header("Location: status_filter_reload.php");
	exit;
}
if($_POST['syncfilter']) {
	send_event("filter sync");
	header("Location: status_filter_reload.php");
	exit;
}

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<br />
<form action="status_filter_reload.php" method="post" name="filter">
<input type="submit" value="Reload Filter" name="reloadfilter" id="reloadfilter" />
<?php if ($config['hasync'] && $config['hasync']["synchronizetoip"] != ""): ?>
&nbsp;&nbsp;&nbsp;&nbsp;
<input type="submit" value="Force Config Sync" name="syncfilter" id="syncfilter" />
<?php endif; ?>
</form>
<br /><br /><br />
<div id="status" style="padding:5px; border:1px dashed #990000; background-color: #ffffff; color: #000000;">
	<?php echo $status; ?>
</div>

<div id="doneurl">
</div>

<br/>

<div id="reloadinfo"><?=gettext("This page will automatically refresh every 3 seconds until the filter is done reloading"); ?>.</div>



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
	result_text = result_text.replace("\n","");
	result_text = result_text.replace("\r","");
	if (result_text) {
		jQuery('#status').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" /> ' + result_text + '...');
	} else {
		jQuery('#status').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" /> Obtaining filter status...');
	}
	if(result_text == "Initializing") {
		jQuery('#status').html('<img src="/themes/<?=$g['theme'];?>/images/misc/loader.gif" alt="loader" /> Initializing...');
	} else if(result_text == "Done") {
		jQuery('#status').effect('highlight');
		jQuery('#status').html('Done.  The filter rules have been reloaded.');
		jQuery('#reloadinfo').css("visibility","hidden");
		jQuery('#doneurl').css("visibility","visible");
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
    if (!url)
      throw 'No URL for getURL';

    try {
      if (typeof callback.operationComplete == 'function')
        callback = callback.operationComplete;
    } catch (e) {}
    if (typeof callback != 'function')
      throw 'No callback function for getURL';

    var http_request = null;
    if (typeof XMLHttpRequest != 'undefined') {
      http_request = new XMLHttpRequest();
    }
    else if (typeof ActiveXObject != 'undefined') {
      try {
        http_request = new ActiveXObject('Msxml2.XMLHTTP');
      } catch (e) {
        try {
          http_request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (e) {}
      }
    }
    if (!http_request)
      throw 'Both getURL and XMLHttpRequest are undefined';

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

<?php include("fend.inc"); ?>

</body>
</html>
