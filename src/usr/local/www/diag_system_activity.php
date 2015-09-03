<?php
/* $Id$ */
/*
	diag_system_activity.php
	Copyright (C) 2013-2015 Electric Sheep Fencing, LP

	Copyright (C) 2008-2009 Scott Ullrich
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
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-diagnostics-system-activity
##|*NAME=Diagnostics: System Activity
##|*DESCR=Allows access to the 'Diagnostics: System Activity' page
##|*MATCH=diag_system_activity.php*
##|-PRIV

require("guiconfig.inc");

$pgtitle = gettext("Diagnostics: System Activity");

if ($_REQUEST['getactivity']) {
	$text = `/usr/bin/top -aHS | /usr/bin/cut -c1-105`;
	echo $text;
	exit;
}

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
	function getcpuactivity() {
		scroll(0, 0);
		var url = "/diag_system_activity.php";
		var pars = 'getactivity=yes';
		jQuery.ajax(
			url,
			{
				type: 'post',
				data: pars,
				complete: activitycallback
			});
	}
	function activitycallback(transport) {
		jQuery('#cpuactivitydiv').html('<font face="Courier" size="2"><pre style="text-align:left;">' + transport.responseText + '<\/pre><\/font>');
		setTimeout('getcpuactivity()', 2500);
	}
	setTimeout('getcpuactivity()', 1000);
//]]>
</script>
<div id="maincontent">
<?php
	if ($savemsg) {
		echo "<div id=\"savemsg\">";
		print_info_box($savemsg);
		echo "</div>";
	}
	if ($input_errors) {
		print_input_errors($input_errors);
	}
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="diag system activity">
	<tr>
		<td>
			<table id="backuptable" class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0" summary="tabcont">
				<tr>
					<td align="center">
						<table summary="results">
							<tr>
								<td>
									<div id="cpuactivitydiv">
										<?=gettext("Gathering CPU activity, please wait...");?>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</div>
<?php include("fend.inc"); ?>
</body>
</html>
