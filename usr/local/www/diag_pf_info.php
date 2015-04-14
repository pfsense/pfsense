<?php
/* $Id$ */
/*
	diag_pf_info.php
	Copyright (C) 2010 Scott Ullrich
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP
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
	pfSense_BUILDER_BINARIES:	/usr/bin/top
	pfSense_MODULE: system
*/

##|+PRIV
##|*IDENT=page-diagnostics-pf-info
##|*NAME=Diagnostics: pfInfo
##|*DESCR=Allows access to the 'Diagnostics: pfInfo' page
##|*MATCH=diag_pf_info.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = gettext("Diagnostics: pfInfo");

if (stristr($_POST['Submit'], gettext("No"))) {
	header("Location: index.php");
	exit;
}

if($_REQUEST['getactivity']) {
	$text = `/sbin/pfctl -vvsi`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsm`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvst`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsI`;
	echo $text;
	exit;
}

include("head.inc");

?>

<script type="text/javascript">
//<![CDATA[
	var run = true;

	function getpfinfo() {
		scroll(0,0);
		var url = "/diag_pf_info.php";
		var pars = 'getactivity=yes';

        if(run) {
    		jQuery.ajax(
    			url,
    			{
    				type: 'post',
    				data: pars,
    				complete: activitycallback
    			});
    			
        }
	}

	function activitycallback(transport) {
		jQuery('#pfactivitydiv').html('<font face="Courier" size="2"><pre style="text-align:left;">' + transport.responseText	+ '<\/pre><\/font>');
		setTimeout('getpfinfo()', 2500);
	}
	setTimeout('getpfinfo()', 1000);
//]]>
</script>

<div id="maincontent">
<?php
	if($savemsg) {
		echo "<div id=\"savemsg\">";
		print_info_box($savemsg);
		echo "</div>";
	}
	if ($input_errors)
		print_input_errors($input_errors);
?>
	<div class="panel panel-default">
		<div class="row">
			<div class=" checkbox col-sm-3">
				<label class="pull-right"><input type="checkbox" checked="yes" id="RunBox">Run <small>(Un-check to freeze the page)</small></label>
			</div>
		</div>
	</div>

	<div class="panel panel-default panel-info">
		<div class="panel-heading">Output</div>
		<div class="panel panel-body" id="pfactivitydiv">
			<?=gettext("Gathering PF information, please wait...")?>
		</div>
	</div>
</div>

<?php include("foot.inc"); ?>

<!-- JS allows the viewer to stop the automatic page refresh by un-clicking the "Run" checkbox -->
<script type="text/javascript">
//<![CDATA[
$(document).ready(function(){
	$('#RunBox').change(function(){
		if($(this).prop('checked') === true) {
		   run = true;
		   getpfinfo()
		}
		else
		   run = false;
	});
});
//]]>
</script>
